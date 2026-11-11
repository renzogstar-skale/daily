<?php
/**
 * DAIRY — Suite de pruebas de Optimización #14 Devoluciones Logísticas
 * Verifica las 6 etapas canónicas, 17 capacidades y 6 correcciones obligatorias:
 *  1. Mercancía dañada/perecedera bloqueada como vendible
 *  2. Prevención de devoluciones físicas duplicadas (idempotency_key + ventana 90s)
 *  3. Bloqueo de unidades sin destino final
 *  4. Bloqueo de clasificación/cierre en retornos sin recepción ni inspección previa
 *  5. Bloqueo de diferencias entre cantidades recibidas y conciliadas
 *  6. Bloqueo de reingreso al stock sin autorización explícita de supervisor + Kardex real
 *  7. Separación estricta entre devoluciones comerciales del POS y logística inversa física
 */

require __DIR__ . '/bootstrap.php';

use Grocy\Services\RetailPreventionService;
use Grocy\Services\StockService;

$svc = RetailPreventionService::GetInstance();
$stockSvc = StockService::GetInstance();

// Producto no perecedero activo con ubicación y stock conocido
$prod = $db->query("
	SELECT p.id, p.name, p.sku, p.location_id
	FROM products p
	JOIN locations l ON l.id = p.location_id
	WHERE p.active = 1
	  AND p.device_control = 0
	  AND IFNULL(l.is_freezer, 0) = 0
	  AND LOWER(p.name) NOT LIKE '%leche%'
	  AND LOWER(p.name) NOT LIKE '%huevo%'
	  AND LOWER(p.name) NOT LIKE '%queso%'
	  AND LOWER(p.name) NOT LIKE '%pollo%'
	ORDER BY p.id ASC
	LIMIT 1
")->fetch(PDO::FETCH_ASSOC);

if (!$prod) {
	throw new Exception("No se encontró producto base no perecedero para prueba.");
}

$pid = intval($prod['id']);
$sku = $prod['sku'] ?: ('SKU-RET-' . $pid);
if (empty($prod['sku'])) {
	$db->prepare("UPDATE products SET sku = ? WHERE id = ?")->execute([$sku, $pid]);
}

$testBarcode = '750100999' . $pid;
$db->prepare("DELETE FROM product_barcodes WHERE product_id = ? AND barcode = ?")->execute([$pid, $testBarcode]);
$db->prepare("INSERT INTO product_barcodes (product_id, barcode) VALUES (?, ?)")->execute([$pid, $testBarcode]);

// Segundo producto distinto para probar escaneo UPC cruzado inválido
$otherPid = intval($db->query("SELECT id FROM products WHERE id <> $pid AND active = 1 LIMIT 1")->fetchColumn());
$otherBarcode = '999888777' . $otherPid;
$db->prepare("DELETE FROM product_barcodes WHERE product_id = ? AND barcode = ?")->execute([$otherPid, $otherBarcode]);
$db->prepare("INSERT INTO product_barcodes (product_id, barcode) VALUES (?, ?)")->execute([$otherPid, $otherBarcode]);

// Crear usuario operativo sin permisos de supervisor/aprobación
$db->exec("DELETE FROM users WHERE username = 'emp_returns_test'");
$db->exec("INSERT INTO users (username, first_name, last_name, password, row_created_timestamp) VALUES ('emp_returns_test', 'Operario', 'Muelle', 'x', datetime('now'))");
$empUserId = intval($db->lastInsertId());

// Pedido real para validar tope de cantidad
$db->exec("INSERT INTO customer_orders (order_code, branch_id, customer_id, status, created_at) VALUES ('PED-RET-TEST-' || strftime('%s','now'), 1, 1, 'dispatched', datetime('now'))");
$orderId = intval($db->lastInsertId());
$db->prepare("INSERT INTO customer_order_items (order_id, product_id, amount, unit_price) VALUES (?, ?, 5, 10)")->execute([$orderId, $pid]);

$assertThrows = function (callable $fn, string $needle) {
	try {
		$fn();
		return false;
	} catch (\Throwable $e) {
		return stripos($e->getMessage(), $needle) !== false;
	}
};

// =========================================================================
// 1. ETAPA 1 (SOLICITUD RMA) + FIX 2 (ANTI-DUPLICADOS)
// =========================================================================
$idemA = 'test-rma-a-' . uniqid();
$resA = $svc->CreateReturn([
	'product_id' => $pid,
	'branch_id' => 1,
	'order_id' => $orderId,
	'quantity' => 5,
	'unit_cost' => 6.50,
	'return_type' => 'daño_transporte',
	'reason' => 'Caja golpeada en descarga',
	'idempotency_key' => $idemA,
], 1);
ok('Etapa 1 (Solicitud RMA): creada con folio DEV y RMA único', !empty($resA['rma_number']) && str_starts_with($resA['rma_number'], 'RMA-') && $resA['status'] === 'solicitud');

$resADup = $svc->CreateReturn([
	'product_id' => $pid,
	'branch_id' => 1,
	'order_id' => $orderId,
	'quantity' => 5,
	'unit_cost' => 6.50,
	'return_type' => 'daño_transporte',
	'reason' => 'Reintento',
	'idempotency_key' => $idemA,
], 1);
ok('FIX 2a: Reintento por idempotency_key devuelve existente sin duplicar', !empty($resADup['replayed']) && intval($resADup['id']) === intval($resA['id']));

ok('FIX 2b: Bloquea solicitud física duplicada en ventana de 90s', $assertThrows(fn() => $svc->CreateReturn([
	'product_id' => $pid,
	'branch_id' => 1,
	'order_id' => $orderId,
	'quantity' => 5,
	'return_type' => 'daño_transporte',
	'reason' => 'Doble clic accidental',
	'idempotency_key' => 'otro-' . uniqid(),
], 1), 'duplicada'));

ok('FIX 2c: Bloquea retorno por encima de unidades del pedido', $assertThrows(fn() => $svc->CreateReturn([
	'product_id' => $pid,
	'branch_id' => 1,
	'order_id' => $orderId,
	'quantity' => 3,
	'return_type' => 'devolucion_cliente',
	'reason' => 'Exceso de pedido',
], 1), 'excede'));

// =========================================================================
// 2. ETAPA 2 (RECEPCIÓN UPC) + FIX 4 (NO SALTAR INSPECCIÓN)
// =========================================================================
$retA = intval($resA['id']);
ok('FIX 4a: Bloquea inspeccionar antes de recepción física', $assertThrows(fn() => $svc->InspectReturn($retA, [
	'condition_grade' => 'danado',
	'inspection_notes' => 'Intento sin recibir',
], 1), 'recepción física'));

ok('FIX 4b: Bloquea clasificar sin recepción ni inspección previa', $assertThrows(fn() => $svc->ClassifyReturn($retA, [
	'classification' => 'merma_definitiva',
], 1), 'sin inspección'));

ok('Escaneo UPC cruzado de otro producto es rechazado', $assertThrows(fn() => $svc->ReceiveReturn($retA, [
	'upc_scanned' => $otherBarcode,
	'received_quantity' => 4,
], 1), 'no corresponde'));

$scanLookup = $svc->LookupReturnByUpc($testBarcode);
ok('Escáner UPC resuelve producto y RMA abierto', ($scanLookup['match_type'] ?? '') === 'product' && !empty($scanLookup['open_returns']));

$recA = $svc->ReceiveReturn($retA, [
	'upc_scanned' => $testBarcode,
	'received_quantity' => 4, // 1 unidad faltante vs 5 solicitadas
	'notes' => 'Faltó 1 unidad en tarima',
], 1);
ok('Etapa 2 (Recepción Física UPC): registra 4/5 u. y detecta varianza -1', $recA['status'] === 'recepcion' && $recA['variance'] == -1.0);

// =========================================================================
// 3. ETAPA 3 (INSPECCIÓN + FOTO) & FIX 1 (DAÑADO BLOQUEADO COMO VENDIBLE)
// =========================================================================
$inspA = $svc->InspectReturn($retA, [
	'condition_grade' => 'danado',
	'inspection_notes' => 'Empaque aplastado y sello abierto por humedad',
	'photo_data' => '/img/control_center/dest_merma.jpg',
], 1);
ok('Etapa 3 (Inspección): dictamina daño y guarda fotografía persistente', $inspA['status'] === 'inspeccion'
	&& intval($db->query("SELECT COUNT(*) FROM return_evidence_photos WHERE return_id = $retA")->fetchColumn()) === 1);

ok('FIX 1a: Bloquea registrar mercancía dañada como apta para reventa (Reincorporación)', $assertThrows(fn() => $svc->ClassifyReturn($retA, [
	'classification' => 'reincorporacion',
], 1), 'apta para reventa'));

ok('FIX 1b: Bloquea asignar unidades dañadas en resalable_quantity > 0', $assertThrows(fn() => $svc->ClassifyReturn($retA, [
	'classification' => 'merma_definitiva',
	'resalable_quantity' => 1,
	'damaged_quantity' => 3,
	'rtv_quantity' => 0,
	'repairable_quantity' => 0,
], 1), 'no puede registrarse como apta para reventa'));

// =========================================================================
// 4. FIX 3 (UNIDADES SIN DESTINO FINAL) + CLASIFICACIÓN MIXTA (DAÑADO + RTV)
// =========================================================================
ok('FIX 3a: Bloquea dejar en cuarentena como destino final al clasificar', $assertThrows(fn() => $svc->ClassifyReturn($retA, [
	'classification' => 'cuarentena',
], 1), 'destino final'));

ok('FIX 3b/FIX 5: Bloquea clasificación cuya suma no iguale lo recibido (3 != 4)', $assertThrows(fn() => $svc->ClassifyReturn($retA, [
	'classification' => 'devolucion_proveedor',
	'resalable_quantity' => 0,
	'damaged_quantity' => 2,
	'rtv_quantity' => 1,
	'repairable_quantity' => 0,
], 1), 'cuadrar'));

$clsA = $svc->ClassifyReturn($retA, [
	'classification' => 'devolucion_proveedor',
	'resalable_quantity' => 0,
	'damaged_quantity' => 2,
	'rtv_quantity' => 2,
	'repairable_quantity' => 0,
	'supplier_id' => 1,
], 1);
ok('Etapa 4 (Clasificación mixta): 2 dañadas + 2 RTV = 4 recibidas', $clsA['status'] === 'clasificacion' && $clsA['breakdown']['rtv'] == 2.0);

// =========================================================================
// 5. ETAPA 5 (DISPOSICIÓN AUTORIZADA) + FIX 6 (SIN AUTORIZACIÓN BLOQUEADO)
// =========================================================================
ok('FIX 6a: Empleado sin permiso supervisor NO puede autorizar disposición', $assertThrows(fn() => $svc->AuthorizeAndExecuteDisposition($retA, [
	'notes' => 'Intento no autorizado',
], $empUserId), 'Autorización de supervisor'));

ok('FIX 5b: Bloquea conciliar antes de ejecutar disposición autorizada', $assertThrows(fn() => $svc->ConciliateReturn($retA, [
	'conciliated_quantity' => 4,
	'conciliation_notes' => 'Antes de tiempo',
], 1), 'disposición autorizada'));

$dispA = $svc->AuthorizeAndExecuteDisposition($retA, [
	'notes' => 'Baja 2 dañadas + envío 2 a proveedor #1',
], 1);
ok('Etapa 5 (Disposición): autorizada por supervisor sin reingreso indebido', $dispA['status'] === 'disposicion' && $dispA['stock_reentered'] === false);

// =========================================================================
// 6. ETAPA 6 (CONCILIACIÓN) + FIX 5 (DIFERENCIAS ENTRE RECIBIDO Y CONCILIADO)
// =========================================================================
ok('FIX 5c: Bloquea conciliar cantidad distinta a la recibida (3 vs 4)', $assertThrows(fn() => $svc->ConciliateReturn($retA, [
	'conciliated_quantity' => 3,
	'conciliation_notes' => 'Cierre incompleto',
], 1), 'Diferencia detectada'));

ok('FIX 5d: Si recibido != solicitado (4 != 5), exige nota obligatoria de varianza', $assertThrows(fn() => $svc->ConciliateReturn($retA, [
	'conciliated_quantity' => 4,
	'conciliation_notes' => '',
], 1), 'documente el motivo'));

$concA = $svc->ConciliateReturn($retA, [
	'conciliated_quantity' => 4,
	'conciliation_notes' => 'Se concilian 4 u. físicas; 1 faltante reclamada a transportista',
], 1);
ok('Etapa 6 (Conciliación): cierra RMA por 4 u. con diferencia justificada', $concA['status'] === 'conciliacion' && $concA['conciliated_quantity'] == 4.0);

// =========================================================================
// 7. FLUJO COMPLETO DE REINGRESO AUTORIZADO AL STOCK (FIX 6 + KARDEX REAL)
// =========================================================================
$beforeStock = floatval($stockSvc->GetProductDetails($pid)['stock_amount']);

$resB = $svc->CreateReturn([
	'product_id' => $pid,
	'branch_id' => 1,
	'quantity' => 3,
	'unit_cost' => 5.00,
	'return_type' => 'cambio',
	'reason' => 'Excedente sellado de sucursal destino',
	'idempotency_key' => 'test-rma-b-' . uniqid(),
], 1);
$retB = intval($resB['id']);

$svc->ReceiveReturn($retB, ['upc_scanned' => $sku, 'received_quantity' => 3], 1);
$svc->InspectReturn($retB, ['condition_grade' => 'intacto', 'inspection_notes' => 'Caja y sello 100% íntegros'], 1);
$svc->ClassifyReturn($retB, ['classification' => 'reincorporacion'], 1);

ok('FIX 6b: Bloquea reingreso si authorize_stock_reentry = false', $assertThrows(fn() => $svc->AuthorizeAndExecuteDisposition($retB, [
	'authorize_stock_reentry' => false,
], 1), 'autorización de reingreso'));

$afterRejectStock = floatval($stockSvc->GetProductDetails($pid)['stock_amount']);
ok('FIX 6c: El stock real no cambió tras rechazo de reingreso', abs($afterRejectStock - $beforeStock) < 0.001);

$dispB = $svc->AuthorizeAndExecuteDisposition($retB, [
	'authorize_stock_reentry' => true,
	'target_location_id' => intval($prod['location_id']),
	'notes' => 'Reingreso aprobado tras inspección íntegra',
], 1);
$afterReentryStock = floatval($stockSvc->GetProductDetails($pid)['stock_amount']);
ok('Reingreso autorizado suma exactamente +3 u. reales al inventario', $dispB['stock_reentered'] === true && !empty($dispB['stock_reentry_tx_id']) && abs($afterReentryStock - ($beforeStock + 3.0)) < 0.001);

// Idempotencia de disposición (no duplica reingreso)
$svc->AuthorizeAndExecuteDisposition($retB, ['authorize_stock_reentry' => true], 1);
$afterAgainStock = floatval($stockSvc->GetProductDetails($pid)['stock_amount']);
ok('Re-ejecutar disposición aprobada NO duplica el reingreso en stock', abs($afterAgainStock - ($beforeStock + 3.0)) < 0.001);

$concB = $svc->ConciliateReturn($retB, ['conciliated_quantity' => 3, 'conciliation_notes' => 'Reingreso confirmado en Kardex'], 1);
ok('Etapa 6 (Conciliación con reingreso): cerrada al 100%', $concB['status'] === 'conciliacion');

// =========================================================================
// 8. REPARACIÓN Y SEPARACIÓN POS VS LOGÍSTICA + AUDITORÍA + EVIDENCIA
// =========================================================================
$resC = $svc->CreateReturn([
	'product_id' => $pid,
	'branch_id' => 1,
	'quantity' => 2,
	'return_type' => 'calidad',
	'reason' => 'Etiqueta despegada, apto para reempaque/reparación',
	'idempotency_key' => 'test-rma-c-' . uniqid(),
], 1);
$retC = intval($resC['id']);
$svc->ReceiveReturn($retC, ['upc_scanned' => $testBarcode, 'received_quantity' => 2], 1);
$svc->InspectReturn($retC, ['condition_grade' => 'reparable', 'inspection_notes' => 'Requiere reetiquetado y sellado térmico'], 1);
$clsC = $svc->ClassifyReturn($retC, ['classification' => 'reparacion', 'repair_notes' => 'Taller de reacondicionamiento #2'], 1);
ok('Clasificación Reparación / Reacondicionamiento soportada correctamente', $clsC['classification'] === 'reparacion' && $clsC['breakdown']['repairable'] == 2.0);

$ev = $svc->AddReturnEvidence($retC, ['photo_data' => '/img/control_center/dest_cuarentena.jpg', 'caption' => 'Etiqueta dañada previo a taller'], 1);
ok('Evidencia fotográfica persistida por endpoint específico', !empty($ev['photo_id']));

$detailB = $svc->GetReturnDetail($retB);
ok('6 etapas canónicas en BuildReturnStages todas completadas tras conciliar', count($detailB['stages']) === 6 && count(array_filter($detailB['stages'], fn($s) => $s['done'] === true)) === 6);
ok('Bitácora de auditoría registra todo el ciclo de vida completo', count($detailB['audit_log']) >= 6);

$listLogistics = $svc->GetReturns(1, null, null, 'logistics');
ok('Separación POS vs Logística: resumen financiero logístico NO incluye customer_refunds de caja', !array_key_exists('customer_refunds', $listLogistics['financial_summary']));

echo "\n$count verificaciones, $fails fallos\n";
exit($fails > 0 ? 1 : 0);
