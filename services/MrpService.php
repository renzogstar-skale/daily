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

		$branchStockFilter = $branchId ? ' AND l.branch_id = :bid' : '';
		$bp = $branchId ? [':bid' => $branchId] : [];
		$branchCostFilter = $branchId ? ' AND ls.branch_id = ' . intval($branchId) : '';

		// 1. Productos con su proveedor activo vigente más barato y costo real de la sucursal
		$products = $dbRaw->query("
			SELECT
				p.id AS product_id,
				p.sku AS product_sku,
				p.name AS product_name,
				p.min_stock_amount,
				p.max_stock_amount,
				p.default_best_before_days,
				qu.name AS qu_name,
				pg.name AS category_name,
				spp.supplier_id,
				spp.price AS supplier_price,
				spp.min_order_qty,
				IFNULL(spp.order_multiple, 1) AS order_multiple,
				COALESCE(spp.delivery_lead_days, sm.lead_time_days, smp.lead_time_days) AS lead_time_days,
				COALESCE(sup.name, supp.name) AS supplier_name,
				COALESCE(spp.supplier_id, CASE WHEN supp.active = 1 THEN p.shopping_location_id ELSE NULL END) AS resolved_supplier_id,
				COALESCE(sm.commercial_terms_verified, smp.commercial_terms_verified, 0) AS commercial_terms_verified,
				COALESCE(sm.payment_terms, smp.payment_terms) AS supplier_payment_terms,
				COALESCE(sm.min_order_amount, smp.min_order_amount, 0) AS supplier_min_order_amount,
				IFNULL((SELECT SUM(s.amount * s.price) / NULLIF(SUM(s.amount), 0)
						FROM stock s JOIN locations ls ON ls.id = s.location_id
						WHERE s.product_id = p.id AND s.price > 0 AND IFNULL(ls.is_transit, 0) = 0 {$branchCostFilter}),
					(SELECT plp.price FROM products_last_purchased plp WHERE plp.product_id = p.id)) AS stock_cost
			FROM products p
			LEFT JOIN quantity_units qu ON p.qu_id_stock = qu.id
			LEFT JOIN product_groups pg ON p.product_group_id = pg.id
			LEFT JOIN supplier_product_prices spp ON spp.id = (
				SELECT x.id FROM supplier_product_prices x
				JOIN shopping_locations slx ON slx.id = x.supplier_id AND slx.active = 1
				WHERE x.product_id = p.id AND x.is_current = 1
				  AND (x.valid_to IS NULL OR x.valid_to = '' OR x.valid_to >= DATE('now', 'localtime'))
				ORDER BY x.price ASC, IFNULL(x.delivery_lead_days, 99) ASC LIMIT 1
			)
			LEFT JOIN shopping_locations sup ON spp.supplier_id = sup.id AND sup.active = 1
			LEFT JOIN suppliers_meta sm ON spp.supplier_id = sm.id
			LEFT JOIN shopping_locations supp ON p.shopping_location_id = supp.id AND supp.active = 1
			LEFT JOIN suppliers_meta smp ON p.shopping_location_id = smp.id
			WHERE p.active = 1
			ORDER BY p.name ASC
		")->fetchAll(\PDO::FETCH_ASSOC);

		// 2. Lotes físicos en stock (FEFO, excluyendo ubicaciones de tránsito para evitar stock inflado)
		$stmtLots = $dbRaw->prepare("
			SELECT s.product_id, s.stock_id, s.amount, s.best_before_date
			FROM stock s
			JOIN locations l ON l.id = s.location_id
			WHERE s.amount > 0 AND IFNULL(l.is_transit, 0) = 0 {$branchStockFilter}
			ORDER BY s.product_id, s.best_before_date ASC
		");
		$stmtLots->execute($bp);
		$lots = [];
		while ($row = $stmtLots->fetch(\PDO::FETCH_ASSOC))
		{
			$lots[$row['product_id']][] = $row;
		}

		// 3. Cantidades en cuarentena (identificando stock_id para evitar doble resta si el lote ya venció)
		$stmtQ = $dbRaw->prepare("SELECT product_id, stock_id, amount FROM quarantine_items WHERE status = 'in_quarantine'" . ($branchId ? ' AND branch_id = :bid' : ''));
		$stmtQ->execute($bp);
		$quarantineByStockId = [];
		$quarantineUnlinked = [];
		$quarantineTotal = [];
		while ($qRow = $stmtQ->fetch(\PDO::FETCH_ASSOC))
		{
			$qpid = intval($qRow['product_id']);
			$qamt = floatval($qRow['amount']);
			$quarantineTotal[$qpid] = ($quarantineTotal[$qpid] ?? 0.0) + $qamt;
			if (!empty($qRow['stock_id']))
			{
				$quarantineByStockId[$qRow['stock_id']] = ($quarantineByStockId[$qRow['stock_id']] ?? 0.0) + $qamt;
			}
			else
			{
				$quarantineUnlinked[$qpid] = ($quarantineUnlinked[$qpid] ?? 0.0) + $qamt;
			}
		}

		// 4a. Entradas planificadas firmes: órdenes aprobadas/enviadas/confirmadas/parciales (NO borradores)
		$stmtOpen = $dbRaw->prepare("
			SELECT poi.product_id, SUM(MAX(0, COALESCE(NULLIF(poi.approved_amount, 0), poi.suggested_amount) - poi.received_amount - poi.rejected_amount)) AS amount
			FROM procurement_order_items poi
			JOIN procurement_orders po ON po.id = poi.order_id
			WHERE po.status IN ('approved', 'sent', 'confirmed', 'partially_received')" . ($branchId ? ' AND po.branch_id = :bid' : '') . '
			GROUP BY poi.product_id
		');
		$stmtOpen->execute($bp);
		$openReceipts = $stmtOpen->fetchAll(\PDO::FETCH_KEY_PAIR);

		// 4b. Órdenes borrador existentes (aviso anti-duplicidad sin inflar disponible neto)
		$stmtDraft = $dbRaw->prepare("
			SELECT poi.product_id, SUM(MAX(0, poi.suggested_amount)) AS amount
			FROM procurement_order_items poi
			JOIN procurement_orders po ON po.id = poi.order_id
			WHERE po.status = 'draft'" . ($branchId ? ' AND po.branch_id = :bid' : '') . '
			GROUP BY poi.product_id
		');
		$stmtDraft->execute($bp);
		$draftPending = $stmtDraft->fetchAll(\PDO::FETCH_KEY_PAIR);

		// 4c. Traslados entre sucursales en tránsito hacia la sucursal
		$incomingTransfers = [];
		try
		{
			$stmtTr = $dbRaw->prepare("
				SELECT sti.product_id, SUM(MAX(0, sti.sent_amount - IFNULL(sti.received_amount, 0))) AS amount
				FROM stock_transfer_items sti
				JOIN stock_transfers st ON st.id = sti.transfer_id
				WHERE st.status = 'in_transit'" . ($branchId ? ' AND st.to_branch_id = :bid' : '') . '
				GROUP BY sti.product_id
			');
			$stmtTr->execute($bp);
			$incomingTransfers = $stmtTr->fetchAll(\PDO::FETCH_KEY_PAIR);
		}
		catch (\Throwable $e) {}

		// 4d. Stock comprometido en pedidos confirmados / en preparación y mermas pendientes sin descontar
		$reservedOrders = [];
		try
		{
			$stmtCo = $dbRaw->prepare("
				SELECT coi.product_id, SUM(MAX(0, coi.amount)) AS amount
				FROM customer_order_items coi
				JOIN customer_orders co ON co.id = coi.order_id
				WHERE co.status IN ('confirmed', 'picking', 'picked', 'packed', 'dispatching')" . ($branchId ? ' AND co.branch_id = :bid' : '') . '
				GROUP BY coi.product_id
			');
			$stmtCo->execute($bp);
			$reservedOrders = $stmtCo->fetchAll(\PDO::FETCH_KEY_PAIR);
		}
		catch (\Throwable $e) {}

		$pendingWaste = [];
		try
		{
			$stmtPw = $dbRaw->prepare("
				SELECT product_id, SUM(amount) AS amount
				FROM waste_records
				WHERE status = 'pending_approval' AND IFNULL(inventory_deducted, 0) = 0" . ($branchId ? ' AND branch_id = :bid' : '') . '
				GROUP BY product_id
			');
			$stmtPw->execute($bp);
			$pendingWaste = $stmtPw->fetchAll(\PDO::FETCH_KEY_PAIR);
		}
		catch (\Throwable $e) {}

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
			$maxStock = isset($p['max_stock_amount']) && $p['max_stock_amount'] !== null ? floatval($p['max_stock_amount']) : 0.0;
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
			elseif (empty($p['commercial_terms_verified']) || trim(strval($p['supplier_payment_terms'] ?? '')) === '')
			{
				$warnings[] = 'Condiciones comerciales del proveedor incompletas';
			}

			// Demanda: media ponderada por semana (la más reciente pesa 4) y σ diaria
			$series = [];
			$activeSalesDays = 0;
			for ($d = 27; $d >= 0; $d--)
			{
				$day = date('Y-m-d', strtotime("-{$d} days"));
				$val = $dailySales[$pid][$day] ?? 0.0;
				if ($val > 0)
				{
					$activeSalesDays++;
				}
				$series[] = $val;
			}
			$saleUnits28d = array_sum($series);
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
			$mean = $saleUnits28d / count($series);
			$variance = 0.0;
			foreach ($series as $v)
			{
				$variance += ($v - $mean) ** 2;
			}
			$sigma = round(sqrt($variance / (count($series) - 1)), 2);
			$hasHistory = $saleUnits28d > 0;
			$forecastConfidence = !$hasHistory ? 'none' : (($activeSalesDays >= 5 && $saleUnits28d >= 5) ? 'high' : 'low');
			if ($forecastConfidence === 'low')
			{
				$warnings[] = "Pronóstico con datos limitados ({$activeSalesDays} días con venta en 28 d): revisar cobertura";
			}

			// Estacionalidad semanal: índice por día de la semana (promedio del día / promedio general) con las 4
			// observaciones de cada día; solo con volumen suficiente y acotado a [0.5, 2] para no amplificar ruido
			$dowIndex = array_fill(0, 7, 1.0);
			if ($mean > 0 && $saleUnits28d >= GROCY_MRP_SEASONALITY_MIN_UNITS)
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

			// Stock: vencido, cuarentena (sin doble resta de lotes vencidos), merma proyectada FEFO, compromisos y utilizable
			$stockTotal = 0.0;
			$expired = 0.0;
			$quarantinedOnValid = 0.0;
			$projectedSpoilage = 0.0;
			$absorbed = 0.0;
			foreach ($lots[$pid] ?? [] as $lot)
			{
				$amount = floatval($lot['amount']);
				$stockTotal += $amount;
				$lotQuar = min($amount, floatval($quarantineByStockId[$lot['stock_id']] ?? 0.0));
				if ($lot['best_before_date'] !== null && $lot['best_before_date'] !== '' && $lot['best_before_date'] < $today)
				{
					$expired += $amount;
					continue;
				}
				$quarantinedOnValid += $lotQuar;
				$freeLot = max(0.0, $amount - $lotQuar);
				if ($freeLot <= 0)
				{
					continue;
				}
				$daysLeft = ($lot['best_before_date'] !== null && $lot['best_before_date'] !== '')
					? (strtotime($lot['best_before_date']) - strtotime($today)) / 86400
					: 9999;
				if ($daysLeft <= $horizonDays)
				{
					// Lo que la demanda no alcanza a vender antes del vencimiento del lote (consumo FEFO)
					$sellable = max(0.0, $vmd * $daysLeft - $absorbed);
					$sold = min($freeLot, $sellable);
					$projectedSpoilage += $freeLot - $sold;
					$absorbed += $sold;
				}
				else
				{
					$absorbed += $freeLot;
				}
			}
			$quarantinedTotal = floatval($quarantineTotal[$pid] ?? 0);
			$quarantinedDeduction = $quarantinedOnValid + floatval($quarantineUnlinked[$pid] ?? 0);
			$reservedQty = floatval($reservedOrders[$pid] ?? 0);
			$pendingWasteQty = floatval($pendingWaste[$pid] ?? 0);
			$usable = max(0.0, $stockTotal - $expired - $quarantinedDeduction - $projectedSpoilage - $reservedQty - $pendingWasteQty);
			$openPo = floatval($openReceipts[$pid] ?? 0);
			$openTransfers = floatval($incomingTransfers[$pid] ?? 0);
			$open = $openPo + $openTransfers;
			$draftQty = floatval($draftPending[$pid] ?? 0);
			$netAvailable = $usable + $open;
			$spoilageCost = round(($projectedSpoilage + $expired) * $unitCost, 2);
			$totalProjectedSpoilageCost += $spoilageCost;

			// Stock de seguridad y punto de pedido
			$safetyStock = round(max($z * $sigma * sqrt($leadTimeDays), $minStock), 1);
			$rop = round($demandOver($leadTimeDays) + $safetyStock, 1);
			$daysCoverage = $vmd > 0 ? round($usable / $vmd, 1) : null;
			$projectedCoverageDays = $vmd > 0 ? round($netAvailable / $vmd, 1) : null;

			$status = 'normal';
			$suggestedOrder = 0.0;

			if (!$hasHistory)
			{
				$status = 'no_history';
				$noHistoryCount++;
				$physicalAvailable = max(0.0, $stockTotal - $expired - $quarantinedDeduction - $reservedQty - $pendingWasteQty) + $open;
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

				// Tope de stock máximo si está configurado
				if ($maxStock > 0 && ($netAvailable + $suggestedOrder) > $maxStock)
				{
					$suggestedOrder = max(0.0, $maxStock - $netAvailable);
					$warnings[] = 'Sugerencia acotada al stock máximo configurado';
				}

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
			$multiple = max(1.0, floatval($p['order_multiple']));
			$moq = floatval($p['min_order_qty'] ?? 0);
			if ($suggestedOrder > 0)
			{
				$suggestedOrder = ceil($suggestedOrder);
				$suggestedOrder = ceil($suggestedOrder / $multiple) * $multiple;
				if ($moq > 0 && $suggestedOrder < $moq)
				{
					$suggestedOrder = $moq;
					if ($shelfLife > 0 && $hasHistory && $moq > $vmd * $shelfLife * 0.8)
					{
						$warnings[] = 'El MOQ del proveedor supera lo vendible en la vida útil (riesgo de merma)';
					}
				}
			}
			if ($draftQty > 0 && $suggestedOrder > 0)
			{
				$warnings[] = "Ya existe orden borrador por {$draftQty} u. (pendiente de aprobación)";
			}
			if ($suggestedOrder > 0)
			{
				$reorderCount++;
			}

			$orderCost = round($suggestedOrder * $unitCost, 2);
			$totalEstimatedCost += $orderCost;

			$results[] = [
				'product_id' => $pid,
				'product_sku' => $p['product_sku'] ?? '',
				'product_name' => $p['product_name'],
				'category_name' => $p['category_name'] ?? 'General',
				'qu_name' => $p['qu_name'] ?? 'u',
				'current_stock' => round($stockTotal, 2),
				'expired_stock' => round($expired, 2),
				'quarantine_stock' => round($quarantinedTotal, 2),
				'reserved_stock' => round($reservedQty + $pendingWasteQty, 2),
				'projected_spoilage' => round($projectedSpoilage, 2),
				'projected_spoilage_cost' => $spoilageCost,
				'usable_stock' => round($usable, 2),
				'open_receipts' => round($open, 2),
				'incoming_transfers' => round($openTransfers, 2),
				'draft_pending' => round($draftQty, 2),
				'net_available' => round($netAvailable, 2),
				'min_stock' => $minStock,
				'max_stock' => $maxStock,
				'vmd' => $vmd,
				'sale_units_28d' => round($saleUnits28d, 2),
				'active_sales_days' => $activeSalesDays,
				'forecast_confidence' => $forecastConfidence,
				'dow_index' => $dowIndex,
				'seasonal' => max($dowIndex) - min($dowIndex) >= 0.3,
				'demand_sigma' => $sigma,
				'lead_time_days' => $leadTimeDays,
				'safety_stock' => $safetyStock,
				'rop' => $rop,
				'forecast_demand' => round($demandOver($leadTimeDays + $reviewDays), 1),
				'days_coverage' => $daysCoverage ?? 99.0,
				'days_coverage_display' => $daysCoverage,
				'projected_coverage_days' => $projectedCoverageDays,
				'has_coverage_data' => $daysCoverage !== null,
				'status' => $status,
				'suggested_order' => $suggestedOrder,
				'purchase_price' => $unitCost,
				'estimated_cost' => $orderCost,
				'best_before_days' => $shelfLife,
				'min_order_qty' => $moq,
				'order_multiple' => $multiple,
				'supplier_id' => $p['resolved_supplier_id'] !== null ? intval($p['resolved_supplier_id']) : null,
				'supplier_name' => $p['supplier_name'] ?? 'Sin proveedor',
				'commercial_terms_verified' => intval($p['commercial_terms_verified'] ?? 0) === 1,
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
	 * Prorratea costos de adquisición (flete + impuestos + otros − descuento) en el costo unitario puesto en bodega.
	 */
	private function RecomputeOrderAcquisitionTotals(\PDO $dbRaw, int $orderId): float
	{
		$ord = $dbRaw->query('SELECT freight_cost, tax_cost, other_acquisition_cost, discount_amount FROM procurement_orders WHERE id = ' . $orderId)->fetch(\PDO::FETCH_ASSOC);
		$freight = floatval($ord['freight_cost'] ?? 0);
		$tax = floatval($ord['tax_cost'] ?? 0);
		$other = floatval($ord['other_acquisition_cost'] ?? 0);
		$discount = floatval($ord['discount_amount'] ?? 0);

		$rows = $dbRaw->query('SELECT id, suggested_amount, approved_amount, unit_price FROM procurement_order_items WHERE order_id = ' . $orderId)->fetchAll(\PDO::FETCH_ASSOC);
		$merchTotal = 0.0;
		foreach ($rows as $r)
		{
			$q = ($r['approved_amount'] !== null && floatval($r['approved_amount']) > 0) ? floatval($r['approved_amount']) : floatval($r['suggested_amount']);
			$merchTotal += $q * floatval($r['unit_price']);
		}
		$extra = max(-$merchTotal, $freight + $tax + $other - $discount);
		$grandTotal = round(max(0.0, $merchTotal + $extra), 2);
		$updLine = $dbRaw->prepare('UPDATE procurement_order_items SET landed_unit_cost = :luc WHERE id = :id');
		foreach ($rows as $r)
		{
			$q = ($r['approved_amount'] !== null && floatval($r['approved_amount']) > 0) ? floatval($r['approved_amount']) : floatval($r['suggested_amount']);
			$lineMerch = $q * floatval($r['unit_price']);
			$share = ($merchTotal > 0 && $q > 0) ? ($lineMerch / $merchTotal) * $extra : 0.0;
			$landed = $q > 0 ? round(max(0.0, floatval($r['unit_price']) + ($share / $q)), 4) : floatval($r['unit_price']);
			$updLine->execute([':luc' => $landed, ':id' => intval($r['id'])]);
		}
		$dbRaw->prepare('UPDATE procurement_orders SET total_estimated_cost = :c WHERE id = :id')->execute([':c' => $grandTotal, ':id' => $orderId]);
		return $grandTotal;
	}

	private function LogSupplyEvent(\PDO $dbRaw, ?int $branchId, ?int $supplierId, ?int $orderId, ?int $receiptId, string $eventType, ?string $refCode, int $userId, string $summary, array $payload = []): void
	{
		try
		{
			$dbRaw->prepare("
				INSERT INTO procurement_supply_events (branch_id, supplier_id, order_id, receipt_id, event_type, reference_code, actor_user_id, summary, payload_json, created_at)
				VALUES (:bid, :sid, :oid, :rid, :et, :ref, :uid, :sum, :pay, datetime('now', 'localtime'))
			")->execute([
				':bid' => $branchId,
				':sid' => $supplierId,
				':oid' => $orderId,
				':rid' => $receiptId,
				':et' => $eventType,
				':ref' => $refCode,
				':uid' => $userId,
				':sum' => $summary,
				':pay' => empty($payload) ? null : json_encode($payload, JSON_UNESCAPED_UNICODE)
			]);
		}
		catch (\Throwable $e) {}
	}

	/**
	 * Create a draft Procurement Order from MRP suggestions or manual buyer request
	 */
	public function CreateProcurementOrder(array $data, int $userId)
	{
		$dbRaw = DatabaseService::GetInstance()->GetDbConnectionRaw();
		$branchId = max(1, intval($data['branch_id'] ?? 1));
		$supplierId = intval($data['supplier_id'] ?? 0);
		$rawItems = $data['items'] ?? [];
		$idemKey = trim(strval($data['idempotency_key'] ?? '')) ?: null;

		if ($idemKey !== null)
		{
			$stEx = $dbRaw->prepare('SELECT id, order_code, total_estimated_cost, status FROM procurement_orders WHERE idempotency_key = :ik');
			$stEx->execute([':ik' => $idemKey]);
			if ($existing = $stEx->fetch(\PDO::FETCH_ASSOC))
			{
				return [
					'id' => intval($existing['id']),
					'order_code' => $existing['order_code'],
					'total_estimated_cost' => floatval($existing['total_estimated_cost']),
					'status' => $existing['status'],
					'idempotent_reuse' => true,
					'message' => "Orden {$existing['order_code']} recuperada por clave de idempotencia"
				];
			}
		}

		if ($supplierId <= 0)
		{
			throw new \Exception("Asigne un proveedor al producto antes de generar la orden de reabastecimiento");
		}

		$supStmt = $dbRaw->prepare("
			SELECT sl.id, sl.name, sl.active, IFNULL(sm.lead_time_days, 0) AS lead_time_days,
				IFNULL(sm.min_order_amount, 0) AS min_order_amount, IFNULL(sm.payment_terms, '') AS payment_terms,
				IFNULL(sm.commercial_terms_verified, 0) AS commercial_terms_verified
			FROM shopping_locations sl
			LEFT JOIN suppliers_meta sm ON sm.id = sl.id
			WHERE sl.id = :sid
		");
		$supStmt->execute([':sid' => $supplierId]);
		$supplierRow = $supStmt->fetch(\PDO::FETCH_ASSOC);
		if (!$supplierRow || intval($supplierRow['active']) !== 1)
		{
			throw new \Exception("El proveedor #{$supplierId} no existe o está inactivo");
		}

		$enforceCommercial = !empty($data['enforce_commercial_terms']);
		$commercialWarnings = [];
		if (intval($supplierRow['lead_time_days']) <= 0 || trim(strval($supplierRow['payment_terms'])) === '')
		{
			$msg = "El proveedor {$supplierRow['name']} no tiene condiciones comerciales completas (lead time o condiciones de pago)";
			if ($enforceCommercial)
			{
				throw new \Exception($msg);
			}
			$commercialWarnings[] = $msg;
		}

		if (empty($rawItems))
		{
			throw new \Exception("Debe seleccionar al menos un producto para generar la orden de reabastecimiento");
		}

		// Fusionar SKUs duplicados dentro de la misma solicitud de OC
		$mergedItems = [];
		foreach ($rawItems as $item)
		{
			$pid = intval($item['product_id'] ?? 0);
			$qty = floatval($item['amount'] ?? 0);
			$price = floatval($item['unit_price'] ?? 0);
			if ($pid <= 0 || $qty <= 0)
			{
				continue;
			}
			if ($price < 0)
			{
				throw new \Exception("El precio unitario no puede ser negativo");
			}
			if (!isset($mergedItems[$pid]))
			{
				$mergedItems[$pid] = [
					'product_id' => $pid,
					'amount' => $qty,
					'unit_price' => $price,
					'vmd' => floatval($item['vmd'] ?? 0.0),
					'coverage' => floatval($item['coverage'] ?? 7.0)
				];
			}
			else
			{
				$mergedItems[$pid]['amount'] += $qty;
				if ($price > 0)
				{
					$mergedItems[$pid]['unit_price'] = $price;
				}
			}
		}

		if (empty($mergedItems))
		{
			throw new \Exception("Debe indicar al menos una línea con producto y cantidad mayor a cero");
		}

		if ($enforceCommercial)
		{
			$stMoq = $dbRaw->prepare('SELECT IFNULL(min_order_qty, 0) AS moq, IFNULL(order_multiple, 1) AS mult FROM supplier_product_prices WHERE supplier_id = :sid AND product_id = :pid AND is_current = 1 ORDER BY id DESC LIMIT 1');
			$merchCheck = 0.0;
			foreach ($mergedItems as $pid => &$mi)
			{
				$stMoq->execute([':sid' => $supplierId, ':pid' => $pid]);
				if ($pm = $stMoq->fetch(\PDO::FETCH_ASSOC))
				{
					$moq = floatval($pm['moq']);
					if ($moq > 0 && $mi['amount'] + 0.0001 < $moq)
					{
						throw new \Exception("Producto #{$pid}: la cantidad ({$mi['amount']}) es inferior al mínimo del proveedor (MOQ {$moq})");
					}
				}
				$merchCheck += $mi['amount'] * $mi['unit_price'];
			}
			unset($mi);
			$minAmt = floatval($supplierRow['min_order_amount'] ?? 0);
			if ($minAmt > 0 && $merchCheck + 0.009 < $minAmt)
			{
				throw new \Exception("El pedido ($" . number_format($merchCheck, 2) . ") no alcanza el pedido mínimo comercial del proveedor ($" . number_format($minAmt, 2) . ")");
			}
		}

		$freightCost = max(0.0, floatval($data['freight_cost'] ?? 0));
		$taxCost = max(0.0, floatval($data['tax_cost'] ?? 0));
		$otherCost = max(0.0, floatval($data['other_acquisition_cost'] ?? 0));
		$discountAmt = max(0.0, floatval($data['discount_amount'] ?? 0));
		$workflow = ($data['reception_workflow'] ?? 'standard') === 'two_step' ? 'two_step' : 'standard';

		$ownTx = false;
		if (!$dbRaw->inTransaction())
		{
			$dbRaw->beginTransaction();
			$ownTx = true;
		}
		try
		{
			// Evitar órdenes duplicadas cuando el llamador activa la verificación explícita anti-duplicado
			if (!empty($data['prevent_duplicate_open_draft']))
			{
				$pids = array_keys($mergedItems);
				$ph = implode(',', array_fill(0, count($pids), '?'));
				$dupStmt = $dbRaw->prepare("
					SELECT DISTINCT po.order_code, p.name AS product_name
					FROM procurement_orders po
					JOIN procurement_order_items poi ON poi.order_id = po.id
					JOIN products p ON p.id = poi.product_id
					WHERE po.branch_id = ? AND po.supplier_id = ? AND po.status IN ('draft', 'approved', 'sent', 'confirmed')
					  AND poi.product_id IN ({$ph})
					LIMIT 1
				");
				$dupStmt->execute(array_merge([$branchId, $supplierId], $pids));
				if ($dupRow = $dupStmt->fetch(\PDO::FETCH_ASSOC))
				{
					throw new \Exception("Compra duplicada bloqueada: ya existe la orden activa {$dupRow['order_code']} para {$dupRow['product_name']} con este proveedor");
				}
			}

			$orderCode = 'PO-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));

			$stmtOrder = $dbRaw->prepare("
				INSERT INTO procurement_orders (
					order_code, branch_id, supplier_id, status, generated_by, created_by_user_id,
					total_estimated_cost, freight_cost, tax_cost, other_acquisition_cost, discount_amount,
					reception_workflow, idempotency_key, created_at
				)
				VALUES (
					:order_code, :branch_id, :supplier_id, 'draft', :gen_by, :uid,
					0.0, :freight, :tax, :other, :disc, :wf, :ik, datetime('now')
				)
			");
			$stmtOrder->execute([
				':order_code' => $orderCode,
				':branch_id' => $branchId,
				':supplier_id' => $supplierId,
				':gen_by' => trim(strval($data['generated_by'] ?? 'mrp_engine')) ?: 'mrp_engine',
				':uid' => $userId,
				':freight' => $freightCost,
				':tax' => $taxCost,
				':other' => $otherCost,
				':disc' => $discountAmt,
				':wf' => $workflow,
				':ik' => $idemKey
			]);
			$orderId = intval($dbRaw->lastInsertId());

			// En borrador, approved_amount permanece NULL hasta que se apruebe explícitamente
			$stmtItem = $dbRaw->prepare("
				INSERT INTO procurement_order_items (order_id, product_id, suggested_amount, approved_amount, unit_price, vmd_daily_sales, days_coverage_projected)
				VALUES (:order_id, :product_id, :suggested_amount, NULL, :unit_price, :vmd, :coverage)
			");

			foreach ($mergedItems as $item)
			{
				$stmtItem->execute([
					':order_id' => $orderId,
					':product_id' => $item['product_id'],
					':suggested_amount' => $item['amount'],
					':unit_price' => $item['unit_price'],
					':vmd' => $item['vmd'],
					':coverage' => $item['coverage']
				]);
			}

			$totalCost = $this->RecomputeOrderAcquisitionTotals($dbRaw, $orderId);
			$this->LogSupplyEvent($dbRaw, $branchId, $supplierId, $orderId, null, 'po_created', $orderCode, $userId, "Borrador {$orderCode} creado con " . count($mergedItems) . " línea(s) por $" . number_format($totalCost, 2));

			if ($ownTx && $dbRaw->inTransaction())
			{
				$dbRaw->commit();
			}

			return [
				'id' => $orderId,
				'order_code' => $orderCode,
				'total_estimated_cost' => $totalCost,
				'status' => 'draft',
				'commercial_warnings' => $commercialWarnings,
				'message' => "Orden de compra {$orderCode} generada exitosamente en estado borrador"
			];
		}
		catch (\Exception $e)
		{
			if ($ownTx && $dbRaw->inTransaction())
			{
				$dbRaw->rollBack();
			}
			throw $e;
		}
	}

	/**
	 * Permite editar cantidades sugeridas, precios pactados y costos de adquisición mientras la orden sigue en borrador.
	 */
	public function UpdateDraftOrderItems(int $orderId, array $data, int $userId): array
	{
		$dbRaw = DatabaseService::GetInstance()->GetDbConnectionRaw();
		$order = $this->FetchOrder($orderId);
		if ($order['status'] !== 'draft')
		{
			throw new \Exception("Solo se pueden editar órdenes en estado borrador (estado actual: {$order['status']})");
		}

		$db = DatabaseService::GetInstance()->BeginWriteTransaction();
		try
		{
			if (isset($data['freight_cost']) || isset($data['tax_cost']) || isset($data['other_acquisition_cost']) || isset($data['discount_amount']))
			{
				$dbRaw->prepare("
					UPDATE procurement_orders
					SET freight_cost = :f, tax_cost = :t, other_acquisition_cost = :o, discount_amount = :d
					WHERE id = :id AND status = 'draft'
				")->execute([
					':f' => max(0.0, floatval($data['freight_cost'] ?? $order['freight_cost'] ?? 0)),
					':t' => max(0.0, floatval($data['tax_cost'] ?? $order['tax_cost'] ?? 0)),
					':o' => max(0.0, floatval($data['other_acquisition_cost'] ?? $order['other_acquisition_cost'] ?? 0)),
					':d' => max(0.0, floatval($data['discount_amount'] ?? $order['discount_amount'] ?? 0)),
					':id' => $orderId
				]);
			}

			$updItem = $dbRaw->prepare('UPDATE procurement_order_items SET suggested_amount = :qty, unit_price = :pr WHERE id = :iid AND order_id = :oid');
			foreach ($data['items'] ?? [] as $it)
			{
				$iid = intval($it['order_item_id'] ?? $it['id'] ?? 0);
				$qty = floatval($it['amount'] ?? $it['suggested_amount'] ?? 0);
				$price = floatval($it['unit_price'] ?? 0);
				if ($iid > 0 && $qty > 0 && $price >= 0)
				{
					$updItem->execute([':qty' => $qty, ':pr' => $price, ':iid' => $iid, ':oid' => $orderId]);
				}
			}

			$total = $this->RecomputeOrderAcquisitionTotals($dbRaw, $orderId);
			$this->LogSupplyEvent($dbRaw, intval($order['branch_id']), intval($order['supplier_id']), $orderId, null, 'po_draft_updated', $order['order_code'], $userId, "Borrador {$order['order_code']} ajustado (total $" . number_format($total, 2) . ")");
			$db->commit();

			return ['success' => true, 'order_id' => $orderId, 'total_estimated_cost' => $total];
		}
		catch (\Throwable $ex)
		{
			if ($db->inTransaction())
			{
				$db->rollBack();
			}
			throw $ex;
		}
	}

	/**
	 * Genera órdenes borrador desde el cálculo MRP bajo bloqueo de escritura y prevención anti-duplicados:
	 * una orden por proveedor omitiendo SKUs que ya tienen un borrador u orden abierta sin recibir.
	 */
	public function GenerateOrdersFromMrp(?int $branchId, int $userId): array
	{
		$targetBranch = ($branchId !== null && $branchId > 0) ? $branchId : 1;
		$db = DatabaseService::GetInstance()->BeginWriteTransaction();
		try
		{
			$dbRaw = DatabaseService::GetInstance()->GetDbConnectionRaw();
			$mrp = $this->CalculateMrp($targetBranch);

			// SKUs que ya tienen borrador abierto en esta sucursal para evitar generar borradores duplicados
			$stDup = $dbRaw->prepare("
				SELECT DISTINCT poi.product_id
				FROM procurement_order_items poi
				JOIN procurement_orders po ON po.id = poi.order_id
				WHERE po.branch_id = :bid AND po.status = 'draft'
			");
			$stDup->execute([':bid' => $targetBranch]);
			$existingDraftPids = array_flip(array_map('intval', $stDup->fetchAll(\PDO::FETCH_COLUMN)));

			$bySupplier = [];
			$skipped = [];
			$skippedDuplicates = [];
			foreach ($mrp['items'] as $item)
			{
				if ($item['suggested_order'] <= 0)
				{
					continue;
				}
				if (isset($existingDraftPids[intval($item['product_id'])]))
				{
					$skippedDuplicates[] = $item['product_name'];
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
				if (!empty($skippedDuplicates))
				{
					throw new \Exception('Los productos que requieren reposición ya cuentan con orden borrador pendiente de aprobación: ' . implode(', ', array_slice($skippedDuplicates, 0, 5)));
				}
				throw new \Exception(empty($skipped) ? 'No hay productos que requieran reposición' : 'Los productos a reponer no tienen proveedor asignado: ' . implode(', ', $skipped));
			}

			$orders = [];
			foreach ($bySupplier as $supplierId => $items)
			{
				$orders[] = $this->CreateProcurementOrder(['branch_id' => $targetBranch, 'supplier_id' => $supplierId, 'items' => $items], $userId);
			}
			$db->commit();

			$msg = count($orders) . ' orden(es) borrador generada(s)';
			if (!empty($skippedDuplicates))
			{
				$msg .= '; omitidos por borrador previo (anti-duplicado): ' . count($skippedDuplicates);
			}
			if (!empty($skipped))
			{
				$msg .= '; sin proveedor: ' . implode(', ', $skipped);
			}

			return [
				'orders' => $orders,
				'skipped_products' => $skipped,
				'skipped_duplicates' => $skippedDuplicates,
				'message' => $msg
			];
		}
		catch (\Throwable $e)
		{
			if ($db->inTransaction())
			{
				$db->rollBack();
			}
			throw $e;
		}
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
				IFNULL(NULLIF(TRIM(uc.first_name || ' ' || IFNULL(uc.last_name, '')), ''), uc.username) AS created_by_name,
				COUNT(poi.id) AS item_count,
				ROUND(IFNULL(SUM(COALESCE(NULLIF(poi.approved_amount, 0), poi.suggested_amount)), 0), 2) AS ordered_units_total,
				ROUND(IFNULL(SUM(poi.received_amount), 0), 2) AS received_units_total,
				ROUND(IFNULL(SUM(poi.rejected_amount), 0), 2) AS rejected_units_total,
				(SELECT COUNT(*) FROM procurement_receipts pr WHERE pr.order_id = po.id AND pr.reception_stage = 'admin_recorded') AS pending_physical_receipts
			FROM procurement_orders po
			LEFT JOIN shopping_locations sup ON po.supplier_id = sup.id
			LEFT JOIN branches b ON po.branch_id = b.id
			LEFT JOIN users u ON po.approved_by_user_id = u.id
			LEFT JOIN users uc ON po.created_by_user_id = uc.id
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
	public function ApproveProcurementOrder(int $orderId, int $userId, bool $enforceSegregation = false)
	{
		$dbRaw = DatabaseService::GetInstance()->GetDbConnectionRaw();
		$order = $this->FetchOrder($orderId);
		if ($order['status'] !== 'draft')
		{
			throw new \Exception("La orden #{$orderId} no existe o ya no está en estado borrador");
		}
		if (($enforceSegregation || (defined('GROCY_PROCUREMENT_STRICT_SEGREGATION') && GROCY_PROCUREMENT_STRICT_SEGREGATION))
			&& !empty($order['created_by_user_id']) && intval($order['created_by_user_id']) === $userId)
		{
			throw new \Exception('Segregación de funciones: quien creó la orden borrador no puede aprobarla');
		}

		$dbRaw->prepare('UPDATE procurement_order_items SET approved_amount = suggested_amount WHERE order_id = :id AND (approved_amount IS NULL OR approved_amount <= 0)')->execute([':id' => $orderId]);
		$total = $this->RecomputeOrderAcquisitionTotals($dbRaw, $orderId);

		$stmt = $dbRaw->prepare("
			UPDATE procurement_orders 
			SET status = 'approved', approved_by_user_id = :user_id, approved_at = datetime('now', 'localtime'), total_estimated_cost = :tot
			WHERE id = :id AND status = 'draft'
		");
		$stmt->execute([':user_id' => $userId, ':tot' => $total, ':id' => $orderId]);

		if ($stmt->rowCount() === 0)
		{
			throw new \Exception("La orden #{$orderId} no existe o ya no está en estado borrador");
		}
		$this->LogSupplyEvent($dbRaw, intval($order['branch_id']), intval($order['supplier_id']), $orderId, null, 'po_approved', $order['order_code'], $userId, "Orden {$order['order_code']} aprobada por $" . number_format($total, 2));

		return true;
	}

	/**
	 * Aprobación masiva respetando alcance de sucursales del aprobador.
	 */
	public function BulkApproveOrders(?int $branchId, int $userId, ?array $allowedBranches = null): int
	{
		$dbRaw = DatabaseService::GetInstance()->GetDbConnectionRaw();
		$sql = "SELECT id FROM procurement_orders WHERE status = 'draft'";
		$params = [];
		if ($branchId !== null && $branchId > 0)
		{
			$sql .= ' AND branch_id = :bid';
			$params[':bid'] = $branchId;
		}
		elseif ($allowedBranches !== null)
		{
			$bList = empty($allowedBranches) ? '0' : implode(',', array_map('intval', $allowedBranches));
			$sql .= " AND branch_id IN ({$bList})";
		}
		$stmt = $dbRaw->prepare($sql);
		$stmt->execute($params);
		$ids = $stmt->fetchAll(\PDO::FETCH_COLUMN);
		$approved = 0;
		foreach ($ids as $oid)
		{
			try
			{
				$this->ApproveProcurementOrder(intval($oid), $userId);
				$approved++;
			}
			catch (\Throwable $e) {}
		}
		return $approved;
	}

	/**
	 * Orden con sus líneas, pendientes de recibir, facturado previo, ubicaciones de la sucursal y reglas de recepción por producto
	 */
	public function GetProcurementOrderDetail(int $orderId): array
	{
		$dbRaw = DatabaseService::GetInstance()->GetDbConnectionRaw();
		$order = $this->FetchOrder($orderId);

		$stmt = $dbRaw->prepare("
			SELECT poi.*, p.name AS product_name, IFNULL(p.sku, '') AS product_sku,
				IFNULL(qu.name, 'u') AS qu_name, p.location_id AS product_location_id,
				p.default_best_before_days, r.min_remaining_shelf_life_days, r.min_temperature, r.max_temperature,
				COALESCE(NULLIF(poi.approved_amount, 0), poi.suggested_amount) AS ordered_amount,
				MAX(0, COALESCE(NULLIF(poi.approved_amount, 0), poi.suggested_amount) - poi.received_amount - poi.rejected_amount) AS pending_amount,
				IFNULL((
					SELECT SUM(ii.invoiced_amount)
					FROM supplier_invoice_items ii
					JOIN supplier_invoices si ON si.id = ii.invoice_id
					WHERE ii.order_item_id = poi.id AND si.status != 'rejected'
				), 0) AS invoiced_amount
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
			$item['ordered_amount'] = round(floatval($item['ordered_amount']), 4);
			$item['diff_amount'] = round($item['ordered_amount'] - floatval($item['received_amount']) - floatval($item['rejected_amount']), 4);
		}
		unset($item);

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
		$dbRaw = DatabaseService::GetInstance()->GetDbConnectionRaw();
		$order = $this->FetchOrder($orderId);
		if ($order['status'] !== 'approved')
		{
			throw new \Exception("Solo se puede enviar una orden aprobada (estado actual: {$order['status']})");
		}

		if (empty($expectedDeliveryDate))
		{
			$expectedDeliveryDate = date('Y-m-d', strtotime('+' . $this->SupplierLeadTimeDays($order['supplier_id']) . ' days'));
		}

		$stmt = $dbRaw->prepare("
			UPDATE procurement_orders SET status = 'sent', sent_at = datetime('now', 'localtime'), sent_by_user_id = :uid, expected_delivery_date = :edd
			WHERE id = :id
		");
		$stmt->execute([':uid' => $userId, ':edd' => $expectedDeliveryDate, ':id' => $orderId]);
		$this->LogSupplyEvent($dbRaw, intval($order['branch_id']), intval($order['supplier_id']), $orderId, null, 'po_sent', $order['order_code'], $userId, "Orden {$order['order_code']} enviada al proveedor (entrega esperada {$expectedDeliveryDate})");

		return true;
	}

	/**
	 * Confirmación formal del proveedor sobre una orden enviada (sent → confirmed):
	 * registra referencia de confirmación, contacto, fecha comprometida ajustada y cantidades confirmadas por línea.
	 */
	public function ConfirmProcurementOrder(int $orderId, array $data, int $userId): array
	{
		$dbRaw = DatabaseService::GetInstance()->GetDbConnectionRaw();
		$order = $this->FetchOrder($orderId);
		if (!in_array($order['status'], ['approved', 'sent'], true))
		{
			throw new \Exception("Solo se pueden confirmar órdenes aprobadas o enviadas (estado actual: {$order['status']})");
		}

		$contact = trim(strval($data['confirmed_by_supplier_contact'] ?? $data['contact_name'] ?? ''));
		$ref = trim(strval($data['supplier_confirmation_ref'] ?? $data['confirmation_ref'] ?? ''));
		$confDate = trim(strval($data['confirmed_delivery_date'] ?? $order['expected_delivery_date'] ?? ''));
		if ($confDate !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $confDate))
		{
			throw new \Exception('Fecha de entrega confirmada inválida (use AAAA-MM-DD)');
		}
		if ($confDate === '')
		{
			$confDate = date('Y-m-d', strtotime('+' . $this->SupplierLeadTimeDays(intval($order['supplier_id'])) . ' days'));
		}

		$db = DatabaseService::GetInstance()->BeginWriteTransaction();
		try
		{
			$dbRaw->prepare("
				UPDATE procurement_orders
				SET status = 'confirmed',
					confirmed_at = datetime('now', 'localtime'),
					confirmed_by_supplier_contact = :c,
					supplier_confirmation_ref = :r,
					confirmed_delivery_date = :cd,
					expected_delivery_date = :edd
				WHERE id = :id
			")->execute([
				':c' => $contact ?: null,
				':r' => $ref ?: ('ACK-' . $order['order_code']),
				':cd' => $confDate,
				':edd' => $confDate,
				':id' => $orderId
			]);

			$updLine = $dbRaw->prepare('UPDATE procurement_order_items SET confirmed_amount = :ca WHERE id = :iid AND order_id = :oid');
			if (!empty($data['items']) && is_array($data['items']))
			{
				foreach ($data['items'] as $li)
				{
					$iid = intval($li['order_item_id'] ?? $li['id'] ?? 0);
					$ca = max(0.0, floatval($li['confirmed_amount'] ?? 0));
					if ($iid > 0)
					{
						$updLine->execute([':ca' => $ca, ':iid' => $iid, ':oid' => $orderId]);
					}
				}
			}
			else
			{
				$dbRaw->prepare('UPDATE procurement_order_items SET confirmed_amount = COALESCE(NULLIF(approved_amount, 0), suggested_amount) WHERE order_id = :oid')->execute([':oid' => $orderId]);
			}

			$this->LogSupplyEvent($dbRaw, intval($order['branch_id']), intval($order['supplier_id']), $orderId, null, 'supplier_confirmed', $ref ?: $order['order_code'], $userId, "Proveedor confirmó {$order['order_code']} para entrega {$confDate}" . ($contact ? " ({$contact})" : ''));
			$db->commit();

			return [
				'success' => true,
				'order_id' => $orderId,
				'status' => 'confirmed',
				'confirmed_delivery_date' => $confDate,
				'supplier_confirmation_ref' => $ref ?: ('ACK-' . $order['order_code']),
				'message' => "Orden {$order['order_code']} confirmada por el proveedor para el {$confDate}"
			];
		}
		catch (\Throwable $ex)
		{
			if ($db->inTransaction())
			{
				$db->rollBack();
			}
			throw $ex;
		}
	}

	/**
	 * Cancela (borrador/aprobada sin recepciones) o cierra con faltante (enviada/confirmada/parcial). El pendiente deja de contar en el MRP.
	 */
	public function CloseProcurementOrder(int $orderId, int $userId, string $reason)
	{
		$dbRaw = DatabaseService::GetInstance()->GetDbConnectionRaw();
		$order = $this->FetchOrder($orderId);
		$reason = trim($reason);
		if ($reason === '')
		{
			throw new \Exception('Indique el motivo');
		}

		if (in_array($order['status'], ['draft', 'approved'], true))
		{
			$newStatus = 'cancelled';
		}
		elseif (in_array($order['status'], ['sent', 'confirmed', 'partially_received'], true))
		{
			$newStatus = 'closed';
		}
		else
		{
			throw new \Exception("La orden ya está {$order['status']}");
		}

		$stmt = $dbRaw->prepare("
			UPDATE procurement_orders SET status = :st, closed_at = datetime('now', 'localtime'), closed_by_user_id = :uid, close_reason = :reason
			WHERE id = :id
		");
		$stmt->execute([':st' => $newStatus, ':uid' => $userId, ':reason' => $reason, ':id' => $orderId]);
		$this->LogSupplyEvent($dbRaw, intval($order['branch_id']), intval($order['supplier_id']), $orderId, null, 'po_' . $newStatus, $order['order_code'], $userId, "Orden {$order['order_code']} marcada como {$newStatus}: {$reason}");

		return $newStatus;
	}

	/**
	 * Recepción contra orden (MIGO 101):
	 * - 'direct' (por defecto para compatibilidad): registra recepción administrativa + entrada física a stock_log y lotes.
	 * - 'administrative': registra únicamente el documento administrativo REC- (reception_stage = 'admin_recorded'),
	 *   incidencias de rechazo y entrega OTIF sin mover el inventario físico hasta confirmar puesta en stock físico.
	 */
	public function ReceiveProcurementOrder(int $orderId, array $data, int $userId): array
	{
		$mode = ($data['reception_mode'] ?? 'direct') === 'administrative' ? 'administrative' : 'direct';
		$db = DatabaseService::GetInstance()->BeginWriteTransaction();
		try
		{
			return $this->ReceiveProcurementOrderLocked($orderId, $data, $userId, $mode);
		}
		finally
		{
			if ($db->inTransaction())
			{
				$db->rollBack();
			}
		}
	}

	public function RecordAdministrativeReception(int $orderId, array $data, int $userId): array
	{
		$data['reception_mode'] = 'administrative';
		return $this->ReceiveProcurementOrder($orderId, $data, $userId);
	}

	/**
	 * Paso físico posterior a una recepción administrativa: ingresa las cantidades aceptadas de REC- al inventario real (stock/stock_log/lotes).
	 */
	public function ConfirmPhysicalReception(int $receiptId, int $userId, ?int $overrideLocationId = null): array
	{
		$db = DatabaseService::GetInstance()->BeginWriteTransaction();
		try
		{
			$dbRaw = DatabaseService::GetInstance()->GetDbConnectionRaw();
			$stR = $dbRaw->prepare("
				SELECT r.*, po.order_code
				FROM procurement_receipts r
				JOIN procurement_orders po ON po.id = r.order_id
				WHERE r.id = :id
			");
			$stR->execute([':id' => $receiptId]);
			$rec = $stR->fetch(\PDO::FETCH_ASSOC);
			if (!$rec)
			{
				throw new \Exception("Recepción #{$receiptId} no encontrada");
			}
			if (($rec['reception_stage'] ?? 'completed') !== 'admin_recorded')
			{
				throw new \Exception("La recepción {$rec['receipt_code']} ya fue ingresada al inventario físico (estado: {$rec['reception_stage']})");
			}

			$stLines = $dbRaw->prepare('SELECT * FROM procurement_receipt_items WHERE receipt_id = :rid ORDER BY id');
			$stLines->execute([':rid' => $receiptId]);
			$rItems = $stLines->fetchAll(\PDO::FETCH_ASSOC);

			$stockService = StockService::GetInstance();
			$transactionId = null;
			$today = date('Y-m-d');
			$unitsPosted = 0.0;

			foreach ($rItems as $ri)
			{
				$acc = floatval($ri['accepted_amount']);
				if ($acc <= 0)
				{
					continue;
				}
				$locId = $overrideLocationId ?: intval($ri['location_id'] ?? 0);
				if ($locId <= 0)
				{
					$locId = intval($dbRaw->query('SELECT location_id FROM products WHERE id = ' . intval($ri['product_id']))->fetchColumn());
				}
				$stockService->AddProduct(
					intval($ri['product_id']),
					$acc,
					$ri['best_before_date'] ?: null,
					StockService::TRANSACTION_TYPE_PURCHASE,
					$today,
					floatval($ri['unit_price'] ?? 0),
					$locId,
					intval($rec['supplier_id']),
					$transactionId,
					0,
					false,
					$rec['receipt_code'] . ' / ' . $rec['order_code'] . ' (Físico)'
				);
				$unitsPosted += $acc;
			}

			$dbRaw->prepare("
				UPDATE procurement_receipts
				SET reception_stage = 'physical_posted',
					physical_posted_at = datetime('now', 'localtime'),
					physical_posted_by_user_id = :uid,
					stock_transaction_id = :tx
				WHERE id = :id
			")->execute([':uid' => $userId, ':tx' => $transactionId, ':id' => $receiptId]);

			$this->LogSupplyEvent($dbRaw, intval($rec['branch_id']), intval($rec['supplier_id']), intval($rec['order_id']), $receiptId, 'physical_reception_posted', $rec['receipt_code'], $userId, "Movimiento físico confirmado para {$rec['receipt_code']} ({$unitsPosted} u. ingresadas a stock)");
			$db->commit();

			return [
				'success' => true,
				'receipt_id' => $receiptId,
				'receipt_code' => $rec['receipt_code'],
				'reception_stage' => 'physical_posted',
				'units_posted' => round($unitsPosted, 4),
				'stock_transaction_id' => $transactionId,
				'message' => "Movimiento físico de {$rec['receipt_code']} confirmado ({$unitsPosted} u. ingresadas al inventario)"
			];
		}
		catch (\Throwable $ex)
		{
			if ($db->inTransaction())
			{
				$db->rollBack();
			}
			throw $ex;
		}
	}

	private function ReceiveProcurementOrderLocked(int $orderId, array $data, int $userId, string $mode = 'direct'): array
	{
		$dbRaw = DatabaseService::GetInstance()->GetDbConnectionRaw();
		$detail = $this->GetProcurementOrderDetail($orderId);
		$order = $detail['order'];

		if (!in_array($order['status'], ['approved', 'sent', 'confirmed', 'partially_received'], true))
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

			$poPrice = floatval($item['unit_price']);
			$unitPrice = (isset($in['unit_price']) && $in['unit_price'] !== '') ? floatval($in['unit_price']) : $poPrice;
			if ($unitPrice < 0)
			{
				throw new \Exception("{$item['product_name']}: el precio unitario no puede ser negativo");
			}
			if ($poPrice > 0 && abs($unitPrice - $poPrice) / $poPrice > 0.25 && empty($in['allow_price_override']))
			{
				throw new \Exception("{$item['product_name']}: el precio recibido (\${$unitPrice}) varía más de 25 % respecto a la OC (\${$poPrice})");
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
				'price' => $unitPrice
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
			$stage = ($mode === 'administrative') ? 'admin_recorded' : 'completed';

			$stmtReceipt = $dbRaw->prepare("
				INSERT INTO procurement_receipts (
					receipt_code, order_id, supplier_id, branch_id, received_by_user_id,
					supplier_document, temperature_on_arrival, notes,
					reception_stage, admin_recorded_at, admin_recorded_by_user_id,
					physical_posted_at, physical_posted_by_user_id
				)
				VALUES (
					:code, :oid, :sid, :bid, :uid, :doc, :temp, :notes,
					:stage, datetime('now', 'localtime'), :uid2,
					CASE WHEN :stage2 = 'completed' THEN datetime('now', 'localtime') ELSE NULL END,
					CASE WHEN :stage3 = 'completed' THEN :uid3 ELSE NULL END
				)
			");
			$stmtReceipt->execute([
				':code' => $receiptCode,
				':oid' => $orderId,
				':sid' => $order['supplier_id'],
				':bid' => $order['branch_id'],
				':uid' => $userId,
				':doc' => trim($data['supplier_document'] ?? '') ?: null,
				':temp' => $temperature,
				':notes' => trim($data['notes'] ?? '') ?: null,
				':stage' => $stage,
				':uid2' => $userId,
				':stage2' => $stage,
				':stage3' => $stage,
				':uid3' => $userId
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
					if ($mode === 'direct')
					{
						$stockService->AddProduct($item['product_id'], $l['accepted'], $l['bbd'], StockService::TRANSACTION_TYPE_PURCHASE, $today, $l['price'], $l['location_id'], $order['supplier_id'], $transactionId, 0, false, $receiptCode . ' / ' . $order['order_code']);
					}
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
					':loc' => $l['location_id'],
					':price' => $l['price'],
					':inc' => $incidentId
				]);
				$stmtItem->execute([':acc' => $l['accepted'], ':rej' => $l['rejected'], ':id' => $item['id']]);
				$deliveredTotal += $l['delivered'];
				$acceptedTotal += $l['accepted'];
			}

			// Costo prorrateado de flete/adquisición en la recepción
			$poExtra = max(0.0, floatval($order['freight_cost'] ?? 0) + floatval($order['tax_cost'] ?? 0) + floatval($order['other_acquisition_cost'] ?? 0) - floatval($order['discount_amount'] ?? 0));
			$poSubtotal = max(0.01, floatval($order['total_estimated_cost']) - $poExtra);
			$freightAllocated = ($poExtra > 0 && $acceptedCost > 0) ? round(($acceptedCost / $poSubtotal) * $poExtra, 2) : 0.0;
			$landedTotal = round($acceptedCost + $freightAllocated, 2);

			// Estado de la orden según lo pendiente
			$stmtPending = $dbRaw->prepare('SELECT IFNULL(SUM(MAX(0, COALESCE(NULLIF(approved_amount, 0), suggested_amount) - received_amount - rejected_amount)), 0) FROM procurement_order_items WHERE order_id = :id');
			$stmtPending->execute([':id' => $orderId]);
			$pendingAfter = floatval($stmtPending->fetchColumn());
			$newStatus = $pendingAfter > 0.0001 ? 'partially_received' : 'received';
			$stmtStatus = $dbRaw->prepare("UPDATE procurement_orders SET status = :st, closed_at = CASE WHEN :st2 = 'received' THEN datetime('now', 'localtime') ELSE closed_at END WHERE id = :id");
			$stmtStatus->execute([':st' => $newStatus, ':st2' => $newStatus, ':id' => $orderId]);

			// Entrega para la evaluación OTIF del proveedor
			$expected = $order['confirmed_delivery_date'] ?: $order['expected_delivery_date'] ?: date('Y-m-d', strtotime(substr($order['approved_at'] ?? $order['created_at'], 0, 10) . ' +' . $this->SupplierLeadTimeDays($order['supplier_id']) . ' days'));
			$late = $today > $expected;
			$orderedTotal = array_sum(array_map(fn($i) => floatval($i['pending_amount']), array_filter($detail['items'], fn($i) => $i['pending_amount'] > 0)));
			$deliveryStatus = $late ? 'delayed' : ($pendingAfter > 0.0001 ? 'partial' : 'on_time');
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

			$stmtUpd = $dbRaw->prepare('UPDATE procurement_receipts SET stock_transaction_id = :tx, delivery_id = :did, total_accepted_cost = :acc, total_rejected_cost = :rej, freight_allocated = :fa, landed_total_cost = :ltc WHERE id = :id');
			$stmtUpd->execute([':tx' => $transactionId, ':did' => $deliveryId, ':acc' => round($acceptedCost, 2), ':rej' => round($rejectedCost, 2), ':fa' => $freightAllocated, ':ltc' => $landedTotal, ':id' => $receiptId]);

			// Trazabilidad: lote del proveedor ligado a cada entrada de stock creada en modo directo
			if ($mode === 'direct' && $transactionId)
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

			$eventDesc = $mode === 'administrative'
				? "Recepción administrativa {$receiptCode} registrada ($" . number_format($acceptedCost, 2) . ") — pendiente de ingreso físico a bodega"
				: "Recepción {$receiptCode} completada e ingresada a inventario ($" . number_format($acceptedCost, 2) . ")";
			$this->LogSupplyEvent($dbRaw, intval($order['branch_id']), intval($order['supplier_id']), $orderId, $receiptId, $mode === 'administrative' ? 'admin_reception_recorded' : 'direct_reception_completed', $receiptCode, $userId, $eventDesc);

			$dbRaw->commit();
		}
		catch (\Exception $e)
		{
			$dbRaw->rollBack();
			throw $e;
		}

		$msg = $mode === 'administrative'
			? "Recepción administrativa {$receiptCode} registrada ($" . number_format($acceptedCost, 2) . "): pendiente confirmar ingreso físico al inventario."
			: "Recepción {$receiptCode} registrada: $" . number_format($acceptedCost, 2) . ' ingresado al stock' . ($rejectedCost > 0 ? ', $' . number_format($rejectedCost, 2) . ' rechazado (incidencia abierta con el proveedor)' : '');

		return [
			'id' => $receiptId,
			'receipt_id' => $receiptId,
			'receipt_code' => $receiptCode,
			'reception_stage' => $stage,
			'order_status' => $newStatus,
			'accepted_cost' => round($acceptedCost, 2),
			'rejected_cost' => round($rejectedCost, 2),
			'freight_allocated' => $freightAllocated,
			'landed_total_cost' => $landedTotal,
			'stock_transaction_id' => $transactionId,
			'message' => $msg
		];
	}

	/**
	 * Recepciones registradas (documento REC-) con etapa administrativa/física y número de orden.
	 */
	public function GetReceipts(?int $branchId = null, int $limit = 50): array
	{
		$dbRaw = DatabaseService::GetInstance()->GetDbConnectionRaw();
		$stmt = $dbRaw->prepare("
			SELECT r.*, po.order_code, sl.name AS supplier_name,
				IFNULL(NULLIF(TRIM(u.first_name || ' ' || IFNULL(u.last_name, '')), ''), u.username) AS received_by_name,
				IFNULL(NULLIF(TRIM(up.first_name || ' ' || IFNULL(up.last_name, '')), ''), up.username) AS physical_posted_by_name,
				(SELECT COUNT(*) FROM procurement_receipt_items ri WHERE ri.receipt_id = r.id AND ri.rejected_amount > 0) AS rejected_lines,
				(SELECT IFNULL(SUM(ri.accepted_amount), 0) FROM procurement_receipt_items ri WHERE ri.receipt_id = r.id) AS accepted_units_total
			FROM procurement_receipts r
			JOIN procurement_orders po ON po.id = r.order_id
			LEFT JOIN shopping_locations sl ON sl.id = r.supplier_id
			LEFT JOIN users u ON u.id = r.received_by_user_id
			LEFT JOIN users up ON up.id = r.physical_posted_by_user_id
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
		$db = DatabaseService::GetInstance()->GetDbConnectionRaw();
		$stmt = $db->prepare('SELECT MAX(delivery_lead_days) FROM supplier_product_prices WHERE supplier_id = :sid AND is_current = 1');
		$stmt->execute([':sid' => $supplierId]);
		$lt = intval($stmt->fetchColumn() ?: 0);
		if ($lt <= 0)
		{
			$stM = $db->prepare('SELECT lead_time_days FROM suppliers_meta WHERE id = :sid');
			$stM->execute([':sid' => $supplierId]);
			$lt = intval($stM->fetchColumn() ?: 2);
		}
		return max(1, $lt);
	}

	/**
	 * Registra solicitud de cotización formal (RFQ) sin DDL en controladores.
	 */
	public function RequestQuotation(array $data, int $userId): array
	{
		$db = DatabaseService::GetInstance()->GetDbConnectionRaw();
		$supplierId = intval($data['supplier_id'] ?? 0);
		$branchId = max(1, intval($data['branch_id'] ?? 1));
		$title = trim(strval($data['title'] ?? 'Solicitud de Cotización Formal')) ?: 'Solicitud de Cotización Formal';
		$deadline = trim(strval($data['deadline_date'] ?? '')) ?: date('Y-m-d', strtotime('+3 days'));
		$notes = trim(strval($data['notes'] ?? ''));
		$items = is_array($data['items'] ?? null) ? $data['items'] : [];

		if ($supplierId <= 0)
		{
			throw new \Exception('Debe seleccionar un proveedor válido.');
		}
		$sup = $db->prepare('SELECT name, active FROM shopping_locations WHERE id = :sid');
		$sup->execute([':sid' => $supplierId]);
		$supRow = $sup->fetch(\PDO::FETCH_ASSOC);
		if (!$supRow || intval($supRow['active']) !== 1)
		{
			throw new \Exception('El proveedor seleccionado no existe o está inactivo.');
		}

		$rfqCode = 'RFQ-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(2)));
		$db->prepare("
			INSERT INTO procurement_rfqs (rfq_code, supplier_id, branch_id, title, items_json, status, notes, deadline_date, created_by_user_id, created_at)
			VALUES (:code, :sid, :bid, :title, :items, 'Enviada', :notes, :deadline, :uid, datetime('now', 'localtime'))
		")->execute([
			':code' => $rfqCode,
			':sid' => $supplierId,
			':bid' => $branchId,
			':title' => $title,
			':items' => json_encode($items, JSON_UNESCAPED_UNICODE),
			':notes' => $notes,
			':deadline' => $deadline,
			':uid' => $userId
		]);
		$rfqId = intval($db->lastInsertId());
		$this->LogSupplyEvent($db, $branchId, $supplierId, null, null, 'rfq_sent', $rfqCode, $userId, "RFQ {$rfqCode} emitida a {$supRow['name']}: {$title}");

		return [
			'success' => true,
			'rfq_id' => $rfqId,
			'rfq_code' => $rfqCode,
			'message' => "Solicitud de cotización {$rfqCode} generada y registrada para el proveedor."
		];
	}

	/**
	 * Resuelve una incidencia de proveedor con validación de sucursal y auditoría.
	 */
	public function ResolveIncident(int $incidentId, array $data, int $userId, ?array $allowedBranches = null): array
	{
		$db = DatabaseService::GetInstance()->GetDbConnectionRaw();
		$st = $db->prepare('SELECT * FROM supplier_incidents WHERE id = :id');
		$st->execute([':id' => $incidentId]);
		$inc = $st->fetch(\PDO::FETCH_ASSOC);
		if (!$inc)
		{
			throw new \Exception("Incidencia #{$incidentId} no encontrada");
		}
		if ($allowedBranches !== null && !in_array(intval($inc['branch_id']), $allowedBranches, true))
		{
			throw new \Exception('La incidencia pertenece a otra sucursal');
		}

		$notes = trim(strval($data['resolution_notes'] ?? $data['notes'] ?? 'Incidencia resuelta y conciliada con el proveedor'));
		$costImpact = max(0.0, floatval($data['cost_impact'] ?? $inc['cost_impact'] ?? 0));
		$status = in_array($data['status'] ?? 'resolved', ['resolved', 'credited', 'closed'], true) ? $data['status'] : 'resolved';

		$db->prepare('UPDATE supplier_incidents SET status = :status, resolution_notes = :notes, cost_impact = :cost WHERE id = :id')
			->execute([':status' => $status, ':notes' => $notes, ':cost' => $costImpact, ':id' => $incidentId]);

		$this->LogSupplyEvent($db, intval($inc['branch_id']), intval($inc['supplier_id']), null, null, 'incident_resolved', 'INC-' . $incidentId, $userId, "Incidencia #{$incidentId} resuelta: {$notes}");

		return [
			'success' => true,
			'incident_id' => $incidentId,
			'status' => $status,
			'message' => "Incidencia #{$incidentId} resuelta formalmente con el proveedor."
		];
	}

	/**
	 * Simulador interactivo de demanda (what-if solo lectura):
	 * evalúa el impacto de variaciones en ventas (+/− %), lead time y nivel de servicio sobre ROP, SS, inversión y riesgo de quiebre.
	 */
	public function SimulateDemand(?int $branchId = 1, array $params = []): array
	{
		$horizon = max(1, min(60, intval($params['horizon_days'] ?? 14)));
		$demandMult = max(0.2, min(5.0, floatval($params['demand_multiplier'] ?? 1.0)));
		$ltDelta = max(-10, min(30, intval($params['lead_time_delta'] ?? 0)));
		$serviceLevel = max(0.80, min(0.99, floatval($params['service_level'] ?? (defined('GROCY_MRP_SERVICE_LEVEL') ? floatval(GROCY_MRP_SERVICE_LEVEL) : 0.95))));
		$zSim = $this->ServiceLevelZ($serviceLevel);
		$reviewDays = defined('GROCY_MRP_REVIEW_PERIOD_DAYS') ? intval(GROCY_MRP_REVIEW_PERIOD_DAYS) : 7;
		$categoryFilter = trim(strval($params['category_name'] ?? ''));

		$base = $this->CalculateMrp($branchId, $horizon);
		$simItems = [];
		$baseCost = 0.0;
		$simCost = 0.0;
		$baseReorder = 0;
		$simReorder = 0;
		$baseUnits = 0.0;
		$simUnits = 0.0;

		foreach ($base['items'] as $it)
		{
			if ($categoryFilter !== '' && strcasecmp($it['category_name'], $categoryFilter) !== 0)
			{
				continue;
			}
			$vmdSim = round($it['vmd'] * $demandMult, 2);
			$ltSim = max(1, intval($it['lead_time_days']) + $ltDelta);
			$sigmaSim = round($it['demand_sigma'] * sqrt($demandMult), 2);
			$ssSim = round(max($zSim * $sigmaSim * sqrt($ltSim), floatval($it['min_stock'])), 1);
			$baseForecastFactor = ($it['vmd'] > 0 && ($it['lead_time_days'] + $reviewDays) > 0)
				? ($it['forecast_demand'] / max(0.001, $it['vmd'] * ($it['lead_time_days'] + $reviewDays)))
				: 1.0;
			$ropSim = round(($vmdSim * $ltSim * $baseForecastFactor) + $ssSim, 1);
			$forecastSim = round($vmdSim * ($ltSim + $reviewDays) * $baseForecastFactor, 1);

			$netAvail = floatval($it['net_available']);
			$sugSim = 0.0;
			if ($it['status'] === 'no_history')
			{
				$sugSim = floatval($it['suggested_order']);
			}
			elseif ($netAvail <= $ropSim)
			{
				$sugSim = max(0.0, $forecastSim + $ssSim - $netAvail);
				$shelfLife = intval($it['best_before_days']);
				if ($shelfLife > 0 && $vmdSim > 0)
				{
					$sugSim = min($sugSim, floor($vmdSim * $shelfLife * 0.8));
				}
				if ($sugSim > 0)
				{
					$sugSim = ceil($sugSim);
					$mult = max(1.0, floatval($it['order_multiple'] ?? 1));
					$sugSim = ceil($sugSim / $mult) * $mult;
					$moq = floatval($it['min_order_qty'] ?? 0);
					if ($moq > 0 && $sugSim < $moq)
					{
						$sugSim = $moq;
					}
				}
			}

			$price = floatval($it['purchase_price']);
			$costBase = floatval($it['estimated_cost']);
			$costSim = round($sugSim * $price, 2);
			$baseCost += $costBase;
			$simCost += $costSim;
			$baseUnits += floatval($it['suggested_order']);
			$simUnits += $sugSim;
			if ($it['suggested_order'] > 0) $baseReorder++;
			if ($sugSim > 0) $simReorder++;

			$covSim = $vmdSim > 0 ? round(floatval($it['usable_stock']) / $vmdSim, 1) : null;
			$simItems[] = [
				'product_id' => $it['product_id'],
				'product_name' => $it['product_name'],
				'category_name' => $it['category_name'],
				'base_vmd' => $it['vmd'],
				'sim_vmd' => $vmdSim,
				'base_rop' => $it['rop'],
				'sim_rop' => $ropSim,
				'base_safety_stock' => $it['safety_stock'],
				'sim_safety_stock' => $ssSim,
				'base_coverage' => $it['days_coverage_display'],
				'sim_coverage' => $covSim,
				'base_suggested' => floatval($it['suggested_order']),
				'sim_suggested' => $sugSim,
				'delta_units' => round($sugSim - floatval($it['suggested_order']), 2),
				'delta_cost' => round($costSim - $costBase, 2)
			];
		}

		usort($simItems, fn($a, $b) => abs($b['delta_cost']) <=> abs($a['delta_cost']));

		return [
			'params' => [
				'branch_id' => $branchId,
				'horizon_days' => $horizon,
				'demand_multiplier' => $demandMult,
				'lead_time_delta' => $ltDelta,
				'service_level' => $serviceLevel,
				'category_name' => $categoryFilter ?: 'Todas'
			],
			'summary' => [
				'base_investment' => round($baseCost, 2),
				'sim_investment' => round($simCost, 2),
				'delta_investment' => round($simCost - $baseCost, 2),
				'base_units' => round($baseUnits, 2),
				'sim_units' => round($simUnits, 2),
				'delta_units' => round($simUnits - $baseUnits, 2),
				'base_reorder_skus' => $baseReorder,
				'sim_reorder_skus' => $simReorder
			],
			'items' => array_slice($simItems, 0, 25)
		];
	}

	/**
	 * Pronóstico de demanda y necesidades de abastecimiento agregado por sucursal activa.
	 */
	public function GetBranchDemandForecast(int $horizonDays = 14): array
	{
		$db = DatabaseService::GetInstance()->GetDbConnectionRaw();
		$branches = $db->query('SELECT id, name FROM branches WHERE active = 1 ORDER BY id')->fetchAll(\PDO::FETCH_ASSOC);
		$rows = [];
		foreach ($branches as $b)
		{
			$bid = intval($b['id']);
			$calc = $this->CalculateMrp($bid, $horizonDays);
			$vmdTotal = 0.0;
			$forecastTotal = 0.0;
			$covSum = 0.0;
			$covCnt = 0;
			$stockouts = 0;
			$lowConf = 0;
			foreach ($calc['items'] as $it)
			{
				$vmdTotal += floatval($it['vmd']);
				$forecastTotal += floatval($it['forecast_demand']);
				if ($it['usable_stock'] <= 0 && $it['vmd'] > 0) $stockouts++;
				if (($it['forecast_confidence'] ?? 'none') === 'low') $lowConf++;
				if ($it['days_coverage_display'] !== null)
				{
					$covSum += min(60.0, floatval($it['days_coverage_display']));
					$covCnt++;
				}
			}
			$rows[] = [
				'branch_id' => $bid,
				'branch_name' => $b['name'],
				'daily_demand_units' => round($vmdTotal, 1),
				'horizon_demand_units' => round($forecastTotal, 1),
				'reorder_needed_count' => $calc['summary']['reorder_needed_count'],
				'overstock_risk_count' => $calc['summary']['overstock_risk_count'],
				'suggested_investment' => $calc['summary']['total_suggested_order_cost'],
				'avg_coverage_days' => $covCnt > 0 ? round($covSum / $covCnt, 1) : null,
				'stockouts' => $stockouts,
				'low_confidence_skus' => $lowConf
			];
		}
		return $rows;
	}

	/**
	 * Recomendaciones de transferencia entre sucursales (red de tiendas):
	 * empareja sucursales con excedente utilizable sobre ROP vs sucursales con quiebre o necesidad de reposición.
	 */
	public function GetTransferRecommendations(?int $targetBranchId = null, int $horizonDays = 14): array
	{
		$db = DatabaseService::GetInstance()->GetDbConnectionRaw();
		$branches = $db->query('SELECT id, name FROM branches WHERE active = 1 ORDER BY id')->fetchAll(\PDO::FETCH_KEY_PAIR);
		if (count($branches) < 2)
		{
			return [];
		}

		$byProduct = [];
		foreach ($branches as $bid => $bname)
		{
			$calc = $this->CalculateMrp(intval($bid), $horizonDays);
			foreach ($calc['items'] as $it)
			{
				$byProduct[$it['product_id']][] = $it + ['branch_id' => intval($bid), 'branch_name' => $bname];
			}
		}

		$recs = [];
		foreach ($byProduct as $pid => $branchItems)
		{
			$surplus = [];
			$deficit = [];
			foreach ($branchItems as $bi)
			{
				if ($bi['usable_stock'] > ($bi['rop'] * 1.35) && $bi['status'] !== 'reorder_needed' && $bi['usable_stock'] >= 5)
				{
					$excess = floor(max(0.0, $bi['usable_stock'] - max($bi['rop'] * 1.15, $bi['safety_stock'] * 1.5)));
					if ($excess >= 2)
					{
						$surplus[] = $bi + ['excess_qty' => $excess];
					}
				}
				if (($bi['status'] === 'reorder_needed' || $bi['suggested_order'] > 0) && ($targetBranchId === null || $bi['branch_id'] === $targetBranchId))
				{
					$need = ceil(max(1.0, $bi['suggested_order']));
					$deficit[] = $bi + ['need_qty' => $need];
				}
			}

			if (empty($surplus) || empty($deficit))
			{
				continue;
			}
			usort($surplus, fn($a, $b) => $b['excess_qty'] <=> $a['excess_qty']);
			usort($deficit, fn($a, $b) => ($a['usable_stock'] <=> $b['usable_stock']));

			foreach ($deficit as $def)
			{
				foreach ($surplus as &$src)
				{
					if ($src['branch_id'] === $def['branch_id'] || $src['excess_qty'] < 2)
					{
						continue;
					}
					$qty = min($def['need_qty'], $src['excess_qty']);
					if ($qty < 1)
					{
						continue;
					}
					$unitCost = floatval($def['purchase_price'] ?: $src['purchase_price'] ?: 0);
					$recs[] = [
						'product_id' => intval($pid),
						'product_name' => $def['product_name'],
						'qu_name' => $def['qu_name'],
						'from_branch_id' => $src['branch_id'],
						'from_branch_name' => $src['branch_name'],
						'to_branch_id' => $def['branch_id'],
						'to_branch_name' => $def['branch_name'],
						'from_usable' => $src['usable_stock'],
						'to_usable' => $def['usable_stock'],
						'from_coverage' => $src['days_coverage_display'],
						'to_coverage' => $def['days_coverage_display'],
						'recommended_qty' => $qty,
						'unit_cost' => $unitCost,
						'estimated_savings' => round($qty * $unitCost, 2),
						'reason' => "Excedente en {$src['branch_name']} ({$src['usable_stock']} {$def['qu_name']}) cubre déficit en {$def['branch_name']} (ROP {$def['rop']})"
					];
					$src['excess_qty'] -= $qty;
					break;
				}
				unset($src);
			}
		}

		usort($recs, fn($a, $b) => $b['estimated_savings'] <=> $a['estimated_savings']);
		return array_slice($recs, 0, 30);
	}

	/**
	 * Ejecuta una recomendación de transferencia entre sucursales mediante StockTransferService.
	 */
	public function ExecuteRecommendedTransfer(int $fromBranchId, int $toBranchId, int $productId, float $qty, int $userId): array
	{
		if ($fromBranchId <= 0 || $toBranchId <= 0 || $fromBranchId === $toBranchId || $productId <= 0 || $qty <= 0)
		{
			throw new \Exception('Parámetros inválidos para la transferencia recomendada');
		}
		$db = DatabaseService::GetInstance()->GetDbConnectionRaw();
		$fromLoc = intval($db->query("SELECT id FROM locations WHERE active = 1 AND IFNULL(is_transit, 0) = 0 AND branch_id = {$fromBranchId} ORDER BY id LIMIT 1")->fetchColumn());
		$toLoc = intval($db->query("SELECT id FROM locations WHERE active = 1 AND IFNULL(is_transit, 0) = 0 AND branch_id = {$toBranchId} ORDER BY id LIMIT 1")->fetchColumn());
		if ($fromLoc <= 0 || $toLoc <= 0)
		{
			throw new \Exception('No existen ubicaciones activas configuradas en origen y destino');
		}
		$res = StockTransferService::GetInstance()->CreateTransfer([
			'from_location_id' => $fromLoc,
			'to_location_id' => $toLoc,
			'notes' => 'Transferencia sugerida por Planificación MRP para equilibrar red',
			'items' => [['product_id' => $productId, 'amount' => $qty]]
		], $userId);
		$this->LogSupplyEvent($db, $toBranchId, null, null, null, 'transfer_recommended_executed', $res['transfer_code'] ?? null, $userId, "Traslado intersucursal {$res['transfer_code']} enviado ({$qty} u. de sucursal #{$fromBranchId} a #{$toBranchId})");
		return $res;
	}

	/**
	 * Entregas pendientes y atrasadas con desglose de estado y días de atraso.
	 */
	public function GetPendingDeliveries(?int $branchId = null): array
	{
		$orders = $this->GetProcurementOrders($branchId);
		$today = date('Y-m-d');
		$list = [];
		foreach ($orders as $o)
		{
			if (!in_array($o->status, ['approved', 'sent', 'confirmed', 'partially_received'], true))
			{
				continue;
			}
			$orderedUnits = round(floatval($o->ordered_units_total ?? 0), 2);
			$receivedUnits = round(floatval($o->received_units_total ?? 0), 2);
			$pendingUnits = round(max(0.0, $orderedUnits - $receivedUnits), 2);
			$edd = $o->confirmed_delivery_date ?: $o->expected_delivery_date;
			$diff = $edd ? intval((strtotime($edd) - strtotime($today)) / 86400) : null;
			$list[] = [
				'id' => intval($o->id),
				'order_code' => $o->order_code,
				'branch_id' => intval($o->branch_id),
				'branch_name' => $o->branch_name ?? '',
				'supplier_id' => intval($o->supplier_id),
				'supplier_name' => $o->supplier_name ?? '',
				'status' => $o->status,
				'ordered_units' => $orderedUnits,
				'received_units' => $receivedUnits,
				'pending_units' => $pendingUnits,
				'effective_eta' => $edd,
				'days_until_delivery' => $diff,
				'is_overdue' => ($diff !== null && $diff < 0 && in_array($o->status, ['sent', 'confirmed', 'partially_received'], true)),
				'total_estimated_cost' => round(floatval($o->total_estimated_cost ?? 0), 2),
				'pending_physical_receipts' => intval($o->pending_physical_receipts ?? 0)
			];
		}
		return $list;
	}

	/**
	 * Historial consolidado de abastecimiento (órdenes, confirmaciones, recepciones admin/físicas, facturas y cambios).
	 */
	public function GetSupplyHistory(?int $branchId = null, ?int $supplierId = null, int $limit = 60): array
	{
		$db = DatabaseService::GetInstance()->GetDbConnectionRaw();
		$events = [];
		try
		{
			$sql = "
				SELECT e.*, sl.name AS supplier_name, b.name AS branch_name,
					IFNULL(NULLIF(TRIM(u.first_name || ' ' || IFNULL(u.last_name, '')), ''), u.username) AS actor_name
				FROM procurement_supply_events e
				LEFT JOIN shopping_locations sl ON sl.id = e.supplier_id
				LEFT JOIN branches b ON b.id = e.branch_id
				LEFT JOIN users u ON u.id = e.actor_user_id
				WHERE 1=1
			";
			$params = [];
			if ($branchId !== null && $branchId > 0)
			{
				$sql .= ' AND e.branch_id = :bid';
				$params[':bid'] = $branchId;
			}
			if ($supplierId !== null && $supplierId > 0)
			{
				$sql .= ' AND e.supplier_id = :sid';
				$params[':sid'] = $supplierId;
			}
			$sql .= ' ORDER BY e.id DESC LIMIT ' . max(1, min(200, $limit));
			$st = $db->prepare($sql);
			$st->execute($params);
			$events = $st->fetchAll(\PDO::FETCH_ASSOC);
		}
		catch (\Throwable $e) {}

		if (empty($events))
		{
			// Fallback histórico desde órdenes y recepciones existentes antes de la migración 0336
			$sqlOrd = "
				SELECT po.id AS order_id, po.branch_id, po.supplier_id, 'po_' || po.status AS event_type,
					po.order_code AS reference_code,
					'Orden ' || po.order_code || ' (' || po.status || ') por $' || ROUND(po.total_estimated_cost, 2) AS summary,
					po.created_at, sl.name AS supplier_name, b.name AS branch_name, '' AS actor_name
				FROM procurement_orders po
				LEFT JOIN shopping_locations sl ON sl.id = po.supplier_id
				LEFT JOIN branches b ON b.id = po.branch_id
				WHERE 1=1" . ($branchId ? ' AND po.branch_id = ' . intval($branchId) : '') . ($supplierId ? ' AND po.supplier_id = ' . intval($supplierId) : '') . "
				ORDER BY po.id DESC LIMIT " . max(1, min(100, $limit));
			$events = $db->query($sqlOrd)->fetchAll(\PDO::FETCH_ASSOC);
		}

		return $events;
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
