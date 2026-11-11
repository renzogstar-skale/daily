<?php

namespace Grocy\Controllers\Api;

use Grocy\Services\RetailPreventionService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class RetailPreventionApiController extends BaseApiController
{
	private function Svc(): RetailPreventionService
	{
		return RetailPreventionService::GetInstance();
	}

	/* -------------------------------------------------------------------------
	 * EXPIRY PREDICTIVE ALERTS & RULES
	 * ------------------------------------------------------------------------- */

	public function GetExpiryAlerts(Request $request, Response $response, array $args): Response
	{
		$q = $request->getQueryParams();
		$branchId = isset($q['branch_id']) && $q['branch_id'] !== '' ? intval($q['branch_id']) : null;
		$category = $q['category'] ?? null;
		$status = $q['status'] ?? null;

		$res = $this->Svc()->GetPredictiveExpiryAlerts($branchId, $category, $status);
		return $this->ApiResponse($response, $res);
	}

	public function ResolveExpiryAlert(Request $request, Response $response, array $args): Response
	{
		$id = intval($args['id']);
		$body = (array)($request->getParsedBody() ?? []);
		$newStatus = strval($body['status'] ?? 'cerrada');
		$action = isset($body['action_taken']) ? strval($body['action_taken']) : null;
		$notes = isset($body['notes']) ? strval($body['notes']) : null;
		$photo = isset($body['photo_url']) ? strval($body['photo_url']) : null;

		try
		{
			$res = $this->Svc()->AdvanceExpiryAlert($id, $newStatus, $action, $notes, $photo, GROCY_USER_ID);
			return $this->ApiResponse($response, $res);
		}
		catch (\Throwable $e)
		{
			return $this->GenericErrorResponse($response, $e->getMessage(), 400);
		}
	}

	public function GetExpiryRules(Request $request, Response $response, array $args): Response
	{
		return $this->ApiResponse($response, $this->Svc()->GetExpiryRules());
	}

	public function SaveExpiryRule(Request $request, Response $response, array $args): Response
	{
		$body = (array)($request->getParsedBody() ?? []);
		$category = strval($body['category_name'] ?? '');
		if (!$category)
		{
			return $this->GenericErrorResponse($response, 'Nombre de categoría requerido', 400);
		}

		try
		{
			$res = $this->Svc()->SaveExpiryRule($category, $body);
			return $this->ApiResponse($response, $res);
		}
		catch (\Throwable $e)
		{
			return $this->GenericErrorResponse($response, $e->getMessage(), 400);
		}
	}

	/* -------------------------------------------------------------------------
	 * DISPATCH CONCILIATION, PICKUP VS DELIVERY & SCANS
	 * ------------------------------------------------------------------------- */

	public function GetDispatchOrderDetail(Request $request, Response $response, array $args): Response
	{
		$orderId = intval($args['id']);
		try
		{
			$res = $this->Svc()->GetDispatchOrderDetail($orderId);
			return $this->ApiResponse($response, $res);
		}
		catch (\Throwable $e)
		{
			return $this->GenericErrorResponse($response, $e->getMessage(), 404);
		}
	}

	public function VerifyPickup(Request $request, Response $response, array $args): Response
	{
		$body = (array)($request->getParsedBody() ?? []);
		$orderId = intval($body['order_id'] ?? 0);
		$pin = strval($body['pin_code'] ?? '');
		$sig = isset($body['signature_name']) ? strval($body['signature_name']) : null;
		$evi = isset($body['evidence_url']) ? strval($body['evidence_url']) : null;

		try
		{
			$res = $this->Svc()->VerifyPickupOrder($orderId, $pin, GROCY_USER_ID, $sig, $evi);
			return $this->ApiResponse($response, $res);
		}
		catch (\Throwable $e)
		{
			return $this->GenericErrorResponse($response, $e->getMessage(), 400);
		}
	}

	public function RecordDelivery(Request $request, Response $response, array $args): Response
	{
		$body = (array)($request->getParsedBody() ?? []);
		$orderId = intval($body['order_id'] ?? 0);

		try
		{
			$res = $this->Svc()->RecordDispatchDelivery($orderId, $body, GROCY_USER_ID);
			return $this->ApiResponse($response, $res);
		}
		catch (\Throwable $e)
		{
			return $this->GenericErrorResponse($response, $e->getMessage(), 400);
		}
	}

	public function FastScanUPC(Request $request, Response $response, array $args): Response
	{
		$body = (array)($request->getParsedBody() ?? []);
		$orderId = intval($body['order_id'] ?? 0);
		$barcode = strval($body['barcode'] ?? '');
		$qty = floatval($body['quantity'] ?? 1.0);
		$lot = isset($body['lot_number']) ? strval($body['lot_number']) : null;

		if (!$orderId || !$barcode)
		{
			return $this->GenericErrorResponse($response, 'Pedido y código de barras requeridos', 400);
		}

		try
		{
			$res = $this->Svc()->FastScanUPCVerify($orderId, $barcode, $qty, $lot, GROCY_USER_ID);
			return $this->ApiResponse($response, $res);
		}
		catch (\Throwable $e)
		{
			return $this->GenericErrorResponse($response, $e->getMessage(), 400);
		}
	}

	public function ResolveVariance(Request $request, Response $response, array $args): Response
	{
		$body = (array)($request->getParsedBody() ?? []);
		$varianceId = intval($body['variance_id'] ?? 0);
		$status = strval($body['resolution_status'] ?? 'authorized_exception');
		$notes = trim(strval($body['notes'] ?? ($body['resolution_notes'] ?? '')));

		try
		{
			$res = $this->Svc()->ResolveDispatchVariance($varianceId, $status, $notes, GROCY_USER_ID);
			return $this->ApiResponse($response, $res);
		}
		catch (\Throwable $e)
		{
			return $this->GenericErrorResponse($response, $e->getMessage(), 400);
		}
	}

	public function GetDispatchAnalytics(Request $request, Response $response, array $args): Response
	{
		$q = $request->getQueryParams();
		$branchId = isset($q['branch_id']) && $q['branch_id'] !== '' ? intval($q['branch_id']) : null;
		$res = $this->Svc()->GetDispatchComparativeAnalytics($branchId);
		return $this->ApiResponse($response, $res);
	}

	public function CreateDispatchOrder(Request $request, Response $response, array $args): Response
	{
		$b = (array)($request->getParsedBody() ?? []);
		try
		{
			$cos = new \Grocy\Services\CustomerOrderService();
			$res = $cos->Create($b, GROCY_USER_ID);
			$ord = $res['order'] ?? $res;
			return $this->ApiResponse($response, ['success' => true, 'order' => $ord, 'message' => "Pedido " . ($ord['order_code'] ?? '') . " registrado."]);
		}
		catch (\Throwable $e)
		{
			return $this->GenericErrorResponse($response, $e->getMessage(), 400);
		}
	}

	public function ReserveOrder(Request $request, Response $response, array $args): Response
	{
		$b = (array)($request->getParsedBody() ?? []);
		$orderId = intval($b['order_id'] ?? 0);
		try
		{
			$res = $this->Svc()->ReserveDispatchOrder($orderId, GROCY_USER_ID);
			return $this->ApiResponse($response, $res);
		}
		catch (\Throwable $e)
		{
			return $this->GenericErrorResponse($response, $e->getMessage(), 400);
		}
	}

	public function PackOrder(Request $request, Response $response, array $args): Response
	{
		$b = (array)($request->getParsedBody() ?? []);
		$orderId = intval($b['order_id'] ?? 0);
		$pkgs = intval($b['packages_count'] ?? 1);
		$notes = isset($b['notes']) ? strval($b['notes']) : null;
		try
		{
			$res = $this->Svc()->PackDispatchOrder($orderId, $pkgs, $notes, GROCY_USER_ID);
			return $this->ApiResponse($response, $res);
		}
		catch (\Throwable $e)
		{
			return $this->GenericErrorResponse($response, $e->getMessage(), 400);
		}
	}

	public function GetDispatchLabel(Request $request, Response $response, array $args): Response
	{
		$orderId = intval($args['id'] ?? 0);
		try
		{
			$res = $this->Svc()->GetDispatchLabel($orderId);
			return $this->ApiResponse($response, $res);
		}
		catch (\Throwable $e)
		{
			return $this->GenericErrorResponse($response, $e->getMessage(), 404);
		}
	}

	public function AssignPicking(Request $request, Response $response, array $args): Response
	{
		$b = (array)($request->getParsedBody() ?? []);
		$orderId = intval($b['order_id'] ?? 0);
		$pickerId = intval($b['picker_user_id'] ?? GROCY_USER_ID);
		try
		{
			$res = $this->Svc()->AssignPickingOrder($orderId, $pickerId, GROCY_USER_ID);
			return $this->ApiResponse($response, $res);
		}
		catch (\Throwable $e)
		{
			return $this->GenericErrorResponse($response, $e->getMessage(), 400);
		}
	}

	public function ScheduleTransport(Request $request, Response $response, array $args): Response
	{
		$b = (array)($request->getParsedBody() ?? []);
		$orderId = intval($b['order_id'] ?? 0);
		try
		{
			$res = $this->Svc()->ScheduleTransportOrder($orderId, $b, GROCY_USER_ID);
			return $this->ApiResponse($response, $res);
		}
		catch (\Throwable $e)
		{
			return $this->GenericErrorResponse($response, $e->getMessage(), 400);
		}
	}

	public function ConfirmExit(Request $request, Response $response, array $args): Response
	{
		$b = (array)($request->getParsedBody() ?? []);
		$orderId = intval($b['order_id'] ?? 0);
		try
		{
			$res = $this->Svc()->ConfirmDispatchExit($orderId, $b, GROCY_USER_ID);
			return $this->ApiResponse($response, $res);
		}
		catch (\Throwable $e)
		{
			return $this->GenericErrorResponse($response, $e->getMessage(), 400);
		}
	}

	public function ReportIncident(Request $request, Response $response, array $args): Response
	{
		$b = (array)($request->getParsedBody() ?? []);
		$orderId = intval($b['order_id'] ?? 0);
		try
		{
			$res = $this->Svc()->ReportLogisticsIncident($orderId, $b, GROCY_USER_ID);
			return $this->ApiResponse($response, $res);
		}
		catch (\Throwable $e)
		{
			return $this->GenericErrorResponse($response, $e->getMessage(), 400);
		}
	}

	public function ResolveIncident(Request $request, Response $response, array $args): Response
	{
		$b = (array)($request->getParsedBody() ?? []);
		$incidentId = intval($b['incident_id'] ?? 0);
		$notes = strval($b['resolution_notes'] ?? ($b['notes'] ?? ''));
		try
		{
			$res = $this->Svc()->ResolveLogisticsIncident($incidentId, $notes, GROCY_USER_ID);
			return $this->ApiResponse($response, $res);
		}
		catch (\Throwable $e)
		{
			return $this->GenericErrorResponse($response, $e->getMessage(), 400);
		}
	}

	public function ConciliateOrder(Request $request, Response $response, array $args): Response
	{
		$b = (array)($request->getParsedBody() ?? []);
		$orderId = intval($b['order_id'] ?? 0);
		$notes = isset($b['notes']) ? strval($b['notes']) : null;
		try
		{
			$res = $this->Svc()->ConciliateOrder($orderId, $notes, GROCY_USER_ID);
			return $this->ApiResponse($response, $res);
		}
		catch (\Throwable $e)
		{
			return $this->GenericErrorResponse($response, $e->getMessage(), 400);
		}
	}

	public function CancelOrder(Request $request, Response $response, array $args): Response
	{
		$b = (array)($request->getParsedBody() ?? []);
		$orderId = intval($b['order_id'] ?? 0);
		$reason = strval($b['reason'] ?? 'Cancelado');
		try
		{
			$res = $this->Svc()->CancelOrder($orderId, $reason, GROCY_USER_ID);
			return $this->ApiResponse($response, $res);
		}
		catch (\Throwable $e)
		{
			return $this->GenericErrorResponse($response, $e->getMessage(), 400);
		}
	}

	/* -------------------------------------------------------------------------
	 * LOGISTICS RETURNS & REVERSE LOGISTICS (6-STAGE LIFECYCLE)
	 * ------------------------------------------------------------------------- */

	public function GetReturns(Request $request, Response $response, array $args): Response
	{
		$q = $request->getQueryParams();
		$branchId = isset($q['branch_id']) && $q['branch_id'] !== '' ? intval($q['branch_id']) : null;
		$status = $q['status'] ?? null;
		$type = $q['return_type'] ?? null;
		$channel = $q['channel'] ?? null;

		$res = $this->Svc()->GetReturns($branchId, $status, $type, $channel);
		return $this->ApiResponse($response, $res);
	}

	public function ScanReturnUpc(Request $request, Response $response, array $args): Response
	{
		$q = $request->getQueryParams();
		$code = strval($q['code'] ?? '');
		try
		{
			$res = $this->Svc()->LookupReturnByUpc($code);
			return $this->ApiResponse($response, $res);
		}
		catch (\Throwable $e)
		{
			return $this->GenericErrorResponse($response, $e->getMessage(), 404);
		}
	}

	public function GetReturnDetail(Request $request, Response $response, array $args): Response
	{
		$id = intval($args['id']);
		try
		{
			$res = $this->Svc()->GetReturnDetail($id);
			return $this->ApiResponse($response, ['return' => $res]);
		}
		catch (\Throwable $e)
		{
			return $this->GenericErrorResponse($response, $e->getMessage(), 404);
		}
	}

	public function CreateReturn(Request $request, Response $response, array $args): Response
	{
		$body = (array)($request->getParsedBody() ?? []);
		try
		{
			$res = $this->Svc()->CreateReturn($body, GROCY_USER_ID);
			return $this->ApiResponse($response, $res);
		}
		catch (\Throwable $e)
		{
			return $this->GenericErrorResponse($response, $e->getMessage(), 400);
		}
	}

	public function ReceiveReturn(Request $request, Response $response, array $args): Response
	{
		$id = intval($args['id']);
		$body = (array)($request->getParsedBody() ?? []);
		try
		{
			$res = $this->Svc()->ReceiveReturn($id, $body, GROCY_USER_ID);
			return $this->ApiResponse($response, $res);
		}
		catch (\Throwable $e)
		{
			return $this->GenericErrorResponse($response, $e->getMessage(), 400);
		}
	}

	public function InspectReturn(Request $request, Response $response, array $args): Response
	{
		$id = intval($args['id']);
		$body = (array)($request->getParsedBody() ?? []);
		try
		{
			$res = $this->Svc()->InspectReturn($id, $body, GROCY_USER_ID);
			return $this->ApiResponse($response, $res);
		}
		catch (\Throwable $e)
		{
			return $this->GenericErrorResponse($response, $e->getMessage(), 400);
		}
	}

	public function ClassifyReturn(Request $request, Response $response, array $args): Response
	{
		$id = intval($args['id']);
		$body = (array)($request->getParsedBody() ?? []);
		try
		{
			$res = $this->Svc()->ClassifyReturn($id, $body, GROCY_USER_ID);
			return $this->ApiResponse($response, $res);
		}
		catch (\Throwable $e)
		{
			return $this->GenericErrorResponse($response, $e->getMessage(), 400);
		}
	}

	public function AuthorizeReturnDisposition(Request $request, Response $response, array $args): Response
	{
		$id = intval($args['id']);
		$body = (array)($request->getParsedBody() ?? []);
		try
		{
			$res = $this->Svc()->AuthorizeAndExecuteDisposition($id, $body, GROCY_USER_ID);
			return $this->ApiResponse($response, $res);
		}
		catch (\Throwable $e)
		{
			return $this->GenericErrorResponse($response, $e->getMessage(), 400);
		}
	}

	public function ConciliateReturn(Request $request, Response $response, array $args): Response
	{
		$id = intval($args['id']);
		$body = (array)($request->getParsedBody() ?? []);
		try
		{
			$res = $this->Svc()->ConciliateReturn($id, $body, GROCY_USER_ID);
			return $this->ApiResponse($response, $res);
		}
		catch (\Throwable $e)
		{
			return $this->GenericErrorResponse($response, $e->getMessage(), 400);
		}
	}

	public function AddReturnEvidence(Request $request, Response $response, array $args): Response
	{
		$id = intval($args['id']);
		$body = (array)($request->getParsedBody() ?? []);
		try
		{
			$res = $this->Svc()->AddReturnEvidence($id, $body, GROCY_USER_ID);
			return $this->ApiResponse($response, $res);
		}
		catch (\Throwable $e)
		{
			return $this->GenericErrorResponse($response, $e->getMessage(), 400);
		}
	}

	public function AdvanceReturn(Request $request, Response $response, array $args): Response
	{
		$id = intval($args['id']);
		$body = (array)($request->getParsedBody() ?? []);
		$newStatus = strval($body['status'] ?? 'conciliacion');

		try
		{
			$res = $this->Svc()->AdvanceReturnLifecycle($id, $newStatus, $body, GROCY_USER_ID);
			return $this->ApiResponse($response, $res);
		}
		catch (\Throwable $e)
		{
			return $this->GenericErrorResponse($response, $e->getMessage(), 400);
		}
	}

	/* -------------------------------------------------------------------------
	 * BAKERY INTELLIGENCE & AI PRODUCTION FORECAST
	 * ------------------------------------------------------------------------- */

	public function GetBakeryDashboard(Request $request, Response $response, array $args): Response
	{
		$q = $request->getQueryParams();
		$branchId = isset($q['branch_id']) && $q['branch_id'] !== '' ? intval($q['branch_id']) : null;
		$res = $this->Svc()->GetBakeryDashboard($branchId);
		return $this->ApiResponse($response, $res);
	}
}
