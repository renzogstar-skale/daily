<?php
define('GROCY_DATAPATH', __DIR__ . '/../data');
require __DIR__ . '/../app.php';
$db = \Grocy\Services\DatabaseService::GetInstance()->GetDbConnectionRaw();
$tables = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name LIKE 'employee%'")->fetchAll(PDO::FETCH_COLUMN);
echo "EMPLOYEE TABLES:\n";
print_r($tables);
