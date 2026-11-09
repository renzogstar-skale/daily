<?php
define('GROCY_IS_EMBEDDED_INSTALL', false);
define('GROCY_DATAPATH', __DIR__ . '/../data');
define('GROCY_USER_ID', 1);

require_once __DIR__ . '/../app.php';

$t0 = microtime(true);
$procService = \Grocy\Services\SupplierProcurementService::GetInstance();
$branchService = \Grocy\Services\BranchService::GetInstance();

$suppliers = $procService->GetSuppliers();
echo "GetSuppliers: " . round(microtime(true) - $t0, 3) . "s\n";

$t1 = microtime(true);
$priceHistory = $procService->GetPriceHistory();
echo "GetPriceHistory: " . round(microtime(true) - $t1, 3) . "s\n";

$t2 = microtime(true);
$deliveries = $procService->GetDeliveries();
echo "GetDeliveries: " . round(microtime(true) - $t2, 3) . "s\n";

$t3 = microtime(true);
$incidents = $procService->GetIncidents();
echo "GetIncidents: " . round(microtime(true) - $t3, 3) . "s\n";

$t4 = microtime(true);
$recommendations = $procService->GetSmartProcurementRecommendations();
echo "GetSmartProcurementRecommendations: " . round(microtime(true) - $t4, 3) . "s\n";

$t5 = microtime(true);
$budgetData = $procService->BudgetOverview(null, null);
echo "BudgetOverview: " . round(microtime(true) - $t5, 3) . "s\n";

$t6 = microtime(true);
$rawDb = \Grocy\Services\DatabaseService::GetInstance()->GetDbConnectionRaw();
$supplierContracts = $rawDb->query("SELECT * FROM daily_supplier_contracts ORDER BY id DESC")->fetchAll(\PDO::FETCH_ASSOC);
echo "Contracts: " . round(microtime(true) - $t6, 3) . "s\n";

echo "Total: " . round(microtime(true) - $t0, 3) . "s\n";
