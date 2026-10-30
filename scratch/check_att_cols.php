<?php
define('GROCY_DATAPATH', __DIR__ . '/../data');
require __DIR__ . '/../app.php';
$db = \Grocy\Services\DatabaseService::GetInstance()->GetDbConnectionRaw();
echo "EMPLOYEE_ATTENDANCE COLUMNS:\n";
print_r($db->query("PRAGMA table_info(employee_attendance)")->fetchAll(PDO::FETCH_ASSOC));
echo "ADJUSTMENTS COLUMNS:\n";
print_r($db->query("PRAGMA table_info(employee_attendance_adjustments)")->fetchAll(PDO::FETCH_ASSOC));
