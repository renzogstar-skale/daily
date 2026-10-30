<?php
$db = new PDO("sqlite:data/grocy_es.db");
$stmt = $db->query("SELECT sql FROM sqlite_master WHERE name = 'user_permissions_resolved'");
echo $stmt->fetchColumn() . "\n";

echo "=== USER 1 PERMISSIONS ===\n";
$p1 = $db->query("SELECT * FROM user_permissions_resolved WHERE user_id = 1")->fetchAll(PDO::FETCH_ASSOC);
print_r($p1);

echo "=== USER 2 PERMISSIONS ===\n";
$p2 = $db->query("SELECT * FROM user_permissions_resolved WHERE user_id = 2")->fetchAll(PDO::FETCH_ASSOC);
print_r($p2);

echo "=== USER 3 PERMISSIONS ===\n";
$p3 = $db->query("SELECT * FROM user_permissions_resolved WHERE user_id = 3")->fetchAll(PDO::FETCH_ASSOC);
print_r($p3);
