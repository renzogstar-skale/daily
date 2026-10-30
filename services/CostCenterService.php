<?php

namespace Grocy\Services;

/**
 * Centros de costo (equivalente simplificado a SAP CO / CO-PA): cada sucursal es un centro de costo y cada
 * categoría de producto un segmento. Por mes: ventas netas, costo de ventas (CMV real de los lotes vendidos),
 * margen bruto, merma aprobada a costo, merma % de la venta y margen después de merma, con el mes anterior.
 */
class CostCenterService extends BaseService
{
	public function GetReport(string $month, ?int $branchId = null): array
	{
		if (!preg_match('/^\d{4}-\d{2}$/', $month))
		{
			$month = date('Y-m');
		}
		$prev = date('Y-m', strtotime($month . '-01 -1 month'));

		$current = $this->Aggregate($month);
		$previous = $this->Aggregate($prev);

		$branches = DatabaseService::GetInstance()->GetDbConnectionRaw()->query('SELECT id, name, code FROM branches WHERE active = 1 OR active IS NULL ORDER BY id')->fetchAll(\PDO::FETCH_ASSOC);
		// Presupuesto: proporcional a los días transcurridos si es el mes en curso
		$budgetService = BudgetService::GetInstance();
		$daysInMonth = intval(date('t', strtotime($month . '-01')));
		$elapsed = $month === date('Y-m') ? intval(date('j')) : ($month < date('Y-m') ? $daysInMonth : 0);
		$factor = $daysInMonth > 0 ? $elapsed / $daysInMonth : 0;
		$withBudget = function (array $row, ?int $bid) use ($budgetService, $month, $factor) {
			$t = $budgetService->GetTargets($bid, $month);
			$row['targets'] = $t;
			$row['waste_budget_to_date'] = $t['waste_budget_amount'] !== null ? round($t['waste_budget_amount'] * $factor, 2) : null;
			$row['waste_budget_deviation'] = $row['waste_budget_to_date'] !== null ? round($row['waste'] - $row['waste_budget_to_date'], 2) : null;
			$row['sales_target_to_date'] = $t['sales_target'] !== null ? round($t['sales_target'] * $factor, 2) : null;
			$row['sales_vs_target_pct'] = $row['sales_target_to_date'] ? round($row['sales'] / $row['sales_target_to_date'] * 100, 1) : null;
			$row['waste_over_target'] = $row['waste_pct'] !== null && $row['waste_pct'] > $t['waste_target_pct'];
			return $row;
		};

		$byBranch = [];
		foreach ($branches as $b)
		{
			$byBranch[] = $withBudget($this->Row($b['name'], $current['branch'][$b['id']] ?? [], $previous['branch'][$b['id']] ?? [], ['id' => $b['id'], 'code' => $b['code']]), intval($b['id']));
		}

		$catKey = $branchId ? 'branch_category' : 'category';
		$cats = [];
		foreach (array_unique(array_merge(array_keys($branchId ? ($current[$catKey][$branchId] ?? []) : $current[$catKey]), array_keys($branchId ? ($previous[$catKey][$branchId] ?? []) : $previous[$catKey]))) as $cat)
		{
			$cur = $branchId ? ($current[$catKey][$branchId][$cat] ?? []) : ($current[$catKey][$cat] ?? []);
			$old = $branchId ? ($previous[$catKey][$branchId][$cat] ?? []) : ($previous[$catKey][$cat] ?? []);
			$cats[] = $this->Row($cat, $cur, $old);
		}
		usort($cats, fn($a, $b) => $b['waste'] <=> $a['waste']);

		return [
			'month' => $month,
			'previous_month' => $prev,
			'total' => $withBudget($this->Row('Red', $current['total'], $previous['total']), null),
			'month_factor' => $factor,
			'branches' => $byBranch,
			'categories' => $cats
		];
	}

	private function Row(string $name, array $c, array $p, array $extra = []): array
	{
		$sales = floatval($c['sales'] ?? 0);
		$cogs = floatval($c['cogs'] ?? 0);
		$waste = floatval($c['waste'] ?? 0);
		$prevSales = floatval($p['sales'] ?? 0);
		$prevWaste = floatval($p['waste'] ?? 0);
		// Margen neto de IVA, igual que sales.gross_margin (venta − IVA − costo)
		$netSales = floatval($c['net_sales'] ?? $sales);
		$margin = $netSales - $cogs;
		$wastePct = $sales > 0 ? $waste / $sales * 100 : null;
		$prevWastePct = $prevSales > 0 ? $prevWaste / $prevSales * 100 : null;

		return $extra + [
			'name' => $name,
			'sales' => round($sales, 2),
			'cogs' => round($cogs, 2),
			'margin' => round($margin, 2),
			'net_sales' => round($netSales, 2),
			'margin_pct' => SalesService::MarginPct($margin, $netSales),
			'waste' => round($waste, 2),
			'waste_pct' => $wastePct !== null ? round($wastePct, 2) : null,
			'net_margin' => round($margin - $waste, 2),
			'net_margin_pct' => SalesService::MarginPct($margin - $waste, $netSales),
			'prev_sales' => round($prevSales, 2),
			'prev_waste' => round($prevWaste, 2),
			'prev_waste_pct' => $prevWastePct !== null ? round($prevWastePct, 2) : null,
			'waste_pct_delta' => ($wastePct !== null && $prevWastePct !== null) ? round($wastePct - $prevWastePct, 2) : null
		];
	}

	// Ventas/CMV por línea de venta y merma aprobada, por sucursal, categoría y sucursal×categoría
	private function Aggregate(string $month): array
	{
		$db = DatabaseService::GetInstance()->GetDbConnectionRaw();
		$out = ['total' => [], 'branch' => [], 'category' => [], 'branch_category' => []];
		$add = function ($branch, $cat, $field, $value) use (&$out) {
			$cat = $cat ?: 'Sin categoría';
			$out['total'][$field] = ($out['total'][$field] ?? 0) + $value;
			$out['branch'][$branch][$field] = ($out['branch'][$branch][$field] ?? 0) + $value;
			$out['category'][$cat][$field] = ($out['category'][$cat][$field] ?? 0) + $value;
			$out['branch_category'][$branch][$cat][$field] = ($out['branch_category'][$branch][$cat][$field] ?? 0) + $value;
		};

		$stmt = $db->prepare("
			SELECT s.branch_id, pg.name AS cat, SUM(si.subtotal) AS sales, SUM(" . SalesService::SQL_ITEM_NET_SALES . ") AS net_sales, SUM(si.total_cost) AS cogs
			FROM sale_items si
			JOIN sales s ON s.id = si.sale_id
			LEFT JOIN products p ON p.id = si.product_id
			LEFT JOIN product_groups pg ON pg.id = p.product_group_id
			WHERE s.status = 'completed' AND strftime('%Y-%m', s.created_timestamp) = :m
			GROUP BY s.branch_id, pg.name
		");
		$stmt->execute([':m' => $month]);
		foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $r)
		{
			$add($r['branch_id'], $r['cat'], 'sales', floatval($r['sales']));
			$add($r['branch_id'], $r['cat'], 'net_sales', floatval($r['net_sales']));
			$add($r['branch_id'], $r['cat'], 'cogs', floatval($r['cogs']));
		}

		$stmt = $db->prepare("
			SELECT w.branch_id, pg.name AS cat, SUM(w.total_cost) AS waste
			FROM waste_records w
			LEFT JOIN products p ON p.id = w.product_id
			LEFT JOIN product_groups pg ON pg.id = p.product_group_id
			WHERE w.status = 'approved' AND strftime('%Y-%m', w.created_timestamp) = :m
			GROUP BY w.branch_id, pg.name
		");
		$stmt->execute([':m' => $month]);
		foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $r)
		{
			$add($r['branch_id'], $r['cat'], 'waste', floatval($r['waste']));
		}

		return $out;
	}
}
