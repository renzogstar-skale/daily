<?php
define('GROCY_DATAPATH', __DIR__ . '/../data');
require __DIR__ . '/../packages/autoload.php';
require __DIR__ . '/../app.php';

$t0 = microtime(true);
$data = \Grocy\Services\TodayCommandService::GetInstance()->Get(null, true, fn($path) => true);
$t1 = microtime(true);
echo "TodayCommandService::Get took: " . round(($t1 - $t0) * 1000, 1) . " ms\n";

$t2 = microtime(true);
$selectedDate = \Grocy\Services\DailyMetricsService::AnchorDate(null);
$execData = \Grocy\Services\TodayExecutiveSalesService::GetInstance()->GetExecutiveData(
    null, $selectedDate, 'todos', 'todas', 'sales', 'sales'
);
$t3 = microtime(true);
echo "TodayExecutiveSalesService::GetExecutiveData took: " . round(($t3 - $t2) * 1000, 1) . " ms\n";

