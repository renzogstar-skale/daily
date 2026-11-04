<?php

// DAILY — Prevención de pérdidas: clasificación sensible configurable, conteos programados con escáner,
// expedientes con flujo Detectado → Reconteo → En investigación → Pendiente de aprobación → Resuelto / No resuelto,
// hipótesis por comprobar y bitácora encadenada por hash (inalterable, igual que el libro de movimientos).

$db = \Grocy\Services\DatabaseService::GetInstance()->GetDbConnectionRaw();

$add = function (string $table, string $col, string $def) use ($db) {
	$cols = array_column($db->query("PRAGMA table_info($table)")->fetchAll(\PDO::FETCH_ASSOC), 'name');
	if (!in_array($col, $cols, true))
	{
		$db->exec("ALTER TABLE $table ADD COLUMN $col $def");
	}
};

$db->exec("
CREATE TABLE IF NOT EXISTS sensitive_categories (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	code TEXT NOT NULL UNIQUE,
	name TEXT NOT NULL,
	count_frequency TEXT NOT NULL DEFAULT 'weekly', -- daily | shift | weekly | risk
	high_value_threshold REAL NOT NULL DEFAULT 500,
	requires_serial INTEGER NOT NULL DEFAULT 0,
	active INTEGER NOT NULL DEFAULT 1,
	sort_order INTEGER NOT NULL DEFAULT 0
);

CREATE TABLE IF NOT EXISTS sensitive_case_events (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	case_id INTEGER NOT NULL,
	action TEXT NOT NULL,
	from_status TEXT,
	to_status TEXT,
	note TEXT,
	payload_json TEXT,
	user_id INTEGER,
	created_at DATETIME NOT NULL,
	prev_hash TEXT,
	hash TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS ix_sce_case ON sensitive_case_events (case_id, id);
CREATE TRIGGER IF NOT EXISTS sensitive_case_events_no_update BEFORE UPDATE ON sensitive_case_events
BEGIN
	SELECT RAISE(ABORT, 'La bitácora del expediente es inalterable');
END;
CREATE TRIGGER IF NOT EXISTS sensitive_case_events_no_delete BEFORE DELETE ON sensitive_case_events
BEGIN
	SELECT RAISE(ABORT, 'La bitácora del expediente es inalterable');
END;

CREATE TABLE IF NOT EXISTS sensitive_case_hypotheses (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	case_id INTEGER NOT NULL,
	code TEXT NOT NULL,
	label TEXT NOT NULL,
	qty REAL NOT NULL DEFAULT 0,
	source TEXT,
	status TEXT NOT NULL DEFAULT 'pendiente', -- pendiente | confirmada | descartada
	note TEXT,
	decided_by_user_id INTEGER,
	decided_at DATETIME,
	UNIQUE (case_id, code)
);

CREATE TABLE IF NOT EXISTS sensitive_count_plans (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	branch_id INTEGER NOT NULL,
	category_id INTEGER,
	product_id INTEGER,
	frequency TEXT NOT NULL DEFAULT 'daily', -- daily | shift | weekly | risk
	assigned_user_id INTEGER,
	active INTEGER NOT NULL DEFAULT 1,
	created_by_user_id INTEGER,
	created_at DATETIME NOT NULL
);

CREATE TABLE IF NOT EXISTS sensitive_count_tasks (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	plan_id INTEGER,
	branch_id INTEGER NOT NULL,
	product_id INTEGER NOT NULL,
	period_key TEXT NOT NULL,
	due_at DATETIME NOT NULL,
	status TEXT NOT NULL DEFAULT 'pending', -- pending | recount | done | cancelled
	assigned_user_id INTEGER,
	first_qty REAL,
	first_user_id INTEGER,
	first_at DATETIME,
	final_qty REAL,
	final_user_id INTEGER,
	final_at DATETIME,
	case_id INTEGER,
	created_at DATETIME NOT NULL,
	UNIQUE (branch_id, product_id, period_key)
);
CREATE INDEX IF NOT EXISTS ix_sct_status ON sensitive_count_tasks (branch_id, status, due_at);

CREATE TABLE IF NOT EXISTS sensitive_count_serials (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	task_id INTEGER NOT NULL,
	pass INTEGER NOT NULL DEFAULT 1,
	code TEXT NOT NULL,
	matched INTEGER NOT NULL DEFAULT 0,
	user_id INTEGER,
	scanned_at DATETIME NOT NULL,
	UNIQUE (task_id, pass, code)
);
");

$add('products', 'sensitive_category_id', 'INTEGER');
$add('products', 'is_demo', 'INTEGER NOT NULL DEFAULT 0');

$add('sensitive_inventory_movements', 'location_id', 'INTEGER');
$add('sensitive_inventory_movements', 'shift_code', 'TEXT');
$add('sensitive_inventory_movements', 'is_demo', 'INTEGER NOT NULL DEFAULT 0');

$add('sensitive_investigation_cases', 'workflow_status', "TEXT NOT NULL DEFAULT 'detectado'");
$add('sensitive_investigation_cases', 'location_id', 'INTEGER');
$add('sensitive_investigation_cases', 'count_at', 'DATETIME');
$add('sensitive_investigation_cases', 'occurrences', 'INTEGER NOT NULL DEFAULT 1');
$add('sensitive_investigation_cases', 'provisional', 'INTEGER NOT NULL DEFAULT 0');
$add('sensitive_investigation_cases', 'explained_qty', 'REAL NOT NULL DEFAULT 0');
$add('sensitive_investigation_cases', 'assigned_user_id', 'INTEGER');
$add('sensitive_investigation_cases', 'due_at', 'DATETIME');
$add('sensitive_investigation_cases', 'proposed_by_user_id', 'INTEGER');
$add('sensitive_investigation_cases', 'proposed_at', 'DATETIME');
$add('sensitive_investigation_cases', 'proposed_reason', 'TEXT');
$add('sensitive_investigation_cases', 'resolution_type', 'TEXT');
$add('sensitive_investigation_cases', 'is_demo', 'INTEGER NOT NULL DEFAULT 0');

$add('sensitive_investigation_evidence', 'file_name', 'TEXT');
$add('sensitive_investigation_evidence', 'mime', 'TEXT');
$add('sensitive_investigation_evidence', 'camera_ref', 'TEXT');
$add('sensitive_investigation_evidence', 'authorized_by', 'TEXT');

// products cambió de columnas: el historial de cambios debe incluirlas
\Grocy\Helpers\DairyChangeLog::InstallTriggers($db, ['products']);

// Etapas del módulo 36 → flujo del expediente; se conserva la bitácora vacía de lo ya existente
$db->exec("UPDATE sensitive_investigation_cases SET workflow_status = CASE stage
	WHEN 'preliminar' THEN 'reconteo' WHEN 'faltante_confirmado' THEN 'en_investigacion' WHEN 'perdida_reconocida' THEN 'resuelto'
	WHEN 'ajustado' THEN 'resuelto' WHEN 'cerrado' THEN 'resuelto' ELSE 'reconteo' END, count_at = IFNULL(audit_interval_end, created_at)");
// El caso del módulo 36 viene de la semilla de demostración: se marca como tal
$db->exec("UPDATE sensitive_investigation_cases SET is_demo = 1 WHERE case_code = 'INV-2026-0001'");
$db->exec("UPDATE sensitive_inventory_movements SET is_demo = 1 WHERE movement_code IN ('ING-2026-00089','CNT-2026-00102','VTA-2026-00045','TRA-2026-00012','RES-2026-00078','CNT-2026-00144','DEV-2026-00018','TRA-2026-00034','DEV-2026-00022','MRM-2026-00067')");

$db->exec('UPDATE sensitive_investigation_cases SET assigned_user_id = (SELECT owner_user_id FROM dairy_alerts a WHERE a.id = alert_id), due_at = (SELECT due_at FROM dairy_alerts a WHERE a.id = alert_id) WHERE alert_id IS NOT NULL');

// Un solo expediente abierto por producto y sucursal: el índice lo garantiza también ante conteos simultáneos
$db->exec("CREATE UNIQUE INDEX IF NOT EXISTS ux_sic_open ON sensitive_investigation_cases (branch_id, product_id) WHERE workflow_status NOT IN ('resuelto', 'no_resuelto')");

$cat = $db->prepare('INSERT OR IGNORE INTO sensitive_categories (code, name, count_frequency, high_value_threshold, requires_serial, sort_order) VALUES (?, ?, ?, ?, ?, ?)');
foreach ([
	['phones', 'Celulares', 'daily', 300, 1, 1],
	['headphones', 'Audífonos', 'daily', 150, 0, 2],
	['tablets', 'Tablets', 'daily', 300, 1, 3],
	['computers', 'Computadores', 'weekly', 500, 1, 4],
	['consoles', 'Consolas', 'weekly', 300, 1, 5],
	['accessories', 'Accesorios de alto valor', 'weekly', 200, 0, 6],
	['other', 'Otros productos definidos por la empresa', 'risk', 500, 0, 7],
] as $c)
{
	$cat->execute($c);
}
$db->exec("UPDATE products SET sensitive_category_id = (SELECT id FROM sensitive_categories WHERE code = 'phones') WHERE imei_tracking = 1 AND sensitive_category_id IS NULL");
$db->exec("UPDATE products SET sensitive_category_id = (SELECT id FROM sensitive_categories WHERE code = 'other') WHERE is_sensitive = 1 AND sensitive_category_id IS NULL");

$rule = $db->prepare('INSERT OR IGNORE INTO dairy_alert_rules (code, name, description, enabled, severity, due_hours, category, alert_type, impact_bucket, impact_kind, recommended_action, frequency_minutes) VALUES (?, ?, ?, 1, ?, ?, ?, ?, ?, ?, ?, 60)');
$rule->execute(['sensitive_difference', 'Diferencia en inventario sensible', 'Conteo físico distinto del stock esperado reconstruido por el motor de conciliación', 'danger', 24, 'inventory', 'count_diff', 'inventory', 'estimated', 'Abrir el expediente, recontar y revisar los movimientos del intervalo']);
$rule->execute(['sensitive_count_overdue', 'Conteo sensible vencido', 'Tarea de conteo programada que pasó su fecha límite sin ejecutarse', 'warning', 8, 'inventory', 'count_diff', 'process', 'estimated', 'Ejecutar el conteo con escáner']);
$rule->execute(['sensitive_manual_adjust', 'Ajuste manual sobre producto sensible', 'Corrección de inventario de un producto sensible sin expediente aprobado', 'danger', 12, 'inventory', 'count_diff', 'inventory', 'estimated', 'Verificar quién autorizó el ajuste y documentarlo']);

if (GROCY_MODE === 'demo' || GROCY_MODE === 'prerelease' || GROCY_MODE === 'dev')
{
	\Grocy\Services\SensitiveCaseService::GetInstance()->SeedDemo();
}
