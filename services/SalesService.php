<?php

namespace Grocy\Services;

class SalesService extends BaseService
{
	// Fuente común de indicadores de venta (alias s = sales, si = sale_items). sales.gross_margin = total − IVA − costo,
	// por eso el margen % siempre se calcula sobre la venta neta de IVA. La línea no guarda IVA: se prorratea el de su ticket.
	public const SQL_NET_SALES = '(s.total_amount - IFNULL(s.tax_amount, 0))';
	public const SQL_ITEM_NET_SALES = '(si.subtotal * (1 - IFNULL(s.tax_amount, 0) / CASE WHEN IFNULL(s.total_amount, 0) = 0 THEN 1 ELSE s.total_amount END))';

	public static function MarginPct(float $margin, float $netSales, int $decimals = 1): ?float
	{
		return $netSales > 0 ? round($margin / $netSales * 100, $decimals) : null;
	}

	public function CreateSale(array $data, int $userId = GROCY_USER_ID)
	{
		$branchId = intval($data['branch_id'] ?? 1);
		$customerId = intval($data['customer_id'] ?? 1);
		$paymentMethod = $data['payment_method'] ?? 'cash';
		$notes = $data['notes'] ?? null;
		$items = $data['items'] ?? [];
		// Venta ya ocurrida en un POS externo (PosIngestService): número, fecha, impuesto y descuento los informa el POS;
		// el precio es un hecho consumado (no se valida contra la lista), pero stock, cuarentena y reservas sí.
		$ext = isset($data['_external']) && is_array($data['_external']) ? $data['_external'] : null;

		if (empty($items) || !is_array($items))
		{
			throw new \Exception('La venta debe contener al menos un producto');
		}

		if (!empty($data['enforce_open_shift']))
		{
			$shiftState = $this->GetCurrentShift($branchId);
			if (!$shiftState || ($shiftState->status ?? '') !== 'open')
			{
				throw new \Exception('La caja de esta sucursal se encuentra cerrada. Abra un turno de caja antes de registrar ventas.');
			}
		}

		// Celulares por IMEI: valida antes de la transacción para que la alerta del intento rechazado persista
		$devices = DeviceService::GetInstance();
		$items = $devices->PrepareSale($items, $branchId, !empty($data['customer_order_id']) ? intval($data['customer_order_id']) : null, $ext !== null, $userId);

		// Bloqueo de escritura desde el inicio: ventas simultáneas esperan su turno y validan stock ya actualizado
		$dbRaw = DatabaseService::GetInstance()->BeginWriteTransaction();

		try
		{
			// Generate sale number: VTA-YYYYMMDD-XXXX
			$todayStr = date('Ymd');
			$randomSuffix = strtoupper(bin2hex(random_bytes(3)));
			$saleNumber = $ext ? strval($ext['sale_number']) : "VTA-{$todayStr}-{$randomSuffix}";

			$stockService = StockService::GetInstance();

			$totalSubtotal = 0.0;
			$totalTax = 0.0;
			$totalDiscount = 0.0;
			$totalAmount = 0.0;
			$totalCogs = 0.0;

			$processedItems = [];
			$activeMarkdowns = MarkdownService::GetInstance()->GetActiveMarkdowns();

			foreach ($items as $item)
			{
				$productId = intval($item['product_id']);
				$amount = floatval($item['amount']);
				$unitPrice = floatval($item['unit_price']);
				$locationId = isset($item['location_id']) ? intval($item['location_id']) : null;
				// Lote exacto (despacho de pedido preparado por escáner): se consume esa entrada, no la FEFO general
				$stockEntryId = !empty($item['stock_id']) ? (string) $item['stock_id'] : null;

				if ($amount <= 0)
				{
					throw new \Exception("La cantidad para el producto #{$productId} debe ser mayor a 0");
				}

				$product = $this->DB->products($productId);
				if (!$product)
				{
					throw new \Exception("El producto #{$productId} no existe");
				}

				// SAP QM Candado de Calidad: Verificar si hay unidades en cuarentena activa
				$dbRaw = DatabaseService::GetInstance()->GetDbConnectionRaw();
				$stmtQuarantine = $dbRaw->prepare("SELECT IFNULL(SUM(amount), 0) FROM quarantine_items WHERE product_id = :pid AND status = 'in_quarantine' AND branch_id = :bid");
				$stmtQuarantine->execute([':pid' => $productId, ':bid' => $branchId]);
				$quarantinedAmount = floatval($stmtQuarantine->fetchColumn());

				// Lotes vendibles: la ubicación indicada o, si no hay, las ubicaciones de la sucursal (nunca la de tránsito), FEFO
				$stmtLots = $dbRaw->prepare("
					SELECT s.stock_id, s.location_id, SUM(s.amount) AS amount
					FROM stock s JOIN locations l ON l.id = s.location_id
					WHERE s.product_id = :pid AND IFNULL(l.is_transit, 0) = 0 AND " . ($locationId !== null ? 's.location_id = :loc' : 'l.branch_id = :bid') . ($stockEntryId !== null ? ' AND s.stock_id = :sid' : '') . "
					GROUP BY s.stock_id, s.location_id
					ORDER BY MIN(s.best_before_date), MIN(s.purchased_date), MIN(s.id)
				");
				$stmtLots->execute(($locationId !== null ? [':pid' => $productId, ':loc' => $locationId] : [':pid' => $productId, ':bid' => $branchId]) + ($stockEntryId !== null ? [':sid' => $stockEntryId] : []));
				$lots = $stmtLots->fetchAll(\PDO::FETCH_ASSOC);
				$currentStock = array_sum(array_column($lots, 'amount'));

				// El lote retenido en cuarentena no se vende
				$stmtQLots = $dbRaw->prepare("SELECT stock_id || '@' || location_id, SUM(amount) FROM quarantine_items WHERE product_id = :pid AND status = 'in_quarantine' AND IFNULL(stock_id, '') != '' GROUP BY stock_id, location_id");
				$stmtQLots->execute([':pid' => $productId]);
				$quarantineByLot = $stmtQLots->fetchAll(\PDO::FETCH_KEY_PAIR);
				foreach ($lots as &$lot)
				{
					$key = $lot['stock_id'] . '@' . $lot['location_id'];
					if (isset($quarantineByLot[$key]))
					{
						$held = min(floatval($lot['amount']), floatval($quarantineByLot[$key]));
						$lot['amount'] = floatval($lot['amount']) - $held;
						$quarantineByLot[$key] -= $held;
					}
				}
				unset($lot);
				$availableStock = max(0.0, $currentStock - $quarantinedAmount);
				// Stock reservado por pedidos de cliente confirmados (salvo el pedido que se está despachando)
				$reservedOthers = CustomerOrderService::GetInstance()->Reserved($productId, $branchId, !empty($data['customer_order_id']) ? intval($data['customer_order_id']) : null);
				if ($reservedOthers > 0)
				{
					$stmtBranch = $dbRaw->prepare('SELECT IFNULL(SUM(s.amount), 0) FROM stock s JOIN locations l ON l.id = s.location_id WHERE s.product_id = :pid AND l.branch_id = :bid AND IFNULL(l.is_transit, 0) = 0');
					$stmtBranch->execute([':pid' => $productId, ':bid' => $branchId]);
					$branchFree = floatval($stmtBranch->fetchColumn()) - $quarantinedAmount - $reservedOthers;
					if ($amount > $branchFree + 0.00001)
					{
						throw new \Exception("'{$product->name}': " . round($reservedOthers, 2) . ' u. están reservadas para pedidos de clientes. Libre para vender: ' . max(0, round($branchFree, 2)) . ", solicitado: {$amount}");
					}
				}

				if ($amount > $availableStock)
				{
					if ($quarantinedAmount > 0)
					{
						throw new \Exception("Bloqueo de Calidad (SAP QM): El producto '{$product->name}' tiene {$quarantinedAmount} u. retenidas en cuarentena. Stock libre vendible: {$availableStock}, Solicitado: {$amount}");
					}
					throw new \Exception("Stock insuficiente para '{$product->name}'. Disponible: {$currentStock}, Solicitado: {$amount}");
				}

				// Deduct stock via StockService, lote a lote en orden FEFO (una sola transacción)
				$transactionId = null;
				$remaining = $amount;
				foreach ($lots as $lot)
				{
					if ($remaining <= 0.00001)
					{
						break;
					}
					$take = min($remaining, floatval($lot['amount']));
					if ($take <= 0)
					{
						continue;
					}
					$stockService->ConsumeProduct(
						$productId,
						$take,
						false, // spoiled = false
						StockService::TRANSACTION_TYPE_CONSUME,
						$lot['stock_id'],
						null,
						intval($lot['location_id']),
						$transactionId,
						false,
						true // consumeExactAmount = true
					);
					$remaining -= $take;
				}
				if ($remaining > 0.00001)
				{
					throw new \Exception("Stock libre insuficiente para '{$product->name}' en la sucursal (faltan {$remaining})");
				}

				// Query stock_log to calculate exact COGS / CMV
				$consumedEntries = $this->DB->stock_log()->where('transaction_id = :1 AND spoiled = 0', $transactionId)->fetchAll();
				$itemCogsSum = 0.0;
				$itemAmountSum = 0.0;
				$primaryStockId = null;

				foreach ($consumedEntries as $entry)
				{
					$entryAmount = abs($entry->amount);
					$itemCogsSum += ($entryAmount * floatval($entry->price));
					$itemAmountSum += $entryAmount;
					if ($primaryStockId === null)
					{
						$primaryStockId = $entry->stock_id;
					}
				}

				if ($itemAmountSum > 0)
				{
					$unitCost = $itemCogsSum / $itemAmountSum;
					$itemTotalCost = $itemCogsSum;
				}
				else
				{
					$plp = $this->DB->cache__products_last_purchased()->where('product_id', $productId)->fetch();
					$unitCost = floatval($plp->price ?? 0);
					$itemTotalCost = $unitCost * $amount;
				}

				// Rebaja FEFO o Descuento Comercial POS autorizado
				$plpPrice = $this->DB->cache__products_last_purchased()->where('product_id', $productId)->fetch();
				$fallbackCost = floatval($plpPrice->price ?? 0) > 0 ? floatval($plpPrice->price) : $unitCost;
				$listPrice = floatval($product->sale_price ?? 0) > 0 ? floatval($product->sale_price) : round($fallbackCost * (1 + GROCY_SALE_PRICE_DEFAULT_MARKUP_PCT / 100), 2);
				if ($listPrice <= 0 && $ext === null)
				{
					throw new \Exception('El producto ' . $product->name . ' no tiene precio de venta ni costo de compra: configure su precio de venta');
				}
				if ($ext === null && $unitPrice < $listPrice - 0.005)
				{
					$allowPosDiscount = !empty($data['allow_pos_discount']) || !empty($item['allow_pos_discount']);
					if (!$allowPosDiscount)
					{
						$maxPct = isset($activeMarkdowns[$productId]) ? $activeMarkdowns[$productId]['discount_pct'] : 0.0;
						$minAllowed = round($listPrice * (1 - $maxPct / 100), 2);
						if ($unitPrice < $minAllowed - 0.005)
						{
							throw new \Exception("Precio no autorizado para '{$product->name}': mínimo permitido \${$minAllowed}" . ($maxPct > 0 ? " (rebaja FEFO {$maxPct}%)" : ' (sin rebaja FEFO vigente)'));
						}
					}
					$totalDiscount += round(($listPrice - $unitPrice) * $amount, 2);
				}

				$itemSubtotal = round($unitPrice * $amount, 2);

				$totalSubtotal += $itemSubtotal;
				$totalCogs += $itemTotalCost;

				$processedItems[] = [
					'product_id' => $productId,
					'stock_id' => $primaryStockId,
					'transaction_id' => $transactionId,
					'amount' => $amount,
					'qu_id' => $product->qu_id_stock,
					'unit_price' => $unitPrice,
					'subtotal' => $itemSubtotal,
					'unit_cost' => round($unitCost, 4),
					'total_cost' => round($itemTotalCost, 2)
				];
			}

			$ticketDiscount = max(0.0, round(floatval($data['ticket_discount_amount'] ?? 0), 2));
			if ($ticketDiscount > $totalSubtotal)
			{
				$ticketDiscount = $totalSubtotal;
			}
			$netAfterDiscount = round(max(0.0, $totalSubtotal - $ticketDiscount), 2);
			$totalDiscount = round($totalDiscount + $ticketDiscount, 2);

			if ($ext)
			{
				$totalTax = floatval($ext['tax_amount'] ?? 0);
				$totalDiscount = floatval($ext['discount_amount'] ?? 0);
				$totalAmount = $totalSubtotal;
			}
			else
			{
				$totalTax = max(0.0, round(floatval($data['tax_amount'] ?? 0), 2));
				$totalAmount = round($netAfterDiscount + $totalTax, 2);
			}
			// Margen bruto neto de impuesto (misma definición que /m, /sales y centros de costo): venta − IVA − costo de ventas
			$grossMargin = $totalAmount - $totalTax - $totalCogs;

			// Insert Sales Record
			$saleRow = $this->DB->sales()->createRow([
				'sale_number' => $saleNumber,
				'branch_id' => $branchId,
				'customer_id' => $customerId,
				'user_id' => $userId,
				'subtotal' => round($totalSubtotal + $totalDiscount, 2),
				'tax_amount' => round($totalTax, 2),
				'discount_amount' => round($totalDiscount, 2),
				'total_amount' => round($totalAmount, 2),
				'total_cogs' => round($totalCogs, 2),
				'gross_margin' => round($grossMargin, 2),
				'payment_method' => $paymentMethod,
				'status' => 'completed',
				'notes' => $notes,
				'created_timestamp' => $ext ? strval($ext['timestamp']) : date('Y-m-d H:i:s')
			]);
			$saleRow = $saleRow->save();

			// Insert Sale Items
			foreach ($processedItems as $pItem)
			{
				$itemRow = $this->DB->sale_items()->createRow([
					'sale_id' => $saleRow->id,
					'product_id' => $pItem['product_id'],
					'stock_id' => $pItem['stock_id'],
					'transaction_id' => $pItem['transaction_id'],
					'amount' => $pItem['amount'],
					'qu_id' => $pItem['qu_id'],
					'unit_price' => $pItem['unit_price'],
					'subtotal' => $pItem['subtotal'],
					'unit_cost' => $pItem['unit_cost'],
					'total_cost' => $pItem['total_cost']
				]);
				$itemRow->save();
			}

			$devices->MarkSold($items, intval($saleRow->id), $saleNumber, $userId);
			$dbRaw->commit();

			// DAILY Ciclo 1: Publicar evento canónico de venta confirmada en el Bus de Dominio
			if (class_exists('\\Grocy\\Services\\UnifiedInventoryEventService'))
			{
				try
				{
					\Grocy\Services\UnifiedInventoryEventService::GetInstance()->Publish(
						\Grocy\Services\UnifiedInventoryEventService::EVENT_MERCHANDISE_DISPATCHED,
						'sale',
						strval($saleRow->id),
						[
							'sale_number' => $saleNumber,
							'branch_id' => $branchId,
							'customer_id' => $customerId,
							'total_amount' => $totalAmount,
							'total_cogs' => $totalCogs,
							'gross_margin' => $grossMargin,
							'items_count' => count($processedItems),
							'payment_method' => $paymentMethod
						],
						$branchId,
						$userId
					);
				}
				catch (\Throwable $t)
				{
					// La publicación no bloquea el retorno de la venta
				}
			}

			// Sellar el libro inalterable de movimientos (Kardex SHA-256)
			if (class_exists('\\Grocy\\Services\\MovementLedgerService'))
			{
				try
				{
					\Grocy\Services\MovementLedgerService::GetInstance()->Seal();
				}
				catch (\Throwable $t)
				{
				}
			}

			return $saleRow;
		}
		catch (\Exception $ex)
		{
			$dbRaw->rollBack();
			throw $ex;
		}
	}

	public function GetSales(?int $branchId = null, ?string $startDate = null, ?string $endDate = null, int $limit = 200, ?int $groupId = null, ?int $productId = null)
	{
		$sql = "
			SELECT 
				s.*,
				b.name AS branch_name,
				c.name AS customer_name,
				u.username AS seller_username,
				(SELECT COUNT(*) FROM sale_items si WHERE si.sale_id = s.id) AS item_count
			FROM sales s
			JOIN branches b ON s.branch_id = b.id
			LEFT JOIN customers c ON s.customer_id = c.id
			JOIN users u ON s.user_id = u.id
			WHERE 1=1
		";

		$params = [];
		if ($branchId !== null && $branchId > 0)
		{
			$sql .= " AND s.branch_id = :branch_id";
			$params[':branch_id'] = $branchId;
		}
		if ($startDate !== null && !empty($startDate))
		{
			$sql .= " AND date(s.created_timestamp) >= :start_date";
			$params[':start_date'] = $startDate;
		}
		if ($endDate !== null && !empty($endDate))
		{
			$sql .= " AND date(s.created_timestamp) <= :end_date";
			$params[':end_date'] = $endDate;
		}
		if ($productId !== null && $productId > 0)
		{
			$sql .= " AND EXISTS (SELECT 1 FROM sale_items si WHERE si.sale_id = s.id AND si.product_id = :product_id)";
			$params[':product_id'] = $productId;
		}
		elseif ($groupId !== null && $groupId > 0)
		{
			$sql .= " AND EXISTS (SELECT 1 FROM sale_items si JOIN products p ON p.id = si.product_id WHERE si.sale_id = s.id AND p.product_group_id = :group_id)";
			$params[':group_id'] = $groupId;
		}
		elseif ($groupId !== null && $groupId === 0)
		{
			$sql .= " AND EXISTS (SELECT 1 FROM sale_items si LEFT JOIN products p ON p.id = si.product_id WHERE si.sale_id = s.id AND p.product_group_id IS NULL)";
		}

		$sql .= " ORDER BY s.created_timestamp DESC, s.id ASC LIMIT " . intval($limit);

		$stmt = DatabaseService::GetInstance()->GetDbConnectionRaw()->prepare($sql);
		$stmt->execute($params);
		return $stmt->fetchAll(\PDO::FETCH_OBJ);
	}

	public function GetSale(int $saleId)
	{
		$sql = "
			SELECT 
				s.*,
				b.name AS branch_name,
				b.address AS branch_address,
				c.name AS customer_name,
				c.tax_id AS customer_tax_id,
				c.phone AS customer_phone,
				u.username AS seller_username
			FROM sales s
			JOIN branches b ON s.branch_id = b.id
			LEFT JOIN customers c ON s.customer_id = c.id
			JOIN users u ON s.user_id = u.id
			WHERE s.id = :id
		";
		$stmt = DatabaseService::GetInstance()->GetDbConnectionRaw()->prepare($sql);
		$stmt->execute([':id' => $saleId]);
		return $stmt->fetch(\PDO::FETCH_OBJ);
	}

	public function GetSaleItems(int $saleId)
	{
		$sql = "
			SELECT 
				si.*,
				p.name AS product_name,
				qu.name AS qu_name
			FROM sale_items si
			JOIN products p ON si.product_id = p.id
			JOIN quantity_units qu ON si.qu_id = qu.id
			WHERE si.sale_id = :sale_id
			ORDER BY si.id ASC
		";
		$stmt = DatabaseService::GetInstance()->GetDbConnectionRaw()->prepare($sql);
		$stmt->execute([':sale_id' => $saleId]);
		return $stmt->fetchAll(\PDO::FETCH_OBJ);
	}

	// Bloqueo de escritura antes de validar el estado: dos solicitudes simultáneas no aplican el movimiento dos veces
	public function CancelSale(int $saleId, int $userId = GROCY_USER_ID)
	{
		return DatabaseService::GetInstance()->Locked(fn() => $this->CancelSaleLocked($saleId, $userId));
	}

	private function CancelSaleLocked(int $saleId, int $userId)
	{
		$sale = $this->DB->sales($saleId);
		if (!$sale)
		{
			throw new \Exception('Venta no encontrada');
		}

		if ($sale->status === 'cancelled')
		{
			throw new \Exception('Esta venta ya se encuentra cancelada');
		}

		$dbRaw = DatabaseService::GetInstance()->GetDbConnectionRaw();
		if (!$dbRaw->inTransaction())
		{
			$dbRaw->beginTransaction();
		}

		try
		{
			$stockService = StockService::GetInstance();
			$items = $this->DB->sale_items()->where('sale_id', $saleId)->fetchAll();

			foreach ($items as $item)
			{
				// Reintegrar con el vencimiento y ubicación del lote original para no romper FEFO
				$originalLot = $this->DB->stock_log()->where('stock_id', $item->stock_id)->orderBy('id')->limit(1)->fetch();
				$bestBeforeDate = $originalLot ? $originalLot->best_before_date : date('Y-m-d', strtotime('+1 year'));
				$locationId = $originalLot ? $originalLot->location_id : null;

				$reversalTxId = null;
				$stockService->AddProduct(
					$item->product_id,
					$item->amount,
					$bestBeforeDate,
					StockService::TRANSACTION_TYPE_PURCHASE,
					date('Y-m-d'),
					$item->unit_cost,
					$locationId,
					null,
					$reversalTxId,
					0,
					true, // addExactAmount = true
					"Devolución por cancelación de ticket #" . $sale->sale_number
				);
			}

			$sale->update([
				'status' => 'cancelled',
				'notes' => trim(($sale->notes ?? '') . "\n[Cancelada por usuario #$userId en " . date('Y-m-d H:i:s') . ']')
			]);

			DeviceService::GetInstance()->OnSaleCancelled($saleId, $userId);
			$dbRaw->commit();

			// Sellar el libro inalterable tras anulación/devolución de inventario
			if (class_exists('\\Grocy\\Services\\MovementLedgerService'))
			{
				try
				{
					\Grocy\Services\MovementLedgerService::GetInstance()->Seal();
				}
				catch (\Throwable $t)
				{
				}
			}

			return $sale;
		}
		catch (\Exception $ex)
		{
			$dbRaw->rollBack();
			throw $ex;
		}
	}

	public function EnsurePosTables(): void
	{
		$dbRaw = DatabaseService::GetInstance()->GetDbConnectionRaw();
		$dbRaw->exec("
			CREATE TABLE IF NOT EXISTS pos_cash_shifts (
				id INTEGER PRIMARY KEY AUTOINCREMENT,
				branch_id INTEGER NOT NULL,
				register_name TEXT NOT NULL DEFAULT 'Caja 1',
				shift_name TEXT NOT NULL DEFAULT 'Mañana',
				user_id INTEGER NOT NULL DEFAULT 1,
				status TEXT NOT NULL DEFAULT 'open',
				opening_amount REAL NOT NULL DEFAULT 200.0,
				opened_at TEXT NOT NULL,
				closed_at TEXT NULL,
				closed_by_user_id INTEGER NULL,
				expected_cash REAL NULL,
				counted_cash REAL NULL,
				cash_difference REAL NULL,
				sales_count INTEGER NOT NULL DEFAULT 0,
				total_sales REAL NOT NULL DEFAULT 0.0,
				total_cash REAL NOT NULL DEFAULT 0.0,
				total_card REAL NOT NULL DEFAULT 0.0,
				total_transfer REAL NOT NULL DEFAULT 0.0,
				notes TEXT NULL
			)
		");

		$dbRaw->exec("
			CREATE TABLE IF NOT EXISTS pos_held_sales (
				id INTEGER PRIMARY KEY AUTOINCREMENT,
				branch_id INTEGER NOT NULL DEFAULT 1,
				user_id INTEGER NOT NULL DEFAULT 1,
				customer_id INTEGER NOT NULL DEFAULT 1,
				customer_name TEXT NOT NULL DEFAULT 'Público General / Mostrador',
				label TEXT NOT NULL,
				notes TEXT NULL,
				discount_type TEXT NOT NULL DEFAULT 'pct',
				discount_value REAL NOT NULL DEFAULT 0.0,
				discount_reason TEXT NULL,
				tax_rate REAL NOT NULL DEFAULT 15.0,
				items_json TEXT NOT NULL,
				total_amount REAL NOT NULL DEFAULT 0.0,
				item_count INTEGER NOT NULL DEFAULT 0,
				created_at TEXT NOT NULL
			)
		");

		// Inicializar turno abierto por defecto para cada sucursal que aún no tenga historial de turnos
		$branches = $dbRaw->query("SELECT id FROM branches")->fetchAll(\PDO::FETCH_COLUMN);
		$stmtCheck = $dbRaw->prepare("SELECT COUNT(*) FROM pos_cash_shifts WHERE branch_id = :bid");
		$stmtInsert = $dbRaw->prepare("
			INSERT INTO pos_cash_shifts (branch_id, register_name, shift_name, user_id, status, opening_amount, opened_at, notes)
			VALUES (:bid, 'Caja 1', 'Mañana', 1, 'open', 200.00, :opened_at, 'Apertura automática de turno')
		");
		$defaultOpenTime = date('Y-m-d') . ' 08:00:00';
		foreach ($branches as $bid)
		{
			$stmtCheck->execute([':bid' => intval($bid)]);
			if (intval($stmtCheck->fetchColumn()) === 0)
			{
				$stmtInsert->execute([
					':bid' => intval($bid),
					':opened_at' => $defaultOpenTime
				]);
			}
		}
	}

	public function GetCurrentShift(int $branchId): object
	{
		$this->EnsurePosTables();
		$dbRaw = DatabaseService::GetInstance()->GetDbConnectionRaw();
		$stmt = $dbRaw->prepare("
			SELECT s.*, u.username AS opened_by_username
			FROM pos_cash_shifts s
			LEFT JOIN users u ON u.id = s.user_id
			WHERE s.branch_id = :bid
			ORDER BY s.id DESC
			LIMIT 1
		");
		$stmt->execute([':bid' => $branchId]);
		$shift = $stmt->fetch(\PDO::FETCH_OBJ);
		if (!$shift)
		{
			$openedAt = date('Y-m-d') . ' 08:00:00';
			$stmtIns = $dbRaw->prepare("
				INSERT INTO pos_cash_shifts (branch_id, register_name, shift_name, user_id, status, opening_amount, opened_at)
				VALUES (:bid, 'Caja 1', 'Mañana', 1, 'open', 200.00, :opened_at)
			");
			$stmtIns->execute([':bid' => $branchId, ':opened_at' => $openedAt]);
			return $this->GetCurrentShift($branchId);
		}

		$summary = $this->GetShiftSummary($branchId, $shift);
		$shift->live_sales_count = $summary['sales_count'];
		$shift->live_total_sales = $summary['total_sales'];
		$shift->live_total_cash = $summary['total_cash'];
		$shift->live_total_card = $summary['total_card'];
		$shift->live_total_transfer = $summary['total_transfer'];
		$shift->live_total_mixed = $summary['total_mixed'];
		$shift->live_expected_cash = round(floatval($shift->opening_amount) + $summary['total_cash'], 2);
		$shift->opened_time_short = date('H:i', strtotime($shift->opened_at));

		return $shift;
	}

	public function GetShiftSummary(int $branchId, ?object $shift = null): array
	{
		$dbRaw = DatabaseService::GetInstance()->GetDbConnectionRaw();
		$since = $shift && !empty($shift->opened_at) ? $shift->opened_at : (date('Y-m-d') . ' 00:00:00');
		$until = ($shift && ($shift->status ?? '') === 'closed' && !empty($shift->closed_at)) ? $shift->closed_at : date('Y-m-d H:i:s', time() + 60);

		$stmt = $dbRaw->prepare("
			SELECT payment_method, total_amount, notes
			FROM sales
			WHERE branch_id = :bid
			  AND status = 'completed'
			  AND datetime(created_timestamp) >= datetime(:since)
			  AND datetime(created_timestamp) <= datetime(:until)
		");
		$stmt->execute([
			':bid' => $branchId,
			':since' => $since,
			':until' => $until
		]);
		$rows = $stmt->fetchAll(\PDO::FETCH_OBJ);

		// Si no hay ventas desde la hora de apertura de hoy (ej. datos demo con fechas anteriores), incluir las del último día activo de la sucursal como respaldo informativo si el turno es el inicial
		if (empty($rows) && $shift && strpos((string)($shift->notes ?? ''), 'Apertura automática') !== false)
		{
			$stmtFallback = $dbRaw->prepare("
				SELECT payment_method, total_amount, notes
				FROM sales
				WHERE branch_id = :bid AND status = 'completed'
				  AND date(created_timestamp) = (SELECT MAX(date(created_timestamp)) FROM sales WHERE branch_id = :bid2 AND status = 'completed')
			");
			$stmtFallback->execute([':bid' => $branchId, ':bid2' => $branchId]);
			$rows = $stmtFallback->fetchAll(\PDO::FETCH_OBJ);
		}

		$salesCount = 0;
		$totalSales = 0.0;
		$totalCash = 0.0;
		$totalCard = 0.0;
		$totalTransfer = 0.0;
		$totalMixed = 0.0;

		foreach ($rows as $r)
		{
			$salesCount++;
			$amt = floatval($r->total_amount);
			$totalSales += $amt;
			$pm = strtolower(trim((string)$r->payment_method));

			if ($pm === 'cash' || $pm === 'efectivo')
			{
				$totalCash += $amt;
			}
			elseif ($pm === 'card' || $pm === 'tarjeta' || $pm === 'credit_card' || $pm === 'debit_card')
			{
				$totalCard += $amt;
			}
			elseif ($pm === 'transfer' || $pm === 'transferencia')
			{
				$totalTransfer += $amt;
			}
			elseif ($pm === 'mixed' || $pm === 'mixto')
			{
				$totalMixed += $amt;
				// Desglosar montos si están registrados en notes: [Pago Mixto: Efectivo $X | Tarjeta $Y | Transferencia $Z]
				$notesStr = (string)($r->notes ?? '');
				$parsedAny = false;
				if (preg_match('/Efectivo\s*\$([0-9]+(?:\.[0-9]+)?)/i', $notesStr, $mCash))
				{
					$totalCash += floatval($mCash[1]);
					$parsedAny = true;
				}
				if (preg_match('/Tarjeta\s*\$([0-9]+(?:\.[0-9]+)?)/i', $notesStr, $mCard))
				{
					$totalCard += floatval($mCard[1]);
					$parsedAny = true;
				}
				if (preg_match('/Transferencia\s*\$([0-9]+(?:\.[0-9]+)?)/i', $notesStr, $mTrans))
				{
					$totalTransfer += floatval($mTrans[1]);
					$parsedAny = true;
				}
				if (!$parsedAny)
				{
					$totalCash += round($amt * 0.5, 2);
					$totalCard += round($amt - round($amt * 0.5, 2), 2);
				}
			}
			else
			{
				$totalCard += $amt;
			}
		}

		return [
			'sales_count' => $salesCount,
			'total_sales' => round($totalSales, 2),
			'total_cash' => round($totalCash, 2),
			'total_card' => round($totalCard, 2),
			'total_transfer' => round($totalTransfer, 2),
			'total_mixed' => round($totalMixed, 2)
		];
	}

	public function OpenShift(array $data, int $userId = GROCY_USER_ID): object
	{
		$this->EnsurePosTables();
		$branchId = max(1, intval($data['branch_id'] ?? 1));
		$registerName = trim(strval($data['register_name'] ?? 'Caja 1')) ?: 'Caja 1';
		$shiftName = trim(strval($data['shift_name'] ?? 'Mañana')) ?: 'Mañana';
		$openingAmount = max(0.0, round(floatval($data['opening_amount'] ?? 200.0), 2));
		$notes = trim(strval($data['notes'] ?? '')) ?: null;

		$dbRaw = DatabaseService::GetInstance()->GetDbConnectionRaw();
		// Cerrar cualquier turno que haya quedado abierto en la misma sucursal antes de abrir uno nuevo
		$stmtClosePrev = $dbRaw->prepare("
			UPDATE pos_cash_shifts
			SET status = 'closed', closed_at = :now, closed_by_user_id = :uid
			WHERE branch_id = :bid AND status = 'open'
		");
		$now = date('Y-m-d H:i:s');
		$stmtClosePrev->execute([':now' => $now, ':uid' => $userId, ':bid' => $branchId]);

		$stmtIns = $dbRaw->prepare("
			INSERT INTO pos_cash_shifts (branch_id, register_name, shift_name, user_id, status, opening_amount, opened_at, notes)
			VALUES (:bid, :reg, :shift, :uid, 'open', :open_amt, :opened_at, :notes)
		");
		$stmtIns->execute([
			':bid' => $branchId,
			':reg' => $registerName,
			':shift' => $shiftName,
			':uid' => $userId,
			':open_amt' => $openingAmount,
			':opened_at' => $now,
			':notes' => $notes
		]);

		return $this->GetCurrentShift($branchId);
	}

	public function CloseShift(array $data, int $userId = GROCY_USER_ID): object
	{
		$this->EnsurePosTables();
		$branchId = max(1, intval($data['branch_id'] ?? 1));
		$current = $this->GetCurrentShift($branchId);
		if (!$current || ($current->status ?? '') !== 'open')
		{
			throw new \Exception('La caja ya se encuentra cerrada en esta sucursal.');
		}

		$expectedCash = round(floatval($current->live_expected_cash ?? $current->opening_amount), 2);
		$countedCash = isset($data['counted_cash']) && $data['counted_cash'] !== ''
			? round(floatval($data['counted_cash']), 2)
			: $expectedCash;
		$difference = round($countedCash - $expectedCash, 2);
		$closeNotes = trim(strval($data['notes'] ?? ''));

		$dbRaw = DatabaseService::GetInstance()->GetDbConnectionRaw();
		$stmtUpd = $dbRaw->prepare("
			UPDATE pos_cash_shifts
			SET status = 'closed',
			    closed_at = :closed_at,
			    closed_by_user_id = :uid,
			    expected_cash = :expected_cash,
			    counted_cash = :counted_cash,
			    cash_difference = :diff,
			    sales_count = :scount,
			    total_sales = :tsales,
			    total_cash = :tcash,
			    total_card = :tcard,
			    total_transfer = :ttrans,
			    notes = :notes
			WHERE id = :id
		");
		$combinedNotes = trim(($current->notes ? $current->notes . ' | ' : '') . ($closeNotes ?: 'Cierre de caja registrado'));
		$stmtUpd->execute([
			':closed_at' => date('Y-m-d H:i:s'),
			':uid' => $userId,
			':expected_cash' => $expectedCash,
			':counted_cash' => $countedCash,
			':diff' => $difference,
			':scount' => intval($current->live_sales_count ?? 0),
			':tsales' => floatval($current->live_total_sales ?? 0),
			':tcash' => floatval($current->live_total_cash ?? 0),
			':tcard' => floatval($current->live_total_card ?? 0),
			':ttrans' => floatval($current->live_total_transfer ?? 0),
			':notes' => $combinedNotes,
			':id' => intval($current->id)
		]);

		return $this->GetCurrentShift($branchId);
	}

	public function GetHeldSales(int $branchId): array
	{
		$this->EnsurePosTables();
		$dbRaw = DatabaseService::GetInstance()->GetDbConnectionRaw();
		$stmt = $dbRaw->prepare("
			SELECT *
			FROM pos_held_sales
			WHERE branch_id = :bid
			ORDER BY id DESC
		");
		$stmt->execute([':bid' => $branchId]);
		$rows = $stmt->fetchAll(\PDO::FETCH_OBJ);
		foreach ($rows as $row)
		{
			$decoded = json_decode((string)$row->items_json, true);
			$row->items = is_array($decoded) ? $decoded : [];
		}
		return $rows;
	}

	public function HoldSale(array $data, int $userId = GROCY_USER_ID): object
	{
		$this->EnsurePosTables();
		$branchId = max(1, intval($data['branch_id'] ?? 1));
		$customerId = max(1, intval($data['customer_id'] ?? 1));
		$customerName = trim(strval($data['customer_name'] ?? 'Público General / Mostrador')) ?: 'Público General / Mostrador';
		$items = $data['items'] ?? [];
		if (empty($items) || !is_array($items))
		{
			throw new \Exception('El ticket debe tener al menos un producto para guardarse en espera.');
		}

		$label = trim(strval($data['label'] ?? ''));
		if ($label === '')
		{
			$label = 'Ticket en espera - ' . $customerName . ' (' . date('H:i') . ')';
		}
		$notes = trim(strval($data['notes'] ?? '')) ?: null;
		$discountType = in_array($data['discount_type'] ?? 'pct', ['pct', 'amt'], true) ? $data['discount_type'] : 'pct';
		$discountValue = max(0.0, floatval($data['discount_value'] ?? 0));
		$discountReason = trim(strval($data['discount_reason'] ?? '')) ?: null;
		$taxRate = max(0.0, floatval($data['tax_rate'] ?? 15.0));

		$itemCount = 0;
		$subtotal = 0.0;
		foreach ($items as $it)
		{
			$qty = max(0.0, floatval($it['amount'] ?? 0));
			$price = max(0.0, floatval($it['unit_price'] ?? 0));
			$itemCount += intval(ceil($qty));
			$subtotal += ($qty * $price);
		}
		$discAmt = $discountType === 'pct' ? ($subtotal * ($discountValue / 100.0)) : $discountValue;
		$net = max(0.0, $subtotal - $discAmt);
		$totalAmount = round($net * (1.0 + ($taxRate / 100.0)), 2);

		$dbRaw = DatabaseService::GetInstance()->GetDbConnectionRaw();
		$stmt = $dbRaw->prepare("
			INSERT INTO pos_held_sales (
				branch_id, user_id, customer_id, customer_name, label, notes,
				discount_type, discount_value, discount_reason, tax_rate,
				items_json, total_amount, item_count, created_at
			) VALUES (
				:bid, :uid, :cid, :cname, :label, :notes,
				:dtype, :dval, :dreason, :tax_rate,
				:items_json, :total_amount, :item_count, :created_at
			)
		");
		$now = date('Y-m-d H:i:s');
		$stmt->execute([
			':bid' => $branchId,
			':uid' => $userId,
			':cid' => $customerId,
			':cname' => $customerName,
			':label' => $label,
			':notes' => $notes,
			':dtype' => $discountType,
			':dval' => $discountValue,
			':dreason' => $discountReason,
			':tax_rate' => $taxRate,
			':items_json' => json_encode(array_values($items), JSON_UNESCAPED_UNICODE),
			':total_amount' => $totalAmount,
			':item_count' => $itemCount,
			':created_at' => $now
		]);

		$newId = intval($dbRaw->lastInsertId());
		$stmtGet = $dbRaw->prepare("SELECT * FROM pos_held_sales WHERE id = :id");
		$stmtGet->execute([':id' => $newId]);
		$held = $stmtGet->fetch(\PDO::FETCH_OBJ);
		$held->items = array_values($items);
		return $held;
	}

	public function DeleteHeldSale(int $holdId): bool
	{
		$this->EnsurePosTables();
		$dbRaw = DatabaseService::GetInstance()->GetDbConnectionRaw();
		$stmt = $dbRaw->prepare("DELETE FROM pos_held_sales WHERE id = :id");
		return $stmt->execute([':id' => $holdId]);
	}

	/* =========================================================================
	   DIRECTORIO DE CLIENTES (MÓDULO /customers COMPARTIDO CON /pos, /orders, /sales)
	   ========================================================================= */

	public function EnsureCustomerColumns(): void
	{
		$dbRaw = DatabaseService::GetInstance()->GetDbConnectionRaw();
		$cols = $dbRaw->query("PRAGMA table_info(customers)")->fetchAll(\PDO::FETCH_COLUMN, 1);

		$alterMap = [
			'customer_type' => "ALTER TABLE customers ADD COLUMN customer_type TEXT DEFAULT 'Comercial'",
			'branch_id' => "ALTER TABLE customers ADD COLUMN branch_id INTEGER NULL",
			'tags' => "ALTER TABLE customers ADD COLUMN tags TEXT NULL",
			'notes' => "ALTER TABLE customers ADD COLUMN notes TEXT NULL",
			'credit_limit' => "ALTER TABLE customers ADD COLUMN credit_limit REAL NULL",
			'seeded_directory_v1' => "ALTER TABLE customers ADD COLUMN seeded_directory_v1 INTEGER DEFAULT 0"
		];

		foreach ($alterMap as $colName => $sql)
		{
			if (!in_array($colName, $cols, true))
			{
				$dbRaw->exec($sql);
			}
		}

		// Reemplazar cualquier nombre de persona real en clientes demo por razón social genérica
		$dbRaw->exec("UPDATE customers SET name = 'Restaurante Central del Valle', email = 'compras@centraldelvalle.com' WHERE name LIKE '%Don Pedro%'");

		// Inicializar metadatos comerciales en los 5 clientes existentes solo la primera vez
		$unseeded = intval($dbRaw->query("SELECT COUNT(*) FROM customers WHERE IFNULL(seeded_directory_v1, 0) = 0")->fetchColumn());
		if ($unseeded > 0)
		{
			$dbRaw->exec("
				UPDATE customers SET
					customer_type = 'Minorista',
					branch_id = 1,
					tags = 'Mostrador, Contado',
					notes = 'Cuenta genérica para ventas rápidas de mostrador sin facturación nominal.',
					credit_limit = NULL,
					seeded_directory_v1 = 1
				WHERE id = 1 AND IFNULL(seeded_directory_v1, 0) = 0
			");
			$dbRaw->exec("
				UPDATE customers SET
					customer_type = 'Comercial',
					branch_id = 1,
					tags = 'HORECA, Crédito',
					notes = 'Cliente gastronómico recurrente. Entregas antes de las 11:00 am en recepción de cocina.',
					credit_limit = 5000.00,
					seeded_directory_v1 = 1
				WHERE id = 2 AND IFNULL(seeded_directory_v1, 0) = 0
			");
			$dbRaw->exec("
				UPDATE customers SET
					customer_type = 'Comercial',
					branch_id = 1,
					tags = 'Mayorista, Crédito, Barista',
					notes = 'Cliente habitual de lácteos y cremas. Atención preferente en temporada alta.',
					credit_limit = 10000.00,
					seeded_directory_v1 = 1
				WHERE id = 3 AND IFNULL(seeded_directory_v1, 0) = 0
			");
			$dbRaw->exec("
				UPDATE customers SET
					customer_type = 'Corporativo',
					branch_id = 2,
					tags = 'Mayorista, Cadena Frío, Crédito',
					notes = ' Cadena regional autoservicio. Requiere cajas térmicas selladas con precinto.',
					credit_limit = 15000.00,
					seeded_directory_v1 = 1
				WHERE id = 4 AND IFNULL(seeded_directory_v1, 0) = 0
			");
			$dbRaw->exec("
				UPDATE customers SET
					customer_type = 'Institucional',
					branch_id = 1,
					tags = 'Institucional, Comedor',
					notes = 'Menú desayuno institucional. Pendiente confirmar correo de facturación electrónica y dirección fiscal completa.',
					email = '',
					address = '',
					credit_limit = 3500.00,
					seeded_directory_v1 = 1
				WHERE id = 5 AND IFNULL(seeded_directory_v1, 0) = 0
			");
			$dbRaw->exec("UPDATE customers SET seeded_directory_v1 = 1 WHERE IFNULL(seeded_directory_v1, 0) = 0");
		}
	}

	public function GetCustomersDirectory(): array
	{
		$this->EnsureCustomerColumns();
		$dbRaw = DatabaseService::GetInstance()->GetDbConnectionRaw();

		$sql = "
			SELECT
				c.*,
				IFNULL(b.name, 'Sin sucursal') AS branch_name,
				IFNULL(s_agg.sales_count, 0) AS sales_count,
				IFNULL(s_agg.sales_total, 0.0) AS sales_total,
				s_agg.last_sale_at,
				s_agg.last_sale_amount,
				IFNULL(o_agg.orders_total_count, 0) AS orders_total_count,
				IFNULL(o_agg.open_orders_count, 0) AS open_orders_count,
				IFNULL(o_agg.open_orders_value, 0.0) AS open_orders_value,
				IFNULL(o_agg.dispatched_orders_count, 0) AS dispatched_orders_count,
				IFNULL(o_agg.orders_value_sum, 0.0) AS orders_value_sum,
				o_agg.last_order_at,
				o_agg.last_order_amount
			FROM customers c
			LEFT JOIN branches b ON b.id = c.branch_id
			LEFT JOIN (
				SELECT
					s.customer_id,
					COUNT(*) AS sales_count,
					SUM(s.total_amount) AS sales_total,
					MAX(s.created_timestamp) AS last_sale_at,
					(
						SELECT s2.total_amount
						FROM sales s2
						WHERE s2.customer_id = s.customer_id AND s2.status = 'completed'
						ORDER BY s2.created_timestamp DESC, s2.id DESC
						LIMIT 1
					) AS last_sale_amount
				FROM sales s
				WHERE s.status = 'completed' AND s.customer_id IS NOT NULL
				GROUP BY s.customer_id
			) s_agg ON s_agg.customer_id = c.id
			LEFT JOIN (
				SELECT
					co.customer_id,
					COUNT(*) AS orders_total_count,
					SUM(CASE WHEN co.status NOT IN ('dispatched', 'cancelled') THEN 1 ELSE 0 END) AS open_orders_count,
					SUM(CASE WHEN co.status NOT IN ('dispatched', 'cancelled') THEN IFNULL(oi.order_total, 0) ELSE 0 END) AS open_orders_value,
					SUM(CASE WHEN co.status = 'dispatched' THEN 1 ELSE 0 END) AS dispatched_orders_count,
					SUM(CASE WHEN co.status != 'cancelled' THEN IFNULL(oi.order_total, 0) ELSE 0 END) AS orders_value_sum,
					MAX(co.created_at) AS last_order_at,
					(
						SELECT SUM(ci2.amount * ci2.unit_price)
						FROM customer_orders co2
						JOIN customer_order_items ci2 ON ci2.order_id = co2.id
						WHERE co2.customer_id = co.customer_id AND co2.status != 'cancelled'
						ORDER BY co2.created_at DESC, co2.id DESC
						LIMIT 1
					) AS last_order_amount
				FROM customer_orders co
				LEFT JOIN (
					SELECT order_id, SUM(amount * unit_price) AS order_total
					FROM customer_order_items
					GROUP BY order_id
				) oi ON oi.order_id = co.id
				GROUP BY co.customer_id
			) o_agg ON o_agg.customer_id = c.id
			ORDER BY c.id ASC
		";

		$rows = $dbRaw->query($sql)->fetchAll(\PDO::FETCH_ASSOC);

		// Pedidos recientes por cliente (y globales) para mostrar en la ficha y en la tarjeta inferior
		$stmtOrders = $dbRaw->query("
			SELECT
				co.id,
				co.order_code,
				co.customer_id,
				c.name AS customer_name,
				co.branch_id,
				b.name AS branch_name,
				co.status,
				co.requested_date,
				co.created_at,
				co.sale_id,
				IFNULL((SELECT SUM(i.amount * i.unit_price) FROM customer_order_items i WHERE i.order_id = co.id), 0) AS total_amount
			FROM customer_orders co
			JOIN customers c ON c.id = co.customer_id
			LEFT JOIN branches b ON b.id = co.branch_id
			ORDER BY co.id DESC
			LIMIT 40
		");
		$allRecentOrders = $stmtOrders->fetchAll(\PDO::FETCH_ASSOC);
		$ordersByCustomer = [];
		foreach ($allRecentOrders as &$ro)
		{
			$ro['total_amount'] = round(floatval($ro['total_amount']), 2);
			$ro['status_label'] = CustomerOrderService::STATUS[$ro['status']] ?? $ro['status'];
			$ro['source'] = 'order';
			$ro['ref_id'] = intval($ro['id']);
			$ro['code'] = $ro['order_code'];
			$ro['date'] = $ro['created_at'] ?? $ro['requested_date'];
			$ro['status'] = $ro['status_label'];
			$ro['amount'] = $ro['total_amount'];
			$ordersByCustomer[intval($ro['customer_id'])][] = $ro;
		}
		unset($ro);

		// Ventas recientes por cliente en POS
		$stmtSales = $dbRaw->query("
			SELECT
				s.id,
				s.sale_number,
				s.customer_id,
				s.branch_id,
				b.name AS branch_name,
				s.total_amount,
				s.payment_method,
				s.status,
				s.created_timestamp
			FROM sales s
			LEFT JOIN branches b ON b.id = s.branch_id
			WHERE s.status = 'completed' AND s.customer_id IS NOT NULL
			ORDER BY s.id DESC
			LIMIT 50
		");
		$salesByCustomer = [];
		foreach ($stmtSales->fetchAll(\PDO::FETCH_ASSOC) as $sr)
		{
			$cid = intval($sr['customer_id']);
			if (!isset($salesByCustomer[$cid]) || count($salesByCustomer[$cid]) < 8)
			{
				$salesByCustomer[$cid][] = $sr;
			}
		}

		foreach ($rows as &$r)
		{
			$cid = intval($r['id']);
			$r['id'] = $cid;
			$r['display_code'] = '#' . (100 + $cid);
			$r['code'] = $r['display_code'];
			$r['active'] = intval($r['active']);
			$r['customer_type'] = trim((string)($r['customer_type'] ?? '')) ?: 'Comercial';
			$r['row_created_timestamp'] = $r['created_timestamp'] ?? null;

			// Etiquetas como arreglo limpio
			$rawTags = array_filter(array_map('trim', explode(',', (string)($r['tags'] ?? ''))));
			$r['tags_list'] = array_values($rawTags);

			// Detectar campos faltantes / datos pendientes
			$missingFields = [];
			if (trim((string)($r['tax_id'] ?? '')) === '') $missingFields[] = 'identificación fiscal';
			if (trim((string)($r['phone'] ?? '')) === '') $missingFields[] = 'teléfono';
			if (trim((string)($r['email'] ?? '')) === '') $missingFields[] = 'correo electrónico';
			if (trim((string)($r['address'] ?? '')) === '') $missingFields[] = 'dirección fiscal';

			$r['missing_fields'] = $missingFields;
			$r['has_incomplete_data'] = count($missingFields) > 0;
			$r['has_pending_data'] = $r['has_incomplete_data'];
			$r['missing_summary'] = count($missingFields) > 0
				? ('Falta: ' . implode(', ', $missingFields))
				: '';

			// Estadísticas comerciales combinadas (ventas POS + pedidos no facturados aún)
			$salesCnt = intval($r['sales_count']);
			$ordersTotalCnt = intval($r['orders_total_count']);
			$openOrdersCnt = intval($r['open_orders_count']);
			$r['open_orders_count'] = $openOrdersCnt;
			$r['has_open_orders'] = $openOrdersCnt > 0;
			$r['total_transactions'] = $salesCnt + $openOrdersCnt;
			$r['total_purchases'] = $r['total_transactions'];
			$r['total_amount_purchased'] = round(floatval($r['sales_total']) + floatval($r['open_orders_value']), 2);
			$r['total_amount'] = $r['total_amount_purchased'];

			// Última compra o pedido real (sin inventar fechas si no existen)
			$lastSaleAt = $r['last_sale_at'] ?? null;
			$lastOrderAt = $r['last_order_at'] ?? null;
			if ($lastSaleAt && (!$lastOrderAt || $lastSaleAt >= $lastOrderAt))
			{
				$r['last_purchase_at'] = $lastSaleAt;
				$r['last_purchase_date'] = $lastSaleAt;
				$r['last_purchase_amount'] = round(floatval($r['last_sale_amount']), 2);
			}
			elseif ($lastOrderAt)
			{
				$r['last_purchase_at'] = $lastOrderAt;
				$r['last_purchase_date'] = $lastOrderAt;
				$r['last_purchase_amount'] = round(floatval($r['last_order_amount']), 2);
			}
			else
			{
				$r['last_purchase_at'] = null;
				$r['last_purchase_date'] = null;
				$r['last_purchase_amount'] = null;
			}

			// Crédito disponible solo cuando existe un límite configurado (> 0)
			if ($r['credit_limit'] !== null && $r['credit_limit'] !== '' && floatval($r['credit_limit']) > 0)
			{
				$limit = round(floatval($r['credit_limit']), 2);
				$committed = round(floatval($r['open_orders_value']), 2);
				$r['credit_limit'] = $limit;
				$r['credit_limit_val'] = $limit;
				$r['credit_available'] = max(0.0, round($limit - $committed, 2));
				$r['credit_available_val'] = $r['credit_available'];
				$r['has_credit_config'] = true;
				$r['has_credit_configured'] = true;
			}
			else
			{
				$r['credit_limit'] = null;
				$r['credit_limit_val'] = null;
				$r['credit_available'] = null;
				$r['credit_available_val'] = null;
				$r['has_credit_config'] = false;
				$r['has_credit_configured'] = false;
			}

			$r['recent_orders'] = $ordersByCustomer[$cid] ?? [];
			$r['recent_sales'] = $salesByCustomer[$cid] ?? [];
		}
		unset($r);

		return [
			'customers' => $rows,
			'recent_orders_all' => array_slice($allRecentOrders, 0, 12)
		];
	}

	/**
	 * Detecta posibles duplicados por identificación fiscal (tax_id), correo electrónico o teléfono.
	 * No fusiona ni elimina registros automáticamente; devuelve las coincidencias para advertir al usuario.
	 */
	public function CheckCustomerDuplicates(array $data, ?int $excludeId = null): array
	{
		$this->EnsureCustomerColumns();
		$dbRaw = DatabaseService::GetInstance()->GetDbConnectionRaw();

		$taxId = trim((string)($data['tax_id'] ?? ''));
		$email = mb_strtolower(trim((string)($data['email'] ?? '')), 'UTF-8');
		$phone = preg_replace('/[^0-9+]/', '', (string)($data['phone'] ?? ''));

		$matches = [];
		$rows = $dbRaw->query("SELECT id, name, tax_id, email, phone FROM customers")->fetchAll(\PDO::FETCH_ASSOC);
		foreach ($rows as $r)
		{
			$cid = intval($r['id']);
			if ($excludeId !== null && $cid === $excludeId)
			{
				continue;
			}
			$reasons = [];
			if ($taxId !== '' && strcasecmp($taxId, trim((string)$r['tax_id'])) === 0)
			{
				$reasons[] = 'Identificación fiscal (' . $taxId . ')';
			}
			if ($email !== '' && mb_strtolower(trim((string)$r['email']), 'UTF-8') === $email)
			{
				$reasons[] = 'Correo electrónico (' . $email . ')';
			}
			$rPhone = preg_replace('/[^0-9+]/', '', (string)$r['phone']);
			if (strlen($phone) >= 7 && $phone === $rPhone)
			{
				$reasons[] = 'Teléfono (' . $r['phone'] . ')';
			}
			if (!empty($reasons))
			{
				$matches[] = [
					'id' => $cid,
					'display_code' => '#' . (100 + $cid),
					'code' => '#' . (100 + $cid),
					'name' => $r['name'],
					'reasons' => $reasons
				];
			}
		}
		return $matches;
	}

	public function SaveCustomer(array $data): array
	{
		$this->EnsureCustomerColumns();
		$dbRaw = DatabaseService::GetInstance()->GetDbConnectionRaw();

		$id = !empty($data['id']) ? intval($data['id']) : null;
		$name = trim((string)($data['name'] ?? ''));
		if ($name === '')
		{
			throw new \Exception('El nombre o razón social del cliente es obligatorio.');
		}

		$taxId = trim((string)($data['tax_id'] ?? '')) ?: null;
		$phone = trim((string)($data['phone'] ?? '')) ?: null;
		$email = trim((string)($data['email'] ?? '')) ?: null;
		if ($email !== null && !filter_var($email, FILTER_VALIDATE_EMAIL))
		{
			throw new \Exception('El formato del correo electrónico no es válido.');
		}
		$address = trim((string)($data['address'] ?? '')) ?: null;
		$customerType = trim((string)($data['customer_type'] ?? 'Comercial')) ?: 'Comercial';
		$branchId = !empty($data['branch_id']) ? intval($data['branch_id']) : null;
		$tags = trim((string)($data['tags'] ?? '')) ?: null;
		$notes = trim((string)($data['notes'] ?? '')) ?: null;
		$active = isset($data['active']) ? (intval($data['active']) ? 1 : 0) : 1;
		$creditLimit = (isset($data['credit_limit']) && $data['credit_limit'] !== '' && $data['credit_limit'] !== null)
			? max(0.0, round(floatval($data['credit_limit']), 2))
			: null;

		// Verificar posibles duplicados antes de guardar si no viene confirmado explícitamente
		$duplicates = $this->CheckCustomerDuplicates([
			'tax_id' => $taxId,
			'email' => $email,
			'phone' => $phone
		], $id);

		if (!empty($duplicates) && empty($data['confirm_duplicate']))
		{
			return [
				'success' => false,
				'saved' => false,
				'duplicate_warning' => true,
				'duplicates' => $duplicates,
				'message' => 'Se detectó al menos un cliente existente con datos coincidentes.'
			];
		}

		if ($id)
		{
			$stmt = $dbRaw->prepare("
				UPDATE customers
				SET name = :name,
				    tax_id = :tax_id,
				    phone = :phone,
				    email = :email,
				    address = :address,
				    customer_type = :ctype,
				    branch_id = :bid,
				    tags = :tags,
				    notes = :notes,
				    credit_limit = :climit,
				    active = :active
				WHERE id = :id
			");
			$stmt->execute([
				':name' => $name,
				':tax_id' => $taxId,
				':phone' => $phone,
				':email' => $email,
				':address' => $address,
				':ctype' => $customerType,
				':bid' => $branchId,
				':tags' => $tags,
				':notes' => $notes,
				':climit' => $creditLimit,
				':active' => $active,
				':id' => $id
			]);
		}
		else
		{
			$stmt = $dbRaw->prepare("
				INSERT INTO customers (
					name, tax_id, phone, email, address,
					customer_type, branch_id, tags, notes, credit_limit,
					active, created_timestamp, seeded_directory_v1
				) VALUES (
					:name, :tax_id, :phone, :email, :address,
					:ctype, :bid, :tags, :notes, :climit,
					:active, :created_at, 1
				)
			");
			$stmt->execute([
				':name' => $name,
				':tax_id' => $taxId,
				':phone' => $phone,
				':email' => $email,
				':address' => $address,
				':ctype' => $customerType,
				':bid' => $branchId,
				':tags' => $tags,
				':notes' => $notes,
				':climit' => $creditLimit,
				':active' => $active,
				':created_at' => date('Y-m-d H:i:s')
			]);
			$id = intval($dbRaw->lastInsertId());
		}

		$dir = $this->GetCustomersDirectory();
		$savedCustomer = null;
		foreach ($dir['customers'] as $c)
		{
			if (intval($c['id']) === $id)
			{
				$savedCustomer = $c;
				break;
			}
		}

		return [
			'success' => true,
			'saved' => true,
			'duplicate_warning' => false,
			'customer_id' => $id,
			'customer' => $savedCustomer,
			'directory' => $dir
		];
	}

	public function ToggleCustomerActive(int $customerId): array
	{
		$this->EnsureCustomerColumns();
		$dbRaw = DatabaseService::GetInstance()->GetDbConnectionRaw();
		$stmt = $dbRaw->prepare("SELECT active FROM customers WHERE id = :id");
		$stmt->execute([':id' => $customerId]);
		$cur = $stmt->fetchColumn();
		if ($cur === false)
		{
			throw new \Exception('Cliente no encontrado.');
		}
		$newActive = intval($cur) === 1 ? 0 : 1;
		$dbRaw->prepare("UPDATE customers SET active = :a WHERE id = :id")->execute([':a' => $newActive, ':id' => $customerId]);
		$dir = $this->GetCustomersDirectory();
		$updatedCustomer = null;
		foreach ($dir['customers'] as $c)
		{
			if (intval($c['id']) === $customerId)
			{
				$updatedCustomer = $c;
				break;
			}
		}
		return [
			'success' => true,
			'customer_id' => $customerId,
			'active' => $newActive,
			'customer' => $updatedCustomer,
			'directory' => $dir
		];
	}

	public function SaveCustomerNotes(int $customerId, ?string $notes, ?string $tags = null): array
	{
		$this->EnsureCustomerColumns();
		$dbRaw = DatabaseService::GetInstance()->GetDbConnectionRaw();
		if ($tags !== null)
		{
			$dbRaw->prepare("UPDATE customers SET notes = :n, tags = :t WHERE id = :id")->execute([
				':n' => trim((string)$notes) ?: null,
				':t' => trim((string)$tags) ?: null,
				':id' => $customerId
			]);
		}
		else
		{
			$dbRaw->prepare("UPDATE customers SET notes = :n WHERE id = :id")->execute([
				':n' => trim((string)$notes) ?: null,
				':id' => $customerId
			]);
		}
		$dir = $this->GetCustomersDirectory();
		$updatedCustomer = null;
		foreach ($dir['customers'] as $c)
		{
			if (intval($c['id']) === $customerId)
			{
				$updatedCustomer = $c;
				break;
			}
		}
		return [
			'success' => true,
			'customer_id' => $customerId,
			'customer' => $updatedCustomer,
			'directory' => $dir
		];
	}
}
