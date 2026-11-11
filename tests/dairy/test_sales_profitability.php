<?php
/**
 * Rentabilidad retail (SalesReportsService::Profitability): utilidad y margen bruto sobre la base correcta, costos
 * faltantes fuera del margen, costo contra el lote, descuentos, precio/volumen/costo, margen negativo y contribución.
 * Uso: php tests/dairy/test_sales_profitability.php [base.db]   (trabaja sobre una copia de la base)
 */

use Grocy\Helpers\DailyFilters;
use Grocy\Services\SalesReportsService;

require __DIR__ . '/bootstrap.php';

$sr = SalesReportsService::GetInstance();
$db = Grocy\Services\DatabaseService::GetInstance()->GetDbConnectionRaw();
$near = fn($a, $b, $tol = 0.05) => abs(floatval($a) - floatval($b)) <= $tol;
$to = $db->query("SELECT date(MAX(created_timestamp)) FROM sales WHERE status = 'completed'")->fetchColumn();
$from = date('Y-m-d', strtotime("$to -29 days"));
$report = fn(array $q = []) => $sr->Report(DailyFilters::FromQuery($q + ['start_date' => $from, 'end_date' => $to]));

echo "\n1. Ventas netas, costo, utilidad y margen bruto\n";
$t0 = microtime(true);
$R = $report();
ok('Informe completo en menos de 4 s', microtime(true) - $t0 < 4, round(microtime(true) - $t0, 2) . ' s');
$P = $R['profitability'];
ok('Ventas netas = Tablero (sin duplicar)', $near($P['net'], $R['dashboard']['net'], 0.01), json_encode([$P['net'], $R['dashboard']['net']]));
ok('Ventas con costo + sin costo = ventas netas', $near($P['net_c'] + $P['net_missing'], $P['net'], 0.05));
ok('Utilidad bruta = ventas con costo − costo', $near($P['profit'], $P['net_c'] - $P['cogs'], 0.02));
ok('Margen = utilidad ÷ ventas con costo (no sobre costo)', $near($P['margin_pct'], $P['profit'] / $P['net_c'] * 100, 0.06) && !$near($P['margin_pct'], $P['profit'] / max(1, $P['cogs']) * 100, 1));
$sql = $db->query("SELECT SUM(si.total_cost) FROM sale_items si JOIN sales s ON s.id = si.sale_id WHERE s.status = 'completed' AND date(s.created_timestamp) BETWEEN '$from' AND '$to'")->fetchColumn();
ok('Costo = Σ costo de líneas (período sin archivo)', $P['net_archive'] > 0 || $near($P['cogs'], $sql, 0.5), json_encode([$P['cogs'], round($sql, 2)]));
foreach (['by_department' => 'departamento', 'by_branch' => 'sucursal', 'products' => 'SKU'] as $k => $n)
{
	ok("Σ ventas netas por $n = total", $near(array_sum(array_column($P[$k], 'net')), $P['net'], 1));
	ok("Σ utilidad por $n = total", $near(array_sum(array_map(fn($r) => $r['profit'] ?? 0, $P[$k])), $P['profit'], 1));
}

echo "\n2. Costos faltantes no cuentan como cero\n";
$missing = floatval($db->query("SELECT SUM(si.subtotal * (s.total_amount - s.tax_amount) / s.subtotal) FROM sale_items si JOIN sales s ON s.id = si.sale_id
	WHERE s.status = 'completed' AND date(s.created_timestamp) BETWEEN '$from' AND '$to' AND IFNULL(si.total_cost, 0) = 0 AND s.subtotal > 0")->fetchColumn());
ok('Ventas sin costo informadas', $near($P['net_missing'], $missing, 1), json_encode([$P['net_missing'], round($missing, 2)]));
$naive = ($P['net'] - $P['cogs']) / $P['net'] * 100;
ok('El margen no se infla con costo cero', $P['net_missing'] <= 0 || $P['margin_pct'] < round($naive, 1), json_encode([$P['margin_pct'], round($naive, 1)]));
ok('Cobertura de costo = ventas con costo ÷ ventas', $near($P['coverage_pct'], $P['net_c'] / $P['net'] * 100, 0.06));
ok('Productos sin costo listados', $P['net_missing'] <= 0 || ($P['missing_cost_products'] > 0 && $near(array_sum(array_column($P['missing_cost'], 'net_missing')), $P['net_missing'], max(1, $P['net_missing'] * 0.5))));
$sid = intval($db->query("SELECT si.id FROM sale_items si JOIN sales s ON s.id = si.sale_id WHERE s.status = 'completed' AND date(s.created_timestamp) BETWEEN '$from' AND '$to' AND si.total_cost > 0 ORDER BY si.subtotal DESC LIMIT 1")->fetchColumn());
$row = $db->query("SELECT si.*, (s.total_amount - s.tax_amount) / s.subtotal AS k FROM sale_items si JOIN sales s ON s.id = si.sale_id WHERE si.id = $sid")->fetch(\PDO::FETCH_ASSOC);
$db->exec("UPDATE sale_items SET total_cost = 0 WHERE id = $sid");
$P2 = $report()['profitability'];
ok('Quitar el costo de una línea la saca del margen (no la suma como utilidad)', $near($P2['net_missing'] - $P['net_missing'], $row['subtotal'] * $row['k'], 0.05)
	&& $near($P2['profit'], $P['profit'] - ($row['subtotal'] * $row['k'] - $row['total_cost']), 0.1), json_encode([$P['profit'], $P2['profit']]));
$db->exec("UPDATE sale_items SET total_cost = {$row['total_cost']} WHERE id = $sid");

echo "\n3. Costo contra el lote (costos desactualizados)\n";
ok('Ventas verificadas + distinto al lote + sin lote = ventas con costo', $near($P['net_c'] * $P['verified_pct'] / 100 + $P['net_offlot'] + $P['net_nolot'], $P['net_c'], $P['net_c'] * 0.001 + 1));
$lot = $db->query("SELECT si.id, si.unit_cost, si.amount, si.total_cost FROM sale_items si JOIN sales s ON s.id = si.sale_id WHERE s.status = 'completed' AND date(s.created_timestamp) BETWEEN '$from' AND '$to'
	AND si.total_cost > 0 AND si.stock_id IN (SELECT stock_id FROM stock_log WHERE price > 0) ORDER BY si.id LIMIT 1")->fetch(\PDO::FETCH_ASSOC);
$db->exec("UPDATE sale_items SET unit_cost = unit_cost * 1.5, total_cost = total_cost * 1.5 WHERE id = {$lot['id']}");
$P3 = $report()['profitability'];
ok('Costo inflado 50 % se detecta como distinto al lote', $P3['net_offlot'] > $P['net_offlot']);
ok('Utilidad con costo del lote no cambia con el costo inflado', $near($P3['profit_lot'], $P['profit_lot'], 0.05) && $P3['profit'] < $P['profit'], json_encode([$P['profit_lot'], $P3['profit_lot']]));
$db->exec("UPDATE sale_items SET unit_cost = {$lot['unit_cost']}, total_cost = {$lot['total_cost']} WHERE id = {$lot['id']}");

echo "\n4. Descuentos, períodos y margen negativo\n";
$disc = floatval($db->query("SELECT SUM(discount_amount) FROM sales WHERE status = 'completed' AND date(created_timestamp) BETWEEN '$from' AND '$to'")->fetchColumn());
ok('Descuentos = Σ descuentos de los tickets (± líneas que no suman el subtotal)', $P['net_archive'] > 0 || $near($P['discount'], $disc, 1), json_encode([$P['discount'], $disc]));
ok('Utilidad cedida = descuentos sobre ventas con costo', $near($P['profit_nodisc'] - $P['profit'], $P['discount_c'], 0.05));
$cmp = array_column($P['compare'], null, 'key');
ok('Comparación: período, anterior, año anterior, día, mes y año a la fecha', count($cmp) === 6 && $near($cmp['period']['margin_pct'], $P['margin_pct'], 0.01));
ok('Período sin ventas: margen «Sin información», no 0 %', $report(['start_date' => '2001-01-01', 'end_date' => '2001-01-07'])['profitability']['margin_pct'] === null);
$neg = $db->query("SELECT si.id, si.total_cost, si.product_id FROM sale_items si JOIN sales s ON s.id = si.sale_id WHERE s.status = 'completed' AND date(s.created_timestamp) BETWEEN '$from' AND '$to' AND si.total_cost > 0
	AND si.product_id IN (SELECT product_id FROM sale_items GROUP BY product_id HAVING COUNT(*) = 1) LIMIT 1")->fetch(\PDO::FETCH_ASSOC);
if ($neg)
{
	$db->exec("UPDATE sale_items SET total_cost = subtotal * 2 WHERE id = {$neg['id']}");
	$P4 = $report()['profitability'];
	ok('Producto vendido bajo el costo aparece con margen negativo', in_array(intval($neg['product_id']), array_column($P4['negative'], 'id'), true));
	$db->exec("UPDATE sale_items SET total_cost = {$neg['total_cost']} WHERE id = {$neg['id']}");
}

echo "\n5. Precio, volumen y costo\n";
$V = $P['pvc'];
ok('Puente calculado (período anterior con detalle)', $V !== null);
if ($V)
{
	ok('Utilidad anterior + volumen + precio + costo + mezcla = utilidad actual', $near($V['prev_profit'] + $V['volume'] + $V['price'] + $V['cost'] + $V['mix_new'] + $V['mix_lost'], $V['profit'], 0.5));
	ok('Utilidad actual del puente = utilidad bruta (sin archivo)', $P['net_archive'] > 0 || $near($V['profit'], $P['profit'], 1), json_encode([$V['profit'], $P['profit']]));
	$r = $V['rows'][0];
	ok('Cada producto: volumen + precio + costo + mezcla = variación', $near($r['volume'] + $r['price'] + $r['cost'] + $r['mix'], $r['delta'], 0.05));
}

echo "\n6. Contribución con costos asignables confiables\n";
$run = $db->query("SELECT * FROM hr_payroll_runs WHERE status = 'aprobado' ORDER BY id LIMIT 1")->fetch(\PDO::FETCH_ASSOC);
ok('Sin nómina que cubra el período: sin contribución', $report(['start_date' => '2001-01-01', 'end_date' => '2001-01-07'])['profitability']['contribution']['reliable_count'] === 0);
if ($run)
{
	$C = $report(['start_date' => $run['period_start'], 'end_date' => $run['period_end']])['profitability']['contribution'];
	$ok = array_values(array_filter($C['rows'], fn($r) => $r['contribution'] !== null));
	ok('Nómina aprobada que cubre el período: contribución = utilidad − nómina', count($ok) === 1 && $near($ok[0]['contribution'], $ok[0]['profit'] - $run['total_cost'], 0.05), json_encode($ok));
	$C2 = $report(['start_date' => $run['period_start'], 'end_date' => date('Y-m-d', strtotime($run['period_end'] . ' +3 days'))])['profitability']['contribution'];
	ok('Cobertura parcial de nómina: no se estima', $C2['reliable_count'] === 0);
	$C3 = $report(['start_date' => $run['period_start'], 'end_date' => $run['period_end'], 'department' => 'dairy'])['profitability']['contribution'];
	ok('Con filtro de departamento: no se asigna nómina', $C3['product_filtered'] && $C3['reliable_count'] === 0);
}

echo "\n$count pruebas, $fails fallas\n";
exit($fails ? 1 : 0);
