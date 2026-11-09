<?php
define('GROCY_DATAPATH', __DIR__ . '/../data');
require __DIR__ . '/../packages/autoload.php';
require __DIR__ . '/../app.php';

$m = \Grocy\Services\DailyMetricsService::GetInstance();
$db = \Grocy\Services\DatabaseService::GetInstance()->GetDbConnectionRaw();
foreach (\Grocy\Services\DailyMetricsService::DEPARTMENTS as $k => [$name]) {
    $s = $m->Sales(null, '2026-11-01', '2026-11-04', [$k]);
    $st = $db->query("SELECT COUNT(*) as cnt, IFNULL(SUM(w.total_cost), 0) as waste_cost FROM waste_records w JOIN products p ON p.id = w.product_id WHERE w.status = 'approved' AND date(w.created_timestamp) BETWEEN '2026-11-01' AND '2026-11-04' AND " . $m->DeptSql([$k]));
    $w = $st->fetch(PDO::FETCH_ASSOC);
    echo sprintf("%-25s | Sales: $%8.2f | Waste: $%8.2f (cnt: %d)\n", $name, $s['net_sales'], floatval($w['waste_cost']), intval($w['cnt']));
}

