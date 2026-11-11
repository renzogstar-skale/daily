<?php

namespace Grocy\Services;

/**
 * Devoluciones comerciales del POS: ticket → elegibilidad → productos, cantidades y motivo → autorización por importe y
 * rol → reembolso al medio original, nota de crédito o cambio → comprobante → solicitud de inspección física.
 *
 * Reglas:
 * - Solo ventas completadas de la sucursal, dentro del plazo (pos_return_days); nunca más unidades que las vendidas
 *   menos las ya devueltas, ni más dinero que lo pagado por la línea (precio neto de descuentos + impuesto).
 * - Reembolso por medio de pago con tope de lo cobrado en ese medio menos lo ya reembolsado; efectivo solo con turno de
 *   caja abierto (sale de la gaveta); tarjeta y transferencia con el código de reverso del datáfono (sin datos de tarjeta).
 * - Una referencia única por devolución (return_ref): un reintento devuelve la misma devolución.
 * - El POS no reintegra stock: cada línea queda como solicitud de inspección en el módulo de devoluciones (/returns),
 *   que decide reventa, cuarentena, merma o proveedor.
 */
class PosReturnService extends BaseService
{
	public const REASONS = ['defectuoso' => 'Producto defectuoso', 'no_deseado' => 'Ya no lo quiere', 'equivocado' => 'Producto o talla equivocada',
		'mal_estado' => 'Vencido o en mal estado', 'danado' => 'Empaque dañado', 'no_conforme' => 'No coincide con lo ofrecido', 'otro' => 'Otro motivo'];
	public const CONDITIONS = ['sin_abrir' => 'Sin abrir', 'abierto' => 'Abierto', 'danado' => 'Dañado'];
	private const PERISHABLE_DEPTS = ['dairy', 'carnes', 'fruver', 'bakery', 'congelados'];

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
		return $this->Rows($sql, $p)[0] ?? null;
	}

	/** Busca la venta por ticket, referencia del cliente, transacción de inventario o referencia de pago */
	public function Find(string $q, int $branchId): ?int
	{
		$q = trim($q);
		if ($q === '')
		{
			return null;
		}
		$r = $this->Row("SELECT s.id FROM sales s WHERE s.branch_id = :b AND (s.sale_number = :q OR s.client_ref = :q
				OR s.id IN (SELECT sale_id FROM sale_items WHERE transaction_id = :q)
				OR s.id IN (SELECT sale_id FROM daily_pos_payments WHERE reference = :q)) ORDER BY s.id DESC LIMIT 1", [':b' => $branchId, ':q' => $q]);
		return $r ? intval($r['id']) : null;
	}

	/** Venta con elegibilidad por línea y por medio de pago */
	public function Eligibility(int $saleId): array
	{
		$s = $this->Row('SELECT s.*, b.name AS branch_name, c.name AS customer_name, u.username AS cashier FROM sales s LEFT JOIN branches b ON b.id = s.branch_id
			LEFT JOIN customers c ON c.id = s.customer_id LEFT JOIN users u ON u.id = s.user_id WHERE s.id = ?', [$saleId]);
		if (!$s)
		{
			throw new \Exception('Venta no encontrada');
		}
		$set = PosService::GetInstance()->Settings();
		$days = intval(floor((strtotime(date('Y-m-d')) - strtotime(substr($s['created_timestamp'], 0, 10))) / 86400));
		$blocks = [];
		if ($s['status'] !== 'completed')
		{
			$blocks[] = 'La venta está anulada: no admite devoluciones';
		}
		if ($days > $set['return_days'])
		{
			$blocks[] = "La venta tiene {$days} días: el plazo de devolución es de {$set['return_days']} días";
		}
		$lines = $this->Rows('SELECT si.id, si.product_id, si.amount, si.unit_price, si.subtotal, si.unit_cost, si.stock_id, p.name, p.sku, p.department,
				(SELECT SUM(subtotal) FROM sale_items x WHERE x.sale_id = si.sale_id) AS ls
			FROM sale_items si JOIN products p ON p.id = si.product_id WHERE si.sale_id = ? ORDER BY si.id', [$saleId]);
		$prior = [];
		foreach ($this->Rows("SELECT sale_item_id, SUM(quantity) AS q, SUM(IFNULL(customer_refund_amount, 0)) AS amt FROM customer_returns_exchanges
			WHERE sale_id = ? AND IFNULL(status, '') NOT IN ('rechazada', 'anulada') AND sale_item_id IS NOT NULL GROUP BY sale_item_id", [$saleId]) as $r)
		{
			$prior[intval($r['sale_item_id'])] = $r;
		}
		$deptOf = [];
		foreach (DailyMetricsService::Departments() as $k => $d)
		{
			foreach ($d[2] ?? [] as $v)
			{
				$deptOf[$v] = $k;
			}
		}
		$out = [];
		foreach ($lines as $l)
		{
			// Lo pagado por la línea: su parte del total del ticket (precio con descuentos + impuesto)
			$paid = PosService::Money(floatval($l['ls']) > 0 ? floatval($l['subtotal']) * floatval($s['total_amount']) / floatval($l['ls']) : 0);
			$retQ = floatval($prior[intval($l['id'])]['q'] ?? 0);
			$retA = floatval($prior[intval($l['id'])]['amt'] ?? 0);
			$out[] = ['sale_item_id' => intval($l['id']), 'product_id' => intval($l['product_id']), 'name' => $l['name'], 'sku' => $l['sku'], 'sold' => floatval($l['amount']),
				'returned' => $retQ, 'returnable' => max(0.0, round(floatval($l['amount']) - $retQ, 3)), 'paid' => $paid, 'refunded' => PosService::Money($retA),
				'unit_refund' => floatval($l['amount']) > 0 ? round($paid / floatval($l['amount']), 4) : 0.0,
				'perishable' => in_array($deptOf[$l['department']] ?? '', self::PERISHABLE_DEPTS, true)];
		}
		// Medios de pago: lo cobrado (pagos del POS o, en ventas anteriores, el medio del ticket) menos lo ya reembolsado
		$methods = [];
		$pays = $this->Rows('SELECT method, SUM(amount) AS amt FROM daily_pos_payments WHERE sale_id = ? GROUP BY method', [$saleId]);
		if ($pays)
		{
			foreach ($pays as $p)
			{
				$methods[$p['method']] = floatval($p['amt']);
			}
		}
		elseif (in_array($s['payment_method'], ['cash', 'card', 'transfer'], true))
		{
			$methods[$s['payment_method']] = floatval($s['total_amount']);
		}
		$refunded = array_column($this->Rows('SELECT method, SUM(amount) AS amt FROM daily_pos_refunds WHERE sale_id = ? GROUP BY method', [$saleId]), 'amt', 'method');
		$refundable = [];
		foreach ($methods as $m => $amt)
		{
			$refundable[$m] = PosService::Money(max(0, $amt - floatval($refunded[$m] ?? 0)));
		}
		$legacyRefunded = floatval($this->Row("SELECT IFNULL(SUM(customer_refund_amount), 0) AS a FROM customer_returns_exchanges WHERE sale_id = ? AND IFNULL(status, '') NOT IN ('rechazada', 'anulada')", [$saleId])['a']);
		return ['sale' => ['id' => intval($s['id']), 'number' => $s['sale_number'], 'datetime' => $s['created_timestamp'], 'days' => $days, 'status' => $s['status'],
			'branch_id' => intval($s['branch_id']), 'branch' => $s['branch_name'], 'customer_id' => intval($s['customer_id']), 'customer' => $s['customer_name'] ?: 'Público general',
			'cashier' => $s['cashier'], 'total' => floatval($s['total_amount']), 'payment_method' => $s['payment_method']],
			'eligible' => !$blocks, 'blocks' => $blocks, 'lines' => $out, 'refundable_by_method' => $refundable,
			'refundable_total' => PosService::Money(max(0, floatval($s['total_amount']) - $legacyRefunded)),
			'policy' => ['days' => $set['return_days'], 'auth_threshold' => $set['return_auth_threshold']]];
	}

	/**
	 * Registra la devolución. $d: return_ref, sale_id, register, resolution (refund | credit_note | exchange),
	 * lines[{sale_item_id, quantity, reason, condition}], refunds[{method, amount, reference}], auth_id, notes.
	 */
	public function Process(array $d, int $userId): array
	{
		$ref = (string) ($d['return_ref'] ?? '');
		if (!preg_match('/^[A-Za-z0-9-]{16,64}$/', $ref))
		{
			throw new \Exception('Falta el identificador único de la devolución');
		}
		$prior = $this->Row('SELECT id FROM daily_pos_returns WHERE return_ref = ?', [$ref]);
		if ($prior)
		{
			return $this->Result(intval($prior['id'])) + ['replayed' => true];
		}
		$resolution = (string) ($d['resolution'] ?? '');
		if (!in_array($resolution, ['refund', 'credit_note', 'exchange'], true))
		{
			throw new \Exception('Elija reembolso, nota de crédito o cambio');
		}
		$pos = PosService::GetInstance();
		$set = $pos->Settings();
		$db = DatabaseService::GetInstance()->BeginWriteTransaction();
		try
		{
			// Dentro de la transacción de escritura: otra devolución simultánea del mismo ticket espera y ve estas cantidades
			$E = $this->Eligibility(intval($d['sale_id'] ?? 0));
			if (!$E['eligible'])
			{
				throw new \Exception(implode('. ', $E['blocks']));
			}
			$sale = $E['sale'];
			$byLine = array_column($E['lines'], null, 'sale_item_id');
			$lines = [];
			$amount = 0.0;
			$units = 0.0;
			foreach ($d['lines'] ?? [] as $l)
			{
				$id = intval($l['sale_item_id'] ?? 0);
				$qty = round(floatval($l['quantity'] ?? 0), 3);
				if ($qty <= 0)
				{
					continue;
				}
				$L = $byLine[$id] ?? null;
				if (!$L)
				{
					throw new \Exception('Un producto no pertenece a este ticket');
				}
				if (isset($lines[$id]))
				{
					throw new \Exception("{$L['name']} está repetido en la devolución");
				}
				if ($qty > $L['returnable'] + 0.0001)
				{
					throw new \Exception("{$L['name']}: solo quedan {$L['returnable']} u. por devolver (vendidas {$L['sold']}, ya devueltas {$L['returned']})");
				}
				$reason = (string) ($l['reason'] ?? '');
				$cond = (string) ($l['condition'] ?? '');
				if (!isset(self::REASONS[$reason]) || !isset(self::CONDITIONS[$cond]))
				{
					throw new \Exception("Indique motivo y estado de {$L['name']}");
				}
				// Lo devuelto nunca supera lo pagado: la última unidad devuelve exactamente el saldo de la línea
				$money = abs($qty - $L['returnable']) < 0.0001 ? PosService::Money($L['paid'] - $L['refunded']) : PosService::Money(min($qty * $L['unit_refund'], $L['paid'] - $L['refunded']));
				$lines[$id] = $L + ['qty' => $qty, 'money' => $money, 'reason' => $reason, 'condition' => $cond, 'notes' => trim((string) ($l['notes'] ?? ''))];
				$amount += $money;
				$units += $qty;
			}
			$amount = PosService::Money($amount);
			if (!$lines || $amount <= 0)
			{
				throw new \Exception('Seleccione al menos un producto y su cantidad');
			}
			if ($amount > $E['refundable_total'] + 0.005)
			{
				throw new \Exception('El importe supera lo que queda por devolver de esta venta ($' . number_format($E['refundable_total'], 2) . ')');
			}

			// Reembolso: cada medio con su tope; efectivo con turno abierto en la caja
			$refunds = [];
			$shiftId = null;
			if ($resolution === 'refund')
			{
				$sum = 0.0;
				foreach ($d['refunds'] ?? [] as $r)
				{
					$m = (string) ($r['method'] ?? '');
					$a = PosService::Money(floatval($r['amount'] ?? 0));
					if ($a <= 0)
					{
						continue;
					}
					if ($m !== 'credit_note' && !isset($E['refundable_by_method'][$m]))
					{
						throw new \Exception('El reembolso solo puede ir al medio con que se pagó (' . implode(', ', array_map(fn($k) => PosService::METHODS[$k] ?? $k, array_keys($E['refundable_by_method']))) . ') o a nota de crédito');
					}
					if ($m !== 'credit_note' && $a > $E['refundable_by_method'][$m] + 0.005)
					{
						throw new \Exception('El reembolso en ' . PosService::METHODS[$m] . ' supera lo cobrado en ese medio ($' . number_format($E['refundable_by_method'][$m], 2) . ')');
					}
					$refRef = null;
					if ($m === 'card' || $m === 'transfer')
					{
						$refRef = PosService::SafeReference($r['reference'] ?? null);
						if ($refRef === null)
						{
							throw new \Exception('Anote el código de reverso del datáfono o del comprobante de transferencia');
						}
					}
					$refunds[] = ['method' => $m, 'amount' => $a, 'reference' => $refRef];
					$sum += $a;
				}
				if (abs(PosService::Money($sum) - $amount) > 0.005)
				{
					throw new \Exception('El reembolso ($' . number_format($sum, 2) . ') debe ser exactamente el importe devuelto ($' . number_format($amount, 2) . ')');
				}
				if (in_array('cash', array_column($refunds, 'method'), true))
				{
					$sh = $pos->OpenShiftFor($sale['branch_id'], trim((string) ($d['register'] ?? '')) ?: 'Caja 1');
					if (!$sh)
					{
						throw new \Exception('Para devolver efectivo la caja debe tener un turno abierto');
					}
					$shiftId = intval($sh['id']);
					$cashOut = array_sum(array_map(fn($r) => $r['method'] === 'cash' ? $r['amount'] : 0, $refunds));
					if ($cashOut > $pos->ShiftSummary($shiftId)['expected_cash'] + 0.005)
					{
						throw new \Exception('No hay efectivo suficiente en la gaveta para este reembolso');
					}
				}
			}
			else
			{
				$refunds[] = ['method' => 'credit_note', 'amount' => $amount, 'reference' => null];
			}

			// Autorización por importe y rol
			$auth = null;
			if ($amount > $set['return_auth_threshold'] + 0.005 && !$pos->HasPermission($userId, 'SALES_SUPERVISE'))
			{
				$auth = $pos->TakeAuthorization(!empty($d['auth_id']) ? intval($d['auth_id']) : null, 'refund', $userId);
				if (floatval($auth['amount']) + 0.005 < $amount)
				{
					throw new \Exception('La autorización cubre $' . number_format(floatval($auth['amount']), 2) . ' y la devolución es de $' . number_format($amount, 2));
				}
			}

			$seq = intval($db->query('SELECT IFNULL(MAX(id), 0) + 1 FROM daily_pos_returns')->fetchColumn());
			$code = 'DEV-' . date('Ymd') . '-' . str_pad((string) $seq, 5, '0', STR_PAD_LEFT);
			$db->prepare('INSERT INTO daily_pos_returns (code, return_ref, sale_id, branch_id, shift_id, customer_id, resolution, amount, items_count, auth_id, user_id, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
				->execute([$code, $ref, $sale['id'], $sale['branch_id'], $shiftId, $sale['customer_id'], $resolution, $amount, $units, $auth['id'] ?? null, $userId, trim((string) ($d['notes'] ?? '')) ?: null]);
			$retId = intval($db->lastInsertId());
			if ($auth)
			{
				$pos->UseAuthorization(intval($auth['id']), $sale['id'], $shiftId);
			}

			// Nota de crédito (resolución o parte del reembolso)
			$noteAmt = PosService::Money(array_sum(array_map(fn($r) => $r['method'] === 'credit_note' ? $r['amount'] : 0, $refunds)));
			$noteCode = null;
			if ($noteAmt > 0)
			{
				$noteCode = 'NC-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
				$db->prepare('INSERT INTO daily_credit_notes (code, customer_id, branch_id, return_id, amount, balance, expires_at) VALUES (?, ?, ?, ?, ?, ?, ?)')
					->execute([$noteCode, $sale['customer_id'], $sale['branch_id'], $retId, $noteAmt, $noteAmt, date('Y-m-d', strtotime('+' . $set['credit_note_days'] . ' days'))]);
				$noteId = intval($db->lastInsertId());
				$db->prepare("INSERT INTO daily_credit_note_movements (note_id, kind, amount, return_id, user_id) VALUES (?, 'issue', ?, ?, ?)")->execute([$noteId, $noteAmt, $retId, $userId]);
				$db->prepare('UPDATE daily_pos_returns SET credit_note_id = ? WHERE id = ?')->execute([$noteId, $retId]);
				foreach ($refunds as &$r)
				{
					if ($r['method'] === 'credit_note')
					{
						$r['reference'] = $noteCode;
					}
				}
				unset($r);
			}
			$insRef = $db->prepare('INSERT INTO daily_pos_refunds (return_id, sale_id, shift_id, method, amount, reference) VALUES (?, ?, ?, ?, ?, ?)');
			foreach ($refunds as $r)
			{
				$insRef->execute([$retId, $sale['id'], $r['method'] === 'cash' ? $shiftId : null, $r['method'], $r['amount'], $r['reference']]);
			}

			// Cada línea = solicitud de inspección física en /returns (sin movimiento de stock)
			$primary = $refunds[0]['method'];
			$ins = $db->prepare("INSERT INTO customer_returns_exchanges (return_code, branch_id, customer_id, product_id, stock_id, return_type, quantity, unit_cost, total_cost,
					recoverable_value, definitive_loss_value, supplier_credit_amount, customer_refund_amount, classification, is_perishable, inspection_result, inspection_notes,
					approval_status, status, created_at, sale_id, sale_item_id, ticket_number, resolution, condition_state, refund_method, refund_status, refund_gateway_ref,
					cashier_user_id, evidence_notes, is_pos_return, pos_return_id)
				VALUES (?, ?, ?, ?, ?, 'devolucion_cliente', ?, ?, ?, 0, 0, 0, ?, 'pendiente_inspeccion', ?, 'Pendiente de inspección física', ?, 'pending', 'solicitud',
					datetime('now', 'localtime'), ?, ?, ?, ?, ?, ?, 'completed', ?, ?, ?, 1, ?)");
			$n = 0;
			foreach ($lines as $L)
			{
				$n++;
				$unitCost = floatval($this->Row('SELECT unit_cost FROM sale_items WHERE id = ?', [$L['sale_item_id']])['unit_cost'] ?? 0);
				$ins->execute([$code . '-' . $n, $sale['branch_id'], $sale['customer_id'], $L['product_id'], null, $L['qty'], $unitCost, PosService::Money($unitCost * $L['qty']), $L['money'],
					$L['perishable'] ? 1 : 0, self::REASONS[$L['reason']] . ' · ' . self::CONDITIONS[$L['condition']] . ($L['notes'] ? ' · ' . $L['notes'] : ''),
					$sale['id'], $L['sale_item_id'], $sale['number'], $resolution === 'refund' ? 'reembolso' : 'credito_tienda', $L['condition'], $primary,
					count($refunds) > 1 ? 'mixto' : ($refunds[0]['reference'] ?? null), $userId, self::REASONS[$L['reason']], $retId]);
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
		try
		{
			UnifiedInventoryEventService::GetInstance()->Publish('customer_return.registered', 'pos_return', (string) $retId,
				['code' => $code, 'sale_id' => $sale['id'], 'amount' => $amount, 'resolution' => $resolution, 'inspection_requests' => count($lines)], $sale['branch_id'], $userId, 'return-' . $ref);
		}
		catch (\Throwable $e) {}
		return $this->Result($retId) + ['replayed' => false];
	}

	/** Comprobante de la devolución */
	public function Result(int $id): array
	{
		$r = $this->Row('SELECT r.*, s.sale_number, s.created_timestamp AS sold_at, b.name AS branch, b.address, u.username AS cashier, c.name AS customer, n.code AS credit_note, n.balance AS credit_balance, n.expires_at
			FROM daily_pos_returns r JOIN sales s ON s.id = r.sale_id LEFT JOIN branches b ON b.id = r.branch_id LEFT JOIN users u ON u.id = r.user_id LEFT JOIN customers c ON c.id = r.customer_id
			LEFT JOIN daily_credit_notes n ON n.id = r.credit_note_id WHERE r.id = ?', [$id]);
		if (!$r)
		{
			throw new \Exception('Devolución no encontrada');
		}
		return ['return' => $r,
			'lines' => $this->Rows('SELECT x.quantity, x.customer_refund_amount AS amount, x.evidence_notes AS reason, x.condition_state, x.status, x.return_code, p.name, p.sku
				FROM customer_returns_exchanges x JOIN products p ON p.id = x.product_id WHERE x.pos_return_id = ? ORDER BY x.id', [$id]),
			'refunds' => $this->Rows('SELECT method, amount, reference FROM daily_pos_refunds WHERE return_id = ? ORDER BY id', [$id])];
	}

	/** Historial de devoluciones comerciales con filtros */
	public function History(int $branchId, string $from, string $to, string $q = ''): array
	{
		$p = [':b' => $branchId, ':f' => $from, ':t' => $to];
		$w = '';
		if (trim($q) !== '')
		{
			$w = ' AND (r.code LIKE :q OR s.sale_number LIKE :q OR c.name LIKE :q)';
			$p[':q'] = '%' . trim($q) . '%';
		}
		return $this->Rows("SELECT r.id, r.code, r.created_at, r.resolution, r.amount, r.items_count, s.sale_number, c.name AS customer, u.username AS cashier, a.username AS approved,
				(SELECT group_concat(method, ', ') FROM (SELECT DISTINCT method FROM daily_pos_refunds WHERE return_id = r.id)) AS methods,
				(SELECT COUNT(*) FROM customer_returns_exchanges x WHERE x.pos_return_id = r.id AND x.status = 'solicitud') AS pending_inspection
			FROM daily_pos_returns r JOIN sales s ON s.id = r.sale_id LEFT JOIN customers c ON c.id = r.customer_id LEFT JOIN users u ON u.id = r.user_id
			LEFT JOIN daily_pos_authorizations au ON au.id = r.auth_id LEFT JOIN users a ON a.id = au.approved_by
			WHERE r.branch_id = :b AND date(r.created_at) BETWEEN :f AND :t$w ORDER BY r.id DESC LIMIT 300", $p);
	}

	/** Indicadores: tasa de devolución por importe y unidades, motivos, resolución, medios y productos */
	public function Kpis(int $branchId, string $from, string $to): array
	{
		$p = [':b' => $branchId, ':f' => $from, ':t' => $to];
		$ret = $this->Row("SELECT COUNT(*) AS n, IFNULL(SUM(amount), 0) AS amt, IFNULL(SUM(items_count), 0) AS units, SUM(auth_id IS NOT NULL) AS authorized
			FROM daily_pos_returns WHERE branch_id = :b AND date(created_at) BETWEEN :f AND :t", $p);
		$sales = $this->Row("SELECT COUNT(*) AS n, IFNULL(SUM(total_amount), 0) AS amt FROM sales WHERE branch_id = :b AND status = 'completed' AND date(created_timestamp) BETWEEN :f AND :t", $p);
		$units = floatval($this->Row("SELECT IFNULL(SUM(si.amount), 0) AS u FROM sale_items si JOIN sales s ON s.id = si.sale_id WHERE s.branch_id = :b AND s.status = 'completed' AND date(s.created_timestamp) BETWEEN :f AND :t", $p)['u']);
		$x = "FROM customer_returns_exchanges x JOIN daily_pos_returns r ON r.id = x.pos_return_id WHERE r.branch_id = :b AND date(r.created_at) BETWEEN :f AND :t";
		return [
			'returns' => intval($ret['n']), 'amount' => PosService::Money(floatval($ret['amt'])), 'units' => floatval($ret['units']), 'authorized' => intval($ret['authorized']),
			'sales' => intval($sales['n']), 'sales_amount' => PosService::Money(floatval($sales['amt'])), 'sold_units' => $units,
			'rate_amount_pct' => floatval($sales['amt']) > 0 ? round(floatval($ret['amt']) / floatval($sales['amt']) * 100, 2) : null,
			'rate_units_pct' => $units > 0 ? round(floatval($ret['units']) / $units * 100, 2) : null,
			'by_reason' => $this->Rows("SELECT x.evidence_notes AS reason, COUNT(*) AS n, SUM(x.customer_refund_amount) AS amount $x GROUP BY x.evidence_notes ORDER BY amount DESC", $p),
			'by_resolution' => $this->Rows('SELECT resolution, COUNT(*) AS n, SUM(amount) AS amount FROM daily_pos_returns WHERE branch_id = :b AND date(created_at) BETWEEN :f AND :t GROUP BY resolution', $p),
			'by_method' => $this->Rows('SELECT f.method, SUM(f.amount) AS amount FROM daily_pos_refunds f JOIN daily_pos_returns r ON r.id = f.return_id WHERE r.branch_id = :b AND date(r.created_at) BETWEEN :f AND :t GROUP BY f.method', $p),
			'top_products' => $this->Rows("SELECT p.name, p.sku, SUM(x.quantity) AS units, SUM(x.customer_refund_amount) AS amount FROM customer_returns_exchanges x JOIN daily_pos_returns r ON r.id = x.pos_return_id JOIN products p ON p.id = x.product_id
				WHERE r.branch_id = :b AND date(r.created_at) BETWEEN :f AND :t GROUP BY x.product_id ORDER BY amount DESC LIMIT 10", $p),
			'pending_inspection' => intval($this->Row("SELECT COUNT(*) AS n $x AND x.status = 'solicitud'", $p)['n'])
		];
	}

	public function CreditNotes(int $branchId): array
	{
		return $this->Rows("SELECT n.*, c.name AS customer, r.code AS return_code FROM daily_credit_notes n LEFT JOIN customers c ON c.id = n.customer_id
			LEFT JOIN daily_pos_returns r ON r.id = n.return_id WHERE n.branch_id = ? ORDER BY n.id DESC LIMIT 200", [$branchId]);
	}

	public function CreditNote(string $code): array
	{
		$n = $this->Row('SELECT n.*, c.name AS customer FROM daily_credit_notes n LEFT JOIN customers c ON c.id = n.customer_id WHERE n.code = ?', [trim($code)]);
		if (!$n)
		{
			throw new \Exception('Nota de crédito no encontrada');
		}
		$n['usable'] = $n['status'] === 'active' && floatval($n['balance']) > 0 && ($n['expires_at'] === null || $n['expires_at'] >= date('Y-m-d'));
		$n['movements'] = $this->Rows('SELECT m.*, s.sale_number FROM daily_credit_note_movements m LEFT JOIN sales s ON s.id = m.sale_id WHERE m.note_id = ? ORDER BY m.id', [$n['id']]);
		return $n;
	}
}
