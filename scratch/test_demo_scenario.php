<?php
define('GROCY_DATAPATH', __DIR__ . '/../data');
require_once __DIR__ . '/../packages/autoload.php';
require_once GROCY_DATAPATH . '/config.php';
require_once __DIR__ . '/../config-dist.php';

require_once __DIR__ . '/../services/BaseService.php';
require_once __DIR__ . '/../services/DatabaseService.php';
require_once __DIR__ . '/../services/WorkforceScheduleService.php';
require_once __DIR__ . '/../services/WorkforceAutoScheduleService.php';
require_once __DIR__ . '/../services/DemoSchedulingScenarioService.php';

use Grocy\Services\DemoSchedulingScenarioService;

$svc = DemoSchedulingScenarioService::GetInstance();
echo "Executing ResetDemoScenario()...\n";
$res = $svc->ResetDemoScenario();
print_r($res);

$branchId = $svc->EnsureDemoBranch();
echo "Demo Branch ID: $branchId\n";

$db = \Grocy\Services\DatabaseService::GetInstance()->GetDbConnectionRaw();
$empCount = $db->query("SELECT COUNT(*) FROM employees WHERE branch_id = $branchId")->fetchColumn();
echo "Employees in DEMO branch: $empCount\n";

$shiftsCount = $db->query("SELECT COUNT(*) FROM employee_shifts WHERE branch_id = $branchId")->fetchColumn();
echo "Shifts in DEMO branch: $shiftsCount\n";

$monthlyCount = $db->query("SELECT COUNT(*) FROM workforce_monthly_schedules WHERE payroll_code LIKE 'DEMO-%'")->fetchColumn();
echo "Monthly schedules in DEMO: $monthlyCount\n";

$areasCount = $db->query("SELECT COUNT(DISTINCT department) FROM employees WHERE branch_id = $branchId")->fetchColumn();
echo "Areas in DEMO branch: $areasCount\n";

// Test 30-min matrix
$matrix = $svc->GetCoverageMatrix30Min($branchId, '2026-11-04', 'Electrónica', 'normal');
echo "Matrix intervals: " . count($matrix['intervals']) . "\n";
echo "Sample interval 12:00: \n";
foreach ($matrix['intervals'] as $int) {
    if ($int['time'] === '12:00') {
        print_r($int);
        break;
    }
}
