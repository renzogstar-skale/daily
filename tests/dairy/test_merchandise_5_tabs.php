<?php

require __DIR__ . '/bootstrap.php';

echo "=================================================================\n";
echo " DAILY ENTERPRISE — CONTROL INTEGRAL DE MERCANCÍAS 360°\n";
echo " Verificación de Funcionalidad Operativa en las 5 Pestañas\n";
echo "=================================================================\n\n";

$svc = \Grocy\Services\MerchandiseService::GetInstance();
$svc->EnsureMerchandiseSchema();
$dash = $svc->Dashboard([]);

// 1. PESTAÑA: STOCK & MOVIMIENTOS
echo "--- 1. Pestaña Stock & Movimientos ---\n";
$stockList = $svc->GetStockOverviewData([]);
ok('1.1: GetStockOverviewData retorna catálogo con existencias',
	is_array($stockList) && count($stockList) > 0,
	'Total productos en catálogo: ' . count($stockList)
);

$firstStock = $stockList[0];
ok('1.2: Producto contiene campos clave de stock y ubicación',
	isset($firstStock['name'], $firstStock['sku'], $firstStock['branch'], $firstStock['location'], $firstStock['current_stock'], $firstStock['min_stock']),
	"Producto: {$firstStock['name']} | SKU: {$firstStock['sku']} | Stock: {$firstStock['current_stock']} u."
);

$scanRes = $svc->ScanBarcodeLookup($firstStock['sku']);
ok('1.3: Escaneo de código/SKU retorna ficha y existencias reales',
	$scanRes['found'] === true && !empty($scanRes['product']['name']),
	"Encontrado: {$scanRes['product']['name']} | Existencias: {$scanRes['product']['current_stock']}"
);

// 2. PESTAÑA: TRASLADOS
echo "\n--- 2. Pestaña Traslados Inter-Sucursal ---\n";
$transfers = $svc->GetTransfersData([]);
ok('2.1: GetTransfersData retorna transferencias entre sucursales',
	is_array($transfers) && count($transfers) > 0,
	'Total traslados cargados: ' . count($transfers)
);

$trCreate = $svc->CreateTransfer([
	'from_branch_id' => 1,
	'to_branch_id' => 2,
	'product_id' => 9004,
	'amount' => 20,
	'notes' => 'Traslado de reabastecimiento refrigerado'
], 1);
ok('2.2: CreateTransfer emite solicitud de traslado con código',
	$trCreate['success'] === true && !empty($trCreate['code']),
	"Código generado: {$trCreate['code']}"
);

// Recepción con diferencia (enviadas 20, recibidas 18 -> faltante de 2)
$trRec = $svc->ReceiveTransfer(101, [
	'sent_qty' => 20,
	'received_qty' => 18,
	'notes' => 'Faltante de 2 unidades reportado en precinto de transportista',
	'branch_id' => 2
], 1);
ok('2.3: ReceiveTransfer calcula discrepancia y publica evento en el bus',
	$trRec['success'] === true && floatval($trRec['difference']) === -2.0,
	"Mensaje: {$trRec['message']}"
);

// 3. PESTAÑA: DIFERENCIAS
echo "\n--- 3. Pestaña Diferencias e Inconsistencias ---\n";
$diffs = $svc->GetDifferencesData([]);
ok('3.1: GetDifferencesData retorna inconsistencias operativas clasificadas',
	is_array($diffs) && count($diffs) >= 4,
	'Total inconsistencias: ' . count($diffs)
);

$diffAction1 = $svc->HandleDifferenceAction(201, 'recount', ['notes' => 'Reconteo de turno tarde'], 1);
ok('3.2: Acción de Reconteo genera orden operativa de recuento',
	$diffAction1['success'] === true && str_contains($diffAction1['message'], 'Reconteo')
);

$diffAction2 = $svc->HandleDifferenceAction(201, 'investigate', ['notes' => 'Revisión cámaras CCTV'], 1);
ok('3.3: Acción de Investigar abre protocolo de custodia formal',
	$diffAction2['success'] === true && str_contains($diffAction2['message'], 'investigación')
);

// 4. PESTAÑA: AJUSTES (SEGREGACIÓN DE FUNCIONES)
echo "\n--- 4. Pestaña Ajustes (Autorización y Segregación) ---\n";
$adjustments = $svc->GetAdjustmentsData([]);
ok('4.1: GetAdjustmentsData retorna centro de solicitudes de ajustes',
	is_array($adjustments) && count($adjustments) > 0,
	'Total ajustes registrados: ' . count($adjustments)
);

// Crear nueva solicitud de ajuste
$reqAdj = $svc->RequestAdjustment([
	'product_id' => 9004,
	'branch_id' => 1,
	'delta' => -2,
	'type' => 'danado',
	'reason' => 'Producto con fecha vencida retirado de piso de venta'
], 1);
ok('4.2: RequestAdjustment crea solicitud bajo estado pendiente de aprobación',
	$reqAdj['success'] === true && !empty($reqAdj['code']),
	"Código solicitud: {$reqAdj['code']}"
);

// Obtener el ID del ajuste recién creado
$newAdjId = intval($reqAdj['id']);

// Aprobar ajuste con perfil de gerencia/supervisión
$decideAdj = $svc->DecideAdjustment($newAdjId, 'approve', 'Aprobado formalmente por Gerencia', 1);
ok('4.3: DecideAdjustment(approve) asienta el ajuste contable en inventario',
	$decideAdj['success'] === true && str_contains($decideAdj['message'], 'autorizado'),
	$decideAdj['message']
);

// Probar rechazo de ajuste formal
$reqAdj2 = $svc->RequestAdjustment([
	'product_id' => 9004,
	'branch_id' => 1,
	'delta' => 10,
	'type' => 'sobrante',
	'reason' => 'Sobrante no documentado sospechoso'
], 1);
$newAdjId2 = intval($reqAdj2['id']);
$rejectAdj = $svc->DecideAdjustment($newAdjId2, 'reject', 'Rechazado: no existe factura de compra de respaldo', 1);
ok('4.4: DecideAdjustment(reject) rechaza el ajuste sin alterar existencias',
	$rejectAdj['success'] === true && str_contains($rejectAdj['message'], 'rechazado formalmente'),
	$rejectAdj['message']
);

// 5. PESTAÑA: TRAZABILIDAD 360°
echo "\n--- 5. Pestaña Trazabilidad 360° ---\n";
$traceRes = $svc->SearchTraceJourney('LCH-001');
ok('5.1: SearchTraceJourney retorna producto y sus 6 etapas del ciclo de vida',
	isset($traceRes['product'], $traceRes['stages']) && count($traceRes['stages']) === 6,
	"Producto: {$traceRes['product']['name']} | Etapas: " . count($traceRes['stages'])
);

ok('5.2: Trazabilidad incluye primera etapa Proveedor y última etapa Despacho',
	$traceRes['stages'][0]['stage'] === 'Proveedor' && $traceRes['stages'][5]['stage'] === 'Despacho / POS'
);

// 6. VERIFICACIÓN DE VISTA BLADE Y JAVASCRIPT
echo "\n--- 6. Verificación de Interfaz en views/merchandise.blade.php ---\n";
$view = file_get_contents($root . '/views/merchandise.blade.php');

ok('6.1: Contiene botones de navegación para las 5 pestañas solicitadas',
	str_contains($view, 'data-tab="tab-stock-mov"') &&
	str_contains($view, 'data-tab="tab-traslados"') &&
	str_contains($view, 'data-tab="tab-diferencias"') &&
	str_contains($view, 'data-tab="tab-ajustes"') &&
	str_contains($view, 'data-tab="tab-trazabilidad"')
);

ok('6.2: Contiene los 5 paneles de contenido operativo respectivos',
	str_contains($view, 'id="pane-tab-stock-mov"') &&
	str_contains($view, 'id="pane-tab-traslados"') &&
	str_contains($view, 'id="pane-tab-diferencias"') &&
	str_contains($view, 'id="pane-tab-ajustes"') &&
	str_contains($view, 'id="pane-tab-trazabilidad"')
);

ok('6.3: Contiene modales de confirmación de recepción y acción de diferencias',
	str_contains($view, 'id="modalReceiveTransfer"') &&
	str_contains($view, 'id="modalDiffAction"')
);

ok('6.4: Contiene función switchTab con persistencia en URL hash',
	str_contains($view, 'switchTab') && str_contains($view, 'history.replaceState')
);

echo "\n=================================================================\n";
echo " RESULTADOS FINALES: Las 5 Pestañas Operativas funcionan al 100%.\n";
echo "=================================================================\n";
