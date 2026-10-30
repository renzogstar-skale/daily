<?php
$db = new PDO('sqlite:data/grocy_es.db');

$tables = ['employee_shifts', 'employee_absences', 'employee_availability', 'workforce_monthly_schedules', 'workforce_skills_matrix', 'workforce_hourly_demand', 'shift_templates'];
foreach ($tables as $t) {
    echo "=== $t ===\n";
    $stmt = $db->query("PRAGMA table_info($t)");
    print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
}
