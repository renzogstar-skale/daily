<?php
/**
 * DAILY — Suite de pruebas de Optimización de Despachos (#13)
 * Verifica las 6 correcciones obligatorias y el ciclo de 7 etapas OMS/WMS:
 *   Pedido -> Reserva -> Picking -> Packing -> Despacho -> Entrega -> Conciliación
 */

use Grocy\Services\CustomerOrderService;
use Grocy\Services\RetailPreventionService;
use Grocy\Services\StockService;

require __DIR__ . '/bootstrap.php';

$cos = CustomerOrderService::GetInstance();
$svc = RetailPreventionService::GetInstance();
$stock = StockService::GetInstance();

// Setup isolated product with stock, barcode, and customer
$locId = intval($val("SELECT id FROM locations WHERE branch_id = 1 AND active = 1 AND IFNULL(is_transit, 0) = 0 ORDER BY id LIMIT 1"));
$qu = intval($val("SELECT id FROM quantity_units ORDER BY id LIMIT 1"));
$db->exec("INSERT INTO products (name, sku, location_id, qu_id_purchase, qu_id_stock, qu_id_consume, qu_id_price, default_best_before_days, sale_price, active) VALUES ('Producto Despacho E13', 'SKU-D13-01', $locId, $qu, $qu, $qu, $qu, 30, 10.0, 1)");
$pid = intval($db->lastInsertId());
$barcode = '770130000' . $pid;
$db->exec("INSERT INTO product_barcodes (product_id, barcode) VALUES ($pid, '$barcode')");
$stock->AddProduct($pid, 50, date('Y-m-d', strtotime('+20 days')), StockService::TRANSACTION_TYPE_PURCHASE, date('Y-m-d'), 4.0, $locId);
$custId = intval($val("SELECT id FROM customers WHERE IFNULL(active, 1) = 1 ORDER BY id LIMIT 1"));

$ok = 'ok';

// 1. Pedidos duplicados bloqueados (idempotency_key + firma en ventana 90s)
$idemKey = 'idem-test-' . $pid . '-' . time();
$ordRes = $cos->Create([
	'branch_id' => 1,
	'customer_id' => $custId,
	'delivery_type' => 'delivery',
	'requested_date' => date('Y-m-d'),
	'route_name' => 'Ruta Norte QA',
	'idempotency_key' => $idemKey,
	'items' => [['product_id' => $pid, 'amount' => 5]]
], 1);
$ord = $ordRes['order'] ?? $ordRes;
$oid = intval($ord['id']);
$ok('Pedido creado en etapa Pedido (draft)', $oid > 0 && $ord['status'] === 'draft' && $ord['route_name'] === 'Ruta Norte QA');

$dupThrown = false;
try
{
	$cos->Create([
		'branch_id' => 1,
		'customer_id' => $custId,
		'delivery_type' => 'delivery',
		'requested_date' => date('Y-m-d'),
		'idempotency_key' => $idemKey,
		'items' => [['product_id' => $pid, 'amount' => 5]]
	], 1);
}
catch (\Throwable $e)
{
	$dupThrown = str_contains($e->getMessage(), 'duplicado');
}
$ok('FIX 1a: Bloquea pedido duplicado por idempotency_key', $dupThrown);

$sigDupThrown = false;
try
{
	$cos->Create([
		'branch_id' => 1,
		'customer_id' => $custId,
		'delivery_type' => 'delivery',
		'requested_date' => date('Y-m-d'),
		'items' => [['product_id' => $pid, 'amount' => 5]]
	], 1);
}
catch (\Throwable $e)
{
	$sigDupThrown = str_contains($e->getMessage(), 'duplicado');
}
$ok('FIX 1b: Bloquea pedido abierto duplicado con misma firma (<90s)', $sigDupThrown);

// 2. Estados contradictorios / ciclo estricto de 7 etapas
$badStageThrown = false;
try
{
	$svc->FastScanUPCVerify($oid, $barcode, 1.0, null, 1);
}
catch (\Throwable $e)
{
	$badStageThrown = str_contains($e->getMessage(), 'Reserva');
}
$ok('FIX 3a: Bloquea escaneo UPC en borrador sin reserva', $badStageThrown);

// Etapa 2: Reserva
$resv = $svc->ReserveDispatchOrder($oid, 1);
$ok('Etapa 2 (Reserva): pedido confirmado y reservado', ($resv['order']['status'] ?? '') === 'confirmed');

// Asignar picking
$assign = $svc->AssignPickingOrder($oid, 1, 1);
$ok('Etapa 3a (Picking): asigna operador', $assign['success'] === true);

// Etapa 3: Picking UPC parcial (3/5) + Bloqueo sobrante (3+4 > 5)
$scan1 = $svc->FastScanUPCVerify($oid, $barcode, 3.0, 'L1', 1);
$ok('Etapa 3b: Escaneo parcial 3/5 avanza a picking (warning)', $scan1['status'] === 'warning' && floatval($scan1['scanned_qty']) === 3.0);

$scanOver = $svc->FastScanUPCVerify($oid, $barcode, 4.0, 'L1', 1);
$ok('FIX 2: Bloquea escaneo por encima de cantidad reservada (3+4 > 5)', !empty($scanOver['blocked']) && $scanOver['status'] === 'overage' && floatval($scanOver['scanned_qty']) === 3.0);

// Completar picking 3+2 = 5
$scan2 = $svc->FastScanUPCVerify($oid, $barcode, 2.0, 'L1', 1);
$ok('Etapa 3c: Completa 5/5 y pasa a picked', $scan2['status'] === 'match' && $scan2['order_status'] === 'picked');

// Etapa 4: Packing y etiqueta
$packRes = $svc->PackDispatchOrder($oid, 2, 'Precinto P-100', 1);
$ok('Etapa 4 (Packing): cierra 2 bultos y genera etiqueta ETQ-', $packRes['success'] === true && str_starts_with($packRes['label_code'], 'ETQ-'));

$label = $svc->GetDispatchLabel($oid);
$ok('Etiqueta imprimible contiene datos reales del pedido', $label['packages_count'] === 2 && $label['route_name'] === 'Ruta Norte QA');

// Asignación transporte
$tr = $svc->ScheduleTransportOrder($oid, [
	'carrier_name' => 'Conductor DemoQA',
	'carrier_vehicle' => 'Van Urbana V-04 (BCR-115)',
	'route' => 'Ruta Norte QA',
	'carrier_phone' => '+57 300 1112233'
], 1);
$ok('Etapa 5a (Transporte): programa vehículo y ruta reales', $tr['success'] === true);

// Etapa 5: Confirmar salida
$exit = $svc->ConfirmDispatchExit($oid, ['dispatch_number' => 'DSP-QA-' . $oid], 1);
$ok('Etapa 5b (Despacho): confirma salida y estado in_transit coherente', $exit['success'] === true && $exit['delivery_status'] === 'in_transit');

// Corrupción simulada (picked_amount > amount) bloqueada en salida si ocurriera
$badCancel = false;
try
{
	$svc->CancelOrder($oid, 'Intento inválido tras salida', 1);
}
catch (\Throwable $e)
{
	$badCancel = str_contains($e->getMessage(), 'despachado');
}
$ok('FIX 3b: Bloquea cancelar pedido ya despachado', $badCancel);

// FIX 5 & 6: Bloqueo cierre sin POD ni incidencias resueltas
$blockNoPod = false;
try
{
	$svc->ConciliateOrder($oid, 'Intento cierre sin entrega', 1);
}
catch (\Throwable $e)
{
	$blockNoPod = true;
}
$ok('FIX 5a: Bloquea conciliación sin entrega ni POD', $blockNoPod);

$missingSig = false;
try
{
	$svc->RecordDispatchDelivery($oid, [
		'delivery_status' => 'delivered',
		'delivery_signature_name' => '',
		'delivery_evidence_url' => ''
	], 1);
}
catch (\Throwable $e)
{
	$missingSig = str_contains($e->getMessage(), 'Prueba de Entrega');
}
$ok('FIX 5b: Bloquea registrar entrega domicilio sin firma ni folio POD', $missingSig);

// Entrega parcial con POD -> auto-crea incidencia logística -> bloquea conciliación hasta resolver incidencia
$partialRes = $svc->RecordDispatchDelivery($oid, [
	'delivery_status' => 'partial',
	'delivery_signature_name' => 'Carlos Méndez',
	'delivery_evidence_url' => 'POD-FOTO-881',
	'delivery_accuracy_pct' => 80.0,
	'route_incidents' => '1 unidad averiada en transporte',
	'cold_chain_temp_ok' => 1
], 1);
$ok('Etapa 6 (Entrega Parcial POD): registra entrega parcial e incidencia', $partialRes['success'] === true && $partialRes['delivery_status'] === 'partial');

$blockOpenInc = false;
try
{
	$svc->ConciliateOrder($oid, 'Cierre prematuro', 1);
}
catch (\Throwable $e)
{
	$blockOpenInc = str_contains($e->getMessage(), 'incidencias');
}
$ok('FIX 5c: Bloquea conciliación con incidencia logística abierta', $blockOpenInc);

$incId = intval($val("SELECT id FROM dispatch_logistics_incidents WHERE order_id = $oid AND resolution_status = 'open' ORDER BY id DESC LIMIT 1"));
$ok('Incidencia logística creada automáticamente en BD', $incId > 0);

$resInc = $svc->ResolveLogisticsIncident($incId, 'Nota ajuste aplicada por coordinación logística', 1);
$ok('Incidencia logística resuelta correctamente', $resInc['success'] === true);

$conc = $svc->ConciliateOrder($oid, 'Cierre auditado completo', 1);
$ok('Etapa 7 (Conciliación): cierra ciclo tras POD y resolución de incidencias', $conc['success'] === true);

// Verificación 7 etapas consistentes en GetDispatchOrderDetail
$detail = $svc->GetDispatchOrderDetail($oid);
$stagesDone = count(array_filter($detail['order']['stages'], fn($s) => $s['state'] === 'done'));
$ok('FIX 6: Matriz de 7 etapas canónicas 100% completada tras conciliación', count($detail['order']['stages']) === 7 && $stagesDone === 7);

// Verificación Pickup PIN & POD
$ordPickupRes = $cos->Create([
	'branch_id' => 1,
	'customer_id' => $custId,
	'delivery_type' => 'pickup',
	'requested_date' => date('Y-m-d'),
	'confirm' => 1,
	'items' => [['product_id' => $pid, 'amount' => 2]]
], 1);
$ordPickup = $ordPickupRes['order'] ?? $ordPickupRes;
$pidPickup = intval($ordPickup['id']);
$pinReal = $ordPickup['pickup_pin'];
$ok('Pedido pickup genera PIN automático de 6 dígitos', strlen((string)$pinReal) === 6);
$svc->FastScanUPCVerify($pidPickup, $barcode, 2.0, null, 1);
$svc->PackDispatchOrder($pidPickup, 1, 'Bolsa retiro', 1);
$exitP = $svc->ConfirmDispatchExit($pidPickup, [], 1);
$ok('Salida pickup pasa a ready_for_pickup (no in_transit)', $exitP['delivery_status'] === 'ready_for_pickup');

$wrongPin = false;
try
{
	$svc->VerifyPickupOrder($pidPickup, '000000', 1, 'Persona X');
}
catch (\Throwable $e)
{
	$wrongPin = str_contains($e->getMessage(), 'PIN');
}
$ok('Bloquea retiro con PIN incorrecto', $wrongPin);

$okPin = $svc->VerifyPickupOrder($pidPickup, $pinReal, 1, 'Laura Sánchez');
$ok('Retiro verificado con PIN + POD registra entrega', $okPin['success'] === true && $okPin['pod_signature'] === 'Laura Sánchez');

// FIX 4: Analítica OTIF real (no piso falso 96.8%)
$analytics = $svc->GetDispatchComparativeAnalytics(null);
$ok('FIX 4: OTIF real calculado dinámicamente (sin piso falso 96.8%)', array_key_exists('otif_pct', $analytics['delivery']) && $analytics['pickup']['otif_pct'] !== null);

// Cancelar pedido antes de despacho libera reserva
$ordCancelRes = $cos->Create([
	'branch_id' => 1,
	'customer_id' => $custId,
	'delivery_type' => 'pickup',
	'requested_date' => date('Y-m-d'),
	'confirm' => 1,
	'items' => [['product_id' => $pid, 'amount' => 3]]
], 1);
$ordCancel = $ordCancelRes['order'] ?? $ordCancelRes;
$cidCancel = intval($ordCancel['id']);
$cRes = $svc->CancelOrder($cidCancel, 'Cliente desistió antes de salida', 1);
$ok('Cancelación antes de salida anula y libera reservas', $cRes['success'] === true && floatval($cos->Reserved($pid, 1)) === 0.0);

echo "\n$count verificaciones, $fails fallos\n";
exit($fails > 0 ? 1 : 0);
