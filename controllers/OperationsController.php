<?php

namespace Grocy\Controllers;

use Grocy\Controllers\Users\User;
use Grocy\Helpers\DairyAccess;
use Grocy\Services\DatabaseService;
use Grocy\Services\ImportService;
use Grocy\Services\ScanService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/** Etapa 21: páginas de operaciones con escáner, importación de maestros y etiquetas de ubicación. */
class OperationsController extends BaseController
{
	public function Scan(Request $request, Response $response, array $args)
	{
		$q = $request->getQueryParams();
		return $this->RenderPage($response, 'scan', [
			'form' => ScanService::GetInstance()->FormData(null),
			'sessions' => ScanService::GetInstance()->ListSessions(null, GROCY_USER_ID, 15),
			'openId' => !empty($q['session']) ? intval($q['session']) : null,
			'can' => [
				'count' => DairyAccess::HasAny([User::PERMISSION_STOCK_INVENTORY]),
				'move' => DairyAccess::HasAny([User::PERMISSION_STOCK_TRANSFER]),
				'receive' => DairyAccess::HasAny([User::PERMISSION_PROCUREMENT_RECEIVE]),
				'waste' => DairyAccess::HasAny([User::PERMISSION_WASTE_REPORT]),
				'pick' => DairyAccess::HasAny([User::PERMISSION_ORDERS_PICK]),
			],
			'pickId' => !empty($q['pick']) ? intval($q['pick']) : null,
			'initialCode' => isset($q['code']) ? mb_substr((string) $q['code'], 0, 120) : null,
			'blind' => !defined('GROCY_CYCLE_COUNT_BLIND') || GROCY_CYCLE_COUNT_BLIND,
		]);
	}

	public function DataImport(Request $request, Response $response, array $args)
	{
		return $this->RenderPage($response, 'data_import', [
			'entities' => ImportService::ENTITIES,
			'jobs' => ImportService::GetInstance()->Jobs(),
		]);
	}

	public function Orders(Request $request, Response $response, array $args)
	{
		$q = $request->getQueryParams();
		$db = DatabaseService::GetInstance()->GetDbConnectionRaw();

		// Asegurar marcas de tiempo coherentes en pedidos demo ya empacados/despachados y nombres genéricos de transporte
		$db->exec("UPDATE customer_orders SET picked_at = '2026-10-24 10:15:00', packed_at = '2026-10-24 11:00:00' WHERE order_code = 'PED-2026-00002' AND picked_at IS NULL");
		$db->exec("UPDATE customer_orders SET picked_at = '2026-10-24 09:40:00', packed_at = '2026-10-24 10:10:00', dispatched_at = '2026-10-25 08:30:00' WHERE order_code = 'PED-2026-00001' AND dispatched_at IS NULL");
		$db->exec("UPDATE customer_orders SET carrier_name = 'Flota Frigorífica Andina · Unidad C-08' WHERE carrier_name LIKE '%Hernán%'");

		$branches = DairyAccess::FilterBranches($db->query('SELECT id, name FROM branches WHERE active = 1 ORDER BY id')->fetchAll(\PDO::FETCH_ASSOC));
		$branchId = !empty($q['branch_id']) ? intval($q['branch_id']) : null;
		$status = isset($q['status']) && $q['status'] !== '' ? (string)$q['status'] : 'open';
		$svc = \Grocy\Services\CustomerOrderService::GetInstance();

		$orders = $svc->List($branchId, $status);
		$allOrders = $svc->List($branchId, 'all', 200);

		$orderDetailsMap = \Grocy\Services\ScanService::GetInstance()->WithAvailabilityMemo(function () use ($allOrders, $svc) {
			$details = [];
			foreach ($allOrders as $ordRow)
			{
				$oid = intval($ordRow['id']);
				try
				{
					$details[$oid] = $svc->Get($oid);
				}
				catch (\Exception $e)
				{
					// Ignorar si no es accesible
				}
			}
			return $details;
		});

		$selected = null;
		if (!empty($q['id']) && isset($orderDetailsMap[intval($q['id'])]))
		{
			$selected = $orderDetailsMap[intval($q['id'])];
		}
		elseif (!empty($orders))
		{
			$firstId = intval($orders[0]['id']);
			$selected = $orderDetailsMap[$firstId] ?? $svc->Get($firstId);
		}
		elseif (!empty($allOrders))
		{
			$firstId = intval($allOrders[0]['id']);
			$selected = $orderDetailsMap[$firstId] ?? null;
		}

		// Productos con precio de lista y disponibilidad libre por sucursal para consultar antes de confirmar un nuevo pedido
		$rawProducts = $db->query("
			SELECT p.id, p.name, p.sku, IFNULL(qu.name, 'pza') AS unit_name
			FROM products p
			LEFT JOIN quantity_units qu ON qu.id = p.qu_id_stock
			WHERE p.active = 1
			ORDER BY p.name
		")->fetchAll(\PDO::FETCH_ASSOC);

		$products = [];
		foreach ($rawProducts as $rp)
		{
			$pid = intval($rp['id']);
			$listPrice = $svc->ListPrice($pid);
			if ($listPrice <= 0)
			{
				continue;
			}
			$freeByBranch = [];
			foreach ($branches as $b)
			{
				$bid = intval($b['id']);
				$freeByBranch[$bid] = max(0.0, round($svc->Free($pid, $bid), 2));
			}
			$rp['list_price'] = $listPrice;
			$rp['free_by_branch'] = $freeByBranch;
			$products[] = $rp;
		}

		return $this->RenderPage($response, 'orders', [
			'orders' => $orders,
			'allOrders' => $allOrders,
			'orderDetailsMap' => $orderDetailsMap,
			'selected' => $selected,
			'branches' => $branches,
			'branchId' => $branchId,
			'status' => $status,
			'customers' => $db->query('SELECT id, name, tax_id FROM customers WHERE active = 1 ORDER BY name')->fetchAll(\PDO::FETCH_ASSOC),
			'products' => $products,
			'can' => [
				'manage' => DairyAccess::HasAny([User::PERMISSION_ORDERS_MANAGE]),
				'pick' => DairyAccess::HasAny([User::PERMISSION_ORDERS_PICK]),
				'dispatch' => DairyAccess::HasAny([User::PERMISSION_ORDERS_MANAGE]) && DairyAccess::HasAny([User::PERMISSION_SALES_POS])
			],
		]);
	}

	/**
	 * Mapa operativo: sucursal › almacén › zona › pasillo con ocupación (unidades / capacidad), vencidos, por vencer y valor.
	 * Si las ubicaciones no tienen jerarquía cargada se agrupa por sucursal y se indica qué falta.
	 */
	public function LocationMap(Request $request, Response $response, array $args)
	{
		$q = $request->getQueryParams();
		$db = DatabaseService::GetInstance()->GetDbConnectionRaw();
		$soon = date('Y-m-d', strtotime('+3 days'));
		$today = date('Y-m-d');
		$rows = $db->query("SELECT l.id, l.name, l.label_code, l.branch_id, b.name AS branch_name, l.warehouse, l.zone, l.aisle, l.shelf, l.bin, l.storage_class, l.capacity_units,
				IFNULL(SUM(s.amount), 0) AS units, IFNULL(SUM(s.amount * IFNULL(s.price, 0)), 0) AS value, COUNT(DISTINCT s.product_id) AS products,
				IFNULL(SUM(CASE WHEN s.best_before_date < '$today' AND s.best_before_date <> '2999-12-31' THEN s.amount END), 0) AS expired,
				IFNULL(SUM(CASE WHEN s.best_before_date >= '$today' AND s.best_before_date <= '$soon' THEN s.amount END), 0) AS expiring,
				(SELECT COUNT(*) FROM stock s2 JOIN products p2 ON p2.id = s2.product_id WHERE s2.location_id = l.id AND IFNULL(p2.storage_class, '') <> '' AND IFNULL(l.storage_class, '') <> '' AND p2.storage_class <> l.storage_class) AS incompatible
			FROM locations l LEFT JOIN branches b ON b.id = l.branch_id LEFT JOIN stock s ON s.location_id = l.id
			WHERE l.active = 1 AND IFNULL(l.is_transit, 0) = 0 AND l.branch_id IS NOT NULL
			GROUP BY l.id ORDER BY l.branch_id, l.warehouse, l.zone, l.aisle, l.shelf, l.bin, l.name")->fetchAll(\PDO::FETCH_ASSOC);
		$rows = array_values(array_filter($rows, fn($r) => DairyAccess::CanSeeBranch(intval($r['branch_id']))));
		$branchId = !empty($q['branch_id']) ? intval($q['branch_id']) : null;
		if ($branchId)
		{
			$rows = array_values(array_filter($rows, fn($r) => intval($r['branch_id']) === $branchId));
		}
		$tree = [];
		foreach ($rows as $r)
		{
			$r['fill'] = floatval($r['capacity_units']) > 0 ? round(floatval($r['units']) / floatval($r['capacity_units']) * 100) : null;
			$tree[$r['branch_name']][$r['warehouse'] ?: 'Sin almacén'][$r['zone'] ?: 'Sin zona'][$r['aisle'] ? 'Pasillo ' . $r['aisle'] : 'Sin pasillo'][] = $r;
		}
		$stockEntries = $db->query("SELECT s.stock_id, s.location_id, s.product_id, p.name AS product_name, p.sku, qu.name AS unit_name,
				s.amount, s.price, s.best_before_date, dl.lot_number, p.storage_class AS product_storage_class
			FROM stock s
			JOIN products p ON p.id = s.product_id
			LEFT JOIN quantity_units qu ON qu.id = p.qu_id_stock
			LEFT JOIN dairy_stock_lots dl ON dl.stock_id = s.stock_id
			JOIN locations l ON l.id = s.location_id
			WHERE l.active = 1 AND IFNULL(l.is_transit, 0) = 0
			ORDER BY s.location_id, s.best_before_date")->fetchAll(\PDO::FETCH_ASSOC);

		$stockByLoc = [];
		$unitsByLoc = [];
		foreach ($stockEntries as $se)
		{
			$locId = intval($se['location_id']);
			$stockByLoc[$locId][] = $se;
			$unit = $se['unit_name'] ?: 'Pieza';
			$unitsByLoc[$locId][$unit] = ($unitsByLoc[$locId][$unit] ?? 0) + floatval($se['amount']);
		}

		$selectedLocId = !empty($q['location_id']) ? intval($q['location_id']) : 2;

		return $this->RenderPage($response, 'location_map', [
			'tree' => $tree,
			'rows' => $rows,
			'branches' => DairyAccess::FilterBranches($db->query('SELECT id, name FROM branches WHERE active = 1 ORDER BY id')->fetchAll(\PDO::FETCH_ASSOC)),
			'branchId' => $branchId,
			'withHierarchy' => count(array_filter($rows, fn($r) => $r['warehouse'] || $r['zone'] || $r['aisle'])),
			'withCapacity' => count(array_filter($rows, fn($r) => floatval($r['capacity_units']) > 0)),
			'stockByLoc' => $stockByLoc,
			'unitsByLoc' => $unitsByLoc,
			'selectedLocId' => $selectedLocId,
			'canEdit' => DairyAccess::HasAny([User::PERMISSION_MASTER_DATA_EDIT]),
		]);
	}

	public function Traceability(Request $request, Response $response, array $args)
	{
		$q = $request->getQueryParams();
		$query = mb_substr(trim((string) ($q['q'] ?? '')), 0, 200);
		$productId = !empty($q['product_id']) ? intval($q['product_id']) : null;
		$svc = \Grocy\Services\TraceabilityService::GetInstance();
		$result = null;
		$error = null;
		if ($query !== '')
		{
			try
			{
				$result = $svc->Trace($query);
			}
			catch (\Exception $e)
			{
				$result = ['groups' => [], 'message' => $e->getMessage()];
			}
		}
		$db = DatabaseService::GetInstance()->GetDbConnectionRaw();
		return $this->RenderPage($response, 'traceability', [
			'q' => $query,
			'result' => $result,
			'productId' => $productId,
			'productLots' => $productId ? $svc->ProductLots($productId) : [],
			'products' => $db->query('SELECT id, name FROM products WHERE active = 1 ORDER BY name')->fetchAll(\PDO::FETCH_ASSOC),
		]);
	}

	public function LocationLabels(Request $request, Response $response, array $args)
	{
		$q = $request->getQueryParams();
		$db = DatabaseService::GetInstance()->GetDbConnectionRaw();
		$rows = $db->query('SELECT l.id, l.name, l.label_code, l.branch_id, l.warehouse, l.zone, l.aisle, l.shelf, l.bin, l.storage_class, l.capacity_units, b.name AS branch_name
			FROM locations l LEFT JOIN branches b ON b.id = l.branch_id WHERE l.active = 1 AND IFNULL(l.is_transit, 0) = 0 ORDER BY l.branch_id, l.warehouse, l.zone, l.aisle, l.shelf, l.bin, l.name')->fetchAll(\PDO::FETCH_ASSOC);
		$rows = array_values(array_filter($rows, fn($r) => $r['branch_id'] === null || DairyAccess::CanSeeBranch(intval($r['branch_id']))));
		$branchId = !empty($q['branch_id']) ? intval($q['branch_id']) : null;
		if ($branchId)
		{
			$rows = array_values(array_filter($rows, fn($r) => intval($r['branch_id']) === $branchId));
		}
		return $this->RenderPage($response, 'location_labels', [
			'locations' => $rows,
			'branches' => DairyAccess::FilterBranches($db->query('SELECT id, name FROM branches WHERE active = 1 ORDER BY id')->fetchAll(\PDO::FETCH_ASSOC)),
			'branchId' => $branchId,
			'symbology' => in_array($q['type'] ?? '', ['qrcode', 'datamatrix', 'code128']) ? $q['type'] : 'qrcode',
		]);
	}
}
