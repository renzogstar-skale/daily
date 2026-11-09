<?php

namespace Grocy\Controllers\Api;

use Grocy\Services\WarehouseService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class WarehouseApiController extends BaseApiController
{
	public function ConfirmPutaway(Request $request, Response $response, array $args)
	{
		$taskId = intval($args['id'] ?? 0);
		$data = $request->getParsedBody();
		$targetLocationId = !empty($data['location_id']) ? intval($data['location_id']) : null;

		$success = WarehouseService::GetInstance()->ConfirmPutaway($taskId, $targetLocationId);

		return $this->ApiResponse($response, [
			'success' => $success,
			'task_id' => $taskId,
			'message' => $success ? 'Putaway confirmado y registrado con éxito' : 'No fue posible confirmar el putaway'
		]);
	}

	public function RecordInspection(Request $request, Response $response, array $args)
	{
		$data = $request->getParsedBody() ?? [];
		if (empty($data['product_id']) || empty($data['received_units']))
		{
			return $this->GenericErrorResponse($response, 'Producto y unidades recibidas son obligatorios', 400);
		}

		$inspId = WarehouseService::GetInstance()->RecordInspection($data);

		return $this->ApiResponse($response, [
			'success' => true,
			'inspection_id' => $inspId,
			'message' => 'Inspección de recepción registrada'
		]);
	}

	public function UpdateDockStatus(Request $request, Response $response, array $args)
	{
		$dockId = intval($args['id'] ?? 0);
		$data = $request->getParsedBody() ?? [];
		$status = $data['status'] ?? 'disponible';
		$carrier = $data['carrier'] ?? null;
		$poCode = $data['po_code'] ?? null;

		$success = WarehouseService::GetInstance()->UpdateDockStatus($dockId, $status, $carrier, $poCode);

		return $this->ApiResponse($response, [
			'success' => $success,
			'dock_id' => $dockId,
			'status' => $status
		]);
	}
}
