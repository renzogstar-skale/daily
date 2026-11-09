<?php
define('GROCY_DATAPATH', __DIR__ . '/../data');
require __DIR__ . '/../packages/autoload.php';
require __DIR__ . '/../app.php';
$db = \Grocy\Services\DatabaseService::GetInstance()->GetDbConnectionRaw();
$svc = \Grocy\Services\CustomerOrderService::GetInstance();
$br = array_map('intval', array_column($db->query('SELECT id FROM branches WHERE active = 1')->fetchAll(PDO::FETCH_ASSOC), 'id'));
$t = microtime(true); $pm = $svc->ListPriceMap(); $fm = $svc->FreeMap($br); printf("bulk %.0f ms\n", (microtime(true)-$t)*1000);
$pids = array_column($db->query('SELECT id FROM products WHERE active = 1')->fetchAll(PDO::FETCH_ASSOC), 'id');
$bad = 0; $t = microtime(true);
foreach ($pids as $pid) { $pid = intval($pid);
	if (abs($svc->ListPrice($pid) - ($pm[$pid] ?? 0)) > 0.001) { $bad++; if ($bad < 5) echo "price $pid\n"; }
	foreach ($br as $b) { $o = max(0.0, round($svc->Free($pid, $b), 2)); $n = max(0.0, round(round($fm[$pid][$b] ?? 0, 4), 2)); if (abs($o - $n) > 0.001) { $bad++; if ($bad < 8) echo "free $pid/$b $o vs $n\n"; } }
}
printf("old %.0f ms; products %d branches %d; diffs %d\n", (microtime(true)-$t)*1000, count($pids), count($br), $bad);
echo "dup cache rows: ", $db->query('SELECT COUNT(*) FROM (SELECT product_id FROM cache__products_last_purchased GROUP BY product_id HAVING COUNT(*) > 1)')->fetchColumn(), "\n";
