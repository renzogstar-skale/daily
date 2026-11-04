<?php

// DAILY — Motor de programación: las propuestas se guardan como borrador con vista previa. Solo la aprobación del
// supervisor las lleva a workforce_monthly_schedules; un mes publicado exige reabrirlo con un motivo auditado.

$db = \Grocy\Services\DatabaseService::GetInstance()->GetDbConnectionRaw();

$db->exec("CREATE TABLE IF NOT EXISTS workforce_schedule_drafts (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	period TEXT NOT NULL,
	branch_id INTEGER NOT NULL,
	status TEXT NOT NULL DEFAULT 'draft', -- draft | approved | discarded
	report_json TEXT,
	created_by_user_id INTEGER,
	created_at DATETIME DEFAULT (datetime('now', 'localtime')),
	decided_by_user_id INTEGER,
	decided_at DATETIME
)");
$db->exec('CREATE INDEX IF NOT EXISTS ix_wsd_period ON workforce_schedule_drafts (period, branch_id, status)');

$db->exec("CREATE TABLE IF NOT EXISTS workforce_schedule_draft_rows (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	draft_id INTEGER NOT NULL REFERENCES workforce_schedule_drafts (id) ON DELETE CASCADE,
	payroll_code TEXT NOT NULL,
	employee_id INTEGER,
	shift_date TEXT NOT NULL,
	shift_code TEXT NOT NULL,
	shift_type TEXT NOT NULL,
	start_time TEXT NOT NULL,
	end_time TEXT NOT NULL,
	hours REAL NOT NULL DEFAULT 0,
	is_sunday INTEGER NOT NULL DEFAULT 0,
	is_holiday INTEGER NOT NULL DEFAULT 0,
	compensatory_origin_date TEXT,
	notes TEXT,
	UNIQUE (draft_id, payroll_code, shift_date)
)");

$db->exec("CREATE TABLE IF NOT EXISTS workforce_schedule_reopens (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	period TEXT NOT NULL,
	branch_id INTEGER NOT NULL,
	reason TEXT NOT NULL,
	user_id INTEGER,
	created_at DATETIME DEFAULT (datetime('now', 'localtime')),
	consumed_at DATETIME
)");
