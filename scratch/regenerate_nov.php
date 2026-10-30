<?php
require_once __DIR__ . '/../tests/dairy/bootstrap.php';
$svc = \Grocy\Services\WorkforceAutoScheduleService::GetInstance();
$res = $svc->GenerateMonth(1, 2026, 11, true);
echo "Regenerated Nov 2026: Total shifts=" . $res['total_shifts'] . "\n";

$db = Grocy\Services\DatabaseService::getInstance()->GetDbConnection();
$vcs = $db->query("SELECT payroll_code, shift_date, shift_code FROM workforce_monthly_schedules WHERE shift_code = 'VC' ORDER BY payroll_code, shift_date")->fetchAll(PDO::FETCH_ASSOC);
echo "Total VC shifts generated: " . count($vcs) . "\n";
foreach ($vcs as $v) {
    echo "  {$v['payroll_code']} on {$v['shift_date']}: {$v['shift_code']}\n";
}
