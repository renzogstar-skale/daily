<?php

// DAIRY Etapa 13: turno vigente de quien registra la merma (contexto operativo, no atribución de culpa)

use Grocy\Services\DatabaseService;

$db = DatabaseService::GetInstance()->GetDbConnectionRaw();
$cols = array_column($db->query('PRAGMA table_info(waste_records)')->fetchAll(\PDO::FETCH_ASSOC), 'name');
if (!in_array('shift_id', $cols))
{
	$db->exec('ALTER TABLE waste_records ADD COLUMN shift_id INTEGER');
}
$db->exec('CREATE INDEX IF NOT EXISTS ix_workforce_tasks_source ON workforce_store_tasks (source_type, source_id)');
