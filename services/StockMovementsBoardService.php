<?php

namespace Grocy\Services;

use Grocy\Helpers\DailyFilters;

/**
 * Tablero «Stock & Movimientos» (Inventario y almacenes). Una sola fuente para tarjetas, gráficos y listados:
 *  - Movimientos: vista merchandise_movements (stock_log clasificado; la misma que usa Kardex). Se excluyen las patas
 *    en la ubicación de tránsito (espejo del envío/recepción) y cada movimiento se cuenta una vez por transacción y producto.
 *  - Saldos: InventoryOperationsService::PositionMatrix (disponible = físico − cuarentena − vencido − merma por aprobar − reservado).
 *  - Conteos: stock_cycle_counts (cantidad de sistema registrada al contar, diferencia y ajuste vinculado por transacción).
 *  - Traslados: stock_transfers / stock_transfer_items (saldo en tránsito = enviado − recibido).
 * Filtros compartidos: DailyFilters (sucursal, departamento, categoría, producto y periodo; la fecha de corte es end_date).
 */
class StockMovementsBoardService extends BaseService
{
	const COVERAGE_DAYS = 30;
	const LOW_COVERAGE_DAYS = 3;
	const LATE_TRANSFER_HOURS = 48;
	const ASSORTMENT_DAYS = 90;
	const FINAL = "('completed', 'discrepancy', 'adjusted')";

	// Grupos de «Movimientos por tipo» => buckets de merchandise_movements
	const TYPES = [
		'receptions' => ['Recepciones', ['receptions'], '#10b981'],
		'internal' => ['Reposiciones internas', ['internal'], '#6ee7b7'],
		'transfers' => ['Traslados', ['transfers_in', 'transfers_out'], '#eab308'],
		'exits' => ['Salidas', ['sales', 'dispatches', 'waste'], '#94a3b8'],
		'returns' => ['Devoluciones', ['returns'], '#0d9488'],
		'adjustments' => ['Ajustes', ['adjustments'], '#ef4444'],
	];
	const BUCKET_LABELS = ['receptions' => 'Recepción', 'internal' => 'Reposición interna', 'transfers_in' => 'Traslado (entrada)', 'transfers_out' => 'Traslado (salida)',
		'sales' => 'Venta POS', 'dispatches' => 'Despacho', 'waste' => 'Merma', 'returns' => 'Devolución', 'adjustments' => 'Ajuste'];
	const EXIT_BUCKETS = ['sales', 'dispatches', 'waste', 'transfers_out'];
	const COUNT_STATUS = ['pending' => 'Programado', 'completed' => 'Sin diferencia', 'discrepancy' => 'Por conciliar', 'adjusted' => 'Ajuste confirmado', 'cancelled' => 'Anulado'];
	// Unidades de medida que no son piezas: se totalizan aparte, convertidas a kg o L
	const MEASURES = ['kilogramo' => ['kg', 1], 'gramo' => ['kg', 0.001], 'litro' => ['L', 1], 'mililitro' => ['L', 0.001], 'galón' => ['L', 3.78541], '1/2 galón' => ['L', 1.892705], 'pinta' => ['L', 0.473176]];

	private array $f;
	private ?array $units = null;

	private function Db(): \PDO
	{
		return DatabaseService::GetInstance()->GetDbConnectionRaw();
	}

	private function Rows(string $sql, array $p = []): array
	{
		$st = $this->Db()->prepare($sql);
		$st->execute($p);
		return $st->fetchAll(\PDO::FETCH_ASSOC);
	}

	private function Val(string $sql, array $p = [])
	{
		$st = $this->Db()->prepare($sql);
		$st->execute($p);
		return $st->fetchColumn();
	}

	private function B(string $col): string
	{
		return DailyFilters::BranchSql($this->f, $col);
	}

	private function P(string $alias = 'p'): string
	{
		return DailyFilters::ProductSql($this->f, $alias);
	}

	private function Cut(): string
	{
		return $this->f['end_date'] . ' 23:59:59';
	}

	private function IsCurrent(): bool
	{
		return $this->f['end_date'] >= date('Y-m-d');
	}

	/** Unidad de cada producto: 'u' (piezas, paquetes…) o kg / L con su factor */
	private function Unit(int $productId): array
	{
		if ($this->units === null)
		{
			$this->units = [];
			foreach ($this->Rows('SELECT p.id, LOWER(IFNULL(qu.name, \'\')) AS qu FROM products p LEFT JOIN quantity_units qu ON qu.id = p.qu_id_stock') as $r)
			{
				$this->units[intval($r['id'])] = self::MEASURES[$r['qu']] ?? ['u', 1];
			}
		}
		return $this->units[$productId] ?? ['u', 1];
	}

	private function AddQty(array &$acc, int $productId, float $qty): void
	{
		[$u, $k] = $this->Unit($productId);
		$acc[$u] = ($acc[$u] ?? 0) + $qty * $k;
	}

	private static function Qty(array $acc): array
	{
		$out = ['u' => round($acc['u'] ?? 0, 2)];
		foreach (['kg', 'L'] as $u)
		{
			if (abs($acc[$u] ?? 0) >= 0.01)
			{
				$out[$u] = round($acc[$u], 2);
			}
		}
		return $out;
	}

	private static function SumQty(array $list): array
	{
		$acc = [];
		foreach ($list as $q)
		{
			foreach ($q as $u => $v)
			{
				$acc[$u] = ($acc[$u] ?? 0) + $v;
			}
		}
		return self::Qty($acc);
	}

	private function DeptMap(): array
	{
		$map = [];
		foreach (DailyMetricsService::Departments() as $key => $d)
		{
			foreach ((array) ($d[2] ?? []) as $v)
			{
				$map[mb_strtolower($v)] = $key;
			}
		}
		return $map;
	}

	/* ================================================================ Tablero */

	public function Dashboard(array $f): array
	{
		$this->f = $f;
		$start = $f['start_date'];
		$end = $f['end_date'];
		$days = intval(round((strtotime($end) - strtotime($start)) / 86400)) + 1;
		$prevEnd = date('Y-m-d', strtotime($start . ' -1 day'));
		$prevStart = date('Y-m-d', strtotime($prevEnd . ' -' . ($days - 1) . ' days'));

		$acc = $this->Accuracy($start, $end);
		$accPrev = $this->Accuracy($prevStart, $prevEnd);
		$pairs = $this->Pairs();
		$transfers = $this->OpenTransfers();
		$mov = $this->Movements($start, $end);
		$counts = $this->CountLines($start, $end);
		$diffLines = array_values(array_filter($counts, fn($c) => abs(floatval($c['difference_amount'])) >= 0.001));
		$pending = $this->Pending($transfers);
		$late = count(array_filter($transfers, fn($t) => $t['late']));
		$unreconciled = $pending['counts']['count'] + $pending['adjustments']['count'];

		return [
			'filters' => $f,
			'period' => ['start' => $start, 'end' => $end, 'days' => $days, 'prev_start' => $prevStart, 'prev_end' => $prevEnd, 'label' => DailyFilters::PeriodLabel($start, $end),
				'cut_label' => date('d/m/Y', strtotime($end)), 'is_current' => $this->IsCurrent()],
			'kpis' => [
				'accuracy' => $acc + ['prev_pct' => $accPrev['pct'], 'prev_lines' => $accPrev['lines'], 'delta_pts' => $acc['pct'] !== null && $accPrev['pct'] !== null ? round($acc['pct'] - $accPrev['pct'], 1) : null],
				'stockouts' => $pairs['stockouts'],
				'coverage' => $pairs['coverage'],
				'transfers' => ['count' => count($transfers), 'qty' => self::SumQty(array_column($transfers, 'pending_qty')), 'late' => $late,
					'partial' => count(array_filter($transfers, fn($t) => $t['status'] === 'partially_received'))],
				'received' => ['qty' => self::Qty($mov['received']), 'docs' => $mov['received_docs']],
				'exits' => ['qty' => self::Qty($mov['exits']), 'by_type' => array_map(fn($a) => self::Qty($a), $mov['exits_by'])],
				'count_diffs' => ['lines' => count($diffLines), 'short' => count(array_filter($diffLines, fn($c) => $c['difference_amount'] < 0)), 'over' => count(array_filter($diffLines, fn($c) => $c['difference_amount'] > 0)),
					'value' => round(array_sum(array_map(fn($c) => abs(floatval($c['difference_cost'])), $diffLines)), 2), 'evaluated' => count($counts)],
				'accumulated' => $this->Accumulated(),
			],
			'differences' => $this->DifferencesChart($diffLines),
			'pending' => $pending,
			'flows' => $mov['daily'],
			'compliance' => $this->Compliance($start, $end),
			'top_differences' => $this->TopDifferences($diffLines, 5),
			'types' => $mov['types'],
			'quarantine' => self::Qty($this->Quarantine()),
			'alerts' => [
				['key' => 'stockouts', 'label' => 'Agotados', 'count' => $pairs['stockouts']['pairs'], 'detail' => 'combinaciones SKU–sucursal sin disponible', 'short' => 'SKU–sucursal sin disponible', 'level' => $pairs['stockouts']['pairs'] > 0 ? 'red' : 'gray', 'kind' => 'stockouts'],
				['key' => 'low_coverage', 'label' => 'Cobertura baja', 'count' => $pairs['coverage']['low'], 'detail' => 'SKU–sucursal con menos de ' . self::LOW_COVERAGE_DAYS . ' días de demanda', 'short' => 'menos de ' . self::LOW_COVERAGE_DAYS . ' días de demanda', 'level' => $pairs['coverage']['low'] > 0 ? 'yellow' : 'gray', 'kind' => 'coverage'],
				['key' => 'late_transfers', 'label' => 'Traslados atrasados', 'count' => $late, 'detail' => 'en tránsito hace más de ' . self::LATE_TRANSFER_HOURS . ' h', 'short' => 'más de ' . self::LATE_TRANSFER_HOURS . ' h en tránsito', 'level' => $late > 0 ? 'red' : 'gray', 'kind' => 'transfers', 'params' => ['late' => 1]],
				['key' => 'unreconciled', 'label' => 'Diferencias por conciliar', 'count' => $unreconciled, 'detail' => $pending['counts']['count'] . ' conteos · ' . $pending['adjustments']['count'] . ' ajustes por aprobar', 'short' => $pending['counts']['count'] . ' conteos · ' . $pending['adjustments']['count'] . ' ajustes', 'level' => $unreconciled > 0 ? 'yellow' : 'gray', 'kind' => 'counts', 'params' => ['status' => 'discrepancy']],
			],
			'series' => $this->SeriesOptions($pairs['rows']),
			'formulas' => [
				'accuracy' => 'Líneas de conteo finalizadas sin diferencia ÷ líneas válidas finalizadas × 100',
				'stockouts' => 'SKUs activos con disponible ≤ 0 al corte (disponible = físico − cuarentena − vencido − merma por aprobar − reservado)',
				'coverage' => 'Mediana por SKU y sucursal de disponible ÷ salida diaria promedio por demanda (venta POS y despacho) de los últimos ' . self::COVERAGE_DAYS . ' días hasta el corte',
				'transfers' => 'Traslados despachados con saldo pendiente: enviado − recibido',
			],
		];
	}

	/* ---------------------------------------------------------------- Conteos */

	private function CountLines(string $start, string $end, string $extra = ''): array
	{
		return $this->Rows("SELECT cc.*, p.name AS product_name, IFNULL(p.sku, 'SKU-' || p.id) AS sku, p.department, b.name AS branch_name, l.name AS location_name, IFNULL(qu.name, 'u') AS qu_name
			FROM stock_cycle_counts cc JOIN products p ON p.id = cc.product_id LEFT JOIN branches b ON b.id = cc.branch_id LEFT JOIN locations l ON l.id = cc.location_id
			LEFT JOIN quantity_units qu ON qu.id = p.qu_id_stock
			WHERE cc.status IN " . self::FINAL . " AND cc.counted_stock IS NOT NULL AND cc.executed_date BETWEEN :a AND :b AND " . $this->B('cc.branch_id') . ' AND ' . $this->P() . $extra . '
			ORDER BY cc.executed_date DESC, cc.id DESC', [':a' => $start, ':b' => $end]);
	}

	private function Accuracy(string $start, string $end): array
	{
		$r = $this->Rows("SELECT COUNT(*) AS n, SUM(CASE WHEN ABS(IFNULL(cc.difference_amount, 0)) < 0.001 THEN 1 ELSE 0 END) AS hits
			FROM stock_cycle_counts cc JOIN products p ON p.id = cc.product_id
			WHERE cc.status IN " . self::FINAL . " AND cc.counted_stock IS NOT NULL AND cc.executed_date BETWEEN :a AND :b AND " . $this->B('cc.branch_id') . ' AND ' . $this->P(), [':a' => $start, ':b' => $end])[0];
		$n = intval($r['n']);
		return ['lines' => $n, 'hits' => intval($r['hits']), 'pct' => $n > 0 ? round(intval($r['hits']) / $n * 100, 1) : null];
	}

	private function DifferencesChart(array $lines): array
	{
		$byBranch = $this->f['branch_id'] === null;
		$map = $this->DeptMap();
		$groups = [];
		foreach ($lines as $c)
		{
			$key = $byBranch ? strval($c['branch_id']) : ($map[mb_strtolower(trim(strval($c['department'])))] ?? 'otros');
			$label = $byBranch ? ($c['branch_name'] ?? ('Sucursal ' . $c['branch_id'])) : DailyMetricsService::DepartmentLabel($key);
			$groups[$key] ??= ['key' => $key, 'label' => $label, 'short_value' => 0.0, 'over_value' => 0.0, 'short_lines' => 0, 'over_lines' => 0, 'short_qty' => [], 'over_qty' => []];
			$side = floatval($c['difference_amount']) < 0 ? 'short' : 'over';
			$groups[$key][$side . '_value'] += abs(floatval($c['difference_cost']));
			$groups[$key][$side . '_lines']++;
			$this->AddQty($groups[$key][$side . '_qty'], intval($c['product_id']), abs(floatval($c['difference_amount'])));
		}
		foreach ($groups as &$g)
		{
			$g['short_value'] = round($g['short_value'], 2);
			$g['over_value'] = round($g['over_value'], 2);
			$g['short_qty'] = self::Qty($g['short_qty']);
			$g['over_qty'] = self::Qty($g['over_qty']);
		}
		unset($g);
		usort($groups, fn($a, $b) => ($b['short_value'] + $b['over_value']) <=> ($a['short_value'] + $a['over_value']));
		return ['by' => $byBranch ? 'branch' : 'department', 'groups' => array_slice($groups, 0, 10)];
	}

	private function TopDifferences(array $lines, int $n): array
	{
		usort($lines, fn($a, $b) => [abs(floatval($b['difference_cost'])), abs(floatval($b['difference_amount']))] <=> [abs(floatval($a['difference_cost'])), abs(floatval($a['difference_amount']))]);
		return array_map(fn($c) => ['id' => intval($c['id']), 'sku' => $c['sku'], 'product' => $c['product_name'], 'branch' => $c['branch_name'], 'branch_id' => intval($c['branch_id']),
			'difference' => round(floatval($c['difference_amount']), 3), 'value' => round(floatval($c['difference_cost']), 2), 'unit' => $c['qu_name'], 'status' => self::COUNT_STATUS[$c['status']] ?? $c['status']], array_slice($lines, 0, $n));
	}

	/** Acumulado diario de conteos programados en el periodo frente a los ya completados */
	private function Compliance(string $start, string $end): array
	{
		$rows = $this->Rows("SELECT cc.scheduled_date, cc.executed_date, cc.status FROM stock_cycle_counts cc JOIN products p ON p.id = cc.product_id
			WHERE cc.status <> 'cancelled' AND cc.scheduled_date BETWEEN :a AND :b AND " . $this->B('cc.branch_id') . ' AND ' . $this->P(), [':a' => $start, ':b' => $end]);
		$sched = $done = [];
		foreach ($rows as $r)
		{
			$sched[$r['scheduled_date']] = ($sched[$r['scheduled_date']] ?? 0) + 1;
			if ($r['executed_date'] && in_array($r['status'], ['completed', 'discrepancy', 'adjusted'], true))
			{
				$d = max($r['executed_date'], $start);
				$done[$d] = ($done[$d] ?? 0) + 1;
			}
		}
		$labels = $s = $c = [];
		$cs = $cc = 0;
		for ($t = strtotime($start); $t <= strtotime($end); $t = strtotime('+1 day', $t))
		{
			$d = date('Y-m-d', $t);
			$cs += $sched[$d] ?? 0;
			$cc += $done[$d] ?? 0;
			$labels[] = $d;
			$s[] = $cs;
			$c[] = $cc;
		}
		return ['labels' => $labels, 'scheduled' => $s, 'completed' => $c, 'total_scheduled' => $cs, 'total_completed' => $cc, 'pct' => $cs > 0 ? round(min($cc, $cs) / $cs * 100, 1) : null];
	}

	/* ---------------------------------------------------------------- Saldos por SKU y sucursal */

	/** Surtido (SKU activo con stock o movimiento en la sucursal en 90 días) con disponible al corte, demanda y cobertura */
	private function Pairs(): array
	{
		$cut = $this->Cut();
		$ids = DailyFilters::BranchIds($this->f);
		$active = [];
		foreach ($this->Rows("SELECT p.id, IFNULL(p.sku, 'SKU-' || p.id) AS sku, p.name, p.min_stock_amount FROM products p WHERE p.active = 1 AND " . $this->P()) as $r)
		{
			$active[intval($r['id'])] = $r;
		}
		$pairs = [];
		$key = fn($b, $p) => intval($b) . ':' . intval($p);
		foreach (InventoryOperationsService::GetInstance()->PositionMatrix($ids) as $r)
		{
			if (isset($active[$r['product_id']]))
			{
				$pairs[$key($r['branch_id'], $r['product_id'])] = ['branch_id' => $r['branch_id'], 'product_id' => $r['product_id'], 'physical' => $r['physical'], 'available' => $r['available'],
					'quarantine' => $r['quarantine'], 'bodega' => $r['bodega'], 'sala' => $r['sala']];
			}
		}
		foreach ($this->Rows("SELECT DISTINCT l.branch_id, sl.product_id FROM stock_log sl JOIN locations l ON l.id = sl.location_id
			WHERE sl.undone = 0 AND IFNULL(l.is_transit, 0) = 0 AND l.branch_id IS NOT NULL AND sl.row_created_timestamp BETWEEN :a AND :b AND " . $this->B('l.branch_id'),
			[':a' => date('Y-m-d 00:00:00', strtotime($this->f['end_date'] . ' -' . self::ASSORTMENT_DAYS . ' days')), ':b' => $cut]) as $r)
		{
			if (isset($active[intval($r['product_id'])]))
			{
				$pairs[$key($r['branch_id'], $r['product_id'])] ??= ['branch_id' => intval($r['branch_id']), 'product_id' => intval($r['product_id']), 'physical' => 0.0, 'available' => 0.0, 'quarantine' => 0.0, 'bodega' => 0.0, 'sala' => 0.0];
			}
		}
		// Corte anterior a hoy: el saldo se reconstruye con el kardex posterior al corte (sin reservas ni bloqueos históricos)
		if (!$this->IsCurrent())
		{
			foreach ($this->Rows("SELECT l.branch_id, sl.product_id, SUM(sl.amount) AS q FROM stock_log sl JOIN locations l ON l.id = sl.location_id
				WHERE sl.undone = 0 AND IFNULL(l.is_transit, 0) = 0 AND sl.row_created_timestamp > :c AND " . $this->B('l.branch_id') . ' GROUP BY 1, 2', [':c' => $cut]) as $r)
			{
				$k = $key($r['branch_id'], $r['product_id']);
				if (isset($pairs[$k]))
				{
					$pairs[$k]['physical'] = round($pairs[$k]['physical'] - floatval($r['q']), 4);
					$pairs[$k]['available'] = max(0.0, $pairs[$k]['physical']);
				}
			}
		}
		$demand = [];
		foreach ($this->Rows("SELECT l.branch_id, sl.product_id, SUM(-sl.amount) AS q FROM stock_log sl JOIN locations l ON l.id = sl.location_id
			WHERE sl.transaction_type = 'consume' AND sl.undone = 0 AND IFNULL(sl.spoiled, 0) = 0 AND sl.amount < 0 AND IFNULL(l.is_transit, 0) = 0
				AND sl.row_created_timestamp BETWEEN :a AND :c AND " . $this->B('l.branch_id') . '
				AND NOT EXISTS (SELECT 1 FROM waste_records w WHERE w.transaction_id = sl.transaction_id AND w.product_id = sl.product_id)
			GROUP BY 1, 2', [':a' => date('Y-m-d 00:00:00', strtotime($this->f['end_date'] . ' -' . (self::COVERAGE_DAYS - 1) . ' days')), ':c' => $cut]) as $r)
		{
			$demand[$key($r['branch_id'], $r['product_id'])] = floatval($r['q']);
		}
		$branchNames = array_column($this->Rows('SELECT id, name FROM branches'), 'name', 'id');
		$covs = [];
		$outPairs = $low = 0;
		$skuTotal = [];
		foreach ($pairs as $k => &$p)
		{
			$info = $active[$p['product_id']];
			$p['sku'] = $info['sku'];
			$p['name'] = $info['name'];
			$p['min_stock'] = floatval($info['min_stock_amount']);
			$p['branch'] = $branchNames[$p['branch_id']] ?? ('Sucursal ' . $p['branch_id']);
			$p['demand_daily'] = isset($demand[$k]) ? round($demand[$k] / self::COVERAGE_DAYS, 4) : 0.0;
			$p['coverage_days'] = $p['demand_daily'] > 0 ? round($p['available'] / $p['demand_daily'], 1) : null;
			$skuTotal[$p['product_id']] = ($skuTotal[$p['product_id']] ?? 0) + $p['available'];
			if ($p['available'] <= 0)
			{
				$outPairs++;
			}
			if ($p['coverage_days'] !== null)
			{
				$covs[] = $p['coverage_days'];
				if ($p['coverage_days'] > 0 && $p['coverage_days'] < self::LOW_COVERAGE_DAYS)
				{
					$low++;
				}
			}
		}
		unset($p);
		sort($covs);
		$n = count($covs);
		$median = $n === 0 ? null : ($n % 2 ? $covs[intdiv($n, 2)] : round(($covs[$n / 2 - 1] + $covs[$n / 2]) / 2, 1));
		return [
			'rows' => $pairs,
			'stockouts' => ['skus' => count(array_filter($skuTotal, fn($t) => $t <= 0)), 'pairs' => $outPairs, 'assortment_pairs' => count($pairs), 'assortment_skus' => count($skuTotal),
				'grouped' => $this->f['branch_id'] === null, 'is_current' => $this->IsCurrent()],
			'coverage' => ['median_days' => $median, 'pairs' => $n, 'low' => $low],
		];
	}

	private function Quarantine(): array
	{
		$acc = [];
		foreach ($this->Rows("SELECT q.product_id, SUM(q.amount) AS q FROM quarantine_items q JOIN products p ON p.id = q.product_id WHERE q.status = 'in_quarantine' AND " . $this->B('q.branch_id') . ' AND ' . $this->P() . ' GROUP BY 1') as $r)
		{
			$this->AddQty($acc, intval($r['product_id']), floatval($r['q']));
		}
		return $acc;
	}

	/* ---------------------------------------------------------------- Traslados */

	/** Traslados despachados hasta el corte con saldo pendiente de recepción (enviado − recibido) */
	private function OpenTransfers(): array
	{
		$cut = $this->Cut();
		$ids = DailyFilters::BranchIds($this->f);
		$in = implode(',', $ids ?: [0]);
		$scope = $ids === null ? '' : " AND (t.to_branch_id IN ($in) OR t.from_branch_id IN ($in))";
		$rows = $this->Rows("SELECT t.id, t.transfer_code, t.status, t.sent_at, t.from_branch_id, t.to_branch_id, bf.name AS from_branch, bt.name AS to_branch,
				i.product_id, i.sent_amount, IFNULL(i.received_amount, 0) AS received_amount
			FROM stock_transfers t JOIN stock_transfer_items i ON i.transfer_id = t.id JOIN products p ON p.id = i.product_id
			JOIN branches bf ON bf.id = t.from_branch_id JOIN branches bt ON bt.id = t.to_branch_id
			WHERE t.status <> 'cancelled' AND t.sent_at <= :c AND (t.status IN ('in_transit', 'sent', 'partially_received') OR t.received_at > :c2)$scope AND " . $this->P() . '
			ORDER BY t.sent_at', [':c' => $cut, ':c2' => $cut]);
		$out = [];
		$ref = $this->IsCurrent() ? time() : strtotime($cut);
		foreach ($rows as $r)
		{
			$id = intval($r['id']);
			$open = in_array($r['status'], ['in_transit', 'sent', 'partially_received'], true);
			$pend = $open ? floatval($r['sent_amount']) - floatval($r['received_amount']) : floatval($r['sent_amount']);
			$out[$id] ??= ['id' => $id, 'code' => $r['transfer_code'], 'status' => $open ? $r['status'] : 'received', 'sent_at' => $r['sent_at'], 'from' => $r['from_branch'], 'to' => $r['to_branch'],
				'products' => 0, 'pending_qty' => [], 'sent_qty' => [], 'hours' => round(($ref - strtotime($r['sent_at'])) / 3600, 1)];
			$out[$id]['products']++;
			$this->AddQty($out[$id]['pending_qty'], intval($r['product_id']), $pend);
			$this->AddQty($out[$id]['sent_qty'], intval($r['product_id']), floatval($r['sent_amount']));
		}
		foreach ($out as &$t)
		{
			$t['pending_qty'] = self::Qty($t['pending_qty']);
			$t['sent_qty'] = self::Qty($t['sent_qty']);
			$t['late'] = $t['hours'] > self::LATE_TRANSFER_HOURS;
		}
		unset($t);
		return array_values(array_filter($out, fn($t) => array_sum($t['pending_qty']) > 0.0001));
	}

	/* ---------------------------------------------------------------- Pendientes */

	private function Pending(array $transfers): array
	{
		$pf = $this->P();
		$adm = intval($this->Val("SELECT COUNT(*) FROM procurement_receipts r WHERE r.reception_stage = 'admin_recorded' AND " . $this->B('r.branch_id')));
		$po = intval($this->Val("SELECT COUNT(*) FROM procurement_orders o WHERE o.status IN ('sent', 'partially_received') AND " . $this->B('o.branch_id')));
		$counts = intval($this->Val("SELECT COUNT(*) FROM stock_cycle_counts cc JOIN products p ON p.id = cc.product_id WHERE cc.status = 'discrepancy' AND " . $this->B('cc.branch_id') . " AND $pf"));
		$sched = intval($this->Val("SELECT COUNT(*) FROM stock_cycle_counts cc JOIN products p ON p.id = cc.product_id WHERE cc.status = 'pending' AND cc.scheduled_date <= :d AND " . $this->B('cc.branch_id') . " AND $pf", [':d' => $this->f['end_date']]));
		$adj = intval($this->Val("SELECT COUNT(*) FROM inventory_adjustments a JOIN products p ON p.id = a.product_id WHERE a.status = 'pending_approval' AND " . $this->B('a.branch_id') . " AND $pf"));
		return [
			'receptions' => ['count' => $adm + $po, 'detail' => $po . ' OC por recibir · ' . $adm . ' sin ingreso físico', 'kind' => 'receptions'],
			'transfers' => ['count' => count($transfers), 'qty' => self::SumQty(array_column($transfers, 'pending_qty')), 'detail' => count(array_filter($transfers, fn($t) => $t['status'] === 'partially_received')) . ' con recepción parcial', 'kind' => 'transfers'],
			'counts' => ['count' => $counts, 'detail' => $sched . ' programados sin ejecutar', 'kind' => 'counts', 'params' => ['status' => 'discrepancy']],
			'adjustments' => ['count' => $adj, 'detail' => 'requieren aprobación de otro usuario', 'kind' => 'adjustments'],
		];
	}

	/* ---------------------------------------------------------------- Movimientos */

	private function MovementSql(string $select, string $where = '', string $tail = ''): string
	{
		return "SELECT $select FROM merchandise_movements m JOIN products p ON p.id = m.product_id
			WHERE m.undone = 0 AND m.is_transit = 0 AND m.bucket <> 'transit' AND " . $this->B('m.branch_id') . ' AND ' . $this->P() . " $where $tail";
	}

	private function Movements(string $start, string $end): array
	{
		$range = [':a' => $start . ' 00:00:00', ':b' => $end . ' 23:59:59'];
		$rows = $this->Rows($this->MovementSql("substr(m.recorded_at, 1, 10) AS d, m.bucket, m.product_id, SUM(m.amount) AS q, SUM(CASE WHEN m.amount > 0 THEN m.amount ELSE 0 END) AS qin, SUM(CASE WHEN m.amount < 0 THEN -m.amount ELSE 0 END) AS qout",
			'AND m.recorded_at BETWEEN :a AND :b', 'GROUP BY 1, 2, 3'), $range);
		$received = $exits = $exitsBy = $daily = $typesQty = [];
		foreach ($rows as $r)
		{
			$pid = intval($r['product_id']);
			$b = $r['bucket'];
			if ($b === 'receptions')
			{
				$this->AddQty($received, $pid, floatval($r['qin']));
			}
			if (in_array($b, self::EXIT_BUCKETS, true))
			{
				$this->AddQty($exits, $pid, floatval($r['qout']));
				$exitsBy[$b] ??= [];
				$this->AddQty($exitsBy[$b], $pid, floatval($r['qout']));
			}
			// Gráfico diario solo en piezas: kg y L no se suman con piezas
			if ($this->Unit($pid)[0] === 'u' && $b !== 'internal')
			{
				$daily[$r['d']][$b] = ($daily[$r['d']][$b] ?? 0) + floatval($r['q']);
			}
			foreach (self::TYPES as $tk => [$label, $buckets])
			{
				if (in_array($b, $buckets, true))
				{
					$typesQty[$tk] ??= [];
					// Reposición interna: una pata sale de bodega y otra entra a sala; lo movido se cuenta una vez
					$this->AddQty($typesQty[$tk], $pid, $b === 'internal' ? floatval($r['qin']) : floatval($r['qin']) + floatval($r['qout']));
				}
			}
		}
		$typeCounts = array_column($this->Rows($this->MovementSql("m.bucket, COUNT(DISTINCT IFNULL(m.operation_ref, m.id) || ':' || m.product_id) AS n", 'AND m.recorded_at BETWEEN :a AND :b', 'GROUP BY 1'), $range), 'n', 'bucket');
		$docs = intval($this->Val($this->MovementSql('COUNT(DISTINCT IFNULL(m.operation_ref, m.id))', "AND m.bucket = 'receptions' AND m.recorded_at BETWEEN :a AND :b"), $range));
		$types = [];
		foreach (self::TYPES as $tk => [$label, $buckets, $color])
		{
			$types[] = ['key' => $tk, 'label' => $label, 'color' => $color, 'count' => array_sum(array_map(fn($b) => intval($typeCounts[$b] ?? 0), $buckets)), 'qty' => self::Qty($typesQty[$tk] ?? [])];
		}
		$labels = [];
		$series = array_fill_keys(['receptions', 'transfers_in', 'returns', 'adjustments_in', 'sales', 'dispatches', 'waste', 'transfers_out', 'adjustments_out'], []);
		for ($t = strtotime($start); $t <= strtotime($end); $t = strtotime('+1 day', $t))
		{
			$d = date('Y-m-d', $t);
			$labels[] = $d;
			$x = $daily[$d] ?? [];
			foreach (['receptions', 'transfers_in', 'returns'] as $b)
			{
				$series[$b][] = round(max(0, $x[$b] ?? 0), 2);
			}
			foreach (['sales', 'dispatches', 'waste', 'transfers_out'] as $b)
			{
				$series[$b][] = round(min(0, $x[$b] ?? 0), 2);
			}
			$series['adjustments_in'][] = round(max(0, $x['adjustments'] ?? 0), 2);
			$series['adjustments_out'][] = round(min(0, $x['adjustments'] ?? 0), 2);
		}
		return ['received' => $received, 'received_docs' => $docs, 'exits' => $exits, 'exits_by' => $exitsBy, 'types' => $types, 'daily' => ['labels' => $labels, 'series' => $series]];
	}

	/** Movimientos confirmados (una vez por transacción y producto) en el día, la semana y el mes hasta el corte */
	private function Accumulated(): array
	{
		$end = $this->f['end_date'];
		$ranges = ['day' => $end, 'week' => date('Y-m-d', strtotime($end . ' -' . (intval(date('N', strtotime($end))) - 1) . ' days')), 'month' => substr($end, 0, 8) . '01'];
		$out = [];
		foreach ($ranges as $k => $from)
		{
			$out[$k] = intval($this->Val($this->MovementSql("COUNT(DISTINCT IFNULL(m.operation_ref, m.id) || ':' || m.product_id)", 'AND m.recorded_at BETWEEN :a AND :b'), [':a' => $from . ' 00:00:00', ':b' => $end . ' 23:59:59']));
			$out[$k . '_from'] = $from;
		}
		return $out;
	}

	/* ---------------------------------------------------------------- Stock disponible vs. mínimo */

	private function SeriesOptions(array $pairs): array
	{
		$rows = array_values(array_filter($pairs, fn($p) => $p['demand_daily'] > 0));
		// Primero los SKUs con mínimo configurado y menor cobertura
		usort($rows, fn($a, $b) => [$b['min_stock'] > 0 ? 1 : 0, $a['coverage_days'] ?? 1e9] <=> [$a['min_stock'] > 0 ? 1 : 0, $b['coverage_days'] ?? 1e9]);
		$opts = array_map(fn($p) => ['key' => $p['branch_id'] . ':' . $p['product_id'], 'label' => $p['sku'] . ' · ' . $p['name'] . ' · ' . $p['branch']], array_slice($rows, 0, 40));
		$first = $opts[0]['key'] ?? null;
		return ['options' => $opts, 'selected' => $first, 'data' => $first ? $this->Series(...array_map('intval', explode(':', $first))) : null];
	}

	public function SeriesFor(array $f, int $branchId, int $productId): array
	{
		$this->f = $f;
		if (!\Grocy\Helpers\DairyAccess::CanSeeBranch($branchId))
		{
			throw new \Exception('No tiene acceso a esa sucursal');
		}
		return $this->Series($branchId, $productId);
	}

	/** Cierre diario del stock físico de un SKU en una sucursal (saldo actual − movimientos posteriores) frente a su mínimo */
	private function Series(int $branchId, int $productId): array
	{
		$start = $this->f['start_date'];
		$end = $this->f['end_date'];
		$now = floatval($this->Val('SELECT IFNULL(SUM(s.amount), 0) FROM stock s JOIN locations l ON l.id = s.location_id WHERE s.product_id = :p AND l.branch_id = :b AND IFNULL(l.is_transit, 0) = 0', [':p' => $productId, ':b' => $branchId]));
		$byDay = array_column($this->Rows('SELECT substr(sl.row_created_timestamp, 1, 10) AS d, SUM(sl.amount) AS q FROM stock_log sl JOIN locations l ON l.id = sl.location_id
			WHERE sl.product_id = :p AND l.branch_id = :b AND sl.undone = 0 AND IFNULL(l.is_transit, 0) = 0 AND sl.row_created_timestamp >= :a GROUP BY 1', [':p' => $productId, ':b' => $branchId, ':a' => $start . ' 00:00:00']), 'q', 'd');
		$p = $this->Rows('SELECT p.name, IFNULL(p.sku, \'SKU-\' || p.id) AS sku, p.min_stock_amount, IFNULL(qu.name, \'u\') AS unit FROM products p LEFT JOIN quantity_units qu ON qu.id = p.qu_id_stock WHERE p.id = :p', [':p' => $productId])[0] ?? [];
		$labels = $stock = $min = [];
		$later = 0.0;
		foreach ($byDay as $d => $q)
		{
			if ($d > $end)
			{
				$later += floatval($q);
			}
		}
		$closing = [];
		for ($t = strtotime($end); $t >= strtotime($start); $t = strtotime('-1 day', $t))
		{
			$d = date('Y-m-d', $t);
			$closing[$d] = round($now - $later, 2);
			$later += floatval($byDay[$d] ?? 0);
		}
		ksort($closing);
		return ['branch_id' => $branchId, 'product_id' => $productId, 'product' => $p['name'] ?? '', 'sku' => $p['sku'] ?? '', 'unit' => $p['unit'] ?? 'u',
			'labels' => array_keys($closing), 'stock' => array_values($closing), 'min' => array_fill(0, count($closing), floatval($p['min_stock_amount'] ?? 0))];
	}

	/* ================================================================ Detalles filtrados */

	public function Detail(string $kind, array $f, array $q): array
	{
		$this->f = $f;
		switch ($kind)
		{
			case 'counts': return $this->DetailCounts($q);
			case 'movements': return $this->DetailMovements($q);
			case 'stockouts': return $this->DetailPairs($q, 'stockouts');
			case 'coverage': return $this->DetailPairs($q, 'coverage');
			case 'transfers': return $this->DetailTransfers($q);
			case 'adjustments': return $this->DetailAdjustments();
			case 'receptions': return $this->DetailReceptions();
		}
		throw new \Exception('Detalle no disponible');
	}

	private function DetailCounts(array $q): array
	{
		$status = strval($q['status'] ?? '');
		$sign = strval($q['sign'] ?? 'diff');
		if ($status === 'discrepancy')
		{
			// Por conciliar: estado actual, sin importar la fecha del conteo
			$rows = $this->Rows("SELECT cc.*, p.name AS product_name, IFNULL(p.sku, 'SKU-' || p.id) AS sku, b.name AS branch_name, l.name AS location_name, IFNULL(qu.name, 'u') AS qu_name
				FROM stock_cycle_counts cc JOIN products p ON p.id = cc.product_id LEFT JOIN branches b ON b.id = cc.branch_id LEFT JOIN locations l ON l.id = cc.location_id
				LEFT JOIN quantity_units qu ON qu.id = p.qu_id_stock WHERE cc.status = 'discrepancy' AND " . $this->B('cc.branch_id') . ' AND ' . $this->P() . ' ORDER BY cc.executed_date DESC, cc.id DESC');
		}
		else
		{
			$extra = '';
			if (($q['group'] ?? '') !== '')
			{
				$extra .= ($q['group_by'] ?? '') === 'branch' ? ' AND cc.branch_id = ' . intval($q['group']) : ' AND ' . DailyMetricsService::GetInstance()->DeptSql([strval($q['group'])]);
			}
			$extra .= ['short' => ' AND cc.difference_amount < 0', 'over' => ' AND cc.difference_amount > 0', 'diff' => ' AND ABS(cc.difference_amount) >= 0.001'][$sign] ?? '';
			$rows = $this->CountLines($this->f['start_date'], $this->f['end_date'], $extra);
			usort($rows, fn($a, $b) => abs(floatval($b['difference_cost'])) <=> abs(floatval($a['difference_cost'])));
		}
		$adjAt = [];
		$txs = array_values(array_filter(array_column($rows, 'adjustment_transaction_id')));
		if ($txs)
		{
			$adjAt = array_column($this->Rows('SELECT transaction_id, MIN(row_created_timestamp) AS at FROM stock_log WHERE transaction_id IN (' . implode(',', array_map(fn($t) => $this->Db()->quote($t), $txs)) . ') GROUP BY 1'), 'at', 'transaction_id');
		}
		$users = array_column($this->Rows("SELECT id, IFNULL(NULLIF(TRIM(IFNULL(first_name, '') || ' ' || IFNULL(last_name, '')), ''), username) AS n FROM users"), 'n', 'id');
		$out = array_map(fn($c) => [
			'id' => 'CC-' . str_pad(strval($c['id']), 4, '0', STR_PAD_LEFT), 'date' => $c['executed_date'] ?: $c['scheduled_date'], 'sku' => $c['sku'], 'product' => $c['product_name'], 'branch' => $c['branch_name'], 'location' => $c['location_name'] ?? '—',
			'system' => round(floatval($c['theoretical_stock']), 3), 'counted' => $c['counted_stock'] !== null ? round(floatval($c['counted_stock']), 3) : null, 'difference' => round(floatval($c['difference_amount']), 3),
			'value' => round(floatval($c['difference_cost']), 2), 'unit' => $c['qu_name'], 'status' => self::COUNT_STATUS[$c['status']] ?? $c['status'], 'status_key' => $c['status'],
			'trace' => $c['status'] === 'adjusted'
				? ($c['adjustment_transaction_id'] ? 'Ajuste ' . substr($c['adjustment_transaction_id'], 0, 8) . ' · ' . substr(strval($adjAt[$c['adjustment_transaction_id']] ?? ''), 0, 16) : 'Sin movimiento (el saldo ya cuadraba)') . ' · aprobó ' . ($users[$c['supervisor_user_id']] ?? '—')
				: ($c['status'] === 'discrepancy' ? 'Contó ' . ($users[$c['executed_by_user_id']] ?? '—') . ' · espera aprobación de otro usuario' : '—'),
		], array_slice($rows, 0, 400));
		return ['kind' => 'counts', 'title' => $status === 'discrepancy' ? 'Diferencias por conciliar' : 'Diferencias de conteo', 'total' => count($rows), 'rows' => $out, 'link' => '/inventory',
			'columns' => [['id', 'Conteo'], ['date', 'Fecha'], ['sku', 'SKU'], ['product', 'Producto'], ['branch', 'Sucursal'], ['location', 'Ubicación'], ['system', 'Sistema al contar', 'r'], ['counted', 'Contado', 'r'], ['difference', 'Diferencia', 'r'], ['value', 'Valor', 'r'], ['status', 'Estado'], ['trace', 'Ajuste vinculado']]];
	}

	private function DetailMovements(array $q): array
	{
		$isDate = fn($v) => is_string($v) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $v);
		$p = [':a' => ($isDate($q['from'] ?? null) ? $q['from'] : $this->f['start_date']) . ' 00:00:00', ':b' => $this->f['end_date'] . ' 23:59:59'];
		if ($isDate($q['date'] ?? null))
		{
			$p = [':a' => $q['date'] . ' 00:00:00', ':b' => $q['date'] . ' 23:59:59'];
		}
		$where = 'AND m.recorded_at BETWEEN :a AND :b';
		$type = strval($q['type'] ?? '');
		$buckets = $type === 'all_exits' ? self::EXIT_BUCKETS : (self::TYPES[$type][1] ?? (isset(self::BUCKET_LABELS[$type]) ? [$type] : null));
		if ($buckets)
		{
			$where .= " AND m.bucket IN ('" . implode("', '", $buckets) . "')";
		}
		if ($type === '' || $type === 'internal')
		{
			$where .= " AND NOT (m.bucket = 'internal' AND m.amount < 0)"; // una fila por reposición: la pata que entra a sala
		}
		$total = intval($this->Val($this->MovementSql('COUNT(*)', $where), $p));
		$rows = $this->Rows($this->MovementSql("m.id, m.operation_ref, m.product_id, m.amount, m.stock_id, m.location_id, m.location_name, m.branch_id, m.recorded_at, m.bucket,
			p.name AS product_name, IFNULL(p.sku, 'SKU-' || p.id) AS sku, (SELECT name FROM quantity_units WHERE id = p.qu_id_stock) AS unit,
			(SELECT best_before_date FROM stock_log WHERE id = m.id) AS best_before_date, (SELECT name FROM branches WHERE id = m.branch_id) AS branch_name",
			$where, 'ORDER BY m.recorded_at DESC, m.id DESC LIMIT 400'), $p);
		$docs = $this->Documents($rows);
		$out = [];
		foreach ($rows as $r)
		{
			$d = $docs[$r['id']];
			$bbd = $r['best_before_date'] ?? null;
			$out[] = ['date' => substr($r['recorded_at'], 0, 16), 'type' => self::BUCKET_LABELS[$r['bucket']] ?? $r['bucket'], 'sku' => $r['sku'], 'product' => $r['product_name'],
				'lot' => $bbd && $bbd !== '2999-12-31' ? substr(strval($r['stock_id']), 0, 14) . ' · vence ' . date('d/m/Y', strtotime($bbd)) : '—',
				'from' => $d['from'], 'to' => $d['to'], 'qty' => round(floatval($r['amount']), 3), 'unit' => $r['unit'] ?: 'u', 'status' => $d['status'], 'doc' => $d['doc'] ?? ('MOV-' . $r['id'])];
		}
		return ['kind' => 'movements', 'title' => 'Movimientos' . ($buckets ? ' · ' . ($type === 'all_exits' ? 'Salidas de inventario' : (self::TYPES[$type][0] ?? self::BUCKET_LABELS[$type])) : ''), 'total' => $total, 'rows' => $out, 'link' => '/kardex',
			'columns' => [['date', 'Fecha'], ['type', 'Tipo'], ['sku', 'SKU'], ['product', 'Producto'], ['lot', 'Lote'], ['from', 'Origen'], ['to', 'Destino'], ['qty', 'Cantidad', 'r'], ['status', 'Estado'], ['doc', 'Documento']]];
	}

	/** Documento que originó cada movimiento y su origen / destino (venta, recepción, traslado, merma, ajuste, reposición) */
	private function Documents(array $rows): array
	{
		$db = $this->Db();
		$quote = fn(array $v) => implode(',', array_map(fn($x) => $db->quote(strval($x)), array_values(array_unique($v)) ?: ['']));
		$txs = array_filter(array_column($rows, 'operation_ref'));
		$sales = $waste = $rec = $adj = $cc = $rep = $other = $trf = [];
		if ($txs)
		{
			$in = $quote($txs);
			$sales = array_column($this->Rows("SELECT si.transaction_id AS tx, s.sale_number FROM sale_items si JOIN sales s ON s.id = si.sale_id WHERE si.transaction_id IN ($in)"), 'sale_number', 'tx');
			foreach ($this->Rows("SELECT transaction_id AS tx, product_id, case_number FROM waste_records WHERE transaction_id IN ($in)") as $r)
			{
				$waste[$r['tx'] . ':' . $r['product_id']] = $r['case_number'];
			}
			foreach ($this->Rows("SELECT r.stock_transaction_id AS tx, r.receipt_code, sh.name AS supplier FROM procurement_receipts r LEFT JOIN shopping_locations sh ON sh.id = r.supplier_id WHERE r.stock_transaction_id IN ($in)") as $r)
			{
				$rec[$r['tx']] = $r;
			}
			$adj = array_column($this->Rows("SELECT transaction_id AS tx, adjustment_number FROM inventory_adjustments WHERE transaction_id IN ($in)"), 'adjustment_number', 'tx');
			foreach ($this->Rows("SELECT adjustment_transaction_id AS tx, id FROM stock_cycle_counts WHERE adjustment_transaction_id IN ($in)") as $r)
			{
				$cc[$r['tx']] = 'CC-' . str_pad(strval($r['id']), 4, '0', STR_PAD_LEFT);
			}
			foreach ($this->Rows("SELECT w.transaction_id AS tx, w.id, lf.name AS from_name FROM wms_internal_movements w LEFT JOIN locations lf ON lf.id = w.from_location_id WHERE w.transaction_id IN ($in)") as $r)
			{
				$rep[$r['tx']] = $r;
			}
			// Pata de salida de la misma transacción (ubicación de origen de una reposición interna)
			foreach ($this->Rows("SELECT sl.transaction_id AS tx, sl.product_id, l.name FROM stock_log sl JOIN locations l ON l.id = sl.location_id WHERE sl.transaction_id IN ($in) AND sl.transaction_type = 'transfer_from' AND sl.undone = 0") as $r)
			{
				$other[$r['tx'] . ':' . $r['product_id']] = $r['name'];
			}
		}
		$lots = array_filter(array_map(fn($r) => in_array($r['bucket'], ['transfers_in', 'transfers_out'], true) ? $r['stock_id'] : null, $rows));
		if ($lots)
		{
			foreach ($this->Rows('SELECT i.product_id, i.stock_id, t.transfer_code, t.status, t.from_location_id, t.to_location_id, bf.name AS from_branch, bt.name AS to_branch
				FROM stock_transfer_items i JOIN stock_transfers t ON t.id = i.transfer_id JOIN branches bf ON bf.id = t.from_branch_id JOIN branches bt ON bt.id = t.to_branch_id WHERE i.stock_id IN (' . $quote($lots) . ')') as $r)
			{
				$trf[$r['product_id'] . ':' . $r['stock_id'] . ':' . $r['from_location_id'] . ':o'] = $r;
				$trf[$r['product_id'] . ':' . $r['stock_id'] . ':' . $r['to_location_id'] . ':d'] = $r;
			}
		}
		$tStatus = ['in_transit' => 'en tránsito', 'partially_received' => 'recepción parcial', 'received' => 'recibido', 'cancelled' => 'anulado'];
		$out = [];
		foreach ($rows as $r)
		{
			$tx = $r['operation_ref'];
			$here = $r['location_name'] . ($r['branch_name'] ? ' · ' . $r['branch_name'] : '');
			$neg = floatval($r['amount']) < 0;
			$x = ['from' => $neg ? $here : '—', 'to' => $neg ? '—' : $here, 'doc' => null, 'status' => 'Confirmado'];
			switch ($r['bucket'])
			{
				case 'sales':
					$x = ['from' => $here, 'to' => 'Cliente · POS', 'doc' => $sales[$tx] ?? null, 'status' => 'Confirmado'];
					break;
				case 'dispatches':
					$x = ['from' => $here, 'to' => 'Cliente · despacho', 'doc' => null, 'status' => 'Confirmado'];
					break;
				case 'waste':
					$x = ['from' => $here, 'to' => 'Merma', 'doc' => $waste[$tx . ':' . $r['product_id']] ?? null, 'status' => 'Confirmado'];
					break;
				case 'receptions':
					$x = ['from' => 'Proveedor' . (!empty($rec[$tx]['supplier']) ? ' · ' . $rec[$tx]['supplier'] : ''), 'to' => $here, 'doc' => $rec[$tx]['receipt_code'] ?? null, 'status' => 'Confirmado'];
					break;
				case 'returns':
					$x = ['from' => 'Cliente · devolución aprobada', 'to' => $here, 'doc' => null, 'status' => 'Reingresado'];
					break;
				case 'adjustments':
					$x = ['from' => $neg ? $here : 'Ajuste de inventario', 'to' => $neg ? 'Ajuste de inventario' : $here, 'doc' => $adj[$tx] ?? ($cc[$tx] ?? null), 'status' => 'Ajuste confirmado'];
					break;
				case 'internal':
					$x = ['from' => $rep[$tx]['from_name'] ?? ($other[$tx . ':' . $r['product_id']] ?? 'Bodega'), 'to' => $here, 'doc' => isset($rep[$tx]) ? 'REP-' . str_pad(strval($rep[$tx]['id']), 4, '0', STR_PAD_LEFT) : null, 'status' => 'Confirmado'];
					break;
				case 'transfers_out':
					$t = $trf[$r['product_id'] . ':' . $r['stock_id'] . ':' . $r['location_id'] . ':o'] ?? null;
					$x = ['from' => $here, 'to' => 'Tránsito → ' . ($t['to_branch'] ?? 'otra sucursal'), 'doc' => $t['transfer_code'] ?? null, 'status' => 'Despachado' . ($t ? ' · ' . ($tStatus[$t['status']] ?? $t['status']) : '')];
					break;
				case 'transfers_in':
					$t = $trf[$r['product_id'] . ':' . $r['stock_id'] . ':' . $r['location_id'] . ':d'] ?? null;
					$x = ['from' => 'Tránsito ← ' . ($t['from_branch'] ?? 'otra sucursal'), 'to' => $here, 'doc' => $t['transfer_code'] ?? null, 'status' => 'Recibido' . ($t ? ' · ' . ($tStatus[$t['status']] ?? $t['status']) : '')];
					break;
			}
			$out[$r['id']] = $x;
		}
		return $out;
	}

	private function DetailPairs(array $q, string $mode): array
	{
		$rows = array_values(array_filter($this->Pairs()['rows'], fn($p) => $mode === 'stockouts' ? $p['available'] <= 0
			: ($p['coverage_days'] !== null && (!empty($q['all']) || ($p['coverage_days'] > 0 && $p['coverage_days'] < self::LOW_COVERAGE_DAYS)))));
		usort($rows, fn($a, $b) => $mode === 'stockouts' ? $b['demand_daily'] <=> $a['demand_daily'] : $a['coverage_days'] <=> $b['coverage_days']);
		$out = array_map(fn($p) => ['sku' => $p['sku'], 'product' => $p['name'], 'branch' => $p['branch'], 'physical' => round($p['physical'], 2), 'quarantine' => round($p['quarantine'], 2),
			'available' => round($p['available'], 2), 'bodega' => round($p['bodega'], 2), 'sala' => round($p['sala'], 2), 'demand' => round($p['demand_daily'], 2),
			'coverage' => $p['coverage_days'], 'min' => $p['min_stock']], array_slice($rows, 0, 400));
		return ['kind' => $mode, 'title' => $mode === 'stockouts' ? 'SKUs agotados por sucursal' : (!empty($q['all']) ? 'Cobertura por SKU y sucursal' : 'Cobertura baja (menos de ' . self::LOW_COVERAGE_DAYS . ' días)'),
			'total' => count($rows), 'rows' => $out, 'link' => '/inventory/stockouts',
			'note' => $this->IsCurrent() ? null : 'Corte anterior a hoy: el disponible se reconstruye con el kardex posterior al corte (sin reservas históricas).',
			'columns' => [['sku', 'SKU'], ['product', 'Producto'], ['branch', 'Sucursal'], ['physical', 'Físico', 'r'], ['quarantine', 'Cuarentena', 'r'], ['available', 'Disponible', 'r'], ['bodega', 'Bodega', 'r'], ['sala', 'Sala', 'r'], ['demand', 'Demanda diaria', 'r'], ['coverage', 'Cobertura (días)', 'r'], ['min', 'Mínimo', 'r']]];
	}

	private function DetailTransfers(array $q): array
	{
		$rows = $this->OpenTransfers();
		if (!empty($q['late']))
		{
			$rows = array_values(array_filter($rows, fn($t) => $t['late']));
		}
		$st = ['in_transit' => 'En tránsito', 'partially_received' => 'Recepción parcial', 'received' => 'Recibido después del corte', 'sent' => 'Despachado'];
		$fmt = fn(array $qty) => implode(' · ', array_map(fn($u) => rtrim(rtrim(number_format($qty[$u], 2, '.', ','), '0'), '.') . ' ' . $u, array_keys(array_filter($qty, fn($v) => abs($v) > 0.0001))));
		$out = array_map(fn($t) => ['doc' => $t['code'], 'sent_at' => substr($t['sent_at'], 0, 16), 'from' => $t['from'], 'to' => $t['to'], 'products' => $t['products'], 'sent' => $fmt($t['sent_qty']),
			'pending' => $fmt($t['pending_qty']), 'hours' => $t['hours'], 'status' => ($st[$t['status']] ?? $t['status']) . ($t['late'] ? ' · atrasado' : '')], $rows);
		return ['kind' => 'transfers', 'title' => !empty($q['late']) ? 'Traslados atrasados' : 'Traslados pendientes de recepción', 'total' => count($rows), 'rows' => $out, 'link' => '/transfers',
			'columns' => [['doc', 'Traslado'], ['sent_at', 'Despachado'], ['from', 'Origen'], ['to', 'Destino'], ['products', 'SKUs', 'r'], ['sent', 'Enviado', 'r'], ['pending', 'En tránsito', 'r'], ['hours', 'Horas', 'r'], ['status', 'Estado']]];
	}

	private function DetailAdjustments(): array
	{
		$rows = $this->Rows("SELECT a.*, p.name AS product_name, IFNULL(p.sku, 'SKU-' || p.id) AS sku, b.name AS branch_name, l.name AS location_name FROM inventory_adjustments a JOIN products p ON p.id = a.product_id
			LEFT JOIN branches b ON b.id = a.branch_id LEFT JOIN locations l ON l.id = a.location_id WHERE a.status = 'pending_approval' AND " . $this->B('a.branch_id') . ' AND ' . $this->P() . ' ORDER BY a.created_timestamp DESC');
		$out = array_map(fn($a) => ['doc' => $a['adjustment_number'], 'date' => substr(strval($a['created_timestamp']), 0, 16), 'sku' => $a['sku'], 'product' => $a['product_name'], 'branch' => $a['branch_name'],
			'location' => $a['location_name'] ?? '—', 'system' => floatval($a['recorded_stock']), 'counted' => floatval($a['counted_stock']), 'difference' => floatval($a['difference_amount']),
			'value' => round(floatval($a['total_cost_impact']), 2), 'status' => 'Por aprobar'], $rows);
		return ['kind' => 'adjustments', 'title' => 'Ajustes por aprobar', 'total' => count($rows), 'rows' => $out, 'link' => '/stockoverview?tab=operaciones#inv-ops',
			'columns' => [['doc', 'Ajuste'], ['date', 'Solicitado'], ['sku', 'SKU'], ['product', 'Producto'], ['branch', 'Sucursal'], ['location', 'Ubicación'], ['system', 'Sistema', 'r'], ['counted', 'Contado', 'r'], ['difference', 'Diferencia', 'r'], ['value', 'Valor', 'r'], ['status', 'Estado']]];
	}

	private function DetailReceptions(): array
	{
		$rows = $this->Rows("SELECT o.order_code AS doc, o.status, IFNULL(o.sent_at, o.created_at) AS at, o.expected_delivery_date AS due, b.name AS branch_name, sh.name AS supplier
			FROM procurement_orders o LEFT JOIN branches b ON b.id = o.branch_id LEFT JOIN shopping_locations sh ON sh.id = o.supplier_id WHERE o.status IN ('sent', 'partially_received') AND " . $this->B('o.branch_id') . "
			UNION ALL SELECT r.receipt_code, 'admin_recorded', r.received_at, NULL, b.name, sh.name FROM procurement_receipts r LEFT JOIN branches b ON b.id = r.branch_id LEFT JOIN shopping_locations sh ON sh.id = r.supplier_id
			WHERE r.reception_stage = 'admin_recorded' AND " . $this->B('r.branch_id') . ' ORDER BY 3 DESC');
		$st = ['sent' => 'OC enviada · por recibir', 'partially_received' => 'OC con recepción parcial', 'admin_recorded' => 'Documento registrado · falta ingreso físico'];
		$out = array_map(fn($r) => ['doc' => $r['doc'], 'date' => substr(strval($r['at']), 0, 16), 'supplier' => $r['supplier'] ?? '—', 'branch' => $r['branch_name'] ?? '—', 'due' => $r['due'] ?? '—', 'status' => $st[$r['status']] ?? $r['status']], $rows);
		return ['kind' => 'receptions', 'title' => 'Recepciones por confirmar', 'total' => count($rows), 'rows' => $out, 'link' => '/mrp',
			'columns' => [['doc', 'Documento'], ['date', 'Fecha'], ['supplier', 'Proveedor'], ['branch', 'Sucursal'], ['due', 'Entrega esperada'], ['status', 'Estado']]];
	}
}
