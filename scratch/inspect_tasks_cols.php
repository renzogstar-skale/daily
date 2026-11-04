<?php
$db = new PDO('sqlite:data/grocy_es.db');
$stmt = $db->query("PRAGMA table_info(sensitive_case_tasks)");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

