<?php
define('GROCY_DATAPATH', __DIR__ . '/../data');
require __DIR__ . '/../packages/autoload.php';
require __DIR__ . '/../app.php';

$t0 = microtime(true);
$svc = \Grocy\Services\QualityExpiryService::GetInstance();
$data = $svc->GetQualityOverviewData(null, '2026-11-01', '2026-11-04');
$t1 = microtime(true);
echo "QualityExpiryService::GetQualityOverviewData took: " . round(($t1 - $t0) * 1000, 1) . " ms\n";

