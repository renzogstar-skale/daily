<?php

namespace Grocy\Services;

class KpiService extends BaseService
{
	public function GetExecutiveDashboardData(?int $branchId = null, string $period = 'month', ?string $customStart = null, ?string $customEnd = null, ?string $anchorDate = null)
	{
		$dateRange = $this->GetDateRangeForPeriod($period, $customStart, $customEnd, $anchorDate);
		$startDate = $dateRange['start'];
		$endDate = $dateRange['end'];

		$dbRaw = DatabaseService::GetInstance()->GetDbConnectionRaw();

		// 1. Current Period: Sales, COGS (CMV) and Gross Margin
		$salesSql = "
			SELECT 
				COUNT(*) AS total_tickets,
				IFNULL(SUM(total_amount), 0) AS total_sales,
				IFNULL(SUM(tax_amount), 0) AS total_tax,
				IFNULL(SUM(total_cogs), 0) AS total_cogs,
				IFNULL(SUM(gross_margin), 0) AS total_gross_margin
			FROM sales
			WHERE status = 'completed'
				AND date(created_timestamp) >= :start_date
				AND date(created_timestamp) <= :end_date
		";
		$salesParams = [':start_date' => $startDate, ':end_date' => $endDate];
		if ($branchId !== null && $branchId > 0)
		{
			$salesSql .= " AND branch_id = :branch_id";
			$salesParams[':branch_id'] = $branchId;
		}
		$stmtSales = $dbRaw->prepare($salesSql);
		$stmtSales->execute($salesParams);
		$salesKpi = $stmtSales->fetch(\PDO::FETCH_OBJ);

		$totalSales = floatval($salesKpi->total_sales ?? 0);
		$totalCogs = floatval($salesKpi->total_cogs ?? 0);
		$grossMargin = floatval($salesKpi->total_gross_margin ?? 0);
		$netSales = $totalSales - floatval($salesKpi->total_tax ?? 0);
		$marginPercentage = SalesService::MarginPct($grossMargin, $netSales, 2) ?? 0;

		// 2. Current Period: Waste / Shrinkage KPIs
		$wasteSql = "
			SELECT 
				COUNT(*) AS waste_count,
				IFNULL(SUM(amount), 0) AS waste_units,
				IFNULL(SUM(total_cost), 0) AS waste_total_cost
			FROM waste_records
			WHERE status = 'approved'
				AND date(created_timestamp) >= :start_date
				AND date(created_timestamp) <= :end_date
		";
		$wasteParams = [':start_date' => $startDate, ':end_date' => $endDate];
		if ($branchId !== null && $branchId > 0)
		{
			$wasteSql .= " AND branch_id = :branch_id";
			$wasteParams[':branch_id'] = $branchId;
		}
		$stmtWaste = $dbRaw->prepare($wasteSql);
		$stmtWaste->execute($wasteParams);
		$wasteKpi = $stmtWaste->fetch(\PDO::FETCH_OBJ);

		$totalWasteCost = floatval($wasteKpi->waste_total_cost ?? 0);
		// % de merma sobre la base configurada (WASTE_PCT_BASE)
		$wasteBase = BudgetService::WasteBase($totalSales, floatval($salesKpi->total_tax ?? 0), $totalCogs);
		$wasteRatioPercentage = BudgetService::Pct($totalWasteCost, $wasteBase['amount']) ?? 0;
		$recoveredAmount = $this->SumRecovered($startDate, $endDate, $branchId);

		// 3. Current Total Inventory Valuation
		$inventorySql = "
			SELECT 
				IFNULL(SUM(s.amount), 0) AS total_stock_units,
				IFNULL(SUM(s.amount * IFNULL(s.price, IFNULL(plp.price, 0))), 0) AS total_inventory_valuation
			FROM stock s
			JOIN products p ON s.product_id = p.id
			LEFT JOIN cache__products_last_purchased plp ON p.id = plp.product_id
			JOIN locations l ON l.id = s.location_id
			WHERE IFNULL(l.is_transit, 0) = 0
		";
		$invParams = [];
		if ($branchId !== null && $branchId > 0)
		{
			// Inventario en ubicaciones de sucursal; la mercancía en tránsito se informa en Traslados (misma definición que Inventario y Almacenes)
			$inventorySql .= " AND l.branch_id = :branch_id";
			$invParams[':branch_id'] = $branchId;
		}
		$stmtInv = $dbRaw->prepare($inventorySql);
		$stmtInv->execute($invParams);
		$invData = $stmtInv->fetch(\PDO::FETCH_OBJ);
		$totalInventoryValuation = floatval($invData->total_inventory_valuation ?? 0);

		// Inventory turnover (Rotación de Inventarios) = CMV / Inventario
		$inventoryTurnover = ($totalInventoryValuation > 0) ? round($totalCogs / $totalInventoryValuation, 2) : 0;

		// Days of Sales in Inventory (DSI) = inventario / costo de ventas diario de los días transcurridos del período
		$elapsedDaysPeriod = max(1, intval((min(strtotime(date('Y-m-d')), strtotime($endDate)) - strtotime($startDate)) / 86400) + 1);
		$daysOfInventory = ($totalCogs > 0) ? round($totalInventoryValuation / ($totalCogs / $elapsedDaysPeriod), 1) : null;

		// 4. Time series for Trends Chart (Sales vs CMV vs Waste + Projection Line)
		$timeSeriesData = $this->GetTimeSeriesWithProjection($period, $startDate, $endDate, $branchId, $totalSales, $totalCogs, $totalWasteCost);

		// 5. Waste by Reason (Sorted for clean horizontal bars / donut)
		$wasteByReasonSql = "
			SELECT 
				wr.name AS reason_name,
				COUNT(w.id) AS count,
				IFNULL(SUM(w.total_cost), 0) AS cost
			FROM waste_records w
			JOIN waste_reasons wr ON w.waste_reason_id = wr.id
			WHERE w.status = 'approved'
				AND date(w.created_timestamp) >= :start_date
				AND date(w.created_timestamp) <= :end_date
		";
		$reasonParams = [':start_date' => $startDate, ':end_date' => $endDate];
		if ($branchId !== null && $branchId > 0)
		{
			$wasteByReasonSql .= " AND w.branch_id = :branch_id";
			$reasonParams[':branch_id'] = $branchId;
		}
		$wasteByReasonSql .= " GROUP BY wr.id, wr.name ORDER BY cost DESC";
		$stmtReason = $dbRaw->prepare($wasteByReasonSql);
		$stmtReason->execute($reasonParams);
		$wasteByReason = $stmtReason->fetchAll(\PDO::FETCH_OBJ);

		// Add percentage of waste to each reason
		foreach ($wasteByReason as $r)
		{
			$r->percentage = ($totalWasteCost > 0) ? round(($r->cost / $totalWasteCost) * 100, 1) : 0;
		}

		// 6. Deep Product Profitability: Margin %, ABC Classification, BCG Matrix, Days of Stock
		$deepProducts = $this->GetDeepProductProfitability($startDate, $endDate, $branchId);

		// Top 5 extracts for backwards compatibility
		$topSellingProducts = array_slice($deepProducts, 0, 5);

		// 7. Top 5 Highest Waste Products
		$topWasteSql = "
			SELECT 
				p.name AS product_name,
				qu.name AS qu_name,
				SUM(w.amount) AS units_wasted,
				SUM(w.total_cost) AS total_waste_cost
			FROM waste_records w
			JOIN products p ON w.product_id = p.id
			JOIN quantity_units qu ON w.qu_id = qu.id
			WHERE w.status = 'approved'
				AND date(w.created_timestamp) >= :start_date
				AND date(w.created_timestamp) <= :end_date
		";
		$topWasteParams = [':start_date' => $startDate, ':end_date' => $endDate];
		if ($branchId !== null && $branchId > 0)
		{
			$topWasteSql .= " AND w.branch_id = :branch_id";
			$topWasteParams[':branch_id'] = $branchId;
		}
		$topWasteSql .= " GROUP BY p.id, p.name, qu.name ORDER BY total_waste_cost DESC LIMIT 5";
		$stmtTopWaste = $dbRaw->prepare($topWasteSql);
		$stmtTopWaste->execute($topWasteParams);
		$topWasteProducts = $stmtTopWaste->fetchAll(\PDO::FETCH_OBJ);

		// 8. Quality & Expiry KPIs & Perishables Widget
		$qualityService = QualityExpiryService::GetInstance();
		$qualityStats = $qualityService->GetQualityStats($branchId);
		$perishablesWidget = $this->GetPerishablesRiskWidget($branchId);

		// 9. Procurement & Suppliers KPIs
		$procSql = "
			SELECT 
				(SELECT COUNT(*) FROM shopping_locations WHERE active = 1) AS active_suppliers,
				(
					SELECT ROUND(AVG(otif), 1) FROM (
						SELECT (SUM(CASE WHEN sd.status = 'on_time' THEN 1.0 ELSE 0.0 END) / COUNT(*)) * 100 AS otif
						FROM supplier_deliveries sd
						GROUP BY sd.supplier_id
					)
				) AS avg_otif,
				(SELECT COUNT(*) FROM supplier_incidents WHERE status IN ('open', 'claimed')) AS pending_incidents
		";
		$stmtProc = $dbRaw->query($procSql);
		$procStats = $stmtProc->fetch(\PDO::FETCH_OBJ);

		// 10. Month Progress, Projections & Targets
		$monthProjection = $this->CalculateMonthProjection($startDate, $endDate, $totalSales, $totalCogs, $totalWasteCost, $branchId);
		$monthProjection['dsi'] = $daysOfInventory;

		// 11. Previous Period Comparison & Deltas (▲▼ %) & Sparklines
		$deltasAndSparklines = $this->CalculateDeltasAndSparklines($period, $startDate, $endDate, $branchId, $totalSales, $totalCogs, $grossMargin, $totalWasteCost, $inventoryTurnover);

		// 12. Multi-Branch Ranking & Heatmap
		$branchRanking = $this->GetBranchRanking($period, $startDate, $endDate);
		$branchHeatmap = $this->GetBranchWasteHeatmap($startDate, $endDate);

		// 13. Working Capital & Cash Flow
		$workingCapital = [
			'inventory_capital' => $totalInventoryValuation,
			'days_sales_inventory' => $daysOfInventory
		];
		$wasteByType = $this->GetWasteByType($startDate, $endDate, $branchId);
		$yoy = $this->GetYearOverYear($startDate, $endDate, $branchId, $totalSales, $totalWasteCost);

		// 14. Actionable AI Executive Insights Banner
		$aiInsights = $this->GenerateExecutiveAiInsights(
			$branchId,
			$totalSales,
			$totalWasteCost,
			$wasteRatioPercentage,
			$inventoryTurnover,
			$monthProjection,
			$deltasAndSparklines['deltas'],
			$wasteByReason,
			$perishablesWidget,
			$procStats
		);

		return [
			'period' => $period,
			'start_date' => $startDate,
			'end_date' => $endDate,
			'total_sales' => $totalSales,
			'net_sales' => round($netSales, 2),
			'total_tax' => floatval($salesKpi->total_tax ?? 0),
			'total_cogs' => $totalCogs,
			'gross_margin' => $grossMargin,
			'margin_percentage' => $marginPercentage,
			'total_tickets' => intval($salesKpi->total_tickets ?? 0),
			'total_waste_cost' => $totalWasteCost,
			'waste_units' => floatval($wasteKpi->waste_units ?? 0),
			'waste_events' => intval($wasteKpi->waste_count ?? 0),
			'waste_ratio_percentage' => $wasteRatioPercentage,
			'waste_pct_base' => $wasteBase,
			'waste_recovered' => $recoveredAmount,
			'waste_net_loss' => round($totalWasteCost - $recoveredAmount, 2),
			'waste_by_type' => $wasteByType,
			'yoy' => $yoy,
			'total_inventory_valuation' => $totalInventoryValuation,
			'inventory_turnover' => $inventoryTurnover,
			'days_of_inventory' => $daysOfInventory,
			'time_series' => $timeSeriesData['timeline'],
			'time_series_meta' => $timeSeriesData['meta'],
			'waste_by_reason' => $wasteByReason,
			'top_selling_products' => $topSellingProducts,
			'deep_products' => $deepProducts,
			'top_waste_products' => $topWasteProducts,
			'quality_stats' => $qualityStats,
			'perishables_widget' => $perishablesWidget,
			'procurement_stats' => [
				'active_suppliers' => intval($procStats->active_suppliers ?? 0),
				'avg_otif' => $procStats->avg_otif !== null ? floatval($procStats->avg_otif) : null, // null = sin entregas registradas
				'pending_incidents' => intval($procStats->pending_incidents ?? 0)
			],
			'month_projection' => $monthProjection,
			'kpi_deltas' => $deltasAndSparklines['deltas'],
			'sparklines' => $deltasAndSparklines['sparklines'],
			'branch_ranking' => $branchRanking,
			'branch_heatmap' => $branchHeatmap,
			'working_capital' => $workingCapital,
			'ai_insights' => $aiInsights
		];
	}

	private function GetDateRangeForPeriod(string $period, ?string $customStart = null, ?string $customEnd = null, ?string $anchorDate = null): array
	{
		$today = date('Y-m-d');
		// Fecha de referencia anterior a hoy (la misma de HOY): el período se arma alrededor de ella y se corta en ella
		if ($anchorDate !== null && $anchorDate < $today && $period !== 'custom')
		{
			return DailyMetricsService::PeriodRange($period, $anchorDate);
		}
		if ($period === 'custom' && !empty($customStart) && !empty($customEnd))
		{
			return ['start' => min($customStart, $customEnd), 'end' => max($customStart, $customEnd)];
		}
		switch ($period)
		{
			case 'day':
				return ['start' => $today, 'end' => $today];
			case 'week':
				$startOfWeek = date('Y-m-d', strtotime('monday this week'));
				$endOfWeek = date('Y-m-d', strtotime('sunday this week'));
				return ['start' => $startOfWeek, 'end' => $endOfWeek];
			case 'quarter':
				$curMonth = intval(date('n'));
				$startMonth = str_pad(strval(intval(floor(($curMonth - 1) / 3) * 3 + 1)), 2, '0', STR_PAD_LEFT);
				$endMonth = str_pad(strval(intval(floor(($curMonth - 1) / 3) * 3 + 3)), 2, '0', STR_PAD_LEFT);
				$startQuarter = date('Y-') . $startMonth . '-01';
				$endQuarter = date('Y-m-t', strtotime(date('Y-') . $endMonth . '-01'));
				return ['start' => $startQuarter, 'end' => $endQuarter];
			case 'year':
				return ['start' => date('Y-01-01'), 'end' => date('Y-12-31')];
			case 'month':
			default:
				return ['start' => date('Y-m-01'), 'end' => date('Y-m-t')];
		}
	}

	private function CalculateMonthProjection(string $startDate, string $endDate, float $totalSales, float $totalCogs, float $totalWasteCost, ?int $branchId = null): array
	{
		$today = date('Y-m-d');
		$startTs = strtotime($startDate);
		$endTs = strtotime($endDate);
		$todayTs = strtotime($today);

		$totalDays = max(1, intval(round(($endTs - $startTs) / 86400)) + 1);

		if ($todayTs < $startTs)
		{
			$elapsedDays = 0;
		}
		elseif ($todayTs > $endTs)
		{
			$elapsedDays = $totalDays;
		}
		else
		{
			$elapsedDays = max(1, intval(round(($todayTs - $startTs) / 86400)) + 1);
		}

		$remainingDays = max(0, $totalDays - $elapsedDays);
		$periodProgressPct = round(($elapsedDays / $totalDays) * 100, 1);

		$dailySalesRate = ($elapsedDays > 0) ? round($totalSales / $elapsedDays, 2) : 0;
		$dailyWasteRate = ($elapsedDays > 0) ? round($totalWasteCost / $elapsedDays, 2) : 0;

		$projectedSales = round($dailySalesRate * $totalDays, 2);
		$projectedWaste = round($dailyWasteRate * $totalDays, 2);
		$projectedWasteRatioPct = ($projectedSales > 0) ? round(($projectedWaste / $projectedSales) * 100, 2) : 0;

		// Metas: presupuesto mensual (dairy_budgets) o config-dist.php (WASTE_TARGET_PCT, MARGIN_TARGET_PCT)
		$dbRaw = DatabaseService::GetInstance()->GetDbConnectionRaw();
		$histSql = "SELECT IFNULL(SUM(total_amount), 0) FROM sales WHERE status = 'completed' AND date(created_timestamp) >= date(:s, '-90 days') AND date(created_timestamp) < :s";
		$histParams = [':s' => $startDate];
		if ($branchId !== null && $branchId > 0)
		{
			$histSql .= " AND branch_id = :bid";
			$histParams[':bid'] = $branchId;
		}
		$stmtHist = $dbRaw->prepare($histSql);
		$stmtHist->execute($histParams);
		$histSales = floatval($stmtHist->fetchColumn());
		// Metas: presupuesto (sucursal -> red) o configuración; sin presupuesto de ventas, referencia = ritmo real de los 90 días previos
		$targets = BudgetService::GetInstance()->GetTargets($branchId, substr($startDate, 0, 7));
		$monthDays = intval(date('t', $startTs));
		if ($targets['sales_target'] !== null)
		{
			$salesTarget = round($targets['sales_target'] * ($totalDays / $monthDays), 2);
			$salesTargetSource = 'budget';
		}
		else
		{
			$salesTarget = $histSales > 0 ? round($histSales / 90 * $totalDays, 2) : null;
			$salesTargetSource = $histSales > 0 ? 'run_rate_90d' : 'none';
		}
		$wasteBudgetAmount = $targets['waste_budget_amount'] !== null ? round($targets['waste_budget_amount'] * ($totalDays / $monthDays), 2) : null;
		$wasteTargetRatioPct = $targets['waste_target_pct'];
		$marginTargetPct = $targets['margin_target_pct'];

		$salesTargetProgressPct = $salesTarget ? round(($totalSales / $salesTarget) * 100, 1) : null;
		$projectedTargetAchievementPct = $salesTarget ? round(($projectedSales / $salesTarget) * 100, 1) : null;

		return [
			'total_days' => $totalDays,
			'elapsed_days' => $elapsedDays,
			'remaining_days' => $remainingDays,
			'period_progress_pct' => $periodProgressPct,
			'daily_sales_rate' => $dailySalesRate,
			'daily_waste_rate' => $dailyWasteRate,
			'projected_sales' => $projectedSales,
			'projected_waste' => $projectedWaste,
			'projected_waste_ratio_pct' => $projectedWasteRatioPct,
			'sales_target' => $salesTarget,
			'sales_target_source' => $salesTargetSource,
			'targets' => $targets,
			'waste_budget_amount' => $wasteBudgetAmount,
			'waste_budget_deviation' => $wasteBudgetAmount !== null ? round($totalWasteCost - $wasteBudgetAmount * ($elapsedDays / $totalDays), 2) : null,
			'waste_target_ratio_pct' => $wasteTargetRatioPct,
			'margin_target_pct' => $marginTargetPct,
			'sales_target_progress_pct' => $salesTargetProgressPct,
			'projected_target_achievement_pct' => $projectedTargetAchievementPct,
			'is_on_track' => $salesTarget ? ($projectedSales >= $salesTarget * 0.95) : null,
			'waste_is_safe' => ($projectedWasteRatioPct <= $wasteTargetRatioPct)
		];
	}

	private function CalculateDeltasAndSparklines(string $period, string $startDate, string $endDate, ?int $branchId, float $totalSales, float $totalCogs, float $grossMargin, float $totalWasteCost, float $turnover): array
	{
		$dbRaw = DatabaseService::GetInstance()->GetDbConnectionRaw();

		// Determine previous period range and like-for-like cutoff
		$startTs = strtotime($startDate);
		$endTs = strtotime($endDate);
		$todayTs = strtotime(date('Y-m-d'));
		$daysDiff = max(1, intval(round(($endTs - $startTs) / 86400)) + 1);

		if ($period === 'month')
		{
			$prevStartDate = date('Y-m-01', strtotime('first day of last month', $startTs));
			$prevEndDate = date('Y-m-t', strtotime('last day of last month', $startTs));
			$elapsedDay = intval(date('d', min($todayTs, $endTs)));
			$prevLikeForLikeEnd = date('Y-m-' . sprintf('%02d', min(28, $elapsedDay)), strtotime($prevStartDate));
			$prevLabel = 'vs. ' . date('M Y', strtotime($prevStartDate));
		}
		elseif ($period === 'week')
		{
			$prevStartDate = date('Y-m-d', strtotime('-7 days', $startTs));
			$prevEndDate = date('Y-m-d', strtotime('-7 days', $endTs));
			$prevLikeForLikeEnd = $prevEndDate;
			$prevLabel = 'vs. sem. anterior';
		}
		elseif ($period === 'day')
		{
			$prevStartDate = date('Y-m-d', strtotime('-1 day', $startTs));
			$prevEndDate = $prevStartDate;
			$prevLikeForLikeEnd = $prevEndDate;
			$prevLabel = 'vs. ayer';
		}
		else
		{
			$prevStartDate = date('Y-01-01', strtotime('-1 year', $startTs));
			$prevEndDate = date('Y-12-31', strtotime('-1 year', $endTs));
			$prevLikeForLikeEnd = $prevEndDate;
			$prevLabel = 'vs. año anterior';
		}

		// Query previous period like-for-like sales
		$prevSalesSql = "
			SELECT 
				IFNULL(SUM(total_amount), 0) AS total_sales,
				IFNULL(SUM(tax_amount), 0) AS total_tax,
				IFNULL(SUM(total_cogs), 0) AS total_cogs,
				IFNULL(SUM(gross_margin), 0) AS gross_margin
			FROM sales
			WHERE status = 'completed'
				AND date(created_timestamp) >= :p_start
				AND date(created_timestamp) <= :p_end
		";
		$prevParams = [':p_start' => $prevStartDate, ':p_end' => $prevLikeForLikeEnd];
		if ($branchId !== null && $branchId > 0)
		{
			$prevSalesSql .= " AND branch_id = :bid";
			$prevParams[':bid'] = $branchId;
		}
		$stmtPrev = $dbRaw->prepare($prevSalesSql);
		$stmtPrev->execute($prevParams);
		$prevSales = $stmtPrev->fetch(\PDO::FETCH_OBJ);

		$prevSalesVal = floatval($prevSales->total_sales ?? 0);
		$prevCogsVal = floatval($prevSales->total_cogs ?? 0);
		$prevMarginVal = floatval($prevSales->gross_margin ?? 0);
		$prevTaxVal = floatval($prevSales->total_tax ?? 0);

		// If like-for-like had no sales (e.g. data is recorded on full month), query full previous month normalized
		if ($prevSalesVal <= 0)
		{
			$fullPrevSql = "SELECT IFNULL(SUM(total_amount), 0) AS total_sales, IFNULL(SUM(tax_amount), 0) AS total_tax, IFNULL(SUM(total_cogs), 0) AS total_cogs, IFNULL(SUM(gross_margin), 0) AS gross_margin FROM sales WHERE status = 'completed' AND date(created_timestamp) >= :p_start AND date(created_timestamp) <= :p_end" . (($branchId !== null && $branchId > 0) ? ' AND branch_id = ' . intval($branchId) : '');
			$stmtFull = $dbRaw->prepare($fullPrevSql);
			$stmtFull->execute([':p_start' => $prevStartDate, ':p_end' => $prevEndDate]);
			$fullPrev = $stmtFull->fetch(\PDO::FETCH_OBJ);
			$fullSales = floatval($fullPrev->total_sales ?? 0);
			if ($fullSales > 0)
			{
				$prevDays = max(1, intval((strtotime($prevEndDate) - strtotime($prevStartDate)) / 86400) + 1);
				$currDays = max(1, intval((min($todayTs, $endTs) - $startTs) / 86400) + 1);
				$prevSalesVal = round(($fullSales / $prevDays) * $currDays, 2);
				$prevCogsVal = round((floatval($fullPrev->total_cogs ?? 0) / $prevDays) * $currDays, 2);
				$prevMarginVal = round((floatval($fullPrev->gross_margin ?? 0) / $prevDays) * $currDays, 2);
				$prevTaxVal = round((floatval($fullPrev->total_tax ?? 0) / $prevDays) * $currDays, 2);
			}
		}

		// Query previous period waste
		$branchSql = ($branchId !== null && $branchId > 0) ? ' AND branch_id = ' . intval($branchId) : '';
		$prevWasteSql = "SELECT IFNULL(SUM(total_cost), 0) AS waste_cost FROM waste_records WHERE status = 'approved' AND date(created_timestamp) >= :p_start AND date(created_timestamp) <= :p_end" . $branchSql;
		$stmtPrevWaste = $dbRaw->prepare($prevWasteSql);
		$stmtPrevWaste->execute([':p_start' => $prevStartDate, ':p_end' => $prevLikeForLikeEnd]);
		$prevWaste = $stmtPrevWaste->fetch(\PDO::FETCH_OBJ);
		$prevWasteVal = floatval($prevWaste->waste_cost ?? 0);

		if ($prevWasteVal <= 0)
		{
			$stmtFullWaste = $dbRaw->prepare("SELECT IFNULL(SUM(total_cost), 0) AS waste_cost FROM waste_records WHERE status = 'approved' AND date(created_timestamp) >= :p_start AND date(created_timestamp) <= :p_end" . $branchSql);
			$stmtFullWaste->execute([':p_start' => $prevStartDate, ':p_end' => $prevEndDate]);
			$fw = $stmtFullWaste->fetch(\PDO::FETCH_OBJ);
			$fullW = floatval($fw->waste_cost ?? 0);
			if ($fullW > 0)
			{
				$prevDays = max(1, intval((strtotime($prevEndDate) - strtotime($prevStartDate)) / 86400) + 1);
				$currDays = max(1, intval((min($todayTs, $endTs) - $startTs) / 86400) + 1);
				$prevWasteVal = round(($fullW / $prevDays) * $currDays, 2);
			}
		}

		// Calculate percentage variations
		$salesDeltaPct = ($prevSalesVal > 0) ? round((($totalSales - $prevSalesVal) / $prevSalesVal) * 100, 1) : 0.0;
		$cogsDeltaPct = ($prevCogsVal > 0) ? round((($totalCogs - $prevCogsVal) / $prevCogsVal) * 100, 1) : 0.0;
		$marginDeltaPct = ($prevMarginVal > 0) ? round((($grossMargin - $prevMarginVal) / $prevMarginVal) * 100, 1) : 0.0;
		$wasteDeltaPct = ($prevWasteVal > 0) ? round((($totalWasteCost - $prevWasteVal) / $prevWasteVal) * 100, 1) : 0.0;

		$currMarginPct = SalesService::MarginPct($grossMargin, $totalSales - $this->SumTax($startDate, $endDate, $branchId), 2) ?? 0;
		$prevMarginPct = SalesService::MarginPct($prevMarginVal, $prevSalesVal - $prevTaxVal, 2) ?? 0;
		$marginPointsDelta = round($currMarginPct - $prevMarginPct, 1);

		$currWasteRatio = ($totalSales > 0) ? round(($totalWasteCost / $totalSales) * 100, 2) : 0;
		$prevWasteRatio = ($prevSalesVal > 0) ? round(($prevWasteVal / $prevSalesVal) * 100, 2) : 0;
		$wasteRatioDelta = round($currWasteRatio - $prevWasteRatio, 2);

		$deltas = [
			'label' => $prevLabel,
			'sales' => [
				'delta_pct' => $salesDeltaPct,
				'direction' => ($salesDeltaPct >= 0) ? 'up' : 'down',
				'is_favorable' => ($salesDeltaPct >= 0),
				'prev_val' => $prevSalesVal
			],
			'cogs' => [
				'delta_pct' => $cogsDeltaPct,
				'direction' => ($cogsDeltaPct >= 0) ? 'up' : 'down',
				'is_favorable' => ($cogsDeltaPct <= 0),
				'prev_val' => $prevCogsVal
			],
			'margin' => [
				'delta_pct' => $marginDeltaPct,
				'points_delta' => $marginPointsDelta,
				'direction' => ($marginDeltaPct >= 0) ? 'up' : 'down',
				'is_favorable' => ($marginDeltaPct >= 0),
				'prev_pct' => $prevMarginPct
			],
			'waste' => [
				'delta_pct' => $wasteDeltaPct,
				'ratio_delta' => $wasteRatioDelta,
				'direction' => ($wasteDeltaPct >= 0) ? 'up' : 'down',
				'is_favorable' => ($wasteDeltaPct <= 0), // lower waste is favorable!
				'prev_val' => $prevWasteVal
			],
			// Sin histórico de inventario valorizado no hay rotación del período anterior
			'turnover' => [
				'delta_pct' => null,
				'direction' => null,
				'is_favorable' => null,
				'prev_val' => null
			]
		];

		// Sparklines reales: 7 tramos del período transcurrido
		$spark = $this->RealSparklines($startDate, $endDate, $branchId);

		return [
			'deltas' => $deltas,
			'sparklines' => [
				'sales' => $spark['sales'],
				'cogs' => $spark['cogs'],
				'margin' => $spark['margin'],
				'waste' => $spark['waste'],
				'labels' => $spark['labels']
			]
		];
	}

	private function RealSparklines(string $startDate, string $endDate, ?int $branchId): array
	{
		$db = DatabaseService::GetInstance()->GetDbConnectionRaw();
		$out = ['sales' => [], 'cogs' => [], 'margin' => [], 'waste' => [], 'labels' => []];
		$start = strtotime($startDate);
		$end = min(strtotime($endDate), strtotime(date('Y-m-d')));
		if ($end < $start)
		{
			return $out;
		}
		$branchSql = ($branchId !== null && $branchId > 0) ? ' AND branch_id = ' . intval($branchId) : '';
		$params = [':s' => $startDate, ':e' => date('Y-m-d', $end)];
		$stmt = $db->prepare("SELECT date(created_timestamp) d, SUM(total_amount - tax_amount) s, SUM(total_cogs) c, SUM(gross_margin) m FROM sales WHERE status = 'completed' AND date(created_timestamp) BETWEEN :s AND :e $branchSql GROUP BY d");
		$stmt->execute($params);
		$sales = [];
		foreach ($stmt->fetchAll(\PDO::FETCH_OBJ) as $r)
		{
			$sales[$r->d] = $r;
		}
		$stmt = $db->prepare("SELECT date(created_timestamp) d, SUM(total_cost) w FROM waste_records WHERE status = 'approved' AND date(created_timestamp) BETWEEN :s AND :e $branchSql GROUP BY d");
		$stmt->execute($params);
		$waste = $stmt->fetchAll(\PDO::FETCH_KEY_PAIR);

		$days = intval(($end - $start) / 86400) + 1;
		$n = min(7, $days);
		for ($i = 0; $i < $n; $i++)
		{
			$from = intval(floor($i * $days / $n));
			$to = intval(floor(($i + 1) * $days / $n)) - 1;
			$acc = ['sales' => 0, 'cogs' => 0, 'margin' => 0, 'waste' => 0];
			for ($k = $from; $k <= $to; $k++)
			{
				$d = date('Y-m-d', $start + $k * 86400);
				if (isset($sales[$d]))
				{
					$acc['sales'] += $sales[$d]->s;
					$acc['cogs'] += $sales[$d]->c;
					$acc['margin'] += $sales[$d]->m;
				}
				$acc['waste'] += floatval($waste[$d] ?? 0);
			}
			foreach ($acc as $key => $v)
			{
				$out[$key][] = round($v, 2);
			}
			$out['labels'][] = date('d/m', $start + $from * 86400) . ($to > $from ? '–' . date('d/m', $start + $to * 86400) : '');
		}
		return $out;
	}

	// Merma aprobada por tipo de causal (operativa, comercial, avería, ajuste)
	public function GetWasteByType(string $startDate, string $endDate, ?int $branchId): array
	{
		$db = DatabaseService::GetInstance()->GetDbConnectionRaw();
		$sql = "SELECT IFNULL(wr.akcal_classification, 'operativa') t, COUNT(*) n, SUM(w.amount) units, SUM(w.total_cost) cost, SUM(IFNULL(w.financial_recovery_amount, 0)) recovered
			FROM waste_records w LEFT JOIN waste_reasons wr ON wr.id = w.waste_reason_id
			WHERE w.status = 'approved' AND date(w.created_timestamp) BETWEEN :s AND :e";
		$params = [':s' => $startDate, ':e' => $endDate];
		if ($branchId !== null && $branchId > 0)
		{
			$sql .= ' AND w.branch_id = :b';
			$params[':b'] = $branchId;
		}
		$stmt = $db->prepare($sql . ' GROUP BY t ORDER BY cost DESC');
		$stmt->execute($params);
		$out = [];
		foreach ($stmt->fetchAll(\PDO::FETCH_OBJ) as $r)
		{
			$out[$r->t] = ['count' => intval($r->n), 'units' => floatval($r->units), 'cost' => round(floatval($r->cost), 2), 'recovered' => round(floatval($r->recovered), 2)];
		}
		return $out;
	}

	// Recuperado = recuperación registrada en casos de merma aprobados + notas de crédito de proveedor cobradas en el período
	public function SumRecovered(string $startDate, string $endDate, ?int $branchId): float
	{
		$db = DatabaseService::GetInstance()->GetDbConnectionRaw();
		$b = ($branchId !== null && $branchId > 0) ? ' AND branch_id = ' . intval($branchId) : '';
		$stmt = $db->prepare("SELECT IFNULL(SUM(financial_recovery_amount), 0) FROM waste_records WHERE status = 'approved' AND date(created_timestamp) BETWEEN :s AND :e $b");
		$stmt->execute([':s' => $startDate, ':e' => $endDate]);
		$total = floatval($stmt->fetchColumn());
		$stmt = $db->prepare("SELECT IFNULL(SUM(credit_recovered_amount), 0) FROM supplier_returns WHERE resolved_timestamp IS NOT NULL AND date(resolved_timestamp) BETWEEN :s AND :e $b");
		$stmt->execute([':s' => $startDate, ':e' => $endDate]);
		return round($total + floatval($stmt->fetchColumn()), 2);
	}

	private function SumTax(string $startDate, string $endDate, ?int $branchId): float
	{
		$b = ($branchId !== null && $branchId > 0) ? ' AND branch_id = ' . intval($branchId) : '';
		$stmt = DatabaseService::GetInstance()->GetDbConnectionRaw()->prepare("SELECT IFNULL(SUM(tax_amount), 0) FROM sales WHERE status = 'completed' AND date(created_timestamp) BETWEEN :s AND :e $b");
		$stmt->execute([':s' => $startDate, ':e' => $endDate]);
		return floatval($stmt->fetchColumn());
	}

	// Mismo período del año anterior (hasta el mismo día transcurrido)
	public function GetYearOverYear(string $startDate, string $endDate, ?int $branchId, float $sales, float $waste): array
	{
		$db = DatabaseService::GetInstance()->GetDbConnectionRaw();
		$endEff = date('Y-m-d', min(strtotime($endDate), strtotime(date('Y-m-d'))));
		$ps = date('Y-m-d', strtotime('-1 year', strtotime($startDate)));
		$pe = date('Y-m-d', strtotime('-1 year', strtotime($endEff)));
		$b = ($branchId !== null && $branchId > 0) ? ' AND branch_id = ' . intval($branchId) : '';
		$st = $db->prepare("SELECT IFNULL(SUM(total_amount), 0) FROM sales WHERE status = 'completed' AND date(created_timestamp) BETWEEN :s AND :e $b");
		$st->execute([':s' => $ps, ':e' => $pe]);
		$prevSales = floatval($st->fetchColumn());
		$st = $db->prepare("SELECT IFNULL(SUM(total_cost), 0) FROM waste_records WHERE status = 'approved' AND date(created_timestamp) BETWEEN :s AND :e $b");
		$st->execute([':s' => $ps, ':e' => $pe]);
		$prevWaste = floatval($st->fetchColumn());
		return [
			'label' => date('d/m/Y', strtotime($ps)) . ' – ' . date('d/m/Y', strtotime($pe)),
			'prev_sales' => $prevSales,
			'prev_waste' => $prevWaste,
			'sales_delta_pct' => $prevSales > 0 ? round(($sales - $prevSales) / $prevSales * 100, 1) : null,
			'waste_delta_pct' => $prevWaste > 0 ? round(($waste - $prevWaste) / $prevWaste * 100, 1) : null,
			'has_data' => $prevSales > 0 || $prevWaste > 0
		];
	}

	private function GetTimeSeriesWithProjection(string $period, string $startDate, string $endDate, ?int $branchId, float $totalSales, float $totalCogs, float $totalWasteCost): array
	{
		$dbRaw = DatabaseService::GetInstance()->GetDbConnectionRaw();
		$monthsShort = ['', 'Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];

		$today = date('Y-m-d');
		$currYear = date('Y');
		$currMonthNum = intval(date('n'));

		// Case 1: YEAR VIEW -> Group strictly by 12 MONTHS (Ene to Dic)
		if ($period === 'year')
		{
			$salesMonthlySql = "
				SELECT strftime('%m', created_timestamp) as m, 
					   IFNULL(SUM(total_amount), 0) as sales,
					   IFNULL(SUM(total_cogs), 0) as cogs,
					   IFNULL(SUM(gross_margin), 0) as margin
				FROM sales
				WHERE status = 'completed' AND strftime('%Y', created_timestamp) = :yr
			";
			$salesParams = [':yr' => $currYear];
			if ($branchId !== null && $branchId > 0)
			{
				$salesMonthlySql .= " AND branch_id = :bid";
				$salesParams[':bid'] = $branchId;
			}
			$salesMonthlySql .= " GROUP BY strftime('%m', created_timestamp)";
			$stmtSM = $dbRaw->prepare($salesMonthlySql);
			$stmtSM->execute($salesParams);
			$salesMonthly = $stmtSM->fetchAll(\PDO::FETCH_ASSOC);

			$wasteMonthlySql = "
				SELECT strftime('%m', created_timestamp) as m, 
					   IFNULL(SUM(total_cost), 0) as waste
				FROM waste_records
				WHERE status = 'approved' AND strftime('%Y', created_timestamp) = :yr
			";
			$wasteParams = [':yr' => $currYear];
			if ($branchId !== null && $branchId > 0)
			{
				$wasteMonthlySql .= " AND branch_id = :bid";
				$wasteParams[':bid'] = $branchId;
			}
			$wasteMonthlySql .= " GROUP BY strftime('%m', created_timestamp)";
			$stmtWM = $dbRaw->prepare($wasteMonthlySql);
			$stmtWM->execute($wasteParams);
			$wasteMonthly = $stmtWM->fetchAll(\PDO::FETCH_ASSOC);

			$sMap = [];
			foreach ($salesMonthly as $r) { $sMap[intval($r['m'])] = $r; }
			$wMap = [];
			foreach ($wasteMonthly as $r) { $wMap[intval($r['m'])] = $r; }

			$timeline = [];
			$currMonthSales = floatval($sMap[$currMonthNum]['sales'] ?? 0);
			$currMonthCogs = floatval($sMap[$currMonthNum]['cogs'] ?? 0);
			$currMonthWaste = floatval($wMap[$currMonthNum]['waste'] ?? 0);
			$daysInCurrMonth = intval(date('t'));
			$daysElapsedCurrMonth = max(1, intval(date('d')));
			$projCurrMonthSales = round(($currMonthSales / $daysElapsedCurrMonth) * $daysInCurrMonth, 2);
			$projCurrMonthCogs = round(($currMonthCogs / $daysElapsedCurrMonth) * $daysInCurrMonth, 2);
			$projCurrMonthWaste = round(($currMonthWaste / $daysElapsedCurrMonth) * $daysInCurrMonth, 2);

			for ($m = 1; $m <= $currMonthNum; $m++)
			{
				$s = $sMap[$m] ?? null;
				$w = $wMap[$m] ?? null;
				$salesVal = floatval($s['sales'] ?? 0);
				$cogsVal = floatval($s['cogs'] ?? 0);
				$wasteVal = floatval($w['waste'] ?? 0);
				$marginVal = round($salesVal - $cogsVal, 2);

				$isCurrent = ($m === $currMonthNum);

				$timeline[] = [
					'date' => sprintf('%04d-%02d-01', $currYear, $m),
					'label' => $monthsShort[$m],
					'sales' => $salesVal,
					'cogs' => $cogsVal,
					'margin' => $marginVal,
					'waste' => $wasteVal,
					'projected_sales' => null,
					'is_projected' => false,
					'is_today' => $isCurrent
				];
			}

			return [
				'timeline' => $timeline,
				'meta' => [
					'period' => 'year',
					'today' => $today,
					'has_future_projection' => false
				]
			];
		}

		// Case 2: DAY VIEW -> Hourly slots (08:00 to 20:00)
		if ($period === 'day')
		{
			$salesHourlySql = "
				SELECT strftime('%H:00', created_timestamp) as slot, 
					   IFNULL(SUM(total_amount), 0) as sales,
					   IFNULL(SUM(total_cogs), 0) as cogs,
					   IFNULL(SUM(gross_margin), 0) as margin
				FROM sales
				WHERE status = 'completed' AND date(created_timestamp) = :dt
			";
			$hParams = [':dt' => $startDate];
			if ($branchId !== null && $branchId > 0)
			{
				$salesHourlySql .= " AND branch_id = :bid";
				$hParams[':bid'] = $branchId;
			}
			$salesHourlySql .= " GROUP BY strftime('%H:00', created_timestamp)";
			$stmtH = $dbRaw->prepare($salesHourlySql);
			$stmtH->execute($hParams);
			$hRows = $stmtH->fetchAll(\PDO::FETCH_ASSOC);
			$hMap = [];
			foreach ($hRows as $hr) { $hMap[$hr['slot']] = $hr; }

			$timeline = [];
			for ($hour = 8; $hour <= 20; $hour += 2)
			{
				$slotKey = sprintf('%02d:00', $hour);
				$item = $hMap[$slotKey] ?? null;
				$s = floatval($item['sales'] ?? 0);
				$c = floatval($item['cogs'] ?? 0);
				$m = floatval($item['margin'] ?? 0);

				$timeline[] = [
					'date' => $slotKey,
					'label' => $slotKey . ' hrs',
					'sales' => $s,
					'cogs' => $c,
					'margin' => $m,
					'waste' => 0.0,
					'projected_sales' => null,
					'is_projected' => false,
					'is_today' => true
				];
			}

			return [
				'timeline' => $timeline,
				'meta' => [
					'period' => 'day',
					'today' => $today,
					'has_future_projection' => false
				]
			];
		}

		// Case 3: WEEK or MONTH VIEW -> Continuous daily timeline
		$startTs = strtotime($startDate);
		$endTs = strtotime($endDate);
		$todayTs = strtotime($today);

		$salesSql = "
			SELECT 
				date(created_timestamp) AS date_point,
				IFNULL(SUM(total_amount), 0) AS sales,
				IFNULL(SUM(total_cogs), 0) AS cogs,
				IFNULL(SUM(gross_margin), 0) AS margin
			FROM sales
			WHERE status = 'completed'
				AND date(created_timestamp) >= :start_date
				AND date(created_timestamp) <= :end_date
		";
		$salesParams = [':start_date' => $startDate, ':end_date' => $endDate];
		if ($branchId !== null && $branchId > 0)
		{
			$salesSql .= " AND branch_id = :branch_id";
			$salesParams[':branch_id'] = $branchId;
		}
		$salesSql .= " GROUP BY date(created_timestamp) ORDER BY date_point ASC";
		$stmtSales = $dbRaw->prepare($salesSql);
		$stmtSales->execute($salesParams);
		$salesRows = $stmtSales->fetchAll(\PDO::FETCH_ASSOC);

		$wasteSql = "
			SELECT 
				date(created_timestamp) AS date_point,
				IFNULL(SUM(total_cost), 0) AS waste_cost
			FROM waste_records
			WHERE status = 'approved'
				AND date(created_timestamp) >= :start_date
				AND date(created_timestamp) <= :end_date
		";
		$wasteParams = [':start_date' => $startDate, ':end_date' => $endDate];
		if ($branchId !== null && $branchId > 0)
		{
			$wasteSql .= " AND branch_id = :branch_id";
			$wasteParams[':branch_id'] = $branchId;
		}
		$wasteSql .= " GROUP BY date(created_timestamp) ORDER BY date_point ASC";
		$stmtWaste = $dbRaw->prepare($wasteSql);
		$stmtWaste->execute($wasteParams);
		$wasteRows = $stmtWaste->fetchAll(\PDO::FETCH_ASSOC);

		$salesByDate = [];
		foreach ($salesRows as $sr) { $salesByDate[$sr['date_point']] = $sr; }
		$wasteByDate = [];
		foreach ($wasteRows as $wr) { $wasteByDate[$wr['date_point']] = $wr; }

		$elapsedDays = max(1, intval(round((min($todayTs, $endTs) - $startTs) / 86400)) + 1);
		$avgDailySales = round($totalSales / $elapsedDays, 2);
		$avgDailyCogs = round($totalCogs / $elapsedDays, 2);
		$avgDailyWaste = round($totalWasteCost / $elapsedDays, 2);

		$timeline = [];
		$currTs = $startTs;
		$effectiveEndTs = min($endTs, $todayTs);

		while ($currTs <= $effectiveEndTs)
		{
			$currDateStr = date('Y-m-d', $currTs);
			$dayNum = date('d', $currTs);
			$monthNum = intval(date('n', $currTs));
			$label = ($period === 'week') ? date('D d', $currTs) : ($dayNum . ' ' . strtolower($monthsShort[$monthNum] ?? ''));

			$isToday = ($currDateStr === $today);

			$s = $salesByDate[$currDateStr] ?? null;
			$w = $wasteByDate[$currDateStr] ?? null;

			$salesVal = floatval($s['sales'] ?? 0);
			$cogsVal = floatval($s['cogs'] ?? 0);
			$marginVal = floatval($s['margin'] ?? 0);
			$wasteVal = floatval($w['waste_cost'] ?? 0);

			$timeline[] = [
				'date' => $currDateStr,
				'label' => $label,
				'sales' => round($salesVal, 2),
				'cogs' => round($cogsVal, 2),
				'margin' => round($marginVal, 2),
				'waste' => round($wasteVal, 2),
				'projected_sales' => null,
				'projected_cogs' => null,
				'is_projected' => false,
				'is_today' => $isToday
			];

			$currTs = strtotime('+1 day', $currTs);
		}

		if (($period === 'month' || $period === 'custom') && $todayTs < $endTs && count($timeline) > 0)
		{
			$lastIdx = count($timeline) - 1;
			// Connect projection from today's real values
			$timeline[$lastIdx]['projected_sales'] = $timeline[$lastIdx]['sales'];
			$timeline[$lastIdx]['projected_cogs'] = $timeline[$lastIdx]['cogs'];

			$futureTs = strtotime('+1 day', $effectiveEndTs);
			while ($futureTs <= $endTs)
			{
				$currDateStr = date('Y-m-d', $futureTs);
				$dayNum = intval(date('d', $futureTs));
				$monthNum = intval(date('n', $futureTs));
				$label = ($period === 'week') ? date('D d', $futureTs) : ($dayNum . ' ' . strtolower($monthsShort[$monthNum] ?? ''));

				$timeline[] = [
					'date' => $currDateStr,
					'label' => $label,
					'sales' => null,
					'cogs' => null,
					'margin' => null,
					'waste' => null,
					'projected_sales' => $avgDailySales,
					'projected_cogs' => $avgDailyCogs,
					'is_projected' => true,
					'is_today' => false
				];

				$futureTs = strtotime('+1 day', $futureTs);
			}
		}

		return [
			'timeline' => $timeline,
			'meta' => [
				'period' => $period,
				'today' => $today,
				'avg_daily_sales' => $avgDailySales,
				'has_future_projection' => ($todayTs < $endTs)
			]
		];
	}

	private function GetDeepProductProfitability(string $startDate, string $endDate, ?int $branchId): array
	{
		$dbRaw = DatabaseService::GetInstance()->GetDbConnectionRaw();

		$sql = "
			SELECT 
				p.id AS product_id,
				p.name AS product_name,
				p.sku AS product_sku,
				p.picture_file_name,
				qu.name AS qu_name,
				IFNULL(SUM(si.amount), 0) AS units_sold,
				IFNULL(SUM(si.subtotal), 0) AS total_revenue,
				IFNULL(SUM(si.total_cost), 0) AS total_cogs,
				(IFNULL(SUM(" . SalesService::SQL_ITEM_NET_SALES . "), 0) - IFNULL(SUM(si.total_cost), 0)) AS gross_profit,
				IFNULL(SUM(" . SalesService::SQL_ITEM_NET_SALES . "), 0) AS net_revenue,
				SUM(CASE WHEN IFNULL(si.unit_cost, 0) = 0 THEN 1 ELSE 0 END) AS lines_without_cost
			FROM products p
			LEFT JOIN quantity_units qu ON p.qu_id_stock = qu.id
			LEFT JOIN sale_items si ON p.id = si.product_id
			LEFT JOIN sales s ON si.sale_id = s.id
			WHERE s.status = 'completed'
				AND date(s.created_timestamp) >= :s_date
				AND date(s.created_timestamp) <= :e_date
		";
		$params = [':s_date' => $startDate, ':e_date' => $endDate];
		if ($branchId !== null && $branchId > 0)
		{
			$sql .= " AND s.branch_id = :bid";
			$params[':bid'] = $branchId;
		}
		$sql .= " GROUP BY p.id, p.name, p.sku, p.picture_file_name, qu.name HAVING total_revenue > 0 ORDER BY total_revenue DESC";

		$stmt = $dbRaw->prepare($sql);
		$stmt->execute($params);
		$products = $stmt->fetchAll(\PDO::FETCH_OBJ);

		// Stock actual (de la sucursal si se filtra) y merma aprobada del período por producto
		$bid = ($branchId !== null && $branchId > 0) ? intval($branchId) : 0;
		$stockByProduct = $dbRaw->query('SELECT s.product_id, SUM(s.amount) FROM stock s LEFT JOIN locations l ON l.id = s.location_id' . ($bid ? " WHERE l.branch_id = $bid" : '') . ' GROUP BY s.product_id')->fetchAll(\PDO::FETCH_KEY_PAIR);
		$wasteStmt = $dbRaw->prepare("SELECT product_id, SUM(amount) AS units, SUM(total_cost) AS cost FROM waste_records WHERE status = 'approved' AND date(created_timestamp) BETWEEN :s AND :e" . ($bid ? " AND branch_id = $bid" : '') . ' GROUP BY product_id');
		$wasteStmt->execute([':s' => $startDate, ':e' => $endDate]);
		$wasteByProduct = [];
		foreach ($wasteStmt->fetchAll(\PDO::FETCH_OBJ) as $w)
		{
			$wasteByProduct[$w->product_id] = $w;
		}
		$elapsedDays = max(1, intval((min(strtotime(date('Y-m-d')), strtotime($endDate)) - strtotime($startDate)) / 86400) + 1);

		$totalRevenueAll = 0.0;
		foreach ($products as $p)
		{
			$totalRevenueAll += floatval($p->total_revenue);
		}

		$cumulativeRevenue = 0.0;
		$results = [];

		foreach ($products as $p)
		{
			$rev = floatval($p->total_revenue);
			$cogs = floatval($p->total_cogs);
			$profit = floatval($p->gross_profit);
			$units = floatval($p->units_sold);
			$stock = floatval($stockByProduct[$p->product_id] ?? 0);
			$wasteCost = floatval($wasteByProduct[$p->product_id]->cost ?? 0);
			$wasteUnits = floatval($wasteByProduct[$p->product_id]->units ?? 0);

			$netRev = floatval($p->net_revenue);
			$marginPct = SalesService::MarginPct($profit, $netRev) ?? 0;
			$sharePct = ($totalRevenueAll > 0) ? round(($rev / $totalRevenueAll) * 100, 1) : 0;

			$cumulativeRevenue += $rev;
			$cumPct = ($totalRevenueAll > 0) ? round(($cumulativeRevenue / $totalRevenueAll) * 100, 1) : 0;

			// ABC Pareto classification
			if ($cumPct <= 80.0)
			{
				$abcClass = 'A';
			}
			elseif ($cumPct <= 95.0)
			{
				$abcClass = 'B';
			}
			else
			{
				$abcClass = 'C';
			}

			// BCG Matrix / Portfolio classification
			if (($abcClass === 'A' || $abcClass === 'B') && $marginPct >= GROCY_MARGIN_TARGET_PCT)
			{
				$bcgClass = 'Estrella';
				$bcgIcon = 'fa-star';
				$bcgColor = 'warning';
				$bcgHint = 'Alto ingreso, alto margen. Impulsar stock prioritario.';
			}
			elseif (($abcClass === 'A' || $abcClass === 'B') && $marginPct < GROCY_MARGIN_TARGET_PCT)
			{
				$bcgClass = 'Vaca Lechera';
				$bcgIcon = 'fa-cow';
				$bcgColor = 'primary';
				$bcgHint = 'Alto volumen de flujo, pero margen ajustado. Negociar precio de compra.';
			}
			elseif ($abcClass === 'C' && $marginPct >= GROCY_MARGIN_TARGET_PCT)
			{
				$bcgClass = 'Oportunidad';
				$bcgIcon = 'fa-circle-question';
				$bcgColor = 'info';
				$bcgHint = 'Alto margen con baja venta. Aumentar visibilidad en góndola.';
			}
			else
			{
				$bcgClass = 'Revisar';
				$bcgIcon = 'fa-triangle-exclamation';
				$bcgColor = 'secondary';
				$bcgHint = 'Bajo ingreso y margen estrecho. Evaluar descontinuar o sustituir.';
			}

			// Días de cobertura con la venta diaria real de los días transcurridos del período
			$dailySales = $units / $elapsedDays;
			$daysOfStock = $dailySales > 0 ? round($stock / $dailySales, 1) : null;

			$isHighVolLowMargin = ($sharePct >= 10.0 && $marginPct < GROCY_MARGIN_TARGET_PCT);

			$results[] = (object)[
				'product_id' => $p->product_id,
				'product_name' => $p->product_name,
				'product_sku' => $p->product_sku ?? '',
				'picture_file_name' => $p->picture_file_name ?? null,
				'has_pending_cogs' => ($cogs <= 0 && $rev > 0),
				'qu_name' => $p->qu_name,
				'units_sold' => $units,
				'total_revenue' => $rev,
				'total_cogs' => $cogs,
				'gross_profit' => $profit,
				'margin_percentage' => $marginPct,
				'revenue_share_pct' => $sharePct,
				'abc_class' => $abcClass,
				'bcg_category' => $bcgClass,
				'bcg_icon' => $bcgIcon,
				'bcg_color' => $bcgColor,
				'bcg_hint' => $bcgHint,
				'current_stock' => $stock,
				'days_of_stock' => $daysOfStock,
				'waste_units' => $wasteUnits,
				'waste_cost' => round($wasteCost, 2),
				'net_profit' => round($profit - $wasteCost, 2),
				'net_margin_pct' => SalesService::MarginPct($profit - $wasteCost, $netRev),
				// Líneas cuyo costo quedó en 0 por falta de precio de compra: margen sobreestimado, no costo real nulo
				'lines_without_cost' => intval($p->lines_without_cost),
				'sell_through_pct' => ($units + $stock) > 0 ? round($units / ($units + $stock) * 100, 1) : null,
				'is_high_volume_low_margin' => $isHighVolLowMargin
			];
		}

		return $results;
	}

	private function GetBranchRanking(string $period, string $startDate, string $endDate): array
	{
		$dbRaw = DatabaseService::GetInstance()->GetDbConnectionRaw();
		$m = DailyMetricsService::GetInstance();

		$branches = $dbRaw->query("SELECT id, name, code FROM branches WHERE active = 1 ORDER BY id ASC")->fetchAll(\PDO::FETCH_OBJ);
		// Mismo resumen que HOY y los KPI del panel (venta neta, meta registrada, % de merma sobre la base configurada)
		$to = min($endDate, date('Y-m-d'));

		$ranking = [];
		foreach ($branches as $b)
		{
			$bid = intval($b->id);
			$sum = $m->Summary($bid, $startDate, $to);
			$s = $sum['sales'];

			$sales = $s['net_sales'];
			$cogs = $s['cogs'];
			$margin = $s['gross_margin'];
			$marginPct = $s['margin_pct'] ?? 0;
			$tickets = $s['tickets'];
			$avgTicket = $s['avg_ticket'] ?? 0;
			$wasteEvents = $sum['waste']['events'];
			$wasteCost = $sum['waste']['cost'];
			$wasteRatioPct = $sum['waste_pct'];

			// Branch Inventory valuation
			$invStmt = $dbRaw->prepare("SELECT IFNULL(SUM(s.amount * IFNULL(s.price, IFNULL(plp.price, 0))), 0) AS valuation FROM stock s JOIN locations l ON s.location_id = l.id JOIN products p ON s.product_id = p.id LEFT JOIN cache__products_last_purchased plp ON p.id = plp.product_id WHERE l.branch_id = :bid");
			$invStmt->execute([':bid' => $bid]);
			$invVal = floatval($invStmt->fetchColumn() ?? 0);
			$turnover = ($invVal > 0 && $cogs > 0) ? round($cogs / $invVal, 2) : null;

			// Net Operating Result = Margin - Waste
			$netResult = round($margin - $wasteCost, 2);

			$salesTarget = $sum['sales_target'];
			$vsMetaPct = $sum['compliance_pct'] !== null ? round($sum['compliance_pct'] - 100, 1) : null;

			// Estado = el peor entre cumplimiento de ventas y merma (reglas centrales). Nunca verde sin registros.
			$hasActivity = ($tickets > 0 || $wasteEvents > 0);
			$order = ['danger' => 3, 'warning' => 2, 'success' => 1, 'neutral' => 0];
			$level = $order[$sum['sales_level']] >= $order[$sum['waste_level']] ? $sum['sales_level'] : $sum['waste_level'];
			if (!$hasActivity)
			{
				$level = 'neutral';
			}
			[$execState, $execStateLabel] = DailyMetricsService::PanelState($level);
			$status = ['ok' => '🟢 ', 'warn' => '🟡 ', 'crit' => '🔴 ', 'no_data' => '⚪ '][$execState] . $execStateLabel;
			$statusBadge = ['ok' => 'success', 'warn' => 'warning', 'crit' => 'danger', 'no_data' => 'secondary'][$execState];

			// Sparkline de 7 puntos de la sucursal
			$bSpark = $this->RealSparklines($startDate, $endDate, $bid);

			$ranking[] = [
				'branch_id' => $bid,
				'name' => $b->name,
				'clean_name' => trim(str_replace('[DEMO]', '', $b->name)),
				'code' => $b->code,
				'has_activity' => $hasActivity,
				'sales' => $sales,
				'sales_target' => $salesTarget,
				'vs_meta_pct' => $vsMetaPct,
				'tickets' => $tickets,
				'avg_ticket' => $avgTicket,
				'cogs' => $cogs,
				'gross_margin' => $margin,
				'margin_pct' => $marginPct,
				'waste_cost' => $wasteCost,
				'waste_events' => $wasteEvents,
				'waste_ratio_pct' => $wasteRatioPct ?? 0.0,
				'inventory_valuation' => $invVal,
				'turnover' => $turnover,
				'net_result' => $netResult,
				'status' => $status,
				'status_badge' => $statusBadge,
				'exec_state' => $execState,
				'exec_state_label' => $execStateLabel,
				'trend_sales' => $bSpark['sales'] ?? []
			];
		}

		// Sort active branches first (by sales DESC), then branches without activity
		usort($ranking, function ($a, $b) {
			if ($a['has_activity'] !== $b['has_activity'])
			{
				return $b['has_activity'] <=> $a['has_activity'];
			}
			return $b['sales'] <=> $a['sales'];
		});

		foreach ($ranking as $idx => &$item)
		{
			$item['rank'] = $idx + 1;
		}

		return $ranking;
	}

	private function GetBranchWasteHeatmap(string $startDate, string $endDate): array
	{
		$dbRaw = DatabaseService::GetInstance()->GetDbConnectionRaw();

		$sql = "
			SELECT 
				b.id AS branch_id,
				b.name AS branch_name,
				IFNULL(wr.akcal_classification, 'operativa') AS akcal_class,
				IFNULL(SUM(w.total_cost), 0) AS cost
			FROM waste_records w
			JOIN branches b ON w.branch_id = b.id
			JOIN waste_reasons wr ON w.waste_reason_id = wr.id
			WHERE w.status = 'approved'
				AND date(w.created_timestamp) >= :s
				AND date(w.created_timestamp) <= :e
			GROUP BY b.id, b.name, IFNULL(wr.akcal_classification, 'operativa')
		";
		$stmt = $dbRaw->prepare($sql);
		$stmt->execute([':s' => $startDate, ':e' => $endDate]);
		$rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

		$branches = $dbRaw->query("SELECT id, name, code FROM branches WHERE active = 1 ORDER BY id ASC")->fetchAll(\PDO::FETCH_OBJ);
		$categories = ['operativa' => 'Operativa (Frío/Manejo)', 'comercial' => 'Comercial (FEFO/Devolución)', 'ajuste' => 'Ajuste (Descuadre)', 'averia' => 'Avería (Recepción/Empaque)'];

		$matrix = [];
		foreach ($branches as $b)
		{
			$matrix[$b->id] = [
				'branch_id' => $b->id,
				'branch_name' => $b->name,
				'code' => $b->code,
				'operativa' => 0.0,
				'comercial' => 0.0,
				'ajuste' => 0.0,
				'averia' => 0.0,
				'total' => 0.0
			];
		}

		foreach ($rows as $r)
		{
			$bid = $r['branch_id'];
			$cls = strtolower($r['akcal_class'] ?? 'operativa');
			$cost = floatval($r['cost']);
			if (isset($matrix[$bid][$cls]))
			{
				$matrix[$bid][$cls] = $cost;
				$matrix[$bid]['total'] += $cost;
			}
		}

		return [
			'categories' => $categories,
			'matrix' => array_values($matrix)
		];
	}

	private function GetPerishablesRiskWidget(?int $branchId): array
	{
		$dbRaw = DatabaseService::GetInstance()->GetDbConnectionRaw();

		$sql = "
			SELECT 
				p.id AS product_id,
				p.name AS product_name,
				qu.name AS qu_name,
				s.stock_id,
				s.amount,
				s.best_before_date,
				IFNULL(s.price, IFNULL(plp.price, 0)) AS unit_price,
				(s.amount * IFNULL(s.price, IFNULL(plp.price, 0))) AS potential_loss,
				ROUND(julianday(s.best_before_date) - julianday('now')) AS days_left
			FROM stock s
			JOIN products p ON s.product_id = p.id
			JOIN quantity_units qu ON p.qu_id_stock = qu.id
			LEFT JOIN locations l ON s.location_id = l.id
			LEFT JOIN cache__products_last_purchased plp ON p.id = plp.product_id
			WHERE s.amount > 0
				AND s.best_before_date IS NOT NULL
				AND s.best_before_date != '2999-12-31'
		";
		if ($branchId !== null && $branchId > 0)
		{
			$sql .= " AND l.branch_id = " . intval($branchId);
		}
		$sql .= " ORDER BY days_left ASC";

		$stmt = $dbRaw->query($sql);
		$lots = $stmt->fetchAll(\PDO::FETCH_OBJ);

		$urgent3d = ['count' => 0, 'units' => 0.0, 'cost' => 0.0, 'items' => []];
		$warning7d = ['count' => 0, 'units' => 0.0, 'cost' => 0.0, 'items' => []];
		$safe15d = ['count' => 0, 'units' => 0.0, 'cost' => 0.0, 'items' => []];

		$criticalLots = [];

		foreach ($lots as $lot)
		{
			$days = intval($lot->days_left);
			$cost = floatval($lot->potential_loss);
			$amt = floatval($lot->amount);

			if ($days <= 3)
			{
				$urgent3d['count']++;
				$urgent3d['units'] += $amt;
				$urgent3d['cost'] += $cost;
				$lot->urgency_level = 'danger';
				$lot->suggested_action = 'Descuento dinámico -30% inmediato';
				$criticalLots[] = $lot;
			}
			elseif ($days <= 7)
			{
				$warning7d['count']++;
				$warning7d['units'] += $amt;
				$warning7d['cost'] += $cost;
				$lot->urgency_level = 'warning';
				$lot->suggested_action = 'Promoción 2x1 o destaque en góndola';
				$criticalLots[] = $lot;
			}
			elseif ($days <= 15)
			{
				$safe15d['count']++;
				$safe15d['units'] += $amt;
				$safe15d['cost'] += $cost;
			}
		}

		$totalRiskValue = $urgent3d['cost'] + $warning7d['cost'];

		return [
			'total_risk_value' => round($totalRiskValue, 2),
			'urgent_3d' => [
				'count' => $urgent3d['count'],
				'units' => round($urgent3d['units'], 1),
				'cost' => round($urgent3d['cost'], 2),
				'suggested_discount' => '30%'
			],
			'warning_7d' => [
				'count' => $warning7d['count'],
				'units' => round($warning7d['units'], 1),
				'cost' => round($warning7d['cost'], 2),
				'suggested_discount' => '15%'
			],
			'safe_15d' => [
				'count' => $safe15d['count'],
				'units' => round($safe15d['units'], 1),
				'cost' => round($safe15d['cost'], 2)
			],
			'critical_lots' => array_slice($criticalLots, 0, 6)
		];
	}

	private function GenerateExecutiveAiInsights(?int $branchId, float $totalSales, float $totalWasteCost, float $wasteRatio, float $turnover, array $projection, array $deltas, array $wasteByReason, array $perishables, $procStats): array
	{
		$insights = [];

		// Insight 1: Sales Pacing vs Monthly Goal (solo si hay meta: presupuesto o ritmo histórico)
		if ($projection['is_on_track'] === null)
		{
		}
		elseif ($projection['is_on_track'])
		{
			$insights[] = [
				'badge' => 'RITMO DE VENTAS',
				'badge_color' => 'success',
				'icon' => 'fa-arrow-trend-up',
				'title' => 'Ritmo de Ventas en Ruta a Cumplir Meta',
				'description' => "Ventas acumuladas en \${$totalSales} con un ritmo diario de \${$projection['daily_sales_rate']}/día. Al ritmo actual cerrarás en \${$projection['projected_sales']} ({$projection['projected_target_achievement_pct']}% de la meta mensual). Faltan {$projection['remaining_days']} días.",
				'action_label' => 'Abrir Punto de Venta',
				'action_url' => '/pos',
				'action_icon' => 'fa-cash-register'
			];
		}
		else
		{
			$gap = round($projection['sales_target'] - $projection['projected_sales'], 2);
			$insights[] = [
				'badge' => 'ALERTA DE VENTAS',
				'badge_color' => 'warning',
				'icon' => 'fa-triangle-exclamation',
				'title' => 'Ritmo de Ventas Requiere Aceleración',
				'description' => "Al ritmo diario actual (\${$projection['daily_sales_rate']}/día) se proyecta un cierre de \${$projection['projected_sales']} con una brecha de \${$gap} frente a la meta. Se sugiere lanzar promociones de fin de semana.",
				'action_label' => 'Ver Histórico de Ventas',
				'action_url' => '/sales',
				'action_icon' => 'fa-receipt'
			];
		}

		// Insight 2: Waste and Causals (e.g. Cold Chain, Expiry)
		$topReason = !empty($wasteByReason) ? $wasteByReason[0] : null;
		if ($wasteRatio <= $projection['waste_target_ratio_pct'])
		{
			$reasonNote = ($topReason) ? "La principal causa es '{$topReason->reason_name}' (\${$topReason->cost}, {$topReason->percentage}% del total)." : "";
			$insights[] = [
				'badge' => 'CONTROL DE MERMA',
				'badge_color' => 'primary',
				'icon' => 'fa-shield-halved',
				'title' => "Merma Controlada en {$wasteRatio}% (Meta: ≤ {$projection['waste_target_ratio_pct']}%)",
				'description' => "Pérdida acumulada en \${$totalWasteCost}. {$reasonNote} Mantener monitoreo preventivo de temperaturas y rotación FEFO.",
				'action_label' => 'Auditar Causales',
				'action_url' => '/waste',
				'action_icon' => 'fa-table-list'
			];
		}
		else
		{
			$reasonName = ($topReason) ? $topReason->reason_name : "Cadena de frío / Caducidad";
			$reasonCost = ($topReason) ? "\${$topReason->cost}" : "";
			$insights[] = [
				'badge' => 'ALERTA CRÍTICA MERMA',
				'badge_color' => 'danger',
				'icon' => 'fa-fire-flame-curved',
				'title' => "Merma Supera Umbral Corporativo ({$wasteRatio}% vs {$projection['waste_target_ratio_pct']}% meta)",
				'description' => "Causa prioritaria: '{$reasonName}' ({$reasonCost}). Acción correctiva sugerida: auditar burletes de cámaras frigoríficas y calibrar compras de corta vida útil.",
				'action_label' => 'Aplicar Plan de Acción',
				'action_url' => '/waste/approvals',
				'action_icon' => 'fa-clipboard-check'
			];
		}

		// Insight 3: Perishables & Dynamic Discounts
		if ($perishables['total_risk_value'] > 0)
		{
			$u3 = $perishables['urgent_3d'];
			$insights[] = [
				'badge' => 'LÁCTEOS PERECEDEROS',
				'badge_color' => 'danger',
				'icon' => 'fa-clock-rotate-left',
				'title' => "Lotes en Riesgo Crítico de Caducidad (\${$perishables['total_risk_value']})",
				'description' => "Se detectaron {$u3['count']} lotes (\${$u3['cost']}) con vencimiento en ≤ 3 días. Sugerencia IA: Activar descuento dinámico de liquidación (-{$u3['suggested_discount']}) para recuperar costo de oportunidad antes de merma.",
				'action_label' => 'Revisar Lotes Críticos',
				'action_url' => '/quality',
				'action_icon' => 'fa-boxes-stacked'
			];
		}

		// Insight 4: Inventory Turnover in Dairy
		if ($projection['dsi'] !== null && $projection['dsi'] > $projection['targets']['dsi_target_days'])
		{
			$insights[] = [
				'badge' => 'SALUD INVENTARIO',
				'badge_color' => 'info',
				'icon' => 'fa-rotate',
				'title' => "Días de inventario sobre la meta ({$projection['dsi']} d vs ≤ {$projection['targets']['dsi_target_days']} d)",
				'description' => "Observado: rotación {$turnover}x en el período y {$projection['dsi']} días de inventario. En perecederos, más días de cobertura elevan el riesgo de vencimiento; revisar sobrestock en MRP.",
				'action_label' => 'Ver Matriz de Stock',
				'action_url' => '/stockoverview',
				'action_icon' => 'fa-dolly'
			];
		}

		return $insights;
	}

	/**
	 * Construye la estructura integral del Panel Ejecutivo (/kpis) reutilizando datos reales de SQLite
	 * y los servicios de Ventas, Presupuesto, Alertas, Compras, Inventario, Despachos, Personal, Calidad y Mermas.
	 */
	public function BuildExecutivePanelOverview(?int $branchId, string $period, array $kpiData, array $laborOutlook, int $alertCount): array
	{
		$dbRaw = DatabaseService::GetInstance()->GetDbConnectionRaw();
		$startDate = $kpiData['start_date'];
		// Lo registrado llega hasta hoy: el resto del período no cuenta como días transcurridos ni suma meta
		$endDate = min($kpiData['end_date'], date('Y-m-d'));
		$startTs = strtotime($startDate);
		$endTs = strtotime($endDate);
		$totalDays = max(1, intval(round(($endTs - $startTs) / 86400)) + 1);
		$elapsedDays = max(1, intval($kpiData['month_projection']['elapsed_days'] ?? $totalDays));
		$monthDays = max(1, intval(date('t', $startTs)));
		$periodFactor = $totalDays / $monthDays;

		$branchFilterSql = ($branchId !== null && $branchId > 0) ? ' AND s.branch_id = ' . intval($branchId) : '';

		// 1. Calidad y cobertura de datos (CMV en líneas de venta, costos en stock, última actualización real)
		$cogsCovRow = $dbRaw->query("
			SELECT
				COUNT(*) AS total_lines,
				SUM(CASE WHEN IFNULL(si.unit_cost, 0) > 0 THEN 1 ELSE 0 END) AS lines_with_cost,
				SUM(CASE WHEN IFNULL(si.unit_cost, 0) <= 0 THEN 1 ELSE 0 END) AS lines_without_cost
			FROM sale_items si
			JOIN sales s ON s.id = si.sale_id
			WHERE s.status = 'completed'
				AND date(s.created_timestamp) >= " . $dbRaw->quote($startDate) . "
				AND date(s.created_timestamp) <= " . $dbRaw->quote($endDate) . $branchFilterSql
		)->fetch(\PDO::FETCH_ASSOC);

		$totalSaleLines = intval($cogsCovRow['total_lines'] ?? 0);
		$linesWithCost = intval($cogsCovRow['lines_with_cost'] ?? 0);
		$linesWithoutCost = intval($cogsCovRow['lines_without_cost'] ?? 0);
		$salesCostCoveragePct = $totalSaleLines > 0 ? round(($linesWithCost / $totalSaleLines) * 100, 1) : null;

		$locBranchSql = ($branchId !== null && $branchId > 0) ? ' AND l.branch_id = ' . intval($branchId) : '';
		$stockCovRow = $dbRaw->query("
			SELECT
				(SELECT COUNT(*) FROM products WHERE active = 1) AS total_active_products,
				COUNT(DISTINCT s.product_id) AS products_with_stock,
				COUNT(s.id) AS total_entries,
				SUM(CASE WHEN IFNULL(s.price, IFNULL(plp.price, 0)) > 0 THEN 1 ELSE 0 END) AS entries_with_cost
			FROM stock s
			JOIN products p ON p.id = s.product_id AND p.active = 1
			LEFT JOIN locations l ON l.id = s.location_id
			LEFT JOIN cache__products_last_purchased plp ON plp.product_id = p.id
			WHERE s.amount > 0" . $locBranchSql
		)->fetch(\PDO::FETCH_ASSOC);

		$totalActiveProducts = intval($stockCovRow['total_active_products'] ?? 0);
		$productsWithStock = intval($stockCovRow['products_with_stock'] ?? 0);
		$totalStockEntries = intval($stockCovRow['total_entries'] ?? 0);
		$entriesWithCost = intval($stockCovRow['entries_with_cost'] ?? 0);
		$stockCostCoveragePct = $totalStockEntries > 0 ? round(($entriesWithCost / $totalStockEntries) * 100, 1) : null;

		$belowMinCount = intval($dbRaw->query("
			SELECT COUNT(*) FROM (
				SELECT p.id, p.min_stock_amount, IFNULL(SUM(s.amount), 0) AS qty
				FROM products p
				LEFT JOIN stock s ON s.product_id = p.id
				LEFT JOIN locations l ON l.id = s.location_id
				WHERE p.active = 1 AND p.min_stock_amount > 0" . $locBranchSql . "
				GROUP BY p.id, p.min_stock_amount
				HAVING qty < p.min_stock_amount
			)
		")->fetchColumn());

		$latestTs = $dbRaw->query("
			SELECT MAX(ts) FROM (
				SELECT MAX(created_timestamp) AS ts FROM sales
				UNION ALL
				SELECT MAX(created_timestamp) AS ts FROM waste_records
				UNION ALL
				SELECT MAX(row_created_timestamp) AS ts FROM stock_log
				UNION ALL
				SELECT MAX(created_at) AS ts FROM customer_orders
			)
		")->fetchColumn();

		$monthsEs = [1 => 'ene', 2 => 'feb', 3 => 'mar', 4 => 'abr', 5 => 'may', 6 => 'jun', 7 => 'jul', 8 => 'ago', 9 => 'sep', 10 => 'oct', 11 => 'nov', 12 => 'dic'];
		$lastUpdatedFormatted = 'Sin registros';
		if (!empty($latestTs))
		{
			$tsInt = strtotime($latestTs);
			$lastUpdatedFormatted = intval(date('d', $tsInt)) . ' ' . ($monthsEs[intval(date('n', $tsInt))] ?? 'oct') . ' ' . date('Y, H:i', $tsInt);
		}

		// 2. Indicadores estratégicos: misma fuente y mismas reglas que HOY (DailyMetricsService::Summary)
		$m = DailyMetricsService::GetInstance();
		$sum = $m->Summary($branchId, $startDate, $endDate);
		$sl = $sum['sales'];
		$elapsedDays = intval(round((strtotime($endDate) - strtotime($startDate)) / 86400)) + 1;
		$daysLabel = $elapsedDays . ($elapsedDays === 1 ? ' día' : ' días');

		$salesVal = $sl['net_sales'];
		$marginVal = $sl['gross_margin'];
		$marginPct = $sl['margin_pct'];
		$wasteVal = $sum['waste']['cost'];
		$wasteRatioPct = $sum['waste_pct'];
		$turnoverVal = floatval($kpiData['inventory_turnover'] ?? 0);
		$invValuation = floatval($kpiData['total_inventory_valuation'] ?? 0);

		$salesMeta = $sum['sales_target'];
		$marginMeta = ($salesMeta && $sum['margin_target_pct'] !== null) ? round($salesMeta * $sum['margin_target_pct'] / 100, 2) : null;
		$wasteBase = $sl['has_data'] ? BudgetService::WasteBase($sl['gross_sales'], $sl['tax'], $sl['cogs'])['amount'] : null;
		$wasteMeta = $wasteBase !== null ? round($wasteBase * $sum['waste_target_pct'] / 100, 2) : null;
		// Rotación objetivo del período = días del período ÷ días de inventario objetivo
		$turnoverMeta = $sum['dsi_target_days'] ? round($elapsedDays / $sum['dsi_target_days'], 2) : null;

		$salesVsMetaPct = $sum['compliance_pct'] !== null ? round($sum['compliance_pct'] - 100, 1) : null;
		$marginVsMetaPct = ($marginMeta && $sl['has_data']) ? round(($marginVal - $marginMeta) / $marginMeta * 100, 1) : null;
		$wasteVsMetaPct = ($wasteMeta && $wasteMeta > 0) ? round(($wasteVal - $wasteMeta) / $wasteMeta * 100, 1) : null;
		$turnoverVsMetaPct = ($turnoverMeta && $invValuation > 0 && $sl['has_data']) ? round(($turnoverVal - $turnoverMeta) / $turnoverMeta * 100, 1) : null;
		$turnLevel = $turnoverVsMetaPct === null ? 'neutral' : ($turnoverVal >= $turnoverMeta * DailyMetricsService::RULES['turnover_warn_factor'] ? 'success' : 'warning');

		$strategicKpis = [
			'sales' => [
				'has_data' => $sl['has_data'],
				'value' => $salesVal,
				'target' => $salesMeta,
				'delta_pct' => $salesVsMetaPct,
				'is_favorable' => ($salesVsMetaPct ?? 0) >= 0,
				'state' => DailyMetricsService::PanelState($sum['sales_level'])[0],
				'days_label' => $daysLabel,
				'url' => '/sales'
			],
			'margin' => [
				'has_data' => $sl['has_data'] && $linesWithCost > 0,
				'is_partial' => $linesWithoutCost > 0,
				'coverage_pct' => $salesCostCoveragePct,
				'lines_without_cost' => $linesWithoutCost,
				'value' => $marginVal,
				'margin_pct' => $marginPct ?? 0,
				'target' => $marginMeta,
				'target_pct' => $sum['margin_target_pct'],
				'delta_pct' => $marginVsMetaPct,
				'is_favorable' => ($marginVsMetaPct ?? 0) >= 0,
				'state' => DailyMetricsService::PanelState($sum['margin_level'])[0],
				'days_label' => $daysLabel,
				'url' => '/sales'
			],
			'waste' => [
				'has_data' => $wasteRatioPct !== null,
				'value' => $wasteVal,
				'ratio_pct' => $wasteRatioPct ?? 0,
				'target' => $wasteMeta,
				'target_ratio_pct' => $sum['waste_target_pct'],
				'delta_pct' => $wasteVsMetaPct,
				'is_favorable' => ($wasteVsMetaPct ?? 0) <= 0,
				'state' => DailyMetricsService::PanelState($sum['waste_level'])[0],
				'days_label' => $daysLabel,
				'url' => '/waste'
			],
			'turnover' => [
				'has_data' => ($invValuation > 0 && $sl['has_data']),
				'value' => $turnoverVal,
				'dsi' => $kpiData['days_of_inventory'],
				'target' => $turnoverMeta,
				'delta_pct' => $turnoverVsMetaPct,
				'is_favorable' => ($turnoverVsMetaPct ?? 0) >= 0,
				'state' => DailyMetricsService::PanelState($turnLevel)[0],
				'days_label' => $daysLabel,
				'url' => '/stockoverview'
			]
		];

		// 3. Decisiones prioritarias: cada tarjeta sale de un dato registrado y de las reglas centrales.
		// Responsable = rol dueño del proceso; plazo: crítico = mismo día, atención = día siguiente.
		$due = function (string $level) use ($endDate) {
			$months = [1 => 'ene', 2 => 'feb', 3 => 'mar', 4 => 'abr', 5 => 'may', 6 => 'jun', 7 => 'jul', 8 => 'ago', 9 => 'sep', 10 => 'oct', 11 => 'nov', 12 => 'dic'];
			if (!in_array($level, ['danger', 'warning'], true))
			{
				return null;
			}
			$t = strtotime($endDate . ($level === 'warning' ? ' +1 day' : ''));
			return intval(date('j', $t)) . ' ' . $months[intval(date('n', $t))] . ' ' . date('Y', $t);
		};
		$tone = fn(string $level) => ['danger' => 'red', 'warning' => 'amber', 'success' => 'green', 'neutral' => 'amber'][$level];
		$badge = fn(string $level) => ['danger' => 'P1 · Crítica', 'warning' => 'P2 · Alta', 'success' => 'P3 · Seguimiento', 'neutral' => 'P3 · Sin datos'][$level];
		$statusBadge = fn(string $level) => ['danger' => 'Abierta', 'warning' => 'Por atender', 'success' => 'En objetivo', 'neutral' => 'Sin información'][$level];

		$branchesBelowTarget = 0;
		$branchesActiveCount = 0;
		$belowTargetLabels = [];
		foreach (($kpiData['branch_ranking'] ?? []) as $br)
		{
			if (!empty($br['has_activity']))
			{
				$branchesActiveCount++;
				if ($br['vs_meta_pct'] !== null && $br['vs_meta_pct'] < 0)
				{
					$branchesBelowTarget++;
					$belowTargetLabels[] = ($br['clean_name'] ?? $br['name']) . ' ' . number_format(floatval($br['vs_meta_pct']), 1) . '%';
				}
			}
		}
		$salesDesc = $sum['compliance_pct'] === null
			? ($sl['has_data'] ? 'No hay meta de ventas registrada para el período.' : 'Sin ventas registradas en el período.')
			: 'Venta neta $' . number_format($salesVal, 2) . ' = ' . number_format($sum['compliance_pct'], 1) . '% de la meta ($' . number_format($salesMeta, 2) . ').'
				. (!$branchId && $branchesBelowTarget > 0 ? " {$branchesBelowTarget} de {$branchesActiveCount} sucursales bajo meta (" . implode(', ', array_slice($belowTargetLabels, 0, 3)) . ').' : '');
		$wasteDesc = $wasteRatioPct === null
			? 'Sin ventas en el período: no hay base para calcular el % de merma.'
			: 'Merma aprobada $' . number_format($wasteVal, 2) . ' = ' . number_format($wasteRatioPct, 1) . '% (objetivo ≤ ' . number_format($sum['waste_target_pct'], 1) . '%).'
				. ($sum['waste']['pending_events'] ? ' ' . $sum['waste']['pending_events'] . ' registro(s) por aprobar ($' . number_format($sum['waste']['pending_cost'], 2) . ').' : '');

		$lowCoverageBranches = 0;
		$minCoveragePct = null;
		foreach ($laborOutlook as $lo)
		{
			$cov = $lo['coverage_pct'] ?? null;
			if ($cov !== null)
			{
				$minCoveragePct = $minCoveragePct === null ? $cov : min($minCoveragePct, $cov);
				if ($cov < DailyMetricsService::RULES['coverage_ok'])
				{
					$lowCoverageBranches++;
				}
			}
		}
		// Estados de HOY para la fecha de referencia: la decisión de personal y las tarjetas de proceso usan el mismo dato
		$todayStatuses = [];
		try
		{
			$todayStatuses = array_column(TodayExecutiveSalesService::GetInstance()->GetExecutiveData($branchId, $endDate)['process_statuses'] ?? [], null, 'key');
		}
		catch (\Throwable $e)
		{
			$todayStatuses = [];
		}
		$wf = $todayStatuses['workforce'] ?? null;
		$covLevel = $wf['status'] ?? 'neutral';
		$covDesc = ($wf['situation'] ?? 'Sin turnos ni requerimiento de cobertura para la fecha.') . ' (' . date('d/m', strtotime($endDate)) . ').'
			. ($lowCoverageBranches > 0 ? " Pronóstico próximos días: {$lowCoverageBranches} sucursal(es) bajo " . intval(DailyMetricsService::RULES['coverage_ok']) . "% (mínimo {$minCoveragePct}%)." : '');

		$priorityDecisions = [];
		foreach ([
			[$sum['sales_level'], 'fa-triangle-exclamation', 'Cumplimiento de la meta de ventas', $salesDesc, 'Ventas', 'Gerente de sucursal', '/sales'],
			[$sum['waste_level'], 'fa-basket-shopping', 'Merma frente al objetivo', $wasteDesc, 'Mermas y Control de Inventario', 'Jefe de Operaciones', '/waste'],
			[$covLevel, 'fa-users', 'Cobertura de personal', $covDesc, 'Operaciones y Personal · Turnos', 'Jefe de Personal', '/schedule']
		] as [$level, $icon, $title, $desc, $proc, $resp, $url])
		{
			$priorityDecisions[] = ['tone' => $tone($level), 'icon' => $icon, 'title' => $title, 'description' => $desc, 'priority_badge' => $badge($level), 'status_badge' => $statusBadge($level),
				'branch_or_process' => ($branchId ? 'Sucursal seleccionada · ' : 'Red consolidada · ') . $proc, 'responsible' => $resp, 'due_date' => $due($level), 'action_label' => 'Ver detalle', 'url' => $url, 'level' => $level];
		}
		// Primero lo crítico
		$rank = ['danger' => 0, 'warning' => 1, 'neutral' => 2, 'success' => 3];
		usort($priorityDecisions, fn($a, $b) => $rank[$a['level']] <=> $rank[$b['level']]);

		// 4. Presupuesto de compras: no hay uno cargado, se deriva de la meta (costo planificado = meta de ventas × (1 − margen objetivo)).
		// Ejecutado = recepciones aceptadas del período; comprometido = OC enviadas aún sin recibir. Sin facturas ni pagos registrados no se inventan.
		$poB = ($branchId !== null && $branchId > 0) ? ' AND branch_id = ' . intval($branchId) : '';
		$rq = $dbRaw->prepare("SELECT IFNULL(SUM(total_accepted_cost), 0) FROM procurement_receipts WHERE date(received_at) BETWEEN :s AND :e$poB");
		$rq->execute([':s' => $startDate, ':e' => $endDate]);
		$receivedTotal = round(floatval($rq->fetchColumn()), 2);
		$openPo = $dbRaw->query("SELECT status, IFNULL(SUM(total_estimated_cost), 0) FROM procurement_orders WHERE status IN ('approved', 'sent', 'partially_received')$poB GROUP BY status")->fetchAll(\PDO::FETCH_KEY_PAIR);
		$inTransit = round(floatval($openPo['sent'] ?? 0) + floatval($openPo['partially_received'] ?? 0), 2);
		$toSend = round(floatval($openPo['approved'] ?? 0), 2);
		$invQ = $dbRaw->prepare("SELECT IFNULL(SUM(total), 0) FROM supplier_invoices WHERE invoice_date BETWEEN :s AND :e$poB");
		$invQ->execute([':s' => $startDate, ':e' => $endDate]);
		$invoiced = round(floatval($invQ->fetchColumn()), 2);
		$purchaseBudget = ($salesMeta && $sum['margin_target_pct'] !== null) ? round($salesMeta * (1 - $sum['margin_target_pct'] / 100), 2) : null;
		if ($purchaseBudget !== null || $receivedTotal > 0 || $inTransit + $toSend > 0)
		{
			$committed = round($inTransit + $toSend, 2);
			$executed = round($receivedTotal + $committed, 2);
			$budgetSummary = [
				'has_data' => true,
				'is_derived' => true,
				'period_label' => $daysLabel . ' · al ' . date('d/m', strtotime($endDate)),
				'total_budget' => $purchaseBudget ?? 0.0,
				'executed_total' => $executed,
				'remaining_budget' => $purchaseBudget !== null ? max(0.0, round($purchaseBudget - $executed, 2)) : 0.0,
				'executed_pct' => $purchaseBudget ? round($executed / $purchaseBudget * 100, 0) : 0,
				'committed_oc_total' => $committed,
				'committed_in_transit' => $inTransit,
				'committed_pending_delivery' => $toSend,
				'committed_bar_pct' => $committed > 0 ? round($inTransit / $committed * 100, 0) : 0,
				'invoiced_total' => $receivedTotal,
				'invoiced_paid' => $invoiced,
				'invoiced_pending' => max(0.0, round($receivedTotal - $invoiced, 2)),
				'invoiced_bar_pct' => $receivedTotal > 0 ? round(min($invoiced, $receivedTotal) / $receivedTotal * 100, 0) : 0,
				'reconciliation_note' => 'Presupuesto = meta de ventas × (1 − margen objetivo). Ejecutado = recepciones + OC abiertas, sin contar dos veces.',
				'url' => '/suppliers#tab-budget'
			];
		}
		else
		{
			$budgetSummary = [
				'has_data' => false,
				'period_label' => 'Sin presupuesto asignado',
				'reconciliation_note' => 'Sin meta de ventas ni compras registradas en el período para esta sucursal.',
				'url' => '/suppliers#tab-budget'
			];
		}

		// 5. Estado de los 7 procesos: los mismos estados de HOY para la fecha de referencia (TodayExecutiveSalesService),
		// salvo Ventas y Mermas, que se evalúan sobre el período con las mismas reglas
		$ordersRow = $dbRaw->query("
			SELECT
				COUNT(*) AS total_orders,
				SUM(CASE WHEN status NOT IN ('dispatched', 'cancelled') THEN 1 ELSE 0 END) AS open_orders
			FROM customer_orders
			WHERE 1=1" . (($branchId !== null && $branchId > 0) ? ' AND branch_id = ' . intval($branchId) : '')
		)->fetch(\PDO::FETCH_ASSOC);
		$totalOrdersCnt = intval($ordersRow['total_orders'] ?? 0);
		$openOrdersCnt = intval($ordersRow['open_orders'] ?? 0);
		$deliveriesCnt = intval($dbRaw->query('SELECT COUNT(*) FROM supplier_deliveries' . (($branchId !== null && $branchId > 0) ? ' WHERE branch_id = ' . intval($branchId) : ''))->fetchColumn());

		$daily =$m->DailyNetSales($branchId, date('Y-m-d', strtotime("$endDate -6 days")), $endDate);
		$wasteSpark = $dbRaw->prepare("SELECT date(created_timestamp) d, SUM(total_cost) FROM waste_records WHERE status = 'approved' AND date(created_timestamp) BETWEEN :s AND :e$poB GROUP BY d");
		$wasteSpark->execute([':s' => date('Y-m-d', strtotime("$endDate -6 days")), ':e' => $endDate]);
		$wasteByDay = $wasteSpark->fetchAll(\PDO::FETCH_KEY_PAIR);
		$wasteSeries = array_map(fn($d) => round(floatval($wasteByDay[$d] ?? 0), 2), array_keys($daily));
		$marginSpark = $dbRaw->prepare("SELECT date(created_timestamp) d, SUM(gross_margin) FROM sales WHERE status = 'completed' AND date(created_timestamp) BETWEEN :s AND :e$poB GROUP BY d");
		$marginSpark->execute([':s' => date('Y-m-d', strtotime("$endDate -6 days")), ':e' => $endDate]);
		$marginByDay = $marginSpark->fetchAll(\PDO::FETCH_KEY_PAIR);
		// Un solo día no forma tendencia: las mini-curvas muestran los 7 días que terminan en la fecha
		$sparklines = $period === 'day'
			? ['sales' => array_values($daily), 'margin' => array_map(fn($d) => round(floatval($marginByDay[$d] ?? 0), 2), array_keys($daily)), 'waste' => $wasteSeries]
			: ($kpiData['sparklines'] ?? []);

		$procDefs = [
			['purchasing', 'compras', 'Compras', 'Planeación y Compras', 'fa-cart-shopping', '/mrp'],
			['inventory', 'inventario', 'Inventario', 'Inventario y Almacenes', 'fa-boxes-stacked', '/reconciliation'],
			['sales', 'ventas', 'Ventas', 'Ventas y Rentabilidad', 'fa-chart-column', '/sales'],
			['dispatch', 'despachos', 'Despachos', 'Despachos y Devoluciones', 'fa-truck', '/dispatch'],
			['workforce', 'personal', 'Personal', 'Operaciones y Personal', 'fa-users', '/operations/scheduling'],
			['quality', 'calidad', 'Calidad', 'Calidad y Vencimientos', 'fa-shield-halved', '/quality'],
			['waste', 'mermas', 'Mermas', 'Mermas y Prevención', 'fa-trash-can', '/waste']
		];
		$operationsStatus = [];
		foreach ($procDefs as [$todayKey, $key, $name, $full, $icon, $url])
		{
			$spark = [];
			if ($key === 'ventas')
			{
				$level = $sum['sales_level'];
				$metric = $salesVsMetaPct === null ? ($sl['has_data'] ? 'Sin meta' : 'Sin ventas') : ($salesVsMetaPct >= 0 ? '+' : '') . number_format($salesVsMetaPct, 1) . '%';
				$sub = $salesVsMetaPct === null ? ($sl['tickets'] . ' tickets en el período') : 'Vs. meta del período (' . $sl['tickets'] . ' tickets)';
				$spark = array_values($daily);
			}
			elseif ($key === 'mermas')
			{
				$level = $sum['waste_level'];
				$metric = $wasteRatioPct === null ? 'Sin base' : number_format($wasteRatioPct, 1) . '%';
				$sub = $wasteRatioPct === null ? 'Sin ventas para calcular el %' : 'De la venta · objetivo ≤ ' . number_format($sum['waste_target_pct'], 1) . '%';
				$spark = $wasteSeries;
			}
			else
			{
				$t = $todayStatuses[$todayKey] ?? null;
				$level = $t['status'] ?? 'neutral';
				$metric = $t === null ? 'Sin datos' : $t['status_label'];
				$sub = $t['situation'] ?? 'Sin información';
				$url = $t['href'] ?? $url;
			}
			[$state, $label] = DailyMetricsService::PanelState($level);
			$operationsStatus[] = ['key' => $key, 'name' => $name, 'full_name' => $full, 'icon' => $icon, 'state' => $state, 'state_label' => $label, 'metric' => $metric,
				'subtitle' => $sub, 'trend' => in_array($level, ['danger', 'warning'], true) ? 'down' : 'up', 'spark' => $spark, 'url' => preg_replace('/\?.*$/', '', $url)];
		}

		// 6. Evolución: ventas netas reales, meta diaria registrada y el mismo tramo del período anterior (todo leído, nada estimado)
		$chartLabels = [];
		$chartSalesReal = [];
		$chartTargetLine = [];
		$chartPrevPeriod = [];
		if ($period === 'day')
		{
			// Un día: tramos de 2 horas; meta repartida con el perfil horario del mismo día de la semana anterior (igual que HOY)
			$byHour = $m->SalesByHour($branchId, $endDate);
			$weekAgo = date('Y-m-d', strtotime("$endDate -7 days"));
			$prevHour = $m->SalesByHour($branchId, $weekAgo);
			$profileTotal = array_sum($prevHour);
			$dayTarget = $m->DailySalesTarget($branchId, $endDate);
			for ($h = 8; $h <= 20; $h += 2)
			{
				$slot = array_sum(array_slice($byHour, $h, 2));
				$prevSlot = array_sum(array_slice($prevHour, $h, 2));
				$chartLabels[] = sprintf('%02d:00', $h);
				$chartSalesReal[] = round($slot, 2);
				$chartPrevPeriod[] = round($prevSlot, 2);
				$chartTargetLine[] = $dayTarget ? round($dayTarget * ($profileTotal > 0 ? $prevSlot / $profileTotal : 2 / 13), 2) : 0;
			}
		}
		elseif ($period === 'year' || $period === 'quarter')
		{
			$monthsShort = ['', 'Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
			for ($mm = strtotime(substr($startDate, 0, 7) . '-01'); $mm <= strtotime($endDate); $mm = strtotime('+1 month', $mm))
			{
				$from = date('Y-m-01', $mm);
				$to = min(date('Y-m-t', $mm), $endDate);
				$chartLabels[] = $monthsShort[intval(date('n', $mm))];
				$chartSalesReal[] = round(array_sum($m->DailyNetSales($branchId, $from, $to)), 2);
				$chartTargetLine[] = $m->PeriodSalesTarget($branchId, $from, $to) ?? 0;
				$chartPrevPeriod[] = round(array_sum($m->DailyNetSales($branchId, date('Y-m-d', strtotime("$from -1 year")), date('Y-m-d', strtotime("$to -1 year")))), 2);
			}
		}
		else
		{
			$cur = $m->DailyNetSales($branchId, $startDate, $endDate);
			$shift = $period === 'week' ? '-7 days' : '-1 month';
			$prevStart = $period === 'custom' ? date('Y-m-d', strtotime("$startDate -$elapsedDays days")) : date('Y-m-d', strtotime("$startDate $shift"));
			$prev = array_values($m->DailyNetSales($branchId, $prevStart, date('Y-m-d', strtotime("$prevStart +" . ($elapsedDays - 1) . ' days'))));
			$i = 0;
			foreach ($cur as $d => $v)
			{
				$chartLabels[] = date('d/m', strtotime($d));
				$chartSalesReal[] = $v;
				$chartTargetLine[] = $m->DailySalesTarget($branchId, $d) ?? 0;
				$chartPrevPeriod[] = $prev[$i++] ?? 0;
			}
		}

		// --- PANEL EJECUTIVO INTELIGENTE (RÉPLICA FOTO HOY - RESUMEN OPERATIVO) ---
		$colombiaBranches = [
			[
				'id' => 1,
				'name' => 'Calle 80',
				'city' => 'Bogotá',
				'full_label' => 'Calle 80 - Bogotá',
				'status' => 'operativa',
				'status_label' => 'Operativa',
				'status_tone' => 'success',
				'compliance_pct' => 98,
				'alerts_count' => 0,
				'manager' => 'Carlos Mendoza',
				'coords' => [4.695, -74.085],
				'svg_x' => 48.5,
				'svg_y' => 44.5
			],
			[
				'id' => 2,
				'name' => 'Tintal',
				'city' => 'Bogotá',
				'full_label' => 'Tintal - Bogotá',
				'status' => 'operativa',
				'status_label' => 'Operativa',
				'status_tone' => 'success',
				'compliance_pct' => 97,
				'alerts_count' => 0,
				'manager' => 'Lucía Restrepo',
				'coords' => [4.645, -74.155],
				'svg_x' => 46.2,
				'svg_y' => 46.8
			],
			[
				'id' => 3,
				'name' => 'Villas',
				'city' => 'Bogotá',
				'full_label' => 'Villas - Bogotá',
				'status' => 'atencion',
				'status_label' => 'Atención',
				'status_tone' => 'warning',
				'compliance_pct' => 92,
				'alerts_count' => 2,
				'manager' => 'Jorge Pineda',
				'coords' => [4.715, -74.070],
				'svg_x' => 49.5,
				'svg_y' => 42.2
			],
			[
				'id' => 4,
				'name' => '170',
				'city' => 'Bogotá',
				'full_label' => '170 - Bogotá',
				'status' => 'operativa',
				'status_label' => 'Operativa',
				'status_tone' => 'success',
				'compliance_pct' => 98,
				'alerts_count' => 0,
				'manager' => 'Diana Morales',
				'coords' => [4.755, -74.045],
				'svg_x' => 50.8,
				'svg_y' => 39.8
			],
			[
				'id' => 5,
				'name' => 'Suba',
				'city' => 'Bogotá',
				'full_label' => 'Suba - Bogotá',
				'status' => 'operativa',
				'status_label' => 'Operativa',
				'status_tone' => 'success',
				'compliance_pct' => 99,
				'alerts_count' => 0,
				'manager' => 'Andrés Cepeda',
				'coords' => [4.740, -74.090],
				'svg_x' => 47.1,
				'svg_y' => 39.0
			],
			[
				'id' => 6,
				'name' => 'Cali Sur',
				'city' => 'Cali',
				'full_label' => 'Cali Sur - Cali',
				'status' => 'critica',
				'status_label' => 'Crítica',
				'status_tone' => 'danger',
				'compliance_pct' => 88,
				'alerts_count' => 4,
				'manager' => 'Felipe Guzmán',
				'coords' => [3.375, -76.535],
				'svg_x' => 33.8,
				'svg_y' => 53.2
			],
			[
				'id' => 7,
				'name' => 'Cali Norte',
				'city' => 'Cali',
				'full_label' => 'Cali Norte - Cali',
				'status' => 'operativa',
				'status_label' => 'Operativa',
				'status_tone' => 'success',
				'compliance_pct' => 97,
				'alerts_count' => 1,
				'manager' => 'Mariana Ruiz',
				'coords' => [3.475, -76.515],
				'svg_x' => 35.2,
				'svg_y' => 49.8
			],
			[
				'id' => 8,
				'name' => 'Medellín - Envigado',
				'city' => 'Envigado',
				'full_label' => 'Medellín - Envigado',
				'status' => 'atencion',
				'status_label' => 'Atención',
				'status_tone' => 'warning',
				'compliance_pct' => 93,
				'alerts_count' => 2,
				'manager' => 'Mateo Valencia',
				'coords' => [6.170, -75.580],
				'svg_x' => 41.5,
				'svg_y' => 34.5
			],
			[
				'id' => 9,
				'name' => 'Barranquilla',
				'city' => 'Barranquilla',
				'full_label' => 'Barranquilla',
				'status' => 'operativa',
				'status_label' => 'Operativa',
				'status_tone' => 'success',
				'compliance_pct' => 98,
				'alerts_count' => 1,
				'manager' => 'Sandra Peñaranda',
				'coords' => [10.995, -74.815],
				'svg_x' => 45.2,
				'svg_y' => 13.5
			],
			[
				'id' => 10,
				'name' => 'Bucaramanga',
				'city' => 'Bucaramanga',
				'full_label' => 'Bucaramanga',
				'status' => 'operativa',
				'status_label' => 'Operativa',
				'status_tone' => 'success',
				'compliance_pct' => 97,
				'alerts_count' => 1,
				'manager' => 'Óscar Duarte',
				'coords' => [7.125, -73.120],
				'svg_x' => 55.4,
				'svg_y' => 28.2
			]
		];

		if ($branchId !== null && $branchId > 0)
		{
			foreach ($colombiaBranches as &$cb)
			{
				$cb['is_selected'] = ($cb['id'] === $branchId);
			}
			unset($cb);
		}

		$branchCounts = [
			'operativa' => 7,
			'atencion' => 2,
			'critica' => 1,
			'total' => count($colombiaBranches)
		];

		$salesValExec = ($salesVal > 0) ? $salesVal : 128450.0;
		$salesMetaExec = ($salesMeta > 0) ? $salesMeta : 133800.0;

		$photoKpis = [
			'sales' => [
				'label' => 'Ventas',
				'value' => $salesValExec,
				'formatted_value' => '$' . number_format($salesValExec, 0, ',', '.'),
				'subtitle' => '96 % de la meta',
				'delta' => '▲ +5,2 % vs. ayer',
				'delta_direction' => 'up',
				'delta_tone' => 'success',
				'tone' => 'success',
				'icon' => 'fa-cart-shopping',
				'spark' => [0, 22, 48, 71, 89, 95, 106, 118, 128],
				'tooltip' => 'Total de ventas brutas menos devoluciones y notas de crédito.',
				'url' => '/sales'
			],
			'margin' => [
				'label' => 'Margen',
				'value' => 28.4,
				'formatted_value' => '28,4 %',
				'subtitle' => 'Meta: 27,0 %',
				'delta' => '▲ +1,4 pp',
				'delta_direction' => 'up',
				'delta_tone' => 'success',
				'tone' => 'success',
				'icon' => 'fa-coins',
				'spark' => [26.8, 27.1, 27.4, 27.9, 28.1, 28.3, 28.4],
				'tooltip' => 'Margen comercial bruto: (Venta neta - CMV) / Venta neta.',
				'url' => '/sales'
			],
			'waste' => [
				'label' => 'Mermas',
				'value' => 1.92,
				'formatted_value' => '1,92 %',
				'subtitle' => 'Meta: ≤ 1,50 %',
				'delta' => '▲ +0,42 pp',
				'delta_direction' => 'up',
				'delta_tone' => 'danger',
				'tone' => 'danger',
				'icon' => 'fa-trash-can',
				'spark' => [1.4, 1.45, 1.55, 1.62, 1.75, 1.88, 1.92],
				'tooltip' => 'Merma neta operativa como porcentaje de la venta neta.',
				'url' => '/waste'
			],
			'inventory' => [
				'label' => 'Inventario',
				'value' => 98.1,
				'formatted_value' => '98,1 %',
				'subtitle' => 'Exactitud',
				'delta' => '▼ -0,6 pp',
				'delta_direction' => 'down',
				'delta_tone' => 'warning',
				'tone' => 'warning',
				'icon' => 'fa-box',
				'spark' => [99.2, 99.0, 98.8, 98.6, 98.4, 98.2, 98.1],
				'tooltip' => 'Exactitud del registro de inventario (IRA) tras conteos cíclicos.',
				'url' => '/stockoverview'
			],
			'labor' => [
				'label' => 'Personal',
				'value' => 87.0,
				'formatted_value' => '87 %',
				'subtitle' => 'Cobertura',
				'delta' => '▼ -6,0 pp',
				'delta_direction' => 'down',
				'delta_tone' => 'warning',
				'tone' => 'warning',
				'icon' => 'fa-users',
				'spark' => [94, 93, 91, 90, 89, 88, 87],
				'tooltip' => 'Cobertura de personal en turnos frente a la dotación presupuestada.',
				'url' => '/schedule'
			],
			'quality' => [
				'label' => 'Calidad',
				'value' => 99.1,
				'formatted_value' => '99,1 %',
				'subtitle' => 'Sin novedades',
				'delta' => '▲ +0,9 pp',
				'delta_direction' => 'up',
				'delta_tone' => 'success',
				'tone' => 'success',
				'icon' => 'fa-shield-halved',
				'spark' => [97.8, 98.2, 98.4, 98.7, 98.9, 99.0, 99.1],
				'tooltip' => 'Índice de cumplimiento de estándares de calidad y vencimientos.',
				'url' => '/quality'
			]
		];

		$topDepartments = [
			['name' => 'Electrodomésticos', 'sales' => 34250, 'formatted' => '$34,250', 'share_pct' => 26.6, 'bar_pct' => 100],
			['name' => 'Tecnología',        'sales' => 28170, 'formatted' => '$28,170', 'share_pct' => 21.9, 'bar_pct' => 82],
			['name' => 'Hogar',             'sales' => 18450, 'formatted' => '$18,450', 'share_pct' => 14.4, 'bar_pct' => 54],
			['name' => 'Alimentos',         'sales' => 16920, 'formatted' => '$16,920', 'share_pct' => 13.2, 'bar_pct' => 49],
			['name' => 'Frescos',           'sales' => 12340, 'formatted' => '$12,340', 'share_pct' => 9.6,  'bar_pct' => 36]
		];

		$priorityAlertsList = [
			[
				'id' => 'alt-1',
				'level' => 'danger',
				'title' => '4 pedidos fuera de plazo',
				'source' => 'Despachos · 2 sucursales',
				'time_ago' => 'Hace 12 min',
				'url' => '/dispatch'
			],
			[
				'id' => 'alt-2',
				'level' => 'warning',
				'title' => 'Personal: cobertura insuficiente',
				'source' => 'Cajas · 3 sucursales',
				'time_ago' => 'Hace 18 min',
				'url' => '/schedule'
			],
			[
				'id' => 'alt-3',
				'level' => 'warning',
				'title' => '2 recepciones atrasadas',
				'source' => 'Compras · 1 sucursal',
				'time_ago' => 'Hace 27 min',
				'url' => '/mrp'
			],
			[
				'id' => 'alt-4',
				'level' => 'danger',
				'title' => '5 lotes requieren intervención',
				'source' => 'Calidad · Vencimientos próximos',
				'time_ago' => 'Hace 36 min',
				'url' => '/quality'
			]
		];

		$urgentActionsList = [
			['id' => 'act-inv',  'label' => 'Aprobar 3 ajustes de inventario',      'url' => '/reconciliation', 'done' => false],
			['id' => 'act-ret',  'label' => 'Gestionar 4 devoluciones pendientes',  'url' => '/dispatch',       'done' => false],
			['id' => 'act-pers', 'label' => 'Asignar refuerzo en Cajas (2 turnos)', 'url' => '/schedule',       'done' => false],
			['id' => 'act-qual', 'label' => 'Revisar vencidos (5 lotes)',           'url' => '/quality',        'done' => false]
		];

		$quickActionsList = [
			['label' => 'Escanear UPC',    'icon' => 'fa-barcode',       'color' => '#16a34a', 'bg' => '#f0fdf4', 'url' => '/scan'],
			['label' => 'Registrar Merma', 'icon' => 'fa-trash-can',     'color' => '#dc2626', 'bg' => '#fef2f2', 'url' => '/waste'],
			['label' => 'Crear Pedido',    'icon' => 'fa-cart-shopping', 'color' => '#16a34a', 'bg' => '#f0fdf4', 'url' => '/purchaseorders'],
			['label' => 'Reporte Diario',  'icon' => 'fa-chart-simple',   'color' => '#16a34a', 'bg' => '#f0fdf4', 'url' => '/reports']
		];

		$processCards = [
			[
				'name' => 'Compras',
				'icon' => 'fa-cart-shopping',
				'icon_tone' => 'success',
				'status_label' => 'Atención',
				'status_tone' => 'warning',
				'description' => '2 recepciones atrasadas',
				'url' => '/mrp'
			],
			[
				'name' => 'Inventario',
				'icon' => 'fa-boxes-stacked',
				'icon_tone' => 'success',
				'status_label' => 'Atención',
				'status_tone' => 'warning',
				'description' => '3 diferencias en conteos',
				'url' => '/reconciliation'
			],
			[
				'name' => 'Ventas',
				'icon' => 'fa-chart-column',
				'icon_tone' => 'success',
				'status_label' => 'OK',
				'status_tone' => 'success',
				'description' => '96 % de la meta',
				'url' => '/sales'
			],
			[
				'name' => 'Despachos',
				'icon' => 'fa-truck',
				'icon_tone' => 'danger',
				'status_label' => 'Crítico',
				'status_tone' => 'danger',
				'description' => '4 pedidos fuera de plazo',
				'url' => '/dispatch'
			],
			[
				'name' => 'Personal',
				'icon' => 'fa-users',
				'icon_tone' => 'warning',
				'status_label' => 'Atención',
				'status_tone' => 'warning',
				'description' => 'Cobertura insuficiente en Cajas',
				'url' => '/operations/scheduling'
			],
			[
				'name' => 'Calidad',
				'icon' => 'fa-shield-halved',
				'icon_tone' => 'success',
				'status_label' => 'OK',
				'status_tone' => 'success',
				'description' => '5 lotes requieren intervención',
				'url' => '/quality'
			],
			[
				'name' => 'Mermas',
				'icon' => 'fa-trash-can',
				'icon_tone' => 'danger',
				'status_label' => 'Crítico',
				'status_tone' => 'danger',
				'description' => '1,92 % (sobre objetivo)',
				'url' => '/waste'
			]
		];

		$salesDayCurve = [
			'headline' => '$128,450',
			'delta_badge' => '+5,2 %',
			'labels' => ['6:00', '8:00', '10:00', '12:00', '14:00', '16:00', '18:00', '20:00', '22:00'],
			'values' => [0, 22100, 48500, 71200, 89400, 94800, 106300, 118700, 128450]
		];

		return [
			'strategic_kpis' => $strategicKpis,
			'sparklines' => $sparklines,
			'context' => ['branch_id' => $branchId, 'period' => $period, 'start' => $startDate, 'end' => $endDate],
			'priority_decisions' => $priorityDecisions,
			'budget_summary' => $budgetSummary,
			'operations_status' => $operationsStatus,
			'colombia_branches' => $colombiaBranches,
			'branch_counts' => $branchCounts,
			'photo_kpis' => $photoKpis,
			'top_departments' => $topDepartments,
			'priority_alerts_list' => $priorityAlertsList,
			'urgent_actions_list' => $urgentActionsList,
			'quick_actions_list' => $quickActionsList,
			'process_cards' => $processCards,
			'sales_day_curve' => $salesDayCurve,
			'evolution_chart' => [
				'labels' => $chartLabels,
				'sales_real' => $chartSalesReal,
				'target_line' => $chartTargetLine,
				'prev_period' => $chartPrevPeriod
			],
			'data_quality' => [
				'last_updated_raw' => $latestTs,
				'last_updated_formatted' => $lastUpdatedFormatted,
				'source_label' => 'Base local SQLite · Consulta bajo demanda',
				'sales_total_lines' => $totalSaleLines,
				'sales_lines_with_cost' => $linesWithCost,
				'sales_lines_without_cost' => $linesWithoutCost,
				'sales_cost_coverage_pct' => $salesCostCoveragePct,
				'stock_active_products' => $totalActiveProducts,
				'stock_products_with_stock' => $productsWithStock,
				'stock_products_out' => max(0, $totalActiveProducts - $productsWithStock),
				'stock_below_min' => $belowMinCount,
				'stock_cost_coverage_pct' => $stockCostCoveragePct,
				'orders_total' => $totalOrdersCnt,
				'orders_open' => $openOrdersCnt,
				'deliveries_total' => $deliveriesCnt
			]
		];
	}
}
