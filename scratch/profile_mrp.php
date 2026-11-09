<?php
define('GROCY_IS_EMBEDDED_INSTALL', false);
define('GROCY_DATAPATH', __DIR__ . '/../data');
define('GROCY_USER_ID', 1);

require_once __DIR__ . '/../app.php';

$mrpService = \Grocy\Services\MrpService::GetInstance();
$procService = \Grocy\Services\SupplierProcurementService::GetInstance();
$invoiceService = \Grocy\Services\SupplierInvoiceService::GetInstance();

$t0 = microtime(true);
$mrp = $mrpService->CalculateMrp(null, 14);
echo "CalculateMrp: " . round(microtime(true) - $t0, 3) . "s\n";

$t1 = microtime(true);
$orders = $mrpService->GetProcurementOrders(null);
echo "GetProcurementOrders: " . round(microtime(true) - $t1, 3) . "s\n";

$t2 = microtime(true);
$forecast = $mrpService->GetForecastAccuracy(null, 4);
echo "GetForecastAccuracy: " . round(microtime(true) - $t2, 3) . "s\n";

$t3 = microtime(true);
$invoices = $invoiceService->GetInvoices(null);
echo "GetInvoices: " . round(microtime(true) - $t3, 3) . "s\n";

