<?php

// DAILY — Control de celulares por IMEI: registro, recepción, custodia, venta, pickup, devoluciones, conteos y escenarios A–G

require __DIR__ . '/bootstrap.php';

use Grocy\Services\AlertCenterService;
use Grocy\Services\CustomerOrderService;
use Grocy\Services\DeviceService;
use Grocy\Services\LossReconciliationService;
use Grocy\Services\ReconciliationService;
use Grocy\Services\SalesService;
use Grocy\Services\SensitiveCaseService;
use Grocy\Services\StockTransferService;

$dev = DeviceService::GetInstance();
$flow = SensitiveCaseService::GetInstance();
$engine = LossReconciliationService::GetInstance();
$sales = SalesService::GetInstance();
$one = fn(string $sql) => $db->query($sql)->fetch(PDO::FETCH_ASSOC);
$state = fn(string $i) => $dev->Find($i)['state'];
$qu = intval($val('SELECT qu_id_stock FROM products WHERE id = 9003'));
$loc = intval($val('SELECT location_id FROM products WHERE id = 9003'));
$customer = intval($val('SELECT id FROM customers ORDER BY id LIMIT 1'));
$alerts = fn(string $like) => intval($val("SELECT COUNT(*) FROM dairy_alerts WHERE dedupe_key LIKE '$like'"));
$dev->SetSystem(false);

// Producto y lote propios: sucursal 2 para no interferir con la demostración
$db->exec("INSERT INTO products (id, name, active, location_id, qu_id_purchase, qu_id_stock, min_stock_amount, default_best_before_days, sku, brand, sale_price, is_sensitive, is_serialized, imei_tracking, device_control, department, storage_class)
	VALUES (9200, 'iPhone Prueba 128', 1, $loc, $qu, $qu, 0, 900, 'TST-IP-128', 'Apple', 900, 1, 1, 1, 1, 'Electrónica y Telefonía', 'ambient')");
$seq = 500;
$imei = function () use (&$seq) { return DeviceService::DemoImei($seq++); };
$receive = function (int $n, int $branch = 1) use ($dev, $imei, $db) {
	$list = [];
	for ($i = 0; $i < $n; $i++)
	{
		$list[] = $imei();
	}
	$location = intval($db->query("SELECT id FROM locations WHERE branch_id = $branch AND IFNULL(is_transit, 0) = 0 AND IFNULL(active, 1) = 1 ORDER BY (storage_class = 'ambient') DESC, id LIMIT 1")->fetchColumn());
	$r = $dev->OpenReceipt(['product_id' => 9200, 'branch_id' => $branch, 'location_id' => $location, 'supplier_name' => 'Apple Dist', 'order_code' => 'OC-1', 'document' => 'FAC-1', 'unit_cost' => 700, 'manifest' => $list], 1);
	foreach ($list as $m)
	{
		$dev->ScanReceipt($r['id'], $m, 1);
	}
	$dev->CloseReceipt($r['id'], null, 1);
	return $list;
};
$sale = fn(array $imeis, int $branch = 1, array $extra = []) => $sales->CreateSale($extra + ['branch_id' => $branch, 'customer_id' => $customer, 'payment_method' => 'cash', 'items' => [['product_id' => 9200, 'amount' => count($imeis), 'unit_price' => 900, 'qu_id' => $qu, 'imeis' => $imeis]]]);

echo "\n1. Registro único\n";
ok('IMEI válido por Luhn', DeviceService::ValidImei('490154203237518'));
ok('IMEI inválido rechazado', !DeviceService::ValidImei('490154203237519') && !DeviceService::ValidImei('12345'));
ok('enmascarado deja solo 4 dígitos', DeviceService::Mask('490154203237518') === str_repeat('•', 11) . '7518');
$list = $receive(3);
$u = $dev->Find($list[0]);
ok('unidad con proveedor, OC, documento y costo', $u['supplier_name'] === 'Apple Dist' && $u['order_code'] === 'OC-1' && floatval($u['unit_cost']) == 700 && $u['receipt_id']);
ok('apunta a su entrada de stock', !empty($u['stock_id']));
ok('tres entradas distintas para tres unidades', intval($val('SELECT COUNT(DISTINCT stock_id) FROM device_units WHERE product_id = 9200')) === 3);
ok('el stock real sube', $engine->OnHand(9200, 1) == 3);
expectError('IMEI duplicado no se registra', fn() => $dev->OpenReceipt(['product_id' => 9200, 'branch_id' => 1, 'manifest' => [$list[0]]], 1));
expectError('manifiesto con IMEI inválido', fn() => $dev->OpenReceipt(['product_id' => 9200, 'branch_id' => 1, 'manifest' => ['123']], 1));

echo "\n2. Recepción con diferencias\n";
$m1 = $imei(); $m2 = $imei(); $ext = $imei();
$r = $dev->OpenReceipt(['product_id' => 9200, 'branch_id' => 1, 'location_id' => $loc, 'unit_cost' => 700, 'manifest' => [$m1, $m2]], 1);
ok('IMEI conforme', $dev->ScanReceipt($r['id'], $m1, 1)['result'] === 'matched');
ok('escaneo duplicado detectado', $dev->ScanReceipt($r['id'], $m1, 1)['result'] === 'duplicate');
ok('IMEI adicional detectado', $dev->ScanReceipt($r['id'], $ext, 1)['result'] === 'extra');
$s = $dev->ReceiptStatus($r['id']);
ok('faltante y adicional listados', count($s['missing']) === 1 && count($s['extra']) === 1 && $s['duplicates'] === 1);
expectError('no cierra sin justificación', fn() => $dev->CloseReceipt($r['id'], '', 1));
expectError('quien recibió no autoriza sus diferencias', fn() => $dev->CloseReceipt($r['id'], 'Proveedor enviará el faltante', 1));
$c = $dev->CloseReceipt($r['id'], 'Proveedor enviará el faltante', 2);
ok('cierra con diferencias, no como completa', $c['status'] === 'with_differences' && $c['registered'] === 1);
ok('solo entra lo conforme', $dev->Find($m2) === null && $dev->Find($ext) === null && $dev->Find($m1) !== null);

echo "\n3. Custodia\n";
$a = $list[0];
$mv = $dev->RequestMove($a, 'showcase', 1);
ok('la zona no cambia sin confirmación', $dev->Find($a)['zone'] === 'main' && $mv['high_risk'] === false);
expectError('segundo movimiento pendiente rechazado', fn() => $dev->RequestMove($a, 'floor', 1));
expectError('quien movió no confirma su propia recepción', fn() => $dev->ConfirmMove($mv['id'], true, 1));
$dev->ConfirmMove($mv['id'], true, 2);
$h = $one("SELECT * FROM device_events WHERE device_id = {$dev->Find($a)['id']} AND event = 'move_confirmed'");
ok('registra origen, destino, usuario, hora y confirmación', $dev->Find($a)['zone'] === 'showcase' && str_contains($h['note'], 'Vitrina') && $h['user_id'] && $h['confirmed_by_user_id'] && $h['created_at']);
ok('movimiento a bodega segura es de alto riesgo', $dev->RequestMove($list[1], 'secure', 1)['high_risk'] === true);
$dev->ConfirmMove(intval($val('SELECT MAX(id) FROM device_moves')), false, 2);
ok('rechazo conserva la zona', $dev->Find($list[1])['zone'] === 'main');

echo "\n4. Escenario B: vender dos veces el mismo IMEI\n";
expectError('producto controlado exige IMEI', fn() => $sales->CreateSale(['branch_id' => 1, 'customer_id' => $customer, 'payment_method' => 'cash', 'items' => [['product_id' => 9200, 'amount' => 1, 'unit_price' => 900, 'qu_id' => $qu]]]));
$first = $sale([$a]);
ok('venta vincula el IMEI', $dev->Find($a)['state'] === 'sold' && intval($dev->Find($a)['sale_id']) === intval($first->id));
ok('descuenta exactamente esa unidad', intval($val("SELECT COUNT(*) FROM stock WHERE stock_id = '{$dev->Find($a)['stock_id']}'")) === 0);
expectError('segunda venta bloqueada', fn() => $sale([$a]));
ok('alerta del intento', $alerts('device:sale:%already_sold') === 1);
expectError('repetir no duplica la alerta', fn() => $sale([$a]));
ok('sigue una sola alerta', $alerts('device:sale:%already_sold') === 1);
ok('stock no se descontó dos veces', $engine->OnHand(9200, 1) == 3);
expectError('IMEI inexistente', fn() => $sale([$imei()]));
$b = $list[1];
$dev->SetBlocked($b, true, 'Revisión interna', 1);
expectError('equipo bloqueado no se vende', fn() => $sale([$b]));
$dev->SetBlocked($b, false, 'Aclarado', 1);
$other = $receive(1, 2)[0];
expectError('equipo de otra sucursal bloqueado', fn() => $sale([$other], 1));
$db->exec("UPDATE device_rules SET value = 'pending' WHERE code = 'sale_exception_mode'");
try { $sale([$other], 1); ok('modo pendiente: debía quedar retenida', false); } catch (\Exception $e) { ok('modo pendiente retiene la venta', str_contains($e->getMessage(), 'pendiente de autorización')); }
$hold = intval($val("SELECT id FROM device_sale_holds WHERE status = 'pending' ORDER BY id DESC LIMIT 1"));
ok('la solicitud queda registrada', $hold > 0);
$dev->DecideHold($hold, false, 1);
$db->exec("UPDATE device_rules SET value = 'block' WHERE code = 'sale_exception_mode'");

echo "\n5. Escenario C: devolución con IMEI diferente\n";
$saleNo = $first->sale_number;
expectError('IMEI diferente rechazado', fn() => $dev->RegisterReturn($saleNo, $list[2], ['condition' => 'Bueno'], 1));
ok('alerta y equipo vendido intacto', $alerts('device:return:%') === 1 && $state($a) === 'sold');
ok('el equipo presentado no cambia', $state($list[2]) === 'available');
expectError('devolución exige condición', fn() => $dev->RegisterReturn($saleNo, $a, [], 1));
$ret = $dev->RegisterReturn($saleNo, $a, ['condition' => 'Pantalla sin rayas, cargador incluido'], 1);
ok('no vuelve a disponible', $state($a) === 'returned_review');
ok('no reincorpora stock', $engine->OnHand(9200, 1) == 3);
ok('aparece en el módulo de devoluciones', intval($val("SELECT COUNT(*) FROM customer_returns_exchanges WHERE return_code LIKE 'DEV-IMEI-%'")) === 1);
expectError('en revisión no se vende', fn() => $sale([$a]));
$dev->DecideReturn($ret['device_id'], true, 'Revisado por técnico', 1);
ok('aprobada: disponible y con stock', $state($a) === 'available' && $engine->OnHand(9200, 1) == 4);
$exp = $engine->Expected(9200, 1);
ok('entra por la fórmula como devolución', $exp['buckets']['returns'] == 1 && $exp['expected'] == 4, json_encode($exp['buckets']));
$again = $sale([$a]);
ok('se puede vender otra vez su nueva entrada', $state($a) === 'sold');

echo "\n6. Escenario E: pickup\n";
$pk = $receive(2);
$mk = function () use ($db, $customer) {
	$db->exec("INSERT INTO customer_orders (order_code, branch_id, customer_id, status, created_at) VALUES ('T-" . uniqid() . "', 1, $customer, 'draft', datetime('now'))");
	return intval($db->lastInsertId());
};
$o1 = $mk(); $o2 = $mk();
$dev->ReserveForOrder($o1, $pk[0], 1);
expectError('mismo IMEI no va a dos pedidos', fn() => $dev->ReserveForOrder($o2, $pk[0], 1));
expectError('reservado para pedido no se vende por caja', fn() => $sale([$pk[0]]));
$dev->ReserveForOrder($o2, $pk[1], 1);
expectError('entregar equipo de otro pedido', fn() => $dev->OrderScan($o2, 'picked', $pk[0], 1));
ok('sustitución alertada', $alerts('device:substitution:%') === 1);
expectError('no se salta etapas', fn() => $dev->OrderScan($o1, 'packed', $pk[0], 1));
$dev->OrderScan($o1, 'picked', $pk[0], 1);
$dev->OrderScan($o1, 'packed', $pk[0], 1);
ok('empaque lo deja en pickup', $dev->Find($pk[0])['zone'] === 'pickup');
expectError('entrega exige comprobante', fn() => $dev->OrderScan($o1, 'verified', $pk[0], 1));
$dev->OrderScan($o1, 'verified', $pk[0], 1, 'Firma del cliente');
$disp = $sales->CreateSale(['branch_id' => 1, 'customer_id' => $customer, 'payment_method' => 'cash', 'customer_order_id' => $o1, 'items' => [['product_id' => 9200, 'amount' => 1, 'unit_price' => 900, 'qu_id' => $qu]]]);
ok('despacho vende el IMEI reservado', $state($pk[0]) === 'sold' && $dev->Find($pk[0])['order_stage'] === 'delivered');
$dev->ReleaseOrder($o2, 1);
ok('cancelar libera la reserva', $state($pk[1]) === 'available');

echo "\n7. Escenario D: traslado sin confirmar\n";
$tr = $receive(1)[0];
$toLoc = intval($val("SELECT id FROM locations WHERE branch_id = 2 AND IFNULL(is_transit, 0) = 0 AND IFNULL(active, 1) = 1 ORDER BY (storage_class = 'ambient') DESC, id LIMIT 1"));
$before = $engine->OnHand(9200, 1);
$t = $dev->Transfer([$tr], $toLoc, 1);
ok('equipo en tránsito', $state($tr) === 'in_transit' && $t['transfer_id'] > 0, json_encode($t));
ok('sale del stock de origen', $engine->OnHand(9200, 1) == $before - 1);
ok('tránsito visible en la posición', $engine->Positions(9200, 1)['in_transit_out'] == 1);
expectError('en tránsito no se vende', fn() => $sale([$tr]));
$db->exec("UPDATE stock_transfers SET sent_at = datetime('now', '-3 days') WHERE id = {$t['transfer_id']}");
AlertCenterService::GetInstance()->Scan(1, null);
ok('alerta de traslado sin confirmar', intval($val("SELECT COUNT(*) FROM dairy_alerts WHERE rule_code = 'device_transfer_unconfirmed' AND status <> 'closed'")) === 1);
expectError('recepción sin escanear rechazada', fn() => $dev->ReceiveTransfer($t['transfer_id'], [], 1));
$dev->ReceiveTransfer($t['transfer_id'], [$tr], 1);
$u = $dev->Find($tr);
ok('recibido en destino', $u['state'] === 'available' && intval($u['branch_id']) === 2 && intval($u['location_id']) === $toLoc);
ok('programa conteo después del traslado', intval($val("SELECT COUNT(*) FROM device_counts WHERE reason = 'transfer' AND branch_id = 2")) === 1);
$sale([$tr], 2);
ok('se vende en la sucursal destino con su misma entrada', $state($tr) === 'sold');

echo "\n8. Escenario A: entran 8 y faltan 2\n";
$db->exec("UPDATE device_units SET state = 'sold' WHERE product_id = 9200 AND branch_id = 1 AND state = 'available' AND 1 = 0");
$br = 3;
$eight = $receive(8, $br);
$cnt = $dev->ScheduleCount($br, 'close', 'T-A', 1);
foreach (array_slice($eight, 0, 6) as $m)
{
	ok('escaneo individual', $dev->ScanCount($cnt, $m, 1)['result'] === 'ok');
}
ok('escaneo repetido no suma', $dev->ScanCount($cnt, $eight[0], 1)['result'] === 'duplicate');
$res = $dev->CloseCount($cnt, 1);
ok('identifica exactamente los 2 IMEI', $res['expected'] === 8 && $res['found'] === 6 && count($res['missing']) === 2 && $state($eight[6]) === 'not_located' && $state($eight[7]) === 'not_located');
ok('un expediente por equipo', count($res['cases']) === 2 && $res['cases'][0] !== $res['cases'][1]);
$c1 = $one("SELECT * FROM sensitive_investigation_cases WHERE id = {$res['cases'][0]}");
ok('en reconteo, sin clasificar como robo', $c1['workflow_status'] === 'reconteo' && $c1['suspected_cause_category'] === 'indeterminada' && str_contains($c1['evidence_notes'], 'No se clasifica como robo'));
$al = $one("SELECT * FROM dairy_alerts WHERE id = {$c1['alert_id']}");
ok('alerta con responsable, escalable', $al['rule_code'] === 'device_not_located' && !empty($al['owner_user_id']) && !empty($al['due_at']) && intval($val("SELECT escalate_after_hours FROM dairy_alert_rules WHERE code = 'device_not_located'")) > 0);
ok('revisa ventas, pickup y traslados', intval($val("SELECT COUNT(*) FROM sensitive_case_hypotheses WHERE case_id = {$c1['id']} AND code IN ('sale_check', 'pickup_check', 'transfer_check', 'last_zone', 'pending_move')")) === 5);
expectError('no localizado no se vende', fn() => $sale([$eight[6]], $br));
ok('stock contable intacto hasta aprobar ajuste', $engine->OnHand(9200, $br) == 8);
$second = $dev->ScheduleCount($br, 'prevention', 'T-A2', 1);
foreach (array_slice($eight, 0, 7) as $m)
{
	$dev->ScanCount($second, $m, 1);
}
$res2 = $dev->CloseCount($second, 1);
ok('segundo conteo recupera el que apareció', $res2['recovered'] === 1 && $state($eight[6]) === 'available');
$cid8 = $res['cases'][1];
$cid7 = $res['cases'][0];
$st = fn($id) => $val("SELECT workflow_status FROM sensitive_investigation_cases WHERE id = $id");
ok('uno resuelto y el otro en investigación', in_array('resuelto', [$st($cid7), $st($cid8)], true) && in_array('en_investigacion', [$st($cid7), $st($cid8)], true));

echo "\n9. Escenario F: aparece un equipo reportado\n";
$open = in_array($st($cid7), ['en_investigacion'], true) ? $cid7 : $cid8;
expectError('reporte exige dónde apareció', fn() => $dev->ReportFound($eight[7], 'floor', '', 1));
$dev->ReportFound($eight[7], 'service', 'Estaba en servicio técnico', 1);
ok('disponible en la zona real', $state($eight[7]) === 'available' && $dev->Find($eight[7])['zone'] === 'service');
ok('expediente resuelto y alerta cerrada', $st($open) === 'resuelto' && $val("SELECT status FROM dairy_alerts WHERE id = (SELECT alert_id FROM sensitive_investigation_cases WHERE id = $open)") === 'closed');
ok('sin pérdida', floatval($val("SELECT financial_impact FROM sensitive_investigation_cases WHERE id = $open")) == 0);
expectError('un equipo normal no se reporta como encontrado', fn() => $dev->ReportFound($eight[0], 'floor', 'x', 1));

echo "\n10. Ajuste por equipo con doble aprobación\n";
$lost = $receive(1, 4)[0];
$cc = $dev->ScheduleCount(4, 'risk', 'T-W', 1);
$rr = $dev->CloseCount($cc, 1);
$cw = $rr['cases'][0];
$rc = $dev->ScheduleCount(4, 'prevention', 'T-W2', 1);
$dev->CloseCount($rc, 1);
ok('segundo conteo sin aparecer escala a investigación', $st($cw) === 'en_investigacion');
$flow->ProposeAdjustment($cw, 'No aparece tras dos conteos', 1);
$rec = ReconciliationService::GetInstance();
$ap = $rec->AuthorizeAdjustment($cw, 'approved', 'Primera', 1);
ok('alto valor pide segundo aprobador', !empty($ap['pending_second_approval']) && $state($lost) === 'not_located');
expectError('misma persona no aprueba dos veces', fn() => $rec->AuthorizeAdjustment($cw, 'approved', 'Segunda', 1));
$db->exec("UPDATE sensitive_investigation_cases SET first_approver_user_id = 2 WHERE id = $cw");
$rec->AuthorizeAdjustment($cw, 'approved', 'Segunda', 1);
ok('baja del equipo exacto', $state($lost) === 'written_off' && $engine->OnHand(9200, 4) == 0);
$dev->ReportFound($lost, 'floor', 'Apareció después de la baja', 1);
ok('hallado tras la baja queda en revisión', $state($lost) === 'returned_review');
$dev->DecideReturn($dev->Find($lost)['id'], true, 'Reincorporado', 1);
ok('reincorpora stock una vez', $state($lost) === 'available' && $engine->OnHand(9200, 4) == 1);

echo "\n11. Escenario G: dos colaboradores a la vez\n";
$g = $receive(1, 5)[0];
$v = intval($dev->Find($g)['version']);
$dev->RequestMove($g, 'floor', 1, $v);
expectError('el segundo con la misma versión pierde', fn() => $dev->RequestMove($g, 'showcase', 1, $v));
ok('solo un movimiento pendiente', intval($val("SELECT COUNT(*) FROM device_moves WHERE device_id = {$dev->Find($g)['id']} AND status = 'pending'")) === 1);
$g2 = $dev->Find($g);
$ref = new ReflectionMethod($dev, 'Advance');
$ref->setAccessible(true);
$ref->invoke($dev, $g2, ['custodian_area' => 'A'], 'test', null, null, 1);
expectError('versión vieja no pisa el cambio', fn() => $ref->invoke($dev, $g2, ['custodian_area' => 'B'], 'test', null, null, 1));

$rc = $receive(1, 6)[0];
$start = microtime(true) + 2.5;
$cmd = fn(string $z) => [PHP_BINARY, __DIR__ . '/race_device.php', GROCY_DATAPATH, $rc, $z, strval($start)];
$procs = [];
foreach (['floor', 'showcase'] as $z)
{
	$pipes = [];
	$procs[] = [proc_open($cmd($z), [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes), $pipes];
}
$results = array_map(function ($p) {
	$out = stream_get_contents($p[1][1]);
	proc_close($p[0]);
	return $out;
}, $procs);
ok('dos procesos a la vez: gana exactamente uno', count(array_filter($results, fn($r) => $r === 'WIN')) === 1, implode(' | ', array_map(fn($r) => substr($r, 0, 70), $results)));
ok('solo queda un movimiento pendiente', intval($val("SELECT COUNT(*) FROM device_moves WHERE device_id = {$dev->Find($rc)['id']} AND status = 'pending'")) === 1);

echo "\n12. Reservado también en cancelación de venta\n";
$cs = $receive(1)[0];
$sl = $sale([$cs]);
$sales->CancelSale(intval($sl->id));
ok('cancelada pasa a revisión', $state($cs) === 'returned_review');
$stockAfter = $engine->OnHand(9200, 1);
$dev->DecideReturn($dev->Find($cs)['id'], true, 'Ok', 1);
ok('no duplica stock al aprobar', $engine->OnHand(9200, 1) == $stockAfter && $state($cs) === 'available');
$sale([$cs]);
ok('se vende por la entrada reincorporada', $state($cs) === 'sold');

echo "\n13. Conciliación y auditoría\n";
foreach ([1, 2, 3, 4, 5] as $b)
{
	$e = $engine->Expected(9200, $b);
	$u2 = intval($val("SELECT COUNT(*) FROM device_units WHERE product_id = 9200 AND branch_id = $b AND state IN ('available', 'reserved', 'not_located', 'blocked', 'returned_review') AND stock_id IS NOT NULL AND stock_reinstated >= 0 AND NOT (state = 'returned_review' AND stock_reinstated = 0)"));
	ok("sucursal $b: stock = unidades por IMEI", abs($e['on_hand'] - $u2) < 0.001 && abs($e['book_gap']) < 0.001, $e['on_hand'] . ' vs ' . $u2);
}
ok('historial encadenado íntegro', $dev->VerifyChain() === null);
expectError('historial no se edita', fn() => $db->exec("UPDATE device_events SET note = 'x' WHERE id = 1"));
expectError('historial no se borra', fn() => $db->exec('DELETE FROM device_events WHERE id = 1'));
$d = $dev->Detail(intval($dev->Find($eight[0])['id']));
ok('el expediente del equipo no expone IMEI completo', !isset($d['unit']['imei1']) && !str_contains(json_encode($d['unit']), $eight[0]));
$r = $dev->Reveal(intval($dev->Find($eight[0])['id']), 1);
ok('revelar deja rastro', $r['imei1'] === $eight[0] && intval($val("SELECT COUNT(*) FROM device_events WHERE event = 'reveal'")) === 1);

echo "\n14. Dashboard y demostración\n";
$dash = $dev->Dashboard([]);
ok('indicadores completos', !array_diff(['registered', 'sold', 'reserved', 'in_transit', 'showcase', 'not_located', 'inconsistent', 'pending_value', 'open_cases', 'avg_resolution_hours', 'count_compliance'], array_keys($dash['kpis'])));
ok('demostración: 2 no localizados', intval($val("SELECT COUNT(*) FROM device_units WHERE product_id = 9003 AND state = 'not_located'")) === 2);
ok('2 expedientes DEMO', intval($val("SELECT COUNT(*) FROM sensitive_investigation_cases WHERE case_code LIKE 'DEMO-IMEI-%' AND product_id = 9003")) === 2);
ok('filtro por estado', count(array_filter($dev->Dashboard(['state' => 'not_located'])['units'], fn($u) => $u['state'] !== 'not_located')) === 0);
ok('filtro por marca', count($dev->Dashboard(['brand' => 'Apple'])['units']) > 0 && count(array_filter($dev->Dashboard(['brand' => 'Apple'])['units'], fn($u) => $u['brand'] !== 'Apple')) === 0);
ok('la tabla va enmascarada', !str_contains(json_encode($dash['units']), $eight[0]));
ok('el resumen de Prevención incluye estos expedientes', $flow->Summary()['open_cases'] >= 2);

echo "\n$count verificaciones · $fails FALLAS\n";
exit($fails ? 1 : 0);
