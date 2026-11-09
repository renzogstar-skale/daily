<?php
define('GROCY_DATAPATH', __DIR__ . '/../data');
require __DIR__ . '/../packages/autoload.php';
require __DIR__ . '/../app.php';

$db = \Grocy\Services\DatabaseService::GetInstance()->GetDbConnectionRaw();
$row = $db->query("SELECT * FROM waste_records WHERE id = 724")->fetch(PDO::FETCH_ASSOC);
print_r($row);

