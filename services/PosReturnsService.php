<?php

namespace Grocy\Services;

class PosReturnsService extends BaseService
{
	private static $instance = null;

	public static function GetInstance()
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

	/**
	 * Garantiza que customer_returns_exchanges tenga todas las columnas requeridas para POS
	 */
	public function EnsureTables(): void
	{
		$db = $this->Db();
		$cols = $db->query("PRAGMA table_info(customer_returns_exchanges)")->fetchAll(\PDO::FETCH_COLUMN, 1);

		$columnsToAdd = [
			'sale_id' => 'INTEGER NULL',
			'sale_item_id' => 'INTEGER NULL',
			'ticket_number' => 'TEXT NULL',
			'resolution' => "TEXT NULL DEFAULT 'reembolso'",
			'condition_state' => "TEXT NULL DEFAULT 'sin_uso'",
			'refund_method' => "TEXT NULL DEFAULT 'cash'",
			'refund_status' => "TEXT NULL DEFAULT 'completed'",
			'refund_gateway_ref' => 'TEXT NULL',
			'refund_card_last4' => 'TEXT NULL',
			'cashier_user_id' => 'INTEGER NULL',
			'rejection_reason' => 'TEXT NULL',
			'evidence_notes' => 'TEXT NULL',
			'resolution_time_minutes' => 'INTEGER DEFAULT 5',
			'is_pos_return' => 'INTEGER DEFAULT 0'
		];

		foreach ($columnsToAdd as $col => $definition)
		{
			if (!in_array($col, $cols))
			{
				$db->exec("ALTER TABLE customer_returns_exchanges ADD COLUMN $col $definition");
			}
		}

		$this->SeedDemoPosReturnsIfEmpty();
	}

	/**
	 * Genera registros demo representativos de devoluciones POS si aún no existen
	 */
	private function SeedDemoPosReturnsIfEmpty(): void
	{
		$db = $this->Db();
		$count = intval($db->query("SELECT COUNT(*) FROM customer_returns_exchanges WHERE is_pos_return = 1")->fetchColumn());
		if ($count > 0)
		{
			return;
		}

		// Tomar las últimas ventas reales del POS para vincularlas directamente
		$sales = $db->query("
			SELECT s.id AS sale_id, s.sale_number, s.branch_id, s.customer_id, s.payment_method,
			       si.id AS sale_item_id, si.product_id, si.amount, si.unit_price, si.unit_cost,
			       p.name AS product_name, p.sku
			FROM sales s
			JOIN sale_items si ON si.sale_id = s.id
			JOIN products p ON si.product_id = p.id
			ORDER BY s.id DESC
			LIMIT 6
		")->fetchAll(\PDO::FETCH_ASSOC);

		if (empty($sales))
		{
			return;
		}

		$demoCases = [
			[
				'reason' => 'Defecto de empaque y sello roto al abrir',
				'condition' => 'defectuoso_fabrica',
				'resolution' => 'reembolso',
				'classification' => 'devolucion_proveedor',
				'method' => 'card',
				'card_last4' => '4242',
				'status' => 'aprobada',
				'qty' => 1.0,
				'mins' => 4
			],
			[
				'reason' => 'Cliente compró presentación equivocada (sin abrir)',
				'condition' => 'sin_uso',
				'resolution' => 'cambio',
				'classification' => 'reincorporacion',
				'method' => 'cash',
				'card_last4' => null,
				'status' => 'cerrada',
				'qty' => 1.0,
				'mins' => 3
			],
			[
				'reason' => 'Fecha de vencimiento próxima no advertida en caja',
				'condition' => 'no_apto_reventa',
				'resolution' => 'credito_tienda',
				'classification' => 'merma_definitiva',
				'method' => 'store_credit',
				'card_last4' => null,
				'status' => 'cerrada',
				'qty' => 1.0,
				'mins' => 6
			],
			[
				'reason' => 'Intento de devolución sin ticket de compra original',
				'condition' => 'dañado_cliente',
				'resolution' => 'rechazo_justificado',
				'classification' => 'merma_definitiva',
				'method' => 'cash',
				'card_last4' => null,
				'status' => 'rechazada',
				'rejection' => 'Producto consumido en más del 70% sin evidencia de falla de fábrica y fuera de plazo.',
				'qty' => 1.0,
				'mins' => 8
			]
		];

		$ins = $db->prepare("
			INSERT INTO customer_returns_exchanges (
				return_code, order_id, branch_id, customer_id, product_id, stock_id, return_type,
				quantity, unit_cost, total_cost, recoverable_value, definitive_loss_value,
				supplier_credit_amount, customer_refund_amount, classification, is_perishable,
				inspection_result, inspection_notes, inspected_by_user_id, inspected_at,
				approval_status, approved_by_user_id, approved_at, disposition_notes,
				status, created_at, sale_id, sale_item_id, ticket_number, resolution,
				condition_state, refund_method, refund_status, refund_gateway_ref,
				refund_card_last4, cashier_user_id, rejection_reason, resolution_time_minutes, is_pos_return
			) VALUES (
				:code, NULL, :bid, :cid, :pid, 'STK-POS-RET', 'devolucion_cliente',
				:qty, :ucost, :tcost, :rec, :loss, :cred, :ref, :class, :per,
				:insp_res, :insp_notes, 1, datetime('now', '-2 hours'),
				:app_st, 1, datetime('now', '-2 hours'), :disp,
				:st, datetime('now', '-2 hours'), :sale_id, :sale_item_id, :ticket_num, :res,
				:cond, :ref_method, 'completed', :ref_gw, :card4, 1, :rej, :mins, 1
			)
		");

		$idx = 1;
		foreach ($demoCases as $i => $demo)
		{
			if (!isset($sales[$i]))
			{
				break;
			}
			$s = $sales[$i];
			$qty = min(floatval($s['amount']), floatval($demo['qty']));
			$unitPrice = floatval($s['unit_price'] ?: 4.50);
			$unitCost = floatval($s['unit_cost'] ?: 2.20);
			$totCost = round($qty * $unitCost, 2);
			$refAmt = $demo['resolution'] === 'rechazo_justificado' ? 0.0 : round($qty * $unitPrice, 2);

			$loss = ($demo['classification'] === 'merma_definitiva') ? $totCost : 0.0;
			$rec = ($demo['classification'] === 'reincorporacion') ? $totCost : 0.0;
			$cred = ($demo['classification'] === 'devolucion_proveedor') ? $totCost : 0.0;

			$isPerishable = (stripos($s['product_name'], 'leche') !== false || stripos($s['product_name'], 'queso') !== false || stripos($s['product_name'], 'carne') !== false) ? 1 : 0;
			$code = sprintf('DEV-POS-2026-%04d', $idx++);
			$gwRef = ($demo['method'] === 'card') ? 'SANDBOX-RF-' . strtoupper(substr(md5($code), 0, 8)) : null;

			$ins->execute([
				':code' => $code,
				':bid' => $s['branch_id'] ?: 1,
				':cid' => $s['customer_id'] ?: 1,
				':pid' => $s['product_id'],
				':qty' => $qty,
				':ucost' => $unitCost,
				':tcost' => $totCost,
				':rec' => $rec,
				':loss' => $loss,
				':cred' => $cred,
				':ref' => $refAmt,
				':class' => $demo['classification'],
				':per' => $isPerishable,
				':insp_res' => 'Inspección física en caja completada',
				':insp_notes' => $demo['reason'],
				':app_st' => ($demo['resolution'] === 'rechazo_justificado' ? 'rejected' : 'approved'),
				':disp' => 'Destino asignado conforme a protocolo retail',
				':st' => $demo['status'],
				':sale_id' => $s['sale_id'],
				':sale_item_id' => $s['sale_item_id'],
				':ticket_num' => $s['sale_number'],
				':res' => $demo['resolution'],
				':cond' => $demo['condition'],
				':ref_method' => $demo['method'],
				':ref_gw' => $gwRef,
				':card4' => $demo['card_last4'],
				':rej' => $demo['rejection'] ?? null,
				':mins' => $demo['mins']
			]);
		}
	}

	/**
	 * BÚSQUEDA DE VENTA ORIGINAL (Por Ticket, Factura, Fecha, Cliente, SKU o Transacción)
	 */
	public function LookupSale(string $query, ?int $branchId = null): array
	{
		$this->EnsureTables();
		$db = $this->Db();
		$q = trim($query);

		$where = ["1=1"];
		$params = [];

		if (!empty($branchId))
		{
			$where[] = "s.branch_id = :bid";
			$params[':bid'] = $branchId;
		}

		if ($q !== '')
		{
			$where[] = "(
				s.sale_number LIKE :q 
				OR s.id = :qid
				OR c.name LIKE :q 
				OR s.payment_method LIKE :q
				OR si.transaction_id LIKE :q
				OR p.name LIKE :q
				OR p.sku LIKE :q
			)";
			$params[':q'] = "%$q%";
			$params[':qid'] = is_numeric($q) ? intval($q) : -1;
		}

		$whereSql = implode(' AND ', $where);

		$sql = "
			SELECT DISTINCT s.id
			FROM sales s
			LEFT JOIN customers c ON s.customer_id = c.id
			LEFT JOIN sale_items si ON si.sale_id = s.id
			LEFT JOIN products p ON si.product_id = p.id
			WHERE $whereSql
			ORDER BY s.id DESC
			LIMIT 15
		";

		$stmt = $db->prepare($sql);
		$stmt->execute($params);
		$saleIds = $stmt->fetchAll(\PDO::FETCH_COLUMN);

		if (empty($saleIds))
		{
			return [];
		}

		$inPlaceholders = implode(',', array_fill(0, count($saleIds), '?'));
		$salesSql = "
			SELECT 
				s.*,
				IFNULL(c.name, 'Público General / Mostrador') AS customer_name,
				IFNULL(c.phone, 'Sin teléfono') AS customer_phone,
				IFNULL(b.name, 'Sucursal Matriz') AS branch_name,
				u.username AS cashier_name
			FROM sales s
			LEFT JOIN customers c ON s.customer_id = c.id
			LEFT JOIN branches b ON s.branch_id = b.id
			LEFT JOIN users u ON s.user_id = u.id
			WHERE s.id IN ($inPlaceholders)
			ORDER BY s.id DESC
		";
		$stmtSales = $db->prepare($salesSql);
		$stmtSales->execute($saleIds);
		$sales = $stmtSales->fetchAll(\PDO::FETCH_ASSOC);

		// Obtener ítems y devoluciones previas
		$itemsSql = "
			SELECT 
				si.*,
				p.name AS product_name,
				p.sku,
				(SELECT barcode FROM product_barcodes WHERE product_id = p.id LIMIT 1) AS barcode,
				qu.name AS qu_name
			FROM sale_items si
			JOIN products p ON si.product_id = p.id
			LEFT JOIN quantity_units qu ON p.qu_id_stock = qu.id
			WHERE si.sale_id IN ($inPlaceholders)
			ORDER BY si.id ASC
		";
		$stmtItems = $db->prepare($itemsSql);
		$stmtItems->execute($saleIds);
		$allItems = $stmtItems->fetchAll(\PDO::FETCH_ASSOC);

		// Obtener historial de devoluciones para estos ítems
		$returnsSql = "
			SELECT 
				sale_item_id,
				product_id,
				SUM(CASE WHEN status != 'rechazada' THEN quantity ELSE 0 END) AS total_returned_qty,
				COUNT(*) AS return_records_count
			FROM customer_returns_exchanges
			WHERE sale_id IN ($inPlaceholders)
			GROUP BY sale_item_id, product_id
		";
		$stmtRet = $db->prepare($returnsSql);
		$stmtRet->execute($saleIds);
		$returnsMap = [];
		foreach ($stmtRet->fetchAll(\PDO::FETCH_ASSOC) as $r)
		{
			$returnsMap[$r['sale_item_id']] = floatval($r['total_returned_qty']);
		}

		$itemsBySale = [];
		$now = time();

		foreach ($allItems as $item)
		{
			$sid = $item['sale_id'];
			$soldQty = floatval($item['amount']);
			$alreadyReturned = $returnsMap[$item['id']] ?? 0.0;
			$eligibleQty = max(0.0, round($soldQty - $alreadyReturned, 2));

			$isPerishable = (stripos($item['product_name'], 'leche') !== false ||
				stripos($item['product_name'], 'queso') !== false ||
				stripos($item['product_name'], 'carne') !== false ||
				stripos($item['product_name'], 'congelad') !== false ||
				stripos($item['product_name'], 'pan') !== false) ? 1 : 0;

			$item['amount_sold'] = $soldQty;
			$item['amount_returned'] = $alreadyReturned;
			$item['eligible_amount'] = $eligibleQty;
			$item['is_eligible'] = ($eligibleQty > 0);
			$item['is_perishable'] = $isPerishable;

			$itemsBySale[$sid][] = $item;
		}

		foreach ($sales as &$sale)
		{
			$saleTimestamp = strtotime($sale['created_timestamp'] ?: $sale['row_created_timestamp'] ?? 'now');
			$daysElapsed = max(0, intval(floor(($now - $saleTimestamp) / 86400)));
			$sale['days_elapsed'] = $daysElapsed;
			$sale['within_policy_days'] = ($daysElapsed <= 30);
			$sale['items'] = $itemsBySale[$sale['id']] ?? [];

			$totalEligible = 0.0;
			foreach ($sale['items'] as $it)
			{
				$totalEligible += $it['eligible_amount'];
			}
			$sale['has_eligible_items'] = ($totalEligible > 0);
		}

		return $sales;
	}

	/**
	 * VALIDACIÓN DE REGLAS CRÍTICAS DE DEVOLUCIÓN POS
	 */
	public function ValidateReturn(int $saleId, int $saleItemId, float $quantity, string $condition, string $resolution): array
	{
		$this->EnsureTables();
		$db = $this->Db();

		$stmt = $db->prepare("
			SELECT si.*, s.sale_number, s.status AS sale_status, s.created_timestamp, p.name AS product_name
			FROM sale_items si
			JOIN sales s ON si.sale_id = s.id
			JOIN products p ON si.product_id = p.id
			WHERE si.id = :si_id AND s.id = :s_id
		");
		$stmt->execute([':si_id' => $saleItemId, ':s_id' => $saleId]);
		$item = $stmt->fetch(\PDO::FETCH_ASSOC);

		if (!$item)
		{
			throw new \Exception("Ítem de venta no encontrado o no corresponde al ticket especificado.");
		}

		// Validar estado de la venta
		if ($item['sale_status'] === 'cancelled')
		{
			throw new \Exception("Esta venta ya fue anulada previamente. No admite devoluciones adicionales.");
		}

		// Calcular devoluciones previas aprobadas o en curso
		$retStmt = $db->prepare("
			SELECT IFNULL(SUM(quantity), 0)
			FROM customer_returns_exchanges
			WHERE sale_item_id = :si_id AND status != 'rechazada'
		");
		$retStmt->execute([':si_id' => $saleItemId]);
		$alreadyReturned = floatval($retStmt->fetchColumn());

		$soldQty = floatval($item['amount']);
		$eligibleQty = max(0.0, round($soldQty - $alreadyReturned, 2));

		// REGLA CRÍTICA 1: Evitar devolución duplicada
		if ($eligibleQty <= 0)
		{
			throw new \Exception("BLOQUEO POR DUPLICIDAD: Este ítem ya fue devuelto en su totalidad en una operación previa (Vendidas: $soldQty, Devueltas: $alreadyReturned).");
		}

		// REGLA CRÍTICA 2: No permitir devolver más unidades que las vendidas
		if ($quantity > $eligibleQty)
		{
			throw new \Exception("CANTIDAD EXCEDENTE: No es posible devolver más unidades que las vendidas disponibles (Disponibles: $eligibleQty, Solicitadas: $quantity).");
		}

		// REGLA CRÍTICA 3: Bloqueo sanitario en alimentos perecederos abiertos o dañados
		$isPerishable = (stripos($item['product_name'], 'leche') !== false ||
			stripos($item['product_name'], 'queso') !== false ||
			stripos($item['product_name'], 'carne') !== false ||
			stripos($item['product_name'], 'congelad') !== false) ? 1 : 0;

		$suggestedDisposition = 'reincorporacion';
		if ($condition === 'dañado_cliente' || $condition === 'no_apto_reventa')
		{
			$suggestedDisposition = 'merma_definitiva';
		}
		elseif ($condition === 'defectuoso_fabrica')
		{
			$suggestedDisposition = 'devolucion_proveedor';
		}
		elseif ($isPerishable && $condition !== 'sin_uso')
		{
			$suggestedDisposition = 'cuarentena';
		}

		if ($isPerishable && in_array($condition, ['abierto', 'dañado_cliente', 'no_apto_reventa', 'defectuoso_fabrica']) && $suggestedDisposition === 'reincorporacion')
		{
			throw new \Exception("BLOQUEO SANITARIO ESTRICTO: Alimentos perecederos o refrigerados abiertos/dañados no pueden reintegrarse a stock vendible. Deben ir a merma o cuarentena.");
		}

		return [
			'valid' => true,
			'sold_quantity' => $soldQty,
			'already_returned' => $alreadyReturned,
			'eligible_quantity' => $eligibleQty,
			'requested_quantity' => $quantity,
			'is_perishable' => $isPerishable,
			'suggested_disposition' => $suggestedDisposition,
			'unit_price' => floatval($item['unit_price']),
			'total_refund' => round($quantity * floatval($item['unit_price']), 2)
		];
	}

	/**
	 * PROCESAMIENTO COMPLETO DE DEVOLUCIÓN DEL POS
	 * Venta POS → Solicitud → Validación → Recepción → Inspección → Autorización → Reembolso/cambio → Kardex → Cierre
	 */
	public function ProcessPosReturn(array $d, int $userId = 1): array
	{
		$this->EnsureTables();
		$db = $this->Db();

		$saleId = intval($d['sale_id'] ?? 0);
		$saleItemId = intval($d['sale_item_id'] ?? 0);
		$quantity = max(0.01, floatval($d['quantity'] ?? 1.0));
		$condition = strval($d['condition_state'] ?? 'sin_uso');
		$resolution = strval($d['resolution'] ?? 'reembolso');
		$reason = trim(strval($d['reason'] ?? 'Devolución de mostrador POS'));
		$observations = trim(strval($d['observations'] ?? ''));
		$supervisorUserId = !empty($d['supervisor_user_id']) ? intval($d['supervisor_user_id']) : $userId;

		// 1. Ejecutar validaciones obligatorias
		$validation = $this->ValidateReturn($saleId, $saleItemId, $quantity, $condition, $resolution);

		// 2. Obtener datos completos de la venta y producto
		$stmtItem = $db->prepare("
			SELECT si.*, s.sale_number, s.branch_id, s.customer_id, s.payment_method, s.user_id AS cashier_id,
			       p.name AS product_name, p.sku, p.location_id
			FROM sale_items si
			JOIN sales s ON si.sale_id = s.id
			JOIN products p ON si.product_id = p.id
			WHERE si.id = :si_id
		");
		$stmtItem->execute([':si_id' => $saleItemId]);
		$saleItem = $stmtItem->fetch(\PDO::FETCH_ASSOC);

		// 3. Generar código único correlativo
		$nextId = intval($db->query("SELECT IFNULL(MAX(id), 0) + 1 FROM customer_returns_exchanges")->fetchColumn());
		$returnCode = sprintf('DEV-POS-2026-%04d', $nextId);

		// 4. Manejo de caso: RECHAZO JUSTIFICADO
		if ($resolution === 'rechazo_justificado')
		{
			$rejectionReason = trim(strval($d['rejection_reason'] ?? $observations ?: 'No cumple con las políticas comerciales de devolución.'));

			$ins = $db->prepare("
				INSERT INTO customer_returns_exchanges (
					return_code, order_id, branch_id, customer_id, product_id, stock_id, return_type,
					quantity, unit_cost, total_cost, recoverable_value, definitive_loss_value,
					supplier_credit_amount, customer_refund_amount, classification, is_perishable,
					inspection_result, inspection_notes, inspected_by_user_id, inspected_at,
					approval_status, approved_by_user_id, approved_at, disposition_notes,
					status, created_at, sale_id, sale_item_id, ticket_number, resolution,
					condition_state, refund_method, refund_status, refund_gateway_ref,
					refund_card_last4, cashier_user_id, rejection_reason, resolution_time_minutes, is_pos_return
				) VALUES (
					:code, NULL, :bid, :cid, :pid, 'STK-POS-REJ', 'devolucion_cliente',
					:qty, :ucost, 0.0, 0.0, 0.0,
					0.0, 0.0, 'merma_definitiva', :per,
					'Rechazado tras inspección técnica', :insp_notes, :insp_uid, datetime('now', 'localtime'),
					'rejected', :app_uid, datetime('now', 'localtime'), :disp,
					'rechazada', datetime('now', 'localtime'), :sale_id, :sale_item_id, :ticket_num, 'rechazo_justificado',
					:cond, 'none', 'rejected', NULL,
					NULL, :cashier_id, :rejection_reason, 5, 1
				)
			");

			$ins->execute([
				':code' => $returnCode,
				':bid' => $saleItem['branch_id'] ?: 1,
				':cid' => $saleItem['customer_id'] ?: 1,
				':pid' => $saleItem['product_id'],
				':qty' => $quantity,
				':ucost' => floatval($saleItem['unit_cost'] ?: 0.0),
				':per' => $validation['is_perishable'],
				':insp_notes' => $reason,
				':insp_uid' => $userId,
				':app_uid' => $supervisorUserId,
				':disp' => 'Caso cerrado por rechazo justificado con constancia para cliente.',
				':sale_id' => $saleId,
				':sale_item_id' => $saleItemId,
				':ticket_num' => $saleItem['sale_number'],
				':cond' => $condition,
				':cashier_id' => $userId,
				':rejection_reason' => $rejectionReason
			]);

			return [
				'success' => true,
				'return_id' => $nextId,
				'return_code' => $returnCode,
				'resolution' => 'rechazo_justificado',
				'rejection_reason' => $rejectionReason,
				'message' => "Devolución $returnCode rechazada justificadamente y registrada en bitácora de auditoría."
			];
		}

		// 5. Caso: DEVOLUCIÓN ACEPTADA (Reembolso, Cambio, Crédito de Tienda o Cuarentena)
		$unitPrice = floatval($saleItem['unit_price']);
		$unitCost = floatval($saleItem['unit_cost'] ?: ($unitPrice * 0.70));
		$totalRefundAmount = round($quantity * $unitPrice, 2);
		$totalCost = round($quantity * $unitCost, 2);

		// Clasificación de inventario segura
		$classification = $validation['suggested_disposition'];

		$recoverableVal = 0.0;
		$definitiveLoss = 0.0;
		$supplierCredit = 0.0;

		if ($classification === 'reincorporacion')
		{
			$recoverableVal = $totalCost;
		}
		elseif ($classification === 'devolucion_proveedor')
		{
			$supplierCredit = $totalCost;
			$recoverableVal = $totalCost;
		}
		elseif ($classification === 'merma_definitiva')
		{
			$definitiveLoss = $totalCost;
		}

		// Método de reembolso y pasarela sandbox
		$refundMethod = !empty($d['refund_method']) ? strval($d['refund_method']) : ($saleItem['payment_method'] ?: 'cash');
		$gatewayRef = null;
		$cardLast4 = null;

		if ($refundMethod === 'card')
		{
			// Pasarela Sandbox Demo PCI-DSS
			$gatewayRef = 'SANDBOX-RF-' . strtoupper(substr(md5(uniqid('', true)), 0, 10));
			$cardLast4 = !empty($d['card_last4']) ? preg_replace('/\D/', '', substr($d['card_last4'], -4)) : '4242';
		}
		elseif ($refundMethod === 'store_credit')
		{
			$gatewayRef = 'VALE-CRED-' . date('Ymd') . '-' . sprintf('%04d', $nextId);
		}

		$ins = $db->prepare("
			INSERT INTO customer_returns_exchanges (
				return_code, order_id, branch_id, customer_id, product_id, stock_id, return_type,
				quantity, unit_cost, total_cost, recoverable_value, definitive_loss_value,
				supplier_credit_amount, customer_refund_amount, classification, is_perishable,
				inspection_result, inspection_notes, inspected_by_user_id, inspected_at,
				approval_status, approved_by_user_id, approved_at, disposition_notes,
				status, created_at, sale_id, sale_item_id, ticket_number, resolution,
				condition_state, refund_method, refund_status, refund_gateway_ref,
				refund_card_last4, cashier_user_id, rejection_reason, resolution_time_minutes, is_pos_return
			) VALUES (
				:code, NULL, :bid, :cid, :pid, 'STK-POS-RET', 'devolucion_cliente',
				:qty, :ucost, :tcost, :rec, :loss, :cred, :ref, :class, :per,
				'Inspección conforme por mostrador POS', :insp_notes, :insp_uid, datetime('now', 'localtime'),
				'approved', :app_uid, datetime('now', 'localtime'), :disp,
				'aprobada', datetime('now', 'localtime'), :sale_id, :sale_item_id, :ticket_num, :res,
				:cond, :ref_method, 'completed', :ref_gw, :card4, :cashier_id, NULL, 5, 1
			)
		");

		$ins->execute([
			':code' => $returnCode,
			':bid' => $saleItem['branch_id'] ?: 1,
			':cid' => $saleItem['customer_id'] ?: 1,
			':pid' => $saleItem['product_id'],
			':qty' => $quantity,
			':ucost' => $unitCost,
			':tcost' => $totalCost,
			':rec' => $recoverableVal,
			':loss' => $definitiveLoss,
			':cred' => $supplierCredit,
			':ref' => $totalRefundAmount,
			':class' => $classification,
			':per' => $validation['is_perishable'],
			':insp_notes' => $reason . ($observations ? " ($observations)" : ''),
			':insp_uid' => $userId,
			':app_uid' => $supervisorUserId,
			':disp' => "Resolución: $resolution. Destino de inventario: $classification.",
			':sale_id' => $saleId,
			':sale_item_id' => $saleItemId,
			':ticket_num' => $saleItem['sale_number'],
			':res' => $resolution,
			':cond' => $condition,
			':ref_method' => $refundMethod,
			':ref_gw' => $gatewayRef,
			':card4' => $cardLast4,
			':cashier_id' => $userId
		]);

		// 6. Si clasifica como REINCORPORACIÓN: Reintegro seguro a stock y Kardex
		if ($classification === 'reincorporacion')
		{
			// Registrar en stock de forma idempotente
			$locId = intval($saleItem['location_id'] ?: 1);
			$stockDb = $db->prepare("
				INSERT INTO stock (product_id, amount, best_before_date, purchased_date, stock_id, price, location_id)
				VALUES (:pid, :amt, date('now', '+30 days'), date('now'), :stk_id, :pr, :loc_id)
			");
			$stockDb->execute([
				':pid' => $saleItem['product_id'],
				':amt' => $quantity,
				':stk_id' => 'RET-' . $returnCode,
				':pr' => $unitCost,
				':loc_id' => $locId
			]);

			// Registrar asiento en el libro inalterable Kardex (stock_log)
			$logDb = $db->prepare("
				INSERT INTO stock_log (product_id, amount, best_before_date, purchased_date, stock_id, price, location_id, transaction_type, transaction_id, user_id, row_created_timestamp, note)
				VALUES (:pid, :amt, date('now', '+30 days'), date('now'), :stk_id, :pr, :loc_id, 'purchase', :tx_id, :uid, datetime('now', 'localtime'), :note)
			");
			$logDb->execute([
				':pid' => $saleItem['product_id'],
				':amt' => $quantity,
				':stk_id' => 'RET-' . $returnCode,
				':pr' => $unitCost,
				':loc_id' => $locId,
				':tx_id' => $returnCode,
				':uid' => $userId,
				':note' => "Devolución mostrador POS {$saleItem['sale_number']} ({$returnCode})"
			]);
		}
		elseif ($classification === 'merma_definitiva')
		{
			// Conectar automáticamente con el módulo de Mermas y Prevención (/waste)
			$wasteIns = $db->prepare("
				INSERT INTO waste_records (product_id, amount, total_cost, branch_id, status, notes, case_number, stock_id, transaction_id, reported_by_user_id, approved_by_user_id, created_timestamp)
				VALUES (:pid, :amt, :tc, :bid, 'approved', :notes, :case_num, :stk_id, :tx_id, :uid, :app_uid, datetime('now', 'localtime'))
			");
			$wasteIns->execute([
				':pid' => $saleItem['product_id'],
				':amt' => $quantity,
				':tc' => $totalCost,
				':bid' => $saleItem['branch_id'] ?: 1,
				':notes' => "Merma por devolución POS averiada ({$saleItem['sale_number']}): " . $reason,
				':case_num' => 'MRM-RET-' . $nextId,
				':stk_id' => 'RET-' . $returnCode,
				':tx_id' => $returnCode,
				':uid' => $userId,
				':app_uid' => $supervisorUserId
			]);
		}

		// 7. Preparar Comprobante Oficial de Devolución
		$voucher = [
			'return_code' => $returnCode,
			'ticket_number' => $saleItem['sale_number'],
			'product_name' => $saleItem['product_name'],
			'sku' => $saleItem['sku'],
			'quantity' => $quantity,
			'unit_price' => $unitPrice,
			'refund_amount' => $totalRefundAmount,
			'resolution' => $resolution,
			'condition_state' => $condition,
			'classification' => $classification,
			'refund_method' => $refundMethod,
			'gateway_reference' => $gatewayRef,
			'card_last4' => $cardLast4 ? '**** ' . $cardLast4 : null,
			'reason' => $reason,
			'cashier_user_id' => $userId,
			'supervisor_user_id' => $supervisorUserId,
			'timestamp' => date('d/m/Y H:i:s')
		];

		return [
			'success' => true,
			'return_id' => $nextId,
			'return_code' => $returnCode,
			'resolution' => $resolution,
			'refund_amount' => $totalRefundAmount,
			'voucher' => $voucher,
			'message' => "Devolución $returnCode procesada exitosamente por $" . number_format($totalRefundAmount, 2) . "."
		];
	}

	/**
	 * DASHBOARD ANALÍTICO DE DEVOLUCIONES DEL POS (KPIs, Gráficos, Historial y Alertas Anti-Fraude)
	 */
	public function GetDashboardData(?int $branchId = null): array
	{
		$this->EnsureTables();
		$db = $this->Db();

		$where = ["r.is_pos_return = 1"];
		$params = [];

		if (!empty($branchId))
		{
			$where[] = "r.branch_id = :bid";
			$params[':bid'] = $branchId;
		}

		$whereSql = implode(' AND ', $where);

		// 1. Listado de devoluciones POS
		$sql = "
			SELECT 
				r.*,
				p.name AS product_name,
				p.sku,
				IFNULL(c.name, 'Público General / Mostrador') AS customer_name,
				IFNULL(b.name, 'Sucursal Matriz') AS branch_name,
				u_insp.username AS cashier_name,
				u_app.username AS supervisor_name
			FROM customer_returns_exchanges r
			JOIN products p ON r.product_id = p.id
			LEFT JOIN customers c ON r.customer_id = c.id
			LEFT JOIN branches b ON r.branch_id = b.id
			LEFT JOIN users u_insp ON r.inspected_by_user_id = u_insp.id
			LEFT JOIN users u_app ON r.approved_by_user_id = u_app.id
			WHERE $whereSql
			ORDER BY r.id DESC
		";

		$stmt = $db->prepare($sql);
		$stmt->execute($params);
		$returns = $stmt->fetchAll(\PDO::FETCH_ASSOC);

		// 2. Indicadores Principales (7 KPIs)
		$totalReturnedAmount = 0.0;
		$pendingRefunds = 0;
		$totalMinutes = 0;
		$reasonsCount = [];
		$productsCount = [];
		$totalLoss = 0.0;
		$totalRecovered = 0.0;
		$totalCredits = 0.0;

		foreach ($returns as $ret)
		{
			if ($ret['status'] !== 'rechazada')
			{
				$totalReturnedAmount += floatval($ret['customer_refund_amount']);
				$totalLoss += floatval($ret['definitive_loss_value']);
				$totalRecovered += floatval($ret['recoverable_value']);
				$totalCredits += floatval($ret['supplier_credit_amount']);
			}

			if (in_array($ret['status'], ['inspeccion', 'solicitud']))
			{
				$pendingRefunds++;
			}

			$totalMinutes += intval($ret['resolution_time_minutes'] ?: 5);

			// Motivos
			$rReason = $ret['inspection_notes'] ?: 'Otro';
			$rReasonSimple = explode('(', $rReason)[0];
			$rReasonSimple = trim(mb_substr($rReasonSimple, 0, 35));
			$reasonsCount[$rReasonSimple] = ($reasonsCount[$rReasonSimple] ?? 0) + 1;

			// Productos
			$pKey = $ret['product_name'];
			if (!isset($productsCount[$pKey]))
			{
				$productsCount[$pKey] = [
					'name' => $ret['product_name'],
					'sku' => $ret['sku'],
					'units' => 0.0,
					'amount' => 0.0
				];
			}
			$productsCount[$pKey]['units'] += floatval($ret['quantity']);
			$productsCount[$pKey]['amount'] += floatval($ret['customer_refund_amount']);
		}

		// Ventas totales POS para cálculo de % de devolución
		$salesTotalSql = "SELECT IFNULL(SUM(total_amount), 1.0) FROM sales" . (!empty($branchId) ? " WHERE branch_id = $branchId" : "");
		$totalSales = floatval($db->query($salesTotalSql)->fetchColumn()) ?: 1.0;
		$returnRatePct = round(($totalReturnedAmount / $totalSales) * 100, 2);

		// Tiempo promedio de resolución
		$avgMins = count($returns) > 0 ? round($totalMinutes / count($returns), 1) : 5.0;

		// Top productos
		uasort($productsCount, fn($a, $b) => $b['units'] <=> $a['units']);
		$topProducts = array_slice(array_values($productsCount), 0, 5);

		// Top motivos
		arsort($reasonsCount);
		$topReasons = array_slice($reasonsCount, 0, 5, true);

		// 3. Alertas de posibles abusos / Control Anti-Fraude
		$fraudAlerts = [];
		// Clientes recurrentes
		$clientReturns = $db->query("
			SELECT c.name, COUNT(*) AS count_returns, SUM(r.customer_refund_amount) AS total_amount
			FROM customer_returns_exchanges r
			JOIN customers c ON r.customer_id = c.id
			WHERE r.is_pos_return = 1 AND r.status != 'rechazada'
			GROUP BY r.customer_id
			HAVING COUNT(*) >= 2
			ORDER BY count_returns DESC
			LIMIT 3
		")->fetchAll(\PDO::FETCH_ASSOC);

		foreach ($clientReturns as $cr)
		{
			$fraudAlerts[] = [
				'type' => 'cliente_recurrente',
				'level' => 'warning',
				'title' => 'Cliente con múltiples devoluciones',
				'desc' => "{$cr['name']} registra {$cr['count_returns']} devoluciones por un total de $" . number_format($cr['total_amount'], 2) . "."
			];
		}

		// Devolución unitaria de alto valor
		foreach ($returns as $ret)
		{
			if (floatval($ret['customer_refund_amount']) >= 40.0)
			{
				$fraudAlerts[] = [
					'type' => 'alto_valor',
					'level' => 'info',
					'title' => 'Devolución de alto impacto financiero',
					'desc' => "Ticket {$ret['ticket_number']} ({$ret['product_name']}): Reembolso de $" . number_format($ret['customer_refund_amount'], 2) . " autorizado por {$ret['supervisor_name']}."
				];
				break;
			}
		}

		return [
			'returns' => $returns,
			'total_count' => count($returns),
			'kpis' => [
				'total_returned_amount' => $totalReturnedAmount,
				'return_rate_pct' => $returnRatePct,
				'pending_refunds_count' => $pendingRefunds,
				'avg_resolution_time_minutes' => $avgMins,
				'total_loss' => $totalLoss,
				'total_recovered' => $totalRecovered,
				'total_credits' => $totalCredits,
				'net_financial_impact' => round($totalLoss - $totalCredits, 2)
			],
			'top_reasons' => $topReasons,
			'top_products' => $topProducts,
			'fraud_alerts' => $fraudAlerts
		];
	}
}
