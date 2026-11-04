<?php

// DAILY — Control integral: libro único, consolidación, casos por categoría, causas, reapertura, bloqueos y alertas

require __DIR__ . '/bootstrap.php';

use Grocy\Services\AlertCenterService;
use Grocy\Services\DeviceService;
use Grocy\Services\LossReconciliationService;
use Grocy\Services\MerchandiseService;
use Grocy\Services\ReconciliationService;
use Grocy\Services\SalesService;
use Grocy\Services\SensitiveCaseService;
use Grocy\Services\StockService;
use Grocy\Services\StockTransferService;

$m = MerchandiseService::GetInstance();
$engine = LossReconciliationService::GetInstance();
$flow = SensitiveCaseService::GetInstance();
$rec = ReconciliationService::GetInstance();
$one = fn(string $sql) => $db->query($sql)->fetch(PDO::FETCH_ASSOC);
$caseByCode = fn(string $c) => $one("SELECT * FROM sensitive_investigation_cases WHERE case_code = '$c'");
$qu = intval($val('SELECT qu_id_stock FROM products WHERE id = 9001'));
$loc = intval($val('SELECT location_id FROM products WHERE id = 9001'));
$loc2 = intval($val("SELECT id FROM locations WHERE branch_id = 1 AND IFNULL(is_transit, 0) = 0 AND storage_class = 'ambient' ORDER BY id DESC LIMIT 1"));
$toLoc = intval($val("SELECT id FROM locations WHERE branch_id = 2 AND IFNULL(is_transit, 0) = 0 AND storage_class = 'ambient' ORDER BY id LIMIT 1"));
$customer = intval($val('SELECT id FROM customers ORDER BY id LIMIT 1'));
$next = 9300;
$make = function (float $units, float $cost = 10.0, array $extra = []) use ($db, $loc, $qu, &$next) {
	$id = $next++;
	$cols = ['id' => $id, 'name' => "Prueba M $id", 'active' => 1, 'location_id' => $loc, 'qu_id_purchase' => $qu, 'qu_id_stock' => $qu, 'min_stock_amount' => 0, 'default_best_before_days' => 365, 'sku' => "M-$id", 'sale_price' => 20, 'department' => 'General', 'storage_class' => 'ambient'] + $extra;
	$db->prepare('INSERT INTO products (' . implode(', ', array_keys($cols)) . ') VALUES (' . implode(', ', array_fill(0, count($cols), '?')) . ')')->execute(array_values($cols));
	if ($units > 0)
	{
		$tid = null;
		StockService::GetInstance()->AddProduct($id, $units, date('Y-m-d', strtotime('+1 year')), StockService::TRANSACTION_TYPE_PURCHASE, date('Y-m-d'), $cost, $loc, null, $tid, 0, true);
	}
	return $id;
};

echo "\n1. Catálogo y tipo de control\n";
ok('celular por serie', $val('SELECT control_type FROM products WHERE id = 9003') === 'serial');
ok('leche por lote', $val('SELECT control_type FROM products WHERE id = 9004') === 'lot');
ok('carne por peso', $val('SELECT control_type FROM products WHERE id = 9006') === 'weight');
ok('sensible = riesgo alto', intval($val("SELECT COUNT(*) FROM products WHERE is_sensitive = 1 AND risk_level <> 'high' AND id < 9000")) === 0);
ok('todo producto tiene tipo válido', intval($val("SELECT COUNT(*) FROM products WHERE control_type NOT IN ('quantity', 'lot', 'serial', 'weight', 'pack')")) === 0);
ok('categorías del catálogo universal', intval($val("SELECT COUNT(*) FROM product_groups WHERE name IN ('Televisores', 'Ropa', 'Calzado', 'Repuestos')")) === 4);

echo "\n2. Libro único: una fila por movimiento, igual al motor\n";
ok('el libro tiene las mismas filas que stock_log', intval($val('SELECT COUNT(*) FROM merchandise_movements')) === intval($val("SELECT COUNT(*) FROM stock_log sl JOIN locations l ON l.id = sl.location_id WHERE sl.transaction_type <> 'product-opened'")));
$mismatch = 0;
foreach ($db->query("SELECT DISTINCT m.product_id, m.branch_id FROM merchandise_movements m WHERE m.is_transit = 0 ORDER BY RANDOM() LIMIT 60")->fetchAll(PDO::FETCH_ASSOC) as $r)
{
	$php = array_filter(array_map(fn($b) => round($b, 2), $engine->Expected(intval($r['product_id']), intval($r['branch_id']))['buckets']));
	$sql = [];
	foreach ($db->query("SELECT bucket, ROUND(SUM(amount), 2) AS q FROM merchandise_movements WHERE undone = 0 AND is_transit = 0 AND product_id = {$r['product_id']} AND branch_id = {$r['branch_id']} GROUP BY bucket") as $x)
	{
		if ($x['bucket'] !== 'internal' && floatval($x['q']) != 0)
		{
			$sql[$x['bucket']] = floatval($x['q']);
		}
	}
	ksort($php);
	ksort($sql);
	$mismatch += $php == $sql ? 0 : 1;
}
ok('vista SQL y motor PHP clasifican igual (60 productos al azar)', $mismatch === 0, "$mismatch distintos");
ok('el libro no se usa para escribir: es una vista', $val("SELECT type FROM sqlite_master WHERE name = 'merchandise_movements'") === 'view');

echo "\n3. Sin doble conteo al consolidar\n";
$p = $make(10);
$today = date('Y-m-d');
$before = $m->Totals(['from' => $today, 'to' => $today]);
$bBefore = $m->Totals(['from' => $today, 'to' => $today, 'branch_id' => 1]);
$v0 = $m->InventoryValue(null);
StockService::GetInstance()->TransferProduct($p, 3, $loc, $loc2);
$t1 = $m->Totals(['from' => $today, 'to' => $today, 'product_id' => $p, 'branch_id' => 1]);
ok('movimiento interno no es entrada ni salida', $t1['exits']['transfers_out']['units'] == 0 && $t1['internal']['units'] == 0);
$tr = StockTransferService::GetInstance()->CreateTransfer(['from_location_id' => $loc, 'to_location_id' => $toLoc, 'items' => [['product_id' => $p, 'amount' => 2]]], 1);
StockTransferService::GetInstance()->ReceiveTransfer($tr['id'], ['items' => [['product_id' => $p, 'received_amount' => 2]]], 1);
$co = $m->Totals(['from' => $today, 'to' => $today, 'product_id' => $p]);
ok('empresa: el traslado no aparece en entradas ni salidas', !isset($co['entries']['transfers_in']) && !isset($co['exits']['transfers_out']));
ok('empresa: traslado informado una vez', $co['transfers']['units'] == 2, json_encode($co['transfers']));
ok('sucursal origen: salida', $m->Totals(['from' => $today, 'to' => $today, 'product_id' => $p, 'branch_id' => 1])['exits']['transfers_out']['units'] == 2);
ok('sucursal destino: entrada', $m->Totals(['from' => $today, 'to' => $today, 'product_id' => $p, 'branch_id' => 2])['entries']['transfers_in']['units'] == 2);
ok('el valor de la empresa no cambia al trasladar', abs($m->InventoryValue(null) - $v0) < 0.01);
ok('entradas de la empresa = compra original', $co['entries']['receptions']['units'] == 10);

echo "\n4. Salidas con motivo, sin duplicar reintentos\n";
$sale = SalesService::GetInstance()->CreateSale(['branch_id' => 1, 'customer_id' => $customer, 'payment_method' => 'cash', 'items' => [['product_id' => $p, 'amount' => 2, 'unit_price' => 20, 'qu_id' => $qu]]]);
$w = \Grocy\Services\WasteService::GetInstance();
$reason = intval($val('SELECT id FROM waste_reasons WHERE active = 1 AND requires_evidence = 0 AND requires_approval = 0 ORDER BY id LIMIT 1'));
$w->ReportWaste($p, 1, $reason, 1, null, 'default', 'Prueba', null, null, null, null, null, null, 'idem-merch-1');
$w->ReportWaste($p, 1, $reason, 1, null, 'default', 'Prueba', null, null, null, null, null, null, 'idem-merch-1');
$t = $m->Totals(['from' => $today, 'to' => $today, 'product_id' => $p, 'branch_id' => 1]);
ok('venta y merma separadas', $t['exits']['sales']['units'] == 2 && $t['exits']['waste']['units'] == 1, json_encode($t['exits']));
$book = $m->Book(['product_id' => $p, 'branch_id' => 1]);
ok('cada movimiento lleva código, usuario y documento', $book[0]['movement_code'] && $book[0]['username'] !== null);
ok('la venta muestra su documento', count(array_filter($book, fn($x) => $x['bucket'] === 'sales' && $x['document'] === $sale->sale_number)) >= 1);
SalesService::GetInstance()->CancelSale(intval($sale->id));
$t = $m->Totals(['from' => $today, 'to' => $today, 'product_id' => $p, 'branch_id' => 1]);
ok('cancelar es un reverso: la venta sigue en el libro y entra la devolución', $t['exits']['sales']['units'] == 2 && $t['entries']['returns']['units'] == 2);
$e = $engine->Expected($p, 1);
ok('fórmula = existencia real', abs($e['expected'] - $e['on_hand']) < 0.001 && $e['book_gap'] == 0, json_encode($e['buckets']));

echo "\n5. Casos por categoría\n";
$d1 = $caseByCode('DEMO-001'); $d2 = $caseByCode('DEMO-002'); $d3 = $caseByCode('DEMO-003'); $d5 = $caseByCode('DEMO-005');
ok('Caso 1 celulares 8 → 6', floatval($d1['difference_qty']) == -2);
ok('Caso 2 audífonos 10 → 8', floatval($d2['difference_qty']) == -2);
ok('Caso 3 leche 80 → 74', $d3 && floatval($d3['expected_stock']) == 80 && floatval($d3['physical_count']) == 74 && floatval($d3['difference_qty']) == -6 && $d3['workflow_status'] === 'en_investigacion');
$tv = $one("SELECT * FROM sensitive_investigation_cases WHERE product_id = 9005");
ok('Caso 4 televisores: un expediente con su serie', $tv && !empty($tv['device_id']) && floatval($tv['difference_qty']) == -1);
ok('Caso 4 stock 12 y un equipo no localizado', $engine->OnHand(9005, 1) == 12 && intval($val("SELECT COUNT(*) FROM device_units WHERE product_id = 9005 AND state = 'not_located'")) === 1);
ok('Caso 5 peso 120 → 116,5 kg', $d5 && floatval($d5['expected_stock']) == 120 && floatval($d5['physical_count']) == 116.5 && abs(floatval($d5['difference_qty']) + 3.5) < 0.001, $d5['difference_qty'] ?? '');
ok('peso: valor por kg', abs(floatval($d5['financial_impact']) - 29.75) < 0.01, $d5['financial_impact'] ?? '');
ok('peso: excede la tolerancia del 1 %', floatval($val('SELECT count_tolerance_pct FROM products WHERE id = 9006')) == 1);
$px = $make(100, 2, ['count_tolerance_pct' => 2]);
$rt = $flow->RegisterCount(1, $px, 98.5, [], 1);
ok('dentro de tolerancia: registra, sin expediente', $rt['within_tolerance'] === true && $rt['case_id'] === null && abs($rt['difference_qty'] + 1.5) < 0.001);
ok('el conteo de televisores no toca a los demás celulares', intval($val("SELECT COUNT(*) FROM device_units WHERE product_id = 9003 AND state = 'not_located'")) === 2);
ok('ninguno se clasifica como robo', intval($val("SELECT COUNT(*) FROM sensitive_investigation_cases WHERE confirmed_cause = 'RIN'")) === 0);

echo "\n6. Clasificación de diferencias\n";
ok('pendiente de reconteo', MerchandiseService::Classify($d2) === 'Pendiente de reconteo');
ok('no justificada', MerchandiseService::Classify($d3) === 'Diferencia no justificada');
ok('positiva', MerchandiseService::Classify(['difference_qty' => 3, 'workflow_status' => 'en_investigacion']) === 'Diferencia positiva');
ok('explicada por causa', MerchandiseService::Classify(['difference_qty' => -3, 'workflow_status' => 'en_investigacion', 'confirmed_cause' => 'CERR']) === 'Diferencia explicada');
ok('conciliado', MerchandiseService::Classify(['difference_qty' => 0]) === 'Inventario conciliado');
$pr = $make(5);
$r = $flow->RegisterCount(1, $pr, 7, [], 1);
ok('sobrante abre expediente positivo', $r['difference_qty'] == 2 && str_contains($val("SELECT title FROM dairy_alerts WHERE id = {$r['alert_id']}"), 'Excedente'));

echo "\n7. Causas confirmadas y Mermas\n";
$c3 = intval($d3['id']);
expectError('causa exige fundamento', fn() => $flow->ConfirmCause($c3, 'EXP', '', 1));
expectError('hurto exige evidencia autorizada', fn() => $flow->ConfirmCause($c3, 'RIN', 'Sospecha', 1));
expectError('causa inexistente', fn() => $flow->ConfirmCause($c3, 'x', 'y', 1));
$wasteBefore = floatval($val('SELECT IFNULL(SUM(total_cost), 0) FROM waste_records'));
$stockBefore = $engine->OnHand(9004, 1);
$flow->ConfirmCause($c3, 'EXP', 'Retiradas vencidas sin registrar', 1);
ok('explicada al confirmar la causa', MerchandiseService::Classify($one("SELECT * FROM sensitive_investigation_cases WHERE id = $c3")) === 'Diferencia explicada');
ok('la diferencia aún no es merma', abs(floatval($val('SELECT IFNULL(SUM(total_cost), 0) FROM waste_records')) - $wasteBefore) < 0.001);
$flow->ProposeAdjustment($c3, 'Baja por vencimiento', 1);
$rec->AuthorizeAdjustment($c3, 'approved', 'Aprobado', 1);
$after = floatval($val('SELECT IFNULL(SUM(total_cost), 0) FROM waste_records'));
ok('al aprobar aparece en Mermas al costo del Kardex', abs($after - $wasteBefore - 6.9) < 0.01, $after - $wasteBefore);
ok('el stock baja una sola vez', $engine->OnHand(9004, 1) == $stockBefore - 6);
$e = $engine->Expected(9004, 1);
ok('fórmula cuadra: sin doble descuento por el registro de merma', $e['buckets']['adjustments'] == -6 && $e['buckets']['waste'] == 0 && $e['expected'] == 74, json_encode($e['buckets']));

echo "\n8. Reapertura\n";
expectError('reabrir exige evidencia nueva', fn() => $flow->Reopen($c3, '', 1));
$flow->Reopen($c3, 'Aparecieron facturas de devolución', 1);
$c = $one("SELECT * FROM sensitive_investigation_cases WHERE id = $c3");
ok('vuelve a investigación', $c['workflow_status'] === 'en_investigacion' && intval($c['reopened_count']) === 1 && $c['closed_at'] === null);
ok('la alerta se reactiva, sin crear otra', in_array($val("SELECT status FROM dairy_alerts WHERE id = {$c['alert_id']}"), AlertCenterService::ACTIVE, true) && intval($val("SELECT COUNT(*) FROM dairy_alerts WHERE product_id = 9004")) === 1);
ok('queda en la bitácora', intval($val("SELECT COUNT(*) FROM sensitive_case_events WHERE case_id = $c3 AND action = 'reopened'")) === 1 && $flow->VerifyChain() === null);
expectError('abierto no se reabre', fn() => $flow->Reopen($c3, 'Otra evidencia', 1));

echo "\n9. Rutas que saltaban el IMEI\n";
expectError('traslado genérico bloqueado', fn() => StockTransferService::GetInstance()->CreateTransfer(['from_location_id' => $loc, 'to_location_id' => $toLoc, 'items' => [['product_id' => 9003, 'amount' => 1]]], 1));
expectError('merma genérica bloqueada', fn() => $w->ReportWaste(9003, 1, $reason, 1));
expectError('aviso correcto', function () { DeviceService::AssertNotControlled(9003, 'recibirlo'); });
ok('producto común no se afecta', DeviceService::AssertNotControlled(30, 'x') === null);

echo "\n10. Alertas nuevas\n";
$pp = $make(5);
foreach ([1, 2] as $i)
{
	$r = $flow->RegisterCount(1, $pp, 4 + 0 * $i, [], 1);
	$flow->Recount($r['case_id'], 5, 'Conforme', 1);
}
foreach ([1, 2, 3] as $i)
{
	StockService::GetInstance()->InventoryProduct($pp, 5 - $i, date('Y-m-d', strtotime('+1 year')), $loc, 10);
}
$db->exec("UPDATE dairy_alert_rules SET last_run_at = NULL");
AlertCenterService::GetInstance()->Scan(1, null);
$AlertCount = fn(string $rule) => intval($val("SELECT COUNT(*) FROM dairy_alerts WHERE rule_code = '$rule' AND status <> 'closed'"));
ok('diferencias repetidas', $AlertCount('recurrent_difference') >= 1);
ok('ajustes repetidos', $AlertCount('repeated_adjustments') >= 1);
$n = $AlertCount('recurrent_difference');
AlertCenterService::GetInstance()->Scan(1, null);
ok('el barrido no duplica', $AlertCount('recurrent_difference') === $n);
ok('no nombra personas', !str_contains((string) $val("SELECT detail FROM dairy_alerts WHERE rule_code = 'recurrent_difference' LIMIT 1"), 'empleado'));

echo "\n11. Dashboard coherente\n";
$dash = $m->Dashboard(['from' => date('Y-m-d', strtotime('-30 days')), 'to' => $today, 'dim' => 'branch']);
$sum = $flow->Summary();
ok('valor en investigación = resumen de Prevención', abs($dash['open_value'] - $sum['open_value']) < 0.01, $dash['open_value'] . ' vs ' . $sum['open_value']);
ok('expedientes abiertos iguales', $dash['open_cases'] === $sum['open_cases']);
$valStock = floatval($val('SELECT SUM(s.amount * IFNULL(s.price, 0)) FROM stock s JOIN locations l ON l.id = s.location_id WHERE IFNULL(l.is_transit, 0) = 0'));
ok('valor de inventario = tabla stock', abs($dash['inventory_value'] - $valStock) < 0.01);
ok('mermas = libro', abs($dash['waste_confirmed']['value'] - floatval($val("SELECT IFNULL(SUM(value), 0) FROM merchandise_movements WHERE bucket = 'waste' AND undone = 0 AND is_transit = 0 AND date(effective_at) BETWEEN '" . date('Y-m-d', strtotime('-30 days')) . "' AND '$today'"))) < 0.01);
foreach (array_keys(MerchandiseService::DIMS) as $dim)
{
	ok("comparar por $dim", is_array($engine->DifferencesBy($dim)));
}
$pos = $m->Position(['branch_id' => 1, 'product_id' => 9002, 'from' => $today, 'to' => $today]);
ok('un producto responde cuánto recibió, debería y encontró', $pos['product']['formula']['buckets']['receptions'] == 10 && $pos['product']['formula']['expected'] == 10 && floatval($pos['product']['last_count']['actual_amount']) == 8);
ok('separa físico, disponible, reservado, bloqueado y tránsito', isset($pos['product']['positions']['blocked'], $pos['product']['positions']['available'], $pos['product']['positions']['reserved']));

echo "\n$count verificaciones · $fails FALLAS\n";
exit($fails ? 1 : 0);
