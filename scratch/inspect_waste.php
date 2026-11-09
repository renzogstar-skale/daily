<?php
define('GROCY_DATAPATH', __DIR__ . '/../data');
require __DIR__ . '/../packages/autoload.php';
require __DIR__ . '/../app.php';

$db = \Grocy\Services\DatabaseService::GetInstance()->GetDbConnectionRaw();
$st = $db->query("SELECT r.id, r.name, r.akcal_classification, COUNT(w.id) as cnt, SUM(w.total_cost) as total 
                  FROM waste_reasons r 
                  LEFT JOIN waste_records w ON w.waste_reason_id = r.id 
                  GROUP BY r.id, r.name, r.akcal_classification");
echo json_encode($st->fetchAll(PDO::FETCH_ASSOC), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

