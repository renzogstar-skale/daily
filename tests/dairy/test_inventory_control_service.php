<?php
require_once __DIR__ . '/bootstrap.php';
use Grocy\Services\InventoryControlService;

$svc = InventoryControlService::GetInstance();
$data = $svc->GetControlData();

echo "KPIs count: " . count($data['kpis']) . "\n";
echo "Stock total: " . $data['kpis']['stock_total']['formatted'] . "\n";
echo "Stock fantasma: " . $data['kpis']['stock_phantom']['formatted'] . "\n";
echo "Venta perdida: " . $data['kpis']['lost_sales']['formatted'] . "\n";
echo "Valor en duda: " . $data['kpis']['value_in_doubt']['formatted'] . "\n";
echo "Clase AX: " . $data['kpis']['class_ax']['formatted'] . "\n";
echo "Clase AZ + CZ: " . $data['kpis']['class_az_cz']['formatted'] . "\n";
echo "Phantom items: " . count($data['phantom_stock']) . "\n";
echo "ABC matrix rows: " . count($data['abc_xyz_matrix']) . "\n";
echo "Top value products: " . count($data['top_value_products']) . "\n";
echo "SUCCESS!\n";

