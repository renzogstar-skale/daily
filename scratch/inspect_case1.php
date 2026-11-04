<?php
$db = new PDO('sqlite:data/grocy_es.db');
$stmt = $db->query('SELECT * FROM sensitive_investigation_cases WHERE id = 1');
$case = $stmt->fetch(PDO::FETCH_ASSOC);
echo "CASE 1:\n";
print_r($case);

echo "\nCUSTODY ITEMS FOR PRODUCT {$case['product_id']}:\n";
$stmt2 = $db->prepare('SELECT id, product_id, imei, serial_number, status, branch_id, location_id, updated_at FROM sensitive_goods_custody WHERE product_id = ?');
$stmt2->execute([$case['product_id']]);
$items = $stmt2->fetchAll(PDO::FETCH_ASSOC);
print_r($items);

