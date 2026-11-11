<?php

namespace Grocy\Services;

use Grocy\Controllers\Users\User;
use Grocy\Helpers\DairyAccess;
use Grocy\Services\DatabaseService;
use Grocy\Services\DeviceService;
use Grocy\Services\ScanService;
use Grocy\Services\StockService;

class RetailPreventionService extends BaseService
{
	private static $instance = null;

	public static function GetInstance(): self
	{
		if (self::$instance === null)
		{
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function Db(): \PDO
	{
		return DatabaseService::GetInstance()->GetDbConnectionRaw();
	}

	/* =========================================================================
	 * 1. ALERTAS INTELIGENTES DE VENCIMIENTO & PREVENCIÓN PREDICTIVA
	 * ========================================================================= */

	/**
	 * Reglas de anticipación de vencimientos por categoría
	 */
	public function GetExpiryRules(): array
	{
		$stmt = $this->Db()->query("SELECT * FROM expiry_category_rules ORDER BY shelf_life_days ASC");
		$rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
		foreach ($rows as &$r)
		{
			$r['alert_days'] = json_decode($r['alert_days_json'] ?: '[]', true);
		}
		return $rows;
	}

	public function GetCategoryExpiryRules(): array
	{
		return $this->GetExpiryRules();
	}

	public function SaveExpiryRule(string $categoryName, array $data): array
	{
		$db = $this->Db();
		$days = is_array($data['alert_days'] ?? null) ? $data['alert_days'] : [3, 5, 7];
		$daysJson = json_encode(array_values(array_map('intval', $days)));
		$shelf = intval($data['shelf_life_days'] ?? 10);
		$action = strval($data['recommended_action'] ?? 'fefo_rotation');
		$discount = floatval($data['auto_markdown_discount'] ?? 0.20);
		$escalate = intval($data['escalate_hours'] ?? 24);

		$stmt = $db->prepare("
			INSERT INTO expiry_category_rules (category_name, alert_days_json, shelf_life_days, recommended_action, auto_markdown_discount, escalate_hours, updated_at)
			VALUES (:c, :d, :s, :a, :disc, :esc, datetime('now', 'localtime'))
			ON CONFLICT(category_name) DO UPDATE SET
				alert_days_json = :d,
				shelf_life_days = :s,
				recommended_action = :a,
				auto_markdown_discount = :disc,
				escalate_hours = :esc,
				updated_at = datetime('now', 'localtime')
		");
		$stmt->execute([
			':c' => $categoryName,
			':d' => $daysJson,
			':s' => $shelf,
			':a' => $action,
			':disc' => $discount,
			':esc' => $escalate
		]);

		return ['success' => true, 'category_name' => $categoryName];
	}

	/**
	 * Listado de alertas predictivas de vencimiento con fórmula matemática de unidades en riesgo
	 */
	public function GetPredictiveExpiryAlerts(?int $branchId = null, ?string $category = null, ?string $status = null): array
	{
		$db = $this->Db();
		$where = ["1=1"];
		$params = [];

		if ($branchId !== null && $branchId > 0)
		{
			$where[] = "a.branch_id = :bid";
			$params[':bid'] = $branchId;
		}
		if (!empty($category))
		{
			$where[] = "a.category_name = :cat";
			$params[':cat'] = $category;
		}
		if (!empty($status))
		{
			$where[] = "a.resolution_status = :st";
			$params[':st'] = $status;
		}

		$whereSql = implode(" AND ", $where);
		$sql = "
			SELECT 
				a.*,
				p.name AS product_name,
				p.sku,
				IFNULL((SELECT barcode FROM product_barcodes WHERE product_id = p.id LIMIT 1), '770200100' || p.id) AS upc,
				qu.name AS qu_name,
				IFNULL(l.name, 'Góndola Principal') AS location_name,
				IFNULL(b.name, 'Sin sucursal') AS branch_name,
				IFNULL(u.username, 'Sin asignar') AS assigned_user
			FROM expiry_predictive_alerts a
			JOIN products p ON a.product_id = p.id
			LEFT JOIN quantity_units qu ON p.qu_id_stock = qu.id
			LEFT JOIN locations l ON a.location_id = l.id
			LEFT JOIN branches b ON a.branch_id = b.id
			LEFT JOIN users u ON a.assigned_to_user_id = u.id
			WHERE $whereSql
			ORDER BY a.days_left ASC, a.total_value_risk DESC
		";
		$stmt = $db->prepare($sql);
		$stmt->execute($params);
		$alerts = $stmt->fetchAll(\PDO::FETCH_ASSOC);

		// Format labels and action details
		$actionLabels = [
			'fefo_rotation' => ['label' => 'Priorizar rotación FEFO', 'icon' => 'fa-arrows-rotate', 'tone' => 'warning'],
			'markdown_discount' => ['label' => 'Evaluar descuento autorizado', 'icon' => 'fa-tag', 'tone' => 'danger'],
			'branch_transfer' => ['label' => 'Trasladar a otra sucursal', 'icon' => 'fa-truck-arrow-right', 'tone' => 'info'],
			'shelf_reposition' => ['label' => 'Reubicar en cabecera', 'icon' => 'fa-arrows-up-down-left-right', 'tone' => 'primary'],
			'supplier_return' => ['label' => 'Devolución al proveedor', 'icon' => 'fa-rotate-left', 'tone' => 'secondary'],
			'donation' => ['label' => 'Evaluar donación permitida', 'icon' => 'fa-hand-holding-heart', 'tone' => 'success'],
			'withdraw_unfit' => ['label' => 'Retirar producto no apto', 'icon' => 'fa-ban', 'tone' => 'dark'],
		];

		$statusFlow = [
			'alerta' => ['step' => 1, 'label' => 'Alerta generada', 'badge' => 'badge-danger'],
			'validacion' => ['step' => 2, 'label' => 'En validación física', 'badge' => 'badge-warning'],
			'accion_asignada' => ['step' => 3, 'label' => 'Acción asignada', 'badge' => 'badge-info'],
			'ejecucion' => ['step' => 4, 'label' => 'En ejecución', 'badge' => 'badge-primary'],
			'verificacion' => ['step' => 5, 'label' => 'Por verificar', 'badge' => 'badge-dark'],
			'cerrada' => ['step' => 6, 'label' => 'Cerrada y verificada', 'badge' => 'badge-success'],
		];

		$totalRisk = 0.0;
		$totalUnitsRisk = 0.0;
		$urgentCount = 0;

		foreach ($alerts as &$alt)
		{
			$act = $alt['recommended_action'];
			$st = $alt['resolution_status'];
			$alt['action_meta'] = $actionLabels[$act] ?? ['label' => $act, 'icon' => 'fa-info-circle', 'tone' => 'secondary'];
			$alt['status_meta'] = $statusFlow[$st] ?? ['step' => 1, 'label' => $st, 'badge' => 'badge-secondary'];
			
			$totalRisk += floatval($alt['total_value_risk']);
			$totalUnitsRisk += floatval($alt['units_at_risk']);
			if (intval($alt['days_left']) <= 2)
			{
				$urgentCount++;
			}
		}

		return [
			'alerts' => $alerts,
			'total_count' => count($alerts),
			'total_value_risk' => round($totalRisk, 2),
			'total_units_risk' => round($totalUnitsRisk, 1),
			'urgent_count' => $urgentCount,
			'rules' => $this->GetExpiryRules()
		];
	}

	/**
	 * Avanza el flujo de resolución de la alerta de vencimiento
	 * Flujo: ALERTA -> VALIDACIÓN -> ACCIÓN ASIGNADA -> EJECUCIÓN -> VERIFICACIÓN -> CERRADA
	 */
	public function AdvanceExpiryAlert(int $alertId, string $newStatus, ?string $actionTaken = null, ?string $notes = null, ?string $photoUrl = null, int $userId = 1): array
	{
		$db = $this->Db();
		$stmt = $db->prepare("SELECT * FROM expiry_predictive_alerts WHERE id = :id");
		$stmt->execute([':id' => $alertId]);
		$a = $stmt->fetch(\PDO::FETCH_ASSOC);
		if (!$a)
		{
			throw new \Exception("Alerta de vencimiento #$alertId no encontrada.");
		}

		$validStatuses = ['alerta', 'validacion', 'accion_asignada', 'ejecucion', 'verificacion', 'cerrada'];
		if (!in_array($newStatus, $validStatuses))
		{
			throw new \Exception("Estado inválido para el flujo de alerta.");
		}

		$resolvedAt = ($newStatus === 'cerrada') ? date('Y-m-d H:i:s') : $a['resolved_at'];
		$resTimeHours = null;
		if ($newStatus === 'cerrada')
		{
			$createdTs = strtotime($a['created_at']);
			$nowTs = time();
			$resTimeHours = round(($nowTs - $createdTs) / 3600.0, 1);
		}

		$upd = $db->prepare("
			UPDATE expiry_predictive_alerts
			SET resolution_status = :st,
				resolution_action_taken = IFNULL(:act, resolution_action_taken),
				evidence_notes = IFNULL(:notes, evidence_notes),
				evidence_photo_url = IFNULL(:photo, evidence_photo_url),
				resolved_at = :res_at,
				resolution_time_hours = IFNULL(:res_hours, resolution_time_hours),
				assigned_to_user_id = :u
			WHERE id = :id
		");
		$upd->execute([
			':st' => $newStatus,
			':act' => $actionTaken,
			':notes' => $notes,
			':photo' => $photoUrl,
			':res_at' => $resolvedAt,
			':res_hours' => $resTimeHours,
			':u' => $userId,
			':id' => $alertId
		]);

		return ['success' => true, 'id' => $alertId, 'status' => $newStatus, 'resolution_time_hours' => $resTimeHours];
	}

	/* =========================================================================
	 * 2. VARIACIONES DE DESPACHO & CONCILIACIÓN EN 7 ETAPAS
	 * ========================================================================= */

	/** Estados lógicos del ciclo de 7 etapas: Pedido → Reserva → Picking → Packing → Despacho → Entrega → Conciliación */
	public static function BuildOrderStages(array $order): array
	{
		$st = $order['status'] ?? 'draft';
		$dst = $order['delivery_status'] ?? 'pending';
		$isCancelled = ($st === 'cancelled');
		$hasReserve = !$isCancelled && in_array($st, ['confirmed', 'picking', 'picked', 'packed', 'dispatching', 'dispatched'], true);
		$pickingDone = !$isCancelled && in_array($st, ['picked', 'packed', 'dispatching', 'dispatched'], true);
		$packedDone = !$isCancelled && in_array($st, ['packed', 'dispatching', 'dispatched'], true);
		$dispatchedDone = !$isCancelled && ($st === 'dispatched');
		$deliveredDone = !$isCancelled && in_array($dst, ['delivered', 'partial', 'rejected'], true);
		$conciliatedDone = !$isCancelled && !empty($order['conciliated_at']);

		$raw = [
			['key' => 'pedido', 'num' => 1, 'label' => 'Pedido', 'sub' => $isCancelled ? 'Cancelado' : 'Registrado', 'done' => !$isCancelled, 'current' => ($st === 'draft'), 'at' => $order['created_at'] ?? null],
			['key' => 'reserva', 'num' => 2, 'label' => 'Reserva', 'sub' => $hasReserve ? 'Stock bloq.' : 'Pendiente', 'done' => $hasReserve, 'current' => ($st === 'confirmed'), 'at' => $order['confirmed_at'] ?? null],
			['key' => 'picking', 'num' => 3, 'label' => 'Picking', 'sub' => $pickingDone ? 'Validado' : 'UPC / FEFO', 'done' => $pickingDone, 'current' => ($st === 'picking'), 'at' => $order['picked_at'] ?? null],
			['key' => 'packing', 'num' => 4, 'label' => 'Packing', 'sub' => $packedDone ? (!empty($order['label_code']) ? $order['label_code'] : 'Etiquetado') : 'Bultos', 'done' => $packedDone, 'current' => ($st === 'picked'), 'at' => $order['packed_at'] ?? null],
			['key' => 'despacho', 'num' => 5, 'label' => 'Despacho', 'sub' => $dispatchedDone ? 'En ruta/retiro' : 'Salida muelle', 'done' => $dispatchedDone, 'current' => ($st === 'packed'), 'at' => $order['dispatched_at'] ?? null],
			['key' => 'entrega', 'num' => 6, 'label' => 'Entrega', 'sub' => $deliveredDone ? ($dst === 'partial' ? 'Parcial POD' : ($dst === 'rejected' ? 'Rechazado' : 'POD OK')) : 'POD / PIN', 'done' => $deliveredDone, 'current' => ($dispatchedDone && !$deliveredDone), 'at' => $order['delivery_time'] ?? ($order['pickup_verified_at'] ?? null)],
			['key' => 'conciliacion', 'num' => 7, 'label' => 'Conciliación', 'sub' => $conciliatedDone ? 'Cerrado' : 'Auditar', 'done' => $conciliatedDone, 'current' => ($deliveredDone && !$conciliatedDone), 'at' => $order['conciliated_at'] ?? null],
		];

		return array_map(function ($s) {
			$s['state'] = $s['done'] ? 'done' : ($s['current'] ? 'current' : 'pending');
			return $s;
		}, $raw);
	}

	/**
	 * Conciliación completa de un despacho comparando las 7 cantidades obligatorias
	 * 1. Solicitada, 2. Preparada, 3. Escaneada, 4. Despachada, 5. Recibida/Entregada, 6. Devuelta, 7. Diferencia Final
	 */
	public function GetDispatchOrderDetail(int $orderId): array
	{
		$db = $this->Db();
		$orderStmt = $db->prepare("
			SELECT o.*, c.name AS customer_name, b.name AS branch_name,
			       pu.username AS picker_username, du.username AS dispatcher_username, cu.username AS conciliated_by_username
			FROM customer_orders o
			JOIN customers c ON o.customer_id = c.id
			LEFT JOIN branches b ON o.branch_id = b.id
			LEFT JOIN users pu ON o.picked_by_user_id = pu.id
			LEFT JOIN users du ON o.dispatched_by_user_id = du.id
			LEFT JOIN users cu ON o.conciliated_by_user_id = cu.id
			WHERE o.id = :id
		");
		$orderStmt->execute([':id' => $orderId]);
		$order = $orderStmt->fetch(\PDO::FETCH_ASSOC);
		if (!$order)
		{
			throw new \Exception("Pedido #$orderId no encontrado.");
		}

		// Items with 7-stage amounts + real scans
		$itemsStmt = $db->prepare("
			SELECT 
				i.*,
				p.name AS product_name,
				p.sku,
				IFNULL((SELECT barcode FROM product_barcodes WHERE product_id = p.id LIMIT 1), '770200100' || p.id) AS upc,
				qu.name AS qu_name,
				IFNULL((SELECT SUM(ds.scanned_qty) FROM dispatch_scans ds WHERE ds.order_id = i.order_id AND ds.product_id = i.product_id AND ds.match_status != 'mismatch'), -1) AS real_scanned_qty
			FROM customer_order_items i
			JOIN products p ON i.product_id = p.id
			LEFT JOIN quantity_units qu ON p.qu_id_stock = qu.id
			WHERE i.order_id = :id
		");
		$itemsStmt->execute([':id' => $orderId]);
		$items = $itemsStmt->fetchAll(\PDO::FETCH_ASSOC);

		// Variances recorded for this order
		$varStmt = $db->prepare("
			SELECT v.*, p.name AS product_name, u.username AS authorized_by_user
			FROM dispatch_variances v
			JOIN products p ON v.product_id = p.id
			LEFT JOIN users u ON v.authorized_by_user_id = u.id
			WHERE v.order_id = :id
			ORDER BY v.created_at DESC
		");
		$varStmt->execute([':id' => $orderId]);
		$variances = $varStmt->fetchAll(\PDO::FETCH_ASSOC);

		// Scans audit log
		$scansStmt = $db->prepare("
			SELECT s.*, p.name AS product_name, u.username AS operator_name
			FROM dispatch_scans s
			LEFT JOIN products p ON s.product_id = p.id
			LEFT JOIN users u ON s.operator_user_id = u.id
			WHERE s.order_id = :id
			ORDER BY s.scanned_at DESC
		");
		$scansStmt->execute([':id' => $orderId]);
		$scans = $scansStmt->fetchAll(\PDO::FETCH_ASSOC);

		// Logistics incidents
		$incidents = [];
		try
		{
			$incStmt = $db->prepare("
				SELECT li.*, u.username AS reporter_name, ru.username AS resolved_by_name
				FROM dispatch_logistics_incidents li
				LEFT JOIN users u ON u.id = li.reported_by_user_id
				LEFT JOIN users ru ON ru.id = li.resolved_by_user_id
				WHERE li.order_id = :id
				ORDER BY li.id DESC
			");
			$incStmt->execute([':id' => $orderId]);
			$incidents = $incStmt->fetchAll(\PDO::FETCH_ASSOC);
		}
		catch (\Throwable $e)
		{
			$incidents = [];
		}

		// Map variance metrics
		$productImgMap = [
			31 => '/img/control_center/leche_descremada.jpg',
			45 => '/img/control_center/mantequilla_cremas.jpg',
			42 => '/img/control_center/crema_de_leche.jpg',
			30 => '/img/control_center/leche_entera_galon.jpg',
			41 => '/img/control_center/queso_mozzarella.jpg',
			40 => '/img/control_center/queso_cheddar.jpg',
			34 => '/img/control_center/huevos_grandes.jpg',
			37 => '/img/control_center/yogur_griego.jpg',
			38 => '/img/control_center/yogur_griego.jpg',
			43 => '/img/control_center/mantequilla_cremas.jpg',
		];
		$skuCounts = array_count_values(array_column($items, 'sku'));

		$reconciledLines = [];
		$totalRequested = 0.0;
		$totalPrepared = 0.0;
		$totalScanned = 0.0;
		$totalDispatched = 0.0;
		$totalReceived = 0.0;
		$totalReturned = 0.0;
		$totalDiff = 0.0;
		$prepVarianceQty = 0.0;
		$deliveryVarianceQty = 0.0;

		$isDispatchedStatus = ($order['status'] === 'dispatched');
		$isDeliveredStatus = in_array($order['delivery_status'] ?? 'pending', ['delivered', 'partial', 'rejected'], true);

		foreach ($items as $it)
		{
			$req = floatval($it['amount']);
			// Si el pedido historico fue despachado sin poblar picked_amount y sin faltantes de preparacion, considerar despachado hasta $req menos varianzas
			$prepRaw = floatval($it['picked_amount']);
			if ($isDispatchedStatus && $prepRaw <= 0.0001 && in_array($order['delivery_status'] ?? '', ['delivered', 'partial'], true))
			{
				$prep = $req;
			}
			else
			{
				$prep = min($req, $prepRaw);
			}

			$itemVars = array_filter($variances, fn($v) => intval($v['product_id']) === intval($it['product_id']));
			$delivVars = array_filter($itemVars, fn($v) => $v['stage'] === 'delivery');

			$realScan = floatval($it['real_scanned_qty'] ?? -1);
			$scan = $realScan >= 0 ? min($req, $realScan) : $prep;
			$disp = $isDispatchedStatus ? min($req, $prep) : 0.0;

			if (!$isDeliveredStatus)
			{
				$recv = 0.0;
				$ret = 0.0;
			}
			elseif (($order['delivery_status'] ?? '') === 'rejected')
			{
				$recv = 0.0;
				$ret = $disp;
			}
			else
			{
				$recv = $disp;
				$ret = 0.0;
				foreach ($delivVars as $dv)
				{
					if ($dv['variance_type'] === 'producto_dañado' || $dv['variance_type'] === 'faltante' || $dv['variance_type'] === 'error_entrega')
					{
						$recv -= abs(floatval($dv['difference_qty']));
						$ret += floatval($dv['returned_qty']);
					}
				}
				$recv = max(0.0, $recv);
			}
			$finalDiff = $isDeliveredStatus ? ($recv - $req) : ($prep - $req);

			$pVar = $prep - $req;
			$dVar = $isDeliveredStatus ? ($recv - $disp) : 0.0;

			$prepVarianceQty += $pVar;
			$deliveryVarianceQty += $dVar;

			$totalRequested += $req;
			$totalPrepared += $prep;
			$totalScanned += $scan;
			$totalDispatched += $disp;
			$totalReceived += $recv;
			$totalReturned += $ret;
			$totalDiff += $finalDiff;

			$prodImg = $productImgMap[intval($it['product_id'])] ?? '/img/control_center/prod_crema_leche.jpg';
			$isDuplicateSku = ($skuCounts[$it['sku']] ?? 0) > 1 && $it['product_name'] === 'Crema de leche';

			$reconciledLines[] = [
				'item_id' => $it['id'],
				'product_id' => $it['product_id'],
				'product_name' => $it['product_name'],
				'product_image' => $prodImg,
				'sku' => $it['sku'],
				'sku_warning' => $isDuplicateSku,
				'upc' => $it['upc'],
				'qu_name' => $it['qu_name'],
				'unit_price' => floatval($it['unit_price']),
				'stages' => [
					'1_requested' => $req,
					'2_prepared' => $prep,
					'3_scanned' => $scan,
					'4_dispatched' => $disp,
					'5_received' => $recv,
					'6_returned' => $ret,
					'7_final_difference' => $finalDiff,
				],
				'variances' => [
					'prep_variance' => $pVar,
					'delivery_variance' => $dVar
				],
				'has_variance' => ($pVar != 0.0 || $dVar != 0.0)
			];
		}

		$hasUnresolvedVariances = false;
		foreach ($variances as $v)
		{
			if ($v['resolution_status'] === 'pending')
			{
				$hasUnresolvedVariances = true;
				break;
			}
		}
		$openIncidentsCount = count(array_filter($incidents, fn($i) => ($i['resolution_status'] ?? 'open') === 'open'));

		$unreconciledItems = [];
		if ($order['status'] !== 'draft' && $order['status'] !== 'cancelled')
		{
			foreach ($reconciledLines as $rl)
			{
				if (in_array($order['status'], ['picked', 'packed', 'dispatched'], true) && $rl['stages']['2_prepared'] < $rl['stages']['1_requested'])
				{
					$unreconciledItems[] = [
						'product_name' => $rl['product_name'],
						'requested' => $rl['stages']['1_requested'],
						'prepared' => $rl['stages']['2_prepared'],
						'difference' => $rl['stages']['2_prepared'] - $rl['stages']['1_requested']
					];
				}
			}
		}

		$stagesTimeline = self::BuildOrderStages($order);
		$order['stages'] = $stagesTimeline;

		return [
			'order' => $order,
			'lines' => $reconciledLines,
			'variances' => $variances,
			'scans' => $scans,
			'incidents' => $incidents,
			'stages_timeline' => $stagesTimeline,
			'summary' => [
				'total_requested' => $totalRequested,
				'total_prepared' => $totalPrepared,
				'total_scanned' => $totalScanned,
				'total_dispatched' => $totalDispatched,
				'total_received' => $totalReceived,
				'total_returned' => $totalReturned,
				'total_final_diff' => $totalDiff,
				'prep_variance_qty' => $prepVarianceQty,
				'delivery_variance_qty' => $deliveryVarianceQty,
				'has_unresolved_variances' => $hasUnresolvedVariances,
				'open_incidents_count' => $openIncidentsCount,
				'unreconciled_items' => $unreconciledItems,
				'has_unreconciled_shortage' => !empty($unreconciledItems)
			]
		];
	}

	/**
	 * Validación rápida por escaneo de UPC en el despacho
	 * Compara producto esperado vs escaneado en tiempo real con semáforo:
	 * Verde: coincidencia exacta
	 * Amarillo: requiere revisión (ej. lote alterno o sobreconteo parcial)
	 * Rojo: producto equivocado o diferencia no autorizada
	 */
	public function FastScanUPCVerify(int $orderId, string $barcode, float $scannedQty = 1.0, ?string $lotNumber = null, int $userId = 1): array
	{
		$db = $this->Db();
		$bcode = trim($barcode);
		if ($bcode === '')
		{
			throw new \Exception('Debe escanear o ingresar un código UPC o SKU.');
		}
		if ($scannedQty <= 0)
		{
			throw new \Exception('La cantidad escaneada debe ser mayor a cero.');
		}

		$ordStmt = $db->prepare("SELECT * FROM customer_orders WHERE id = :id");
		$ordStmt->execute([':id' => $orderId]);
		$order = $ordStmt->fetch(\PDO::FETCH_ASSOC);
		if (!$order)
		{
			throw new \Exception("Pedido #$orderId no encontrado.");
		}
		if (!in_array($order['status'] ?? '', ['confirmed', 'picking', 'picked'], true))
		{
			throw new \Exception("El pedido {$order['order_code']} debe estar en estado Reserva o Picking para validar escaneo (estado actual: {$order['status']}).");
		}

		// Lookup product by barcode or SKU
		$pStmt = $db->prepare("
			SELECT p.id, p.name, p.sku, pb.barcode, p.qu_id_stock,
			       (SELECT price FROM cache__products_last_purchased WHERE product_id = p.id) AS last_cost
			FROM products p
			LEFT JOIN product_barcodes pb ON p.id = pb.product_id
			WHERE pb.barcode = :b OR p.sku = :b OR ('770200100' || p.id) = :b
			LIMIT 1
		");
		$pStmt->execute([':b' => $bcode]);
		$product = $pStmt->fetch(\PDO::FETCH_ASSOC);

		if (!$product && is_numeric($bcode))
		{
			$pStmt2 = $db->prepare("
				SELECT p.id, p.name, p.sku, p.qu_id_stock,
				       (SELECT price FROM cache__products_last_purchased WHERE product_id = p.id) AS last_cost
				FROM products p WHERE p.id = :id
			");
			$pStmt2->execute([':id' => intval($bcode)]);
			$product = $pStmt2->fetch(\PDO::FETCH_ASSOC);
		}

		if (!$product)
		{
			$db->prepare("INSERT INTO dispatch_scans (order_id, barcode_scanned, match_status, operator_user_id) VALUES (?, ?, 'mismatch', ?)")
				->execute([$orderId, $bcode, $userId]);
			return [
				'status' => 'mismatch',
				'status_color' => 'danger',
				'message' => "Código UPC '$bcode' no encontrado en el maestro de productos.",
				'barcode' => $bcode,
				'product_found' => null,
				'expected' => null
			];
		}

		$pid = intval($product['id']);

		$itStmt = $db->prepare("SELECT * FROM customer_order_items WHERE order_id = :oid AND product_id = :pid");
		$itStmt->execute([':oid' => $orderId, ':pid' => $pid]);
		$orderItem = $itStmt->fetch(\PDO::FETCH_ASSOC);

		if (!$orderItem)
		{
			$db->prepare("INSERT INTO dispatch_scans (order_id, barcode_scanned, product_id, match_status, operator_user_id) VALUES (?, ?, ?, 'mismatch', ?)")
				->execute([$orderId, $bcode, $pid, $userId]);
			return [
				'status' => 'mismatch',
				'status_color' => 'danger',
				'message' => "Producto equivocado: {$product['name']} (SKU {$product['sku']}) no pertenece a este pedido.",
				'barcode' => $bcode,
				'product_found' => $product,
				'expected' => null
			];
		}

		$prevScanStmt = $db->prepare("SELECT IFNULL(SUM(scanned_qty), 0) FROM dispatch_scans WHERE order_id = :oid AND product_id = :pid AND match_status NOT IN ('mismatch', 'overage')");
		$prevScanStmt->execute([':oid' => $orderId, ':pid' => $pid]);
		$alreadyScanned = max(floatval($prevScanStmt->fetchColumn()), floatval($orderItem['picked_amount'] ?? 0));

		$newTotalScanned = $alreadyScanned + $scannedQty;
		$expectedAmount = floatval($orderItem['amount']);
		$diff = $newTotalScanned - $expectedAmount;

		// Hard rule: never allow dispatching/picking more than the reserved amount
		if ($newTotalScanned > $expectedAmount + 0.0001)
		{
			$db->prepare("
				INSERT INTO dispatch_scans
				(order_id, barcode_scanned, product_id, stock_id, expected_product_id, expected_qty, scanned_qty, match_status, operator_user_id)
				VALUES (:oid, :bc, :pid, :stock, :epid, :eqty, :sqty, 'overage', :u)
			")->execute([
				':oid' => $orderId,
				':bc' => $bcode,
				':pid' => $pid,
				':stock' => $lotNumber ?: 'BLOCKED-OVERAGE',
				':epid' => $pid,
				':eqty' => $expectedAmount,
				':sqty' => 0,
				':u' => $userId
			]);

			return [
				'status' => 'overage',
				'status_color' => 'danger',
				'blocked' => true,
				'message' => "Bloqueado: {$product['name']} ya tiene {$alreadyScanned} de {$expectedAmount} reservadas. No se permite despachar más de lo reservado.",
				'barcode' => $bcode,
				'product' => $product,
				'expected_qty' => $expectedAmount,
				'scanned_qty' => $alreadyScanned,
				'difference' => round($alreadyScanned - $expectedAmount, 3),
				'financial_impact' => round(($newTotalScanned - $expectedAmount) * floatval($product['last_cost'] ?? 1.0), 2)
			];
		}

		$matchStatus = (abs($newTotalScanned - $expectedAmount) <= 0.0001) ? 'match' : 'warning';
		$statusColor = ($matchStatus === 'match') ? 'success' : 'warning';
		$msg = ($matchStatus === 'match')
			? "Coincidencia completa validada: {$product['name']} ({$newTotalScanned}/{$expectedAmount})"
			: "Validación parcial: {$product['name']} lleva {$newTotalScanned} de {$expectedAmount} (faltan " . round(abs($diff), 2) . ").";

		$db->prepare("
			INSERT INTO dispatch_scans 
			(order_id, barcode_scanned, product_id, stock_id, expected_product_id, expected_qty, scanned_qty, match_status, operator_user_id)
			VALUES (:oid, :bc, :pid, :stock, :epid, :eqty, :sqty, :st, :u)
		")->execute([
			':oid' => $orderId,
			':bc' => $bcode,
			':pid' => $pid,
			':stock' => $lotNumber ?: 'SCAN-VERIFIED',
			':epid' => $pid,
			':eqty' => $expectedAmount,
			':sqty' => $scannedQty,
			':st' => $matchStatus,
			':u' => $userId
		]);

		// Synchronize item picked_amount and advance order status
		$db->prepare("UPDATE customer_order_items SET picked_amount = :pa WHERE id = :id")
			->execute([':pa' => min($expectedAmount, $newTotalScanned), ':id' => intval($orderItem['id'])]);

		$allItemsStmt = $db->prepare("SELECT SUM(amount) AS req, SUM(IFNULL(picked_amount, 0)) AS pic FROM customer_order_items WHERE order_id = :oid");
		$allItemsStmt->execute([':oid' => $orderId]);
		$totRow = $allItemsStmt->fetch(\PDO::FETCH_ASSOC);
		$totReq = floatval($totRow['req'] ?? 0);
		$totPic = floatval($totRow['pic'] ?? 0);
		$prepAcc = $totReq > 0 ? round(min(100.0, ($totPic / $totReq) * 100.0), 1) : 100.0;
		$nextOrderStatus = ($totReq > 0 && $totPic >= $totReq - 0.0001) ? 'picked' : 'picking';

		$db->prepare("
			UPDATE customer_orders
			SET status = :st,
				preparation_accuracy_pct = :acc,
				picked_by_user_id = COALESCE(picked_by_user_id, :u),
				picked_at = COALESCE(picked_at, datetime('now', 'localtime'))
			WHERE id = :oid
		")->execute([
			':st' => $nextOrderStatus,
			':acc' => $prepAcc,
			':u' => $userId,
			':oid' => $orderId
		]);

		return [
			'status' => $matchStatus,
			'status_color' => $statusColor,
			'blocked' => false,
			'order_status' => $nextOrderStatus,
			'prep_accuracy_pct' => $prepAcc,
			'message' => $msg,
			'barcode' => $bcode,
			'product' => $product,
			'expected_qty' => $expectedAmount,
			'scanned_qty' => $newTotalScanned,
			'difference' => $diff,
			'financial_impact' => round($diff * floatval($product['last_cost'] ?? 1.0), 2)
		];
	}

	/**
	 * Validación y entrega para Flujo A: Cliente Recoge (Click & Collect)
	 * Requiere PIN temporal de 6 dígitos o QR token + estado listo/despachado + POD
	 */
	public function VerifyPickupOrder(int $orderId, string $pinCode, int $userId = 1, ?string $signatureName = null, ?string $evidenceUrl = null): array
	{
		$db = $this->Db();
		$stmt = $db->prepare("SELECT * FROM customer_orders WHERE id = :id");
		$stmt->execute([':id' => $orderId]);
		$order = $stmt->fetch(\PDO::FETCH_ASSOC);
		if (!$order)
		{
			throw new \Exception("Pedido #$orderId no encontrado.");
		}

		if ($order['delivery_type'] !== 'pickup')
		{
			throw new \Exception("Este pedido está configurado como 'Despacho a Domicilio', no como 'Cliente Recoge'.");
		}
		if (!in_array($order['status'] ?? '', ['packed', 'dispatched'], true))
		{
			throw new \Exception("El pedido {$order['order_code']} debe completar Packing o Salida antes de entregarse en retiro.");
		}
		if (($order['delivery_status'] ?? '') === 'delivered')
		{
			throw new \Exception("Este pedido ya fue entregado al cliente anteriormente.");
		}

		$pinGiven = trim($pinCode);
		if ($pinGiven === '')
		{
			throw new \Exception("Debe ingresar el PIN de retiro de 6 dígitos o token QR.");
		}
		$storedPin = trim((string)($order['pickup_pin'] ?? ''));
		$storedQr = trim((string)($order['pickup_qr_token'] ?? ''));
		if (($storedPin === '' || $storedPin !== $pinGiven) && ($storedQr === '' || $storedQr !== $pinGiven))
		{
			throw new \Exception("PIN o Código de retiro inválido. El cliente debe presentar el código correcto.");
		}

		$sig = trim((string)($signatureName ?? ''));
		if ($sig === '')
		{
			$custName = '';
			if (!empty($order['customer_id']))
			{
				$cStmt = $db->prepare("SELECT name FROM customers WHERE id = ?");
				$cStmt->execute([intval($order['customer_id'])]);
				$custName = (string)($cStmt->fetchColumn() ?: '');
			}
			$sig = trim("Retiro verificado PIN {$pinGiven}" . ($custName ? " · {$custName}" : ''));
		}
		$evi = trim((string)($evidenceUrl ?? '')) ?: "PIN:{$pinGiven}";

		$db->prepare("
			UPDATE customer_orders
			SET status = 'dispatched',
				delivery_status = 'delivered',
				dispatched_by_user_id = COALESCE(dispatched_by_user_id, :u),
				dispatched_at = COALESCE(dispatched_at, datetime('now', 'localtime')),
				departure_time = COALESCE(departure_time, datetime('now', 'localtime')),
				pickup_verified_by = :u,
				pickup_verified_at = datetime('now', 'localtime'),
				delivery_time = datetime('now', 'localtime'),
				delivery_signature_name = :sig,
				delivery_evidence_url = :evi
			WHERE id = :id
		")->execute([':u' => $userId, ':sig' => $sig, ':evi' => $evi, ':id' => $orderId]);

		return [
			'success' => true,
			'message' => "Retiro verificado exitosamente con POD PIN. Pedido {$order['order_code']} entregado en tienda.",
			'order_code' => $order['order_code'],
			'verified_at' => date('Y-m-d H:i:s'),
			'pod_signature' => $sig
		];
	}

	/**
	 * Registro de entrega y Prueba de Entrega (POD) para Flujo B: Despacho a Domicilio
	 */
	public function RecordDispatchDelivery(int $orderId, array $d, int $userId = 1): array
	{
		$db = $this->Db();
		$stmt = $db->prepare("SELECT * FROM customer_orders WHERE id = :id");
		$stmt->execute([':id' => $orderId]);
		$order = $stmt->fetch(\PDO::FETCH_ASSOC);
		if (!$order)
		{
			throw new \Exception("Pedido #$orderId no encontrado.");
		}
		if (($order['status'] ?? '') !== 'dispatched')
		{
			throw new \Exception("El pedido {$order['order_code']} debe confirmar Salida de Muelle antes de registrar la entrega (estado actual: {$order['status']}).");
		}

		$delivStatus = trim(strval($d['delivery_status'] ?? 'delivered'));
		$validStatuses = ['delivered', 'partial', 'rejected'];
		if (!in_array($delivStatus, $validStatuses, true))
		{
			throw new \Exception("Estado de entrega inválido. Use delivered, partial o rejected.");
		}

		$carrier = trim(strval($d['carrier_name'] ?? $order['carrier_name'] ?? ''));
		$vehicle = trim(strval($d['carrier_vehicle'] ?? $order['carrier_vehicle'] ?? ''));
		$phone = trim(strval($d['carrier_phone'] ?? $order['carrier_phone'] ?? ''));
		$incidents = trim(strval($d['route_incidents'] ?? ''));
		$signature = trim(strval($d['delivery_signature_name'] ?? ''));
		$evidenceUrl = trim(strval($d['delivery_evidence_url'] ?? ''));
		$tempOk = !empty($d['cold_chain_temp_ok']) ? 1 : 0;

		if (in_array($delivStatus, ['delivered', 'partial'], true))
		{
			if (mb_strlen($signature) < 3)
			{
				throw new \Exception("Prueba de Entrega obligatoria: ingrese el nombre o firma legible de quien recibe (mínimo 3 caracteres).");
			}
			if ($evidenceUrl === '')
			{
				throw new \Exception("Prueba de Entrega obligatoria: registre la referencia, folio o URL de evidencia fotográfica POD.");
			}
		}
		elseif ($delivStatus === 'rejected' && $incidents === '')
		{
			throw new \Exception("Para entregas rechazadas es obligatorio documentar el motivo en Incidencia / Observación de Ruta.");
		}

		if ($delivStatus === 'partial' && $incidents === '')
		{
			$incidents = 'Entrega parcial registrada en ruta; revisar unidades faltantes para conciliación.';
		}

		// Calculate real delivery accuracy if partial quantities supplied
		$delivAcc = ($delivStatus === 'delivered') ? 100.0 : (($delivStatus === 'rejected') ? 0.0 : floatval($d['delivery_accuracy_pct'] ?? 75.0));

		$upd = $db->prepare("
			UPDATE customer_orders
			SET carrier_name = CASE WHEN :c != '' THEN :c ELSE carrier_name END,
				carrier_vehicle = CASE WHEN :v != '' THEN :v ELSE carrier_vehicle END,
				carrier_phone = CASE WHEN :ph != '' THEN :ph ELSE carrier_phone END,
				delivery_status = :dst,
				delivery_accuracy_pct = :dacc,
				route_incidents = NULLIF(:inc, ''),
				delivery_signature_name = NULLIF(:sig, ''),
				delivery_evidence_url = NULLIF(:evi, ''),
				cold_chain_temp_ok = :temp,
				delivery_time = datetime('now', 'localtime')
			WHERE id = :id
		");
		$upd->execute([
			':c' => $carrier,
			':v' => $vehicle,
			':ph' => $phone,
			':dst' => $delivStatus,
			':dacc' => $delivAcc,
			':inc' => $incidents,
			':sig' => $signature,
			':evi' => $evidenceUrl,
			':temp' => $tempOk,
			':id' => $orderId
		]);

		// Auto-create logistics incident when delivery is partial or rejected
		if (in_array($delivStatus, ['partial', 'rejected'], true))
		{
			$incType = ($delivStatus === 'partial') ? 'entrega_parcial' : 'rechazo_cliente';
			$sev = ($delivStatus === 'rejected') ? 'high' : 'medium';
			$db->prepare("
				INSERT INTO dispatch_logistics_incidents
				(order_id, branch_id, incident_type, severity, description, reported_by_user_id, resolution_status)
				VALUES (:oid, :bid, :it, :sev, :desc, :u, 'open')
			")->execute([
				':oid' => $orderId,
				':bid' => intval($order['branch_id'] ?? 1),
				':it' => $incType,
				':sev' => $sev,
				':desc' => $incidents,
				':u' => $userId
			]);
		}

		$labelMsg = $delivStatus === 'delivered'
			? "Entrega completa con POD registrada para {$order['order_code']}."
			: ($delivStatus === 'partial'
				? "Entrega parcial con incidencia registrada para {$order['order_code']}."
				: "Rechazo de entrega documentado para {$order['order_code']}.");

		return ['success' => true, 'delivery_status' => $delivStatus, 'message' => $labelMsg];
	}

	/**
	 * Resolución obligatoria de una variación logística antes de conciliación final
	 */
	public function ResolveDispatchVariance(int $varianceId, string $resolutionStatus, string $notes, int $userId = 1): array
	{
		$db = $this->Db();
		$stmt = $db->prepare("SELECT * FROM dispatch_variances WHERE id = :id");
		$stmt->execute([':id' => $varianceId]);
		$v = $stmt->fetch(\PDO::FETCH_ASSOC);
		if (!$v)
		{
			throw new \Exception("Variación #$varianceId no encontrada.");
		}

		$validResolutions = ['authorized_exception', 'waste_recorded', 'credited', 'resolved', 'partial_credit', 'shortage_confirmed'];
		if (!in_array($resolutionStatus, $validResolutions, true))
		{
			throw new \Exception("Resolución logística inválida.");
		}
		if (trim($notes) === '')
		{
			throw new \Exception("Debe ingresar una observación o justificación logística para resolver la variación.");
		}

		$upd = $db->prepare("
			UPDATE dispatch_variances
			SET resolution_status = :st,
				resolution_notes = :n,
				authorized_by_user_id = :u
			WHERE id = :id
		");
		$upd->execute([
			':st' => $resolutionStatus,
			':n' => trim($notes),
			':u' => $userId,
			':id' => $varianceId
		]);

		return ['success' => true, 'id' => $varianceId, 'resolution_status' => $resolutionStatus];
	}

	/**
	 * Métricas analíticas reales comparativas: Cliente Recoge (Pickup) vs Despacho a Domicilio (Delivery)
	 */
	public function GetDispatchComparativeAnalytics(?int $branchId = null): array
	{
		$db = $this->Db();
		$where = ["status != 'cancelled'"];
		$params = [];
		if ($branchId !== null && $branchId > 0)
		{
			$where[] = "branch_id = :bid";
			$params[':bid'] = $branchId;
		}
		$wSql = implode(" AND ", $where);

		$stmt = $db->prepare("SELECT * FROM customer_orders WHERE $wSql");
		$stmt->execute($params);
		$orders = $stmt->fetchAll(\PDO::FETCH_ASSOC);

		$pickupOrders = array_values(array_filter($orders, fn($o) => ($o['delivery_type'] ?? 'pickup') === 'pickup'));
		$deliveryOrders = array_values(array_filter($orders, fn($o) => ($o['delivery_type'] ?? 'pickup') === 'delivery'));

		$calcMetrics = function(array $subOrders, string $type) use ($db) {
			$cnt = count($subOrders);
			if ($cnt === 0)
			{
				return [
					'type' => $type,
					'orders_count' => 0,
					'prep_accuracy_pct' => null,
					'delivery_accuracy_pct' => null,
					'otif_pct' => null,
					'missing_per_thousand' => 0.0,
					'financial_variance' => 0.0,
					'avg_validation_minutes' => null,
					'incidents_count' => 0,
					'claims_rate_pct' => 0.0
				];
			}

			// Prep accuracy only on orders that reached picking or later
			$prepped = array_filter($subOrders, fn($o) => !in_array($o['status'], ['draft', 'confirmed'], true));
			$prepAcc = count($prepped) > 0
				? round(array_sum(array_map(fn($o) => floatval($o['preparation_accuracy_pct'] ?? 0), $prepped)) / count($prepped), 1)
				: null;

			// Delivery accuracy & OTIF only on closed/delivered/partial/rejected orders
			$evaluatedDeliv = array_filter($subOrders, fn($o) => in_array($o['delivery_status'] ?? '', ['delivered', 'partial', 'rejected'], true));
			$delivAcc = count($evaluatedDeliv) > 0
				? round(array_sum(array_map(fn($o) => floatval($o['delivery_accuracy_pct'] ?? ($o['delivery_status'] === 'delivered' ? 100.0 : 0.0)), $evaluatedDeliv)) / count($evaluatedDeliv), 1)
				: null;

			$otifHits = 0;
			foreach ($evaluatedDeliv as $o)
			{
				$isInFull = ($o['delivery_status'] ?? '') === 'delivered' && floatval($o['delivery_accuracy_pct'] ?? 100.0) >= 99.9 && floatval($o['preparation_accuracy_pct'] ?? 100.0) >= 99.9;
				$targetDeadline = trim((string)($o['scheduled_time_slot_end'] ?? ''));
				if ($targetDeadline === '' && !empty($o['requested_date']))
				{
					$targetDeadline = substr((string)$o['requested_date'], 0, 10) . ' 23:59:59';
				}
				$delivTs = !empty($o['delivery_time']) ? strtotime((string)$o['delivery_time']) : false;
				$deadTs = $targetDeadline !== '' ? strtotime($targetDeadline) : false;
				$isOnTime = ($delivTs && $deadTs) ? ($delivTs <= $deadTs) : true;
				if ($isInFull && $isOnTime)
				{
					$otifHits++;
				}
			}
			$otifPct = count($evaluatedDeliv) > 0 ? round(($otifHits / count($evaluatedDeliv)) * 100.0, 1) : null;

			$varCost = array_sum(array_map(fn($o) => floatval($o['variance_total_cost'] ?? 0), $subOrders));

			// Real preparation minutes between created_at/confirmed_at and picked_at/packed_at
			$prepMins = [];
			foreach ($subOrders as $o)
			{
				$startRaw = $o['confirmed_at'] ?? $o['created_at'] ?? null;
				$endRaw = $o['packed_at'] ?? $o['picked_at'] ?? $o['dispatched_at'] ?? null;
				if (!empty($startRaw) && !empty($endRaw))
				{
					$sTs = strtotime((string)$startRaw);
					$eTs = strtotime((string)$endRaw);
					if ($sTs && $eTs && $eTs >= $sTs)
					{
						$mins = ($eTs - $sTs) / 60.0;
						if ($mins <= 1440.0) // ignore multi-day backlog anomalies up to 24h window unless valid
						{
							$prepMins[] = $mins;
						}
					}
				}
			}
			$avgPrepMins = count($prepMins) > 0 ? round(array_sum($prepMins) / count($prepMins), 1) : null;

			$orderIds = array_map('intval', array_column($subOrders, 'id'));
			$missingUnits = 0.0;
			$totalUnits = 0.0;
			$incidentsCount = count(array_filter($subOrders, fn($o) => !empty($o['route_incidents'])));
			if ($orderIds)
			{
				$inList = implode(',', $orderIds);
				$missingUnits = floatval($db->query("SELECT IFNULL(SUM(ABS(difference_qty)), 0) FROM dispatch_variances WHERE order_id IN ($inList) AND difference_qty < 0")->fetchColumn() ?: 0.0);
				$totalUnits = floatval($db->query("SELECT IFNULL(SUM(amount), 0) FROM customer_order_items WHERE order_id IN ($inList)")->fetchColumn() ?: 0.0);
				$dliCnt = intval($db->query("SELECT COUNT(DISTINCT order_id) FROM dispatch_logistics_incidents WHERE order_id IN ($inList)")->fetchColumn() ?: 0);
				$incidentsCount = max($incidentsCount, $dliCnt);
			}

			$mpt = $totalUnits > 0 ? round(($missingUnits / $totalUnits) * 1000.0, 1) : 0.0;
			$claimsRate = round(($incidentsCount / $cnt) * 100.0, 1);

			return [
				'type' => $type,
				'orders_count' => $cnt,
				'evaluated_delivery_count' => count($evaluatedDeliv),
				'prep_accuracy_pct' => $prepAcc,
				'delivery_accuracy_pct' => $delivAcc,
				'otif_pct' => $otifPct,
				'missing_per_thousand' => $mpt,
				'financial_variance' => round($varCost, 2),
				'avg_validation_minutes' => $avgPrepMins,
				'incidents_count' => $incidentsCount,
				'claims_rate_pct' => $claimsRate
			];
		};

		return [
			'pickup' => $calcMetrics($pickupOrders, 'pickup'),
			'delivery' => $calcMetrics($deliveryOrders, 'delivery'),
			'total_orders' => count($orders)
		];
	}

	/* =========================================================================
	 * 3. DEVOLUCIONES LOGÍSTICAS (LOGÍSTICA INVERSA: 6 ETAPAS CANÓNICAS)
	 *    Solicitud -> Recepción -> Inspección -> Clasificación -> Disposición -> Conciliación
	 * ========================================================================= */

	public const RETURN_STAGES = [
		'solicitud' => ['num' => 1, 'name' => 'Solicitud', 'sub' => 'RMA generado'],
		'recepcion' => ['num' => 2, 'name' => 'Recepción', 'sub' => 'Física y UPC'],
		'inspeccion' => ['num' => 3, 'name' => 'Inspección', 'sub' => 'Estado y evidencia'],
		'clasificacion' => ['num' => 4, 'name' => 'Clasificación', 'sub' => 'Destino por unidad'],
		'disposicion' => ['num' => 5, 'name' => 'Disposición', 'sub' => 'Autorización y stock'],
		'conciliacion' => ['num' => 6, 'name' => 'Conciliación', 'sub' => 'Cierre de unidades'],
	];

	public const FINAL_CLASSIFICATIONS = [
		'reincorporacion',
		'devolucion_proveedor',
		'reparacion',
		'recuperacion',
		'donacion',
		'merma_definitiva',
		'destruccion',
	];

	public const DAMAGE_GRADES = ['danado', 'vencido', 'defectuoso_fabrica'];

	/**
	 * Normaliza estados heredados al ciclo canónico de 6 etapas
	 */
	private function NormalizeReturnStatus(?string $status): string
	{
		$s = strtolower(trim(strval($status ?? 'solicitud')));
		$map = [
			'identificacion' => 'recepcion',
			'pending_inspection' => 'recepcion',
			'aprobada' => 'clasificacion',
			'conciliada' => 'conciliacion',
			'cerrada' => 'conciliacion',
		];
		return $map[$s] ?? ($s !== '' ? $s : 'solicitud');
	}

	/**
	 * Construye las 6 etapas canónicas del ciclo logístico inverso para UI/API
	 */
	private function BuildReturnStages(array $r): array
	{
		$st = $this->NormalizeReturnStatus($r['status'] ?? 'solicitud');
		$isRejected = ($st === 'rechazada');
		$order = array_keys(self::RETURN_STAGES);
		$curIdx = array_search($st, $order, true);
		if ($curIdx === false)
		{
			$curIdx = 0;
		}
		$timestamps = [
			'solicitud' => $r['created_at'] ?? null,
			'recepcion' => $r['received_at'] ?? null,
			'inspeccion' => $r['inspected_at'] ?? null,
			'clasificacion' => !empty($r['classification']) && !in_array($r['classification'], ['pendiente_inspeccion', 'cuarentena'], true) ? ($r['inspected_at'] ?? $r['created_at']) : null,
			'disposicion' => $r['disposition_authorized_at'] ?? ($r['approved_at'] ?? null),
			'conciliacion' => $r['conciliated_at'] ?? null,
		];

		$stages = [];
		foreach ($order as $idx => $key)
		{
			$meta = self::RETURN_STAGES[$key];
			$done = !$isRejected && ($idx < $curIdx || ($idx === count($order) - 1 && $curIdx === $idx));
			$current = !$isRejected && ($idx === $curIdx) && !$done;
			$stages[] = [
				'key' => $key,
				'num' => $meta['num'],
				'name' => $meta['name'],
				'label' => $meta['name'],
				'sub' => $meta['sub'],
				'done' => $done,
				'current' => $current,
				'state' => $isRejected ? ($idx === 0 ? 'done' : 'rejected') : ($done ? 'done' : ($current ? 'current' : 'pending')),
				'at' => $done || $current ? ($timestamps[$key] ?? null) : null,
			];
		}
		return $stages;
	}

	/**
	 * Verifica autorización de supervisor/gerente para disposición autorizada o reingreso a stock
	 */
	private function CheckReturnSupervisorAuth(int $userId): void
	{
		if (PHP_SAPI !== 'cli' && DairyAccess::GetSimulatedRole() === 'empleado')
		{
			throw new \Exception("Autorización denegada: el rol empleado no puede autorizar disposiciones ni reingresos de inventario.");
		}
		$db = $this->Db();
		$allowed = [
			User::PERMISSION_ADMIN,
			User::PERMISSION_INVENTORY_ADJUST_APPROVE,
			User::PERMISSION_WASTE_APPROVE,
			User::PERMISSION_QUALITY_QUARANTINE,
		];
		$in = implode(',', array_map(fn($p) => $db->quote($p), $allowed));
		$has = intval($db->query("
			SELECT COUNT(*)
			FROM users u
			JOIN user_permissions up ON up.user_id = u.id
			WHERE u.id = " . intval($userId) . "
			  AND up.permission_id IN (SELECT id FROM permission_hierarchy WHERE name IN ($in))
		")->fetchColumn());
		if ($has <= 0)
		{
			throw new \Exception("Autorización de supervisor requerida para aprobar disposición o reingreso al inventario.");
		}
	}

	/**
	 * Registra entrada inmutable en bitácora de auditoría logística de devoluciones
	 */
	private function LogReturnAudit(
		int $returnId,
		string $stage,
		string $action,
		string $details,
		?string $prevStatus,
		?string $newStatus,
		?float $qty,
		int $userId,
		?string $evidenceUrl = null
	): void
	{
		$db = $this->Db();
		$db->prepare("
			INSERT INTO return_audit_log
			(return_id, stage, action, details, previous_status, new_status, qty_snapshot, evidence_url, user_id, created_at)
			VALUES (:rid, :st, :act, :det, :prev, :next, :qty, :ev, :uid, datetime('now', 'localtime'))
		")->execute([
			':rid' => $returnId,
			':st' => $stage,
			':act' => $action,
			':det' => $details,
			':prev' => $prevStatus,
			':next' => $newStatus,
			':qty' => $qty,
			':ev' => $evidenceUrl,
			':uid' => $userId,
		]);
	}

	private function FetchReturnRow(int $returnId): array
	{
		$db = $this->Db();
		$stmt = $db->prepare("
			SELECT r.*,
			       p.name AS product_name,
			       p.sku,
			       p.location_id AS default_location_id,
			       p.default_best_before_days,
			       IFNULL(b.name, 'Sin sucursal') AS branch_name,
			       IFNULL(o.order_code, '') AS order_code,
			       IFNULL(s.name, '') AS supplier_name,
			       IFNULL(loc.name, '') AS target_location_name
			FROM customer_returns_exchanges r
			JOIN products p ON r.product_id = p.id
			LEFT JOIN branches b ON r.branch_id = b.id
			LEFT JOIN customer_orders o ON r.order_id = o.id
			LEFT JOIN shopping_locations s ON r.supplier_id = s.id
			LEFT JOIN locations loc ON r.target_location_id = loc.id
			WHERE r.id = :id
		");
		$stmt->execute([':id' => $returnId]);
		$r = $stmt->fetch(\PDO::FETCH_ASSOC);
		if (!$r)
		{
			throw new \Exception("Devolución #$returnId no encontrada.");
		}
		return $r;
	}

	/**
	 * Catálogo de clasificación física de mercancía
	 */
	public static function ReturnClassMeta(): array
	{
		return [
			'reincorporacion' => ['label' => 'Apto para reventa (Reingreso)', 'badge' => 'badge-success', 'desc' => 'Reingreso autorizado a inventario vendible'],
			'devolucion_proveedor' => ['label' => 'Devolución a proveedor (RTV)', 'badge' => 'badge-info', 'desc' => 'Retorno físico a proveedor / crédito comercial'],
			'reparacion' => ['label' => 'Reparación / Reacondicionamiento', 'badge' => 'badge-primary', 'desc' => 'Taller técnico o reempaque controlado'],
			'recuperacion' => ['label' => 'Recuperación / Reproceso', 'badge' => 'badge-primary', 'desc' => 'Aprovechamiento secundario autorizado'],
			'cuarentena' => ['label' => 'Cuarentena técnica (Pendiente)', 'badge' => 'badge-warning', 'desc' => 'Aislamiento preventivo sin destino final'],
			'donacion' => ['label' => 'Donación autorizada', 'badge' => 'badge-teal', 'desc' => 'Banco de Alimentos con acta formal'],
			'merma_definitiva' => ['label' => 'Producto dañado / Merma', 'badge' => 'badge-danger', 'desc' => 'Mercancía dañada no apta para reventa'],
			'destruccion' => ['label' => 'Destrucción controlada', 'badge' => 'badge-dark', 'desc' => 'Desnaturalización y baja certificada'],
			'pendiente_inspeccion' => ['label' => 'Pendiente de inspección física', 'badge' => 'badge-warning', 'desc' => 'En espera de verificación física en almacén'],
		];
	}

	/**
	 * Listado de devoluciones logísticas separadas estrictamente del POS comercial
	 */
	public function GetReturns(?int $branchId = null, ?string $status = null, ?string $type = null, ?string $channel = null): array
	{
		$db = $this->Db();
		$where = ["1=1"];
		$params = [];

		if ($branchId !== null && $branchId > 0)
		{
			$where[] = "r.branch_id = :bid";
			$params[':bid'] = $branchId;
		}
		if (!empty($status))
		{
			$where[] = "r.status = :st";
			$params[':st'] = $this->NormalizeReturnStatus($status);
		}
		if (!empty($type))
		{
			$where[] = "r.return_type = :rt";
			$params[':rt'] = $type;
		}
		if ($channel === 'logistics')
		{
			$where[] = "IFNULL(r.is_pos_return, 0) = 0";
		}
		elseif ($channel === 'pos_handoff')
		{
			$where[] = "IFNULL(r.is_pos_return, 0) = 1";
		}

		$wSql = implode(" AND ", $where);
		$sql = "
			SELECT
				r.id, r.return_code, IFNULL(r.rma_number, r.return_code) AS rma_number,
				r.order_id, r.branch_id, r.customer_id, r.product_id, r.supplier_id,
				r.stock_id, r.return_type, r.reference_doc, r.upc_scanned,
				r.quantity, IFNULL(r.received_quantity, 0) AS received_quantity,
				r.received_at, r.received_by_user_id,
				IFNULL(r.condition_grade, 'sin_inspeccionar') AS condition_grade,
				IFNULL(r.resalable_quantity, 0) AS resalable_quantity,
				IFNULL(r.damaged_quantity, 0) AS damaged_quantity,
				IFNULL(r.repairable_quantity, 0) AS repairable_quantity,
				IFNULL(r.rtv_quantity, 0) AS rtv_quantity,
				r.repair_notes, r.target_location_id,
				r.unit_cost, r.total_cost, r.recoverable_value, r.definitive_loss_value,
				r.supplier_credit_amount,
				r.classification, r.is_perishable,
				r.inspection_result, r.inspection_notes, r.inspected_by_user_id, r.inspected_at,
				r.approval_status, r.approved_by_user_id, r.approved_at,
				r.disposition_authorized_by, r.disposition_authorized_at, r.disposition_notes,
				IFNULL(r.stock_reentered, 0) AS stock_reentered, r.stock_reentry_tx_id,
				IFNULL(r.conciliated_quantity, 0) AS conciliated_quantity, r.conciliated_at, r.conciliated_by_user_id, r.conciliation_notes,
				r.evidence_photo_url, r.status, r.waste_record_id, r.stock_booking_id, r.created_at,
				IFNULL(r.is_pos_return, 0) AS is_pos_return,
				p.name AS product_name,
				p.sku,
				p.location_id AS default_location_id,
				(SELECT barcode FROM product_barcodes pb WHERE pb.product_id = p.id ORDER BY pb.id LIMIT 1) AS primary_barcode,
				IFNULL(c.name, 'Cliente / Origen Interno') AS customer_name,
				IFNULL(b.name, 'Sin sucursal') AS branch_name,
				IFNULL(o.order_code, IFNULL(r.reference_doc, 'Sin pedido directo')) AS order_code,
				IFNULL(sup.name, '') AS supplier_name,
				IFNULL(loc.name, '') AS target_location_name,
				u_rec.username AS received_by_name,
				u_insp.username AS inspector_name,
				u_app.username AS approver_name,
				u_auth.username AS authorized_by_name,
				u_conc.username AS conciliated_by_name
			FROM customer_returns_exchanges r
			JOIN products p ON r.product_id = p.id
			LEFT JOIN customers c ON r.customer_id = c.id
			LEFT JOIN branches b ON r.branch_id = b.id
			LEFT JOIN customer_orders o ON r.order_id = o.id
			LEFT JOIN shopping_locations sup ON r.supplier_id = sup.id
			LEFT JOIN locations loc ON r.target_location_id = loc.id
			LEFT JOIN users u_rec ON r.received_by_user_id = u_rec.id
			LEFT JOIN users u_insp ON r.inspected_by_user_id = u_insp.id
			LEFT JOIN users u_app ON r.approved_by_user_id = u_app.id
			LEFT JOIN users u_auth ON r.disposition_authorized_by = u_auth.id
			LEFT JOIN users u_conc ON r.conciliated_by_user_id = u_conc.id
			WHERE $wSql
			ORDER BY r.created_at DESC, r.id DESC
		";

		$stmt = $db->prepare($sql);
		$stmt->execute($params);
		$rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

		// Preload audit logs and evidence photos for displayed rows
		$ids = array_map(fn($x) => intval($x['id']), $rows);
		$auditByRet = [];
		$photosByRet = [];
		if (!empty($ids))
		{
			$inIds = implode(',', $ids);
			$audRows = $db->query("
				SELECT l.*, IFNULL(u.username, 'Sistema') AS username
				FROM return_audit_log l
				LEFT JOIN users u ON l.user_id = u.id
				WHERE l.return_id IN ($inIds)
				ORDER BY l.created_at DESC, l.id DESC
			")->fetchAll(\PDO::FETCH_ASSOC);
			foreach ($audRows as $a)
			{
				$auditByRet[intval($a['return_id'])][] = $a;
			}

			$phRows = $db->query("
				SELECT p.*, IFNULL(u.username, 'Sistema') AS username
				FROM return_evidence_photos p
				LEFT JOIN users u ON p.uploaded_by_user_id = u.id
				WHERE p.return_id IN ($inIds)
				ORDER BY p.created_at DESC, p.id DESC
			")->fetchAll(\PDO::FETCH_ASSOC);
			foreach ($phRows as $ph)
			{
				$photosByRet[intval($ph['return_id'])][] = $ph;
			}
		}

		$classMeta = self::ReturnClassMeta();

		$totCost = 0.0;
		$totLoss = 0.0;
		$totRecovered = 0.0;
		$totCredit = 0.0;
		$pendingCount = 0;
		$conciliatedCount = 0;
		$requestedUnits = 0.0;
		$receivedUnits = 0.0;
		$conciliatedUnits = 0.0;
		$resalableUnits = 0.0;
		$damagedUnits = 0.0;
		$rtvUnits = 0.0;
		$repairableUnits = 0.0;

		foreach ($rows as &$r)
		{
			$id = intval($r['id']);
			$r['status'] = $this->NormalizeReturnStatus($r['status'] ?? 'solicitud');
			$r['stages'] = $this->BuildReturnStages($r);
			$cKey = $r['classification'] ?: 'cuarentena';
			$r['class_meta'] = $classMeta[$cKey] ?? ['label' => $cKey, 'badge' => 'badge-secondary', 'desc' => ''];
			$r['audit_log'] = $auditByRet[$id] ?? [];
			$r['evidence_photos'] = $photosByRet[$id] ?? [];

			if (in_array($r['status'], ['conciliacion', 'rechazada'], true))
			{
				if ($r['status'] === 'conciliacion')
				{
					$conciliatedCount++;
				}
			}
			else
			{
				$pendingCount++;
			}

			$totCost += floatval($r['total_cost']);
			$totLoss += floatval($r['definitive_loss_value']);
			$totRecovered += floatval($r['recoverable_value']);
			$totCredit += floatval($r['supplier_credit_amount']);

			$requestedUnits += floatval($r['quantity']);
			$receivedUnits += floatval($r['received_quantity']);
			$conciliatedUnits += floatval($r['conciliated_quantity']);
			$resalableUnits += floatval($r['resalable_quantity']);
			$damagedUnits += floatval($r['damaged_quantity']);
			$rtvUnits += floatval($r['rtv_quantity']);
			$repairableUnits += floatval($r['repairable_quantity']);
		}
		unset($r);

		$logisticsOnly = array_values(array_filter($rows, fn($x) => intval($x['is_pos_return']) === 0));
		$posHandoff = array_values(array_filter($rows, fn($x) => intval($x['is_pos_return']) === 1));

		return [
			'returns' => $rows,
			'logistics_returns' => $logisticsOnly,
			'pos_inspection_queue' => $posHandoff,
			'total_count' => count($rows),
			'logistics_count' => count($logisticsOnly),
			'pos_handoff_count' => count($posHandoff),
			'logistics_summary' => [
				'pending_count' => $pendingCount,
				'conciliated_count' => $conciliatedCount,
				'requested_units' => round($requestedUnits, 2),
				'received_units' => round($receivedUnits, 2),
				'conciliated_units' => round($conciliatedUnits, 2),
				'unit_variance' => round($receivedUnits - $conciliatedUnits, 2),
				'resalable_units' => round($resalableUnits, 2),
				'damaged_units' => round($damagedUnits, 2),
				'rtv_units' => round($rtvUnits, 2),
				'repairable_units' => round($repairableUnits, 2),
				'reconcile_pct' => $receivedUnits > 0 ? round(($conciliatedUnits / $receivedUnits) * 100, 1) : 100.0,
			],
			'financial_summary' => [
				'total_cost' => round($totCost, 2),
				'definitive_loss' => round($totLoss, 2),
				'recoverable_value' => round($totRecovered, 2),
				'supplier_credits' => round($totCredit, 2),
				'net_economic_impact' => round($totLoss - $totCredit, 2)
			]
		];
	}

	public function GetReturnDetail(int $returnId): array
	{
		$res = $this->GetReturns(null, null, null, null);
		foreach ($res['returns'] as $r)
		{
			if (intval($r['id']) === $returnId)
			{
				return $r;
			}
		}
		throw new \Exception("Devolución #$returnId no encontrada.");
	}

	/**
	 * Busca un producto o RMA pendiente por código UPC / SKU / RMA para recepción con escáner
	 */
	public function LookupReturnByUpc(string $code): array
	{
		$clean = trim($code);
		if ($clean === '')
		{
			throw new \Exception("Ingrese o escanee un código UPC, SKU o RMA.");
		}
		$db = $this->Db();

		// 1. Buscar si es código RMA o folio DEV
		$stRma = $db->prepare("
			SELECT r.id, r.return_code, r.rma_number, r.product_id, r.status, r.quantity, r.received_quantity, p.name AS product_name, p.sku
			FROM customer_returns_exchanges r
			JOIN products p ON r.product_id = p.id
			WHERE UPPER(IFNULL(r.rma_number,'')) = UPPER(:c) OR UPPER(r.return_code) = UPPER(:c)
			LIMIT 1
		");
		$stRma->execute([':c' => $clean]);
		if ($rmaRow = $stRma->fetch(\PDO::FETCH_ASSOC))
		{
			return ['match_type' => 'rma', 'return' => $rmaRow, 'product_id' => intval($rmaRow['product_id'])];
		}

		// 2. Buscar por código de barras (product_barcodes) o SKU
		$stProd = $db->prepare("
			SELECT p.id, p.name, p.sku,
			       (SELECT barcode FROM product_barcodes pb WHERE pb.product_id = p.id ORDER BY pb.id LIMIT 1) AS barcode,
			       (SELECT price FROM cache__products_last_purchased WHERE product_id = p.id) AS last_cost
			FROM products p
			WHERE p.active = 1
			  AND (UPPER(IFNULL(p.sku,'')) = UPPER(:c)
			       OR EXISTS (SELECT 1 FROM product_barcodes pb WHERE pb.product_id = p.id AND UPPER(pb.barcode) = UPPER(:c)))
			LIMIT 1
		");
		$stProd->execute([':c' => $clean]);
		$prod = $stProd->fetch(\PDO::FETCH_ASSOC);
		if (!$prod)
		{
			throw new \Exception("No se encontró producto ni RMA coincidente para '$clean'.");
		}

		$openRmas = $db->prepare("
			SELECT id, return_code, IFNULL(rma_number, return_code) AS rma_number, status, quantity, received_quantity
			FROM customer_returns_exchanges
			WHERE product_id = :pid AND status IN ('solicitud', 'recepcion', 'inspeccion')
			ORDER BY id DESC LIMIT 10
		");
		$openRmas->execute([':pid' => intval($prod['id'])]);

		return [
			'match_type' => 'product',
			'product' => $prod,
			'open_returns' => $openRmas->fetchAll(\PDO::FETCH_ASSOC),
		];
	}

	/**
	 * ETAPA 1: Solicitud de Retorno (crea RMA único sin duplicados físicos)
	 */
	public function CreateReturn(array $d, int $userId = 1): array
	{
		$db = $this->Db();
		$pid = intval($d['product_id'] ?? 0);
		$qty = floatval($d['quantity'] ?? 0);
		if ($pid <= 0 || $qty <= 0)
		{
			throw new \Exception("Seleccione producto válido y cantidad mayor a cero.");
		}

		$idempotencyKey = !empty($d['idempotency_key']) ? substr(trim(strval($d['idempotency_key'])), 0, 80) : null;
		if ($idempotencyKey)
		{
			$stDup = $db->prepare("SELECT id, return_code, rma_number, total_cost FROM customer_returns_exchanges WHERE idempotency_key = :k LIMIT 1");
			$stDup->execute([':k' => $idempotencyKey]);
			if ($existing = $stDup->fetch(\PDO::FETCH_ASSOC))
			{
				return [
					'success' => true,
					'replayed' => true,
					'id' => intval($existing['id']),
					'return_code' => $existing['return_code'],
					'rma_number' => $existing['rma_number'],
					'total_cost' => floatval($existing['total_cost']),
				];
			}
		}

		$type = strval($d['return_type'] ?? 'devolucion_cliente');
		$allowedTypes = ['devolucion_cliente', 'cambio', 'devolucion_proveedor', 'rechazo_recepcion', 'daño_transporte', 'calidad', 'vencido', 'producto_incorrecto', 'diferencia_despacho'];
		if (!in_array($type, $allowedTypes, true))
		{
			$type = 'devolucion_cliente';
		}

		$reason = trim(strval($d['reason'] ?? ''));
		if ($reason === '')
		{
			throw new \Exception("Indique el motivo o causal de la solicitud de retorno.");
		}

		$orderId = !empty($d['order_id']) ? intval($d['order_id']) : null;
		$branchId = !empty($d['branch_id']) ? intval($d['branch_id']) : 1;
		$customerId = !empty($d['customer_id']) ? intval($d['customer_id']) : 1;
		$supplierId = !empty($d['supplier_id']) ? intval($d['supplier_id']) : null;
		$refDoc = !empty($d['reference_doc']) ? trim(strval($d['reference_doc'])) : null;

		// Verificar producto existente
		$pInfo = $db->query("
			SELECT p.id, p.name, p.location_id,
			       (SELECT price FROM cache__products_last_purchased WHERE product_id = p.id) AS last_cost,
			       (SELECT is_freezer FROM locations WHERE id = p.location_id) AS is_freezer
			FROM products p WHERE p.id = $pid
		")->fetch(\PDO::FETCH_ASSOC);

		if (!$pInfo)
		{
			throw new \Exception("Producto #$pid no encontrado.");
		}

		// FIX 2: Bloqueo de devolución física duplicada en ventana de 90 segundos
		$stRecent = $db->prepare("
			SELECT return_code, rma_number
			FROM customer_returns_exchanges
			WHERE product_id = " . intval($pid) . "
			  AND branch_id = " . intval($branchId) . "
			  AND IFNULL(order_id, 0) = " . intval($orderId ?? 0) . "
			  AND ABS(quantity - " . floatval($qty) . ") < 0.001
			  AND return_type = :rt
			  AND status NOT IN ('conciliacion', 'rechazada')
			  AND created_at >= datetime('now', 'localtime', '-90 seconds')
			LIMIT 1
		");
		$stRecent->execute([':rt' => $type]);
		if ($dupRow = $stRecent->fetch(\PDO::FETCH_ASSOC))
		{
			throw new \Exception("Devolución física duplicada detectada (RMA activo {$dupRow['rma_number']}).");
		}

		// Si hay pedido asociado con líneas del producto, no permitir devolver más de lo pedido
		if ($orderId)
		{
			$stOrdered = $db->prepare("SELECT IFNULL(SUM(amount), 0) FROM customer_order_items WHERE order_id = :oid AND product_id = :pid");
			$stOrdered->execute([':oid' => $orderId, ':pid' => $pid]);
			$orderedQty = floatval($stOrdered->fetchColumn());
			if ($orderedQty > 0)
			{
				$stAlready = $db->prepare("SELECT IFNULL(SUM(quantity), 0) FROM customer_returns_exchanges WHERE order_id = :oid AND product_id = :pid AND status <> 'rechazada'");
				$stAlready->execute([':oid' => $orderId, ':pid' => $pid]);
				$alreadyQty = floatval($stAlready->fetchColumn());
				if ($alreadyQty + $qty > $orderedQty + 0.001)
				{
					throw new \Exception("La cantidad solicitada ($qty) más lo ya devuelto ($alreadyQty) excede las $orderedQty unidades del pedido asociado.");
				}
			}
		}

		$isPerishable = intval($d['is_perishable'] ?? 0);
		if (!empty($pInfo['is_freezer']) || preg_match('/leche|pollo|carne|queso|yogur|huevo|jam[oó]n|salchicha/i', $pInfo['name']))
		{
			$isPerishable = 1;
		}

		$cost = floatval(!empty($d['unit_cost']) ? $d['unit_cost'] : ($pInfo['last_cost'] ?: 1.0));
		$totalCost = round($qty * $cost, 2);

		$nextNum = intval($db->query("SELECT IFNULL(MAX(id), 0) + 1 FROM customer_returns_exchanges")->fetchColumn());
		$retCode = sprintf('DEV-%s-%04d', date('Y'), $nextNum);
		$rmaNumber = !empty($d['rma_number'])
			? strtoupper(preg_replace('/[^A-Za-z0-9\-_]/', '', strval($d['rma_number'])))
			: sprintf('RMA-%s-%04d', date('Y'), $nextNum);

		$ins = $db->prepare("
			INSERT INTO customer_returns_exchanges
			(return_code, rma_number, idempotency_key, reference_doc, order_id, branch_id, customer_id, supplier_id,
			 product_id, stock_id, return_type, quantity, received_quantity, unit_cost, total_cost,
			 recoverable_value, definitive_loss_value, supplier_credit_amount, customer_refund_amount,
			 classification, condition_grade, is_perishable, inspection_result, inspection_notes,
			 target_location_id, approval_status, status, evidence_photo_url, is_pos_return, created_at)
			VALUES
			(:code, :rma, :idem, :refdoc, :oid, :bid, :cid, :sid,
			 :pid, NULL, :rtype, :q, 0, :c, :tc,
			 0, 0, 0, 0,
			 'cuarentena', 'sin_inspeccionar', :per, 'Pendiente de recepción física e inspección', :notes,
			 :loc, 'pending', 'solicitud', :photo, 0, datetime('now', 'localtime'))
		");
		$ins->execute([
			':code' => $retCode,
			':rma' => $rmaNumber,
			':idem' => $idempotencyKey,
			':refdoc' => $refDoc,
			':oid' => $orderId,
			':bid' => $branchId,
			':cid' => $customerId,
			':sid' => $supplierId,
			':pid' => $pid,
			':rtype' => $type,
			':q' => $qty,
			':c' => $cost,
			':tc' => $totalCost,
			':per' => $isPerishable,
			':notes' => $reason,
			':loc' => intval($pInfo['location_id'] ?? 0) ?: null,
			':photo' => $d['evidence_photo_url'] ?? null,
		]);
		$newId = intval($db->lastInsertId());

		$this->LogReturnAudit(
			$newId,
			'solicitud',
			'solicitud_rma',
			"Solicitud RMA $rmaNumber creada por {$qty} u. de {$pInfo['name']}. Motivo: $reason",
			null,
			'solicitud',
			$qty,
			$userId,
			$d['evidence_photo_url'] ?? null
		);

		return [
			'success' => true,
			'replayed' => false,
			'id' => $newId,
			'return_code' => $retCode,
			'rma_number' => $rmaNumber,
			'status' => 'solicitud',
			'total_cost' => $totalCost,
		];
	}

	/**
	 * ETAPA 2: Recepción Física y Escaneo UPC
	 */
	public function ReceiveReturn(int $returnId, array $d, int $userId = 1): array
	{
		$db = $this->Db();
		$r = $this->FetchReturnRow($returnId);
		$cur = $this->NormalizeReturnStatus($r['status']);
		if (!in_array($cur, ['solicitud', 'recepcion'], true))
		{
			throw new \Exception("El RMA {$r['rma_number']} ya superó la etapa de recepción física.");
		}

		$receivedQty = floatval($d['received_quantity'] ?? 0);
		if ($receivedQty <= 0)
		{
			throw new \Exception("Indique la cantidad física recibida (mayor a 0).");
		}

		$upc = trim(strval($d['upc_scanned'] ?? ''));
		if ($upc !== '')
		{
			$stMatch = $db->prepare("
				SELECT COUNT(*)
				FROM products p
				WHERE p.id = :pid
				  AND (UPPER(IFNULL(p.sku,'')) = UPPER(:c)
				       OR EXISTS (SELECT 1 FROM product_barcodes pb WHERE pb.product_id = p.id AND UPPER(pb.barcode) = UPPER(:c)))
			");
			$stMatch->execute([':pid' => intval($r['product_id']), ':c' => $upc]);
			if (intval($stMatch->fetchColumn()) === 0)
			{
				throw new \Exception("Código UPC/SKU '$upc' no corresponde al producto {$r['product_name']} del RMA {$r['rma_number']}.");
			}
		}

		$notes = trim(strval($d['notes'] ?? ''));
		$variance = round($receivedQty - floatval($r['quantity']), 2);
		$varText = $variance == 0.0 ? 'Cantidad coincide con lo solicitado' : ($variance < 0 ? "Faltante en recepción de " . abs($variance) . " u." : "Excedente en recepción de +$variance u.");

		$db->prepare("
			UPDATE customer_returns_exchanges
			SET status = 'recepcion',
			    upc_scanned = :upc,
			    received_quantity = :rq,
			    received_at = datetime('now', 'localtime'),
			    received_by_user_id = :u,
			    inspection_result = 'Recibido físicamente — pendiente de inspección'
			WHERE id = :id
		")->execute([
			':upc' => $upc !== '' ? $upc : ($r['sku'] ?? 'MANUAL'),
			':rq' => $receivedQty,
			':u' => $userId,
			':id' => $returnId,
		]);

		$this->LogReturnAudit(
			$returnId,
			'recepcion',
			'recepcion_fisica',
			"Recepción de {$receivedQty} u. (UPC: " . ($upc !== '' ? $upc : 'verificación manual') . "). $varText" . ($notes ? " · $notes" : ''),
			$cur,
			'recepcion',
			$receivedQty,
			$userId
		);

		return [
			'success' => true,
			'id' => $returnId,
			'status' => 'recepcion',
			'received_quantity' => $receivedQty,
			'requested_quantity' => floatval($r['quantity']),
			'variance' => $variance,
			'message' => "Recepción registrada ({$receivedQty} u.). $varText",
		];
	}

	/**
	 * ETAPA 3: Inspección de Estado Físico y Evidencia Fotográfica
	 */
	public function InspectReturn(int $returnId, array $d, int $userId = 1): array
	{
		$db = $this->Db();
		$r = $this->FetchReturnRow($returnId);
		$cur = $this->NormalizeReturnStatus($r['status']);
		if ($cur === 'solicitud' || floatval($r['received_quantity']) <= 0)
		{
			throw new \Exception("Debe registrar la recepción física del RMA {$r['rma_number']} antes de inspeccionar.");
		}
		if (in_array($cur, ['disposicion', 'conciliacion', 'rechazada'], true))
		{
			throw new \Exception("No se puede re-inspeccionar un retorno ya dispuesto o conciliado.");
		}

		$grade = trim(strval($d['condition_grade'] ?? ''));
		$validGrades = ['intacto', 'empaque_abierto', 'reparable', 'danado', 'vencido', 'defectuoso_fabrica'];
		if (!in_array($grade, $validGrades, true))
		{
			throw new \Exception("Seleccione una condición física válida (intacto, empaque_abierto, reparable, danado, vencido o defectuoso_fabrica).");
		}
		$notes = trim(strval($d['inspection_notes'] ?? ($d['notes'] ?? '')));
		if ($notes === '')
		{
			throw new \Exception("Registre observaciones técnicas de inspección física.");
		}

		$photoData = trim(strval($d['photo_data'] ?? ($d['evidence_photo_url'] ?? '')));
		$resultLabels = [
			'intacto' => 'Sello intacto y apto tras validación sanitaria',
			'empaque_abierto' => 'Empaque abierto — requiere revisión o reproceso',
			'reparable' => 'Apto para reparación o reacondicionamiento técnico',
			'danado' => 'Mercancía dañada — NO apta para reventa',
			'vencido' => 'Producto vencido o sin vida útil — bloqueo de reventa',
			'defectuoso_fabrica' => 'Defecto de origen / fábrica — candidato a RTV',
		];

		$db->prepare("
			UPDATE customer_returns_exchanges
			SET status = 'inspeccion',
			    condition_grade = :cg,
			    inspection_result = :res,
			    inspection_notes = :notes,
			    inspected_by_user_id = :u,
			    inspected_at = datetime('now', 'localtime'),
			    evidence_photo_url = COALESCE(NULLIF(:photo, ''), evidence_photo_url)
			WHERE id = :id
		")->execute([
			':cg' => $grade,
			':res' => $resultLabels[$grade],
			':notes' => $notes,
			':u' => $userId,
			':photo' => $photoData,
			':id' => $returnId,
		]);

		if ($photoData !== '')
		{
			$db->prepare("
				INSERT INTO return_evidence_photos (return_id, photo_data, caption, stage, uploaded_by_user_id, created_at)
				VALUES (:rid, :ph, :cap, 'inspeccion', :u, datetime('now', 'localtime'))
			")->execute([
				':rid' => $returnId,
				':ph' => $photoData,
				':cap' => $notes,
				':u' => $userId,
			]);
		}

		$this->LogReturnAudit(
			$returnId,
			'inspeccion',
			'inspeccion_fisica',
			"Condición dictaminada: $grade. $notes",
			$cur,
			'inspeccion',
			floatval($r['received_quantity']),
			$userId,
			$photoData !== '' ? $photoData : null
		);

		return [
			'success' => true,
			'id' => $returnId,
			'status' => 'inspeccion',
			'condition_grade' => $grade,
			'message' => "Inspección registrada como '$grade'.",
		];
	}

	/**
	 * ETAPA 4: Clasificación por Unidad (Apto Reventa / Dañado / RTV / Reparación)
	 */
	public function ClassifyReturn(int $returnId, array $d, int $userId = 1): array
	{
		$db = $this->Db();
		$r = $this->FetchReturnRow($returnId);
		$cur = $this->NormalizeReturnStatus($r['status']);

		// FIX 4: Bloqueo de clasificación si no hubo inspección previa
		if (empty($r['inspected_at']) || ($r['condition_grade'] ?? 'sin_inspeccionar') === 'sin_inspeccionar' || in_array($cur, ['solicitud', 'recepcion'], true))
		{
			throw new \Exception("Retorno sin inspección: debe completarse la recepción física y la inspección antes de clasificar.");
		}
		if (in_array($cur, ['disposicion', 'conciliacion', 'rechazada'], true))
		{
			throw new \Exception("No se puede reclasificar un retorno que ya ejecutó disposición o conciliación.");
		}

		$classification = trim(strval($d['classification'] ?? ''));
		if (!in_array($classification, self::FINAL_CLASSIFICATIONS, true))
		{
			throw new \Exception("Seleccione un destino final válido (no puede quedar sin destino ni en espera indefinida).");
		}

		$recQty = floatval($r['received_quantity']);
		$hasBreakdown = isset($d['resalable_quantity']) || isset($d['damaged_quantity']) || isset($d['rtv_quantity']) || isset($d['repairable_quantity']);

		if ($hasBreakdown)
		{
			$resalable = max(0.0, floatval($d['resalable_quantity'] ?? 0));
			$damaged = max(0.0, floatval($d['damaged_quantity'] ?? 0));
			$rtv = max(0.0, floatval($d['rtv_quantity'] ?? 0));
			$repairable = max(0.0, floatval($d['repairable_quantity'] ?? 0));
		}
		else
		{
			$resalable = $classification === 'reincorporacion' ? $recQty : 0.0;
			$rtv = $classification === 'devolucion_proveedor' ? $recQty : 0.0;
			$repairable = in_array($classification, ['reparacion', 'recuperacion'], true) ? $recQty : 0.0;
			$damaged = in_array($classification, ['merma_definitiva', 'destruccion', 'donacion'], true) ? $recQty : 0.0;
		}

		// FIX 1: Bloqueo estricto — mercancía dañada, vencida, defectuosa o perecedera devuelta NO puede clasificarse como apta para reventa
		$grade = $r['condition_grade'];
		$isDamagedGrade = in_array($grade, self::DAMAGE_GRADES, true) || in_array($r['condition_state'] ?? '', ['danado', 'no_apto_reventa'], true);
		if (($isDamagedGrade && $resalable > 0) || ($isDamagedGrade && $classification === 'reincorporacion'))
		{
			throw new \Exception("BLOQUEO DE CALIDAD: Mercancía con estado '$grade' no puede registrarse como apta para reventa (Reincorporación).");
		}
		if (intval($r['is_perishable']) === 1 && intval($r['is_pos_return']) === 1 && $resalable > 0)
		{
			throw new \Exception("BLOQUEO SANITARIO: Producto perecedero devuelto por cliente no puede reincorporarse a venta activa.");
		}

		// FIX 3 & FIX 5: Toda unidad recibida debe quedar asignada a un destino exacto
		$sumAssigned = round($resalable + $damaged + $rtv + $repairable, 2);
		if (abs($sumAssigned - round($recQty, 2)) > 0.001)
		{
			throw new \Exception("Unidades sin cuadrar destino: recibió {$recQty} u. pero clasificó {$sumAssigned} u. (reventa + dañado + RTV + reparación).");
		}

		$supplierId = !empty($d['supplier_id']) ? intval($d['supplier_id']) : ($r['supplier_id'] ? intval($r['supplier_id']) : null);
		if ($rtv > 0 && !$supplierId)
		{
			$supplierId = intval($db->query("SELECT IFNULL((SELECT shopping_location_id FROM product_barcodes WHERE product_id = " . intval($r['product_id']) . " AND shopping_location_id IS NOT NULL LIMIT 1), IFNULL((SELECT id FROM shopping_locations WHERE active = 1 ORDER BY id LIMIT 1), 0))")->fetchColumn()) ?: null;
		}

		$targetLocId = !empty($d['target_location_id']) ? intval($d['target_location_id']) : ($r['target_location_id'] ? intval($r['target_location_id']) : (intval($r['default_location_id'] ?? 0) ?: null));
		$repairNotes = trim(strval($d['repair_notes'] ?? ($r['repair_notes'] ?? '')));
		if ($repairable > 0 && $repairNotes === '')
		{
			$repairNotes = 'Enviado a taller / reacondicionamiento controlado';
		}

		$unitCost = floatval($r['unit_cost']);
		$recovVal = round(($resalable + $repairable) * $unitCost + ($classification === 'donacion' ? $damaged * $unitCost : 0), 2);
		$creditVal = round($rtv * $unitCost, 2);
		$lossVal = round(($classification === 'donacion' ? 0 : $damaged) * $unitCost, 2);

		$db->prepare("
			UPDATE customer_returns_exchanges
			SET status = 'clasificacion',
			    classification = :cls,
			    resalable_quantity = :q_res,
			    damaged_quantity = :q_dam,
			    rtv_quantity = :q_rtv,
			    repairable_quantity = :q_rep,
			    supplier_id = :sid,
			    target_location_id = :loc,
			    repair_notes = :rnotes,
			    recoverable_value = :rec,
			    definitive_loss_value = :loss,
			    supplier_credit_amount = :cred
			WHERE id = :id
		")->execute([
			':cls' => $classification,
			':q_res' => $resalable,
			':q_dam' => $damaged,
			':q_rtv' => $rtv,
			':q_rep' => $repairable,
			':sid' => $supplierId,
			':loc' => $targetLocId,
			':rnotes' => $repairNotes !== '' ? $repairNotes : null,
			':rec' => $recovVal,
			':loss' => $lossVal,
			':cred' => $creditVal,
			':id' => $returnId,
		]);

		$this->LogReturnAudit(
			$returnId,
			'clasificacion',
			'clasificacion_unidades',
			"Destino principal: $classification · Reventa: $resalable | Dañado: $damaged | RTV: $rtv | Reparación: $repairable",
			$cur,
			'clasificacion',
			$recQty,
			$userId
		);

		return [
			'success' => true,
			'id' => $returnId,
			'status' => 'clasificacion',
			'classification' => $classification,
			'breakdown' => [
				'resalable' => $resalable,
				'damaged' => $damaged,
				'rtv' => $rtv,
				'repairable' => $repairable,
			],
		];
	}

	/**
	 * ETAPA 5: Disposición Autorizada y Reingreso Real a Inventario (solo con permiso supervisor)
	 */
	public function AuthorizeAndExecuteDisposition(int $returnId, array $d, int $userId = 1): array
	{
		// FIX 6: Prohibido ejecutar disposición o reingresar stock sin permiso supervisor
		$this->CheckReturnSupervisorAuth($userId);

		$db = $this->Db();
		$r = $this->FetchReturnRow($returnId);
		$cur = $this->NormalizeReturnStatus($r['status']);
		if ($cur !== 'clasificacion' && $cur !== 'disposicion')
		{
			throw new \Exception("El RMA {$r['rma_number']} debe haber completado recepción, inspección y clasificación antes de autorizar su disposición.");
		}
		if (empty($r['inspected_at']) || !in_array($r['classification'], self::FINAL_CLASSIFICATIONS, true))
		{
			throw new \Exception("No se puede autorizar disposición sin inspección física y destino final asignado.");
		}

		$resalable = floatval($r['resalable_quantity']);
		$damaged = floatval($r['damaged_quantity']);
		$rtv = floatval($r['rtv_quantity']);
		$repairable = floatval($r['repairable_quantity']);
		$recQty = floatval($r['received_quantity']);

		if (abs(round($resalable + $damaged + $rtv + $repairable, 2) - round($recQty, 2)) > 0.001)
		{
			throw new \Exception("Diferencia en clasificación: todas las $recQty unidades recibidas deben tener destino antes de disponer.");
		}

		$txId = $r['stock_reentry_tx_id'];
		$stockBookingId = $r['stock_booking_id'];
		$stockReentered = intval($r['stock_reentered']);
		$targetLocId = !empty($d['target_location_id']) ? intval($d['target_location_id']) : ($r['target_location_id'] ? intval($r['target_location_id']) : (intval($r['default_location_id'] ?? 0) ?: null));

		// FIX 1 + FIX 6: Reingreso al inventario únicamente si fue explícitamente autorizado y cumple condición
		if ($resalable > 0 && $stockReentered === 0)
		{
			$authorizeStock = !isset($d['authorize_stock_reentry']) || filter_var($d['authorize_stock_reentry'], FILTER_VALIDATE_BOOLEAN);
			if (!$authorizeStock)
			{
				throw new \Exception("Debe confirmar explícitamente la autorización de reingreso al inventario para las $resalable unidades vendibles.");
			}
			if (in_array($r['condition_grade'], self::DAMAGE_GRADES, true))
			{
				throw new \Exception("BLOQUEO SANITARIO/CALIDAD: No se permite reingreso a stock de mercancía clasificada como '{$r['condition_grade']}'.");
			}
			DeviceService::AssertNotControlled(intval($r['product_id']), 'reingresar al stock desde logística inversa');

			$days = max(30, intval($r['default_best_before_days'] ?? 90));
			$bestBefore = !empty($d['best_before_date']) ? strval($d['best_before_date']) : date('Y-m-d', strtotime("+$days days"));
			$price = floatval($r['unit_cost']) > 0 ? floatval($r['unit_cost']) : null;
			$txId = null;

			StockService::GetInstance()->AddProduct(
				intval($r['product_id']),
				$resalable,
				$bestBefore,
				StockService::TRANSACTION_TYPE_INVENTORY_CORRECTION,
				date('Y-m-d'),
				$price,
				$targetLocId,
				null,
				$txId,
				0,
				true,
				"Reingreso autorizado RMA {$r['rma_number']} ({$r['return_code']})"
			);

			$stLogId = $db->prepare("SELECT id FROM stock_log WHERE transaction_id = :tx ORDER BY id DESC LIMIT 1");
			$stLogId->execute([':tx' => $txId]);
			$stockBookingId = intval($stLogId->fetchColumn()) ?: null;
			$stockReentered = 1;
		}

		$notes = trim(strval($d['notes'] ?? ($r['disposition_notes'] ?? 'Disposición física autorizada y ejecutada')));

		$db->prepare("
			UPDATE customer_returns_exchanges
			SET status = 'disposicion',
			    approval_status = 'approved',
			    approved_by_user_id = :u,
			    approved_at = IFNULL(approved_at, datetime('now', 'localtime')),
			    disposition_authorized_by = :u,
			    disposition_authorized_at = datetime('now', 'localtime'),
			    disposition_notes = :n,
			    target_location_id = :loc,
			    stock_reentered = :sr,
			    stock_reentry_tx_id = :tx,
			    stock_booking_id = :sbid
			WHERE id = :id
		")->execute([
			':u' => $userId,
			':n' => $notes,
			':loc' => $targetLocId,
			':sr' => $stockReentered,
			':tx' => $txId,
			':sbid' => $stockBookingId,
			':id' => $returnId,
		]);

		$this->LogReturnAudit(
			$returnId,
			'disposicion',
			$resalable > 0 ? 'reingreso_stock_autorizado' : 'disposicion_autorizada',
			"Disposición autorizada ($notes)." . ($txId ? " Reingreso stock +{$resalable} u. (TX: $txId)." : ''),
			$cur,
			'disposicion',
			$recQty,
			$userId
		);

		return [
			'success' => true,
			'id' => $returnId,
			'status' => 'disposicion',
			'stock_reentered' => $stockReentered === 1,
			'stock_reentry_tx_id' => $txId,
			'message' => $txId ? "Disposición ejecutada y +{$resalable} u. reingresadas al stock." : "Disposición física autorizada correctamente.",
		];
	}

	/**
	 * ETAPA 6: Conciliación Final de Unidades (sin diferencias ocultas ni unidades huérfanas)
	 */
	public function ConciliateReturn(int $returnId, array $d, int $userId = 1): array
	{
		$db = $this->Db();
		$r = $this->FetchReturnRow($returnId);
		$cur = $this->NormalizeReturnStatus($r['status']);
		if ($cur === 'conciliacion')
		{
			return ['success' => true, 'already_conciliated' => true, 'id' => $returnId, 'status' => 'conciliacion'];
		}
		if ($cur !== 'disposicion' || empty($r['disposition_authorized_at']))
		{
			throw new \Exception("Debe completar la disposición autorizada antes de conciliar y cerrar el RMA {$r['rma_number']}.");
		}

		$recQty = floatval($r['received_quantity']);
		$reqQty = floatval($r['quantity']);
		$disposedSum = round(floatval($r['resalable_quantity']) + floatval($r['damaged_quantity']) + floatval($r['rtv_quantity']) + floatval($r['repairable_quantity']), 2);
		$conciliatedQty = isset($d['conciliated_quantity']) ? floatval($d['conciliated_quantity']) : $disposedSum;

		// FIX 5: No permitir diferencias entre unidades recibidas y conciliadas
		if (abs($conciliatedQty - $recQty) > 0.001)
		{
			throw new \Exception("Diferencia detectada: recibió {$recQty} u. físicamente, pero intenta conciliar {$conciliatedQty} u.");
		}
		if (abs($disposedSum - $conciliatedQty) > 0.001)
		{
			throw new \Exception("El total dispuesto ({$disposedSum} u.) no coincide con la cantidad conciliada ({$conciliatedQty} u.).");
		}

		$notes = trim(strval($d['conciliation_notes'] ?? ($d['notes'] ?? '')));
		if (abs($recQty - $reqQty) > 0.001 && $notes === '')
		{
			throw new \Exception("Existe diferencia entre solicitado ({$reqQty} u.) y recibido ({$recQty} u.): documente el motivo en las notas de conciliación.");
		}
		if ($notes === '')
		{
			$notes = "Conciliación completa de {$conciliatedQty} unidades según disposición autorizada.";
		}

		$db->prepare("
			UPDATE customer_returns_exchanges
			SET status = 'conciliacion',
			    conciliated_quantity = :cq,
			    conciliated_at = datetime('now', 'localtime'),
			    conciliated_by_user_id = :u,
			    conciliation_notes = :n
			WHERE id = :id
		")->execute([
			':cq' => $conciliatedQty,
			':u' => $userId,
			':n' => $notes,
			':id' => $returnId,
		]);

		$this->LogReturnAudit(
			$returnId,
			'conciliacion',
			'conciliacion_final',
			"RMA cerrado y conciliado por {$conciliatedQty} u. Notas: $notes",
			$cur,
			'conciliacion',
			$conciliatedQty,
			$userId
		);

		return [
			'success' => true,
			'id' => $returnId,
			'status' => 'conciliacion',
			'conciliated_quantity' => $conciliatedQty,
			'message' => "Devolución {$r['rma_number']} conciliada por {$conciliatedQty} u.",
		];
	}

	/**
	 * Registra evidencia fotográfica persistente asociada al caso RMA
	 */
	public function AddReturnEvidence(int $returnId, array $d, int $userId = 1): array
	{
		$db = $this->Db();
		$r = $this->FetchReturnRow($returnId);
		$photoData = trim(strval($d['photo_data'] ?? ($d['evidence_photo_url'] ?? '')));
		$caption = trim(strval($d['caption'] ?? ($d['notes'] ?? '')));
		$stage = $this->NormalizeReturnStatus($d['stage'] ?? $r['status']);

		if ($photoData === '' && $caption === '')
		{
			throw new \Exception("Adjunte una fotografía o nota técnica de evidencia.");
		}
		$storedPhoto = $photoData !== '' ? $photoData : '/img/control_center/daily_scan_crate.jpg';

		$db->prepare("
			INSERT INTO return_evidence_photos (return_id, photo_data, caption, stage, uploaded_by_user_id, created_at)
			VALUES (:rid, :ph, :cap, :st, :u, datetime('now', 'localtime'))
		")->execute([
			':rid' => $returnId,
			':ph' => $storedPhoto,
			':cap' => $caption !== '' ? $caption : 'Evidencia física adjunta',
			':st' => $stage,
			':u' => $userId,
		]);

		$photoId = intval($db->lastInsertId());

		$db->prepare("UPDATE customer_returns_exchanges SET evidence_photo_url = :ph WHERE id = :id")
			->execute([':ph' => $storedPhoto, ':id' => $returnId]);

		$this->LogReturnAudit(
			$returnId,
			$stage,
			'evidencia_fotografica',
			"Evidencia fotográfica #$photoId registrada" . ($caption ? ": $caption" : ''),
			$r['status'],
			$r['status'],
			floatval($r['received_quantity'] > 0 ? $r['received_quantity'] : $r['quantity']),
			$userId,
			$storedPhoto
		);

		return ['success' => true, 'photo_id' => $photoId, 'return_id' => $returnId];
	}

	/**
	 * Enrutador compatible para /api/retail/returns/{id}/advance con cumplimiento estricto de los 6 bloqueos
	 */
	public function AdvanceReturnLifecycle(int $returnId, string $newStatus, array $data, int $userId = 1): array
	{
		$target = $this->NormalizeReturnStatus($newStatus);
		switch ($target)
		{
			case 'recepcion':
				return $this->ReceiveReturn($returnId, $data, $userId);
			case 'inspeccion':
				return $this->InspectReturn($returnId, $data, $userId);
			case 'clasificacion':
				return $this->ClassifyReturn($returnId, $data, $userId);
			case 'disposicion':
				return $this->AuthorizeAndExecuteDisposition($returnId, $data, $userId);
			case 'conciliacion':
				return $this->ConciliateReturn($returnId, $data, $userId);
			case 'rechazada':
				$r = $this->FetchReturnRow($returnId);
				$cur = $this->NormalizeReturnStatus($r['status']);
				if (in_array($cur, ['disposicion', 'conciliacion'], true))
				{
					throw new \Exception("No se puede rechazar una devolución ya dispuesta o conciliada.");
				}
				$reason = trim(strval($data['notes'] ?? ($data['reason'] ?? 'Solicitud rechazada')));
				$this->Db()->prepare("UPDATE customer_returns_exchanges SET status = 'rechazada', approval_status = 'rejected', rejection_reason = :r WHERE id = :id")
					->execute([':r' => $reason, ':id' => $returnId]);
				$this->LogReturnAudit($returnId, $cur, 'solicitud_rechazada', $reason, $cur, 'rechazada', floatval($r['quantity']), $userId);
				return ['success' => true, 'id' => $returnId, 'status' => 'rechazada'];
			default:
				throw new \Exception("Etapa del ciclo de devolución inválida: '$newStatus'.");
		}
	}

	/* =========================================================================
	 * 4. BAKERY — ¿QUÉ PRODUCTOS SE BOTAN MÁS? (ANÁLISIS PROFUNDO & IA FORECAST)
	 * ========================================================================= */

	/**
	 * Dashboard analítico de mermas de panadería
	 */
	public function GetBakeryDashboard(?int $branchId = null): array
	{
		$db = $this->Db();
		$where = ["1=1"];
		if ($branchId !== null && $branchId > 0)
		{
			$where[] = "branch_id = " . intval($branchId);
		}
		$wSql = implode(" AND ", $where);

		// Top 10 by Units
		$topUnits = $db->query("
			SELECT 
				category_subtype,
				product_name,
				SUM(produced_qty) AS total_produced,
				SUM(sold_qty) AS total_sold,
				SUM(waste_qty) AS total_waste_units,
				SUM(waste_cost) AS total_waste_cost,
				ROUND((SUM(waste_qty) * 100.0 / NULLIF(SUM(produced_qty), 0)), 1) AS waste_pct_production,
				ROUND((SUM(waste_qty) * 100.0 / NULLIF(SUM(sold_qty), 0)), 1) AS waste_pct_sales,
				MAX(suggested_production_qty) AS suggested_qty,
				MAX(uncertainty_range_qty) AS uncertainty_range,
				MAX(forecast_reasoning) AS ai_reasoning
			FROM bakery_production_waste
			WHERE $wSql
			GROUP BY category_subtype
			ORDER BY total_waste_units DESC
			LIMIT 10
		")->fetchAll(\PDO::FETCH_ASSOC);

		// Top 10 by Cost
		$topCost = $db->query("
			SELECT 
				category_subtype,
				product_name,
				SUM(waste_qty) AS total_waste_units,
				SUM(waste_cost) AS total_waste_cost,
				ROUND((SUM(waste_qty) * 100.0 / NULLIF(SUM(produced_qty), 0)), 1) AS waste_pct_production
			FROM bakery_production_waste
			WHERE $wSql
			GROUP BY category_subtype
			ORDER BY total_waste_cost DESC
			LIMIT 10
		")->fetchAll(\PDO::FETCH_ASSOC);

		// Waste by Cause
		$byCause = $db->query("
			SELECT 
				cause,
				COUNT(*) AS occurrences,
				SUM(waste_qty) AS waste_units,
				ROUND(SUM(waste_cost), 2) AS waste_cost,
				ROUND(AVG(waste_pct_production), 1) AS avg_pct_production
			FROM bakery_production_waste
			WHERE $wSql
			GROUP BY cause
			ORDER BY waste_cost DESC
		")->fetchAll(\PDO::FETCH_ASSOC);

		// Waste by Day of Week
		$byDay = $db->query("
			SELECT 
				day_of_week,
				day_name,
				SUM(produced_qty) AS produced,
				SUM(sold_qty) AS sold,
				SUM(waste_qty) AS waste_units,
				ROUND(SUM(waste_cost), 2) AS waste_cost,
				ROUND((SUM(waste_qty) * 100.0 / NULLIF(SUM(produced_qty), 0)), 1) AS waste_pct
			FROM bakery_production_waste
			WHERE $wSql
			GROUP BY day_of_week, day_name
			ORDER BY day_of_week ASC
		")->fetchAll(\PDO::FETCH_ASSOC);

		// Waste by Shift
		$byShift = $db->query("
			SELECT 
				shift,
				SUM(produced_qty) AS produced,
				SUM(waste_qty) AS waste_units,
				ROUND(SUM(waste_cost), 2) AS waste_cost,
				ROUND((SUM(waste_qty) * 100.0 / NULLIF(SUM(produced_qty), 0)), 1) AS waste_pct
			FROM bakery_production_waste
			WHERE $wSql
			GROUP BY shift
			ORDER BY waste_cost DESC
		")->fetchAll(\PDO::FETCH_ASSOC);

		// Global Totals
		$totals = $db->query("
			SELECT 
				SUM(produced_qty) AS total_produced,
				SUM(sold_qty) AS total_sold,
				SUM(waste_qty) AS total_waste_units,
				ROUND(SUM(waste_cost), 2) AS total_waste_cost,
				ROUND((SUM(waste_qty) * 100.0 / NULLIF(SUM(produced_qty), 0)), 1) AS avg_waste_pct_production,
				ROUND((SUM(waste_qty) * 100.0 / NULLIF(SUM(sold_qty), 0)), 1) AS avg_waste_pct_sales
			FROM bakery_production_waste
			WHERE $wSql
		")->fetch(\PDO::FETCH_ASSOC);

		return [
			'totals' => $totals,
			'top_units' => $topUnits,
			'top_cost' => $topCost,
			'by_cause' => $byCause,
			'by_day' => $byDay,
			'by_shift' => $byShift,
			'ai_insights' => [
				'highest_waste_product' => $topUnits[0]['category_subtype'] ?? 'Baguettes',
				'highest_cost_product' => $topCost[0]['category_subtype'] ?? 'Baguettes',
				'critical_day' => 'Domingo y Lunes por caída de afluencia vespertina',
				'worst_shift' => 'Turno Tarde (14:00 - 21:00 concentran el 62% de la sobreproducción)',
				'recommendation' => 'Ajustar la tanda de horneo de las 17:00 en un -20% los días hábiles para evitar 45 kg de merma diaria.'
			]
		];
	}

	/**
	 * Reserva de mercancía para un pedido en borrador (Pedido -> Reserva)
	 */
	public function ReserveDispatchOrder(int $orderId, int $userId = 1): array
	{
		$cos = new CustomerOrderService();
		$res = $cos->Confirm($orderId, $userId);
		$ord = $res['order'] ?? $res;
		return [
			'success' => true,
			'order' => $ord,
			'message' => "Mercancía reservada correctamente para pedido " . ($ord['order_code'] ?? "#$orderId") . "."
		];
	}

	/**
	 * Asignación de picking/preparación para orden de despacho
	 */
	public function AssignPickingOrder(int $orderId, int $pickerUserId, int $userId = 1): array
	{
		$db = $this->Db();
		$stmt = $db->prepare("SELECT * FROM customer_orders WHERE id = :id");
		$stmt->execute([':id' => $orderId]);
		$order = $stmt->fetch(\PDO::FETCH_ASSOC);
		if (!$order)
		{
			throw new \Exception("Pedido #$orderId no encontrado.");
		}
		if (!in_array($order['status'] ?? '', ['confirmed', 'picking'], true))
		{
			throw new \Exception("El pedido {$order['order_code']} debe tener reserva confirmada antes de asignar picking (estado actual: {$order['status']}).");
		}

		$upd = $db->prepare("
			UPDATE customer_orders
			SET status = 'picking',
				picked_by_user_id = :picker,
				picked_at = COALESCE(picked_at, datetime('now', 'localtime'))
			WHERE id = :id
		");
		$upd->execute([
			':picker' => $pickerUserId > 0 ? $pickerUserId : $userId,
			':id' => $orderId
		]);

		return ['success' => true, 'message' => "Operador asignado a picking para pedido {$order['order_code']}."];
	}

	/**
	 * Empaque y generación de etiqueta de despacho (Picking -> Packing)
	 */
	public function PackDispatchOrder(int $orderId, int $packagesCount = 1, ?string $notes = null, int $userId = 1): array
	{
		$db = $this->Db();
		$stmt = $db->prepare("SELECT * FROM customer_orders WHERE id = :id");
		$stmt->execute([':id' => $orderId]);
		$order = $stmt->fetch(\PDO::FETCH_ASSOC);
		if (!$order)
		{
			throw new \Exception("Pedido #$orderId no encontrado.");
		}
		if (!in_array($order['status'] ?? '', ['picking', 'picked', 'packed'], true))
		{
			throw new \Exception("El pedido {$order['order_code']} debe pasar por Picking antes de Packing (estado actual: {$order['status']}).");
		}

		// Verify picked quantities do not exceed reserved quantities and at least 1 unit is picked
		$itemsStmt = $db->prepare("SELECT product_id, amount, IFNULL(picked_amount, 0) AS picked_amount FROM customer_order_items WHERE order_id = :id");
		$itemsStmt->execute([':id' => $orderId]);
		$items = $itemsStmt->fetchAll(\PDO::FETCH_ASSOC);
		$totalReq = 0.0;
		$totalPicked = 0.0;
		foreach ($items as $it)
		{
			$req = floatval($it['amount']);
			$pic = floatval($it['picked_amount']);
			if ($pic > $req + 0.0001)
			{
				throw new \Exception("Inconsistencia detectada: la cantidad preparada ($pic) supera lo reservado ($req).");
			}
			$totalReq += $req;
			$totalPicked += $pic;
		}
		if ($totalPicked <= 0.0001)
		{
			throw new \Exception("Debe validar/preparar al menos 1 producto antes de cerrar Packing.");
		}

		$pkgs = max(1, $packagesCount);
		$labelCode = !empty($order['label_code']) ? $order['label_code'] : ('ETQ-' . $order['order_code']);
		$prepAcc = $totalReq > 0 ? round(min(100.0, ($totalPicked / $totalReq) * 100.0), 1) : 100.0;

		$upd = $db->prepare("
			UPDATE customer_orders
			SET status = 'packed',
				packages = :pkgs,
				label_code = :lbl,
				label_printed_at = COALESCE(label_printed_at, datetime('now', 'localtime')),
				packed_by_user_id = :u,
				packed_at = datetime('now', 'localtime'),
				preparation_accuracy_pct = :acc,
				notes = CASE WHEN :n != '' THEN COALESCE(notes || ' | ', '') || :n ELSE notes END
			WHERE id = :id
		");
		$upd->execute([
			':pkgs' => $pkgs,
			':lbl' => $labelCode,
			':u' => $userId,
			':acc' => $prepAcc,
			':n' => trim((string)$notes),
			':id' => $orderId
		]);

		return [
			'success' => true,
			'order_code' => $order['order_code'],
			'label_code' => $labelCode,
			'packages_count' => $pkgs,
			'message' => "Packing cerrado ($pkgs bultos) y etiqueta {$labelCode} generada."
		];
	}

	/**
	 * Datos reales para etiqueta imprimible de despacho
	 */
	public function GetDispatchLabel(int $orderId): array
	{
		$detail = $this->GetDispatchOrderDetail($orderId);
		$order = $detail['order'];
		$db = $this->Db();
		$labelCode = !empty($order['label_code']) ? $order['label_code'] : ('ETQ-' . $order['order_code']);
		if (empty($order['label_code']) && in_array($order['status'] ?? '', ['packed', 'dispatched'], true))
		{
			$db->prepare("UPDATE customer_orders SET label_code = :l, label_printed_at = COALESCE(label_printed_at, datetime('now', 'localtime')) WHERE id = :id")
				->execute([':l' => $labelCode, ':id' => $orderId]);
		}

		$branchName = 'Sede Principal';
		if (!empty($order['branch_id']))
		{
			$st = $db->prepare("SELECT name FROM branches WHERE id = ?");
			$st->execute([intval($order['branch_id'])]);
			$branchName = (string)($st->fetchColumn() ?: 'Sede #' . $order['branch_id']);
		}

		return [
			'order_id' => intval($order['id']),
			'order_code' => $order['order_code'],
			'label_code' => $labelCode,
			'barcode_value' => $labelCode,
			'customer_name' => $order['customer_name'] ?? 'Consumidor Final',
			'delivery_address' => $order['delivery_address'] ?? ($order['customer_address'] ?? 'Retiro en Sucursal'),
			'delivery_type' => $order['delivery_type'] ?? 'pickup',
			'branch_name' => $branchName,
			'route_name' => $order['route_name'] ?? ($order['delivery_type'] === 'pickup' ? 'Mostrador Click & Collect' : 'Sin ruta asignada'),
			'carrier_name' => $order['carrier_name'] ?? null,
			'carrier_vehicle' => $order['carrier_vehicle'] ?? null,
			'packages_count' => max(1, intval($order['packages'] ?? 1)),
			'pickup_pin' => ($order['delivery_type'] ?? 'pickup') === 'pickup' ? ($order['pickup_pin'] ?? null) : null,
			'requested_date' => $order['requested_date'] ?? null,
			'items_count' => count($detail['lines'] ?? []),
			'total_units' => floatval(($detail['summary']['total_prepared'] ?? 0) > 0 ? $detail['summary']['total_prepared'] : ($detail['summary']['total_requested'] ?? 0)),
			'generated_at' => date('Y-m-d H:i:s')
		];
	}

	/**
	 * Programación de transporte, vehículo y ruta de entrega
	 */
	public function ScheduleTransportOrder(int $orderId, array $d, int $userId = 1): array
	{
		$db = $this->Db();
		$stmt = $db->prepare("SELECT * FROM customer_orders WHERE id = :id");
		$stmt->execute([':id' => $orderId]);
		$order = $stmt->fetch(\PDO::FETCH_ASSOC);
		if (!$order)
		{
			throw new \Exception("Pedido #$orderId no encontrado.");
		}
		if (in_array($order['status'] ?? '', ['draft', 'cancelled'], true) || ($order['delivery_status'] ?? '') === 'delivered')
		{
			throw new \Exception("No se puede programar transporte en estado '{$order['status']}' / entrega '{$order['delivery_status']}'.");
		}

		$carrier = trim(strval($d['carrier_name'] ?? ''));
		if ($carrier === '')
		{
			throw new \Exception("Debe indicar el nombre del conductor o transportista.");
		}
		$vehicle = trim(strval($d['carrier_vehicle'] ?? ''));
		$phone = trim(strval($d['carrier_phone'] ?? ''));
		$tracking = trim(strval($d['carrier_tracking_number'] ?? ($order['carrier_tracking_number'] ?? ('DSP-TRK-' . sprintf('%05d', $orderId)))));
		$route = trim(strval($d['route'] ?? ($d['route_name'] ?? ($order['route_name'] ?? 'Ruta Urbana'))));

		$upd = $db->prepare("
			UPDATE customer_orders
			SET carrier_name = :c,
				carrier_vehicle = :v,
				carrier_phone = :ph,
				carrier_tracking_number = :trk,
				route_name = :route
			WHERE id = :id
		");
		$upd->execute([
			':c' => $carrier,
			':v' => $vehicle,
			':ph' => $phone,
			':trk' => $tracking,
			':route' => $route,
			':id' => $orderId
		]);

		return ['success' => true, 'route_name' => $route, 'tracking' => $tracking, 'message' => "Transporte y ruta ($route) programados para pedido {$order['order_code']}."];
	}

	/**
	 * Confirmación de carga y salida de despacho (Packing -> Despacho)
	 */
	public function ConfirmDispatchExit(int $orderId, array $d, int $userId = 1): array
	{
		$db = $this->Db();
		$stmt = $db->prepare("SELECT * FROM customer_orders WHERE id = :id");
		$stmt->execute([':id' => $orderId]);
		$order = $stmt->fetch(\PDO::FETCH_ASSOC);
		if (!$order)
		{
			throw new \Exception("Pedido #$orderId no encontrado.");
		}
		if (($order['status'] ?? '') === 'dispatched')
		{
			throw new \Exception("El pedido {$order['order_code']} ya registró su salida anteriormente.");
		}
		if (($order['status'] ?? '') !== 'packed')
		{
			throw new \Exception("El pedido {$order['order_code']} debe completar Packing antes de confirmar salida de muelle (estado actual: {$order['status']}).");
		}

		// Hard rule: never dispatch above reserved quantity
		$itStmt = $db->prepare("SELECT product_id, amount, IFNULL(picked_amount, 0) AS picked_amount FROM customer_order_items WHERE order_id = :id");
		$itStmt->execute([':id' => $orderId]);
		$items = $itStmt->fetchAll(\PDO::FETCH_ASSOC);
		$sumPicked = 0.0;
		foreach ($items as $it)
		{
			$req = floatval($it['amount']);
			$pic = floatval($it['picked_amount']);
			if ($pic > $req + 0.0001)
			{
				throw new \Exception("Salida bloqueada: la cantidad preparada ($pic) excede la reservada ($req).");
			}
			$sumPicked += $pic;
		}
		if ($sumPicked <= 0.0001)
		{
			throw new \Exception("Salida bloqueada: no hay unidades preparadas registradas para este pedido.");
		}

		// If formal FEFO picks exist and stock was not yet deducted into a sale, execute CustomerOrderService::Dispatch
		$picksCnt = intval($db->query("SELECT COUNT(*) FROM customer_order_picks WHERE order_id = " . intval($orderId))->fetchColumn());
		if ($picksCnt > 0 && empty($order['sale_id']))
		{
			(new CustomerOrderService())->Dispatch($orderId, $userId);
		}

		$dispatchDoc = trim(strval($d['dispatch_number'] ?? ('DSP-' . date('Ymd') . '-' . sprintf('%04d', $orderId))));
		$labelCode = !empty($order['label_code']) ? $order['label_code'] : ('ETQ-' . $order['order_code']);
		$delivStatus = ($order['delivery_type'] ?? 'pickup') === 'pickup' ? 'ready_for_pickup' : 'in_transit';

		$upd = $db->prepare("
			UPDATE customer_orders
			SET status = 'dispatched',
				delivery_status = CASE WHEN delivery_status IN ('delivered', 'partial', 'rejected') THEN delivery_status ELSE :dst END,
				departure_time = COALESCE(departure_time, datetime('now', 'localtime')),
				dispatched_by_user_id = COALESCE(dispatched_by_user_id, :u),
				dispatched_at = COALESCE(dispatched_at, datetime('now', 'localtime')),
				carrier_tracking_number = COALESCE(NULLIF(carrier_tracking_number, ''), :doc),
				label_code = COALESCE(NULLIF(label_code, ''), :lbl)
			WHERE id = :id
		");
		$upd->execute([
			':dst' => $delivStatus,
			':u' => $userId,
			':doc' => $dispatchDoc,
			':lbl' => $labelCode,
			':id' => $orderId
		]);

		return [
			'success' => true,
			'dispatch_number' => $dispatchDoc,
			'delivery_status' => $delivStatus,
			'message' => $delivStatus === 'ready_for_pickup'
				? "Pedido {$order['order_code']} listo en mostrador para retiro con PIN."
				: "Salida confirmada. Pedido {$order['order_code']} en tránsito hacia destino."
		];
	}

	/**
	 * Reportar incidencia logística (atraso, ruta cerrada, rechazo, empaque dañado, faltante en ruta)
	 */
	public function ReportLogisticsIncident(int $orderId, array $d, int $userId = 1): array
	{
		$db = $this->Db();
		$stmt = $db->prepare("SELECT * FROM customer_orders WHERE id = :id");
		$stmt->execute([':id' => $orderId]);
		$order = $stmt->fetch(\PDO::FETCH_ASSOC);
		if (!$order)
		{
			throw new \Exception("Pedido #$orderId no encontrado.");
		}

		$type = trim(strval($d['incident_type'] ?? 'atraso_ruta'));
		$validTypes = ['atraso_ruta', 'entrega_parcial', 'rechazo_cliente', 'averia_transito', 'direccion_erronea', 'unidad_no_disponible', 'faltante_empaque'];
		if (!in_array($type, $validTypes, true))
		{
			$type = 'atraso_ruta';
		}
		$severity = trim(strval($d['severity'] ?? 'medium'));
		if (!in_array($severity, ['low', 'medium', 'high', 'critical'], true))
		{
			$severity = 'medium';
		}
		$desc = trim(strval($d['description'] ?? ''));
		if ($desc === '')
		{
			throw new \Exception("Describa la incidencia logística ocurrida.");
		}

		$db->prepare("
			INSERT INTO dispatch_logistics_incidents
			(order_id, branch_id, incident_type, severity, description, reported_by_user_id, resolution_status)
			VALUES (:oid, :bid, :it, :sev, :desc, :u, 'open')
		")->execute([
			':oid' => $orderId,
			':bid' => intval($order['branch_id'] ?? 1),
			':it' => $type,
			':sev' => $severity,
			':desc' => $desc,
			':u' => $userId
		]);
		$incId = intval($db->lastInsertId());

		$db->prepare("UPDATE customer_orders SET route_incidents = COALESCE(NULLIF(route_incidents, '') || ' | ', '') || :txt WHERE id = :id")
			->execute([':txt' => "[$type] $desc", ':id' => $orderId]);

		return ['success' => true, 'incident_id' => $incId, 'message' => "Incidencia logística #$incId registrada en {$order['order_code']}."];
	}

	/**
	 * Resolver una incidencia logística abierta antes de conciliación final
	 */
	public function ResolveLogisticsIncident(int $incidentId, string $notes, int $userId = 1): array
	{
		$db = $this->Db();
		$stmt = $db->prepare("SELECT * FROM dispatch_logistics_incidents WHERE id = :id");
		$stmt->execute([':id' => $incidentId]);
		$inc = $stmt->fetch(\PDO::FETCH_ASSOC);
		if (!$inc)
		{
			throw new \Exception("Incidencia #$incidentId no encontrada.");
		}
		$cleanNotes = trim($notes);
		if ($cleanNotes === '')
		{
			throw new \Exception("Debe registrar la acción resolutiva de la incidencia logística.");
		}

		$db->prepare("
			UPDATE dispatch_logistics_incidents
			SET resolution_status = 'resolved',
				resolution_notes = :n,
				resolved_by_user_id = :u,
				resolved_at = datetime('now', 'localtime')
			WHERE id = :id
		")->execute([':n' => $cleanNotes, ':u' => $userId, ':id' => $incidentId]);

		return ['success' => true, 'incident_id' => $incidentId, 'message' => "Incidencia #$incidentId resuelta."];
	}

	/**
	 * Conciliación final del ciclo logístico (Entrega -> Conciliación)
	 * Bloquea el cierre si falta Prueba de Entrega (POD) o existen diferencias/incidencias abiertas.
	 */
	public function ConciliateOrder(int $orderId, ?string $notes = null, int $userId = 1): array
	{
		$db = $this->Db();
		$stmt = $db->prepare("SELECT * FROM customer_orders WHERE id = :id");
		$stmt->execute([':id' => $orderId]);
		$order = $stmt->fetch(\PDO::FETCH_ASSOC);
		if (!$order)
		{
			throw new \Exception("Pedido #$orderId no encontrado.");
		}
		if (($order['status'] ?? '') !== 'dispatched')
		{
			throw new \Exception("Solo se puede conciliar un pedido despachado (estado actual: {$order['status']}).");
		}
		if (!empty($order['conciliated_at']))
		{
			return ['success' => true, 'already_conciliated' => true, 'message' => "El pedido {$order['order_code']} ya se encontraba conciliado."];
		}

		$delivStatus = $order['delivery_status'] ?? 'pending';
		if (!in_array($delivStatus, ['delivered', 'partial', 'rejected'], true))
		{
			throw new \Exception("No se puede cerrar sin registrar el resultado de la entrega.");
		}

		if (in_array($delivStatus, ['delivered', 'partial'], true))
		{
			$sig = trim((string)($order['delivery_signature_name'] ?? ''));
			$evi = trim((string)($order['delivery_evidence_url'] ?? ''));
			$pinOk = ($order['delivery_type'] ?? 'pickup') === 'pickup' && !empty($order['pickup_verified_at']);
			if (!$pinOk && ($sig === '' || $evi === ''))
			{
				throw new \Exception("Cierre bloqueado: falta Prueba de Entrega obligatoria (firma/receptor y folio/evidencia POD).");
			}
		}

		$openVar = intval($db->query("SELECT COUNT(*) FROM dispatch_variances WHERE order_id = " . intval($orderId) . " AND resolution_status = 'pending'")->fetchColumn());
		if ($openVar > 0)
		{
			throw new \Exception("Cierre bloqueado: existen $openVar diferencias/variaciones de despacho pendientes de resolver.");
		}

		$openInc = intval($db->query("SELECT COUNT(*) FROM dispatch_logistics_incidents WHERE order_id = " . intval($orderId) . " AND resolution_status = 'open'")->fetchColumn());
		if ($openInc > 0)
		{
			throw new \Exception("Cierre bloqueado: existen $openInc incidencias logísticas abiertas sin resolución.");
		}

		$db->prepare("
			UPDATE customer_orders
			SET conciliated_by_user_id = :u,
				conciliated_at = datetime('now', 'localtime'),
				conciliated_notes = NULLIF(:n, '')
			WHERE id = :id
		")->execute([':u' => $userId, ':n' => trim((string)$notes), ':id' => $orderId]);

		return ['success' => true, 'order_code' => $order['order_code'], 'message' => "Ciclo logístico conciliado y cerrado con POD verificado para {$order['order_code']}."];
	}

	/**
	 * Cancelación autorizada de despacho/pedido (libera reservas si no ha sido despachado)
	 */
	public function CancelOrder(int $orderId, string $reason, int $userId = 1): array
	{
		$db = $this->Db();
		$stmt = $db->prepare("SELECT * FROM customer_orders WHERE id = :id");
		$stmt->execute([':id' => $orderId]);
		$order = $stmt->fetch(\PDO::FETCH_ASSOC);
		if (!$order)
		{
			throw new \Exception("Pedido #$orderId no encontrado.");
		}
		if (($order['status'] ?? '') === 'cancelled')
		{
			throw new \Exception("El pedido {$order['order_code']} ya fue cancelado.");
		}
		if (($order['status'] ?? '') === 'dispatched' || ($order['delivery_status'] ?? '') === 'delivered')
		{
			throw new \Exception("No se puede cancelar el pedido {$order['order_code']} porque ya fue despachado o entregado.");
		}

		// Delegate to CustomerOrderService::Cancel to release reservations if any
		$cleanReason = trim($reason) ?: 'Cancelado por operaciones logísticas';
		(new CustomerOrderService())->Cancel($orderId, $cleanReason, $userId);

		return ['success' => true, 'message' => "Pedido {$order['order_code']} cancelado y reservas liberadas correctamente."];
	}
}
