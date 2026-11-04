<?php
/**
 * HOY ↔ Panel ejecutivo: misma fuente (DailyMetricsService), mismas cifras para el mismo contexto (sucursal, fecha, período),
 * mismas reglas de estado, series que suman los totales, sin valores fijos ni estimados y estabilidad entre lecturas.
 * También verifica que la continuación de la empresa de ejemplo sea determinista y deje el libro de movimientos completo.
 */

use Grocy\Services\DailyMetricsService;
use Grocy\Services\KpiService;
use Grocy\Services\TodayExecutiveSalesService;

require __DIR__ . '/bootstrap.php';

$dm = DailyMetricsService::GetInstance();
$kpi = KpiService::GetInstance();
$today = TodayExecutiveSalesService::GetInstance();
$eq = fn($a, $b) => abs(floatval($a) - floatval($b)) < 0.011;

$panel = function (?int $branch, string $period, string $date) use ($kpi) {
	$k = $kpi->GetExecutiveDashboardData($branch, $period, null, null, $date);
	return [$k, $kpi->BuildExecutivePanelOverview($branch, $period, $k, [], 0)];
};

echo "\n1. Período anclado a la fecha (misma regla en ambas páginas)\n";
ok('Día = la fecha', DailyMetricsService::PeriodRange('day', '2026-10-28') === ['start' => '2026-10-28', 'end' => '2026-10-28']);
ok('Mes = 1.º del mes hasta la fecha', DailyMetricsService::PeriodRange('month', '2026-10-15') === ['start' => '2026-10-01', 'end' => '2026-10-15']);
ok('Semana = lunes hasta la fecha', DailyMetricsService::PeriodRange('week', '2026-10-28') === ['start' => '2026-10-26', 'end' => '2026-10-28']);
ok('Fecha futura se limita a hoy', DailyMetricsService::AnchorDate('2999-01-01') === date('Y-m-d'));
ok('Fecha inválida = hoy', DailyMetricsService::AnchorDate('29/10/2026') === date('Y-m-d'));

echo "\n2. Reglas de estado centralizadas\n";
ok('Ventas ≥ 100 % = en objetivo', DailyMetricsService::SalesLevel(100.0) === 'success');
ok('Ventas 90–99,9 % = atención', DailyMetricsService::SalesLevel(95.0) === 'warning');
ok('Ventas < 90 % = crítico', DailyMetricsService::SalesLevel(89.9) === 'danger');
ok('Sin meta o sin ventas = sin información', DailyMetricsService::SalesLevel(null) === 'neutral');
ok('Merma ≤ objetivo = en objetivo', DailyMetricsService::WasteLevel(2.0, 2.0) === 'success');
ok('Merma ≤ 1,5 × objetivo = atención', DailyMetricsService::WasteLevel(3.0, 2.0) === 'warning');
ok('Merma > 1,5 × objetivo = crítico', DailyMetricsService::WasteLevel(3.1, 2.0) === 'danger');
ok('Estado del Panel = estado de HOY', DailyMetricsService::PanelState('danger') === ['crit', 'Crítico'] && DailyMetricsService::PanelState('neutral') === ['no_data', 'Sin información']);

$lastDay = substr($val("SELECT MAX(created_timestamp) FROM sales WHERE status = 'completed'"), 0, 10);
$branches = array_map('intval', $db->query('SELECT id FROM branches WHERE active = 1 ORDER BY id')->fetchAll(PDO::FETCH_COLUMN));
foreach ([[1, $lastDay], [null, $lastDay], [4, date('Y-m-d', strtotime("$lastDay -1 day"))]] as [$b, $date])
{
	$label = ($b ? "sucursal $b" : 'red') . " · $date";
	echo "\n3. Mismo contexto, mismas cifras ($label, período día)\n";
	$h = $today->GetExecutiveData($b, $date);
	[$k, $p] = $panel($b, 'day', $date);
	$s = $p['strategic_kpis'];
	ok('Ventas netas HOY = Panel', $eq($h['kpis']['sales_net']['numeric'], $s['sales']['value']), $h['kpis']['sales_net']['numeric'] . ' vs ' . $s['sales']['value']);
	ok('Meta del día HOY = Panel', $eq($dm->DailySalesTarget($b, $date), $s['sales']['target']), json_encode([$dm->DailySalesTarget($b, $date), $s['sales']['target']]));
	$hc = $h['kpis']['target_compliance']['raw_numeric'];
	ok('Cumplimiento HOY = Panel (pp vs. 100 %)', $hc === null ? $s['sales']['delta_pct'] === null : $eq($hc - 100, $s['sales']['delta_pct']), json_encode([$hc, $s['sales']['delta_pct']]));
	ok('Margen % HOY = Panel', $eq($h['kpis']['gross_margin']['numeric'], $s['margin']['margin_pct']));
	ok('Utilidad bruta HOY = Panel', $eq($h['kpis']['gross_profit']['numeric'], $s['margin']['value']));
	ok('% merma HOY = Panel', $h['kpis']['waste_ratio']['numeric'] === null ? !$s['waste']['has_data'] : $eq($h['kpis']['waste_ratio']['numeric'], $s['waste']['ratio_pct']));
	ok('Merma $ = Mermas (aprobada, una sola vez)', $eq($s['waste']['value'], $dm->Waste($b, $date, $date)['cost']));
	$ops = array_column($p['operations_status'], null, 'key');
	$hs = array_column($h['process_statuses'], null, 'key');
	foreach (['ventas' => 'sales', 'mermas' => 'waste', 'compras' => 'purchasing', 'inventario' => 'inventory', 'despachos' => 'dispatch', 'personal' => 'workforce', 'calidad' => 'quality'] as $pk => $hk)
	{
		ok("Estado «{$pk}» Panel = HOY", $ops[$pk]['state'] === DailyMetricsService::PanelState($hs[$hk]['status'])[0], $ops[$pk]['state'] . ' vs ' . $hs[$hk]['status']);
	}
	$decs = array_column($p['priority_decisions'], null, 'title');
	ok('Decisión de personal = estado Personal de HOY (sin contradicción)', $decs['Cobertura de personal']['level'] === $hs['workforce']['status'], $decs['Cobertura de personal']['level'] . ' vs ' . $hs['workforce']['status']);
	ok('Decisión de ventas = estado Ventas', $decs['Cumplimiento de la meta de ventas']['level'] === $hs['sales']['status']);
	ok('Decisión de merma = estado Mermas', $decs['Merma frente al objetivo']['level'] === $hs['waste']['status']);
	$evo = $p['evolution_chart'];
	ok('Serie horaria del Panel suma la venta del día (08–21 h)', $eq(array_sum($evo['sales_real']), array_sum(array_slice($dm->SalesByHour($b, $date), 8, 14))));
	$main = $h['main_chart']['real_sales'];
	$lastReal = array_values(array_filter($main, fn($v) => $v !== null));
	ok('Acumulado de HOY (último tramo, redondeado a $1) = suma horaria', $date === date('Y-m-d') || abs(end($lastReal) - array_sum(array_slice($dm->SalesByHour($b, $date), 0, 21))) <= 0.5, json_encode([end($lastReal), array_sum(array_slice($dm->SalesByHour($b, $date), 0, 21))]));
	$donut = $h['donut_chart'];
	ok('Departamentos de HOY suman la venta neta (sin impuestos)', $eq($donut['total_sales_num'], $h['kpis']['sales_net']['numeric']) || abs($donut['total_sales_num'] - $h['kpis']['sales_net']['numeric']) <= max(1, 0.02 * $h['kpis']['sales_net']['numeric']),
		$donut['total_sales_num'] . ' vs ' . $h['kpis']['sales_net']['numeric']);
	if (!$b)
	{
		$rk = $k['branch_ranking'];
		ok('Ranking: suma de sucursales = red', $eq(array_sum(array_column($rk, 'sales')), $s['sales']['value']), array_sum(array_column($rk, 'sales')) . ' vs ' . $s['sales']['value']);
	}
	else
	{
		$row = array_values(array_filter($k['branch_ranking'], fn($r) => $r['branch_id'] === $b))[0];
		ok('Ranking de la sucursal = KPI de la sucursal', $eq($row['sales'], $s['sales']['value']) && $eq($row['sales_target'], $s['sales']['target']));
	}
	[$k2, $p2] = $panel($b, 'day', $date);
	ok('Estable al recargar (mismas cifras)', json_encode($p2['strategic_kpis']) === json_encode($s) && json_encode($p2['evolution_chart']) === json_encode($evo));
}

echo "\n4. Mes hasta la fecha: Panel = suma de los días de HOY\n";
[$k, $p] = $panel(1, 'month', $lastDay);
$from = substr($lastDay, 0, 8) . '01';
ok('Rango del Panel = 1.º del mes … fecha', $p['context']['start'] === $from && $p['context']['end'] === $lastDay, json_encode($p['context']));
ok('Ventas del mes = suma diaria', $eq($p['strategic_kpis']['sales']['value'], array_sum($dm->DailyNetSales(1, $from, $lastDay))));
ok('Meta del mes = suma de metas diarias', $eq($p['strategic_kpis']['sales']['target'], $dm->PeriodSalesTarget(1, $from, $lastDay)));
ok('Barras de evolución = ventas del período', $eq(array_sum($p['evolution_chart']['sales_real']), $p['strategic_kpis']['sales']['value']));
ok('Meta de la evolución = meta del período', $eq(array_sum($p['evolution_chart']['target_line']), $p['strategic_kpis']['sales']['target']));
$prevFrom = date('Y-m-d', strtotime("$from -1 month"));
ok('Período anterior leído (no estimado)', $eq(array_sum($p['evolution_chart']['prev_period']), array_sum(array_slice($dm->DailyNetSales(1, $prevFrom, date('Y-m-t', strtotime($prevFrom))), 0, count($p['evolution_chart']['labels'])))));

echo "\n5. Sin valores fijos\n";
$json = json_encode([$p, $k['branch_ranking']]);
ok('Sin metas fijas antiguas (5216.60 / 25500 / 52000)', strpos($json, '5216.6') === false && strpos($json, '25500') === false && strpos($json, '52000') === false);
$opsTxt = array_column($p['operations_status'], 'metric', 'key');
ok('Procesos sin las métricas fijas anteriores (+2.1% / -6.0% / +1.2% / +0.5%)', $opsTxt['compras'] !== '+2.1%' && $opsTxt['personal'] !== '-6.0%' && $opsTxt['despachos'] !== '+1.2%' && $opsTxt['calidad'] !== '+0.5%', json_encode($opsTxt, JSON_UNESCAPED_UNICODE));
ok('Mini-curvas de procesos sin datos de relleno', count(array_filter($p['operations_status'], fn($o) => in_array($o['key'], ['compras', 'inventario', 'despachos', 'personal', 'calidad'], true) && $o['spark'] !== [])) === 0);
ok('Sin «undefined», «NaN» ni «null» como texto en etiquetas', !preg_match('/"(undefined|NaN|null)"/', $json));
ok('Decisiones con plazo solo si hay desviación', count(array_filter($p['priority_decisions'], fn($d) => in_array($d['level'], ['success', 'neutral'], true) && $d['due_date'] !== null)) === 0);

echo "\n6. Empresa de ejemplo: continuación determinista\n";
ok('Libro de movimientos completo (cada movimiento de stock está en el libro)', intval($val('SELECT COUNT(*) FROM stock_log WHERE id NOT IN (SELECT stock_log_id FROM dairy_movement_ledger)')) === 0);
\Grocy\Services\DailyCompanySeedService::GetInstance()->CatchUp(120);
$r = \Grocy\Services\DailyCompanySeedService::GetInstance()->CatchUp(5);
ok('Segunda ejecución en la misma hora no agrega nada', isset($r['up_to_date']) || isset($r['skipped']), json_encode($r));

echo "
$count verificaciones, $fails fallidas
";
exit($fails > 0 ? 1 : 0);
