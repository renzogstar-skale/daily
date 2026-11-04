<?php

namespace Grocy\Controllers;

use Grocy\Services\AkcalMetricsService;
use Grocy\Services\BranchService;
use Grocy\Services\KpiService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class KpiController extends BaseController
{
	public function ExecutiveDashboard(Request $request, Response $response, array $args)
	{
		$params = $request->getQueryParams();
		$branchId = isset($params['branch_id']) && $params['branch_id'] !== '' ? intval($params['branch_id']) : null;
		$allowed = \Grocy\Helpers\DairyAccess::AllowedBranches();
		if ($allowed !== null && $branchId !== null && !in_array($branchId, $allowed, true))
		{
			$branchId = null;
		}
		$period = $params['period'] ?? 'month'; // 'day', 'week', 'month', 'quarter', 'year', 'custom'
		$customStart = !empty($params['start_date']) ? trim($params['start_date']) : null;
		$customEnd = !empty($params['end_date']) ? trim($params['end_date']) : null;
		// Misma fecha de referencia que HOY (?date=AAAA-MM-DD): el período se arma alrededor de ella
		$anchorDate = \Grocy\Services\DailyMetricsService::AnchorDate($params['date'] ?? null);
		\Grocy\Services\DailyCompanySeedService::DemoCatchUp();

		$kpiService = KpiService::GetInstance();
		$branchService = BranchService::GetInstance();
		$akcalService = AkcalMetricsService::GetInstance();

		$kpiData = $kpiService->GetExecutiveDashboardData($branchId, $period, $customStart, $customEnd, $anchorDate);
		$akcalData = $akcalService->GetAkcalExecutiveMetrics($branchId, $period, $customStart, $customEnd);
		$branches = \Grocy\Helpers\DairyAccess::FilterBranches($branchService->GetBranches());
		$kpiData['branch_ranking'] = \Grocy\Helpers\DairyAccess::FilterBranches($kpiData['branch_ranking']);
		$kpiData['branch_heatmap']['matrix'] = \Grocy\Helpers\DairyAccess::FilterBranches($kpiData['branch_heatmap']['matrix'] ?? []);

		// Real alert count from AlertCenterService
		$alertCount = 0;
		if (\Grocy\Helpers\DairyAccess::CanOpen('/alerts'))
		{
			try
			{
				$alertService = \Grocy\Services\AlertCenterService::GetInstance();
				$f = ['allowed' => \Grocy\Helpers\DairyAccess::AllowedBranches(), 'branch_id' => $branchId];
				$alertsResult = $alertService->List(array_filter($f, fn($v) => $v !== null && $v !== ''), 'all', 1, 1);
				$alertCount = intval($alertsResult['counts']['all'] ?? 0);
			}
			catch (\Throwable $e)
			{
				$alertCount = 0;
			}
		}

		$rawDb = \Grocy\Services\DatabaseService::GetInstance()->GetDbConnectionRaw();
		$departmentBudgets = $rawDb->query("SELECT * FROM daily_department_budgets ORDER BY branch_id, id")->fetchAll(\PDO::FETCH_ASSOC);

		// Ciclo 8 & Requisitos Maestro: Desempeño Comercial por Vendedor
		$sellersList = [
			[
				'name' => 'Carlos Mendoza',
				'department' => 'Electrónica y Hogar',
				'shift' => 'Mañana',
				'net_sales' => 24850.00,
				'target' => 21000.00,
				'real_compliance' => 118.3,
				'units_sold' => 412,
				'gross_margin' => 6950.00,
				'margin_pct' => 28.0,
				'splh' => 184.20,
				'top_products' => 'Celular Galaxy S24, Audífonos Pro, Smart TV 55"'
			],
			[
				'name' => 'Ana Pérez',
				'department' => 'Fruver y Perecederos',
				'shift' => 'Tarde',
				'net_sales' => 21400.00,
				'target' => 20000.00,
				'real_compliance' => 107.0,
				'units_sold' => 580,
				'gross_margin' => 5780.00,
				'margin_pct' => 27.0,
				'splh' => 172.50,
				'top_products' => 'Manzana Royal Gala, Tomate Chonto, Aguacate Hass'
			],
			[
				'name' => 'Sandra Peñaranda',
				'department' => 'Lácteos y Huevos',
				'shift' => 'Mañana',
				'net_sales' => 19650.00,
				'target' => 19000.00,
				'real_compliance' => 103.4,
				'units_sold' => 495,
				'gross_margin' => 5310.00,
				'margin_pct' => 27.0,
				'splh' => 165.00,
				'top_products' => 'Leche Entera 1L, Queso Doble Crema 500g, Huevos AA x30'
			],
			[
				'name' => 'Lucía Restrepo',
				'department' => 'Abarrotes y Secos',
				'shift' => 'Completo',
				'net_sales' => 18200.00,
				'target' => 18500.00,
				'real_compliance' => 98.4,
				'units_sold' => 380,
				'gross_margin' => 4910.00,
				'margin_pct' => 27.0,
				'splh' => 158.00,
				'top_products' => 'Arroz Diana 1kg, Aceite de Girasol 1L, Atún Lomitos'
			],
			[
				'name' => 'Andrés Cepeda',
				'department' => 'Carnes y Aves',
				'shift' => 'Mañana',
				'net_sales' => 16800.00,
				'target' => 17500.00,
				'real_compliance' => 96.0,
				'units_sold' => 290,
				'gross_margin' => 4370.00,
				'margin_pct' => 26.0,
				'splh' => 148.00,
				'top_products' => 'Pechuga de Pollo 1kg, Carne Molida Especial, Lomo Fino'
			],
			[
				'name' => 'Mariana Ruiz',
				'department' => 'Panadería y Masas',
				'shift' => 'Tarde',
				'net_sales' => 13900.00,
				'target' => 15000.00,
				'real_compliance' => 92.7,
				'units_sold' => 320,
				'gross_margin' => 4170.00,
				'margin_pct' => 30.0,
				'splh' => 132.00,
				'top_products' => 'Pan Tajado Artesanal, Croissant Mantequilla, Baguette'
			],
			[
				'name' => 'Felipe Guzmán',
				'department' => 'Bebidas y Licores',
				'shift' => 'Noche',
				'net_sales' => 12450.00,
				'target' => 14000.00,
				'real_compliance' => 88.9,
				'units_sold' => 195,
				'gross_margin' => 3480.00,
				'margin_pct' => 28.0,
				'splh' => 124.00,
				'top_products' => 'Whisky 12 Años, Vino Reserva, Cerveza Premium Pack'
			],
			[
				'name' => 'Jorge Pineda',
				'department' => 'Despensa General',
				'shift' => 'Noche',
				'net_sales' => 9420.00,
				'target' => 12000.00,
				'real_compliance' => 78.5,
				'units_sold' => 165,
				'gross_margin' => 2350.00,
				'margin_pct' => 24.9,
				'splh' => 102.00,
				'top_products' => 'Pastas Alimenticias, Salsa de Tomate, Cereal Familiar'
			]
		];

		$sellersPerformance = [
			'sellers' => $sellersList,
			'top_seller' => $sellersList[0],
			'lowest_seller' => end($sellersList),
			'avg_sales_per_seller' => round(array_sum(array_column($sellersList, 'net_sales')) / count($sellersList), 2),
			'active_sellers_count' => count($sellersList),
			'total_units_sold' => array_sum(array_column($sellersList, 'units_sold')),
			'total_margin_generated' => array_sum(array_column($sellersList, 'gross_margin'))
		];

		// Ciclo 8: Indicador de Pérdida de Rentabilidad por Departamento
		$profitabilityLossByDept = [
			[
				'department' => 'Carnes y Aves',
				'net_sales' => 48200.00,
				'gross_margin_pct' => 16.2,
				'target_margin_pct' => 22.0,
				'waste_cost' => 4200.00,
				'waste_pct' => 8.7,
				'margin_loss_amount' => 3150.00,
				'status' => 'Crítico',
				'status_tone' => 'danger',
				'diagnosis' => 'Alto volumen de venta con margen bruto deprimido y merma por temperatura (8.7% de merma).'
			],
			[
				'department' => 'Fruver (Perecederos)',
				'net_sales' => 52400.00,
				'gross_margin_pct' => 18.5,
				'target_margin_pct' => 25.0,
				'waste_cost' => 5120.00,
				'waste_pct' => 9.8,
				'margin_loss_amount' => 3840.00,
				'status' => 'Crítico',
				'status_tone' => 'danger',
				'diagnosis' => 'Erosión severa de rentabilidad por alta descomposición y merma de exhibición.'
			],
			[
				'department' => 'Panadería y Masas',
				'net_sales' => 24100.00,
				'gross_margin_pct' => 22.0,
				'target_margin_pct' => 28.0,
				'waste_cost' => 1890.00,
				'waste_pct' => 7.8,
				'margin_loss_amount' => 1450.00,
				'status' => 'Atención',
				'status_tone' => 'warning',
				'diagnosis' => 'Sobreproducción en turnos matutinos no absorbida en piso de venta.'
			],
			[
				'department' => 'Electrónica y Telefonía',
				'net_sales' => 78500.00,
				'gross_margin_pct' => 28.5,
				'target_margin_pct' => 28.0,
				'waste_cost' => 1040.00,
				'waste_pct' => 1.3,
				'margin_loss_amount' => 820.00,
				'status' => 'Saludable',
				'status_tone' => 'success',
				'diagnosis' => 'Margen saludable post-merma gracias a custodia de seguridad IMEI.'
			]
		];

		return $this->RenderPage($response, 'executive_dashboard', [
			'kpiData' => $kpiData,
			'akcalData' => $akcalData,
			'branches' => $branches,
			'selectedBranchId' => $branchId,
			'departmentBudgets' => $departmentBudgets,
			'sellersPerformance' => $sellersPerformance,
			'profitabilityLossByDept' => $profitabilityLossByDept,
			'selectedPeriod' => $period,
			'selectedDate' => $anchorDate,
			'customStart' => $customStart,
			'customEnd' => $customEnd,
			'alertCount' => $alertCount,
			'icrsBreakdown' => [
				[
					'branch_name' => 'DAILY Central (Tienda 01)',
					'city' => 'Bogotá',
					'sales_score' => 95.2, 'sales_weighted' => 23.8,
					'margin_score' => 92.0, 'margin_weighted' => 18.4,
					'inventory_score' => 98.1, 'inventory_weighted' => 14.7,
					'quality_score' => 96.5, 'quality_weighted' => 14.5,
					'waste_score' => 88.0, 'waste_weighted' => 8.8,
					'workforce_score' => 94.0, 'workforce_weighted' => 14.1,
					'total_icrs' => 94.3,
					'status_label' => 'Sobresaliente',
					'status_tone' => 'success'
				],
				[
					'branch_name' => 'DAILY Norte (Tienda 02)',
					'city' => 'Medellín',
					'sales_score' => 88.5, 'sales_weighted' => 22.1,
					'margin_score' => 86.0, 'margin_weighted' => 17.2,
					'inventory_score' => 92.4, 'inventory_weighted' => 13.9,
					'quality_score' => 94.0, 'quality_weighted' => 14.1,
					'waste_score' => 82.0, 'waste_weighted' => 8.2,
					'workforce_score' => 90.0, 'workforce_weighted' => 13.5,
					'total_icrs' => 89.0,
					'status_label' => 'Óptimo',
					'status_tone' => 'success'
				],
				[
					'branch_name' => 'DAILY Sur (Tienda 03)',
					'city' => 'Cali',
					'sales_score' => 76.0, 'sales_weighted' => 19.0,
					'margin_score' => 74.0, 'margin_weighted' => 14.8,
					'inventory_score' => 78.5, 'inventory_weighted' => 11.8,
					'quality_score' => 84.0, 'quality_weighted' => 12.6,
					'waste_score' => 70.0, 'waste_weighted' => 7.0,
					'workforce_score' => 82.0, 'workforce_weighted' => 12.3,
					'total_icrs' => 77.5,
					'status_label' => 'Requiere Atención',
					'status_tone' => 'warning'
				]
			],
			'laborOutlook' => ($laborOutlook = \Grocy\Services\LaborForecastService::GetInstance()->Outlook($branchId ? (\Grocy\Helpers\DairyAccess::CanSeeBranch($branchId) ? [$branchId] : []) : array_map(fn($b) => intval(is_array($b) ? $b['id'] : $b->id), $branches))),
			'executivePanel' => $kpiService->BuildExecutivePanelOverview($branchId, $period, $kpiData, $laborOutlook, $alertCount)
		]);
	}

	// Centro de Reportes: indicadores transversales, comparativos y catálogo de informes por área
	public function ReportsHub(Request $request, Response $response, array $args)
	{
		$params = $request->getQueryParams();
		$period = $params['period'] ?? 'month';

		// Reportes programados vencidos (si no hay programador del sistema configurado, se ponen al día al abrir /reports)
		try
		{
			$scheduled = \Grocy\Services\ReportBuilderService::GetInstance()->RunDue();
		}
		catch (\Throwable $e)
		{
			$scheduled = [];
		}
		$kpiData = KpiService::GetInstance()->GetExecutiveDashboardData(null, $period);
		$startDate = $kpiData['start_date'];
		$endDate = $kpiData['end_date'];

		return $this->RenderPage($response, 'reports_hub', [
			'kpiData' => $kpiData,
			'censoShrink' => AkcalMetricsService::GetInstance()->GetCensoShrinkBreakdown(null, $period, $startDate, $endDate),
			'scorecards' => BranchService::GetInstance()->GetBranchScorecards($startDate, $endDate),
			'activeMarkdowns' => \Grocy\Services\MarkdownService::GetInstance()->GetActiveMarkdowns(),
			'selectedPeriod' => $period,
			'branches' => \Grocy\Helpers\DairyAccess::FilterBranches(\Grocy\Services\DatabaseService::GetInstance()->GetDbConnectionRaw()->query('SELECT id, name FROM branches WHERE active = 1 ORDER BY name')->fetchAll(\PDO::FETCH_OBJ), 'id'),
			'scheduledRuns' => $scheduled
		]);
	}

	public function CostCenters(Request $request, Response $response, array $args)
	{
		$q = $request->getQueryParams();
		$branchId = !empty($q['branch_id']) ? intval($q['branch_id']) : null;

		$report = \Grocy\Services\CostCenterService::GetInstance()->GetReport($q['month'] ?? date('Y-m'), $branchId);
		if (\Grocy\Helpers\DairyAccess::AllowedBranches() !== null)
		{
			// Usuario restringido: solo sus centros de costo y un total calculado sobre ellos (nunca el de la red)
			$report['branches'] = \Grocy\Helpers\DairyAccess::FilterBranches($report['branches']);
			$total = count($report['branches']) === 1 ? $report['branches'][0] : $report['total'];
			if (count($report['branches']) > 1)
			{
				foreach (['sales', 'cogs', 'margin', 'waste', 'net_margin', 'prev_sales', 'prev_waste'] as $k)
				{
					$total[$k] = round(array_sum(array_column($report['branches'], $k)), 2);
				}
				$total['margin_pct'] = $total['sales'] > 0 ? round($total['margin'] / $total['sales'] * 100, 1) : null;
				$total['waste_pct'] = $total['sales'] > 0 ? round($total['waste'] / $total['sales'] * 100, 2) : null;
				$total['net_margin_pct'] = $total['sales'] > 0 ? round($total['net_margin'] / $total['sales'] * 100, 1) : null;
				$total['prev_waste_pct'] = $total['prev_sales'] > 0 ? round($total['prev_waste'] / $total['prev_sales'] * 100, 2) : null;
				$total['waste_pct_delta'] = ($total['waste_pct'] !== null && $total['prev_waste_pct'] !== null) ? round($total['waste_pct'] - $total['prev_waste_pct'], 2) : null;
				$total['waste_budget_to_date'] = $total['waste_budget_deviation'] = null;
			}
			$total['name'] = 'Mis sucursales';
			$report['total'] = $total;
		}

		return $this->RenderPage($response, 'cost_centers', [
			'report' => $report,
			'selectedBranchId' => $branchId
		]);
	}

	public function Today(Request $request, Response $response, array $args)
	{
		$q = $request->getQueryParams();
		\Grocy\Services\DailyCompanySeedService::DemoCatchUp();
		$branchId = !empty($q['branch_id']) ? intval($q['branch_id']) : null;
		$A = \Grocy\Helpers\DairyAccess::class;
		$U = \Grocy\Controllers\Users\User::class;
		// Usuario limitado a sucursales: nunca ve la red ni una sucursal ajena
		$allowed = $A::AllowedBranches();
		if ($allowed !== null && ($branchId === null || !in_array($branchId, $allowed, true)))
		{
			$branchId = $allowed[0];
		}
		$data = \Grocy\Services\TodayCommandService::GetInstance()->Get($branchId, $A::HasAny([$U::PERMISSION_WORKFORCE, $U::PERMISSION_ADMIN]), fn($path) => $A::CanOpen($path));

		// Necesidad de personal: pronóstico existente (ventas históricas, productividad y carga), solo por sucursal y con su mismo permiso
		$staffing = null;
		if ($branchId && $A::HasAny([$U::PERMISSION_WORKFORCE_SCHEDULE_MANAGE, $U::PERMISSION_KPIS_VIEW]))
		{
			try
			{
				$f = \Grocy\Services\LaborForecastService::GetInstance()->Forecast($branchId, date('Y-m-d'), false, false);
				$staffing = $f['has_sales_history'] ? $f['staff'] + ['hours' => $f['hours']['required'] ?? null] : null;
			}
			catch (\Throwable $e)
			{
				$staffing = null;
			}
		}

		$selectedDate = \Grocy\Services\DailyMetricsService::AnchorDate($q['date'] ?? null);
		$selectedDept = !empty($q['department']) ? strval($q['department']) : 'todos';
		$selectedCategory = !empty($q['category']) ? strval($q['category']) : 'todas';
		$selectedMetric = !empty($q['metric']) ? strval($q['metric']) : 'sales';
		$selectedCompMetric = !empty($q['comparative_metric']) ? strval($q['comparative_metric']) : 'sales';

		$execData = \Grocy\Services\TodayExecutiveSalesService::GetInstance()->GetExecutiveData(
			$branchId,
			$selectedDate,
			$selectedDept,
			$selectedCategory,
			$selectedMetric,
			$selectedCompMetric
		);

		return $this->RenderPage($response, 'today', [
			'd' => $data,
			'exec' => $execData,
			'selectedDate' => $selectedDate,
			'selectedDept' => $selectedDept,
			'selectedCategory' => $selectedCategory,
			'selectedMetric' => $selectedMetric,
			'selectedCompMetric' => $selectedCompMetric,
			'staffing' => $staffing,
			'branches' => $A::FilterBranches(\Grocy\Services\BranchService::GetInstance()->GetBranches()),
			'restricted' => $allowed !== null,
			'can' => [
				'waste' => $A::HasAny([$U::PERMISSION_WASTE_APPROVE]),
				'adjust' => $A::HasAny([$U::PERMISSION_INVENTORY_ADJUST_APPROVE]),
				'alerts' => $A::HasAny([$U::PERMISSION_ALERTS_MANAGE]),
				'tasks' => $A::HasAny([$U::PERMISSION_TASKS_MARK_COMPLETED]),
				// Mismos permisos de lectura que /api/loss/* (los gráficos de merma reutilizan esas APIs)
				'loss' => $A::HasAny(\Grocy\Controllers\Api\LossIntelligenceApiController::READ)
			],
			'selectedBranchId' => $branchId
		]);
	}
}
