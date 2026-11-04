<?php
/**
 * Fase 1 — arquitectura central: motor de flujos, catálogo de KPI, motor de movimientos y datos maestros.
 *   php tests/dairy/test_phase1_core.php [ruta/a/grocy.db]
 */

use Grocy\Services\InventoryMovementService;
use Grocy\Services\KpiCatalogService;
use Grocy\Services\KpiService;
use Grocy\Services\MasterDataService;
use Grocy\Services\WorkflowService;

require __DIR__ . '/bootstrap.php';

$wf = WorkflowService::GetInstance();

echo "== Motor de flujos\n";
ok('4 definiciones iniciales', count($wf->Definitions()) >= 4);
$src = 'test-' . getmypid();
$i = $wf->Start('opening_checklist', 'branch_day', $src, ['branch_id' => 1]);
ok('instancia abierta en el primer paso', $i['status'] === 'open' && $i['current_step'] === 'security', $i['status'] . '/' . $i['current_step']);
ok('plazo por defecto aplicado', $i['due_at'] !== null);
$again = $wf->Start('opening_checklist', 'branch_day', $src, ['branch_id' => 1]);
ok('iniciar dos veces el mismo documento = misma instancia', $again['id'] === $i['id']);
ok('un solo evento created', count(array_filter($again['history'], fn($e) => $e['event_type'] === 'created')) === 1);
$id = intval($i['id']);

expectError('paso fuera de orden rechazado', fn() => $wf->CompleteStep($id, 'cash'));
$wf->CompleteStep($id, 'security', null, 'rk-1');
$r = $wf->CompleteStep($id, 'security', null, 'rk-1');
ok('reintento con el mismo request_key no duplica', count(array_filter($r['history'], fn($e) => $e['event_type'] === 'step_completed')) === 1 && $r['current_step'] === 'cold_chain');
expectError('paso con evidencia obligatoria sin evidencia', fn() => $wf->CompleteStep($id));
expectError('evidencia de tipo inválido', fn() => $wf->AddEvidence($id, 'virus', 'x'));
$wf->AddEvidence($id, 'photo', 'files/temp_camara1.jpg', 'Cámara 1: 3 °C');
$wf->CompleteStep($id);
expectError('paso obligatorio no se puede omitir', fn() => $wf->SkipStep($id, 'cash', 'no hay caja'));
$wf->CompleteStep($id);
$wf->CompleteStep($id);
$r = $wf->CompleteStep($id);
ok('paso con aprobación → pendiente de aprobación', $r['status'] === 'pending_approval' && $r['current_step'] === 'supervisor_ok', $r['status']);
expectError('segregación: quien inició no aprueba', fn() => $wf->Approve($id));
expectError('rechazo sin observaciones', fn() => $wf->Reject($id, ''));
// Simula que otro usuario inició y ejecutó los pasos: el usuario de la prueba (ADMIN) puede aprobar
$db->exec("UPDATE daily_workflow_instances SET created_by_user_id = 999 WHERE id = $id");
$db->exec("UPDATE daily_workflow_step_states SET completed_by_user_id = 999 WHERE instance_id = $id");
$r = $wf->Approve($id, 'Conforme');
ok('aprobado por otro usuario → completado', $r['status'] === 'completed' && $r['progress_pct'] == 100, $r['status'] . ' ' . $r['progress_pct']);
expectError('proceso terminado no acepta más pasos', fn() => $wf->CompleteStep($id));
$r = $wf->Close($id, 'ok', 'Apertura sin novedades');
ok('cierre formal registrado', $r['status'] === 'closed' && $r['result'] === 'ok');
$types = array_column($r['history'], 'event_type');
ok('historial completo y ordenado', $types === ['created', 'step_completed', 'evidence_added', 'step_completed', 'step_completed', 'step_completed', 'approval_requested', 'approved', 'completed', 'closed'], implode(',', $types));
expectError('historial inmutable (UPDATE)', fn() => $db->exec("UPDATE daily_workflow_events SET event_type = 'x' WHERE instance_id = $id"));
expectError('historial inmutable (DELETE)', fn() => $db->exec("DELETE FROM daily_workflow_events WHERE instance_id = $id"));

$t = $wf->Start('operational_task', 'manual', $src . '-b', ['priority' => 'critical', 'due_at' => date('Y-m-d H:i:s', time() - 3600)]);
ok('prioridad y plazo explícitos', $t['priority'] === 'critical' && $t['overdue'] === true);
ok('bandeja de vencidos la incluye', in_array($t['id'], array_column($wf->List(['overdue' => 1]), 'id')));
$wf->CompleteStep(intval($t['id']));
$t = $wf->SkipStep(intval($t['id']), 'verify', 'Verificado en el acto');
ok('paso opcional omitido con motivo → completado', $t['status'] === 'completed');
$c = $wf->Start('purchase_proposal', 'mrp_run', $src . '-c');
$c = $wf->Cancel(intval($c['id']), 'Duplicada');
ok('anulación con motivo', $c['status'] === 'cancelled' && $c['result_notes'] === 'Duplicada');
expectError('definición sin pasos rechazada', fn() => $wf->SaveDefinition(['code' => 'bad_one', 'name' => 'x', 'steps' => []]));
$d = $wf->SaveDefinition(['code' => 'test_flow', 'name' => 'Flujo prueba', 'module' => 'test', 'steps' => [['code' => 'a', 'name' => 'A'], ['code' => 'b', 'name' => 'B', 'requires_approval' => true, 'approver_permission' => 'ADMIN']]]);
ok('definición configurable guardada', count($d['steps']) === 2);
$stats = array_column($wf->Stats(), null, 'definition_code');
ok('estadísticas por flujo', intval($stats['opening_checklist']['completed'] ?? 0) >= 1 && intval($stats['operational_task']['completed'] ?? 0) >= 1);

echo "== Catálogo de KPI\n";
$cat = KpiCatalogService::GetInstance();
$defs = $cat->Catalog();
ok('cada KPI con fórmula, unidad, fuente, período y frecuencia', count($defs) >= 15 && !array_filter($defs, fn($k) => empty($k['formula']) || empty($k['unit']) || empty($k['source']) || empty($k['period']) || empty($k['frequency'])));
$month = $val("SELECT strftime('%Y-%m', created_timestamp) FROM sales WHERE status = 'completed' GROUP BY 1 ORDER BY COUNT(*) DESC LIMIT 1") ?: date('Y-m');
$start = $month . '-01';
$end = date('Y-m-t', strtotime($start));
$kpi = KpiService::GetInstance()->GetExecutiveDashboardData(null, 'custom', $start, $end);
$v = $cat->Compute([], null, $start, $end);
$same = fn($a, $b) => abs(floatval($a) - floatval($b)) < 0.011;
ok('ventas = Panel Ejecutivo', $same($v['gross_sales']['value'], $kpi['total_sales']), $v['gross_sales']['value'] . ' vs ' . $kpi['total_sales']);
ok('ventas netas = Panel Ejecutivo', $same($v['net_sales']['value'], $kpi['net_sales']));
ok('margen % = Panel Ejecutivo', $same($v['margin_pct']['value'], $kpi['margin_percentage']), $v['margin_pct']['value'] . ' vs ' . $kpi['margin_percentage']);
ok('merma a costo = Panel Ejecutivo', $same($v['waste_cost']['value'], $kpi['total_waste_cost']));
ok('merma % = Panel Ejecutivo', $same($v['waste_pct']['value'] ?? 0, $kpi['waste_ratio_percentage']), ($v['waste_pct']['value'] ?? 'null') . ' vs ' . $kpi['waste_ratio_percentage']);
ok('recuperación = Panel Ejecutivo', $same($v['waste_recovered']['value'], $kpi['waste_recovered']));
ok('inventario valorizado = Panel Ejecutivo', $same($v['inventory_value']['value'], $kpi['total_inventory_valuation']));
ok('rotación = Panel Ejecutivo', $same($v['inventory_turnover']['value'] ?? 0, $kpi['inventory_turnover']));
ok('transacciones = Panel Ejecutivo', intval($v['tickets']['value']) === intval($kpi['total_tickets']));
$proj = $kpi['month_projection'];
if (($proj['sales_target_source'] ?? '') === 'budget')
{
	ok('cumplimiento de meta = Panel Ejecutivo (mes completo)', $same($v['sales_goal_attainment']['value'], $proj['sales_target_progress_pct']));
}
else
{
	ok('sin meta presupuestada → null con motivo', $v['sales_goal_attainment']['value'] === null && $v['sales_goal_attainment']['validation'] !== null, (string) $v['sales_goal_attainment']['validation']);
}
$byBranch = 0.0;
foreach ($db->query('SELECT id FROM branches')->fetchAll(PDO::FETCH_COLUMN) as $b)
{
	$byBranch += $cat->Compute(['net_sales'], intval($b), $start, $end)['net_sales']['value'];
}
ok('Σ ventas netas por sucursal = red', $same($byBranch, $v['net_sales']['value']), round($byBranch, 2) . ' vs ' . $v['net_sales']['value']);
expectError('rango invertido rechazado', fn() => $cat->Compute([], null, $end, $start));
expectError('KPI desconocido rechazado', fn() => $cat->Compute(['nope'], null, $start, $end));
$cat->SaveSettings('waste_pct', ['warn_threshold' => 0.0001, 'critical_threshold' => 0.0002]);
$st = $cat->Compute(['waste_pct'], null, $start, $end)['waste_pct'];
ok('umbral configurado clasifica el estado', $st['value'] === null ? $st['status'] === 'no_data' : $st['status'] === 'critical', $st['status']);

echo "== Motor de movimientos\n";
$mv = InventoryMovementService::GetInstance();
ok('11 tipos: 7 físicos y 4 de disponibilidad', count(array_filter($mv->Types(), fn($t) => $t['affects'] === 'physical')) === 7 && count(array_filter($mv->Types(), fn($t) => $t['affects'] === 'availability')) === 4);
$from = date('Y-m-d', strtotime('-60 days'));
$ledger = $mv->Ledger(['from' => $from, 'to' => date('Y-m-d')], 5000);
ok('libro con movimientos físicos', count(array_filter($ledger, fn($r) => $r['affects'] === 'physical')) > 0, count($ledger));
ok('cada fila con id, documento, fecha, producto, cantidad, tipo y estado', !array_filter($ledger, fn($r) => !$r['uid'] || $r['source_id'] === null || !$r['occurred_at'] || !$r['product_id'] || !$r['type'] || !$r['status']));
ok('ids únicos en el libro', count(array_unique(array_column($ledger, 'uid'))) === count($ledger));
$from3 = date('Y-m-d', strtotime('-3 days'));
$physLedger = $mv->Ledger(['from' => $from3, 'affects' => 'physical'], 5000);
$phys = floatval($val("SELECT IFNULL(SUM(amount), 0) FROM merchandise_movements WHERE undone = 0 AND date(recorded_at) BETWEEN '$from3' AND date('now', 'localtime')"));
$fromLedger = array_sum(array_map(fn($r) => $r['status'] === 'posted' ? $r['amount'] : 0, $physLedger));
if (count($physLedger) < 5000)
{
	ok('Σ físico del libro = Σ merchandise_movements', abs($phys - $fromLedger) < 0.001, round($fromLedger, 3) . ' vs ' . round($phys, 3));
}
ok('filtro por tipo devuelve solo ese tipo', !array_filter($mv->Ledger(['type' => 'sale', 'from' => $from], 300), fn($r) => $r['type'] !== 'sale'));
expectError('tipo desconocido rechazado', fn() => $mv->Ledger(['type' => 'robo']));

// Posición: reservado y bloqueado deben coincidir con el libro de disponibilidad en todos los productos con reservas o cuarentena
$pairs = $db->query("SELECT DISTINCT i.product_id, o.branch_id FROM customer_order_items i JOIN customer_orders o ON o.id = i.order_id
	UNION SELECT DISTINCT product_id, branch_id FROM quarantine_items LIMIT 300")->fetchAll(PDO::FETCH_NUM);
$bad = [];
foreach ($pairs as [$p, $b])
{
	$pos = $mv->Position(intval($p), intval($b));
	if (!$pos['consistency']['reserved_ok'] || !$pos['consistency']['blocked_ok'])
	{
		$bad[] = "p$p/b$b r{$pos['reserved']}≠{$pos['consistency']['reserved_ledger']} q{$pos['blocked']}≠{$pos['consistency']['blocked_ledger']}";
	}
}
ok('reservado y bloqueado = saldo del libro de disponibilidad (' . count($pairs) . ' pares)', !$bad, implode('; ', array_slice($bad, 0, 5)));
$pos = $pairs ? $mv->Position(intval($pairs[0][0]), intval($pairs[0][1])) : null;
ok('disponible = físico − bloqueado − vencido − merma pendiente − reservado', !$pos || abs(max(0, $pos['physical'] - $pos['blocked'] - $pos['expired'] - $pos['committed_waste'] - $pos['reserved']) - $pos['available']) < 0.0001);
$dup = $mv->DuplicateCheck(30, 5);
ok('control de duplicados y sello del libro ejecutan', isset($dup['possible_duplicates'], $dup['ledger_seal']), count($dup['possible_duplicates']) . ' posibles duplicados');

echo "== Datos maestros\n";
$md = MasterDataService::GetInstance();
$reg = $md->Registry();
ok('registro de 14 entidades con tabla canónica', count($reg) === 14 && !array_filter($reg, fn($e) => $e['rows'] < 0));
$integ = $md->Integrity();
ok('14 controles de integridad ejecutan', count($integ['checks']) === 14, $integ['issues'] . ' hallazgos');
foreach ($integ['checks'] as $c)
{
	if ($c['count'] > 0)
	{
		echo "  --   {$c['code']} ({$c['severity']}): {$c['count']}\n";
	}
}

echo "\n$count verificaciones, $fails fallidas\n";
exit($fails ? 1 : 0);
