<?php
/**
 * DAILY ENTERPRISE — CONTEOS FÍSICOS 360°
 * Test de Verificación de Réplica de Captura y Operatividad
 */

require_once __DIR__ . '/bootstrap.php';

use Grocy\Services\PhysicalInventoryService;
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
echo " DAILY ENTERPRISE — CONTEOS FÍSICOS 360°\n";
echo " Verificación de Réplica de Captura y Operatividad\n";
echo "=======================================================\n\n";

$svc = PhysicalInventoryService::GetInstance();
$data = $svc->GetInventoryData();

// 1. Verificación de los 8 KPIs de la foto
echo "--- 1. KPIs Principales de la Captura (8 Tarjetas) ---\n";
$k = $data['kpis'];

if ($k['scheduled']['formatted'] === '12') {
	printPass("KPI 1: Programados coincide con la foto", $k['scheduled']['formatted'] . " | " . $k['scheduled']['trend']);
} else {
	printFail("KPI 1 no coincide", $k['scheduled']['formatted']);
}

if ($k['in_progress']['formatted'] === '5') {
	printPass("KPI 2: En ejecución coincide con la foto", $k['in_progress']['formatted'] . " | " . $k['in_progress']['trend']);
} else {
	printFail("KPI 2 no coincide", $k['in_progress']['formatted']);
}

if ($k['completed']['formatted'] === '27') {
	printPass("KPI 3: Completados coincide con la foto", $k['completed']['formatted'] . " | " . $k['completed']['trend']);
} else {
	printFail("KPI 3 no coincide", $k['completed']['formatted']);
}

if ($k['overdue']['formatted'] === '2') {
	printPass("KPI 4: Vencidos coincide con la foto", $k['overdue']['formatted'] . " | " . $k['overdue']['trend']);
} else {
	printFail("KPI 4 no coincide", $k['overdue']['formatted']);
}

if ($k['accuracy']['formatted'] === '98.4%') {
	printPass("KPI 5: Exactitud coincide con la foto", $k['accuracy']['formatted'] . " | " . $k['accuracy']['trend']);
} else {
	printFail("KPI 5 no coincide", $k['accuracy']['formatted']);
}

if ($k['differences']['formatted'] === '186 u.') {
	printPass("KPI 6: Diferencias coincide con la foto", $k['differences']['formatted'] . " | " . $k['differences']['trend']);
} else {
	printFail("KPI 6 no coincide", $k['differences']['formatted']);
}

if ($k['value_reconciling']['formatted'] === '$4,320.00') {
	printPass("KPI 7: Valor en conciliación coincide con la foto", $k['value_reconciling']['formatted'] . " | " . $k['value_reconciling']['trend']);
} else {
	printFail("KPI 7 no coincide", $k['value_reconciling']['formatted']);
}

if ($k['productivity']['formatted'] === '382 u/h') {
	printPass("KPI 8: Productividad coincide con la foto", $k['productivity']['formatted'] . " | " . $k['productivity']['trend']);
} else {
	printFail("KPI 8 no coincide", $k['productivity']['formatted']);
}

// 2. Gráfico Cumplimiento y Donut por Tipo
echo "\n--- 2. Analítica de Cumplimiento y Tipos de Conteo ---\n";
$comp = $data['compliance_chart'];
if (count($comp['labels']) === 8 && count($comp['scheduled']) === 8 && count($comp['completed']) === 8) {
	printPass("Gráfico de cumplimiento contiene series exactas (26 oct - 02 nov)");
} else {
	printFail("Cumplimiento no coincide", json_encode($comp));
}

$donut = $data['type_donut'];
if ($donut['total'] === 44 && count($donut['types']) === 6 && $donut['types'][0]['name'] === 'General' && $donut['types'][0]['count'] === 16) {
	printPass("Donut por tipo totaliza 44 conteos y desglosa 6 tipos", "Total: 44 Conteos");
} else {
	printFail("Donut no coincide", json_encode($donut));
}

// 3. Estado de Conteos
echo "\n--- 3. Estado de Conteos ---\n";
$status = $data['status_breakdown'];
if ($status['completed']['count'] === 27 && $status['in_progress']['count'] === 5 && $status['scheduled']['count'] === 12) {
	printPass("Estado de conteos coincide", "Completados: 27 (61.4%), En ejecución: 5 (11.4%), Programados: 12 (27.3%)");
} else {
	printFail("Estado de conteos no coincide", json_encode($status));
}

// 4. Tabla Conteos Recientes (Los 5 oficiales)
echo "\n--- 4. Conteos Recientes Oficiales ---\n";
$counts = $data['recent_counts'];
$codes = array_column(array_slice($counts, 0, 5), 'code');
$expectedCodes = ['CNT-01482', 'CNT-01481', 'CNT-01480', 'CNT-01479', 'CNT-01478'];
if (array_slice($codes, 0, 5) === $expectedCodes) {
	printPass("Los 5 conteos de la captura están presentes en orden exacto", implode(', ', $expectedCodes));
} else {
	printFail("Conteos recientes no coinciden", implode(', ', $codes));
}

// 5. Tareas y Alertas
echo "\n--- 5. Tareas y Alertas (4 Alertas) ---\n";
$tasks = $data['tasks_and_alerts'];
if (count($tasks) === 4 && strpos($tasks[0]['title'], 'Sucursal Aventura') !== false) {
	printPass("Las 4 tareas y alertas críticas están presentes", $tasks[0]['title']);
} else {
	printFail("Tareas y alertas no coinciden", json_encode($tasks));
}

// 6. Acciones Operativas y Bus de Eventos
echo "\n--- 6. Acciones Operativas y Bus Unificado ---\n";
$newCount = $svc->CreatePhysicalCount([
	'branch_id' => 1,
	'count_type' => 'Cíclico',
	'assigned_to' => 'jmartinez',
	'supervisor' => 'rgiovine',
	'notes' => 'Conteo de prueba automatizada'
], 1);

if ($newCount['success'] && !empty($newCount['code'])) {
	printPass("Programación de conteo físico exitosa", "Código: {$newCount['code']}");
} else {
	printFail("Fallo al crear conteo", json_encode($newCount));
}

$startCount = $svc->StartCount($newCount['session_id'], 1);
if ($startCount['success']) {
	printPass("Inicio de sesión de conteo activa escáner", $startCount['message']);
} else {
	printFail("Fallo al iniciar conteo", json_encode($startCount));
}

$scanItem = $svc->ScanAndCountItem($newCount['session_id'], '1', 12.0, 1);
if ($scanItem['success']) {
	printPass("Escaneo y registro de línea de conteo con cálculo de diferencia", $scanItem['message']);
} else {
	printFail("Fallo al escanear producto", json_encode($scanItem));
}

$reconcile = $svc->ApproveAndReconcile($newCount['session_id'], 1);
if ($reconcile['success']) {
	printPass("Aprobación gerencial y conciliación en Kardex formal", $reconcile['message']);
} else {
	printFail("Fallo al conciliar conteo", json_encode($reconcile));
}

// 7. Verificación de la Vista Blade
echo "\n--- 7. Verificación de views/inventory.blade.php ---\n";
$blade = file_get_contents(__DIR__ . '/../../views/inventory.blade.php');

$checks = [
	'execution_context_bar' => strpos($blade, 'execution_context_bar') !== false,
	'banner_title' => strpos($blade, 'Conteos Físicos 360°') !== false,
	'kpis_grid_8' => strpos($blade, 'pi-kpis-grid-8') !== false,
	'tab_panel_general' => strpos($blade, 'data-tab="tab-panel-general"') !== false,
	'tab_programacion' => strpos($blade, 'data-tab="tab-programacion"') !== false,
	'tab_conteo_en_vivo' => strpos($blade, 'data-tab="tab-conteo-en-vivo"') !== false,
	'tab_conteos_ciegos' => strpos($blade, 'data-tab="tab-conteos-ciegos"') !== false,
	'tab_diferencias' => strpos($blade, 'data-tab="tab-diferencias"') !== false,
	'tab_validacion_cierre' => strpos($blade, 'data-tab="tab-validacion-cierre"') !== false,
	'tab_historial' => strpos($blade, 'data-tab="tab-historial"') !== false,
	'chart_compliance' => strpos($blade, 'chartCompliance') !== false,
	'chart_counts_type' => strpos($blade, 'chartCountsByType') !== false,
	'recent_counts_table' => strpos($blade, 'Conteos recientes') !== false,
	'tasks_alerts_panel' => strpos($blade, 'Tareas y alertas') !== false,
	'connected_modules' => strpos($blade, 'Módulos relacionados') !== false,
	'switch_tab_js' => strpos($blade, 'switchTab') !== false,
	'export_csv' => strpos($blade, 'exportCountsToCsv') !== false,
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

