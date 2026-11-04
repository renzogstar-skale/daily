<?php
$db = new PDO('sqlite:data/grocy_es.db');
$stmt = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND (name LIKE '%device%' OR name LIKE '%sensitive%' OR name LIKE '%loss%' OR name LIKE '%imei%')");
print_r($stmt->fetchAll(PDO::FETCH_COLUMN));

