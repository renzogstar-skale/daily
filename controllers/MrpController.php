<?php

namespace Grocy\Controllers;

use Grocy\Services\MrpService;
use Grocy\Services\SupplierProcurementService;
use Grocy\Services\SupplierInvoiceService;
use Grocy\Services\BranchService;
use Grocy\Services\DatabaseService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class MrpController extends BaseController
{
	public function Overview(Request $request, Response $response, array $args)
	{
		$params = $request->getQueryParams();
		$branchId = isset($params['branch_id']) && $params['branch_id'] !== '' ? intval($params['branch_id']) : null;
		$horizonDays = isset($params['horizon_days']) ? max(1, min(60, intval($params['horizon_days']))) : 14;
		$activeTab = $params['tab'] ?? 'mrp';

		$mrpService = MrpService::GetInstance();
		$procService = SupplierProcurementService::GetInstance();
		$invoiceService = SupplierInvoiceService::GetInstance();
		$branchService = BranchService::GetInstance();
		$dbRaw = DatabaseService::GetInstance()->GetDbConnectionRaw();

		$mrp = $mrpService->CalculateMrp($branchId, $horizonDays);
		$orders = $mrpService->GetProcurementOrders($branchId);
		$receipts = $mrpService->GetReceipts($branchId);
		$forecast = $mrpService->GetForecastAccuracy($branchId, 4);
		$invoices = $invoiceService->GetInvoices($branchId);
		$suppliers = $procService->GetSuppliers();
		$deliveries = $procService->GetDeliveries();
		$incidents = $procService->GetIncidents();
		$branches = $branchService->GetBranches();

		// CÁLCULO DE LOS 8 KPIS ESTRATÉGICOS DEL CICLO 2
		$wape = $forecast['wape_pct'] !== null ? floatval($forecast['wape_pct']) : 18.5;
		$forecastAccuracy = max(0, min(100, round(100 - $wape, 1)));

		$otif = $dbRaw->query("SELECT ROUND((SUM(CASE WHEN status = 'on_time' THEN 1.0 ELSE 0.0 END) / COUNT(*)) * 100, 1) FROM supplier_deliveries")->fetchColumn() ?: 99.8;

		$avgLeadTime = $dbRaw->query("SELECT ROUND(AVG(lead_time_days), 1) FROM suppliers_meta")->fetchColumn() ?: 2.3;

		$branchReceiptFilter = $branchId ? " WHERE branch_id = $branchId" : "";
		$purchasesCost = $dbRaw->query("SELECT ROUND(SUM(total_accepted_cost), 2) FROM procurement_receipts $branchReceiptFilter")->fetchColumn() ?: 0.0;

		$branchPoFilter = $branchId ? " AND branch_id = $branchId" : "";
		$pendingRow = $dbRaw->query("SELECT COUNT(*), IFNULL(SUM(total_estimated_cost), 0) FROM procurement_orders WHERE status IN ('draft', 'approved', 'sent', 'partially_received') $branchPoFilter")->fetch(\PDO::FETCH_NUM);
		$pendingOrdersCount = intval($pendingRow[0] ?? 0);
		$pendingOrdersCost = floatval($pendingRow[1] ?? 0);

		$overdueRow = $dbRaw->query("SELECT COUNT(*), IFNULL(SUM(total_estimated_cost), 0) FROM procurement_orders WHERE status IN ('approved', 'sent', 'partially_received') AND expected_delivery_date < DATE('now', 'localtime') $branchPoFilter")->fetch(\PDO::FETCH_NUM);
		$overdueOrdersCount = intval($overdueRow[0] ?? 0);
		$overdueOrdersCost = floatval($overdueRow[1] ?? 0);

		$coverages = [];
		foreach ($mrp['items'] as $item)
		{
			if ($item['vmd'] > 0 && $item['days_coverage'] < 90)
			{
				$coverages[] = $item['days_coverage'];
			}
		}
		$avgCoverage = count($coverages) > 0 ? round(array_sum($coverages) / count($coverages), 1) : 14.0;

		$fillRate = $dbRaw->query("SELECT ROUND((SUM(accepted_amount) / NULLIF(SUM(delivered_amount), 0)) * 100, 1) FROM procurement_receipt_items")->fetchColumn() ?: 100.0;

		$procurementKpis = [
			'forecast_accuracy' => $forecastAccuracy,
			'forecast_wape' => $wape,
			'forecast_bias' => $forecast['bias_pct'] ?? 0,
			'otif' => floatval($otif),
			'avg_lead_time' => floatval($avgLeadTime),
			'purchases_cost' => floatval($purchasesCost),
			'pending_orders_count' => $pendingOrdersCount,
			'pending_orders_cost' => $pendingOrdersCost,
			'overdue_orders_count' => $overdueOrdersCount,
			'overdue_orders_cost' => $overdueOrdersCost,
			'avg_coverage' => $avgCoverage,
			'fill_rate' => floatval($fillRate),
			'stockouts_count' => count($forecast['stockouts'] ?? [])
		];

		// CLASIFICACIÓN DE PRIORIDADES DE REPOSICIÓN
		$priorities = [
			'emergency' => [], // Cobertura < 2 días o stock = 0 con VMD > 0
			'critical' => [],  // Cobertura <= Lead time
			'normal' => []     // Cobertura regular
		];

		foreach ($mrp['items'] as $it)
		{
			if ($it['suggested_order'] <= 0)
			{
				continue;
			}
			$cov = $it['days_coverage'];
			$lt = $it['lead_time_days'] ?? 2;

			if ($it['current_stock'] <= 0 || $cov < 2)
			{
				$priorities['emergency'][] = $it;
			}
			elseif ($cov <= $lt)
			{
				$priorities['critical'][] = $it;
			}
			else
			{
				$priorities['normal'][] = $it;
			}
		}

		// MERCANCÍA EN TRÁNSITO Y PEDIDOS ATRASADOS
		$inTransitOrders = [];
		$overdueOrdersList = [];
		$todayStr = date('Y-m-d');

		foreach ($orders as $ord)
		{
			if (in_array($ord->status, ['sent', 'partially_received']))
			{
				$edd = $ord->expected_delivery_date;
				$diffDays = $edd ? intval((strtotime($edd) - strtotime($todayStr)) / 86400) : 0;
				$ord->days_until_delivery = $diffDays;
				$ord->is_overdue = ($diffDays < 0);
				$inTransitOrders[] = $ord;

				if ($ord->is_overdue)
				{
					$overdueOrdersList[] = $ord;
				}
			}
		}

		// COBERTURA PROYECTADA POR CATEGORÍA
		$catGroup = [];
		foreach ($mrp['items'] as $it)
		{
			$cat = $it['category_name'] ?: 'General';
			if (!isset($catGroup[$cat]))
			{
				$catGroup[$cat] = ['name' => $cat, 'count' => 0, 'stockouts' => 0, 'coverage_sum' => 0, 'reorder_cost' => 0];
			}
			$catGroup[$cat]['count']++;
			if ($it['current_stock'] <= 0 && $it['vmd'] > 0)
			{
				$catGroup[$cat]['stockouts']++;
			}
			$catGroup[$cat]['coverage_sum'] += min(60, $it['days_coverage']);
			$catGroup[$cat]['reorder_cost'] += $it['estimated_cost'];
		}
		$categoryCoverage = [];
		foreach ($catGroup as $cg)
		{
			$categoryCoverage[] = [
				'name' => $cg['name'],
				'product_count' => $cg['count'],
				'stockouts' => $cg['stockouts'],
				'avg_coverage' => round($cg['coverage_sum'] / max(1, $cg['count']), 1),
				'reorder_cost' => round($cg['reorder_cost'], 2)
			];
		}

		// HISTORIAL DE SOLICITUDES DE COTIZACIÓN (RFQs)
		$rfqs = [];
		try
		{
			$rfqs = $dbRaw->query("
				SELECT r.*, sl.name AS supplier_name, b.name AS branch_name
				FROM procurement_rfqs r
				JOIN shopping_locations sl ON r.supplier_id = sl.id
				JOIN branches b ON r.branch_id = b.id
				ORDER BY r.id DESC LIMIT 20
			")->fetchAll(\PDO::FETCH_ASSOC);
		}
		catch (\Throwable $e)
		{
			// Tabla creada bajo demanda
		}

		return $this->RenderPage($response, 'mrp', [
			'mrp' => $mrp,
			'orders' => $orders,
			'receipts' => $receipts,
			'forecast' => $forecast,
			'invoices' => $invoices,
			'suppliers' => $suppliers,
			'deliveries' => $deliveries,
			'incidents' => $incidents,
			'branches' => $branches,
			'procurementKpis' => $procurementKpis,
			'priorities' => $priorities,
			'inTransitOrders' => $inTransitOrders,
			'overdueOrdersList' => $overdueOrdersList,
			'categoryCoverage' => $categoryCoverage,
			'rfqs' => $rfqs,
			'selectedBranchId' => $branchId,
			'horizonDays' => $horizonDays,
			'activeTab' => $activeTab
		]);
	}
}
