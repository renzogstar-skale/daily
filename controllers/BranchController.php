<?php

namespace Grocy\Controllers;

use Grocy\Services\BranchService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class BranchController extends BaseController
{
	public function BranchesList(Request $request, Response $response, array $args)
	{
		$branchService = BranchService::GetInstance();
		$branches = $branchService->GetBranches(false);

		$startDate = date('Y-m-01');
		$endDate = date('Y-m-t');

		return $this->RenderPage($response, 'branches', [
			'branches' => \Grocy\Helpers\DairyAccess::FilterBranches($branches),
			'scorecards' => \Grocy\Helpers\DairyAccess::FilterBranches($branchService->GetBranchScorecards($startDate, $endDate)),
			'userAccess' => \Grocy\Helpers\DairyAccess::HasAny([\Grocy\Controllers\Users\User::PERMISSION_BRANCH_MANAGE]) ? array_values(array_filter($branchService->GetUserBranchAccess(), fn($u) => \Grocy\Helpers\DairyAccess::AllowedBranches() === null || count(array_intersect($u['branch_ids'], \Grocy\Helpers\DairyAccess::AllowedBranches())) > 0)) : [],
			'startDate' => $startDate,
			'endDate' => $endDate
		]);
	}

	public function BranchStock(Request $request, Response $response, array $args)
	{
		$branchId = intval($args['branchId']);
		$branchService = BranchService::GetInstance();
		$branch = $branchService->GetBranch($branchId);
		$stock = $branchService->GetBranchStock($branchId);

		return $this->RenderPage($response, 'branch_stock', [
			'branch' => $branch,
			'stock' => $stock
		]);
	}
}
