<?php
$db = new PDO('sqlite:data/grocy_es.db');
$stmt = $db->query("SELECT id, name, active FROM branches");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

