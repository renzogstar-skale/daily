<?php
$pdo = new PDO('sqlite:' . __DIR__ . '/../data/grocy_es.db');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$startDate = date('Y-m-d', strtotime('-29 days'));
$endDate = date('Y-m-d');

$costExpr = 'IFNULL(NULLIF(si.total_cost, 0), si.amount * IFNULL(si.unit_cost, 0))';
$iNet = '(si.subtotal * (1 - IFNULL(s.tax_amount, 0) / CASE WHEN IFNULL(s.total_amount, 0) = 0 THEN 1 ELSE s.total_amount END))';

$t0 = microtime(true);
$sql = "
    SELECT p.id,
           p.name,
           p.sku,
           (SELECT barcode FROM product_barcodes WHERE product_id = p.id LIMIT 1) AS upc,
           IFNULL(pg.name, 'General') AS department,
           SUM(si.amount) AS units_sold,
           ROUND(SUM($iNet), 2) AS net_sales,
           ROUND(SUM($costExpr), 2) AS cogs
    FROM sale_items si
    JOIN sales s ON s.id = si.sale_id
    JOIN products p ON p.id = si.product_id
    LEFT JOIN product_groups pg ON pg.id = p.product_group_id
    WHERE s.status = 'completed' AND date(s.created_timestamp) BETWEEN :f AND :t
    GROUP BY p.id
    ORDER BY net_sales DESC
";
$stmt = $pdo->prepare($sql);
$stmt->execute([':f' => $startDate, ':t' => $endDate]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "Query took: " . round(microtime(true) - $t0, 3) . "s, returned " . count($rows) . " rows\n";
