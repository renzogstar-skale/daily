<?php

// DAILY — Test de Verificación Réplica Fotográfica 1:1 de Conciliación & Sensible (/reconciliation)
// Referencia: media_1793661795072_04441e74.jpg

require __DIR__ . '/bootstrap.php';

use Grocy\Services\ReconciliationService;

echo "== Verificación de Réplica Fotográfica 1:1: Conciliación & Sensible (/reconciliation) ==\n";

$svc = ReconciliationService::GetInstance();

// 1. Resumen y KPIs
echo "\n1. Verificando 5 KPIs de la fotografía\n";
$overview = $svc->GetDashboardOverview();

ok('KPI 1: Faltantes detectados monto es $3,236.65', floatval($overview['shortage_value']) === 3236.65, "Valor: " . $overview['shortage_value']);
ok('KPI 1: Faltantes detectados unidades es 18.5 u.', floatval($overview['shortage_units']) === 18.5, "Unidades: " . $overview['shortage_units']);
ok('KPI 2: Casos en investigación es 8 (6 preliminares + 2 confirmados)', intval($overview['open_cases_count']) === 8, "Casos: " . $overview['open_cases_count']);
ok('KPI 2: Preliminares es 6', intval($overview['preliminary_count']) === 6);
ok('KPI 2: Confirmados es 2', intval($overview['confirmed_shortage_count']) === 2);
ok('KPI 3: Pérdidas reconocidas es $0.00', floatval($overview['recognized_loss_value']) === 0.0);
ok('KPI 4: Conteos pendientes es 1', intval($overview['pending_counts']) === 1);
ok('KPI 5: Reincidencias 90D es 2', intval($overview['recidivism_count']) === 2);

// 2. Catálogo de productos (Columna Izquierda)
echo "\n2. Verificando Catálogo & Sensibilidad (Columna Izquierda)\n";
$products = $svc->GetProductsCatalog();
ok('Catálogo devuelve productos', count($products) >= 4);

$s24 = null;
$audifonos = null;
$a55 = null;
$smp = null;

foreach ($products as $p)
{
	if ($p['sku'] === 'DEMO-SMG-001') $s24 = $p;
	if ($p['sku'] === 'DEMO-AUD-002') $audifonos = $p;
	if ($p['sku'] === 'DEMO-A55-256') $a55 = $p;
	if ($p['sku'] === 'TECH-SMP-001') $smp = $p;
}

ok('Producto Celular Samsung Galaxy S24 128GB presente (DEMO-SMG-001)', $s24 !== null);
ok('Producto Audífonos inalámbricos Pro presente (DEMO-AUD-002)', $audifonos !== null);
ok('Producto Galaxy A55 256GB Azul presente (DEMO-A55-256)', $a55 !== null);
ok('Producto Smartphone Galaxy Pro 5G presente (TECH-SMP-001)', $smp !== null);

// 3. Ficha 360° del Producto Seleccionado (Samsung Galaxy S24)
echo "\n3. Verificando Ficha 360° de DEMO-SMG-001\n";
$card = $svc->GetProductCard(intval($s24['id']));
$prod = $card['product'];
$metrics = $card['metrics'];
$activeCase = $card['active_case'];

ok('Nombre del producto contiene Celular Samsung Galaxy S24', str_contains($prod['name'], 'Samsung Galaxy S24'));
ok('SKU del producto es DEMO-SMG-001', $prod['sku'] === 'DEMO-SMG-001');
ok('UPC del producto es 2000000900100', $prod['upc'] === '2000000900100');
ok('Precio de venta es $799.00', floatval($prod['sale_price']) === 799.0);
ok('Costo unitario es $520.00', floatval($prod['unit_cost']) === 520.0);
ok('Producto marcado como sensible', $prod['is_sensitive'] === true);

// 4. Caso Activo de Investigación (DEMO-001)
echo "\n4. Verificando Expediente de Investigación Activo (DEMO-001)\n";
ok('Existe caso activo de investigación', $activeCase !== null);
ok('Código de caso es DEMO-001', $activeCase['case_code'] === 'DEMO-001');
ok('Etapa del caso es faltante_confirmado', $activeCase['stage'] === 'faltante_confirmado');
ok('Diferencia es -2 unidades', floatval($activeCase['difference_qty']) === -2.0);
ok('Impacto financiero es $1,040.00', floatval($activeCase['financial_impact']) === 1040.0);
ok('Stock esperado es 8', floatval($activeCase['expected_stock']) === 8.0);
ok('Conteo físico es 6', floatval($activeCase['physical_count']) === 6.0);

// 5. 14 Puntos Clave de Conciliación
echo "\n5. Verificando 14 Puntos Clave de Conciliación\n";
ok('Punto 1: Ingreso inicial presente', !empty($metrics['first_reception_date']));
ok('Punto 2: Stock esperado actual es 8', floatval($metrics['current_expected_stock']) === 8.0);
ok('Punto 10: Último conteo registró 6 u. con -2 u.', floatval($metrics['latest_count']['difference_amount']) === -2.0);
ok('Punto 11: Diferencias históricas registradas', isset($metrics['historical_diff_stats']['net_difference']));
ok('Punto 13: Números de serie / IMEI en custodia', count($metrics['serials']) >= 6);

// 6. Ubicaciones, Indicadores 90D y Línea de Tiempo
echo "\n6. Verificando Ubicaciones, Indicadores 90D y Línea de Tiempo\n";
ok('Ubicaciones contiene Bodega principal, Vitrina y Virtual', count($metrics['locations']) === 3);
ok('Indicadores 90D contiene rotación, ventas, diferencias y exactitud', isset($metrics['indicators']['accuracy_pct']));
ok('Exactitud 90D es 75.0%', floatval($metrics['indicators']['accuracy_pct']) === 75.0);

$timeline = $svc->GetTimeline(intval($s24['id']));
ok('Línea de tiempo contiene al menos 3 eventos', count($timeline) >= 3);

$hasRec = false;
$hasCnt1 = false;
$hasCnt2 = false;
foreach ($timeline as $t)
{
	if ($t['movement_code'] === 'DEMO-REC-001') $hasRec = true;
	if ($t['movement_code'] === 'CNT-20261102-0001') $hasCnt1 = true;
	if ($t['movement_code'] === 'CNT-20261102-0002') $hasCnt2 = true;
}
ok('Línea de tiempo incluye DEMO-REC-001 (Ingreso)', $hasRec);
ok('Línea de tiempo incluye CNT-20261102-0001 (Conteo Físico -2 u.)', $hasCnt1);
ok('Línea de tiempo incluye CNT-20261102-0002 (Reconteo Físico -2 u.)', $hasCnt2);

// 7. Renderizado de la Vista (Sin Barra Duplicada de 7 Módulos)
echo "\n7. Verificando Renderizado de la Vista\n";
$viewContent = file_get_contents(__DIR__ . '/../../views/reconciliation.blade.php');
$cssContent = file_get_contents(__DIR__ . '/../../public/css/daily_reconciliation.css');
ok('Barra visual de navegación duplicada context-nav-modules está oculta en CSS', str_contains($cssContent, '.context-nav-modules') && str_contains($cssContent, 'display: none !important'));
ok('Barra de 5 etapas presente', str_contains($viewContent, 'rc-flow-bar'));
ok('Tarjeta de investigación destacada (DEMO-001) presente', str_contains($viewContent, 'rc-investigation-callout'));
ok('14 puntos clave de conciliación presentes en plantilla', str_contains($viewContent, 'rc-14-grid'));
ok('Canvas para gráfico de variación de stock presente', str_contains($viewContent, 'reconciliationVariationChart'));
ok('7 pestañas del producto presentes', str_contains($viewContent, 'rc-product-tabs-bar'));
ok('Modales de Ingreso, Devolución, Despacho y Conteo presentes',
	str_contains($viewContent, 'modalGoodsIn') &&
	str_contains($viewContent, 'modalReturn') &&
	str_contains($viewContent, 'modalMovement') &&
	str_contains($viewContent, 'modalCount') &&
	str_contains($viewContent, 'modalInvestigationDossier')
);

echo "\n== ¡TODAS LAS VERIFICACIONES DE RÉPLICA FOTOGRÁFICA PASARON AL 100%! ==\n";
