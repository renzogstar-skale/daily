<?php
$db = new PDO('sqlite:data/grocy_es.db');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$orders = $db->query("SELECT id, order_code, supplier_id, branch_id, total_estimated_cost, status FROM procurement_orders LIMIT 15")->fetchAll(PDO::FETCH_ASSOC);
echo "Sample procurement orders:\n";
print_r($orders);

$suppliers = $db->query("SELECT id, name FROM shopping_locations LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
echo "Sample suppliers:\n";
print_r($suppliers);

