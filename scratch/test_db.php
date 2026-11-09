<?php
define('GROCY_DATAPATH', dirname(__DIR__) . '/data');
require dirname(__DIR__) . '/app.php';
$db = \Grocy\Services\DatabaseService::GetInstance()->GetDbConnectionRaw();
echo "Products count: " . $db->query("SELECT COUNT(*) FROM products")->fetchColumn() . "\n";
echo "Branches count: " . $db->query("SELECT COUNT(*) FROM branches")->fetchColumn() . "\n";
echo "Sale items count: " . $db->query("SELECT COUNT(*) FROM sale_items")->fetchColumn() . "\n";

