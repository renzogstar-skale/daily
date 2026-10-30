<?php

namespace Grocy\Controllers\Api;

use Grocy\Controllers\Users\User;
use Grocy\Services\SupplierProcurementService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class SupplierProcurementApiController extends BaseApiController
{
	public function GetSuppliers(Request $request, Response $response, array $args)
	{
		$this->DairyRead($request, [User::PERMISSION_PROCUREMENT_MANAGE, User::PERMISSION_PROCUREMENT_RECEIVE, User::PERMISSION_KPIS_VIEW], null, false);
		$procService = SupplierProcurementService::GetInstance();
		$params = $request->getQueryParams();
		$activeOnly = isset($params['active_only']) ? boolval($params['active_only']) : null;

		return $this->ApiResponse($response, $procService->GetSuppliers($activeOnly));
	}

	public function GetSupplier(Request $request, Response $response, array $args)
	{
		$this->DairyRead($request, [User::PERMISSION_PROCUREMENT_MANAGE, User::PERMISSION_PROCUREMENT_RECEIVE, User::PERMISSION_KPIS_VIEW], null, false);
		$procService = SupplierProcurementService::GetInstance();
		$supplier = $procService->GetSupplier(intval($args['supplierId']));

		if (!$supplier)
		{
			return $this->GenericErrorResponse($response, 'Proveedor no encontrado', 404);
		}

		return $this->ApiResponse($response, $supplier);
	}

	public function SaveSupplier(Request $request, Response $response, array $args)
	{
		User::CheckPermission($request, User::PERMISSION_PROCUREMENT_MANAGE);

		$procService = SupplierProcurementService::GetInstance();
		$data = $request->getParsedBody();

		if (empty($data['name']))
		{
			return $this->GenericErrorResponse($response, 'El nombre del proveedor es obligatorio', 400);
		}

		try
		{
			$id = $procService->SaveSupplier($data);
			return $this->ApiResponse($response, [
				'success' => true,
				'supplier_id' => $id,
				'message' => 'Proveedor guardado exitosamente'
			]);
		}
		catch (\Exception $ex)
		{
			return $this->GenericErrorResponse($response, $ex->getMessage(), 500);
		}
	}

	public function ToggleActive(Request $request, Response $response, array $args)
	{
		User::CheckPermission($request, User::PERMISSION_PROCUREMENT_MANAGE);

		$procService = SupplierProcurementService::GetInstance();
		$supplierId = intval($args['supplierId']);
		$data = $request->getParsedBody();
		$active = isset($data['active']) ? intval($data['active']) : 1;

		$procService->ToggleSupplierActive($supplierId, $active);
		return $this->ApiResponse($response, [
			'success' => true,
			'message' => $active ? 'Proveedor activado' : 'Proveedor desactivado'
		]);
	}

	public function GetCostComparison(Request $request, Response $response, array $args)
	{
		$this->DairyRead($request, [User::PERMISSION_PROCUREMENT_MANAGE, User::PERMISSION_PROCUREMENT_RECEIVE, User::PERMISSION_KPIS_VIEW], null, false);
		$procService = SupplierProcurementService::GetInstance();
		$productId = intval($args['productId']);

		return $this->ApiResponse($response, $procService->GetCostComparison($productId));
	}

	public function AddPrice(Request $request, Response $response, array $args)
	{
		User::CheckPermission($request, User::PERMISSION_PROCUREMENT_MANAGE);

		$procService = SupplierProcurementService::GetInstance();
		$data = $request->getParsedBody();

		if (empty($data['supplier_id']) || empty($data['product_id']) || !isset($data['price']))
		{
			return $this->GenericErrorResponse($response, 'Proveedor, producto y precio son obligatorios', 400);
		}

		$id = $procService->AddPriceRecord($data);
		return $this->ApiResponse($response, [
			'success' => true,
			'price_id' => $id,
			'message' => 'Cotización / precio registrado exitosamente'
		]);
	}

	public function RecordDelivery(Request $request, Response $response, array $args)
	{
		User::CheckPermission($request, User::PERMISSION_PROCUREMENT_RECEIVE);

		$procService = SupplierProcurementService::GetInstance();
		$data = $request->getParsedBody();

		if (empty($data['supplier_id']) || empty($data['expected_delivery_date']))
		{
			return $this->GenericErrorResponse($response, 'Datos de entrega incompletos', 400);
		}

		$id = $procService->RecordDelivery($data);
		return $this->ApiResponse($response, [
			'success' => true,
			'delivery_id' => $id,
			'message' => 'Recepción de entrega registrada'
		]);
	}

	public function SaveIncident(Request $request, Response $response, array $args)
	{
		User::CheckPermission($request, User::PERMISSION_PROCUREMENT_RECEIVE);

		$procService = SupplierProcurementService::GetInstance();
		$data = $request->getParsedBody();

		if (empty($data['supplier_id']) || empty($data['description']))
		{
			return $this->GenericErrorResponse($response, 'Proveedor y descripción son obligatorios', 400);
		}

		$id = $procService->SaveIncident($data);
		return $this->ApiResponse($response, [
			'success' => true,
			'incident_id' => $id,
			'message' => 'Incidencia guardada correctamente'
		]);
	}

	public function GetRecommendations(Request $request, Response $response, array $args)
	{
		$this->DairyRead($request, [User::PERMISSION_PROCUREMENT_MANAGE, User::PERMISSION_PROCUREMENT_RECEIVE, User::PERMISSION_KPIS_VIEW], null, false);
		$procService = SupplierProcurementService::GetInstance();
		return $this->ApiResponse($response, $procService->GetSmartProcurementRecommendations());
	}
}
