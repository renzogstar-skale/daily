<?php

namespace Grocy\Services;

/**
 * Caso demo de principio a fin (solo modo demo): Yogur natural 500 g, SKU DAI-YOG-500, lote LAC-001, Hallandale Beach.
 * Cada paso usa el servicio real del módulo (MRP, Stock, POS, Pedidos, Alertas, Tareas, Calidad, Mermas), así que todos los
 * tableros leen el caso de la misma base de datos. Los pasos son idempotentes (repetir no duplica movimientos ni importes)
 * y el caso se puede restablecer: se borran las filas que crearon sus pasos y se vuelve a sellar el libro de movimientos.
 */
class DemoRetailCaseService extends BaseService
{
	const CODE = 'CASO-YOG-500';
	const PRODUCT_ID = 9900;
	const SKU = 'DAI-YOG-500';
	const LOT = 'LAC-001';
	const NAME = 'Yogur natural 500 g';
	const DEPARTMENT = 'Lácteos y huevos';
	const BRANCH = 1;          // Hallandale Beach
	const LOC_STORE = 7;       // Cámara Fría de Lácteos (bodega)
	const LOC_SHELF = 8;       // Góndola Refrigerada Dairy
	const COST = 2.00;
	const PRICE = 3.50;
	const ORDERED = 60;
	const RECEIVED = 48;
	const MOVED = 36;
	const POS_TICKETS = [10, 9, 7]; // 26 u. en caja = $91.00
	const ORDER_QTY = 4;            // pedido entregado = $14.00
	const AT_RISK = 6;              // baja por vencimiento = $12.00
	const OPEX = 20.00;             // gastos operativos asignados al caso
	const CUSTOMER_ID = 8;          // Cafetería cliente 01-A
	const APPROVER_USER_ID = 1;     // gerente que aprueba (distinto de quien reporta)
	const WASTE_REASON_ID = 23;     // D03 Vencimiento de lácteos por rotación deficiente (exige aprobación y foto)
	const EVIDENCE = 'waste_caso_yog_500_lac001.png';

	// Pasos en orden; «pre_close» = estado previo al cierre que muestra HOY
	const STEPS = [
		'po' => ['Compras emite la orden por 60 u.', 'Planeación y Compras'],
		'receive' => ['Recepción escaneada: 48 u. del lote LAC-001 en bodega (12 pendientes)', 'Planeación y Compras'],
		'move' => ['Traslado interno: 36 u. de bodega a góndola', 'Inventario y WMS'],
		'pos' => ['POS: 26 u. vendidas en caja ($91.00)', 'Ventas y POS'],
		'reserve' => ['Pedido de 4 u. confirmado: reserva el disponible', 'Despachos'],
		'pick' => ['Picking del pedido desde góndola', 'Despachos'],
		'deliver' => ['Despacho y entrega del pedido ($14.00, venta reconocida al despachar)', 'Despachos'],
		'fefo' => ['Calidad detecta 6 u. en riesgo: alerta y tarea FEFO al turno', 'Calidad y Trazabilidad'],
		'block' => ['Turno confirma el retiro: 6 u. bloqueadas', 'Operaciones y Personal'],
		'waste' => ['Mermas: baja por vencimiento reportada con evidencia (pendiente de aprobación)', 'Mermas'],
		'approve' => ['Gerente aprueba la baja: una sola salida de 6 u. ($12.00)', 'Mermas'],
		'opex' => ['Finanzas registra $20.00 de gastos operativos del caso', 'Finanzas'],
	];
	const PRE_CLOSE = 'waste';

	// Tablas cuyo vínculo con el caso es el producto (se borran por producto al restablecer)
	const PRODUCT_TABLES = ['stock', 'stock_log', 'dairy_stock_lots', 'quarantine_items', 'waste_records', 'sale_items', 'customer_order_items', 'customer_order_picks',
		'procurement_order_items', 'procurement_receipt_items', 'cache__products_average_price', 'cache__products_last_purchased'];

	private $pdo;

	private function Db(): \PDO
	{
		return $this->pdo ??= DatabaseService::GetInstance()->GetDbConnectionRaw();
	}

	public static function Enabled(): bool
	{
		return defined('GROCY_MODE') && GROCY_MODE === 'demo';
	}

	private function EnsureTable(): void
	{
		$this->Db()->exec("CREATE TABLE IF NOT EXISTS daily_demo_case (code TEXT PRIMARY KEY, steps TEXT NOT NULL DEFAULT '{}', refs TEXT NOT NULL DEFAULT '{}', rows_created TEXT NOT NULL DEFAULT '{}',
			started_at DATETIME, updated_at DATETIME)");
	}

	/** Estado guardado: pasos hechos (con hora), referencias a los registros y filas creadas (para restablecer) */
	public function State(): array
	{
		$this->EnsureTable();
		$r = $this->Db()->query("SELECT * FROM daily_demo_case WHERE code = '" . self::CODE . "'")->fetch(\PDO::FETCH_ASSOC);
		return [
			'steps' => $r ? json_decode($r['steps'], true) ?: [] : [],
			'refs' => $r ? json_decode($r['refs'], true) ?: [] : [],
			'rows' => $r ? json_decode($r['rows_created'], true) ?: [] : [],
			'started_at' => $r['started_at'] ?? null,
			'updated_at' => $r['updated_at'] ?? null,
		];
	}

	private function Save(array $s): void
	{
		$this->Db()->prepare('INSERT OR REPLACE INTO daily_demo_case (code, steps, refs, rows_created, started_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)')
			->execute([self::CODE, json_encode($s['steps']), json_encode($s['refs']), json_encode($s['rows']), $s['started_at'] ?: date('Y-m-d H:i:s'), date('Y-m-d H:i:s')]);
	}

	/** Maestros del caso (no se borran al restablecer: Maestros conserva los mismos códigos en todas las vistas) */
	public function EnsureMaster(): void
	{
		$db = $this->Db();
		$exists = $db->query('SELECT id FROM products WHERE id = ' . self::PRODUCT_ID)->fetchColumn();
		if (!$exists)
		{
			$ref = $db->query('SELECT product_group_id, shopping_location_id, qu_id_purchase, qu_id_stock FROM products WHERE id = 37')->fetch(\PDO::FETCH_ASSOC) ?: ['product_group_id' => null, 'shopping_location_id' => 1, 'qu_id_purchase' => 2, 'qu_id_stock' => 2];
			$db->prepare("INSERT INTO products (id, name, description, product_group_id, active, location_id, shopping_location_id, qu_id_purchase, qu_id_stock, qu_id_consume, qu_id_price,
				min_stock_amount, default_best_before_days, sku, brand, sale_price, department, should_not_be_frozen)
				VALUES (?, ?, ?, ?, 1, ?, ?, ?, ?, ?, ?, 0, 5, ?, 'DAILY', ?, ?, 1)")
				->execute([self::PRODUCT_ID, self::NAME, 'Caso demo de principio a fin (Compras → Finanzas). Lote ' . self::LOT . ', Hallandale Beach.', $ref['product_group_id'], self::LOC_STORE,
					$ref['shopping_location_id'] ?: 1, $ref['qu_id_purchase'], $ref['qu_id_stock'], $ref['qu_id_stock'], $ref['qu_id_stock'], self::SKU, self::PRICE, self::DEPARTMENT]);
		}
		if (!$db->query('SELECT 1 FROM product_barcodes WHERE product_id = ' . self::PRODUCT_ID)->fetchColumn())
		{
			$db->prepare('INSERT INTO product_barcodes (product_id, barcode, note) VALUES (?, ?, ?)')->execute([self::PRODUCT_ID, DailyCompanySeedService::Ean13(self::PRODUCT_ID), 'EAN-13 del caso demo ' . self::SKU]);
		}
		// Foto de evidencia de la merma (imagen generada, sin datos personales)
		$dir = GROCY_DATAPATH . '/storage/waste';
		$file = $dir . '/' . self::EVIDENCE;
		if (!is_file($file))
		{
			@mkdir($dir, 0777, true);
			if (function_exists('imagecreatetruecolor'))
			{
				$im = imagecreatetruecolor(480, 320);
				imagefill($im, 0, 0, imagecolorallocate($im, 236, 246, 240));
				$ink = imagecolorallocate($im, 0, 102, 51);
				imagerectangle($im, 10, 10, 469, 309, $ink);
				foreach (['DAILY - Evidencia de merma', self::SKU . ' / Lote ' . self::LOT, '6 u. retiradas de gondola', 'Causal: vencimiento (FEFO)', 'Hallandale Beach'] as $i => $line)
				{
					imagestring($im, 5, 30, 40 + $i * 46, $line, $ink);
				}
				imagepng($im, $file);
			}
		}
	}

	/* ---------------------------------------------------------------- Ejecución de pasos */

	public function NextStep(): ?string
	{
		$done = $this->State()['steps'];
		foreach (array_keys(self::STEPS) as $k)
		{
			if (!isset($done[$k]))
			{
				return $k;
			}
		}
		return null;
	}

	/** Ejecuta pasos pendientes hasta $target (incluido): 'next', un código de paso, 'pre_close' o 'all' */
	public function Run(string $target = 'next', int $userId = 1): array
	{
		if (!self::Enabled())
		{
			throw new \Exception('El caso demo solo existe en modo demo');
		}
		$keys = array_keys(self::STEPS);
		$stop = match ($target)
		{
			'all' => end($keys),
			'pre_close' => self::PRE_CLOSE,
			'next' => $this->NextStep(),
			default => isset(self::STEPS[$target]) ? $target : throw new \Exception('Paso desconocido'),
		};
		$ran = [];
		if ($stop === null)
		{
			return ['ran' => [], 'message' => 'El caso ya está completo'];
		}
		$this->Lock();
		try
		{
			$this->EnsureMaster();
			foreach ($keys as $k)
			{
				$s = $this->State();
				if (!isset($s['steps'][$k]))
				{
					$this->Sync();
					$s = $this->State();
				}
				if (!isset($s['steps'][$k]))
				{
					$mark = $this->Mark();
					$s['refs'] = $this->{'Step' . ucfirst($k)}($s['refs'], $userId);
					$s['steps'][$k] = date('Y-m-d H:i:s');
					$s['rows'] = $this->Collect($s['rows'], $mark);
					$this->Save($s);
					$ran[] = $k;
				}
				if ($k === $stop)
				{
					break;
				}
			}
			$this->Sync();
		}
		finally
		{
			$this->Unlock();
		}
		return ['ran' => $ran, 'next' => $this->NextStep()];
	}

	/**
	 * Efectos derivados cuando el paso se hizo desde la pantalla del módulo (no desde el caso): la tarea completada en el móvil
	 * bloquea las 6 u.; la merma aprobada en Mermas cierra el bloqueo con el vínculo a la merma. Idempotente.
	 */
	public function Sync(): void
	{
		$s = $this->State();
		$r = $s['refs'];
		$db = $this->Db();
		$changed = false;
		if (!empty($r['task_id']) && !isset($s['steps']['block']) && $db->query('SELECT status FROM workforce_store_tasks WHERE id = ' . intval($r['task_id']))->fetchColumn() === 'completed')
		{
			$mark = $this->Mark();
			$r = $this->Quarantine($r);
			$s['steps']['block'] = date('Y-m-d H:i:s');
			$s['rows'] = $this->Collect($s['rows'], $mark);
			$changed = true;
		}
		if (!empty($r['waste_id']) && !isset($s['steps']['approve']))
		{
			$w = $db->query('SELECT status, transaction_id FROM waste_records WHERE id = ' . intval($r['waste_id']))->fetch(\PDO::FETCH_ASSOC);
			if (($w['status'] ?? '') === 'approved')
			{
				$r['approve_tx'] = $w['transaction_id'];
				$this->CloseQuarantine($r);
				$s['steps']['approve'] = date('Y-m-d H:i:s');
				$changed = true;
			}
		}
		if ($changed)
		{
			$s['refs'] = $r;
			$this->Save($s);
		}
	}

	private function StepPo(array $r, int $userId): array
	{
		$mrp = MrpService::GetInstance();
		$supplier = intval($this->Db()->query('SELECT shopping_location_id FROM products WHERE id = ' . self::PRODUCT_ID)->fetchColumn()) ?: 1;
		$po = $mrp->CreateProcurementOrder(['branch_id' => self::BRANCH, 'supplier_id' => $supplier, 'idempotency_key' => self::CODE . '-po',
			'items' => [['product_id' => self::PRODUCT_ID, 'amount' => self::ORDERED, 'unit_price' => self::COST]]], $userId);
		$st = $this->Db()->query('SELECT status FROM procurement_orders WHERE id = ' . intval($po['id']))->fetchColumn();
		if (in_array($st, ['draft', 'pending_approval', 'suggested'], true))
		{
			$mrp->ApproveProcurementOrder($po['id'], $userId);
		}
		if (!in_array($this->Db()->query('SELECT status FROM procurement_orders WHERE id = ' . intval($po['id']))->fetchColumn(), ['sent', 'confirmed', 'partially_received', 'received'], true))
		{
			$mrp->SendProcurementOrder($po['id'], $userId, date('Y-m-d'));
		}
		$r['po_id'] = intval($po['id']);
		$r['po_code'] = $po['order_code'];
		return $r;
	}

	private function StepReceive(array $r, int $userId): array
	{
		$mrp = MrpService::GetInstance();
		$item = null;
		foreach ($mrp->GetProcurementOrderDetail($r['po_id'])['items'] as $it)
		{
			if (intval($it['product_id']) === self::PRODUCT_ID)
			{
				$item = $it;
			}
		}
		$rec = $mrp->ReceiveProcurementOrder($r['po_id'], ['items' => [['order_item_id' => $item['id'], 'delivered_amount' => self::RECEIVED, 'rejected_amount' => 0,
			'best_before_date' => date('Y-m-d', strtotime('+5 days')), 'location_id' => self::LOC_STORE, 'lot_number' => self::LOT]],
			'temperature_on_arrival' => 3.2, 'supplier_document' => 'FAC-' . self::CODE, 'notes' => 'Recepción parcial escaneada: 48 de 60 u.'], $userId);
		$r['receipt_id'] = intval($rec['id'] ?? $rec['receipt_id'] ?? 0);
		$r['receipt_code'] = $rec['receipt_code'] ?? $this->Db()->query('SELECT receipt_code FROM procurement_receipts WHERE id = ' . $r['receipt_id'])->fetchColumn();
		$r['stock_id'] = $this->Db()->query('SELECT stock_id FROM stock WHERE product_id = ' . self::PRODUCT_ID . ' ORDER BY id LIMIT 1')->fetchColumn();
		// Alerta de la recepción parcial: sigue abierta hasta que llegue el saldo
		$a = AlertCenterService::GetInstance()->Create(['title' => 'Recepción parcial ' . $r['po_code'] . ': 12 u. de ' . self::SKU . ' pendientes', 'category' => 'purchasing', 'alert_type' => 'purchasing',
			'severity' => 'warning', 'branch_id' => self::BRANCH, 'product_id' => self::PRODUCT_ID, 'department' => self::DEPARTMENT, 'value' => 24, 'impact_bucket' => 'sales_risk', 'due_hours' => 24,
			'detail' => 'Se recibieron 48 de 60 u. (' . $r['receipt_code'] . '). El saldo de 12 u. sigue en la orden y no forma parte del stock.', 'owner_user_id' => $userId, 'client_token' => 'caso-yog-500-recepcion'], $userId);
		$r['alert_receipt_id'] = intval($a['id']);
		$this->Db()->prepare('UPDATE dairy_alerts SET href = ? WHERE id = ?')->execute(['/process-flow?type=procurement_order&id=' . $r['po_id'], $r['alert_receipt_id']]);
		return $r;
	}

	private function StepMove(array $r, int $userId): array
	{
		$tx = null;
		StockService::GetInstance()->TransferProduct(self::PRODUCT_ID, self::MOVED, self::LOC_STORE, self::LOC_SHELF, $r['stock_id'], $tx);
		$r['move_tx'] = $tx;
		return $r;
	}

	private function StepPos(array $r, int $userId): array
	{
		$r['sale_ids'] = [];
		foreach (self::POS_TICKETS as $i => $qty)
		{
			$sale = SalesService::GetInstance()->CreateSale(['branch_id' => self::BRANCH, 'customer_id' => 1, 'payment_method' => $i === 1 ? 'card' : 'cash', 'notes' => 'Caso demo ' . self::SKU,
				'items' => [['product_id' => self::PRODUCT_ID, 'amount' => $qty, 'unit_price' => self::PRICE, 'location_id' => self::LOC_SHELF]],
				'client_ref' => 'caso-yog-500-pos-' . ($i + 1)], $userId);
			$r['sale_ids'][] = intval(is_object($sale) ? $sale->id : $sale['id']);
		}
		return $r;
	}

	private function StepReserve(array $r, int $userId): array
	{
		$o = CustomerOrderService::GetInstance()->Create(['branch_id' => self::BRANCH, 'customer_id' => self::CUSTOMER_ID, 'delivery_type' => 'delivery', 'confirm' => true,
			'requested_date' => date('Y-m-d'), 'notes' => 'Caso demo ' . self::SKU . ': pedido de 4 u.',
			'items' => [['product_id' => self::PRODUCT_ID, 'amount' => self::ORDER_QTY, 'unit_price' => self::PRICE]]], $userId);
		$r['order_id'] = intval($o['order']['id'] ?? $o['id']);
		$r['order_code'] = $this->Db()->query('SELECT order_code FROM customer_orders WHERE id = ' . $r['order_id'])->fetchColumn();
		return $r;
	}

	private function StepPick(array $r, int $userId): array
	{
		$svc = CustomerOrderService::GetInstance();
		$svc->MarkPicking($r['order_id']);
		$svc->RegisterPicks($r['order_id'], ['id' => null, 'location_id' => self::LOC_SHELF], [['product_id' => self::PRODUCT_ID, 'amount' => self::ORDER_QTY, 'lot_number' => null, 'serial_number' => null]], $userId);
		return $r;
	}

	private function StepDeliver(array $r, int $userId): array
	{
		$svc = CustomerOrderService::GetInstance();
		$svc->Pack($r['order_id'], 1, $userId);
		$svc->Dispatch($r['order_id'], $userId);
		RetailPreventionService::GetInstance()->RecordDispatchDelivery($r['order_id'], ['delivery_status' => 'delivered', 'delivery_signature_name' => 'Cafetería cliente 01-A (recibe)',
			'delivery_evidence_url' => 'POD-' . self::CODE, 'cold_chain_temp_ok' => 1, 'carrier_name' => 'Flota DAILY'], $userId);
		$r['order_sale_id'] = intval($this->Db()->query('SELECT sale_id FROM customer_orders WHERE id = ' . $r['order_id'])->fetchColumn());
		return $r;
	}

	/** Empleado en turno ahora en la sucursal (área de piso que atiende la góndola de Lácteos) */
	private function ShiftEmployee(): array
	{
		$now = date('H:i');
		$rows = $this->Db()->query("SELECT e.id, e.user_id, e.name, s.id AS shift_id, s.department, s.start_time, s.end_time FROM employee_shifts s JOIN employees e ON e.id = s.employee_id
			WHERE s.branch_id = " . self::BRANCH . " AND s.shift_date = '" . date('Y-m-d') . "' AND s.status = 'published' AND e.active = 1 AND e.user_id IS NOT NULL
			ORDER BY CASE WHEN s.department LIKE 'L%cteo%' THEN 0 WHEN s.department LIKE 'Ventas%' THEN 1 ELSE 2 END, s.start_time")->fetchAll(\PDO::FETCH_ASSOC);
		foreach ($rows as $e)
		{
			if ($e['start_time'] <= $now && ($e['end_time'] > $now || $e['end_time'] < $e['start_time']))
			{
				return $e;
			}
		}
		return $rows[0] ?? ['id' => null, 'user_id' => 2, 'name' => 'Encargado de turno', 'shift_id' => null, 'department' => null];
	}

	private function StepFefo(array $r, int $userId): array
	{
		$emp = $this->ShiftEmployee();
		$a = AlertCenterService::GetInstance()->Create(['title' => 'FEFO: 6 u. de ' . self::SKU . ' lote ' . self::LOT . ' en riesgo de vencimiento en góndola', 'category' => 'quality', 'alert_type' => 'expiry',
			'severity' => 'warning', 'branch_id' => self::BRANCH, 'product_id' => self::PRODUCT_ID, 'department' => self::DEPARTMENT, 'value' => 12, 'impact_bucket' => 'waste', 'due_hours' => 4,
			'detail' => 'Góndola Refrigerada Dairy: 6 u. del lote ' . self::LOT . ' sin rotar y próximas a vencer. Retirarlas, bloquearlas y solicitar su baja.', 'client_token' => 'caso-yog-500-fefo'], $userId);
		$r['alert_fefo_id'] = intval($a['id']);
		$this->Db()->prepare('UPDATE dairy_alerts SET href = ? WHERE id = ?')->execute(['/traceability?q=' . self::LOT, $r['alert_fefo_id']]);
		$t = MobileWorkService::GetInstance()->TaskFromAlert($r['alert_fefo_id'], intval($emp['user_id']), date('Y-m-d H:i:s', strtotime('+4 hours')), $userId);
		$r['task_id'] = intval($t['id']);
		$this->Db()->prepare("UPDATE workforce_store_tasks SET task_type = 'fefo', assigned_employee_id = ?, shift_id = ?, title = ?, description = ? WHERE id = ?")
			->execute([$emp['id'], $emp['shift_id'], 'FEFO ' . self::LOT . ': retirar 6 u. de ' . self::NAME . ' de góndola', 'Turno ' . ($emp['department'] ?: 'de piso') . ' · Góndola Refrigerada Dairy → bloquear en bodega y fotografiar. Origen: alerta #' . $r['alert_fefo_id'], $r['task_id']]);
		$r['task_user_id'] = intval($emp['user_id']);
		$r['task_employee'] = $emp['name'];
		return $r;
	}

	private function StepBlock(array $r, int $userId): array
	{
		$st = $this->Db()->query('SELECT status FROM workforce_store_tasks WHERE id = ' . intval($r['task_id']))->fetchColumn();
		if ($st === 'pending')
		{
			MobileWorkService::GetInstance()->CompleteTask($r['task_id'], 'Retiré 6 u. del lote ' . self::LOT . ' de góndola y las dejé bloqueadas', self::EVIDENCE, $r['task_user_id'] ?: $userId);
		}
		return $this->Quarantine($r);
	}

	private function Quarantine(array $r): array
	{
		if (empty($r['quarantine_id']))
		{
			$r['quarantine_id'] = QualityExpiryService::GetInstance()->MoveToQuarantine(['product_id' => self::PRODUCT_ID, 'stock_id' => $r['stock_id'], 'location_id' => self::LOC_SHELF, 'branch_id' => self::BRANCH,
				'amount' => self::AT_RISK, 'reason' => 'Tarea FEFO TAR-' . ($r['task_id'] ?? '') . ': 6 u. del lote ' . self::LOT . ' en riesgo de vencimiento']);
		}
		return $r;
	}

	private function StepWaste(array $r, int $userId): array
	{
		$reporter = intval($r['task_user_id'] ?? 0) ?: 2;
		$w = WasteService::GetInstance()->ReportWaste(self::PRODUCT_ID, self::AT_RISK, self::WASTE_REASON_ID, self::BRANCH, self::LOC_SHELF, $r['stock_id'],
			'Caso demo: baja por vencimiento de las 6 u. bloqueadas (tarea TAR-' . $r['task_id'] . ')', self::EVIDENCE, null, self::LOT, null, null, null, self::CODE . '-waste', $reporter, false, true);
		$r['waste_id'] = intval($w->id);
		$r['waste_case'] = $w->case_number;
		return $r;
	}

	private function StepApprove(array $r, int $userId): array
	{
		$w = WasteService::GetInstance()->ApproveWaste($r['waste_id'], self::APPROVER_USER_ID);
		$r['approve_tx'] = $w->transaction_id;
		$this->CloseQuarantine($r);
		return $r;
	}

	/** El bloqueo termina con la baja ya descontada por Mermas: se cierra con el vínculo, sin otro movimiento de stock */
	private function CloseQuarantine(array $r): void
	{
		if (!empty($r['quarantine_id']))
		{
			$this->Db()->prepare("UPDATE quarantine_items SET status = 'discarded_as_waste', resolved_timestamp = datetime('now', 'localtime'), resolved_by = 'Mermas', waste_record_id = ?,
				resolution_notes = ? WHERE id = ? AND status = 'in_quarantine'")->execute([$r['waste_id'], 'Baja aprobada ' . ($r['waste_case'] ?? ''), $r['quarantine_id']]);
		}
		foreach (['alert_fefo_id'] as $k)
		{
			if (!empty($r[$k]) && in_array($this->Db()->query('SELECT status FROM dairy_alerts WHERE id = ' . intval($r[$k]))->fetchColumn(), AlertCenterService::WORKING, true))
			{
				AlertCenterService::GetInstance()->Resolve(intval($r[$k]), 'Merma ' . ($r['waste_case'] ?? '') . ' aprobada: 6 u. dadas de baja', self::APPROVER_USER_ID);
			}
		}
	}

	private function StepOpex(array $r, int $userId): array
	{
		$r['expense'] = ['code' => 'GOP-' . self::CODE, 'amount' => self::OPEX, 'concept' => 'Energía del exhibidor y mano de obra de reposición asignadas al caso', 'branch_id' => self::BRANCH,
			'department' => self::DEPARTMENT, 'date' => date('Y-m-d'), 'user_id' => $userId];
		return $r;
	}

	/* ---------------------------------------------------------------- Restablecer */

	public function Reset(): array
	{
		if (!self::Enabled())
		{
			throw new \Exception('El caso demo solo existe en modo demo');
		}
		$db = $this->Db();
		$s = $this->State();
		$this->Lock();
		$triggers = $db->query("SELECT name, sql FROM sqlite_master WHERE type = 'trigger' AND name IN ('dairy_movement_ledger_no_delete', 'dairy_movement_ledger_no_update')")->fetchAll(\PDO::FETCH_KEY_PAIR);
		$deleted = 0;
		try
		{
			$db->beginTransaction();
			$minLog = intval($db->query('SELECT IFNULL(MIN(stock_log_id), 0) FROM dairy_movement_ledger WHERE product_id = ' . self::PRODUCT_ID)->fetchColumn());
			foreach ($triggers as $name => $sql)
			{
				$db->exec("DROP TRIGGER IF EXISTS $name");
			}
			foreach ($s['rows'] as $table => $ranges)
			{
				foreach ($ranges as [$from, $to])
				{
					$deleted += $db->exec("DELETE FROM \"$table\" WHERE id > " . intval($from) . ' AND id <= ' . intval($to));
				}
			}
			foreach (array_merge(self::PRODUCT_TABLES, ['dairy_movement_ledger']) as $t)
			{
				if ($db->query("SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = '$t'")->fetchColumn())
				{
					$deleted += $db->exec("DELETE FROM $t WHERE product_id = " . self::PRODUCT_ID);
				}
			}
			// Libro de movimientos: se vuelve a sellar desde el primer movimiento del caso (cadena de hash continua)
			if ($minLog > 0)
			{
				$db->exec('UPDATE dairy_movement_ledger SET prev_hash = NULL, hash = NULL, sealed_timestamp = NULL WHERE stock_log_id > ' . $minLog);
			}
			$db->exec("DELETE FROM daily_demo_case WHERE code = '" . self::CODE . "'");
			foreach ($triggers as $sql)
			{
				$db->exec($sql);
			}
			$db->commit();
		}
		catch (\Throwable $e)
		{
			if ($db->inTransaction())
			{
				$db->rollBack();
			}
			foreach ($triggers as $sql)
			{
				$db->exec(str_replace('CREATE TRIGGER ', 'CREATE TRIGGER IF NOT EXISTS ', $sql));
			}
			throw $e;
		}
		finally
		{
			$this->Unlock();
		}
		$sealed = MovementLedgerService::GetInstance()->Seal();
		return ['deleted_rows' => $deleted, 'resealed' => $sealed];
	}

	/* ---------------------------------------------------------------- Filas creadas por cada paso */

	private function Tables(): array
	{
		static $t = null;
		if ($t === null)
		{
			$t = [];
			foreach ($this->Db()->query("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%' AND name NOT IN ('daily_demo_case', 'daily_seed_runs', 'products', 'product_barcodes')")->fetchAll(\PDO::FETCH_COLUMN) as $name)
			{
				foreach ($this->Db()->query("PRAGMA table_info(\"$name\")")->fetchAll(\PDO::FETCH_ASSOC) as $c)
				{
					if ($c['name'] === 'id' && intval($c['pk']) === 1)
					{
						$t[] = $name;
					}
				}
			}
		}
		return $t;
	}

	private function Mark(): array
	{
		$m = [];
		foreach ($this->Tables() as $t)
		{
			$m[$t] = intval($this->Db()->query("SELECT IFNULL(MAX(id), 0) FROM \"$t\"")->fetchColumn());
		}
		return $m;
	}

	private function Collect(array $rows, array $mark): array
	{
		foreach ($mark as $t => $from)
		{
			$to = intval($this->Db()->query("SELECT IFNULL(MAX(id), 0) FROM \"$t\"")->fetchColumn());
			if ($to > $from)
			{
				$rows[$t][] = [$from, $to];
			}
		}
		return $rows;
	}

	// Mismo bloqueo que la continuación de la empresa de ejemplo: el caso no se mezcla con un relleno en curso
	private function Lock(): void
	{
		$db = $this->Db();
		try
		{
			$db->exec("DELETE FROM daily_seed_runs WHERE code = 'daily_company_v1_lock' AND run_at < datetime('now', 'localtime', '-15 minutes')");
			$taken = $db->exec("INSERT OR IGNORE INTO daily_seed_runs (code, run_at) VALUES ('daily_company_v1_lock', datetime('now', 'localtime'))") === 1;
		}
		catch (\PDOException $e)
		{
			$taken = false; // otro proceso tiene la escritura (relleno de horas simuladas en curso)
		}
		if (!$taken)
		{
			throw new \Exception('La empresa de ejemplo se está actualizando (horas simuladas): intente de nuevo en unos segundos');
		}
	}

	private function Unlock(): void
	{
		$this->Db()->exec("DELETE FROM daily_seed_runs WHERE code = 'daily_company_v1_lock'");
	}

	/* ---------------------------------------------------------------- Cifras del caso (desde los registros fuente) */

	public function Summary(): array
	{
		$db = $this->Db();
		$s = $this->State();
		$r = $s['refs'];
		$p = self::PRODUCT_ID;
		$num = fn(string $sql) => floatval($db->query($sql)->fetchColumn());
		$po = !empty($r['po_id']) ? $db->query('SELECT IFNULL(SUM(IFNULL(approved_amount, suggested_amount)), 0) AS ordered, IFNULL(SUM(received_amount), 0) AS received FROM procurement_order_items WHERE order_id = ' . intval($r['po_id']) . " AND product_id = $p")->fetch(\PDO::FETCH_ASSOC) : null;
		$byLoc = $db->query("SELECT location_id, SUM(amount) AS qty, SUM(amount * IFNULL(price, 0)) AS value FROM stock WHERE product_id = $p GROUP BY location_id")->fetchAll(\PDO::FETCH_UNIQUE | \PDO::FETCH_ASSOC);
		$qty = array_sum(array_column($byLoc, 'qty'));
		$value = array_sum(array_column($byLoc, 'value'));
		$reserved = CustomerOrderService::GetInstance()->Reserved($p, self::BRANCH);
		$blocked = $num("SELECT IFNULL(SUM(amount), 0) FROM quarantine_items WHERE product_id = $p AND status = 'in_quarantine'");
		$sales = $db->query("SELECT CASE WHEN s.id IN (SELECT sale_id FROM customer_orders WHERE sale_id IS NOT NULL) THEN 'order' ELSE 'pos' END AS ch, SUM(i.amount) AS qty, SUM(i.subtotal) AS net, SUM(i.total_cost) AS cogs
			FROM sale_items i JOIN sales s ON s.id = i.sale_id WHERE i.product_id = $p AND s.status = 'completed' GROUP BY ch")->fetchAll(\PDO::FETCH_UNIQUE | \PDO::FETCH_ASSOC);
		$net = array_sum(array_column($sales, 'net'));
		$cogs = array_sum(array_column($sales, 'cogs'));
		$waste = $num("SELECT IFNULL(SUM(total_cost), 0) FROM waste_records WHERE product_id = $p AND status = 'approved'");
		$wasteQty = $num("SELECT IFNULL(SUM(amount), 0) FROM waste_records WHERE product_id = $p AND status = 'approved'");
		$wastePending = $num("SELECT IFNULL(SUM(total_cost), 0) FROM waste_records WHERE product_id = $p AND status = 'pending_approval'");
		$opex = floatval($r['expense']['amount'] ?? 0);
		$kardex = $db->query("SELECT transaction_type, spoiled, SUM(amount) AS qty, COUNT(DISTINCT transaction_id) AS tx FROM stock_log WHERE product_id = $p AND IFNULL(undone, 0) = 0 GROUP BY transaction_type, spoiled")->fetchAll(\PDO::FETCH_ASSOC);
		$wasteMoves = intval($db->query("SELECT COUNT(DISTINCT transaction_id) FROM stock_log WHERE product_id = $p AND spoiled = 1 AND IFNULL(undone, 0) = 0")->fetchColumn());
		$hasSales = $net > 0;
		return [
			'steps' => $s['steps'], 'refs' => $r, 'next' => $this->NextStep(), 'pre_close' => self::PRE_CLOSE, 'started_at' => $s['started_at'], 'cut' => $s['updated_at'],
			'po' => $po ? ['ordered' => floatval($po['ordered']), 'received' => floatval($po['received']), 'pending' => floatval($po['ordered']) - floatval($po['received'])] : null,
			'stock' => ['qty' => $qty, 'value' => round($value, 2), 'store' => floatval($byLoc[self::LOC_STORE]['qty'] ?? 0), 'shelf' => floatval($byLoc[self::LOC_SHELF]['qty'] ?? 0),
				'reserved' => $reserved, 'blocked' => $blocked, 'available' => $qty - $reserved - $blocked],
			'sales' => ['qty' => array_sum(array_column($sales, 'qty')), 'net' => round($net, 2), 'pos' => round(floatval($sales['pos']['net'] ?? 0), 2), 'pos_qty' => floatval($sales['pos']['qty'] ?? 0),
				'order' => round(floatval($sales['order']['net'] ?? 0), 2), 'order_qty' => floatval($sales['order']['qty'] ?? 0)],
			'pnl' => ['net' => round($net, 2), 'cogs' => round($cogs, 2), 'gross' => round($net - $cogs, 2), 'gross_pct' => $hasSales ? round(($net - $cogs) / $net * 100, 1) : null,
				'waste' => round($waste, 2), 'opex' => $opex, 'result' => round($net - $cogs - $waste - $opex, 2)],
			'waste' => ['qty' => $wasteQty, 'cost' => round($waste, 2), 'pending_cost' => round($wastePending, 2), 'moves' => $wasteMoves],
			'kardex' => $kardex,
		];
	}

	/** Enlaces al registro exacto de cada paso (mismos que usan Alertas y la bandeja de expedientes) */
	public function Links(array $r): array
	{
		$l = [];
		if (!empty($r['po_id']))
		{
			$l['po'] = ['/process-flow?type=procurement_order&id=' . $r['po_id'], 'Orden ' . $r['po_code']];
		}
		if (!empty($r['receipt_id']))
		{
			$l['receive'] = ['/process-flow?type=procurement_order&id=' . $r['po_id'], 'Recepción ' . $r['receipt_code']];
			$l['lot'] = ['/traceability?q=' . self::LOT, 'Lote ' . self::LOT];
		}
		$l['kardex'] = ['/kardex?product_id=' . self::PRODUCT_ID . '&branch_id=' . self::BRANCH . '&from=' . date('Y-m-d', strtotime('-7 days')), 'Kardex ' . self::SKU];
		if (!empty($r['sale_ids']))
		{
			$l['pos'] = ['/sales?product_id=' . self::PRODUCT_ID . '&branch_id=' . self::BRANCH, 'Ventas ' . self::SKU];
		}
		if (!empty($r['order_id']))
		{
			$l['order'] = ['/process-flow?type=customer_order&id=' . $r['order_id'], 'Pedido ' . $r['order_code']];
			$l['customer'] = ['/customers/' . self::CUSTOMER_ID, 'Cliente del pedido'];
		}
		if (!empty($r['alert_receipt_id']))
		{
			$l['alert_receipt'] = ['/alerts?alert=' . $r['alert_receipt_id'], 'Alerta #' . $r['alert_receipt_id']];
		}
		if (!empty($r['alert_fefo_id']))
		{
			$l['alert_fefo'] = ['/alerts?alert=' . $r['alert_fefo_id'], 'Alerta #' . $r['alert_fefo_id']];
		}
		if (!empty($r['task_id']))
		{
			$l['task'] = ['/process-flow?type=store_task&id=' . $r['task_id'], 'Tarea TAR-' . $r['task_id']];
			$l['task_mobile'] = ['/m#/tasks', 'Completar en el móvil'];
		}
		if (!empty($r['quarantine_id']))
		{
			$l['block'] = ['/process-flow?type=quarantine&id=' . $r['quarantine_id'], 'Bloqueo #' . $r['quarantine_id']];
		}
		if (!empty($r['waste_id']))
		{
			$l['waste'] = ['/process-flow?type=waste_record&id=' . $r['waste_id'], 'Merma ' . $r['waste_case']];
			$l['waste_queue'] = ['/waste/approvals', 'Aprobaciones de merma'];
		}
		$l['finance'] = ['/cost-centers?month=' . date('Y-m') . '&branch_id=' . self::BRANCH, 'Centros de costo'];
		return $l;
	}

	/** Prioridades del turno para HOY (3–5): qué pasó, dónde, quién, plazo y acción; el estado cambia al resolverse */
	public function Priorities(): array
	{
		$s = $this->Summary();
		$r = $s['refs'];
		$l = $this->Links($r);
		$db = $this->Db();
		$user = fn($id) => $id ? ($db->query('SELECT IFNULL(NULLIF(TRIM(IFNULL(first_name, \'\') || \' \' || IFNULL(last_name, \'\')), \'\'), username) FROM users WHERE id = ' . intval($id))->fetchColumn() ?: '—') : '—';
		$out = [];
		if ($s['po'] && $s['po']['pending'] > 0)
		{
			$out[] = ['key' => 'receipt', 'done' => false, 'what' => 'Recepción parcial ' . $r['po_code'] . ': faltan ' . $s['po']['pending'] . ' u.', 'who' => $user($db->query('SELECT owner_user_id FROM dairy_alerts WHERE id = ' . intval($r['alert_receipt_id'] ?? 0))->fetchColumn()),
				'due' => 'Hoy', 'action' => 'Ver saldo de la orden', 'href' => $l['po'][0]];
		}
		if (!empty($r['task_id']))
		{
			$t = $db->query('SELECT status, due_time FROM workforce_store_tasks WHERE id = ' . intval($r['task_id']))->fetch(\PDO::FETCH_ASSOC);
			$out[] = ['key' => 'fefo', 'done' => ($t['status'] ?? '') !== 'pending', 'what' => 'Tarea FEFO TAR-' . $r['task_id'] . ': retirar 6 u. del lote ' . self::LOT, 'who' => $r['task_employee'] ?? '—',
				'due' => substr((string) ($t['due_time'] ?? ''), 11, 5), 'action' => ($t['status'] ?? '') === 'pending' ? 'Completar desde el móvil' : 'Ver tarea', 'href' => ($t['status'] ?? '') === 'pending' ? $l['task_mobile'][0] : $l['task'][0]];
		}
		if (!empty($r['waste_id']))
		{
			$approved = isset($s['steps']['approve']);
			$out[] = ['key' => 'waste', 'done' => $approved, 'what' => 'Merma ' . $r['waste_case'] . ': 6 u. por $' . number_format($approved ? $s['waste']['cost'] : $s['waste']['pending_cost'], 2) . ($approved ? ' aprobada' : ' por aprobar'),
				'who' => $user(self::APPROVER_USER_ID), 'due' => 'Antes del cierre', 'action' => $approved ? 'Ver movimiento' : 'Aprobar o rechazar', 'href' => $approved ? $l['kardex'][0] : $l['waste_queue'][0]];
		}
		if (!empty($r['order_id']))
		{
			$o = $db->query('SELECT status, delivery_status FROM customer_orders WHERE id = ' . intval($r['order_id']))->fetch(\PDO::FETCH_ASSOC);
			$out[] = ['key' => 'order', 'done' => ($o['delivery_status'] ?? '') === 'delivered', 'what' => 'Pedido ' . $r['order_code'] . ' (4 u.): ' . (($o['delivery_status'] ?? '') === 'delivered' ? 'entregado' : $o['status']),
				'who' => 'Despachos', 'due' => 'Hoy', 'action' => 'Ver pedido', 'href' => $l['order'][0]];
		}
		return $out;
	}
}
