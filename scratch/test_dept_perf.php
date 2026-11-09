<?php
require_once __DIR__ . '/../packages/autoload.php';
define('GROCY_DATAPATH', __DIR__ . '/../data');
require_once __DIR__ . '/../data/config.php';
require_once __DIR__ . '/../config-dist.php';

use Grocy\Services\DatabaseService;
use Grocy\Services\DailyMetricsService;
use Grocy\Helpers\DailyFilters;

$db = DatabaseService::GetInstance()->GetDbConnectionRaw();
$f = DailyFilters::FromQuery(['start_date' => '2026-10-07', 'end_date' => '2026-11-05']);
$from = $f['start_date'];
$to = $f['end_date'];
$lyFrom = date('Y-m-d', strtotime("$from -1 year"));
$lyTo = date('Y-m-d', strtotime("$to -1 year"));

$daysTotal = (int)round((strtotime($to) - strtotime($from)) / 86400) + 1;
$ppTo = date('Y-m-d', strtotime("$from -1 day"));
$ppFrom = date('Y-m-d', strtotime("$ppTo -" . ($daysTotal - 1) . ' days'));

$metrics = DailyMetricsService::GetInstance();
$deptDefs = DailyMetricsService::DEPARTMENTS;

$results = [];
$totalTY = 0.0;
$totalLY = 0.0;
$totalPP = 0.0;

$bSql = DailyFilters::BranchSql($f, 's.branch_id');

foreach ($deptDefs as $key => [$name, $color, $rawDepts])
{
    $deptSql = $metrics->DeptSql([$key]);
    
    // TY query
    $sqlTY = "SELECT IFNULL(SUM(si.subtotal), 0) as net, IFNULL(SUM(si.amount), 0) as units, COUNT(DISTINCT s.id) as tickets,
              IFNULL(SUM(IFNULL(NULLIF(si.total_cost, 0), si.amount * IFNULL(si.unit_cost, 0))), 0) as cogs
              FROM sale_items si JOIN sales s ON s.id = si.sale_id JOIN products p ON p.id = si.product_id
              WHERE s.status = 'completed' AND date(s.created_timestamp) BETWEEN :f AND :t AND $bSql AND $deptSql";
    $st = $db->prepare($sqlTY);
    $st->execute([':f' => $from, ':t' => $to]);
    $rowTY = $st->fetch(PDO::FETCH_ASSOC);
    
    // LY query
    $stLY = $db->prepare($sqlTY);
    $stLY->execute([':f' => $lyFrom, ':t' => $lyTo]);
    $rowLY = $stLY->fetch(PDO::FETCH_ASSOC);

    // PP query (Previous period)
    $stPP = $db->prepare($sqlTY);
    $stPP->execute([':f' => $ppFrom, ':t' => $ppTo]);
    $rowPP = $stPP->fetch(PDO::FETCH_ASSOC);

    $netTY = round(floatval($rowTY['net']), 2);
    $netLY = round(floatval($rowLY['net']), 2);
    $netPP = round(floatval($rowPP['net']), 2);
    $cogsTY = round(floatval($rowTY['cogs']), 2);

    $totalTY += $netTY;
    $totalLY += $netLY;
    $totalPP += $netPP;

    $deltaLY = ($netLY > 0) ? round((($netTY - $netLY) / $netLY) * 100, 1) : null;
    $deltaPP = ($netPP > 0) ? round((($netTY - $netPP) / $netPP) * 100, 1) : null;

    $results[$key] = [
        'key' => $key,
        'name' => $name,
        'color' => $color,
        'ty_net' => $netTY,
        'ly_net' => $netLY,
        'pp_net' => $netPP,
        'units' => round(floatval($rowTY['units']), 2),
        'tickets' => intval($rowTY['tickets']),
        'cogs' => $cogsTY,
        'margin' => round($netTY - $cogsTY, 2),
        'margin_pct' => ($netTY > 0 && $cogsTY <= $netTY * 3) ? round((($netTY - $cogsTY) / $netTY) * 100, 1) : null,
        'delta_ly' => $deltaLY,
        'delta_pp' => $deltaPP
    ];
}

echo "TOTAL TY: $totalTY | TOTAL LY: $totalLY | TOTAL PP: $totalPP\n";
foreach ($results as $r) {
    if ($r['ty_net'] > 0 || $r['ly_net'] > 0) {
        $share = $totalTY > 0 ? round(($r['ty_net'] / $totalTY) * 100, 1) : 0;
        echo sprintf("%-25s | TY: $%10.2f | LY: $%10.2f (%s) | PP: $%10.2f (%s) | Share: %5.1f%%\n",
            $r['name'], $r['ty_net'], $r['ly_net'], $r['delta_ly'] !== null ? $r['delta_ly'].'%' : 'N/D',
            $r['pp_net'], $r['delta_pp'] !== null ? $r['delta_pp'].'%' : 'N/D', $share);
    }
}
