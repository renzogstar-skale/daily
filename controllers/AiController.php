<?php

namespace Grocy\Controllers;

use Grocy\Services\AIAdvisorService;
use Grocy\Services\BranchService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class AiController extends BaseController
{
	public function AdvisorView(Request $request, Response $response, array $args)
	{
		$params = $request->getQueryParams();
		$branchId = isset($params['branch_id']) ? intval($params['branch_id']) : null;

		$aiService = AIAdvisorService::GetInstance();
		$branchService = BranchService::GetInstance();

		$alerts = $aiService->GetSmartAlerts($branchId);
		$branches = $branchService->GetBranches();
		$products = $this->DB->products()->where('active = 1')->orderBy('name')->fetchAll();

		return $this->RenderPage($response, 'ai_advisor', [
			'alerts' => $alerts,
			'branches' => $branches,
			'products' => $products,
			'selectedBranchId' => $branchId
		]);
	}
}
