<?php

// DAILY — Integración global (Fase 3): las operaciones de Conciliación & Sensible pasan por el inventario central.
// Cada movimiento sensible guarda la transacción de stock que lo contabilizó (kardex y libro) y un identificador
// de operación único para que un reintento no lo duplique.

$db = \Grocy\Services\DatabaseService::GetInstance()->GetDbConnectionRaw();
$cols = fn(string $t) => array_column($db->query("PRAGMA table_info($t)")->fetchAll(\PDO::FETCH_ASSOC), 'name');
$add = function (string $t, string $c, string $def) use ($db, $cols) {
	if (!in_array($c, $cols($t), true))
	{
		$db->exec("ALTER TABLE $t ADD COLUMN $c $def");
	}
};

$add('sensitive_inventory_movements', 'stock_transaction_id', 'TEXT');
$add('sensitive_inventory_movements', 'operation_id', 'TEXT');
$db->exec('CREATE UNIQUE INDEX IF NOT EXISTS ix_sim_operation ON sensitive_inventory_movements (operation_id) WHERE operation_id IS NOT NULL');
$add('sensitive_investigation_cases', 'adjustment_transaction_id', 'TEXT');

// Tareas por sucursal (filtro de contexto y sustituciones de personal); las existentes quedan generales (NULL)
$add('tasks', 'branch_id', 'INTEGER');
$add('tasks', 'source_ref', 'TEXT');
$db->exec('CREATE INDEX IF NOT EXISTS ix_tasks_branch ON tasks (branch_id)');
