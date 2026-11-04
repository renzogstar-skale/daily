<?php

// DAILY — Control de celulares por unidad: registro por IMEI, recepción contra manifiesto, cadena de custodia y conteos.
// No reemplaza nada: el stock sigue en stock/stock_log (cada unidad apunta a su stock_id) y los expedientes y alertas son
// los de prevención de pérdidas. Solo se controlan los productos con device_control = 1.

$db = \Grocy\Services\DatabaseService::GetInstance()->GetDbConnectionRaw();

$add = function (string $table, string $col, string $def) use ($db) {
	$cols = array_column($db->query("PRAGMA table_info($table)")->fetchAll(\PDO::FETCH_ASSOC), 'name');
	if (!in_array($col, $cols, true))
	{
		$db->exec("ALTER TABLE $table ADD COLUMN $col $def");
	}
};

$db->exec("
CREATE TABLE IF NOT EXISTS device_units (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	product_id INTEGER NOT NULL,
	imei1 TEXT NOT NULL UNIQUE,
	imei2 TEXT UNIQUE,
	serial_number TEXT UNIQUE,
	color TEXT,
	capacity TEXT,
	supplier_name TEXT,
	order_code TEXT,
	receipt_id INTEGER,
	received_at DATETIME NOT NULL,
	branch_id INTEGER NOT NULL,
	location_id INTEGER NOT NULL,
	zone TEXT NOT NULL DEFAULT 'main',
	state TEXT NOT NULL DEFAULT 'available',
	unit_cost REAL NOT NULL DEFAULT 0,
	stock_id TEXT,
	custodian_user_id INTEGER,
	custodian_area TEXT,
	sale_id INTEGER,
	order_id INTEGER,
	order_stage TEXT,
	transfer_id INTEGER,
	stock_reinstated INTEGER NOT NULL DEFAULT 0,
	block_reason TEXT,
	last_confirmed_at DATETIME,
	version INTEGER NOT NULL DEFAULT 1,
	is_demo INTEGER NOT NULL DEFAULT 0
);
CREATE INDEX IF NOT EXISTS ix_du_branch_state ON device_units (branch_id, state);
CREATE INDEX IF NOT EXISTS ix_du_product ON device_units (product_id, state);
CREATE INDEX IF NOT EXISTS ix_du_order ON device_units (order_id);
CREATE INDEX IF NOT EXISTS ix_du_sale ON device_units (sale_id);

CREATE TABLE IF NOT EXISTS device_events (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	device_id INTEGER NOT NULL,
	event TEXT NOT NULL,
	from_value TEXT,
	to_value TEXT,
	document TEXT,
	note TEXT,
	user_id INTEGER,
	confirmed_by_user_id INTEGER,
	created_at DATETIME NOT NULL,
	prev_hash TEXT,
	hash TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS ix_de_device ON device_events (device_id, id);
CREATE TRIGGER IF NOT EXISTS device_events_no_update BEFORE UPDATE ON device_events
BEGIN
	SELECT RAISE(ABORT, 'El historial del dispositivo es inalterable');
END;
CREATE TRIGGER IF NOT EXISTS device_events_no_delete BEFORE DELETE ON device_events
BEGIN
	SELECT RAISE(ABORT, 'El historial del dispositivo es inalterable');
END;

CREATE TABLE IF NOT EXISTS device_receipts (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	code TEXT NOT NULL UNIQUE,
	product_id INTEGER NOT NULL,
	branch_id INTEGER NOT NULL,
	location_id INTEGER NOT NULL,
	supplier_name TEXT,
	order_code TEXT,
	document TEXT,
	unit_cost REAL NOT NULL DEFAULT 0,
	status TEXT NOT NULL DEFAULT 'open', -- open | complete | with_differences
	justification TEXT,
	received_by_user_id INTEGER,
	authorized_by_user_id INTEGER,
	created_at DATETIME NOT NULL,
	closed_at DATETIME
);
CREATE TABLE IF NOT EXISTS device_receipt_lines (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	receipt_id INTEGER NOT NULL,
	imei1 TEXT NOT NULL,
	imei2 TEXT,
	serial_number TEXT,
	color TEXT,
	capacity TEXT,
	status TEXT NOT NULL DEFAULT 'expected', -- expected | matched | extra
	duplicate_scans INTEGER NOT NULL DEFAULT 0,
	scanned_by_user_id INTEGER,
	scanned_at DATETIME,
	UNIQUE (receipt_id, imei1)
);

CREATE TABLE IF NOT EXISTS device_moves (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	device_id INTEGER NOT NULL,
	from_zone TEXT NOT NULL,
	to_zone TEXT NOT NULL,
	high_risk INTEGER NOT NULL DEFAULT 0,
	status TEXT NOT NULL DEFAULT 'pending', -- pending | confirmed | rejected
	requested_by_user_id INTEGER NOT NULL,
	requested_at DATETIME NOT NULL,
	confirmed_by_user_id INTEGER,
	confirmed_at DATETIME
);
CREATE UNIQUE INDEX IF NOT EXISTS ux_dm_pending ON device_moves (device_id) WHERE status = 'pending';

CREATE TABLE IF NOT EXISTS device_counts (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	branch_id INTEGER NOT NULL,
	reason TEXT NOT NULL, -- open | close | shift | transfer | risk | random | prevention
	period_key TEXT,
	status TEXT NOT NULL DEFAULT 'open', -- open | closed
	expected_count INTEGER,
	found_count INTEGER,
	missing_count INTEGER,
	opened_by_user_id INTEGER,
	closed_by_user_id INTEGER,
	created_at DATETIME NOT NULL,
	closed_at DATETIME,
	UNIQUE (branch_id, reason, period_key)
);
CREATE TABLE IF NOT EXISTS device_count_scans (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	count_id INTEGER NOT NULL,
	device_id INTEGER,
	code TEXT NOT NULL,
	result TEXT NOT NULL, -- ok | other_branch | not_expected | unknown
	user_id INTEGER,
	scanned_at DATETIME NOT NULL,
	UNIQUE (count_id, code)
);

CREATE TABLE IF NOT EXISTS device_sale_holds (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	device_id INTEGER NOT NULL,
	branch_id INTEGER NOT NULL,
	reason TEXT NOT NULL,
	status TEXT NOT NULL DEFAULT 'pending', -- pending | approved | rejected | used
	requested_by_user_id INTEGER,
	decided_by_user_id INTEGER,
	created_at DATETIME NOT NULL,
	decided_at DATETIME
);

CREATE TABLE IF NOT EXISTS device_rules (
	code TEXT PRIMARY KEY,
	value TEXT NOT NULL,
	description TEXT
);
INSERT OR IGNORE INTO device_rules (code, value, description) VALUES
	('sale_exception_mode', 'block', 'Equipo reservado o de otra sucursal: block (rechaza) o pending (queda pendiente de autorización)'),
	('high_risk_zones', 'secure,service,pickup', 'Zonas cuyo movimiento exige verificación de un supervisor'),
	('double_approval_value', '500', 'Valor desde el que un ajuste de celular exige dos aprobadores distintos'),
	('transfer_confirm_hours', '24', 'Horas para confirmar un traslado antes de alertar');
");

$add('products', 'device_control', 'INTEGER NOT NULL DEFAULT 0');
\Grocy\Helpers\DairyChangeLog::InstallTriggers($db, ['products']);

// Un expediente por equipo: el índice de un expediente abierto por producto ahora distingue el dispositivo
$add('sensitive_investigation_cases', 'device_id', 'INTEGER');
$add('sensitive_investigation_cases', 'first_approver_user_id', 'INTEGER');
$db->exec('DROP INDEX IF EXISTS ux_sic_open');
$db->exec("CREATE UNIQUE INDEX ux_sic_open ON sensitive_investigation_cases (branch_id, product_id, IFNULL(device_id, 0)) WHERE workflow_status NOT IN ('resuelto', 'no_resuelto')");

$rule = $db->prepare("INSERT OR IGNORE INTO dairy_alert_rules (code, name, description, enabled, severity, due_hours, escalate_after_hours, category, alert_type, impact_bucket, impact_kind, recommended_action, frequency_minutes) VALUES (?, ?, ?, 1, ?, ?, ?, 'inventory', 'count_diff', 'inventory', 'estimated', ?, 60)");
$rule->execute(['device_not_located', 'Celular no localizado', 'Un IMEI esperado no apareció en el conteo', 'danger', 8, 16, 'Recontar por IMEI y revisar la última operación confirmada']);
$rule->execute(['device_incident', 'Incidencia de IMEI', 'Operación rechazada o inconsistente sobre un celular', 'danger', 12, 24, 'Revisar el historial del dispositivo']);
$rule->execute(['device_transfer_unconfirmed', 'Traslado de celular sin confirmar', 'Equipo en tránsito sin confirmación de recepción', 'warning', 8, 16, 'Confirmar la recepción por IMEI en la sucursal de destino']);

if (GROCY_MODE === 'demo' || GROCY_MODE === 'prerelease' || GROCY_MODE === 'dev')
{
	\Grocy\Services\DeviceService::GetInstance()->SeedDemo();
}
