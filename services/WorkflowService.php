<?php

namespace Grocy\Services;

use Grocy\Helpers\DairyAccess;

/**
 * Motor de flujos operativos (Fase 1.3). Cada proceso declara sus pasos en daily_workflow_definitions; no hay un
 * flujo único: un paso puede pedir aprobación (con permiso propio), evidencia o ser opcional.
 * Instancia = proceso aplicado a un documento origen (source_type/source_id). Iniciar dos veces el mismo documento
 * devuelve la misma instancia; cada acción acepta request_key para que un reintento no duplique el historial.
 * Segregación: quien aprueba no puede ser quien creó el proceso ni quien completó el paso anterior.
 */
class WorkflowService extends BaseService
{
	const STATUS = ['open' => 'Abierto', 'in_progress' => 'En curso', 'pending_approval' => 'Pendiente de aprobación', 'completed' => 'Completado',
		'rejected' => 'Rechazado', 'cancelled' => 'Anulado', 'closed' => 'Cerrado'];
	const ACTIVE = ['open', 'in_progress', 'pending_approval'];
	const PRIORITIES = ['low', 'normal', 'high', 'critical'];
	const EVIDENCE_KINDS = ['photo', 'document', 'note', 'link', 'reference'];

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

	private function Now(): string
	{
		return date('Y-m-d H:i:s');
	}

	private function UserId(): ?int
	{
		return defined('GROCY_USER_ID') ? intval(GROCY_USER_ID) : null;
	}

	public function Definitions(bool $activeOnly = true): array
	{
		$rows = $this->Rows('SELECT * FROM daily_workflow_definitions' . ($activeOnly ? ' WHERE active = 1' : '') . ' ORDER BY module, name');
		foreach ($rows as &$r)
		{
			$r['steps'] = json_decode($r['steps_json'], true) ?: [];
			unset($r['steps_json']);
		}
		return $rows;
	}

	public function Definition(string $code): array
	{
		$d = $this->Row('SELECT * FROM daily_workflow_definitions WHERE code = :c', [':c' => $code]);
		if (!$d)
		{
			throw new \Exception('Flujo no definido: ' . $code);
		}
		$d['steps'] = json_decode($d['steps_json'], true) ?: [];
		unset($d['steps_json']);
		return $d;
	}

	/** Crea o actualiza una definición. Los pasos se validan para que el motor siempre pueda recorrerlos. */
	public function SaveDefinition(array $d): array
	{
		$code = trim((string) ($d['code'] ?? ''));
		$steps = $d['steps'] ?? [];
		if (!preg_match('/^[a-z0-9_]{3,60}$/', $code) || trim((string) ($d['name'] ?? '')) === '' || !is_array($steps) || !$steps)
		{
			throw new \Exception('Código (a-z, 0-9, _), nombre y al menos un paso son obligatorios');
		}
		$seen = [];
		foreach ($steps as $s)
		{
			$sc = (string) ($s['code'] ?? '');
			if (!preg_match('/^[a-z0-9_]{1,60}$/', $sc) || isset($seen[$sc]) || trim((string) ($s['name'] ?? '')) === '')
			{
				throw new \Exception('Cada paso necesita código único y nombre');
			}
			$seen[$sc] = true;
		}
		$prio = in_array($d['default_priority'] ?? 'normal', self::PRIORITIES, true) ? ($d['default_priority'] ?? 'normal') : 'normal';
		$this->Db()->prepare('INSERT INTO daily_workflow_definitions (code, name, module, operation_type, steps_json, default_due_hours, default_priority, active)
			VALUES (:c, :n, :m, :o, :s, :h, :p, :a)
			ON CONFLICT(code) DO UPDATE SET name = excluded.name, module = excluded.module, operation_type = excluded.operation_type, steps_json = excluded.steps_json,
				default_due_hours = excluded.default_due_hours, default_priority = excluded.default_priority, active = excluded.active')->execute([
			':c' => $code, ':n' => trim($d['name']), ':m' => trim((string) ($d['module'] ?? 'general')), ':o' => trim((string) ($d['operation_type'] ?? 'task')),
			':s' => json_encode(array_values($steps), JSON_UNESCAPED_UNICODE), ':h' => isset($d['default_due_hours']) && $d['default_due_hours'] !== '' ? max(1, intval($d['default_due_hours'])) : null,
			':p' => $prio, ':a' => isset($d['active']) ? (intval($d['active']) ? 1 : 0) : 1,
		]);
		return $this->Definition($code);
	}

	/**
	 * Inicia el proceso para un documento. Idempotente por (definición, documento): si ya existe, la devuelve tal cual.
	 * $opts: title, branch_id, owner_user_id, priority, due_at
	 */
	public function Start(string $definitionCode, string $sourceType, string $sourceId, array $opts = []): array
	{
		$def = $this->Definition($definitionCode);
		if (!intval($def['active']))
		{
			throw new \Exception('El flujo ' . $def['name'] . ' está inactivo');
		}
		$branchId = isset($opts['branch_id']) && $opts['branch_id'] !== '' ? intval($opts['branch_id']) : null;
		if ($branchId !== null && !DairyAccess::CanSeeBranch($branchId))
		{
			throw new \Exception('No tiene acceso a esa sucursal');
		}
		if (trim($sourceType) === '' || trim($sourceId) === '')
		{
			throw new \Exception('El documento origen (tipo e identificador) es obligatorio');
		}
		$id = DatabaseService::GetInstance()->Locked(function () use ($def, $sourceType, $sourceId, $opts, $branchId) {
			$existing = $this->Row('SELECT id FROM daily_workflow_instances WHERE definition_code = :d AND source_type = :t AND source_id = :s',
				[':d' => $def['code'], ':t' => $sourceType, ':s' => $sourceId]);
			if ($existing)
			{
				return intval($existing['id']);
			}
			$now = $this->Now();
			$prio = in_array($opts['priority'] ?? '', self::PRIORITIES, true) ? $opts['priority'] : $def['default_priority'];
			$due = !empty($opts['due_at']) ? date('Y-m-d H:i:s', strtotime($opts['due_at'])) : ($def['default_due_hours'] ? date('Y-m-d H:i:s', time() + 3600 * intval($def['default_due_hours'])) : null);
			$this->Db()->prepare('INSERT INTO daily_workflow_instances (uuid, definition_code, operation_type, source_type, source_id, branch_id, title, status, current_step,
				priority, owner_user_id, due_at, created_by_user_id, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, \'open\', ?, ?, ?, ?, ?, ?, ?)')->execute([
				bin2hex(random_bytes(16)), $def['code'], $def['operation_type'], $sourceType, $sourceId, $branchId,
				trim((string) ($opts['title'] ?? '')) ?: $def['name'] . ' · ' . $sourceType . ' ' . $sourceId, $def['steps'][0]['code'],
				$prio, isset($opts['owner_user_id']) && $opts['owner_user_id'] !== '' ? intval($opts['owner_user_id']) : null, $due, $this->UserId(), $now, $now,
			]);
			$id = intval($this->Db()->lastInsertId());
			$st = $this->Db()->prepare('INSERT INTO daily_workflow_step_states (instance_id, step_code, position) VALUES (?, ?, ?)');
			foreach ($def['steps'] as $i => $s)
			{
				$st->execute([$id, $s['code'], $i]);
			}
			$this->Log($id, 'created', null, null, 'open', ['source_type' => $sourceType, 'source_id' => $sourceId]);
			return $id;
		});
		return $this->Get($id);
	}

	public function Get(int $id): array
	{
		$i = $this->Row('SELECT * FROM daily_workflow_instances WHERE id = :id', [':id' => $id]);
		if (!$i)
		{
			throw new \Exception('Proceso no encontrado');
		}
		if ($i['branch_id'] !== null && !DairyAccess::CanSeeBranch(intval($i['branch_id'])))
		{
			throw new \Exception('No tiene acceso a la sucursal de este proceso');
		}
		$def = $this->Definition($i['definition_code']);
		$states = [];
		foreach ($this->Rows('SELECT * FROM daily_workflow_step_states WHERE instance_id = :id ORDER BY position', [':id' => $id]) as $s)
		{
			$states[$s['step_code']] = $s;
		}
		$i['status_label'] = self::STATUS[$i['status']] ?? $i['status'];
		$i['definition_name'] = $def['name'];
		$i['steps'] = array_map(fn($s) => $s + ['state' => $states[$s['code']] ?? null], $def['steps']);
		$done = count(array_filter($states, fn($s) => in_array($s['status'], ['done', 'skipped', 'approved'], true)));
		$i['progress_pct'] = $states ? round($done / count($states) * 100) : 0;
		$i['overdue'] = $i['due_at'] && in_array($i['status'], self::ACTIVE, true) && $i['due_at'] < $this->Now();
		$i['evidence'] = $this->Rows('SELECT * FROM daily_workflow_evidence WHERE instance_id = :id ORDER BY id', [':id' => $id]);
		$i['history'] = $this->Rows('SELECT e.*, u.display_name AS user_name FROM daily_workflow_events e LEFT JOIN users_dto u ON u.id = e.user_id WHERE e.instance_id = :id ORDER BY e.id', [':id' => $id]);
		return $i;
	}

	public function FindBySource(string $sourceType, string $sourceId): array
	{
		return array_map(fn($r) => $this->Get(intval($r['id'])), $this->Rows('SELECT id FROM daily_workflow_instances WHERE source_type = :t AND source_id = :s ORDER BY id', [':t' => $sourceType, ':s' => $sourceId]));
	}

	/** Bandeja: filtros status (o 'active'), definition_code, branch_id, owner_user_id, overdue=1 */
	public function List(array $f = [], int $limit = 200): array
	{
		$w = ['1 = 1'];
		$p = [];
		if (($f['status'] ?? '') === 'active')
		{
			$w[] = "i.status IN ('" . implode("', '", self::ACTIVE) . "')";
		}
		elseif (!empty($f['status']) && isset(self::STATUS[$f['status']]))
		{
			$w[] = 'i.status = :st';
			$p[':st'] = $f['status'];
		}
		foreach (['definition_code' => 'i.definition_code', 'operation_type' => 'i.operation_type'] as $k => $col)
		{
			if (!empty($f[$k]))
			{
				$w[] = "$col = :$k";
				$p[":$k"] = $f[$k];
			}
		}
		foreach (['branch_id' => 'i.branch_id', 'owner_user_id' => 'i.owner_user_id'] as $k => $col)
		{
			if (isset($f[$k]) && $f[$k] !== '')
			{
				$w[] = "$col = :$k";
				$p[":$k"] = intval($f[$k]);
			}
		}
		if (!empty($f['overdue']))
		{
			$w[] = "i.due_at < :now AND i.status IN ('" . implode("', '", self::ACTIVE) . "')";
			$p[':now'] = $this->Now();
		}
		$allowed = DairyAccess::AllowedBranches();
		if ($allowed !== null)
		{
			$w[] = '(i.branch_id IS NULL OR i.branch_id IN (' . implode(',', array_map('intval', $allowed ?: [0])) . '))';
		}
		$rows = $this->Rows('SELECT i.*, d.name AS definition_name, u.display_name AS owner_name FROM daily_workflow_instances i
			JOIN daily_workflow_definitions d ON d.code = i.definition_code LEFT JOIN users_dto u ON u.id = i.owner_user_id
			WHERE ' . implode(' AND ', $w) . " ORDER BY CASE i.priority WHEN 'critical' THEN 0 WHEN 'high' THEN 1 WHEN 'normal' THEN 2 ELSE 3 END, IFNULL(i.due_at, '9999'), i.id DESC LIMIT " . max(1, min(1000, $limit)), $p);
		$now = $this->Now();
		foreach ($rows as &$r)
		{
			$r['status_label'] = self::STATUS[$r['status']] ?? $r['status'];
			$r['overdue'] = $r['due_at'] && in_array($r['status'], self::ACTIVE, true) && $r['due_at'] < $now;
		}
		return $rows;
	}

	/** Completa el paso actual (o $stepCode si es el actual). Si el paso pide aprobación, queda pendiente de aprobar. */
	public function CompleteStep(int $id, ?string $stepCode = null, ?string $notes = null, ?string $requestKey = null): array
	{
		return $this->Act($id, $requestKey, function (array $i, array $def) use ($stepCode, $notes) {
			$step = $this->CurrentStep($i, $def, $stepCode);
			if (!empty($step['requires_approval']))
			{
				if ($i['status'] === 'pending_approval')
				{
					throw new \Exception('El paso «' . $step['name'] . '» ya espera aprobación');
				}
				$this->RequireEvidence($i, $step);
				$this->SetStatus($i, 'pending_approval');
				$this->Log(intval($i['id']), 'approval_requested', $step['code'], $i['status'], 'pending_approval', ['notes' => $notes]);
				return;
			}
			$this->RequireEvidence($i, $step);
			$this->MarkStep($i, $step['code'], 'done', $notes);
			$this->Log(intval($i['id']), 'step_completed', $step['code'], $i['status'], null, ['notes' => $notes]);
			$this->AdvanceFrom($i, $def, $step['code']);
		});
	}

	public function SkipStep(int $id, string $stepCode, string $reason, ?string $requestKey = null): array
	{
		return $this->Act($id, $requestKey, function (array $i, array $def) use ($stepCode, $reason) {
			$step = $this->CurrentStep($i, $def, $stepCode);
			if (empty($step['optional']))
			{
				throw new \Exception('El paso «' . $step['name'] . '» es obligatorio');
			}
			if (trim($reason) === '')
			{
				throw new \Exception('Indique el motivo para omitir el paso');
			}
			$this->MarkStep($i, $step['code'], 'skipped', $reason);
			$this->Log(intval($i['id']), 'step_skipped', $step['code'], $i['status'], null, ['reason' => $reason]);
			$this->AdvanceFrom($i, $def, $step['code']);
		});
	}

	public function Approve(int $id, ?string $notes = null, ?string $requestKey = null): array
	{
		return $this->Decide($id, true, $notes, $requestKey);
	}

	public function Reject(int $id, string $notes, ?string $requestKey = null): array
	{
		if (trim($notes) === '')
		{
			throw new \Exception('El rechazo requiere observaciones');
		}
		return $this->Decide($id, false, $notes, $requestKey);
	}

	private function Decide(int $id, bool $approve, ?string $notes, ?string $requestKey): array
	{
		return $this->Act($id, $requestKey, function (array $i, array $def) use ($approve, $notes) {
			if ($i['status'] !== 'pending_approval')
			{
				throw new \Exception('El proceso no está pendiente de aprobación');
			}
			$step = $this->CurrentStep($i, $def, null);
			$perm = $step['approver_permission'] ?? 'ADMIN';
			if (!DairyAccess::HasAny([$perm, 'ADMIN']))
			{
				throw new \Exception('No tiene permiso para aprobar este paso (' . $perm . ')');
			}
			$me = $this->UserId();
			$prevBy = $this->Row("SELECT completed_by_user_id FROM daily_workflow_step_states WHERE instance_id = :id AND completed_by_user_id IS NOT NULL ORDER BY position DESC LIMIT 1", [':id' => $i['id']]);
			if ($me !== null && ($me === intval($i['created_by_user_id']) || ($prevBy && $me === intval($prevBy['completed_by_user_id']))))
			{
				throw new \Exception('Segregación de funciones: quien aprueba no puede ser quien inició el proceso ni quien ejecutó el paso anterior');
			}
			if ($approve)
			{
				$this->MarkStep($i, $step['code'], 'approved', $notes);
				$this->Log(intval($i['id']), 'approved', $step['code'], 'pending_approval', 'in_progress', ['notes' => $notes]);
				$i['status'] = 'in_progress';
				$this->SetStatus($i, 'in_progress');
				$this->AdvanceFrom($i, $def, $step['code']);
				return;
			}
			$this->MarkStep($i, $step['code'], 'rejected', $notes);
			$this->Db()->prepare("UPDATE daily_workflow_instances SET status = 'rejected', result = 'rejected', result_notes = :n, closed_at = :t, closed_by_user_id = :u, updated_at = :t WHERE id = :id")
				->execute([':n' => $notes, ':t' => $this->Now(), ':u' => $me, ':id' => $i['id']]);
			$this->Log(intval($i['id']), 'rejected', $step['code'], 'pending_approval', 'rejected', ['notes' => $notes]);
		});
	}

	public function Assign(int $id, ?int $ownerUserId, ?string $requestKey = null): array
	{
		return $this->Act($id, $requestKey, function (array $i) use ($ownerUserId) {
			if ($ownerUserId !== null && !$this->Row('SELECT id FROM users WHERE id = :id', [':id' => $ownerUserId]))
			{
				throw new \Exception('Usuario no encontrado');
			}
			$this->Db()->prepare('UPDATE daily_workflow_instances SET owner_user_id = :o, updated_at = :t WHERE id = :id')->execute([':o' => $ownerUserId, ':t' => $this->Now(), ':id' => $i['id']]);
			$this->Log(intval($i['id']), 'assigned', null, null, null, ['from' => $i['owner_user_id'], 'to' => $ownerUserId]);
		});
	}

	public function AddEvidence(int $id, string $kind, string $reference, ?string $description = null, ?string $stepCode = null, ?string $requestKey = null): array
	{
		return $this->Act($id, $requestKey, function (array $i) use ($kind, $reference, $description, $stepCode) {
			if (!in_array($kind, self::EVIDENCE_KINDS, true) || trim($reference) === '')
			{
				throw new \Exception('Evidencia inválida: tipo (' . implode(', ', self::EVIDENCE_KINDS) . ') y referencia obligatorios');
			}
			$step = $stepCode ?: $i['current_step'];
			$this->Db()->prepare('INSERT INTO daily_workflow_evidence (instance_id, step_code, kind, reference, description, user_id, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)')
				->execute([$i['id'], $step, $kind, trim($reference), $description, $this->UserId(), $this->Now()]);
			$this->Log(intval($i['id']), 'evidence_added', $step, null, null, ['kind' => $kind, 'reference' => trim($reference)]);
		}, false);
	}

	public function Comment(int $id, string $text, ?string $requestKey = null): array
	{
		if (trim($text) === '')
		{
			throw new \Exception('Comentario vacío');
		}
		return $this->Act($id, $requestKey, fn(array $i) => $this->Log(intval($i['id']), 'commented', null, null, null, ['text' => trim($text)]), false);
	}

	/** Cierre explícito de un proceso completado (registra resultado). Un proceso activo solo puede anularse. */
	public function Close(int $id, ?string $result = null, ?string $notes = null, ?string $requestKey = null): array
	{
		return $this->Act($id, $requestKey, function (array $i) use ($result, $notes) {
			if ($i['status'] !== 'completed')
			{
				throw new \Exception('Solo se cierra un proceso completado (estado actual: ' . (self::STATUS[$i['status']] ?? $i['status']) . ')');
			}
			$this->Finish($i, 'closed', $result ?: 'ok', $notes);
		}, false);
	}

	public function Cancel(int $id, string $reason, ?string $requestKey = null): array
	{
		if (trim($reason) === '')
		{
			throw new \Exception('Indique el motivo de la anulación');
		}
		return $this->Act($id, $requestKey, fn(array $i) => $this->Finish($i, 'cancelled', 'cancelled', $reason));
	}

	/** Indicadores del motor: activos, vencidos, pendientes de aprobación y tiempo medio de cierre por flujo */
	public function Stats(?int $branchId = null): array
	{
		$p = [];
		$b = '';
		if ($branchId)
		{
			$b = ' AND branch_id = :b';
			$p[':b'] = $branchId;
		}
		$p[':now'] = $this->Now();
		return $this->Rows("SELECT definition_code,
				SUM(status IN ('open', 'in_progress', 'pending_approval')) AS active,
				SUM(status = 'pending_approval') AS pending_approval,
				SUM(status IN ('open', 'in_progress', 'pending_approval') AND due_at < :now) AS overdue,
				SUM(status IN ('completed', 'closed')) AS completed,
				SUM(status = 'rejected') AS rejected,
				ROUND(AVG(CASE WHEN closed_at IS NOT NULL AND status IN ('completed', 'closed') THEN (julianday(closed_at) - julianday(created_at)) * 24 END), 2) AS avg_hours_to_close
			FROM daily_workflow_instances WHERE 1 = 1 $b GROUP BY definition_code ORDER BY definition_code", $p);
	}

	// ── internos ────────────────────────────────────────────────────────────────

	/** Ejecuta $fn con bloqueo de escritura; $requireActive exige proceso en curso. Un request_key repetido no vuelve a ejecutar. */
	private function Act(int $id, ?string $requestKey, callable $fn, bool $requireActive = true): array
	{
		DatabaseService::GetInstance()->Locked(function () use ($id, $requestKey, $fn, $requireActive) {
			$i = $this->Row('SELECT * FROM daily_workflow_instances WHERE id = :id', [':id' => $id]);
			if (!$i)
			{
				throw new \Exception('Proceso no encontrado');
			}
			if ($i['branch_id'] !== null && !DairyAccess::CanSeeBranch(intval($i['branch_id'])))
			{
				throw new \Exception('No tiene acceso a la sucursal de este proceso');
			}
			if ($requestKey !== null && $requestKey !== '' && $this->Row('SELECT id FROM daily_workflow_events WHERE instance_id = :id AND request_key = :k', [':id' => $id, ':k' => $requestKey]))
			{
				return; // reintento: la acción ya quedó registrada
			}
			if ($requireActive && !in_array($i['status'], self::ACTIVE, true))
			{
				throw new \Exception('El proceso ya terminó (' . (self::STATUS[$i['status']] ?? $i['status']) . ')');
			}
			$this->PendingRequestKey = $requestKey !== '' ? $requestKey : null;
			try
			{
				$fn($i, $this->Definition($i['definition_code']));
			}
			finally
			{
				$this->PendingRequestKey = null;
			}
		});
		return $this->Get($id);
	}

	private ?string $PendingRequestKey = null;

	private function CurrentStep(array $i, array $def, ?string $stepCode): array
	{
		foreach ($def['steps'] as $s)
		{
			if ($s['code'] === $i['current_step'])
			{
				if ($stepCode !== null && $stepCode !== '' && $stepCode !== $s['code'])
				{
					throw new \Exception('El paso actual es «' . $s['name'] . '»: los pasos se ejecutan en orden');
				}
				return $s;
			}
		}
		throw new \Exception('El proceso no tiene paso actual');
	}

	private function RequireEvidence(array $i, array $step): void
	{
		if (!empty($step['requires_evidence']) && !$this->Row('SELECT id FROM daily_workflow_evidence WHERE instance_id = :id AND step_code = :s LIMIT 1', [':id' => $i['id'], ':s' => $step['code']]))
		{
			throw new \Exception('El paso «' . $step['name'] . '» requiere evidencia');
		}
	}

	private function MarkStep(array $i, string $stepCode, string $status, ?string $notes): void
	{
		$this->Db()->prepare('UPDATE daily_workflow_step_states SET status = :s, completed_by_user_id = :u, completed_at = :t, notes = :n WHERE instance_id = :id AND step_code = :c')
			->execute([':s' => $status, ':u' => $this->UserId(), ':t' => $this->Now(), ':n' => $notes, ':id' => $i['id'], ':c' => $stepCode]);
	}

	private function SetStatus(array $i, string $status): void
	{
		$this->Db()->prepare('UPDATE daily_workflow_instances SET status = :s, updated_at = :t WHERE id = :id')->execute([':s' => $status, ':t' => $this->Now(), ':id' => $i['id']]);
	}

	private function AdvanceFrom(array $i, array $def, string $stepCode): void
	{
		$codes = array_column($def['steps'], 'code');
		$next = $codes[array_search($stepCode, $codes, true) + 1] ?? null;
		if ($next === null)
		{
			$this->Db()->prepare("UPDATE daily_workflow_instances SET status = 'completed', current_step = NULL, result = IFNULL(result, 'ok'), closed_at = :t, closed_by_user_id = :u, updated_at = :t WHERE id = :id")
				->execute([':t' => $this->Now(), ':u' => $this->UserId(), ':id' => $i['id']]);
			$this->Log(intval($i['id']), 'completed', $stepCode, $i['status'], 'completed', null, false);
			return;
		}
		$this->Db()->prepare("UPDATE daily_workflow_instances SET status = 'in_progress', current_step = :c, updated_at = :t WHERE id = :id")->execute([':c' => $next, ':t' => $this->Now(), ':id' => $i['id']]);
	}

	private function Finish(array $i, string $status, string $result, ?string $notes): void
	{
		$this->Db()->prepare('UPDATE daily_workflow_instances SET status = :s, result = :r, result_notes = :n, closed_at = IFNULL(closed_at, :t), closed_by_user_id = :u, updated_at = :t WHERE id = :id')
			->execute([':s' => $status, ':r' => $result, ':n' => $notes, ':t' => $this->Now(), ':u' => $this->UserId(), ':id' => $i['id']]);
		$this->Log(intval($i['id']), $status, $i['current_step'], $i['status'], $status, ['result' => $result, 'notes' => $notes]);
	}

	/** El request_key se guarda solo en el primer evento de la acción (los derivados, como 'completed', no lo llevan) */
	private function Log(int $id, string $type, ?string $step, ?string $from, ?string $to, ?array $payload = null, bool $useKey = true): void
	{
		$key = $useKey ? $this->PendingRequestKey : null;
		$this->Db()->prepare('INSERT INTO daily_workflow_events (instance_id, event_type, step_code, from_status, to_status, user_id, payload, request_key, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)')
			->execute([$id, $type, $step, $from, $to, $this->UserId(), $payload ? json_encode($payload, JSON_UNESCAPED_UNICODE) : null, $key, $this->Now()]);
		if ($useKey)
		{
			$this->PendingRequestKey = null;
		}
	}
}
