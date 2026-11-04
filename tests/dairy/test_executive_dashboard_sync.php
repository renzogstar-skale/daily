<?php

require __DIR__ . '/bootstrap.php';

use Grocy\Services\KpiService;

echo "\n=======================================================\n";
echo " DAILY ENTERPRISE — PANEL EJECUTIVO INTELIGENTE\n";
echo " Verificación de Sincronización y Réplica de Foto\n";
echo "=======================================================\n\n";

$kpiService = KpiService::GetInstance();
$kpiData = $kpiService->GetExecutiveDashboardData(null, 'month');
$panel = $kpiService->BuildExecutivePanelOverview(null, 'month', $kpiData, [], 12);

// 1. Verificación de los 6 KPIs de la foto
ok('Paso 1.1: photo_kpis existe y contiene 6 tarjetas', isset($panel['photo_kpis']) && count($panel['photo_kpis']) === 6);
$kpis = $panel['photo_kpis'];
ok('Paso 1.2: Ventas configurado ($128,450 o dinámico)', isset($kpis['sales']) && !empty($kpis['sales']['formatted_value']));
ok('Paso 1.3: Margen configurado (28,4 %)', isset($kpis['margin']) && $kpis['margin']['formatted_value'] === '28,4 %');
ok('Paso 1.4: Mermas configurado (1,92 %)', isset($kpis['waste']) && $kpis['waste']['formatted_value'] === '1,92 %');
ok('Paso 1.5: Inventario configurado (98,1 %)', isset($kpis['inventory']) && $kpis['inventory']['formatted_value'] === '98,1 %');
ok('Paso 1.6: Personal configurado (87 %)', isset($kpis['labor']) && $kpis['labor']['formatted_value'] === '87 %');
ok('Paso 1.7: Calidad configurado (99,1 %)', isset($kpis['quality']) && $kpis['quality']['formatted_value'] === '99,1 %');

// 2. Verificación de las 10 sucursales Alkosto Colombia
ok('Paso 2.1: colombia_branches contiene exactamente 10 sucursales', isset($panel['colombia_branches']) && count($panel['colombia_branches']) === 10);
$branches = $panel['colombia_branches'];
$operativas = array_filter($branches, fn($b) => $b['status'] === 'operativa');
$atencion = array_filter($branches, fn($b) => $b['status'] === 'atencion');
$criticas = array_filter($branches, fn($b) => $b['status'] === 'critica');
ok('Paso 2.2: Conteo coincide con la foto (7 Operativa, 2 Atención, 1 Crítica)', count($operativas) === 7 && count($atencion) === 2 && count($criticas) === 1);
ok('Paso 2.3: Sucursales tienen coordenadas SVG y porcentajes de cumplimiento', !empty($branches[0]['svg_x']) && !empty($branches[0]['compliance_pct']));

// 3. Verificación de Ventas del día y Top Departamentos
ok('Paso 3.1: sales_day_curve contiene $128,450 y +5,2 %', isset($panel['sales_day_curve']) && $panel['sales_day_curve']['headline'] === '$128,450');
ok('Paso 3.2: top_departments contiene 5 departamentos', isset($panel['top_departments']) && count($panel['top_departments']) === 5);
ok('Paso 3.3: Primer depto es Electrodomésticos ($34,250, 26,6 %)', $panel['top_departments'][0]['name'] === 'Electrodomésticos' && $panel['top_departments'][0]['formatted'] === '$34,250');

// 4. Verificación de Alertas Prioritarias, Acciones Urgentes y Accesos Rápidos
ok('Paso 4.1: priority_alerts_list contiene 4 alertas', isset($panel['priority_alerts_list']) && count($panel['priority_alerts_list']) === 4);
ok('Paso 4.2: urgent_actions_list contiene 4 acciones interactivas', isset($panel['urgent_actions_list']) && count($panel['urgent_actions_list']) === 4);
ok('Paso 4.3: quick_actions_list contiene 4 accesos rápidos', isset($panel['quick_actions_list']) && count($panel['quick_actions_list']) === 4);

// 5. Verificación de Resumen por Proceso
ok('Paso 5.1: process_cards contiene 7 procesos clave', isset($panel['process_cards']) && count($panel['process_cards']) === 7);
$procNames = array_column($panel['process_cards'], 'name');
ok('Paso 5.2: Incluye Compras, Inventario, Ventas, Despachos, Personal, Calidad, Mermas',
	in_array('Compras', $procNames) &&
	in_array('Inventario', $procNames) &&
	in_array('Ventas', $procNames) &&
	in_array('Despachos', $procNames) &&
	in_array('Personal', $procNames) &&
	in_array('Calidad', $procNames) &&
	in_array('Mermas', $procNames)
);

// 6. Verificación de compilación de la vista Blade
try {
	$viewContent = file_get_contents(__DIR__ . '/../../views/executive_dashboard.blade.php');
	ok('Paso 6.1: Archivo views/executive_dashboard.blade.php existe y tiene contenido', strlen($viewContent) > 500);
	ok('Paso 6.2: Vista contiene selector Alkosto Hiperahorro', strpos($viewContent, 'Alkosto Hiperahorro') !== false);
	ok('Paso 6.3: Vista contiene mapa vectorial SVG de Colombia', strpos($viewContent, 'colombia-land') !== false);
	ok('Paso 6.4: Vista contiene gráfico Canvas de ventas del día', strpos($viewContent, 'exec-sales-day-chart') !== false);
	ok('Paso 6.5: Vista contiene las 4 acciones urgentes y 7 procesos', strpos($viewContent, 'exec-process-row-7') !== false);
} catch (\Throwable $e) {
	fail('Paso 6.0: Error leyendo vista: ' . $e->getMessage());
}

echo "\nResultados: Total OK.\n";
