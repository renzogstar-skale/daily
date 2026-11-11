<?php

namespace Grocy\Controllers\Api;

use Grocy\Controllers\Users\User;
use Grocy\Services\MrpService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class MrpApiController extends BaseApiController
{
	private function AssertOrderBranch(Request $request, int $orderId): void
	{
		$b = \Grocy\Services\DatabaseService::GetInstance()->GetDbConnectionRaw()->query('SELECT branch_id FROM procurement_orders WHERE id = ' . $orderId)->fetchColumn();
		if ($b !== false && $b !== null && !\Grocy\Helpers\DairyAccess::CanSeeBranch(intval($b)))
		{
			throw new \Grocy\Controllers\Users\PermissionMissingException($request, 'BRANCH #' . intval($b));
		}
	}

	private function AssertInvoiceBranch(Request $request, int $invoiceId): void
	{
		$oid = \Grocy\Services\DatabaseService::GetInstance()->GetDbConnectionRaw()->query('SELECT order_id FROM supplier_invoices WHERE id = ' . $invoiceId)->fetchColumn();
		if ($oid !== false && $oid !== null)
		{
			$this->AssertOrderBranch($request, intval($oid));
		}
	}

	private function AssertReceiptBranch(Request $request, int $receiptId): void
	{
		$b = \Grocy\Services\DatabaseService::GetInstance()->GetDbConnectionRaw()->query('SELECT branch_id FROM procurement_receipts WHERE id = ' . $receiptId)->fetchColumn();
		if ($b !== false && $b !== null && !\Grocy\Helpers\DairyAccess::CanSeeBranch(intval($b)))
		{
			throw new \Grocy\Controllers\Users\PermissionMissingException($request, 'BRANCH #' . intval($b));
		}
	}

	public function GetMrpCalculations(Request $request, Response $response, array $args)
	{
		$params = $request->getQueryParams();
		$branchId = $this->DairyRead($request, [User::PERMISSION_PROCUREMENT_MANAGE, User::PERMISSION_PROCUREMENT_RECEIVE, User::PERMISSION_KPIS_VIEW], self::QueryBranch($request)) ?? 1;
		$horizonDays = isset($params['horizon_days']) ? intval($params['horizon_days']) : 14;

		$mrpService = MrpService::GetInstance();
		$result = $mrpService->CalculateMrp($branchId, $horizonDays);

		return $this->ApiResponse($response, $result);
	}

	public function CreateOrder(Request $request, Response $response, array $args)
	{
		User::CheckPermission($request, User::PERMISSION_PROCUREMENT_MANAGE);

		try
		{
			$body = $this->GetParsedAndFilteredRequestBody($request);
			$body['branch_id'] = $this->DairyRead($request, [User::PERMISSION_PROCUREMENT_MANAGE], !empty($body['branch_id']) ? intval($body['branch_id']) : null) ?? ($body['branch_id'] ?? null);
			$mrpService = MrpService::GetInstance();
			$order = $mrpService->CreateProcurementOrder($body, GROCY_USER_ID);

			return $this->ApiResponse($response, $order);
		}
		catch (\Exception $ex)
		{
			return $this->GenericErrorResponse($response, $ex->getMessage());
		}
	}

	public function GenerateOrders(Request $request, Response $response, array $args)
	{
		User::CheckPermission($request, User::PERMISSION_PROCUREMENT_MANAGE);

		try
		{
			$body = $this->GetParsedAndFilteredRequestBody($request);
			$branchId = $this->DairyRead($request, [User::PERMISSION_PROCUREMENT_MANAGE], !empty($body['branch_id']) ? intval($body['branch_id']) : null);
			return $this->ApiResponse($response, MrpService::GetInstance()->GenerateOrdersFromMrp($branchId, GROCY_USER_ID));
		}
		catch (\Exception $ex)
		{
			return $this->GenericErrorResponse($response, $ex->getMessage());
		}
	}

	public function GetOrders(Request $request, Response $response, array $args)
	{
		$params = $request->getQueryParams();
		$branchId = $this->DairyRead($request, [User::PERMISSION_PROCUREMENT_MANAGE, User::PERMISSION_PROCUREMENT_RECEIVE, User::PERMISSION_KPIS_VIEW], self::QueryBranch($request));
		$status = isset($params['status']) ? $params['status'] : null;

		$mrpService = MrpService::GetInstance();
		return $this->ApiResponse($response, $mrpService->GetProcurementOrders($branchId, $status));
	}

	public function ApproveOrder(Request $request, Response $response, array $args)
	{
		User::CheckPermission($request, User::PERMISSION_PROCUREMENT_MANAGE);
		$this->AssertOrderBranch($request, intval($args['orderId']));

		try
		{
			$orderId = intval($args['orderId']);
			$mrpService = MrpService::GetInstance();
			$mrpService->ApproveProcurementOrder($orderId, GROCY_USER_ID);

			return $this->ApiResponse($response, [
				'success' => true,
				'message' => "Orden de reabastecimiento #{$orderId} aprobada con éxito."
			]);
		}
		catch (\Exception $ex)
		{
			return $this->GenericErrorResponse($response, $ex->getMessage());
		}
	}

	public function GetOrder(Request $request, Response $response, array $args)
	{
		$b = \Grocy\Services\DatabaseService::GetInstance()->GetDbConnectionRaw()->query('SELECT branch_id FROM procurement_orders WHERE id = ' . intval($args['orderId']))->fetchColumn();
		$this->DairyRead($request, [User::PERMISSION_PROCUREMENT_MANAGE, User::PERMISSION_PROCUREMENT_RECEIVE, User::PERMISSION_KPIS_VIEW], $b === false || $b === null ? null : intval($b), false);
		try
		{
			return $this->ApiResponse($response, MrpService::GetInstance()->GetProcurementOrderDetail(intval($args['orderId'])));
		}
		catch (\Exception $ex)
		{
			return $this->GenericErrorResponse($response, $ex->getMessage());
		}
	}

	public function SendOrder(Request $request, Response $response, array $args)
	{
		User::CheckPermission($request, User::PERMISSION_PROCUREMENT_MANAGE);
		$this->AssertOrderBranch($request, intval($args['orderId']));

		try
		{
			$body = $this->GetParsedAndFilteredRequestBody($request);
			MrpService::GetInstance()->SendProcurementOrder(intval($args['orderId']), GROCY_USER_ID, $body['expected_delivery_date'] ?? null);
			return $this->ApiResponse($response, ['success' => true, 'message' => 'Orden enviada al proveedor']);
		}
		catch (\Exception $ex)
		{
			return $this->GenericErrorResponse($response, $ex->getMessage());
		}
	}

	public function CloseOrder(Request $request, Response $response, array $args)
	{
		User::CheckPermission($request, User::PERMISSION_PROCUREMENT_MANAGE);
		$this->AssertOrderBranch($request, intval($args['orderId']));

		try
		{
			$body = $this->GetParsedAndFilteredRequestBody($request);
			$status = MrpService::GetInstance()->CloseProcurementOrder(intval($args['orderId']), GROCY_USER_ID, $body['reason'] ?? '');
			return $this->ApiResponse($response, ['success' => true, 'status' => $status, 'message' => $status === 'cancelled' ? 'Orden cancelada' : 'Orden cerrada con faltante']);
		}
		catch (\Exception $ex)
		{
			return $this->GenericErrorResponse($response, $ex->getMessage());
		}
	}

	public function ReceiveOrder(Request $request, Response $response, array $args)
	{
		User::CheckPermission($request, User::PERMISSION_PROCUREMENT_RECEIVE);
		$this->AssertOrderBranch($request, intval($args['orderId']));

		try
		{
			$body = $this->GetParsedAndFilteredRequestBody($request);
			return $this->ApiResponse($response, MrpService::GetInstance()->ReceiveProcurementOrder(intval($args['orderId']), $body, GROCY_USER_ID));
		}
		catch (\Exception $ex)
		{
			return $this->GenericErrorResponse($response, $ex->getMessage());
		}
	}

	public function RegisterInvoice(Request $request, Response $response, array $args)
	{
		User::CheckPermission($request, User::PERMISSION_PROCUREMENT_MANAGE);
		try
		{
			$body = $this->GetParsedAndFilteredRequestBody($request);
			return $this->ApiResponse($response, \Grocy\Services\SupplierInvoiceService::GetInstance()->RegisterInvoice(intval($args['orderId']), $body, GROCY_USER_ID));
		}
		catch (\Exception $ex)
		{
			return $this->GenericErrorResponse($response, $ex->getMessage());
		}
	}

	public function GetInvoiceItems(Request $request, Response $response, array $args)
	{
		User::CheckPermission($request, User::PERMISSION_PROCUREMENT_MANAGE);
		$invoiceId = intval($args['invoiceId']);
		$this->AssertInvoiceBranch($request, $invoiceId);
		return $this->ApiResponse($response, \Grocy\Services\SupplierInvoiceService::GetInstance()->GetInvoiceItems($invoiceId));
	}

	public function DecideInvoice(Request $request, Response $response, array $args)
	{
		User::CheckPermission($request, User::PERMISSION_PROCUREMENT_MANAGE);
		try
		{
			$invoiceId = intval($args['invoiceId']);
			$this->AssertInvoiceBranch($request, $invoiceId);
			$body = $this->GetParsedAndFilteredRequestBody($request);
			\Grocy\Services\SupplierInvoiceService::GetInstance()->DecideInvoice($invoiceId, !empty($body['approve']), strval($body['reason'] ?? ''), GROCY_USER_ID);
			return $this->ApiResponse($response, ['success' => true]);
		}
		catch (\Exception $ex)
		{
			return $this->GenericErrorResponse($response, $ex->getMessage());
		}
	}

	public function GetReceivingRules(Request $request, Response $response, array $args)
	{
		$this->DairyRead($request, [User::PERMISSION_PROCUREMENT_MANAGE, User::PERMISSION_PROCUREMENT_RECEIVE, User::PERMISSION_KPIS_VIEW], null, false);
		return $this->ApiResponse($response, MrpService::GetInstance()->GetReceivingRules());
	}

	public function SaveReceivingRule(Request $request, Response $response, array $args)
	{
		User::CheckPermission($request, User::PERMISSION_PROCUREMENT_MANAGE);

		try
		{
			MrpService::GetInstance()->SaveReceivingRule(intval($args['productId']), $this->GetParsedAndFilteredRequestBody($request));
			return $this->ApiResponse($response, ['success' => true, 'message' => 'Regla de recepción guardada']);
		}
		catch (\Exception $ex)
		{
			return $this->GenericErrorResponse($response, $ex->getMessage());
		}
	}

	public function BulkApproveOrders(Request $request, Response $response, array $args)
	{
		User::CheckPermission($request, User::PERMISSION_PROCUREMENT_MANAGE);

		try
		{
			$body = $this->GetParsedAndFilteredRequestBody($request);
			$branchId = $this->DairyRead($request, [User::PERMISSION_PROCUREMENT_MANAGE], !empty($body['branch_id']) ? intval($body['branch_id']) : null);
			$allowed = \Grocy\Helpers\DairyAccess::AllowedBranches();
			$count = MrpService::GetInstance()->BulkApproveOrders($branchId, GROCY_USER_ID, $allowed);

			return $this->ApiResponse($response, [
				'success' => true,
				'approved_count' => $count,
				'message' => "Se han aprobado {$count} orden(es) de compra en borrador exitosamente."
			]);
		}
		catch (\Throwable $ex)
		{
			return $this->GenericErrorResponse($response, $ex->getMessage());
		}
	}

	public function UpdateDraftOrder(Request $request, Response $response, array $args)
	{
		User::CheckPermission($request, User::PERMISSION_PROCUREMENT_MANAGE);

		try
		{
			$orderId = intval($args['orderId']);
			$this->AssertOrderBranch($request, $orderId);
			$body = $this->GetParsedAndFilteredRequestBody($request);
			$res = MrpService::GetInstance()->UpdateDraftOrderItems($orderId, $body, GROCY_USER_ID);
			return $this->ApiResponse($response, ['success' => true, 'order' => $res, 'message' => 'Borrador actualizado correctamente']);
		}
		catch (\Throwable $ex)
		{
			return $this->GenericErrorResponse($response, $ex->getMessage());
		}
	}

	public function ConfirmOrder(Request $request, Response $response, array $args)
	{
		User::CheckPermission($request, User::PERMISSION_PROCUREMENT_MANAGE);

		try
		{
			$orderId = intval($args['orderId']);
			$this->AssertOrderBranch($request, $orderId);
			$body = $this->GetParsedAndFilteredRequestBody($request);
			$res = MrpService::GetInstance()->ConfirmProcurementOrder($orderId, $body, GROCY_USER_ID);
			return $this->ApiResponse($response, ['success' => true, 'confirmation' => $res, 'message' => 'Confirmación de proveedor registrada']);
		}
		catch (\Throwable $ex)
		{
			return $this->GenericErrorResponse($response, $ex->getMessage());
		}
	}

	public function ReceiveOrderAdmin(Request $request, Response $response, array $args)
	{
		User::CheckPermission($request, User::PERMISSION_PROCUREMENT_RECEIVE);

		try
		{
			$orderId = intval($args['orderId']);
			$this->AssertOrderBranch($request, $orderId);
			$body = $this->GetParsedAndFilteredRequestBody($request);
			$res = MrpService::GetInstance()->RecordAdministrativeReception($orderId, $body, GROCY_USER_ID);
			return $this->ApiResponse($response, $res);
		}
		catch (\Throwable $ex)
		{
			return $this->GenericErrorResponse($response, $ex->getMessage());
		}
	}

	public function ConfirmPhysicalReceipt(Request $request, Response $response, array $args)
	{
		User::CheckPermission($request, User::PERMISSION_PROCUREMENT_RECEIVE);

		try
		{
			$receiptId = intval($args['receiptId']);
			$this->AssertReceiptBranch($request, $receiptId);
			$body = $this->GetParsedAndFilteredRequestBody($request);
			$locId = !empty($body['location_id']) ? intval($body['location_id']) : null;
			$res = MrpService::GetInstance()->ConfirmPhysicalReception($receiptId, GROCY_USER_ID, $locId);
			return $this->ApiResponse($response, $res);
		}
		catch (\Throwable $ex)
		{
			return $this->GenericErrorResponse($response, $ex->getMessage());
		}
	}

	public function SimulateDemand(Request $request, Response $response, array $args)
	{
		$params = $request->getParsedBody() ?: $request->getQueryParams();
		$branchId = $this->DairyRead($request, [User::PERMISSION_PROCUREMENT_MANAGE, User::PERMISSION_PROCUREMENT_RECEIVE, User::PERMISSION_KPIS_VIEW], !empty($params['branch_id']) ? intval($params['branch_id']) : self::QueryBranch($request)) ?? 1;
		try
		{
			$res = MrpService::GetInstance()->SimulateDemand($branchId, is_array($params) ? $params : []);
			return $this->ApiResponse($response, $res);
		}
		catch (\Throwable $ex)
		{
			return $this->GenericErrorResponse($response, $ex->getMessage());
		}
	}

	public function GetBranchForecast(Request $request, Response $response, array $args)
	{
		$this->DairyRead($request, [User::PERMISSION_PROCUREMENT_MANAGE, User::PERMISSION_PROCUREMENT_RECEIVE, User::PERMISSION_KPIS_VIEW], null, false);
		$params = $request->getQueryParams();
		$horizonDays = isset($params['horizon_days']) ? intval($params['horizon_days']) : 14;
		$rows = MrpService::GetInstance()->GetBranchDemandForecast($horizonDays);
		$allowed = \Grocy\Helpers\DairyAccess::AllowedBranches();
		if ($allowed !== null)
		{
			$rows = array_values(array_filter($rows, fn($r) => in_array(intval($r['branch_id']), $allowed, true)));
		}
		return $this->ApiResponse($response, $rows);
	}

	public function GetTransferRecommendations(Request $request, Response $response, array $args)
	{
		$params = $request->getQueryParams();
		$branchId = $this->DairyRead($request, [User::PERMISSION_PROCUREMENT_MANAGE, User::PERMISSION_PROCUREMENT_RECEIVE, User::PERMISSION_KPIS_VIEW], self::QueryBranch($request));
		$horizonDays = isset($params['horizon_days']) ? intval($params['horizon_days']) : 14;
		return $this->ApiResponse($response, MrpService::GetInstance()->GetTransferRecommendations($branchId, $horizonDays));
	}

	public function ExecuteTransferRecommendation(Request $request, Response $response, array $args)
	{
		User::CheckPermission($request, User::PERMISSION_PROCUREMENT_MANAGE);
		try
		{
			$body = $this->GetParsedAndFilteredRequestBody($request);
			$fromBranch = intval($body['from_branch_id'] ?? 0);
			$toBranch = intval($body['to_branch_id'] ?? 0);
			if (!\Grocy\Helpers\DairyAccess::CanSeeBranch($fromBranch) || !\Grocy\Helpers\DairyAccess::CanSeeBranch($toBranch))
			{
				throw new \Grocy\Controllers\Users\PermissionMissingException($request, 'BRANCH TRANSFER');
			}
			$res = MrpService::GetInstance()->ExecuteRecommendedTransfer(
				$fromBranch,
				$toBranch,
				intval($body['product_id'] ?? 0),
				floatval($body['qty'] ?? 0),
				GROCY_USER_ID
			);
			return $this->ApiResponse($response, $res);
		}
		catch (\Throwable $ex)
		{
			return $this->GenericErrorResponse($response, $ex->getMessage());
		}
	}

	public function GetPendingDeliveries(Request $request, Response $response, array $args)
	{
		$branchId = $this->DairyRead($request, [User::PERMISSION_PROCUREMENT_MANAGE, User::PERMISSION_PROCUREMENT_RECEIVE, User::PERMISSION_KPIS_VIEW], self::QueryBranch($request));
		return $this->ApiResponse($response, MrpService::GetInstance()->GetPendingDeliveries($branchId));
	}

	public function GetSupplyHistory(Request $request, Response $response, array $args)
	{
		$params = $request->getQueryParams();
		$branchId = $this->DairyRead($request, [User::PERMISSION_PROCUREMENT_MANAGE, User::PERMISSION_PROCUREMENT_RECEIVE, User::PERMISSION_KPIS_VIEW], self::QueryBranch($request));
		$supplierId = !empty($params['supplier_id']) ? intval($params['supplier_id']) : null;
		$limit = !empty($params['limit']) ? intval($params['limit']) : 60;
		return $this->ApiResponse($response, MrpService::GetInstance()->GetSupplyHistory($branchId, $supplierId, $limit));
	}

	public function RequestQuotation(Request $request, Response $response, array $args)
	{
		User::CheckPermission($request, User::PERMISSION_PROCUREMENT_MANAGE);

		try
		{
			$body = $this->GetParsedAndFilteredRequestBody($request);
			$body['branch_id'] = $this->DairyRead($request, [User::PERMISSION_PROCUREMENT_MANAGE], !empty($body['branch_id']) ? intval($body['branch_id']) : null) ?? 1;
			return $this->ApiResponse($response, MrpService::GetInstance()->RequestQuotation($body, GROCY_USER_ID));
		}
		catch (\Throwable $ex)
		{
			return $this->GenericErrorResponse($response, $ex->getMessage());
		}
	}

	public function ResolveIncident(Request $request, Response $response, array $args)
	{
		User::CheckPermission($request, User::PERMISSION_PROCUREMENT_RECEIVE);

		try
		{
			$incidentId = intval($args['incidentId'] ?? 0);
			$body = $this->GetParsedAndFilteredRequestBody($request);
			$allowed = \Grocy\Helpers\DairyAccess::AllowedBranches();
			return $this->ApiResponse($response, MrpService::GetInstance()->ResolveIncident($incidentId, $body, GROCY_USER_ID, $allowed));
		}
		catch (\Throwable $ex)
		{
			return $this->GenericErrorResponse($response, $ex->getMessage());
		}
	}
}
