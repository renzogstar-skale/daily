<?php

namespace Grocy\Services;

/**
 * Turnos abiertos: el gerente publica una necesidad de cobertura (labor_coverage_requests) y los empleados de la
 * sucursal la toman desde el móvil. Cada toma se valida con las mismas reglas que un turno normal (CheckConflicts:
 * solapes, descanso, certificaciones). Con aprobación automática el primero válido queda asignado; si no, el gerente aprueba.
 * Al aprobar se crea y publica el turno real (employee_shifts + historial) y, al llenarse los cupos, la solicitud se cierra.
 */
class OpenShiftService extends BaseService
{
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

	private function Sched(): WorkforceScheduleService
	{
		return WorkforceScheduleService::GetInstance();
	}

	private function Request(int $id): array
	{
		$r = $this->Rows('SELECT * FROM labor_coverage_requests WHERE id = ?', [$id])[0] ?? null;
		if (!$r)
		{
			throw new \Exception('Turno abierto no encontrado', 404);
		}
		return $r;
	}

	private function Approved(int $requestId): int
	{
		return intval($this->Rows("SELECT COUNT(*) AS n FROM labor_coverage_claims WHERE request_id = ? AND status = 'approved'", [$requestId])[0]['n']);
	}

	/** Publica un turno abierto y avisa a los empleados activos de la sucursal */
	public function Create(array $d, int $userId): array
	{
		$id = LaborForecastService::GetInstance()->CreateCoverageRequest($d, $userId);
		$this->Db()->prepare('UPDATE labor_coverage_requests SET auto_approve = ?, department = ?, break_minutes = ? WHERE id = ?')
			->execute([!empty($d['auto_approve']) ? 1 : 0, trim(strval($d['department'] ?? '')) ?: null, max(0, min(240, intval($d['break_minutes'] ?? 0))), $id]);
		$r = $this->Request($id);
		$users = array_map('intval', array_column($this->Rows('SELECT user_id FROM employees WHERE branch_id = ? AND active = 1 AND user_id IS NOT NULL', [$r['branch_id']]), 'user_id'));
		$push = PushService::GetInstance()->Notify($users, 'open_shift', 'Turno disponible', self::Label($r) . ($r['role'] ? ' · ' . $r['role'] : ''), ['request_id' => $id, 'route' => 'open-shifts']);
		return ['id' => $id, 'notified_users' => count($users), 'push' => $push];
	}

	private static function Label(array $r): string
	{
		return date('d/m', strtotime($r['request_date'])) . ' ' . substr($r['start_time'], 0, 5) . '–' . substr($r['end_time'], 0, 5);
	}

	/** Turnos abiertos visibles para el empleado vinculado al usuario, con la validación previa de cada uno */
	public function ForEmployee(int $userId): array
	{
		$emp = $this->Sched()->GetEmployeeByUser($userId);
		if (!$emp)
		{
			return ['linked' => false, 'open' => [], 'mine' => []];
		}
		$open = $this->Rows("SELECT r.*, (SELECT COUNT(*) FROM labor_coverage_claims c WHERE c.request_id = r.id AND c.status = 'approved') AS filled,
				(SELECT status FROM labor_coverage_claims c WHERE c.request_id = r.id AND c.employee_id = :e) AS my_status
			FROM labor_coverage_requests r WHERE r.branch_id = :b AND r.status = 'open' AND r.request_date >= date('now', 'localtime') ORDER BY r.request_date, r.start_time", [':b' => $emp->branch_id, ':e' => $emp->id]);
		foreach ($open as &$r)
		{
			$r['remaining'] = max(0, intval($r['staff_needed']) - intval($r['filled']));
			$issues = $this->Sched()->CheckConflicts(intval($emp->id), $r['request_date'], $r['start_time'], $r['end_time'], intval($r['break_minutes']), 0, $r['role'] ?: null);
			$r['blocking'] = array_values(array_map(fn($i) => $i['message'], array_filter($issues, fn($i) => $i['level'] === 'error')));
			$r['warnings'] = array_values(array_map(fn($i) => $i['message'], array_filter($issues, fn($i) => $i['level'] !== 'error')));
			$r['auto_approve'] = intval($r['auto_approve']) === 1;
		}
		unset($r);
		$mine = $this->Rows("SELECT c.*, r.request_date, r.start_time, r.end_time, r.role, r.reason FROM labor_coverage_claims c JOIN labor_coverage_requests r ON r.id = c.request_id
			WHERE c.employee_id = ? AND r.request_date >= date('now', 'localtime', '-7 day') ORDER BY c.id DESC LIMIT 30", [$emp->id]);
		return ['linked' => true, 'employee_id' => intval($emp->id), 'open' => $open, 'mine' => $mine];
	}

	public function Claim(int $requestId, int $userId, ?string $notes = null): array
	{
		$emp = $this->Sched()->GetEmployeeByUser($userId);
		if (!$emp)
		{
			throw new \Exception('Su usuario no está vinculado a una ficha de empleado', 403);
		}
		$r = $this->Request($requestId);
		if (intval($r['branch_id']) !== intval($emp->branch_id))
		{
			throw new \Exception('Este turno es de otra sucursal', 403);
		}
		if ($r['status'] !== 'open' || $r['request_date'] < date('Y-m-d'))
		{
			throw new \Exception('El turno ya no está disponible');
		}
		if ($this->Approved($requestId) >= intval($r['staff_needed']))
		{
			throw new \Exception('El turno ya fue cubierto');
		}
		$issues = $this->Sched()->CheckConflicts(intval($emp->id), $r['request_date'], $r['start_time'], $r['end_time'], intval($r['break_minutes']), 0, $r['role'] ?: null);
		$errors = array_values(array_filter($issues, fn($i) => $i['level'] === 'error'));
		if ($errors)
		{
			throw new \Exception('No puede tomar este turno: ' . implode('; ', array_column($errors, 'message')));
		}
		$prev = $this->Rows('SELECT * FROM labor_coverage_claims WHERE request_id = ? AND employee_id = ?', [$requestId, $emp->id])[0] ?? null;
		if ($prev && in_array($prev['status'], ['pending', 'approved'], true))
		{
			throw new \Exception($prev['status'] === 'approved' ? 'Ya tiene este turno asignado' : 'Ya lo solicitó; espere la aprobación');
		}
		$now = date('Y-m-d H:i:s');
		if ($prev)
		{
			$this->Db()->prepare("UPDATE labor_coverage_claims SET status = 'pending', notes = ?, claimed_by_user_id = ?, created_at = ?, decided_by_user_id = NULL, decided_at = NULL, decision_notes = NULL, shift_id = NULL WHERE id = ?")
				->execute([$notes, $userId, $now, $prev['id']]);
			$claimId = intval($prev['id']);
		}
		else
		{
			$this->Db()->prepare('INSERT INTO labor_coverage_claims (request_id, employee_id, status, notes, claimed_by_user_id, created_at) VALUES (?, ?, ?, ?, ?, ?)')
				->execute([$requestId, $emp->id, 'pending', $notes, $userId, $now]);
			$claimId = intval($this->Db()->lastInsertId());
		}
		if (intval($r['auto_approve']) === 1)
		{
			// El primero que cumple las reglas se queda con el turno; queda registrado quién lo publicó como aprobador
			return $this->Decide($claimId, true, intval($r['created_by_user_id']), 'Aprobación automática del turno abierto', true) + ['auto' => true];
		}
		$managers = AlertCenterService::GetInstance()->Managers(intval($r['branch_id']), 'WORKFORCE_SCHEDULE_MANAGE');
		PushService::GetInstance()->Notify($managers, 'open_shift_claim', 'Solicitud de turno abierto', $emp->name . ' · ' . self::Label($r), ['claim_id' => $claimId, 'route' => 'open-shifts']);
		return ['claim_id' => $claimId, 'status' => 'pending', 'auto' => false, 'warnings' => array_column(array_filter($issues, fn($i) => $i['level'] !== 'error'), 'message')];
	}

	public function Withdraw(int $claimId, int $userId): void
	{
		$emp = $this->Sched()->GetEmployeeByUser($userId);
		$st = $this->Db()->prepare("UPDATE labor_coverage_claims SET status = 'withdrawn', decided_at = ? WHERE id = ? AND employee_id = ? AND status = 'pending'");
		$st->execute([date('Y-m-d H:i:s'), $claimId, $emp ? $emp->id : -1]);
		if ($st->rowCount() === 0)
		{
			throw new \Exception('La solicitud ya no está pendiente');
		}
	}

	/** Aprobar crea y publica el turno real (con las reglas de SaveShift). Segregación: nadie aprueba su propia toma. */
	public function Decide(int $claimId, bool $approve, int $userId, ?string $notes = null, bool $auto = false): array
	{
		$c = $this->Rows('SELECT * FROM labor_coverage_claims WHERE id = ?', [$claimId])[0] ?? null;
		if (!$c || $c['status'] !== 'pending')
		{
			throw new \Exception('La solicitud ya no está pendiente');
		}
		$own = $this->Sched()->GetEmployeeByUser($userId);
		if (!$auto && $own && intval($own->id) === intval($c['employee_id']))
		{
			throw new \Exception('No puede aprobar su propia solicitud', 403);
		}
		$r = $this->Request(intval($c['request_id']));
		$now = date('Y-m-d H:i:s');
		$empUser = intval($this->Rows('SELECT user_id FROM employees WHERE id = ?', [$c['employee_id']])[0]['user_id'] ?? 0);
		if (!$approve)
		{
			$this->Db()->prepare("UPDATE labor_coverage_claims SET status = 'rejected', decided_by_user_id = ?, decided_at = ?, decision_notes = ? WHERE id = ?")->execute([$userId, $now, $notes, $claimId]);
			PushService::GetInstance()->Notify([$empUser], 'open_shift_decision', 'Turno abierto no asignado', self::Label($r) . ($notes ? ' · ' . $notes : ''), ['route' => 'open-shifts']);
			return ['claim_id' => $claimId, 'status' => 'rejected'];
		}
		if ($this->Approved(intval($r['id'])) >= intval($r['staff_needed']))
		{
			throw new \Exception('El turno ya fue cubierto');
		}
		$db = $this->Db();
		$db->beginTransaction();
		try
		{
			$shift = $this->Sched()->SaveShift(['employee_id' => $c['employee_id'], 'branch_id' => $r['branch_id'], 'shift_date' => $r['request_date'], 'start_time' => substr($r['start_time'], 0, 5),
				'end_time' => substr($r['end_time'], 0, 5), 'break_minutes' => $r['break_minutes'], 'department' => $r['department'], 'role' => $r['role'],
				'notes' => 'Turno abierto #' . $r['id'] . ($r['reason'] ? ': ' . $r['reason'] : ''), 'reason' => 'Turno abierto tomado por el empleado'], $userId, 'create');
			$shiftId = intval($shift['id']);
			$this->Sched()->PublishShift($shiftId, $userId, 'Turno abierto aprobado');
			$db->prepare("UPDATE labor_coverage_claims SET status = 'approved', shift_id = ?, decided_by_user_id = ?, decided_at = ?, decision_notes = ? WHERE id = ?")->execute([$shiftId, $userId, $now, $notes, $claimId]);
			$closed = false;
			if ($this->Approved(intval($r['id'])) >= intval($r['staff_needed']))
			{
				$db->prepare("UPDATE labor_coverage_requests SET status = 'filled', closed_by_user_id = ?, closed_timestamp = ? WHERE id = ? AND status = 'open'")->execute([$userId, $now, $r['id']]);
				$db->prepare("UPDATE labor_coverage_claims SET status = 'rejected', decided_by_user_id = ?, decided_at = ?, decision_notes = 'Cupos completos' WHERE request_id = ? AND status = 'pending'")->execute([$userId, $now, $r['id']]);
				$closed = true;
			}
			$db->commit();
		}
		catch (\Throwable $e)
		{
			$db->rollBack();
			throw $e;
		}
		PushService::GetInstance()->Notify([$empUser], 'open_shift_decision', 'Turno asignado', self::Label($r) . ' · revise y confirme en Mi horario', ['shift_id' => $shiftId, 'route' => 'schedule']);
		return ['claim_id' => $claimId, 'status' => 'approved', 'shift_id' => $shiftId, 'request_closed' => $closed];
	}

	/** Bandeja del gerente: turnos abiertos de sus sucursales con las tomas pendientes */
	public function ForManager(array $branchIds): array
	{
		$in = implode(',', array_map('intval', $branchIds)) ?: '0';
		$reqs = $this->Rows("SELECT r.*, b.name AS branch, (SELECT COUNT(*) FROM labor_coverage_claims c WHERE c.request_id = r.id AND c.status = 'approved') AS filled
			FROM labor_coverage_requests r LEFT JOIN branches b ON b.id = r.branch_id WHERE r.branch_id IN ($in) AND r.status = 'open' AND r.request_date >= date('now', 'localtime') ORDER BY r.request_date, r.start_time");
		$claims = $this->Rows("SELECT c.*, e.name AS employee, e.position, r.request_date, r.start_time, r.end_time FROM labor_coverage_claims c JOIN employees e ON e.id = c.employee_id
			JOIN labor_coverage_requests r ON r.id = c.request_id WHERE r.branch_id IN ($in) AND c.status = 'pending' ORDER BY c.created_at");
		foreach ($reqs as &$q)
		{
			$q['claims'] = array_values(array_filter($claims, fn($c) => intval($c['request_id']) === intval($q['id'])));
			$q['auto_approve'] = intval($q['auto_approve']) === 1;
		}
		return ['requests' => $reqs, 'pending' => count($claims)];
	}
}
