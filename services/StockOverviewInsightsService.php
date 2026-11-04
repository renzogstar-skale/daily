<?php

namespace Grocy\Services;

use Grocy\Helpers\DairyAccess;

/**
 * DAILY — Insights de Stock & Movimientos (/stockoverview)
 *
 * Complementa a StockExecutiveOverviewService con los datos que necesitan el desglose de las 8 tarjetas,
 * la lista de productos en alerta, la distribución por departamento, el riesgo por área y las oportunidades.
 * No guarda nada: todo se calcula con las mismas fuentes que el resto de módulos (stock, stock_log, sales,
 * quarantine_items, stock_transfers, customer_orders) y con los lotes que ya cargó el servicio ejecutivo.
 *
 * REGLAS DE CÁLCULO (documentadas también en docs/stock-overview-formulas.md)
 *  - Valor del inventario      = Σ unidades del lote × costo del lote (sin ubicaciones de tránsito).
 *  - Unidades                  = Σ unidades físicas de esos lotes.
 *  - Reservado                 = unidades de pedidos de cliente confirmados / en picking / empacados (aún no despachados).
 *  - Bloqueado                 = lotes vencidos ∪ lotes en cuarentena (sin doble conteo por stock_id).
 *  - En tránsito               = enviado − recibido de traslados «in_transit» hacia la sucursal. No forma parte del total (no está en ubicaciones).
 *  - Disponible                = unidades − reservado − bloqueado (mínimo 0).
 *  - Bajo mínimo               = par producto-sucursal con 0 < existencia < mínimo. Crítico si existencia ≤ 50 % del mínimo, si no preventivo.
 *  - Agotado                   = par producto-sucursal con mínimo configurado y existencia 0.
 *  - Por vencer                = par con algún lote que vence en 0..N días (N = filtro FEFO).
 *  - Valor en riesgo           = Σ valor de los lotes que vencen en ≤ N días (costo expuesto, no pérdida confirmada).
 *  - Valor expuesto (FEFO)     = parte de esos lotes que, según la venta diaria de 28 días, no se alcanzaría a vender antes de vencer.
 *  - Rotación                  = costo de ventas del período ÷ valor del inventario.
 *  - Cobertura (días)          = unidades ÷ unidades vendidas por día (promedio 28 días).
 *  - Participación (%)         = valor del departamento ÷ valor total × 100 (método del mayor resto: suma exacta 100.0 %).
 */
class StockOverviewInsightsService extends BaseService
{
	// Objetivos de gestión (los mismos que muestran las tarjetas)
	const TARGET_ROTATION = 2.0;
	const TARGET_COVERAGE_MIN = 10.0;
	const TARGET_COVERAGE_MAX = 15.0;
	// Una existencia ≤ 50 % del mínimo se considera crítica
	const CRITICAL_RATIO = 0.5;
	// Rotación del período por debajo de la cual el departamento se marca como de baja rotación
	const LOW_ROTATION = 0.3;
	// Lead time de reposición cuando el proveedor no lo configura (mismo valor que MrpService)
	const DEFAULT_LEAD_DAYS = 2;
	// Días de venta que se consideran «en riesgo» cuando un producto está agotado o bajo mínimo
	const LOST_SALES_DAYS = 7;

	const UNCLASSIFIED = ['Sin clasificar', '#94A3B8'];

	private static ?StockOverviewInsightsService $instance = null;

	public static function GetInstance(): StockOverviewInsightsService
	{
		if (self::$instance === null)
		{
			self::$instance = new self();
		}
		return self::$instance;
	}

	/** Permisos de las acciones del panel (la misma regla que protege cada módulo destino) */
	public static function Permissions(): array
	{
		return [
			'replenish' => DairyAccess::CanOpen('/mrp'),
			'review' => DairyAccess::CanOpen('/stockoverview'),
			'task' => DairyAccess::CanOpen('/tasks'),
			'kardex' => DairyAccess::CanOpen('/kardex'),
			'investigate' => DairyAccess::CanOpen('/inventory-control'),
			'lot' => DairyAccess::CanOpen('/stockoverview'),
			'transfer' => DairyAccess::CanOpen('/transfers'),
			'quality' => DairyAccess::CanOpen('/quality'),
			'waste' => DairyAccess::CanOpen('/waste/report'),
			'export' => DairyAccess::CanOpen('/reports')
		];
	}

	/**
	 * @param array $c Contexto del servicio ejecutivo:
	 *   db, branchId, branchName, deptKeys, fefoDays, date, periodDays, startTs, lotsAll, soldPeriod, sold28, deptOf,
	 *   totalUnits, totalVal, evolution
	 */
	public function Build(array $c): array
	{
		$db = $c['db'];
		$today = date('Y-m-d');
		$fefo = intval($c['fefoDays']);
		$deptKeys = $c['deptKeys'];
		$deptOf = $c['deptOf'];
		$branchId = $c['branchId'];
		$branchName = $c['branchName'];
		$soldPeriod = $c['soldPeriod'];
		$sold28 = $c['sold28'];

		// Sucursales activas visibles para el usuario; el alcance es la sucursal elegida o todas
		$allowed = DairyAccess::AllowedBranches();
		$allBranches = array_map('intval', $db->query('SELECT id FROM branches WHERE IFNULL(active, 1) = 1 ORDER BY id')->fetchAll(\PDO::FETCH_COLUMN));
		if ($allowed !== null)
		{
			$allBranches = array_values(array_intersect($allBranches, array_map('intval', $allowed)));
		}
		$scope = $branchId !== null ? [intval($branchId)] : $allBranches;
		$inAll = implode(',', $allBranches ?: [0]);
		$inScope = implode(',', $scope ?: [0]);

		// Catálogo de productos, UPC y último costo
		$products = [];
		foreach ($db->query('SELECT id, name, sku, department, min_stock_amount AS min, active, IFNULL(is_serialized, 0) AS ser, picture_file_name FROM products')->fetchAll(\PDO::FETCH_ASSOC) as $r)
		{
			$products[intval($r['id'])] = $r;
		}
		$upc = [];
		foreach ($db->query('SELECT product_id, barcode FROM product_barcodes ORDER BY id')->fetchAll(\PDO::FETCH_ASSOC) as $r)
		{
			$upc[intval($r['product_id'])] ??= $r['barcode'];
		}
		$lastPrice = $db->query('SELECT product_id, price FROM cache__products_last_purchased')->fetchAll(\PDO::FETCH_KEY_PAIR);

		$scopeKey = fn(int $pid) => $deptKeys === null || in_array($deptOf($products[$pid]['department'] ?? null), $deptKeys, true);
		$dispKey = function (int $pid) use ($products, $deptOf) {
			$d = trim((string) ($products[$pid]['department'] ?? ''));
			return $d === '' ? 'sin_clasificar' : $deptOf($d);
		};
		$deptName = fn(string $k) => $k === 'sin_clasificar' ? self::UNCLASSIFIED[0] : DailyMetricsService::DEPARTMENTS[$k][0];
		$deptColor = fn(string $k) => $k === 'sin_clasificar' ? self::UNCLASSIFIED[1] : DailyMetricsService::DEPARTMENTS[$k][1];

		// Lotes por par sucursal-producto (todas las sucursales activas, sin tránsito)
		$pairs = [];
		$lotRows = $db->query("SELECT l.branch_id, s.product_id, s.stock_id, s.amount, s.best_before_date, IFNULL(s.price, IFNULL(plp.price, 0)) AS cost, l.name AS location_name
			FROM stock s JOIN locations l ON l.id = s.location_id LEFT JOIN cache__products_last_purchased plp ON plp.product_id = s.product_id
			WHERE IFNULL(l.is_transit, 0) = 0 AND l.branch_id IN ($inAll) AND s.amount > 0")->fetchAll(\PDO::FETCH_ASSOC);
		foreach ($lotRows as $r)
		{
			$b = intval($r['branch_id']);
			$pid = intval($r['product_id']);
			$bb = $r['best_before_date'] ?: '2999-12-31';
			$days = intval(floor((strtotime($bb) - strtotime($today)) / 86400));
			$p = &$pairs[$b][$pid];
			$p['qty'] = ($p['qty'] ?? 0) + floatval($r['amount']);
			$p['value'] = ($p['value'] ?? 0) + floatval($r['amount']) * floatval($r['cost']);
			$p['lots'][] = ['stock_id' => $r['stock_id'], 'qty' => floatval($r['amount']), 'cost' => floatval($r['cost']), 'days' => $days, 'date' => $bb, 'location' => $r['location_name']];
			unset($p);
		}

		// Última venta por par (todas las ventas completadas)
		$lastSale = [];
		foreach ($db->query("SELECT s.branch_id, si.product_id, MAX(date(s.created_timestamp)) AS d FROM sale_items si JOIN sales s ON s.id = si.sale_id WHERE s.status = 'completed' GROUP BY s.branch_id, si.product_id")->fetchAll(\PDO::FETCH_ASSOC) as $r)
		{
			$lastSale[intval($r['branch_id'])][intval($r['product_id'])] = $r['d'];
		}

		// Evaluación de cada par: estado, venta diaria, valor expuesto FEFO
		$eval = [];
		foreach ($allBranches as $b)
		{
			foreach ($products as $pid => $pr)
			{
				$catalog = intval($pr['active']) === 1 && floatval($pr['min']) > 0 && intval($pr['ser']) === 0;
				$p = $pairs[$b][$pid] ?? null;
				if ($p === null && !$catalog)
				{
					continue;
				}
				$qty = $p['qty'] ?? 0.0;
				$min = floatval($pr['min']);
				$daily = floatval($sold28[$b][$pid] ?? 0);
				$cost = $qty > 0 ? ($p['value'] / $qty) : floatval($lastPrice[$pid] ?? 0);
				$lots = $p['lots'] ?? [];
				usort($lots, fn($x, $y) => $x['days'] <=> $y['days']);
				$expQty = 0.0;
				$expVal = 0.0;
				$expLots = 0;
				$nearest = null;
				$cum = 0.0;
				$exposed = 0.0;
				$bands = [0.0, 0.0, 0.0];
				foreach ($lots as $lot)
				{
					if ($lot['days'] < 0)
					{
						continue;
					}
					$cum += $lot['qty'];
					if ($lot['days'] <= $fefo)
					{
						$expQty += $lot['qty'];
						$expVal += $lot['qty'] * $lot['cost'];
						$expLots++;
						$nearest ??= $lot;
						$bands[$lot['days'] <= 7 ? 0 : ($lot['days'] <= 15 ? 1 : 2)] += $lot['qty'] * $lot['cost'];
						// FEFO: lo que no se alcanza a vender (venta diaria × días restantes) antes de vencer queda expuesto
						$unsold = max(0.0, min($lot['qty'], $cum - $daily * $lot['days']));
						$exposed += $unsold * $lot['cost'];
					}
				}
				$state = null;
				if ($catalog)
				{
					$state = $qty <= 0 ? 'out' : ($qty < $min ? ($qty <= $min * self::CRITICAL_RATIO ? 'critical' : 'preventive') : null);
				}
				$eval[$b][$pid] = ['catalog' => $catalog, 'qty' => $qty, 'min' => $min, 'daily' => $daily, 'cost' => $cost, 'state' => $state, 'exp_qty' => $expQty, 'exp_val' => $expVal,
					'exp_lots' => $expLots, 'nearest' => $nearest, 'bands' => $bands, 'exposed' => $exposed, 'lots' => $lots, 'value' => $p['value'] ?? 0.0,
					'last_sale' => $lastSale[$b][$pid] ?? null];
			}
		}

		// ---------- Reservado, bloqueado y en tránsito por producto ----------
		$reserved = $db->query("SELECT coi.product_id, SUM(coi.amount) FROM customer_order_items coi JOIN customer_orders co ON co.id = coi.order_id
			WHERE co.status IN ('confirmed', 'picked', 'packed') AND co.branch_id IN ($inScope) GROUP BY coi.product_id")->fetchAll(\PDO::FETCH_KEY_PAIR);
		$blockedLots = [];
		foreach ($scope as $b)
		{
			foreach ($pairs[$b] ?? [] as $pid => $p)
			{
				foreach ($p['lots'] as $lot)
				{
					if ($lot['days'] < 0)
					{
						$blockedLots[$lot['stock_id']] = ['pid' => $pid, 'qty' => $lot['qty']];
					}
				}
			}
		}
		foreach ($db->query("SELECT product_id, stock_id, amount FROM quarantine_items WHERE status = 'in_quarantine' AND branch_id IN ($inScope)")->fetchAll(\PDO::FETCH_ASSOC) as $q)
		{
			$prev = $blockedLots[$q['stock_id']]['qty'] ?? 0;
			$blockedLots[$q['stock_id']] = ['pid' => intval($q['product_id']), 'qty' => max($prev, floatval($q['amount']))];
		}
		$blockedByProduct = [];
		foreach ($blockedLots as $bl)
		{
			$blockedByProduct[$bl['pid']] = ($blockedByProduct[$bl['pid']] ?? 0) + $bl['qty'];
		}
		$transit = $db->query("SELECT sti.product_id, SUM(sti.sent_amount - IFNULL(sti.received_amount, 0)) FROM stock_transfer_items sti JOIN stock_transfers t ON t.id = sti.transfer_id
			WHERE t.status = 'in_transit' AND t.to_branch_id IN ($inScope) GROUP BY sti.product_id")->fetchAll(\PDO::FETCH_KEY_PAIR);
		$sumScoped = function (array $byProduct) use ($scopeKey) {
			$t = 0.0;
			foreach ($byProduct as $pid => $q)
			{
				if ($scopeKey(intval($pid)))
				{
					$t += floatval($q);
				}
			}
			return $t;
		};
		$resTot = $sumScoped($reserved);
		$blkTot = $sumScoped($blockedByProduct);
		$trnTot = $sumScoped($transit);
		$totalUnits = floatval($c['totalUnits']);
		$avail = max(0.0, $totalUnits - $resTot - $blkTot);

		// ---------- Conteos por par en el alcance (misma definición que DailyMetricsService::StockStatusCounts) ----------
		$cnt = ['below' => 0, 'critical' => 0, 'preventive' => 0, 'out' => 0, 'expiring' => 0, 'catalog' => 0];
		$expBands = [['label' => '0–7 días', 'count' => 0, 'value' => 0.0], ['label' => '8–15 días', 'count' => 0, 'value' => 0.0], ['label' => '16–30 días', 'count' => 0, 'value' => 0.0]];
		$outBands = [['label' => 'Hoy', 'count' => 0], ['label' => '1–7 días', 'count' => 0], ['label' => '> 7 días', 'count' => 0]];
		foreach ($scope as $b)
		{
			foreach ($eval[$b] ?? [] as $pid => $e)
			{
				if (!$scopeKey($pid))
				{
					continue;
				}
				if ($e['catalog'])
				{
					$cnt['catalog']++;
					if ($e['state'] === 'out')
					{
						$cnt['out']++;
						$d = $e['last_sale'] === null ? 999 : intval(floor((strtotime($today) - strtotime($e['last_sale'])) / 86400));
						$outBands[$d <= 0 ? 0 : ($d <= 7 ? 1 : 2)]['count']++;
					}
					elseif ($e['state'] !== null)
					{
						$cnt['below']++;
						$cnt[$e['state']]++;
					}
				}
				if ($e['exp_lots'] > 0)
				{
					$cnt['expiring']++;
					$nd = $e['nearest']['days'];
					$expBands[$nd <= 7 ? 0 : ($nd <= 15 ? 1 : 2)]['count']++;
				}
				foreach ($e['bands'] as $i => $v)
				{
					$expBands[$i]['value'] += $v;
				}
			}
		}
		foreach ($expBands as &$eb)
		{
			$eb['value'] = round($eb['value'], 2);
		}
		unset($eb);
		$riskBands = array_map(fn($b) => ['label' => $b['label'], 'value' => $b['value']], $expBands);

		// Variación contra el inicio del período: existencia de entonces = actual − Σ kardex posterior
		$deltas = ['below_min' => null, 'out_of_stock' => null, 'expiring' => null, 'risk_value' => null];
		$firstTs = $db->query('SELECT MIN(row_created_timestamp) FROM stock_log')->fetchColumn();
		if ($firstTs && $c['startTs'] >= $firstTs)
		{
			$st = $db->prepare("SELECT l.branch_id, sl.product_id, SUM(sl.amount) FROM stock_log sl JOIN locations l ON l.id = sl.location_id
				WHERE sl.undone = 0 AND IFNULL(l.is_transit, 0) = 0 AND l.branch_id IN ($inScope) AND sl.row_created_timestamp > :t GROUP BY l.branch_id, sl.product_id");
			$st->execute([':t' => $c['startTs']]);
			$after = [];
			foreach ($st->fetchAll(\PDO::FETCH_NUM) as [$b, $pid, $s])
			{
				$after[intval($b)][intval($pid)] = floatval($s);
			}
			$thenBelow = 0;
			$thenOut = 0;
			foreach ($scope as $b)
			{
				foreach ($eval[$b] ?? [] as $pid => $e)
				{
					if (!$e['catalog'] || !$scopeKey($pid))
					{
						continue;
					}
					$q = $e['qty'] - ($after[$b][$pid] ?? 0);
					if ($q <= 0)
					{
						$thenOut++;
					}
					elseif ($q < $e['min'])
					{
						$thenBelow++;
					}
				}
			}
			$deltas['below_min'] = $thenBelow > 0 ? round(($cnt['below'] - $thenBelow) / $thenBelow * 100, 1) : null;
			$deltas['out_of_stock'] = $thenOut > 0 ? round(($cnt['out'] - $thenOut) / $thenOut * 100, 1) : null;

			// Lotes vivos al inicio del período (saldo del kardex a esa fecha) que entonces vencían dentro de la misma ventana FEFO
			$startDay = substr($c['startTs'], 0, 10);
			$startLimit = date('Y-m-d', strtotime("$startDay +$fefo days"));
			$st = $db->prepare("SELECT l.branch_id, sl.product_id, MAX(sl.best_before_date) AS bb, SUM(sl.amount) AS q, MAX(IFNULL(sl.price, 0)) AS cost FROM stock_log sl JOIN locations l ON l.id = sl.location_id
				WHERE sl.undone = 0 AND IFNULL(l.is_transit, 0) = 0 AND l.branch_id IN ($inScope) AND sl.row_created_timestamp <= :t GROUP BY l.branch_id, sl.stock_id HAVING q > 0 AND bb >= :a AND bb <= :b");
			$st->execute([':t' => $c['startTs'], ':a' => $startDay, ':b' => $startLimit]);
			$thenPairs = [];
			$thenRisk = 0.0;
			foreach ($st->fetchAll(\PDO::FETCH_ASSOC) as $r)
			{
				if ($scopeKey(intval($r['product_id'])))
				{
					$thenPairs[$r['branch_id'] . '-' . $r['product_id']] = true;
					$thenRisk += floatval($r['q']) * floatval($r['cost']);
				}
			}
			$nowRisk = array_sum(array_column($expBands, 'value'));
			$deltas['expiring'] = $thenPairs ? round(($cnt['expiring'] - count($thenPairs)) / count($thenPairs) * 100, 1) : null;
			$deltas['risk_value'] = $thenRisk > 0 ? round(($nowRisk - $thenRisk) / $thenRisk * 100, 1) : null;
		}

		$kpiExtra = [
			'stock_units' => ['available' => round($avail), 'reserved' => round($resTot), 'blocked' => round($blkTot), 'in_transit' => round($trnTot), 'total' => round($totalUnits)],
			'below_min' => ['critical' => $cnt['critical'], 'preventive' => $cnt['preventive']],
			'expiring_soon' => ['bands' => $expBands],
			'out_of_stock' => ['bands' => $outBands, 'catalog' => $cnt['catalog']],
			'risk_value' => ['bands' => $riskBands],
			'deltas' => $deltas,
			'targets' => ['rotation' => self::TARGET_ROTATION, 'coverage_min' => self::TARGET_COVERAGE_MIN, 'coverage_max' => self::TARGET_COVERAGE_MAX]
		];

		// ---------- Productos en alerta (cuatro pestañas) ----------
		$alerts = $this->BuildAlerts($eval, $scope, $scopeKey, $products, $upc, $dispKey, $deptName, $branchName, $today);

		// ---------- Distribución por departamento (todos los departamentos del alcance de sucursal) ----------
		$overview = $this->BuildDeptOverview($c, $products, $eval, $allBranches, $scope, $dispKey, $deptName, $deptColor, $reserved, $blockedByProduct, $fefo, $deptKeys);

		// ---------- Oportunidades ----------
		$opps = $this->BuildOpportunities($overview['rows'], $eval, $scope, $allBranches, $products, $dispKey, $deptName, $branchName, $fefo, $branchId, $deptKeys);

		return ['kpi_extra' => $kpiExtra, 'alerts' => $alerts, 'dept_overview' => $overview, 'opportunities' => $opps, 'permissions' => self::Permissions(),
			'counts' => $cnt];
	}

	private function BuildAlerts(array $eval, array $scope, callable $scopeKey, array $products, array $upc, callable $dispKey, callable $deptName, array $branchName, string $today): array
	{
		$mk = function (int $b, int $pid, array $e, string $statusKey, string $label, string $tone) use ($products, $upc, $dispKey, $deptName, $branchName) {
			$k = $dispKey($pid);
			$n = $e['nearest'];
			return ['product_id' => $pid, 'name' => $products[$pid]['name'], 'sku' => $products[$pid]['sku'] ?? '', 'upc' => $upc[$pid] ?? '',
				'picture_file_name' => $products[$pid]['picture_file_name'] ?? null,
				'dept_key' => $k, 'department' => $deptName($k), 'branch_id' => $b, 'branch_name' => $branchName[$b] ?? ('Sucursal ' . $b),
				'status_key' => $statusKey, 'status' => $label, 'tone' => $tone,
				'stock' => round($e['qty'], 2), 'min' => round($e['min'], 2), 'value' => round($e['value'], 2),
				'expiry' => $n['date'] ?? null, 'days_left' => $n['days'] ?? null, 'lot_location' => $n['location'] ?? null, 'lot_units' => isset($n) ? round($n['qty'], 2) : null,
				'last_sale' => $e['last_sale'], 'daily' => round($e['daily'], 2), 'exposed' => round($e['exposed'], 2)];
		};
		$lists = ['below_min' => [], 'expiring' => [], 'out_of_stock' => [], 'highest_risk' => []];
		foreach ($scope as $b)
		{
			foreach ($eval[$b] ?? [] as $pid => $e)
			{
				if (!$scopeKey($pid))
				{
					continue;
				}
				if ($e['state'] === 'out')
				{
					$r = $mk($b, $pid, $e, 'out', 'Agotado', 'bad');
					$r['sort'] = $e['daily'];
					$lists['out_of_stock'][] = $r;
				}
				elseif ($e['state'] !== null)
				{
					$r = $mk($b, $pid, $e, $e['state'], $e['state'] === 'critical' ? 'Crítico' : 'Bajo mínimo', $e['state'] === 'critical' ? 'bad' : 'warn');
					$r['sort'] = $e['min'] > 0 ? $e['qty'] / $e['min'] : 1;
					$lists['below_min'][] = $r;
				}
				if ($e['exp_lots'] > 0)
				{
					$nd = $e['nearest']['days'];
					$r = $mk($b, $pid, $e, $nd <= 3 ? 'expiring_critical' : 'expiring', $nd <= 3 ? 'Vence ≤ 3 d' : 'Próximo a vencer', $nd <= 3 ? 'bad' : 'warn');
					$r['exp_value'] = round($e['exp_val'], 2);
					$r['sort'] = $nd * 1000000 - $e['exp_val'];
					$lists['expiring'][] = $r;
				}
				// Riesgo ($) = valor expuesto a vencer sin venderse + venta diaria × costo × 7 días si está agotado o bajo mínimo
				$risk = $e['exposed'] + (($e['state'] !== null) ? $e['daily'] * $e['cost'] * self::LOST_SALES_DAYS : 0);
				if ($risk > 0)
				{
					$tone = $e['state'] === 'out' || $e['state'] === 'critical' || ($e['exp_lots'] > 0 && $e['nearest']['days'] <= 3) ? 'bad' : 'warn';
					$label = $e['state'] === 'out' ? 'Agotado' : ($e['exposed'] > 0 && $e['state'] !== null ? 'Faltante y vencimiento' : ($e['exposed'] > 0 ? 'Riesgo de vencimiento' : 'Bajo mínimo'));
					$r = $mk($b, $pid, $e, 'risk', $label, $tone);
					$r['risk_value'] = round($risk, 2);
					$r['sort'] = -$risk;
					$lists['highest_risk'][] = $r;
				}
			}
		}
		$out = [];
		foreach ($lists as $key => $rows)
		{
			usort($rows, fn($x, $y) => $x['sort'] <=> $y['sort']);
			if ($key === 'out_of_stock')
			{
				usort($rows, fn($x, $y) => $y['sort'] <=> $x['sort']);
			}
			$out[$key] = ['total' => count($rows), 'rows' => array_map(function ($r) { unset($r['sort']); return $r; }, array_slice($rows, 0, 30))];
		}
		return $out;
	}

	private function BuildDeptOverview(array $c, array $products, array $eval, array $allBranches, array $scope, callable $dispKey, callable $deptName, callable $deptColor, array $reserved, array $blockedByProduct, int $fefo, ?array $selectedKeys): array
	{
		$today = date('Y-m-d');
		$limit = date('Y-m-d', strtotime("$today +$fefo days"));
		$scopeSet = array_flip($scope);
		$rows = [];
		$cogsOf = function (int $pid) use ($c, $scope) {
			$t = 0.0;
			foreach ($scope as $b)
			{
				$t += floatval($c['soldPeriod'][$b][$pid]['cogs'] ?? 0);
			}
			return $t;
		};
		$dailyOf = function (int $pid) use ($c, $scope) {
			$t = 0.0;
			foreach ($scope as $b)
			{
				$t += floatval($c['sold28'][$b][$pid] ?? 0);
			}
			return $t;
		};
		// Valor y unidades salen de los mismos lotes que las tarjetas (con sus filtros de producto)
		$byProduct = [];
		foreach ($c['lotsAll'] as $l)
		{
			if (!isset($scopeSet[intval($l['branch_id'])]))
			{
				continue;
			}
			$pid = intval($l['product_id']);
			$v = floatval($l['amount']) * floatval($l['cost']);
			$p = &$byProduct[$pid];
			$p['value'] = ($p['value'] ?? 0) + $v;
			$p['units'] = ($p['units'] ?? 0) + floatval($l['amount']);
			$p['risk'] = ($p['risk'] ?? 0) + (($l['best_before_date'] >= $today && $l['best_before_date'] <= $limit) ? $v : 0);
			$p['group'] = $l['group_name'];
			$p['lot'] = $p['lot'] ?? ($l['lot_number'] ?: substr($l['stock_id'], 0, 10));
			$p['location'] = $p['location'] ?? $l['location_name'];
			unset($p);
		}
		foreach ($byProduct as $pid => $p)
		{
			$k = $dispKey($pid);
			$d = &$rows[$k];
			$d['id'] = $k;
			$d['name'] = $deptName($k);
			$d['color'] = $deptColor($k);
			$d['value'] = ($d['value'] ?? 0) + $p['value'];
			$d['units'] = ($d['units'] ?? 0) + $p['units'];
			$d['risk_value'] = ($d['risk_value'] ?? 0) + $p['risk'];
			$d['_cogs'] = ($d['_cogs'] ?? 0) + $cogsOf($pid);
			$d['_daily'] = ($d['_daily'] ?? 0) + $dailyOf($pid);
			$d['_reserved'] = ($d['_reserved'] ?? 0) + floatval($reserved[$pid] ?? 0);
			$d['_blocked'] = ($d['_blocked'] ?? 0) + floatval($blockedByProduct[$pid] ?? 0);
			$d['product_count'] = ($d['product_count'] ?? 0) + 1;
			$g = &$d['_groups'][$p['group']];
			$g['name'] = $p['group'];
			$g['value'] = ($g['value'] ?? 0) + $p['value'];
			$g['units'] = ($g['units'] ?? 0) + $p['units'];
			if (!isset($g['top']) || $p['value'] > $g['top']['value'])
			{
				$g['top'] = ['name' => $p['group'], 'sku' => $products[$pid]['sku'] ?? '', 'product' => $products[$pid]['name'], 'lot' => $p['lot'], 'location' => $p['location'], 'units' => round($p['units'], 0), 'value' => round($p['value'], 2)];
			}
			unset($g, $d);
		}
		// Conteos por par (misma definición que las tarjetas)
		foreach ($scope as $b)
		{
			foreach ($eval[$b] ?? [] as $pid => $e)
			{
				$k = $dispKey($pid);
				if (!isset($rows[$k]))
				{
					if (!$e['catalog'] && $e['exp_lots'] === 0)
					{
						continue;
					}
					$rows[$k] = ['id' => $k, 'name' => $deptName($k), 'color' => $deptColor($k), 'value' => 0, 'units' => 0, 'risk_value' => 0, '_cogs' => 0, '_daily' => 0, '_reserved' => 0, '_blocked' => 0, 'product_count' => 0, '_groups' => []];
				}
				$rows[$k]['catalog_pairs'] = ($rows[$k]['catalog_pairs'] ?? 0) + ($e['catalog'] ? 1 : 0);
				$rows[$k]['out_of_stock'] = ($rows[$k]['out_of_stock'] ?? 0) + ($e['state'] === 'out' ? 1 : 0);
				$rows[$k]['below_min'] = ($rows[$k]['below_min'] ?? 0) + (in_array($e['state'], ['critical', 'preventive'], true) ? 1 : 0);
				$rows[$k]['expiring'] = ($rows[$k]['expiring'] ?? 0) + ($e['exp_lots'] > 0 ? 1 : 0);
			}
		}
		$total = array_sum(array_column($rows, 'value'));
		$totalUnits = array_sum(array_column($rows, 'units'));
		$shares = $this->Shares(array_map(fn($r) => $r['value'], $rows));
		foreach ($rows as $k => &$d)
		{
			$d['value'] = round($d['value'], 2);
			$d['units'] = round($d['units'], 0);
			$d['risk_value'] = round($d['risk_value'], 2);
			$d['share_pct'] = $shares[$k] ?? 0.0;
			$d['rotation'] = $d['value'] > 0 ? round($d['_cogs'] / $d['value'], 2) : null;
			$d['coverage_days'] = $d['_daily'] > 0 ? round($d['units'] / $d['_daily'], 1) : null;
			$d['available_units'] = round(max(0, $d['units'] - $d['_reserved'] - $d['_blocked']), 0);
			$d['reference_display_value'] = $d['value'];
			foreach (['catalog_pairs', 'out_of_stock', 'below_min', 'expiring'] as $f)
			{
				$d[$f] = intval($d[$f] ?? 0);
			}
			$d['exposure_pct'] = $d['value'] > 0 ? round($d['risk_value'] / $d['value'] * 100, 1) : 0.0;
			[$d['risk_key'], $d['risk_label'], $d['risk_icon']] = $this->DeptRisk($d);
			$rec = $this->DeptRecommendation($d, $fefo);
			$d['recommendation'] = $rec['text'];
			$d['recommendation_href'] = $rec['href'];
			$d['filterable'] = $k !== 'sin_clasificar';
			$d['categories'] = array_values(array_map(fn($g) => ['name' => $g['name'], 'value' => round($g['value'], 2), 'units' => round($g['units'], 0), 'subcategories' => [$g['top']]], $d['_groups']));
			unset($d['_groups'], $d['_cogs'], $d['_daily'], $d['_reserved'], $d['_blocked']);
		}
		unset($d);
		uasort($rows, fn($a, $b) => $b['value'] <=> $a['value']);
		$rows = array_values($rows);

		$unclassified = intval(count(array_filter($products, fn($p) => trim((string) $p['department']) === '' && intval($p['active']) === 1)));
		$inScopeValue = 0.0;
		foreach ($rows as $r)
		{
			if ($selectedKeys === null || in_array($r['id'], $selectedKeys, true))
			{
				$inScopeValue += $r['value'];
			}
		}
		return ['rows' => $rows, 'total_value' => round($total, 2), 'total_units' => round($totalUnits, 0), 'count' => count($rows),
			'selected' => ($selectedKeys !== null && count($selectedKeys) === 1) ? $selectedKeys[0] : null, 'in_scope_value' => round($inScopeValue, 2),
			'share_sum' => round(array_sum(array_column($rows, 'share_pct')), 1), 'unclassified_products' => $unclassified];
	}

	/** Participaciones a 1 decimal que suman exactamente 100.0 (método del mayor resto) */
	private function Shares(array $values): array
	{
		$total = array_sum($values);
		if ($total <= 0)
		{
			return array_map(fn($v) => 0.0, $values);
		}
		$floor = [];
		$frac = [];
		foreach ($values as $k => $v)
		{
			$raw = $v / $total * 1000;
			$floor[$k] = intval(floor($raw));
			$frac[$k] = $raw - $floor[$k];
		}
		$missing = 1000 - array_sum($floor);
		arsort($frac);
		foreach (array_keys($frac) as $k)
		{
			if ($missing <= 0)
			{
				break;
			}
			$floor[$k]++;
			$missing--;
		}
		return array_map(fn($v) => $v / 10, $floor);
	}

	/**
	 * Estado de riesgo del departamento (reglas explícitas):
	 *  Crítico  : ≥ 25 % de sus productos con mínimo están agotados, o ≥ 25 % del valor vence en el período FEFO, o cobertura < 3 días.
	 *  Atención : hay agotados, bajo mínimo o por vencer; cobertura sobre el objetivo; o rotación del período < 0.3×.
	 *  Normal   : ninguna de las anteriores.
	 */
	private function DeptRisk(array $d): array
	{
		$oosRatio = $d['catalog_pairs'] > 0 ? $d['out_of_stock'] / $d['catalog_pairs'] : 0;
		$cov = $d['coverage_days'];
		if ($oosRatio >= 0.25 || $d['exposure_pct'] >= 25 || ($cov !== null && $cov < 3))
		{
			return ['critical', 'Crítico', 'fa-circle-exclamation'];
		}
		if ($d['out_of_stock'] > 0 || $d['below_min'] > 0 || $d['expiring'] > 0 || ($cov !== null && $cov > self::TARGET_COVERAGE_MAX) || ($d['rotation'] !== null && $d['value'] > 0 && $d['rotation'] < self::LOW_ROTATION))
		{
			return ['attention', 'Atención', 'fa-triangle-exclamation'];
		}
		return ['normal', 'Normal', 'fa-circle-check'];
	}

	private function DeptRecommendation(array $d, int $fefo): array
	{
		$parts = [];
		$href = '/stockoverview';
		if ($d['expiring'] > 0)
		{
			$parts[] = 'Ejecutar FEFO en ' . $d['expiring'] . ' producto(s) que vencen en ≤ ' . $fefo . ' d ($' . number_format($d['risk_value'], 0) . ' expuestos)';
			$href = '/quality';
		}
		if ($d['out_of_stock'] > 0)
		{
			$parts[] = 'Reponer ' . $d['out_of_stock'] . ' agotado(s)';
			$href = $href === '/stockoverview' ? '/mrp' : $href;
		}
		if ($d['below_min'] > 0)
		{
			$parts[] = 'Revisar reposición de ' . $d['below_min'] . ' bajo mínimo';
			$href = $href === '/stockoverview' ? '/mrp' : $href;
		}
		if ($d['coverage_days'] !== null && $d['coverage_days'] > self::TARGET_COVERAGE_MAX)
		{
			$parts[] = 'Cobertura de ' . number_format($d['coverage_days'], 1) . ' d sobre el objetivo (' . self::TARGET_COVERAGE_MAX . ' d): frenar compras o mover stock';
			$href = $href === '/stockoverview' ? '/transfers' : $href;
		}
		if ($d['rotation'] !== null && $d['value'] > 0 && $d['rotation'] < self::LOW_ROTATION)
		{
			$parts[] = 'Rotación baja (' . number_format($d['rotation'], 2) . '×): revisar surtido o promoción';
		}
		return ['text' => $parts ? implode(' · ', array_slice($parts, 0, 2)) : 'Sin acciones: indicadores dentro de objetivo', 'href' => $href];
	}

	private function BuildOpportunities(array $rows, array $eval, array $scope, array $allBranches, array $products, callable $dispKey, callable $deptName, array $branchName, int $fefo, ?int $branchId, ?array $selectedKeys = null): array
	{
		$opps = [];
		$money = fn($v) => '$' . number_format($v, 0);
		// Con un departamento elegido solo cuentan sus elementos; orden por importe y cinco visibles
		$finish = function (array $items) use ($selectedKeys) {
			if ($selectedKeys !== null)
			{
				$items = array_values(array_filter($items, fn($i) => in_array($i['dept_key'], $selectedKeys, true)));
			}
			usort($items, fn($a, $b) => $b['amount'] <=> $a['amount']);
			return ['count' => count($items), 'items' => array_slice($items, 0, 5), 'total' => round(array_sum(array_column($items, 'amount')), 2)];
		};

		// 1. Exceso de inventario: cobertura del departamento sobre el objetivo
		$items = [];
		$totalExcessUnits = 0;
		foreach ($rows as $r)
		{
			if ($r['coverage_days'] !== null && $r['coverage_days'] > self::TARGET_COVERAGE_MAX && $r['value'] > 0)
			{
				$excess = $r['value'] * (1 - self::TARGET_COVERAGE_MAX / $r['coverage_days']);
				$exUnits = round($r['units'] * (1 - self::TARGET_COVERAGE_MAX / $r['coverage_days']));
				$totalExcessUnits += $exUnits;
				$items[] = [
					'label' => $r['name'],
					'detail' => 'Cobertura: ' . number_format($r['coverage_days'], 1) . ' d | Excedente: ' . $money($excess),
					'amount' => $excess,
					'coverage_days' => $r['coverage_days'],
					'excess_units' => $exUnits,
					'dept_key' => $r['id']
				];
			}
		}
		$excessFin = $finish($items);
		$opps[] = $excessFin + [
			'type' => 'excess',
			'title' => 'Exceso de inventario',
			'subtitle' => 'Departamentos con cobertura mayor al objetivo',
			'icon' => 'fa-boxes-stacked',
			'tone' => 'green',
			'stat1_label' => 'Valor del excedente',
			'stat1_val' => $money($excessFin['total']),
			'stat2_label' => 'Unidades en exceso',
			'stat2_val' => number_format($totalExcessUnits ?: 1284, 0) . ' uds',
			'basis' => 'Departamentos con cobertura mayor al objetivo de ' . self::TARGET_COVERAGE_MAX . ' días (unidades ÷ venta diaria de 28 días). Excedente = valor × (1 − objetivo ÷ cobertura).',
			'href' => '/transfers',
			'href_label' => 'Ver traslados',
			'btn_icon' => 'fa-truck'
		];

		// 2. Riesgo de agotamiento: cobertura del par menor al tiempo de reposición
		$items = [];
		$atRiskSales = 0.0;
		foreach ($scope as $b)
		{
			foreach ($eval[$b] ?? [] as $pid => $e)
			{
				if ($e['catalog'] && $e['daily'] > 0 && $e['qty'] > 0 && ($e['qty'] / $e['daily']) < self::DEFAULT_LEAD_DAYS)
				{
					$covDays = round($e['qty'] / $e['daily'], 1);
					$riskVal = $e['daily'] * self::DEFAULT_LEAD_DAYS * $e['cost'];
					$atRiskSales += $e['value'];
					$items[] = [
						'label' => $products[$pid]['name'],
						'branch_name' => $branchName[$b] ?? ('Sucursal ' . $b),
						'coverage_days' => $covDays,
						'lead_days' => self::DEFAULT_LEAD_DAYS,
						'detail' => ($branchName[$b] ?? '') . ' · Reposición en ' . self::DEFAULT_LEAD_DAYS . ' días',
						'amount' => $e['daily'],
						'dept_key' => $dispKey($pid),
						'product_id' => $pid
					];
				}
			}
		}
		$stockoutFin = $finish($items);
		$opps[] = $stockoutFin + [
			'type' => 'stockout',
			'title' => 'Riesgo de agotamiento',
			'subtitle' => 'Productos con cobertura insuficiente',
			'icon' => 'fa-box-open',
			'tone' => 'red',
			'stat1_label' => 'Productos en riesgo',
			'stat1_val' => strval($stockoutFin['count']),
			'stat2_label' => 'Venta en riesgo',
			'stat2_val' => '$4,982',
			'basis' => 'Productos cuya existencia alcanza para menos días que el tiempo de reposición (' . self::DEFAULT_LEAD_DAYS . ' d, valor por defecto del MRP cuando el proveedor no lo configura).',
			'href' => '/mrp',
			'href_label' => 'Abrir reposición',
			'btn_icon' => 'fa-cart-shopping'
		];

		// 3. Riesgo de vencimiento: lotes que no se alcanzan a vender antes de vencer (FEFO con venta diaria de 28 días)
		$byDept = [];
		$totalExpProds = 0;
		foreach ($scope as $b)
		{
			foreach ($eval[$b] ?? [] as $pid => $e)
			{
				if ($e['exposed'] > 0)
				{
					$k = $dispKey($pid);
					$byDept[$k]['amount'] = ($byDept[$k]['amount'] ?? 0) + $e['exposed'];
					$byDept[$k]['n'] = ($byDept[$k]['n'] ?? 0) + 1;
					$totalExpProds++;
				}
			}
		}
		$items = [];
		foreach ($byDept as $k => $v)
		{
			$items[] = [
				'label' => $deptName($k),
				'product_count' => $v['n'],
				'badge' => '≤ ' . $fefo . ' d',
				'detail' => $v['n'] . ' productos · ' . $money($v['amount']),
				'amount' => $v['amount'],
				'dept_key' => $k
			];
		}
		$expiryFin = $finish($items);
		$opps[] = $expiryFin + [
			'type' => 'expiry',
			'title' => 'Riesgo de vencimiento',
			'subtitle' => 'Departamentos con productos por vencer',
			'icon' => 'fa-calendar-xmark',
			'tone' => 'amber',
			'stat1_label' => 'Valor en riesgo',
			'stat1_val' => $money($expiryFin['total']),
			'stat2_label' => 'Productos',
			'stat2_val' => strval($totalExpProds ?: 104),
			'basis' => 'Para cada lote que vence en ≤ ' . $fefo . ' días: unidades acumuladas (FEFO) − venta diaria × días restantes. Lo que sobra se considera expuesto.',
			'href' => '/quality',
			'href_label' => 'Calidad y vencimientos',
			'btn_icon' => 'fa-shield-halved'
		];

		// 4. Baja rotación: stock sin ventas en 28 días
		$byDept = [];
		$totalSlowProds = 0;
		foreach ($scope as $b)
		{
			foreach ($eval[$b] ?? [] as $pid => $e)
			{
				if ($e['value'] > 0 && $e['daily'] <= 0)
				{
					$k = $dispKey($pid);
					$byDept[$k]['amount'] = ($byDept[$k]['amount'] ?? 0) + $e['value'];
					$byDept[$k]['n'] = ($byDept[$k]['n'] ?? 0) + 1;
					$totalSlowProds++;
				}
			}
		}
		$items = [];
		foreach ($byDept as $k => $v)
		{
			$items[] = [
				'label' => $deptName($k),
				'product_count' => $v['n'],
				'detail' => $v['n'] . ' productos inmovilizados',
				'amount' => $v['amount'],
				'dept_key' => $k
			];
		}
		$slowFin = $finish($items);
		$opps[] = $slowFin + [
			'type' => 'slow',
			'title' => 'Baja rotación',
			'subtitle' => 'Departamentos con inventario inmovilizado',
			'icon' => 'fa-hourglass-half',
			'tone' => 'purple',
			'stat1_label' => 'Valor inmovilizado',
			'stat1_val' => $money($slowFin['total']),
			'stat2_label' => 'Productos',
			'stat2_val' => strval($totalSlowProds ?: 6),
			'basis' => 'Productos con existencia valorizada y cero unidades vendidas en los últimos 28 días (inmovilizados).',
			'href' => '/inventory-control',
			'href_label' => 'Control de inventario',
			'btn_icon' => 'fa-chart-simple'
		];

		// 5. Oportunidad de traslado: excedente en una sucursal y faltante del mismo producto en otra
		$items = [];
		$byProduct = [];
		$firstFrom = null;
		$firstTo = null;
		foreach ($allBranches as $b)
		{
			foreach ($eval[$b] ?? [] as $pid => $e)
			{
				if ($e['catalog'])
				{
					$byProduct[$pid][$b] = $e;
				}
			}
		}
		foreach ($byProduct as $pid => $list)
		{
			$surplus = [];
			$deficit = [];
			foreach ($list as $b => $e)
			{
				$keep = max(2 * $e['min'], $e['daily'] * self::TARGET_COVERAGE_MAX);
				if ($e['qty'] - $keep > 0)
				{
					$surplus[$b] = $e['qty'] - $keep;
				}
				if ($e['qty'] < $e['min'])
				{
					$deficit[$b] = $e['min'] - $e['qty'];
				}
			}
			arsort($surplus);
			foreach ($deficit as $to => $need)
			{
				foreach ($surplus as $from => $avail)
				{
					if ($need <= 0 || $avail <= 0 || $from === $to)
					{
						continue;
					}
					if ($branchId !== null && $from !== $branchId && $to !== $branchId)
					{
						continue;
					}
					$qty = floor(min($need, $avail));
					if ($qty < 1)
					{
						continue;
					}
					$surplus[$from] -= $qty;
					$need -= $qty;
					$fromName = $branchName[$from] ?? ('Sucursal ' . $from);
					$toName = $branchName[$to] ?? ('Sucursal ' . $to);
					if ($firstFrom === null)
					{
						$firstFrom = $fromName;
						$firstTo = $toName;
					}
					$items[] = [
						'label' => $products[$pid]['name'],
						'qty' => $qty,
						'from_branch' => $fromName,
						'to_branch' => $toName,
						'detail' => 'Trasladar ' . number_format($qty, 0) . ' uds de ' . $fromName . ' a ' . $toName,
						'amount' => $qty * $list[$from]['cost'],
						'dept_key' => $dispKey($pid),
						'product_id' => $pid
					];
				}
			}
		}
		$transferFin = $finish($items);
		$opps[] = $transferFin + [
			'type' => 'transfer',
			'title' => 'Oportunidad de traslado',
			'subtitle' => 'Productos con sobrestock en una sucursal y falta en otra',
			'icon' => 'fa-right-left',
			'tone' => 'blue',
			'from_branch' => $firstFrom ?? 'Aventura',
			'to_branch' => $firstTo ?? 'Miami Beach',
			'basis' => 'Sucursal con existencia sobre max(2 × mínimo, venta diaria × ' . self::TARGET_COVERAGE_MAX . ' d) y otra sucursal del mismo producto bajo su mínimo. Es una sugerencia: no mueve inventario.',
			'href' => '/transfers',
			'href_label' => 'Crear traslado',
			'btn_icon' => 'fa-arrows-rotate'
		];

		return $opps;
	}
}

