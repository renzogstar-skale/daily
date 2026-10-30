<?php
define('GROCY_DATAPATH', __DIR__ . '/../data');
require __DIR__ . '/../app.php';
$db = \Grocy\Services\DatabaseService::GetInstance()->GetDbConnectionRaw();
echo "GROUPS:\n";
print_r($db->query("SELECT id, name FROM product_groups")->fetchAll(PDO::FETCH_ASSOC));
echo "BRANCHES:\n";
print_r($db->query("SELECT id, name, code FROM branches")->fetchAll(PDO::FETCH_ASSOC));
echo "SALES COUNT: " . $db->query("SELECT COUNT(*) FROM sales")->fetchColumn() . "\n";
echo "DATE RANGE:\n";
print_r($db->query("SELECT MIN(date(created_timestamp)) as min_d, MAX(date(created_timestamp)) as max_d FROM sales")->fetch(PDO::FETCH_ASSOC));
