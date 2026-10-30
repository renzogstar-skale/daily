<?php
$db = new PDO("sqlite:data/grocy_es.db");
$stmt = $db->query("SELECT user_id, count(*) as cnt FROM user_permissions GROUP BY user_id");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
