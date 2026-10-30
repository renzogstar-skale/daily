<?php

namespace Grocy\Services;

use Grocy\Helpers\DairyAccess;

/**
 * DAILY — Servicio Ejecutivo de Stock & Movimientos (/stockoverview)
 *
 * Fuente unificada para el panel ejecutivo de inventario:
 * - Conciliación matemática estricta entre KPIs, Dona por departamento, Evolución y Sucursales.
 * - Integración con tablas reales SQLite (products, stock, stock_log, sales, waste_records,
 *   stock_cycle_counts, sensitive_investigation_cases, locations, branches, shopping_locations)
 *   y datos demostrativos empresariales claramente identificados cuando se visualiza la Red Consolidada.
 * - Flujo operativo transversal de 10 pasos con trazabilidad y auditoría.
 */
class StockExecutiveOverviewService extends BaseService
{
	private static ?StockExecutiveOverviewService $instance = null;

	public static function GetInstance(): StockExecutiveOverviewService
	{
		if (self::$instance === null)
		{
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function Db(): \PDO
	{
		return DatabaseService::GetInstance()->GetDbConnectionRaw();
	}

	// Las sucursales del escenario calibrado se nombran desde el catálogo único (marcadores [[B<id>]])
	public function GetExecutiveData(array $filters = []): array
	{
		$data = BranchService::FillBranchPlaceholders($this->BuildExecutiveDataRaw($filters));
		$branchId = intval($filters['branch_id'] ?? 0);
		if ($branchId > 0 && !in_array($branchId, array_map('intval', array_column($data['branches_table'] ?? [], 'id')), true))
		{
			// Distinguir ausencia de datos de un cero real
			$data['filters']['no_data'] = true;
			foreach (($data['kpis'] ?? []) as $key => $kpi)
			{
				if (is_array($kpi) && array_key_exists('value', $kpi))
				{
					$data['kpis'][$key]['value'] = 'Sin información';
					$data['kpis'][$key]['delta'] = '';
					$data['kpis'][$key]['subtext'] = BranchService::NameById($branchId) . ' sin datos en este panel';
				}
			}
		}
		return $data;
	}

	public function GetDetailedMovementsLedger(?int $branchId = null, string $movementType = 'todos', ?array $deptKeys = null): array
	{
		return BranchService::FillBranchPlaceholders($this->GetDetailedMovementsLedgerRaw($branchId, $movementType, $deptKeys));
	}

	public function GetProduct360Sheet(int $productId): array
	{
		return BranchService::FillBranchPlaceholders($this->GetProduct360SheetRaw($productId));
	}

	/**
	 * Panel ejecutivo de stock con los registros reales: existencias por lote (stock), kardex (stock_log) y ventas.
	 * Definiciones compartidas con HOY y el Panel ejecutivo (DailyMetricsService): valor = Σ cantidad × costo del lote,
	 * bajo mínimo / agotado por par producto-sucursal, por vencer = lotes que vencen en ≤ N días.
	 * Rotación del período = costo de ventas del período ÷ valor actual del inventario.
	 * Cobertura = unidades en stock ÷ unidades vendidas por día (promedio de los últimos 28 días).
	 */
	private function BuildExecutiveDataRaw(array $filters = []): array
	{
		$db = $this->Db();
		$this->EnsureAuditTable();
		$m = DailyMetricsService::GetInstance();

		$date = !empty($filters['date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', strval($filters['date'])) ? strval($filters['date']) : date('Y-m-d');
		$period = !empty($filters['period']) ? strval($filters['period']) : 'month';
		$branchId = isset($filters['branch_id']) && $filters['branch_id'] !== '' && $filters['branch_id'] !== 'all' ? intval($filters['branch_id']) : null;
		$department = !empty($filters['department']) && $filters['department'] !== 'todos' && $filters['department'] !== 'all' ? strval($filters['department']) : 'todos';
		$category = !empty($filters['category']) && $filters['category'] !== 'todas' && $filters['category'] !== 'all' ? strval($filters['category']) : 'todas';
		$supplier = !empty($filters['supplier']) && $filters['supplier'] !== 'todos' && $filters['supplier'] !== 'all' ? strval($filters['supplier']) : 'todos';
		$skuQuery = !empty($filters['sku']) ? trim(strval($filters['sku'])) : '';
		$invStatus = !empty($filters['status']) && $filters['status'] !== 'todos' && $filters['status'] !== 'all' ? strval($filters['status']) : 'todos';
		$location = !empty($filters['location']) && $filters['location'] !== 'todas' && $filters['location'] !== 'all' ? strval($filters['location']) : 'todas';
		$movementType = !empty($filters['movement_type']) && $filters['movement_type'] !== 'todos' && $filters['movement_type'] !== 'all' ? strval($filters['movement_type']) : 'todos';
		$fefoDays = isset($filters['fefo_days']) && in_array(intval($filters['fefo_days']), [1, 3, 7, 15, 30], true) ? intval($filters['fefo_days']) : 30;

		$allowedBranches = DairyAccess::AllowedBranches();
		if ($allowedBranches !== null && ($branchId === null || !in_array($branchId, $allowedBranches, true)))
		{
			$branchId = $allowedBranches[0];
		}
		$deptKeys = $m->DepartmentFilter($department === 'todos' ? null : $department, $category === 'todas' ? null : $category);
		$periodDays = ['day' => 1, 'week' => 7, 'month' => 30, 'quarter' => 90, 'year' => 365][$period] ?? 30;
		$from = date('Y-m-d', strtotime("$date -" . ($periodDays - 1) . ' days'));

		// Lotes vigentes con los filtros de producto (departamento, categoría, proveedor, SKU, ubicación)
		// Los lotes se leen sin filtro de departamento para poder mostrar la distribución completa; el filtro se aplica más abajo
		$where = ['IFNULL(l.is_transit, 0) = 0', 's.amount > 0'];
		$params = [];
		if ($supplier !== 'todos' && ctype_digit($supplier))
		{
			$where[] = 'p.shopping_location_id = ' . intval($supplier);
		}
		if ($location !== 'todas' && ctype_digit($location))
		{
			$where[] = 'l.id = ' . intval($location);
		}
		if ($skuQuery !== '')
		{
			$where[] = '(p.name LIKE :q OR IFNULL(p.sku, \'\') LIKE :q OR IFNULL(dl.lot_number, \'\') LIKE :q)';
			$params[':q'] = '%' . $skuQuery . '%';
		}
		$st = $db->prepare('SELECT s.product_id, s.stock_id, s.amount, s.best_before_date, IFNULL(s.price, IFNULL(plp.price, 0)) AS cost, l.id AS location_id, l.name AS location_name, l.branch_id,
				p.name, p.sku, p.department, p.min_stock_amount, IFNULL(pg.name, \'Sin grupo\') AS group_name, dl.lot_number
			FROM stock s JOIN locations l ON l.id = s.location_id JOIN products p ON p.id = s.product_id LEFT JOIN product_groups pg ON pg.id = p.product_group_id
			LEFT JOIN cache__products_last_purchased plp ON plp.product_id = s.product_id LEFT JOIN dairy_stock_lots dl ON dl.stock_id = s.stock_id
			WHERE ' . implode(' AND ', $where));
		$st->execute($params);
		$lotsAll = $st->fetchAll(\PDO::FETCH_ASSOC);

		$deptOf = function (?string $dept) {
			foreach (DailyMetricsService::DEPARTMENTS as $k => $d)
			{
				if ($d[2] !== null && in_array($dept, $d[2], true))
				{
					return $k;
				}
			}
			return 'otros';
		};
		$lots = $deptKeys === null ? $lotsAll : array_values(array_filter($lotsAll, fn($l) => in_array($deptOf($l['department']), $deptKeys, true)));

		// Ventas por producto y sucursal: período elegido (costo) y 28 días (unidades por día, para la cobertura)
		$sold = $db->prepare("SELECT s.branch_id, si.product_id, SUM(si.amount) AS units, SUM(si.total_cost) AS cogs FROM sale_items si JOIN sales s ON s.id = si.sale_id
			WHERE s.status = 'completed' AND date(s.created_timestamp) BETWEEN :f AND :t GROUP BY s.branch_id, si.product_id");
		$sold->execute([':f' => $from, ':t' => $date]);
		$soldPeriod = [];
		foreach ($sold->fetchAll(\PDO::FETCH_ASSOC) as $r)
		{
			$soldPeriod[$r['branch_id']][$r['product_id']] = $r;
		}
		$sold->execute([':f' => date('Y-m-d', strtotime("$date -27 days")), ':t' => $date]);
		$sold28 = [];
		foreach ($sold->fetchAll(\PDO::FETCH_ASSOC) as $r)
		{
			$sold28[$r['branch_id']][$r['product_id']] = floatval($r['units']) / 28;
		}

		$today = date('Y-m-d');
		$fefoLimit = date('Y-m-d', strtotime("$today +$fefoDays days"));
		$branchesAll = BranchService::GetInstance()->GetBranches();
		$branchName = [];
		foreach ($branchesAll as $b)
		{
			$branchName[intval($b->id)] = $b->name;
		}

		// Agregados por sucursal y por producto
		$byBranch = [];
		$byProduct = [];
		foreach ($lots as $l)
		{
			$b = intval($l['branch_id']);
			$v = floatval($l['amount']) * floatval($l['cost']);
			$byBranch[$b]['value'] = ($byBranch[$b]['value'] ?? 0) + $v;
			$byBranch[$b]['units'] = ($byBranch[$b]['units'] ?? 0) + floatval($l['amount']);
			if ($l['best_before_date'] >= $today && $l['best_before_date'] <= $fefoLimit)
			{
				$byBranch[$b]['risk'] = ($byBranch[$b]['risk'] ?? 0) + $v;
			}
			if ($branchId === null || $b === $branchId)
			{
				$pid = intval($l['product_id']);
				$p = &$byProduct[$pid];
				$p['product_id'] = $pid;
				$p['name'] = $l['name'];
				$p['sku'] = $l['sku'] ?? '';
				$p['dept_key'] = $deptOf($l['department']);
				$p['department'] = DailyMetricsService::DEPARTMENTS[$p['dept_key']][0];
				$p['group'] = $l['group_name'];
				$p['value'] = ($p['value'] ?? 0) + $v;
				$p['units'] = ($p['units'] ?? 0) + floatval($l['amount']);
				$p['risk'] = ($p['risk'] ?? 0) + (($l['best_before_date'] >= $today && $l['best_before_date'] <= $fefoLimit) ? $v : 0);
				$p['top_lot'] = $p['top_lot'] ?? ['lot' => $l['lot_number'] ?: substr($l['stock_id'], 0, 10), 'location' => $l['location_name']];
				unset($p);
			}
		}
		$scopeBranches = $branchId !== null ? [$branchId] : array_keys($branchName);
		$soldDay = function (int $pid) use ($sold28, $scopeBranches) {
			$u = 0.0;
			foreach ($scopeBranches as $b)
			{
				$u += $sold28[$b][$pid] ?? 0;
			}
			return $u;
		};
		$cogsOf = function (int $pid) use ($soldPeriod, $scopeBranches) {
			$c = 0.0;
			foreach ($scopeBranches as $b)
			{
				$c += floatval($soldPeriod[$b][$pid]['cogs'] ?? 0);
			}
			return $c;
		};

		$counts = $m->StockStatusCounts($branchId, $fefoDays, $deptKeys);
		$totalVal = round(array_sum(array_column($byProduct, 'value')), 2);
		$totalUnits = round(array_sum(array_column($byProduct, 'units')), 0);
		$totalRisk = round(array_sum(array_column($byProduct, 'risk')), 2);
		$cogsPeriod = round(array_sum(array_map(fn($pid) => $cogsOf($pid), array_keys($byProduct))), 2);
		$dailyUnits = array_sum(array_map(fn($pid) => $soldDay($pid), array_keys($byProduct)));
		$rotation = $totalVal > 0 ? round($cogsPeriod / $totalVal, 2) : null;
		$coverage = $dailyUnits > 0 ? round($totalUnits / $dailyUnits, 1) : null;

		// Variación del valor contra el inicio del período (kardex posterior a esa fecha)
		$evolution = $this->BuildEvolutionReal($period, $date, $branchId, $totalVal, $totalUnits, $byBranch, $branchName, $deptKeys);
		$startVal = $evolution['period_start_value'];
		$valDelta = ($startVal !== null && $startVal > 0) ? round(($totalVal - $startVal) / $startVal * 100, 1) : null;
		$arrow = fn($d, $suffix = '%') => $d === null ? '' : (($d >= 0 ? '↑ +' : '↓ ') . number_format($d, 1) . $suffix);

		$kpis = [
			'inventory_value' => ['id' => 'inventory_value', 'label' => 'Valor del inventario', 'value' => '$' . number_format($totalVal, 0), 'value_reconciled' => '$' . number_format($totalVal, 2), 'numeric' => $totalVal,
				'delta' => $arrow($valDelta), 'delta_is_good' => true, 'subtext' => $valDelta === null ? 'Sin dato al inicio del período' : 'vs. inicio del período', 'state' => 'green', 'icon' => 'fa-boxes-stacked',
				'formula' => 'Σ (unidades por lote × costo de ingreso del lote), ubicaciones de la sucursal sin tránsito. Misma cifra que el Panel ejecutivo.', 'sparkline_points' => $evolution['spark_value']],
			'stock_units' => ['id' => 'stock_units', 'label' => 'Unidades en stock', 'value' => number_format($totalUnits, 0), 'numeric' => $totalUnits, 'delta' => '', 'delta_is_good' => true,
				'subtext' => count($byProduct) . ' productos con existencias', 'state' => 'green', 'icon' => 'fa-layer-group', 'formula' => 'Σ unidades físicas en ubicaciones activas de la sucursal.', 'sparkline_points' => $evolution['spark_units']],
			'turnover' => ['id' => 'turnover', 'label' => 'Rotación de inventario', 'value' => $rotation === null ? 'Sin información' : number_format($rotation, 2), 'numeric' => $rotation, 'delta' => '', 'delta_is_good' => true,
				'subtext' => 'costo de ventas de ' . $periodDays . ' día(s) ÷ inventario', 'state' => 'green', 'icon' => 'fa-cart-shopping',
				'formula' => 'Rotación = costo de ventas del período ($' . number_format($cogsPeriod, 0) . ') ÷ valor actual del inventario ($' . number_format($totalVal, 0) . ').', 'sparkline_points' => $evolution['spark_rotation']],
			'coverage' => ['id' => 'coverage', 'label' => 'Cobertura promedio', 'value' => $coverage === null ? 'Sin información' : number_format($coverage, 1) . ' días', 'numeric' => $coverage, 'delta' => '', 'delta_is_good' => true,
				'subtext' => 'según venta de los últimos 28 días', 'state' => $coverage !== null && $coverage < 3 ? 'red' : 'green', 'icon' => 'fa-clock',
				'formula' => 'Cobertura = unidades en stock (' . number_format($totalUnits, 0) . ') ÷ unidades vendidas por día (' . number_format($dailyUnits, 1) . ').', 'sparkline_points' => $evolution['spark_units']],
			'below_min' => ['id' => 'below_min', 'label' => 'Productos bajo mínimo', 'value' => number_format($counts['below_min'], 0), 'numeric' => $counts['below_min'], 'delta' => '', 'delta_is_good' => false,
				'subtext' => 'pares producto-sucursal', 'state' => $counts['below_min'] ? 'red' : 'green', 'icon' => 'fa-triangle-exclamation',
				'formula' => 'Productos activos con existencia en la sucursal mayor a 0 y menor al stock mínimo configurado.', 'sparkline_points' => []],
			'expiring_soon' => ['id' => 'expiring_soon', 'label' => 'Próximos a vencer', 'value' => number_format($counts['expiring'], 0), 'numeric' => $counts['expiring'], 'delta' => '', 'delta_is_good' => false,
				'subtext' => 'en los próximos ' . $fefoDays . ' días', 'state' => $counts['expiring'] ? 'red' : 'green', 'icon' => 'fa-calendar-days',
				'formula' => 'Productos (por sucursal) con lotes que vencen en ≤ ' . $fefoDays . ' días y saldo físico > 0.', 'sparkline_points' => []],
			'out_of_stock' => ['id' => 'out_of_stock', 'label' => 'Productos agotados', 'value' => number_format($counts['out_of_stock'], 0), 'numeric' => $counts['out_of_stock'], 'delta' => '', 'delta_is_good' => true,
				'subtext' => 'de ' . $counts['catalog'] . ' pares producto-sucursal', 'state' => $counts['out_of_stock'] ? 'red' : 'green', 'icon' => 'fa-circle-check',
				'formula' => 'Productos activos con stock mínimo y existencia 0 en la sucursal.', 'sparkline_points' => []],
			'risk_value' => ['id' => 'risk_value', 'label' => 'Valor en riesgo (vencimientos)', 'value' => '$' . number_format($totalRisk, 0), 'numeric' => $totalRisk, 'delta' => '', 'delta_is_good' => false,
				'subtext' => 'en los próximos ' . $fefoDays . ' días', 'state' => 'amber', 'icon' => 'fa-coins',
				'formula' => 'Σ (unidades de lotes que vencen en ≤ ' . $fefoDays . ' días × costo del lote). Costo expuesto, no pérdida confirmada.', 'sparkline_points' => []]
		];

		// Desglose de tarjetas, alertas, distribución por departamento y oportunidades (servicio compartido de insights)
		$insights = StockOverviewInsightsService::GetInstance()->Build([
			'db' => $db, 'branchId' => $branchId, 'branchName' => $branchName, 'deptKeys' => $deptKeys, 'fefoDays' => $fefoDays, 'date' => $date, 'periodDays' => $periodDays,
			'startTs' => date('Y-m-d 23:59:59', strtotime("$date -$periodDays days")), 'lotsAll' => $lotsAll, 'soldPeriod' => $soldPeriod, 'sold28' => $sold28, 'deptOf' => $deptOf,
			'totalUnits' => $totalUnits, 'totalVal' => $totalVal, 'evolution' => $evolution
		]);
		$kx = $insights['kpi_extra'];
		$prev = $evolution['period_prev'] ?? [];
		$pct = fn($cur, $old) => ($old !== null && $old > 0 && $cur !== null) ? round(($cur - $old) / $old * 100, 1) : null;
		$tone = function (string $key, ?string $text, bool $good) use (&$kpis) {
			$kpis[$key]['delta'] = $text ?? '';
			$kpis[$key]['delta_is_good'] = $good;
		};
		$uD = $pct($totalUnits, $prev['units'] ?? null);
		$tone('stock_units', $arrow($uD), $uD === null || $uD >= 0);
		$rD = $pct($rotation, $prev['rotation'] ?? null);
		$tone('turnover', $arrow($rD), $rD === null || $rD >= 0);
		$cPrev = $prev['coverage'] ?? null;
		$cDiff = ($coverage !== null && $cPrev !== null) ? round($coverage - $cPrev, 1) : null;
		$mid = (StockOverviewInsightsService::TARGET_COVERAGE_MIN + StockOverviewInsightsService::TARGET_COVERAGE_MAX) / 2;
		$tone('coverage', $cDiff === null ? '' : (($cDiff >= 0 ? '↑ +' : '↓ ') . number_format($cDiff, 1) . ' días'), $cDiff === null || abs($coverage - $mid) <= abs($cPrev - $mid));
		$tone('below_min', $arrow($kx['deltas']['below_min']), $kx['deltas']['below_min'] === null || $kx['deltas']['below_min'] <= 0);
		$tone('out_of_stock', $arrow($kx['deltas']['out_of_stock']), $kx['deltas']['out_of_stock'] === null || $kx['deltas']['out_of_stock'] <= 0);
		$kpis['inventory_value']['delta_is_good'] = $valDelta === null || $valDelta >= 0;
		$kpis['stock_units']['subtext'] = count($byProduct) . ' productos con existencias' . ($uD === null ? '' : ' · vs. inicio del período');
		$kpis['below_min']['subtext'] = 'pares producto-sucursal' . ($kx['deltas']['below_min'] === null ? '' : ' · vs. inicio del período');
		$kpis['out_of_stock']['subtext'] = 'de ' . $counts['catalog'] . ' pares producto-sucursal' . ($kx['deltas']['out_of_stock'] === null ? '' : ' · vs. inicio del período');

		$kpis['turnover']['target'] = 'Objetivo: ' . number_format(StockOverviewInsightsService::TARGET_ROTATION, 1) . '×';
		$kpis['coverage']['target'] = 'Objetivo: ' . StockOverviewInsightsService::TARGET_COVERAGE_MIN . '–' . StockOverviewInsightsService::TARGET_COVERAGE_MAX . ' días';
		$kpis['inventory_value']['target'] = $startVal !== null ? 'Inicio del período: $' . number_format($startVal, 0) : '';
		// Estado visual calculado con reglas de negocio (verde / ámbar / rojo)
		$kpis['inventory_value']['state'] = ($valDelta === null || $valDelta >= 0) ? 'green' : ($valDelta > -10 ? 'amber' : 'red');
		$kpis['stock_units']['state'] = ($totalUnits > 0 && $kx['stock_units']['blocked'] / $totalUnits > 0.1) ? 'amber' : 'green';
		$kpis['turnover']['state'] = $rotation === null ? 'amber' : ($rotation >= StockOverviewInsightsService::TARGET_ROTATION ? 'green' : ($rotation >= StockOverviewInsightsService::TARGET_ROTATION / 2 ? 'amber' : 'red'));
		$kpis['coverage']['state'] = $coverage === null ? 'amber' : (($coverage < 3 || $coverage > 2 * StockOverviewInsightsService::TARGET_COVERAGE_MAX) ? 'red'
			: (($coverage < StockOverviewInsightsService::TARGET_COVERAGE_MIN || $coverage > StockOverviewInsightsService::TARGET_COVERAGE_MAX) ? 'amber' : 'green'));
		$kpis['below_min']['state'] = $counts['below_min'] === 0 ? 'green' : ($kx['below_min']['critical'] > 0 ? 'red' : 'amber');
		$kpis['expiring_soon']['state'] = $counts['expiring'] === 0 ? 'green' : ($kx['expiring_soon']['bands'][0]['count'] > 0 ? 'red' : 'amber');
		$kpis['out_of_stock']['state'] = $counts['out_of_stock'] === 0 ? 'green' : (($counts['catalog'] > 0 && $counts['out_of_stock'] / $counts['catalog'] >= 0.1) ? 'red' : 'amber');
		$kpis['risk_value']['state'] = $totalRisk <= 0 ? 'green' : (($totalVal > 0 && $totalRisk / $totalVal >= 0.1) ? 'red' : 'amber');
		foreach ($kx as $kk => $extra)
		{
			if (isset($kpis[$kk]))
			{
				$kpis[$kk]['extra'] = $extra;
			}
		}

		// Departamentos (dona) con categoría = grupo de producto y su producto de mayor valor
		$depts = [];
		foreach ($byProduct as $p)
		{
			$k = $p['dept_key'];
			$d = &$depts[$k];
			$d['id'] = $k;
			$d['name'] = DailyMetricsService::DEPARTMENTS[$k][0];
			$d['color'] = DailyMetricsService::DEPARTMENTS[$k][1];
			$d['value'] = ($d['value'] ?? 0) + $p['value'];
			$d['units'] = ($d['units'] ?? 0) + $p['units'];
			$d['risk_value'] = ($d['risk_value'] ?? 0) + $p['risk'];
			$d['_cogs'] = ($d['_cogs'] ?? 0) + $cogsOf($p['product_id']);
			$d['_daily'] = ($d['_daily'] ?? 0) + $soldDay($p['product_id']);
			$g = &$d['_groups'][$p['group']];
			$g['name'] = $p['group'];
			$g['value'] = ($g['value'] ?? 0) + $p['value'];
			$g['units'] = ($g['units'] ?? 0) + $p['units'];
			if (!isset($g['top']) || $p['value'] > $g['top']['value'])
			{
				$g['top'] = ['name' => $p['group'], 'sku' => $p['sku'], 'product' => $p['name'], 'lot' => $p['top_lot']['lot'], 'location' => $p['top_lot']['location'], 'units' => round($p['units'], 0), 'value' => round($p['value'], 2)];
			}
			unset($g, $d);
		}
		foreach ($depts as $k => &$d)
		{
			$dc = $m->StockStatusCounts($branchId, $fefoDays, [$k]);
			$d['value'] = round($d['value'], 2);
			$d['units'] = round($d['units'], 0);
			$d['risk_value'] = round($d['risk_value'], 2);
			$d['share_pct'] = $totalVal > 0 ? round($d['value'] / $totalVal * 100, 1) : 0;
			$d['rotation'] = $d['value'] > 0 ? round($d['_cogs'] / $d['value'], 2) : 0;
			$d['coverage_days'] = $d['_daily'] > 0 ? round($d['units'] / $d['_daily'], 1) : 0;
			$d['below_min'] = $dc['below_min'];
			$d['expiring'] = $dc['expiring'];
			$d['out_of_stock'] = $dc['out_of_stock'];
			$d['reference_display_value'] = $d['value'];
			$d['categories'] = array_values(array_map(fn($g) => ['name' => $g['name'], 'value' => round($g['value'], 2), 'units' => round($g['units'], 0), 'subcategories' => [$g['top']]], $d['_groups']));
			unset($d['_groups'], $d['_cogs'], $d['_daily']);
		}
		unset($d);
		uasort($depts, fn($a, $b) => $b['value'] <=> $a['value']);

		// Tabla por sucursal (mismos filtros de producto; suma = red)
		$branchRows = [];
		foreach ($branchName as $b => $name)
		{
			if ($allowedBranches !== null && !in_array($b, $allowedBranches, true))
			{
				continue;
			}
			$bc = $m->StockStatusCounts($b, $fefoDays, $deptKeys);
			$val = round($byBranch[$b]['value'] ?? 0, 2);
			$units = round($byBranch[$b]['units'] ?? 0, 0);
			$cg = 0.0;
			$du = 0.0;
			foreach ($soldPeriod[$b] ?? [] as $pid => $r)
			{
				$cg += floatval($r['cogs']);
			}
			foreach ($sold28[$b] ?? [] as $pid => $u)
			{
				$du += $u;
			}
			$adj = $db->query("SELECT IFNULL(SUM(total_cost_impact), 0) FROM inventory_adjustments WHERE branch_id = $b AND status = 'approved' AND date(created_timestamp) BETWEEN '$from' AND '$date'")->fetchColumn();
			$cov = $du > 0 ? round($units / $du, 1) : null;
			$status = ($bc['out_of_stock'] > 3 || ($cov !== null && $cov < 2)) ? 'critical' : (($bc['out_of_stock'] > 0 || $bc['expiring'] > 5) ? 'warning' : 'optimal');
			$start = $evolution['branch_start'][$b] ?? null;
			$branchRows[] = ['id' => $b, 'code' => sprintf('%02d', $b), 'name' => $name, 'inventory_value' => $val, 'units' => $units, 'cogs_period' => round($cg, 2),
				'rotation' => $val > 0 ? round($cg / $val, 2) : 0, 'coverage_days' => $cov ?? 0, 'inventory_diff_val' => round(floatval($adj), 2), 'inventory_diff_pct' => $val > 0 ? round(floatval($adj) / $val * 100, 2) : 0,
				'prev_variation_pct' => ($start !== null && $start > 0) ? round(($val - $start) / $start * 100, 1) : 0, 'status' => $status,
				'status_label' => ['critical' => 'Crítico', 'warning' => 'Atención', 'optimal' => 'Óptimo'][$status], 'below_min' => $bc['below_min'], 'expiring' => $bc['expiring'],
				'out_of_stock' => $bc['out_of_stock'], 'risk_value' => round($byBranch[$b]['risk'] ?? 0, 2)];
		}

		$topProducts = $this->BuildTopProducts($department, 1.0, 1.0, $skuQuery, $invStatus, ['by_product' => $byProduct, 'sold_day' => $soldDay, 'cogs' => $cogsOf]);
		$expiringProducts = $this->BuildExpiringProducts($department, $branchId, $fefoDays, $skuQuery, $lots, $deptOf, $branchName);
		$movements = $this->BuildRealtimeMovements($branchId, $movementType, 1.0, $from, $date, $deptKeys);
		$heatmap = $this->BuildHeatmapReal($branchId, $date);
		$priorityDecisions = $this->BuildPriorityDecisions($counts['expiring'], $totalRisk, $counts['below_min'], $counts['out_of_stock'], $branchId, $fefoDays);

		return [
			'filters' => ['date' => $date, 'period' => $period, 'branch_id' => $branchId ?? 'all', 'department' => $department, 'category' => $category, 'supplier' => $supplier, 'sku' => $skuQuery,
				'status' => $invStatus, 'location' => $location, 'movement_type' => $movementType, 'fefo_days' => $fefoDays],
			'meta' => [
				'last_updated' => date('d/m/Y H:i'),
				'synced_at_real' => date('d M Y H:i:s'),
				'is_demo_network' => false,
				'demo_notice' => 'Cifras calculadas con las existencias, el kardex y las ventas registradas',
				'role' => DairyAccess::GetSimulatedRole(),
				'reconciliation' => [
					'kpi_valuation' => $totalVal,
					'donut_sum_valuation' => $insights['dept_overview']['in_scope_value'],
					'branches_sum_valuation' => round(array_sum(array_column($branchRows, 'inventory_value')), 2),
					'kpi_units' => $totalUnits,
					'donut_sum_units' => round(array_sum(array_column(array_filter($insights['dept_overview']['rows'], fn($r) => $deptKeys === null || in_array($r['id'], $deptKeys, true)), 'units')), 0),
					'branches_sum_units' => array_sum(array_column($branchRows, 'units')),
					'sqlite_local_valuation' => $totalVal,
					'sqlite_local_kardex_valuation' => $totalVal,
					'sqlite_local_products' => count($byProduct),
					'is_reconciled' => abs($totalVal - $insights['dept_overview']['in_scope_value']) < 1.0 && abs($insights['dept_overview']['share_sum'] - 100.0) < 0.05
				]
			],
			'kpis' => $kpis,
			'evolution' => $evolution,
			'donut' => ['center_value' => '$' . number_format($insights['dept_overview']['total_value'], 0), 'center_value_reconciled' => '$' . number_format($insights['dept_overview']['total_value'], 2), 'center_units' => number_format($insights['dept_overview']['total_units'], 0) . ' uds',
				'center_numeric_value' => $insights['dept_overview']['total_value'], 'center_numeric_units' => $insights['dept_overview']['total_units'], 'center_sub' => $insights['dept_overview']['count'] . ' departamentos', 'selected' => $insights['dept_overview']['selected'], 'departments' => $insights['dept_overview']['rows']],
			'heatmap' => $heatmap,
			'alerts' => $insights['alerts'], 'dept_overview' => $insights['dept_overview'], 'opportunities' => $insights['opportunities'], 'permissions' => $insights['permissions'], 'targets' => $kx['targets'],
			'top_products' => $topProducts,
			'expiring_products' => $expiringProducts,
			'branches_table' => $branchRows,
			'movements' => $movements,
			'priority_decisions' => $priorityDecisions
		];
	}

	/** Estadísticas reales de existencias (compatibilidad con llamadas existentes) */
	public function GetRealSqliteStats(?int $branchId = null): array
	{
		$inv = DailyMetricsService::GetInstance()->Inventory($branchId);
		return ['products' => intval($this->Db()->query('SELECT COUNT(DISTINCT product_id) FROM stock WHERE amount > 0')->fetchColumn()), 'valuation' => $inv['value'], 'physical_valuation' => $inv['value'],
			'units' => $inv['units'], 'coverage_days' => null, 'below_min' => $inv['below_min'], 'expiring' => $inv['expiring_products'], 'expiring_value' => $inv['expiring_value'], 'out_of_stock' => $inv['out_of_stock']];
	}

	/**
	 * Evolución del valor y unidades reconstruida desde el kardex: valor en t = valor actual − Σ movimientos posteriores a t.
	 * Rotación de cada tramo = costo de ventas del tramo ÷ valor al cierre; cobertura = unidades ÷ venta diaria del tramo.
	 */
	private function BuildEvolutionReal(string $period, string $date, ?int $branchId, float $curVal, float $curUnits, array $byBranch, array $branchName, ?array $deptKeys = null): array
	{
		$db = $this->Db();
		$deptSql = DailyMetricsService::GetInstance()->DeptSql($deptKeys);
		$bSql = $branchId ? ' AND l.branch_id = ' . intval($branchId) : '';
		$mon = ['', 'Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
		$defs = [
			'day' => [14, '1 day', fn($t) => date('d', $t) . ' ' . $mon[intval(date('n', $t))]],
			'week' => [8, '7 days', fn($t) => 'Sem ' . date('W', $t)],
			'month' => [6, '1 month', fn($t) => $mon[intval(date('n', $t))] . ' ' . date('y', $t)],
			'quarter' => [4, '3 months', fn($t) => 'T' . ceil(intval(date('n', $t)) / 3) . ' ' . date('y', $t)],
			'year' => [3, '1 year', fn($t) => date('Y', $t)]
		];
		// El filtro de departamento se aplica a kardex y ventas con el mismo criterio que a las existencias
		$after = $db->prepare("SELECT IFNULL(SUM(sl.amount * IFNULL(sl.price, 0)), 0), IFNULL(SUM(sl.amount), 0) FROM stock_log sl JOIN locations l ON l.id = sl.location_id JOIN products p ON p.id = sl.product_id
			WHERE sl.undone = 0 AND IFNULL(l.is_transit, 0) = 0 AND sl.row_created_timestamp > :t AND $deptSql$bSql");
		$cogs = $db->prepare("SELECT IFNULL(SUM(si.total_cost), 0) FROM sale_items si JOIN sales s ON s.id = si.sale_id JOIN products p ON p.id = si.product_id
			WHERE s.status = 'completed' AND s.created_timestamp > :a AND s.created_timestamp <= :b AND $deptSql" . ($branchId ? ' AND s.branch_id = ' . intval($branchId) : ''));
		$units = $db->prepare("SELECT IFNULL(SUM(si.amount), 0) FROM sale_items si JOIN sales s ON s.id = si.sale_id JOIN products p ON p.id = si.product_id
			WHERE s.status = 'completed' AND s.created_timestamp > :a AND s.created_timestamp <= :b AND $deptSql" . ($branchId ? ' AND s.branch_id = ' . intval($branchId) : ''));
		$first = $db->query("SELECT MIN(row_created_timestamp) FROM stock_log")->fetchColumn();
		$all = [];
		foreach ($defs as $key => [$n, $step, $label])
		{
			$s = ['labels' => [], 'value' => [], 'prev_year_value' => [], 'units' => [], 'rotation' => [], 'coverage' => [],
				'prev_labels' => [], 'prev_value' => [], 'prev_units' => [], 'prev_rotation' => [], 'prev_coverage' => []];
			// 2n tramos: los n más recientes son el período actual y los n anteriores el período comparable
			for ($i = $n * 2 - 1; $i >= 0; $i--)
			{
				$isPrev = $i >= $n;
				$end = strtotime("$date 23:59:59 -" . ($i * intval($step)) . ' ' . preg_replace('/^\d+ /', '', $step));
				$startT = strtotime('-' . $step, $end);
				$endS = date('Y-m-d H:i:s', $end);
				$after->execute([':t' => $endS]);
				[$av, $au] = $after->fetch(\PDO::FETCH_NUM);
				$known = $first && $endS >= $first;
				$v = $known ? round($curVal - floatval($av), 0) : null;
				$u = $known ? round($curUnits - floatval($au), 0) : null;
				$cogs->execute([':a' => date('Y-m-d H:i:s', $startT), ':b' => $endS]);
				$c = floatval($cogs->fetchColumn());
				$units->execute([':a' => date('Y-m-d H:i:s', $startT), ':b' => $endS]);
				$su = floatval($units->fetchColumn());
				$days = max(1, ($end - $startT) / 86400);
				$pre = $isPrev ? 'prev_' : '';
				$s[$isPrev ? 'prev_labels' : 'labels'][] = $label($end) . (!$isPrev && $i === 0 ? ' (actual)' : '');
				$s[$pre . 'value'][] = $v;
				if (!$isPrev)
				{
					$s['prev_year_value'][] = null;
				}
				$s[$pre . 'units'][] = $u;
				$s[$pre . 'rotation'][] = ($v && $v > 0) ? round($c / $v, 2) : null;
				$s[$pre . 'coverage'][] = ($su > 0 && $u !== null) ? round($u / ($su / $days), 1) : null;
			}
			$all[$key] = $s;
		}
		$active = isset($all[$period]) ? $period : 'month';
		// Valor al inicio del período elegido y por sucursal (para la variación)
		$periodDays = ['day' => 1, 'week' => 7, 'month' => 30, 'quarter' => 90, 'year' => 365][$active];
		$startTs = date('Y-m-d 23:59:59', strtotime("$date -$periodDays days"));
		$after->execute([':t' => $startTs]);
		[$pvRaw, $puRaw] = $after->fetch(\PDO::FETCH_NUM);
		$startKnown = $first && $startTs >= $first;
		$pv = $startKnown ? round($curVal - floatval($pvRaw), 2) : null;
		$pu = $startKnown ? round($curUnits - floatval($puRaw), 0) : null;
		// Período anterior comparable (variación de rotación y cobertura): ventana de la misma duración justo antes del inicio
		$prevFrom = date('Y-m-d H:i:s', strtotime("$date 23:59:59 -" . (2 * $periodDays) . ' days'));
		$cogs->execute([':a' => $prevFrom, ':b' => $startTs]);
		$pc = floatval($cogs->fetchColumn());
		$units->execute([':a' => $prevFrom, ':b' => $startTs]);
		$psu = floatval($units->fetchColumn());
		$periodPrev = ['value' => $pv, 'units' => $pu, 'rotation' => ($pv && $pv > 0) ? round($pc / $pv, 2) : null, 'coverage' => ($psu > 0 && $pu !== null) ? round($pu / ($psu / $periodDays), 1) : null];
		$branchStart = [];
		$st = $db->prepare("SELECT l.branch_id, IFNULL(SUM(sl.amount * IFNULL(sl.price, 0)), 0) FROM stock_log sl JOIN locations l ON l.id = sl.location_id WHERE sl.undone = 0 AND IFNULL(l.is_transit, 0) = 0 AND sl.row_created_timestamp > :t GROUP BY l.branch_id");
		$st->execute([':t' => $startTs]);
		$afterByBranch = $st->fetchAll(\PDO::FETCH_KEY_PAIR);
		foreach ($branchName as $b => $name)
		{
			$branchStart[$b] = $startKnown ? round(($byBranch[$b]['value'] ?? 0) - floatval($afterByBranch[$b] ?? 0), 2) : null;
		}
		$compare = ['labels' => [], 'value' => [], 'units' => [], 'rotation' => [], 'coverage' => []];
		foreach ($branchName as $b => $name)
		{
			if ($branchId && $b !== $branchId)
			{
				continue;
			}
			$compare['labels'][] = $name;
			$compare['value'][] = round($byBranch[$b]['value'] ?? 0, 0);
			$compare['units'][] = round($byBranch[$b]['units'] ?? 0, 0);
		}
		$spark = fn(array $xs) => array_values(array_map(fn($x) => $x === null ? 0 : $x, array_slice($xs, -8)));
		return ['active_period' => $active, 'series' => $all[$active], 'all_periods' => $all, 'branch_compare' => $compare, 'period_start_value' => $pv, 'period_prev' => $periodPrev, 'branch_start' => $branchStart,
			'spark_value' => $spark($all['day']['value']), 'spark_units' => $spark($all['day']['units']), 'spark_rotation' => $spark($all['day']['rotation'])];
	}

	/** Mapa de calor Lun–Dom × 07–20 con el kardex de las últimas 4 semanas (promedio por día) */
	private function BuildHeatmapReal(?int $branchId, string $date): array
	{
		$days = ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'];
		$hours = ['07', '08', '09', '10', '11', '12', '13', '14', '15', '16', '17', '18', '19', '20'];
		$from = date('Y-m-d', strtotime("$date -27 days"));
		$st = $this->Db()->prepare("SELECT CAST(strftime('%w', sl.row_created_timestamp) AS INTEGER) AS w, CAST(strftime('%H', sl.row_created_timestamp) AS INTEGER) AS h, COUNT(*) AS n,
				SUM(CASE WHEN sl.transaction_type = 'consume' AND sl.spoiled = 0 THEN -sl.amount ELSE 0 END) AS sold,
				SUM(CASE WHEN sl.transaction_type IN ('purchase', 'transfer_to', 'inventory-correction') AND sl.amount > 0 THEN 1 ELSE 0 END) AS repl
			FROM stock_log sl JOIN locations l ON l.id = sl.location_id
			WHERE sl.undone = 0 AND IFNULL(l.is_transit, 0) = 0 AND date(sl.row_created_timestamp) BETWEEN :f AND :t" . ($branchId ? ' AND l.branch_id = ' . intval($branchId) : '') . ' GROUP BY w, h');
		$st->execute([':f' => $from, ':t' => $date]);
		$cell = [];
		foreach ($st->fetchAll(\PDO::FETCH_ASSOC) as $r)
		{
			$cell[intval($r['w']) === 0 ? 7 : intval($r['w'])][intval($r['h'])] = $r;
		}
		$units = DailyMetricsService::GetInstance()->Inventory($branchId)['units'];
		$maxN = 1;
		$maxS = 1;
		foreach ($cell as $row)
		{
			foreach ($row as $r)
			{
				$maxN = max($maxN, intval($r['n']));
				$maxS = max($maxS, floatval($r['sold']));
			}
		}
		$grid = [];
		$ranges = ['units' => [null, null], 'stockout_risk' => [null, null], 'movements_vol' => [null, null], 'replenishment' => [null, null], 'hourly_demand' => [null, null]];
		foreach ($days as $di => $dn)
		{
			$cells = [];
			foreach ($hours as $hh)
			{
				$r = $cell[$di + 1][intval($hh)] ?? ['n' => 0, 'sold' => 0, 'repl' => 0];
				$mov = round(intval($r['n']) / 4, 1);
				$dem = round(floatval($r['sold']) / 4, 1);
				$rep = round(intval($r['repl']) / 4, 1);
				$press = $units > 0 ? round($dem / $units * 100, 2) : 0;
				$p = intval(round(floatval($r['sold']) / $maxS * 100));
				$vals = ['units' => $units, 'stockout_risk' => $press, 'movements_vol' => $mov, 'replenishment' => $rep, 'hourly_demand' => $dem];
				foreach ($vals as $k => $v)
				{
					$ranges[$k] = [$ranges[$k][0] === null ? $v : min($ranges[$k][0], $v), $ranges[$k][1] === null ? $v : max($ranges[$k][1], $v)];
				}
				$cells[] = ['day' => $dn, 'hour' => $hh, 'period_label' => "$dn $hh:00 – $hh:59", 'intensity' => $p, 'color' => self::HeatmapColor($p),
					'metrics' => [
						'units' => ['val' => $units, 'formatted' => number_format($units, 0) . ' uds', 'status' => 'Existencia actual (no varía por franja)'],
						'stockout_risk' => ['val' => $press, 'formatted' => number_format($press, 2) . '%', 'status' => 'Venta promedio de la franja ÷ stock actual'],
						'movements_vol' => ['val' => $mov, 'formatted' => number_format($mov, 1) . ' movs', 'status' => 'Movimientos de kardex promedio por día'],
						'replenishment' => ['val' => $rep, 'formatted' => number_format($rep, 1) . ' ingresos', 'status' => 'Entradas (recepciones, traslados, correcciones) promedio por día'],
						'hourly_demand' => ['val' => $dem, 'formatted' => number_format($dem, 1) . ' uds/h', 'status' => 'Unidades vendidas promedio por día']
					]];
			}
			$grid[] = ['day' => $dn, 'cells' => $cells];
		}
		$lg = fn($k, $fmt) => ['min_label' => 'Mín. ' . $fmt($ranges[$k][0] ?? 0), 'max_label' => 'Máx. ' . $fmt($ranges[$k][1] ?? 0)];
		return ['days' => $days, 'hours' => $hours, 'grid' => $grid, 'note' => 'Promedio por día de las últimas 4 semanas, desde el kardex',
			'legends' => [
				'units' => $lg('units', fn($v) => number_format($v, 0) . ' uds'),
				'stockout_risk' => $lg('stockout_risk', fn($v) => number_format($v, 2) . '%'),
				'movements_vol' => $lg('movements_vol', fn($v) => number_format($v, 1) . ' movs'),
				'replenishment' => $lg('replenishment', fn($v) => number_format($v, 1) . ' ingresos'),
				'hourly_demand' => $lg('hourly_demand', fn($v) => number_format($v, 1) . ' uds/h')
			]];
	}

	private static function HeatmapColor(int $intensity): string
	{
		if ($intensity <= 22) return '#4ade80';
		if ($intensity <= 35) return '#86efac';
		if ($intensity <= 50) return '#fde047';
		if ($intensity <= 68) return '#facc15';
		if ($intensity <= 82) return '#fb923c';
		if ($intensity <= 92) return '#ef4444';
		return '#dc2626';
	}

	/** Top 10 productos por valor en inventario (alcance y filtros del panel) */
	private function BuildTopProducts(string $department, float $valScale = 1.0, float $unitScale = 1.0, string $skuQuery = '', string $invStatus = 'todos', ?array $ctx = null): array
	{
		if ($ctx === null)
		{
			// Ficha 360: toda la red sin filtros
			$data = $this->BuildExecutiveDataRaw([]);
			return $data['top_products'];
		}
		$items = array_values($ctx['by_product']);
		usort($items, fn($a, $b) => $b['value'] <=> $a['value']);
		$out = [];
		$barcode = $this->Db()->prepare('SELECT barcode FROM product_barcodes WHERE product_id = ? ORDER BY id LIMIT 1');
		foreach ($items as $it)
		{
			$daily = ($ctx['sold_day'])($it['product_id']);
			$cov = $daily > 0 ? round($it['units'] / $daily, 1) : 0;
			$riskPct = $it['value'] > 0 ? round($it['risk'] / $it['value'] * 100) : 0;
			$status = $riskPct >= 50 ? 'critical' : ($riskPct >= 20 || ($daily > 0 && $cov < 2) ? 'warning' : 'optimal');
			if ($invStatus !== 'todos' && $status !== $invStatus)
			{
				continue;
			}
			$barcode->execute([$it['product_id']]);
			$out[] = ['rank' => count($out) + 1, 'product_id' => $it['product_id'], 'name' => $it['name'], 'sku' => $it['sku'], 'upc' => $barcode->fetchColumn() ?: '', 'dept_key' => $it['dept_key'],
				'department' => $it['department'], 'image' => CustomerOrderService::GetInstance()->ResolveProductImage($it['name'], $it['group']), 'value' => round($it['value'], 2), 'units' => round($it['units'], 0),
				'rotation' => $it['value'] > 0 ? round(($ctx['cogs'])($it['product_id']) / $it['value'], 2) : 0, 'coverage_days' => $cov, 'risk_score' => $riskPct, 'status' => $status,
				'status_label' => ['critical' => 'Riesgo de vencimiento', 'warning' => 'Vigilar FEFO / cobertura', 'optimal' => 'Óptimo'][$status],
				'formatted_value' => '$' . number_format($it['value'], 0), 'formatted_units' => number_format($it['units'], 0)];
			if (count($out) >= 10)
			{
				break;
			}
		}
		return $out;
	}

	/** Lotes con saldo que vencen dentro del umbral FEFO (más próximos primero) */
	private function BuildExpiringProducts(string $department, ?int $branchId, int $fefoDays, string $skuQuery, array $lots = [], ?callable $deptOf = null, array $branchName = []): array
	{
		$today = date('Y-m-d');
		$limit = date('Y-m-d', strtotime("+$fefoDays days"));
		$rows = [];
		foreach ($lots as $l)
		{
			if ($branchId !== null && intval($l['branch_id']) !== $branchId)
			{
				continue;
			}
			if ($l['best_before_date'] < $today || $l['best_before_date'] > $limit)
			{
				continue;
			}
			$days = intval(floor((strtotime($l['best_before_date']) - strtotime($today)) / 86400));
			$k = $deptOf ? $deptOf($l['department']) : 'otros';
			$v = floatval($l['amount']) * floatval($l['cost']);
			[$state, $badge] = $days <= 3 ? ['Crítico (≤3d)', 'danger'] : ($days <= 7 ? ['Alerta (4-7d)', 'warning'] : ($days <= 15 ? ['Alerta (8-15d)', 'warning'] : ['Seguimiento (16-30d)', 'info']));
			$rows[] = ['product_id' => intval($l['product_id']), 'name' => $l['name'], 'sku' => $l['sku'] ?? '', 'dept_key' => $k, 'department' => DailyMetricsService::DEPARTMENTS[$k][0],
				'lot' => $l['lot_number'] ?: substr($l['stock_id'], 0, 10), 'stock_id' => $l['stock_id'], 'expiry_date' => $l['best_before_date'], 'days_left' => $days, 'stock' => round(floatval($l['amount']), 2),
				'value' => round($v, 2), 'formatted_value' => '$' . number_format($v, 0), 'branch_id' => intval($l['branch_id']), 'branch_name' => $branchName[intval($l['branch_id'])] ?? '',
				'location' => $l['location_name'], 'location_id' => intval($l['location_id']), 'fefo_state' => $state, 'fefo_badge' => $badge,
				'image' => CustomerOrderService::GetInstance()->ResolveProductImage($l['name'], $l['group_name'])];
		}
		usort($rows, fn($a, $b) => [$a['days_left'], -$a['value']] <=> [$b['days_left'], -$b['value']]);
		$rows = array_slice($rows, 0, 50);
		foreach ($rows as $i => &$r)
		{
			$r['rank'] = $i + 1;
		}
		return $rows;
	}

	/** Movimientos del período desde el kardex, clasificados sin doble conteo (cada fila de stock_log en una sola categoría) */
	private function BuildRealtimeMovements(?int $branchId, string $movementType, float $scale = 1.0, ?string $from = null, ?string $to = null, ?array $deptKeys = null): array
	{
		$from = $from ?: date('Y-m-d', strtotime('-29 days'));
		$to = $to ?: date('Y-m-d');
		$st = $this->Db()->prepare("SELECT sl.transaction_type AS t, sl.spoiled, sl.correlation_id, SUM(ABS(sl.amount)) AS units,
				EXISTS (SELECT 1 FROM sale_items si JOIN sales s ON s.id = si.sale_id JOIN customer_orders co ON co.sale_id = s.id WHERE si.transaction_id = sl.transaction_id) AS is_dispatch,
				EXISTS (SELECT 1 FROM waste_records w WHERE w.transaction_id = sl.transaction_id) AS is_waste
			FROM stock_log sl JOIN locations l ON l.id = sl.location_id
			WHERE sl.undone = 0 AND IFNULL(l.is_transit, 0) = 0 AND date(sl.row_created_timestamp) BETWEEN :f AND :t" . ($branchId ? ' AND l.branch_id = ' . intval($branchId) : '') . '
			GROUP BY sl.id');
		$st->execute([':f' => $from, ':t' => $to]);
		$c = ['entradas' => 0, 'salidas' => 0, 'traslados' => 0, 'devoluciones' => 0, 'despachos' => 0, 'ajustes' => 0, 'mermas' => 0];
		foreach ($st->fetchAll(\PDO::FETCH_ASSOC) as $r)
		{
			$u = floatval($r['units']);
			$t = $r['t'];
			if ($t === 'purchase')
			{
				$c['entradas'] += $u;
			}
			elseif (in_array($t, ['transfer_from', 'transfer_to'], true))
			{
				$c['traslados'] += $u / 2;
			}
			elseif ($t === 'inventory-correction' || $t === 'stock-edit-old' || $t === 'stock-edit-new')
			{
				$c['ajustes'] += $u;
			}
			elseif ($t === 'consume' && (intval($r['spoiled']) === 1 || intval($r['is_waste']) === 1))
			{
				$c['mermas'] += $u;
			}
			elseif ($t === 'consume' && intval($r['is_dispatch']) === 1)
			{
				$c['despachos'] += $u;
			}
			elseif ($t === 'consume')
			{
				$c['salidas'] += $u;
			}
			elseif ($t === 'product-opened' || $t === 'self-production')
			{
				continue;
			}
			else
			{
				$c['ajustes'] += $u;
			}
		}
		// Devoluciones de clientes: ventas anuladas (el stock vuelve al lote; no es una compra)
		$c['devoluciones'] = floatval($this->Db()->query("SELECT IFNULL(SUM(si.amount), 0) FROM sale_items si JOIN sales s ON s.id = si.sale_id WHERE s.status = 'cancelled' AND date(s.created_timestamp) BETWEEN '$from' AND '$to'" . ($branchId ? ' AND s.branch_id = ' . intval($branchId) : ''))->fetchColumn());
		$c['entradas'] = max(0, $c['entradas'] - $c['devoluciones']);
		$c = array_map(fn($v) => intval(round($v)), $c);
		$otros = $c['despachos'] + $c['ajustes'] + $c['mermas'];
		$bars5 = [
			['key' => 'entradas', 'label' => 'Entradas', 'full_label' => 'Entradas de mercancía (recepciones)', 'units' => $c['entradas'], 'color' => '#00C83B'],
			['key' => 'salidas', 'label' => 'Salidas', 'full_label' => 'Salidas por venta POS', 'units' => $c['salidas'], 'color' => '#EF4444'],
			['key' => 'traslados', 'label' => 'Traslados', 'full_label' => 'Traslados entre sucursales', 'units' => $c['traslados'], 'color' => '#EAB308'],
			['key' => 'devoluciones', 'label' => 'Dev. clientes', 'full_label' => 'Devoluciones de clientes (ventas anuladas)', 'units' => $c['devoluciones'], 'color' => '#A855F7'],
			['key' => 'otros', 'label' => 'Otros', 'full_label' => "Despachos ({$c['despachos']}), Ajustes ({$c['ajustes']}) y Mermas ({$c['mermas']})", 'units' => $otros, 'color' => '#64748B']
		];
		$bars7 = array_slice($bars5, 0, 4);
		$bars7[] = ['key' => 'despachos', 'label' => 'Despachos', 'full_label' => 'Despachos de pedidos de clientes', 'units' => $c['despachos'], 'color' => '#3B82F6'];
		$bars7[] = ['key' => 'ajustes', 'label' => 'Ajustes', 'full_label' => 'Ajustes y correcciones de inventario', 'units' => $c['ajustes'], 'color' => '#F97316'];
		$bars7[] = ['key' => 'mermas', 'label' => 'Mermas', 'full_label' => 'Mermas aprobadas (vencimiento, rotura, daño)', 'units' => $c['mermas'], 'color' => '#DC2626'];
		return ['bars_photo' => $bars5, 'bars_detailed' => $bars7, 'total_movements_units' => array_sum($c), 'period' => ['from' => $from, 'to' => $to],
			'ledger' => $this->GetDetailedMovementsLedgerRaw($branchId, $movementType, $deptKeys)];
	}

	/** Últimos movimientos del kardex con su documento de origen (venta, recepción, merma, pedido) */
	private function GetDetailedMovementsLedgerRaw(?int $branchId = null, string $movementType = 'todos', ?array $deptKeys = null): array
	{
		$rows = $this->Db()->query("SELECT sl.id, sl.product_id, sl.stock_id, p.department, sl.transaction_type, sl.amount, sl.spoiled, sl.row_created_timestamp, sl.note, sl.transaction_id, p.name, p.sku, l.name AS location_name, l.branch_id, b.name AS branch_name,
				IFNULL(NULLIF(TRIM(IFNULL(u.first_name, '') || ' ' || IFNULL(u.last_name, '')), ''), u.username) AS user_name,
				(SELECT s.sale_number FROM sale_items si JOIN sales s ON s.id = si.sale_id WHERE si.transaction_id = sl.transaction_id LIMIT 1) AS sale_number,
				(SELECT co.order_code FROM sale_items si JOIN customer_orders co ON co.sale_id = si.sale_id WHERE si.transaction_id = sl.transaction_id LIMIT 1) AS order_code,
				(SELECT IFNULL(w.case_number, 'MERMA-' || w.id) FROM waste_records w WHERE w.transaction_id = sl.transaction_id LIMIT 1) AS waste_ref,
				(SELECT r.receipt_code FROM procurement_receipts r WHERE r.stock_transaction_id = sl.transaction_id LIMIT 1) AS receipt_code
			FROM stock_log sl JOIN products p ON p.id = sl.product_id JOIN locations l ON l.id = sl.location_id LEFT JOIN branches b ON b.id = l.branch_id LEFT JOIN users u ON u.id = sl.user_id
			WHERE sl.undone = 0 AND sl.transaction_type NOT IN ('product-opened')" . ($branchId ? ' AND l.branch_id = ' . intval($branchId) : '') . ' AND ' . DailyMetricsService::GetInstance()->DeptSql($deptKeys) . '
			ORDER BY sl.row_created_timestamp DESC, sl.id DESC LIMIT 200')->fetchAll(\PDO::FETCH_ASSOC);
		$out = [];
		$deptKeyOf = function (?string $dept) {
			foreach (DailyMetricsService::DEPARTMENTS as $k => $d)
			{
				if ($d[2] !== null && in_array($dept, $d[2], true))
				{
					return $k;
				}
			}
			return 'otros';
		};
		$deptName = fn(?string $dept) => trim((string) $dept) === '' ? 'Sin clasificar' : DailyMetricsService::DEPARTMENTS[$deptKeyOf($dept)][0];
		foreach ($rows as $r)
		{
			$t = $r['transaction_type'];
			if ($t === 'purchase')
			{
				[$key, $label, $doc] = ['entradas', 'Entrada de mercancía', $r['receipt_code'] ?: ($r['note'] ?: 'Compra')];
			}
			elseif ($t === 'consume' && ($r['waste_ref'] || intval($r['spoiled']) === 1))
			{
				[$key, $label, $doc] = ['mermas', 'Merma', $r['waste_ref'] ?: 'Merma'];
			}
			elseif ($t === 'consume' && $r['order_code'])
			{
				[$key, $label, $doc] = ['despachos', 'Despacho de pedido', $r['order_code'] . ' / ' . $r['sale_number']];
			}
			elseif ($t === 'consume')
			{
				[$key, $label, $doc] = ['salidas', 'Salida por venta POS', $r['sale_number'] ?: 'Consumo'];
			}
			elseif (in_array($t, ['transfer_from', 'transfer_to'], true))
			{
				[$key, $label, $doc] = ['traslados', 'Traslado', $r['note'] ?: 'Traslado'];
			}
			else
			{
				[$key, $label, $doc] = ['ajustes', 'Ajuste de inventario', $r['note'] ?: $t];
			}
			if ($movementType !== 'todos' && $key !== $movementType && !($movementType === 'otros' && in_array($key, ['despachos', 'ajustes', 'mermas'], true)))
			{
				continue;
			}
			$q = floatval($r['amount']);
			$out[] = ['mov_id' => 'KDX-' . $r['id'], 'category_key' => $key, 'movement_type' => $label, 'product_name' => $r['name'], 'sku' => $r['sku'] ?? '',
				'quantity' => ($q > 0 ? '+' : '') . rtrim(rtrim(number_format($q, 2), '0'), '.') . ' uds', 'numeric_qty' => $q, 'timestamp' => $r['row_created_timestamp'],
				'branch_id' => intval($r['branch_id']), 'branch_name' => $r['branch_name'] ?? '', 'origin_location' => $q < 0 ? $r['location_name'] : ($key === 'entradas' ? 'Recepción' : '—'),
				'dest_location' => $q > 0 ? $r['location_name'] : ($key === 'mermas' ? 'Baja por merma' : ($key === 'salidas' ? 'Caja POS' : 'Cliente')), 'user' => $r['user_name'] ?? '',
				'ref_doc' => $doc, 'validation_status' => 'Registrado', 'badge_class' => $key === 'mermas' ? 'danger' : ($key === 'entradas' ? 'success' : 'secondary'),
				'product_id' => intval($r['product_id']), 'stock_id' => $r['stock_id'], 'transaction_id' => $r['transaction_id'], 'department' => $deptName($r['department']), 'dept_key' => $deptKeyOf($r['department']), 'source' => 'kardex'];
		}
		// Devoluciones y cambios de clientes: solo las que NO generaron ya un movimiento de kardex (stock_booking_id vacío), para no duplicar
		if ($movementType === 'todos' || $movementType === 'devoluciones')
		{
			$ret = $this->Db()->query("SELECT r.id, r.return_code, r.product_id, r.quantity, r.return_type, r.branch_id, r.created_at, r.approval_status, p.name, p.sku, p.department, b.name AS branch_name
				FROM customer_returns_exchanges r JOIN products p ON p.id = r.product_id LEFT JOIN branches b ON b.id = r.branch_id
				WHERE r.return_type IN ('cambio', 'devolucion_cliente') AND (r.stock_booking_id IS NULL OR r.stock_booking_id = '')" . ($branchId ? ' AND r.branch_id = ' . intval($branchId) : '') . ' AND ' . DailyMetricsService::GetInstance()->DeptSql($deptKeys) . '
				ORDER BY r.created_at DESC, r.id DESC LIMIT 60')->fetchAll(\PDO::FETCH_ASSOC);
			foreach ($ret as $r)
			{
				$q = floatval($r['quantity']);
				$out[] = ['mov_id' => 'DEV-' . $r['id'], 'category_key' => 'devoluciones', 'movement_type' => $r['return_type'] === 'cambio' ? 'Cambio de cliente' : 'Devolución de cliente', 'product_name' => $r['name'], 'sku' => $r['sku'] ?? '',
					'quantity' => '+' . rtrim(rtrim(number_format($q, 2), '0'), '.') . ' uds', 'numeric_qty' => $q, 'timestamp' => $r['created_at'], 'branch_id' => intval($r['branch_id']), 'branch_name' => $r['branch_name'] ?? '',
					'origin_location' => 'Cliente', 'dest_location' => 'Inspección', 'user' => '', 'ref_doc' => $r['return_code'], 'validation_status' => $r['approval_status'] === 'approved' ? 'Aprobada' : 'Pendiente', 'badge_class' => 'info',
					'product_id' => intval($r['product_id']), 'stock_id' => null, 'transaction_id' => null, 'department' => $deptName($r['department']), 'dept_key' => $deptKeyOf($r['department']), 'source' => 'devolucion'];
			}
			usort($out, fn($a, $b) => strcmp($b['timestamp'], $a['timestamp']));
		}
		return array_slice($out, 0, 200);
	}

	/** Decisiones con los conteos reales del panel; cada una enlaza al módulo donde se resuelve */
	private function BuildPriorityDecisions(int $expiringCount, float $riskVal, int $belowMinCount, int $outOfStockCount, ?int $branchId = null, int $fefoDays = 30): array
	{
		$qs = $branchId ? '?branch_id=' . $branchId : '';
		$now = date('d/m/Y H:i');
		$all = [];
		if ($expiringCount > 0)
		{
			$all[] = ['id' => 'DEC-FEFO', 'severity' => 'red', 'category_label' => 'Crítico', 'icon' => 'fa-triangle-exclamation', 'title' => "$expiringCount productos próximos a vencer",
				'line1' => 'Valor en riesgo: $' . number_format($riskVal, 0) . " en ≤ $fefoDays días.", 'line2' => 'Revisar FEFO: rebaja, traslado o retiro del lote.', 'owner' => 'Calidad y FEFO', 'date' => $now,
				'priority' => 'Crítica', 'status' => 'Pendiente', 'action_type' => 'open_fefo_modal', 'action_label' => 'Ejecutar FEFO', 'href' => '/quality' . $qs];
		}
		if ($outOfStockCount > 0)
		{
			$all[] = ['id' => 'DEC-OOS', 'severity' => 'red', 'category_label' => 'Crítico', 'icon' => 'fa-box-open', 'title' => "$outOfStockCount productos agotados",
				'line1' => 'Pares producto-sucursal con existencia 0 y stock mínimo configurado.', 'line2' => 'Generar orden de compra o traslado.', 'owner' => 'Compras', 'date' => $now,
				'priority' => 'Alta', 'status' => 'Pendiente', 'action_type' => 'navigate', 'action_label' => 'Abrir MRP', 'href' => '/mrp' . $qs];
		}
		if ($belowMinCount > 0)
		{
			$all[] = ['id' => 'DEC-MIN', 'severity' => 'yellow', 'category_label' => 'Atención', 'icon' => 'fa-arrow-down-wide-short', 'title' => "$belowMinCount productos bajo mínimo",
				'line1' => 'Existencia mayor a 0 y menor al mínimo de la sucursal.', 'line2' => 'Revisar el cálculo de reposición.', 'owner' => 'Compras', 'date' => $now,
				'priority' => 'Media', 'status' => 'Pendiente', 'action_type' => 'navigate', 'action_label' => 'Ver reposición', 'href' => '/mrp' . $qs];
		}
		$pend = intval($this->Db()->query("SELECT COUNT(*) FROM inventory_adjustments WHERE status = 'pending_approval'" . ($branchId ? ' AND branch_id = ' . intval($branchId) : ''))->fetchColumn());
		if ($pend > 0)
		{
			$all[] = ['id' => 'DEC-ADJ', 'severity' => 'yellow', 'category_label' => 'Atención', 'icon' => 'fa-scale-balanced', 'title' => "$pend ajustes de inventario por aprobar",
				'line1' => 'Diferencias de conteo pendientes: el stock aún no se corrigió.', 'line2' => 'Aprobar o rechazar con evidencia.', 'owner' => 'Inventario', 'date' => $now,
				'priority' => 'Media', 'status' => 'Pendiente', 'action_type' => 'navigate', 'action_label' => 'Revisar ajustes', 'href' => '/reconciliation' . $qs];
		}
		if (!$all)
		{
			$all[] = ['id' => 'DEC-OK', 'severity' => 'green', 'category_label' => 'En objetivo', 'icon' => 'fa-circle-check', 'title' => 'Sin decisiones pendientes', 'line1' => 'No hay vencimientos, agotados ni ajustes pendientes con los filtros actuales.',
				'line2' => '', 'owner' => '', 'date' => $now, 'priority' => 'Baja', 'status' => 'Al día', 'action_type' => 'navigate', 'action_label' => 'Ver inventario', 'href' => '/stockoverview' . $qs];
		}
		return ['featured' => array_slice($all, 0, 3), 'all' => $all];
	}

	/** Ficha 360° de un producto con sus registros: existencias por sucursal, lotes, ventas y recepciones */
	private function GetProduct360SheetRaw(int $productId): array
	{
		$db = $this->Db();
		$p = $db->query('SELECT p.id, p.name, p.sku, p.department, p.sale_price, IFNULL(pg.name, \'\') AS group_name FROM products p LEFT JOIN product_groups pg ON pg.id = p.product_group_id WHERE p.id = ' . intval($productId))->fetch(\PDO::FETCH_ASSOC);
		if (!$p)
		{
			throw new \Exception('Producto no encontrado');
		}
		$lots = $db->query('SELECT s.stock_id, s.amount, s.best_before_date, IFNULL(s.price, 0) AS cost, l.name AS location, b.name AS branch, dl.lot_number FROM stock s JOIN locations l ON l.id = s.location_id
			LEFT JOIN branches b ON b.id = l.branch_id LEFT JOIN dairy_stock_lots dl ON dl.stock_id = s.stock_id WHERE s.product_id = ' . intval($productId) . ' AND s.amount > 0 AND IFNULL(l.is_transit, 0) = 0 ORDER BY s.best_before_date')->fetchAll(\PDO::FETCH_ASSOC);
		$units = array_sum(array_column($lots, 'amount'));
		$value = array_sum(array_map(fn($l) => $l['amount'] * $l['cost'], $lots));
		$byBranch = [];
		foreach ($lots as $l)
		{
			$k = $l['branch'];
			$byBranch[$k]['branch'] = $k;
			$byBranch[$k]['location'] = isset($byBranch[$k]['location']) && strpos($byBranch[$k]['location'], $l['location']) !== false ? $byBranch[$k]['location'] : trim(($byBranch[$k]['location'] ?? '') . ' / ' . $l['location'], ' /');
			$byBranch[$k]['units'] = ($byBranch[$k]['units'] ?? 0) + floatval($l['amount']);
			$byBranch[$k]['_v'] = ($byBranch[$k]['_v'] ?? 0) + $l['amount'] * $l['cost'];
		}
		$branchesStock = array_values(array_map(fn($b) => ['branch' => $b['branch'], 'location' => $b['location'], 'units' => round($b['units'], 0), 'value' => '$' . number_format($b['_v'], 0), 'status' => 'Registrado'], $byBranch));
		$today = date('Y-m-d');
		$activeLots = array_map(function ($l) use ($today) {
			$d = intval(floor((strtotime($l['best_before_date']) - strtotime($today)) / 86400));
			return ['lot' => $l['lot_number'] ?: substr($l['stock_id'], 0, 10), 'qty' => round(floatval($l['amount']), 2), 'expiry' => $l['best_before_date'], 'days' => $d, 'location' => $l['branch'] . ' · ' . $l['location'],
				'state' => $d < 0 ? 'Vencido' : ($d <= 3 ? 'Prioridad FEFO 1' : 'Vigente')];
		}, array_slice($lots, 0, 8));
		$sales = $db->query("SELECT s.sale_number, s.created_timestamp, si.amount, si.subtotal, b.name AS branch FROM sale_items si JOIN sales s ON s.id = si.sale_id LEFT JOIN branches b ON b.id = s.branch_id
			WHERE si.product_id = " . intval($productId) . " AND s.status = 'completed' ORDER BY s.created_timestamp DESC LIMIT 6")->fetchAll(\PDO::FETCH_ASSOC);
		$recs = $db->query("SELECT r.receipt_code, r.received_at, ri.accepted_amount, b.name AS branch, l.name AS location FROM procurement_receipt_items ri JOIN procurement_receipts r ON r.id = ri.receipt_id
			LEFT JOIN branches b ON b.id = r.branch_id LEFT JOIN locations l ON l.id = ri.location_id WHERE ri.product_id = " . intval($productId) . ' ORDER BY r.received_at DESC LIMIT 6')->fetchAll(\PDO::FETCH_ASSOC);
		$k = 'otros';
		foreach (DailyMetricsService::DEPARTMENTS as $dk => $d)
		{
			if ($d[2] !== null && in_array($p['department'], $d[2], true))
			{
				$k = $dk;
			}
		}
		return [
			'product' => ['rank' => '-', 'product_id' => intval($p['id']), 'name' => $p['name'], 'sku' => $p['sku'] ?? '', 'upc' => '', 'dept_key' => $k, 'department' => DailyMetricsService::DEPARTMENTS[$k][0],
				'image' => CustomerOrderService::GetInstance()->ResolveProductImage($p['name'], $p['group_name']), 'value' => round($value, 2), 'units' => round($units, 0), 'rotation' => 0, 'coverage_days' => 0,
				'status_label' => count($lots) ? 'Con existencias' : 'Sin existencias'],
			'branches_stock' => $branchesStock,
			'active_lots' => $activeLots,
			'recent_sales' => array_map(fn($s) => ['date' => $s['created_timestamp'], 'doc' => $s['sale_number'], 'units' => '-' . rtrim(rtrim(number_format($s['amount'], 2), '0'), '.') . ' uds', 'amount' => '$' . number_format($s['subtotal'], 2), 'branch' => $s['branch']], $sales),
			'receptions_and_returns' => array_map(fn($r) => ['type' => 'Recepción OC', 'doc' => $r['receipt_code'], 'date' => $r['received_at'], 'qty' => '+' . rtrim(rtrim(number_format($r['accepted_amount'], 2), '0'), '.') . ' uds', 'location' => $r['branch'] . ' · ' . $r['location'], 'status' => 'Registrada'], $recs)
		];
	}

	/** Producto activo por id o SKU (las pantallas envían uno u otro) */
	private function ResolveProduct(array $payload): array
	{
		$db = $this->Db();
		if (!empty($payload['product_id']))
		{
			$st = $db->prepare('SELECT * FROM products WHERE id = :v AND active = 1');
			$st->execute([':v' => intval($payload['product_id'])]);
		}
		else
		{
			$st = $db->prepare('SELECT * FROM products WHERE sku = :v AND active = 1');
			$st->execute([':v' => strval($payload['sku'] ?? '')]);
		}
		$p = $st->fetch(\PDO::FETCH_ASSOC);
		if (!$p)
		{
			throw new \Exception('Producto inexistente o inactivo');
		}
		return $p;
	}

	/** Sucursal por id o por nombre exacto */
	private function ResolveBranch(array $payload): int
	{
		$db = $this->Db();
		if (!empty($payload['branch_id']))
		{
			$id = intval($db->query('SELECT id FROM branches WHERE id = ' . intval($payload['branch_id']))->fetchColumn());
		}
		else
		{
			$st = $db->prepare('SELECT id FROM branches WHERE name = :n');
			$st->execute([':n' => trim(strval($payload['branch_name'] ?? ''))]);
			$id = intval($st->fetchColumn());
		}
		if ($id <= 0)
		{
			throw new \Exception('Seleccione una sucursal válida');
		}
		return $id;
	}

	private function BranchUnits(int $productId, int $branchId): float
	{
		return floatval($this->Db()->query("SELECT IFNULL(SUM(s.amount), 0) FROM stock s JOIN locations l ON l.id = s.location_id WHERE s.product_id = $productId AND l.branch_id = $branchId AND IFNULL(l.is_transit, 0) = 0")->fetchColumn());
	}

	private function CreateTask(string $name, string $description, int $userId): int
	{
		$this->Db()->prepare('INSERT INTO tasks (name, description, due_date, done, assigned_to_user_id) VALUES (:n, :d, :due, 0, :u)')
			->execute([':n' => $name, ':d' => $description, ':due' => date('Y-m-d'), ':u' => $userId]);
		return intval($this->Db()->lastInsertId());
	}

	/**
	 * Acción sobre un lote FEFO: cada opción ejecuta la operación real del módulo dueño (tarea, merma)
	 * o lleva al módulo cuando falta un dato que solo el usuario puede elegir (destino del traslado).
	 * Nunca informa una operación que no ocurrió.
	 */
	public function ExecuteFefoAction(array $payload, int $userId): array
	{
		$action = strval($payload['action'] ?? '');
		$p = $this->ResolveProduct($payload);
		$branchId = $this->ResolveBranch($payload);
		if (!DairyAccess::CanSeeBranch($branchId))
		{
			throw new \Exception('Sin acceso a la sucursal');
		}
		$lot = strval($payload['lot'] ?? '');
		$stockId = trim(strval($payload['stock_id'] ?? ''));
		$locationId = !empty($payload['location_id']) ? intval($payload['location_id']) : null;
		$qty = floatval($payload['qty'] ?? 0);
		$notes = trim(strval($payload['notes'] ?? ''));
		$branchName = BranchService::NameById($branchId);

		switch ($action)
		{
			case 'inspect':
				$id = $this->CreateTask("Inspección de calidad: {$p['name']} (lote {$lot})", trim("Verificar estado, temperatura y vencimiento del lote {$lot} en {$branchName}. $notes"), $userId);
				return ['success' => true, 'doc_ref' => 'TAREA-' . $id, 'summary' => "Inspección asignada como tarea #$id. El resultado se registra en Calidad & Vencimientos."];

			case 'rotate_task':
				$id = $this->CreateTask("Rotación FEFO: {$p['name']} (lote {$lot})", trim("Adelantar el lote {$lot} al frente de góndola en {$branchName} · " . $qty . " u. $notes"), $userId);
				return ['success' => true, 'doc_ref' => 'TAREA-' . $id, 'summary' => "Tarea de rotación #$id creada para piso de venta."];

			case 'transfer':
				// El destino lo elige el usuario en Traslados (salida, tránsito y recepción quedan trazados allí)
				return ['success' => true, 'doc_ref' => null, 'redirect' => '/transfers?product_id=' . intval($p['id']) . ($locationId ? '&from_location_id=' . $locationId : ''),
					'summary' => 'Elija la sucursal de destino en Traslados entre Tiendas para registrar el envío.'];

			case 'markdown':
				$pct = MarkdownService::GetInstance()->GetMaxDiscountPct(intval($p['id']));
				return ['success' => true, 'doc_ref' => null, 'summary' => $pct > 0
					? 'La rebaja automática FEFO de hasta ' . rtrim(rtrim(number_format($pct, 1), '0'), '.') . '% ya se aplica en el POS a este producto según sus días de vida.'
					: 'Este producto no tiene regla de rebaja FEFO: configúrela en las reglas de rebaja para que el POS la aplique.'];

			case 'waste':
				if ($qty <= 0)
				{
					throw new \Exception('Indique la cantidad a dar de baja');
				}
				$w = (array) WasteService::GetInstance()->ReportWaste(intval($p['id']), $qty, 33, $branchId, $locationId, $stockId !== '' ? $stockId : 'default',
					trim("Baja FEFO del lote {$lot}. $notes"), null, null, $lot ?: null, null, null, null, strval($payload['operation_id'] ?? '') ?: null, $userId);
				$wid = intval($w['waste_record_id'] ?? $w['id'] ?? 0);
				$status = $wid ? $this->Db()->query("SELECT status FROM waste_records WHERE id = $wid")->fetchColumn() : null;
				return ['success' => true, 'doc_ref' => $wid ? 'MER-' . $wid : null, 'summary' => $status === 'pending_approval'
					? "Merma registrada (#$wid) y enviada a aprobación: el stock se descuenta cuando se apruebe."
					: "Merma registrada (#$wid): stock, kardex y Control de Mermas actualizados."];
		}
		throw new \Exception('Acción FEFO no reconocida');
	}

	/**
	 * Recorrido operativo de demostración con operaciones reales: cada paso usa el servicio dueño
	 * (Conciliación para recepciones y devoluciones, POS para ventas, conteo cíclico y su aprobación)
	 * y devuelve el saldo antes y después leído del inventario.
	 */
	public function ExecuteIntegratedWorkflowStep(array $payload, int $userId): array
	{
		$role = DairyAccess::GetSimulatedRole();
		$stepType = strval($payload['step_type'] ?? '');
		if ($stepType === 'supervisor_decision' && $role === 'empleado')
		{
			throw new \Exception('Permiso denegado: El rol Empleado no puede aprobar ni rechazar ajustes financieros de inventario. Se requiere rol Supervisor o Manager.');
		}
		$p = $this->ResolveProduct($payload);
		$pid = intval($p['id']);
		$branchId = $this->ResolveBranch($payload);
		if (!DairyAccess::CanSeeBranch($branchId))
		{
			throw new \Exception('Sin acceso a la sucursal');
		}
		$qty = floatval($payload['qty'] ?? 0);
		$opId = strval($payload['operation_id'] ?? '') ?: null;
		$before = $this->BranchUnits($pid, $branchId);
		$rc = ReconciliationService::GetInstance();
		$steps = [];

		switch ($stepType)
		{
			case 'reception':
				$r = $rc->RegisterGoodsIn(['product_id' => $pid, 'branch_id' => $branchId, 'expected_qty' => $qty, 'received_qty' => $qty, 'unit_cost' => floatval($payload['unit_cost'] ?? 0), 'operation_id' => $opId], $userId);
				$doc = $r['movement_code'];
				$steps[] = "Recepción $doc: +$qty u. de {$p['name']}";
				break;

			case 'sale':
				if ($qty <= 0)
				{
					throw new \Exception('Indique la cantidad vendida');
				}
				$sale = SalesService::GetInstance()->CreateSale(['branch_id' => $branchId, 'customer_id' => 1, 'payment_method' => 'cash', 'items' => [['product_id' => $pid, 'amount' => $qty, 'unit_price' => floatval($p['sale_price'])]]], $userId);
				$doc = strval($this->Db()->query('SELECT sale_number FROM sales WHERE id = ' . intval($sale->id))->fetchColumn());
				$steps[] = "Venta $doc en el POS: −$qty u. (costo FEFO al kardex)";
				break;

			case 'return':
				$disp = ['apto' => 'disponible', 'cuarentena' => 'cuarentena', 'merma' => 'merma_definitiva'][strval($payload['return_state'] ?? 'apto')] ?? 'disponible';
				$r = $rc->RegisterReturn(['product_id' => $pid, 'branch_id' => $branchId, 'quantity' => $qty, 'disposition' => $disp, 'operation_id' => $opId], $userId);
				$doc = $r['movement_code'];
				$steps[] = "Devolución $doc con destino «{$disp}»";
				break;

			case 'cycle_count':
				// La cantidad indicada es lo contado físicamente
				$cc = CycleCountService::GetInstance();
				$cc->ScheduleForProducts($branchId, [$pid], 'Conteo desde el recorrido operativo', $userId);
				$countId = intval($this->Db()->query("SELECT id FROM stock_cycle_counts WHERE branch_id = $branchId AND product_id = $pid AND status = 'pending' ORDER BY id DESC LIMIT 1")->fetchColumn());
				if (!$countId)
				{
					throw new \Exception('No se pudo programar el conteo (sin stock en la sucursal)');
				}
				$res = $cc->Execute($countId, $qty, 'Conteo desde el recorrido operativo', $userId);
				$doc = 'CC-' . str_pad((string) $countId, 4, '0', STR_PAD_LEFT);
				$steps[] = "Conteo $doc: sistema " . $res['theoretical_stock'] . " u., contado $qty u. → " . $res['message'];
				break;

			case 'supervisor_decision':
				$countId = intval($this->Db()->query("SELECT id FROM stock_cycle_counts WHERE branch_id = $branchId AND product_id = $pid AND status = 'discrepancy' ORDER BY id DESC LIMIT 1")->fetchColumn());
				if (!$countId)
				{
					throw new \Exception('No hay conteos con diferencia pendiente para este producto y sucursal');
				}
				$doc = 'CC-' . str_pad((string) $countId, 4, '0', STR_PAD_LEFT);
				if (strval($payload['decision'] ?? 'approve') === 'approve')
				{
					$res = CycleCountService::GetInstance()->ApproveAdjustment($countId, $userId);
					$steps[] = "Ajuste $doc aprobado: corrección de " . $res['difference'] . ' u. en inventario y kardex';
				}
				else
				{
					$steps[] = "Ajuste $doc no aprobado: la diferencia queda pendiente de investigación (sin movimiento de stock)";
				}
				break;

			default:
				throw new \Exception('Paso operativo no reconocido');
		}
		$after = $this->BranchUnits($pid, $branchId);
		$steps[] = 'Saldo en ' . BranchService::NameById($branchId) . ": $before → $after u.";
		return ['success' => true, 'doc_ref' => $doc, 'stock_before' => $before, 'stock_after' => $after, 'steps_completed' => $steps];
	}

	/** Ya no hay valores de demostración que restablecer: todo lo ejecutado son movimientos reales del inventario */
	public function ResetLiveMovements(): array
	{
		return ['success' => true, 'message' => 'Las operaciones ejecutadas son movimientos reales del inventario; se revierten con su operación contraria (devolución, ajuste o traslado).'];
	}

	private function EnsureAuditTable(): void
	{
		$db = $this->Db();
		$db->exec("
			CREATE TABLE IF NOT EXISTS stock_executive_movements (
				id INTEGER PRIMARY KEY AUTOINCREMENT,
				mov_id TEXT NOT NULL,
				category_key TEXT NOT NULL,
				movement_type TEXT NOT NULL,
				product_name TEXT NOT NULL,
				sku TEXT NOT NULL,
				quantity TEXT NOT NULL,
				numeric_qty REAL DEFAULT 0,
				timestamp TEXT NOT NULL,
				branch_id INTEGER DEFAULT 1,
				branch_name TEXT NOT NULL,
				origin_location TEXT NOT NULL,
				dest_location TEXT NOT NULL,
				user TEXT NOT NULL,
				ref_doc TEXT NOT NULL,
				validation_status TEXT NOT NULL,
				badge_class TEXT DEFAULT 'success',
				val_delta REAL DEFAULT 0,
				units_delta INTEGER DEFAULT 0,
				below_min_delta INTEGER DEFAULT 0,
				expiring_delta INTEGER DEFAULT 0,
				out_of_stock_delta INTEGER DEFAULT 0,
				risk_val_delta REAL DEFAULT 0
			)
		");
	}
}

