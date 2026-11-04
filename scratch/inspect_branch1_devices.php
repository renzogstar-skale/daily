<?php
$db = new PDO('sqlite:data/grocy_es.db');
$stmt = $db->query("SELECT id, product_id, imei1, serial_number, state, branch_id, location_id, zone FROM device_units WHERE branch_id = 1 LIMIT 15");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

