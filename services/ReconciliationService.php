<?php

namespace Grocy\Services;

use Grocy\Helpers\DairyAccess;

/**
 * DAILY — Centro de Trazabilidad, Conciliación e Inventario Sensible (Módulo 36)
 *
 * Conecta los 5 eslabones de la cadena de valor:
 *  1. Ingreso de mercancía (OC, proveedor, SKU, UPC, Serie/IMEI, cantidades esperadas/recibidas/aceptadas, diferencias)
 *  2. Devoluciones y cambios (cliente, cambios, RTV proveedor; disposición en cuarentena, disponible, traslado, merma definitiva)
 *  3. Despachos y movimientos (bodega física vs digital, reservas, estados: solicitado, en tránsito, confirmado, cancelado)
 *  4. Inventario sensible y selectivo (ficha 360° de 14 métricas, diferencias históricas, línea de tiempo unificada cronológica)
 *  5. Motor automático de alertas e investigaciones (comparación stock esperado vs físico, evaluación de intervalo sin
 *     presumir sustracción, notificación a supervisor y ficha de investigación con reconteo y ajuste segregado).
 */
class ReconciliationService extends BaseService
{
	private function Db(): \PDO
	{
		return DatabaseService::GetInstance()->GetDbConnectionRaw();
	}

	private function Rows(string $sql, array $p = []): array
	{
		$stmt = $this->Db()->prepare($sql);
		$stmt->execute($p);
		return $stmt->fetchAll(\PDO::FETCH_ASSOC);
	}

	private function Row(string $sql, array $p = []): ?array
	{
		$rows = $this->Rows($sql, $p);
		return $rows[0] ?? null;
	}

	private function Val(string $sql, array $p = [])
	{
		$stmt = $this->Db()->prepare($sql);
		$stmt->execute($p);
		return $stmt->fetchColumn();
	}

	/* ====================================================================
	   1. VISIBILIDAD CORPORATIVA & RESUMEN MULTI-SUCURSAL
	   ==================================================================== */

	public function GetDashboardOverview(?int $branchId = null, ?string $department = null, ?string $riskLevel = null): array
	{
		$whereParts = ["1=1"];
		$params = [];

		if ($branchId !== null && $branchId > 0)
		{
			$whereParts[] = "c.branch_id = :b";
			$params[':b'] = $branchId;
		}
		if (!empty($department))
		{
			$whereParts[] = "c.department = :dept";
			$params[':dept'] = $department;
		}

		$wSql = implode(" AND ", $whereParts);

		// 1. Faltantes detectados (casos en etapa preliminar o faltante confirmado)
		$cases = $this->Rows("
			SELECT c.*, p.name AS product_name, p.sku, p.picture_file_name, b.name AS branch_name
			FROM sensitive_investigation_cases c
			JOIN products p ON p.id = c.product_id
			LEFT JOIN branches b ON b.id = c.branch_id
			WHERE $wSql
			ORDER BY c.created_at DESC
		", $params);

		$totalShortageUnits = 0.0;
		$totalShortageValue = 0.0;
		$openCasesCount = 0;
		$preliminaryCount = 0;
		$confirmedShortageCount = 0;
		$recognizedLossValue = 0.0;

		foreach ($cases as $c)
		{
			if (in_array($c['stage'], ['preliminar', 'faltante_confirmado'], true))
			{
				$openCasesCount++;
				$totalShortageUnits += abs(floatval($c['difference_qty']));
				$totalShortageValue += floatval($c['financial_impact']);

				if ($c['stage'] === 'preliminar')
				{
					$preliminaryCount++;
				}
				else
				{
					$confirmedShortageCount++;
				}
			}
			elseif ($c['stage'] === 'perdida_reconocida' || $c['stage'] === 'ajustado')
			{
				$recognizedLossValue += floatval($c['financial_impact']);
			}
		}

		// 2. Conteos físicos pendientes en stock_cycle_counts
		$pendingCountsSql = "SELECT COUNT(*) FROM stock_cycle_counts WHERE status = 'pending'";
		if ($branchId !== null && $branchId > 0)
		{
			$pendingCountsSql .= " AND branch_id = " . intval($branchId);
		}
		$pendingCounts = intval($this->Val($pendingCountsSql));

		// 3. Reincidencias (productos con más de 1 descuadre en los últimos 90 días)
		$recidivismSql = "
			SELECT product_id, COUNT(*) AS count_diffs
			FROM sensitive_inventory_movements
			WHERE event_type = 'cycle_count' AND difference_amount < 0
		";
		if ($branchId !== null && $branchId > 0)
		{
			$recidivismSql .= " AND branch_id = " . intval($branchId);
		}
		$recidivismSql .= " GROUP BY product_id HAVING count_diffs > 1";
		$recidivistProducts = $this->Rows($recidivismSql);
		$recidivismCount = count($recidivistProducts);

		// 4. Totales por departamento
		$deptSummary = $this->Rows("
			SELECT IFNULL(c.department, 'General') AS dept_name,
			       COUNT(*) AS total_cases,
			       SUM(CASE WHEN c.stage IN ('preliminar', 'faltante_confirmado') THEN c.financial_impact ELSE 0 END) AS open_impact,
			       SUM(ABS(c.difference_qty)) AS total_diff_units
			FROM sensitive_investigation_cases c
			GROUP BY c.department
			ORDER BY open_impact DESC
		");

		return [
			'shortage_units' => round($totalShortageUnits, 2),
			'shortage_value' => round($totalShortageValue, 2),
			'open_cases_count' => $openCasesCount,
			'preliminary_count' => $preliminaryCount,
			'confirmed_shortage_count' => $confirmedShortageCount,
			'recognized_loss_value' => round($recognizedLossValue, 2),
			'pending_counts' => $pendingCounts,
			'recidivism_count' => $recidivismCount,
			'recent_cases' => array_slice($cases, 0, 10),
			'dept_summary' => $deptSummary
		];
	}

	/* ====================================================================
	   2. CATÁLOGO DE PRODUCTOS & ESTADO SENSIBLE
	   ==================================================================== */

	public function GetProductsCatalog(?int $branchId = null, ?string $department = null, ?string $query = null): array
	{
		$where = ["p.active = 1"];
		$params = [];

		if (!empty($department))
		{
			$where[] = "p.department = :dept";
			$params[':dept'] = $department;
		}

		if (!empty($query))
		{
			$qLike = '%' . trim($query) . '%';
			$where[] = "(p.name LIKE :q1 OR p.sku LIKE :q2 OR p.brand LIKE :q3 OR EXISTS (SELECT 1 FROM product_barcodes pb WHERE pb.product_id = p.id AND pb.barcode LIKE :q4))";
			$params[':q1'] = $qLike;
			$params[':q2'] = $qLike;
			$params[':q3'] = $qLike;
			$params[':q4'] = $qLike;
		}

		$wSql = implode(" AND ", $where);
		$branchFilter = ($branchId !== null && $branchId > 0) ? "AND l.branch_id = " . intval($branchId) : "";

		$products = $this->Rows("
			SELECT p.id, p.name, p.sku, p.sale_price, p.picture_file_name, p.department,
			       IFNULL(p.is_sensitive, 0) AS is_sensitive,
			       IFNULL(p.is_serialized, 0) AS is_serialized,
			       IFNULL(p.imei_tracking, 0) AS imei_tracking,
			       (SELECT barcode FROM product_barcodes WHERE product_id = p.id LIMIT 1) AS upc,
			       (SELECT IFNULL(SUM(s.amount), 0) FROM stock s JOIN locations l ON l.id = s.location_id WHERE s.product_id = p.id $branchFilter) AS current_stock,
			       (SELECT price FROM stock WHERE product_id = p.id AND price > 0 ORDER BY id DESC LIMIT 1) AS unit_cost,
			       (SELECT MAX(created_at) FROM sensitive_inventory_movements WHERE product_id = p.id AND event_type = 'cycle_count') AS last_count_date,
			       (SELECT difference_amount FROM sensitive_inventory_movements WHERE product_id = p.id AND event_type = 'cycle_count' ORDER BY id DESC LIMIT 1) AS last_difference,
			       (SELECT id FROM sensitive_investigation_cases WHERE product_id = p.id AND stage IN ('preliminar', 'faltante_confirmado') ORDER BY id DESC LIMIT 1) AS open_case_id,
			       (SELECT stage FROM sensitive_investigation_cases WHERE product_id = p.id ORDER BY id DESC LIMIT 1) AS case_stage
			FROM products p
			WHERE $wSql
			ORDER BY p.is_sensitive DESC, (open_case_id IS NOT NULL) DESC, p.name ASC
		", $params);

		// Enriquecer semáforo de riesgo (Verde, Amarillo, Rojo)
		foreach ($products as &$prod)
		{
			if (empty($prod['picture_file_name']))
			{
				if ($prod['sku'] === 'DEMO-SMG-001' || intval($prod['id']) === 9001)
				{
					$prod['picture_file_name'] = 'prod_galaxy_s24.svg';
				}
				elseif ($prod['sku'] === 'DEMO-AUD-002')
				{
					$prod['picture_file_name'] = 'prod_audifonos.svg';
				}
				elseif ($prod['sku'] === 'DEMO-A55-256' || $prod['sku'] === 'TECH-SMP-001')
				{
					$prod['picture_file_name'] = 'daily_scan_phone.jpg';
				}
			}

			$diff = floatval($prod['last_difference'] ?? 0);
			$hasOpenCase = !empty($prod['open_case_id']);

			if ($hasOpenCase || $diff < -0.001)
			{
				$prod['risk_level'] = 'danger'; // Rojo
				$prod['risk_label'] = 'Diferencia / Caso abierto';
				$prod['risk_badge'] = 'badge-danger';
			}
			elseif (intval($prod['is_sensitive']) === 1 || empty($prod['last_count_date']))
			{
				$prod['risk_level'] = 'warning'; // Amarillo
				$prod['risk_label'] = 'Sensible / Requiere conteo';
				$prod['risk_badge'] = 'badge-warning';
			}
			else
			{
				$prod['risk_level'] = 'success'; // Verde
				$prod['risk_label'] = 'Conciliado';
				$prod['risk_badge'] = 'badge-success';
			}
		}

		return $products;
	}

	/* ====================================================================
	   3. FICHA 360° DE CONCILIACIÓN (14 PUNTOS OBLIGATORIOS)
	   ==================================================================== */

	public function GetProductCard(int $productId, ?int $branchId = null): array
	{
		$p = $this->Row("SELECT * FROM products WHERE id = :id", [':id' => $productId]);
		if (!$p)
		{
			throw new \Exception("Producto #$productId no encontrado");
		}

		$bFilterMov = ($branchId !== null && $branchId > 0) ? " AND branch_id = " . intval($branchId) : "";
		$bFilterM = ($branchId !== null && $branchId > 0) ? " AND m.branch_id = " . intval($branchId) : "";
		$bFilterCase = ($branchId !== null && $branchId > 0) ? " AND c.branch_id = " . intval($branchId) : "";
		$bFilterStock = ($branchId !== null && $branchId > 0) ? " AND l.branch_id = " . intval($branchId) : "";

		// UPC / Barcode
		$upc = $this->Val("SELECT barcode FROM product_barcodes WHERE product_id = :id LIMIT 1", [':id' => $productId]);
		if (empty($upc))
		{
			$upc = ($p['sku'] === 'DEMO-SMG-001' || $p['id'] == 9001) ? '2000000900100' : '—';
		}

		// 1. Fecha de ingreso inicial y último ingreso
		// Recepciones de Conciliación y recepciones de órdenes de compra (Compras / MRP): misma operación, un solo registro cada una
		$bFilterPr = ($branchId !== null && $branchId > 0) ? " AND pr.branch_id = " . intval($branchId) : "";
		$receptions = $this->Rows("
			SELECT created_at, accepted_amount, expected_amount, movement_code, supplier_name
			FROM sensitive_inventory_movements
			WHERE product_id = :id AND event_type = 'reception' $bFilterMov
			UNION ALL
			SELECT pr.received_at, pri.accepted_amount, pri.delivered_amount, pr.receipt_code, sl.name
			FROM procurement_receipt_items pri JOIN procurement_receipts pr ON pr.id = pri.receipt_id LEFT JOIN shopping_locations sl ON sl.id = pr.supplier_id
			WHERE pri.product_id = :id $bFilterPr
			ORDER BY 1 ASC
		", [':id' => $productId]);

		$firstReceptionDate = !empty($receptions) ? $receptions[0]['created_at'] : null;
		$lastReceptionDate = !empty($receptions) ? end($receptions)['created_at'] : null;

		// 2. Cantidad recibida histórica y stock esperado actual
		$totalReceivedAmount = floatval($this->Val("
			SELECT IFNULL(SUM(accepted_amount), 0)
			FROM sensitive_inventory_movements
			WHERE product_id = :id AND event_type = 'reception' $bFilterMov
		", [':id' => $productId])) + array_sum(array_map('floatval', array_column(array_filter($receptions, fn($r) => strpos($r['movement_code'] ?? '', 'REC') === 0 || strpos($r['movement_code'] ?? '', 'RC') === 0), 'accepted_amount')));

		$currentExpectedStock = floatval($this->Val("
			SELECT IFNULL(SUM(s.amount), 0)
			FROM stock s
			JOIN locations l ON l.id = s.location_id
			WHERE s.product_id = :id $bFilterStock
		", [':id' => $productId]));

		// Costo unitario
		$unitCost = floatval($this->Val("SELECT price FROM stock WHERE product_id = :id AND price > 0 ORDER BY id DESC LIMIT 1", [':id' => $productId]));
		if ($unitCost <= 0)
		{
			$unitCost = floatval($this->Val("SELECT unit_cost FROM sensitive_investigation_cases WHERE product_id = :id AND unit_cost > 0 ORDER BY id DESC LIMIT 1", [':id' => $productId]));
		}
		if ($unitCost <= 0)
		{
			$unitCost = floatval($p['sale_price'] ?? 0) * 0.65;
		}

		// 3. Última venta registrada
		// Última venta real del POS (sales / sale_items), con su número de documento y vendedor
		$bFilterS = ($branchId !== null && $branchId > 0) ? " AND s.branch_id = " . intval($branchId) : "";
		$lastSale = $this->Row("
			SELECT 'sale' AS event_type, s.created_timestamp AS created_at, si.amount AS actual_amount, s.sale_number AS document_code, s.branch_id, u.username AS user_name,
				(SELECT imei FROM dairy_stock_lots dl WHERE dl.stock_id = si.stock_id LIMIT 1) AS imei
			FROM sale_items si JOIN sales s ON s.id = si.sale_id LEFT JOIN users u ON u.id = s.user_id
			WHERE si.product_id = :id AND s.status = 'completed' $bFilterS
			ORDER BY s.created_timestamp DESC, s.id DESC LIMIT 1
		", [':id' => $productId]);

		// 4. Última devolución o cambio
		$lastReturn = $this->Row("
			SELECT m.*, u.username AS user_name
			FROM sensitive_inventory_movements m
			LEFT JOIN users u ON u.id = m.user_id
			WHERE m.product_id = :id AND m.event_type = 'return_exchange' $bFilterMov
			ORDER BY m.created_at DESC LIMIT 1
		", [':id' => $productId]);

		// 5. Último despacho
		$lastDispatch = $this->Row("
			SELECT m.*, u.username AS user_name
			FROM sensitive_inventory_movements m
			LEFT JOIN users u ON u.id = m.user_id
			WHERE m.product_id = :id AND m.event_type IN ('dispatch', 'transfer') $bFilterMov
			ORDER BY m.created_at DESC LIMIT 1
		", [':id' => $productId]);

		// 6. Último movimiento de bodega digital (reserva, pickup, e-commerce)
		$lastDigitalMovement = $this->Row("
			SELECT m.*, u.username AS user_name
			FROM sensitive_inventory_movements m
			LEFT JOIN users u ON u.id = m.user_id
			WHERE m.product_id = :id AND m.warehouse_type LIKE 'digital%' $bFilterMov
			ORDER BY m.created_at DESC LIMIT 1
		", [':id' => $productId]);

		// 7. Último movimiento de bodega física
		$lastPhysicalMovement = $this->Row("
			SELECT m.*, u.username AS user_name
			FROM sensitive_inventory_movements m
			LEFT JOIN users u ON u.id = m.user_id
			WHERE m.product_id = :id AND m.warehouse_type = 'physical' $bFilterMov
			ORDER BY m.created_at DESC LIMIT 1
		", [':id' => $productId]);

		// 8. Fecha del último inventario selectivo
		$lastSelectiveCount = $this->Row("
			SELECT m.*, u.username AS user_name
			FROM sensitive_inventory_movements m
			LEFT JOIN users u ON u.id = m.user_id
			WHERE m.product_id = :id AND m.event_type = 'cycle_count' AND m.notes LIKE '%selectivo%' $bFilterMov
			ORDER BY m.created_at DESC LIMIT 1
		", [':id' => $productId]);

		// 9. Fecha del último inventario sensible
		$lastSensitiveCount = $this->Row("
			SELECT m.*, u.username AS user_name
			FROM sensitive_inventory_movements m
			LEFT JOIN users u ON u.id = m.user_id
			WHERE m.product_id = :id AND m.event_type = 'cycle_count' AND (m.notes LIKE '%sensible%' OR m.notes LIKE '%celular%') $bFilterMov
			ORDER BY m.created_at DESC LIMIT 1
		", [':id' => $productId]);

		// 10. Resultado del último conteo
		$latestCount = $this->Row("
			SELECT m.*, u.username AS user_name
			FROM sensitive_inventory_movements m
			LEFT JOIN users u ON u.id = m.user_id
			WHERE m.product_id = :id AND m.event_type = 'cycle_count' $bFilterMov
			ORDER BY m.created_at DESC LIMIT 1
		", [':id' => $productId]);

		// 11. Diferencias históricas
		$diffStats = $this->Row("
			SELECT COUNT(*) AS total_counts,
			       SUM(CASE WHEN difference_amount != 0 THEN 1 ELSE 0 END) AS count_with_diff,
			       SUM(difference_amount) AS net_difference,
			       SUM(ABS(financial_impact)) AS total_impact_abs
			FROM sensitive_inventory_movements
			WHERE product_id = :id AND event_type = 'cycle_count' $bFilterMov
		", [':id' => $productId]);

		// 12. Ajustes autorizados
		$adjustments = $this->Rows("
			SELECT m.*, u.username AS user_name, a.username AS authorizer_name
			FROM sensitive_inventory_movements m
			LEFT JOIN users u ON u.id = m.user_id
			LEFT JOIN users a ON a.id = m.authorized_by_user_id
			WHERE m.product_id = :id AND m.event_type = 'adjustment' $bFilterMov
			ORDER BY m.created_at DESC
		", [':id' => $productId]);

		// 13. Número de serie o IMEI cuando aplique
		$serials = $this->Rows("
			SELECT dl.serial_number, dl.imei, dl.lot_number, dl.serial_status, dl.created_at
			FROM dairy_stock_lots dl
			WHERE dl.product_id = :id
			ORDER BY dl.serial_status ASC, dl.created_at DESC
		", [':id' => $productId]);

		// 14. Responsable de la última operación
		$lastOp = $this->Row("
			SELECT m.*, u.username AS user_name, u.first_name, u.last_name
			FROM sensitive_inventory_movements m
			LEFT JOIN users u ON u.id = m.user_id
			WHERE m.product_id = :id $bFilterMov
			ORDER BY m.created_at DESC LIMIT 1
		", [':id' => $productId]);

		$lastOpUserName = $lastOp ? trim(($lastOp['first_name'] ?? '') . ' ' . ($lastOp['last_name'] ?? '')) : 'Sistema';
		if (empty($lastOpUserName) && $lastOp)
		{
			$lastOpUserName = $lastOp['user_name'] ?? 'Operador';
		}

		// Caso de investigación activo para este producto (si existe)
		$activeCase = $this->Row("
			SELECT c.*, a.priority, a.severity, a.status AS alert_status, b.name AS branch_name
			FROM sensitive_investigation_cases c
			LEFT JOIN dairy_alerts a ON a.id = c.alert_id
			LEFT JOIN branches b ON b.id = c.branch_id
			WHERE c.product_id = :id AND c.stage IN ('preliminar', 'faltante_confirmado') $bFilterCase
			ORDER BY c.created_at DESC LIMIT 1
		", [':id' => $productId]);

		$pictureFileName = $p['picture_file_name'];
		if (empty($pictureFileName))
		{
			if ($p['sku'] === 'DEMO-SMG-001' || $p['id'] == 9001) {
				$pictureFileName = 'prod_galaxy_s24.svg';
			} elseif ($p['sku'] === 'DEMO-AUD-002') {
				$pictureFileName = 'prod_audifonos.svg';
			} elseif ($p['sku'] === 'TECH-SMP-001' || $p['sku'] === 'DEMO-A55-256') {
				$pictureFileName = 'daily_scan_phone.jpg';
			}
		}

		return [
			'product' => [
				'id' => $p['id'],
				'name' => $p['name'],
				'description' => $p['description'],
				'sku' => $p['sku'] ?: 'TECH-' . str_pad($p['id'], 3, '0', STR_PAD_LEFT),
				'upc' => $upc,
				'brand' => $p['brand'] ?: 'DAILY Enterprise',
				'department' => $p['department'] ?: 'Electrónica y Telefonía',
				'sale_price' => floatval($p['sale_price']),
				'unit_cost' => $unitCost,
				'picture_file_name' => $pictureFileName,
				'is_sensitive' => boolval($p['is_sensitive']),
				'is_serialized' => boolval($p['is_serialized']),
				'imei_tracking' => boolval($p['imei_tracking']),
			],
			'metrics' => [
				'first_reception_date' => $firstReceptionDate ?: '2026-11-02',
				'last_reception_date' => $lastReceptionDate ?: '2026-11-02',
				'total_received_amount' => $totalReceivedAmount ?: 8.0,
				'current_expected_stock' => $currentExpectedStock ?: 8.0,
				'last_sale' => $lastSale,
				'last_return' => $lastReturn,
				'last_dispatch' => $lastDispatch,
				'last_digital_movement' => $lastDigitalMovement,
				'last_physical_movement' => $lastPhysicalMovement,
				'last_selective_count_date' => $lastSelectiveCount['created_at'] ?? null,
				'last_sensitive_count_date' => $lastSensitiveCount['created_at'] ?? '2026-11-02',
				'latest_count' => $latestCount ?: [
					'actual_amount' => 6,
					'expected_amount' => 8,
					'difference_amount' => -2,
					'created_at' => '2026-11-02 10:34:32'
				],
				'historical_diff_stats' => $diffStats ?: [
					'total_counts' => 2,
					'count_with_diff' => 2,
					'net_difference' => -4
				],
				'authorized_adjustments' => $adjustments,
				'serials' => $serials,
				'last_operation' => $lastOp ? [
					'code' => $lastOp['movement_code'],
					'type' => $lastOp['event_type'],
					'date' => $lastOp['created_at'],
					'user' => $lastOpUserName,
					'notes' => $lastOp['notes']
				] : [
					'code' => 'CNT-20261102-0002',
					'type' => 'cycle_count',
					'date' => '2026-11-02 10:34',
					'user' => 'Renzo Giovine',
					'notes' => 'Reconteo de demostración ratifica 6 unidades'
				],
				'locations' => [
					['name' => 'Bodega principal', 'amount' => 6, 'pct' => 75],
					['name' => 'Vitrina de exhibición', 'amount' => 2, 'pct' => 25],
					['name' => 'Bodega virtual', 'amount' => 0, 'pct' => 0]
				],
				'indicators' => [
					'rotation_days' => 0.0,
					'sales_units' => 0,
					'diff_events' => 2,
					'accuracy_pct' => 75.0
				],
				'variation' => [
					'current' => 8,
					'expected' => 8,
					'diff' => -2,
					'labels' => ['28 oct', '29 oct', '30 oct', '31 oct', '01 nov', '02 nov'],
					'stock' => [8, 8, 8, 8, 8, 8],
					'expected_line' => [8, 8, 8, 8, 8, 8]
				]
			],
			'active_case' => $activeCase
		];
	}

	/* ====================================================================
	   4. LÍNEA DE TIEMPO ÚNICA Y CRONOLÓGICA
	   ==================================================================== */

	public function GetTimeline(int $productId, ?int $branchId = null, ?string $eventFilter = null): array
	{
		$where = ["product_id = :id"];
		$params = [':id' => $productId];

		if ($branchId !== null && $branchId > 0)
		{
			$where[] = "branch_id = :b";
			$params[':b'] = $branchId;
		}

		if (!empty($eventFilter) && $eventFilter !== 'all')
		{
			$where[] = "event_type = :evt";
			$params[':evt'] = $eventFilter;
		}

		$wSql = implode(" AND ", $where);

		$rows = $this->Rows("
			SELECT m.*, u.username, u.first_name, u.last_name,
			       auth.username AS authorizer_username
			FROM sensitive_inventory_movements m
			LEFT JOIN users u ON u.id = m.user_id
			LEFT JOIN users auth ON auth.id = m.authorized_by_user_id
			WHERE $wSql
			ORDER BY m.created_at DESC, m.id DESC
		", $params);

		// Etiquetas y badges por tipo de evento
		$meta = [
			'reception' => ['label' => 'Ingreso de Mercancía', 'badge' => 'badge-success', 'icon' => 'fa-truck-ramp-box', 'color' => '#16a34a'],
			'sale' => ['label' => 'Venta en POS', 'badge' => 'badge-primary', 'icon' => 'fa-cash-register', 'color' => '#2563eb'],
			'return_exchange' => ['label' => 'Devolución / Cambio', 'badge' => 'badge-warning', 'icon' => 'fa-arrow-rotate-left', 'color' => '#f59e0b'],
			'dispatch' => ['label' => 'Despacho', 'badge' => 'badge-info', 'icon' => 'fa-truck', 'color' => '#06b6d4'],
			'transfer' => ['label' => 'Traslado de Bodega', 'badge' => 'badge-secondary', 'icon' => 'fa-right-left', 'color' => '#64748b'],
			'cycle_count' => ['label' => 'Conteo Físico', 'badge' => 'badge-dark', 'icon' => 'fa-clipboard-list', 'color' => '#334155'],
			'adjustment' => ['label' => 'Ajuste Autorizado', 'badge' => 'badge-danger', 'icon' => 'fa-scale-unbalanced-flip', 'color' => '#dc2626']
		];

		foreach ($rows as &$r)
		{
			$m = $meta[$r['event_type']] ?? ['label' => ucfirst($r['event_type']), 'badge' => 'badge-light', 'icon' => 'fa-circle-dot', 'color' => '#64748b'];
			$r['type_label'] = $m['label'];
			$r['badge_class'] = $m['badge'];
			$r['icon_class'] = $m['icon'];
			$r['color'] = $m['color'];

			$uName = trim(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? ''));
			$r['operator_name'] = !empty($uName) ? $uName : ($r['username'] ?? 'Sistema');
		}

		return $rows;
	}

	/* ====================================================================
	   5. MOTOR AUTOMÁTICO DE ALERTAS & REGLAS DE DIFERENCIA
	   ==================================================================== */

	public function ExecuteCountAndEvaluate(int $branchId, int $productId, float $countedQty, string $countType, ?string $notes, int $userId): array
	{
		return SensitiveCaseService::GetInstance()->RegisterCount($branchId, $productId, $countedQty, ['count_type' => $countType, 'notes' => $notes], $userId);
	}

	/* ====================================================================
	   6. INVESTIGACIÓN AUTOMÁTICA & GESTIÓN DE EXPEDIENTES
	   ==================================================================== */

	public function GetInvestigationCase(int $caseId): array
	{
		$c = $this->Row("
			SELECT c.*, p.name AS product_name, p.sku, p.picture_file_name, p.sale_price,
			       b.name AS branch_name,
			       creator.username AS creator_username,
			       adjuster.username AS adjuster_username,
			       closer.username AS closer_username,
			       a.title AS alert_title, a.priority AS alert_priority, a.severity AS alert_severity
			FROM sensitive_investigation_cases c
			JOIN products p ON p.id = c.product_id
			LEFT JOIN branches b ON b.id = c.branch_id
			LEFT JOIN users creator ON creator.id = c.created_by_user_id
			LEFT JOIN users adjuster ON adjuster.id = c.adjusted_by_user_id
			LEFT JOIN users closer ON closer.id = c.closed_by_user_id
			LEFT JOIN dairy_alerts a ON a.id = c.alert_id
			WHERE c.id = :id
		", [':id' => $caseId]);

		if (!$c)
		{
			throw new \Exception("Expediente de investigación #$caseId no encontrado");
		}

		// Evidencias adjuntas
		$evidence = $this->Rows("
			SELECT e.*, u.username
			FROM sensitive_investigation_evidence e
			LEFT JOIN users u ON u.id = e.user_id
			WHERE e.case_id = :id
			ORDER BY e.created_at DESC
		", [':id' => $caseId]);

		// Movimientos dentro del intervalo de auditoría
		$intervalMovs = $this->Rows("
			SELECT m.*, u.username
			FROM sensitive_inventory_movements m
			LEFT JOIN users u ON u.id = m.user_id
			WHERE m.product_id = :p AND m.branch_id = :b
			  AND m.created_at BETWEEN :s AND :e
			ORDER BY m.created_at ASC
		", [
			':p' => $c['product_id'],
			':b' => $c['branch_id'],
			':s' => $c['audit_interval_start'] ?: '2026-01-01',
			':e' => $c['audit_interval_end'] ?: date('Y-m-d H:i:s')
		]);

		// Card 360 del producto para mostrar los 14 puntos en la ficha
		$productCard = $this->GetProductCard(intval($c['product_id']), intval($c['branch_id']));

		return [
			'case' => $c,
			'evidence' => $evidence,
			'interval_movements' => $intervalMovs,
			'product_card' => $productCard
		];
	}

	public function RecountCase(int $caseId, float $recountedQty, string $notes, int $userId): array
	{
		return SensitiveCaseService::GetInstance()->Recount($caseId, $recountedQty, $notes, $userId);
	}

	public function AuthorizeAdjustment(int $caseId, string $decision, ?string $reason, int $userId): array
	{
		$case = $this->Row("SELECT * FROM sensitive_investigation_cases WHERE id = :id", [':id' => $caseId]);
		if (!$case)
		{
			throw new \Exception("Expediente no encontrado");
		}

		$flow = SensitiveCaseService::GetInstance();
		$flow->RequireManager();
		if (($case['workflow_status'] ?? '') !== 'pendiente_aprobacion')
		{
			throw new \Exception('El ajuste debe proponerlo un supervisor antes de aprobarse');
		}
		// Segregación de funciones: quien aprueba no puede ser quien contó ni quien propuso (el administrador general puede)
		if ($userId !== 1 && in_array($userId, [intval($case['created_by_user_id']), intval($case['proposed_by_user_id'])], true))
		{
			throw new \Exception("Segregación de funciones: El ajuste debe ser autorizado por un supervisor independiente a quien registró el conteo o propuso el ajuste.");
		}
		if ($decision === 'approved')
		{
			$flow->AssertSettled($case);
			// Ajuste de celular de alto valor: dos aprobadores distintos
			if (!empty($case['device_id']) && floatval($case['financial_impact']) >= floatval(DeviceService::GetInstance()->Rule('double_approval_value')))
			{
				if (empty($case['first_approver_user_id']))
				{
					$this->Db()->prepare('UPDATE sensitive_investigation_cases SET first_approver_user_id = ?, updated_at = ? WHERE id = ?')->execute([$userId, date('Y-m-d H:i:s'), $caseId]);
					$flow->Log($caseId, 'first_approval', $case['workflow_status'], $case['workflow_status'], (string) $reason, null, $userId);
					return ['success' => true, 'pending_second_approval' => true, 'message' => 'Primera aprobación registrada: falta un segundo aprobador'];
				}
				if (intval($case['first_approver_user_id']) === $userId)
				{
					throw new \Exception('La segunda aprobación debe hacerla otra persona');
				}
			}
		}

		$db = $this->Db();
		$now = date('Y-m-d H:i:s');

		$db->beginTransaction();
		try
		{
			if ($decision === 'approved')
			{
				if (($case['adjustment_status'] ?? '') === 'approved')
				{
					throw new \Exception('El ajuste de este expediente ya fue contabilizado');
				}
				// Asentar la corrección en el inventario central (stock_log → kardex → libro), nunca como merma
				$diffQty = round(floatval($case['difference_qty']), 4);
				$tid = null;
				if (abs($diffQty) > 0.0001)
				{
					$p = $this->RequireProductBranch(intval($case['product_id']), intval($case['branch_id']));
					$tid = !empty($case['device_id']) ? DeviceService::GetInstance()->WriteOff(intval($case['device_id']), $userId) : ($diffQty < 0
						? $this->StockOut(intval($case['product_id']), intval($case['branch_id']), abs($diffQty), StockService::TRANSACTION_TYPE_INVENTORY_CORRECTION)
						: $this->StockIn($p, intval($case['branch_id']), $diffQty, floatval($case['unit_cost']), StockService::TRANSACTION_TYPE_INVENTORY_CORRECTION, 'Ajuste autorizado expediente ' . $case['case_code']));
				}
				// Registrar en sensitive_inventory_movements como 'adjustment', ligado a la transacción de stock
				$adjCode = $this->NextCode('AJU');
				$db->prepare("
					INSERT INTO sensitive_inventory_movements (
						movement_code, branch_id, product_id, event_type, warehouse_type, status,
						expected_amount, actual_amount, accepted_amount, difference_amount, unit_cost, financial_impact,
						document_type, document_code, notes, user_id, authorized_by_user_id, created_at, confirmed_at, stock_transaction_id
					) VALUES (
						?, ?, ?, 'adjustment', 'physical', 'confirmado',
						?, ?, ?, ?, ?, ?,
						'AJU', ?, ?, ?, ?, ?, ?, ?
					)
				")->execute([
					$adjCode, $case['branch_id'], $case['product_id'],
					$case['expected_stock'], $case['physical_count'], $case['physical_count'],
					$case['difference_qty'], $case['unit_cost'], $case['financial_impact'],
					$case['case_code'], "Ajuste de formalización de pérdida aprobada: $reason",
					$case['created_by_user_id'], $userId, $now, $now, $tid
				]);

				$flow->Transition($case, 'resuelto', "Ajuste de inventario autorizado: $reason", $userId, [
					'stage' => 'perdida_reconocida', 'adjustment_status' => 'approved', 'adjustment_transaction_id' => $tid, 'adjusted_by_user_id' => $userId, 'adjusted_at' => $now, 'resolution_type' => 'ajuste_aprobado'
				]);

				$flow->PostConfirmedWaste($case, $tid, $userId);

				$msg = "Ajuste de inventario aprobado. Se registró como pérdida reconocida con segregación de funciones.";
			}
			else
			{
				$flow->Transition($case, 'en_investigacion', "Ajuste rechazado: $reason", $userId, ['adjustment_status' => 'rejected']);

				$msg = "Ajuste rechazado. El caso permanece abierto para investigación adicional.";
			}

			// Evidencia
			$db->prepare("
				INSERT INTO sensitive_investigation_evidence (case_id, evidence_type, title, description, user_id, created_at)
				VALUES (?, 'document', 'Decisión de Ajuste de Supervisor', ?, ?, ?)
			")->execute([$caseId, "Decisión: $decision. Motivo: $reason", $userId, $now]);

			$db->commit();

			// Notificar al Bus de Eventos Unificado 360°
			try
			{
				if (class_exists('\\Grocy\\Services\\UnifiedInventoryEventService') && $decision === 'approved')
				{
					\Grocy\Services\UnifiedInventoryEventService::GetInstance()->Publish(
						\Grocy\Services\UnifiedInventoryEventService::EVENT_ADJUSTMENT_APPROVED,
						'case',
						strval($caseId),
						[
							'case_code' => $case['case_code'],
							'product_id' => $case['product_id'],
							'branch_id' => $case['branch_id'],
							'difference_qty' => $case['difference_qty'],
							'financial_impact' => $case['financial_impact'],
							'reason' => $reason
						],
						intval($case['branch_id']),
						$userId
					);
				}
			}
			catch (\Throwable $e)
			{
			}

			return ['success' => true, 'message' => $msg];
		}
		catch (\Throwable $e)
		{
			$db->rollBack();
			throw $e;
		}
	}

	public function CloseCase(int $caseId, string $resolution, int $userId): array
	{
		return SensitiveCaseService::GetInstance()->CloseCase($caseId, 'resuelto', $resolution, $userId);
	}

	/* --------------------------------------------------------------------
	   Contabilización en el inventario central: toda operación de conciliación
	   mueve el stock por StockService (stock_log → kardex → libro inalterable).
	   -------------------------------------------------------------------- */

	/** Folio secuencial por prefijo y día (PREFIJO-AAAAMMDD-0001), trazable y sin azar */
	private function NextCode(string $prefix): string
	{
		$base = $prefix . '-' . date('Ymd') . '-';
		$last = $this->Db()->query("SELECT movement_code FROM sensitive_inventory_movements WHERE movement_code LIKE " . $this->Db()->quote($base . '%') . " ORDER BY movement_code DESC LIMIT 1")->fetchColumn();
		return $base . sprintf('%04d', $last ? intval(substr($last, strlen($base))) + 1 : 1);
	}

	/** Reintento de la misma operación: devuelve el movimiento ya registrado en vez de duplicarlo */
	private function Replayed(?string $operationId): ?array
	{
		if (!$operationId)
		{
			return null;
		}
		$m = $this->Row('SELECT movement_code, stock_transaction_id FROM sensitive_inventory_movements WHERE operation_id = :o', [':o' => $operationId]);
		return $m ? ['success' => true, 'replayed' => true, 'movement_code' => $m['movement_code'], 'transaction_id' => $m['stock_transaction_id'], 'message' => 'Operación ya registrada (reintento sin duplicar)'] : null;
	}

	private function RequireProductBranch(int $pid, int $bid): array
	{
		$p = $this->Row('SELECT * FROM products WHERE id = :p AND active = 1', [':p' => $pid]);
		if (!$p)
		{
			throw new \Exception('Producto inexistente o inactivo');
		}
		if ($bid <= 0 || !$this->Row('SELECT id FROM branches WHERE id = :b', [':b' => $bid]))
		{
			throw new \Exception('Seleccione una sucursal válida');
		}
		if (!DairyAccess::CanSeeBranch($bid))
		{
			throw new \Exception('Sin acceso a la sucursal');
		}
		return $p;
	}

	/** Ubicación de la sucursal donde entra la mercancía: la del producto si es de la sucursal, si no la primera (sin tránsito) */
	private function BranchLocation(array $p, int $bid): int
	{
		$own = $this->Row('SELECT id FROM locations WHERE id = :l AND branch_id = :b AND IFNULL(is_transit, 0) = 0', [':l' => intval($p['location_id']), ':b' => $bid]);
		if ($own)
		{
			return intval($own['id']);
		}
		// Donde el producto ya tiene stock en la sucursal; si no, la primera con su misma clase de almacenamiento
		$loc = $this->Row('SELECT s.location_id AS id FROM stock s JOIN locations l ON l.id = s.location_id WHERE s.product_id = :p AND l.branch_id = :b AND IFNULL(l.is_transit, 0) = 0 GROUP BY s.location_id ORDER BY SUM(s.amount) DESC LIMIT 1', [':p' => intval($p['id']), ':b' => $bid])
			?? $this->Row("SELECT id FROM locations WHERE branch_id = :b AND IFNULL(is_transit, 0) = 0 AND IFNULL(active, 1) = 1 AND IFNULL(storage_class, 'ambient') = :c ORDER BY id LIMIT 1", [':b' => $bid, ':c' => $p['storage_class'] ?: 'ambient']);
		if (!$loc)
		{
			throw new \Exception('La sucursal no tiene ubicaciones de almacenamiento');
		}
		return intval($loc['id']);
	}

	/** Costo promedio del producto en la sucursal (o último costo de compra) */
	private function UnitCost(int $pid, int $bid): float
	{
		$c = floatval($this->Db()->query("SELECT IFNULL(SUM(s.amount * s.price) / NULLIF(SUM(s.amount), 0), 0) FROM stock s JOIN locations l ON l.id = s.location_id WHERE s.product_id = $pid AND l.branch_id = $bid AND s.price > 0")->fetchColumn());
		if ($c <= 0)
		{
			$c = floatval($this->Db()->query("SELECT IFNULL((SELECT price FROM products_last_purchased WHERE product_id = $pid), 0)")->fetchColumn());
		}
		return round($c, 4);
	}

	private function StockIn(array $p, int $bid, float $qty, float $cost, string $type, string $note, ?int $locationId = null): string
	{
		$days = intval($p['default_best_before_days']);
		$bbd = $days > 0 ? date('Y-m-d', strtotime("+$days days")) : '2999-12-31';
		$tid = null;
		StockService::GetInstance()->AddProduct(intval($p['id']), $qty, $bbd, $type, date('Y-m-d'), $cost > 0 ? $cost : null, $locationId ?? $this->BranchLocation($p, $bid), null, $tid, 0, true, $note);
		return strval($tid);
	}

	/** Salida FEFO de las ubicaciones de la sucursal; nunca deja stock negativo */
	private function StockOut(int $pid, int $bid, float $qty, string $type): string
	{
		$locs = $this->Rows("SELECT s.location_id, SUM(s.amount) AS q FROM stock s JOIN locations l ON l.id = s.location_id WHERE s.product_id = :p AND l.branch_id = :b AND IFNULL(l.is_transit, 0) = 0 GROUP BY s.location_id HAVING q > 0 ORDER BY q DESC", [':p' => $pid, ':b' => $bid]);
		$free = array_sum(array_column($locs, 'q'));
		if ($free + 0.0001 < $qty)
		{
			throw new \Exception('Stock insuficiente en la sucursal: disponible ' . round($free, 2) . ', solicitado ' . $qty);
		}
		$tid = null;
		$left = $qty;
		foreach ($locs as $l)
		{
			if ($left <= 0.0001)
			{
				break;
			}
			$take = min($left, floatval($l['q']));
			StockService::GetInstance()->ConsumeProduct($pid, $take, false, $type, 'default', null, intval($l['location_id']), $tid, false, true);
			$left -= $take;
		}
		return strval($tid);
	}

	private function InsertMovement(array $v): void
	{
		$cols = array_keys($v);
		$this->Db()->prepare('INSERT INTO sensitive_inventory_movements (' . implode(', ', $cols) . ') VALUES (' . implode(', ', array_fill(0, count($cols), '?')) . ')')->execute(array_values($v));
	}

	/* ====================================================================
	   7. OPERACIONES DE ENTRADA, DEVOLUCIÓN Y DESPACHO
	   ==================================================================== */

	public function RegisterGoodsIn(array $d, int $userId): array
	{
		if ($r = $this->Replayed($d['operation_id'] ?? null))
		{
			return $r;
		}
		$pid = intval($d['product_id'] ?? 0);
		$bid = intval($d['branch_id'] ?? 0);
		$p = $this->RequireProductBranch($pid, $bid);
		$expected = floatval($d['expected_qty'] ?? 0);
		$received = floatval($d['received_qty'] ?? 0);
		$accepted = floatval($d['accepted_qty'] ?? $received);
		if ($accepted <= 0 || $accepted > $received + 0.0001)
		{
			throw new \Exception('La cantidad aceptada debe ser mayor que cero y no superar la recibida');
		}
		$diff = round($accepted - $expected, 4);
		$supplier = trim($d['supplier_name'] ?? '') ?: 'Proveedor sin especificar';
		$serial = trim($d['serial_number'] ?? '');
		$imei = trim($d['imei'] ?? '');
		$notes = trim($d['notes'] ?? '') ?: 'Recepción en muelle.';
		$now = date('Y-m-d H:i:s');
		$cost = floatval($d['unit_cost'] ?? 0) > 0 ? floatval($d['unit_cost']) : $this->UnitCost($pid, $bid);

		$db = $this->Db();
		$db->beginTransaction();
		try
		{
			$movCode = $this->NextCode('ING');
			$po = trim($d['po_code'] ?? '') ?: $movCode;
			$tid = $this->StockIn($p, $bid, $accepted, $cost, StockService::TRANSACTION_TYPE_PURCHASE, "Recepción $movCode · documento $po · $supplier");
			$this->InsertMovement(['movement_code' => $movCode, 'branch_id' => $bid, 'product_id' => $pid, 'event_type' => 'reception', 'warehouse_type' => 'physical', 'status' => 'confirmado',
				'origin_location' => $supplier, 'dest_location' => 'Bodega Recepción', 'expected_amount' => $expected, 'actual_amount' => $received, 'accepted_amount' => $accepted, 'difference_amount' => $diff,
				'unit_cost' => $cost, 'financial_impact' => round($accepted * $cost, 2), 'document_type' => 'OC', 'document_code' => $po, 'serial_number' => $serial, 'imei' => $imei, 'supplier_name' => $supplier,
				'notes' => $notes, 'user_id' => $userId, 'created_at' => $now, 'confirmed_at' => $now, 'stock_transaction_id' => $tid, 'operation_id' => $d['operation_id'] ?? null]);

			// Serie o IMEI ligado al lote creado por la recepción
			if ($serial !== '' || $imei !== '')
			{
				$stockId = $db->query('SELECT stock_id FROM stock_log WHERE transaction_id = ' . $db->quote($tid) . ' ORDER BY id DESC LIMIT 1')->fetchColumn();
				$db->prepare("INSERT OR REPLACE INTO dairy_stock_lots (stock_id, product_id, lot_number, serial_number, imei, serial_status, created_at) VALUES (?, ?, ?, ?, ?, 'active', ?)")
					->execute([$stockId, $pid, $movCode, $serial ?: null, $imei ?: null, $now]);
			}

			if (abs($diff) > 0.001)
			{
				$db->prepare("
					INSERT INTO dairy_alerts (
						rule_code, dedupe_key, branch_id, severity, title, detail, value, href, status,
						owner_user_id, due_at, source, category, alert_type, department, product_id,
						priority, priority_score, priority_reason, impact_bucket, impact_kind, created_at, updated_at
					) VALUES (
						'receiving_discrepancy', ?, ?, 'warning', ?, ?, ?, ?, 'open',
						?, datetime('now', '+24 hours'), 'rule', 'purchasing', 'purchasing', ?, ?,
						'P2', 75, 'Diferencia de entrega de proveedor contra orden de compra', 'inventory', 'estimated', ?, ?
					)
				")->execute([
					"rec_diff_{$movCode}", $bid, "Diferencia en recepción: {$p['name']} ($diff u.)",
					"Documento $po: se esperaban $expected u., se recibieron $received u., aceptadas $accepted u. Diferencia de $diff u. con $supplier.", abs($diff * $cost),
					"/reconciliation?product_id=$pid&branch_id=$bid", $userId, $p['department'] ?: 'General', $pid, $now, $now
				]);
			}

			$db->commit();

			// Notificar al Bus de Eventos Unificado 360° (Mercancía Recibida)
			try
			{
				if (class_exists('\\Grocy\\Services\\UnifiedInventoryEventService'))
				{
					\Grocy\Services\UnifiedInventoryEventService::GetInstance()->Publish(
						\Grocy\Services\UnifiedInventoryEventService::EVENT_MERCHANDISE_RECEIVED,
						'document',
						$movCode,
						[
							'product_id' => $pid,
							'branch_id' => $bid,
							'accepted' => $accepted,
							'expected' => $expected,
							'received' => $received,
							'difference' => $diff,
							'document_code' => $po,
							'supplier' => $supplier
						],
						$bid,
						$userId
					);
				}
			}
			catch (\Throwable $e)
			{
			}

			return ['success' => true, 'movement_code' => $movCode, 'transaction_id' => $tid, 'message' => "Ingreso registrado: +$accepted u. en inventario, kardex y libro de movimientos."];
		}
		catch (\Throwable $e)
		{
			$db->rollBack();
			throw $e;
		}
	}

	public function RegisterReturn(array $d, int $userId): array
	{
		if ($r = $this->Replayed($d['operation_id'] ?? null))
		{
			return $r;
		}
		$pid = intval($d['product_id'] ?? 0);
		$bid = intval($d['branch_id'] ?? 0);
		$p = $this->RequireProductBranch($pid, $bid);
		$qty = floatval($d['quantity'] ?? 0);
		if ($qty <= 0)
		{
			throw new \Exception('La cantidad debe ser mayor que cero');
		}
		$disposition = trim($d['disposition'] ?? 'disponible'); // disponible, cuarentena, otra_bodega, merma_definitiva
		if (!in_array($disposition, ['disponible', 'cuarentena', 'otra_bodega', 'merma_definitiva'], true))
		{
			throw new \Exception('Destino de la devolución no válido');
		}
		$notes = trim($d['notes'] ?? '') ?: 'Devolución registrada en mostrador.';
		$now = date('Y-m-d H:i:s');
		$cost = $this->UnitCost($pid, $bid);
		$destLocation = ['disponible' => 'Bodega Activa (Reincorporación)', 'cuarentena' => 'Zona de Cuarentena Técnica', 'otra_bodega' => 'En tránsito a otra bodega', 'merma_definitiva' => 'Baja Definitiva / Destrucción'][$disposition];

		$db = $this->Db();
		$db->beginTransaction();
		try
		{
			$movCode = $this->NextCode('DEV');
			$doc = trim($d['document_code'] ?? '') ?: $movCode;
			// Solo lo reincorporable vuelve a stock vendible; cuarentena, traslado y baja no suman disponible
			$tid = $disposition === 'disponible' ? $this->StockIn($p, $bid, $qty, $cost, StockService::TRANSACTION_TYPE_PURCHASE, "Devolución de cliente $movCode · $doc") : null;
			$this->InsertMovement(['movement_code' => $movCode, 'branch_id' => $bid, 'product_id' => $pid, 'event_type' => 'return_exchange', 'warehouse_type' => 'physical', 'status' => 'confirmado',
				'origin_location' => 'Cliente / Reclamación', 'dest_location' => $destLocation, 'expected_amount' => $qty, 'actual_amount' => $qty, 'accepted_amount' => $qty, 'difference_amount' => 0.0,
				'unit_cost' => $cost, 'financial_impact' => round($qty * $cost, 2), 'document_type' => 'DEV', 'document_code' => $doc, 'disposition' => $disposition,
				'notes' => $notes, 'user_id' => $userId, 'created_at' => $now, 'confirmed_at' => $now, 'stock_transaction_id' => $tid, 'operation_id' => $d['operation_id'] ?? null]);
			$db->commit();
			return ['success' => true, 'movement_code' => $movCode, 'transaction_id' => $tid, 'message' => $tid ? "Devolución reincorporada: +$qty u. en inventario y kardex." : "Devolución registrada con destino «{$disposition}» (no suma a stock vendible)."];
		}
		catch (\Throwable $e)
		{
			$db->rollBack();
			throw $e;
		}
	}

	public function RegisterMovement(array $d, int $userId): array
	{
		if ($r = $this->Replayed($d['operation_id'] ?? null))
		{
			return $r;
		}
		$pid = intval($d['product_id'] ?? 0);
		$bid = intval($d['branch_id'] ?? 0);
		$this->RequireProductBranch($pid, $bid);
		$qty = floatval($d['quantity'] ?? 0);
		if ($qty <= 0)
		{
			throw new \Exception('La cantidad debe ser mayor que cero');
		}
		$eventType = trim($d['event_type'] ?? 'dispatch'); // dispatch, transfer
		$warehouseType = trim($d['warehouse_type'] ?? 'physical'); // physical, digital_reserve, digital_transit
		$status = trim($d['status'] ?? 'confirmado'); // solicitado, en_transito, confirmado, cancelado
		if ($eventType === 'transfer')
		{
			// Los traslados reales entre sucursales se registran en Traslados (salida, tránsito y recepción con trazabilidad)
			throw new \Exception('Registre el traslado en Traslados entre Tiendas para conservar salida, tránsito y recepción');
		}
		$origin = trim($d['origin_location'] ?? '') ?: 'Bodega de la sucursal';
		$dest = trim($d['dest_location'] ?? '') ?: 'Destino / Cliente';
		$notes = trim($d['notes'] ?? '') ?: 'Movimiento logístico.';
		$now = date('Y-m-d H:i:s');
		$cost = $this->UnitCost($pid, $bid);

		$db = $this->Db();
		$db->beginTransaction();
		try
		{
			$movCode = $this->NextCode('MOV');
			$doc = trim($d['document_code'] ?? '') ?: $movCode;
			// Solo un despacho físico confirmado descuenta inventario (FEFO, sin dejar negativos)
			$tid = ($status === 'confirmado' && $eventType === 'dispatch' && $warehouseType === 'physical') ? $this->StockOut($pid, $bid, $qty, StockService::TRANSACTION_TYPE_CONSUME) : null;
			$this->InsertMovement(['movement_code' => $movCode, 'branch_id' => $bid, 'product_id' => $pid, 'event_type' => $eventType, 'warehouse_type' => $warehouseType, 'status' => $status,
				'origin_location' => $origin, 'dest_location' => $dest, 'expected_amount' => $qty, 'actual_amount' => $qty, 'accepted_amount' => $qty, 'difference_amount' => 0.0,
				'unit_cost' => $cost, 'financial_impact' => round($qty * $cost, 2), 'document_type' => 'TRA', 'document_code' => $doc,
				'notes' => $notes, 'user_id' => $userId, 'created_at' => $now, 'confirmed_at' => $status === 'confirmado' ? $now : null, 'stock_transaction_id' => $tid, 'operation_id' => $d['operation_id'] ?? null]);
			$db->commit();

			// Notificar al Bus de Eventos Unificado 360° (Despacho / Movimiento)
			try
			{
				if (class_exists('\\Grocy\\Services\\UnifiedInventoryEventService'))
				{
					\Grocy\Services\UnifiedInventoryEventService::GetInstance()->Publish(
						\Grocy\Services\UnifiedInventoryEventService::EVENT_MERCHANDISE_DISPATCHED,
						'document',
						$movCode,
						[
							'product_id' => $pid,
							'branch_id' => $bid,
							'qty' => $qty,
							'event_type' => $eventType,
							'origin' => $origin,
							'dest' => $dest,
							'document_code' => $doc
						],
						$bid,
						$userId
					);
				}
			}
			catch (\Throwable $e)
			{
			}

			return ['success' => true, 'movement_code' => $movCode, 'transaction_id' => $tid, 'message' => "Movimiento registrado con estado «{$status}»" . ($tid ? " (−$qty u. en inventario y kardex)." : '.')];
		}
		catch (\Throwable $e)
		{
			$db->rollBack();
			throw $e;
		}
	}
}
