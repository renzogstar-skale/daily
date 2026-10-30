<?php

namespace Grocy\Controllers\Api;

use Grocy\Services\WorkforcePeakEngineService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class WorkforcePeakApiController extends BaseApiController
{
	public function Dashboard(Request $request, Response $response, array $args): Response
	{
		$params = $request->getQueryParams();
		$branchId = isset($params['branch_id']) ? intval($params['branch_id']) : 1;
		$week = $params['week'] ?? null;

		$service = WorkforcePeakEngineService::GetInstance();
		$data = $service->GetPeakDashboardData($branchId, $week);

		return $this->ApiResponse($response, $data);
	}

	public function Simulate(Request $request, Response $response, array $args): Response
	{
		$body = $request->getParsedBody();
		$branchId = isset($body['branch_id']) ? intval($body['branch_id']) : 1;
		$sales = isset($body['daily_sales']) ? floatval($body['daily_sales']) : 40000.0;

		$service = WorkforcePeakEngineService::GetInstance();
		$result = $service->SimulateStaffing($branchId, $sales);

		return $this->ApiResponse($response, $result);
	}

	public function ApplyRecommendation(Request $request, Response $response, array $args): Response
	{
		$id = intval($args['id'] ?? 0);
		$userId = defined('GROCY_USER_ID') ? GROCY_USER_ID : 1;

		$service = WorkforcePeakEngineService::GetInstance();
		$result = $service->ApplyRecommendation($id, $userId);

		return $this->ApiResponse($response, $result);
	}
}
