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
}
