<?php
define('GROCY_DATAPATH', __DIR__ . '/../data');
require_once __DIR__ . '/../packages/autoload.php';
require_once GROCY_DATAPATH . '/config.php';
require_once __DIR__ . '/../config-dist.php';
require_once __DIR__ . '/../services/BaseService.php';
require_once __DIR__ . '/../services/DatabaseService.php';
require_once __DIR__ . '/../services/WorkforceAutoScheduleService.php';
require_once __DIR__ . '/../services/DemoSchedulingScenarioService.php';

use Grocy\Services\DemoSchedulingScenarioService;
use Grocy\Services\WorkforceAutoScheduleService;

$demoSvc = DemoSchedulingScenarioService::GetInstance();
$branchId = $demoSvc->EnsureDemoBranch();
$auto = WorkforceAutoScheduleService::GetInstance();
$monthSched = $auto->GetMonthSchedule($branchId, 2026, 11);

echo "Month collaborators: " . count($monthSched['collaborators']) . "\n";
echo "First collaborator: " . $monthSched['collaborators'][0]['name'] . "\n";
echo "First collab shifts in Nov: " . count($monthSched['collaborators'][0]['shifts']) . "\n";
echo "KPIs: ";
print_r($monthSched['kpis']);
