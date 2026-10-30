<?php

// DAIRY Etapa 13: presupuesto de merma/ventas, personal y turnos, trazabilidad de tareas correctivas.
// Solo agrega tablas/columnas (no destructiva, idempotente).

use Grocy\Services\DatabaseService;

$db = DatabaseService::GetInstance()->GetDbConnectionRaw();

$db->exec("
CREATE TABLE IF NOT EXISTS dairy_budgets (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	branch_id INTEGER NOT NULL DEFAULT 0, -- 0 = red consolidada
	period TEXT NOT NULL, -- YYYY-MM
	sales_target REAL,
	waste_budget_amount REAL,
	waste_target_pct REAL,
	margin_target_pct REAL,
	dsi_target_days REAL,
	notes TEXT,
	updated_by_user_id INTEGER,
	updated_timestamp DATETIME DEFAULT (datetime('now', 'localtime')),
	UNIQUE (branch_id, period)
);

CREATE TABLE IF NOT EXISTS employees (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	user_id INTEGER UNIQUE, -- usuario de Grocy (opcional) para fichar y ver su horario
	name TEXT NOT NULL,
	branch_id INTEGER NOT NULL,
	position TEXT NOT NULL DEFAULT 'Auxiliar',
	department TEXT NOT NULL DEFAULT 'Lácteos',
	weekly_hours REAL,
	hourly_cost REAL,
	active INTEGER NOT NULL DEFAULT 1,
	row_created_timestamp DATETIME DEFAULT (datetime('now', 'localtime'))
);

CREATE TABLE IF NOT EXISTS employee_shifts (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	employee_id INTEGER NOT NULL,
	branch_id INTEGER NOT NULL,
	shift_date DATE NOT NULL,
	start_time TEXT NOT NULL, -- HH:MM
	end_time TEXT NOT NULL, -- HH:MM (menor que inicio = termina al día siguiente)
	break_minutes INTEGER NOT NULL DEFAULT 0,
	department TEXT,
	role TEXT,
	status TEXT NOT NULL DEFAULT 'planned', -- planned, published, cancelled
	notes TEXT,
	created_by_user_id INTEGER,
	row_created_timestamp DATETIME DEFAULT (datetime('now', 'localtime'))
);
CREATE INDEX IF NOT EXISTS ix_employee_shifts_date ON employee_shifts (branch_id, shift_date);
CREATE INDEX IF NOT EXISTS ix_employee_shifts_emp ON employee_shifts (employee_id, shift_date);

CREATE TABLE IF NOT EXISTS employee_absences (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	employee_id INTEGER NOT NULL,
	absence_type TEXT NOT NULL DEFAULT 'vacation', -- vacation, sick, permission, other
	start_date DATE NOT NULL,
	end_date DATE NOT NULL,
	status TEXT NOT NULL DEFAULT 'requested', -- requested, approved, rejected
	notes TEXT,
	requested_by_user_id INTEGER,
	decided_by_user_id INTEGER,
	decided_timestamp DATETIME,
	row_created_timestamp DATETIME DEFAULT (datetime('now', 'localtime'))
);

CREATE TABLE IF NOT EXISTS employee_availability (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	employee_id INTEGER NOT NULL,
	weekday INTEGER NOT NULL, -- 1 = lunes … 7 = domingo
	start_time TEXT NOT NULL,
	end_time TEXT NOT NULL,
	UNIQUE (employee_id, weekday)
);

CREATE TABLE IF NOT EXISTS employee_attendance (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	employee_id INTEGER NOT NULL,
	shift_id INTEGER,
	clock_in DATETIME NOT NULL,
	clock_out DATETIME,
	source TEXT NOT NULL DEFAULT 'web', -- web, mobile, manager
	notes TEXT,
	recorded_by_user_id INTEGER
);
CREATE INDEX IF NOT EXISTS ix_employee_attendance_emp ON employee_attendance (employee_id, clock_in);

CREATE TABLE IF NOT EXISTS shift_swap_requests (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	shift_id INTEGER NOT NULL,
	from_employee_id INTEGER NOT NULL,
	to_employee_id INTEGER NOT NULL,
	status TEXT NOT NULL DEFAULT 'requested', -- requested, approved, rejected
	notes TEXT,
	decided_by_user_id INTEGER,
	decided_timestamp DATETIME,
	row_created_timestamp DATETIME DEFAULT (datetime('now', 'localtime'))
);

CREATE TABLE IF NOT EXISTS coverage_requirements (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	branch_id INTEGER NOT NULL,
	department TEXT NOT NULL DEFAULT 'Lácteos',
	weekday INTEGER NOT NULL, -- 1..7, 0 = todos los días
	start_time TEXT NOT NULL,
	end_time TEXT NOT NULL,
	min_staff INTEGER NOT NULL DEFAULT 1
);
");

// Tareas de piso: origen (merma, alerta…) y turno al que se asignan
$cols = array_column($db->query('PRAGMA table_info(workforce_store_tasks)')->fetchAll(\PDO::FETCH_ASSOC), 'name');
foreach (['source_type' => 'TEXT', 'source_id' => 'INTEGER', 'shift_id' => 'INTEGER', 'assigned_employee_id' => 'INTEGER', 'created_timestamp' => 'DATETIME'] as $col => $type)
{
	if (!in_array($col, $cols))
	{
		$db->exec("ALTER TABLE workforce_store_tasks ADD COLUMN $col $type");
	}
}

// Permisos: WORKFORCE (gestión de turnos) y BUDGET_MANAGE, bajo ADMIN
$adminId = intval($db->query("SELECT id FROM permission_hierarchy WHERE name = 'ADMIN'")->fetchColumn());
$insert = $db->prepare('INSERT OR IGNORE INTO permission_hierarchy (name, parent) VALUES (:name, :parent)');
$getId = $db->prepare('SELECT id FROM permission_hierarchy WHERE name = :name');
foreach (['WORKFORCE' => ['WORKFORCE_SCHEDULE_MANAGE', 'WORKFORCE_ATTENDANCE_MANAGE'], 'BUDGET_MANAGE' => []] as $group => $children)
{
	$insert->execute([':name' => $group, ':parent' => $adminId]);
	$getId->execute([':name' => $group]);
	$groupId = intval($getId->fetchColumn());
	foreach ($children as $child)
	{
		$insert->execute([':name' => $child, ':parent' => $groupId]);
	}
}
