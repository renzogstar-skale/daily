<?php
define('GROCY_DATAPATH', __DIR__ . '/../data');
require __DIR__ . '/../app.php';
$db = \Grocy\Services\DatabaseService::GetInstance()->GetDbConnectionRaw();
echo "LOCATIONS:\n";
print_r($db->query("SELECT id, name, branch_id, is_freezer FROM locations LIMIT 20")->fetchAll(PDO::FETCH_ASSOC));
echo "LOCATIONS COUNT: " . $db->query("SELECT COUNT(*) FROM locations")->fetchColumn() . "\n";
