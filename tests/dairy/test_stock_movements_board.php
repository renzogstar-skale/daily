<?php
/**
 * Stock & Movimientos (StockMovementsBoardService): saldos del ciclo retail y coherencia del tablero con Kardex, Conteos y Traslados.
 *  - Reposición interna: cambia bodega y sala, conserva el total de la sucursal y cuenta un solo movimiento.
 *  - Recepción parcial de traslado: el destino sube lo recibido, el resto sigue en tránsito; la final cierra con merma del faltante.
 *  - Conteo con diferencia: conserva la cantidad de sistema, no cambia stock hasta aprobar; el ajuste queda vinculado al conteo.
 * Trabaja sobre una copia de la BD. Uso: php tests/dairy/test_stock_movements_board.php [base.db]
 */
require __DIR__ . '/bootstrap.php';

use Grocy\Helpers\DailyFilters;
use Grocy\Services\InventoryOperationsService as IOS;
use Grocy\Services\StockMovementsBoardService as Board;

$board = Board::GetInstance();
$ios = IOS::GetInstance();
$num = fn(string $sql) => floatval($db->query($sql)->fetchColumn());
$branchStock = fn(int $p, int $b) => $num("SELECT IFNULL(SUM(s.amount), 0) FROM stock s JOIN locations l ON l.id = s.location_id WHERE s.product_id = $p AND l.branch_id = $b AND IFNULL(l.is_transit, 0) = 0");
$zoneStock = fn(int $p, int $b, string $zone) => $num("SELECT IFNULL(SUM(s.amount), 0) FROM stock s JOIN locations l ON l.id = s.location_id WHERE s.product_id = $p AND l.branch_id = $b AND l.zone_type = '$zone'");
$transitStock = fn(int $p) => $num("SELECT IFNULL(SUM(s.amount), 0) FROM stock s JOIN locations l ON l.id = s.location_id WHERE s.product_id = $p AND l.is_transit = 1");
$f = fn(array $q = []) => DailyFilters::FromQuery($q);
$ccCode = fn(int $id) => 'CC-' . str_pad((string) $id, 4, '0', STR_PAD_LEFT);

echo "Tablero\n";
$d = $board->Dashboard($f());
$k = $d['kpis'];
ok('ocho indicadores', count($k) === 8, implode(',', array_keys($k)));
ok('exactitud = sin diferencia ÷ evaluadas', $k['accuracy']['pct'] === null || abs($k['accuracy']['pct'] - round($k['accuracy']['hits'] / $k['accuracy']['lines'] * 100, 1)) < 0.05);
ok('exactitud usa las mismas líneas que el detalle de conteos', $board->Detail('counts', $f(), ['sign' => 'all'])['total'] === $k['accuracy']['lines']);
ok('conteos con diferencias = detalle filtrado', $board->Detail('counts', $f(), ['sign' => 'diff'])['total'] === $k['count_diffs']['lines']);
ok('faltantes + sobrantes = líneas con diferencia', $k['count_diffs']['short'] + $k['count_diffs']['over'] === $k['count_diffs']['lines']);
ok('agotados: combinaciones ≥ SKUs', $k['stockouts']['pairs'] >= $k['stockouts']['skus']);
ok('agotados = detalle', $board->Detail('stockouts', $f(), [])['total'] === $k['stockouts']['pairs']);
ok('traslados pendientes = detalle', $board->Detail('transfers', $f(), [])['total'] === $k['transfers']['count']);
ok('seis tipos de movimiento', array_column($d['types'], 'key') === ['receptions', 'internal', 'transfers', 'exits', 'returns', 'adjustments']);
ok('recibidas = suma del kardex de recepciones (piezas)', abs($k['received']['qty']['u'] - $num("SELECT IFNULL(SUM(m.amount), 0) FROM merchandise_movements m JOIN products p ON p.id = m.product_id LEFT JOIN quantity_units qu ON qu.id = p.qu_id_stock
	WHERE m.undone = 0 AND m.is_transit = 0 AND m.bucket = 'receptions' AND m.recorded_at BETWEEN '{$d['period']['start']} 00:00:00' AND '{$d['period']['end']} 23:59:59'
	AND LOWER(IFNULL(qu.name, '')) NOT IN ('kilogramo', 'gramo', 'litro', 'mililitro', 'galón', '1/2 galón', 'pinta')")) < 0.05);
ok('acumulado día ≤ semana ≤ mes', $k['accumulated']['day'] <= $k['accumulated']['week'] && $k['accumulated']['week'] <= $k['accumulated']['month']);
ok('cuatro alertas', count($d['alerts']) === 4);
$dept = $board->Dashboard($f(['branch_id' => 1, 'department' => 'dairy']));
ok('sucursal fija: diferencias por departamento', $dept['differences']['by'] === 'department');
ok('filtro de departamento acota las líneas', $dept['kpis']['accuracy']['lines'] <= $k['accuracy']['lines']);
$past = $board->Dashboard($f(['start_date' => date('Y-m-d', strtotime('-20 days')), 'end_date' => date('Y-m-d', strtotime('-7 days'))]));
ok('corte anterior: saldo reconstruido y marcado', $past['period']['is_current'] === false && $past['kpis']['stockouts']['is_current'] === false);

echo "Reposición interna (bodega → sala)\n";
$row = $db->query("SELECT l.branch_id, s.product_id FROM stock s JOIN locations l ON l.id = s.location_id JOIN products p ON p.id = s.product_id
	WHERE l.zone_type = 'bodega' AND p.active = 1 AND IFNULL(p.is_serialized, 0) = 0 AND IFNULL(p.enable_tare_weight_handling, 0) = 0
	AND EXISTS (SELECT 1 FROM locations x WHERE x.branch_id = l.branch_id AND x.zone_type = 'piso_venta' AND x.active = 1)
	AND NOT EXISTS (SELECT 1 FROM wms_internal_movements w WHERE w.product_id = s.product_id AND w.branch_id = l.branch_id AND w.status = 'pendiente')
	GROUP BY 1, 2 HAVING SUM(s.amount) >= 4 ORDER BY SUM(s.amount) DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$pid = intval($row['product_id']);
$bid = intval($row['branch_id']);
[$tot0, $bod0, $sal0] = [$branchStock($pid, $bid), $zoneStock($pid, $bid, 'bodega'), $zoneStock($pid, $bid, 'piso_venta')];
$mov0 = $board->Dashboard($f(['branch_id' => $bid]))['types'][1]['count'];
$ios->CreateReplenishmentTasks($bid, [['product_id' => $pid, 'amount' => 3]]);
$task = intval($db->query("SELECT id FROM wms_internal_movements WHERE product_id = $pid AND branch_id = $bid AND status = 'pendiente' ORDER BY id DESC LIMIT 1")->fetchColumn());
ok('tarea pendiente no mueve stock', abs($zoneStock($pid, $bid, 'piso_venta') - $sal0) < 0.0001);
$ios->ConfirmReplenishment($task, 3);
ok('bodega −3', abs($zoneStock($pid, $bid, 'bodega') - ($bod0 - 3)) < 0.0001, $zoneStock($pid, $bid, 'bodega') . ' vs ' . ($bod0 - 3));
ok('sala +3', abs($zoneStock($pid, $bid, 'piso_venta') - ($sal0 + 3)) < 0.0001);
ok('total de la sucursal se conserva', abs($branchStock($pid, $bid) - $tot0) < 0.0001);
ok('una sola reposición en el tablero', $board->Dashboard($f(['branch_id' => $bid]))['types'][1]['count'] === $mov0 + 1);
$det = $board->Detail('movements', $f(['branch_id' => $bid]), ['type' => 'internal']);
ok('detalle: origen bodega, destino sala y documento REP', $det['rows'] && str_starts_with($det['rows'][0]['doc'], 'REP-') && $det['rows'][0]['from'] !== $det['rows'][0]['to'] && $det['rows'][0]['qty'] == 3, json_encode($det['rows'][0] ?? null));

echo "Traslado con recepción parcial\n";
$tsvc = Grocy\Services\StockTransferService::GetInstance();
$from = intval($db->query("SELECT l.id FROM locations l JOIN stock s ON s.location_id = l.id JOIN products p ON p.id = s.product_id WHERE l.branch_id = 1 AND l.active = 1 AND IFNULL(l.is_transit, 0) = 0
	AND IFNULL(p.device_control, 0) = 0 AND IFNULL(p.is_serialized, 0) = 0 GROUP BY l.id ORDER BY SUM(s.amount) DESC LIMIT 1")->fetchColumn());
$to = intval($db->query("SELECT id FROM locations WHERE branch_id = 2 AND active = 1 AND IFNULL(is_transit, 0) = 0 AND IFNULL(storage_class, '') = (SELECT IFNULL(storage_class, '') FROM locations WHERE id = $from) ORDER BY id LIMIT 1")->fetchColumn());
$tp = intval($db->query("SELECT s.product_id FROM stock s JOIN products p ON p.id = s.product_id WHERE s.location_id = $from AND p.active = 1 AND IFNULL(p.device_control, 0) = 0 AND IFNULL(p.is_serialized, 0) = 0
	AND s.stock_id NOT IN (SELECT stock_id FROM quarantine_items WHERE status = 'in_quarantine') GROUP BY s.product_id HAVING SUM(s.amount) >= 12 ORDER BY SUM(s.amount) DESC LIMIT 1")->fetchColumn());
ok('origen, destino y producto para el traslado', $from > 0 && $to > 0 && $tp > 0, json_encode(compact('from', 'to', 'tp')));
[$o0, $d0, $t0] = [$branchStock($tp, 1), $branchStock($tp, 2), $transitStock($tp)];
$tr = $tsvc->CreateTransfer(['from_location_id' => $from, 'to_location_id' => $to, 'items' => [['product_id' => $tp, 'amount' => 10]], 'notes' => 'Prueba recepción parcial'], 1);
$tid = intval($tr['id']);
$code = $db->query("SELECT transfer_code FROM stock_transfers WHERE id = $tid")->fetchColumn();
ok('despacho: origen −10', abs($branchStock($tp, 1) - ($o0 - 10)) < 0.0001);
ok('despacho: tránsito +10', abs($transitStock($tp) - ($t0 + 10)) < 0.0001);
$r = $tsvc->ReceiveTransfer($tid, ['items' => [['product_id' => $tp, 'received_amount' => 6]], 'partial' => 1, 'notes' => 'Llegó la primera tanda'], 1);
ok('estado recepción parcial', $r['status'] === 'partially_received' && $db->query("SELECT status FROM stock_transfers WHERE id = $tid")->fetchColumn() === 'partially_received');
ok('destino +6', abs($branchStock($tp, 2) - ($d0 + 6)) < 0.0001, $branchStock($tp, 2) . ' vs ' . ($d0 + 6));
ok('quedan 4 en tránsito', abs($transitStock($tp) - ($t0 + 4)) < 0.0001);
ok('parcial sin merma', intval($db->query("SELECT COUNT(*) FROM stock_transfer_items WHERE transfer_id = $tid AND waste_record_id IS NOT NULL")->fetchColumn()) === 0);
$open = array_values(array_filter($board->Detail('transfers', $f(), [])['rows'], fn($x) => $x['doc'] === $code));
ok('tablero: 4 u en tránsito y estado parcial', $open && $open[0]['pending'] === '4 u' && str_starts_with($open[0]['status'], 'Recepción parcial'), json_encode($open[0] ?? null));
expectError('no se recibe más de lo pendiente', fn() => $tsvc->ReceiveTransfer($tid, ['items' => [['product_id' => $tp, 'received_amount' => 5]], 'partial' => 1], 1));
expectError('no se anula un traslado ya recibido en parte', fn() => $tsvc->CancelTransfer($tid, 1, 'prueba'));
$tsvc->ReceiveTransfer($tid, ['items' => [['product_id' => $tp, 'received_amount' => 3]], 'notes' => 'Cierre: falta 1'], 1);
ok('recepción final: recibido y cerrado', $db->query("SELECT status FROM stock_transfers WHERE id = $tid")->fetchColumn() === 'received');
ok('destino suma 9 en total', abs($branchStock($tp, 2) - ($d0 + 9)) < 0.0001);
ok('tránsito vuelve al saldo previo', abs($transitStock($tp) - $t0) < 0.0001);
ok('faltante de 1 como merma de traslado', abs($num("SELECT IFNULL(SUM(difference_amount), 0) FROM stock_transfer_items WHERE transfer_id = $tid") - 1) < 0.0001
	&& intval($db->query("SELECT COUNT(*) FROM stock_transfer_items WHERE transfer_id = $tid AND waste_record_id IS NOT NULL")->fetchColumn()) > 0);
ok('enviado = recibido + diferencia', abs($num("SELECT SUM(sent_amount) - SUM(received_amount) - SUM(difference_amount) FROM stock_transfer_items WHERE transfer_id = $tid")) < 0.0001);

echo "Conteo pendiente de aprobación y ajuste vinculado\n";
$cc = Grocy\Services\CycleCountService::GetInstance();
$cp = intval($db->query("SELECT s.product_id FROM stock s JOIN locations l ON l.id = s.location_id JOIN products p ON p.id = s.product_id WHERE l.branch_id = 1 AND p.active = 1
	AND IFNULL(p.is_serialized, 0) = 0 AND p.id <> $tp AND p.id <> $pid AND NOT EXISTS (SELECT 1 FROM stock_cycle_counts c WHERE c.product_id = s.product_id AND c.branch_id = 1 AND c.status = 'pending')
	GROUP BY s.product_id HAVING SUM(s.amount) >= 6 ORDER BY SUM(s.amount) DESC LIMIT 1")->fetchColumn());
$cc->ScheduleForProducts(1, [$cp], 'Prueba tablero', 1);
$cid = intval($db->query("SELECT id FROM stock_cycle_counts WHERE product_id = $cp AND branch_id = 1 AND status = 'pending' ORDER BY id DESC LIMIT 1")->fetchColumn());
$loc = intval($db->query("SELECT location_id FROM stock_cycle_counts WHERE id = $cid")->fetchColumn());
$locStock = fn() => $num("SELECT IFNULL(SUM(amount), 0) FROM stock WHERE product_id = $cp AND location_id = $loc");
$before = $locStock();
$cc->Execute($cid, $before - 2, 'Faltan 2', 1);
$c = $db->query("SELECT * FROM stock_cycle_counts WHERE id = $cid")->fetch(PDO::FETCH_ASSOC);
ok('conserva la cantidad de sistema al contar', abs(floatval($c['theoretical_stock']) - $before) < 0.0001 && floatval($c['difference_amount']) == -2.0);
ok('pendiente de aprobación', $c['status'] === 'discrepancy');
ok('el conteo pendiente no cambia el saldo', abs($locStock() - $before) < 0.0001);
$pend = $board->Detail('counts', $f(), ['status' => 'discrepancy']);
ok('tablero: aparece por conciliar con sistema y contado', (bool) array_filter($pend['rows'], fn($x) => $x['id'] === $ccCode($cid) && $x['system'] == $before && $x['counted'] == $before - 2));
expectError('quien contó no aprueba', fn() => $cc->ApproveAdjustment($cid, 1));
$res = $cc->ApproveAdjustment($cid, 2);
ok('ajuste aplicado al saldo', abs($locStock() - ($before - 2)) < 0.0001, $locStock() . ' vs ' . ($before - 2));
$c = $db->query("SELECT * FROM stock_cycle_counts WHERE id = $cid")->fetch(PDO::FETCH_ASSOC);
ok('conteo vinculado al ajuste y a su aprobador', $c['status'] === 'adjusted' && $c['adjustment_transaction_id'] === $res['transaction_id'] && intval($c['supervisor_user_id']) === 2);
$adjRows = $board->Detail('movements', $f(), ['type' => 'adjustments'])['rows'];
ok('kardex: el ajuste lleva el documento del conteo', (bool) array_filter($adjRows, fn($x) => $x['doc'] === $ccCode($cid) && $x['qty'] == -2), json_encode(array_slice($adjRows, 0, 2)));
$trace = array_values(array_filter($board->Detail('counts', $f(), ['sign' => 'diff'])['rows'], fn($x) => $x['id'] === $ccCode($cid)));
ok('detalle de conteo muestra el ajuste confirmado', $trace && $trace[0]['status'] === 'Ajuste confirmado' && str_contains($trace[0]['trace'], substr($res['transaction_id'], 0, 8)), json_encode($trace[0] ?? null));

echo "\n$count verificaciones, $fails fallas\n";
exit($fails ? 1 : 0);
