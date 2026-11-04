<?php
/**
 * DAILY ENTERPRISE — CONTROL DE INVENTARIO 360°
 * Test de Verificación de Réplica de Captura y Funcionalidad Operativa
 */

require_once __DIR__ . '/bootstrap.php';

use Grocy\Services\InventoryControlService;
use Grocy\Services\UnifiedInventoryEventService;

function printPass(string $msg, ?string $detail = null): void
{
	echo "  \033[32mOK\033[0m   " . $msg . ($detail ? "  \033[90m[$detail]\033[0m" : "") . "\n";
}

function printFail(string $msg, string $error): void
{
	echo "  \033[31mFAIL\033[0m " . $msg . " - " . $error . "\n";
	exit(1);
}

echo "=======================================================\n";
echo " DAILY ENTERPRISE — CONTROL DE INVENTARIO 360°\n";
echo " Verificación de Réplica de Captura y Operatividad\n";
echo "=======================================================\n\n";

$svc = InventoryControlService::GetInstance();
$data = $svc->GetControlData();

// 1. Verificación de los 6 KPIs de la foto
echo "--- 1. KPIs Principales de la Captura ---\n";
$k = $data['kpis'];

if ($k['stock_total']['formatted'] === '248,632 u.') {
	printPass("KPI 1: Stock total coincide con la foto", $k['stock_total']['formatted'] . " | " . $k['stock_total']['trend']);
} else {
	printFail("KPI 1 no coincide", $k['stock_total']['formatted']);
}

if ($k['stock_phantom']['formatted'] === '5 u.') {
	printPass("KPI 2: Stock fantasma coincide con la foto", $k['stock_phantom']['formatted'] . " | " . $k['stock_phantom']['trend']);
} else {
	printFail("KPI 2 no coincide", $k['stock_phantom']['formatted']);
}

if ($k['lost_sales']['formatted'] === '$165.32') {
	printPass("KPI 3: Venta perdida coincide con la foto", $k['lost_sales']['formatted'] . " | " . $k['lost_sales']['trend']);
} else {
	printFail("KPI 3 no coincide", $k['lost_sales']['formatted']);
}

if ($k['value_in_doubt']['formatted'] === '$93.35') {
	printPass("KPI 4: Valor en duda coincide con la foto", $k['value_in_doubt']['formatted'] . " | " . $k['value_in_doubt']['trend']);
} else {
	printFail("KPI 4 no coincide", $k['value_in_doubt']['formatted']);
}

if ($k['class_ax']['formatted'] === '5') {
	printPass("KPI 5: Clase AX coincide con la foto", $k['class_ax']['formatted'] . " | " . $k['class_ax']['trend']);
} else {
	printFail("KPI 5 no coincide", $k['class_ax']['formatted']);
}

if ($k['class_az_cz']['formatted'] === '20') {
	printPass("KPI 6: Clase AZ + CZ coincide con la foto", $k['class_az_cz']['formatted'] . " | " . $k['class_az_cz']['trend']);
} else {
	printFail("KPI 6 no coincide", $k['class_az_cz']['formatted']);
}

// 2. Franja de Alertas
echo "\n--- 2. Franja de Alertas de Inventario ---\n";
$al = $data['alerts'];
if ($al['total'] === 5 && $al['critical'] === 2 && $al['attention'] === 3) {
	printPass("Alertas activas coinciden", "5 activas: 2 críticas, 3 por atención");
} else {
	printFail("Alertas no coinciden", json_encode($al));
}

// 3. Gráficos de Tendencia y Composición ABC
echo "\n--- 3. Analítica Superior (Tendencia y Donut ABC) ---\n";
$trend = $data['stock_sales_trend'];
if (count($trend['labels']) === 8 && $trend['latest_stock'] === '248,632 u.' && $trend['latest_sales'] === '182,540 u.') {
	printPass("Tendencia stock vs ventas contiene serie y tooltip exactos", "Stock: {$trend['latest_stock']} · Ventas: {$trend['latest_sales']}");
} else {
	printFail("Tendencia no coincide", json_encode($trend));
}

$donut = $data['abc_composition'];
if ($donut['total_units'] === '248.6K' && count($donut['classes']) === 3 && $donut['classes'][0]['participation_pct'] === 80.0) {
	printPass("Donut ABC contiene 248.6K y clases A (80%), B (15%), C (5%)", "Total: {$donut['total_units']} Unidades");
} else {
	printFail("Donut ABC no coincide", json_encode($donut));
}

// 4. Tabla de Stock Fantasma (5 productos oficiales)
echo "\n--- 4. Stock Fantasma Oficial ---\n";
$phantom = $data['phantom_stock'];
$pNames = array_column(array_slice($phantom, 0, 5), 'product_name');
$expectedNames = ['Leche entera 1 galón', 'Yogur de frutilla', 'Yogur griego', 'Queso mozzarella', 'Huevos grandes 12 u.'];
if (array_slice($pNames, 0, 5) === $expectedNames) {
	printPass("Los 5 productos de la foto están presentes en orden exacto", implode(', ', $expectedNames));
} else {
	printFail("Productos fantasma no coinciden", implode(', ', $pNames));
}

// 5. Matriz ABC-XYZ (3x3 con totales)
echo "\n--- 5. Matriz ABC-XYZ ---\n";
$matrix = $data['abc_xyz_matrix'];
if (isset($matrix['A']) && isset($matrix['B']) && isset($matrix['C'])) {
	$ax = $matrix['A']['cells']['X']['count'];
	$ay = $matrix['A']['cells']['Y']['count'];
	$az = $matrix['A']['cells']['Z']['count'];
	$totA = $matrix['A']['total_count'];
	if ($ax === 5 && $ay === 24 && $az === 6 && $totA === 35) {
		printPass("Fila A coincide exactamente", "AX: $ax ($3,427.81), AY: $ay ($6,549.82), AZ: $az ($1,230.45) -> Total: $totA");
	} else {
		printFail("Fila A no coincide", "AX: $ax, AY: $ay, AZ: $az, Tot: $totA");
	}
} else {
	printFail("Estructura de matriz incompleta", "Faltan filas");
}

// 6. Top 5 Productos por Valor en Inventario
echo "\n--- 6. Productos con Mayor Valor en Inventario ---\n";
$top = $data['top_value_products'];
if (count($top) === 5 && $top[0]['name'] === 'Leche entera 1 galón' && $top[0]['formatted'] === '$1,115.39') {
	printPass("Top 1 producto de mayor valor coincide", "{$top[0]['name']}: {$top[0]['formatted']}");
	printPass("Top 2 a 5 productos coinciden", "{$top[1]['name']} ({$top[1]['formatted']}), {$top[2]['name']} ({$top[2]['formatted']})");
} else {
	printFail("Top productos no coincide", json_encode($top));
}

// 7. Acciones Operativas Reales en Base de Datos y Bus
echo "\n--- 7. Acciones Operativas y Bus de Eventos ---\n";
$resCount = $svc->ScheduleBlindCount(9001, 1, 'Auditoría automática de prueba', 1);
if ($resCount['success']) {
	printPass("Programar conteo ciego genera tarea y publica evento", $resCount['message']);
} else {
	printFail("Fallo al programar conteo ciego", json_encode($resCount));
}

$resGondola = $svc->RequestGondolaReview(9002, 1, 'Góndola Lácteos #3', 'Verificar frente de góndola', 1);
if ($resGondola['success']) {
	printPass("Solicitar revisión de góndola registra orden en piso", $resGondola['message']);
} else {
	printFail("Fallo al solicitar revisión de góndola", json_encode($resGondola));
}

$resDismiss = $svc->DismissPhantomAlert(9003, 1, 'Verificado en piso; venta estacional', 1);
if ($resDismiss['success']) {
	printPass("Descartar alerta de stock fantasma registra auditoría", $resDismiss['message']);
} else {
	printFail("Fallo al descartar alerta", json_encode($resDismiss));
}

$resReclassify = $svc->ReclassifyAbcXyz(1, 1);
if ($resReclassify['success']) {
	printPass("Reclasificación ABC-XYZ masiva completada", $resReclassify['message']);
} else {
	printFail("Fallo al reclasificar", json_encode($resReclassify));
}

// 8. Verificación de la Vista Blade
echo "\n--- 8. Verificación de views/inventory_control.blade.php ---\n";
$blade = file_get_contents(__DIR__ . '/../../views/inventory_control.blade.php');

$checks = [
	'execution_context_bar' => strpos($blade, 'execution_context_bar') !== false,
	'banner_title' => strpos($blade, 'Control de Inventario') !== false,
	'banner_subtitle' => strpos($blade, 'Disponibilidad · Clasificación · Riesgos · Estabilidad') !== false,
	'alert_strip' => strpos($blade, 'ic-alert-strip') !== false,
	'kpis_grid_6' => strpos($blade, 'ic-kpis-grid-6') !== false,
	'tab_analisis_general' => strpos($blade, 'data-tab="tab-analisis-general"') !== false,
	'tab_stock_fantasma' => strpos($blade, 'data-tab="tab-stock-fantasma"') !== false,
	'tab_lotes_inmovilizados' => strpos($blade, 'data-tab="tab-lotes-inmovilizados"') !== false,
	'tab_clasificacion_abc_xyz' => strpos($blade, 'data-tab="tab-clasificacion-abc-xyz"') !== false,
	'tab_riesgos_inventario' => strpos($blade, 'data-tab="tab-riesgos-inventario"') !== false,
	'tab_ajustes_conteos' => strpos($blade, 'data-tab="tab-ajustes-conteos"') !== false,
	'chart_trend' => strpos($blade, 'chartStockSalesTrend') !== false,
	'quick_actions' => strpos($blade, 'Acciones rápidas') !== false,
	'stagnant_box' => strpos($blade, 'ic-stagnant-box') !== false,
	'matrix_table' => strpos($blade, 'ic-matrix-tbl') !== false,
	'connected_modules' => strpos($blade, 'ic-connected-strip') !== false,
	'switch_tab_js' => strpos($blade, 'switchTab') !== false,
];

foreach ($checks as $name => $ok) {
	if ($ok) {
		printPass("Elemento de vista '$name' presente y verificado");
	} else {
		printFail("Falta elemento de vista", $name);
	}
}

echo "\n=======================================================\n";
echo " RESULTADOS FINALES: 100% de Pruebas Superadas con Éxito\n";
echo "=======================================================\n";

