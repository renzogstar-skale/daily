<?php

// DAILY — Sistema Unificado de Inventario, Mercancías, Conciliación y Prevención de Pérdidas
// Motor de Eventos de Dominio y Contexto Operativo Compartido

$db = \Grocy\Services\DatabaseService::GetInstance()->GetDbConnectionRaw();

// 1. Bus Central de Eventos de Dominio
$db->exec("CREATE TABLE IF NOT EXISTS daily_domain_events (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	event_uuid TEXT NOT NULL UNIQUE,
	event_name TEXT NOT NULL,
	aggregate_type TEXT NOT NULL,
	aggregate_id TEXT NOT NULL,
	branch_id INTEGER,
	user_id INTEGER,
	payload TEXT NOT NULL,
	occurred_at DATETIME DEFAULT (datetime('now', 'localtime')),
	created_at DATETIME DEFAULT (datetime('now', 'localtime')),
	processed_at DATETIME,
	status TEXT NOT NULL DEFAULT 'published'
)");
$db->exec('CREATE INDEX IF NOT EXISTS ix_dde_event_name ON daily_domain_events (event_name, occurred_at)');
$db->exec('CREATE INDEX IF NOT EXISTS ix_dde_aggregate ON daily_domain_events (aggregate_type, aggregate_id)');
$db->exec('CREATE INDEX IF NOT EXISTS ix_dde_branch ON daily_domain_events (branch_id)');

// 2. Contexto Operativo Compartido entre los 7 Módulos
$db->exec("CREATE TABLE IF NOT EXISTS daily_operational_contexts (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	user_id INTEGER NOT NULL UNIQUE,
	branch_id INTEGER,
	product_id INTEGER,
	case_id INTEGER,
	device_id INTEGER,
	document_ref TEXT,
	period TEXT,
	last_module TEXT,
	metadata TEXT,
	updated_at DATETIME DEFAULT (datetime('now', 'localtime'))
)");
$db->exec('CREATE INDEX IF NOT EXISTS ix_doc_user ON daily_operational_contexts (user_id)');

