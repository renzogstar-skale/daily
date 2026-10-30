<?php

namespace Grocy\Services;

class AkcalMetricsService extends BaseService
{
	/**
	 * Retorna la consolidación ejecutiva bajo el marco metodológico AKCAL 2015
	 * - 4 Clasificaciones: Operativa, Comercial, Ajustes, Averías
	 * - Garantía estricta de no duplicidad
	 * - Comparativa histórica con metas y desviaciones
	 */
	public function GetAkcalExecutiveMetrics(?int $branchId = null, string $period = 'month', ?string $customStart = null, ?string $customEnd = null): array
	{
		$dbRaw = DatabaseService::GetInstance()->GetDbConnectionRaw();
		$dates = $this->ResolveDateRanges($period, $customStart, $customEnd);
		$currStart = $dates['curr_start'];
		$currEnd = $dates['curr_end'];
		$prevStart = $dates['prev_start'];
		$prevEnd = $dates['prev_end'];

		$branchCondition = "";
		$branchParams = [];
		if ($branchId !== null && $branchId > 0)
		{
			$branchCondition = " AND branch_id = :branch_id";
			$branchParams[':branch_id'] = $branchId;
		}

		// 1. Ventas Netas del período actual y anterior
		$salesCurr = $this->GetSalesTotals($currStart, $currEnd, $branchId);
		$salesPrev = $this->GetSalesTotals($prevStart, $prevEnd, $branchId);

		$netSales = $salesCurr['net_sales'];
		$netSalesPrev = $salesPrev['net_sales'];

		// 2. Extracción de Mermas por Clasificación AKCAL
		$wasteCurr = $this->GetWasteByClassification($currStart, $currEnd, $branchId);
		$wastePrev = $this->GetWasteByClassification($prevStart, $prevEnd, $branchId);

		// 3. Ajustes de Inventario (stock_log transaction_type = 'inventory-correction')
		$adjustmentsCurr = $this->GetInventoryAdjustments($currStart, $currEnd, $branchId);
		$adjustmentsPrev = $this->GetInventoryAdjustments($prevStart, $prevEnd, $branchId);

		// 4. Métricas consolidadas por los 4 Pilares AKCAL
		// Metas corporativas estándar AKCAL (% sobre ventas netas):
		// Operativa: meta 1.20% | Comercial: meta 0.50% | Ajustes: meta 0.30% | Averías: meta 0.50% | Total: meta 2.50%
		$metaTargetTotal = 2.50;
		$metaTargetOperativa = 1.20;
		$metaTargetComercial = 0.50;
		$metaTargetAjustes = 0.30;
		$metaTargetAverias = 0.50;

		// PILAR 1: Merma Operativa
		$costOperativa = $wasteCurr['operativa']['cost'];
		$unitsOperativa = $wasteCurr['operativa']['units'];
		$costOperativaPrev = $wastePrev['operativa']['cost'];
		$indexOperativa = ($netSales > 0) ? round(($costOperativa / $netSales) * 100, 2) : 0.0;
		$desvOperativa = round($indexOperativa - $metaTargetOperativa, 2);
		$varOperativaPct = ($costOperativaPrev > 0) ? round((($costOperativa - $costOperativaPrev) / $costOperativaPrev) * 100, 1) : 0.0;

		// PILAR 2: Merma Comercial (Decisiones comerciales, liquidaciones, markdown FEFO)
		$costComercial = $wasteCurr['comercial']['cost'];
		$unitsComercial = $wasteCurr['comercial']['units'];
		$costComercialPrev = $wastePrev['comercial']['cost'];
		$indexComercial = ($netSales > 0) ? round(($costComercial / $netSales) * 100, 2) : 0.0;
		$desvComercial = round($indexComercial - $metaTargetComercial, 2);
		$varComercialPct = ($costComercialPrev > 0) ? round((($costComercial - $costComercialPrev) / $costComercialPrev) * 100, 1) : 0.0;

		// PILAR 3: Ajustes de Inventario (Sobrantes +, Faltantes -, Variación Neta)
		$adjPositiveCost = $adjustmentsCurr['positive_cost'];
		$adjNegativeCost = $adjustmentsCurr['negative_cost']; // siempre positivo en magnitud de pérdida
		$adjNetCost = $adjustmentsCurr['net_cost']; // positivo si sobrante neto, negativo si faltante neto
		$adjUnjustifiedWaste = $wasteCurr['ajuste']['cost']; // mermas registradas por descuadre M06
		$totalAdjImpact = max(0, $adjNegativeCost - $adjPositiveCost) + $adjUnjustifiedWaste;
		$indexAjustes = ($netSales > 0) ? round(($totalAdjImpact / $netSales) * 100, 2) : 0.0;
		$desvAjustes = round($indexAjustes - $metaTargetAjustes, 2);

		// PILAR 4: Averías (Recuperables vs. Irrecuperables)
		$costAveriasTotal = $wasteCurr['averia']['cost'];
		$unitsAverias = $wasteCurr['averia']['units'];
		$costAveriasPrev = $wastePrev['averia']['cost'];
		// Recuperación vía notas de crédito o reclamos a proveedores
		$recuperadoAverias = $wasteCurr['averia']['recovery_amount'];
		$costAveriasNeta = max(0, $costAveriasTotal - $recuperadoAverias);
		$indexAverias = ($netSales > 0) ? round(($costAveriasTotal / $netSales) * 100, 2) : 0.0;
		$desvAverias = round($indexAverias - $metaTargetAverias, 2);
		$varAveriasPct = ($costAveriasPrev > 0) ? round((($costAveriasTotal - $costAveriasPrev) / $costAveriasPrev) * 100, 1) : 0.0;

		// CONSOLIDADO TOTAL SIN DOBLE CONTEO
		// La pérdida consolidada suma Operativa + Comercial + Descuadre Neto no justificado + Avería Neta (descontando recuperos)
		$totalLossConsolidated = round($costOperativa + $costComercial + $adjUnjustifiedWaste + $costAveriasNeta, 2);
		$totalLossGross = round($costOperativa + $costComercial + $adjUnjustifiedWaste + $costAveriasTotal, 2);
		$totalRecovery = round($wasteCurr['all']['recovery_amount'], 2);

		$indexTotal = ($netSales > 0) ? round(($totalLossGross / $netSales) * 100, 2) : 0.0;
		$desvTotal = round($indexTotal - $metaTargetTotal, 2);

		$totalLossPrev = round($wastePrev['operativa']['cost'] + $wastePrev['comercial']['cost'] + $wastePrev['ajuste']['cost'] + $wastePrev['averia']['cost'], 2);
		$varTotalPct = ($totalLossPrev > 0) ? round((($totalLossGross - $totalLossPrev) / $totalLossPrev) * 100, 1) : 0.0;
		$varTotalAbs = round($totalLossGross - $totalLossPrev, 2);

		// Margen Bruto y Resultado Ajustado por Merma
		$cogs = $salesCurr['cogs'];
		$grossMargin = round($netSales - $cogs, 2);
		$grossMarginPct = ($netSales > 0) ? round(($grossMargin / $netSales) * 100, 2) : 0.0;
		$adjustedNetResult = round($grossMargin - $totalLossConsolidated, 2);
		$adjustedNetResultPct = ($netSales > 0) ? round(($adjustedNetResult / $netSales) * 100, 2) : 0.0;

		// Evolución Histórica Multiserie para Gráfico (12 meses o período dinámico)
		$historySeries = $this->GetHistoryEvolutionSeries($branchId);

		// Tabla Comparativa Histórica Estilo AKCAL (Períodos, Ventas, Merma, Índice %, Meta %, Desviación %, Variación $ y %)
		$comparisonTable = $this->GetAkcalHistoricalComparisonTable($branchId);

		return [
			'period' => $period,
			'start_date' => $currStart,
			'end_date' => $currEnd,
			'prev_start_date' => $prevStart,
			'prev_end_date' => $prevEnd,
			'financials' => [
				'net_sales' => $netSales,
				'net_sales_prev' => $netSalesPrev,
				'cogs' => $cogs,
				'gross_margin' => $grossMargin,
				'gross_margin_pct' => $grossMarginPct,
				'adjusted_result' => $adjustedNetResult,
				'adjusted_result_pct' => $adjustedNetResultPct,
				'total_loss_gross' => $totalLossGross,
				'total_loss_consolidated' => $totalLossConsolidated,
				'total_recovery' => $totalRecovery,
				'index_total' => $indexTotal,
				'target_total' => $metaTargetTotal,
				'deviation_total' => $desvTotal,
				'var_abs' => $varTotalAbs,
				'var_pct' => $varTotalPct
			],
			'pillars' => [
				'operativa' => [
					'title' => 'Merma Operativa',
					'icon' => 'fa-gear',
					'color' => 'danger',
					'cost' => $costOperativa,
					'units' => $unitsOperativa,
					'index_pct' => $indexOperativa,
					'target_pct' => $metaTargetOperativa,
					'deviation' => $desvOperativa,
					'var_pct' => $varOperativaPct
				],
				'comercial' => [
					'title' => 'Merma Comercial',
					'icon' => 'fa-badge-percent',
					'color' => 'warning',
					'cost' => $costComercial,
					'units' => $unitsComercial,
					'index_pct' => $indexComercial,
					'target_pct' => $metaTargetComercial,
					'deviation' => $desvComercial,
					'var_pct' => $varComercialPct
				],
				'ajustes' => [
					'title' => 'Ajustes de Inventario',
					'icon' => 'fa-scale-balanced',
					'color' => 'info',
					'cost' => $totalAdjImpact,
					'positive_cost' => $adjPositiveCost,
					'negative_cost' => $adjNegativeCost,
					'net_cost' => $adjNetCost,
					'unjustified_waste' => $adjUnjustifiedWaste,
					'index_pct' => $indexAjustes,
					'target_pct' => $metaTargetAjustes,
					'deviation' => $desvAjustes,
					'count' => $adjustmentsCurr['count']
				],
				'averias' => [
					'title' => 'Averías de Mercancía',
					'icon' => 'fa-box-open',
					'color' => 'secondary',
					'cost_total' => $costAveriasTotal,
					'cost_neta' => $costAveriasNeta,
					'recovered' => $recuperadoAverias,
					'units' => $unitsAverias,
					'index_pct' => $indexAverias,
					'target_pct' => $metaTargetAverias,
					'deviation' => $desvAverias,
					'var_pct' => $varAveriasPct
				]
			],
			'history_series' => $historySeries,
			'comparison_table' => $comparisonTable
		];
	}

	/**
	 * Consulta los inventarios selectivos y cíclicos en productos sensibles (Leche, Huevos, Quesos, Yogur)
	 */
	public function GetSensitiveInventoryCycleCounts(?int $branchId = null): array
	{
		$dbRaw = DatabaseService::GetInstance()->GetDbConnectionRaw();
		$sql = "
			SELECT 
				cc.*,
				p.name AS product_name,
				p.sku AS product_sku,
				qu.name AS qu_name,
				l.name AS location_name,
				b.name AS branch_name,
				u_asg.username AS assigned_username,
				u_exe.username AS executed_username
			FROM stock_cycle_counts cc
			JOIN products p ON cc.product_id = p.id
			JOIN quantity_units qu ON p.qu_id_stock = qu.id
			LEFT JOIN locations l ON cc.location_id = l.id
			JOIN branches b ON cc.branch_id = b.id
			LEFT JOIN users u_asg ON cc.assigned_user_id = u_asg.id
			LEFT JOIN users u_exe ON cc.executed_by_user_id = u_exe.id
			WHERE 1=1
		";
		$params = [];
		if ($branchId !== null && $branchId > 0)
		{
			$sql .= " AND cc.branch_id = :bid";
			$params[':bid'] = $branchId;
		}
		$sql .= " ORDER BY cc.scheduled_date DESC, cc.id DESC";

		$stmt = $dbRaw->prepare($sql);
		$stmt->execute($params);
		$counts = $stmt->fetchAll(\PDO::FETCH_ASSOC);

		// Estadísticas de Exactitud de Inventario (IRA)
		$totalAudited = count($counts);
		$completed = 0;
		$exactCounts = 0;
		$discrepancies = 0;
		$pending = 0;
		$totalDiscrepancyCost = 0.0;

		foreach ($counts as $c)
		{
			if ($c['status'] === 'completed')
			{
				$completed++;
				if (abs(floatval($c['difference_amount'])) < 0.001)
				{
					$exactCounts++;
				}
			}
			elseif (in_array($c['status'], ['discrepancy', 'adjusted'])) // un ajuste aprobado sigue contando como descuadre para el IRA
			{
				$discrepancies++;
				$totalDiscrepancyCost += abs(floatval($c['difference_cost']));
			}
			elseif ($c['status'] === 'pending')
			{
				$pending++;
			}
		}

		$evaluated = $completed + $discrepancies;
		$inventoryRecordAccuracy = ($evaluated > 0) ? round(($exactCounts / $evaluated) * 100, 1) : null; // sin conteos evaluados no hay exactitud que informar

		return [
			'counts' => $counts,
			'kpis' => [
				'total_scheduled' => $totalAudited,
				'completed' => $completed,
				'discrepancies' => $discrepancies,
				'pending' => $pending,
				'ira_percentage' => $inventoryRecordAccuracy,
				'ira_pct' => $inventoryRecordAccuracy,
				'discrepancy_cost' => round($totalDiscrepancyCost, 2)
			]
		];
	}

	/**
	 * Consulta el consolidado de devoluciones a proveedores y recuperación económica
	 */
	public function GetSupplierReturnsConsolidated(?int $branchId = null): array
	{
		$dbRaw = DatabaseService::GetInstance()->GetDbConnectionRaw();
		$sql = "
			SELECT 
				sr.*,
				sl.name AS supplier_name,
				p.name AS product_name,
				p.sku AS product_sku,
				qu.name AS qu_name,
				b.name AS branch_name
			FROM supplier_returns sr
			JOIN shopping_locations sl ON sr.supplier_id = sl.id
			JOIN products p ON sr.product_id = p.id
			JOIN quantity_units qu ON sr.qu_id = qu.id
			JOIN branches b ON sr.branch_id = b.id
			WHERE 1=1
		";
		$params = [];
		if ($branchId !== null && $branchId > 0)
		{
			$sql .= " AND sr.branch_id = :bid";
			$params[':bid'] = $branchId;
		}
		$sql .= " ORDER BY sr.created_timestamp DESC";

		$stmt = $dbRaw->prepare($sql);
		$stmt->execute($params);
		$returns = $stmt->fetchAll(\PDO::FETCH_ASSOC);

		$totalLossCost = 0.0;
		$totalRequested = 0.0;
		$totalRecovered = 0.0;
		$totalPending = 0.0;

		foreach ($returns as $r)
		{
			$totalLossCost += floatval($r['total_loss_cost']);
			$totalRequested += floatval($r['credit_requested_amount']);
			$totalRecovered += floatval($r['credit_recovered_amount']);
			if ($r['status'] === 'pending_credit')
			{
				$totalPending += floatval($r['total_loss_cost']);
			}
		}

		$recoveryRate = ($totalRequested > 0) ? round(($totalRecovered / $totalRequested) * 100, 1) : 0.0;

		return [
			'returns' => $returns,
			'kpis' => [
				'total_claims_cost' => round($totalLossCost, 2),
				'total_recovered' => round($totalRecovered, 2),
				'total_pending' => round($totalPending, 2),
				'recovery_rate_pct' => $recoveryRate,
				'cases_count' => count($returns)
			]
		];
	}

	/**
	 * Consulta la matriz de Planes de Acción AKCAL: Actividad -> Problema -> Seguimiento -> Estado
	 */
	public function GetActionPlansMatrix(?int $branchId = null, ?string $status = null): array
	{
		$dbRaw = DatabaseService::GetInstance()->GetDbConnectionRaw();
		$sql = "
			SELECT 
				ap.*,
				wr.code AS reason_code,
				wr.name AS reason_name,
				wr.category AS reason_category,
				u.username AS assigned_username,
				b.name AS branch_name
			FROM waste_action_plans ap
			LEFT JOIN waste_reasons wr ON ap.waste_reason_id = wr.id
			JOIN users u ON ap.assigned_user_id = u.id
			JOIN branches b ON ap.branch_id = b.id
			WHERE 1=1
		";
		$params = [];
		if ($branchId !== null && $branchId > 0)
		{
			$sql .= " AND ap.branch_id = :bid";
			$params[':bid'] = $branchId;
		}
		if (!empty($status) && $status !== 'all')
		{
			$sql .= " AND ap.status = :status";
			$params[':status'] = $status;
		}
		$sql .= " ORDER BY ap.due_date ASC, ap.priority DESC";

		$stmt = $dbRaw->prepare($sql);
		$stmt->execute($params);
		$plans = $stmt->fetchAll(\PDO::FETCH_ASSOC);

		$totalPlans = count($plans);
		$openCount = 0;
		$inProgressCount = 0;
		$completedCount = 0;
		$overdueCount = 0;
		$costAvoidedTotal = 0.0;
		$today = date('Y-m-d');

		foreach ($plans as &$p)
		{
			if ($p['status'] !== 'completed' && $p['due_date'] < $today)
			{
				$p['is_overdue'] = true;
				$overdueCount++;
			}
			else
			{
				$p['is_overdue'] = false;
			}

			if ($p['status'] === 'completed') $completedCount++;
			elseif ($p['status'] === 'in_progress') $inProgressCount++;
			elseif ($p['status'] === 'open') $openCount++;

			$costAvoidedTotal += floatval($p['cost_avoided_estimate'] ?? 0);
		}

		return [
			'plans' => $plans,
			'kpis' => [
				'total' => $totalPlans,
				'open' => $openCount,
				'in_progress' => $inProgressCount,
				'completed' => $completedCount,
				'overdue' => $overdueCount,
				'compliance_rate' => ($totalPlans > 0) ? round(($completedCount / $totalPlans) * 100, 1) : 100.0,
				'cost_avoided_total' => round($costAvoidedTotal, 2)
			]
		];
	}

	// ==========================================
	// Métodos Auxiliares y Cálculos Internos
	// ==========================================

	private function GetSalesTotals(string $startDate, string $endDate, ?int $branchId = null): array
	{
		$dbRaw = DatabaseService::GetInstance()->GetDbConnectionRaw();
		$sql = "
			SELECT 
				COUNT(*) AS tickets,
				(IFNULL(SUM(total_amount), 0) + IFNULL(SUM(discount_amount), 0)) AS gross_sales,
				IFNULL(SUM(discount_amount), 0) AS discounts,
				IFNULL(SUM(total_amount), 0) AS net_sales,
				IFNULL(SUM(total_cogs), 0) AS cogs
			FROM sales
			WHERE status = 'completed'
				AND date(created_timestamp) >= :sdate
				AND date(created_timestamp) <= :edate
		";
		$params = [':sdate' => $startDate, ':edate' => $endDate];
		if ($branchId !== null && $branchId > 0)
		{
			$sql .= " AND branch_id = :bid";
			$params[':bid'] = $branchId;
		}

		$stmt = $dbRaw->prepare($sql);
		$stmt->execute($params);
		$res = $stmt->fetch(\PDO::FETCH_ASSOC);

		return [
			'tickets' => intval($res['tickets'] ?? 0),
			'gross_sales' => floatval($res['gross_sales'] ?? 0),
			'discounts' => floatval($res['discounts'] ?? 0),
			'net_sales' => floatval($res['net_sales'] ?? 0),
			'cogs' => floatval($res['cogs'] ?? 0)
		];
	}

	// Desglose según el XI Censo Nacional de Mermas (FENALCO): conocida vs. desconocida y sus categorías
	public function GetCensoShrinkBreakdown(?int $branchId = null, string $period = 'month', ?string $customStart = null, ?string $customEnd = null): array
	{
		$dbRaw = DatabaseService::GetInstance()->GetDbConnectionRaw();
		$dates = $this->ResolveDateRanges($period, $customStart, $customEnd);

		$sql = "
			SELECT
				IFNULL(wr.shrink_type, 'conocida') AS shrink_type,
				IFNULL(wr.censo_category, 'otros') AS censo_category,
				COUNT(w.id) AS cases_count,
				IFNULL(SUM(w.total_cost), 0) AS cost_lost
			FROM waste_records w
			JOIN waste_reasons wr ON w.waste_reason_id = wr.id
			WHERE w.status = 'approved'
				AND date(w.created_timestamp) >= :sdate
				AND date(w.created_timestamp) <= :edate
		";
		$params = [':sdate' => $dates['curr_start'], ':edate' => $dates['curr_end']];
		if ($branchId !== null && $branchId > 0)
		{
			$sql .= " AND w.branch_id = :bid";
			$params[':bid'] = $branchId;
		}
		$sql .= " GROUP BY 1, 2";

		$stmt = $dbRaw->prepare($sql);
		$stmt->execute($params);

		$labels = [
			'vencimientos' => 'Vencimientos',
			'desperdicios' => 'Desperdicios / Averías',
			'robo_externo' => 'Robo externo',
			'robo_interno' => 'Robo interno',
			'error_administrativo' => 'Error administrativo',
			'error_proveedor' => 'Error de proveedor',
			'otros' => 'Otros'
		];
		$result = [
			'conocida' => ['cases' => 0, 'cost' => 0.0, 'categories' => []],
			'desconocida' => ['cases' => 0, 'cost' => 0.0, 'categories' => []],
			'total_cost' => 0.0
		];

		foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $r)
		{
			$type = $r['shrink_type'] === 'desconocida' ? 'desconocida' : 'conocida';
			$cat = $r['censo_category'];
			$cost = floatval($r['cost_lost']);
			$result[$type]['cases'] += intval($r['cases_count']);
			$result[$type]['cost'] += $cost;
			$result[$type]['categories'][$cat] = [
				'label' => $labels[$cat] ?? ucfirst($cat),
				'cases' => intval($r['cases_count']),
				'cost' => $cost
			];
			$result['total_cost'] += $cost;
		}

		foreach (['conocida', 'desconocida'] as $type)
		{
			$result[$type]['pct'] = $result['total_cost'] > 0 ? round($result[$type]['cost'] / $result['total_cost'] * 100, 2) : 0;
			uasort($result[$type]['categories'], fn($a, $b) => $b['cost'] <=> $a['cost']);
		}

		$netSales = $this->GetSalesTotals($dates['curr_start'], $dates['curr_end'], $branchId)['net_sales'];
		$result['net_sales'] = $netSales;
		$result['shrink_on_sales_pct'] = $netSales > 0 ? round($result['total_cost'] / $netSales * 100, 2) : 0;

		// Referencia sectorial histórica (Censo 2013, operación 2012), no es meta
		$result['benchmark'] = ['shrink_on_sales_pct' => 1.51, 'conocida_pct' => 57.24, 'desconocida_pct' => 42.76];

		return $result;
	}

	private function GetWasteByClassification(string $startDate, string $endDate, ?int $branchId = null): array
	{
		$dbRaw = DatabaseService::GetInstance()->GetDbConnectionRaw();
		$sql = "
			SELECT 
				IFNULL(wr.akcal_classification, 'operativa') AS classification,
				COUNT(w.id) AS cases_count,
				IFNULL(SUM(w.amount), 0) AS units_lost,
				IFNULL(SUM(w.total_cost), 0) AS cost_lost,
				IFNULL(SUM(w.financial_recovery_amount), 0) AS recovery_amount
			FROM waste_records w
			JOIN waste_reasons wr ON w.waste_reason_id = wr.id
			WHERE w.status = 'approved'
				AND date(w.created_timestamp) >= :sdate
				AND date(w.created_timestamp) <= :edate
		";
		$params = [':sdate' => $startDate, ':edate' => $endDate];
		if ($branchId !== null && $branchId > 0)
		{
			$sql .= " AND w.branch_id = :bid";
			$params[':bid'] = $branchId;
		}
		$sql .= " GROUP BY IFNULL(wr.akcal_classification, 'operativa')";

		$stmt = $dbRaw->prepare($sql);
		$stmt->execute($params);
		$rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

		$result = [
			'operativa' => ['cases' => 0, 'units' => 0.0, 'cost' => 0.0, 'recovery_amount' => 0.0],
			'comercial' => ['cases' => 0, 'units' => 0.0, 'cost' => 0.0, 'recovery_amount' => 0.0],
			'ajuste' => ['cases' => 0, 'units' => 0.0, 'cost' => 0.0, 'recovery_amount' => 0.0],
			'averia' => ['cases' => 0, 'units' => 0.0, 'cost' => 0.0, 'recovery_amount' => 0.0],
			'all' => ['cases' => 0, 'units' => 0.0, 'cost' => 0.0, 'recovery_amount' => 0.0]
		];

		foreach ($rows as $r)
		{
			$cls = $r['classification'];
			if (isset($result[$cls]))
			{
				$result[$cls]['cases'] = intval($r['cases_count']);
				$result[$cls]['units'] = floatval($r['units_lost']);
				$result[$cls]['cost'] = floatval($r['cost_lost']);
				$result[$cls]['recovery_amount'] = floatval($r['recovery_amount']);

				$result['all']['cases'] += intval($r['cases_count']);
				$result['all']['units'] += floatval($r['units_lost']);
				$result['all']['cost'] += floatval($r['cost_lost']);
				$result['all']['recovery_amount'] += floatval($r['recovery_amount']);
			}
		}

		return $result;
	}

	private function GetInventoryAdjustments(string $startDate, string $endDate, ?int $branchId = null): array
	{
		$dbRaw = DatabaseService::GetInstance()->GetDbConnectionRaw();
		$sql = "
			SELECT 
				COUNT(*) AS count,
				IFNULL(SUM(CASE WHEN sl.amount > 0 THEN sl.amount * IFNULL(sl.price, 0) ELSE 0 END), 0) AS positive_cost,
				IFNULL(SUM(CASE WHEN sl.amount < 0 THEN ABS(sl.amount) * IFNULL(sl.price, 0) ELSE 0 END), 0) AS negative_cost,
				IFNULL(SUM(sl.amount * IFNULL(sl.price, 0)), 0) AS net_cost
			FROM stock_log sl
			WHERE sl.transaction_type = 'inventory-correction'
				AND sl.undone = 0
				AND date(sl.row_created_timestamp) >= :sdate
				AND date(sl.row_created_timestamp) <= :edate
		";
		$params = [':sdate' => $startDate, ':edate' => $endDate];

		$stmt = $dbRaw->prepare($sql);
		$stmt->execute($params);
		$res = $stmt->fetch(\PDO::FETCH_ASSOC);

		return [
			'count' => intval($res['count'] ?? 0),
			'positive_cost' => floatval($res['positive_cost'] ?? 0),
			'negative_cost' => floatval($res['negative_cost'] ?? 0),
			'net_cost' => floatval($res['net_cost'] ?? 0)
		];
	}

	private function ResolveDateRanges(string $period, ?string $customStart = null, ?string $customEnd = null): array
	{
		$today = date('Y-m-d');

		if (!empty($customStart) && !empty($customEnd))
		{
			$currStart = $customStart;
			$currEnd = $customEnd;
			$daysDiff = max(1, intval((strtotime($currEnd) - strtotime($currStart)) / 86400));
			$prevEnd = date('Y-m-d', strtotime($currStart . ' -1 day'));
			$prevStart = date('Y-m-d', strtotime($prevEnd . " -$daysDiff days"));
			return [
				'curr_start' => $currStart,
				'curr_end' => $currEnd,
				'prev_start' => $prevStart,
				'prev_end' => $prevEnd
			];
		}

		switch ($period)
		{
			case 'day':
				$currStart = $today;
				$currEnd = $today;
				$prevStart = date('Y-m-d', strtotime('-1 day'));
				$prevEnd = $prevStart;
				break;
			case 'week':
				$currStart = date('Y-m-d', strtotime('monday this week'));
				$currEnd = date('Y-m-d', strtotime('sunday this week'));
				$prevStart = date('Y-m-d', strtotime('monday last week'));
				$prevEnd = date('Y-m-d', strtotime('sunday last week'));
				break;
			case 'year':
				$currStart = date('Y-01-01');
				$currEnd = date('Y-12-31');
				$prevStart = date((intval(date('Y')) - 1) . '-01-01');
				$prevEnd = date((intval(date('Y')) - 1) . '-12-31');
				break;
			case 'month':
			default:
				$currStart = date('Y-m-01');
				$currEnd = date('Y-m-t');
				$prevStart = date('Y-m-01', strtotime('first day of last month'));
				$prevEnd = date('Y-m-t', strtotime('last day of last month'));
				break;
		}

		return [
			'curr_start' => $currStart,
			'curr_end' => $currEnd,
			'prev_start' => $prevStart,
			'prev_end' => $prevEnd
		];
	}

	private function GetHistoryEvolutionSeries(?int $branchId = null): array
	{
		$dbRaw = DatabaseService::GetInstance()->GetDbConnectionRaw();
		$months = [];
		for ($i = 5; $i >= 0; $i--)
		{
			$m = date('Y-m', strtotime("-$i months"));
			$months[] = $m;
		}

		$labels = [];
		$operativaSeries = [];
		$comercialSeries = [];
		$averiasSeries = [];
		$targetSeries = [];

		$monthNames = ['01' => 'Ene', '02' => 'Feb', '03' => 'Mar', '04' => 'Abr', '05' => 'May', '06' => 'Jun', '07' => 'Jul', '08' => 'Ago', '09' => 'Sep', '10' => 'Oct', '11' => 'Nov', '12' => 'Dic'];

		foreach ($months as $m)
		{
			$mParts = explode('-', $m);
			$labels[] = ($monthNames[$mParts[1]] ?? $mParts[1]) . ' ' . substr($mParts[0], 2);

			$sDate = "$m-01";
			$eDate = date('Y-m-t', strtotime($sDate));

			$w = $this->GetWasteByClassification($sDate, $eDate, $branchId);
			$operativaSeries[] = round($w['operativa']['cost'], 2);
			$comercialSeries[] = round($w['comercial']['cost'], 2);
			$averiasSeries[] = round($w['averia']['cost'], 2);
			$targetSeries[] = 2.50; // Meta fija en porcentaje
		}

		return [
			'labels' => $labels,
			'operativa' => $operativaSeries,
			'comercial' => $comercialSeries,
			'averias' => $averiasSeries,
			'target' => $targetSeries
		];
	}

	private function GetAkcalHistoricalComparisonTable(?int $branchId = null): array
	{
		$today = date('Y-m-d');
		$currMonth = date('Y-m');
		$prevMonth = date('Y-m', strtotime('-1 month'));
		$prev2Month = date('Y-m', strtotime('-2 months'));

		$evalMonths = [
			['key' => $currMonth, 'label' => 'Mes Actual (' . date('M Y') . ')', 'is_in_progress' => true],
			['key' => $prevMonth, 'label' => 'Mes Anterior (' . date('M Y', strtotime('-1 month')) . ')', 'is_in_progress' => false],
			['key' => $prev2Month, 'label' => 'Hace 2 Meses (' . date('M Y', strtotime('-2 months')) . ')', 'is_in_progress' => false]
		];

		$targetIndex = 2.50;
		$rows = [];
		$lastCost = null;
		$lastDailySales = null;

		foreach ($evalMonths as $em)
		{
			$sDate = "{$em['key']}-01";
			$eDate = date('Y-m-t', strtotime($sDate));
			$totalDaysInMonth = intval(date('t', strtotime($sDate)));

			if ($em['is_in_progress'])
			{
				$daysElapsed = min($totalDaysInMonth, max(1, intval(date('d'))));
				$daysContext = "$daysElapsed de $totalDaysInMonth días";
			}
			else
			{
				$daysElapsed = $totalDaysInMonth;
				$daysContext = "$totalDaysInMonth días (completo)";
			}

			$sales = $this->GetSalesTotals($sDate, $eDate, $branchId);
			$waste = $this->GetWasteByClassification($sDate, $eDate, $branchId);

			$netSales = $sales['net_sales'];
			$totalLoss = $waste['all']['cost'];
			$wasteIndex = ($netSales > 0) ? round(($totalLoss / $netSales) * 100, 2) : 0.0;
			$deviation = round($wasteIndex - $targetIndex, 2);

			$dailySalesAvg = ($daysElapsed > 0) ? round($netSales / $daysElapsed, 2) : 0;
			$dailyWasteAvg = ($daysElapsed > 0) ? round($totalLoss / $daysElapsed, 2) : 0;

			$varAbs = ($lastCost !== null) ? round($totalLoss - $lastCost, 2) : 0.0;
			$varPct = ($lastCost !== null && $lastCost > 0) ? round((($totalLoss - $lastCost) / $lastCost) * 100, 1) : 0.0;
			$lastCost = $totalLoss;

			$dailySalesVarPct = ($lastDailySales !== null && $lastDailySales > 0) ? round((($dailySalesAvg - $lastDailySales) / $lastDailySales) * 100, 1) : 0.0;
			$lastDailySales = $dailySalesAvg;

			$rows[] = [
				'period_label' => $em['label'],
				'net_sales' => $netSales,
				'waste_cost' => $totalLoss,
				'waste_index_pct' => $wasteIndex,
				'target_pct' => $targetIndex,
				'deviation_pct' => $deviation,
				'var_abs' => $varAbs,
				'var_pct' => $varPct,
				'days_elapsed' => $daysElapsed,
				'total_days' => $totalDaysInMonth,
				'days_context' => $daysContext,
				'is_in_progress' => $em['is_in_progress'],
				'daily_sales_avg' => $dailySalesAvg,
				'daily_waste_avg' => $dailyWasteAvg,
				'daily_sales_var_pct' => $dailySalesVarPct,
				'status' => ($deviation <= 0) ? 'Favorable' : 'Desfavorable'
			];
		}

		return $rows;
	}

	// Datos reales del Centro de Control de Mermas (KPI, categorías, productos, lotes en riesgo, alertas, FEFO y serie diaria)
	public function GetControlCenterData(?int $branchId, string $startDate, string $endDate): array
	{
		$dbRaw = DatabaseService::GetInstance()->GetDbConnectionRaw();
		$today = date('Y-m-d');
		$effEnd = min($endDate, $today);
		if ($effEnd < $startDate)
		{
			$effEnd = $startDate;
		}
		$days = intval(round((strtotime($effEnd) - strtotime($startDate)) / 86400)) + 1;
		$prevStart = date('Y-m-d', strtotime($startDate . ' -1 month'));
		$prevEnd = date('Y-m-d', strtotime($prevStart . ' +' . ($days - 1) . ' days'));

		$fetchWaste = function (string $s, string $e) use ($dbRaw, $branchId) {
			$sql = "
				SELECT w.id, w.amount, w.total_cost, w.workflow_status, IFNULL(w.financial_recovery_amount, 0) AS recovered,
					date(w.created_timestamp) AS day, IFNULL(r.category, '') AS r_cat, IFNULL(r.name, '') AS r_name,
					IFNULL(r.risk_level, '') AS r_risk, IFNULL(p.name, 'Producto #' || w.product_id) AS p_name
				FROM waste_records w
				LEFT JOIN waste_reasons r ON r.id = w.waste_reason_id
				LEFT JOIN products p ON p.id = w.product_id
				WHERE w.status = 'approved' AND date(w.created_timestamp) >= :s AND date(w.created_timestamp) <= :e
			";
			$params = [':s' => $s, ':e' => $e];
			if ($branchId !== null && $branchId > 0)
			{
				$sql .= ' AND w.branch_id = :bid';
				$params[':bid'] = $branchId;
			}
			$stmt = $dbRaw->prepare($sql);
			$stmt->execute($params);
			return $stmt->fetchAll(\PDO::FETCH_ASSOC);
		};

		$rows = $fetchWaste($startDate, $effEnd);
		$prevRows = $fetchWaste($prevStart, $prevEnd);
		$prevScale = 1.0;
		if (count($prevRows) == 0)
		{
			// Sin registros en el tramo equivalente: mes anterior completo prorrateado a los días transcurridos
			$prevMonthEnd = date('Y-m-t', strtotime($prevStart));
			$prevRows = $fetchWaste($prevStart, $prevMonthEnd);
			$prevScale = $days / (intval(round((strtotime($prevMonthEnd) - strtotime($prevStart)) / 86400)) + 1);
		}
		$sum = function (array $rs, string $col) {
			return array_sum(array_map(function ($r) use ($col) { return floatval($r[$col]); }, $rs));
		};
		$var = function (float $cur, float $prev) {
			if ($prev <= 0)
			{
				return 's/ref.';
			}
			$pct = ($cur - $prev) / $prev * 100;
			return ($pct >= 0 ? '+' : '') . number_format($pct, 1) . '%';
		};

		$gross = $sum($rows, 'total_cost');
		$units = $sum($rows, 'amount');
		$recovered = min($gross, $sum($rows, 'recovered'));
		$prevGross = $sum($prevRows, 'total_cost') * $prevScale;
		$prevRecovered = min($prevGross, $sum($prevRows, 'recovered') * $prevScale);
		$recoveryRate = $gross > 0 ? round($recovered / $gross * 100, 1) : 0.0;
		$criticalRisks = ['Crítico', 'Alto', 'Alta'];
		$isOpen = function ($r) { return !in_array($r['workflow_status'], ['closed', 'rejected']); };
		$isCritical = function ($r) use ($criticalRisks, $isOpen) { return $isOpen($r) && in_array($r['r_risk'], $criticalRisks); };
		$critical = count(array_filter($rows, $isCritical));
		$prevCritical = count(array_filter($prevRows, $isCritical));

		// Categorías estándar del tablero
		$bucketDefs = [
			'Merma Operativa' => ['#0284c7', 'fa-solid fa-dolly'],
			'Merma Comercial' => ['#7c3aed', 'fa-solid fa-bag-shopping'],
			'Vencimientos' => ['#d97706', 'fa-solid fa-hourglass-end'],
			'Averías' => ['#dc2626', 'fa-solid fa-box-open'],
			'Ajustes Inventario' => ['#475569', 'fa-solid fa-scale-unbalanced'],
			'Cadena de Frío' => ['#0891b2', 'fa-solid fa-snowflake'],
			'Devoluciones' => ['#059669', 'fa-solid fa-arrow-rotate-left'],
			'Otras Causales' => ['#94a3b8', 'fa-solid fa-asterisk'],
		];
		$bucketOf = function ($r) {
			$cat = mb_strtolower($r['r_cat']);
			$name = mb_strtolower($r['r_name']);
			$has = function ($h, $needles) { foreach ($needles as $n) { if (strpos($h, $n) !== false) return true; } return false; };
			if ($has($name, ['venc', 'caduc']) || $has($cat, ['venc'])) return 'Vencimientos';
			if ($has($cat, ['frío', 'calidad'])) return 'Cadena de Frío';
			if ($has($cat, ['aver', 'despacho', 'transporte'])) return 'Averías';
			if ($has($cat, ['devol'])) return 'Devoluciones';
			if ($has($cat, ['comercial', 'garant', 'obsol', 'compras'])) return 'Merma Comercial';
			if ($has($cat, ['auditor', 'administra', 'ajuste'])) return 'Ajustes Inventario';
			if ($has($cat, ['operativ', 'piso', 'almacen'])) return 'Merma Operativa';
			return 'Otras Causales';
		};
		$bucketCost = array_fill_keys(array_keys($bucketDefs), 0.0);
		$productCost = [];
		$daily = [];
		foreach ($rows as $r)
		{
			$cost = floatval($r['total_cost']);
			$bucketCost[$bucketOf($r)] += $cost;
			$productCost[$r['p_name']] = ($productCost[$r['p_name']] ?? 0) + $cost;
			$daily[$r['day']]['gross'] = ($daily[$r['day']]['gross'] ?? 0) + $cost;
			$daily[$r['day']]['rec'] = ($daily[$r['day']]['rec'] ?? 0) + floatval($r['recovered']);
		}
		$categories = [];
		foreach ($bucketDefs as $name => $def)
		{
			$categories[] = ['name' => $name, 'cost' => round($bucketCost[$name], 2), 'pct' => $gross > 0 ? round($bucketCost[$name] / $gross * 100, 1) : 0, 'color' => $def[0], 'icon' => $def[1]];
		}

		arsort($productCost);
		$topProducts = [];
		$maxCost = max(array_merge([0.01], array_values($productCost)));
		foreach (array_slice($productCost, 0, 8, true) as $name => $cost)
		{
			$n = mb_strtolower($name);
			$ic = ['fa-solid fa-bottle-droplet', '#e0f2fe', '#0284c7'];
			if (strpos($n, 'queso') !== false) $ic = ['fa-solid fa-cheese', '#fffbeb', '#d97706'];
			elseif (strpos($n, 'yogur') !== false || strpos($n, 'kéfir') !== false) $ic = ['fa-solid fa-jar', '#f5f3ff', '#7c3aed'];
			elseif (strpos($n, 'huevo') !== false) $ic = ['fa-solid fa-egg', '#fffbeb', '#b45309'];
			elseif (strpos($n, 'crema') !== false || strpos($n, 'mantequilla') !== false) $ic = ['fa-solid fa-ice-cream', '#ecfeff', '#0891b2'];
			$topProducts[] = ['name' => $name, 'cost' => round($cost, 2), 'pct' => round($cost / $maxCost * 100, 1), 'icon' => $ic[0], 'icon_bg' => $ic[1], 'icon_color' => $ic[2]];
		}

		// Lotes en riesgo (stock real por vencer)
		$lotSql = "
			SELECT s.stock_id, s.amount, s.best_before_date, IFNULL(s.price, 0) AS price, p.name AS p_name,
				CAST(julianday(s.best_before_date) - julianday(date('now', 'localtime')) AS INTEGER) AS days_left
			FROM stock s
			JOIN products p ON p.id = s.product_id
			LEFT JOIN locations l ON l.id = s.location_id
			WHERE s.amount > 0
		";
		$lotParams = [];
		if ($branchId !== null && $branchId > 0)
		{
			$lotSql .= ' AND (l.branch_id = :bid OR l.branch_id IS NULL)';
			$lotParams[':bid'] = $branchId;
		}
		$lotSql .= ' ORDER BY s.best_before_date ASC';
		$stmt = $dbRaw->prepare($lotSql);
		$stmt->execute($lotParams);
		$lots = $stmt->fetchAll(\PDO::FETCH_ASSOC);

		$riskCost = 0.0;
		$riskUnits = 0.0;
		$expiring = [];
		$expiredLots = 0;
		$noDateLots = 0;
		foreach ($lots as $lot)
		{
			if (empty($lot['best_before_date']) || $lot['best_before_date'] >= '2999-12-31')
			{
				$noDateLots++;
				continue;
			}
			$d = intval($lot['days_left']);
			$value = floatval($lot['amount']) * floatval($lot['price']);
			if ($d < 0)
			{
				$expiredLots++;
			}
			if ($d <= 7)
			{
				$riskCost += $value;
				$riskUnits += floatval($lot['amount']);
			}
			if ($d <= 15 && count($expiring) < 5)
			{
				$expiring[] = [
					'name' => $lot['p_name'], 'batch' => 'L-' . strtoupper(substr($lot['stock_id'], -5)),
					'date' => date('d/m/Y', strtotime($lot['best_before_date'])), 'days' => $d,
					'days_color' => $d <= 3 ? 'danger' : ($d <= 7 ? 'warning' : 'success'),
					'stock' => round(floatval($lot['amount']), 1), 'risk_val' => round($value, 2),
					'risk_badge' => $d <= 3 ? 'Crítico' : ($d <= 7 ? 'Alto' : 'Medio'),
					'badge_class' => $d <= 3 ? 'danger' : 'warning',
					'action' => $d <= 1 ? 'Rebaja 50%' : ($d <= 3 ? 'Rebaja 30%' : ($d <= 7 ? 'Rebaja 15%' : 'Monitoreo'))
				];
			}
		}
		$totalLots = count($lots);
		$fefoCorrect = max(0, $totalLots - $expiredLots - $noDateLots);
		$pctOf = function ($n) use ($totalLots) { return $totalLots > 0 ? round($n / $totalLots * 100) : 0; };
		$fefo = [
			'score' => $totalLots > 0 ? $pctOf($fefoCorrect) : 100,
			'correct_pct' => $totalLots > 0 ? $pctOf($fefoCorrect) : 100,
			'incorrect_pct' => $pctOf($expiredLots),
			'unregistered_pct' => $pctOf($noDateLots),
			'verified_lots' => number_format($totalLots),
			'compliance_count' => number_format($fefoCorrect),
			'non_compliance_count' => number_format($expiredLots),
			'unregistered_count' => number_format($noDateLots)
		];

		// Alertas derivadas de datos reales
		$sales = $this->GetSalesTotals($startDate, $effEnd, $branchId);
		$ratio = $sales['net_sales'] > 0 ? $gross / $sales['net_sales'] * 100 : 0;
		$alerts = [];
		foreach (array_slice(array_values(array_filter($expiring, function ($x) { return $x['days'] <= 3; })), 0, 2) as $x)
		{
			$alerts[] = ['title' => $x['name'] . ' (' . $x['batch'] . ') vence en ' . max(0, $x['days']) . ' d', 'subtitle' => $x['stock'] . ' u. • $' . number_format($x['risk_val'], 2) . ' en riesgo', 'badge' => 'Crítico', 'badge_class' => 'danger', 'time' => 'FEFO', 'icon' => 'fa-solid fa-triangle-exclamation text-danger'];
		}
		$alerts[] = $ratio > 2.5
			? ['title' => 'Merma sobre el tope corporativo', 'subtitle' => number_format($ratio, 2) . '% s/ventas (tope 2.50%)', 'badge' => 'Alto', 'badge_class' => 'warning', 'time' => 'Período', 'icon' => 'fa-solid fa-chart-line text-warning']
			: ['title' => 'Merma dentro del tope corporativo', 'subtitle' => number_format($ratio, 2) . '% s/ventas (tope 2.50% · sector 1.51%)', 'badge' => 'OK', 'badge_class' => 'success', 'time' => 'Período', 'icon' => 'fa-solid fa-circle-check text-success'];
		$openCases = count(array_filter($rows, $isOpen));
		if ($openCases > 0)
		{
			$alerts[] = ['title' => $openCases . ' casos de merma sin cerrar', 'subtitle' => $critical . ' de riesgo alto o crítico', 'badge' => $critical > 0 ? 'Alto' : 'Medio', 'badge_class' => 'warning', 'time' => 'Workflow', 'icon' => 'fa-solid fa-folder-open text-warning'];
		}
		$inc = $dbRaw->query("SELECT COUNT(*) AS n, IFNULL(SUM(cost_impact), 0) AS c FROM supplier_incidents WHERE status IN ('open', 'claimed')")->fetch(\PDO::FETCH_ASSOC);
		if (intval($inc['n']) > 0)
		{
			$alerts[] = ['title' => intval($inc['n']) . ' reclamos abiertos a proveedores', 'subtitle' => '$' . number_format(floatval($inc['c']), 2) . ' por recuperar', 'badge' => 'Medio', 'badge_class' => 'warning', 'time' => 'Compras', 'icon' => 'fa-solid fa-truck text-warning'];
		}

		// Serie diaria del período (hasta hoy)
		$series = ['labels' => [], 'gross' => [], 'net' => [], 'recovery' => []];
		$months = ['', 'Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
		for ($i = 0; $i < $days; $i++)
		{
			$d = date('Y-m-d', strtotime($startDate . ' +' . $i . ' days'));
			$g = round($daily[$d]['gross'] ?? 0, 2);
			$rc = round(min($g, $daily[$d]['rec'] ?? 0), 2);
			$series['labels'][] = intval(substr($d, 8, 2)) . ' ' . $months[intval(substr($d, 5, 2))];
			$series['gross'][] = $g;
			$series['recovery'][] = $rc;
			$series['net'][] = round($g - $rc, 2);
		}

		return [
			'total_loss_cost' => round($gross, 2),
			'total_units' => round($units, 1),
			'total_loss_var' => $var($gross, $prevGross),
			'net_loss_cost' => round($gross - $recovered, 2),
			'net_loss_var' => $var($gross - $recovered, $prevGross - $prevRecovered),
			'recovered_amount' => round($recovered, 2),
			'recovered_cases' => count(array_filter($rows, function ($r) { return floatval($r['recovered']) > 0; })),
			'recovered_var' => $var($recovered, $prevRecovered),
			'recovery_rate' => $recoveryRate,
			'recovery_target' => 25.0,
			'risk_loss_cost' => round($riskCost, 2),
			'risk_units' => round($riskUnits, 1),
			'risk_level' => $riskCost > 300 ? 'Alto' : ($riskCost > 100 ? 'Medio' : 'Bajo'),
			'critical_cases' => $critical,
			'critical_var' => ($critical - $prevCritical >= 0 ? '+' : '') . ($critical - $prevCritical),
			'total_cases' => count($rows),
			'waste_ratio' => round($ratio, 2),
			'categories' => $categories,
			'top_products' => $topProducts,
			'expiring_products' => $expiring,
			'alerts' => $alerts,
			'fefo' => $fefo,
			'series' => $series
		];
	}
}
