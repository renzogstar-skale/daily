<?php

// DAILY — Fases 4 a 16: Columnas para Centro de Comando Gerencial de Prevención de Pérdidas,
// Flujo guiado de 15 pasos con persistencia de estado, 5 porqués, desglose financiero y notas de relevo.

$db = \Grocy\Services\DatabaseService::GetInstance()->GetDbConnectionRaw();

$cols = array_column($db->query("PRAGMA table_info(sensitive_investigation_cases)")->fetchAll(\PDO::FETCH_ASSOC), 'name');

$toAdd = [
	'cause_status' => "TEXT DEFAULT 'sospechada'",
	'five_whys_json' => "TEXT",
	'wizard_step' => "INTEGER DEFAULT 1",
	'wizard_state_json' => "TEXT",
	'financial_recovery_json' => "TEXT",
	'handover_notes' => "TEXT",
];

foreach ($toAdd as $col => $definition)
{
	if (!in_array($col, $cols, true))
	{
		$db->exec("ALTER TABLE sensitive_investigation_cases ADD COLUMN {$col} {$definition}");
	}
}

