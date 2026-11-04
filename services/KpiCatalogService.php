<?php

namespace Grocy\Services;

use Grocy\Helpers\DairyAccess;

/**
 * Catálogo único de indicadores (Fase 1.4): nombre, fórmula, unidad, fuente, período, frecuencia, responsable y reglas
 * de validación de cada KPI, con un único cálculo por indicador. Reutiliza las definiciones ya vigentes
 * (SalesService::MarginPct, BudgetService::WasteBase/Pct, KpiService::SumRecovered) para que cualquier pantalla que
 * consulte el catálogo obtenga el mismo valor que el Panel Ejecutivo. Responsable y umbrales: daily_kpi_settings.
 */
class KpiCatalogService extends BaseService
{
	private array $Memo = [];

	/** period: range = suma en el rango de fechas · snapshot = foto al momento de la consulta */
	public function Definitions(): array
	{
		return [
			'gross_sales' => ['name' => 'Ventas (con IVA)', 'unit' => 'money', 'period' => 'range', 'frequency' => 'tiempo real', 'module' => 'ventas',
				'formula' => 'Σ sales.total_amount (status = completed)', 'source' => 'sales', 'direction' => 'up'],
			'net_sales' => ['name' => 'Ventas netas', 'unit' => 'money', 'period' => 'range', 'frequency' => 'tiempo real', 'module' => 'ventas',
				'formula' => 'Σ (sales.total_amount − sales.tax_amount) (status = completed)', 'source' => 'sales', 'direction' => 'up'],
			'tickets' => ['name' => 'Transacciones', 'unit' => 'count', 'period' => 'range', 'frequency' => 'tiempo real', 'module' => 'ventas',
				'formula' => 'COUNT(sales) (status = completed)', 'source' => 'sales', 'direction' => 'up'],
			'avg_ticket' => ['name' => 'Ticket promedio', 'unit' => 'money', 'period' => 'range', 'frequency' => 'tiempo real', 'module' => 'ventas',
				'formula' => 'ventas netas / transacciones', 'source' => 'sales', 'direction' => 'up'],
			'units_per_transaction' => ['name' => 'Unidades por transacción', 'unit' => 'number', 'period' => 'range', 'frequency' => 'tiempo real', 'module' => 'ventas',
				'formula' => 'Σ sale_items.amount / transacciones', 'source' => 'sale_items', 'direction' => 'up'],
			'cogs' => ['name' => 'Costo de ventas (CMV)', 'unit' => 'money', 'period' => 'range', 'frequency' => 'tiempo real', 'module' => 'ventas',
				'formula' => 'Σ sales.total_cogs (status = completed)', 'source' => 'sales', 'direction' => 'down'],
			'gross_margin' => ['name' => 'Margen bruto', 'unit' => 'money', 'period' => 'range', 'frequency' => 'tiempo real', 'module' => 'rentabilidad',
				'formula' => 'Σ sales.gross_margin (= venta − IVA − costo)', 'source' => 'sales', 'direction' => 'up'],
			'margin_pct' => ['name' => 'Margen bruto %', 'unit' => 'pct', 'period' => 'range', 'frequency' => 'tiempo real', 'module' => 'rentabilidad',
				'formula' => 'margen bruto / ventas netas × 100', 'source' => 'sales', 'direction' => 'up'],
			'sales_goal_attainment' => ['name' => 'Cumplimiento de meta de ventas', 'unit' => 'pct', 'period' => 'range', 'frequency' => 'tiempo real', 'module' => 'ventas',
				'formula' => 'ventas (con IVA) / (meta mensual presupuestada × días del rango / días del mes) × 100 · objetivo 100 %', 'source' => 'sales + dairy_budgets', 'direction' => 'up', 'target_default' => 100],
			'waste_cost' => ['name' => 'Merma a costo', 'unit' => 'money', 'period' => 'range', 'frequency' => 'tiempo real', 'module' => 'mermas',
				'formula' => 'Σ waste_records.total_cost (status = approved)', 'source' => 'waste_records', 'direction' => 'down'],
			'waste_pct' => ['name' => 'Merma %', 'unit' => 'pct', 'period' => 'range', 'frequency' => 'tiempo real', 'module' => 'mermas',
				'formula' => 'merma a costo / base configurada (GROCY_WASTE_PCT_BASE: ventas netas, ventas con IVA o CMV) × 100', 'source' => 'waste_records + sales', 'direction' => 'down'],
			'waste_recovered' => ['name' => 'Recuperación financiera', 'unit' => 'money', 'period' => 'range', 'frequency' => 'tiempo real', 'module' => 'mermas',
				'formula' => 'Σ waste_records.financial_recovery_amount (aprobadas) + Σ supplier_returns.credit_recovered_amount (resueltas)', 'source' => 'waste_records + supplier_returns', 'direction' => 'up'],
			'waste_net_loss' => ['name' => 'Pérdida neta por merma', 'unit' => 'money', 'period' => 'range', 'frequency' => 'tiempo real', 'module' => 'mermas',
				'formula' => 'merma a costo − recuperación financiera', 'source' => 'waste_records + supplier_returns', 'direction' => 'down'],
			'inventory_value' => ['name' => 'Inventario valorizado', 'unit' => 'money', 'period' => 'snapshot', 'frequency' => 'tiempo real', 'module' => 'inventario',
				'formula' => 'Σ stock.amount × IFNULL(stock.price, último precio de compra) en ubicaciones no de tránsito', 'source' => 'stock + locations', 'direction' => 'neutral'],
			'inventory_turnover' => ['name' => 'Rotación de inventario', 'unit' => 'ratio', 'period' => 'range', 'frequency' => 'tiempo real', 'module' => 'inventario',
				'formula' => 'CMV del rango / inventario valorizado', 'source' => 'sales + stock', 'direction' => 'up'],
			'days_of_inventory' => ['name' => 'Días de inventario', 'unit' => 'days', 'period' => 'range', 'frequency' => 'tiempo real', 'module' => 'inventario',
				'formula' => 'inventario valorizado / (CMV del rango / días transcurridos del rango)', 'source' => 'sales + stock', 'direction' => 'down'],
			'open_alerts' => ['name' => 'Alertas abiertas', 'unit' => 'count', 'period' => 'snapshot', 'frequency' => 'tiempo real', 'module' => 'alertas',
				'formula' => 'COUNT(dairy_alerts) en estado activo', 'source' => 'dairy_alerts', 'direction' => 'down'],
			'overdue_alerts' => ['name' => 'Alertas vencidas', 'unit' => 'count', 'period' => 'snapshot', 'frequency' => 'tiempo real', 'module' => 'alertas',
				'formula' => 'COUNT(dairy_alerts) activas con due_at < ahora', 'source' => 'dairy_alerts', 'direction' => 'down'],
			'overdue_workflows' => ['name' => 'Procesos vencidos', 'unit' => 'count', 'period' => 'snapshot', 'frequency' => 'tiempo real', 'module' => 'procesos',
				'formula' => 'COUNT(daily_workflow_instances) activos con due_at < ahora', 'source' => 'daily_workflow_instances', 'direction' => 'down'],
		];
	}

	/** Catálogo con responsable/umbrales configurados */
	public function Catalog(): array
	{
		$settings = [];
		foreach ($this->Db()->query('SELECT k.*, u.display_name AS owner_name FROM daily_kpi_settings k LEFT JOIN users_dto u ON u.id = k.owner_user_id')->fetchAll(\PDO::FETCH_ASSOC) as $s)
		{
			$settings[$s['code']] = $s;
		}
		$out = [];
		foreach ($this->Definitions() as $code => $d)
		{
			$s = $settings[$code] ?? [];
			$out[] = ['code' => $code] + $d + [
				'owner_user_id' => $s['owner_user_id'] ?? null, 'owner_name' => $s['owner_name'] ?? null,
				'target' => isset($s['target']) ? floatval($s['target']) : ($d['target_default'] ?? null),
				'warn_threshold' => isset($s['warn_threshold']) ? floatval($s['warn_threshold']) : null,
				'critical_threshold' => isset($s['critical_threshold']) ? floatval($s['critical_threshold']) : null,
			];
		}
		return $out;
	}

	public function SaveSettings(string $code, array $d): array
	{
		if (!isset($this->Definitions()[$code]))
		{
			throw new \Exception('KPI desconocido: ' . $code);
		}
		$num = fn($k) => isset($d[$k]) && $d[$k] !== '' && $d[$k] !== null ? floatval($d[$k]) : null;
		$this->Db()->prepare('INSERT INTO daily_kpi_settings (code, owner_user_id, target, warn_threshold, critical_threshold, updated_by_user_id, updated_at) VALUES (:c, :o, :t, :w, :x, :u, :n)
			ON CONFLICT(code) DO UPDATE SET owner_user_id = excluded.owner_user_id, target = excluded.target, warn_threshold = excluded.warn_threshold,
				critical_threshold = excluded.critical_threshold, updated_by_user_id = excluded.updated_by_user_id, updated_at = excluded.updated_at')->execute([
			':c' => $code, ':o' => isset($d['owner_user_id']) && $d['owner_user_id'] !== '' ? intval($d['owner_user_id']) : null,
			':t' => $num('target'), ':w' => $num('warn_threshold'), ':x' => $num('critical_threshold'),
			':u' => defined('GROCY_USER_ID') ? GROCY_USER_ID : null, ':n' => date('Y-m-d H:i:s'),
		]);
		return array_values(array_filter($this->Catalog(), fn($k) => $k['code'] === $code))[0];
	}

	/**
	 * Calcula los KPI pedidos ($codes vacío = todos) para la sucursal (null = red visible) y el rango [start, end].
	 * Cada resultado trae valor, estado frente al umbral y la validación (por qué es null, si lo es).
	 */
	public function Compute(array $codes, ?int $branchId, string $start, string $end): array
	{
		if ($branchId !== null && !DairyAccess::CanSeeBranch($branchId))
		{
			throw new \Exception('No tiene acceso a esa sucursal');
		}
		if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end) || $start > $end)
		{
			throw new \Exception('Rango de fechas inválido (AAAA-MM-DD, inicio ≤ fin)');
		}
		$catalog = [];
		foreach ($this->Catalog() as $k)
		{
			$catalog[$k['code']] = $k;
		}
		$codes = $codes ?: array_keys($catalog);
		$out = [];
		foreach ($codes as $code)
		{
			if (!isset($catalog[$code]))
			{
				throw new \Exception('KPI desconocido: ' . $code);
			}
			[$value, $note] = $this->Value($code, $branchId, $start, $end);
			$k = $catalog[$code];
			$out[$code] = [
				'code' => $code, 'name' => $k['name'], 'unit' => $k['unit'], 'formula' => $k['formula'], 'source' => $k['source'],
				'value' => $value, 'validation' => $note, 'status' => $this->Status($k, $value), 'target' => $k['target'],
				'branch_id' => $branchId, 'start' => $start, 'end' => $end,
			];
		}
		$this->Memo = [];
		return $out;
	}

	private function Db(): \PDO
	{
		return DatabaseService::GetInstance()->GetDbConnectionRaw();
	}

	private function Status(array $k, ?float $v): string
	{
		if ($v === null)
		{
			return 'no_data';
		}
		$warn = $k['warn_threshold'];
		$crit = $k['critical_threshold'];
		if ($warn === null && $crit === null)
		{
			return 'ok';
		}
		$bad = fn($t) => $t !== null && ($k['direction'] === 'down' ? $v >= $t : $v <= $t);
		return $bad($crit) ? 'critical' : ($bad($warn) ? 'warning' : 'ok');
	}

	/** Allowed branches: sin sucursal y usuario restringido → suma solo sus sucursales */
	private function BranchFilter(?int $branchId, string $col, array &$p): string
	{
		if ($branchId !== null && $branchId > 0)
		{
			$p[':b'] = $branchId;
			return " AND $col = :b";
		}
		$allowed = DairyAccess::AllowedBranches();
		return $allowed === null ? '' : " AND $col IN (" . implode(',', array_map('intval', $allowed ?: [0])) . ')';
	}

	private function Sales(?int $b, string $s, string $e): array
	{
		return $this->Memo["sales|$b|$s|$e"] ??= (function () use ($b, $s, $e) {
			$p = [':s' => $s, ':e' => $e];
			$bf = $this->BranchFilter($b, 'branch_id', $p);
			$st = $this->Db()->prepare("SELECT COUNT(*) n, IFNULL(SUM(total_amount), 0) gross, IFNULL(SUM(tax_amount), 0) tax, IFNULL(SUM(total_cogs), 0) cogs, IFNULL(SUM(gross_margin), 0) margin
				FROM sales WHERE status = 'completed' AND date(created_timestamp) >= :s AND date(created_timestamp) <= :e $bf");
			$st->execute($p);
			$r = $st->fetch(\PDO::FETCH_ASSOC);
			$p2 = [':s' => $s, ':e' => $e];
			$bf2 = $this->BranchFilter($b, 's.branch_id', $p2);
			$st = $this->Db()->prepare("SELECT IFNULL(SUM(si.amount), 0) FROM sale_items si JOIN sales s ON s.id = si.sale_id WHERE s.status = 'completed' AND date(s.created_timestamp) >= :s AND date(s.created_timestamp) <= :e $bf2");
			$st->execute($p2);
			$r['units'] = floatval($st->fetchColumn());
			return array_map('floatval', $r);
		})();
	}

	private function Waste(?int $b, string $s, string $e): float
	{
		return $this->Memo["waste|$b|$s|$e"] ??= (function () use ($b, $s, $e) {
			$p = [':s' => $s, ':e' => $e];
			$bf = $this->BranchFilter($b, 'branch_id', $p);
			$st = $this->Db()->prepare("SELECT IFNULL(SUM(total_cost), 0) FROM waste_records WHERE status = 'approved' AND date(created_timestamp) >= :s AND date(created_timestamp) <= :e $bf");
			$st->execute($p);
			return floatval($st->fetchColumn());
		})();
	}

	private function Recovered(?int $b, string $s, string $e): float
	{
		if ($b === null && DairyAccess::AllowedBranches() !== null)
		{
			return array_sum(array_map(fn($x) => KpiService::GetInstance()->SumRecovered($s, $e, intval($x)), DairyAccess::AllowedBranches()));
		}
		return $this->Memo["rec|$b|$s|$e"] ??= KpiService::GetInstance()->SumRecovered($s, $e, $b);
	}

	private function Inventory(?int $b): float
	{
		return $this->Memo["inv|$b"] ??= (function () use ($b) {
			$p = [];
			$bf = $this->BranchFilter($b, 'l.branch_id', $p);
			$st = $this->Db()->prepare("SELECT IFNULL(SUM(s.amount * IFNULL(s.price, IFNULL(plp.price, 0))), 0) FROM stock s JOIN products p ON s.product_id = p.id
				LEFT JOIN cache__products_last_purchased plp ON p.id = plp.product_id JOIN locations l ON l.id = s.location_id WHERE IFNULL(l.is_transit, 0) = 0 $bf");
			$st->execute($p);
			return floatval($st->fetchColumn());
		})();
	}

	/** @return array{0: ?float, 1: ?string} valor y, si es null, el motivo */
	private function Value(string $code, ?int $b, string $s, string $e): array
	{
		$sales = fn() => $this->Sales($b, $s, $e);
		switch ($code)
		{
			case 'gross_sales':
				return [round($sales()['gross'], 2), null];
			case 'net_sales':
				return [round($sales()['gross'] - $sales()['tax'], 2), null];
			case 'tickets':
				return [$sales()['n'], null];
			case 'avg_ticket':
				return $sales()['n'] > 0 ? [round(($sales()['gross'] - $sales()['tax']) / $sales()['n'], 2), null] : [null, 'Sin transacciones en el rango'];
			case 'units_per_transaction':
				return $sales()['n'] > 0 ? [round($sales()['units'] / $sales()['n'], 2), null] : [null, 'Sin transacciones en el rango'];
			case 'cogs':
				return [round($sales()['cogs'], 2), null];
			case 'gross_margin':
				return [round($sales()['margin'], 2), null];
			case 'margin_pct':
				$v = SalesService::MarginPct($sales()['margin'], $sales()['gross'] - $sales()['tax'], 2);
				return [$v, $v === null ? 'Sin ventas netas en el rango' : null];
			case 'sales_goal_attainment':
				if (substr($s, 0, 7) !== substr($e, 0, 7))
				{
					return [null, 'La meta es mensual: el rango debe estar dentro de un mes'];
				}
				$t = BudgetService::GetInstance()->GetTargets($b, substr($s, 0, 7));
				if ($t['sales_target'] === null || $t['sales_target'] <= 0)
				{
					return [null, 'Sin meta de ventas presupuestada para ' . substr($s, 0, 7)];
				}
				$days = (int) round((strtotime($e) - strtotime($s)) / 86400) + 1;
				$target = $t['sales_target'] * $days / intval(date('t', strtotime($s)));
				return [round($sales()['gross'] / $target * 100, 1), null];
			case 'waste_cost':
				return [round($this->Waste($b, $s, $e), 2), null];
			case 'waste_pct':
				$base = BudgetService::WasteBase($sales()['gross'], $sales()['tax'], $sales()['cogs']);
				$v = BudgetService::Pct($this->Waste($b, $s, $e), $base['amount']);
				return [$v, $v === null ? 'Base de cálculo (' . $base['label'] . ') en cero' : null];
			case 'waste_recovered':
				return [round($this->Recovered($b, $s, $e), 2), null];
			case 'waste_net_loss':
				return [round($this->Waste($b, $s, $e) - $this->Recovered($b, $s, $e), 2), null];
			case 'inventory_value':
				return [round($this->Inventory($b), 2), null];
			case 'inventory_turnover':
				return $this->Inventory($b) > 0 ? [round($sales()['cogs'] / $this->Inventory($b), 2), null] : [null, 'Inventario valorizado en cero'];
			case 'days_of_inventory':
				$elapsed = max(1, intval((min(strtotime(date('Y-m-d')), strtotime($e)) - strtotime($s)) / 86400) + 1);
				return $sales()['cogs'] > 0 ? [round($this->Inventory($b) / ($sales()['cogs'] / $elapsed), 1), null] : [null, 'Sin costo de ventas en el rango'];
			case 'open_alerts':
			case 'overdue_alerts':
				$p = [];
				$bf = $this->BranchFilter($b, 'branch_id', $p);
				$sql = "SELECT COUNT(*) FROM dairy_alerts WHERE status IN ('" . implode("', '", AlertCenterService::ACTIVE) . "') $bf";
				if ($code === 'overdue_alerts')
				{
					$sql .= ' AND due_at < :now';
					$p[':now'] = date('Y-m-d H:i:s');
				}
				$st = $this->Db()->prepare($sql);
				$st->execute($p);
				return [floatval($st->fetchColumn()), null];
			case 'overdue_workflows':
				$p = [':now' => date('Y-m-d H:i:s')];
				$bf = $this->BranchFilter($b, 'branch_id', $p);
				$st = $this->Db()->prepare("SELECT COUNT(*) FROM daily_workflow_instances WHERE status IN ('" . implode("', '", WorkflowService::ACTIVE) . "') AND due_at < :now $bf");
				$st->execute($p);
				return [floatval($st->fetchColumn()), null];
		}
		throw new \Exception('KPI sin cálculo: ' . $code);
	}
}
