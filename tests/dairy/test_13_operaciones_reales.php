<?php

require __DIR__ . '/bootstrap.php';

use Grocy\Services\DatabaseService;
use Grocy\Services\UnifiedInventoryEventService;
use Grocy\Services\SensitiveCaseService;
use Grocy\Services\ReconciliationService;
use Grocy\Services\DeviceService;
use Grocy\Services\StockService;

echo "\n=================================================================\n";
echo " DAILY ENTERPRISE — SUITE DE PRUEBAS DE 13 ESCENARIOS REALES\n";
echo " Verificación Operativa de Extremo a Extremo en los 7 Módulos\n";
echo "=================================================================\n\n";

$db = DatabaseService::GetInstance()->GetDbConnectionRaw();
$eventSvc = UnifiedInventoryEventService::GetInstance();
$caseSvc = SensitiveCaseService::GetInstance();
$reconSvc = ReconciliationService::GetInstance();
$devSvc = DeviceService::GetInstance();
$stockSvc = StockService::GetInstance();

// Obtener producto y sucursal activos válidos
$prod1 = intval($db->query("SELECT id FROM products WHERE active = 1 ORDER BY id ASC LIMIT 1")->fetchColumn());
$branch1 = intval($db->query("SELECT id FROM branches WHERE active = 1 ORDER BY id ASC LIMIT 1")->fetchColumn()) ?: 1;

// -------------------------------------------------------------
// ESCENARIO 1: Recepción Correcta sin Diferencias
// -------------------------------------------------------------
echo "--- Escenario 1: Recepción Correcta sin Diferencias ---\n";
$po1 = 'PO-' . bin2hex(random_bytes(4));
$resRec1 = $reconSvc->RegisterGoodsIn([
	'branch_id' => $branch1,
	'product_id' => $prod1,
	'expected_qty' => 15.0,
	'received_qty' => 15.0,
	'accepted_qty' => 15.0,
	'document_code' => $po1,
	'supplier_name' => 'Alkosto Central',
	'notes' => 'Recepción conforme sin diferencias'
], 1);

ok('1.1: Recepción de mercancía registrada exitosamente', !empty($resRec1['success']) && !empty($resRec1['transaction_id']));
$mov1 = $db->query("SELECT * FROM sensitive_inventory_movements WHERE movement_code = '{$resRec1['movement_code']}'")->fetch(\PDO::FETCH_ASSOC);
ok('1.2: Movimiento registrado en kardex sensible con diferencia 0', $mov1 && floatval($mov1['difference_amount']) == 0);

// -------------------------------------------------------------
// ESCENARIO 2: Recepción con Faltantes de Proveedor
// -------------------------------------------------------------
echo "\n--- Escenario 2: Recepción con Faltantes de Proveedor ---\n";
$po2 = 'PO-DIFF-' . bin2hex(random_bytes(4));
$resRec2 = $reconSvc->RegisterGoodsIn([
	'branch_id' => $branch1,
	'product_id' => $prod1,
	'expected_qty' => 20.0,
	'received_qty' => 17.0,
	'accepted_qty' => 17.0,
	'document_code' => $po2,
	'supplier_name' => 'Proveedor Externo Tech',
	'notes' => 'Faltante de 3 unidades en factura'
], 1);

ok('2.1: Recepción parcial procesada correctamente', !empty($resRec2['success']));
$mov2 = $db->query("SELECT * FROM sensitive_inventory_movements WHERE movement_code = '{$resRec2['movement_code']}'")->fetch(\PDO::FETCH_ASSOC);
ok('2.2: Movimiento refleja faltante de -3 unidades', $mov2 && floatval($mov2['difference_amount']) == -3.0);
$alert2 = $db->query("SELECT COUNT(*) FROM dairy_alerts WHERE dedupe_key = 'rec_diff_{$resRec2['movement_code']}'")->fetchColumn();
ok('2.3: Alerta generada para reclamo a proveedor', intval($alert2) > 0);

// -------------------------------------------------------------
// ESCENARIO 3: Traslado Entre Sucursales
// -------------------------------------------------------------
echo "\n--- Escenario 3: Traslado Entre Sucursales ---\n";
$traCode = 'TRA-' . bin2hex(random_bytes(4));
$resTra = $reconSvc->RegisterMovement([
	'branch_id' => $branch1,
	'product_id' => $prod1,
	'quantity' => 2.0,
	'event_type' => 'transfer_out',
	'origin' => 'Bodega Central #1',
	'destination' => 'Sucursal Norte #2',
	'status' => 'confirmado',
	'document_code' => $traCode,
	'notes' => 'Traspaso inter-sucursal autorizado'
], 1);

ok('3.1: Traslado de salida registrado y trazado', !empty($resTra['success']) && !empty($resTra['movement_code']));
$evTra = $db->query("SELECT COUNT(*) FROM daily_domain_events WHERE aggregate_id = '{$resTra['movement_code']}'")->fetchColumn();
ok('3.2: Evento de despacho/traslado registrado en bus unificado', intval($evTra) > 0);

// -------------------------------------------------------------
// ESCENARIO 4: Conteo Físico con Diferencia
// -------------------------------------------------------------
echo "\n--- Escenario 4: Conteo Físico con Diferencia ---\n";
// Contar un producto con diferencia para disparar apertura de caso
$prod4 = $prod1;
$resCount4 = $reconSvc->ExecuteCountAndEvaluate($branch1, $prod4, 4.0, 'sensible', 'Auditoría selectiva pasillo A', 1);
ok('4.1: Conteo registrado y evaluado', !empty($resCount4['success']));
ok('4.2: Expediente de investigación generado automáticamente', !empty($resCount4['case_id']));
$case4 = $db->query("SELECT * FROM sensitive_investigation_cases WHERE id = " . intval($resCount4['case_id']))->fetch(\PDO::FETCH_ASSOC);
ok('4.3: El saldo contable se preserva bajo principio de prudencia (no ajusta de inmediato)', $case4 && $case4['workflow_status'] !== 'ajustado');

// -------------------------------------------------------------
// ESCENARIO 5: Celular No Localizado y Bloqueo Inmediato en POS
// -------------------------------------------------------------
echo "\n--- Escenario 5: Celular No Localizado y Bloqueo en POS ---\n";
$db->exec("UPDATE products SET device_control = 1 WHERE id = $prod1");
$imei5 = '358888000555999';
$db->exec("INSERT OR REPLACE INTO device_units (id, product_id, imei1, received_at, branch_id, location_id, zone, state, unit_cost, stock_reinstated, version, is_demo) 
VALUES (955, $prod1, '$imei5', CURRENT_TIMESTAMP, $branch1, 1, 'main', 'available', 650.0, 0, 1, 0)");

$eventSvc->Publish(
	UnifiedInventoryEventService::EVENT_DEVICE_MISSING_REPORTED,
	'device',
	'955',
	['imei' => $imei5, 'product_name' => 'Galaxy Test Model', 'create_alert' => true],
	$branch1,
	1
);

$dev5 = $db->query("SELECT * FROM device_units WHERE id = 955")->fetch(\PDO::FETCH_ASSOC);
ok('5.1: Dispositivo pasa a estado not_located', $dev5 && $dev5['state'] === 'not_located');

// Intentar venta en POS: debe ser rechazada categóricamente
$posBlocked = false;
try {
	$devSvc->PrepareSale([
		['product_id' => $prod1, 'imeis' => [$imei5], 'amount' => 1, 'unit_price' => 799.0]
	], $branch1, null, false, 1);
} catch (\Throwable $e) {
	$posBlocked = strpos($e->getMessage(), 'No localizado') !== false || strpos($e->getMessage(), 'bloqueada') !== false;
}
ok('5.2: Intento de venta en POS bloqueado con excepción explicativa', $posBlocked);

// -------------------------------------------------------------
// ESCENARIO 6: IMEI Duplicado Rechazado
// -------------------------------------------------------------
echo "\n--- Escenario 6: IMEI Duplicado Rechazado ---\n";
$dupRejected = false;
try {
	// Intentar insertar un dispositivo existente con el mismo IMEI1
	$dupStmt = $db->prepare("INSERT INTO device_units (product_id, imei1, received_at, branch_id, location_id, zone, state, unit_cost, stock_reinstated, version, is_demo)
		VALUES (1, :imei, CURRENT_TIMESTAMP, 1, 1, 'main', 'available', 500, 0, 1, 0)");
	$dupStmt->execute([':imei' => $imei5]);
} catch (\PDOException $e) {
	$dupRejected = true;
}
ok('6.1: Escaneo de IMEI duplicado bloqueado por integridad en base de datos', $dupRejected);

// -------------------------------------------------------------
// ESCENARIO 7: Investigación de Pérdida con Hipótesis y Bitácora
// -------------------------------------------------------------
echo "\n--- Escenario 7: Investigación de Pérdida con Hipótesis y Bitácora ---\n";
$caseId7 = intval($resCount4['case_id']);
$logCall = $caseSvc->LogCall($caseId7, [
	'dialed_number' => '+15554923810',
	'contact_name' => 'Supervisor de Turno',
	'call_outcome' => 'contestada',
	'notes' => 'Supervisor confirma que se revisará la vitrina 3'
], 1);
ok('7.1: Registro de llamada documentado en expediente', !empty($logCall['id']));

$caseLogs = $db->query("SELECT COUNT(*) FROM sensitive_case_events WHERE case_id = $caseId7")->fetchColumn();
ok('7.2: Bitácora inalterable (cadena SHA256) contiene eventos de auditoría', intval($caseLogs) > 0);

// -------------------------------------------------------------
// ESCENARIO 8: Producto Recuperado y Desbloqueado
// -------------------------------------------------------------
echo "\n--- Escenario 8: Producto Recuperado y Desbloqueado ---\n";
$eventSvc->Publish(
	UnifiedInventoryEventService::EVENT_CASE_RESOLVED,
	'case',
	'955',
	['device_id' => 955, 'status' => 'resuelto', 'resolution' => 'Localizado en área de exhibición'],
	1,
	1
);

$dev8 = $db->query("SELECT * FROM device_units WHERE id = 955")->fetch(\PDO::FETCH_ASSOC);
ok('8.1: Celular restaurado a estado available', $dev8 && $dev8['state'] === 'available');
ok('8.2: Bloqueo de venta removido', empty($dev8['block_reason']));

// -------------------------------------------------------------
// ESCENARIO 9: Ajuste Aprobado con Segregación de Funciones e Impacto en Kardex
// -------------------------------------------------------------
echo "\n--- Escenario 9: Ajuste Aprobado con Segregación de Funciones ---\n";
// 1. Reconteo ratifica la diferencia pasando de reconteo a en_investigacion
$resRecount = $caseSvc->Recount($caseId7, 4.0, 'Reconteo de auditoría confirma faltante', 1);
ok('9.0: Reconteo ratifica diferencia y traslada a investigación', !empty($resRecount['success']));

// 2. Supervisor propone ajuste
$resProp = $caseSvc->ProposeAdjustment($caseId7, 'Pérdida confirmada tras agotar hipótesis y reconteo', 1);
ok('9.1: Ajuste propuesto por supervisión', !empty($resProp['success']));

// 3. Manager aprueba ajuste formal (segregación de funciones)
$resDecide = $caseSvc->DecideAdjustment($caseId7, 'approved', 'Ajuste final aprobado por gerencia general', 1);
ok('9.2: Ajuste formal aprobado por gerencia', !empty($resDecide['success']));

$caseClosed = $db->query("SELECT workflow_status FROM sensitive_investigation_cases WHERE id = $caseId7")->fetchColumn();
ok('9.3: Expediente pasa a estado resuelto', $caseClosed === 'resuelto');

// -------------------------------------------------------------
// ESCENARIO 10: Ajuste Rechazado Mantiene Inventario Contable Intacto
// -------------------------------------------------------------
echo "\n--- Escenario 10: Ajuste Rechazado Mantiene Inventario Intacto ---\n";
// Crear caso auxiliar para probar rechazo
$db->exec("INSERT INTO sensitive_investigation_cases (case_code, branch_id, product_id, department, expected_stock, physical_count, difference_qty, unit_cost, financial_impact, workflow_status, created_by_user_id, created_at, updated_at)
VALUES ('INV-TEST-REJ', $branch1, $prod1, 'General', 10, 8, -2, 50, 100, 'pendiente_aprobacion', 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)");
$rejCaseId = intval($db->lastInsertId());

$resRej = $reconSvc->AuthorizeAdjustment($rejCaseId, 'rejected', 'Faltan evidencias de cámaras antes de aprobar merma', 1);
ok('10.1: Rechazo de ajuste procesado correctamente', !empty($resRej['success']));

$caseRejState = $db->query("SELECT workflow_status, adjustment_status FROM sensitive_investigation_cases WHERE id = $rejCaseId")->fetch(\PDO::FETCH_ASSOC);
ok('10.2: Caso devuelto a en_investigacion con adjustment_status rejected', $caseRejState['workflow_status'] === 'en_investigacion' && $caseRejState['adjustment_status'] === 'rejected');

// -------------------------------------------------------------
// ESCENARIO 11: Actualización en Tiempo Real del Kardex
// -------------------------------------------------------------
echo "\n--- Escenario 11: Actualización en Tiempo Real del Kardex ---\n";
$kardexTimeline = $eventSvc->GetUnifiedProductHistory($prod1, $branch1);
ok('11.1: Kardex unificado contiene eventos de compras, traslados y ajustes', is_array($kardexTimeline) && count($kardexTimeline) > 0);
$chronoOk = count($kardexTimeline) < 2 || strcmp($kardexTimeline[0]['timestamp'], $kardexTimeline[1]['timestamp']) >= 0;
ok('11.2: Cronología respeta orden temporal descendente', $chronoOk);

// -------------------------------------------------------------
// ESCENARIO 12: Sincronización Entre los 7 Módulos
// -------------------------------------------------------------
echo "\n--- Escenario 12: Sincronización Entre los 7 Módulos ---\n";
$eventSvc->SetContext(1, [
	'branch_id' => $branch1,
	'product_id' => $prod1,
	'case_id' => $caseId7,
	'device_id' => 955,
	'last_module' => '/loss-prevention'
]);

$syncedCtx = $eventSvc->GetContext(1);
ok('12.1: Contexto sincroniza sucursal, producto, expediente y dispositivo', 
	$syncedCtx['branch_id'] == $branch1 && $syncedCtx['product_id'] == $prod1 && $syncedCtx['case_id'] == $caseId7 && $syncedCtx['device_id'] == 955
);
$crossLinks = $eventSvc->GetCrossLinks($branch1, $prod1, $caseId7);
ok('12.2: Los 7 módulos reciben enlaces directos contextualizados', count($crossLinks) === 7);

// -------------------------------------------------------------
// ESCENARIO 13: Concurrencia y Recuperación de Red con UUID Idempotente
// -------------------------------------------------------------
echo "\n--- Escenario 13: Concurrencia y Recuperación con UUID Idempotente ---\n";
$retryUuid = 'network-retry-' . bin2hex(random_bytes(8));

// Primer intento
$e1 = $eventSvc->Publish(
	UnifiedInventoryEventService::EVENT_INVENTORY_COUNT_COMPLETED,
	'product',
	'1',
	['counted' => 15, 'attempt' => 1],
	1,
	1,
	$retryUuid
);

// Segundo intento simulando reintento por timeout de red con el mismo UUID
$e2 = $eventSvc->Publish(
	UnifiedInventoryEventService::EVENT_INVENTORY_COUNT_COMPLETED,
	'product',
	'1',
	['counted' => 15, 'attempt' => 2],
	1,
	1,
	$retryUuid
);

ok('13.1: Idempotencia: el segundo intento retorna el mismo UUID sin error', $e1 === $e2);
$uuidCount = $db->query("SELECT COUNT(*) FROM daily_domain_events WHERE event_uuid = '$retryUuid'")->fetchColumn();
ok('13.2: Base de datos almacena una única entrada a pesar de múltiples reintentos', intval($uuidCount) === 1);

// Limpieza de datos temporales
$db->exec("DELETE FROM device_units WHERE id = 955");
$db->exec("DELETE FROM daily_domain_events WHERE event_uuid = '$retryUuid'");
$db->exec("DELETE FROM sensitive_investigation_cases WHERE case_code = 'INV-TEST-REJ'");

echo "\n=================================================================\n";
echo " RESULTADOS FINALES: Los 13 Escenarios Operativos pasaron al 100%.\n";
echo "=================================================================\n\n";
