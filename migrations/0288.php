<?php

// DAIRY Etapa 34: acciones propuestas por Arturito (IA). Nunca se ejecutan solas: el usuario ve la vista previa,
// confirma y el servidor vuelve a validar el permiso de la acción. Cada ejecución queda en esta bitácora con
// usuario, fecha, parámetros y el registro creado (orden en borrador, plan de acción o tarea de piso).

$db = \Grocy\Services\DatabaseService::GetInstance()->GetDbConnectionRaw();
$db->exec("CREATE TABLE IF NOT EXISTS dairy_ai_actions (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	action_type TEXT NOT NULL,
	branch_id INTEGER,
	params_json TEXT NOT NULL,
	question TEXT,
	status TEXT NOT NULL DEFAULT 'executed',
	result_json TEXT,
	error_message TEXT,
	user_id INTEGER NOT NULL,
	row_created_timestamp DATETIME DEFAULT (datetime('now', 'localtime'))
)");
$db->exec('CREATE INDEX IF NOT EXISTS ix_dairy_ai_actions_user ON dairy_ai_actions (user_id, row_created_timestamp)');
