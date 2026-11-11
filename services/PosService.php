<?php

namespace Grocy\Services;

/**
 * Punto de venta retail: turno de caja → escaneo → carrito → descuento autorizado → impuesto → cobro (efectivo, tarjeta,
 * transferencia o mixto) → recibo → anulación/reimpresión autorizadas → arqueo y cierre.
 *
 * Reglas:
 * - El servidor calcula precios, descuentos, impuestos y total; la terminal envía el total que mostró y si no coincide la
 *   venta se rechaza (nunca se cobra un total distinto del registrado).
 * - Cada venta lleva un client_ref (UUID) único: reintentos y sincronizaciones sin conexión devuelven la venta ya
 *   registrada sin cobrar ni descontar stock de nuevo.
 * - Stock, venta, costo, pagos y autorización se guardan en una sola transacción (SalesService::CreateSale) y se publica
 *   un único evento de venta confirmada (UUID determinista) para inventario, ventas y rentabilidad.
 * - Descuentos sobre el límite del cajero, anulaciones, reimpresiones adicionales y diferencias de arqueo exigen la
 *   clave de un usuario con SALES_SUPERVISE.
 * - Nunca se guardan datos de tarjeta: solo medio, importe y referencia de autorización.
 */
class PosService extends BaseService
{
	public const METHODS = ['cash' => 'Efectivo', 'card' => 'Tarjeta', 'transfer' => 'Transferencia', 'credit_note' => 'Nota de crédito', 'points' => 'Canje de puntos'];

	private function Db(): \PDO
	{
		return DatabaseService::GetInstance()->GetDbConnectionRaw();
	}

	private function Row(string $sql, array $p = []): ?array
	{
		$st = $this->Db()->prepare($sql);
		$st->execute($p);
		$r = $st->fetch(\PDO::FETCH_ASSOC);
		return $r ?: null;
	}

	private function Rows(string $sql, array $p = []): array
	{
		$st = $this->Db()->prepare($sql);
		$st->execute($p);
		return $st->fetchAll(\PDO::FETCH_ASSOC);
	}

	public function Settings(): array
	{
		$s = array_column($this->Rows("SELECT setting_key, setting_value FROM daily_system_settings WHERE setting_key LIKE 'pos\\_%' ESCAPE '\\'"), 'setting_value', 'setting_key');
		return [
			'tax_rates' => json_decode($s['pos_tax_rates'] ?? '{}', true) ?: [],
			'default_tax_rate' => floatval($s['pos_default_tax_rate'] ?? 0),
			'cashier_max_discount_pct' => floatval($s['pos_cashier_max_discount_pct'] ?? 0),
			'supervisor_max_discount_pct' => floatval($s['pos_supervisor_max_discount_pct'] ?? 30),
			'cash_diff_tolerance' => floatval($s['pos_cash_diff_tolerance'] ?? 5),
			'free_reprints' => intval($s['pos_free_reprints'] ?? 1),
			'offline_max_hours' => intval($s['pos_offline_max_hours'] ?? 72),
			'return_days' => intval($s['pos_return_days'] ?? 30),
			'return_auth_threshold' => floatval($s['pos_return_auth_threshold'] ?? 50),
			'credit_note_days' => intval($s['pos_credit_note_days'] ?? 365)
		];
	}

	public static function Money(float $v): float
	{
		return round($v + 0.0000001, 2);
	}

	/* ------------------------------------------------------------------ autorizaciones */

	public function HasPermission(int $userId, string $perm): bool
	{
		return (bool) $this->Row("SELECT 1 FROM user_permissions_resolved WHERE user_id = ? AND permission_name IN (?, 'ADMIN') LIMIT 1", [$userId, $perm]);
	}

	/**
	 * Un supervisor autoriza una operación con su usuario y clave. Devuelve la autorización (válida 10 minutos y una sola
	 * vez) que la terminal adjunta a la venta, anulación, reimpresión o cierre.
	 */
	public function Authorize(string $kind, string $username, string $password, string $reason, array $ctx, int $requestedBy): array
	{
		if (!in_array($kind, ['discount', 'void', 'reprint', 'cash_difference', 'refund'], true))
		{
			throw new \Exception('Tipo de autorización no válido');
		}
		$reason = trim($reason);
		if (mb_strlen($reason) < 3)
		{
			throw new \Exception('Indique el motivo de la autorización');
		}
		$u = $this->Row('SELECT id, password FROM users WHERE username = ?', [trim($username)]);
		// Misma respuesta para usuario inexistente y clave errónea
		if (!$u || $password === '' || !password_verify($password, (string) $u['password']))
		{
			usleep(300000);
			throw new \Exception('Usuario o clave de supervisor incorrectos');
		}
		$approver = intval($u['id']);
		if (!$this->HasPermission($approver, 'SALES_SUPERVISE'))
		{
			throw new \Exception('El usuario no tiene permiso de supervisor de caja');
		}
		$pct = isset($ctx['pct']) ? round(floatval($ctx['pct']), 2) : null;
		if ($kind === 'discount')
		{
			$max = $this->Settings()['supervisor_max_discount_pct'];
			if ($pct === null || $pct <= 0)
			{
				throw new \Exception('Indique el porcentaje de descuento a autorizar');
			}
			if ($pct > $max + 0.001)
			{
				throw new \Exception("El descuento supera el máximo que puede autorizar un supervisor ({$max} %)");
			}
		}
		$this->Db()->prepare('INSERT INTO daily_pos_authorizations (kind, branch_id, shift_id, sale_id, requested_by, approved_by, amount, pct, reason) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)')
			->execute([$kind, $ctx['branch_id'] ?? null, $ctx['shift_id'] ?? null, $ctx['sale_id'] ?? null, $requestedBy, $approver,
				isset($ctx['amount']) ? round(floatval($ctx['amount']), 2) : null, $pct, $reason]);
		return ['id' => intval($this->Db()->lastInsertId()), 'kind' => $kind, 'approved_by' => $approver, 'pct' => $pct];
	}

	/** Autorización vigente (10 min, sin usar, del mismo tipo y solicitante) */
	public function TakeAuthorization(?int $id, string $kind, int $requestedBy, ?int $saleId = null): array
	{
		$a = $id ? $this->Row("SELECT * FROM daily_pos_authorizations WHERE id = ? AND kind = ? AND status = 'granted' AND requested_by = ?
			AND created_at >= datetime('now', 'localtime', '-10 minutes')", [$id, $kind, $requestedBy]) : null;
		if (!$a || ($saleId !== null && $a['sale_id'] !== null && intval($a['sale_id']) !== $saleId))
		{
			throw new \Exception(['discount' => 'El descuento requiere autorización de un supervisor', 'void' => 'La anulación requiere autorización de un supervisor',
				'reprint' => 'Esta reimpresión requiere autorización de un supervisor', 'cash_difference' => 'La diferencia de caja requiere autorización de un supervisor',
				'refund' => 'La devolución requiere autorización de un supervisor'][$kind]);
		}
		return $a;
	}

	public function UseAuthorization(int $id, ?int $saleId, ?int $shiftId = null): void
	{
		$st = $this->Db()->prepare("UPDATE daily_pos_authorizations SET status = 'used', used_at = datetime('now', 'localtime'), sale_id = IFNULL(?, sale_id), shift_id = IFNULL(?, shift_id) WHERE id = ? AND status = 'granted'");
		$st->execute([$saleId, $shiftId, $id]);
		if ($st->rowCount() !== 1)
		{
			throw new \Exception('La autorización ya fue utilizada');
		}
	}

	/* ------------------------------------------------------------------ turnos de caja */

	public function Registers(int $branchId): array
	{
		$names = array_column($this->Rows('SELECT DISTINCT register_name FROM pos_cash_shifts WHERE branch_id = ? ORDER BY register_name', [$branchId]), 'register_name');
		return array_values(array_unique(array_merge(['Caja 1'], $names)));
	}

	public function OpenShiftFor(int $branchId, string $register): ?array
	{
		return $this->Row("SELECT * FROM pos_cash_shifts WHERE branch_id = ? AND register_name = ? AND status = 'open' ORDER BY id DESC LIMIT 1", [$branchId, $register]);
	}

	public function OpenShift(int $branchId, string $register, float $openingAmount, string $shiftName, int $userId): array
	{
		$register = trim($register) ?: 'Caja 1';
		if ($openingAmount < 0)
		{
			throw new \Exception('El fondo de apertura no puede ser negativo');
		}
		if (!$this->Row('SELECT 1 FROM branches WHERE id = ?', [$branchId]))
		{
			throw new \Exception('Sucursal no válida');
		}
		$db = DatabaseService::GetInstance()->BeginWriteTransaction();
		try
		{
			if ($this->OpenShiftFor($branchId, $register))
			{
				throw new \Exception("{$register} ya tiene un turno abierto: ciérrelo con su arqueo antes de abrir otro");
			}
			$db->prepare("INSERT INTO pos_cash_shifts (branch_id, register_name, shift_name, user_id, status, opening_amount, opened_at) VALUES (?, ?, ?, ?, 'open', ?, ?)")
				->execute([$branchId, $register, trim($shiftName) ?: 'Turno', $userId, self::Money($openingAmount), date('Y-m-d H:i:s')]);
			$id = intval($db->lastInsertId());
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
		return $this->ShiftSummary($id);
	}

	/** Totales del turno a partir de los pagos registrados (no de notas ni repartos estimados) */
	public function ShiftSummary(int $shiftId): array
	{
		$s = $this->Row('SELECT s.*, u.username AS opened_by, b.name AS branch_name FROM pos_cash_shifts s LEFT JOIN users u ON u.id = s.user_id LEFT JOIN branches b ON b.id = s.branch_id WHERE s.id = ?', [$shiftId]);
		if (!$s)
		{
			throw new \Exception('Turno no encontrado');
		}
		$byMethod = array_fill_keys(array_keys(self::METHODS), 0.0);
		foreach ($this->Rows("SELECT p.method, SUM(p.amount) AS amt FROM daily_pos_payments p JOIN sales x ON x.id = p.sale_id
			WHERE p.shift_id = ? AND x.status = 'completed' GROUP BY p.method", [$shiftId]) as $r)
		{
			$byMethod[$r['method']] = self::Money(floatval($r['amt']));
		}
		$sales = $this->Row("SELECT SUM(status = 'completed') AS n, SUM(CASE WHEN status = 'completed' THEN total_amount END) AS total,
			SUM(CASE WHEN status = 'completed' THEN discount_amount END) AS disc, SUM(CASE WHEN status = 'completed' THEN tax_amount END) AS tax,
			SUM(status IN ('cancelled', 'void')) AS voided FROM sales WHERE shift_id = ?", [$shiftId]);
		$mv = $this->Row("SELECT IFNULL(SUM(CASE WHEN kind = 'payin' THEN amount END), 0) AS payin, IFNULL(SUM(CASE WHEN kind = 'drop' THEN amount END), 0) AS drops FROM daily_pos_cash_movements WHERE shift_id = ?", [$shiftId]);
		$refunds = array_fill_keys(array_keys(self::METHODS), 0.0);
		try
		{
			foreach ($this->Rows('SELECT method, SUM(amount) AS amt FROM daily_pos_refunds WHERE shift_id = ? GROUP BY method', [$shiftId]) as $r)
			{
				$refunds[$r['method']] = self::Money(floatval($r['amt']));
			}
		}
		catch (\Throwable $e) {}
		$expected = self::Money(floatval($s['opening_amount']) + $byMethod['cash'] - $refunds['cash'] + floatval($mv['payin']) - floatval($mv['drops']));
		return [
			'id' => intval($s['id']), 'branch_id' => intval($s['branch_id']), 'branch_name' => $s['branch_name'], 'register' => $s['register_name'], 'shift_name' => $s['shift_name'],
			'status' => $s['status'], 'opened_at' => $s['opened_at'], 'opened_by' => $s['opened_by'], 'closed_at' => $s['closed_at'],
			'opening_amount' => floatval($s['opening_amount']), 'sales_count' => intval($sales['n'] ?? 0), 'voided_count' => intval($sales['voided'] ?? 0),
			'total_sales' => self::Money(floatval($sales['total'] ?? 0)), 'discount_total' => self::Money(floatval($sales['disc'] ?? 0)), 'tax_total' => self::Money(floatval($sales['tax'] ?? 0)),
			'by_method' => $byMethod, 'refunds' => $refunds, 'payins' => self::Money(floatval($mv['payin'])), 'drops' => self::Money(floatval($mv['drops'])), 'expected_cash' => $expected,
			'counted_cash' => $s['counted_cash'] !== null ? floatval($s['counted_cash']) : null, 'cash_difference' => $s['cash_difference'] !== null ? floatval($s['cash_difference']) : null,
			'difference_reason' => $s['difference_reason'] ?? null,
			'movements' => $this->Rows('SELECT m.*, u.username FROM daily_pos_cash_movements m LEFT JOIN users u ON u.id = m.user_id WHERE m.shift_id = ? ORDER BY m.id', [$shiftId])
		];
	}

	public function CashMovement(int $shiftId, string $kind, float $amount, string $reason, int $userId): array
	{
		$s = $this->Row("SELECT * FROM pos_cash_shifts WHERE id = ? AND status = 'open'", [$shiftId]);
		if (!$s)
		{
			throw new \Exception('El turno no está abierto');
		}
		if (!in_array($kind, ['drop', 'payin'], true) || $amount <= 0 || mb_strlen(trim($reason)) < 3)
		{
			throw new \Exception('Indique tipo, monto mayor a cero y motivo del movimiento');
		}
		if ($kind === 'drop' && $amount > $this->ShiftSummary($shiftId)['expected_cash'] + 0.001)
		{
			throw new \Exception('El retiro supera el efectivo esperado en la gaveta');
		}
		$this->Db()->prepare('INSERT INTO daily_pos_cash_movements (shift_id, kind, amount, reason, user_id) VALUES (?, ?, ?, ?, ?)')
			->execute([$shiftId, $kind, self::Money($amount), trim($reason), $userId]);
		return $this->ShiftSummary($shiftId);
	}

	/** Arqueo y cierre: efectivo contado vs. esperado; fuera de tolerancia exige motivo y autorización */
	public function CloseShift(int $shiftId, float $counted, ?array $breakdown, string $reason, ?int $authId, int $userId): array
	{
		$db = DatabaseService::GetInstance()->BeginWriteTransaction();
		try
		{
			$sum = $this->ShiftSummary($shiftId);
			if ($sum['status'] !== 'open')
			{
				throw new \Exception('El turno ya está cerrado');
			}
			if ($counted < 0)
			{
				throw new \Exception('El efectivo contado no puede ser negativo');
			}
			if ($breakdown)
			{
				$fromBreakdown = 0.0;
				foreach ($breakdown as $den => $qty)
				{
					$fromBreakdown += floatval($den) * max(0, intval($qty));
				}
				if (abs($fromBreakdown - $counted) > 0.005)
				{
					throw new \Exception('El conteo por denominación (' . number_format($fromBreakdown, 2) . ') no coincide con el efectivo contado');
				}
			}
			$diff = self::Money($counted - $sum['expected_cash']);
			$auth = null;
			if (abs($diff) > $this->Settings()['cash_diff_tolerance'] + 0.001)
			{
				if (mb_strlen(trim($reason)) < 3)
				{
					throw new \Exception('Explique la diferencia de caja de ' . number_format($diff, 2));
				}
				$auth = $this->TakeAuthorization($authId, 'cash_difference', $userId);
				$this->UseAuthorization(intval($auth['id']), null, $shiftId);
			}
			$db->prepare("UPDATE pos_cash_shifts SET status = 'closed', closed_at = ?, closed_by_user_id = ?, expected_cash = ?, counted_cash = ?, cash_difference = ?,
				sales_count = ?, total_sales = ?, total_cash = ?, total_card = ?, total_transfer = ?, counted_breakdown = ?, difference_reason = ?, difference_auth_id = ?,
				voided_count = ?, discount_total = ? WHERE id = ? AND status = 'open'")
				->execute([date('Y-m-d H:i:s'), $userId, $sum['expected_cash'], self::Money($counted), $diff, $sum['sales_count'], $sum['total_sales'], $sum['by_method']['cash'],
					$sum['by_method']['card'], $sum['by_method']['transfer'], $breakdown ? json_encode($breakdown) : null, trim($reason) ?: null, $auth ? intval($auth['id']) : null,
					$sum['voided_count'], $sum['discount_total'], $shiftId]);
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
		return $this->ShiftSummary($shiftId);
	}

	/* ------------------------------------------------------------------ cálculo y cobro */

	/** Departamento (clave) de cada producto para el impuesto */
	private function DeptOf(array $productIds): array
	{
		$map = [];
		foreach (DailyMetricsService::Departments() as $k => $d)
		{
			foreach ($d[2] ?? [] as $v)
			{
				$map[$v] = $k;
			}
		}
		$out = [];
		if ($productIds)
		{
			foreach ($this->Rows('SELECT id, department FROM products WHERE id IN (' . implode(',', array_map('intval', $productIds)) . ')') as $p)
			{
				$out[intval($p['id'])] = $map[$p['department']] ?? 'otros';
			}
		}
		return $out;
	}

	/**
	 * Cotización autoritativa: precio de lista o rebaja FEFO vigente, descuento del ticket prorrateado por línea e impuesto
	 * por departamento sobre la base ya descontada. La terminal muestra exactamente estos importes.
	 */
	public function Quote(array $items, array $discount = []): array
	{
		if (!$items)
		{
			throw new \Exception('El ticket no tiene productos');
		}
		$set = $this->Settings();
		$markdowns = MarkdownService::GetInstance()->GetActiveMarkdowns();
		$ids = [];
		$qty = [];
		foreach ($items as $it)
		{
			$pid = intval($it['product_id'] ?? 0);
			$amount = floatval($it['amount'] ?? 0);
			if ($pid <= 0 || $amount <= 0)
			{
				throw new \Exception('Cada línea necesita producto y cantidad mayor a cero');
			}
			$qty[$pid] = ($qty[$pid] ?? 0) + $amount;
			$ids[] = $pid;
		}
		$depts = $this->DeptOf($ids);
		$products = [];
		foreach ($this->Rows('SELECT p.id, p.name, p.sku, p.sale_price, IFNULL(c.price, 0) AS last_cost FROM products p LEFT JOIN cache__products_last_purchased c ON c.product_id = p.id
			WHERE p.id IN (' . implode(',', array_map('intval', $ids)) . ')') as $p)
		{
			$products[intval($p['id'])] = $p;
		}
		$lines = [];
		$subtotal = 0.0;
		$markdownTotal = 0.0;
		foreach ($qty as $pid => $amount)
		{
			$p = $products[$pid] ?? null;
			if (!$p)
			{
				throw new \Exception("El producto #{$pid} no existe");
			}
			$list = floatval($p['sale_price']) > 0 ? floatval($p['sale_price']) : self::Money(floatval($p['last_cost']) * (1 + GROCY_SALE_PRICE_DEFAULT_MARKUP_PCT / 100));
			if ($list <= 0)
			{
				throw new \Exception("{$p['name']} no tiene precio de venta: configúrelo antes de venderlo");
			}
			$mdPct = isset($markdowns[$pid]) ? floatval($markdowns[$pid]['discount_pct']) : 0.0;
			$price = $mdPct > 0 ? self::Money($list * (1 - $mdPct / 100)) : self::Money($list);
			$lineSub = self::Money($price * $amount);
			$subtotal += $lineSub;
			$markdownTotal += self::Money(($list - $price) * $amount);
			$rate = floatval($set['tax_rates'][$depts[$pid] ?? 'otros'] ?? $set['default_tax_rate']);
			$lines[] = ['product_id' => $pid, 'name' => $p['name'], 'sku' => $p['sku'], 'amount' => $amount, 'list_price' => self::Money($list), 'unit_price' => $price,
				'markdown_pct' => $mdPct, 'subtotal' => $lineSub, 'tax_rate' => $rate];
		}
		$subtotal = self::Money($subtotal);
		$type = ($discount['type'] ?? 'pct') === 'amt' ? 'amt' : 'pct';
		$value = max(0.0, floatval($discount['value'] ?? 0));
		$disc = $type === 'pct' ? self::Money($subtotal * min($value, 100) / 100) : self::Money(min($value, $subtotal));
		$discPct = $subtotal > 0 ? round($disc / $subtotal * 100, 2) : 0.0;
		// Descuento prorrateado por línea (el residuo de redondeo va a la última) e impuesto sobre la base descontada
		$tax = 0.0;
		$left = $disc;
		foreach ($lines as $i => &$l)
		{
			$share = $i === count($lines) - 1 ? $left : self::Money($subtotal > 0 ? $disc * $l['subtotal'] / $subtotal : 0);
			$left = self::Money($left - $share);
			$l['discount'] = $share;
			$l['tax'] = self::Money(($l['subtotal'] - $share) * $l['tax_rate'] / 100);
			$tax += $l['tax'];
		}
		unset($l);
		$tax = self::Money($tax);
		$total = self::Money($subtotal - $disc + $tax);
		return ['lines' => $lines, 'subtotal' => $subtotal, 'markdown_total' => self::Money($markdownTotal), 'discount' => $disc, 'discount_pct' => $discPct,
			'discount_type' => $type, 'discount_value' => $value, 'tax' => $tax, 'total' => $total, 'items' => array_sum($qty)];
	}

	/** Rechaza cualquier cosa que parezca un número de tarjeta (13–19 dígitos) o código de seguridad en la referencia */
	public static function SafeReference(?string $ref): ?string
	{
		$ref = trim((string) $ref);
		if ($ref === '')
		{
			return null;
		}
		$digits = preg_replace('/\D/', '', $ref);
		if (preg_match('/\d[\d \-]{11,}\d/', $ref) && strlen($digits) >= 12)
		{
			throw new \Exception('No registre números de tarjeta: anote solo el código de autorización del datáfono');
		}
		if (preg_match('/\b(cvv|cvc|venc|exp)\b/i', $ref))
		{
			throw new \Exception('No registre datos de seguridad de la tarjeta');
		}
		return mb_substr($ref, 0, 40);
	}

	/**
	 * Cobro de una venta. $d: client_ref, branch_id, register, customer_id, items[{product_id, amount}],
	 * discount{type, value, reason, auth_id}, payments[{method, amount, tendered?, reference?}], expected_total,
	 * offline_created_at? (venta registrada sin conexión; solo efectivo).
	 */
	public function Checkout(array $d, int $userId): array
	{
		$ref = (string) ($d['client_ref'] ?? '');
		if (!preg_match('/^[A-Za-z0-9-]{16,64}$/', $ref))
		{
			throw new \Exception('Falta el identificador único de la venta (client_ref)');
		}
		// Reintento de una venta ya registrada: misma respuesta, sin volver a cobrar
		$prior = $this->Row('SELECT id FROM sales WHERE client_ref = ?', [$ref]);
		if ($prior)
		{
			return $this->SaleResult(intval($prior['id'])) + ['replayed' => true];
		}
		$branchId = intval($d['branch_id'] ?? 0);
		$register = trim((string) ($d['register'] ?? '')) ?: 'Caja 1';
		$set = $this->Settings();
		$offlineAt = null;
		if (!empty($d['offline_created_at']))
		{
			$ts = strtotime((string) $d['offline_created_at']);
			if ($ts === false || $ts > time() + 60 || $ts < time() - $set['offline_max_hours'] * 3600)
			{
				throw new \Exception('La venta sin conexión tiene una fecha no válida o supera ' . $set['offline_max_hours'] . ' h: regístrela con un supervisor');
			}
			// La terminal siempre informa la hora de la venta: si llega en menos de 2 minutos es una venta en línea
			$offlineAt = $ts < time() - 120 ? date('Y-m-d H:i:s', $ts) : null;
		}
		// Turno: el abierto de la caja; una venta sin conexión pertenece al turno en que se hizo (aunque ya esté cerrado)
		$shift = null;
		if ($offlineAt !== null && !empty($d['shift_id']))
		{
			$shift = $this->Row('SELECT * FROM pos_cash_shifts WHERE id = ? AND branch_id = ? AND opened_at <= ?', [intval($d['shift_id']), $branchId, $offlineAt]);
		}
		$shift = $shift ?: $this->OpenShiftFor($branchId, $register);
		if (!$shift)
		{
			throw new \Exception("{$register} está cerrada: abra el turno de caja antes de vender");
		}

		$q = $this->Quote($d['items'] ?? [], $d['discount'] ?? []);
		if (!isset($d['expected_total']) || abs(floatval($d['expected_total']) - $q['total']) > 0.005)
		{
			throw new \Exception('El total de la terminal ($' . number_format(floatval($d['expected_total'] ?? 0), 2) . ') no coincide con el calculado ($' . number_format($q['total'], 2) . '). Actualice el ticket y cobre de nuevo.');
		}

		// Descuento: dentro del límite del cajero o con autorización de supervisor por al menos ese porcentaje
		$auth = null;
		if ($q['discount'] > 0)
		{
			if (mb_strlen(trim((string) ($d['discount']['reason'] ?? ''))) < 3)
			{
				throw new \Exception('Indique el motivo del descuento');
			}
			if ($q['discount_pct'] > $set['cashier_max_discount_pct'] + 0.001)
			{
				$auth = $this->TakeAuthorization(isset($d['discount']['auth_id']) ? intval($d['discount']['auth_id']) : null, 'discount', $userId);
				if (floatval($auth['pct']) + 0.001 < $q['discount_pct'])
				{
					throw new \Exception('La autorización cubre ' . $auth['pct'] . ' % y el descuento es ' . $q['discount_pct'] . ' %');
				}
			}
		}

		// Pagos: la suma aplicada debe ser exactamente el total; el cambio solo sale del efectivo
		$payments = [];
		$applied = 0.0;
		foreach ($d['payments'] ?? [] as $p)
		{
			$m = (string) ($p['method'] ?? '');
			$amt = self::Money(floatval($p['amount'] ?? 0));
			if (!isset(self::METHODS[$m]) || $amt <= 0)
			{
				throw new \Exception('Medio de pago no válido');
			}
			// Sin conexión: tarjeta o transferencia solo con el código de autorización obtenido fuera de línea
			if ($offlineAt !== null && $m !== 'cash' && trim((string) ($p['reference'] ?? '')) === '')
			{
				throw new \Exception('Sin conexión, la tarjeta o transferencia requiere el código de autorización del datáfono o comprobante');
			}
			if ($m === 'credit_note')
			{
				if ($offlineAt !== null)
				{
					throw new \Exception('Las notas de crédito solo se canjean con conexión');
				}
				$note = $this->Row("SELECT * FROM daily_credit_notes WHERE code = ? AND status = 'active' AND (expires_at IS NULL OR expires_at >= date('now', 'localtime'))", [trim((string) ($p['reference'] ?? ''))]);
				if (!$note || floatval($note['balance']) + 0.005 < $amt)
				{
					throw new \Exception('Nota de crédito no válida, vencida o con saldo insuficiente' . ($note ? ' (saldo $' . number_format(floatval($note['balance']), 2) . ')' : ''));
				}
			}
			$pointsNeeded = 0;
			if ($m === 'points')
			{
				if ($offlineAt !== null)
				{
					throw new \Exception('El canje de puntos requiere conexión en línea');
				}
				$custId = max(1, intval($d['customer_id'] ?? 1));
				$custRow = $this->Row('SELECT id, IFNULL(is_anonymous, 0) AS is_anonymous FROM customers WHERE id = ? AND active = 1 AND merged_into_id IS NULL', [$custId]);
				if (!$custRow || intval($custRow['is_anonymous']) === 1)
				{
					throw new \Exception('Seleccione un cliente registrado para canjear puntos en caja');
				}
				$crm = CustomerCrmService::GetInstance();
				$loy = $crm->Loyalty($custId);
				if (empty($loy['enabled']) || floatval($loy['settings']['point_value'] ?? 0) <= 0)
				{
					throw new \Exception('El programa de fidelización o valor por punto no está activo');
				}
				$ptVal = floatval($loy['settings']['point_value']);
				$pointsNeeded = max(1, intval(round($amt / $ptVal)));
				if (floatval($loy['balance_value'] ?? 0) + 0.005 < $amt || intval($loy['balance'] ?? 0) < $pointsNeeded)
				{
					throw new \Exception('Saldo de puntos insuficiente (disponible: ' . intval($loy['balance'] ?? 0) . ' pts = $' . number_format(floatval($loy['balance_value'] ?? 0), 2) . ')');
				}
			}
			$tendered = $m === 'cash' ? self::Money(floatval($p['tendered'] ?? $amt)) : null;
			if ($tendered !== null && $tendered + 0.001 < $amt)
			{
				throw new \Exception('El efectivo entregado es menor que el importe en efectivo');
			}
			$payments[] = ['method' => $m, 'amount' => $amt, 'tendered' => $tendered, 'change' => $tendered !== null ? self::Money($tendered - $amt) : 0.0,
				'points' => $pointsNeeded,
				'reference' => $m === 'cash' ? null : ($m === 'credit_note' ? trim((string) $p['reference']) : ($m === 'points' ? ($pointsNeeded . ' pts') : self::SafeReference($p['reference'] ?? null)))];
			$applied += $amt;
		}
		if (!$payments || abs(self::Money($applied) - $q['total']) > 0.005)
		{
			throw new \Exception('Los pagos ($' . number_format($applied, 2) . ') no cubren exactamente el total ($' . number_format($q['total'], 2) . ')');
		}
		$methods = array_unique(array_column($payments, 'method'));
		$notes = count($methods) > 1 ? 'Pago mixto: ' . implode(' | ', array_map(fn($p) => self::METHODS[$p['method']] . ' $' . number_format($p['amount'], 2, '.', ''), $payments)) : null;
		if ($offlineAt !== null)
		{
			$notes = trim(($notes ? $notes . ' · ' : '') . 'Registrada sin conexión el ' . $offlineAt);
		}
		if ($q['discount'] > 0)
		{
			$notes = trim(($notes ? $notes . ' · ' : '') . 'Descuento: ' . trim((string) $d['discount']['reason']));
		}

		$shiftId = intval($shift['id']);
		$custIdForSale = max(1, intval($d['customer_id'] ?? 1));
		$sale = SalesService::GetInstance()->CreateSale([
			'branch_id' => $branchId,
			'customer_id' => $custIdForSale,
			'payment_method' => count($methods) > 1 ? 'mixed' : $methods[0],
			'notes' => $notes,
			'items' => array_map(fn($l) => ['product_id' => $l['product_id'], 'amount' => $l['amount'], 'unit_price' => $l['unit_price']], $q['lines']),
			'ticket_discount_amount' => $q['discount'],
			'tax_amount' => $q['tax'],
			'client_ref' => $ref,
			'shift_id' => $shiftId,
			'sold_at' => $offlineAt,
			'event_uuid' => 'sale-confirmed-' . $ref,
			'_before_commit' => function ($saleRow, \PDO $db) use ($payments, $shiftId, $auth, $userId, $q, $custIdForSale) {
				$saleId = intval($saleRow->id);
				if (abs(floatval($saleRow->total_amount) - $q['total']) > 0.005)
				{
					throw new \Exception('El total registrado no coincide con el cobrado: venta revertida');
				}
				$ins = $db->prepare('INSERT INTO daily_pos_payments (sale_id, shift_id, method, amount, tendered, change_given, reference) VALUES (?, ?, ?, ?, ?, ?, ?)');
				foreach ($payments as $p)
				{
					$ins->execute([$saleId, $shiftId, $p['method'], $p['amount'], $p['tendered'], $p['change'], $p['reference']]);
					if ($p['method'] === 'credit_note')
					{
						// Canje atómico: solo descuenta si el saldo alcanza (dos ventas no gastan la misma nota)
						$u = $db->prepare("UPDATE daily_credit_notes SET balance = ROUND(balance - ?, 2), status = CASE WHEN balance - ? < 0.005 THEN 'used' ELSE status END
							WHERE code = ? AND status = 'active' AND balance + 0.005 >= CAST(? AS REAL)");
						$u->execute([$p['amount'], $p['amount'], $p['reference'], $p['amount']]);
						if ($u->rowCount() !== 1)
						{
							throw new \Exception('La nota de crédito ya no tiene saldo suficiente');
						}
						$db->prepare("INSERT INTO daily_credit_note_movements (note_id, kind, amount, sale_id, user_id) SELECT id, 'redeem', ?, ?, ? FROM daily_credit_notes WHERE code = ?")
							->execute([$p['amount'], $saleId, $userId, $p['reference']]);
					}
					if ($p['method'] === 'points' && $p['points'] > 0)
					{
						$balSt = $db->prepare('SELECT IFNULL(SUM(points), 0) FROM customer_loyalty_ledger WHERE customer_id = ?');
						$balSt->execute([$custIdForSale]);
						$bal = intval($balSt->fetchColumn());
						if ($bal < $p['points'])
						{
							throw new \Exception('Saldo de puntos insuficiente al confirmar el cobro');
						}
						$db->prepare("INSERT INTO customer_loyalty_ledger (customer_id, kind, points, sale_id, reason, user_id) VALUES (?, 'redeem', ?, ?, ?, ?)")
							->execute([$custIdForSale, -$p['points'], $saleId, 'Canje POS ' . ($saleRow->sale_number ?: ('#' . $saleId)), $userId]);
					}
				}
				if ($auth)
				{
					$this->UseAuthorization(intval($auth['id']), $saleId, $shiftId);
				}
				$db->prepare('INSERT INTO daily_pos_receipts (sale_id, copy_no, printed_by) VALUES (?, 0, ?)')->execute([$saleId, $userId]);
			}
		], $userId);
		$res = $this->SaleResult(intval($sale->id));
		return $res + ['replayed' => !empty($sale->replayed)];
	}

	/** Venta con líneas, pagos y recibos (lo que muestra el recibo y la consulta de transacciones) */
	public function SaleResult(int $saleId): array
	{
		$s = $this->Row('SELECT s.*, b.name AS branch_name, b.address AS branch_address, u.username AS cashier, c.name AS customer_name, sh.register_name
			FROM sales s LEFT JOIN branches b ON b.id = s.branch_id LEFT JOIN users u ON u.id = s.user_id LEFT JOIN customers c ON c.id = s.customer_id
			LEFT JOIN pos_cash_shifts sh ON sh.id = s.shift_id WHERE s.id = ?', [$saleId]);
		if (!$s)
		{
			throw new \Exception('Venta no encontrada');
		}
		$items = $this->Rows('SELECT si.product_id, p.name, p.sku, si.amount, si.unit_price, si.subtotal FROM sale_items si JOIN products p ON p.id = si.product_id WHERE si.sale_id = ? ORDER BY si.id', [$saleId]);
		$payments = $this->Rows('SELECT method, amount, tendered, change_given, reference FROM daily_pos_payments WHERE sale_id = ? ORDER BY id', [$saleId]);
		$prints = $this->Rows('SELECT r.copy_no, r.printed_at, u.username FROM daily_pos_receipts r LEFT JOIN users u ON u.id = r.printed_by WHERE r.sale_id = ? ORDER BY r.copy_no', [$saleId]);
		return ['sale' => [
			'id' => intval($s['id']), 'number' => $s['sale_number'], 'status' => $s['status'], 'datetime' => $s['created_timestamp'], 'branch' => $s['branch_name'],
			'branch_address' => $s['branch_address'], 'register' => $s['register_name'], 'cashier' => $s['cashier'], 'customer' => $s['customer_name'] ?: 'Público general',
			'subtotal' => floatval($s['subtotal']), 'discount' => floatval($s['discount_amount']), 'tax' => floatval($s['tax_amount']), 'total' => floatval($s['total_amount']),
			'payment_method' => $s['payment_method'], 'notes' => $s['notes'], 'shift_id' => $s['shift_id'] !== null ? intval($s['shift_id']) : null, 'client_ref' => $s['client_ref']
		], 'items' => $items, 'payments' => $payments, 'prints' => $prints];
	}

	/** Anulación con autorización: solo dentro del turno abierto (después, devolución); reintegra el stock */
	public function Void(int $saleId, string $reason, ?int $authId, int $userId): array
	{
		$s = $this->Row('SELECT s.*, sh.status AS shift_status FROM sales s LEFT JOIN pos_cash_shifts sh ON sh.id = s.shift_id WHERE s.id = ?', [$saleId]);
		if (!$s)
		{
			throw new \Exception('Venta no encontrada');
		}
		if ($s['status'] !== 'completed')
		{
			throw new \Exception('La venta ya está anulada');
		}
		if ($s['shift_id'] === null || $s['shift_status'] !== 'open')
		{
			throw new \Exception('Solo se anulan ventas del turno abierto; para ventas anteriores registre una devolución');
		}
		if (mb_strlen(trim($reason)) < 3)
		{
			throw new \Exception('Indique el motivo de la anulación');
		}
		$auth = $this->TakeAuthorization($authId, 'void', $userId, $saleId);
		$this->UseAuthorization(intval($auth['id']), $saleId, intval($s['shift_id']));
		SalesService::GetInstance()->CancelSale($saleId, $userId);
		// Revertir canje de puntos si la venta pagó con fidelización
		$redeems = $this->Rows("SELECT customer_id, ABS(points) AS pts FROM customer_loyalty_ledger WHERE sale_id = ? AND kind = 'redeem' AND points < 0", [$saleId]);
		foreach ($redeems as $rd)
		{
			$this->Db()->prepare("INSERT INTO customer_loyalty_ledger (customer_id, kind, points, reason, user_id) VALUES (?, 'adjust', ?, ?, ?)")
				->execute([intval($rd['customer_id']), intval($rd['pts']), 'Reverso anulación venta #' . $saleId, $userId]);
		}
		$this->Db()->prepare('UPDATE sales SET notes = TRIM(IFNULL(notes, \'\') || ?) WHERE id = ?')->execute([' · Anulada: ' . trim($reason) . ' (autorización #' . $auth['id'] . ')', $saleId]);
		return $this->SaleResult($saleId);
	}

	/** Reimpresión controlada: copias numeradas; pasadas las libres exige autorización */
	public function Reprint(int $saleId, ?int $authId, int $userId): array
	{
		$n = intval($this->Row('SELECT IFNULL(MAX(copy_no), 0) AS n FROM daily_pos_receipts WHERE sale_id = ?', [$saleId])['n']);
		if (!$this->Row('SELECT 1 FROM sales WHERE id = ?', [$saleId]))
		{
			throw new \Exception('Venta no encontrada');
		}
		$auth = null;
		if ($n >= $this->Settings()['free_reprints'])
		{
			$auth = $this->TakeAuthorization($authId, 'reprint', $userId, $saleId);
			$this->UseAuthorization(intval($auth['id']), $saleId);
		}
		$this->Db()->prepare('INSERT INTO daily_pos_receipts (sale_id, copy_no, printed_by, authorization_id) VALUES (?, ?, ?, ?)')->execute([$saleId, $n + 1, $userId, $auth['id'] ?? null]);
		return $this->SaleResult($saleId) + ['copy_no' => $n + 1];
	}

	/** Consulta de transacciones de la sucursal (filtros: fecha, texto, estado, turno) */
	public function Transactions(int $branchId, array $f): array
	{
		$w = ['s.branch_id = :b'];
		$p = [':b' => $branchId];
		$day = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($f['date'] ?? '')) ? $f['date'] : date('Y-m-d');
		$w[] = 'date(s.created_timestamp) = :d';
		$p[':d'] = $day;
		if (!empty($f['shift_id']))
		{
			$w[] = 's.shift_id = :sh';
			$p[':sh'] = intval($f['shift_id']);
		}
		if (in_array($f['status'] ?? '', ['completed', 'cancelled'], true))
		{
			$w[] = 's.status = :st';
			$p[':st'] = $f['status'];
		}
		if (trim((string) ($f['q'] ?? '')) !== '')
		{
			$w[] = '(s.sale_number LIKE :q OR c.name LIKE :q OR CAST(s.total_amount AS TEXT) LIKE :q)';
			$p[':q'] = '%' . trim($f['q']) . '%';
		}
		return $this->Rows('SELECT s.id, s.sale_number AS number, s.created_timestamp AS datetime, s.status, s.total_amount AS total, s.payment_method, s.shift_id,
				c.name AS customer, u.username AS cashier, sh.register_name AS register, (SELECT COUNT(*) FROM daily_pos_receipts r WHERE r.sale_id = s.id) AS prints
			FROM sales s LEFT JOIN customers c ON c.id = s.customer_id LEFT JOIN users u ON u.id = s.user_id LEFT JOIN pos_cash_shifts sh ON sh.id = s.shift_id
			WHERE ' . implode(' AND ', $w) . ' ORDER BY s.created_timestamp DESC, s.id DESC LIMIT 200', $p);
	}

	/** Recupera un ticket suspendido (se borra en la misma operación: no puede recuperarse dos veces) */
	public function Recall(int $holdId, int $branchId): array
	{
		$db = DatabaseService::GetInstance()->BeginWriteTransaction();
		try
		{
			$h = $this->Row('SELECT * FROM pos_held_sales WHERE id = ? AND branch_id = ?', [$holdId, $branchId]);
			if (!$h)
			{
				throw new \Exception('El ticket suspendido ya fue recuperado o no existe');
			}
			$db->prepare('DELETE FROM pos_held_sales WHERE id = ?')->execute([$holdId]);
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
		return ['id' => $holdId, 'label' => $h['label'], 'customer_id' => intval($h['customer_id']), 'items' => json_decode($h['items_json'], true) ?: [],
			'discount' => ['type' => $h['discount_type'], 'value' => floatval($h['discount_value']), 'reason' => $h['discount_reason']]];
	}

	/** Autorizaciones del turno (supervisión: quién aprobó qué) */
	public function Authorizations(int $branchId, int $limit = 50): array
	{
		return $this->Rows('SELECT a.*, r.username AS requested_name, ap.username AS approved_name, s.sale_number FROM daily_pos_authorizations a
			LEFT JOIN users r ON r.id = a.requested_by LEFT JOIN users ap ON ap.id = a.approved_by LEFT JOIN sales s ON s.id = a.sale_id
			WHERE a.branch_id = ? OR a.branch_id IS NULL ORDER BY a.id DESC LIMIT ' . intval($limit), [$branchId]);
	}
}
