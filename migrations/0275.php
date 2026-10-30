<?php

// DAIRY Etapa 18: claves de idempotencia para operaciones enviadas desde la cola sin conexión (cabecera X-Idempotency-Key)

use Grocy\Services\DatabaseService;

DatabaseService::GetInstance()->GetDbConnectionRaw()->exec("
CREATE TABLE IF NOT EXISTS dairy_idempotency_keys (
	idempotency_key TEXT NOT NULL PRIMARY KEY,
	user_id INTEGER NOT NULL,
	request_path TEXT NOT NULL,
	status_code INTEGER, -- NULL = en proceso
	response_body TEXT,
	created_timestamp DATETIME DEFAULT (datetime('now', 'localtime'))
);
CREATE INDEX IF NOT EXISTS ix_dairy_idempotency_created ON dairy_idempotency_keys (created_timestamp);
");
