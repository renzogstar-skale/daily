<?php

namespace Grocy\Services;

use Grocy\Helpers\DairyAccess;

/**
 * Mermas · Panel ejecutivo. Cifras de waste_records aprobados (las pendientes de aprobación se informan aparte),
 * ventas completadas y metas registradas, con las definiciones de DailyMetricsService (las mismas de HOY y del
 * Panel ejecutivo). Período: mes en curso hasta la fecha elegida; la comparación usa los mismos días del mes anterior.
 * Tipo de merma = clasificación del motivo (waste_reasons.akcal_classification).
 */
class WasteExecutiveControlCenterService extends BaseService
{
	private static ?WasteExecutiveControlCenterService $instance = null;

	const TYPES = ['operativa' => ['Operativa', '#DC2626'], 'comercial' => ['Comercial', '#F59E0B'], 'averia' => ['Averías', '#F97316'], 'ajuste' => ['Ajustes', '#10B981']];

	public static function GetInstance(): WasteExecutiveControlCenterService
	{
		if (self::$instance === null)
		{
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function GetExecutiveData(?int $branchId = null, ?string $date = null, ?string $department = null, ?string $causal = null, string $metric = 'pct'): array
	{
		return $this->BuildExecutiveDataRaw($branchId, $date, $department, $causal, $metric);
	}

	private function Db(): \PDO
	{
		return DatabaseService::GetInstance()->GetDbConnectionRaw();
	}

	/** Merma aprobada agrupada; $group es una expresión SQL o null (total) */
	private function WasteBy(?int $branchId, string $from, string $to, ?array $deptKeys, ?string $causal, ?string $group = null): array
	{
		$m = DailyMetricsService::GetInstance();
		$where = "w.status = 'approved' AND date(w.created_timestamp) BETWEEN :f AND :t AND " . $m->DeptSql($deptKeys) . ($branchId ? ' AND w.branch_id = ' . intval($branchId) : '');
		$p = [':f' => $from, ':t' => $to];
		if ($causal !== null && $causal !== '' && $causal !== 'todas')
		{
			if (ctype_digit($causal))
			{
				$where .= ' AND w.waste_reason_id = ' . intval($causal);
			}
			else
			{
				$where .= ' AND (r.akcal_classification = :c OR w.loss_category = :c)';
				$p[':c'] = $causal;
			}
		}
		$sel = $group ? "$group AS g, " : '';
		$st = $this->Db()->prepare("SELECT {$sel}COUNT(*) AS n, IFNULL(SUM(w.total_cost), 0) AS cost, IFNULL(SUM(w.amount), 0) AS units, IFNULL(SUM(w.financial_recovery_amount), 0) AS recovered
			FROM waste_records w JOIN products p ON p.id = w.product_id LEFT JOIN waste_reasons r ON r.id = w.waste_reason_id WHERE $where" . ($group ? ' GROUP BY g' : ''));
		$st->execute($p);
		$rows = $st->fetchAll(\PDO::FETCH_ASSOC);
		return $group ? $rows : ($rows[0] ?? ['n' => 0, 'cost' => 0, 'units' => 0, 'recovered' => 0]);
	}

	/** Trazo SVG (120×20) de una serie */
	private static function Spark(array $values): string
	{
		$n = count($values);
		if ($n < 2)
		{
			return 'M0,10 L120,10';
		}
		$max = max($values);
		$min = min($values);
		$span = ($max - $min) ?: 1;
		$pts = [];
		foreach (array_values($values) as $i => $v)
		{
			$pts[] = round($i * 120 / ($n - 1), 1) . ',' . round(18 - ($v - $min) / $span * 14, 1);
		}
		return 'M' . implode(' L', $pts);
	}

	private static function Arrow(?float $d, string $suffix = '%', int $dec = 1): string
	{
		return $d === null ? '' : (($d > 0 ? '↑ +' : ($d < 0 ? '↓ ' : '→ ')) . number_format($d, $dec) . $suffix);
	}

	private function BuildExecutiveDataRaw(?int $branchId, ?string $date, ?string $department, ?string $causal, string $metric): array
	{
		$m = DailyMetricsService::GetInstance();
		$date = ($date && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) ? $date : date('Y-m-d');
		$deptKeys = $m->DepartmentFilter($department);
		$causal = ($causal && $causal !== 'todas' && $causal !== 'todos') ? $causal : null;
		$from = substr($date, 0, 8) . '01';
		$dayOfMonth = intval(date('j', strtotime($date)));
		$prevFrom = date('Y-m-01', strtotime("$from -1 month"));
		$prevTo = date('Y-m-d', min(strtotime("$prevFrom +" . ($dayOfMonth - 1) . ' days'), strtotime(date('Y-m-t', strtotime($prevFrom)))));
		$monthLabel = ['', 'Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];

		$sales = $m->Sales($branchId, $from, $date, $deptKeys);
		$salesPrev = $m->Sales($branchId, $prevFrom, $prevTo, $deptKeys);
		$w = $this->WasteBy($branchId, $from, $date, $deptKeys, $causal);
		$wPrev = $this->WasteBy($branchId, $prevFrom, $prevTo, $deptKeys, $causal);
		$cost = round(floatval($w['cost']), 2);
		$costPrev = floatval($wPrev['cost']);
		$pct = $sales['has_data'] ? $m->WastePct($cost, $sales) : null;
		$pctPrev = $salesPrev['has_data'] ? $m->WastePct($costPrev, $salesPrev) : null;
		$targets = $m->Targets($branchId, $date);
		$targetPct = floatval($targets['waste_target_pct']);
		$pending = $this->Db()->query("SELECT COUNT(*), IFNULL(SUM(total_cost), 0) FROM waste_records WHERE status = 'pending_approval'" . ($branchId ? ' AND branch_id = ' . intval($branchId) : ''))->fetch(\PDO::FETCH_NUM);
		$noData = intval($w['n']) === 0 && !$sales['has_data'];

		$byType = array_column($this->WasteBy($branchId, $from, $date, $deptKeys, $causal, "IFNULL(r.akcal_classification, 'operativa')"), null, 'g');
		$byTypePrev = array_column($this->WasteBy($branchId, $prevFrom, $prevTo, $deptKeys, $causal, "IFNULL(r.akcal_classification, 'operativa')"), null, 'g');
		$tc = fn($k, $src) => floatval($src[$k]['cost'] ?? 0);
		$share = fn($v) => $cost > 0 ? number_format($v / $cost * 100, 1) . '% del total' : 'Sin merma en el período';
		$dPct = fn($a, $b) => $b > 0 ? round(($a - $b) / $b * 100, 1) : null;

		// Serie diaria de 14 días para los trazos
		$daily = $this->WasteBy($branchId, date('Y-m-d', strtotime("$date -13 days")), $date, $deptKeys, $causal, 'date(w.created_timestamp)');
		$dailyMap = array_column($daily, 'cost', 'g');
		$series = [];
		for ($i = 13; $i >= 0; $i--)
		{
			$series[] = floatval($dailyMap[date('Y-m-d', strtotime("$date -$i days"))] ?? 0);
		}
		$spark = self::Spark($series);

		$expiryCost = floatval($this->Db()->query("SELECT IFNULL(SUM(w.total_cost), 0) FROM waste_records w JOIN waste_reasons r ON r.id = w.waste_reason_id JOIN products p ON p.id = w.product_id
			WHERE w.status = 'approved' AND date(w.created_timestamp) BETWEEN '$from' AND '$date' AND (r.name LIKE '%encim%' OR r.name LIKE '%FEFO%' OR r.name LIKE '%rotación%') AND " . $m->DeptSql($deptKeys) . ($branchId ? ' AND w.branch_id = ' . intval($branchId) : ''))->fetchColumn());
		$recovered = floatval($w['recovered']) + floatval($this->Db()->query("SELECT IFNULL(SUM(credit_recovered_amount), 0) FROM supplier_returns WHERE date(created_timestamp) BETWEEN '$from' AND '$date'" . ($branchId ? ' AND branch_id = ' . intval($branchId) : ''))->fetchColumn());

		$kpi = fn($label, $value, $delta, $good, $sub, $color) => ['label' => $label, 'value' => $value, 'delta' => $delta, 'delta_is_good' => $good, 'subtext' => $sub, 'sparkline' => $spark, 'spark_color' => $color];
		$dTotal = $dPct($cost, $costPrev);
		$dPp = ($pct !== null && $pctPrev !== null) ? round($pct - $pctPrev, 2) : null;
		$kpis = [
			'total_waste' => $kpi('Merma total', '$' . number_format($cost, 0), self::Arrow($dTotal), $dTotal !== null && $dTotal <= 0, 'vs. mismos días del mes anterior' . (intval($pending[0]) ? ' · ' . intval($pending[0]) . ' por aprobar ($' . number_format(floatval($pending[1]), 0) . ')' : ''), '#EF4444'),
			'waste_sales' => $kpi('Merma / ventas', $pct === null ? 'Sin información' : number_format($pct, 2) . '%', self::Arrow($dPp, ' pp', 2), $dPp !== null && $dPp <= 0, 'Meta: ≤ ' . number_format($targetPct, 2) . '%' . ($targets['sources']['waste_pct'] === 'config' ? ' (configuración)' : ''), '#EF4444'),
			'operative' => $kpi('Merma operativa', '$' . number_format($tc('operativa', $byType), 0), self::Arrow($dPct($tc('operativa', $byType), $tc('operativa', $byTypePrev))), $tc('operativa', $byType) <= $tc('operativa', $byTypePrev), $share($tc('operativa', $byType)), '#EF4444'),
			'commercial' => $kpi('Merma comercial', '$' . number_format($tc('comercial', $byType), 0), self::Arrow($dPct($tc('comercial', $byType), $tc('comercial', $byTypePrev))), $tc('comercial', $byType) <= $tc('comercial', $byTypePrev), $share($tc('comercial', $byType)), '#F59E0B'),
			'breakage' => $kpi('Averías', '$' . number_format($tc('averia', $byType), 0), self::Arrow($dPct($tc('averia', $byType), $tc('averia', $byTypePrev))), $tc('averia', $byType) <= $tc('averia', $byTypePrev), $share($tc('averia', $byType)), '#F97316'),
			'adjustments' => $kpi('Ajustes', '$' . number_format($tc('ajuste', $byType), 0), self::Arrow($dPct($tc('ajuste', $byType), $tc('ajuste', $byTypePrev))), $tc('ajuste', $byType) <= $tc('ajuste', $byTypePrev), $share($tc('ajuste', $byType)), '#10B981'),
			'recovery' => $kpi('Recuperación financiera', '$' . number_format($recovered, 0), '', true, $recovered > 0 ? 'Recuperado registrado (mermas y notas de crédito)' : 'Sin recuperaciones registradas', '#10B981'),
			'avoidable' => $kpi('Pérdida evitable', '$' . number_format($expiryCost, 0), '', false, 'Merma por vencimiento: prevenible con FEFO y rebajas', '#EF4444')
		];

		// Evolución mensual del año vs. año anterior
		$year = intval(date('Y', strtotime($date)));
		$evo = ['labels' => array_slice($monthLabel, 1), 'pct' => ['current' => [], 'previous' => [], 'target' => []], 'money' => ['current' => [], 'previous' => [], 'target' => []]];
		foreach ([$year => 'current', $year - 1 => 'previous'] as $y => $key)
		{
			for ($mo = 1; $mo <= 12; $mo++)
			{
				$f = sprintf('%04d-%02d-01', $y, $mo);
				$t = date('Y-m-t', strtotime($f));
				if ($f > $date)
				{
					$evo['pct'][$key][] = null;
					$evo['money'][$key][] = null;
					continue;
				}
				$s = $m->Sales($branchId, $f, min($t, $date), $deptKeys);
				$wc = floatval($this->WasteBy($branchId, $f, min($t, $date), $deptKeys, $causal)['cost']);
				$evo['money'][$key][] = $s['has_data'] || $wc > 0 ? round($wc, 0) : null;
				$evo['pct'][$key][] = $s['has_data'] ? $m->WastePct($wc, $s) : null;
				if ($key === 'current')
				{
					$evo['pct']['target'][] = $targetPct;
					$evo['money']['target'][] = $s['has_data'] ? round($s['net_sales'] * $targetPct / 100, 0) : null;
				}
			}
		}
		$sameMonthLy = $evo['pct']['previous'][intval(date('n', strtotime($date))) - 1] ?? null;
		$daysInMonth = intval(date('t', strtotime($date)));
		$projMoney = $dayOfMonth > 0 ? round($cost / $dayOfMonth * $daysInMonth, 0) : null;
		$evolution = $evo + [
			'current_month' => ['label' => 'Este mes (' . $monthLabel[intval(date('n', strtotime($date)))] . ' ' . $year . ')', 'value' => $pct === null ? 'Sin información' : number_format($pct, 2) . '%',
				'delta' => ($pct !== null && $sameMonthLy !== null) ? self::Arrow(round($pct - $sameMonthLy, 2), ' pp', 2) : '', 'subtext' => $sameMonthLy === null ? 'Sin datos del mismo mes del año anterior' : 'vs. mismo mes del año anterior'],
			'closing_projection' => ['label' => 'Proyección de cierre', 'value' => $projMoney === null ? 'Sin información' : '$' . number_format($projMoney, 0),
				'delta' => $pct === null ? '' : self::Arrow(round($pct - $targetPct, 2), ' pp', 2), 'subtext' => 'ritmo diario del mes; % vs. meta (' . number_format($targetPct, 2) . '%)']
		];

		$donut = [];
		foreach (self::TYPES as $k => [$label, $color])
		{
			$v = $tc($k, $byType);
			$donut[] = ['label' => $label, 'amount' => round($v, 2), 'share' => $cost > 0 ? round($v / $cost * 100, 1) : 0, 'color' => $color];
		}

		// Merma por departamento sobre la venta del mismo departamento
		$deptBar = [];
		foreach (DailyMetricsService::DEPARTMENTS as $k => [$name])
		{
			if ($deptKeys !== null && !in_array($k, $deptKeys, true))
			{
				continue;
			}
			$wc = floatval($this->WasteBy($branchId, $from, $date, [$k], $causal)['cost']);
			$s = $m->Sales($branchId, $from, $date, [$k]);
			if ($wc <= 0 && !$s['has_data'])
			{
				continue;
			}
			$dp = $s['net_sales'] > 0 ? round($wc / $s['net_sales'] * 100, 2) : null;
			$deptBar[] = ['name' => $name, 'pct' => $dp ?? 0, 'amount' => round($wc, 0), 'color' => $dp === null ? '#64748B' : ($dp > $targetPct * 1.5 ? '#EF4444' : ($dp > $targetPct ? '#F59E0B' : '#10B981')),
				'over_target' => $dp !== null && $dp > $targetPct, 'note' => $dp === null ? 'Sin ventas del departamento' : ''];
		}
		usort($deptBar, fn($a, $b) => $b['pct'] <=> $a['pct']);

		// Mapa de calor: merma por día de semana y hora del registro (4 semanas)
		$hmFrom = date('Y-m-d', strtotime("$date -27 days"));
		$hm = $this->WasteBy($branchId, $hmFrom, $date, $deptKeys, $causal, "CAST(strftime('%w', w.created_timestamp) AS INTEGER) * 100 + CAST(strftime('%H', w.created_timestamp) AS INTEGER)");
		$hmMap = array_column($hm, 'cost', 'g');
		$hmMax = max(1, max(array_map('floatval', $hmMap ?: [0])));
		$hmTotal = array_sum(array_map('floatval', $hmMap));
		$days = ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'];
		$hours = ['06', '07', '08', '09', '10', '11', '12', '13', '14', '15', '16', '17', '18', '19'];
		$grid = [];
		foreach ($days as $i => $dn)
		{
			$wd = $i === 6 ? 0 : $i + 1;
			$cells = [];
			foreach ($hours as $hh)
			{
				$v = floatval($hmMap[$wd * 100 + intval($hh)] ?? 0);
				$int = intval(round($v / $hmMax * 100));
				$cells[] = ['day' => $dn, 'hour' => "$hh:00", 'intensity' => $int, 'color' => self::GetHeatmapColor($int), 'merma_pct' => $hmTotal > 0 ? number_format($v / $hmTotal * 100, 1) . '% de 4 sem.' : '0%',
					'amount' => '$' . number_format($v / 4, 0) . '/día'];
			}
			$grid[] = ['day' => $dn, 'cells' => $cells];
		}

		// Top 10 productos por costo de merma del mes, con su venta neta para el %
		$top = $this->WasteBy($branchId, $from, $date, $deptKeys, $causal, 'w.product_id');
		usort($top, fn($a, $b) => floatval($b['cost']) <=> floatval($a['cost']));
		$icons = ['dairy' => '🥛', 'bebidas' => '🧃', 'fruver' => '🥬', 'carnes' => '🥩', 'bakery' => '🍞', 'electronica' => '📱', 'otros' => '📦'];
		$topProducts = [];
		foreach (array_slice($top, 0, 10) as $i => $r)
		{
			$p = $this->Db()->query('SELECT name, sku, department FROM products WHERE id = ' . intval($r['g']))->fetch(\PDO::FETCH_ASSOC);
			$dk = 'otros';
			foreach (DailyMetricsService::DEPARTMENTS as $k => $d)
			{
				if ($d[2] !== null && in_array($p['department'], $d[2], true))
				{
					$dk = $k;
				}
			}
			$ps = $this->Db()->query("SELECT IFNULL(SUM(si.subtotal), 0) FROM sale_items si JOIN sales s ON s.id = si.sale_id WHERE s.status = 'completed' AND si.product_id = " . intval($r['g']) . " AND date(s.created_timestamp) BETWEEN '$from' AND '$date'" . ($branchId ? ' AND s.branch_id = ' . intval($branchId) : ''))->fetchColumn();
			$topProducts[] = ['rank' => $i + 1, 'product_id' => intval($r['g']), 'name' => $p['name'], 'sku' => $p['sku'], 'dept' => DailyMetricsService::DEPARTMENTS[$dk][0], 'amount' => '$' . number_format(floatval($r['cost']), 0),
				'units' => round(floatval($r['units']), 1), 'pct_sales' => floatval($ps) > 0 ? number_format(floatval($r['cost']) / floatval($ps) * 100, 1) . '%' : 'Sin ventas', 'icon' => $icons[$dk]];
		}

		// Causas (Pareto)
		$causesRows = $this->WasteBy($branchId, $from, $date, $deptKeys, $causal, "IFNULL(r.name, 'Sin motivo')");
		usort($causesRows, fn($a, $b) => floatval($b['cost']) <=> floatval($a['cost']));
		$palette = ['#EF4444', '#F97316', '#F59E0B', '#EAB308', '#10B981', '#059669', '#14B8A6'];
		$causes = [];
		$acc = 0.0;
		foreach (array_slice($causesRows, 0, 7) as $i => $r)
		{
			$v = floatval($r['cost']);
			$acc += $v;
			$causes[] = ['name' => $r['g'], 'amount' => round($v, 0), 'pct' => $cost > 0 ? round($v / $cost * 100, 1) : 0, 'color' => $palette[$i], 'pareto' => $cost > 0 ? round($acc / $cost * 100, 1) : 0];
		}

		// Sucursales: misma definición; la red es la suma
		$branchesTable = [];
		foreach (BranchService::GetInstance()->GetBranches() as $br)
		{
			$b = intval($br->id);
			if (!DairyAccess::CanSeeBranch($b))
			{
				continue;
			}
			$s = $m->Sales($b, $from, $date, $deptKeys);
			$sp = $m->Sales($b, $prevFrom, $prevTo, $deptKeys);
			$wc = floatval($this->WasteBy($b, $from, $date, $deptKeys, $causal)['cost']);
			$wp = floatval($this->WasteBy($b, $prevFrom, $prevTo, $deptKeys, $causal)['cost']);
			$bp = $s['has_data'] ? $m->WastePct($wc, $s) : null;
			$bpp = $sp['has_data'] ? $m->WastePct($wp, $sp) : null;
			$status = $bp === null ? 'secondary' : ($bp > $targetPct * 1.5 ? 'danger' : ($bp > $targetPct ? 'warning' : 'success'));
			$branchesTable[] = ['id' => sprintf('%02d', $b), 'branch_id' => $b, 'name' => $br->name, 'sales' => $s['has_data'] ? '$' . number_format($s['net_sales'], 0) : 'Sin información', 'waste' => '$' . number_format($wc, 0),
				'pct' => $bp === null ? 'Sin información' : number_format($bp, 2) . '%', 'pct_num' => $bp, 'delta' => ($bp !== null && $bpp !== null) ? self::Arrow(round($bp - $bpp, 2), ' pp', 2) : '',
				'status' => $status, 'dot' => ['danger' => '🔴', 'warning' => '🟡', 'success' => '🟢', 'secondary' => '⚪'][$status]];
		}
		usort($branchesTable, fn($a, $b) => ($b['pct_num'] ?? -1) <=> ($a['pct_num'] ?? -1));

		$claims = $this->Db()->query("SELECT COUNT(*), SUM(CASE WHEN status = 'credit_recovered' THEN 1 ELSE 0 END), SUM(CASE WHEN status = 'pending_credit' THEN 1 ELSE 0 END), IFNULL(SUM(credit_requested_amount), 0), IFNULL(SUM(credit_recovered_amount), 0)
			FROM supplier_returns WHERE date(created_timestamp) BETWEEN '$from' AND '$date'" . ($branchId ? ' AND branch_id = ' . intval($branchId) : ''))->fetch(\PDO::FETCH_NUM);
		$financialRecovery = ['recovered_amount' => '$' . number_format($recovered, 0), 'delta_pct' => '', 'claims_count' => intval($claims[0]), 'claims_closed' => intval($claims[1]), 'claims_in_process' => intval($claims[2]),
			'recovery_rate' => floatval($claims[3]) > 0 ? round(floatval($claims[4]) / floatval($claims[3]) * 100) : null, 'target_rate' => 80];

		$inv = $m->Inventory($branchId);
		$qs = $branchId ? '?branch_id=' . $branchId : '';
		$priorityDecisions = [];
		if ($inv['expiring_products'] > 0)
		{
			$priorityDecisions[] = ['id' => 'dec_expiry', 'severity' => 'danger', 'icon' => 'fa-triangle-exclamation', 'title' => 'Vencimientos próximos', 'description' => $inv['expiring_products'] . ' producto(s) con lotes que vencen en ≤ 7 días. Costo expuesto: $' . number_format($inv['expiring_value'], 0) . '.',
				'badge_text' => 'Prioridad alta', 'badge_class' => 'badge-danger', 'href' => '/quality' . $qs];
		}
		if (intval($pending[0]) > 0)
		{
			$priorityDecisions[] = ['id' => 'dec_pending', 'severity' => 'warning', 'icon' => 'fa-scale-unbalanced', 'title' => 'Mermas por aprobar', 'description' => intval($pending[0]) . ' registro(s) por $' . number_format(floatval($pending[1]), 2) . ': el stock aún no se descontó.',
				'badge_text' => 'Aprobar o rechazar', 'badge_class' => 'badge-warning', 'href' => '/waste/approvals' . $qs];
		}
		if ($causes)
		{
			$priorityDecisions[] = ['id' => 'dec_cause', 'severity' => 'success', 'icon' => 'fa-bullseye', 'title' => 'Causa principal: ' . $causes[0]['name'], 'description' => number_format($causes[0]['pct'], 1) . '% de la merma del mes ($' . number_format($causes[0]['amount'], 0) . ').',
				'badge_text' => 'Plan de acción', 'badge_class' => 'badge-success', 'href' => '/waste' . $qs];
		}

		return [
			'date' => $date,
			'branch_id' => $branchId,
			'no_data' => $noData,
			'department' => $department ?: 'todos',
			'causal' => $causal ?: 'todas',
			'metric' => $metric,
			'period' => ['from' => $from, 'to' => $date, 'compare_from' => $prevFrom, 'compare_to' => $prevTo],
			'target_pct' => $targetPct,
			'last_updated' => date('d/m/Y H:i'),
			'kpis' => $kpis,
			'evolution' => $evolution,
			'donut_types' => ['total' => '$' . number_format($cost, 0), 'items' => $donut],
			'departments_bar' => $deptBar,
			'heatmap' => ['days' => $days, 'hours' => $hours, 'grid' => $grid],
			'top_products' => $topProducts,
			'causes' => $causes,
			'branches_table' => $branchesTable,
			'recovery' => $financialRecovery,
			'priority_decisions' => $priorityDecisions
		];
	}

	private static function GetHeatmapColor(int $intensity): string
	{
		if ($intensity <= 25) return '#bbf7d0';
		if ($intensity <= 45) return '#fef08a';
		if ($intensity <= 65) return '#fed7aa';
		if ($intensity <= 85) return '#fb923c';
		return '#ef4444';
	}
}
