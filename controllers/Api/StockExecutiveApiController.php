<?php

namespace Grocy\Controllers\Api;

use Grocy\Controllers\Users\User;
use Grocy\Helpers\DairyAccess;
use Grocy\Services\StockExecutiveOverviewService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class StockExecutiveApiController extends BaseApiController
{
	public function GetOverview(Request $request, Response $response, array $args)
	{
		if (!DairyAccess::CanOpen('/stockoverview'))
		{
			throw new \Grocy\Controllers\Users\PermissionMissingException($request, User::PERMISSION_STOCK);
		}
		try
		{
			$params = $request->getQueryParams();
			$data = StockExecutiveOverviewService::GetInstance()->GetExecutiveData($params);
			return $this->ApiResponse($response, $data);
		}
		catch (\Exception $ex)
		{
			return $this->GenericErrorResponse($response, $ex->getMessage());
		}
	}

	public function GetHeatmap(Request $request, Response $response, array $args)
	{
		return $this->Widget($request, $response, 'GetHeatmapWidget');
	}

	public function GetTopProducts(Request $request, Response $response, array $args)
	{
		return $this->Widget($request, $response, 'GetTopProductsWidget');
	}

	public function GetExpiring(Request $request, Response $response, array $args)
	{
		return $this->Widget($request, $response, 'GetExpiringWidget');
	}

	private function Widget(Request $request, Response $response, string $method)
	{
		if (!DairyAccess::CanOpen('/stockoverview'))
		{
			throw new \Grocy\Controllers\Users\PermissionMissingException($request, User::PERMISSION_STOCK);
		}
		try
		{
			return $this->ApiResponse($response, StockExecutiveOverviewService::GetInstance()->$method($request->getQueryParams()));
		}
		catch (\Exception $ex)
		{
			return $this->GenericErrorResponse($response, $ex->getMessage());
		}
	}

	public function GetProduct360(Request $request, Response $response, array $args)
	{
		if (!DairyAccess::CanOpen('/stockoverview'))
		{
			throw new \Grocy\Controllers\Users\PermissionMissingException($request, User::PERMISSION_STOCK);
		}
		try
		{
			$productId = intval($args['productId'] ?? 1);
			$data = StockExecutiveOverviewService::GetInstance()->GetProduct360Sheet($productId);
			return $this->ApiResponse($response, $data);
		}
		catch (\Exception $ex)
		{
			return $this->GenericErrorResponse($response, $ex->getMessage());
		}
	}

	public function ExecuteFefoAction(Request $request, Response $response, array $args)
	{
		if (!DairyAccess::CanOpen('/stockoverview'))
		{
			throw new \Grocy\Controllers\Users\PermissionMissingException($request, User::PERMISSION_STOCK);
		}
		try
		{
			$body = $request->getParsedBody() ?? [];
			// Cada acción exige el permiso del módulo que la ejecuta
			$actionPage = ['inspect' => '/tasks', 'rotate_task' => '/tasks', 'alert_task' => '/tasks', 'transfer' => '/transfers', 'waste' => '/waste/report'][strval($body['action'] ?? '')] ?? null;
			if ($actionPage !== null && !DairyAccess::CanOpen($actionPage))
			{
				return $this->GenericErrorResponse($response, 'Sin permiso para esta acción', 403);
			}
			$res = StockExecutiveOverviewService::GetInstance()->ExecuteFefoAction($body, defined('GROCY_USER_ID') ? GROCY_USER_ID : 1);
			return $this->ApiResponse($response, $res);
		}
		catch (\Exception $ex)
		{
			return $this->GenericErrorResponse($response, $ex->getMessage());
		}
	}

	public function ExecuteWorkflowStep(Request $request, Response $response, array $args)
	{
		if (!DairyAccess::CanOpen('/stockoverview'))
		{
			throw new \Grocy\Controllers\Users\PermissionMissingException($request, User::PERMISSION_STOCK);
		}
		try
		{
			$body = $request->getParsedBody() ?? [];
			$res = StockExecutiveOverviewService::GetInstance()->ExecuteIntegratedWorkflowStep($body, defined('GROCY_USER_ID') ? GROCY_USER_ID : 1);
			return $this->ApiResponse($response, $res);
		}
		catch (\Exception $ex)
		{
			return $this->GenericErrorResponse($response, $ex->getMessage());
		}
	}

	public function ResetDemo(Request $request, Response $response, array $args)
	{
		if (!DairyAccess::CanOpen('/stockoverview'))
		{
			throw new \Grocy\Controllers\Users\PermissionMissingException($request, User::PERMISSION_STOCK);
		}
		try
		{
			$res = StockExecutiveOverviewService::GetInstance()->ResetLiveMovements();
			return $this->ApiResponse($response, $res);
		}
		catch (\Exception $ex)
		{
			return $this->GenericErrorResponse($response, $ex->getMessage());
		}
	}
}

