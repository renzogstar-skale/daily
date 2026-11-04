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

		$salesNet = $cur['has_data'] ? $cur['net_sales'] : ($noData ? null : 0.0);
		$cmpTxt = 'vs. ' . self::DayName($weekAgo) . ' anterior' . ($cutoff ? ' a la misma hora' : '');

		// Meta del día (meta mensual registrada ÷ días del mes). Con filtro de departamento no hay meta propia.
		$target = $deptKeys === null ? $m->DailySalesTarget($branchId, $date) : null;
		$rawCompliance = ($target && $salesNet !== null) ? round($salesNet / $target * 100, 1) : null;
		$compliance = $rawCompliance !== null ? min(100.0, $rawCompliance) : null;
		$overTarget = ($rawCompliance !== null && $rawCompliance > 100) ? round($rawCompliance - 100, 1) : 0.0;

		$labor = $m->Labor($branchId, $date);
		$elapsedShare = $isToday ? $this->ElapsedLaborShare($branchId, $date) : 1.0;
		$laborHours = $labor['has_data'] ? round($labor['hours'] * $elapsedShare, 1) : null;
		$sph = ($laborHours && $salesNet !== null) ? round($salesNet / $laborHours, 2) : null;
		$laborCmp = $m->Labor($branchId, $weekAgo);
		$sphCmp = ($laborCmp['hours'] > 0 && $cmp['has_data']) ? round($cmp['net_sales'] / max(0.1, $laborCmp['hours'] * $elapsedShare), 2) : null;

		$waste = $m->Waste($branchId, $date, $date);
		$wasteCmp = $m->Waste($branchId, $weekAgo, $weekAgo);
		$wastePct = $cur['has_data'] ? $m->WastePct($waste['cost'], $cur) : null;
		$wastePctCmp = $cmpFull['has_data'] ? $m->WastePct($wasteCmp['cost'], $cmpFull) : null;
		$targets = $m->Targets($branchId, $date);
		$wasteTarget = floatval($targets['waste_target_pct']);

		// Proyección de cierre: venta a esta hora + lo que el mismo día de la semana pasada vendió desde esta hora
		$projected = $salesNet === null ? null : ($isToday ? round($salesNet + max(0, $cmpFull['net_sales'] - $cmp['net_sales']), 0) : $salesNet);

		$d = fn($a, $b, $good = true, $pp = false) => self::Delta($a, $b, $good, $pp);
		$dSales = $d($salesNet, $cmp['has_data'] ? $cmp['net_sales'] : null);
		$dMargin = $d($cur['margin_pct'], $cmp['margin_pct'], true, true);
		$dProfit = $d($cur['has_data'] ? $cur['gross_margin'] : null, $cmp['has_data'] ? $cmp['gross_margin'] : null);
		$dTicket = $d($cur['avg_ticket'], $cmp['avg_ticket']);
		$dTx = $d($cur['has_data'] ? floatval($cur['tickets']) : null, $cmp['has_data'] ? floatval($cmp['tickets']) : null);
		$dSph = $d($sph, $sphCmp);
		$dWaste = $d($wastePct, $wastePctCmp, false, true);

		$kpis = [
			'sales_net' => ['value' => self::Money($salesNet), 'numeric' => $salesNet, 'delta_pct' => $dSales['text'], 'delta_direction' => $dSales['dir'], 'tone' => $dSales['tone'],
				'comparison_text' => $dSales['text'] !== '' ? $cmpTxt : 'Sin día comparable con ventas'],
			'target_compliance' => [
				'value' => $compliance === null ? 'Sin meta definida' : number_format($compliance, 1) . '%',
				'numeric' => $compliance,
				'raw_numeric' => $rawCompliance,
				'over_target_pct' => $overTarget,
				'visual_value' => $compliance === null ? 'Sin meta definida' : number_format($compliance, 1) . '%',
				'real_value' => $rawCompliance === null ? 'Sin meta definida' : number_format($rawCompliance, 1) . '%',
				'gap_pp' => $compliance === null ? 'Sin meta definida' : ($rawCompliance >= 100 ? ('✓ 100% (Real: ' . number_format($rawCompliance, 1) . '%' . ($overTarget > 0 ? " · +{$overTarget}% sobre meta" : '') . ')') : ('↓ ' . number_format($compliance - 100, 1) . ' pp')),
				'tone' => $compliance === null ? 'neutral' : ($rawCompliance >= 100 ? 'good' : 'bad'),
				'target_text' => $target === null ? 'Sin meta definida' : 'Meta del día: $' . number_format($target, 0) . ($isToday ? ' (día completo)' : ''),
				'diff_monetary' => ($target && $salesNet !== null) ? '$' . number_format($salesNet - $target, 0) : ''
			],
			'gross_margin' => ['value' => $cur['margin_pct'] === null ? 'Sin información' : number_format($cur['margin_pct'], 1) . '%', 'numeric' => $cur['margin_pct'],
				'delta_pp' => $dMargin['text'], 'delta_direction' => $dMargin['dir'], 'tone' => $dMargin['tone'], 'comparison_text' => $dMargin['text'] !== '' ? $cmpTxt : '',
				'cogs_text' => 'CMV: ' . self::Money($cur['has_data'] ? $cur['cogs'] : null)],
			'gross_profit' => ['value' => self::Money($cur['has_data'] ? $cur['gross_margin'] : null), 'numeric' => $cur['gross_margin'], 'delta_pct' => $dProfit['text'], 'delta_direction' => $dProfit['dir'],
				'tone' => $dProfit['tone'], 'subtext' => 'Venta neta − costo de ventas'],
			'avg_ticket' => ['value' => self::Money($cur['avg_ticket'], 2), 'numeric' => $cur['avg_ticket'], 'delta_pct' => $dTicket['text'], 'delta_direction' => $dTicket['dir'], 'tone' => $dTicket['tone'],
				'subtext' => 'Venta neta ÷ tickets'],
			'transactions' => ['value' => $cur['has_data'] || !$noData ? number_format($cur['tickets'], 0) : 'Sin información', 'numeric' => $cur['tickets'], 'delta_pct' => $dTx['text'], 'delta_direction' => $dTx['dir'],
				'tone' => $dTx['tone'], 'subtext' => $deptKeys === null ? 'Tickets completados' : 'Tickets con productos del filtro'],
			'labor_sales_per_hour' => ['value' => $sph === null ? 'Sin información' : '$' . number_format($sph, 2), 'numeric' => $sph, 'delta_pct' => $dSph['text'], 'delta_direction' => $dSph['dir'], 'tone' => $dSph['tone'],
				'subtext' => $laborHours === null ? 'Sin turnos programados' : number_format($laborHours, 1) . ' h programadas' . ($isToday ? ' transcurridas' : '')],
			'waste_ratio' => ['value' => $wastePct === null ? 'Sin información' : number_format($wastePct, 1) . '%', 'numeric' => $wastePct, 'delta_pp' => $dWaste['text'], 'delta_direction' => $dWaste['dir'], 'tone' => $dWaste['tone'],
				'subtext' => $wastePct === null ? 'Sin ventas para calcular la base' : ('$' . number_format($waste['cost'], 2) . ' aprobada · objetivo ≤ ' . number_format($wasteTarget, 1) . '%' . ($waste['pending_events'] ? ' · ' . $waste['pending_events'] . ' pendiente(s)' : ''))]
		];

		// Ventas por hora: acumulado real vs. meta repartida según el perfil horario del mismo día de la semana pasada
		$labels = ['08:00', '10:00', '12:00', '14:00', '16:00', '18:00', '20:00'];
		$byHour = $m->SalesByHour($branchId, $date, $deptKeys);
		$profile = $m->SalesByHour($branchId, $weekAgo, $deptKeys);
		$profileTotal = array_sum($profile);
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
		$mainChart = ['labels' => $labels, 'real_sales' => $real, 'target_sales' => $goal,
			'projected_closing' => ['amount' => self::Money($projected), 'numeric' => $projected, 'variance_pct' => $projVar['text'], 'variance_direction' => $projVar['dir'], 'tone' => $projVar['tone'],
				'variance_text' => $target ? 'vs. meta del día' : ($isToday ? 'según el mismo día de la semana pasada' : 'día cerrado')]];

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
		$heat = $m->SalesHeatmap($branchId, date('Y-m-d', strtotime("$date -27 days")), $date);
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

		$comparative = $this->Comparative($date, $prevDay, $weekAgo, $yearAgo, $cur, $yday, $cmp, $lyear, $comparativeMetric, $metric);

		// Indicadores operativos
		$inv = $m->Inventory($branchId);
		$catalog = max(1, $inv['catalog_with_min']);
		$availability = round(($catalog - $inv['out_of_stock']) / $catalog * 100, 1);
		$returns = $m->Returns($branchId, $date, $date);
		$orders = $m->Orders($branchId, date('Y-m-d', strtotime("$date -27 days")), $date);
		$coverage = $this->Coverage($branchId, $date, $labor);
		$operationalKpis = [
			'on_shelf_availability' => [
				'title' => 'Disponibilidad en bodega',
				'value' => number_format($availability, 1) . '%',
				'numeric' => $availability,
				'delta' => $inv['out_of_stock'] . ' agotado(s)',
				'tone' => $inv['out_of_stock'] ? 'bad' : 'good',
				'tooltip' => 'Disponibilidad en bodega calculada a partir de existencias físicas disponibles descontando reservas y movimientos comprometidos para abastecer la operación comercial',
				'href' => '/stockoverview'
			],
			'returns' => ['title' => 'Devoluciones', 'value' => '$' . number_format($returns['amount'], 0), 'numeric' => $returns['amount'], 'delta' => $returns['count'] . ' registro(s)', 'tone' => 'neutral',
				'tooltip' => 'Ventas anuladas por devolución total y devoluciones/cambios de clientes registrados en el día', 'href' => '/sales'],
			'staff_coverage' => ['title' => 'Cobertura de personal', 'value' => $coverage === null ? ($labor['has_data'] ? $labor['people'] . ' personas' : 'Sin información') : number_format($coverage, 0) . '%', 'numeric' => $coverage,
				'delta' => $labor['has_data'] ? number_format($labor['hours'], 1) . ' h programadas' : 'Sin turnos', 'tone' => $coverage === null ? 'neutral' : ($coverage >= 95 ? 'good' : 'bad'),
				'tooltip' => $coverage === null ? 'Sin requerimiento de dotación configurado: se muestran las personas con turno' : 'Horas programadas ÷ horas requeridas por la cobertura mínima configurada', 'href' => '/operations/scheduling'],
			'on_time_delivery' => ['title' => 'Pedidos a tiempo', 'value' => $orders['on_time_pct'] === null ? 'Sin información' : number_format($orders['on_time_pct'], 0) . '%', 'numeric' => $orders['on_time_pct'],
				'delta' => $orders['dispatched'] . ' despachados (28 d)', 'tone' => $orders['on_time_pct'] === null ? 'neutral' : ($orders['on_time_pct'] >= 95 ? 'good' : 'bad'),
				'tooltip' => 'Pedidos de clientes despachados en o antes de la fecha solicitada, últimos 28 días', 'href' => '/dispatch']
		];

		$cogsPct = ($cur['has_data'] && $cur['net_sales'] > 0) ? round($cur['cogs'] / $cur['net_sales'] * 100, 1) : null;
		$discPct = ($cur['has_data'] && $cur['net_sales'] > 0) ? round($cur['discount'] / ($cur['net_sales'] + $cur['discount']) * 100, 1) : null;
		$dCogs = $d($cur['has_data'] ? $cur['cogs'] : null, $cmp['has_data'] ? $cmp['cogs'] : null, false);
		$dDisc = $d($cur['has_data'] ? $cur['discount'] : null, $cmp['has_data'] ? $cmp['discount'] : null, false);
		$dUpt = $d($cur['units_per_ticket'], $cmp['units_per_ticket']);
		$profitabilityKpis = [
			'cogs' => ['title' => 'Costo de ventas', 'value' => self::Money($cur['has_data'] ? $cur['cogs'] : null), 'numeric' => $cur['cogs'], 'subline' => $cogsPct === null ? 'Sin ventas' : $cogsPct . '% de ventas',
				'delta' => $dCogs['text'], 'tone' => $dCogs['tone'], 'tooltip' => 'Costo FEFO de los lotes vendidos (CMV). Utilidad bruta: ' . self::Money($cur['has_data'] ? $cur['gross_margin'] : null), 'href' => '/kpis'],
			'discounts' => ['title' => 'Descuentos', 'value' => self::Money($cur['has_data'] ? $cur['discount'] : null), 'numeric' => $cur['discount'], 'subline' => $discPct === null ? 'Sin ventas' : $discPct . '% de la venta bruta',
				'delta' => $dDisc['text'], 'tone' => $dDisc['tone'], 'tooltip' => $deptKeys === null ? 'Rebajas FEFO y descuentos de ticket registrados en el POS' : 'Los descuentos se registran por ticket: no se reparten por departamento', 'href' => '/sales'],
			'units_per_tx' => ['title' => 'Unidades por transacción', 'value' => $cur['units_per_ticket'] === null ? 'Sin información' : number_format($cur['units_per_ticket'], 1), 'numeric' => $cur['units_per_ticket'],
				'delta' => $dUpt['text'], 'tone' => $dUpt['tone'], 'tooltip' => 'Unidades vendidas ÷ tickets', 'href' => '/sales']
		];

		$sensitive = intval(DatabaseService::GetInstance()->GetDbConnectionRaw()->query("SELECT COUNT(*) FROM sensitive_investigation_cases WHERE stage <> 'cerrado'" . ($branchId ? ' AND branch_id = ' . intval($branchId) : ''))->fetchColumn());
		$adjust = $m->PendingAdjustments($branchId);
		$branchQs = $branchId ? '&branch_id=' . $branchId : '';
		$riskInventory = [
			'low_stock' => ['title' => 'Bajo stock', 'value' => ($inv['below_min'] + $inv['out_of_stock']) . ' productos', 'count' => $inv['below_min'] + $inv['out_of_stock'],
				'delta' => $inv['out_of_stock'] . ' agotados · ' . $inv['below_min'] . ' bajo mínimo', 'tone' => ($inv['below_min'] + $inv['out_of_stock']) ? 'bad' : 'good',
				'tooltip' => 'Productos activos con existencia por debajo del stock mínimo configurado', 'href' => '/stockoverview?filter=below_min' . $branchQs],
			'expiring' => ['title' => 'Próximos a vencer', 'value' => $inv['expiring_products'] . ' productos', 'count' => $inv['expiring_products'], 'delta' => '$' . number_format($inv['expiring_value'], 0) . ' en ≤ 7 días',
				'tone' => $inv['expiring_products'] ? 'bad' : 'good', 'tooltip' => 'Productos con lotes que vencen en los próximos 7 días (costo expuesto, no pérdida confirmada)', 'href' => '/quality' . ($branchId ? '?branch_id=' . $branchId : '')],
			'sensitive_diffs' => ['title' => 'Diferencias de inventario', 'value' => ($sensitive + $adjust) . ' pendientes', 'count' => $sensitive + $adjust, 'delta' => $adjust . ' ajustes por aprobar · ' . $sensitive . ' investigaciones',
				'tone' => ($sensitive + $adjust) ? 'bad' : 'good', 'tooltip' => 'Ajustes de inventario pendientes de aprobación e investigaciones de productos sensibles abiertas', 'href' => '/reconciliation']
		];

		$branchPriorities = $this->BranchPriorities($date, $branchId);

		$hoursCmp = $laborCmp['has_data'] ? $laborCmp['hours'] : null;
		$workforceKpis = [
			'coverage' => $operationalKpis['staff_coverage'],
			'productivity' => ['title' => 'Venta por hora programada', 'value' => $kpis['labor_sales_per_hour']['value'], 'numeric' => $sph, 'delta' => $dSph['text'], 'tone' => $dSph['tone'],
				'tooltip' => 'Ventas netas ÷ horas de turno programadas' . ($isToday ? ' ya transcurridas' : ''), 'href' => '/operations/scheduling'],
			'overtime' => ['title' => 'Horas programadas', 'value' => $labor['has_data'] ? number_format($labor['hours'], 1) . ' h' : 'Sin información', 'numeric' => $labor['hours'],
				'delta' => $hoursCmp === null ? '' : self::Delta($labor['hours'], $hoursCmp, false)['text'], 'tone' => 'neutral',
				'tooltip' => 'Horas de los turnos del día (sin pausas). Las horas extra no se registran por separado.', 'href' => '/operations/scheduling']
		];

		$result = [
			'date' => $date,
			'branch_id' => $branchId,
			'no_data' => $noData,
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
			'heatmap' => ['hours' => $heatHours, 'days' => $days, 'grid' => $grid, 'insights' => $heatInsights],
			'comparative' => $comparative,
			'priority_decisions' => $this->PriorityDecisions($branchId, $waste),
			'operational_kpis' => $operationalKpis,
			'profitability_kpis' => $profitabilityKpis,
			'risk_inventory' => $riskInventory,
			'branch_priorities' => $branchPriorities,
			'workforce_kpis' => $workforceKpis,
			'last_updated' => date('d/m/Y H:i')
		];
		$result['sparks'] = $this->Sparks($branchId, $date, $deptKeys);
		$result['process_statuses'] = $this->GetProcessStatuses($branchId, $date, $department, $category, $kpis, $operationalKpis, $riskInventory, $workforceKpis, $wasteTarget);
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
			$total += $e - $a;
			$done += max(0, min($now, $e) - $a);
		}
		return $total > 0 ? max(0.05, $done / $total) : 1.0;
	}

	/** Horas programadas ÷ horas requeridas por coverage_requirements del día; null si no hay requerimiento */
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
		return $req > 0 ? round(min(100, ($labor['present_hours'] ?? $labor['hours']) / $req * 100), 0) : null;
	}

	private function Comparative(string $date, string $prevDay, string $weekAgo, string $yearAgo, array $cur, array $yday, array $cmp, array $lyear, ?string $comparativeMetric, string $metric): array
	{
		$fmt = function (string $d, bool $year = false) {
			$mon = ['', 'Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'][intval(date('n', strtotime($d)))];
			return date('j', strtotime($d)) . ' ' . $mon . ($year ? ' ' . date('Y', strtotime($d)) : '');
		};
		$rows = [['Ayer', $fmt($prevDay), $yday, '#8194ad', false], ['Hoy', $fmt($date), $cur, '#00C83B', true], ['Semana ant.', $fmt($weekAgo), $cmp, '#6ee7b7', false], ['Año anterior', $fmt($yearAgo, true), $lyear, '#a7f3d0', false]];
		$build = function (string $title, string $unit, callable $val, callable $show) use ($rows) {
			$items = [];
			$vals = [];
			foreach ($rows as [$label, $dt, $s, $color, $isCur])
			{
				$v = $s['has_data'] ? $val($s) : null;
				$vals[] = floatval($v ?? 0);
				$items[] = ['label' => $label, 'date' => $dt, 'value' => $v ?? 0, 'formatted' => $v === null ? 'Sin información' : $show($v), 'color' => $color, 'is_current' => $isCur, 'no_data' => $v === null];
			}
			return ['title' => $title, 'unit' => $unit, 'suggested_max' => max(1, round(max($vals) * 1.18, 1)), 'items' => $items];
		};
		$metrics = [
			'sales' => $build('Ventas netas', '$', fn($s) => $s['net_sales'], fn($v) => '$' . number_format($v, 0)),
			'margin' => $build('Margen', '%', fn($s) => $s['margin_pct'], fn($v) => number_format($v, 1) . '%'),
			'profit' => $build('Utilidad bruta', '$', fn($s) => $s['gross_margin'], fn($v) => '$' . number_format($v, 0))
		];
		$items = array_map(fn($i) => ['label' => $i['label'], 'date' => $i['date'], 'sales' => $i['value'], 'formatted_sales' => $i['formatted'], 'color' => $i['color'], 'is_current' => $i['is_current']], $metrics['sales']['items']);
		$active = in_array($comparativeMetric, ['sales', 'margin', 'profit'], true) ? $comparativeMetric : (in_array($metric, ['margin', 'profit'], true) ? $metric : 'sales');
		return ['metric' => $active, 'metrics' => $metrics, 'items' => $items, 'note' => 'Los días anteriores se cortan a la misma hora cuando la fecha es hoy'];
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
			$out[] = ['id' => 'dec_waste_approvals', 'severity' => 'warning', 'icon' => 'fa-scale-unbalanced', 'title' => 'Mermas por aprobar', 'description' => intval($p[0]) . ' registro(s) de merma esperan aprobación; su stock aún no se descontó.',
				'impact_text' => 'Costo pendiente: $' . number_format(floatval($p[1]), 2), 'impact_class' => 'text-warning', 'href' => '/waste/approvals' . $qs, 'module_label' => 'Mermas'];
		}
		$late = DailyMetricsService::GetInstance()->LateReceipts($branchId);
		if ($late > 0)
		{
			$out[] = ['id' => 'dec_late_receipts', 'severity' => 'warning', 'icon' => 'fa-truck-ramp-box', 'title' => 'Recepciones atrasadas', 'description' => "$late orden(es) de compra enviadas superaron su fecha comprometida sin recibirse.",
				'impact_text' => 'Afecta la disponibilidad del día', 'impact_class' => 'text-warning', 'href' => '/mrp' . $qs, 'module_label' => 'Compras'];
		}
		$a = $db->query("SELECT COUNT(*) FROM dairy_alerts WHERE status IN ('open', 'in_progress') AND priority IN ('P1', 'P2')$b")->fetchColumn();
		if (intval($a) > 0)
		{
			$out[] = ['id' => 'dec_alerts', 'severity' => 'danger', 'icon' => 'fa-bell', 'title' => 'Alertas de prioridad alta abiertas', 'description' => intval($a) . ' alerta(s) P1/P2 sin cerrar. Cada una enlaza al producto o registro que la originó.',
				'impact_text' => 'Requiere responsable asignado', 'impact_class' => 'text-danger', 'href' => '/alerts' . $qs, 'module_label' => 'Centro de alertas'];
		}
		$t = $db->query("SELECT COUNT(*) FROM tasks WHERE done = 0 AND due_date IS NOT NULL AND date(due_date) < date('now', 'localtime')")->fetchColumn();
		if (intval($t) > 0)
		{
			$out[] = ['id' => 'dec_tasks_overdue', 'severity' => 'warning', 'icon' => 'fa-list-check', 'title' => 'Tareas vencidas', 'description' => intval($t) . ' tarea(s) pendientes con fecha vencida (todas las sucursales).',
				'impact_text' => 'Prioridad · requiere acción', 'impact_class' => 'text-warning', 'href' => '/tasks', 'module_label' => 'Tareas'];
		}
		$o = DailyMetricsService::GetInstance()->Orders($branchId, date('Y-m-d'), date('Y-m-d'));
		if ($o['open'] > 0)
		{
			$out[] = ['id' => 'dec_orders', 'severity' => $o['late_open'] ? 'danger' : 'info', 'icon' => 'fa-box', 'title' => 'Pedidos de clientes en curso', 'description' => $o['open'] . ' pedido(s) confirmados con stock reservado' . ($o['late_open'] ? ', ' . $o['late_open'] . ' con fecha vencida' : '') . '.',
				'impact_text' => 'Stock reservado: no disponible para venta', 'impact_class' => $o['late_open'] ? 'text-danger' : 'text-info', 'href' => '/orders' . $qs, 'module_label' => 'Pedidos'];
		}
		return $out;
	}

	/** Una prioridad por sucursal (la más relevante), ordenadas por gravedad */
	private function BranchPriorities(string $date, ?int $only): array
	{
		$m = DailyMetricsService::GetInstance();
		$out = [];
		foreach (BranchService::GetInstance()->GetBranches() as $br)
		{
			$id = intval($br->id);
			if ($only && $id !== $only)
			{
				continue;
			}
			$inv = $m->Inventory($id);
			$s = $m->Sales($id, $date, $date);
			$w = $m->Waste($id, date('Y-m-d', strtotime("$date -6 days")), $date);
			$sw = $m->Sales($id, date('Y-m-d', strtotime("$date -6 days")), $date);
			$wPct = $sw['has_data'] ? $m->WastePct($w['cost'], $sw) : null;
			$cands = [];
			if ($inv['out_of_stock'] > 0)
			{
				$cands[] = [3, 'fa-box-open', '#ef4444', 'Agotados', 'Alta', 'is-alta', $inv['out_of_stock'] . ' producto(s) sin stock', '/stockoverview?branch_id=' . $id . '&filter=below_min'];
			}
			if ($wPct !== null && $wPct > 2.5)
			{
				$cands[] = [2, 'fa-triangle-exclamation', '#ef4444', 'Merma', 'Alta', 'is-alta', 'Merma ' . number_format($wPct, 1) . '% en 7 días ($' . number_format($w['cost'], 0) . ')', '/waste?branch_id=' . $id];
			}
			if ($inv['expiring_products'] > 0)
			{
				$cands[] = [1, 'fa-calendar-days', '#f59e0b', 'Vencimientos', 'Media', 'is-media', $inv['expiring_products'] . ' producto(s) vencen en ≤ 7 días', '/quality?branch_id=' . $id];
			}
			if ($inv['below_min'] > 0)
			{
				$cands[] = [1, 'fa-arrow-down-wide-short', '#f59e0b', 'Bajo mínimo', 'Media', 'is-media', $inv['below_min'] . ' producto(s) bajo el mínimo', '/stockoverview?branch_id=' . $id . '&filter=below_min'];
			}
			if (!$s['has_data'])
			{
				$cands[] = [0, 'fa-circle-question', '#64748b', 'Sin ventas', 'Baja', 'is-baja', 'Sin ventas registradas en la fecha', '/sales?branch_id=' . $id];
			}
			if (!$cands)
			{
				continue;
			}
			usort($cands, fn($a, $b) => $b[0] <=> $a[0]);
			$c = $cands[0];
			$out[] = ['branch' => $br->name, 'branch_code' => $br->code ?? '', 'branch_id' => $id, 'status_icon' => $c[1], 'status_icon_color' => $c[2], 'status_text' => $c[3], 'priority' => $c[4],
				'priority_class' => $c[5], 'detail' => $c[6], 'href' => $c[7], '_rank' => $c[0]];
		}
		usort($out, fn($a, $b) => $b['_rank'] <=> $a['_rank']);
		return array_slice($out, 0, $only ? 1 : 5);
	}

	private function GetProcessStatuses(?int $branchId, ?string $date, ?string $department, ?string $category, array $kpis, array $operationalKpis, array $riskInventory, array $workforceKpis, float $wasteTarget): array
	{
		$m = DailyMetricsService::GetInstance();
		$scopeDept = ($department && $department !== 'todos' && $department !== 'all') || ($category && $category !== 'todas' && $category !== 'all');
		$scopeText = $scopeDept ? 'Alcance: toda la sucursal' : null;
		$branchQs = $branchId ? '?branch_id=' . $branchId : '';
		$st = function (string $level) {
			return ['success' => ['En objetivo', 'fa-circle-check'], 'warning' => ['Atención', 'fa-circle-exclamation'], 'danger' => ['Crítico', 'fa-circle-xmark'], 'neutral' => ['Sin información', 'fa-circle-question']][$level];
		};
		$rows = [];

		$late = $m->LateReceipts($branchId);
		$rows[] = ['purchasing', 'Compras', 'fa-cart-shopping', '#0284c7', DailyMetricsService::CountLevel($late, 'late_receipts_crit'),
			$late > 0 ? "$late orden(es) de compra atrasada(s)" : 'Sin órdenes de compra atrasadas', $scopeText, 'Ver compras', '/mrp' . $branchQs];

		$diff = intval($riskInventory['sensitive_diffs']['count']);
		$rows[] = ['inventory', 'Inventario', 'fa-boxes-stacked', '#059669', DailyMetricsService::CountLevel($diff, 'inventory_diffs_crit'),
			$diff > 0 ? "$diff diferencia(s) pendiente(s) de revisión" : 'Sin ajustes ni investigaciones pendientes', $scopeText, 'Ver inventario', '/reconciliation' . $branchQs];

		$c = $kpis['target_compliance']['numeric'];
		$rows[] = ['sales', 'Ventas', 'fa-chart-line', '#00C83B', DailyMetricsService::SalesLevel($c),
			$c === null ? ($kpis['target_compliance']['value'] === 'Sin meta' ? 'Sin meta registrada para evaluar el objetivo' : 'Sin ventas registradas') : 'Cumplimiento ' . number_format($c, 1) . ' % · ' . $kpis['target_compliance']['target_text'], null, 'Ver ventas', '/sales' . $branchQs];

		$o = $m->Orders($branchId, date('Y-m-d', strtotime(($date ?: 'now') . ' -27 days')), $date ?: date('Y-m-d'));
		$rows[] = ['dispatch', 'Despachos', 'fa-truck-fast', '#6366f1', $o['late_open'] > 0 ? DailyMetricsService::CountLevel($o['late_open'], 'late_orders_crit') : ($o['dispatched'] + $o['open'] > 0 ? 'success' : 'neutral'),
			$o['late_open'] > 0 ? $o['late_open'] . ' pedido(s) con fecha vencida sin despachar' : ($o['dispatched'] + $o['open'] > 0 ? $o['open'] . ' pedido(s) en curso · ' . $o['dispatched'] . ' despachados en 28 días' : 'Sin pedidos registrados'), $scopeText, 'Ver despachos', '/dispatch' . $branchQs];

		$cov = $operationalKpis['staff_coverage']['numeric'];
		$people = $workforceKpis['overtime']['numeric'];
		$absentPeople = intval($m->Labor($branchId, $date ?: date('Y-m-d'))['absent_people']);
		$rows[] = ['workforce', 'Personal', 'fa-user-clock', '#8b5cf6', DailyMetricsService::CoverageLevel($cov),
			$cov === null ? ($people > 0 ? number_format($people, 1) . ' h programadas (sin requerimiento de cobertura configurado)' : 'Sin turnos programados para la fecha') : 'Cobertura ' . number_format($cov, 0) . ' % de la dotación requerida' . ($absentPeople ? ' · ' . $absentPeople . ' ausencia(s) o inasistencia(s)' : ''), $scopeText, 'Ver cobertura', '/operations/scheduling' . $branchQs];

		$q = $m->QualityIssues($branchId, $date ?: date('Y-m-d'));
		$rows[] = ['quality', 'Calidad', 'fa-shield-halved', '#ec4899', DailyMetricsService::CountLevel($q['total'], 'quality_issues_crit'),
			$q['total'] > 0 ? "{$q['expired_lots']} lote(s) vencidos con stock · {$q['quarantine']} en cuarentena · {$q['temperature_excursions']} lectura(s) fuera de rango" : 'Sin lotes vencidos, cuarentenas ni temperaturas fuera de rango', $scopeText, 'Ver calidad', '/quality' . $branchQs];

		$w = $kpis['waste_ratio']['numeric'];
		$rows[] = ['waste', 'Mermas', 'fa-triangle-exclamation', '#ef4444', DailyMetricsService::WasteLevel($w, $wasteTarget),
			$w === null ? 'Sin ventas para calcular la merma del día' : 'Merma ' . number_format($w, 1) . ' % (objetivo ≤ ' . number_format($wasteTarget, 1) . ' %)', null, 'Ver mermas', '/waste' . $branchQs];

		return array_map(function ($r) use ($st) {
			[$label, $icon] = $st($r[4]);
			return ['key' => $r[0], 'process' => $r[1], 'icon' => $r[2], 'color' => $r[3], 'status' => $r[4], 'status_label' => $label, 'status_icon' => $icon, 'situation' => $r[5], 'scope_note' => $r[6], 'action_text' => $r[7], 'href' => $r[8]];
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
