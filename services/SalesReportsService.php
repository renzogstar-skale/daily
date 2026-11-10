<?php

namespace Grocy\Services;

use Grocy\Helpers\DailyFilters;

/**
 * Informes comerciales de Ventas y Rentabilidad (Tablero, Departamentos, Productos, Sucursales, Historial y Rentabilidad).
 *
 * Una sola definición de venta neta para todos los informes: la venta neta de cada ticket (total − IVA, ya con
 * descuentos) se reparte entre sus líneas en proporción al subtotal de cada línea. Así, con los mismos filtros,
 * Σ por sucursal = Σ por departamento = Σ por producto = venta neta total (sin duplicar ni perder transacciones).
 * Costo de ventas = sale_items.total_cost (costo FEFO del lote). Solo ventas «completed».
 * Un valor null significa «Sin información» (no hay registros); nunca se rellena con datos inventados.
 */
class SalesReportsService extends BaseService
{
	private ?string $deptCase = null;
	private ?string $cutover = null;
	// Con filtro de departamento, los tickets del archivo son los del departamento (como en las ventas transaccionales)
	private static bool $deptFiltered = false;

	private function Db(): \PDO
	{
		return DatabaseService::GetInstance()->GetDbConnectionRaw();
	}

	/** Clave de departamento (DailyMetricsService::DEPARTMENTS) de cada producto, como expresión SQL */
	private function DeptCase(): string
	{
		if ($this->deptCase === null)
		{
			$db = $this->Db();
			$parts = [];
			foreach (DailyMetricsService::DEPARTMENTS as $k => $d)
			{
				if ($d[2] !== null)
				{
					$parts[] = 'WHEN p.department IN (' . implode(',', array_map([$db, 'quote'], $d[2])) . ') THEN ' . $db->quote($k);
				}
			}
			$this->deptCase = 'CASE ' . implode(' ', $parts) . " ELSE 'otros' END";
		}
		return $this->deptCase;
	}

	/**
	 * Primer día de ventas transaccionales cuando existe archivo histórico (daily_sales_archive, migración 0322):
	 * los días anteriores se leen solo del archivo para no duplicar. '' = sin archivo.
	 */
	private function Cutover(): string
	{
		if ($this->cutover === null)
		{
			$this->cutover = '';
			try
			{
				$max = $this->Db()->query('SELECT MAX(day) FROM daily_sales_archive')->fetchColumn();
				if ($max)
				{
					$this->cutover = date('Y-m-d', strtotime("$max +1 day"));
				}
			}
			catch (\Throwable $e) {}
		}
		return $this->cutover;
	}

	/** El archivo no tiene categoría, subcategoría ni producto: con esos filtros no se usa */
	private function ArchiveWhere(array $f, string $from, string $to): ?string
	{
		$cut = $this->Cutover();
		if ($cut === '' || $from >= $cut || $f['group_id'] !== null || $f['subcategory'] !== null || $f['product'] !== null)
		{
			return null;
		}
		$last = min($to, date('Y-m-d', strtotime("$cut -1 day")));
		$w = 'a.day BETWEEN ' . $this->Db()->quote($from) . ' AND ' . $this->Db()->quote($last) . ' AND ' . DailyFilters::BranchSql($f, 'a.branch_id');
		if ($f['department'] !== null)
		{
			$w .= ' AND a.department = ' . $this->Db()->quote($f['department']);
		}
		return $w;
	}

	private function ArchiveAgg(array $f, string $from, string $to, array $groups): array
	{
		$w = $this->ArchiveWhere($f, $from, $to);
		if ($w === null)
		{
			return [];
		}
		$expr = ['day' => 'a.day', 'month' => 'substr(a.day, 1, 7)', 'dow' => "CAST(strftime('%w', a.day) AS INTEGER)", 'branch' => 'a.branch_id',
			'dept' => 'a.department', 'product' => '0', 'hour' => '-1'];
		$sel = [];
		foreach (array_values($groups) as $i => $g)
		{
			$sel[] = $expr[$g] . " AS g$i";
		}
		$tk = in_array('dept', $groups, true) || $f['department'] !== null ? 'a.tickets' : 'a.branch_tickets';
		$rows = $this->Db()->query('SELECT ' . ($sel ? implode(', ', $sel) . ', ' : '') . "SUM(a.net) AS net, SUM(a.cogs) AS cogs, SUM(a.units) AS units, SUM($tk) AS tickets
			FROM daily_sales_archive a WHERE $w" . ($sel ? ' GROUP BY ' . implode(', ', array_map(fn($i) => "g$i", array_keys($sel))) : ''))->fetchAll(\PDO::FETCH_ASSOC);
		return array_values(array_filter($rows, fn($r) => $r['net'] !== null));
	}

	/** Filas del archivo con la forma de Lines(): sin ticket (sale = null), hora -1 y producto 0 */
	private function ArchiveLines(array $f, string $from, string $to): array
	{
		$w = $this->ArchiveWhere($f, $from, $to);
		if ($w === null)
		{
			return [];
		}
		return $this->Db()->query("SELECT NULL AS sale, a.day, -1 AS hour, CAST(strftime('%w', a.day) AS INTEGER) AS dow, a.branch_id AS branch, a.department AS dept,
			0 AS product, a.net, a.cogs, a.units, a.tickets AS tk, a.branch_tickets AS tkb FROM daily_sales_archive a WHERE $w")->fetchAll(\PDO::FETCH_ASSOC);
	}

	/**
	 * Detalle por producto del archivo: cada celda (día × sucursal × departamento) repartida con la mezcla de productos
	 * (daily_sales_archive_mix). $by = '' (producto), 'branch' o 'day'; $only = ids de producto a incluir.
	 * Devuelve [producto => fila] o [producto => [clave => venta]].
	 */
	private function ArchiveProducts(array $f, string $from, string $to, string $by = '', ?array $only = null): array
	{
		$w = $this->ArchiveWhere($f, $from, $to);
		if ($w === null)
		{
			return [];
		}
		$only = $only !== null ? ' AND m.product_id IN (' . (implode(',', array_map('intval', $only)) ?: '0') . ')' : '';
		try
		{
			// La participación es fija por sucursal × departamento: se suman primero las celdas y luego se reparte
			$k = ['' => '', 'branch' => 'c.branch_id', 'day' => 'c.day'][$by];
			$rows = $this->Db()->query("SELECT m.product_id AS p" . ($k !== '' ? ", $k AS k" : '') . ", SUM(c.net * m.share) AS net, SUM(c.cogs * m.share) AS cogs, SUM(c.net * m.share / m.unit_price) AS units
				FROM (SELECT a.branch_id, a.department" . ($by === 'day' ? ', a.day' : '') . ", SUM(a.net) AS net, SUM(a.cogs) AS cogs FROM daily_sales_archive a
					WHERE $w GROUP BY a.branch_id, a.department" . ($by === 'day' ? ', a.day' : '') . ") c
				JOIN daily_sales_archive_mix m ON m.branch_id = c.branch_id AND m.department = c.department
				WHERE 1 = 1$only GROUP BY m.product_id" . ($k !== '' ? ", $k" : ''))->fetchAll(\PDO::FETCH_ASSOC);
		}
		catch (\Throwable $e)
		{
			return [];
		}
		$out = [];
		foreach ($rows as $r)
		{
			if ($by === '')
			{
				$out[$r['p']] = ['net' => round(floatval($r['net']), 2), 'cogs' => round(floatval($r['cogs']), 2), 'units' => round(floatval($r['units']), 2), 'tickets' => 0];
			}
			else
			{
				$out[$r['p']][$r['k']] = round(floatval($r['net']), 2);
			}
		}
		return $out;
	}

	/** Participación de cada hora en la venta del día para los días del archivo ([hora => participación]) */
	private function ArchiveHours(): array
	{
		try
		{
			return array_column($this->Db()->query('SELECT hour, share FROM daily_sales_archive_hours')->fetchAll(\PDO::FETCH_ASSOC), 'share', 'hour');
		}
		catch (\Throwable $e)
		{
			return [];
		}
	}

	/** Sustituye la fila «producto 0» del archivo por su detalle por producto */
	private function MergeArchiveProducts(array $byProduct, array $f, string $from, string $to): array
	{
		if (!isset($byProduct['0']) && !isset($byProduct[0]))
		{
			return $byProduct;
		}
		$detail = $this->ArchiveProducts($f, $from, $to);
		if (!$detail)
		{
			return $byProduct;
		}
		unset($byProduct['0'], $byProduct[0]);
		foreach ($detail as $pid => $r)
		{
			if (isset($byProduct[$pid]))
			{
				foreach (['net', 'cogs', 'units'] as $m)
				{
					$byProduct[$pid][$m] = round($byProduct[$pid][$m] + $r[$m], 2);
				}
			}
			else
			{
				$byProduct[$pid] = ['g0' => (string) $pid] + $r;
			}
		}
		return $byProduct;
	}

	private function GroupExpr(string $g): string
	{
		return [
			'day' => 'date(s.created_timestamp)',
			'hour' => "CAST(strftime('%H', s.created_timestamp) AS INTEGER)",
			'dow' => "CAST(strftime('%w', s.created_timestamp) AS INTEGER)",
			'month' => "strftime('%Y-%m', s.created_timestamp)",
			'branch' => 's.branch_id',
			'dept' => $this->DeptCase(),
			'product' => 'p.id'
		][$g];
	}

	/**
	 * Agregado de líneas de venta con los filtros compartidos (sucursal, departamento, categoría, subcategoría, producto).
	 * Devuelve filas con g0..gn (agrupaciones), net, cogs, units y tickets (tickets distintos dentro del grupo).
	 */
	private function Agg(array $f, string $from, string $to, array $groups = [], string $having = ''): array
	{
		$branch = DailyFilters::BranchSql($f, 's.branch_id');
		$sel = [];
		foreach (array_values($groups) as $i => $g)
		{
			$sel[] = $this->GroupExpr($g) . " AS g$i";
		}
		$sql = "WITH t AS (SELECT si.sale_id, SUM(si.subtotal) AS ls FROM sale_items si JOIN sales s ON s.id = si.sale_id
				WHERE s.status = 'completed' AND s.created_timestamp >= :f AND s.created_timestamp < :t AND $branch GROUP BY si.sale_id)
			SELECT " . ($sel ? implode(', ', $sel) . ', ' : '') . "
				IFNULL(SUM(si.subtotal * CASE WHEN t.ls > 0 THEN (s.total_amount - s.tax_amount) / t.ls ELSE 1 END), 0) AS net,
				IFNULL(SUM(si.total_cost), 0) AS cogs, IFNULL(SUM(si.amount), 0) AS units, COUNT(DISTINCT s.id) AS tickets
			FROM sale_items si JOIN sales s ON s.id = si.sale_id JOIN t ON t.sale_id = s.id JOIN products p ON p.id = si.product_id
			WHERE s.status = 'completed' AND s.created_timestamp >= :f AND s.created_timestamp < :t AND $branch AND " . DailyFilters::ProductSql($f)
			. ($sel ? ' GROUP BY ' . implode(', ', array_map(fn($i) => "g$i", array_keys($sel))) : '') . " $having";
		$tFrom = max($from, $this->Cutover());
		$rows = [];
		if ($tFrom <= $to)
		{
			$st = $this->Db()->prepare($sql);
			$st->execute([':f' => $tFrom . ' 00:00:00', ':t' => date('Y-m-d', strtotime("$to +1 day")) . ' 00:00:00']);
			$rows = $st->fetchAll(\PDO::FETCH_ASSOC);
		}
		// Días anteriores a las ventas transaccionales: archivo histórico consolidado
		$idx = [];
		foreach ($rows as $i => $r)
		{
			$idx[($r['g0'] ?? '') . '|' . ($r['g1'] ?? '')] = $i;
		}
		foreach ($this->ArchiveAgg($f, $from, $to, $groups) as $a)
		{
			$k = ($a['g0'] ?? '') . '|' . ($a['g1'] ?? '');
			if (isset($idx[$k]))
			{
				foreach (['net', 'cogs', 'units', 'tickets'] as $m)
				{
					$rows[$idx[$k]][$m] += $a[$m];
				}
			}
			else
			{
				$idx[$k] = count($rows);
				$rows[] = $a;
			}
		}
		foreach ($rows as &$r)
		{
			$r['net'] = round(floatval($r['net']), 2);
			$r['cogs'] = round(floatval($r['cogs']), 2);
			$r['units'] = round(floatval($r['units']), 2);
			$r['tickets'] = intval($r['tickets']);
		}
		unset($r);
		if (!$groups && !$rows)
		{
			$rows[] = ['net' => 0.0, 'cogs' => 0.0, 'units' => 0.0, 'tickets' => 0];
		}
		return $rows;
	}

	/** Agregado indexado por la primera agrupación */
	private function By(array $f, string $from, string $to, string $g): array
	{
		$out = [];
		foreach ($this->Agg($f, $from, $to, [$g]) as $r)
		{
			$out[(string) $r['g0']] = $r;
		}
		return $out;
	}

	private function Total(array $f, string $from, string $to): array
	{
		return $this->Agg($f, $from, $to)[0] ?? ['net' => 0.0, 'cogs' => 0.0, 'units' => 0.0, 'tickets' => 0];
	}

	/** Líneas de venta del período (una lectura) para agregarlas en memoria por cualquier dimensión */
	private function Lines(array $f, string $from, string $to): array
	{
		$branch = DailyFilters::BranchSql($f, 's.branch_id');
		$sql = "WITH t AS (SELECT si.sale_id, SUM(si.subtotal) AS ls FROM sale_items si JOIN sales s ON s.id = si.sale_id
				WHERE s.status = 'completed' AND s.created_timestamp >= :f AND s.created_timestamp < :t AND $branch GROUP BY si.sale_id)
			SELECT s.id AS sale, date(s.created_timestamp) AS day, CAST(strftime('%H', s.created_timestamp) AS INTEGER) AS hour,
				CAST(strftime('%w', s.created_timestamp) AS INTEGER) AS dow, s.branch_id AS branch, " . $this->DeptCase() . " AS dept, p.id AS product,
				si.subtotal * CASE WHEN t.ls > 0 THEN (s.total_amount - s.tax_amount) / t.ls ELSE 1 END AS net, si.total_cost AS cogs, si.amount AS units
			FROM sale_items si JOIN sales s ON s.id = si.sale_id JOIN t ON t.sale_id = s.id JOIN products p ON p.id = si.product_id
			WHERE s.status = 'completed' AND s.created_timestamp >= :f AND s.created_timestamp < :t AND $branch AND " . DailyFilters::ProductSql($f);
		$tFrom = max($from, $this->Cutover());
		$rows = [];
		if ($tFrom <= $to)
		{
			$st = $this->Db()->prepare($sql);
			$st->execute([':f' => $tFrom . ' 00:00:00', ':t' => date('Y-m-d', strtotime("$to +1 day")) . ' 00:00:00']);
			$rows = $st->fetchAll(\PDO::FETCH_ASSOC);
		}
		return array_merge($rows, $this->ArchiveLines($f, $from, $to));
	}

	/** Agregado en memoria de Lines(), con la misma forma que Agg() (g0, g1, net, cogs, units, tickets) */
	private static function Group(array $lines, array $groups = []): array
	{
		$g0 = $groups[0] ?? null;
		$g1 = $groups[1] ?? null;
		$net = $cogs = $units = $sales = $keys = $extra = [];
		$tkField = in_array('dept', $groups, true) || self::$deptFiltered ? 'tk' : 'tkb';
		foreach ($lines as $l)
		{
			$key = $g0 === null ? '' : ($g1 === null ? $l[$g0] : $l[$g0] . '|' . $l[$g1]);
			if (!isset($net[$key]))
			{
				$net[$key] = $cogs[$key] = $units[$key] = 0.0;
				$keys[$key] = $g0 === null ? [] : ($g1 === null ? ['g0' => (string) $l[$g0]] : ['g0' => (string) $l[$g0], 'g1' => (string) $l[$g1]]);
			}
			$net[$key] += $l['net'];
			$cogs[$key] += $l['cogs'];
			$units[$key] += $l['units'];
			if ($l['sale'] === null)
			{
				$extra[$key] = ($extra[$key] ?? 0) + intval($l[$tkField]);
			}
			else
			{
				$sales[$key][$l['sale']] = true;
			}
		}
		$out = [];
		foreach ($keys as $key => $r)
		{
			$out[] = $r + ['net' => round($net[$key], 2), 'cogs' => round($cogs[$key], 2), 'units' => round($units[$key], 2), 'tickets' => count($sales[$key] ?? []) + ($extra[$key] ?? 0)];
		}
		if (!$groups && !$out)
		{
			$out[] = ['net' => 0.0, 'cogs' => 0.0, 'units' => 0.0, 'tickets' => 0];
		}
		return $out;
	}

	/** Venta neta por dos dimensiones: [$a][$b] => net (sin tickets, más liviano que Group) */
	private static function Sum2(array $lines, string $a, string $b): array
	{
		$out = [];
		foreach ($lines as $l)
		{
			$out[$l[$a]][$l[$b]] = ($out[$l[$a]][$l[$b]] ?? 0) + $l['net'];
		}
		foreach ($out as &$row)
		{
			foreach ($row as &$v)
			{
				$v = round($v, 2);
			}
		}
		return $out;
	}

	private static function GroupBy(array $lines, string $g): array
	{
		$out = [];
		foreach (self::Group($lines, [$g]) as $r)
		{
			$out[$r['g0']] = $r;
		}
		return $out;
	}

	/** Suma de un rango de fechas a partir de un agregado diario (las ventas pertenecen a un solo día: los tickets se suman) */
	private static function SumDays(array $byDay, string $from, string $to): array
	{
		$out = ['net' => 0.0, 'cogs' => 0.0, 'tickets' => 0];
		foreach ($byDay as $d => $r)
		{
			if ($d >= $from && $d <= $to)
			{
				$out['net'] += $r['net'];
				$out['cogs'] += $r['cogs'];
				$out['tickets'] += $r['tickets'];
			}
		}
		$out['net'] = round($out['net'], 2);
		$out['cogs'] = round($out['cogs'], 2);
		return $out;
	}

	public static function Delta(?float $cur, ?float $prev): ?float
	{
		return ($cur === null || $prev === null || $prev <= 0) ? null : round(($cur - $prev) / $prev * 100, 1);
	}

	public static function Share(float $part, float $total): ?float
	{
		return $total > 0 ? round($part / $total * 100, 1) : null;
	}

	/** Margen bruto %; null si no hay venta o si el costo registrado no es confiable (más de 3 veces la venta) */
	public static function Margin(float $net, float $cogs): ?float
	{
		return ($net > 0 && $cogs <= $net * 3) ? round(($net - $cogs) / $net * 100, 1) : null;
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

	private static function Series(array $days, array $by, string $field = 'net'): array
	{
		return array_map(fn($d) => isset($by[$d]) ? $by[$d][$field] : 0, $days);
	}

	/**
	 * Meta comercial del período (dairy_budgets.sales_target mensual prorrateada por día). Por sucursal si hay metas por
	 * sucursal; si no, la meta consolidada (branch_id = 0) cuando se ven todas las sucursales. Null si falta la meta de
	 * algún mes del período o si hay filtro de producto/departamento (no hay metas por departamento).
	 * Devuelve ['total' => float, 'daily' => [fecha => meta], 'by_branch' => [id => meta]].
	 */
	private function Goals(array $f, array $days, bool $productFiltered): ?array
	{
		if ($productFiltered)
		{
			// Solo departamento (sin categoría ni producto) y toda la red: meta del departamento
			if ($f['department'] !== null && $f['group_id'] === null && $f['subcategory'] === null && $f['product'] === null)
			{
				$dt = $this->DeptTargets($f, $days)[$f['department']] ?? null;
				if ($dt && $dt['total'] > 0)
				{
					// Meta del departamento por sucursal (para el ranking de sucursales con este filtro)
					$byBranch = [];
					foreach ((DailyFilters::BranchIds($f) ?? array_map('intval', $this->Db()->query('SELECT id FROM branches WHERE active = 1')->fetchAll(\PDO::FETCH_COLUMN))) as $b)
					{
						$bt = $this->DeptTargets(['branch_id' => $b] + $f, $days)[$f['department']] ?? null;
						if ($bt)
						{
							$byBranch[$b] = $bt['total'];
						}
					}
					return ['total' => $dt['total'], 'daily' => array_map(fn($v) => round($v, 2), $dt['daily']), 'by_branch' => $byBranch];
				}
			}
			return null;
		}
		$months = array_values(array_unique(array_map(fn($d) => substr($d, 0, 7), $days)));
		$in = implode(',', array_map(fn($m) => $this->Db()->quote($m), $months));
		$rows = $this->Db()->query("SELECT branch_id, period, sales_target FROM dairy_budgets WHERE period IN ($in) AND sales_target IS NOT NULL AND sales_target > 0")->fetchAll(\PDO::FETCH_ASSOC);
		$t = [];
		foreach ($rows as $r)
		{
			$t[intval($r['branch_id'])][$r['period']] = floatval($r['sales_target']);
		}
		// Meta diaria de una sucursal (0 = red consolidada); null si falta la meta de algún mes del período
		$prorate = function (int $id) use ($t, $days): ?array {
			$out = [];
			foreach ($days as $d)
			{
				$m = substr($d, 0, 7);
				if (!isset($t[$id][$m]))
				{
					return null;
				}
				$out[$d] = $t[$id][$m] / intval(date('t', strtotime($d)));
			}
			return $out;
		};
		$ids = DailyFilters::BranchIds($f);
		$all = $ids === null;
		$ids = $ids ?? array_map('intval', $this->Db()->query('SELECT id FROM branches WHERE active = 1')->fetchAll(\PDO::FETCH_COLUMN));
		$byBranch = [];
		$daily = array_fill_keys($days, 0.0);
		$complete = true;
		foreach ($ids as $id)
		{
			$p = $prorate($id);
			if ($p === null)
			{
				$complete = false;
				continue;
			}
			$byBranch[$id] = round(array_sum($p), 2);
			foreach ($p as $d => $v)
			{
				$daily[$d] += $v;
			}
		}
		// Si alguna sucursal del alcance no tiene meta, solo vale la meta consolidada de la red (viendo toda la red)
		if (!$complete)
		{
			$net = $all ? $prorate(0) : null;
			if ($net === null)
			{
				return $byBranch ? ['total' => null, 'daily' => null, 'by_branch' => $byBranch] : null;
			}
			$daily = $net;
		}
		$total = array_sum($daily);
		if ($total <= 0)
		{
			return null;
		}
		return ['total' => round($total, 2), 'daily' => array_map(fn($v) => round($v, 2), $daily), 'by_branch' => $byBranch];
	}

	/**
	 * Metas de venta por departamento (daily_department_sales_targets) prorrateadas por día: de la red (branch_id = 0) o de
	 * la sucursal filtrada. [clave => ['total' => meta, 'daily' => [día => meta]]]; un
	 * departamento sin meta en algún mes del período queda fuera.
	 */
	private function DeptTargets(array $f, array $days): array
	{
		$bid = intval($f['branch_id'] ?? 0);
		try
		{
			$months = array_values(array_unique(array_map(fn($d) => substr($d, 0, 7), $days)));
			$in = implode(',', array_map(fn($m) => $this->Db()->quote($m), $months));
			$rows = $this->Db()->query("SELECT period, department, sales_target FROM daily_department_sales_targets WHERE branch_id = $bid AND period IN ($in)")->fetchAll(\PDO::FETCH_ASSOC);
		}
		catch (\Throwable $e)
		{
			return [];
		}
		$t = [];
		foreach ($rows as $r)
		{
			$t[$r['department']][$r['period']] = floatval($r['sales_target']);
		}
		$out = [];
		foreach ($t as $dept => $byMonth)
		{
			$daily = [];
			foreach ($days as $d)
			{
				if (!isset($byMonth[substr($d, 0, 7)]))
				{
					continue 2;
				}
				$daily[$d] = $byMonth[substr($d, 0, 7)] / intval(date('t', strtotime($d)));
			}
			$out[$dept] = ['total' => round(array_sum($daily), 2), 'daily' => $daily];
		}
		return $out;
	}

	/** Todos los informes comerciales con los filtros compartidos y el modo de comparación (prev_period | year_ago | target) */
	public function Report(array $f, string $compareWith = 'prev_period'): array
	{
		$from = $f['start_date'];
		$to = $f['end_date'];
		$days = self::Days($from, $to);
		$len = count($days);
		if ($compareWith === 'year_ago')
		{
			$prevFrom = date('Y-m-d', strtotime("$from -1 year"));
			$prevTo = date('Y-m-d', strtotime("$to -1 year"));
			$prevLabel = 'mismo período del año anterior';
		}
		else
		{
			$prevTo = date('Y-m-d', strtotime("$from -1 day"));
			$prevFrom = date('Y-m-d', strtotime("$prevTo -" . ($len - 1) . ' days'));
			$prevLabel = 'período anterior';
		}
		$productFiltered = $f['department'] !== null || $f['group_id'] !== null || $f['subcategory'] !== null || $f['product'] !== null;

		self::$deptFiltered = $f['department'] !== null;
		$cut = $this->Cutover();
		$L = $this->Lines($f, $from, $to);
		$PL = $this->Lines($f, $prevFrom, $prevTo);
		$cur = self::Group($L)[0];
		$prev = self::Group($PL)[0];
		$goals = $this->Goals($f, $days, $productFiltered);
		$deptTargets = $this->DeptTargets($f, $days);

		$branchNames = [];
		foreach ($this->Db()->query('SELECT id, name FROM branches')->fetchAll(\PDO::FETCH_ASSOC) as $b)
		{
			$branchNames[intval($b['id'])] = $b['name'];
		}
		$depts = [];
		foreach (DailyMetricsService::DEPARTMENTS as $k => $d)
		{
			$depts[$k] = ['key' => $k, 'name' => $d[0], 'color' => $d[1]];
		}

		$ctx = compact('f', 'from', 'to', 'days', 'prevFrom', 'prevTo', 'cur', 'prev', 'goals', 'branchNames', 'depts', 'L', 'deptTargets');
		$ctx['curDaily'] = self::GroupBy($L, 'day');
		$ctx['prevDaily'] = self::GroupBy($PL, 'day');
		$ctx['curBranch'] = self::GroupBy($L, 'branch');
		$ctx['prevBranch'] = self::GroupBy($PL, 'branch');
		$ctx['curDept'] = self::GroupBy($L, 'dept');
		$ctx['prevDept'] = self::GroupBy($PL, 'dept');
		$ctx['curProduct'] = $this->MergeArchiveProducts(self::GroupBy($L, 'product'), $f, $from, $to);
		$ctx['prevProduct'] = $this->MergeArchiveProducts(self::GroupBy($PL, 'product'), $f, $prevFrom, $prevTo);
		$ctx['prevProductDetail'] = !isset($ctx['prevProduct']['0']) && !isset($ctx['prevProduct'][0]);
		// Agregado diario de las 53 semanas previas a la fecha final (tendencia 90 días, acumulados, calendario y fines de semana)
		// y agregado mensual de todo el historial, ambos de una sola lectura diaria
		$calFrom = date('Y-m-d', strtotime("$to -372 days"));
		$ctx['cal'] = [];
		$ctx['monthly'] = [];
		foreach ($this->By($f, '2000-01-01', $to, 'day') as $d => $r)
		{
			if ($d >= $calFrom)
			{
				$ctx['cal'][$d] = $r;
			}
			$m = substr($d, 0, 7);
			$ctx['monthly'][$m] = ['net' => ($ctx['monthly'][$m]['net'] ?? 0) + $r['net'], 'cogs' => ($ctx['monthly'][$m]['cogs'] ?? 0) + $r['cogs'], 'tickets' => ($ctx['monthly'][$m]['tickets'] ?? 0) + $r['tickets']];
		}
		foreach ($ctx['monthly'] as &$mr)
		{
			$mr['net'] = round($mr['net'], 2);
			$mr['cogs'] = round($mr['cogs'], 2);
		}
		unset($mr);
		ksort($ctx['monthly']);
		unset($L, $PL);

		return [
			'period' => ['from' => $from, 'to' => $to, 'days' => $len, 'label' => DailyFilters::PeriodLabel($from, $to),
				'prev_from' => $prevFrom, 'prev_to' => $prevTo, 'prev_label' => DailyFilters::PeriodLabel($prevFrom, $prevTo), 'prev_name' => $prevLabel,
				'compare_with' => $compareWith],
			'product_filtered' => $productFiltered,
			'archive' => ['cutover' => $cut ?: null, 'in_period' => $cut !== '' && $from < $cut, 'in_prev' => $cut !== '' && $prevFrom < $cut,
				'usable' => $f['group_id'] === null && $f['subcategory'] === null && $f['product'] === null],
			'consistency' => $this->Consistency($ctx),
			'dashboard' => $this->Dashboard($ctx),
			'departments' => $this->Departments($ctx),
			'products' => $this->Products($ctx),
			'branches' => $this->Branches($ctx),
			'history' => $this->History($ctx),
			'profitability' => $this->Profitability($ctx)
		];
	}

	/** Comprobación: total = Σ sucursales = Σ departamentos = Σ productos (diferencias solo de redondeo) */
	private function Consistency(array $c): array
	{
		$t = $c['cur']['net'];
		$sb = round(array_sum(array_column($c['curBranch'], 'net')), 2);
		$sd = round(array_sum(array_column($c['curDept'], 'net')), 2);
		$sp = round(array_sum(array_column($c['curProduct'], 'net')), 2);
		$tol = max(1.0, count($c['curProduct']) * 0.01);
		return ['total' => $t, 'by_branch' => $sb, 'by_department' => $sd, 'by_product' => $sp,
			'ok' => abs($t - $sb) <= $tol && abs($t - $sd) <= $tol && abs($t - $sp) <= $tol];
	}

	/* 1. Tablero de ventas: ¿cuánto vendemos y cómo evoluciona? */
	private function Dashboard(array $c): array
	{
		['f' => $f, 'from' => $from, 'to' => $to, 'days' => $days, 'cur' => $cur, 'prev' => $prev, 'goals' => $goals] = $c;
		$hourly = array_fill(0, 24, ['net' => 0.0, 'tickets' => 0]);
		foreach (self::Group($c['L'], ['hour']) as $r)
		{
			if (intval($r['g0']) >= 0)
			{
				$hourly[intval($r['g0'])] = ['net' => $r['net'], 'tickets' => $r['tickets']];
			}
		}
		$activeHours = array_sum(array_map(fn($h) => count(array_filter(array_keys($h), fn($k) => $k >= 0)), self::Sum2($c['L'], 'day', 'hour')));
		// Días del archivo: su venta se reparte con la curva por hora de referencia
		$shares = $this->ArchiveHours();
		if ($shares)
		{
			$archDays = 0;
			foreach (self::Group(array_filter($c['L'], fn($l) => $l['sale'] === null), ['day']) as $r)
			{
				foreach ($shares as $h => $sh)
				{
					$hourly[$h]['net'] = round($hourly[$h]['net'] + $r['net'] * $sh, 2);
					$hourly[$h]['tickets'] += (int) round($r['tickets'] * $sh);
				}
				$archDays++;
			}
			$activeHours += $archDays * count($shares);
		}

		// Últimos 90 días hasta la fecha final (para las vistas 7 / 30 / 90 días)
		$d90 = self::Days(date('Y-m-d', strtotime("$to -89 days")), $to);
		$by90 = $c['cal'];

		// Serie del período anterior alineada día a día
		$prevDays = self::Days($c['prevFrom'], $c['prevTo']);
		$prevBy = $c['prevDaily'];

		// Acumulados del día, la semana (lunes) y el mes de la fecha final
		$weekStart = date('Y-m-d', strtotime('monday this week', strtotime($to)));
		$acc = [];
		foreach (['day' => [$to, $to], 'week' => [$weekStart, $to], 'month' => [substr($to, 0, 8) . '01', $to]] as $k => [$a, $b])
		{
			$t = self::SumDays($c['cal'], $a, $b);
			$acc[$k] = ['from' => $a, 'to' => $b, 'label' => DailyFilters::PeriodLabel($a, $b), 'net' => $t['net'], 'tickets' => $t['tickets']];
		}

		$curSeries = self::Series($days, $c['curDaily']);
		$cum = [];
		$run = 0;
		foreach ($curSeries as $v)
		{
			$cum[] = round($run += $v, 2);
		}
		$goalCum = null;
		if (!empty($goals['daily']))
		{
			$run = 0;
			$goalCum = array_map(function ($d) use (&$run, $goals) { return round($run += $goals['daily'][$d], 2); }, $days);
		}

		return [
			'net' => $cur['net'], 'prev_net' => $prev['net'], 'growth_pct' => self::Delta($cur['net'], $prev['net']),
			'goal' => $goals['total'] ?? null, 'goal_pct' => !empty($goals['total']) ? round($cur['net'] / $goals['total'] * 100, 1) : null,
			'goal_gap' => !empty($goals['total']) ? round($cur['net'] - $goals['total'], 2) : null,
			'tickets' => $cur['tickets'], 'prev_tickets' => $prev['tickets'], 'tickets_delta' => self::Delta($cur['tickets'], $prev['tickets']),
			'avg_ticket' => $cur['tickets'] > 0 ? round($cur['net'] / $cur['tickets'], 2) : null,
			'prev_avg_ticket' => $prev['tickets'] > 0 ? round($prev['net'] / $prev['tickets'], 2) : null,
			'active_hours' => $activeHours,
			'per_hour' => $activeHours > 0 ? round($cur['net'] / $activeHours, 2) : null,
			'accumulated' => $acc,
			'hourly' => ['labels' => array_map(fn($h) => sprintf('%02d:00', $h), range(0, 23)), 'net' => array_column($hourly, 'net'), 'tickets' => array_column($hourly, 'tickets')],
			'trend90' => ['days' => $d90, 'net' => self::Series($d90, $by90)],
			'vs_prev' => ['days' => $days, 'prev_days' => $prevDays, 'net' => $curSeries, 'prev_net' => self::Series($prevDays, $prevBy)],
			'cumulative' => ['days' => $days, 'net' => $cum, 'goal' => $goalCum]
		];
	}

	/* 2. Ventas por departamento: ¿qué departamento vende más? */
	private function Departments(array $c): array
	{
		['f' => $f, 'from' => $from, 'to' => $to, 'days' => $days, 'cur' => $cur, 'depts' => $depts, 'branchNames' => $bn] = $c;
		$rows = [];
		foreach ($depts as $k => $d)
		{
			$n = $c['curDept'][$k]['net'] ?? 0.0;
			$p = $c['prevDept'][$k]['net'] ?? 0.0;
			if ($n <= 0 && $p <= 0)
			{
				continue;
			}
			$rows[] = $d + ['net' => $n, 'prev_net' => $p, 'delta_pct' => self::Delta($n, $p), 'share_pct' => self::Share($n, $cur['net']),
				'tickets' => $c['curDept'][$k]['tickets'] ?? 0, 'units' => $c['curDept'][$k]['units'] ?? 0, 'goal' => $c['deptTargets'][$k]['total'] ?? null,
				'goal_pct' => !empty($c['deptTargets'][$k]['total']) ? round($n / $c['deptTargets'][$k]['total'] * 100, 1) : null];
		}
		usort($rows, fn($a, $b) => $b['net'] <=> $a['net']);

		// Evolución diaria por departamento y matriz departamento × sucursal
		$evo = [];
		$evo = self::Sum2($c['L'], 'dept', 'day');
		$series = [];
		foreach ($rows as $r)
		{
			$series[$r['key']] = array_map(fn($d) => $evo[$r['key']][$d] ?? 0, $days);
		}
		$heat = [];
		$branchIds = [];
		$heat = self::Sum2($c['L'], 'dept', 'branch');
		foreach ($heat as $hr)
		{
			foreach ($hr as $bid => $v)
			{
				$branchIds[intval($bid)] = true;
			}
		}
		$branches = [];
		foreach (array_keys($branchIds) as $id)
		{
			$branches[] = ['id' => $id, 'name' => $bn[$id] ?? ('Sucursal ' . $id)];
		}
		usort($branches, fn($a, $b) => strcmp($a['name'], $b['name']));
		return ['rows' => $rows, 'days' => $days, 'series' => $series, 'heat' => $heat, 'heat_branches' => $branches,
			'has_goals' => count(array_filter($rows, fn($r) => $r['goal'] !== null)) > 0];
	}

	/* 3. Productos de venta: ¿qué productos venden más en toda la empresa? */
	private function Products(array $c): array
	{
		['f' => $f, 'from' => $from, 'to' => $to, 'days' => $days, 'cur' => $cur, 'depts' => $depts, 'branchNames' => $bn] = $c;
		$ids = array_keys($c['curProduct'] + $c['prevProduct']);
		$meta = [];
		if ($ids)
		{
			$st = $this->Db()->query('SELECT p.id, p.name, p.sku, ' . $this->DeptCase() . ' AS dept, g.name AS category FROM products p LEFT JOIN product_groups g ON g.id = p.product_group_id WHERE p.id IN (' . implode(',', array_map('intval', $ids)) . ')');
			foreach ($st->fetchAll(\PDO::FETCH_ASSOC) as $m)
			{
				$meta[$m['id']] = $m;
			}
		}
		// Sucursal líder de cada producto
		$leader = [];
		$byBranch = [];
		$byBranch = self::Sum2($c['L'], 'product', 'branch');
		unset($byBranch[0]);
		foreach ($this->ArchiveProducts($f, $from, $to, 'branch') as $pid => $bs)
		{
			foreach ($bs as $bid => $v)
			{
				$byBranch[$pid][$bid] = round(($byBranch[$pid][$bid] ?? 0) + $v, 2);
			}
		}
		foreach ($byBranch as $pid => $bs)
		{
			foreach ($bs as $bid => $v)
			{
				if (!isset($leader[$pid]) || $v > $leader[$pid][1])
				{
					$leader[$pid] = [intval($bid), $v];
				}
			}
		}
		$rows = [];
		foreach ($ids as $pid)
		{
			$pid = (string) $pid;
			if ($pid === '0')
			{
				continue; // archivo histórico: sin detalle por producto
			}
			$m = $meta[$pid] ?? ['name' => 'Producto ' . $pid, 'sku' => '', 'dept' => 'otros', 'category' => null];
			$n = $c['curProduct'][$pid]['net'] ?? 0.0;
			$p = $c['prevProductDetail'] ? ($c['prevProduct'][$pid]['net'] ?? 0.0) : null;
			$rows[] = ['id' => intval($pid), 'name' => $m['name'], 'sku' => (string) $m['sku'], 'dept' => $depts[$m['dept']]['name'] ?? 'Otros',
				'category' => trim(preg_replace('/^\d{2}\s+/', '', (string) $m['category'])) ?: 'Sin categoría',
				'units' => $c['curProduct'][$pid]['units'] ?? 0, 'net' => $n, 'prev_net' => $p, 'delta_pct' => self::Delta($n, $p),
				'delta_abs' => $p === null ? null : round($n - $p, 2), 'share_pct' => self::Share($n, $cur['net']),
				'leader' => isset($leader[$pid]) ? ($bn[$leader[$pid][0]] ?? '') : null];
		}
		usort($rows, fn($a, $b) => $b['net'] <=> $a['net']);
		$sold = array_values(array_filter($rows, fn($r) => $r['net'] > 0 || $r['units'] > 0));
		$topNet = array_slice($sold, 0, 10);
		$byUnits = $sold;
		usort($byUnits, fn($a, $b) => $b['units'] <=> $a['units']);
		$topUnits = array_slice($byUnits, 0, 10);
		// Mayor crecimiento / mayor caída en valor absoluto (productos con venta en ambos períodos)
		$both = array_values(array_filter($rows, fn($r) => $r['delta_pct'] !== null));
		usort($both, fn($a, $b) => $b['delta_abs'] <=> $a['delta_abs']);
		$gainers = array_slice(array_values(array_filter($both, fn($r) => $r['delta_abs'] > 0)), 0, 5);
		$losers = array_slice(array_reverse(array_values(array_filter($both, fn($r) => $r['delta_abs'] < 0))), 0, 5);

		// Evolución diaria de los 5 primeros y ventas de los 5 primeros por sucursal
		$top5 = array_map(fn($r) => (string) $r['id'], array_slice($topNet, 0, 5));
		$evo = [];
		if ($top5)
		{
			$in5 = array_flip($top5);
			foreach (array_filter($c['L'], fn($l) => isset($in5[(string) $l['product']])) as $l)
			{
				$evo[(string) $l['product']][$l['day']] = round(($evo[(string) $l['product']][$l['day']] ?? 0) + $l['net'], 2);
			}
			foreach ($this->ArchiveProducts($f, $from, $to, 'day', $top5) as $pid => $ds)
			{
				foreach ($ds as $d => $v)
				{
					$evo[(string) $pid][$d] = round(($evo[(string) $pid][$d] ?? 0) + $v, 2);
				}
			}
		}
		$series = [];
		$branchMatrix = [];
		$bIds = [];
		foreach ($top5 as $pid)
		{
			$series[$pid] = array_map(fn($d) => $evo[$pid][$d] ?? 0, $days);
			foreach ($byBranch[$pid] ?? [] as $bid => $v)
			{
				$bIds[$bid] = true;
			}
		}
		$branches = [];
		foreach (array_keys($bIds) as $bid)
		{
			$branches[] = ['id' => $bid, 'name' => $bn[$bid] ?? ('Sucursal ' . $bid)];
		}
		usort($branches, fn($a, $b) => strcmp($a['name'], $b['name']));
		foreach ($top5 as $pid)
		{
			$branchMatrix[$pid] = array_map(fn($b) => $byBranch[$pid][$b['id']] ?? 0, $branches);
		}
		return ['rows' => $sold, 'count' => count($sold), 'top_net' => $topNet, 'top_units' => $topUnits, 'gainers' => $gainers, 'losers' => $losers,
			'days' => $days, 'top5' => array_map('intval', $top5), 'series' => $series, 'branches' => $branches, 'branch_matrix' => $branchMatrix];
	}

	/* 4. Sucursales: ¿qué tienda vende más? */
	private function Branches(array $c): array
	{
		['f' => $f, 'from' => $from, 'to' => $to, 'days' => $days, 'cur' => $cur, 'goals' => $goals, 'branchNames' => $bn] = $c;
		$rows = [];
		foreach ($c['curBranch'] + $c['prevBranch'] as $id => $_)
		{
			$n = $c['curBranch'][$id]['net'] ?? 0.0;
			$p = $c['prevBranch'][$id]['net'] ?? 0.0;
			$t = $c['curBranch'][$id]['tickets'] ?? 0;
			$g = $goals['by_branch'][intval($id)] ?? null;
			$rows[] = ['id' => intval($id), 'name' => $bn[intval($id)] ?? ('Sucursal ' . $id), 'net' => $n, 'prev_net' => $p, 'delta_pct' => self::Delta($n, $p),
				'tickets' => $t, 'avg_ticket' => $t > 0 ? round($n / $t, 2) : null, 'share_pct' => self::Share($n, $cur['net']),
				'goal' => $g, 'goal_pct' => $g ? round($n / $g * 100, 1) : null];
		}
		usort($rows, fn($a, $b) => $b['net'] <=> $a['net']);
		foreach ($rows as $i => &$r)
		{
			$r['rank'] = $i + 1;
		}
		unset($r);

		// Matriz sucursal × día (≤ 31 días) o sucursal × día de la semana (períodos largos)
		$byDay = count($days) <= 31;
		$daily = self::Sum2($c['L'], 'branch', 'day');
		$heat = $byDay ? $daily : self::Sum2($c['L'], 'branch', 'dow');
		$cols = $byDay ? $days : ['1', '2', '3', '4', '5', '6', '0'];
		$evoIds = array_slice(array_column($rows, 'id'), 0, 5);
		$series = [];
		foreach ($rows as $r)
		{
			$series[$r['id']] = array_map(fn($d) => $daily[$r['id']][$d] ?? 0, $days);
		}
		return ['rows' => $rows, 'heat' => $heat, 'heat_cols' => $cols, 'heat_by_day' => $byDay, 'days' => $days, 'series' => $series, 'top5' => $evoIds,
			'has_goals' => $goals !== null && !empty($goals['by_branch'])];
	}

	/* 5. Historial de ventas: ¿cuándo vendemos más? */
	private function History(array $c): array
	{
		['f' => $f, 'from' => $from, 'to' => $to, 'days' => $days, 'cur' => $cur] = $c;
		$monthly = $c['monthly'];
		$years = [];
		foreach ($monthly as $m => $r)
		{
			$y = substr($m, 0, 4);
			$years[$y]['net'] = ($years[$y]['net'] ?? 0) + $r['net'];
			$years[$y]['months'][intval(substr($m, 5, 2)) - 1] = $r['net'];
		}
		$endYear = intval(substr($to, 0, 4));
		$overlay = [];
		// Años con ventas entre los tres últimos (si ninguno tiene, se muestran los tres sin información)
		$yearList = array_values(array_filter([$endYear - 2, $endYear - 1, $endYear], fn($y) => isset($years[(string) $y]))) ?: [$endYear - 2, $endYear - 1, $endYear];
		foreach ($yearList as $y)
		{
			$overlay[] = ['year' => $y, 'has_data' => isset($years[(string) $y]), 'net' => isset($years[(string) $y]) ? round($years[(string) $y]['net'], 2) : null,
				'months' => isset($years[(string) $y]) ? array_map(fn($i) => isset($years[(string) $y]['months'][$i]) ? round($years[(string) $y]['months'][$i], 2) : null, range(0, 11)) : null];
		}

		// Interanual del período seleccionado
		$yaFrom = date('Y-m-d', strtotime("$from -1 year"));
		$yaTo = date('Y-m-d', strtotime("$to -1 year"));
		$ya = $this->Total($f, $yaFrom, $yaTo);

		// Mes de la fecha final frente al mismo tramo del mes anterior
		$mStart = substr($to, 0, 8) . '01';
		$dayN = intval(substr($to, 8, 2));
		$pmStart = date('Y-m-01', strtotime("$mStart -1 day"));
		$pmEnd = date('Y-m-d', min(strtotime($pmStart . ' +' . ($dayN - 1) . ' days'), strtotime(date('Y-m-t', strtotime($pmStart)))));
		$mtd = self::SumDays($c['cal'], $mStart, $to);
		$pmtd = self::SumDays($c['cal'], $pmStart, $pmEnd);

		$curDaily = $c['curDaily'];
		$best = null;
		foreach ($curDaily as $d => $r)
		{
			if ($best === null || $r['net'] > $best['net'])
			{
				$best = ['date' => $d, 'net' => $r['net']];
			}
		}
		$bestMonth = null;
		foreach ($monthly as $m => $r)
		{
			if ($bestMonth === null || $r['net'] > $bestMonth['net'])
			{
				$bestMonth = ['month' => $m, 'net' => $r['net']];
			}
		}

		// Calendario: 53 semanas hasta la fecha final (+ la misma semana del año anterior para los fines de semana)
		$calFrom = date('Y-m-d', strtotime("$to -370 days"));
		$cal = $c['cal'];
		$calDays = self::Days($calFrom, $to);
		$calendar = array_map(fn($d) => [$d, $cal[$d]['net'] ?? 0], $calDays);

		// Fines de semana: los 8 últimos completos hasta la fecha final y el mismo fin de semana del año anterior (364 días antes)
		$lastSun = date('w', strtotime($to)) === '0' ? $to : date('Y-m-d', strtotime('last sunday', strtotime($to)));
		$weekends = [];
		for ($i = 7; $i >= 0; $i--)
		{
			$sun = date('Y-m-d', strtotime("$lastSun -" . (7 * $i) . ' days'));
			$sat = date('Y-m-d', strtotime("$sun -1 day"));
			$weekends[] = ['sat' => $sat, 'sun' => $sun, 'net' => round(($cal[$sat]['net'] ?? 0) + ($cal[$sun]['net'] ?? 0), 2)];
		}
		$ly = self::SumDays($cal, date('Y-m-d', strtotime("$lastSun -365 days")), date('Y-m-d', strtotime("$lastSun -364 days")));
		$lastWe = end($weekends);
		$sameWeekendLy = ['sat' => date('Y-m-d', strtotime("$lastSun -365 days")), 'sun' => date('Y-m-d', strtotime("$lastSun -364 days")),
			'net' => $ly['tickets'] > 0 ? $ly['net'] : null];

		// Día de la semana (período seleccionado): total y promedio por día del calendario
		$dowNet = array_fill(0, 7, 0.0);
		$dowCount = array_fill(0, 7, 0);
		foreach ($days as $d)
		{
			$w = intval(date('w', strtotime($d)));
			$dowNet[$w] += $curDaily[$d]['net'] ?? 0;
			$dowCount[$w]++;
		}
		$order = [1, 2, 3, 4, 5, 6, 0];

		// Semanas (lunes) del período para la línea temporal semanal
		$weekly = [];
		foreach ($days as $d)
		{
			$wk = date('Y-m-d', strtotime('monday this week', strtotime($d)));
			$weekly[$wk] = round(($weekly[$wk] ?? 0) + ($curDaily[$d]['net'] ?? 0), 2);
		}

		return [
			'net' => $cur['net'], 'tickets' => $cur['tickets'],
			'yoy' => ['from' => $yaFrom, 'to' => $yaTo, 'net' => $ya['tickets'] > 0 ? $ya['net'] : null, 'pct' => $ya['tickets'] > 0 ? self::Delta($cur['net'], $ya['net']) : null],
			'mom' => ['label' => DailyFilters::PeriodLabel($mStart, $to), 'prev_label' => DailyFilters::PeriodLabel($pmStart, $pmEnd), 'net' => $mtd['net'], 'prev_net' => $pmtd['net'],
				'pct' => self::Delta($mtd['net'], $pmtd['net'])],
			'best_day' => $best, 'best_month' => $bestMonth,
			'daily_avg' => count($days) > 0 ? round($cur['net'] / count($days), 2) : null,
			'timeline' => [
				'day' => ['labels' => $days, 'net' => self::Series($days, $curDaily)],
				'week' => ['labels' => array_keys($weekly), 'net' => array_values($weekly)],
				'month' => ['labels' => array_keys($monthly), 'net' => array_values(array_map(fn($r) => $r['net'], $monthly))],
				'year' => ['labels' => array_map('strval', array_keys($years)), 'net' => array_values(array_map(fn($y) => round($y['net'], 2), $years))]
			],
			'years' => $overlay,
			'calendar' => $calendar,
			'weekday' => ['labels' => ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'],
				'net' => array_map(fn($w) => round($dowNet[$w], 2), $order),
				'avg' => array_map(fn($w) => $dowCount[$w] > 0 ? round($dowNet[$w] / $dowCount[$w], 2) : null, $order)],
			'weekends' => $weekends,
			'weekend_last' => $lastWe,
			'weekend_ly' => $sameWeekendLy,
			'weekend_ly_pct' => $sameWeekendLy['net'] !== null ? self::Delta($lastWe['net'], $sameWeekendLy['net']) : null
		];
	}

	/* 6. Rentabilidad comercial: ¿qué ventas generan más utilidad bruta? */
	private function Profitability(array $c): array
	{
		['f' => $f, 'to' => $to, 'days' => $days, 'cur' => $cur, 'prev' => $prev, 'depts' => $depts, 'branchNames' => $bn] = $c;
		$row = function (string $name, array $r) {
			$m = self::Margin($r['net'], $r['cogs']);
			return ['name' => $name, 'net' => $r['net'], 'cogs' => $r['cogs'], 'profit' => $m === null ? null : round($r['net'] - $r['cogs'], 2), 'margin_pct' => $m];
		};
		$byDept = [];
		foreach ($c['curDept'] as $k => $r)
		{
			$byDept[] = $row($depts[$k]['name'] ?? $k, $r) + ['color' => $depts[$k]['color'] ?? '#64748b'];
		}
		$byBranch = [];
		foreach ($c['curBranch'] as $id => $r)
		{
			$byBranch[] = $row($bn[intval($id)] ?? ('Sucursal ' . $id), $r);
		}
		$sortProfit = fn($a, $b) => ($b['profit'] ?? -INF) <=> ($a['profit'] ?? -INF);
		usort($byDept, $sortProfit);
		usort($byBranch, $sortProfit);

		$names = [];
		if ($c['curProduct'])
		{
			$st = $this->Db()->query('SELECT id, name, sku FROM products WHERE id IN (' . implode(',', array_map('intval', array_keys($c['curProduct']))) . ')');
			foreach ($st->fetchAll(\PDO::FETCH_ASSOC) as $p)
			{
				$names[$p['id']] = $p;
			}
		}
		$products = [];
		foreach ($c['curProduct'] as $id => $r)
		{
			if ($r['net'] <= 0 || (string) $id === '0')
			{
				continue;
			}
			$products[] = $row($names[$id]['name'] ?? ('Producto ' . $id), $r) + ['sku' => (string) ($names[$id]['sku'] ?? ''), 'units' => $r['units']];
		}
		usort($products, $sortProfit);

		$daily = array_map(function ($d) use ($c) {
			$r = $c['curDaily'][$d] ?? ['net' => 0, 'cogs' => 0];
			return ['net' => $r['net'], 'cogs' => $r['cogs'], 'margin_pct' => self::Margin($r['net'], $r['cogs'])];
		}, $days);
		$monthly = $c['monthly'];

		$m = self::Margin($cur['net'], $cur['cogs']);
		$pm = self::Margin($prev['net'], $prev['cogs']);
		return [
			'net' => $cur['net'], 'cogs' => $cur['cogs'], 'profit' => $m === null ? null : round($cur['net'] - $cur['cogs'], 2), 'margin_pct' => $m,
			'prev_profit' => $pm === null ? null : round($prev['net'] - $prev['cogs'], 2), 'prev_margin_pct' => $pm,
			'margin_delta_pp' => ($m !== null && $pm !== null) ? round($m - $pm, 1) : null,
			'reliable' => $cur['net'] <= 0 || $m !== null,
			'by_department' => $byDept, 'by_branch' => $byBranch,
			'top_products' => array_slice($products, 0, 15),
			'scatter' => array_map(fn($p) => ['name' => $p['name'], 'net' => $p['net'], 'margin_pct' => $p['margin_pct']], array_slice(array_filter($products, fn($p) => $p['margin_pct'] !== null), 0, 150)),
			'daily' => ['days' => $days, 'net' => array_column($daily, 'net'), 'cogs' => array_column($daily, 'cogs'), 'margin_pct' => array_column($daily, 'margin_pct')],
			'monthly' => ['labels' => array_keys($monthly), 'net' => array_values(array_map(fn($r) => $r['net'], $monthly)),
				'profit' => array_values(array_map(fn($r) => self::Margin($r['net'], $r['cogs']) === null ? null : round($r['net'] - $r['cogs'], 2), $monthly)),
				'margin_pct' => array_values(array_map(fn($r) => self::Margin($r['net'], $r['cogs']), $monthly))]
		];
	}
}
