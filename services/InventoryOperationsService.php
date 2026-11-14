<?php

namespace Grocy\Services;

use Grocy\Controllers\Users\User;
use Grocy\Helpers\DairyAccess;

/**
 * Inventario y Almacenes: control de la mercancía desde la recepción hasta la salida, traslado o ajuste autorizado.
 *
 * No es otro libro: lee el stock físico (stock / stock_log), la disponibilidad (cuarentena, vencidos, mermas por aprobar,
 * reservas de pedidos) y los documentos de cada módulo dueño (Compras recibe, POS vende, Despachos entrega, Mermas da
 * de baja, Traslados mueve entre tiendas). Las únicas escrituras propias son las del almacén:
 *  - ajustes de inventario (conteo que cambia existencias): quedan pendientes y solo un aprobador distinto los aplica;
 *  - reposición bodega → sala: tarea pendiente que mueve stock solo al confirmarse.
 *
 * Fórmulas (iguales en toda la pantalla):
 *  disponible = físico − cuarentena − vencido − merma por aprobar − reservado (nunca se muestra negativo; el faltante
 *  se reporta como anomalía «sobrecomprometido»).
 *  exactitud = conteos dentro de tolerancia ÷ conteos ejecutados (90 días, tolerancia ±2 % o 0 u si el sistema dice 0).
 *  cobertura = físico ÷ salidas diarias promedio (28 días, salidas = consumo no mermado: venta, despacho, producción).
 *  rotación anual = costo de salidas de 90 días × 365/90 ÷ valor actual del inventario.
 */
class InventoryOperationsService extends BaseService
{
	const ACCURACY_TOLERANCE_PCT = 2.0;
	const ACCURACY_DAYS = 90;
	const COVERAGE_DAYS = 28;
	const ROTATION_DAYS = 90;
	const TRANSIT_LATE_HOURS = 48;
	const EXTREME_COVERAGE_DAYS = 365;
	const EXTREME_ROTATION = 52;
	const ZONES = ['bodega' => 'Bodega', 'sala' => 'Sala de ventas', 'otra' => 'Otras ubicaciones'];
	// Origen de cada movimiento físico (bucket de merchandise_movements) → módulo dueño que lo registró
	const SOURCES = [
		'receptions' => ['Recepción', 'Compras'], 'sales' => ['Venta', 'POS'], 'dispatches' => ['Despacho', 'Despachos'],
		'waste' => ['Merma', 'Mermas'], 'returns' => ['Devolución', 'Devoluciones'], 'transfers_in' => ['Traslado (entrada)', 'Traslados'],
		'transfers_out' => ['Traslado (salida)', 'Traslados'], 'transit' => ['En tránsito', 'Traslados'], 'internal' => ['Movimiento interno', 'Inventario'],
		'adjustments' => ['Ajuste', 'Inventario'],
	];

	private ?array $branchNames = null;

	private function Db(): \PDO
	{
		return DatabaseService::GetInstance()->GetDbConnectionRaw();
	}

	private function Rows(string $sql, array $p = []): array
	{
		$st = $this->Db()->prepare($sql);
		$st->execute($p);
		return $st->fetchAll(\PDO::FETCH_ASSOC);
	}

	private function Row(string $sql, array $p = []): ?array
	{
		$st = $this->Db()->prepare($sql);
		$st->execute($p);
		$r = $st->fetch(\PDO::FETCH_ASSOC);
		return $r === false ? null : $r;
	}

	private function Val(string $sql, array $p = [])
	{
		$st = $this->Db()->prepare($sql);
		$st->execute($p);
		return $st->fetchColumn();
	}

	private static function UserId(): int
	{
		return defined('GROCY_USER_ID') ? intval(GROCY_USER_ID) : 0;
	}

	/** Sucursales visibles para el filtro: una (validada) o las permitidas al usuario (null = toda la red) */
	private function Scope($branchId): ?array
	{
		$b = ($branchId !== null && $branchId !== '' && intval($branchId) > 0) ? intval($branchId) : null;
		if ($b !== null)
		{
			if (!DairyAccess::CanSeeBranch($b))
			{
				throw new \Exception('No tiene acceso a esa sucursal');
			}
			return [$b];
		}
		$allowed = DairyAccess::AllowedBranches();
		return $allowed === null ? null : array_map('intval', $allowed ?: [0]);
	}

	private static function In(?array $scope, string $col): string
	{
		return $scope === null ? '' : " AND $col IN (" . implode(',', $scope) . ')';
	}

	private function BranchNames(): array
	{
		if ($this->branchNames === null)
		{
			$this->branchNames = [];
			foreach ($this->Rows('SELECT id, name FROM branches') as $r)
			{
				$this->branchNames[intval($r['id'])] = $r['name'];
			}
		}
		return $this->branchNames;
	}

	private static function ZoneSql(string $alias = 'l'): string
	{
		return "CASE $alias.zone_type WHEN 'bodega' THEN 'bodega' WHEN 'piso_venta' THEN 'sala' ELSE 'otra' END";
	}

	/* ================================================================ Posición (físico, reservado, disponible) */

	/**
	 * Posición por sucursal y producto con el desglose de disponibilidad y la división bodega / sala.
	 * Una sola pasada por tabla (no una consulta por producto) para que cargue rápido en móvil.
	 */
	private function PositionRows(?array $scope, ?int $productId = null): array
	{
		$today = date('Y-m-d');
		$p = $productId ? ' AND s.product_id = ' . intval($productId) : '';
		$pos = [];
		$key = fn($b, $pid) => intval($b) . ':' . intval($pid);
		$init = fn($b, $pid) => ['branch_id' => intval($b), 'product_id' => intval($pid), 'physical' => 0.0, 'bodega' => 0.0, 'sala' => 0.0, 'otra' => 0.0, 'value' => 0.0,
			'expired' => 0.0, 'quarantine' => 0.0, 'committed_waste' => 0.0, 'reserved' => 0.0, 'negative_rows' => 0];
		foreach ($this->Rows("SELECT l.branch_id, s.product_id, " . self::ZoneSql() . " AS zgroup, SUM(s.amount) AS qty,
				SUM(CASE WHEN s.best_before_date < '$today' AND s.best_before_date <> '2999-12-31' THEN s.amount ELSE 0 END) AS expired,
				SUM(s.amount * IFNULL(NULLIF(s.price, 0), IFNULL(plp.price, 0))) AS value, SUM(CASE WHEN s.amount < 0 THEN 1 ELSE 0 END) AS neg
			FROM stock s JOIN locations l ON l.id = s.location_id LEFT JOIN cache__products_last_purchased plp ON plp.product_id = s.product_id
			WHERE IFNULL(l.is_transit, 0) = 0 AND l.branch_id IS NOT NULL" . self::In($scope, 'l.branch_id') . $p . '
			GROUP BY l.branch_id, s.product_id, zgroup') as $r)
		{
			$k = $key($r['branch_id'], $r['product_id']);
			$pos[$k] ??= $init($r['branch_id'], $r['product_id']);
			$q = floatval($r['qty']);
			$pos[$k]['physical'] += $q;
			$pos[$k][$r['zgroup']] += $q;
			$pos[$k]['value'] += floatval($r['value']);
			$pos[$k]['expired'] += max(0, floatval($r['expired']));
			$pos[$k]['negative_rows'] += intval($r['neg']);
		}
		$extra = [
			'quarantine' => "SELECT q.branch_id, q.product_id, SUM(q.amount) AS v FROM quarantine_items q WHERE q.status = 'in_quarantine'" . self::In($scope, 'q.branch_id') . ($productId ? ' AND q.product_id = ' . intval($productId) : '') . ' GROUP BY 1, 2',
			'committed_waste' => "SELECT w.branch_id, w.product_id, SUM(w.amount) AS v FROM waste_records w WHERE w.status = 'pending_approval' AND IFNULL(w.inventory_deducted, 0) = 0" . self::In($scope, 'w.branch_id') . ($productId ? ' AND w.product_id = ' . intval($productId) : '') . ' GROUP BY 1, 2',
			'reserved' => "SELECT o.branch_id, i.product_id, SUM(CASE WHEN o.status IN ('picked', 'packed', 'dispatching') THEN i.picked_amount ELSE i.amount END) AS v
				FROM customer_order_items i JOIN customer_orders o ON o.id = i.order_id WHERE o.status IN ('" . implode("', '", CustomerOrderService::RESERVING) . "')" . self::In($scope, 'o.branch_id') . ($productId ? ' AND i.product_id = ' . intval($productId) : '') . ' GROUP BY 1, 2',
		];
		foreach ($extra as $field => $sql)
		{
			foreach ($this->Rows($sql) as $r)
			{
				if ($r['branch_id'] === null)
				{
					continue;
				}
				$k = $key($r['branch_id'], $r['product_id']);
				$pos[$k] ??= $init($r['branch_id'], $r['product_id']);
				$pos[$k][$field] += floatval($r['v']);
			}
		}
		foreach ($pos as &$r)
		{
			$blocked = $r['quarantine'] + $r['expired'] + $r['committed_waste'];
			$net = round($r['physical'] - $blocked - $r['reserved'], 4);
			$r['blocked'] = round($blocked, 4);
			$r['available'] = max(0.0, $net);
			$r['shortfall'] = $net < 0 ? round(-$net, 4) : 0.0; // reservado + bloqueado supera al físico
			foreach (['physical', 'bodega', 'sala', 'otra', 'value', 'reserved'] as $f)
			{
				$r[$f] = round($r[$f], 4);
			}
		}
		unset($r);
		return array_values($pos);
	}

	/** Posición por sucursal y producto (mismo disponible que Inventario y Almacenes); $branchIds null = toda la red */
	public function PositionMatrix(?array $branchIds): array
	{
		return $this->PositionRows($branchIds === null ? null : array_map('intval', $branchIds ?: [0]));
	}

	private function ProductInfo(array $ids): array
	{
		$ids = array_values(array_unique(array_map('intval', $ids)));
		if (!$ids)
		{
			return [];
		}
		$out = [];
		foreach (array_chunk($ids, 500) as $chunk)
		{
			foreach ($this->Rows('SELECT p.id, p.name, p.sku, p.min_stock_amount, p.department, IFNULL(qu.name, \'u\') AS unit,
					(SELECT b.barcode FROM product_barcodes b WHERE b.product_id = p.id ORDER BY b.id LIMIT 1) AS barcode
				FROM products p LEFT JOIN quantity_units qu ON qu.id = p.qu_id_stock WHERE p.id IN (' . implode(',', $chunk) . ')') as $r)
			{
				$out[intval($r['id'])] = $r;
			}
		}
		return $out;
	}

	/** Salidas no mermadas (venta POS, despacho, consumo) por sucursal y producto desde $since: unidades y costo */
	private function Exits(?array $scope, string $since): array
	{
		$out = [];
		foreach ($this->Rows("SELECT l.branch_id, sl.product_id, SUM(-sl.amount) AS units, SUM(-sl.amount * IFNULL(NULLIF(sl.price, 0), IFNULL(plp.price, 0))) AS cost
			FROM stock_log sl JOIN locations l ON l.id = sl.location_id LEFT JOIN cache__products_last_purchased plp ON plp.product_id = sl.product_id
			WHERE sl.transaction_type = 'consume' AND sl.undone = 0 AND IFNULL(sl.spoiled, 0) = 0 AND sl.amount < 0 AND IFNULL(l.is_transit, 0) = 0
				AND sl.row_created_timestamp >= :since" . self::In($scope, 'l.branch_id') . ' GROUP BY 1, 2', [':since' => $since]) as $r)
		{
			$out[intval($r['branch_id']) . ':' . intval($r['product_id'])] = ['units' => floatval($r['units']), 'cost' => floatval($r['cost'])];
		}
		return $out;
	}

	/* ================================================================ Resumen del tablero */

	public function Summary($branchId = null): array
	{
		$scope = $this->Scope($branchId);
		$rows = $this->PositionRows($scope);
		$t = ['physical' => 0.0, 'reserved' => 0.0, 'blocked' => 0.0, 'available' => 0.0, 'value' => 0.0, 'bodega' => 0.0, 'sala' => 0.0, 'otra' => 0.0];
		$byBranch = [];
		$overcommitted = 0;
		$negatives = 0;
		foreach ($rows as $r)
		{
			foreach ($t as $k => $_)
			{
				$t[$k] += $r[$k];
			}
			$b = $r['branch_id'];
			$byBranch[$b] ??= ['branch_id' => $b, 'name' => $this->BranchNames()[$b] ?? ('Sucursal ' . $b), 'physical' => 0.0, 'reserved' => 0.0, 'blocked' => 0.0, 'available' => 0.0,
				'value' => 0.0, 'bodega' => 0.0, 'sala' => 0.0, 'products' => 0, 'overcommitted' => 0, 'negative' => 0];
			foreach (['physical', 'reserved', 'blocked', 'available', 'value', 'bodega', 'sala'] as $k)
			{
				$byBranch[$b][$k] += $r[$k];
			}
			$byBranch[$b]['products'] += $r['physical'] > 0 ? 1 : 0;
			if ($r['shortfall'] > 0)
			{
				$overcommitted++;
				$byBranch[$b]['overcommitted']++;
			}
			if ($r['physical'] < 0 || $r['negative_rows'] > 0)
			{
				$negatives++;
				$byBranch[$b]['negative']++;
			}
		}
		$accuracy = $this->Accuracy($scope);
		$exits = $this->Exits($scope, date('Y-m-d 00:00:00', strtotime('-' . self::COVERAGE_DAYS . ' days')));
		$exitsUnits = array_sum(array_column($exits, 'units'));
		$rotExits = $this->Exits($scope, date('Y-m-d 00:00:00', strtotime('-' . self::ROTATION_DAYS . ' days')));
		$rotCost = array_sum(array_column($rotExits, 'cost'));
		$coverage = $exitsUnits > 0 ? round($t['physical'] / ($exitsUnits / self::COVERAGE_DAYS), 1) : null;
		$rotation = $t['value'] > 0 ? round($rotCost * 365 / self::ROTATION_DAYS / $t['value'], 2) : null;

		$transit = $this->TransitTransfers($scope);
		$pendingAdj = intval($this->Val("SELECT COUNT(*) FROM inventory_adjustments WHERE status = 'pending_approval'" . self::In($scope, 'branch_id')));
		$pendingCounts = intval($this->Val("SELECT COUNT(*) FROM stock_cycle_counts WHERE status IN ('pending', 'discrepancy')" . self::In($scope, 'branch_id')));
		$replenish = $this->Replenishment($branchId, 500);
		$cycle = $this->ProcessCycle($scope);

		foreach ($byBranch as &$b)
		{
			foreach (['physical', 'reserved', 'blocked', 'available', 'value', 'bodega', 'sala'] as $k)
			{
				$b[$k] = round($b[$k], 2);
			}
		}
		unset($b);
		usort($byBranch, fn($a, $b) => $b['value'] <=> $a['value']);

		return [
			'generated_at' => date('Y-m-d H:i:s'),
			'branch_id' => $scope !== null && count($scope) === 1 ? $scope[0] : null,
			'kpis' => [
				'physical' => round($t['physical'], 2), 'reserved' => round($t['reserved'], 2), 'blocked' => round($t['blocked'], 2), 'available' => round($t['available'], 2),
				'value' => round($t['value'], 2), 'bodega' => round($t['bodega'], 2), 'sala' => round($t['sala'], 2), 'other_locations' => round($t['otra'], 2),
				'accuracy_pct' => $accuracy['pct'], 'accuracy_counts' => $accuracy['counts'], 'accuracy_hits' => $accuracy['hits'],
				'coverage_days' => $coverage, 'coverage_note' => $coverage === null ? 'Sin salidas en ' . self::COVERAGE_DAYS . ' días: la cobertura no se puede calcular' : ($coverage > self::EXTREME_COVERAGE_DAYS ? 'Más de un año de inventario al ritmo de salidas actual' : null),
				'rotation' => $rotation, 'rotation_note' => $rotation === null ? 'Sin inventario valorizado' : ($rotation > self::EXTREME_ROTATION ? 'Inventario muy bajo frente a las salidas: revisar costo o existencias' : null),
				'negative' => $negatives, 'overcommitted' => $overcommitted,
				'transit_open' => count($transit), 'transit_late' => count(array_filter($transit, fn($x) => $x['late'])),
				'adjustments_pending' => $pendingAdj, 'counts_open' => $pendingCounts, 'replenishment_suggested' => count($replenish['suggestions']), 'replenishment_tasks' => count($replenish['tasks']),
			],
			'formulas' => [
				'available' => 'Disponible = físico − cuarentena − vencido − merma por aprobar − reservado',
				'accuracy' => 'Exactitud = conteos dentro de ±' . self::ACCURACY_TOLERANCE_PCT . ' % ÷ conteos ejecutados en ' . self::ACCURACY_DAYS . ' días',
				'coverage' => 'Cobertura = unidades físicas ÷ salidas diarias promedio (' . self::COVERAGE_DAYS . ' días; venta, despacho y consumo, sin mermas)',
				'rotation' => 'Rotación anual = costo de salidas de ' . self::ROTATION_DAYS . ' días × 365/' . self::ROTATION_DAYS . ' ÷ valor actual del inventario',
			],
			'branches' => array_values($byBranch),
			'cycle' => $cycle,
		];
	}

	private function Accuracy(?array $scope): array
	{
		$r = $this->Row("SELECT COUNT(*) AS n,
				SUM(CASE WHEN ABS(IFNULL(difference_amount, 0)) <= (CASE WHEN IFNULL(theoretical_stock, 0) = 0 THEN 0.0001 ELSE ABS(theoretical_stock) * " . (self::ACCURACY_TOLERANCE_PCT / 100) . " END) THEN 1 ELSE 0 END) AS hits
			FROM stock_cycle_counts WHERE status IN ('completed', 'discrepancy', 'adjusted') AND executed_date >= :d" . self::In($scope, 'branch_id'), [':d' => date('Y-m-d', strtotime('-' . self::ACCURACY_DAYS . ' days'))]);
		$n = intval($r['n'] ?? 0);
		return ['counts' => $n, 'hits' => intval($r['hits'] ?? 0), 'pct' => $n > 0 ? round(intval($r['hits']) / $n * 100, 1) : null];
	}

	/** Ciclo obligatorio con su carga actual: recepción → ubicación → almacenamiento → reposición → salida → conciliación */
	private function ProcessCycle(?array $scope): array
	{
		$since = date('Y-m-d 00:00:00', strtotime('-7 days'));
		$mov = [];
		foreach ($this->Rows('SELECT bucket, COUNT(*) AS n, SUM(ABS(amount)) AS u FROM merchandise_movements WHERE undone = 0 AND recorded_at >= :s' . self::In($scope, 'branch_id') . ' GROUP BY bucket', [':s' => $since]) as $r)
		{
			$mov[$r['bucket']] = ['n' => intval($r['n']), 'u' => round(floatval($r['u']), 2)];
		}
		$val = fn(string $sql) => intval($this->Val($sql));
		$exitN = ($mov['sales']['n'] ?? 0) + ($mov['dispatches']['n'] ?? 0) + ($mov['waste']['n'] ?? 0) + ($mov['transfers_out']['n'] ?? 0);
		return [
			['code' => 'reception', 'label' => 'Recepción', 'owner' => 'Compras', 'done_7d' => $mov['receptions']['n'] ?? 0, 'pending' => $val("SELECT COUNT(*) FROM stock_transfers WHERE status IN ('in_transit', 'sent', 'partially_received')" . self::In($scope, 'to_branch_id')), 'pending_label' => 'traslados por recibir', 'href' => '/transfers'],
			['code' => 'putaway', 'label' => 'Ubicación', 'owner' => 'Almacén', 'done_7d' => $val("SELECT COUNT(*) FROM wms_putaway_tasks WHERE status IN ('completado', 'completed') AND IFNULL(completed_at, created_at) >= '$since'" . self::In($scope, 'branch_id')), 'pending' => $val("SELECT COUNT(*) FROM wms_putaway_tasks WHERE status NOT IN ('completado', 'completed', 'cancelado')" . self::In($scope, 'branch_id')), 'pending_label' => 'tareas de ubicación', 'href' => '/warehouse'],
			['code' => 'storage', 'label' => 'Almacenamiento', 'owner' => 'Almacén', 'done_7d' => null, 'pending' => $val("SELECT COUNT(*) FROM quarantine_items WHERE status = 'in_quarantine'" . self::In($scope, 'branch_id')), 'pending_label' => 'lotes en cuarentena', 'href' => '/locations/map'],
			['code' => 'replenishment', 'label' => 'Reposición', 'owner' => 'Inventario', 'done_7d' => $val("SELECT COUNT(*) FROM wms_internal_movements WHERE movement_type = 'sala_ventas' AND status = 'completado' AND IFNULL(completed_at, created_at) >= '$since'" . self::In($scope, 'branch_id')), 'pending' => $val("SELECT COUNT(*) FROM wms_internal_movements WHERE movement_type = 'sala_ventas' AND status = 'pendiente'" . self::In($scope, 'branch_id')), 'pending_label' => 'reposiciones por confirmar', 'href' => '#replenishment'],
			['code' => 'exit', 'label' => 'Salida', 'owner' => 'POS · Despachos · Mermas', 'done_7d' => $exitN, 'pending' => $val("SELECT COUNT(*) FROM customer_orders WHERE status IN ('" . implode("', '", CustomerOrderService::RESERVING) . "')" . self::In($scope, 'branch_id')), 'pending_label' => 'pedidos con reserva', 'href' => '#reservations'],
			['code' => 'reconciliation', 'label' => 'Conciliación', 'owner' => 'Inventario', 'done_7d' => $val("SELECT COUNT(*) FROM stock_cycle_counts WHERE status IN ('completed', 'adjusted') AND executed_date >= '" . substr($since, 0, 10) . "'" . self::In($scope, 'branch_id')), 'pending' => $val("SELECT COUNT(*) FROM stock_cycle_counts WHERE status IN ('pending', 'discrepancy')" . self::In($scope, 'branch_id')) + $val("SELECT COUNT(*) FROM inventory_adjustments WHERE status = 'pending_approval'" . self::In($scope, 'branch_id')), 'pending_label' => 'conteos y ajustes abiertos', 'href' => '#counts'],
		];
	}

	/* ================================================================ Existencias por producto y por ubicación */

	public function Positions(array $f): array
	{
		$scope = $this->Scope($f['branch_id'] ?? null);
		$rows = $this->PositionRows($scope);
		$info = $this->ProductInfo(array_column($rows, 'product_id'));
		$q = mb_strtolower(trim(strval($f['q'] ?? '')));
		$status = strval($f['status'] ?? '');
		$out = [];
		foreach ($rows as $r)
		{
			$p = $info[$r['product_id']] ?? null;
			if (!$p)
			{
				continue;
			}
			if ($q !== '' && !str_contains(mb_strtolower($p['name'] . ' ' . $p['sku'] . ' ' . $p['barcode']), $q))
			{
				continue;
			}
			$min = floatval($p['min_stock_amount']);
			$state = $r['physical'] < 0 ? 'negative' : ($r['shortfall'] > 0 ? 'overcommitted' : ($r['physical'] <= 0 ? ($min > 0 ? 'out' : 'empty') : ($min > 0 && $r['available'] < $min ? 'below_min' : 'ok')));
			if ($status !== '' && $status !== $state)
			{
				continue;
			}
			if ($state === 'empty' && $q === '' && $status === '')
			{
				continue;
			}
			$out[] = $r + ['name' => $p['name'], 'sku' => $p['sku'], 'barcode' => $p['barcode'], 'unit' => $p['unit'], 'min_stock' => $min, 'state' => $state,
				'branch_name' => $this->BranchNames()[$r['branch_id']] ?? ''];
		}
		$rank = ['negative' => 0, 'overcommitted' => 1, 'out' => 2, 'below_min' => 3, 'ok' => 4, 'empty' => 5];
		usort($out, fn($a, $b) => [$rank[$a['state']], -$a['value']] <=> [$rank[$b['state']], -$b['value']]);
		$limit = max(1, min(1000, intval($f['limit'] ?? 200)));
		return ['total' => count($out), 'rows' => array_slice($out, 0, $limit)];
	}

	public function Locations($branchId = null): array
	{
		$scope = $this->Scope($branchId);
		$today = date('Y-m-d');
		$rows = $this->Rows("SELECT l.id, l.name, l.branch_id, l.zone_type, " . self::ZoneSql() . " AS zgroup, l.zone, l.aisle, l.shelf, l.bin, l.label_code, l.capacity_units, l.storage_class,
				IFNULL(SUM(s.amount), 0) AS units, COUNT(DISTINCT s.product_id) AS products, COUNT(s.id) AS lots,
				IFNULL(SUM(s.amount * IFNULL(NULLIF(s.price, 0), IFNULL(plp.price, 0))), 0) AS value,
				SUM(CASE WHEN s.best_before_date < '$today' AND s.best_before_date <> '2999-12-31' THEN s.amount ELSE 0 END) AS expired,
				SUM(CASE WHEN s.amount < 0 THEN 1 ELSE 0 END) AS negative_rows
			FROM locations l LEFT JOIN stock s ON s.location_id = l.id LEFT JOIN cache__products_last_purchased plp ON plp.product_id = s.product_id
			WHERE l.active = 1 AND IFNULL(l.is_transit, 0) = 0" . self::In($scope, 'l.branch_id') . '
			GROUP BY l.id ORDER BY l.branch_id, zgroup, l.name');
		foreach ($rows as &$r)
		{
			$cap = floatval($r['capacity_units']);
			$r['occupancy_pct'] = $cap > 0 ? round(floatval($r['units']) / $cap * 100, 1) : null;
			$r['occupancy_note'] = $cap > 0 && floatval($r['units']) > $cap ? 'Sobre la capacidad registrada: revisar capacidad o ubicación' : null;
			$r['zone_label'] = self::ZONES[$r['zgroup']];
			$r['branch_name'] = $this->BranchNames()[intval($r['branch_id'])] ?? '';
			foreach (['units', 'value', 'expired'] as $k)
			{
				$r[$k] = round(floatval($r[$k]), 2);
			}
		}
		unset($r);
		$zones = [];
		foreach ($rows as $r)
		{
			$z = &$zones[$r['zgroup']];
			$z ??= ['zone' => $r['zgroup'], 'label' => $r['zone_label'], 'locations' => 0, 'units' => 0.0, 'value' => 0.0];
			$z['locations']++;
			$z['units'] += $r['units'];
			$z['value'] += $r['value'];
			unset($z);
		}
		return ['zones' => array_values($zones), 'locations' => $rows];
	}

	/* ================================================================ Kardex detallado */

	/** Kardex de un producto (sucursal o red): cada movimiento físico con saldo corrido reconstruido desde el saldo actual */
	public function Kardex(int $productId, $branchId = null, ?string $from = null, ?string $to = null, int $limit = 300): array
	{
		if ($productId <= 0)
		{
			throw new \Exception('Seleccione un producto');
		}
		$scope = $this->Scope($branchId);
		$from = $from && IsIsoDate($from) ? $from : date('Y-m-d', strtotime('-30 days'));
		$to = $to && IsIsoDate($to) ? $to : date('Y-m-d');
		$limit = max(10, min(2000, $limit));
		$balance = floatval($this->Val('SELECT IFNULL(SUM(s.amount), 0) FROM stock s JOIN locations l ON l.id = s.location_id WHERE s.product_id = :p AND IFNULL(l.is_transit, 0) = 0' . self::In($scope, 'l.branch_id'), [':p' => $productId]));
		// Movimientos posteriores a $to: se descuentan para obtener el saldo al cierre del rango
		$after = floatval($this->Val('SELECT IFNULL(SUM(m.amount), 0) FROM merchandise_movements m WHERE m.product_id = :p AND m.undone = 0 AND IFNULL(m.is_transit, 0) = 0 AND date(m.recorded_at) > :t' . self::In($scope, 'm.branch_id'), [':p' => $productId, ':t' => $to]));
		$rows = $this->Rows('SELECT m.id, m.movement_code, m.operation_ref, m.transaction_type, m.bucket, m.amount, m.unit_cost, m.value, m.stock_id, m.location_id, m.location_name, m.branch_id,
				m.user_id, m.note, m.undone, m.recorded_at, dl.lot_number, s.best_before_date, u.display_name AS user_name
			FROM merchandise_movements m LEFT JOIN dairy_stock_lots dl ON dl.stock_id = m.stock_id LEFT JOIN stock_log s ON s.id = m.id LEFT JOIN users_dto u ON u.id = m.user_id
			WHERE m.product_id = :p AND IFNULL(m.is_transit, 0) = 0 AND date(m.recorded_at) BETWEEN :f AND :t' . self::In($scope, 'm.branch_id') . '
			ORDER BY m.recorded_at DESC, m.id DESC LIMIT ' . $limit, [':p' => $productId, ':f' => $from, ':t' => $to]);
		$run = $balance - $after;
		$closing = $run;
		$in = $out = 0.0;
		foreach ($rows as &$r)
		{
			$amt = floatval($r['amount']);
			$r['amount'] = $amt;
			$r['balance_after'] = intval($r['undone']) ? null : round($run, 4);
			if (!intval($r['undone']))
			{
				$run -= $amt;
				$amt > 0 ? $in += $amt : $out += -$amt;
			}
			[$label, $module] = self::SOURCES[$r['bucket']] ?? [$r['transaction_type'], 'Inventario'];
			$r['movement_label'] = $label;
			$r['source_module'] = $module;
			$r['branch_name'] = $this->BranchNames()[intval($r['branch_id'])] ?? '';
		}
		unset($r);
		$p = $this->ProductInfo([$productId])[$productId] ?? null;
		// Saldo inicial reconstruido negativo = hay existencias sin movimiento que las explique: se informa, no se muestra un saldo imposible
		$opening = count($rows) < $limit ? round($run, 4) : null;
		$note = null;
		if ($opening !== null && $opening < -0.0001)
		{
			$note = 'El kardex no explica ' . round(-$opening, 2) . ' u del saldo actual (existencias cargadas sin movimiento). Programe un conteo y concilie con un ajuste aprobado.';
			$opening = null;
		}
		return ['product' => $p, 'from' => $from, 'to' => $to, 'opening_balance' => $opening, 'reconciliation_note' => $note, 'closing_balance' => round($closing, 4),
			'current_balance' => round($balance, 4), 'entries' => round($in, 4), 'exits' => round($out, 4), 'truncated' => count($rows) >= $limit, 'rows' => $rows];
	}

	/* ================================================================ Anomalías y conciliación */

	public function Anomalies($branchId = null): array
	{
		$scope = $this->Scope($branchId);
		$rows = $this->PositionRows($scope);
		$info = $this->ProductInfo(array_merge(array_column(array_filter($rows, fn($r) => $r['physical'] < 0 || $r['negative_rows'] > 0 || $r['shortfall'] > 0), 'product_id')));
		$name = fn($pid) => $info[$pid]['name'] ?? ('Producto ' . $pid);
		$bn = $this->BranchNames();
		$items = [];
		foreach ($rows as $r)
		{
			if ($r['physical'] < 0 || $r['negative_rows'] > 0)
			{
				$items[] = ['type' => 'negative', 'severity' => 'critical', 'product_id' => $r['product_id'], 'product' => $name($r['product_id']), 'branch_id' => $r['branch_id'], 'branch' => $bn[$r['branch_id']] ?? '',
					'value' => $r['physical'], 'detail' => 'Stock negativo sin justificar (' . $r['negative_rows'] . ' lote(s) bajo cero). Programar conteo y revisar la última salida.', 'action' => 'count'];
			}
			if ($r['shortfall'] > 0)
			{
				$items[] = ['type' => 'overcommitted', 'severity' => 'high', 'product_id' => $r['product_id'], 'product' => $name($r['product_id']), 'branch_id' => $r['branch_id'], 'branch' => $bn[$r['branch_id']] ?? '',
					'value' => $r['shortfall'], 'detail' => 'Reservado + bloqueado supera al físico en ' . $r['shortfall'] . ' u (físico ' . $r['physical'] . ', reservado ' . $r['reserved'] . ', bloqueado ' . $r['blocked'] . ').', 'action' => 'count'];
			}
		}
		// Conteos con diferencia relativa extrema: se explican, no se ocultan
		foreach ($this->Rows("SELECT c.id, c.product_id, p.name, c.branch_id, c.theoretical_stock, c.counted_stock, c.difference_amount, c.difference_cost, c.status, c.executed_date
			FROM stock_cycle_counts c JOIN products p ON p.id = c.product_id
			WHERE c.status = 'discrepancy'" . self::In($scope, 'c.branch_id') . ' ORDER BY ABS(c.difference_cost) DESC LIMIT 100') as $c)
		{
			$theo = floatval($c['theoretical_stock']);
			$pct = $theo != 0.0 ? round(floatval($c['difference_amount']) / abs($theo) * 100, 1) : null;
			$why = $pct === null ? 'El sistema tenía 0 y se contaron ' . floatval($c['counted_stock']) . ' u: posible recepción sin registrar o ubicación equivocada.'
				: (abs($pct) > 100 ? 'Diferencia mayor al 100 % del saldo: posible conteo en otra unidad de medida o movimiento sin registrar.' : 'Diferencia de ' . $pct . ' % frente al sistema.');
			$items[] = ['type' => 'count_difference', 'severity' => abs(floatval($c['difference_cost'])) >= 100 || $pct === null || abs($pct) > 100 ? 'high' : 'medium', 'product_id' => intval($c['product_id']), 'product' => $c['name'],
				'branch_id' => intval($c['branch_id']), 'branch' => $bn[intval($c['branch_id'])] ?? '', 'value' => floatval($c['difference_amount']), 'pct' => $pct,
				'detail' => $why . ' Costo $' . number_format(floatval($c['difference_cost']), 2) . '. Pendiente de aprobación: el stock no cambia hasta aprobar.', 'action' => 'approve_count', 'ref_id' => intval($c['id'])];
		}
		foreach ($this->TransitTransfers($scope) as $tr)
		{
			if ($tr['late'])
			{
				$items[] = ['type' => 'transfer_unconfirmed', 'severity' => 'high', 'product_id' => null, 'product' => $tr['code'], 'branch_id' => $tr['to_branch_id'], 'branch' => $tr['to_branch'],
					'value' => $tr['sent'], 'detail' => $tr['units'] <= 0
						? 'Traslado enviado hace ' . $tr['hours'] . ' h con cantidades recibidas cargadas pero sin cerrar la recepción en ' . $tr['to_branch'] . ': el documento sigue en tránsito.'
						: 'Traslado enviado hace ' . $tr['hours'] . ' h sin confirmación de recepción en ' . $tr['to_branch'] . ' (' . $tr['units'] . ' u pendientes). La mercancía sigue en tránsito y no está disponible.', 'action' => 'transfer', 'ref_id' => $tr['id']];
			}
		}
		foreach ($this->Rows("SELECT ia.id, ia.adjustment_number, ia.product_id, p.name, ia.branch_id, ia.difference_amount FROM inventory_adjustments ia JOIN products p ON p.id = ia.product_id
			WHERE ia.status = 'approved' AND IFNULL(ia.inventory_applied, 0) = 0" . self::In($scope, 'ia.branch_id')) as $a)
		{
			$items[] = ['type' => 'adjustment_not_applied', 'severity' => 'medium', 'product_id' => intval($a['product_id']), 'product' => $a['name'], 'branch_id' => intval($a['branch_id']), 'branch' => $bn[intval($a['branch_id'])] ?? '',
				'value' => floatval($a['difference_amount']), 'detail' => 'Ajuste ' . $a['adjustment_number'] . ' aprobado sin movimiento en el kardex: el stock no refleja la aprobación.', 'action' => 'review'];
		}
		$dup = InventoryMovementService::GetInstance()->DuplicateCheck(7, 5);
		foreach ($dup['possible_duplicates'] as $d)
		{
			$items[] = ['type' => 'duplicate', 'severity' => 'high', 'product_id' => intval($d['product_id']), 'product' => $d['product_name'], 'branch_id' => null, 'branch' => '',
				'value' => floatval($d['amount']), 'detail' => 'Movimiento ' . $d['transaction_type'] . ' repetido con distinta transacción en ≤ 5 s (' . $d['first_at'] . '). Revisar y deshacer el duplicado desde el kardex.', 'action' => 'kardex', 'ref_id' => $d['second_tx']];
		}
		$sev = ['critical' => 0, 'high' => 1, 'medium' => 2, 'low' => 3];
		usort($items, fn($a, $b) => $sev[$a['severity']] <=> $sev[$b['severity']]);
		$counts = [];
		foreach ($items as $i)
		{
			$counts[$i['type']] = ($counts[$i['type']] ?? 0) + 1;
		}
		return ['counts' => $counts, 'total' => count($items), 'items' => $items, 'ledger_seal' => $dup['ledger_seal']];
	}

	private function TransitTransfers(?array $scope): array
	{
		$bn = $this->BranchNames();
		$out = [];
		foreach ($this->Rows("SELECT t.id, t.transfer_code, t.from_branch_id, t.to_branch_id, t.status, t.sent_at, IFNULL(SUM(i.sent_amount - IFNULL(i.received_amount, 0)), 0) AS units, IFNULL(SUM(i.sent_amount), 0) AS sent, t.total_sent_cost
			FROM stock_transfers t LEFT JOIN stock_transfer_items i ON i.transfer_id = t.id
			WHERE t.status IN ('in_transit', 'sent', 'partially_received')" . ($scope === null ? '' : ' AND (t.to_branch_id IN (' . implode(',', $scope) . ') OR t.from_branch_id IN (' . implode(',', $scope) . '))') . '
			GROUP BY t.id ORDER BY t.sent_at') as $t)
		{
			$hours = $t['sent_at'] ? intval(floor((time() - strtotime($t['sent_at'])) / 3600)) : null;
			$out[] = ['id' => intval($t['id']), 'code' => $t['transfer_code'], 'from_branch_id' => intval($t['from_branch_id']), 'from_branch' => $bn[intval($t['from_branch_id'])] ?? '',
				'to_branch_id' => intval($t['to_branch_id']), 'to_branch' => $bn[intval($t['to_branch_id'])] ?? '', 'status' => $t['status'], 'sent_at' => $t['sent_at'], 'hours' => $hours,
				'late' => $hours !== null && $hours >= self::TRANSIT_LATE_HOURS, 'units' => round(floatval($t['units']), 2), 'sent' => round(floatval($t['sent']), 2), 'cost' => round(floatval($t['total_sent_cost']), 2)];
		}
		return $out;
	}

	public function Transfers($branchId = null): array
	{
		return ['late_hours' => self::TRANSIT_LATE_HOURS, 'rows' => $this->TransitTransfers($this->Scope($branchId))];
	}

	/* ================================================================ Antigüedad, cobertura, rotación */

	public function Aging($branchId = null): array
	{
		$scope = $this->Scope($branchId);
		$buckets = [['0-30', 0, 30], ['31-60', 31, 60], ['61-90', 61, 90], ['91-180', 91, 180], ['181+', 181, null]];
		$rows = $this->Rows("SELECT s.product_id, p.name, l.branch_id, s.amount, IFNULL(NULLIF(s.price, 0), IFNULL(plp.price, 0)) AS cost,
				CAST(julianday('now', 'localtime') - julianday(IFNULL(s.purchased_date, s.row_created_timestamp)) AS INTEGER) AS age
			FROM stock s JOIN locations l ON l.id = s.location_id JOIN products p ON p.id = s.product_id LEFT JOIN cache__products_last_purchased plp ON plp.product_id = s.product_id
			WHERE s.amount > 0 AND IFNULL(l.is_transit, 0) = 0" . self::In($scope, 'l.branch_id'));
		$agg = [];
		foreach ($buckets as [$label])
		{
			$agg[$label] = ['bucket' => $label, 'units' => 0.0, 'value' => 0.0, 'lots' => 0];
		}
		$old = [];
		$totalVal = 0.0;
		foreach ($rows as $r)
		{
			$age = max(0, intval($r['age']));
			foreach ($buckets as [$label, $min, $max])
			{
				if ($age >= $min && ($max === null || $age <= $max))
				{
					$v = floatval($r['amount']) * floatval($r['cost']);
					$agg[$label]['units'] += floatval($r['amount']);
					$agg[$label]['value'] += $v;
					$agg[$label]['lots']++;
					$totalVal += $v;
					break;
				}
			}
			if ($age > 90)
			{
				$k = $r['product_id'] . ':' . $r['branch_id'];
				$old[$k] ??= ['product_id' => intval($r['product_id']), 'name' => $r['name'], 'branch' => $this->BranchNames()[intval($r['branch_id'])] ?? '', 'units' => 0.0, 'value' => 0.0, 'max_age' => 0];
				$old[$k]['units'] += floatval($r['amount']);
				$old[$k]['value'] += floatval($r['amount']) * floatval($r['cost']);
				$old[$k]['max_age'] = max($old[$k]['max_age'], $age);
			}
		}
		foreach ($agg as &$a)
		{
			$a['units'] = round($a['units'], 2);
			$a['value'] = round($a['value'], 2);
			$a['share_pct'] = $totalVal > 0 ? round($a['value'] / $totalVal * 100, 1) : null;
		}
		unset($a);
		usort($old, fn($a, $b) => $b['value'] <=> $a['value']);
		return ['basis' => 'Edad del lote desde su fecha de compra (o de ingreso si no la tiene)', 'buckets' => array_values($agg), 'oldest' => array_slice(array_map(fn($o) => array_merge($o, ['units' => round($o['units'], 2), 'value' => round($o['value'], 2)]), $old), 0, 50)];
	}

	public function Coverage($branchId = null, int $limit = 100, string $sort = 'risk'): array
	{
		$scope = $this->Scope($branchId);
		$rows = $this->PositionRows($scope);
		$ex28 = $this->Exits($scope, date('Y-m-d 00:00:00', strtotime('-' . self::COVERAGE_DAYS . ' days')));
		$ex90 = $this->Exits($scope, date('Y-m-d 00:00:00', strtotime('-' . self::ROTATION_DAYS . ' days')));
		$info = $this->ProductInfo(array_column($rows, 'product_id'));
		$out = [];
		foreach ($rows as $r)
		{
			if ($r['physical'] <= 0 && !isset($ex28[$r['branch_id'] . ':' . $r['product_id']]))
			{
				continue;
			}
			$k = $r['branch_id'] . ':' . $r['product_id'];
			$daily = ($ex28[$k]['units'] ?? 0) / self::COVERAGE_DAYS;
			$cov = $daily > 0 ? round(max(0, $r['physical']) / $daily, 1) : null;
			$rot = $r['value'] > 0 ? round(($ex90[$k]['cost'] ?? 0) * 365 / self::ROTATION_DAYS / $r['value'], 2) : null;
			$note = null;
			if ($cov === null)
			{
				$note = 'Sin salidas en ' . self::COVERAGE_DAYS . ' días (inventario inmóvil)';
			}
			elseif ($cov > self::EXTREME_COVERAGE_DAYS)
			{
				$note = 'Cobertura mayor a un año: salidas muy bajas frente al stock';
			}
			elseif ($rot !== null && $rot > self::EXTREME_ROTATION)
			{
				$note = 'Rotación extrema: stock casi agotado frente a las salidas o costo del lote incompleto';
			}
			$p = $info[$r['product_id']] ?? ['name' => 'Producto ' . $r['product_id'], 'sku' => ''];
			$out[] = ['product_id' => $r['product_id'], 'name' => $p['name'], 'sku' => $p['sku'], 'branch_id' => $r['branch_id'], 'branch' => $this->BranchNames()[$r['branch_id']] ?? '',
				'physical' => $r['physical'], 'available' => $r['available'], 'value' => round($r['value'], 2), 'daily_exits' => round($daily, 2), 'coverage_days' => $cov, 'rotation' => $rot,
				'state' => $cov === null ? 'stagnant' : ($cov < 3 ? 'short' : ($cov > 60 ? 'excess' : 'ok')), 'note' => $note];
		}
		$rank = ['short' => 0, 'stagnant' => 1, 'excess' => 2, 'ok' => 3];
		if ($sort === 'value')
		{
			usort($out, fn($a, $b) => $b['value'] <=> $a['value']);
		}
		else
		{
			usort($out, fn($a, $b) => [$rank[$a['state']], -$a['value']] <=> [$rank[$b['state']], -$b['value']]);
		}
		$counts = array_count_values(array_column($out, 'state'));
		return ['window_days' => self::COVERAGE_DAYS, 'rotation_days' => self::ROTATION_DAYS, 'counts' => $counts, 'total' => count($out), 'rows' => array_slice($out, 0, max(1, min(1000, $limit)))];
	}

	/* ================================================================ Lotes y vencimientos */

	public function Lots($branchId = null, int $days = 30, string $q = ''): array
	{
		$scope = $this->Scope($branchId);
		$days = max(1, min(365, $days));
		$limitDate = date('Y-m-d', strtotime("+$days days"));
		$params = [':lim' => $limitDate];
		$qSql = '';
		if (trim($q) !== '')
		{
			$qSql = ' AND (p.name LIKE :q OR IFNULL(p.sku, \'\') LIKE :q OR IFNULL(dl.lot_number, \'\') LIKE :q OR s.stock_id LIKE :q)';
			$params[':q'] = '%' . trim($q) . '%';
		}
		$rows = $this->Rows("SELECT s.id, s.stock_id, s.product_id, p.name, p.sku, s.amount, s.best_before_date, s.purchased_date, l.name AS location, l.branch_id, " . self::ZoneSql() . " AS zone,
				dl.lot_number, dl.serial_number, IFNULL(NULLIF(s.price, 0), 0) AS cost,
				CAST(julianday(s.best_before_date) - julianday(date('now', 'localtime')) AS INTEGER) AS days_left,
				EXISTS (SELECT 1 FROM quarantine_items q WHERE q.stock_id = s.stock_id AND q.status = 'in_quarantine') AS in_quarantine
			FROM stock s JOIN locations l ON l.id = s.location_id JOIN products p ON p.id = s.product_id LEFT JOIN dairy_stock_lots dl ON dl.stock_id = s.stock_id
			WHERE s.amount > 0 AND IFNULL(l.is_transit, 0) = 0 AND s.best_before_date <> '2999-12-31' AND s.best_before_date <= :lim" . self::In($scope, 'l.branch_id') . $qSql . '
			ORDER BY s.best_before_date, p.name LIMIT 500', $params);
		foreach ($rows as &$r)
		{
			$r['branch'] = $this->BranchNames()[intval($r['branch_id'])] ?? '';
			$r['value'] = round(floatval($r['amount']) * floatval($r['cost']), 2);
			$r['state'] = intval($r['days_left']) < 0 ? 'expired' : (intval($r['days_left']) <= 7 ? 'critical' : 'warning');
		}
		unset($r);
		return ['days' => $days, 'fefo_rule' => 'Salida FEFO: primero vence, primero sale. Los vencidos no cuentan como disponibles.', 'rows' => $rows];
	}

	/* ================================================================ Reposición de góndolas (bodega → sala) */

	/**
	 * Sugerencias: productos con mínimo configurado cuya existencia en sala está por debajo del mínimo y que tienen stock
	 * en bodega de la misma sucursal. Cantidad = lo que falta en sala hasta el mínimo, limitada a lo que hay en bodega.
	 */
	public function Replenishment($branchId = null, int $limit = 200): array
	{
		$scope = $this->Scope($branchId);
		$zone = self::ZoneSql();
		$rows = $this->Rows("SELECT l.branch_id, s.product_id, p.name, p.sku, p.min_stock_amount,
				SUM(CASE WHEN $zone = 'sala' THEN s.amount ELSE 0 END) AS sala,
				SUM(CASE WHEN $zone = 'bodega' AND (s.best_before_date >= date('now', 'localtime') OR s.best_before_date = '2999-12-31') THEN s.amount ELSE 0 END) AS bodega
			FROM stock s JOIN locations l ON l.id = s.location_id JOIN products p ON p.id = s.product_id
			WHERE IFNULL(l.is_transit, 0) = 0 AND p.active = 1 AND p.min_stock_amount > 0" . self::In($scope, 'l.branch_id') . '
			GROUP BY l.branch_id, s.product_id HAVING sala < p.min_stock_amount AND bodega > 0');
		$open = [];
		foreach ($this->Rows("SELECT branch_id, product_id FROM wms_internal_movements WHERE movement_type = 'sala_ventas' AND status = 'pendiente'" . self::In($scope, 'branch_id')) as $o)
		{
			$open[$o['branch_id'] . ':' . $o['product_id']] = true;
		}
		$sug = [];
		foreach ($rows as $r)
		{
			$need = floatval($r['min_stock_amount']) - floatval($r['sala']);
			$qty = round(min(floatval($r['bodega']), $need), 2);
			if ($qty <= 0)
			{
				continue;
			}
			$sug[] = ['branch_id' => intval($r['branch_id']), 'branch' => $this->BranchNames()[intval($r['branch_id'])] ?? '', 'product_id' => intval($r['product_id']), 'name' => $r['name'], 'sku' => $r['sku'],
				'min_stock' => floatval($r['min_stock_amount']), 'sala' => round(floatval($r['sala']), 2), 'bodega' => round(floatval($r['bodega']), 2), 'suggested' => $qty,
				'task_open' => isset($open[$r['branch_id'] . ':' . $r['product_id']]), 'priority' => floatval($r['sala']) <= 0 ? 'high' : 'normal'];
		}
		usort($sug, fn($a, $b) => [$a['priority'] === 'high' ? 0 : 1, $a['sala']] <=> [$b['priority'] === 'high' ? 0 : 1, $b['sala']]);
		$tasks = $this->Rows("SELECT m.*, lf.name AS from_location, lt.name AS to_location, u.display_name AS operator FROM wms_internal_movements m
			LEFT JOIN locations lf ON lf.id = m.from_location_id LEFT JOIN locations lt ON lt.id = m.to_location_id LEFT JOIN users_dto u ON u.id = m.operator_user_id
			WHERE m.movement_type = 'sala_ventas' AND m.status = 'pendiente'" . self::In($scope, 'm.branch_id') . ' ORDER BY m.created_at');
		return ['rule' => 'Sugerido = mínimo − existencia en sala, sin superar el stock vigente en bodega', 'suggestions' => array_slice($sug, 0, max(1, $limit)), 'tasks' => $tasks];
	}

	/** Crea tareas de reposición (pendientes; el stock no se mueve hasta confirmar). Omite las que ya tienen tarea abierta. */
	public function CreateReplenishmentTasks(int $branchId, array $items): array
	{
		if (!DairyAccess::CanSeeBranch($branchId))
		{
			throw new \Exception('No tiene acceso a esa sucursal');
		}
		$db = $this->Db();
		$created = $skipped = 0;
		$db->beginTransaction();
		try
		{
			foreach ($items as $it)
			{
				$pid = intval($it['product_id'] ?? 0);
				$qty = round(floatval($it['amount'] ?? 0), 4);
				if ($pid <= 0 || $qty <= 0)
				{
					$skipped++;
					continue;
				}
				if (intval($this->Val("SELECT COUNT(*) FROM wms_internal_movements WHERE movement_type = 'sala_ventas' AND status = 'pendiente' AND branch_id = :b AND product_id = :p", [':b' => $branchId, ':p' => $pid])) > 0)
				{
					$skipped++;
					continue;
				}
				$from = $this->Row("SELECT s.location_id, SUM(s.amount) AS q FROM stock s JOIN locations l ON l.id = s.location_id
					WHERE s.product_id = :p AND l.branch_id = :b AND l.zone_type = 'bodega' AND IFNULL(l.is_transit, 0) = 0 GROUP BY s.location_id HAVING q > 0 ORDER BY q DESC LIMIT 1", [':p' => $pid, ':b' => $branchId]);
				$to = $this->Row("SELECT l.id FROM locations l LEFT JOIN stock s ON s.location_id = l.id AND s.product_id = :p
					WHERE l.branch_id = :b AND l.zone_type = 'piso_venta' AND l.active = 1 GROUP BY l.id ORDER BY IFNULL(SUM(s.amount), 0) DESC, l.id LIMIT 1", [':p' => $pid, ':b' => $branchId]);
				if (!$from || !$to)
				{
					$skipped++;
					continue;
				}
				$p = $this->Row('SELECT name, sku FROM products WHERE id = :p', [':p' => $pid]);
				$st = $db->prepare("INSERT INTO wms_internal_movements (branch_id, product_id, sku, product_name, from_location_id, to_location_id, amount, movement_type, status, operator_user_id, created_at, notes)
					VALUES (:b, :p, :sku, :n, :f, :t, :a, 'sala_ventas', 'pendiente', :u, :c, :notes)");
				$st->execute([':b' => $branchId, ':p' => $pid, ':sku' => $p['sku'] ?? '', ':n' => $p['name'] ?? '', ':f' => $from['location_id'], ':t' => $to['id'], ':a' => min($qty, floatval($from['q'])),
					':u' => self::UserId() ?: null, ':c' => date('Y-m-d H:i:s'), ':notes' => 'Reposición de góndola']);
				$created++;
			}
			$db->commit();
		}
		catch (\Throwable $e)
		{
			$db->rollBack();
			throw $e;
		}
		return ['created' => $created, 'skipped' => $skipped, 'message' => "$created tarea(s) de reposición creadas" . ($skipped ? " · $skipped omitidas (ya abiertas o sin bodega/sala)" : '')];
	}

	/** Confirma la reposición: traslado interno bodega → sala en stock_log, con la cantidad realmente movida */
	public function ConfirmReplenishment(int $taskId, $movedAmount = null): array
	{
		$db = $this->Db();
		$t = $this->Row("SELECT * FROM wms_internal_movements WHERE id = :id AND movement_type = 'sala_ventas'", [':id' => $taskId]);
		if (!$t)
		{
			throw new \Exception('Tarea de reposición no encontrada');
		}
		if (!DairyAccess::CanSeeBranch(intval($t['branch_id'])))
		{
			throw new \Exception('No tiene acceso a esa sucursal');
		}
		if ($t['status'] !== 'pendiente')
		{
			throw new \Exception('La tarea ya fue cerrada');
		}
		$qty = $movedAmount !== null && $movedAmount !== '' ? round(floatval($movedAmount), 4) : floatval($t['amount']);
		if ($qty <= 0)
		{
			throw new \Exception('Indique la cantidad movida (mayor a cero)');
		}
		$available = floatval($this->Val('SELECT IFNULL(SUM(amount), 0) FROM stock WHERE product_id = :p AND location_id = :l', [':p' => $t['product_id'], ':l' => $t['from_location_id']]));
		if ($qty > $available + 0.0001)
		{
			throw new \Exception('En bodega solo hay ' . round($available, 2) . ' u en la ubicación de origen');
		}
		$db->beginTransaction();
		try
		{
			$tx = null;
			StockService::GetInstance()->TransferProduct(intval($t['product_id']), $qty, intval($t['from_location_id']), intval($t['to_location_id']), 'default', $tx);
			$upd = $db->prepare("UPDATE wms_internal_movements SET status = 'completado', amount = :a, completed_at = :c, completed_by_user_id = :u, transaction_id = :tx WHERE id = :id AND status = 'pendiente'");
			$upd->execute([':a' => $qty, ':c' => date('Y-m-d H:i:s'), ':u' => self::UserId() ?: null, ':tx' => $tx, ':id' => $taskId]);
			if ($upd->rowCount() !== 1)
			{
				throw new \Exception('La tarea ya fue cerrada por otro usuario');
			}
			$db->commit();
		}
		catch (\Throwable $e)
		{
			if ($db->inTransaction())
			{
				$db->rollBack();
			}
			throw $e;
		}
		return ['success' => true, 'transaction_id' => $tx, 'moved' => $qty, 'message' => 'Reposición confirmada: ' . $qty . ' u a sala de ventas'];
	}

	public function CancelReplenishment(int $taskId, string $reason): array
	{
		$t = $this->Row("SELECT branch_id FROM wms_internal_movements WHERE id = :id AND movement_type = 'sala_ventas'", [':id' => $taskId]);
		if (!$t || !DairyAccess::CanSeeBranch(intval($t['branch_id'])))
		{
			throw new \Exception('Tarea de reposición no encontrada');
		}
		$st = $this->Db()->prepare("UPDATE wms_internal_movements SET status = 'cancelado', completed_at = :c, completed_by_user_id = :u, notes = :n WHERE id = :id AND status = 'pendiente'");
		$st->execute([':c' => date('Y-m-d H:i:s'), ':u' => self::UserId() ?: null, ':n' => 'Cancelada: ' . (trim($reason) ?: 'sin motivo'), ':id' => $taskId]);
		if ($st->rowCount() !== 1)
		{
			throw new \Exception('La tarea ya fue cerrada');
		}
		return ['success' => true];
	}

	/* ================================================================ Reservas */

	public function Reservations($branchId = null): array
	{
		$scope = $this->Scope($branchId);
		$rows = $this->Rows("SELECT o.id, o.order_code, o.status, o.branch_id, o.confirmed_at, o.requested_date, o.delivery_type, c.name AS customer,
				i.product_id, p.name AS product, i.amount, i.picked_amount, CASE WHEN o.status IN ('picked', 'packed', 'dispatching') THEN i.picked_amount ELSE i.amount END AS reserved
			FROM customer_orders o JOIN customer_order_items i ON i.order_id = o.id JOIN products p ON p.id = i.product_id LEFT JOIN customers c ON c.id = o.customer_id
			WHERE o.status IN ('" . implode("', '", CustomerOrderService::RESERVING) . "')" . self::In($scope, 'o.branch_id') . ' ORDER BY o.confirmed_at, o.id');
		$orders = [];
		foreach ($rows as $r)
		{
			$id = intval($r['id']);
			$orders[$id] ??= ['id' => $id, 'code' => $r['order_code'], 'status' => $r['status'], 'branch' => $this->BranchNames()[intval($r['branch_id'])] ?? '', 'customer' => $r['customer'],
				'confirmed_at' => $r['confirmed_at'], 'requested_date' => $r['requested_date'], 'delivery_type' => $r['delivery_type'],
				'age_hours' => $r['confirmed_at'] ? intval(floor((time() - strtotime($r['confirmed_at'])) / 3600)) : null, 'units' => 0.0, 'lines' => []];
			$orders[$id]['units'] += floatval($r['reserved']);
			$orders[$id]['lines'][] = ['product_id' => intval($r['product_id']), 'product' => $r['product'], 'reserved' => floatval($r['reserved'])];
		}
		return ['rule' => 'Reserva desde la confirmación del pedido; al preparar pasa a lo preparado; se libera al despachar o anular (Despachos).', 'orders' => array_values($orders),
			'total_units' => round(array_sum(array_column($orders, 'units')), 2)];
	}

	/* ================================================================ Auditoría de movimientos */

	public function Audit(array $f): array
	{
		$scope = $this->Scope($f['branch_id'] ?? null);
		$from = !empty($f['from']) && IsIsoDate($f['from']) ? $f['from'] : date('Y-m-d', strtotime('-7 days'));
		$to = !empty($f['to']) && IsIsoDate($f['to']) ? $f['to'] : date('Y-m-d');
		$params = [':f' => $from, ':t' => $to];
		$extra = '';
		if (!empty($f['bucket']) && isset(self::SOURCES[$f['bucket']]))
		{
			$extra .= ' AND m.bucket = :bk';
			$params[':bk'] = $f['bucket'];
		}
		if (!empty($f['user_id']))
		{
			$extra .= ' AND m.user_id = ' . intval($f['user_id']);
		}
		if (!empty($f['product_id']))
		{
			$extra .= ' AND m.product_id = ' . intval($f['product_id']);
		}
		$limit = max(10, min(1000, intval($f['limit'] ?? 200)));
		$rows = $this->Rows('SELECT m.id, m.movement_code, m.operation_ref, m.bucket, m.transaction_type, m.product_id, p.name AS product, m.amount, m.value, m.location_name, m.branch_id,
				m.user_id, u.display_name AS user_name, m.note, m.undone, m.undone_timestamp, m.recorded_at, led.hash IS NOT NULL AS sealed
			FROM merchandise_movements m JOIN products p ON p.id = m.product_id LEFT JOIN users_dto u ON u.id = m.user_id LEFT JOIN dairy_movement_ledger led ON led.stock_log_id = m.id
			WHERE date(m.recorded_at) BETWEEN :f AND :t' . self::In($scope, 'm.branch_id') . $extra . ' ORDER BY m.recorded_at DESC, m.id DESC LIMIT ' . $limit, $params);
		$byModule = [];
		foreach ($rows as &$r)
		{
			[$label, $module] = self::SOURCES[$r['bucket']] ?? [$r['transaction_type'], 'Inventario'];
			$r['movement_label'] = $label;
			$r['source_module'] = $module;
			$r['branch'] = $this->BranchNames()[intval($r['branch_id'])] ?? '';
			$byModule[$module] = ($byModule[$module] ?? 0) + 1;
		}
		unset($r);
		$seal = MovementLedgerService::GetInstance()->Verify(100);
		return ['from' => $from, 'to' => $to, 'by_module' => $byModule, 'rows' => $rows, 'ledger_seal' => $seal,
			'note' => 'Cada movimiento lo registra su módulo dueño (Compras, POS, Despachos, Mermas, Traslados); Inventario solo los lee y concilia.'];
	}

	/* ================================================================ Escaneo UPC */

	/** Resuelve un código (UPC/EAN, SKU, lote o grocycode) y devuelve la posición del producto en la sucursal */
	public function Scan(string $code, $branchId = null): array
	{
		$code = trim($code);
		if ($code === '')
		{
			throw new \Exception('Escanee o escriba un código');
		}
		$productId = null;
		$matchedBy = null;
		$lot = null;
		if (preg_match('/^grcy:p:(\d+)(?::(.+))?$/', $code, $m))
		{
			$productId = intval($m[1]);
			$matchedBy = 'grocycode';
			$lot = $m[2] ?? null;
		}
		if (!$productId && ($r = $this->Row('SELECT product_id FROM product_barcodes WHERE barcode = :c', [':c' => $code])))
		{
			[$productId, $matchedBy] = [intval($r['product_id']), 'barcode'];
		}
		if (!$productId && ($r = $this->Row('SELECT id FROM products WHERE sku = :c LIMIT 1', [':c' => $code])))
		{
			[$productId, $matchedBy] = [intval($r['id']), 'sku'];
		}
		if (!$productId && ($r = $this->Row('SELECT product_id, stock_id FROM dairy_stock_lots WHERE lot_number = :c OR gtin = :c OR serial_number = :c OR imei = :c LIMIT 1', [':c' => $code])))
		{
			[$productId, $matchedBy, $lot] = [intval($r['product_id']), 'lot', $r['stock_id']];
		}
		if (!$productId)
		{
			return ['found' => false, 'code' => $code, 'message' => 'Código no registrado en el catálogo'];
		}
		$scope = $this->Scope($branchId);
		$pos = $this->PositionRows($scope, $productId);
		$sum = ['physical' => 0.0, 'reserved' => 0.0, 'blocked' => 0.0, 'available' => 0.0, 'bodega' => 0.0, 'sala' => 0.0, 'shortfall' => 0.0];
		foreach ($pos as $r)
		{
			foreach ($sum as $k => $_)
			{
				$sum[$k] += $r[$k];
			}
		}
		$locations = $this->Rows("SELECT l.id, l.name, l.branch_id, " . self::ZoneSql() . " AS zgroup, s.stock_id, s.amount, s.best_before_date, dl.lot_number
			FROM stock s JOIN locations l ON l.id = s.location_id LEFT JOIN dairy_stock_lots dl ON dl.stock_id = s.stock_id
			WHERE s.product_id = :p AND IFNULL(l.is_transit, 0) = 0" . self::In($scope, 'l.branch_id') . ' ORDER BY s.best_before_date, l.name LIMIT 50', [':p' => $productId]);
		foreach ($locations as &$l)
		{
			$l['branch'] = $this->BranchNames()[intval($l['branch_id'])] ?? '';
		}
		unset($l);
		$last = $this->Rows('SELECT m.recorded_at, m.bucket, m.amount, m.location_name FROM merchandise_movements m WHERE m.product_id = :p AND m.undone = 0' . self::In($scope, 'm.branch_id') . ' ORDER BY m.recorded_at DESC LIMIT 5', [':p' => $productId]);
		foreach ($last as &$x)
		{
			$x['label'] = self::SOURCES[$x['bucket']][0] ?? $x['bucket'];
		}
		unset($x);
		$pendingCount = $this->Row("SELECT id, location_id FROM stock_cycle_counts WHERE product_id = :p AND status = 'pending'" . self::In($scope, 'branch_id') . ' ORDER BY id LIMIT 1', [':p' => $productId]);
		return ['found' => true, 'code' => $code, 'matched_by' => $matchedBy, 'lot_stock_id' => $lot, 'product' => $this->ProductInfo([$productId])[$productId] ?? null,
			'position' => array_map(fn($v) => round($v, 4), $sum), 'by_branch' => $pos, 'locations' => $locations, 'last_movements' => $last, 'pending_count_id' => $pendingCount ? intval($pendingCount['id']) : null];
	}

	/* ================================================================ Ajustes con aprobación */

	public function Adjustments(array $f): array
	{
		$scope = $this->Scope($f['branch_id'] ?? null);
		$status = in_array($f['status'] ?? '', ['pending_approval', 'approved', 'rejected'], true) ? $f['status'] : 'pending_approval';
		$rows = $this->Rows('SELECT ia.*, p.name AS product, l.name AS location, ur.display_name AS requested_by, ua.display_name AS approved_by
			FROM inventory_adjustments ia JOIN products p ON p.id = ia.product_id LEFT JOIN locations l ON l.id = ia.location_id
			LEFT JOIN users_dto ur ON ur.id = ia.requested_by_user_id LEFT JOIN users_dto ua ON ua.id = IFNULL(ia.resolved_by_user_id, ia.approved_by_user_id)
			WHERE ia.status = :s' . self::In($scope, 'ia.branch_id') . ' ORDER BY ia.created_timestamp DESC LIMIT 200', [':s' => $status]);
		foreach ($rows as &$r)
		{
			$r['branch'] = $this->BranchNames()[intval($r['branch_id'])] ?? '';
		}
		unset($r);
		$counts = $this->Rows("SELECT c.id, c.product_id, p.name AS product, c.branch_id, l.name AS location, c.theoretical_stock, c.counted_stock, c.difference_amount, c.difference_cost, c.executed_date, c.status, u.display_name AS counted_by
			FROM stock_cycle_counts c JOIN products p ON p.id = c.product_id LEFT JOIN locations l ON l.id = c.location_id LEFT JOIN users_dto u ON u.id = c.executed_by_user_id
			WHERE c.status IN ('pending', 'discrepancy')" . self::In($scope, 'c.branch_id') . ' ORDER BY c.status DESC, c.scheduled_date LIMIT 300');
		foreach ($counts as &$c)
		{
			$c['branch'] = $this->BranchNames()[intval($c['branch_id'])] ?? '';
		}
		unset($c);
		return ['status' => $status, 'adjustments' => $rows, 'counts' => $counts, 'can_approve' => DairyAccess::HasAny([User::PERMISSION_INVENTORY_ADJUST_APPROVE])];
	}

	/**
	 * Solicitud de ajuste por conteo: calcula la diferencia contra el saldo actual (ubicación o red, como InventoryProduct
	 * de Grocy) y la deja pendiente de aprobación. No toca el stock. $requestKey hace idempotente el reenvío.
	 */
	public function RequestAdjustment(int $productId, float $newTotal, ?int $locationId, array $opts = []): array
	{
		if ($newTotal < 0)
		{
			throw new \Exception('La cantidad contada no puede ser negativa');
		}
		$product = $this->Row('SELECT id, name FROM products WHERE id = :p AND active = 1', [':p' => $productId]);
		if (!$product)
		{
			throw new \Exception('Producto inexistente o inactivo');
		}
		$requestKey = !empty($opts['request_key']) ? substr(strval($opts['request_key']), 0, 80) : null;
		if ($requestKey && ($dup = $this->Row('SELECT * FROM inventory_adjustments WHERE request_key = :k', [':k' => $requestKey])))
		{
			return ['adjustment' => $dup, 'duplicate' => true];
		}
		// Igual que Grocy: el "nuevo total" es del producto en toda la red; la corrección se registra en la ubicación indicada
		$current = floatval($this->Val('SELECT IFNULL(SUM(amount), 0) FROM stock WHERE product_id = :p', [':p' => $productId]));
		$diff = round($newTotal - $current, 4);
		if (abs($diff) < 0.0001)
		{
			return ['adjustment' => null, 'no_difference' => true, 'current' => $current];
		}
		$branchId = $locationId ? intval($this->Val('SELECT branch_id FROM locations WHERE id = :l', [':l' => $locationId])) : null;
		if (!$branchId)
		{
			$branchId = intval($this->Val('SELECT l.branch_id FROM stock s JOIN locations l ON l.id = s.location_id WHERE s.product_id = :p GROUP BY l.branch_id ORDER BY SUM(s.amount) DESC LIMIT 1', [':p' => $productId])) ?: null;
		}
		if ($branchId && !DairyAccess::CanSeeBranch($branchId))
		{
			throw new \Exception('No tiene acceso a esa sucursal');
		}
		$cost = floatval($this->Val('SELECT IFNULL(SUM(amount * price) / NULLIF(SUM(CASE WHEN price > 0 THEN amount END), 0), 0) FROM stock WHERE product_id = :p AND price > 0', [':p' => $productId]) ?: 0);
		if ($cost <= 0)
		{
			$cost = floatval($this->Val('SELECT IFNULL((SELECT price FROM cache__products_last_purchased WHERE product_id = :p), 0)', [':p' => $productId]));
		}
		$number = 'AJ-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));
		$st = $this->Db()->prepare("INSERT INTO inventory_adjustments (adjustment_number, product_id, branch_id, location_id, recorded_stock, counted_stock, difference_amount, unit_cost, total_cost_impact,
				adjustment_type, status, requires_manager_approval, requested_by_user_id, inventory_applied, notes, created_timestamp, best_before_date, price, source, request_key)
			VALUES (:n, :p, :b, :l, :rec, :cnt, :d, :uc, :tc, :t, 'pending_approval', 1, :u, 0, :notes, :ts, :bb, :price, :src, :rk)");
		$st->execute([':n' => $number, ':p' => $productId, ':b' => $branchId, ':l' => $locationId, ':rec' => $current, ':cnt' => $newTotal, ':d' => $diff, ':uc' => round($cost, 4),
			':tc' => round($diff * $cost, 2), ':t' => $diff < 0 ? 'faltante' : 'sobrante', ':u' => self::UserId() ?: null, ':notes' => $opts['note'] ?? null, ':ts' => date('Y-m-d H:i:s'),
			':bb' => !empty($opts['best_before_date']) && IsIsoDate($opts['best_before_date']) ? $opts['best_before_date'] : null,
			':price' => isset($opts['price']) && is_numeric($opts['price']) ? floatval($opts['price']) : null, ':src' => $opts['source'] ?? 'manual', ':rk' => $requestKey]);
		$id = intval($this->Db()->lastInsertId());
		return ['adjustment' => $this->Row('SELECT * FROM inventory_adjustments WHERE id = :id', [':id' => $id]), 'current' => $current];
	}

	/** Aprueba y aplica un ajuste pendiente en una sola transacción. Segregación: quien lo pidió no lo aprueba. */
	public function ApproveAdjustment(int $id, ?string $notes = null): array
	{
		$db = $this->Db();
		$a = $this->Row('SELECT ia.*, p.default_best_before_days FROM inventory_adjustments ia JOIN products p ON p.id = ia.product_id WHERE ia.id = :id', [':id' => $id]);
		if (!$a)
		{
			throw new \Exception('Ajuste no encontrado');
		}
		if ($a['branch_id'] && !DairyAccess::CanSeeBranch(intval($a['branch_id'])))
		{
			throw new \Exception('No tiene acceso a esa sucursal');
		}
		if ($a['status'] !== 'pending_approval')
		{
			throw new \Exception('El ajuste ya fue resuelto');
		}
		$uid = self::UserId();
		if (!(defined('GROCY_WASTE_ALLOW_SELF_APPROVAL') && GROCY_WASTE_ALLOW_SELF_APPROVAL) && $a['requested_by_user_id'] !== null && intval($a['requested_by_user_id']) === $uid)
		{
			throw new \Exception('Segregación de funciones: quien solicitó el ajuste no puede aprobarlo');
		}
		$diff = floatval($a['difference_amount']);
		$loc = $a['location_id'] ? intval($a['location_id']) : null;
		$db->beginTransaction();
		try
		{
			$tx = null;
			$stock = StockService::GetInstance();
			if ($diff < 0)
			{
				$have = floatval($this->Val('SELECT IFNULL(SUM(amount), 0) FROM stock WHERE product_id = :p' . ($loc ? ' AND location_id = ' . $loc : ''), [':p' => $a['product_id']]));
				if ($have + 0.0001 < abs($diff))
				{
					throw new \Exception('El saldo cambió desde el conteo (hay ' . round($have, 2) . ' u): rechace y vuelva a contar');
				}
				$stock->ConsumeProduct(intval($a['product_id']), abs($diff), false, StockService::TRANSACTION_TYPE_INVENTORY_CORRECTION, 'default', null, $loc, $tx, false, true);
			}
			else
			{
				$days = intval($a['default_best_before_days']);
				$bb = $a['best_before_date'] ?: ($days > 0 ? date('Y-m-d', strtotime("+$days days")) : '2999-12-31');
				$price = $a['price'] !== null ? floatval($a['price']) : (floatval($a['unit_cost']) > 0 ? floatval($a['unit_cost']) : null);
				$stock->AddProduct(intval($a['product_id']), $diff, $bb, StockService::TRANSACTION_TYPE_INVENTORY_CORRECTION, date('Y-m-d'), $price, $loc, null, $tx, 0, false, 'Ajuste ' . $a['adjustment_number']);
			}
			$upd = $db->prepare("UPDATE inventory_adjustments SET status = 'approved', approved_by_user_id = :u, approved_timestamp = :ts, resolved_by_user_id = :u, resolved_timestamp = :ts,
				inventory_applied = 1, transaction_id = :tx, resolution_notes = :n WHERE id = :id AND status = 'pending_approval'");
			$upd->execute([':u' => $uid ?: null, ':ts' => date('Y-m-d H:i:s'), ':tx' => $tx, ':n' => $notes, ':id' => $id]);
			if ($upd->rowCount() !== 1)
			{
				throw new \Exception('El ajuste ya fue resuelto por otro usuario');
			}
			$db->commit();
		}
		catch (\Throwable $e)
		{
			if ($db->inTransaction())
			{
				$db->rollBack();
			}
			throw $e;
		}
		return ['success' => true, 'transaction_id' => $tx, 'message' => 'Ajuste ' . $a['adjustment_number'] . ' aprobado y aplicado (' . ($diff > 0 ? '+' : '') . $diff . ' u)'];
	}

	public function RejectAdjustment(int $id, string $reason): array
	{
		if (trim($reason) === '')
		{
			throw new \Exception('Indique el motivo del rechazo');
		}
		$a = $this->Row('SELECT branch_id FROM inventory_adjustments WHERE id = :id', [':id' => $id]);
		if (!$a || ($a['branch_id'] && !DairyAccess::CanSeeBranch(intval($a['branch_id']))))
		{
			throw new \Exception('Ajuste no encontrado');
		}
		$st = $this->Db()->prepare("UPDATE inventory_adjustments SET status = 'rejected', resolved_by_user_id = :u, resolved_timestamp = :ts, resolution_notes = :n WHERE id = :id AND status = 'pending_approval'");
		$st->execute([':u' => self::UserId() ?: null, ':ts' => date('Y-m-d H:i:s'), ':n' => trim($reason), ':id' => $id]);
		if ($st->rowCount() !== 1)
		{
			throw new \Exception('El ajuste ya fue resuelto');
		}
		return ['success' => true];
	}

	/* ================================================================ Reglas de traslado */

	/** Un traslado directo entre ubicaciones de distintas sucursales saltaría la confirmación de recepción */
	public function IsCrossBranchMove(int $fromLocationId, int $toLocationId): bool
	{
		$r = $this->Row('SELECT a.branch_id AS a, IFNULL(a.is_transit, 0) AS at, b.branch_id AS b, IFNULL(b.is_transit, 0) AS bt FROM locations a, locations b WHERE a.id = :f AND b.id = :t', [':f' => $fromLocationId, ':t' => $toLocationId]);
		if (!$r || intval($r['at']) || intval($r['bt']))
		{
			return $r !== null && (intval($r['at']) || intval($r['bt']));
		}
		return $r['a'] !== null && $r['b'] !== null && intval($r['a']) !== intval($r['b']);
	}
}
