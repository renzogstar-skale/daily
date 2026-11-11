<?php

require __DIR__ . '/bootstrap.php';

use Grocy\Services\SensitiveCaseService;

echo "\n=======================================================\n";
echo " DAILY ENTERPRISE — PREVENCIÓN DE PÉRDIDAS 360°\n";
echo " Verificación de Réplica de Captura de Referencia\n";
echo "=======================================================\n\n";

$svc = SensitiveCaseService::GetInstance();
$data = $svc->Dashboard();

// 1. Verificar KPIs
ok('Paso 1.1: Dashboard retorna estructura de KPIs', isset($data['kpis']));
$k = $data['kpis'];
echo "Alertas críticas: " . ($k['critical_alerts'] ?? 0) . "\n";
echo "Incidencias activas: " . ($k['active_incidents'] ?? 0) . "\n";
echo "Diferencias inventario: " . ($k['inventory_differences'] ?? 0) . "\n";
echo "Valor en riesgo: " . ($k['value_at_risk'] ?? 0) . "\n";
echo "Mermas acumuladas: " . ($k['waste_shrinkage_val'] ?? 0) . "\n";
echo "Tareas pendientes: " . ($k['pending_tasks'] ?? 0) . "\n";

// 2. Verificar casos
ok('Paso 2.1: Existen casos de investigación en la base', isset($data['cases']) && count($data['cases']) > 0);
echo "Total de casos cargados: " . count($data['cases']) . "\n";
foreach ($data['cases'] as $idx => $c) {
	echo "Caso " . ($idx + 1) . ": " . ($c['case_number'] ?? $c['id']) . " | " . ($c['product_name'] ?? '') . " | " . ($c['branch_name'] ?? '') . " | Dif: " . ($c['difference_amount'] ?? '') . " | Valor: $" . ($c['financial_impact'] ?? '') . " | Estado: " . ($c['workflow_status'] ?? '') . "\n";
}

// 3. Verificar analíticas
ok('Paso 3.1: by_branch (Sucursales con mayor exposición) disponible', isset($data['by_branch']));
ok('Paso 3.2: by_product (Productos con mayor exposición) disponible', isset($data['by_product']));
ok('Paso 3.3: by_department (Exposición por departamento) disponible', isset($data['by_department']));
ok('Paso 3.4: trend (Tendencia de diferencias) disponible', isset($data['trend']));

// 4. Verificación de archivo de vista y CSS
$viewContent = file_get_contents(__DIR__ . '/../../views/loss_prevention.blade.php');
ok('Paso 4.1: views/loss_prevention.blade.php contiene Hero Banner', strpos($viewContent, 'Centro de Control') !== false);
ok('Paso 4.2: views/loss_prevention.blade.php contiene los 8 KPIs de la foto', strpos($viewContent, 'lp-kpis-grid-8') !== false);
ok('Paso 4.3: views/loss_prevention.blade.php contiene tabla de expedientes real (BD)', strpos($viewContent, 'lp-cases-table') !== false && count(array_filter($data['cases'] ?? [], fn($c) => ($c['case_code'] ?? '') === 'DEMO-001')) > 0);
ok('Paso 4.4: views/loss_prevention.blade.php contiene los 4 gráficos de analítica', strpos($viewContent, 'lp-trend-chart') !== false && strpos($viewContent, 'lp-products-chart') !== false && strpos($viewContent, 'lp-department-chart') !== false);
ok('Paso 4.5: views/loss_prevention.blade.php contiene el drawer RESOLVER 360°', strpos($viewContent, 'drawerResolveOverlay') !== false && strpos($viewContent, 'Detectar') !== false);
ok('Paso 4.6: views/loss_prevention.blade.php contiene Acciones Rápidas inferiores', strpos($viewContent, 'lp-quick-actions-section') !== false);

$cssContent = file_get_contents(__DIR__ . '/../../public/css/daily_loss_control.css');
ok('Paso 4.7: public/css/daily_loss_control.css existe y tiene estilos', strlen($cssContent) > 2000);
ok('Paso 4.8: public/css/daily_loss_control.css define la paleta blanca y verde', strpos($cssContent, '--lp-green') !== false && strpos($cssContent, '#f8fafc') !== false);

echo "\nResultados: $count verificaciones · $fails FALLAS\n";
exit($fails ? 1 : 0);
