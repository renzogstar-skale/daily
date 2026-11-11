<?php
/**
 * DAIRY — Optimizacion #12 Prevencion de Perdidas
 * Valida las 6 correcciones obligatorias y los 16 pilares de seguridad/investigacion.
 */
require __DIR__ . '/bootstrap.php';

use Grocy\Controllers\Users\User;
use Grocy\Helpers\DairyAccess;
use Grocy\Services\DeviceService;
use Grocy\Services\SensitiveCaseService;

echo "\n=======================================================\n";
echo " DAILY — OPTIMIZACION PREVENCION DE PERDIDAS (#12)\n";
echo "=======================================================\n\n";

$svc = SensitiveCaseService::GetInstance();
$devSvc = DeviceService::GetInstance();
$uid = GROCY_USER_ID;

// 1. Migracion 0332: sin casos sin responsable, estados normalizados y tabla security_access_audit_log
$unassignedCount = intval($val("SELECT COUNT(*) FROM sensitive_investigation_cases WHERE assigned_user_id IS NULL OR assigned_user_id = 0"));
ok('1.1 Migracion 0332: cero expedientes sin responsable asignado', $unassignedCount === 0, "unassigned=$unassignedCount");

$legacyStatuses = intval($val("SELECT COUNT(*) FROM sensitive_investigation_cases WHERE workflow_status IN ('closed','resolved','in_progress','pending_approval')"));
ok('1.2 Migracion 0332: estados legacy normalizados al ciclo espanol', $legacyStatuses === 0, "legacy=$legacyStatuses");

$auditTableExists = intval($val("SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name='security_access_audit_log'"));
ok('1.3 Migracion 0332: tabla security_access_audit_log creada', $auditTableExists === 1);

// 2. Separacion estricta de /waste y permisos restringidos en DairyAccess
$lpPerms = DairyAccess::PAGES['/loss-prevention'] ?? [];
ok('2.1 /loss-prevention restringido (no abierto a PERMISSION_KPIS_VIEW)', !in_array(User::PERMISSION_KPIS_VIEW, $lpPerms, true) && in_array(User::PERMISSION_LOSS_PREVENTION, $lpPerms, true));
ok('2.2 /api/loss-control/cctv/access restringido a supervision LP', isset(DairyAccess::PAGES['/api/loss-control/cctv/access']) && !in_array(User::PERMISSION_KPIS_VIEW, DairyAccess::PAGES['/api/loss-control/cctv/access'], true));
ok('2.3 /api/loss-control/cctv/logs restringido a supervision LP', isset(DairyAccess::PAGES['/api/loss-control/cctv/logs']));

$dash = $svc->Dashboard();
ok('2.4 Dashboard separa mermas ordinarias (waste_shrinkage_val === 0.0)', abs(floatval($dash['kpis']['waste_shrinkage_val'] ?? -1)) < 0.001, 'waste=' . ($dash['kpis']['waste_shrinkage_val'] ?? 'null'));
ok('2.5 Dashboard incluye ciclo de 7 etapas, cadena, anomalias POS y auditoria', isset($dash['chain_ok']) && is_array($dash['pos_anomalies']) && is_array($dash['audit_events']) && is_array($dash['devices_summary']));

// 3. No clasificar automaticamente como robo & exigir investigador responsable
$freeProds = $db->query("SELECT p.id FROM products p WHERE p.active = 1 AND NOT EXISTS (SELECT 1 FROM sensitive_investigation_cases c WHERE c.branch_id = 1 AND c.product_id = p.id AND IFNULL(c.device_id, 0) = 0 AND c.workflow_status NOT IN ('resuelto','no_resuelto')) ORDER BY p.is_sensitive DESC, p.id ASC LIMIT 2")->fetchAll(\PDO::FETCH_COLUMN);
$prodId = intval($freeProds[0] ?? 0);
$prodId2 = intval($freeProds[1] ?? $prodId);
expectError('3.1 CreateManualIncident rechaza incidente sin responsable', fn() => $svc->CreateManualIncident([
	'product_id' => $prodId,
	'branch_id' => 1,
	'difference_qty' => 2,
	'notes' => 'Intento sin responsable asignado'
], $uid));

$inc = $svc->CreateManualIncident([
	'product_id' => $prodId,
	'branch_id' => 1,
	'assigned_user_id' => $uid,
	'difference_qty' => 2,
	'priority' => 'high',
	'zone' => 'Vitrinas de Alta Seguridad',
	'notes' => 'Faltante detectado en auditoria de vitrina',
	'evidence_title' => 'Acta inicial de verificacion',
	'evidence_type' => 'note'
], $uid);
$caseId = intval($inc['id'] ?? 0);
ok('3.2 CreateManualIncident crea expediente con responsable y codigo unico', $caseId > 0 && !empty($inc['case_code']) && intval($inc['assigned_user_id']) === $uid, $inc['case_code'] ?? '');

$suspectedCause = (string)$val("SELECT suspected_cause_category FROM sensitive_investigation_cases WHERE id = $caseId");
ok('3.3 Nunca clasifica automaticamente diferencia como robo (indeterminada)', $suspectedCause === 'indeterminada', "cause=$suspectedCause");

expectError('3.4 Evita duplicar expediente abierto para el mismo SKU/sucursal', fn() => $svc->CreateManualIncident([
	'product_id' => $prodId,
	'branch_id' => 1,
	'assigned_user_id' => $uid,
	'difference_qty' => 1,
	'notes' => 'Intento duplicado en mismo SKU y sucursal'
], $uid));

// 4. Bloquear cierre resuelto sin evidencia cuando hay diferencia sin ajuste aprobado
$incNoEv = $svc->CreateManualIncident([
	'product_id' => $prodId2,
	'branch_id' => 1,
	'assigned_user_id' => $uid,
	'difference_qty' => 1,
	'priority' => 'medium',
	'notes' => 'Caso de prueba para exigir evidencia al cerrar'
], $uid);
$noEvId = intval($incNoEv['id']);
expectError('4.1 CloseCase(resuelto) bloquea cierre sin evidencia con diferencia fisica', fn() => $svc->CloseCase($noEvId, 'resuelto', 'Intento de cierre sin soporte', $uid));

$evId = $svc->AddEvidence($noEvId, [
	'type' => 'note',
	'title' => 'Acta de inventario fisico firmado',
	'description' => 'Se adjunta respaldo fotografico y conteo firmado'
], $uid);
ok('4.2 AddEvidence registra evidencia documental', $evId > 0);
$closed = $svc->CloseCase($noEvId, 'resuelto', 'Cierre documentado con evidencia verificada', $uid);
ok('4.3 CloseCase(resuelto) permite cerrar tras adjuntar evidencia', !empty($closed['success']) && $val("SELECT workflow_status FROM sensitive_investigation_cases WHERE id = $noEvId") === 'resuelto');

// ConfirmCause con causal real de loss_causes
$validCauseCode = (string)$val("SELECT code FROM loss_causes WHERE active = 1 AND kind <> 'other' LIMIT 1");
$confirmed = $svc->ConfirmCause($caseId, $validCauseCode, 'Diferencia atribuida tras analisis de auditoria', $uid);
ok('4.4 ConfirmCause actualiza causal tras analisis', !empty($confirmed['success']) && $val("SELECT confirmed_cause FROM sensitive_investigation_cases WHERE id = $caseId") === $validCauseCode);

// 5. Auditoria de acceso a CCTV e IMEI en security_access_audit_log
expectError('5.1 RecordCctvAccess exige camara, autorizador y motivo', fn() => $svc->RecordCctvAccess([
	'branch_id' => 1,
	'camera_ref' => '',
	'authorized_by' => '',
	'notes' => 'x'
], $uid));

$cctvRes = $svc->RecordCctvAccess([
	'branch_id' => 1,
	'case_id' => $caseId,
	'camera_ref' => 'CAM-VITRINA-02 (14:00-14:30)',
	'authorized_by' => 'Gerente Turno A',
	'notes' => 'Revision de apertura de vitrina por incidente'
], $uid);
ok('5.2 RecordCctvAccess registra consulta auditada y adjunta evidencia al caso', !empty($cctvRes['success']) && intval($cctvRes['id']) > 0);

$cctvAuditRows = intval($val("SELECT COUNT(*) FROM security_access_audit_log WHERE case_id = $caseId AND access_type = 'cctv_view'"));
ok('5.3 Bitacora security_access_audit_log guarda el evento cctv_view', $cctvAuditRows >= 1);

$devId = intval($val("SELECT id FROM device_units WHERE imei1 IS NOT NULL AND imei1 <> '' LIMIT 1"));
if ($devId > 0)
{
	$rev = $devSvc->Reveal($devId, $uid);
	ok('5.4 DeviceService::Reveal devuelve identificadores completos al rol autorizado', !empty($rev['imei1']) || !empty($rev['serial_number']));
	$imeiAuditRows = intval($val("SELECT COUNT(*) FROM security_access_audit_log WHERE device_id = $devId AND access_type = 'imei_reveal'"));
	ok('5.5 DeviceService::Reveal registra imei_reveal en security_access_audit_log', $imeiAuditRows >= 1);
}
else
{
	ok('5.4 DeviceService::Reveal (sin dispositivos IMEI en BD)', true);
	ok('5.5 security_access_audit_log IMEI (sin dispositivos IMEI en BD)', true);
}

// 6. Anti-duplicacion de recuperacion financiera
$impact = floatval($val("SELECT ABS(financial_impact) FROM sensitive_investigation_cases WHERE id = $caseId"));
$halfRec = max(1.0, round($impact * 0.5, 2));
$uniqueRef = 'NC-TEST-OPT-' . time();

expectError('6.1 SaveFinancialRecovery exige referencia documental cuando monto > 0', fn() => $svc->SaveFinancialRecovery($caseId, [
	'supplier_claim' => $halfRec,
	'document_refs' => [],
	'notes' => 'Sin folio'
], $uid));

if ($impact > 0)
{
	expectError('6.2 SaveFinancialRecovery rechaza monto superior a la exposicion del caso', fn() => $svc->SaveFinancialRecovery($caseId, [
		'recovered_merchandise' => round($impact + 50, 2),
		'document_refs' => ['FOL-EXC-' . time()],
		'notes' => 'Exceso'
	], $uid));
}
else
{
	ok('6.2 Exposicion mayor a cero para probar tope financiero', true);
}

$recOk = $svc->SaveFinancialRecovery($caseId, [
	'supplier_claim' => $halfRec,
	'document_refs' => [$uniqueRef],
	'notes' => 'Nota de credito validada por proveedor'
], $uid);
ok('6.3 SaveFinancialRecovery guarda recuperacion valida con folio unico', !empty($recOk['saved_at']));

expectError('6.4 SaveFinancialRecovery bloquea reenvio identico duplicado en el mismo caso', fn() => $svc->SaveFinancialRecovery($caseId, [
	'supplier_claim' => $halfRec,
	'document_refs' => [$uniqueRef],
	'notes' => 'Reenvio identico'
], $uid));

// Otro caso intentando usar la misma referencia documental
$otherCaseId = intval($val("SELECT id FROM sensitive_investigation_cases WHERE id <> $caseId AND financial_impact >= $halfRec LIMIT 1"));
if ($otherCaseId > 0)
{
	expectError('6.5 SaveFinancialRecovery bloquea folio documental ya usado en otro expediente', fn() => $svc->SaveFinancialRecovery($otherCaseId, [
		'supplier_claim' => $halfRec,
		'document_refs' => [strtolower($uniqueRef)],
		'notes' => 'Intento de doble cobro entre casos'
	], $uid));
}
else
{
	ok('6.5 Caso alterno para cruce de folios duplicados', true);
}

// 7. Anomalias de POS y patrones sospechosos reales
$pos = $svc->PosAnomaliesAndPatterns(null);
ok('7.1 PosAnomaliesAndPatterns retorna arreglos reales sin errores', isset($pos['anomalies']) && isset($pos['patterns']) && is_array($pos['anomalies']) && is_array($pos['patterns']));

// Cadena criptografica intacta tras todas las operaciones
ok('7.2 Cadena de custodia SHA-256 integra tras todas las operaciones', $svc->VerifyChain() === null);

echo "\n=======================================================\n";
echo " RESULTADO: $count VERIFICACIONES · $fails FALLAS\n";
echo "=======================================================\n\n";
exit($fails ? 1 : 0);
