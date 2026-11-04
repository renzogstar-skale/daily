<?php
$db = new PDO('sqlite:data/grocy_es.db');
$stmt = $db->query("SELECT id, product_id, imei1, imei2, serial_number, status, branch_id, location_id, custody_user_id FROM device_units LIMIT 10");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

