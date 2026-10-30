<?php
require_once __DIR__ . '/../tests/dairy/bootstrap.php';
$db = Grocy\Services\DatabaseService::getInstance()->GetDbConnection();

$rows = $db->query("SELECT * FROM workforce_monthly_schedules WHERE payroll_code='38042452' AND shift_date='2026-11-01'")->fetchAll(PDO::FETCH_ASSOC);
print_r($rows);
