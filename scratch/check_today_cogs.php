<?php
$db = new PDO('sqlite:data/grocy_es.db');
$sales = $db->query("SELECT id, sale_number, subtotal, tax_amount, total_amount, total_cogs, gross_margin FROM sales WHERE date(created_timestamp) = '2026-11-04' ORDER BY id DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
echo "Recent sales today:\n";
print_r($sales);

$items = $db->query("SELECT si.id, si.sale_id, si.product_id, p.name, si.amount, si.unit_price, si.subtotal, si.unit_cost, si.total_cost FROM sale_items si JOIN products p ON p.id = si.product_id WHERE date(si.created_timestamp) = '2026-11-04' ORDER BY si.id DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
echo "Recent sale items today:\n";
print_r($items);

