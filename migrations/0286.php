<?php

// DAIRY Etapa 31: escenarios de personal guardados (simulador «¿qué pasa si…?»). Solo lectura sobre datos productivos:
// guarda parámetros y el resultado calculado para comparar; nunca modifica turnos ni horarios publicados.

$db = \Grocy\Services\DatabaseService::GetInstance()->GetDbConnectionRaw();
$db->exec("CREATE TABLE IF NOT EXISTS labor_scenarios (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	branch_id INTEGER NOT NULL,
	scenario_date TEXT NOT NULL,
	name TEXT NOT NULL,
	params_json TEXT NOT NULL,
	result_json TEXT NOT NULL,
	created_by INTEGER,
	row_created_timestamp DATETIME DEFAULT (datetime('now', 'localtime'))
)");
$db->exec('CREATE INDEX IF NOT EXISTS ix_labor_scenarios_branch ON labor_scenarios (branch_id, scenario_date)');
