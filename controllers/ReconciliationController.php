<?php

namespace Grocy\Controllers;

use Grocy\Services\BranchService;
use Grocy\Services\DatabaseService;
use Grocy\Services\ReconciliationService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class ReconciliationController extends BaseController
{
	private function Svc(): ReconciliationService
	{
		return ReconciliationService::GetInstance();
	}

	public function Overview(Request $request, Response $response, array $args): Response
	{
		$params = $request->getQueryParams();
		$branchId = isset($params['branch_id']) && $params['branch_id'] !== '' ? intval($params['branch_id']) : null;
		$department = $params['department'] ?? null;
		$riskLevel = $params['risk_level'] ?? null;
		$query = $params['q'] ?? null;
		$selectedProductId = isset($params['product_id']) && $params['product_id'] !== '' ? intval($params['product_id']) : null;
		$selectedCaseId = isset($params['case_id']) && $params['case_id'] !== '' ? intval($params['case_id']) : null;

		$branches = BranchService::GetInstance()->GetBranches();

		// Resumen general de KPIs corporativos
		$overview = $this->Svc()->GetDashboardOverview($branchId, $department, $riskLevel);

		// Catálogo de productos con filtros
		$products = $this->Svc()->GetProductsCatalog($branchId, $department, $query);

		// Lista de departamentos para el filtro
		$db = DatabaseService::GetInstance()->GetDbConnectionRaw();
		$departments = $db->query("
			SELECT DISTINCT department FROM products WHERE department IS NOT NULL AND department != '' ORDER BY department ASC
		")->fetchAll(\PDO::FETCH_COLUMN);
		if (empty($departments))
		{
			$departments = ['Electrónica y Telefonía', 'Lácteos y Leches', 'Quesos y Untables', 'Bebidas y Licores', 'Carnes y Aves', 'General'];
		}

		// Determinar producto seleccionado por defecto:
		// Preferir el producto con ID 175 (Smartphone Galaxy Pro) o el primer producto con caso abierto / sensible
		if ($selectedProductId === null && !empty($products))
		{
			$findPhone = array_filter($products, fn($p) => str_contains(mb_strtolower($p['name']), 'smartphone') || $p['sku'] === 'TECH-SMP-001');
			if (!empty($findPhone))
			{
				$selectedProductId = intval(reset($findPhone)['id']);
			}
			else
			{
				$findSensitive = array_filter($products, fn($p) => intval($p['is_sensitive']) === 1 || !empty($p['open_case_id']));
				$selectedProductId = !empty($findSensitive) ? intval(reset($findSensitive)['id']) : intval($products[0]['id']);
			}
		}

		// Cargar ficha 360° y línea de tiempo del producto seleccionado
		$selectedProductCard = null;
		$timeline = [];
		if ($selectedProductId !== null)
		{
			try
			{
				$selectedProductCard = $this->Svc()->GetProductCard($selectedProductId, $branchId);
				$timeline = $this->Svc()->GetTimeline($selectedProductId, $branchId);
			}
			catch (\Throwable $e)
			{
			}
		}

		// Si se solicitó un caso específico o el producto tiene caso activo
		$caseDetail = null;
		$caseToLoad = $selectedCaseId ?: ($selectedProductCard['active_case']['id'] ?? null);
		if ($caseToLoad)
		{
			try
			{
				$caseDetail = $this->Svc()->GetInvestigationCase(intval($caseToLoad));
			}
			catch (\Throwable $e)
			{
			}
		}

		return $this->RenderPage($response, 'reconciliation', [
			'branches' => $branches,
			'selectedBranchId' => $branchId,
			'departments' => $departments,
			'selectedDepartment' => $department,
			'riskLevel' => $riskLevel,
			'query' => $query,
			'overview' => $overview,
			'products' => $products,
			'selectedProductId' => $selectedProductId,
			'selectedProductCard' => $selectedProductCard,
			'timeline' => $timeline,
			'caseDetail' => $caseDetail,
			'selectedCaseId' => $selectedCaseId
		]);
	}
}
