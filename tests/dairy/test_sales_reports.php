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

echo "\n3. Rentabilidad: utilidad = venta con costo − costo; margen = utilidad / venta con costo × 100\n";
$p = $r['profitability'];
ok('Utilidad bruta = ventas con costo − costo (sin costo ≠ costo cero)', $p['profit'] === null || $near($p['profit'], $p['net_c'] - $p['cogs'], 0.02), json_encode([$p['net_c'], $p['cogs'], $p['profit']]));
ok('Margen bruto % sobre ventas con costo', $p['margin_pct'] === null || $near($p['margin_pct'], ($p['net_c'] - $p['cogs']) / $p['net_c'] * 100, 0.06), $p['margin_pct']);
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
ok('Sin ventas: sin alertas ni top 5 inventados', $e['dashboard']['alerts'] === [] && $e['dashboard']['top5'] === []);

echo "\n7. Tablero de ventas: bruto, descuentos, comparaciones, canal, top 5 y alertas\n";
$D = $r['dashboard'];
$raw = $db->query("SELECT SUM(subtotal), SUM(discount_amount) FROM sales WHERE status = 'completed' AND date(created_timestamp) BETWEEN '$from' AND '$to'")->fetch(PDO::FETCH_NUM);
ok('Ventas brutas = Σ subtotal de ventas', $near($D['gross'], $raw[0], 1), json_encode([$D['gross'], $raw[0]]));
ok('Descuentos = Σ descuento de ventas', $near($D['discounts'], $raw[1], 1), json_encode([$D['discounts'], $raw[1]]));
ok('Bruto − descuentos = neto', $near($D['gross'] - $D['discounts'], $D['net'], 0.05));
foreach ($D['comparisons'] as $cp)
{
	ok("Comparación {$cp['key']} con períodos de igual duración", $cp['key'] === 'mtd' ? $cp['prev_days'] <= $cp['days'] : $cp['days'] === $cp['prev_days'], json_encode([$cp['days'], $cp['prev_days']]));
}
$day = floatval($db->query("SELECT SUM(total_amount - tax_amount) FROM sales WHERE status = 'completed' AND date(created_timestamp) = '$to'")->fetchColumn());
ok('Día vs. ayer usa la venta neta de la fecha final', $near($D['comparisons'][0]['net'], $day, 0.5));
ok('Σ canales = venta neta', $near(array_sum(array_column($D['channels'], 'net')), $D['net'], 1));
ok('Σ medios de pago = venta neta', $near(array_sum(array_column($D['payments'], 'net')), $D['net'], 1));
ok('Top 5 = cinco primeros de Productos', array_column($D['top5'], 'id') === array_slice(array_column($r['products']['rows'], 'id'), 0, 5));
ok('Alertas solo comerciales', count(array_filter($D['alerts'], fn($a) => !in_array($a['kind'], ['goal', 'day_vs_last_week', 'wtd', 'mtd', 'period', 'year_ago', 'avg_ticket', 'tickets', 'branch', 'dept'], true))) === 0);
$fd = $sr->Report(DailyFilters::FromQuery(['department' => 'dairy', 'start_date' => $from, 'end_date' => $to]))['dashboard'];
ok('Con departamento: neto ≤ bruto y Σ canales = neto', $fd['net'] <= $fd['gross'] + 0.01 && $near(array_sum(array_column($fd['channels'], 'net')), $fd['net'], 1));
ok('Cumplimiento de meta sin tope al 100 %', $D['goal'] === null || $near($D['goal_pct'], $D['net'] / $D['goal'] * 100, 0.1));

echo "\n8. Ventas por departamento: configuración, crecimiento, rentabilidad, ticket y conciliación\n";
$f = DailyFilters::FromQuery(['start_date' => $from, 'end_date' => $to]);
$DP = $sr->Report($f)['departments'];
$keys = array_column($DP['rows'], 'key');
ok('Sin departamentos duplicados en la tabla', count($keys) === count(array_unique($keys)));
ok('Conciliación: Σ departamentos = total comercial', $DP['reconciliation']['ok'], json_encode(array_slice($DP['reconciliation'], 0, 3)));
$sold = array_values(array_filter($DP['rows'], fn($x) => !$x['no_sales']));
ok('Ranking 1..n solo entre departamentos con venta', array_column($sold, 'rank') === range(1, max(1, count($sold))) || !$sold);
ok('Sin venta: sin posición, participación 0', count(array_filter($DP['rows'], fn($x) => $x['no_sales'] && ($x['rank'] !== null || $x['net'] > 0))) === 0);
ok('«Otros» solo aparece con venta', count(array_filter($DP['rows'], fn($x) => $x['key'] === 'otros' && $x['no_sales'])) === 0);
foreach ($sold as $x)
{
	if ($x['margin_pct'] !== null && !$near($x['gross_profit'], $x['net'] - $x['cogs'], 0.05)) { ok("Utilidad {$x['key']} = venta − costo", false); }
	if ($x['avg_ticket'] !== null && !$near($x['avg_ticket'], $x['net'] / $x['tickets'], 0.01)) { ok("Ticket {$x['key']} = venta ÷ tickets", false); }
}
ok('Utilidad y ticket por departamento coherentes', true);
foreach ($DP['growth'] as $gk => $gw)
{
	ok("Crecimiento $gk: ventanas comparables", $gk === 'month' ? $gw['prev_days'] <= $gw['days'] : $gw['days'] === $gw['prev_days'], json_encode([$gw['days'], $gw['prev_days']]));}
$w = $DP['growth']['week'];
$wk = floatval($db->query("SELECT SUM(total_amount - tax_amount) FROM sales WHERE status = 'completed' AND date(created_timestamp) BETWEEN '{$w['from']}' AND '{$w['to']}'")->fetchColumn());
ok('Ventana semanal = venta neta de esos 7 días', $near($w['net'], $wk, 1), json_encode([$w['net'], round($wk, 2)]));
$hs = array_sum(array_map('array_sum', $DP['hourly']['series']));
ok('Σ ventas por hora = total de departamentos', $near($hs, $DP['reconciliation']['sum'], 1), json_encode([$hs, $DP['reconciliation']['sum']]));
$fc = $sr->Report(DailyFilters::FromQuery(['department' => 'dairy', 'start_date' => $from, 'end_date' => $to]))['departments'];
ok('Con filtro de departamento solo ese departamento', array_column($fc['rows'], 'key') === ['dairy'] || !$fc['rows'], json_encode(array_column($fc['rows'], 'key')));
$g = $db->query('SELECT id FROM product_groups LIMIT 1')->fetchColumn();
$fg = $sr->Report(DailyFilters::FromQuery(['group_id' => $g, 'start_date' => $from, 'end_date' => $to]))['departments'];
ok('Con filtro de categoría el ticket no se atribuye', !$fg['ticket_attributable'] && count(array_filter($fg['rows'], fn($x) => $x['avg_ticket'] !== null)) === 0);
ok('Con filtro de categoría no se aplican metas globales de departamento', !$fg['has_goals'] && count(array_filter($fg['rows'], fn($x) => $x['goal'] !== null)) === 0);

// Auditoría comercial ampliada (bruto, descuentos, semanal, subcategorías = depto, LY sin inventar)
$okComm = true;
foreach ($sold as $x)
{
	$subSum = array_sum(array_column($x['subcategories'] ?? [], 'net'));
	$wSum = array_sum($x['weekly'] ?? []);
	$brSum = array_sum(array_column($x['by_branch'] ?? [], 'net'));
	$okComm = $okComm
		&& $near($x['gross'] - $x['discounts'], $x['net'], 0.05)
		&& ($x['units'] <= 0 || $near($x['avg_unit_price'], $x['net'] / $x['units'], 0.02))
		&& (!$DP['ticket_attributable'] || $x['tickets'] <= 0 || $near($x['units_per_ticket'], $x['units'] / $x['tickets'], 0.02))
		&& $near($subSum, $x['net'], 1.5)
		&& $near($wSum, $x['net'], 0.2)
		&& $near($brSum, $x['net'], 0.2);
}
ok('Depto comercial: bruto−desc=neto, precio/ud, ud/ticket, Σ subcategorías=depto, Σ semanas=depto, Σ sucursales=depto', $okComm);
$noHistLy = $sr->Report(DailyFilters::FromQuery(['start_date' => '2024-02-01', 'end_date' => '2024-02-28']))['departments'];
ok('Sin historial del año anterior: ly_has_data false y valores LY nulos (no inventados)', !$noHistLy['ly_has_data'] && $noHistLy['ly_total'] === null && $noHistLy['ly_delta_pct'] === null);

$ms = Grocy\Services\DailyMetricsService::GetInstance();
$cfg = SalesReportsService::DeptConfig()['departments'];
$base = array_values(array_filter($cfg, fn($x) => $x['key'] === 'congelados'))[0];
$ms->SaveDepartments([['key' => 'congelados', 'code' => '90', 'name' => 'Frozen', 'color' => '#38BDF8', 'sort_order' => 5, 'source_values' => $base['values']]]);
$after = $sr->Report($f)['departments'];
$fr = array_values(array_filter($after['rows'], fn($x) => $x['key'] === 'congelados'))[0] ?? null;
ok('Renombrar y codificar no cambia la venta', $fr && $fr['name'] === 'Frozen' && $fr['code'] === '90' && $near($fr['net'], array_values(array_filter($DP['rows'], fn($x) => $x['key'] === 'congelados'))[0]['net'], 0.01));
ok('Filtro de departamento con nombre configurado', DailyFilters::Describe(DailyFilters::FromQuery(['department' => 'congelados']), DailyFilters::Options(DailyFilters::FromQuery([])))['department'] === 'Frozen');
expectError('Código repetido rechazado', fn() => $ms->SaveDepartments([['key' => 'dairy', 'code' => '90', 'name' => 'Lácteos y huevos', 'source_values' => ['Lácteos']]]));
expectError('Valor de producto en dos departamentos rechazado (categoría duplicada)', fn() => $ms->SaveDepartments([['key' => 'bakery', 'name' => 'Panadería', 'source_values' => ['Panadería', 'Congelados']]]));
expectError('Departamento base no se elimina', fn() => $ms->SaveDepartments([['key' => 'hogar', 'delete' => true]]));
$ms->SaveDepartments([['key' => '', 'code' => '94', 'name' => 'Deli', 'source_values' => ['Charcutería']]]);
$nd = array_values(array_filter($sr->Report($f)['departments']['rows'], fn($x) => $x['key'] === 'deli'))[0] ?? null;
ok('Departamento nuevo sin productos: visible como «Sin ventas»', $nd && $nd['no_sales'] && $nd['code'] === '94');
$ms->SaveDepartments([['key' => 'deli', 'delete' => true]]);
ok('Departamento nuevo eliminado', !isset(Grocy\Services\DailyMetricsService::Departments()['deli']));

echo "\n9. Sucursales: ranking con períodos completos, sin datos ≠ cero, mismas fechas, margen, hora e histórico\n";
$BR = $sr->Report($f)['branches'];
$withData = array_values(array_filter($BR['rows'], fn($x) => !$x['no_data']));
ok('Σ sucursales = total comercial', $BR['reconciliation']['ok'] && $near($BR['reconciliation']['sum'], $BR['reconciliation']['total'], 1));
ok('Sin sucursales duplicadas', count(array_unique(array_column($BR['rows'], 'id'))) === count($BR['rows']));
$rk = array_values(array_filter(array_column($BR['rows'], 'rank')));
ok('Posiciones 1..n solo para períodos completos', $rk === range(1, count($rk)) && count(array_filter($BR['rows'], fn($x) => ($x['rank'] !== null) !== $x['complete'])) === 0);
$rn = array_map(fn($x) => $x['net'], array_filter($BR['rows'], fn($x) => $x['rank'] !== null));
$rs = $rn;
rsort($rs);
ok('Ranking descendente por ventas netas', array_values($rn) === $rs);
ok('Sin datos: venta nula (no cero), fuera de gráficos', count(array_filter($BR['rows'], fn($x) => $x['no_data'] && ($x['net'] !== null || isset($BR['series'][$x['id']]) || in_array($x['id'], $BR['top5'], true)))) === 0);
ok('Participaciones suman 100 %', $near(array_sum(array_column($withData, 'share_pct')), 100, 0.6));
$okF = true;
foreach ($withData as $x)
{
	$okF = $okF && ($x['gross_profit'] === null || ($near($x['gross_profit'], $x['net'] - $x['cogs'], 0.02) && $near($x['margin_pct'], $x['gross_profit'] / $x['net'] * 100, 0.06)))
		&& ($x['tickets'] == 0 || $near($x['avg_ticket'], $x['net'] / $x['tickets'], 0.01));
}
ok('Utilidad, margen y ticket por sucursal = fórmula', $okF);
ok('Período anterior de igual duración', count(range(strtotime($r['period']['prev_from']), strtotime($r['period']['prev_to']), 86400)) === $r['period']['days']);
ok('Ventanas de crecimiento de igual duración', $BR['growth']['week']['days'] === 7 && $BR['growth']['week']['prev_days'] === 7 && $BR['growth']['month']['days'] >= $BR['growth']['month']['prev_days'] - 0);
$wk = $db->query("SELECT ROUND(SUM(total_amount - tax_amount), 2) FROM sales WHERE status = 'completed' AND created_timestamp >= '{$BR['growth']['week']['from']}' AND created_timestamp < date('{$BR['growth']['week']['to']}', '+1 day')")->fetchColumn();
ok('Ventana semanal = SQL', $near($BR['growth']['week']['net'], $wk, 1), json_encode([$BR['growth']['week']['net'], $wk]));
$hs = 0;
foreach ($BR['hourly']['series'] as $s)
{
	$hs += array_sum($s);
}
ok('Σ ventas por hora = Σ sucursales', $near($hs, $BR['reconciliation']['sum'], 1));
$mi = array_search(substr($to, 0, 7), $BR['history']['months'], true);
$mSql = $db->query("SELECT ROUND(SUM(total_amount - tax_amount), 2) FROM sales WHERE status = 'completed' AND created_timestamp >= '" . substr($to, 0, 7) . "-01' AND created_timestamp < date('$to', '+1 day')")->fetchColumn();
ok('Histórico mensual: mes final = SQL', $mi !== false && $near(array_sum(array_map(fn($s) => $s[$mi] ?? 0, $BR['history']['series'])), $mSql, 1));
// Sucursal nueva que solo vendió el último día: aparece sin posición (período incompleto); una inactiva sin ventas no aparece
$db->exec("INSERT INTO branches (name, code, active) VALUES ('Prueba incompleta', 'TST-INC', 1), ('Prueba inactiva', 'TST-OFF', 0)");
$nb = intval($db->query("SELECT id FROM branches WHERE code = 'TST-INC'")->fetchColumn());
$pid = intval($db->query('SELECT id FROM products WHERE active = 1 LIMIT 1')->fetchColumn());
$db->exec("INSERT INTO sales (sale_number, branch_id, user_id, subtotal, total_amount, status, created_timestamp) VALUES ('TST-INC-1', $nb, 1, 100, 100, 'completed', '$to 10:00:00')");
$sid = intval($db->lastInsertId());
$db->exec("INSERT INTO sale_items (sale_id, product_id, amount, qu_id, unit_price, subtotal, total_cost) VALUES ($sid, $pid, 1, 1, 100, 100, 60)");
// Coordenadas: una válida y una (0, 0) inválida
$db->exec('UPDATE branches SET latitude = 25.98, longitude = -80.14 WHERE id = 1');
$db->exec('UPDATE branches SET latitude = 0, longitude = 0 WHERE id = 2');
$BR2 = SalesReportsService::GetInstance()->Report($f)['branches'];
$inc = array_values(array_filter($BR2['rows'], fn($x) => $x['id'] === $nb))[0] ?? null;
ok('Sucursal con período incompleto: sin posición, con su venta', $inc && $inc['rank'] === null && !$inc['complete'] && $near($inc['net'], 100, 0.01) && $inc['active_days'] === 1, json_encode($inc ? [$inc['rank'], $inc['active_days'], $inc['net']] : null));
ok('Incompleta no desplaza el ranking', array_values(array_filter(array_column($BR2['rows'], 'rank'))) === range(1, count($rk)));
ok('Sucursal inactiva sin ventas no aparece', !in_array('Prueba inactiva', array_column($BR2['rows'], 'name'), true));
ok('Solo coordenadas válidas para el mapa', $BR2['with_coords'] === 1);

function array_sort_num(array $a): array
{
	sort($a);
	return $a;
}


