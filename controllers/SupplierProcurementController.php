<?php

namespace Grocy\Controllers;

use Grocy\Services\BranchService;
use Grocy\Services\SupplierProcurementService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class SupplierProcurementController extends BaseController
{
	public function Overview(Request $request, Response $response, array $args)
	{
		$procService = SupplierProcurementService::GetInstance();
		$branchService = BranchService::GetInstance();

		$suppliers = $procService->GetSuppliers();
		$priceHistory = $procService->GetPriceHistory();
		$deliveries = $procService->GetDeliveries();
		$incidents = $procService->GetIncidents();
		$recommendations = $procService->GetSmartProcurementRecommendations();
		$branches = $branchService->GetBranches();

		// Productos para comparador y formularios
		$products = $this->DB->products()->where('active = 1')->orderBy('name')->fetchAll();

		// Presupuesto y compromisos calculados desde metas, recepciones y órdenes abiertas (sin cifras fijas)
		$scope = \Grocy\Helpers\DairyAccess::AllowedBranches();
		$qBranch = intval($request->getQueryParams()['branch_id'] ?? 0);
		if ($qBranch > 0 && ($scope === null || in_array($qBranch, $scope, true)))
		{
			$scope = [$qBranch];
		}
		$budgetData = $procService->BudgetOverview(null, $scope);

				$rawDb = \Grocy\Services\DatabaseService::GetInstance()->GetDbConnectionRaw();
		$supplierContracts = $rawDb->query("SELECT * FROM daily_supplier_contracts ORDER BY id DESC")->fetchAll(\PDO::FETCH_ASSOC);
return $this->RenderPage($response, 'suppliers_procurement', [
			'suppliers' => $suppliers,
			'priceHistory' => $priceHistory,
			'deliveries' => $deliveries,
			'incidents' => $incidents,
			'recommendations' => $recommendations,
			'branches' => $branches,
			'products' => $products,
			'budgetData' => $budgetData,
			'supplierContracts' => $supplierContracts
		]);
	}
}
