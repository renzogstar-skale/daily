<?php

namespace Grocy\Services;

/**
 * Kardex valorizado por producto y lote (equivalente a SAP MB51 + MMBE):
 * cada movimiento de stock_log con su saldo acumulado y el documento DAIRY que lo originó
 * (recepción, traslado, venta, merma, ajuste). Por sucursal solo cuentan sus ubicaciones:
 * un traslado es salida en origen y entrada en destino; en la red consolidada el tránsito suma.
 */
class KardexService extends BaseService
{
	const TYPE_LABELS = [
		'purchase' => 'Compra / recepción',
		'consume' => 'Salida',
		'inventory-correction' => 'Ajuste de inventario',
		'transfer_from' => 'Traslado (salida)',
		'transfer_to' => 'Traslado (entrada)',
		'product-opened' => 'Apertura',
		'stock-edit-old' => 'Edición de lote (anterior)',
		'stock-edit-new' => 'Edición de lote (nuevo)',
		'self-production' => 'Producción propia'
	];

	public function GetKardex(int $productId, ?int $branchId = null, ?string $stockId = null, ?string $from = null, ?string $to = null): array
	{
		$dbRaw = DatabaseService::GetInstance()->GetDbConnectionRaw();
		$product = $dbRaw->prepare("SELECT p.id, p.name, IFNULL(qu.name, 'u') AS qu_name, p.active FROM products p LEFT JOIN quantity_units qu ON qu.id = p.qu_id_stock WHERE p.id = :id");
		$product->execute([':id' => $productId]);
		$product = $product->fetch(\PDO::FETCH_ASSOC);
		if (!$product)
		{
			throw new \Exception("El producto #{$productId} no existe");
		}

		$where = 'l.product_id = :pid AND l.undone = 0';
		$params = [':pid' => $productId];
		if ($branchId)
		{
			$where .= ' AND l.location_id IN (SELECT id FROM locations WHERE branch_id = :bid AND IFNULL(is_transit, 0) = 0)';
			$params[':bid'] = $branchId;
		}
		if ($stockId)
		{
			$where .= ' AND l.stock_id = :sid';
			$params[':sid'] = $stockId;
		}
		// La apertura de un envase no cambia la cantidad en stock
		$effect = "CASE WHEN l.transaction_type = 'product-opened' THEN 0 ELSE l.amount END";

		$opening = 0.0;
		$openingValue = 0.0;
		if ($from)
		{
			$stmt = $dbRaw->prepare("SELECT IFNULL(SUM($effect), 0), IFNULL(SUM(($effect) * IFNULL(l.price, 0)), 0) FROM stock_log l WHERE $where AND DATE(l.row_created_timestamp) < :from");
			$stmt->execute($params + [':from' => $from]);
			[$opening, $openingValue] = array_map('floatval', $stmt->fetch(\PDO::FETCH_NUM));
		}

		$sql = "
			SELECT l.id, l.row_created_timestamp, l.transaction_type, l.transaction_id, l.stock_id, l.best_before_date, l.spoiled, l.note,
				$effect AS qty, IFNULL(l.price, 0) AS price, l.location_id, loc.name AS location_name, IFNULL(loc.is_transit, 0) AS is_transit,
				b.name AS branch_name, IFNULL(NULLIF(TRIM(u.first_name || ' ' || IFNULL(u.last_name, '')), ''), u.username) AS user_name
			FROM stock_log l
			LEFT JOIN locations loc ON loc.id = l.location_id
			LEFT JOIN branches b ON b.id = loc.branch_id
			LEFT JOIN users u ON u.id = l.user_id
			WHERE $where" . ($from ? ' AND DATE(l.row_created_timestamp) >= :from' : '') . ($to ? ' AND DATE(l.row_created_timestamp) <= :to' : '') . '
			ORDER BY l.row_created_timestamp, l.id
		';
		$stmt = $dbRaw->prepare($sql);
		$stmt->execute($params + ($from ? [':from' => $from] : []) + ($to ? [':to' => $to] : []));
		$rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

		$docs = $this->ResolveDocuments(array_values(array_unique(array_filter(array_column($rows, 'transaction_id')))));

		$balance = $opening;
		$value = $openingValue;
		$in = 0.0;
		$inValue = 0.0;
		$out = 0.0;
		$outValue = 0.0;
		$wasteQty = 0.0;
		$wasteCost = 0.0;
		foreach ($rows as &$r)
		{
			$qty = floatval($r['qty']);
			$unitPrice = floatval($r['price']);
			$lineVal = round(abs($qty * $unitPrice), 2);
			$balance += $qty;
			$value += $qty * $unitPrice;
			if ($qty >= 0)
			{
				$in += $qty;
				$inValue += $lineVal;
				$r['in_qty'] = $qty;
				$r['in_price'] = $unitPrice;
				$r['in_val'] = $lineVal;
				$r['out_qty'] = null;
				$r['out_price'] = null;
				$r['out_val'] = null;
			}
			else
			{
				$out += -$qty;
				$outValue += $lineVal;
				$r['in_qty'] = null;
				$r['in_price'] = null;
				$r['in_val'] = null;
				$r['out_qty'] = -$qty;
				$r['out_price'] = $unitPrice;
				$r['out_val'] = $lineVal;
			}
			if ($r['spoiled'] == 1)
			{
				$wasteQty += -$qty;
				$wasteCost += -$qty * $unitPrice;
			}
			$r['type_label'] = $r['spoiled'] == 1 ? 'Merma' : (self::TYPE_LABELS[$r['transaction_type']] ?? $r['transaction_type']);
			$r['balance'] = round($balance, 4);
			$r['balance_value'] = round($value, 2);
			$r['document'] = $docs[$r['transaction_id']] ?? null;
			if ($r['document'] === null && $r['transaction_type'] === 'consume' && $r['spoiled'] == 0)
			{
				$r['type_label'] = 'Consumo';
			}
		}
		unset($r);

		return [
			'product' => $product,
			'rows' => $rows,
			'summary' => [
				'opening' => round($opening, 4),
				'opening_value' => round($openingValue, 2),
				'in' => round($in, 4),
				'in_value' => round($inValue, 2),
				'out' => round($out, 4),
				'out_value' => round($outValue, 2),
				'closing' => round($balance, 4),
				'closing_value' => round($value, 2),
				'waste_qty' => round($wasteQty, 4),
				'waste_cost' => round($wasteCost, 2)
			],
			'lots' => $this->GetLots($productId, $branchId)
		];
	}

	/**
	 * Lotes del producto con existencia actual por ubicación (para filtrar el kardex de un lote)
	 */
	public function GetLots(int $productId, ?int $branchId = null): array
	{
		$stmt = DatabaseService::GetInstance()->GetDbConnectionRaw()->prepare("
			SELECT l.stock_id, MIN(l.best_before_date) AS best_before_date, MIN(l.row_created_timestamp) AS first_seen,
				(SELECT dl.lot_number FROM dairy_stock_lots dl WHERE dl.stock_id = l.stock_id) AS lot_number,
				IFNULL((SELECT SUM(s.amount) FROM stock s JOIN locations sl ON sl.id = s.location_id
					WHERE s.stock_id = l.stock_id AND s.product_id = l.product_id" . ($branchId ? ' AND sl.branch_id = :bid2 AND IFNULL(sl.is_transit, 0) = 0' : '') . "), 0) AS current_amount
			FROM stock_log l
			WHERE l.product_id = :pid AND l.undone = 0 AND l.stock_id IS NOT NULL" . ($branchId ? ' AND l.location_id IN (SELECT id FROM locations WHERE branch_id = :bid)' : '') . '
			GROUP BY l.stock_id
			ORDER BY MIN(l.best_before_date) DESC
		');
		$stmt->execute([':pid' => $productId] + ($branchId ? [':bid' => $branchId, ':bid2' => $branchId] : []));
		return $stmt->fetchAll(\PDO::FETCH_ASSOC);
	}

	/**
	 * transaction_id → documento DAIRY (tipo para /document, id y folio). Ventas sin vista de documento enlazan al historial.
	 */
	public function ResolveDocuments(array $transactionIds): array
	{
		if (empty($transactionIds))
		{
			return [];
		}
		$dbRaw = DatabaseService::GetInstance()->GetDbConnectionRaw();
		$in = implode(',', array_fill(0, count($transactionIds), '?'));
		$docs = [];
		$queries = [
			['receipt', "SELECT stock_transaction_id, id, receipt_code FROM procurement_receipts WHERE stock_transaction_id IN ($in)"],
			['transfer', "SELECT send_transaction_id, id, transfer_code FROM stock_transfers WHERE send_transaction_id IN ($in)"],
			['transfer', "SELECT receive_transaction_id, id, transfer_code FROM stock_transfers WHERE receive_transaction_id IN ($in)"],
			['waste', "SELECT transaction_id, id, IFNULL(case_number, 'MRM-' || id) FROM waste_records WHERE transaction_id IN ($in)"],
			['adjustment', "SELECT adjustment_transaction_id, id, 'AJU-' || strftime('%Y', created_timestamp) || '-' || substr('00000' || id, -5) FROM stock_cycle_counts WHERE adjustment_transaction_id IN ($in)"],
			['sale', "SELECT si.transaction_id, s.id, s.sale_number FROM sale_items si JOIN sales s ON s.id = si.sale_id WHERE si.transaction_id IN ($in)"],
			['return', "SELECT return_code, id, return_code FROM customer_returns_exchanges WHERE return_code IN ($in)"],
			['return', "SELECT stock_id, id, return_code FROM customer_returns_exchanges WHERE stock_id IN ($in)"]
		];
		foreach ($queries as [$type, $sql])
		{
			$stmt = $dbRaw->prepare($sql);
			$stmt->execute($transactionIds);
			foreach ($stmt->fetchAll(\PDO::FETCH_NUM) as [$tx, $id, $folio])
			{
				$docs[$tx] ??= ['type' => $type, 'id' => intval($id), 'folio' => $folio];
			}
		}
		return $docs;
	}
}
