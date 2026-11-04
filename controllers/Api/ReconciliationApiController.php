<?php

namespace Grocy\Controllers\Api;

use Grocy\Helpers\DairyAccess;
use Grocy\Services\ReconciliationService;
use Grocy\Services\SensitiveCaseService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class ReconciliationApiController extends BaseApiController
{
	private function Svc(): ReconciliationService
	{
		return ReconciliationService::GetInstance();
	}

	private function GetParsedBody(Request $request): array
	{
		return (array) ($request->getParsedBody() ?? []);
	}

	/** Las acciones que mueven stock o cambian un expediente exigen permiso en el servidor, no solo en la página */
	private function Guard(Response $response, array $permissions, callable $fn): Response
	{
		if (!DairyAccess::HasAny($permissions))
		{
			return $this->GenericErrorResponse($response, 'Sin permiso para esta operación', 403);
		}
		try
		{
			return $fn();
		}
		catch (\Exception $e)
		{
			return $this->GenericErrorResponse($response, $e->getMessage(), in_array($e->getCode(), [403, 404], true) ? $e->getCode() : 400);
		}
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
		return $this->Guard($response, SensitiveCaseService::CountPerms(), function () use ($request, $response, $args) {
			$body = $this->GetParsedBody($request);
			$branchId = intval($body['branch_id'] ?? 1);
			$productId = intval($body['product_id'] ?? 0);
			$countedQty = floatval($body['counted_qty'] ?? 0);
			$countType = $body['count_type'] ?? 'sensible';
			$notes = $body['notes'] ?? '';
			$userId = defined('GROCY_USER_ID') ? GROCY_USER_ID : 1;

			$result = $this->Svc()->ExecuteCountAndEvaluate($branchId, $productId, $countedQty, $countType, $notes, $userId);
			return $this->ApiResponse($response, $result);
		});
	}

	public function GetCase(Request $request, Response $response, array $args): Response
	{
		return $this->Guard($response, SensitiveCaseService::SupervisorPerms(), fn() => $this->ApiResponse($response, $this->Svc()->GetInvestigationCase(intval($args['caseId']))));
	}

	public function RecountCase(Request $request, Response $response, array $args): Response
	{
		return $this->Guard($response, SensitiveCaseService::CountPerms(), function () use ($request, $response, $args) {
			$caseId = intval($args['caseId']);
			$body = $this->GetParsedBody($request);
			$recountedQty = floatval($body['recounted_qty'] ?? 0);
			$notes = $body['notes'] ?? '';
			$userId = defined('GROCY_USER_ID') ? GROCY_USER_ID : 1;

			$result = $this->Svc()->RecountCase($caseId, $recountedQty, $notes, $userId);
			return $this->ApiResponse($response, $result);
		});
	}

	public function AuthorizeAdjustment(Request $request, Response $response, array $args): Response
	{
		return $this->Guard($response, SensitiveCaseService::ManagerPerms(), function () use ($request, $response, $args) {
			$caseId = intval($args['caseId']);
			$body = $this->GetParsedBody($request);
			$decision = $body['decision'] ?? 'approved';
			$reason = $body['reason'] ?? '';
			$userId = defined('GROCY_USER_ID') ? GROCY_USER_ID : 1;

			$result = $this->Svc()->AuthorizeAdjustment($caseId, $decision, $reason, $userId);
			return $this->ApiResponse($response, $result);
		});
	}

	public function CloseCase(Request $request, Response $response, array $args): Response
	{
		return $this->Guard($response, SensitiveCaseService::ManagerPerms(), function () use ($request, $response, $args) {
			$caseId = intval($args['caseId']);
			$body = $this->GetParsedBody($request);
			$resolution = $body['resolution'] ?? 'Cierre conforme de investigación';
			$userId = defined('GROCY_USER_ID') ? GROCY_USER_ID : 1;

			$result = $this->Svc()->CloseCase($caseId, $resolution, $userId);
			return $this->ApiResponse($response, $result);
		});
	}

	public function RegisterGoodsIn(Request $request, Response $response, array $args): Response
	{
		return $this->Guard($response, SensitiveCaseService::CountPerms(), function () use ($request, $response, $args) {
			$body = $this->GetParsedBody($request);
			$userId = defined('GROCY_USER_ID') ? GROCY_USER_ID : 1;

			$result = $this->Svc()->RegisterGoodsIn($body, $userId);
			return $this->ApiResponse($response, $result);
		});
	}

	public function RegisterReturn(Request $request, Response $response, array $args): Response
	{
		return $this->Guard($response, SensitiveCaseService::CountPerms(), function () use ($request, $response, $args) {
			$body = $this->GetParsedBody($request);
			$userId = defined('GROCY_USER_ID') ? GROCY_USER_ID : 1;

			$result = $this->Svc()->RegisterReturn($body, $userId);
			return $this->ApiResponse($response, $result);
		});
	}

	public function RegisterMovement(Request $request, Response $response, array $args): Response
	{
		return $this->Guard($response, SensitiveCaseService::CountPerms(), function () use ($request, $response, $args) {
			$body = $this->GetParsedBody($request);
			$userId = defined('GROCY_USER_ID') ? GROCY_USER_ID : 1;

			$result = $this->Svc()->RegisterMovement($body, $userId);
			return $this->ApiResponse($response, $result);
		});
	}
}
