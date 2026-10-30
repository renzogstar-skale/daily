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

		return $this->RenderPage($response, 'executive_dashboard', [
			'kpiData' => $kpiData,
			'akcalData' => $akcalData,
			'branches' => $branches,
			'selectedBranchId' => $branchId,
			'selectedPeriod' => $period,
			'selectedDate' => $anchorDate,
			'customStart' => $customStart,
			'customEnd' => $customEnd,
			'alertCount' => $alertCount,
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
