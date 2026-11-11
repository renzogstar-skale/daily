<?php

namespace Grocy\Controllers\Api;

use Grocy\Controllers\Users\User;
use Grocy\Services\SalesService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class SalesApiController extends BaseApiController
{
	private function AssertSaleBranch(Request $request, int $saleId): void
	{
		$b = \Grocy\Services\DatabaseService::GetInstance()->GetDbConnectionRaw()->query("SELECT branch_id FROM sales WHERE id = " . $saleId)->fetchColumn();
		if ($b !== false && $b !== null && !\Grocy\Helpers\DairyAccess::CanSeeBranch(intval($b)))
		{
			throw new \Grocy\Controllers\Users\PermissionMissingException($request, "BRANCH #" . intval($b));
		}
	}

	public function GetSales(Request $request, Response $response, array $args)
	{
		User::CheckPermission($request, User::PERMISSION_SALES_VIEW);

		$params = $request->getQueryParams();
		$branchId = isset($params['branch_id']) ? intval($params['branch_id']) : null;
		$startDate = $params['start_date'] ?? null;
		$endDate = $params['end_date'] ?? null;
		$limit = isset($params['limit']) ? intval($params['limit']) : 100;

		$salesService = SalesService::GetInstance();
		$sales = $salesService->GetSales($branchId, $startDate, $endDate, $limit);
		return $this->ApiResponse($response, $sales);
	}

	public function GetSale(Request $request, Response $response, array $args)
	{
		User::CheckPermission($request, User::PERMISSION_SALES_VIEW);
		$this->AssertSaleBranch($request, intval($args["saleId"]));

		$saleId = intval($args['saleId']);
		$salesService = SalesService::GetInstance();
		$sale = $salesService->GetSale($saleId);

		if (!$sale)
		{
			return $this->GenericErrorResponse($response, 'Venta no encontrada', 404);
		}

		$items = $salesService->GetSaleItems($saleId);
		return $this->ApiResponse($response, [
			'sale' => $sale,
			'items' => $items
		]);
	}

	public function CreateSale(Request $request, Response $response, array $args)
	{
		User::CheckPermission($request, User::PERMISSION_SALES_POS);

		try
		{
			$requestBody = $this->GetParsedAndFilteredRequestBody($request);
			// Descuentos, precios bajo lista, fechas y turnos solo los fija el servidor (POS: /api/pos/checkout con autorización)
			unset($requestBody['allow_pos_discount'], $requestBody['_external'], $requestBody['sold_at'], $requestBody['shift_id'], $requestBody['ticket_discount_amount'], $requestBody['event_uuid']);
			foreach ($requestBody['items'] ?? [] as $i => $it)
			{
				if (is_array($it))
				{
					unset($requestBody['items'][$i]['allow_pos_discount']);
				}
			}
			$salesService = SalesService::GetInstance();
			$sale = $salesService->CreateSale($requestBody, GROCY_USER_ID);
			$branchId = intval($sale->branch_id ?? ($requestBody['branch_id'] ?? 1));
			$shift = $salesService->GetCurrentShift($branchId);

			return $this->ApiResponse($response, [
				'success' => true,
				'sale_id' => $sale->id,
				'sale_number' => $sale->sale_number,
				'branch_id' => $branchId,
				'customer_id' => $sale->customer_id,
				'subtotal' => floatval($sale->subtotal),
				'discount_amount' => floatval($sale->discount_amount),
				'tax_amount' => floatval($sale->tax_amount),
				'total_amount' => floatval($sale->total_amount),
				'total_cogs' => floatval($sale->total_cogs),
				'gross_margin' => floatval($sale->gross_margin),
				'payment_method' => $sale->payment_method,
				'notes' => $sale->notes,
				'created_timestamp' => $sale->created_timestamp,
				'shift' => $shift,
				'message' => "Venta {$sale->sale_number} completada y stock deducido exitosamente"
			]);
		}
		catch (\Exception $ex)
		{
			return $this->GenericErrorResponse($response, $ex->getMessage());
		}
	}

	public function GetPosState(Request $request, Response $response, array $args)
	{
		User::CheckPermission($request, User::PERMISSION_SALES_POS);

		$params = $request->getQueryParams();
		$branchId = max(1, intval($params['branch_id'] ?? 1));
		$salesService = SalesService::GetInstance();

		$shift = $salesService->GetCurrentShift($branchId);
		$heldSales = $salesService->GetHeldSales($branchId);
		$recentSales = $salesService->GetSales($branchId, null, null, 25);

		return $this->ApiResponse($response, [
			'branch_id' => $branchId,
			'shift' => $shift,
			'held_sales' => $heldSales,
			'recent_sales' => $recentSales
		]);
	}

	public function OpenShift(Request $request, Response $response, array $args)
	{
		User::CheckPermission($request, User::PERMISSION_SALES_POS);

		try
		{
			$requestBody = $this->GetParsedAndFilteredRequestBody($request);
			$salesService = SalesService::GetInstance();
			$shift = $salesService->OpenShift($requestBody, GROCY_USER_ID);
			return $this->ApiResponse($response, [
				'success' => true,
				'shift' => $shift,
				'message' => 'Caja abierta exitosamente'
			]);
		}
		catch (\Exception $ex)
		{
			return $this->GenericErrorResponse($response, $ex->getMessage());
		}
	}

	public function CloseShift(Request $request, Response $response, array $args)
	{
		User::CheckPermission($request, User::PERMISSION_SALES_POS);

		try
		{
			$requestBody = $this->GetParsedAndFilteredRequestBody($request);
			$salesService = SalesService::GetInstance();
			$shift = $salesService->CloseShift($requestBody, GROCY_USER_ID);
			return $this->ApiResponse($response, [
				'success' => true,
				'shift' => $shift,
				'message' => 'Cierre de caja registrado exitosamente'
			]);
		}
		catch (\Exception $ex)
		{
			return $this->GenericErrorResponse($response, $ex->getMessage());
		}
	}

	public function HoldSale(Request $request, Response $response, array $args)
	{
		User::CheckPermission($request, User::PERMISSION_SALES_POS);

		try
		{
			$requestBody = $this->GetParsedAndFilteredRequestBody($request);
			$salesService = SalesService::GetInstance();
			$held = $salesService->HoldSale($requestBody, GROCY_USER_ID);
			$branchId = max(1, intval($requestBody['branch_id'] ?? 1));
			return $this->ApiResponse($response, [
				'success' => true,
				'held_sale' => $held,
				'held_sales' => $salesService->GetHeldSales($branchId),
				'message' => 'Venta guardada en espera exitosamente'
			]);
		}
		catch (\Exception $ex)
		{
			return $this->GenericErrorResponse($response, $ex->getMessage());
		}
	}

	public function DeleteHeldSale(Request $request, Response $response, array $args)
	{
		User::CheckPermission($request, User::PERMISSION_SALES_POS);

		try
		{
			$holdId = intval($args['holdId'] ?? 0);
			$salesService = SalesService::GetInstance();
			$salesService->DeleteHeldSale($holdId);
			return $this->ApiResponse($response, [
				'success' => true,
				'hold_id' => $holdId,
				'message' => 'Venta en espera descartada'
			]);
		}
		catch (\Exception $ex)
		{
			return $this->GenericErrorResponse($response, $ex->getMessage());
		}
	}

	public function CancelSale(Request $request, Response $response, array $args)
	{
		User::CheckPermission($request, User::PERMISSION_SALES_CANCEL);
		$this->AssertSaleBranch($request, intval($args["saleId"]));

		try
		{
			$saleId = intval($args['saleId']);
			$salesService = SalesService::GetInstance();
			$sale = $salesService->CancelSale($saleId, GROCY_USER_ID);

			return $this->ApiResponse($response, [
				'success' => true,
				'sale_id' => $sale->id,
				'sale_number' => $sale->sale_number,
				'status' => $sale->status,
				'message' => "Venta cancelada y productos reintegrados al inventario"
			]);
		}
		catch (\Exception $ex)
		{
			return $this->GenericErrorResponse($response, $ex->getMessage());
		}
	}

	public function GetCustomers(Request $request, Response $response, array $args)
	{
		User::CheckPermission($request, User::PERMISSION_SALES_VIEW);
		$salesService = SalesService::GetInstance();
		return $this->ApiResponse($response, $salesService->GetCustomersDirectory());
	}

	public function SaveCustomer(Request $request, Response $response, array $args)
	{
		User::CheckPermission($request, User::PERMISSION_SALES_POS);

		try
		{
			$requestBody = $this->GetParsedAndFilteredRequestBody($request);
			$salesService = SalesService::GetInstance();
			$result = $salesService->SaveCustomer($requestBody);
			return $this->ApiResponse($response, $result);
		}
		catch (\Exception $ex)
		{
			return $this->GenericErrorResponse($response, $ex->getMessage());
		}
	}

	public function ToggleCustomerActive(Request $request, Response $response, array $args)
	{
		User::CheckPermission($request, User::PERMISSION_SALES_POS);

		try
		{
			$customerId = intval($args['customerId'] ?? 0);
			$salesService = SalesService::GetInstance();
			$result = $salesService->ToggleCustomerActive($customerId);
			return $this->ApiResponse($response, $result);
		}
		catch (\Exception $ex)
		{
			return $this->GenericErrorResponse($response, $ex->getMessage());
		}
	}

	public function SaveCustomerNotes(Request $request, Response $response, array $args)
	{
		User::CheckPermission($request, User::PERMISSION_SALES_POS);

		try
		{
			$customerId = intval($args['customerId'] ?? 0);
			$requestBody = $this->GetParsedAndFilteredRequestBody($request);
			$notes = $requestBody['notes'] ?? null;
			$tags = array_key_exists('tags', $requestBody) ? $requestBody['tags'] : null;
			$salesService = SalesService::GetInstance();
			$result = $salesService->SaveCustomerNotes($customerId, $notes, $tags);
			return $this->ApiResponse($response, $result);
		}
		catch (\Exception $ex)
		{
			return $this->GenericErrorResponse($response, $ex->getMessage());
		}
	}

	public function ExecuteCommercialAction(Request $request, Response $response, array $args)
	{
		User::CheckPermission($request, User::PERMISSION_SALES_VIEW);

		try
		{
			$body = $this->GetParsedAndFilteredRequestBody($request);
			$actionType = $body['action_type'] ?? $body['action'] ?? '';
			$branchId = isset($body['branch_id']) ? intval($body['branch_id']) : 1;
			$db = \Grocy\Services\DatabaseService::GetInstance()->GetDbConnectionRaw();

			if ($actionType === 'investigate_drop')
			{
				$title = $body['title'] ?? 'Investigación de caída de ventas';
				$desc = $body['description'] ?? $body['reason'] ?? 'Detectada variación comercial negativa frente al período comparable';
				$priority = $body['priority'] ?? 'alta';
				$incidentCode = 'INC-VTA-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(2)));

				$bStmt = $db->prepare("SELECT name FROM branches WHERE id = :bid");
				$bStmt->execute([':bid' => $branchId]);
				$branchRow = $bStmt->fetch(\PDO::FETCH_ASSOC);
				$branchName = $branchRow['name'] ?? ("Sucursal #" . $branchId);

				$stmt = $db->prepare("
					INSERT INTO commercial_incidents (
						incident_code, type, title, priority, branch_id, branch_name,
						what_happened, where_happened, root_cause, recommended_action,
						status, created_at
					)
					VALUES (
						:code, 'drop_investigation', :title, :prio, :bid, :bname,
						:what, :where, :cause, :action,
						'Detectado', datetime('now', 'localtime')
					)
				");
				$stmt->execute([
					':code' => $incidentCode,
					':title' => $title,
					':prio' => $priority,
					':bid' => $branchId,
					':bname' => $branchName,
					':what' => $desc,
					':where' => "{$branchName} - Piso de venta / Punto de Cobro",
					':cause' => 'Baja de afluencia o quiebre de inventario bajo investigación',
					':action' => 'Auditar existencias en góndola, contrastar afluencia y verificar precios'
				]);
				$incidentId = $db->lastInsertId();

				return $this->ApiResponse($response, [
					'success' => true,
					'action_type' => $actionType,
					'incident_id' => $incidentId,
					'incident_code' => $incidentCode,
					'message' => "Expediente {$incidentCode} abierto en estado Detectado para la sucursal {$branchName}."
				]);
			}
			elseif ($actionType === 'assign_task')
			{
				$title = $body['title'] ?? 'Acción comercial en piso de venta';
				$desc = $body['description'] ?? $body['reason'] ?? 'Revisar exhibición y precios de venta';
				$stmt = $db->prepare("
					INSERT INTO workforce_store_tasks (branch_id, task_type, title, description, status, due_time, created_timestamp)
					VALUES (:bid, 'commercial_action', :title, :desc, 'pending', datetime('now', '+24 hours'), datetime('now', 'localtime'))
				");
				$stmt->execute([
					':bid' => $branchId,
					':title' => $title,
					':desc' => $desc
				]);
				$taskId = $db->lastInsertId();

				return $this->ApiResponse($response, [
					'success' => true,
					'action_type' => $actionType,
					'task_id' => $taskId,
					'message' => "Tarea comercial #{$taskId} asignada al equipo de tienda."
				]);
			}
			elseif ($actionType === 'replenish_request')
			{
				$productId = intval($body['product_id'] ?? 0);
				$productName = $body['product_name'] ?? ("Producto #" . $productId);
				$amount = floatval($body['quantity'] ?? $body['amount'] ?? 10);
				$reason = $body['reason'] ?? "Reposición rápida solicitada desde Ventas y Rentabilidad para {$amount} unidades.";
				$stmt = $db->prepare("
					INSERT INTO workforce_store_tasks (branch_id, task_type, title, description, status, due_time, created_timestamp)
					VALUES (:bid, 'replenishment_request', :title, :desc, 'pending', datetime('now', '+12 hours'), datetime('now', 'localtime'))
				");
				$stmt->execute([
					':bid' => $branchId,
					':title' => "Reposición urgente: {$productName}",
					':desc' => "{$reason} (Cantidad solicitada: {$amount})"
				]);
				return $this->ApiResponse($response, [
					'success' => true,
					'action_type' => $actionType,
					'message' => "Solicitud de reposición registrada y enlazada al plan de abastecimiento."
				]);
			}
			elseif ($actionType === 'review_discounts')
			{
				$notes = $body['notes'] ?? $body['reason'] ?? 'Revisión periódica de políticas de descuento comercial';
				$code = 'AUD-DESC-' . date('Ymd-His');

				$bStmt = $db->prepare("SELECT name FROM branches WHERE id = :bid");
				$bStmt->execute([':bid' => $branchId]);
				$branchRow = $bStmt->fetch(\PDO::FETCH_ASSOC);
				$branchName = $branchRow['name'] ?? ("Sucursal #" . $branchId);

				$stmt = $db->prepare("
					INSERT INTO commercial_incidents (
						incident_code, type, title, priority, branch_id, branch_name,
						what_happened, where_happened, root_cause, recommended_action,
						status, created_at
					)
					VALUES (
						:code, 'discount_audit', 'Auditoría de Descuentos Comerciales', 'media', :bid, :bname,
						:notes, :where, 'Evaluación de promociones y descuentos aplicados', 'Ajustar límites de descuento en POS y revisar margen neto de categoría',
						'Resuelto', datetime('now', 'localtime')
					)
				");
				$stmt->execute([
					':code' => $code,
					':bid' => $branchId,
					':bname' => $branchName,
					':notes' => $notes,
					':where' => "{$branchName} - Control de Caja y Precios"
				]);
				return $this->ApiResponse($response, [
					'success' => true,
					'action_type' => $actionType,
					'message' => "Auditoría de descuentos guardada con folio {$code}."
				]);
			}
			else
			{
				return $this->GenericErrorResponse($response, 'Acción comercial no reconocida');
			}
		}
		catch (\Throwable $e)
		{
			return $this->GenericErrorResponse($response, $e->getMessage());
		}
	}

	public function GetDepartmentPerformance(Request $request, Response $response, array $args)
	{
		User::CheckPermission($request, User::PERMISSION_SALES_VIEW);
		try
		{
			$params = $request->getQueryParams();
			$data = \Grocy\Services\DepartmentPerformanceService::GetInstance()->GetDepartmentPerformance($params);
			return $this->ApiResponse($response, $data);
		}
		catch (\Throwable $e)
		{
			return $this->GenericErrorResponse($response, $e->getMessage());
		}
	}

	/** Comparativo TY vs. LY por departamento (filtros compartidos); ?drill=<clave> agrega el desglose del departamento */
	public function GetTyLyComparison(Request $request, Response $response, array $args)
	{
		User::CheckPermission($request, User::PERMISSION_SALES_VIEW);
		try
		{
			$params = $request->getQueryParams();
			$drill = !empty($params['drill']) ? strval($params['drill']) : null;
			$data = \Grocy\Services\DepartmentPerformanceService::GetInstance()->GetTyLyComparison(\Grocy\Helpers\DailyFilters::FromQuery($params), $drill);
			return $this->ApiResponse($response, $data);
		}
		catch (\Throwable $e)
		{
			return $this->GenericErrorResponse($response, $e->getMessage());
		}
	}

	public function GetDepartmentCategoryProducts(Request $request, Response $response, array $args)
	{
		User::CheckPermission($request, User::PERMISSION_SALES_VIEW);
		try
		{
			$groupId = intval($args['groupId'] ?? 0);
			$params = $request->getQueryParams();
			$data = \Grocy\Services\DepartmentPerformanceService::GetInstance()->GetCategoryProducts($groupId, $params);
			return $this->ApiResponse($response, $data);
		}
		catch (\Throwable $e)
		{
			return $this->GenericErrorResponse($response, $e->getMessage());
		}
	}

	public function LookupSaleForReturn(Request $request, Response $response, array $args)
	{
		User::CheckPermission($request, User::PERMISSION_SALES_VIEW);
		try
		{
			$params = $request->getQueryParams();
			$q = $params['q'] ?? '';
			$branchId = isset($params['branch_id']) && $params['branch_id'] !== '' ? intval($params['branch_id']) : null;
			$sales = \Grocy\Services\PosReturnsService::GetInstance()->LookupSale($q, $branchId);
			return $this->ApiResponse($response, ['sales' => $sales]);
		}
		catch (\Throwable $e)
		{
			return $this->GenericErrorResponse($response, $e->getMessage());
		}
	}

	public function ValidatePosReturn(Request $request, Response $response, array $args)
	{
		User::CheckPermission($request, User::PERMISSION_SALES_VIEW);
		try
		{
			$b = $request->getParsedBody();
			$saleId = intval($b['sale_id'] ?? 0);
			$saleItemId = intval($b['sale_item_id'] ?? 0);
			$qty = floatval($b['quantity'] ?? 1.0);
			$condition = strval($b['condition_state'] ?? 'sin_uso');
			$resolution = strval($b['resolution'] ?? 'reembolso');
			$val = \Grocy\Services\PosReturnsService::GetInstance()->ValidateReturn($saleId, $saleItemId, $qty, $condition, $resolution);
			return $this->ApiResponse($response, $val);
		}
		catch (\Throwable $e)
		{
			return $this->GenericErrorResponse($response, $e->getMessage(), 400);
		}
	}

	public function ProcessPosReturn(Request $request, Response $response, array $args)
	{
		User::CheckPermission($request, User::PERMISSION_SALES_POS);
		try
		{
			$b = $request->getParsedBody();
			$userId = GROCY_USER_ID;
			$res = \Grocy\Services\PosReturnsService::GetInstance()->ProcessPosReturn($b, $userId);
			return $this->ApiResponse($response, $res);
		}
		catch (\Throwable $e)
		{
			return $this->GenericErrorResponse($response, $e->getMessage(), 400);
		}
	}

	/** Configuración comercial de departamentos (código, nombre, color, orden y valores de producto) */
	/** Historial de Ventas: corrección autorizada (duplicate = anular copia | date = fecha de venta) */
	public function SaveHistoryCorrection(Request $request, Response $response, array $args)
	{
		User::CheckPermission($request, User::PERMISSION_SALES_CANCEL);

		try
		{
			$body = $this->GetParsedAndFilteredRequestBody($request);
			$saleId = intval($body['sale_id'] ?? 0);
			$this->AssertSaleBranch($request, $saleId);
			$svc = \Grocy\Services\SalesHistoryAuditService::GetInstance();
			$kind = (string) ($body['kind'] ?? '');
			if ($kind === 'duplicate')
			{
				$r = $svc->VoidDuplicate($saleId, (string) ($body['reason'] ?? ''), GROCY_USER_ID);
			}
			elseif ($kind === 'date')
			{
				$r = $svc->CorrectDate($saleId, (string) ($body['datetime'] ?? ''), (string) ($body['reason'] ?? ''), GROCY_USER_ID);
			}
			else
			{
				throw new \Exception('Tipo de corrección no válido');
			}
			return $this->ApiResponse($response, ['success' => true] + $r);
		}
		catch (\Grocy\Controllers\Users\PermissionMissingException $e)
		{
			throw $e;
		}
		catch (\Throwable $e)
		{
			return $this->GenericErrorResponse($response, $e->getMessage(), 400);
		}
	}

	/** Historial de Ventas: cierre comercial de una semana o un mes terminado */
	public function SaveHistoryClosure(Request $request, Response $response, array $args)
	{
		User::CheckPermission($request, User::PERMISSION_SALES_CANCEL);

		try
		{
			$body = $this->GetParsedAndFilteredRequestBody($request);
			$r = \Grocy\Services\SalesHistoryAuditService::GetInstance()->Close((string) ($body['type'] ?? ''), (string) ($body['from'] ?? ''), (string) ($body['notes'] ?? ''), GROCY_USER_ID);
			return $this->ApiResponse($response, ['success' => true] + $r);
		}
		catch (\Throwable $e)
		{
			return $this->GenericErrorResponse($response, $e->getMessage(), 400);
		}
	}

	public function SaveDepartmentConfig(Request $request, Response $response, array $args)
	{
		User::CheckPermission($request, User::PERMISSION_MASTER_DATA_EDIT);

		try
		{
			$body = $this->GetParsedAndFilteredRequestBody($request);
			\Grocy\Services\DailyMetricsService::GetInstance()->SaveDepartments(is_array($body['departments'] ?? null) ? $body['departments'] : []);
			return $this->ApiResponse($response, ['success' => true] + \Grocy\Services\SalesReportsService::DeptConfig());
		}
		catch (\Throwable $e)
		{
			return $this->GenericErrorResponse($response, $e->getMessage(), 400);
		}
	}
}
