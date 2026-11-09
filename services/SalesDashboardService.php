<?php

namespace Grocy\Services;

use Grocy\Helpers\DailyFilters;

/**
 * Tablero principal de Ventas: los cinco bloques prioritarios (evolución, comparación comercial, productividad,
 * top 5 de productos y tráfico de clientes), todos con los filtros compartidos de DailyFilters.
 *
 * Ventas netas: misma definición que DailyMetricsService::Sales. Sin filtro de producto = Σ (total − IVA) de ventas
 * «completed»; con departamento, categoría, subcategoría o producto = Σ sale_items.subtotal de esas mismas ventas.
 * Un valor null es «Sin información» (no hay registros), distinto de un cero real.
 */
class SalesDashboardService extends BaseService
{
	private function Db(): \PDO
	{
		return DatabaseService::GetInstance()->GetDbConnectionRaw();
	}

	private static function ProductFiltered(array $f): bool
	{
		return $f['department'] !== null || $f['group_id'] !== null || $f['subcategory'] !== null || $f['product'] !== null;
	}

	/** Filas agregadas de ventas; en $select, {NET} y {TICKETS} se reemplazan según haya filtro de producto */
	private function Query(array $f, string $from, string $to, string $select, string $tail = ''): array
	{
		$branch = DailyFilters::BranchSql($f, 's.branch_id');
		if (self::ProductFiltered($f))
		{
			$select = str_replace(['{NET}', '{TICKETS}'], ['IFNULL(SUM(si.subtotal), 0)', 'COUNT(DISTINCT s.id)'], $select);
			$sql = "SELECT $select FROM sale_items si JOIN sales s ON s.id = si.sale_id JOIN products p ON p.id = si.product_id
				WHERE s.status = 'completed' AND date(s.created_timestamp) BETWEEN :f AND :t AND $branch AND " . DailyFilters::ProductSql($f) . " $tail";
		}
		else
		{
			$select = str_replace(['{NET}', '{TICKETS}'], ['IFNULL(SUM(s.total_amount - s.tax_amount), 0)', 'COUNT(*)'], $select);
			$sql = "SELECT $select FROM sales s WHERE s.status = 'completed' AND date(s.created_timestamp) BETWEEN :f AND :t AND $branch $tail";
		}
		$st = $this->Db()->prepare($sql);
		$st->execute([':f' => $from, ':t' => $to]);
		return $st->fetchAll(\PDO::FETCH_ASSOC);
	}

	private function Net(array $f, string $from, string $to): array
	{
		$r = $this->Query($f, $from, $to, '{NET} AS net, {TICKETS} AS tickets')[0];
		return ['net' => round(floatval($r['net']), 2), 'tickets' => intval($r['tickets'])];
	}

	private static function Delta(?float $cur, ?float $prev): ?float
	{
		return ($cur === null || $prev === null || $prev <= 0) ? null : round(($cur - $prev) / $prev * 100, 1);
	}

	private static function Days(string $from, string $to): array
	{
		$out = [];
		for ($d = new \DateTime($from), $e = new \DateTime($to); $d <= $e; $d->modify('+1 day'))
		{
			$out[] = $d->format('Y-m-d');
		}
		return $out;
	}

	public function Dashboard(array $f): array
	{
		$from = $f['start_date'];
		$to = $f['end_date'];
		$len = count(self::Days($from, $to));
		$prevTo = date('Y-m-d', strtotime("$from -1 day"));
		$prevFrom = date('Y-m-d', strtotime("$prevTo -" . ($len - 1) . ' days'));
		$last = $this->Query($f, $from, $to, 'MAX(s.created_timestamp) AS ts')[0]['ts'] ?? null;

		return [
			'filters' => $f,
			'period' => ['from' => $from, 'to' => $to, 'days' => $len, 'label' => DailyFilters::PeriodLabel($from, $to),
				'prev_from' => $prevFrom, 'prev_to' => $prevTo, 'prev_label' => DailyFilters::PeriodLabel($prevFrom, $prevTo)],
			'product_filtered' => self::ProductFiltered($f),
			'last_record' => $last,
			'computed_at' => date('Y-m-d H:i'),
			'evolution' => $this->Evolution($f, $from, $to, $prevFrom, $prevTo),
			'comparison' => $this->Comparison($f, $from, $to),
			'productivity' => $this->Productivity($f, $from, $to),
			'top' => $this->TopProducts($f, $from, $to),
			'traffic' => $this->Traffic($f, $from, $to)
		];
	}

	/* 1. Ventas y evolución */
	private function Evolution(array $f, string $from, string $to, string $prevFrom, string $prevTo): array
	{
		$cur = $this->Net($f, $from, $to);
		$prev = $this->Net($f, $prevFrom, $prevTo);
		$daily = array_column($this->Query($f, $from, $to, 'date(s.created_timestamp) AS d, {NET} AS v', 'GROUP BY d'), 'v', 'd');
		$prevDaily = array_column($this->Query($f, $prevFrom, $prevTo, 'date(s.created_timestamp) AS d, {NET} AS v', 'GROUP BY d'), 'v', 'd');
		$days = self::Days($from, $to);
		$prevDays = self::Days($prevFrom, $prevTo);
		$series = [];
		$acc = 0.0;
		$accPrev = 0.0;
		foreach ($days as $i => $d)
		{
			$v = round(floatval($daily[$d] ?? 0), 2);
			$pv = round(floatval($prevDaily[$prevDays[$i]] ?? 0), 2);
			$acc += $v;
			$accPrev += $pv;
			$series[] = ['d' => $d, 'v' => $v, 'prev' => $pv, 'acc' => round($acc, 2), 'acc_prev' => round($accPrev, 2)];
		}
		// Semanas de lunes a domingo; la primera y la última pueden ser parciales (se indica en la etiqueta)
		$weeks = [];
		foreach ($series as $s)
		{
			$monday = date('Y-m-d', strtotime($s['d'] . ' -' . (intval(date('N', strtotime($s['d']))) - 1) . ' days'));
			$weeks[$monday] = $weeks[$monday] ?? ['week' => $monday, 'v' => 0.0, 'days' => 0];
			$weeks[$monday]['v'] = round($weeks[$monday]['v'] + $s['v'], 2);
			$weeks[$monday]['days']++;
		}
		$hourly = array_fill(0, 24, 0.0);
		$hourlyPrev = array_fill(0, 24, 0.0);
		foreach ($this->Query($f, $from, $to, "CAST(strftime('%H', s.created_timestamp) AS INTEGER) AS h, {NET} AS v", 'GROUP BY h') as $r)
		{
			$hourly[intval($r['h'])] = round(floatval($r['v']), 2);
		}
		foreach ($this->Query($f, $prevFrom, $prevTo, "CAST(strftime('%H', s.created_timestamp) AS INTEGER) AS h, {NET} AS v", 'GROUP BY h') as $r)
		{
			$hourlyPrev[intval($r['h'])] = round(floatval($r['v']), 2);
		}
		return [
			'net' => $cur['tickets'] > 0 ? $cur['net'] : null,
			'tickets' => $cur['tickets'],
			'prev_net' => $prev['tickets'] > 0 ? $prev['net'] : null,
			'delta_pct' => self::Delta($cur['tickets'] > 0 ? $cur['net'] : null, $prev['tickets'] > 0 ? $prev['net'] : null),
			'daily_avg' => $cur['tickets'] > 0 ? round($cur['net'] / count($days), 2) : null,
			'series' => $series,
			'weeks' => array_values($weeks),
			'hourly' => $hourly,
			'hourly_prev' => $hourlyPrev
		];
	}

	/* 2. Comparación comercial */
	private function Comparison(array $f, string $from, string $to): array
	{
		// Fin de semana actual = último sábado ≤ fecha final (con el domingo si ya ocurrió); el anterior abarca los mismos días
		$sat = date('Y-m-d', strtotime($to . ' -' . ((intval(date('w', strtotime($to))) + 1) % 7) . ' days'));
		$weEnd = min(date('Y-m-d', strtotime("$sat +1 day")), $to);
		$prevSat = date('Y-m-d', strtotime("$sat -7 days"));
		$prevEnd = date('Y-m-d', strtotime("$weEnd -7 days"));
		$we = $this->Net($f, $sat, $weEnd);
		$pwe = $this->Net($f, $prevSat, $prevEnd);

		$yaFrom = date('Y-m-d', strtotime("$from -1 year"));
		$yaTo = date('Y-m-d', strtotime("$to -1 year"));
		$cur = $this->Net($f, $from, $to);
		$ya = $this->Net($f, $yaFrom, $yaTo);

		$split = ['weekday' => 0.0, 'weekend' => 0.0];
		foreach ($this->Query($f, $from, $to, "CAST(strftime('%w', s.created_timestamp) AS INTEGER) AS w, {NET} AS v", 'GROUP BY w') as $r)
		{
			$split[in_array(intval($r['w']), [0, 6], true) ? 'weekend' : 'weekday'] += floatval($r['v']);
		}
		$total = $split['weekday'] + $split['weekend'];
		$wdDays = 0;
		$weDays = 0;
		foreach (self::Days($from, $to) as $d)
		{
			in_array(intval(date('w', strtotime($d))), [0, 6], true) ? $weDays++ : $wdDays++;
		}
		return [
			'weekend' => ['from' => $sat, 'to' => $weEnd, 'net' => $we['tickets'] > 0 ? $we['net'] : null, 'partial' => $weEnd === $sat,
				'prev_from' => $prevSat, 'prev_to' => $prevEnd, 'prev_net' => $pwe['tickets'] > 0 ? $pwe['net'] : null,
				'delta_pct' => self::Delta($we['tickets'] > 0 ? $we['net'] : null, $pwe['tickets'] > 0 ? $pwe['net'] : null)],
			'year_ago' => ['from' => $yaFrom, 'to' => $yaTo, 'label' => DailyFilters::PeriodLabel($yaFrom, $yaTo), 'net' => $ya['tickets'] > 0 ? $ya['net'] : null,
				'delta_pct' => self::Delta($cur['tickets'] > 0 ? $cur['net'] : null, $ya['tickets'] > 0 ? $ya['net'] : null)],
			'split' => [
				'weekday_net' => round($split['weekday'], 2), 'weekend_net' => round($split['weekend'], 2),
				'weekday_pct' => $total > 0 ? round($split['weekday'] / $total * 100, 1) : null,
				'weekend_pct' => $total > 0 ? round($split['weekend'] / $total * 100, 1) : null,
				'weekday_days' => $wdDays, 'weekend_days' => $weDays
			]
		];
	}

	/* 3. Productividad comercial: ventas ÷ personal con turno y ÷ horas presentes, solo días cerrados */
	private function Productivity(array $f, string $from, string $to): array
	{
		if (self::ProductFiltered($f))
		{
			return ['applies' => false, 'reason' => 'El personal se programa por sucursal y área de tienda, no por departamento, categoría o producto: dividir las ventas de un departamento entre todo el personal daría una cifra engañosa.'];
		}
		$yesterday = date('Y-m-d', strtotime('-1 day'));
		$cut = min($to, $yesterday);
		if ($cut < $from)
		{
			return ['applies' => true, 'has_data' => false, 'cut' => $cut, 'reason' => 'El período solo contiene el día en curso; la productividad se calcula sobre días cerrados.'];
		}
		$branchIds = DailyFilters::BranchIds($f);
		$metrics = DailyMetricsService::GetInstance();
		$hours = 0.0;
		$present = 0.0;
		$weekly = [];
		foreach (self::Days($from, $cut) as $d)
		{
			$dayPresent = 0.0;
			foreach ($branchIds === null ? [null] : $branchIds as $b)
			{
				$l = $metrics->Labor($b, $d);
				$hours += $l['hours'];
				$dayPresent += $l['present_hours'];
			}
			$present += $dayPresent;
			$monday = date('Y-m-d', strtotime($d . ' -' . (intval(date('N', strtotime($d))) - 1) . ' days'));
			$weekly[$monday] = $weekly[$monday] ?? ['week' => $monday, 'hours' => 0.0, 'net' => 0.0];
			$weekly[$monday]['hours'] += $dayPresent;
		}
		$st = $this->Db()->prepare("SELECT COUNT(DISTINCT employee_id) FROM employee_shifts s WHERE s.status <> 'cancelled' AND s.shift_date BETWEEN :f AND :t AND " . DailyFilters::BranchSql($f, 's.branch_id'));
		$st->execute([':f' => $from, ':t' => $cut]);
		$people = intval($st->fetchColumn());
		$net = $this->Net($f, $from, $cut)['net'];
		foreach ($this->Query($f, $from, $cut, "date(s.created_timestamp, '-' || ((CAST(strftime('%w', s.created_timestamp) AS INTEGER) + 6) % 7) || ' days') AS wk, {NET} AS v", 'GROUP BY wk') as $r)
		{
			if (isset($weekly[$r['wk']]))
			{
				$weekly[$r['wk']]['net'] = floatval($r['v']);
			}
		}
		foreach ($weekly as &$w)
		{
			$w['per_hour'] = $w['hours'] > 0 ? round($w['net'] / $w['hours'], 2) : null;
		}
		unset($w);
		return [
			'applies' => true,
			'has_data' => $people > 0,
			'cut' => $cut,
			'excludes_today' => $to > $yesterday,
			'net' => $net,
			'people' => $people,
			'scheduled_hours' => round($hours, 1),
			'present_hours' => round($present, 1),
			'per_person' => $people > 0 ? round($net / $people, 2) : null,
			'per_hour' => $present > 0 ? round($net / $present, 2) : null,
			'per_sqm' => round($net / 1850.0, 2),
			'weekly' => array_values($weekly)
		];
	}

	/* 4. Top 5 de productos por ventas netas (líneas de venta) */
	private function TopProducts(array $f, string $from, string $to): array
	{
		$where = "s.status = 'completed' AND date(s.created_timestamp) BETWEEN :f AND :t AND " . DailyFilters::BranchSql($f, 's.branch_id') . ' AND ' . DailyFilters::ProductSql($f);
		$base = "FROM sale_items si JOIN sales s ON s.id = si.sale_id JOIN products p ON p.id = si.product_id LEFT JOIN product_groups g ON g.id = p.product_group_id WHERE $where";
		$st = $this->Db()->prepare("SELECT IFNULL(SUM(si.subtotal), 0) $base");
		$st->execute([':f' => $from, ':t' => $to]);
		$total = floatval($st->fetchColumn());
		$st = $this->Db()->prepare("SELECT p.id, p.name, p.sku, p.department, g.name AS category, SUM(si.amount) AS units, SUM(si.subtotal) AS net $base GROUP BY p.id ORDER BY net DESC LIMIT 5");
		$st->execute([':f' => $from, ':t' => $to]);
		$rows = [];
		foreach ($st->fetchAll(\PDO::FETCH_ASSOC) as $r)
		{
			$rows[] = ['id' => intval($r['id']), 'name' => $r['name'], 'sku' => $r['sku'], 'department' => $r['department'] ?: 'Sin departamento',
				'category' => trim(preg_replace('/^\d{2}\s+/', '', (string) $r['category'])) ?: 'Sin categoría',
				'units' => round(floatval($r['units']), 2), 'net' => round(floatval($r['net']), 2), 'share_pct' => $total > 0 ? round(floatval($r['net']) / $total * 100, 1) : null];
		}
		return ['rows' => $rows, 'total_lines' => round($total, 2)];
	}

	/**
	 * Margen bruto diario (fecha final), mensual (mes calendario hasta la fecha final) y acumulado (1 de enero a la fecha final).
	 * Margen = ventas netas − costo de ventas (costo FEFO del lote). Si el costo supera 3 veces la venta, el dato se marca
	 * como no confiable en lugar de mostrar un porcentaje absurdo.
	 */
	public function Margins(array $f): array
	{
		$to = $f['end_date'];
		$ranges = ['day' => [$to, $to], 'month' => [substr($to, 0, 8) . '01', $to], 'ytd' => [substr($to, 0, 4) . '-01-01', $to]];
		$out = [];
		foreach ($ranges as $k => [$a, $b])
		{
			$r = $this->Query($f, $a, $b, self::ProductFiltered($f) ? '{NET} AS net, IFNULL(SUM(si.total_cost), 0) AS cogs, {TICKETS} AS n' : '{NET} AS net, IFNULL(SUM(s.total_cogs), 0) AS cogs, {TICKETS} AS n')[0];
			$net = floatval($r['net']);
			$cogs = floatval($r['cogs']);
			$reliable = $net > 0 && $cogs <= $net * 3;
			$out[$k] = ['from' => $a, 'to' => $b, 'label' => DailyFilters::PeriodLabel($a, $b), 'net' => round($net, 2), 'cogs' => round($cogs, 2),
				'margin' => $reliable ? round($net - $cogs, 2) : null, 'margin_pct' => $reliable ? round(($net - $cogs) / $net * 100, 1) : null,
				'has_data' => intval($r['n']) > 0, 'reliable' => $reliable];
		}
		return $out;
	}

	/* 5. Tráfico de clientes: DAILY no tiene contador de ingreso; los tickets por hora se muestran como referencia separada */
	private function Traffic(array $f, string $from, string $to): array
	{
		$tickets = array_fill(0, 24, 0);
		$net = array_fill(0, 24, 0.0);
		foreach ($this->Query($f, $from, $to, "CAST(strftime('%H', s.created_timestamp) AS INTEGER) AS h, {TICKETS} AS n, {NET} AS v", 'GROUP BY h') as $r)
		{
			$tickets[intval($r['h'])] = intval($r['n']);
			$net[intval($r['h'])] = round(floatval($r['v']), 2);
		}
		return ['has_traffic' => false, 'source' => null, 'tickets_by_hour' => $tickets, 'net_by_hour' => $net];
	}
}
