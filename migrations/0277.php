<?php

// DAIRY Etapa 19: prevención de pérdidas y operaciones multisucursal.
// Solo agrega tablas, permisos y maestros (reglas de alerta, plantilla de auditoría); no modifica datos existentes.

use Grocy\Services\DatabaseService;

$db = DatabaseService::GetInstance()->GetDbConnectionRaw();

$db->exec("
-- Retiro de producto (recall): un documento por producto/lotes, una fila por lote retenido en cada sucursal
CREATE TABLE IF NOT EXISTS product_recalls (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	recall_code TEXT NOT NULL UNIQUE,
	product_id INTEGER NOT NULL,
	supplier_id INTEGER,
	best_before_from DATE,
	best_before_to DATE,
	reason TEXT NOT NULL,
	severity TEXT NOT NULL DEFAULT 'class_2', -- class_1 riesgo grave · class_2 · class_3
	disposition TEXT NOT NULL DEFAULT 'destroy', -- destroy | return_supplier
	status TEXT NOT NULL DEFAULT 'open', -- open | closed | cancelled
	created_by_user_id INTEGER,
	created_at DATETIME,
	closed_by_user_id INTEGER,
	closed_at DATETIME,
	close_notes TEXT
);
CREATE TABLE IF NOT EXISTS product_recall_items (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	recall_id INTEGER NOT NULL,
	branch_id INTEGER,
	location_id INTEGER,
	stock_id TEXT NOT NULL,
	best_before_date DATE,
	amount REAL NOT NULL,
	quarantine_id INTEGER,
	status TEXT NOT NULL DEFAULT 'quarantined', -- quarantined | confirmed | disposed
	found_amount REAL,
	confirmed_by_user_id INTEGER,
	confirmed_at DATETIME,
	waste_record_id INTEGER,
	notes TEXT
);
CREATE INDEX IF NOT EXISTS ix_recall_items_recall ON product_recall_items(recall_id);

-- Auditorías de tienda (checklist ponderado por sucursal)
CREATE TABLE IF NOT EXISTS audit_templates (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	name TEXT NOT NULL,
	area TEXT,
	active INTEGER NOT NULL DEFAULT 1,
	created_at DATETIME
);
CREATE TABLE IF NOT EXISTS audit_template_items (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	template_id INTEGER NOT NULL,
	question TEXT NOT NULL,
	weight REAL NOT NULL DEFAULT 1,
	is_critical INTEGER NOT NULL DEFAULT 0,
	sort_order INTEGER NOT NULL DEFAULT 0,
	active INTEGER NOT NULL DEFAULT 1
);
CREATE TABLE IF NOT EXISTS store_audits (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	audit_code TEXT NOT NULL UNIQUE,
	template_id INTEGER NOT NULL,
	branch_id INTEGER NOT NULL,
	status TEXT NOT NULL DEFAULT 'in_progress', -- in_progress | completed
	auditor_user_id INTEGER,
	started_at DATETIME,
	completed_at DATETIME,
	score REAL,
	critical_failures INTEGER NOT NULL DEFAULT 0,
	notes TEXT
);
CREATE TABLE IF NOT EXISTS store_audit_answers (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	audit_id INTEGER NOT NULL,
	item_id INTEGER NOT NULL,
	result TEXT NOT NULL, -- pass | fail | na
	comment TEXT,
	task_id INTEGER,
	UNIQUE(audit_id, item_id)
);

-- Certificaciones del personal y requisitos por puesto/rol del turno
CREATE TABLE IF NOT EXISTS employee_certifications (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	employee_id INTEGER NOT NULL,
	name TEXT NOT NULL,
	issued_date DATE,
	expires_date DATE,
	notes TEXT
);
CREATE TABLE IF NOT EXISTS role_certification_requirements (
	role TEXT NOT NULL,
	certification TEXT NOT NULL,
	PRIMARY KEY (role, certification)
);

-- Centro de alertas: reglas configurables, alertas con responsable y bitácora hasta el cierre
CREATE TABLE IF NOT EXISTS dairy_alert_rules (
	code TEXT PRIMARY KEY,
	name TEXT NOT NULL,
	description TEXT,
	enabled INTEGER NOT NULL DEFAULT 1,
	threshold REAL,
	threshold_label TEXT,
	severity TEXT NOT NULL DEFAULT 'warning', -- danger | warning | info
	due_hours INTEGER NOT NULL DEFAULT 24,
	requires_approval INTEGER NOT NULL DEFAULT 0,
	owner_user_id INTEGER
);
CREATE TABLE IF NOT EXISTS dairy_alerts (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	rule_code TEXT NOT NULL,
	dedupe_key TEXT NOT NULL UNIQUE,
	branch_id INTEGER,
	severity TEXT NOT NULL,
	title TEXT NOT NULL,
	detail TEXT,
	value REAL,
	href TEXT,
	status TEXT NOT NULL DEFAULT 'open', -- open | in_progress | pending_approval | closed | dismissed
	owner_user_id INTEGER,
	due_at DATETIME,
	created_at DATETIME,
	updated_at DATETIME,
	last_seen_at DATETIME,
	resolution TEXT,
	resolved_by_user_id INTEGER,
	resolved_at DATETIME,
	approved_by_user_id INTEGER,
	approved_at DATETIME
);
CREATE INDEX IF NOT EXISTS ix_dairy_alerts_status ON dairy_alerts(status, branch_id);
CREATE TABLE IF NOT EXISTS dairy_alert_events (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	alert_id INTEGER NOT NULL,
	user_id INTEGER,
	action TEXT NOT NULL,
	note TEXT,
	created_at DATETIME
);
");

$rules = [
	['phantom_stock', 'Stock fantasma', 'El sistema muestra stock, pero un producto que se vende con regularidad lleva días sin venderse: probable faltante físico. Umbral = probabilidad máxima de que la racha sin ventas sea casual.', 0.05, 'probabilidad', 'warning', 24, 0],
	['unexplained_outflow', 'Salidas sin documento', 'Consumos de stock que no provienen de venta, merma, traslado ni ajuste aprobado. Umbral = valor mínimo ($).', 5, '$ mínimo', 'warning', 48, 1],
	['pos_unreconciled', 'Venta sin descargo de inventario', 'Ventas completadas cuyas líneas no tienen movimiento de stock: el inventario queda sobrestimado.', 0, '$ mínimo', 'danger', 24, 0],
	['inventory_anomaly', 'Movimiento anómalo', 'Duplicados, correcciones atípicas, mermas sin caso, fuera de horario o anulaciones repetidas (Kardex → Control de movimientos).', null, null, 'warning', 48, 0],
	['cold_chain_breach', 'Ruptura de cadena de frío', 'Lecturas de temperatura fuera de rango en las últimas 24 h. Requiere aprobación del cierre.', null, null, 'danger', 4, 1],
	['recall_unconfirmed', 'Retiro sin confirmar', 'Lotes de un retiro de producto que la sucursal no confirmó como separados físicamente. Umbral = horas.', 4, 'horas', 'danger', 4, 0],
	['audit_failed', 'Auditoría reprobada', 'Auditoría de tienda bajo el puntaje mínimo o con incumplimientos críticos. Umbral = puntaje mínimo (%).', 80, '% mínimo', 'danger', 72, 1],
	['cert_expiring', 'Certificación por vencer', 'Certificaciones del personal vencidas o por vencer. Umbral = días de anticipación.', 30, 'días', 'warning', 168, 0],
	['high_value_waste', 'Merma de alto valor', 'Merma aprobada en los últimos 7 días sobre el umbral: confirmar causa y acción correctiva. Umbral = $.', 50, '$ mínimo', 'warning', 72, 1],
];
$ins = $db->prepare('INSERT OR IGNORE INTO dairy_alert_rules (code, name, description, threshold, threshold_label, severity, due_hours, requires_approval) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
foreach ($rules as $r)
{
	$ins->execute($r);
}

// Plantilla base de auditoría (editable desde /audits)
if (intval($db->query('SELECT COUNT(*) FROM audit_templates')->fetchColumn()) === 0)
{
	$db->exec("INSERT INTO audit_templates (name, area, created_at) VALUES ('Auditoría de tienda – frescos y lácteos', 'Lácteos', datetime('now', 'localtime'))");
	$tid = intval($db->lastInsertId());
	$items = [
		['Equipos de frío entre 0 y 5 °C y registro de temperatura del turno completo', 3, 1],
		['Sin productos vencidos en exhibición ni en bodega', 3, 1],
		['Rotación FEFO: lo que vence primero está al frente', 2, 0],
		['Producto en cuarentena o retiro separado e identificado', 3, 1],
		['Precios y rebajas correctamente etiquetados', 1, 0],
		['Empaques íntegros (sin abolladuras, fugas ni inflados)', 2, 0],
		['Recepción: mercancía verificada contra orden antes de almacenar', 2, 0],
		['Limpieza de góndolas y cámaras según cronograma', 1, 0],
		['Mermas del día registradas con evidencia', 2, 0],
		['Puertas de cámara cerradas y cortinas en buen estado', 1, 0],
	];
	$insI = $db->prepare('INSERT INTO audit_template_items (template_id, question, weight, is_critical, sort_order) VALUES (?, ?, ?, ?, ?)');
	foreach ($items as $i => $it)
	{
		$insI->execute([$tid, $it[0], $it[1], $it[2], $i + 1]);
	}
}

// Permisos: LOSS_PREVENTION (alertas, retiros, auditorías) bajo ADMIN
$adminId = intval($db->query("SELECT id FROM permission_hierarchy WHERE name = 'ADMIN'")->fetchColumn());
$insert = $db->prepare('INSERT OR IGNORE INTO permission_hierarchy (name, parent) VALUES (:name, :parent)');
$getId = $db->prepare('SELECT id FROM permission_hierarchy WHERE name = :name');
$insert->execute([':name' => 'LOSS_PREVENTION', ':parent' => $adminId]);
$getId->execute([':name' => 'LOSS_PREVENTION']);
$groupId = intval($getId->fetchColumn());
foreach (['ALERTS_MANAGE', 'RECALL_MANAGE', 'AUDIT_PERFORM'] as $child)
{
	$insert->execute([':name' => $child, ':parent' => $groupId]);
}
