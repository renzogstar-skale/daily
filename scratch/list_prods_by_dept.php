<?php
define('GROCY_DATAPATH', __DIR__ . '/../data');
require __DIR__ . '/../packages/autoload.php';
require __DIR__ . '/../app.php';

$m = \Grocy\Services\DailyMetricsService::GetInstance();
$db = \Grocy\Services\DatabaseService::GetInstance()->GetDbConnectionRaw();
foreach (\Grocy\Services\DailyMetricsService::DEPARTMENTS as $k => [$name]) {
    $st = $db->query("SELECT p.id, p.name, p.qu_id_stock, p.location_id FROM products p WHERE " . $m->DeptSql([$k]) . " LIMIT 5");
    $prods = $st->fetchAll(PDO::FETCH_ASSOC);
    echo "DEPT: $name ($k)\n";
    foreach ($prods as $p) {
        echo "  [{$p['id']}] {$p['name']} (loc: {$p['location_id']})\n";
    }
}

