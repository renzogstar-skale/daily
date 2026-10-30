<?php
/**
 * Empresa de ejemplo coherente: catálogo único, 10 sucursales con operación, y las mismas cifras en HOY,
 * Panel ejecutivo, Inventario y Mermas con los mismos filtros. También una venta de prueba sin doble descuento.
 *   php tests/dairy/test_company_coherence.php [ruta/a/grocy.db]
 */

use Grocy\Services\DailyMetricsService;
use Grocy\Services\KpiService;
use Grocy\Services\SalesService;
use Grocy\Services\StockExecutiveOverviewService;
use Grocy\Services\TodayExecutiveSalesService;
use Grocy\Services\WasteExecutiveControlCenterService;

require __DIR__ . '/bootstrap.php';

$near = fn($a, $b, $tol = 0.6) => abs(floatval($a) - floatval($b)) <= $tol;
$today = date('Y-m-d');
$monthStart = date('Y-m-01');

echo "\n1. Catálogo y relaciones\n";
ok('SKU único en productos activos', intval($val("SELECT COUNT(*) FROM (SELECT sku FROM products WHERE active = 1 GROUP BY sku HAVING COUNT(*) > 1 OR sku IS NULL)")) === 0);
ok('toda línea de venta apunta a un producto existente', intval($val('SELECT COUNT(*) FROM sale_items si LEFT JOIN products p ON p.id = si.product_id WHERE p.id IS NULL')) === 0);
ok('toda unidad de producto existe', intval($val('SELECT COUNT(*) FROM products p LEFT JOIN quantity_units q ON q.id = p.qu_id_stock WHERE p.active = 1 AND q.id IS NULL')) === 0);
ok('pedidos con cliente y sucursal válidos', intval($val('SELECT COUNT(*) FROM customer_orders o LEFT JOIN customers c ON c.id = o.customer_id LEFT JOIN branches b ON b.id = o.branch_id WHERE c.id IS NULL OR b.id IS NULL')) === 0);
ok('libro de movimientos = kardex (sin faltantes ni duplicados)', intval($val('SELECT COUNT(*) FROM stock_log')) === intval($val('SELECT COUNT(*) FROM dairy_movement_ledger')));
ok('sin existencias negativas', intval($val('SELECT COUNT(*) FROM stock WHERE amount < -0.0001')) === 0);
foreach ($db->query('SELECT id, name FROM branches ORDER BY id')->fetchAll(PDO::FETCH_ASSOC) as $b)
{
	$n = intval($val("SELECT COUNT(*) FROM sales WHERE status = 'completed' AND branch_id = {$b['id']} AND date(created_timestamp) >= date('now', 'localtime', '-27 days')"));
	$e = intval($val("SELECT COUNT(*) FROM employees WHERE active = 1 AND branch_id = {$b['id']}"));
	$l = intval($val("SELECT COUNT(*) FROM locations WHERE branch_id = {$b['id']}"));
	ok("sucursal {$b['id']} {$b['name']}: ventas, personal y ubicaciones", $n > 0 && $e > 0 && $l > 0, "$n ventas · $e empleados · $l ubicaciones");
}

echo "\n2. Misma métrica en distintas pantallas\n";
$m = DailyMetricsService::GetInstance();
foreach ([null, 4] as $bid)
{
	$tag = $bid ? "sucursal $bid" : 'red';
	$hoy = TodayExecutiveSalesService::GetInstance()->GetExecutiveData($bid, $today);
	$kpi = KpiService::GetInstance()->GetExecutiveDashboardData($bid, 'custom', $today, $today);
	$s = $m->Sales($bid, $today, $today);
	ok("$tag · ventas netas HOY = métrica común", $near($hoy['kpis']['sales_net']['numeric'] ?? 0, $s['net_sales']), ($hoy['kpis']['sales_net']['numeric'] ?? 'null') . ' / ' . $s['net_sales']);
	ok("$tag · ventas HOY = Panel ejecutivo (día)", $near($s['gross_sales'], $kpi['total_sales']), $s['gross_sales'] . ' / ' . $kpi['total_sales']);
	ok("$tag · margen HOY = Panel ejecutivo", $near($s['margin_pct'] ?? 0, $kpi['margin_percentage'], 0.06), ($s['margin_pct'] ?? 'null') . ' / ' . $kpi['margin_percentage']);
	$stock = StockExecutiveOverviewService::GetInstance()->GetExecutiveData(['branch_id' => $bid ?? 'all']);
	$kpiM = KpiService::GetInstance()->GetExecutiveDashboardData($bid, 'custom', $monthStart, $today);
	ok("$tag · valor de inventario Inventario = Panel ejecutivo", $near($stock['kpis']['inventory_value']['numeric'], $kpiM['total_inventory_valuation'], 1.0), $stock['kpis']['inventory_value']['numeric'] . ' / ' . $kpiM['total_inventory_valuation']);
	$waste = WasteExecutiveControlCenterService::GetInstance()->GetExecutiveData($bid, $today);
	ok("$tag · merma del mes Mermas = Panel ejecutivo", $near(str_replace([',', '$'], '', $waste['kpis']['total_waste']['value']), $kpiM['total_waste_cost'], 1.0), $waste['kpis']['total_waste']['value'] . ' / ' . $kpiM['total_waste_cost']);
}
$stockNet = StockExecutiveOverviewService::GetInstance()->GetExecutiveData([]);
ok('Inventario: Σ sucursales = red', $near(array_sum(array_column($stockNet['branches_table'], 'inventory_value')), $stockNet['kpis']['inventory_value']['numeric'], 1.0));
ok('Inventario: Σ departamentos = total', !empty($stockNet['meta']['reconciliation']['is_reconciled']));
$sumB = 0.0;
foreach ($db->query('SELECT id FROM branches')->fetchAll(PDO::FETCH_COLUMN) as $b)
{
	$sumB += $m->Sales(intval($b), $monthStart, $today)['net_sales'];
}
ok('Ventas del mes: Σ sucursales = red', $near($sumB, $m->Sales(null, $monthStart, $today)['net_sales'], 1.0));
$hoy0 = TodayExecutiveSalesService::GetInstance()->GetExecutiveData(10, '2020-01-01');
ok('fecha sin registros → «Sin información», no cero', ($hoy0['kpis']['sales_net']['value'] ?? '') === 'Sin información');

echo "\n3. Venta de prueba: inventario, kardex y KPI sin doble descuento\n";
$pid = intval($val("SELECT s.product_id FROM stock s JOIN locations l ON l.id = s.location_id WHERE l.branch_id = 5 AND s.amount >= 2 AND s.best_before_date >= date('now') ORDER BY s.amount DESC LIMIT 1"));
$stockBefore = floatval($val("SELECT SUM(s.amount) FROM stock s JOIN locations l ON l.id = s.location_id WHERE l.branch_id = 5 AND s.product_id = $pid"));
$logBefore = intval($val('SELECT COUNT(*) FROM stock_log'));
$salesBefore = $m->Sales(5, $today, $today)['net_sales'];
$price = floatval($val("SELECT sale_price FROM products WHERE id = $pid"));
SalesService::GetInstance()->CreateSale(['branch_id' => 5, 'customer_id' => 1, 'payment_method' => 'card', 'items' => [['product_id' => $pid, 'amount' => 2, 'unit_price' => $price]]], 1);
ok('stock de la sucursal −2', $near(floatval($val("SELECT SUM(s.amount) FROM stock s JOIN locations l ON l.id = s.location_id WHERE l.branch_id = 5 AND s.product_id = $pid")), $stockBefore - 2, 0.001));
ok('kardex y libro registran el consumo', intval($val('SELECT COUNT(*) FROM stock_log')) > $logBefore && intval($val('SELECT COUNT(*) FROM stock_log')) === intval($val('SELECT COUNT(*) FROM dairy_movement_ledger')));
ok('ventas del día suben exactamente el importe', $near($m->Sales(5, $today, $today)['net_sales'], $salesBefore + 2 * $price, 0.01));
$hoy5 = TodayExecutiveSalesService::GetInstance()->GetExecutiveData(5, $today);
ok('HOY refleja la venta', $near($hoy5['kpis']['sales_net']['numeric'], $salesBefore + 2 * $price, 0.6));

echo "\n4. Recorridos por proceso\n";
$branchStock = fn(int $b, int $p) => floatval($val("SELECT IFNULL(SUM(s.amount), 0) FROM stock s JOIN locations l ON l.id = s.location_id WHERE l.branch_id = $b AND s.product_id = $p AND IFNULL(l.is_transit, 0) = 0"));
$logCount = fn() => intval($val('SELECT COUNT(*) FROM stock_log'));
$depts = intval($val("SELECT COUNT(DISTINCT p.department) FROM sale_items si JOIN sales s ON s.id = si.sale_id JOIN products p ON p.id = si.product_id WHERE s.status = 'completed' AND date(s.created_timestamp) >= date('now', 'localtime', '-27 days')"));
ok('ventas en al menos 8 departamentos', $depts >= 8, "$depts departamentos");
ok('mismo SKU y nombre en POS, pedidos e inventario (Arroz blanco 2 lb)', $val("SELECT sku || '|' || name FROM products WHERE id = 179") === 'ABA-ARR-001|Arroz blanco 2 lb'
	&& intval($val("SELECT COUNT(*) FROM sale_items WHERE product_id = 179")) > 0 && intval($val("SELECT COUNT(*) FROM stock WHERE product_id = 179")) > 0);
ok('ventas cargadas sin descargo quedan anuladas (no suman ni cuentan como devolución)', intval($val("SELECT COUNT(*) FROM sales s WHERE s.status = 'completed' AND date(s.created_timestamp) >= date('now', 'localtime', '-57 days')
	AND NOT EXISTS (SELECT 1 FROM sale_items si JOIN stock_log l ON l.transaction_id = si.transaction_id WHERE si.sale_id = s.id)")) === 0);

// Recepción: orden → aprobación → envío → recepción con lote; stock y kardex suben una sola vez
$mrp = \Grocy\Services\MrpService::GetInstance();
$before = $branchStock(6, 180);
$logBefore = $logCount();
$po = $mrp->CreateProcurementOrder(['branch_id' => 6, 'supplier_id' => intval($val('SELECT shopping_location_id FROM products WHERE id = 180')), 'items' => [['product_id' => 180, 'amount' => 10, 'unit_price' => 0.68]]], 1);
$mrp->ApproveProcurementOrder($po['id'], 1);
$mrp->SendProcurementOrder($po['id'], 1, date('Y-m-d'));
$it = $mrp->GetProcurementOrderDetail($po['id'])['items'][0];
$loc = intval($val("SELECT id FROM locations WHERE branch_id = 6 AND storage_class = 'ambient' LIMIT 1"));
$mrp->ReceiveProcurementOrder($po['id'], ['items' => [['order_item_id' => $it['id'], 'delivered_amount' => 10, 'rejected_amount' => 0, 'best_before_date' => date('Y-m-d', strtotime('+500 days')), 'location_id' => $loc, 'lot_number' => 'LT-PRUEBA-180']], 'temperature_on_arrival' => 21], 1);
ok('recepción: stock de la sucursal +10', $near($branchStock(6, 180), $before + 10, 0.001));
ok('recepción: movimiento de compra en kardex y libro', $logCount() > $logBefore && $logCount() === intval($val('SELECT COUNT(*) FROM dairy_movement_ledger')));
ok('recepción: lote ligado al producto y la sucursal', intval($val("SELECT COUNT(*) FROM dairy_stock_lots dl JOIN stock s ON s.stock_id = dl.stock_id JOIN locations l ON l.id = s.location_id WHERE dl.lot_number = 'LT-PRUEBA-180' AND s.product_id = 180 AND l.branch_id = 6")) > 0);
$again = true;
try
{
	$mrp->ReceiveProcurementOrder($po['id'], ['items' => [['order_item_id' => $it['id'], 'delivered_amount' => 10, 'rejected_amount' => 0, 'best_before_date' => date('Y-m-d', strtotime('+500 days')), 'location_id' => $loc]]], 1);
}
catch (\Throwable $e)
{
	$again = false;
}
ok('recepción repetida no duplica stock', !$again || $near($branchStock(6, 180), $before + 10, 0.001));

// Pedido: reserva al confirmar, preparación FEFO, empaque y despacho = una venta y un descuento
$co = \Grocy\Services\CustomerOrderService::GetInstance();
// Producto de pedidos comerciales con stock libre en la sucursal (la operación simulada del día puede agotar alguno)
$op = 40;
foreach ([40, 41, 42, 43, 44] as $cand) { if ($co->Free($cand, 4) >= 3) { $op = $cand; break; } }
$freeBefore = $co->Free($op, 4);
$stockBefore = $branchStock(4, $op);
$salesN = intval($val("SELECT COUNT(*) FROM sales WHERE status = 'completed'"));
$o = $co->Create(['branch_id' => 4, 'customer_id' => intval($val('SELECT id FROM customers WHERE branch_id = 4 LIMIT 1')), 'items' => [['product_id' => $op, 'amount' => 3]], 'delivery_type' => 'delivery', 'confirm' => true], 1);
$oid = intval($o['order']['id'] ?? $o['id']);
ok('pedido confirmado reserva la cantidad', $near($co->Free($op, 4), $freeBefore - 3, 0.001) && $near($branchStock(4, $op), $stockBefore, 0.001));
$co->QuickPickFefo($oid, 1);
$co->Pack($oid, 1, 1);
$co->Dispatch($oid, 1);
$co->Dispatch($oid, 1);
ok('despacho: stock −3 una sola vez (despacho repetido sin efecto)', $near($branchStock(4, $op), $stockBefore - 3, 0.001));
ok('despacho: una venta ligada al pedido', intval($val("SELECT COUNT(*) FROM sales WHERE status = 'completed'")) === $salesN + 1 && intval($val("SELECT IFNULL(sale_id, 0) FROM customer_orders WHERE id = $oid")) > 0);
ok('pedido despachado ya no reserva', $near($co->Free($op, 4), $freeBefore - 3, 0.001));

// Merma: descuenta inventario una vez y queda con causal, sucursal y estado
$wp = intval($val("SELECT s.product_id FROM stock s JOIN locations l ON l.id = s.location_id WHERE l.branch_id = 2 AND s.amount >= 3 AND s.product_id = 30 LIMIT 1")) ?: 30;
$wb = $branchStock(2, $wp);
$wn = intval($val('SELECT COUNT(*) FROM waste_records'));
\Grocy\Services\WasteService::GetInstance()->ReportWaste($wp, 1, 35, 2, null, 'default', 'Rotura en prueba', null, null, null, null, null, null, 'prueba-merma-1', 1, false, true);
\Grocy\Services\WasteService::GetInstance()->ReportWaste($wp, 1, 35, 2, null, 'default', 'Rotura en prueba', null, null, null, null, null, null, 'prueba-merma-1', 1, false, true);
$w = $db->query('SELECT * FROM waste_records ORDER BY id DESC LIMIT 1')->fetch(PDO::FETCH_ASSOC);
ok('merma: un registro con producto, causal, sucursal y estado (reintento idempotente)', intval($val('SELECT COUNT(*) FROM waste_records')) === $wn + 1 && intval($w['product_id']) === $wp && intval($w['waste_reason_id']) === 35 && intval($w['branch_id']) === 2 && $w['status'] !== '');
ok('merma: inventario según su estado', ($w['status'] === 'approved') ? $near($branchStock(2, $wp), $wb - 1, 0.001) : $near($branchStock(2, $wp), $wb, 0.001), $w['status']);

// Vencimientos: la alerta abre un lote real del producto y la sucursal
$exp = $db->query("SELECT a.product_id, a.branch_id FROM dairy_alerts a WHERE a.rule_code = 'expiring_lots' AND a.status NOT IN ('closed', 'dismissed') AND a.product_id IS NOT NULL LIMIT 1")->fetch(PDO::FETCH_ASSOC);
ok('alerta de lote por vencer con lote vigente en su sucursal', $exp && intval($val("SELECT COUNT(*) FROM stock s JOIN locations l ON l.id = s.location_id WHERE s.product_id = {$exp['product_id']} AND l.branch_id = {$exp['branch_id']} AND s.amount > 0 AND s.best_before_date <= date('now', '+10 days')")) > 0);

// Personal: turnos en la sucursal correcta y cobertura de hoy
ok('turnos: sucursal del turno = sucursal del empleado', intval($val('SELECT COUNT(*) FROM employee_shifts sh JOIN employees e ON e.id = sh.employee_id WHERE sh.branch_id <> e.branch_id')) === 0);
ok('Hollywood: turnos programados hoy', intval($val("SELECT COUNT(*) FROM employee_shifts WHERE branch_id = 7 AND shift_date = date('now', 'localtime') AND status <> 'cancelled'")) > 0);

// Traslados y conteos
ok('traslados recibidos y uno en tránsito', intval($val("SELECT COUNT(*) FROM stock_transfers WHERE status = 'received'")) > 0 && intval($val("SELECT COUNT(*) FROM stock_transfers WHERE status = 'in_transit'")) > 0);
ok('conteos cíclicos con diferencia pendiente de aprobación', intval($val("SELECT COUNT(*) FROM stock_cycle_counts WHERE status = 'discrepancy'")) > 0 && intval($val("SELECT COUNT(*) FROM stock_cycle_counts WHERE status = 'completed'")) > 0);

// Textos visibles
ok('sin «[DEMO]» ni «demo» en sucursales, empresa, productos y notas', intval($val("SELECT (SELECT COUNT(*) FROM branches WHERE name LIKE '%demo%') + (SELECT COUNT(*) FROM companies WHERE name LIKE '%demo%')
	+ (SELECT COUNT(*) FROM products WHERE name LIKE '%demo%' OR description LIKE '%demo%') + (SELECT COUNT(*) FROM sales WHERE notes LIKE '%demo%') + (SELECT COUNT(*) FROM pos_cash_shifts WHERE notes LIKE '%demo%')")) === 0);

echo "\n$count pruebas, $fails fallos\n";
exit($fails > 0 ? 1 : 0);
