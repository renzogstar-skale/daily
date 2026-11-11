<?php

// DAILY — Punto de venta (PosService).
//
// - sales.client_ref: UUID de la venta generado por la terminal; índice único → un reintento o una resincronización sin
//   conexión nunca cobra ni registra dos veces. sales.shift_id: turno de caja de la venta.
// - daily_pos_payments: cada medio de pago de la venta (efectivo con entregado y cambio, tarjeta, transferencia). Solo
//   referencia de autorización; nunca número de tarjeta, vencimiento ni código de seguridad.
// - daily_pos_authorizations: descuentos, anulaciones, reimpresiones y diferencias de caja aprobadas por un supervisor.
// - daily_pos_receipts: emisión y reimpresiones de cada recibo. daily_pos_cash_movements: retiros e ingresos de caja.
// - Parámetros del POS en daily_system_settings e impuesto por departamento derivado del historial de ventas.
// - Permiso SALES_SUPERVISE. Los turnos «Apertura automática» (abiertos sin cajero ni fondo contado) se cierran.

$db = \Grocy\Services\DatabaseService::GetInstance()->GetDbConnectionRaw();

$cols = array_column($db->query('PRAGMA table_info(sales)')->fetchAll(\PDO::FETCH_ASSOC), 'name');
if (!in_array('client_ref', $cols, true))
{
	$db->exec('ALTER TABLE sales ADD COLUMN client_ref TEXT');
}
if (!in_array('shift_id', $cols, true))
{
	$db->exec('ALTER TABLE sales ADD COLUMN shift_id INTEGER');
}
$db->exec('CREATE UNIQUE INDEX IF NOT EXISTS ux_sales_client_ref ON sales(client_ref) WHERE client_ref IS NOT NULL');
$db->exec('CREATE INDEX IF NOT EXISTS ix_sales_shift ON sales(shift_id)');

// Los triggers de historial (0325) listan las columnas de sales: se regeneran con las nuevas
foreach (['sales'] as $table)
{
	$c = array_column($db->query("PRAGMA table_info($table)")->fetchAll(\PDO::FETCH_ASSOC), 'name');
	$json = fn($row) => 'json_object(' . implode(', ', array_map(fn($x) => "'$x', $row.$x", $c)) . ')';
	$db->exec("DROP TRIGGER IF EXISTS dairy_log_{$table}_upd");
	$db->exec("DROP TRIGGER IF EXISTS dairy_log_{$table}_del");
	$db->exec("CREATE TRIGGER dairy_log_{$table}_upd AFTER UPDATE ON $table WHEN " . $json('OLD') . ' IS NOT ' . $json('NEW') . " BEGIN
		INSERT INTO dairy_change_log (table_name, row_id, action, old_json, new_json, user_id) VALUES ('$table', NEW.id, 'update', " . $json('OLD') . ', ' . $json('NEW') . ", dairy_user_id()); END");
	$db->exec("CREATE TRIGGER dairy_log_{$table}_del AFTER DELETE ON $table BEGIN
		INSERT INTO dairy_change_log (table_name, row_id, action, old_json, user_id) VALUES ('$table', OLD.id, 'delete', " . $json('OLD') . ", dairy_user_id()); END");
}

$db->exec("CREATE TABLE IF NOT EXISTS pos_cash_shifts (
	id INTEGER PRIMARY KEY AUTOINCREMENT, branch_id INTEGER NOT NULL, register_name TEXT NOT NULL DEFAULT 'Caja 1', shift_name TEXT NOT NULL DEFAULT 'Mañana',
	user_id INTEGER NOT NULL DEFAULT 1, status TEXT NOT NULL DEFAULT 'open', opening_amount REAL NOT NULL DEFAULT 0, opened_at TEXT NOT NULL, closed_at TEXT NULL,
	closed_by_user_id INTEGER NULL, expected_cash REAL NULL, counted_cash REAL NULL, cash_difference REAL NULL, sales_count INTEGER NOT NULL DEFAULT 0,
	total_sales REAL NOT NULL DEFAULT 0.0, total_cash REAL NOT NULL DEFAULT 0.0, total_card REAL NOT NULL DEFAULT 0.0, total_transfer REAL NOT NULL DEFAULT 0.0, notes TEXT NULL)");
$sc = array_column($db->query('PRAGMA table_info(pos_cash_shifts)')->fetchAll(\PDO::FETCH_ASSOC), 'name');
foreach (['counted_breakdown' => 'TEXT', 'difference_reason' => 'TEXT', 'difference_auth_id' => 'INTEGER', 'voided_count' => 'INTEGER', 'discount_total' => 'REAL'] as $col => $type)
{
	if (!in_array($col, $sc, true))
	{
		$db->exec("ALTER TABLE pos_cash_shifts ADD COLUMN $col $type");
	}
}
$db->exec("UPDATE pos_cash_shifts SET status = 'closed', closed_at = datetime('now', 'localtime'),
	notes = IFNULL(notes, '') || ' | Cerrado al activar el POS: turno abierto automáticamente, sin cajero ni arqueo'
	WHERE status = 'open' AND notes LIKE '%Apertura automática%'");

$db->exec("CREATE TABLE IF NOT EXISTS daily_pos_payments (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	sale_id INTEGER NOT NULL,
	shift_id INTEGER,
	method TEXT NOT NULL, -- cash | card | transfer
	amount REAL NOT NULL, -- importe aplicado a la venta
	tendered REAL, -- efectivo entregado
	change_given REAL NOT NULL DEFAULT 0,
	reference TEXT, -- código de autorización o comprobante (nunca datos de tarjeta)
	created_at DATETIME NOT NULL DEFAULT (datetime('now', 'localtime'))
);
CREATE INDEX IF NOT EXISTS ix_daily_pos_payments_sale ON daily_pos_payments(sale_id);
CREATE INDEX IF NOT EXISTS ix_daily_pos_payments_shift ON daily_pos_payments(shift_id);
CREATE TABLE IF NOT EXISTS daily_pos_authorizations (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	kind TEXT NOT NULL, -- discount | void | reprint | cash_difference
	branch_id INTEGER,
	shift_id INTEGER,
	sale_id INTEGER,
	requested_by INTEGER NOT NULL,
	approved_by INTEGER NOT NULL,
	amount REAL,
	pct REAL,
	reason TEXT NOT NULL,
	status TEXT NOT NULL DEFAULT 'granted', -- granted | used | expired
	created_at DATETIME NOT NULL DEFAULT (datetime('now', 'localtime')),
	used_at DATETIME
);
CREATE INDEX IF NOT EXISTS ix_daily_pos_auth_sale ON daily_pos_authorizations(sale_id);
CREATE TABLE IF NOT EXISTS daily_pos_receipts (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	sale_id INTEGER NOT NULL,
	copy_no INTEGER NOT NULL, -- 0 = original, 1.. = reimpresiones
	printed_by INTEGER,
	authorization_id INTEGER,
	printed_at DATETIME NOT NULL DEFAULT (datetime('now', 'localtime')),
	UNIQUE (sale_id, copy_no)
);
CREATE TABLE IF NOT EXISTS daily_pos_cash_movements (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	shift_id INTEGER NOT NULL,
	kind TEXT NOT NULL, -- drop (retiro a bóveda) | payin (ingreso)
	amount REAL NOT NULL,
	reason TEXT NOT NULL,
	user_id INTEGER,
	authorization_id INTEGER,
	created_at DATETIME NOT NULL DEFAULT (datetime('now', 'localtime'))
)");

// Impuesto por departamento: el del historial (tickets de un solo departamento con IVA > 0 en la mayoría)
$deptOf = [];
foreach (\Grocy\Services\DailyMetricsService::Departments() as $k => $d)
{
	foreach ($d[2] ?? [] as $v)
	{
		$deptOf[$v] = $k;
	}
}
$stats = [];
foreach ($db->query("SELECT s.id, s.tax_amount, s.total_amount - s.tax_amount AS net, group_concat(DISTINCT p.department) AS deps
	FROM sales s JOIN sale_items si ON si.sale_id = s.id JOIN products p ON p.id = si.product_id
	WHERE s.status = 'completed' GROUP BY s.id HAVING COUNT(DISTINCT p.department) = 1")->fetchAll(\PDO::FETCH_ASSOC) as $r)
{
	$k = $deptOf[$r['deps']] ?? null;
	if ($k === null || $r['net'] <= 0)
	{
		continue;
	}
	$stats[$k]['n'] = ($stats[$k]['n'] ?? 0) + 1;
	if ($r['tax_amount'] > 0)
	{
		$stats[$k]['rates'][] = round($r['tax_amount'] / $r['net'] * 100, 2);
	}
}
$rates = [];
foreach ($stats as $k => $s)
{
	$taxed = $s['rates'] ?? [];
	sort($taxed);
	$rates[$k] = count($taxed) > $s['n'] / 2 ? round($taxed[intdiv(count($taxed), 2)], 1) : 0.0;
}
$set = $db->prepare("INSERT OR IGNORE INTO daily_system_settings (setting_key, setting_value, category, description) VALUES (?, ?, 'pos', ?)");
$set->execute(['pos_tax_rates', json_encode($rates), 'Impuesto por departamento (%) aplicado por el POS; departamentos no listados: pos_default_tax_rate']);
$set->execute(['pos_default_tax_rate', '0', 'Impuesto (%) de departamentos sin regla en pos_tax_rates']);
$set->execute(['pos_cashier_max_discount_pct', '0', 'Descuento máximo (%) que un cajero aplica sin autorización (las rebajas FEFO vigentes siempre se permiten)']);
$set->execute(['pos_supervisor_max_discount_pct', '30', 'Descuento máximo (%) que puede autorizar un supervisor']);
$set->execute(['pos_cash_diff_tolerance', '5', 'Diferencia de arqueo ($) que no requiere autorización del supervisor']);
$set->execute(['pos_free_reprints', '1', 'Reimpresiones de un recibo sin autorización del supervisor']);
$set->execute(['pos_offline_max_hours', '72', 'Antigüedad máxima (horas) de una venta registrada sin conexión al sincronizarse']);

// Permiso de supervisor de caja (debajo de Ventas)
$parent = $db->query("SELECT id FROM permission_hierarchy WHERE name = 'SALES'")->fetchColumn();
$db->prepare('INSERT OR IGNORE INTO permission_hierarchy (name, parent) VALUES (?, ?)')->execute(['SALES_SUPERVISE', $parent ?: null]);
