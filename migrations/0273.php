<?php

// DAIRY Etapa 14: sucursales asignadas por usuario (sin filas = acceso a toda la red)

use Grocy\Services\DatabaseService;

DatabaseService::GetInstance()->GetDbConnectionRaw()->exec("
CREATE TABLE IF NOT EXISTS user_branches (
	user_id INTEGER NOT NULL,
	branch_id INTEGER NOT NULL,
	PRIMARY KEY (user_id, branch_id)
);
");
