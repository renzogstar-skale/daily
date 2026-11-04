<?php
$db = new PDO('sqlite:data/grocy_es.db');
$stmt = $db->query("SELECT id, product_id, imei1, serial_number, state, branch_id, location_id, zone, custodian_user_id FROM device_units WHERE product_id = 175");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

