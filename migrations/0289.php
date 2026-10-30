<?php

// DAIRY Etapa 34: reportes personalizables y programados (/reports → «Mis reportes»).
// Definición = conjunto de datos del catálogo (ReportBuilderService::DATASETS, SQL fijo y parametrizado) + columnas,
// período, sucursal, orden y frecuencia. Cada ejecución (manual o programada) guarda su CSV en dairy_report_runs.
// Las programadas corren con los permisos y sucursales del dueño (se revalidan en cada ejecución).

$db = \Grocy\Services\DatabaseService::GetInstance()->GetDbConnectionRaw();
$db->exec("CREATE TABLE IF NOT EXISTS dairy_report_definitions (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	name TEXT NOT NULL,
	dataset TEXT NOT NULL,
	config_json TEXT NOT NULL,
	schedule TEXT NOT NULL DEFAULT 'none' CHECK(schedule IN ('none', 'daily', 'weekly', 'monthly')),
	schedule_hour INTEGER NOT NULL DEFAULT 7,
	next_run_at DATETIME,
	last_run_at DATETIME,
	owner_user_id INTEGER NOT NULL,
	active TINYINT NOT NULL DEFAULT 1,
	row_created_timestamp DATETIME DEFAULT (datetime('now', 'localtime')),
	updated_timestamp DATETIME
)");
$db->exec("CREATE TABLE IF NOT EXISTS dairy_report_runs (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	definition_id INTEGER NOT NULL,
	trigger_type TEXT NOT NULL DEFAULT 'manual',
	status TEXT NOT NULL DEFAULT 'ok',
	period_from TEXT,
	period_to TEXT,
	row_count INTEGER NOT NULL DEFAULT 0,
	csv TEXT,
	error_message TEXT,
	user_id INTEGER,
	row_created_timestamp DATETIME DEFAULT (datetime('now', 'localtime'))
)");
$db->exec('CREATE INDEX IF NOT EXISTS ix_dairy_report_runs_def ON dairy_report_runs (definition_id, id)');
$db->exec('CREATE INDEX IF NOT EXISTS ix_dairy_report_defs_due ON dairy_report_definitions (active, schedule, next_run_at)');
