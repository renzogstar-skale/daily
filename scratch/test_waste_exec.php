<?php
define('GROCY_DATAPATH', __DIR__ . '/../data');
require __DIR__ . '/../packages/autoload.php';
require __DIR__ . '/../app.php';

$s = \Grocy\Services\WasteExecutiveControlCenterService::GetInstance();
$d = $s->GetExecutiveData(null, '2026-11-04', null, null);
echo "=== EVOLUTION ===\n";
echo json_encode($d['evolution'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
echo "=== DONUT ===\n";
echo json_encode($d['donut_types'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
echo "=== DEPARTMENTS BAR ===\n";
echo json_encode($d['departments_bar'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
