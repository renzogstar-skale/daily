<?php

namespace Grocy\Services;

/**
 * Presupuesto mensual por sucursal (ventas, merma, margen, DSI) y base configurable del % de merma.
 * Prioridad de metas: presupuesto de la sucursal → presupuesto de la red (branch_id 0) → config-dist.php.
 */
class BudgetService extends BaseService
{
	const BASES = [
		'net_sales' => 'ventas netas',
		'gross_sales' => 'ventas con impuestos',
		'cogs' => 'costo de ventas'
	];

	public function GetTargets(?int $branchId, string $period): array
	{
		$db = DatabaseService::GetInstance()->GetDbConnectionRaw();
		$stmt = $db->prepare('SELECT * FROM dairy_budgets WHERE period = :p AND branch_id IN (:b, 0) ORDER BY branch_id DESC');
		$stmt->execute([':p' => $period, ':b' => intval($branchId ?? 0)]);
		$rows = $stmt->fetchAll(\PDO::FETCH_OBJ);

		// Campo por campo: el de la sucursal si existe, si no el de la red, si no el de configuración
		$pick = function (string $field, $fallback) use ($rows) {
			foreach ($rows as $r)
			{
				if ($r->$field !== null && $r->$field !== '')
				{
					return [floatval($r->$field), intval($r->branch_id) > 0 ? 'budget_branch' : 'budget_network'];
				}
			}
			return [$fallback, $fallback === null ? 'none' : 'config'];
		};

		[$sales, $salesSrc] = $pick('sales_target', null);
		[$wasteAmount, $wasteAmountSrc] = $pick('waste_budget_amount', null);
		[$labor, $laborSrc] = $pick('labor_budget_amount', null);
		[$wastePct, $wastePctSrc] = $pick('waste_target_pct', floatval(GROCY_WASTE_TARGET_PCT));
		[$marginPct, $marginSrc] = $pick('margin_target_pct', floatval(GROCY_MARGIN_TARGET_PCT));
		[$dsi, $dsiSrc] = $pick('dsi_target_days', floatval(GROCY_DSI_TARGET_DAYS));

		return [
			'period' => $period,
			'sales_target' => $sales,
			'waste_budget_amount' => $wasteAmount,
			'labor_budget_amount' => $labor,
			'waste_target_pct' => $wastePct,
			'margin_target_pct' => $marginPct,
			'dsi_target_days' => $dsi,
			'sources' => ['sales' => $salesSrc, 'waste_amount' => $wasteAmountSrc, 'labor' => $laborSrc, 'waste_pct' => $wastePctSrc, 'margin' => $marginSrc, 'dsi' => $dsiSrc],
			'has_budget' => count($rows) > 0
		];
	}

	public function GetBudgets(string $year): array
	{
		$db = DatabaseService::GetInstance()->GetDbConnectionRaw();
		$stmt = $db->prepare("SELECT b.*, IFNULL(br.name, 'Red consolidada') AS branch_name FROM dairy_budgets b LEFT JOIN branches br ON br.id = b.branch_id WHERE b.period LIKE :y ORDER BY b.period, b.branch_id");
		$stmt->execute([':y' => $year . '-%']);
		return $stmt->fetchAll(\PDO::FETCH_OBJ);
	}

	public function SaveBudget(array $data, int $userId): int
	{
		$period = trim(strval($data['period'] ?? ''));
		if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $period))
		{
			throw new \Exception('Período inválido (use AAAA-MM)');
		}
		$branchId = intval($data['branch_id'] ?? 0);
		$num = function ($key, $min = 0, $max = null) use ($data) {
			if (!isset($data[$key]) || $data[$key] === '' || $data[$key] === null)
			{
				return null;
			}
			$v = floatval($data[$key]);
			if ($v < $min || ($max !== null && $v > $max))
			{
				throw new \Exception("Valor fuera de rango: $key");
			}
			return $v;
		};
		$row = [
			':b' => $branchId, ':p' => $period,
			':s' => $num('sales_target'), ':wa' => $num('waste_budget_amount'), ':la' => $num('labor_budget_amount'), ':wp' => $num('waste_target_pct', 0, 100),
			':mp' => $num('margin_target_pct', -100, 100), ':d' => $num('dsi_target_days', 0, 365),
			':n' => trim(strval($data['notes'] ?? '')) ?: null, ':u' => $userId
		];
		$db = DatabaseService::GetInstance()->GetDbConnectionRaw();
		$db->prepare("INSERT INTO dairy_budgets (branch_id, period, sales_target, waste_budget_amount, labor_budget_amount, waste_target_pct, margin_target_pct, dsi_target_days, notes, updated_by_user_id, updated_timestamp)
			VALUES (:b, :p, :s, :wa, :la, :wp, :mp, :d, :n, :u, datetime('now', 'localtime'))
			ON CONFLICT (branch_id, period) DO UPDATE SET sales_target = excluded.sales_target, waste_budget_amount = excluded.waste_budget_amount, labor_budget_amount = excluded.labor_budget_amount,
				waste_target_pct = excluded.waste_target_pct, margin_target_pct = excluded.margin_target_pct, dsi_target_days = excluded.dsi_target_days,
				notes = excluded.notes, updated_by_user_id = excluded.updated_by_user_id, updated_timestamp = excluded.updated_timestamp")->execute($row);
		$id = $db->prepare('SELECT id FROM dairy_budgets WHERE branch_id = :b AND period = :p');
		$id->execute([':b' => $branchId, ':p' => $period]);
		return intval($id->fetchColumn());
	}

	public function DeleteBudget(int $id): void
	{
		DatabaseService::GetInstance()->GetDbConnectionRaw()->prepare('DELETE FROM dairy_budgets WHERE id = :id')->execute([':id' => $id]);
	}

	// Base del % de merma según WASTE_PCT_BASE
	// $grossSales = ventas con IVA (sales.total_amount), como lo pasan todos los llamadores; ventas netas = con IVA − IVA
	public static function WasteBase(float $grossSales, float $tax, float $cogs): array
	{
		$key = defined('GROCY_WASTE_PCT_BASE') && isset(self::BASES[GROCY_WASTE_PCT_BASE]) ? GROCY_WASTE_PCT_BASE : 'net_sales';
		$amount = $key === 'gross_sales' ? $grossSales : ($key === 'cogs' ? $cogs : $grossSales - $tax);
		return ['key' => $key, 'label' => self::BASES[$key], 'amount' => $amount];
	}

	public static function Pct(float $value, float $base): ?float
	{
		return $base > 0 ? round($value / $base * 100, 2) : null;
	}
}
