<?php

namespace Grocy\Services;

/**
 * HOY · Panel comercial del día. Todas las cifras salen de los registros operativos (ventas, mermas, inventario,
 * turnos, pedidos, compras, calidad y metas) con las definiciones de DailyMetricsService, las mismas del Panel
 * ejecutivo, Ventas, Inventario y Mermas. Cuando no hay registros o meta se informa «Sin información» / «Sin meta».
 */
class TodayExecutiveSalesService extends BaseService
{
	private static ?TodayExecutiveSalesService $instance = null;

	public static function GetInstance(): TodayExecutiveSalesService
	{
		if (self::$instance === null)
		{
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function GetExecutiveData(?int $branchId = null, ?string $date = null, ?string $department = null, ?string $category = null, string $metric = 'sales', ?string $comparativeMetric = null): array
	{
		return $this->BuildExecutiveDataRaw($branchId, $date, $department, $category, $metric, $comparativeMetric);
	}

	private static function Money(?float $v, int $dec = 0): string
	{
		return $v === null ? 'Sin información' : '$' . number_format($v, $dec);
	}

	/** Variación porcentual con flecha y tono; '' cuando no hay base comparable */
	private static function Delta(?float $cur, ?float $prev, bool $higherIsGood = true, bool $points = false): array
	{
		if ($cur === null || $prev === null || (!$points && abs($prev) < 0.005))
		{
			return ['text' => '', 'dir' => 'flat', 'tone' => 'neutral'];
		}
		$d = $points ? round($cur - $prev, 1) : round(($cur - $prev) / abs($prev) * 100, 1);
		$dir = $d > 0 ? 'up' : ($d < 0 ? 'down' : 'flat');
		$arrow = $d > 0 ? '↑' : ($d < 0 ? '↓' : '→');
		$good = $dir === 'flat' ? null : (($dir === 'up') === $higherIsGood);
		return ['text' => $arrow . ' ' . ($d > 0 ? '+' : '') . number_format($d, 1) . ($points ? ' pp' : '%'), 'dir' => $dir, 'tone' => $good === null ? 'neutral' : ($good ? 'good' : 'bad')];
	}

	private function BuildExecutiveDataRaw(?int $branchId, ?string $date, ?string $department, ?string $category, string $metric, ?string $comparativeMetric): array
	{
		$m = DailyMetricsService::GetInstance();
		$today = date('Y-m-d');
		$date = ($date && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) ? $date : $today;
		$isToday = $date === $today;
		// El día en curso se compara contra otros días hasta la misma hora
		$cutoff = $isToday ? date('H:i:s') : null;
		$deptKeys = $m->DepartmentFilter($department, $category);
		$prevDay = date('Y-m-d', strtotime("$date -1 day"));
		$weekAgo = date('Y-m-d', strtotime("$date -7 days"));
		$yearAgo = date('Y-m-d', strtotime("$date -1 year"));

		$cur = $m->Sales($branchId, $date, $date, $deptKeys);
		$cmp = $m->Sales($branchId, $weekAgo, $weekAgo, $deptKeys, $cutoff);
		$yday = $m->Sales($branchId, $prevDay, $prevDay, $deptKeys, $cutoff);
		$lyear = $m->Sales($branchId, $yearAgo, $yearAgo, $deptKeys, $cutoff);
		$cmpFull = $m->Sales($branchId, $weekAgo, $weekAgo, $deptKeys);
		$noData = !$cur['has_data'] && !$cmp['has_data'] && !$cmpFull['has_data'];

		$salesState = $m->SalesDataState($branchId, $date, $date, $cur['has_data']);
		$salesNet = $cur['has_data'] ? $cur['net_sales'] : ($salesState === 'confirmed_zero' ? 0.0 : null);
		$pending = $salesState === 'no_data';
		$cutLabel = $cutoff ? substr($cutoff, 0, 5) : null;
		$refLabel = self::DayName($weekAgo) . ' ' . self::ShortDate($weekAgo);
		$cmpTxt = 'vs. ' . $refLabel . ($cutLabel ? ' a las ' . $cutLabel : ' (día completo)');
		$cutoffText = $isToday ? 'Corte ' . $cutLabel : 'Día cerrado';

		// Meta: la del día completo y la acumulada al corte según el perfil horario del mismo día de la semana pasada
		$target = $deptKeys === null ? $m->DailySalesTarget($branchId, $date) : null;
		$byHour = $m->SalesByHour($branchId, $date, $deptKeys);
		$profile = $m->SalesByHour($branchId, $weekAgo, $deptKeys);
		$profileTotal = array_sum($profile);
		$shareAt = function (float $hour) use ($profile, $profileTotal): float {
			if ($profileTotal <= 0)
			{
				return max(0.0, min(1.0, ($hour - 7) / 14));
			}
			$h = intval(floor($hour));
			$acc = array_sum(array_slice($profile, 0, $h)) + ($profile[$h] ?? 0) * ($hour - $h);
			return max(0.0, min(1.0, $acc / $profileTotal));
		};
		$nowShare = $isToday ? $shareAt(intval(date('G')) + intval(date('i')) / 60) : 1.0;
		$targetToCut = $target ? round($target * $nowShare, 0) : null;
		$pace = ($targetToCut && $salesNet !== null) ? round($salesNet / $targetToCut * 100, 1) : null;
		$dayProgress = ($target && $salesNet !== null) ? round($salesNet / $target * 100, 1) : null;
		$rawCompliance = $dayProgress;

		// Productividad: con fichajes se usa la hora real trabajada; sin ellos, la hora programada ya transcurrida
		$labor = $m->Labor($branchId, $date);
		$elapsedShare = $isToday ? $this->ElapsedLaborShare($branchId, $date) : 1.0;
		$punched = $m->PunchedHours($branchId, $date);
		$laborCmp = $m->Labor($branchId, $weekAgo);
		if ($punched !== null && $punched > 0)
		{
			$sphBasis = 'punched';
			$laborHours = $punched;
			$punchedCmp = $isToday ? null : $m->PunchedHours($branchId, $weekAgo);
			$sphCmp = ($punchedCmp && $cmp['has_data']) ? round($cmp['net_sales'] / $punchedCmp, 2) : null;
		}
		else
		{
			$sphBasis = 'scheduled';
			$laborHours = $labor['has_data'] ? round($labor['hours'] * $elapsedShare, 1) : null;
			$sphCmp = ($laborCmp['hours'] > 0 && $cmp['has_data']) ? round($cmp['net_sales'] / max(0.1, $laborCmp['hours'] * $elapsedShare), 2) : null;
		}
		$sph = ($laborHours && $salesNet !== null) ? round($salesNet / $laborHours, 2) : null;

		$waste = $m->Waste($branchId, $date, $date);
		$wasteCmp = $m->Waste($branchId, $weekAgo, $weekAgo);
		$wastePct = $cur['has_data'] ? $m->WastePct($waste['cost'], $cur) : null;
		$wastePctCmp = $cmpFull['has_data'] ? $m->WastePct($wasteCmp['cost'], $cmpFull) : null;
		$targets = $m->Targets($branchId, $date);
		$wasteTarget = floatval($targets['waste_target_pct']);

		// Proyección de cierre: venta al corte + lo que el mismo día de la semana pasada vendió desde esa hora
		$restRef = max(0, $cmpFull['net_sales'] - $cmp['net_sales']);
		$projected = $salesNet === null ? null : ($isToday ? round($salesNet + $restRef, 0) : $salesNet);
		$projBasis = $salesNet === null ? 'Sin corte de ventas del día: no se proyecta con histórico'
			: (!$isToday ? 'Día cerrado: es la venta real'
			: ($cmpFull['has_data'] ? 'Venta al corte ' . self::Money($salesNet) . ' + lo vendido el ' . $refLabel . ' desde las ' . $cutLabel . ' (' . self::Money($restRef) . ', histórico)'
			: 'Sin día de referencia con ventas: igual a la venta al corte'));

		// Costo de ventas: líneas vendidas sin costo registrado dejan el margen sobrestimado
		$noCost = 0;
		if ($cur['has_data'] && empty($cur['full_day']))
		{
			$st = DatabaseService::GetInstance()->GetDbConnectionRaw()->prepare("SELECT COUNT(*) FROM sale_items si JOIN sales s ON s.id = si.sale_id WHERE s.status = 'completed' AND date(s.created_timestamp) = :d AND IFNULL(si.total_cost, 0) = 0" . ($branchId ? ' AND s.branch_id = ' . intval($branchId) : ''));
			$st->execute([':d' => $date]);
			$noCost = intval($st->fetchColumn());
		}
		$cogsScope = $pending ? 'Costo no disponible: falta el corte de ventas del día'
			: (!$cur['has_data'] ? 'Sin ventas: sin costo que comparar'
			: ('Costo FEFO de lo vendido ' . ($isToday ? 'hasta las ' . $cutLabel : 'en el día') . ($noCost ? ' · ' . $noCost . ' línea(s) sin costo: margen sobrestimado' : '')));

		$d = fn($a, $b, $good = true, $pp = false) => self::Delta($a, $b, $good, $pp);
		$dSales = $d($salesNet, $cmp['has_data'] ? $cmp['net_sales'] : null);
		$dMargin = $d($cur['margin_pct'], $cmp['margin_pct'], true, true);
		$dProfit = $d($cur['has_data'] ? $cur['gross_margin'] : null, $cmp['has_data'] ? $cmp['gross_margin'] : null);
		$dTicket = $d($cur['avg_ticket'], $cmp['avg_ticket']);
		$dTx = $d($cur['has_data'] ? floatval($cur['tickets']) : null, $cmp['has_data'] ? floatval($cmp['tickets']) : null);
		$dSph = $d($sph, $sphCmp);
		$dWaste = $d($wastePct, $wastePctCmp, false, true);
		$pendTxt = 'Pendiente de sincronización';

		$kpis = [
			'sales_net' => ['value' => $pending ? $pendTxt : self::Money($salesNet), 'numeric' => $salesNet, 'delta_pct' => $dSales['text'], 'delta_direction' => $dSales['dir'], 'tone' => $dSales['tone'],
				'comparison_text' => $dSales['text'] !== '' ? $cmpTxt : ($pending ? 'Sin corte de ventas del día' : 'Sin ' . $refLabel . ' con ventas para comparar'), 'state' => $salesState,
				'cutoff_text' => $cutoffText],
			'target_compliance' => [
				'value' => $target === null ? 'Sin meta definida' : ($pace === null ? $pendTxt : number_format($pace, 1) . '%'),
				'numeric' => $pace,
				'raw_numeric' => $rawCompliance,
				'gap_pp' => $pace === null ? '' : ($pace >= 100 ? '↑ ' . number_format($pace - 100, 1) . ' pp sobre la meta al corte' : '↓ ' . number_format($pace - 100, 1) . ' pp bajo la meta al corte'),
				'tone' => $pace === null ? 'neutral' : ($pace >= 100 ? 'good' : ($pace >= 90 ? 'warning' : 'bad')),
				'cutoff_target_text' => $targetToCut === null ? 'Sin meta al corte' : 'Meta al corte' . ($cutLabel ? ' (' . $cutLabel . ')' : '') . ': ' . self::Money($targetToCut),
				'target_text' => $target === null ? 'Sin meta definida' : 'Meta del día: ' . self::Money($target) . ($dayProgress !== null ? ' · lleva ' . number_format($dayProgress, 1) . '%' : ''),
				'diff_monetary' => ($targetToCut && $salesNet !== null) ? (($salesNet - $targetToCut >= 0 ? '+$' : '-$') . number_format(abs($salesNet - $targetToCut), 0)) : ''
			],
			'gross_margin' => ['value' => $cur['margin_pct'] === null ? ($pending ? $pendTxt : 'Sin información') : number_format($cur['margin_pct'], 1) . '%', 'numeric' => $cur['margin_pct'],
				'amount' => self::Money($cur['has_data'] ? $cur['gross_margin'] : null),
				'delta_pp' => $dMargin['text'], 'delta_direction' => $dMargin['dir'], 'tone' => $dMargin['tone'], 'comparison_text' => $dMargin['text'] !== '' ? $cmpTxt : '',
				'cogs_text' => 'Costo de ventas: ' . self::Money($cur['has_data'] ? $cur['cogs'] : null), 'cogs_scope' => $cogsScope, 'missing_cost_lines' => $noCost],
			'gross_profit' => ['value' => self::Money($cur['has_data'] ? $cur['gross_margin'] : null), 'numeric' => $cur['gross_margin'], 'delta_pct' => $dProfit['text'], 'delta_direction' => $dProfit['dir'],
				'tone' => $dProfit['tone'], 'subtext' => 'Venta neta − costo de ventas'],
			'avg_ticket' => ['value' => $pending ? $pendTxt : ($cur['avg_ticket'] === null ? '—' : self::Money($cur['avg_ticket'], 2)), 'numeric' => $cur['avg_ticket'], 'delta_pct' => $dTicket['text'], 'delta_direction' => $dTicket['dir'], 'tone' => $dTicket['tone'],
				'subtext' => 'Venta neta ÷ tickets'],
			'transactions' => ['value' => $pending ? $pendTxt : number_format($cur['tickets'], 0), 'numeric' => $cur['tickets'], 'delta_pct' => $dTx['text'], 'delta_direction' => $dTx['dir'],
				'tone' => $dTx['tone'], 'subtext' => $deptKeys === null ? 'Tickets completados' : 'Tickets con productos del filtro'],
			'units_per_tx' => ['value' => $pending ? $pendTxt : ($cur['units_per_ticket'] === null ? '—' : number_format($cur['units_per_ticket'], 1)), 'numeric' => $cur['units_per_ticket']],
			'labor_sales_per_hour' => ['title' => $sphBasis === 'punched' ? 'Venta por hora fichada' : 'Venta por hora programada', 'basis' => $sphBasis,
				'value' => $sph === null ? ($pending ? $pendTxt : 'Sin información') : '$' . number_format($sph, 2), 'numeric' => $sph, 'delta_pct' => $dSph['text'], 'delta_direction' => $dSph['dir'], 'tone' => $dSph['tone'],
				'subtext' => $laborHours === null ? 'Sin turnos ni fichajes' : number_format($laborHours, 1) . ($sphBasis === 'punched' ? ' h fichadas (asistencia)' : ' h programadas' . ($isToday ? ' transcurridas · sin fichajes' : ' · sin fichajes')),
				'tooltip' => $sphBasis === 'punched' ? 'Ventas netas ÷ horas trabajadas según los fichajes de entrada y salida' : 'Ventas netas ÷ horas de turno programadas. No hay fichajes del día: no es productividad real.'],
			'waste_ratio' => ['value' => $wastePct !== null ? number_format($wastePct, 1) . '%' : ($waste['cost'] > 0 ? self::Money($waste['cost'], 2) . ' confirmada' : 'Sin información'), 'numeric' => $wastePct, 'cost' => round($waste['cost'], 2),
				'delta_pp' => $dWaste['text'], 'delta_direction' => $dWaste['dir'], 'tone' => $dWaste['tone'],
				'subtext' => $wastePct === null ? ($waste['cost'] > 0 ? 'Sin ventas del día: no se calcula el porcentaje' : 'Sin merma confirmada ni ventas del día') : (self::Money($waste['cost'], 2) . ' aprobada · objetivo ≤ ' . number_format($wasteTarget, 1) . '%' . ($waste['pending_events'] ? ' · ' . $waste['pending_events'] . ' pendiente(s)' : ''))]
		];

		// Ventas por hora: acumulado real vs. meta repartida según el perfil horario del mismo día de la semana pasada
		$labels = ['08:00', '10:00', '12:00', '14:00', '16:00', '18:00', '20:00'];
		$nowHour = intval(date('G'));
		$real = [];
		$goal = [];
		foreach ($labels as $l)
		{
			$h = intval(substr($l, 0, 2));
			$real[] = ($isToday && $h > $nowHour + 1) ? null : round(array_sum(array_slice($byHour, 0, $h + 1)), 0);
			$share = $profileTotal > 0 ? array_sum(array_slice($profile, 0, $h + 1)) / $profileTotal : max(0, min(1, ($h - 7 + 1) / 14));
			$goal[] = $target ? round($target * $share, 0) : null;
		}
		$projVar = ($projected !== null && $target) ? self::Delta($projected, $target) : ['text' => '', 'dir' => 'flat', 'tone' => 'neutral'];
		// Proyección: tramo punteado desde el último punto real hasta el cierre
		$proj = array_fill(0, count($labels), null);
		if ($isToday && $projected !== null)
		{
			$lastReal = null;
			foreach ($real as $i => $v)
			{
				if ($v !== null) $lastReal = $i;
			}
			if ($lastReal !== null && $lastReal < count($labels) - 1)
			{
				$proj[$lastReal] = $real[$lastReal];
				$proj[count($labels) - 1] = $projected;
			}
		}
		$mainChart = ['labels' => $labels, 'real_sales' => $real, 'target_sales' => $goal, 'projection_sales' => $proj,
			'legend' => ['real' => 'Real al corte' . ($cutLabel ? ' (' . $cutLabel . ')' : ''), 'target' => 'Meta al corte', 'projection' => 'Proyección de cierre'],
			'projected_closing' => ['amount' => $pending ? $pendTxt : self::Money($projected), 'numeric' => $projected, 'variance_pct' => $projVar['text'], 'variance_direction' => $projVar['dir'], 'tone' => $projVar['tone'],
				'variance_text' => $target ? 'vs. meta del día (' . self::Money($target) . ')' : 'sin meta del día', 'basis_text' => $projBasis]];

		// Ventas por departamento (mismo día y filtros)
		$depts = $m->SalesByDepartment($branchId, $date, $date, $deptKeys);
		$depts = array_values(array_filter($depts, fn($x) => $x['sales'] > 0 || $x['units'] > 0));
		$totS = array_sum(array_column($depts, 'sales'));
		$totP = array_sum(array_column($depts, 'gross_profit'));
		$totU = array_sum(array_column($depts, 'units'));
		foreach ($depts as &$x)
		{
			$x['scaled_sales'] = round($x['sales'], 0);
			$x['scaled_profit'] = round($x['gross_profit'], 0);
			$x['scaled_units'] = round($x['units'], 0);
			$x['share_pct'] = $totS > 0 ? round($x['sales'] / $totS * 100, 1) : 0;
			$x['profit_share_pct'] = $totP > 0 ? round($x['gross_profit'] / $totP * 100, 1) : 0;
			$x['units_share_pct'] = $totU > 0 ? round($x['units'] / $totU * 100, 1) : 0;
		}
		unset($x);

		// Mapa de calor: promedio diario por día de semana y hora, 4 semanas hasta la fecha
		$days = ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'];
		$heatHours = ['08', '09', '10', '11', '12', '13', '14', '15', '16', '17', '18', '19', '20'];
		$heatFrom = date('Y-m-d', strtotime("$date -27 days"));
		$heat = $m->SalesHeatmap($branchId, $heatFrom, $date);
		$max = 0.0;
		foreach ($heat as $row)
		{
			foreach ($row as $c)
			{
				$max = max($max, $c['sales']);
			}
		}
		$grid = [];
		for ($i = 0; $i < 7; $i++)
		{
			$cells = [];
			foreach ($heatHours as $hh)
			{
				$v = $heat[$i + 1][intval($hh)]['sales'] ?? 0.0;
				$avg = round($v / 4, 0);
				$intensity = $max > 0 ? intval(round($v / $max * 100)) : 0;
				$cells[] = ['day' => $days[$i], 'hour' => "$hh:00", 'intensity' => $intensity, 'sales' => $avg, 'formatted_sales' => '$' . number_format($avg, 0), 'color' => self::GetHeatmapColor($intensity)];
			}
			$grid[] = ['day' => $days[$i], 'cells' => $cells];
		}
		// Franja pico y valle (promedio por día en 4 semanas) y concentración de la venta
		$flat = [];
		foreach ($grid as $row)
		{
			foreach ($row['cells'] as $c)
			{
				$flat[] = $c;
			}
		}
		usort($flat, fn($a, $b) => $b['sales'] <=> $a['sales']);
		$totalHeat = array_sum(array_column($flat, 'sales'));
		$nonZero = array_values(array_filter($flat, fn($c) => $c['sales'] > 0));
		$top = array_slice($flat, 0, 18);
		$heatInsights = $totalHeat > 0 ? [
			'peak' => $flat[0]['day'] . ' ' . $flat[0]['hour'], 'peak_sales' => '$' . number_format($flat[0]['sales'], 0) . '/h',
			'valley' => end($nonZero)['day'] . ' ' . end($nonZero)['hour'], 'valley_sales' => '$' . number_format(end($nonZero)['sales'], 0) . '/h',
			'top_share' => round(array_sum(array_column($top, 'sales')) / $totalHeat * 100, 0)
		] : null;

		$heatPeriod = 'Histórico de 4 semanas · ' . self::ShortDate($heatFrom) . ' – ' . self::ShortDate($date);

		$comparative = $this->Comparative($date, $prevDay, $weekAgo, $yearAgo, $cur, $yday, $cmp, $lyear, $comparativeMetric, $metric, $cutLabel, $pending);

		// Indicadores operativos
		$inv = $m->Inventory($branchId);
		$catalog = max(1, $inv['catalog_with_min']);
		$availability = round(($catalog - $inv['out_of_stock']) / $catalog * 100, 1);
		$returns = $m->Returns($branchId, $date, $date);
		$orders = $m->Orders($branchId, date('Y-m-d', strtotime("$date -27 days")), $date);
		$covDetail = $m->CoverageDetail($branchId, $date);
		$coverage = $covDetail['pct'] ?? null;
		$operationalKpis = [
			'on_shelf_availability' => [
				'title' => 'Disponibilidad en bodega',
				'value' => number_format($availability, 1) . '%',
				'numeric' => $availability,
				'delta' => $inv['out_of_stock'] . ' agotado(s)',
				'period' => 'Ahora',
				'tone' => $inv['out_of_stock'] ? 'bad' : 'good',
				'tooltip' => 'Disponibilidad en bodega calculada a partir de existencias físicas disponibles descontando reservas y movimientos comprometidos para abastecer la operación comercial',
				'href' => '/stockoverview'
			],
			'returns' => ['title' => 'Devoluciones', 'value' => '$' . number_format($returns['amount'], 0), 'numeric' => $returns['amount'], 'delta' => $returns['count'] . ' registro(s)', 'period' => $isToday ? 'Hoy hasta las ' . $cutLabel : 'Día', 'tone' => 'neutral',
				'tooltip' => 'Ventas anuladas por devolución total y devoluciones/cambios de clientes registrados en el día', 'href' => '/sales'],
			'staff_coverage' => ['title' => 'Cobertura de personal', 'value' => $coverage === null ? ($labor['has_data'] ? $labor['people'] . ' personas' : 'Sin información') : number_format($coverage, 0) . '%', 'numeric' => $coverage,
				'delta' => $labor['has_data'] ? number_format($labor['hours'], 1) . ' h programadas' : 'Sin turnos', 'period' => 'Turnos de hoy', 'tone' => $coverage === null ? 'neutral' : ($coverage >= 95 ? 'good' : 'bad'),
				'tooltip' => $coverage === null ? 'Sin requerimiento de dotación configurado: se muestran las personas con turno' : 'Horas-persona cubiertas ÷ requeridas en las franjas mínimas (' . $covDetail['covered_h'] . ' de ' . $covDetail['required_h'] . ' h)', 'href' => '/operations/scheduling'],
			'on_time_delivery' => ['title' => 'Pedidos a tiempo (OTIF)', 'value' => $orders['on_time_pct'] === null ? 'Sin información' : number_format($orders['on_time_pct'], 0) . '% de los despachados', 'numeric' => $orders['on_time_pct'],
				'period' => 'Despachados: 28 días · abiertos: ahora', 'secondary' => $orders['dispatched'] . ' despachados en 28 días',
				'delta' => $orders['late_open'] . ' pedido(s) abierto(s) vencido(s)', 'tone' => $orders['late_open'] > 0 ? 'bad' : ($orders['on_time_pct'] === null ? 'neutral' : ($orders['on_time_pct'] >= 95 ? 'good' : 'bad')),
				'tooltip' => 'Pedidos de clientes despachados en o antes de la fecha solicitada, últimos 28 días', 'href' => '/dispatches']
		];

		$cogsPct = ($cur['has_data'] && $cur['net_sales'] > 0) ? round($cur['cogs'] / $cur['net_sales'] * 100, 1) : null;
		$discPct = ($cur['has_data'] && $cur['net_sales'] > 0) ? round($cur['discount'] / ($cur['net_sales'] + $cur['discount']) * 100, 1) : null;
		$dCogs = $d($cur['has_data'] ? $cur['cogs'] : null, $cmp['has_data'] ? $cmp['cogs'] : null, false);
		$dDisc = $d($cur['has_data'] ? $cur['discount'] : null, $cmp['has_data'] ? $cmp['discount'] : null, false);
		$dUpt = $d($cur['units_per_ticket'], $cmp['units_per_ticket']);
		$profitabilityKpis = [
			'cogs' => ['title' => 'Costo de ventas', 'value' => self::Money($cur['has_data'] ? $cur['cogs'] : null), 'numeric' => $cur['cogs'], 'subline' => $cogsPct === null ? 'Sin ventas' : $cogsPct . '% de ventas',
				'delta' => $dCogs['text'], 'tone' => $dCogs['tone'], 'tooltip' => 'Costo FEFO de los lotes vendidos (CMV). Utilidad bruta: ' . self::Money($cur['has_data'] ? $cur['gross_margin'] : null), 'href' => '/sales?tab=rentabilidad' . ($branchId ? '&branch_id=' . $branchId : ''),
				'period' => $isToday ? 'Hoy hasta las ' . $cutLabel : 'Día'],
			'discounts' => ['title' => 'Descuentos', 'value' => self::Money($cur['has_data'] ? $cur['discount'] : null), 'numeric' => $cur['discount'], 'subline' => $discPct === null ? 'Sin ventas' : $discPct . '% de la venta bruta',
				'delta' => $dDisc['text'], 'tone' => $dDisc['tone'], 'tooltip' => $deptKeys === null ? 'Rebajas FEFO y descuentos de ticket registrados en el POS' : 'Los descuentos se registran por ticket: no se reparten por departamento', 'href' => '/sales?tab=rentabilidad' . ($branchId ? '&branch_id=' . $branchId : ''),
				'period' => $isToday ? 'Hoy hasta las ' . $cutLabel : 'Día'],
			'units_per_tx' => ['title' => 'Unidades por transacción', 'value' => $cur['units_per_ticket'] === null ? 'Sin información' : number_format($cur['units_per_ticket'], 1), 'numeric' => $cur['units_per_ticket'],
				'delta' => $dUpt['text'], 'tone' => $dUpt['tone'], 'tooltip' => 'Unidades vendidas ÷ tickets', 'href' => '/sales']
		];

		$sensitive = intval(DatabaseService::GetInstance()->GetDbConnectionRaw()->query("SELECT COUNT(*) FROM sensitive_investigation_cases WHERE stage <> 'cerrado'" . ($branchId ? ' AND branch_id = ' . intval($branchId) : ''))->fetchColumn());
		$adjust = $m->PendingAdjustments($branchId);
		$branchQs = $branchId ? '&branch_id=' . $branchId : '';
		$nearest = DatabaseService::GetInstance()->GetDbConnectionRaw()->query("SELECT MIN(CAST(julianday(s.best_before_date) - julianday(date('now', 'localtime')) AS INTEGER)) FROM stock s JOIN locations l ON l.id = s.location_id
			WHERE s.amount > 0 AND IFNULL(l.is_transit, 0) = 0 AND s.best_before_date >= date('now', 'localtime') AND s.best_before_date <= date('now', 'localtime', '+7 days')" . ($branchId ? ' AND l.branch_id = ' . intval($branchId) : ''))->fetchColumn();
		$nearest = $nearest === null || $nearest === false ? null : intval($nearest);
		$riskInventory = [
			'low_stock' => ['title' => 'Agotados y bajo mínimo', 'value' => number_format($inv['out_of_stock']) . ' agotados', 'count' => $inv['below_min'] + $inv['out_of_stock'],
				'out' => $inv['out_of_stock'], 'below' => $inv['below_min'], 'period' => 'Ahora',
				'delta' => number_format($inv['below_min']) . ' bajo mínimo con stock', 'tone' => ($inv['below_min'] + $inv['out_of_stock']) ? 'bad' : 'good',
				'tooltip' => 'Pares producto-sucursal con mínimo configurado: agotados (sin existencia) y bajo mínimo (con existencia, por debajo del mínimo)',
				'href_out' => '/stockoverview?iop_state=out' . $branchQs . '#inv-ops-positions', 'href' => '/stockoverview?iop_state=below_min' . $branchQs . '#inv-ops-positions'],
			'expiring' => ['title' => 'Próximos a vencer', 'value' => self::Money($inv['expiring_value']) . ' expuesto', 'count' => $inv['expiring_products'], 'period' => 'Próximos 7 días',
				'delta' => $inv['expiring_products'] . ' producto(s)' . ($nearest !== null ? ' · el más próximo vence ' . ($nearest === 0 ? 'hoy' : 'en ' . $nearest . ' día(s)') : ''),
				'tone' => $inv['expiring_products'] ? 'bad' : 'good', 'tooltip' => 'Costo de los lotes que vencen en ≤ 7 días (costo expuesto, no pérdida confirmada)', 'href' => '/waste?fefo_estado=rotacion' . $branchQs . '#tab-fefo'],
			'sensitive_diffs' => ['title' => 'Diferencias de inventario', 'value' => ($sensitive + $adjust) . ' pendientes', 'count' => $sensitive + $adjust, 'period' => 'Abiertas ahora',
				'adjust' => $adjust, 'investigations' => $sensitive, 'delta' => $adjust . ' ajustes por aprobar · ' . $sensitive . ' investigaciones',
				'href_adjust' => '/stockoverview' . ($branchId ? '?branch_id=' . $branchId : '') . '#inv-ops-counts', 'href_investigations' => '/reconciliation?tab=investigations' . $branchQs,
				'tone' => ($sensitive + $adjust) ? 'bad' : 'good', 'tooltip' => 'Ajustes de inventario pendientes de aprobación e investigaciones de productos sensibles abiertas', 'href' => '/reconciliation']
		];

		$branchPriorities = $this->BranchPriorities($date, $branchId);

		$hoursCmp = $laborCmp['has_data'] ? $laborCmp['hours'] : null;
		$reqHours = $covDetail['required_h'] ?? null;
		$deficit = $covDetail !== null ? floatval($covDetail['gap_h']) : null;
		$schedHref = '/operations/scheduling?date=' . $date . ($branchId ? '&branch_id=' . $branchId : '');
		$workforceKpis = [
			'scheduled' => ['title' => 'Horas programadas', 'value' => $labor['has_data'] ? number_format($labor['hours'], 1) . ' h' : 'Sin turnos', 'sub' => $labor['has_data'] ? $labor['shifts'] . ' turno(s) · ' . $labor['people'] . ' persona(s)' : '', 'href' => $schedHref],
			'punched' => ['title' => 'Horas fichadas', 'value' => $punched === null ? 'Sin fichajes' : number_format($punched, 1) . ' h', 'sub' => $labor['absent_people'] ? $labor['absent_people'] . ' ausencia(s) o turno(s) sin fichar' : ($punched === null ? 'La sucursal no registró asistencia hoy' : 'Según entradas y salidas'), 'href' => '/operations/attendance' . ($branchId ? '?branch_id=' . $branchId : '')],
			'deficit' => ['title' => 'Déficit de cobertura', 'value' => $deficit === null ? 'Sin requerimiento' : ($deficit > 0 ? number_format($deficit, 1) . ' h por cubrir' : 'Cubierto'),
				'sub' => $reqHours === null ? 'Configure la cobertura mínima de la sucursal' : $covDetail['covered_h'] . ' de ' . $reqHours . ' h requeridas cubiertas en las franjas mínimas', 'tone' => $deficit !== null && $deficit > 0 ? 'bad' : 'good', 'href' => $schedHref],
			'coverage' => $operationalKpis['staff_coverage'],
			'productivity' => ['title' => 'Venta por hora programada', 'value' => $kpis['labor_sales_per_hour']['value'], 'numeric' => $sph, 'delta' => $dSph['text'], 'tone' => $dSph['tone'],
				'tooltip' => 'Ventas netas ÷ horas de turno programadas' . ($isToday ? ' ya transcurridas' : ''), 'href' => '/operations/scheduling'],
			'overtime' => ['title' => 'Horas programadas', 'value' => $labor['has_data'] ? number_format($labor['hours'], 1) . ' h' : 'Sin información', 'numeric' => $labor['hours'],
				'delta' => $hoursCmp === null ? '' : self::Delta($labor['hours'], $hoursCmp, false)['text'], 'tone' => 'neutral',
				'tooltip' => 'Horas de los turnos del día (sin pausas). Las horas extra no se registran por separado.', 'href' => '/operations/scheduling']
		];

		$dbRaw = DatabaseService::GetInstance()->GetDbConnectionRaw();
		$bOnly = ($branchId !== null && $branchId > 0) ? ' WHERE branch_id = ' . intval($branchId) : '';
		$latestTs = $dbRaw->query("
			SELECT MAX(ts) FROM (
				SELECT MAX(created_timestamp) AS ts FROM sales$bOnly
				UNION ALL
				SELECT MAX(created_timestamp) AS ts FROM waste_records$bOnly
				UNION ALL
				SELECT MAX(created_at) AS ts FROM customer_orders$bOnly
				UNION ALL
				SELECT MAX(row_created_timestamp) AS ts FROM stock_log
			)
		")->fetchColumn();
		$lastUpdatedFormatted = !empty($latestTs) ? date('d/m/Y H:i', strtotime($latestTs)) : 'Sin registros';

		$correctivePlans = ['total' => 0, 'open' => 0, 'in_progress' => 0, 'overdue' => 0, 'cost_avoided_total' => 0.0, 'top' => []];
		try
		{
			$apData = AkcalMetricsService::GetInstance()->GetActionPlansMatrix($branchId);
			$apKpis = $apData['kpis'] ?? [];
			$activePlans = array_values(array_filter($apData['plans'] ?? [], fn($p) => ($p['status'] ?? '') !== 'completed'));
			$correctivePlans = [
				'total' => intval($apKpis['total'] ?? 0),
				'open' => intval($apKpis['open'] ?? 0),
				'in_progress' => intval($apKpis['in_progress'] ?? 0),
				'overdue' => intval($apKpis['overdue'] ?? 0),
				'cost_avoided_total' => round(floatval($apKpis['cost_avoided_total'] ?? 0), 2),
				'top' => array_slice($activePlans, 0, 5)
			];
		}
		catch (\Throwable $e) {}

		$result = [
			'date' => $date,
			'branch_id' => $branchId,
			'no_data' => $noData,
			'sales_state' => $salesState,
			'department' => $department ?: 'todos',
			'category' => $category ?: 'todas',
			'metric' => $metric,
			'kpis' => $kpis,
			'main_chart' => $mainChart,
			'donut_chart' => [
				'total_sales' => self::Money($totS),
				'total_sales_num' => $totS,
				'total_profit' => self::Money($totP),
				'total_profit_num' => $totP,
				'avg_margin' => $totS > 0 ? number_format($totP / $totS * 100, 1) . '%' : 'Sin información',
				'avg_margin_num' => $totS > 0 ? round($totP / $totS * 100, 1) : null,
				'total_units' => number_format($totU, 0),
				'total_units_num' => $totU,
				'departments' => $depts
			],
			'heatmap' => ['hours' => $heatHours, 'days' => $days, 'grid' => $grid, 'insights' => $heatInsights, 'period_text' => $heatPeriod],
			'comparative' => $comparative,
			'priority_decisions' => $this->PriorityDecisions($branchId, $waste),
			'corrective_actions' => $correctivePlans,
			'operational_kpis' => $operationalKpis,
			'profitability_kpis' => $profitabilityKpis,
			'risk_inventory' => $riskInventory,
			'branch_priorities' => $branchPriorities,
			'workforce_kpis' => $workforceKpis,
			'last_updated' => $lastUpdatedFormatted,
			'last_updated_iso' => $latestTs ?: null,
			'checked_at' => date('d/m/Y H:i:s')
		];
		$result['sparks'] = $this->Sparks($branchId, $date, $deptKeys);
		$result['process_statuses'] = $this->GetProcessStatuses(
			$branchId, $date, $department, $category, $kpis, $operationalKpis, $riskInventory, $workforceKpis,
			$wasteTarget, $lastUpdatedFormatted, $target, $salesNet, $waste['cost'], $dSales['dir'], $depts
		);
		return $result;
	}

	/**
	 * Series de 7 días que terminan en la fecha para las mini-curvas de las tarjetas (mismas fórmulas que la tarjeta).
	 * Indicadores que son una foto del momento (disponibilidad, bajo stock, sensibles) no tienen historial: serie vacía.
	 * Próximos a vencer: productos que vencen en cada uno de los próximos 7 días.
	 */
	private function Sparks(?int $branchId, string $date, ?array $deptKeys): array
	{
		$m = DailyMetricsService::GetInstance();
		$out = ['availability' => [], 'low_stock' => [], 'sensitive' => []];
		for ($i = 6; $i >= 0; $i--)
		{
			$d = date('Y-m-d', strtotime("$date -$i days"));
			$s = $m->Sales($branchId, $d, $d, $deptKeys);
			$labor = $m->Labor($branchId, $d);
			$out['returns'][] = $m->Returns($branchId, $d, $d)['amount'];
			$out['coverage'][] = $this->Coverage($branchId, $d, $labor) ?? 0;
			$out['on_time'][] = $m->Orders($branchId, date('Y-m-d', strtotime("$d -27 days")), $d)['on_time_pct'] ?? 0;
			$out['cogs'][] = $s['cogs'];
			$out['discounts'][] = $s['discount'];
			$out['upt'][] = $s['units_per_ticket'] ?? 0;
			$out['productivity'][] = $labor['hours'] > 0 ? round($s['net_sales'] / $labor['hours'], 2) : 0;
			$out['hours'][] = $labor['hours'];
		}
		$b = ($branchId !== null && $branchId > 0) ? ' AND l.branch_id = ' . intval($branchId) : '';
		$today = date('Y-m-d');
		$rows = DatabaseService::GetInstance()->GetDbConnectionRaw()->query("SELECT s.best_before_date AS d, COUNT(DISTINCT s.product_id) AS n FROM stock s JOIN locations l ON l.id = s.location_id
			WHERE s.amount > 0 AND IFNULL(l.is_transit, 0) = 0 AND s.best_before_date BETWEEN '$today' AND date('$today', '+6 days')$b GROUP BY s.best_before_date")->fetchAll(\PDO::FETCH_KEY_PAIR);
		for ($i = 0; $i < 7; $i++)
		{
			$out['expiring'][] = intval($rows[date('Y-m-d', strtotime("$today +$i days"))] ?? 0);
		}
		// Una serie toda en cero no es tendencia (p. ej. sin devoluciones en la semana): se dibuja plana
		return $out;
	}

	/** «6 nov» */
	private static function ShortDate(string $date): string
	{
		return date('j', strtotime($date)) . ' ' . ['', 'ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'][intval(date('n', strtotime($date)))];
	}

	private static function DayName(string $date): string
	{
		return ['', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado', 'domingo'][intval(date('N', strtotime($date)))];
	}

	/** Parte de las horas programadas de hoy que ya transcurrió (para no dividir la venta parcial por la jornada completa) */
	private function ElapsedLaborShare(?int $branchId, string $date): float
	{
		$st = DatabaseService::GetInstance()->GetDbConnectionRaw()->prepare("SELECT start_time, end_time, break_minutes FROM employee_shifts WHERE shift_date = :d AND status <> 'cancelled'" . ($branchId ? ' AND branch_id = ' . intval($branchId) : ''));
		$st->execute([':d' => $date]);
		$now = time();
		$total = 0.0;
		$done = 0.0;
		foreach ($st->fetchAll(\PDO::FETCH_ASSOC) as $s)
		{
			$a = strtotime("$date {$s['start_time']}");
			$e = strtotime("$date {$s['end_time']}");
			if ($e <= $a)
			{
				$e += 86400;
			}
			$brk = max(0, intval($s['break_minutes'] ?? 0) * 60);
			$dur = max(0, ($e - $a) - $brk);
			$total += $dur;
			$done += max(0, min($dur, min($now, $e) - $a));
		}
		return $total > 0 ? max(0.05, $done / $total) : 1.0;
	}

	/** Horas programadas ÷ horas requeridas por coverage_requirements del día (sin tope artificial 100%); null si no hay requerimiento */
	private function Coverage(?int $branchId, string $date, array $labor): ?float
	{
		$dow = intval(date('N', strtotime($date)));
		$st = DatabaseService::GetInstance()->GetDbConnectionRaw()->prepare('SELECT start_time, end_time, min_staff FROM coverage_requirements WHERE weekday IN (0, :w)' . ($branchId ? ' AND branch_id = ' . intval($branchId) : ''));
		$st->execute([':w' => $dow]);
		$req = 0.0;
		foreach ($st->fetchAll(\PDO::FETCH_ASSOC) as $r)
		{
			$a = strtotime("2000-01-01 {$r['start_time']}");
			$e = strtotime("2000-01-01 {$r['end_time']}");
			$req += max(0, ($e - $a) / 3600) * intval($r['min_staff']);
		}
		return $req > 0 ? round(($labor['present_hours'] ?? $labor['hours']) / $req * 100, 0) : null;
	}

	private function Comparative(string $date, string $prevDay, string $weekAgo, string $yearAgo, array $cur, array $yday, array $cmp, array $lyear, ?string $comparativeMetric, string $metric, ?string $cutLabel = null, bool $pending = false): array
	{
		$fmt = function (string $d, bool $year = false) {
			$mon = ['', 'Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'][intval(date('n', strtotime($d)))];
			return date('j', strtotime($d)) . ' ' . $mon . ($year ? ' ' . date('Y', strtotime($d)) : '');
		};
		$rows = [['Ayer', $fmt($prevDay), $yday, '#8194ad', false], ['Hoy', $fmt($date), $cur, '#00C83B', true], ['Semana ant.', $fmt($weekAgo), $cmp, '#6ee7b7', false], ['Año anterior', $fmt($yearAgo, true), $lyear, '#a7f3d0', false]];
		$build = function (string $title, string $unit, callable $val, callable $show) use ($rows, $cutLabel, $pending) {
			$items = [];
			$vals = [];
			$curV = null;
			foreach ($rows as [$label, $dt, $s, $color, $isCur])
			{
				if ($isCur)
				{
					$curV = ($s['has_data'] && !$pending) ? $val($s) : null;
				}
			}
			foreach ($rows as [$label, $dt, $s, $color, $isCur])
			{
				$v = ($s['has_data'] && !($isCur && $pending)) ? $val($s) : null;
				$vals[] = floatval($v ?? 0);
				// Diferencia solo con ambas bases completas: nunca una «caída» contra un día sin información
				$diff = (!$isCur && $v !== null && $curV !== null) ? $curV - $v : null;
				$items[] = ['label' => $label, 'date' => $dt, 'value' => $v, 'formatted' => $v === null ? 'Sin información' : $show($v), 'color' => $color, 'is_current' => $isCur, 'no_data' => $v === null,
					'cut' => !empty($s['full_day']) ? 'día completo' : ($cutLabel ? 'hasta las ' . $cutLabel : 'día completo'),
					'diff_abs' => $diff, 'diff_text' => $diff === null ? '' : ($unit === '%' ? (($diff >= 0 ? '+' : '') . number_format($diff, 1) . ' pp') : (($diff >= 0 ? '+$' : '-$') . number_format(abs($diff), 0))),
					'diff_pct' => ($diff !== null && $unit !== '%' && abs($v) > 0.005) ? round($diff / abs($v) * 100, 1) : null];
			}
			return ['title' => $title, 'unit' => $unit, 'suggested_max' => max(1, round(max($vals) * 1.18, 1)), 'items' => $items];
		};
		$metrics = [
			'sales' => $build('Ventas netas', '$', fn($s) => $s['net_sales'], fn($v) => '$' . number_format($v, 0)),
			'margin' => $build('Margen', '%', fn($s) => $s['margin_pct'], fn($v) => number_format($v, 1) . '%'),
			'profit' => $build('Utilidad bruta', '$', fn($s) => $s['gross_margin'], fn($v) => '$' . number_format($v, 0))
		];
		$items = array_map(fn($i) => ['label' => $i['label'], 'date' => $i['date'], 'sales' => $i['value'] ?? 0, 'formatted_sales' => $i['formatted'], 'color' => $i['color'], 'is_current' => $i['is_current']], $metrics['sales']['items']);
		$active = in_array($comparativeMetric, ['sales', 'margin', 'profit'], true) ? $comparativeMetric : (in_array($metric, ['margin', 'profit'], true) ? $metric : 'sales');
		return ['metric' => $active, 'metrics' => $metrics, 'items' => $items, 'note' => $cutLabel ? 'Todas las fechas cortadas a las ' . $cutLabel : 'Días completos'];
	}

	/** Decisiones que requieren atención, cada una con su registro de origen */
	private function PriorityDecisions(?int $branchId, array $wasteToday): array
	{
		$db = DatabaseService::GetInstance()->GetDbConnectionRaw();
		$b = $branchId ? ' AND branch_id = ' . intval($branchId) : '';
		$qs = $branchId ? '?branch_id=' . $branchId : '';
		$out = [];
		$p = $db->query("SELECT COUNT(*), IFNULL(SUM(total_cost), 0) FROM waste_records WHERE status = 'pending_approval'$b")->fetch(\PDO::FETCH_NUM);
		if (intval($p[0]) > 0)
		{
			$cost = round(floatval($p[1]), 2);
			$out[] = ['id' => 'dec_waste_approvals', 'severity' => 'warning', 'icon' => 'fa-scale-unbalanced', 'title' => 'Mermas por aprobar', 'description' => intval($p[0]) . ' registro(s) de merma esperan aprobación; su stock aún no se descontó.',
				'impact_text' => 'Costo pendiente: $' . number_format($cost, 2), 'impact_class' => 'text-warning', 'economic_impact' => $cost,
				'owner' => 'Jefe de Mermas / Operaciones', 'due_date' => 'Hoy', 'href' => '/today' . $qs . ($qs ? '&' : '?') . 'tab=operations&qcat=waste#cc-queue', 'module_label' => 'Mermas', 'count' => intval($p[0])];
		}
		$late = DailyMetricsService::GetInstance()->LateReceipts($branchId);
		if ($late > 0)
		{
			$lateVal = floatval($db->query("SELECT IFNULL(SUM(total_estimated_cost), 0) FROM procurement_orders WHERE status IN ('sent', 'confirmed', 'partially_received') AND COALESCE(confirmed_delivery_date, expected_delivery_date) < date('now', 'localtime')$b")->fetchColumn());
			$out[] = ['id' => 'dec_late_receipts', 'severity' => 'warning', 'icon' => 'fa-truck-ramp-box', 'title' => 'Recepciones atrasadas', 'description' => "$late orden(es) de compra enviadas superaron su fecha comprometida sin recibirse.",
				'impact_text' => $lateVal > 0 ? 'OC comprometidas: $' . number_format($lateVal, 0) : 'Afecta disponibilidad del día', 'impact_class' => 'text-warning', 'economic_impact' => round($lateVal, 2),
				'owner' => 'Jefe de Compras', 'due_date' => 'Hoy', 'href' => '/today' . $qs . ($qs ? '&' : '?') . 'tab=operations&qcat=purchasing#cc-queue', 'module_label' => 'Compras', 'count' => $late];
		}
		$a = $db->query("SELECT COUNT(*) FROM dairy_alerts WHERE status IN ('open', 'in_progress') AND priority IN ('P1', 'P2')$b")->fetchColumn();
		if (intval($a) > 0)
		{
			$out[] = ['id' => 'dec_alerts', 'severity' => 'danger', 'icon' => 'fa-bell', 'title' => 'Alertas de prioridad alta abiertas', 'description' => intval($a) . ' alerta(s) P1/P2 sin cerrar. Cada una enlaza al producto o registro que la originó.',
				'impact_text' => 'Requiere responsable y cierre', 'impact_class' => 'text-danger', 'economic_impact' => null,
				'owner' => 'Supervisor / Centro de Alertas', 'due_date' => 'Inmediato', 'href' => '/today' . $qs . ($qs ? '&' : '?') . 'tab=operations&qcat=alerts#cc-queue', 'module_label' => 'Centro de alertas', 'count' => intval($a)];
		}
		$wt = intval($db->query("SELECT COUNT(*) FROM workforce_store_tasks WHERE status IN ('pending', 'in_progress', 'blocked') AND due_time IS NOT NULL AND datetime(due_time) < datetime('now', 'localtime')$b")->fetchColumn());
		$gt = $branchId ? 0 : intval($db->query("SELECT COUNT(*) FROM tasks WHERE done = 0 AND due_date IS NOT NULL AND date(due_date) < date('now', 'localtime')")->fetchColumn());
		$tTotal = $wt + $gt;
		if ($tTotal > 0)
		{
			$out[] = ['id' => 'dec_tasks_overdue', 'severity' => 'warning', 'icon' => 'fa-list-check', 'title' => 'Tareas y acciones correctivas vencidas', 'description' => $tTotal . ' tarea(s) operativa(s) con plazo vencido' . ($branchId ? ' en esta sucursal.' : ' en la red.'),
				'impact_text' => 'Prioridad · requiere ejecución', 'impact_class' => 'text-warning', 'economic_impact' => null,
				'owner' => 'Jefe de Turno / Operaciones', 'due_date' => 'Hoy', 'href' => ($wt > 0 ? '/workforce' . $qs : '/tasks'), 'module_label' => ($wt > 0 ? 'Personal' : 'Tareas'), 'count' => $tTotal];
		}
		$o = DailyMetricsService::GetInstance()->Orders($branchId, date('Y-m-d'), date('Y-m-d'));
		if ($o['open'] > 0)
		{
			$out[] = ['id' => 'dec_orders', 'severity' => $o['late_open'] ? 'danger' : 'info', 'icon' => 'fa-box', 'title' => 'Pedidos de clientes en curso', 'description' => $o['open'] . ' pedido(s) confirmados con stock reservado' . ($o['late_open'] ? ', ' . $o['late_open'] . ' con fecha vencida' : '') . '.',
				'impact_text' => 'Stock reservado: no disponible para venta', 'impact_class' => $o['late_open'] ? 'text-danger' : 'text-info', 'economic_impact' => null,
				'owner' => 'Jefe de Despachos', 'due_date' => $o['late_open'] ? 'Vencido' : 'En curso', 'href' => '/today' . $qs . ($qs ? '&' : '?') . 'tab=operations&qcat=operations#cc-queue', 'module_label' => 'Despachos', 'count' => $o['open']];
		}
		return $out;
	}

	/**
	 * Una prioridad por sucursal, ordenada por impacto en dólares; cada fila dice qué producto o departamento la explica
	 * y enlaza con la sucursal y la excepción. Agotados: venta en riesgo por día (venta diaria promedio de 28 días de lo agotado).
	 */
	private function BranchPriorities(string $date, ?int $only): array
	{
		$m = DailyMetricsService::GetInstance();
		$db = DatabaseService::GetInstance()->GetDbConnectionRaw();
		$from28 = date('Y-m-d', strtotime("$date -27 days"));
		$from7 = date('Y-m-d', strtotime("$date -6 days"));
		$lim7 = date('Y-m-d', strtotime('+7 days'));
		$deptOf = [];
		foreach (DailyMetricsService::Departments() as $dk => $dd)
		{
			foreach ($dd[2] ?? [] as $dv)
			{
				$deptOf[$dv] = DailyMetricsService::DepartmentLabel($dk);
			}
		}
		$label = fn($r) => trim(($r['sku'] ? $r['sku'] . ' · ' : '') . $r['name']) . ($r['department'] ? ' (' . ($deptOf[$r['department']] ?? $r['department']) . ')' : '');
		$out = [];
		foreach (BranchService::GetInstance()->GetBranches() as $br)
		{
			$id = intval($br->id);
			if ($only && $id !== $only)
			{
				continue;
			}
			$qs = '?branch_id=' . $id;
			$inv = $m->Inventory($id);
			$cands = [];
			if ($inv['out_of_stock'] > 0)
			{
				$st = $db->prepare("SELECT p.name, p.sku, p.department, IFNULL(sv.v, 0) / 28.0 AS daily FROM products p
					LEFT JOIN (SELECT s.product_id, SUM(s.amount) AS q FROM stock s JOIN locations l ON l.id = s.location_id WHERE l.branch_id = :b AND IFNULL(l.is_transit, 0) = 0 GROUP BY s.product_id) st ON st.product_id = p.id
					LEFT JOIN (SELECT si.product_id, SUM(si.subtotal) AS v FROM sale_items si JOIN sales sa ON sa.id = si.sale_id WHERE sa.branch_id = :b AND sa.status = 'completed' AND sa.created_timestamp >= :f GROUP BY si.product_id) sv ON sv.product_id = p.id
					WHERE p.active = 1 AND p.min_stock_amount > 0 AND IFNULL(p.is_serialized, 0) = 0 AND IFNULL(st.q, 0) <= 0 ORDER BY daily DESC");
				$st->execute([':b' => $id, ':f' => $from28]);
				$rows = $st->fetchAll(\PDO::FETCH_ASSOC);
				$risk = round(array_sum(array_column($rows, 'daily')), 0);
				$top = $rows[0] ?? null;
				$cands[] = [$risk, 'fa-box-open', '#ef4444', 'Agotados', $inv['out_of_stock'] . ' producto(s) sin stock' . ($top ? ' · principal: ' . $label($top) : ''),
					'Venta en riesgo ~' . self::Money($risk) . '/día', '/stockoverview' . $qs . '&iop_state=out#inv-ops-positions'];
			}
			$w = $m->Waste($id, $from7, $date);
			$sw = $m->Sales($id, $from7, $date);
			$wPct = $sw['has_data'] ? $m->WastePct($w['cost'], $sw) : null;
			$wTarget = floatval($m->Targets($id, $date)['waste_target_pct'] ?? 2.0);
			if ($wPct !== null && $wPct > $wTarget)
			{
				$st = $db->prepare("SELECT p.name, p.sku, p.department, SUM(w.total_cost) AS c FROM waste_records w JOIN products p ON p.id = w.product_id
					WHERE w.branch_id = :b AND w.status = 'approved' AND date(w.created_timestamp) BETWEEN :f AND :t GROUP BY w.product_id ORDER BY c DESC LIMIT 1");
				$st->execute([':b' => $id, ':f' => $from7, ':t' => $date]);
				$top = $st->fetch(\PDO::FETCH_ASSOC) ?: null;
				$cands[] = [round($w['cost'], 0), 'fa-triangle-exclamation', '#ef4444', 'Merma', 'Merma ' . number_format($wPct, 1) . '% en 7 días (obj ≤ ' . number_format($wTarget, 1) . '%)' . ($top ? ' · mayor: ' . $label($top) : ''),
					self::Money($w['cost']) . ' en 7 días', '/waste' . $qs];
			}
			if ($inv['expiring_products'] > 0)
			{
				$st = $db->prepare("SELECT p.name, p.sku, p.department, SUM(s.amount * IFNULL(s.price, 0)) AS v FROM stock s JOIN locations l ON l.id = s.location_id JOIN products p ON p.id = s.product_id
					WHERE l.branch_id = :b AND IFNULL(l.is_transit, 0) = 0 AND s.amount > 0 AND s.best_before_date >= date('now', 'localtime') AND s.best_before_date <= :lim GROUP BY s.product_id ORDER BY v DESC LIMIT 1");
				$st->execute([':b' => $id, ':lim' => $lim7]);
				$top = $st->fetch(\PDO::FETCH_ASSOC) ?: null;
				$cands[] = [round($inv['expiring_value'], 0), 'fa-calendar-days', '#f59e0b', 'Vencimientos', $inv['expiring_products'] . ' producto(s) vencen en ≤ 7 días' . ($top ? ' · mayor costo: ' . $label($top) : ''),
					self::Money($inv['expiring_value']) . ' expuesto', '/waste' . $qs . '&fefo_estado=rotacion#tab-fefo'];
			}
			if (!$cands && $inv['below_min'] > 0)
			{
				$cands[] = [0, 'fa-arrow-down-wide-short', '#f59e0b', 'Bajo mínimo', $inv['below_min'] . ' producto(s) bajo el mínimo', 'Sin impacto inmediato', '/stockoverview' . $qs . '&iop_state=below_min#inv-ops-positions'];
			}
			if (!$cands)
			{
				continue;
			}
			usort($cands, fn($a, $b) => $b[0] <=> $a[0]);
			$c = $cands[0];
			$prio = $c[0] >= 1000 ? ['Alta', 'is-alta'] : ($c[0] >= 200 ? ['Media', 'is-media'] : ['Baja', 'is-baja']);
			$out[] = ['branch' => $br->name . (!empty($br->code) && !str_contains($br->name, $br->code) ? ' · ' . $br->code : ''), 'branch_code' => $br->code ?? '', 'branch_id' => $id,
				'status_icon' => $c[1], 'status_icon_color' => $c[2], 'status_text' => $c[3], 'priority' => $prio[0], 'priority_class' => $prio[1],
				'detail' => $c[4], 'impact' => $c[5], 'impact_num' => $c[0], 'href' => $c[6], '_rank' => $c[0]];
		}
		usort($out, fn($a, $b) => $b['_rank'] <=> $a['_rank']);
		return array_slice($out, 0, $only ? 1 : 5);
	}

	private function GetProcessStatuses(?int $branchId, ?string $date, ?string $department, ?string $category, array $kpis, array $operationalKpis, array $riskInventory, array $workforceKpis, float $wasteTarget, string $lastUpdated = '', ?float $salesTarget = null, ?float $salesNet = null, float $wasteCost = 0.0, string $salesTrendDir = 'flat', array $depts = []): array
	{
		$m = DailyMetricsService::GetInstance();
		$db = DatabaseService::GetInstance()->GetDbConnectionRaw();
		$bAnd = $branchId ? ' AND branch_id = ' . intval($branchId) : '';
		$scopeDept = ($department && $department !== 'todos' && $department !== 'all') || ($category && $category !== 'todas' && $category !== 'all');
		$scopeText = $scopeDept ? 'Alcance: toda la sucursal' : null;
		$branchQs = $branchId ? '?branch_id=' . $branchId : '';
		$st = function (string $level) {
			return ['success' => ['En objetivo', 'fa-circle-check'], 'warning' => ['Atención', 'fa-circle-exclamation'], 'danger' => ['Crítico', 'fa-circle-xmark'], 'neutral' => ['Sin información', 'fa-circle-question']][$level];
		};
		$rows = [];

		$day = $date ?: date('Y-m-d');
		$bS = fn(string $col) => $branchId ? " AND $col = " . intval($branchId) : '';
		$bName = [];
		foreach (BranchService::GetInstance()->GetBranches() as $br)
		{
			$bName[intval($br->id)] = $br->name;
		}
		$where = fn($id) => $branchId ? '' : ' · ' . ($bName[intval($id)] ?? 'Sucursal ' . $id);
		$fmtDt = fn($d) => $d ? (date('Y-m-d', strtotime($d)) === date('Y-m-d') ? 'hoy' : self::ShortDate($d)) . (strlen($d) > 10 && substr($d, 11, 5) !== '00:00' ? ' ' . substr($d, 11, 5) : '') : 'sin fecha';

		// Compras: recepciones atrasadas con proveedor, hora prometida y valor comprometido; abre la OC
		$poRows = $db->query("SELECT po.id, po.order_code, po.branch_id, po.total_estimated_cost AS v, COALESCE(po.confirmed_delivery_date, po.expected_delivery_date) AS due, IFNULL(sp.name, 'Proveedor ' || po.supplier_id) AS supplier
			FROM procurement_orders po LEFT JOIN shopping_locations sp ON sp.id = po.supplier_id
			WHERE po.status IN ('sent', 'confirmed', 'partially_received') AND COALESCE(po.confirmed_delivery_date, po.expected_delivery_date) < date('now', 'localtime')" . $bS('po.branch_id') . ' ORDER BY po.total_estimated_cost DESC')->fetchAll(\PDO::FETCH_ASSOC);
		$late = count($poRows);
		$latePoCost = round(array_sum(array_column($poRows, 'v')), 2);
		$poDet = array_map(fn($r) => ['text' => $r['order_code'] . ' · ' . $r['supplier'] . ' · prometida ' . $fmtDt($r['due']) . ' · ' . self::Money(floatval($r['v'])) . $where($r['branch_id']), 'href' => '/document/order/' . $r['id'], 'tone' => 'danger'], array_slice($poRows, 0, 3));
		$rows[] = ['purchasing', 'Compras', 'fa-cart-shopping', '#0284c7', DailyMetricsService::CountLevel($late, 'late_receipts_crit'),
			$late > 0 ? "$late recepción(es) atrasada(s) · " . self::Money($latePoCost) . ' comprometido' : 'Sin recepciones atrasadas', $scopeText, $late > 0 ? 'Ver todas las OC' : 'Ver compras', '/mrp' . $branchQs,
			$late > 0 ? "$late OC atrasadas" : '0 atrasos', $late > 0 ? -$latePoCost : 0.0, $late > 0 ? self::Money($latePoCost) . ' comprometido' : '$0', $late > 0 ? 'down' : 'flat', $late > 0 ? 'Atraso activo' : 'Estable', 'Jefe de Compras', $lastUpdated, $poDet];

		// Inventario: diferencias por validar con valor y ubicación; abre el ajuste (conteo) que la explica
		$adjRows = $db->query("SELECT a.id, a.adjustment_number, a.branch_id, a.product_id, a.difference_amount, ABS(IFNULL(a.total_cost_impact, 0)) AS v, IFNULL(p.name, '#' || a.product_id) AS product, IFNULL(l.name, 'sin ubicación') AS location
			FROM inventory_adjustments a LEFT JOIN products p ON p.id = a.product_id LEFT JOIN locations l ON l.id = a.location_id
			WHERE a.status = 'pending_approval'" . $bS('a.branch_id') . ' ORDER BY v DESC')->fetchAll(\PDO::FETCH_ASSOC);
		$adjN = count($adjRows);
		$adjV = round(array_sum(array_column($adjRows, 'v')), 2);
		$invN = intval($riskInventory['sensitive_diffs']['investigations'] ?? 0);
		$inv = $m->Inventory($branchId);
		$adjDet = array_map(fn($r) => ['text' => ($r['adjustment_number'] ?: 'Ajuste ' . $r['id']) . ' · ' . $r['product'] . ' · ' . ($r['difference_amount'] > 0 ? '+' : '') . floatval($r['difference_amount']) . ' u. · ' . self::Money(floatval($r['v'])) . ' · ' . $r['location'] . $where($r['branch_id']), 'href' => '/kardex?product_id=' . $r['product_id'] . '&branch_id=' . $r['branch_id'], 'tone' => 'warning'], array_slice($adjRows, 0, 3));
		if ($invN > 0)
		{
			$adjDet[] = ['text' => "$invN investigación(es) de productos sensibles abiertas", 'href' => '/reconciliation?tab=investigations' . ($branchId ? '&branch_id=' . $branchId : ''), 'tone' => 'warning'];
		}
		$rows[] = ['inventory', 'Inventario', 'fa-boxes-stacked', '#059669', DailyMetricsService::CountLevel($adjN + $invN, 'inventory_diffs_crit'),
			$adjN + $invN > 0 ? "$adjN diferencia(s) por validar (" . self::Money($adjV) . ')' . ($invN ? " · $invN investigación(es)" : '') : 'Sin diferencias por validar', $scopeText, 'Ver conteos y ajustes', '/stockoverview' . $branchQs . '#inv-ops-counts',
			$adjN > 0 ? "$adjN por validar" : '0 pendientes', $adjN > 0 ? -$adjV : 0.0, $adjN > 0 ? self::Money($adjV) . ' por validar' : '$0', $adjN > 0 ? 'down' : 'flat', $inv['out_of_stock'] . ' agotados', 'Jefe de Inventario', $lastUpdated, $adjDet];

		// Ventas: desviación contra la meta al corte y departamento que más aporta; POS desactualizado = estado del proceso
		$salesState = $kpis['sales_net']['state'] ?? 'data';
		$c = $kpis['target_compliance']['numeric'];
		$lastSale = $db->query("SELECT MAX(created_timestamp) FROM sales WHERE status = 'completed'" . $bS('branch_id'))->fetchColumn();
		$topDept = null;
		foreach ($depts as $dp)
		{
			if ($topDept === null || $dp['sales'] > $topDept['sales']) $topDept = $dp;
		}
		$salesDet = [];
		if ($salesState === 'no_data')
		{
			$salesLevel = 'warning';
			$salesSit = 'POS desactualizado: último ticket sincronizado ' . ($lastSale ? self::ShortDate($lastSale) . ' ' . substr($lastSale, 11, 5) : 'nunca') . '. Sin corte de ventas del día.';
			$salesDet[] = ['text' => 'Revisar la sincronización del POS', 'href' => '/pos', 'tone' => 'warning'];
		}
		else
		{
			$salesLevel = DailyMetricsService::SalesLevel($c);
			$salesSit = $c === null ? 'Sin meta registrada para evaluar el objetivo' : 'Avance ' . number_format($c, 1) . ' % de la meta al corte (' . $kpis['target_compliance']['diff_monetary'] . ')';
			if ($topDept && $topDept['sales'] > 0)
			{
				$salesDet[] = ['text' => 'Mayor aporte: ' . $topDept['name'] . ' · ' . self::Money($topDept['sales']) . ' (' . number_format($topDept['share_pct'] ?? 0, 1) . '%)', 'href' => '/today?department=' . $topDept['id'] . ($branchId ? '&branch_id=' . $branchId : ''), 'tone' => 'neutral'];
			}
		}
		$salesGap = $kpis['target_compliance']['diff_monetary'] ?? '';
		$rows[] = ['sales', 'Ventas', 'fa-chart-line', '#00C83B', $salesLevel, $salesSit, null, 'Ver ventas', '/sales' . $branchQs,
			$c === null ? ($salesState === 'no_data' ? 'Pendiente de sincronización' : 'Sin meta') : (($c - 100 >= 0 ? '+' : '') . number_format($c - 100, 1) . ' pp vs. meta al corte'),
			null, $salesGap !== '' ? $salesGap . ' vs. meta al corte' : 'Sin datos', $salesTrendDir, $kpis['sales_net']['delta_pct'] ?: 'Estable', 'Gerente Comercial', $lastUpdated, $salesDet];

		// Despachos: vencidos, próxima salida y OTIF con período y denominador
		$o = $m->Orders($branchId, date('Y-m-d', strtotime("$day -27 days")), $day);
		$next = $db->query("SELECT id, order_code, branch_id, requested_date, departure_time FROM customer_orders WHERE status IN ('confirmed', 'picking', 'picked', 'packed') AND IFNULL(requested_date, '9999') >= date('now', 'localtime')" . $bS('branch_id') . ' ORDER BY requested_date, departure_time LIMIT 1')->fetch(\PDO::FETCH_ASSOC);
		$lateRows = $db->query("SELECT id, order_code, branch_id, requested_date FROM customer_orders WHERE status IN ('confirmed', 'picking', 'picked', 'packed') AND requested_date IS NOT NULL AND requested_date < date('now', 'localtime')" . $bS('branch_id') . ' ORDER BY requested_date LIMIT 3')->fetchAll(\PDO::FETCH_ASSOC);
		$otifTxt = $o['on_time_pct'] !== null ? 'OTIF ' . number_format($o['on_time_pct'], 0) . '% (' . $o['on_time'] . ' de ' . $o['dispatched'] . ' despachados, 28 días)' : 'OTIF sin despachos en 28 días';
		$dDet = array_map(fn($r) => ['text' => $r['order_code'] . ' · vencido desde ' . self::ShortDate($r['requested_date']) . $where($r['branch_id']), 'href' => '/dispatches' . ($branchQs ?: '?') . ($branchQs ? '&' : '') . 'id=' . $r['id'], 'tone' => 'danger'], $lateRows);
		$dDet[] = ['text' => $next ? 'Próxima salida: ' . $next['order_code'] . ' · ' . self::ShortDate($next['requested_date']) . ($next['departure_time'] ? ' ' . substr($next['departure_time'], 0, 5) : '') . $where($next['branch_id']) : 'Sin salidas programadas', 'href' => $next ? '/dispatches' . ($branchQs ?: '?') . ($branchQs ? '&' : '') . 'id=' . $next['id'] : null, 'tone' => 'neutral'];
		$rows[] = ['dispatch', 'Despachos', 'fa-truck-fast', '#6366f1', $o['late_open'] > 0 ? DailyMetricsService::CountLevel($o['late_open'], 'late_orders_crit') : ($o['dispatched'] + $o['open'] > 0 ? 'success' : 'neutral'),
			($o['late_open'] > 0 ? $o['late_open'] . ' pedido(s) abierto(s) vencido(s) · ' : $o['open'] . ' pedido(s) en curso · ') . $otifTxt, $scopeText, 'Ver despachos', '/dispatches' . $branchQs,
			$otifTxt, null, $o['late_open'] > 0 ? $o['late_open'] . ' vencidos' : 'En plazo', $o['late_open'] > 0 ? 'down' : ($o['dispatched'] + $o['open'] > 0 ? 'up' : 'no_data'), $o['open'] . ' abiertos ahora', 'Jefe de Despachos', $lastUpdated, $dDet];

		// Personal: cobertura por hora (y departamento cuando coinciden) con horas programadas y requeridas del mismo alcance
		$labor = $m->Labor($branchId, $day);
		$cd = $m->CoverageDetail($branchId, $day);
		$absentPeople = intval($labor['absent_people']);
		$wDet = [];
		if ($cd === null)
		{
			$wLevel = $labor['has_data'] ? 'neutral' : 'neutral';
			$wSit = $labor['has_data'] ? number_format($labor['hours'], 1) . ' h programadas · sin cobertura mínima configurada' : 'Sin turnos programados para la fecha';
			$wDev = 'Sin req. cobertura';
		}
		else
		{
			$wLevel = DailyMetricsService::CoverageLevel($cd['pct']);
			$wSit = 'Cobertura ' . $cd['pct'] . ' %: ' . $cd['covered_h'] . ' h cubiertas de ' . $cd['required_h'] . ' h requeridas en las franjas mínimas · programadas ' . number_format($labor['hours'], 1) . ' h en total (' . $cd['in_window_h'] . ' h dentro de las franjas)';
			$wDev = $cd['gap_h'] > 0 ? $cd['gap_h'] . ' h sin cubrir' : 'Mínimo cubierto';
			foreach (array_slice($cd['gaps'], 0, 3) as $g)
			{
				$wDet[] = ['text' => $g['hour'] . ($g['dept'] ? ' · ' . $g['dept'] : '') . $where($g['branch_id']) . ': ' . $g['have'] . ' de ' . $g['req'] . ' persona(s)', 'href' => '/operations/scheduling?date=' . $day . '&branch_id=' . $g['branch_id'], 'tone' => 'danger'];
			}
			if (!$cd['by_department'])
			{
				$wDet[] = ['text' => 'Comparado por hora: los departamentos de los turnos no coinciden con los de la cobertura (' . implode(', ', $cd['departments']) . ')', 'href' => null, 'tone' => 'neutral'];
			}
		}
		if ($absentPeople)
		{
			$wDet[] = ['text' => $absentPeople . ' ausencia(s) o turno(s) sin fichar', 'href' => '/operations/attendance' . $branchQs, 'tone' => 'warning'];
		}
		$rows[] = ['workforce', 'Personal', 'fa-user-clock', '#8b5cf6', $wLevel, $wSit, $scopeText, 'Ver turnos', '/operations/scheduling?date=' . $day . ($branchId ? '&branch_id=' . $branchId : ''),
			$wDev, null, $absentPeople > 0 ? "$absentPeople ausencia(s)" : 'Completa', $cd === null ? 'no_data' : ($cd['pct'] >= 95 ? 'up' : 'down'), $cd !== null ? $cd['pct'] . '%' : 'Sin req.', 'Jefe de Personal', $lastUpdated, $wDet];

		// Calidad: por vencer, cuarentena y frío, cada uno con su acción
		$q = $m->QualityIssues($branchId, $day);
		$qrN = intval($db->query("SELECT COUNT(*) FROM quarantine_items WHERE status = 'in_quarantine'" . $bS('branch_id'))->fetchColumn());
		$qDet = [
			['text' => $inv['expiring_products'] . ' producto(s) por vencer en ≤ 7 días · ' . self::Money($inv['expiring_value']) . ' expuesto → rebajar o trasladar', 'href' => '/waste?fefo_estado=rotacion' . ($branchId ? '&branch_id=' . $branchId : '') . '#tab-fefo', 'tone' => $inv['expiring_products'] ? 'warning' : 'neutral'],
			['text' => $qrN . ' lote(s) en cuarentena → dictaminar', 'href' => '/waste?fefo_estado=cuarentena' . ($branchId ? '&branch_id=' . $branchId : '') . '#tab-fefo', 'tone' => $qrN ? 'danger' : 'neutral'],
			['text' => $q['temperature_excursions'] . ' lectura(s) de frío fuera de rango hoy → revisar cadena de frío', 'href' => '/waste' . $branchQs . '#tab-frio', 'tone' => $q['temperature_excursions'] ? 'danger' : 'neutral']
		];
		if ($q['expired_lots'])
		{
			$qDet[] = ['text' => $q['expired_lots'] . ' lote(s) vencidos con stock → retirar y registrar merma', 'href' => '/waste?fefo_estado=critico' . ($branchId ? '&branch_id=' . $branchId : '') . '#tab-fefo', 'tone' => 'danger'];
		}
		$rows[] = ['quality', 'Calidad', 'fa-shield-halved', '#ec4899', DailyMetricsService::CountLevel($q['total'], 'quality_issues_crit'),
			$q['total'] > 0 ? "{$q['expired_lots']} vencido(s) con stock · {$q['quarantine']} en cuarentena · {$q['temperature_excursions']} frío fuera de rango" : 'Sin lotes vencidos, cuarentenas ni temperaturas fuera de rango', $scopeText, 'Ver vencimientos y FEFO', '/waste' . $branchQs . '#tab-fefo',
			$q['total'] > 0 ? "{$q['total']} hallazgo(s)" : '0 hallazgos', $inv['expiring_value'] > 0 ? -round($inv['expiring_value'], 2) : 0.0, $inv['expiring_value'] > 0 ? self::Money($inv['expiring_value']) . ' expuesto' : '$0', $q['total'] > 0 ? 'down' : 'flat', $q['total'] . ' hallazgo(s)', 'Líder de Calidad', $lastUpdated, $qDet];

		// Mermas: confirmada, pendiente de aprobación y recuperada; % sobre ventas solo con base comercial
		// Confirmada y recuperada del día; pendiente = todo lo que espera aprobación, sea del día que sea
		$wst = $db->prepare("SELECT IFNULL(SUM(CASE WHEN status = 'approved' AND date(created_timestamp) = :d THEN total_cost END), 0) AS ok,
				IFNULL(SUM(CASE WHEN status = 'pending_approval' THEN total_cost END), 0) AS pend, SUM(CASE WHEN status = 'pending_approval' THEN 1 ELSE 0 END) AS pn,
				IFNULL(SUM(CASE WHEN date(created_timestamp) = :d THEN IFNULL(financial_recovery_amount, 0) + IFNULL(recovered_goods_value, 0) END), 0) AS rec
			FROM waste_records WHERE 1 = 1" . $bS('branch_id'));
		$wst->execute([':d' => $day]);
		$wr = $wst->fetch(\PDO::FETCH_ASSOC);
		$w = $kpis['waste_ratio']['numeric'];
		$wGap = $w !== null ? round($w - $wasteTarget, 1) : null;
		$wDet2 = [
			['text' => 'Confirmada hoy: ' . self::Money(floatval($wr['ok']), 2), 'href' => '/waste' . $branchQs, 'tone' => 'neutral'],
			['text' => 'Pendiente de aprobación (todas las fechas): ' . self::Money(floatval($wr['pend']), 2) . ' (' . intval($wr['pn']) . ' registro(s))', 'href' => '/waste/approvals' . $branchQs, 'tone' => floatval($wr['pend']) > 0 ? 'warning' : 'neutral'],
			['text' => 'Recuperada hoy: ' . self::Money(floatval($wr['rec']), 2), 'href' => '/waste' . $branchQs . '#tab-recuperacion', 'tone' => 'neutral']
		];
		$rows[] = ['waste', 'Mermas', 'fa-triangle-exclamation', '#ef4444', $w === null ? (floatval($wr['ok']) + floatval($wr['pend']) > 0 ? 'warning' : 'neutral') : DailyMetricsService::WasteLevel($w, $wasteTarget),
			$w === null ? 'Merma confirmada ' . self::Money(floatval($wr['ok']), 2) . ' · sin base de ventas del día para el porcentaje' : 'Merma ' . number_format($w, 1) . ' % de las ventas (objetivo ≤ ' . number_format($wasteTarget, 1) . ' %)', null, 'Ver mermas', '/waste' . $branchQs,
			$wGap === null ? 'Sin base comercial' : (($wGap > 0 ? '+' : '') . number_format($wGap, 1) . ' pp vs ≤' . number_format($wasteTarget, 1) . '%'), -floatval($wr['ok']), self::Money(floatval($wr['ok'])) . ' confirmada', ($w !== null && $w > $wasteTarget) ? 'down' : 'up', $kpis['waste_ratio']['delta_pp'] ?: 'Sin comparativo', 'Jefe de Mermas', $lastUpdated, $wDet2];

		return array_map(function ($r) use ($st) {
			[$label, $icon] = $st($r[4]);
			return [
				'key' => $r[0], 'process' => $r[1], 'icon' => $r[2], 'color' => $r[3],
				'status' => $r[4], 'status_label' => $label, 'status_icon' => $icon,
				'situation' => $r[5], 'scope_note' => $r[6], 'action_text' => $r[7], 'href' => $r[8],
				'deviation' => $r[9], 'economic_impact' => $r[10], 'economic_impact_label' => $r[11],
				'trend' => $r[12], 'trend_label' => $r[13], 'owner' => $r[14], 'last_updated' => $r[15], 'details' => $r[16] ?? []
			];
		}, $rows);
	}

	private static function GetHeatmapColor(int $intensity): string
	{
		if ($intensity <= 25)
		{
			return '#bbf7d0';
		}
		elseif ($intensity <= 45)
		{
			return '#fef08a';
		}
		elseif ($intensity <= 65)
		{
			return '#fed7aa';
		}
		elseif ($intensity <= 85)
		{
			return '#fb923c';
		}
		return '#ef4444';
	}
}
