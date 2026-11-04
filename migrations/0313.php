<?php

// DAILY — Control integral de mercancías: tipo de control por producto, causas confirmadas y libro central.
// merchandise_movements es una VISTA de stock_log: no hay un segundo inventario. Clasifica cada fila una sola vez con las
// mismas reglas de LossReconciliationService::Bucket (test_merchandise_control comprueba que coinciden).

$db = \Grocy\Services\DatabaseService::GetInstance()->GetDbConnectionRaw();

$add = function (string $table, string $col, string $def) use ($db) {
	$cols = array_column($db->query("PRAGMA table_info($table)")->fetchAll(\PDO::FETCH_ASSOC), 'name');
	if (!in_array($col, $cols, true))
	{
		$db->exec("ALTER TABLE $table ADD COLUMN $col $def");
	}
};

// Un conteo puede limitarse a un producto: lo no contado no es faltante
$add('device_counts', 'product_id', 'INTEGER');
$add('products', 'control_type', "TEXT NOT NULL DEFAULT 'quantity'"); // quantity | lot | serial | weight | pack
$add('products', 'risk_level', "TEXT NOT NULL DEFAULT 'low'"); // low | medium | high
$add('products', 'count_tolerance_pct', 'REAL NOT NULL DEFAULT 0');
\Grocy\Helpers\DairyChangeLog::InstallTriggers($db, ['products']);

$db->exec("UPDATE products SET control_type = CASE
	WHEN device_control = 1 OR imei_tracking = 1 OR is_serialized = 1 THEN 'serial'
	WHEN qu_id_stock IN (SELECT id FROM quantity_units WHERE name IN ('Kilogramo', 'Gramo', 'Libra', 'Litro', 'Mililitro')) THEN 'weight'
	WHEN default_best_before_days > 0 AND default_best_before_days <= 365 THEN 'lot'
	WHEN EXISTS (SELECT 1 FROM quantity_unit_conversions c WHERE c.product_id = products.id) THEN 'pack'
	ELSE 'quantity' END,
	risk_level = CASE WHEN is_sensitive = 1 THEN 'high' WHEN IFNULL(sale_price, 0) >= 100 THEN 'medium' ELSE 'low' END");

$add('sensitive_investigation_cases', 'confirmed_cause', 'TEXT');
$add('sensitive_investigation_cases', 'reopened_count', 'INTEGER NOT NULL DEFAULT 0');

$db->exec("
CREATE INDEX IF NOT EXISTS ix_stock_log_transaction ON stock_log (transaction_id);
CREATE INDEX IF NOT EXISTS ix_sim_transaction ON sensitive_inventory_movements (stock_transaction_id);

DROP VIEW IF EXISTS merchandise_movements;
CREATE VIEW merchandise_movements AS
SELECT sl.id, 'MOV-' || sl.id AS movement_code, sl.transaction_id AS operation_ref, sl.transaction_type, sl.product_id, sl.amount, sl.price AS unit_cost,
	ABS(sl.amount * IFNULL(sl.price, 0)) AS value, sl.stock_id, sl.location_id, l.name AS location_name, l.warehouse, l.branch_id, IFNULL(l.is_transit, 0) AS is_transit,
	sl.user_id, sl.note, sl.undone, sl.undone_timestamp, sl.row_created_timestamp AS recorded_at,
	IFNULL((SELECT IFNULL(pe.occurred_at, s.created_timestamp) FROM sale_items si JOIN sales s ON s.id = si.sale_id LEFT JOIN pos_external_sales pe ON pe.sale_id = s.id WHERE si.transaction_id = sl.transaction_id LIMIT 1), sl.row_created_timestamp) AS effective_at,
	CASE
		WHEN sl.transaction_type IN ('transfer_from', 'transfer_to') THEN
			CASE WHEN IFNULL(l.is_transit, 0) = 1 THEN 'transit'
			WHEN EXISTS (SELECT 1 FROM stock_log o JOIN locations ol ON ol.id = o.location_id WHERE o.transaction_id = sl.transaction_id AND o.undone = 0 AND o.transaction_type = CASE sl.transaction_type WHEN 'transfer_from' THEN 'transfer_to' ELSE 'transfer_from' END AND ol.branch_id = l.branch_id AND IFNULL(ol.is_transit, 0) = 0) THEN 'internal'
			WHEN sl.transaction_type = 'transfer_from' THEN 'transfers_out' ELSE 'transfers_in' END
		WHEN sl.transaction_type IN ('purchase', 'self-production') THEN
			CASE WHEN sl.note LIKE 'Devolución%' OR EXISTS (SELECT 1 FROM sensitive_inventory_movements m WHERE m.stock_transaction_id = sl.transaction_id AND m.product_id = sl.product_id AND m.event_type = 'return_exchange') THEN 'returns'
			WHEN EXISTS (SELECT 1 FROM sensitive_inventory_movements m WHERE m.stock_transaction_id = sl.transaction_id AND m.product_id = sl.product_id AND m.event_type = 'adjustment') THEN 'adjustments'
			ELSE 'receptions' END
		WHEN sl.transaction_type = 'consume' THEN
			CASE WHEN EXISTS (SELECT 1 FROM sale_items si WHERE si.transaction_id = sl.transaction_id) THEN 'sales'
			WHEN sl.spoiled = 1 OR EXISTS (SELECT 1 FROM waste_records w WHERE w.transaction_id = sl.transaction_id AND w.product_id = sl.product_id) THEN 'waste'
			ELSE 'dispatches' END
		ELSE 'adjustments'
	END AS bucket
FROM stock_log sl
JOIN locations l ON l.id = sl.location_id
WHERE sl.transaction_type <> 'product-opened';
");

// Causas: se reutiliza el catálogo canónico loss_causes. Solo se le agrega cómo actúa al cerrar un expediente.
// kind: waste = pérdida física confirmada | process = error sin pérdida física | unexplained | theft | other (no aplica a expedientes)
$add('loss_causes', 'kind', "TEXT NOT NULL DEFAULT 'other'");
$add('loss_causes', 'requires_manager', 'INTEGER NOT NULL DEFAULT 0');
$add('loss_causes', 'requires_evidence', 'INTEGER NOT NULL DEFAULT 0');
$insCause = $db->prepare("INSERT OR IGNORE INTO loss_causes (code, name, loss_class, responsible_area, approval_rule, treatment, is_definitive) VALUES (?, ?, 'ajuste', 'Control de inventario', 'Supervisor valida', 'Corregir el registro y documentar la causa', 0)");
foreach ([['RERR', 'Error de recepción'], ['CERR', 'Error de conteo'], ['PERR', 'Error de procedimiento'], ['DDIF', 'Diferencia de despacho'], ['TDIF', 'Diferencia de traslado'], ['OTRA', 'Otra causa']] as $c)
{
	$insCause->execute($c);
}
$db->exec("UPDATE loss_causes SET kind = 'waste' WHERE code IN ('ROT', 'EXP', 'CFR', 'AVE', 'DET', 'DER', 'MAN', 'FEF', 'TRA', 'ALM', 'DEX', 'DDIF', 'TDIF')");
$db->exec("UPDATE loss_causes SET kind = 'process' WHERE code IN ('RERR', 'CERR', 'PERR', 'REC', 'OTRA')");
$db->exec("UPDATE loss_causes SET kind = 'unexplained', name = 'Pérdida no explicada (ajuste injustificado)', requires_manager = 1 WHERE code = 'AJI'");
$db->exec("UPDATE loss_causes SET kind = 'theft', requires_manager = 1, requires_evidence = 1 WHERE code IN ('RIN', 'REX')");
$db->exec("UPDATE loss_causes SET kind = 'other' WHERE code IN ('EPR', 'EMA', 'EPC', 'NOV', 'LIQ', 'INV', 'FPO', 'FEL')");

$group = $db->prepare('INSERT INTO product_groups (name, description) SELECT ?, ? WHERE NOT EXISTS (SELECT 1 FROM product_groups WHERE name = ?)');
foreach (['Tablets', 'Computadores', 'Televisores', 'Electrodomésticos', 'Audífonos', 'Ropa', 'Calzado', 'Limpieza', 'Farmacia y cuidado personal', 'Accesorios', 'Repuestos', 'Mercancía general'] as $g)
{
	$group->execute([$g, 'Categoría del catálogo universal', $g]);
}

$rule = $db->prepare("INSERT OR IGNORE INTO dairy_alert_rules (code, name, description, enabled, severity, due_hours, escalate_after_hours, category, alert_type, impact_bucket, impact_kind, recommended_action, frequency_minutes) VALUES (?, ?, ?, 1, ?, ?, ?, 'inventory', 'count_diff', 'inventory', 'estimated', ?, 60)");
$rule->execute(['recurrent_difference', 'Diferencias repetidas', 'Mismo producto y sucursal con dos o más expedientes en 90 días', 'danger', 24, 24, 'Revisar el proceso de ese producto y programar conteos por riesgo']);
$rule->execute(['repeated_adjustments', 'Ajustes repetidos', 'Tres o más correcciones del mismo producto en 30 días', 'warning', 24, 24, 'Verificar quién autorizó los ajustes y por qué se repiten']);
$rule->execute(['reception_difference', 'Recepción con diferencias', 'Recepción aceptada con menos de lo entregado, o por IMEI con faltantes', 'warning', 24, 24, 'Reclamar al proveedor o aclarar la diferencia']);
$rule->execute(['sensitive_no_follow_up', 'Producto sensible sin seguimiento', 'Producto sensible con existencia y sin conteo en 14 días', 'warning', 48, 48, 'Programar un conteo']);

if (GROCY_MODE === 'demo' || GROCY_MODE === 'prerelease' || GROCY_MODE === 'dev')
{
	\Grocy\Services\MerchandiseService::GetInstance()->SeedDemo();
}
