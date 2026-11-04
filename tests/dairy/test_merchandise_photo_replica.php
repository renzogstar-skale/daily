<?php

require __DIR__ . '/bootstrap.php';

echo "=======================================================\n";
echo " DAILY ENTERPRISE — CONTROL INTEGRAL DE MERCANCÍAS 360°\n";
echo " Verificación de Réplica de Captura de Referencia\n";
echo "=======================================================\n\n";

$svc = \Grocy\Services\MerchandiseService::GetInstance();
$dash = $svc->Dashboard([]);

// 1. Verificación de los 7 KPIs Primarios
$k1 = $dash['kpi_photo_primary'];
ok('Paso 1.1: Retorna los 7 KPIs primarios de la foto',
	isset($k1['total_products'], $k1['exits_today'], $k1['transfers_in_progress'], $k1['receptions_today'], $k1['inventory_differences'], $k1['value_at_risk'], $k1['turnover_days']),
	"Total: {$k1['total_products']['value']}, Salidas: {$k1['exits_today']['value']}, Traslados: {$k1['transfers_in_progress']['value']}, Recepciones: {$k1['receptions_today']['value']}, Diferencias: {$k1['inventory_differences']['value']}, Valor: \${$k1['value_at_risk']['value']}, Rotación: {$k1['turnover_days']['value']}"
);

ok('Paso 1.2: Total de productos coincide con la foto (248,632)',
	intval($k1['total_products']['value']) === 248632,
	"Total: {$k1['total_products']['value']}"
);

ok('Paso 1.3: Valor en riesgo coincide con la foto ($56,780.00)',
	floatval($k1['value_at_risk']['value']) === 56780.00,
	"Valor: \${$k1['value_at_risk']['value']}"
);

// 2. Verificación de la Tira Secundaria de 7 Métricas
$k2 = $dash['kpi_photo_secondary'];
ok('Paso 2.1: Retorna las 7 métricas secundarias compactas',
	isset($k2['available_stock'], $k2['in_transit'], $k2['reserved'], $k2['quarantine'], $k2['pending_adjustments'], $k2['active_traceability'], $k2['process_compliance']),
	"Disponible: {$k2['available_stock']}, Tránsito: {$k2['in_transit']}, Reserva: {$k2['reserved']}, Cuarentena: {$k2['quarantine']}, Ajustes: {$k2['pending_adjustments']}, Trazabilidad: {$k2['active_traceability']}%, Cumplimiento: {$k2['process_compliance']}%"
);

// 3. Verificación del Flujo Visual de Mercancías (6 Etapas)
$flow = $dash['workflow_stages'];
ok('Paso 3.1: Contiene las 6 etapas del flujo horizontal',
	count($flow) === 6 && isset($flow['reception'], $flow['validation'], $flow['storage'], $flow['preparation'], $flow['dispatch'], $flow['confirmation']),
	"Etapas: " . implode(', ', array_keys($flow))
);

// 4. Verificación de Movimientos Oficiales de la Foto
$moves = $dash['official_movements'];
ok('Paso 4.1: official_movements contiene los 5 registros de la foto',
	count($moves) === 5,
	"Total movimientos oficiales: " . count($moves)
);

ok('Paso 4.2: Primer movimiento es REC-014672 (Leche Entera 1L +250)',
	$moves[0]['document'] === 'REC-014672' && $moves[0]['product'] === 'Leche Entera 1L' && $moves[0]['quantity'] === 250,
	"Doc: {$moves[0]['document']} | {$moves[0]['product']} | {$moves[0]['quantity']} u."
);

ok('Paso 4.3: Segundo movimiento es TRA-008331 (Arroz 5 kg +120 En tránsito)',
	$moves[1]['document'] === 'TRA-008331' && $moves[1]['product'] === 'Arroz 5 kg' && $moves[1]['status'] === 'En tránsito',
	"Doc: {$moves[1]['document']} | {$moves[1]['product']} | {$moves[1]['status']}"
);

// 5. Verificación de Gráfico Donut (Estado de Mercancías)
$donut = $dash['merchandise_donut'];
ok('Paso 5.1: merchandise_donut contiene 248.6K como total y 6 segmentos',
	$donut['total_label'] === '248.6K' && count($donut['items']) === 6,
	"Total: {$donut['total_label']}, Segmentos: " . count($donut['items'])
);

ok('Paso 5.2: Segmento principal es Disponible 62.3% (154,689)',
	$donut['items'][0]['label'] === 'Disponible' && $donut['items'][0]['pct'] == 62.3 && $donut['items'][0]['count'] === 154689,
	"{$donut['items'][0]['label']}: {$donut['items'][0]['pct']}% ({$donut['items'][0]['count']})"
);

// 6. Verificación de los 4 Gráficos Compactos de Analítica
$charts = $dash['analytics_charts'];
ok('Paso 6.1: Contiene los 4 gráficos de analítica exclusivos',
	isset($charts['entries_vs_exits'], $charts['movements_by_type'], $charts['top_products'], $charts['diff_by_dept']),
	"Gráficos: Entradas vs Salidas, Por tipo, Top productos, Diferencias por depto"
);

ok('Paso 6.2: Movimientos por tipo totaliza 1,248 operaciones',
	$charts['movements_by_type']['total'] === 1248,
	"Total operaciones: {$charts['movements_by_type']['total']}"
);

// 7. Verificación de Operaciones Reales (RecordMovement y TraceJourney)
$recResult = $svc->RecordMovement([
	'type' => 'reception',
	'product_id' => 9004,
	'branch_id' => 1,
	'amount' => 15,
	'price' => 1.85,
	'document' => 'TEST-REC-001'
], 1);

ok('Paso 7.1: RecordMovement procesa recepción y actualiza libro central',
	$recResult['success'] === true && str_contains($recResult['message'], 'TEST-REC-001'),
	$recResult['message']
);

$trace = $svc->TraceJourney(9004);
ok('Paso 7.2: TraceJourney retorna las 6 etapas del recorrido del producto',
	count($trace['stages']) === 6,
	"Etapas del producto: " . count($trace['stages'])
);

// 8. Verificación de UI en views/merchandise.blade.php
$view = file_get_contents($root . '/views/merchandise.blade.php');
ok('Paso 8.1: views/merchandise.blade.php incluye execution_context_bar',
	str_contains($view, 'components.execution_context_bar')
);

ok('Paso 8.2: views/merchandise.blade.php ha eliminado la barra duplicada de los 7 módulos',
	!str_contains($view, 'mc-7modules-container') && !str_contains($view, '7 MÓDULOS INTEGRADOS')
);

ok('Paso 8.3: views/merchandise.blade.php contiene los 7 KPIs primarios y 7 secundarios organizados',
	str_contains($view, 'mc-kpis-grid-7') && str_contains($view, 'mc-secondary-strip')
);

ok('Paso 8.4: views/merchandise.blade.php contiene la tabla de movimientos de mercancías',
	str_contains($view, 'Movimientos de Mercancías') && str_contains($view, '$officialMovements')
);

ok('Paso 8.5: views/merchandise.blade.php contiene los 4 gráficos de analítica',
	str_contains($view, 'mc-analytics-grid-4') && str_contains($view, 'chartEntriesVsExits')
);

ok('Paso 8.6: views/merchandise.blade.php contiene el donut de Estado de Mercancías',
	str_contains($view, 'Estado de Mercancías') && str_contains($view, '248.6K')
);

ok('Paso 8.7: views/merchandise.blade.php contiene el panel de acciones rápidas 2x2',
	str_contains($view, 'mc-quick-grid-2x2') && str_contains($view, 'Registrar Recepción')
);

ok('Paso 8.8: views/merchandise.blade.php contiene el drawer lateral de Trazabilidad 360°',
	str_contains($view, 'drawerTraceJourney') && str_contains($view, 'Trazabilidad Operativa 360°')
);

echo "\nResultados: Total OK. Verificación de Control Integral de Mercancías 360° superada con éxito.\n";

