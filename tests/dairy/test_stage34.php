<?php
/**
 * Etapa 34 — pendientes reales: pronóstico → MRP, pérdidas evitadas, acciones de Arturito con confirmación,
 * reportes personalizables/programados, simulador sin supuestos ocultos y pruebas directas de Calidad,
 * Proveedores, Centros de costo y Arturito (AdvisorQa).
 */

require __DIR__ . '/bootstrap.php';

use Grocy\Services\AiActionService;
use Grocy\Services\AlertCenterService;
use Grocy\Services\CostCenterService;
use Grocy\Services\MrpService;
use Grocy\Services\QualityExpiryService;
use Grocy\Services\ReportBuilderService;
use Grocy\Services\SimulatorService;
use Grocy\Services\StockService;
use Grocy\Services\SupplierProcurementService;
use Grocy\Controllers\Users\User;

method_exists($db, 'createFunction') ? $db->createFunction('dairy_user_id', fn() => 1, 0) : $db->sqliteCreateFunction('dairy_user_id', fn() => 1, 0);
$changes = fn() => intval($db->query('SELECT total_changes()')->fetchColumn());

/* ------------------------------------------------------------------ 1. Pronóstico de ventas → MRP */
echo "== Pronóstico → MRP\n";
$db->exec('DELETE FROM labor_calendar_events');
$base = MrpService::GetInstance()->CalculateMrp(1, 14);
$withDemand = array_values(array_filter($base['items'], fn($i) => $i['vmd'] > 0));
ok('hay productos con venta para probar', count($withDemand) > 0, count($withDemand));
$p0 = $withDemand[0];
ok('el MRP informa el pronóstico aplicado', isset($base['forecast']['applied']) && isset($base['forecast']['correction']['factor']));
ok('cada ítem trae la demanda pronosticada del ciclo (lead time + revisión)', isset($p0['forecast_demand']) && $p0['forecast_demand'] > 0, $p0['forecast_demand'] ?? '-');
for ($k = 1; $k <= 60; $k++)
{
	$db->prepare("INSERT INTO labor_calendar_events (branch_id, event_date, kind, name, sales_factor, extra_hours) VALUES (0, ?, 'promotion', 'Prueba x2', 2, 0)")->execute([date('Y-m-d', strtotime("+$k days"))]);
}
$ev = MrpService::GetInstance()->CalculateMrp(1, 14);
$p1 = array_values(array_filter($ev['items'], fn($i) => $i['product_id'] == $p0['product_id']))[0];
ok('un evento ×2 en todo el horizonte duplica la demanda pronosticada', abs($p1['forecast_demand'] - 2 * $p0['forecast_demand']) <= 0.2, $p0['forecast_demand'] . ' → ' . $p1['forecast_demand']);
ok('y sube el punto de pedido', $p1['rop'] > $p0['rop'], $p0['rop'] . ' → ' . $p1['rop']);
ok('la VMD (historia) no cambia: el evento es futuro', $p1['vmd'] == $p0['vmd']);
ok('el MRP lista los eventos usados', count($ev['forecast']['events']) === 60 && $ev['forecast']['applied']);
$db->exec('DELETE FROM labor_calendar_events');

/* ------------------------------------------------------------------ 2. Pérdidas evitadas */
echo "== Pérdidas evitadas\n";
$lot = $db->query("SELECT s.stock_id, s.product_id, s.amount, s.price, s.location_id, l.branch_id FROM stock s JOIN locations l ON l.id = s.location_id WHERE s.amount >= 4 AND IFNULL(s.price, 0) > 0 AND IFNULL(l.is_transit, 0) = 0 AND l.branch_id IS NOT NULL ORDER BY s.id LIMIT 1")->fetch(PDO::FETCH_ASSOC);
ok('lote con stock y costo para la prueba', (bool) $lot);
$bbd = date('Y-m-d', strtotime('+2 days'));
$created = date('Y-m-d H:i:s', time() - 60);
$db->prepare("INSERT INTO dairy_alerts (rule_code, dedupe_key, branch_id, severity, title, detail, status, created_at, updated_at, last_seen_at) VALUES ('expiring_lots', ?, ?, 'warning', 'Lote por vencer: prueba', '', 'open', ?, ?, ?)")
	->execute(['expiring_lots:' . $lot['stock_id'] . ':' . $bbd, $lot['branch_id'], $created, $created, $created]);
$alertId = intval($db->lastInsertId());
$tid = null;
StockService::GetInstance()->ConsumeProduct(intval($lot['product_id']), 2, false, StockService::TRANSACTION_TYPE_CONSUME, $lot['stock_id'], null, null, $tid, false, true);
$tid = null;
StockService::GetInstance()->ConsumeProduct(intval($lot['product_id']), 1, true, StockService::TRANSACTION_TYPE_CONSUME, $lot['stock_id'], null, null, $tid, false, true);
$av = AlertCenterService::GetInstance()->AvoidedLosses(intval($lot['branch_id']));
$row = array_values(array_filter($av['rows'], fn($r) => $r['alert_id'] === $alertId))[0] ?? null;
ok('la alerta aparece en pérdidas evitadas', (bool) $row);
ok('vendido sin merma = 2 u. a costo del lote', $row && abs($row['sold_qty'] - 2) < 0.001 && abs($row['avoided'] - round(2 * $lot['price'], 2)) < 0.011, json_encode($row));
ok('merma = 1 u. a costo', $row && abs($row['lost_qty'] - 1) < 0.001 && abs($row['lost'] - round($lot['price'], 2)) < 0.011);
ok('queda el resto del lote en curso (aún no vence)', $row && abs($row['pending_qty'] - ($lot['amount'] - 3)) < 0.001 && $row['outcome'] === ($lot['amount'] - 3 > 0 ? 'in_progress' : 'partial'), $row['outcome'] ?? '');
ok('% evitado = evitada ÷ (evitada + merma + ajustes)', $av['totals']['avoided_pct'] !== null);
$other = AlertCenterService::GetInstance()->AvoidedLosses(null, [999]);
ok('sucursales no permitidas no ven la alerta', count($other['rows']) === 0);

/* ------------------------------------------------------------------ 3. Acciones de Arturito */
echo "== Acciones de Arturito\n";
ok('cada acción exige su propio permiso', AiActionService::Permission('draft_po') === User::PERMISSION_PROCUREMENT_MANAGE && AiActionService::Permission('action_plan') === User::PERMISSION_WASTE_APPROVE && AiActionService::Permission('fefo_task') === User::PERMISSION_ALERTS_MANAGE);
expectError('acción desconocida se rechaza', fn() => AiActionService::Permission('delete_all'));
$before = $changes();
$pv = AiActionService::GetInstance()->Preview('draft_po', 1);
$pv2 = AiActionService::GetInstance()->Preview('fefo_task', 1);
$pv3 = AiActionService::GetInstance()->Preview('action_plan', 1);
ok('la vista previa no escribe nada', $changes() === $before, $changes() - $before);
ok('la vista previa describe lo que se creará', !empty($pv['summary']) && is_array($pv['lines']) && isset($pv['can_execute']));
expectError('sin confirmación explícita no se ejecuta', fn() => AiActionService::GetInstance()->Execute('fefo_task', 1, false, 1));
$db->exec("UPDATE stock SET best_before_date = '" . date('Y-m-d', strtotime('+1 day')) . "' WHERE id = (SELECT s.id FROM stock s JOIN locations l ON l.id = s.location_id WHERE l.branch_id = 1 AND s.amount > 0 AND IFNULL(l.is_transit, 0) = 0 LIMIT 1)");
$tasks0 = $val("SELECT COUNT(*) FROM workforce_store_tasks WHERE source_type = 'ai'");
$r = AiActionService::GetInstance()->Execute('fefo_task', 1, true, 1, '¿qué productos están en riesgo?');
ok('confirmada: crea 1 tarea FEFO pendiente ligada a la bitácora', $val("SELECT COUNT(*) FROM workforce_store_tasks WHERE source_type = 'ai'") == $tasks0 + 1 && $val("SELECT status FROM workforce_store_tasks WHERE source_type = 'ai' AND source_id = " . $r['log_id']) === 'pending', json_encode($r));
ok('bitácora con usuario, pregunta y resultado', $val('SELECT status FROM dairy_ai_actions WHERE id = ' . $r['log_id']) === 'executed' && $val('SELECT user_id FROM dairy_ai_actions WHERE id = ' . $r['log_id']) == 1 && $val('SELECT question FROM dairy_ai_actions WHERE id = ' . $r['log_id']) !== null);
if ($pv3['can_execute'])
{
	$r3 = AiActionService::GetInstance()->Execute('action_plan', 1, true, 1);
	ok('plan de acción: ahorro estimado 0 (no lo inventa la IA)', floatval($val('SELECT cost_avoided_estimate FROM waste_action_plans WHERE id = ' . $r3['id'])) == 0.0);
}
if ($pv['can_execute'])
{
	$o0 = $val("SELECT COUNT(*) FROM procurement_orders WHERE status = 'draft'");
	AiActionService::GetInstance()->Execute('draft_po', 1, true, 1);
	ok('órdenes de compra quedan en borrador (requieren aprobación de compras)', $val("SELECT COUNT(*) FROM procurement_orders WHERE status = 'draft'") > $o0 && $val("SELECT COUNT(*) FROM procurement_orders WHERE status = 'approved' AND approved_at >= datetime('now', 'localtime', '-1 minute')") == 0);
}
$ans = \Grocy\Services\AdvisorQaService::GetInstance()->Ask('¿Qué productos debo reponer?', 1);
$acts = [];
foreach ($ans['sections'] as $s)
{
	foreach ($s['items'] ?? [] as $i)
	{
		if (is_array($i) && !empty($i['action']))
		{
			$acts[] = $i['action'];
		}
	}
}
ok('Arturito propone acciones solo del catálogo permitido', !array_diff($acts, array_keys(AiActionService::TYPES)), json_encode($acts));

/* ------------------------------------------------------------------ 4. Reportes personalizables y programados */
echo "== Reportes personalizables\n";
$rb = ReportBuilderService::GetInstance();
expectError('columna fuera del catálogo se rechaza (sin SQL libre)', fn() => ReportBuilderService::Normalize('sales', ['columns' => ['1; DROP TABLE sales']]));
$n = ReportBuilderService::Normalize('sales', ['columns' => ['total', 'x'], 'sort' => 'total DESC; --', 'dir' => 'evil']);
ok('orden y sentido inválidos se normalizan', $n['sort'] === 'total' && $n['dir'] === 'DESC' && $n['columns'] === ['total']);
[$f, $t] = ReportBuilderService::Period('30d');
$res = $rb->Run('sales', ['columns' => ['number', 'total'], 'period' => '30d', 'limit' => 5000], 1);
$sql = floatval($val("SELECT IFNULL(SUM(total_amount), 0) FROM sales WHERE status != 'cancelled' AND date(created_timestamp) BETWEEN '$f' AND '$t'"));
ok('total del reporte = suma directa en BD (30 días)', abs(array_sum(array_column($res['rows'], 1)) - $sql) < 0.01, $sql);
$byProd = $rb->Run('sales_products', ['columns' => ['product', 'sales'], 'period' => '30d', 'limit' => 5000], 1);
ok('ventas por producto suman lo mismo que las líneas', abs(array_sum(array_column($byProd['rows'], 1)) - floatval($val("SELECT IFNULL(SUM(i.subtotal), 0) FROM sale_items i JOIN sales s ON s.id = i.sale_id WHERE s.status != 'cancelled' AND date(s.created_timestamp) BETWEEN '$f' AND '$t'"))) < 0.01);
// Usuario 2 solo con ventas de la sucursal 2
$db->exec('DELETE FROM user_permissions WHERE user_id = 2');
$db->exec("INSERT INTO user_permissions (user_id, permission_id) SELECT 2, id FROM permission_hierarchy WHERE name = 'SALES_VIEW'");
$db->exec('DELETE FROM user_branches WHERE user_id = 2');
$db->exec('INSERT INTO user_branches (user_id, branch_id) VALUES (2, 2)');
$r2 = $rb->Run('sales', ['columns' => ['branch', 'total'], 'period' => 'ytd', 'limit' => 5000], 2);
$b2name = $val('SELECT name FROM branches WHERE id = 2');
ok('usuario con 1 sucursal solo ve esa sucursal', count($r2['rows']) === 0 || !array_diff(array_unique(array_column($r2['rows'], 0)), [$b2name]), json_encode(array_unique(array_column($r2['rows'], 0))));
expectError('pedir otra sucursal → 403', fn() => $rb->Run('sales', ['columns' => ['total'], 'branch_id' => 1], 2));
expectError('conjunto sin permiso (mermas) → 403', fn() => $rb->Run('waste', ['columns' => ['cost']], 2));
$id = $rb->Save(['name' => 'Ventas diarias prueba', 'dataset' => 'sales', 'config' => ['columns' => ['date', 'total'], 'period' => '7d'], 'schedule' => 'daily', 'schedule_hour' => 6], 1);
$def = $rb->Get($id, 1);
ok('programación diaria a las 06:00 calcula la próxima ejecución', substr($def['next_run_at'], 11) === '06:00:00' && $def['next_run_at'] > date('Y-m-d H:i:s'), $def['next_run_at']);
expectError('otro usuario no ve la definición', fn() => $rb->Get($id, 2));
ok('RunDue antes de la hora no ejecuta', count($rb->RunDue(date('Y-m-d H:i:s'))) === 0 || !in_array($id, array_column($rb->RunDue(date('Y-m-d H:i:s')), 'id')));
$due = $rb->RunDue($def['next_run_at']);
$mine = array_values(array_filter($due, fn($d) => $d['id'] === $id))[0] ?? null;
ok('RunDue a la hora ejecuta y guarda el CSV', $mine && $mine['status'] === 'ok', json_encode($mine));
$run = $rb->GetRun($mine['run_id'], 1);
ok('CSV con BOM UTF-8, separador ; y encabezados', str_starts_with($run['csv'], "\xEF\xBB\xBF") && str_contains($run['csv'], 'Fecha;Total'));
$def2 = $rb->Get($id, 1);
ok('la próxima ejecución avanza un día', $def2['next_run_at'] === date('Y-m-d H:i:s', strtotime($def['next_run_at'] . ' +1 day')), $def2['next_run_at']);
// Dueño pierde el permiso → la programada falla con registro (no corre con más permisos de los que tiene)
$id2 = $rb->Save(['name' => 'Ventas usuario 2', 'dataset' => 'sales', 'config' => ['columns' => ['total']], 'schedule' => 'daily'], 2);
$db->exec('DELETE FROM user_permissions WHERE user_id = 2');
$e2 = $rb->Execute($id2, 2, 'schedule');
ok('si el dueño pierde el permiso la ejecución falla y queda registrada', $e2['status'] === 'failed' && $val("SELECT status FROM dairy_report_runs WHERE id = {$e2['run_id']}") === 'failed', $e2['error'] ?? '');
$rb->Delete($id, 1);
expectError('eliminado: ya no se encuentra', fn() => $rb->Get($id, 1));

/* ------------------------------------------------------------------ 5. Simulador (solo lectura, sin datos inventados) */
echo "== Simulador\n";
$pid = intval($lot['product_id']);
$db->exec("UPDATE products SET sale_price = NULL WHERE id = $pid");
expectError('sin precio de venta no simula (antes usaba 3,99)', fn() => SimulatorService::GetInstance()->SimulateWasteVsMarkdown($pid, 10, 0.3));
$db->exec("UPDATE products SET sale_price = 10 WHERE id = $pid");
$before = $changes();
$s = SimulatorService::GetInstance()->SimulateWasteVsMarkdown($pid, 10, 0.3, 0.8, 0);
ok('la simulación no modifica datos', $changes() === $before && $s['writes'] === false);
$cu = $s['product']['cost_unit'];
ok('costo real del lote (no 65 % del precio)', abs($cu - 6.5) > 0.0001 || floatval($val("SELECT SUM(amount*price)/SUM(amount) FROM stock WHERE product_id = $pid AND price > 0")) == 6.5, $cu);
ok('A con 0 días: no vende, pierde todo a costo', $s['scenario_a_waste']['sold_units'] == 0 && abs($s['scenario_a_waste']['total_cogs_lost'] - round(10 * $cu, 2)) < 0.011);
ok('B: vende 80 % a $7 y pierde 20 % a costo', $s['scenario_b_markdown']['sold_units'] == 8 && $s['scenario_b_markdown']['revenue_recovered'] == 56.0 && abs($s['scenario_b_markdown']['total_cogs_lost'] - round(2 * $cu, 2)) < 0.011);
ok('merma evitada = A − B', abs($s['financial_roi']['loss_avoided_usd'] - round(round(10 * $cu, 2) - round(2 * $cu, 2), 2)) < 0.011);
ok('devuelve hechos y supuestos por separado', count($s['facts']) >= 4 && count($s['assumptions']) >= 1);
// Producto con venta real: un lote que al ritmo histórico se vende completo antes de vencer
$sold = $db->query("SELECT i.product_id, SUM(i.amount) / 28.0 AS vmd FROM sale_items i JOIN sales s ON s.id = i.sale_id WHERE s.status = 'completed' AND date(s.created_timestamp) >= date('now', 'localtime', '-28 days') GROUP BY i.product_id HAVING vmd >= 0.1 ORDER BY vmd DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
if ($sold)
{
	$db->exec('UPDATE products SET sale_price = IFNULL(NULLIF(sale_price, 0), 10) WHERE id = ' . intval($sold['product_id']));
	try
	{
		$lotQ = max(1, floor($sold['vmd'] * 5));
		$s2 = SimulatorService::GetInstance()->SimulateWasteVsMarkdown(intval($sold['product_id']), $lotQ, 0.3, 0.8, 10);
		ok('si al ritmo actual se vende todo, recomienda no rebajar', $s2['scenario_a_waste']['sold_units'] == $lotQ && str_contains($s2['financial_roi']['recommendation'], 'No rebajar'), $s2['financial_roi']['recommendation']);
	}
	catch (\Throwable $e)
	{
		ok('producto con venta tiene costo para simular', str_contains($e->getMessage(), 'costo'), $e->getMessage());
	}
}
expectError('% vendido fuera de rango', fn() => SimulatorService::GetInstance()->SimulateWasteVsMarkdown($pid, 10, 0.3, 1.5));

/* ------------------------------------------------------------------ 6. Calidad */
echo "== Calidad (QualityExpiryService)\n";
$q = QualityExpiryService::GetInstance();
$fridge = intval($val('SELECT id FROM locations WHERE IFNULL(is_freezer, 0) = 0 LIMIT 1'));
$t1 = $q->RecordTemperature(['location_id' => $fridge, 'branch_id' => 1, 'temperature' => 3.5]);
$t2 = $q->RecordTemperature(['location_id' => $fridge, 'branch_id' => 1, 'temperature' => 5.0]);
$t3 = $q->RecordTemperature(['location_id' => $fridge, 'branch_id' => 1, 'temperature' => 7.0]);
ok('refrigerado: 3,5 normal · 5,0 advertencia · 7,0 crítico', $val("SELECT status FROM cold_chain_temperatures WHERE id = $t1") === 'normal' && $val("SELECT status FROM cold_chain_temperatures WHERE id = $t2") === 'warning' && $val("SELECT status FROM cold_chain_temperatures WHERE id = $t3") === 'critical');
ok('sin humedad medida no se inventa (antes 80 %)', $val("SELECT humidity FROM cold_chain_temperatures WHERE id = $t1") === null);
ok('responsable = usuario real (antes «Supervisor de Calidad»)', $val("SELECT recorded_by FROM cold_chain_temperatures WHERE id = $t1") !== 'Supervisor de Calidad');
expectError('temperatura no numérica se rechaza', fn() => $q->RecordTemperature(['location_id' => $fridge, 'temperature' => 'abc']));
$ql = $db->query("SELECT s.stock_id, s.product_id, s.amount, s.location_id, l.branch_id FROM stock s JOIN locations l ON l.id = s.location_id WHERE s.amount >= 2 AND s.stock_id != '{$lot['stock_id']}' ORDER BY s.id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
expectError('aislar más que el lote se rechaza', fn() => $q->MoveToQuarantine(['product_id' => $ql['product_id'], 'stock_id' => $ql['stock_id'], 'amount' => floatval($val("SELECT SUM(amount) FROM stock WHERE stock_id = '{$ql['stock_id']}'")) + 1]));
$qid = $q->MoveToQuarantine(['product_id' => $ql['product_id'], 'stock_id' => $ql['stock_id'], 'location_id' => $ql['location_id'], 'branch_id' => $ql['branch_id'], 'amount' => 1]);
$stock0 = floatval($val("SELECT SUM(amount) FROM stock WHERE stock_id = '{$ql['stock_id']}'"));
expectError('resolución no válida se rechaza', fn() => $q->ResolveQuarantine($qid, 'vender'));
$q->ResolveQuarantine($qid, 'discarded_as_waste', 'prueba', 1);
ok('baja desde cuarentena: −1 en el lote y merma aprobada con costo', abs(floatval($val("SELECT IFNULL(SUM(amount), 0) FROM stock WHERE stock_id = '{$ql['stock_id']}'")) - ($stock0 - 1)) < 0.001 && $val("SELECT status FROM waste_records WHERE id = (SELECT waste_record_id FROM quarantine_items WHERE id = $qid)") === 'approved');
expectError('resolver dos veces no da de baja el lote otra vez', fn() => $q->ResolveQuarantine($qid, 'discarded_as_waste', 'otra vez', 1));
ok('stock intacto tras el segundo intento', abs(floatval($val("SELECT IFNULL(SUM(amount), 0) FROM stock WHERE stock_id = '{$ql['stock_id']}'")) - ($stock0 - 1)) < 0.001);
ok('resuelto por el usuario real', $val("SELECT resolved_by FROM quarantine_items WHERE id = $qid") !== 'Supervisor de Calidad');

/* ------------------------------------------------------------------ 7. Proveedores */
echo "== Proveedores (SupplierProcurementService)\n";
$sp = SupplierProcurementService::GetInstance();
$sup = intval($val('SELECT id FROM shopping_locations ORDER BY id LIMIT 1'));
$d1 = $sp->RecordDelivery(['supplier_id' => $sup, 'expected_delivery_date' => '2026-10-10', 'actual_delivery_date' => '2026-10-12', 'items_ordered' => 10, 'items_received' => 10, 'status' => 'on_time']);
$d2 = $sp->RecordDelivery(['supplier_id' => $sup, 'expected_delivery_date' => '2026-10-10', 'actual_delivery_date' => '2026-10-10', 'items_ordered' => 10, 'items_received' => 8]);
$d3 = $sp->RecordDelivery(['supplier_id' => $sup, 'expected_delivery_date' => '2026-10-10', 'actual_delivery_date' => '2026-10-09', 'items_ordered' => 10, 'items_received' => 10]);
ok('entrega tardía no puede declararse «a tiempo»', $val("SELECT status FROM supplier_deliveries WHERE id = $d1") === 'delayed');
ok('incompleta = parcial · a tiempo y completa = on_time (OTIF)', $val("SELECT status FROM supplier_deliveries WHERE id = $d2") === 'partial' && $val("SELECT status FROM supplier_deliveries WHERE id = $d3") === 'on_time');
expectError('sin fecha comprometida se rechaza', fn() => $sp->RecordDelivery(['supplier_id' => $sup, 'items_ordered' => 1]));
expectError('precio 0 se rechaza', fn() => $sp->AddPriceRecord(['supplier_id' => $sup, 'product_id' => $pid, 'price' => 0]));
$db->exec("UPDATE shopping_locations SET active = 1 WHERE id = $sup");
$sp->AddPriceRecord(['supplier_id' => $sup, 'product_id' => $pid, 'price' => 5, 'delivery_lead_days' => 2]);
$cmp = $sp->GetCostComparison($pid);
$mine = array_values(array_filter($cmp, fn($o) => $o['supplier_id'] == $sup))[0] ?? null;
ok('comparador: puntaje 0–100 y sin calificación/condiciones inventadas', $mine && $mine['score'] >= 0 && $mine['score'] <= 100 && ($mine['supplier_rating'] === null || is_numeric($mine['supplier_rating'])), json_encode($mine));
ok('OTIF del comparador = entregas on_time ÷ total', $mine && abs(floatval($mine['otif_percentage']) - round(floatval($val("SELECT SUM(status = 'on_time') * 100.0 / COUNT(*) FROM supplier_deliveries WHERE supplier_id = $sup")), 1)) < 0.11);

/* ------------------------------------------------------------------ 8. Centros de costo */
echo "== Centros de costo (CostCenterService)\n";
$month = date('Y-m');
$cc = CostCenterService::GetInstance()->GetReport($month);
$sales = floatval($val("SELECT IFNULL(SUM(i.subtotal), 0) FROM sale_items i JOIN sales s ON s.id = i.sale_id WHERE s.status = 'completed' AND strftime('%Y-%m', s.created_timestamp) = '$month'"));
$waste = floatval($val("SELECT IFNULL(SUM(total_cost), 0) FROM waste_records WHERE status = 'approved' AND strftime('%Y-%m', created_timestamp) = '$month'"));
ok('venta del mes = suma de líneas de venta', abs($cc['total']['sales'] - round($sales, 2)) < 0.01, $cc['total']['sales'] . ' vs ' . $sales);
ok('merma del mes = merma aprobada', abs($cc['total']['waste'] - round($waste, 2)) < 0.01);
ok('merma % = merma ÷ venta × 100', $sales <= 0 || abs($cc['total']['waste_pct'] - round($waste / $sales * 100, 2)) < 0.01);
ok('Σ sucursales ≤ total (ventas sin sucursal quedan solo en el total)', array_sum(array_column($cc['branches'], 'sales')) <= $cc['total']['sales'] + 0.01);
ok('margen neto = margen − merma', abs($cc['total']['net_margin'] - round($cc['total']['margin'] - $cc['total']['waste'], 2)) < 0.011);
$catSum = array_sum(array_column($cc['categories'], 'sales'));
ok('Σ categorías = total (sin doble conteo)', abs($catSum - $cc['total']['sales']) < 0.05, $catSum);
ok('mes inválido cae al mes en curso', CostCenterService::GetInstance()->GetReport('xx')['month'] === date('Y-m'));

/* ------------------------------------------------------------------ 8b. Margen neto de IVA (una sola definición) */
echo "== Margen con impuesto\n";
$sl = $db->query("SELECT s.product_id, l.branch_id FROM stock s JOIN locations l ON l.id = s.location_id WHERE s.amount >= 3 AND l.branch_id = 1 AND IFNULL(l.is_transit, 0) = 0 AND NOT EXISTS (SELECT 1 FROM quarantine_items q WHERE q.stock_id = s.stock_id AND q.status = 'in_quarantine') ORDER BY s.id LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$sale = \Grocy\Services\SalesService::GetInstance()->CreateSale(['branch_id' => 1, 'payment_method' => 'cash', 'items' => [['product_id' => intval($sl['product_id']), 'amount' => 1, 'unit_price' => 11.9]],
	'_external' => ['sale_number' => 'T34-IVA-' . uniqid(), 'timestamp' => date('Y-m-d H:i:s'), 'tax_amount' => 1.9, 'discount_amount' => 0]], 1);
$row = $db->query('SELECT total_amount, tax_amount, total_cogs, gross_margin FROM sales WHERE id = ' . intval($sale->id))->fetch(PDO::FETCH_ASSOC);
ok('margen bruto = venta − IVA − costo (antes incluía el IVA)', abs($row['gross_margin'] - round($row['total_amount'] - $row['tax_amount'] - $row['total_cogs'], 2)) < 0.011, json_encode($row));
$rm = new ReflectionMethod(\Grocy\Services\MobileManagerService::class, 'SalesTotals');
$rm->setAccessible(true);
$mm = $rm->invoke(\Grocy\Services\MobileManagerService::GetInstance(), 1, date('Y-m-d'), date('Y-m-d'));
$sqlMargin = floatval($val("SELECT SUM(gross_margin) FROM sales WHERE branch_id = 1 AND status = 'completed' AND date(created_timestamp) = date('now', 'localtime')"));
ok('margen de /m (venta neta − costo) = Σ gross_margin de las ventas', $mm['margin'] !== null && abs($mm['margin'] - round($sqlMargin, 2)) < 0.02, $mm['margin'] . ' vs ' . $sqlMargin);

/* ------------------------------------------------------------------ 9. Arturito (AdvisorQaService) */
echo "== Arturito (AdvisorQaService)\n";
$cases = ['¿Por qué aumentaron las mermas?' => 'why', '¿Hay anomalías?' => 'anomalies', '¿Qué productos están en riesgo de vencer?' => 'critical', '¿Qué debo reponer?' => 'replenish', '¿Cómo reduzco el desperdicio?' => 'actions', 'Compara las sucursales' => 'branches', '' => 'help'];
$allOk = true;
foreach ($cases as $qq => $intent)
{
	$a = \Grocy\Services\AdvisorQaService::GetInstance()->Ask($qq, 1);
	$typesOk = !array_diff(array_column($a['sections'] ?? [], 'type'), ['observed', 'estimate', 'recommendation', 'help']);
	if ($a['intent'] !== $intent || !$typesOk || ($intent !== 'help' && empty($a['sources'])))
	{
		$allOk = false;
		echo "    · $qq → {$a['intent']} " . json_encode(array_column($a['sections'] ?? [], 'type')) . "\n";
	}
}
ok('cada pregunta va a su intención, separa hechos/estimaciones/recomendaciones y cita fuentes', $allOk);
$before = $changes();
\Grocy\Services\AdvisorQaService::GetInstance()->Ask('¿Qué debo reponer?', 1);
ok('preguntar no escribe datos', $changes() === $before);

echo "\n$count verificaciones, $fails fallos\n";
exit($fails > 0 ? 1 : 0);
