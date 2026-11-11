<?php

// DAILY — Historial de Ventas: trazabilidad de la historia comercial.
//
// daily_sales_corrections: correcciones comerciales autorizadas sobre tickets ya registrados (duplicado anulado,
// fecha de venta corregida). Cada fila guarda quién autorizó, el motivo y el antes/después.
// daily_sales_closures: cierres comerciales (semana o mes) con la foto de las cifras al cerrar; si después cambian,
// el Historial lo señala y lo explica con las correcciones y cambios registrados.
// Triggers en sales y sale_items: toda modificación o borrado queda en dairy_change_log (antes/después y usuario).

$db = \Grocy\Services\DatabaseService::GetInstance()->GetDbConnectionRaw();

$db->exec("CREATE TABLE IF NOT EXISTS daily_sales_corrections (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	sale_id INTEGER NOT NULL,
	kind TEXT NOT NULL, -- duplicate | date
	reason TEXT NOT NULL,
	related_sale_id INTEGER,
	before_json TEXT,
	after_json TEXT,
	authorized_by INTEGER,
	created_at DATETIME NOT NULL DEFAULT (datetime('now', 'localtime'))
);
CREATE INDEX IF NOT EXISTS ix_daily_sales_corrections_sale ON daily_sales_corrections(sale_id);
CREATE TABLE IF NOT EXISTS daily_sales_closures (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	period_type TEXT NOT NULL, -- week | month
	period_from DATE NOT NULL,
	period_to DATE NOT NULL,
	net REAL NOT NULL,
	tickets INTEGER NOT NULL,
	refunds REAL NOT NULL DEFAULT 0,
	notes TEXT,
	closed_by INTEGER,
	closed_at DATETIME NOT NULL DEFAULT (datetime('now', 'localtime')),
	UNIQUE (period_type, period_from)
)");

foreach (['sales', 'sale_items'] as $table)
{
	$cols = array_column($db->query("PRAGMA table_info($table)")->fetchAll(\PDO::FETCH_ASSOC), 'name');
	$json = fn($row) => 'json_object(' . implode(', ', array_map(fn($c) => "'$c', $row.$c", $cols)) . ')';
	$db->exec("DROP TRIGGER IF EXISTS dairy_log_{$table}_upd");
	$db->exec("DROP TRIGGER IF EXISTS dairy_log_{$table}_del");
	$db->exec("CREATE TRIGGER dairy_log_{$table}_upd AFTER UPDATE ON $table WHEN " . $json('OLD') . ' IS NOT ' . $json('NEW') . " BEGIN
		INSERT INTO dairy_change_log (table_name, row_id, action, old_json, new_json, user_id) VALUES ('$table', NEW.id, 'update', " . $json('OLD') . ', ' . $json('NEW') . ", dairy_user_id()); END");
	$db->exec("CREATE TRIGGER dairy_log_{$table}_del AFTER DELETE ON $table BEGIN
		INSERT INTO dairy_change_log (table_name, row_id, action, old_json, user_id) VALUES ('$table', OLD.id, 'delete', " . $json('OLD') . ", dairy_user_id()); END");
}
