<?php

namespace Grocy\Services;

use Grocy\Helpers\DairyAccess;

/**
 * Pedidos de cliente (Etapa 22): borrador → confirmado (reserva stock) → en preparación (picking por escáner, FEFO guiado)
 * → preparado → empacado (bultos) → despachado (venta con SalesService sobre las entradas exactas preparadas).
 * Preparar no mueve stock; el despacho lo descuenta una sola vez (estado «dispatching» reclamado con UPDATE condicional).
 * La reserva se libera al despachar o anular y la respeta el POS (SalesService) y la disponibilidad del escáner.
 */
class CustomerOrderService extends BaseService
{
	const STATUS = ['draft' => 'Borrador', 'confirmed' => 'Confirmado', 'picking' => 'En preparación', 'picked' => 'Preparado', 'packed' => 'Empacado',
		'dispatching' => 'Despachando', 'dispatched' => 'Despachado', 'cancelled' => 'Anulado'];
	const RESERVING = ['confirmed', 'picking', 'picked', 'packed', 'dispatching'];

	private function Db(): \PDO
	{
		return DatabaseService::GetInstance()->GetDbConnectionRaw();
	}

	private function Row(string $sql, array $p = []): ?array
	{
		$stmt = $this->Db()->prepare($sql);
		$stmt->execute($p);
		$r = $stmt->fetch(\PDO::FETCH_ASSOC);
		return $r ?: null;
	}

	private function Rows(string $sql, array $p = []): array
	{
		$stmt = $this->Db()->prepare($sql);
		$stmt->execute($p);
		return $stmt->fetchAll(\PDO::FETCH_ASSOC);
	}

	/** Cantidad reservada: pedido completo mientras se confirma/prepara; lo preparado una vez cerrado el picking. */
	public function Reserved(int $productId, ?int $branchId, ?int $excludeOrderId = null): float
	{
		$sql = "SELECT IFNULL(SUM(CASE WHEN o.status IN ('picked', 'packed', 'dispatching') THEN i.picked_amount ELSE i.amount END), 0)
			FROM customer_order_items i JOIN customer_orders o ON o.id = i.order_id
			WHERE i.product_id = :p AND o.status IN ('" . implode("', '", self::RESERVING) . "')" . ($branchId ? ' AND o.branch_id = :b' : '') . ($excludeOrderId ? ' AND o.id <> :x' : '');
		$p = [':p' => $productId] + ($branchId ? [':b' => $branchId] : []) + ($excludeOrderId ? [':x' => $excludeOrderId] : []);
		$stmt = $this->Db()->prepare($sql);
		$stmt->execute($p);
		return floatval($stmt->fetchColumn());
	}

	/** Libre para comprometer = disponible (físico − cuarentena − vencido − merma pendiente) − reservado por otros pedidos */
	public function Free(int $productId, int $branchId, ?int $excludeOrderId = null): float
	{
		$a = ScanService::GetInstance()->Availability($productId, $branchId);
		return round(floatval($a['physical']) - floatval($a['quarantine']) - floatval($a['expired']) - floatval($a['committed_waste']) - $this->Reserved($productId, $branchId, $excludeOrderId), 4);
	}

	public function ListPrice(int $productId): float
	{
		$p = $this->Row('
			SELECT
				p.sale_price,
				COALESCE(
					NULLIF((SELECT price FROM cache__products_last_purchased WHERE product_id = p.id), 0),
					(SELECT MAX(price) FROM stock WHERE product_id = p.id AND IFNULL(price, 0) > 0),
					0
				) AS last_price
			FROM products p
			WHERE p.id = :id
		', [':id' => $productId]);
		if (!$p)
		{
			return 0;
		}
		return floatval($p['sale_price']) > 0 ? floatval($p['sale_price']) : round(floatval($p['last_price']) * (1 + GROCY_SALE_PRICE_DEFAULT_MARKUP_PCT / 100), 2);
	}

	private function Order(int $id): array
	{
		$o = $this->Row('SELECT * FROM customer_orders WHERE id = :id', [':id' => $id]);
		if (!$o)
		{
			throw new \Exception('Pedido no encontrado');
		}
		if (!DairyAccess::CanSeeBranch(intval($o['branch_id'])))
		{
			throw new \Exception('No tiene acceso a la sucursal de este pedido');
		}
		return $o;
	}

	public function Create(array $data, int $userId): array
	{
		$branchId = intval($data['branch_id'] ?? 0);
		if ($branchId <= 0 || !DairyAccess::CanSeeBranch($branchId) || !$this->Row('SELECT id FROM branches WHERE id = :id', [':id' => $branchId]))
		{
			throw new \Exception('Seleccione una sucursal a la que tenga acceso');
		}
		$customerId = intval($data['customer_id'] ?? 0);
		if (!$this->Row('SELECT id FROM customers WHERE id = :id AND active = 1', [':id' => $customerId]))
		{
			throw new \Exception('Seleccione un cliente activo');
		}
		$date = trim((string) ($data['requested_date'] ?? ''));
		if ($date !== '' && (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || $date < date('Y-m-d')))
		{
			throw new \Exception('Fecha de entrega inválida o pasada');
		}
		$lines = [];
		foreach ($data['items'] ?? [] as $it)
		{
			$pid = intval($it['product_id'] ?? 0);
			$amount = floatval($it['amount'] ?? 0);
			if ($pid <= 0 && $amount <= 0)
			{
				continue;
			}
			$prod = $this->Row('SELECT id, name FROM products WHERE id = :id AND active = 1', [':id' => $pid]);
			if (!$prod)
			{
				throw new \Exception('Producto inválido en el pedido');
			}
			if ($amount <= 0 || $amount > 100000)
			{
				throw new \Exception("{$prod['name']}: cantidad inválida");
			}
			if (isset($lines[$pid]))
			{
				throw new \Exception("{$prod['name']} está repetido en el pedido: use una sola línea");
			}
			$list = $this->ListPrice($pid);
			$price = isset($it['unit_price']) && $it['unit_price'] !== '' ? round(floatval($it['unit_price']), 2) : $list;
			if ($list <= 0)
			{
				throw new \Exception("{$prod['name']} no tiene precio de venta ni costo: configúrelo antes de venderlo");
			}
			if ($price < $list - 0.005)
			{
				throw new \Exception("{$prod['name']}: precio \${$price} menor al de lista \${$list} (las rebajas FEFO se aplican en el POS)");
			}
			$lines[$pid] = ['product_id' => $pid, 'amount' => $amount, 'unit_price' => $price];
		}
		if (count($lines) === 0)
		{
			throw new \Exception('El pedido debe tener al menos un producto');
		}
		$db = $this->Db();
		$db->beginTransaction();
		try
		{
			$year = date('Y');
			$seq = intval($db->query("SELECT COUNT(*) + 1 FROM customer_orders WHERE order_code LIKE 'PED-$year-%'")->fetchColumn());
			$rawDeliveryType = $data['delivery_type'] ?? 'pickup';
			$deliveryType = in_array($rawDeliveryType, ['pickup', 'delivery'], true) ? $rawDeliveryType : 'pickup';
			$db->prepare('INSERT INTO customer_orders (order_code, branch_id, customer_id, requested_date, notes, delivery_type, created_by_user_id, created_at) VALUES (:c, :b, :cu, :d, :n, :dt, :u, :t)')
				->execute([':c' => sprintf('PED-%s-%05d', $year, $seq), ':b' => $branchId, ':cu' => $customerId, ':d' => $date ?: null, ':n' => trim((string) ($data['notes'] ?? '')) ?: null, ':dt' => $deliveryType, ':u' => $userId, ':t' => date('Y-m-d H:i:s')]);
			$id = intval($db->lastInsertId());
			$ins = $db->prepare('INSERT INTO customer_order_items (order_id, product_id, amount, unit_price) VALUES (:o, :p, :a, :pr)');
			foreach ($lines as $l)
			{
				$ins->execute([':o' => $id, ':p' => $l['product_id'], ':a' => $l['amount'], ':pr' => $l['unit_price']]);
			}
			$db->commit();
		}
		catch (\Throwable $e)
		{
			$db->rollBack();
			throw $e;
		}
		return !empty($data['confirm']) ? $this->Confirm($id, $userId) : $this->Get($id);
	}

	/** Confirmar = reservar. Toma el bloqueo de escritura antes de leer disponibilidad para que dos confirmaciones no reserven lo mismo. */
	public function Confirm(int $id, int $userId): array
	{
		$o = $this->Order($id);
		if ($o['status'] !== 'draft')
		{
			if ($o['status'] === 'confirmed')
			{
				return $this->Get($id);
			}
			throw new \Exception('Solo se confirma un pedido en borrador');
		}
		$db = $this->Db();
		$db->beginTransaction();
		try
		{
			$db->prepare('UPDATE customer_orders SET status = status WHERE id = :id')->execute([':id' => $id]); // adquiere el bloqueo de escritura
			$short = [];
			foreach ($this->Rows('SELECT i.product_id, i.amount, p.name FROM customer_order_items i JOIN products p ON p.id = i.product_id WHERE i.order_id = :o', [':o' => $id]) as $i)
			{
				$free = $this->Free(intval($i['product_id']), intval($o['branch_id']), $id);
				if (floatval($i['amount']) > $free + 0.0001)
				{
					$short[] = "{$i['name']} (libre " . max(0, round($free, 2)) . ", pedido " . round(floatval($i['amount']), 2) . ')';
				}
			}
			if ($short)
			{
				throw new \Exception('Stock libre insuficiente para reservar: ' . implode('; ', $short));
			}
			$db->prepare("UPDATE customer_orders SET status = 'confirmed', confirmed_by_user_id = :u, confirmed_at = :t WHERE id = :id AND status = 'draft'")->execute([':u' => $userId, ':t' => date('Y-m-d H:i:s'), ':id' => $id]);
			$db->commit();
		}
		catch (\Throwable $e)
		{
			$db->rollBack();
			throw $e;
		}
		return $this->Get($id);
	}

	/** Entradas de stock preparables (FEFO, sin cuarentena, descontando lo ya preparado y no despachado de otros pedidos) */
	public function PickableEntries(int $productId, int $branchId, ?int $locationId = null, ?string $lot = null, ?string $serial = null): array
	{
		$rows = $this->Rows("SELECT s.stock_id, s.location_id, l.name AS location_name, l.label_code, MIN(s.best_before_date) AS best_before_date, SUM(s.amount) AS amount, dl.lot_number, dl.serial_number,
				(SELECT IFNULL(SUM(q.amount), 0) FROM quarantine_items q WHERE q.product_id = s.product_id AND q.status = 'in_quarantine' AND q.stock_id = s.stock_id AND q.location_id = s.location_id) AS held,
				(SELECT IFNULL(SUM(cp.amount), 0) FROM customer_order_picks cp JOIN customer_orders co ON co.id = cp.order_id WHERE cp.stock_id = s.stock_id AND cp.location_id = s.location_id AND co.status IN ('picked', 'packed', 'dispatching')) AS allocated
			FROM stock s JOIN locations l ON l.id = s.location_id LEFT JOIN dairy_stock_lots dl ON dl.stock_id = s.stock_id
			WHERE s.product_id = :p AND l.branch_id = :b AND IFNULL(l.is_transit, 0) = 0 AND (s.best_before_date >= :today OR s.best_before_date = '2999-12-31')
			GROUP BY s.stock_id, s.location_id ORDER BY MIN(s.best_before_date), MIN(s.purchased_date), MIN(s.id)", [':p' => $productId, ':b' => $branchId, ':today' => date('Y-m-d')]);
		$out = [];
		foreach ($rows as $r)
		{
			$r['free'] = round(floatval($r['amount']) - floatval($r['held']) - floatval($r['allocated']), 4);
			if ($r['free'] <= 0.0001 || ($locationId && intval($r['location_id']) !== $locationId) || ($lot && $r['lot_number'] !== $lot) || ($serial && $r['serial_number'] !== $serial))
			{
				continue;
			}
			$out[] = $r;
		}
		return $out;
	}

	/** Cierre de una sesión de picking: asigna cada lectura a entradas de stock concretas (lote escaneado o FEFO) */
	public function RegisterPicks(int $orderId, array $session, array $lines, int $userId): array
	{
		$o = $this->Order($orderId);
		if (!in_array($o['status'], ['confirmed', 'picking']))
		{
			throw new \Exception('El pedido no está en preparación');
		}
		$items = array_column($this->Rows('SELECT * FROM customer_order_items WHERE order_id = :o', [':o' => $orderId]), null, 'product_id');
		$db = $this->Db();
		$db->beginTransaction();
		try
		{
			$db->prepare('UPDATE customer_orders SET status = status WHERE id = :id')->execute([':id' => $orderId]);
			$ins = $db->prepare('INSERT INTO customer_order_picks (order_id, item_id, product_id, stock_id, location_id, amount, lot_number, fefo_ok, scan_session_id, user_id, picked_at) VALUES (:o, :i, :p, :s, :l, :a, :lot, :f, :ss, :u, :t)');
			$upd = $db->prepare('UPDATE customer_order_items SET picked_amount = picked_amount + :a WHERE id = :id');
			$fefoBreaks = 0;
			$used = []; // asignado en este mismo cierre (aún no visible en PickableEntries)
			foreach ($lines as $l)
			{
				$pid = intval($l['product_id']);
				$item = $items[$pid] ?? throw new \Exception('Producto fuera del pedido');
				$need = floatval($l['amount']);
				$byLot = !empty($l['lot_number']) || !empty($l['serial_number']);
				$entries = $this->PickableEntries($pid, intval($o['branch_id']), $session['location_id'] ? intval($session['location_id']) : null, $l['lot_number'] ?: null, $l['serial_number'] ?: null);
				$first = null;
				foreach ($this->PickableEntries($pid, intval($o['branch_id'])) as $fe)
				{
					if (floatval($fe['free']) - ($used[$fe['stock_id'] . '@' . $fe['location_id']] ?? 0) > 0.0001)
					{
						$first = $fe['best_before_date'];
						break;
					}
				}
				foreach ($entries as $e)
				{
					if ($need <= 0.0001)
					{
						break;
					}
					$key = $e['stock_id'] . '@' . $e['location_id'];
					$take = min($need, floatval($e['free']) - ($used[$key] ?? 0));
					if ($take <= 0.0001)
					{
						continue;
					}
					$used[$key] = ($used[$key] ?? 0) + $take;
					// La asignación automática es FEFO por construcción; un lote escaneado se compara con el más próximo libre
					$fefo = !$byLot || $first === null || $e['best_before_date'] <= $first ? 1 : 0;
					$fefoBreaks += 1 - $fefo;
					$ins->execute([':o' => $orderId, ':i' => $item['id'], ':p' => $pid, ':s' => $e['stock_id'], ':l' => $e['location_id'], ':a' => $take, ':lot' => $e['lot_number'], ':f' => $fefo,
						':ss' => $session['id'], ':u' => $userId, ':t' => date('Y-m-d H:i:s')]);
					$need -= $take;
				}
				if ($need > 0.0001)
				{
					throw new \Exception('No hay stock libre suficiente' . ($l['lot_number'] ? " del lote {$l['lot_number']}" : '') . ' para lo escaneado (faltan ' . round($need, 2) . ')');
				}
				$upd->execute([':a' => floatval($l['amount']), ':id' => $item['id']]);
			}
			$db->prepare("UPDATE customer_orders SET status = 'picked', picked_by_user_id = :u, picked_at = :t WHERE id = :id")->execute([':u' => $userId, ':t' => date('Y-m-d H:i:s'), ':id' => $orderId]);
			$db->commit();
		}
		catch (\Throwable $e)
		{
			$db->rollBack();
			throw $e;
		}
		$d = $this->Get($orderId);
		$short = array_filter($d['items'], fn($i) => floatval($i['picked_amount']) < floatval($i['amount']) - 0.0001);
		return ['order_id' => $orderId, 'fefo_breaks' => $fefoBreaks, 'short_lines' => count($short),
			'message' => "Pedido {$o['order_code']} preparado" . (count($short) ? ' con ' . count($short) . ' línea(s) incompleta(s) (se despacha solo lo preparado)' : ' completo') . ($fefoBreaks ? " · $fefoBreaks lote(s) fuera de FEFO" : '')];
	}

	public function Pack(int $id, int $packages, int $userId): array
	{
		$o = $this->Order($id);
		if ($o['status'] !== 'picked')
		{
			throw new \Exception('Solo se empaca un pedido preparado');
		}
		if ($packages < 1 || $packages > 999)
		{
			throw new \Exception('Indique la cantidad de bultos (1 a 999)');
		}
		$this->Db()->prepare("UPDATE customer_orders SET status = 'packed', packages = :n, packed_by_user_id = :u, packed_at = :t WHERE id = :id AND status = 'picked'")
			->execute([':n' => $packages, ':u' => $userId, ':t' => date('Y-m-d H:i:s'), ':id' => $id]);
		return $this->Get($id);
	}

	/** Despacho: venta sobre las entradas exactas preparadas. Idempotente y con reclamo atómico del estado. */
	public function Dispatch(int $id, int $userId): array
	{
		$o = $this->Order($id);
		if ($o['status'] === 'dispatched')
		{
			return $this->Get($id) + ['already_dispatched' => true];
		}
		if ($o['status'] !== 'packed')
		{
			throw new \Exception('Solo se despacha un pedido empacado');
		}
		$claim = $this->Db()->prepare("UPDATE customer_orders SET status = 'dispatching' WHERE id = :id AND status = 'packed'");
		$claim->execute([':id' => $id]);
		if ($claim->rowCount() !== 1)
		{
			throw new \Exception('El pedido se está despachando desde otra sesión');
		}
		try
		{
			$items = [];
			foreach ($this->Rows('SELECT cp.product_id, cp.stock_id, cp.location_id, SUM(cp.amount) AS amount, i.unit_price FROM customer_order_picks cp JOIN customer_order_items i ON i.id = cp.item_id
				WHERE cp.order_id = :o GROUP BY cp.product_id, cp.stock_id, cp.location_id ORDER BY cp.id', [':o' => $id]) as $p)
			{
				$items[] = ['product_id' => intval($p['product_id']), 'amount' => floatval($p['amount']), 'unit_price' => floatval($p['unit_price']), 'location_id' => intval($p['location_id']), 'stock_id' => $p['stock_id']];
			}
			if (count($items) === 0)
			{
				throw new \Exception('El pedido no tiene nada preparado');
			}
			$sale = SalesService::GetInstance()->CreateSale(['branch_id' => intval($o['branch_id']), 'customer_id' => intval($o['customer_id']), 'payment_method' => 'credit',
				'notes' => "Despacho pedido {$o['order_code']}", 'items' => $items, 'customer_order_id' => $id], $userId);
		}
		catch (\Throwable $e)
		{
			$this->Db()->prepare("UPDATE customer_orders SET status = 'packed' WHERE id = :id AND status = 'dispatching'")->execute([':id' => $id]);
			throw $e;
		}
		$saleId = is_object($sale) ? intval($sale->id) : intval($sale['id'] ?? 0);
		$this->Db()->prepare("UPDATE customer_orders SET status = 'dispatched', sale_id = :s, dispatched_by_user_id = :u, dispatched_at = :t WHERE id = :id")
			->execute([':s' => $saleId ?: null, ':u' => $userId, ':t' => date('Y-m-d H:i:s'), ':id' => $id]);
		return $this->Get($id);
	}

	public function Cancel(int $id, string $reason, int $userId): array
	{
		DeviceService::GetInstance()->ReleaseOrder($id, $userId);
		$o = $this->Order($id);
		if (in_array($o['status'], ['dispatched', 'dispatching', 'cancelled']))
		{
			throw new \Exception('El pedido ya está ' . mb_strtolower(self::STATUS[$o['status']]));
		}
		if (trim($reason) === '')
		{
			throw new \Exception('Indique el motivo de anulación');
		}
		if ($this->Row("SELECT id FROM dairy_scan_sessions WHERE customer_order_id = :o AND status = 'open'", [':o' => $id]))
		{
			throw new \Exception('Hay una sesión de picking abierta: anúlela primero');
		}
		// Preparar no movió stock: anular solo libera la reserva (los picks quedan como historial)
		$this->Db()->prepare("UPDATE customer_orders SET status = 'cancelled', cancel_reason = :r, cancelled_by_user_id = :u, cancelled_at = :t WHERE id = :id")
			->execute([':r' => trim($reason), ':u' => $userId, ':t' => date('Y-m-d H:i:s'), ':id' => $id]);
		return $this->Get($id);
	}

	public function MarkPicking(int $id): void
	{
		$this->Db()->prepare("UPDATE customer_orders SET status = 'picking' WHERE id = :id AND status = 'confirmed'")->execute([':id' => $id]);
	}

	/** Preparación directa asistida por FEFO desde la vista de Pedidos (asigna los lotes FEFO más próximos de cada ítem pendiente) */
	public function QuickPickFefo(int $orderId, int $userId): array
	{
		$o = $this->Order($orderId);
		if (!in_array($o['status'], ['confirmed', 'picking']))
		{
			throw new \Exception('El pedido debe estar Confirmado o En preparación para registrar el picking.');
		}
		$items = $this->Rows('SELECT * FROM customer_order_items WHERE order_id = :o ORDER BY id', [':o' => $orderId]);
		$lines = [];
		foreach ($items as $it)
		{
			$pending = max(0.0, round(floatval($it['amount']) - floatval($it['picked_amount']), 4));
			if ($pending > 0.0001)
			{
				$lines[] = [
					'product_id' => intval($it['product_id']),
					'amount' => $pending,
					'lot_number' => null,
					'serial_number' => null
				];
			}
		}
		if (empty($lines))
		{
			$this->Db()->prepare("UPDATE customer_orders SET status = 'picked', picked_by_user_id = :u, picked_at = :t WHERE id = :id")
				->execute([':u' => $userId, ':t' => date('Y-m-d H:i:s'), ':id' => $orderId]);
			return $this->Get($orderId);
		}
		$this->RegisterPicks($orderId, ['id' => null, 'location_id' => null], $lines, $userId);
		return $this->Get($orderId);
	}

	public function ResolveProductImage(string $productName, string $categoryName = ''): string
	{
		$n = mb_strtolower($productName, 'UTF-8');
		$c = mb_strtolower($categoryName, 'UTF-8');

		if (strpos($n, 'leche entera') !== false) return '/img/control_center/leche_entera_galon.jpg';
		if (strpos($n, 'leche descremada') !== false || strpos($n, 'deslactosada') !== false) return '/img/control_center/leche_descremada.jpg';
		if (strpos($n, 'leche') !== false) return '/img/control_center/leche_entera_1l.jpg';
		if (strpos($n, 'yogur') !== false || strpos($n, 'kefir') !== false || strpos($n, 'probi') !== false) return '/img/control_center/prod_yogur_tub.svg';
		if (strpos($n, 'mozzarella') !== false) return '/img/control_center/queso_mozzarella.jpg';
		if (strpos($n, 'cheddar') !== false) return '/img/control_center/queso_cheddar.jpg';
		if (strpos($n, 'parmesano') !== false) return '/img/control_center/queso_parmesano.jpg';
		if (strpos($n, 'cottage') !== false || strpos($n, 'untable') !== false || strpos($n, 'queso') !== false) return '/img/control_center/queso_cottage.jpg';
		if (strpos($n, 'huevo') !== false) return '/img/control_center/huevos_grandes.jpg';
		if (strpos($n, 'mantequilla') !== false) return '/img/control_center/mantequilla_cremas.jpg';
		if (strpos($n, 'crema') !== false) return '/img/control_center/crema_de_leche.jpg';
		if (strpos($n, 'prote') !== false || strpos($n, 'bebida') !== false || strpos($n, 'jugo') !== false) return '/img/control_center/bebida_proteica.jpg';
		if (strpos($n, 'tomate') !== false) return '/img/control_center/tomate.jpg';
		if (strpos($n, 'pepino') !== false) return '/img/control_center/pepino.jpg';
		if (strpos($n, 'piment') !== false) return '/img/control_center/pimenton.jpg';
		if (strpos($n, 'rábano') !== false || strpos($n, 'rabano') !== false || strpos($n, 'verdura') !== false) return '/img/control_center/verduras_frutas.jpg';
		if (strpos($n, 'pollo') !== false || strpos($n, 'pechuga') !== false) return '/img/control_center/pechuga_pollo.jpg';
		if (strpos($n, 'res') !== false || strpos($n, 'lomo') !== false || strpos($n, 'carne') !== false) return '/img/control_center/carne_picada.jpg';
		if (strpos($n, 'phone') !== false || strpos($n, 'smartphone') !== false || strpos($n, 'galaxy') !== false) return '/img/control_center/daily_scan_phone.jpg';
		if (strpos($n, 'audífono') !== false || strpos($n, 'audifono') !== false || strpos($n, 'inalámbrico') !== false || strpos($n, 'auricular') !== false) return '/img/control_center/daily_scan_industrial.jpg';
		if (strpos($n, 'baguette') !== false) return '/img/control_center/prod_baguette.svg';
		if (strpos($n, 'croissant') !== false) return '/img/control_center/thumb_coffee_croissant.jpg';
		if (strpos($n, 'pan') !== false || strpos($n, 'molde') !== false) return '/img/control_center/prod_pan_molde.svg';
		if (strpos($n, 'aceite') !== false) return '/img/control_center/prod_aceite_vegetal.svg';
		if (strpos($n, 'vegetal') !== false || strpos($n, 'congelado') !== false) return '/img/control_center/prod_vegetales_congelados.svg';
		if (strpos($n, 'frutilla') !== false || strpos($n, 'fresa') !== false) return '/img/control_center/prod_yogur_tub.svg';
		if (strpos($c, 'electr') !== false) return '/img/control_center/daily_scan_phone.jpg';
		if (strpos($c, 'panad') !== false) return '/img/control_center/prod_baguette.svg';

		return '/img/control_center/lacteos_leches.jpg';
	}

	public function Get(int $id): array
	{
		$o = $this->Order($id);
		$meta = $this->Row('
			SELECT
				c.name AS customer_name,
				c.tax_id,
				c.phone AS customer_phone,
				c.email AS customer_email,
				c.address AS customer_address,
				b.name AS branch_name,
				s.sale_number,
				s.payment_method AS sale_payment_method,
				s.total_amount AS sale_total_amount,
				s.tax_amount AS sale_tax_amount,
				s.discount_amount AS sale_discount_amount,
				u_cr.username AS created_by_username,
				u_cf.username AS confirmed_by_username,
				u_pk.username AS picked_by_username,
				u_pa.username AS packed_by_username,
				u_dp.username AS dispatched_by_username,
				u_cn.username AS cancelled_by_username
			FROM customer_orders o
			JOIN customers c ON c.id = o.customer_id
			JOIN branches b ON b.id = o.branch_id
			LEFT JOIN sales s ON s.id = o.sale_id
			LEFT JOIN users u_cr ON u_cr.id = o.created_by_user_id
			LEFT JOIN users u_cf ON u_cf.id = o.confirmed_by_user_id
			LEFT JOIN users u_pk ON u_pk.id = o.picked_by_user_id
			LEFT JOIN users u_pa ON u_pa.id = o.packed_by_user_id
			LEFT JOIN users u_dp ON u_dp.id = o.dispatched_by_user_id
			LEFT JOIN users u_cn ON u_cn.id = o.cancelled_by_user_id
			WHERE o.id = :id
		', [':id' => $id]) ?? [];
		$o += $meta;
		$o['status_label'] = self::STATUS[$o['status']] ?? $o['status'];

		// Estado de pago y modalidad de entrega coherentes con los datos registrados
		if ($o['status'] === 'cancelled')
		{
			$o['payment_status'] = 'cancelled';
			$o['payment_label'] = 'Anulado';
		}
		elseif (!empty($o['sale_id']) || $o['status'] === 'dispatched')
		{
			$o['payment_status'] = 'invoiced';
			$o['payment_label'] = !empty($o['sale_number']) ? 'Facturado (' . $o['sale_number'] . ')' : 'Facturado al despacho';
		}
		else
		{
			$o['payment_status'] = 'pending';
			$o['payment_label'] = 'Por facturar al despacho';
		}

		$delType = strtolower((string)($o['delivery_type'] ?? 'pickup'));
		$o['delivery_type'] = $delType ?: 'pickup';
		$o['delivery_type_label'] = $delType === 'delivery' ? 'Domicilio / Ruta' : 'Retiro en tienda';
		if ($o['status'] === 'dispatched')
		{
			$o['delivery_status_label'] = ($o['delivery_status'] ?? '') === 'delivered' ? 'Entregado' : 'Despachado';
		}
		elseif ($o['status'] === 'cancelled')
		{
			$o['delivery_status_label'] = 'Cancelado';
		}
		elseif (in_array($o['status'], ['picked', 'packed'], true))
		{
			$o['delivery_status_label'] = 'Listo para despacho';
		}
		else
		{
			$o['delivery_status_label'] = 'Pendiente de preparación';
		}

		$picks = $this->Rows('
			SELECT
				cp.*,
				p.name AS product_name,
				l.name AS location_name,
				u.username,
				(SELECT MIN(s.best_before_date) FROM stock s WHERE s.stock_id = cp.stock_id) AS best_before_date
			FROM customer_order_picks cp
			JOIN products p ON p.id = cp.product_id
			JOIN locations l ON l.id = cp.location_id
			LEFT JOIN users u ON u.id = cp.user_id
			WHERE cp.order_id = :o
			ORDER BY cp.id
		', [':o' => $id]);

		$picksByProduct = [];
		foreach ($picks as $pk)
		{
			$picksByProduct[intval($pk['product_id'])][] = $pk;
		}

		$items = $this->Rows('
			SELECT
				i.*,
				p.name AS product_name,
				p.sku AS product_sku,
				IFNULL(qu.name, "pza") AS unit_name,
				IFNULL(pg.name, "General") AS category_name
			FROM customer_order_items i
			JOIN products p ON p.id = i.product_id
			LEFT JOIN quantity_units qu ON qu.id = p.qu_id_stock
			LEFT JOIN product_groups pg ON pg.id = p.product_group_id
			WHERE i.order_id = :o
			ORDER BY i.id
		', [':o' => $id]);

		$branchId = intval($o['branch_id']);
		$isReserving = in_array($o['status'], self::RESERVING, true);
		$hasShortage = false;

		foreach ($items as &$i)
		{
			$pid = intval($i['product_id']);
			$reqAmt = round(floatval($i['amount']), 2);
			$pickedAmt = round(floatval($i['picked_amount']), 2);
			$i['pending'] = max(0, round($reqAmt - $pickedAmt, 4));
			$i['subtotal'] = round($reqAmt * floatval($i['unit_price']), 2);
			$i['image_url'] = $this->ResolveProductImage((string)$i['product_name'], (string)$i['category_name']);

			$avail = ScanService::GetInstance()->Availability($pid, $branchId);
			$physicalUsable = max(0.0, round(floatval($avail['physical']) - floatval($avail['quarantine']) - floatval($avail['expired']) - floatval($avail['committed_waste']), 2));
			$freeOther = max(0.0, round($this->Free($pid, $branchId, $id), 2));

			$i['physical_available'] = $physicalUsable;
			$i['free_available'] = $freeOther;
			$i['reserved_amount'] = $isReserving
				? (in_array($o['status'], ['picked', 'packed', 'dispatching'], true) ? $pickedAmt : $reqAmt)
				: 0.0;

			$i['suggested'] = in_array($o['status'], ['draft', 'confirmed', 'picking'], true)
				? array_slice($this->PickableEntries($pid, $branchId), 0, 3)
				: [];
			$i['item_picks'] = $picksByProduct[$pid] ?? [];

			if ($o['status'] === 'draft' && $reqAmt > $freeOther + 0.0001)
			{
				$i['shortage'] = round($reqAmt - $freeOther, 2);
				$hasShortage = true;
			}
			elseif (in_array($o['status'], ['picked', 'packed', 'dispatched'], true) && $pickedAmt < $reqAmt - 0.0001)
			{
				$i['shortage'] = round($reqAmt - $pickedAmt, 2);
				$hasShortage = true;
			}
			else
			{
				$i['shortage'] = 0.0;
			}
		}
		unset($i);

		// Historial cronológico de eventos reales del pedido
		$history = [];
		if (!empty($o['created_at']))
		{
			$history[] = [
				'step' => 'Borrador creado',
				'timestamp' => $o['created_at'],
				'user' => $o['created_by_username'] ?: 'Sistema',
				'detail' => 'Pedido registrado con ' . count($items) . ' línea(s)',
				'tone' => 'secondary'
			];
		}
		if (!empty($o['confirmed_at']))
		{
			$history[] = [
				'step' => 'Confirmado y stock reservado',
				'timestamp' => $o['confirmed_at'],
				'user' => $o['confirmed_by_username'] ?: 'Operador',
				'detail' => 'Reserva activa en ' . $o['branch_name'],
				'tone' => 'info'
			];
		}
		if (!empty($o['picked_at']))
		{
			$history[] = [
				'step' => 'Preparación (Picking FEFO) completada',
				'timestamp' => $o['picked_at'],
				'user' => $o['picked_by_username'] ?: 'Operador',
				'detail' => count($picks) . ' asignación(es) de lote registradas',
				'tone' => 'primary'
			];
		}
		if (!empty($o['packed_at']))
		{
			$history[] = [
				'step' => 'Empacado completado',
				'timestamp' => $o['packed_at'],
				'user' => $o['packed_by_username'] ?: 'Operador',
				'detail' => ($o['packages'] ? $o['packages'] . ' bulto(s) sellado(s)' : 'Empaque verificado'),
				'tone' => 'primary'
			];
		}
		if (!empty($o['dispatched_at']))
		{
			$history[] = [
				'step' => 'Despachado y facturado',
				'timestamp' => $o['dispatched_at'],
				'user' => $o['dispatched_by_username'] ?: 'Operador',
				'detail' => !empty($o['sale_number']) ? ('Venta generada: ' . $o['sale_number']) : 'Inventario descontado sobre lotes preparados',
				'tone' => 'success'
			];
		}
		if (!empty($o['cancelled_at']))
		{
			$history[] = [
				'step' => 'Pedido anulado (Reserva liberada)',
				'timestamp' => $o['cancelled_at'],
				'user' => $o['cancelled_by_username'] ?: 'Operador',
				'detail' => 'Motivo: ' . ($o['cancel_reason'] ?: 'Sin motivo especificado'),
				'tone' => 'danger'
			];
		}

		$o['has_shortage'] = $hasShortage;
		$o['history'] = $history;

		$total = round(array_sum(array_map(fn($i) => floatval($i['amount']) * floatval($i['unit_price']), $items)), 2);
		return [
			'order' => $o,
			'items' => $items,
			'picks' => $picks,
			'total' => $total
		];
	}

	public function List(?int $branchId, ?string $status = null, int $limit = 100): array
	{
		$where = ['1 = 1'];
		$p = [];
		if ($branchId)
		{
			$where[] = 'o.branch_id = :b';
			$p[':b'] = $branchId;
		}
		if ($status === 'open')
		{
			$where[] = "o.status NOT IN ('dispatched', 'cancelled')";
		}
		elseif ($status && $status !== 'all')
		{
			$where[] = 'o.status = :s';
			$p[':s'] = $status;
		}
		$rows = $this->Rows("
			SELECT
				o.*,
				c.name AS customer_name,
				c.tax_id AS customer_tax_id,
				b.name AS branch_name,
				s.sale_number,
				(SELECT COUNT(*) FROM customer_order_items i WHERE i.order_id = o.id) AS lines,
				(SELECT IFNULL(SUM(i.amount * i.unit_price), 0) FROM customer_order_items i WHERE i.order_id = o.id) AS total,
				(SELECT IFNULL(SUM(i.amount), 0) FROM customer_order_items i WHERE i.order_id = o.id) AS total_units,
				(SELECT IFNULL(SUM(i.picked_amount), 0) FROM customer_order_items i WHERE i.order_id = o.id) AS picked_units,
				(SELECT GROUP_CONCAT(p.name || ' ' || IFNULL(p.sku, ''), ' | ') FROM customer_order_items i JOIN products p ON p.id = i.product_id WHERE i.order_id = o.id) AS product_search
			FROM customer_orders o
			JOIN customers c ON c.id = o.customer_id
			JOIN branches b ON b.id = o.branch_id
			LEFT JOIN sales s ON s.id = o.sale_id
			WHERE " . implode(' AND ', $where) . "
			ORDER BY o.id DESC
			LIMIT " . intval($limit), $p);

		$rows = array_values(array_filter($rows, fn($r) => DairyAccess::CanSeeBranch(intval($r['branch_id']))));
		foreach ($rows as &$r)
		{
			$r['status_label'] = self::STATUS[$r['status']] ?? $r['status'];
			$r['total'] = round(floatval($r['total']), 2);
			if ($r['status'] === 'cancelled')
			{
				$r['payment_status'] = 'cancelled';
				$r['payment_label'] = 'Anulado';
			}
			elseif (!empty($r['sale_id']) || $r['status'] === 'dispatched')
			{
				$r['payment_status'] = 'invoiced';
				$r['payment_label'] = 'Facturado';
			}
			else
			{
				$r['payment_status'] = 'pending';
				$r['payment_label'] = 'Por facturar';
			}
			$delType = strtolower((string)($r['delivery_type'] ?? 'pickup'));
			$r['delivery_type'] = $delType ?: 'pickup';
			$r['delivery_type_label'] = $delType === 'delivery' ? 'Domicilio' : 'Retiro en tienda';
			$r['has_shortage'] = in_array($r['status'], ['picked', 'packed'], true) && (floatval($r['picked_units']) < floatval($r['total_units']) - 0.0001);
		}
		return $rows;
	}
}
