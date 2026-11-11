<?php
require_once __DIR__ . '/bootstrap.php';

use Grocy\Services\MrpService;
use Grocy\Services\SupplierProcurementService;
use Grocy\Services\SupplierInvoiceService;

$mrpSvc = MrpService::GetInstance();
$supSvc = SupplierProcurementService::GetInstance();
$invSvc = SupplierInvoiceService::GetInstance();

echo "== 1. MRP, Stock Neto (no tránsito/reservas/merma/borradores), Confianza y Simulador ==\n";
$prodRow = $db->query("SELECT p.id, p.name FROM products p WHERE p.active = 1 AND EXISTS (SELECT 1 FROM stock s WHERE s.product_id = p.id AND s.amount > 0) LIMIT 1")->fetch(PDO::FETCH_ASSOC);
ok('producto activo con stock encontrado', (bool)$prodRow, $prodRow['name'] ?? '');
$pid = intval($prodRow['id']);

$baseMrp = $mrpSvc->CalculateMrp(1, 14);
$baseItem = null;
foreach ($baseMrp['items'] as $it)
{
	if (intval($it['product_id']) === $pid)
	{
		$baseItem = $it;
		break;
	}
}
ok('producto presente en MRP sucursal 1', $baseItem !== null);
ok('campos forecast_confidence y days_coverage_display expuestos', array_key_exists('forecast_confidence', $baseItem) && array_key_exists('days_coverage_display', $baseItem));

$db->exec("INSERT INTO locations (name, branch_id, storage_class, is_transit, active) VALUES ('Camión Tránsito Test', 1, 'refrigerado', 1, 1)");
$transitLocId = intval($db->lastInsertId());
$db->prepare("INSERT INTO stock (product_id, amount, location_id, stock_id, best_before_date, purchased_date, price) VALUES (:p, 100, :l, 'STK-TRANSIT-TST', date('now', '+20 days'), date('now'), 5.0)")
   ->execute([':p' => $pid, ':l' => $transitLocId]);

$afterTransitMrp = $mrpSvc->CalculateMrp(1, 14);
$afterTransitItem = null;
foreach ($afterTransitMrp['items'] as $it)
{
	if (intval($it['product_id']) === $pid)
	{
		$afterTransitItem = $it;
		break;
	}
}
ok('stock en ubicación is_transit=1 NO infla current_stock', abs($afterTransitItem['current_stock'] - $baseItem['current_stock']) < 0.001, "{$baseItem['current_stock']} vs {$afterTransitItem['current_stock']}");

$custId = intval($db->query("SELECT IFNULL(MIN(id), 1) FROM customers")->fetchColumn());
$db->prepare("INSERT INTO customer_orders (order_code, branch_id, customer_id, status, created_at) VALUES ('ORD-TST-RES', 1, :cid, 'confirmed', datetime('now'))")->execute([':cid' => $custId]);
$coId = intval($db->lastInsertId());
$db->prepare("INSERT INTO customer_order_items (order_id, product_id, amount, unit_price, picked_amount) VALUES (:oid, :pid, 3, 2.0, 0)")
   ->execute([':oid' => $coId, ':pid' => $pid]);
$quId = intval($db->query("SELECT qu_id_stock FROM products WHERE id = $pid")->fetchColumn() ?: 1);
$wrId = intval($db->query("SELECT IFNULL(MIN(id), 1) FROM waste_reasons")->fetchColumn());
$db->prepare("INSERT INTO waste_records (branch_id, product_id, amount, qu_id, unit_cost, total_cost, waste_reason_id, reported_by_user_id, status, inventory_deducted) VALUES (1, :pid, 2, :qid, 1.0, 2.0, :wrid, 1, 'pending_approval', 0)")
   ->execute([':pid' => $pid, ':qid' => $quId, ':wrid' => $wrId]);

$afterResMrp = $mrpSvc->CalculateMrp(1, 14);
$afterResItem = null;
foreach ($afterResMrp['items'] as $it)
{
	if (intval($it['product_id']) === $pid)
	{
		$afterResItem = $it;
		break;
	}
}
ok('reservas despacho + mermas pendientes calculadas en MRP', $afterResItem['reserved_stock'] >= 5.0 - 0.001, (string)$afterResItem['reserved_stock']);
ok('usable_stock descuenta reservas y mermas sin aplicar', $afterResItem['usable_stock'] <= $afterTransitItem['usable_stock'] - 5.0 + 0.001);

$branchForecast = $mrpSvc->GetBranchDemandForecast(14);
ok('pronóstico agregado por sucursal disponible', count($branchForecast) >= 1, count($branchForecast) . ' sucursales');

$sim = $mrpSvc->SimulateDemand(1, ['horizon_days' => 14, 'demand_multiplier' => 2.5, 'lead_time_delta' => 3, 'service_level' => 0.99]);
ok('simulador What-If recalcula demanda e inversión al alza', isset($sim['summary']['sim_investment']) && $sim['summary']['sim_investment'] >= $sim['summary']['base_investment'], $sim['summary']['base_investment'] . ' -> ' . $sim['summary']['sim_investment']);

echo "\n== 2. Anti-Duplicados, Idempotencia, Condiciones Comerciales, MOQ y Costos ==\n";
$unverifiedId = $supSvc->SaveSupplier([
	'name' => 'Proveedor Sin Terminos Test',
	'lead_time_days' => 0,
	'payment_terms' => '',
	'min_order_amount' => 0,
	'active' => 1
]);
expectError('bloquea orden con enforce_commercial_terms=true si proveedor carece de condiciones comerciales', function () use ($mrpSvc, $unverifiedId, $pid) {
	$mrpSvc->CreateProcurementOrder([
		'branch_id' => 1,
		'supplier_id' => $unverifiedId,
		'enforce_commercial_terms' => true,
		'items' => [['product_id' => $pid, 'amount' => 5, 'unit_price' => 2.0]]
	], 1);
});

$supSvc->SaveSupplier([
	'id' => $unverifiedId,
	'name' => 'Proveedor Completo Test',
	'lead_time_days' => 3,
	'payment_terms' => 'Crédito 30 días',
	'credit_days' => 30,
	'currency_code' => 'USD',
	'incoterm' => 'DDP',
	'min_order_amount' => 10,
	'active' => 1
]);
$priceId = $supSvc->AddPriceRecord([
	'supplier_id' => $unverifiedId,
	'product_id' => $pid,
	'price' => 4.0,
	'min_order_qty' => 6,
	'order_multiple' => 6,
	'delivery_lead_days' => 3,
	'valid_from' => date('Y-m-d')
]);
ok('cotización con MOQ y múltiplo guardada', $priceId > 0);

$ctrId = $supSvc->SaveContract([
	'supplier_id' => $unverifiedId,
	'title' => 'Contrato Marco Abasto Test',
	'start_date' => date('Y-m-d'),
	'end_date' => date('Y-m-d', strtotime('+180 days')),
	'payment_terms_days' => 30,
	'minimum_order_val' => 50,
	'discount_terms' => '2% pronto pago'
]);
$ctrs = $supSvc->GetContracts($unverifiedId);
ok('contrato comercial persistido y consultable', $ctrId > 0 && count($ctrs) === 1);

$idem = 'IDEM-TEST-' . uniqid();
$po1 = $mrpSvc->CreateProcurementOrder([
	'branch_id' => 1,
	'supplier_id' => $unverifiedId,
	'idempotency_key' => $idem,
	'freight_cost' => 12.0,
	'tax_cost' => 3.0,
	'discount_amount' => 3.0,
	'items' => [
		['product_id' => $pid, 'amount' => 6, 'unit_price' => 4.0],
		['product_id' => $pid, 'amount' => 6, 'unit_price' => 4.0],
	]
], 1);
$po1Dup = $mrpSvc->CreateProcurementOrder([
	'branch_id' => 1,
	'supplier_id' => $unverifiedId,
	'idempotency_key' => $idem,
	'items' => [['product_id' => $pid, 'amount' => 6, 'unit_price' => 4.0]]
], 1);
ok('idempotency_key evita creación duplicada de OC', intval($po1['id']) === intval($po1Dup['id']));
$po1Detail = $mrpSvc->GetProcurementOrderDetail(intval($po1['id']));
ok('líneas duplicadas del mismo SKU se consolidan en la OC', count($po1Detail['items']) === 1 && floatval($po1Detail['items'][0]['suggested_amount']) == 12.0);
ok('borrador mantiene approved_amount NULL hasta aprobación', $po1Detail['items'][0]['approved_amount'] === null);
ok('landed_unit_cost prorrota costos de adquisición ($4 base + $1 neto/u = $5)', abs(floatval($po1Detail['items'][0]['landed_unit_cost']) - 5.0) < 0.01, (string)$po1Detail['items'][0]['landed_unit_cost']);

expectError('impide enviar OC en estado borrador', fn() => $mrpSvc->SendProcurementOrder(intval($po1['id']), 1));
expectError('impide recibir OC sin aprobación previa', fn() => $mrpSvc->ReceiveProcurementOrder(intval($po1['id']), ['items' => [['order_item_id' => $po1Detail['items'][0]['id'], 'delivered_amount' => 1]]], 1));

$mrpSvc->UpdateDraftOrderItems(intval($po1['id']), [
	'freight_cost' => 6.0,
	'tax_cost' => 0.0,
	'other_acquisition_cost' => 0.0,
	'discount_amount' => 0.0,
	'items' => [['order_item_id' => $po1Detail['items'][0]['id'], 'amount' => 6, 'unit_price' => 4.0]]
], 1);
$po1DetailAfterEdit = $mrpSvc->GetProcurementOrderDetail(intval($po1['id']));
ok('edición de borrador actualiza cantidad y landed_unit_cost', floatval($po1DetailAfterEdit['items'][0]['suggested_amount']) == 6.0 && abs(floatval($po1DetailAfterEdit['items'][0]['landed_unit_cost']) - 5.0) < 0.01);

echo "\n== 3. Flujo Aprobación -> Envío -> Confirmación Proveedor -> Recepción Admin vs Física ==\n";
$mrpSvc->ApproveProcurementOrder(intval($po1['id']), 1);
$mrpSvc->SendProcurementOrder(intval($po1['id']), 1, date('Y-m-d', strtotime('+3 days')));
$conf = $mrpSvc->ConfirmProcurementOrder(intval($po1['id']), [
	'confirmed_by_supplier_contact' => 'Laura Gómez',
	'supplier_confirmation_ref' => 'CONF-7788',
	'confirmed_delivery_date' => date('Y-m-d', strtotime('+4 days')),
	'items' => [['order_item_id' => $po1Detail['items'][0]['id'], 'confirmed_amount' => 6]]
], 1);
ok('confirmación de proveedor mueve estado a confirmed', $conf['status'] === 'confirmed');

$futureBbd = date('Y-m-d', strtotime('+30 days'));
expectError('bloquea variación de precio >25% en recepción sin allow_price_override', fn() => $mrpSvc->ReceiveProcurementOrder(intval($po1['id']), [
	'items' => [['order_item_id' => $po1Detail['items'][0]['id'], 'delivered_amount' => 2, 'unit_price' => 8.0, 'best_before_date' => $futureBbd]]
], 1));

expectError('RecordDelivery impide recibir más unidades de las ordenadas', fn() => $supSvc->RecordDelivery([
	'supplier_id' => $unverifiedId,
	'branch_id' => 1,
	'expected_delivery_date' => date('Y-m-d'),
	'items_ordered' => 10,
	'items_received' => 12
]));

$stockBeforeAdmin = floatval($db->query("SELECT IFNULL(SUM(amount), 0) FROM stock WHERE product_id = $pid")->fetchColumn());
$adminRec = $mrpSvc->RecordAdministrativeReception(intval($po1['id']), [
	'supplier_document' => 'REM-ADMIN-01',
	'freight_allocated' => 4.0,
	'items' => [['order_item_id' => $po1Detail['items'][0]['id'], 'delivered_amount' => 4, 'rejected_amount' => 0, 'best_before_date' => $futureBbd]]
], 1);
$stockAfterAdmin = floatval($db->query("SELECT IFNULL(SUM(amount), 0) FROM stock WHERE product_id = $pid")->fetchColumn());
ok('recepción administrativa queda en etapa admin_recorded', $adminRec['reception_stage'] === 'admin_recorded');
ok('recepción administrativa NO modifica stock físico', abs($stockAfterAdmin - $stockBeforeAdmin) < 0.001, "$stockBeforeAdmin vs $stockAfterAdmin");

$physRec = $mrpSvc->ConfirmPhysicalReception(intval($adminRec['receipt_id']), 1);
$stockAfterPhys = floatval($db->query("SELECT IFNULL(SUM(amount), 0) FROM stock WHERE product_id = $pid")->fetchColumn());
ok('ConfirmPhysicalReception transiciona a physical_posted', $physRec['reception_stage'] === 'physical_posted');
ok('ConfirmPhysicalReception incrementa stock físico exactamente +4 u.', abs($stockAfterPhys - ($stockBeforeAdmin + 4.0)) < 0.001, "$stockBeforeAdmin -> $stockAfterPhys");

$invRes = $invSvc->RegisterInvoice(intval($po1['id']), [
	'invoice_number' => 'FAC-TST-100',
	'invoice_date' => date('Y-m-d'),
	'items' => [['order_item_id' => $po1Detail['items'][0]['id'], 'invoiced_amount' => 4, 'unit_price' => 4.0]]
], 1);
ok('conciliación 3 vías marca matched al coincidir OC, recepción y factura', $invRes['status'] === 'matched');

$pendingDels = $mrpSvc->GetPendingDeliveries(1);
$foundPending = false;
foreach ($pendingDels as $pd)
{
	if (intval($pd['id']) === intval($po1['id']) && abs(floatval($pd['pending_units']) - 2.0) < 0.01)
	{
		$foundPending = true;
	}
}
ok('GetPendingDeliveries reporta saldo parcial pendiente de 2 u.', $foundPending);

$history = $mrpSvc->GetSupplyHistory(1, $unverifiedId, 20);
ok('GetSupplyHistory registra trazabilidad completa de eventos', count($history) >= 4, count($history) . ' eventos');

$otherBranch = intval($db->query("SELECT id FROM branches WHERE active = 1 AND id != 1 LIMIT 1")->fetchColumn());
if ($otherBranch > 0)
{
	$locOther = intval($db->query("SELECT id FROM locations WHERE branch_id = $otherBranch AND IFNULL(is_transit,0) = 0 LIMIT 1")->fetchColumn());
	if ($locOther > 0)
	{
		$db->prepare("INSERT INTO stock (product_id, amount, location_id, stock_id, best_before_date, purchased_date, price) VALUES (:p, 200, :l, 'STK-SURPLUS-TST', date('now', '+60 days'), date('now'), 4.0)")
		   ->execute([':p' => $pid, ':l' => $locOther]);
		$trRecs = $mrpSvc->GetTransferRecommendations(1, 14);
		ok('GetTransferRecommendations calcula oportunidades inter-sucursal', is_array($trRecs), count($trRecs) . ' recomendaciones');
	}
}

echo "\nResumen: PASS=" . ($count - $fails) . " FAIL=$fails (Total=$count)\n";
exit($fails > 0 ? 1 : 0);
