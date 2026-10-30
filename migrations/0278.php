<?php

// DAIRY Etapa 21: operaciones con escáner, jerarquía de ubicaciones, lotes/series, historial de cambios de maestros,
// importaciones y política de aprobación de mermas. Solo agrega columnas, tablas, triggers y permisos.

use Grocy\Services\DatabaseService;

$db = DatabaseService::GetInstance()->GetDbConnectionRaw();

// Jerarquía sucursal > almacén > zona > pasillo > estante > ubicación, etiqueta escaneable y reglas de almacenamiento
$cols = array_column($db->query('PRAGMA table_info(locations)')->fetchAll(\PDO::FETCH_ASSOC), 'name');
foreach (['warehouse' => 'TEXT', 'zone' => 'TEXT', 'aisle' => 'TEXT', 'shelf' => 'TEXT', 'bin' => 'TEXT', 'label_code' => 'TEXT',
	'capacity_units' => 'REAL', 'storage_class' => 'TEXT'] as $col => $type)
{
	if (!in_array($col, $cols))
	{
		$db->exec("ALTER TABLE locations ADD COLUMN $col $type");
	}
}
$db->exec("UPDATE locations SET label_code = 'LOC-' || printf('%05d', id) WHERE label_code IS NULL OR label_code = ''");
$db->exec("UPDATE locations SET storage_class = CASE WHEN is_freezer = 1 THEN 'frozen' WHEN lower(name) LIKE '%refriger%' OR lower(name) LIKE '%fr_a%' THEN 'chilled' ELSE 'ambient' END WHERE storage_class IS NULL");

$db->exec("
CREATE UNIQUE INDEX IF NOT EXISTS ix_locations_label_code ON locations(label_code);
-- La etiqueta automática (triggers) está en 0279.

-- Lote y serie por entrada de stock (stock_id de Grocy se conserva en traslados y divisiones)
CREATE TABLE IF NOT EXISTS dairy_stock_lots (
	stock_id TEXT PRIMARY KEY,
	product_id INTEGER NOT NULL,
	lot_number TEXT,
	serial_number TEXT,
	gtin TEXT,
	created_by_user_id INTEGER,
	created_at DATETIME
);
CREATE INDEX IF NOT EXISTS ix_dairy_stock_lots_lot ON dairy_stock_lots(product_id, lot_number);
CREATE UNIQUE INDEX IF NOT EXISTS ix_dairy_stock_lots_serial ON dairy_stock_lots(product_id, serial_number) WHERE serial_number IS NOT NULL;

-- Sesiones de escaneo (conteo guiado, movimiento, recepción contra OC) y sus lecturas
CREATE TABLE IF NOT EXISTS dairy_scan_sessions (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	folio TEXT NOT NULL UNIQUE,
	type TEXT NOT NULL, -- count | move | receive
	branch_id INTEGER NOT NULL,
	location_id INTEGER,
	target_location_id INTEGER,
	order_id INTEGER,
	device_id TEXT,
	user_id INTEGER NOT NULL,
	status TEXT NOT NULL DEFAULT 'open', -- open | closed | cancelled
	notes TEXT,
	result_json TEXT,
	created_at DATETIME NOT NULL,
	closed_at DATETIME,
	closed_by_user_id INTEGER
);
CREATE TABLE IF NOT EXISTS dairy_scan_lines (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	session_id INTEGER NOT NULL,
	client_uuid TEXT NOT NULL UNIQUE,
	raw_code TEXT NOT NULL,
	product_id INTEGER NOT NULL,
	lot_number TEXT,
	serial_number TEXT,
	best_before_date DATE,
	amount REAL NOT NULL,
	device_id TEXT,
	user_id INTEGER NOT NULL,
	scanned_at DATETIME NOT NULL,
	received_at DATETIME NOT NULL,
	voided_at DATETIME,
	voided_by_user_id INTEGER
);
CREATE INDEX IF NOT EXISTS ix_dairy_scan_lines_session ON dairy_scan_lines(session_id);
CREATE UNIQUE INDEX IF NOT EXISTS ix_dairy_scan_lines_serial ON dairy_scan_lines(session_id, product_id, serial_number) WHERE serial_number IS NOT NULL AND voided_at IS NULL;

-- Importaciones CSV (validación previa, resultado y autor)
CREATE TABLE IF NOT EXISTS dairy_import_jobs (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	entity TEXT NOT NULL,
	file_name TEXT,
	rows_total INTEGER NOT NULL DEFAULT 0,
	rows_created INTEGER NOT NULL DEFAULT 0,
	rows_updated INTEGER NOT NULL DEFAULT 0,
	rows_rejected INTEGER NOT NULL DEFAULT 0,
	errors_json TEXT,
	user_id INTEGER,
	created_at DATETIME NOT NULL
);

-- Historial de cambios de maestros (quién, cuándo, antes/después)
CREATE TABLE IF NOT EXISTS dairy_change_log (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	table_name TEXT NOT NULL,
	row_id INTEGER NOT NULL,
	action TEXT NOT NULL, -- insert | update | delete
	old_json TEXT,
	new_json TEXT,
	user_id INTEGER,
	changed_at DATETIME NOT NULL DEFAULT (datetime('now', 'localtime'))
);
CREATE INDEX IF NOT EXISTS ix_dairy_change_log_row ON dairy_change_log(table_name, row_id);
");

// Triggers de historial: se generan con las columnas reales de cada tabla
foreach (['products', 'product_barcodes', 'shopping_locations', 'customers', 'locations', 'waste_reasons'] as $table)
{
	$names = array_column($db->query("PRAGMA table_info($table)")->fetchAll(\PDO::FETCH_ASSOC), 'name');
	if (count($names) === 0)
	{
		continue;
	}
	$json = fn(string $alias) => 'json_object(' . implode(', ', array_map(fn($c) => "'$c', $alias.\"$c\"", $names)) . ')';
	$db->exec("DROP TRIGGER IF EXISTS dairy_log_{$table}_ins; DROP TRIGGER IF EXISTS dairy_log_{$table}_upd; DROP TRIGGER IF EXISTS dairy_log_{$table}_del;");
	$db->exec("CREATE TRIGGER dairy_log_{$table}_ins AFTER INSERT ON $table BEGIN
		INSERT INTO dairy_change_log (table_name, row_id, action, new_json, user_id) VALUES ('$table', NEW.id, 'insert', " . $json('NEW') . ", dairy_user_id()); END");
	$db->exec("CREATE TRIGGER dairy_log_{$table}_upd AFTER UPDATE ON $table WHEN " . $json('OLD') . ' IS NOT ' . $json('NEW') . " BEGIN
		INSERT INTO dairy_change_log (table_name, row_id, action, old_json, new_json, user_id) VALUES ('$table', NEW.id, 'update', " . $json('OLD') . ', ' . $json('NEW') . ", dairy_user_id()); END");
	$db->exec("CREATE TRIGGER dairy_log_{$table}_del AFTER DELETE ON $table BEGIN
		INSERT INTO dairy_change_log (table_name, row_id, action, old_json, user_id) VALUES ('$table', OLD.id, 'delete', " . $json('OLD') . ", dairy_user_id()); END");
}

// Permisos: operar escáner (STOCK), aprobar mermas de alto valor (WASTE), importar maestros (ADMIN)
$insert = $db->prepare('INSERT OR IGNORE INTO permission_hierarchy (name, parent) VALUES (:name, :parent)');
$getId = $db->prepare('SELECT id FROM permission_hierarchy WHERE name = :name');
// WASTE_APPROVE_HIGH va junto a WASTE_APPROVE (no debajo: un permiso padre incluye a sus hijos)
$wasteGroup = $db->query("SELECT p.name FROM permission_hierarchy c JOIN permission_hierarchy p ON p.id = c.parent WHERE c.name = 'WASTE_APPROVE'")->fetchColumn() ?: 'ADMIN';
foreach (['SCAN_OPERATE' => 'STOCK', 'WASTE_APPROVE_HIGH' => $wasteGroup, 'DATA_IMPORT' => 'ADMIN'] as $perm => $parentName)
{
	$getId->execute([':name' => $parentName]);
	$parent = $getId->fetchColumn();
	if ($parent === false)
	{
		$getId->execute([':name' => 'ADMIN']);
		$parent = $getId->fetchColumn();
	}
	$insert->execute([':name' => $perm, ':parent' => intval($parent)]);
}
