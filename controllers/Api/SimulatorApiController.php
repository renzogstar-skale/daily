<?php

namespace Grocy\Controllers\Api;

use Grocy\Services\SimulatorService;
use Grocy\Controllers\Users\User;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class SimulatorApiController extends BaseApiController
{
	public function EvaluateSimulation(Request $request, Response $response, array $args)
	{
		User::CheckPermission($request, User::PERMISSION_KPIS_VIEW);

		try
		{
			$body = $this->GetParsedAndFilteredRequestBody($request);
			if (empty($body['product_id']))
			{
				throw new \Exception('Seleccione un producto');
			}
			$branchId = $this->DairyRead($request, [User::PERMISSION_KPIS_VIEW], !empty($body['branch_id']) ? intval($body['branch_id']) : null, false);
			$result = SimulatorService::GetInstance()->SimulateWasteVsMarkdown(intval($body['product_id']), floatval($body['lot_amount'] ?? 0), floatval($body['markdown_discount_pct'] ?? 0.30),
				isset($body['sell_through_pct']) && $body['sell_through_pct'] !== '' ? floatval($body['sell_through_pct']) / 100 : null,
				isset($body['days_left']) && $body['days_left'] !== '' ? intval($body['days_left']) : null, $branchId);

			return $this->ApiResponse($response, $result);
		}
		catch (\Exception $ex)
		{
			return $this->GenericErrorResponse($response, $ex->getMessage());
		}
	}
}
