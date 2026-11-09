<?php
define('GROCY_DATAPATH', __DIR__ . '/../data');
require __DIR__ . '/../packages/autoload.php';
require __DIR__ . '/../app.php';

$db = \Grocy\Services\DatabaseService::GetInstance()->GetDbConnectionRaw();
$st = $db->query("SELECT substr(created_timestamp, 1, 7) as ym, count(*) as cnt, sum(total_cost) as total FROM waste_records GROUP BY ym ORDER BY ym");
echo json_encode($st->fetchAll(PDO::FETCH_ASSOC), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

