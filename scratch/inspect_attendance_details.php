<?php
$db = new PDO('sqlite:data/grocy_es.db');
$tables = ['employee_attendance', 'employee_attendance_adjustments', 'employee_absences', 'employee_shifts', 'dairy_alerts'];
foreach ($tables as $t) {
    echo "\n=== Table: $t ===\n";
    $cols = $db->query("PRAGMA table_info($t)")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($cols as $c) {
        echo "  {$c['name']} ({$c['type']})\n";
    }
    $cnt = $db->query("SELECT COUNT(*) FROM $t")->fetchColumn();
    echo "  Total rows: $cnt\n";
    $sample = $db->query("SELECT * FROM $t LIMIT 3")->fetchAll(PDO::FETCH_ASSOC);
    echo "  Sample:\n";
    print_r($sample);
}
