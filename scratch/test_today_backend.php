<?php
define('GROCY_IS_EMBEDDED_INSTALL', false);
define('GROCY_DATAPATH', __DIR__ . '/../data');
define('GROCY_USER_ID', 1);

require_once __DIR__ . '/../packages/autoload.php';
require_once __DIR__ . '/../app.php';

$t0 = microtime(true);
echo "Testing TodayCommandService::Get...\n";
$data = \Grocy\Services\TodayCommandService::GetInstance()->Get(null, true, fn($p) => true);
echo "Done in " . round(microtime(true) - $t0, 3) . "s\n";

$t1 = microtime(true);
echo "Testing TodayExecutiveSalesService::GetExecutiveData...\n";
$exec = \Grocy\Services\TodayExecutiveSalesService::GetInstance()->GetExecutiveData(null, '2026-11-04', 'todos', 'todas', 'sales', 'sales');
echo "Done in " . round(microtime(true) - $t1, 3) . "s\n";

echo "KPIs:\n";
print_r($exec['kpis']);

