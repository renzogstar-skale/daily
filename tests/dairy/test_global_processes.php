<?php
/**
 * Integración global: una operación → un registro central → todos los módulos.
 * Recorridos del escenario Hallandale Beach (sucursal 1) → Aventura (sucursal 4):
 *  1. Venta: 100 → 92 (Stock, Kardex, libro, Ventas, HOY, Panel)
 *  2. Traslado: A 100 / B 50 → 80 / 70, red 150 (tránsito sin duplicar)
 *  3. Merma aprobada: 50 → 45 (Kardex, Inventario, Mermas)
 *  4. Diferencia sensible: recibir 10, vender 2, contar 6 → −2, alerta, caso; el ajuste solo tras autorización
 *  5. Persistencia (relectura) e idempotencia (reintentos sin duplicar)
 *  6. Integridad global: libro = kardex, sin negativos, suma de sucursales = red
 */

use Grocy\Services\DailyMetricsService;
use Grocy\Services\KpiService;
use Grocy\Services\ReconciliationService;
use Grocy\Services\SalesService;
use Grocy\Services\StockTransferService;
use Grocy\Services\TodayExecutiveSalesService;
use Grocy\Services\WasteService;

require __DIR__ . '/bootstrap.php';

$rc = ReconciliationService::GetInstance();
$dm = DailyMetricsService::GetInstance();
$near = fn($a, $b, $t = 0.011) => abs(floatval($a) - floatval($b)) < $t;
$branchStock = fn(int $b, int $p) => floatval($val("SELECT IFNULL(SUM(s.amount), 0) FROM stock s JOIN locations l ON l.id = s.location_id WHERE s.product_id = $p AND l.branch_id = $b AND IFNULL(l.is_transit, 0) = 0"));
$transit = fn(int $p) => floatval($val("SELECT IFNULL(SUM(s.amount), 0) FROM stock s JOIN locations l ON l.id = s.location_id WHERE s.product_id = $p AND IFNULL(l.is_transit, 0) = 1"));
$network = fn(int $p) => floatval($val("SELECT IFNULL(SUM(amount), 0) FROM stock WHERE product_id = $p"));
$logCount = fn() => intval($val('SELECT COUNT(*) FROM stock_log'));
// Lleva el stock de la sucursal a un valor exacto con movimientos reales (recepción o despacho de conciliación)
$setTo = function (int $b, int $p, float $target) use ($rc, $branchStock) {
	$cur = $branchStock($b, $p);
	if ($target > $cur)
	{
		$rc->RegisterGoodsIn(['product_id' => $p, 'branch_id' => $b, 'expected_qty' => $target - $cur, 'received_qty' => $target - $cur, 'supplier_name' => 'Proveedor de prueba'], 1);
	}
	elseif ($target < $cur)
	{
		$rc->RegisterMovement(['product_id' => $p, 'branch_id' => $b, 'quantity' => $cur - $target, 'event_type' => 'dispatch'], 1);
	}
	return $branchStock($b, $p);
};
$today = date('Y-m-d');

echo "\n0. Conciliación contabiliza en el inventario central (antes escribía directo en stock)\n";
$pid = 188; // Cargador USB-C 20 W
$before = $branchStock(1, $pid);
$logs = $logCount();
$r = $rc->RegisterGoodsIn(['product_id' => $pid, 'branch_id' => 1, 'expected_qty' => 5, 'received_qty' => 5, 'unit_cost' => 8.5, 'supplier_name' => 'Proveedor de prueba', 'operation_id' => 'op-test-in-1'], 1);
ok('Ingreso: stock de la sucursal +5', $near($branchStock(1, $pid), $before + 5));
ok('Ingreso: movimiento en kardex (stock_log) con su transacción', $logCount() > $logs && intval($val("SELECT COUNT(*) FROM stock_log WHERE transaction_id = " . $db->quote($r['transaction_id']))) > 0);
ok('Ingreso: entra al libro inalterable', intval($val("SELECT COUNT(*) FROM dairy_movement_ledger WHERE transaction_id = " . $db->quote($r['transaction_id']))) > 0);
ok('Folio secuencial (sin azar)', preg_match('/^ING-\d{8}-\d{4}$/', $r['movement_code']) === 1, $r['movement_code']);
$r2 = $rc->RegisterGoodsIn(['product_id' => $pid, 'branch_id' => 1, 'expected_qty' => 5, 'received_qty' => 5, 'unit_cost' => 8.5, 'operation_id' => 'op-test-in-1'], 1);
ok('Reintento con el mismo identificador no duplica la recepción', !empty($r2['replayed']) && $near($branchStock(1, $pid), $before + 5));
expectError('Producto inexistente rechazado', fn() => $rc->RegisterGoodsIn(['product_id' => 999999, 'branch_id' => 1, 'received_qty' => 1], 1));
expectError('Sin sucursal rechazado (antes asumía la 1)', fn() => $rc->RegisterGoodsIn(['product_id' => $pid, 'received_qty' => 1], 1));
expectError('Despacho mayor al stock rechazado (sin negativos)', fn() => $rc->RegisterMovement(['product_id' => $pid, 'branch_id' => 1, 'quantity' => $branchStock(1, $pid) + 1000, 'event_type' => 'dispatch'], 1));
expectError('Traslado por conciliación desviado a Traslados (trazabilidad)', fn() => $rc->RegisterMovement(['product_id' => $pid, 'branch_id' => 1, 'quantity' => 1, 'event_type' => 'transfer'], 1));

echo "\n1. Venta: 100 → 92\n";
ok('Stock inicial exacto: 100', $near($setTo(1, $pid, 100), 100));
$salesBefore = $dm->Sales(1, $today, $today);
$logs = $logCount();
$price = floatval($val("SELECT sale_price FROM products WHERE id = $pid"));
$sale = SalesService::GetInstance()->CreateSale(['branch_id' => 1, 'customer_id' => 1, 'payment_method' => 'card', 'items' => [['product_id' => $pid, 'amount' => 8, 'unit_price' => $price]]], 1);
ok('Stock final: 92', $near($branchStock(1, $pid), 92), $branchStock(1, $pid));
ok('Kardex: salida valorizada registrada', $logCount() > $logs && intval($val("SELECT COUNT(*) FROM stock_log WHERE product_id = $pid AND transaction_type = 'consume' AND id > " . ($val('SELECT MAX(id) FROM stock_log') - 20))) > 0);
$salesAfter = $dm->Sales(1, $today, $today);
ok('Ventas: +1 ticket y +8 unidades', $salesAfter['tickets'] === $salesBefore['tickets'] + 1 && $near($salesAfter['units'], $salesBefore['units'] + 8));
ok('Ventas netas suben el subtotal sin impuestos', $near($salesAfter['net_sales'] - $salesBefore['net_sales'], 8 * $price, 0.05), ($salesAfter['net_sales'] - $salesBefore['net_sales']) . ' vs ' . 8 * $price);
$h = TodayExecutiveSalesService::GetInstance()->GetExecutiveData(1, $today);
ok('HOY refleja la venta', $near($h['kpis']['sales_net']['numeric'], $salesAfter['net_sales']));
$k = KpiService::GetInstance()->GetExecutiveDashboardData(1, 'day', null, null, $today);
$p = KpiService::GetInstance()->BuildExecutivePanelOverview(1, 'day', $k, [], 0);
ok('Panel Ejecutivo = HOY', $near($p['strategic_kpis']['sales']['value'], $salesAfter['net_sales']));

echo "\n2. Traslado Hallandale → Aventura: 100 / 50 → 80 / 70, red 150\n";
$setTo(1, $pid, 100);
$setTo(4, $pid, 50);
$net0 = $network($pid);
$tr = StockTransferService::GetInstance();
$locA = intval($val("SELECT s.location_id FROM stock s JOIN locations l ON l.id = s.location_id WHERE s.product_id = $pid AND l.branch_id = 1 AND IFNULL(l.is_transit, 0) = 0 GROUP BY s.location_id ORDER BY SUM(s.amount) DESC LIMIT 1"));
$locB = intval($val("SELECT id FROM locations WHERE branch_id = 4 AND IFNULL(is_transit, 0) = 0 AND IFNULL(storage_class, 'ambient') = (SELECT IFNULL(storage_class, 'ambient') FROM products WHERE id = $pid) ORDER BY id LIMIT 1"));
$t = $tr->CreateTransfer(['from_location_id' => $locA, 'to_location_id' => $locB, 'items' => [['product_id' => $pid, 'amount' => 20]]], 1);
ok('Envío: origen 80, destino 50, 20 en tránsito', $near($branchStock(1, $pid), 80) && $near($branchStock(4, $pid), 50) && $near($transit($pid), 20));
ok('En tránsito la red no duplica (sigue igual)', $near($network($pid), $net0));
$tr->ReceiveTransfer($t['id'], ['items' => [['product_id' => $pid, 'received_amount' => 20]]], 1);
ok('Recepción: A 80, B 70', $near($branchStock(1, $pid), 80) && $near($branchStock(4, $pid), 70));
ok('Red A + B = 150 y tránsito vacío', $near($branchStock(1, $pid) + $branchStock(4, $pid), 150) && $near($transit($pid), 0));
ok('Kardex conserva salida y entrada del traslado', intval($val("SELECT COUNT(*) FROM stock_log WHERE product_id = $pid AND transaction_type IN ('transfer_from', 'transfer_to') AND id > " . ($val('SELECT MAX(id) FROM stock_log') - 30))) >= 2);
expectError('Recibir dos veces rechazado', fn() => $tr->ReceiveTransfer($t['id'], ['items' => [['product_id' => $pid, 'received_amount' => 20]]], 1));

echo "\n3. Merma aprobada: 50 → 45\n";
$setTo(4, $pid, 50);
$wasteBefore = $dm->Waste(4, $today, $today);
$w = WasteService::GetInstance()->ReportWaste($pid, 5, 34, 4, null, 'default', 'Prueba de integración', null, null, null, null, null, null, 'waste-test-1', 1, false, true);
$w = (array) $w;
$wid = intval($w['waste_record_id'] ?? $w['id'] ?? $val("SELECT MAX(id) FROM waste_records WHERE product_id = $pid"));
$status = $val("SELECT status FROM waste_records WHERE id = $wid");
if ($status === 'pending_approval')
{
	ok('Merma pendiente: el stock no se descuenta antes de aprobar', $near($branchStock(4, $pid), 50));
	WasteService::GetInstance()->ApproveWaste($wid, 2);
}
ok('Stock tras merma aprobada: 45', $near($branchStock(4, $pid), 45), $branchStock(4, $pid));
$wasteAfter = $dm->Waste(4, $today, $today);
ok('Mermas: +1 evento aprobado y su costo, una sola vez', $wasteAfter['events'] === $wasteBefore['events'] + 1 && $wasteAfter['cost'] > $wasteBefore['cost']);
$w2 = WasteService::GetInstance()->ReportWaste($pid, 5, 34, 4, null, 'default', 'Prueba de integración', null, null, null, null, null, null, 'waste-test-1', 1, false, true);
ok('Reintento de la merma no duplica', $near($branchStock(4, $pid), 45) && $dm->Waste(4, $today, $today)['events'] === $wasteAfter['events']);

echo "\n4. Diferencia sensible: recibir 10, vender 2, contar 6\n";
$aud = 187; // Audífonos inalámbricos
$setTo(1, $aud, 0);
$rc->RegisterGoodsIn(['product_id' => $aud, 'branch_id' => 1, 'expected_qty' => 10, 'received_qty' => 10, 'unit_cost' => 22, 'supplier_name' => 'Proveedor de prueba'], 1);
SalesService::GetInstance()->CreateSale(['branch_id' => 1, 'customer_id' => 1, 'payment_method' => 'cash', 'items' => [['product_id' => $aud, 'amount' => 2, 'unit_price' => 49.99]]], 1);
ok('Stock teórico: 8', $near($branchStock(1, $aud), 8));
$wasteEvents = intval($val("SELECT COUNT(*) FROM waste_records WHERE product_id = $aud"));
$cnt = $rc->ExecuteCountAndEvaluate(1, $aud, 6, 'sensible', 'Conteo de prueba', 3);
$case = $db->query("SELECT * FROM sensitive_investigation_cases WHERE product_id = $aud ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
ok('Diferencia detectada: −2', $case && $near($case['difference_qty'], -2), json_encode($case['difference_qty'] ?? null));
ok('Alerta de inventario sensible generada', $case && intval($val('SELECT COUNT(*) FROM dairy_alerts WHERE id = ' . intval($case['alert_id']))) === 1);
ok('Caso de investigación con folio secuencial', $case && preg_match('/^INV-\d{4}-\d{5}$/', $case['case_code']) === 1, $case['case_code'] ?? '');
ok('La diferencia no se convierte en merma', intval($val("SELECT COUNT(*) FROM waste_records WHERE product_id = $aud")) === $wasteEvents);
ok('Antes de autorizar el stock sigue en 8', $near($branchStock(1, $aud), 8));
$m = $rc->GetProductCard($aud, 1)['metrics'];
ok('Expediente muestra la última recepción y la última venta', !empty($m['last_reception_date']) && !empty($m['last_sale']) && $near($m['last_sale']['actual_amount'], 2), json_encode([$m['last_reception_date'], $m['last_sale']['document_code'] ?? null]));
expectError('Quien contó no puede autorizar su propio ajuste', fn() => $rc->AuthorizeAdjustment(intval($case['id']), 'approved', 'auto', intval($case['created_by_user_id']) === 1 ? 999 : intval($case['created_by_user_id'])));
$logs = $logCount();
$rc->AuthorizeAdjustment(intval($case['id']), 'approved', 'Pérdida reconocida tras reconteo', 2);
ok('Tras autorizar: stock 6 (corrección en inventario)', $near($branchStock(1, $aud), 6), $branchStock(1, $aud));
$adjTid = $val('SELECT adjustment_transaction_id FROM sensitive_investigation_cases WHERE id = ' . intval($case['id']));
ok('Ajuste en kardex como corrección de inventario, ligado al caso', $adjTid && intval($val("SELECT COUNT(*) FROM stock_log WHERE transaction_id = " . $db->quote($adjTid) . " AND transaction_type = 'inventory-correction'")) > 0);
expectError('Autorizar dos veces no descuenta de nuevo', fn() => $rc->AuthorizeAdjustment(intval($case['id']), 'approved', 'otra vez', 2));
ok('Stock sigue en 6', $near($branchStock(1, $aud), 6));

echo "\n4b. Recorrido de Stock & Movimientos con operaciones reales (antes solo mostraba mensajes)\n";
$se = \Grocy\Services\StockExecutiveOverviewService::GetInstance();
$sku = $val("SELECT sku FROM products WHERE id = $pid");
$b0 = $branchStock(1, $pid);
$s1 = $se->ExecuteIntegratedWorkflowStep(['step_type' => 'reception', 'sku' => $sku, 'qty' => 12, 'branch_name' => 'Hallandale Beach', 'operation_id' => 'wf-test-1'], 1);
ok('Recepción del recorrido: +12 en inventario', $near($branchStock(1, $pid), $b0 + 12) && $near($s1['stock_after'] - $s1['stock_before'], 12), json_encode($s1['steps_completed'], JSON_UNESCAPED_UNICODE));
$s2 = $se->ExecuteIntegratedWorkflowStep(['step_type' => 'sale', 'sku' => $sku, 'qty' => 3, 'branch_name' => 'Hallandale Beach'], 1);
ok('Venta del recorrido: −3 y venta en Ventas', $near($branchStock(1, $pid), $b0 + 9) && intval($val("SELECT COUNT(*) FROM sales WHERE sale_number = " . $db->quote($s2['doc_ref']))) === 1, $s2['doc_ref']);
$s3 = $se->ExecuteIntegratedWorkflowStep(['step_type' => 'cycle_count', 'sku' => $sku, 'qty' => $b0 + 8, 'branch_name' => 'Hallandale Beach'], 3);
ok('Conteo del recorrido: diferencia −1 pendiente, stock sin cambios', $near($branchStock(1, $pid), $b0 + 9) && $val("SELECT status FROM stock_cycle_counts WHERE id = " . intval(substr($s3['doc_ref'], 3))) === 'discrepancy');
expectError('Quien contó no aprueba su ajuste', fn() => $se->ExecuteIntegratedWorkflowStep(['step_type' => 'supervisor_decision', 'sku' => $sku, 'branch_name' => 'Hallandale Beach', 'decision' => 'approve'], 3));
$se->ExecuteIntegratedWorkflowStep(['step_type' => 'supervisor_decision', 'sku' => $sku, 'branch_name' => 'Hallandale Beach', 'decision' => 'approve'], 2);
ok('Aprobación: corrección −1 en inventario', $near($branchStock(1, $pid), $b0 + 8));
expectError('Producto inexistente en el recorrido rechazado', fn() => $se->ExecuteIntegratedWorkflowStep(['step_type' => 'sale', 'sku' => 'NO-EXISTE', 'qty' => 1, 'branch_name' => 'Hallandale Beach'], 1));
$lotRow = $db->query("SELECT s.stock_id, s.location_id FROM stock s JOIN locations l ON l.id = s.location_id WHERE s.product_id = $pid AND l.branch_id = 1 AND IFNULL(l.is_transit, 0) = 0 AND s.amount >= 2 ORDER BY s.best_before_date LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$tasks0 = intval($val('SELECT COUNT(*) FROM tasks'));
$f1 = $se->ExecuteFefoAction(['action' => 'rotate_task', 'product_id' => $pid, 'branch_id' => 1, 'lot' => 'L1', 'qty' => 2], 1);
ok('FEFO rotación crea una tarea real', intval($val('SELECT COUNT(*) FROM tasks')) === $tasks0 + 1 && str_starts_with($f1['doc_ref'], 'TAREA-'));
$f2 = $se->ExecuteFefoAction(['action' => 'transfer', 'product_id' => $pid, 'branch_id' => 1, 'location_id' => $lotRow['location_id']], 1);
ok('FEFO traslado lleva a Traslados (no informa un traslado inexistente)', str_starts_with($f2['redirect'] ?? '', '/transfers') && $f2['doc_ref'] === null);
$w0 = intval($val("SELECT COUNT(*) FROM waste_records WHERE product_id = $pid"));
$f3 = $se->ExecuteFefoAction(['action' => 'waste', 'product_id' => $pid, 'branch_id' => 1, 'stock_id' => $lotRow['stock_id'], 'location_id' => $lotRow['location_id'], 'qty' => 2, 'lot' => 'L1', 'operation_id' => 'fefo-test-1'], 1);
ok('FEFO baja registra una merma real en Control de Mermas', intval($val("SELECT COUNT(*) FROM waste_records WHERE product_id = $pid")) === $w0 + 1, $f3['summary']);

echo "\n4c. Personal: ausencia → cobertura → tarea de sustitución\n";
$shift = $db->query("SELECT s.id, s.employee_id, s.branch_id, e.name FROM employee_shifts s JOIN employees e ON e.id = s.employee_id WHERE s.shift_date = '$today' AND s.status = 'published' AND s.start_time <= time('now', 'localtime', '-40 minutes') ORDER BY s.branch_id, s.id LIMIT 1")->fetch(PDO::FETCH_ASSOC);
if ($shift)
{
	$b = intval($shift['branch_id']);
	$l0 = $dm->Labor($b, $today);
	$db->prepare("INSERT INTO employee_absences (employee_id, absence_type, start_date, end_date, status, notes, requested_by_user_id, decided_by_user_id, decided_timestamp) VALUES (?, 'sick', ?, ?, 'approved', 'Prueba de integración', 1, 1, datetime('now', 'localtime'))")
		->execute([$shift['employee_id'], $today, $today]);
	$db->prepare('DELETE FROM employee_attendance WHERE shift_id = ?')->execute([$shift['id']]);
	$l1 = $dm->Labor($b, $today);
	ok('Asistencia: la ausencia aprobada resta horas presentes', $l1['present_hours'] < $l0['present_hours'] || $l0['absent_people'] === $l1['absent_people'] - 1 || $l1['absent_people'] >= 1, json_encode([$l0['present_hours'], $l1['present_hours'], $l1['absent_people']]));
	$h = TodayExecutiveSalesService::GetInstance()->GetExecutiveData($b, $today);
	$wf = array_column($h['process_statuses'], null, 'key')['workforce'];
	ok('HOY informa ausencias en Personal', str_contains($wf['situation'], 'ausencia') || $h['operational_kpis']['staff_coverage']['numeric'] === null, $wf['situation']);
	$r1 = \Grocy\Services\AttendanceService::GetInstance()->SyncCoverageTasks($b, $today);
	$task = $db->query("SELECT * FROM tasks WHERE source_ref = 'cover:shift:" . intval($shift['id']) . "'")->fetch(PDO::FETCH_ASSOC);
	ok('Tareas: sustitución creada en la sucursal del turno', $task && intval($task['branch_id']) === $b && intval($task['done']) === 0, $task['name'] ?? '');
	\Grocy\Services\AttendanceService::GetInstance()->SyncCoverageTasks($b, $today);
	ok('Una sola tarea por turno (sin duplicar)', intval($val("SELECT COUNT(*) FROM tasks WHERE source_ref = 'cover:shift:" . intval($shift['id']) . "'")) === 1);
	ok('Panel Ejecutivo: decisión de personal = estado Personal de HOY', (function () use ($b, $today, $wf) {
		$k = KpiService::GetInstance()->GetExecutiveDashboardData($b, 'day', null, null, $today);
		$p = KpiService::GetInstance()->BuildExecutivePanelOverview($b, 'day', $k, [], 0);
		return array_column($p['priority_decisions'], null, 'title')['Cobertura de personal']['level'] === $wf['status'];
	})());
}
else
{
	ok('Hay un turno publicado ya iniciado hoy para probar la ausencia', false);
}

echo "\n5. Persistencia\n";
$db2 = new PDO('sqlite:' . GROCY_DATAPATH . '/' . $dbName);
ok('Una conexión nueva lee los mismos saldos', $near($db2->query("SELECT IFNULL(SUM(s.amount), 0) FROM stock s JOIN locations l ON l.id = s.location_id WHERE s.product_id = $aud AND l.branch_id = 1 AND IFNULL(l.is_transit, 0) = 0")->fetchColumn(), 6));

echo "\n6. Integridad global\n";
ok('Libro de movimientos = kardex (sin faltantes)', intval($val('SELECT COUNT(*) FROM stock_log WHERE id NOT IN (SELECT stock_log_id FROM dairy_movement_ledger)')) === 0);
ok('Sin existencias negativas', intval($val('SELECT COUNT(*) FROM stock WHERE amount < -0.0001')) === 0);
ok('Movimientos sensibles con stock ligados a su transacción', intval($val("SELECT COUNT(*) FROM sensitive_inventory_movements WHERE created_at >= '$today' AND ((event_type = 'reception') OR (event_type = 'adjustment' AND difference_amount <> 0)) AND stock_transaction_id IS NULL")) === 0);
$sumBranches = 0.0;
foreach ($db->query('SELECT id FROM branches')->fetchAll(PDO::FETCH_COLUMN) as $b)
{
	$sumBranches += $dm->Sales(intval($b), $today, $today)['net_sales'];
}
ok('Ventas del día: suma de sucursales = red', $near($sumBranches, $dm->Sales(null, $today, $today)['net_sales'], 0.05));

echo "\n$count verificaciones, $fails fallidas\n";
exit($fails > 0 ? 1 : 0);
