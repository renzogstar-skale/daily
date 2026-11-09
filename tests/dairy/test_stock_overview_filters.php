<?php
/**
 * Filtros de las tarjetas de Stock & Movimientos: mapa de calor, top de productos y vencimientos.
 * Cada caso compara contra los registros (consulta directa) o contra las cifras del panel, no contra la propia salida.
 */

use Grocy\Services\DailyMetricsService;
use Grocy\Services\StockExecutiveOverviewService;

require __DIR__ . '/bootstrap.php';

$svc = StockExecutiveOverviewService::GetInstance();
$dm = DailyMetricsService::GetInstance();
$eq = fn($a, $b, $tol = 0.05) => abs(floatval($a) - floatval($b)) <= $tol;
$today = date('Y-m-d');
$all = $svc->GetExecutiveData([]);
$branches = array_column($all['branches_table'], 'id');
$hm = fn(array $f = []) => $svc->GetHeatmapWidget($f);
$top = fn(array $f = []) => $svc->GetTopProductsWidget($f);
$exp = fn(array $f = []) => $svc->GetExpiringWidget($f);

echo "\n1. Mapa de calor\n";
foreach ([7, 14, 30, 60, 90] as $n)
{
	$h = $hm(['heat_period' => $n]);
	ok("$n días = $n columnas", count($h['dates']) === $n && count($h['rows'][0]['cells']) === $n);
}
$custom = $hm(['heat_period' => 'custom', 'from' => '2026-10-01', 'to' => '2026-10-05']);
ok('Rango personalizado respeta fechas', array_column($custom['dates'], 'date') === ['2026-10-01', '2026-10-02', '2026-10-03', '2026-10-04', '2026-10-05']);
$threw = false;
try
{
	$hm(['heat_period' => 'custom', 'from' => '2026-10-05', 'to' => '2026-10-01']);
}
catch (\Exception $e)
{
	$threw = true;
}
ok('Rango invertido se rechaza', $threw);

$lastTotal = fn(array $h) => $h['rows'][0]['cells'][count($h['rows'][0]['cells']) - 1]['val'];
ok('Cierre de hoy = unidades del panel', $eq($lastTotal($hm(['metric' => 'units'])), $all['kpis']['stock_units']['numeric'], 1), $lastTotal($hm(['metric' => 'units'])) . ' vs ' . $all['kpis']['stock_units']['numeric']);
ok('Cierre de hoy = valor del panel', $eq($lastTotal($hm(['metric' => 'value'])), $all['kpis']['inventory_value']['numeric'], 1), $lastTotal($hm(['metric' => 'value'])) . ' vs ' . $all['kpis']['inventory_value']['numeric']);
ok('Críticos de hoy = bajo mínimo + agotados', intval($lastTotal($hm(['metric' => 'critical']))) === $all['kpis']['below_min']['numeric'] + $all['kpis']['out_of_stock']['numeric'], $lastTotal($hm(['metric' => 'critical'])) . ' vs ' . ($all['kpis']['below_min']['numeric'] + $all['kpis']['out_of_stock']['numeric']));

$u = $hm(['metric' => 'units', 'heat_period' => 7]);
$d3 = $u['dates'][3]['date'];
$after = floatval($db->query("SELECT IFNULL(SUM(sl.amount), 0) FROM stock_log sl JOIN locations l ON l.id = sl.location_id WHERE sl.undone = 0 AND sl.transaction_type <> 'product-opened' AND IFNULL(l.is_transit, 0) = 0 AND date(sl.row_created_timestamp) > '$d3'")->fetchColumn());
ok('Cierre histórico = stock actual − kardex posterior', $eq($u['rows'][0]['cells'][3]['val'], $all['kpis']['stock_units']['numeric'] - $after, 1));
$rowSum = array_sum(array_map(fn($r) => $r['cells'][3]['val'], array_slice($u['rows'], 1)));
ok('Total = suma de departamentos', $eq($rowSum, $u['rows'][0]['cells'][3]['val'], 0.5));
ok('Estadísticas salen de los totales diarios', $eq($u['stats']['min'], min(array_column($u['rows'][0]['cells'], 'val')), 0.01) && $eq($u['stats']['max'], max(array_column($u['rows'][0]['cells'], 'val')), 0.01) && $eq($u['stats']['avg'], array_sum(array_column($u['rows'][0]['cells'], 'val')) / 7, 0.02));
$first = $u['rows'][0]['cells'][0]['val'];
$expectedVar = ($first !== null && $first > 0) ? ($u['rows'][0]['cells'][6]['val'] - $first) / $first * 100 : null;
ok('Variación = primer vs último cierre', $expectedVar !== null ? $eq($u['stats']['variation_pct'], $expectedVar, 0.06) : ($u['stats']['variation_pct'] === null || $u['stats']['variation_pct'] === 0));
ok('Días críticos = celdas rojas del total', $u['stats']['critical_days'] === count(array_filter($u['rows'][0]['cells'], fn($c) => $c['color'] === '#ef4444')));

$metrics = ['units', 'value', 'rotation', 'coverage', 'critical', 'variation'];
$seen = [];
foreach ($metrics as $m)
{
	$h = $hm(['metric' => $m, 'heat_period' => 30]);
	ok("Métrica $m con descripción y escala", $h['metric'] === $m && $h['description'] !== '' && $h['stats']['formatted_min'] !== '');
	$seen[$m] = md5(json_encode(array_map(fn($r) => array_column($r['cells'], 'color'), $h['rows'])));
}
ok('Cada métrica recalcula los colores', count(array_unique($seen)) === count($metrics));
$old = $hm(['heat_period' => 'custom', 'from' => '2026-08-20', 'to' => '2026-09-05']);
$firstLog = substr($db->query('SELECT MIN(row_created_timestamp) FROM stock_log')->fetchColumn(), 0, 10);
ok('Días sin kardex quedan sin datos', $old['rows'][0]['cells'][0]['val'] === null && $old['rows'][0]['cells'][0]['formatted'] === 'Sin datos' && $firstLog > '2026-08-20');

$bid = intval($branches[0]);
$hb = $hm(['branch_id' => $bid, 'metric' => 'value']);
ok('Respeta la sucursal', $eq($lastTotal($hb), $svc->GetExecutiveData(['branch_id' => $bid])['kpis']['inventory_value']['numeric'], 1));
$hd = $hm(['department' => 'dairy']);
ok('Respeta el departamento global', count($hd['rows']) === 2 && $hd['rows'][1]['key'] === 'dairy');

echo "\n2. Top de productos\n";
$every = $top(['limit' => 'all']);
ok('Todos = productos del panel', $every['stats']['shown_count'] === count($every['rows']) && $eq($every['stats']['total_value'], $all['kpis']['inventory_value']['numeric'], 0.5));
foreach ([5, 10, 20, 50] as $n)
{
	$t = $top(['limit' => $n]);
	ok("Límite $n", count($t['rows']) === min($n, $every['stats']['matched_count']) && str_contains($t['title'], "Top $n"));
}
$sorted = function (array $rows, string $f, int $dir) {
	$v = array_column($rows, $f);
	$s = $v;
	$dir > 0 ? sort($s) : rsort($s);
	return $v === $s;
};
foreach ([['value_desc', 'value', -1], ['value_asc', 'value', 1], ['units_desc', 'units', -1], ['units_asc', 'units', 1], ['rotation_desc', 'rotation', -1], ['rotation_asc', 'rotation', 1]] as [$k, $f, $d])
{
	ok("Orden $k", $sorted($top(['sort' => $k, 'limit' => 'all'])['rows'], $f, $d));
}
$ov = $top(['sort' => 'overstock', 'limit' => 'all']);
ok('Sobrestock ordena por excedente y excluye sin excedente', $sorted($ov['rows'], 'overstock_value', -1) && array_filter($ov['rows'], fn($r) => $r['overstock_value'] <= 0) === []);
$nm = $top(['sort' => 'no_movement', 'limit' => 'all']);
$soldIds = array_map('intval', $db->query("SELECT DISTINCT si.product_id FROM sale_items si JOIN sales s ON s.id = si.sale_id WHERE s.status = 'completed' AND date(s.created_timestamp) >= '" . date('Y-m-d', strtotime('-27 days')) . "'")->fetchAll(PDO::FETCH_COLUMN));
ok('Sin movimiento = sin ventas en 28 días', array_intersect(array_column($nm['rows'], 'product_id'), $soldIds) === []);
$titles = array_map(fn($k) => $top(['sort' => $k])['title'], array_keys(StockExecutiveOverviewService::TOP_SORTS));
ok('El título refleja el orden', count(array_unique($titles)) === 8);
$d2 = array_slice(array_values(array_unique(array_column($every['rows'], 'dept_key'))), 0, 2);
$multi = $top(['departments' => implode(',', $d2), 'limit' => 'all']);
$exp2 = array_sum(array_map(fn($r) => in_array($r['dept_key'], $d2, true) ? $r['value'] : 0, $every['rows']));
ok('Multidepartamento filtra y suma', array_diff(array_unique(array_column($multi['rows'], 'dept_key')), $d2) === [] && $eq($multi['stats']['total_value'], $exp2, 0.05));
$combo = $top(['departments' => $d2[0], 'sort' => 'units_asc', 'limit' => 5]);
ok('Combinación orden + departamento + límite', count($combo['rows']) <= 5 && $sorted($combo['rows'], 'units', 1) && array_unique(array_column($combo['rows'], 'dept_key')) === [$d2[0]]);
ok('Participación contra el alcance filtrado', $eq($multi['stats']['participation_pct'], 100, 0.1));
ok('Departamento desconocido se ignora', $top(['departments' => 'xx'])['departments'] === []);
ok('Respeta la sucursal', $eq($top(['branch_id' => $bid, 'limit' => 'all'])['stats']['total_value'], $svc->GetExecutiveData(['branch_id' => $bid])['kpis']['inventory_value']['numeric'], 0.5));
ok('Top 10 = top del panel', array_column($top([])['rows'], 'product_id') === array_column($all['top_products'], 'product_id'));

echo "\n3. Vencimientos\n";
$sqlWin = function (string $a, string $b) use ($db) {
	return $db->query("SELECT COUNT(DISTINCT s.product_id), IFNULL(SUM(s.amount), 0), IFNULL(SUM(s.amount * IFNULL(s.price, IFNULL(plp.price, 0))), 0) FROM stock s JOIN locations l ON l.id = s.location_id LEFT JOIN cache__products_last_purchased plp ON plp.product_id = s.product_id
		WHERE IFNULL(l.is_transit, 0) = 0 AND s.amount > 0 AND s.best_before_date BETWEEN '$a' AND '$b'")->fetch(PDO::FETCH_NUM);
};
foreach (['1', '3', '7', '15', '30', '60'] as $w)
{
	$e = $exp(['window' => $w]);
	$lim = date('Y-m-d', strtotime("+$w days"));
	$r = $sqlWin($today, $lim);
	ok("Ventana $w = registros", intval($r[0]) === $e['stats']['products_count'] && $eq($r[1], $e['stats']['units_count'], 0.01) && $eq($r[2], $e['stats']['total_risk'], 0.05), json_encode([$r, $e['stats']]));
	ok("Ventana $w días restantes correctos", array_filter($e['rows'], fn($x) => $x['days_left'] < 0 || $x['days_left'] > intval($w) || $x['days_left'] !== intval(round((strtotime($x['expiry_date']) - strtotime($today)) / 86400))) === []);
}
$e = $exp(['window' => 'expired']);
$r = $sqlWin('0000-00-00', date('Y-m-d', strtotime('-1 day')));
ok('Vencidos = registros', intval($r[0]) === $e['stats']['products_count'] && $eq($r[2], $e['stats']['total_risk'], 0.05) && array_filter($e['rows'], fn($x) => $x['days_left'] >= 0 || $x['fefo_state'] !== 'Vencido') === []);
// Lote vencido sembrado en la copia, repartido en dos ubicaciones de la misma sucursal: una sola fila, con cantidades sumadas
$seed = $db->query("SELECT s.* FROM stock s JOIN locations l ON l.id = s.location_id JOIN products p ON p.id = s.product_id WHERE IFNULL(l.is_transit, 0) = 0 AND s.amount > 0 AND s.price > 0 AND l.branch_id = 1 AND (IFNULL(p.storage_class, '') = '' OR IFNULL(l.storage_class, '') = '' OR p.storage_class = l.storage_class) LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$prodStorage = $db->query("SELECT IFNULL(storage_class, '') FROM products WHERE id = " . intval($seed['product_id']))->fetchColumn();
$otherLoc = intval($db->query("SELECT id FROM locations WHERE branch_id = 1 AND IFNULL(is_transit, 0) = 0 AND (IFNULL(storage_class, '') = '$prodStorage' OR IFNULL(storage_class, '') = '') AND id <> " . intval($seed['location_id']) . ' LIMIT 1')->fetchColumn());
if (!$otherLoc) {
	$otherLoc = intval($seed['location_id']);
}
$past = date('Y-m-d', strtotime('-3 days'));
$ins = function (int $loc, float $amount) use ($db, $seed, $past) {
	$r = $seed;
	unset($r['id']);
	$r['stock_id'] = 'T-EXPIRED-LOT';
	$r['best_before_date'] = $past;
	$r['location_id'] = $loc;
	$r['amount'] = $amount;
	$db->prepare('INSERT INTO stock (' . implode(',', array_keys($r)) . ') VALUES (' . implode(',', array_fill(0, count($r), '?')) . ')')->execute(array_values($r));
};
$ins(intval($seed['location_id']), 4);
$ins($otherLoc, 6);
$e = $exp(['window' => 'expired']);
$lotRows = array_values(array_filter($e['rows'], fn($x) => $x['stock_id'] === 'T-EXPIRED-LOT'));
ok('Lote en dos ubicaciones = una fila', count($lotRows) === 1, count($lotRows));
ok('Cantidad y valor sumados', ($lotRows[0]['stock'] ?? 0) == 10 && $eq($lotRows[0]['value'] ?? 0, 10 * floatval($seed['price']), 0.05));
ok('Días negativos y estado vencido', ($lotRows[0]['days_left'] ?? 0) === -3 && ($lotRows[0]['fefo_state'] ?? '') === 'Vencido');
ok('Total vencidos = sus filas', $eq($e['stats']['total_risk'], array_sum(array_column($e['rows'], 'value')), 0.01));
ok('Vencidos no entran en ventana futura', array_filter($exp(['window' => '60'])['rows'], fn($x) => $x['stock_id'] === 'T-EXPIRED-LOT') === []);
$db->exec("DELETE FROM stock WHERE stock_id = 'T-EXPIRED-LOT'");
$c = $exp(['window' => 'custom', 'from' => date('Y-m-d', strtotime('+5 days')), 'to' => date('Y-m-d', strtotime('+20 days'))]);
$r = $sqlWin($c['from'], $c['to']);
ok('Rango personalizado = registros', $eq($r[2], $c['stats']['total_risk'], 0.05));
ok('Ventana 30 = valor en riesgo del panel', $eq($exp(['window' => '30'])['stats']['total_risk'], $all['kpis']['risk_value']['numeric'], 1));
$e60 = $exp(['window' => '60']);
$keys = array_map(fn($x) => $x['product_id'] . '|' . $x['lot'] . '|' . $x['branch_id'] . '|' . $x['expiry_date'], $e60['rows']);
ok('Sin duplicados por producto + lote + sucursal + fecha', count($keys) === count(array_unique($keys)));
$dp = array_values(array_unique(array_column($e60['rows'], 'dept_key')));
$f = array_slice($dp, 0, 2);
$e2 = $exp(['window' => '60', 'departments' => implode(',', $f)]);
$want = array_sum(array_map(fn($x) => in_array($x['dept_key'], $f, true) ? $x['value'] : 0, $e60['rows']));
ok('Ventana + varios departamentos a la vez', array_diff(array_unique(array_column($e2['rows'], 'dept_key')), $f) === [] && $eq($e2['stats']['total_risk'], $want, 0.05));
$eb = $exp(['window' => '60', 'branch_id' => $bid]);
ok('Respeta la sucursal', array_unique(array_column($eb['rows'], 'branch_id')) === [$bid] || $eb['rows'] === []);
ok('El panel admite 60 días', $svc->GetExecutiveData(['fefo_days' => 60])['filters']['fefo_days'] === 60);

echo "
$count verificaciones, $fails fallidas
";
exit($fails > 0 ? 1 : 0);
