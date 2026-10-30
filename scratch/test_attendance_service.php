<?php
define('GROCY_MODE', 'demo');
require_once 'packages/autoload.php';
require_once 'services/BaseService.php';
require_once 'services/DatabaseService.php';
require_once 'services/DemoSchedulingScenarioService.php';
require_once 'services/AttendanceService.php';

try {
    $attSvc = \Grocy\Services\AttendanceService::GetInstance();
    $demoSvc = \Grocy\Services\DemoSchedulingScenarioService::GetInstance();
    $branchId = $demoSvc->EnsureDemoBranch();
    echo "Branch ID: $branchId\n";
    $overview = $attSvc->GetAttendanceOverview($branchId, '2026-11-04');
    echo "Overview successfully generated!\n";
    echo "KPIs:\n";
    print_r($overview['kpis']);
    echo "Records count: " . count($overview['records']) . "\n";
    echo "Dept cards count: " . count($overview['dept_cards']) . "\n";
} catch (\Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
