<?php

namespace Grocy\Controllers;

use Grocy\Services\MrpService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class MrpController extends BaseController
{
	public function Overview(Request $request, Response $response, array $args)
	{
		$params = $request->getQueryParams();
		$branchId = isset($params['branch_id']) && $params['branch_id'] !== '' ? intval($params['branch_id']) : null;
		$horizonDays = isset($params['horizon_days']) ? max(1, min(60, intval($params['horizon_days']))) : 14;

		$mrpService = MrpService::GetInstance();

		return $this->RenderPage($response, 'mrp', [
			'mrp' => $mrpService->CalculateMrp($branchId, $horizonDays),
			'orders' => $mrpService->GetProcurementOrders($branchId),
			'receipts' => $mrpService->GetReceipts($branchId),
			'forecast' => $mrpService->GetForecastAccuracy($branchId, 4),
			'invoices' => \Grocy\Services\SupplierInvoiceService::GetInstance()->GetInvoices($branchId),
			'selectedBranchId' => $branchId,
			'horizonDays' => $horizonDays
		]);
	}
}
