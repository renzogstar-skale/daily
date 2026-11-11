<?php
/**
 * Suite de verificación integral: #11 OPTIMIZAR MERMAS
 * Valida las 6 correcciones de integridad y los 18 requisitos funcionales sobre SQLite/Services reales.
 */

use Grocy\Services\DatabaseService;
use Grocy\Services\StockService;
use Grocy\Services\WasteExecutiveControlCenterService;
use Grocy\Services\WasteService;

require __DIR__ . '/bootstrap.php';

$pass = 0;
$fail = 0;
$check = function (bool $ok, string $label) use (&$pass, &$fail) {
	if ($ok)
	{
		$pass++;
		echo "  [OK] $label\n";
	}
	else
	{
		$fail++;
		echo "  [FAIL] $label\n";
	}
};

$ws = WasteService::GetInstance();
$execSvc = WasteExecutiveControlCenterService::GetInstance();
$stockSvc = StockService::GetInstance();
$raw = DatabaseService::GetInstance()->GetDbConnectionRaw();

echo "=== VERIFICACIÓN DE INTEGRIDAD Y FUNCIONALIDAD DE MERMAS ===\n\n";

// 1. Integridad DB existente (0 pendientes con inventory_deducted=1 o sin transaction_id)
$badPending = intval($raw->query("SELECT COUNT(*) FROM waste_records WHERE status = 'pending_approval' AND inventory_deducted = 1")->fetchColumn());
$check($badPending === 0, "Cero registros pendientes marcados incorrectamente como descontados ($badPending)");

$approvedMissingTx = intval($raw->query("SELECT COUNT(*) FROM waste_records WHERE status = 'approved' AND TRIM(IFNULL(transaction_id, '')) = '' AND date(created_timestamp) >= '2026-11-01'")->fetchColumn());
$check($approvedMissingTx === 0, "Todas las mermas aprobadas recientes tienen transaction_id ($approvedMissingTx sin tx)");

// 2. Separación estricta: Merma física real vs Ajustes contables y exclusión de seguridad en UI de mermas
$reasonsNoSec = $ws->GetWasteReasons(true, null, null, null, true);
$secFound = array_filter($reasonsNoSec, fn($r) => in_array($r->censo_category ?? '', ['robo_interno', 'robo_externo'], true) || in_array(strtoupper($r->code ?? ''), ['M03', 'M04', 'FPO01', 'FEL01'], true));
$check(count($secFound) === 0, 'GetWasteReasons(..., excludeSecurityReasons=true) excluye causales de hurto/seguridad');

$execAll = $execSvc->GetExecutiveData(null, date('Y-m-d'), null, null, 'pct', date('Y-m-01'), date('Y-m-d'));
$check(isset($execAll['kpis']['total_waste']['raw_cost'], $execAll['kpis']['total_waste']['units']), 'KPI Merma Real expone monto ($) y unidades');
$check(isset($execAll['kpis']['adjustments']['raw_cost'], $execAll['kpis']['procedural_errors'], $execAll['reduction_metrics']), 'Ajustes separados, errores de procedimiento e indicadores de reducción presentes');

// Donut suma solo merma física real (sin ajustes)
$donutSum = array_sum(array_map(fn($d) => floatval($d['amount']), $execAll['donut_types']['items'] ?? []));
$realTotal = floatval($execAll['kpis']['total_waste']['raw_cost'] ?? 0);
$check(abs($donutSum - $realTotal) < 0.05, "Desglose físico ($donutSum) coincide con Merma Real Total ($realTotal), excluyendo Ajustes Contables");

// Evolución sin benchmarks inventados cuando un año no tiene datos
$evoPrev = $execAll['evolution']['pct']['previous'] ?? [];
$check(!in_array(1.88, $evoPrev, true) && !in_array(1.92, $evoPrev, true) && !in_array(1.97, $evoPrev, true), 'Evolución histórica libre de benchmarks sintéticos');

// 3. Bloqueo de registro sin causal válida o con causal inactiva
$threwInvalidReason = false;
try
{
	$ws->ReportWaste(30, 1.0, 0, 1);
}
catch (\Throwable $e)
{
	$threwInvalidReason = true;
}
$check($threwInvalidReason, 'ReportWaste rechaza registro sin causal válida (reason_id=0)');

$inactReasonId = intval($raw->query('SELECT id FROM waste_reasons WHERE active = 0 LIMIT 1')->fetchColumn());
if ($inactReasonId > 0)
{
	$threwInactive = false;
	try
	{
		$ws->ReportWaste(30, 1.0, $inactReasonId, 1);
	}
	catch (\Throwable $e)
	{
		$threwInactive = str_contains($e->getMessage(), 'inactiva');
	}
	$check($threwInactive, 'ReportWaste rechaza causal inactiva');
}
else
{
	// Crear y desactivar causal temporal para validar
	$raw->exec("INSERT INTO waste_reasons (code, name, category, active) VALUES ('TMP_INACT', 'Temporal Inactiva', 'Operativa', 0)");
	$tmpId = intval($raw->lastInsertId());
	$threwInactive = false;
	try
	{
		$ws->ReportWaste(30, 1.0, $tmpId, 1);
	}
	catch (\Throwable $e)
	{
		$threwInactive = str_contains($e->getMessage(), 'inactiva');
	}
	$raw->exec("DELETE FROM waste_reasons WHERE id = $tmpId");
	$check($threwInactive, 'ReportWaste rechaza causal inactiva');
}

// 4. Prueba transaccional completa: producto con stock + causal con evidencia obligatoria -> bloqueo sin evidencia -> aprobación descuenta inventario y adjunta evidencia
$raw->beginTransaction();
$prodId = 30;
$branchId = 1;
$locId = intval($raw->query("SELECT id FROM locations WHERE branch_id = $branchId AND active = 1 AND IFNULL(is_transit,0) = 0 ORDER BY id LIMIT 1")->fetchColumn());
$evReasonId = intval($raw->query("SELECT id FROM waste_reasons WHERE active = 1 AND requires_evidence = 1 AND IFNULL(censo_category,'') NOT IN ('robo_interno','robo_externo') LIMIT 1")->fetchColumn());
$check($evReasonId > 0 && $locId > 0, "Existen ubicación ($locId) y causal con evidencia obligatoria ($evReasonId)");

$threwMissingEv = false;
try
{
	$ws->ReportWaste($prodId, 1.0, $evReasonId, $branchId, $locId, 'default', 'Prueba sin evidencia');
}
catch (\Throwable $e)
{
	$threwMissingEv = str_contains($e->getMessage(), 'evidencia');
}
$check($threwMissingEv, 'ReportWaste bloquea registro cuando causal exige evidencia y falta fotografía');

$raw->rollBack();

// Prueba E2E con transacción real controlada (rollback/limpieza explícita después porque StockService usa sus propios commits si no está anidado)
$stockBefore = floatval($raw->query("SELECT IFNULL(SUM(amount), 0) FROM stock WHERE product_id = $prodId AND location_id = $locId")->fetchColumn());
$stockSvc->AddProduct($prodId, 5.0, date('Y-m-d', strtotime('+5 days')), StockService::TRANSACTION_TYPE_PURCHASE, null, 100.0, $locId);
$stockAdded = floatval($raw->query("SELECT IFNULL(SUM(amount), 0) FROM stock WHERE product_id = $prodId AND location_id = $locId")->fetchColumn());
$check(abs(($stockBefore + 5.0) - $stockAdded) < 0.001, 'Stock agregado para prueba atómica de merma');

$evPhoto = '/api/waste/photo/waste_test_' . uniqid() . '.jpg';
$uniqueAmount = 2.0;

// Registro con evidencia que cae en pending_approval ($200 >= $50 min approval cost)
$created = $ws->ReportWaste($prodId, $uniqueAmount, $evReasonId, $branchId, $locId, 'default', 'Prueba evidencia + doble rol', $evPhoto, null, null, null, null, null, 'test-opt-' . uniqid(), 1);
$check($created->status === 'pending_approval' && intval($created->inventory_deducted) === 0, 'Merma >= umbral nace pending_approval y NO descuenta stock todavía');

// Duplicado inmediato rechazado
$threwDup = false;
try
{
	$ws->ReportWaste($prodId, $uniqueAmount, $evReasonId, $branchId, $locId, 'default', 'Duplicado', $evPhoto, null, null, null, null, null, null, 1);
}
catch (\Throwable $e)
{
	$threwDup = str_starts_with($e->getMessage(), 'Posible duplicado:');
}
$check($threwDup, 'Control anti-duplicados detecta envío repetido dentro de la ventana');

// Aprobar por usuario distinto (#2) descuenta inventario y guarda transaction_id
$approverId = intval($raw->query("SELECT id FROM users WHERE id != 1 ORDER BY id LIMIT 1")->fetchColumn()) ?: 2;
$approved = $ws->ApproveWaste(intval($created->id), $approverId);
$stockAfterApprove = floatval($raw->query("SELECT IFNULL(SUM(amount), 0) FROM stock WHERE product_id = $prodId AND location_id = $locId")->fetchColumn());
$check(
	$approved->status === 'approved' && intval($approved->inventory_deducted) === 1 && !empty($approved->transaction_id),
	'ApproveWaste marca approved, inventory_deducted=1 y genera transaction_id real'
);
$check(abs(($stockAdded - $uniqueAmount) - $stockAfterApprove) < 0.001, 'ApproveWaste descuenta exactamente la cantidad de inventario');

// Probar bloqueo de aprobación cuando una fila pendiente exige evidencia y la foto fue borrada/nula
$createdNoEv = $ws->ReportWaste($prodId, 1.0, $evReasonId, $branchId, $locId, 'default', 'Test guard aprobación evidencia', $evPhoto, null, null, null, null, null, 'test-opt-ev2-' . uniqid(), 1, false, true);
$raw->prepare("UPDATE waste_records SET evidence_photo_path = NULL WHERE id = :id")->execute([':id' => $createdNoEv->id]);
$threwApproveEv = false;
try
{
	$ws->ApproveWaste(intval($createdNoEv->id), $approverId);
}
catch (\Throwable $e)
{
	$threwApproveEv = str_contains($e->getMessage(), 'evidencia');
}
$check($threwApproveEv, 'ApproveWaste bloquea autorización si falta evidencia obligatoria');

// Adjuntar evidencia vía AttachEvidence y luego aprobar
$attached = $ws->AttachEvidence(intval($createdNoEv->id), $evPhoto, 1);
$check(!empty($attached->evidence_photo_path), 'AttachEvidence adjunta fotografía al registro existente');
$approved2 = $ws->ApproveWaste(intval($createdNoEv->id), $approverId);
$check($approved2->status === 'approved' && intval($approved2->inventory_deducted) === 1, 'Tras adjuntar evidencia, ApproveWaste autoriza y descuenta stock');

// Restaurar inventario exacto y limpiar registros de prueba
foreach ([$approved, $approved2] as $rec)
{
	$tx = $rec->transaction_id;
	$raw->prepare("DELETE FROM waste_action_logs WHERE waste_record_id = :id")->execute([':id' => $rec->id]);
	$raw->prepare("DELETE FROM workforce_store_tasks WHERE source_type = 'waste' AND source_id = :id")->execute([':id' => $rec->id]);
	$raw->prepare("DELETE FROM waste_records WHERE id = :id")->execute([':id' => $rec->id]);
	if ($tx)
	{
		$raw->prepare("DELETE FROM stock_log WHERE transaction_id = :tx")->execute([':tx' => $tx]);
	}
}
$raw->prepare("DELETE FROM stock WHERE product_id = :p AND location_id = :l AND amount = 2.0 OR best_before_date = :d")
	->execute([':p' => $prodId, ':l' => $locId, ':d' => date('Y-m-d', strtotime('+5 days'))]);
$raw->prepare("DELETE FROM stock_log WHERE product_id = :p AND location_id = :l AND amount = 5.0 AND transaction_type = 'purchase'")
	->execute([':p' => $prodId, ':l' => $locId]);

echo "\nRESULTADO: $pass pasadas, $fail fallidas\n";
exit($fail > 0 ? 1 : 0);

