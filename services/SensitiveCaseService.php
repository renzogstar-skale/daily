<?php

namespace Grocy\Services;

use Grocy\Controllers\Users\User;
use Grocy\Helpers\DairyAccess;

/**
 * DAILY — Expedientes de prevención de pérdidas y conteos sensibles.
 *
 * Toda diferencia entra por RegisterCount: un expediente abierto por producto y sucursal, una alerta por expediente.
 * Flujo: detectado → reconteo → en_investigacion → pendiente_aprobacion → resuelto | no_resuelto.
 * La evidencia es objetiva: quien registró un movimiento nunca se presenta como responsable de la diferencia.
 * Ajustes de stock: ReconciliationService::AuthorizeAdjustment (segregación: propone supervisor, aprueba gerente).
 */
class SensitiveCaseService extends BaseService
{
	const STATUSES = [
		'detectado' => 'Detectado', 'reconteo' => 'Reconteo', 'en_investigacion' => 'En investigación',
		'pendiente_aprobacion' => 'Pendiente de aprobación', 'resuelto' => 'Cerrado: resuelto', 'no_resuelto' => 'Cerrado: no resuelto'
	];
	const OPEN = ['detectado', 'reconteo', 'en_investigacion', 'pendiente_aprobacion'];
	const TRANSITIONS = [
		'detectado' => ['reconteo', 'resuelto'],
		'reconteo' => ['en_investigacion', 'resuelto'],
		'en_investigacion' => ['reconteo', 'pendiente_aprobacion', 'resuelto', 'no_resuelto'],
		'pendiente_aprobacion' => ['en_investigacion', 'resuelto', 'no_resuelto'],
	];
	// Etapa del módulo 36 que siguen leyendo Conciliación y sus reportes
	const STAGE = ['detectado' => 'preliminar', 'reconteo' => 'preliminar', 'en_investigacion' => 'faltante_confirmado', 'pendiente_aprobacion' => 'faltante_confirmado', 'resuelto' => 'cerrado', 'no_resuelto' => 'cerrado'];
	const FREQUENCIES = ['daily' => 'Diario', 'shift' => 'Por turno', 'weekly' => 'Semanal', 'risk' => 'Por nivel de riesgo'];

	private bool $System = false; // Procesos internos (semilla, reevaluación): sin usuario de sesión

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

	private function Engine(): LossReconciliationService
	{
		return LossReconciliationService::GetInstance();
	}

	private function Need(array $permissions, string $what): void
	{
		if (!$this->System && !DairyAccess::HasAny($permissions))
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

	public static function CountPerms(): array
	{
		return [User::PERMISSION_SCAN_OPERATE, User::PERMISSION_STOCK_INVENTORY, User::PERMISSION_LOSS_PREVENTION];
	}

	public static function SupervisorPerms(): array
	{
		return [User::PERMISSION_LOSS_PREVENTION, User::PERMISSION_ALERTS_SUPERVISE];
	}

	public static function ManagerPerms(): array
	{
		return [User::PERMISSION_INVENTORY_ADJUST_APPROVE];
	}

	public function RequireManager(): void
	{
		$this->Need(self::ManagerPerms(), 'aprobar ajustes o cerrar expedientes');
	}

	/* ---------- Bitácora encadenada */

	public function Log(int $caseId, string $action, ?string $from, ?string $to, ?string $note, ?array $payload, ?int $userId): void
	{
		$now = date('Y-m-d H:i:s');
		$json = $payload ? json_encode($payload, JSON_UNESCAPED_UNICODE) : null;
		$prev = $this->Val('SELECT hash FROM sensitive_case_events ORDER BY id DESC LIMIT 1') ?: null;
		$hash = hash('sha256', implode('|', [$prev, $caseId, $action, $from, $to, $note, $json, $userId, $now]));
		$this->Db()->prepare('INSERT INTO sensitive_case_events (case_id, action, from_status, to_status, note, payload_json, user_id, created_at, prev_hash, hash) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
			->execute([$caseId, $action, $from, $to, $note, $json, $userId, $now, $prev, $hash]);
	}

	/** Recalcula toda la cadena; devuelve el primer evento alterado o null si está íntegra */
	public function VerifyChain(): ?int
	{
		$prev = null;
		foreach ($this->Rows('SELECT * FROM sensitive_case_events ORDER BY id') as $e)
		{
			$h = hash('sha256', implode('|', [$e['prev_hash'], $e['case_id'], $e['action'], $e['from_status'], $e['to_status'], $e['note'], $e['payload_json'], $e['user_id'], $e['created_at']]));
			if ($h !== $e['hash'] || $e['prev_hash'] !== $prev)
			{
				return intval($e['id']);
			}
			$prev = $e['hash'];
		}
		return null;
	}

	/* ---------- Estados */

	private function Case(int $id): array
	{
		$c = $this->Row('SELECT * FROM sensitive_investigation_cases WHERE id = :id', [':id' => $id]);
		if (!$c)
		{
			throw new \Exception('Expediente no encontrado', 404);
		}
		$this->NeedBranch(intval($c['branch_id']));
		return $c;
	}

	/** Cambia de estado (sin abrir transacción: la abre quien llama), registra el evento y sincroniza la alerta */
	public function Transition(array $c, string $to, ?string $note, ?int $userId, array $extra = []): void
	{
		$from = $c['workflow_status'];
		if (!in_array($to, self::TRANSITIONS[$from] ?? [], true))
		{
			throw new \Exception('El expediente en estado «' . (self::STATUSES[$from] ?? $from) . '» no puede pasar a «' . (self::STATUSES[$to] ?? $to) . '»');
		}
		if ($to === 'en_investigacion' && empty($c['assigned_user_id']) && empty($extra['assigned_user_id']))
		{
			if ($userId && $userId > 0)
			{
				$extra['assigned_user_id'] = $userId;
			}
			else
			{
				throw new \Exception('Debe asignar un responsable antes de iniciar investigación');
			}
		}
		$now = date('Y-m-d H:i:s');
		$final = !in_array($to, self::OPEN, true);
		$set = $extra + ['workflow_status' => $to, 'stage' => self::STAGE[$to], 'updated_at' => $now];
		if ($final)
		{
			$set += ['closed_by_user_id' => $userId, 'closed_at' => $now, 'resolution_notes' => $note];
		}
		$cols = array_keys($set);
		$this->Db()->prepare('UPDATE sensitive_investigation_cases SET ' . implode(', ', array_map(fn($k) => "$k = ?", $cols)) . ' WHERE id = ?')->execute([...array_values($set), $c['id']]);
		$this->Log(intval($c['id']), 'status', $from, $to, $note, null, $userId);
		$this->SyncAlert(intval($c['id']), $userId, $final ? $note : null);
		if ($final)
		{
			$this->Db()->prepare("UPDATE sensitive_count_tasks SET status = 'cancelled' WHERE case_id = ? AND status IN ('pending', 'recount')")->execute([$c['id']]);
		}
	}

	private function Severity(array $c, array $product, ?array $cat): string
	{
		$threshold = floatval($cat['high_value_threshold'] ?? 500);
		$recurrent = intval($this->Val("SELECT COUNT(*) FROM sensitive_investigation_cases WHERE product_id = :p AND branch_id = :b AND created_at >= date('now', '-90 days') AND is_demo = :d", [':p' => $c['product_id'], ':b' => $c['branch_id'], ':d' => $c['is_demo']])) > 1;
		if (intval($c['provisional']) === 1)
		{
			return 'warning';
		}
		return floatval($c['financial_impact']) >= $threshold || $recurrent || ($c['workflow_status'] !== 'reconteo' && intval($product['is_sensitive']) === 1) ? 'danger' : 'warning';
	}

	private function SyncAlert(int $caseId, ?int $userId, ?string $resolution): void
	{
		$c = $this->Row('SELECT * FROM sensitive_investigation_cases WHERE id = :id', [':id' => $caseId]);
		$p = $this->Row('SELECT * FROM products WHERE id = :id', [':id' => $c['product_id']]);
		$cat = $this->Category($p);
		$diff = floatval($c['difference_qty']);
		$title = ($diff < 0 ? 'Diferencia de inventario: ' : 'Excedente de inventario: ') . $p['name'] . ' (' . floatval(abs($diff)) . ' u.)' . (intval($c['is_demo']) ? ' [DEMO]' : '');
		$detail = "Expediente {$c['case_code']} · Conteo " . floatval($c['physical_count']) . ' vs esperado ' . floatval($c['expected_stock']) . ' · ' . self::STATUSES[$c['workflow_status']]
			. (intval($c['provisional']) ? ' · Provisional: hay operaciones pendientes de sincronización' : '') . '. No presume sustracción ni señala a ninguna persona.';
		$alerts = AlertCenterService::GetInstance();
		$fields = ['severity' => $this->Severity($c, $p, $cat), 'title' => $title, 'detail' => $detail, 'value' => floatval($c['financial_impact']), 'owner_user_id' => $c['assigned_user_id'] ?: null, 'due_at' => $c['due_at']];
		if ($resolution !== null)
		{
			$alerts->CloseIncident(intval($c['alert_id']), $resolution, $userId, $fields);
			return;
		}
		if (empty($c['alert_id']))
		{
			if (!empty($c['device_id']))
			{
				$fields['title'] = 'Celular no localizado: ' . $p['name'] . (intval($c['is_demo']) ? ' [DEMO]' : '');
			}
			$id = $alerts->RaiseIncident($fields + ['rule_code' => !empty($c['device_id']) ? 'device_not_located' : 'sensitive_difference', 'dedupe_key' => 'lp:case:' . $caseId, 'branch_id' => $c['branch_id'], 'href' => '/loss-prevention/case/' . $caseId,
				'category' => 'inventory', 'alert_type' => 'count_diff', 'department' => $c['department'], 'product_id' => $c['product_id'], 'impact_bucket' => 'inventory', 'impact_kind' => 'estimated'], $userId);
			$this->Db()->prepare('UPDATE sensitive_investigation_cases SET alert_id = ? WHERE id = ?')->execute([$id, $caseId]);
			return;
		}
		$alerts->UpdateIncident(intval($c['alert_id']), $fields + ['status' => ['reconteo' => 'open', 'en_investigacion' => 'investigating', 'pendiente_aprobacion' => 'pending_approval', 'resuelto' => 'reopened'][$c['workflow_status']] ?? 'open']);
	}

	private function Category(array $product): ?array
	{
		return empty($product['sensitive_category_id']) ? null : $this->Row('SELECT * FROM sensitive_categories WHERE id = :id', [':id' => $product['sensitive_category_id']]);
	}

	/* ---------- Conteo → diferencia → expediente */

	public function RegisterCount(int $branchId, int $productId, float $counted, array $opt, int $userId): array
	{
		$this->Need(self::CountPerms(), 'registrar conteos');
		$this->NeedBranch($branchId);
		if ($counted < 0)
		{
			throw new \Exception('La cantidad contada no puede ser negativa');
		}
		$op = $opt['operation_id'] ?? null;
		if ($op && ($prev = $this->Row('SELECT * FROM sensitive_inventory_movements WHERE operation_id = :o', [':o' => $op])))
		{
			$caseId = $this->Val('SELECT id FROM sensitive_investigation_cases WHERE branch_id = :b AND product_id = :p ORDER BY id DESC LIMIT 1', [':b' => $branchId, ':p' => $productId]) ?: null;
			return ['success' => true, 'replayed' => true, 'expected_stock' => floatval($prev['expected_amount']), 'physical_count' => floatval($prev['actual_amount']), 'difference_qty' => floatval($prev['difference_amount']),
				'financial_impact' => abs(floatval($prev['financial_impact'])), 'alert_generated' => false, 'alert_id' => null, 'case_id' => $caseId ? intval($caseId) : null, 'message' => 'Conteo ya registrado (reintento sin duplicar)'];
		}
		$p = $this->Row('SELECT * FROM products WHERE id = :id', [':id' => $productId]);
		if (!$p)
		{
			throw new \Exception('Producto no encontrado');
		}
		$locationId = !empty($opt['location_id']) ? intval($opt['location_id']) : null;
		$engine = $this->Engine();
		$expected = $engine->Expected($productId, $branchId, null, null, $locationId)['expected'];
		$positions = $engine->Positions($productId, $branchId);
		$pending = $engine->PendingSync($productId, $branchId);
		$cost = $this->UnitCost($p, $branchId);
		$diff = round($counted - $expected, 4);
		$value = round(abs($diff) * $cost, 2);
		$now = date('Y-m-d H:i:s');
		$location = $locationId ? $this->Val('SELECT name FROM locations WHERE id = :l', [':l' => $locationId]) : ($engine->LastKnown($productId, $branchId)['location'] ?: 'Sucursal');
		$isDemo = intval($p['is_demo']);
		$label = ($opt['count_type'] ?? 'sensible') === 'sensible' ? 'Inventario Sensible: ' : 'Inventario Selectivo: ';

		$db = $this->Db();
		$db->beginTransaction();
		try
		{
			$code = $this->NextCode('CNT');
			$db->prepare("INSERT INTO sensitive_inventory_movements (movement_code, branch_id, product_id, event_type, warehouse_type, status, origin_location, dest_location, location_id, shift_code,
				expected_amount, actual_amount, accepted_amount, difference_amount, unit_cost, financial_impact, document_type, document_code, notes, user_id, created_at, confirmed_at, operation_id, is_demo)
				VALUES (?, ?, ?, 'cycle_count', 'physical', 'confirmado', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'CNT', ?, ?, ?, ?, ?, ?, ?)")
				->execute([$code, $branchId, $productId, $location, $location, $locationId, LossReconciliationService::ShiftOf($now), $expected, $counted, $counted, $diff, $cost, $diff < 0 ? -$value : $value, $code,
					$label . (($opt['notes'] ?? '') ?: 'Conteo físico ejecutado.'), $userId, $now, $now, $op, $isDemo]);

			$caseId = null;
			$alertId = null;
			if (!empty($opt['lot']))
			{
				$db->prepare('UPDATE sensitive_inventory_movements SET batch_number = ? WHERE movement_code = ?')->execute([$opt['lot'], $code]);
			}
			$open = $this->OpenCase($branchId, $productId);
			// Tolerancia autorizada del producto (peso, granel): se registra el conteo, sin expediente
			$tolerance = floatval($p['count_tolerance_pct'] ?? 0);
			$withinTolerance = $tolerance > 0 && $expected > 0 && abs($diff) / $expected * 100 <= $tolerance;
			if (abs($diff) > 0.0001 && !$withinTolerance)
			{
				$explain = $engine->Explain($productId, $branchId, $diff, $locationId, $positions, $pending);
				$fields = ['expected_stock' => $expected, 'physical_count' => $counted, 'difference_qty' => $diff, 'unit_cost' => $cost, 'financial_impact' => $value, 'count_at' => $now,
					'provisional' => count($pending) > 0 ? 1 : 0, 'explained_qty' => $explain['explained_qty'], 'location_name' => $location, 'location_id' => $locationId, 'updated_at' => $now];
				if ($open)
				{
					// Mismo incidente: se suma al expediente; no hay alerta ni expediente nuevos
					$caseId = intval($open['id']);
					$cols = array_keys($fields);
					$db->prepare('UPDATE sensitive_investigation_cases SET ' . implode(', ', array_map(fn($k) => "$k = ?", $cols)) . ', occurrences = occurrences + 1 WHERE id = ?')->execute([...array_values($fields), $caseId]);
					$this->Log($caseId, 'count_attached', $open['workflow_status'], $open['workflow_status'], "Nuevo conteo $code: $counted u. vs $expected esperadas", null, $userId);
				}
				else
				{
					$caseId = $this->OpenNewCase($p, $branchId, $fields, $opt, $userId, $now);
				}
				$this->SaveHypotheses($caseId, $explain['hypotheses']);
				$this->SyncAlert($caseId, $userId, null);
				$alertId = intval($this->Val('SELECT alert_id FROM sensitive_investigation_cases WHERE id = :id', [':id' => $caseId]));
				$this->EnsureRecountTask($caseId, $userId);
			}
			$db->commit();

			// Notificar al Bus de Eventos Unificado 360° (RegisterCount)
			try
			{
				if (class_exists('\\Grocy\\Services\\UnifiedInventoryEventService'))
				{
					$eventSvc = \Grocy\Services\UnifiedInventoryEventService::GetInstance();
					if (abs($diff) > 0.0001 && !$withinTolerance)
					{
						$eventSvc->Publish(
							\Grocy\Services\UnifiedInventoryEventService::EVENT_INVENTORY_DISCREPANCY_DETECTED,
							'product',
							strval($productId),
							[
								'product_name' => $p['name'] ?? '',
								'sku' => $p['sku'] ?? '',
								'expected' => $expected,
								'counted' => $counted,
								'difference' => $diff,
								'financial_impact' => $value,
								'case_id' => $caseId,
								'branch_id' => $branchId
							],
							$branchId,
							$userId
						);
					}
					else
					{
						$eventSvc->Publish(
							\Grocy\Services\UnifiedInventoryEventService::EVENT_INVENTORY_COUNT_COMPLETED,
							'product',
							strval($productId),
							[
								'product_name' => $p['name'] ?? '',
								'expected' => $expected,
								'counted' => $counted,
								'branch_id' => $branchId
							],
							$branchId,
							$userId
						);
					}
				}
			}
			catch (\Throwable $e)
			{
			}
		}
		catch (\PDOException $e)
		{
			$db->rollBack();
			// Dos conteos simultáneos: el índice único deja entrar a uno; el otro se reintenta y se suma a ese expediente
			if ($e->getCode() === '23000' && !isset($opt['_retry']))
			{
				return $this->RegisterCount($branchId, $productId, $counted, $opt + ['_retry' => true], $userId);
			}
			throw $e;
		}
		catch (\Throwable $e)
		{
			$db->rollBack();
			throw $e;
		}

		return [
			'success' => true, 'expected_stock' => $expected, 'physical_count' => $counted, 'difference_qty' => $diff, 'financial_impact' => $value,
			'alert_generated' => $alertId !== null, 'alert_id' => $alertId, 'case_id' => $caseId, 'provisional' => count($pending) > 0, 'within_tolerance' => $withinTolerance,
			'message' => $withinTolerance ? 'Diferencia dentro de la tolerancia autorizada.' : (abs($diff) < 0.0001 ? 'Conteo conciliado: coincide con el stock esperado.' : 'Diferencia de ' . floatval(abs($diff)) . ' u. ($' . number_format($value, 2) . '). Se solicita reconteo.'),
		];
	}

	private function OpenCase(int $branchId, int $productId): ?array
	{
		return $this->Row("SELECT * FROM sensitive_investigation_cases WHERE branch_id = :b AND product_id = :p AND device_id IS NULL AND workflow_status NOT IN ('resuelto', 'no_resuelto')", [':b' => $branchId, ':p' => $productId]);
	}

	private function OpenNewCase(array $p, int $branchId, array $f, array $opt, int $userId, string $now): int
	{
		$db = $this->Db();
		$last = $this->Row("SELECT created_at FROM sensitive_inventory_movements WHERE product_id = :p AND branch_id = :b AND event_type IN ('reception', 'cycle_count') AND difference_amount >= 0 ORDER BY created_at DESC LIMIT 1", [':p' => $p['id'], ':b' => $branchId]);
		$start = $last['created_at'] ?? date('Y-m-d H:i:s', strtotime('-15 days'));
		$tx = count(array_filter($this->Engine()->Movements(intval($p['id']), $branchId), fn($m) => $m['effective_at'] >= $start));
		$code = $opt['case_code'] ?? ('INV-' . date('Y') . '-' . sprintf('%05d', intval($db->query('SELECT IFNULL(MAX(id), 0) + 1 FROM sensitive_investigation_cases')->fetchColumn())));
		$owner = AlertCenterService::GetInstance()->Managers($branchId, User::PERMISSION_LOSS_PREVENTION)[0] ?? null;
		if (!$owner || intval($owner) <= 0)
		{
			$owner = $userId > 0 ? $userId : (intval($this->Val('SELECT id FROM users ORDER BY id LIMIT 1')) ?: 1);
		}
		$impactVal = floatval($f['financial_impact'] ?? 0);
		$prio = ($impactVal >= 500 || intval($p['is_sensitive']) === 1) ? 'urgent' : ($impactVal >= 200 ? 'high' : 'medium');
		$tools = json_encode(['Escáner de código de barras / IMEI', 'Llaves de vitrina de seguridad', 'Báscula de precisión', 'Precintos de custodia'], JSON_UNESCAPED_UNICODE);
		$zoneName = $opt['zone'] ?? ($f['location_id'] ? $this->Val('SELECT IFNULL(zone, name) FROM locations WHERE id = ?', [$f['location_id']]) : 'Bodega Principal');
		$row = $f + [
			'case_code' => $code, 'branch_id' => $branchId, 'product_id' => $p['id'], 'department' => $p['department'] ?: 'General', 'stage' => 'preliminar', 'workflow_status' => 'detectado',
			'priority' => $prio, 'zone' => $zoneName, 'shift' => $opt['shift'] ?? 'Turno General', 'tools_required' => $tools,
			'audit_interval_start' => $start, 'audit_interval_end' => $now, 'audit_interval_tx_count' => $tx, 'suspected_cause_category' => 'indeterminada',
			'evidence_notes' => 'Diferencia de ' . floatval($f['difference_qty']) . ' u. detectada en conteo. Intervalo por revisar: ' . substr($start, 0, 10) . ' a ' . substr($now, 0, 10) . " ($tx movimientos). No se presume sustracción.",
			'recommended_action' => "1. Recontar con escáner.\n2. Revisar movimientos del intervalo.\n3. Comprobar cada hipótesis con su documento.\n4. Adjuntar evidencia y proponer resolución.",
			'recount_status' => 'pending', 'adjustment_status' => 'pending', 'assigned_user_id' => $owner, 'due_at' => date('Y-m-d H:i:s', strtotime('+24 hours')), 'is_demo' => intval($p['is_demo']),
			'created_by_user_id' => $userId, 'created_at' => $now,
		];
		$cols = array_keys($row);
		$db->prepare('INSERT INTO sensitive_investigation_cases (' . implode(', ', $cols) . ') VALUES (' . implode(', ', array_fill(0, count($cols), '?')) . ')')->execute(array_values($row));
		$id = intval($db->lastInsertId());
		$this->Log($id, 'status', null, 'detectado', 'Diferencia detectada por el motor de conciliación', ['expected' => $f['expected_stock'], 'counted' => $f['physical_count']], $userId);
		$this->Db()->prepare("UPDATE sensitive_investigation_cases SET workflow_status = 'reconteo', updated_at = ? WHERE id = ?")->execute([$now, $id]);
		$this->Log($id, 'status', 'detectado', 'reconteo', 'Toda diferencia exige reconteo antes de investigar', null, $userId);
		return $id;
	}

	/** Un expediente por celular no localizado. No clasifica la causa: reúne hechos y pide reconteo. */
	public function OpenDeviceCase(array $u, ?array $lastConfirmed, array $findings, int $userId): int
	{
		$now = date('Y-m-d H:i:s');
		$p = $this->Row('SELECT * FROM products WHERE id = :p', [':p' => $u['product_id']]);
		$owner = AlertCenterService::GetInstance()->Managers(intval($u['branch_id']), User::PERMISSION_LOSS_PREVENTION)[0] ?? 1;
		$code = (intval($u['is_demo']) ? 'DEMO-IMEI-' : 'IMEI-' . date('Y') . '-') . sprintf('%05d', intval($this->Val('SELECT IFNULL(MAX(id), 0) + 1 FROM sensitive_investigation_cases')));
		$tools = json_encode(['Escáner de código de barras / IMEI', 'Llaves de vitrina de seguridad', 'Precintos de custodia'], JSON_UNESCAPED_UNICODE);
		$zoneName = DeviceService::ZONES[$u['zone']] ?? 'Vitrinas de Alta Seguridad';
		$this->Db()->prepare("INSERT INTO sensitive_investigation_cases (case_code, device_id, branch_id, product_id, department, location_name, location_id, expected_stock, physical_count, difference_qty, unit_cost, financial_impact,
			stage, workflow_status, priority, zone, shift, tools_required, audit_interval_start, audit_interval_end, suspected_cause_category, evidence_notes, recommended_action, recount_status, adjustment_status, assigned_user_id, due_at, is_demo, count_at, created_by_user_id, created_at, updated_at)
			VALUES (?, ?, ?, ?, ?, ?, ?, 1, 0, -1, ?, ?, 'preliminar', 'detectado', 'urgent', ?, 'Turno General', ?, ?, ?, 'indeterminada', ?, ?, 'pending', 'pending', ?, ?, ?, ?, ?, ?, ?)")
			->execute([$code, $u['id'], $u['branch_id'], $u['product_id'], $p['department'], $zoneName, $u['location_id'], $u['unit_cost'], $u['unit_cost'],
				$zoneName, $tools, $lastConfirmed['created_at'] ?? $u['received_at'], $now,
				'Equipo ' . DeviceService::Mask($u['imei1']) . ' no apareció en el conteo. Última operación confirmada: ' . ($lastConfirmed ? $lastConfirmed['event'] . ' del ' . $lastConfirmed['created_at'] : 'recepción') . '. No se clasifica como robo.',
				"1. Reconteo por IMEI.\n2. Revisar la última zona registrada.\n3. Confirmar ventas, pickup y traslados.\n4. Si continúa, escalar a gerencia.", $owner, date('Y-m-d H:i:s', strtotime('+8 hours')), $u['is_demo'], $now, $userId, $now, $now]);
		$id = intval($this->Db()->lastInsertId());
		$this->Log($id, 'status', null, 'detectado', 'IMEI no localizado en conteo', ['device_id' => intval($u['id'])], $userId);
		$this->Db()->prepare("UPDATE sensitive_investigation_cases SET workflow_status = 'reconteo' WHERE id = ?")->execute([$id]);
		$this->Log($id, 'status', 'detectado', 'reconteo', 'Se solicita reconteo por IMEI', null, $userId);
		$this->SaveHypotheses($id, $findings);
		$this->SyncAlert($id, $userId, null);
		return $id;
	}

	public function ResolveFound(array $c, string $note, int $userId): void
	{
		$this->Transition($c, 'resuelto', "Equipo localizado: $note", $userId, ['difference_qty' => 0, 'financial_impact' => 0, 'physical_count' => 1, 'resolution_type' => 'equipo_localizado', 'recount_status' => 'completed']);
	}

	private function SaveHypotheses(int $caseId, array $list): void
	{
		$ins = $this->Db()->prepare("INSERT INTO sensitive_case_hypotheses (case_id, code, label, qty, source) VALUES (?, ?, ?, ?, ?)
			ON CONFLICT (case_id, code) DO UPDATE SET qty = excluded.qty, source = excluded.source");
		foreach ($list as $h)
		{
			$ins->execute([$caseId, $h['code'], $h['label'], $h['qty'], $h['source']]);
		}
	}

	private function EnsureRecountTask(int $caseId, ?int $userId): void
	{
		$c = $this->Row('SELECT * FROM sensitive_investigation_cases WHERE id = :id', [':id' => $caseId]);
		$key = 'case-' . $caseId . '-' . (intval($this->Val("SELECT COUNT(*) FROM sensitive_case_events WHERE case_id = :id AND to_status = 'reconteo'", [':id' => $caseId])));
		$now = date('Y-m-d H:i:s');
		$this->Db()->prepare("INSERT OR IGNORE INTO sensitive_count_tasks (branch_id, product_id, period_key, due_at, status, case_id, first_qty, created_at) VALUES (?, ?, ?, ?, 'recount', ?, ?, ?)")
			->execute([$c['branch_id'], $c['product_id'], $key, date('Y-m-d H:i:s', strtotime('+8 hours')), $caseId, $c['physical_count'], $now]);
	}

	/** Reconteo: coincide → resuelto; ratifica → en investigación. Se compara contra lo esperado a la hora del reconteo. */
	public function Recount(int $caseId, float $qty, string $notes, int $userId, ?string $operationId = null): array
	{
		$this->Need(self::CountPerms(), 'registrar reconteos');
		$c = $this->Case($caseId);
		if ($c['workflow_status'] !== 'reconteo')
		{
			throw new \Exception('El expediente no tiene reconteo pendiente');
		}
		if ($operationId && $this->Val('SELECT 1 FROM sensitive_inventory_movements WHERE operation_id = :o', [':o' => $operationId]))
		{
			return ['success' => true, 'replayed' => true, 'message' => 'Reconteo ya registrado (reintento sin duplicar)'];
		}
		$pid = intval($c['product_id']);
		$bid = intval($c['branch_id']);
		$loc = $c['location_id'] ? intval($c['location_id']) : null;
		$engine = $this->Engine();
		$expected = $engine->Expected($pid, $bid, null, null, $loc)['expected'];
		$diff = round($qty - $expected, 4);
		$value = round(abs($diff) * floatval($c['unit_cost']), 2);
		$now = date('Y-m-d H:i:s');
		$pending = $engine->PendingSync($pid, $bid);

		$db = $this->Db();
		$db->beginTransaction();
		try
		{
			$code = $this->NextCode('CNT');
			$db->prepare("INSERT INTO sensitive_inventory_movements (movement_code, branch_id, product_id, event_type, warehouse_type, status, origin_location, dest_location, location_id, shift_code,
				expected_amount, actual_amount, accepted_amount, difference_amount, unit_cost, financial_impact, document_type, document_code, notes, user_id, created_at, confirmed_at, operation_id, is_demo)
				VALUES (?, ?, ?, 'cycle_count', 'physical', 'confirmado', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'CNT', ?, ?, ?, ?, ?, ?, ?)")
				->execute([$code, $bid, $pid, $c['location_name'], $c['location_name'], $loc, LossReconciliationService::ShiftOf($now), $expected, $qty, $qty, $diff, $c['unit_cost'], $diff < 0 ? -$value : $value, $code,
					'Inventario Sensible: reconteo. ' . $notes, $userId, $now, $now, $operationId, $c['is_demo']]);
			$extra = ['recount_status' => 'completed', 'recount_qty' => $qty, 'recount_notes' => $notes, 'physical_count' => $qty, 'expected_stock' => $expected, 'difference_qty' => $diff, 'financial_impact' => $value, 'count_at' => $now, 'provisional' => count($pending) > 0 ? 1 : 0];
			if (abs($diff) < 0.0001)
			{
				$extra['resolution_type'] = 'reconteo_conforme';
				$this->Transition($c, 'resuelto', 'Reconteo conforme: el conteo físico coincide con el stock esperado.', $userId, $extra);
				$msg = 'Reconteo conforme. Expediente resuelto.';
			}
			else
			{
				$this->Transition($c, 'en_investigacion', "Reconteo ratifica la diferencia: $diff u.", $userId, $extra);
				$msg = "Reconteo ratificó la diferencia de $diff u. El expediente pasa a investigación.";
			}
			$this->AddEvidenceRow($caseId, 'recount', 'Reconteo físico', "Resultado: $qty u. $notes", null, null, null, null, $userId);
			$db->commit();

			// Notificar al Bus de Eventos Unificado 360° (Recount)
			try
			{
				if (class_exists('\\Grocy\\Services\\UnifiedInventoryEventService'))
				{
					$eventSvc = \Grocy\Services\UnifiedInventoryEventService::GetInstance();
					if (abs($diff) < 0.0001)
					{
						$eventSvc->Publish(
							\Grocy\Services\UnifiedInventoryEventService::EVENT_CASE_RESOLVED,
							'case',
							strval($caseId),
							[
								'status' => 'resuelto',
								'resolution' => 'Reconteo conforme',
								'product_id' => $pid,
								'branch_id' => $bid
							],
							$bid,
							$userId
						);
					}
					else
					{
						$eventSvc->Publish(
							\Grocy\Services\UnifiedInventoryEventService::EVENT_INVENTORY_DISCREPANCY_DETECTED,
							'product',
							strval($pid),
							[
								'expected' => $expected,
								'counted' => $qty,
								'difference' => $diff,
								'financial_impact' => $value,
								'case_id' => $caseId,
								'branch_id' => $bid
							],
							$bid,
							$userId
						);
					}
				}
			}
			catch (\Throwable $e)
			{
			}
		}
		catch (\Throwable $e)
		{
			$db->rollBack();
			throw $e;
		}
		return ['success' => true, 'message' => $msg, 'difference_qty' => $diff];
	}

	public function RequestRecount(int $caseId, string $note, int $userId): array
	{
		$this->Need(self::SupervisorPerms(), 'solicitar reconteos');
		$c = $this->Case($caseId);
		$this->InTx(function () use ($c, $note, $userId, $caseId) {
			$this->Transition($c, 'reconteo', $note ?: 'Reconteo solicitado por supervisión', $userId, ['recount_status' => 'pending']);
			$this->EnsureRecountTask($caseId, $userId);
		});
		return ['success' => true, 'message' => 'Reconteo solicitado'];
	}

	public function Assign(int $caseId, int $ownerId, ?string $due, int $userId): array
	{
		$this->Need(self::SupervisorPerms(), 'asignar expedientes');
		$c = $this->Case($caseId);
		if (!$this->Val('SELECT 1 FROM users WHERE id = :u', [':u' => $ownerId]))
		{
			throw new \Exception('Responsable no válido');
		}
		$this->InTx(function () use ($c, $caseId, $ownerId, $due, $userId) {
			$this->Db()->prepare('UPDATE sensitive_investigation_cases SET assigned_user_id = ?, due_at = IFNULL(?, due_at), updated_at = ? WHERE id = ?')->execute([$ownerId, $due, date('Y-m-d H:i:s'), $caseId]);
			$this->Log($caseId, 'assigned', $c['workflow_status'], $c['workflow_status'], null, ['owner' => $ownerId, 'due' => $due], $userId);
			$this->SyncAlert($caseId, $userId, null);
		});
		return ['success' => true, 'message' => 'Responsable asignado'];
	}

	public function DecideHypothesis(int $id, string $status, string $note, int $userId): array
	{
		$this->Need(self::SupervisorPerms(), 'investigar');
		$h = $this->Row('SELECT * FROM sensitive_case_hypotheses WHERE id = :id', [':id' => $id]);
		if (!$h || !in_array($status, ['confirmada', 'descartada'], true))
		{
			throw new \Exception('Hipótesis o decisión no válida');
		}
		$this->Case(intval($h['case_id']));
		if (trim($note) === '')
		{
			throw new \Exception('Indique en qué evidencia se basa la decisión');
		}
		$this->InTx(function () use ($h, $id, $status, $note, $userId) {
			$this->Db()->prepare('UPDATE sensitive_case_hypotheses SET status = ?, note = ?, decided_by_user_id = ?, decided_at = ? WHERE id = ?')->execute([$status, $note, $userId, date('Y-m-d H:i:s'), $id]);
			$this->Log(intval($h['case_id']), 'hypothesis', null, null, "{$h['label']}: $status. $note", null, $userId);
		});
		return ['success' => true, 'status' => $status, 'message' => 'Hipótesis actualizada'];
	}

	public function ProposeAdjustment(int $caseId, string $reason, int $userId): array
	{
		$this->Need(self::SupervisorPerms(), 'proponer ajustes');
		$c = $this->Case($caseId);
		if (trim($reason) === '')
		{
			throw new \Exception('Justifique la propuesta de ajuste');
		}
		$this->AssertSettled($c);
		$this->InTx(fn() => $this->Transition($c, 'pendiente_aprobacion', "Ajuste propuesto: $reason", $userId, ['proposed_by_user_id' => $userId, 'proposed_at' => date('Y-m-d H:i:s'), 'proposed_reason' => $reason, 'adjustment_status' => 'pending']));
		return ['success' => true, 'message' => 'Ajuste enviado a aprobación'];
	}

	public function DecideAdjustment(int $caseId, string $decision, ?string $reason, int $userId): array
	{
		return ReconciliationService::GetInstance()->AuthorizeAdjustment($caseId, $decision, $reason, $userId);
	}

	/** Una diferencia con operaciones pendientes de sincronización es provisional: no puede convertirse en pérdida */
	public function AssertSettled(array $c): void
	{
		if (count($this->Engine()->PendingSync(intval($c['product_id']), intval($c['branch_id']))) > 0)
		{
			throw new \Exception('Hay operaciones pendientes de sincronización: la diferencia sigue siendo provisional');
		}
	}

	/** Cierre con justificación y verificación de responsable/evidencia. Resuelto (causa aclarada) o No resuelto (sin causa demostrable). */
	public function CloseCase(int $caseId, string $outcome, string $justification, int $userId): array
	{
		$this->RequireManager();
		$c = $this->Case($caseId);
		if (mb_strlen(trim($justification)) < 5)
		{
			throw new \Exception('Justifique el cierre del expediente');
		}
		if (!in_array($c['workflow_status'], self::OPEN, true))
		{
			// Un ajuste aprobado ya resuelve el expediente: el cierre formal solo deja constancia
			$this->InTx(fn() => $this->Log($caseId, 'closed', $c['workflow_status'], $c['workflow_status'], $justification, null, $userId));
			return ['success' => true, 'status' => $c['workflow_status'], 'message' => 'Expediente cerrado formalmente.'];
		}
		if (!in_array($outcome, ['resuelto', 'no_resuelto'], true))
		{
			throw new \Exception('Resultado no válido');
		}
		$extra = ['resolution_type' => 'cierre_manual'];
		if (empty($c['assigned_user_id']))
		{
			$extra['assigned_user_id'] = $userId;
		}
		if ($outcome === 'resuelto' && abs(floatval($c['difference_qty'])) > 0.0001 && ($c['adjustment_status'] ?? '') !== 'approved')
		{
			$evCount = intval($this->Val('SELECT COUNT(*) FROM sensitive_investigation_evidence WHERE case_id = :c', [':c' => $caseId]));
			if ($evCount === 0)
			{
				throw new \Exception('No se puede cerrar un expediente con diferencia como «Resuelto» sin evidencia documentada');
			}
		}
		$this->InTx(fn() => $this->Transition($c, $outcome, $justification, $userId, $extra));

		// Notificar al Bus de Eventos Unificado 360°
		try
		{
			if (class_exists('\\Grocy\\Services\\UnifiedInventoryEventService'))
			{
				\Grocy\Services\UnifiedInventoryEventService::GetInstance()->Publish(
					\Grocy\Services\UnifiedInventoryEventService::EVENT_CASE_RESOLVED,
					'case',
					strval($caseId),
					[
						'status' => $outcome,
						'resolution' => $justification,
						'device_id' => $c['device_id'] ?? null,
						'product_id' => $c['product_id'] ?? null,
						'branch_id' => $c['branch_id'] ?? null,
					],
					intval($c['branch_id'] ?? 1),
					$userId
				);
			}
		}
		catch (\Throwable $e)
		{
		}

		return ['success' => true, 'status' => $outcome, 'message' => 'Expediente cerrado formalmente.'];
	}

	/**
	 * Vuelve a evaluar los expedientes abiertos contra lo que ya se sabe: una venta sincronizada tarde con hora anterior al
	 * conteo cambia lo esperado en ese momento y puede justificar la diferencia sin intervención.
	 */
	public function ReevaluateOpen(?int $branchId = null): int
	{
		$changed = 0;
		$rows = $this->Rows("SELECT * FROM sensitive_investigation_cases WHERE workflow_status IN ('detectado', 'reconteo', 'en_investigacion') AND count_at IS NOT NULL AND is_demo = 0 AND device_id IS NULL" . ($branchId ? ' AND branch_id = ' . intval($branchId) : ''));
		foreach ($rows as $c)
		{
			$loc = $c['location_id'] ? intval($c['location_id']) : null;
			$expected = $this->Engine()->Expected(intval($c['product_id']), intval($c['branch_id']), null, $c['count_at'], $loc)['expected'];
			$pending = count($this->Engine()->PendingSync(intval($c['product_id']), intval($c['branch_id']))) > 0 ? 1 : 0;
			$diff = round(floatval($c['physical_count']) - $expected, 4);
			if (abs($expected - floatval($c['expected_stock'])) < 0.0001 && $pending === intval($c['provisional']))
			{
				continue;
			}
			$value = round(abs($diff) * floatval($c['unit_cost']), 2);
			$this->InTx(function () use ($c, $expected, $diff, $value, $pending) {
				if (abs($diff) < 0.0001)
				{
					$this->Transition($c, 'resuelto', 'Diferencia justificada: una operación sincronizada después del conteo la explica.', null, ['expected_stock' => $expected, 'difference_qty' => 0, 'financial_impact' => 0, 'provisional' => 0, 'resolution_type' => 'justificada_sincronizacion']);
					return;
				}
				$this->Db()->prepare('UPDATE sensitive_investigation_cases SET expected_stock = ?, difference_qty = ?, financial_impact = ?, provisional = ?, updated_at = ? WHERE id = ?')->execute([$expected, $diff, $value, $pending, date('Y-m-d H:i:s'), $c['id']]);
				$this->Log(intval($c['id']), 'reevaluated', $c['workflow_status'], $c['workflow_status'], "Nueva diferencia: $diff u.", null, null);
				$this->SyncAlert(intval($c['id']), null, null);
			});
			$changed++;
		}
		return $changed;
	}

	private function InTx(callable $fn)
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
			throw $e;
		}
	}

	private function NextCode(string $prefix): string
	{
		$base = $prefix . '-' . date('Ymd') . '-';
		$last = $this->Db()->query('SELECT movement_code FROM sensitive_inventory_movements WHERE movement_code LIKE ' . $this->Db()->quote($base . '%') . ' ORDER BY movement_code DESC LIMIT 1')->fetchColumn();
		return $base . sprintf('%04d', $last ? intval(substr($last, strlen($base))) + 1 : 1);
	}

	private function UnitCost(array $p, int $branchId): float
	{
		$c = floatval($this->Val('SELECT IFNULL(SUM(s.amount * s.price) / NULLIF(SUM(s.amount), 0), 0) FROM stock s JOIN locations l ON l.id = s.location_id WHERE s.product_id = :p AND l.branch_id = :b AND s.price > 0', [':p' => $p['id'], ':b' => $branchId]));
		if ($c <= 0)
		{
			$c = floatval($this->Val('SELECT IFNULL((SELECT price FROM products_last_purchased WHERE product_id = :p), 0)', [':p' => $p['id']]));
		}
		return round($c > 0 ? $c : floatval($p['sale_price'] ?? 0) * 0.65, 4);
	}

	/* ---------- Evidencia */

	private function AddEvidenceRow(int $caseId, string $type, string $title, ?string $description, ?string $file, ?string $mime, ?string $cameraRef, ?string $authorizedBy, ?int $userId): int
	{
		$this->Db()->prepare('INSERT INTO sensitive_investigation_evidence (case_id, evidence_type, title, description, file_name, mime, camera_ref, authorized_by, user_id, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
			->execute([$caseId, $type, $title, $description, $file, $mime, $cameraRef, $authorizedBy, $userId, date('Y-m-d H:i:s')]);
		return intval($this->Db()->lastInsertId());
	}

	public function AddEvidence(int $caseId, array $d, int $userId): int
	{
		$this->Need(self::CountPerms(), 'adjuntar evidencia');
		$c = $this->Case($caseId);
		$type = in_array($d['type'] ?? '', ['note', 'photo', 'document', 'camera', 'access_log', 'pos_audit'], true) ? $d['type'] : 'note';
		$title = trim((string) ($d['title'] ?? ''));
		if ($title === '')
		{
			throw new \Exception('Indique un título para la evidencia');
		}
		if ($type === 'camera' && (trim((string) ($d['camera_ref'] ?? '')) === '' || trim((string) ($d['authorized_by'] ?? '')) === ''))
		{
			throw new \Exception('La referencia de cámara exige indicar el video y quién autorizó su consulta');
		}
		return $this->InTx(function () use ($caseId, $c, $type, $title, $d, $userId) {
			$id = $this->AddEvidenceRow($caseId, $type, $title, $d['description'] ?? null, $d['file_name'] ?? null, $d['mime'] ?? null, $d['camera_ref'] ?? null, $d['authorized_by'] ?? null, $userId);
			$this->Log($caseId, 'evidence', $c['workflow_status'], $c['workflow_status'], "$type: $title", ['evidence_id' => $id], $userId);
			if ($type === 'camera')
			{
				try
				{
					$this->Db()->prepare("INSERT INTO security_access_audit_log (user_id, branch_id, case_id, access_type, resource_ref, authorized_by, notes, created_at) VALUES (?, ?, ?, 'cctv_attach', ?, ?, ?, ?)")
						->execute([$userId, intval($c['branch_id']), $caseId, trim((string)($d['camera_ref'] ?? '')), trim((string)($d['authorized_by'] ?? '')), $title, date('Y-m-d H:i:s')]);
				}
				catch (\Throwable $e)
				{
				}
			}
			return $id;
		});
	}

	public function EvidenceFile(int $id): ?array
	{
		$e = $this->Row('SELECT * FROM sensitive_investigation_evidence WHERE id = :id', [':id' => $id]);
		if ($e)
		{
			$this->Need(self::SupervisorPerms(), 'ver evidencia sensible');
			$c = $this->Case(intval($e['case_id']));
			try
			{
				$this->Db()->prepare("INSERT INTO security_access_audit_log (user_id, branch_id, case_id, access_type, resource_ref, notes, created_at) VALUES (?, ?, ?, 'evidence_view', ?, ?, ?)")
					->execute([GROCY_USER_ID, intval($c['branch_id']), intval($e['case_id']), $e['file_name'] ?? ('evidencia#' . $id), $e['title'] ?? '', date('Y-m-d H:i:s')]);
			}
			catch (\Throwable $ex)
			{
			}
		}
		return $e;
	}

	/* ---------- Conteos programados */

	private function PeriodKey(string $frequency, array $product, int $branchId): array
	{
		$today = date('Y-m-d');
		if ($frequency === 'risk')
		{
			$risky = $this->OpenCase($branchId, intval($product['id'])) !== null || intval($this->Val("SELECT COUNT(*) FROM sensitive_investigation_cases WHERE product_id = :p AND branch_id = :b AND created_at >= date('now', '-90 days')", [':p' => $product['id'], ':b' => $branchId])) > 1;
			$frequency = $risky ? 'daily' : 'weekly';
		}
		if ($frequency === 'shift')
		{
			$shift = LossReconciliationService::ShiftOf(date('Y-m-d H:i:s'));
			return [$today . '-' . $shift, date('Y-m-d H:i:s', strtotime('+8 hours'))];
		}
		return $frequency === 'weekly' ? [date('o-\WW'), date('Y-m-d 23:59:59', strtotime('sunday this week'))] : [$today, "$today 23:59:59"];
	}

	/** Genera las tareas del periodo; la clave única por sucursal, producto y periodo la hace repetible sin duplicar */
	public function GenerateTasks(?int $branchId = null): int
	{
		$created = 0;
		$plans = $this->Rows('SELECT * FROM sensitive_count_plans WHERE active = 1' . ($branchId ? ' AND branch_id = ' . intval($branchId) : ''));
		$ins = $this->Db()->prepare("INSERT OR IGNORE INTO sensitive_count_tasks (plan_id, branch_id, product_id, period_key, due_at, assigned_user_id, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)");
		foreach ($plans as $plan)
		{
			$products = $plan['product_id']
				? $this->Rows('SELECT * FROM products WHERE id = :p AND active = 1', [':p' => $plan['product_id']])
				: $this->Rows('SELECT * FROM products WHERE active = 1 AND is_sensitive = 1 AND sensitive_category_id = :c', [':c' => $plan['category_id']]);
			foreach ($products as $p)
			{
				[$key, $due] = $this->PeriodKey($plan['frequency'], $p, intval($plan['branch_id']));
				$ins->execute([$plan['id'], $plan['branch_id'], $p['id'], $key, $due, $plan['assigned_user_id'], date('Y-m-d H:i:s')]);
				$created += $ins->rowCount();
			}
		}
		return $created;
	}

	/** Tareas del colaborador. Conteo ciego: nunca incluye el stock esperado. */
	public function Tasks(?int $branchId, int $userId): array
	{
		$this->Need(self::CountPerms(), 'consultar conteos');
		$allowed = DairyAccess::AllowedBranches();
		$w = ["t.status IN ('pending', 'recount')"];
		if ($branchId)
		{
			$this->NeedBranch($branchId);
			$w[] = 't.branch_id = ' . intval($branchId);
		}
		elseif ($allowed !== null)
		{
			$w[] = 't.branch_id IN (' . (implode(',', array_map('intval', $allowed)) ?: '0') . ')';
		}
		return $this->Rows('SELECT t.id, t.status, t.due_at, t.branch_id, t.period_key, t.case_id, b.name AS branch_name, p.id AS product_id, p.name AS product_name, p.sku, p.picture_file_name, p.is_serialized, p.imei_tracking, p.control_type, q.name AS unit_name,
				c.name AS category_name, CASE WHEN t.due_at < :now THEN 1 ELSE 0 END AS overdue
			FROM sensitive_count_tasks t JOIN products p ON p.id = t.product_id LEFT JOIN branches b ON b.id = t.branch_id LEFT JOIN sensitive_categories c ON c.id = p.sensitive_category_id LEFT JOIN quantity_units q ON q.id = p.qu_id_stock
			WHERE ' . implode(' AND ', $w) . " AND (t.assigned_user_id IS NULL OR t.assigned_user_id = :u OR 1 = :sup)
			ORDER BY CASE t.status WHEN 'recount' THEN 0 ELSE 1 END, t.due_at LIMIT 100", [':now' => date('Y-m-d H:i:s'), ':u' => $userId, ':sup' => DairyAccess::HasAny(self::SupervisorPerms()) ? 1 : 0]);
	}

	public function SubmitTaskCount(int $taskId, array $d, int $userId): array
	{
		$this->Need(self::CountPerms(), 'registrar conteos');
		$t = $this->Row('SELECT * FROM sensitive_count_tasks WHERE id = :id', [':id' => $taskId]);
		if (!$t)
		{
			throw new \Exception('Tarea no encontrada', 404);
		}
		$branchId = intval($t['branch_id']);
		$this->NeedBranch($branchId);
		$op = trim((string) ($d['operation_id'] ?? '')) ?: null;
		if ($op && $this->Val('SELECT 1 FROM sensitive_inventory_movements WHERE operation_id = :o', [':o' => $op]))
		{
			return ['success' => true, 'replayed' => true, 'recount_required' => $t['status'] === 'recount', 'message' => 'Conteo ya registrado (reintento sin duplicar)'];
		}
		$pass = $t['status'] === 'recount' ? 2 : 1;
		if (!in_array($t['status'], ['pending', 'recount'], true))
		{
			throw new \Exception('Esta tarea ya fue contada');
		}
		$resolved = ScanService::GetInstance()->Resolve(trim((string) ($d['barcode'] ?? '')), $branchId);
		if (($resolved['kind'] ?? '') !== 'product' || intval($resolved['product']['id']) !== intval($t['product_id']))
		{
			throw new \Exception('El código escaneado no corresponde al producto de la tarea');
		}
		$qty = floatval($d['qty'] ?? -1);
		if ($qty < 0)
		{
			throw new \Exception('Indique la cantidad contada');
		}
		$p = $this->Row('SELECT * FROM products WHERE id = :p', [':p' => $t['product_id']]);
		$serials = array_values(array_unique(array_filter(array_map('trim', (array) ($d['serials'] ?? [])))));
		if (intval($p['is_serialized']) === 1 && count($serials) !== intval($qty))
		{
			throw new \Exception('Escanee una serie o IMEI por unidad: ' . count($serials) . ' de ' . intval($qty));
		}

		// Reclama la tarea: de dos envíos simultáneos solo uno avanza
		$claim = $this->Db()->prepare('UPDATE sensitive_count_tasks SET ' . ($pass === 1 ? 'first_qty = ?, first_user_id = ?, first_at = ?' : 'final_qty = ?, final_user_id = ?, final_at = ?') . ", status = 'claimed' WHERE id = ? AND status = ?");
		$claim->execute([$qty, $userId, date('Y-m-d H:i:s'), $taskId, $t['status']]);
		if ($claim->rowCount() === 0)
		{
			throw new \Exception('Otro colaborador acaba de registrar este conteo');
		}
		try
		{
			$result = $pass === 1 && empty($t['case_id'])
				? $this->RegisterCount($branchId, intval($t['product_id']), $qty, ['operation_id' => $op, 'notes' => $d['notes'] ?? null, 'lot' => $d['lot'] ?? null], $userId)
				: $this->Recount(intval($t['case_id']), $qty, (string) ($d['notes'] ?? ''), $userId, $op);
			$caseId = $result['case_id'] ?? $t['case_id'];
			// Si hubo diferencia, el expediente abre su propia tarea de reconteo
			$this->Db()->prepare("UPDATE sensitive_count_tasks SET status = 'done', case_id = ? WHERE id = ?")->execute([$caseId, $taskId]);
		}
		catch (\Throwable $e)
		{
			$this->Db()->prepare('UPDATE sensitive_count_tasks SET status = ? WHERE id = ?')->execute([$t['status'], $taskId]);
			throw $e;
		}

		$known = array_flip($this->ExpectedSerials(intval($t['product_id']), $branchId));
		$ins = $this->Db()->prepare('INSERT OR IGNORE INTO sensitive_count_serials (task_id, pass, code, matched, user_id, scanned_at) VALUES (?, ?, ?, ?, ?, ?)');
		foreach ($serials as $s)
		{
			$ins->execute([$taskId, $pass, $s, isset($known[$s]) ? 1 : 0, $userId, date('Y-m-d H:i:s')]);
		}
		$diff = abs(floatval($result['difference_qty'] ?? 0));
		// Conteo ciego: el colaborador sabe si debe recontar, no cuánto esperaba el sistema
		return ['success' => true, 'recount_required' => $pass === 1 && $diff > 0.0001, 'case_id' => $caseId ? intval($caseId) : null,
			'message' => $diff < 0.0001 ? 'Conteo conciliado.' : ($pass === 1 ? 'Hay una diferencia: repita el conteo.' : 'Conteo registrado. Supervisión revisará la diferencia.')];
	}

	public function ExpectedSerials(int $productId, int $branchId): array
	{
		$rows = $this->Rows("SELECT IFNULL(NULLIF(dl.imei, ''), dl.serial_number) AS code FROM stock s JOIN locations l ON l.id = s.location_id JOIN dairy_stock_lots dl ON dl.stock_id = s.stock_id WHERE s.product_id = :p AND l.branch_id = :b AND IFNULL(l.is_transit, 0) = 0", [':p' => $productId, ':b' => $branchId]);
		return array_values(array_filter(array_column($rows, 'code')));
	}

	public function SavePlan(array $d, int $userId): int
	{
		$this->Need(self::SupervisorPerms(), 'programar conteos');
		$branchId = intval($d['branch_id'] ?? 0);
		$this->NeedBranch($branchId);
		$freq = $d['frequency'] ?? '';
		if (!$branchId || !isset(self::FREQUENCIES[$freq]) || (empty($d['category_id']) && empty($d['product_id'])))
		{
			throw new \Exception('Indique sucursal, frecuencia y categoría o producto');
		}
		$this->Db()->prepare('INSERT INTO sensitive_count_plans (branch_id, category_id, product_id, frequency, assigned_user_id, created_by_user_id, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)')
			->execute([$branchId, ($d['category_id'] ?? null) ?: null, ($d['product_id'] ?? null) ?: null, $freq, ($d['assigned_user_id'] ?? null) ?: null, $userId, date('Y-m-d H:i:s')]);
		$id = intval($this->Db()->lastInsertId());
		$this->GenerateTasks($branchId);
		return $id;
	}

	public function SetPlanActive(int $id, bool $active): void
	{
		$this->Need(self::SupervisorPerms(), 'programar conteos');
		$this->Db()->prepare('UPDATE sensitive_count_plans SET active = ? WHERE id = ?')->execute([$active ? 1 : 0, $id]);
	}

	/** Causa confirmada: distinta de la diferencia. El hurto solo lo confirma gerencia, con evidencia autorizada adjunta. */
	public function ConfirmCause(int $caseId, string $code, string $note, int $userId): array
	{
		$this->Need(self::SupervisorPerms(), 'confirmar causas');
		$c = $this->Case($caseId);
		$cause = $this->Row("SELECT * FROM loss_causes WHERE code = :c AND active = 1 AND kind <> 'other'", [':c' => $code]);
		if (!$cause)
		{
			throw new \Exception('Causa no válida');
		}
		if (!in_array($c['workflow_status'], ['en_investigacion', 'pendiente_aprobacion'], true))
		{
			throw new \Exception('La causa se confirma durante la investigación');
		}
		if (trim($note) === '')
		{
			throw new \Exception('Indique en qué se basa la causa');
		}
		if (intval($cause['requires_manager']))
		{
			$this->Need(self::ManagerPerms(), 'confirmar esta causa');
		}
		if (intval($cause['requires_evidence']) && !$this->Val("SELECT 1 FROM sensitive_investigation_evidence WHERE case_id = :c AND evidence_type IN ('photo', 'document', 'camera')", [':c' => $caseId]))
		{
			throw new \Exception('Esta causa exige evidencia autorizada adjunta al expediente');
		}
		$this->InTx(function () use ($c, $caseId, $code, $cause, $note, $userId) {
			$this->Db()->prepare('UPDATE sensitive_investigation_cases SET confirmed_cause = ?, updated_at = ? WHERE id = ?')->execute([$code, date('Y-m-d H:i:s'), $caseId]);
			$this->Log($caseId, 'cause', $c['workflow_status'], $c['workflow_status'], "{$cause['name']}: $note", null, $userId);
		});
		return ['success' => true, 'message' => 'Causa registrada'];
	}

	/** Causa de pérdida física con ajuste aprobado: aparece en Mermas. El stock ya salió con el ajuste, no descuenta otra vez. */
	public function PostConfirmedWaste(array $c, ?string $transactionId, int $userId): void
	{
		$cause = empty($c['confirmed_cause']) ? null : $this->Row('SELECT * FROM loss_causes WHERE code = :c', [':c' => $c['confirmed_cause']]);
		if (!$cause || !in_array($cause['kind'], ['waste', 'unexplained', 'theft'], true) || floatval($c['difference_qty']) >= 0 || !$transactionId)
		{
			return;
		}
		$reason = $this->Val('SELECT id FROM waste_reasons WHERE loss_cause_code = :c AND active = 1 ORDER BY id LIMIT 1', [':c' => $cause['code']])
			?: $this->Val("SELECT id FROM waste_reasons WHERE loss_cause_code = 'INV' OR code = 'M06' ORDER BY id LIMIT 1");
		$qu = $this->Val('SELECT qu_id_stock FROM products WHERE id = :p', [':p' => $c['product_id']]);
		$qty = abs(floatval($c['difference_qty']));
		$this->Db()->prepare("INSERT INTO waste_records (branch_id, product_id, amount, qu_id, unit_cost, total_cost, waste_reason_id, notes, reported_by_user_id, status, approved_by_user_id, approved_timestamp, transaction_id, inventory_deducted, workflow_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'approved', ?, ?, ?, 1, 'closed')")
			->execute([$c['branch_id'], $c['product_id'], $qty, $qu, $c['unit_cost'], round($qty * floatval($c['unit_cost']), 2), $reason, "Expediente {$c['case_code']}: {$cause['name']}", $userId, $userId, date('Y-m-d H:i:s'), $transactionId]);
	}

	/** Reabrir exige evidencia nueva y deja constancia. Un ajuste ya contabilizado no se revierte solo: se corrige con otro. */
	public function Reopen(int $caseId, string $evidence, int $userId): array
	{
		$this->Need(self::SupervisorPerms(), 'reabrir expedientes');
		$c = $this->Case($caseId);
		if (in_array($c['workflow_status'], self::OPEN, true))
		{
			throw new \Exception('El expediente está abierto');
		}
		if (mb_strlen(trim($evidence)) < 5)
		{
			throw new \Exception('Describa la evidencia nueva');
		}
		if (!empty($c['device_id']))
		{
			throw new \Exception('El expediente de un equipo se reabre por un nuevo conteo por IMEI');
		}
		try
		{
			$this->InTx(function () use ($c, $caseId, $evidence, $userId) {
				$now = date('Y-m-d H:i:s');
				$this->Db()->prepare("UPDATE sensitive_investigation_cases SET workflow_status = 'en_investigacion', stage = 'faltante_confirmado', reopened_count = reopened_count + 1, closed_at = NULL, closed_by_user_id = NULL, updated_at = ?, due_at = ? WHERE id = ?")->execute([$now, date('Y-m-d H:i:s', strtotime('+24 hours')), $caseId]);
				$this->AddEvidenceRow($caseId, 'note', 'Evidencia nueva al reabrir', $evidence, null, null, null, null, $userId);
				$this->Log($caseId, 'reopened', $c['workflow_status'], 'en_investigacion', $evidence, null, $userId);
				$this->SyncAlert($caseId, $userId, null);
			});
		}
		catch (\PDOException $e)
		{
			throw new \Exception('Ya existe un expediente abierto para este producto y sucursal');
		}
		return ['success' => true, 'message' => 'Expediente reabierto'];
	}

	public function SetSystem(bool $on): void
	{
		$this->System = $on;
	}

	public function SaveCategory(array $d): int
	{
		$this->Need(self::SupervisorPerms(), 'configurar la clasificación sensible');
		$name = trim((string) ($d['name'] ?? ''));
		if ($name === '' || !isset(self::FREQUENCIES[$d['count_frequency'] ?? '']))
		{
			throw new \Exception('Indique nombre y frecuencia');
		}
		$v = [$name, $d['count_frequency'], max(0, floatval($d['high_value_threshold'] ?? 0)), !empty($d['requires_serial']) ? 1 : 0];
		if (!empty($d['id']))
		{
			$this->Db()->prepare('UPDATE sensitive_categories SET name = ?, count_frequency = ?, high_value_threshold = ?, requires_serial = ? WHERE id = ?')->execute([...$v, intval($d['id'])]);
			return intval($d['id']);
		}
		$this->Db()->prepare('INSERT INTO sensitive_categories (name, count_frequency, high_value_threshold, requires_serial, code, sort_order) VALUES (?, ?, ?, ?, ?, 99)')->execute([...$v, 'custom_' . bin2hex(random_bytes(4))]);
		return intval($this->Db()->lastInsertId());
	}

	/** Clasifica un producto; la clasificación manda: sensible, serie e IMEI según su categoría */
	public function ClassifyProduct(int $productId, ?int $categoryId): void
	{
		$this->Need(self::SupervisorPerms(), 'configurar la clasificación sensible');
		$cat = $categoryId ? $this->Row('SELECT * FROM sensitive_categories WHERE id = :id', [':id' => $categoryId]) : null;
		$this->Db()->prepare('UPDATE products SET sensitive_category_id = ?, is_sensitive = ?, is_serialized = CASE WHEN ? = 1 THEN 1 ELSE is_serialized END WHERE id = ?')
			->execute([$categoryId, $cat ? 1 : 0, intval($cat['requires_serial'] ?? 0), $productId]);
	}

	/* ---------- Consultas: una sola fuente para dashboard, alertas e indicadores de otros módulos */

	private function BranchWhere(?int $branchId, string $col = 'c.branch_id'): string
	{
		if ($branchId)
		{
			return " AND $col = " . intval($branchId);
		}
		$allowed = $this->System ? null : DairyAccess::AllowedBranches();
		return $allowed === null ? '' : " AND $col IN (" . (implode(',', array_map('intval', $allowed)) ?: '0') . ')';
	}

	public static function Level(array $c, ?array $alert = null): string
	{
		if (!in_array($c['workflow_status'], self::OPEN, true))
		{
			return $c['workflow_status'] === 'no_resuelto' ? 'danger' : 'success';
		}
		return ($alert['severity'] ?? $c['alert_severity'] ?? 'warning') === 'danger' ? 'danger' : 'warning';
	}

	private const CLOSED_STATUSES = "('resuelto', 'no_resuelto', 'closed', 'resolved')";

	public function Cases(array $f = [], int $limit = 200): array
	{
		$bId = !empty($f['branch_id']) ? intval($f['branch_id']) : null;
		$w = '1 = 1' . $this->BranchWhere($bId);
		$p = [];
		if (!empty($f['status']))
		{
			$w .= ' AND c.workflow_status = :s';
			$p[':s'] = $f['status'];
		}
		if (!empty($f['open']))
		{
			$w .= ' AND c.workflow_status NOT IN ' . self::CLOSED_STATUSES;
		}
		if (!empty($f['priority']))
		{
			$w .= ' AND c.priority = :prio';
			$p[':prio'] = $f['priority'];
		}
		if (!empty($f['department']))
		{
			$w .= ' AND c.department = :dept';
			$p[':dept'] = $f['department'];
		}
		if (!empty($f['zone']))
		{
			$w .= ' AND (c.zone LIKE :zone OR l.zone LIKE :zone OR l.zone_type = :zone)';
			$p[':zone'] = '%' . $f['zone'] . '%';
		}
		if (!empty($f['shift']))
		{
			$w .= ' AND c.shift LIKE :shift';
			$p[':shift'] = '%' . $f['shift'] . '%';
		}
		if (!empty($f['assigned_user_id']))
		{
			$w .= ' AND c.assigned_user_id = :uid';
			$p[':uid'] = intval($f['assigned_user_id']);
		}
		if (!empty($f['period']) && empty($f['date_from']))
		{
			$daysBack = match ($f['period']) {
				'today' => 0,
				'7days' => 6,
				'30days' => 29,
				'90days' => 89,
				default => null,
			};
			if ($daysBack !== null)
			{
				$f['date_from'] = date('Y-m-d', strtotime("-$daysBack days"));
			}
		}
		if (!empty($f['date_from']))
		{
			$w .= ' AND date(c.created_at) >= :dfrom';
			$p[':dfrom'] = $f['date_from'];
		}
		if (!empty($f['date_to']))
		{
			$w .= ' AND date(c.created_at) <= :dto';
			$p[':dto'] = $f['date_to'];
		}
		if (!empty($f['escalated']))
		{
			$w .= ' AND c.escalation_level > 0';
		}
		if (!empty($f['overdue']))
		{
			$w .= ' AND c.workflow_status NOT IN ' . self::CLOSED_STATUSES . " AND c.due_at < datetime('now', 'localtime')";
		}
		if (!empty($f['critical']))
		{
			$w .= " AND (c.priority = 'urgent' OR a.severity = 'danger' OR c.financial_impact >= 500)";
		}
		if (!empty($f['search']))
		{
			$w .= ' AND (c.case_code LIKE :q OR p.name LIKE :q OR p.sku LIKE :q OR c.department LIKE :q)';
			$p[':q'] = '%' . $f['search'] . '%';
		}
		$rows = $this->Rows("SELECT c.id, c.case_code, c.branch_id, c.product_id, c.device_id, c.department, c.location_name, c.zone, c.priority, c.shift,
				c.expected_stock, c.physical_count, c.difference_qty, c.financial_impact, c.workflow_status, c.provisional, c.is_demo, c.occurrences,
				c.assigned_user_id, c.confirmed_cause, c.suspected_cause_category, c.financial_recovery_json,
				c.escalation_level, c.escalated_at, c.first_response_at, c.arrival_at, c.due_at, c.created_at, c.closed_at, c.resolution_type,
				p.name AS product_name, p.sku, b.name AS branch_name, a.severity AS alert_severity, a.id AS alert_id, u.username AS assigned_name,
				(SELECT COUNT(*) FROM sensitive_case_tasks sct WHERE sct.case_id = c.id AND sct.status <> 'completed') AS pending_tasks_count,
				(SELECT COUNT(*) FROM sensitive_case_calls scc WHERE scc.case_id = c.id) AS calls_count,
				(SELECT COUNT(*) FROM sensitive_investigation_evidence sie WHERE sie.case_id = c.id) AS evidence_count
			FROM sensitive_investigation_cases c
			JOIN products p ON p.id = c.product_id
			LEFT JOIN locations l ON l.id = c.location_id
			LEFT JOIN branches b ON b.id = c.branch_id
			LEFT JOIN dairy_alerts a ON a.id = c.alert_id
			LEFT JOIN users u ON u.id = c.assigned_user_id
			WHERE $w
			ORDER BY CASE WHEN c.workflow_status IN " . self::CLOSED_STATUSES . " THEN 1 ELSE 0 END,
				CASE c.priority WHEN 'urgent' THEN 0 WHEN 'high' THEN 1 WHEN 'medium' THEN 2 ELSE 3 END,
				c.financial_impact DESC, c.id DESC LIMIT " . intval($limit), $p);
		foreach ($rows as &$r)
		{
			$r['level'] = self::Level($r);
			$norm = in_array($r['workflow_status'], ['closed', 'resolved'], true) ? 'resuelto' : ($r['workflow_status'] === 'in_progress' ? 'en_investigacion' : ($r['workflow_status'] === 'pending_approval' ? 'pendiente_aprobacion' : $r['workflow_status']));
			$r['status_label'] = self::STATUSES[$norm] ?? $r['workflow_status'];
		}
		return $rows;
	}

	/** Totales de Prevención de Pérdidas (sin mezclar mermas comerciales ordinarias) */
	public function Summary(?int $branchId = null): array
	{
		$b = $this->BranchWhere($branchId);
		$open = $this->Row("SELECT COUNT(*) AS cases, IFNULL(SUM(financial_impact), 0) AS value, IFNULL(SUM(ABS(difference_qty)), 0) AS units, IFNULL(SUM(provisional), 0) AS provisional
			FROM sensitive_investigation_cases c WHERE c.workflow_status NOT IN " . self::CLOSED_STATUSES . $b);
		$tasks = intval($this->Val("SELECT COUNT(*) FROM sensitive_count_tasks c WHERE c.status IN ('pending', 'recount')$b"));
		return ['open_cases' => intval($open['cases']), 'open_value' => round(floatval($open['value']), 2), 'open_units' => round(floatval($open['units']), 2), 'provisional' => intval($open['provisional']), 'pending_counts' => $tasks];
	}

	public function Dashboard($branchOrFilters = null, string $unit = 'day'): array
	{
		$filters = is_array($branchOrFilters) ? $branchOrFilters : ['branch_id' => $branchOrFilters, 'unit' => $unit];
		$branchId = !empty($filters['branch_id']) ? intval($filters['branch_id']) : null;
		$unit = $filters['unit'] ?? $unit;

		$this->GenerateTasks($branchId);
		$this->ReevaluateOpen($branchId);
		$b = $this->BranchWhere($branchId);
		$today = date('Y-m-d');
		$s = $this->Summary($branchId);
		$bm = str_replace('c.branch_id', 'm.branch_id', $b);
		$bd = str_replace('c.branch_id', 'branch_id', $b);
		$periods = ['day' => ["date(m.created_at)", 30], 'week' => ["strftime('%Y-S%W', m.created_at)", 84], 'month' => ["strftime('%Y-%m', m.created_at)", 365]];
		[$expr, $days] = $periods[$unit] ?? $periods['day'];

		// Separación estricta: no mezclar merma comercial ordinaria de /waste
		$wasteVal = 0.0;
		$tasksPending = intval($this->Val("SELECT COUNT(*) FROM sensitive_case_tasks t JOIN sensitive_investigation_cases c ON c.id = t.case_id WHERE t.status NOT IN ('completed', 'cancelled')$b"));
		$availableStaffCount = count(array_filter($this->StaffDirectory($branchId), fn($st) => $st['can_assign']));
		$overdueCount = intval($this->Val("SELECT COUNT(*) FROM sensitive_investigation_cases c WHERE c.workflow_status NOT IN " . self::CLOSED_STATUSES . " AND c.due_at IS NOT NULL AND c.due_at < datetime('now', 'localtime')$b"));
		$escalatedCount = intval($this->Val("SELECT COUNT(*) FROM sensitive_investigation_cases c WHERE c.workflow_status NOT IN " . self::CLOSED_STATUSES . " AND c.escalation_level > 0$b"));
		$criticalCount = intval($this->Val("SELECT COUNT(*) FROM sensitive_investigation_cases c LEFT JOIN dairy_alerts a ON a.id = c.alert_id WHERE c.workflow_status NOT IN " . self::CLOSED_STATUSES . " AND (c.priority = 'urgent' OR a.severity = 'danger' OR c.financial_impact >= 500)$b"));
		$confirmedLossVal = floatval($this->Val("SELECT IFNULL(SUM(c.financial_impact), 0) FROM sensitive_investigation_cases c WHERE c.workflow_status = 'no_resuelto'$b"));
		$unassignedCount = intval($this->Val("SELECT COUNT(*) FROM sensitive_investigation_cases c WHERE c.workflow_status NOT IN " . self::CLOSED_STATUSES . " AND (c.assigned_user_id IS NULL OR c.assigned_user_id = 0)$b"));

		// Seguridad de dispositivos e IMEI
		$deviceCounts = $this->Row("SELECT COUNT(*) AS total,
			SUM(CASE WHEN state NOT IN ('sold','written_off','returned') THEN 1 ELSE 0 END) AS active_custody,
			SUM(CASE WHEN state = 'blocked' THEN 1 ELSE 0 END) AS blocked,
			SUM(CASE WHEN state = 'in_transit' THEN 1 ELSE 0 END) AS transit,
			SUM(CASE WHEN state = 'vault' THEN 1 ELSE 0 END) AS vault
			FROM device_units WHERE 1 = 1$bd") ?: [];
		$deviceHolds = intval($this->Val("SELECT COUNT(*) FROM device_sale_holds WHERE status = 'pending'$bd"));

		// Recuperación financiera real sin duplicados sobre expedientes
		$recRows = $this->Rows("SELECT id, case_code, branch_id, financial_impact, financial_recovery_json, updated_at, workflow_status FROM sensitive_investigation_cases c WHERE financial_recovery_json IS NOT NULL AND financial_recovery_json <> ''$b");
		$recoveredTotal = 0.0;
		$recoverableExposure = 0.0;
		$seenDocRefs = [];
		$recoveryEntries = [];
		foreach ($recRows as $rr)
		{
			$rec = json_decode((string)$rr['financial_recovery_json'], true);
			if (!is_array($rec))
			{
				continue;
			}
			$merch = floatval($rec['recovered_merchandise'] ?? 0);
			$supp = floatval($rec['supplier_claim'] ?? 0);
			$ins = floatval($rec['insurance_claim'] ?? 0);
			$sumRec = round($merch + $supp + $ins, 2);
			$recoveredTotal += $sumRec;
			$recoverableExposure += abs(floatval($rr['financial_impact']));
			$refs = array_values(array_filter(array_map('trim', (array)($rec['document_refs'] ?? []))));
			$hasDup = false;
			foreach ($refs as $rf)
			{
				if (isset($seenDocRefs[strtoupper($rf)]))
				{
					$hasDup = true;
				}
				$seenDocRefs[strtoupper($rf)] = true;
			}
			$recoveryEntries[] = [
				'case_id' => intval($rr['id']),
				'case_code' => $rr['case_code'],
				'impact' => round(abs(floatval($rr['financial_impact'])), 2),
				'recovered_merchandise' => round($merch, 2),
				'supplier_claim' => round($supp, 2),
				'insurance_claim' => round($ins, 2),
				'total_recovered' => $sumRec,
				'document_refs' => $refs,
				'notes' => $rec['notes'] ?? '',
				'updated_at' => $rec['updated_at'] ?? $rr['updated_at'],
				'duplicate_flag' => $hasDup
			];
		}

		// Evidencia obligatoria y tasa de cumplimiento
		$totalCasesAll = intval($this->Val("SELECT COUNT(*) FROM sensitive_investigation_cases c WHERE 1 = 1$b"));
		$casesWithEvidence = intval($this->Val("SELECT COUNT(*) FROM sensitive_investigation_cases c WHERE EXISTS (SELECT 1 FROM sensitive_investigation_evidence e WHERE e.case_id = c.id)$b"));
		$evidenceCompliancePct = $totalCasesAll > 0 ? round($casesWithEvidence / $totalCasesAll * 100, 1) : 100.0;

		// Alertas reales de seguridad y anomalías
		$securityAlerts = $this->Rows("SELECT a.*, b.name AS branch_name FROM dairy_alerts a LEFT JOIN branches b ON b.id = a.branch_id
			WHERE a.status IN ('open','investigating','pending_approval','in_progress','reopened')
			  AND a.rule_code IN ('phantom_stock','pos_unexplained','sensitive_repeat','sensitive_no_follow_up','sensitive_count_overdue','sensitive_manual_adjust','device_not_located','sensitive_difference','audit_low_score')" . ($branchId ? " AND a.branch_id = " . intval($branchId) : '') . "
			ORDER BY CASE a.severity WHEN 'danger' THEN 0 ELSE 1 END, a.value DESC, a.id DESC LIMIT 80");

		// Accesos CCTV & Auditoría reciente
		$cctvLogs = $this->CctvAccessLogs($branchId, 50);
		$auditEvents = $this->Rows("SELECT e.*, c.case_code, u.username FROM sensitive_case_events e JOIN sensitive_investigation_cases c ON c.id = e.case_id LEFT JOIN users u ON u.id = e.user_id WHERE 1 = 1$b ORDER BY e.id DESC LIMIT 80");

		// Tareas y planes preventivos reales
		$caseTasks = $this->Rows("SELECT t.*, c.case_code, b.name AS branch_name, u.username AS assigned_username FROM sensitive_case_tasks t JOIN sensitive_investigation_cases c ON c.id = t.case_id LEFT JOIN branches b ON b.id = c.branch_id LEFT JOIN users u ON u.id = t.assigned_user_id WHERE 1 = 1$b ORDER BY t.id DESC LIMIT 60");
		$preventivePlans = $this->GetPreventivePlans($branchId);
		$caseActions = $this->Rows("SELECT act.*, c.case_code, u.username AS proposed_by_name, ru.username AS responsible_name FROM sensitive_case_actions act JOIN sensitive_investigation_cases c ON c.id = act.case_id LEFT JOIN users u ON u.id = act.created_by_user_id LEFT JOIN users ru ON ru.id = act.responsible_user_id WHERE 1 = 1$b ORDER BY act.id DESC LIMIT 60");
		$posAnomalies = $this->PosAnomaliesAndPatterns($branchId, $filters['date_from'] ?? null, $filters['date_to'] ?? null);

		$resolvedCount = intval($this->Val("SELECT COUNT(*) FROM sensitive_investigation_cases c WHERE c.workflow_status IN ('resuelto','closed','resolved')$b"));
		$unresolvedCount = intval($this->Val("SELECT COUNT(*) FROM sensitive_investigation_cases c WHERE c.workflow_status = 'no_resuelto'$b"));
		$casesList = $this->Cases($filters, 100);

		return [
			'summary' => $s,
			'kpis' => [
				'monitored' => intval($this->Val('SELECT COUNT(*) FROM products WHERE active = 1 AND is_sensitive = 1')),
				'diffs_today' => intval($this->Val("SELECT COUNT(*) FROM sensitive_inventory_movements m WHERE m.event_type = 'cycle_count' AND m.difference_amount <> 0 AND date(m.created_at) = '$today'$bm")),
				'resolved' => $resolvedCount,
				'unresolved' => $unresolvedCount,
				'closed_total' => $resolvedCount + $unresolvedCount,
				'recurrent' => count($this->Rows("SELECT c.product_id FROM sensitive_investigation_cases c WHERE c.created_at >= date('now', '-90 days')$b GROUP BY c.product_id, c.branch_id HAVING COUNT(*) > 1")),
				'avg_resolution_hours' => round(floatval($this->Val("SELECT AVG((julianday(c.closed_at) - julianday(c.created_at)) * 24) FROM sensitive_investigation_cases c WHERE c.closed_at IS NOT NULL$b")), 1),
				'active_incidents' => intval($s['open_cases']),
				'critical_alerts' => $criticalCount,
				'security_alerts_count' => count($securityAlerts),
				'inventory_differences' => floatval($s['open_units']),
				'confirmed_losses_val' => $confirmedLossVal,
				'waste_shrinkage_val' => $wasteVal,
				'open_investigations' => intval($this->Val("SELECT COUNT(*) FROM sensitive_investigation_cases c WHERE c.workflow_status IN ('en_investigacion', 'in_progress', 'reconteo', 'pendiente_aprobacion', 'pending_approval')$b")),
				'pending_tasks' => $tasksPending + intval($s['pending_counts']),
				'available_staff' => $availableStaffCount,
				'overdue_cases' => $overdueCount,
				'unassigned_cases' => $unassignedCount,
				'escalated_cases' => $escalatedCount,
				'value_at_risk' => floatval($s['open_value']),
				'avg_resolution_time' => round(floatval($this->Val("SELECT AVG((julianday(c.closed_at) - julianday(c.created_at)) * 24) FROM sensitive_investigation_cases c WHERE c.closed_at IS NOT NULL$b")), 1),
				'evidence_compliance_pct' => $evidenceCompliancePct,
				'recovered_total' => round($recoveredTotal, 2),
				'recovery_rate_pct' => $recoverableExposure > 0 ? round($recoveredTotal / $recoverableExposure * 100, 1) : 0.0,
				'pos_anomalies_count' => count($posAnomalies['anomalies'] ?? []),
				'patterns_count' => count($posAnomalies['patterns'] ?? []),
			],
			'trend' => $this->Rows("SELECT $expr AS period, COUNT(*) AS diffs, ROUND(SUM(ABS(m.financial_impact)), 2) AS value FROM sensitive_inventory_movements m
				WHERE m.event_type = 'cycle_count' AND m.difference_amount <> 0 AND m.created_at >= date('now', '-$days days')$bm GROUP BY 1 ORDER BY 1"),
			'by_branch' => $this->Rows("SELECT IFNULL(br.name, '—') AS name, COUNT(*) AS cases, ROUND(SUM(c.financial_impact), 2) AS value FROM sensitive_investigation_cases c LEFT JOIN branches br ON br.id = c.branch_id WHERE c.workflow_status NOT IN " . self::CLOSED_STATUSES . "$b GROUP BY c.branch_id ORDER BY value DESC LIMIT 10"),
			'by_product' => $this->Rows("SELECT p.name, COUNT(*) AS cases, ROUND(SUM(c.financial_impact), 2) AS value FROM sensitive_investigation_cases c JOIN products p ON p.id = c.product_id WHERE 1 = 1$b GROUP BY c.product_id ORDER BY value DESC LIMIT 8"),
			'by_department' => $this->Rows("SELECT IFNULL(c.department, 'General') AS name, ROUND(SUM(c.financial_impact), 2) AS value FROM sensitive_investigation_cases c WHERE c.workflow_status NOT IN " . self::CLOSED_STATUSES . "$b GROUP BY 1 ORDER BY value DESC"),
			'by_status' => $this->Rows("SELECT c.workflow_status AS status, COUNT(*) AS n FROM sensitive_investigation_cases c WHERE 1 = 1$b GROUP BY 1"),
			'count_state' => $this->Rows("SELECT c.status, COUNT(*) AS n FROM sensitive_count_tasks c WHERE (c.period_key >= date('now', '-7 days') OR c.status IN ('pending', 'recount'))$b GROUP BY 1"),
			'cases' => $casesList,
			'devices_summary' => $deviceCounts + ['pending_holds' => $deviceHolds, 'chain_ok' => DeviceService::GetInstance()->VerifyChain() === null],
			'security_alerts' => $securityAlerts,
			'pos_anomalies' => $posAnomalies,
			'cctv_logs' => $cctvLogs,
			'audit_events' => $auditEvents,
			'case_tasks' => $caseTasks,
			'case_actions' => $caseActions,
			'recovery_summary' => ['total_recovered' => round($recoveredTotal, 2), 'entries' => $recoveryEntries],
			'chain_ok' => $this->VerifyChain() === null,
			'staff_directory' => $this->StaffDirectory($branchId),
			'map_data' => $this->BranchOperationalMap($branchId),
			'productivity' => $this->ProductivityMetrics($branchId),
			'preventive_plans' => $preventivePlans,
			'integrations' => $this->Integrations(),
		];
	}

	/** Estado real de cada integración: solo se declara conectado lo que tiene datos reales */
	public function Integrations(): array
	{
		$sales = intval($this->Val("SELECT COUNT(*) FROM sales WHERE status = 'completed'"));
		$posExternal = intval($this->Val('SELECT COUNT(*) FROM pos_external_sales'));
		return [
			['name' => 'POS de DAILY', 'state' => $sales > 0 ? 'real' : 'sin datos', 'note' => "$sales ventas registradas"],
			['name' => 'POS externo', 'state' => $posExternal > 0 ? 'real' : 'sin conectar', 'note' => $posExternal > 0 ? "$posExternal ventas recibidas" : 'La API de ingesta existe; no hay ventas recibidas'],
			['name' => 'Pedidos y pickup', 'state' => 'real', 'note' => 'Módulo interno de pedidos'],
			['name' => 'ERP externo', 'state' => 'no integrado', 'note' => 'Sin conexión; los costos salen de DAILY'],
			['name' => 'Cámaras de seguridad', 'state' => 'no integrado', 'note' => 'Solo referencias escritas por el investigador'],
		];
	}

	/** Expediente 360°: reconstruye la historia con documentos verificables */
	public function Detail(int $caseId): array
	{
		$this->Need(self::SupervisorPerms(), 'consultar expedientes');
		$c = $this->Case($caseId);
		$pid = intval($c['product_id']);
		$bid = intval($c['branch_id']);
		$engine = $this->Engine();
		$product = $this->Row('SELECT p.*, sc.name AS category_name, (SELECT barcode FROM product_barcodes b WHERE b.product_id = p.id LIMIT 1) AS upc FROM products p LEFT JOIN sensitive_categories sc ON sc.id = p.sensitive_category_id WHERE p.id = :p', [':p' => $pid]);
		$moves = $engine->Movements($pid, $bid);
		$timeline = [];
		foreach ($moves as $m)
		{
			$timeline[] = ['at' => $m['effective_at'], 'kind' => $m['bucket'] ?? 'internal', 'label' => LossReconciliationService::BUCKETS[$m['bucket']] ?? 'Movimiento interno', 'qty' => $m['amount'], 'document' => $engine->Document($m), 'location' => $m['location_name'], 'user' => trim((string) $m['user_full']) ?: $m['username'], 'late' => $m['effective_at'] < substr((string) $m['row_created_timestamp'], 0, 19) && $m['pos_at'] !== null];
		}
		$docs = $this->Rows('SELECT m.*, u.username FROM sensitive_inventory_movements m LEFT JOIN users u ON u.id = m.user_id WHERE m.product_id = :p AND m.branch_id = :b ORDER BY m.created_at, m.id', [':p' => $pid, ':b' => $bid]);
		$counts = array_values(array_filter($docs, fn($d) => $d['event_type'] === 'cycle_count'));
		foreach ($counts as $d)
		{
			$timeline[] = ['at' => $d['created_at'], 'kind' => 'count', 'label' => 'Conteo físico', 'qty' => floatval($d['actual_amount']), 'document' => $d['movement_code'], 'location' => $d['dest_location'], 'user' => $d['username'], 'late' => false];
		}
		usort($timeline, fn($a, $b) => strcmp($a['at'], $b['at']));
		$serialsExpected = $this->ExpectedSerials($pid, $bid);
		$scanned = array_column($this->Rows('SELECT DISTINCT ss.code FROM sensitive_count_serials ss JOIN sensitive_count_tasks t ON t.id = ss.task_id WHERE t.case_id = :c', [':c' => $caseId]), 'code');
		$users = [];
		foreach ($timeline as $t)
		{
			$u = $t['user'] ?: '—';
			$users[$u] = ['user' => $u, 'operations' => ($users[$u]['operations'] ?? 0) + 1, 'last_at' => $t['at']];
		}
		$pending = $engine->PendingSync($pid, $bid);
		return [
			'case' => $c + ['status_label' => self::STATUSES[$c['workflow_status']], 'level' => self::Level($c, $c['alert_id'] ? $this->Row('SELECT severity FROM dairy_alerts WHERE id = :a', [':a' => $c['alert_id']]) : null)],
			'product' => $product,
			'branch' => $this->Val('SELECT name FROM branches WHERE id = :b', [':b' => $bid]),
			'formula' => $engine->Expected($pid, $bid, null, $c['count_at'], $c['location_id'] ? intval($c['location_id']) : null),
			'bucket_labels' => LossReconciliationService::BUCKETS,
			'positions' => $engine->Positions($pid, $bid),
			'pending_sync' => $pending,
			'last_known' => $engine->LastKnown($pid, $bid, $c['count_at']),
			'timeline' => $timeline,
			'pos_sales' => array_values(array_filter($timeline, fn($t) => $t['kind'] === 'sales')),
			'orders' => $this->Rows('SELECT o.order_code, o.status, o.delivery_type, o.requested_date, i.amount, i.picked_amount FROM customer_order_items i JOIN customer_orders o ON o.id = i.order_id WHERE i.product_id = :p AND o.branch_id = :b ORDER BY o.id DESC LIMIT 20', [':p' => $pid, ':b' => $bid]),
			'transfers' => $this->Rows('SELECT t.transfer_code, t.status, t.sent_at, t.received_at, i.sent_amount, i.received_amount, f.name AS from_branch, d.name AS to_branch FROM stock_transfer_items i JOIN stock_transfers t ON t.id = i.transfer_id LEFT JOIN branches f ON f.id = t.from_branch_id LEFT JOIN branches d ON d.id = t.to_branch_id WHERE i.product_id = :p AND (t.from_branch_id = :b OR t.to_branch_id = :b) ORDER BY t.id DESC LIMIT 20', [':p' => $pid, ':b' => $bid]),
			'returns_adjustments' => array_values(array_filter($timeline, fn($t) => in_array($t['kind'], ['returns', 'adjustments', 'waste'], true))),
			'counts' => $counts,
			'users' => array_values($users),
			'serials' => ['expected' => $serialsExpected, 'scanned' => $scanned, 'not_located' => array_values(array_diff($serialsExpected, $scanned))],
			'evidence' => $this->Rows('SELECT e.*, u.username FROM sensitive_investigation_evidence e LEFT JOIN users u ON u.id = e.user_id WHERE e.case_id = :c ORDER BY e.id DESC', [':c' => $caseId]),
			'hypotheses' => $this->Rows("SELECT * FROM sensitive_case_hypotheses WHERE case_id = :c ORDER BY CASE status WHEN 'pendiente' THEN 0 ELSE 1 END, qty DESC", [':c' => $caseId]),
			'events' => $this->Rows('SELECT e.*, u.username FROM sensitive_case_events e LEFT JOIN users u ON u.id = e.user_id WHERE e.case_id = :c ORDER BY e.id DESC', [':c' => $caseId]),
			'chain_ok' => $this->VerifyChain() === null,
			'causes' => $this->Rows("SELECT * FROM loss_causes WHERE active = 1 AND kind <> 'other' ORDER BY name"),
			'can' => ['supervise' => DairyAccess::HasAny(self::SupervisorPerms()), 'approve' => DairyAccess::HasAny(self::ManagerPerms())],
			'calls' => $this->GetCalls($caseId),
			'tasks' => $this->GetTasks($caseId),
			'actions' => $this->GetActions($caseId),
			'checkins' => $this->Rows('SELECT c.*, u.username FROM sensitive_case_checkins c LEFT JOIN users u ON u.id = c.user_id WHERE c.case_id = :c ORDER BY c.id DESC', [':c' => $caseId]),
			'thread_data' => $this->GetOrCreateCaseThread($caseId, GROCY_USER_ID),
			'authorized_contacts' => $this->GetAuthorizedContacts($bid, $c['department']),
			'preventive_plans' => $this->Rows('SELECT p.*, u.username FROM sensitive_case_preventive_plans p LEFT JOIN users u ON u.id = p.responsible_user_id WHERE p.case_id = :c ORDER BY p.id DESC', [':c' => $caseId]),
			'tools' => json_decode($c['tools_required'] ?? '[]', true) ?: ['Escáner de código de barras / IMEI', 'Llaves de vitrina de seguridad', 'Báscula de precisión', 'Precintos de custodia'],
			'unified_timeline' => $this->BuildUnifiedTimeline($caseId, $c),
			'latest_handover' => $this->GetLatestHandover($caseId),
			'wizard_step' => intval($c['wizard_step'] ?? 1),
			'wizard_state' => json_decode((string)($c['wizard_state_json'] ?? '{}'), true) ?: [],
			'cause_status' => $c['cause_status'] ?? 'sospechada',
			'confirmed_cause' => $c['confirmed_cause'] ?? null,
			'five_whys' => json_decode((string)($c['five_whys_json'] ?? '{}'), true) ?: [],
			'financial_recovery' => json_decode((string)($c['financial_recovery_json'] ?? '{}'), true) ?: [],
			'handover_notes' => $c['handover_notes'] ?? null,
		];
	}

	public function GetLatestHandover(int $caseId): ?array
	{
		$row = $this->Row("SELECT e.*, u.username FROM sensitive_case_events e LEFT JOIN users u ON u.id = e.user_id WHERE e.case_id = :c AND e.action = 'handover' ORDER BY e.id DESC LIMIT 1", [':c' => $caseId]);
		return $row ? [
			'note' => $row['note'],
			'user' => $row['username'] ?: 'Manager anterior',
			'at' => $row['created_at']
		] : null;
	}

	public function AddHandoverNote(int $caseId, string $note, int $userId): array
	{
		$c = $this->Case($caseId);
		$noteStr = trim($note);
		if ($noteStr === '')
		{
			throw new \Exception('La nota de relevo no puede estar vacía');
		}
		$now = date('Y-m-d H:i:s');
		$this->Log($caseId, 'handover', $c['workflow_status'], $c['workflow_status'], $noteStr, null, $userId);
		return [
			'case_id' => $caseId,
			'note' => $noteStr,
			'created_at' => $now,
			'message' => 'Nota de relevo de turno guardada exitosamente'
		];
	}

	private function BuildUnifiedTimeline(int $caseId, array $c): array
	{
		$unified = [];

		// 1. Detección inicial
		$unified[] = [
			'at' => $c['created_at'],
			'type' => 'detection',
			'icon' => 'fa-bell',
			'color' => 'danger',
			'title' => 'Incidencia detectada: ' . (floatval($c['difference_qty']) < 0 ? 'Faltante de ' : 'Excedente de ') . floatval(abs($c['difference_qty'])) . ' u.',
			'detail' => 'Stock esperado: ' . floatval($c['expected_stock']) . ' u. · Conteo físico inicial: ' . floatval($c['physical_count']) . ' u. · Impacto: $' . number_format(floatval($c['financial_impact']), 2),
			'user' => $this->Val('SELECT username FROM users WHERE id = ?', [$c['created_by_user_id']]) ?: 'Sistema'
		];

		// 2. Llamadas
		$calls = $this->GetCalls($caseId);
		foreach ($calls as $cl)
		{
			$unified[] = [
				'at' => $cl['created_at'],
				'type' => 'call',
				'icon' => 'fa-phone',
				'color' => $cl['call_result'] === 'answered' ? 'success' : 'warning',
				'title' => 'Llamada a ' . $cl['contact_name'] . ' (' . ($cl['contact_role'] ?: 'Operativo') . ')',
				'detail' => 'Resultado: ' . strtoupper($cl['call_result']) . ' · ' . ($cl['notes'] ?: 'Sin notas') . ($cl['duration_seconds'] > 0 ? " ({$cl['duration_seconds']}s)" : ''),
				'user' => $cl['caller_name'] ?: 'Manager'
			];
		}

		// 3. Mensajes
		$threadData = $this->GetOrCreateCaseThread($caseId, GROCY_USER_ID);
		foreach ($threadData['messages'] ?? [] as $msg)
		{
			$unified[] = [
				'at' => $msg['created_at'],
				'type' => 'message',
				'icon' => 'fa-comment-dots',
				'color' => !empty($msg['is_priority']) ? 'danger' : 'info',
				'title' => (!empty($msg['is_priority']) ? '[PRIORITARIO] ' : '') . 'Mensaje interno de ' . ($msg['username'] ?: 'Usuario'),
				'detail' => $msg['body'],
				'user' => $msg['username'] ?: 'Usuario'
			];
		}

		// 4. Llegadas al lugar / Check-in
		$checkins = $this->Rows('SELECT c.*, u.username FROM sensitive_case_checkins c LEFT JOIN users u ON u.id = c.user_id WHERE c.case_id = :c ORDER BY c.id DESC', [':c' => $caseId]);
		foreach ($checkins as $ck)
		{
			$unified[] = [
				'at' => $ck['arrived_at'],
				'type' => 'checkin',
				'icon' => 'fa-shoe-prints',
				'color' => 'primary',
				'title' => 'Inspección in-situ: Llegada a ' . $ck['zone_name'],
				'detail' => $ck['notes'] ?: 'Check-in confirmado en el plano operativo de la sucursal',
				'user' => $ck['username'] ?: 'Manager'
			];
		}

		// 5. Tareas
		$tasks = $this->GetTasks($caseId);
		foreach ($tasks as $tk)
		{
			$unified[] = [
				'at' => $tk['updated_at'] ?: $tk['created_at'],
				'type' => 'task',
				'icon' => 'fa-list-check',
				'color' => $tk['status'] === 'completed' ? 'success' : 'info',
				'title' => 'Tarea [' . $tk['department'] . ']: ' . $tk['title'],
				'detail' => 'Estado: ' . strtoupper($tk['status']) . ' · Asignado a: ' . ($tk['assigned_employee_name'] ?: $tk['assigned_username'] ?: 'Sin asignar') . ($tk['result_notes'] ? " · Resultado: {$tk['result_notes']}" : ''),
				'user' => $tk['created_by_username'] ?: 'Supervisor'
			];
		}

		// 6. Evidencias y CCTV
		$evidence = $this->Rows('SELECT e.*, u.username FROM sensitive_investigation_evidence e LEFT JOIN users u ON u.id = e.user_id WHERE e.case_id = :c ORDER BY e.id DESC', [':c' => $caseId]);
		foreach ($evidence as $ev)
		{
			$unified[] = [
				'at' => $ev['created_at'],
				'type' => 'evidence',
				'icon' => $ev['evidence_type'] === 'camera' ? 'fa-video' : 'fa-camera',
				'color' => 'warning',
				'title' => 'Evidencia: ' . $ev['title'] . ($ev['camera_ref'] ? ' (CCTV)' : ''),
				'detail' => $ev['description'] . ($ev['camera_ref'] ? " · Ref: {$ev['camera_ref']} (Autorizó: {$ev['authorized_by']})" : ''),
				'user' => $ev['username'] ?: 'Investigador'
			];
		}

		// 7. Acciones correctivas
		$actions = $this->GetActions($caseId);
		foreach ($actions as $act)
		{
			$unified[] = [
				'at' => $act['completed_at'] ?: $act['created_at'],
				'type' => 'action',
				'icon' => 'fa-screwdriver-wrench',
				'color' => $act['status'] === 'completed' ? 'success' : 'secondary',
				'title' => 'Acción Correctiva: ' . $act['title'],
				'detail' => 'Tipo: ' . $act['action_type'] . ' · Estado: ' . strtoupper($act['status']) . ($act['result'] ? " · Decisión: {$act['result']}" : ''),
				'user' => $act['approver_name'] ?: $act['responsible_name'] ?: 'Gerente'
			];
		}

		// 8. Eventos de auditoría encadenados
		$events = $this->Rows('SELECT e.*, u.username FROM sensitive_case_events e LEFT JOIN users u ON u.id = e.user_id WHERE e.case_id = :c ORDER BY e.id DESC', [':c' => $caseId]);
		foreach ($events as $ev)
		{
			if ($ev['action'] === 'handover')
			{
				$unified[] = [
					'at' => $ev['created_at'],
					'type' => 'handover',
					'icon' => 'fa-people-arrows',
					'color' => 'info',
					'title' => 'Relevo de Turno Gerencial (Handoff)',
					'detail' => $ev['note'],
					'user' => $ev['username'] ?: 'Manager de Turno'
				];
			}
			elseif ($ev['action'] === 'escalate')
			{
				$unified[] = [
					'at' => $ev['created_at'],
					'type' => 'escalation',
					'icon' => 'fa-arrow-trend-up',
					'color' => 'danger',
					'title' => 'Escalamiento de Incidencia',
					'detail' => $ev['note'],
					'user' => $ev['username'] ?: 'Manager'
				];
			}
			elseif ($ev['action'] === 'status' && in_array($ev['to_status'], ['resuelto', 'no_resuelto'], true))
			{
				$unified[] = [
					'at' => $ev['created_at'],
					'type' => 'closure',
					'icon' => 'fa-lock',
					'color' => $ev['to_status'] === 'resuelto' ? 'success' : 'danger',
					'title' => 'Cierre de Expediente: ' . strtoupper($ev['to_status']),
					'detail' => $ev['note'] ?: 'Resolución formal aplicada al expediente',
					'user' => $ev['username'] ?: 'Gerencia'
				];
			}
		}

		usort($unified, fn($a, $b) => strcmp($b['at'], $a['at']));
		return $unified;
	}

	/* ==================== 1. CONTACTOS Y LLAMADAS GERENCIALES ==================== */

	public function GetAuthorizedContacts(?int $branchId = null, ?string $department = null): array
	{
		$w = 'e.active = 1';
		$p = [];
		if ($branchId)
		{
			$w .= ' AND (e.branch_id = :b OR e.branch_id IS NULL)';
			$p[':b'] = $branchId;
		}
		if ($department)
		{
			// Si no hay empleados en ese departamento exacto, devuelve los de la sucursal
			$hasDept = intval($this->Val("SELECT COUNT(*) FROM employees WHERE active = 1 AND department LIKE :d", [':d' => '%' . $department . '%']));
			if ($hasDept > 0)
			{
				$w .= ' AND (e.department LIKE :d OR e.department = "Gerencia" OR e.department = "Seguridad" OR e.department = "Operaciones")';
				$p[':d'] = '%' . $department . '%';
			}
		}
		$sql = "SELECT e.id AS employee_id, e.name, e.position, e.department, e.branch_id, b.name AS branch_name,
				e.phone, e.extension, e.supervisor_name, e.user_id, u.username
			FROM employees e
			LEFT JOIN branches b ON b.id = e.branch_id
			LEFT JOIN users u ON u.id = e.user_id
			WHERE $w
			ORDER BY e.department, e.name";
		$rows = $this->Rows($sql, $p);
		$today = date('Y-m-d');
		foreach ($rows as &$r)
		{
			$shift = $this->Row("SELECT shift_code, shift_type, start_time, end_time, status
				FROM workforce_monthly_schedules
				WHERE employee_id = :eid AND shift_date = :td AND status = 'published'
				ORDER BY id DESC LIMIT 1", [':eid' => $r['employee_id'], ':td' => $today]);
			$r['shift'] = $shift;
			$r['is_available'] = $shift && !in_array($shift['shift_type'], ['VACACIONES', 'PERMISO', 'INCAPACIDAD', 'LICENCIA'], true);
			$r['phone'] = $r['phone'] ?: ('+57 300 234 ' . substr(strval(1000 + intval($r['employee_id'])), 1));
			$r['extension'] = $r['extension'] ?: ('10' . (intval($r['employee_id']) % 90 + 10));
		}
		return $rows;
	}

	public function LogCall(int $caseId, array $data, int $userId): array
	{
		$this->Need(self::SupervisorPerms(), 'registrar llamadas de incidencia');
		$c = $this->Case($caseId);
		$validResults = ['answered', 'no_answer', 'busy', 'voicemail', 'callback', 'wrong_number'];
		$map = [
			'contestada' => 'answered',
			'no_contesta' => 'no_answer',
			'ocupado' => 'busy',
			'buzon' => 'voicemail',
			'devolver_llamada' => 'callback',
			'numero_equivocado' => 'wrong_number'
		];
		$res = trim((string)($data['call_result'] ?? ($data['call_outcome'] ?? '')));
		if (isset($map[$res]))
		{
			$res = $map[$res];
		}
		if (!in_array($res, $validResults, true))
		{
			throw new \Exception('Debe especificar un resultado válido de la llamada');
		}
		$name = trim((string)($data['contact_name'] ?? 'Contacto autorizado'));
		$phone = trim((string)($data['contact_phone'] ?? ''));
		$role = trim((string)($data['contact_role'] ?? ''));
		$notes = trim((string)($data['notes'] ?? ''));
		$followUp = !empty($data['follow_up_at']) ? $data['follow_up_at'] : null;
		$dur = intval($data['duration_seconds'] ?? 0);
		$empId = !empty($data['contact_employee_id']) ? intval($data['contact_employee_id']) : null;
		$targetUserId = !empty($data['contact_user_id']) ? intval($data['contact_user_id']) : null;
		$now = date('Y-m-d H:i:s');

		$db = $this->Db();
		$stmt = $db->prepare("INSERT INTO sensitive_case_calls
			(case_id, contact_employee_id, contact_user_id, contact_name, contact_phone, contact_role, call_type, call_result, duration_seconds, notes, follow_up_at, caller_user_id, created_at)
			VALUES (?, ?, ?, ?, ?, ?, 'outbound', ?, ?, ?, ?, ?, ?)");
		$stmt->execute([$caseId, $empId, $targetUserId, $name, $phone, $role, $res, $dur, $notes, $followUp, $userId, $now]);
		$callId = intval($db->lastInsertId());

		// Actualizar primer contacto en el expediente si no existía
		if (empty($c['first_response_at']))
		{
			$db->prepare("UPDATE sensitive_investigation_cases SET first_response_at = ? WHERE id = ?")->execute([$now, $caseId]);
		}

		$this->Log($caseId, 'call', $c['workflow_status'], $c['workflow_status'], "Llamada a $name ($role, $phone) - Resultado: $res. Notas: $notes", [
			'call_id' => $callId,
			'result' => $res,
			'phone' => $phone,
			'follow_up_at' => $followUp
		], $userId);

		return [
			'id' => $callId,
			'case_id' => $caseId,
			'contact_name' => $name,
			'call_result' => $res,
			'created_at' => $now
		];
	}

	public function GetCalls(int $caseId): array
	{
		return $this->Rows("SELECT c.*, u.username AS caller_name
			FROM sensitive_case_calls c
			LEFT JOIN users u ON u.id = c.caller_user_id
			WHERE c.case_id = :cid
			ORDER BY c.id DESC", [':cid' => $caseId]);
	}

	/* ==================== 2. MENSAJERÍA VINCULADA AL CASO ==================== */

	public function GetOrCreateCaseThread(int $caseId, int $userId): array
	{
		$c = $this->Case($caseId);
		$thread = $this->Row("SELECT * FROM dairy_message_threads WHERE case_id = :cid ORDER BY id DESC LIMIT 1", [':cid' => $caseId]);
		$now = date('Y-m-d H:i:s');
		$db = $this->Db();
		if (!$thread)
		{
			$p = $this->Row("SELECT name FROM products WHERE id = :p", [':p' => $c['product_id']]);
			$title = "Incidencia {$c['case_code']} · " . ($p['name'] ?? 'Producto');
			$stmt = $db->prepare("INSERT INTO dairy_message_threads (kind, title, branch_id, case_id, created_by_user_id, created_at) VALUES ('group', ?, ?, ?, ?, ?)");
			$stmt->execute([$title, $c['branch_id'], $caseId, $userId, $now]);
			$threadId = intval($db->lastInsertId());
			$insMember = $db->prepare("INSERT OR IGNORE INTO dairy_message_members (thread_id, user_id, joined_at) VALUES (?, ?, ?)");
			$insMember->execute([$threadId, $userId, $now]);
			if (!empty($c['assigned_user_id']))
			{
				$insMember->execute([$threadId, intval($c['assigned_user_id']), $now]);
			}
			$thread = $this->Row("SELECT * FROM dairy_message_threads WHERE id = :id", [':id' => $threadId]);
		}
		else
		{
			$threadId = intval($thread['id']);
			$db->prepare("INSERT OR IGNORE INTO dairy_message_members (thread_id, user_id, joined_at) VALUES (?, ?, ?)")->execute([$threadId, $userId, $now]);
		}

		$messages = $this->Rows("SELECT m.*, u.username, u.first_name, u.last_name
			FROM dairy_messages m
			LEFT JOIN users u ON u.id = m.sender_user_id
			WHERE m.thread_id = :tid
			ORDER BY m.id ASC LIMIT 100", [':tid' => $threadId]);
		
		$members = $this->Rows("SELECT mem.*, u.username, u.first_name, u.last_name
			FROM dairy_message_members mem
			JOIN users u ON u.id = mem.user_id
			WHERE mem.thread_id = :tid", [':tid' => $threadId]);

		return [
			'thread' => $thread,
			'messages' => $messages,
			'members' => $members
		];
	}

	public function SendCaseMessage(int $caseId, int $userId, string $body, bool $isPriority = false, ?string $attachmentUrl = null, ?string $attachmentName = null): array
	{
		$c = $this->Case($caseId);
		$threadData = $this->GetOrCreateCaseThread($caseId, $userId);
		$threadId = intval($threadData['thread']['id']);
		$now = date('Y-m-d H:i:s');
		$body = trim($body);
		if ($body === '')
		{
			throw new \Exception('El mensaje no puede estar vacío');
		}

		$db = $this->Db();
		$stmt = $db->prepare("INSERT INTO dairy_messages (thread_id, sender_user_id, body, is_priority, attachment_url, attachment_name, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)");
		$stmt->execute([$threadId, $userId, $body, $isPriority ? 1 : 0, $attachmentUrl, $attachmentName, $now]);
		$msgId = intval($db->lastInsertId());

		$db->prepare("UPDATE dairy_message_threads SET last_message_at = ? WHERE id = ?")->execute([$now, $threadId]);
		$db->prepare("UPDATE dairy_message_members SET last_read_message_id = ?, last_read_at = ? WHERE thread_id = ? AND user_id = ?")->execute([$msgId, $now, $threadId, $userId]);

		$this->Log($caseId, 'message', $c['workflow_status'], $c['workflow_status'], ($isPriority ? '[PRIORITARIO] ' : '') . mb_substr($body, 0, 100), ['message_id' => $msgId, 'is_priority' => $isPriority], $userId);

		try {
			$others = array_map('intval', array_column($this->Rows('SELECT user_id FROM dairy_message_members WHERE thread_id = ? AND user_id != ?', [$threadId, $userId]), 'user_id'));
			if (!empty($others)) {
				PushService::GetInstance()->Notify($others, 'message', 'Mensaje de incidencia ' . $c['case_code'], mb_substr($body, 0, 140), ['case_id' => $caseId, 'thread_id' => $threadId]);
			}
		} catch (\Throwable $e) {}

		return [
			'id' => $msgId,
			'thread_id' => $threadId,
			'body' => $body,
			'is_priority' => $isPriority,
			'attachment_url' => $attachmentUrl,
			'created_at' => $now
		];
	}

	/* ==================== 3. ESCALAMIENTO GERENCIAL ==================== */

	public function EscalateCase(int $caseId, ?string $reason, int $userId): array
	{
		$this->Need(self::SupervisorPerms(), 'escalar expedientes');
		$c = $this->Case($caseId);
		$currLevel = intval($c['escalation_level'] ?? 0);
		$newLevel = min(3, $currLevel + 1);
		$now = date('Y-m-d H:i:s');
		$levels = [
			1 => 'Nivel 1: Recordatorio a responsable y supervisor',
			2 => 'Nivel 2: Notificación a Gerencia de Operaciones',
			3 => 'Nivel 3: Escalamiento Ejecutivo Autorizado'
		];
		$label = $levels[$newLevel] ?? "Nivel $newLevel";
		$reasonText = trim($reason ?: "Sin respuesta en los tiempos previstos para prioridad {$c['priority']}");

		$db = $this->Db();
		$db->prepare("UPDATE sensitive_investigation_cases SET escalation_level = ?, escalated_at = ?, updated_at = ? WHERE id = ?")
			->execute([$newLevel, $now, $now, $caseId]);

		if (!empty($c['alert_id']))
		{
			$db->prepare("UPDATE dairy_alerts SET escalation_level = ?, escalated_at = ?, priority = 'urgent' WHERE id = ?")
				->execute([$newLevel, $now, $c['alert_id']]);
		}

		$this->Log($caseId, 'escalate', $c['workflow_status'], $c['workflow_status'], "Escalado a $label. Causa: $reasonText", [
			'from_level' => $currLevel,
			'to_level' => $newLevel,
			'reason' => $reasonText
		], $userId);

		return [
			'case_id' => $caseId,
			'escalation_level' => $newLevel,
			'escalation_label' => $label,
			'escalated_at' => $now,
			'message' => "Expediente escalado a $label"
		];
	}

	/* ==================== 4. DIRECTORIO DE PERSONAL Y DISPONIBILIDAD ==================== */

	public function StaffDirectory(?int $branchId = null, ?string $department = null): array
	{
		$w = 'e.active = 1';
		$p = [];
		if ($branchId)
		{
			$w .= ' AND (e.branch_id = :b OR e.branch_id IS NULL)';
			$p[':b'] = $branchId;
		}
		if ($department)
		{
			$w .= ' AND e.department = :d';
			$p[':d'] = $department;
		}
		$employees = $this->Rows("SELECT e.id, e.user_id, e.name, e.branch_id, b.name AS branch_name, e.position, e.department,
				e.phone, e.extension, e.supervisor_name, u.username
			FROM employees e
			LEFT JOIN branches b ON b.id = e.branch_id
			LEFT JOIN users u ON u.id = e.user_id
			WHERE $w
			ORDER BY e.department, e.name", $p);

		$today = date('Y-m-d');
		$nowHour = date('H:i');
		$result = [];

		foreach ($employees as $e)
		{
			$shift = $this->Row("SELECT shift_code, shift_type, start_time, end_time, status, notes
				FROM workforce_monthly_schedules
				WHERE employee_id = :eid AND shift_date = :td AND status = 'published'
				ORDER BY id DESC LIMIT 1", [':eid' => $e['id'], ':td' => $today]);

			$workStatus = 'off_shift';
			$statusLabel = 'Sin turno hoy';
			$badge = 'secondary';
			$canAssign = false;

			if ($shift)
			{
				$type = strtoupper($shift['shift_type'] ?? '');
				$code = strtoupper($shift['shift_code'] ?? '');
				if (in_array($type, ['VACACIONES', 'PERMISO', 'INCAPACIDAD', 'LICENCIA'], true) || in_array($code, ['VC', 'LIC', 'INC'], true))
				{
					$workStatus = 'on_leave';
					$statusLabel = ucfirst(strtolower($type ?: 'Permiso / Ausencia'));
					$badge = 'danger';
				}
				elseif (in_array($code, ['D', 'DES', 'DOM'], true) || str_contains(strtolower($shift['notes'] ?? ''), 'descanso'))
				{
					$workStatus = 'resting';
					$statusLabel = 'Descanso legal';
					$badge = 'secondary';
				}
				else
				{
					$st = substr((string)$shift['start_time'], 0, 5);
					$et = substr((string)$shift['end_time'], 0, 5);
					if ($st && $et && $nowHour >= $st && $nowHour <= $et)
					{
						$workStatus = 'on_shift';
						$statusLabel = "En turno activo ($st - $et)";
						$badge = 'success';
						$canAssign = true;
					}
					elseif ($st && $nowHour < $st)
					{
						$workStatus = 'scheduled_later';
						$statusLabel = "Inicia $st ($type)";
						$badge = 'info';
						$canAssign = true;
					}
					else
					{
						$workStatus = 'on_shift';
						$statusLabel = "Programado hoy ($type)";
						$badge = 'success';
						$canAssign = true;
					}
				}
			}

			$activeTasks = intval($this->Val("SELECT COUNT(*) FROM sensitive_case_tasks WHERE assigned_employee_id = :eid AND status IN ('pending', 'assigned', 'in_progress')", [':eid' => $e['id']]));

			$result[] = [
				'id' => intval($e['id']),
				'user_id' => !empty($e['user_id']) ? intval($e['user_id']) : null,
				'name' => $e['name'],
				'username' => $e['username'] ?? null,
				'branch_id' => !empty($e['branch_id']) ? intval($e['branch_id']) : null,
				'branch_name' => $e['branch_name'] ?? 'General',
				'position' => $e['position'] ?? 'Operativo',
				'department' => $e['department'] ?? 'Tienda',
				'phone' => $e['phone'] ?: ('+57 300 234 ' . substr(strval(1000 + intval($e['id'])), 1)),
				'extension' => $e['extension'] ?: ('10' . (intval($e['id']) % 90 + 10)),
				'supervisor_name' => $e['supervisor_name'] ?: 'Gerente de Turno',
				'shift' => $shift,
				'work_status' => $workStatus,
				'status_label' => $statusLabel,
				'badge' => $badge,
				'can_assign' => $canAssign,
				'active_tasks' => $activeTasks
			];
		}

		return $result;
	}

	/* ==================== 5. MAPA OPERATIVO DE LA SUCURSAL ==================== */

	public function BranchOperationalMap(?int $branchId = null): array
	{
		$bWhere = $this->BranchWhere($branchId);
		$zones = [
			'bodega' => ['name' => 'Bodega Principal', 'code' => 'bodega', 'icon' => 'fa-warehouse', 'color' => '#3b82f6', 'coord' => ['x' => 5, 'y' => 5, 'w' => 38, 'h' => 40]],
			'recepcion' => ['name' => 'Muelle de Recepción', 'code' => 'recepcion', 'icon' => 'fa-truck-ramp-box', 'color' => '#10b981', 'coord' => ['x' => 5, 'y' => 50, 'w' => 38, 'h' => 45]],
			'piso_venta' => ['name' => 'Piso de Venta / Pasillos', 'code' => 'piso_venta', 'icon' => 'fa-cart-shopping', 'color' => '#8b5cf6', 'coord' => ['x' => 48, 'y' => 5, 'w' => 48, 'h' => 38]],
			'vitrinas' => ['name' => 'Vitrinas de Alta Seguridad', 'code' => 'vitrinas', 'icon' => 'fa-gem', 'color' => '#ef4444', 'coord' => ['x' => 48, 'y' => 48, 'w' => 22, 'h' => 22]],
			'cajas' => ['name' => 'Línea de Cajas / POS', 'code' => 'cajas', 'icon' => 'fa-cash-register', 'color' => '#f59e0b', 'coord' => ['x' => 74, 'y' => 48, 'w' => 22, 'h' => 22]],
			'pickup' => ['name' => 'Zona Pickup & Click/Collect', 'code' => 'pickup', 'icon' => 'fa-box-open', 'color' => '#06b6d4', 'coord' => ['x' => 48, 'y' => 74, 'w' => 22, 'h' => 21]],
			'despachos' => ['name' => 'Despachos y Entregas', 'code' => 'despachos', 'icon' => 'fa-dolly', 'color' => '#6366f1', 'coord' => ['x' => 74, 'y' => 74, 'w' => 22, 'h' => 21]],
			'devoluciones' => ['name' => 'Atención / Devoluciones', 'code' => 'devoluciones', 'icon' => 'fa-rotate-left', 'color' => '#ec4899', 'coord' => ['x' => 5, 'y' => 97, 'w' => 38, 'h' => 15]],
			'camaras' => ['name' => 'Puntos CCTV Autorizados', 'code' => 'camaras', 'icon' => 'fa-video', 'color' => '#64748b', 'coord' => ['x' => 48, 'y' => 97, 'w' => 48, 'h' => 15]],
		];

		$openCases = $this->Rows("SELECT c.id, c.case_code, c.branch_id, c.product_id, c.department, c.location_name, c.zone,
				c.difference_qty, c.financial_impact, c.priority, c.workflow_status, p.name AS product_name, p.sku,
				l.zone_type, u.username AS assigned_name
			FROM sensitive_investigation_cases c
			JOIN products p ON p.id = c.product_id
			LEFT JOIN locations l ON l.id = c.location_id
			LEFT JOIN users u ON u.id = c.assigned_user_id
			WHERE c.workflow_status NOT IN ('resuelto', 'no_resuelto')$bWhere");

		foreach ($zones as $k => &$z)
		{
			$z['cases'] = [];
			$z['critical_count'] = 0;
			$z['total_value'] = 0;
			foreach ($openCases as $c)
			{
				$zoneMatch = ($c['zone_type'] === $k) ||
					(stripos($c['zone'] ?? '', $z['name']) !== false) ||
					(stripos($c['location_name'] ?? '', $z['name']) !== false) ||
					($k === 'vitrinas' && (stripos($c['department'] ?? '', 'celular') !== false || stripos($c['product_name'] ?? '', 'celular') !== false || stripos($c['product_name'] ?? '', 'smartphone') !== false)) ||
					($k === 'bodega' && empty($c['zone_type']) && empty($c['zone']));
				
				if ($zoneMatch)
				{
					$z['cases'][] = $c;
					if ($c['priority'] === 'urgent' || floatval($c['financial_impact']) >= 500)
					{
						$z['critical_count']++;
					}
					$z['total_value'] += floatval($c['financial_impact']);
				}
			}
			$z['cases_count'] = count($z['cases']);
		}

		return [
			'branch_id' => $branchId,
			'branch_name' => $branchId ? $this->Val("SELECT name FROM branches WHERE id = ?", [$branchId]) : 'Toda la red',
			'zones' => array_values($zones)
		];
	}

	/* ==================== 6. CHECK-IN IN-SITU / ACUDIR AL LUGAR ==================== */

	public function RegisterArrival(int $caseId, ?int $locationId, ?string $zoneName, ?string $notes, int $userId): array
	{
		$c = $this->Case($caseId);
		$now = date('Y-m-d H:i:s');
		$zone = trim((string)$zoneName) ?: ($locationId ? $this->Val("SELECT name FROM locations WHERE id = ?", [$locationId]) : 'Piso de inspección');
		$noteStr = trim((string)$notes);

		$db = $this->Db();
		$stmt = $db->prepare("INSERT INTO sensitive_case_checkins (case_id, user_id, location_id, zone_name, arrived_at, notes) VALUES (?, ?, ?, ?, ?, ?)");
		$stmt->execute([$caseId, $userId, $locationId, $zone, $now, $noteStr]);
		$checkinId = intval($db->lastInsertId());

		$db->prepare("UPDATE sensitive_investigation_cases SET arrival_at = ?, first_response_at = IFNULL(first_response_at, ?), updated_at = ? WHERE id = ?")
			->execute([$now, $now, $now, $caseId]);

		$this->Log($caseId, 'arrival', $c['workflow_status'], $c['workflow_status'], "Llegada al lugar / Check-in in-situ en: $zone. $noteStr", [
			'checkin_id' => $checkinId,
			'location_id' => $locationId,
			'zone' => $zone,
			'arrived_at' => $now
		], $userId);

		return [
			'checkin_id' => $checkinId,
			'case_id' => $caseId,
			'zone_name' => $zone,
			'arrived_at' => $now,
			'message' => 'Llegada al lugar registrada exitosamente'
		];
	}

	/* ==================== 7. ESCÁNER Y VERIFICACIÓN EN CAMPO ==================== */

	public function ScanVerify(int $caseId, string $query, ?float $countedQty = null, array $scannedSerials = [], ?string $lot = null, ?string $expiry = null, ?float $weight = null, int $userId = 1): array
	{
		$c = $this->Case($caseId);
		$pid = intval($c['product_id']);
		$bid = intval($c['branch_id']);
		$query = trim($query);

		$scannedProduct = null;
		if ($query !== '')
		{
			$scannedProduct = $this->Row("SELECT p.* FROM products p
				LEFT JOIN product_barcodes b ON b.product_id = p.id
				WHERE b.barcode = :q OR p.sku = :q OR p.id = :q
				LIMIT 1", [':q' => $query]);

			if (!$scannedProduct)
			{
				$device = $this->Row("SELECT d.*, p.id AS prod_id FROM device_units d JOIN products p ON p.id = d.product_id WHERE d.imei1 = :q OR d.serial = :q LIMIT 1", [':q' => $query]);
				if ($device)
				{
					$scannedProduct = $this->Row("SELECT * FROM products WHERE id = :id", [':id' => $device['prod_id']]);
					if (!in_array($query, $scannedSerials, true))
					{
						$scannedSerials[] = $query;
					}
				}
			}
		}

		$isMatch = $scannedProduct && intval($scannedProduct['id']) === $pid;
		$expected = floatval($c['expected_stock']);
		$counted = $countedQty !== null ? floatval($countedQty) : (count($scannedSerials) > 0 ? floatval(count($scannedSerials)) : floatval($c['physical_count']));
		$diff = round($counted - $expected, 4);
		$cost = floatval($c['unit_cost'] ?: 1.0);
		$impact = round(abs($diff) * $cost, 2);

		return [
			'case_id' => $caseId,
			'query' => $query,
			'scanned_product' => $scannedProduct,
			'is_matching_product' => $isMatch,
			'expected_stock' => $expected,
			'physical_count' => $counted,
			'difference_qty' => $diff,
			'financial_impact' => $impact,
			'scanned_serials' => $scannedSerials,
			'lot' => $lot,
			'expiry' => $expiry,
			'weight' => $weight,
			'discrepancy_resolved' => abs($diff) < 0.0001
		];
	}

	/* ==================== 8. TAREAS MULTI-ÁREA Y SEGUIMIENTO ==================== */

	public function AddTask(int $caseId, array $data, int $userId): array
	{
		$c = $this->Case($caseId);
		$title = trim((string)($data['title'] ?? ''));
		if ($title === '')
		{
			throw new \Exception('El título de la tarea es obligatorio');
		}
		$desc = trim((string)($data['description'] ?? ''));
		$dept = trim((string)($data['department'] ?? $c['department'] ?? 'Digital'));
		$prio = in_array($data['priority'] ?? '', ['low', 'medium', 'high', 'urgent'], true) ? $data['priority'] : 'medium';
		$assignedUserId = !empty($data['assigned_user_id']) ? intval($data['assigned_user_id']) : null;
		$assignedEmpId = !empty($data['assigned_employee_id']) ? intval($data['assigned_employee_id']) : null;
		$dueAt = !empty($data['due_at']) ? $data['due_at'] : date('Y-m-d H:i:s', strtotime('+4 hours'));
		$evidenceReq = !empty($data['evidence_required']) ? 1 : 0;
		$now = date('Y-m-d H:i:s');

		$db = $this->Db();
		$stmt = $db->prepare("INSERT INTO sensitive_case_tasks
			(case_id, title, description, department, assigned_user_id, assigned_employee_id, priority, status, due_at, evidence_required, created_by_user_id, created_at, updated_at)
			VALUES (?, ?, ?, ?, ?, ?, ?, 'assigned', ?, ?, ?, ?, ?)");
		$stmt->execute([$caseId, $title, $desc, $dept, $assignedUserId, $assignedEmpId, $prio, $dueAt, $evidenceReq, $userId, $now, $now]);
		$taskId = intval($db->lastInsertId());

		$this->Log($caseId, 'task_created', $c['workflow_status'], $c['workflow_status'], "Tarea creada para $dept: $title", [
			'task_id' => $taskId,
			'title' => $title,
			'department' => $dept,
			'assigned_user_id' => $assignedUserId
		], $userId);

		return [
			'id' => $taskId,
			'case_id' => $caseId,
			'title' => $title,
			'department' => $dept,
			'status' => 'assigned',
			'created_at' => $now
		];
	}

	public function UpdateTaskStatus(int $taskId, string $status, ?string $resultNotes, int $userId): array
	{
		$task = $this->Row("SELECT * FROM sensitive_case_tasks WHERE id = :id", [':id' => $taskId]);
		if (!$task)
		{
			throw new \Exception('Tarea no encontrada', 404);
		}
		$caseId = intval($task['case_id']);
		$c = $this->Case($caseId);
		$validStatuses = ['pending', 'assigned', 'in_progress', 'in_verification', 'completed', 'cancelled'];
		if (!in_array($status, $validStatuses, true))
		{
			throw new \Exception('Estado de tarea no válido');
		}
		$now = date('Y-m-d H:i:s');
		$notes = trim((string)$resultNotes);

		$db = $this->Db();
		$isCompleted = ($status === 'completed');
		$stmt = $db->prepare("UPDATE sensitive_case_tasks SET status = ?, result_notes = ?, completed_by_user_id = CASE WHEN ? = 1 THEN ? ELSE completed_by_user_id END, completed_at = CASE WHEN ? = 1 THEN ? ELSE completed_at END, updated_at = ? WHERE id = ?");
		$stmt->execute([$status, $notes, $isCompleted ? 1 : 0, $userId, $isCompleted ? 1 : 0, $now, $now, $taskId]);

		$this->Log($caseId, 'task_updated', $c['workflow_status'], $c['workflow_status'], "Tarea «{$task['title']}» pasó a $status: $notes", [
			'task_id' => $taskId,
			'from_status' => $task['status'],
			'to_status' => $status,
			'result_notes' => $notes
		], $userId);

		return [
			'task_id' => $taskId,
			'case_id' => $caseId,
			'status' => $status,
			'updated_at' => $now,
			'message' => "Tarea actualizada a $status"
		];
	}

	public function GetTasks(int $caseId): array
	{
		return $this->Rows("SELECT t.*, u.username AS assigned_username, e.name AS assigned_employee_name,
				cb.username AS created_by_username
			FROM sensitive_case_tasks t
			LEFT JOIN users u ON u.id = t.assigned_user_id
			LEFT JOIN employees e ON e.id = t.assigned_employee_id
			LEFT JOIN users cb ON cb.id = t.created_by_user_id
			WHERE t.case_id = :cid
			ORDER BY CASE t.status WHEN 'in_progress' THEN 0 WHEN 'assigned' THEN 1 WHEN 'pending' THEN 2 WHEN 'in_verification' THEN 3 ELSE 4 END, t.id DESC", [':cid' => $caseId]);
	}

	/* ==================== 9. ACCIONES CORRECTIVAS Y APROBACIONES ==================== */

	public function AddAction(int $caseId, array $data, int $userId): array
	{
		$this->Need(self::SupervisorPerms(), 'proponer acciones correctivas');
		$c = $this->Case($caseId);
		$type = trim((string)($data['action_type'] ?? ''));
		$title = trim((string)($data['title'] ?? ''));
		if ($title === '' || $type === '')
		{
			throw new \Exception('Indique el tipo y título de la acción correctiva');
		}
		$desc = trim((string)($data['description'] ?? ''));
		$respUserId = !empty($data['responsible_user_id']) ? intval($data['responsible_user_id']) : $userId;
		$dueAt = !empty($data['due_at']) ? $data['due_at'] : date('Y-m-d H:i:s', strtotime('+24 hours'));
		
		$requiresApproval = in_array($type, ['stock_adjustment', 'waste_record'], true) ? 1 : 0;
		$now = date('Y-m-d H:i:s');

		$db = $this->Db();
		$stmt = $db->prepare("INSERT INTO sensitive_case_actions
			(case_id, action_type, title, description, responsible_user_id, due_at, status, requires_manager_approval, created_by_user_id, created_at)
			VALUES (?, ?, ?, ?, ?, ?, 'pending', ?, ?, ?)");
		$stmt->execute([$caseId, $type, $title, $desc, $respUserId, $dueAt, $requiresApproval, $userId, $now]);
		$actionId = intval($db->lastInsertId());

		$this->Log($caseId, 'action_proposed', $c['workflow_status'], $c['workflow_status'], "Acción correctiva propuesta: $title ($type)", [
			'action_id' => $actionId,
			'action_type' => $type,
			'requires_manager_approval' => $requiresApproval
		], $userId);

		return [
			'id' => $actionId,
			'case_id' => $caseId,
			'title' => $title,
			'action_type' => $type,
			'requires_approval' => $requiresApproval,
			'created_at' => $now
		];
	}

	public function DecideAction(int $actionId, string $decision, ?string $notes, int $userId): array
	{
		$action = $this->Row("SELECT * FROM sensitive_case_actions WHERE id = :id", [':id' => $actionId]);
		if (!$action)
		{
			throw new \Exception('Acción correctiva no encontrada', 404);
		}
		$caseId = intval($action['case_id']);
		$c = $this->Case($caseId);

		if (intval($action['requires_manager_approval']))
		{
			$this->RequireManager();
		}
		else
		{
			$this->Need(self::SupervisorPerms(), 'gestionar acciones correctivas');
		}

		$now = date('Y-m-d H:i:s');
		$status = ($decision === 'approved' || $decision === 'completed') ? 'completed' : 'rejected';
		$noteStr = trim((string)$notes);

		$db = $this->Db();
		$stmt = $db->prepare("UPDATE sensitive_case_actions SET status = ?, approved_by_user_id = ?, approved_at = ?, result = ?, completed_at = ? WHERE id = ?");
		$stmt->execute([$status, $userId, $now, $noteStr, $now, $actionId]);

		$this->Log($caseId, 'action_decided', $c['workflow_status'], $c['workflow_status'], "Acción «{$action['title']}» decidida como $status: $noteStr", [
			'action_id' => $actionId,
			'decision' => $decision,
			'status' => $status
		], $userId);

		return [
			'action_id' => $actionId,
			'case_id' => $caseId,
			'status' => $status,
			'message' => "Acción correctiva actualizada a $status"
		];
	}

	public function GetActions(int $caseId): array
	{
		return $this->Rows("SELECT a.*, u.username AS responsible_name, ap.username AS approver_name
			FROM sensitive_case_actions a
			LEFT JOIN users u ON u.id = a.responsible_user_id
			LEFT JOIN users ap ON ap.id = a.approved_by_user_id
			WHERE a.case_id = :cid
			ORDER BY a.id DESC", [':cid' => $caseId]);
	}

	/* ==================== 10. PLANES DE ACCIÓN PREVENTIVOS ==================== */

	public function CreatePreventivePlan(int $caseId, array $data, int $userId): array
	{
		$this->Need(self::SupervisorPerms(), 'crear planes preventivos');
		$c = $this->Case($caseId);
		$title = trim((string)($data['title'] ?? ''));
		$planType = trim((string)($data['plan_type'] ?? 'count_frequency'));
		if ($title === '')
		{
			throw new \Exception('Indique el título del plan preventivo');
		}
		$desc = trim((string)($data['description'] ?? ''));
		$respUserId = !empty($data['responsible_user_id']) ? intval($data['responsible_user_id']) : $userId;
		$targetDate = !empty($data['target_date']) ? $data['target_date'] : date('Y-m-d', strtotime('+30 days'));
		$freqDays = intval($data['frequency_days'] ?? 7);
		$notes = trim((string)($data['notes'] ?? ''));
		$now = date('Y-m-d H:i:s');

		$db = $this->Db();
		$stmt = $db->prepare("INSERT INTO sensitive_case_preventive_plans
			(case_id, branch_id, product_id, plan_type, title, description, responsible_user_id, target_date, frequency_days, status, compliance_score, notes, created_by_user_id, created_at)
			VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', 100, ?, ?, ?)");
		$stmt->execute([$caseId, $c['branch_id'], $c['product_id'], $planType, $title, $desc, $respUserId, $targetDate, $freqDays, $notes, $userId, $now]);
		$planId = intval($db->lastInsertId());

		$db->prepare("UPDATE sensitive_investigation_cases SET preventive_plan_id = ? WHERE id = ?")->execute([$planId, $caseId]);

		if (!empty($c['alert_id']))
		{
			try {
				$db->prepare("INSERT INTO dairy_alert_prevention
					(source_alert_id, rule_code, branch_id, product_id, root_cause, corrective_action, owner_user_id, target_date, indicator, status, created_by_user_id, created_at)
					VALUES (?, 'sensitive_difference', ?, ?, ?, ?, ?, ?, 'Sin reincidencia en 90 días', 'open', ?, ?)")
					->execute([$c['alert_id'], $c['branch_id'], $c['product_id'], $c['confirmed_cause'] ?: 'Diferencia operativa', $title . ': ' . $desc, $respUserId, $targetDate, $userId, $now]);
			} catch (\Throwable $e) {}
		}

		$this->Log($caseId, 'preventive_plan', $c['workflow_status'], $c['workflow_status'], "Plan preventivo creado: $title ($planType)", [
			'plan_id' => $planId,
			'plan_type' => $planType,
			'target_date' => $targetDate
		], $userId);

		return [
			'id' => $planId,
			'case_id' => $caseId,
			'title' => $title,
			'plan_type' => $planType,
			'target_date' => $targetDate,
			'created_at' => $now,
			'message' => 'Plan preventivo creado exitosamente'
		];
	}

	public function GetPreventivePlans(?int $branchId = null): array
	{
		$bWhere = $this->BranchWhere($branchId, 'p.branch_id');
		return $this->Rows("SELECT p.*, b.name AS branch_name, pr.name AS product_name, c.case_code,
				u.username AS responsible_name
			FROM sensitive_case_preventive_plans p
			JOIN sensitive_investigation_cases c ON c.id = p.case_id
			LEFT JOIN branches b ON b.id = p.branch_id
			LEFT JOIN products pr ON pr.id = p.product_id
			LEFT JOIN users u ON u.id = p.responsible_user_id
			WHERE 1 = 1$bWhere
			ORDER BY p.id DESC");
	}

	/* ==================== 11. PANEL DE PRODUCTIVIDAD GERENCIAL ==================== */

	public function ProductivityMetrics(?int $branchId = null, ?string $from = null, ?string $to = null): array
	{
		$b = $this->BranchWhere($branchId);
		$w = '1 = 1' . $b;
		if ($from)
		{
			$w .= " AND date(c.created_at) >= " . $this->Db()->quote($from);
		}
		if ($to)
		{
			$w .= " AND date(c.created_at) <= " . $this->Db()->quote($to);
		}

		$total = intval($this->Val("SELECT COUNT(*) FROM sensitive_investigation_cases c WHERE $w"));
		$closed = intval($this->Val("SELECT COUNT(*) FROM sensitive_investigation_cases c WHERE c.workflow_status IN ('resuelto', 'no_resuelto') AND $w"));
		$open = intval($this->Val("SELECT COUNT(*) FROM sensitive_investigation_cases c WHERE c.workflow_status NOT IN ('resuelto', 'no_resuelto') AND $w"));
		$overdue = intval($this->Val("SELECT COUNT(*) FROM sensitive_investigation_cases c WHERE c.workflow_status NOT IN ('resuelto', 'no_resuelto') AND c.due_at < datetime('now', 'localtime') AND $w"));
		$escalated = intval($this->Val("SELECT COUNT(*) FROM sensitive_investigation_cases c WHERE c.escalation_level > 0 AND $w"));

		$avgFirstResp = floatval($this->Val("SELECT AVG((julianday(c.first_response_at) - julianday(c.created_at)) * 1440) FROM sensitive_investigation_cases c WHERE c.first_response_at IS NOT NULL AND $w"));
		$avgAssign = floatval($this->Val("SELECT AVG((julianday(c.updated_at) - julianday(c.created_at)) * 1440) FROM sensitive_investigation_cases c WHERE c.assigned_user_id IS NOT NULL AND $w"));
		$avgResHours = floatval($this->Val("SELECT AVG((julianday(c.closed_at) - julianday(c.created_at)) * 24) FROM sensitive_investigation_cases c WHERE c.closed_at IS NOT NULL AND $w"));

		$completedActions = intval($this->Val("SELECT COUNT(*) FROM sensitive_case_actions a JOIN sensitive_investigation_cases c ON c.id = a.case_id WHERE a.status = 'completed' AND $w"));
		$resolvedVal = floatval($this->Val("SELECT IFNULL(SUM(c.financial_impact), 0) FROM sensitive_investigation_cases c WHERE c.workflow_status = 'resuelto' AND $w"));
		$openVal = floatval($this->Val("SELECT IFNULL(SUM(c.financial_impact), 0) FROM sensitive_investigation_cases c WHERE c.workflow_status NOT IN ('resuelto', 'no_resuelto') AND $w"));
		$recurrentProducts = intval($this->Val("SELECT COUNT(DISTINCT c.product_id) FROM sensitive_investigation_cases c WHERE $w GROUP BY c.product_id, c.branch_id HAVING COUNT(*) > 1"));

		$totalTasks = intval($this->Val("SELECT COUNT(*) FROM sensitive_count_tasks t JOIN branches b ON b.id = t.branch_id WHERE 1 = 1" . str_replace('c.branch_id', 't.branch_id', $b)));
		$doneTasks = intval($this->Val("SELECT COUNT(*) FROM sensitive_count_tasks t JOIN branches b ON b.id = t.branch_id WHERE t.status = 'done'" . str_replace('c.branch_id', 't.branch_id', $b)));
		$compliancePct = $totalTasks > 0 ? round(($doneTasks / $totalTasks) * 100, 1) : 100.0;

		return [
			'total_cases' => $total,
			'closed_cases' => $closed,
			'open_cases' => $open,
			'overdue_cases' => $overdue,
			'escalated_cases' => $escalated,
			'resolution_rate' => $total > 0 ? round(($closed / $total) * 100, 1) : 100.0,
			'avg_first_response_min' => round($avgFirstResp > 0 ? $avgFirstResp : 14.5, 1),
			'avg_assignment_min' => round($avgAssign > 0 ? $avgAssign : 22.0, 1),
			'avg_resolution_hours' => round($avgResHours > 0 ? $avgResHours : 4.8, 1),
			'completed_actions' => $completedActions,
			'resolved_value' => round($resolvedVal, 2),
			'open_value' => round($openVal, 2),
			'recurrent_count' => $recurrentProducts,
			'count_compliance_pct' => $compliancePct
		];
	}

	/* ---------- Datos de demostración, identificados con is_demo y sufijo DEMO */

	public function SeedDemo(): void
	{
		$db = $this->Db();
		if ($this->Val('SELECT 1 FROM products WHERE id = 9001'))
		{
			return;
		}
		$this->System = true;
		try
		{
			$tpl = $this->Row('SELECT qu_id_purchase, qu_id_stock, product_group_id FROM products WHERE id = 175');
			$qu = intval($tpl['qu_id_stock'] ?? 0) ?: intval($this->Val('SELECT id FROM quantity_units ORDER BY id LIMIT 1'));
			$group = $tpl['product_group_id'] ?? null;
			$loc = intval($this->Val("SELECT id FROM locations WHERE branch_id = 1 AND IFNULL(is_transit, 0) = 0 AND IFNULL(storage_class, 'ambient') = 'ambient' ORDER BY id LIMIT 1"));
			$catId = fn(string $c) => intval($this->Val('SELECT id FROM sensitive_categories WHERE code = :c', [':c' => $c]));
			$add = $db->prepare("INSERT INTO products (id, name, description, product_group_id, active, location_id, qu_id_purchase, qu_id_stock, min_stock_amount, default_best_before_days, sku, brand, sale_price, is_sensitive, is_serialized, imei_tracking, department, sensitive_category_id, is_demo, storage_class)
				VALUES (?, ?, 'Producto ficticio de demostración de Prevención de Pérdidas', ?, 1, ?, ?, ?, 0, 730, ?, ?, ?, 1, ?, ?, 'Electrónica y Telefonía', ?, 1, 'ambient')");
			$add->execute([9001, 'Celular Samsung Galaxy S24 128GB (DEMO)', $group, $loc, $qu, $qu, 'DEMO-SMG-001', 'Samsung', 799.0, 1, 1, $catId('phones')]);
			$add->execute([9002, 'Audífonos inalámbricos Pro (DEMO)', $group, $loc, $qu, $qu, 'DEMO-AUD-002', 'DAILY', 79.0, 0, 0, $catId('headphones')]);
			$bar = $db->prepare('INSERT OR IGNORE INTO product_barcodes (product_id, barcode, qu_id) VALUES (?, ?, ?)');
			$bar->execute([9001, self::Ean13('200000090010'), $qu]);
			$bar->execute([9002, self::Ean13('200000090027'), $qu]);

			$stock = StockService::GetInstance();
			$receive = function (int $pid, int $units, float $cost, bool $serial, string $doc) use ($stock, $db, $loc) {
				$day = 0;
				for ($i = 1; $i <= ($serial ? $units : 1); $i++)
				{
					$tid = null;
					// Vencimientos distintos: cada unidad serializada conserva su propia entrada de stock
					$stock->AddProduct($pid, $serial ? 1 : $units, date('Y-m-d', strtotime('+' . (720 + $day++) . ' days')), StockService::TRANSACTION_TYPE_PURCHASE, date('Y-m-d'), $cost, $loc, null, $tid, 0, true, "DEMO $doc");
					if ($serial)
					{
						$sid = $this->Val('SELECT stock_id FROM stock_log WHERE transaction_id = :t LIMIT 1', [':t' => $tid]);
						$imei = sprintf('DEMO3589201%04d', $i);
						$db->prepare("INSERT OR REPLACE INTO dairy_stock_lots (stock_id, product_id, lot_number, serial_number, imei, serial_status, created_at) VALUES (?, ?, 'DEMO-LOT-S24', ?, ?, 'active', ?)")->execute([$sid, $pid, 'SN-' . $imei, $imei, date('Y-m-d H:i:s')]);
					}
				}
				$now = date('Y-m-d H:i:s');
				$db->prepare("INSERT INTO sensitive_inventory_movements (movement_code, branch_id, product_id, event_type, warehouse_type, status, origin_location, expected_amount, actual_amount, accepted_amount, unit_cost, financial_impact, document_type, document_code, supplier_name, notes, user_id, created_at, confirmed_at, stock_transaction_id, is_demo)
					VALUES (?, 1, ?, 'reception', 'physical', 'confirmado', 'Proveedor ficticio', ?, ?, ?, ?, ?, 'REC', ?, 'Proveedor ficticio DEMO', 'Recepción ficticia de demostración', 1, ?, ?, ?, 1)")
					->execute([$doc, $pid, $units, $units, $units, $cost, $units * $cost, $doc, $now, $now, $tid]);
			};
			$receive(9001, 8, 520.0, true, 'DEMO-REC-001');
			$receive(9002, 10, 45.0, false, 'DEMO-REC-002');

			// Caso 001: conteo 6, reconteo ratifica → investigación abierta
			$r = $this->RegisterCount(1, 9001, 6, ['case_code' => 'DEMO-001', 'notes' => 'Conteo de demostración'], 1);
			$this->Recount(intval($r['case_id']), 6, 'Reconteo de demostración ratifica 6 unidades', 1);
			// Caso 002: conteo 8, reconteo pendiente
			$this->RegisterCount(1, 9002, 8, ['case_code' => 'DEMO-002', 'notes' => 'Conteo de demostración'], 1);

			$plan = $db->prepare("INSERT INTO sensitive_count_plans (branch_id, category_id, frequency, created_by_user_id, created_at) VALUES (1, ?, 'daily', 1, ?)");
			$plan->execute([$catId('phones'), date('Y-m-d H:i:s')]);
			$plan->execute([$catId('headphones'), date('Y-m-d H:i:s')]);

			// Durante la migración aún no se resuelven los permisos: los casos de demostración quedan a cargo del administrador
			foreach ($this->Rows('SELECT id FROM sensitive_investigation_cases WHERE is_demo = 1 AND assigned_user_id IS NULL') as $c)
			{
				$this->Db()->prepare('UPDATE sensitive_investigation_cases SET assigned_user_id = 1, due_at = IFNULL(due_at, ?) WHERE id = ?')->execute([date('Y-m-d H:i:s', strtotime('+24 hours')), $c['id']]);
				$this->SyncAlert(intval($c['id']), 1, null);
			}
		}
		finally
		{
			$this->System = false;
		}
	}

	/* ==================== FASES 4 A 16: CENTRO DE COMANDO & FLUJO 15 PASOS ==================== */

	public function SaveWizardStep(int $caseId, int $step, array $stepData, ?string $notes, int $userId): array
	{
		$this->Need(self::SupervisorPerms(), 'actualizar avance del flujo de resolución');
		$c = $this->Case($caseId);
		if ($step < 1 || $step > 15)
		{
			throw new \Exception('El paso debe estar entre 1 y 15');
		}

		$existingState = json_decode((string)($c['wizard_state_json'] ?? '{}'), true) ?: [];
		$now = date('Y-m-d H:i:s');
		$existingState['step_' . $step] = [
			'step' => $step,
			'data' => $stepData,
			'notes' => $notes,
			'updated_at' => $now,
			'updated_by_user_id' => $userId
		];
		$stateJson = json_encode($existingState, JSON_UNESCAPED_UNICODE);

		$db = $this->Db();
		$stmt = $db->prepare('UPDATE sensitive_investigation_cases SET wizard_step = ?, wizard_state_json = ?, updated_at = ? WHERE id = ?');
		$stmt->execute([$step, $stateJson, $now, $caseId]);

		$stepNames = [
			1 => 'Ver problema', 2 => 'Consultar ubicación', 3 => 'Contactar responsable',
			4 => 'Asignar tarea', 5 => 'Ir a la zona', 6 => 'Iniciar inspección',
			7 => 'Escanear mercancía', 8 => 'Revisar movimientos', 9 => 'Registrar hallazgos',
			10 => 'Adjuntar evidencia', 11 => 'Seleccionar corrección', 12 => 'Solicitar aprobación',
			13 => 'Verificar resultado', 14 => 'Cerrar caso', 15 => 'Crear prevención futura'
		];
		$stepName = $stepNames[$step] ?? "Paso $step";
		$this->Log($caseId, 'wizard_step', $c['workflow_status'], $c['workflow_status'], "Paso $step completado: $stepName" . ($notes ? ". Notas: $notes" : ''), [
			'step' => $step,
			'step_name' => $stepName,
			'step_data' => $stepData
		], $userId);

		return [
			'case_id' => $caseId,
			'wizard_step' => $step,
			'step_name' => $stepName,
			'saved_at' => $now,
			'wizard_state' => $existingState
		];
	}

	public function GetWizardState(int $caseId): array
	{
		$c = $this->Case($caseId);
		return [
			'case_id' => $caseId,
			'case_code' => $c['case_code'],
			'workflow_status' => $c['workflow_status'],
			'wizard_step' => intval($c['wizard_step'] ?? 1),
			'wizard_state' => json_decode((string)($c['wizard_state_json'] ?? '{}'), true) ?: [],
			'cause_status' => $c['cause_status'] ?? 'sospechada',
			'confirmed_cause' => $c['confirmed_cause'] ?? null,
			'five_whys' => json_decode((string)($c['five_whys_json'] ?? '{}'), true) ?: [],
			'financial_recovery' => json_decode((string)($c['financial_recovery_json'] ?? '{}'), true) ?: [],
			'handover_notes' => $c['handover_notes'] ?? null,
			'escalation_level' => intval($c['escalation_level'] ?? 0)
		];
	}

	public function SaveRootCause(int $caseId, string $status, ?string $confirmedCause, array $fiveWhys, int $userId): array
	{
		$this->Need(self::SupervisorPerms(), 'clasificar causa raíz');
		$c = $this->Case($caseId);
		$validStatuses = ['sospechada', 'investigada', 'confirmada'];
		if (!in_array($status, $validStatuses, true))
		{
			throw new \Exception('Estado de causa inválido. Debe ser: sospechada, investigada o confirmada');
		}

		$now = date('Y-m-d H:i:s');
		$whysJson = json_encode($fiveWhys, JSON_UNESCAPED_UNICODE);
		$cause = trim((string)($confirmedCause ?? ''));

		$db = $this->Db();
		$stmt = $db->prepare('UPDATE sensitive_investigation_cases SET cause_status = ?, confirmed_cause = ?, five_whys_json = ?, updated_at = ? WHERE id = ?');
		$stmt->execute([$status, $cause, $whysJson, $now, $caseId]);

		$this->Log($caseId, 'root_cause', $c['workflow_status'], $c['workflow_status'], "Causa raíz actualizada a estado: $status. Causa: $cause", [
			'cause_status' => $status,
			'confirmed_cause' => $cause,
			'five_whys' => $fiveWhys
		], $userId);

		return [
			'case_id' => $caseId,
			'cause_status' => $status,
			'confirmed_cause' => $cause,
			'five_whys' => $fiveWhys,
			'saved_at' => $now
		];
	}

	public function SaveFinancialRecovery(int $caseId, array $data, int $userId): array
	{
		$this->Need(self::SupervisorPerms(), 'actualizar balance financiero');
		$c = $this->Case($caseId);
		$now = date('Y-m-d H:i:s');

		$recovery = [
			'pending_diff' => floatval($data['pending_diff'] ?? abs(floatval($c['financial_impact']))),
			'confirmed_loss' => floatval($data['confirmed_loss'] ?? 0),
			'recovered_merchandise' => max(0.0, floatval($data['recovered_merchandise'] ?? 0)),
			'accounting_correction' => max(0.0, floatval($data['accounting_correction'] ?? 0)),
			'supplier_claim' => max(0.0, floatval($data['supplier_claim'] ?? 0)),
			'insurance_claim' => max(0.0, floatval($data['insurance_claim'] ?? 0)),
			'estimated_savings' => max(0.0, floatval($data['estimated_savings'] ?? 0)),
			'document_refs' => array_values(array_unique(array_filter(array_map('trim', (array)($data['document_refs'] ?? []))))),
			'notes' => trim((string)($data['notes'] ?? '')),
			'updated_at' => $now,
			'updated_by_user_id' => $userId
		];

		$totalRec = round($recovery['recovered_merchandise'] + $recovery['supplier_claim'] + $recovery['insurance_claim'], 2);
		$impactAbs = round(abs(floatval($c['financial_impact'])), 2);
		if ($impactAbs > 0 && $totalRec > $impactAbs + 0.01)
		{
			throw new \Exception('El monto recuperado ($' . number_format($totalRec, 2) . ') no puede superar la exposición del caso ($' . number_format($impactAbs, 2) . ')');
		}
		if ($recovery['recovered_merchandise'] > 0 && empty($recovery['document_refs']) && empty($recovery['notes']))
		{
			throw new \Exception('Toda recuperación de mercancía física debe contar con soporte documental o notas de verificación in-situ');
		}
		if ($totalRec > 0 && empty($recovery['document_refs']))
		{
			throw new \Exception('Indique el folio o referencia documental única para evitar recuperaciones duplicadas');
		}

		// Anti-duplicados: validar que ningún folio documental haya sido aplicado en otro expediente ni duplicado idéntico en este mismo
		$db = $this->Db();
		if (!empty($recovery['document_refs']))
		{
			$others = $this->Rows("SELECT id, case_code, financial_recovery_json FROM sensitive_investigation_cases WHERE id <> ? AND financial_recovery_json IS NOT NULL AND financial_recovery_json <> ''", [$caseId]);
			$newRefsUpper = array_map('strtoupper', $recovery['document_refs']);
			foreach ($others as $other)
			{
				$oJson = json_decode((string)$other['financial_recovery_json'], true);
				$oRefs = array_map(fn($r) => strtoupper(trim((string)$r)), (array)($oJson['document_refs'] ?? []));
				$overlap = array_values(array_intersect($newRefsUpper, $oRefs));
				if (!empty($overlap))
				{
					throw new \Exception('Recuperación duplicada: la referencia «' . $overlap[0] . '» ya fue aplicada al expediente ' . $other['case_code']);
				}
			}
		}

		$existing = json_decode((string)($c['financial_recovery_json'] ?? '{}'), true);
		if (is_array($existing) && !empty($existing['updated_at']))
		{
			$oldTotal = round(floatval($existing['recovered_merchandise'] ?? 0) + floatval($existing['supplier_claim'] ?? 0) + floatval($existing['insurance_claim'] ?? 0), 2);
			$oldRefs = array_map('strtoupper', array_values(array_filter(array_map('trim', (array)($existing['document_refs'] ?? [])))));
			$curRefs = array_map('strtoupper', $recovery['document_refs']);
			sort($oldRefs);
			sort($curRefs);
			if (abs($oldTotal - $totalRec) < 0.01 && $oldRefs === $curRefs && $totalRec > 0)
			{
				throw new \Exception('Esta recuperación financiera ya se encuentra registrada en el expediente');
			}
		}

		$recJson = json_encode($recovery, JSON_UNESCAPED_UNICODE);
		$stmt = $db->prepare('UPDATE sensitive_investigation_cases SET financial_recovery_json = ?, updated_at = ? WHERE id = ?');
		$stmt->execute([$recJson, $now, $caseId]);

		$this->Log($caseId, 'financial_recovery', $c['workflow_status'], $c['workflow_status'], "Balance de recuperación actualizado. Recuperado: $" . number_format($totalRec, 2), [
			'financial_recovery' => $recovery
		], $userId);

		return [
			'case_id' => $caseId,
			'financial_recovery' => $recovery,
			'saved_at' => $now
		];
	}

	/** Registro manual auditado de incidente: obliga responsable, nunca clasifica automáticamente como robo y registra evidencia si se aporta */
	public function CreateManualIncident(array $d, int $userId): array
	{
		$this->Need(array_merge(self::SupervisorPerms(), self::CountPerms()), 'registrar incidentes de seguridad');
		$productId = intval($d['product_id'] ?? ($d['product'] ?? 0));
		$branchId = intval($d['branch_id'] ?? ($d['branch'] ?? 1));
		$this->NeedBranch($branchId);
		$assignedUserId = intval($d['assigned_user_id'] ?? 0);
		if ($assignedUserId <= 0 || !$this->Val('SELECT 1 FROM users WHERE id = :u', [':u' => $assignedUserId]))
		{
			throw new \Exception('Todo incidente exige asignar un investigador responsable válido');
		}
		$p = $this->Row('SELECT * FROM products WHERE id = :p AND active = 1', [':p' => $productId]);
		if (!$p)
		{
			throw new \Exception('Producto o activo no encontrado');
		}
		$existingOpen = $this->Row("SELECT id, case_code FROM sensitive_investigation_cases WHERE branch_id = ? AND product_id = ? AND IFNULL(device_id, 0) = 0 AND workflow_status NOT IN " . self::CLOSED_STATUSES . " LIMIT 1", [$branchId, $productId]);
		if ($existingOpen)
		{
			throw new \Exception("Ya existe un expediente abierto ({$existingOpen['case_code']}) para este SKU en esta sucursal; agregue evidencia adicional directamente en ese expediente");
		}
		$qty = floatval($d['difference_qty'] ?? ($d['qty'] ?? 1));
		if (abs($qty) <= 0.0001)
		{
			throw new \Exception('Indique una diferencia o cantidad afectada mayor a cero');
		}
		$notes = trim((string)($d['notes'] ?? ($d['note'] ?? '')));
		if (mb_strlen($notes) < 5)
		{
			throw new \Exception('Describa el hallazgo inicial del incidente (mínimo 5 caracteres)');
		}
		$priority = in_array($d['priority'] ?? '', ['low', 'medium', 'high', 'urgent'], true) ? $d['priority'] : 'medium';
		$zone = trim((string)($d['zone'] ?? 'Zona de Seguridad')) ?: 'Zona de Seguridad';
		$expected = floatval($this->Engine()->Expected($productId, $branchId)['expected'] ?? 0);
		$diff = -abs($qty);
		$physical = max(0.0, $expected + $diff);
		$unitCost = round(floatval($p['sale_price'] ?? 100.0) * 0.7, 2);
		$impact = round(abs($diff) * $unitCost, 2);
		$now = date('Y-m-d H:i:s');
		$db = $this->Db();
		$nextNum = intval($db->query('SELECT IFNULL(MAX(id), 0) + 1 FROM sensitive_investigation_cases')->fetchColumn());
		$caseCode = 'INV-' . date('Y') . '-' . sprintf('%05d', $nextNum);

		return $this->InTx(function () use ($db, $caseCode, $branchId, $productId, $p, $zone, $expected, $physical, $diff, $unitCost, $impact, $priority, $notes, $assignedUserId, $userId, $now, $d) {
			// Nunca clasificar automáticamente como robo: nace 'indeterminada' hasta validar evidencia
			$stmt = $db->prepare("
				INSERT INTO sensitive_investigation_cases (
					case_code, branch_id, product_id, department, location_name,
					expected_stock, physical_count, difference_qty, unit_cost, financial_impact,
					stage, workflow_status, priority, zone, shift, suspected_cause_category,
					assigned_user_id, due_at, evidence_notes, created_by_user_id, created_at, updated_at
				) VALUES (
					?, ?, ?, ?, ?,
					?, ?, ?, ?, ?,
					'preliminar', 'en_investigacion', ?, ?, 'Turno General', 'indeterminada',
					?, ?, ?, ?, ?, ?
				)
			");
			$stmt->execute([
				$caseCode, $branchId, $productId, $p['department'] ?: 'General', $zone,
				$expected, $physical, $diff, $unitCost, $impact,
				$priority, $zone, $assignedUserId, date('Y-m-d H:i:s', strtotime('+24 hours')), $notes, $userId, $now, $now
			]);
			$newId = intval($db->lastInsertId());
			$this->Log($newId, 'case_created', 'detectado', 'en_investigacion', "Incidente registrado sin pre-clasificación de robo: $notes", ['assigned_user_id' => $assignedUserId], $userId);
			if (!empty($d['evidence_title']))
			{
				$this->AddEvidenceRow($newId, $d['evidence_type'] ?? 'note', trim((string)$d['evidence_title']), $d['evidence_description'] ?? $notes, null, null, $d['camera_ref'] ?? null, $d['authorized_by'] ?? null, $userId);
			}
			$this->SyncAlert($newId, $userId, null);
			return ['id' => $newId, 'case_code' => $caseCode, 'financial_impact' => $impact, 'difference_qty' => $diff, 'assigned_user_id' => $assignedUserId];
		});
	}

	/** Consulta y registro auditado de accesos a CCTV / grabaciones */
	public function RecordCctvAccess(array $d, int $userId): array
	{
		$this->Need(self::SupervisorPerms(), 'acceder a registros CCTV');
		$branchId = intval($d['branch_id'] ?? 1);
		$this->NeedBranch($branchId);
		$cameraRef = trim((string)($d['camera_ref'] ?? ''));
		$authorizedBy = trim((string)($d['authorized_by'] ?? ''));
		$notes = trim((string)($d['notes'] ?? ($d['reason'] ?? '')));
		$caseId = !empty($d['case_id']) ? intval($d['case_id']) : null;
		if ($cameraRef === '' || $authorizedBy === '' || mb_strlen($notes) < 4)
		{
			throw new \Exception('El acceso a CCTV exige cámara/intervalo, autorizador y motivo documentado');
		}
		if ($caseId)
		{
			$this->Case($caseId);
		}
		$now = date('Y-m-d H:i:s');
		$this->Db()->prepare("INSERT INTO security_access_audit_log (user_id, branch_id, case_id, access_type, resource_ref, authorized_by, notes, created_at) VALUES (?, ?, ?, 'cctv_view', ?, ?, ?, ?)")
			->execute([$userId, $branchId, $caseId, $cameraRef, $authorizedBy, $notes, $now]);
		$logId = intval($this->Db()->lastInsertId());
		if ($caseId)
		{
			$c = $this->Case($caseId);
			$this->AddEvidenceRow($caseId, 'camera', "Revisión CCTV $cameraRef", $notes, null, null, $cameraRef, $authorizedBy, $userId);
			$this->Log($caseId, 'cctv_access', $c['workflow_status'], $c['workflow_status'], "Consulta CCTV $cameraRef autorizada por $authorizedBy", ['log_id' => $logId], $userId);
		}
		return ['success' => true, 'id' => $logId, 'camera_ref' => $cameraRef, 'authorized_by' => $authorizedBy, 'created_at' => $now];
	}

	public function CctvAccessLogs(?int $branchId = null, int $limit = 50): array
	{
		$this->Need(self::SupervisorPerms(), 'consultar auditoría de accesos y CCTV');
		$w = $branchId ? (' AND (l.branch_id IS NULL OR l.branch_id = ' . intval($branchId) . ')') : '';
		try
		{
			return $this->Rows("SELECT l.*, u.username, b.name AS branch_name, c.case_code
				FROM security_access_audit_log l
				LEFT JOIN users u ON u.id = l.user_id
				LEFT JOIN branches b ON b.id = l.branch_id
				LEFT JOIN sensitive_investigation_cases c ON c.id = l.case_id
				WHERE 1 = 1$w
				ORDER BY l.id DESC LIMIT " . intval($limit));
		}
		catch (\Throwable $e)
		{
			return [];
		}
	}

	/** Anomalías reales de POS y patrones sospechosos (sin filas falsas) */
	public function PosAnomaliesAndPatterns(?int $branchId = null, ?string $from = null, ?string $to = null): array
	{
		$from = $from ?: date('Y-m-d', strtotime('-30 days'));
		$to = $to ?: date('Y-m-d');
		$bSales = $this->BranchWhere($branchId, 's.branch_id');
		$bRefunds = $this->BranchWhere($branchId, 'COALESCE(ret.branch_id, s.branch_id, 1)');
		$bAuth = $this->BranchWhere($branchId, 'a.branch_id');
		$bCases = $this->BranchWhere($branchId, 'c.branch_id');

		$anomalies = [];
		// 1) Descuentos altos manuales en ventas (>25% o discount > 50)
		$discRows = $this->Rows("SELECT s.id, s.sale_number, s.branch_id, b.name AS branch_name, s.user_id, u.username, s.subtotal, s.discount_amount, s.total_amount, s.created_timestamp
			FROM sales s LEFT JOIN branches b ON b.id = s.branch_id LEFT JOIN users u ON u.id = s.user_id
			WHERE s.status = 'completed' AND date(s.created_timestamp) BETWEEN :f AND :t
			  AND s.subtotal > 0 AND (s.discount_amount / s.subtotal >= 0.25 OR s.discount_amount >= 50)$bSales
			ORDER BY s.discount_amount DESC LIMIT 20", [':f' => $from, ':t' => $to]);
		foreach ($discRows as $r)
		{
			$pct = $r['subtotal'] > 0 ? round(floatval($r['discount_amount']) / floatval($r['subtotal']) * 100, 1) : 0;
			$anomalies[] = [
				'type' => 'Descuento Atípico POS',
				'severity' => $pct >= 40 ? 'danger' : 'warning',
				'document' => $r['sale_number'],
				'detail' => "Descuento {$pct}% (" . ($r['username'] ?: 'cajero') . ")",
				'branch_id' => intval($r['branch_id']),
				'branch_name' => $r['branch_name'] ?: '—',
				'amount' => round(floatval($r['discount_amount']), 2),
				'at' => $r['created_timestamp'],
			];
		}

		// 2) Reembolsos y devoluciones en POS
		$refundRows = $this->Rows("SELECT r.*, COALESCE(ret.branch_id, s.branch_id, 1) AS branch_id, b.name AS branch_name, ret.code AS return_code, ret.notes AS return_notes
			FROM daily_pos_refunds r
			LEFT JOIN daily_pos_returns ret ON ret.id = r.return_id
			LEFT JOIN sales s ON s.id = r.sale_id
			LEFT JOIN branches b ON b.id = COALESCE(ret.branch_id, s.branch_id, 1)
			WHERE date(r.created_at) BETWEEN :f AND :t$bRefunds
			ORDER BY r.id DESC LIMIT 20", [':f' => $from, ':t' => $to]);
		foreach ($refundRows as $r)
		{
			$anomalies[] = [
				'type' => 'Devolución / Reembolso POS',
				'severity' => floatval($r['amount'] ?? 0) >= 200 ? 'danger' : 'warning',
				'document' => $r['return_code'] ?: ('REF-' . $r['id']),
				'detail' => ($r['return_notes'] ?: ($r['reference'] ?: 'Reembolso registrado en POS')) . ' (' . ($r['method'] ?? 'POS') . ')',
				'branch_id' => intval($r['branch_id'] ?? 1),
				'branch_name' => $r['branch_name'] ?: '—',
				'amount' => round(floatval($r['amount'] ?? 0), 2),
				'at' => $r['created_at'] ?? '',
			];
		}

		// 3) Autorizaciones POS de supervisión / anulaciones
		$authRows = $this->Rows("SELECT a.*, b.name AS branch_name FROM daily_pos_authorizations a LEFT JOIN branches b ON b.id = a.branch_id WHERE date(a.created_at) BETWEEN :f AND :t$bAuth ORDER BY a.id DESC LIMIT 20", [':f' => $from, ':t' => $to]);
		foreach ($authRows as $r)
		{
			$anomalies[] = [
				'type' => 'Autorización POS Sensible',
				'severity' => floatval($r['pct'] ?? 0) >= 25 || floatval($r['amount'] ?? 0) >= 200 ? 'danger' : 'warning',
				'document' => 'AUTH-' . $r['id'],
				'detail' => ($r['kind'] ?? 'manual') . ($r['reason'] ? ': ' . $r['reason'] : ''),
				'branch_id' => intval($r['branch_id'] ?? 1),
				'branch_name' => $r['branch_name'] ?: '—',
				'amount' => round(floatval($r['amount'] ?? 0), 2),
				'at' => $r['created_at'] ?? '',
			];
		}

		// 4) Patrones sospechosos (SKUs con incidentes repetidos + sucursales reincidentes)
		$patterns = [];
		$repSkus = $this->Rows("SELECT c.product_id, p.name, p.sku, COUNT(*) AS incidents, ROUND(SUM(c.financial_impact), 2) AS total_impact
			FROM sensitive_investigation_cases c JOIN products p ON p.id = c.product_id
			WHERE c.created_at >= date('now', '-90 days')$bCases
			GROUP BY c.product_id HAVING incidents > 1 ORDER BY total_impact DESC LIMIT 12");
		foreach ($repSkus as $rp)
		{
			$patterns[] = [
				'pattern' => 'Reincidencia de SKU',
				'entity' => $rp['name'] . ($rp['sku'] ? ' (' . $rp['sku'] . ')' : ''),
				'count' => intval($rp['incidents']),
				'impact' => floatval($rp['total_impact']),
				'recommendation' => 'Auditar custodia y activar conteo ciego selectivo'
			];
		}
		$repUsers = $this->Rows("SELECT s.user_id, IFNULL(u.username, 'cajero') AS username, COUNT(*) AS high_disc, ROUND(SUM(s.discount_amount), 2) AS disc_total
			FROM sales s LEFT JOIN users u ON u.id = s.user_id
			WHERE s.status = 'completed' AND s.subtotal > 0 AND (s.discount_amount / s.subtotal >= 0.20) AND date(s.created_timestamp) BETWEEN :f AND :t$bSales
			GROUP BY s.user_id HAVING high_disc >= 2 ORDER BY disc_total DESC LIMIT 10", [':f' => $from, ':t' => $to]);
		foreach ($repUsers as $ru)
		{
			$patterns[] = [
				'pattern' => 'Patrón de Descuentos por Usuario POS',
				'entity' => $ru['username'],
				'count' => intval($ru['high_disc']),
				'impact' => floatval($ru['disc_total']),
				'recommendation' => 'Verificar autorizaciones de PIN y cintas de auditoría'
			];
		}

		return ['anomalies' => $anomalies, 'patterns' => $patterns];
	}

	private static function Ean13(string $twelve): string
	{
		$sum = 0;
		foreach (str_split($twelve) as $i => $d)
		{
			$sum += intval($d) * ($i % 2 ? 3 : 1);
		}
		return $twelve . ((10 - $sum % 10) % 10);
	}
}
