<?php

namespace Grocy\Services;

/**
 * Traslados entre sucursales en dos pasos (equivalente a SAP 303/305):
 * - Envío: los lotes salen de la ubicación de origen hacia la ubicación de tránsito (FEFO, sin lotes en cuarentena).
 * - Recepción: lo recibido pasa a la ubicación de destino; la diferencia se da de baja como merma de traslado.
 * Cada paso es una transacción de stock (transfer_from / transfer_to) y el documento TRA- enlaza ambas.
 */
class StockTransferService extends BaseService
{
	public function GetTransitLocationId(): int
	{
		$id = intval(DatabaseService::GetInstance()->GetDbConnectionRaw()->query('SELECT id FROM locations WHERE is_transit = 1 ORDER BY id LIMIT 1')->fetchColumn());
		if ($id <= 0)
		{
			throw new \Exception('No existe la ubicación de tránsito (migración 0268)');
		}
		return $id;
	}

	public function GetTransfers(?int $branchId = null, ?string $status = null, int $limit = 100): array
	{
		$dbRaw = DatabaseService::GetInstance()->GetDbConnectionRaw();
		$sql = "
			SELECT t.*, bf.name AS from_branch_name, bt.name AS to_branch_name, lf.name AS from_location_name, lt.name AS to_location_name,
				" . $this->UserNameSql('uc') . " AS created_by_name, " . $this->UserNameSql('ur') . " AS received_by_name,
				(SELECT COUNT(DISTINCT product_id) FROM stock_transfer_items i WHERE i.transfer_id = t.id) AS product_count,
				(SELECT SUM(sent_amount) FROM stock_transfer_items i WHERE i.transfer_id = t.id) AS sent_units,
				(SELECT SUM(difference_amount) FROM stock_transfer_items i WHERE i.transfer_id = t.id) AS difference_units
			FROM stock_transfers t
			JOIN branches bf ON bf.id = t.from_branch_id
			JOIN branches bt ON bt.id = t.to_branch_id
			JOIN locations lf ON lf.id = t.from_location_id
			JOIN locations lt ON lt.id = t.to_location_id
			LEFT JOIN users uc ON uc.id = t.created_by_user_id
			LEFT JOIN users ur ON ur.id = t.received_by_user_id
			WHERE 1 = 1
		";
		$params = [];
		if ($branchId)
		{
			$sql .= ' AND (t.from_branch_id = :b1 OR t.to_branch_id = :b2)';
			$params[':b1'] = $branchId;
			$params[':b2'] = $branchId;
		}
		if ($status)
		{
			$sql .= ' AND t.status = :st';
			$params[':st'] = $status;
		}
		$sql .= ' ORDER BY t.id DESC LIMIT ' . max(1, $limit);
		$stmt = $dbRaw->prepare($sql);
		$stmt->execute($params);
		return $stmt->fetchAll(\PDO::FETCH_ASSOC);
	}

	public function GetTransferDetail(int $transferId): array
	{
		$dbRaw = DatabaseService::GetInstance()->GetDbConnectionRaw();
		$rows = $this->GetTransfers(null, null, 1000000);
		$transfer = null;
		foreach ($rows as $r)
		{
			if (intval($r['id']) === $transferId)
			{
				$transfer = $r;
				break;
			}
		}
		if ($transfer === null)
		{
			throw new \Exception("El traslado #{$transferId} no existe");
		}

		$stmt = $dbRaw->prepare("
			SELECT i.*, p.name AS product_name, IFNULL(qu.name, 'u') AS qu_name, w.case_number AS waste_case_number
			FROM stock_transfer_items i
			JOIN products p ON p.id = i.product_id
			LEFT JOIN quantity_units qu ON qu.id = p.qu_id_stock
			LEFT JOIN waste_records w ON w.id = i.waste_record_id
			WHERE i.transfer_id = :id
			ORDER BY p.name, i.best_before_date, i.id
		");
		$stmt->execute([':id' => $transferId]);
		$lots = $stmt->fetchAll(\PDO::FETCH_ASSOC);

		// Resumen por producto (la recepción se informa por producto y se reparte FEFO entre sus lotes)
		$products = [];
		foreach ($lots as $l)
		{
			$pid = $l['product_id'];
			if (!isset($products[$pid]))
			{
				$products[$pid] = ['product_id' => $pid, 'product_name' => $l['product_name'], 'qu_name' => $l['qu_name'], 'sent_amount' => 0.0, 'received_amount' => null, 'difference_amount' => null, 'cost' => 0.0];
			}
			$products[$pid]['sent_amount'] += floatval($l['sent_amount']);
			$products[$pid]['cost'] += floatval($l['sent_amount']) * floatval($l['unit_price']);
			if ($l['received_amount'] !== null)
			{
				$products[$pid]['received_amount'] = ($products[$pid]['received_amount'] ?? 0) + floatval($l['received_amount']);
				$products[$pid]['difference_amount'] = ($products[$pid]['difference_amount'] ?? 0) + floatval($l['difference_amount']);
			}
		}

		return ['transfer' => $transfer, 'lots' => $lots, 'products' => array_values($products)];
	}

	/**
	 * Paso 1: envío. $data: from_location_id, to_location_id, items[] {product_id, amount}, notes
	 */
	// Bloqueo de escritura antes de validar el estado: dos solicitudes simultáneas no aplican el movimiento dos veces
	public function CreateTransfer(array $data, int $userId): array
	{
		return DatabaseService::GetInstance()->Locked(fn() => $this->CreateTransferLocked($data, $userId));
	}

	private function CreateTransferLocked(array $data, int $userId): array
	{
		$dbRaw = DatabaseService::GetInstance()->GetDbConnectionRaw();
		$transitId = $this->GetTransitLocationId();
		$from = $this->GetLocation(intval($data['from_location_id'] ?? 0));
		$to = $this->GetLocation(intval($data['to_location_id'] ?? 0));

		if ($from['is_transit'] || $to['is_transit'])
		{
			throw new \Exception('La ubicación de tránsito no puede ser origen ni destino');
		}
		if (empty($from['branch_id']) || empty($to['branch_id']))
		{
			throw new \Exception('Origen y destino deben pertenecer a una sucursal');
		}
		if (!\Grocy\Helpers\DairyAccess::CanSeeBranch(intval($from['branch_id'])))
		{
			throw new \Exception('Solo puede enviar desde sus sucursales asignadas');
		}
		if (intval($from['branch_id']) === intval($to['branch_id']))
		{
			throw new \Exception('Origen y destino son de la misma sucursal: use Transferir de Grocy (movimiento interno)');
		}

		$requested = [];
		foreach ($data['items'] ?? [] as $item)
		{
			$pid = intval($item['product_id'] ?? 0);
			$amount = floatval($item['amount'] ?? 0);
			if ($pid > 0 && $amount > 0)
			{
				$requested[$pid] = ($requested[$pid] ?? 0) + $amount;
			}
		}
		if (empty($requested))
		{
			throw new \Exception('Agregue al menos un producto con cantidad');
		}

		// Lotes FEFO disponibles en el origen, sin lo retenido en cuarentena
		$plan = [];
		foreach ($requested as $pid => $amount)
		{
			if (empty($data['pin_stock_ids'][$pid]))
			{
				DeviceService::AssertNotControlled($pid, 'trasladarlo');
			}
			$lots = $this->GetAvailableLots($pid, $from['id']);
			// Celulares por IMEI: viajan las entradas de esos equipos, no las FEFO
			if (!empty($data['pin_stock_ids'][$pid]))
			{
				$lots = array_values(array_filter($lots, fn($l) => in_array($l['stock_id'], $data['pin_stock_ids'][$pid], true)));
			}
			$available = array_sum(array_column($lots, 'amount'));
			if ($amount > $available + 0.00001)
			{
				$name = $dbRaw->query('SELECT name FROM products WHERE id = ' . intval($pid))->fetchColumn();
				throw new \Exception("Stock insuficiente de {$name} en {$from['name']}: disponible {$available}, solicitado {$amount}");
			}
			$remaining = $amount;
			foreach ($lots as $lot)
			{
				if ($remaining <= 0.00001)
				{
					break;
				}
				$take = min($remaining, floatval($lot['amount']));
				$plan[] = ['product_id' => $pid, 'lot' => $lot, 'amount' => $take];
				$remaining -= $take;
			}
		}

		if (!$dbRaw->inTransaction())
		{
			$dbRaw->beginTransaction();
		}
		try
		{
			$next = intval($dbRaw->query('SELECT IFNULL(MAX(id), 0) + 1 FROM stock_transfers')->fetchColumn());
			$code = 'TRA-' . date('Y') . '-' . str_pad((string) $next, 5, '0', STR_PAD_LEFT);

			$dbRaw->prepare("
				INSERT INTO stock_transfers (transfer_code, from_branch_id, to_branch_id, from_location_id, to_location_id, status, created_by_user_id, notes)
				VALUES (:code, :fb, :tb, :fl, :tl, 'in_transit', :uid, :notes)
			")->execute([
				':code' => $code,
				':fb' => $from['branch_id'],
				':tb' => $to['branch_id'],
				':fl' => $from['id'],
				':tl' => $to['id'],
				':uid' => $userId,
				':notes' => trim($data['notes'] ?? '') ?: null
			]);
			$transferId = intval($dbRaw->lastInsertId());

			$stmtItem = $dbRaw->prepare('
				INSERT INTO stock_transfer_items (transfer_id, product_id, stock_id, best_before_date, unit_price, sent_amount)
				VALUES (:tid, :pid, :sid, :bbd, :price, :amount)
			');
			$stockService = StockService::GetInstance();
			$transactionId = null;
			$totalCost = 0.0;
			foreach ($plan as $p)
			{
				$stockService->TransferProduct($p['product_id'], $p['amount'], $from['id'], $transitId, $p['lot']['stock_id'], $transactionId);
				$stmtItem->execute([
					':tid' => $transferId,
					':pid' => $p['product_id'],
					':sid' => $p['lot']['stock_id'],
					':bbd' => $p['lot']['best_before_date'],
					':price' => floatval($p['lot']['price']),
					':amount' => $p['amount']
				]);
				$totalCost += $p['amount'] * floatval($p['lot']['price']);
			}

			$dbRaw->prepare('UPDATE stock_transfers SET send_transaction_id = :tx, total_sent_cost = :cost WHERE id = :id')
				->execute([':tx' => $transactionId, ':cost' => round($totalCost, 2), ':id' => $transferId]);

			$dbRaw->commit();
		}
		catch (\Exception $e)
		{
			$dbRaw->rollBack();
			throw $e;
		}

		return ['id' => $transferId, 'transfer_code' => $code, 'message' => "Traslado {$code} enviado: $" . number_format($totalCost, 2) . ' en tránsito hacia ' . $to['name']];
	}

	/**
	 * Paso 2: recepción en destino. $data: items[] {product_id, received_amount}, notes
	 * La diferencia (enviado − recibido) se da de baja desde tránsito como merma de traslado (causal M07).
	 */
	// Bloqueo de escritura antes de validar el estado: dos solicitudes simultáneas no aplican el movimiento dos veces
	public function ReceiveTransfer(int $transferId, array $data, int $userId): array
	{
		return DatabaseService::GetInstance()->Locked(fn() => $this->ReceiveTransferLocked($transferId, $data, $userId));
	}

	private function ReceiveTransferLocked(int $transferId, array $data, int $userId): array
	{
		$dbRaw = DatabaseService::GetInstance()->GetDbConnectionRaw();
		$detail = $this->GetTransferDetail($transferId);
		$t = $detail['transfer'];
		if (!in_array($t['status'], ['in_transit', 'partially_received'], true))
		{
			throw new \Exception("El traslado {$t['transfer_code']} no está en tránsito");
		}
		if (!\Grocy\Helpers\DairyAccess::CanSeeBranch(intval($t['to_branch_id'])))
		{
			throw new \Exception('Solo la sucursal de destino puede recibir el traslado');
		}
		// Recepción parcial: suma al destino lo recibido y deja el resto en tránsito (sin merma). La recepción final da de baja el faltante.
		$partial = !empty($data['partial']);
		$transitId = $this->GetTransitLocationId();

		$received = [];
		foreach ($data['items'] ?? [] as $item)
		{
			$received[intval($item['product_id'] ?? 0)] = max(0.0, floatval($item['received_amount'] ?? 0));
		}
		foreach ($detail['products'] as $p)
		{
			if (!array_key_exists($p['product_id'], $received))
			{
				throw new \Exception("Indique la cantidad recibida de {$p['product_name']}");
			}
			$pendingAmount = $p['sent_amount'] - floatval($p['received_amount'] ?? 0);
			if ($received[$p['product_id']] > $pendingAmount + 0.00001)
			{
				throw new \Exception("{$p['product_name']}: se recibe {$received[$p['product_id']]} y quedan {$pendingAmount} en tránsito");
			}
		}
		if ($partial && array_sum($received) <= 0)
		{
			throw new \Exception('Indique al menos una cantidad recibida');
		}

		$reasonId = intval($dbRaw->query("SELECT id FROM waste_reasons WHERE code = 'M07' OR name LIKE '%Traslado%' ORDER BY (code = 'M07') DESC, id LIMIT 1")->fetchColumn());

		if (!$dbRaw->inTransaction())
		{
			$dbRaw->beginTransaction();
		}
		try
		{
			$stockService = StockService::GetInstance();
			$receiveTx = null;
			$differenceCost = 0.0;
			$stillOpen = false;
			$stmtLot = $dbRaw->prepare('UPDATE stock_transfer_items SET received_amount = :rec, difference_amount = :diff, waste_record_id = :wid WHERE id = :id');

			foreach ($detail['products'] as $p)
			{
				$pid = $p['product_id'];
				$toReceive = $received[$pid];
				$lots = array_filter($detail['lots'], fn($l) => $l['product_id'] == $pid);
				$lotResults = [];
				$wasteTx = null;
				$diffQty = 0.0;
				$diffCost = 0.0;

				foreach ($lots as $lot)
				{
					$prevRec = floatval($lot['received_amount'] ?? 0);
					$lotPending = max(0.0, floatval($lot['sent_amount']) - $prevRec);
					$rec = min($toReceive, $lotPending);
					$toReceive -= $rec;
					$diff = $partial ? 0.0 : round($lotPending - $rec, 6);
					if ($rec > 0)
					{
						$stockService->TransferProduct($pid, $rec, $transitId, intval($t['to_location_id']), $lot['stock_id'], $receiveTx);
					}
					if ($diff > 0)
					{
						$stockService->ConsumeProduct($pid, $diff, true, StockService::TRANSACTION_TYPE_CONSUME, $lot['stock_id'], null, $transitId, $wasteTx, false, true);
						$diffQty += $diff;
						$diffCost += $diff * floatval($lot['unit_price']);
					}
					$lotResults[] = [$lot['id'], $prevRec + $rec, $diff, $prevRec + $rec + $diff < floatval($lot['sent_amount']) - 0.000001];
				}

				$wasteId = null;
				if ($diffQty > 0)
				{
					if ($reasonId <= 0)
					{
						throw new \Exception('No existe la causal de merma de traslado (código M07)');
					}
					$prefix = 'MRM-' . date('Ym') . '-';
					// Siguiente correlativo del mes (el conteo de filas choca si hay huecos o casos de otro origen con el mismo prefijo)
					$seq = intval($dbRaw->query("SELECT IFNULL(MAX(CAST(substr(case_number, " . (strlen($prefix) + 1) . ") AS INTEGER)), 0) + 1 FROM waste_records WHERE case_number LIKE '{$prefix}%'")->fetchColumn());
					$quId = intval($dbRaw->query('SELECT qu_id_stock FROM products WHERE id = ' . intval($pid))->fetchColumn());
					$dbRaw->prepare("
						INSERT INTO waste_records (case_number, branch_id, product_id, location_id, amount, qu_id, unit_cost, total_cost, waste_reason_id, notes,
							reported_by_user_id, status, approved_by_user_id, approved_timestamp, transaction_id, inventory_deducted, origin_type, workflow_status)
						VALUES (:case, :bid, :pid, :loc, :amount, :qu, :unit, :total, :reason, :notes,
							:uid, 'approved', :uid2, datetime('now', 'localtime'), :tx, 1, 'transfer', 'action_assigned')
					")->execute([
						':case' => sprintf('%s%04d', $prefix, $seq),
						':bid' => $t['to_branch_id'],
						':pid' => $pid,
						':loc' => $transitId,
						':amount' => $diffQty,
						':qu' => $quId,
						':unit' => round($diffCost / $diffQty, 4),
						':total' => round($diffCost, 2),
						':reason' => $reasonId,
						':notes' => "Diferencia en recepción del traslado {$t['transfer_code']} ({$t['from_branch_name']} → {$t['to_branch_name']}). " . trim($data['notes'] ?? ''),
						':uid' => $userId,
						':uid2' => $userId,
						':tx' => $wasteTx
					]);
					$wasteId = intval($dbRaw->lastInsertId());
					$differenceCost += $diffCost;
				}

				foreach ($lotResults as [$lotId, $rec, $diff, $open])
				{
					$stillOpen = $stillOpen || $open;
					$stmtLot->execute([':rec' => $rec, ':diff' => $partial ? null : $diff, ':wid' => $diff > 0 ? $wasteId : null, ':id' => $lotId]);
				}
			}

			// Cada recepción deja su transacción en el historial; receive_transaction_id conserva la última
			$prevNotes = trim(strval($t['receive_notes'] ?? ''));
			$newNote = trim($data['notes'] ?? '');
			$notes = trim(($prevNotes !== '' ? $prevNotes . ' | ' : '') . ($partial ? 'Recepción parcial ' . date('Y-m-d H:i') . ($newNote !== '' ? ': ' . $newNote : '') : $newNote));
			$dbRaw->prepare("
				UPDATE stock_transfers SET status = :st, received_by_user_id = :uid, received_at = datetime('now', 'localtime'),
					receive_transaction_id = :tx, total_difference_cost = total_difference_cost + :cost, receive_notes = :notes
				WHERE id = :id
			")->execute([':st' => $stillOpen ? 'partially_received' : 'received', ':uid' => $userId, ':tx' => $receiveTx, ':cost' => round($differenceCost, 2), ':notes' => $notes ?: null, ':id' => $transferId]);

			$dbRaw->commit();
		}
		catch (\Exception $e)
		{
			$dbRaw->rollBack();
			throw $e;
		}

		return [
			'id' => $transferId,
			'difference_cost' => round($differenceCost, 2),
			'status' => $stillOpen ? 'partially_received' : 'received',
			'message' => $stillOpen ? "Traslado {$t['transfer_code']}: recepción parcial registrada; el saldo sigue en tránsito" : "Traslado {$t['transfer_code']} recibido" . ($differenceCost > 0 ? ': diferencia de $' . number_format($differenceCost, 2) . ' registrada como merma de traslado' : ' sin diferencias')
		];
	}

	/**
	 * Anula un traslado en tránsito: los lotes vuelven a la ubicación de origen
	 */
	// Bloqueo de escritura antes de validar el estado: dos solicitudes simultáneas no aplican el movimiento dos veces
	public function CancelTransfer(int $transferId, int $userId, string $reason): bool
	{
		return DatabaseService::GetInstance()->Locked(fn() => $this->CancelTransferLocked($transferId, $userId, $reason));
	}

	private function CancelTransferLocked(int $transferId, int $userId, string $reason): bool
	{
		$reason = trim($reason);
		if ($reason === '')
		{
			throw new \Exception('Indique el motivo');
		}
		$dbRaw = DatabaseService::GetInstance()->GetDbConnectionRaw();
		$detail = $this->GetTransferDetail($transferId);
		$t = $detail['transfer'];
		if ($t['status'] !== 'in_transit')
		{
			throw new \Exception("El traslado {$t['transfer_code']} no está en tránsito");
		}
		$transitId = $this->GetTransitLocationId();

		if (!$dbRaw->inTransaction())
		{
			$dbRaw->beginTransaction();
		}
		try
		{
			$tx = null;
			foreach ($detail['lots'] as $lot)
			{
				StockService::GetInstance()->TransferProduct(intval($lot['product_id']), floatval($lot['sent_amount']), $transitId, intval($t['from_location_id']), $lot['stock_id'], $tx);
			}
			$dbRaw->prepare("
				UPDATE stock_transfers SET status = 'cancelled', received_by_user_id = :uid, received_at = datetime('now', 'localtime'), receive_transaction_id = :tx, receive_notes = :notes
				WHERE id = :id
			")->execute([':uid' => $userId, ':tx' => $tx, ':notes' => 'Anulado: ' . $reason, ':id' => $transferId]);
			$dbRaw->commit();
		}
		catch (\Exception $e)
		{
			$dbRaw->rollBack();
			throw $e;
		}
		return true;
	}

	/**
	 * Ubicaciones de sucursal (sin tránsito) y stock vendible por producto en cada una, para el formulario de envío
	 */
	public function GetFormData(): array
	{
		$dbRaw = DatabaseService::GetInstance()->GetDbConnectionRaw();
		return [
			'locations' => $dbRaw->query('
				SELECT l.id, l.name, l.branch_id, b.name AS branch_name
				FROM locations l JOIN branches b ON b.id = l.branch_id
				WHERE l.active = 1 AND IFNULL(l.is_transit, 0) = 0
				ORDER BY b.name, l.name
			')->fetchAll(\PDO::FETCH_ASSOC),
			'stock' => $dbRaw->query("
				SELECT s.location_id, s.product_id, p.name AS product_name, IFNULL(qu.name, 'u') AS qu_name, SUM(s.amount) AS amount
				FROM stock s
				JOIN products p ON p.id = s.product_id
				LEFT JOIN quantity_units qu ON qu.id = p.qu_id_stock
				JOIN locations l ON l.id = s.location_id AND IFNULL(l.is_transit, 0) = 0
				GROUP BY s.location_id, s.product_id
				HAVING SUM(s.amount) > 0
				ORDER BY p.name
			")->fetchAll(\PDO::FETCH_ASSOC)
		];
	}

	private function GetAvailableLots(int $productId, int $locationId): array
	{
		$dbRaw = DatabaseService::GetInstance()->GetDbConnectionRaw();
		$stmt = $dbRaw->prepare('
			SELECT stock_id, MIN(best_before_date) AS best_before_date, MAX(IFNULL(price, 0)) AS price, SUM(amount) AS amount
			FROM stock
			WHERE product_id = :pid AND location_id = :loc
			GROUP BY stock_id
			ORDER BY MIN(best_before_date), MIN(purchased_date), MIN(id)
		');
		$stmt->execute([':pid' => $productId, ':loc' => $locationId]);
		$lots = $stmt->fetchAll(\PDO::FETCH_ASSOC);

		$stmtQ = $dbRaw->prepare("SELECT stock_id, SUM(amount) FROM quarantine_items WHERE product_id = :pid AND location_id = :loc AND status = 'in_quarantine' AND IFNULL(stock_id, '') != '' GROUP BY stock_id");
		$stmtQ->execute([':pid' => $productId, ':loc' => $locationId]);
		$held = $stmtQ->fetchAll(\PDO::FETCH_KEY_PAIR);

		$result = [];
		foreach ($lots as $lot)
		{
			$lot['amount'] = floatval($lot['amount']) - min(floatval($lot['amount']), floatval($held[$lot['stock_id']] ?? 0));
			if ($lot['amount'] > 0.00001)
			{
				$result[] = $lot;
			}
		}
		return $result;
	}

	private function GetLocation(int $id): array
	{
		$stmt = DatabaseService::GetInstance()->GetDbConnectionRaw()->prepare('SELECT id, name, branch_id, IFNULL(is_transit, 0) AS is_transit FROM locations WHERE id = :id AND active = 1');
		$stmt->execute([':id' => $id]);
		$loc = $stmt->fetch(\PDO::FETCH_ASSOC);
		if (!$loc)
		{
			throw new \Exception('Ubicación inválida');
		}
		$loc['is_transit'] = intval($loc['is_transit']) === 1;
		return $loc;
	}

	private function UserNameSql(string $alias): string
	{
		return "IFNULL(NULLIF(TRIM({$alias}.first_name || ' ' || IFNULL({$alias}.last_name, '')), ''), {$alias}.username)";
	}
}
