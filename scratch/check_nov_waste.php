<?php
define('GROCY_DATAPATH', __DIR__ . '/../data');
require __DIR__ . '/../packages/autoload.php';
require __DIR__ . '/../app.php';

$db = \Grocy\Services\DatabaseService::GetInstance()->GetDbConnectionRaw();
$st = $db->query("SELECT r.akcal_classification, COUNT(w.id) as cnt, SUM(w.total_cost) as total 
                  FROM waste_records w 
                  JOIN waste_reasons r ON r.id = w.waste_reason_id
                  WHERE w.status = 'approved' AND date(w.created_timestamp) BETWEEN '2026-11-01' AND '2026-11-04'
                  GROUP BY r.akcal_classification");
echo json_encode($st->fetchAll(PDO::FETCH_ASSOC), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

