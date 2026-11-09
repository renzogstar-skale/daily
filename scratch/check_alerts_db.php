<?php
require __DIR__ . '/../packages/autoload.php';
define('GROCY_DATAPATH', __DIR__ . '/../data');
require __DIR__ . '/../app.php';

$db = \Grocy\Services\DatabaseService::GetInstance()->GetDbConnectionRaw();
echo "Indexes on stock_log:\n";
foreach ($db->query("PRAGMA index_list(stock_log)") as $idx) {
    echo " - " . $idx['name'] . "\n";
}
echo "Total stock_log rows: " . $db->query("SELECT COUNT(*) FROM stock_log")->fetchColumn() . "\n";
