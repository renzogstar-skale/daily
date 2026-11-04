<?php

namespace Grocy\Services;

use Grocy\Helpers\DairyAccess;

/**
 * Motor único de movimientos (Fase 1.2). No crea otro libro: unifica los dos que ya existen.
 *  - Físico (cambia stock): stock_log de Grocy, sellado en dairy_movement_ledger y clasificado por la vista merchandise_movements.
 *  - Disponibilidad (no cambia stock): vista inventory_availability_events (reserva/liberación de pedidos, bloqueo/desbloqueo
 *    por cuarentena), derivada de sus documentos para que no pueda desincronizarse.
 * Las escrituras siguen en cada servicio dueño (StockService, CustomerOrderService, QualityExpiryService…), con
 * transacción + bloqueo de escritura e idempotencia (DairyIdempotencyMiddleware); aquí se consultan y verifican.
 */
class InventoryMovementService extends BaseService
{
	/** Tipo canónico → efecto, y de dónde sale (bucket de merchandise_movements o movement_type de disponibilidad) */
	const TYPES = [
		'reception' => ['label' => 'Recepción', 'affects' => 'physical', 'buckets' => ['receptions']],
		'sale' => ['label' => 'Venta', 'affects' => 'physical', 'buckets' => ['sales']],
		'return' => ['label' => 'Devolución', 'affects' => 'physical', 'buckets' => ['returns']],
		'transfer' => ['label' => 'Traslado', 'affects' => 'physical', 'buckets' => ['transfers_in', 'transfers_out', 'internal', 'transit']],
		'waste' => ['label' => 'Merma', 'affects' => 'physical', 'buckets' => ['waste']],
		'adjustment' => ['label' => 'Ajuste', 'affects' => 'physical', 'buckets' => ['adjustments']],
		'dispatch' => ['label' => 'Salida / despacho', 'affects' => 'physical', 'buckets' => ['dispatches']],
		'block' => ['label' => 'Bloqueo', 'affects' => 'availability', 'events' => ['block']],
		'unblock' => ['label' => 'Desbloqueo', 'affects' => 'availability', 'events' => ['unblock']],
		'reserve' => ['label' => 'Reserva', 'affects' => 'availability', 'events' => ['reserve', 'reserve_adjust']],
		'release' => ['label' => 'Liberación de reserva', 'affects' => 'availability', 'events' => ['release']],
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

	public function Types(): array
	{
		return array_map(fn($k, $t) => ['code' => $k] + $t, array_keys(self::TYPES), self::TYPES);
	}

	private static function TypeOfBucket(string $bucket): string
	{
		foreach (self::TYPES as $k => $t)
		{
			if (in_array($bucket, $t['buckets'] ?? [], true))
			{
				return $k;
			}
		}
		return 'adjustment';
	}

	private static function TypeOfEvent(string $event): string
	{
		foreach (self::TYPES as $k => $t)
		{
			if (in_array($event, $t['events'] ?? [], true))
			{
				return $k;
			}
		}
		throw new \Exception('Evento de disponibilidad sin tipo: ' . $event);
	}

	/**
	 * Libro unificado. Filtros: product_id, branch_id, from, to (AAAA-MM-DD), affects (physical|availability), type (código canónico).
	 * Cada fila: id único, documento origen, usuario, fecha, producto, ubicación, cantidad con signo, tipo y estado.
	 */
	public function Ledger(array $f = [], int $limit = 500): array
	{
		$limit = max(1, min(5000, $limit));
		$from = $f['from'] ?? date('Y-m-d', strtotime('-30 days'));
		$to = $f['to'] ?? date('Y-m-d');
		$branchId = isset($f['branch_id']) && $f['branch_id'] !== '' ? intval($f['branch_id']) : null;
		if ($branchId !== null && !DairyAccess::CanSeeBranch($branchId))
		{
			throw new \Exception('No tiene acceso a esa sucursal');
		}
		$type = $f['type'] ?? null;
		if ($type !== null && $type !== '' && !isset(self::TYPES[$type]))
		{
			throw new \Exception('Tipo de movimiento desconocido: ' . $type);
		}
		$affects = $type ? self::TYPES[$type]['affects'] : ($f['affects'] ?? null);
		$allowed = DairyAccess::AllowedBranches();
		$branchSql = fn(string $col) => $branchId !== null ? " AND $col = $branchId" : ($allowed !== null ? " AND $col IN (" . implode(',', array_map('intval', $allowed ?: [0])) . ')' : '');
		$productSql = fn(string $col) => !empty($f['product_id']) ? " AND $col = " . intval($f['product_id']) : '';
		$out = [];

		if ($affects === null || $affects === 'physical')
		{
			$buckets = $type ? self::TYPES[$type]['buckets'] : null;
			$bucketSql = $buckets ? " AND m.bucket IN ('" . implode("', '", $buckets) . "')" : '';
			foreach ($this->Rows("SELECT m.id, m.movement_code, m.operation_ref, m.transaction_type, m.bucket, m.product_id, p.name AS product_name, m.amount, m.unit_cost, m.value,
					m.stock_id, m.location_id, m.location_name, m.branch_id, m.user_id, m.note, m.undone, m.recorded_at, m.effective_at
				FROM merchandise_movements m JOIN products p ON p.id = m.product_id
				WHERE date(m.recorded_at) BETWEEN :f AND :t" . $branchSql('m.branch_id') . $productSql('m.product_id') . $bucketSql . " ORDER BY m.recorded_at DESC, m.id DESC LIMIT $limit", [':f' => $from, ':t' => $to]) as $r)
			{
				$out[] = [
					'uid' => $r['movement_code'], 'affects' => 'physical', 'type' => self::TypeOfBucket($r['bucket']), 'detail_type' => $r['bucket'],
					'product_id' => intval($r['product_id']), 'product_name' => $r['product_name'], 'branch_id' => $r['branch_id'] !== null ? intval($r['branch_id']) : null,
					'location_id' => intval($r['location_id']), 'location_name' => $r['location_name'], 'stock_id' => $r['stock_id'],
					'amount' => floatval($r['amount']), 'unit_cost' => $r['unit_cost'] !== null ? floatval($r['unit_cost']) : null,
					'source_type' => 'stock_transaction', 'source_id' => $r['operation_ref'], 'user_id' => $r['user_id'] !== null ? intval($r['user_id']) : null,
					'occurred_at' => $r['recorded_at'], 'status' => intval($r['undone']) ? 'undone' : 'posted', 'note' => $r['note'],
				];
			}
		}
		if ($affects === null || $affects === 'availability')
		{
			$events = $type ? self::TYPES[$type]['events'] : null;
			$evSql = $events ? " AND e.movement_type IN ('" . implode("', '", $events) . "')" : '';
			foreach ($this->Rows("SELECT e.*, p.name AS product_name FROM inventory_availability_events e JOIN products p ON p.id = e.product_id
				WHERE date(e.occurred_at) BETWEEN :f AND :t" . $branchSql('e.branch_id') . $productSql('e.product_id') . $evSql . " ORDER BY e.occurred_at DESC LIMIT $limit", [':f' => $from, ':t' => $to]) as $r)
			{
				$out[] = [
					'uid' => $r['event_code'], 'affects' => 'availability', 'type' => self::TypeOfEvent($r['movement_type']), 'detail_type' => $r['movement_type'] . ':' . $r['bucket'],
					'product_id' => intval($r['product_id']), 'product_name' => $r['product_name'], 'branch_id' => $r['branch_id'] !== null ? intval($r['branch_id']) : null,
					'location_id' => $r['location_id'] !== null ? intval($r['location_id']) : null, 'location_name' => null, 'stock_id' => $r['stock_id'],
					'amount' => floatval($r['amount']), 'unit_cost' => null,
					'source_type' => $r['source_type'], 'source_id' => strval($r['source_id']), 'user_id' => $r['user_id'] !== null ? intval($r['user_id']) : null,
					'occurred_at' => $r['occurred_at'], 'status' => 'posted', 'note' => $r['note'],
				];
			}
		}
		usort($out, fn($a, $b) => strcmp((string) $b['occurred_at'], (string) $a['occurred_at']));
		return array_slice($out, 0, $limit);
	}

	/**
	 * Posición de un producto en una sucursal: físico, disponible, reservado, bloqueado, en tránsito.
	 * Fuente: ScanService::Availability (la que usan POS, pedidos y escáner). 'consistency' compara reservado y bloqueado
	 * contra el saldo del libro de disponibilidad: si difieren, algún documento quedó en un estado que el libro no explica.
	 */
	public function Position(int $productId, int $branchId): array
	{
		if (!DairyAccess::CanSeeBranch($branchId))
		{
			throw new \Exception('No tiene acceso a esa sucursal');
		}
		$a = ScanService::GetInstance()->Availability($productId, $branchId);
		$ledger = [];
		foreach ($this->Rows('SELECT bucket, IFNULL(SUM(amount), 0) AS v FROM inventory_availability_events WHERE product_id = :p AND branch_id = :b GROUP BY bucket', [':p' => $productId, ':b' => $branchId]) as $r)
		{
			$ledger[$r['bucket']] = round(floatval($r['v']), 4);
		}
		$transitToBranch = floatval($this->Val("SELECT IFNULL(SUM(i.sent_amount - IFNULL(i.received_amount, 0)), 0) FROM stock_transfer_items i JOIN stock_transfers t ON t.id = i.transfer_id
			WHERE i.product_id = :p AND t.to_branch_id = :b AND t.status IN ('in_transit', 'sent', 'partially_received')", [':p' => $productId, ':b' => $branchId]) ?: 0);
		$reserved = round(floatval($a['reserved']), 4);
		$blocked = round(floatval($a['quarantine']), 4);
		return [
			'product_id' => $productId, 'branch_id' => $branchId,
			'physical' => floatval($a['physical']),
			'available' => floatval($a['available']),
			'reserved' => $reserved,
			'blocked' => $blocked,
			'expired' => floatval($a['expired']),
			'committed_waste' => floatval($a['committed_waste']),
			'in_transit_inbound' => round($transitToBranch, 4),
			'formula' => 'disponible = físico − bloqueado (cuarentena) − vencido − merma por aprobar − reservado',
			'consistency' => [
				'reserved_ledger' => $ledger['reserved'] ?? 0.0, 'reserved_ok' => abs(($ledger['reserved'] ?? 0) - $reserved) < 0.0001,
				'blocked_ledger' => $ledger['blocked'] ?? 0.0, 'blocked_ok' => abs(($ledger['blocked'] ?? 0) - $blocked) < 0.0001,
			],
		];
	}

	/**
	 * Control de duplicados: movimientos físicos idénticos (producto, ubicación, cantidad, tipo, usuario) registrados con
	 * distinta transacción en menos de $seconds segundos — típico de doble envío. Más la verificación del sello del libro.
	 */
	public function DuplicateCheck(int $days = 7, int $seconds = 5): array
	{
		$allowed = DairyAccess::AllowedBranches();
		$b = $allowed !== null ? ' AND l.branch_id IN (' . implode(',', array_map('intval', $allowed ?: [0])) . ')' : '';
		$dups = $this->Rows("SELECT a.id AS first_id, b.id AS second_id, a.product_id, p.name AS product_name, a.location_id, a.amount, a.transaction_type, a.user_id,
				a.row_created_timestamp AS first_at, b.row_created_timestamp AS second_at, a.transaction_id AS first_tx, b.transaction_id AS second_tx
			FROM stock_log a
			JOIN stock_log b ON b.id > a.id AND b.product_id = a.product_id AND b.location_id = a.location_id AND b.amount = a.amount AND b.transaction_type = a.transaction_type
				AND IFNULL(b.user_id, 0) = IFNULL(a.user_id, 0) AND b.transaction_id <> a.transaction_id AND b.undone = 0
				AND (julianday(b.row_created_timestamp) - julianday(a.row_created_timestamp)) * 86400 BETWEEN 0 AND " . intval($seconds) . "
			JOIN products p ON p.id = a.product_id JOIN locations l ON l.id = a.location_id
			WHERE a.undone = 0 AND a.transaction_type <> 'product-opened' AND a.row_created_timestamp >= :since $b
				AND IFNULL(p.device_control, 0) = 0 AND NOT EXISTS (SELECT 1 FROM device_units du WHERE du.product_id = a.product_id) -- equipos con IMEI/serie entran una fila por unidad: no es duplicado
			ORDER BY a.id DESC LIMIT 200", [':since' => date('Y-m-d H:i:s', strtotime("-$days days"))]);
		$seal = MovementLedgerService::GetInstance()->Verify(100);
		return ['possible_duplicates' => $dups, 'window_seconds' => $seconds, 'days' => $days, 'ledger_seal' => $seal];
	}
}
