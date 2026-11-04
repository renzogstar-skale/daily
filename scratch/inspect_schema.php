<?php
$db = new PDO('sqlite:data/grocy_es.db');
$tables = [
    'sensitive_investigation_cases',
    'sensitive_case_preventive_plans',
    'sensitive_case_hypotheses',
    'sensitive_case_calls',
    'sensitive_case_tasks',
    'sensitive_case_checkins',
    'sensitive_case_events',
    'sensitive_investigation_evidence',
    'loss_causes'
];
foreach ($tables as $t) {
    echo "=== $t ===\n";
    foreach ($db->query("PRAGMA table_info($t)") as $c) {
        echo "  {$c['name']} ({$c['type']})\n";
    }
}
