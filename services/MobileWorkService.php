<?php

namespace Grocy\Services;

use Grocy\Controllers\Users\User;
use Grocy\Helpers\DairyAccess;

/**
 * DAIRY Mobile por rol (empleado / supervisor / gerente): "Hoy", centro de tareas unificado, equipo, notificaciones
 * y escáner. No duplica lógica: lee las mismas tablas y llama a los servicios existentes (turnos, piso, alertas, mermas).
 * Los permisos se validan aquí y en el controlador; el rol solo decide qué se muestra primero.
 */
class MobileWorkService extends BaseService
{
	const ROLE_LABELS = ['employee' => 'Empleado', 'supervisor' => 'Supervisor', 'manager' => 'Gerente'];
	const NOTIF_TYPES = ['shifts' => 'Turnos y solicitudes', 'tasks' => 'Tareas asignadas', 'approvals' => 'Aprobaciones y decisiones', 'alerts' => 'Alertas y riesgos'];

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

	private function Val(string $sql, array $p = [])
	{
		$st = $this->Db()->prepare($sql);
		$st->execute($p);
		return $st->fetchColumn();
	}

	private function Sched(): WorkforceScheduleService
	{
		return WorkforceScheduleService::GetInstance();
	}

	// Rol de presentación derivado de los permisos reales (ADMIN = gerente)
	public static function Role(): string
	{
		if (DairyAccess::HasAny([User::PERMISSION_KPIS_VIEW, User::PERMISSION_WORKFORCE_SCHEDULE_MANAGE, User::PERMISSION_BRANCH_MANAGE]))
		{
			return 'manager';
		}
		if (DairyAccess::HasAny([User::PERMISSION_WASTE_APPROVE, User::PERMISSION_INVENTORY_ADJUST_APPROVE, User::PERMISSION_ALERTS_MANAGE, User::PERMISSION_WORKFORCE_ATTENDANCE_MANAGE]))
		{
			return 'supervisor';
		}
		return 'employee';
	}

	// Destinatario de una tarea sin persona asignada, en lenguaje de tienda
	public static function RoleGroup(?string $role): string
	{
		return ['clerk' => 'Equipo de piso', 'supervisor' => 'Supervisores', 'manager' => 'Gerencia'][$role ?? ''] ?? 'Todo el equipo';
	}

	public static function CanApprove(): bool
	{
		return DairyAccess::HasAny([User::PERMISSION_WASTE_APPROVE, User::PERMISSION_INVENTORY_ADJUST_APPROVE, User::PERMISSION_ALERTS_MANAGE, User::PERMISSION_WORKFORCE_SCHEDULE_MANAGE]);
	}

	public static function CanSeeTeam(): bool
	{
		return DairyAccess::HasAny([User::PERMISSION_WORKFORCE_SCHEDULE_MANAGE, User::PERMISSION_WORKFORCE_ATTENDANCE_MANAGE]);
	}

	public function Employee(int $userId): ?array
	{
		$e = $this->Sched()->GetEmployeeByUser($userId);
		return $e ? (array) $e : null;
	}

	/* ------------------------------------------------------------------ Turno */

	// Estado del turno del usuario: off | upcoming | due (debe marcar entrada) | late | on_shift | overtime | done
	public function ShiftState(int $userId): ?array
	{
		$emp = $this->Employee($userId);
		if (!$emp)
		{
			return null;
		}
		$now = time();
		$today = date('Y-m-d');
		$shifts = $this->Rows("SELECT id, shift_date, start_time, end_time, break_minutes, department, role, status, confirmed_at FROM employee_shifts WHERE employee_id = :e AND status NOT IN ('cancelled', 'planned') AND shift_date BETWEEN date(:d, '-1 day') AND date(:d, '+14 day') ORDER BY shift_date, start_time", [':e' => $emp['id'], ':d' => $today]);
		$open = $this->Rows('SELECT id, shift_id, clock_in FROM employee_attendance WHERE employee_id = :e AND clock_out IS NULL ORDER BY clock_in DESC LIMIT 1', [':e' => $emp['id']])[0] ?? null;
		$current = null;
		$next = null;
		foreach ($shifts as $s)
		{
			$start = strtotime($s['shift_date'] . ' ' . $s['start_time']);
			$end = strtotime($s['shift_date'] . ' ' . $s['end_time']);
			if ($end <= $start)
			{
				$end += 86400;
			}
			$s['start_ts'] = $start;
			$s['end_ts'] = $end;
			if ($now >= $start - 3600 && $now <= $end && !$current)
			{
				$current = $s;
			}
			elseif ($start > $now && !$next)
			{
				$next = $s;
			}
		}
		$done = $current ? $this->Val('SELECT COUNT(*) FROM employee_attendance WHERE employee_id = :e AND shift_id = :s AND clock_out IS NOT NULL', [':e' => $emp['id'], ':s' => $current['id']]) > 0 : false;
		$state = 'off';
		if ($open)
		{
			$state = $current && $now > $current['end_ts'] ? 'overtime' : 'on_shift';
			if (!$current)
			{
				$state = 'on_shift';
			}
		}
		elseif ($current)
		{
			$state = $done ? 'done' : ($now < $current['start_ts'] ? 'upcoming' : ($now - $current['start_ts'] > 300 ? 'late' : 'due'));
		}
		$monday = date('Y-m-d', strtotime('monday this week'));
		$worked = $this->Sched()->WorkedHoursByEmployee($monday, date('Y-m-d', strtotime("$monday +6 day")))[$emp['id']] ?? 0;
		return [
			'employee' => ['id' => intval($emp['id']), 'name' => $emp['name'], 'position' => $emp['position'], 'department' => $emp['department'], 'branch_id' => intval($emp['branch_id'])],
			'state' => $state, 'current' => $current, 'next' => $next, 'open_attendance' => $open,
			'week_worked' => round(floatval($worked), 2), 'contract' => $this->Sched()->ContractHours((object) $emp)
		];
	}

	/* ------------------------------------------------------------------ Tareas unificadas */

	private static function Sev(?string $due, bool $overdue): string
	{
		if ($overdue)
		{
			return 'high';
		}
		return $due && strtotime($due) < time() + 2 * 3600 ? 'medium' : 'low';
	}

	private static function Origin(?string $type, $id): ?array
	{
		if (!$type || !$id)
		{
			return null;
		}
		$map = ['waste' => ['Merma', '#/case/waste/' . $id], 'alert' => ['Alerta ALR-' . $id, '#/case/alert/' . $id], 'audit' => ['Auditoría #' . $id, null], 'count' => ['Conteo CC-' . $id, '#/case/count/' . $id]];
		return isset($map[$type]) ? ['label' => $map[$type][0], 'href' => $map[$type][1]] : ['label' => ucfirst($type) . ' #' . $id, 'href' => null];
	}

	private function BranchIds(?int $branchId): array
	{
		if ($branchId)
		{
			return [$branchId];
		}
		$allowed = DairyAccess::AllowedBranches();
		return $allowed ?? array_map('intval', array_column($this->Rows('SELECT id FROM branches WHERE IFNULL(active, 1) = 1'), 'id'));
	}

	// Tareas de piso (incluye las rutinas diarias que crea WorkforceService) con origen enlazado
	public function FloorTasks(?int $branchId, int $userId, string $scope, string $role): array
	{
		$emp = $this->Employee($userId);
		$rows = [];
		foreach ($this->BranchIds($branchId) as $b)
		{
			foreach (WorkforceService::GetInstance()->GetStoreTasks($b, 'pending') as $t)
			{
				$rows[] = (array) $t;
			}
		}
		// Sin asignar: cada rol ve las de su nivel (las del equipo completo están en "Equipo")
		$roleMatch = ['employee' => ['clerk', ''], 'supervisor' => ['supervisor', ''], 'manager' => ['manager', '']][$role];
		$horizon = date('Y-m-d 23:59:59', strtotime('+1 day'));
		// Rutinas de días anteriores no hechas: no se acumulan en "mis tareas" (quedan visibles en Equipo como vencidas)
		$floorFrom = date('Y-m-d H:i:s', strtotime('-36 hours'));
		$out = [];
		foreach ($rows as $t)
		{
			$mineDirect = intval($t['assigned_user_id']) === $userId || ($emp && intval($t['assigned_employee_id']) === intval($emp['id']));
			$unassigned = !$t['assigned_user_id'] && !$t['assigned_employee_id'];
			$forRole = $unassigned && ($roleMatch === null || in_array((string) $t['assigned_role'], $roleMatch, true));
			if ($scope === 'mine' && (!$mineDirect && !$forRole || (!$mineDirect && $t['due_time'] && $t['due_time'] < $floorFrom)))
			{
				continue;
			}
			if ($t['due_time'] && $t['due_time'] > $horizon)
			{
				continue;
			}
			$overdue = $t['due_time'] && strtotime($t['due_time']) < time();
			$out[] = ['type' => 'task', 'id' => intval($t['id']), 'ref' => 'TAR-' . $t['id'], 'branch_id' => intval($t['branch_id']),
				'title' => $t['title'], 'detail' => (string) $t['description'], 'severity' => self::Sev($t['due_time'], $overdue), 'overdue' => $overdue,
				'status' => $overdue ? 'Vencida' : 'Pendiente', 'due' => $t['due_time'], 'created_at' => $t['created_timestamp'],
				'assignee' => $t['assigned_user_name'] ?: ($unassigned ? self::RoleGroup($t['assigned_role']) : null), 'mine' => $mineDirect,
				'origin' => self::Origin($t['source_type'], $t['source_id']), 'source_type' => $t['source_type'], 'source_id' => $t['source_id'] ? intval($t['source_id']) : null,
				'href' => '#/task/' . $t['id']];
		}
		return $out;
	}

	public function GetTask(int $id): ?array
	{
		$r = $this->Rows("SELECT t.*, b.name AS branch_name, IFNULL(NULLIF(TRIM(IFNULL(u.first_name, '') || ' ' || IFNULL(u.last_name, '')), ''), u.username) AS assigned_user_name,
				IFNULL(NULLIF(TRIM(IFNULL(cu.first_name, '') || ' ' || IFNULL(cu.last_name, '')), ''), cu.username) AS completed_by_name, e.name AS assigned_employee_name
			FROM workforce_store_tasks t LEFT JOIN branches b ON b.id = t.branch_id LEFT JOIN users u ON u.id = t.assigned_user_id LEFT JOIN users cu ON cu.id = t.completed_by_user_id
			LEFT JOIN employees e ON e.id = t.assigned_employee_id WHERE t.id = :id", [':id' => $id])[0] ?? null;
		if ($r)
		{
			$r['origin'] = self::Origin($r['source_type'], $r['source_id']);
			$r['assigned_group'] = self::RoleGroup($r['assigned_role']);
		}
		return $r;
	}

	public function IsAssignedTo(array $task, int $userId): bool
	{
		$emp = $this->Employee($userId);
		return intval($task['assigned_user_id']) === $userId || ($emp && intval($task['assigned_employee_id']) === intval($emp['id']));
	}

	/**
	 * Completa una tarea de piso. Si nació de una alerta, la alerta se resuelve en la misma transacción
	 * (o queda "por aprobar cierre" si su regla lo exige): la tarea y su origen no quedan desalineados.
	 */
	public function CompleteTask(int $id, string $notes, ?string $photo, int $userId): array
	{
		$t = $this->GetTask($id);
		if (!$t)
		{
			throw new \Exception('Tarea no encontrada', 404);
		}
		if ($t['status'] !== 'pending')
		{
			throw new \Exception('La tarea ya fue completada');
		}
		$alertOpen = $t['source_type'] === 'alert' && $t['source_id'] && in_array($this->Val('SELECT status FROM dairy_alerts WHERE id = :i', [':i' => $t['source_id']]), AlertCenterService::WORKING, true) && $this->Val('SELECT status FROM dairy_alerts WHERE id = :i', [':i' => $t['source_id']]) !== 'waiting_approval';
		if ($alertOpen && mb_strlen(trim($notes)) < 5)
		{
			throw new \Exception('Describa qué hizo (mínimo 5 caracteres): con esa nota se cierra la alerta de origen');
		}
		$db = $this->Db();
		$db->beginTransaction();
		try
		{
			WorkforceService::GetInstance()->CompleteStoreTask($id, ['notes' => trim($notes), 'evidence_photo_path' => $photo], $userId);
			$alertStatus = null;
			if ($alertOpen)
			{
				$alertStatus = AlertCenterService::GetInstance()->Resolve(intval($t['source_id']), 'Tarea TAR-' . $id . ' completada: ' . trim($notes), $userId);
			}
			$db->commit();
		}
		catch (\Throwable $ex)
		{
			$db->rollBack();
			throw $ex;
		}
		return ['success' => true, 'alert_status' => $alertStatus,
			'message' => 'Tarea completada' . ($alertStatus === 'closed' ? ' y alerta cerrada' : ($alertStatus === 'pending_approval' ? '; la alerta queda por aprobar cierre' : ''))];
	}

	/**
	 * Convierte una alerta en tarea de piso para una persona (sin duplicar: si ya hay una pendiente, se devuelve esa).
	 */
	public function TaskFromAlert(int $alertId, int $ownerUserId, ?string $due, int $userId): array
	{
		$a = $this->Rows('SELECT * FROM dairy_alerts WHERE id = :i', [':i' => $alertId])[0] ?? null;
		if (!$a)
		{
			throw new \Exception('Alerta no encontrada', 404);
		}
		if (!in_array($a['status'], AlertCenterService::WORKING, true))
		{
			throw new \Exception('La alerta ya no está abierta');
		}
		if (!$a['branch_id'])
		{
			throw new \Exception('La alerta no tiene sucursal: asígnela desde el Centro de alertas');
		}
		$existing = $this->Val("SELECT id FROM workforce_store_tasks WHERE source_type = 'alert' AND source_id = :i AND status = 'pending'", [':i' => $alertId]);
		if ($existing)
		{
			return ['id' => intval($existing), 'created' => false, 'message' => 'Ya existe la tarea TAR-' . $existing . ' para esta alerta'];
		}
		if (!$this->Val('SELECT id FROM users WHERE id = :u', [':u' => $ownerUserId]))
		{
			throw new \Exception('Seleccione un responsable');
		}
		$due = $due && preg_match('/^\d{4}-\d{2}-\d{2}( \d{2}:\d{2}(:\d{2})?)?$/', $due) ? $due : ($a['due_at'] ?: date('Y-m-d H:i:s', strtotime('+4 hours')));
		$db = $this->Db();
		$db->beginTransaction();
		try
		{
			$db->prepare("INSERT INTO workforce_store_tasks (branch_id, task_type, assigned_role, assigned_user_id, title, description, due_time, status, source_type, source_id, created_timestamp)
				VALUES (:b, 'alert_followup', 'clerk', :u, :t, :d, :due, 'pending', 'alert', :i, :now)")
				->execute([':b' => $a['branch_id'], ':u' => $ownerUserId, ':t' => 'Atender: ' . $a['title'], ':d' => (string) $a['detail'], ':due' => $due, ':i' => $alertId, ':now' => date('Y-m-d H:i:s')]);
			$id = intval($db->lastInsertId());
			if (intval($a['owner_user_id']) !== $ownerUserId)
			{
				AlertCenterService::GetInstance()->Assign($alertId, $ownerUserId, $userId);
			}
			$db->commit();
		}
		catch (\Throwable $ex)
		{
			$db->rollBack();
			throw $ex;
		}
		return ['id' => $id, 'created' => true, 'message' => 'Tarea TAR-' . $id . ' creada y alerta asignada'];
	}

	// Tarea directa del supervisor a un empleado de la sucursal (se liga a su turno de ese día si existe)
	public function CreateTask(array $d, int $userId): array
	{
		$employeeId = intval($d['employee_id'] ?? 0);
		$title = trim(mb_substr(strval($d['title'] ?? ''), 0, 160));
		if ($title === '' || mb_strlen($title) < 3)
		{
			throw new \Exception('Indique qué hay que hacer (mínimo 3 caracteres)');
		}
		$emp = $this->Rows('SELECT id, user_id, branch_id, active FROM employees WHERE id = :e', [':e' => $employeeId])[0] ?? null;
		if (!$emp || !intval($emp['active']))
		{
			throw new \Exception('Seleccione un empleado activo');
		}
		$due = strval($d['due'] ?? '');
		$due = preg_match('/^\d{4}-\d{2}-\d{2}( \d{2}:\d{2}(:\d{2})?)?$/', $due) ? $due : date('Y-m-d H:i:s', strtotime('+4 hours'));
		$shift = $this->ShiftForTask($employeeId, $due);
		$this->Db()->prepare("INSERT INTO workforce_store_tasks (branch_id, task_type, assigned_role, assigned_user_id, assigned_employee_id, shift_id, title, description, due_time, status, source_type, source_id, created_timestamp)
			VALUES (:b, 'manual', 'clerk', :u, :e, :s, :t, :d, :due, 'pending', 'manual', :by, :now)")
			->execute([':b' => $emp['branch_id'], ':u' => $emp['user_id'] ?: null, ':e' => $employeeId, ':s' => $shift ?: null, ':t' => $title, ':d' => mb_substr(strval($d['description'] ?? ''), 0, 1000), ':due' => $due, ':by' => $userId, ':now' => date('Y-m-d H:i:s')]);
		$id = intval($this->Db()->lastInsertId());
		return ['id' => $id, 'created' => true, 'message' => 'Tarea TAR-' . $id . ' asignada'];
	}

	/**
	 * Turno publicado al que se liga una tarea: el que está en curso (incluye turnos nocturnos que cruzan la medianoche)
	 * o el que contiene el vencimiento; si no, el primero del día del vencimiento.
	 */
	private function ShiftForTask(int $employeeId, string $due): ?int
	{
		$dueTs = strtotime(strlen($due) === 10 ? $due . ' 23:59:59' : $due);
		$day = date('Y-m-d', $dueTs);
		$rows = $this->Rows("SELECT id, shift_date, start_time, end_time FROM employee_shifts WHERE employee_id = :e AND status = 'published'
			AND shift_date BETWEEN date(:t, '-1 day') AND :d ORDER BY shift_date, start_time", [':e' => $employeeId, ':t' => min(date('Y-m-d'), $day), ':d' => $day]);
		$within = function ($r, int $ts) {
			$start = strtotime($r['shift_date'] . ' ' . $r['start_time']);
			$end = strtotime($r['shift_date'] . ' ' . $r['end_time']);
			if ($end <= $start)
			{
				$end += 86400;
			}
			return $ts >= $start && $ts <= $end;
		};
		foreach ([time(), $dueTs] as $ts)
		{
			foreach ($rows as $r)
			{
				if ($within($r, $ts))
				{
					return intval($r['id']);
				}
			}
		}
		foreach ($rows as $r)
		{
			if ($r['shift_date'] === $day)
			{
				return intval($r['id']);
			}
		}
		return null;
	}

	public function EmployeeBranch(int $employeeId): ?int
	{
		$b = $this->Val('SELECT branch_id FROM employees WHERE id = :e', [':e' => $employeeId]);
		return $b ? intval($b) : null;
	}

	// Solicitudes de personal pendientes de decisión (ausencias y cambios de turno)
	public function PendingPeopleRequests(array $branchIds, ?int $userId = null): array
	{
		$out = [];
		// Segregación: nadie decide sus propias solicitudes (el servicio también lo impide)
		$own = $userId ? $this->Employee($userId) : null;
		$ownId = $own ? intval($own['id']) : -1;
		foreach ($branchIds as $b)
		{
			$req = $this->Sched()->GetPendingRequests($b);
			foreach ($req['absences'] as $a)
			{
				if ($a->status !== 'requested' || intval($a->employee_id) === $ownId || intval($a->requested_by_user_id) === $userId)
				{
					continue;
				}
				$out[] = ['type' => 'absence', 'id' => intval($a->id), 'ref' => 'AUS-' . $a->id, 'branch_id' => $b,
					'title' => 'Ausencia · ' . $a->employee_name, 'detail' => (WorkforceScheduleService::ABSENCE_TYPES[$a->absence_type] ?? $a->absence_type) . ' · ' . $a->start_date . ($a->end_date !== $a->start_date ? ' → ' . $a->end_date : '') . ($a->notes ? ' · ' . $a->notes : ''),
					'severity' => $a->start_date <= date('Y-m-d', strtotime('+2 day')) ? 'high' : 'medium', 'overdue' => false, 'status' => 'Por decidir', 'due' => $a->start_date, 'created_at' => $a->row_created_timestamp];
			}
			foreach ($req['swaps'] as $s)
			{
				if ($s->status !== 'requested' || intval($s->from_employee_id) === $ownId)
				{
					continue;
				}
				$out[] = ['type' => 'swap', 'id' => intval($s->id), 'ref' => 'CAM-' . $s->id, 'branch_id' => $b,
					'title' => 'Cambio de turno · ' . $s->from_name . ' → ' . $s->to_name, 'detail' => $s->shift_date . ' ' . substr($s->start_time, 0, 5) . '–' . substr($s->end_time, 0, 5) . ($s->notes ? ' · ' . $s->notes : ''),
					'severity' => $s->shift_date <= date('Y-m-d', strtotime('+2 day')) ? 'high' : 'medium', 'overdue' => false, 'status' => 'Por decidir', 'due' => $s->shift_date, 'created_at' => $s->row_created_timestamp];
			}
			// Correcciones de fichaje: ni quien la pidió ni el propio empleado deciden
			foreach ($this->Sched()->GetAttendanceAdjustments($b, 'requested') as $a)
			{
				if (intval($a->employee_id) === $ownId || (intval($a->requested_by_user_id) === $userId && !(defined('GROCY_WORKFORCE_ADJUSTMENT_SELF_APPROVAL') && GROCY_WORKFORCE_ADJUSTMENT_SELF_APPROVAL)))
				{
					continue;
				}
				$fmt = fn($v) => $v ? substr($v, 5, 11) : 'sin salida';
				$out[] = ['type' => 'adjustment', 'id' => intval($a->id), 'ref' => 'HRS-' . $a->id, 'branch_id' => $b,
					'title' => ($a->original_clock_in ? 'Corrección de horas · ' : 'Fichaje omitido · ') . $a->employee_name,
					'detail' => ($a->original_clock_in ? 'Antes ' . $fmt($a->original_clock_in) . '→' . $fmt($a->original_clock_out) . ' · ahora ' : '') . $fmt($a->new_clock_in) . '→' . $fmt($a->new_clock_out) . ' · ' . $a->reason . ' (pidió ' . ($a->requested_by_name ?? '—') . ')',
					'severity' => 'medium', 'overdue' => false, 'status' => 'Por decidir', 'due' => substr($a->new_clock_in, 0, 10), 'created_at' => $a->row_created_timestamp];
			}
		}
		return $out;
	}

	/**
	 * Centro de tareas: "mine" (lo que debo hacer), "approve" (lo que debo decidir), "team" (todo lo abierto de la sucursal).
	 * Sin duplicados: una alerta con tarea pendiente vinculada se muestra solo como tarea.
	 */
	public function Tasks(string $scope, ?int $branchId, int $userId, string $role): array
	{
		$items = [];
		$inbox = [];
		if (DairyAccess::HasAny([User::PERMISSION_WASTE_APPROVE, User::PERMISSION_ALERTS_MANAGE, User::PERMISSION_KPIS_VIEW, User::PERMISSION_STOCK_INVENTORY]) || DairyAccess::CanOpen('/alerts'))
		{
			$inbox = MobileManagerService::GetInstance()->Inbox($branchId, DairyAccess::AllowedBranches());
		}
		$canType = [
			'waste' => DairyAccess::HasAny([User::PERMISSION_WASTE_APPROVE]),
			'count' => DairyAccess::HasAny([User::PERMISSION_STOCK_INVENTORY, User::PERMISSION_INVENTORY_ADJUST_APPROVE]),
			'alert' => DairyAccess::CanOpen('/alerts'),
			'transfer' => DairyAccess::HasAny([User::PERMISSION_STOCK_TRANSFER]),
		];
		$inbox = array_values(array_filter($inbox, fn($i) => $canType[$i['type']] ?? false));
		$floor = $this->FloorTasks($branchId, $userId, $scope === 'team' ? 'team' : 'mine', $role);
		$linkedAlerts = [];
		foreach ($floor as $f)
		{
			if ($f['source_type'] === 'alert')
			{
				$linkedAlerts[$f['source_id']] = true;
			}
		}
		$inbox = array_values(array_filter($inbox, fn($i) => !($i['type'] === 'alert' && isset($linkedAlerts[$i['id']]))));
		foreach ($inbox as &$i)
		{
			$i['href'] = '#/case/' . $i['type'] . '/' . $i['id'];
		}
		unset($i);
		$myAlerts = [];
		if ($canType['alert'])
		{
			foreach (AlertCenterService::GetInstance()->GetAlerts('active', $branchId, DairyAccess::AllowedBranches(), $userId, 100) as $a)
			{
				$myAlerts[intval($a['id'])] = true;
			}
		}
		$isApproval = fn($i) => ($i['type'] === 'waste' && $i['status'] === 'Por aprobar') || ($i['type'] === 'count' && $i['status'] === 'Diferencia') || ($i['type'] === 'alert' && $i['status'] === MobileManagerService::AlertLabel('pending_approval'));

		if ($scope === 'mine')
		{
			$items = $floor;
			// Conteos asignados a otra persona no son "míos"
			$others = array_flip(array_map('intval', array_column($this->Rows("SELECT id FROM stock_cycle_counts WHERE status = 'pending' AND assigned_user_id IS NOT NULL AND assigned_user_id != :u", [':u' => $userId]), 'id')));
			foreach ($inbox as $i)
			{
				// Lo que me toca ejecutar: conteos pendientes, alertas a mi nombre, traslados por recibir
				if (($i['type'] === 'count' && $i['status'] === 'Pendiente' && !isset($others[$i['id']])) || ($i['type'] === 'alert' && isset($myAlerts[$i['id']]) && !$isApproval($i)) || $i['type'] === 'transfer')
				{
					$items[] = $i;
				}
			}
		}
		elseif ($scope === 'approve')
		{
			$items = array_values(array_filter($inbox, $isApproval));
			if (DairyAccess::HasAny([User::PERMISSION_WORKFORCE_SCHEDULE_MANAGE]))
			{
				$items = array_merge($items, $this->PendingPeopleRequests($this->BranchIds($branchId), $userId));
			}
		}
		else
		{
			$items = array_merge($floor, $inbox);
		}
		$rank = ['high' => 0, 'medium' => 1, 'low' => 2];
		usort($items, fn($a, $b) => ($rank[$a['severity']] ?? 3) <=> ($rank[$b['severity']] ?? 3) ?: ($b['overdue'] <=> $a['overdue']) ?: strcmp((string) ($a['due'] ?? '9999'), (string) ($b['due'] ?? '9999')));
		return $items;
	}

	public function TaskCounts(?int $branchId, int $userId, string $role): array
	{
		$mine = $this->Tasks('mine', $branchId, $userId, $role);
		$approve = self::CanApprove() ? $this->Tasks('approve', $branchId, $userId, $role) : [];
		return [
			'mine' => count($mine), 'mine_overdue' => count(array_filter($mine, fn($i) => $i['overdue'])),
			'approve' => count($approve), 'approve_high' => count(array_filter($approve, fn($i) => $i['severity'] === 'high')),
			'done_today' => intval($this->Val("SELECT COUNT(*) FROM workforce_store_tasks WHERE completed_by_user_id = :u AND date(completed_at) = :d", [':u' => $userId, ':d' => date('Y-m-d')])),
			'top_mine' => array_slice($mine, 0, 4), 'top_approve' => array_slice($approve, 0, 3)
		];
	}

	/* ------------------------------------------------------------------ Hoy */

	public function Today(?int $branchId, int $userId, string $role): array
	{
		$shift = $this->ShiftState($userId);
		$tasks = $this->TaskCounts($branchId, $userId, $role);
		$next = $this->NextAction($shift, $tasks);
		$recent = $this->Rows("SELECT 'task' AS kind, id, title AS label, completed_at AS at FROM workforce_store_tasks WHERE completed_by_user_id = :u AND completed_at >= :since
			UNION ALL SELECT 'waste', w.id, IFNULL(w.case_number, 'MRM-' || w.id) || ' · ' || IFNULL(p.name, ''), w.created_timestamp FROM waste_records w LEFT JOIN products p ON p.id = w.product_id WHERE w.reported_by_user_id = :u AND w.created_timestamp >= :since
			ORDER BY at DESC LIMIT 5", [':u' => $userId, ':since' => date('Y-m-d H:i:s', strtotime('-2 day'))]);
		$d = ['role' => $role, 'shift' => $shift, 'tasks' => $tasks, 'next_action' => $next, 'recent' => $recent, 'now' => date('Y-m-d H:i:s')];
		if (self::CanSeeTeam())
		{
			$b = $branchId ?? ($shift['employee']['branch_id'] ?? null) ?? ($this->BranchIds(null)[0] ?? null);
			if ($b)
			{
				$d['team'] = $this->TeamSummary($b, date('Y-m-d'));
			}
		}
		return $d;
	}

	private function NextAction(?array $shift, array $tasks): array
	{
		if ($shift && in_array($shift['state'], ['due', 'late'], true))
		{
			return ['kind' => 'clock_in', 'title' => $shift['state'] === 'late' ? 'Marque su entrada: su turno ya empezó' : 'Marque su entrada', 'detail' => 'Turno ' . substr($shift['current']['start_time'], 0, 5) . '–' . substr($shift['current']['end_time'], 0, 5), 'tone' => $shift['state'] === 'late' ? 'danger' : 'info'];
		}
		if ($shift && $shift['state'] === 'overtime')
		{
			return ['kind' => 'clock_out', 'title' => 'Su turno terminó: marque la salida', 'detail' => 'Salida programada ' . substr($shift['current']['end_time'], 0, 5), 'tone' => 'warn'];
		}
		$overdue = array_values(array_filter($tasks['top_mine'], fn($i) => $i['overdue']));
		if ($overdue)
		{
			return ['kind' => 'open', 'title' => $overdue[0]['title'], 'detail' => 'Vencida · ' . ($overdue[0]['detail'] ?: 'ábrala para completarla'), 'href' => $overdue[0]['href'], 'tone' => 'danger'];
		}
		if ($tasks['approve_high'] > 0)
		{
			return ['kind' => 'open', 'title' => $tasks['approve_high'] . ' decisiones urgentes por tomar', 'detail' => $tasks['top_approve'][0]['title'] ?? '', 'href' => '#/tasks?scope=approve', 'tone' => 'warn'];
		}
		if ($tasks['top_mine'])
		{
			$t = $tasks['top_mine'][0];
			return ['kind' => 'open', 'title' => $t['title'], 'detail' => ($t['due'] ? 'Para las ' . substr($t['due'], 11, 5) . ' · ' : '') . ($t['detail'] ?: ''), 'href' => $t['href'], 'tone' => 'info'];
		}
		if ($tasks['approve'] > 0)
		{
			return ['kind' => 'open', 'title' => $tasks['approve'] . ' pendientes por aprobar', 'detail' => 'Revise uno por uno', 'href' => '#/tasks?scope=approve', 'tone' => 'info'];
		}
		return ['kind' => 'none', 'title' => 'Todo al día', 'detail' => 'No tiene tareas pendientes', 'tone' => 'ok'];
	}

	/* ------------------------------------------------------------------ Equipo */

	public function TeamSummary(int $branchId, string $date): array
	{
		$team = $this->Team($branchId, $date);
		$c = ['scheduled' => 0, 'on_shift' => 0, 'late' => 0, 'absent' => 0];
		foreach ($team['people'] as $p)
		{
			$c['scheduled']++;
			if (isset($c[$p['status']]))
			{
				$c[$p['status']]++;
			}
		}
		return $c + ['gaps' => count($team['coverage']['gaps']), 'has_requirements' => $team['coverage']['has_requirements'], 'branch_id' => $branchId];
	}

	// Personas programadas en un día con estado de asistencia, ausencias aprobadas y huecos de cobertura
	public function Team(int $branchId, string $date): array
	{
		$shifts = $this->Rows("SELECT s.*, e.name AS employee_name, e.position, e.user_id FROM employee_shifts s JOIN employees e ON e.id = s.employee_id WHERE s.branch_id = :b AND s.shift_date = :d AND s.status != 'cancelled' ORDER BY s.start_time, e.name", [':b' => $branchId, ':d' => $date]);
		$att = [];
		foreach ($this->Rows('SELECT a.* FROM employee_attendance a JOIN employees e ON e.id = a.employee_id WHERE e.branch_id = :b AND date(a.clock_in) = :d ORDER BY a.clock_in', [':b' => $branchId, ':d' => $date]) as $a)
		{
			$att[$a['employee_id']][] = $a;
		}
		$abs = [];
		foreach ($this->Rows("SELECT a.employee_id, a.absence_type FROM employee_absences a JOIN employees e ON e.id = a.employee_id WHERE e.branch_id = :b AND a.status = 'approved' AND :d BETWEEN a.start_date AND a.end_date", [':b' => $branchId, ':d' => $date]) as $a)
		{
			$abs[$a['employee_id']] = WorkforceScheduleService::ABSENCE_TYPES[$a['absence_type']] ?? $a['absence_type'];
		}
		$now = time();
		$people = [];
		foreach ($shifts as $s)
		{
			$start = strtotime($s['shift_date'] . ' ' . $s['start_time']);
			$a = $att[$s['employee_id']] ?? [];
			$in = $a[0]['clock_in'] ?? null;
			$openNow = (bool) array_filter($a, fn($x) => !$x['clock_out']);
			if (isset($abs[$s['employee_id']]))
			{
				$status = 'absence';
			}
			elseif ($openNow)
			{
				$status = $in && strtotime($in) - $start > 300 ? 'late' : 'on_shift';
			}
			elseif ($a)
			{
				$status = 'finished';
			}
			elseif ($date === date('Y-m-d') && $now - $start > 900)
			{
				$status = 'absent';
			}
			else
			{
				$status = 'scheduled';
			}
			$people[] = ['shift_id' => intval($s['id']), 'version' => intval($s['version'] ?? 1), 'shift_date' => $s['shift_date'],'employee_id' => intval($s['employee_id']), 'name' => $s['employee_name'], 'position' => $s['position'],
				'start' => substr($s['start_time'], 0, 5), 'end' => substr($s['end_time'], 0, 5), 'break_minutes' => intval($s['break_minutes']), 'department' => $s['department'], 'role' => $s['role'],
				'shift_status' => $s['status'], 'status' => $status, 'clock_in' => $in, 'absence' => $abs[$s['employee_id']] ?? null];
		}
		return ['date' => $date, 'branch_id' => $branchId, 'people' => $people, 'coverage' => $this->Sched()->CoverageForDay($branchId, $date),
			'employees' => array_map(fn($e) => ['id' => intval($e->id), 'name' => $e->name, 'position' => $e->position], $this->Sched()->GetEmployees($branchId))];
	}

	/* ------------------------------------------------------------------ Mi horario */

	public function MySchedule(int $userId): ?array
	{
		$my = $this->Sched()->GetMySchedule($userId);
		if (!$my)
		{
			return null;
		}
		$emp = $my['employee'];
		$coworkers = array_values(array_filter(array_map(fn($e) => ['id' => intval($e->id), 'name' => $e->name, 'position' => $e->position], $this->Sched()->GetEmployees(intval($emp->branch_id))), fn($e) => $e['id'] !== intval($emp->id)));
		$swaps = $this->Rows("SELECT r.*, s.shift_date, s.start_time, s.end_time, ef.name AS from_name, et.name AS to_name FROM shift_swap_requests r JOIN employee_shifts s ON s.id = r.shift_id
			JOIN employees ef ON ef.id = r.from_employee_id JOIN employees et ON et.id = r.to_employee_id WHERE (r.from_employee_id = :e OR r.to_employee_id = :e) AND s.shift_date >= date('now', 'localtime', '-14 day') ORDER BY r.id DESC LIMIT 20", [':e' => $emp->id]);
		$certs = $this->Rows('SELECT name, issued_date, expires_date FROM employee_certifications WHERE employee_id = :e ORDER BY expires_date', [':e' => $emp->id]);
		// Los turnos en borrador ("planned") no se muestran al empleado hasta publicarse
		$my['shifts'] = array_values(array_filter($my['shifts'], fn($s) => $s->status !== 'planned'));
		return $my + ['availability' => $this->Sched()->GetAvailability(intval($emp->id)), 'coworkers' => $coworkers, 'swaps' => $swaps, 'certifications' => $certs, 'absence_types' => WorkforceScheduleService::ABSENCE_TYPES];
	}

	/* ------------------------------------------------------------------ Notificaciones */

	public function Prefs(int $userId): array
	{
		$raw = $this->Val("SELECT value FROM user_settings WHERE user_id = :u AND key = 'dairy_m_notif_prefs'", [':u' => $userId]);
		$p = $raw ? json_decode($raw, true) : null;
		$out = [];
		foreach (array_keys(self::NOTIF_TYPES) as $k)
		{
			$out[$k] = !is_array($p) || !array_key_exists($k, $p) || !empty($p[$k]);
		}
		return $out;
	}

	public function SetSetting(int $userId, string $key, string $value): void
	{
		$db = $this->Db();
		$exists = $this->Val('SELECT id FROM user_settings WHERE user_id = :u AND key = :k', [':u' => $userId, ':k' => $key]);
		if ($exists)
		{
			$db->prepare('UPDATE user_settings SET value = :v, row_updated_timestamp = :t WHERE id = :i')->execute([':v' => $value, ':t' => date('Y-m-d H:i:s'), ':i' => $exists]);
		}
		else
		{
			$db->prepare('INSERT INTO user_settings (user_id, key, value) VALUES (:u, :k, :v)')->execute([':u' => $userId, ':k' => $key, ':v' => $value]);
		}
	}

	public function GetSetting(int $userId, string $key): ?string
	{
		$v = $this->Val('SELECT value FROM user_settings WHERE user_id = :u AND key = :k', [':u' => $userId, ':k' => $key]);
		return $v === false ? null : $v;
	}

	/**
	 * Notificaciones derivadas de hechos reales (sin tabla propia: no hay que sincronizar ni pueden duplicarse).
	 * Cada una tiene una clave estable; "no leídas" = posteriores a la última apertura del centro.
	 */
	public function Notifications(?int $branchId, int $userId, string $role): array
	{
		$prefs = $this->Prefs($userId);
		$seen = $this->GetSetting($userId, 'dairy_m_notif_seen') ?? '1970-01-01 00:00:00';
		$since = date('Y-m-d H:i:s', strtotime('-7 day'));
		$items = [];
		$emp = $this->Employee($userId);
		if ($prefs['shifts'] && $emp)
		{
			// Publicación y cambios de turnos ya publicados (desde el historial: fecha real del cambio, no de creación)
			$labels = ['update' => 'Tu turno cambió', 'move' => 'Tu turno cambió', 'split' => 'Tu turno se dividió', 'swap' => 'Cambio de turno aplicado', 'cancel' => 'Turno anulado'];
			foreach ($this->Rows("SELECT c.id, c.shift_id, c.action, c.before_json, c.after_json, c.row_created_timestamp, s.shift_date, s.start_time, s.end_time, s.employee_id FROM employee_shift_changes c JOIN employee_shifts s ON s.id = c.shift_id
				WHERE c.row_created_timestamp >= :s AND c.undone = 0 AND c.action IN ('publish', 'update', 'move', 'split', 'swap', 'cancel') AND s.shift_date >= date('now', 'localtime', '-1 day')
					AND (s.employee_id = :e OR json_extract(c.before_json, '$.employee_id') = CAST(:e AS INTEGER)) ORDER BY c.id", [':e' => $emp['id'], ':s' => $since]) as $c)
			{
				$before = json_decode($c['before_json'] ?? 'null', true);
				$after = json_decode($c['after_json'] ?? 'null', true);
				$when = $after ? $after['shift_date'] . ' · ' . substr($after['start_time'], 0, 5) . '–' . substr($after['end_time'], 0, 5) : $c['shift_date'];
				if ($c['action'] === 'publish')
				{
					if (intval($c['employee_id']) === intval($emp['id']))
					{
						$items[] = ['key' => 'shift:' . $c['shift_id'], 'type' => 'shifts', 'icon' => 'fa-calendar-plus', 'tone' => 'info', 'title' => 'Turno publicado', 'detail' => $when, 'at' => $c['row_created_timestamp'], 'href' => '#/profile/schedule'];
					}
					continue;
				}
				if (!$before || $before['status'] !== 'published')
				{
					continue; // los borradores no se notifican
				}
				$lost = intval($before['employee_id']) === intval($emp['id']) && intval($after['employee_id'] ?? 0) !== intval($emp['id']);
				$items[] = ['key' => 'shchg:' . $c['id'], 'type' => 'shifts', 'icon' => $c['action'] === 'cancel' || $lost ? 'fa-calendar-xmark' : 'fa-calendar-day', 'tone' => $c['action'] === 'cancel' || $lost ? 'warn' : 'info',
					'title' => $lost ? 'Turno reasignado a otra persona' : ($labels[$c['action']] ?? 'Tu turno cambió'),
					'detail' => ($c['action'] !== 'cancel' && !$lost ? 'Antes ' . $before['shift_date'] . ' ' . substr($before['start_time'], 0, 5) . '–' . substr($before['end_time'], 0, 5) . ' · ahora ' : '') . $when,
					'at' => $c['row_created_timestamp'], 'href' => '#/profile/schedule'];
			}
			foreach ($this->Rows("SELECT id, status, new_clock_in, decided_timestamp, decision_notes FROM employee_attendance_adjustments WHERE employee_id = :e AND status IN ('approved', 'rejected') AND decided_timestamp >= :s", [':e' => $emp['id'], ':s' => $since]) as $a)
			{
				$items[] = ['key' => 'adj:' . $a['id'], 'type' => 'shifts', 'icon' => $a['status'] === 'approved' ? 'fa-user-check' : 'fa-user-xmark', 'tone' => $a['status'] === 'approved' ? 'ok' : 'danger',
					'title' => 'Corrección de horas ' . ($a['status'] === 'approved' ? 'aprobada' : 'rechazada'), 'detail' => substr($a['new_clock_in'], 0, 10) . ($a['decision_notes'] ? ' · ' . $a['decision_notes'] : ''), 'at' => $a['decided_timestamp'], 'href' => '#/profile/hours'];
			}
			foreach ($this->Rows("SELECT id, absence_type, start_date, status, decided_timestamp FROM employee_absences WHERE employee_id = :e AND status IN ('approved', 'rejected') AND decided_timestamp >= :s", [':e' => $emp['id'], ':s' => $since]) as $a)
			{
				$items[] = ['key' => 'abs:' . $a['id'] . ':' . $a['status'], 'type' => 'shifts', 'icon' => $a['status'] === 'approved' ? 'fa-circle-check' : 'fa-circle-xmark', 'tone' => $a['status'] === 'approved' ? 'ok' : 'danger',
					'title' => 'Ausencia ' . ($a['status'] === 'approved' ? 'aprobada' : 'rechazada'), 'detail' => (WorkforceScheduleService::ABSENCE_TYPES[$a['absence_type']] ?? '') . ' desde ' . $a['start_date'], 'at' => $a['decided_timestamp'], 'href' => '#/profile/requests'];
			}
			foreach ($this->Rows("SELECT r.id, r.status, r.from_employee_id, r.to_employee_id, r.decided_timestamp, r.row_created_timestamp, s.shift_date, ef.name AS from_name FROM shift_swap_requests r JOIN employee_shifts s ON s.id = r.shift_id JOIN employees ef ON ef.id = r.from_employee_id
				WHERE (r.from_employee_id = :e OR r.to_employee_id = :e) AND IFNULL(r.decided_timestamp, r.row_created_timestamp) >= :s", [':e' => $emp['id'], ':s' => $since]) as $r)
			{
				if ($r['status'] === 'requested' && intval($r['to_employee_id']) === intval($emp['id']))
				{
					$items[] = ['key' => 'swap:' . $r['id'] . ':req', 'type' => 'shifts', 'icon' => 'fa-right-left', 'tone' => 'info', 'title' => $r['from_name'] . ' le propone cambiar un turno', 'detail' => 'Turno del ' . $r['shift_date'] . ' · lo decide su gerente', 'at' => $r['row_created_timestamp'], 'href' => '#/profile/requests'];
				}
				elseif ($r['status'] !== 'requested')
				{
					$items[] = ['key' => 'swap:' . $r['id'] . ':' . $r['status'], 'type' => 'shifts', 'icon' => 'fa-right-left', 'tone' => $r['status'] === 'approved' ? 'ok' : 'danger', 'title' => 'Cambio de turno ' . ($r['status'] === 'approved' ? 'aprobado' : 'rechazado'), 'detail' => 'Turno del ' . $r['shift_date'], 'at' => $r['decided_timestamp'], 'href' => '#/profile/schedule'];
				}
			}
		}
		if ($prefs['tasks'])
		{
			$q = "SELECT id, title, due_time, created_timestamp FROM workforce_store_tasks WHERE status = 'pending' AND created_timestamp >= :s AND (assigned_user_id = :u" . ($emp ? ' OR assigned_employee_id = ' . intval($emp['id']) : '') . ')';
			foreach ($this->Rows($q, [':s' => $since, ':u' => $userId]) as $t)
			{
				$items[] = ['key' => 'task:' . $t['id'], 'type' => 'tasks', 'icon' => 'fa-list-check', 'tone' => 'info', 'title' => 'Nueva tarea: ' . $t['title'], 'detail' => $t['due_time'] ? 'Para ' . substr($t['due_time'], 0, 16) : '', 'at' => $t['created_timestamp'], 'href' => '#/task/' . $t['id']];
			}
		}
		if ($prefs['approvals'])
		{
			// Decisión sobre mermas que reportó el propio usuario (aprobadas por otra persona o rechazadas)
			foreach ($this->Rows("SELECT w.id, w.status, w.case_number, w.amount, w.approved_timestamp, p.name AS product_name FROM waste_records w JOIN products p ON p.id = w.product_id
				WHERE w.reported_by_user_id = :u AND w.status IN ('approved', 'rejected') AND w.approved_timestamp >= :s AND IFNULL(w.approved_by_user_id, 0) <> CAST(:u AS INTEGER)", [':u' => $userId, ':s' => $since]) as $w)
			{
				$items[] = ['key' => 'wdec:' . $w['id'] . ':' . $w['status'], 'type' => 'approvals', 'icon' => $w['status'] === 'approved' ? 'fa-circle-check' : 'fa-circle-xmark', 'tone' => $w['status'] === 'approved' ? 'ok' : 'danger',
					'title' => 'Merma ' . ($w['status'] === 'approved' ? 'aprobada' : 'rechazada') . ': ' . $w['product_name'], 'detail' => ($w['case_number'] ?: 'MRM-' . $w['id']) . ' · ' . floatval($w['amount']) . ' u.' . ($w['status'] === 'rejected' ? ' · vea el motivo en el caso' : ''), 'at' => $w['approved_timestamp'], 'href' => '#/case/waste/' . $w['id']];
			}
		}
		if ($prefs['approvals'] && self::CanApprove())
		{
			// Agrupadas por tipo: una sola notificación por tipo y cantidad (no una por registro)
			$groups = [];
			foreach ($this->Tasks('approve', $branchId, $userId, $role) as $i)
			{
				$groups[$i['type']] = ($groups[$i['type']] ?? []);
				$groups[$i['type']][] = $i;
			}
			$labels = ['waste' => 'mermas por aprobar', 'count' => 'diferencias de conteo por ajustar', 'alert' => 'cierres de alerta por aprobar', 'absence' => 'ausencias por decidir', 'swap' => 'cambios de turno por decidir'];
			foreach ($groups as $type => $list)
			{
				$latest = max(array_map(fn($i) => (string) ($i['created_at'] ?? ''), $list));
				$items[] = ['key' => 'appr:' . $type . ':' . count($list) . ':' . $latest, 'type' => 'approvals', 'icon' => 'fa-clipboard-check', 'tone' => 'warn', 'title' => count($list) . ' ' . ($labels[$type] ?? $type), 'detail' => $list[0]['title'], 'at' => $latest ?: date('Y-m-d H:i:s'), 'href' => '#/tasks?scope=approve', 'count' => count($list)];
			}
		}
		if ($prefs['alerts'] && DairyAccess::CanOpen('/alerts'))
		{
			$mine = !DairyAccess::HasAny([User::PERMISSION_ALERTS_MANAGE, User::PERMISSION_KPIS_VIEW]);
			foreach (AlertCenterService::GetInstance()->GetAlerts('active', $branchId, DairyAccess::AllowedBranches(), $mine ? $userId : null, 30) as $a)
			{
				if (!in_array($a['priority'], ['P1', 'P2'], true) && !$mine)
				{
					continue;
				}
				$items[] = ['key' => 'alert:' . $a['id'], 'type' => 'alerts', 'icon' => 'fa-triangle-exclamation', 'tone' => $a['priority'] === 'P1' ? 'danger' : 'warn', 'title' => $a['priority'] . ' · ' . $a['title'], 'detail' => (string) $a['detail'], 'at' => $a['created_at'], 'href' => '#/case/alert/' . $a['id']];
			}
		}
		usort($items, fn($a, $b) => strcmp((string) $b['at'], (string) $a['at']));
		foreach ($items as &$i)
		{
			$i['unread'] = (string) $i['at'] > $seen;
		}
		unset($i);
		return ['items' => array_slice($items, 0, 60), 'unread' => count(array_filter($items, fn($i) => $i['unread'])), 'prefs' => $prefs, 'types' => self::NOTIF_TYPES, 'seen' => $seen];
	}

	/* ------------------------------------------------------------------ Escáner universal */

	public function ScanActions(array $resolved): array
	{
		$a = [];
		if (($resolved['kind'] ?? '') === 'product')
		{
			$pid = intval($resolved['product']['id']);
			$lot = $resolved['parsed']['lot'] ?? null;
			if (DairyAccess::HasAny([User::PERMISSION_STOCK]))
			{
				$a[] = ['code' => 'sku', 'label' => 'Ver existencias y lotes', 'icon' => 'fa-box-open', 'href' => '#/sku/' . $pid];
			}
			if (DairyAccess::HasAny([User::PERMISSION_WASTE_REPORT]))
			{
				$a[] = ['code' => 'waste', 'label' => 'Registrar merma', 'icon' => 'fa-trash-can', 'href' => '#/capture/waste?product=' . $pid];
			}
			if (DairyAccess::HasAny([User::PERMISSION_STOCK_INVENTORY]))
			{
				$a[] = ['code' => 'count', 'label' => 'Contar', 'icon' => 'fa-clipboard-list', 'href' => '#/capture/count?product=' . $pid];
				$a[] = ['code' => 'adjust', 'label' => 'Ajustar inventario', 'icon' => 'fa-scale-balanced', 'href' => '#/capture/adjust?product=' . $pid];
			}
			if (DairyAccess::HasAny([User::PERMISSION_STOCK_TRANSFER]))
			{
				$a[] = ['code' => 'move', 'label' => 'Mover / reponer', 'icon' => 'fa-right-left', 'href' => '#/capture/move?product=' . $pid];
			}
			if (DairyAccess::HasAny([User::PERMISSION_SCAN_OPERATE]))
			{
				$a[] = ['code' => 'receive', 'label' => 'Recibir, despachar o trasladar', 'icon' => 'fa-truck-ramp-box', 'web' => '/scan'];
			}
			if (DairyAccess::HasAny([User::PERMISSION_STOCK]) && $lot)
			{
				$a[] = ['code' => 'trace', 'label' => 'Trazabilidad del lote ' . $lot, 'icon' => 'fa-route', 'web' => '/traceability?q=' . rawurlencode($lot)];
			}
		}
		elseif (($resolved['kind'] ?? '') === 'location' && DairyAccess::HasAny([User::PERMISSION_STOCK]))
		{
			$a[] = ['code' => 'map', 'label' => 'Ver en el mapa de ubicaciones', 'icon' => 'fa-map-location-dot', 'web' => '/locations/map'];
		}
		return $a;
	}
}
