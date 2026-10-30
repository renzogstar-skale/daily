<?php

// DAILY — Autoservicio del empleado y comunicación (paridad con When I Work):
// confirmación de turnos, turnos abiertos que el empleado toma, mensajería con lectura real,
// notificaciones push (tokens Expo + bandeja de salida con estado real del proveedor) y geocerca opcional en el fichaje.

$db = \Grocy\Services\DatabaseService::GetInstance()->GetDbConnectionRaw();
$cols = fn(string $t) => array_column($db->query("PRAGMA table_info($t)")->fetchAll(\PDO::FETCH_ASSOC), 'name');
$add = function (string $t, string $c, string $def) use ($db, $cols) {
	if (!in_array($c, $cols($t), true))
	{
		$db->exec("ALTER TABLE $t ADD COLUMN $c $def");
	}
};

// 1. Confirmación de turno por el empleado (se borra si el turno cambia de fecha, hora o empleado)
$add('employee_shifts', 'confirmed_at', 'DATETIME');
$add('employee_shifts', 'confirmed_by_user_id', 'INTEGER');

// 2. Turnos abiertos: el gerente publica la necesidad (labor_coverage_requests); el empleado la toma
$add('labor_coverage_requests', 'auto_approve', 'TINYINT NOT NULL DEFAULT 0');
$add('labor_coverage_requests', 'department', 'TEXT');
$add('labor_coverage_requests', 'break_minutes', 'INTEGER NOT NULL DEFAULT 0');

// 3. Geocerca opcional por sucursal. off = no se guarda ubicación; record = se guarda y se informa; enforce = fuera del radio no ficha
$add('branches', 'latitude', 'REAL');
$add('branches', 'longitude', 'REAL');
$add('branches', 'geofence_radius_m', 'INTEGER NOT NULL DEFAULT 150');
$add('branches', 'geofence_mode', "TEXT NOT NULL DEFAULT 'off'");
foreach (['in', 'out'] as $k)
{
	$add('employee_attendance', "clock_{$k}_lat", 'REAL');
	$add('employee_attendance', "clock_{$k}_lng", 'REAL');
	$add('employee_attendance', "clock_{$k}_accuracy_m", 'REAL');
	$add('employee_attendance', "clock_{$k}_distance_m", 'REAL');
	$add('employee_attendance', "clock_{$k}_geo_status", 'TEXT'); // inside | outside | not_provided | no_branch_location (NULL = geocerca apagada)
}

$db->exec("
CREATE TABLE IF NOT EXISTS labor_coverage_claims (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	request_id INTEGER NOT NULL,
	employee_id INTEGER NOT NULL,
	status TEXT NOT NULL DEFAULT 'pending' CHECK(status IN ('pending', 'approved', 'rejected', 'withdrawn')),
	shift_id INTEGER,
	notes TEXT,
	decision_notes TEXT,
	claimed_by_user_id INTEGER,
	decided_by_user_id INTEGER,
	decided_at DATETIME,
	created_at DATETIME NOT NULL DEFAULT (datetime('now', 'localtime')),
	UNIQUE(request_id, employee_id)
);
CREATE INDEX IF NOT EXISTS ix_lcc_request ON labor_coverage_claims(request_id, status);

CREATE TABLE IF NOT EXISTS dairy_message_threads (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	kind TEXT NOT NULL CHECK(kind IN ('direct', 'group', 'announcement')),
	title TEXT,
	branch_id INTEGER,
	direct_key TEXT UNIQUE, -- 'menor:mayor' para no duplicar conversaciones 1:1
	created_by_user_id INTEGER NOT NULL,
	created_at DATETIME NOT NULL,
	last_message_at DATETIME
);
CREATE TABLE IF NOT EXISTS dairy_message_members (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	thread_id INTEGER NOT NULL,
	user_id INTEGER NOT NULL,
	can_post TINYINT NOT NULL DEFAULT 1,
	last_read_message_id INTEGER NOT NULL DEFAULT 0,
	last_read_at DATETIME,
	joined_at DATETIME NOT NULL,
	UNIQUE(thread_id, user_id)
);
CREATE INDEX IF NOT EXISTS ix_dmm_user ON dairy_message_members(user_id);
CREATE TABLE IF NOT EXISTS dairy_messages (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	thread_id INTEGER NOT NULL,
	sender_user_id INTEGER NOT NULL,
	body TEXT NOT NULL,
	client_uuid TEXT UNIQUE, -- reintentos sin duplicar
	created_at DATETIME NOT NULL
);
CREATE INDEX IF NOT EXISTS ix_dm_thread ON dairy_messages(thread_id, id);

CREATE TABLE IF NOT EXISTS dairy_push_devices (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	user_id INTEGER NOT NULL,
	platform TEXT NOT NULL DEFAULT 'expo',
	token TEXT NOT NULL UNIQUE,
	device_name TEXT,
	created_at DATETIME NOT NULL,
	last_seen_at DATETIME,
	revoked_at DATETIME,
	revoked_reason TEXT
);
CREATE INDEX IF NOT EXISTS ix_dpd_user ON dairy_push_devices(user_id, revoked_at);
CREATE TABLE IF NOT EXISTS dairy_push_outbox (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	user_id INTEGER NOT NULL,
	device_id INTEGER, -- NULL = el usuario no tiene dispositivos registrados
	category TEXT NOT NULL,
	title TEXT NOT NULL,
	body TEXT,
	data_json TEXT,
	-- queued → sent (aceptado por Expo, con ticket) → receipt_ok | receipt_error; error = rechazo del envío; no_device = sin token
	status TEXT NOT NULL CHECK(status IN ('queued', 'sent', 'receipt_ok', 'receipt_error', 'error', 'no_device')),
	ticket_id TEXT,
	error TEXT,
	attempts INTEGER NOT NULL DEFAULT 0,
	created_at DATETIME NOT NULL,
	sent_at DATETIME,
	receipt_at DATETIME
);
CREATE INDEX IF NOT EXISTS ix_dpo_status ON dairy_push_outbox(status, id);
");
