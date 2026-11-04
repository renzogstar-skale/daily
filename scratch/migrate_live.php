<?php
$db = new PDO('sqlite:data/grocy_es.db');
$cols = array_column($db->query('PRAGMA table_info(sensitive_investigation_cases)')->fetchAll(PDO::FETCH_ASSOC), 'name');
$toAdd = [
    'cause_status' => "TEXT DEFAULT 'sospechada'",
    'five_whys_json' => "TEXT",
    'wizard_step' => "INTEGER DEFAULT 1",
    'wizard_state_json' => "TEXT",
    'financial_recovery_json' => "TEXT",
    'handover_notes' => "TEXT",
];
foreach ($toAdd as $col => $def) {
    if (!in_array($col, $cols, true)) {
        $db->exec("ALTER TABLE sensitive_investigation_cases ADD COLUMN {$col} {$def}");
        echo "Added {$col}\n";
    } else {
        echo "Exists: {$col}\n";
    }
}

