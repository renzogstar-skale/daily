<?php
define('GROCY_DATAPATH', __DIR__ . '/../data');
require __DIR__ . '/../packages/autoload.php';
require __DIR__ . '/../app.php';
$svc = \Grocy\Services\StockExecutiveOverviewService::GetInstance();
$m = \Grocy\Services\DailyMetricsService::GetInstance();
$call = function ($name, ...$args) use ($svc) { $r = new ReflectionMethod($svc, $name); $r->setAccessible(true); return $r->invoke($svc, ...$args); };
$t = function ($label, $fn) { $a = microtime(true); $r = $fn(); printf("%-28s %6.0f ms\n", $label, (microtime(true) - $a) * 1000); return $r; };
$s = $t('LoadScope', fn() => $call('LoadScope', []));
$s['counts'] = $t('StockStatusCounts', fn() => $m->StockStatusCounts($s['branchId'], $s['fefoDays'], $s['deptKeys']));
$s['totalVal'] = 0; $s['totalUnits'] = 0; $s['totalRisk'] = 0;
$db = \Grocy\Services\DatabaseService::GetInstance()->GetDbConnectionRaw();
// Perfil por sentencia dentro de Assemble: envolver PDO::query/prepare no es trivial; medir el total
$t('AssembleExecutiveData', fn() => $call('AssembleExecutiveData', $s + ['db' => $db, 'm' => $m]));
extract($s);
$t('BuildEvolutionReal', fn() => $call('BuildEvolutionReal', $period, $date, $branchId, 0.0, 0.0, $byBranch, $branchName, $deptKeys));
$evolution = $call('BuildEvolutionReal', $period, $date, $branchId, 0.0, 0.0, $byBranch, $branchName, $deptKeys);
$t('Insights Build', fn() => \Grocy\Services\StockOverviewInsightsService::GetInstance()->Build(['db' => $db, 'branchId' => $branchId, 'branchName' => $branchName, 'deptKeys' => $deptKeys, 'fefoDays' => $fefoDays, 'date' => $date, 'periodDays' => $periodDays,
	'startTs' => date('Y-m-d 23:59:59', strtotime("$date -$periodDays days")), 'lotsAll' => $lotsAll, 'soldPeriod' => $soldPeriod, 'sold28' => $sold28, 'deptOf' => $deptOf, 'totalUnits' => 0, 'totalVal' => 0, 'evolution' => $evolution]));
$t('StockStatusCounts x branches', function () use ($branchName, $m, $fefoDays, $deptKeys) { foreach ($branchName as $b => $n) $m->StockStatusCounts($b, $fefoDays, $deptKeys); });
$t('BuildTopProducts', fn() => $call('BuildTopProducts', $department, 1.0, 1.0, $skuQuery, $invStatus, ['by_product' => $byProduct, 'sold_day' => $soldDay, 'cogs' => $cogsOf]));
$t('BuildExpiringProducts', fn() => $call('BuildExpiringProducts', $department, $branchId, $fefoDays, $skuQuery, $lots, $deptOf, $branchName));
$t('BuildRealtimeMovements', fn() => $call('BuildRealtimeMovements', $branchId, $movementType, 1.0, $from, $date, $deptKeys));
