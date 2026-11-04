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
		return $this->ApiResponse($response, \Grocy\Services\SupplierInvoiceService::GetInstance()->GetInvoiceItems(intval($args['invoiceId'])));
	}

	public function DecideInvoice(Request $request, Response $response, array $args)
	{
		User::CheckPermission($request, User::PERMISSION_PROCUREMENT_MANAGE);
		try
		{
			$body = $this->GetParsedAndFilteredRequestBody($request);
			\Grocy\Services\SupplierInvoiceService::GetInstance()->DecideInvoice(intval($args['invoiceId']), !empty($body['approve']), strval($body['reason'] ?? ''), GROCY_USER_ID);
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
			$branchId = !empty($body['branch_id']) ? intval($body['branch_id']) : null;
			$db = \Grocy\Services\DatabaseService::GetInstance()->GetDbConnectionRaw();

			$sql = "UPDATE procurement_orders SET status = 'approved', approved_by_user_id = :uid, approved_at = datetime('now', 'localtime') WHERE status = 'draft'";
			$params = [':uid' => GROCY_USER_ID];
			if ($branchId)
			{
				$sql .= " AND branch_id = :bid";
				$params[':bid'] = $branchId;
			}
			$stmt = $db->prepare($sql);
			$stmt->execute($params);
			$count = $stmt->rowCount();

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

	public function RequestQuotation(Request $request, Response $response, array $args)
	{
		User::CheckPermission($request, User::PERMISSION_PROCUREMENT_MANAGE);

		try
		{
			$body = $this->GetParsedAndFilteredRequestBody($request);
			$supplierId = intval($body['supplier_id'] ?? 0);
			$branchId = intval($body['branch_id'] ?? 1);
			$title = trim($body['title'] ?? 'Solicitud de Cotización Formal');
			$deadline = $body['deadline_date'] ?? date('Y-m-d', strtotime('+3 days'));
			$notes = trim($body['notes'] ?? '');
			$items = $body['items'] ?? [];

			if ($supplierId <= 0)
			{
				throw new \Exception('Debe seleccionar un proveedor válido.');
			}

			$db = \Grocy\Services\DatabaseService::GetInstance()->GetDbConnectionRaw();
			$db->exec("
				CREATE TABLE IF NOT EXISTS procurement_rfqs (
					id INTEGER PRIMARY KEY AUTOINCREMENT,
					rfq_code TEXT NOT NULL UNIQUE,
					supplier_id INTEGER NOT NULL,
					branch_id INTEGER NOT NULL,
					title TEXT NOT NULL,
					items_json TEXT NOT NULL,
					status TEXT NOT NULL DEFAULT 'Enviada',
					notes TEXT,
					deadline_date DATE,
					created_by_user_id INTEGER,
					created_at DATETIME DEFAULT CURRENT_TIMESTAMP
				)
			");

			$rfqCode = 'RFQ-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(2)));

			$stmt = $db->prepare("
				INSERT INTO procurement_rfqs (rfq_code, supplier_id, branch_id, title, items_json, status, notes, deadline_date, created_by_user_id, created_at)
				VALUES (:code, :sid, :bid, :title, :items, 'Enviada', :notes, :deadline, :uid, datetime('now', 'localtime'))
			");
			$stmt->execute([
				':code' => $rfqCode,
				':sid' => $supplierId,
				':bid' => $branchId,
				':title' => $title,
				':items' => json_encode($items),
				':notes' => $notes,
				':deadline' => $deadline,
				':uid' => GROCY_USER_ID
			]);
			$rfqId = $db->lastInsertId();

			return $this->ApiResponse($response, [
				'success' => true,
				'rfq_id' => $rfqId,
				'rfq_code' => $rfqCode,
				'message' => "Solicitud de cotización {$rfqCode} generada y registrada para el proveedor."
			]);
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
			$notes = trim($body['resolution_notes'] ?? $body['notes'] ?? 'Incidencia resuelta y conciliada con el proveedor');
			$costImpact = floatval($body['cost_impact'] ?? 0);
			$status = $body['status'] ?? 'resolved';

			$db = \Grocy\Services\DatabaseService::GetInstance()->GetDbConnectionRaw();
			$stmt = $db->prepare("
				UPDATE supplier_incidents 
				SET status = :status, resolution_notes = :notes, cost_impact = :cost
				WHERE id = :id
			");
			$stmt->execute([
				':status' => $status,
				':notes' => $notes,
				':cost' => $costImpact,
				':id' => $incidentId
			]);

			return $this->ApiResponse($response, [
				'success' => true,
				'incident_id' => $incidentId,
				'message' => "Incidencia #{$incidentId} resuelta formalmente con el proveedor."
			]);
		}
		catch (\Throwable $ex)
		{
			return $this->GenericErrorResponse($response, $ex->getMessage());
		}
	}
}
