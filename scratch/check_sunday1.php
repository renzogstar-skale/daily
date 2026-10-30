<?php
require_once __DIR__ . '/../tests/dairy/bootstrap.php';
$db = Grocy\Services\DatabaseService::getInstance()->GetDbConnection();

$shifts = $db->query("SELECT payroll_code, shift_code FROM workforce_monthly_schedules WHERE shift_date='2026-11-01'")->fetchAll(PDO::FETCH_ASSOC);
foreach ($shifts as $s) {
    echo "  {$s['payroll_code']}: {$s['shift_code']}\n";
}
