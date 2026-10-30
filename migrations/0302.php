<?php

// DAILY — Informe Ejecutivo de Pérdidas y Novedades (Control 360) para el Director de Mermas.
// Programación configurable (diaria 09:00 en la zona horaria corporativa), historial inalterable de informes generados
// y registro de entrega por destinatario. Sin transporte de correo: la entrega es la publicación en la plataforma.

$db = \Grocy\Services\DatabaseService::GetInstance()->GetDbConnectionRaw();

$db->exec("
CREATE TABLE IF NOT EXISTS dcr_report_schedules (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	period_type TEXT NOT NULL UNIQUE CHECK(period_type IN ('daily', 'weekly', 'monthly', 'annual')),
	run_time TEXT NOT NULL DEFAULT '09:00',
	timezone TEXT NOT NULL,
	active TINYINT NOT NULL DEFAULT 1,
	recipient_user_ids TEXT, -- JSON; vacío = usuarios con LOSS_PREVENTION (o ADMIN)
	next_run_at DATETIME,
	last_run_at DATETIME,
	updated_by_user_id INTEGER,
	updated_timestamp DATETIME
);
CREATE TABLE IF NOT EXISTS dcr_executive_reports (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	report_code TEXT NOT NULL UNIQUE,
	period_type TEXT NOT NULL,
	period_from TEXT NOT NULL,
	period_to TEXT NOT NULL,
	report_date TEXT NOT NULL, -- fecha de publicación (corte = día anterior)
	completeness TEXT NOT NULL CHECK(completeness IN ('definitivo', 'preliminar')),
	branches_total INTEGER NOT NULL DEFAULT 0,
	branches_synced INTEGER NOT NULL DEFAULT 0,
	trigger_type TEXT NOT NULL, -- schedule | catch_up | manual
	status TEXT NOT NULL CHECK(status IN ('publicado', 'error')),
	payload_json TEXT,
	payload_sha256 TEXT,
	error_message TEXT,
	generated_by_user_id INTEGER,
	generated_at DATETIME NOT NULL,
	duration_ms INTEGER
);
CREATE INDEX IF NOT EXISTS ix_dcr_reports_period ON dcr_executive_reports(period_type, report_date, id);
CREATE TABLE IF NOT EXISTS dcr_report_deliveries (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	report_id INTEGER NOT NULL,
	user_id INTEGER NOT NULL,
	channel TEXT NOT NULL, -- app | email
	status TEXT NOT NULL, -- publicado | leido | no_configurado
	detail TEXT,
	created_at DATETIME NOT NULL,
	read_at DATETIME,
	UNIQUE(report_id, user_id, channel)
);
CREATE INDEX IF NOT EXISTS ix_dcr_deliv_user ON dcr_report_deliveries(user_id, read_at);

-- Historial inalterable: un informe publicado no se modifica ni se borra (se genera uno nuevo)
CREATE TRIGGER IF NOT EXISTS tr_dcr_reports_no_update BEFORE UPDATE ON dcr_executive_reports
BEGIN
	SELECT RAISE(ABORT, 'Los informes ejecutivos publicados son inalterables');
END;
CREATE TRIGGER IF NOT EXISTS tr_dcr_reports_no_delete BEFORE DELETE ON dcr_executive_reports
BEGIN
	SELECT RAISE(ABORT, 'Los informes ejecutivos publicados son inalterables');
END;
");

// Programación diaria a las 09:00 (zona horaria corporativa); semanal, mensual y anual creadas inactivas
$tz = date_default_timezone_get();
$next = date('Y-m-d') . ' 09:00:00';
if (strtotime($next) <= time())
{
	$next = date('Y-m-d', strtotime('+1 day')) . ' 09:00:00';
}
$ins = $db->prepare('INSERT OR IGNORE INTO dcr_report_schedules (period_type, run_time, timezone, active, next_run_at) VALUES (?, ?, ?, ?, ?)');
$ins->execute(['daily', '09:00', $tz, 1, $next]);
$ins->execute(['weekly', '09:00', $tz, 0, null]);
$ins->execute(['monthly', '09:00', $tz, 0, null]);
$ins->execute(['annual', '09:00', $tz, 0, null]);
