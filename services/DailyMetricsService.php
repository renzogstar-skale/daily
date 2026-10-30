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
		'dairy' => ['Lácteos y huevos', '#FFB800', ['Lácteos y huevos']],
		'bebidas' => ['Bebidas', '#0EA5E9', ['Bebidas refrigeradas', 'Bebidas']],
		'fruver' => ['Frutas y verduras', '#FF7A00', ['Frutas y verduras']],
		'carnes' => ['Carnes', '#E11D48', ['Carnes']],
		'bakery' => ['Panadería', '#8B5CF6', ['Panadería']],
		'abarrotes' => ['Abarrotes', '#A16207', ['Abarrotes']],
		'congelados' => ['Congelados', '#38BDF8', ['Congelados']],
		'electronica' => ['Electrónica', '#00C83B', ['Electrónica y Telefonía']],
		'otros' => ['Otros', '#64748B', null]
	];

	// Categoría del filtro de HOY => claves de departamento
	const CATEGORIES = [
		'frescos' => ['fruver', 'carnes', 'bakery'],
		'refrigerados' => ['dairy', 'bebidas'],
		'congelados' => ['congelados'],
		'abarrotes' => ['abarrotes', 'otros'],
		'non_food' => ['electronica']
	];

	private function Db(): \PDO
	{
		return DatabaseService::GetInstance()->GetDbConnectionRaw();
	}

	/** Departamentos de producto incluidos por una clave de departamento y/o categoría. null = sin filtro. */
	public function DepartmentFilter(?string $deptKey, ?string $category = null): ?array
	{
		$keys = null;
		$deptKey = strtolower(trim((string) $deptKey));
		if ($deptKey !== '' && $deptKey !== 'todos' && $deptKey !== 'all' && isset(self::DEPARTMENTS[$deptKey]))
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
		foreach (self::DEPARTMENTS as $k => $d)
		{
			if ($d[2] !== null)
			{
				$named = array_merge($named, $d[2]);
			}
		}
		$parts = [];
		foreach ($keys as $k)
		{
			$d = self::DEPARTMENTS[$k] ?? null;
			if ($d === null)
			{
				continue;
			}
			$parts[] = $d[2] === null
				? "IFNULL(p.department, '') NOT IN (" . $this->Quote($named) . ')'
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
	 * Ventas del rango [from, to] (fechas Y-m-d). $untilTime (HH:MM:SS) recorta cada día a esa hora (comparativos del día en curso).
	 * Con filtro de departamento se calcula desde las líneas de esas mismas ventas.
	 */
	public function Sales(?int $branchId, string $from, string $to, ?array $deptKeys = null, ?string $untilTime = null): array
	{
		$time = $untilTime ? " AND time(s.created_timestamp) <= '" . preg_replace('/[^0-9:]/', '', $untilTime) . "'" : '';
		$where = "s.status = 'completed' AND date(s.created_timestamp) BETWEEN :f AND :t" . $this->BranchSql($branchId, 's.branch_id') . $time;
		$p = [':f' => $from, ':t' => $to];
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

	/** Ventas netas, costo, unidades y tickets por clave de departamento */
	public function SalesByDepartment(?int $branchId, string $from, string $to, ?array $deptKeys = null): array
	{
		$out = [];
		foreach (self::DEPARTMENTS as $k => [$name, $color])
		{
			if ($deptKeys !== null && !in_array($k, $deptKeys, true))
			{
				continue;
			}
			$s = $this->Sales($branchId, $from, $to, [$k]);
			$out[$k] = ['id' => $k, 'name' => $name, 'color' => $color, 'sales' => $s['net_sales'], 'gross_profit' => $s['gross_margin'],
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
				MAX(CASE WHEN s.best_before_date < '$today' THEN 1 ELSE 0 END) AS expd
			FROM stock s JOIN locations l ON l.id = s.location_id WHERE IFNULL(l.is_transit, 0) = 0 AND l.branch_id IN ($in) GROUP BY l.branch_id, s.product_id");
		$stock = [];
		$expiring = 0;
		$expired = 0;
		$deptOk = $deptKeys === null ? null : array_flip(array_map('intval', $this->Db()->query('SELECT p.id FROM products p WHERE ' . $this->DeptSql($deptKeys))->fetchAll(\PDO::FETCH_COLUMN)));
		foreach ($st->fetchAll(\PDO::FETCH_ASSOC) as $r)
		{
			if ($deptOk !== null && !isset($deptOk[intval($r['product_id'])]))
			{
				continue;
			}
			$stock[$r['branch_id']][$r['product_id']] = floatval($r['qty']);
			$expiring += intval($r['exp']);
			$expired += intval($r['expd']);
		}
		$catalog = $this->Db()->query('SELECT p.id, p.min_stock_amount FROM products p WHERE p.active = 1 AND p.min_stock_amount > 0 AND IFNULL(p.is_serialized, 0) = 0 AND ' . $this->DeptSql($deptKeys))->fetchAll(\PDO::FETCH_KEY_PAIR);
		$below = 0;
		$out = 0;
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
				}
			}
		}
		return ['below_min' => $below, 'out_of_stock' => $out, 'expiring' => $expiring, 'expired' => $expired, 'catalog' => count($catalog) * count($branches)];
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
		$compliance = ($salesTarget && $s['has_data']) ? round($s['net_sales'] / $salesTarget * 100, 1) : null;
		$wasteTarget = floatval($targets['waste_target_pct']);
		$wastePct = $s['has_data'] ? $this->WastePct($w['cost'], $s) : null;
		$marginTarget = $targets['margin_target_pct'] !== null ? floatval($targets['margin_target_pct']) : null;
		return [
			'from' => $from,
			'to' => $to,
			'sales' => $s,
			'sales_target' => $salesTarget,
			'compliance_pct' => $compliance,
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
