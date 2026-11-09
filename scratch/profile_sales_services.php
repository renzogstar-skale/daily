<?php
require_once __DIR__ . '/../packages/autoload.php';

define('GROCY_IS_EMBEDDED_INSTALL', false);
define('GROCY_DATAPATH', __DIR__ . '/../data');
define('GROCY_USER_ID', 1);
define('GROCY_MODE', 'production');
define('GROCY_DEFAULT_LOCALE', 'es');

$sharedFilters = \Grocy\Helpers\DailyFilters::FromQuery([
    'start_date' => date('Y-m-d', strtotime('-29 days')),
    'end_date' => date('Y-m-d')
]);

$t = microtime(true);
$salesDash = \Grocy\Services\SalesDashboardService::GetInstance()->Dashboard($sharedFilters);
echo "SalesDashboardService: " . round(microtime(true) - $t, 3) . "s\n";

$t = microtime(true);
$deptPerformance = \Grocy\Services\DepartmentPerformanceService::GetInstance()->GetDepartmentPerformance($sharedFilters);
echo "DepartmentPerformance: " . round(microtime(true) - $t, 3) . "s\n";

$t = microtime(true);
$deptExec = \Grocy\Services\DepartmentPerformanceService::GetInstance()->GetExecutiveDashboard($sharedFilters);
echo "GetExecutiveDashboard: " . round(microtime(true) - $t, 3) . "s\n";

$t = microtime(true);
$productCatalogDashboard = \Grocy\Services\ProductPerformanceService::GetInstance()->GetProductCatalogDashboard($sharedFilters);
echo "ProductCatalogDashboard: " . round(microtime(true) - $t, 3) . "s\n";

$t = microtime(true);
$branchSupervisionDashboard = \Grocy\Services\BranchSupervisionService::GetInstance()->GetBranchSupervisionDashboard($sharedFilters);
echo "BranchSupervisionDashboard: " . round(microtime(true) - $t, 3) . "s\n";

$t = microtime(true);
$salesAuditDashboard = \Grocy\Services\SalesAuditTraceService::GetInstance()->GetSalesAuditDashboard($sharedFilters);
echo "SalesAuditDashboard: " . round(microtime(true) - $t, 3) . "s\n";
