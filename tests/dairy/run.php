<?php
/**
 * Pruebas de integración DAIRY sobre una COPIA de la base de datos (nunca toca la original).
 *
 *   php tests/dairy/run.php [ruta/a/grocy.db]
 *
 * Sin argumento usa data/grocy_<cultura>.db en modo demo o data/grocy.db. Cubre:
 *  - Flujo integral: OC → recepción con lote/vencimiento → merma (aprobación con segregación) → descuento de stock
 *    → costo → KPIs → MRP → tarea correctiva y agenda del gerente → kardex/auditoría; idempotencia (sin doble conteo).
 *  - Presupuesto (metas y validaciones), personal y turnos (conflictos, ausencias, cambios, fichaje, cobertura).
 */

use Grocy\Services\BudgetService;
use Grocy\Services\KardexService;
use Grocy\Services\KpiService;
use Grocy\Services\ManagerAgendaService;
use Grocy\Services\MrpService;
use Grocy\Services\WasteService;
use Grocy\Services\WorkforceScheduleService;

require __DIR__ . '/bootstrap.php';

// ---------------------------------------------------------------- 1. Flujo integral de merma
echo "\n1. Recepción → merma → KPIs → MRP → tarea → auditoría\n";
$product = $db->query("SELECT p.id, p.name, p.location_id, p.default_best_before_days FROM products p JOIN locations l ON l.id = p.location_id WHERE p.active = 1 AND l.branch_id = 1 AND p.default_best_before_days > 0 ORDER BY (lower(p.name) LIKE '%leche%') DESC, p.id LIMIT 1")->fetch(PDO::FETCH_OBJ);
$supplier = intval($val('SELECT id FROM shopping_locations WHERE active = 1 ORDER BY id LIMIT 1'));
$reason = $db->query("SELECT * FROM waste_reasons WHERE active = 1 AND requires_evidence = 0 AND requires_approval = 1 ORDER BY (akcal_classification = 'averia') DESC, id LIMIT 1")->fetch(PDO::FETCH_OBJ);
if (!$product || !$supplier || !$reason)
{
	ok('datos maestros disponibles (producto con vida útil en sucursal 1, proveedor, causal con aprobación)', false);
	exit(1);
}
echo "  Producto #{$product->id} {$product->name} · proveedor #$supplier · causal {$reason->code}\n";

$mrp = MrpService::GetInstance();
$kpiBefore = KpiService::GetInstance()->GetExecutiveDashboardData(1, 'month');
$stockBefore = floatval($val("SELECT IFNULL(SUM(amount), 0) FROM stock WHERE product_id = {$product->id}"));

$order = $mrp->CreateProcurementOrder(['branch_id' => 1, 'supplier_id' => $supplier, 'items' => [['product_id' => $product->id, 'amount' => 120, 'unit_price' => 3.5]]], 1);
$mrp->ApproveProcurementOrder($order['id'], 1);
$mrp->SendProcurementOrder($order['id'], 1, date('Y-m-d'));
$detail = $mrp->GetProcurementOrderDetail($order['id']);
$bbd = date('Y-m-d', strtotime('+' . $product->default_best_before_days . ' days'));
$mrp->ReceiveProcurementOrder($order['id'], ['items' => [[
	'order_item_id' => $detail['items'][0]['id'], 'delivered_amount' => 120, 'best_before_date' => $bbd, 'location_id' => $product->location_id
]]], 1);
$stockAfterReceipt = floatval($val("SELECT IFNULL(SUM(amount), 0) FROM stock WHERE product_id = {$product->id}"));
ok('recepción suma 120 al stock', abs($stockAfterReceipt - $stockBefore - 120) < 0.001, "$stockBefore → $stockAfterReceipt");
ok('OC queda recibida', $mrp->GetProcurementOrderDetail($order['id'])['order']['status'] === 'received');
$lot = $db->query("SELECT stock_id, best_before_date FROM stock WHERE product_id = {$product->id} ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_OBJ);
ok('lote con vencimiento registrado', $lot && $lot->best_before_date === $bbd, $lot->stock_id ?? '');

// Conciliación de 3 vías: factura correcta, luego una que cobra de más
$inv = \Grocy\Services\SupplierInvoiceService::GetInstance();
$oiId = intval($detail['items'][0]['id']);
$f1 = $inv->RegisterInvoice($order['id'], ['invoice_number' => 'T-' . uniqid(), 'invoice_date' => date('Y-m-d'), 'tax_amount' => 0, 'items' => [['order_item_id' => $oiId, 'invoiced_amount' => 120, 'unit_price' => 3.5]]], 1);
ok('factura igual a OC y recepción queda conciliada', $f1['status'] === 'matched' && $f1['amount_at_risk'] == 0);
expectError('número de factura duplicado', fn() => $inv->RegisterInvoice($order['id'], ['invoice_number' => $db->query("SELECT invoice_number FROM supplier_invoices WHERE id = {$f1['id']}")->fetchColumn(), 'invoice_date' => date('Y-m-d'), 'items' => [['order_item_id' => $oiId, 'invoiced_amount' => 1, 'unit_price' => 3.5]]], 1));
$f2 = $inv->RegisterInvoice($order['id'], ['invoice_number' => 'T2-' . uniqid(), 'invoice_date' => date('Y-m-d'), 'items' => [['order_item_id' => $oiId, 'invoiced_amount' => 10, 'unit_price' => 3.9]]], 1);
$issues = $db->query("SELECT issues FROM supplier_invoice_items WHERE invoice_id = {$f2['id']}")->fetchColumn();
ok('segunda factura detecta cobro de lo no recibido y sobreprecio', $f2['status'] === 'discrepancy' && str_contains($issues, 'qty_over_received') && str_contains($issues, 'price_over_po'), $issues);
ok('monto en riesgo = 10 × 3.90', abs($f2['amount_at_risk'] - 39) < 0.01, $f2['amount_at_risk']);
expectError('quien registró no puede decidir', fn() => $inv->DecideInvoice($f2['id'], false, 'x', 1));
$inv->DecideInvoice($f2['id'], false, 'Cobro de mercancía no recibida', 2);
ok('rechazada por otro usuario', $db->query("SELECT status FROM supplier_invoices WHERE id = {$f2['id']}")->fetchColumn() === 'rejected');
$fdoc = \Grocy\Services\DocumentService::GetInstance()->GetDocument('invoice', $f2['id']);
ok('documento de factura con folio, líneas y decisión', $fdoc['status'] === 'Rechazado' && count($fdoc['rows']) === 1 && str_contains($fdoc['notes'] ?? '', 'Cobro de mercancía'));

$waste = WasteService::GetInstance();
$key = 'test-' . uniqid();
$rec = $waste->ReportWaste($product->id, 8, intval($reason->id), 1, intval($product->location_id), $lot->stock_id, 'Prueba E2E: 8 dañadas en recepción', null, null, null, $bbd, null, null, $key, 2);
ok('merma queda pendiente de aprobación', $rec->status === 'pending_approval');
ok('pendiente no descuenta stock', abs(floatval($val("SELECT SUM(amount) FROM stock WHERE product_id = {$product->id}")) - $stockAfterReceipt) < 0.001);
$dup = $waste->ReportWaste($product->id, 8, intval($reason->id), 1, intval($product->location_id), $lot->stock_id, 'reintento', null, null, null, $bbd, null, null, $key, 2);
ok('reintento con la misma clave no duplica', intval($dup->id) === intval($rec->id));
expectError('quien reporta no puede aprobar', fn() => $waste->ApproveWaste(intval($rec->id), 2));
$approved = $waste->ApproveWaste(intval($rec->id), 1);
$waste->ApproveWaste(intval($rec->id), 1); // idempotente
$stockAfterWaste = floatval($val("SELECT SUM(amount) FROM stock WHERE product_id = {$product->id}"));
ok('aprobación descuenta 8 una sola vez', abs($stockAfterReceipt - $stockAfterWaste - 8) < 0.001, "$stockAfterReceipt → $stockAfterWaste");
ok('costo = 8 × costo del lote (3.50)', abs(floatval($approved->total_cost) - 28.0) < 0.01, $approved->total_cost);
ok('movimiento de stock marcado como merma (spoiled)', intval($val("SELECT COUNT(*) FROM stock_log WHERE transaction_id = '{$approved->transaction_id}' AND spoiled = 1")) > 0);

$kpiAfter = KpiService::GetInstance()->GetExecutiveDashboardData(1, 'month');
ok('KPI merma del mes sube 28', abs($kpiAfter['total_waste_cost'] - $kpiBefore['total_waste_cost'] - 28) < 0.01, $kpiBefore['total_waste_cost'] . ' → ' . $kpiAfter['total_waste_cost']);
ok('sparklines con datos reales (suma = merma del período)', abs(array_sum($kpiAfter['sparklines']['waste']) - $kpiAfter['total_waste_cost']) < 0.05);
ok('merma por tipo suma el total', abs(array_sum(array_column($kpiAfter['waste_by_type'], 'cost')) - $kpiAfter['total_waste_cost']) < 0.05);

$mrpItem = array_values(array_filter($mrp->CalculateMrp(1)['items'], fn($i) => intval($i['product_id']) === intval($product->id)))[0] ?? null;
ok('MRP ve el stock neto actualizado', $mrpItem !== null && abs(floatval($mrpItem['stock'] ?? $mrpItem['current_stock'] ?? -1) - floatval($val("SELECT IFNULL(SUM(s.amount), 0) FROM stock s JOIN locations l ON l.id = s.location_id WHERE s.product_id = {$product->id} AND l.branch_id = 1"))) < 0.01, json_encode(array_intersect_key($mrpItem ?? [], array_flip(['stock', 'current_stock', 'suggested_qty', 'status']))));

$task = $db->query("SELECT * FROM workforce_store_tasks WHERE source_type = 'waste' AND source_id = {$rec->id}")->fetch(PDO::FETCH_OBJ);
ok('tarea correctiva creada (pérdida ≥ WASTE_CORRECTIVE_TASK_MIN_COST)', $task !== false && $task->task_type === 'corrective_action', $task->title ?? '');
ok('una sola tarea aunque se apruebe dos veces', intval($val("SELECT COUNT(*) FROM workforce_store_tasks WHERE source_type = 'waste' AND source_id = {$rec->id}")) === 1);
$agenda = ManagerAgendaService::GetInstance()->GetAgenda(1);
ok('agenda del gerente muestra la acción correctiva', count(array_filter($agenda, fn($i) => $i['key'] === 'corrective')) === 1);
ok('bitácora de la merma (registro + aprobación)', intval($val("SELECT COUNT(*) FROM waste_action_logs WHERE waste_record_id = {$rec->id}")) >= 2);
$kardex = KardexService::GetInstance()->GetKardex(intval($product->id), 1, $lot->stock_id);
$docs = implode(',', array_unique(array_filter(array_map(fn($l) => substr(strval($l['document']['folio'] ?? ''), 0, 3), $kardex['rows']))));
ok('kardex del lote enlaza recepción y merma', str_contains($docs, 'REC') && str_contains($docs, 'MRM'), $docs);

// ---------------------------------------------------------------- 2. Presupuesto
echo "\n2. Presupuesto y metas\n";
$budget = BudgetService::GetInstance();
expectError('período inválido', fn() => $budget->SaveBudget(['period' => '2026-13'], 1));
expectError('% fuera de rango', fn() => $budget->SaveBudget(['period' => date('Y-m'), 'waste_target_pct' => 150], 1));
$budget->SaveBudget(['branch_id' => 0, 'period' => date('Y-m'), 'waste_target_pct' => 3, 'waste_budget_amount' => 900], 1);
$budget->SaveBudget(['branch_id' => 1, 'period' => date('Y-m'), 'waste_target_pct' => 2], 1);
$t = $budget->GetTargets(1, date('Y-m'));
ok('meta de la sucursal prevalece sobre la red', $t['waste_target_pct'] == 2 && $t['sources']['waste_pct'] === 'budget_branch');
ok('campo vacío en sucursal hereda de la red', $t['waste_budget_amount'] == 900 && $t['sources']['waste_amount'] === 'budget_network');
ok('sin presupuesto usa configuración', $budget->GetTargets(1, '2001-01')['sources']['margin'] === 'config');
$k = KpiService::GetInstance()->GetExecutiveDashboardData(1, 'month');
ok('panel usa la meta presupuestada', $k['month_projection']['waste_target_ratio_pct'] == 2 && $k['month_projection']['waste_budget_deviation'] !== null);

// ---------------------------------------------------------------- 3. Personal y turnos
echo "\n3. Personal y turnos\n";
$ws = WorkforceScheduleService::GetInstance();
$users = $db->query('SELECT id FROM users WHERE id NOT IN (SELECT user_id FROM employees WHERE user_id IS NOT NULL) ORDER BY id LIMIT 3')->fetchAll(PDO::FETCH_COLUMN);
$e1 = $ws->SaveEmployee(['name' => 'Prueba Uno', 'branch_id' => 1, 'user_id' => $users[1] ?? null, 'weekly_hours' => 16, 'hourly_cost' => 5], 1);
$e2 = $ws->SaveEmployee(['name' => 'Prueba Dos', 'branch_id' => 1, 'user_id' => $users[2] ?? null], 1);
expectError('usuario vinculado a dos empleados', fn() => $ws->SaveEmployee(['name' => 'X', 'branch_id' => 1, 'user_id' => $users[1]], 1));
$monday = date('Y-m-d', strtotime('monday next week'));
$tue = date('Y-m-d', strtotime("$monday +1 day"));
$s1 = $ws->SaveShift(['employee_id' => $e1, 'shift_date' => $monday, 'start_time' => '06:00', 'end_time' => '14:00', 'break_minutes' => 30], 1);
ok('turno creado', $s1['id'] > 0);
expectError('solapamiento bloquea', fn() => $ws->SaveShift(['employee_id' => $e1, 'shift_date' => $monday, 'start_time' => '13:00', 'end_time' => '18:00'], 1));
$late = $ws->SaveShift(['employee_id' => $e1, 'shift_date' => $tue, 'start_time' => '22:00', 'end_time' => '06:00'], 1);
ok('turno nocturno (cruza medianoche) permitido', $late['id'] > 0);
$rest = $ws->SaveShift(['employee_id' => $e1, 'shift_date' => date('Y-m-d', strtotime("$monday +2 day")), 'start_time' => '10:00', 'end_time' => '14:00'], 1);
ok('descanso corto se advierte', count(array_filter($rest['warnings'], fn($w) => $w['code'] === 'rest')) > 0);
$ot = $ws->SaveShift(['employee_id' => $e1, 'shift_date' => date('Y-m-d', strtotime("$monday +4 day")), 'start_time' => '06:00', 'end_time' => '14:00'], 1);
ok('horas sobre contrato se advierten', count(array_filter($ot['warnings'], fn($w) => $w['code'] === 'overtime')) > 0);
$abs = $ws->RequestAbsence(['employee_id' => $e2, 'absence_type' => 'sick', 'start_date' => $tue, 'end_date' => $tue], 1);
$ws->DecideAbsence($abs, true, 1);
expectError('ausencia aprobada bloquea el turno', fn() => $ws->SaveShift(['employee_id' => $e2, 'shift_date' => $tue, 'start_time' => '08:00', 'end_time' => '12:00'], 1));
$ws->SetAvailability($e2, [['weekday' => 1, 'start_time' => '08:00', 'end_time' => '16:00']]);
$av = $ws->SaveShift(['employee_id' => $e2, 'shift_date' => $monday, 'start_time' => '14:00', 'end_time' => '20:00'], 1);
ok('fuera de disponibilidad se advierte', count(array_filter($av['warnings'], fn($w) => $w['code'] === 'availability')) > 0);
$moved = $ws->MoveShift($av['id'], $e1, date('Y-m-d', strtotime("$monday +5 day")), 1);
ok('arrastrar turno a otro empleado/día', intval($ws->GetShift($av['id'])->employee_id) === $e1);
$swap = $ws->RequestSwap($s1['id'], $e2, 1);
$ws->DecideSwap($swap, true, 1);
ok('cambio de turno aprobado reasigna', intval($ws->GetShift($s1['id'])->employee_id) === $e2);
$ws->SaveCoverageRequirements(1, [['weekday' => 0, 'start_time' => '06:00', 'end_time' => '22:00', 'min_staff' => 2]]);
$week = $ws->GetWeek(1, $monday);
ok('cobertura detecta faltantes', count($week['coverage'][$monday]['gaps']) > 0, count($week['coverage'][$monday]['gaps']) . ' h');
ok('totales de horas programadas', $week['totals'][$e2]['scheduled'] == 7.5, $week['totals'][$e2]['scheduled']);
ok('publicar semana', $ws->PublishWeek(1, $monday) > 0);
$copy = $ws->CopyWeek(1, $monday, date('Y-m-d', strtotime("$monday +7 day")), 1);
ok('copiar semana', $copy['created'] > 0, $copy['created'] . ' creados, ' . count($copy['skipped']) . ' omitidos');

// Fichaje con turno vigente y merma con contexto de turno
$today = date('Y-m-d');
$nowShift = $ws->SaveShift(['employee_id' => $e2, 'shift_date' => date('Y-m-d', time() - 3600), 'start_time' => date('H:i', time() - 3600), 'end_time' => date('H:i', time() + 3600)], 1);
$in = $ws->ClockIn($e2, 'mobile', 1);
ok('entrada vinculada al turno', intval($in['shift_id']) === intval($nowShift['id']));
expectError('doble entrada', fn() => $ws->ClockIn($e2, 'web', 1));
$out = $ws->ClockOut($e2, 1);
ok('salida registrada', $out['hours'] >= 0);
expectError('registro manual exige motivo', fn() => $ws->SaveAttendance(['employee_id' => $e1, 'clock_in' => date('Y-m-d 08:00', strtotime('-1 day')), 'clock_out' => date('Y-m-d 12:00', strtotime('-1 day'))], 1));
// Etapa 25: el registro manual es una solicitud; solo suma horas al aprobarla otra persona
$adjReq = $ws->SaveAttendance(['employee_id' => $e1, 'clock_in' => date('Y-m-d 08:00', strtotime('-1 day')), 'clock_out' => date('Y-m-d 12:00', strtotime('-1 day')), 'notes' => 'olvidó fichar'], 1);
ok('registro manual pendiente no suma horas', ($ws->WorkedHoursByEmployee(date('Y-m-d', strtotime('-1 day')), $today)[$e1] ?? 0) == 0);
$ws->DecideAttendanceAdjustment($adjReq, true, intval($users[2] ?? 0));
ok('horas trabajadas suman el registro manual', ($ws->WorkedHoursByEmployee(date('Y-m-d', strtotime('-1 day')), $today)[$e1] ?? 0) == 4);
if (isset($users[2]))
{
	$r2 = $waste->ReportWaste($product->id, 1, intval($reason->id), 1, intval($product->location_id), 'default', 'merma en turno', null, null, null, null, null, null, 'test-' . uniqid(), intval($users[2]));
	ok('merma guarda el turno vigente de quien la registra', intval($val("SELECT shift_id FROM waste_records WHERE id = {$r2->id}")) === intval($nowShift['id']));
}
$prod = $ws->GetProductivity(1, $monday, date('Y-m-d', strtotime("$monday +6 day")));
ok('productividad por empleado', count($prod) >= 2);

echo "\n4. Conteo cíclico por ABC\n";
$cc = \Grocy\Services\CycleCountService::GetInstance();
$prev = $cc->PlanByAbc(1, true, 1);
$pendingBefore = intval($val("SELECT COUNT(*) FROM stock_cycle_counts WHERE status = 'pending'"));
$run1 = $cc->PlanByAbc(1, false, 1);
ok('ABC: programa lo que anunció la vista previa (' . $run1['scheduled'] . ')', $run1['scheduled'] === count($prev['due']));
ok('ABC: conteos creados en BD', intval($val("SELECT COUNT(*) FROM stock_cycle_counts WHERE status = 'pending'")) === $pendingBefore + $run1['scheduled']);
ok('ABC: no duplica pendientes', $cc->PlanByAbc(1, false, 1)['scheduled'] === 0);
ok('ABC: solo ubicaciones de la sucursal', intval($val("SELECT COUNT(*) FROM stock_cycle_counts c JOIN locations l ON l.id = c.location_id WHERE c.notes LIKE 'Automático ABC%' AND l.branch_id != 1")) === 0);
expectError('ABC: exige sucursal', fn() => $cc->PlanByAbc(0, true, 1));

echo "\n5. Conteo ciego: stock del sistema al contar, una sola ejecución\n";
$cp = $db->query('SELECT s.product_id, s.location_id, SUM(s.amount) AS qty FROM stock s JOIN locations l ON l.id = s.location_id WHERE l.branch_id = 1 GROUP BY s.product_id, s.location_id HAVING SUM(s.amount) > 2 LIMIT 1')->fetch(PDO::FETCH_OBJ);
$db->exec("INSERT INTO stock_cycle_counts (branch_id, product_id, location_id, scheduled_date, status, theoretical_stock, assigned_user_id) VALUES (1, {$cp->product_id}, {$cp->location_id}, date('now'), 'pending', 99999, 1)");
$ccId = intval($db->lastInsertId());
expectError('conteo: rechaza cantidad negativa', fn() => $cc->Execute($ccId, -1, null, 1));
expectError('conteo: exige cantidad', fn() => $cc->Execute($ccId, '', null, 1));
$ex = $cc->Execute($ccId, floatval($cp->qty), 'ok', 1);
ok('conteo: stock del sistema tomado al contar (programado con 99999)', $ex['status'] === 'completed' && abs($ex['theoretical_stock'] - floatval($cp->qty)) < 0.001);
expectError('conteo: no se re-ejecuta un conteo registrado', fn() => $cc->Execute($ccId, 0, null, 1));
$db->exec("INSERT INTO stock_cycle_counts (branch_id, product_id, location_id, scheduled_date, status, theoretical_stock, assigned_user_id) VALUES (1, {$cp->product_id}, {$cp->location_id}, date('now'), 'pending', 0, 1)");
$ccId2 = intval($db->lastInsertId());
$ex2 = $cc->Execute($ccId2, floatval($cp->qty) - 1, null, 1);
ok('conteo: faltante de 1 u. queda como diferencia por aprobar', $ex2['status'] === 'discrepancy' && abs($ex2['difference_amount'] + 1) < 0.001);
ok('conteo: no toca el stock antes de aprobar', abs(floatval($val("SELECT SUM(amount) FROM stock WHERE product_id = {$cp->product_id} AND location_id = {$cp->location_id}")) - floatval($cp->qty)) < 0.001);

echo "\n6. Escalamiento de tareas de piso vencidas\n";
$late = date('Y-m-d H:i:s', time() - 60 * (GROCY_STORE_TASK_ESCALATION_MINUTES + 5));
$soon = date('Y-m-d H:i:s', time() + 3600);
$db->exec("UPDATE workforce_store_tasks SET status = 'completed' WHERE status = 'pending' AND branch_id = 1");
$db->exec("INSERT INTO workforce_store_tasks (branch_id, task_type, assigned_role, title, due_time, status) VALUES (1, 'temperature_check', 'clerk', 'TEST vencida', '$late', 'pending'), (1, 'temperature_check', 'clerk', 'TEST a tiempo', '$soon', 'pending')");
$cards = array_column(ManagerAgendaService::GetInstance()->GetAgenda(1), null, 'key');
$names = fn($k) => array_column($cards[$k]['rows'] ?? [], 'name');
ok('escalamiento: la vencida aparece en "Tareas de piso vencidas"', $names('tasks_overdue') === ['TEST vencida']);
ok('escalamiento: pendientes solo muestra la que está en plazo', $names('tasks') === ['TEST a tiempo']);
ok('escalamiento: tarjeta de vencidas con prioridad alta', ($cards['tasks_overdue']['tone'] ?? '') === 'danger');
$tid = intval($val("SELECT id FROM workforce_store_tasks WHERE title = 'TEST vencida'"));
\Grocy\Services\WorkforceService::GetInstance()->CompleteStoreTask($tid, ['notes' => 'hecho'], 1);
ok('tarea completada guarda hora local', abs(strtotime($val("SELECT completed_at FROM workforce_store_tasks WHERE id = $tid")) - time()) < 120);
ok('tarea completada sale de la agenda', !isset(array_column(ManagerAgendaService::GetInstance()->GetAgenda(1), null, 'key')['tasks_overdue']));

echo "\n$count verificaciones, $fails fallidas\n";

// Pruebas adicionales en procesos separados (otro usuario / su propia copia de la BD)
foreach (glob(__DIR__ . '/test_*.php') as $extra)
{
	echo "\n== " . basename($extra) . " ==\n";
	passthru(escapeshellarg(PHP_BINARY) . ' -d opcache.enable=0 ' . escapeshellarg($extra) . (isset($argv[1]) ? ' ' . escapeshellarg($argv[1]) : ''), $code);
	$fails += $code !== 0 ? 1 : 0;
}
exit($fails > 0 ? 1 : 0);
