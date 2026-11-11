<?php

namespace Grocy\Services;

/**
 * Personal y turnos (referencia funcional: UKG / Deputy / Blue Yonder WFM, adaptado a tienda):
 * empleados por sucursal, turnos con validación de conflictos, disponibilidad, ausencias, cambios de turno,
 * fichaje, horas programadas vs trabajadas, horas extra, cobertura por franja y productividad.
 * Las mermas se relacionan con turnos solo como contexto (franja/turno en que se registraron), nunca como culpa.
 */
class WorkforceScheduleService extends BaseService
{
	const ABSENCE_TYPES = ['vacation' => 'Vacaciones', 'sick' => 'Incapacidad', 'permission' => 'Permiso', 'other' => 'Otra'];

	private function Db(): \PDO
	{
		return DatabaseService::GetInstance()->GetDbConnectionRaw();
	}

	private function Q(string $sql, array $params = []): \PDOStatement
	{
		$stmt = $this->Db()->prepare($sql);
		$stmt->execute($params);
		return $stmt;
	}

	/* ------------------------------------------------------------------ Empleados */

	public function GetEmployees(?int $branchId = null, bool $activeOnly = true): array
	{
		$sql = "SELECT e.*, b.name AS branch_name, u.username FROM employees e LEFT JOIN branches b ON b.id = e.branch_id LEFT JOIN users u ON u.id = e.user_id WHERE 1 = 1";
		$p = [];
		if ($branchId)
		{
			$sql .= ' AND e.branch_id = :b';
			$p[':b'] = $branchId;
		}
		if ($activeOnly)
		{
			$sql .= ' AND e.active = 1';
		}
		return $this->Q($sql . ' ORDER BY e.name', $p)->fetchAll(\PDO::FETCH_OBJ);
	}

	public function GetEmployee(int $id)
	{
		$e = $this->Q('SELECT * FROM employees WHERE id = :id', [':id' => $id])->fetch(\PDO::FETCH_OBJ);
		if (!$e)
		{
			throw new \Exception('Empleado no encontrado');
		}
		return $e;
	}

	public function GetEmployeeByUser(int $userId)
	{
		return $this->Q('SELECT * FROM employees WHERE user_id = :u AND active = 1', [':u' => $userId])->fetch(\PDO::FETCH_OBJ) ?: null;
	}

	public function SaveEmployee(array $d): int
	{
		$name = trim(strval($d['name'] ?? ''));
		$branchId = intval($d['branch_id'] ?? 0);
		if ($name === '' || $branchId <= 0)
		{
			throw new \Exception('Nombre y sucursal son obligatorios');
		}
		if (!$this->Q('SELECT 1 FROM branches WHERE id = :b', [':b' => $branchId])->fetchColumn())
		{
			throw new \Exception('Sucursal inexistente');
		}
		$userId = !empty($d['user_id']) ? intval($d['user_id']) : null;
		if ($userId !== null && !$this->Q('SELECT 1 FROM users WHERE id = :u', [':u' => $userId])->fetchColumn())
		{
			throw new \Exception('Usuario inexistente');
		}
		$id = intval($d['id'] ?? 0);
		if ($userId !== null && $this->Q('SELECT 1 FROM employees WHERE user_id = :u AND id != :id', [':u' => $userId, ':id' => $id])->fetchColumn())
		{
			throw new \Exception('Ese usuario ya está vinculado a otro empleado');
		}
		$row = [
			':name' => $name, ':b' => $branchId, ':u' => $userId,
			':pos' => trim(strval($d['position'] ?? '')) ?: 'Auxiliar',
			':dep' => trim(strval($d['department'] ?? '')) ?: 'Lácteos',
			':wh' => ($d['weekly_hours'] ?? '') !== '' ? max(0, floatval($d['weekly_hours'])) : null,
			':hc' => ($d['hourly_cost'] ?? '') !== '' ? max(0, floatval($d['hourly_cost'])) : null,
			':a' => isset($d['active']) ? (intval($d['active']) ? 1 : 0) : 1
		];
		$payrollCode = trim(strval($d['payroll_code'] ?? ''));
		if ($id > 0)
		{
			$existingEmp = $this->GetEmployee($id);
			$effectivePc = $payrollCode !== '' ? $payrollCode : (trim(strval($existingEmp['payroll_code'] ?? '')) ?: ('EMP-' . str_pad(strval($id), 4, '0', STR_PAD_LEFT)));
			$this->Q('UPDATE employees SET name = :name, branch_id = :b, user_id = :u, position = :pos, department = :dep, weekly_hours = :wh, hourly_cost = :hc, active = :a, payroll_code = :pc WHERE id = :id', $row + [':pc' => $effectivePc, ':id' => $id]);
			return $id;
		}
		$this->Q('INSERT INTO employees (name, branch_id, user_id, position, department, weekly_hours, hourly_cost, active, payroll_code) VALUES (:name, :b, :u, :pos, :dep, :wh, :hc, :a, :pc)', $row + [':pc' => ($payrollCode !== '' ? $payrollCode : null)]);
		$newId = intval($this->Db()->lastInsertId());
		if ($payrollCode === '' && $newId > 0)
		{
			$this->Q('UPDATE employees SET payroll_code = :pc WHERE id = :id', [':pc' => 'EMP-' . str_pad(strval($newId), 4, '0', STR_PAD_LEFT), ':id' => $newId]);
		}
		return $newId;
	}

	public function SetAvailability(int $employeeId, array $days): void
	{
		$this->GetEmployee($employeeId);
		$db = $this->Db();
		$db->beginTransaction();
		try
		{
			$this->Q('DELETE FROM employee_availability WHERE employee_id = :e', [':e' => $employeeId]);
			foreach ($days as $d)
			{
				$wd = intval($d['weekday'] ?? 0);
				if ($wd < 1 || $wd > 7 || !$this->ValidTime($d['start_time'] ?? '') || !$this->ValidTime($d['end_time'] ?? ''))
				{
					continue;
				}
				$this->Q('INSERT INTO employee_availability (employee_id, weekday, start_time, end_time) VALUES (:e, :w, :s, :t)', [':e' => $employeeId, ':w' => $wd, ':s' => $d['start_time'], ':t' => $d['end_time']]);
			}
			$db->commit();
		}
		catch (\Throwable $ex)
		{
			$db->rollBack();
			throw $ex;
		}
	}

	public function GetAvailability(int $employeeId): array
	{
		return $this->Q('SELECT weekday, start_time, end_time FROM employee_availability WHERE employee_id = :e ORDER BY weekday', [':e' => $employeeId])->fetchAll(\PDO::FETCH_ASSOC);
	}

	/* ------------------------------------------------------------------ Turnos */

	private function ValidTime($t): bool
	{
		return is_string($t) && preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $t) === 1;
	}

	private function ValidDate($d): bool
	{
		return is_string($d) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) === 1 && strtotime($d) !== false;
	}

	// Inicio/fin en timestamp; si fin <= inicio, termina al día siguiente
	public static function Span(string $date, string $start, string $end): array
	{
		$s = strtotime("$date $start");
		$e = strtotime("$date $end");
		if ($e <= $s)
		{
			// Día calendario siguiente (no +86400: el cambio de horario de verano hace días de 23 o 25 h)
			$e = strtotime(date('Y-m-d', strtotime("$date +1 day")) . " $end");
		}
		return [$s, $e];
	}

	public static function ShiftHours($shift): float
	{
		[$s, $e] = self::Span($shift->shift_date, $shift->start_time, $shift->end_time);
		return max(0, round(($e - $s) / 3600 - intval($shift->break_minutes ?? 0) / 60, 2));
	}

	public function GetShift(int $id)
	{
		$s = $this->Q('SELECT * FROM employee_shifts WHERE id = :id', [':id' => $id])->fetch(\PDO::FETCH_OBJ);
		if (!$s)
		{
			throw new \Exception('Turno no encontrado');
		}
		return $s;
	}

	/**
	 * Conflictos de un turno propuesto. 'error' bloquea (solapamiento, ausencia aprobada); 'warning' se informa.
	 */
	public function CheckConflicts(int $employeeId, string $date, string $start, string $end, int $breakMinutes = 0, int $ignoreShiftId = 0, ?string $role = null): array
	{
		$emp = $this->GetEmployee($employeeId);
		$issues = $this->CertificationIssues($emp, $date, $role);
		[$s, $e] = self::Span($date, $start, $end);

		// Solapamiento con otros turnos del empleado (día anterior, mismo y siguiente por turnos nocturnos)
		$autoSvc = \Grocy\Services\WorkforceAutoScheduleService::GetInstance();
		$jPolicy = $autoSvc->GetBranchJurisdictionPolicy(intval($emp->branch_id ?? 1), $date);
		$others = $this->Q("SELECT * FROM employee_shifts WHERE employee_id = :e AND status != 'cancelled' AND id != :i AND shift_date BETWEEN date(:d, '-1 day') AND date(:d, '+1 day')", [':e' => $employeeId, ':i' => $ignoreShiftId, ':d' => $date])->fetchAll(\PDO::FETCH_OBJ);
		$minRestHrs = defined('GROCY_WORKFORCE_MIN_REST_HOURS') ? floatval(GROCY_WORKFORCE_MIN_REST_HOURS) : floatval($jPolicy['min_rest_hours'] ?? 10);
		$minRest = $minRestHrs * 3600;
		foreach ($others as $o)
		{
			[$os, $oe] = self::Span($o->shift_date, $o->start_time, $o->end_time);
			if ($s < $oe && $os < $e)
			{
				$issues[] = ['level' => 'error', 'code' => 'overlap', 'message' => "Se superpone con el turno del {$o->shift_date} {$o->start_time}–{$o->end_time}"];
			}
			elseif ($minRest > 0 && (($s >= $oe && $s - $oe < $minRest) || ($os >= $e && $os - $e < $minRest)))
			{
				$gapSec = ($s >= $oe ? $s - $oe : $os - $e);
				$gap = round($gapSec / 3600, 1);
				// Jornada partida válida intra-día (pausa >= 1h y <= 5h el mismo día) no viola descanso inter-jornada
				if ($o->shift_date === $date && $gapSec >= 3600 && $gapSec <= 18000)
				{
					$issues[] = ['level' => 'info', 'code' => 'split_shift', 'message' => "Jornada partida con pausa de {$gap} h el mismo día"];
				}
				else
				{
					$issues[] = ['level' => 'warning', 'code' => 'rest', 'message' => "Descanso de {$gap} h con el turno del {$o->shift_date} (mínimo {$minRestHrs} h · {$jPolicy['jurisdiction_code']})"];
				}
			}
		}

		// Ausencias
		foreach ($this->Q("SELECT * FROM employee_absences WHERE employee_id = :e AND status != 'rejected' AND :d BETWEEN start_date AND end_date", [':e' => $employeeId, ':d' => $date])->fetchAll(\PDO::FETCH_OBJ) as $a)
		{
			$type = self::ABSENCE_TYPES[$a->absence_type] ?? $a->absence_type;
			$issues[] = $a->status === 'approved'
				? ['level' => 'error', 'code' => 'absence', 'message' => "Ausencia aprobada ($type) del {$a->start_date} al {$a->end_date}"]
				: ['level' => 'warning', 'code' => 'absence_requested', 'message' => "Ausencia solicitada ($type) pendiente de aprobación"];
		}

		// Disponibilidad (solo si el empleado la declaró)
		$avail = $this->GetAvailability($employeeId);
		if (count($avail) > 0)
		{
			$wd = intval(date('N', strtotime($date)));
			$day = array_values(array_filter($avail, fn($a) => intval($a['weekday']) === $wd));
			if (count($day) === 0)
			{
				$issues[] = ['level' => 'warning', 'code' => 'availability', 'message' => 'El empleado no declaró disponibilidad ese día'];
			}
			else
			{
				[$as, $ae] = self::Span($date, $day[0]['start_time'], $day[0]['end_time']);
				if ($s < $as || $e > $ae)
				{
					$issues[] = ['level' => 'warning', 'code' => 'availability', 'message' => "Fuera de su disponibilidad ({$day[0]['start_time']}–{$day[0]['end_time']})"];
				}
			}
		}

		// Límite diario configurable (horas netas del día, incluido este turno)
		$maxDaily = floatval(defined('GROCY_WORKFORCE_MAX_DAILY_HOURS') ? GROCY_WORKFORCE_MAX_DAILY_HOURS : 10);
		if ($maxDaily > 0)
		{
			$dayShifts = array_filter($others, fn($o) => $o->shift_date === $date);
			$dayHours = array_sum(array_map(fn($x) => self::ShiftHours($x), $dayShifts)) + max(0, ($e - $s) / 3600 - $breakMinutes / 60);
			if ($dayHours > $maxDaily)
			{
				$issues[] = ['level' => 'warning', 'code' => 'daily_limit', 'message' => 'Día con ' . round($dayHours, 1) . " h programadas (límite $maxDaily h)"];
			}
		}

		// Horas de la semana vs contrato
		$monday = date('Y-m-d', strtotime('monday this week', strtotime($date)));
		$weekShifts = $this->Q("SELECT * FROM employee_shifts WHERE employee_id = :e AND status != 'cancelled' AND id != :i AND shift_date BETWEEN :m AND date(:m, '+6 day')", [':e' => $employeeId, ':i' => $ignoreShiftId, ':m' => $monday])->fetchAll(\PDO::FETCH_OBJ);
		$hours = array_sum(array_map(fn($x) => self::ShiftHours($x), $weekShifts)) + max(0, ($e - $s) / 3600 - $breakMinutes / 60);
		$contract = $this->ContractHours($emp);
		if ($hours > $contract)
		{
			$issues[] = ['level' => 'warning', 'code' => 'overtime', 'message' => 'Semana con ' . round($hours, 1) . " h programadas (contrato $contract h): " . round($hours - $contract, 1) . ' h extra'];
		}

		// Regla de Domingo Libre (3x1 o política legal de empresa configurada)
		if (intval(date('N', strtotime($date))) === 7)
		{
			$autoSvc = \Grocy\Services\WorkforceAutoScheduleService::GetInstance();
			$empCode = !empty($emp->payroll_code) ? $emp->payroll_code : strval($emp->id);
			$sunStatus = $autoSvc->CalculateSundayStatus($empCode, $date, intval($emp->company_id ?? 1) ?: 1);
			if (($sunStatus['status'] ?? '') === 'LIBRE')
			{
				$issues[] = [
					'level' => 'warning',
					'code' => 'sunday_3x1_free',
					'message' => "Domingo de descanso obligatorio según regla {$sunStatus['cycle_length']}x1 (Fase {$sunStatus['phase']})"
				];
			}
		}
		return $issues;
	}

	public function ContractHours($emp): float
	{
		return $emp->weekly_hours !== null ? floatval($emp->weekly_hours) : floatval(defined('GROCY_WORKFORCE_DEFAULT_WEEKLY_HOURS') ? GROCY_WORKFORCE_DEFAULT_WEEKLY_HOURS : 44);
	}

	const SHIFT_FIELDS = ['employee_id', 'branch_id', 'shift_date', 'start_time', 'end_time', 'break_minutes', 'department', 'role', 'notes', 'status'];

	private function Snapshot($s): array
	{
		$o = [];
		foreach (self::SHIFT_FIELDS as $f)
		{
			$o[$f] = $s->$f ?? null;
		}
		return $o;
	}

	private function LogChange(int $shiftId, int $branchId, string $action, ?array $before, ?array $after, int $userId, ?string $reason = null, ?string $batch = null): void
	{
		$this->Q('INSERT INTO employee_shift_changes (shift_id, branch_id, action, before_json, after_json, reason, batch_key, user_id, row_created_timestamp) VALUES (:s, :b, :a, :bj, :aj, :r, :k, :u, :t)', [
			':s' => $shiftId, ':b' => $branchId, ':a' => $action, ':bj' => $before ? json_encode($before, JSON_UNESCAPED_UNICODE) : null,
			':aj' => $after ? json_encode($after, JSON_UNESCAPED_UNICODE) : null, ':r' => $reason ?: null, ':k' => $batch, ':u' => $userId, ':t' => date('Y-m-d H:i:s')
		]);
	}

	// Ejecuta $fn en una transacción (o dentro de la vigente)
	private function Tx(callable $fn)
	{
		$db = $this->Db();
		if ($db->inTransaction())
		{
			return $fn();
		}
		$db->beginTransaction();
		try
		{
			$r = $fn();
			$db->commit();
			return $r;
		}
		catch (\Throwable $ex)
		{
			$db->rollBack();
			throw $ex;
		}
	}

	/**
	 * Crea o edita un turno planificado (no toca horas trabajadas: esas solo cambian por corrección de fichaje).
	 * 'version' opcional detecta ediciones simultáneas; cada cambio queda en employee_shift_changes.
	 */
	public function SaveShift(array $d, int $userId, ?string $action = null, ?string $batch = null): array
	{
		$employeeId = intval($d['employee_id'] ?? 0);
		$date = strval($d['shift_date'] ?? '');
		$start = strval($d['start_time'] ?? '');
		$end = strval($d['end_time'] ?? '');
		$break = max(0, min(240, intval($d['break_minutes'] ?? 0)));
		if (!$this->ValidDate($date) || !$this->ValidTime($start) || !$this->ValidTime($end) || $start === $end)
		{
			throw new \Exception('Fecha u horario inválido (HH:MM)');
		}
		[$ss, $se] = self::Span($date, $start, $end);
		if ($break * 60 >= $se - $ss)
		{
			throw new \Exception('El descanso no puede ser igual o mayor que la duración del turno');
		}
		$emp = $this->GetEmployee($employeeId);
		if (!intval($emp->active))
		{
			throw new \Exception('El empleado está inactivo');
		}
		$id = intval($d['id'] ?? 0);
		$current = null;
		if ($id > 0)
		{
			$current = $this->GetShift($id);
			if ($current->status === 'cancelled')
			{
				throw new \Exception('El turno está anulado');
			}
			if (isset($d['version']) && $d['version'] !== '' && intval($d['version']) !== intval($current->version))
			{
				throw new \Exception('Otra persona modificó este turno; recargue antes de guardar');
			}
			if (intval($current->employee_id) !== $employeeId && $this->Q('SELECT 1 FROM employee_attendance WHERE shift_id = :id', [':id' => $id])->fetchColumn())
			{
				throw new \Exception('El turno ya tiene fichajes; no se puede pasar a otro empleado');
			}
		}
		$issues = $this->CheckConflicts($employeeId, $date, $start, $end, $break, $id, trim(strval($d['role'] ?? '')) ?: null);
		$errors = array_values(array_filter($issues, fn($i) => $i['level'] === 'error'));
		if (count($errors) > 0)
		{
			throw new \Exception(implode('; ', array_column($errors, 'message')));
		}
		$branchResolved = intval($d['branch_id'] ?? 0) ?: ($current ? intval($current->branch_id) : intval($emp->branch_id));
		$jPolicy = \Grocy\Services\WorkforceAutoScheduleService::GetInstance()->GetBranchJurisdictionPolicy($branchResolved, $date);
		$shiftBand = trim(strval($d['shift_band'] ?? '')) ?: WorkforceLaborRulesService::DetectShiftBand($start, $end, $jPolicy);
		$isOvernight = ($end <= $start) ? 1 : 0;
		$compOrigin = !empty($d['compensatory_origin_date']) ? strval($d['compensatory_origin_date']) : null;

		$row = [
			':e' => $employeeId, ':b' => $branchResolved, ':d' => $date, ':s' => $start, ':t' => $end, ':br' => $break,
			':dep' => trim(strval($d['department'] ?? '')) ?: $emp->department, ':r' => trim(strval($d['role'] ?? '')) ?: $emp->position,
			':n' => trim(strval($d['notes'] ?? '')) ?: null,
			':sb' => $shiftBand, ':ov' => $isOvernight, ':co' => $compOrigin
		];
		$reason = trim(strval($d['reason'] ?? '')) ?: null;
		$now = date('Y-m-d H:i:s');
		$res = $this->Tx(function () use ($id, $current, $row, $userId, $action, $batch, $reason, $now, $issues) {
			if ($id > 0)
			{
				$n = $this->Q('UPDATE employee_shifts SET employee_id = :e, branch_id = :b, shift_date = :d, start_time = :s, end_time = :t, break_minutes = :br, department = :dep, role = :r, notes = :n, shift_band = :sb, is_overnight = :ov, compensatory_origin_date = :co, version = version + 1, updated_by_user_id = :u, updated_timestamp = :now WHERE id = :id AND version = :v',
					$row + [':id' => $id, ':v' => $current->version, ':u' => $userId, ':now' => $now])->rowCount();
				if ($n === 0)
				{
					throw new \Exception('Otra persona modificó este turno; recargue antes de guardar');
				}
				$after = $this->GetShift($id);
				// Una confirmación vale para el turno confirmado: si cambia fecha, horario o empleado, hay que volver a confirmar
				if ($current->confirmed_at !== null && ($current->shift_date !== $after->shift_date || $current->start_time !== $after->start_time || $current->end_time !== $after->end_time || intval($current->employee_id) !== intval($after->employee_id)))
				{
					$this->Q('UPDATE employee_shifts SET confirmed_at = NULL, confirmed_by_user_id = NULL WHERE id = :id', [':id' => $id]);
				}
				$this->LogChange($id, intval($after->branch_id), $action ?? 'update', $this->Snapshot($current), $this->Snapshot($after), $userId, $reason, $batch);
			}
			else
			{
				$this->Q("INSERT INTO employee_shifts (employee_id, branch_id, shift_date, start_time, end_time, break_minutes, department, role, notes, shift_band, is_overnight, compensatory_origin_date, status, created_by_user_id) VALUES (:e, :b, :d, :s, :t, :br, :dep, :r, :n, :sb, :ov, :co, 'planned', :u)", $row + [':u' => $userId]);
				$id = intval($this->Db()->lastInsertId());
				$after = $this->GetShift($id);
				$this->LogChange($id, intval($after->branch_id), $action ?? 'create', null, $this->Snapshot($after), $userId, $reason, $batch);
			}

			// Bidirectional sync to workforce_monthly_schedules & audit log
			try
			{
				$pCode = $this->Db()->query("SELECT payroll_code FROM employees WHERE id = " . intval($after->employee_id))->fetchColumn();
				if ($pCode)
				{
					$autoSvc = \Grocy\Services\WorkforceAutoScheduleService::GetInstance();
					$sapCode = $autoSvc->DeriveSapCode($after->start_time, $after->end_time, $after->department);
					$autoSvc->SyncSingleShiftToMonthly($pCode, $after->shift_date, $sapCode, $userId, $reason ?: 'Actualización desde vista semanal');
				}
			}
			catch (\Throwable $syncEx)
			{
				// Non-blocking sync error
			}

			return ['id' => $id, 'version' => intval($after->version), 'status' => $after->status, 'warnings' => array_values(array_filter($issues, fn($i) => $i['level'] === 'warning'))];
		});
		// Cambio de un turno ya publicado: aviso push al empleado anterior y al nuevo (después del commit)
		if ($current && $current->status === 'published')
		{
			try
			{
				$after = $this->GetShift(intval($res['id']));
				$label = date('d/m', strtotime($after->shift_date)) . ' ' . substr($after->start_time, 0, 5) . '–' . substr($after->end_time, 0, 5);
				foreach (array_unique([intval($current->employee_id), intval($after->employee_id)]) as $empId)
				{
					$uid = $this->Q('SELECT user_id FROM employees WHERE id = :e', [':e' => $empId])->fetchColumn();
					if ($uid)
					{
						$mine = $empId === intval($after->employee_id);
						PushService::GetInstance()->Notify([intval($uid)], 'shift_changed', $mine ? 'Tu turno cambió' : 'Turno reasignado', $mine ? $label . ' · confirme de nuevo' : 'Ya no tiene el turno del ' . date('d/m', strtotime($current->shift_date)), ['shift_id' => intval($after->id), 'route' => 'schedule'], !$this->Db()->inTransaction());
					}
				}
			}
			catch (\Throwable $e)
			{
			}
		}
		return $res;
	}

	// Arrastrar y soltar: cambia empleado y/o fecha conservando el horario
	public function MoveShift(int $id, int $employeeId, string $date, int $userId, string $action = 'move', ?string $reason = null): array
	{
		$s = $this->GetShift($id);
		return $this->SaveShift(['id' => $id, 'employee_id' => $employeeId, 'shift_date' => $date, 'start_time' => $s->start_time, 'end_time' => $s->end_time, 'break_minutes' => $s->break_minutes, 'branch_id' => $s->branch_id, 'department' => $s->department, 'role' => $s->role, 'notes' => $s->notes, 'reason' => $reason], $userId, $action);
	}

	public function CancelShift(int $id, int $userId = 0, ?string $reason = null, ?string $batch = null): void
	{
		$s = $this->GetShift($id);
		if ($s->status === 'cancelled')
		{
			throw new \Exception('El turno ya está anulado');
		}
		if ($this->Q('SELECT 1 FROM employee_attendance WHERE shift_id = :id', [':id' => $id])->fetchColumn())
		{
			throw new \Exception('El turno ya tiene fichajes; no se puede anular');
		}
		$this->Tx(function () use ($s, $id, $userId, $reason, $batch) {
			$this->Q("UPDATE employee_shifts SET status = 'cancelled', version = version + 1, updated_by_user_id = :u, updated_timestamp = :now WHERE id = :id", [':id' => $id, ':u' => $userId, ':now' => date('Y-m-d H:i:s')]);
			$this->LogChange($id, intval($s->branch_id), 'cancel', $this->Snapshot($s), $this->Snapshot($this->GetShift($id)), $userId, $reason, $batch);

			try
			{
				$pCode = $this->Db()->query("SELECT payroll_code FROM employees WHERE id = " . intval($s->employee_id))->fetchColumn();
				if ($pCode)
				{
					\Grocy\Services\WorkforceAutoScheduleService::GetInstance()->SyncSingleShiftToMonthly($pCode, $s->shift_date, 'LIBRE', $userId, $reason ?: 'Turno anulado');
				}
			}
			catch (\Throwable $syncEx) {}
		});
	}

	public function PublishWeek(int $branchId, string $monday, int $userId = 0): int
	{
		$monday = date('Y-m-d', strtotime('monday this week', strtotime($monday)));
		$published = [];
		$n = $this->Tx(function () use ($branchId, $monday, $userId, &$published) {
			$rows = $this->Q("SELECT * FROM employee_shifts WHERE branch_id = :b AND status = 'planned' AND shift_date BETWEEN :m AND date(:m, '+6 day')", [':b' => $branchId, ':m' => $monday])->fetchAll(\PDO::FETCH_OBJ);
			$batch = 'pub-' . uniqid();
			foreach ($rows as $s)
			{
				$this->Q("UPDATE employee_shifts SET status = 'published', version = version + 1 WHERE id = :id", [':id' => $s->id]);
				$this->LogChange(intval($s->id), $branchId, 'publish', $this->Snapshot($s), ['status' => 'published'] + $this->Snapshot($s), $userId, null, $batch);
				$published[intval($s->employee_id)] = ($published[intval($s->employee_id)] ?? 0) + 1;
			}
			return count($rows);
		});
		// Aviso push a cada empleado con turnos publicados (después del commit; un fallo del aviso no revierte la publicación)
		if ($published)
		{
			try
			{
				$users = $this->Q('SELECT id, user_id FROM employees WHERE user_id IS NOT NULL AND id IN (' . implode(',', array_keys($published)) . ')')->fetchAll(\PDO::FETCH_KEY_PAIR);
				foreach ($users as $empId => $uid)
				{
					PushService::GetInstance()->Notify([intval($uid)], 'schedule_published', 'Horario publicado', $published[$empId] . ' turno(s) en la semana del ' . date('d/m', strtotime($monday)) . '. Revise y confirme.', ['route' => 'schedule']);
				}
			}
			catch (\Throwable $e)
			{
			}
		}
		return $n;
	}

	// Copia los turnos de una semana a otra; valida conflictos antes y permite modo atómico para evitar semanas parciales
	public function CopyWeek(int $branchId, string $fromMonday, string $toMonday, int $userId, bool $atomic = false): array
	{
		$shifts = $this->Q("SELECT * FROM employee_shifts WHERE branch_id = :b AND status != 'cancelled' AND shift_date BETWEEN :m AND date(:m, '+6 day')", [':b' => $branchId, ':m' => $fromMonday])->fetchAll(\PDO::FETCH_OBJ);
		$offset = intval(round((strtotime($toMonday) - strtotime($fromMonday)) / 86400));
		$created = 0;
		$skipped = [];
		$validPayloads = [];

		foreach ($shifts as $s)
		{
			$date = date('Y-m-d', strtotime("{$s->shift_date} {$offset} days"));
			$issues = $this->CheckConflicts(intval($s->employee_id), $date, $s->start_time, $s->end_time, intval($s->break_minutes ?? 0), 0, $s->role);
			$errors = array_values(array_filter($issues, fn($i) => $i['level'] === 'error'));
			if (count($errors) > 0)
			{
				$skipped[] = "$date: " . implode('; ', array_column($errors, 'message'));
			}
			else
			{
				$validPayloads[] = [
					'employee_id' => $s->employee_id,
					'shift_date' => $date,
					'start_time' => $s->start_time,
					'end_time' => $s->end_time,
					'break_minutes' => $s->break_minutes,
					'branch_id' => $s->branch_id,
					'department' => $s->department,
					'role' => $s->role,
					'notes' => $s->notes
				];
			}
		}

		if ($atomic && count($skipped) > 0)
		{
			throw new \Exception('Copia atómica cancelada por conflictos: ' . implode(' | ', array_slice($skipped, 0, 5)));
		}

		$batch = 'copy-' . uniqid();
		$this->Tx(function () use ($validPayloads, $userId, $batch, &$created, &$skipped) {
			foreach ($validPayloads as $payload)
			{
				try
				{
					$this->SaveShift($payload, $userId, 'duplicate', $batch);
					$created++;
				}
				catch (\Exception $ex)
				{
					$skipped[] = $payload['shift_date'] . ': ' . $ex->getMessage();
				}
			}
		});
		return ['created' => $created, 'skipped' => $skipped];
	}

	// Duplica un turno a otras fechas y/o empleados (mismo horario); omite y reporta los que tengan conflicto
	public function DuplicateShift(int $id, array $dates, array $employeeIds, int $userId): array
	{
		$s = $this->GetShift($id);
		$dates = array_values(array_unique(array_filter(array_map('strval', $dates ?: [$s->shift_date]), fn($d) => $this->ValidDate($d))));
		$employeeIds = array_values(array_unique(array_map('intval', $employeeIds ?: [$s->employee_id])));
		if (count($dates) * count($employeeIds) > 200)
		{
			throw new \Exception('Demasiados turnos en una sola operación (máximo 200)');
		}
		$created = [];
		$skipped = [];
		$batch = 'dup-' . uniqid();
		foreach ($employeeIds as $e)
		{
			foreach ($dates as $d)
			{
				if ($e === intval($s->employee_id) && $d === $s->shift_date)
				{
					continue;
				}
				try
				{
					$created[] = $this->SaveShift(['employee_id' => $e, 'shift_date' => $d, 'start_time' => $s->start_time, 'end_time' => $s->end_time, 'break_minutes' => $s->break_minutes, 'branch_id' => $s->branch_id, 'department' => $s->department, 'role' => $s->role, 'notes' => $s->notes], $userId, 'duplicate', $batch)['id'];
				}
				catch (\Exception $ex)
				{
					$skipped[] = "$d · empleado $e: " . $ex->getMessage();
				}
			}
		}
		return ['created' => $created, 'skipped' => $skipped];
	}

	// Divide un turno en dos (p. ej. jornada partida o cobertura compartida); el descanso queda en el tramo más largo
	public function SplitShift(int $id, string $at, ?int $secondEmployeeId, int $userId, ?string $reason = null): array
	{
		$s = $this->GetShift($id);
		if ($s->status === 'cancelled' || !$this->ValidTime($at))
		{
			throw new \Exception('Turno anulado u hora de corte inválida');
		}
		if ($this->Q('SELECT 1 FROM employee_attendance WHERE shift_id = :id', [':id' => $id])->fetchColumn())
		{
			throw new \Exception('El turno ya tiene fichajes; no se puede dividir');
		}
		[$ss, $se] = self::Span($s->shift_date, $s->start_time, $s->end_time);
		$cut = strtotime("{$s->shift_date} $at");
		if ($cut <= $ss)
		{
			$cut = strtotime(date('Y-m-d', strtotime("{$s->shift_date} +1 day")) . " $at");
		}
		if ($cut - $ss < 3600 || $se - $cut < 3600)
		{
			throw new \Exception('Cada tramo debe durar al menos 1 hora');
		}
		$firstLonger = ($cut - $ss) >= ($se - $cut);
		$break = intval($s->break_minutes);
		$batch = 'split-' . uniqid();
		return $this->Tx(function () use ($s, $id, $at, $cut, $firstLonger, $break, $secondEmployeeId, $userId, $reason, $batch) {
			$this->SaveShift(['id' => $id, 'version' => $s->version, 'employee_id' => $s->employee_id, 'shift_date' => $s->shift_date, 'start_time' => $s->start_time, 'end_time' => $at, 'break_minutes' => $firstLonger ? $break : 0, 'branch_id' => $s->branch_id, 'department' => $s->department, 'role' => $s->role, 'notes' => $s->notes, 'reason' => $reason], $userId, 'split', $batch);
			$second = $this->SaveShift(['employee_id' => $secondEmployeeId ?: $s->employee_id, 'shift_date' => date('Y-m-d', $cut), 'start_time' => $at, 'end_time' => $s->end_time, 'break_minutes' => $firstLonger ? 0 : $break, 'branch_id' => $s->branch_id, 'department' => $s->department, 'role' => $s->role, 'notes' => $s->notes, 'reason' => $reason], $userId, 'split', $batch);
			return ['first_id' => $id, 'second_id' => $second['id'], 'warnings' => $second['warnings']];
		});
	}

	// Alta masiva: mismos horarios para varios empleados y fechas
	public function BulkCreateShifts(array $d, int $userId, string $action = 'create', ?string $batch = null): array
	{
		$dates = array_values(array_unique(array_map('strval', $d['dates'] ?? [])));
		$employeeIds = array_values(array_unique(array_map('intval', $d['employee_ids'] ?? [])));
		if (count($dates) === 0 || count($employeeIds) === 0)
		{
			throw new \Exception('Seleccione al menos un empleado y una fecha');
		}
		if (count($dates) * count($employeeIds) > 200)
		{
			throw new \Exception('Demasiados turnos en una sola operación (máximo 200)');
		}
		$batch = $batch ?? 'bulk-' . uniqid();
		$created = [];
		$skipped = [];
		foreach ($employeeIds as $e)
		{
			foreach ($dates as $date)
			{
				try
				{
					$created[] = $this->SaveShift(['employee_id' => $e, 'shift_date' => $date] + array_intersect_key($d, array_flip(['start_time', 'end_time', 'break_minutes', 'branch_id', 'department', 'role', 'notes'])), $userId, $action, $batch)['id'];
				}
				catch (\Exception $ex)
				{
					$skipped[] = "$date · empleado $e: " . $ex->getMessage();
				}
			}
		}
		return ['created' => $created, 'skipped' => $skipped, 'batch' => $batch];
	}

	/* ------------------------------------------------------------------ Plantillas */

	public function GetTemplates(int $branchId): array
	{
		return $this->Q('SELECT * FROM shift_templates WHERE branch_id = :b AND active = 1 ORDER BY start_time, name', [':b' => $branchId])->fetchAll(\PDO::FETCH_OBJ);
	}

	public function SaveTemplate(array $d, int $userId): int
	{
		$name = trim(strval($d['name'] ?? ''));
		$start = strval($d['start_time'] ?? '');
		$end = strval($d['end_time'] ?? '');
		$days = array_values(array_unique(array_filter(array_map('intval', is_array($d['weekdays'] ?? null) ? $d['weekdays'] : explode(',', strval($d['weekdays'] ?? ''))), fn($x) => $x >= 1 && $x <= 7)));
		sort($days);
		if ($name === '' || !$this->ValidTime($start) || !$this->ValidTime($end) || $start === $end || count($days) === 0 || intval($d['branch_id'] ?? 0) <= 0)
		{
			throw new \Exception('Nombre, horario, sucursal y días son obligatorios');
		}
		$p = [':b' => intval($d['branch_id']), ':n' => $name, ':dep' => trim(strval($d['department'] ?? '')) ?: null, ':r' => trim(strval($d['role'] ?? '')) ?: null,
			':s' => $start, ':e' => $end, ':br' => max(0, min(240, intval($d['break_minutes'] ?? 0))), ':w' => implode(',', $days)];
		if (!empty($d['id']))
		{
			$this->Q('UPDATE shift_templates SET branch_id = :b, name = :n, department = :dep, role = :r, start_time = :s, end_time = :e, break_minutes = :br, weekdays = :w WHERE id = :id', $p + [':id' => intval($d['id'])]);
			return intval($d['id']);
		}
		$this->Q('INSERT INTO shift_templates (branch_id, name, department, role, start_time, end_time, break_minutes, weekdays, created_by_user_id) VALUES (:b, :n, :dep, :r, :s, :e, :br, :w, :u)', $p + [':u' => $userId]);
		return intval($this->Db()->lastInsertId());
	}

	public function DeleteTemplate(int $id): void
	{
		$this->Q('UPDATE shift_templates SET active = 0 WHERE id = :id', [':id' => $id]);
	}

	public function GetTemplate(int $id)
	{
		$t = $this->Q('SELECT * FROM shift_templates WHERE id = :id AND active = 1', [':id' => $id])->fetch(\PDO::FETCH_OBJ);
		if (!$t)
		{
			throw new \Exception('Plantilla no encontrada');
		}
		return $t;
	}

	// Aplica una plantilla a una semana para los empleados indicados (en borrador)
	public function ApplyTemplate(int $id, string $monday, array $employeeIds, int $userId): array
	{
		$t = $this->GetTemplate($id);
		$monday = date('Y-m-d', strtotime('monday this week', strtotime($monday)));
		$dates = array_map(fn($w) => date('Y-m-d', strtotime("$monday +" . (intval($w) - 1) . ' day')), explode(',', $t->weekdays));
		return $this->BulkCreateShifts(['employee_ids' => $employeeIds, 'dates' => $dates, 'start_time' => $t->start_time, 'end_time' => $t->end_time, 'break_minutes' => $t->break_minutes, 'branch_id' => $t->branch_id, 'department' => $t->department, 'role' => $t->role], $userId, 'template', 'tpl-' . uniqid());
	}

	/* ------------------------------------------------------------------ Historial y deshacer */

	public function GetShiftHistory(int $shiftId): array
	{
		return $this->Q('SELECT c.*, u.display_name AS user_name FROM employee_shift_changes c LEFT JOIN users_dto u ON u.id = c.user_id WHERE c.shift_id = :s ORDER BY c.id DESC', [':s' => $shiftId])->fetchAll(\PDO::FETCH_OBJ);
	}

	public function GetChangeLog(int $branchId, int $limit = 50): array
	{
		return $this->Q("SELECT c.*, u.display_name AS user_name, e.name AS employee_name FROM employee_shift_changes c LEFT JOIN users_dto u ON u.id = c.user_id
			LEFT JOIN employee_shifts s ON s.id = c.shift_id LEFT JOIN employees e ON e.id = s.employee_id
			WHERE c.branch_id = :b ORDER BY c.id DESC LIMIT " . max(1, min(200, $limit)), [":b" => $branchId])->fetchAll(\PDO::FETCH_OBJ);
	}

	// Última operación deshacible (del usuario, sin publicar) en la sucursal
	private function LastUndoable(int $branchId, int $userId)
	{
		return $this->Q("SELECT * FROM employee_shift_changes WHERE branch_id = :b AND user_id = :u AND undone = 0 AND action IN ('create', 'update', 'move', 'cancel', 'split', 'duplicate', 'template') ORDER BY id DESC LIMIT 1", [':b' => $branchId, ':u' => $userId])->fetch(\PDO::FETCH_OBJ);
	}

	/**
	 * Deshace la última operación del usuario (o todo su lote: copiar semana, plantilla, masivo, dividir) si los turnos
	 * siguen en borrador y no cambiaron después. Lo publicado no se deshace: se edita y se vuelve a publicar.
	 */
	public function UndoLastChange(int $branchId, int $userId): array
	{
		$last = $this->LastUndoable($branchId, $userId);
		if (!$last)
		{
			throw new \Exception('No hay cambios suyos para deshacer');
		}
		$changes = $last->batch_key
			? $this->Q('SELECT * FROM employee_shift_changes WHERE batch_key = :k AND undone = 0 ORDER BY id DESC', [':k' => $last->batch_key])->fetchAll(\PDO::FETCH_OBJ)
			: [$last];
		return $this->Tx(function () use ($changes, $userId, $branchId) {
			$undoBatch = 'undo-' . uniqid();
			foreach ($changes as $c)
			{
				$s = $this->GetShift(intval($c->shift_id));
				$newer = $this->Q('SELECT 1 FROM employee_shift_changes WHERE shift_id = :s AND id > :id AND undone = 0', [':s' => $c->shift_id, ':id' => $c->id])->fetchColumn();
				if ($newer)
				{
					throw new \Exception("El turno #{$s->id} cambió después; no se puede deshacer");
				}
				$before = $c->before_json ? json_decode($c->before_json, true) : null;
				if ($s->status === 'published' || ($before && $before['status'] === 'published'))
				{
					throw new \Exception("El turno #{$s->id} ya fue publicado; edítelo y vuelva a publicar");
				}
				if ($this->Q('SELECT 1 FROM employee_attendance WHERE shift_id = :id', [':id' => $s->id])->fetchColumn())
				{
					throw new \Exception("El turno #{$s->id} ya tiene fichajes");
				}
				if ($before === null)
				{
					$this->Q("UPDATE employee_shifts SET status = 'cancelled', version = version + 1 WHERE id = :id", [':id' => $s->id]);
				}
				else
				{
					if ($before['status'] !== 'cancelled')
					{
						$err = array_filter($this->CheckConflicts(intval($before['employee_id']), $before['shift_date'], $before['start_time'], $before['end_time'], intval($before['break_minutes']), intval($s->id), $before['role']), fn($i) => $i['level'] === 'error');
						if (count($err) > 0)
						{
							throw new \Exception('No se puede deshacer: ' . implode('; ', array_column($err, 'message')));
						}
					}
					$this->Q('UPDATE employee_shifts SET employee_id = :e, branch_id = :b, shift_date = :d, start_time = :s, end_time = :t, break_minutes = :br, department = :dep, role = :r, notes = :n, status = :st, version = version + 1 WHERE id = :id', [
						':e' => $before['employee_id'], ':b' => $before['branch_id'], ':d' => $before['shift_date'], ':s' => $before['start_time'], ':t' => $before['end_time'], ':br' => $before['break_minutes'],
						':dep' => $before['department'], ':r' => $before['role'], ':n' => $before['notes'], ':st' => $before['status'], ':id' => $s->id
					]);
				}
				$this->Q('UPDATE employee_shift_changes SET undone = 1 WHERE id = :id', [':id' => $c->id]);
				$this->LogChange(intval($s->id), $branchId, 'undo', $this->Snapshot($s), $this->Snapshot($this->GetShift(intval($s->id))), $userId, 'Deshace cambio #' . $c->id, $undoBatch);
			}
			$this->Q('UPDATE employee_shift_changes SET undone = 1 WHERE batch_key = :k', [':k' => $undoBatch]);
			return ['undone' => count($changes)];
		});
	}

	public function CanUndo(int $branchId, int $userId): bool
	{
		return (bool) $this->LastUndoable($branchId, $userId);
	}

	/* ------------------------------------------------------------------ Semana (calendario) */

	public function GetWeek(int $branchId, string $monday): array
	{
		$monday = preg_match('/^\d{4}-\d{2}-\d{2}$/', $monday) ? $monday : date('Y-m-d', strtotime('monday this week', strtotime($monday)));
		$days = [];
		for ($i = 0; $i < 7; $i++)
		{
			$days[] = date('Y-m-d', strtotime("$monday +$i day"));
		}
		$sunday = end($days);
		$employees = $this->GetEmployees($branchId);
		$shifts = $this->Q("SELECT s.*, e.name AS employee_name FROM employee_shifts s JOIN employees e ON e.id = s.employee_id WHERE s.branch_id = :b AND s.status != 'cancelled' AND s.shift_date BETWEEN :m AND :s ORDER BY s.shift_date, s.start_time", [':b' => $branchId, ':m' => $monday, ':s' => $sunday])->fetchAll(\PDO::FETCH_OBJ);
		$absences = $this->Q("SELECT a.* FROM employee_absences a JOIN employees e ON e.id = a.employee_id WHERE e.branch_id = :b AND a.status != 'rejected' AND a.start_date <= :s AND a.end_date >= :m", [':b' => $branchId, ':m' => $monday, ':s' => $sunday])->fetchAll(\PDO::FETCH_OBJ);
		$worked = $this->WorkedHoursByEmployee($monday, $sunday, $branchId);

		$grid = [];
		$totals = [];
		foreach ($employees as $e)
		{
			$grid[$e->id] = array_fill_keys($days, []);
			$totals[$e->id] = ['scheduled' => 0.0, 'worked' => $worked[$e->id] ?? 0.0, 'contract' => $this->ContractHours($e)];
		}
		foreach ($shifts as $s)
		{
			$s->hours = self::ShiftHours($s);
			$s->issues = array_values(array_filter($this->CheckConflicts(intval($s->employee_id), $s->shift_date, $s->start_time, $s->end_time, intval($s->break_minutes), intval($s->id), $s->role), fn($i) => $i['code'] !== 'overtime'));
			if (isset($grid[$s->employee_id]))
			{
				$grid[$s->employee_id][$s->shift_date][] = $s;
				$totals[$s->employee_id]['scheduled'] += $s->hours;
			}
		}
		foreach ($totals as $id => &$t)
		{
			$t['scheduled'] = round($t['scheduled'], 2);
			$t['overtime'] = round(max(0, $t['worked'] - $t['contract']), 2);
			$t['scheduled_overtime'] = round(max(0, $t['scheduled'] - $t['contract']), 2);
		}
		unset($t);

		$absByEmp = [];
		foreach ($absences as $a)
		{
			foreach ($days as $d)
			{
				if ($d >= $a->start_date && $d <= $a->end_date)
				{
					$absByEmp[$a->employee_id][$d] = $a;
				}
			}
		}

		$coverage = [];
		foreach ($days as $d)
		{
			$coverage[$d] = $this->CoverageForDay($branchId, $d, $shifts);
		}

		return [
			'monday' => $monday,
			'days' => $days,
			'employees' => $employees,
			'grid' => $grid,
			'totals' => $totals,
			'absences' => $absByEmp,
			'coverage' => $coverage,
			'planned_count' => count(array_filter($shifts, fn($s) => $s->status === 'planned')),
			'labor_cost' => round(array_sum(array_map(function ($e) use ($totals) {
				return floatval($e->hourly_cost ?? 0) * $totals[$e->id]['scheduled'];
			}, $employees)), 2)
		];
	}

	// Vista mensual: por día, turnos, horas programadas, ausencias y horas con faltante de cobertura
	public function GetMonthSummary(int $branchId, string $month): array
	{
		$first = $month . '-01';
		$last = date('Y-m-t', strtotime($first));
		$shifts = $this->Q("SELECT * FROM employee_shifts WHERE branch_id = :b AND status != 'cancelled' AND shift_date BETWEEN date(:f, '-1 day') AND :l", [':b' => $branchId, ':f' => $first, ':l' => $last])->fetchAll(\PDO::FETCH_OBJ);
		$abs = $this->Q("SELECT a.* FROM employee_absences a JOIN employees e ON e.id = a.employee_id WHERE e.branch_id = :b AND a.status = 'approved' AND a.start_date <= :l AND a.end_date >= :f", [':b' => $branchId, ':f' => $first, ':l' => $last])->fetchAll(\PDO::FETCH_OBJ);
		$out = [];
		for ($d = $first; $d <= $last; $d = date('Y-m-d', strtotime("$d +1 day")))
		{
			$day = array_filter($shifts, fn($s) => $s->shift_date === $d);
			$near = array_filter($shifts, fn($s) => $s->shift_date === $d || $s->shift_date === date('Y-m-d', strtotime("$d -1 day")));
			$cov = $this->CoverageForDay($branchId, $d, array_values($near));
			$out[$d] = [
				'shifts' => count($day),
				'hours' => round(array_sum(array_map(fn($s) => self::ShiftHours($s), $day)), 1),
				'absences' => count(array_filter($abs, fn($a) => $d >= $a->start_date && $d <= $a->end_date)),
				'gap_hours' => count($cov['gaps']),
				'has_requirements' => $cov['has_requirements']
			];
		}
		return $out;
	}

	// Personas programadas por hora vs requisito mínimo (agregado correctamente por departamento)
	public function CoverageForDay(int $branchId, string $date, ?array $shifts = null): array
	{
		if ($shifts === null)
		{
			$shifts = $this->Q("SELECT * FROM employee_shifts WHERE branch_id = :b AND status != 'cancelled' AND shift_date BETWEEN date(:d, '-1 day') AND :d", [':b' => $branchId, ':d' => $date])->fetchAll(\PDO::FETCH_OBJ);
		}
		$wd = intval(date('N', strtotime($date)));
		$reqs = $this->Q('SELECT * FROM coverage_requirements WHERE branch_id = :b AND weekday IN (0, :w)', [':b' => $branchId, ':w' => $wd])->fetchAll(\PDO::FETCH_OBJ);
		$staff = array_fill(0, 24, 0);
		foreach ($shifts as $s)
		{
			[$ss, $se] = self::Span($s->shift_date, $s->start_time, $s->end_time);
			for ($h = 0; $h < 24; $h++)
			{
				$hs = strtotime("$date " . sprintf('%02d:00', $h));
				if ($ss <= $hs && $se >= $hs + 3600)
				{
					$staff[$h]++;
				}
			}
		}
		// Agrupar requisitos primero por departamento antes de sumar al total de tienda
		$reqByDept = [];
		foreach ($reqs as $r)
		{
			$dept = trim(strval($r->department ?? '')) ?: 'General';
			if (!isset($reqByDept[$dept]))
			{
				$reqByDept[$dept] = array_fill(0, 24, 0);
			}
			[$rs, $re] = self::Span($date, $r->start_time, $r->end_time);
			for ($h = 0; $h < 24; $h++)
			{
				$hs = strtotime("$date " . sprintf('%02d:00', $h));
				if ($hs >= $rs && $hs < $re)
				{
					$reqByDept[$dept][$h] = max($reqByDept[$dept][$h], intval($r->min_staff));
				}
			}
		}
		$required = array_fill(0, 24, 0);
		foreach ($reqByDept as $deptHours)
		{
			for ($h = 0; $h < 24; $h++)
			{
				$required[$h] += $deptHours[$h];
			}
		}
		$gaps = [];
		for ($h = 0; $h < 24; $h++)
		{
			if ($required[$h] > $staff[$h])
			{
				$gaps[] = ['hour' => $h, 'required' => $required[$h], 'scheduled' => $staff[$h]];
			}
		}
		return ['staff' => $staff, 'required' => $required, 'gaps' => $gaps, 'has_requirements' => count($reqs) > 0];
	}

	/**
	 * Cobertura precisa por departamento, hora (fraccional con descuento proporcional de descansos) y banda operativa
	 * (apertura, intermedio, cierre, nocturno). Evita mezclar departamentos ni descartar turnos que empiezan a media hora.
	 */
	public function CoverageByDepartmentAndHour(int $branchId, string $date, ?array $shifts = null): array
	{
		if ($shifts === null)
		{
			$shifts = $this->Q("SELECT * FROM employee_shifts WHERE branch_id = :b AND status != 'cancelled' AND shift_date BETWEEN date(:d, '-1 day') AND :d", [':b' => $branchId, ':d' => $date])->fetchAll(\PDO::FETCH_OBJ);
		}
		$wd = intval(date('N', strtotime($date)));
		$reqs = $this->Q('SELECT * FROM coverage_requirements WHERE branch_id = :b AND weekday IN (0, :w)', [':b' => $branchId, ':w' => $wd])->fetchAll(\PDO::FETCH_OBJ);
		$deptConfigs = $this->Q('SELECT * FROM workforce_department_configs WHERE branch_id = :b', [':b' => $branchId])->fetchAll(\PDO::FETCH_ASSOC);
		$policy = \Grocy\Services\WorkforceAutoScheduleService::GetInstance()->GetBranchJurisdictionPolicy($branchId, $date);

		$departments = [];
		$initDept = function (string $name) use (&$departments) {
			if (!isset($departments[$name]))
			{
				$departments[$name] = [
					'department' => $name,
					'gross_hourly' => array_fill(0, 24, 0.0),
					'effective_hourly' => array_fill(0, 24, 0.0),
					'required_hourly' => array_fill(0, 24, 0),
					'bands' => ['apertura' => 0, 'intermedio' => 0, 'cierre' => 0, 'nocturno' => 0],
					'min_open' => 1,
					'min_peak' => 1,
					'gaps' => []
				];
			}
		};

		foreach ($deptConfigs as $cfg)
		{
			$dName = trim(strval($cfg['department'] ?? '')) ?: 'General';
			$initDept($dName);
			$departments[$dName]['min_open'] = max(1, intval($cfg['min_staff_open'] ?? 1));
			$departments[$dName]['min_peak'] = max(1, intval($cfg['min_staff_peak'] ?? 1));
		}

		foreach ($reqs as $r)
		{
			$dName = trim(strval($r->department ?? '')) ?: 'General';
			$initDept($dName);
			[$rs, $re] = self::Span($date, $r->start_time, $r->end_time);
			for ($h = 0; $h < 24; $h++)
			{
				$hs = strtotime("$date " . sprintf('%02d:00', $h));
				$he = $hs + 3600;
				if ($hs < $re && $he > $rs)
				{
					$departments[$dName]['required_hourly'][$h] = max($departments[$dName]['required_hourly'][$h], intval($r->min_staff));
				}
			}
		}

		$storeHourlyEffective = array_fill(0, 24, 0.0);
		$storeHourlyRequired = array_fill(0, 24, 0);
		$bandTotals = ['apertura' => 0, 'intermedio' => 0, 'cierre' => 0, 'nocturno' => 0];

		foreach ($shifts as $s)
		{
			$dName = trim(strval($s->department ?? '')) ?: 'General';
			$initDept($dName);
			[$ss, $se] = self::Span($s->shift_date, $s->start_time, $s->end_time);
			$totalSec = max(1, $se - $ss);
			$breakSec = min($totalSec, max(0, intval($s->break_minutes ?? 0)) * 60);
			$effectiveRatio = ($totalSec - $breakSec) / $totalSec;

			if ($s->shift_date === $date)
			{
				$band = !empty($s->shift_band) ? strval($s->shift_band) : WorkforceLaborRulesService::DetectShiftBand($s->start_time, $s->end_time, $policy);
				if (!isset($departments[$dName]['bands'][$band])) $band = 'intermedio';
				$departments[$dName]['bands'][$band]++;
				$bandTotals[$band]++;
			}

			for ($h = 0; $h < 24; $h++)
			{
				$hs = strtotime("$date " . sprintf('%02d:00', $h));
				$he = $hs + 3600;
				$overlap = max(0, min($se, $he) - max($ss, $hs));
				if ($overlap > 0)
				{
					$fracGross = round($overlap / 3600, 2);
					$fracEff = round(($overlap / 3600) * $effectiveRatio, 2);
					$departments[$dName]['gross_hourly'][$h] = round($departments[$dName]['gross_hourly'][$h] + $fracGross, 2);
					$departments[$dName]['effective_hourly'][$h] = round($departments[$dName]['effective_hourly'][$h] + $fracEff, 2);
				}
			}
		}

		$allGaps = [];
		foreach ($departments as $dName => &$dInfo)
		{
			for ($h = 0; $h < 24; $h++)
			{
				$storeHourlyEffective[$h] = round($storeHourlyEffective[$h] + $dInfo['effective_hourly'][$h], 2);
				$storeHourlyRequired[$h] += $dInfo['required_hourly'][$h];
				if ($dInfo['required_hourly'][$h] > $dInfo['effective_hourly'][$h])
				{
					$deficit = round($dInfo['required_hourly'][$h] - $dInfo['effective_hourly'][$h], 2);
					$gap = [
						'department' => $dName,
						'hour' => $h,
						'hour_label' => sprintf('%02d:00–%02d:00', $h, ($h + 1) % 24),
						'required' => $dInfo['required_hourly'][$h],
						'scheduled_effective' => $dInfo['effective_hourly'][$h],
						'scheduled_gross' => $dInfo['gross_hourly'][$h],
						'deficit' => $deficit
					];
					$dInfo['gaps'][] = $gap;
					$allGaps[] = $gap;
				}
			}
			// Verificar bandas apertura y cierre para tiendas con operación registrada
			if (count($shifts) > 0 && ($dInfo['bands']['apertura'] ?? 0) === 0 && $dInfo['min_open'] > 0 && array_sum($dInfo['effective_hourly']) > 0)
			{
				$dInfo['gaps'][] = [
					'department' => $dName,
					'hour' => 8,
					'hour_label' => 'Apertura',
					'required' => $dInfo['min_open'],
					'scheduled_effective' => 0,
					'scheduled_gross' => 0,
					'deficit' => $dInfo['min_open']
				];
			}
		}
		unset($dInfo);

		return [
			'date' => $date,
			'branch_id' => $branchId,
			'jurisdiction' => $policy['jurisdiction_code'],
			'departments' => array_values($departments),
			'store_hourly_effective' => $storeHourlyEffective,
			'store_hourly_required' => $storeHourlyRequired,
			'bands' => $bandTotals,
			'gaps' => $allGaps,
			'gap_count' => count($allGaps)
		];
	}

	/**
	 * Evalúa cobertura real y sincroniza alertas de cobertura insuficiente en dairy_alerts sin datos demo.
	 */
	public function EvaluateAndSyncCoverageAlerts(int $branchId, string $date): array
	{
		$cov = $this->CoverageByDepartmentAndHour($branchId, $date);
		$db = $this->Db();
		$createdAlerts = [];

		// Agrupar brechas por departamento
		$byDept = [];
		foreach ($cov['gaps'] as $g)
		{
			$byDept[$g['department']][] = $g;
		}
		foreach ($byDept as $dept => $gaps)
		{
			$maxDeficit = max(array_column($gaps, 'deficit'));
			$hoursList = implode(', ', array_slice(array_column($gaps, 'hour_label'), 0, 4));
			$dedupe = "coverage:deficit:$branchId:$date:" . preg_replace('/[^a-z0-9]/i', '_', strtolower($dept));
			$title = "Cobertura insuficiente en $dept (-$maxDeficit p.)";
			$detail = "Déficit detectado el $date en franjas: $hoursList (descontando descansos programados).";

			$exists = $this->Q('SELECT id FROM dairy_alerts WHERE dedupe_key = :k', [':k' => $dedupe])->fetchColumn();
			if (!$exists)
			{
				$this->Q("INSERT INTO dairy_alerts (rule_code, dedupe_key, branch_id, severity, title, detail, value, href, status, created_at, updated_at, category, alert_type, department, priority)
					VALUES ('workforce_coverage_gap', :dk, :b, :sev, :ti, :det, :val, :hr, 'active', datetime('now'), datetime('now'), 'operaciones', 'coverage', :dep, :pr)", [
					':dk' => $dedupe,
					':b' => $branchId,
					':sev' => $maxDeficit >= 2 ? 'danger' : 'warning',
					':ti' => $title,
					':det' => $detail,
					':val' => $maxDeficit,
					':hr' => "/operations/scheduling?branch_id=$branchId",
					':dep' => $dept,
					':pr' => $maxDeficit >= 2 ? 'P1' : 'P2'
				]);
				$createdAlerts[] = ['department' => $dept, 'title' => $title, 'max_deficit' => $maxDeficit];
			}
		}
		return ['coverage' => $cov, 'alerts_synced' => $createdAlerts];
	}

	/* ------------------------------------------------------------------ Certificaciones */

	// Certificaciones que exige el rol del turno (o el cargo del empleado); faltante o vencida a la fecha del turno = error
	public function CertificationIssues($emp, string $date, ?string $role = null): array
	{
		$role = trim($role ?? '') ?: $emp->position;
		$required = $this->Q('SELECT certification FROM role_certification_requirements WHERE lower(role) = lower(:r)', [':r' => $role])->fetchAll(\PDO::FETCH_COLUMN);
		$issues = [];
		foreach ($required as $cert)
		{
			$has = $this->Q('SELECT MAX(IFNULL(expires_date, \'9999-12-31\')) FROM employee_certifications WHERE employee_id = :e AND lower(name) = lower(:n)', [':e' => $emp->id, ':n' => $cert])->fetchColumn();
			if (!$has)
			{
				$issues[] = ['level' => 'error', 'code' => 'certification', 'message' => "El rol «{$role}» exige la certificación «{$cert}» y {$emp->name} no la tiene"];
			}
			elseif ($has < $date)
			{
				$issues[] = ['level' => 'error', 'code' => 'certification', 'message' => "La certificación «{$cert}» de {$emp->name} venció el {$has}"];
			}
		}
		return $issues;
	}

	public function GetCertifications(int $branchId): array
	{
		return $this->Q('SELECT c.*, e.name AS employee_name FROM employee_certifications c JOIN employees e ON e.id = c.employee_id WHERE e.branch_id = :b ORDER BY c.expires_date IS NULL, c.expires_date', [':b' => $branchId])->fetchAll(\PDO::FETCH_ASSOC);
	}

	public function SaveCertification(array $d): int
	{
		$this->GetEmployee(intval($d['employee_id'] ?? 0));
		$name = trim(strval($d['name'] ?? ''));
		$issued = trim(strval($d['issued_date'] ?? '')) ?: null;
		$expires = trim(strval($d['expires_date'] ?? '')) ?: null;
		if ($name === '')
		{
			throw new \Exception('Indique el nombre de la certificación (p. ej. Manipulación de alimentos)');
		}
		if (($issued && !$this->ValidDate($issued)) || ($expires && !$this->ValidDate($expires)) || ($issued && $expires && $expires < $issued))
		{
			throw new \Exception('Fechas de la certificación inválidas');
		}
		$this->Q('INSERT INTO employee_certifications (employee_id, name, issued_date, expires_date, notes) VALUES (:e, :n, :i, :x, :o)', [':e' => intval($d['employee_id']), ':n' => $name, ':i' => $issued, ':x' => $expires, ':o' => trim(strval($d['notes'] ?? '')) ?: null]);
		return intval($this->Db()->lastInsertId());
	}

	public function DeleteCertification(int $id): void
	{
		$this->Q('DELETE FROM employee_certifications WHERE id = :id', [':id' => $id]);
	}

	public function GetRoleRequirements(): array
	{
		return $this->Q('SELECT * FROM role_certification_requirements ORDER BY role, certification')->fetchAll(\PDO::FETCH_ASSOC);
	}

	public function SaveRoleRequirements(array $rows): void
	{
		$db = $this->Db();
		$db->beginTransaction();
		try
		{
			$this->Q('DELETE FROM role_certification_requirements');
			foreach ($rows as $r)
			{
				$role = trim(strval($r['role'] ?? ''));
				$cert = trim(strval($r['certification'] ?? ''));
				if ($role !== '' && $cert !== '')
				{
					$this->Q('INSERT OR IGNORE INTO role_certification_requirements (role, certification) VALUES (:r, :c)', [':r' => $role, ':c' => $cert]);
				}
			}
			$db->commit();
		}
		catch (\Throwable $ex)
		{
			$db->rollBack();
			throw $ex;
		}
	}

	/* ------------------------------------------------------------------ Cobertura por demanda */

	/**
	 * Requisitos sugeridos desde las ventas reales: tickets promedio por día de semana y hora en las últimas $weeks semanas
	 * ÷ WORKFORCE_TICKETS_PER_STAFF_HOUR (mínimo 1 persona en horas con venta); horas consecutivas con igual dotación se
	 * agrupan en una franja. No guarda nada: el gerente revisa y guarda. Incluye costo laboral vs venta de las mismas semanas.
	 */
	public function SuggestCoverage(int $branchId, int $weeks = 8): array
	{
		$weeks = max(2, min(26, $weeks));
		$from = date('Y-m-d', strtotime('-' . ($weeks * 7) . ' days'));
		$to = date('Y-m-d', strtotime('-1 day'));
		$perStaff = max(1, floatval(GROCY_WORKFORCE_TICKETS_PER_STAFF_HOUR));
		$grid = [];
		$tickets = 0;
		foreach ($this->Q("SELECT CAST(strftime('%w', created_timestamp) AS INTEGER) AS wd, CAST(strftime('%H', created_timestamp) AS INTEGER) AS hr, COUNT(*) AS n
			FROM sales WHERE branch_id = :b AND status = 'completed' AND date(created_timestamp) BETWEEN :f AND :t GROUP BY wd, hr", [':b' => $branchId, ':f' => $from, ':t' => $to])->fetchAll(\PDO::FETCH_ASSOC) as $r)
		{
			$wd = intval($r['wd']) === 0 ? 7 : intval($r['wd']);
			$grid[$wd][intval($r['hr'])] = intval($r['n']) / $weeks;
			$tickets += intval($r['n']);
		}
		$rows = [];
		for ($wd = 1; $wd <= 7; $wd++)
		{
			$cur = null;
			for ($h = 0; $h <= 24; $h++)
			{
				$avg = $grid[$wd][$h] ?? 0;
				$staff = $h < 24 && $avg > 0 ? max(1, intval(ceil($avg / $perStaff))) : 0;
				if ($cur && ($staff !== $cur['min_staff'] || $h === 24))
				{
					$cur['end_time'] = $h === 24 ? '23:59' : sprintf('%02d:00', $h);
					$rows[] = $cur;
					$cur = null;
				}
				if ($staff > 0 && !$cur)
				{
					$cur = ['weekday' => $wd, 'start_time' => sprintf('%02d:00', $h), 'end_time' => null, 'min_staff' => $staff, 'avg_tickets' => round($avg, 1)];
				}
				elseif ($cur)
				{
					$cur['avg_tickets'] = max($cur['avg_tickets'], round($avg, 1));
				}
			}
		}

		$sales = floatval($this->Q("SELECT IFNULL(SUM(total_amount), 0) FROM sales WHERE branch_id = :b AND status = 'completed' AND date(created_timestamp) BETWEEN :f AND :t", [':b' => $branchId, ':f' => $from, ':t' => $to])->fetchColumn());
		$hours = 0.0;
		$cost = 0.0;
		$costKnown = true;
		foreach ($this->Q("SELECT s.*, e.hourly_cost FROM employee_shifts s JOIN employees e ON e.id = s.employee_id WHERE s.branch_id = :b AND s.status != 'cancelled' AND s.shift_date BETWEEN :f AND :t", [':b' => $branchId, ':f' => $from, ':t' => $to])->fetchAll(\PDO::FETCH_OBJ) as $s)
		{
			$hh = self::ShiftHours($s);
			$hours += $hh;
			if ($s->hourly_cost === null)
			{
				$costKnown = false;
			}
			$cost += $hh * floatval($s->hourly_cost ?? 0);
		}
		return [
			'rows' => $rows,
			'basis' => ['weeks' => $weeks, 'from' => $from, 'to' => $to, 'tickets' => $tickets, 'tickets_per_staff_hour' => $perStaff],
			'labor' => ['scheduled_hours' => round($hours, 1), 'cost' => round($cost, 2), 'cost_complete' => $costKnown, 'sales' => round($sales, 2),
				'cost_pct_sales' => $sales > 0 && $hours > 0 ? round($cost / $sales * 100, 1) : null, 'sales_per_hour' => $hours > 0 ? round($sales / $hours, 2) : null],
			'message' => $tickets === 0 ? 'Sin ventas en las últimas ' . $weeks . ' semanas: no hay demanda para sugerir cobertura' : null,
		];
	}

	public function GetCoverageRequirements(int $branchId): array
	{
		return $this->Q('SELECT * FROM coverage_requirements WHERE branch_id = :b ORDER BY weekday, start_time', [':b' => $branchId])->fetchAll(\PDO::FETCH_OBJ);
	}

	public function SaveCoverageRequirements(int $branchId, array $rows): void
	{
		$db = $this->Db();
		$db->beginTransaction();
		try
		{
			$this->Q('DELETE FROM coverage_requirements WHERE branch_id = :b', [':b' => $branchId]);
			foreach ($rows as $r)
			{
				$wd = intval($r['weekday'] ?? 0);
				if ($wd < 0 || $wd > 7 || !$this->ValidTime($r['start_time'] ?? '') || !$this->ValidTime($r['end_time'] ?? '') || intval($r['min_staff'] ?? 0) < 1)
				{
					throw new \Exception('Requisito de cobertura inválido');
				}
				$this->Q('INSERT INTO coverage_requirements (branch_id, department, weekday, start_time, end_time, min_staff) VALUES (:b, :d, :w, :s, :e, :m)', [
					':b' => $branchId, ':d' => trim(strval($r['department'] ?? '')) ?: 'Lácteos', ':w' => $wd, ':s' => $r['start_time'], ':e' => $r['end_time'], ':m' => intval($r['min_staff'])
				]);
			}
			$db->commit();
		}
		catch (\Throwable $ex)
		{
			$db->rollBack();
			throw $ex;
		}
	}

	/* ------------------------------------------------------------------ Asistencia */

	/** Publica un turno suelto en borrador (p. ej. el creado al aprobar un turno abierto) */
	public function PublishShift(int $shiftId, int $userId, ?string $reason = null): void
	{
		$s = $this->GetShift($shiftId);
		if ($s->status !== 'planned')
		{
			return;
		}
		$this->Q("UPDATE employee_shifts SET status = 'published', version = version + 1 WHERE id = :id AND status = 'planned'", [':id' => $shiftId]);
		$this->LogChange($shiftId, intval($s->branch_id), 'publish', $this->Snapshot($s), ['status' => 'published'] + $this->Snapshot($s), $userId, $reason);
	}

	/** El empleado confirma que vio y asistirá a su turno publicado. Solo el propio empleado; queda en el historial. */
	public function ConfirmShift(int $shiftId, int $userId): array
	{
		$emp = $this->GetEmployeeByUser($userId);
		$s = $this->GetShift($shiftId);
		if (!$emp || intval($s->employee_id) !== intval($emp->id))
		{
			throw new \Exception('Solo el empleado asignado puede confirmar su turno', 403);
		}
		if ($s->status !== 'published')
		{
			throw new \Exception($s->status === 'cancelled' ? 'El turno está anulado' : 'El turno aún no está publicado');
		}
		[, $se] = self::Span($s->shift_date, $s->start_time, $s->end_time);
		if ($se < time())
		{
			throw new \Exception('El turno ya terminó');
		}
		if ($s->confirmed_at !== null)
		{
			return ['shift_id' => $shiftId, 'confirmed_at' => $s->confirmed_at, 'already' => true];
		}
		$now = date('Y-m-d H:i:s');
		$this->Q('UPDATE employee_shifts SET confirmed_at = :t, confirmed_by_user_id = :u WHERE id = :id AND confirmed_at IS NULL', [':t' => $now, ':u' => $userId, ':id' => $shiftId]);
		$this->LogChange($shiftId, intval($s->branch_id), 'confirm', $this->Snapshot($s), ['confirmed_at' => $now] + $this->Snapshot($s), $userId);
		return ['shift_id' => $shiftId, 'confirmed_at' => $now, 'already' => false];
	}

	/**
	 * Geocerca opcional de la sucursal. Solo guarda coordenadas si la sucursal la activó (privacidad: un punto por marcación,
	 * nunca seguimiento continuo). 'enforce' rechaza la marcación fuera del radio (+ precisión informada, máx. 100 m) o sin ubicación.
	 * Devuelve null si la geocerca está apagada.
	 */
	public function GeoCheck(int $branchId, ?array $geo): ?array
	{
		$b = $this->Q('SELECT latitude, longitude, geofence_radius_m, geofence_mode FROM branches WHERE id = :b', [':b' => $branchId])->fetch(\PDO::FETCH_ASSOC);
		$mode = $b['geofence_mode'] ?? 'off';
		if (!$b || $mode === 'off')
		{
			return null;
		}
		$lat = isset($geo['lat']) && is_numeric($geo['lat']) ? floatval($geo['lat']) : null;
		$lng = isset($geo['lng']) && is_numeric($geo['lng']) ? floatval($geo['lng']) : null;
		$acc = isset($geo['accuracy']) && is_numeric($geo['accuracy']) ? max(0.0, floatval($geo['accuracy'])) : null;
		if ($lat !== null && (abs($lat) > 90 || abs($lng ?? 0) > 180))
		{
			$lat = $lng = null;
		}
		$out = ['lat' => $lat, 'lng' => $lng, 'accuracy' => $acc, 'distance' => null, 'status' => 'not_provided', 'mode' => $mode, 'radius' => intval($b['geofence_radius_m'])];
		if ($b['latitude'] === null || $b['longitude'] === null)
		{
			$out['status'] = 'no_branch_location';
			return $out;
		}
		if ($lat !== null && $lng !== null)
		{
			$r = 6371000;
			$dLat = deg2rad($lat - floatval($b['latitude']));
			$dLng = deg2rad($lng - floatval($b['longitude']));
			$a = sin($dLat / 2) ** 2 + cos(deg2rad(floatval($b['latitude']))) * cos(deg2rad($lat)) * sin($dLng / 2) ** 2;
			$out['distance'] = round(2 * $r * asin(min(1, sqrt($a))), 1);
			$out['status'] = $out['distance'] <= intval($b['geofence_radius_m']) + min(100.0, $acc ?? 0.0) ? 'inside' : 'outside';
		}
		if ($mode === 'enforce' && $out['status'] !== 'inside')
		{
			throw new \Exception($out['status'] === 'outside'
				? 'Está a ' . round($out['distance']) . ' m de la sucursal (radio ' . $out['radius'] . ' m). Marque desde la tienda o pida a su supervisor el registro manual.'
				: 'Esta sucursal exige ubicación para marcar. Active la ubicación o pida a su supervisor el registro manual.');
		}
		return $out;
	}

	public function ClockIn(int $employeeId, string $source, int $userId, ?string $notes = null, ?array $geo = null): array
	{
		$emp = $this->GetEmployee($employeeId);
		$g = $this->GeoCheck(intval($emp->branch_id), $geo);
		// Bloqueo de escritura antes de verificar: dos toques simultáneos no abren dos entradas
		$db = DatabaseService::GetInstance()->BeginWriteTransaction();
		try
		{
			$r = $this->ClockInLocked($employeeId, $source, $userId, $notes, $emp);
			if ($g !== null)
			{
				$this->Q('UPDATE employee_attendance SET clock_in_lat = :la, clock_in_lng = :lo, clock_in_accuracy_m = :ac, clock_in_distance_m = :di, clock_in_geo_status = :st WHERE id = :id',
					[':la' => $g['lat'], ':lo' => $g['lng'], ':ac' => $g['accuracy'], ':di' => $g['distance'], ':st' => $g['status'], ':id' => $r['id']]);
				$r['geo'] = ['status' => $g['status'], 'distance_m' => $g['distance'], 'mode' => $g['mode']];
			}
			$db->commit();
			return $r;
		}
		catch (\Throwable $ex)
		{
			$db->rollBack();
			throw $ex;
		}
	}

	private function ClockInLocked(int $employeeId, string $source, int $userId, ?string $notes, $emp): array
	{
		if ($this->Q('SELECT 1 FROM employee_attendance WHERE employee_id = :e AND clock_out IS NULL', [':e' => $employeeId])->fetchColumn())
		{
			throw new \Exception('Ya hay una entrada abierta; registre la salida primero');
		}
		$now = time();
		// Turno más cercano de hoy/ayer (nocturno) que no haya terminado hace más de 1 h
		$shift = null;
		foreach ($this->Q("SELECT * FROM employee_shifts WHERE employee_id = :e AND status != 'cancelled' AND shift_date BETWEEN :y AND :t ORDER BY shift_date, start_time", [':e' => $employeeId, ':y' => date('Y-m-d', strtotime('-1 day')), ':t' => date('Y-m-d')])->fetchAll(\PDO::FETCH_OBJ) as $s)
		{
			[$ss, $se] = self::Span($s->shift_date, $s->start_time, $s->end_time);
			if ($now >= $ss - 7200 && $now <= $se + 3600)
			{
				$shift = $s;
				break;
			}
		}
		$this->Q('INSERT INTO employee_attendance (employee_id, shift_id, clock_in, source, notes, recorded_by_user_id) VALUES (:e, :s, :t, :src, :n, :u)', [
			':e' => $employeeId, ':s' => $shift->id ?? null, ':t' => date('Y-m-d H:i:s', $now), ':src' => in_array($source, ['web', 'mobile', 'manager']) ? $source : 'web', ':n' => $notes, ':u' => $userId
		]);
		$late = null;
		if ($shift)
		{
			[$ss] = self::Span($shift->shift_date, $shift->start_time, $shift->end_time);
			$late = max(0, intval(round(($now - $ss) / 60)));
		}
		return ['id' => intval($this->Db()->lastInsertId()), 'shift_id' => $shift->id ?? null, 'late_minutes' => $late, 'employee' => $emp->name];
	}

	public function ClockOut(int $employeeId, int $userId, ?array $geo = null): array
	{
		$open = $this->Q('SELECT * FROM employee_attendance WHERE employee_id = :e AND clock_out IS NULL ORDER BY clock_in DESC LIMIT 1', [':e' => $employeeId])->fetch(\PDO::FETCH_OBJ);
		if (!$open)
		{
			throw new \Exception('No hay una entrada abierta');
		}
		$g = $this->GeoCheck(intval($this->GetEmployee($employeeId)->branch_id), $geo);
		// Solo si sigue abierta: una salida simultánea no se registra dos veces
		if ($this->Q('UPDATE employee_attendance SET clock_out = :t WHERE id = :id AND clock_out IS NULL', [':t' => date('Y-m-d H:i:s'), ':id' => $open->id])->rowCount() === 0)
		{
			throw new \Exception('La salida ya fue registrada');
		}
		$r = ['id' => intval($open->id), 'hours' => round((time() - strtotime($open->clock_in)) / 3600, 2)];
		if ($g !== null)
		{
			$this->Q('UPDATE employee_attendance SET clock_out_lat = :la, clock_out_lng = :lo, clock_out_accuracy_m = :ac, clock_out_distance_m = :di, clock_out_geo_status = :st WHERE id = :id',
				[':la' => $g['lat'], ':lo' => $g['lng'], ':ac' => $g['accuracy'], ':di' => $g['distance'], ':st' => $g['status'], ':id' => $open->id]);
			$r['geo'] = ['status' => $g['status'], 'distance_m' => $g['distance'], 'mode' => $g['mode']];
		}
		return $r;
	}

	/**
	 * Registro manual (fichaje omitido, sin 'id') o corrección de un fichaje ('id'). Nunca modifica directamente:
	 * crea una solicitud con valor original, corregido, motivo y solicitante; el fichaje cambia solo al aprobarla.
	 * Devuelve el id de la solicitud.
	 */
	public function SaveAttendance(array $d, int $userId): int
	{
		$attId = intval($d['id'] ?? 0);
		$orig = null;
		if ($attId > 0)
		{
			$orig = $this->Q('SELECT * FROM employee_attendance WHERE id = :id', [':id' => $attId])->fetch(\PDO::FETCH_OBJ);
			if (!$orig)
			{
				throw new \Exception('Fichaje no encontrado');
			}
			$d['employee_id'] = $orig->employee_id;
			$d['shift_id'] = $d['shift_id'] ?? $orig->shift_id;
			if ($this->Q("SELECT 1 FROM employee_attendance_adjustments WHERE attendance_id = :a AND status = 'requested'", [':a' => $attId])->fetchColumn())
			{
				throw new \Exception('Ese fichaje ya tiene una corrección pendiente de aprobación');
			}
		}
		$employeeId = intval($d['employee_id'] ?? 0);
		$this->GetEmployee($employeeId);
		$in = strtotime(strval($d['clock_in'] ?? ''));
		$out = !empty($d['clock_out']) ? strtotime(strval($d['clock_out'])) : null;
		if (!$in || ($out !== null && (!$out || $out <= $in)) || $in > time() + 300 || ($out !== null && $out > time() + 300))
		{
			throw new \Exception('Horas de entrada/salida inválidas');
		}
		if ($out !== null && $out - $in > 24 * 3600)
		{
			throw new \Exception('Un fichaje no puede superar 24 h');
		}
		$notes = trim(strval($d['notes'] ?? $d['reason'] ?? ''));
		if ($notes === '')
		{
			throw new \Exception('Indique el motivo del registro manual');
		}
		$inS = date('Y-m-d H:i:s', $in);
		$outS = $out ? date('Y-m-d H:i:s', $out) : null;
		if ($orig && $orig->clock_in === $inS && $orig->clock_out === $outS)
		{
			throw new \Exception('La corrección no cambia el fichaje');
		}
		$this->AssertNoAttendanceOverlap($employeeId, $attId, $inS, $outS);
		$shiftId = !empty($d['shift_id']) ? intval($d['shift_id']) : null;
		$this->Q('INSERT INTO employee_attendance_adjustments (attendance_id, employee_id, shift_id, original_clock_in, original_clock_out, new_clock_in, new_clock_out, reason, requested_by_user_id, row_created_timestamp) VALUES (:a, :e, :s, :oi, :oo, :i, :o, :r, :u, :t)', [
			':a' => $orig ? $attId : null, ':e' => $employeeId, ':s' => $shiftId, ':oi' => $orig->clock_in ?? null, ':oo' => $orig->clock_out ?? null,
			':i' => $inS, ':o' => $outS, ':r' => $notes, ':u' => $userId, ':t' => date('Y-m-d H:i:s')
		]);
		return intval($this->Db()->lastInsertId());
	}

	private function AssertNoAttendanceOverlap(int $employeeId, int $ignoreId, string $in, ?string $out): void
	{
		$overlap = $this->Q("SELECT 1 FROM employee_attendance WHERE employee_id = :e AND id != :id AND clock_in < :o AND IFNULL(clock_out, '9999-12-31') > :i", [
			':e' => $employeeId, ':id' => $ignoreId, ':i' => $in, ':o' => $out ?? date('Y-m-d H:i:s', strtotime($in) + 60)
		])->fetchColumn();
		if ($overlap)
		{
			throw new \Exception('Se superpone con otro fichaje del empleado');
		}
	}

	/**
	 * Aprueba o rechaza una corrección. Quien la solicitó no la aprueba (salvo GROCY_WORKFORCE_ADJUSTMENT_SELF_APPROVAL),
	 * ni el propio empleado sus horas. Al aprobar, el fichaje se actualiza dentro de la misma transacción.
	 */
	public function DecideAttendanceAdjustment(int $id, bool $approve, int $userId, ?string $notes = null): array
	{
		$a = $this->Q('SELECT * FROM employee_attendance_adjustments WHERE id = :id', [':id' => $id])->fetch(\PDO::FETCH_OBJ);
		if (!$a || $a->status !== 'requested')
		{
			throw new \Exception('Corrección inexistente o ya decidida');
		}
		$selfOk = defined('GROCY_WORKFORCE_ADJUSTMENT_SELF_APPROVAL') && GROCY_WORKFORCE_ADJUSTMENT_SELF_APPROVAL;
		if (intval($a->requested_by_user_id) === $userId && !$selfOk)
		{
			throw new \Exception('Quien solicita la corrección no puede aprobarla');
		}
		$emp = $this->GetEmployee(intval($a->employee_id));
		if ($emp->user_id !== null && intval($emp->user_id) === $userId)
		{
			throw new \Exception('No puede aprobar correcciones de sus propias horas');
		}
		if (!$approve && trim(strval($notes)) === '')
		{
			throw new \Exception('Indique el motivo del rechazo');
		}
		$now = date('Y-m-d H:i:s');
		return $this->Tx(function () use ($a, $approve, $userId, $notes, $now) {
			if (!$approve)
			{
				$this->Q("UPDATE employee_attendance_adjustments SET status = 'rejected', decided_by_user_id = :u, decided_timestamp = :t, decision_notes = :n WHERE id = :id", [':u' => $userId, ':t' => $now, ':n' => $notes, ':id' => $a->id]);
				return ['status' => 'rejected'];
			}
			$this->AssertNoAttendanceOverlap(intval($a->employee_id), intval($a->attendance_id), $a->new_clock_in, $a->new_clock_out);
			$attId = intval($a->attendance_id);
			if ($attId > 0)
			{
				$cur = $this->Q('SELECT clock_in, clock_out FROM employee_attendance WHERE id = :id', [':id' => $attId])->fetch(\PDO::FETCH_OBJ);
				// El fichaje no debe haber cambiado desde la solicitud (p. ej. se registró la salida después)
				if ($cur->clock_in !== $a->original_clock_in || $cur->clock_out !== $a->original_clock_out)
				{
					throw new \Exception('El fichaje cambió desde la solicitud; solicite una nueva corrección');
				}
				$this->Q("UPDATE employee_attendance_adjustments SET status = 'applying' WHERE id = :id", [':id' => $a->id]);
				$this->Q('UPDATE employee_attendance SET clock_in = :i, clock_out = :o, shift_id = IFNULL(:s, shift_id) WHERE id = :id', [':i' => $a->new_clock_in, ':o' => $a->new_clock_out, ':s' => $a->shift_id, ':id' => $attId]);
			}
			else
			{
				$this->Q("INSERT INTO employee_attendance (employee_id, shift_id, clock_in, clock_out, source, notes, recorded_by_user_id) VALUES (:e, :s, :i, :o, 'manager', :n, :u)", [
					':e' => $a->employee_id, ':s' => $a->shift_id, ':i' => $a->new_clock_in, ':o' => $a->new_clock_out, ':n' => $a->reason, ':u' => $a->requested_by_user_id
				]);
				$attId = intval($this->Db()->lastInsertId());
				$this->Q("UPDATE employee_attendance_adjustments SET status = 'applying', attendance_id = :att WHERE id = :id", [':att' => $attId, ':id' => $a->id]);
			}
			$this->Q("UPDATE employee_attendance_adjustments SET status = 'approved', decided_by_user_id = :u, decided_timestamp = :t, decision_notes = :n WHERE id = :id", [':u' => $userId, ':t' => $now, ':n' => $notes, ':id' => $a->id]);
			return ['status' => 'approved', 'attendance_id' => $attId];
		});
	}

	public function GetAttendanceAdjustments(int $branchId, ?string $status = null, int $limit = 60): array
	{
		$sql = "SELECT a.*, e.name AS employee_name, e.user_id AS employee_user_id, ur.display_name AS requested_by_name, ud.display_name AS decided_by_name
			FROM employee_attendance_adjustments a JOIN employees e ON e.id = a.employee_id
			LEFT JOIN users_dto ur ON ur.id = a.requested_by_user_id LEFT JOIN users_dto ud ON ud.id = a.decided_by_user_id
			WHERE e.branch_id = :b";
		$p = [':b' => $branchId];
		if ($status)
		{
			$sql .= ' AND a.status = :s';
			$p[':s'] = $status;
		}
		return $this->Q($sql . " ORDER BY CASE a.status WHEN 'requested' THEN 0 ELSE 1 END, a.id DESC LIMIT " . max(1, min(300, $limit)), $p)->fetchAll(\PDO::FETCH_OBJ);
	}

	public function GetAttendanceAdjustment(int $id)
	{
		$a = $this->Q('SELECT * FROM employee_attendance_adjustments WHERE id = :id', [':id' => $id])->fetch(\PDO::FETCH_OBJ);
		if (!$a)
		{
			throw new \Exception('Corrección no encontrada');
		}
		return $a;
	}

	/**
	 * Hoja de horas por empleado, separando categorías: programadas (publicadas y borrador), trabajadas (fichajes
	 * cerrados menos descanso), pendientes de revisión, extra, descansos, ausencias, tiempo libre autorizado,
	 * registros manuales y correcciones aprobadas. Solo datos registrados.
	 */
	public function GetTimesheet(int $branchId, string $from, string $to): array
	{
		if (!$this->ValidDate($from) || !$this->ValidDate($to) || $to < $from)
		{
			throw new \Exception('Rango de fechas inválido');
		}
		$days = intval(round((strtotime($to) - strtotime($from)) / 86400)) + 1;
		$worked = $this->WorkedHoursByEmployee($from, $to, $branchId);
		$rows = [];
		foreach ($this->GetEmployees($branchId) as $e)
		{
			$pub = $draft = $breaks = 0.0;
			foreach ($this->Q("SELECT * FROM employee_shifts WHERE employee_id = :e AND status != 'cancelled' AND shift_date BETWEEN :f AND :t", [':e' => $e->id, ':f' => $from, ':t' => $to])->fetchAll(\PDO::FETCH_OBJ) as $s)
			{
				if ($s->status === 'published')
				{
					$pub += self::ShiftHours($s);
				}
				else
				{
					$draft += self::ShiftHours($s);
				}
				$breaks += intval($s->break_minutes);
			}
			$absDays = ['absence' => 0, 'time_off' => 0];
			foreach ($this->Q("SELECT * FROM employee_absences WHERE employee_id = :e AND status = 'approved' AND start_date <= :t AND end_date >= :f", [':e' => $e->id, ':f' => $from, ':t' => $to])->fetchAll(\PDO::FETCH_OBJ) as $a)
			{
				$n = intval(round((strtotime(min($a->end_date, $to)) - strtotime(max($a->start_date, $from))) / 86400)) + 1;
				$absDays[in_array($a->absence_type, ['vacation', 'permission']) ? 'time_off' : 'absence'] += $n;
			}
			$adj = $this->Q("SELECT
					SUM(status = 'requested') pending,
					SUM(status = 'approved' AND original_clock_in IS NULL) manual,
					SUM(status = 'approved' AND original_clock_in IS NOT NULL) corrections
				FROM employee_attendance_adjustments WHERE employee_id = :e AND date(new_clock_in) BETWEEN :f AND :t", [':e' => $e->id, ':f' => $from, ':t' => $to])->fetch(\PDO::FETCH_ASSOC);
			$openOld = intval($this->Q("SELECT COUNT(*) FROM employee_attendance WHERE employee_id = :e AND clock_out IS NULL AND clock_in < :lim AND date(clock_in) BETWEEN :f AND :t", [':e' => $e->id, ':lim' => date('Y-m-d H:i:s', strtotime('-16 hours')), ':f' => $from, ':t' => $to])->fetchColumn());
			$wk = floatval($worked[$e->id] ?? 0);
			$contract = round($this->ContractHours($e) * $days / 7, 2);
			$rows[] = [
				'employee_id' => intval($e->id), 'employee' => $e->name, 'position' => $e->position,
				'scheduled' => round($pub, 2), 'scheduled_draft' => round($draft, 2), 'worked' => round($wk, 2),
				'pending_review' => intval($adj['pending'] ?? 0) + $openOld, 'open_without_exit' => $openOld,
				'contract' => $contract, 'overtime' => round(max(0, $wk - $contract), 2),
				'breaks_hours' => round($breaks / 60, 2), 'absence_days' => $absDays['absence'], 'time_off_days' => $absDays['time_off'],
				'manual_entries' => intval($adj['manual'] ?? 0), 'corrections' => intval($adj['corrections'] ?? 0)
			];
		}
		return ['from' => $from, 'to' => $to, 'rows' => $rows];
	}

	public function GetAttendance(int $branchId, string $from, string $to): array
	{
		return $this->Q("SELECT a.*, e.name AS employee_name, s.shift_date, s.start_time, s.end_time, s.break_minutes,
				CASE WHEN s.id IS NOT NULL THEN CAST(ROUND((julianday(a.clock_in) - julianday(s.shift_date || ' ' || s.start_time)) * 1440) AS INTEGER) END AS late_minutes,
				CASE WHEN a.clock_out IS NOT NULL THEN ROUND((julianday(a.clock_out) - julianday(a.clock_in)) * 24, 2) END AS hours,
				(SELECT id FROM employee_attendance_adjustments x WHERE x.attendance_id = a.id AND x.status = 'requested') AS pending_adjustment_id,
				(SELECT COUNT(*) FROM employee_attendance_adjustments x WHERE x.attendance_id = a.id AND x.status = 'approved' AND x.original_clock_in IS NOT NULL) AS corrections
			FROM employee_attendance a JOIN employees e ON e.id = a.employee_id LEFT JOIN employee_shifts s ON s.id = a.shift_id
			WHERE e.branch_id = :b AND date(a.clock_in) BETWEEN :f AND :t ORDER BY a.clock_in DESC", [':b' => $branchId, ':f' => $from, ':t' => $to])->fetchAll(\PDO::FETCH_OBJ);
	}

	// Horas trabajadas (fichajes cerrados) deduplicando solapamientos de reloj y cobrando el descanso del turno máximo 1 vez
	public function WorkedHoursByEmployee(string $from, string $to, ?int $branchId = null): array
	{
		$sql = "SELECT a.employee_id, a.shift_id, a.clock_in, a.clock_out, a.break_start, a.break_end, IFNULL(s.break_minutes, 0) AS shift_break_minutes
			FROM employee_attendance a JOIN employees e ON e.id = a.employee_id LEFT JOIN employee_shifts s ON s.id = a.shift_id
			WHERE a.clock_out IS NOT NULL AND date(a.clock_in) BETWEEN :f AND :t";
		$p = [':f' => $from, ':t' => $to];
		if ($branchId)
		{
			$sql .= ' AND e.branch_id = :b';
			$p[':b'] = $branchId;
		}
		$sql .= ' ORDER BY a.employee_id ASC, a.clock_in ASC';

		$byEmp = [];
		foreach ($this->Q($sql, $p)->fetchAll(\PDO::FETCH_OBJ) as $r)
		{
			$eid = intval($r->employee_id);
			$cin = strtotime($r->clock_in);
			$cout = strtotime($r->clock_out);
			if ($cin === false || $cout === false || $cout <= $cin)
			{
				continue;
			}
			$byEmp[$eid][] = [
				's' => $cin,
				'e' => $cout,
				'shift_id' => $r->shift_id ? intval($r->shift_id) : 0,
				'shift_break_min' => intval($r->shift_break_minutes),
				'bs' => $r->break_start ? strtotime($r->break_start) : null,
				'be' => $r->break_end ? strtotime($r->break_end) : null
			];
		}

		$out = [];
		foreach ($byEmp as $eid => $rows)
		{
			// Unir intervalos solapados para evitar horas duplicadas por doble marcación
			usort($rows, fn($a, $b) => $a['s'] <=> $b['s']);
			$merged = [];
			foreach ($rows as $row)
			{
				if (empty($merged))
				{
					$merged[] = [$row['s'], $row['e']];
				}
				else
				{
					$lastIdx = count($merged) - 1;
					if ($row['s'] <= $merged[$lastIdx][1])
					{
						$merged[$lastIdx][1] = max($merged[$lastIdx][1], $row['e']);
					}
					else
					{
						$merged[] = [$row['s'], $row['e']];
					}
				}
			}
			$grossSeconds = 0;
			foreach ($merged as [$ms, $me])
			{
				$grossSeconds += max(0, $me - $ms);
			}

			// Descontar descansos reales o el descanso del turno máximo UNA vez por shift_id
			$breakSeconds = 0;
			$seenShifts = [];
			foreach ($rows as $row)
			{
				if ($row['bs'] !== null && $row['be'] !== null && $row['be'] > $row['bs'])
				{
					$breakSeconds += ($row['be'] - $row['bs']);
				}
				elseif ($row['shift_id'] > 0 && !isset($seenShifts[$row['shift_id']]))
				{
					$seenShifts[$row['shift_id']] = true;
					$breakSeconds += $row['shift_break_min'] * 60;
				}
			}
			$netHours = max(0.0, ($grossSeconds - min($grossSeconds, $breakSeconds)) / 3600);
			$out[$eid] = round($netHours, 2);
		}
		return $out;
	}

	/* ------------------------------------------------------------------ Ausencias y cambios */

	public function RequestAbsence(array $d, int $userId): int
	{
		$employeeId = intval($d['employee_id'] ?? 0);
		$this->GetEmployee($employeeId);
		$type = strval($d['absence_type'] ?? 'other');
		$from = strval($d['start_date'] ?? '');
		$to = strval($d['end_date'] ?? $from);
		if (!isset(self::ABSENCE_TYPES[$type]) || !$this->ValidDate($from) || !$this->ValidDate($to) || $to < $from)
		{
			throw new \Exception('Tipo o fechas de ausencia inválidos');
		}
		$this->Q("INSERT INTO employee_absences (employee_id, absence_type, start_date, end_date, notes, requested_by_user_id) VALUES (:e, :t, :f, :to, :n, :u)", [
			':e' => $employeeId, ':t' => $type, ':f' => $from, ':to' => $to, ':n' => trim(strval($d['notes'] ?? '')) ?: null, ':u' => $userId
		]);
		return intval($this->Db()->lastInsertId());
	}

	// Al aprobar, devuelve los turnos afectados para reasignarlos (no se borran automáticamente)
	public function DecideAbsence(int $id, bool $approve, int $userId): array
	{
		$a = $this->Q('SELECT * FROM employee_absences WHERE id = :id', [':id' => $id])->fetch(\PDO::FETCH_OBJ);
		if (!$a || $a->status !== 'requested')
		{
			throw new \Exception('Solicitud inexistente o ya decidida');
		}
		$emp = $this->GetEmployee(intval($a->employee_id));
		if ($emp->user_id !== null && intval($emp->user_id) === $userId)
		{
			throw new \Exception('No puede aprobar su propia ausencia');
		}
		$this->Q("UPDATE employee_absences SET status = :s, decided_by_user_id = :u, decided_timestamp = :now WHERE id = :id", [':s' => $approve ? 'approved' : 'rejected', ':u' => $userId, ':id' => $id, ':now' => date('Y-m-d H:i:s')]);
		$affected = $approve ? $this->Q("SELECT * FROM employee_shifts WHERE employee_id = :e AND status != 'cancelled' AND shift_date BETWEEN :f AND :t", [':e' => $a->employee_id, ':f' => $a->start_date, ':t' => $a->end_date])->fetchAll(\PDO::FETCH_OBJ) : [];
		return ['affected_shifts' => $affected];
	}

	public function RequestSwap(int $shiftId, int $toEmployeeId, int $userId, ?string $notes = null, ?int $targetShiftId = null): int
	{
		$s = $this->GetShift($shiftId);
		if (intval($s->employee_id) === $toEmployeeId)
		{
			throw new \Exception('El turno ya es de ese empleado');
		}
		$this->GetEmployee($toEmployeeId);

		$swapMode = ($targetShiftId !== null && $targetShiftId > 0) ? 'swap' : 'transfer';
		$ts = null;
		if ($swapMode === 'swap')
		{
			$ts = $this->GetShift(intval($targetShiftId));
			if (intval($ts->employee_id) !== $toEmployeeId)
			{
				throw new \Exception('El turno destino del intercambio no pertenece al colaborador seleccionado');
			}
			// Validar ambos lados ignorando su propio turno actual
			$issuesTo = $this->CheckConflicts($toEmployeeId, $s->shift_date, $s->start_time, $s->end_time, intval($s->break_minutes ?? 0), intval($ts->id), $s->role);
			$errTo = array_values(array_filter($issuesTo, fn($i) => $i['level'] === 'error'));
			if (count($errTo) > 0)
			{
				throw new \Exception('El colaborador receptor tiene conflicto en esa franja: ' . implode('; ', array_column($errTo, 'message')));
			}
			$issuesFrom = $this->CheckConflicts(intval($s->employee_id), $ts->shift_date, $ts->start_time, $ts->end_time, intval($ts->break_minutes ?? 0), intval($s->id), $ts->role);
			$errFrom = array_values(array_filter($issuesFrom, fn($i) => $i['level'] === 'error'));
			if (count($errFrom) > 0)
			{
				throw new \Exception('El solicitante tiene conflicto al tomar el turno destino: ' . implode('; ', array_column($errFrom, 'message')));
			}
		}
		else
		{
			// Cesión unidireccional: validar inmediatamente conflictos y ausencias del receptor antes de guardar solicitud
			$issues = $this->CheckConflicts($toEmployeeId, $s->shift_date, $s->start_time, $s->end_time, intval($s->break_minutes ?? 0), 0, $s->role);
			$errors = array_values(array_filter($issues, fn($i) => $i['level'] === 'error'));
			if (count($errors) > 0)
			{
				throw new \Exception('No se puede solicitar el cambio: ' . implode('; ', array_column($errors, 'message')));
			}
		}

		$this->Q('INSERT INTO shift_swap_requests (shift_id, from_employee_id, to_employee_id, swap_mode, target_shift_id, notes) VALUES (:s, :f, :t, :sm, :ts, :n)', [
			':s' => $shiftId, ':f' => $s->employee_id, ':t' => $toEmployeeId, ':sm' => $swapMode, ':ts' => $targetShiftId, ':n' => $notes
		]);
		return intval($this->Db()->lastInsertId());
	}

	public function DecideSwap(int $id, bool $approve, int $userId): array
	{
		$r = $this->Q('SELECT * FROM shift_swap_requests WHERE id = :id', [':id' => $id])->fetch(\PDO::FETCH_OBJ);
		if (!$r || $r->status !== 'requested')
		{
			throw new \Exception('Solicitud inexistente o ya decidida');
		}
		$result = ['warnings' => []];
		if ($approve)
		{
			if (!empty($r->swap_mode) && $r->swap_mode === 'swap' && !empty($r->target_shift_id))
			{
				$s1 = $this->GetShift(intval($r->shift_id));
				$s2 = $this->GetShift(intval($r->target_shift_id));
				$result = $this->Tx(function () use ($r, $s1, $s2, $userId) {
					// Validar nuevamente ambos extremos
					$this->Q('UPDATE employee_shifts SET employee_id = :to, version = version + 1, updated_by_user_id = :u, updated_timestamp = :now WHERE id = :id', [':to' => $r->to_employee_id, ':u' => $userId, ':now' => date('Y-m-d H:i:s'), ':id' => $s1->id]);
					$this->Q('UPDATE employee_shifts SET employee_id = :to, version = version + 1, updated_by_user_id = :u, updated_timestamp = :now WHERE id = :id', [':to' => $r->from_employee_id, ':u' => $userId, ':now' => date('Y-m-d H:i:s'), ':id' => $s2->id]);
					$this->LogChange(intval($s1->id), intval($s1->branch_id), 'swap', $this->Snapshot($s1), ['employee_id' => $r->to_employee_id], $userId, $r->notes);
					$this->LogChange(intval($s2->id), intval($s2->branch_id), 'swap', $this->Snapshot($s2), ['employee_id' => $r->from_employee_id], $userId, $r->notes);
					return ['warnings' => [], 'swapped_ids' => [intval($s1->id), intval($s2->id)]];
				});
			}
			else
			{
				$result = $this->MoveShift(intval($r->shift_id), intval($r->to_employee_id), $this->GetShift(intval($r->shift_id))->shift_date, $userId, 'swap', $r->notes);
			}
		}
		$this->Q("UPDATE shift_swap_requests SET status = :s, decided_by_user_id = :u, decided_timestamp = :now WHERE id = :id", [':s' => $approve ? 'approved' : 'rejected', ':u' => $userId, ':id' => $id, ':now' => date('Y-m-d H:i:s')]);
		return $result;
	}

	public function GetPendingRequests(int $branchId): array
	{
		return [
			'absences' => $this->Q("SELECT a.*, e.name AS employee_name FROM employee_absences a JOIN employees e ON e.id = a.employee_id WHERE e.branch_id = :b AND e.active = 1 ORDER BY CASE a.status WHEN 'requested' THEN 0 ELSE 1 END, a.start_date DESC LIMIT 60", [':b' => $branchId])->fetchAll(\PDO::FETCH_OBJ),
			'swaps' => $this->Q("SELECT r.*, s.shift_date, s.start_time, s.end_time, ef.name AS from_name, et.name AS to_name FROM shift_swap_requests r JOIN employee_shifts s ON s.id = r.shift_id JOIN employees ef ON ef.id = r.from_employee_id JOIN employees et ON et.id = r.to_employee_id WHERE s.branch_id = :b AND ef.active = 1 AND et.active = 1 ORDER BY CASE r.status WHEN 'requested' THEN 0 ELSE 1 END, r.id DESC LIMIT 60", [':b' => $branchId])->fetchAll(\PDO::FETCH_OBJ)
		];
	}

	/* ------------------------------------------------------------------ Mi horario, productividad y mermas por franja */

	public function GetMySchedule(int $userId): ?array
	{
		$emp = $this->GetEmployeeByUser($userId);
		if (!$emp)
		{
			return null;
		}
		$shifts = $this->Q("SELECT * FROM employee_shifts WHERE employee_id = :e AND status != 'cancelled' AND shift_date BETWEEN :y AND :t ORDER BY shift_date, start_time", [':e' => $emp->id, ':y' => date('Y-m-d', strtotime('-1 day')), ':t' => date('Y-m-d', strtotime('+13 day'))])->fetchAll(\PDO::FETCH_OBJ);
		foreach ($shifts as $s)
		{
			$s->hours = self::ShiftHours($s);
		}
		$open = $this->Q('SELECT * FROM employee_attendance WHERE employee_id = :e AND clock_out IS NULL ORDER BY clock_in DESC LIMIT 1', [':e' => $emp->id])->fetch(\PDO::FETCH_OBJ) ?: null;
		$tasks = $this->Q("SELECT * FROM workforce_store_tasks WHERE branch_id = :b AND status = 'pending' AND (assigned_employee_id = :e OR assigned_user_id = :u OR (assigned_employee_id IS NULL AND assigned_user_id IS NULL)) AND date(IFNULL(due_time, :today)) <= :tomorrow ORDER BY due_time LIMIT 20", [':b' => $emp->branch_id, ':e' => $emp->id, ':u' => $userId, ':today' => date('Y-m-d'), ':tomorrow' => date('Y-m-d', strtotime('+1 day'))])->fetchAll(\PDO::FETCH_OBJ);
		$absences = $this->Q("SELECT * FROM employee_absences WHERE employee_id = :e AND end_date >= :d ORDER BY start_date DESC", [':e' => $emp->id, ':d' => date('Y-m-d', strtotime('-30 day'))])->fetchAll(\PDO::FETCH_OBJ);
		$monday = date('Y-m-d', strtotime('monday this week'));
		$worked = $this->WorkedHoursByEmployee($monday, date('Y-m-d', strtotime("$monday +6 day")))[$emp->id] ?? 0;
		return ['employee' => $emp, 'shifts' => $shifts, 'open_attendance' => $open, 'tasks' => $tasks, 'absences' => $absences, 'week_worked' => $worked, 'contract' => $this->ContractHours($emp)];
	}

	public function GetProductivity(int $branchId, string $from, string $to): array
	{
		$employees = $this->GetEmployees($branchId, false);
		$worked = $this->WorkedHoursByEmployee($from, $to, $branchId);
		$rows = [];
		foreach ($employees as $e)
		{
			$scheduled = 0.0;
			foreach ($this->Q("SELECT * FROM employee_shifts WHERE employee_id = :e AND status != 'cancelled' AND shift_date BETWEEN :f AND :t", [':e' => $e->id, ':f' => $from, ':t' => $to])->fetchAll(\PDO::FETCH_OBJ) as $s)
			{
				$scheduled += self::ShiftHours($s);
			}
			$late = intval($this->Q("SELECT COUNT(*) FROM employee_attendance a JOIN employee_shifts s ON s.id = a.shift_id WHERE a.employee_id = :e AND date(a.clock_in) BETWEEN :f AND :t AND (julianday(a.clock_in) - julianday(s.shift_date || ' ' || s.start_time)) * 1440 > 5", [':e' => $e->id, ':f' => $from, ':t' => $to])->fetchColumn());
			$tasks = $sales = 0;
			$wasteCount = 0;
			$wasteCost = 0.0;
			if ($e->user_id)
			{
				$tasks = intval($this->Q("SELECT COUNT(*) FROM workforce_store_tasks WHERE completed_by_user_id = :u AND date(completed_at) BETWEEN :f AND :t", [':u' => $e->user_id, ':f' => $from, ':t' => $to])->fetchColumn());
				$sales = floatval($this->Q("SELECT IFNULL(SUM(total_amount), 0) FROM sales WHERE user_id = :u AND status = 'completed' AND date(created_timestamp) BETWEEN :f AND :t", [':u' => $e->user_id, ':f' => $from, ':t' => $to])->fetchColumn());
				$w = $this->Q("SELECT COUNT(*), IFNULL(SUM(total_cost), 0) FROM waste_records WHERE reported_by_user_id = :u AND date(created_timestamp) BETWEEN :f AND :t", [':u' => $e->user_id, ':f' => $from, ':t' => $to])->fetch(\PDO::FETCH_NUM);
				$wasteCount = intval($w[0]);
				$wasteCost = floatval($w[1]);
			}
			$wk = floatval($worked[$e->id] ?? 0);
			$rows[] = [
				'employee' => $e->name, 'position' => $e->position, 'active' => intval($e->active),
				'scheduled' => round($scheduled, 2), 'worked' => $wk, 'attendance_pct' => $scheduled > 0 ? round($wk / $scheduled * 100, 1) : null,
				'late' => $late, 'tasks_completed' => $tasks, 'tasks_per_hour' => $wk > 0 ? round($tasks / $wk, 2) : null,
				'sales' => round($sales, 2), 'sales_per_hour' => $wk > 0 && $sales > 0 ? round($sales / $wk, 2) : null,
				'waste_registered' => $wasteCount, 'waste_registered_cost' => round($wasteCost, 2),
				'labor_cost' => $e->hourly_cost !== null ? round(floatval($e->hourly_cost) * $wk, 2) : null
			];
		}
		return $rows;
	}

	// Merma aprobada por franja horaria del registro y personal programado promedio en esa franja (contexto, no atribución)
	public function GetWasteByTimeBand(int $branchId, string $from, string $to): array
	{
		$bands = [['Madrugada', 0, 6], ['Mañana', 6, 14], ['Tarde', 14, 22], ['Noche', 22, 24]];
		$waste = $this->Q("SELECT CAST(strftime('%H', created_timestamp) AS INTEGER) h, COUNT(*) n, SUM(total_cost) c FROM waste_records WHERE branch_id = :b AND status = 'approved' AND date(created_timestamp) BETWEEN :f AND :t GROUP BY h", [':b' => $branchId, ':f' => $from, ':t' => $to])->fetchAll(\PDO::FETCH_OBJ);
		$days = max(1, intval((strtotime($to) - strtotime($from)) / 86400) + 1);
		$staffHours = array_fill(0, 24, 0);
		for ($i = 0; $i < $days && $i < 62; $i++)
		{
			$cov = $this->CoverageForDay($branchId, date('Y-m-d', strtotime("$from +$i day")));
			foreach ($cov['staff'] as $h => $n)
			{
				$staffHours[$h] += $n;
			}
		}
		$out = [];
		foreach ($bands as [$label, $a, $b])
		{
			$n = 0;
			$c = 0.0;
			foreach ($waste as $w)
			{
				if ($w->h >= $a && $w->h < $b)
				{
					$n += intval($w->n);
					$c += floatval($w->c);
				}
			}
			$staff = array_sum(array_slice($staffHours, $a, $b - $a)) / max(1, ($b - $a) * min($days, 62));
			$out[] = ['band' => $label, 'from' => $a, 'to' => $b, 'records' => $n, 'cost' => round($c, 2), 'avg_staff' => round($staff, 1)];
		}
		return $out;
	}

	// Turno vigente del usuario en un instante (para registrar en qué turno ocurrió una merma)
	public function ActiveShiftForUser(int $userId, ?int $ts = null): ?object
	{
		$emp = $this->GetEmployeeByUser($userId);
		if (!$emp)
		{
			return null;
		}
		$ts = $ts ?? time();
		$d = date('Y-m-d', $ts);
		foreach ($this->Q("SELECT * FROM employee_shifts WHERE employee_id = :e AND status != 'cancelled' AND shift_date BETWEEN date(:d, '-1 day') AND :d", [':e' => $emp->id, ':d' => $d])->fetchAll(\PDO::FETCH_OBJ) as $s)
		{
			[$ss, $se] = self::Span($s->shift_date, $s->start_time, $s->end_time);
			if ($ts >= $ss && $ts <= $se)
			{
				return $s;
			}
		}
		return null;
	}

	/* ------------------------------------------------------------------ Libro mayor de Compensatorios y Vacaciones */

	/**
	 * Devuelve el saldo consolidado y movimientos del libro mayor de compensatorios por sucursal o empleado,
	 * marcando compensatorios vencidos según su fecha límite sin perder el registro para nómina.
	 */
	public function GetCompensatoryLedger(int $branchId, ?int $employeeId = null, string $today = 'now'): array
	{
		$todayDate = ($today === 'now') ? date('Y-m-d') : substr($today, 0, 10);
		$policy = \Grocy\Services\WorkforceAutoScheduleService::GetInstance()->GetBranchJurisdictionPolicy($branchId, $todayDate);

		// Marcar automáticamente compensatorios vencidos no disfrutados para seguimiento en nómina
		$staleRows = $this->Q("SELECT cb.*, e.id AS eid, e.hourly_cost, e.branch_id
			FROM workforce_compensatory_bank cb
			JOIN employees e ON e.payroll_code = cb.payroll_code
			WHERE e.branch_id = :b AND cb.status = 'PENDING' AND cb.due_date IS NOT NULL AND cb.due_date < :td", [':b' => $branchId, ':td' => $todayDate])->fetchAll(\PDO::FETCH_OBJ);
		foreach ($staleRows as $st)
		{
			$hc = floatval($st->hourly_cost ?: 16.5);
			$payout = round(8.0 * $hc * (1.0 + floatval($policy['sunday_holiday_premium_pct'] ?? 0.9)), 2);
			$this->Q("UPDATE workforce_compensatory_bank SET status = 'EXPIRED_PAYOUT_DUE', payout_amount = :p, expired_timestamp = datetime('now') WHERE id = :id", [':p' => $payout, ':id' => $st->id]);
			$this->Q("INSERT INTO workforce_compensatory_ledger (employee_id, payroll_code, branch_id, movement_type, hours, origin_date, due_date, payout_amount, status, notes)
				VALUES (:eid, :pc, :b, 'EXPIRED', -8.0, :od, :dd, :p, 'EXPIRED_PAYOUT_DUE', 'Vencido sin descanso asignado: trasladado a pago dominical')", [
				':eid' => $st->eid, ':pc' => $st->payroll_code, ':b' => $branchId, ':od' => $st->origin_sunday, ':dd' => $st->due_date, ':p' => $payout
			]);
		}

		$whereEmp = $employeeId ? ' AND e.id = :eid' : '';
		$params = [':b' => $branchId];
		if ($employeeId) $params[':eid'] = $employeeId;

		$balances = $this->Q("SELECT e.id AS employee_id, e.name, e.payroll_code, e.department,
				COUNT(CASE WHEN cb.status = 'PENDING' THEN 1 END) AS pending_days,
				COUNT(CASE WHEN cb.status IN ('SCHEDULED', 'TAKEN') THEN 1 END) AS taken_days,
				COUNT(CASE WHEN cb.status IN ('EXPIRED_PAYOUT_DUE', 'PAID_OUT') THEN 1 END) AS payout_days,
				ROUND(IFNULL(SUM(CASE WHEN cb.status IN ('EXPIRED_PAYOUT_DUE', 'PAID_OUT') THEN cb.payout_amount ELSE 0 END), 0), 2) AS total_payout_due,
				MIN(CASE WHEN cb.status = 'PENDING' THEN cb.due_date END) AS next_due_date
			FROM employees e
			LEFT JOIN workforce_compensatory_bank cb ON cb.payroll_code = e.payroll_code
			WHERE e.branch_id = :b AND e.active = 1 $whereEmp
			GROUP BY e.id, e.name, e.payroll_code, e.department
			HAVING pending_days > 0 OR taken_days > 0 OR payout_days > 0
			ORDER BY pending_days DESC, e.name ASC", $params)->fetchAll(\PDO::FETCH_ASSOC);

		$entries = $this->Q("SELECT cb.*, e.name AS employee_name, e.department
			FROM workforce_compensatory_bank cb
			JOIN employees e ON e.payroll_code = cb.payroll_code
			WHERE e.branch_id = :b $whereEmp
			ORDER BY cb.origin_sunday DESC, cb.id DESC LIMIT 100", $params)->fetchAll(\PDO::FETCH_ASSOC);

		return ['balances' => $balances, 'entries' => $entries, 'jurisdiction' => $policy['jurisdiction_code']];
	}

	/**
	 * Registra o redime un compensatorio ganado en domingo/festivo (evita duplicados y soporta Art. 180 CST pago en dinero cuando es ocasional).
	 */
	public function AccrueOrRedeemCompensatory(int $employeeId, string $originSunday, bool $choosePayout, ?string $redeemDate, int $userId): array
	{
		$emp = $this->GetEmployee($employeeId);
		$payrollCode = !empty($emp->payroll_code) ? $emp->payroll_code : ('EMP-' . $emp->id);
		$branchId = intval($emp->branch_id ?: 1);
		$policy = \Grocy\Services\WorkforceAutoScheduleService::GetInstance()->GetBranchJurisdictionPolicy($branchId, $originSunday);

		$ym = substr($originSunday, 0, 7);
		$workedSundays = intval($this->Q("SELECT COUNT(*) FROM employee_shifts WHERE employee_id = :e AND status != 'cancelled' AND substr(shift_date, 1, 7) = :ym AND strftime('%w', shift_date) = '0'", [':e' => $employeeId, ':ym' => $ym])->fetchColumn()) + 1;
		$eval = WorkforceLaborRulesService::EvaluateCompensatoryRule($originSunday, $workedSundays, $choosePayout, floatval($emp->hourly_cost ?: 16.5), $policy);

		return $this->Tx(function () use ($employeeId, $payrollCode, $branchId, $originSunday, $eval, $redeemDate, $userId) {
			$existing = $this->Q('SELECT * FROM workforce_compensatory_bank WHERE payroll_code = :pc AND origin_sunday = :od', [':pc' => $payrollCode, ':od' => $originSunday])->fetch(\PDO::FETCH_OBJ);
			if ($existing && in_array($existing->status, ['SCHEDULED', 'TAKEN', 'PAID_OUT'], true))
			{
				throw new \Exception("El compensatorio del {$originSunday} ya fue {$existing->status}; no se permite duplicarlo");
			}

			$status = ($eval['action'] === 'PAGO_RECARGO') ? 'PAID_OUT' : ($redeemDate ? 'SCHEDULED' : 'PENDING');
			$payout = ($eval['action'] === 'PAGO_RECARGO') ? $eval['payout_amount'] : 0.0;

			if ($existing)
			{
				$this->Q('UPDATE workforce_compensatory_bank SET status = :st, assigned_date = :ad, entitlement = :en, due_date = :dd, payout_amount = :pa WHERE id = :id', [
					':st' => $status, ':ad' => $redeemDate, ':en' => $eval['entitlement'], ':dd' => $eval['due_date'], ':pa' => $payout, ':id' => $existing->id
				]);
			}
			else
			{
				$this->Q('INSERT INTO workforce_compensatory_bank (payroll_code, origin_sunday, assigned_date, status, entitlement, due_date, payout_amount, notes) VALUES (:pc, :od, :ad, :st, :en, :dd, :pa, :nt)', [
					':pc' => $payrollCode, ':od' => $originSunday, ':ad' => $redeemDate, ':st' => $status, ':en' => $eval['entitlement'], ':dd' => $eval['due_date'], ':pa' => $payout, ':nt' => 'Generado desde motor laboral'
				]);
			}

			$mvType = $eval['action'] === 'PAGO_RECARGO' ? 'PAYOUT' : ($redeemDate ? 'REDEEMED' : 'ACCRUAL');
			$mvHours = $eval['action'] === 'PAGO_RECARGO' ? 0.0 : ($redeemDate ? -8.0 : 8.0);
			$this->Q('INSERT INTO workforce_compensatory_ledger (employee_id, payroll_code, branch_id, movement_type, hours, origin_date, due_date, payout_amount, status, created_by_user_id, notes) VALUES (:e, :pc, :b, :mt, :h, :od, :dd, :pa, :st, :u, :nt)', [
				':e' => $employeeId, ':pc' => $payrollCode, ':b' => $branchId, ':mt' => $mvType, ':h' => $mvHours, ':od' => $originSunday, ':dd' => $eval['due_date'], ':pa' => $payout, ':st' => $status, ':u' => $userId, ':nt' => "Derecho {$eval['entitlement']}"
			]);

			return array_merge($eval, ['status' => $status, 'assigned_date' => $redeemDate]);
		});
	}

	/**
	 * Devuelve el saldo real de vacaciones por empleado y período.
	 */
	public function GetVacationBalances(int $branchId, int $year = 2026): array
	{
		return $this->Q("SELECT e.id AS employee_id, e.name, e.payroll_code, e.department,
				COALESCE(vb.accrued_days, 15.0) AS accrued_days,
				COALESCE(vb.taken_days, 0.0) AS taken_days,
				COALESCE(vb.pending_days, 0.0) AS pending_days,
				ROUND(COALESCE(vb.accrued_days, 15.0) - COALESCE(vb.taken_days, 0.0) - COALESCE(vb.pending_days, 0.0), 1) AS available_days
			FROM employees e
			LEFT JOIN workforce_vacation_balances vb ON vb.employee_id = e.id AND vb.period_year = :y
			WHERE e.branch_id = :b AND e.active = 1
			ORDER BY e.name ASC", [':b' => $branchId, ':y' => $year])->fetchAll(\PDO::FETCH_ASSOC);
	}
}
