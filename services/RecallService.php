<?php

namespace Grocy\Services;

/**
 * Retiro de producto (recall) multisucursal: retiene en cuarentena todos los lotes afectados de la red, cada sucursal
 * confirma que los separó físicamente (cantidad encontrada) y el cierre da de baja el stock como merma vía
 * QualityExpiryService::ResolveQuarantine (stock_log + MRM). Con disposición "devolver al proveedor" abre además el
 * reclamo de crédito (supplier_returns) por el costo real dado de baja.
 */
class RecallService extends BaseService
{
	const SEVERITY = ['class_1' => 'Clase I · riesgo grave para la salud', 'class_2' => 'Clase II · riesgo moderado', 'class_3' => 'Clase III · sin riesgo para la salud'];
	const DISPOSITION = ['destroy' => 'Destruir (merma)', 'return_supplier' => 'Devolver al proveedor (reclamo de crédito)'];

	private function Db(): \PDO
	{
		return DatabaseService::GetInstance()->GetDbConnectionRaw();
	}

	private function Row(string $sql, array $p = [])
	{
		$stmt = $this->Db()->prepare($sql);
		$stmt->execute($p);
		return $stmt->fetch(\PDO::FETCH_ASSOC);
	}

	private function Rows(string $sql, array $p = []): array
	{
		$stmt = $this->Db()->prepare($sql);
		$stmt->execute($p);
		return $stmt->fetchAll(\PDO::FETCH_ASSOC);
	}

	// Lotes afectados con stock (excluye tránsito y lo que ya está retenido en cuarentena)
	public function FindAffectedStock(int $productId, ?string $bbdFrom, ?string $bbdTo): array
	{
		$where = '';
		$p = [':p' => $productId];
		if ($bbdFrom)
		{
			$where .= ' AND s.best_before_date >= :f';
			$p[':f'] = $bbdFrom;
		}
		if ($bbdTo)
		{
			$where .= ' AND s.best_before_date <= :t';
			$p[':t'] = $bbdTo;
		}
		return $this->Rows("SELECT s.stock_id, s.location_id, l.branch_id, IFNULL(b.name, 'Sin sucursal') AS branch_name, l.name AS location_name, s.best_before_date, SUM(s.amount) AS amount, IFNULL(l.is_transit, 0) AS is_transit,
				SUM(s.amount * IFNULL(s.price, 0)) AS value
			FROM stock s JOIN locations l ON l.id = s.location_id LEFT JOIN branches b ON b.id = l.branch_id
			WHERE s.product_id = :p$where
				AND s.stock_id NOT IN (SELECT stock_id FROM quarantine_items WHERE status = 'in_quarantine' AND product_id = :p)
			GROUP BY s.stock_id, s.location_id HAVING SUM(s.amount) > 0 ORDER BY l.branch_id, s.best_before_date", $p);
	}

	public function CreateRecall(array $d, int $userId): array
	{
		$productId = intval($d['product_id'] ?? 0);
		$reason = trim($d['reason'] ?? '');
		$severity = $d['severity'] ?? 'class_2';
		$disposition = $d['disposition'] ?? 'destroy';
		$from = !empty($d['best_before_from']) ? $d['best_before_from'] : null;
		$to = !empty($d['best_before_to']) ? $d['best_before_to'] : null;
		if ($productId <= 0)
		{
			throw new \Exception('Seleccione el producto a retirar');
		}
		if (mb_strlen($reason) < 5)
		{
			throw new \Exception('Indique el motivo del retiro (aviso del proveedor, autoridad sanitaria, hallazgo de calidad)');
		}
		if (!isset(self::SEVERITY[$severity]) || !isset(self::DISPOSITION[$disposition]))
		{
			throw new \Exception('Clase o disposición de retiro no válida');
		}
		if ($from && $to && $from > $to)
		{
			throw new \Exception('El rango de vencimiento está invertido');
		}
		$product = $this->Row('SELECT id, name, shopping_location_id FROM products WHERE id = :id', [':id' => $productId]);
		if (!$product)
		{
			throw new \Exception('Producto no encontrado');
		}
		$supplierId = !empty($d['supplier_id']) ? intval($d['supplier_id']) : ($product['shopping_location_id'] ? intval($product['shopping_location_id']) : null);
		if ($disposition === 'return_supplier' && !$supplierId)
		{
			throw new \Exception('Para devolver al proveedor indique el proveedor (el producto no tiene uno por defecto)');
		}

		$affected = $this->FindAffectedStock($productId, $from, $to);
		$inStore = array_values(array_filter($affected, fn($a) => !intval($a['is_transit'])));
		$inTransit = array_values(array_filter($affected, fn($a) => intval($a['is_transit'])));
		if (count($inStore) === 0)
		{
			throw new \Exception('No hay stock libre de ese producto' . ($from || $to ? ' en el rango de vencimiento indicado' : '') . (count($inTransit) ? ' (solo en tránsito: recíbalo primero)' : ''));
		}

		$db = $this->Db();
		$next = intval($db->query('SELECT IFNULL(MAX(id), 0) + 1 FROM product_recalls')->fetchColumn());
		$code = 'RET-' . date('Y') . '-' . str_pad((string) $next, 5, '0', STR_PAD_LEFT);
		$quality = QualityExpiryService::GetInstance();

		$db->beginTransaction();
		try
		{
			$db->prepare("INSERT INTO product_recalls (recall_code, product_id, supplier_id, best_before_from, best_before_to, reason, severity, disposition, status, created_by_user_id, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'open', ?, ?)")
				->execute([$code, $productId, $supplierId, $from, $to, $reason, $severity, $disposition, $userId, date('Y-m-d H:i:s')]);
			$recallId = intval($db->lastInsertId());
			$insItem = $db->prepare("INSERT INTO product_recall_items (recall_id, branch_id, location_id, stock_id, best_before_date, amount, quarantine_id, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'quarantined')");
			foreach ($inStore as $a)
			{
				$qid = $quality->MoveToQuarantine(['product_id' => $productId, 'stock_id' => $a['stock_id'], 'location_id' => $a['location_id'], 'branch_id' => $a['branch_id'] ?: 1, 'amount' => $a['amount'], 'reason' => "Retiro $code: $reason"]);
				$insItem->execute([$recallId, $a['branch_id'], $a['location_id'], $a['stock_id'], $a['best_before_date'], $a['amount'], $qid]);
			}
			$db->commit();
		}
		catch (\Throwable $e)
		{
			$db->rollBack();
			throw $e;
		}

		return [
			'id' => $recallId, 'recall_code' => $code, 'lots' => count($inStore),
			'branches' => count(array_unique(array_column($inStore, 'branch_id'))),
			'amount' => round(array_sum(array_column($inStore, 'amount')), 2),
			'warnings' => count($inTransit) ? [['message' => count($inTransit) . ' lote(s) en tránsito: se retendrán al recibirse; vuelva a revisar el retiro']] : [],
		];
	}

	public function ConfirmItem(int $itemId, $foundAmount, ?string $notes, int $userId): array
	{
		$item = $this->Row('SELECT i.*, r.status AS recall_status FROM product_recall_items i JOIN product_recalls r ON r.id = i.recall_id WHERE i.id = :id', [':id' => $itemId]);
		if (!$item)
		{
			throw new \Exception('Lote del retiro no encontrado');
		}
		if ($item['recall_status'] !== 'open' || $item['status'] !== 'quarantined')
		{
			throw new \Exception('Este lote ya fue confirmado o el retiro está cerrado');
		}
		if ($foundAmount === null || $foundAmount === '' || !is_numeric($foundAmount) || floatval($foundAmount) < 0)
		{
			throw new \Exception('Indique la cantidad física encontrada y separada (cero o mayor)');
		}
		$found = floatval($foundAmount);
		$diff = round($found - floatval($item['amount']), 4);
		if (abs($diff) > 0.0001 && mb_strlen(trim($notes ?? '')) < 3)
		{
			throw new \Exception('La cantidad encontrada difiere del sistema: explique la diferencia');
		}
		$upd = $this->Db()->prepare("UPDATE product_recall_items SET status = 'confirmed', found_amount = ?, confirmed_by_user_id = ?, confirmed_at = ?, notes = ? WHERE id = ? AND status = 'quarantined'");
		$upd->execute([$found, $userId, date('Y-m-d H:i:s'), $notes, $itemId]);
		if ($upd->rowCount() !== 1)
		{
			throw new \Exception('Este lote ya fue confirmado por otro usuario');
		}
		return ['success' => true, 'difference' => $diff, 'message' => abs($diff) < 0.0001 ? 'Lote separado y confirmado' : 'Confirmado con diferencia de ' . $diff . ' u.'];
	}

	public function CloseRecall(int $recallId, ?string $notes, int $userId): array
	{
		$recall = $this->Row('SELECT * FROM product_recalls WHERE id = :id', [':id' => $recallId]);
		if (!$recall || $recall['status'] !== 'open')
		{
			throw new \Exception('El retiro no existe o ya está cerrado');
		}
		$items = $this->Rows('SELECT * FROM product_recall_items WHERE recall_id = :id', [':id' => $recallId]);
		$pending = array_filter($items, fn($i) => $i['status'] === 'quarantined');
		if (count($pending) > 0)
		{
			throw new \Exception(count($pending) . ' lote(s) sin confirmar por la sucursal: no se puede cerrar el retiro');
		}

		$quality = QualityExpiryService::GetInstance();
		$db = $this->Db();
		$totalCost = 0.0;
		$totalAmount = 0.0;
		foreach ($items as $i)
		{
			if ($i['status'] === 'confirmed')
			{
				$q = $this->Row('SELECT status, waste_record_id FROM quarantine_items WHERE id = :id', [':id' => $i['quarantine_id']]);
				if ($q && $q['status'] === 'in_quarantine')
				{
					$quality->ResolveQuarantine(intval($i['quarantine_id']), 'discarded_as_waste', "Retiro {$recall['recall_code']}: {$recall['reason']}" . ($i['found_amount'] !== null && abs(floatval($i['found_amount']) - floatval($i['amount'])) > 0.0001 ? " · encontrado {$i['found_amount']} de {$i['amount']}" : ''), $userId);
					$q = $this->Row('SELECT waste_record_id FROM quarantine_items WHERE id = :id', [':id' => $i['quarantine_id']]);
				}
				$db->prepare("UPDATE product_recall_items SET status = 'disposed', waste_record_id = ? WHERE id = ?")->execute([$q['waste_record_id'] ?? null, $i['id']]);
				$i['waste_record_id'] = $q['waste_record_id'] ?? null;
			}
			if (!empty($i['waste_record_id']))
			{
				$w = $this->Row('SELECT amount, total_cost FROM waste_records WHERE id = :id', [':id' => $i['waste_record_id']]);
				$totalCost += floatval($w['total_cost'] ?? 0);
				$totalAmount += floatval($w['amount'] ?? 0);
			}
		}

		$returnId = null;
		if ($recall['disposition'] === 'return_supplier' && $totalAmount > 0)
		{
			$quId = intval($db->query('SELECT qu_id_stock FROM products WHERE id = ' . intval($recall['product_id']))->fetchColumn());
			$db->prepare("INSERT INTO supplier_returns (supplier_id, branch_id, product_id, case_number, amount, qu_id, unit_cost, total_loss_cost, return_reason, incident_type, credit_requested_amount, credit_recovered_amount, loss_unrecovered_amount, status, notes, user_id, created_timestamp)
				VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'recall', ?, 0, ?, 'pending_credit', ?, ?, ?)")
				->execute([$recall['supplier_id'], intval($items[0]['branch_id'] ?? 1) ?: 1, $recall['product_id'], $recall['recall_code'], $totalAmount, $quId, round($totalCost / $totalAmount, 4), round($totalCost, 2), 'Retiro de producto: ' . $recall['reason'], round($totalCost, 2), round($totalCost, 2), $notes, $userId, date('Y-m-d H:i:s')]);
			$returnId = intval($db->lastInsertId());
		}
		$db->prepare("UPDATE product_recalls SET status = 'closed', closed_by_user_id = ?, closed_at = ?, close_notes = ? WHERE id = ?")->execute([$userId, date('Y-m-d H:i:s'), $notes, $recallId]);

		return ['success' => true, 'written_off_amount' => round($totalAmount, 2), 'written_off_cost' => round($totalCost, 2), 'supplier_return_id' => $returnId,
			'message' => 'Retiro cerrado: ' . round($totalAmount, 2) . ' u. dadas de baja ($' . number_format($totalCost, 2) . ')' . ($returnId ? '; reclamo de crédito abierto' : '')];
	}

	public function CancelRecall(int $recallId, string $reason, int $userId): void
	{
		$recall = $this->Row('SELECT * FROM product_recalls WHERE id = :id', [':id' => $recallId]);
		if (!$recall || $recall['status'] !== 'open')
		{
			throw new \Exception('El retiro no existe o ya está cerrado');
		}
		if (mb_strlen(trim($reason)) < 5)
		{
			throw new \Exception('Indique por qué se anula el retiro (p. ej. el proveedor lo revocó)');
		}
		$quality = QualityExpiryService::GetInstance();
		foreach ($this->Rows("SELECT quarantine_id FROM product_recall_items WHERE recall_id = :id", [':id' => $recallId]) as $i)
		{
			$q = $this->Row('SELECT status FROM quarantine_items WHERE id = :id', [':id' => $i['quarantine_id']]);
			if ($q && $q['status'] === 'in_quarantine')
			{
				$quality->ResolveQuarantine(intval($i['quarantine_id']), 'released', 'Retiro anulado: ' . $reason, $userId);
			}
		}
		$this->Db()->prepare("UPDATE product_recalls SET status = 'cancelled', closed_by_user_id = ?, closed_at = ?, close_notes = ? WHERE id = ?")->execute([$userId, date('Y-m-d H:i:s'), $reason, $recallId]);
	}

	public function GetRecalls(?array $allowedBranches = null): array
	{
		$rows = $this->Rows("SELECT r.*, p.name AS product_name, sl.name AS supplier_name,
				(SELECT COUNT(*) FROM product_recall_items i WHERE i.recall_id = r.id) AS lots,
				(SELECT COUNT(*) FROM product_recall_items i WHERE i.recall_id = r.id AND i.status = 'quarantined') AS pending_lots,
				(SELECT COUNT(DISTINCT i.branch_id) FROM product_recall_items i WHERE i.recall_id = r.id) AS branches,
				(SELECT IFNULL(SUM(i.amount), 0) FROM product_recall_items i WHERE i.recall_id = r.id) AS amount
			FROM product_recalls r LEFT JOIN products p ON p.id = r.product_id LEFT JOIN shopping_locations sl ON sl.id = r.supplier_id
			ORDER BY (r.status = 'open') DESC, r.id DESC");
		if ($allowedBranches !== null)
		{
			$rows = array_values(array_filter($rows, function ($r) use ($allowedBranches) {
				$b = $this->Db()->query('SELECT DISTINCT branch_id FROM product_recall_items WHERE recall_id = ' . intval($r['id']))->fetchAll(\PDO::FETCH_COLUMN);
				return count(array_intersect(array_map('intval', $b), $allowedBranches)) > 0;
			}));
		}
		return $rows;
	}

	public function GetRecall(int $recallId): array
	{
		$recall = $this->Row('SELECT r.*, p.name AS product_name, sl.name AS supplier_name FROM product_recalls r LEFT JOIN products p ON p.id = r.product_id LEFT JOIN shopping_locations sl ON sl.id = r.supplier_id WHERE r.id = :id', [':id' => $recallId]);
		if (!$recall)
		{
			throw new \Exception('Retiro no encontrado');
		}
		$recall['items'] = $this->Rows("SELECT i.*, IFNULL(b.name, 'Sin sucursal') AS branch_name, l.name AS location_name, w.case_number AS waste_case,
				IFNULL(NULLIF(TRIM(IFNULL(u.first_name, '') || ' ' || IFNULL(u.last_name, '')), ''), u.username) AS confirmed_by
			FROM product_recall_items i LEFT JOIN branches b ON b.id = i.branch_id LEFT JOIN locations l ON l.id = i.location_id LEFT JOIN users u ON u.id = i.confirmed_by_user_id LEFT JOIN waste_records w ON w.id = i.waste_record_id
			WHERE i.recall_id = :id ORDER BY i.branch_id, i.best_before_date", [':id' => $recallId]);
		$recall['in_transit'] = array_values(array_filter($this->FindAffectedStock(intval($recall['product_id']), $recall['best_before_from'], $recall['best_before_to']), fn($a) => intval($a['is_transit'])));
		return $recall;
	}
}
