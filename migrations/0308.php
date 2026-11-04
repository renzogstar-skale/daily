<?php

// DAILY — Motor laboral: fechas especiales, excepciones del ciclo dominical, restricciones individuales, solicitudes,
// trazabilidad de compensatorios y datos de planta que la planilla no trae (quedan vacíos hasta que RR. HH. los configure).

$db = \Grocy\Services\DatabaseService::GetInstance()->GetDbConnectionRaw();

$hasColumn = fn(string $table, string $col) => in_array($col, array_column($db->query("PRAGMA table_info($table)")->fetchAll(\PDO::FETCH_ASSOC), 'name'), true);

$db->exec("CREATE TABLE IF NOT EXISTS workforce_schedule_special_dates (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	special_date TEXT NOT NULL,
	rule_type TEXT NOT NULL, -- NO_COMPENSATORY | NO_SUNDAY_FREE
	branch_id INTEGER, -- NULL = todas las sucursales
	reason TEXT NOT NULL,
	active INTEGER NOT NULL DEFAULT 1,
	created_by_user_id INTEGER,
	created_at DATETIME DEFAULT (datetime('now', 'localtime')),
	UNIQUE (special_date, rule_type, branch_id)
)");
$seed = $db->prepare("INSERT OR IGNORE INTO workforce_schedule_special_dates (special_date, rule_type, reason) VALUES (:d, :r, :m)");
$seed->execute([':d' => '2026-11-27', ':r' => 'NO_COMPENSATORY', ':m' => 'Restricción de noviembre de 2026: nadie con compensatorio el 27']);
$seed->execute([':d' => '2026-11-29', ':r' => 'NO_SUNDAY_FREE', ':m' => 'Restricción de noviembre de 2026: nadie con libre dominical el 29']);

$db->exec("CREATE TABLE IF NOT EXISTS workforce_sunday_exceptions (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	payroll_code TEXT NOT NULL,
	original_sunday TEXT NOT NULL,
	new_sunday TEXT,
	reason TEXT NOT NULL,
	draft_id INTEGER,
	approved_by_user_id INTEGER,
	created_at DATETIME DEFAULT (datetime('now', 'localtime')),
	UNIQUE (payroll_code, original_sunday)
)");

$db->exec("CREATE TABLE IF NOT EXISTS workforce_employee_restrictions (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	payroll_code TEXT NOT NULL,
	restriction_type TEXT NOT NULL, -- PARCIAL | NOCTURNO | TOTAL
	start_date TEXT NOT NULL,
	end_date TEXT NOT NULL,
	weekdays TEXT, -- PARCIAL: JSON con los días disponibles (1 = lunes ... 7 = domingo)
	window_start TEXT, -- PARCIAL: hora más temprana de inicio
	window_end TEXT, -- PARCIAL: hora más tardía de salida
	max_weekly_hours REAL, -- PARCIAL
	night_mode TEXT, -- NOCTURNO: DENY | ALLOW, siempre explícito
	reason TEXT NOT NULL,
	notes TEXT,
	approved_by TEXT,
	active INTEGER NOT NULL DEFAULT 1,
	created_by_user_id INTEGER,
	created_at DATETIME DEFAULT (datetime('now', 'localtime'))
)");
$db->exec('CREATE INDEX IF NOT EXISTS ix_wer_code ON workforce_employee_restrictions (payroll_code, start_date, end_date)');

$db->exec("CREATE TABLE IF NOT EXISTS workforce_requests (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	request_type TEXT NOT NULL, -- VACACIONES | PERMISO | CAMBIO_TURNO | COMPENSATORIO | RESTRICCION | INTERCAMBIO | INCAPACIDAD
	payroll_code TEXT NOT NULL,
	branch_id INTEGER NOT NULL,
	start_date TEXT NOT NULL,
	end_date TEXT NOT NULL,
	payload_json TEXT,
	reason TEXT,
	status TEXT NOT NULL DEFAULT 'pendiente', -- pendiente | aprobada | rechazada | aplicada
	result_json TEXT,
	created_by_user_id INTEGER,
	created_at DATETIME DEFAULT (datetime('now', 'localtime')),
	decided_by_user_id INTEGER,
	decided_at DATETIME,
	decision_notes TEXT,
	applied_at DATETIME
)");
$db->exec('CREATE INDEX IF NOT EXISTS ix_wr_status ON workforce_requests (branch_id, status)');

foreach (['previous_date' => 'TEXT', 'reprogram_reason' => 'TEXT', 'entitlement' => 'TEXT', 'due_date' => 'TEXT'] as $col => $type)
{
	if (!$hasColumn('workforce_compensatory_bank', $col))
	{
		$db->exec("ALTER TABLE workforce_compensatory_bank ADD COLUMN $col $type");
	}
}
// Un domingo da derecho a un solo compensatorio: se conserva el registro más reciente de cada domingo
$db->exec('DELETE FROM workforce_compensatory_bank WHERE id NOT IN (SELECT MAX(id) FROM workforce_compensatory_bank GROUP BY payroll_code, origin_sunday)');
$db->exec('CREATE UNIQUE INDEX IF NOT EXISTS ux_wcb_origin ON workforce_compensatory_bank (payroll_code, origin_sunday)');

foreach (['contract_type', 'allowed_shifts', 'comp_sequence', 'comp_sequence_start'] as $col)
{
	if (!$hasColumn('workforce_planilla_collaborators', $col))
	{
		$db->exec("ALTER TABLE workforce_planilla_collaborators ADD COLUMN $col TEXT");
	}
}
// Única secuencia definida por gerencia hasta ahora: lunes, martes, jueves
$db->exec("UPDATE workforce_planilla_collaborators SET comp_sequence = '1,2,4', comp_sequence_start = '2026-11-01' WHERE payroll_code = '38053738'");
