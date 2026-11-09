<?php
define('GROCY_DATAPATH', __DIR__ . '/../data');
require __DIR__ . '/../packages/autoload.php';
require __DIR__ . '/../app.php';

$qualityService = \Grocy\Services\QualityExpiryService::GetInstance();
$t0 = microtime(true);
$batches = $qualityService->GetBatchExpiryOverview(null);
echo "GetBatchExpiryOverview: " . round((microtime(true) - $t0) * 1000, 1) . " ms (count: " . count($batches) . ")\n";

$t0 = microtime(true);
$coldChain = $qualityService->GetColdChainSummary(null);
echo "GetColdChainSummary: " . round((microtime(true) - $t0) * 1000, 1) . " ms\n";

$t0 = microtime(true);
$stats = $qualityService->GetQualityStats(null);
echo "GetQualityStats: " . round((microtime(true) - $t0) * 1000, 1) . " ms\n";

