<?php
$db = new PDO('sqlite:data/grocy_es.db');
$sample = $db->query("SELECT id, name, department, min_stock_amount FROM products WHERE department IN ('Tecnología', 'Electrodomésticos', 'Hogar', 'Alimentos', 'Accesorios', 'Cuidado Personal') LIMIT 15")->fetchAll(PDO::FETCH_ASSOC);
print_r($sample);
