<?php
$db = new PDO('sqlite:data/grocy_es.db');
$row = $db->query("
    SELECT c.*, e.id AS employee_id, e.position, e.weekly_hours, e.hourly_cost, e.department AS emp_dept
    FROM workforce_planilla_collaborators c
    JOIN employees e ON e.payroll_code = c.payroll_code
    WHERE c.status = 'ACTIVO' AND e.active = 1
    ORDER BY c.department, c.name LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

print_r($row);

$audits = $db->query("SELECT distinct payroll_code FROM workforce_shift_audit_log")->fetchAll(PDO::FETCH_COLUMN);
echo "Audits seeded for: " . implode(", ", $audits) . "\n";

