<?php

// DAILY — Inventario y Almacenes (InventoryOperationsService).
//
// - inventory_adjustments: un conteo o ajuste que cambia existencias queda pendiente hasta que un aprobador distinto lo
//   apruebe (antes /api/stock/products/{id}/inventory corregía el stock directo). Se guardan los datos necesarios para
//   aplicarlo después (vencimiento y costo del ingreso) y su origen.
// - wms_internal_movements: tareas de reposición bodega → sala de ventas. La tarea se crea pendiente y el stock se mueve
//   solo al confirmarla (traslado interno en stock_log), con quién y cuándo.
// - Índices para las lecturas por ubicación del módulo (kardex, antigüedad, anomalías) en móvil.

$db = \Grocy\Services\DatabaseService::GetInstance()->GetDbConnectionRaw();

$addCols = function (string $table, array $cols) use ($db) {
	$have = array_column($db->query("PRAGMA table_info($table)")->fetchAll(\PDO::FETCH_ASSOC), 'name');
	foreach ($cols as $col => $def)
	{
		if (!in_array($col, $have, true))
		{
			$db->exec("ALTER TABLE $table ADD COLUMN $col $def");
		}
	}
};

$addCols('inventory_adjustments', [
	'best_before_date' => 'DATE',
	'price' => 'REAL',
	'source' => "TEXT DEFAULT 'manual'", // count_form | scan | api | manual
	'request_key' => 'TEXT',
	'resolved_by_user_id' => 'INTEGER',
	'resolved_timestamp' => 'DATETIME',
]);
$db->exec('CREATE UNIQUE INDEX IF NOT EXISTS ux_inventory_adjustments_request ON inventory_adjustments (request_key) WHERE request_key IS NOT NULL');
$db->exec('CREATE INDEX IF NOT EXISTS ix_inventory_adjustments_status ON inventory_adjustments (status, branch_id)');

$addCols('wms_internal_movements', [
	'completed_at' => 'DATETIME',
	'completed_by_user_id' => 'INTEGER',
	'transaction_id' => 'TEXT',
	'notes' => 'TEXT',
]);
$db->exec('CREATE INDEX IF NOT EXISTS ix_wms_internal_movements_status ON wms_internal_movements (status, branch_id)');

$db->exec('CREATE INDEX IF NOT EXISTS ix_stock_log_location_time ON stock_log (location_id, row_created_timestamp)');
$db->exec('CREATE INDEX IF NOT EXISTS ix_stock_location ON stock (location_id, product_id)');
