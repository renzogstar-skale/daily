<?php

// DAIRY Hoy (Centro de Comando): estado diario de la cola de decisiones por alcance (sucursal o red) para comparar contra ayer.
// Una fila por día y alcance; se sobrescribe en cada carga de /today. Idempotente.

$db = \Grocy\Services\DatabaseService::GetInstance()->GetDbConnectionRaw();
$db->exec("CREATE TABLE IF NOT EXISTS today_snapshots (
	snap_date TEXT NOT NULL,
	scope TEXT NOT NULL,
	data TEXT NOT NULL,
	updated_at DATETIME NOT NULL,
	PRIMARY KEY (snap_date, scope)
)");
