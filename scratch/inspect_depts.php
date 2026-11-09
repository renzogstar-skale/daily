<?php
$db = new PDO('sqlite:data/grocy_es.db');
$rows = $db->query("SELECT department, COUNT(*) as c FROM products GROUP BY department")->fetchAll(PDO::FETCH_ASSOC);
echo "=== PRODUCTS BY DEPARTMENT ===\n";
print_r($rows);

$deptsInSales = $db->query("SELECT p.department, COUNT(*) as items, SUM(si.subtotal) as total_sales FROM sale_items si JOIN sales s ON s.id = si.sale_id JOIN products p ON p.id = si.product_id WHERE s.status = 'completed' GROUP BY p.department")->fetchAll(PDO::FETCH_ASSOC);
echo "=== SALES BY DEPARTMENT ===\n";
print_r($deptsInSales);

$minMax = $db->query("SELECT MIN(created_timestamp), MAX(created_timestamp) FROM sales WHERE status = 'completed'")->fetch(PDO::FETCH_ASSOC);
echo "=== SALES DATES ===\n";
print_r($minMax);

