<?php

namespace Grocy\Controllers\Api;

use Grocy\Services\BudgetService;
use Grocy\Services\KpiService;
use Grocy\Controllers\Users\User;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class KpiApiController extends BaseApiController
{
	public function GetExecutiveDashboard(Request $request, Response $response, array $args)
	{
		User::CheckPermission($request, User::PERMISSION_KPIS_VIEW);

		$params = $request->getQueryParams();
		$rawBranch = trim((string) ($params['branch_id'] ?? ''));
		$branchId = ($rawBranch !== '' && $rawBranch !== 'all' && intval($rawBranch) > 0) ? intval($rawBranch) : null;
		$allowed = \Grocy\Helpers\DairyAccess::AllowedBranches();
		if ($allowed !== null && ($branchId === null || !in_array($branchId, $allowed, true)))
		{
			$branchId = $allowed[0];
		}
		$period = $params['period'] ?? 'month'; // 'day', 'week', 'month', 'quarter', 'year', 'custom'
		$customStart = !empty($params['start_date']) ? trim($params['start_date']) : null;
		$customEnd = !empty($params['end_date']) ? trim($params['end_date']) : null;
		$anchorDate = \Grocy\Services\DailyMetricsService::AnchorDate($params['date'] ?? null);

		$kpiService = KpiService::GetInstance();
		$data = $kpiService->GetExecutiveDashboardData($branchId, $period, $customStart, $customEnd, $anchorDate);
		$data['branch_ranking'] = \Grocy\Helpers\DairyAccess::FilterBranches($data['branch_ranking'] ?? []);
		$data['branch_heatmap']['matrix'] = \Grocy\Helpers\DairyAccess::FilterBranches($data['branch_heatmap']['matrix'] ?? []);

		$alertCount = 0;
		if (\Grocy\Helpers\DairyAccess::CanOpen('/alerts'))
		{
			try
			{
				$f = ['allowed' => $allowed, 'branch_id' => $branchId];
				$alertsResult = \Grocy\Services\AlertCenterService::GetInstance()->List(array_filter($f, fn($v) => $v !== null && $v !== ''), 'all', 1, 1);
				$alertCount = intval($alertsResult['counts']['all'] ?? 0);
			}
			catch (\Throwable $e)
			{
				$alertCount = 0;
			}
		}

		$branches = \Grocy\Helpers\DairyAccess::FilterBranches(\Grocy\Services\BranchService::GetInstance()->GetBranches());
		$laborOutlook = \Grocy\Services\LaborForecastService::GetInstance()->Outlook(
			$branchId
				? (\Grocy\Helpers\DairyAccess::CanSeeBranch($branchId) ? [$branchId] : [])
				: array_map(fn($b) => intval(is_array($b) ? $b['id'] : $b->id), $branches)
		);
		$data['executive_panel'] = $kpiService->BuildExecutivePanelOverview($branchId, $period, $data, $laborOutlook, $alertCount);
		return $this->ApiResponse($response, $data);
	}

	public function GetBudgets(Request $request, Response $response, array $args)
	{
		User::CheckPermission($request, User::PERMISSION_KPIS_VIEW);
		$year = preg_match('/^\d{4}$/', $request->getQueryParams()['year'] ?? '') ? $request->getQueryParams()['year'] : date('Y');
		return $this->ApiResponse($response, BudgetService::GetInstance()->GetBudgets($year));
	}

	public function SaveBudget(Request $request, Response $response, array $args)
	{
		User::CheckPermission($request, User::PERMISSION_BUDGET_MANAGE);
		try
		{
			$id = BudgetService::GetInstance()->SaveBudget($request->getParsedBody() ?? [], GROCY_USER_ID);
			return $this->ApiResponse($response, ['id' => $id]);
		}
		catch (\Exception $ex)
		{
			return $this->GenericErrorResponse($response, $ex->getMessage());
		}
	}

	public function GetTodayExecutiveSales(Request $request, Response $response, array $args)
	{
		$params = $request->getQueryParams();
		$branchId = isset($params['branch_id']) && $params['branch_id'] !== '' && $params['branch_id'] !== 'all' ? intval($params['branch_id']) : null;
		// Sin entrada en DairyAccess::PAGES: exigir los mismos permisos que la página /today (el alcance por sucursal lo aplica el middleware)
		if (!\Grocy\Helpers\DairyAccess::CanOpen('/today'))
		{
			throw new \Grocy\Controllers\Users\PermissionMissingException($request, User::PERMISSION_KPIS_VIEW);
		}
		$allowed = \Grocy\Helpers\DairyAccess::AllowedBranches();
		if ($allowed !== null && ($branchId === null || !in_array($branchId, $allowed, true)))
		{
			$branchId = $allowed[0];
		}
		$date = !empty($params['date']) ? strval($params['date']) : null;
		$department = !empty($params['department']) ? strval($params['department']) : null;
		$category = !empty($params['category']) ? strval($params['category']) : null;
		$metric = !empty($params['metric']) ? strval($params['metric']) : 'sales';
		$comparativeMetric = !empty($params['comparative_metric']) ? strval($params['comparative_metric']) : null;

		$svc = \Grocy\Services\TodayExecutiveSalesService::GetInstance();
		$data = $svc->GetExecutiveData($branchId, $date, $department, $category, $metric, $comparativeMetric);
		return $this->ApiResponse($response, $data);
	}
}
