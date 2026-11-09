<?php
define('GROCY_DATAPATH', __DIR__ . '/../data');
require __DIR__ . '/../packages/autoload.php';
require __DIR__ . '/../app.php';
$svc = \Grocy\Services\CustomerOrderService::GetInstance();
$t = microtime(true); $all = $svc->List(null, 'all', 200); printf("List %d: %.0f ms\n", count($all), (microtime(true)-$t)*1000);
$t = microtime(true); $n=0; $slow=[];
\Grocy\Services\ScanService::GetInstance()->WithAvailabilityMemo(function () use ($all, $svc, &$n, &$slow) {
	foreach ($all as $o) { $a = microtime(true); try { $svc->Get(intval($o['id'])); $n++; } catch (\Exception $e) {} $slow[] = [(microtime(true)-$a)*1000, $o['id']]; }
});
printf("Get x%d: %.0f ms\n", $n, (microtime(true)-$t)*1000);
usort($slow, fn($a,$b)=>$b[0]<=>$a[0]); foreach (array_slice($slow,0,3) as $s) printf("  id %s %.0f ms\n", $s[1], $s[0]);
