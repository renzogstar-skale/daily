<?php
/**
 * Informes comerciales de Ventas y Rentabilidad (SalesReportsService): consistencia de totales entre sucursal,
 * departamento y producto, fórmulas de utilidad y margen, metas, comparación y filtros.
 * Uso: php tests/dairy/test_sales_reports.php [base.db]
 */

use Grocy\Helpers\DailyFilters;
use Grocy\Services\SalesDashboardService;
use Grocy\Services\SalesReportsService;

require __DIR__ . '/bootstrap.php';

$sr = SalesReportsService::GetInstance();
$db = Grocy\Services\DatabaseService::GetInstance()->GetDbConnectionRaw();
$near = fn($a, $b, $tol = 0.05) => abs(floatval($a) - floatval($b)) <= $tol;
$to = $db->query("SELECT date(MAX(created_timestamp)) FROM sales WHERE status = 'completed'")->fetchColumn();
$from = date('Y-m-d', strtotime("$to -29 days"));

echo "\n1. Total = Σ sucursales = Σ departamentos = Σ productos (mismos filtros)\n";
foreach ([[], ['branch_id' => 1], ['department' => 'tecnologia'], ['branch_id' => 2, 'department' => 'dairy']] as $q)
{
	$t0 = microtime(true);
	$f = DailyFilters::FromQuery($q + ['start_date' => $from, 'end_date' => $to]);
	$r = $sr->Report($f);
	$ms = round((microtime(true) - $t0) * 1000);
	$c = $r['consistency'];
	$tag = json_encode($q);
	ok("Consistencia $tag", $c["ok"], json_encode($c));
	ok("Σ filas de sucursal = total $tag", $near(array_sum(array_column($r['branches']['rows'], 'net')), $r['dashboard']['net'], 1));
	ok("Σ filas de departamento = total $tag", $near(array_sum(array_column($r['departments']['rows'], 'net')), $r['dashboard']['net'], 1));
	ok("Σ participación de departamentos ≈ 100 % $tag", $r['dashboard']['net'] <= 0 || $near(array_sum(array_column($r['departments']['rows'], 'share_pct')), 100, 0.6));
	ok("Informe en menos de 3 s $tag", $ms < 3000, $ms . ' ms');
}

echo "\n2. Sin filtro de producto, la venta neta coincide con el Tablero (Σ total − IVA)\n";
$f = DailyFilters::FromQuery(['start_date' => $from, 'end_date' => $to]);
$r = $sr->Report($f);
$hdr = floatval($db->query("SELECT SUM(total_amount - tax_amount) FROM sales WHERE status = 'completed' AND date(created_timestamp) BETWEEN '$from' AND '$to'")->fetchColumn());
ok('Venta neta = Σ (total − IVA)', $near($r['dashboard']['net'], $hdr, 0.5), json_encode([$r['dashboard']['net'], round($hdr, 2)]));
$sd = SalesDashboardService::GetInstance()->Dashboard($f);
ok('Tickets = Tablero de cinco bloques', $r['dashboard']['tickets'] === $sd['evolution']['tickets'], json_encode([$r['dashboard']['tickets'], $sd['evolution']['tickets']]));
ok('Ticket promedio = venta / tickets', $near($r['dashboard']['avg_ticket'], $r['dashboard']['net'] / max(1, $r['dashboard']['tickets']), 0.01));
ok('Σ por hora = total', $near(array_sum($r['dashboard']['hourly']['net']), $r['dashboard']['net'], 1));
ok('Σ serie diaria = total', $near(array_sum($r['dashboard']['vs_prev']['net']), $r['dashboard']['net'], 1));
ok('Acumulado final = total', $near(end($r['dashboard']['cumulative']['net']), $r['dashboard']['net'], 1));
ok('Σ tickets por sucursal = tickets totales', array_sum(array_column($r['branches']['rows'], 'tickets')) === $r['dashboard']['tickets']);

echo "\n3. Rentabilidad: utilidad = venta − costo; margen = utilidad / venta × 100\n";
$p = $r['profitability'];
ok('Utilidad bruta', $p['profit'] === null || $near($p['profit'], $p['net'] - $p['cogs'], 0.02), json_encode([$p["net"], $p["cogs"], $p["profit"]]));
ok('Margen bruto %', $p['margin_pct'] === null || $near($p['margin_pct'], ($p['net'] - $p['cogs']) / $p['net'] * 100, 0.06), $p['margin_pct']);
ok('Σ utilidad por departamento = utilidad total', $p['profit'] === null || $near(array_sum(array_column($p['by_department'], 'profit')), $p['profit'], 1));
ok('Margen nulo con venta cero', SalesReportsService::Margin(0, 0) === null && SalesReportsService::Margin(100, 400) === null && SalesReportsService::Margin(100, 60) === 40.0);
ok('Departamentos ordenados por utilidad', array_column($p['by_department'], 'profit') === array_values(array_reverse(array_values(array_sort_num(array_column($p['by_department'], 'profit'))))));

echo "\n4. Rankings\n";
$nets = array_column($r['branches']['rows'], 'net');
$sorted = $nets;
rsort($sorted);
ok('Ranking de sucursales descendente', $nets === $sorted);
ok('Top 10 productos por facturación descendente', array_column($r['products']['top_net'], 'net') === array_values(array_reverse(array_sort_num(array_column($r['products']['top_net'], 'net')))));
ok('Top 10 productos por unidades descendente', array_column($r['products']['top_units'], 'units') === array_values(array_reverse(array_sort_num(array_column($r['products']['top_units'], 'units')))));
ok('Sucursal líder informada en productos', count(array_filter($r['products']['rows'], fn($x) => $x['leader'] !== null)) === count($r['products']['rows']));

echo "\n5. Metas y comparación\n";
$g = $r['dashboard']['goal'];
ok('Meta del período desde dairy_budgets (o Sin información)', $g === null || $g > 0, $g);
ok('Cumplimiento = venta / meta', $g === null || $near($r['dashboard']['goal_pct'], $r['dashboard']['net'] / $g * 100, 0.06));
$dr = $sr->Report(DailyFilters::FromQuery(['department' => 'carnes', 'start_date' => $from, 'end_date' => $to]));
$dRow = array_values(array_filter($r['departments']['rows'], fn($x) => $x['key'] === 'carnes'))[0] ?? null;
ok('Meta por departamento = meta de la fila del departamento', $dr['dashboard']['goal'] === null || ($dRow && $near($dr['dashboard']['goal'], $dRow['goal'], 0.5)), json_encode([$dr['dashboard']['goal'], $dRow['goal'] ?? null]));
$bd = $sr->Report(DailyFilters::FromQuery(['branch_id' => 1, 'department' => 'carnes', 'start_date' => $from, 'end_date' => $to]));
ok('Meta sucursal + departamento ≤ meta del departamento en la red', $bd['dashboard']['goal'] === null || ($dr['dashboard']['goal'] !== null && $bd['dashboard']['goal'] < $dr['dashboard']['goal']), json_encode([$bd['dashboard']['goal'], $dr['dashboard']['goal']]));
ok('Cumplimiento por departamento = venta / meta', count(array_filter($r['departments']['rows'], fn($x) => $x['goal'] !== null && !$near($x['goal_pct'], $x['net'] / $x['goal'] * 100, 0.06))) === 0);

echo "\n5b. Archivo histórico consolidado (si existe)\n";
$cut = $r['archive']['cutover'];
if ($cut)
{
	$yr = $sr->Report(DailyFilters::FromQuery(['start_date' => substr($to, 0, 4) . '-01-01', 'end_date' => $to]));
	ok('Año actual: consistencia total = Σ sucursal = Σ depto (archivo + transaccional)', $yr['consistency']['ok'], json_encode($yr['consistency']));
	ok('Año actual: Σ serie diaria = total', $near(array_sum($yr['dashboard']['vs_prev']['net']), $yr['dashboard']['net'], 1));
	ok('Año actual: Σ tickets por sucursal = tickets totales', array_sum(array_column($yr['branches']['rows'], 'tickets')) === $yr['dashboard']['tickets']);
	ok('Año actual: meta del año completa', $yr['dashboard']['goal'] > 0);
	ok('Productos sin fila «producto 0» del archivo', count(array_filter($yr['products']['rows'], fn($x) => $x['id'] === 0)) === 0);
	ok('Año actual: Σ curva por hora = total (archivo repartido por hora)', $near(array_sum($yr['dashboard']['hourly']['net']), $yr['dashboard']['net'], 2));
	ok('Año actual: Σ productos = total', $near($yr['consistency']['by_product'], $yr['dashboard']['net'], 5));
	$hist = array_filter($r['history']['years'], fn($y) => $y['has_data']);
	ok('Historial con los tres años', count($hist) === 3, count($hist));
	ok('Interanual con dato', $sr->Report($f, 'year_ago')['dashboard']['growth_pct'] !== null && $r['history']['yoy']['pct'] !== null);
	$total = floatval($db->query("SELECT SUM(net) FROM daily_sales_archive WHERE day BETWEEN '2025-01-01' AND '2025-12-31'")->fetchColumn());
	$y25 = array_values(array_filter($r['history']['years'], fn($y) => $y['year'] === 2025))[0];
	ok('Año 2025 = Σ archivo 2025', $near($y25['net'], $total, 1), json_encode([$y25['net'], round($total, 2)]));
	ok('Con filtro de categoría no se mezcla el archivo', $sr->Report(DailyFilters::FromQuery(['group_id' => 1, 'start_date' => '2025-01-01', 'end_date' => '2025-01-31']))['dashboard']['net'] == 0);
	$dd = $sr->Report(DailyFilters::FromQuery(['department' => 'dairy', 'start_date' => '2025-03-01', 'end_date' => '2025-03-31']));
	ok('Archivo filtrado por departamento', $dd['dashboard']['net'] > 0 && count($dd['departments']['rows']) === 1 && $dd['consistency']['ok']);
}
$ya = $sr->Report($f, 'year_ago');
ok('Comparación interanual usa el mismo período del año anterior', $ya['period']['prev_from'] === date('Y-m-d', strtotime("$from -1 year")));
ok('Sin datos del año anterior → crecimiento nulo (no inventado)', $ya['dashboard']['prev_net'] > 0 || $ya['dashboard']['growth_pct'] === null);
ok('Años sin datos marcados sin información', count(array_filter($r['history']['years'], fn($y) => !$y['has_data'] && $y['net'] === null)) === count(array_filter($r['history']['years'], fn($y) => !$y['has_data'])));

echo "\n6. Período sin ventas\n";
$e = $sr->Report(DailyFilters::FromQuery(['start_date' => '2001-01-01', 'end_date' => '2001-01-07']));
ok('Venta cero sin errores', $e['dashboard']['net'] == 0 && $e['dashboard']['avg_ticket'] === null && $e['profitability']['margin_pct'] === null && $e['dashboard']['growth_pct'] === null);

function array_sort_num(array $a): array
{
	sort($a);
	return $a;
}


