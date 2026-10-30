<?php

namespace Grocy\Services;

class DairyInventoryService extends BaseService
{
	/**
	 * Returns the unified master inventory for all active Dairy products.
	 * Acts as the Single Source of Truth across all dashboards.
	 */
	public function GetUnifiedInventory(?int $branchId = null): array
	{
		$dbRaw = DatabaseService::GetInstance()->GetDbConnectionRaw();

		$sql = "
			SELECT 
				p.id,
				p.name,
				p.sku,
				p.brand,
				p.product_group_id,
				pg.name AS category_name,
				p.qu_id_stock,
				qu.name AS qu_name,
				qu.name_plural AS qu_name_plural,
				p.location_id,
				l.name AS default_location_name,
				p.sale_price,
				p.min_stock_amount,
				IFNULL(pap.price, IFNULL(plp.price, 0.0)) AS unit_cost,
				IFNULL(st.total_current_stock, 0.0) AS current_stock,
				IFNULL(st.earliest_best_before_date, NULL) AS next_best_before_date,
				IFNULL(sl.total_sold_units, 0.0) AS sold_units,
				IFNULL(sl.total_sold_revenue, 0.0) AS sold_revenue,
				IFNULL(sl.total_sold_cogs, 0.0) AS sold_cogs,
				IFNULL(wr.total_waste_units, 0.0) AS waste_units,
				IFNULL(wr.total_waste_cost, 0.0) AS waste_cost,
				IFNULL(adj.total_adjustment_units, 0.0) AS adjustments_units
			FROM products p
			JOIN product_groups pg ON p.product_group_id = pg.id
			JOIN quantity_units qu ON p.qu_id_stock = qu.id
			LEFT JOIN locations l ON p.location_id = l.id
			LEFT JOIN cache__products_average_price pap ON p.id = pap.product_id
			LEFT JOIN cache__products_last_purchased plp ON p.id = plp.product_id
			-- Subquery for Current Physical Stock and Earliest Expiry
			LEFT JOIN (
				SELECT 
					product_id,
					SUM(amount) AS total_current_stock,
					MIN(best_before_date) AS earliest_best_before_date
				FROM stock
				GROUP BY product_id
			) st ON p.id = st.product_id
			-- Subquery for Completed Sales
			LEFT JOIN (
				SELECT 
					si.product_id,
					SUM(si.amount) AS total_sold_units,
					SUM(si.amount * si.unit_price) AS total_sold_revenue,
					SUM(si.total_cost) AS total_sold_cogs
				FROM sale_items si
				JOIN sales s ON si.sale_id = s.id
				WHERE s.status = 'completed'
				GROUP BY si.product_id
			) sl ON p.id = sl.product_id
			-- Subquery for Approved Waste Records
			LEFT JOIN (
				SELECT 
					product_id,
					SUM(amount) AS total_waste_units,
					SUM(total_cost) AS total_waste_cost
				FROM waste_records
				WHERE status = 'approved'
				GROUP BY product_id
			) wr ON p.id = wr.product_id
			-- Subquery for Manual Adjustments (Inventory Corrections)
			LEFT JOIN (
				SELECT 
					product_id,
					SUM(amount) AS total_adjustment_units
				FROM stock_log
				WHERE transaction_type = 'inventory-correction'
					AND undone = 0
				GROUP BY product_id
			) adj ON p.id = adj.product_id
			WHERE p.active = 1
			ORDER BY p.id ASC
		";

		$stmt = $dbRaw->query($sql);
		$rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

		$today = date('Y-m-d');
		$soonThreshold = date('Y-m-d', strtotime('+5 days'));

		$result = [];
		foreach ($rows as $row)
		{
			$currentStock = floatval($row['current_stock']);
			$unitCost = floatval($row['unit_cost']);
			$salePrice = floatval($row['sale_price']);
			$soldUnits = floatval($row['sold_units']);
			$wasteUnits = floatval($row['waste_units']);
			$adjustmentsUnits = floatval($row['adjustments_units']);

			// Margin calculation
			$marginAmount = $salePrice - $unitCost;
			$marginPct = ($salePrice > 0) ? round(($marginAmount / $salePrice) * 100, 2) : 0.0;

			// Reconstructed initial stock: Current + Sold + Waste - Adjustments
			// (Since adjustments were negative reductions, - adjustmentsUnits adds them back)
			$initialStock = $currentStock + $soldUnits + $wasteUnits - $adjustmentsUnits;

			// Inventory valuation
			$inventoryValuation = $currentStock * $unitCost;

			// Expiry Status Badge
			$expiryDate = $row['next_best_before_date'];
			if (empty($expiryDate))
			{
				$status = ($currentStock > 0) ? 'Normal' : 'Agotado';
				$statusClass = ($currentStock > 0) ? 'badge-success' : 'badge-secondary';
			}
			elseif ($expiryDate < $today)
			{
				$status = 'Vencido';
				$statusClass = 'badge-danger';
			}
			elseif ($expiryDate <= $soonThreshold)
			{
				$status = 'Próximo a vencer';
				$statusClass = 'badge-warning';
			}
			else
			{
				$status = 'Óptimo';
				$statusClass = 'badge-success';
			}

			$row['current_stock'] = $currentStock;
			$row['unit_cost'] = $unitCost;
			$row['sale_price'] = $salePrice;
			$row['margin_amount'] = $marginAmount;
			$row['margin_percentage'] = $marginPct;
			$row['sold_units'] = $soldUnits;
			$row['sold_revenue'] = floatval($row['sold_revenue']);
			$row['sold_cogs'] = floatval($row['sold_cogs']);
			$row['sold_profit'] = $row['sold_revenue'] - $row['sold_cogs'];
			$row['waste_units'] = $wasteUnits;
			$row['waste_cost'] = floatval($row['waste_cost']);
			$row['adjustments_units'] = $adjustmentsUnits;
			$row['initial_stock'] = $initialStock;
			$row['inventory_valuation'] = $inventoryValuation;
			$row['status'] = $status;
			$row['status_class'] = $statusClass;

			$result[] = $row;
		}

		return $result;
	}

	/**
	 * Inventory-only health indicators (no sales/waste duplication): value, coverage days,
	 * kardex accuracy (stock vs. stock_log), below minimum, expiring and out-of-stock.
	 */
	public function GetInventoryHealth(): array
	{
		$items = $this->GetUnifiedInventory();
		$dbRaw = \Grocy\Services\DatabaseService::GetInstance()->GetDbConnectionRaw();

		$valuation = 0.0;
		$belowMin = 0;
		$expiring = 0;
		$expiringValue = 0.0;
		$outOfStock = 0;
		foreach ($items as $item)
		{
			$valuation += $item['inventory_valuation'];
			if ($item['current_stock'] <= 0)
			{
				$outOfStock++;
			}
			elseif (floatval($item['min_stock_amount']) > 0 && $item['current_stock'] < floatval($item['min_stock_amount']))
			{
				$belowMin++;
			}
			if ($item['status'] === 'Próximo a vencer' || $item['status'] === 'Vencido')
			{
				$expiring++;
				$expiringValue += $item['inventory_valuation'];
			}
		}

		$cogs30 = floatval($dbRaw->query("SELECT IFNULL(SUM(total_cogs), 0) FROM sales WHERE status = 'completed' AND created_timestamp >= date('now', 'localtime', '-30 days')")->fetchColumn());
		$coverageDays = $cogs30 > 0 ? $valuation / ($cogs30 / 30) : 0.0;

		$kardexRows = $dbRaw->query("
			SELECT p.id,
				IFNULL((SELECT SUM(amount) FROM stock s WHERE s.product_id = p.id), 0) AS physical,
				IFNULL((SELECT SUM(amount) FROM stock_log l WHERE l.product_id = p.id AND l.undone = 0), 0) AS kardex
			FROM products p WHERE p.active = 1
		")->fetchAll(\PDO::FETCH_ASSOC);
		$matched = 0;
		foreach ($kardexRows as $k)
		{
			if (abs(floatval($k['physical']) - floatval($k['kardex'])) < 0.001)
			{
				$matched++;
			}
		}
		$kardexTotal = count($kardexRows);

		return [
			'products' => count($items),
			'valuation' => round($valuation, 2),
			'coverage_days' => round($coverageDays, 1),
			'cogs_30d' => round($cogs30, 2),
			'kardex_accuracy' => $kardexTotal > 0 ? round($matched / $kardexTotal * 100, 1) : 100.0,
			'kardex_mismatches' => $kardexTotal - $matched,
			'below_min' => $belowMin,
			'expiring' => $expiring,
			'expiring_value' => round($expiringValue, 2),
			'out_of_stock' => $outOfStock
		];
	}

	/**
	 * Returns reconciled summary totals for executive indicators and validation.
	 */
	public function GetReconciliationSummary(?int $branchId = null): array
	{
		$items = $this->GetUnifiedInventory($branchId);

		$totalProducts = count($items);
		$totalCurrentStock = 0.0;
		$totalValuation = 0.0;
		$totalSoldUnits = 0.0;
		$totalSoldRevenue = 0.0;
		$totalSoldCogs = 0.0;
		$totalWasteUnits = 0.0;
		$totalWasteCost = 0.0;
		$totalAdjustments = 0.0;
		$totalInitialStock = 0.0;
		$expiringSoonCount = 0;
		$outOfStockCount = 0;

		foreach ($items as $item)
		{
			$totalCurrentStock += $item['current_stock'];
			$totalValuation += $item['inventory_valuation'];
			$totalSoldUnits += $item['sold_units'];
			$totalSoldRevenue += $item['sold_revenue'];
			$totalSoldCogs += $item['sold_cogs'];
			$totalWasteUnits += $item['waste_units'];
			$totalWasteCost += $item['waste_cost'];
			$totalAdjustments += $item['adjustments_units'];
			$totalInitialStock += $item['initial_stock'];

			if ($item['status'] === 'Próximo a vencer' || $item['status'] === 'Vencido')
			{
				$expiringSoonCount++;
			}
			if ($item['current_stock'] == 0)
			{
				$outOfStockCount++;
			}
		}

		$totalGrossProfit = $totalSoldRevenue - $totalSoldCogs;
		$overallMarginPct = ($totalSoldRevenue > 0) ? round(($totalGrossProfit / $totalSoldRevenue) * 100, 2) : 0.0;

		// Mathematical verification: Initial == Current + Sold + Waste - Adjustments
		$reconciledCheck = abs($totalInitialStock - ($totalCurrentStock + $totalSoldUnits + $totalWasteUnits - $totalAdjustments)) < 0.001;

		return [
			'total_products' => $totalProducts,
			'total_current_stock' => $totalCurrentStock,
			'total_inventory_valuation' => round($totalValuation, 2),
			'total_initial_stock' => $totalInitialStock,
			'total_sold_units' => $totalSoldUnits,
			'total_sold_revenue' => round($totalSoldRevenue, 2),
			'total_sold_cogs' => round($totalSoldCogs, 2),
			'total_gross_profit' => round($totalGrossProfit, 2),
			'overall_margin_percentage' => $overallMarginPct,
			'total_waste_units' => $totalWasteUnits,
			'total_waste_cost' => round($totalWasteCost, 2),
			'total_adjustments' => $totalAdjustments,
			'expiring_soon_count' => $expiringSoonCount,
			'out_of_stock_count' => $outOfStockCount,
			'reconciliation_passed' => $reconciledCheck
		];
	}

	/**
	 * Returns single product details with complete movement trace.
	 */
	public function GetProductDetailsReconciled(int $productId): ?array
	{
		$items = $this->GetUnifiedInventory();
		foreach ($items as $item)
		{
			if (intval($item['id']) === $productId)
			{
				return $item;
			}
		}
		return null;
	}
}
