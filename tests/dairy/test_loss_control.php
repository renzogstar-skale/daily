<?php

// DAILY — Motor de conciliación, expedientes, alertas sin duplicar, conteos sensibles, permisos y auditoría

require __DIR__ . '/bootstrap.php';

use Grocy\Services\AlertCenterService;
use Grocy\Services\CustomerOrderService;
use Grocy\Services\LossReconciliationService;
use Grocy\Services\ReconciliationService;
use Grocy\Services\SalesService;
use Grocy\Services\SensitiveCaseService;
use Grocy\Services\StockService;

$engine = LossReconciliationService::GetInstance();
$cases = SensitiveCaseService::GetInstance();
$rec = ReconciliationService::GetInstance();
$one = fn(string $sql) => $db->query($sql)->fetch(PDO::FETCH_ASSOC);

// Producto aislado para cada escenario: no lo toca la simulación de ventas
$loc = intval($val("SELECT id FROM locations WHERE branch_id = 1 AND IFNULL(is_transit, 0) = 0 AND storage_class = 'ambient' ORDER BY id LIMIT 1"));
$loc2 = intval($val("SELECT id FROM locations WHERE branch_id = 1 AND IFNULL(is_transit, 0) = 0 AND storage_class = 'ambient' ORDER BY id DESC LIMIT 1"));
$qu = intval($val('SELECT qu_id_stock FROM products WHERE id = 175'));
$nextId = 9100;
$make = function (float $units, float $cost = 100.0, int $sensitive = 1) use ($db, $loc, $qu, &$nextId) {
	$id = $nextId++;
	$db->prepare("INSERT INTO products (id, name, active, location_id, qu_id_purchase, qu_id_stock, min_stock_amount, default_best_before_days, sku, sale_price, is_sensitive, department, is_demo, storage_class)
		VALUES (?, ?, 1, ?, ?, ?, 0, 365, ?, 200, ?, 'Electrónica y Telefonía', 0, 'ambient')")->execute([$id, "Prueba LP $id", $loc, $qu, $qu, "LP-$id", $sensitive]);
	$db->prepare('INSERT INTO product_barcodes (product_id, barcode, qu_id) VALUES (?, ?, ?)')->execute([$id, "LP$id", $qu]);
	if ($units > 0)
	{
		$tid = null;
		StockService::GetInstance()->AddProduct($id, $units, date('Y-m-d', strtotime('+1 year')), StockService::TRANSACTION_TYPE_PURCHASE, date('Y-m-d'), $cost, $loc, null, $tid, 0, true);
	}
	return $id;
};

echo "\n1. Stock esperado por fórmula\n";
$p = $make(10);
$exp = $engine->Expected($p, 1);
ok('recepción de 10 → esperado 10', $exp['expected'] == 10 && $exp['buckets']['receptions'] == 10);
ok('sin brecha contra la existencia real', $exp['book_gap'] == 0);
$customer = intval($val('SELECT id FROM customers ORDER BY id LIMIT 1'));
SalesService::GetInstance()->CreateSale(['branch_id' => 1, 'customer_id' => $customer, 'payment_method' => 'cash', 'items' => [['product_id' => $p, 'amount' => 3, 'unit_price' => 200, 'qu_id' => $qu]]]);
$exp = $engine->Expected($p, 1);
ok('venta POS clasificada una sola vez', $exp['buckets']['sales'] == -3 && $exp['buckets']['dispatches'] == 0, json_encode($exp['buckets']));
ok('esperado = existencia real', $exp['expected'] == 7 && $exp['on_hand'] == 7);

echo "\n2. Sin doble descuento venta + despacho\n";
$sold = $db->query("SELECT o.id FROM customer_orders o WHERE o.status = 'dispatched' AND o.sale_id IS NOT NULL LIMIT 1")->fetchColumn();
if ($sold)
{
	$r = $one("SELECT i.product_id, o.branch_id, i.amount FROM customer_order_items i JOIN customer_orders o ON o.id = i.order_id WHERE o.id = $sold LIMIT 1");
	$rows = $engine->Movements(intval($r['product_id']), intval($r['branch_id']));
	$tx = array_filter($rows, fn($m) => $m['order_code'] !== null);
	ok('pedido despachado aparece como venta, no además como despacho', count($tx) > 0 && !array_filter($tx, fn($m) => $m['bucket'] === 'dispatches'));
	$e = $engine->Expected(intval($r['product_id']), intval($r['branch_id']));
	ok('la fórmula cuadra con la existencia real en producto con pedidos', abs($e['expected'] - $e['on_hand']) < 0.001, $e['expected'] . ' vs ' . $e['on_hand']);
}
else
{
	ok('hay pedidos despachados para verificar', false);
}
$p2 = $make(5);
StockService::GetInstance()->TransferProduct($p2, 2, $loc, $loc2);
$e = $engine->Expected($p2, 1);
ok('traslado interno no cambia el total de la sucursal', $e['expected'] == 5 && $e['buckets']['transfers_out'] == 0, json_encode($e['buckets']));

echo "\n3. Posiciones\n";
$pos = $engine->Positions($p, 1);
ok('físico, disponible, reservado y tránsito separados', isset($pos['physical'], $pos['available'], $pos['reserved'], $pos['in_transit_out'], $pos['in_transit_in']));
ok('físico = 7', $pos['physical'] == 7);

echo "\n4. Detección, expediente y alerta únicos\n";
$pc = $make(8, 520);
$r = $cases->RegisterCount(1, $pc, 6, [], 1);
ok('diferencia -2 detectada', $r['difference_qty'] == -2 && $r['financial_impact'] == 1040);
$case = $one("SELECT * FROM sensitive_investigation_cases WHERE id = {$r['case_id']}");
ok('expediente en reconteo', $case['workflow_status'] === 'reconteo');
$alert = $one("SELECT * FROM dairy_alerts WHERE id = {$r['alert_id']}");
ok('alerta crítica por alto valor', $alert['severity'] === 'danger' && !empty($alert['due_at']));
ok('la alerta enlaza al expediente', str_contains($alert['href'], '/loss-prevention/case/' . $r['case_id']));
$r2 = $cases->RegisterCount(1, $pc, 6, [], 1);
ok('segundo conteo se suma al mismo expediente', $r2['case_id'] === $r['case_id']);
ok('no se duplica el expediente', intval($val("SELECT COUNT(*) FROM sensitive_investigation_cases WHERE product_id = $pc")) === 1);
ok('no se duplica la alerta', intval($val("SELECT COUNT(*) FROM dairy_alerts WHERE product_id = $pc")) === 1);
$scan = AlertCenterService::GetInstance()->Scan(1, null);
ok('el barrido no cierra la alerta del expediente', $val("SELECT status FROM dairy_alerts WHERE id = {$r['alert_id']}") !== 'closed');

echo "\n5. Escaneos repetidos y simultáneos\n";
$pr = $make(4);
$a = $cases->RegisterCount(1, $pr, 3, ['operation_id' => 'op-rep-1'], 1);
$b = $cases->RegisterCount(1, $pr, 3, ['operation_id' => 'op-rep-1'], 1);
ok('reintento con la misma operación no duplica', !empty($b['replayed']) && intval($val("SELECT COUNT(*) FROM sensitive_inventory_movements WHERE product_id = $pr AND event_type = 'cycle_count'")) === 1);
$pd = $make(4);
$db->exec("INSERT INTO sensitive_investigation_cases (case_code, branch_id, product_id, workflow_status) VALUES ('T-RACE', 1, $pd, 'reconteo')");
try
{
	$db->exec("INSERT INTO sensitive_investigation_cases (case_code, branch_id, product_id, workflow_status) VALUES ('T-RACE2', 1, $pd, 'detectado')");
	ok('el índice impide dos expedientes abiertos', false);
}
catch (\PDOException $e)
{
	ok('el índice impide dos expedientes abiertos', true);
}

echo "\n6. Reconteo y resolución\n";
$pz = $make(5);
$r = $cases->RegisterCount(1, $pz, 4, [], 1);
$res = $cases->Recount($r['case_id'], 5, 'Apareció en bodega', 1);
$c = $one("SELECT * FROM sensitive_investigation_cases WHERE id = {$r['case_id']}");
ok('reconteo conforme resuelve el expediente', $c['workflow_status'] === 'resuelto' && $c['resolution_type'] === 'reconteo_conforme');
ok('y cierra su alerta', $val("SELECT status FROM dairy_alerts WHERE id = {$r['alert_id']}") === 'closed');
ok('stock intacto', $engine->OnHand($pz, 1) == 5);

$py = $make(5, 80);
$r = $cases->RegisterCount(1, $py, 3, [], 1);
$cases->Recount($r['case_id'], 3, 'Ratifica', 1);
$cid = $r['case_id'];
ok('reconteo que ratifica pasa a investigación', $val("SELECT workflow_status FROM sensitive_investigation_cases WHERE id = $cid") === 'en_investigacion');
expectError('no se aprueba sin propuesta de supervisor', fn() => $rec->AuthorizeAdjustment($cid, 'approved', 'x', 1));
expectError('propuesta exige justificación', fn() => $cases->ProposeAdjustment($cid, '  ', 1));
$cases->ProposeAdjustment($cid, 'Faltante no ubicado', 1);
ok('pendiente de aprobación', $val("SELECT workflow_status FROM sensitive_investigation_cases WHERE id = $cid") === 'pendiente_aprobacion');
$rec->AuthorizeAdjustment($cid, 'rejected', 'Falta evidencia', 1);
ok('rechazo devuelve a investigación sin mover stock', $val("SELECT workflow_status FROM sensitive_investigation_cases WHERE id = $cid") === 'en_investigacion' && $engine->OnHand($py, 1) == 5);
$cases->ProposeAdjustment($cid, 'Faltante no ubicado', 1);
$rec->AuthorizeAdjustment($cid, 'approved', 'Aprobado', 1);
$c = $one("SELECT * FROM sensitive_investigation_cases WHERE id = $cid");
ok('aprobación ajusta stock una vez', $engine->OnHand($py, 1) == 3 && $c['workflow_status'] === 'resuelto' && $c['adjustment_status'] === 'approved');
$e = $engine->Expected($py, 1);
ok('el ajuste entra por la fórmula', $e['buckets']['adjustments'] == -2 && $e['expected'] == 3, json_encode($e['buckets']));
ok('kardex y libro lo registran', intval($val("SELECT COUNT(*) FROM dairy_movement_ledger WHERE product_id = $py AND transaction_type = 'inventory-correction'")) >= 1);
ok('sin nueva diferencia tras el ajuste', $cases->RegisterCount(1, $py, 3, [], 1)['difference_qty'] == 0);
expectError('estado terminal no retrocede', function () use ($cases, $one, $cid) {
	$cases->Transition($one("SELECT * FROM sensitive_investigation_cases WHERE id = $cid"), 'reconteo', 'x', 1);
});

echo "\n7. Sincronización retrasada\n";
$pl = $make(6);
$r = $cases->RegisterCount(1, $pl, 4, [], 1);
$lateCount = $val("SELECT count_at FROM sensitive_investigation_cases WHERE id = {$r['case_id']}");
$sale = SalesService::GetInstance()->CreateSale(['branch_id' => 1, 'customer_id' => $customer, 'payment_method' => 'cash', 'items' => [['product_id' => $pl, 'amount' => 2, 'unit_price' => 200, 'qu_id' => $qu]]]);
$saleId = intval($sale->id);
$after = date('Y-m-d H:i:s', strtotime($lateCount) + 300);
$db->prepare('UPDATE sales SET created_timestamp = ? WHERE id = ?')->execute([$after, $saleId]);
ok('sin hora real, la venta posterior al conteo no cambia lo esperado', $engine->Expected($pl, 1, null, $lateCount)['expected'] == 6);
$earlier = date('Y-m-d H:i:s', strtotime($lateCount) - 3600);
$db->prepare("INSERT INTO pos_external_sales (source, external_id, kind, branch_id, occurred_at, payload_json, payload_hash, status, sale_id) VALUES ('test', 'late1', 'sale', 1, ?, '{}', 'h', 'imported', ?)")->execute([$earlier, $saleId]);
ok('la venta tardía conserva su hora real', $engine->Expected($pl, 1, null, $lateCount)['expected'] == 4);
ok('reevaluación detecta el cambio', $cases->ReevaluateOpen(1) >= 1);
$c = $one("SELECT * FROM sensitive_investigation_cases WHERE id = {$r['case_id']}");
ok('diferencia justificada sin pérdida', $c['workflow_status'] === 'resuelto' && $c['resolution_type'] === 'justificada_sincronizacion' && floatval($c['financial_impact']) == 0);

$ps = $make(6);
$sku = "LP-$ps";
$db->prepare("INSERT INTO pos_external_sales (source, external_id, kind, branch_id, occurred_at, payload_json, payload_hash, status) VALUES ('test', 'pend1', 'sale', 1, ?, ?, 'h2', 'failed')")->execute([date('Y-m-d H:i:s'), json_encode(['items' => [['sku' => $sku, 'amount' => 2]]])]);
$r = $cases->RegisterCount(1, $ps, 4, [], 1);
$c = $one("SELECT * FROM sensitive_investigation_cases WHERE id = {$r['case_id']}");
ok('operación pendiente vuelve provisional la diferencia', intval($c['provisional']) === 1 && $r['provisional'] === true);
ok('alerta amarilla, no crítica', $val("SELECT severity FROM dairy_alerts WHERE id = {$r['alert_id']}") === 'warning');
$cases->Recount($r['case_id'], 4, 'Ratifica', 1);
expectError('no se propone pérdida con sincronización pendiente', fn() => $cases->ProposeAdjustment($r['case_id'], 'Pérdida', 1));
$db->exec("DELETE FROM pos_external_sales WHERE external_id = 'pend1'");

echo "\n8. Trazabilidad e hipótesis\n";
$d = $cases->Detail($cid);
foreach (['case', 'product', 'formula', 'positions', 'last_known', 'timeline', 'pos_sales', 'orders', 'transfers', 'returns_adjustments', 'counts', 'users', 'evidence', 'hypotheses', 'events'] as $k)
{
	ok("expediente incluye $k", array_key_exists($k, $d));
}
ok('línea de tiempo cronológica', count($d['timeline']) >= 3 && $d['timeline'][0]['at'] <= end($d['timeline'])['at']);
ok('último registro aclara que no implica responsabilidad', str_contains($d['last_known']['disclaimer'], 'no es responsable'));
$codes = array_column($d['hypotheses'], 'code');
ok('sin documento que la justifique queda como no explicada', in_array('unexplained', $codes, true));
$h = $d['hypotheses'][0];
expectError('decidir hipótesis exige evidencia', fn() => $cases->DecideHypothesis(intval($h['id']), 'descartada', '', 1));
$cases->DecideHypothesis(intval($h['id']), 'descartada', 'Revisado documento', 1);
ok('hipótesis decidida', $val("SELECT status FROM sensitive_case_hypotheses WHERE id = {$h['id']}") === 'descartada');
expectError('cámara exige autorización', fn() => $cases->AddEvidence($cid, ['type' => 'camera', 'title' => 'Video'], 1));
ok('referencia de cámara autorizada', $cases->AddEvidence($cid, ['type' => 'camera', 'title' => 'Cámara 3', 'camera_ref' => 'CAM-3 10:00', 'authorized_by' => 'Gerencia'], 1) > 0);
$cam = array_values(array_filter($cases->Integrations(), fn($i) => $i['name'] === 'Cámaras de seguridad'))[0];
ok('no declara integración de cámaras', $cam['state'] === 'no integrado');

echo "\n9. Conteo por tarea: UPC, serie, ciego\n";
$pt = $make(3);
$db->exec("UPDATE products SET is_serialized = 1 WHERE id = $pt");
$db->exec("INSERT INTO sensitive_count_tasks (branch_id, product_id, period_key, due_at, created_at) VALUES (1, $pt, 'T1', datetime('now', '+1 day'), datetime('now'))");
$tid = intval($db->lastInsertId());
$list = array_values(array_filter($cases->Tasks(1, 1), fn($t) => intval($t['id']) === $tid));
ok('tarea listada', count($list) === 1);
ok('conteo ciego: no expone lo esperado', !array_key_exists('expected_stock', $list[0]) && !array_key_exists('first_qty', $list[0]));
expectError('UPC ajeno rechazado', fn() => $cases->SubmitTaskCount($tid, ['barcode' => 'LP9100', 'qty' => 3], 1));
expectError('faltan series', fn() => $cases->SubmitTaskCount($tid, ['barcode' => "LP$pt", 'qty' => 3, 'serials' => ['A']], 1));
$t = $cases->SubmitTaskCount($tid, ['barcode' => "LP$pt", 'qty' => 2, 'serials' => ['A', 'B', 'B'], 'operation_id' => 'task-op-1'], 1);
ok('diferencia pide reconteo sin revelar lo esperado', $t['recount_required'] === true && !isset($t['expected_stock']));
$again = $cases->SubmitTaskCount($tid, ['barcode' => "LP$pt", 'qty' => 2, 'serials' => ['A', 'B'], 'operation_id' => 'task-op-1'], 1);
ok('escaneo repetido no duplica', !empty($again['replayed']));
$recountTask = intval($val("SELECT id FROM sensitive_count_tasks WHERE case_id = {$t['case_id']} AND status = 'recount'"));
ok('el expediente generó su tarea de reconteo', $recountTask > 0);
$t2 = $cases->SubmitTaskCount($recountTask, ['barcode' => "LP$pt", 'qty' => 3, 'serials' => ['A', 'B', 'C']], 1);
ok('reconteo conforme resuelve', $t2['recount_required'] === false && $val("SELECT workflow_status FROM sensitive_investigation_cases WHERE id = {$t['case_id']}") === 'resuelto');
expectError('tarea cerrada no se repite', fn() => $cases->SubmitTaskCount($tid, ['barcode' => "LP$pt", 'qty' => 3, 'serials' => ['A', 'B', 'C']], 1));

$catP = $make(1);
$catId = intval($val("SELECT id FROM sensitive_categories WHERE code = 'tablets'"));
$cases->ClassifyProduct($catP, $catId);
$planId = $cases->SavePlan(['branch_id' => 1, 'category_id' => $catId, 'frequency' => 'daily'], 1);
$n1 = intval($val("SELECT COUNT(*) FROM sensitive_count_tasks WHERE plan_id = $planId"));
$cases->GenerateTasks(1);
ok('plan genera tareas sin duplicar por periodo', $n1 >= 1 && intval($val("SELECT COUNT(*) FROM sensitive_count_tasks WHERE plan_id = $planId")) === $n1);
$db->exec("UPDATE sensitive_count_tasks SET due_at = datetime('now', '-1 day') WHERE plan_id = $planId");
AlertCenterService::GetInstance()->Scan(1, null);
ok('conteo vencido genera advertencia', intval($val("SELECT COUNT(*) FROM dairy_alerts WHERE rule_code = 'sensitive_count_overdue' AND status <> 'closed'")) >= 1);

echo "\n10. Bitácora inalterable\n";
ok('cadena íntegra', $cases->VerifyChain() === null);
expectError('no se puede editar', fn() => $db->exec("UPDATE sensitive_case_events SET note = 'x' WHERE id = 1"));
expectError('no se puede borrar', fn() => $db->exec('DELETE FROM sensitive_case_events WHERE id = 1'));
ok('registra quién y cuándo', intval($val('SELECT COUNT(*) FROM sensitive_case_events WHERE user_id IS NOT NULL AND created_at IS NOT NULL')) > 0);

echo "\n11. Casos de demostración\n";
$d1 = $one("SELECT * FROM sensitive_investigation_cases WHERE case_code = 'DEMO-001'");
$d2 = $one("SELECT * FROM sensitive_investigation_cases WHERE case_code = 'DEMO-002'");
ok('Caso 001 Hallandale: 8 → 6, investigación abierta', $d1 && intval($d1['branch_id']) === 1 && floatval($d1['expected_stock']) == 8 && floatval($d1['physical_count']) == 6 && floatval($d1['difference_qty']) == -2 && $d1['workflow_status'] === 'en_investigacion');
ok('Caso 002 Audífonos: 10 → 8, pendiente de reconteo', $d2 && floatval($d2['expected_stock']) == 10 && floatval($d2['physical_count']) == 8 && $d2['workflow_status'] === 'reconteo');
ok('identificados como ficticios', intval($d1['is_demo']) === 1 && intval($d2['is_demo']) === 1 && intval($val('SELECT is_demo FROM products WHERE id = 9001')) === 1);
$b1 = $engine->Expected(9001, 1);
ok('Caso 001 sin ventas, traslados ni bajas', $b1['buckets']['receptions'] == 8 && $b1['buckets']['sales'] == 0 && $b1['buckets']['transfers_out'] == 0 && $b1['buckets']['waste'] == 0, json_encode($b1['buckets']));
ok('expediente con responsable y fecha límite', !empty($d1['assigned_user_id']) && !empty($d1['due_at']));
ok('alerta roja y amarilla', $val("SELECT severity FROM dairy_alerts WHERE id = {$d1['alert_id']}") === 'danger' && $val("SELECT severity FROM dairy_alerts WHERE id = {$d2['alert_id']}") === 'warning');
$det = $cases->Detail(intval($d1['id']));
ok('valor estimado 2 × costo', floatval($d1['financial_impact']) == 1040);
ok('8 IMEI registrados en la sucursal', count($det['serials']['expected']) === 8, count($det['serials']['expected']));

echo "\n12. Una sola cifra en todos los módulos\n";
$s = $cases->Summary();
$sum = floatval($val("SELECT IFNULL(SUM(financial_impact), 0) FROM sensitive_investigation_cases WHERE workflow_status NOT IN ('resuelto', 'no_resuelto')"));
ok('resumen = suma de expedientes abiertos', abs($s['open_value'] - $sum) < 0.01);
$dash = $cases->Dashboard(null);
ok('dashboard usa el mismo resumen', abs($dash['summary']['open_value'] - $s['open_value']) < 0.01);
ok('dashboard completo', count($dash['cases']) > 0 && isset($dash['kpis']['monitored'], $dash['trend'], $dash['by_branch'], $dash['by_product'], $dash['by_department']));
foreach (['branch', 'department', 'sku', 'location', 'date', 'shift', 'user', 'lot', 'serial'] as $dim)
{
	ok("diferencias por $dim", is_array($engine->DifferencesBy($dim)));
}
expectError('dimensión inválida', fn() => $engine->DifferencesBy('x'));

echo "\n$count verificaciones · $fails FALLAS\n";
exit($fails ? 1 : 0);
