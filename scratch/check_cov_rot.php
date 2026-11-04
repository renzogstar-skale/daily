<?php
$db = new PDO('sqlite:data/grocy_es.db');
$date28 = date('Y-m-d', strtotime('-28 days'));

$res = $db->query("SELECT p.department, SUM(s.amount) as stock_units, SUM(s.amount * s.price) as stock_val
FROM stock s JOIN products p ON p.id = s.product_id
GROUP BY p.department")->fetchAll(PDO::FETCH_ASSOC);

$byDeptStock = [];
foreach ($res as $r) {
    $byDeptStock[$r['department']] = $r;
}

$sales = $db->query("SELECT p.department, SUM(si.amount) as sold_units, SUM(si.total_cost) as cogs
FROM sale_items si JOIN sales s ON s.id = si.sale_id JOIN products p ON p.id = si.product_id
WHERE s.status = 'completed' AND date(s.created_timestamp) >= '$date28'
GROUP BY p.department")->fetchAll(PDO::FETCH_ASSOC);

foreach ($sales as $sl) {
    $dept = $sl['department'];
    $stUnits = $byDeptStock[$dept]['stock_units'] ?? 0;
    $stVal = $byDeptStock[$dept]['stock_val'] ?? 0;
    $daily = $sl['sold_units'] / 28.0;
    $cov = $daily > 0 ? ($stUnits / $daily) : 0;
    $rot = $stVal > 0 ? ($sl['cogs'] / $stVal) : 0;
    echo sprintf("%-25s | Stock: %6.0f uds ($%9.0f) | Sold 28d: %6.0f uds (COGS $%9.0f) | Cov: %5.1f d | Rot: %4.2fx\n",
        $dept, $stUnits, $stVal, $sl['sold_units'], $sl['cogs'], $cov, $rot);
}
