<?php
/**
 * Filtros compartidos y coherencia entre tableros: Ventas (cinco bloques), HOY/Panel (DailyMetricsService) y Agotados
 * deben dar las mismas cifras para la misma sucursal, departamento y período.
 */

use Grocy\Helpers\DailyFilters;
use Grocy\Services\DailyMetricsService;
use Grocy\Services\SalesDashboardService;
use Grocy\Services\StockoutService;

require __DIR__ . '/bootstrap.php';

$dm = DailyMetricsService::GetInstance();
$sd = SalesDashboardService::GetInstance();
$eq = fn($a, $b) => abs(floatval($a) - floatval($b)) < 0.011;
$to = date('Y-m-d', strtotime('-1 day'));
$from = date('Y-m-d', strtotime("$to -13 days"));

echo "\n1. Normalización de filtros\n";
$f = DailyFilters::FromQuery(['branch_id' => '1', 'department' => 'CARNES', 'start_date' => $to, 'end_date' => $from]);
ok('Departamento en minúsculas y válido', $f['department'] === 'carnes');
ok('Fechas invertidas se ordenan', $f['start_date'] === $from && $f['end_date'] === $to);
ok('Departamento desconocido se ignora', DailyFilters::FromQuery(['department' => 'inventado'])['department'] === null);
ok('Período legible', DailyFilters::PeriodLabel('2026-11-01', '2026-11-04') === '1–4 de noviembre de 2026');
ok('Query solo con filtros que aplica la ruta', DailyFilters::Query($f, '/today') === http_build_query(['branch_id' => 1, 'department' => 'carnes', 'end_date' => $to]));

echo "\n2. Ventas = misma cifra que HOY/Panel (DailyMetricsService)\n";
foreach ([[null, null], [1, null], [null, 'carnes'], [2, 'tecnologia'], [null, 'dairy']] as [$b, $d])
{
	$f = DailyFilters::FromQuery(['branch_id' => $b, 'department' => $d, 'start_date' => $from, 'end_date' => $to]);
	$dash = $sd->Dashboard($f);
	$ref = $dm->Sales($b, $from, $to, $d ? [$d] : null);
	$label = 'sucursal ' . ($b ?? 'red') . ', ' . ($d ?? 'todos');
	ok("Ventas netas ($label)", $eq($dash['evolution']['net'] ?? 0, $ref['net_sales']), json_encode([$dash["evolution"]["net"], $ref["net_sales"]]));
	ok("Tickets ($label)", $dash['evolution']['tickets'] === $ref['tickets'], json_encode([$dash["evolution"]["tickets"], $ref["tickets"]]));
	ok("Serie diaria suma el total ($label)", $eq(array_sum(array_column($dash['evolution']['series'], 'v')), $ref['net_sales']));
	ok("Semanas suman el total ($label)", $eq(array_sum(array_column($dash['evolution']['weeks'], 'v')), $ref['net_sales']));
	ok("Horas suman el total ($label)", $eq(array_sum($dash['evolution']['hourly']), $ref['net_sales']));
	$split = $dash['comparison']['split'];
	ok("Entre semana + fin de semana = total ($label)", $eq($split['weekday_net'] + $split['weekend_net'], $ref['net_sales']));
	if ($split['weekday_pct'] !== null)
	{
		ok("Participaciones suman 100 % ($label)", abs($split['weekday_pct'] + $split['weekend_pct'] - 100) < 0.11);
	}
	$top = $dash['top']['rows'];
	ok("Top 5 ordenado y ≤ 5 ($label)", count($top) <= 5 && $top === array_values(array_reverse(array_values(array_reverse($top)))) && (count($top) < 2 || $top[0]['net'] >= $top[1]['net']));
	if ($d !== null)
	{
		$names = DailyMetricsService::DEPARTMENTS[$d][2];
		ok("Top 5 solo del departamento ($label)", !array_filter($top, fn($r) => !in_array($r['department'], $names, true)));
		ok("Productividad no aplica con departamento ($label)", $dash['productivity']['applies'] === false);
	}
	ok("Sin tráfico inventado ($label)", $dash['traffic']['has_traffic'] === false);
	ok("Tickets por hora suman los tickets ($label)", $d !== null || array_sum($dash['traffic']['tickets_by_hour']) === $ref['tickets']);
}

echo "\n3. Producto o SKU\n";
$sku = $db->query("SELECT p.sku FROM sale_items si JOIN sales s ON s.id = si.sale_id JOIN products p ON p.id = si.product_id WHERE s.status = 'completed' AND date(s.created_timestamp) BETWEEN '$from' AND '$to' AND IFNULL(p.sku, '') <> '' GROUP BY p.id ORDER BY SUM(si.subtotal) DESC LIMIT 1")->fetchColumn();
if ($sku)
{
	$f = DailyFilters::FromQuery(['product' => $sku, 'start_date' => $from, 'end_date' => $to]);
	$dash = $sd->Dashboard($f);
	ok('Top 5 de un SKU = ese producto', count($dash['top']['rows']) === 1 && $dash['top']['rows'][0]['sku'] === $sku);
	ok('Participación del único producto = 100 %', $dash['top']['rows'][0]['share_pct'] == 100.0);
	ok('Ventas del SKU = Σ líneas del producto', $eq($dash['evolution']['net'], $dash['top']['rows'][0]['net']));
}

echo "\n4. Agotados = misma regla que HOY (StockStatusCounts)\n";
foreach ([null, 1, 3] as $b)
{
	foreach ([null, 'carnes', 'tecnologia'] as $d)
	{
		$f = DailyFilters::FromQuery(['branch_id' => $b, 'department' => $d]);
		$so = StockoutService::GetInstance()->List($f);
		$ref = $dm->StockStatusCounts($b, 7, $d ? [$d] : null);
		ok('Agotados sucursal ' . ($b ?? 'red') . ', ' . ($d ?? 'todos'), $so['counts']['out'] === $ref['out_of_stock'], json_encode([$so["counts"]["out"], $ref["out_of_stock"]]));
	}
}
$row = $db->query("SELECT p.sku FROM products p WHERE p.active = 1 AND IFNULL(p.sku, '') <> '' AND p.min_stock_amount > 0 LIMIT 1")->fetchColumn();
$res = StockoutService::GetInstance()->List(DailyFilters::FromQuery(['product' => $row]));
ok('Búsqueda por SKU devuelve solo ese SKU', $res['searching'] && !array_filter($res['rows'], fn($r) => $r['sku'] !== $row));
ok('Disponible ≤ registrado', !array_filter($res['rows'], fn($r) => $r['available'] > $r['physical'] + 0.001));

echo "
$count verificaciones, $fails fallidas
";
exit($fails > 0 ? 1 : 0);
