<?php
$db = new PDO('sqlite:data/grocy_es.db');
$row = $db->query("SELECT p.department, COUNT(DISTINCT s.product_id) as prods, SUM(s.amount) as total_units, AVG(s.amount) as avg_units, AVG(IFNULL(s.price, plp.price)) as avg_price, SUM(s.amount * IFNULL(s.price, IFNULL(plp.price, 0))) as total_val 
FROM stock s 
JOIN products p ON p.id = s.product_id 
LEFT JOIN cache__products_last_purchased plp ON plp.product_id = s.product_id 
GROUP BY p.department 
ORDER BY total_val DESC")->fetchAll(PDO::FETCH_ASSOC);

print_r($row);
