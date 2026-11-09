<?php
define('GROCY_DATAPATH', __DIR__ . '/../data');
require __DIR__ . '/../packages/autoload.php';
require __DIR__ . '/../app.php';

$db = \Grocy\Services\DatabaseService::GetInstance()->GetDbConnectionRaw();
$st = $db->query("SELECT w.id, w.product_id, p.name as prod_name, w.total_cost, w.status, w.waste_reason_id, r.name as reason, r.akcal_classification, p.location_id
                  FROM waste_records w 
                  JOIN products p ON p.id = w.product_id 
                  LEFT JOIN waste_reasons r ON r.id = w.waste_reason_id
                  WHERE date(w.created_timestamp) BETWEEN '2026-11-01' AND '2026-11-04'");
echo json_encode($st->fetchAll(PDO::FETCH_ASSOC), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

