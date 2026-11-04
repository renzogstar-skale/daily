<?php
/**
 * Etapa 30 — plataforma integrada: consistencia de cifras de ventas entre web (/sales), móvil (/m), sucursales y
 * Centro de Control; navegación contextual (módulos conectados) desde una sola fuente y filtrada por permiso.
 */

require __DIR__ . '/bootstrap.php';

use Grocy\Helpers\DairyContextNav;
use Grocy\Helpers\SystemMapCatalog;
use Grocy\Services\AkcalMetricsService;
use Grocy\Services\BranchService;
use Grocy\Services\LossPreventionService;
use Grocy\Services\MobileManagerService;
use Grocy\Services\SalesService;

$from = date('Y-m-d', strtotime('-89 days'));
$to = date('Y-m-d');

echo "1. Ventas: mismas cifras en web, móvil, sucursales y Centro de Control\n";
$a = MobileManagerService::GetInstance()->Sales(null, $from, $to);
$t = $a['totals'];
$rows = SalesService::GetInstance()->GetSales(null, $from, $to, 100000);
$done = array_filter($rows, fn($s) => $s->status === 'completed');
ok('tickets = ventas completadas del listado', (int) $t['tickets'] === count($done), $t['tickets'] . ' vs ' . count($done));
$totalWithTax = $t['total_with_tax'] ?? $t['total'];
ok('venta total = Σ total_amount del listado', abs($totalWithTax - array_sum(array_map(fn($s) => (float) $s->total_amount, $done))) < 0.01);
ok('anuladas = filas canceladas del listado', (int) $t['returns_count'] === count(array_filter($rows, fn($s) => $s->status === 'cancelled')));
ok('ticket promedio = total ÷ tickets', $t['tickets'] == 0 || abs($t['avg_ticket'] - round($t['total'] / $t['tickets'], 2)) < 0.01);

$byBranch = array_sum(array_map(fn($r) => (float) $r['net_sales'], BranchService::GetInstance()->GetBranchScorecards($from, $to)));
ok('Σ sucursales (/branches) = venta total', abs($byBranch - $totalWithTax) < 0.01, "$byBranch vs $totalWithTax");

$akcal = (new ReflectionMethod(AkcalMetricsService::class, 'GetSalesTotals'));
$k = $akcal->invoke(AkcalMetricsService::GetInstance(), $from, $to, null);
ok('Centro de Control (/waste) usa la misma venta neta', abs((float) $k['net_sales'] - $totalWithTax) < 0.01, $k['net_sales'] . ' vs ' . $totalWithTax);
ok('descuentos coinciden con Centro de Control', abs((float) $k['discounts'] - $t['discounts']) < 0.01);

$sumGroups = array_sum(array_map(fn($g) => (float) $g['total'], $a['by_group']));
$sumItems = (float) $db->query("SELECT IFNULL(SUM(" . SalesService::SQL_ITEM_NET_SALES . "), 0) FROM sale_items si JOIN sales s ON s.id = si.sale_id WHERE s.status = 'completed' AND date(s.created_timestamp) BETWEEN '$from' AND '$to'")->fetchColumn();
ok('Σ departamentos = Σ líneas vendidas', abs($sumGroups - $sumItems) < 0.01, "$sumGroups vs $sumItems");
ok('serie diaria cubre el período completo', count($a['daily']) === 90 && abs(array_sum(array_column($a['daily'], 'v')) - $sumItems) < 0.01);

$unlinked = LossPreventionService::GetInstance()->PosReconciliation(null, $from, $to);
$expect = (int) $db->query("SELECT COUNT(*) FROM sale_items si JOIN sales s ON s.id = si.sale_id WHERE s.status = 'completed' AND si.transaction_id IS NULL AND date(s.created_timestamp) BETWEEN '$from' AND '$to'")->fetchColumn();
ok('conciliación: líneas sin movimiento ⊆ líneas sin transacción', count($unlinked) <= $expect, count($unlinked) . " ≤ $expect");

echo "2. Venta nueva por el servicio: se refleja una sola vez en todas las vistas\n";
$stock = $db->query("SELECT st.product_id, l.branch_id FROM stock st JOIN locations l ON l.id = st.location_id WHERE IFNULL(l.is_transit, 0) = 0 AND st.amount >= 1 AND l.branch_id IS NOT NULL
	AND st.product_id NOT IN (SELECT product_id FROM quarantine_items WHERE status = 'in_quarantine') AND st.product_id NOT IN (SELECT id FROM products WHERE is_demo = 1) ORDER BY st.amount DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
if ($stock)
{
	$today = date('Y-m-d');
	$before = MobileManagerService::GetInstance()->Sales((int) $stock['branch_id'], $today, $today)['totals'];
	$beforeUnlinked = count(LossPreventionService::GetInstance()->PosReconciliation((int) $stock['branch_id'], $today, $today));
	$sale = SalesService::GetInstance()->CreateSale(['branch_id' => (int) $stock['branch_id'], 'payment_method' => 'cash', 'items' => [['product_id' => (int) $stock['product_id'], 'amount' => 1, 'unit_price' => 10]]]);
	$after = MobileManagerService::GetInstance()->Sales((int) $stock['branch_id'], $today, $today)['totals'];
	ok('venta creada', !empty($sale), json_encode($sale));
	ok('+1 ticket', (int) $after['tickets'] === (int) $before['tickets'] + 1);
	ok('+$10 de venta', abs($after['total'] - $before['total'] - 10) < 0.01, $after['total'] - $before['total']);
	ok('la venta del POS sí movió stock (no aparece en conciliación)', count(LossPreventionService::GetInstance()->PosReconciliation((int) $stock['branch_id'], $today, $today)) === $beforeUnlinked);
	$saleId = (int) $sale->id;
	$lines = (int) $db->query("SELECT COUNT(*) FROM stock_log WHERE transaction_id IN (SELECT transaction_id FROM sale_items WHERE sale_id = $saleId) AND undone = 0")->fetchColumn();
	ok('kardex: la venta generó movimiento de stock', $lines >= 1, $lines);
	SalesService::GetInstance()->CancelSale($saleId);
	$cancel = MobileManagerService::GetInstance()->Sales((int) $stock['branch_id'], $today, $today)['totals'];
	ok('anular: sale de la venta y entra en devoluciones', abs($cancel['total'] - $before['total']) < 0.01 && (int) $cancel['returns_count'] === (int) $before['returns_count'] + 1);
}
else
{
	ok('hay stock para probar una venta', false);
}

echo "3. Módulos conectados: una sola fuente, rutas válidas, permisos\n";
$routes = (string) file_get_contents(__DIR__ . '/../../routes.php');
$nav = DairyContextNav::ForView('sales_history');
ok('Ventas tiene módulos conectados', $nav && count($nav['in']) && count($nav['out']), json_encode(array_map(fn($l) => $l['route'], array_merge($nav['in'] ?? [], $nav['out'] ?? []))));
$fromCatalog = [];
foreach (SystemMapCatalog::CONNECTIONS as $c)
{
	if ($c[0] === 'sales') $fromCatalog[] = $c[1];
}
$outIds = array_map(fn($l) => DairyContextNav::ModuleId($l['route']), $nav['out']);
ok('salidas = conexiones del catálogo (sin listas a mano)', !array_diff($outIds, $fromCatalog), json_encode($outIds));
$bad = [];
foreach (['today', 'alert_center', 'executive_dashboard', 'waste_overview', 'waste_report', 'waste_approvals', 'quality_expiry', 'recalls', 'stockoverview', 'scan', 'transfers', 'kardex', 'traceability',
	'inventory_control', 'inventory', 'branches', 'mrp', 'suppliers_procurement', 'workforce', 'schedule', 'store_audits', 'pos', 'orders', 'reports_hub', 'cost_centers', 'ai_advisor'] as $v)
{
	foreach (array_merge(DairyContextNav::ForView($v)['in'] ?? [], DairyContextNav::ForView($v)['out'] ?? []) as $l)
	{
		$path = explode('#', $l['route'])[0];
		if (!preg_match('#->get\(\'' . preg_quote($path, '#') . '\'#', $routes)) $bad[] = "$v→$path";
	}
}
ok('todos los enlaces apuntan a rutas GET existentes', !$bad, implode(', ', $bad));
ok('vista fuera del menú no muestra el bloque', DairyContextNav::ForView('productform') === null);


echo "4. Vencimiento próximo → alerta → tarea móvil → cierre (Ejemplo 3)\n";
$acs = \Grocy\Services\AlertCenterService::GetInstance();
$rule = $db->query("SELECT * FROM dairy_alert_rules WHERE code = 'expiring_lots'")->fetch(PDO::FETCH_ASSOC);
ok('regla «Lotes por vencer» instalada (migración 0285)', $rule && (int) $rule['enabled'] === 1 && (float) $rule['threshold'] === 3.0);
$lot = $db->query("SELECT s.id, s.stock_id, l.branch_id FROM stock s JOIN locations l ON l.id = s.location_id WHERE l.branch_id IS NOT NULL AND IFNULL(l.is_transit, 0) = 0 AND s.amount > 0
	AND NOT EXISTS (SELECT 1 FROM quarantine_items q WHERE q.stock_id = s.stock_id AND q.status = 'in_quarantine') AND s.product_id NOT IN (SELECT id FROM products WHERE is_demo = 1) ORDER BY s.id LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$setDays = fn(int $d) => $db->exec("UPDATE stock SET best_before_date = date('now', 'localtime', '+$d days') WHERE stock_id = " . $db->quote($lot['stock_id']));
$keyFor = fn(int $d) => "expiring_lots:{$lot['stock_id']}:" . date('Y-m-d', strtotime("+$d days"));
$setDays(2);
$acs->Scan((int) $lot['branch_id'], 1);
$alert = $db->query('SELECT * FROM dairy_alerts WHERE dedupe_key = ' . $db->quote($keyFor(2)))->fetch(PDO::FETCH_ASSOC);
ok('el lote que vence en 2 días abre una alerta', (bool) $alert, $keyFor(2));
ok('alerta con plazo, valor y enlace a Calidad', $alert && $alert['href'] === '/quality' && $alert['due_at'] !== null && str_contains($alert['detail'], 'Vence en 2 día'), $alert['detail'] ?? '');
$acs->Scan((int) $lot['branch_id'], 1);
ok('re-escanear no duplica', (int) $db->query('SELECT COUNT(*) FROM dairy_alerts WHERE dedupe_key = ' . $db->quote($keyFor(2)))->fetchColumn() === 1);
$task = \Grocy\Services\MobileWorkService::GetInstance()->TaskFromAlert((int) $alert['id'], 1, null, 1);
ok('la alerta se asigna como tarea en DAIRY Mobile', !empty($task), json_encode($task));
$setDays(30);
$acs->Scan((int) $lot['branch_id'], 1);
$st = $db->query('SELECT status FROM dairy_alerts WHERE id = ' . (int) $alert['id'])->fetchColumn();
ok('alerta tomada por alguien NO se cierra sola (exige resolución)', $st !== 'closed', $st);
expectError('completar la tarea sin decir qué se hizo → rechazado', fn() => \Grocy\Services\MobileWorkService::GetInstance()->CompleteTask((int) $task['id'], 'ok', null, 1));
\Grocy\Services\MobileWorkService::GetInstance()->CompleteTask((int) $task['id'], 'Rebaja FEFO 30 % aplicada y lote al frente', null, 1);
$closed = $db->query('SELECT status, resolution FROM dairy_alerts WHERE id = ' . (int) $alert['id'])->fetch(PDO::FETCH_ASSOC);
ok('completar la tarea móvil cierra la alerta con la acción realizada', $closed['status'] === 'closed' && str_contains($closed['resolution'], 'Rebaja FEFO'), json_encode($closed));
$setDays(1);
$acs->Scan((int) $lot['branch_id'], 1);
$setDays(30);
$acs->Scan((int) $lot['branch_id'], 1);
ok('alerta sin tocar se cierra sola al desaparecer la condición', $db->query('SELECT status FROM dairy_alerts WHERE dedupe_key = ' . $db->quote($keyFor(1)))->fetchColumn() === 'closed');

echo "5. Tarea asignada de noche: se liga al turno nocturno en curso aunque venza después de medianoche\n";
$db->exec("INSERT INTO employees (name, branch_id, active) VALUES ('Prueba Nocturno', 1, 1)");
$empId = (int) $db->lastInsertId();
$mws = \Grocy\Services\MobileWorkService::GetInstance();
$startTs = time() - 3600;
$db->exec("INSERT INTO employee_shifts (employee_id, branch_id, shift_date, start_time, end_time, status) VALUES ($empId, 1, '" . date('Y-m-d', $startTs) . "', '" . date('H:i', $startTs) . "', '" . date('H:i', $startTs + 9 * 3600) . "', 'published')");
$night = (int) $db->lastInsertId();
$tomorrow = date('Y-m-d', strtotime('+1 day'));
$db->exec("INSERT INTO employee_shifts (employee_id, branch_id, shift_date, start_time, end_time, status) VALUES ($empId, 1, '$tomorrow', '23:00', '23:30', 'published')");
$t1 = $mws->CreateTask(['employee_id' => $empId, 'title' => 'Revisar FEFO góndola', 'due' => date('Y-m-d H:i', time() + 6 * 3600)], 1);
ok('tarea con vencimiento +6 h → turno en curso', (int) $db->query('SELECT shift_id FROM workforce_store_tasks WHERE id = ' . (int) $t1['id'])->fetchColumn() === $night);
$db->exec("UPDATE employee_shifts SET status = 'draft' WHERE id = $night");
$t2 = $mws->CreateTask(['employee_id' => $empId, 'title' => 'Conteo de mañana', 'due' => "$tomorrow 23:10"], 1);
$s2 = (int) $db->query('SELECT shift_id FROM workforce_store_tasks WHERE id = ' . (int) $t2['id'])->fetchColumn();
ok('sin turno en curso → turno que contiene el vencimiento (borradores no cuentan)', $s2 === (int) $db->query("SELECT id FROM employee_shifts WHERE employee_id = $empId AND shift_date = '$tomorrow'")->fetchColumn(), $s2);

echo "\n$count verificaciones, $fails fallos\n";
exit($fails > 0 ? 1 : 0);
