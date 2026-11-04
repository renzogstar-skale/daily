<?php

namespace Grocy\Services;

class MrpService extends BaseService
{
	/**
	 * Planificación MRP por punto de pedido con pronóstico, por sucursal ($branchId null/0 = red consolidada).
	 *
	 * - Demanda: media móvil ponderada de 4 semanas (ventas no anuladas) × índice por día de la semana, desviación estándar diaria de 28 días.
	 * - Disponible neto = stock − vencido − cuarentena − merma proyectada FEFO en el horizonte + órdenes abiertas.
	 * - Stock de seguridad = z(nivel de servicio) · σ · √LT, nunca menor que el stock mínimo del producto.
	 * - Pedido = cobertura (LT + revisión) + SS − disponible neto, con tope por vida útil y redondeo a MOQ / múltiplo.
	 * - Sin historial de ventas no se inventa demanda: solo se repone hasta el stock mínimo configurado.
	 */
	public function CalculateMrp(?int $branchId = 1, int $horizonDays = 14)
	{
		$dbRaw = DatabaseService::GetInstance()->GetDbConnectionRaw();
		$branchId = ($branchId !== null && $branchId > 0) ? $branchId : null;
		$serviceLevel = defined('GROCY_MRP_SERVICE_LEVEL') ? floatval(GROCY_MRP_SERVICE_LEVEL) : 0.95;
		$z = $this->ServiceLevelZ($serviceLevel);
		$reviewDays = defined('GROCY_MRP_REVIEW_PERIOD_DAYS') ? intval(GROCY_MRP_REVIEW_PERIOD_DAYS) : 7;
		$today = date('Y-m-d');

		$branchStockFilter = $branchId ? ' AND s.location_id IN (SELECT id FROM locations WHERE branch_id = :bid)' : '';
		$bp = $branchId ? [':bid' => $branchId] : [];

		// 1. Productos con su proveedor vigente más barato y costo real
		$products = $dbRaw->query("
			SELECT
				p.id AS product_id,
				p.name AS product_name,
				p.min_stock_amount,
				p.default_best_before_days,
				qu.name AS qu_name,
				pg.name AS category_name,
				spp.supplier_id,
				spp.price AS supplier_price,
				spp.min_order_qty,
				IFNULL(spp.order_multiple, 1) AS order_multiple,
				COALESCE(spp.delivery_lead_days, sm.lead_time_days, smp.lead_time_days) AS lead_time_days,
				COALESCE(sup.name, supp.name) AS supplier_name,
				COALESCE(spp.supplier_id, p.shopping_location_id) AS resolved_supplier_id,
				IFNULL((SELECT SUM(s.amount * s.price) / NULLIF(SUM(s.amount), 0) FROM stock s WHERE s.product_id = p.id AND s.price > 0),
					(SELECT plp.price FROM products_last_purchased plp WHERE plp.product_id = p.id)) AS stock_cost
			FROM products p
			LEFT JOIN quantity_units qu ON p.qu_id_stock = qu.id
			LEFT JOIN product_groups pg ON p.product_group_id = pg.id
			LEFT JOIN supplier_product_prices spp ON spp.id = (
				SELECT x.id FROM supplier_product_prices x
				WHERE x.product_id = p.id AND x.is_current = 1
				ORDER BY x.price ASC, x.delivery_lead_days ASC LIMIT 1
			)
			LEFT JOIN shopping_locations sup ON spp.supplier_id = sup.id
			LEFT JOIN suppliers_meta sm ON spp.supplier_id = sm.id
			LEFT JOIN shopping_locations supp ON p.shopping_location_id = supp.id
			LEFT JOIN suppliers_meta smp ON p.shopping_location_id = smp.id
			WHERE p.active = 1
			ORDER BY p.name ASC
		")->fetchAll(\PDO::FETCH_ASSOC);

		// 2. Lotes en stock (FEFO) por producto
		$stmtLots = $dbRaw->prepare("
			SELECT s.product_id, s.amount, s.best_before_date
			FROM stock s
			WHERE s.amount > 0 {$branchStockFilter}
			ORDER BY s.product_id, s.best_before_date ASC
		");
		$stmtLots->execute($bp);
		$lots = [];
		while ($row = $stmtLots->fetch(\PDO::FETCH_ASSOC))
		{
			$lots[$row['product_id']][] = $row;
		}

		// 3. Cantidades en cuarentena (no disponibles)
		$stmtQ = $dbRaw->prepare("SELECT product_id, SUM(amount) AS amount FROM quarantine_items WHERE status = 'in_quarantine'" . ($branchId ? ' AND branch_id = :bid' : '') . ' GROUP BY product_id');
		$stmtQ->execute($bp);
		$quarantine = $stmtQ->fetchAll(\PDO::FETCH_KEY_PAIR);

		// 4. Entradas planificadas: pendiente de recibir de órdenes abiertas (las recibidas, cerradas o canceladas ya no cuentan)
		$stmtOpen = $dbRaw->prepare("
			SELECT poi.product_id, SUM(MAX(0, IFNULL(poi.approved_amount, poi.suggested_amount) - poi.received_amount - poi.rejected_amount)) AS amount
			FROM procurement_order_items poi
			JOIN procurement_orders po ON po.id = poi.order_id
			WHERE po.status IN ('draft', 'approved', 'sent', 'partially_received')" . ($branchId ? ' AND po.branch_id = :bid' : '') . '
			GROUP BY poi.product_id
		');
		$stmtOpen->execute($bp);
		$openReceipts = $stmtOpen->fetchAll(\PDO::FETCH_KEY_PAIR);

		// 5. Ventas diarias de los últimos 28 días
		$stmtSales = $dbRaw->prepare("
			SELECT si.product_id, DATE(s.created_timestamp) AS day, SUM(si.amount) AS units
			FROM sale_items si
			JOIN sales s ON si.sale_id = s.id
			WHERE s.status != 'cancelled'
			AND DATE(s.created_timestamp) > DATE('now', 'localtime', '-28 days')" . ($branchId ? ' AND s.branch_id = :bid' : '') . '
			GROUP BY si.product_id, DATE(s.created_timestamp)
		');
		$stmtSales->execute($bp);
		$dailySales = [];
		while ($row = $stmtSales->fetch(\PDO::FETCH_ASSOC))
		{
			$dailySales[$row['product_id']][$row['day']] = floatval($row['units']);
		}

		$results = [];
		$totalEstimatedCost = 0.0;
		$reorderCount = 0;
		$overstockCount = 0;
		$noHistoryCount = 0;
		$totalProjectedSpoilageCost = 0.0;
		// Pronóstico de ventas (eventos, corrección aprendida, estacionalidad anual) del motor de pronóstico laboral
		$outlook = LaborForecastService::GetInstance()->DemandOutlook($branchId, max(60, $horizonDays + $reviewDays + 30));
		$dayFactor = fn(int $k) => $outlook['factors'][$k] ?? 1.0;

		foreach ($products as $p)
		{
			$pid = $p['product_id'];
			$warnings = [];
			$minStock = floatval($p['min_stock_amount']);
			$shelfLife = intval($p['default_best_before_days']);
			$leadTimeDays = $p['lead_time_days'] !== null ? max(1, intval($p['lead_time_days'])) : null;
			if ($leadTimeDays === null)
			{
				$leadTimeDays = 2;
				if (!empty($p['resolved_supplier_id']))
				{
					$warnings[] = 'Lead time no configurado en el proveedor (se usan 2 días)';
				}
			}

			// Costo unitario: precio vigente del proveedor, o costo real del stock / última compra
			$unitCost = $p['supplier_price'] !== null ? floatval($p['supplier_price']) : floatval($p['stock_cost'] ?? 0);
			if ($unitCost <= 0)
			{
				$warnings[] = 'Sin precio de compra registrado';
			}
			if (empty($p['resolved_supplier_id']))
			{
				$warnings[] = 'Sin proveedor asignado';
			}

			// Demanda: media ponderada por semana (la más reciente pesa 4) y σ diaria
			$series = [];
			for ($d = 27; $d >= 0; $d--)
			{
				$day = date('Y-m-d', strtotime("-{$d} days"));
				$series[] = $dailySales[$pid][$day] ?? 0.0;
			}
			$weeks = array_chunk($series, 7); // [0] = más antigua
			$weightedSum = 0.0;
			$weightTotal = 0;
			foreach ($weeks as $idx => $week)
			{
				$w = $idx + 1;
				$weightedSum += (array_sum($week) / 7.0) * $w;
				$weightTotal += $w;
			}
			$vmd = round($weightedSum / $weightTotal, 2);
			$mean = array_sum($series) / count($series);
			$variance = 0.0;
			foreach ($series as $v)
			{
				$variance += ($v - $mean) ** 2;
			}
			$sigma = round(sqrt($variance / (count($series) - 1)), 2);
			$hasHistory = array_sum($series) > 0;

			// Estacionalidad semanal: índice por día de la semana (promedio del día / promedio general) con las 4
			// observaciones de cada día; solo con volumen suficiente y acotado a [0.5, 2] para no amplificar ruido
			$dowIndex = array_fill(0, 7, 1.0);
			if ($mean > 0 && array_sum($series) >= GROCY_MRP_SEASONALITY_MIN_UNITS)
			{
				$byDow = array_fill(0, 7, 0.0);
				foreach ($series as $i => $v)
				{
					$byDow[intval(date('w', strtotime('-' . (27 - $i) . ' days')))] += $v / 4.0;
				}
				foreach ($byDow as $dow => $avg)
				{
					$dowIndex[$dow] = round(min(2.0, max(0.5, $avg / $mean)), 2);
				}
			}
			$demandOver = function (int $days) use ($vmd, $dowIndex, $dayFactor) {
				$total = 0.0;
				for ($k = 1; $k <= $days; $k++)
				{
					$total += $vmd * $dowIndex[intval(date('w', strtotime("+{$k} days")))] * $dayFactor($k);
				}
				return $total;
			};

			// Stock: vencido, merma proyectada FEFO en el horizonte y utilizable
			$stockTotal = 0.0;
			$expired = 0.0;
			$projectedSpoilage = 0.0;
			$absorbed = 0.0;
			foreach ($lots[$pid] ?? [] as $lot)
			{
				$amount = floatval($lot['amount']);
				$stockTotal += $amount;
				if ($lot['best_before_date'] < $today)
				{
					$expired += $amount;
					continue;
				}
				$daysLeft = (strtotime($lot['best_before_date']) - strtotime($today)) / 86400;
				if ($daysLeft <= $horizonDays)
				{
					// Lo que la demanda no alcanza a vender antes del vencimiento del lote (consumo FEFO)
					$sellable = max(0.0, $vmd * $daysLeft - $absorbed);
					$sold = min($amount, $sellable);
					$projectedSpoilage += $amount - $sold;
					$absorbed += $sold;
				}
				else
				{
					$absorbed += $amount;
				}
			}
			$quarantined = floatval($quarantine[$pid] ?? 0);
			$usable = max(0.0, $stockTotal - $expired - $quarantined - $projectedSpoilage);
			$open = floatval($openReceipts[$pid] ?? 0);
			$netAvailable = $usable + $open;
			$spoilageCost = round(($projectedSpoilage + $expired) * $unitCost, 2);
			$totalProjectedSpoilageCost += $spoilageCost;

			// Stock de seguridad y punto de pedido
			$safetyStock = round(max($z * $sigma * sqrt($leadTimeDays), $minStock), 1);
			$rop = round($demandOver($leadTimeDays) + $safetyStock, 1);
			$daysCoverage = $vmd > 0 ? round($usable / $vmd, 1) : null;

			$status = 'normal';
			$suggestedOrder = 0.0;

			if (!$hasHistory)
			{
				$status = 'no_history';
				$noHistoryCount++;
				// Sin demanda observada no se proyecta venta: se repone hasta el stock mínimo solo con el stock
				// físicamente disponible (sin descontar la merma proyectada, que con VMD 0 abarcaría todo lo que vence)
				$physicalAvailable = max(0.0, $stockTotal - $expired - $quarantined) + $open;
				if ($minStock > 0 && $physicalAvailable < $minStock)
				{
					$suggestedOrder = $minStock - $physicalAvailable;
				}
				if ($projectedSpoilage > 0)
				{
					$warnings[] = 'Stock por vencer en el horizonte sin ventas registradas';
				}
			}
			elseif ($netAvailable <= $rop)
			{
				$status = 'reorder_needed';
				$suggestedOrder = max(0.0, $demandOver($leadTimeDays + $reviewDays) + $safetyStock - $netAvailable);

				// Tope anti-merma: no comprar más de lo vendible en el 80 % de la vida útil
				if ($shelfLife > 0)
				{
					$maxPerishable = floor($vmd * $shelfLife * 0.8);
					if ($suggestedOrder > $maxPerishable)
					{
						$suggestedOrder = $maxPerishable;
						$warnings[] = 'Pedido limitado por vida útil';
					}
				}
			}
			elseif ($vmd > 0 && $daysCoverage > ($shelfLife > 0 ? $shelfLife * 0.8 : 25))
			{
				$status = 'overstock_risk';
				$overstockCount++;
			}

			// Redondeo a MOQ y múltiplo de empaque
			if ($suggestedOrder > 0)
			{
				$suggestedOrder = ceil($suggestedOrder);
				$multiple = max(1.0, floatval($p['order_multiple']));
				$suggestedOrder = ceil($suggestedOrder / $multiple) * $multiple;
				$moq = floatval($p['min_order_qty'] ?? 0);
				if ($moq > 0 && $suggestedOrder < $moq)
				{
					$suggestedOrder = $moq;
					if ($shelfLife > 0 && $hasHistory && $moq > $vmd * $shelfLife * 0.8)
					{
						$warnings[] = 'El MOQ del proveedor supera lo vendible en la vida útil (riesgo de merma)';
					}
				}
			}
			if ($suggestedOrder > 0)
			{
				$reorderCount++;
			}

			$orderCost = round($suggestedOrder * $unitCost, 2);
			$totalEstimatedCost += $orderCost;

			$results[] = [
				'product_id' => $pid,
				'product_name' => $p['product_name'],
				'category_name' => $p['category_name'] ?? 'General',
				'qu_name' => $p['qu_name'] ?? 'u',
				'current_stock' => round($stockTotal, 2),
				'expired_stock' => round($expired, 2),
				'quarantine_stock' => round($quarantined, 2),
				'projected_spoilage' => round($projectedSpoilage, 2),
				'projected_spoilage_cost' => $spoilageCost,
				'usable_stock' => round($usable, 2),
				'open_receipts' => round($open, 2),
				'net_available' => round($netAvailable, 2),
				'min_stock' => $minStock,
				'vmd' => $vmd,
				'dow_index' => $dowIndex,
				'seasonal' => max($dowIndex) - min($dowIndex) >= 0.3,
				'demand_sigma' => $sigma,
				'lead_time_days' => $leadTimeDays,
				'safety_stock' => $safetyStock,
				'rop' => $rop,
				'forecast_demand' => round($demandOver($leadTimeDays + $reviewDays), 1),
				'days_coverage' => $daysCoverage ?? 99.0,
				'status' => $status,
				'suggested_order' => $suggestedOrder,
				'purchase_price' => $unitCost,
				'estimated_cost' => $orderCost,
				'best_before_days' => $shelfLife,
				'min_order_qty' => floatval($p['min_order_qty'] ?? 0),
				'supplier_id' => $p['resolved_supplier_id'] !== null ? intval($p['resolved_supplier_id']) : null,
				'supplier_name' => $p['supplier_name'] ?? 'Sin proveedor',
				'warnings' => $warnings
			];
		}

		return [
			'branch_id' => $branchId,
			'horizon_days' => $horizonDays,
			'forecast' => ['applied' => $outlook['applied'], 'events' => $outlook['events'], 'correction' => $outlook['correction'], 'seasonality' => $outlook['seasonality']],
			'service_level' => $serviceLevel,
			'generated_at' => date('Y-m-d H:i:s'),
			'summary' => [
				'total_products' => count($products),
				'reorder_needed_count' => $reorderCount,
				'overstock_risk_count' => $overstockCount,
				'no_history_count' => $noHistoryCount,
				'total_suggested_order_cost' => round($totalEstimatedCost, 2),
				'projected_spoilage_cost' => round($totalProjectedSpoilageCost, 2)
			],
			'items' => $results
		];
	}

	// Factor z de la normal estándar para el nivel de servicio (ciclo) solicitado
	private function ServiceLevelZ(float $serviceLevel): float
	{
		// Pares [nivel, z]: claves float en un array PHP se truncan a 0
		$table = [[0.80, 0.84], [0.85, 1.04], [0.90, 1.28], [0.95, 1.65], [0.97, 1.88], [0.98, 2.05], [0.99, 2.33]];
		$z = 0.84;
		foreach ($table as [$level, $value])
		{
			if ($serviceLevel >= $level)
			{
				$z = $value;
			}
		}
		return $z;
	}

	/**
	 * Create a draft Procurement Order from MRP suggestions
	 */
	public function CreateProcurementOrder(array $data, int $userId)
	{
		$dbRaw = DatabaseService::GetInstance()->GetDbConnectionRaw();
		$branchId = intval($data['branch_id'] ?? 1);
		$supplierId = intval($data['supplier_id'] ?? 0);
		$items = $data['items'] ?? [];

		if ($supplierId <= 0)
		{
			throw new \Exception("Asigne un proveedor al producto antes de generar la orden de reabastecimiento");
		}

		if (empty($items))
		{
			throw new \Exception("Debe seleccionar al menos un producto para generar la orden de reabastecimiento");
		}

		$dbRaw->beginTransaction();
		try
		{
			$orderCode = 'PO-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
			$totalCost = 0.0;

			$stmtOrder = $dbRaw->prepare("
				INSERT INTO procurement_orders (order_code, branch_id, supplier_id, status, generated_by, total_estimated_cost, created_at)
				VALUES (:order_code, :branch_id, :supplier_id, 'draft', 'mrp_engine', :total_cost, datetime('now'))
			");

			$stmtOrder->execute([
				':order_code' => $orderCode,
				':branch_id' => $branchId,
				':supplier_id' => $supplierId,
				':total_cost' => 0.0
			]);
			$orderId = intval($dbRaw->lastInsertId());

			$stmtItem = $dbRaw->prepare("
				INSERT INTO procurement_order_items (order_id, product_id, suggested_amount, approved_amount, unit_price, vmd_daily_sales, days_coverage_projected)
				VALUES (:order_id, :product_id, :suggested_amount, :approved_amount, :unit_price, :vmd, :coverage)
			");

			foreach ($items as $item)
			{
				$pid = intval($item['product_id']);
				$qty = floatval($item['amount']);
				$price = floatval($item['unit_price']);
				$vmd = floatval($item['vmd'] ?? 0.0);
				$coverage = floatval($item['coverage'] ?? 7.0);

				if ($qty > 0)
				{
					$stmtItem->execute([
						':order_id' => $orderId,
						':product_id' => $pid,
						':suggested_amount' => $qty,
						':approved_amount' => $qty,
						':unit_price' => $price,
						':vmd' => $vmd,
						':coverage' => $coverage
					]);
					$totalCost += ($qty * $price);
				}
			}

			// Update total estimated cost
			$stmtUpdate = $dbRaw->prepare("UPDATE procurement_orders SET total_estimated_cost = :cost WHERE id = :id");
			$stmtUpdate->execute([':cost' => round($totalCost, 2), ':id' => $orderId]);

			$dbRaw->commit();

			return [
				'id' => $orderId,
				'order_code' => $orderCode,
				'total_estimated_cost' => round($totalCost, 2),
				'status' => 'draft',
				'message' => "Orden de compra {$orderCode} generada exitosamente en estado borrador"
			];
		}
		catch (\Exception $e)
		{
			$dbRaw->rollBack();
			throw $e;
		}
	}

	/**
	 * Genera órdenes borrador desde el cálculo MRP: una por proveedor con las líneas que requieren reposición.
	 * Las líneas sin proveedor se omiten y se informan.
	 */
	public function GenerateOrdersFromMrp(?int $branchId, int $userId): array
	{
		$mrp = $this->CalculateMrp($branchId);
		$bySupplier = [];
		$skipped = [];
		foreach ($mrp['items'] as $item)
		{
			if ($item['suggested_order'] <= 0)
			{
				continue;
			}
			if (empty($item['supplier_id']))
			{
				$skipped[] = $item['product_name'];
				continue;
			}
			$bySupplier[$item['supplier_id']][] = [
				'product_id' => $item['product_id'],
				'amount' => $item['suggested_order'],
				'unit_price' => $item['purchase_price'],
				'vmd' => $item['vmd'],
				'coverage' => $item['days_coverage']
			];
		}

		if (empty($bySupplier))
		{
			throw new \Exception(empty($skipped) ? 'No hay productos que requieran reposición' : 'Los productos a reponer no tienen proveedor asignado: ' . implode(', ', $skipped));
		}

		$orders = [];
		foreach ($bySupplier as $supplierId => $items)
		{
			$orders[] = $this->CreateProcurementOrder(['branch_id' => $branchId ?: 1, 'supplier_id' => $supplierId, 'items' => $items], $userId);
		}

		return [
			'orders' => $orders,
			'skipped_products' => $skipped,
			'message' => count($orders) . ' orden(es) borrador generada(s)' . (empty($skipped) ? '' : '; sin proveedor: ' . implode(', ', $skipped))
		];
	}

	/**
	 * Get procurement orders list
	 */
	public function GetProcurementOrders(?int $branchId = null, ?string $status = null)
	{
		$dbRaw = DatabaseService::GetInstance()->GetDbConnectionRaw();
		$sql = "
			SELECT 
				po.*,
				sup.name AS supplier_name,
				b.name AS branch_name,
				IFNULL(NULLIF(TRIM(u.first_name || ' ' || IFNULL(u.last_name, '')), ''), u.username) AS approved_by_name,
				COUNT(poi.id) AS item_count
			FROM procurement_orders po
			LEFT JOIN shopping_locations sup ON po.supplier_id = sup.id
			LEFT JOIN branches b ON po.branch_id = b.id
			LEFT JOIN users u ON po.approved_by_user_id = u.id
			LEFT JOIN procurement_order_items poi ON po.id = poi.order_id
			WHERE 1=1
		";
		$params = [];
		if ($branchId !== null)
		{
			$sql .= " AND po.branch_id = :branch_id";
			$params[':branch_id'] = $branchId;
		}
		if ($status !== null)
		{
			$sql .= " AND po.status = :status";
			$params[':status'] = $status;
		}
		$sql .= " GROUP BY po.id ORDER BY po.id DESC";

		$stmt = $dbRaw->prepare($sql);
		$stmt->execute($params);
		return $stmt->fetchAll(\PDO::FETCH_OBJ);
	}

	/**
	 * Approve a draft procurement order
	 */
	public function ApproveProcurementOrder(int $orderId, int $userId)
	{
		$dbRaw = DatabaseService::GetInstance()->GetDbConnectionRaw();
		$stmt = $dbRaw->prepare("
			UPDATE procurement_orders 
			SET status = 'approved', approved_by_user_id = :user_id, approved_at = datetime('now')
			WHERE id = :id AND status = 'draft'
		");
		$stmt->execute([':user_id' => $userId, ':id' => $orderId]);

		if ($stmt->rowCount() === 0)
		{
			throw new \Exception("La orden #{$orderId} no existe o ya no está en estado borrador");
		}

		return true;
	}

	/**
	 * Orden con sus líneas, pendientes de recibir, ubicaciones de la sucursal y reglas de recepción por producto
	 */
	public function GetProcurementOrderDetail(int $orderId): array
	{
		$dbRaw = DatabaseService::GetInstance()->GetDbConnectionRaw();
		$order = $this->FetchOrder($orderId);

		$stmt = $dbRaw->prepare("
			SELECT poi.*, p.name AS product_name, IFNULL(qu.name, 'u') AS qu_name, p.location_id AS product_location_id,
				p.default_best_before_days, r.min_remaining_shelf_life_days, r.min_temperature, r.max_temperature,
				MAX(0, IFNULL(poi.approved_amount, poi.suggested_amount) - poi.received_amount - poi.rejected_amount) AS pending_amount
			FROM procurement_order_items poi
			JOIN products p ON p.id = poi.product_id
			LEFT JOIN quantity_units qu ON qu.id = p.qu_id_stock
			LEFT JOIN product_receiving_rules r ON r.product_id = poi.product_id
			WHERE poi.order_id = :id
			ORDER BY p.name
		");
		$stmt->execute([':id' => $orderId]);
		$items = $stmt->fetchAll(\PDO::FETCH_ASSOC);
		foreach ($items as &$item)
		{
			$item['min_remaining_days'] = $this->MinRemainingShelfLifeDays($item);
		}

		$stmtLoc = $dbRaw->prepare('SELECT id, name FROM locations WHERE active = 1 AND branch_id = :bid AND IFNULL(is_transit, 0) = 0 ORDER BY name');
		$stmtLoc->execute([':bid' => $order['branch_id']]);

		$stmtRec = $dbRaw->prepare('SELECT * FROM procurement_receipts WHERE order_id = :id ORDER BY id');
		$stmtRec->execute([':id' => $orderId]);

		return [
			'order' => $order,
			'items' => $items,
			'locations' => $stmtLoc->fetchAll(\PDO::FETCH_ASSOC),
			'receipts' => $stmtRec->fetchAll(\PDO::FETCH_ASSOC)
		];
	}

	/**
	 * Aprobada → enviada al proveedor, con fecha de entrega comprometida (base del OTIF)
	 */
	public function SendProcurementOrder(int $orderId, int $userId, ?string $expectedDeliveryDate = null)
	{
		$order = $this->FetchOrder($orderId);
		if ($order['status'] !== 'approved')
		{
			throw new \Exception("Solo se puede enviar una orden aprobada (estado actual: {$order['status']})");
		}

		if (empty($expectedDeliveryDate))
		{
			$expectedDeliveryDate = date('Y-m-d', strtotime('+' . $this->SupplierLeadTimeDays($order['supplier_id']) . ' days'));
		}

		$stmt = DatabaseService::GetInstance()->GetDbConnectionRaw()->prepare("
			UPDATE procurement_orders SET status = 'sent', sent_at = datetime('now', 'localtime'), sent_by_user_id = :uid, expected_delivery_date = :edd
			WHERE id = :id
		");
		$stmt->execute([':uid' => $userId, ':edd' => $expectedDeliveryDate, ':id' => $orderId]);

		return true;
	}

	/**
	 * Cancela (borrador/aprobada sin recepciones) o cierra con faltante (enviada/parcial). El pendiente deja de contar en el MRP.
	 */
	public function CloseProcurementOrder(int $orderId, int $userId, string $reason)
	{
		$order = $this->FetchOrder($orderId);
		$reason = trim($reason);
		if ($reason === '')
		{
			throw new \Exception('Indique el motivo');
		}

		if (in_array($order['status'], ['draft', 'approved']))
		{
			$newStatus = 'cancelled';
		}
		elseif (in_array($order['status'], ['sent', 'partially_received']))
		{
			$newStatus = 'closed';
		}
		else
		{
			throw new \Exception("La orden ya está {$order['status']}");
		}

		$stmt = DatabaseService::GetInstance()->GetDbConnectionRaw()->prepare("
			UPDATE procurement_orders SET status = :st, closed_at = datetime('now', 'localtime'), closed_by_user_id = :uid, close_reason = :reason
			WHERE id = :id
		");
		$stmt->execute([':st' => $newStatus, ':uid' => $userId, ':reason' => $reason, ':id' => $orderId]);

		return $newStatus;
	}

	/**
	 * Recepción contra orden (equivalente a MIGO 101): por línea entregada se aplican las reglas de recepción;
	 * lo aceptado entra al stock como compra (stock_log, una transacción por recepción) y lo rechazado genera una
	 * incidencia con el proveedor, sin tocar el inventario. Registra además la entrega para el OTIF del proveedor.
	 *
	 * $data: items[] {order_item_id, delivered_amount, rejected_amount, reject_reason, best_before_date, location_id, unit_price},
	 *        temperature_on_arrival, supplier_document, notes
	 */
	public function ReceiveProcurementOrder(int $orderId, array $data, int $userId): array
	{
		// Bloqueo de escritura ANTES de leer pendientes: recepciones simultáneas esperan su turno y validan sobre el estado ya actualizado
		$db = DatabaseService::GetInstance()->BeginWriteTransaction();
		try
		{
			return $this->ReceiveProcurementOrderLocked($orderId, $data, $userId);
		}
		finally
		{
			if ($db->inTransaction())
			{
				$db->rollBack();
			}
		}
	}

	private function ReceiveProcurementOrderLocked(int $orderId, array $data, int $userId): array
	{
		$dbRaw = DatabaseService::GetInstance()->GetDbConnectionRaw();
		$detail = $this->GetProcurementOrderDetail($orderId);
		$order = $detail['order'];

		if (!in_array($order['status'], ['approved', 'sent', 'partially_received']))
		{
			throw new \Exception("No se puede recibir una orden en estado {$order['status']}");
		}

		$temperature = (isset($data['temperature_on_arrival']) && $data['temperature_on_arrival'] !== '') ? floatval($data['temperature_on_arrival']) : null;
		$itemsById = array_column($detail['items'], null, 'id');
		$branchLocations = array_column($detail['locations'], 'id');
		$today = date('Y-m-d');

		// Validar y clasificar todas las líneas antes de tocar el stock
		$lines = [];
		foreach ($data['items'] ?? [] as $in)
		{
			$item = $itemsById[intval($in['order_item_id'] ?? 0)] ?? null;
			if ($item)
			{
				DeviceService::AssertNotControlled(intval($item['product_id']), 'recibirlo');
			}
			if ($item === null)
			{
				throw new \Exception('Línea de orden inválida');
			}
			$delivered = floatval($in['delivered_amount'] ?? 0);
			if ($delivered <= 0)
			{
				continue;
			}
			if ($delivered > floatval($item['pending_amount']) + 0.0001)
			{
				throw new \Exception("{$item['product_name']}: se entregan {$delivered} y solo quedan {$item['pending_amount']} pendientes. Registre el excedente como nueva orden.");
			}

			$rule = null;
			$rejected = min($delivered, max(0.0, floatval($in['rejected_amount'] ?? 0)));
			$reasons = [];
			if ($rejected > 0)
			{
				$reasons[] = trim($in['reject_reason'] ?? '') ?: 'Rechazo en inspección';
			}

			$bbd = trim($in['best_before_date'] ?? '');
			if ($bbd !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $bbd))
			{
				throw new \Exception("{$item['product_name']}: fecha de vencimiento inválida");
			}
			$minDays = $item['min_remaining_days'];
			if ($minDays !== null && $minDays > 0 && $rejected < $delivered)
			{
				if ($bbd === '')
				{
					throw new \Exception("{$item['product_name']}: indique la fecha de vencimiento (exige vida útil remanente mínima de {$minDays} días)");
				}
				$remaining = (int) floor((strtotime($bbd) - strtotime($today)) / 86400);
				if ($remaining < $minDays)
				{
					$rejected = $delivered;
					$rule = 'near_expiration';
					$reasons[] = "Vida útil remanente {$remaining} d < mínimo {$minDays} d";
				}
			}
			if ($temperature !== null && $rejected < $delivered)
			{
				if (($item['max_temperature'] !== null && $temperature > floatval($item['max_temperature'])) || ($item['min_temperature'] !== null && $temperature < floatval($item['min_temperature'])))
				{
					$rejected = $delivered;
					$rule = 'cold_chain_break';
					$reasons[] = "Temperatura {$temperature} °C fuera de rango (" . ($item['min_temperature'] ?? '—') . ' a ' . ($item['max_temperature'] ?? '—') . ' °C)';
				}
			}

			$locationId = intval($in['location_id'] ?? 0) ?: intval($item['product_location_id']);
			if ($rejected < $delivered && !in_array($locationId, $branchLocations))
			{
				throw new \Exception("{$item['product_name']}: seleccione una ubicación de la sucursal de destino");
			}

			$lines[] = [
				'item' => $item,
				'delivered' => $delivered,
				'accepted' => round($delivered - $rejected, 4),
				'rejected' => $rejected,
				'reason' => implode('; ', array_unique($reasons)),
				'rule' => $rule,
				'bbd' => $bbd !== '' ? $bbd : null,
				'lot' => mb_substr(trim((string) ($in['lot_number'] ?? '')), 0, 40) ?: null,
				'location_id' => $locationId,
				'price' => isset($in['unit_price']) && $in['unit_price'] !== '' ? floatval($in['unit_price']) : floatval($item['unit_price'])
			];
		}

		if (empty($lines))
		{
			throw new \Exception('Indique al menos una cantidad entregada');
		}

		if (!$dbRaw->inTransaction())
		{
			$dbRaw->beginTransaction();
		}
		try
		{
			$next = intval($dbRaw->query('SELECT IFNULL(MAX(id), 0) + 1 FROM procurement_receipts')->fetchColumn());
			$receiptCode = 'REC-' . date('Y') . '-' . str_pad((string) $next, 5, '0', STR_PAD_LEFT);

			$stmtReceipt = $dbRaw->prepare("
				INSERT INTO procurement_receipts (receipt_code, order_id, supplier_id, branch_id, received_by_user_id, supplier_document, temperature_on_arrival, notes)
				VALUES (:code, :oid, :sid, :bid, :uid, :doc, :temp, :notes)
			");
			$stmtReceipt->execute([
				':code' => $receiptCode,
				':oid' => $orderId,
				':sid' => $order['supplier_id'],
				':bid' => $order['branch_id'],
				':uid' => $userId,
				':doc' => trim($data['supplier_document'] ?? '') ?: null,
				':temp' => $temperature,
				':notes' => trim($data['notes'] ?? '') ?: null
			]);
			$receiptId = intval($dbRaw->lastInsertId());

			$stmtLine = $dbRaw->prepare("
				INSERT INTO procurement_receipt_items (receipt_id, order_item_id, product_id, delivered_amount, accepted_amount, rejected_amount, reject_reason, best_before_date, location_id, unit_price, incident_id)
				VALUES (:rid, :oiid, :pid, :del, :acc, :rej, :reason, :bbd, :loc, :price, :inc)
			");
			$stmtItem = $dbRaw->prepare('UPDATE procurement_order_items SET received_amount = received_amount + :acc, rejected_amount = rejected_amount + :rej WHERE id = :id');
			$stmtIncident = $dbRaw->prepare("
				INSERT INTO supplier_incidents (supplier_id, product_id, branch_id, incident_date, incident_type, severity, status, description, cost_impact)
				VALUES (:sid, :pid, :bid, date('now', 'localtime'), :type, :sev, 'open', :desc, :cost)
			");

			$stockService = StockService::GetInstance();
			$transactionId = null;
			$acceptedCost = 0.0;
			$rejectedCost = 0.0;
			$deliveredTotal = 0.0;
			$acceptedTotal = 0.0;

			foreach ($lines as $l)
			{
				$item = $l['item'];
				if ($l['accepted'] > 0)
				{
					$stockService->AddProduct($item['product_id'], $l['accepted'], $l['bbd'], StockService::TRANSACTION_TYPE_PURCHASE, $today, $l['price'], $l['location_id'], $order['supplier_id'], $transactionId, 0, false, $receiptCode . ' / ' . $order['order_code']);
					$acceptedCost += $l['accepted'] * $l['price'];
				}

				$incidentId = null;
				if ($l['rejected'] > 0)
				{
					$cost = round($l['rejected'] * $l['price'], 2);
					$rejectedCost += $cost;
					$stmtIncident->execute([
						':sid' => $order['supplier_id'],
						':pid' => $item['product_id'],
						':bid' => $order['branch_id'],
						':type' => $l['rule'] ?? 'reception_rejection',
						':sev' => $l['rejected'] >= $l['delivered'] ? 'high' : 'medium',
						':desc' => "{$receiptCode} ({$order['order_code']}): rechazo de {$l['rejected']} {$item['qu_name']} de {$item['product_name']}. {$l['reason']}",
						':cost' => $cost
					]);
					$incidentId = intval($dbRaw->lastInsertId());
				}

				$stmtLine->execute([
					':rid' => $receiptId,
					':oiid' => $item['id'],
					':pid' => $item['product_id'],
					':del' => $l['delivered'],
					':acc' => $l['accepted'],
					':rej' => $l['rejected'],
					':reason' => $l['reason'] ?: null,
					':bbd' => $l['bbd'],
					':loc' => $l['accepted'] > 0 ? $l['location_id'] : null,
					':price' => $l['price'],
					':inc' => $incidentId
				]);
				$stmtItem->execute([':acc' => $l['accepted'], ':rej' => $l['rejected'], ':id' => $item['id']]);
				$deliveredTotal += $l['delivered'];
				$acceptedTotal += $l['accepted'];
			}

			// Estado de la orden según lo pendiente
			$stmtPending = $dbRaw->prepare('SELECT IFNULL(SUM(MAX(0, IFNULL(approved_amount, suggested_amount) - received_amount - rejected_amount)), 0) FROM procurement_order_items WHERE order_id = :id');
			$stmtPending->execute([':id' => $orderId]);
			$pendingAfter = floatval($stmtPending->fetchColumn());
			$newStatus = $pendingAfter > 0.0001 ? 'partially_received' : 'received';
			$stmtStatus = $dbRaw->prepare("UPDATE procurement_orders SET status = :st, closed_at = CASE WHEN :st2 = 'received' THEN datetime('now', 'localtime') ELSE closed_at END WHERE id = :id");
			$stmtStatus->execute([':st' => $newStatus, ':st2' => $newStatus, ':id' => $orderId]);

			// Entrega para la evaluación OTIF del proveedor (antes se digitaba aparte)
			$expected = $order['expected_delivery_date'] ?: date('Y-m-d', strtotime(substr($order['approved_at'] ?? $order['created_at'], 0, 10) . ' +' . $this->SupplierLeadTimeDays($order['supplier_id']) . ' days'));
			$late = $today > $expected;
			$orderedTotal = array_sum(array_map(fn($i) => floatval($i['pending_amount']), array_filter($detail['items'], fn($i) => $i['pending_amount'] > 0)));
			$deliveryStatus = $late ? 'delayed' : ($acceptedTotal + 0.0001 < $orderedTotal ? 'partial' : 'on_time');
			$rating = 5 - ($late ? 1 : 0) - ($acceptedTotal + 0.0001 < $orderedTotal ? 1 : 0) - ($rejectedCost > 0 ? 2 : 0);
			$stmtDelivery = $dbRaw->prepare("
				INSERT INTO supplier_deliveries (supplier_id, branch_id, order_reference, order_date, expected_delivery_date, actual_delivery_date, status, items_ordered, items_received, temperature_on_arrival, compliance_rating, notes)
				VALUES (:sid, :bid, :ref, :odate, :edd, :add, :st, :ord, :rec, :temp, :rating, :notes)
			");
			$stmtDelivery->execute([
				':sid' => $order['supplier_id'],
				':bid' => $order['branch_id'],
				':ref' => $order['order_code'],
				':odate' => substr($order['sent_at'] ?? $order['approved_at'] ?? $order['created_at'], 0, 10),
				':edd' => $expected,
				':add' => $today,
				':st' => $deliveryStatus,
				':ord' => $orderedTotal,
				':rec' => $acceptedTotal,
				':temp' => $temperature,
				':rating' => max(1, $rating),
				':notes' => $receiptCode
			]);
			$deliveryId = intval($dbRaw->lastInsertId());

			$stmtUpd = $dbRaw->prepare('UPDATE procurement_receipts SET stock_transaction_id = :tx, delivery_id = :did, total_accepted_cost = :acc, total_rejected_cost = :rej WHERE id = :id');
			$stmtUpd->execute([':tx' => $transactionId, ':did' => $deliveryId, ':acc' => round($acceptedCost, 2), ':rej' => round($rejectedCost, 2), ':id' => $receiptId]);

			// Trazabilidad: lote del proveedor ligado a cada entrada de stock creada por esta recepción
			if ($transactionId)
			{
				$lotMap = [];
				foreach ($lines as $l)
				{
					if ($l['accepted'] > 0 && $l['lot'])
					{
						$lotMap[intval($l['item']['product_id'])] = ['lot' => $l['lot']];
					}
				}
				TraceabilityService::GetInstance()->RegisterLots($transactionId, $lotMap, $userId);
			}

			$dbRaw->commit();
		}
		catch (\Exception $e)
		{
			$dbRaw->rollBack();
			throw $e;
		}

		return [
			'id' => $receiptId,
			'receipt_code' => $receiptCode,
			'order_status' => $newStatus,
			'accepted_cost' => round($acceptedCost, 2),
			'rejected_cost' => round($rejectedCost, 2),
			'stock_transaction_id' => $transactionId,
			'message' => "Recepción {$receiptCode} registrada: $" . number_format($acceptedCost, 2) . ' ingresado al stock' . ($rejectedCost > 0 ? ', $' . number_format($rejectedCost, 2) . ' rechazado (incidencia abierta con el proveedor)' : '')
		];
	}

	/**
	 * Recepciones registradas (documento REC-)
	 */
	public function GetReceipts(?int $branchId = null, int $limit = 50): array
	{
		$dbRaw = DatabaseService::GetInstance()->GetDbConnectionRaw();
		$stmt = $dbRaw->prepare("
			SELECT r.*, po.order_code, sl.name AS supplier_name,
				IFNULL(NULLIF(TRIM(u.first_name || ' ' || IFNULL(u.last_name, '')), ''), u.username) AS received_by_name,
				(SELECT COUNT(*) FROM procurement_receipt_items ri WHERE ri.receipt_id = r.id AND ri.rejected_amount > 0) AS rejected_lines
			FROM procurement_receipts r
			JOIN procurement_orders po ON po.id = r.order_id
			LEFT JOIN shopping_locations sl ON sl.id = r.supplier_id
			LEFT JOIN users u ON u.id = r.received_by_user_id
			WHERE (:bid IS NULL OR r.branch_id = :bid2)
			ORDER BY r.id DESC LIMIT " . max(1, $limit)
		);
		$stmt->execute([':bid' => $branchId, ':bid2' => $branchId]);
		return $stmt->fetchAll(\PDO::FETCH_ASSOC);
	}

	/**
	 * Reglas de recepción por producto (vacío = sin regla; la vida útil remanente usa el % por defecto de la configuración)
	 */
	public function GetReceivingRules(): array
	{
		return DatabaseService::GetInstance()->GetDbConnectionRaw()->query("
			SELECT p.id AS product_id, p.name AS product_name, p.default_best_before_days,
				r.min_remaining_shelf_life_days, r.min_temperature, r.max_temperature
			FROM products p
			LEFT JOIN product_receiving_rules r ON r.product_id = p.id
			WHERE p.active = 1
			ORDER BY p.name
		")->fetchAll(\PDO::FETCH_ASSOC);
	}

	public function SaveReceivingRule(int $productId, array $data)
	{
		$val = function($key, $int = false) use ($data) {
			if (!isset($data[$key]) || $data[$key] === '' || $data[$key] === null)
			{
				return null;
			}
			return $int ? max(0, intval($data[$key])) : floatval($data[$key]);
		};
		$minDays = $val('min_remaining_shelf_life_days', true);
		$minTemp = $val('min_temperature');
		$maxTemp = $val('max_temperature');
		if ($minTemp !== null && $maxTemp !== null && $minTemp > $maxTemp)
		{
			throw new \Exception('La temperatura mínima no puede ser mayor que la máxima');
		}

		$dbRaw = DatabaseService::GetInstance()->GetDbConnectionRaw();
		if ($minDays === null && $minTemp === null && $maxTemp === null)
		{
			$dbRaw->prepare('DELETE FROM product_receiving_rules WHERE product_id = :pid')->execute([':pid' => $productId]);
			return true;
		}
		$dbRaw->prepare('
			INSERT INTO product_receiving_rules (product_id, min_remaining_shelf_life_days, min_temperature, max_temperature)
			VALUES (:pid, :d, :tmin, :tmax)
			ON CONFLICT(product_id) DO UPDATE SET min_remaining_shelf_life_days = :d2, min_temperature = :tmin2, max_temperature = :tmax2
		')->execute([':pid' => $productId, ':d' => $minDays, ':tmin' => $minTemp, ':tmax' => $maxTemp, ':d2' => $minDays, ':tmin2' => $minTemp, ':tmax2' => $maxTemp]);
		return true;
	}

	private function FetchOrder(int $orderId): array
	{
		$stmt = DatabaseService::GetInstance()->GetDbConnectionRaw()->prepare('
			SELECT po.*, sl.name AS supplier_name, b.name AS branch_name
			FROM procurement_orders po
			LEFT JOIN shopping_locations sl ON sl.id = po.supplier_id
			LEFT JOIN branches b ON b.id = po.branch_id
			WHERE po.id = :id
		');
		$stmt->execute([':id' => $orderId]);
		$order = $stmt->fetch(\PDO::FETCH_ASSOC);
		if (!$order)
		{
			throw new \Exception("La orden #{$orderId} no existe");
		}
		return $order;
	}

	// Regla explícita del producto; si no hay, porcentaje de la vida útil por defecto (RECEIVING_MIN_SHELF_LIFE_PCT)
	private function MinRemainingShelfLifeDays(array $item): ?int
	{
		if ($item['min_remaining_shelf_life_days'] !== null)
		{
			return intval($item['min_remaining_shelf_life_days']);
		}
		$pct = defined('GROCY_RECEIVING_MIN_SHELF_LIFE_PCT') ? floatval(GROCY_RECEIVING_MIN_SHELF_LIFE_PCT) : 0.5;
		$days = intval($item['default_best_before_days']);
		return ($pct > 0 && $days > 0) ? (int) floor($days * $pct) : null;
	}

	private function SupplierLeadTimeDays(int $supplierId): int
	{
		$stmt = DatabaseService::GetInstance()->GetDbConnectionRaw()->prepare('SELECT MAX(delivery_lead_days) FROM supplier_product_prices WHERE supplier_id = :sid');
		$stmt->execute([':sid' => $supplierId]);
		return max(1, intval($stmt->fetchColumn() ?: 2));
	}

	/**
	 * Precisión del pronóstico (backtest del mismo método del MRP): para cada una de las últimas $weeks semanas
	 * cerradas, pronóstico = media ponderada (4-3-2-1) de las 4 semanas previas × 7 vs venta real.
	 * WAPE = Σ|real − pronóstico| / Σ real · Sesgo = Σ(pronóstico − real) / Σ real (positivo = sobrepronóstico).
	 * Además, quiebres actuales: productos con venta en 28 días y sin stock libre en la sucursal.
	 */
	public function GetForecastAccuracy(?int $branchId = null, int $weeks = 4): array
	{
		$db = DatabaseService::GetInstance()->GetDbConnectionRaw();
		$weeks = max(1, min(12, $weeks));
		$totalDays = 7 * ($weeks + 4);
		$bp = $branchId ? [':bid' => $branchId] : [];
		$stmt = $db->prepare("SELECT si.product_id, CAST(julianday(DATE('now', 'localtime')) - julianday(DATE(s.created_timestamp)) AS INTEGER) AS ago, SUM(si.amount) AS units
			FROM sale_items si JOIN sales s ON s.id = si.sale_id
			WHERE s.status != 'cancelled' AND DATE(s.created_timestamp) >= DATE('now', 'localtime', '-$totalDays days') AND DATE(s.created_timestamp) < DATE('now', 'localtime')" . ($branchId ? ' AND s.branch_id = :bid' : '') . '
			GROUP BY si.product_id, ago');
		$stmt->execute($bp);
		// $weekly[product][k] = unidades de la semana k (1 = últimos 7 días cerrados)
		$weekly = [];
		foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $r)
		{
			$k = intdiv(intval($r['ago']) - 1, 7) + 1;
			$weekly[$r['product_id']][$k] = ($weekly[$r['product_id']][$k] ?? 0) + floatval($r['units']);
		}

		$names = $db->query('SELECT id, name FROM products')->fetchAll(\PDO::FETCH_KEY_PAIR);
		$sumAbs = $sumErr = $sumAct = 0.0;
		$byProduct = [];
		foreach ($weekly as $pid => $w)
		{
			$pAbs = $pErr = $pAct = 0.0;
			for ($k = 1; $k <= $weeks; $k++)
			{
				$forecast = 0.0;
				foreach ([1 => 4, 2 => 3, 3 => 2, 4 => 1] as $lag => $weight)
				{
					$forecast += ($w[$k + $lag] ?? 0) * $weight;
				}
				$forecast /= 10;
				$actual = $w[$k] ?? 0;
				$pAbs += abs($actual - $forecast);
				$pErr += $forecast - $actual;
				$pAct += $actual;
			}
			$sumAbs += $pAbs;
			$sumErr += $pErr;
			$sumAct += $pAct;
			if ($pAct > 0 || $pAbs > 0)
			{
				$byProduct[] = [
					'product_id' => intval($pid), 'product_name' => $names[$pid] ?? ('#' . $pid), 'actual' => round($pAct, 2),
					'wape_pct' => $pAct > 0 ? round($pAbs / $pAct * 100, 1) : null, 'bias_pct' => $pAct > 0 ? round($pErr / $pAct * 100, 1) : null,
					'abs_error' => round($pAbs, 2)
				];
			}
		}
		usort($byProduct, fn($a, $b) => $b['abs_error'] <=> $a['abs_error']);

		// Quiebres: venta en 28 días y stock libre (sin vencidos ni cuarentena) ≤ 0 en la sucursal
		$stockSql = "SELECT s.product_id, SUM(s.amount) FROM stock s JOIN locations l ON l.id = s.location_id
			WHERE IFNULL(l.is_transit, 0) = 0 AND (s.best_before_date IS NULL OR s.best_before_date >= DATE('now', 'localtime'))
			AND s.stock_id NOT IN (SELECT IFNULL(stock_id, '') FROM quarantine_items WHERE status = 'in_quarantine')" . ($branchId ? ' AND l.branch_id = ' . intval($branchId) : '') . ' GROUP BY s.product_id';
		$free = $db->query($stockSql)->fetchAll(\PDO::FETCH_KEY_PAIR);
		$stockouts = [];
		foreach ($weekly as $pid => $w)
		{
			$recent = ($w[1] ?? 0) + ($w[2] ?? 0) + ($w[3] ?? 0) + ($w[4] ?? 0);
			if ($recent > 0 && floatval($free[$pid] ?? 0) <= 0)
			{
				$stockouts[] = ['product_id' => intval($pid), 'product_name' => $names[$pid] ?? ('#' . $pid), 'units_28d' => round($recent, 2)];
			}
		}

		return [
			'weeks' => $weeks,
			'actual_units' => round($sumAct, 2),
			'wape_pct' => $sumAct > 0 ? round($sumAbs / $sumAct * 100, 1) : null,
			'bias_pct' => $sumAct > 0 ? round($sumErr / $sumAct * 100, 1) : null,
			'products' => array_slice($byProduct, 0, 10),
			'stockouts' => $stockouts
		];
	}
}
