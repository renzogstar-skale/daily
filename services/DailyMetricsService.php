<?php

namespace Grocy\Services;

/**
 * DAILY — Definiciones únicas de los indicadores que comparten HOY, Panel ejecutivo, Ventas, Inventario, Calidad,
 * Mermas y Reportes. Todas leen las mismas tablas operativas con los mismos criterios:
 *
 *  - Ventas netas: SUM(sales.total_amount − sales.tax_amount) de ventas en estado «completed», por fecha de la venta.
 *    Por departamento: SUM(sale_items.subtotal) de esas mismas ventas (las líneas no llevan impuesto).
 *  - Costo de ventas: SUM(total_cogs) (costo FEFO del lote consumido). Margen bruto = ventas netas − costo.
 *  - Ticket promedio = ventas netas ÷ tickets. Unidades por ticket = unidades vendidas ÷ tickets.
 *  - Merma: SUM(waste_records.total_cost) con estado «approved» (las pendientes se informan aparte, no suman).
 *    % de merma = merma ÷ base configurada (BudgetService::WasteBase, la misma del Panel ejecutivo).
 *  - Inventario: existencias de las ubicaciones de la sucursal (sin tránsito) × costo del lote (o último costo).
 *  - Horas programadas: turnos no cancelados del día, menos pausas.
 * Un valor null significa «Sin información» (no hay registros), distinto de un cero real.
 */
class DailyMetricsService extends BaseService
{
	// Clave del panel => [nombre, color, departamentos de producto (products.department)]
	const DEPARTMENTS = [
		'tecnologia' => ['Tecnología', '#2563EB', ['Tecnología', 'Electrónica y Telefonía', 'Electrónica']],
		'electrodomesticos' => ['Electrodomésticos', '#0D9488', ['Electrodomésticos']],
		'alimentos' => ['Alimentos y Despensa', '#D97706', ['Alimentos', 'Abarrotes']],
		'hogar' => ['Hogar', '#854D0E', ['Hogar']],
		'accesorios' => ['Accesorios', '#6366F1', ['Accesorios']],
		'dairy' => ['Lácteos y huevos', '#FFB800', ['Lácteos y huevos', 'Lácteos']],
		'cuidado_personal' => ['Cuidado Personal', '#EC4899', ['Cuidado Personal', 'Cuidado personal']],
		'bebidas' => ['Bebidas', '#0EA5E9', ['Bebidas refrigeradas', 'Bebidas']],
		'carnes' => ['Carnes y aves', '#E11D48', ['Carnes', 'Carnes y aves']],
		'fruver' => ['Frutas y verduras', '#16A34A', ['Frutas y verduras']],
		'congelados' => ['Congelados', '#38BDF8', ['Congelados']],
		'bakery' => ['Panadería', '#8B5CF6', ['Panadería']],
		'otros' => ['Otros', '#64748B', null]
	];

	// Categoría del filtro de HOY => claves de departamento
	const CATEGORIES = [
		'frescos' => ['fruver', 'carnes', 'bakery'],
		'refrigerados' => ['dairy', 'bebidas'],
		'congelados' => ['congelados'],
		'abarrotes' => ['alimentos'],
		'non_food' => ['tecnologia', 'electrodomesticos', 'hogar', 'accesorios', 'cuidado_personal']
	];

	private static ?array $departments = null;

	private function Db(): \PDO
	{
		return DatabaseService::GetInstance()->GetDbConnectionRaw();
	}

	/**
	 * Departamentos comerciales de la empresa: la definición base (DEPARTMENTS) con la configuración de
	 * daily_sales_departments (migración 0324: código, nombre, color, orden y valores de products.department).
	 * [clave => [nombre, color, valores|null, código|null, orden]]; «otros» (valores null) agrupa lo no asignado y va al final.
	 */
	public static function Departments(): array
	{
		if (self::$departments !== null)
		{
			return self::$departments;
		}
		$out = [];
		$i = 0;
		foreach (self::DEPARTMENTS as $k => $d)
		{
			$out[$k] = [$d[0], $d[1], $d[2], null, $i += 10];
		}
		try
		{
			foreach (DatabaseService::GetInstance()->GetDbConnectionRaw()->query('SELECT * FROM daily_sales_departments')->fetchAll(\PDO::FETCH_ASSOC) as $r)
			{
				$vals = $r['key'] === 'otros' ? null : (json_decode((string) $r['source_values'], true) ?: []);
				$out[$r['key']] = [$r['name'], $r['color'] ?: ($out[$r['key']][1] ?? '#64748B'), $vals, trim((string) $r['code']) !== '' ? trim($r['code']) : null, intval($r['sort_order'])];
			}
		}
		catch (\Throwable $e) {}
		$out['otros'] = $out['otros'] ?? ['Otros', '#64748B', null, null, PHP_INT_MAX];
		uksort($out, fn($a, $b) => ($a === 'otros') <=> ($b === 'otros') ?: ($out[$a][4] <=> $out[$b][4]) ?: strcmp($a, $b));
		return self::$departments = $out;
	}

	/** Nombre visible del departamento según el catálogo acordado: «90 Congelados» cuando tiene código; igual en Ventas, Inventario, Mermas y HOY */
	public static function DepartmentLabel(string $key): string
	{
		$d = self::Departments()[$key] ?? null;
		if ($d === null)
		{
			return $key;
		}
		return $d[3] !== null && $d[3] !== '' ? $d[3] . ' ' . $d[0] : $d[0];
	}

	/**
	 * Guarda la configuración de departamentos. $rows = [['key', 'code', 'name', 'color', 'sort_order', 'source_values' => [..], 'delete']].
	 * Rechaza códigos o nombres repetidos y valores de producto asignados a dos departamentos (evita categorías duplicadas).
	 * Los departamentos base no se eliminan (sus productos quedarían sin departamento); los nuevos sí.
	 */
	public function SaveDepartments(array $rows): array
	{
		$cur = self::Departments();
		$next = $cur;
		$deleted = [];
		foreach ($rows as $r)
		{
			$key = strtolower(trim((string) ($r['key'] ?? '')));
			$name = trim((string) ($r['name'] ?? ''));
			if ($key === '')
			{
				$key = trim(preg_replace('/[^a-z0-9]+/', '_', strtolower(iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name) ?: '')), '_');
			}
			if (!preg_match('/^[a-z0-9_]{2,40}$/', $key))
			{
				throw new \Exception('Clave de departamento no válida: «' . $key . '».');
			}
			if (!empty($r['delete']))
			{
				if (isset(self::DEPARTMENTS[$key]))
				{
					throw new \Exception('«' . ($cur[$key][0] ?? $key) . '» es un departamento base y no se puede eliminar; cambie su nombre o código.');
				}
				unset($next[$key]);
				$deleted[] = $key;
				continue;
			}
			if ($name === '')
			{
				throw new \Exception('Cada departamento necesita un nombre.');
			}
			$color = preg_match('/^#[0-9a-fA-F]{6}$/', (string) ($r['color'] ?? '')) ? strtoupper($r['color']) : ($cur[$key][1] ?? '#64748B');
			$code = mb_substr(trim((string) ($r['code'] ?? '')), 0, 12);
			$vals = $key === 'otros' ? null : array_values(array_unique(array_filter(array_map(fn($v) => trim((string) $v), is_array($r['source_values'] ?? null) ? $r['source_values'] : explode(',', (string) ($r['source_values'] ?? ''))), 'strlen')));
			$next[$key] = [mb_substr($name, 0, 60), $color, $vals, $code !== '' ? $code : null, intval($r['sort_order'] ?? ($cur[$key][4] ?? 999))];
		}
		$codes = $names = $owner = [];
		foreach ($next as $k => $d)
		{
			if ($d[3] !== null)
			{
				if (isset($codes[$d[3]]))
				{
					throw new \Exception('El código «' . $d[3] . '» está repetido en ' . $next[$codes[$d[3]]][0] . ' y ' . $d[0] . '.');
				}
				$codes[$d[3]] = $k;
			}
			$n = mb_strtolower($d[0]);
			if (isset($names[$n]))
			{
				throw new \Exception('El nombre «' . $d[0] . '» está repetido.');
			}
			$names[$n] = $k;
			foreach ($d[2] ?? [] as $v)
			{
				$lv = mb_strtolower($v);
				if (isset($owner[$lv]) && $owner[$lv] !== $k)
				{
					throw new \Exception('El valor de producto «' . $v . '» está asignado a ' . $next[$owner[$lv]][0] . ' y a ' . $d[0] . ': cada valor pertenece a un solo departamento.');
				}
				$owner[$lv] = $k;
			}
		}
		$db = $this->Db();
		$db->beginTransaction();
		try
		{
			$up = $db->prepare("INSERT INTO daily_sales_departments (key, code, name, color, sort_order, source_values, updated_timestamp) VALUES (:k, :c, :n, :col, :o, :v, datetime('now', 'localtime'))
				ON CONFLICT(key) DO UPDATE SET code = excluded.code, name = excluded.name, color = excluded.color, sort_order = excluded.sort_order, source_values = excluded.source_values, updated_timestamp = excluded.updated_timestamp");
			foreach ($next as $k => $d)
			{
				$up->execute([':k' => $k, ':c' => $d[3], ':n' => $d[0], ':col' => $d[1], ':o' => $k === 'otros' ? 9999 : $d[4], ':v' => $d[2] === null ? null : json_encode($d[2], JSON_UNESCAPED_UNICODE)]);
			}
			foreach ($deleted as $k)
			{
				$db->prepare('DELETE FROM daily_sales_departments WHERE key = :k')->execute([':k' => $k]);
			}
			$db->commit();
		}
		catch (\Throwable $e)
		{
			$db->rollBack();
			throw $e;
		}
		self::$departments = null;
		return self::Departments();
	}

	/** Departamentos de producto incluidos por una clave de departamento y/o categoría. null = sin filtro. */
	public function DepartmentFilter(?string $deptKey, ?string $category = null): ?array
	{
		$keys = null;
		$deptKey = strtolower(trim((string) $deptKey));
		if ($deptKey === 'electronica')
		{
			$deptKey = 'tecnologia';
		}
		elseif ($deptKey === 'abarrotes')
		{
			$deptKey = 'alimentos';
		}
		if ($deptKey !== '' && $deptKey !== 'todos' && $deptKey !== 'all' && isset(self::Departments()[$deptKey]))
		{
			$keys = [$deptKey];
		}
		$category = strtolower(trim((string) $category));
		if ($category !== '' && $category !== 'todas' && $category !== 'all' && isset(self::CATEGORIES[$category]))
		{
			$keys = $keys === null ? self::CATEGORIES[$category] : array_values(array_intersect($keys, self::CATEGORIES[$category]));
		}
		return $keys;
	}

	/** Expresión SQL (sobre el alias p de products) para un conjunto de claves de departamento */
	public function DeptSql(?array $keys): string
	{
		if ($keys === null)
		{
			return '1 = 1';
		}
		if (count($keys) === 0)
		{
			return '1 = 0';
		}
		$named = [];
		foreach (self::Departments() as $k => $d)
		{
			if ($d[2] !== null)
			{
				$named = array_merge($named, $d[2]);
			}
		}
		$parts = [];
		foreach ($keys as $k)
		{
			$d = self::Departments()[$k] ?? null;
			if ($d === null || $d[2] === [])
			{
				continue;
			}
			$parts[] = $d[2] === null
				? ($named ? "IFNULL(p.department, '') NOT IN (" . $this->Quote($named) . ')' : '1 = 1')
				: 'p.department IN (' . $this->Quote($d[2]) . ')';
		}
		return $parts ? '(' . implode(' OR ', $parts) . ')' : '1 = 0';
	}

	private function Quote(array $values): string
	{
		return implode(', ', array_map(fn($v) => $this->Db()->quote($v), $values));
	}

	private function BranchSql(?int $branchId, string $col = 'branch_id'): string
	{
		return ($branchId !== null && $branchId > 0) ? " AND $col = " . intval($branchId) : '';
	}

	/* ---------------------------------------------------------------- Ventas */

	/**
	 * Estado de datos de ventas de un rango, común a HOY y al Panel Ejecutivo:
	 * 'data' (hay tickets), 'confirmed_zero' (corte confirmado sin ventas: caja cerrada en el rango o el registro ya
	 * llegó a una fecha posterior) o 'no_data' (aún no hay corte: se muestra «Sin información», sin variación).
	 */
	public function SalesDataState(?int $branchId, string $from, string $to, bool $hasData): string
	{
		if ($hasData)
		{
			return 'data';
		}
		$b = $this->BranchSql($branchId);
		$st = $this->Db()->prepare("SELECT EXISTS (SELECT 1 FROM pos_cash_shifts WHERE status = 'closed' AND date(IFNULL(closed_at, opened_at)) BETWEEN :f AND :t$b)
			OR EXISTS (SELECT 1 FROM sales WHERE status = 'completed' AND date(created_timestamp) > :t$b)");
		$st->execute([':f' => $from, ':t' => $to]);
		return intval($st->fetchColumn()) ? 'confirmed_zero' : 'no_data';
	}

	/**
	 * Ventas del rango [from, to] (fechas Y-m-d). $untilTime (HH:MM:SS) recorta cada día a esa hora (comparativos del día en curso).
	 * Con filtro de departamento se calcula desde las líneas de esas mismas ventas.
	 */
	public function Sales(?int $branchId, string $from, string $to, ?array $deptKeys = null, ?string $untilTime = null): array
	{
		$time = $untilTime ? " AND time(s.created_timestamp) <= '" . preg_replace('/[^0-9:]/', '', $untilTime) . "'" : '';
		$where = "s.status = 'completed' AND date(s.created_timestamp) BETWEEN :f AND :t" . $this->BranchSql($branchId, 's.branch_id') . $time;
		$p = [':f' => $from, ':t' => $to];
		$arch = $this->ArchiveSales($branchId, $from, $to, $deptKeys);
		if ($arch !== null)
		{
			return $arch;
		}
		if ($deptKeys === null)
		{
			$st = $this->Db()->prepare("SELECT COUNT(*) AS tickets, IFNULL(SUM(s.total_amount), 0) AS gross, IFNULL(SUM(s.tax_amount), 0) AS tax, IFNULL(SUM(s.total_cogs), 0) AS cogs,
				IFNULL(SUM(s.gross_margin), 0) AS margin, IFNULL(SUM(s.discount_amount), 0) AS discount,
				(SELECT IFNULL(SUM(si.amount), 0) FROM sale_items si JOIN sales s ON s.id = si.sale_id WHERE $where) AS units
				FROM sales s WHERE $where");
			$st->execute($p);
			$r = $st->fetch(\PDO::FETCH_ASSOC);
			$net = floatval($r['gross']) - floatval($r['tax']);
			$margin = floatval($r['margin']);
		}
		else
		{
			$st = $this->Db()->prepare("SELECT COUNT(DISTINCT s.id) AS tickets, IFNULL(SUM(si.subtotal), 0) AS gross, 0 AS tax, IFNULL(SUM(si.total_cost), 0) AS cogs,
				0 AS discount, IFNULL(SUM(si.amount), 0) AS units
				FROM sale_items si JOIN sales s ON s.id = si.sale_id JOIN products p ON p.id = si.product_id WHERE $where AND " . $this->DeptSql($deptKeys));
			$st->execute($p);
			$r = $st->fetch(\PDO::FETCH_ASSOC);
			$net = floatval($r['gross']);
			$margin = $net - floatval($r['cogs']);
		}
		$tickets = intval($r['tickets']);
		return [
			'tickets' => $tickets,
			'gross_sales' => round(floatval($r['gross']), 2),
			'tax' => round(floatval($r['tax']), 2),
			'net_sales' => round($net, 2),
			'cogs' => round(floatval($r['cogs']), 2),
			'gross_margin' => round($margin, 2),
			'margin_pct' => SalesService::MarginPct($margin, $net),
			'discount' => round(floatval($r['discount']), 2),
			'units' => round(floatval($r['units']), 2),
			'avg_ticket' => $tickets > 0 ? round($net / $tickets, 2) : null,
			'units_per_ticket' => $tickets > 0 ? round(floatval($r['units']) / $tickets, 1) : null,
			'has_data' => $tickets > 0
		];
	}

	/**
	 * Rangos anteriores al corte del archivo histórico (daily_sales_archive, migración 0322) se leen de él, igual que en
	 * SalesReportsService. El archivo es por día completo (sin hora) y sin impuestos: 'full_day' lo indica.
	 */
	private function ArchiveSales(?int $branchId, string $from, string $to, ?array $deptKeys): ?array
	{
		try
		{
			$max = $this->Db()->query('SELECT MAX(day) FROM daily_sales_archive')->fetchColumn();
		}
		catch (\Throwable $e)
		{
			return null;
		}
		// El archivo guarda claves de departamento, no los nombres de producto que usa el filtro: con filtro no se usa
		if (!$max || $to > $max || $deptKeys !== null)
		{
			return null;
		}
		$dept = '';
		$tk = 'a.branch_tickets';
		$st = $this->Db()->prepare("SELECT SUM(a.net) AS net, SUM(a.cogs) AS cogs, SUM(a.units) AS units,
			(SELECT IFNULL(SUM(t), 0) FROM (SELECT MAX($tk) AS t FROM daily_sales_archive a WHERE a.day BETWEEN :f AND :t" . $this->BranchSql($branchId, 'a.branch_id') . "$dept GROUP BY a.day, a.branch_id)) AS tickets
			FROM daily_sales_archive a WHERE a.day BETWEEN :f AND :t" . $this->BranchSql($branchId, 'a.branch_id') . $dept);
		$st->execute([':f' => $from, ':t' => $to]);
		$r = $st->fetch(\PDO::FETCH_ASSOC);
		if ($r['net'] === null)
		{
			return null;
		}
		$net = floatval($r['net']);
		$margin = $net - floatval($r['cogs']);
		$tickets = intval($r['tickets']);
		return [
			'tickets' => $tickets, 'gross_sales' => round($net, 2), 'tax' => 0.0, 'net_sales' => round($net, 2), 'cogs' => round(floatval($r['cogs']), 2),
			'gross_margin' => round($margin, 2), 'margin_pct' => SalesService::MarginPct($margin, $net), 'discount' => 0.0, 'units' => round(floatval($r['units']), 2),
			'avg_ticket' => $tickets > 0 ? round($net / $tickets, 2) : null, 'units_per_ticket' => $tickets > 0 ? round(floatval($r['units']) / $tickets, 1) : null,
			'has_data' => true, 'full_day' => true
		];
	}

	/** Ventas netas, costo, unidades y tickets por clave de departamento */
	public function SalesByDepartment(?int $branchId, string $from, string $to, ?array $deptKeys = null): array
	{
		$out = [];
		foreach (self::Departments() as $k => [$name, $color])
		{
			if ($deptKeys !== null && !in_array($k, $deptKeys, true))
			{
				continue;
			}
			$s = $this->Sales($branchId, $from, $to, [$k]);
			$out[$k] = ['id' => $k, 'name' => self::DepartmentLabel($k), 'color' => $color, 'sales' => $s['net_sales'], 'gross_profit' => $s['gross_margin'],
				'margin_pct' => $s['margin_pct'] ?? 0, 'units' => $s['units'], 'tickets' => $s['tickets']];
		}
		return $out;
	}

	/** Ventas netas acumuladas por hora del día (índice = hora 0..23) */
	public function SalesByHour(?int $branchId, string $date, ?array $deptKeys = null): array
	{
		$hours = array_fill(0, 24, 0.0);
		if ($deptKeys === null)
		{
			$st = $this->Db()->prepare("SELECT CAST(strftime('%H', s.created_timestamp) AS INTEGER) AS h, SUM(s.total_amount - s.tax_amount) AS v FROM sales s
				WHERE s.status = 'completed' AND date(s.created_timestamp) = :d" . $this->BranchSql($branchId, 's.branch_id') . ' GROUP BY h');
		}
		else
		{
			$st = $this->Db()->prepare("SELECT CAST(strftime('%H', s.created_timestamp) AS INTEGER) AS h, SUM(si.subtotal) AS v FROM sale_items si JOIN sales s ON s.id = si.sale_id JOIN products p ON p.id = si.product_id
				WHERE s.status = 'completed' AND date(s.created_timestamp) = :d" . $this->BranchSql($branchId, 's.branch_id') . ' AND ' . $this->DeptSql($deptKeys) . ' GROUP BY h');
		}
		$st->execute([':d' => $date]);
		foreach ($st->fetchAll(\PDO::FETCH_ASSOC) as $r)
		{
			$hours[intval($r['h'])] = round(floatval($r['v']), 2);
		}
		return $hours;
	}

	/** Ventas netas y tickets por día de la semana (1 = lunes) y hora, en el rango */
	public function SalesHeatmap(?int $branchId, string $from, string $to): array
	{
		$st = $this->Db()->prepare("SELECT CAST(strftime('%w', created_timestamp) AS INTEGER) AS w, CAST(strftime('%H', created_timestamp) AS INTEGER) AS h,
			SUM(total_amount - tax_amount) AS v, COUNT(*) AS n FROM sales WHERE status = 'completed' AND date(created_timestamp) BETWEEN :f AND :t" . $this->BranchSql($branchId) . ' GROUP BY w, h');
		$st->execute([':f' => $from, ':t' => $to]);
		$out = [];
		foreach ($st->fetchAll(\PDO::FETCH_ASSOC) as $r)
		{
			$dow = intval($r['w']) === 0 ? 7 : intval($r['w']);
			$out[$dow][intval($r['h'])] = ['sales' => round(floatval($r['v']), 2), 'tickets' => intval($r['n'])];
		}
		return $out;
	}

	/** Devoluciones: ventas anuladas (devolución total) y devoluciones/cambios de cliente registrados */
	public function Returns(?int $branchId, string $from, string $to): array
	{
		$st = $this->Db()->prepare("SELECT COUNT(*), IFNULL(SUM(total_amount - tax_amount), 0) FROM sales WHERE status = 'cancelled' AND date(created_timestamp) BETWEEN :f AND :t" . $this->BranchSql($branchId));
		$st->execute([':f' => $from, ':t' => $to]);
		[$n, $v] = $st->fetch(\PDO::FETCH_NUM);
		$st = $this->Db()->prepare("SELECT COUNT(*), IFNULL(SUM(customer_refund_amount), 0) FROM customer_returns_exchanges WHERE return_type IN ('cambio', 'devolucion_cliente') AND date(created_at) BETWEEN :f AND :t" . $this->BranchSql($branchId));
		$st->execute([':f' => $from, ':t' => $to]);
		[$n2, $v2] = $st->fetch(\PDO::FETCH_NUM);
		return ['count' => intval($n) + intval($n2), 'amount' => round(floatval($v) + floatval($v2), 2)];
	}

	/* ---------------------------------------------------------------- Mermas */

	public function Waste(?int $branchId, string $from, string $to): array
	{
		$st = $this->Db()->prepare("SELECT status, COUNT(*) AS n, IFNULL(SUM(total_cost), 0) AS cost, IFNULL(SUM(amount), 0) AS units FROM waste_records
			WHERE date(created_timestamp) BETWEEN :f AND :t" . $this->BranchSql($branchId) . " AND status IN ('approved', 'pending_approval') GROUP BY status");
		$st->execute([':f' => $from, ':t' => $to]);
		$rows = array_column($st->fetchAll(\PDO::FETCH_ASSOC), null, 'status');
		$a = $rows['approved'] ?? ['n' => 0, 'cost' => 0, 'units' => 0];
		$p = $rows['pending_approval'] ?? ['n' => 0, 'cost' => 0];
		return ['events' => intval($a['n']), 'cost' => round(floatval($a['cost']), 2), 'units' => round(floatval($a['units']), 2),
			'pending_events' => intval($p['n']), 'pending_cost' => round(floatval($p['cost']), 2)];
	}

	/** % de merma sobre la base configurada; null si no hay base (sin ventas ni costo) */
	public function WastePct(float $wasteCost, array $sales): ?float
	{
		$base = BudgetService::WasteBase($sales['gross_sales'], $sales['tax'], $sales['cogs']);
		return BudgetService::Pct($wasteCost, $base['amount']);
	}

	/* ---------------------------------------------------------------- Inventario */

	public function Inventory(?int $branchId, int $expiringDays = 7): array
	{
		$b = ($branchId !== null && $branchId > 0) ? ' AND l.branch_id = ' . intval($branchId) : '';
		$today = date('Y-m-d');
		$limit = date('Y-m-d', strtotime("+$expiringDays days"));
		$r = $this->Db()->query("SELECT IFNULL(SUM(s.amount), 0) AS units, IFNULL(SUM(s.amount * IFNULL(s.price, IFNULL(plp.price, 0))), 0) AS value,
				COUNT(DISTINCT CASE WHEN s.best_before_date >= '$today' AND s.best_before_date <= '$limit' THEN s.product_id END) AS expiring,
				COUNT(DISTINCT CASE WHEN s.best_before_date < '$today' THEN s.product_id END) AS expired,
				IFNULL(SUM(CASE WHEN s.best_before_date >= '$today' AND s.best_before_date <= '$limit' THEN s.amount * IFNULL(s.price, 0) END), 0) AS expiring_value
			FROM stock s JOIN locations l ON l.id = s.location_id LEFT JOIN cache__products_last_purchased plp ON plp.product_id = s.product_id
			WHERE IFNULL(l.is_transit, 0) = 0 $b")->fetch(\PDO::FETCH_ASSOC);
		// Bajo mínimo y agotados se evalúan por sucursal (el mínimo es de cada tienda); la red suma los pares producto-sucursal
		$c = $this->StockStatusCounts($branchId, $expiringDays);
		return ['units' => round(floatval($r['units']), 2), 'value' => round(floatval($r['value']), 2), 'expiring_products' => $c['expiring'],
			'expiring_value' => round(floatval($r['expiring_value']), 2), 'expired_products' => $c['expired'], 'below_min' => $c['below_min'], 'out_of_stock' => $c['out_of_stock'],
			'catalog_with_min' => $c['catalog'], 'expiring_days' => $expiringDays];
	}

	/** Conteos por par producto-sucursal: bajo mínimo, agotado, con lotes por vencer (≤ N días) y con lotes vencidos */
	public function StockStatusCounts(?int $branchId, int $expiringDays = 7, ?array $deptKeys = null): array
	{
		$today = date('Y-m-d');
		$limit = date('Y-m-d', strtotime("+$expiringDays days"));
		$branches = ($branchId !== null && $branchId > 0) ? [intval($branchId)] : array_map('intval', $this->Db()->query('SELECT id FROM branches WHERE IFNULL(active, 1) = 1')->fetchAll(\PDO::FETCH_COLUMN));
		$in = implode(',', $branches ?: [0]);
		$st = $this->Db()->query("SELECT l.branch_id, s.product_id, SUM(s.amount) AS qty,
				MAX(CASE WHEN s.best_before_date >= '$today' AND s.best_before_date <= '$limit' THEN 1 ELSE 0 END) AS exp,
				MAX(CASE WHEN s.best_before_date < '$today' THEN 1 ELSE 0 END) AS expd,
				SUM(CASE WHEN s.best_before_date >= '$today' AND s.best_before_date <= '$limit' AND s.amount > 0 THEN 1 ELSE 0 END) AS exp_lots
			FROM stock s JOIN locations l ON l.id = s.location_id WHERE IFNULL(l.is_transit, 0) = 0 AND l.branch_id IN ($in) GROUP BY l.branch_id, s.product_id");
		$stock = [];
		$expiring = 0;
		$expired = 0;
		$expLots = 0;
		$expBranches = [];
		$deptOk = $deptKeys === null ? null : array_flip(array_map('intval', $this->Db()->query('SELECT p.id FROM products p WHERE ' . $this->DeptSql($deptKeys))->fetchAll(\PDO::FETCH_COLUMN)));
		foreach ($st->fetchAll(\PDO::FETCH_ASSOC) as $r)
		{
			if ($deptOk !== null && !isset($deptOk[intval($r['product_id'])]))
			{
				continue;
			}
			$stock[$r['branch_id']][$r['product_id']] = floatval($r['qty']);
			$expiring += intval($r['exp']);
			$expLots += intval($r['exp_lots']);
			if (intval($r['exp']))
			{
				$expBranches[$r['branch_id']] = true;
			}
			$expired += intval($r['expd']);
		}
		$catalog = $this->Db()->query('SELECT p.id, p.min_stock_amount FROM products p WHERE p.active = 1 AND p.min_stock_amount > 0 AND IFNULL(p.is_serialized, 0) = 0 AND ' . $this->DeptSql($deptKeys))->fetchAll(\PDO::FETCH_KEY_PAIR);
		$below = 0;
		$out = 0;
		$missing = 0.0;
		foreach ($branches as $b)
		{
			foreach ($catalog as $pid => $min)
			{
				$q = $stock[$b][$pid] ?? 0.0;
				if ($q <= 0)
				{
					$out++;
				}
				elseif ($q < floatval($min))
				{
					$below++;
					$missing += floatval($min) - $q;
				}
			}
		}
		return ['below_min' => $below, 'out_of_stock' => $out, 'expiring' => $expiring, 'expired' => $expired, 'catalog' => count($catalog) * count($branches),
			'expiring_lots' => $expLots, 'expiring_branches' => count($expBranches), 'below_min_missing_units' => round($missing, 2)];
	}

	/* ---------------------------------------------------------------- Personal */

	/** Horas programadas (turnos no cancelados, menos pausas), turnos y personas del día */
	public function Labor(?int $branchId, string $date): array
	{
		// Ausente = ausencia aprobada ese día, o turno sin fichaje 30 min después de su inicio cuando la sucursal registra asistencia ese día
		$st = $this->Db()->prepare("SELECT s.start_time, s.end_time, s.break_minutes, s.employee_id,
				EXISTS (SELECT 1 FROM employee_absences a WHERE a.employee_id = s.employee_id AND a.status = 'approved' AND :d BETWEEN a.start_date AND a.end_date) AS on_leave,
				EXISTS (SELECT 1 FROM employee_attendance t WHERE t.shift_id = s.id) AS punched,
				EXISTS (SELECT 1 FROM employee_attendance t JOIN employee_shifts x ON x.id = t.shift_id WHERE x.branch_id = s.branch_id AND x.shift_date = :d) AS tracked
			FROM employee_shifts s WHERE s.shift_date = :d AND s.status <> 'cancelled'" . $this->BranchSql($branchId, 's.branch_id'));
		$st->execute([':d' => $date]);
		$hours = 0.0;
		$absentHours = 0.0;
		$people = [];
		$absent = [];
		$n = 0;
		$now = time();
		foreach ($st->fetchAll(\PDO::FETCH_ASSOC) as $s)
		{
			$a = strtotime("2000-01-01 {$s['start_time']}");
			$e = strtotime("2000-01-01 {$s['end_time']}");
			if ($e <= $a)
			{
				$e += 86400;
			}
			$h = max(0, ($e - $a) / 3600 - intval($s['break_minutes']) / 60);
			$hours += $h;
			$people[$s['employee_id']] = true;
			$n++;
			$started = strtotime("$date {$s['start_time']}") + 1800 <= $now;
			if ($s['on_leave'] || ($s['tracked'] && !$s['punched'] && $started))
			{
				$absentHours += $h;
				$absent[$s['employee_id']] = true;
			}
		}
		return ['shifts' => $n, 'people' => count($people), 'hours' => round($hours, 1), 'absent_hours' => round($absentHours, 1), 'absent_people' => count($absent),
			'present_hours' => round($hours - $absentHours, 1), 'has_data' => $n > 0];
	}

	/**
	 * Horas fichadas del día (entrada → salida, o hasta ahora si el turno sigue abierto, menos la pausa registrada).
	 * null cuando la sucursal no registra asistencia ese día: la productividad se informa entonces sobre horas programadas.
	 */
	public function PunchedHours(?int $branchId, string $date): ?float
	{
		$st = $this->Db()->prepare("SELECT t.clock_in, t.clock_out, t.break_start, t.break_end FROM employee_attendance t JOIN employee_shifts s ON s.id = t.shift_id
			WHERE s.shift_date = :d AND t.clock_in IS NOT NULL" . $this->BranchSql($branchId, 's.branch_id'));
		$st->execute([':d' => $date]);
		$rows = $st->fetchAll(\PDO::FETCH_ASSOC);
		if (!$rows)
		{
			return null;
		}
		$now = time();
		$h = 0.0;
		foreach ($rows as $r)
		{
			$in = strtotime($r['clock_in']);
			$out = $r['clock_out'] ? strtotime($r['clock_out']) : min($now, strtotime("$date 23:59:59"));
			$brk = ($r['break_start'] && $r['break_end']) ? max(0, strtotime($r['break_end']) - strtotime($r['break_start'])) : 0;
			$h += max(0, $out - $in - $brk) / 3600;
		}
		return round($h, 1);
	}

	/**
	 * Cobertura con el mismo alcance que el requerimiento: por hora y departamento, personas programadas vs. mínimo.
	 * covered_h = Σ min(programadas, requeridas) por franja (≤ requeridas); in_window_h = programadas dentro de las franjas.
	 * Si los departamentos de los turnos no coinciden con los de la cobertura, se compara por hora con la dotación total.
	 */
	public function CoverageDetail(?int $branchId, string $date): ?array
	{
		$st = $this->Db()->prepare('SELECT branch_id, department, start_time, end_time, min_staff FROM coverage_requirements WHERE weekday IN (0, :w)' . $this->BranchSql($branchId));
		$st->execute([':w' => intval(date('N', strtotime($date)))]);
		$req = $st->fetchAll(\PDO::FETCH_ASSOC);
		if (!$req)
		{
			return null;
		}
		$st = $this->Db()->prepare("SELECT branch_id, department, start_time, end_time FROM employee_shifts WHERE shift_date = :d AND status <> 'cancelled'" . $this->BranchSql($branchId));
		$st->execute([':d' => $date]);
		$shifts = $st->fetchAll(\PDO::FETCH_ASSOC);
		$reqDepts = array_unique(array_column($req, 'department'));
		$byDept = (bool) array_intersect($reqDepts, array_unique(array_column($shifts, 'department')));
		$hm = fn($t) => intval(substr($t, 0, 2)) + intval(substr($t, 3, 2)) / 60;
		$slots = [];
		foreach ($req as $r)
		{
			for ($h = intval(floor($hm($r['start_time']))); $h < $hm($r['end_time']); $h++)
			{
				$k = $r['branch_id'] . '|' . ($byDept ? $r['department'] : '*') . '|' . $h;
				$slots[$k] = ($slots[$k] ?? ['b' => $r['branch_id'], 'd' => $byDept ? $r['department'] : null, 'h' => $h, 'req' => 0, 'have' => 0]);
				$slots[$k]['req'] += intval($r['min_staff']);
			}
		}
		foreach ($shifts as $s)
		{
			$a = $hm($s['start_time']);
			$e = $hm($s['end_time']);
			if ($e <= $a) $e += 24;
			foreach ($slots as $k => $sl)
			{
				if ($sl['b'] == $s['branch_id'] && ($sl['d'] === null || $sl['d'] === $s['department']) && $a <= $sl['h'] && $e >= $sl['h'] + 1)
				{
					$slots[$k]['have']++;
				}
			}
		}
		$reqH = 0;
		$covered = 0;
		$inWin = 0;
		$gaps = [];
		foreach ($slots as $sl)
		{
			$reqH += $sl['req'];
			$covered += min($sl['have'], $sl['req']);
			$inWin += $sl['have'];
			if ($sl['have'] < $sl['req'])
			{
				$gaps[] = ['hour' => sprintf('%02d:00', $sl['h']), 'dept' => $sl['d'], 'branch_id' => intval($sl['b']), 'req' => $sl['req'], 'have' => $sl['have']];
			}
		}
		usort($gaps, fn($x, $y) => [$x['hour'], $y['req'] - $y['have']] <=> [$y['hour'], $x['req'] - $x['have']]);
		return ['required_h' => $reqH, 'covered_h' => $covered, 'in_window_h' => $inWin, 'pct' => $reqH > 0 ? round($covered / $reqH * 100, 0) : null,
			'by_department' => $byDept, 'departments' => array_values($reqDepts), 'gaps' => $gaps, 'gap_h' => $reqH - $covered];
	}

	/** Horas requeridas por la cobertura mínima configurada para el día (null = sin requerimiento) */
	public function RequiredHours(?int $branchId, string $date): ?float
	{
		$st = $this->Db()->prepare('SELECT start_time, end_time, min_staff FROM coverage_requirements WHERE weekday IN (0, :w)' . $this->BranchSql($branchId));
		$st->execute([':w' => intval(date('N', strtotime($date)))]);
		$req = 0.0;
		foreach ($st->fetchAll(\PDO::FETCH_ASSOC) as $r)
		{
			$req += max(0, (strtotime("2000-01-01 {$r['end_time']}") - strtotime("2000-01-01 {$r['start_time']}")) / 3600) * intval($r['min_staff']);
		}
		return $req > 0 ? round($req, 1) : null;
	}

	/* ---------------------------------------------------------------- Metas */

	/** Meta de ventas del día = meta mensual ÷ días del mes; null = «Sin meta» */
	public function DailySalesTarget(?int $branchId, string $date): ?float
	{
		$t = BudgetService::GetInstance()->GetTargets($branchId, substr($date, 0, 7));
		if ($t['sales_target'] === null)
		{
			return null;
		}
		// La meta de red solo se hereda en la vista de red, nunca como meta de una sucursal
		if ($branchId && $t['sources']['sales'] === 'budget_network')
		{
			return null;
		}
		return round($t['sales_target'] / intval(date('t', strtotime($date))), 2);
	}

	public function Targets(?int $branchId, string $date): array
	{
		return BudgetService::GetInstance()->GetTargets($branchId, substr($date, 0, 7));
	}

	/* ---------------------------------------------------------------- Pedidos, compras, calidad */

	/** Pedidos de clientes: despachados a tiempo (fecha de despacho ≤ fecha solicitada) y vencidos sin despachar */
	public function Orders(?int $branchId, string $from, string $to): array
	{
		$b = $this->BranchSql($branchId);
		$st = $this->Db()->prepare("SELECT COUNT(*), SUM(CASE WHEN requested_date IS NULL OR date(dispatched_at) <= requested_date THEN 1 ELSE 0 END) FROM customer_orders WHERE status = 'dispatched' AND date(dispatched_at) BETWEEN :f AND :t$b");
		$st->execute([':f' => $from, ':t' => $to]);
		[$n, $ok] = $st->fetch(\PDO::FETCH_NUM);
		$late = intval($this->Db()->query("SELECT COUNT(*) FROM customer_orders WHERE status IN ('confirmed', 'picking', 'picked', 'packed') AND requested_date IS NOT NULL AND requested_date < date('now', 'localtime')$b")->fetchColumn());
		$open = intval($this->Db()->query("SELECT COUNT(*) FROM customer_orders WHERE status IN ('confirmed', 'picking', 'picked', 'packed')$b")->fetchColumn());
		return ['dispatched' => intval($n), 'on_time' => intval($ok), 'on_time_pct' => intval($n) > 0 ? round(100 * intval($ok) / intval($n), 1) : null, 'late_open' => $late, 'open' => $open];
	}

	/** Órdenes de compra enviadas cuya fecha comprometida ya pasó sin recibirse */
	public function LateReceipts(?int $branchId): int
	{
		return intval($this->Db()->query("SELECT COUNT(*) FROM procurement_orders WHERE status IN ('sent', 'partially_received') AND expected_delivery_date < date('now', 'localtime')" . $this->BranchSql($branchId))->fetchColumn());
	}

	/** Lotes que requieren intervención de calidad: vencidos con stock, en cuarentena y lecturas de temperatura fuera de rango del día */
	public function QualityIssues(?int $branchId, string $date): array
	{
		$b = ($branchId !== null && $branchId > 0) ? ' AND l.branch_id = ' . intval($branchId) : '';
		$expired = intval($this->Db()->query("SELECT COUNT(DISTINCT s.stock_id) FROM stock s JOIN locations l ON l.id = s.location_id WHERE s.amount > 0 AND s.best_before_date < date('now', 'localtime') AND IFNULL(l.is_transit, 0) = 0$b")->fetchColumn());
		$quarantine = intval($this->Db()->query("SELECT COUNT(*) FROM quarantine_items WHERE status = 'in_quarantine'" . $this->BranchSql($branchId))->fetchColumn());
		$st = $this->Db()->prepare("SELECT COUNT(*) FROM cold_chain_temperatures WHERE status <> 'normal' AND date(recorded_timestamp) = :d" . $this->BranchSql($branchId));
		$st->execute([':d' => $date]);
		$temps = intval($st->fetchColumn());
		return ['expired_lots' => $expired, 'quarantine' => $quarantine, 'temperature_excursions' => $temps, 'total' => $expired + $quarantine + $temps];
	}

	/** Diferencias de inventario pendientes (ajustes por aprobar) */
	public function PendingAdjustments(?int $branchId): int
	{
		return intval($this->Db()->query("SELECT COUNT(*) FROM inventory_adjustments WHERE status = 'pending_approval'" . $this->BranchSql($branchId))->fetchColumn());
	}

	/* ---------------------------------------------------------------- Reglas de estado (únicas para HOY y Panel) */

	// success = en objetivo · warning = atención · danger = crítico · neutral = sin información
	const RULES = [
		'sales_compliance_ok' => 100.0, // % de la meta
		'sales_compliance_warn' => 90.0,
		'margin_warn_pp' => 3.0, // pp por debajo del margen objetivo que aún es «atención»
		'waste_warn_factor' => 1.5, // hasta 1,5 × el objetivo de merma es «atención»
		'coverage_ok' => 95.0,
		'coverage_warn' => 85.0,
		'late_receipts_crit' => 2, // más de N = crítico
		'inventory_diffs_crit' => 3,
		'late_orders_crit' => 2,
		'quality_issues_crit' => 5,
		'turnover_warn_factor' => 0.95
	];

	const LEVELS = [
		'success' => ['ok', 'En objetivo'],
		'warning' => ['warn', 'Atención'],
		'danger' => ['crit', 'Crítico'],
		'neutral' => ['no_data', 'Sin información']
	];

	public static function SalesLevel(?float $compliancePct): string
	{
		if ($compliancePct === null)
		{
			return 'neutral';
		}
		return $compliancePct >= self::RULES['sales_compliance_ok'] ? 'success' : ($compliancePct >= self::RULES['sales_compliance_warn'] ? 'warning' : 'danger');
	}

	public static function MarginLevel(?float $marginPct, ?float $targetPct): string
	{
		if ($marginPct === null || $targetPct === null)
		{
			return 'neutral';
		}
		return $marginPct >= $targetPct ? 'success' : ($marginPct >= $targetPct - self::RULES['margin_warn_pp'] ? 'warning' : 'danger');
	}

	public static function WasteLevel(?float $wastePct, float $targetPct): string
	{
		if ($wastePct === null)
		{
			return 'neutral';
		}
		return $wastePct <= $targetPct ? 'success' : ($wastePct <= $targetPct * self::RULES['waste_warn_factor'] ? 'warning' : 'danger');
	}

	public static function CoverageLevel(?float $coveragePct): string
	{
		if ($coveragePct === null)
		{
			return 'neutral';
		}
		return $coveragePct >= self::RULES['coverage_ok'] ? 'success' : ($coveragePct >= self::RULES['coverage_warn'] ? 'warning' : 'danger');
	}

	/** Conteo de pendientes: 0 = en objetivo, hasta el umbral = atención, más = crítico */
	public static function CountLevel(int $n, string $rule): string
	{
		return $n > self::RULES[$rule] ? 'danger' : ($n > 0 ? 'warning' : 'success');
	}

	/** Estado del Panel ejecutivo (ok/warn/crit/no_data) y su etiqueta para un nivel */
	public static function PanelState(string $level): array
	{
		return self::LEVELS[$level] ?? self::LEVELS['neutral'];
	}

	/* ---------------------------------------------------------------- Contexto y período compartidos */

	/** Fecha de referencia válida (no futura); por defecto hoy */
	public static function AnchorDate(?string $date): string
	{
		$today = date('Y-m-d');
		if (!$date || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || strtotime($date) === false)
		{
			return $today;
		}
		return min($date, $today);
	}

	/**
	 * Rango de un período anclado a la fecha de referencia. Un período que contiene la fecha se corta en ella
	 * («mes hasta el 15»), así HOY y el Panel leen exactamente los mismos días.
	 */
	public static function PeriodRange(string $period, string $anchor, ?string $customStart = null, ?string $customEnd = null): array
	{
		$t = strtotime($anchor);
		switch ($period)
		{
			case 'custom':
				if ($customStart && $customEnd)
				{
					return ['start' => min($customStart, $customEnd), 'end' => min(max($customStart, $customEnd), date('Y-m-d'))];
				}
				// sin rango válido: mes de la fecha
			case 'month':
			default:
				$start = date('Y-m-01', $t);
				$end = date('Y-m-t', $t);
				break;
			case 'day':
				$start = $end = $anchor;
				break;
			case 'week':
				$start = date('Y-m-d', strtotime('monday this week', $t));
				$end = date('Y-m-d', strtotime('sunday this week', $t));
				break;
			case 'quarter':
				$q = intval(floor((intval(date('n', $t)) - 1) / 3)) * 3 + 1;
				$start = date('Y', $t) . sprintf('-%02d-01', $q);
				$end = date('Y-m-t', strtotime(date('Y', $t) . sprintf('-%02d-01', $q + 2)));
				break;
			case 'year':
				$start = date('Y-01-01', $t);
				$end = date('Y-12-31', $t);
				break;
		}
		return ['start' => $start, 'end' => min($end, $anchor)];
	}

	/** Ventas netas por día del rango (fecha => monto); los días sin ventas valen 0 */
	public function DailyNetSales(?int $branchId, string $from, string $to): array
	{
		$st = $this->Db()->prepare("SELECT date(created_timestamp) AS d, SUM(total_amount - tax_amount) AS v FROM sales WHERE status = 'completed' AND date(created_timestamp) BETWEEN :f AND :t" . $this->BranchSql($branchId) . ' GROUP BY d');
		$st->execute([':f' => $from, ':t' => $to]);
		$rows = $st->fetchAll(\PDO::FETCH_KEY_PAIR);
		$out = [];
		for ($d = $from; $d <= $to; $d = date('Y-m-d', strtotime("$d +1 day")))
		{
			$out[$d] = round(floatval($rows[$d] ?? 0), 2);
		}
		return $out;
	}

	/** Meta de ventas del rango = suma de las metas diarias; null si ningún día tiene meta */
	public function PeriodSalesTarget(?int $branchId, string $from, string $to): ?float
	{
		$sum = 0.0;
		$any = false;
		for ($d = $from; $d <= $to; $d = date('Y-m-d', strtotime("$d +1 day")))
		{
			$t = $this->DailySalesTarget($branchId, $d);
			if ($t !== null)
			{
				$sum += $t;
				$any = true;
			}
		}
		return $any ? round($sum, 2) : null;
	}

	/**
	 * Resumen comercial de un rango con sus estados: lo que HOY muestra para un día y el Panel para cualquier período.
	 * Mismas cifras para el mismo contexto (sucursal, rango).
	 */
	public function Summary(?int $branchId, string $from, string $to): array
	{
		$s = $this->Sales($branchId, $from, $to);
		$w = $this->Waste($branchId, $from, $to);
		$targets = $this->Targets($branchId, $to);
		$salesTarget = $this->PeriodSalesTarget($branchId, $from, $to);
		$complianceReal = ($salesTarget && $s['has_data']) ? round($s['net_sales'] / $salesTarget * 100, 1) : null;
		$complianceVisual = $complianceReal !== null ? min(100.0, $complianceReal) : null;
		$overTargetPct = ($complianceReal !== null && $complianceReal > 100) ? round($complianceReal - 100, 1) : 0.0;
		$compliance = $complianceReal;
		$wasteTarget = floatval($targets['waste_target_pct']);
		$wastePct = $s['has_data'] ? $this->WastePct($w['cost'], $s) : null;
		$marginTarget = $targets['margin_target_pct'] !== null ? floatval($targets['margin_target_pct']) : null;
		return [
			'from' => $from,
			'to' => $to,
			'sales' => $s,
			'sales_target' => $salesTarget,
			'compliance_pct' => $compliance,
			'compliance_real_pct' => $complianceReal,
			'compliance_visual_pct' => $complianceVisual,
			'over_target_pct' => $overTargetPct,
			'sales_level' => self::SalesLevel($compliance),
			'margin_target_pct' => $marginTarget,
			'margin_level' => self::MarginLevel($s['margin_pct'], $marginTarget),
			'waste' => $w,
			'waste_pct' => $wastePct,
			'waste_target_pct' => $wasteTarget,
			'waste_level' => self::WasteLevel($wastePct, $wasteTarget),
			'dsi_target_days' => $targets['dsi_target_days'] !== null ? floatval($targets['dsi_target_days']) : null
		];
	}
}
