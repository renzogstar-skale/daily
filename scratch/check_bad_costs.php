<?php
$db = new PDO('sqlite:data/grocy_es.db');
$stmt = $db->query("SELECT si.product_id, p.name, si.unit_price, si.unit_cost FROM sale_items si JOIN products p ON p.id = si.product_id WHERE si.unit_cost > si.unit_price GROUP BY si.product_id");
$badCosts = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "Products with unit_cost > unit_price: " . count($badCosts) . "\n";
print_r($badCosts);

