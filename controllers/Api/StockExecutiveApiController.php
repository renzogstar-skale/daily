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

