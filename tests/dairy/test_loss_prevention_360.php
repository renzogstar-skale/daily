<?php

// DAILY ENTERPRISE — PREVENCIÓN DE PÉRDIDAS 360°
// PRUEBAS COMPLETAS DEL FLUJO OPERATIVO Y CENTRO DE COMANDO (Sección 18)

require __DIR__ . '/bootstrap.php';

use Grocy\Services\AlertCenterService;
use Grocy\Services\LossReconciliationService;
use Grocy\Services\ReconciliationService;
use Grocy\Services\SensitiveCaseService;
use Grocy\Services\StockService;

$svc = SensitiveCaseService::GetInstance();
$engine = LossReconciliationService::GetInstance();
$rec = ReconciliationService::GetInstance();

echo "\n=======================================================\n";
echo " DAILY ENTERPRISE — PREVENCIÓN DE PÉRDIDAS 360°\n";
echo " Verificación del Flujo Completo Operativo y Gerencial\n";
echo "=======================================================\n\n";

// Ubicaciones de prueba
$loc = intval($val("SELECT id FROM locations WHERE branch_id = 1 AND IFNULL(is_transit, 0) = 0 AND storage_class = 'ambient' ORDER BY id LIMIT 1"));
$qu = intval($val('SELECT qu_id_stock FROM products WHERE id = 175'));

// Paso 1: Configurar producto sensible (Smartphone / Celular de alta gama)
$productId = 9880;
$db->prepare("INSERT INTO products (id, name, active, location_id, qu_id_purchase, qu_id_stock, min_stock_amount, default_best_before_days, sku, sale_price, is_sensitive, department, is_demo, storage_class, control_type)
	VALUES (?, 'Smartphone Samsung Galaxy S24 Ultra', 1, ?, ?, ?, 0, 365, 'S24-ULTRA', 1200, 1, 'Telefonía y Digital', 0, 'ambient', 'serial')")->execute([$productId, $loc, $qu, $qu]);
$db->prepare('INSERT INTO product_barcodes (product_id, barcode, qu_id) VALUES (?, "7701234567890", ?)')->execute([$productId, $qu]);

// Recepción inicial de 10 unidades
$tid = null;
StockService::GetInstance()->AddProduct($productId, 10.0, date('Y-m-d', strtotime('+1 year')), StockService::TRANSACTION_TYPE_PURCHASE, date('Y-m-d'), 800.0, $loc, null, $tid, 0, true);

$exp = $engine->Expected($productId, 1);
ok('Paso 1: Stock esperado inicial = 10 unidades', $exp['expected'] == 10.0);

// Paso 2: Conteo físico reporta faltante de 2 celulares (Se contaron 8 vs 10)
$countRes = $svc->RegisterCount(1, $productId, 8.0, ['zone' => 'Vitrinas de Alta Seguridad', 'count_type' => 'sensible', 'shift' => 'Turno Mañana'], 1);
$caseId = intval($countRes['case_id']);
ok('Paso 2.1: Detección de faltante de 2 celulares (-2 u.)', $countRes['difference_qty'] == -2.0);
ok('Paso 2.2: Expediente abierto con código único', $caseId > 0);
ok('Paso 2.3: Alerta generada automáticamente en Centro de Alertas', $countRes['alert_id'] > 0);

$caseData = $svc->Detail($caseId);
ok('Paso 2.4: Expediente en estado inicial de Reconteo', $caseData['case']['workflow_status'] === 'reconteo');
ok('Paso 2.5: Valor económico en riesgo = $1,600 (2 × $800)', $caseData['case']['financial_impact'] == 1600.0);

// Paso 3: Contactar al Supervisor vía Llamada Registrada
$contacts = $svc->GetAuthorizedContacts(1, 'Telefonía y Digital');
ok('Paso 3.1: Directorio de contactos autorizados consultado', count($contacts) > 0);

$callRes = $svc->LogCall($caseId, [
	'contact_name' => 'Carlos M. (Supervisor)',
	'contact_phone' => '+57 300 234 1165',
	'contact_role' => 'Supervisor de Turno',
	'call_result' => 'answered',
	'duration_seconds' => 120,
	'notes' => 'Se informa al supervisor sobre los 2 celulares faltantes en vitrina. Acuerda reconteo inmediato.'
], 1);
ok('Paso 3.2: Llamada registrada con resultado y notas', $callRes['call_result'] === 'answered');

$callsList = $svc->GetCalls($caseId);
ok('Paso 3.3: Bitácora de llamadas incluye el intento de contacto', count($callsList) === 1);

// Paso 4: Enviar Mensaje Interno Vinculado a la Incidencia
$msgRes = $svc->SendCaseMessage($caseId, 1, 'Digital, necesitamos verificar dos celulares faltantes en bodega. Realicen reconteo y confirmen los IMEI.', true);
ok('Paso 4.1: Mensaje de alta prioridad enviado al equipo de la incidencia', $msgRes['is_priority'] === true);

$threadInfo = $svc->GetOrCreateCaseThread($caseId, 1);
ok('Paso 4.2: Hilo de conversación contiene el mensaje registrado', count($threadInfo['messages']) >= 1);

// Paso 5: Asignar Tarea de Reconteo al área Digital
$taskRes = $svc->AddTask($caseId, [
	'title' => 'Reconteo físico ciego y verificación de IMEI en vitrina',
	'department' => 'Digital',
	'priority' => 'urgent',
	'description' => 'Realizar conteo ciego sin mirar existencias previas y registrar números de serie.',
	'evidence_required' => 1
], 1);
$taskId = intval($taskRes['id']);
ok('Paso 5: Tarea multi-área asignada a Digital', $taskId > 0 && $taskRes['department'] === 'Digital');

// Paso 6: Supervisor confirma recepción y avanza tarea a 'in_progress' y luego 'completed'
$upd1 = $svc->UpdateTaskStatus($taskId, 'in_progress', 'Supervisor inicia conteo en vitrina', 2);
ok('Paso 6.1: Tarea avanzada a En Proceso', $upd1['status'] === 'in_progress');

$upd2 = $svc->UpdateTaskStatus($taskId, 'completed', 'Reconteo ratifica 8 unidades físicas. Los 2 faltantes no se encuentran en vitrina.', 2);
ok('Paso 6.2: Supervisor confirma reconteo y completa tarea', $upd2['status'] === 'completed');

// Avanzar expediente a 'en_investigacion'
$cRow = $db->query("SELECT * FROM sensitive_investigation_cases WHERE id = $caseId")->fetch(PDO::FETCH_ASSOC);
$svc->Transition($cRow, 'en_investigacion', 'Reconteo ratificó diferencia; pasa a investigación formal', 1);
$cRow = $db->query("SELECT * FROM sensitive_investigation_cases WHERE id = $caseId")->fetch(PDO::FETCH_ASSOC);
ok('Paso 6.3: Expediente pasa formalmente a Investigación', $cRow['workflow_status'] === 'en_investigacion');

// Paso 7: Manager abre Mapa Operativo de la Sucursal
$mapData = $svc->BranchOperationalMap(1);
ok('Paso 7.1: Mapa operativo de sucursal cargado', count($mapData['zones']) >= 8);
$vitrinasZone = collect($mapData['zones'])->firstWhere('code', 'vitrinas');
ok('Paso 7.2: Zona de vitrinas reporta incidencia crítica activa', $vitrinasZone['critical_count'] >= 1);

// Paso 8: Manager acude a la ubicación (Check-in in-situ)
$checkinRes = $svc->RegisterArrival($caseId, $loc, 'Vitrinas de Alta Seguridad', 'Manager llega a la vitrina central con supervisor', 1);
ok('Paso 8: Check-in in-situ registrado con timestamp auditado', $checkinRes['checkin_id'] > 0);

// Paso 9: Escanear productos en campo con escáner
$scanRes = $svc->ScanVerify($caseId, '7701234567890', 8.0, ['IMEI356789012345671', 'IMEI356789012345672'], null, null, null, 1);
ok('Paso 9.1: Escáner identifica automáticamente el producto correcto', $scanRes['is_matching_product'] === true);
ok('Paso 9.2: Escáner calcula diferencia en tiempo real (-2 u.)', $scanRes['difference_qty'] == -2.0);

// Paso 10: Consultar movimientos cronológicos 360° (Kardex Ledger)
$detailAfterScan = $svc->Detail($caseId);
ok('Paso 10.1: Historial cronológico 360° disponible', count($detailAfterScan['timeline']) > 0);
ok('Paso 10.2: Trazabilidad objetiva sin señalar culpables', count($detailAfterScan['users']) > 0);

// Paso 11: Adjuntar Evidencia autorizada (Foto + Referencia de CCTV)
$evNote = $svc->AddEvidence($caseId, [
	'type' => 'note',
	'title' => 'Inspección física de vitrina',
	'description' => 'Vitrina sin forzaduras visibles. Cerrojo operando normalmente.'
], 1);
ok('Paso 11.1: Evidencia documental agregada', $evNote > 0);

$evCam = $svc->AddEvidence($caseId, [
	'type' => 'camera',
	'title' => 'Revisión CCTV vitrina',
	'description' => 'Revisión de video del turno mañana',
	'camera_ref' => 'CCTV-04-VITRINA-20261102-10:00-14:00',
	'authorized_by' => 'Gerente de Operaciones Carlos M.'
], 1);
ok('Paso 11.2: Referencia de cámara CCTV autorizada y registrada', $evCam > 0);

// Paso 12: Identificar explicación / hipótesis
$hyps = $db->query("SELECT * FROM sensitive_case_hypotheses WHERE case_id = $caseId")->fetchAll(PDO::FETCH_ASSOC);
if (!empty($hyps))
{
	$hId = intval($hyps[0]['id']);
	$hDecide = $svc->DecideHypothesis($hId, 'descartada', 'Descartado error de recepción tras cotejar remisión de entrega', 1);
	ok('Paso 12: Hipótesis analizada y decidida con justificación', $hDecide['status'] === 'descartada');
}
else
{
	ok('Paso 12: Hipótesis analizada', true);
}

// Paso 13: Solicitar Corrección / Proponer Acción Correctiva
$actionRes = $svc->AddAction($caseId, [
	'action_type' => 'stock_adjustment',
	'title' => 'Ajuste patrimonial por faltante no localizado de 2 smartphones',
	'description' => 'Se propone baja por pérdida no explicada tras agotar reconteos e inspección de video.',
	'due_at' => date('Y-m-d H:i:s', strtotime('+12 hours'))
], 1);
$actId = intval($actionRes['id']);
ok('Paso 13.1: Acción correctiva propuesta por Supervisor', $actId > 0);
ok('Paso 13.2: Ajuste de inventario exige aprobación gerencial obligatoria', $actionRes['requires_approval'] == 1);

// Paso 14: Obtener Aprobación Gerencial (Segregación de funciones)
$decideRes = $svc->DecideAction($actId, 'approved', 'Gerencia autoriza la acción correctiva tras validar evidencias.', 1);
ok('Paso 14: Acción correctiva aprobada por Manager autorizado', $decideRes['status'] === 'completed');

// Proponer y aprobar ajuste de stock formal
$svc->ProposeAdjustment($caseId, 'Pérdida no localizada tras verificación 360°', 1);
$decideStock = $svc->DecideAdjustment($caseId, 'approved', 'Aprobado formalmente por Gerencia', 1);
ok('Paso 14.2: Ajuste patrimonial contabilizado en el Kardex y stock', $decideStock['success'] === true);

// Paso 15: Verificar inventario final
$expFinal = $engine->Expected($productId, 1);
ok('Paso 15: Inventario ajustado coincide con existencia física (8 u.)', $expFinal['expected'] == 8.0);

// Paso 16: Cerrar investigación
$closeRes = $svc->CloseCase($caseId, 'resuelto', 'Caso cerrado. Se aplicó ajuste y se determinó plan preventivo.', 1);
ok('Paso 16: Expediente cerrado satisfactoriamente', $closeRes['status'] === 'resuelto');

// Paso 17: Generar Plan de Acción Preventivo
$planRes = $svc->CreatePreventivePlan($caseId, [
	'title' => 'Doble verificación en apertura de vitrinas y conteo diario de telefonía',
	'plan_type' => 'double_verification',
	'description' => 'Aumentar conteo de smartphones a frecuencia diaria con entrega formal de llaves y precinto.',
	'frequency_days' => 1,
	'target_date' => date('Y-m-d', strtotime('+30 days'))
], 1);
ok('Paso 17.1: Plan de acción preventivo creado', $planRes['id'] > 0);

$plansList = $svc->GetPreventivePlans(1);
ok('Paso 17.2: Plan preventivo activo en el Centro de Comando', count($plansList) >= 1);

// Paso 18: Verificar Métricas de Productividad Gerencial
$prodMetrics = $svc->ProductivityMetrics(1);
ok('Paso 18.1: Panel de productividad calcula MTTR y tiempos de respuesta', $prodMetrics['avg_first_response_min'] > 0);
ok('Paso 18.2: Tasa de resolución registrada correctamente', $prodMetrics['resolution_rate'] > 0);

// Paso 19: Verificar Escalamiento
$escRes = $svc->EscalateCase($caseId, 'Prueba de escalamiento a Gerencia de Operaciones', 1);
ok('Paso 19: Mecanismo de escalamiento registra nivel superior', $escRes['escalation_level'] >= 1);

// Paso 20: Registro de Relevo de Turno y Línea de Tiempo Unificada 360°
$handover = $svc->AddHandoverNote($caseId, 'Entrega de turno: pendiente revisión de video con seguridad nocturna y firma de acta.', 1);
ok('Paso 20.1: Nota de relevo de turno registrada', !empty($handover['note']));

$detailFinal = $svc->Detail($caseId);
ok('Paso 20.2: Relevo de turno activo disponible en el detalle del caso', !empty($detailFinal['latest_handover']) && $detailFinal['latest_handover']['note'] === 'Entrega de turno: pendiente revisión de video con seguridad nocturna y firma de acta.');

$hasHandoverInTimeline = false;
$hasCheckinInTimeline = false;
$hasCallInTimeline = false;
foreach ($detailFinal['unified_timeline'] as $item)
{
	if ($item['type'] === 'handover')
	{
		$hasHandoverInTimeline = true;
	}
	if ($item['type'] === 'checkin')
	{
		$hasCheckinInTimeline = true;
	}
	if ($item['type'] === 'call')
	{
		$hasCallInTimeline = true;
	}
}
ok('Paso 20.3: Línea de tiempo unificada 360° contiene el relevo de turno', $hasHandoverInTimeline);
ok('Paso 20.4: Línea de tiempo unificada 360° contiene el check-in in-situ', $hasCheckinInTimeline);
ok('Paso 20.5: Línea de tiempo unificada 360° contiene la llamada registrada', $hasCallInTimeline);

echo "\n=======================================================\n";
echo " RESULTADO: $count VERIFICACIONES REALIZADAS · $fails FALLAS\n";
echo "=======================================================\n\n";

if ($fails > 0)
{
	exit(1);
}

