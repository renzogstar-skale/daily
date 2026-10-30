<?php

// DAIRY Centro de Alertas v2: categoría, tipo, prioridad P1–P4 explicada, SLA, escalamiento, evidencias, plan de acción,
// notificaciones (dedupe), vistas guardadas y reglas nuevas (inventario, compras, calidad, personal, ventas).
// Solo agrega columnas/tablas y completa valores derivados de la regla; no borra ni altera datos operativos. Idempotente.

$db = \Grocy\Services\DatabaseService::GetInstance()->GetDbConnectionRaw();

$addCols = function (string $table, array $cols) use ($db) {
	$have = array_column($db->query("PRAGMA table_info($table)")->fetchAll(\PDO::FETCH_ASSOC), 'name');
	foreach ($cols as $name => $def)
	{
		if (!in_array($name, $have, true))
		{
			$db->exec("ALTER TABLE $table ADD COLUMN $name $def");
		}
	}
};

$addCols('dairy_alert_rules', [
	'category' => "TEXT NOT NULL DEFAULT 'other'",
	'alert_type' => "TEXT NOT NULL DEFAULT 'other'",
	'department' => 'TEXT',
	'impact_bucket' => "TEXT NOT NULL DEFAULT 'process'", // sales_risk | inventory | waste | labor | process
	'impact_kind' => "TEXT NOT NULL DEFAULT 'estimated'", // confirmed | estimated | recoverable
	'food_safety' => 'INTEGER NOT NULL DEFAULT 0',
	'confidence' => "TEXT NOT NULL DEFAULT 'high'", // high = regla determinística · medium = estadística
	'cooldown_hours' => 'INTEGER NOT NULL DEFAULT 0',
	'escalate_after_hours' => 'INTEGER',
	'notify_channel' => "TEXT NOT NULL DEFAULT 'app'", // app | app_email
	'recommended_action' => 'TEXT',
	'branch_id' => 'INTEGER', // NULL = todas las sucursales
	'frequency_minutes' => 'INTEGER NOT NULL DEFAULT 15',
	'last_run_at' => 'DATETIME',
]);

$addCols('dairy_alerts', [
	'source' => "TEXT NOT NULL DEFAULT 'rule'", // rule | manual
	'category' => "TEXT NOT NULL DEFAULT 'other'",
	'alert_type' => "TEXT NOT NULL DEFAULT 'other'",
	'department' => 'TEXT',
	'product_id' => 'INTEGER',
	'priority' => "TEXT NOT NULL DEFAULT 'P3'",
	'priority_score' => 'INTEGER',
	'priority_reason' => 'TEXT',
	'priority_locked' => 'INTEGER NOT NULL DEFAULT 0',
	'impact_bucket' => "TEXT NOT NULL DEFAULT 'process'",
	'impact_kind' => "TEXT NOT NULL DEFAULT 'estimated'",
	'impact_hours' => 'REAL',
	'occurrences' => 'INTEGER NOT NULL DEFAULT 1',
	'first_response_at' => 'DATETIME',
	'escalation_level' => 'INTEGER NOT NULL DEFAULT 0',
	'escalated_at' => 'DATETIME',
	'reopened_count' => 'INTEGER NOT NULL DEFAULT 0',
	'prev_status' => 'TEXT',
	'created_by_user_id' => 'INTEGER',
]);

$db->exec("
CREATE INDEX IF NOT EXISTS ix_dairy_alerts_created ON dairy_alerts(created_at);
CREATE INDEX IF NOT EXISTS ix_dairy_alerts_resolved ON dairy_alerts(resolved_at);
CREATE INDEX IF NOT EXISTS ix_dairy_alerts_owner ON dairy_alerts(owner_user_id, status);
CREATE INDEX IF NOT EXISTS ix_dairy_alerts_cat ON dairy_alerts(category, priority);
CREATE INDEX IF NOT EXISTS ix_dairy_alert_events_alert ON dairy_alert_events(alert_id, id);

-- Todas las claves de hallazgo de una alerta (agrupación: varios hallazgos del mismo producto en una sola alerta)
CREATE TABLE IF NOT EXISTS dairy_alert_keys (
	dedupe_key TEXT PRIMARY KEY,
	alert_id INTEGER NOT NULL REFERENCES dairy_alerts(id),
	created_at DATETIME NOT NULL
);
CREATE INDEX IF NOT EXISTS ix_dairy_alert_keys_alert ON dairy_alert_keys(alert_id);

CREATE TABLE IF NOT EXISTS dairy_alert_evidence (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	alert_id INTEGER NOT NULL REFERENCES dairy_alerts(id),
	file_name TEXT NOT NULL,
	original_name TEXT,
	mime TEXT NOT NULL,
	size INTEGER NOT NULL DEFAULT 0,
	note TEXT,
	user_id INTEGER,
	created_at DATETIME NOT NULL
);
CREATE INDEX IF NOT EXISTS ix_dairy_alert_evidence_alert ON dairy_alert_evidence(alert_id);

-- Plan de acción correctiva: pasos con responsable, plazo y resultado (mide efectividad de acciones)
CREATE TABLE IF NOT EXISTS dairy_alert_actions (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	alert_id INTEGER NOT NULL REFERENCES dairy_alerts(id),
	description TEXT NOT NULL,
	owner_user_id INTEGER,
	due_at DATETIME,
	status TEXT NOT NULL DEFAULT 'pending', -- pending | done | cancelled
	result TEXT,
	done_by_user_id INTEGER,
	done_at DATETIME,
	created_by_user_id INTEGER,
	created_at DATETIME NOT NULL
);
CREATE INDEX IF NOT EXISTS ix_dairy_alert_actions_alert ON dairy_alert_actions(alert_id, status);

-- Notificaciones dentro de DAIRY (y cola de correo si está configurado). dedupe_key evita duplicados.
CREATE TABLE IF NOT EXISTS dairy_alert_notifications (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	alert_id INTEGER NOT NULL,
	user_id INTEGER NOT NULL,
	kind TEXT NOT NULL, -- new | assigned | reminder | escalated | approval | resolved | reopened | help
	channel TEXT NOT NULL DEFAULT 'app',
	title TEXT NOT NULL,
	body TEXT,
	dedupe_key TEXT NOT NULL UNIQUE,
	created_at DATETIME NOT NULL,
	read_at DATETIME,
	email_status TEXT -- NULL (solo app) | pending | sent | not_configured
);
CREATE INDEX IF NOT EXISTS ix_dairy_alert_notif_user ON dairy_alert_notifications(user_id, read_at);

CREATE TABLE IF NOT EXISTS dairy_alert_views (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	user_id INTEGER NOT NULL,
	name TEXT NOT NULL,
	query TEXT NOT NULL,
	created_at DATETIME NOT NULL,
	UNIQUE (user_id, name)
);
");

// Metadatos de reglas existentes: [categoría, tipo, departamento, bucket de impacto, tipo de impacto, seguridad alimentaria, confianza, enfriamiento h, acción recomendada]
$meta = [
	'phantom_stock' => ['inventory', 'count_diff', 'Inventario', 'sales_risk', 'estimated', 0, 'medium', 0, 'Contar a ciegas el producto en góndola y bodega; ajustar el inventario con aprobación si falta.'],
	'unexplained_outflow' => ['security', 'waste', 'Prevención de pérdidas', 'waste', 'confirmed', 0, 'high', 24, 'Revisar el movimiento en el kardex con quien lo registró y documentar la causa (merma, venta o error).'],
	'pos_unreconciled' => ['systems', 'sales', 'Sistemas', 'inventory', 'confirmed', 0, 'high', 0, 'Reprocesar la venta en la integración POS para descargar el inventario.'],
	'inventory_anomaly' => ['inventory', 'other', 'Inventario', 'inventory', 'estimated', 0, 'medium', 0, 'Validar el movimiento con el responsable y anularlo o documentarlo.'],
	'cold_chain_breach' => ['quality', 'cold_chain', 'Calidad', 'inventory', 'estimated', 1, 'high', 0, 'Inspeccionar la cámara, registrar la temperatura y poner en cuarentena los lotes expuestos.'],
	'recall_unconfirmed' => ['quality', 'other', 'Calidad', 'inventory', 'estimated', 1, 'high', 0, 'Separar físicamente los lotes del retiro y confirmarlo en Retiros.'],
	'audit_failed' => ['operations', 'maintenance', 'Operaciones', 'process', 'estimated', 0, 'high', 0, 'Cerrar las tareas correctivas de la auditoría y programar re-auditoría.'],
	'cert_expiring' => ['staff', 'staff', 'Personal', 'labor', 'estimated', 0, 'high', 0, 'Programar la renovación de la certificación antes del vencimiento.'],
	'high_value_waste' => ['waste', 'waste', 'Mermas', 'waste', 'confirmed', 0, 'high', 0, 'Confirmar causa raíz con evidencia y definir acción correctiva para la causal.'],
	'expiring_lots' => ['quality', 'expiry', 'Calidad', 'waste', 'recoverable', 0, 'high', 0, 'Rebajar (FEFO), trasladar o reubicar al frente antes del vencimiento.'],
];

// Reglas nuevas con detector real (AlertCenterService::Detect)
$new = [
	['low_stock', 'Stock bajo mínimo', 'Productos cuyo stock en la sucursal está por debajo del mínimo configurado en el producto.', null, null, 'warning', 24, 0, ['inventory', 'stock_low', 'Inventario', 'sales_risk', 'estimated', 0, 'high', 0, 'Generar orden de compra o traslado desde otra sucursal.']],
	['negative_stock', 'Stock negativo', 'Productos con existencia negativa en la sucursal: hay movimientos registrados sin stock.', null, null, 'danger', 8, 0, ['inventory', 'count_diff', 'Inventario', 'inventory', 'confirmed', 0, 'high', 0, 'Contar el producto y corregir la recepción o venta que falta registrar.']],
	['count_difference', 'Diferencia de conteo', 'Conteos cíclicos con diferencia contra el sistema sin ajustar. Umbral = $ mínimo de diferencia.', 10, '$ mínimo', 'warning', 24, 1, ['inventory', 'count_diff', 'Inventario', 'inventory', 'confirmed', 0, 'high', 0, 'Recontar y aprobar el ajuste con la causa documentada.']],
	['count_overdue', 'Conteo vencido', 'Conteos cíclicos programados cuya fecha ya pasó sin ejecutarse.', null, null, 'warning', 24, 0, ['inventory', 'count_diff', 'Inventario', 'process', 'estimated', 0, 'high', 0, 'Ejecutar el conteo pendiente o reprogramarlo.']],
	['transfer_pending', 'Traslado sin recibir', 'Traslados en tránsito sin recepción después del umbral. Umbral = horas.', 24, 'horas', 'warning', 24, 0, ['inventory', 'other', 'Inventario', 'inventory', 'estimated', 0, 'high', 0, 'Confirmar la llegada y recibir el traslado con sus diferencias.']],
	['po_late', 'Pedido atrasado', 'Órdenes de compra enviadas cuya fecha de entrega ya pasó sin recepción.', null, null, 'warning', 24, 0, ['purchasing', 'purchasing', 'Compras', 'sales_risk', 'estimated', 0, 'high', 0, 'Contactar al proveedor y reprogramar la entrega o buscar alternativa.']],
	['quarantine_stale', 'Cuarentena sin decisión', 'Lotes en cuarentena sin liberar ni dar de baja después del umbral. Umbral = días.', 2, 'días', 'danger', 24, 0, ['quality', 'other', 'Calidad', 'inventory', 'estimated', 1, 'high', 0, 'Decidir liberación o baja del lote en Calidad.']],
	['shift_unpublished', 'Horario sin publicar', 'Turnos en borrador para los próximos días: el personal no los ve. Umbral = días de anticipación.', 2, 'días', 'warning', 12, 0, ['staff', 'staff', 'Personal', 'labor', 'estimated', 0, 'high', 0, 'Revisar y publicar el horario de la semana.']],
	['missing_punch', 'Turno sin fichaje', 'Turnos publicados de hoy que ya iniciaron hace más del umbral sin marca de entrada. Umbral = minutos.', 30, 'minutos', 'warning', 4, 0, ['staff', 'staff', 'Personal', 'labor', 'estimated', 0, 'high', 0, 'Confirmar asistencia o cubrir el turno con otra persona.']],
	['absence_pending', 'Ausencia sin decidir', 'Solicitudes de ausencia que inician en los próximos días sin aprobar ni rechazar. Umbral = días.', 3, 'días', 'info', 24, 0, ['staff', 'staff', 'Personal', 'labor', 'estimated', 0, 'high', 0, 'Decidir la ausencia y ajustar la cobertura del turno.']],
	['sales_drop', 'Caída de ventas', 'La venta de ayer quedó por debajo del promedio de los mismos días de las 4 semanas previas. Umbral = % de caída.', 30, '% caída', 'warning', 24, 0, ['sales', 'sales', 'Ventas', 'sales_risk', 'estimated', 0, 'medium', 0, 'Revisar disponibilidad de los productos más vendidos, precios y cobertura de cajas.']],
	['low_margin', 'Margen bajo objetivo', 'Margen bruto de los últimos 7 días bajo el objetivo (presupuesto del mes o umbral). Umbral = % margen mínimo.', 20, '% mínimo', 'warning', 72, 0, ['sales', 'sales', 'Ventas', 'sales_risk', 'estimated', 0, 'high', 0, 'Revisar costos de compra, rebajas aplicadas y precios de venta.']],
];
$ins = $db->prepare('INSERT OR IGNORE INTO dairy_alert_rules (code, name, description, threshold, threshold_label, severity, due_hours, requires_approval) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
foreach ($new as $r)
{
	$ins->execute(array_slice($r, 0, 8));
	$meta[$r[0]] = $r[8];
}

// Completa metadatos solo donde siguen en el valor por defecto (no pisa configuración del usuario)
$up = $db->prepare("UPDATE dairy_alert_rules SET category = ?, alert_type = ?, department = IFNULL(department, ?), impact_bucket = ?, impact_kind = ?, food_safety = ?, confidence = ?, cooldown_hours = CASE WHEN cooldown_hours = 0 THEN ? ELSE cooldown_hours END, recommended_action = IFNULL(recommended_action, ?)
	WHERE code = ? AND category = 'other'");
foreach ($meta as $code => $m)
{
	$up->execute(array_merge($m, [$code]));
}

// Alertas existentes: heredan categoría/tipo/impacto de su regla; prioridad inicial desde la severidad (el barrido la recalcula con motivo)
$db->exec("UPDATE dairy_alerts SET
	category = IFNULL((SELECT r.category FROM dairy_alert_rules r WHERE r.code = dairy_alerts.rule_code), category),
	alert_type = IFNULL((SELECT r.alert_type FROM dairy_alert_rules r WHERE r.code = dairy_alerts.rule_code), alert_type),
	department = IFNULL(department, (SELECT r.department FROM dairy_alert_rules r WHERE r.code = dairy_alerts.rule_code)),
	impact_bucket = IFNULL((SELECT r.impact_bucket FROM dairy_alert_rules r WHERE r.code = dairy_alerts.rule_code), impact_bucket),
	impact_kind = IFNULL((SELECT r.impact_kind FROM dairy_alert_rules r WHERE r.code = dairy_alerts.rule_code), impact_kind),
	priority = CASE severity WHEN 'danger' THEN 'P2' WHEN 'warning' THEN 'P3' ELSE 'P4' END,
	priority_reason = IFNULL(priority_reason, 'Prioridad inicial por severidad de la regla')
	WHERE category = 'other'");
$db->exec("INSERT OR IGNORE INTO dairy_alert_keys (dedupe_key, alert_id, created_at) SELECT dedupe_key, id, IFNULL(created_at, datetime('now', 'localtime')) FROM dairy_alerts");
// Primera respuesta histórica: primer evento humano posterior a la creación
$db->exec("UPDATE dairy_alerts SET first_response_at = (SELECT MIN(e.created_at) FROM dairy_alert_events e WHERE e.alert_id = dairy_alerts.id AND e.user_id IS NOT NULL AND e.action NOT IN ('created', 'scan'))
	WHERE first_response_at IS NULL");
