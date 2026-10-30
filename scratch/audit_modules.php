<?php
$db = new PDO("sqlite:data/grocy_es.db");
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

function cols($db, $table) {
    $st = $db->query("PRAGMA table_info($table)");
    return array_column($st->fetchAll(PDO::FETCH_ASSOC), 'name');
}

$tables = ['employees', 'users', 'employee_shifts', 'employee_attendance', 'employee_attendance_adjustments', 'products', 'stock_cycle_counts', 'sensitive_investigation_cases', 'dairy_alerts', 'inventory_adjustments'];
foreach ($tables as $t) {
    echo "=== Table $t ===\n";
    echo implode(', ', cols($db, $t)) . "\n";
}

echo "\n=== EMPLOYEES SAMPLE ===\n";
$emps = $db->query("SELECT * FROM employees LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
foreach ($emps as $e) {
    print_r($e);
}
