<?php

namespace Grocy\Services;

/**
 * DAILY — Motor de conciliación de inventario.
 *
 * Stock esperado = inicial + recepciones + devoluciones reincorporadas + traslados entrantes − ventas − despachos
 *                  − traslados salientes − bajas autorizadas ± ajustes aprobados.
 *
 * La fuente es stock_log (lo que alimenta kardex y libro inalterable). Cada fila cae en UN solo concepto, de modo que una
 * venta con despacho asociado no se descuenta dos veces. Los registros de sensitive_inventory_movements son documentos: no
 * mueven stock y por eso no entran en la fórmula. Solo lectura: no escribe nada.
 */
class LossReconciliationService extends BaseService
{
	const BUCKETS = [
		'receptions' => 'Recepciones', 'returns' => 'Devoluciones reincorporadas', 'transfers_in' => 'Traslados entrantes',
		'sales' => 'Ventas', 'dispatches' => 'Despachos y otras salidas', 'transfers_out' => 'Traslados salientes',
		'waste' => 'Bajas autorizadas', 'adjustments' => 'Ajustes aprobados'
	];

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

	private function Val(string $sql, array $p = [])
	{
		$stmt = $this->Db()->prepare($sql);
		$stmt->execute($p);
		return $stmt->fetchColumn();
	}

	/**
	 * Movimientos del producto en la sucursal, ya clasificados. effective_at es cuándo ocurrió la operación (no cuándo llegó
	 * al sistema): una venta POS sincronizada tarde conserva su hora real.
	 */
	public function Movements(int $productId, int $branchId, ?int $locationId = null): array
	{
		$rows = $this->Rows("
			SELECT sl.id, sl.amount, sl.transaction_type, sl.location_id, l.name AS location_name, sl.transaction_id, sl.stock_id, sl.user_id, sl.price, sl.spoiled, sl.note,
				sl.row_created_timestamp, u.username, TRIM(IFNULL(u.first_name, '') || ' ' || IFNULL(u.last_name, '')) AS user_full,
				s.id AS sale_id, s.sale_number, s.status AS sale_status, s.created_timestamp AS sale_at, pe.occurred_at AS pos_at, pe.source AS pos_source,
				co.order_code, co.delivery_type, w.id AS waste_id, w.case_number AS waste_case, sm.event_type AS doc_event, sm.movement_code AS doc_code,
				t.transfer_code, sb.lot_number, sb.serial_number, sb.imei
			FROM stock_log sl
			JOIN locations l ON l.id = sl.location_id
			LEFT JOIN users u ON u.id = sl.user_id
			LEFT JOIN (SELECT transaction_id, MIN(sale_id) AS sale_id FROM sale_items WHERE transaction_id IS NOT NULL GROUP BY transaction_id) si ON si.transaction_id = sl.transaction_id
			LEFT JOIN sales s ON s.id = si.sale_id
			LEFT JOIN pos_external_sales pe ON pe.sale_id = s.id
			LEFT JOIN customer_orders co ON co.sale_id = s.id
			LEFT JOIN waste_records w ON w.transaction_id = sl.transaction_id AND w.product_id = sl.product_id
			LEFT JOIN sensitive_inventory_movements sm ON sm.stock_transaction_id = sl.transaction_id AND sm.product_id = sl.product_id
			LEFT JOIN stock_transfers t ON t.send_transaction_id = sl.transaction_id OR t.receive_transaction_id = sl.transaction_id
			LEFT JOIN (SELECT stock_id, MIN(lot_number) AS lot_number, MIN(serial_number) AS serial_number, MIN(imei) AS imei FROM dairy_stock_lots GROUP BY stock_id) sb ON sb.stock_id = sl.stock_id
			WHERE sl.product_id = :p AND sl.undone = 0 AND sl.transaction_type <> 'product-opened' AND l.branch_id = :b AND IFNULL(l.is_transit, 0) = 0" . ($locationId ? ' AND sl.location_id = :l' : '') . '
			GROUP BY sl.id
			ORDER BY sl.row_created_timestamp, sl.id
		', [':p' => $productId, ':b' => $branchId] + ($locationId ? [':l' => $locationId] : []));

		// Contrapartes de los traslados: el movimiento dentro de la misma sucursal no cambia su total
		$internal = [];
		foreach ($rows as $r)
		{
			if (in_array($r['transaction_type'], ['transfer_from', 'transfer_to'], true) && $r['transaction_id'])
			{
				$internal[$r['transaction_id']][$r['transaction_type']] = true;
			}
		}

		foreach ($rows as &$r)
		{
			$r['amount'] = floatval($r['amount']);
			$r['effective_at'] = $r['pos_at'] ?: ($r['sale_at'] ?: $r['row_created_timestamp']);
			$r['bucket'] = $this->Bucket($r, $internal);
		}
		return $rows;
	}

	private function Bucket(array $r, array $internal): ?string
	{
		switch ($r['transaction_type'])
		{
			case 'transfer_from':
			case 'transfer_to':
				if (count($internal[$r['transaction_id']] ?? []) === 2)
				{
					return null;
				}
				return $r['transaction_type'] === 'transfer_from' ? 'transfers_out' : 'transfers_in';
			case 'purchase':
			case 'self-production':
				if ($r['doc_event'] === 'return_exchange' || stripos((string) $r['note'], 'Devolución') === 0)
				{
					return 'returns';
				}
				return $r['doc_event'] === 'adjustment' ? 'adjustments' : 'receptions';
			case 'consume':
				if ($r['sale_id'])
				{
					return 'sales';
				}
				return ($r['waste_id'] || intval($r['spoiled']) === 1) ? 'waste' : 'dispatches';
			default:
				return 'adjustments'; // inventory-correction, stock-edit-old/new
		}
	}

	/**
	 * Desglose de la fórmula. Sin $from el saldo inicial es cero y la reconstrucción abarca toda la historia.
	 * book_gap: diferencia entre la existencia real (tabla stock) y la historia; si no es cero, hay existencias cargadas sin
	 * movimiento, y se suma al saldo inicial para que la fórmula no invente una pérdida.
	 */
	public function Expected(int $productId, int $branchId, ?string $from = null, ?string $at = null, ?int $locationId = null): array
	{
		$b = array_fill_keys(array_keys(self::BUCKETS), 0.0);
		$opening = 0.0;
		$logged = 0.0;
		foreach ($this->Movements($productId, $branchId, $locationId) as $m)
		{
			$logged += $m['amount'];
			if ($at !== null && $m['effective_at'] > $at)
			{
				continue;
			}
			if ($from !== null && $m['effective_at'] < $from)
			{
				$opening += $m['amount'];
				continue;
			}
			if ($m['bucket'] !== null)
			{
				$b[$m['bucket']] += $m['amount'];
			}
		}
		$book = $this->OnHand($productId, $branchId, $locationId);
		$gap = round($book - $logged, 4);
		$opening = round($opening + $gap, 4);
		$b = array_map(fn($v) => round($v, 4), $b);
		return [
			'opening' => $opening,
			'buckets' => $b,
			'book_gap' => $gap,
			'expected' => round($opening + array_sum($b), 4),
			'on_hand' => $book,
		];
	}

	public function OnHand(int $productId, int $branchId, ?int $locationId = null): float
	{
		return floatval($this->Val('SELECT IFNULL(SUM(s.amount), 0) FROM stock s JOIN locations l ON l.id = s.location_id
			WHERE s.product_id = :p AND l.branch_id = :b AND IFNULL(l.is_transit, 0) = 0' . ($locationId ? ' AND s.location_id = :l' : ''), [':p' => $productId, ':b' => $branchId] + ($locationId ? [':l' => $locationId] : [])));
	}

	/** Físico, disponible, reservado y en tránsito: cuatro cifras distintas que no deben confundirse */
	public function Positions(int $productId, int $branchId): array
	{
		$a = ScanService::GetInstance()->Availability($productId, $branchId);
		$out = floatval($this->Val("SELECT IFNULL(SUM(i.sent_amount), 0) FROM stock_transfer_items i JOIN stock_transfers t ON t.id = i.transfer_id WHERE t.status = 'in_transit' AND i.product_id = :p AND t.from_branch_id = :b", [':p' => $productId, ':b' => $branchId]));
		$in = floatval($this->Val("SELECT IFNULL(SUM(i.sent_amount), 0) FROM stock_transfer_items i JOIN stock_transfers t ON t.id = i.transfer_id WHERE t.status = 'in_transit' AND i.product_id = :p AND t.to_branch_id = :b", [':p' => $productId, ':b' => $branchId]));
		return [
			'physical' => floatval($a['physical']),
			'available' => floatval($a['available']),
			'reserved' => floatval($a['reserved']),
			'quarantine' => floatval($a['quarantine']),
			'blocked' => round(floatval($a['quarantine']) + floatval($a['expired']) + floatval($a['committed_waste']), 4),
			'expired' => floatval($a['expired']),
			'committed_waste' => floatval($a['committed_waste']),
			'in_transit_out' => round($out, 4),
			'in_transit_in' => round($in, 4),
		];
	}

	/**
	 * Operaciones aún no asentadas en stock: mientras existan, una diferencia es provisional, nunca una pérdida definitiva.
	 * Ventas POS recibidas pero sin procesar, sesiones de escáner abiertas y movimientos documentales sin confirmar.
	 */
	public function PendingSync(int $productId, int $branchId): array
	{
		$items = [];
		$sku = $this->Val('SELECT sku FROM products WHERE id = :p', [':p' => $productId]) ?: '';
		$pos = $this->Rows("SELECT external_id, source, payload_json, status FROM pos_external_sales WHERE branch_id = :b AND sale_id IS NULL AND status IN ('processing', 'failed')", [':b' => $branchId]);
		foreach ($pos as $s)
		{
			$qty = 0.0;
			$body = json_decode((string) $s['payload_json'], true) ?: [];
			foreach (($body['items'] ?? []) as $l)
			{
				if (intval($l['product_id'] ?? 0) === $productId || ($l['sku'] ?? null) === $sku)
				{
					$qty += floatval($l['amount'] ?? 0);
				}
			}
			if ($qty > 0)
			{
				$items[] = ['kind' => 'pos', 'label' => "Venta POS {$s['source']} {$s['external_id']} sin procesar", 'qty' => -$qty];
			}
		}
		foreach ($this->Rows("SELECT ss.folio, ss.type, SUM(sl.amount) AS q FROM dairy_scan_lines sl JOIN dairy_scan_sessions ss ON ss.id = sl.session_id
			WHERE sl.product_id = :p AND ss.branch_id = :b AND ss.status = 'open' AND sl.voided_at IS NULL AND ss.type <> 'count' GROUP BY ss.id", [':p' => $productId, ':b' => $branchId]) as $s)
		{
			$items[] = ['kind' => 'scan', 'label' => "Sesión de escáner {$s['folio']} ({$s['type']}) abierta", 'qty' => -floatval($s['q'])];
		}
		foreach ($this->Rows("SELECT movement_code, event_type, actual_amount, status FROM sensitive_inventory_movements
			WHERE product_id = :p AND branch_id = :b AND status IN ('solicitado', 'en_transito') AND stock_transaction_id IS NULL AND IFNULL(is_demo, 0) = 0", [':p' => $productId, ':b' => $branchId]) as $s)
		{
			$items[] = ['kind' => 'doc', 'label' => "{$s['movement_code']} ({$s['status']})", 'qty' => -floatval($s['actual_amount'])];
		}
		return $items;
	}

	/**
	 * Qué podría justificar una diferencia, siempre con cantidad y documento verificables. Solo sugiere: nada se da por
	 * justificado hasta que alguien lo compruebe, y nunca señala a una persona.
	 */
	public function Explain(int $productId, int $branchId, float $diff, ?int $countLocationId, array $positions, array $pending): array
	{
		$short = abs(min($diff, 0));
		$h = [];
		$picked = floatval($this->Val("SELECT IFNULL(SUM(i.picked_amount), 0) FROM customer_order_items i JOIN customer_orders o ON o.id = i.order_id WHERE i.product_id = :p AND o.branch_id = :b AND o.status IN ('picked', 'packed', 'dispatching')", [':p' => $productId, ':b' => $branchId]));
		if ($picked > 0)
		{
			$h[] = ['code' => 'pickup', 'label' => 'Unidades preparadas para pedido o pickup, fuera del área contada', 'qty' => $picked, 'source' => 'Pedidos de Clientes'];
		}
		if ($countLocationId)
		{
			$elsewhere = round($this->OnHand($productId, $branchId) - $this->OnHand($productId, $branchId, $countLocationId), 4);
			if ($elsewhere > 0)
			{
				$h[] = ['code' => 'other_location', 'label' => 'Existencia registrada en otra ubicación de la sucursal', 'qty' => $elsewhere, 'source' => 'Stock & Movimientos'];
			}
		}
		$pendingQty = array_sum(array_map(fn($i) => abs($i['qty']), $pending));
		if ($pendingQty > 0)
		{
			$h[] = ['code' => 'pending_sync', 'label' => 'Operaciones pendientes de sincronización', 'qty' => $pendingQty, 'source' => implode(' · ', array_column($pending, 'label'))];
		}
		$returns = floatval($this->Val("SELECT IFNULL(SUM(quantity), 0) FROM customer_returns_exchanges WHERE product_id = :p AND branch_id = :b AND classification IS NULL", [':p' => $productId, ':b' => $branchId]));
		if ($returns > 0)
		{
			$h[] = ['code' => 'return_pending', 'label' => 'Devolución sin validar', 'qty' => $returns, 'source' => 'Despachos y Devoluciones'];
		}
		if ($positions['in_transit_in'] > 0)
		{
			$h[] = ['code' => 'transfer_in', 'label' => 'Traslado entrante sin confirmar: pudo contarse aunque aún no se recibió', 'qty' => $positions['in_transit_in'], 'source' => 'Traslados entre Tiendas'];
		}
		if ($positions['committed_waste'] > 0)
		{
			$h[] = ['code' => 'waste_pending', 'label' => 'Merma reportada pendiente de aprobación', 'qty' => $positions['committed_waste'], 'source' => 'Mermas y Prevención'];
		}
		if ($positions['expired'] > 0)
		{
			$h[] = ['code' => 'expired', 'label' => 'Unidades vencidas: pudieron retirarse sin registrar la baja', 'qty' => $positions['expired'], 'source' => 'Calidad y Vencimientos'];
		}
		if ($positions['quarantine'] > 0)
		{
			$h[] = ['code' => 'quarantine', 'label' => 'Unidades en cuarentena, fuera del área contada', 'qty' => $positions['quarantine'], 'source' => 'Calidad y Vencimientos'];
		}
		$h[] = ['code' => 'record_error', 'label' => 'Error de registro o de conteo (recepción, UPC o cantidad)', 'qty' => 0, 'source' => 'Requiere reconteo y revisión de documentos'];

		$explained = 0.0;
		foreach ($h as $x)
		{
			$explained += in_array($x['code'], ['pickup', 'other_location', 'pending_sync', 'return_pending', 'waste_pending', 'quarantine'], true) ? $x['qty'] : 0;
		}
		$explained = min($explained, $short);
		if ($short > $explained + 0.0001)
		{
			$h[] = ['code' => 'unexplained', 'label' => 'Diferencia sin movimiento que la justifique', 'qty' => round($short - $explained, 4), 'source' => 'Ningún documento la explica'];
		}
		return ['hypotheses' => $h, 'explained_qty' => round($explained, 4), 'unexplained_qty' => round(max(0, $short - $explained), 4)];
	}

	/** Última ubicación y último registro conocidos. El registro es un dato de la operación, no una atribución de responsabilidad. */
	public function LastKnown(int $productId, int $branchId, ?string $at = null): array
	{
		$rows = array_values(array_filter($this->Movements($productId, $branchId), fn($m) => $at === null || $m['effective_at'] <= $at));
		$last = $rows ? end($rows) : null;
		$loc = $this->Rows('SELECT l.name, SUM(s.amount) AS qty FROM stock s JOIN locations l ON l.id = s.location_id WHERE s.product_id = :p AND l.branch_id = :b AND IFNULL(l.is_transit, 0) = 0 GROUP BY s.location_id HAVING qty > 0 ORDER BY qty DESC LIMIT 1', [':p' => $productId, ':b' => $branchId]);
		return [
			'location' => $loc[0]['name'] ?? ($last['location_name'] ?? null),
			'movement' => $last ? [
				'at' => $last['effective_at'], 'type' => self::BUCKETS[$last['bucket']] ?? 'Movimiento interno', 'amount' => $last['amount'],
				'document' => $this->Document($last),
			] : null,
			'registered_by' => $last ? (trim((string) $last['user_full']) ?: $last['username']) : null,
			'disclaimer' => 'Quien registró un movimiento no es responsable de una diferencia. Es un dato de trazabilidad, no una conclusión.',
		];
	}

	public function Document(array $m): string
	{
		return $m['sale_number'] ?: ($m['transfer_code'] ?: ($m['doc_code'] ?: ($m['waste_case'] ?: ($m['note'] ?: strval($m['transaction_id'])))));
	}

	/** Turno por hora del registro */
	public static function ShiftOf(string $ts): string
	{
		$h = intval(substr($ts, 11, 2));
		return $h >= 6 && $h < 14 ? 'Mañana' : ($h >= 14 && $h < 22 ? 'Tarde' : 'Noche');
	}

	/** Diferencias de conteo agrupadas por una dimensión. Cada conteo cuenta una vez; las series faltantes se listan aparte. */
	public function DifferencesBy(string $dim, ?int $branchId = null, ?string $from = null, ?string $to = null): array
	{
		$map = [
			'branch' => 'b.name', 'department' => "IFNULL(p.department, 'General')", 'sku' => "IFNULL(p.sku, p.name)", 'lot' => "IFNULL(m.batch_number, '—')",
			'location' => "IFNULL(m.dest_location, '—')", 'date' => 'date(m.created_at)', 'shift' => "IFNULL(m.shift_code, '—')", 'user' => "IFNULL(u.username, '—')",
			'category' => "IFNULL(pg.name, 'Sin categoría')", 'warehouse' => "IFNULL(lc.warehouse, IFNULL(lc.name, IFNULL(m.dest_location, '—')))", 'supplier' => "IFNULL(sp.name, 'Sin proveedor')",
		];
		if ($dim === 'serial')
		{
			return $this->Rows("SELECT ss.code AS dim, 1 AS diff_units, 0 AS diff_value, ss.scanned_at FROM sensitive_count_serials ss JOIN sensitive_count_tasks t ON t.id = ss.task_id
				WHERE ss.matched = 0" . ($branchId ? ' AND t.branch_id = ' . intval($branchId) : '') . ' ORDER BY ss.scanned_at DESC LIMIT 100');
		}
		if (!isset($map[$dim]))
		{
			throw new \Exception('Dimensión no válida');
		}
		$w = ["m.event_type = 'cycle_count'", 'm.difference_amount <> 0'];
		$p = [];
		if ($branchId)
		{
			$w[] = 'm.branch_id = :b';
			$p[':b'] = $branchId;
		}
		if ($from)
		{
			$w[] = 'date(m.created_at) >= :f';
			$p[':f'] = $from;
		}
		if ($to)
		{
			$w[] = 'date(m.created_at) <= :t';
			$p[':t'] = $to;
		}
		return $this->Rows("SELECT {$map[$dim]} AS dim, SUM(m.difference_amount) AS diff_units, SUM(ABS(m.financial_impact)) AS diff_value, COUNT(*) AS counts
			FROM sensitive_inventory_movements m JOIN products p ON p.id = m.product_id LEFT JOIN branches b ON b.id = m.branch_id LEFT JOIN users u ON u.id = m.user_id
			LEFT JOIN product_groups pg ON pg.id = p.product_group_id LEFT JOIN locations lc ON lc.id = m.location_id LEFT JOIN shopping_locations sp ON sp.id = p.shopping_location_id
			WHERE " . implode(' AND ', $w) . ' GROUP BY 1 ORDER BY diff_value DESC LIMIT 50', $p);
	}
}
