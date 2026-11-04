<?php
$db = new PDO('sqlite:data/grocy_es.db');
$sample = $db->query("SELECT id, name, row_created_timestamp FROM products WHERE id >= 9000 ORDER BY id LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
print_r($sample);
