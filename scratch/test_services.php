<?php
define('GROCY_DATAPATH', __DIR__ . '/../data');
require_once __DIR__ . '/../packages/autoload.php';
require_once GROCY_DATAPATH . '/config.php';
require_once __DIR__ . '/../config-dist.php';

require_once __DIR__ . '/../services/BaseService.php';
require_once __DIR__ . '/../services/DatabaseService.php';
require_once __DIR__ . '/../services/WorkforceScheduleService.php';
require_once __DIR__ . '/../services/DairyDynamicScheduleService.php';
require_once __DIR__ . '/../services/WorkforceScheduleEquivalenceService.php';
require_once __DIR__ . '/../services/WorkforceAutoScheduleService.php';
require_once __DIR__ . '/../services/DemoSchedulingScenarioService.php';

use Grocy\Services\DemoSchedulingScenarioService;
use Grocy\Services\WorkforceScheduleService;
use Grocy\Services\DairyDynamicScheduleService;
use Grocy\Services\WorkforceScheduleEquivalenceService;
use Grocy\Services\WorkforceAutoScheduleService;

$demoSvc = DemoSchedulingScenarioService::GetInstance();
$branchId = $demoSvc->EnsureDemoBranch();

echo "Testing WorkforceScheduleService::GetWeek on DEMO branch ($branchId)...\n";
$ws = WorkforceScheduleService::GetInstance();
$week = $ws->GetWeek($branchId, '2026-11-02');
echo "Week employees count: " . count($week['employees']) . "\n";
echo "Week planned count: " . $week['planned_count'] . "\n";
echo "Week labor cost: " . $week['labor_cost'] . "\n";

echo "\nTesting DairyDynamicScheduleService::CalculateDynamicPlan...\n";
$dyn = DairyDynamicScheduleService::GetInstance();
$plan = $dyn->CalculateDynamicPlan($branchId, '2026-11-02', []);
echo "Dynamic plan departments: " . implode(', ', array_keys($plan['departments'])) . "\n";
echo "Labor budget: " . ($plan['kpis']['labor_budget']['value'] ?? 'N/A') . "\n";

echo "\nTesting WorkforceAutoScheduleService::GetMonthSchedule...\n";
$auto = WorkforceAutoScheduleService::GetInstance();
$monthSched = $auto->GetMonthSchedule($branchId, 2026, 11);
echo "Month stats: " . ($monthSched['summary']['total_shifts'] ?? count($monthSched['shifts'] ?? [])) . " shifts\n";

echo "\nTesting WorkforceScheduleEquivalenceService::GetEquityDashboardData...\n";
$eq = WorkforceScheduleEquivalenceService::GetInstance();
$equity = $eq->GetEquityDashboardData($branchId, []);
echo "Equity collaborators: " . count($equity['collaborators'] ?? []) . "\n";

echo "\nTesting WorkforceAutoScheduleService::GetAreaViewData...\n";
$areaData = $auto->GetAreaViewData($branchId, '2026-11-02');
echo "Areas count in GetAreaViewData: " . count($areaData['areas']) . "\n";
foreach ($areaData['areas'] as $ar) {
    echo " - " . $ar['name'] . ": assigned=" . $ar['assigned'] . ", scheduled=" . $ar['scheduled'] . "\n";
}

echo "\nTesting WorkforceAutoScheduleService::GetEmployeeViewData...\n";
$empData = $auto->GetEmployeeViewData($branchId, 'DEMO-001', '2026-11-02');
echo "Selected employee: " . ($empData['selected']['name'] ?? 'None') . " (" . ($empData['selected']['cargo'] ?? '') . ")\n";
echo "Weekly shifts: " . count($empData['weekly_shifts'] ?? []) . "\n";
echo "Monthly shifts: " . count($empData['monthly_shifts'] ?? []) . "\n";
