<?php

namespace Grocy\Controllers\Api;

use Grocy\Services\AIAdvisorService;
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
		$branchId = isset($params['branch_id']) ? intval($params['branch_id']) : null;
		$period = $params['period'] ?? 'month'; // 'day', 'week', 'month', 'year'

		$kpiService = KpiService::GetInstance();
		$data = $kpiService->GetExecutiveDashboardData($branchId, $period);
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

	// Acciones propuestas por Arturito: vista previa (sin escribir) y ejecución con confirmación; permiso propio de cada acción
	private function AiActionBranch(Request $request, array $body): ?int
	{
		$type = strval($body['type'] ?? '');
		return $this->DairyRead($request, [\Grocy\Services\AiActionService::Permission($type)], !empty($body['branch_id']) ? intval($body['branch_id']) : null);
	}

	public function AiActionPreview(Request $request, Response $response, array $args)
	{
		$body = $request->getParsedBody() ?? [];
		try
		{
			$branchId = $this->AiActionBranch($request, $body);
			return $this->ApiResponse($response, \Grocy\Services\AiActionService::GetInstance()->Preview(strval($body['type']), $branchId));
		}
		catch (\Grocy\Controllers\Users\PermissionMissingException $ex)
		{
			throw $ex;
		}
		catch (\Exception $ex)
		{
			return $this->GenericErrorResponse($response, $ex->getMessage());
		}
	}

	public function AiActionExecute(Request $request, Response $response, array $args)
	{
		$body = $request->getParsedBody() ?? [];
		try
		{
			$branchId = $this->AiActionBranch($request, $body);
			return $this->ApiResponse($response, \Grocy\Services\AiActionService::GetInstance()->Execute(strval($body['type']), $branchId, ($body['confirm'] ?? false) === true, GROCY_USER_ID, isset($body['question']) ? strval($body['question']) : null));
		}
		catch (\Grocy\Controllers\Users\PermissionMissingException $ex)
		{
			throw $ex;
		}
		catch (\Exception $ex)
		{
			return $this->GenericErrorResponse($response, $ex->getMessage());
		}
	}

	public function AiActionHistory(Request $request, Response $response, array $args)
	{
		User::CheckPermission($request, User::PERMISSION_KPIS_VIEW);
		return $this->ApiResponse($response, \Grocy\Services\AiActionService::GetInstance()->History());
	}

	public function Ask(Request $request, Response $response, array $args)
	{
		User::CheckPermission($request, User::PERMISSION_KPIS_VIEW);
		$body = $request->getParsedBody() ?? [];
		$branchId = !empty($body['branch_id']) ? intval($body['branch_id']) : null;
		try
		{
			return $this->ApiResponse($response, \Grocy\Services\AdvisorQaService::GetInstance()->Ask(mb_substr(strval($body['question'] ?? ''), 0, 500), $branchId));
		}
		catch (\Exception $ex)
		{
			return $this->GenericErrorResponse($response, $ex->getMessage());
		}
	}

	public function GetSmartAlerts(Request $request, Response $response, array $args)
	{
		$params = $request->getQueryParams();
		$branchId = $this->DairyRead($request, [User::PERMISSION_KPIS_VIEW, User::PERMISSION_LOSS_PREVENTION, User::PERMISSION_ALERTS_MANAGE], self::QueryBranch($request));

		$aiService = AIAdvisorService::GetInstance();
		$alerts = $aiService->GetSmartAlerts($branchId);
		return $this->ApiResponse($response, $alerts);
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
