<?php

namespace Grocy\Services;

class ProductEconomicsService extends BaseService
{
	private function Pdo(): \PDO
	{
		return DatabaseService::GetInstance()->GetDbConnectionRaw();
	}

	/** Resumen macro financiero: MRR, ARR, COGS de Infraestructura y Margen Bruto */
	public function GetFinancialSummary(?string $period = null): array
	{
		$period = $period ?: date('Y-m');
		$pdo = $this->Pdo();

		// 1. Ingresos Recurrentes (MRR y ARR)
		$contracts = $pdo->query("SELECT * FROM daily_saas_contracts WHERE status = 'active'")->fetchAll(\PDO::FETCH_ASSOC);
		$mrr = 0.0;
		$totalStores = 0;
		$totalUsers = 0;
		foreach ($contracts as $c)
		{
			$mrr += floatval($c['mrr_usd']);
			$totalStores += intval($c['stores_count']);
			$totalUsers += intval($c['users_count']);
		}
		$arr = $mrr * 12.0;

		// 2. Costos de Infraestructura (COGS)
		$stmtExp = $pdo->prepare("SELECT IFNULL(SUM(amount_usd), 0) FROM daily_saas_expenses WHERE billing_period = :p");
		$stmtExp->execute([':p' => $period]);
		$cogs = floatval($stmtExp->fetchColumn());

		// Si no hay gastos registrados en el periodo actual, estimar según la cantidad de tiendas activas ($5.50/tienda)
		$isCogsEstimated = false;
		if ($cogs <= 0 && $totalStores > 0)
		{
			$cogs = max(45.0, round($totalStores * 5.50 + 35.0, 2));
			$isCogsEstimated = true;
		}

		// 3. Métricas Unitarias y Margen Bruto
		$grossProfit = $mrr - $cogs;
		$grossMarginPct = $mrr > 0 ? round(($grossProfit / $mrr) * 100.0, 1) : 0.0;
		$arpuStore = $totalStores > 0 ? round($mrr / $totalStores, 2) : 0.0;
		$cogsPerStore = $totalStores > 0 ? round($cogs / $totalStores, 2) : 0.0;
		$costPerUser = $totalUsers > 0 ? round($cogs / $totalUsers, 2) : 0.0;

		// Costo de soporte operativo estimado (15% del MRR)
		$supportCost = round($mrr * 0.15, 2);
		$operatingProfit = $grossProfit - $supportCost;

		// Punto de equilibrio (Break-even en número de tiendas a tarifa promedio)
		$fixedBaseCost = 65.0; // VPS base + base de datos mínima
		$breakevenStores = $arpuStore > 0 ? ceil($fixedBaseCost / ($arpuStore - 2.50)) : 1;

		// CAC & Payback estimado
		$cacEst = 450.0; // Costo comercial estimado de adquisición por tienda
		$paybackMonths = $arpuStore > 0 ? round($cacEst / ($arpuStore * ($grossMarginPct / 100.0)), 1) : 0.0;

		return [
			'period' => $period,
			'mrr' => $mrr,
			'arr' => $arr,
			'cogs_monthly' => $cogs,
			'is_cogs_estimated' => $isCogsEstimated,
			'gross_profit' => $grossProfit,
			'gross_margin_pct' => $grossMarginPct,
			'margin_status' => $grossMarginPct >= 80.0 ? 'optimal' : ($grossMarginPct >= 65.0 ? 'warning' : 'critical'),
			'active_clients' => count($contracts),
			'active_stores' => $totalStores,
			'active_users' => $totalUsers,
			'arpu_store' => $arpuStore,
			'cogs_per_store' => $cogsPerStore,
			'cost_per_user' => $costPerUser,
			'support_cost_est' => $supportCost,
			'operating_profit_est' => $operatingProfit,
			'breakeven_stores' => $breakevenStores,
			'cac_est' => $cacEst,
			'payback_months' => $paybackMonths,
		];
	}

	/** Desglose de Gastos de Infraestructura por Categoría */
	public function GetInfrastructureExpenses(?string $period = null): array
	{
		$period = $period ?: date('Y-m');
		$pdo = $this->Pdo();

		$stmt = $pdo->prepare("SELECT * FROM daily_saas_expenses WHERE billing_period = :p ORDER BY amount_usd DESC");
		$stmt->execute([':p' => $period]);
		$rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

		// Totales por categoría
		$categories = [
			'compute' => ['name' => 'Cómputo Backend', 'icon' => 'fa-server', 'color' => '#10B981', 'total' => 0.0],
			'database' => ['name' => 'Base de Datos HA', 'icon' => 'fa-database', 'color' => '#06B6D4', 'total' => 0.0],
			'storage' => ['name' => 'Almacenamiento Evidencias (R2/S3)', 'icon' => 'fa-cloud-arrow-up', 'color' => '#8B5CF6', 'total' => 0.0],
			'network' => ['name' => 'Red, CDN & Seguridad DDoS', 'icon' => 'fa-network-wired', 'color' => '#F59E0B', 'total' => 0.0],
			'monitoring' => ['name' => 'Monitoreo, Uptime & Logs', 'icon' => 'fa-chart-line', 'color' => '#64748B', 'total' => 0.0],
		];

		foreach ($rows as $r)
		{
			$cat = $r['category'];
			if (isset($categories[$cat]))
			{
				$categories[$cat]['total'] += floatval($r['amount_usd']);
			}
		}

		return [
			'period' => $period,
			'items' => $rows,
			'categories' => $categories,
			'total' => array_sum(array_column($rows, 'amount_usd'))
		];
	}

	/** Rentabilidad por Cliente y Sucursal */
	public function GetClientsProfitability(): array
	{
		$pdo = $this->Pdo();
		$contracts = $pdo->query("SELECT * FROM daily_saas_contracts ORDER BY mrr_usd DESC")->fetchAll(\PDO::FETCH_ASSOC);

		$result = [];
		foreach ($contracts as $c)
		{
			$stores = max(1, intval($c['stores_count']));
			$mrr = floatval($c['mrr_usd']);
			$pricePerStore = round($mrr / $stores, 2);

			// Costo asignado de infraestructura por sucursal (~$4.80 a $7.00 según almacenamiento y plan)
			$allocatedCostPerStore = $c['plan_code'] === 'enterprise_360' ? 6.20 : 4.50;
			$totalAllocatedCost = round($stores * $allocatedCostPerStore + (floatval($c['storage_gb_used']) * 0.03), 2);

			$marginVal = $mrr - $totalAllocatedCost;
			$marginPct = $mrr > 0 ? round(($marginVal / $mrr) * 100.0, 1) : 0.0;

			$result[] = [
				'id' => $c['id'],
				'company_name' => $c['company_name'],
				'stores_count' => $stores,
				'users_count' => intval($c['users_count']),
				'plan_name' => $c['plan_name'],
				'plan_code' => $c['plan_code'],
				'support_tier' => $c['support_tier'],
				'mrr_usd' => $mrr,
				'price_per_store' => $pricePerStore,
				'allocated_cost_usd' => $totalAllocatedCost,
				'gross_margin_usd' => $marginVal,
				'gross_margin_pct' => $marginPct,
				'storage_gb_used' => floatval($c['storage_gb_used']),
				'status' => $c['status'],
				'renewal_date' => $c['renewal_date'],
			];
		}

		return $result;
	}

	/**
	 * Simulador de Precios y Cotizador Retail
	 * Entrada: tiendas, usuarios por tienda, plan, soporte, margen objetivo
	 * Salida: Costo estimado, precio sugerido por tienda, MRR, ARR y desglose comercial
	 */
	public function SimulatePricing(array $params): array
	{
		$stores = max(1, intval($params['stores_count'] ?? 1));
		$usersPerStore = max(2, intval($params['users_per_store'] ?? 5));
		$totalUsers = $stores * $usersPerStore;
		$plan = $params['plan'] ?? 'enterprise_360';
		$support = $params['support_tier'] ?? 'standard_8x5';
		$targetMarginPct = max(50.0, min(95.0, floatval($params['target_margin_pct'] ?? 85.0)));
		$devicesVolume = max(0, intval($params['serialized_devices_volume'] ?? 0));

		// 1. Costo Unitario de Infraestructura estimado por tienda según escala
		$baseInfraPerStore = 12.00;
		if ($stores >= 200)
		{
			$baseInfraPerStore = 2.90;
		}
		elseif ($stores >= 30)
		{
			$baseInfraPerStore = 4.83;
		}
		elseif ($stores >= 5)
		{
			$baseInfraPerStore = 7.00;
		}

		// Adicional por plan y dispositivos serializados
		$planAddon = $plan === 'enterprise_360' ? 1.50 : ($plan === 'full_retail' ? 0.80 : 0.0);
		$devicesAddon = ($devicesVolume > 0) ? round(($devicesVolume * 0.04) / $stores, 2) : 0.0;
		$supportAddon = $support === 'premium_24x7' ? 2.00 : 0.0;

		$totalInfraCostPerStore = round($baseInfraPerStore + $planAddon + $devicesAddon + $supportAddon, 2);
		$totalMonthlyCost = round($totalInfraCostPerStore * $stores, 2);

		// 2. Cálculo de Precio Sugerido para alcanzar el Margen Objetivo
		// Margen = (Precio - Costo) / Precio => Precio = Costo / (1 - Margen)
		$marginFactor = 1.0 - ($targetMarginPct / 100.0);
		$suggestedPricePerStore = round($totalInfraCostPerStore / max(0.05, $marginFactor), 2);

		// Redondear a números comerciales limpios ($79, $99, $120, etc.)
		$roundedPricePerStore = ceil($suggestedPricePerStore / 5.0) * 5.0 - 1.0;
		if ($roundedPricePerStore < 49.0)
		{
			$roundedPricePerStore = 49.0;
		}

		$suggestedMRR = round($roundedPricePerStore * $stores, 2);
		$suggestedARR = round($suggestedMRR * 12.0, 2);
		$projectedProfitMonthly = round($suggestedMRR - $totalMonthlyCost, 2);
		$actualMarginPct = round(($projectedProfitMonthly / $suggestedMRR) * 100.0, 1);

		// 3. Estimación de Retorno de Inversión (ROI) para el Cliente
		// Un supermercado promedio pierde ~1.8% de su facturación en merma/robo.
		// DAILY reduce la merma operativa y faltantes en al menos un 18%.
		$avgStoreMonthlySales = 85000.0; // $85,000 USD/mes por tienda
		$estimatedLossWithoutDaily = $avgStoreMonthlySales * 0.018; // $1,530 USD/mes
		$estimatedSavingsWithDaily = round($estimatedLossWithoutDaily * 0.18, 2); // $275.40 USD/mes por tienda
		$clientNetBenefitPerStore = round($estimatedSavingsWithDaily - $roundedPricePerStore, 2);
		$clientRoiMultiplier = round($estimatedSavingsWithDaily / $roundedPricePerStore, 1);

		return [
			'inputs' => [
				'stores_count' => $stores,
				'users_per_store' => $usersPerStore,
				'total_users' => $totalUsers,
				'plan' => $plan,
				'support_tier' => $support,
				'target_margin_pct' => $targetMarginPct,
				'serialized_devices_volume' => $devicesVolume,
			],
			'costs' => [
				'infra_per_store_monthly' => $totalInfraCostPerStore,
				'total_infra_cost_monthly' => $totalMonthlyCost,
				'total_infra_cost_annual' => round($totalMonthlyCost * 12.0, 2),
			],
			'pricing' => [
				'suggested_price_per_store' => $roundedPricePerStore,
				'suggested_mrr' => $suggestedMRR,
				'suggested_arr' => $suggestedARR,
				'projected_profit_monthly' => $projectedProfitMonthly,
				'projected_margin_pct' => $actualMarginPct,
			],
			'client_roi' => [
				'estimated_loss_prevented_store' => $estimatedSavingsWithDaily,
				'client_net_savings_monthly' => round($clientNetBenefitPerStore * $stores, 2),
				'roi_ratio' => $clientRoiMultiplier . 'x',
			]
		];
	}

	/** Alertas de Salud Financiera SaaS */
	public function GetFinancialAlerts(): array
	{
		$summary = $this->GetFinancialSummary();
		$contracts = $this->GetClientsProfitability();
		$alerts = [];

		// Alerta 1: Margen Bruto General
		if ($summary['gross_margin_pct'] < 80.0)
		{
			$alerts[] = [
				'level' => 'warning',
				'title' => 'Margen Bruto General Bajo Objetivo',
				'message' => 'El margen consolidado es de ' . $summary['gross_margin_pct'] . '% (objetivo: ≥85%). Revise la asignación de cómputo o tarifas de planes bajos.',
				'action_label' => 'Ajustar Precios',
				'href' => '#tab-simulator'
			];
		}

		// Alerta 2: Contratos con margen unitario subóptimo
		foreach ($contracts as $c)
		{
			if ($c['gross_margin_pct'] < 75.0)
			{
				$alerts[] = [
					'level' => 'danger',
					'title' => 'Cliente con Margen Subóptimo: ' . $c['company_name'],
					'message' => 'El margen bruto para este cliente es de ' . $c['gross_margin_pct'] . '% debido al alto consumo de almacenamiento o soporte contratado.',
					'action_label' => 'Revisar Contrato',
					'href' => '#tab-clients'
				];
			}
		}

		// Alerta 3: Estado de infraestructura
		if ($summary['is_cogs_estimated'])
		{
			$alerts[] = [
				'level' => 'info',
				'title' => 'Gastos del Periodo Proyectados',
				'message' => 'No se han registrado facturas reales de proveedores cloud para este mes. Las cifras mostradas corresponden al modelo estimado por tienda ($' . $summary['cogs_per_store'] . '/tienda).',
				'action_label' => 'Registrar Gasto Real',
				'href' => '#tab-expenses'
			];
		}

		return $alerts;
	}

	/** Registrar un gasto real de infraestructura */
	public function AddExpense(array $d, int $userId): array
	{
		$cat = trim($d['category'] ?? '');
		$provider = trim($d['provider'] ?? '');
		$amount = floatval($d['amount_usd'] ?? 0);
		$period = trim($d['billing_period'] ?? date('Y-m'));

		if (!$cat || !$provider || $amount <= 0)
		{
			throw new \Exception('Indique categoría, proveedor y un monto válido en USD');
		}

		$pdo = $this->Pdo();
		$stmt = $pdo->prepare("INSERT INTO daily_saas_expenses (category, provider, description, amount_usd, billing_period, is_estimate, invoice_ref, recorded_by_user_id, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
		$stmt->execute([
			$cat,
			$provider,
			trim($d['description'] ?? ''),
			$amount,
			$period,
			intval($d['is_estimate'] ?? 0),
			trim($d['invoice_ref'] ?? ''),
			$userId,
			date('Y-m-d H:i:s')
		]);

		return ['success' => true, 'id' => (int)$pdo->lastInsertId(), 'message' => 'Gasto de infraestructura registrado'];
	}
}
