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
			       (SELECT IFNULL(SUM(amount), 0) FROM customer_order_items WHERE order_id = o.id) AS total_units,
			       (SELECT IFNULL(SUM(picked_amount), 0) FROM customer_order_items WHERE order_id = o.id) AS total_picked_units,
			       (SELECT IFNULL(SUM(amount * unit_price), 0) FROM customer_order_items WHERE order_id = o.id) AS total_amount,
			       (SELECT COUNT(*) FROM dispatch_variances WHERE order_id = o.id) AS variances_count,
			       (SELECT COUNT(*) FROM dispatch_variances WHERE order_id = o.id AND resolution_status = 'pending') AS pending_variances_count,
			       (SELECT COUNT(*) FROM dispatch_logistics_incidents WHERE order_id = o.id AND resolution_status = 'open') AS open_incidents_count,
			       (SELECT COUNT(*) FROM customer_order_items WHERE order_id = o.id AND amount != IFNULL(picked_amount, 0)) AS unreconciled_items_count
			FROM customer_orders o
			JOIN customers c ON o.customer_id = c.id
			LEFT JOIN branches b ON o.branch_id = b.id
			WHERE $wSql
			ORDER BY o.id DESC
		")->fetchAll(\PDO::FETCH_ASSOC);

		// Thumbnails map for orders
		$orderThumbMap = [
			'PED-2026-00002' => '/img/control_center/thumb_coffee_croissant.jpg',
			'PED-2026-00001' => '/img/control_center/depto_fruver.jpg',
			'PED-2026-00003' => '/img/control_center/depto_lacteos.jpg',
			'PED-2026-00004' => '/img/control_center/daily_card_shelf.jpg',
			'PED-2026-00005' => '/img/control_center/depto_carniceria.jpg',
		];

		// Transport vehicles catalog (selectable in Schedule Transport modal)
		$vehicles = [
			['code' => 'VH-01', 'name' => 'Camión C-08 ThermoKing', 'plate' => 'WXY-882', 'capacity_kg' => 3500, 'volume_m3' => 18.5, 'temp' => 'Refrigerado (-2°C a 4°C)', 'status' => 'active'],
			['code' => 'VH-02', 'name' => 'Furgón F-12 Frío Directo', 'plate' => 'TKL-409', 'capacity_kg' => 2000, 'volume_m3' => 12.0, 'temp' => 'Refrigerado (0°C a 5°C)', 'status' => 'active'],
			['code' => 'VH-03', 'name' => 'Van Urbana V-04', 'plate' => 'BCR-115', 'capacity_kg' => 1200, 'volume_m3' => 8.0, 'temp' => 'Seco / Isotérmico', 'status' => 'active'],
			['code' => 'VH-04', 'name' => 'Moto Express M-02', 'plate' => 'EXP-332', 'capacity_kg' => 80, 'volume_m3' => 0.4, 'temp' => 'Mochila Térmica', 'status' => 'active'],
		];

		// Users for picking & transport assignment
		$users = $db->query("SELECT id, username, first_name, last_name FROM users ORDER BY username ASC")->fetchAll(\PDO::FETCH_ASSOC);

		$todayStr = date('Y-m-d');
		$nowTs = time();
		$pendingCount = 0;      // draft + confirmed (pendientes / reservados)
		$preparingCount = 0;    // picking + picked (en preparación)
		$readyCount = 0;        // packed (listos para despacho)
		$dispatchedCount = 0;   // dispatched / delivered
		$overdueCount = 0;
		$incidentsCount = 0;
		$otifEvaluatedCount = 0;
		$otifCompliantCount = 0;
		$prepAccuracySum = 0.0;
		$prepAccuracyTotal = 0;
		$prepMinsList = [];
		$dispatchedTodayHours = [];

		$routesMap = [];

		foreach ($orders as &$ord)
		{
			$ord['thumbnail'] = $orderThumbMap[$ord['order_code']] ?? '/img/control_center/daily_card_dairy.jpg';
			$ord['channel'] = !empty($ord['channel']) ? $ord['channel'] : ($ord['delivery_type'] === 'pickup' ? 'Click & Collect' : 'Despacho Domicilio');
			$ord['route_name'] = trim((string)($ord['route_name'] ?? '')) ?: (($ord['delivery_type'] ?? 'pickup') === 'pickup' ? 'Mostrador Retiro' : 'Sin ruta asignada');
			$ord['stages'] = \Grocy\Services\RetailPreventionService::BuildOrderStages($ord);

			$st = $ord['status'] ?? 'draft';
			$delSt = $ord['delivery_status'] ?? 'pending';
			$reqDate = !empty($ord['requested_date']) ? substr((string)$ord['requested_date'], 0, 10) : substr((string)($ord['created_at'] ?? $todayStr), 0, 10);
			$isTerminalDelivered = in_array($delSt, ['delivered', 'partial', 'rejected'], true) || !empty($ord['conciliated_at']);
			$isOverdue = false;
			if ($st !== 'cancelled' && !$isTerminalDelivered)
			{
				if ($reqDate < $todayStr)
				{
					$isOverdue = true;
				}
				elseif (!empty($ord['scheduled_time_slot_end']) && strtotime((string)$ord['scheduled_time_slot_end']) < $nowTs)
				{
					$isOverdue = true;
				}
			}
			$ord['is_overdue'] = $isOverdue;

			if ($st === 'cancelled')
			{
				$ord['priority'] = 'Anulado';
				$ord['priority_class'] = 'badge-secondary';
			}
			elseif ($isOverdue)
			{
				$ord['priority'] = 'Urgente (Atrasado)';
				$ord['priority_class'] = 'badge-danger';
			}
			elseif ($reqDate === $todayStr)
			{
				$ord['priority'] = 'Hoy';
				$ord['priority_class'] = 'badge-warning';
			}
			else
			{
				$ord['priority'] = 'Normal';
				$ord['priority_class'] = 'badge-secondary';
			}

			// Canonical 7-stage status label
			$hasInc = !empty($ord['route_incidents']) || intval($ord['pending_variances_count']) > 0 || intval($ord['open_incidents_count']) > 0;
			if ($st === 'cancelled')
			{
				$ord['tracking_status'] = 'Cancelado';
				$ord['tracking_class'] = 'badge-secondary';
			}
			elseif (!empty($ord['conciliated_at']))
			{
				$ord['tracking_status'] = 'Conciliado';
				$ord['tracking_class'] = 'badge-success';
			}
			elseif ($delSt === 'partial')
			{
				$ord['tracking_status'] = 'Entrega Parcial';
				$ord['tracking_class'] = 'badge-warning';
			}
			elseif ($delSt === 'rejected')
			{
				$ord['tracking_status'] = 'Rechazado';
				$ord['tracking_class'] = 'badge-danger';
			}
			elseif ($delSt === 'delivered')
			{
				$ord['tracking_status'] = 'Entregado (POD)';
				$ord['tracking_class'] = 'badge-success';
			}
			elseif ($hasInc && $st === 'dispatched')
			{
				$ord['tracking_status'] = 'Incidencia en ruta';
				$ord['tracking_class'] = 'badge-danger';
			}
			elseif ($st === 'dispatched')
			{
				$ord['tracking_status'] = ($ord['delivery_type'] ?? 'pickup') === 'pickup' ? 'Listo p/ Retiro PIN' : 'En tránsito';
				$ord['tracking_class'] = 'badge-info';
			}
			elseif ($st === 'packed')
			{
				$ord['tracking_status'] = 'Packing / Listo Despacho';
				$ord['tracking_class'] = 'badge-primary';
			}
			elseif ($st === 'picked')
			{
				$ord['tracking_status'] = 'Picking Validado';
				$ord['tracking_class'] = 'badge-primary';
			}
			elseif ($st === 'picking')
			{
				$ord['tracking_status'] = 'En Picking';
				$ord['tracking_class'] = 'badge-warning';
			}
			elseif ($st === 'confirmed')
			{
				$ord['tracking_status'] = 'Reservado';
				$ord['tracking_class'] = 'badge-secondary';
			}
			else
			{
				$ord['tracking_status'] = 'Pedido Nuevo';
				$ord['tracking_class'] = 'badge-secondary';
			}

			// Real counts (excluding cancelled)
			if ($st !== 'cancelled')
			{
				if (in_array($st, ['draft', 'confirmed'], true))
				{
					$pendingCount++;
				}
				elseif (in_array($st, ['picking', 'picked'], true))
				{
					$preparingCount++;
				}
				elseif ($st === 'packed')
				{
					$readyCount++;
				}
				elseif ($st === 'dispatched' || $isTerminalDelivered)
				{
					$dispatchedCount++;
					$hKey = !empty($ord['dispatched_at']) ? date('Y-m-d H', strtotime((string)$ord['dispatched_at'])) : null;
					if ($hKey)
					{
						$dispatchedTodayHours[$hKey] = true;
					}
				}
				if ($isOverdue)
				{
					$overdueCount++;
				}
				if ($hasInc || intval($ord['variances_count']) > 0)
				{
					$incidentsCount++;
				}

				// Real OTIF: strictly evaluated on completed delivery/partial/rejected orders (On-Time AND In-Full)
				if (in_array($delSt, ['delivered', 'partial', 'rejected'], true))
				{
					$otifEvaluatedCount++;
					$isInFull = ($delSt === 'delivered') && floatval($ord['delivery_accuracy_pct'] ?? 100.0) >= 99.9 && floatval($ord['preparation_accuracy_pct'] ?? 100.0) >= 99.9 && intval($ord['pending_variances_count']) === 0;
					$deadline = !empty($ord['scheduled_time_slot_end']) ? (string)$ord['scheduled_time_slot_end'] : ($reqDate . ' 23:59:59');
					$delivTs = !empty($ord['delivery_time']) ? strtotime((string)$ord['delivery_time']) : false;
					$deadTs = strtotime($deadline);
					$isOnTime = ($delivTs && $deadTs) ? ($delivTs <= $deadTs) : true;
					if ($isInFull && $isOnTime)
					{
						$otifCompliantCount++;
					}
				}

				if (!in_array($st, ['draft', 'confirmed'], true) && $ord['preparation_accuracy_pct'] !== null)
				{
					$prepAccuracySum += floatval($ord['preparation_accuracy_pct']);
					$prepAccuracyTotal++;
				}

				$startRaw = $ord['confirmed_at'] ?? $ord['created_at'] ?? null;
				$endRaw = $ord['packed_at'] ?? $ord['picked_at'] ?? $ord['dispatched_at'] ?? null;
				if (!empty($startRaw) && !empty($endRaw))
				{
					$sTs = strtotime((string)$startRaw);
					$eTs = strtotime((string)$endRaw);
					if ($sTs && $eTs && $eTs >= $sTs && ($eTs - $sTs) <= 86400)
					{
						$prepMinsList[] = ($eTs - $sTs) / 60.0;
					}
				}

				// Aggregate real route metrics
				if (($ord['delivery_type'] ?? 'pickup') === 'delivery')
				{
					$rKey = $ord['route_name'];
					if (!isset($routesMap[$rKey]))
					{
						$routesMap[$rKey] = [
							'route_name' => $rKey,
							'orders_count' => 0,
							'delivered_count' => 0,
							'in_transit_count' => 0,
							'carrier_name' => $ord['carrier_name'] ?: 'Sin asignar',
							'carrier_vehicle' => $ord['carrier_vehicle'] ?: 'Sin unidad',
							'packages' => 0
						];
					}
					$routesMap[$rKey]['orders_count']++;
					$routesMap[$rKey]['packages'] += max(1, intval($ord['packages'] ?? 1));
					if ($ord['carrier_name'] && $routesMap[$rKey]['carrier_name'] === 'Sin asignar')
					{
						$routesMap[$rKey]['carrier_name'] = $ord['carrier_name'];
					}
					if ($ord['carrier_vehicle'] && $routesMap[$rKey]['carrier_vehicle'] === 'Sin unidad')
					{
						$routesMap[$rKey]['carrier_vehicle'] = $ord['carrier_vehicle'];
					}
					if ($delSt === 'delivered')
					{
						$routesMap[$rKey]['delivered_count']++;
					}
					elseif ($st === 'dispatched')
					{
						$routesMap[$rKey]['in_transit_count']++;
					}
				}
			}

			if (intval($ord['pending_variances_count']) > 0 || intval($ord['open_incidents_count']) > 0)
			{
				$ord['notice_type'] = 'pending_variance';
				$cntAlert = intval($ord['pending_variances_count']) + intval($ord['open_incidents_count']);
				$ord['notice_text'] = $cntAlert . ' alerta pendiente';
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
			elseif (!in_array($st, ['draft', 'confirmed', 'cancelled'], true) && intval($ord['unreconciled_items_count']) > 0)
			{
				$ord['notice_type'] = 'review_reconciliation';
				$ord['notice_text'] = 'Diferencia preparación';
				$ord['notice_class'] = 'badge-warning';
				$ord['notice_icon'] = 'fa-triangle-exclamation';
			}
			else
			{
				$ord['notice_type'] = 'no_diff';
				$ord['notice_text'] = !empty($ord['conciliated_at']) ? 'Conciliado OK' : 'En orden';
				$ord['notice_class'] = 'badge-success';
				$ord['notice_icon'] = 'fa-circle-check';
			}
		}
		unset($ord);

		$otifRate = $otifEvaluatedCount > 0 ? round(($otifCompliantCount / $otifEvaluatedCount) * 100.0, 1) : null;
		$pickingAccuracy = $prepAccuracyTotal > 0 ? round($prepAccuracySum / $prepAccuracyTotal, 1) : null;
		$avgPrepTime = count($prepMinsList) > 0 ? (round(array_sum($prepMinsList) / count($prepMinsList), 1) . ' min') : 'Sin información';
		$activeHours = count($dispatchedTodayHours);
		$productivityPerHour = ($dispatchedCount > 0 && $activeHours > 0) ? (round($dispatchedCount / $activeHours, 1) . ' ped/h') : 'Sin información';

		$kpis = [
			'pending_orders' => $pendingCount,
			'preparing_orders' => $preparingCount,
			'ready_orders' => $readyCount,
			'dispatched_orders' => $dispatchedCount,
			'overdue_orders' => $overdueCount,
			'otif_pct' => $otifRate,
			'otif_evaluated' => $otifEvaluatedCount,
			'picking_accuracy_pct' => $pickingAccuracy,
			'prep_time_minutes' => $avgPrepTime,
			'incidents_count' => $incidentsCount,
			'productivity' => $productivityPerHour
		];

		// Default selected order: prefer active operational order (or first in list)
		if ($selectedOrderId === null && !empty($orders))
		{
			$activeFirst = array_filter($orders, fn($o) => !in_array($o['status'], ['cancelled'], true));
			$firstOrd = !empty($activeFirst) ? reset($activeFirst) : $orders[0];
			$selectedOrderId = intval($firstOrd['id']);
		}

		// Preload order details into map for instant switching
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

		// Real comparative analytics (Pickup vs Delivery)
		$analytics = $this->Svc()->GetDispatchComparativeAnalytics($branchId);

		// Real logistics incidents log
		$incWhere = $branchId ? ("WHERE i.branch_id = " . intval($branchId)) : "";
		$openIncidents = $db->query("
			SELECT i.*, o.order_code, c.name AS customer_name, b.name AS branch_name
			FROM dispatch_logistics_incidents i
			JOIN customer_orders o ON o.id = i.order_id
			LEFT JOIN customers c ON c.id = o.customer_id
			LEFT JOIN branches b ON b.id = i.branch_id
			$incWhere
			ORDER BY CASE WHEN i.resolution_status = 'open' THEN 0 ELSE 1 END, i.id DESC
			LIMIT 100
		")->fetchAll(\PDO::FETCH_ASSOC);

		// Customers & Sellable Products for "+ Nuevo Pedido" modal
		$customers = $db->query("SELECT id, name FROM customers WHERE IFNULL(active, 1) = 1 ORDER BY name ASC")->fetchAll(\PDO::FETCH_ASSOC);
		$sellableProducts = $db->query("
			SELECT p.id, p.name, p.sku,
			       COALESCE(
			           (SELECT price FROM cache__products_last_purchased WHERE product_id = p.id),
			           0
			       ) AS ref_price,
			       IFNULL((SELECT SUM(amount) FROM stock WHERE product_id = p.id AND amount > 0), 0) AS stock_avail
			FROM products p
			WHERE IFNULL(p.active, 1) = 1
			ORDER BY p.name ASC
			LIMIT 200
		")->fetchAll(\PDO::FETCH_ASSOC);

		return $this->RenderPage($response, 'dispatch', [
			'orders' => $orders,
			'selectedOrderId' => $selectedOrderId,
			'selectedDetail' => $selectedDetail,
			'ordersDetailMap' => $ordersDetailMap,
			'ordersDetailMapJson' => json_encode($ordersDetailMap, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
			'analytics' => $analytics,
			'routesSummary' => array_values($routesMap),
			'openIncidents' => $openIncidents,
			'customers' => $customers,
			'sellableProducts' => $sellableProducts,
			'branches' => $branches,
			'selectedBranchId' => $branchId,
			'deliveryTypeFilter' => $deliveryTypeFilter,
			'statusFilter' => $statusFilter,
			'kpis' => $kpis,
			'vehicles' => $vehicles,
			'users' => $users
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

		$data = $this->Svc()->GetReturns($branchId, $statusFilter, $typeFilter, null);
		$returns = $data['returns'];

		$thumbMap = [
			'Galletas' => '/img/control_center/thumb_cookies.jpg',
			'Chocolate' => '/img/control_center/thumb_chocolate.jpg',
			'Ositos de goma' => '/img/control_center/thumb_gummy_bears.jpg',
			'Patatas fritas' => '/img/control_center/thumb_chips.jpg',
			'Huevos' => '/img/control_center/huevos_grandes.jpg',
		];

		// 6 canonical reverse-logistics lifecycle stages
		$stageMeta = [
			'solicitud' => ['num' => 1, 'name' => 'Solicitud', 'action' => 'Registrar recepción física', 'badge' => 'ret-stage-amber'],
			'recepcion' => ['num' => 2, 'name' => 'Recepción', 'action' => 'Inspeccionar estado físico', 'badge' => 'ret-stage-amber'],
			'inspeccion' => ['num' => 3, 'name' => 'Inspección', 'action' => 'Clasificar unidades', 'badge' => 'ret-stage-amber'],
			'clasificacion' => ['num' => 4, 'name' => 'Clasificación', 'action' => 'Autorizar disposición', 'badge' => 'ret-stage-teal'],
			'disposicion' => ['num' => 5, 'name' => 'Disposición', 'action' => 'Conciliar unidades', 'badge' => 'ret-stage-green'],
			'conciliacion' => ['num' => 6, 'name' => 'Conciliación', 'action' => 'Ver historial y auditoría', 'badge' => 'ret-stage-green'],
			'rechazada' => ['num' => 1, 'name' => 'Rechazada', 'action' => 'Ver motivo de rechazo', 'badge' => 'ret-stage-amber'],
		];

		$destMeta = [
			'cuarentena' => [
				'title' => 'Cuarentena técnica',
				'subtitle' => 'Pendiente de destino final',
				'badge_class' => 'ret-badge-amber',
				'detail_notice' => 'Aislado preventivamente hasta completar inspección y asignar destino definitivo.',
				'image' => '/img/control_center/dest_cuarentena.jpg',
				'short_desc' => 'En espera'
			],
			'pendiente_inspeccion' => [
				'title' => 'Pendiente inspección física',
				'subtitle' => 'Recepción desde mostrador POS',
				'badge_class' => 'ret-badge-amber',
				'detail_notice' => 'Mercancía física pendiente de verificación de estado y clasificación en bodega.',
				'image' => '/img/control_center/dest_cuarentena.jpg',
				'short_desc' => 'Sin inspeccionar'
			],
			'reincorporacion' => [
				'title' => 'Apto para reventa',
				'subtitle' => 'Reingreso a stock vendible',
				'badge_class' => 'ret-badge-green',
				'detail_notice' => 'Producto íntegro verificado; requiere autorización de supervisor para reingresar al inventario.',
				'image' => '/img/control_center/dest_reincorporacion.jpg',
				'short_desc' => 'Reventa'
			],
			'devolucion_proveedor' => [
				'title' => 'Devolución a proveedor (RTV)',
				'subtitle' => 'Retorno logístico / crédito',
				'badge_class' => 'ret-badge-cyan',
				'detail_notice' => 'Devolución física al proveedor por defecto de origen o rechazo en recepción.',
				'image' => '/img/control_center/dest_rtv.jpg',
				'short_desc' => 'RTV'
			],
			'reparacion' => [
				'title' => 'Reparación / Reacond.',
				'subtitle' => 'Taller o reempaque técnico',
				'badge_class' => 'ret-badge-purple',
				'detail_notice' => 'Envío a taller o estación de reacondicionamiento controlado antes de nueva revisión.',
				'image' => '/img/control_center/dest_reincorporacion.jpg',
				'short_desc' => 'Reparación'
			],
			'recuperacion' => [
				'title' => 'Recuperación / Reproceso',
				'subtitle' => 'Transformación autorizada',
				'badge_class' => 'ret-badge-purple',
				'detail_notice' => 'Transformación y aprovechamiento secundario autorizado en área interna.',
				'image' => '/img/control_center/dest_reincorporacion.jpg',
				'short_desc' => 'Reproceso'
			],
			'merma_definitiva' => [
				'title' => 'Producto dañado / Merma',
				'subtitle' => 'No apto para reventa',
				'badge_class' => 'ret-badge-red',
				'detail_notice' => 'Mercancía dañada o averiada bloqueada para reventa con baja física autorizada.',
				'image' => '/img/control_center/dest_merma.jpg',
				'short_desc' => 'Dañado'
			],
			'donacion' => [
				'title' => 'Donación autorizada',
				'subtitle' => 'Destino institucional',
				'badge_class' => 'ret-badge-dark',
				'detail_notice' => 'Asignado a Banco de Alimentos bajo acta física de entrega.',
				'image' => '/img/control_center/dest_donacion.jpg',
				'short_desc' => 'Donación'
			],
			'destruccion' => [
				'title' => 'Destrucción controlada',
				'subtitle' => 'Disposición certificada',
				'badge_class' => 'ret-badge-dark',
				'detail_notice' => 'Acta de desnaturalización física y disposición ambiental autorizada.',
				'image' => '/img/control_center/dest_destruccion.jpg',
				'short_desc' => 'Destrucción'
			],
		];

		$enProcesoCount = 0;
		$conciliadosCount = 0;
		$posHandoffCount = 0;
		$logisticsCount = 0;

		foreach ($returns as &$r)
		{
			$pName = $r['product_name'] ?? '';
			$r['thumbnail'] = $thumbMap[$pName] ?? '/img/control_center/daily_scan_crate.jpg';

			$st = $r['status'] ?? 'solicitud';
			$r['stage_meta'] = $stageMeta[$st] ?? ['num' => 1, 'name' => ucfirst($st), 'action' => 'Ver detalle', 'badge' => 'ret-stage-amber'];

			$cl = $r['classification'] ?: 'cuarentena';
			$r['dest_meta'] = $destMeta[$cl] ?? [
				'title' => $cl,
				'subtitle' => 'Destino asignado',
				'badge_class' => 'ret-badge-dark',
				'detail_notice' => 'Destino operativo asignado.',
				'image' => '/img/control_center/dest_cuarentena.jpg',
				'short_desc' => $cl
			];

			$isPosHandoff = intval($r['is_pos_return'] ?? 0) === 1;
			if ($isPosHandoff)
			{
				$posHandoffCount++;
			}
			else
			{
				$logisticsCount++;
			}

			if (in_array($st, ['conciliacion', 'rechazada'], true))
			{
				$conciliadosCount++;
				$r['group_status'] = 'conciliados';
			}
			else
			{
				$enProcesoCount++;
				$r['group_status'] = 'en_proceso';
			}
		}
		unset($r);

		$db = DatabaseService::GetInstance()->GetDbConnectionRaw();
		$products = $db->query("
			SELECT p.id, p.name, p.sku, p.location_id,
			       (SELECT barcode FROM product_barcodes pb WHERE pb.product_id = p.id ORDER BY pb.id LIMIT 1) AS barcode,
			       (SELECT price FROM cache__products_last_purchased WHERE product_id = p.id) AS last_cost
			FROM products p WHERE p.active = 1 ORDER BY p.name
		")->fetchAll(\PDO::FETCH_ASSOC);
		$orders = $db->query("SELECT id, order_code, customer_id FROM customer_orders ORDER BY id DESC LIMIT 50")->fetchAll(\PDO::FETCH_ASSOC);
		$suppliers = $db->query("SELECT id, name FROM shopping_locations WHERE active = 1 ORDER BY name")->fetchAll(\PDO::FETCH_ASSOC);
		$locations = $db->query("SELECT id, name, branch_id, is_freezer FROM locations WHERE active = 1 ORDER BY name")->fetchAll(\PDO::FETCH_ASSOC);

		return $this->RenderPage($response, 'returns', [
			'returns' => $returns,
			'returnsJson' => json_encode($returns, JSON_UNESCAPED_UNICODE),
			'destMeta' => $destMeta,
			'logisticsSummary' => $data['logistics_summary'],
			'financialSummary' => $data['financial_summary'],
			'totalCount' => $data['total_count'],
			'logisticsCount' => $logisticsCount,
			'posHandoffCount' => $posHandoffCount,
			'enProcesoCount' => $enProcesoCount,
			'cerradosCount' => $conciliadosCount,
			'branches' => $branches,
			'selectedBranchId' => $branchId,
			'statusFilter' => $statusFilter,
			'typeFilter' => $typeFilter,
			'products' => $products,
			'orders' => $orders,
			'suppliers' => $suppliers,
			'locations' => $locations,
		]);
	}
}
