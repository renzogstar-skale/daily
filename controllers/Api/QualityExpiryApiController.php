<?php

namespace Grocy\Controllers\Api;

use Grocy\Controllers\Users\User;
use Grocy\Services\QualityExpiryService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class QualityExpiryApiController extends BaseApiController
{
	public function GetBatchExpiry(Request $request, Response $response, array $args)
	{
		$qualityService = QualityExpiryService::GetInstance();
		$params = $request->getQueryParams();
		$branchId = $this->DairyRead($request, [User::PERMISSION_QUALITY_INSPECT, User::PERMISSION_QUALITY_QUARANTINE, User::PERMISSION_STOCK, User::PERMISSION_KPIS_VIEW], self::QueryBranch($request), false);

		return $this->ApiResponse($response, $qualityService->GetBatchExpiryOverview($branchId));
	}

	public function GetColdChain(Request $request, Response $response, array $args)
	{
		$qualityService = QualityExpiryService::GetInstance();
		$params = $request->getQueryParams();
		$branchId = $this->DairyRead($request, [User::PERMISSION_QUALITY_INSPECT, User::PERMISSION_QUALITY_QUARANTINE, User::PERMISSION_STOCK, User::PERMISSION_KPIS_VIEW], self::QueryBranch($request), false);

		return $this->ApiResponse($response, $qualityService->GetColdChainSummary($branchId));
	}

	public function GetTemperatures(Request $request, Response $response, array $args)
	{
		$qualityService = QualityExpiryService::GetInstance();
		$params = $request->getQueryParams();
		$locationId = isset($params['location_id']) ? intval($params['location_id']) : null;
		$this->DairyRead($request, [User::PERMISSION_QUALITY_INSPECT, User::PERMISSION_QUALITY_QUARANTINE, User::PERMISSION_STOCK, User::PERMISSION_KPIS_VIEW], null, false);

		return $this->ApiResponse($response, $qualityService->GetTemperatureLogs($locationId));
	}

	public function RecordTemperature(Request $request, Response $response, array $args)
	{
		User::CheckPermission($request, User::PERMISSION_QUALITY_INSPECT);

		$qualityService = QualityExpiryService::GetInstance();
		$data = $request->getParsedBody();

		if (empty($data['location_id']) || !isset($data['temperature']))
		{
			return $this->GenericErrorResponse($response, 'Ubicación y temperatura son obligatorios', 400);
		}

		$id = $qualityService->RecordTemperature($data);
		return $this->ApiResponse($response, [
			'success' => true,
			'log_id' => $id,
			'message' => 'Medición de temperatura registrada correctamente'
		]);
	}

	public function RecordInspection(Request $request, Response $response, array $args)
	{
		User::CheckPermission($request, User::PERMISSION_QUALITY_INSPECT);

		$qualityService = QualityExpiryService::GetInstance();
		$data = $request->getParsedBody();

		if (empty($data['product_id']) || empty($data['inspector_name']))
		{
			return $this->GenericErrorResponse($response, 'Producto e inspector son obligatorios', 400);
		}

		$id = $qualityService->RecordInspection($data);
		return $this->ApiResponse($response, [
			'success' => true,
			'inspection_id' => $id,
			'message' => 'Control de calidad registrado'
		]);
	}

	public function GetQuarantine(Request $request, Response $response, array $args)
	{
		$qualityService = QualityExpiryService::GetInstance();
		$params = $request->getQueryParams();
		$status = $params['status'] ?? 'in_quarantine';
		$this->DairyRead($request, [User::PERMISSION_QUALITY_INSPECT, User::PERMISSION_QUALITY_QUARANTINE, User::PERMISSION_STOCK, User::PERMISSION_KPIS_VIEW], null, false);

		return $this->ApiResponse($response, $qualityService->GetQuarantineItems($status));
	}

	public function MoveToQuarantine(Request $request, Response $response, array $args)
	{
		User::CheckPermission($request, User::PERMISSION_QUALITY_QUARANTINE);

		$qualityService = QualityExpiryService::GetInstance();
		$data = $request->getParsedBody();

		if (empty($data['product_id']) || empty($data['stock_id']) || empty($data['amount']))
		{
			return $this->GenericErrorResponse($response, 'Producto, lote y cantidad son obligatorios', 400);
		}

		$id = $qualityService->MoveToQuarantine($data);
		return $this->ApiResponse($response, [
			'success' => true,
			'quarantine_id' => $id,
			'message' => 'Lote aislado en cuarentena correctamente'
		]);
	}

	public function ResolveQuarantine(Request $request, Response $response, array $args)
	{
		User::CheckPermission($request, User::PERMISSION_QUALITY_QUARANTINE);

		$qualityService = QualityExpiryService::GetInstance();
		$quarantineId = intval($args['quarantineId']);
		$data = $request->getParsedBody();
		$resolution = $data['resolution'] ?? 'released';
		$notes = $data['notes'] ?? '';

		try
		{
			$qualityService->ResolveQuarantine($quarantineId, $resolution, $notes);
			return $this->ApiResponse($response, [
				'success' => true,
				'message' => ($resolution === 'discarded_as_waste') 
					? 'Lote dado de baja e integrado al Control de Mermas' 
					: 'Lote liberado e integrado a inventario activo'
			]);
		}
		catch (\Exception $ex)
		{
			return $this->GenericErrorResponse($response, $ex->getMessage(), 500);
		}
	}

	public function GetTraceability(Request $request, Response $response, array $args)
	{
		$qualityService = QualityExpiryService::GetInstance();
		$identifier = trim($args['identifier'] ?? '');
		$this->DairyRead($request, [User::PERMISSION_QUALITY_INSPECT, User::PERMISSION_QUALITY_QUARANTINE, User::PERMISSION_STOCK, User::PERMISSION_KPIS_VIEW], null, false);

		if (empty($identifier))
		{
			return $this->GenericErrorResponse($response, 'Identificador o lote requerido', 400);
		}

		return $this->ApiResponse($response, $qualityService->GetTraceabilityHistory($identifier));
	}
}
