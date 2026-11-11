<?php

namespace Grocy\Controllers;

use Grocy\Services\BranchService;
use Grocy\Services\MrpService;
use Grocy\Services\SupplierInvoiceService;
use Grocy\Services\SupplierProcurementService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class SupplierProcurementController extends BaseController
{
	public function Overview(Request $request, Response $response, array $args)
	{
		$procService = SupplierProcurementService::GetInstance();
		$branchService = BranchService::GetInstance();
		$mrpService = MrpService::GetInstance();
		$invoiceService = SupplierInvoiceService::GetInstance();

		$branches = $branchService->GetBranches();
		$scope = \Grocy\Helpers\DairyAccess::AllowedBranches();
		$qBranch = intval($request->getQueryParams()['branch_id'] ?? 0);
		if ($qBranch > 0 && ($scope === null || in_array($qBranch, $scope, true)))
		{
			$scope = [$qBranch];
		}
		$selectedBranchId = $qBranch > 0
			? $qBranch
			: ($scope !== null && count($scope) > 0 ? intval($scope[0]) : (!empty($branches) ? intval($branches[0]->id) : 1));

		$suppliers = $procService->GetSuppliers();
		$priceHistory = $procService->GetPriceHistory();
		$deliveries = $procService->GetDeliveries();
		$incidents = $procService->GetIncidents();
		$recommendations = $procService->GetSmartProcurementRecommendations($selectedBranchId);
		$products = $this->DB->products()->where('active = 1')->orderBy('name')->fetchAll();
		$budgetData = $procService->BudgetOverview(null, $scope);
		$supplierContracts = $procService->GetContracts();
		$invoices = $invoiceService->GetInvoices($selectedBranchId);
		$ordersForConfirmation = $mrpService->GetProcurementOrders($qBranch > 0 ? $qBranch : ($scope !== null && count($scope) === 1 ? intval($scope[0]) : null));

		$rawDb = \Grocy\Services\DatabaseService::GetInstance()->GetDbConnectionRaw();
		$rfqs = [];
		try
		{
			$bFilter = $scope !== null ? ' WHERE r.branch_id IN (' . implode(',', array_map('intval', $scope ?: [0])) . ')' : '';
			$rfqs = $rawDb->query("SELECT r.*, sl.name AS supplier_name, b.name AS branch_name
				FROM procurement_rfqs r
				JOIN shopping_locations sl ON r.supplier_id = sl.id
				JOIN branches b ON r.branch_id = b.id
				$bFilter ORDER BY r.id DESC LIMIT 25")->fetchAll(\PDO::FETCH_ASSOC);
		}
		catch (\Throwable $e)
		{
		}

		return $this->RenderPage($response, 'suppliers_procurement', [
			'suppliers' => $suppliers,
			'priceHistory' => $priceHistory,
			'deliveries' => $deliveries,
			'incidents' => $incidents,
			'recommendations' => $recommendations,
			'branches' => $branches,
			'products' => $products,
			'budgetData' => $budgetData,
			'supplierContracts' => $supplierContracts,
			'invoices' => $invoices,
			'ordersForConfirmation' => $ordersForConfirmation,
			'rfqs' => $rfqs,
			'selectedBranchId' => $selectedBranchId
		]);
	}
}
