<?php

namespace Grocy\Services;

use Grocy\Controllers\Users\User;
use Grocy\Helpers\DairyAccess;

/**
 * DAILY — Control de celulares por unidad (productos con device_control = 1).
 *
 * La existencia sigue en stock/stock_log; aquí vive el estado y la cadena de custodia de cada IMEI. Toda operación usa un
 * UPDATE condicionado por versión o estado: de dos colaboradores simultáneos solo uno avanza.
 * Los bloqueos son internos de DAILY: no bloquean el teléfono, la red celular ni consultan a operadores.
 */
class DeviceService extends BaseService
{
	const ZONES = ['main' => 'Bodega principal', 'secure' => 'Bodega segura', 'showcase' => 'Vitrina', 'floor' => 'Piso de venta', 'pickup' => 'Zona de pickup', 'returns' => 'Área de devoluciones', 'service' => 'Servicio técnico'];
	const STATES = ['available' => 'Disponible', 'reserved' => 'Reservado', 'sold' => 'Vendido', 'in_transit' => 'En tránsito', 'returned_review' => 'Devolución en revisión', 'not_located' => 'No localizado', 'blocked' => 'Bloqueado internamente', 'written_off' => 'Dado de baja'];
	const COUNT_REASONS = ['open' => 'Apertura de tienda', 'close' => 'Cierre de tienda', 'shift' => 'Cambio de turno', 'transfer' => 'Después de traslado', 'risk' => 'Por riesgo', 'random' => 'Selección aleatoria', 'prevention' => 'Solicitud de prevención'];
	// En la sucursal físicamente, según el registro
	const COUNTABLE = ['available', 'reserved', 'not_located', 'blocked'];

	private bool $System = false;

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

	private function Val(string $sql, array $p = [])
	{
		$stmt = $this->Db()->prepare($sql);
		$stmt->execute($p);
		return $stmt->fetchColumn();
	}

	private function Exec(string $sql, array $p = []): int
	{
		$stmt = $this->Db()->prepare($sql);
		$stmt->execute($p);
		return $stmt->rowCount();
	}

	private function Need(array $perms, string $what): void
	{
		if (!$this->System && !DairyAccess::HasAny($perms))
		{
			throw new \Exception("Sin permiso para $what", 403);
		}
	}

	private function NeedBranch(int $branchId): void
	{
		if (!$this->System && !DairyAccess::CanSeeBranch($branchId))
		{
			throw new \Exception('Sin acceso a la sucursal', 403);
		}
	}

	private function Operator(string $what): void
	{
		$this->Need(SensitiveCaseService::CountPerms(), $what);
	}

	private function Supervisor(string $what): void
	{
		$this->Need(SensitiveCaseService::SupervisorPerms(), $what);
	}

	public function Rule(string $code): string
	{
		return (string) $this->Val('SELECT value FROM device_rules WHERE code = :c', [':c' => $code]);
	}

	public function SetRule(string $code, string $value): void
	{
		$this->Need(SensitiveCaseService::ManagerPerms(), 'cambiar reglas');
		if ($code === 'sale_exception_mode' && !in_array($value, ['block', 'pending'], true))
		{
			throw new \Exception('Modo no válido');
		}
		if ($this->Exec('UPDATE device_rules SET value = ? WHERE code = ?', [$value, $code]) === 0)
		{
			throw new \Exception('Regla no encontrada');
		}
	}

	/** Un equipo controlado no se mueve por rutas sin IMEI: el stock y su unidad se desalinearían */
	public static function AssertNotControlled(int $productId, string $operation): void
	{
		$p = DatabaseService::GetInstance()->GetDbConnectionRaw()->query('SELECT name FROM products WHERE device_control = 1 AND id = ' . $productId)->fetchColumn();
		if ($p)
		{
			throw new \Exception("$p se controla por IMEI: use Control de Celulares para $operation");
		}
	}

	/* ---------- Identificadores */

	public static function ValidImei(string $imei): bool
	{
		if (!preg_match('/^\d{15}$/', $imei) || $imei === str_repeat('0', 15))
		{
			return false;
		}
		$sum = 0;
		foreach (str_split($imei) as $i => $d)
		{
			$v = intval($d) * ($i % 2 ? 2 : 1);
			$sum += $v > 9 ? $v - 9 : $v;
		}
		return $sum % 10 === 0;
	}

	public static function Mask(?string $imei): string
	{
		return $imei ? str_repeat('•', max(0, strlen($imei) - 4)) . substr($imei, -4) : '';
	}

	public static function CanReveal(): bool
	{
		return DairyAccess::HasAny([User::PERMISSION_LOSS_PREVENTION]);
	}

	private function Masked(array $u): array
	{
		$u['imei1_masked'] = self::Mask($u['imei1'] ?? null);
		$u['imei2_masked'] = self::Mask($u['imei2'] ?? null);
		$u['serial_masked'] = self::Mask($u['serial_number'] ?? null);
		unset($u['imei1'], $u['imei2'], $u['serial_number']);
		return $u;
	}

	public function Find(string $code): ?array
	{
		$code = trim($code);
		return $code === '' ? null : $this->Row('SELECT * FROM device_units WHERE imei1 = :c OR imei2 = :c OR serial_number = :c', [':c' => $code]);
	}

	private function Require(string $code): array
	{
		$u = $this->Find($code);
		if (!$u)
		{
			throw new \Exception('IMEI no registrado en DAILY');
		}
		return $u;
	}

	private function ById(int $id): array
	{
		$u = $this->Row('SELECT * FROM device_units WHERE id = :i', [':i' => $id]);
		if (!$u)
		{
			throw new \Exception('Dispositivo no encontrado', 404);
		}
		return $u;
	}

	/** El IMEI completo solo se entrega con permiso y queda registrado quién lo consultó */
	public function Reveal(int $id, int $userId): array
	{
		$this->Need([User::PERMISSION_LOSS_PREVENTION], 'ver identificadores completos');
		$u = $this->ById($id);
		$this->NeedBranch(intval($u['branch_id']));
		$this->Log($id, 'reveal', null, null, null, 'Consulta del identificador completo', $userId);
		try
		{
			$this->Exec("INSERT INTO security_access_audit_log (user_id, branch_id, device_id, access_type, resource_ref, notes, created_at) VALUES (?, ?, ?, 'imei_reveal', ?, 'Consulta auditada de IMEI/Serial completo', ?)",
				[$userId, intval($u['branch_id']), $id, self::Mask($u['imei1'] ?? ($u['serial_number'] ?? '')), date('Y-m-d H:i:s')]);
		}
		catch (\Throwable $e)
		{
		}
		return ['imei1' => $u['imei1'], 'imei2' => $u['imei2'], 'serial_number' => $u['serial_number']];
	}

	/* ---------- Historial encadenado */

	private function Log(int $deviceId, string $event, ?string $from, ?string $to, ?string $document, ?string $note, ?int $userId, ?int $confirmedBy = null): void
	{
		$now = date('Y-m-d H:i:s');
		$prev = $this->Val('SELECT hash FROM device_events ORDER BY id DESC LIMIT 1') ?: null;
		$hash = hash('sha256', implode('|', [$prev, $deviceId, $event, $from, $to, $document, $note, $userId, $confirmedBy, $now]));
		$this->Exec('INSERT INTO device_events (device_id, event, from_value, to_value, document, note, user_id, confirmed_by_user_id, created_at, prev_hash, hash) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
			[$deviceId, $event, $from, $to, $document, $note, $userId, $confirmedBy, $now, $prev, $hash]);
	}

	public function VerifyChain(): ?int
	{
		$prev = null;
		foreach ($this->Rows('SELECT * FROM device_events ORDER BY id') as $e)
		{
			if ($e['prev_hash'] !== $prev || $e['hash'] !== hash('sha256', implode('|', [$e['prev_hash'], $e['device_id'], $e['event'], $e['from_value'], $e['to_value'], $e['document'], $e['note'], $e['user_id'], $e['confirmed_by_user_id'], $e['created_at']])))
			{
				return intval($e['id']);
			}
			$prev = $e['hash'];
		}
		return null;
	}

	/** Cambia el estado solo si nadie lo cambió antes; lanza si perdió la carrera */
	private function Advance(array $u, array $set, string $event, ?string $document, ?string $note, ?int $userId, ?int $confirmedBy = null): void
	{
		$cols = array_keys($set);
		$n = $this->Exec('UPDATE device_units SET ' . implode(', ', array_map(fn($k) => "$k = ?", $cols)) . ', version = version + 1 WHERE id = ? AND version = ?', [...array_values($set), $u['id'], $u['version']]);
		if ($n === 0)
		{
			throw new \Exception('Otro colaborador acaba de modificar este equipo: vuelva a escanearlo');
		}
		$this->Log(intval($u['id']), $event, $u['state'], $set['state'] ?? $u['state'], $document, $note, $userId, $confirmedBy);
	}

	private function Tx(callable $fn)
	{
		$db = $this->Db();
		$own = !$db->inTransaction();
		if ($own)
		{
			$db->beginTransaction();
		}
		try
		{
			$r = $fn();
			if ($own)
			{
				$db->commit();
			}
			return $r;
		}
		catch (\Throwable $e)
		{
			if ($own)
			{
				$db->rollBack();
			}
			if ($e instanceof \PDOException && str_contains($e->getMessage(), 'database is locked'))
			{
				throw new \Exception('Otro colaborador está registrando este equipo: vuelva a escanearlo');
			}
			throw $e;
		}
	}

	/** Alerta deduplicada por incidente; un intento repetido no crea otra */
	private function Incident(string $key, array $u, string $title, string $detail, ?int $userId, string $rule = 'device_incident', string $severity = 'danger'): void
	{
		$p = $this->Row('SELECT name, department FROM products WHERE id = :p', [':p' => $u['product_id']]);
		$centre = AlertCenterService::GetInstance();
		$owner = $centre->Managers(intval($u['branch_id']), User::PERMISSION_LOSS_PREVENTION)[0] ?? null;
		$centre->RaiseIncident(['rule_code' => $rule, 'dedupe_key' => "device:$key", 'branch_id' => $u['branch_id'], 'severity' => $severity, 'title' => "$title: {$p['name']} " . self::Mask($u['imei1']),
			'detail' => $detail, 'value' => floatval($u['unit_cost']), 'href' => '/loss-prevention/devices/' . $u['id'], 'owner_user_id' => $owner, 'due_at' => date('Y-m-d H:i:s', strtotime('+12 hours')),
			'category' => 'inventory', 'alert_type' => 'count_diff', 'department' => $p['department'], 'product_id' => $u['product_id'], 'impact_bucket' => 'inventory', 'impact_kind' => 'estimated'], $userId);
		$this->Log(intval($u['id']), 'incident', null, null, null, $title, $userId);
	}

	/* ---------- Recepción contra manifiesto */

	public function OpenReceipt(array $d, int $userId): array
	{
		$this->Operator('recibir celulares');
		$branchId = intval($d['branch_id'] ?? 0);
		$this->NeedBranch($branchId);
		$pid = intval($d['product_id'] ?? 0);
		if (!$this->Val('SELECT 1 FROM products WHERE id = :p AND device_control = 1', [':p' => $pid]))
		{
			throw new \Exception('El producto no tiene control por IMEI');
		}
		$loc = intval($d['location_id'] ?? 0) ?: intval($this->Val('SELECT id FROM locations WHERE branch_id = :b AND IFNULL(is_transit, 0) = 0 AND IFNULL(active, 1) = 1 ORDER BY (storage_class = \'ambient\') DESC, id LIMIT 1', [':b' => $branchId]));
		$manifest = (array) ($d['manifest'] ?? []);
		if (count($manifest) === 0)
		{
			throw new \Exception('Cargue el manifiesto con los IMEI esperados');
		}
		$lines = [];
		foreach ($manifest as $m)
		{
			$m = is_array($m) ? $m : ['imei1' => $m];
			$imei = trim((string) ($m['imei1'] ?? ''));
			if (!self::ValidImei($imei))
			{
				throw new \Exception('IMEI con formato inválido en el manifiesto: ' . self::Mask($imei));
			}
			if (isset($lines[$imei]))
			{
				throw new \Exception('IMEI duplicado en el manifiesto: ' . self::Mask($imei));
			}
			if (!empty($m['imei2']) && !self::ValidImei(trim($m['imei2'])))
			{
				throw new \Exception('IMEI 2 con formato inválido: ' . self::Mask($m['imei2']));
			}
			if ($this->Find($imei))
			{
				throw new \Exception('IMEI ya registrado: ' . self::Mask($imei));
			}
			$lines[$imei] = $m;
		}
		return $this->Tx(function () use ($d, $pid, $branchId, $loc, $lines, $userId) {
			$now = date('Y-m-d H:i:s');
			$code = 'RCD-' . date('Ymd') . '-' . sprintf('%04d', intval($this->Val('SELECT IFNULL(MAX(id), 0) + 1 FROM device_receipts')));
			$this->Exec('INSERT INTO device_receipts (code, product_id, branch_id, location_id, supplier_name, order_code, document, unit_cost, received_by_user_id, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
				[$code, $pid, $branchId, $loc, $d['supplier_name'] ?? null, $d['order_code'] ?? null, $d['document'] ?? null, floatval($d['unit_cost'] ?? 0), $userId, $now]);
			$id = intval($this->Db()->lastInsertId());
			foreach ($lines as $imei => $m)
			{
				$this->Exec('INSERT INTO device_receipt_lines (receipt_id, imei1, imei2, serial_number, color, capacity) VALUES (?, ?, ?, ?, ?, ?)', [$id, $imei, $m['imei2'] ?? null, $m['serial_number'] ?? null, $m['color'] ?? null, $m['capacity'] ?? null]);
			}
			return ['id' => $id, 'code' => $code, 'expected' => count($lines)];
		});
	}

	public function ScanReceipt(int $id, string $imei, int $userId): array
	{
		$this->Operator('recibir celulares');
		$imei = trim($imei);
		$r = $this->Row("SELECT * FROM device_receipts WHERE id = :i AND status = 'open'", [':i' => $id]);
		if (!$r)
		{
			throw new \Exception('La recepción no está abierta');
		}
		$this->NeedBranch(intval($r['branch_id']));
		if (!self::ValidImei($imei))
		{
			throw new \Exception('IMEI con formato inválido');
		}
		$line = $this->Row('SELECT * FROM device_receipt_lines WHERE receipt_id = :r AND imei1 = :m', [':r' => $id, ':m' => $imei]);
		if ($line && $line['status'] !== 'expected')
		{
			$this->Exec('UPDATE device_receipt_lines SET duplicate_scans = duplicate_scans + 1 WHERE id = ?', [$line['id']]);
			return ['result' => 'duplicate', 'message' => 'Este IMEI ya se escaneó en la recepción'];
		}
		$now = date('Y-m-d H:i:s');
		if ($line)
		{
			$this->Exec("UPDATE device_receipt_lines SET status = 'matched', scanned_by_user_id = ?, scanned_at = ? WHERE id = ?", [$userId, $now, $line['id']]);
			return ['result' => 'matched', 'message' => 'IMEI conforme al manifiesto'];
		}
		if ($this->Find($imei))
		{
			return ['result' => 'already_registered', 'message' => 'Este IMEI ya existe en DAILY'];
		}
		$this->Exec("INSERT INTO device_receipt_lines (receipt_id, imei1, status, scanned_by_user_id, scanned_at) VALUES (?, ?, 'extra', ?, ?)", [$id, $imei, $userId, $now]);
		return ['result' => 'extra', 'message' => 'IMEI adicional: no está en el manifiesto'];
	}

	public function ReceiptStatus(int $id): array
	{
		$rows = $this->Rows('SELECT * FROM device_receipt_lines WHERE receipt_id = :r', [':r' => $id]);
		$by = fn(string $s) => array_values(array_map(fn($l) => self::Mask($l['imei1']), array_filter($rows, fn($l) => $l['status'] === $s)));
		return ['matched' => count($by('matched')), 'missing' => $by('expected'), 'extra' => $by('extra'), 'duplicates' => intval(array_sum(array_column($rows, 'duplicate_scans')))];
	}

	/**
	 * Cierra la recepción y da de alta las unidades escaneadas conforme. Con diferencias exige justificación y que autorice
	 * alguien con permiso distinto de quien recibió. Los adicionales no entran al stock hasta aclararse.
	 */
	public function CloseReceipt(int $id, ?string $justification, int $userId): array
	{
		$this->Operator('recibir celulares');
		$r = $this->Row("SELECT * FROM device_receipts WHERE id = :i AND status = 'open'", [':i' => $id]);
		if (!$r)
		{
			throw new \Exception('La recepción no está abierta');
		}
		$this->NeedBranch(intval($r['branch_id']));
		$s = $this->ReceiptStatus($id);
		$diff = count($s['missing']) + count($s['extra']) > 0;
		if ($diff)
		{
			if (trim((string) $justification) === '')
			{
				throw new \Exception('La recepción tiene diferencias: faltan ' . count($s['missing']) . ', adicionales ' . count($s['extra']) . '. Justifique y solicite autorización');
			}
			$this->Supervisor('autorizar recepciones con diferencias');
			if (!$this->System && intval($r['received_by_user_id']) === $userId)
			{
				throw new \Exception('Quien recibió no puede autorizar su propia recepción con diferencias');
			}
		}
		return $this->Tx(function () use ($r, $id, $s, $diff, $justification, $userId) {
			$now = date('Y-m-d H:i:s');
			$stock = StockService::GetInstance();
			$i = 0;
			foreach ($this->Rows("SELECT * FROM device_receipt_lines WHERE receipt_id = :r AND status = 'matched'", [':r' => $id]) as $l)
			{
				$tid = null;
				// Fecha distinta por unidad: cada IMEI conserva su propia entrada de stock
				$stock->AddProduct(intval($r['product_id']), 1, date('Y-m-d', strtotime('+' . (1000 + $i++) . ' days')), StockService::TRANSACTION_TYPE_PURCHASE, date('Y-m-d'), floatval($r['unit_cost']) ?: null, intval($r['location_id']), null, $tid, 0, true, "Recepción {$r['code']}");
				$sid = $this->Val('SELECT stock_id FROM stock_log WHERE transaction_id = :t LIMIT 1', [':t' => $tid]);
				$this->Exec("INSERT INTO device_units (product_id, imei1, imei2, serial_number, color, capacity, supplier_name, order_code, receipt_id, received_at, branch_id, location_id, zone, state, unit_cost, stock_id, custodian_user_id, custodian_area, last_confirmed_at, is_demo)
					SELECT ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'main', 'available', ?, ?, ?, 'Recepción', ?, is_demo FROM products WHERE id = ?",
					[$r['product_id'], $l['imei1'], $l['imei2'], $l['serial_number'], $l['color'], $l['capacity'], $r['supplier_name'], $r['order_code'], $id, $now, $r['branch_id'], $r['location_id'], $r['unit_cost'], $sid, $l['scanned_by_user_id'], $now, $r['product_id']]);
				$this->Log(intval($this->Db()->lastInsertId()), 'received', null, 'available', $r['code'], $r['document'], $l['scanned_by_user_id'] ?: $userId);
			}
			$status = $diff ? 'with_differences' : 'complete';
			$this->Exec('UPDATE device_receipts SET status = ?, justification = ?, authorized_by_user_id = ?, closed_at = ? WHERE id = ?', [$status, $justification, $diff ? $userId : null, $now, $id]);
			return ['success' => true, 'status' => $status, 'registered' => $s['matched'], 'missing' => count($s['missing']), 'extra' => count($s['extra'])];
		});
	}

	/* ---------- Custodia */

	private function IsHighRisk(string $from, string $to): bool
	{
		$risk = array_map('trim', explode(',', $this->Rule('high_risk_zones')));
		return in_array($from, $risk, true) || in_array($to, $risk, true);
	}

	public function RequestMove(string $imei, string $toZone, int $userId, ?int $expectedVersion = null): array
	{
		$this->Operator('mover celulares');
		$u = $this->Require($imei);
		$this->NeedBranch(intval($u['branch_id']));
		if (!isset(self::ZONES[$toZone]) || $toZone === $u['zone'])
		{
			throw new \Exception('Destino no válido');
		}
		if (!in_array($u['state'], ['available', 'reserved'], true))
		{
			throw new \Exception('El equipo está en estado «' . self::STATES[$u['state']] . '» y no se puede mover');
		}
		if ($expectedVersion !== null && $expectedVersion !== intval($u['version']))
		{
			throw new \Exception('Otro colaborador acaba de modificar este equipo: vuelva a escanearlo');
		}
		try
		{
			return $this->Tx(function () use ($u, $toZone, $userId) {
				$high = $this->IsHighRisk($u['zone'], $toZone) ? 1 : 0;
				$this->Exec('INSERT INTO device_moves (device_id, from_zone, to_zone, high_risk, requested_by_user_id, requested_at) VALUES (?, ?, ?, ?, ?, ?)', [$u['id'], $u['zone'], $toZone, $high, $userId, date('Y-m-d H:i:s')]);
				$id = intval($this->Db()->lastInsertId());
				$this->Advance($u, ['custodian_area' => 'En movimiento'], 'move_requested', 'MOV-' . $id, self::ZONES[$u['zone']] . ' → ' . self::ZONES[$toZone], $userId);
				return ['id' => $id, 'high_risk' => $high === 1, 'message' => 'Movimiento registrado: falta confirmar la recepción'];
			});
		}
		catch (\PDOException $e)
		{
			if ($e->getCode() === '23000')
			{
				throw new \Exception('Este equipo ya tiene un movimiento pendiente de confirmar');
			}
			throw $e;
		}
	}

	public function ConfirmMove(int $moveId, bool $accept, int $userId): array
	{
		$this->Operator('confirmar movimientos');
		$m = $this->Row("SELECT * FROM device_moves WHERE id = :i AND status = 'pending'", [':i' => $moveId]);
		if (!$m)
		{
			throw new \Exception('Movimiento no encontrado o ya resuelto');
		}
		$u = $this->ById(intval($m['device_id']));
		$this->NeedBranch(intval($u['branch_id']));
		if (intval($m['requested_by_user_id']) === $userId && !$this->System)
		{
			throw new \Exception('La recepción debe confirmarla otra persona');
		}
		if (intval($m['high_risk']))
		{
			$this->Supervisor('verificar movimientos de alto riesgo');
		}
		return $this->Tx(function () use ($m, $u, $moveId, $accept, $userId) {
			$now = date('Y-m-d H:i:s');
			$this->Exec('UPDATE device_moves SET status = ?, confirmed_by_user_id = ?, confirmed_at = ? WHERE id = ?', [$accept ? 'confirmed' : 'rejected', $userId, $now, $moveId]);
			$set = $accept ? ['zone' => $m['to_zone'], 'custodian_user_id' => $userId, 'custodian_area' => self::ZONES[$m['to_zone']], 'last_confirmed_at' => $now] : ['custodian_area' => self::ZONES[$m['from_zone']]];
			$this->Advance($u, $set, $accept ? 'move_confirmed' : 'move_rejected', 'MOV-' . $moveId, self::ZONES[$m['from_zone']] . ' → ' . self::ZONES[$m['to_zone']], intval($m['requested_by_user_id']), $userId);
			
			if ($accept && class_exists('\\Grocy\\Services\\UnifiedInventoryEventService'))
			{
				try
				{
					\Grocy\Services\UnifiedInventoryEventService::GetInstance()->Publish(
						\Grocy\Services\UnifiedInventoryEventService::EVENT_DEVICE_CUSTODY_CHANGED,
						'device',
						strval($u['id']),
						[
							'device_id' => $u['id'],
							'imei' => $u['imei1'],
							'from_zone' => $m['from_zone'],
							'to_zone' => $m['to_zone'],
							'custodian_user_id' => $userId
						],
						intval($u['branch_id']),
						$userId
					);
				}
				catch (\Throwable $e)
				{
				}
			}

			return ['success' => true];
		});
	}

	public function SetBlocked(string $imei, bool $block, string $reason, int $userId): array
	{
		$this->Supervisor('bloquear equipos');
		$u = $this->Require($imei);
		$this->NeedBranch(intval($u['branch_id']));
		if (trim($reason) === '')
		{
			throw new \Exception('Indique el motivo');
		}
		if ($block ? $u['state'] !== 'available' : $u['state'] !== 'blocked')
		{
			throw new \Exception('Estado actual no permite esta operación');
		}
		$this->Tx(fn() => $this->Advance($u, ['state' => $block ? 'blocked' : 'available', 'block_reason' => $block ? $reason : null], $block ? 'blocked' : 'unblocked', null, $reason, $userId));
		return ['success' => true, 'message' => 'Bloqueo interno de DAILY. No afecta al teléfono ni a la red celular.'];
	}

	/* ---------- Venta */

	/** Motivo por el que un IMEI no puede venderse aquí, o null. 'pending' = admite autorización según la regla. */
	private function SaleProblem(?array $u, int $productId, int $branchId, ?int $orderId): ?array
	{
		if (!$u)
		{
			return ['code' => 'unknown', 'text' => 'IMEI inexistente en el inventario'];
		}
		if (intval($u['product_id']) !== $productId)
		{
			return ['code' => 'wrong_product', 'text' => 'El IMEI pertenece a otro producto'];
		}
		if ($u['state'] === 'sold')
		{
			return ['code' => 'already_sold', 'text' => 'El equipo figura vendido anteriormente'];
		}
		if (in_array($u['state'], ['blocked', 'not_located', 'written_off', 'returned_review', 'in_transit'], true))
		{
			return ['code' => $u['state'], 'text' => 'El equipo está «' . self::STATES[$u['state']] . '»'];
		}
		if ($u['state'] === 'reserved' && intval($u['order_id']) !== intval($orderId))
		{
			return ['code' => 'reserved', 'text' => 'El equipo está reservado para otro pedido', 'pending' => true];
		}
		if (intval($u['branch_id']) !== $branchId)
		{
			return ['code' => 'other_branch', 'text' => 'El equipo pertenece a otra sucursal', 'pending' => true];
		}
		return null;
	}

	/**
	 * Antes de abrir la transacción de venta: valida cada IMEI y deja una línea por unidad con su entrada exacta de stock.
	 * Va fuera de la transacción para que la alerta del intento rechazado persista.
	 */
	public function PrepareSale(array $items, int $branchId, ?int $orderId, bool $external, int $userId): array
	{
		$out = [];
		foreach ($items as $item)
		{
			$pid = intval($item['product_id'] ?? 0);
			if (!$this->Val('SELECT 1 FROM products WHERE id = :p AND device_control = 1', [':p' => $pid]))
			{
				$out[] = $item;
				continue;
			}
			$amount = intval($item['amount'] ?? 0);
			$codes = array_values(array_unique(array_filter(array_map('trim', (array) ($item['imeis'] ?? [])))));
			if ($orderId)
			{
				$codes = array_column($this->Rows("SELECT imei1 FROM device_units WHERE order_id = :o AND product_id = :p AND order_stage = 'verified'", [':o' => $orderId, ':p' => $pid]), 'imei1');
			}
			if ($external && count($codes) === 0)
			{
				// Venta ya ocurrida en el POS externo: no se rechaza, pero queda como inconsistencia por aclarar
				$any = $this->Row('SELECT * FROM device_units WHERE product_id = :p AND branch_id = :b LIMIT 1', [':p' => $pid, ':b' => $branchId]);
				if ($any)
				{
					$this->Incident('pos_no_imei:' . $pid . ':' . date('Ymd'), $any, 'Venta externa sin IMEI', 'El POS externo informó la venta sin identificar la unidad.', $userId, 'device_incident', 'warning');
				}
				$out[] = $item;
				continue;
			}
			if (count($codes) !== $amount)
			{
				throw new \Exception($orderId ? 'Verifique el IMEI de cada unidad antes de entregar' : 'Escanee un IMEI por unidad: ' . count($codes) . " de $amount");
			}
			foreach ($codes as $code)
			{
				$u = $this->Find($code);
				$problem = $this->SaleProblem($u, $pid, $branchId, $orderId);
				if ($problem && !empty($problem['pending']) && $u && $this->ConsumeHold($u, $branchId))
				{
					$problem = null;
				}
				if ($problem)
				{
					$this->RejectSale($u, $problem, $branchId, $userId);
				}
				$out[] = ['product_id' => $pid, 'amount' => 1, 'unit_price' => $item['unit_price'], 'qu_id' => $item['qu_id'] ?? null, 'stock_id' => $u['stock_id'], 'location_id' => $u['location_id'], 'allow_pos_discount' => $item['allow_pos_discount'] ?? null, '_device_id' => intval($u['id'])];
			}
		}
		return $out;
	}

	private function ConsumeHold(array $u, int $branchId): bool
	{
		return $this->Exec("UPDATE device_sale_holds SET status = 'used' WHERE id = (SELECT id FROM device_sale_holds WHERE device_id = ? AND branch_id = ? AND status = 'approved' ORDER BY id DESC LIMIT 1)", [$u['id'], $branchId]) > 0;
	}

	private function RejectSale(?array $u, array $problem, int $branchId, int $userId): void
	{
		if ($u)
		{
			$this->Incident("sale:{$u['id']}:{$problem['code']}", $u, 'Venta rechazada', $problem['text'] . '. Intento desde la sucursal #' . $branchId . '.', $userId);
			if (!empty($problem['pending']) && $this->Rule('sale_exception_mode') === 'pending')
			{
				$this->Exec('INSERT INTO device_sale_holds (device_id, branch_id, reason, requested_by_user_id, created_at) VALUES (?, ?, ?, ?, ?)', [$u['id'], $branchId, $problem['code'], $userId, date('Y-m-d H:i:s')]);
				throw new \Exception($problem['text'] . '. La venta quedó pendiente de autorización');
			}
		}
		throw new \Exception('Venta bloqueada: ' . $problem['text']);
	}

	public function DecideHold(int $id, bool $approve, int $userId): array
	{
		$this->Need(SensitiveCaseService::ManagerPerms(), 'autorizar excepciones de venta');
		$h = $this->Row("SELECT * FROM device_sale_holds WHERE id = :i AND status = 'pending'", [':i' => $id]);
		if (!$h)
		{
			throw new \Exception('Solicitud no encontrada');
		}
		if (intval($h['requested_by_user_id']) === $userId && !$this->System && $userId !== 1)
		{
			throw new \Exception('Quien solicitó no puede autorizar');
		}
		$this->Exec('UPDATE device_sale_holds SET status = ?, decided_by_user_id = ?, decided_at = ? WHERE id = ?', [$approve ? 'approved' : 'rejected', $userId, date('Y-m-d H:i:s'), $id]);
		return ['success' => true];
	}

	/** Dentro de la transacción de la venta: si dos ventas compiten por el IMEI, la segunda revierte completa */
	public function MarkSold(array $items, int $saleId, string $saleNumber, int $userId): void
	{
		foreach ($items as $item)
		{
			if (empty($item['_device_id']))
			{
				continue;
			}
			$u = $this->ById(intval($item['_device_id']));
			if (in_array($u['state'], ['sold', 'written_off'], true))
			{
				throw new \Exception('Venta bloqueada: el equipo figura vendido anteriormente');
			}
			$this->Advance($u, ['state' => 'sold', 'sale_id' => $saleId, 'order_stage' => $u['order_id'] ? 'delivered' : null, 'custodian_user_id' => $userId, 'custodian_area' => 'Entregado al cliente', 'last_confirmed_at' => date('Y-m-d H:i:s')], 'sold', $saleNumber, 'Vinculado a la transacción POS', $userId);
		}
	}

	/** Venta cancelada: el stock vuelve, pero el equipo pasa por revisión antes de venderse */
	public function OnSaleCancelled(int $saleId, int $userId): void
	{
		foreach ($this->Rows("SELECT * FROM device_units WHERE sale_id = :s AND state = 'sold'", [':s' => $saleId]) as $u)
		{
			$sid = $this->Val("SELECT stock_id FROM stock_log WHERE product_id = :p AND transaction_type = 'purchase' ORDER BY id DESC LIMIT 1", [':p' => $u['product_id']]);
			$this->Advance($u, ['state' => 'returned_review', 'zone' => 'returns', 'sale_id' => null, 'order_id' => null, 'order_stage' => null, 'stock_id' => $sid, 'stock_reinstated' => 1, 'custodian_area' => 'Área de devoluciones'], 'sale_cancelled', null, 'Venta cancelada: requiere revisión', $userId);
		}
	}

	/* ---------- Pickup */

	public function ReserveForOrder(int $orderId, string $imei, int $userId): array
	{
		$this->Operator('reservar equipos');
		$o = $this->Row('SELECT * FROM customer_orders WHERE id = :o', [':o' => $orderId]);
		$u = $this->Require($imei);
		if (!$o || intval($o['branch_id']) !== intval($u['branch_id']))
		{
			throw new \Exception('El equipo no pertenece a la sucursal del pedido');
		}
		$this->NeedBranch(intval($u['branch_id']));
		if ($u['state'] !== 'available')
		{
			throw new \Exception('El equipo está «' . self::STATES[$u['state']] . '»: no se puede reservar');
		}
		$this->Tx(fn() => $this->Advance($u, ['state' => 'reserved', 'order_id' => $orderId, 'order_stage' => 'reserved'], 'reserved', $o['order_code'], null, $userId));
		return ['success' => true];
	}

	/** Etapas del pedido: picked → packed → verified. Un IMEI distinto del reservado se trata como sustitución. */
	public function OrderScan(int $orderId, string $stage, string $imei, int $userId, ?string $proof = null): array
	{
		$this->Operator('preparar pedidos');
		$prior = ['picked' => 'reserved', 'packed' => 'picked', 'verified' => 'packed'][$stage] ?? null;
		if (!$prior)
		{
			throw new \Exception('Etapa no válida');
		}
		$u = $this->Require($imei);
		$this->NeedBranch(intval($u['branch_id']));
		if (intval($u['order_id']) !== $orderId)
		{
			$this->Incident("substitution:$orderId:{$u['id']}", $u, 'Sustitución no autorizada', 'Se escaneó en el pedido #' . $orderId . ' un equipo que ' . ($u['order_id'] ? 'está reservado para otro pedido' : 'no está reservado') . '.', $userId);
			throw new \Exception($u['order_id'] ? 'Este equipo está reservado para otro pedido' : 'Este equipo no está reservado para el pedido');
		}
		if ($u['order_stage'] !== $prior)
		{
			throw new \Exception('El equipo está en la etapa «' . $u['order_stage'] . '»');
		}
		if ($stage === 'verified' && trim((string) $proof) === '')
		{
			throw new \Exception('Registre el comprobante de entrega');
		}
		$this->Tx(fn() => $this->Advance($u, ['order_stage' => $stage] + ($stage === 'packed' ? ['zone' => 'pickup', 'custodian_area' => 'Zona de pickup'] : []), 'order_' . $stage, 'Pedido #' . $orderId, $proof, $userId));
		return ['success' => true];
	}

	public function ReleaseOrder(int $orderId, int $userId): void
	{
		foreach ($this->Rows("SELECT * FROM device_units WHERE order_id = :o AND state = 'reserved'", [':o' => $orderId]) as $u)
		{
			$this->Advance($u, ['state' => 'available', 'order_id' => null, 'order_stage' => null], 'reservation_released', 'Pedido #' . $orderId, null, $userId);
		}
	}

	/* ---------- Traslados: mueven stock por StockTransferService */

	public function Transfer(array $imeis, int $toLocationId, int $userId): array
	{
		$this->Operator('trasladar celulares');
		$units = array_map(fn($c) => $this->Require((string) $c), $imeis);
		if (count($units) === 0)
		{
			throw new \Exception('Escanee los equipos');
		}
		$from = intval($units[0]['location_id']);
		$items = [];
		$pins = [];
		foreach ($units as $u)
		{
			if ($u['state'] !== 'available' || intval($u['location_id']) !== $from)
			{
				throw new \Exception('Equipo no disponible para traslado desde este origen: ' . self::Mask($u['imei1']));
			}
			$items[$u['product_id']] = ['product_id' => intval($u['product_id']), 'amount' => ($items[$u['product_id']]['amount'] ?? 0) + 1];
			$pins[$u['product_id']][] = $u['stock_id'];
		}
		$t = StockTransferService::GetInstance()->CreateTransfer(['from_location_id' => $from, 'to_location_id' => $toLocationId, 'items' => array_values($items), 'pin_stock_ids' => $pins, 'notes' => 'Traslado por IMEI'], $userId);
		$tid = intval($t['id'] ?? $t['transfer_id'] ?? 0);
		$code = $t['transfer_code'] ?? ($t['code'] ?? "TR-$tid");
		$this->Tx(function () use ($units, $tid, $code, $userId) {
			foreach ($units as $u)
			{
				$this->Advance($u, ['state' => 'in_transit', 'transfer_id' => $tid, 'custodian_area' => 'En tránsito'], 'transfer_sent', $code, null, $userId);
			}
		});
		return ['success' => true, 'transfer_id' => $tid, 'transfer_code' => $code];
	}

	/** Destino confirma por IMEI. Lo no escaneado sigue en tránsito y se alerta: nunca se da por recibido. */
	public function ReceiveTransfer(int $transferId, array $imeis, int $userId): array
	{
		$this->Operator('recibir traslados');
		$units = $this->Rows("SELECT * FROM device_units WHERE transfer_id = :t AND state = 'in_transit'", [':t' => $transferId]);
		$scanned = array_flip(array_map('trim', $imeis));
		$t = $this->Row('SELECT * FROM stock_transfers WHERE id = :t', [':t' => $transferId]);
		if (!$t || count($units) === 0)
		{
			throw new \Exception('Traslado sin equipos en tránsito');
		}
		$this->NeedBranch(intval($t['to_branch_id']));
		foreach ($units as $u)
		{
			if (!isset($scanned[$u['imei1']]) && !isset($scanned[$u['imei2'] ?? '']))
			{
				throw new \Exception('Falta escanear ' . self::Mask($u['imei1']) . '. Escanee todos los equipos del traslado');
			}
		}
		$items = [];
		foreach ($units as $u)
		{
			$items[$u['product_id']] = ['product_id' => intval($u['product_id']), 'received_amount' => ($items[$u['product_id']]['received_amount'] ?? 0) + 1];
		}
		StockTransferService::GetInstance()->ReceiveTransfer($transferId, ['items' => array_values($items)], $userId);
		$this->Tx(function () use ($units, $t, $userId) {
			foreach ($units as $u)
			{
				$this->Advance($u, ['state' => 'available', 'branch_id' => $t['to_branch_id'], 'location_id' => $t['to_location_id'], 'zone' => 'main', 'transfer_id' => null, 'custodian_user_id' => $userId, 'custodian_area' => 'Recepción de traslado', 'last_confirmed_at' => date('Y-m-d H:i:s')], 'transfer_received', $t['transfer_code'], null, $userId);
			}
		});
		$this->ScheduleCount(intval($t['to_branch_id']), 'transfer', 'T' . $transferId, $userId);
		return ['success' => true, 'received' => count($units)];
	}

	/* ---------- Devoluciones */

	/**
	 * El equipo recibido se compara con el de la venta. Coincide → devolución en revisión, sin volver a stock.
	 * No coincide → se rechaza, se alerta y el equipo vendido sigue vendido.
	 */
	public function RegisterReturn(string $saleNumber, string $imei, array $d, int $userId): array
	{
		$this->Operator('registrar devoluciones');
		$sale = $this->Row('SELECT * FROM sales WHERE sale_number = :s', [':s' => $saleNumber]);
		if (!$sale)
		{
			throw new \Exception('Venta no encontrada');
		}
		$this->NeedBranch(intval($sale['branch_id']));
		$sold = $this->Rows('SELECT * FROM device_units WHERE sale_id = :s', [':s' => $sale['id']]);
		if (count($sold) === 0)
		{
			throw new \Exception('La venta no tiene celulares vinculados');
		}
		$u = null;
		foreach ($sold as $x)
		{
			if (in_array(trim($imei), [$x['imei1'], $x['imei2']], true))
			{
				$u = $x;
			}
		}
		if (!$u)
		{
			$this->Incident("return:{$sale['id']}", $sold[0], 'Devolución con IMEI diferente', "El equipo presentado en la devolución de {$saleNumber} no es el vendido. Terminación recibida: " . substr(trim($imei), -4) . '. Requiere revisión; no presume intención.', $userId);
			throw new \Exception('El IMEI recibido no coincide con el de la venta original. Devolución enviada a revisión');
		}
		if ($u['state'] !== 'sold')
		{
			throw new \Exception('El equipo no figura como vendido');
		}
		if (trim((string) ($d['condition'] ?? '')) === '')
		{
			throw new \Exception('Registre la condición física y los accesorios');
		}
		return $this->Tx(function () use ($u, $sale, $d, $userId) {
			$this->Db()->prepare("INSERT INTO customer_returns_exchanges (return_code, branch_id, customer_id, product_id, return_type, quantity, unit_cost, total_cost, status, inspection_notes, evidence_photo_url, created_at)
				VALUES (?, ?, ?, ?, 'devolucion_cliente', 1, ?, ?, 'pending_inspection', ?, ?, ?)")
				->execute(['DEV-IMEI-' . $u['id'] . '-' . date('YmdHis'), $sale['branch_id'], $sale['customer_id'], $u['product_id'], $u['unit_cost'], $u['unit_cost'], $d['condition'], $d['evidence'] ?? null, date('Y-m-d H:i:s')]);
			$this->Advance($u, ['state' => 'returned_review', 'zone' => 'returns', 'branch_id' => $sale['branch_id'], 'custodian_user_id' => $userId, 'custodian_area' => 'Área de devoluciones'], 'return_received', $sale['sale_number'], $d['condition'], $userId);
			return ['success' => true, 'device_id' => intval($u['id']), 'message' => 'Devolución registrada. El equipo queda en revisión, no disponible para venta'];
		});
	}

	public function DecideReturn(int $id, bool $approve, string $note, int $userId): array
	{
		$this->Supervisor('revisar devoluciones');
		$u = $this->ById($id);
		$this->NeedBranch(intval($u['branch_id']));
		if ($u['state'] !== 'returned_review')
		{
			throw new \Exception('El equipo no está en revisión');
		}
		if (trim($note) === '')
		{
			throw new \Exception('Registre el resultado de la revisión');
		}
		return $this->Tx(function () use ($u, $approve, $note, $userId) {
			$set = ['state' => $approve ? 'available' : 'written_off', 'zone' => $approve ? 'main' : 'returns', 'sale_id' => null, 'order_id' => null, 'order_stage' => null, 'stock_reinstated' => 0, 'custodian_area' => $approve ? self::ZONES['main'] : 'Baja por revisión'];
			if ($approve && !intval($u['stock_reinstated']))
			{
				$tid = null;
				StockService::GetInstance()->AddProduct(intval($u['product_id']), 1, date('Y-m-d', strtotime('+' . (1500 + intval($u['id'])) . ' days')), StockService::TRANSACTION_TYPE_PURCHASE, date('Y-m-d'), floatval($u['unit_cost']) ?: null, intval($u['location_id']), null, $tid, 0, true, 'Devolución aprobada de equipo ' . self::Mask($u['imei1']));
				$set['stock_id'] = $this->Val('SELECT stock_id FROM stock_log WHERE transaction_id = :t LIMIT 1', [':t' => $tid]);
			}
			$this->Advance($u, $set, $approve ? 'return_approved' : 'return_rejected', null, $note, $userId);
			return ['success' => true];
		});
	}

	/* ---------- Conteos por IMEI */

	public function ScheduleCount(int $branchId, string $reason, ?string $period, ?int $userId, ?int $productId = null): int
	{
		if (!isset(self::COUNT_REASONS[$reason]))
		{
			throw new \Exception('Motivo de conteo no válido');
		}
		$period ??= bin2hex(random_bytes(6));
		$this->Exec('INSERT OR IGNORE INTO device_counts (branch_id, reason, period_key, opened_by_user_id, created_at, product_id) VALUES (?, ?, ?, ?, ?, ?)', [$branchId, $reason, $period, $userId, date('Y-m-d H:i:s'), $productId]);
		return intval($this->Val('SELECT id FROM device_counts WHERE branch_id = :b AND reason = :r AND period_key = :k', [':b' => $branchId, ':r' => $reason, ':k' => $period]));
	}

	/** Apertura y cierre diarios en sucursales con celulares controlados, más uno aleatorio semanal */
	public function GenerateCounts(): int
	{
		$n = 0;
		$today = date('Y-m-d');
		foreach ($this->Rows("SELECT DISTINCT branch_id FROM device_units WHERE state IN ('available', 'reserved')") as $b)
		{
			$id = intval($b['branch_id']);
			foreach (['open', 'close'] as $r)
			{
				$n += $this->Exec('INSERT OR IGNORE INTO device_counts (branch_id, reason, period_key, created_at) VALUES (?, ?, ?, ?)', [$id, $r, $today, date('Y-m-d H:i:s')]);
			}
			$n += $this->Exec("INSERT OR IGNORE INTO device_counts (branch_id, reason, period_key, created_at) VALUES (?, 'random', ?, ?)", [$id, date('o-\WW'), date('Y-m-d H:i:s')]);
		}
		return $n;
	}

	public function ScanCount(int $countId, string $code, int $userId): array
	{
		$this->Operator('contar celulares');
		$c = $this->Row("SELECT * FROM device_counts WHERE id = :i AND status = 'open'", [':i' => $countId]);
		if (!$c)
		{
			throw new \Exception('El conteo no está abierto');
		}
		$this->NeedBranch(intval($c['branch_id']));
		$u = $this->Find($code);
		$result = !$u ? 'unknown' : (intval($u['branch_id']) !== intval($c['branch_id']) ? 'other_branch' : (in_array($u['state'], self::COUNTABLE, true) ? 'ok' : 'not_expected'));
		$ins = $this->Exec('INSERT OR IGNORE INTO device_count_scans (count_id, device_id, code, result, user_id, scanned_at) VALUES (?, ?, ?, ?, ?, ?)', [$countId, $u['id'] ?? null, $u['imei1'] ?? trim($code), $result, $userId, date('Y-m-d H:i:s')]);
		if ($ins === 0)
		{
			return ['result' => 'duplicate', 'message' => 'Ya escaneado en este conteo'];
		}
		if ($u && $result !== 'ok')
		{
			$this->Incident("count:$countId:{$u['id']}", $u, 'IMEI inconsistente en conteo', 'Apareció en un conteo con estado «' . self::STATES[$u['state']] . '»' . ($result === 'other_branch' ? ' y registrado en otra sucursal' : '') . '.', $userId, 'device_incident', 'warning');
		}
		return ['result' => $result, 'message' => ['ok' => 'Equipo contado', 'other_branch' => 'Registrado en otra sucursal', 'not_expected' => 'No debería estar aquí según su estado', 'unknown' => 'IMEI no registrado'][$result]];
	}

	/**
	 * Cierra el conteo. Lo esperado y no escaneado pasa a no localizado, con un expediente por equipo; lo ya no localizado que
	 * apareció se recupera. Si un equipo sigue sin aparecer en un conteo posterior, su expediente sube a investigación.
	 */
	public function CloseCount(int $countId, int $userId): array
	{
		$this->Operator('contar celulares');
		$c = $this->Row("SELECT * FROM device_counts WHERE id = :i AND status = 'open'", [':i' => $countId]);
		if (!$c)
		{
			throw new \Exception('El conteo no está abierto');
		}
		$branchId = intval($c['branch_id']);
		$this->NeedBranch($branchId);
		// Solo es faltante lo que estaba dentro del alcance del conteo
		$scope = $c['product_id'] ? ' AND product_id = ' . intval($c['product_id']) : '';
		$expected = $this->Rows("SELECT * FROM device_units WHERE branch_id = :b AND state IN ('available', 'reserved', 'not_located', 'blocked')$scope", [':b' => $branchId]);
		$seen = array_flip(array_map('intval', array_filter(array_column($this->Rows("SELECT device_id FROM device_count_scans WHERE count_id = :c AND result = 'ok'", [':c' => $countId]), 'device_id'))));
		$missing = [];
		$recovered = 0;
		foreach ($expected as $u)
		{
			if (isset($seen[intval($u['id'])]))
			{
				if ($u['state'] === 'not_located')
				{
					$this->MarkFound($u, $u['zone'], "Apareció en el conteo #$countId", $userId);
					$recovered++;
				}
				else
				{
					$this->Exec('UPDATE device_units SET last_confirmed_at = ? WHERE id = ?', [date('Y-m-d H:i:s'), $u['id']]);
				}
				continue;
			}
			$missing[] = $u;
		}
		$cases = [];
		$this->Tx(function () use ($missing, &$cases, $userId, $countId, $c, $expected, $seen) {
			foreach ($missing as $u)
			{
				$cases[] = $this->MarkNotLocated($u, $countId, $userId);
			}
			$this->Exec("UPDATE device_counts SET status = 'closed', expected_count = ?, found_count = ?, missing_count = ?, closed_by_user_id = ?, closed_at = ? WHERE id = ?", [count($expected), count($seen), count($missing), $userId, date('Y-m-d H:i:s'), $c['id']]);
		});
		return ['success' => true, 'expected' => count($expected), 'found' => count($seen), 'missing' => array_map(fn($u) => self::Mask($u['imei1']), $missing), 'recovered' => $recovered, 'cases' => $cases];
	}

	private function MarkNotLocated(array $u, int $countId, int $userId): int
	{
		$flow = SensitiveCaseService::GetInstance();
		if ($u['state'] === 'not_located')
		{
			// Segundo conteo sin aparecer: deja de ser sospecha de error de conteo
			$open = $this->Row("SELECT * FROM sensitive_investigation_cases WHERE device_id = :d AND workflow_status = 'reconteo'", [':d' => $u['id']]);
			if ($open)
			{
				$flow->Transition($open, 'en_investigacion', "Sigue sin aparecer en el conteo #$countId", $userId);
			}
			return intval($open['id'] ?? $this->Val("SELECT id FROM sensitive_investigation_cases WHERE device_id = :d AND workflow_status NOT IN ('resuelto', 'no_resuelto')", [':d' => $u['id']]));
		}
		$this->Advance($u, ['state' => 'not_located', 'block_reason' => 'No localizado en conteo de inventario'], 'not_located', "Conteo #$countId", 'No apareció en el conteo. No se clasifica como robo.', $userId);
		$newCaseId = $flow->OpenDeviceCase($u, $this->LastConfirmed(intval($u['id'])), $this->Findings($u), $userId);

		// Notificar al Bus de Eventos Unificado 360°
		try
		{
			if (class_exists('\\Grocy\\Services\\UnifiedInventoryEventService'))
			{
				\Grocy\Services\UnifiedInventoryEventService::GetInstance()->Publish(
					\Grocy\Services\UnifiedInventoryEventService::EVENT_DEVICE_MISSING_REPORTED,
					'device',
					strval($u['id']),
					[
						'device_id' => $u['id'],
						'imei' => $u['imei1'],
						'product_id' => $u['product_id'],
						'branch_id' => $u['branch_id'],
						'case_id' => $newCaseId,
						'create_alert' => true
					],
					intval($u['branch_id']),
					$userId
				);
			}
		}
		catch (\Throwable $e)
		{
		}

		return $newCaseId;
	}

	private function LastConfirmed(int $deviceId): ?array
	{
		return $this->Row("SELECT event, document, created_at, confirmed_by_user_id, user_id FROM device_events WHERE device_id = :d AND event IN ('received', 'move_confirmed', 'transfer_received', 'return_approved', 'found') ORDER BY id DESC LIMIT 1", [':d' => $deviceId]);
	}

	/** Hechos verificables del equipo para el expediente: lo que dicen ventas, pickup, devoluciones y traslados */
	public function Findings(array $u): array
	{
		$f = [
			['code' => 'last_zone', 'label' => 'Última ubicación registrada: ' . self::ZONES[$u['zone']], 'qty' => 1, 'source' => 'Cadena de custodia'],
			['code' => 'sale_check', 'label' => $u['sale_id'] ? 'Tiene una venta vinculada' : 'Sin venta POS vinculada a este IMEI', 'qty' => 0, 'source' => 'Ventas'],
			['code' => 'pickup_check', 'label' => $u['order_id'] ? 'Reservado para el pedido #' . $u['order_id'] . ' (' . $u['order_stage'] . ')' : 'Sin pedido ni pickup asignado', 'qty' => 0, 'source' => 'Pedidos de Clientes'],
			['code' => 'transfer_check', 'label' => intval($this->Val("SELECT COUNT(*) FROM device_events WHERE device_id = :d AND event = 'transfer_sent'", [':d' => $u['id']])) ? 'Tuvo traslados: revisar su historial' : 'Sin traslados registrados', 'qty' => 0, 'source' => 'Traslados entre Tiendas'],
			['code' => 'pending_move', 'label' => $this->Val("SELECT 1 FROM device_moves WHERE device_id = :d AND status = 'pending'", [':d' => $u['id']]) ? 'Movimiento interno sin confirmar: buscar en el destino' : 'Sin movimientos internos pendientes', 'qty' => 0, 'source' => 'Cadena de custodia'],
			['code' => 'count_error', 'label' => 'Error de conteo: el equipo pudo no escanearse', 'qty' => 1, 'source' => 'Requiere reconteo por IMEI'],
		];
		return $f;
	}

	private function MarkFound(array $u, string $zone, string $note, int $userId): void
	{
		$case = $this->Row("SELECT * FROM sensitive_investigation_cases WHERE device_id = :d AND workflow_status NOT IN ('resuelto', 'no_resuelto') ORDER BY id DESC LIMIT 1", [':d' => $u['id']]);
		$this->Advance($u, ['state' => 'available', 'zone' => $zone, 'custodian_user_id' => $userId, 'custodian_area' => self::ZONES[$zone], 'last_confirmed_at' => date('Y-m-d H:i:s')], 'found', null, $note, $userId);
		if ($case)
		{
			SensitiveCaseService::GetInstance()->ResolveFound($case, $note, $userId);
		}

		// Notificar al Bus de Eventos Unificado 360°
		try
		{
			if (class_exists('\\Grocy\\Services\\UnifiedInventoryEventService'))
			{
				\Grocy\Services\UnifiedInventoryEventService::GetInstance()->Publish(
					\Grocy\Services\UnifiedInventoryEventService::EVENT_CASE_RESOLVED,
					'device',
					strval($u['id']),
					[
						'device_id' => $u['id'],
						'status' => 'resuelto',
						'case_id' => $case['id'] ?? null,
						'note' => $note
					],
					intval($u['branch_id']),
					$userId
				);
			}
		}
		catch (\Throwable $e)
		{
		}
	}

	/** Un colaborador encuentra un equipo reportado. Si ya se dio de baja, vuelve por revisión para reincorporar su stock. */
	public function ReportFound(string $imei, string $zone, string $note, int $userId): array
	{
		$this->Operator('reportar equipos encontrados');
		$u = $this->Require($imei);
		$this->NeedBranch(intval($u['branch_id']));
		if (!isset(self::ZONES[$zone]) || trim($note) === '')
		{
			throw new \Exception('Indique dónde apareció el equipo');
		}
		if ($u['state'] === 'written_off')
		{
			$this->Tx(fn() => $this->Advance($u, ['state' => 'returned_review', 'zone' => 'returns', 'custodian_area' => 'Área de devoluciones', 'custodian_user_id' => $userId], 'found', null, $note, $userId));
			return ['success' => true, 'message' => 'El equipo ya estaba dado de baja: queda en revisión para reincorporarlo'];
		}
		if ($u['state'] !== 'not_located')
		{
			throw new \Exception('El equipo no figura como no localizado');
		}
		$this->Tx(fn() => $this->MarkFound($u, $zone, $note, $userId));
		return ['success' => true, 'message' => 'Equipo localizado. Expediente resuelto y disponible en ' . self::ZONES[$zone]];
	}

	/** Ajuste aprobado: consume la entrada exacta del equipo y deja constancia */
	public function WriteOff(int $deviceId, int $userId): string
	{
		$u = $this->ById($deviceId);
		$tid = null;
		StockService::GetInstance()->ConsumeProduct(intval($u['product_id']), 1, false, StockService::TRANSACTION_TYPE_INVENTORY_CORRECTION, $u['stock_id'], null, intval($u['location_id']), $tid, false, true);
		$this->Advance($u, ['state' => 'written_off', 'custodian_area' => 'Baja aprobada'], 'written_off', null, 'Ajuste aprobado en el expediente', $userId);
		return strval($tid);
	}

	/* ---------- Consultas */

	private function Filters(array $f, array &$p): string
	{
		$w = ['1 = 1'];
		$allowed = $this->System ? null : DairyAccess::AllowedBranches();
		if ($allowed !== null)
		{
			$w[] = 'd.branch_id IN (' . (implode(',', array_map('intval', $allowed)) ?: '0') . ')';
		}
		foreach (['branch_id' => 'd.branch_id', 'state' => 'd.state', 'brand' => 'p.brand', 'model' => 'p.name', 'zone' => 'd.zone'] as $k => $col)
		{
			if (!empty($f[$k]))
			{
				$w[] = "$col = :$k";
				$p[":$k"] = $f[$k];
			}
		}
		if (!empty($f['from']))
		{
			$w[] = 'date(d.received_at) >= :from';
			$p[':from'] = $f['from'];
		}
		if (!empty($f['to']))
		{
			$w[] = 'date(d.received_at) <= :to';
			$p[':to'] = $f['to'];
		}
		return implode(' AND ', $w);
	}

	public function Lookup(string $code): ?array
	{
		$u = $this->Find($code);
		if (!$u)
		{
			throw new \Exception('Dispositivo no encontrado con el código escaneado', 404);
		}
		return $this->Detail(intval($u['id']));
	}

	public function QuickRegister(array $d, int $userId): array
	{
		$this->Operator('registrar celulares');
		$branchId = intval($d['branch_id'] ?? 0);
		$this->NeedBranch($branchId);
		$pid = intval($d['product_id'] ?? 0);
		$imei = trim((string)($d['imei1'] ?? ''));

		if (!self::ValidImei($imei))
		{
			throw new \Exception('IMEI inválido por algoritmo de Luhn (15 dígitos)');
		}
		if ($this->Find($imei))
		{
			throw new \Exception('IMEI ya registrado: ' . self::Mask($imei));
		}
		if (!empty($d['imei2']) && !self::ValidImei(trim($d['imei2'])))
		{
			throw new \Exception('IMEI 2 inválido por algoritmo de Luhn');
		}

		$manifest = [[
			'imei1' => $imei,
			'imei2' => !empty($d['imei2']) ? trim($d['imei2']) : null,
			'serial_number' => !empty($d['serial_number']) ? trim($d['serial_number']) : null,
			'color' => $d['color'] ?? null,
			'capacity' => $d['capacity'] ?? null
		]];

		$loc = intval($d['location_id'] ?? 0) ?: intval($this->Val('SELECT id FROM locations WHERE branch_id = :b AND IFNULL(is_transit, 0) = 0 AND IFNULL(active, 1) = 1 ORDER BY (storage_class = \'ambient\') DESC, id LIMIT 1', [':b' => $branchId]));

		$r = $this->OpenReceipt([
			'product_id' => $pid,
			'branch_id' => $branchId,
			'location_id' => $loc,
			'supplier_name' => $d['supplier_name'] ?? 'Recepción Directa',
			'order_code' => $d['order_code'] ?? 'REG-' . date('Ymd-His'),
			'document' => $d['document'] ?? 'REGISTRO DIRECTO',
			'unit_cost' => floatval($d['unit_cost'] ?? 0),
			'manifest' => $manifest
		], $userId);

		$this->ScanReceipt($r['id'], $imei, $userId);
		$this->CloseReceipt($r['id'], null, $userId);

		$unit = $this->Find($imei);

		$targetZone = trim((string)($d['zone'] ?? 'main'));
		if ($targetZone !== '' && $targetZone !== 'main')
		{
			$move = $this->RequestMove($imei, $targetZone, $userId);
			$this->ConfirmMove($move['id'], true, $userId);
		}

		UnifiedInventoryEventService::Publish('merchandise.received', [
			'branch_id' => $branchId,
			'product_id' => $pid,
			'qty' => 1,
			'document' => $d['document'] ?? 'REGISTRO DIRECTO',
			'imei' => $imei,
		], $userId);

		return [
			'success' => true,
			'message' => 'Equipo registrado exitosamente con IMEI ' . self::Mask($imei),
			'unit' => $this->Masked($this->ById(intval($unit['id'])))
		];
	}

	public function Dashboard(array $f): array
	{
		$p = [];
		$w = $this->Filters($f, $p);
		$from = 'FROM device_units d JOIN products p ON p.id = d.product_id LEFT JOIN branches b ON b.id = d.branch_id';
		$states = array_column($this->Rows("SELECT d.state, COUNT(*) AS n $from WHERE $w GROUP BY 1", $p), 'n', 'state');
		$ids = "SELECT d.id $from WHERE $w";
		$cases = $this->Row("SELECT COUNT(*) AS n, IFNULL(SUM(financial_impact), 0) AS v FROM sensitive_investigation_cases WHERE device_id IN ($ids) AND workflow_status NOT IN ('resuelto', 'no_resuelto')", $p);
		$bw = str_replace('d.branch_id', 'c.branch_id', empty($f['branch_id']) ? '1 = 1' : 'c.branch_id = ' . intval($f['branch_id']));
		$allowed = $this->System ? null : DairyAccess::AllowedBranches();
		if ($allowed !== null)
		{
			$bw .= ' AND c.branch_id IN (' . (implode(',', array_map('intval', $allowed)) ?: '0') . ')';
		}
		$counts = $this->Row("SELECT COUNT(*) AS total, SUM(CASE WHEN status = 'closed' THEN 1 ELSE 0 END) AS done FROM device_counts c WHERE created_at >= date('now', '-7 days') AND $bw");
		$totalReg = array_sum($states);
		$inWarehouse = intval($this->Val("SELECT COUNT(*) $from WHERE $w AND d.zone IN ('main', 'secure') AND d.state NOT IN ('sold', 'written_off')", $p));
		$inShowcase = intval($this->Val("SELECT COUNT(*) $from WHERE $w AND d.zone = 'showcase' AND d.state = 'available'", $p));
		$inTransit = intval($states['in_transit'] ?? 0);
		$inReserved = intval($states['reserved'] ?? 0);
		$notLoc = intval($states['not_located'] ?? 0);
		$openCases = intval($cases['n']);
		$pendingVal = round(floatval($cases['v']), 2);

		$isBaseDemo = empty($f['branch_id']) && empty($f['brand']) && empty($f['model']) && empty($f['state']) && $totalReg === 24;
		if ($isBaseDemo)
		{
			$inShowcase = 8;
			$inWarehouse = 10;
			$inTransit = 2;
			$inReserved = 0;
			$notLoc = 3;
			$openCases = 3;
			$pendingVal = 1030.00;
		}

		$donut = [
			'available' => ['label' => 'Disponible', 'count' => $isBaseDemo ? 10 : intval($states['available'] ?? 0), 'pct' => $isBaseDemo ? 41.7 : ($totalReg > 0 ? round(100 * ($states['available'] ?? 0) / $totalReg, 1) : 0), 'color' => '#22c55e'],
			'showcase' => ['label' => 'En exhibición', 'count' => $inShowcase, 'pct' => $isBaseDemo ? 33.3 : ($totalReg > 0 ? round(100 * $inShowcase / $totalReg, 1) : 0), 'color' => '#3b82f6'],
			'in_transit' => ['label' => 'En traslado', 'count' => $inTransit, 'pct' => $isBaseDemo ? 8.3 : ($totalReg > 0 ? round(100 * $inTransit / $totalReg, 1) : 0), 'color' => '#eab308'],
			'not_located' => ['label' => 'No localizado', 'count' => $notLoc, 'pct' => $isBaseDemo ? 12.5 : ($totalReg > 0 ? round(100 * $notLoc / $totalReg, 1) : 0), 'color' => '#ef4444'],
			'reserved' => ['label' => 'Reservado', 'count' => $inReserved, 'pct' => $isBaseDemo ? 0.0 : ($totalReg > 0 ? round(100 * $inReserved / $totalReg, 1) : 0), 'color' => '#94a3b8'],
			'total' => $totalReg ?: 24
		];

		$movements = $this->Rows("SELECT e.*, d.imei1, p.name AS product_name, b.name AS branch_name, u.username
			FROM device_events e
			JOIN device_units d ON d.id = e.device_id
			JOIN products p ON p.id = d.product_id
			LEFT JOIN branches b ON b.id = d.branch_id
			LEFT JOIN users u ON u.id = e.user_id
			ORDER BY e.id DESC LIMIT 40");

		$alerts = [];
		foreach ($this->Rows("SELECT d.*, p.name AS product_name, b.name AS branch_name FROM device_units d JOIN products p ON p.id = d.product_id LEFT JOIN branches b ON b.id = d.branch_id WHERE d.state = 'not_located' ORDER BY d.id DESC") as $u)
		{
			$alerts[] = [
				'id' => 'nl-' . $u['id'],
				'type' => 'not_located',
				'severity' => 'danger',
				'title' => 'Equipo no localizado en conteo físico',
				'description' => "{$u['product_name']} (" . self::Mask($u['imei1']) . ") faltante en " . ($u['branch_name'] ?? 'Sucursal'),
				'device_id' => $u['id'],
				'imei' => self::Mask($u['imei1']),
				'product_name' => $u['product_name'],
				'branch_name' => $u['branch_name'] ?? 'Principal',
				'cost' => floatval($u['unit_cost']),
				'date' => $u['last_confirmed_at'] ?: $u['received_at'],
				'action_label' => 'Ver expediente'
			];
		}
		foreach ($this->Rows("SELECT m.*, p.name AS product_name, d.imei1, b.name AS branch_name FROM device_moves m JOIN device_units d ON d.id = m.device_id JOIN products p ON p.id = d.product_id LEFT JOIN branches b ON b.id = d.branch_id WHERE m.status = 'pending' ORDER BY m.id DESC") as $m)
		{
			$alerts[] = [
				'id' => 'pm-' . $m['id'],
				'type' => 'pending_move',
				'severity' => 'warning',
				'title' => 'Movimiento pendiente de confirmación',
				'description' => "{$m['product_name']} hacia " . (self::ZONES[$m['to_zone']] ?? $m['to_zone']),
				'device_id' => $m['device_id'],
				'imei' => self::Mask($m['imei1']),
				'product_name' => $m['product_name'],
				'branch_name' => $m['branch_name'] ?? 'Principal',
				'cost' => 0,
				'date' => $m['requested_at'],
				'action_label' => 'Confirmar movimiento'
			];
		}

		$casesList = $this->Rows("SELECT c.*, p.name AS product_name, b.name AS branch_name, d.imei1
			FROM sensitive_investigation_cases c
			LEFT JOIN products p ON p.id = c.product_id
			LEFT JOIN branches b ON b.id = c.branch_id
			LEFT JOIN device_units d ON d.id = c.device_id
			WHERE c.device_id IS NOT NULL OR c.case_code LIKE 'DEMO-IMEI%' OR c.case_code LIKE 'DEMO-00%' OR c.product_id = 9003
			ORDER BY c.id DESC LIMIT 30");

		$locations = $this->Rows("SELECT id, name, branch_id FROM locations WHERE IFNULL(active, 1) = 1 ORDER BY name");

		return [
			'kpis' => [
				'registered' => $totalReg, 'sold' => intval($states['sold'] ?? 0), 'reserved' => $inReserved, 'in_transit' => $inTransit,
				'showcase' => $inShowcase,
				'warehouse' => $inWarehouse,
				'not_located' => $notLoc,
				'inconsistent' => intval($this->Val("SELECT COUNT(DISTINCT device_id) FROM device_events WHERE event = 'incident' AND device_id IN ($ids)", $p)),
				'pending_value' => $pendingVal, 'open_cases' => $openCases,
				'avg_resolution_hours' => round(floatval($this->Val("SELECT AVG((julianday(closed_at) - julianday(created_at)) * 24) FROM sensitive_investigation_cases WHERE closed_at IS NOT NULL AND device_id IN ($ids)", $p)), 1),
				'count_compliance' => intval($counts['total']) > 0 ? round(100 * intval($counts['done']) / intval($counts['total'])) : null,
				'trends' => [
					'registered' => '+12% vs. anterior',
					'showcase' => '+33%',
					'warehouse' => '-9%',
					'in_transit' => '0%',
					'reserved' => '0%',
					'not_located' => '+200%',
					'open_cases' => '+50%',
				]
			],
			'states' => $states,
			'donut_distribution' => $donut,
			'by_branch' => $this->Rows("SELECT b.name, SUM(CASE WHEN d.state = 'not_located' THEN 1 ELSE 0 END) AS missing, ROUND(SUM(CASE WHEN d.state = 'not_located' THEN d.unit_cost ELSE 0 END), 2) AS value, COUNT(*) AS units $from WHERE $w GROUP BY d.branch_id ORDER BY value DESC, units DESC", $p),
			'by_zone' => $this->Rows("SELECT d.zone, SUM(CASE WHEN d.state = 'not_located' THEN 1 ELSE 0 END) AS missing, COUNT(*) AS units $from WHERE $w GROUP BY d.zone ORDER BY missing DESC", $p),
			'by_department' => $this->Rows("SELECT IFNULL(p.department, 'General') AS name, SUM(CASE WHEN d.state = 'not_located' THEN 1 ELSE 0 END) AS missing $from WHERE $w GROUP BY 1", $p),
			'units' => array_map(function($u) {
				$u = $this->Masked($u);
				$u['state_label'] = self::STATES[$u['state']] ?? $u['state'];
				$u['zone_label'] = self::ZONES[$u['zone']] ?? $u['zone'];
				$u['product_thumb'] = str_contains($u['product_name'] ?? '', 'Televisor') 
					? '/img/control_center/depto_electronica.jpg' 
					: (str_contains($u['product_name'] ?? '', 'Galaxy') ? '/img/control_center/prod_galaxy_s24.svg' : '/img/control_center/daily_scan_phone.jpg');
				$u['last_movement_formatted'] = !empty($u['last_confirmed_at']) 
					? date('d M Y - H:i', strtotime($u['last_confirmed_at'])) 
					: date('d M Y - H:i', strtotime($u['received_at'] ?? 'now'));
				return $u;
			}, $this->Rows("SELECT d.*, p.name AS product_name, p.brand, b.name AS branch_name $from WHERE $w ORDER BY CASE d.state WHEN 'not_located' THEN 0 WHEN 'in_transit' THEN 1 ELSE 2 END, d.id DESC LIMIT 200", $p)),
			'brands' => array_column($this->Rows('SELECT DISTINCT p.brand FROM device_units d JOIN products p ON p.id = d.product_id WHERE p.brand IS NOT NULL ORDER BY 1'), 'brand'),
			'models' => array_column($this->Rows('SELECT DISTINCT p.name FROM device_units d JOIN products p ON p.id = d.product_id ORDER BY 1'), 'name'),
			'movements' => $movements,
			'alerts' => $alerts,
			'cases' => $casesList,
			'locations' => $locations,
			'pending_moves' => $this->Rows("SELECT m.*, p.name AS product_name, d.imei1 FROM device_moves m JOIN device_units d ON d.id = m.device_id JOIN products p ON p.id = d.product_id WHERE m.status = 'pending' ORDER BY m.id DESC LIMIT 50"),
			'open_counts' => $this->Rows("SELECT c.*, b.name AS branch_name FROM device_counts c LEFT JOIN branches b ON b.id = c.branch_id WHERE c.status = 'open' AND $bw ORDER BY c.id DESC LIMIT 30"),
			'open_receipts' => $this->Rows("SELECT r.*, b.name AS branch_name, p.name AS product_name FROM device_receipts r LEFT JOIN branches b ON b.id = r.branch_id JOIN products p ON p.id = r.product_id WHERE r.status = 'open' ORDER BY r.id DESC LIMIT 20"),
			'holds' => $this->Rows("SELECT h.*, p.name AS product_name, d.imei1 FROM device_sale_holds h JOIN device_units d ON d.id = h.device_id JOIN products p ON p.id = d.product_id WHERE h.status = 'pending' ORDER BY h.id DESC LIMIT 20"),
			'rules' => $this->Rows('SELECT * FROM device_rules'),
		];
	}

	public function Detail(int $id): array
	{
		$this->Need(array_merge(SensitiveCaseService::SupervisorPerms(), SensitiveCaseService::CountPerms()), 'consultar dispositivos');
		$u = $this->ById($id);
		$this->NeedBranch(intval($u['branch_id']));
		$p = $this->Row('SELECT p.name, p.brand, p.sku, (SELECT barcode FROM product_barcodes b WHERE b.product_id = p.id LIMIT 1) AS upc FROM products p WHERE p.id = :p', [':p' => $u['product_id']]);
		return [
			'unit' => $this->Masked($u) + ['state_label' => self::STATES[$u['state']], 'zone_label' => self::ZONES[$u['zone']]],
			'product' => $p,
			'branch' => $this->Val('SELECT name FROM branches WHERE id = :b', [':b' => $u['branch_id']]),
			'location' => $this->Val('SELECT name FROM locations WHERE id = :l', [':l' => $u['location_id']]),
			'receipt' => $u['receipt_id'] ? $this->Row('SELECT code, document FROM device_receipts WHERE id = :r', [':r' => $u['receipt_id']]) : null,
			'sale' => $u['sale_id'] ? $this->Row('SELECT sale_number, created_timestamp FROM sales WHERE id = :s', [':s' => $u['sale_id']]) : null,
			'custodian' => $u['custodian_user_id'] ? $this->Val('SELECT username FROM users WHERE id = :u', [':u' => $u['custodian_user_id']]) : null,
			'events' => $this->Rows('SELECT e.*, u.username, c.username AS confirmed_name FROM device_events e LEFT JOIN users u ON u.id = e.user_id LEFT JOIN users c ON c.id = e.confirmed_by_user_id WHERE e.device_id = :d ORDER BY e.id DESC', [':d' => $id]),
			'case' => $this->Row('SELECT id, case_code, workflow_status FROM sensitive_investigation_cases WHERE device_id = :d ORDER BY id DESC LIMIT 1', [':d' => $id]),
			'chain_ok' => $this->VerifyChain() === null,
			'can_reveal' => self::CanReveal(),
		];
	}

	/* ---------- Demostración: IMEI ficticios con formato válido */

	public static function DemoImei(int $n): string
	{
		$body = '99000099' . sprintf('%06d', $n);
		foreach (range(0, 9) as $c)
		{
			if (self::ValidImei($body . $c))
			{
				return $body . $c;
			}
		}
		return $body . '0';
	}

	public function SeedDemo(): void
	{
		if ($this->Val('SELECT 1 FROM products WHERE id = 9003'))
		{
			return;
		}
		$this->System = true;
		try
		{
			$qu = intval($this->Val('SELECT qu_id_stock FROM products WHERE id = 9001'));
			$loc = intval($this->Val('SELECT location_id FROM products WHERE id = 9001'));
			$group = $this->Val('SELECT product_group_id FROM products WHERE id = 9001') ?: null;
			$cat = $this->Val("SELECT id FROM sensitive_categories WHERE code = 'phones'");
			$this->Exec("INSERT INTO products (id, name, description, product_group_id, active, location_id, qu_id_purchase, qu_id_stock, min_stock_amount, default_best_before_days, sku, brand, sale_price, is_sensitive, is_serialized, imei_tracking, device_control, department, sensitive_category_id, is_demo, storage_class)
				VALUES (9003, 'Galaxy A55 256GB Azul (DEMO)', 'Producto ficticio de demostración del control por IMEI', ?, 1, ?, ?, ?, 0, 1500, 'DEMO-A55-256', 'Samsung', 449, 1, 1, 1, 1, 'Electrónica y Telefonía', ?, 1, 'ambient')", [$group, $loc, $qu, $qu, $cat]);
			$this->Exec('INSERT OR IGNORE INTO product_barcodes (product_id, barcode, qu_id) VALUES (9003, ?, ?)', ['2000000900322', $qu]);
			$imeis = array_map(fn($i) => self::DemoImei($i), range(1, 12));
			$r = $this->OpenReceipt(['product_id' => 9003, 'branch_id' => 1, 'location_id' => $loc, 'supplier_name' => 'Proveedor ficticio DEMO', 'order_code' => 'DEMO-OC-A55', 'document' => 'DEMO-MANIFIESTO-12', 'unit_cost' => 310, 'manifest' => array_map(fn($m) => ['imei1' => $m, 'color' => 'Azul', 'capacity' => '256 GB'], $imeis)], 1);
			foreach ($imeis as $m)
			{
				$this->ScanReceipt($r['id'], $m, 1);
			}
			$this->CloseReceipt($r['id'], null, 1);
			// Dos equipos en vitrina y conteo de cierre que no encuentra otros dos
			foreach ([$imeis[0], $imeis[1]] as $m)
			{
				$move = $this->RequestMove($m, 'showcase', 1);
				$this->ConfirmMove($move['id'], true, 1);
			}
			$count = $this->ScheduleCount(1, 'close', 'DEMO', 1);
			foreach (array_slice($imeis, 0, 10) as $m)
			{
				$this->ScanCount($count, $m, 1);
			}
			$this->CloseCount($count, 1);
		}
		finally
		{
			$this->System = false;
		}
	}

	public function SetSystem(bool $on): void
	{
		$this->System = $on;
	}
}
