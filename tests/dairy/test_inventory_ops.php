<?php
/**
 * Inventario y Almacenes (InventoryOperationsService): posición físico/reservado/disponible, ajustes con aprobación y
 * segregación, reposición bodega → sala, bloqueo de traslados directos entre tiendas, escaneo y kardex.
 * Trabaja sobre una copia de la BD. Uso: php tests/dairy/test_inventory_ops.php [base.db]
 */
require __DIR__ . '/bootstrap.php';

use Grocy\Services\InventoryOperationsService as IOS;

$s = IOS::GetInstance();
$db = Grocy\Services\DatabaseService::GetInstance()->GetDbConnectionRaw();
$stockOf = fn(int $p, ?int $loc = null) => floatval($val("SELECT IFNULL(SUM(amount), 0) FROM stock WHERE product_id = $p" . ($loc ? " AND location_id = $loc" : '')));

echo "Esquema\n";
$cols = array_column($db->query('PRAGMA table_info(inventory_adjustments)')->fetchAll(PDO::FETCH_ASSOC), 'name');
ok('inventory_adjustments con datos para aplicar y origen', !array_diff(['best_before_date', 'price', 'source', 'request_key', 'resolved_by_user_id'], $cols));
$cols = array_column($db->query('PRAGMA table_info(wms_internal_movements)')->fetchAll(PDO::FETCH_ASSOC), 'name');
ok('wms_internal_movements con cierre trazable', !array_diff(['completed_at', 'completed_by_user_id', 'transaction_id'], $cols));

echo "Resumen y fórmulas\n";
$sum = $s->Summary();
$k = $sum['kpis'];
ok('seis etapas del ciclo en orden', array_column($sum['cycle'], 'code') === ['reception', 'putaway', 'storage', 'replenishment', 'exit', 'reconciliation']);
ok('bodega + sala + otras = físico', abs($k['bodega'] + $k['sala'] + $k['other_locations'] - $k['physical']) < 0.05, json_encode([$k['bodega'], $k['sala'], $k['other_locations'], $k['physical']]));
ok('disponible ≤ físico', $k['available'] <= $k['physical'] + 0.001);
ok('exactitud entre 0 y 100 o sin dato', $k['accuracy_pct'] === null || ($k['accuracy_pct'] >= 0 && $k['accuracy_pct'] <= 100));
ok('cobertura o nota explicativa', $k['coverage_days'] !== null || $k['coverage_note'] !== null);
ok('sin KPI comerciales', !array_intersect(array_keys($k), ['net_sales', 'avg_ticket', 'sales_target', 'margin']));
$pos = $s->Positions(['limit' => 1000]);
$bad = array_filter($pos['rows'], fn($r) => abs(max(0, $r['physical'] - $r['blocked'] - $r['reserved']) - $r['available']) > 0.001);
ok('disponible = físico − bloqueado − reservado (sin negativos)', !$bad, count($bad) . ' filas');
$bad = array_filter($pos['rows'], fn($r) => $r['shortfall'] > 0 && $r['available'] != 0.0);
ok('faltante solo cuando el disponible es cero', !$bad);
$cov = $s->Coverage(null, 1000);
ok('cobertura extrema o sin salidas siempre explicada', !array_filter($cov['rows'], fn($r) => ($r['coverage_days'] === null || $r['coverage_days'] > 365) && !$r['note']));

echo "Ajustes con aprobación\n";
$row = $db->query("SELECT s.product_id, s.location_id, SUM(s.amount) AS q FROM stock s JOIN locations l ON l.id = s.location_id JOIN products p ON p.id = s.product_id
	WHERE p.active = 1 AND IFNULL(l.is_transit, 0) = 0 AND IFNULL(p.is_serialized, 0) = 0 AND IFNULL(p.enable_tare_weight_handling, 0) = 0 GROUP BY 1, 2 HAVING q >= 5 ORDER BY q DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$pid = intval($row['product_id']);
$loc = intval($row['location_id']);
$before = $stockOf($pid);
$r = $s->RequestAdjustment($pid, $before - 2, $loc, ['source' => 'count_form', 'request_key' => 'test-ia-1']);
$adj = $r['adjustment'];
ok('conteo con diferencia crea ajuste pendiente', $adj && $adj['status'] === 'pending_approval' && abs(floatval($adj['difference_amount']) + 2) < 0.0001);
ok('el conteo no modifica existencias', abs($stockOf($pid) - $before) < 0.0001);
ok('reenvío con la misma clave no duplica', ($s->RequestAdjustment($pid, $before - 2, $loc, ['request_key' => 'test-ia-1'])['duplicate'] ?? false) === true);
ok('conteo igual al sistema no crea ajuste', !empty($s->RequestAdjustment($pid, $before, $loc)['no_difference']));
expectError('quien solicitó no puede aprobar', fn() => $s->ApproveAdjustment(intval($adj['id'])));
$db->exec('UPDATE inventory_adjustments SET requested_by_user_id = 999 WHERE id = ' . intval($adj['id']));
$res = $s->ApproveAdjustment(intval($adj['id']));
ok('aprobación aplica la diferencia en la ubicación', abs($stockOf($pid) - ($before - 2)) < 0.0001, $stockOf($pid) . ' vs ' . ($before - 2));
$a2 = $db->query('SELECT * FROM inventory_adjustments WHERE id = ' . intval($adj['id']))->fetch(PDO::FETCH_ASSOC);
ok('ajuste aprobado, aplicado y con transacción', $a2['status'] === 'approved' && intval($a2['inventory_applied']) === 1 && !empty($a2['transaction_id']));
ok('movimiento en kardex como corrección', intval($val("SELECT COUNT(*) FROM stock_log WHERE transaction_id = '" . $a2['transaction_id'] . "' AND transaction_type = 'inventory-correction'")) > 0);
expectError('no se aprueba dos veces', fn() => $s->ApproveAdjustment(intval($adj['id'])));
$r = $s->RequestAdjustment($pid, $stockOf($pid) + 3, $loc);
expectError('rechazo exige motivo', fn() => $s->RejectAdjustment(intval($r['adjustment']['id']), ''));
$s->RejectAdjustment(intval($r['adjustment']['id']), 'Recontado: correcto');
ok('rechazo no toca el stock', abs($stockOf($pid) - ($before - 2)) < 0.0001);
$k2 = $s->Kardex($pid, null, date('Y-m-d', strtotime('-3 days')), date('Y-m-d'));
ok('kardex cuadra: inicial + entradas − salidas = final', $k2['opening_balance'] === null || abs($k2['opening_balance'] + $k2['entries'] - $k2['exits'] - $k2['closing_balance']) < 0.001);
ok('kardex muestra el ajuste con su módulo', (bool)array_filter($k2['rows'], fn($x) => $x['transaction_type'] === 'inventory-correction' && $x['source_module'] === 'Inventario'));

echo "Traslados\n";
$pair = $db->query("SELECT a.id AS a, b.id AS b FROM locations a JOIN locations b ON b.branch_id <> a.branch_id WHERE IFNULL(a.is_transit, 0) = 0 AND IFNULL(b.is_transit, 0) = 0 AND a.branch_id IS NOT NULL AND b.branch_id IS NOT NULL LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$same = $db->query("SELECT a.id AS a, b.id AS b FROM locations a JOIN locations b ON b.branch_id = a.branch_id AND b.id <> a.id WHERE IFNULL(a.is_transit, 0) = 0 AND IFNULL(b.is_transit, 0) = 0 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
ok('entre sucursales se exige traslado con recepción', $s->IsCrossBranchMove(intval($pair['a']), intval($pair['b'])) === true);
ok('dentro de la sucursal se permite el movimiento interno', $s->IsCrossBranchMove(intval($same['a']), intval($same['b'])) === false);
$transit = intval($val('SELECT id FROM locations WHERE is_transit = 1 LIMIT 1'));
ok('hacia tránsito solo por documento de traslado', !$transit || $s->IsCrossBranchMove(intval($same['a']), $transit) === true);
$an = $s->Anomalies();
ok('traslados vencidos sin recepción aparecen como anomalía', ($an['counts']['transfer_unconfirmed'] ?? 0) === count(array_filter($s->Transfers()['rows'], fn($t) => $t['late'])));

echo "Reposición bodega → sala\n";
$rep = $s->Replenishment(null, 500);
$sug = null;
foreach ($rep['suggestions'] as $x)
{
	if (!$x['task_open'] && $x['suggested'] >= 1)
	{
		$sug = $x;
		break;
	}
}
if ($sug)
{
	ok('sugerencia no supera bodega ni el faltante', $sug['suggested'] <= $sug['bodega'] + 0.001 && $sug['suggested'] <= $sug['min_stock'] - $sug['sala'] + 0.001);
	$sp = $sug['product_id'];
	$tot = $stockOf($sp);
	$c = $s->CreateReplenishmentTasks($sug['branch_id'], [['product_id' => $sp, 'amount' => 1]]);
	ok('crea tarea pendiente', $c['created'] === 1);
	ok('crear la tarea no mueve stock', abs($stockOf($sp) - $tot) < 0.0001);
	ok('no duplica tareas abiertas', $s->CreateReplenishmentTasks($sug['branch_id'], [['product_id' => $sp, 'amount' => 1]])['created'] === 0);
	$t = $db->query("SELECT * FROM wms_internal_movements WHERE product_id = $sp AND status = 'pendiente' ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
	$fromB = $stockOf($sp, intval($t['from_location_id']));
	$toB = $stockOf($sp, intval($t['to_location_id']));
	expectError('no confirma más de lo que hay en bodega', fn() => $s->ConfirmReplenishment(intval($t['id']), $fromB + 100));
	$s->ConfirmReplenishment(intval($t['id']), 1);
	ok('confirmar mueve 1 u de bodega a sala', abs($stockOf($sp, intval($t['from_location_id'])) - ($fromB - 1)) < 0.0001 && abs($stockOf($sp, intval($t['to_location_id'])) - ($toB + 1)) < 0.0001);
	ok('el total del producto no cambia', abs($stockOf($sp) - $tot) < 0.0001);
	expectError('no se confirma dos veces', fn() => $s->ConfirmReplenishment(intval($t['id'])));
}
else
{
	ok('sin sugerencias de reposición en esta base (se omite)', true);
}

echo "Escaneo\n";
$bc = $val('SELECT barcode FROM product_barcodes LIMIT 1');
$sc = $s->Scan($bc);
ok('UPC encontrado con posición', $sc['found'] && $sc['matched_by'] === 'barcode' && isset($sc['position']['available']));
ok('código inexistente no falla', $s->Scan('NO-EXISTE-' . time())['found'] === false);
expectError('código vacío', fn() => $s->Scan('  '));

echo "Salidas controladas por su módulo\n";
$au = $s->Audit(['from' => date('Y-m-d', strtotime('-30 days'))]);
ok('auditoría atribuye cada movimiento a su módulo dueño', !array_filter($au['rows'], fn($x) => empty($x['source_module'])));


echo "\n$count verificaciones, $fails fallas\n";
exit($fails ? 1 : 0);
