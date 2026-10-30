<?php

namespace Grocy\Services;

use Grocy\Helpers\DairyAccess;

/**
 * Trazabilidad de punta a punta (Etapa 23). La unidad trazable es la entrada de stock de Grocy (stock_id), que se conserva
 * en traslados, divisiones y ventas; el lote del proveedor se liga a ella en la recepción (dairy_stock_lots).
 *  - Hacia atrás: desde una venta, pedido, traslado o recepción → lotes → proveedor, OC, recepción, temperatura.
 *  - Hacia adelante: desde un lote/serie/GS1 → ubicaciones y sucursales recorridas → clientes, mermas, ajustes, vencido, saldo.
 *  - Conciliación: entradas − salidas por destino = saldo actual en ubicaciones; cualquier diferencia o salida sin
 *    documento se informa como alerta (no se oculta).
 */
class TraceabilityService extends BaseService
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
		return $this->Rows($sql, $p)[0] ?? null;
	}

	private function In(array $values): string
	{
		return implode(',', array_fill(0, max(1, count($values)), '?'));
	}

	/**
	 * Liga lote/serie a las entradas creadas por una transacción de compra. $map: product_id => ['lot' => ?, 'serial' => ?].
	 * Upsert: una recepción por escáner puede aportar la serie después de que la recepción registró el lote.
	 */
	public function RegisterLots(string $transactionId, array $map, int $userId): void
	{
		if (count($map) === 0)
		{
			return;
		}
		$entries = $this->Rows("SELECT stock_id, product_id FROM stock_log WHERE transaction_id = :t AND transaction_type = 'purchase' AND undone = 0 ORDER BY id", [':t' => $transactionId]);
		$ins = $this->Db()->prepare('INSERT INTO dairy_stock_lots (stock_id, product_id, lot_number, serial_number, created_by_user_id, created_at) VALUES (:s, :p, :l, :n, :u, :c)
			ON CONFLICT(stock_id) DO UPDATE SET lot_number = COALESCE(excluded.lot_number, lot_number), serial_number = COALESCE(excluded.serial_number, serial_number)');
		foreach ($entries as $e)
		{
			$m = $map[intval($e['product_id'])] ?? null;
			if (!$m || (empty($m['lot']) && empty($m['serial'])))
			{
				continue;
			}
			$ins->execute([':s' => $e['stock_id'], ':p' => $e['product_id'], ':l' => $m['lot'] ?: null, ':n' => $m['serial'] ?? null, ':u' => $userId, ':c' => date('Y-m-d H:i:s')]);
		}
	}

	/* ---------------------------------------------------------------- Búsqueda */

	/** Resuelve la consulta a grupos (producto + conjunto de stock_id) y traza cada uno. */
	public function Trace(string $query, ?int $productId = null): array
	{
		$q = trim($query);
		if ($q === '' && !$productId)
		{
			throw new \Exception('Indique un lote, serie, código GS1, venta (VTA-), recepción (REC-), pedido (PED-) o traslado (TRA-)');
		}
		$kind = 'lot';
		$stockIds = [];
		$upper = strtoupper($q);
		if (preg_match('/^VTA-/', $upper))
		{
			$kind = 'sale';
			$stockIds = $this->StockIdsByTransactions(array_column($this->Rows('SELECT si.transaction_id FROM sale_items si JOIN sales s ON s.id = si.sale_id WHERE upper(s.sale_number) = :c AND si.transaction_id IS NOT NULL', [':c' => $upper]), 'transaction_id'));
		}
		elseif (preg_match('/^PED-/', $upper))
		{
			$kind = 'customer_order';
			$stockIds = array_column($this->Rows('SELECT DISTINCT cp.stock_id, cp.product_id FROM customer_order_picks cp JOIN customer_orders o ON o.id = cp.order_id WHERE upper(o.order_code) = :c', [':c' => $upper]), 'product_id', 'stock_id');
		}
		elseif (preg_match('/^REC-/', $upper))
		{
			$kind = 'receipt';
			$stockIds = $this->StockIdsByTransactions(array_column($this->Rows('SELECT stock_transaction_id FROM procurement_receipts WHERE upper(receipt_code) = :c AND stock_transaction_id IS NOT NULL', [':c' => $upper]), 'stock_transaction_id'));
		}
		elseif (preg_match('/^TRA-/', $upper))
		{
			$kind = 'transfer';
			$stockIds = array_column($this->Rows('SELECT DISTINCT ti.stock_id, ti.product_id FROM stock_transfer_items ti JOIN stock_transfers t ON t.id = ti.transfer_id WHERE upper(t.transfer_code) = :c AND ti.stock_id IS NOT NULL', [':c' => $upper]), 'product_id', 'stock_id');
		}
		elseif ($q !== '' && $this->Row('SELECT 1 AS x FROM stock_log WHERE stock_id = :s LIMIT 1', [':s' => $q]))
		{
			$kind = 'stock_entry';
			$stockIds = array_column($this->Rows('SELECT DISTINCT stock_id, product_id FROM stock_log WHERE stock_id = :s', [':s' => $q]), 'product_id', 'stock_id');
		}
		else
		{
			$lot = $q;
			$serial = null;
			$gtinProduct = null;
			try
			{
				$parsed = ScanService::GetInstance()->ParseCode($q);
				if ($parsed['format'] === 'gs1')
				{
					$lot = $parsed['lot'] ?? '';
					$serial = $parsed['serial'];
					$gtinProduct = $parsed['gtin'] ? (ScanService::GetInstance()->FindProductByCode($parsed['gtin'])['id'] ?? null) : null;
					$kind = 'gs1';
				}
			}
			catch (\Exception $e)
			{
				// No es GS1 válido: se busca como texto de lote
			}
			$pid = $productId ?: $gtinProduct;
			$rows = $this->Rows('SELECT stock_id, product_id FROM dairy_stock_lots WHERE (' . ($serial ? 'serial_number = :n' : 'lot_number = :l OR serial_number = :l') . ')' . ($pid ? ' AND product_id = :p' : ''),
				($serial ? [':n' => $serial] : [':l' => $lot]) + ($pid ? [':p' => $pid] : []));
			$stockIds = array_column($rows, 'product_id', 'stock_id');
			if (count($stockIds) === 0 && $productId && $q === '')
			{
				throw new \Exception('Seleccione un lote del producto');
			}
		}
		if (count($stockIds) === 0)
		{
			return ['query' => $q, 'kind' => $kind, 'groups' => [], 'message' => 'Sin coincidencias. Los lotes del proveedor se registran al recibir (OC o escáner); para entradas antiguas use el identificador de entrada del Kardex.'];
		}

		// Un grupo por producto y lote (las entradas sin lote del proveedor se trazan por separado)
		$lots = array_column($this->Rows('SELECT stock_id, lot_number FROM dairy_stock_lots WHERE stock_id IN (' . $this->In(array_keys($stockIds)) . ')', array_map('strval', array_keys($stockIds))), 'lot_number', 'stock_id');
		$groups = [];
		foreach ($stockIds as $sid => $pid)
		{
			$key = $pid . '|' . ($lots[$sid] ?? ('#' . $sid));
			$groups[$key]['product_id'] = intval($pid);
			$groups[$key]['lot_number'] = $lots[$sid] ?? null;
			$groups[$key]['stock_ids'][] = (string) $sid;
		}
		$out = [];
		foreach ($groups as $g)
		{
			$out[] = $this->TraceEntries($g['product_id'], $g['stock_ids'], $g['lot_number']);
		}
		return ['query' => $q, 'kind' => $kind, 'groups' => $out];
	}

	private function StockIdsByTransactions(array $txs): array
	{
		$txs = array_values(array_unique(array_filter($txs)));
		if (count($txs) === 0)
		{
			return [];
		}
		return array_column($this->Rows('SELECT DISTINCT stock_id, product_id FROM stock_log WHERE transaction_id IN (' . $this->In($txs) . ') AND stock_id IS NOT NULL', $txs), 'product_id', 'stock_id');
	}

	/* ---------------------------------------------------------------- Traza de un lote */

	public function TraceEntries(int $productId, array $stockIds, ?string $lotNumber = null): array
	{
		$in = $this->In($stockIds);
		$product = $this->Row('SELECT p.id, p.name, p.sku, IFNULL(qu.name, \'u\') AS unit, p.storage_class FROM products p LEFT JOIN quantity_units qu ON qu.id = p.qu_id_stock WHERE p.id = ?', [$productId]);
		$moves = $this->Rows("SELECT l.id, l.row_created_timestamp AS at, l.transaction_type, l.transaction_id, l.stock_id, l.best_before_date, l.spoiled, l.note,
				CASE WHEN l.transaction_type = 'product-opened' THEN 0 ELSE l.amount END AS qty, IFNULL(l.price, 0) AS price, l.location_id, loc.name AS location_name,
				IFNULL(loc.is_transit, 0) AS is_transit, loc.branch_id, b.name AS branch_name, l.shopping_location_id, sl.name AS supplier_name,
				IFNULL(NULLIF(TRIM(u.first_name || ' ' || IFNULL(u.last_name, '')), ''), u.username) AS user_name
			FROM stock_log l LEFT JOIN locations loc ON loc.id = l.location_id LEFT JOIN branches b ON b.id = loc.branch_id
			LEFT JOIN shopping_locations sl ON sl.id = l.shopping_location_id LEFT JOIN users u ON u.id = l.user_id
			WHERE l.product_id = ? AND l.undone = 0 AND l.stock_id IN ($in) ORDER BY l.row_created_timestamp, l.id", array_merge([$productId], $stockIds));
		$docs = KardexService::GetInstance()->ResolveDocuments(array_values(array_unique(array_filter(array_column($moves, 'transaction_id')))));
		$saleInfo = $this->SaleInfo(array_column($moves, 'transaction_id'));
		$scanDocs = $this->ScanDocs(array_column($moves, 'transaction_id'));

		$events = [];
		$sum = ['received' => 0.0, 'received_value' => 0.0, 'sold' => 0.0, 'wasted' => 0.0, 'adjust_in' => 0.0, 'adjust_out' => 0.0, 'consumed_undocumented' => 0.0, 'other' => 0.0];
		$customers = [];
		$wasteCases = [];
		$branchesReached = [];
		$origin = [];
		foreach ($moves as $m)
		{
			$qty = floatval($m['qty']);
			$doc = $docs[$m['transaction_id']] ?? ($scanDocs[$m['transaction_id']] ?? null);
			$visible = $m['branch_id'] === null || DairyAccess::CanSeeBranch(intval($m['branch_id']));
			$label = KardexService::TYPE_LABELS[$m['transaction_type']] ?? $m['transaction_type'];
			$extra = null;
			$cat = 'other';
			if ($m['transaction_type'] === 'purchase')
			{
				$cat = 'received';
				$sum['received'] += $qty;
				$sum['received_value'] += $qty * floatval($m['price']);
				$origin[] = $m + ['document' => $doc];
			}
			elseif (intval($m['spoiled']) === 1)
			{
				$cat = 'wasted';
				$label = 'Merma';
				$sum['wasted'] += -$qty;
				if ($doc)
				{
					$wasteCases[$doc['folio']] = ($wasteCases[$doc['folio']] ?? 0) - $qty;
				}
			}
			elseif ($m['transaction_type'] === 'consume' && isset($saleInfo[$m['transaction_id']]))
			{
				$cat = 'sold';
				$label = 'Venta';
				$sum['sold'] += -$qty;
				$si = $saleInfo[$m['transaction_id']];
				$extra = $si['customer_name'] . ($si['order_code'] ? ' · pedido ' . $si['order_code'] : '');
				$c = $si['customer_name'] ?? 'Cliente';
				$customers[$c] ??= ['customer_name' => $c, 'tax_id' => $si['tax_id'], 'qty' => 0, 'sales' => [], 'last' => null];
				$customers[$c]['qty'] += -$qty;
				$customers[$c]['sales'][$si['sale_number']] = $si['sale_id'];
				$customers[$c]['last'] = substr($m['at'], 0, 10);
			}
			elseif ($m['transaction_type'] === 'inventory-correction' || str_starts_with($m['transaction_type'], 'stock-edit'))
			{
				$cat = $qty >= 0 ? 'adjust_in' : 'adjust_out';
				$sum[$cat] += abs($qty);
			}
			elseif ($m['transaction_type'] === 'consume')
			{
				$cat = 'consumed_undocumented';
				$label = 'Salida sin documento';
				$sum['consumed_undocumented'] += -$qty;
			}
			if (in_array($m['transaction_type'], ['transfer_to', 'purchase']) && $qty > 0 && !$m['is_transit'] && $m['branch_name'])
			{
				$branchesReached[$m['branch_name']] = true;
			}
			$events[] = [
				'at' => $m['at'], 'kind' => 'move', 'category' => $cat, 'label' => $label, 'qty' => $qty, 'price' => floatval($m['price']),
				'where' => $visible ? trim(($m['branch_name'] ? $m['branch_name'] . ' · ' : '') . ($m['location_name'] ?? '')) : 'Otra sucursal',
				'transit' => intval($m['is_transit']) === 1, 'user' => $m['user_name'], 'document' => $doc, 'detail' => $extra, 'stock_id' => $m['stock_id'],
			];
		}

		// Eventos sin movimiento de stock: cuarentena, retiro, merma pendiente y vencimiento
		foreach ($this->Rows("SELECT q.*, b.name AS branch_name FROM quarantine_items q LEFT JOIN branches b ON b.id = q.branch_id WHERE q.product_id = ? AND q.stock_id IN ($in)", array_merge([$productId], $stockIds)) as $q)
		{
			$events[] = ['at' => $q['entered_timestamp'], 'kind' => 'event', 'category' => 'quarantine', 'label' => 'Cuarentena', 'qty' => floatval($q['amount']), 'where' => $q['branch_name'],
				'detail' => $q['reason'] . ' · ' . ($q['status'] === 'in_quarantine' ? 'retenido' : 'resuelto ' . substr((string) $q['resolved_timestamp'], 0, 10)), 'document' => ['type' => 'quarantine', 'id' => intval($q['id']), 'folio' => 'CUA-' . $q['id']]];
		}
		foreach ($this->Rows("SELECT ri.*, r.recall_code, r.reason, r.status AS recall_status, r.created_at FROM product_recall_items ri JOIN product_recalls r ON r.id = ri.recall_id WHERE r.product_id = ? AND ri.stock_id IN ($in)", array_merge([$productId], $stockIds)) as $r)
		{
			$events[] = ['at' => $r['created_at'], 'kind' => 'event', 'category' => 'recall', 'label' => 'Retiro de producto', 'qty' => floatval($r['amount']), 'where' => null,
				'detail' => $r['recall_code'] . ' · ' . $r['reason'] . ' · ' . $r['status'], 'document' => null, 'recall_open' => $r['recall_status'] === 'open'];
		}
		foreach ($this->Rows("SELECT id, case_number, amount, status, created_timestamp FROM waste_records WHERE product_id = ? AND stock_id IN ($in) AND IFNULL(inventory_deducted, 0) = 0 AND status <> 'rejected'", array_merge([$productId], $stockIds)) as $w)
		{
			$events[] = ['at' => $w['created_timestamp'], 'kind' => 'event', 'category' => 'waste_pending', 'label' => 'Merma por aprobar', 'qty' => floatval($w['amount']), 'where' => null,
				'detail' => 'aún no descuenta stock', 'document' => ['type' => 'waste', 'id' => intval($w['id']), 'folio' => $w['case_number']]];
		}

		$current = $this->Rows("SELECT s.location_id, l.name AS location_name, IFNULL(l.is_transit, 0) AS is_transit, b.name AS branch_name, l.branch_id, SUM(s.amount) AS amount, MIN(s.best_before_date) AS best_before_date
			FROM stock s JOIN locations l ON l.id = s.location_id LEFT JOIN branches b ON b.id = l.branch_id WHERE s.product_id = ? AND s.stock_id IN ($in) GROUP BY s.location_id", array_merge([$productId], $stockIds));
		$onHand = array_sum(array_column($current, 'amount'));
		$bbd = $this->Row("SELECT MIN(best_before_date) AS d FROM stock_log WHERE product_id = ? AND stock_id IN ($in) AND best_before_date IS NOT NULL", array_merge([$productId], $stockIds))['d'] ?? null;
		$expired = $bbd && $bbd !== '2999-12-31' && $bbd < date('Y-m-d');
		if ($bbd && $bbd !== '2999-12-31' && $bbd <= date('Y-m-d'))
		{
			$events[] = ['at' => $bbd . ' 23:59:59', 'kind' => 'event', 'category' => 'expiry', 'label' => 'Vencimiento', 'qty' => $onHand, 'where' => null,
				'detail' => $onHand > 0 ? 'quedan ' . round($onHand, 2) . ' u. en stock vencidas' : 'sin stock al vencer', 'document' => null];
		}
		usort($events, fn($a, $b) => strcmp($a['at'], $b['at']));

		$net = array_sum(array_map(fn($m) => floatval($m['qty']), $moves));
		$diff = round($onHand - $net, 4);
		$quarantined = array_sum(array_map(fn($e) => $e['category'] === 'quarantine' && str_contains($e['detail'], 'retenido') ? $e['qty'] : 0, $events));

		$alerts = [];
		if (count($origin) === 0)
		{
			$alerts[] = ['danger', 'Sin entrada de origen registrada para este lote (movimientos previos al historial o datos importados)'];
		}
		foreach ($origin as $o)
		{
			if (!$o['document'])
			{
				$alerts[] = ['warning', 'Entrada del ' . substr($o['at'], 0, 10) . ' sin recepción contra OC (compra directa): sin inspección de temperatura ni vida útil registrada'];
			}
		}
		if ($sum['consumed_undocumented'] > 0)
		{
			$alerts[] = ['danger', round($sum['consumed_undocumented'], 2) . ' u. salieron sin documento (ni venta, ni merma, ni ajuste)'];
		}
		if ($expired && $onHand > 0)
		{
			$alerts[] = ['danger', 'Lote vencido el ' . $bbd . ' con ' . round($onHand, 2) . ' u. aún en stock: registrar merma o retirar'];
		}
		if (array_filter($events, fn($e) => !empty($e['recall_open'])))
		{
			$alerts[] = ['danger', 'Lote incluido en un retiro de producto abierto'];
		}
		if (abs($diff) > 0.0001)
		{
			$alerts[] = ['danger', "No cuadra: saldo en ubicaciones {$onHand} vs movimientos {$net} (diferencia {$diff})"];
		}

		$lotInfo = $this->Row("SELECT GROUP_CONCAT(DISTINCT lot_number) AS lots, GROUP_CONCAT(DISTINCT serial_number) AS serials FROM dairy_stock_lots WHERE stock_id IN ($in)", $stockIds);
		return [
			'product' => $product,
			'lot_number' => $lotNumber ?? ($lotInfo['lots'] ?? null),
			'serials' => $lotInfo['serials'] ?? null,
			'stock_ids' => $stockIds,
			'best_before_date' => $bbd === '2999-12-31' ? null : $bbd,
			'expired' => $expired,
			'origin' => array_map(fn($o) => $this->OriginView($o), $origin),
			'events' => $events,
			'summary' => array_map(fn($v) => round($v, 4), $sum) + ['on_hand' => round($onHand, 4), 'quarantined' => round($quarantined, 4), 'net_movements' => round($net, 4), 'difference' => $diff, 'balanced' => abs($diff) <= 0.0001],
			'customers' => array_values(array_map(fn($c) => array_merge($c, ['sales' => array_map(fn($k, $v) => ['sale_number' => $k, 'sale_id' => $v], array_keys($c['sales']), $c['sales'])]), $customers)),
			'waste_cases' => $wasteCases,
			'branches' => array_keys($branchesReached),
			'current' => array_map(fn($c) => $c + ['visible' => $c['branch_id'] === null || DairyAccess::CanSeeBranch(intval($c['branch_id']))], $current),
			'alerts' => $alerts,
		];
	}

	private function OriginView(array $o): array
	{
		$r = $o['document'] && $o['document']['type'] === 'receipt' ? $this->Row('SELECT r.receipt_code, r.received_at, r.temperature_on_arrival, r.supplier_document, po.order_code, po.id AS order_id, sl.name AS supplier_name,
				IFNULL(NULLIF(TRIM(u.first_name || \' \' || IFNULL(u.last_name, \'\')), \'\'), u.username) AS received_by
			FROM procurement_receipts r LEFT JOIN procurement_orders po ON po.id = r.order_id LEFT JOIN shopping_locations sl ON sl.id = r.supplier_id LEFT JOIN users u ON u.id = r.received_by_user_id WHERE r.id = ?', [$o['document']['id']]) : null;
		return [
			'at' => $o['at'], 'qty' => floatval($o['qty']), 'unit_cost' => floatval($o['price']), 'where' => trim(($o['branch_name'] ? $o['branch_name'] . ' · ' : '') . ($o['location_name'] ?? '')),
			'supplier' => $r['supplier_name'] ?? $o['supplier_name'], 'receipt' => $r, 'document' => $o['document'], 'user' => $o['user_name'], 'best_before_date' => $o['best_before_date'],
		];
	}

	private function SaleInfo(array $txs): array
	{
		$txs = array_values(array_unique(array_filter($txs)));
		if (!$txs)
		{
			return [];
		}
		$out = [];
		foreach ($this->Rows('SELECT si.transaction_id, s.id AS sale_id, s.sale_number, s.status, c.name AS customer_name, c.tax_id, (SELECT order_code FROM customer_orders WHERE sale_id = s.id) AS order_code
			FROM sale_items si JOIN sales s ON s.id = si.sale_id LEFT JOIN customers c ON c.id = s.customer_id WHERE si.transaction_id IN (' . $this->In($txs) . ')', $txs) as $r)
		{
			$out[$r['transaction_id']] = $r;
		}
		return $out;
	}

	// Movimientos hechos por sesiones de escáner (el resultado guarda la transacción de cada movimiento)
	private function ScanDocs(array $txs): array
	{
		$txs = array_values(array_unique(array_filter($txs)));
		if (!$txs)
		{
			return [];
		}
		$out = [];
		foreach ($this->Rows("SELECT id, folio, result_json FROM dairy_scan_sessions WHERE type = 'move' AND status = 'closed'") as $s)
		{
			foreach (json_decode($s['result_json'] ?? '[]', true)['moves'] ?? [] as $mv)
			{
				if (!empty($mv['transaction_id']) && in_array($mv['transaction_id'], $txs, true))
				{
					$out[$mv['transaction_id']] = ['type' => 'scan', 'id' => intval($s['id']), 'folio' => $s['folio']];
				}
			}
		}
		return $out;
	}

	/** Lotes de un producto para elegir qué trazar (con o sin lote del proveedor) */
	public function ProductLots(int $productId): array
	{
		return $this->Rows("SELECT l.stock_id, dl.lot_number, dl.serial_number, MIN(l.best_before_date) AS best_before_date, MIN(l.row_created_timestamp) AS first_seen,
				IFNULL((SELECT SUM(s.amount) FROM stock s WHERE s.stock_id = l.stock_id AND s.product_id = l.product_id), 0) AS on_hand
			FROM stock_log l LEFT JOIN dairy_stock_lots dl ON dl.stock_id = l.stock_id
			WHERE l.product_id = :p AND l.undone = 0 AND l.stock_id IS NOT NULL GROUP BY l.stock_id ORDER BY MIN(l.row_created_timestamp) DESC LIMIT 200", [':p' => $productId]);
	}
}
