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
	private bool $lotTable = false;
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
			foreach (DailyMetricsService::Departments() as $k => $d)
			{
				if (!empty($d[2]))
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
			0 AS product, a.net, a.net AS gross, a.cogs, a.units, a.tickets AS tk, a.branch_tickets AS tkb FROM daily_sales_archive a WHERE $w")->fetchAll(\PDO::FETCH_ASSOC);
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
	private function Agg(array $f, string $from, string $to, array $groups = [], string $having = '', ?string $untilTime = null): array
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
			// $untilTime: el último día solo hasta esa hora (comparar el día en curso contra el mismo tramo horario)
			$st->execute([':f' => $tFrom . ' 00:00:00', ':t' => $untilTime === null ? date('Y-m-d', strtotime("$to +1 day")) . ' 00:00:00' : "$to $untilTime"]);
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

	/**
	 * Venta bruta (antes del descuento del ticket) y neta por día, canal y medio de pago, en una lectura agregada.
	 * Canal: pedido de cliente vinculado a la venta (delivery | pickup) o «store» (caja). Los días del archivo histórico
	 * no tienen canal, medio de pago ni descuento: se agregan como «archive» con bruto = neto.
	 */
	private function Mix(array $f, string $from, string $to, array $lines = []): array
	{
		$branch = DailyFilters::BranchSql($f, 's.branch_id');
		$sql = "WITH t AS (SELECT si.sale_id, SUM(si.subtotal) AS ls FROM sale_items si JOIN sales s ON s.id = si.sale_id
				WHERE s.status = 'completed' AND s.created_timestamp >= :f AND s.created_timestamp < :t AND $branch GROUP BY si.sale_id)
			SELECT date(s.created_timestamp) AS day, IFNULL(co.ch, 'store') AS channel, IFNULL(s.payment_method, 'other') AS pay,
				SUM(si.subtotal * CASE WHEN t.ls > 0 THEN (s.total_amount - s.tax_amount) / t.ls ELSE 1 END) AS net, SUM(si.subtotal) AS gross
			FROM sale_items si JOIN sales s ON s.id = si.sale_id JOIN t ON t.sale_id = s.id JOIN products p ON p.id = si.product_id
				LEFT JOIN (SELECT sale_id, MIN(delivery_type) AS ch FROM customer_orders WHERE sale_id IS NOT NULL GROUP BY sale_id) co ON co.sale_id = s.id
			WHERE s.status = 'completed' AND s.created_timestamp >= :f AND s.created_timestamp < :t AND $branch AND " . DailyFilters::ProductSql($f) . '
			GROUP BY day, channel, pay';
		$tFrom = max($from, $this->Cutover());
		$rows = [];
		if ($tFrom <= $to)
		{
			$st = $this->Db()->prepare($sql);
			$st->execute([':f' => $tFrom . ' 00:00:00', ':t' => date('Y-m-d', strtotime("$to +1 day")) . ' 00:00:00']);
			$rows = $st->fetchAll(\PDO::FETCH_ASSOC);
		}
		$arch = [];
		foreach ($lines as $l)
		{
			if ($l['sale'] === null)
			{
				$arch[$l['day']] = ($arch[$l['day']] ?? 0) + $l['net'];
			}
		}
		foreach ($arch as $d => $v)
		{
			$rows[] = ['day' => $d, 'channel' => 'archive', 'pay' => 'archive', 'net' => $v, 'gross' => $v];
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

	/** Primer día leído de ventas transaccionales ('' = sin archivo histórico) */
	public function ArchiveCutover(): string
	{
		return $this->Cutover();
	}

	/** Venta neta y tickets de un período con la definición única de los informes (cierres comerciales del Historial) */
	public function PeriodTotal(array $f, string $from, string $to): array
	{
		self::$deptFiltered = $f['department'] !== null;
		return $this->Total($f, $from, $to);
	}

	/** Líneas de venta del período (una lectura) para agregarlas en memoria por cualquier dimensión */
	private function Lines(array $f, string $from, string $to): array
	{
		$branch = DailyFilters::BranchSql($f, 's.branch_id');
		$sql = "WITH t AS (SELECT si.sale_id, SUM(si.subtotal) AS ls FROM sale_items si JOIN sales s ON s.id = si.sale_id
				WHERE s.status = 'completed' AND s.created_timestamp >= :f AND s.created_timestamp < :t AND $branch GROUP BY si.sale_id)
			SELECT s.id AS sale, date(s.created_timestamp) AS day, CAST(strftime('%H', s.created_timestamp) AS INTEGER) AS hour,
				CAST(strftime('%w', s.created_timestamp) AS INTEGER) AS dow, s.branch_id AS branch, " . $this->DeptCase() . " AS dept, p.id AS product,
				si.subtotal * CASE WHEN t.ls > 0 THEN (s.total_amount - s.tax_amount) / t.ls ELSE 1 END AS net, si.subtotal AS gross, si.total_cost AS cogs, si.amount AS units
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

	/** Agregado en memoria de Lines(), con la misma forma que Agg() (g0, g1, net, gross, cogs, units, tickets) */
	private static function Group(array $lines, array $groups = []): array
	{
		$g0 = $groups[0] ?? null;
		$g1 = $groups[1] ?? null;
		$net = $gross = $cogs = $units = $sales = $keys = $extra = [];
		$tkField = in_array('dept', $groups, true) || self::$deptFiltered ? 'tk' : 'tkb';
		foreach ($lines as $l)
		{
			$key = $g0 === null ? '' : ($g1 === null ? $l[$g0] : $l[$g0] . '|' . $l[$g1]);
			if (!isset($net[$key]))
			{
				$net[$key] = $gross[$key] = $cogs[$key] = $units[$key] = 0.0;
				$keys[$key] = $g0 === null ? [] : ($g1 === null ? ['g0' => (string) $l[$g0]] : ['g0' => (string) $l[$g0], 'g1' => (string) $l[$g1]]);
			}
			$net[$key] += $l['net'];
			$gross[$key] += ($l['gross'] ?? $l['net']);
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
			$out[] = $r + ['net' => round($net[$key], 2), 'gross' => round($gross[$key], 2), 'cogs' => round($cogs[$key], 2), 'units' => round($units[$key], 2), 'tickets' => count($sales[$key] ?? []) + ($extra[$key] ?? 0)];
		}
		if (!$groups && !$out)
		{
			$out[] = ['net' => 0.0, 'gross' => 0.0, 'cogs' => 0.0, 'units' => 0.0, 'tickets' => 0];
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
		$subDeptFiltered = $f['group_id'] !== null || $f['subcategory'] !== null || $f['product'] !== null;

		self::$deptFiltered = $f['department'] !== null;
		$cut = $this->Cutover();
		$L = $this->Lines($f, $from, $to);
		$PL = $this->Lines($f, $prevFrom, $prevTo);
		$cur = self::Group($L)[0];
		$prev = self::Group($PL)[0];
		$goals = $this->Goals($f, $days, $productFiltered);
		$deptTargets = $subDeptFiltered ? [] : $this->DeptTargets($f, $days);

		$branchNames = [];
		foreach ($this->Db()->query('SELECT id, name FROM branches')->fetchAll(\PDO::FETCH_ASSOC) as $b)
		{
			$branchNames[intval($b['id'])] = $b['name'];
		}
		$depts = [];
		foreach (DailyMetricsService::Departments() as $k => $d)
		{
			$depts[$k] = ['key' => $k, 'name' => $d[0], 'color' => $d[1], 'code' => $d[3]];
		}

		$ctx = compact('f', 'from', 'to', 'days', 'prevFrom', 'prevTo', 'prevLabel', 'compareWith', 'cur', 'prev', 'goals', 'branchNames', 'depts', 'L', 'deptTargets');
		$ctx['curDaily'] = self::GroupBy($L, 'day');
		$ctx['prevDaily'] = self::GroupBy($PL, 'day');
		$ctx['curBranch'] = self::GroupBy($L, 'branch');
		$ctx['prevBranch'] = self::GroupBy($PL, 'branch');
		$ctx['curDept'] = self::GroupBy($L, 'dept');
		$ctx['prevDept'] = self::GroupBy($PL, 'dept');
		$ctx['curProduct'] = $this->MergeArchiveProducts(self::GroupBy($L, 'product'), $f, $from, $to);
		$ctx['prevProduct'] = $this->MergeArchiveProducts(self::GroupBy($PL, 'product'), $f, $prevFrom, $prevTo);
		$ctx['prevProductDetail'] = !isset($ctx['prevProduct']['0']) && !isset($ctx['prevProduct'][0]);
		// Agregado mensual por departamento (últimos 12 meses hasta $to) para evolución mensual en detalle departamental
		$m12From = date('Y-m-01', strtotime(substr($to, 0, 7) . '-01 -11 months'));
		$ctx['monthlyDept'] = [];
		foreach ($this->Agg($f, $m12From, $to, ['month', 'dept']) as $mr)
		{
			$ctx['monthlyDept'][(string) $mr['g0']][(string) $mr['g1']] = round(floatval($mr['net']), 2);
		}
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

		$dashboard = $this->Dashboard($ctx);
		$products = $this->Products($ctx);
		$departments = $this->Departments($ctx);
		unset($L, $PL);
		// Resumen del tablero: los cinco primeros de la pestaña Productos (misma fuente, sin otra consulta)
		$dashboard['top5'] = array_map(fn($r) => array_intersect_key($r, array_flip(['id', 'name', 'sku', 'dept', 'units', 'net', 'share_pct', 'delta_pct'])), array_slice($products['rows'], 0, 5));

		return [
			'period' => ['from' => $from, 'to' => $to, 'days' => $len, 'label' => DailyFilters::PeriodLabel($from, $to),
				'prev_from' => $prevFrom, 'prev_to' => $prevTo, 'prev_label' => DailyFilters::PeriodLabel($prevFrom, $prevTo), 'prev_name' => $prevLabel,
				'compare_with' => $compareWith],
			'product_filtered' => $productFiltered,
			'archive' => ['cutover' => $cut ?: null, 'in_period' => $cut !== '' && $from < $cut, 'in_prev' => $cut !== '' && $prevFrom < $cut,
				'usable' => $f['group_id'] === null && $f['subcategory'] === null && $f['product'] === null],
			'consistency' => $this->Consistency($ctx),
			'dashboard' => $dashboard,
			'departments' => $departments,
			'products' => $products,
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

		// Comparaciones de igual duración. Día, semana y mes se leen del agregado diario; el año anterior, de su propio rango
		$shift = fn($d, $n) => date('Y-m-d', strtotime("$d $n"));
		// Si la fecha final es hoy, el último día de referencia se corta a la misma hora (día en curso vs. mismo tramo)
		$cutTime = $to === date('Y-m-d') ? date('H:i:s') : null;
		$cmp = function ($key, $label, $a, $b, $pa, $pb, $prevLabel) use ($c, $f, $cutTime) {
			$x = self::SumDays($c['cal'], $a, $b);
			$y = $cutTime === null ? self::SumDays($c['cal'], $pa, $pb) : self::SumDays($c['cal'], $pa, date('Y-m-d', strtotime("$pb -1 day")));
			if ($cutTime !== null)
			{
				$t = $this->Agg($f, $pb, $pb, [], '', $cutTime)[0];
				$y['net'] = round($y['net'] + $t['net'], 2);
				$y['tickets'] += $t['tickets'];
			}
			$pn = $y['tickets'] > 0 ? $y['net'] : null;
			return ['key' => $key, 'label' => $label, 'from' => $a, 'to' => $b, 'prev_from' => $pa, 'prev_to' => $pb, 'prev_label' => $prevLabel, 'cut_time' => $cutTime ? substr($cutTime, 0, 5) : null,
				'net' => $x['net'], 'prev_net' => $pn, 'delta_pct' => self::Delta($x['net'], $pn), 'days' => count(self::Days($a, $b)), 'prev_days' => count(self::Days($pa, $pb))];
		};
		$monthStart = substr($to, 0, 8) . '01';
		$pmStart = date('Y-m-01', strtotime("$monthStart -1 month"));
		$pmEnd = min($shift($pmStart, '+' . (intval(substr($to, 8, 2)) - 1) . ' days'), date('Y-m-t', strtotime($pmStart)));
		$comparisons = [
			$cmp('day_vs_yesterday', 'Día vs. ayer', $to, $to, $shift($to, '-1 day'), $shift($to, '-1 day'), 'Ayer'),
			$cmp('day_vs_last_week', 'Día vs. mismo día semana anterior', $to, $to, $shift($to, '-7 days'), $shift($to, '-7 days'), 'Mismo día semana anterior'),
			$cmp('wtd', 'Semana a la fecha vs. semana anterior', $weekStart, $to, $shift($weekStart, '-7 days'), $shift($to, '-7 days'), 'Mismos días semana anterior'),
			$cmp('mtd', 'Mes a la fecha vs. mes anterior', $monthStart, $to, $pmStart, $pmEnd, 'Mismos días mes anterior')
		];
		$yaFrom = $shift($from, '-1 year');
		$yaTo = $shift($to, '-1 year');
		// Período de referencia con el último día cortado a la hora actual cuando el período termina hoy
		$refNet = function ($pa, $pb) use ($f, $cutTime) {
			$t = $this->Total($f, $pa, $cutTime === null ? $pb : date('Y-m-d', strtotime("$pb -1 day")));
			if ($cutTime !== null)
			{
				$u = $this->Agg($f, $pb, $pb, [], '', $cutTime)[0];
				$t = ['net' => $t['net'] + $u['net'], 'tickets' => $t['tickets'] + $u['tickets']];
			}
			return $t['tickets'] > 0 ? round($t['net'], 2) : null;
		};
		$prevNet = $cutTime === null ? ($prev['tickets'] > 0 ? $prev['net'] : null) : $refNet($c['prevFrom'], $c['prevTo']);
		if ($c['prevFrom'] !== $yaFrom)
		{
			$comparisons[] = ['key' => 'period', 'label' => 'Período vs. período anterior', 'from' => $from, 'to' => $to, 'prev_from' => $c['prevFrom'], 'prev_to' => $c['prevTo'],
				'prev_label' => 'Período anterior', 'net' => $cur['net'], 'prev_net' => $prevNet, 'delta_pct' => self::Delta($cur['net'], $prevNet), 'days' => count($days), 'prev_days' => count($prevDays)];
		}
		$yaNet = $refNet($yaFrom, $yaTo);
		$comparisons[] = ['key' => 'year_ago', 'label' => 'Período vs. mismo período año anterior', 'from' => $from, 'to' => $to, 'prev_from' => $yaFrom, 'prev_to' => $yaTo,
			'prev_label' => 'Mismo período año anterior', 'net' => $cur['net'], 'prev_net' => $yaNet, 'delta_pct' => self::Delta($cur['net'], $yaNet), 'days' => count($days), 'prev_days' => count(self::Days($yaFrom, $yaTo))];

		// Bruto, descuentos, canal y medio de pago (una lectura agregada por período)
		$mixRows = $this->Mix($f, $from, $to, $c['L']);
		$gross = round(array_sum(array_column($mixRows, 'gross')), 2);
		$pm = $this->Mix($f, $c['prevFrom'], $c['prevTo']);
		// Días del archivo en el período anterior: bruto = neto (sin descuento registrado)
		$prevGross = round(array_sum(array_column($pm, 'gross')) + ($prev['net'] - array_sum(array_column($pm, 'net'))), 2);
		$channelNames = ['store' => 'Tienda (caja)', 'delivery' => 'Pedido a domicilio', 'pickup' => 'Pedido para recoger', 'archive' => 'Archivo histórico (sin canal)'];
		$payNames = ['cash' => 'Efectivo', 'card' => 'Tarjeta', 'digital' => 'Billetera digital', 'transfer' => 'Transferencia', 'credit' => 'Crédito', 'other' => 'Otro', 'archive' => 'Archivo histórico (sin medio)'];
		$mix = function (string $dim, array $names) use ($mixRows, $days) {
			$by = [];
			foreach ($mixRows as $r)
			{
				$by[$r[$dim]][$r['day']] = ($by[$r[$dim]][$r['day']] ?? 0) + $r['net'];
			}
			$out = [];
			foreach ($by as $k => $ds)
			{
				$out[] = ['key' => (string) $k, 'name' => $names[$k] ?? ucfirst((string) $k), 'net' => round(array_sum($ds), 2), 'series' => array_map(fn($d) => round($ds[$d] ?? 0, 2), $days)];
			}
			usort($out, fn($a, $b) => $b['net'] <=> $a['net']);
			$sum = array_sum(array_column($out, 'net'));
			foreach ($out as &$o)
			{
				$o['share_pct'] = self::Share($o['net'], $sum);
			}
			unset($o);
			return $out;
		};

		// Alertas comerciales: solo desviaciones de venta, con el umbral explícito en cada mensaje
		$alerts = [];
		$money = fn($v) => '$' . number_format($v, 0);
		if (!empty($goals['total']) && ($gp = round($cur['net'] / $goals['total'] * 100, 1)) < 95)
		{
			$alerts[] = ['severity' => $gp < 85 ? 'danger' : 'warning', 'kind' => 'goal', 'title' => 'Cumplimiento de meta: ' . number_format($gp, 1) . ' %',
				'detail' => 'Faltan ' . $money($goals['total'] - $cur['net']) . ' para la meta del período (umbral 95 %).', 'tab' => 'sucursales'];
		}
		foreach ($comparisons as $cp)
		{
			// Día vs. ayer no alerta: el día de la semana distorsiona; se usa el mismo día de la semana anterior
			$limit = $cp['key'] === 'day_vs_last_week' ? -15.0 : -10.0;
			if ($cp['key'] !== 'day_vs_yesterday' && $cp['delta_pct'] !== null && $cp['delta_pct'] <= $limit)
			{
				$alerts[] = ['severity' => $cp['delta_pct'] <= 2 * $limit ? 'danger' : 'warning', 'kind' => $cp['key'], 'title' => $cp['label'] . ': ' . number_format($cp['delta_pct'], 1) . ' %',
					'detail' => $money($cp['net']) . ' frente a ' . $money($cp['prev_net']) . ' (' . mb_strtolower($cp['prev_label']) . '; umbral ' . number_format($limit, 0) . ' %).', 'tab' => 'detalle'];
			}
		}
		$avgTicket = $cur['tickets'] > 0 ? $cur['net'] / $cur['tickets'] : null;
		$prevAvgTicket = $prev['tickets'] > 0 ? $prev['net'] / $prev['tickets'] : null;
		if (($d = self::Delta($avgTicket, $prevAvgTicket)) !== null && $d <= -10)
		{
			$alerts[] = ['severity' => $d <= -20 ? 'danger' : 'warning', 'kind' => 'avg_ticket', 'title' => 'Ticket promedio: ' . number_format($d, 1) . ' %',
				'detail' => '$' . number_format($avgTicket, 2) . ' frente a $' . number_format($prevAvgTicket, 2) . ' del ' . $c['prevLabel'] . ' (umbral −10 %).', 'tab' => 'detalle'];
		}
		if (($d = self::Delta($cur['tickets'], $prev['tickets'])) !== null && $d <= -10)
		{
			$alerts[] = ['severity' => $d <= -20 ? 'danger' : 'warning', 'kind' => 'tickets', 'title' => 'Transacciones: ' . number_format($d, 1) . ' %',
				'detail' => number_format($cur['tickets']) . ' frente a ' . number_format($prev['tickets']) . ' del ' . $c['prevLabel'] . ' (umbral −10 %).', 'tab' => 'detalle'];
		}
		foreach (['branch' => ['curBranch', 'prevBranch', 'sucursales', 'Sucursal'], 'dept' => ['curDept', 'prevDept', 'departamentos', 'Departamento']] as $dim => [$ck, $pk, $tab, $noun])
		{
			$drops = [];
			foreach ($c[$ck] as $k => $r)
			{
				$p = $c[$pk][$k]['net'] ?? null;
				// Solo unidades con peso (≥ 2 % de la venta anterior) para no alertar por montos marginales
				if (($d = self::Delta($r['net'], $p)) !== null && $d <= -15 && $p >= 0.02 * $prev['net'])
				{
					$drops[] = [$k, $d, $r['net'], $p];
				}
			}
			usort($drops, fn($a, $b) => $a[1] <=> $b[1]);
			foreach (array_slice($drops, 0, 3) as [$k, $d, $n, $p])
			{
				$name = $dim === 'branch' ? ($c['branchNames'][intval($k)] ?? 'Sucursal ' . $k) : ($c['depts'][$k]['name'] ?? $k);
				$alerts[] = ['severity' => $d <= -30 ? 'danger' : 'warning', 'kind' => $dim, 'title' => $noun . ' ' . $name . ': ' . number_format($d, 1) . ' %',
					'detail' => $money($n) . ' frente a ' . $money($p) . ' del ' . $c['prevLabel'] . ' (umbral −15 %).', 'tab' => $tab];
			}
		}
		usort($alerts, fn($a, $b) => ($a['severity'] === 'danger' ? 0 : 1) <=> ($b['severity'] === 'danger' ? 0 : 1));

		return [
			'gross' => $gross, 'discounts' => round($gross - $cur['net'], 2), 'discount_pct' => self::Share($gross - $cur['net'], $gross),
			'prev_gross' => $prevGross, 'prev_discounts' => round($prevGross - $prev['net'], 2),
			'daily_avg' => $cur['tickets'] > 0 ? round($cur['net'] / count($days), 2) : null,
			'prev_daily_avg' => $prev['tickets'] > 0 ? round($prev['net'] / count($prevDays), 2) : null,
			'comparisons' => $comparisons,
			'cut_time' => $cutTime === null ? null : substr($cutTime, 0, 5),
			'channels' => $mix('channel', $channelNames),
			'payments' => $mix('pay', $payNames),
			'days' => $days,
			'alerts' => $alerts,
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

	/** Ventas por departamento de [a, b]; con $cutTime el último día solo hasta esa hora. [clave => net, cogs, units, tickets] */
	private function Window(array $f, string $a, string $b, ?string $cutTime = null, string $g = 'dept'): array
	{
		$out = [];
		$parts = $cutTime === null ? [[$a, $b, null]] : array_merge($a < $b ? [[$a, date('Y-m-d', strtotime("$b -1 day")), null]] : [], [[$b, $b, $cutTime]]);
		foreach ($parts as [$x, $y, $t])
		{
			foreach ($this->Agg($f, $x, $y, [$g], '', $t) as $r)
			{
				foreach (['net', 'cogs', 'units', 'tickets'] as $m)
				{
					$out[(string) $r['g0']][$m] = round(($out[(string) $r['g0']][$m] ?? 0) + $r[$m], 2);
				}
			}
		}
		return $out;
	}

	/**
	 * Crecimiento semanal, mensual y anual por departamento o sucursal: ventanas de igual duración que terminan en la fecha final;
	 * si el período termina hoy, la ventana anterior se corta a la misma hora. Devuelve [definición de ventanas, [actual, anterior] por ventana].
	 */
	private function GrowthWindows(array $c, string $g, array $curPeriod, ?string $cutTime): array
	{
		['f' => $f, 'from' => $from, 'to' => $to] = $c;
		$shift = fn($d, $n) => date('Y-m-d', strtotime("$d $n"));
		$monthStart = substr($to, 0, 8) . '01';
		$pmStart = date('Y-m-01', strtotime("$monthStart -1 month"));
		$pmEnd = min($shift($pmStart, '+' . (intval(substr($to, 8, 2)) - 1) . ' days'), date('Y-m-t', strtotime($pmStart)));
		$growth = [
			'week' => ['label' => 'Semanal', 'desc' => 'Últimos 7 días vs. los 7 días anteriores', 'from' => $shift($to, '-6 days'), 'to' => $to, 'prev_from' => $shift($to, '-13 days'), 'prev_to' => $shift($to, '-7 days')],
			'month' => ['label' => 'Mensual', 'desc' => 'Mes a la fecha vs. mismos días del mes anterior', 'from' => $monthStart, 'to' => $to, 'prev_from' => $pmStart, 'prev_to' => $pmEnd],
			'year' => ['label' => 'Anual', 'desc' => 'Período vs. mismo período del año anterior', 'from' => $from, 'to' => $to, 'prev_from' => $shift($from, '-1 year'), 'prev_to' => $shift($to, '-1 year')]
		];
		$gData = [];
		foreach ($growth as $k => &$w)
		{
			$curW = $k === 'year' ? $curPeriod : $this->Window($f, $w['from'], $w['to'], null, $g);
			$prevW = $this->Window($f, $w['prev_from'], $w['prev_to'], $cutTime, $g);
			$gData[$k] = [$curW, $prevW];
			$w['days'] = count(self::Days($w['from'], $w['to']));
			$w['prev_days'] = count(self::Days($w['prev_from'], $w['prev_to']));
			$w['cut_time'] = $cutTime ? substr($cutTime, 0, 5) : null;
			$w['net'] = round(array_sum(array_column($curW, 'net')), 2);
			$w['prev_net'] = round(array_sum(array_column($prevW, 'net')), 2);
			$w['delta_pct'] = self::Delta($w['net'], $w['prev_net'] > 0 ? $w['prev_net'] : null);
		}
		unset($w);
		return [$growth, $gData];
	}

	/* 2. Ventas por departamento: ¿qué departamento vende más y cómo rinde comercialmente? */
	private function Departments(array $c): array
	{
		['f' => $f, 'from' => $from, 'to' => $to, 'days' => $days, 'cur' => $cur, 'depts' => $depts, 'branchNames' => $bn] = $c;
		$compareWith = $c['compareWith'] ?? 'prev_period';
		// Si el período termina hoy, el último día de cada referencia se corta a la misma hora (no se compara un día parcial con uno completo)
		$cutTime = $to === date('Y-m-d') ? date('H:i:s') : null;
		$prevDept = $cutTime === null ? $c['prevDept'] : $this->Window($f, $c['prevFrom'], $c['prevTo'], $cutTime);
		$prevTotal = round(array_sum(array_column($prevDept, 'net')), 2);
		// Con filtro de departamento, categoría o producto solo se listan los departamentos con venta (los demás no aplican)
		$showEmpty = $f['department'] === null && $f['group_id'] === null && $f['subcategory'] === null && $f['product'] === null;
		// El ticket del departamento solo se atribuye bien con el ticket completo del departamento (sin filtro de categoría o producto)
		$ticketOk = $f['group_id'] === null && $f['subcategory'] === null && $f['product'] === null;

		// Crecimiento semanal, mensual y anual
		[$growth, $gData] = $this->GrowthWindows($c, 'dept', $c['curDept'], $cutTime);
		// Referencias explícitas: período anterior contiguo y mismo período del año anterior (sin inventar LY cuando no hay datos)
		if ($compareWith === 'year_ago')
		{
			$pEnd = date('Y-m-d', strtotime("$from -1 day"));
			$pStart = date('Y-m-d', strtotime("$pEnd -" . (count($days) - 1) . ' days'));
			$prevPeriodDept = $this->Window($f, $pStart, $pEnd, $cutTime);
			$lyDept = $prevDept;
		}
		else
		{
			$prevPeriodDept = $prevDept;
			$lyDept = $gData['year'][1];
		}
		$lyTotal = round(array_sum(array_column($lyDept, 'net')), 2);
		$lyHasData = $lyTotal > 0;
		$prevPeriodTotal = round(array_sum(array_column($prevPeriodDept, 'net')), 2);

		// Subcategorías, SKUs, Top 5 y Bottom 5 por departamento desde la misma lectura de ventas
		$deptNets = [];
		foreach ($depts as $k => $d)
		{
			$deptNets[$k] = $c['curDept'][$k]['net'] ?? 0.0;
		}
		$drill = $this->DeptDrilldown($c, $deptNets);
		$origin = $this->DeptDataOrigin($c, $drill['has_demo_products']);

		// Etiquetas semanales y mensuales para evolución temporal
		$mNames = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
		$weekBuckets = [];
		foreach ($days as $idx => $d)
		{
			$wk = date('o-\WW', strtotime($d));
			if (!isset($weekBuckets[$wk]))
			{
				$weekBuckets[$wk] = ['key' => $wk, 'from' => $d, 'to' => $d, 'idx' => []];
			}
			$weekBuckets[$wk]['to'] = $d;
			$weekBuckets[$wk]['idx'][] = $idx;
		}
		$weekLabels = [];
		foreach ($weekBuckets as &$wb)
		{
			$fD = intval(substr($wb['from'], 8, 2));
			$fM = $mNames[max(0, intval(substr($wb['from'], 5, 2)) - 1)] ?? '';
			$tD = intval(substr($wb['to'], 8, 2));
			$tM = $mNames[max(0, intval(substr($wb['to'], 5, 2)) - 1)] ?? '';
			$wNum = intval(substr($wb['key'], -2));
			$rangeStr = $wb['from'] === $wb['to']
				? ($fD . ' ' . $fM)
				: ($fM === $tM ? ($fD . '–' . $tD . ' ' . $tM) : ($fD . ' ' . $fM . '–' . $tD . ' ' . $tM));
			$wb['label'] = "Sem $wNum ($rangeStr)";
			$weekLabels[] = $wb['label'];
		}
		unset($wb);
		$monthKeys = [];
		$monthLabels = [];
		$endMonth = substr($to, 0, 7);
		for ($mi = 11; $mi >= 0; $mi--)
		{
			$ym = date('Y-m', strtotime("$endMonth-01 -$mi months"));
			$monthKeys[] = $ym;
			$monthLabels[] = ($mNames[max(0, intval(substr($ym, 5, 2)) - 1)] ?? '') . ' ' . substr($ym, 2, 2);
		}

		// Matriz departamento × sucursal y evolución diaria por departamento
		$evo = self::Sum2($c['L'], 'dept', 'day');
		$heat = self::Sum2($c['L'], 'dept', 'branch');
		$branchIds = [];
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

		$rows = [];
		foreach ($depts as $k => $d)
		{
			$cd = $c['curDept'][$k] ?? null;
			$pd = $prevDept[$k] ?? null;
			$n = $cd['net'] ?? 0.0;
			$gNet = round($cd['gross'] ?? $n, 2);
			$discounts = round($gNet - $n, 2);
			$p = $pd['net'] ?? 0.0;
			if ($n <= 0 && ($k === 'otros' || !$showEmpty))
			{
				continue;
			}
			$units = round(floatval($cd['units'] ?? 0), 2);
			$margin = self::Margin($n, $cd['cogs'] ?? 0.0);
			$prevMargin = self::Margin($p, $pd['cogs'] ?? 0.0);
			$tk = $cd['tickets'] ?? 0;
			$ptk = $pd['tickets'] ?? 0;
			$avgTicket = $ticketOk && $tk > 0 ? round($n / $tk, 2) : null;
			$prevAvgTicket = $ticketOk && $ptk > 0 ? round($p / $ptk, 2) : null;
			$unitsPerTicket = $ticketOk && $tk > 0 ? round($units / $tk, 2) : null;
			$avgUnitPrice = $units > 0 ? round($n / $units, 2) : null;

			$ppNet = $prevPeriodDept[$k]['net'] ?? 0.0;
			$lyNet = $lyHasData ? round(floatval($lyDept[$k]['net'] ?? 0.0), 2) : null;
			$goalVal = $c['deptTargets'][$k]['total'] ?? null;

			$gr = [];
			foreach ($gData as $g => [$cw, $pw])
			{
				$gr[$g] = self::Delta($cw[$k]['net'] ?? 0.0, ($pw[$k]['net'] ?? 0) > 0 ? $pw[$k]['net'] : null);
			}

			// Acumulado diario y meta acumulada del departamento
			$dailySeries = array_map(fn($day) => round(floatval($evo[$k][$day] ?? 0), 2), $days);
			$cumSales = [];
			$cumGoal = [];
			$accS = 0.0;
			$accG = 0.0;
			$hasG = !empty($c['deptTargets'][$k]['daily']);
			foreach ($days as $di => $day)
			{
				$accS += $dailySeries[$di];
				$cumSales[] = round($accS, 2);
				if ($hasG)
				{
					$accG += floatval($c['deptTargets'][$k]['daily'][$day] ?? 0);
					$cumGoal[] = round($accG, 2);
				}
			}

			// Evolución semanal y mensual del departamento
			$weeklyVals = [];
			foreach ($weekBuckets as $wb)
			{
				$wSum = 0.0;
				foreach ($wb['idx'] as $idx)
				{
					$wSum += $dailySeries[$idx];
				}
				$weeklyVals[] = round($wSum, 2);
			}
			$monthlyVals = array_map(fn($ym) => round(floatval($c['monthlyDept'][$ym][$k] ?? 0.0), 2), $monthKeys);

			// Ventas por sucursal para este departamento
			$deptByBranch = [];
			foreach ($heat[$k] ?? [] as $bid => $bv)
			{
				$deptByBranch[] = ['id' => intval($bid), 'name' => $bn[intval($bid)] ?? ('Sucursal ' . $bid),
					'net' => round(floatval($bv), 2), 'share_dept_pct' => self::Share(floatval($bv), $n)];
			}
			usort($deptByBranch, fn($a, $b) => $b['net'] <=> $a['net'] ?: strcmp($a['name'], $b['name']));

			$dd = $drill['by_dept'][$k] ?? ['subcategories' => [], 'top5' => [], 'bottom5' => [], 'sku_count' => 0];
			$rows[] = $d + [
				'net' => $n, 'gross' => $gNet, 'discounts' => $discounts,
				'prev_net' => $p, 'delta_abs' => ($n > 0 || $p > 0) ? round($n - $p, 2) : 0.0,
				'delta_pct' => self::Delta($n, $p > 0 ? $p : null),
				'share_pct' => self::Share($n, $cur['net']), 'prev_share_pct' => self::Share($p, $prevTotal), 'no_sales' => $n <= 0,
				'prev_period_net' => round($ppNet, 2), 'prev_period_delta_abs' => ($n > 0 || $ppNet > 0) ? round($n - $ppNet, 2) : 0.0,
				'prev_period_delta_pct' => self::Delta($n, $ppNet > 0 ? $ppNet : null),
				'ly_net' => $lyNet, 'ly_delta_abs' => $lyNet !== null ? round($n - $lyNet, 2) : null,
				'ly_delta_pct' => ($lyNet !== null && $lyNet > 0) ? self::Delta($n, $lyNet) : null,
				'tickets' => $tk, 'units' => $units, 'units_per_ticket' => $unitsPerTicket, 'avg_unit_price' => $avgUnitPrice,
				'goal' => $goalVal,
				'goal_pct' => !empty($goalVal) ? round($n / $goalVal * 100, 1) : null,
				'goal_gap' => $goalVal !== null ? round($n - $goalVal, 2) : null,
				'cogs' => $cd['cogs'] ?? 0.0, 'gross_profit' => $margin !== null ? round($n - $cd['cogs'], 2) : null, 'margin_pct' => $margin,
				'prev_margin_pct' => $prevMargin, 'margin_delta_pp' => $margin !== null && $prevMargin !== null ? round($margin - $prevMargin, 1) : null,
				'avg_ticket' => $avgTicket, 'prev_avg_ticket' => $prevAvgTicket, 'avg_ticket_delta' => self::Delta($avgTicket, $prevAvgTicket),
				'growth' => $gr, 'sources' => DailyMetricsService::Departments()[$k][2] ?? null,
				'weekly' => $weeklyVals, 'monthly' => $monthlyVals,
				'cumulative' => $cumSales, 'goal_cumulative' => $hasG ? $cumGoal : null,
				'by_branch' => $deptByBranch,
				'subcategories' => $dd['subcategories'], 'top5_products' => $dd['top5'], 'bottom5_products' => $dd['bottom5'], 'sku_count' => $dd['sku_count']
			];
		}
		usort($rows, fn($a, $b) => $b['net'] <=> $a['net'] ?: strcmp($a['name'], $b['name']));
		// Posición en el ranking actual y en el anterior (solo departamentos con venta)
		$prevOrder = array_filter(array_map(fn($r) => $r['prev_net'], array_column($rows, null, 'key')), fn($v) => $v > 0);
		arsort($prevOrder);
		$prevRank = array_flip(array_keys($prevOrder));
		$i = 0;
		foreach ($rows as &$r)
		{
			$r['rank'] = $r['no_sales'] ? null : ++$i;
			$r['prev_rank'] = isset($prevRank[$r['key']]) ? $prevRank[$r['key']] + 1 : null;
			$r['rank_change'] = $r['rank'] !== null && $r['prev_rank'] !== null ? $prevRank[$r['key']] - $r['rank'] : null;
		}
		unset($r);

		// Ventas por hora de cada departamento; los días del archivo se reparten con la curva por hora de referencia
		$hourly = [];
		$archDept = [];
		foreach ($c['L'] as $l)
		{
			$hh = intval($l['hour']);
			if ($hh >= 0)
			{
				$hourly[$l['dept']][$hh] = ($hourly[$l['dept']][$hh] ?? 0) + $l['net'];
			}
			else
			{
				$archDept[$l['dept']] = ($archDept[$l['dept']] ?? 0) + $l['net'];
			}
		}
		if ($archDept && ($shares = $this->ArchiveHours()))
		{
			foreach ($archDept as $k => $v)
			{
				foreach ($shares as $hh => $sh)
				{
					$hourly[$k][intval($hh)] = ($hourly[$k][intval($hh)] ?? 0) + $v * $sh;
				}
			}
		}
		$hourSeries = [];
		$active = array_fill(0, 24, false);
		foreach ($hourly as $k => $byH)
		{
			$hourSeries[$k] = array_map(fn($hh) => round($byH[$hh] ?? 0, 2), range(0, 23));
			foreach ($byH as $hh => $v)
			{
				$active[$hh] = $active[$hh] || $v > 0;
			}
		}
		$firstH = array_search(true, $active, true);
		$lastH = $firstH === false ? false : 23 - array_search(true, array_reverse($active), true);

		// Conciliación: Σ departamentos frente al total comercial y origen de «Otros»
		$sumDept = round(array_sum(array_column($rows, 'net')), 2);
		$sumGross = round(array_sum(array_column($rows, 'gross')), 2);
		$sumDiscounts = round(array_sum(array_column($rows, 'discounts')), 2);
		$sumUnits = round(array_sum(array_column($rows, 'units')), 2);
		$goalRows = array_filter($rows, fn($r) => $r['goal'] !== null);
		$totalGoal = count($goalRows) > 0 ? round(array_sum(array_column($goalRows, 'goal')), 2) : null;
		$named = [];
		foreach (DailyMetricsService::Departments() as $d)
		{
			foreach ($d[2] ?? [] as $v)
			{
				$named[$v] = true;
			}
		}
		$unmapped = [];
		foreach ($this->Db()->query("SELECT IFNULL(TRIM(department), '') AS v, COUNT(*) AS n FROM products WHERE active = 1 GROUP BY 1 ORDER BY 1")->fetchAll(\PDO::FETCH_ASSOC) as $u)
		{
			if (!isset($named[$u['v']]))
			{
				$unmapped[] = ['value' => $u['v'] === '' ? null : $u['v'], 'products' => intval($u['n'])];
			}
		}

		// Evolución diaria, semanal y acumulado consolidado
		$series = [];
		foreach ($rows as $r)
		{
			if (!$r['no_sales'])
			{
				$series[$r['key']] = array_map(fn($d) => $evo[$r['key']][$d] ?? 0, $days);
			}
		}
		$cumTotal = [];
		$cumGoalTotal = [];
		$accTot = 0.0;
		$accGoalTot = 0.0;
		$hasAnyGoal = $totalGoal !== null;
		foreach ($days as $d)
		{
			$daySum = 0.0;
			$dayGoalSum = 0.0;
			foreach ($rows as $r)
			{
				$daySum += floatval($evo[$r['key']][$d] ?? 0);
				if ($r['goal'] !== null && isset($c['deptTargets'][$r['key']]['daily'][$d]))
				{
					$dayGoalSum += floatval($c['deptTargets'][$r['key']]['daily'][$d]);
				}
			}
			$accTot += $daySum;
			$cumTotal[] = round($accTot, 2);
			if ($hasAnyGoal)
			{
				$accGoalTot += $dayGoalSum;
				$cumGoalTotal[] = round($accGoalTot, 2);
			}
		}

		$branchTotals = [];
		foreach ($heat as $hr)
		{
			foreach ($hr as $bid => $v)
			{
				$branchTotals[$bid] = ($branchTotals[$bid] ?? 0) + $v;
			}
		}
		$other = 0.0;
		foreach ($rows as $r)
		{
			$other += $r['key'] === 'otros' ? $r['net'] : 0;
		}
		return ['rows' => $rows, 'days' => $days, 'series' => $series, 'heat' => $heat, 'heat_branches' => $branches,
			'branch_totals' => array_map(fn($v) => round($v, 2), $branchTotals),
			'weeks' => ['keys' => array_keys($weekBuckets), 'labels' => $weekLabels],
			'months' => ['keys' => $monthKeys, 'labels' => $monthLabels],
			'cumulative_total' => $cumTotal, 'goal_cumulative_total' => $hasAnyGoal ? $cumGoalTotal : null,
			'has_goals' => count($goalRows) > 0,
			'total_net' => $sumDept, 'total_gross' => $sumGross, 'total_discounts' => $sumDiscounts,
			'total_units' => $sumUnits, 'total_tickets' => intval($cur['tickets'] ?? 0),
			'total_goal' => $totalGoal,
			'goal_pct' => !empty($totalGoal) ? round($sumDept / $totalGoal * 100, 1) : null,
			'goal_gap' => $totalGoal !== null ? round($sumDept - $totalGoal, 2) : null,
			'prev_period_total' => $prevPeriodTotal,
			'prev_period_delta_pct' => self::Delta($sumDept, $prevPeriodTotal > 0 ? $prevPeriodTotal : null),
			'ly_has_data' => $lyHasData, 'ly_total' => $lyHasData ? $lyTotal : null,
			'ly_delta_abs' => $lyHasData ? round($sumDept - $lyTotal, 2) : null,
			'ly_delta_pct' => $lyHasData ? self::Delta($sumDept, $lyTotal) : null,
			'with_sales' => count(array_filter($rows, fn($r) => !$r['no_sales'])),
			'without_sales' => array_values(array_map(fn($r) => $r['name'], array_filter($rows, fn($r) => $r['no_sales']))),
			'cut_time' => $cutTime ? substr($cutTime, 0, 5) : null, 'prev_total' => $prevTotal,
			'ticket_attributable' => $ticketOk,
			'growth' => $growth,
			'hourly' => ['first' => $firstH === false ? null : $firstH, 'last' => $lastH === false ? null : $lastH, 'series' => $hourSeries,
				'archive_spread' => !empty($archDept)],
			'period_status' => [
				'days' => count($days),
				'prev_days' => count(self::Days($c['prevFrom'], $c['prevTo'])),
				'equivalent_length' => count($days) === count(self::Days($c['prevFrom'], $c['prevTo'])),
				'includes_today' => $to === date('Y-m-d'),
				'includes_future' => $to > date('Y-m-d'),
				'cut_time' => $cutTime ? substr($cutTime, 0, 5) : null,
				'compare_with' => $compareWith
			],
			'data_origin' => $origin,
			'reconciliation' => ['total' => $cur['net'], 'sum' => $sumDept, 'diff' => round($cur['net'] - $sumDept, 2), 'ok' => abs($cur['net'] - $sumDept) <= 1.0,
				'other_net' => round($other, 2), 'unmapped' => $unmapped],
			'config' => self::DeptConfig()];
	}

	/** Desglose jerárquico Departamento -> Subcategoría -> SKU más Top 5 / Bottom 5 productos sin inventar datos */
	private function DeptDrilldown(array $c, array $deptNets): array
	{
		$ids = array_values(array_filter(array_keys($c['curProduct'] + $c['prevProduct']), fn($id) => (string) $id !== '0'));
		$meta = [];
		$hasDemoProducts = false;
		if ($ids)
		{
			$sql = 'SELECT p.id, p.name, p.sku, COALESCE(p.is_demo, 0) AS is_demo, ' . $this->DeptCase() . ' AS dept_key, g.name AS category, NULLIF(TRIM(p.subcategory), \'\') AS subcat
				FROM products p LEFT JOIN product_groups g ON g.id = p.product_group_id WHERE p.id IN (' . implode(',', array_map('intval', $ids)) . ')';
			foreach ($this->Db()->query($sql)->fetchAll(\PDO::FETCH_ASSOC) as $m)
			{
				$meta[(string) $m['id']] = $m;
				if (!empty($m['is_demo']) && (($c['curProduct'][(string) $m['id']]['net'] ?? 0) > 0))
				{
					$hasDemoProducts = true;
				}
			}
		}

		$prodPosNet = [];
		$prodGross = [];
		$prodTk = [];
		$subTk = [];
		foreach ($c['L'] as $l)
		{
			$pid = (string) ($l['product'] ?? '0');
			if ($pid === '0' || $l['sale'] === null)
			{
				continue;
			}
			$prodPosNet[$pid] = ($prodPosNet[$pid] ?? 0.0) + floatval($l['net']);
			$prodGross[$pid] = ($prodGross[$pid] ?? 0.0) + floatval($l['gross'] ?? $l['net']);
			$prodTk[$pid][$l['sale']] = true;
			$m = $meta[$pid] ?? null;
			if ($m)
			{
				$dk = $m['dept_key'];
				$cleanCat = trim(preg_replace('/^\d{2}\s+/', '', (string) ($m['category'] ?? '')));
				$subName = trim((string) ($m['subcat'] ?? '')) ?: ($cleanCat ?: 'General');
				$subTk[$dk][$subName][$l['sale']] = true;
			}
		}

		$totalNet = $c['cur']['net'] ?? 0.0;
		$byDeptSkus = [];
		$byDeptSubs = [];
		foreach ($ids as $pid)
		{
			$pid = (string) $pid;
			$m = $meta[$pid] ?? null;
			if (!$m)
			{
				continue;
			}
			$dk = $m['dept_key'];
			$cp = $c['curProduct'][$pid] ?? null;
			$n = round(floatval($cp['net'] ?? 0.0), 2);
			$u = round(floatval($cp['units'] ?? 0.0), 2);
			$p = $c['prevProductDetail'] ? round(floatval($c['prevProduct'][$pid]['net'] ?? 0.0), 2) : null;
			if ($n <= 0 && $u <= 0)
			{
				continue;
			}
			$posDiscount = isset($prodGross[$pid]) ? max(0.0, $prodGross[$pid] - ($prodPosNet[$pid] ?? 0.0)) : 0.0;
			$g = round($n + $posDiscount, 2);
			$disc = round($g - $n, 2);
			$skuTk = isset($prodTk[$pid]) ? count($prodTk[$pid]) : null;
			$cleanCat = trim(preg_replace('/^\d{2}\s+/', '', (string) ($m['category'] ?? '')));
			$subName = trim((string) ($m['subcat'] ?? '')) ?: ($cleanCat ?: 'General');
			$deptNet = $deptNets[$dk] ?? 0.0;

			$skuRow = [
				'id' => intval($pid),
				'name' => (string) $m['name'],
				'sku' => (string) ($m['sku'] ?? ''),
				'subcategory' => $subName,
				'net' => $n,
				'gross' => $g,
				'discounts' => $disc,
				'units' => $u,
				'tickets' => $skuTk,
				'avg_unit_price' => $u > 0 ? round($n / $u, 2) : null,
				'prev_net' => $p,
				'delta_abs' => $p === null ? null : round($n - $p, 2),
				'delta_pct' => self::Delta($n, $p !== null && $p > 0 ? $p : null),
				'share_dept_pct' => self::Share($n, $deptNet),
				'share_total_pct' => self::Share($n, $totalNet)
			];
			$byDeptSkus[$dk][] = $skuRow;

			if (!isset($byDeptSubs[$dk][$subName]))
			{
				$byDeptSubs[$dk][$subName] = [
					'key' => preg_replace('/[^a-z0-9]+/', '-', strtolower($subName)) ?: 'sub',
					'name' => $subName,
					'net' => 0.0, 'gross' => 0.0, 'discounts' => 0.0, 'units' => 0.0,
					'prev_net' => 0.0, 'has_prev' => false,
					'skus' => []
				];
			}
			$byDeptSubs[$dk][$subName]['net'] += $n;
			$byDeptSubs[$dk][$subName]['gross'] += $g;
			$byDeptSubs[$dk][$subName]['discounts'] += $disc;
			$byDeptSubs[$dk][$subName]['units'] += $u;
			if ($p !== null)
			{
				$byDeptSubs[$dk][$subName]['prev_net'] += $p;
				$byDeptSubs[$dk][$subName]['has_prev'] = true;
			}
			$byDeptSubs[$dk][$subName]['skus'][] = $skuRow;
		}

		$byDept = [];
		foreach ($deptNets as $dk => $deptNet)
		{
			$skus = $byDeptSkus[$dk] ?? [];
			usort($skus, fn($a, $b) => $b['net'] <=> $a['net'] ?: strcmp($a['name'], $b['name']));
			$top5 = array_slice($skus, 0, 5);
			$bottom5 = count($skus) > 5 ? array_reverse(array_slice($skus, max(5, count($skus) - 5))) : [];

			$subs = [];
			$subSum = 0.0;
			foreach ($byDeptSubs[$dk] ?? [] as $subName => $sr)
			{
				usort($sr['skus'], fn($a, $b) => $b['net'] <=> $a['net'] ?: strcmp($a['name'], $b['name']));
				$sNet = round($sr['net'], 2);
				$subSum += $sNet;
				$sUnits = round($sr['units'], 2);
				$sPrev = $sr['has_prev'] ? round($sr['prev_net'], 2) : null;
				$sTk = isset($subTk[$dk][$subName]) ? count($subTk[$dk][$subName]) : null;
				$subs[] = [
					'key' => $sr['key'],
					'name' => $sr['name'],
					'net' => $sNet,
					'gross' => round($sr['gross'], 2),
					'discounts' => round($sr['discounts'], 2),
					'units' => $sUnits,
					'tickets' => $sTk,
					'avg_unit_price' => $sUnits > 0 ? round($sNet / $sUnits, 2) : null,
					'prev_net' => $sPrev,
					'delta_abs' => $sPrev !== null ? round($sNet - $sPrev, 2) : null,
					'delta_pct' => ($sPrev !== null && $sPrev > 0) ? self::Delta($sNet, $sPrev) : null,
					'share_dept_pct' => self::Share($sNet, $deptNet),
					'share_total_pct' => self::Share($sNet, $totalNet),
					'sku_count' => count($sr['skus']),
					'skus' => $sr['skus']
				];
			}
			// Si parte del archivo histórico no tiene desglose de mezcla por SKU, se registra la diferencia explícitamente
			if ($deptNet > 0 && abs($deptNet - $subSum) > 1.0)
			{
				$rem = round($deptNet - $subSum, 2);
				$subs[] = [
					'key' => '_archive',
					'name' => 'Archivo histórico consolidado (sin desglose SKU)',
					'net' => $rem, 'gross' => $rem, 'discounts' => 0.0, 'units' => 0.0,
					'tickets' => null, 'avg_unit_price' => null,
					'prev_net' => null, 'delta_abs' => null, 'delta_pct' => null,
					'share_dept_pct' => self::Share($rem, $deptNet),
					'share_total_pct' => self::Share($rem, $totalNet),
					'sku_count' => 0, 'is_archive_remainder' => true, 'skus' => []
				];
			}
			usort($subs, fn($a, $b) => $b['net'] <=> $a['net'] ?: strcmp($a['name'], $b['name']));
			$byDept[$dk] = ['subcategories' => $subs, 'top5' => $top5, 'bottom5' => $bottom5, 'sku_count' => count($skus)];
		}

		return ['by_dept' => $byDept, 'has_demo_products' => $hasDemoProducts];
	}

	/** Origen de los datos del período y del año anterior (POS real vs archivo histórico/demo) */
	private function DeptDataOrigin(array $c, bool $hasDemoProducts): array
	{
		['f' => $f, 'from' => $from, 'to' => $to] = $c;
		$hasPos = false;
		$hasArch = false;
		foreach ($c['L'] as $l)
		{
			if ($l['sale'] !== null)
			{
				$hasPos = true;
			}
			else
			{
				$hasArch = true;
			}
			if ($hasPos && $hasArch)
			{
				break;
			}
		}
		$archSources = [];
		if ($hasArch && ($w = $this->ArchiveWhere($f, $from, $to)))
		{
			try
			{
				$archSources = $this->Db()->query("SELECT DISTINCT IFNULL(source, 'demo') AS s FROM daily_sales_archive a WHERE $w")->fetchAll(\PDO::FETCH_COLUMN);
			}
			catch (\Throwable $e) {}
		}

		$lyFrom = date('Y-m-d', strtotime("$from -1 year"));
		$lyTo = date('Y-m-d', strtotime("$to -1 year"));
		$lyCut = $this->Cutover();
		$lyHasPos = false;
		if ($lyCut === '' || $lyTo >= $lyCut)
		{
			$lyTFrom = $lyCut ? max($lyFrom, $lyCut) : $lyFrom;
			$st = $this->Db()->prepare("SELECT 1 FROM sales s WHERE s.status = 'completed' AND s.created_timestamp >= :f AND s.created_timestamp < :t AND " . DailyFilters::BranchSql($f, 's.branch_id') . ' LIMIT 1');
			$st->execute([':f' => $lyTFrom . ' 00:00:00', ':t' => date('Y-m-d', strtotime("$lyTo +1 day")) . ' 00:00:00']);
			$lyHasPos = (bool) $st->fetchColumn();
		}
		$lySources = [];
		if ($wLy = $this->ArchiveWhere($f, $lyFrom, $lyTo))
		{
			try
			{
				$lySources = $this->Db()->query("SELECT DISTINCT IFNULL(source, 'demo') AS s FROM daily_sales_archive a WHERE $wLy")->fetchAll(\PDO::FETCH_COLUMN);
			}
			catch (\Throwable $e) {}
		}
		return [
			'has_pos' => $hasPos,
			'has_archive' => $hasArch,
			'archive_sources' => array_values($archSources),
			'has_demo_products' => $hasDemoProducts,
			'ly_has_pos' => $lyHasPos,
			'ly_has_archive' => !empty($lySources),
			'ly_archive_sources' => array_values($lySources)
		];
	}

	/** Configuración vigente de departamentos y valores de products.department (para el editor de la pestaña) */
	public static function DeptConfig(): array
	{
		$rows = [];
		foreach (DailyMetricsService::Departments() as $k => $d)
		{
			$rows[] = ['key' => $k, 'name' => $d[0], 'color' => $d[1], 'values' => $d[2], 'code' => $d[3], 'sort_order' => $d[4] === PHP_INT_MAX ? 9999 : $d[4],
				'base' => isset(DailyMetricsService::DEPARTMENTS[$k])];
		}
		$values = [];
		try
		{
			$values = array_map(fn($r) => ['value' => $r['v'], 'products' => intval($r['n'])], DatabaseService::GetInstance()->GetDbConnectionRaw()
				->query("SELECT TRIM(department) AS v, COUNT(*) AS n FROM products WHERE active = 1 AND IFNULL(TRIM(department), '') <> '' GROUP BY 1 ORDER BY 1")->fetchAll(\PDO::FETCH_ASSOC));
		}
		catch (\Throwable $e) {}
		return ['departments' => $rows, 'product_values' => $values];
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

	/* 4. Sucursales: ¿qué tienda vende más y cómo rinde comercialmente frente a las demás? */
	private function Branches(array $c): array
	{
		['f' => $f, 'from' => $from, 'to' => $to, 'days' => $days, 'cur' => $cur, 'goals' => $goals] = $c;
		$db = $this->Db();
		// Si el período termina hoy, el período anterior y las ventanas de crecimiento se cortan a la misma hora
		$cutTime = $to === date('Y-m-d') ? date('H:i:s') : null;
		$prevBranch = $cutTime === null ? $c['prevBranch'] : $this->Window($f, $c['prevFrom'], $c['prevTo'], $cutTime, 'branch');
		$prevTotal = round(array_sum(array_column($prevBranch, 'net')), 2);
		[$growth, $gData] = $this->GrowthWindows($c, 'branch', $c['curBranch'], $cutTime);

		// Universo: sucursales activas del filtro (las que no registran ventas se muestran «Sin datos», no en cero) y las que vendieron
		$ids = DailyFilters::BranchIds($f);
		$master = [];
		foreach ($db->query('SELECT id, name, code, format, IFNULL(active, 1) AS active, latitude, longitude FROM branches'
			. ($ids === null ? '' : ' WHERE id IN (' . implode(',', $ids ?: [0]) . ')'))->fetchAll(\PDO::FETCH_ASSOC) as $b)
		{
			$master[intval($b['id'])] = $b;
		}

		// Días operados de cada sucursal (días con ventas, sin importar el filtro de producto) y fecha de su primera venta
		$bsql = DailyFilters::BranchSql($f, 'branch_id');
		$opDays = [];
		$first = [];
		$cut = $this->Cutover();
		$tFrom = max($from, $cut);
		if ($tFrom <= $to)
		{
			$st = $db->prepare("SELECT DISTINCT branch_id, date(created_timestamp) FROM sales WHERE status = 'completed' AND created_timestamp >= :f AND created_timestamp < :t AND $bsql");
			$st->execute([':f' => "$tFrom 00:00:00", ':t' => date('Y-m-d', strtotime("$to +1 day")) . ' 00:00:00']);
			foreach ($st->fetchAll(\PDO::FETCH_NUM) as [$b, $d])
			{
				$opDays[intval($b)][$d] = true;
			}
		}
		foreach ($db->query("SELECT branch_id, MIN(date(created_timestamp)) FROM sales WHERE status = 'completed' AND $bsql GROUP BY 1")->fetchAll(\PDO::FETCH_NUM) as [$b, $d])
		{
			$first[intval($b)] = $d;
		}
		if ($cut !== '')
		{
			$last = min($to, date('Y-m-d', strtotime("$cut -1 day")));
			if ($from <= $last)
			{
				foreach ($db->query('SELECT DISTINCT branch_id, day FROM daily_sales_archive WHERE net > 0 AND day BETWEEN ' . $db->quote($from) . ' AND ' . $db->quote($last) . " AND $bsql")->fetchAll(\PDO::FETCH_NUM) as [$b, $d])
				{
					$opDays[intval($b)][$d] = true;
				}
			}
			foreach ($db->query("SELECT branch_id, MIN(day) FROM daily_sales_archive WHERE net > 0 AND $bsql GROUP BY 1")->fetchAll(\PDO::FETCH_NUM) as [$b, $d])
			{
				$first[intval($b)] = isset($first[intval($b)]) ? min($first[intval($b)], $d) : $d;
			}
		}
		// Días en que operó la red; una sucursal tiene el período completo si operó al menos el 90 % de ellos
		$netDays = [];
		foreach ($opDays as $byDay)
		{
			$netDays += $byDay;
		}
		$netDays = count($netDays);

		$validCoord = fn($lat, $lng) => is_numeric($lat) && is_numeric($lng) && abs($lat) <= 90 && abs($lng) <= 180 && !(floatval($lat) == 0.0 && floatval($lng) == 0.0);
		$rows = [];
		foreach (array_unique(array_merge(array_keys($master), array_map('intval', array_keys($c['curBranch'])))) as $id)
		{
			$m = $master[$id] ?? null;
			$cb = $c['curBranch'][$id] ?? null;
			$pb = $prevBranch[$id] ?? null;
			$active = count($opDays[$id] ?? []);
			$noData = $active === 0 && ($cb['net'] ?? 0) <= 0;
			if ($noData && $m !== null && !intval($m['active']))
			{
				continue;
			}
			$n = $cb['net'] ?? 0.0;
			$p = $pb['net'] ?? 0.0;
			$t = $cb['tickets'] ?? 0;
			$pt = $pb['tickets'] ?? 0;
			$margin = self::Margin($n, $cb['cogs'] ?? 0.0);
			$prevMargin = self::Margin($p, $pb['cogs'] ?? 0.0);
			$avg = $t > 0 ? round($n / $t, 2) : null;
			$prevAvg = $pt > 0 ? round($p / $pt, 2) : null;
			$g = $goals['by_branch'][$id] ?? null;
			$gr = [];
			foreach ($gData as $k => [$cw, $pw])
			{
				$gr[$k] = $noData ? null : self::Delta($cw[$id]['net'] ?? 0.0, ($pw[$id]['net'] ?? 0) > 0 ? $pw[$id]['net'] : null);
			}
			$coords = $m !== null && $validCoord($m['latitude'], $m['longitude']) ? ['lat' => floatval($m['latitude']), 'lng' => floatval($m['longitude'])] : null;
			$rows[] = ['id' => $id, 'name' => $m['name'] ?? ('Sucursal ' . $id), 'code' => $m['code'] ?? null, 'format' => $m['format'] ?? null,
				'no_data' => $noData, 'complete' => !$noData && $netDays > 0 && $active >= ceil($netDays * 0.9),
				'active_days' => $active, 'network_days' => $netDays, 'coverage_pct' => $netDays > 0 ? round($active / $netDays * 100, 1) : null,
				'first_sale' => $first[$id] ?? null,
				'net' => $noData ? null : $n, 'prev_net' => $p, 'delta_pct' => $noData ? null : self::Delta($n, $p > 0 ? $p : null),
				'share_pct' => $noData ? null : self::Share($n, $cur['net']), 'prev_share_pct' => self::Share($p, $prevTotal),
				'net_per_day' => $active > 0 ? round($n / $active, 2) : null,
				'tickets' => $noData ? null : $t, 'prev_tickets' => $pt, 'tickets_delta' => $noData ? null : self::Delta($t, $pt > 0 ? $pt : null),
				'avg_ticket' => $avg, 'prev_avg_ticket' => $prevAvg, 'avg_ticket_delta' => self::Delta($avg, $prevAvg),
				'cogs' => $noData ? null : ($cb['cogs'] ?? 0.0), 'gross_profit' => $margin !== null ? round($n - $cb['cogs'], 2) : null, 'margin_pct' => $margin,
				'prev_margin_pct' => $prevMargin, 'margin_delta_pp' => $margin !== null && $prevMargin !== null ? round($margin - $prevMargin, 1) : null,
				'goal' => $g, 'goal_pct' => $g && !$noData ? round($n / $g * 100, 1) : null, 'growth' => $gr, 'coords' => $coords];
		}
		// Ranking solo entre sucursales con el período completo; las de período incompleto van después, sin posición; las sin datos al final
		$tier = fn($r) => $r['no_data'] ? 2 : ($r['complete'] ? 0 : 1);
		usort($rows, fn($a, $b) => $tier($a) <=> $tier($b) ?: ($b['net'] ?? 0) <=> ($a['net'] ?? 0) ?: strcmp($a['name'], $b['name']));
		$prevOrder = array_filter(array_column($rows, 'prev_net', 'id'), fn($v) => $v > 0);
		arsort($prevOrder);
		$prevRank = array_flip(array_keys($prevOrder));
		$i = 0;
		foreach ($rows as &$r)
		{
			$r['rank'] = $r['complete'] ? ++$i : null;
			$r['prev_rank'] = isset($prevRank[$r['id']]) ? $prevRank[$r['id']] + 1 : null;
			$r['rank_change'] = $r['rank'] !== null && $r['prev_rank'] !== null ? $r['prev_rank'] - $r['rank'] : null;
		}
		unset($r);
		$withData = array_values(array_filter($rows, fn($r) => !$r['no_data']));

		// Matriz sucursal × día (≤ 31 días) o sucursal × día de la semana (períodos largos) y evolución diaria
		$byDay = count($days) <= 31;
		$daily = self::Sum2($c['L'], 'branch', 'day');
		$heat = $byDay ? $daily : self::Sum2($c['L'], 'branch', 'dow');
		$cols = $byDay ? $days : ['1', '2', '3', '4', '5', '6', '0'];
		$series = [];
		foreach ($withData as $r)
		{
			$series[$r['id']] = array_map(fn($d) => $daily[$r['id']][$d] ?? 0, $days);
		}

		// Ventas por hora de cada sucursal; los días del archivo se reparten con la curva por hora de referencia
		$hourly = [];
		$archBranch = [];
		foreach ($c['L'] as $l)
		{
			$hh = intval($l['hour']);
			if ($hh >= 0)
			{
				$hourly[$l['branch']][$hh] = ($hourly[$l['branch']][$hh] ?? 0) + $l['net'];
			}
			else
			{
				$archBranch[$l['branch']] = ($archBranch[$l['branch']] ?? 0) + $l['net'];
			}
		}
		if ($archBranch && ($shares = $this->ArchiveHours()))
		{
			foreach ($archBranch as $k => $v)
			{
				foreach ($shares as $hh => $sh)
				{
					$hourly[$k][intval($hh)] = ($hourly[$k][intval($hh)] ?? 0) + $v * $sh;
				}
			}
		}
		$hourSeries = [];
		$activeH = array_fill(0, 24, false);
		foreach ($hourly as $k => $byH)
		{
			$hourSeries[$k] = array_map(fn($hh) => round($byH[$hh] ?? 0, 2), range(0, 23));
			foreach ($byH as $hh => $v)
			{
				$activeH[$hh] = $activeH[$hh] || $v > 0;
			}
		}
		$firstH = array_search(true, $activeH, true);
		$lastH = $firstH === false ? false : 23 - array_search(true, array_reverse($activeH), true);

		// Evolución histórica mensual por sucursal (36 meses hasta la fecha final; el último mes puede estar en curso)
		$hFrom = date('Y-m-01', strtotime(substr($to, 0, 8) . '01 -35 months'));
		$monthly = [];
		foreach ($this->Agg($f, $hFrom, $to, ['month', 'branch']) as $a)
		{
			$monthly[intval($a['g1'])][$a['g0']] = ['net' => $a['net'], 'cogs' => $a['cogs'], 'tickets' => $a['tickets']];
		}
		$months = [];
		foreach ($monthly as $byM)
		{
			$months += array_flip(array_keys($byM));
		}
		$months = array_keys($months);
		sort($months);
		$hist = [];
		foreach ($withData as $r)
		{
			$hist[$r['id']] = array_map(fn($mo) => isset($monthly[$r['id']][$mo]) ? round($monthly[$r['id']][$mo]['net'], 2) : null, $months);
		}

		$sum = round(array_sum(array_map(fn($r) => $r['net'] ?? 0, $rows)), 2);
		return ['rows' => $rows, 'heat' => $heat, 'heat_cols' => $cols, 'heat_by_day' => $byDay, 'days' => $days, 'series' => $series,
			'top5' => array_slice(array_column($withData, 'id'), 0, 5),
			'has_goals' => $goals !== null && !empty($goals['by_branch']),
			'with_data' => count($withData), 'ranked' => count(array_filter($rows, fn($r) => $r['rank'] !== null)),
			'incomplete' => array_values(array_map(fn($r) => ['name' => $r['name'], 'active_days' => $r['active_days'], 'first_sale' => $r['first_sale']],
				array_filter($rows, fn($r) => !$r['no_data'] && !$r['complete']))),
			'no_data' => array_values(array_map(fn($r) => $r['name'] . ($r['code'] ? ' (' . $r['code'] . ')' : ''), array_filter($rows, fn($r) => $r['no_data']))),
			'network_days' => $netDays, 'cut_time' => $cutTime ? substr($cutTime, 0, 5) : null, 'prev_total' => $prevTotal, 'growth' => $growth,
			'hourly' => ['first' => $firstH === false ? null : $firstH, 'last' => $lastH === false ? null : $lastH, 'series' => $hourSeries, 'archive_spread' => !empty($archBranch)],
			'history' => ['months' => $months, 'series' => $hist, 'partial_last' => $months && end($months) === substr($to, 0, 7) && $to < date('Y-m-t', strtotime($to)) ? intval(substr($to, 8, 2)) : null],
			'with_coords' => count(array_filter($rows, fn($r) => $r['coords'] !== null)),
			'reconciliation' => ['total' => $cur['net'], 'sum' => $sum, 'diff' => round($cur['net'] - $sum, 2), 'ok' => abs($cur['net'] - $sum) <= 1.0]];
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

		// Semana contra semana: semana de la fecha final (lunes → fecha final) frente a los mismos días de la semana anterior
		$wMon = date('Y-m-d', strtotime('monday this week', strtotime($to)));
		$wow = ['from' => $wMon, 'to' => $to, 'prev_from' => date('Y-m-d', strtotime("$wMon -7 days")), 'prev_to' => date('Y-m-d', strtotime("$to -7 days")), 'days' => []];
		for ($d = $wMon; $d <= date('Y-m-d', strtotime("$wMon +6 days")); $d = date('Y-m-d', strtotime("$d +1 day")))
		{
			$pd = date('Y-m-d', strtotime("$d -7 days"));
			$wow['days'][] = ['date' => $d, 'net' => $d <= $to ? ($cal[$d]['net'] ?? 0.0) : null, 'prev_date' => $pd, 'prev_net' => $cal[$pd]['net'] ?? 0.0, 'tickets' => $d <= $to ? ($cal[$d]['tickets'] ?? 0) : null,
				'prev_tickets' => $cal[$pd]['tickets'] ?? 0];
		}
		$wc = self::SumDays($cal, $wMon, $to);
		$wp = self::SumDays($cal, $wow['prev_from'], $wow['prev_to']);
		$wow += ['net' => $wc['net'], 'prev_net' => $wp['net'], 'tickets' => $wc['tickets'], 'prev_tickets' => $wp['tickets'], 'pct' => self::Delta($wc['net'], $wp['net'])];

		// Curva acumulada: período, período de comparación y mismo período del año anterior, día a día
		$yaDaily = $this->By($f, $yaFrom, $yaTo, 'day');
		$prevDays = self::Days($c['prevFrom'], $c['prevTo']);
		$yaDays = self::Days($yaFrom, $yaTo);
		$acc = function (array $ds, array $by) {
			$s = 0.0;
			return array_map(function ($d) use (&$s, $by) { $s += $by[$d]['net'] ?? 0; return round($s, 2); }, $ds);
		};
		$cumulative = ['labels' => $days, 'net' => $acc($days, $curDaily), 'prev' => $acc($prevDays, $c['prevDaily']), 'prev_label' => $c['prevLabel'],
			'ly' => $ya['tickets'] > 0 ? $acc($yaDays, $yaDaily) : null];

		// Históricos mensuales (13 meses hasta la fecha final) por sucursal, departamento, SKU y canal
		$m13 = date('Y-m-01', strtotime(substr($to, 0, 7) . '-01 -12 months'));
		$months = [];
		for ($m = $m13; $m <= $to; $m = date('Y-m-01', strtotime("$m +1 month")))
		{
			$months[] = substr($m, 0, 7);
		}
		$matrix = function (array $rows, array $names, int $limit = 0) use ($months) {
			$by = [];
			foreach ($rows as $r)
			{
				$by[$r['g1']][$r['g0']] = ($by[$r['g1']][$r['g0']] ?? 0) + $r['net'];
			}
			$out = [];
			foreach ($by as $k => $mm)
			{
				if (!isset($names[$k]))
				{
					continue;
				}
				$out[] = ['key' => (string) $k, 'name' => $names[$k], 'total' => round(array_sum($mm), 2), 'months' => array_map(fn($m) => isset($mm[$m]) ? round($mm[$m], 2) : null, $months)];
			}
			usort($out, fn($a, $b) => $b['total'] <=> $a['total']);
			return $limit ? array_slice($out, 0, $limit) : $out;
		};
		$byBranch = $matrix($this->Agg($f, $m13, $to, ['month', 'branch']), $c['branchNames']);
		$byDept = $matrix($this->Agg($f, $m13, $to, ['month', 'dept']), array_map(fn($d) => $d['name'], $c['depts']));
		// SKU: los 10 más vendidos del período; el archivo histórico no tiene detalle por producto (esos meses quedan vacíos)
		$top = $c['curProduct'];
		unset($top['0'], $top[0]);
		uasort($top, fn($a, $b) => $b['net'] <=> $a['net']);
		$skuNames = [];
		if ($top)
		{
			$ids = array_slice(array_map('intval', array_keys($top)), 0, 10);
			foreach ($this->Db()->query('SELECT id, name, sku FROM products WHERE id IN (' . implode(',', $ids) . ')')->fetchAll(\PDO::FETCH_ASSOC) as $p)
			{
				$skuNames[$p['id']] = ($p['sku'] ? $p['sku'] . ' · ' : '') . $p['name'];
			}
		}
		$bySku = $skuNames ? $matrix(array_values(array_filter($this->Agg($f, max($m13, $this->Cutover() ?: $m13), $to, ['month', 'product']), fn($r) => isset($skuNames[$r['g1']]))), $skuNames) : [];
		// Canal: tienda, domicilio o recoger (pedido vinculado al ticket); los días del archivo no tienen canal
		$chNames = ['store' => 'Tienda (caja)', 'delivery' => 'Pedido a domicilio', 'pickup' => 'Pedido para recoger', 'archive' => 'Archivo histórico (sin canal)'];
		$chRows = [];
		$chMonth = [];
		foreach ($this->Mix($f, $m13, $to) as $r)
		{
			$chRows[] = ['g0' => substr($r['day'], 0, 7), 'g1' => isset($chNames[$r['channel']]) ? $r['channel'] : 'store', 'net' => floatval($r['net'])];
			$chMonth[substr($r['day'], 0, 7)] = ($chMonth[substr($r['day'], 0, 7)] ?? 0) + floatval($r['net']);
		}
		foreach ($months as $m)
		{
			$gap = round(($monthly[$m]['net'] ?? 0) - ($chMonth[$m] ?? 0), 2);
			if ($gap > 0.5)
			{
				$chRows[] = ['g0' => $m, 'g1' => 'archive', 'net' => $gap];
			}
		}
		$byChannel = $matrix($chRows, $chNames);

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
			'weekend_ly_pct' => $sameWeekendLy['net'] !== null ? self::Delta($lastWe['net'], $sameWeekendLy['net']) : null,
			'wow' => $wow,
			'cumulative' => $cumulative,
			'months' => $months,
			'by_branch' => $byBranch,
			'by_dept' => $byDept,
			'by_sku' => $bySku,
			'sku_from' => max($m13, $this->Cutover() ?: $m13),
			'by_channel' => $byChannel
		];
	}

	/**
	 * Agregado de rentabilidad con la calidad del costo de cada línea (misma venta neta prorrateada que Agg()):
	 * - net: venta neta (todas las líneas) · gross: venta antes del descuento del ticket (Σ subtotal, sin IVA)
	 * - net_c / gross_c / cogs / units_c: solo líneas con costo registrado. Una línea sin costo NO cuenta como costo cero:
	 *   queda fuera de la utilidad y del margen y se informa aparte (net_missing).
	 * - net_offlot: costo distinto (> 10 %) al precio registrado de su lote · net_nolot: costo sin precio de lote que lo respalde.
	 * Los días del archivo histórico traen venta y costo consolidados (net_archive): se consideran con costo y sin descuento.
	 */
	private function CostAgg(array $f, string $from, string $to, array $groups = []): array
	{
		if (!$this->lotTable)
		{
			$db = $this->Db();
			$db->exec('DROP TABLE IF EXISTS temp.daily_lot_cost');
			// Precio del lote = primer movimiento del lote con precio (compra, inventario inicial o traslado)
			$db->exec('CREATE TEMP TABLE daily_lot_cost AS SELECT stock_id, price FROM stock_log WHERE id IN (SELECT MIN(id) FROM stock_log
				WHERE undone = 0 AND price > 0 AND stock_id IS NOT NULL GROUP BY stock_id)');
			$db->exec('CREATE INDEX temp.ix_daily_lot_cost ON daily_lot_cost(stock_id)');
			$this->lotTable = true;
		}
		$branch = DailyFilters::BranchSql($f, 's.branch_id');
		$sel = [];
		foreach (array_values($groups) as $i => $g)
		{
			$sel[] = $this->GroupExpr($g) . " AS g$i";
		}
		$n = 'si.subtotal * CASE WHEN t.ls > 0 THEN (s.total_amount - s.tax_amount) / t.ls ELSE 1 END';
		$hasCost = 'IFNULL(si.total_cost, 0) > 0';
		$sql = "WITH t AS (SELECT si.sale_id, SUM(si.subtotal) AS ls FROM sale_items si JOIN sales s ON s.id = si.sale_id
				WHERE s.status = 'completed' AND s.created_timestamp >= :f AND s.created_timestamp < :t AND $branch GROUP BY si.sale_id)
			SELECT " . ($sel ? implode(', ', $sel) . ', ' : '') . "
				IFNULL(SUM($n), 0) AS net, IFNULL(SUM(si.subtotal), 0) AS gross, IFNULL(SUM(si.amount), 0) AS units,
				IFNULL(SUM(CASE WHEN $hasCost THEN $n END), 0) AS net_c, IFNULL(SUM(CASE WHEN $hasCost THEN si.subtotal END), 0) AS gross_c,
				IFNULL(SUM(CASE WHEN $hasCost THEN si.total_cost END), 0) AS cogs, IFNULL(SUM(CASE WHEN $hasCost THEN si.amount END), 0) AS units_c,
				IFNULL(SUM(CASE WHEN $hasCost THEN 0 ELSE $n END), 0) AS net_missing, SUM(CASE WHEN $hasCost THEN 0 ELSE 1 END) AS lines_missing,
				IFNULL(SUM(CASE WHEN $hasCost AND l.price IS NULL THEN $n END), 0) AS net_nolot,
				IFNULL(SUM(CASE WHEN $hasCost AND l.price IS NOT NULL AND ABS(si.unit_cost - l.price) > l.price * 0.10 THEN $n END), 0) AS net_offlot,
				IFNULL(SUM(CASE WHEN $hasCost AND l.price IS NOT NULL AND ABS(si.unit_cost - l.price) > l.price * 0.10 THEN l.price * si.amount WHEN $hasCost THEN si.total_cost END), 0) AS cogs_lot,
				0 AS net_archive, COUNT(DISTINCT s.id) AS tickets
			FROM sale_items si JOIN sales s ON s.id = si.sale_id JOIN t ON t.sale_id = s.id JOIN products p ON p.id = si.product_id
				LEFT JOIN temp.daily_lot_cost l ON l.stock_id = si.stock_id
			WHERE s.status = 'completed' AND s.created_timestamp >= :f AND s.created_timestamp < :t AND $branch AND " . DailyFilters::ProductSql($f)
			. ($sel ? ' GROUP BY ' . implode(', ', array_map(fn($i) => "g$i", array_keys($sel))) : '');
		$rows = [];
		$tFrom = max($from, $this->Cutover());
		if ($tFrom <= $to)
		{
			$st = $this->Db()->prepare($sql);
			$st->execute([':f' => $tFrom . ' 00:00:00', ':t' => date('Y-m-d', strtotime("$to +1 day")) . ' 00:00:00']);
			$rows = $st->fetchAll(\PDO::FETCH_ASSOC);
		}
		$idx = [];
		foreach ($rows as $i => $r)
		{
			$idx[($r['g0'] ?? '') . '|' . ($r['g1'] ?? '')] = $i;
		}
		$zero = ['net' => 0, 'gross' => 0, 'units' => 0, 'net_c' => 0, 'gross_c' => 0, 'cogs' => 0, 'cogs_lot' => 0, 'units_c' => 0, 'net_missing' => 0, 'lines_missing' => 0, 'net_nolot' => 0, 'net_offlot' => 0, 'net_archive' => 0, 'tickets' => 0];
		foreach ($this->ArchiveAgg($f, $from, $to, $groups) as $a)
		{
			$k = ($a['g0'] ?? '') . '|' . ($a['g1'] ?? '');
			if (!isset($idx[$k]))
			{
				$idx[$k] = count($rows);
				$rows[] = array_intersect_key($a, ['g0' => 1, 'g1' => 1]) + $zero;
			}
			$i = $idx[$k];
			foreach (['net', 'gross', 'net_c', 'gross_c', 'net_archive'] as $m)
			{
				$rows[$i][$m] += $a['net'];
			}
			$rows[$i]['cogs'] += $a['cogs'];
			$rows[$i]['cogs_lot'] += $a['cogs'];
			$rows[$i]['units'] += $a['units'];
			$rows[$i]['units_c'] += $a['units'];
			$rows[$i]['tickets'] += $a['tickets'];
		}
		$out = [];
		foreach ($rows as $r)
		{
			$out[] = self::Prof($r);
		}
		if (!$groups && !$out)
		{
			$out[] = self::Prof($zero);
		}
		return $out;
	}

	/** Indicadores de rentabilidad de un agregado de CostAgg() */
	private static function Prof(array $r): array
	{
		foreach (['net', 'gross', 'units', 'net_c', 'gross_c', 'cogs', 'cogs_lot', 'units_c', 'net_missing', 'net_nolot', 'net_offlot', 'net_archive'] as $m)
		{
			$r[$m] = round(floatval($r[$m]), 2);
		}
		$r['lines_missing'] = intval($r['lines_missing']);
		$r['tickets'] = intval($r['tickets']);
		$m = self::Margin($r['net_c'], $r['cogs']);
		$r['margin_pct'] = $m;
		// Utilidad bruta = ventas netas con costo − costo de mercancía vendida (no es utilidad neta: no descuenta gastos)
		$r['profit'] = $m === null ? null : round($r['net_c'] - $r['cogs'], 2);
		$r['coverage_pct'] = $r['net'] > 0 ? round($r['net_c'] / $r['net'] * 100, 1) : null;
		$r['discount'] = round($r['gross'] - $r['net'], 2);
		$r['discount_c'] = round($r['gross_c'] - $r['net_c'], 2);
		$r['margin_nodisc_pct'] = self::Margin($r['gross_c'], $r['cogs']);
		$r['profit_lot'] = $m === null ? null : round($r['net_c'] - $r['cogs_lot'], 2);
		$r['margin_lot_pct'] = self::Margin($r['net_c'], $r['cogs_lot']);
		$r['verified_pct'] = $r['net_c'] > 0 ? round(($r['net_c'] - $r['net_nolot'] - $r['net_offlot']) / $r['net_c'] * 100, 1) : null;
		return $r;
	}

	private function CostBy(array $f, string $from, string $to, string $g): array
	{
		$out = [];
		foreach ($this->CostAgg($f, $from, $to, [$g]) as $r)
		{
			$out[(string) $r['g0']] = $r;
		}
		return $out;
	}

	/**
	 * Efectos precio, volumen y costo sobre la utilidad bruta entre dos períodos (solo líneas con costo), por producto:
	 * volumen = (u1 − u0) × (p0 − c0) · precio = (p1 − p0) × u1 · costo = −(c1 − c0) × u1; productos nuevos o que dejaron
	 * de venderse = mezcla. utilidad anterior + volumen + precio + costo + mezcla = utilidad actual.
	 */
	private static function PriceVolumeCost(array $cur, array $prev, array $names): array
	{
		$tot = ['volume' => 0.0, 'price' => 0.0, 'cost' => 0.0, 'mix_new' => 0.0, 'mix_lost' => 0.0];
		$rows = [];
		$profit = fn($r) => $r['net_c'] - $r['cogs'];
		foreach (array_unique(array_merge(array_keys($cur), array_keys($prev))) as $id)
		{
			$a = $cur[$id] ?? null;
			$b = $prev[$id] ?? null;
			$okA = $a && $a['units_c'] > 0 && $a['net_c'] > 0;
			$okB = $b && $b['units_c'] > 0 && $b['net_c'] > 0;
			if (!$okA && !$okB)
			{
				continue;
			}
			$e = ['volume' => 0.0, 'price' => 0.0, 'cost' => 0.0, 'mix' => 0.0];
			if ($okA && $okB)
			{
				[$u0, $p0, $c0] = [$b['units_c'], $b['net_c'] / $b['units_c'], $b['cogs'] / $b['units_c']];
				[$u1, $p1, $c1] = [$a['units_c'], $a['net_c'] / $a['units_c'], $a['cogs'] / $a['units_c']];
				$e = ['volume' => ($u1 - $u0) * ($p0 - $c0), 'price' => ($p1 - $p0) * $u1, 'cost' => -($c1 - $c0) * $u1, 'mix' => 0.0];
				$tot['volume'] += $e['volume'];
				$tot['price'] += $e['price'];
				$tot['cost'] += $e['cost'];
				$row = ['units' => $u1, 'prev_units' => $u0, 'unit_price' => round($p1, 2), 'prev_unit_price' => round($p0, 2), 'unit_cost' => round($c1, 2), 'prev_unit_cost' => round($c0, 2)];
			}
			elseif ($okA)
			{
				$e['mix'] = $profit($a);
				$tot['mix_new'] += $e['mix'];
				$row = ['units' => $a['units_c'], 'prev_units' => 0, 'unit_price' => round($a['net_c'] / $a['units_c'], 2), 'prev_unit_price' => null, 'unit_cost' => round($a['cogs'] / $a['units_c'], 2), 'prev_unit_cost' => null];
			}
			else
			{
				$e['mix'] = -$profit($b);
				$tot['mix_lost'] += $e['mix'];
				$row = ['units' => 0, 'prev_units' => $b['units_c'], 'unit_price' => null, 'prev_unit_price' => round($b['net_c'] / $b['units_c'], 2), 'unit_cost' => null, 'prev_unit_cost' => round($b['cogs'] / $b['units_c'], 2)];
			}
			$delta = array_sum($e);
			$rows[] = ['id' => intval($id), 'name' => $names[$id]['name'] ?? ('Producto ' . $id), 'sku' => (string) ($names[$id]['sku'] ?? ''),
				'profit' => $okA ? round($profit($a), 2) : 0.0, 'prev_profit' => $okB ? round($profit($b), 2) : 0.0, 'delta' => round($delta, 2)]
				+ $row + array_map(fn($v) => round($v, 2), $e);
		}
		usort($rows, fn($x, $y) => abs($y['delta']) <=> abs($x['delta']));
		$p1 = array_sum(array_map(fn($r) => $r['profit'], $rows));
		$p0 = array_sum(array_map(fn($r) => $r['prev_profit'], $rows));
		return ['prev_profit' => round($p0, 2), 'profit' => round($p1, 2)] + array_map(fn($v) => round($v, 2), $tot) + ['rows' => array_slice($rows, 0, 20), 'products' => count($rows)];
	}

	/**
	 * Contribución comercial por sucursal = utilidad bruta − costo laboral de nóminas aprobadas (hr_payroll_runs), prorrateado
	 * por días. Solo se calcula si las nóminas aprobadas cubren todos los días del período; sin filtros de producto
	 * (la nómina no se puede asignar a un departamento o producto con fiabilidad).
	 */
	private function Contribution(array $f, string $from, string $to, array $byBranch, array $bn): array
	{
		$days = count(self::Days($from, $to));
		$productFiltered = $f['department'] !== null || $f['group_id'] !== null || $f['subcategory'] !== null || $f['product'] !== null;
		$labor = [];
		try
		{
			$st = $this->Db()->prepare("SELECT branch_id, period_start, period_end, total_cost FROM hr_payroll_runs
				WHERE status IN ('aprobado', 'approved', 'pagado', 'paid') AND period_end >= :f AND period_start <= :t AND " . DailyFilters::BranchSql($f, 'branch_id'));
			$st->execute([':f' => $from, ':t' => $to]);
			foreach ($st->fetchAll(\PDO::FETCH_ASSOC) as $p)
			{
				$runDays = count(self::Days($p['period_start'], $p['period_end']));
				$ov = self::Days(max($from, $p['period_start']), min($to, $p['period_end']));
				$b = intval($p['branch_id']);
				$labor[$b]['cost'] = ($labor[$b]['cost'] ?? 0) + floatval($p['total_cost']) * count($ov) / max(1, $runDays);
				foreach ($ov as $d)
				{
					$labor[$b]['days'][$d] = true;
				}
			}
		}
		catch (\Throwable $e) {}
		$rows = [];
		foreach ($byBranch as $id => $r)
		{
			$cov = isset($labor[intval($id)]) ? round(count($labor[intval($id)]['days']) / max(1, $days) * 100, 1) : 0.0;
			$ok = !$productFiltered && $cov >= 100 && $r['profit'] !== null;
			$cost = $ok ? round($labor[intval($id)]['cost'], 2) : null;
			$rows[] = ['name' => $bn[intval($id)] ?? ('Sucursal ' . $id), 'profit' => $r['profit'], 'labor_cost' => $cost, 'labor_coverage_pct' => $cov,
				'contribution' => $ok ? round($r['profit'] - $cost, 2) : null, 'contribution_pct' => $ok && $r['net_c'] > 0 ? round(($r['profit'] - $cost) / $r['net_c'] * 100, 1) : null];
		}
		usort($rows, fn($a, $b) => ($b['contribution'] ?? -INF) <=> ($a['contribution'] ?? -INF) ?: ($b['profit'] ?? -INF) <=> ($a['profit'] ?? -INF));
		return ['product_filtered' => $productFiltered, 'rows' => $rows, 'reliable_count' => count(array_filter($rows, fn($r) => $r['contribution'] !== null))];
	}

	/* 6. Rentabilidad comercial: ¿qué ventas generan más utilidad bruta? */
	private function Profitability(array $c): array
	{
		['f' => $f, 'from' => $from, 'to' => $to, 'days' => $days, 'depts' => $depts, 'branchNames' => $bn] = $c;
		$this->lotTable = false;
		$cur = $this->CostAgg($f, $from, $to)[0];
		$prev = $this->CostAgg($f, $c['prevFrom'], $c['prevTo'])[0];
		$yaFrom = date('Y-m-d', strtotime("$from -1 year"));
		$yaTo = date('Y-m-d', strtotime("$to -1 year"));
		$ly = $this->CostAgg($f, $yaFrom, $yaTo)[0];
		$mStart = substr($to, 0, 8) . '01';
		$yStart = substr($to, 0, 5) . '01-01';
		$compare = [];
		foreach ([['day', 'Día', $to, $to], ['mtd', 'Mes a la fecha', $mStart, $to], ['ytd', 'Año a la fecha', $yStart, $to]] as [$k, $label, $a, $b])
		{
			$compare[] = ['key' => $k, 'label' => $label, 'from' => $a, 'to' => $b] + array_intersect_key($this->CostAgg($f, $a, $b)[0], array_flip(['net', 'net_c', 'cogs', 'profit', 'margin_pct', 'coverage_pct']));
		}
		foreach ([['period', 'Período seleccionado', $from, $to, $cur], ['prev', ucfirst($c['prevLabel']), $c['prevFrom'], $c['prevTo'], $prev], ['ly', 'Mismo período del año anterior', $yaFrom, $yaTo, $ly]] as [$k, $label, $a, $b, $r])
		{
			$compare[] = ['key' => $k, 'label' => $label, 'from' => $a, 'to' => $b] + array_intersect_key($r, array_flip(['net', 'net_c', 'cogs', 'profit', 'margin_pct', 'coverage_pct']));
		}

		$deptRows = $this->CostBy($f, $from, $to, 'dept');
		$branchRows = $this->CostBy($f, $from, $to, 'branch');
		$prodRows = $this->CostBy($f, $from, $to, 'product');
		unset($prodRows['0']);
		$names = [];
		$prevProd = $c['prevFrom'] >= $this->Cutover() ? $this->CostBy($f, $c['prevFrom'], $c['prevTo'], 'product') : null;
		$ids = array_unique(array_merge(array_keys($prodRows), $prevProd ? array_keys($prevProd) : []));
		if ($ids)
		{
			$st = $this->Db()->query('SELECT p.id, p.name, p.sku, ' . $this->DeptCase() . ' AS dept FROM products p WHERE p.id IN (' . implode(',', array_map('intval', $ids)) . ')');
			foreach ($st->fetchAll(\PDO::FETCH_ASSOC) as $p)
			{
				$names[$p['id']] = $p;
			}
		}
		$pick = fn($r) => array_intersect_key($r, array_flip(['net', 'net_c', 'cogs', 'profit', 'margin_pct', 'coverage_pct', 'discount', 'units', 'net_missing', 'margin_nodisc_pct']));
		$total = $cur['profit'] ?? 0;
		$byDept = [];
		foreach ($deptRows as $k => $r)
		{
			$byDept[] = ['key' => $k, 'name' => $depts[$k]['name'] ?? $k, 'color' => $depts[$k]['color'] ?? '#64748b', 'profit_share_pct' => $r['profit'] !== null && $total > 0 ? round($r['profit'] / $total * 100, 1) : null] + $pick($r);
		}
		$byBranch = [];
		foreach ($branchRows as $id => $r)
		{
			$byBranch[] = ['id' => intval($id), 'name' => $bn[intval($id)] ?? ('Sucursal ' . $id), 'profit_share_pct' => $r['profit'] !== null && $total > 0 ? round($r['profit'] / $total * 100, 1) : null] + $pick($r);
		}
		$products = [];
		$missing = [];
		foreach ($prodRows as $id => $r)
		{
			$base = ['id' => intval($id), 'name' => $names[$id]['name'] ?? ('Producto ' . $id), 'sku' => (string) ($names[$id]['sku'] ?? ''), 'dept' => $depts[$names[$id]['dept'] ?? '']['name'] ?? ''];
			if ($r['net_missing'] > 0)
			{
				$missing[] = $base + ['net_missing' => $r['net_missing'], 'lines_missing' => $r['lines_missing']];
			}
			if ($r['net'] > 0)
			{
				$products[] = $base + $pick($r) + ['verified_pct' => $r['verified_pct']];
			}
		}
		$sortProfit = fn($a, $b) => ($b['profit'] ?? -INF) <=> ($a['profit'] ?? -INF);
		usort($byDept, $sortProfit);
		usort($byBranch, $sortProfit);
		usort($products, $sortProfit);
		usort($missing, fn($a, $b) => $b['net_missing'] <=> $a['net_missing']);
		$negative = array_values(array_filter($products, fn($p) => $p['profit'] !== null && $p['profit'] < 0));
		usort($negative, fn($a, $b) => $a['profit'] <=> $b['profit']);

		$daily = $this->CostBy($f, $from, $to, 'day');
		$monthly = $this->CostBy($f, '2000-01-01', $to, 'month');
		ksort($monthly);
		$dups = SalesHistoryAuditService::GetInstance()->Duplicates($f, max($from, $this->Cutover() ?: $from), $to);
		$dupNet = round(array_sum(array_map(fn($g) => $g['net'] * count($g['copies']), $dups)), 2);

		return [
			'net' => $cur['net'], 'net_c' => $cur['net_c'], 'cogs' => $cur['cogs'], 'profit' => $cur['profit'], 'margin_pct' => $cur['margin_pct'],
			'gross' => $cur['gross'], 'discount' => $cur['discount'], 'discount_c' => $cur['discount_c'], 'margin_nodisc_pct' => $cur['margin_nodisc_pct'],
			'profit_nodisc' => $cur['margin_pct'] === null ? null : round($cur['gross_c'] - $cur['cogs'], 2),
			'coverage_pct' => $cur['coverage_pct'], 'verified_pct' => $cur['verified_pct'],
			'profit_lot' => $cur['profit_lot'], 'margin_lot_pct' => $cur['margin_lot_pct'],
			'net_missing' => $cur['net_missing'], 'lines_missing' => $cur['lines_missing'], 'net_nolot' => $cur['net_nolot'], 'net_offlot' => $cur['net_offlot'], 'net_archive' => $cur['net_archive'],
			'prev_profit' => $prev['profit'], 'prev_margin_pct' => $prev['margin_pct'],
			'margin_delta_pp' => ($cur['margin_pct'] !== null && $prev['margin_pct'] !== null) ? round($cur['margin_pct'] - $prev['margin_pct'], 1) : null,
			'reliable' => $cur['net_c'] <= 0 || $cur['margin_pct'] !== null,
			'compare' => $compare,
			'duplicates' => ['groups' => count($dups), 'net' => $dupNet],
			'by_department' => $byDept, 'by_branch' => $byBranch,
			'products' => $products,
			'top_products' => array_slice($products, 0, 15),
			'negative' => $negative,
			'missing_cost' => array_slice($missing, 0, 25), 'missing_cost_products' => count($missing),
			'pvc' => $prevProd === null ? null : self::PriceVolumeCost($prodRows, array_diff_key($prevProd, ['0' => 1]), $names),
			'contribution' => $this->Contribution($f, $from, $to, $branchRows, $bn),
			'scatter' => array_map(fn($p) => ['name' => $p['name'], 'net' => $p['net'], 'margin_pct' => $p['margin_pct']], array_slice(array_filter($products, fn($p) => $p['margin_pct'] !== null), 0, 150)),
			'daily' => ['days' => $days, 'net' => array_map(fn($d) => $daily[$d]['net'] ?? 0, $days), 'cogs' => array_map(fn($d) => $daily[$d]['cogs'] ?? 0, $days),
				'margin_pct' => array_map(fn($d) => $daily[$d]['margin_pct'] ?? null, $days)],
			'monthly' => ['labels' => array_keys($monthly), 'net' => array_values(array_map(fn($r) => $r['net'], $monthly)),
				'profit' => array_values(array_map(fn($r) => $r['profit'], $monthly)), 'margin_pct' => array_values(array_map(fn($r) => $r['margin_pct'], $monthly))]
		];
	}
}
