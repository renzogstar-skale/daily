<?php
$db = new PDO('sqlite:data/grocy_es.db');
$items = $db->query("SELECT * FROM procurement_order_items WHERE order_id IN (1, 2, 3)")->fetchAll(PDO::FETCH_ASSOC);
print_r($items);

