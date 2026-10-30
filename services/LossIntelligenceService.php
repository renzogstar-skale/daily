<?php

namespace Grocy\Services;

/**
 * Inteligencia de mermas, pérdidas y prevención (Centro de Control /waste).
 *
 * Fuente única de eventos de pérdida (Events), cada uno con identificador único y una sola clase:
 *  - W<id>  merma registrada y APROBADA (waste_records). Clase = causal canónica (loss_causes) del motivo.
 *  - A<id>  ajuste de inventario físico (stock_log inventory-correction, signo: + pérdida, − sobrante), excepto
 *           las transacciones que ya son una merma (evita doble conteo).
 *  - J<id>  ajuste aprobado (inventory_adjustments) sin movimiento en stock_log (cargado sin transacción).
 *  - D<id>  avería con pérdida por descarte (damage_incidents) que NO generó merma (waste_record_id vacío y estado
 *           distinto de «discarded_to_waste» / en inspección).
 * Las mermas por aprobar, rechazadas, la tolerancia técnica de perecederos y los errores de precio NO entran al total:
 * se muestran como métricas separadas.
 */
class LossIntelligenceService extends BaseService
{
	const CLASSES = [
		'operativa' => 'Merma operativa',
		'comercial' => 'Merma comercial',
		'ajuste' => 'Ajustes de inventario',
		'averia' => 'Averías y roturas',
		'robo_interno' => 'Robo interno',
		'robo_externo' => 'Robo externo',
		'investigacion' => 'Bajo investigación',
	];

	const MONTHS = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];

	const PRICE_FLOW = ['detected', 'validated', 'evidence', 'corrected', 'approved', 'pos_synced', 'verified', 'closed'];

	const PRICE_KINDS = [
		'pos_vs_authorized' => 'Precio POS ≠ precio autorizado',
		'shelf_vs_pos' => 'Precio de góndola ≠ precio POS',
		'expired_promo' => 'Promoción vencida activa',
		'discount' => 'Descuento mal aplicado',
		'wrong_label' => 'Etiqueta en producto incorrecto',
		'barcode_sku' => 'Código de barras en SKU equivocado',
		'branch_diff' => 'Diferencia de precio entre sucursales',
		'epc' => 'Discrepancia EPC / RFID',
	];

	private function Db(): \PDO
	{
		return DatabaseService::GetInstance()->GetDbConnectionRaw();
	}

	private function Rows(string $sql, array $p = []): array
	{
		$stmt = $this->Db()->prepare($sql);
		$stmt->execute($p);
		return $stmt->fetchAll(\PDO::FETCH_ASSOC);
	}

	public function Setting(string $key, $default = null)
	{
		$stmt = $this->Db()->prepare('SELECT value FROM loss_settings WHERE key = ?');
		$stmt->execute([$key]);
		$v = $stmt->fetchColumn();
		return ($v === false || $v === null || $v === '') ? $default : $v;
	}

	public function SaveSetting(string $key, string $value): void
	{
		$allowed = array_column($this->Rows('SELECT key FROM loss_settings'), 'key');
		if (!in_array($key, $allowed, true))
		{
			throw new \Exception('Parámetro desconocido: ' . $key);
		}
		$this->Db()->prepare('UPDATE loss_settings SET value = ? WHERE key = ?')->execute([trim($value), $key]);
	}

	/**
	 * Filtros comunes: branch_id, group_id (departamento), format, loss_class, supplier_id, product_id.
	 */
	public static function Filters(array $q): array
	{
		$int = fn($k) => isset($q[$k]) && $q[$k] !== '' ? intval($q[$k]) : null;
		$class = isset($q['loss_class']) && isset(self::CLASSES[$q['loss_class']]) ? $q['loss_class'] : null;
		return [
			'branch_id' => $int('branch_id'),
			'group_id' => $int('group_id'),
			'format' => isset($q['format']) && $q['format'] !== '' ? strval($q['format']) : null,
			'loss_class' => $class,
			'supplier_id' => $int('supplier_id'),
			'product_id' => $int('product_id'),
			'cause' => isset($q['cause']) && preg_match('/^[A-Z]{2,5}$/', strval($q['cause'])) ? strval($q['cause']) : null,
		];
	}

	private function ProductWhere(array $f, array &$p, string $pa = 'p', string $ba = 'b'): string
	{
		$w = '';
		if ($f['branch_id'] ?? null)
		{
			$w .= " AND $ba.id = :fb";
			$p[':fb'] = $f['branch_id'];
		}
		if ($f['format'] ?? null)
		{
			$w .= " AND IFNULL($ba.format, '') = :ff";
			$p[':ff'] = $f['format'];
		}
		if ($f['group_id'] ?? null)
		{
			$w .= " AND $pa.product_group_id = :fg";
			$p[':fg'] = $f['group_id'];
		}
		if ($f['supplier_id'] ?? null)
		{
			$w .= " AND $pa.shopping_location_id = :fs";
			$p[':fs'] = $f['supplier_id'];
		}
		if ($f['product_id'] ?? null)
		{
			$w .= " AND $pa.id = :fp";
			$p[':fp'] = $f['product_id'];
		}
		return $w;
	}

	/**
	 * Eventos de pérdida confirmados entre $from y $to (fechas inclusivas). Ver docblock de la clase.
	 */
	public function Events(array $f, string $from, string $to): array
	{
		$p = [':f' => $from, ':t' => $to];
		$pw = $this->ProductWhere($f, $p);
		$classExpr = "COALESCE(lc.loss_class, CASE r.loss_category WHEN 'ajuste' THEN 'ajuste' WHEN 'averia' THEN 'averia' WHEN 'comercial' THEN 'comercial' ELSE 'operativa' END)";
		$sql = "SELECT * FROM (
			SELECT 'W' || w.id AS uid, 'waste' AS src, w.id AS ref, date(w.created_timestamp) AS d, w.branch_id AS branch_id, w.product_id AS product_id,
				$classExpr AS loss_class, COALESCE(NULLIF(r.loss_cause_code, ''), 'INV') AS cause, w.amount AS units, w.total_cost AS cost,
				w.amount * IFNULL(p.sale_price, 0) AS sale_value, IFNULL(w.financial_recovery_amount, 0) AS recovered, w.case_number AS doc
			FROM waste_records w JOIN products p ON p.id = w.product_id LEFT JOIN branches b ON b.id = w.branch_id
			LEFT JOIN waste_reasons r ON r.id = w.waste_reason_id LEFT JOIN loss_causes lc ON lc.code = r.loss_cause_code
			WHERE w.status = 'approved' AND date(w.created_timestamp) BETWEEN :f AND :t$pw
			UNION ALL
			SELECT 'A' || l.id, 'adjustment', l.id, date(l.row_created_timestamp), IFNULL(loc.branch_id, 1), l.product_id, 'ajuste', 'AJI', -l.amount, -l.amount * IFNULL(l.price, 0),
				-l.amount * IFNULL(p.sale_price, 0), 0, l.transaction_id
			FROM stock_log l JOIN products p ON p.id = l.product_id LEFT JOIN locations loc ON loc.id = l.location_id LEFT JOIN branches b ON b.id = IFNULL(loc.branch_id, 1)
			WHERE l.transaction_type = 'inventory-correction' AND l.undone = 0 AND date(l.row_created_timestamp) BETWEEN :f AND :t
				AND IFNULL(l.transaction_id, '') NOT IN (SELECT transaction_id FROM waste_records WHERE transaction_id IS NOT NULL)$pw
			UNION ALL
			SELECT 'J' || ia.id, 'inventory_adjustment', ia.id, date(ia.created_timestamp), ia.branch_id, ia.product_id, 'ajuste', 'AJI', -ia.difference_amount, -ia.total_cost_impact,
				-ia.difference_amount * IFNULL(p.sale_price, 0), 0, ia.adjustment_number
			FROM inventory_adjustments ia JOIN products p ON p.id = ia.product_id LEFT JOIN branches b ON b.id = ia.branch_id
			WHERE ia.status = 'approved' AND date(ia.created_timestamp) BETWEEN :f AND :t
				AND (ia.transaction_id IS NULL OR ia.transaction_id NOT IN (SELECT transaction_id FROM stock_log WHERE transaction_id IS NOT NULL))
				AND ia.id NOT IN (SELECT inventory_adjustment_id FROM waste_records WHERE inventory_adjustment_id IS NOT NULL)$pw
			UNION ALL
			SELECT 'D' || di.id, 'damage', di.id, date(di.created_timestamp), di.branch_id, di.product_id, 'averia', 'AVE', di.units_discarded, di.discard_loss_cost,
				di.units_discarded * IFNULL(p.sale_price, 0), IFNULL(di.recovered_value, 0), di.incident_number
			FROM damage_incidents di JOIN products p ON p.id = di.product_id LEFT JOIN branches b ON b.id = di.branch_id
			WHERE di.waste_record_id IS NULL AND di.status NOT IN ('discarded_to_waste', 'in_inspection', 'cancelled') AND IFNULL(di.discard_loss_cost, 0) <> 0
				AND date(di.created_timestamp) BETWEEN :f AND :t
				AND di.id NOT IN (SELECT damage_incident_id FROM waste_records WHERE damage_incident_id IS NOT NULL)$pw
		) e";
		$where = [];
		if ($f['loss_class'] ?? null)
		{
			$where[] = 'e.loss_class = :fc';
			$p[':fc'] = $f['loss_class'];
		}
		if ($f['cause'] ?? null)
		{
			$where[] = 'e.cause = :fca';
			$p[':fca'] = $f['cause'];
		}
		if ($where)
		{
			$sql .= ' WHERE ' . implode(' AND ', $where);
		}
		$rows = $this->Rows($sql . ' ORDER BY e.d', $p);
		foreach ($rows as &$r)
		{
			$r['units'] = floatval($r['units']);
			$r['cost'] = floatval($r['cost']);
			$r['sale_value'] = floatval($r['sale_value']);
			$r['recovered'] = floatval($r['recovered']);
		}
		return $rows;
	}

	/**
	 * Ventas netas (sin impuesto) del período. Con filtro de producto/departamento/proveedor se usan las líneas de venta.
	 * Devuelve [ventas netas, margen bruto] — el margen solo se conoce a nivel de venta completa.
	 */
	public function NetSales(array $f, string $from, string $to): array
	{
		$p = [':f' => $from, ':t' => $to];
		$byProduct = ($f['group_id'] ?? null) || ($f['supplier_id'] ?? null) || ($f['product_id'] ?? null);
		if ($byProduct)
		{
			$pw = $this->ProductWhere($f, $p);
			$r = $this->Rows("SELECT IFNULL(SUM(si.subtotal), 0) AS net, IFNULL(SUM(si.subtotal - IFNULL(si.total_cost, 0)), 0) AS gm
				FROM sale_items si JOIN sales s ON s.id = si.sale_id JOIN products p ON p.id = si.product_id LEFT JOIN branches b ON b.id = s.branch_id
				WHERE s.status = 'completed' AND date(s.created_timestamp) BETWEEN :f AND :t$pw", $p)[0];
		}
		else
		{
			$w = '';
			if ($f['branch_id'] ?? null)
			{
				$w .= ' AND b.id = :fb';
				$p[':fb'] = $f['branch_id'];
			}
			if ($f['format'] ?? null)
			{
				$w .= " AND IFNULL(b.format, '') = :ff";
				$p[':ff'] = $f['format'];
			}
			$r = $this->Rows("SELECT IFNULL(SUM(s.total_amount - IFNULL(s.tax_amount, 0)), 0) AS net, IFNULL(SUM(s.gross_margin), 0) AS gm
				FROM sales s LEFT JOIN branches b ON b.id = s.branch_id WHERE s.status = 'completed' AND date(s.created_timestamp) BETWEEN :f AND :t$w", $p)[0];
		}
		return [floatval($r['net']), floatval($r['gm'])];
	}

	private static function Pct(float $part, float $whole): ?float
	{
		return $whole != 0.0 ? round($part / $whole * 100, 2) : null;
	}

	private static function Var(float $cur, float $prev): ?float
	{
		return $prev != 0.0 ? round(($cur - $prev) / abs($prev) * 100, 2) : null;
	}

	private static function PrevRange(string $from, string $to): array
	{
		$days = (strtotime($to) - strtotime($from)) / 86400 + 1;
		return [date('Y-m-d', strtotime($from . " -$days days")), date('Y-m-d', strtotime($from . ' -1 day'))];
	}

	/**
	 * Recuperación: importe efectivamente recuperado / pérdida recuperable × 100.
	 * Recuperable = créditos solicitados a proveedor + costo de mermas con causal recuperable que no tienen devolución
	 * a proveedor (mismo número de caso) — así una pérdida no se cuenta dos veces como recuperable.
	 */
	public function Recovery(array $f, string $from, string $to): array
	{
		$p = [':f' => $from, ':t' => $to];
		$pw = $this->ProductWhere($f, $p);
		$sr = $this->Rows("SELECT IFNULL(SUM(sr.credit_requested_amount), 0) AS req, IFNULL(SUM(sr.credit_recovered_amount), 0) AS rec, COUNT(*) AS n
			FROM supplier_returns sr JOIN products p ON p.id = sr.product_id LEFT JOIN branches b ON b.id = sr.branch_id
			WHERE date(sr.created_timestamp) BETWEEN :f AND :t$pw", $p)[0];
		$w = $this->Rows("SELECT IFNULL(SUM(CASE WHEN lc.recoverable = 1 THEN w.total_cost ELSE 0 END), 0) AS recoverable, IFNULL(SUM(IFNULL(w.financial_recovery_amount, 0)), 0) AS rec, COUNT(CASE WHEN IFNULL(w.financial_recovery_amount, 0) > 0 THEN 1 END) AS n
			FROM waste_records w JOIN products p ON p.id = w.product_id LEFT JOIN branches b ON b.id = w.branch_id
			LEFT JOIN waste_reasons r ON r.id = w.waste_reason_id LEFT JOIN loss_causes lc ON lc.code = r.loss_cause_code
			WHERE w.status = 'approved' AND date(w.created_timestamp) BETWEEN :f AND :t$pw
				AND IFNULL(w.case_number, '') NOT IN (SELECT case_number FROM supplier_returns WHERE case_number IS NOT NULL)", $p)[0];
		$recoverable = floatval($sr['req']) + floatval($w['recoverable']);
		$recovered = floatval($sr['rec']) + floatval($w['rec']);
		return [
			'recoverable' => round($recoverable, 2),
			'recovered' => round($recovered, 2),
			'pct' => self::Pct($recovered, $recoverable),
			'supplier' => ['requested' => round(floatval($sr['req']), 2), 'recovered' => round(floatval($sr['rec']), 2), 'count' => intval($sr['n'])],
			'internal' => ['recoverable' => round(floatval($w['recoverable']), 2), 'recovered' => round(floatval($w['rec']), 2), 'count' => intval($w['n'])],
		];
	}

	/**
	 * Horas trabajadas reales (fichajes) y, si no hay, horas programadas (turnos). null = sin datos de personal.
	 */
	public function LaborHours(?int $branchId, string $from, string $to): array
	{
		$p = [':f' => $from, ':t' => $to] + ($branchId ? [':b' => $branchId] : []);
		$att = floatval($this->Rows('SELECT IFNULL(SUM((julianday(a.clock_out) - julianday(a.clock_in)) * 24), 0) AS h FROM employee_attendance a JOIN employees e ON e.id = a.employee_id
			WHERE a.clock_out IS NOT NULL AND date(a.clock_in) BETWEEN :f AND :t' . ($branchId ? ' AND e.branch_id = :b' : ''), $p)[0]['h']);
		$sched = floatval($this->Rows("SELECT IFNULL(SUM((julianday(shift_date || ' ' || end_time) - julianday(shift_date || ' ' || start_time)) * 24 - IFNULL(break_minutes, 0) / 60.0), 0) AS h
			FROM employee_shifts WHERE status NOT IN ('cancelled') AND shift_date BETWEEN :f AND :t" . ($branchId ? ' AND branch_id = :b' : ''), $p)[0]['h']);
		$emp = intval($this->Rows('SELECT COUNT(*) AS n FROM employees WHERE active = 1' . ($branchId ? ' AND branch_id = :b' : ''), $branchId ? [':b' => $branchId] : [])[0]['n']);
		$hours = $att > 0 ? $att : ($sched > 0 ? $sched : null);
		return ['hours' => $hours !== null ? round($hours, 1) : null, 'source' => $att > 0 ? 'fichajes' : ($sched > 0 ? 'turnos programados' : null), 'employees' => $emp ?: null];
	}

	private static function Sum(array $events, ?callable $where = null, string $col = 'cost'): float
	{
		$s = 0.0;
		foreach ($events as $e)
		{
			if ($where === null || $where($e))
			{
				$s += $e[$col];
			}
		}
		return $s;
	}

	/**
	 * KPI superiores del Centro de Control. Cada KPI trae su enlace a movimientos de origen (drill).
	 */
	public function Summary(array $f, string $from, string $to): array
	{
		$ev = $this->Events($f, $from, $to);
		[$prevFrom, $prevTo] = self::PrevRange($from, $to);
		$yFrom = date('Y-m-d', strtotime($from . ' -1 year'));
		$yTo = date('Y-m-d', strtotime($to . ' -1 year'));
		$evY = $this->Events($f, $yFrom, $yTo);
		[$sales, $gm] = $this->NetSales($f, $from, $to);
		$total = self::Sum($ev);
		$totalY = self::Sum($evY);
		$by = [];
		foreach (array_keys(self::CLASSES) as $c)
		{
			$by[$c] = self::Sum($ev, fn($e) => $e['loss_class'] === $c);
		}
		$adjLoss = self::Sum($ev, fn($e) => $e['loss_class'] === 'ajuste' && $e['cost'] > 0);
		$adjGain = -self::Sum($ev, fn($e) => $e['loss_class'] === 'ajuste' && $e['cost'] < 0);
		$theft = $by['robo_interno'] + $by['robo_externo'];
		$rec = $this->Recovery($f, $from, $to);
		$labor = $this->LaborHours($f['branch_id'] ?? null, $from, min($to, date('Y-m-d')));
		$saleValue = self::Sum($ev, null, 'sale_value');

		$pPend = [':f' => $from, ':t' => $to];
		$pw = $this->ProductWhere($f, $pPend);
		$pending = $this->Rows("SELECT COUNT(*) AS n, IFNULL(SUM(w.total_cost), 0) AS v FROM waste_records w JOIN products p ON p.id = w.product_id LEFT JOIN branches b ON b.id = w.branch_id
			WHERE w.status = 'pending_approval' AND date(w.created_timestamp) BETWEEN :f AND :t$pw", $pPend)[0];

		$kpi = fn($v, $pct, $extra = []) => array_merge(['value' => round($v, 2), 'pct_sales' => $pct], $extra);
		$q = fn(array $extra) => '?' . http_build_query(array_filter(array_merge(['branch_id' => $f['branch_id'] ?? null, 'start_date' => $from, 'end_date' => $to], $extra), fn($v) => $v !== null && $v !== ''));
		return [
			'from' => $from, 'to' => $to, 'prev_year' => [$yFrom, $yTo],
			'net_sales' => round($sales, 2),
			'gross_margin' => round($gm, 2),
			'events' => count($ev),
			'total' => $kpi($total, self::Pct($total, $sales), ['drill' => $q([]) . '#tab-registro']),
			'operativa' => $kpi($by['operativa'], self::Pct($by['operativa'], $sales), ['share' => self::Pct($by['operativa'], $total), 'drill' => $q(['loss_class' => 'operativa']) . '#tab-clasificacion']),
			'comercial' => $kpi($by['comercial'], self::Pct($by['comercial'], $sales), ['share' => self::Pct($by['comercial'], $total), 'drill' => $q(['loss_class' => 'comercial']) . '#tab-clasificacion']),
			'ajuste' => $kpi($by['ajuste'], self::Pct($by['ajuste'], $sales), ['share' => self::Pct($by['ajuste'], $total), 'loss' => round($adjLoss, 2), 'gain' => round($adjGain, 2), 'drill' => $q(['loss_class' => 'ajuste']) . '#tab-clasificacion']),
			'averia' => $kpi($by['averia'], self::Pct($by['averia'], $sales), ['share' => self::Pct($by['averia'], $total), 'drill' => $q(['loss_class' => 'averia']) . '#tab-clasificacion']),
			'robo' => $kpi($theft, self::Pct($theft, $sales), ['share' => self::Pct($theft, $total), 'internal' => round($by['robo_interno'], 2), 'external' => round($by['robo_externo'], 2), 'drill' => $q([]) . '#tab-robo']),
			'investigacion' => $kpi($by['investigacion'], self::Pct($by['investigacion'], $sales), ['drill' => $q([]) . '#tab-investigaciones']),
			'margin_impact' => [
				'value' => round($total, 2),
				'pp' => self::Pct($total, $sales),
				'margin_before_pct' => self::Pct($gm, $sales),
				'margin_after_pct' => self::Pct($gm - $total, $sales),
			],
			'sale_value' => round($saleValue, 2),
			'recovery' => $rec + ['drill' => $q([]) . '#tab-recuperacion'],
			'yoy' => ['prev' => round($totalY, 2), 'diff' => round($total - $totalY, 2), 'pct' => self::Var($total, $totalY), 'has_prev' => count($evY) > 0, 'drill' => $q([]) . '#tab-anual'],
			'labor' => $labor + [
				'per_employee' => $labor['employees'] ? round($total / $labor['employees'], 2) : null,
				'per_hour' => $labor['hours'] ? round($total / $labor['hours'], 2) : null,
			],
			'pending' => ['count' => intval($pending['n']), 'value' => round(floatval($pending['v']), 2)],
			'by_class' => array_map(fn($v) => round($v, 2), $by),
			'prev_period_total' => round(self::Sum($this->Events($f, $prevFrom, $prevTo)), 2),
		];
	}

	public function AvailableYears(): array
	{
		$y = array_map('intval', array_column($this->Rows("SELECT DISTINCT strftime('%Y', created_timestamp) AS y FROM waste_records WHERE created_timestamp IS NOT NULL
			UNION SELECT DISTINCT strftime('%Y', row_created_timestamp) FROM stock_log WHERE transaction_type = 'inventory-correction'
			UNION SELECT DISTINCT strftime('%Y', created_timestamp) FROM sales"), 'y'));
		$y[] = intval(date('Y'));
		$y[] = intval(date('Y')) - 1;
		$y = array_values(array_unique(array_filter($y)));
		sort($y);
		return $y;
	}

	/**
	 * Comparativo mensual y acumulado por año. $metric: cost ($), pct (% de ventas netas) o units.
	 * Los meses futuros del año en curso quedan en null (no se dibuja un acumulado plano inventado).
	 */
	public function YearComparison(array $f, array $years, string $metric = 'cost'): array
	{
		$metric = in_array($metric, ['cost', 'pct', 'units'], true) ? $metric : 'cost';
		$years = array_values(array_unique(array_map('intval', $years)));
		sort($years);
		$out = ['metric' => $metric, 'labels' => self::MONTHS, 'years' => [], 'available_years' => $this->AvailableYears()];
		$nowY = intval(date('Y'));
		$nowM = intval(date('n'));
		foreach ($years as $y)
		{
			$ev = $this->Events($f, "$y-01-01", "$y-12-31");
			$m = array_fill(0, 12, ['cost' => 0.0, 'units' => 0.0, 'sales' => 0.0, 'by_class' => array_fill_keys(array_keys(self::CLASSES), 0.0)]);
			foreach ($ev as $e)
			{
				$i = intval(substr($e['d'], 5, 2)) - 1;
				$m[$i]['cost'] += $e['cost'];
				$m[$i]['units'] += $e['units'];
				$m[$i]['by_class'][$e['loss_class']] += $e['cost'];
			}
			for ($i = 0; $i < 12; $i++)
			{
				$mf = sprintf('%d-%02d-01', $y, $i + 1);
				$m[$i]['sales'] = $this->NetSales($f, $mf, date('Y-m-t', strtotime($mf)))[0];
			}
			$monthly = [];
			$cum = [];
			$cc = $cu = $cs = 0.0;
			for ($i = 0; $i < 12; $i++)
			{
				$future = $y > $nowY || ($y === $nowY && $i + 1 > $nowM);
				$cc += $m[$i]['cost'];
				$cu += $m[$i]['units'];
				$cs += $m[$i]['sales'];
				if ($future)
				{
					$monthly[] = null;
					$cum[] = null;
					continue;
				}
				$monthly[] = match ($metric) { 'cost' => round($m[$i]['cost'], 2), 'units' => round($m[$i]['units'], 2), 'pct' => self::Pct($m[$i]['cost'], $m[$i]['sales']) };
				$cum[] = match ($metric) { 'cost' => round($cc, 2), 'units' => round($cu, 2), 'pct' => self::Pct($cc, $cs) };
			}
			$top = [];
			foreach ($m as $i => $row)
			{
				if ($row['cost'] > 0)
				{
					$top[] = ['month' => self::MONTHS[$i], 'cost' => round($row['cost'], 2)];
				}
			}
			usort($top, fn($a, $b) => $b['cost'] <=> $a['cost']);
			$out['years'][] = [
				'year' => $y,
				'events' => count($ev),
				'monthly' => $monthly,
				'cumulative' => $cum,
				'total_cost' => round($cc, 2),
				'by_class' => array_map(fn($row) => array_map(fn($v) => round($v, 2), $row['by_class']), $m),
				'top_months' => array_slice($top, 0, 3),
			];
		}
		$out['comparison'] = $this->CompareYears($out['years'], $metric);
		return $out;
	}

	/**
	 * Diferencia absoluta y % entre el último y el penúltimo año, al mismo mes (último mes con dato del año reciente),
	 * con una explicación textual construida solo con los datos.
	 */
	private function CompareYears(array $years, string $metric): ?array
	{
		if (count($years) < 2)
		{
			return null;
		}
		$a = $years[count($years) - 2];
		$b = $years[count($years) - 1];
		$idx = null;
		for ($i = 11; $i >= 0; $i--)
		{
			if ($b['cumulative'][$i] !== null)
			{
				$idx = $i;
				break;
			}
		}
		if ($idx === null)
		{
			return null;
		}
		$va = floatval($a['cumulative'][$idx] ?? 0);
		$vb = floatval($b['cumulative'][$idx] ?? 0);
		$diff = round($vb - $va, 2);
		$pct = self::Var($vb, $va);
		$unit = ['cost' => '$', 'pct' => '% de ventas', 'units' => 'unidades'][$metric];
		$fmt = fn($v) => $metric === 'cost' ? '$' . number_format($v, 2) : number_format($v, 2) . ($metric === 'pct' ? '%' : ' u.');
		$lines = [];
		if ($a['events'] === 0)
		{
			$lines[] = "No hay histórico de {$a['year']}: la comparación necesita importar o registrar ese año (Datos → Importación).";
		}
		else
		{
			$trend = $diff > 0 ? 'deterioro' : ($diff < 0 ? 'mejora' : 'sin cambio');
			$lines[] = sprintf('A %s, %d acumula %s frente a %s en %d (%s%s): %s.', self::MONTHS[$idx], $b['year'], $fmt($vb), $fmt($va), $a['year'],
				$diff >= 0 ? '+' : '', $pct !== null ? number_format($pct, 1) . '%' : $fmt($diff), $trend);
		}
		if (!empty($b['top_months']))
		{
			$lines[] = "Meses con mayor pérdida en {$b['year']}: " . implode(', ', array_map(fn($t) => $t['month'] . ' ($' . number_format($t['cost'], 2) . ')', $b['top_months'])) . '.';
		}
		// Clase que más explica la diferencia
		$classDiff = [];
		foreach (array_keys(self::CLASSES) as $c)
		{
			$sa = $sb = 0.0;
			for ($i = 0; $i <= $idx; $i++)
			{
				$sa += $a['by_class'][$i][$c];
				$sb += $b['by_class'][$i][$c];
			}
			$classDiff[$c] = $sb - $sa;
		}
		arsort($classDiff);
		$worst = array_key_first($classDiff);
		if ($a['events'] > 0 && $classDiff[$worst] > 0)
		{
			$lines[] = sprintf('La mayor alza viene de %s (+$%s).', mb_strtolower(self::CLASSES[$worst]), number_format($classDiff[$worst], 2));
		}
		return ['base_year' => $a['year'], 'year' => $b['year'], 'month_index' => $idx, 'month' => self::MONTHS[$idx], 'base' => $va, 'current' => $vb,
			'diff' => $diff, 'pct' => $pct, 'unit' => $unit, 'trend' => $a['events'] === 0 ? 'sin_historico' : ($diff > 0 ? 'deterioro' : ($diff < 0 ? 'mejora' : 'igual')),
			'class_diff' => array_map(fn($v) => round($v, 2), $classDiff), 'explanation' => $lines];
	}

	/**
	 * Matriz de causales con distribución monetaria y % (Pareto acumulado).
	 */
	public function CauseMatrix(array $f, string $from, string $to): array
	{
		$ev = $this->Events($f, $from, $to);
		$causes = [];
		foreach ($this->Rows('SELECT * FROM loss_causes WHERE active = 1 ORDER BY code') as $c)
		{
			$causes[$c['code']] = $c + ['cost' => 0.0, 'units' => 0.0, 'events' => 0];
		}
		foreach ($ev as $e)
		{
			if (!isset($causes[$e['cause']]))
			{
				continue;
			}
			$causes[$e['cause']]['cost'] += $e['cost'];
			$causes[$e['cause']]['units'] += $e['units'];
			$causes[$e['cause']]['events']++;
		}
		$total = array_sum(array_map(fn($c) => max(0, $c['cost']), $causes));
		$list = array_values($causes);
		usort($list, fn($a, $b) => $b['cost'] <=> $a['cost']);
		$cum = 0.0;
		foreach ($list as &$c)
		{
			$c['cost'] = round($c['cost'], 2);
			$c['pct'] = self::Pct(max(0, $c['cost']), $total);
			$cum += max(0, $c['cost']);
			$c['cum_pct'] = self::Pct($cum, $total);
			$c['vital'] = $c['cost'] > 0 && ($c['cum_pct'] ?? 100) - ($c['pct'] ?? 0) < 80;
		}
		$reasons = $this->Rows("
			SELECT r.id, r.code, r.name, r.loss_cause_code, r.active,
			       COUNT(w.id) AS record_count,
			       IFNULL(SUM(w.total_cost), 0) AS period_cost
			FROM waste_reasons r
			LEFT JOIN waste_records w ON w.waste_reason_id = r.id AND w.status = 'approved' AND date(w.created_timestamp) BETWEEN ? AND ?
			GROUP BY r.id
			ORDER BY r.loss_cause_code, r.name
		", [$from, $to]);
		return ['total' => round($total, 2), 'causes' => $list, 'reasons' => $reasons];
	}

	/**
	 * Distribución por clase con detalle hasta SKU. Para la pestaña Clasificación (operativa, comercial, ajustes, averías).
	 */
	public function Classification(array $f, string $from, string $to): array
	{
		$ev = $this->Events($f, $from, $to);
		[$sales] = $this->NetSales($f, $from, $to);
		$total = self::Sum($ev);
		$names = $this->ProductNames(array_unique(array_column($ev, 'product_id')));
		$causeRows = $this->Rows('SELECT code, name FROM loss_causes WHERE active = 1');
		$causeCatalog = [];
		foreach ($causeRows as $cr)
		{
			$causeCatalog[$cr['code']] = $cr['name'];
		}
		$out = [];
		foreach (['operativa', 'comercial', 'ajuste', 'averia'] as $c)
		{
			$rows = array_values(array_filter($ev, fn($e) => $e['loss_class'] === $c));
			$sku = [];
			$cause = [];
			foreach ($rows as $e)
			{
				$k = $e['product_id'];
				$sku[$k] ??= ['product_id' => $k, 'cost' => 0.0, 'units' => 0.0, 'events' => 0, 'causes' => []] + ($names[$k] ?? []);
				$sku[$k]['cost'] += $e['cost'];
				$sku[$k]['units'] += $e['units'];
				$sku[$k]['events']++;
				$sku[$k]['causes'][$e['cause']] = ($sku[$k]['causes'][$e['cause']] ?? 0) + $e['cost'];
				$cause[$e['cause']] = ($cause[$e['cause']] ?? 0) + $e['cost'];
			}
			$sum = self::Sum($rows);
			foreach ($sku as &$s)
			{
				if (!empty($s['causes']))
				{
					arsort($s['causes']);
					$s['main_cause'] = array_key_first($s['causes']);
				}
				else
				{
					$s['main_cause'] = '—';
				}
				$s['pct_class'] = $sum > 0 ? round($s['cost'] / $sum * 100, 1) : 0;
			}
			unset($s);
			usort($sku, fn($a, $b) => abs($b['cost']) <=> abs($a['cost']));
			arsort($cause);
			$out[$c] = [
				'label' => self::CLASSES[$c], 'cost' => round($sum, 2), 'pct_sales' => self::Pct($sum, $sales), 'share' => self::Pct($sum, $total),
				'events' => count($rows), 'skus' => array_values($sku), 'causes' => array_map(fn($v) => round($v, 2), $cause),
				'recent' => array_values(array_reverse($rows)),
			];
		}
		return ['net_sales' => round($sales, 2), 'total' => round($total, 2), 'classes' => $out, 'names' => $names, 'cause_names' => $causeCatalog];
	}

	public function ProductNames(array $ids): array
	{
		$ids = array_values(array_filter(array_map('intval', $ids)));
		if (!$ids)
		{
			return [];
		}
		$rows = $this->Rows('SELECT p.id, p.name, IFNULL(p.sku, \'\') AS sku, IFNULL(pg.name, \'Sin departamento\') AS department,
			p.picture_file_name, IFNULL(qu.name, \'unid.\') AS qu_name,
			(SELECT barcode FROM product_barcodes pb WHERE pb.product_id = p.id ORDER BY pb.id LIMIT 1) AS barcode
			FROM products p
			LEFT JOIN product_groups pg ON pg.id = p.product_group_id
			LEFT JOIN quantity_units qu ON qu.id = p.qu_id_stock
			WHERE p.id IN (' . implode(',', $ids) . ')');
		$o = [];
		foreach ($rows as $r)
		{
			$o[intval($r['id'])] = [
				'name' => $r['name'],
				'sku' => $r['sku'],
				'department' => $r['department'],
				'barcode' => $r['barcode'],
				'picture_file_name' => $r['picture_file_name'],
				'qu_name' => $r['qu_name']
			];
		}
		return $o;
	}

	/**
	 * Panel de hurto: solo eventos CONFIRMADOS (merma aprobada con causal de robo). Las diferencias sin confirmar van
	 * aparte como «bajo investigación» y nunca se etiquetan como robo.
	 */
	public function Theft(array $f, string $from, string $to): array
	{
		$f2 = $f;
		$f2['loss_class'] = null;
		$ev = $this->Events($f2, $from, $to);
		[$prevFrom, $prevTo] = self::PrevRange($from, $to);
		$evPrev = array_filter($this->Events($f2, $prevFrom, $prevTo), fn($e) => str_starts_with($e['loss_class'], 'robo_'));
		$theft = array_values(array_filter($ev, fn($e) => str_starts_with($e['loss_class'], 'robo_')));
		$inv = array_values(array_filter($ev, fn($e) => $e['loss_class'] === 'investigacion'));
		$names = $this->ProductNames(array_unique(array_column($theft, 'product_id')));
		$branches = array_column($this->Rows('SELECT id, name FROM branches'), 'name', 'id');
		$total = self::Sum($theft);
		$totalUnits = self::Sum($theft, null, 'units');
		$prevBy = [];
		foreach ($evPrev as $e)
		{
			$prevBy[$e['product_id']] = ($prevBy[$e['product_id']] ?? 0) + $e['units'];
		}
		$sku = [];
		$dept = [];
		$branch = [];
		$monthly = [];
		foreach ($theft as $e)
		{
			$k = $e['product_id'];
			$type = $e['loss_class'] === 'robo_interno' ? 'Interno' : 'Externo';
			$sku[$k] ??= ['product_id' => $k, 'units' => 0.0, 'cost' => 0.0, 'incidents' => 0, 'types' => []] + ($names[$k] ?? ['name' => '#' . $k, 'sku' => '', 'department' => '']);
			$sku[$k]['units'] += $e['units'];
			$sku[$k]['cost'] += $e['cost'];
			$sku[$k]['incidents']++;
			$sku[$k]['types'][$type] = true;
			$d = $names[$k]['department'] ?? 'Sin departamento';
			$dept[$d] = ($dept[$d] ?? 0) + $e['cost'];
			$bn = $branches[$e['branch_id']] ?? ('#' . $e['branch_id']);
			$sku[$k]['branches'][$bn] = true;
			$branch[$bn] = ($branch[$bn] ?? 0) + $e['cost'];
			$mk = substr($e['d'], 0, 7);
			$monthly[$mk] ??= ['internal' => 0.0, 'external' => 0.0];
			$monthly[$mk][$type === 'Interno' ? 'internal' : 'external'] += $e['cost'];
		}
		foreach ($sku as &$s)
		{
			$s['type'] = implode(' / ', array_keys($s['types']));
			$s['branch'] = implode(', ', array_keys($s['branches'] ?? []));
			unset($s['types'], $s['branches']);
			$s['pct'] = self::Pct($s['cost'], $total);
			$s['pct_units'] = self::Pct($s['units'], $totalUnits);
			$prev = $prevBy[$s['product_id']] ?? 0.0;
			$s['trend'] = self::Var($s['units'], $prev);
			$s['trend_label'] = $prev == 0.0 ? 'nuevo' : ($s['units'] > $prev ? 'sube' : ($s['units'] < $prev ? 'baja' : 'igual'));
		}
		unset($s);
		$byUnits = array_values($sku);
		usort($byUnits, fn($a, $b) => $b['units'] <=> $a['units']);
		$byCost = array_values($sku);
		usort($byCost, fn($a, $b) => $b['cost'] <=> $a['cost']);
		arsort($dept);
		arsort($branch);
		ksort($monthly);
		return [
			'internal' => round(self::Sum($theft, fn($e) => $e['loss_class'] === 'robo_interno'), 2),
			'external' => round(self::Sum($theft, fn($e) => $e['loss_class'] === 'robo_externo'), 2),
			'total' => round($total, 2),
			'units' => round($totalUnits, 2),
			'incidents' => count($theft),
			'recovered' => round(self::Sum($theft, null, 'recovered'), 2),
			'top_units' => array_slice($byUnits, 0, 10),
			'top_cost' => array_slice($byCost, 0, 10),
			'by_department' => array_map(fn($v) => round($v, 2), $dept),
			'by_branch' => array_map(fn($v) => round($v, 2), $branch),
			'monthly' => $monthly,
			'investigation' => ['count' => count($inv), 'cost' => round(self::Sum($inv), 2)],
			'investigation_list' => array_slice(array_map(fn($e) => $e + [
				'product' => ($this->ProductNames([$e['product_id']])[$e['product_id']]['name'] ?? ('#' . $e['product_id'])),
				'sku' => ($this->ProductNames([$e['product_id']])[$e['product_id']]['sku'] ?? ''),
				'department' => ($this->ProductNames([$e['product_id']])[$e['product_id']]['department'] ?? 'General'),
				'qu_name' => ($this->ProductNames([$e['product_id']])[$e['product_id']]['qu_name'] ?? 'u.'),
				'branch' => ($branches[$e['branch_id']] ?? ('#' . $e['branch_id'])),
				'type' => 'En investigación'
			], array_reverse($inv)), 0, 50),
			'incidents_list' => array_slice(array_map(fn($e) => $e + [
				'product' => $names[$e['product_id']]['name'] ?? '',
				'sku' => $names[$e['product_id']]['sku'] ?? '',
				'department' => $names[$e['product_id']]['department'] ?? 'General',
				'qu_name' => $names[$e['product_id']]['qu_name'] ?? 'u.',
				'branch' => $branches[$e['branch_id']] ?? ('#' . $e['branch_id']),
				'type' => $e['loss_class'] === 'robo_interno' ? 'Interno' : 'Externo'
			], array_reverse($theft)), 0, 50),
		];
	}

	/**
	 * Merma por formato, sucursal y departamento: $ y % de ventas netas (desempeño relativo), y por m² cuando hay dato.
	 */
	public function Formats(array $f, string $from, string $to): array
	{
		$f2 = $f;
		$f2['branch_id'] = null;
		$f2['format'] = null;
		$ev = $this->Events($f2, $from, $to);
		$branches = $this->Rows('SELECT id, name, IFNULL(NULLIF(format, \'\'), \'Sin formato\') AS format, sales_area_m2 FROM branches WHERE active = 1 ORDER BY id');
		$names = $this->ProductNames(array_unique(array_column($ev, 'product_id')));
		$rows = [];
		$formats = [];
		$heat = [];
		$depts = [];
		foreach ($branches as $b)
		{
			$bid = intval($b['id']);
			$bev = array_filter($ev, fn($e) => intval($e['branch_id']) === $bid);
			[$sales] = $this->NetSales(array_merge($f2, ['branch_id' => $bid]), $from, $to);
			$op = self::Sum($bev, fn($e) => $e['loss_class'] === 'operativa');
			$tot = self::Sum($bev);
			$units = self::Sum($this->Rows("SELECT IFNULL(SUM(ABS(l.amount)), 0) AS cost FROM stock_log l JOIN locations loc ON loc.id = l.location_id WHERE loc.branch_id = :b AND l.undone = 0 AND date(l.row_created_timestamp) BETWEEN :f AND :t", [':b' => $bid, ':f' => $from, ':t' => $to]));
			$rows[] = ['branch_id' => $bid, 'branch' => $b['name'], 'format' => $b['format'], 'area_m2' => $b['sales_area_m2'] !== null ? floatval($b['sales_area_m2']) : null,
				'net_sales' => round($sales, 2), 'operativa' => round($op, 2), 'operativa_pct' => self::Pct($op, $sales), 'total' => round($tot, 2), 'total_pct' => self::Pct($tot, $sales),
				'per_m2' => $b['sales_area_m2'] ? round($tot / floatval($b['sales_area_m2']), 2) : null, 'units_handled' => $units, 'per_1000_units' => $units > 0 ? round($tot / $units * 1000, 2) : null];
			$fk = $b['format'];
			$formats[$fk] ??= ['format' => $fk, 'branches' => 0, 'net_sales' => 0.0, 'operativa' => 0.0, 'total' => 0.0];
			$formats[$fk]['branches']++;
			$formats[$fk]['net_sales'] += $sales;
			$formats[$fk]['operativa'] += $op;
			$formats[$fk]['total'] += $tot;
			foreach ($bev as $e)
			{
				$d = $names[$e['product_id']]['department'] ?? 'Sin departamento';
				$depts[$d] = true;
				$heat[$b['name']][$d] = ($heat[$b['name']][$d] ?? 0) + $e['cost'];
			}
		}
		foreach ($formats as &$x)
		{
			$x['operativa_pct'] = self::Pct($x['operativa'], $x['net_sales']);
			$x['total_pct'] = self::Pct($x['total'], $x['net_sales']);
			$x['net_sales'] = round($x['net_sales'], 2);
			$x['operativa'] = round($x['operativa'], 2);
			$x['total'] = round($x['total'], 2);
		}
		unset($x);
		$depts = array_keys($depts);
		sort($depts);
		return ['branches' => $rows, 'formats' => array_values($formats), 'heatmap' => ['rows' => array_column($branches, 'name'), 'cols' => $depts, 'values' => $heat],
			'has_format' => count(array_filter($branches, fn($b) => $b['format'] !== 'Sin formato')) > 0];
	}

	/**
	 * Impacto financiero, empleo y productividad. El indicador VIS no se calcula hasta que el negocio defina qué es.
	 */
	public function Financial(array $f, string $from, string $to): array
	{
		$s = $this->Summary($f, $from, $to);
		$p = [];
		$pw = $this->ProductWhere($f, $p);
		$stockValue = floatval($this->Rows("SELECT IFNULL(SUM(st.amount * IFNULL(st.price, 0)), 0) AS v FROM stock st JOIN products p ON p.id = st.product_id
			LEFT JOIN locations loc ON loc.id = st.location_id LEFT JOIN branches b ON b.id = IFNULL(loc.branch_id, 1) WHERE 1 = 1$pw", $p)[0]['v']);
		$stagnant = LossPreventionService::GetInstance()->StagnantLots($f['branch_id'] ?? null);
		$stagnantValue = floatval($stagnant['total_value'] ?? 0);
		$labor = $s['labor'];
		$counts = $this->Rows("SELECT COUNT(*) AS n FROM stock_cycle_counts WHERE date(IFNULL(executed_date, scheduled_date)) BETWEEN :f AND :t" . (($f['branch_id'] ?? null) ? ' AND branch_id = :b' : ''),
			[':f' => $from, ':t' => $to] + (($f['branch_id'] ?? null) ? [':b' => $f['branch_id']] : []))[0];
		$vis = $this->Setting('vis_definition');
		return [
			'summary' => $s,
			'gross_margin' => $s['gross_margin'],
			'operating_profit_est' => round($s['gross_margin'] - $s['total']['value'], 2),
			'stock_value' => round($stockValue, 2),
			'loss_over_stock_pct' => self::Pct($s['total']['value'], $stockValue),
			'immobilized_capital' => round($stagnantValue, 2),
			'labor' => $labor,
			'cycle_counts' => intval($counts['n']),
			'reprocess_hours' => null,
			'vis' => ['defined' => $vis !== null, 'definition' => $vis, 'source' => $this->Setting('vis_source'), 'value' => null],
		];
	}

	/**
	 * Ranking de inventario selectivo/sensible: puntaje 0–100 con 8 factores medidos (sin datos ⇒ 0 en ese factor).
	 */
	public function SensitiveRanking(?int $branchId, int $limit = 100): array
	{
		$from90 = date('Y-m-d', strtotime('-90 days'));
		$p = [':f' => $from90] + ($branchId ? [':b' => $branchId] : []);
		$bS = $branchId ? ' AND s.branch_id = :b' : '';
		$bW = $branchId ? ' AND w.branch_id = :b' : '';
		$bL = $branchId ? ' AND loc.branch_id = :b' : '';
		$rows = $this->Rows("SELECT p.id, p.name, IFNULL(p.sku, '') AS sku, IFNULL(pg.name, 'Sin departamento') AS department, IFNULL(p.storage_class, '') AS storage_class,
				IFNULL(qu.name, 'u.') AS qu_name,
				IFNULL((SELECT SUM(si.amount) FROM sale_items si JOIN sales s ON s.id = si.sale_id WHERE si.product_id = p.id AND s.status = 'completed' AND date(s.created_timestamp) >= :f$bS), 0) AS sold_units,
				IFNULL((SELECT price FROM cache__products_last_purchased c WHERE c.product_id = p.id), IFNULL(p.sale_price, 0)) AS unit_cost,
				IFNULL((SELECT SUM(w.total_cost) FROM waste_records w JOIN waste_reasons r ON r.id = w.waste_reason_id WHERE w.product_id = p.id AND w.status = 'approved' AND r.loss_cause_code IN ('RIN', 'REX') AND date(w.created_timestamp) >= :f$bW), 0) AS theft_cost,
				IFNULL((SELECT COUNT(*) FROM waste_records w JOIN waste_reasons r ON r.id = w.waste_reason_id WHERE w.product_id = p.id AND r.loss_cause_code IN ('RIN', 'REX', 'INV') AND date(w.created_timestamp) >= :f$bW), 0) AS theft_events,
				IFNULL((SELECT SUM(w.total_cost) FROM waste_records w WHERE w.product_id = p.id AND w.status = 'approved' AND date(w.created_timestamp) >= :f$bW), 0) AS waste_cost,
				IFNULL((SELECT SUM(st.amount) FROM stock st LEFT JOIN locations loc ON loc.id = st.location_id WHERE st.product_id = p.id AND st.best_before_date <= date('now', '+7 days') AND st.best_before_date >= date('now', '-1 days')$bL), 0) AS expiring_units,
				IFNULL((SELECT COUNT(*) FROM stock_log l LEFT JOIN locations loc ON loc.id = l.location_id WHERE l.product_id = p.id AND l.transaction_type = 'inventory-correction' AND l.undone = 0 AND date(l.row_created_timestamp) >= :f$bL), 0)
					+ IFNULL((SELECT COUNT(*) FROM stock_cycle_counts c WHERE c.product_id = p.id AND ABS(IFNULL(c.difference_amount, 0)) > 0 AND date(IFNULL(c.executed_date, c.scheduled_date)) >= :f" . ($branchId ? ' AND c.branch_id = :b' : '') . "), 0) AS diff_events,
				IFNULL((SELECT SUM(st.amount) FROM stock st LEFT JOIN locations loc ON loc.id = st.location_id WHERE st.product_id = p.id$bL), 0) AS stock_units,
				(SELECT MAX(date(IFNULL(c.executed_date, c.scheduled_date))) FROM stock_cycle_counts c WHERE c.product_id = p.id AND c.status IN ('completed', 'discrepancy')) AS last_count
			FROM products p LEFT JOIN product_groups pg ON pg.id = p.product_group_id LEFT JOIN quantity_units qu ON qu.id = p.qu_id_stock WHERE p.active = 1", $p);
		$max = fn($k) => max(1e-9, ...array_map(fn($r) => floatval($r[$k]), $rows ?: [[$k => 0]]));
		$mSold = $max('sold_units');
		$mCost = $max('unit_cost');
		$mTheft = $max('theft_events');
		$mWaste = $max('waste_cost');
		$mDiff = $max('diff_events');
		$weights = ['rotation' => 10, 'value' => 15, 'theft' => 20, 'waste' => 15, 'expiry' => 10, 'temperature' => 10, 'differences' => 10, 'reliability' => 10];
		$out = [];
		foreach ($rows as $r)
		{
			$fac = [
				'rotation' => floatval($r['sold_units']) / $mSold,
				'value' => floatval($r['unit_cost']) / $mCost,
				'theft' => floatval($r['theft_events']) / $mTheft,
				'waste' => floatval($r['waste_cost']) / $mWaste,
				'expiry' => floatval($r['expiring_units']) > 0 ? 1.0 : 0.0,
				'temperature' => in_array($r['storage_class'], ['refrigerado', 'congelado', 'cold', 'frozen', 'refrigerated', 'chilled'], true) ? 1.0 : 0.0,
				'differences' => floatval($r['diff_events']) / $mDiff,
				// Baja confiabilidad: tiene stock, rota y nunca se contó (o hace > 60 días)
				'reliability' => floatval($r['stock_units']) > 0 && (!$r['last_count'] || $r['last_count'] < date('Y-m-d', strtotime('-60 days'))) ? 1.0 : 0.0,
			];
			$score = 0.0;
			foreach ($weights as $k => $w)
			{
				$score += $fac[$k] * $w;
			}
			$reasons = array_keys(array_filter($fac, fn($v) => $v >= 0.5));
			$out[] = [
				'product_id' => intval($r['id']),
				'name' => $r['name'],
				'sku' => $r['sku'],
				'department' => $r['department'],
				'qu_name' => $r['qu_name'] ?? 'u.',
				'score' => round($score, 1),
				'priority' => $score >= 50 ? 'urgente' : ($score >= 30 ? 'alta' : ($score >= 15 ? 'media' : 'baja')),
				'frequency_days' => $score >= 50 ? 7 : ($score >= 30 ? 14 : ($score >= 15 ? 30 : 90)),
				'factors' => array_map(fn($v) => round($v, 2), $fac),
				'raw' => [
					'sold_units' => floatval($r['sold_units']),
					'unit_cost' => floatval($r['unit_cost']),
					'theft_events' => intval($r['theft_events']),
					'waste_cost' => floatval($r['waste_cost']),
					'expiring_units' => floatval($r['expiring_units']),
					'storage_class' => $r['storage_class'],
					'diff_events' => intval($r['diff_events']),
					'stock_units' => floatval($r['stock_units'])
				],
				'reasons' => $reasons,
				'stock_units' => floatval($r['stock_units']),
				'last_count' => $r['last_count']
			];
		}
		usort($out, fn($a, $b) => $b['score'] <=> $a['score']);
		return ['weights' => $weights, 'products' => array_slice($out, 0, $limit), 'evaluated' => count($out)];
	}

	/**
	 * Programa conteos cíclicos para los productos urgentes/altos del ranking (reutiliza CycleCountService).
	 */
	public function ScheduleRiskCounts(int $branchId, int $userId, array $priorities = ['urgente', 'alta']): array
	{
		$ids = array_column(array_filter($this->SensitiveRanking($branchId, 200)['products'], fn($p) => in_array($p['priority'], $priorities, true) && $p['stock_units'] > 0), 'product_id');
		if (!$ids)
		{
			return ['scheduled' => 0, 'message' => 'No hay productos con prioridad ' . implode('/', $priorities) . ' y stock en la sucursal'];
		}
		$r = CycleCountService::GetInstance()->ScheduleForProducts($branchId, $ids, 'Conteo selectivo por riesgo (Centro de Control de pérdidas)', $userId);
		return (is_array($r) ? $r : []) + ['requested' => count($ids)];
	}

	/**
	 * Excursiones de temperatura: lecturas fuera de rango agrupadas por ubicación, con duración hasta la siguiente lectura
	 * en rango y los lotes hoy almacenados allí (potencialmente comprometidos; nunca se liberan automáticamente).
	 */
	public function ColdChain(?int $branchId, string $from, string $to): array
	{
		$maxR = floatval($this->Setting('cold_max_refrigerated', 5));
		$minR = floatval($this->Setting('cold_min_refrigerated', 0));
		$maxF = floatval($this->Setting('cold_max_freezer', -15));
		$p = [':f' => $from, ':t' => $to] + ($branchId ? [':b' => $branchId] : []);
		$readings = $this->Rows("SELECT c.*, l.name AS location_name, l.is_freezer FROM cold_chain_temperatures c JOIN locations l ON l.id = c.location_id
			WHERE date(c.recorded_timestamp) BETWEEN :f AND :t" . ($branchId ? ' AND c.branch_id = :b' : '') . ' ORDER BY c.location_id, c.recorded_timestamp', $p);
		$exc = [];
		$open = [];
		$trend = [];
		foreach ($readings as $r)
		{
			$t = floatval($r['temperature']);
			$out = intval($r['is_freezer']) === 1 ? $t > $maxF : ($t > $maxR || $t < $minR);
			$out = $out || in_array($r['status'], ['alert', 'critical', 'warning', 'out_of_range'], true);
			$loc = intval($r['location_id']);
			$day = substr($r['recorded_timestamp'], 0, 10);
			$trend[$day] ??= ['readings' => 0, 'out' => 0];
			$trend[$day]['readings']++;
			if ($out)
			{
				$trend[$day]['out']++;
				if (!isset($open[$loc]))
				{
					$open[$loc] = ['location_id' => $loc, 'location' => $r['location_name'], 'branch_id' => intval($r['branch_id']), 'start' => $r['recorded_timestamp'], 'end' => null, 'max_temp' => $t, 'readings' => 0, 'action' => $r['action_taken']];
				}
				$open[$loc]['readings']++;
				$open[$loc]['max_temp'] = max($open[$loc]['max_temp'], $t);
				$open[$loc]['last'] = $r['recorded_timestamp'];
			}
			elseif (isset($open[$loc]))
			{
				$open[$loc]['end'] = $r['recorded_timestamp'];
				$exc[] = $open[$loc];
				unset($open[$loc]);
			}
		}
		foreach ($open as $o)
		{
			$exc[] = $o;
		}
		foreach ($exc as &$e)
		{
			$end = $e['end'] ?? date('Y-m-d H:i:s');
			$e['ongoing'] = $e['end'] === null;
			$e['minutes'] = intval(round((strtotime($end) - strtotime($e['start'])) / 60));
			$st = $this->Db()->prepare('SELECT st.id, st.stock_id, st.product_id, p.name, st.amount, st.best_before_date, IFNULL(st.price, 0) * st.amount AS value,
				(SELECT COUNT(*) FROM quarantine_items q WHERE q.stock_id = st.stock_id AND q.status = \'in_quarantine\') AS quarantined
				FROM stock st JOIN products p ON p.id = st.product_id WHERE st.location_id = ? AND st.amount > 0');
			$st->execute([$e['location_id']]);
			$e['lots'] = $st->fetchAll(\PDO::FETCH_ASSOC);
			$e['value_at_risk'] = round(array_sum(array_map(fn($l) => floatval($l['value']), $e['lots'])), 2);
		}
		unset($e);
		usort($exc, fn($a, $b) => strcmp($b['start'], $a['start']));
		ksort($trend);
		$pw = [':f' => $from, ':t' => $to] + ($branchId ? [':b' => $branchId] : []);
		$cfr = $this->Rows("SELECT strftime('%Y-%m', w.created_timestamp) AS m, IFNULL(SUM(w.total_cost), 0) AS cost FROM waste_records w JOIN waste_reasons r ON r.id = w.waste_reason_id
			WHERE w.status = 'approved' AND r.loss_cause_code = 'CFR' AND date(w.created_timestamp) BETWEEN :f AND :t" . ($branchId ? ' AND w.branch_id = :b' : '') . ' GROUP BY m ORDER BY m', $pw);
		return ['ranges' => ['refrigerated' => [$minR, $maxR], 'freezer_max' => $maxF], 'readings' => count($readings), 'excursions' => $exc, 'trend' => $trend, 'cold_chain_loss' => $cfr];
	}

	/* ------------------------------------------------------------------ Control de precios y marcación */

	/**
	 * Detección automática sobre datos reales (idempotente por uid):
	 *  - pos_vs_authorized: línea de venta con precio unitario distinto del precio autorizado (products.sale_price).
	 *  - barcode_sku: código de barras de un producto igual al SKU de otro (el escáner o el POS pueden resolver el SKU equivocado).
	 *  - branch_diff: mismo producto vendido el mismo día a precios distintos entre sucursales.
	 * El impacto económico de lo detectado queda «sin verificar» hasta la validación humana.
	 */
	public function DetectPriceIssues(?int $branchId, string $from, string $to, int $userId): array
	{
		$db = $this->Db();
		$p = [':f' => $from, ':t' => $to] + ($branchId ? [':b' => $branchId] : []);
		$bS = $branchId ? ' AND s.branch_id = :b' : '';
		$ins = $db->prepare('INSERT OR IGNORE INTO price_discrepancies (uid, kind, source, product_id, branch_id, barcode, authorized_price, pos_price, units_affected, impact_type, impact_amount, detail, detected_by_user_id)
			VALUES (:uid, :kind, \'auto\', :pid, :bid, :bc, :auth, :pos, :units, :it, :imp, :detail, :u)');
		$n = 0;
		foreach ($this->Rows("SELECT s.branch_id, si.product_id, ROUND(si.unit_price, 2) AS pos, ROUND(p.sale_price, 2) AS auth, SUM(si.amount) AS units, MIN(date(s.created_timestamp)) AS d1, MAX(date(s.created_timestamp)) AS d2, COUNT(*) AS lines
			FROM sale_items si JOIN sales s ON s.id = si.sale_id JOIN products p ON p.id = si.product_id
			WHERE s.status = 'completed' AND IFNULL(p.sale_price, 0) > 0 AND ABS(si.unit_price - p.sale_price) > 0.009 AND date(s.created_timestamp) BETWEEN :f AND :t$bS
			GROUP BY s.branch_id, si.product_id, ROUND(si.unit_price, 2)", $p) as $r)
		{
			$diff = floatval($r['auth']) - floatval($r['pos']);
			$ins->execute([':uid' => "pos:{$r['branch_id']}:{$r['product_id']}:{$r['pos']}:$from", ':kind' => 'pos_vs_authorized', ':pid' => $r['product_id'], ':bid' => $r['branch_id'], ':bc' => null,
				':auth' => $r['auth'], ':pos' => $r['pos'], ':units' => $r['units'], ':it' => $diff > 0 ? 'subcobro' : 'sobrecobro', ':imp' => round(abs($diff) * floatval($r['units']), 2),
				':detail' => "{$r['lines']} líneas de venta entre {$r['d1']} y {$r['d2']}", ':u' => $userId]);
			$n += $ins->rowCount();
		}
		foreach ($this->Rows("SELECT pb.barcode, pb.product_id || ',' || p2.id AS products, 2 AS n FROM product_barcodes pb JOIN products p2 ON p2.sku = pb.barcode AND p2.id <> pb.product_id") as $r)
		{
			$ins->execute([':uid' => "bc:{$r['barcode']}", ':kind' => 'barcode_sku', ':pid' => intval(explode(',', $r['products'])[0]), ':bid' => $branchId, ':bc' => $r['barcode'],
				':auth' => null, ':pos' => null, ':units' => 0, ':it' => 'sin_impacto', ':imp' => 0, ':detail' => "Código asociado a {$r['n']} productos: #" . str_replace(',', ', #', $r['products']), ':u' => $userId]);
			$n += $ins->rowCount();
		}
		foreach ($this->Rows("SELECT si.product_id, date(s.created_timestamp) AS d, MIN(si.unit_price) AS lo, MAX(si.unit_price) AS hi, GROUP_CONCAT(DISTINCT s.branch_id) AS br
			FROM sale_items si JOIN sales s ON s.id = si.sale_id WHERE s.status = 'completed' AND date(s.created_timestamp) BETWEEN :f AND :t
			GROUP BY si.product_id, d HAVING COUNT(DISTINCT s.branch_id) > 1 AND MAX(si.unit_price) - MIN(si.unit_price) > 0.009", [':f' => $from, ':t' => $to]) as $r)
		{
			$ins->execute([':uid' => "br:{$r['product_id']}:{$r['d']}", ':kind' => 'branch_diff', ':pid' => $r['product_id'], ':bid' => null, ':bc' => null,
				':auth' => null, ':pos' => $r['lo'], ':units' => 0, ':it' => 'sin_impacto', ':imp' => 0, ':detail' => "{$r['d']}: precio entre {$r['lo']} y {$r['hi']} en sucursales {$r['br']}", ':u' => $userId]);
			$n += $ins->rowCount();
		}
		return ['created' => $n];
	}

	public function CreatePriceIssue(array $d, int $userId): int
	{
		$kind = strval($d['kind'] ?? '');
		if (!isset(self::PRICE_KINDS[$kind]))
		{
			throw new \Exception('Tipo de discrepancia inválido');
		}
		$pid = intval($d['product_id'] ?? 0);
		if ($pid <= 0 || !$this->Rows('SELECT id FROM products WHERE id = ?', [$pid]))
		{
			throw new \Exception('Producto inválido');
		}
		$num = fn($k) => isset($d[$k]) && $d[$k] !== '' ? floatval($d[$k]) : null;
		$auth = $num('authorized_price');
		$pos = $num('pos_price');
		$shelf = $num('shelf_price');
		$units = max(0, floatval($d['units_affected'] ?? 0));
		[$type, $impact] = self::PriceImpact($kind, $auth, $pos, $shelf, $units, strval($d['impact_type'] ?? ''));
		$this->Db()->prepare('INSERT INTO price_discrepancies (uid, kind, source, product_id, branch_id, barcode, authorized_price, pos_price, shelf_price, units_affected, impact_type, impact_amount, detail, notes, evidence_path, detected_by_user_id)
			VALUES (?, ?, \'manual\', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
			->execute(['man:' . bin2hex(random_bytes(8)), $kind, $pid, !empty($d['branch_id']) ? intval($d['branch_id']) : null, trim(strval($d['barcode'] ?? '')) ?: null,
				$auth, $pos, $shelf, $units, $type, $impact, trim(strval($d['detail'] ?? '')) ?: null, trim(strval($d['notes'] ?? '')) ?: null, trim(strval($d['evidence_path'] ?? '')) ?: null, $userId]);
		$id = intval($this->Db()->lastInsertId());
		$this->LogPrice($id, null, 'detected', $userId, 'Registro manual');
		return $id;
	}

	/**
	 * Impacto: precio cobrado < autorizado ⇒ subcobro (pérdida comercial de la empresa); > ⇒ sobrecobro (riesgo con el
	 * cliente / devolución, no es merma). Góndola vs POS usa la góndola como precio comunicado.
	 */
	public static function PriceImpact(string $kind, ?float $auth, ?float $pos, ?float $shelf, float $units, string $forced = ''): array
	{
		$ref = $kind === 'shelf_vs_pos' ? $shelf : $auth;
		if (in_array($forced, ['devolucion', 'perdida_comercial'], true) && $pos !== null && $ref !== null)
		{
			return [$forced, round(abs($ref - $pos) * $units, 2)];
		}
		if ($ref === null || $pos === null || abs($ref - $pos) < 0.005)
		{
			return ['sin_impacto', 0.0];
		}
		return [$ref > $pos ? 'subcobro' : 'sobrecobro', round(abs($ref - $pos) * $units, 2)];
	}

	private function LogPrice(int $id, ?string $from, string $to, int $userId, ?string $note): void
	{
		$this->Db()->prepare('INSERT INTO price_discrepancy_log (discrepancy_id, from_status, to_status, user_id, note) VALUES (?, ?, ?, ?, ?)')->execute([$id, $from, $to, $userId, $note]);
	}

	/**
	 * Avanza un paso del flujo Detección → Validación → Evidencia → Corrección → Aprobación → Sincronización POS →
	 * Verificación → Cierre (o rechazo). Reglas: sin saltos; evidencia obligatoria; la corrección propone un precio, pero
	 * el precio autorizado SOLO cambia al aprobar, por otra persona con permiso de aprobación (segregación), y queda
	 * auditado (dairy_change_log + log del flujo). La sincronización POS exige referencia del POS.
	 */
	public function AdvancePriceIssue(int $id, string $to, array $d, int $userId, bool $canApprove): array
	{
		$db = $this->Db();
		DatabaseService::GetInstance()->BeginWriteTransaction();
		try
		{
			$st = $db->prepare('SELECT * FROM price_discrepancies WHERE id = ?');
			$st->execute([$id]);
			$row = $st->fetch(\PDO::FETCH_ASSOC);
			if (!$row)
			{
				throw new \Exception('Discrepancia no encontrada');
			}
			$from = $row['status'];
			if (in_array($from, ['closed', 'rejected'], true))
			{
				throw new \Exception('La discrepancia ya está cerrada');
			}
			$note = trim(strval($d['note'] ?? '')) ?: null;
			if ($to === 'rejected')
			{
				if (!$note)
				{
					throw new \Exception('Indique el motivo del rechazo');
				}
			}
			else
			{
				$i = array_search($from, self::PRICE_FLOW, true);
				if (self::PRICE_FLOW[$i + 1] !== $to)
				{
					throw new \Exception('Paso inválido: después de «' . $from . '» corresponde «' . self::PRICE_FLOW[$i + 1] . '»');
				}
			}
			$set = ['status' => $to];
			switch ($to)
			{
				case 'validated':
					// La validación humana confirma (o corrige) unidades e impacto: desde aquí el impacto es «comprobado»
					if (isset($d['units_affected']) && $d['units_affected'] !== '')
					{
						[$type, $imp] = self::PriceImpact($row['kind'], $row['authorized_price'] !== null ? floatval($row['authorized_price']) : null, $row['pos_price'] !== null ? floatval($row['pos_price']) : null,
							$row['shelf_price'] !== null ? floatval($row['shelf_price']) : null, max(0, floatval($d['units_affected'])), strval($d['impact_type'] ?? ''));
						$set += ['units_affected' => max(0, floatval($d['units_affected'])), 'impact_type' => $type, 'impact_amount' => $imp];
					}
					$set['impact_verified'] = 1;
					break;
				case 'evidence':
					$ev = trim(strval($d['evidence_path'] ?? $row['evidence_path'] ?? ''));
					if ($ev === '' && !$note)
					{
						throw new \Exception('La evidencia es obligatoria (foto o descripción verificable)');
					}
					$set['evidence_path'] = $ev ?: null;
					break;
				case 'corrected':
					if ($row['kind'] === 'pos_vs_authorized' || $row['kind'] === 'shelf_vs_pos' || isset($d['proposed_price']))
					{
						$pp = isset($d['proposed_price']) && $d['proposed_price'] !== '' ? floatval($d['proposed_price']) : null;
						if ($pp !== null && $pp <= 0)
						{
							throw new \Exception('Precio propuesto inválido');
						}
						$set['proposed_price'] = $pp;
					}
					$set['corrected_by_user_id'] = $userId;
					break;
				case 'approved':
					if (!$canApprove)
					{
						throw new \Exception('Se requiere permiso de aprobación');
					}
					if (intval($row['corrected_by_user_id']) === $userId || (intval($row['detected_by_user_id']) === $userId && $row['source'] === 'manual'))
					{
						throw new \Exception('Segregación de funciones: quien detecta o corrige no puede aprobar');
					}
					if ($row['proposed_price'] !== null)
					{
						$db->prepare('UPDATE products SET sale_price = ? WHERE id = ?')->execute([floatval($row['proposed_price']), intval($row['product_id'])]);
						$note = trim(($note ?? '') . ' Precio autorizado actualizado a ' . number_format(floatval($row['proposed_price']), 2));
					}
					$set['approved_by_user_id'] = $userId;
					break;
				case 'pos_synced':
					$ref = trim(strval($d['pos_sync_reference'] ?? ''));
					if ($ref === '')
					{
						throw new \Exception('Indique la referencia de sincronización del POS (lote, ticket o confirmación)');
					}
					$set['pos_sync_reference'] = $ref;
					break;
				case 'verified':
				case 'closed':
					if (!$canApprove && $to === 'closed')
					{
						throw new \Exception('Se requiere permiso de aprobación para cerrar');
					}
					break;
			}
			$set['updated_timestamp'] = date('Y-m-d H:i:s');
			$sql = 'UPDATE price_discrepancies SET ' . implode(', ', array_map(fn($k) => "$k = :$k", array_keys($set))) . ' WHERE id = :id';
			$db->prepare($sql)->execute(array_combine(array_map(fn($k) => ":$k", array_keys($set)), array_values($set)) + [':id' => $id]);
			$this->LogPrice($id, $from, $to, $userId, $note);
			$db->commit();
			return ['id' => $id, 'status' => $to];
		}
		catch (\Throwable $e)
		{
			if ($db->inTransaction())
			{
				$db->rollBack();
			}
			throw $e;
		}
	}

	public function PriceIssues(?int $branchId, ?string $status = null): array
	{
		$p = [];
		$w = '';
		if ($branchId)
		{
			$w .= ' AND (d.branch_id = :b OR d.branch_id IS NULL)';
			$p[':b'] = $branchId;
		}
		if ($status === 'open')
		{
			$w .= " AND d.status NOT IN ('closed', 'rejected')";
		}
		elseif ($status)
		{
			$w .= ' AND d.status = :s';
			$p[':s'] = $status;
		}
		$rows = $this->Rows("SELECT d.*, p.name AS product_name, IFNULL(p.sku, '') AS sku, b.name AS branch_name FROM price_discrepancies d LEFT JOIN products p ON p.id = d.product_id
			LEFT JOIN branches b ON b.id = d.branch_id WHERE 1 = 1$w ORDER BY d.status IN ('closed', 'rejected'), d.id DESC LIMIT 200", $p);
		$sum = ['open' => 0, 'verified_impact' => ['sobrecobro' => 0.0, 'subcobro' => 0.0, 'devolucion' => 0.0, 'perdida_comercial' => 0.0], 'unverified_impact' => 0.0, 'by_kind' => []];
		foreach ($rows as $r)
		{
			if (!in_array($r['status'], ['closed', 'rejected'], true))
			{
				$sum['open']++;
			}
			if ($r['status'] === 'rejected')
			{
				continue;
			}
			if (intval($r['impact_verified']) === 1 && isset($sum['verified_impact'][$r['impact_type']]))
			{
				$sum['verified_impact'][$r['impact_type']] += floatval($r['impact_amount']);
			}
			elseif (intval($r['impact_verified']) === 0)
			{
				$sum['unverified_impact'] += floatval($r['impact_amount']);
			}
			$sum['by_kind'][$r['kind']] = ($sum['by_kind'][$r['kind']] ?? 0) + 1;
		}
		return ['rows' => $rows, 'summary' => $sum, 'flow' => self::PRICE_FLOW, 'kinds' => self::PRICE_KINDS];
	}

	/* ------------------------------------------------------------------ Perecederos: cárnicos, avícolas y fruver */

	public function SaveYieldLot(array $d, int $userId): int
	{
		$kind = strval($d['kind'] ?? '');
		if (!in_array($kind, ['pollo', 'carne', 'fruver'], true))
		{
			throw new \Exception('Tipo de lote inválido (pollo, carne o fruver)');
		}
		$pid = intval($d['product_id'] ?? 0);
		$bid = intval($d['branch_id'] ?? 0);
		if ($pid <= 0 || !$this->Rows('SELECT id FROM products WHERE id = ?', [$pid]) || $bid <= 0)
		{
			throw new \Exception('Producto y sucursal son obligatorios');
		}
		$kg = fn($k) => max(0, round(floatval($d[$k] ?? 0), 3));
		$received = $kg('received_kg');
		if ($received <= 0)
		{
			throw new \Exception('El peso recibido debe ser mayor que 0');
		}
		$parts = ['drain_water_kg', 'drain_blood_kg', 'dehydration_kg', 'ripening_kg', 'bruise_kg', 'transport_kg', 'deterioration_kg', 'expired_kg', 'discarded_kg', 'sold_kg'];
		$vals = [];
		foreach ($parts as $k)
		{
			$vals[$k] = $kg($k);
		}
		if (array_sum($vals) > $received + 0.001)
		{
			throw new \Exception('La suma de pérdidas y ventas (' . array_sum($vals) . ' kg) supera el peso recibido (' . $received . ' kg)');
		}
		$tolN = isset($d['tolerance_natural_pct']) && $d['tolerance_natural_pct'] !== '' ? floatval($d['tolerance_natural_pct']) : floatval($this->Setting('tolerance_natural_' . $kind, 3));
		$tolA = isset($d['tolerance_avoidable_pct']) && $d['tolerance_avoidable_pct'] !== '' ? floatval($d['tolerance_avoidable_pct']) : floatval($this->Setting('tolerance_avoidable_pct', 2));
		$cost = isset($d['unit_cost_kg']) && $d['unit_cost_kg'] !== '' ? floatval($d['unit_cost_kg'])
			: floatval($this->Rows('SELECT IFNULL((SELECT price FROM cache__products_last_purchased WHERE product_id = ?), 0) AS v', [$pid])[0]['v']);
		$id = intval($d['id'] ?? 0);
		$cols = array_merge(['kind' => $kind, 'product_id' => $pid, 'branch_id' => $bid, 'lot_number' => trim(strval($d['lot_number'] ?? '')) ?: null,
			'received_date' => !empty($d['received_date']) ? substr($d['received_date'], 0, 10) : date('Y-m-d'), 'received_kg' => $received,
			'unit_cost_kg' => $cost, 'sale_price_kg' => isset($d['sale_price_kg']) && $d['sale_price_kg'] !== '' ? floatval($d['sale_price_kg']) : null,
			'tolerance_natural_pct' => $tolN, 'tolerance_avoidable_pct' => $tolA, 'notes' => trim(strval($d['notes'] ?? '')) ?: null], $vals);
		if ($id > 0)
		{
			$cur = $this->Rows('SELECT status FROM perishable_yield_lots WHERE id = ?', [$id]);
			if (!$cur)
			{
				throw new \Exception('Lote no encontrado');
			}
			if ($cur[0]['status'] === 'closed')
			{
				throw new \Exception('El lote está cerrado');
			}
			$this->Db()->prepare('UPDATE perishable_yield_lots SET ' . implode(', ', array_map(fn($k) => "$k = :$k", array_keys($cols))) . ' WHERE id = :id')
				->execute(array_combine(array_map(fn($k) => ":$k", array_keys($cols)), array_values($cols)) + [':id' => $id]);
			return $id;
		}
		$cols += ['uid' => 'Y' . date('ymd') . '-' . bin2hex(random_bytes(4)), 'created_by_user_id' => $userId];
		$this->Db()->prepare('INSERT INTO perishable_yield_lots (' . implode(', ', array_keys($cols)) . ') VALUES (' . implode(', ', array_map(fn($k) => ":$k", array_keys($cols))) . ')')
			->execute(array_combine(array_map(fn($k) => ":$k", array_keys($cols)), array_values($cols)));
		return intval($this->Db()->lastInsertId());
	}

	public function CloseYieldLot(int $id, ?float $remainingKg, int $userId): void
	{
		$lot = $this->Rows('SELECT * FROM perishable_yield_lots WHERE id = ?', [$id]);
		if (!$lot || $lot[0]['status'] === 'closed')
		{
			throw new \Exception('Lote no encontrado o ya cerrado');
		}
		$this->Db()->prepare("UPDATE perishable_yield_lots SET status = 'closed', remaining_kg = ?, closed_by_user_id = ?, closed_timestamp = datetime('now', 'localtime') WHERE id = ?")
			->execute([$remainingKg !== null ? max(0, $remainingKg) : 0, $userId, $id]);
	}

	/**
	 * Cálculo de rendimiento de un lote. Natural (tolerancia técnica documentada) = drenaje agua + sangre + deshidratación.
	 * Evitable = maduración + golpes + transporte + deterioro + vencimiento comercial + descarte.
	 * Diferencia teórico vs. real = recibido − natural − evitable − vendido − remanente (solo con lote cerrado).
	 */
	public static function YieldCalc(array $l): array
	{
		$rec = floatval($l['received_kg']);
		$natural = floatval($l['drain_water_kg']) + floatval($l['drain_blood_kg']) + floatval($l['dehydration_kg']);
		$avoid = floatval($l['ripening_kg']) + floatval($l['bruise_kg']) + floatval($l['transport_kg']) + floatval($l['deterioration_kg']) + floatval($l['expired_kg']) + floatval($l['discarded_kg']);
		$cost = floatval($l['unit_cost_kg']);
		$natPct = $rec > 0 ? round($natural / $rec * 100, 2) : 0;
		$avPct = $rec > 0 ? round($avoid / $rec * 100, 2) : 0;
		$tolN = floatval($l['tolerance_natural_pct']);
		$tolA = floatval($l['tolerance_avoidable_pct']);
		$closed = $l['status'] === 'closed';
		$gap = $closed ? round($rec - $natural - $avoid - floatval($l['sold_kg']) - floatval($l['remaining_kg'] ?? 0), 3) : null;
		$excessNatural = max(0, $natural - $rec * $tolN / 100);
		return [
			'available_kg' => round($rec - $natural, 3),
			'natural_kg' => round($natural, 3), 'natural_pct' => $natPct, 'natural_cost' => round($natural * $cost, 2),
			'avoidable_kg' => round($avoid, 3), 'avoidable_pct' => $avPct, 'avoidable_cost' => round($avoid * $cost, 2),
			'natural_exceeded' => $natPct > $tolN, 'avoidable_exceeded' => $avPct > $tolA,
			'excess_natural_kg' => round($excessNatural, 3), 'excess_natural_cost' => round($excessNatural * $cost, 2),
			'theoretical_available_kg' => round($rec * (1 - $tolN / 100), 3),
			'gap_kg' => $gap, 'gap_cost' => $gap !== null ? round($gap * $cost, 2) : null,
			'loss_per_kg_sold' => floatval($l['sold_kg']) > 0 ? round(($natural + $avoid) * $cost / floatval($l['sold_kg']), 4) : null,
			'potential_sale_value' => $l['sale_price_kg'] !== null ? round($avoid * floatval($l['sale_price_kg']), 2) : null,
		];
	}

	public function Perishables(array $f, string $from, string $to): array
	{
		$p = [':f' => $from, ':t' => $to];
		$w = '';
		if ($f['branch_id'] ?? null)
		{
			$w = ' AND y.branch_id = :b';
			$p[':b'] = $f['branch_id'];
		}
		$lots = $this->Rows("SELECT y.*, pr.name AS product_name, IFNULL(pr.sku, '') AS sku, IFNULL(pg.name, 'Sin familia') AS family, b.name AS branch_name
			FROM perishable_yield_lots y JOIN products pr ON pr.id = y.product_id LEFT JOIN product_groups pg ON pg.id = pr.product_group_id LEFT JOIN branches b ON b.id = y.branch_id
			WHERE y.received_date BETWEEN :f AND :t$w ORDER BY y.received_date DESC, y.id DESC", $p);
		$agg = ['pollo' => [], 'carne' => [], 'fruver' => []];
		$fam = [];
		foreach ($lots as &$l)
		{
			$l['calc'] = self::YieldCalc($l);
			$k = $l['kind'];
			foreach (['received_kg', 'sold_kg'] as $c)
			{
				$agg[$k][$c] = ($agg[$k][$c] ?? 0) + floatval($l[$c]);
			}
			foreach (['natural_kg', 'avoidable_kg', 'natural_cost', 'avoidable_cost'] as $c)
			{
				$agg[$k][$c] = ($agg[$k][$c] ?? 0) + $l['calc'][$c];
			}
			$agg[$k]['lots'] = ($agg[$k]['lots'] ?? 0) + 1;
			$agg[$k]['exceeded'] = ($agg[$k]['exceeded'] ?? 0) + ($l['calc']['natural_exceeded'] || $l['calc']['avoidable_exceeded'] ? 1 : 0);
			if ($k === 'fruver')
			{
				$fam[$l['family']] ??= ['received_kg' => 0.0, 'loss_kg' => 0.0, 'loss_cost' => 0.0];
				$fam[$l['family']]['received_kg'] += floatval($l['received_kg']);
				$fam[$l['family']]['loss_kg'] += $l['calc']['natural_kg'] + $l['calc']['avoidable_kg'];
				$fam[$l['family']]['loss_cost'] += $l['calc']['natural_cost'] + $l['calc']['avoidable_cost'];
			}
		}
		unset($l);
		foreach ($agg as $k => &$a)
		{
			$rec = $a['received_kg'] ?? 0;
			$a['natural_pct'] = $rec > 0 ? round(($a['natural_kg'] ?? 0) / $rec * 100, 2) : null;
			$a['avoidable_pct'] = $rec > 0 ? round(($a['avoidable_kg'] ?? 0) / $rec * 100, 2) : null;
		}
		unset($a);
		foreach ($fam as &$x)
		{
			$x['loss_pct'] = $x['received_kg'] > 0 ? round($x['loss_kg'] / $x['received_kg'] * 100, 2) : null;
		}
		unset($x);

		// Fruver desde inventario: rotación, días de inventario y recomendación de liquidación (reglas de rebaja existentes)
		$groups = array_column($this->Rows("SELECT id FROM product_groups WHERE lower(name) LIKE '%frut%' OR lower(name) LIKE '%verdur%' OR lower(name) LIKE '%fruver%' OR lower(name) LIKE '%hortaliz%'"), 'id');
		$fruverStock = [];
		if ($groups)
		{
			$bL = ($f['branch_id'] ?? null) ? ' AND loc.branch_id = ' . intval($f['branch_id']) : '';
			$fruverStock = $this->Rows("SELECT p.id, p.name, IFNULL(p.sku, '') AS sku, IFNULL(SUM(st.amount), 0) AS stock, MIN(st.best_before_date) AS next_expiry,
					IFNULL((SELECT SUM(si.amount) FROM sale_items si JOIN sales s ON s.id = si.sale_id WHERE si.product_id = p.id AND s.status = 'completed' AND date(s.created_timestamp) >= date('now', '-30 days')), 0) AS sold30,
					IFNULL((SELECT SUM(w.total_cost) FROM waste_records w WHERE w.product_id = p.id AND w.status = 'approved' AND date(w.created_timestamp) BETWEEN :f AND :t), 0) AS waste_cost
				FROM products p LEFT JOIN stock st ON st.product_id = p.id LEFT JOIN locations loc ON loc.id = st.location_id
				WHERE p.active = 1 AND p.product_group_id IN (" . implode(',', array_map('intval', $groups)) . ")$bL GROUP BY p.id ORDER BY p.name", [':f' => $from, ':t' => $to]);
			$rules = $this->Rows('SELECT * FROM markdown_rules WHERE active = 1 ORDER BY min_days');
			foreach ($fruverStock as &$s)
			{
				$daily = floatval($s['sold30']) / 30;
				$s['days_inventory'] = $daily > 0 ? round(floatval($s['stock']) / $daily, 1) : null;
				$s['days_to_expiry'] = $s['next_expiry'] ? intval(floor((strtotime($s['next_expiry']) - strtotime(date('Y-m-d'))) / 86400)) : null;
				$s['recommendation'] = null;
				if (floatval($s['stock']) > 0 && $s['days_to_expiry'] !== null)
				{
					foreach ($rules as $r)
					{
						if ($s['days_to_expiry'] >= intval($r['min_days']) && $s['days_to_expiry'] <= intval($r['max_days']))
						{
							$s['recommendation'] = 'Liquidar con ' . floatval($r['discount_pct']) . '% (' . $r['name'] . ')';
							break;
						}
					}
					if (!$s['recommendation'] && $s['days_inventory'] !== null && $s['days_to_expiry'] >= 0 && $s['days_inventory'] > $s['days_to_expiry'])
					{
						$s['recommendation'] = 'Cobertura (' . $s['days_inventory'] . ' d) mayor que vida útil (' . $s['days_to_expiry'] . ' d): rebajar o trasladar';
					}
				}
			}
			unset($s);
		}
		return ['lots' => $lots, 'by_kind' => $agg, 'fruver_families' => $fam, 'fruver_stock' => $fruverStock,
			'tolerances' => ['pollo' => floatval($this->Setting('tolerance_natural_pollo', 3)), 'carne' => floatval($this->Setting('tolerance_natural_carne', 2)), 'fruver' => floatval($this->Setting('tolerance_natural_fruver', 5)), 'avoidable' => floatval($this->Setting('tolerance_avoidable_pct', 2))]];
	}

	/**
	 * Causal del motivo: si no es definitiva (INV), la merma nace como investigación con plazo y responsable.
	 */
	public function CauseForReason(int $reasonId): ?array
	{
		$r = $this->Rows('SELECT lc.* FROM waste_reasons r JOIN loss_causes lc ON lc.code = r.loss_cause_code WHERE r.id = ?', [$reasonId]);
		return $r[0] ?? null;
	}
}
