<?php

namespace Grocy\Controllers;

use Grocy\Helpers\DairyAccess;
use Grocy\Services\BranchService;
use Grocy\Services\RetailPreventionService;
use Grocy\Services\DatabaseService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class RetailPreventionController extends BaseController
{
	private function Svc(): RetailPreventionService
	{
		return RetailPreventionService::GetInstance();
	}

	public function Dispatch(Request $request, Response $response, array $args): Response
	{
		$params = $request->getQueryParams();
		$branchId = isset($params['branch_id']) && $params['branch_id'] !== '' ? intval($params['branch_id']) : null;
		$selectedOrderId = isset($params['id']) && $params['id'] !== '' ? intval($params['id']) : null;
		$deliveryTypeFilter = $params['type'] ?? null;
		$statusFilter = $params['status'] ?? null;

		$branchService = BranchService::GetInstance();
		$branches = $branchService->GetBranches();

		// Fetch orders from database
		$db = DatabaseService::GetInstance()->GetDbConnectionRaw();
		$where = ["1=1"];
		if ($branchId !== null && $branchId > 0)
		{
			$where[] = "o.branch_id = " . intval($branchId);
		}
		if (!empty($deliveryTypeFilter))
		{
			$where[] = "o.delivery_type = " . $db->quote($deliveryTypeFilter);
		}
		if (!empty($statusFilter))
		{
			$where[] = "o.status = " . $db->quote($statusFilter);
		}

		$wSql = implode(" AND ", $where);
		$orders = $db->query("
			SELECT o.*, c.name AS customer_name, b.name AS branch_name,
			       (SELECT COUNT(*) FROM customer_order_items WHERE order_id = o.id) AS lines_count,
			       (SELECT IFNULL(SUM(amount * unit_price), 0) FROM customer_order_items WHERE order_id = o.id) AS total_amount,
			       (SELECT COUNT(*) FROM dispatch_variances WHERE order_id = o.id) AS variances_count,
			       (SELECT COUNT(*) FROM dispatch_variances WHERE order_id = o.id AND resolution_status = 'pending') AS pending_variances_count,
			       (SELECT COUNT(*) FROM customer_order_items WHERE order_id = o.id AND amount != picked_amount) AS unreconciled_items_count
			FROM customer_orders o
			JOIN customers c ON o.customer_id = c.id
			LEFT JOIN branches b ON o.branch_id = b.id
			WHERE $wSql
			ORDER BY CASE 
				WHEN o.order_code = 'PED-2026-00002' THEN 1 
				WHEN o.order_code = 'PED-2026-00001' THEN 2 
				WHEN o.order_code = 'PED-2026-00003' THEN 3 
				WHEN o.order_code = 'PED-2026-00004' THEN 4 
				WHEN o.order_code = 'PED-2026-00005' THEN 5 
				ELSE 6 
			END, o.id ASC
		")->fetchAll(\PDO::FETCH_ASSOC);

		// Thumbnails map for orders
		$orderThumbMap = [
			'PED-2026-00002' => '/img/control_center/thumb_coffee_croissant.jpg',
			'PED-2026-00001' => '/img/control_center/depto_fruver.jpg',
			'PED-2026-00003' => '/img/control_center/depto_lacteos.jpg',
			'PED-2026-00004' => '/img/control_center/daily_card_shelf.jpg',
			'PED-2026-00005' => '/img/control_center/depto_carniceria.jpg',
		];

		foreach ($orders as &$ord)
		{
			$ord['thumbnail'] = $orderThumbMap[$ord['order_code']] ?? '/img/control_center/daily_card_dairy.jpg';

			if (intval($ord['pending_variances_count']) > 0)
			{
				$ord['notice_type'] = 'pending_variance';
				$ord['notice_text'] = $ord['pending_variances_count'] . ' variación pendiente';
				$ord['notice_class'] = 'badge-danger';
				$ord['notice_icon'] = 'fa-triangle-exclamation';
			}
			elseif (intval($ord['variances_count']) > 0)
			{
				$ord['notice_type'] = 'resolved_variances';
				$ord['notice_text'] = 'Variaciones resueltas';
				$ord['notice_class'] = 'badge-success';
				$ord['notice_icon'] = 'fa-circle-check';
			}
			elseif (intval($ord['unreconciled_items_count']) > 0)
			{
				$ord['notice_type'] = 'review_reconciliation';
				$ord['notice_text'] = 'Revisar conciliación';
				$ord['notice_class'] = 'badge-warning';
				$ord['notice_icon'] = 'fa-triangle-exclamation';
			}
			else
			{
				$ord['notice_type'] = 'no_diff';
				$ord['notice_text'] = 'Sin diferencias';
				$ord['notice_class'] = 'badge-success';
				$ord['notice_icon'] = 'fa-circle-check';
			}
		}

		// Default selected order: prefer PED-2026-00002
		if ($selectedOrderId === null && !empty($orders))
		{
			$matchPed2 = array_filter($orders, fn($o) => $o['order_code'] === 'PED-2026-00002');
			if (!empty($matchPed2))
			{
				$selectedOrderId = intval(reset($matchPed2)['id']);
			}
			else
			{
				$selectedOrderId = intval($orders[0]['id']);
			}
		}

		// Preload all order details into map for instant front-end switching
		$ordersDetailMap = [];
		foreach ($orders as $ord)
		{
			try
			{
				$ordersDetailMap[$ord['id']] = $this->Svc()->GetDispatchOrderDetail(intval($ord['id']));
			}
			catch (\Throwable $e)
			{
			}
		}

		$selectedDetail = $ordersDetailMap[$selectedOrderId] ?? null;

		// Comparative Analytics (Pickup vs Delivery)
		$analytics = $this->Svc()->GetDispatchComparativeAnalytics($branchId);

		return $this->RenderPage($response, 'dispatch', [
			'orders' => $orders,
			'selectedOrderId' => $selectedOrderId,
			'selectedDetail' => $selectedDetail,
			'ordersDetailMap' => $ordersDetailMap,
			'ordersDetailMapJson' => json_encode($ordersDetailMap, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
			'analytics' => $analytics,
			'branches' => $branches,
			'selectedBranchId' => $branchId,
			'deliveryTypeFilter' => $deliveryTypeFilter,
			'statusFilter' => $statusFilter
		]);
	}

	public function Returns(Request $request, Response $response, array $args): Response
	{
		$params = $request->getQueryParams();
		$branchId = isset($params['branch_id']) && $params['branch_id'] !== '' ? intval($params['branch_id']) : null;
		$statusFilter = $params['status'] ?? null;
		$typeFilter = $params['type'] ?? null;

		$branchService = BranchService::GetInstance();
		$branches = $branchService->GetBranches();

		$data = $this->Svc()->GetReturns($branchId, $statusFilter, $typeFilter);
		$returns = $data['returns'];

		// Food & product photography thumbnail mappings
		$thumbMap = [
			'Galletas' => '/img/control_center/thumb_cookies.jpg',
			'Chocolate' => '/img/control_center/thumb_chocolate.jpg',
			'Ositos de goma' => '/img/control_center/thumb_gummy_bears.jpg',
			'Patatas fritas' => '/img/control_center/thumb_chips.jpg',
			'Huevos' => '/img/control_center/huevos_grandes.jpg',
		];

		// 8 real lifecycle stages
		$stageMeta = [
			'solicitud' => ['num' => 1, 'name' => 'Solicitud', 'action' => 'Identificar mercancía', 'badge' => 'ret-stage-amber'],
			'identificacion' => ['num' => 2, 'name' => 'Identificación', 'action' => 'Iniciar inspección', 'badge' => 'ret-stage-amber'],
			'inspeccion' => ['num' => 3, 'name' => 'Inspección', 'action' => 'Revisar inspección', 'badge' => 'ret-stage-amber'],
			'clasificacion' => ['num' => 4, 'name' => 'Clasificación', 'action' => 'Definir destino', 'badge' => 'ret-stage-amber'],
			'aprobada' => ['num' => 5, 'name' => 'Aprobada', 'action' => 'Confirmar disposición', 'badge' => 'ret-stage-green'],
			'disposicion' => ['num' => 6, 'name' => 'Disposición', 'action' => 'Ejecutar movimiento', 'badge' => 'ret-stage-teal'],
			'conciliada' => ['num' => 7, 'name' => 'Conciliada', 'action' => 'Cerrar caso', 'badge' => 'ret-stage-green'],
			'cerrada' => ['num' => 8, 'name' => 'Cerrada', 'action' => 'Ver expediente', 'badge' => 'ret-stage-green'],
		];

		// 7 registered destinations
		$destMeta = [
			'cuarentena' => [
				'title' => 'Cuarentena técnica',
				'subtitle' => 'Clasificación por revisar',
				'badge_class' => 'ret-badge-amber',
				'detail_notice' => 'Revisar clasificación y condiciones del producto.',
				'image' => '/img/control_center/dest_cuarentena.jpg',
				'short_desc' => 'Inspección adicional'
			],
			'reincorporacion' => [
				'title' => 'Reincorporación',
				'subtitle' => 'Apto para venta',
				'badge_class' => 'ret-badge-green',
				'detail_notice' => 'Producto apto verificado para reincorporación al inventario activo de sala.',
				'image' => '/img/control_center/dest_reincorporacion.jpg',
				'short_desc' => 'Apto para venta'
			],
			'devolucion_proveedor' => [
				'title' => 'Devolución proveedor (RTV)',
				'subtitle' => 'Por validar con proveedor',
				'badge_class' => 'ret-badge-cyan',
				'detail_notice' => 'Devolución al proveedor acordada. Nota de crédito o reposición comercial requerida.',
				'image' => '/img/control_center/dest_rtv.jpg',
				'short_desc' => 'RTV'
			],
			'merma_definitiva' => [
				'title' => 'Merma definitiva',
				'subtitle' => 'Registro completado',
				'badge_class' => 'ret-badge-red',
				'detail_notice' => 'Pérdida por daño o merma asumida con descargo contable directo en Kardex.',
				'image' => '/img/control_center/dest_merma.jpg',
				'short_desc' => 'Destrucción'
			],
			'donacion' => [
				'title' => 'Donación autorizada',
				'subtitle' => 'Destino registrado',
				'badge_class' => 'ret-badge-dark',
				'detail_notice' => 'Asignado a Banco de Alimentos bajo convenio institucional y acta de entrega.',
				'image' => '/img/control_center/dest_donacion.jpg',
				'short_desc' => 'A instituciones'
			],
			'destruccion' => [
				'title' => 'Destrucción controlada',
				'subtitle' => 'Registro completado',
				'badge_class' => 'ret-badge-dark',
				'detail_notice' => 'Acta de desnaturalización y destrucción con constancia ambiental certificada.',
				'image' => '/img/control_center/dest_destruccion.jpg',
				'short_desc' => 'Proceso autorizado'
			],
			'recuperacion' => [
				'title' => 'Recuperación / Reproceso',
				'subtitle' => 'Transformación autorizada',
				'badge_class' => 'ret-badge-purple',
				'detail_notice' => 'Transformación y aprovechamiento secundario en cocina interna o panadería.',
				'image' => '/img/control_center/dest_reincorporacion.jpg',
				'short_desc' => 'Reproceso'
			]
		];

		$enProcesoCount = 0;
		$cerradosCount = 0;

		foreach ($returns as &$r)
		{
			$pName = $r['product_name'] ?? '';
			$r['thumbnail'] = $thumbMap[$pName] ?? '/img/control_center/daily_scan_crate.jpg';
			
			$st = $r['status'] ?? 'solicitud';
			$r['stage_meta'] = $stageMeta[$st] ?? ['num' => 1, 'name' => $st, 'action' => 'Ver detalle', 'badge' => 'ret-stage-amber'];
			
			$cl = $r['classification'] ?? 'cuarentena';
			$r['dest_meta'] = $destMeta[$cl] ?? [
				'title' => $cl,
				'subtitle' => 'Destino registrado',
				'badge_class' => 'ret-badge-dark',
				'detail_notice' => 'Destino operativo asignado.',
				'image' => '/img/control_center/dest_cuarentena.jpg',
				'short_desc' => $cl
			];

			if (in_array($st, ['cerrada', 'conciliada']))
			{
				$cerradosCount++;
				$r['group_status'] = 'cerrados';
			}
			else
			{
				$enProcesoCount++;
				$r['group_status'] = 'en_proceso';
			}
		}

		// Preload products & orders for modals
		$db = DatabaseService::GetInstance()->GetDbConnectionRaw();
		$products = $db->query("SELECT id, name, sku, (SELECT price FROM cache__products_last_purchased WHERE product_id = products.id) AS last_cost FROM products WHERE active = 1 ORDER BY name")->fetchAll(\PDO::FETCH_ASSOC);
		$customers = $db->query("SELECT id, name FROM customers WHERE active = 1 ORDER BY name")->fetchAll(\PDO::FETCH_ASSOC);
		$orders = $db->query("SELECT id, order_code, customer_id FROM customer_orders ORDER BY id DESC LIMIT 50")->fetchAll(\PDO::FETCH_ASSOC);

		return $this->RenderPage($response, 'returns', [
			'returns' => $returns,
			'returnsJson' => json_encode($returns, JSON_UNESCAPED_UNICODE),
			'destMeta' => $destMeta,
			'financialSummary' => $data['financial_summary'],
			'totalCount' => $data['total_count'],
			'enProcesoCount' => $enProcesoCount,
			'cerradosCount' => $cerradosCount,
			'branches' => $branches,
			'selectedBranchId' => $branchId,
			'statusFilter' => $statusFilter,
			'typeFilter' => $typeFilter,
			'products' => $products,
			'customers' => $customers,
			'orders' => $orders
		]);
	}
}
