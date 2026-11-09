<?php

namespace Grocy\Controllers;

use Grocy\Helpers\DairyAccess;
use Grocy\Services\BranchService;
use Grocy\Services\WarehouseService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class WarehouseController extends BaseController
{
	public function Dashboard(Request $request, Response $response, array $args)
	{
		$wmsService = WarehouseService::GetInstance();
		$branchService = BranchService::GetInstance();

		$branches = DairyAccess::FilterBranches($branchService->GetBranches());
		$q = $request->getQueryParams();

		$firstBranchId = !empty($branches) ? intval(is_object($branches[0]) ? $branches[0]->id : $branches[0]['id']) : 1;
		$selectedBranchId = isset($q['branch_id']) && intval($q['branch_id']) > 0
			? intval($q['branch_id'])
			: $firstBranchId;

		$activeTab = !empty($q['tab']) ? $q['tab'] : 'resumen';

		// Cargar datos del WMS
		$dashboardData = $wmsService->GetDashboardData($selectedBranchId);
		$reception = $wmsService->GetReceptionSchedule($selectedBranchId);
		$inspections = $wmsService->GetInspections($selectedBranchId);
		$putaway = $wmsService->GetPutawayAndWarehouseMap($selectedBranchId);
		$internalMovements = $wmsService->GetInternalMovements($selectedBranchId);
		$pickingOrders = $wmsService->GetOrderPickingQueue($selectedBranchId);
		$stockControl = $wmsService->GetStockControl($selectedBranchId);
		$reverseLogistics = $wmsService->GetReverseLogistics($selectedBranchId);

		return $this->RenderPage($response, 'warehouse_dashboard', [
			'branches' => $branches,
			'selectedBranchId' => $selectedBranchId,
			'activeTab' => $activeTab,
			'dashboard' => $dashboardData,
			'reception' => $reception,
			'inspections' => $inspections,
			'putaway' => $putaway,
			'internalMovements' => $internalMovements,
			'pickingOrders' => $pickingOrders,
			'stockControl' => $stockControl,
			'reverseLogistics' => $reverseLogistics,
		]);
	}
}

