<?php
define('GROCY_IS_EMBEDDED_INSTALL', false);
define('GROCY_DATAPATH', __DIR__ . '/../data');
define('GROCY_USER_ID', 1);

$t0 = microtime(true);
require_once __DIR__ . '/../app.php';

$salesService = \Grocy\Services\SalesService::GetInstance();
$branchService = \Grocy\Services\BranchService::GetInstance();
$db = \Grocy\Services\DatabaseService::GetInstance()->GetDbConnectionRaw();

$startDate = date('Y-m-d', strtotime('-29 days'));
$endDate = date('Y-m-d');

$s1 = microtime(true);
$sales = $salesService->GetSales(null, $startDate, $endDate, 500, null, null);
echo "GetSales: " . round(microtime(true) - $s1, 3) . "s\n";

$s2 = microtime(true);
$analysis = \Grocy\Services\MobileManagerService::GetInstance()->Sales(null, $startDate, $endDate, null, null);
echo "MobileManager Sales: " . round(microtime(true) - $s2, 3) . "s\n";

$s3 = microtime(true);
$unlinked = \Grocy\Services\LossPreventionService::GetInstance()->PosReconciliation(null, $startDate, $endDate);
echo "PosReconciliation: " . round(microtime(true) - $s3, 3) . "s\n";

$s4 = microtime(true);
// Check lines 1190-1400 in SalesController
$stmtHourly = $db->prepare("
    SELECT strftime('%H', s.created_timestamp) AS hour,
           COUNT(DISTINCT s.id) AS tickets,
           SUM(si.amount) AS units,
           ROUND(SUM(si.price * si.amount), 2) AS net_sales
    FROM sales s
    JOIN sale_items si ON si.sale_id = s.id
    WHERE s.status = 'completed' AND date(s.created_timestamp) BETWEEN :f AND :t
    GROUP BY hour
");
$stmtHourly->execute([':f' => $startDate, ':t' => $endDate]);
$stmtHourly->fetchAll();
echo "Hourly: " . round(microtime(true) - $s4, 3) . "s\n";
