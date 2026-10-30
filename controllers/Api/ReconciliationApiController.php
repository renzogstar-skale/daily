<?php

namespace Grocy\Controllers\Api;

use Grocy\Services\ReconciliationService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class ReconciliationApiController extends BaseApiController
{
	private function Svc(): ReconciliationService
	{
		return ReconciliationService::GetInstance();
	}

	public function GetOverview(Request $request, Response $response, array $args): Response
	{
		$params = $request->getQueryParams();
		$branchId = isset($params['branch_id']) && $params['branch_id'] !== '' ? intval($params['branch_id']) : null;
		$department = $params['department'] ?? null;
		$riskLevel = $params['risk_level'] ?? null;

		$data = $this->Svc()->GetDashboardOverview($branchId, $department, $riskLevel);
		return $this->ApiResponse($response, $data);
	}

	public function GetProducts(Request $request, Response $response, array $args): Response
	{
		$params = $request->getQueryParams();
		$branchId = isset($params['branch_id']) && $params['branch_id'] !== '' ? intval($params['branch_id']) : null;
		$department = $params['department'] ?? null;
		$query = $params['q'] ?? null;

		$data = $this->Svc()->GetProductsCatalog($branchId, $department, $query);
		return $this->ApiResponse($response, $data);
	}

	public function GetProduct(Request $request, Response $response, array $args): Response
	{
		$productId = intval($args['productId']);
		$params = $request->getQueryParams();
		$branchId = isset($params['branch_id']) && $params['branch_id'] !== '' ? intval($params['branch_id']) : null;

		$data = $this->Svc()->GetProductCard($productId, $branchId);
		return $this->ApiResponse($response, $data);
	}

	public function GetTimeline(Request $request, Response $response, array $args): Response
	{
		$productId = intval($args['productId']);
		$params = $request->getQueryParams();
		$branchId = isset($params['branch_id']) && $params['branch_id'] !== '' ? intval($params['branch_id']) : null;
		$event = $params['event'] ?? null;

		$data = $this->Svc()->GetTimeline($productId, $branchId, $event);
		return $this->ApiResponse($response, $data);
	}

	public function ExecuteCount(Request $request, Response $response, array $args): Response
	{
		$body = $this->GetParsedBody($request);
		$branchId = intval($body['branch_id'] ?? 1);
		$productId = intval($body['product_id'] ?? 0);
		$countedQty = floatval($body['counted_qty'] ?? 0);
		$countType = $body['count_type'] ?? 'sensible';
		$notes = $body['notes'] ?? '';
		$userId = defined('GROCY_USER_ID') ? GROCY_USER_ID : 1;

		$result = $this->Svc()->ExecuteCountAndEvaluate($branchId, $productId, $countedQty, $countType, $notes, $userId);
		return $this->ApiResponse($response, $result);
	}

	public function GetCase(Request $request, Response $response, array $args): Response
	{
		$caseId = intval($args['caseId']);
		$data = $this->Svc()->GetInvestigationCase($caseId);
		return $this->ApiResponse($response, $data);
	}

	public function RecountCase(Request $request, Response $response, array $args): Response
	{
		$caseId = intval($args['caseId']);
		$body = $this->GetParsedBody($request);
		$recountedQty = floatval($body['recounted_qty'] ?? 0);
		$notes = $body['notes'] ?? '';
		$userId = defined('GROCY_USER_ID') ? GROCY_USER_ID : 1;

		$result = $this->Svc()->RecountCase($caseId, $recountedQty, $notes, $userId);
		return $this->ApiResponse($response, $result);
	}

	public function AuthorizeAdjustment(Request $request, Response $response, array $args): Response
	{
		$caseId = intval($args['caseId']);
		$body = $this->GetParsedBody($request);
		$decision = $body['decision'] ?? 'approved';
		$reason = $body['reason'] ?? '';
		$userId = defined('GROCY_USER_ID') ? GROCY_USER_ID : 1;

		$result = $this->Svc()->AuthorizeAdjustment($caseId, $decision, $reason, $userId);
		return $this->ApiResponse($response, $result);
	}

	public function CloseCase(Request $request, Response $response, array $args): Response
	{
		$caseId = intval($args['caseId']);
		$body = $this->GetParsedBody($request);
		$resolution = $body['resolution'] ?? 'Cierre conforme de investigación';
		$userId = defined('GROCY_USER_ID') ? GROCY_USER_ID : 1;

		$result = $this->Svc()->CloseCase($caseId, $resolution, $userId);
		return $this->ApiResponse($response, $result);
	}

	public function RegisterGoodsIn(Request $request, Response $response, array $args): Response
	{
		$body = $this->GetParsedBody($request);
		$userId = defined('GROCY_USER_ID') ? GROCY_USER_ID : 1;

		$result = $this->Svc()->RegisterGoodsIn($body, $userId);
		return $this->ApiResponse($response, $result);
	}

	public function RegisterReturn(Request $request, Response $response, array $args): Response
	{
		$body = $this->GetParsedBody($request);
		$userId = defined('GROCY_USER_ID') ? GROCY_USER_ID : 1;

		$result = $this->Svc()->RegisterReturn($body, $userId);
		return $this->ApiResponse($response, $result);
	}

	public function RegisterMovement(Request $request, Response $response, array $args): Response
	{
		$body = $this->GetParsedBody($request);
		$userId = defined('GROCY_USER_ID') ? GROCY_USER_ID : 1;

		$result = $this->Svc()->RegisterMovement($body, $userId);
		return $this->ApiResponse($response, $result);
	}
}
