<?php

namespace Grocy\Controllers;

use Grocy\Services\BranchService;
use Grocy\Services\QualityExpiryService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class QualityExpiryController extends BaseController
{
	public function Overview(Request $request, Response $response, array $args)
	{
		$qualityService = QualityExpiryService::GetInstance();
		$branchService = BranchService::GetInstance();

		$params = $request->getQueryParams();
		$branchId = isset($params['branch_id']) && !empty($params['branch_id']) ? intval($params['branch_id']) : null;

		$batches = $qualityService->GetBatchExpiryOverview($branchId);
		$coldChain = $qualityService->GetColdChainSummary($branchId);
		$temperatureLogs = $qualityService->GetTemperatureLogs(null, 25);
		$inspections = $qualityService->GetInspections(null, 25);
		$quarantineItems = $qualityService->GetQuarantineItems('in_quarantine');
		$stats = $qualityService->GetQualityStats($branchId);
		$branches = $branchService->GetBranches();

		// Alertas activas del módulo
		$alertCounts = ['all' => 0, 'P1' => 0, 'overdue' => 0, 'unassigned' => 0];
		try {
			$alertService = \Grocy\Services\AlertCenterService::GetInstance();
			$f = ['category' => 'quality'];
			if ($branchId !== null && $branchId > 0) {
				$f['branch_id'] = $branchId;
			}
			$alertList = $alertService->List($f, 'all', 1, 1, 'priority');
			if (isset($alertList['counts'])) {
				$alertCounts = $alertList['counts'];
			}
		} catch (\Throwable $e) {
			// fallback
		}

		// Lotes que requieren atención (hasta 3 tarjetas prioritarias con foto)
		$attentionBatches = [];
		$seenProducts = [];
		$urgentBatches = array_filter($batches, fn($b) => $b['risk_level'] === 'expired' || $b['risk_level'] === 'critical');
		usort($urgentBatches, function($a, $b) {
			if ($a['days_left'] != $b['days_left']) {
				return $a['days_left'] <=> $b['days_left'];
			}
			return floatval($b['total_value']) <=> floatval($a['total_value']);
		});
		foreach ($urgentBatches as $b) {
			if (!in_array($b['product_id'], $seenProducts)) {
				$seenProducts[] = $b['product_id'];
				$img = null;
				$pNameLower = mb_strtolower($b['product_name']);
				if (str_contains($pNameLower, 'tomate')) {
					$img = '/img/control_center/card_tomate.jpg';
				} elseif (str_contains($pNameLower, 'piment') || str_contains($pNameLower, 'pimiento')) {
					$img = '/img/control_center/card_pimenton.jpg';
				} elseif (str_contains($pNameLower, 'leche') || str_contains($pNameLower, 'dairy')) {
					$img = '/img/control_center/card_leche.jpg';
				} elseif (str_contains($pNameLower, 'pepino')) {
					$img = '/img/control_center/pepino.jpg';
				} elseif (str_contains($pNameLower, 'yogur')) {
					$img = '/img/control_center/yogur_griego.jpg';
				} elseif (str_contains($pNameLower, 'queso')) {
					$img = '/img/control_center/queso_mozzarella.jpg';
				}
				$b['card_image'] = $img;
				$attentionBatches[] = $b;
				if (count($attentionBatches) >= 3) break;
			}
		}

		// Ubicaciones de frío para registros de temperatura
		$coldLocations = $this->DB->locations()->where("active = 1 AND (is_freezer = 1 OR name LIKE '%Refrig%' OR name LIKE '%Fría%' OR name LIKE '%Góndola%')")->fetchAll();
		$allLocations = $this->DB->locations()->where('active = 1')->fetchAll();
		$products = $this->DB->products()->where('active = 1')->orderBy('name')->fetchAll();
		$retailPreventionSvc = \Grocy\Services\RetailPreventionService::GetInstance();
		$predictiveAlerts = $retailPreventionSvc->GetPredictiveExpiryAlerts($branchId);
		$categoryRules = $retailPreventionSvc->GetCategoryExpiryRules();

		return $this->RenderPage($response, 'quality_expiry', [
			'batches' => $batches,
			'attentionBatches' => $attentionBatches,
			'coldChain' => $coldChain,
			'temperatureLogs' => $temperatureLogs,
			'inspections' => $inspections,
			'quarantineItems' => $quarantineItems,
			'stats' => $stats,
			'alertCounts' => $alertCounts,
			'branches' => $branches,
			'coldLocations' => $coldLocations,
			'allLocations' => $allLocations,
			'products' => $products,
			'quantityUnits' => $quantityUnits,
			'selectedBranchId' => $branchId,
			'predictiveAlerts' => $predictiveAlerts,
			'categoryRules' => $categoryRules
		]);
	}
}
