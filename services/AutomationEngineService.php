<?php

namespace Grocy\Services;

/**
 * Motor de automatización: SI ocurre un evento y se cumple la condición → ENTONCES se ejecuta una acción.
 *
 * Eventos: alert_created, alert_escalated (Centro de Alertas) y waste_approved (Mermas).
 * Condición (condition_json): {"campo": valor | [valores] | ">8" | ">=8" | "<8" | "<=8" | "!=x"}; un campo ausente en el evento no cumple.
 * Alcance: sucursal, departamento y horario (active_from–active_to). Fuera de horario la ejecución queda diferida.
 * Idempotencia: una ejecución por regla + evento + entidad (automation_executions.idempotency_key único).
 * Enfriamiento: no repite la acción para la misma regla, sucursal y producto dentro de cooldown_minutes.
 * Reintentos: una acción que falla queda «retrying» hasta max_retries; RetryPending() la reintenta (programador).
 * Nunca lanza excepciones al flujo que dispara el evento: el error queda registrado en la ejecución.
 */
class AutomationEngineService extends BaseService
{
	const EVENTS = ['alert_created' => 'Alerta creada', 'alert_escalated' => 'Alerta escalada', 'waste_approved' => 'Merma aprobada'];
	const ACTIONS = ['create_task' => 'Crear tarea', 'notify' => 'Notificar', 'assign_alert' => 'Asignar la alerta', 'set_priority' => 'Cambiar prioridad', 'escalate_alert' => 'Escalar la alerta', 'quarantine_lot' => 'Poner lote en cuarentena'];
	const PRIORITIES = ['P1', 'P2', 'P3', 'P4'];

	private function Db(): \PDO
	{
		return DatabaseService::GetInstance()->GetDbConnectionRaw();
	}

	private function Row(string $sql, array $p = []): ?array
	{
		$s = $this->Db()->prepare($sql);
		$s->execute($p);
		return $s->fetch(\PDO::FETCH_ASSOC) ?: null;
	}

	private function Rows(string $sql, array $p = []): array
	{
		$s = $this->Db()->prepare($sql);
		$s->execute($p);
		return $s->fetchAll(\PDO::FETCH_ASSOC);
	}

	/** Evento con una alerta: el contexto se toma del registro (no de lo que diga quien dispara) */
	public function OnAlert(string $event, int $alertId): array
	{
		$a = $this->Row('SELECT id, rule_code, branch_id, product_id, category, alert_type, department, severity, priority, status, title, value, source, owner_user_id FROM dairy_alerts WHERE id = :i', [':i' => $alertId]);
		if (!$a)
		{
			return [];
		}
		$suffix = $event === 'alert_escalated' ? ':' . intval($this->Row('SELECT escalation_level FROM dairy_alerts WHERE id = :i', [':i' => $alertId])['escalation_level'] ?? 0) : '';
		return $this->TriggerEvent($event, ['alert_id' => $alertId] + $a, "alert:$alertId$suffix");
	}

	public function TriggerEvent(string $eventTrigger, array $payload = [], ?string $entityKey = null): array
	{
		try
		{
			$rules = $this->Rows("SELECT * FROM automation_rules WHERE event_trigger = :e AND is_active = 1 ORDER BY priority, id", [':e' => $eventTrigger]);
		}
		catch (\Throwable)
		{
			return [];
		}
		$entityKey = $entityKey ?? $this->EntityKey($payload);
		$executed = [];
		foreach ($rules as $rule)
		{
			if (!$this->InScope($rule, $payload) || !$this->Matches(json_decode((string) $rule['condition_json'], true), $payload))
			{
				continue;
			}
			try
			{
				$executed[] = $this->Execute($rule, $eventTrigger, $payload, "{$rule['id']}|$eventTrigger|$entityKey");
			}
			catch (\Throwable $e)
			{
				$executed[] = ['rule_id' => intval($rule['id']), 'rule_name' => $rule['name'], 'status' => 'error', 'error' => $e->getMessage()];
			}
		}
		return $executed;
	}

	private function EntityKey(array $p): string
	{
		foreach (['alert_id' => 'alert', 'waste_record_id' => 'waste'] as $k => $t)
		{
			if (!empty($p[$k]))
			{
				return $t . ':' . intval($p[$k]);
			}
		}
		ksort($p);
		return 'payload:' . sha1(json_encode($p));
	}

	private function InScope(array $rule, array $p): bool
	{
		if ($rule['branch_id'] !== null && intval($rule['branch_id']) !== intval($p['branch_id'] ?? 0))
		{
			return false;
		}
		if (!empty($rule['department']) && mb_strtolower((string) $rule['department']) !== mb_strtolower((string) ($p['department'] ?? '')))
		{
			return false;
		}
		return true;
	}

	public function Matches($conditions, array $p): bool
	{
		if (!is_array($conditions))
		{
			return true;
		}
		foreach ($conditions as $key => $val)
		{
			if (!array_key_exists($key, $p) || $p[$key] === null)
			{
				return false;
			}
			$v = $p[$key];
			if (is_array($val))
			{
				if (!in_array((string) $v, array_map('strval', $val), true))
				{
					return false;
				}
			}
			elseif (is_string($val) && preg_match('/^(>=|<=|!=|>|<)\s*(.+)$/', $val, $m))
			{
				$ok = match ($m[1]) {
					'>=' => floatval($v) >= floatval($m[2]),
					'<=' => floatval($v) <= floatval($m[2]),
					'>' => floatval($v) > floatval($m[2]),
					'<' => floatval($v) < floatval($m[2]),
					'!=' => (string) $v !== trim($m[2])
				};
				if (!$ok)
				{
					return false;
				}
			}
			elseif ((string) $v !== (string) $val)
			{
				return false;
			}
		}
		return true;
	}

	private function InHours(array $rule): bool
	{
		$from = $rule['active_from'] ?: null;
		$to = $rule['active_to'] ?: null;
		if (!$from || !$to)
		{
			return true;
		}
		$t = date('H:i');
		return $from <= $to ? ($t >= $from && $t < $to) : ($t >= $from || $t < $to);
	}

	private function Execute(array $rule, string $event, array $payload, string $key): array
	{
		$db = $this->Db();
		$now = date('Y-m-d H:i:s');
		$ins = $db->prepare('INSERT OR IGNORE INTO automation_executions (rule_id, event_trigger, idempotency_key, entity_type, entity_id, branch_id, product_id, status, attempts, payload_json, created_at, updated_at)
			VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?, ?)');
		[$etype, $eid] = !empty($payload['alert_id']) ? ['alert', intval($payload['alert_id'])] : (!empty($payload['waste_record_id']) ? ['waste', intval($payload['waste_record_id'])] : [null, null]);
		$ins->execute([$rule['id'], $event, $key, $etype, $eid, isset($payload['branch_id']) ? intval($payload['branch_id']) : null, !empty($payload['product_id']) ? intval($payload['product_id']) : null,
			'pending', json_encode($payload, JSON_UNESCAPED_UNICODE), $now, $now]);
		if ($ins->rowCount() === 0)
		{
			return ['rule_id' => intval($rule['id']), 'rule_name' => $rule['name'], 'status' => 'duplicate'];
		}
		$execId = intval($db->lastInsertId());
		$db->prepare('UPDATE automation_rules SET last_run_at = ? WHERE id = ?')->execute([$now, $rule['id']]);

		// Enfriamiento por regla + sucursal + producto
		$cool = intval($rule['cooldown_minutes']);
		if ($cool > 0 && !empty($payload['product_id']))
		{
			$prev = $this->Row("SELECT id FROM automation_executions WHERE rule_id = :r AND id <> :x AND IFNULL(branch_id, 0) = :b AND product_id = :p AND status = 'success' AND created_at >= :c LIMIT 1",
				[':r' => $rule['id'], ':x' => $execId, ':b' => intval($payload['branch_id'] ?? 0), ':p' => intval($payload['product_id']), ':c' => date('Y-m-d H:i:s', time() - 60 * $cool)]);
			if ($prev)
			{
				return $this->Finish($execId, $rule, 'suppressed', ['reason' => "Enfriamiento de $cool min (ejecución #{$prev['id']})"], null, 0);
			}
		}
		if (!$this->InHours($rule))
		{
			return $this->Finish($execId, $rule, 'deferred', ['reason' => 'Fuera del horario ' . $rule['active_from'] . '–' . $rule['active_to']], null, 0);
		}
		return $this->Attempt($execId, $rule, $payload, 0);
	}

	private function Attempt(int $execId, array $rule, array $payload, int $attempts): array
	{
		$attempts++;
		try
		{
			$result = $this->ExecuteAction($rule, $payload);
			return $this->Finish($execId, $rule, ($result['action'] ?? '') === 'skipped' ? 'skipped' : 'success', $result, null, $attempts);
		}
		catch (\Throwable $e)
		{
			$status = $attempts < max(1, intval($rule['max_retries'])) ? 'retrying' : 'failed';
			return $this->Finish($execId, $rule, $status, null, mb_substr($e->getMessage(), 0, 500), $attempts);
		}
	}

	private function Finish(int $execId, array $rule, string $status, ?array $result, ?string $error, int $attempts): array
	{
		$this->Db()->prepare('UPDATE automation_executions SET status = ?, attempts = ?, result_json = ?, error = ?, updated_at = ? WHERE id = ?')
			->execute([$status, $attempts, $result ? json_encode($result, JSON_UNESCAPED_UNICODE) : null, $error, date('Y-m-d H:i:s'), $execId]);
		return ['rule_id' => intval($rule['id']), 'rule_name' => $rule['name'], 'execution_id' => $execId, 'status' => $status, 'result' => $result, 'error' => $error];
	}

	/** Reintenta las fallidas con intentos disponibles y las diferidas que ya están en horario (programador) */
	public function RetryPending(int $limit = 50): array
	{
		$out = ['retried' => 0, 'success' => 0, 'failed' => 0];
		foreach ($this->Rows("SELECT e.id, e.attempts, e.payload_json, e.rule_id FROM automation_executions e JOIN automation_rules r ON r.id = e.rule_id
			WHERE e.status IN ('retrying', 'deferred') AND r.is_active = 1 ORDER BY e.id LIMIT " . intval($limit)) as $e)
		{
			$rule = $this->Row('SELECT * FROM automation_rules WHERE id = :i', [':i' => $e['rule_id']]);
			if (!$rule || !$this->InHours($rule))
			{
				continue;
			}
			$r = $this->Attempt(intval($e['id']), $rule, json_decode((string) $e['payload_json'], true) ?: [], intval($e['attempts']));
			$out['retried']++;
			$out[$r['status'] === 'success' || $r['status'] === 'skipped' ? 'success' : 'failed']++;
		}
		return $out;
	}

	/** Reintento manual de una ejecución fallida (desde el historial) */
	public function Retry(int $execId): array
	{
		$e = $this->Row('SELECT * FROM automation_executions WHERE id = :i', [':i' => $execId]);
		if (!$e || !in_array($e['status'], ['failed', 'retrying', 'deferred'], true))
		{
			throw new \Exception('Solo se reintentan ejecuciones fallidas, en reintento o diferidas');
		}
		$rule = $this->Row('SELECT * FROM automation_rules WHERE id = :i', [':i' => $e['rule_id']]);
		return $this->Attempt($execId, $rule, json_decode((string) $e['payload_json'], true) ?: [], intval($e['attempts']));
	}

	private function Fill(string $text, array $p): string
	{
		return strtr($text, ['{title}' => (string) ($p['title'] ?? ''), '{rule_code}' => (string) ($p['rule_code'] ?? ''), '{alert_id}' => (string) ($p['alert_id'] ?? '')]);
	}

	/** Responsable: el de la automatización → el de la alerta → el responsable de la sucursal → el gestor de alertas de la sucursal */
	private function Owner(array $rule, array $p): ?int
	{
		foreach ([$rule['owner_user_id'] ?? null, $p['owner_user_id'] ?? null] as $u)
		{
			if ($u && $this->Row('SELECT id FROM users WHERE id = :u', [':u' => intval($u)]))
			{
				return intval($u);
			}
		}
		if (!empty($p['branch_id']))
		{
			$lead = $this->Row("SELECT ub.user_id FROM user_branches ub JOIN user_permissions_resolved pr ON pr.user_id = ub.user_id AND pr.permission_name = 'WORKFORCE_SCHEDULE_MANAGE'
				LEFT JOIN employees e ON e.user_id = ub.user_id WHERE ub.branch_id = :b ORDER BY (e.active = 1) DESC, ub.user_id LIMIT 1", [':b' => intval($p['branch_id'])]);
			if ($lead)
			{
				return intval($lead['user_id']);
			}
			// Sin responsable en la sucursal: el gestor de alertas con alcance sobre ella
			$managers = AlertCenterService::GetInstance()->Managers(intval($p['branch_id']));
			if ($managers)
			{
				return min($managers);
			}
		}
		return null;
	}

	private function Note(array $p, string $note): void
	{
		if (!empty($p['alert_id']))
		{
			AlertCenterService::GetInstance()->AutomationNote(intval($p['alert_id']), $note);
		}
	}

	private function ExecuteAction(array $rule, array $p): array
	{
		$db = $this->Db();
		$cfg = json_decode((string) $rule['action_payload_json'], true) ?: [];
		$alerts = AlertCenterService::GetInstance();
		$alertId = intval($p['alert_id'] ?? 0);
		$label = '«' . $rule['name'] . '»';
		switch ($rule['action_type'])
		{
			case 'create_task':
				$branchId = intval($p['branch_id'] ?? 0);
				if ($branchId <= 0)
				{
					return ['action' => 'skipped', 'reason' => 'El evento no tiene sucursal'];
				}
				if ($alertId)
				{
					$existing = $db->query("SELECT id FROM workforce_store_tasks WHERE source_type = 'alert' AND source_id = $alertId AND status = 'pending'")->fetchColumn();
					if ($existing)
					{
						return ['action' => 'skipped', 'reason' => 'Ya existe la tarea TAR-' . $existing, 'task_id' => intval($existing)];
					}
				}
				$owner = $this->Owner($rule, $p);
				if (!$owner)
				{
					throw new \Exception('Sin responsable: asigne uno a la automatización o a la sucursal');
				}
				$db->prepare("INSERT INTO workforce_store_tasks (branch_id, task_type, assigned_role, assigned_user_id, title, description, due_time, status, source_type, source_id, created_timestamp)
					VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?, ?)")->execute([
					$branchId, $cfg['task_type'] ?? 'automation', $cfg['assigned_role'] ?? 'supervisor', $owner,
					mb_substr($this->Fill($cfg['title'] ?? 'Atender: {title}', $p), 0, 200),
					trim(($cfg['description'] ?? '') . ' Generada por la automatización ' . $label . '.'),
					date('Y-m-d H:i:s', time() + 3600 * max(1, intval($cfg['due_hours'] ?? 4))),
					$alertId ? 'alert' : (!empty($p['waste_record_id']) ? 'waste' : 'automation'),
					$alertId ?: (intval($p['waste_record_id'] ?? 0) ?: null), date('Y-m-d H:i:s')
				]);
				$taskId = intval($db->lastInsertId());
				if ($alertId && intval($p['owner_user_id'] ?? 0) !== $owner && in_array($p['status'] ?? '', AlertCenterService::WORKING, true))
				{
					$alerts->Assign($alertId, $owner, 0);
				}
				$this->Note($p, "Automatización $label: tarea TAR-$taskId asignada");
				return ['action' => 'task_created', 'task_id' => $taskId, 'owner_user_id' => $owner];

			case 'notify':
				if (!$alertId)
				{
					return ['action' => 'skipped', 'reason' => 'La notificación requiere una alerta'];
				}
				$branch = isset($p['branch_id']) ? intval($p['branch_id']) : null;
				$to = ($cfg['to'] ?? 'managers') === 'owner' ? array_filter([$this->Owner($rule, $p)]) : $alerts->Managers($branch);
				if ($rule['owner_user_id'])
				{
					$to[] = intval($rule['owner_user_id']);
				}
				if (!$to)
				{
					throw new \Exception('No hay destinatarios para la notificación');
				}
				$n = $alerts->Notify($alertId, $to, 'automation', $this->Fill($cfg['title'] ?? '{title}', $p), $cfg['body'] ?? null, 'auto' . $rule['id']);
				$this->Note($p, "Automatización $label: $n aviso(s) enviados");
				return ['action' => 'notified', 'recipients' => $n];

			case 'assign_alert':
				$owner = $this->Owner($rule, $p);
				if (!$alertId || !$owner)
				{
					return ['action' => 'skipped', 'reason' => $alertId ? 'Sin responsable configurado' : 'El evento no es una alerta'];
				}
				$alerts->Assign($alertId, $owner, 0);
				return ['action' => 'assigned', 'owner_user_id' => $owner];

			case 'set_priority':
				$prio = $cfg['priority'] ?? '';
				if (!$alertId || !in_array($prio, self::PRIORITIES, true))
				{
					return ['action' => 'skipped', 'reason' => 'Sin alerta o prioridad no válida'];
				}
				if (strcmp((string) ($p['priority'] ?? 'P4'), $prio) <= 0)
				{
					return ['action' => 'skipped', 'reason' => 'La alerta ya tiene prioridad ' . $p['priority']];
				}
				$alerts->SetPriority($alertId, $prio, ($cfg['reason'] ?? 'Automatización') . ' ' . $label, 0);
				return ['action' => 'priority_set', 'priority' => $prio];

			case 'escalate_alert':
				if (!$alertId)
				{
					return ['action' => 'skipped', 'reason' => 'El evento no es una alerta'];
				}
				$alerts->Escalate($alertId, 'Automatización ' . $label, 0);
				return ['action' => 'escalated'];

			case 'quarantine_lot':
				// Solo con lote y cantidad del evento (nunca una cantidad supuesta)
				$productId = intval($p['product_id'] ?? 0);
				$amount = floatval($p['amount'] ?? 0);
				if ($productId <= 0 || $amount <= 0 || empty($p['stock_id']) || empty($p['branch_id']))
				{
					return ['action' => 'skipped', 'reason' => 'El evento no trae producto, lote, cantidad y sucursal'];
				}
				$db->prepare("INSERT INTO quarantine_items (product_id, stock_id, branch_id, amount, reason, status, entered_timestamp) VALUES (?, ?, ?, ?, ?, 'in_quarantine', ?)")
					->execute([$productId, $p['stock_id'], intval($p['branch_id']), $amount, $cfg['reason'] ?? 'Bloqueo preventivo por automatización ' . $label, date('Y-m-d H:i:s')]);
				return ['action' => 'quarantined', 'quarantine_id' => intval($db->lastInsertId())];
		}
		throw new \Exception('Acción no soportada: ' . $rule['action_type']);
	}

	/* ---------- Configuración */

	public function Rules(): array
	{
		return $this->Rows("SELECT r.*, (SELECT COUNT(*) FROM automation_executions e WHERE e.rule_id = r.id AND e.status = 'success') AS runs_ok,
			(SELECT COUNT(*) FROM automation_executions e WHERE e.rule_id = r.id AND e.status IN ('failed', 'retrying')) AS runs_failed
			FROM automation_rules r ORDER BY r.is_active DESC, r.priority, r.name");
	}

	public function Executions(?int $ruleId = null, int $limit = 50): array
	{
		return $this->Rows('SELECT e.id, e.rule_id, r.name AS rule_name, e.event_trigger, e.entity_type, e.entity_id, e.branch_id, b.name AS branch_name, e.status, e.attempts, e.result_json, e.error, e.created_at, e.updated_at
			FROM automation_executions e JOIN automation_rules r ON r.id = e.rule_id LEFT JOIN branches b ON b.id = e.branch_id
			WHERE (:r IS NULL OR e.rule_id = :r) ORDER BY e.id DESC LIMIT ' . max(1, min(500, $limit)), [':r' => $ruleId]);
	}

	public function SaveRule(?int $id, array $d, int $userId): int
	{
		$before = $id ? $this->Row('SELECT * FROM automation_rules WHERE id = :i', [':i' => $id]) : null;
		if ($id && !$before)
		{
			throw new \Exception('Automatización no encontrada', 404);
		}
		$get = fn($k) => array_key_exists($k, $d) ? $d[$k] : ($before[$k] ?? null);
		$name = trim((string) $get('name'));
		if (mb_strlen($name) < 4)
		{
			throw new \Exception('Indique un nombre (mínimo 4 caracteres)');
		}
		$event = (string) $get('event_trigger');
		if (!isset(self::EVENTS[$event]))
		{
			throw new \Exception('Evento no válido');
		}
		$action = (string) $get('action_type');
		if (!isset(self::ACTIONS[$action]))
		{
			throw new \Exception('Acción no válida');
		}
		$json = function ($v, string $label) {
			if (is_array($v))
			{
				return json_encode($v, JSON_UNESCAPED_UNICODE);
			}
			$v = trim((string) $v) ?: '{}';
			if (!is_array(json_decode($v, true)))
			{
				throw new \Exception("$label: JSON no válido");
			}
			return $v;
		};
		$cond = $json($get('condition_json'), 'Condición');
		$payload = $json($get('action_payload_json'), 'Acción');
		$prio = (string) ($get('priority') ?: 'P3');
		if (!in_array($prio, self::PRIORITIES, true))
		{
			throw new \Exception('Prioridad no válida');
		}
		$hhmm = function ($v, string $label) {
			$v = trim((string) $v);
			if ($v !== '' && !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $v))
			{
				throw new \Exception("$label: use HH:MM");
			}
			return $v ?: null;
		};
		$from = $hhmm($get('active_from'), 'Horario desde');
		$to = $hhmm($get('active_to'), 'Horario hasta');
		if (($from === null) !== ($to === null))
		{
			throw new \Exception('Indique el horario completo (desde y hasta) o déjelo vacío');
		}
		$owner = $get('owner_user_id') ? intval($get('owner_user_id')) : null;
		if ($owner && !$this->Row('SELECT id FROM users WHERE id = :u', [':u' => $owner]))
		{
			throw new \Exception('Responsable no válido');
		}
		$branch = $get('branch_id') ? intval($get('branch_id')) : null;
		$active = in_array($get('is_active'), [1, '1', true, 'true', 'on'], true) ? 1 : 0;
		$vals = [
			'name' => $name, 'description' => trim((string) $get('description')) ?: null, 'event_trigger' => $event, 'condition_json' => $cond, 'action_type' => $action,
			'action_payload_json' => $payload, 'priority' => $prio, 'is_active' => $active, 'branch_id' => $branch, 'department' => trim((string) $get('department')) ?: null,
			'owner_user_id' => $owner, 'active_from' => $from, 'active_to' => $to, 'cooldown_minutes' => max(0, intval($get('cooldown_minutes'))),
			'max_retries' => max(1, min(10, intval($get('max_retries') ?: 3)))
		];
		$now = date('Y-m-d H:i:s');
		$db = $this->Db();
		if ($id)
		{
			$set = implode(', ', array_map(fn($k) => "$k = :$k", array_keys($vals)));
			$db->prepare("UPDATE automation_rules SET $set, updated_at = :now, updated_by_user_id = :u WHERE id = :id")
				->execute(array_combine(array_map(fn($k) => ":$k", array_keys($vals)), array_values($vals)) + [':now' => $now, ':u' => $userId, ':id' => $id]);
		}
		else
		{
			$cols = implode(', ', array_keys($vals));
			$db->prepare("INSERT INTO automation_rules ($cols, created_at, updated_at, updated_by_user_id) VALUES (:" . implode(', :', array_keys($vals)) . ', :now, :now, :u)')
				->execute(array_combine(array_map(fn($k) => ":$k", array_keys($vals)), array_values($vals)) + [':now' => $now, ':u' => $userId]);
			$id = intval($db->lastInsertId());
		}
		$diff = [];
		foreach ($vals as $k => $v)
		{
			if (!$before || (string) $before[$k] !== (string) $v)
			{
				$diff[$k] = [$before[$k] ?? null, $v];
			}
		}
		if ($diff)
		{
			$db->prepare('INSERT INTO automation_rule_changes (rule_id, user_id, changes, created_at) VALUES (?, ?, ?, ?)')->execute([$id, $userId, json_encode($diff, JSON_UNESCAPED_UNICODE), $now]);
		}
		return $id;
	}

	public function RuleChanges(int $ruleId, int $limit = 20): array
	{
		return $this->Rows('SELECT c.*, u.username AS user_name FROM automation_rule_changes c LEFT JOIN users u ON u.id = c.user_id WHERE c.rule_id = :r ORDER BY c.id DESC LIMIT ' . intval($limit), [':r' => $ruleId]);
	}
}
