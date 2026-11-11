<?php

namespace Grocy\Services;

use Grocy\Helpers\DairyAccess;

class AttendanceService extends BaseService
{
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

	/**
	 * Retorna la sucursal DEMO predeterminada o la especificada
	 */
	public function ResolveBranchId(?int $requestedBranchId): int
	{
		if ($requestedBranchId !== null && $requestedBranchId > 0)
		{
			return $requestedBranchId;
		}

		// Sucursal del usuario (o la primera de la red); ya no se abre la sucursal del escenario guionizado
		$allowed = \Grocy\Helpers\DairyAccess::AllowedBranches();
		return $allowed ? intval($allowed[0]) : intval($this->Db()->query('SELECT MIN(id) FROM branches WHERE active = 1')->fetchColumn());
	}

	/**
	 * Asegura que existan registros de asistencia coherentes para el escenario DEMO
	 * en la fecha dada (ej. 2026-11-04 o la fecha seleccionada).
	 * Exactamente: 25 programados, 20 presentes, 2 tardanzas, 2 ausencias, 1 pendiente.
	 */
	public function EnsureDemoAttendance(int $branchId, string $date): void
	{
		$db = $this->Db();
		$demoBranchId = DemoSchedulingScenarioService::GetInstance()->EnsureDemoBranch();

		// Solo sembramos de forma automática en la sucursal DEMO
		if ($branchId !== $demoBranchId)
		{
			return;
		}

		// Verificar cuántos turnos existen para esta fecha
		$shiftsStmt = $db->prepare("SELECT s.*, e.name, e.payroll_code, e.department as emp_dept FROM employee_shifts s JOIN employees e ON e.id = s.employee_id WHERE s.branch_id = :b AND s.shift_date = :d ORDER BY s.start_time ASC, e.name ASC");
		$shiftsStmt->execute([':b' => $branchId, ':d' => $date]);
		$shifts = $shiftsStmt->fetchAll(\PDO::FETCH_ASSOC);

		// Si no hay turnos en esta fecha, buscamos los de 2026-11-04 como fecha base DEMO
		if (empty($shifts))
		{
			$date = '2026-11-04';
			$shiftsStmt->execute([':b' => $branchId, ':d' => $date]);
			$shifts = $shiftsStmt->fetchAll(\PDO::FETCH_ASSOC);
		}

		if (empty($shifts))
		{
			return;
		}

		// Verificar si ya existen fichajes para esta fecha
		$existingCnt = intval($db->query("SELECT COUNT(*) FROM employee_attendance a JOIN employee_shifts s ON s.id = a.shift_id WHERE s.branch_id = $branchId AND s.shift_date = '$date'")->fetchColumn());
		// Sembrar solo si la fecha no tiene ningún fichaje: con datos parciales, el borrado de abajo
		// eliminaba ausencias aprobadas en cada carga de la página (GET)
		if ($existingCnt > 0)
		{
			return; // Ya está sembrado
		}

		// Distribución exacta para cumplir la composición de la imagen:
		// 25 empleados:
		// - 20 Presentes (puntuales o en turno)
		// - 2 Tardanzas:
		//     1. Luis Mendoza (DEMO-023 o Frutas/Bodega) -> llegó 15 min tarde (08:15)
		//     2. Valeria Sánchez / Camila Soto (DEMO-004 o Electrónica) -> llegó 5 min tarde (10:05 / 08:05)
		// - 2 Ausencias:
		//     1. Jorge Ramírez / Nicolás Torres -> Ausencia sin justificar
		//     2. Daniel Castro / Gabriel Castro -> Ausencia justificada (permiso / salud)
		// - 1 Pendiente:
		//     1. Carlos Moya / Sebastián Díaz -> Pendiente / Aún no registra entrada

		$db->beginTransaction();
		try
		{
			// Limpiar fichajes previos de esta fecha para la sucursal DEMO
			$db->exec("DELETE FROM employee_attendance WHERE shift_id IN (SELECT id FROM employee_shifts WHERE branch_id = $branchId AND shift_date = '$date')");
			$db->exec("DELETE FROM employee_absences WHERE employee_id IN (SELECT id FROM employees WHERE branch_id = $branchId) AND start_date = '$date'");

			$idx = 0;
			foreach ($shifts as $s)
			{
				$shiftId = intval($s['id']);
				$empId = intval($s['employee_id']);
				$code = $s['payroll_code'] ?? '';
				$startTime = $s['start_time'];
				$endTime = $s['end_time'];
				$dept = $s['department'] ?: $s['emp_dept'];

				// Configurar comportamientos según el índice y el empleado:
				// Tardanza 1: Luis Mendoza o índice 1 (Frutas y verduras / Bodega)
				if ($code === 'DEMO-011' || $idx === 1)
				{
					// Tardanza de 15 minutos
					$inTime = date('H:i:s', strtotime("$startTime +15 minutes"));
					$outTime = date('H:i:s', strtotime($endTime));
					$db->exec("INSERT INTO employee_attendance (employee_id, shift_id, clock_in, clock_out, source, notes, recorded_by_user_id) 
						VALUES ($empId, $shiftId, '$date $inTime', '$date $outTime', 'biometric', 'Llegó 15 min tarde por congestión vehicular', 1)");
				}
				// Tardanza 2: Valeria Sánchez / Camila Soto o índice 4 (Electrónica)
				elseif ($code === 'DEMO-004' || $idx === 4)
				{
					// Tardanza de 5 minutos
					$inTime = date('H:i:s', strtotime("$startTime +5 minutes"));
					$outTime = null; // Aún en turno
					$db->exec("INSERT INTO employee_attendance (employee_id, shift_id, clock_in, clock_out, source, notes, recorded_by_user_id) 
						VALUES ($empId, $shiftId, '$date $inTime', NULL, 'web', 'Llegó 5 min tarde', 1)");
				}
				// Ausencia 1: Jorge Ramírez / DEMO-003 (Sin justificar)
				elseif ($code === 'DEMO-003' || $idx === 3)
				{
					// Sin fichaje de asistencia, registrar ausencia injustificada
					$db->exec("INSERT INTO employee_absences (employee_id, absence_type, start_date, end_date, status, notes, requested_by_user_id, decided_by_user_id, decided_timestamp, row_created_timestamp)
						VALUES ($empId, 'unjustified', '$date', '$date', 'pending', 'Ausencia sin justificar reportada a las 08:00', 1, NULL, NULL, datetime('now'))");
				}
				// Ausencia 2: Daniel Castro / DEMO-009 (Justificada)
				elseif ($code === 'DEMO-009' || $idx === 7)
				{
					// Sin fichaje de asistencia, ausencia justificada con soporte médico
					$db->exec("INSERT INTO employee_absences (employee_id, absence_type, start_date, end_date, status, notes, requested_by_user_id, decided_by_user_id, decided_timestamp, row_created_timestamp)
						VALUES ($empId, 'sick', '$date', '$date', 'approved', 'Inasistencia justificada con certificado médico EPS', 1, 1, datetime('now'), datetime('now'))");
				}
				// Pendiente: Carlos Moya / DEMO-005 (En turno, aún no registra entrada)
				elseif ($code === 'DEMO-005' || $idx === 9)
				{
					// No tiene fichaje y no tiene ausencia justificada; está en horario de inicio pendiente
				}
				// Los demás 20 empleados: Presentes y puntuales
				else
				{
					// Entrada puntual (2 min antes o exacta)
					$offsetMin = ($idx % 3 === 0) ? -2 : (($idx % 2 === 0) ? -1 : 1);
					$inTime = date('H:i:s', strtotime("$startTime $offsetMin minutes"));
					$isCompleted = ($idx % 2 === 0);
					$outTime = $isCompleted ? date('H:i:s', strtotime("$endTime +1 minute")) : null;

					$outSql = $outTime ? "'$date $outTime'" : "NULL";
					$notes = $isCompleted ? "Turno completado normalmente" : "En turno operativo";
					$db->exec("INSERT INTO employee_attendance (employee_id, shift_id, clock_in, clock_out, source, notes, recorded_by_user_id) 
						VALUES ($empId, $shiftId, '$date $inTime', $outSql, 'biometric', '$notes', 1)");
				}

				$idx++;
			}

			// Asegurar una alerta en dairy_alerts para ausencias y tardanzas del día
			$this->SyncDailyAttendanceAlerts($branchId, $date);

			$db->commit();
		}
		catch (\Throwable $t)
		{
			$db->rollBack();
		}
	}

	/**
	 * Sincroniza las alertas reales del día en la tabla dairy_alerts desde registros verificados (sin datos demo fijos)
	 */
	public function SyncDailyAttendanceAlerts(int $branchId, string $date): void
	{
		$db = $this->Db();
		$overview = $this->GetAttendanceOverview($branchId, $date);
		$rows = $overview['rows'] ?? [];
		$alerts = [];

		foreach ($rows as $row)
		{
			$empId = intval($row['employee_id'] ?? 0);
			$empName = strval($row['employee_name'] ?? 'Colaborador');
			$dept = strval($row['department'] ?? 'General');
			$shiftStart = strval($row['scheduled_start'] ?? '');
			$shiftEnd = strval($row['scheduled_end'] ?? '');
			$status = strval($row['status'] ?? '');

			if ($status === 'ausente' && empty($row['absence_id']))
			{
				$alerts[] = [
					'rule_code' => 'attendance_unjustified',
					'dedupe_key' => "attendance:unjustified:$branchId:$date:emp-$empId-s-" . intval($row['shift_id']),
					'severity' => 'danger',
					'title' => "Inasistencia sin aviso: $empName ($dept)",
					'detail' => "No registró entrada para el turno $shiftStart–$shiftEnd.",
					'department' => $dept,
					'href' => "/operations/attendance?branch_id=$branchId&date=$date",
					'priority' => 'P1'
				];
			}
			elseif ($status === 'tardanza' && intval($row['diff_minutes'] ?? 0) >= 15)
			{
				$lateMin = intval($row['diff_minutes']);
				$alerts[] = [
					'rule_code' => 'attendance_late_15',
					'dedupe_key' => "attendance:late:$branchId:$date:emp-$empId-s-" . intval($row['shift_id']),
					'severity' => 'warning',
					'title' => "Tardanza de {$lateMin} min: $empName ($dept)",
					'detail' => "Entrada registrada a las " . ($row['clock_in'] ?: '—') . " (programado $shiftStart).",
					'department' => $dept,
					'href' => "/operations/attendance?branch_id=$branchId&date=$date",
					'priority' => 'P3'
				];
			}
		}

		foreach ($alerts as $a)
		{
			$stmt = $db->prepare("SELECT id FROM dairy_alerts WHERE dedupe_key = :k");
			$stmt->execute([':k' => $a['dedupe_key']]);
			if (!$stmt->fetchColumn())
			{
				$ins = $db->prepare("INSERT INTO dairy_alerts (rule_code, dedupe_key, branch_id, severity, title, detail, value, href, status, created_at, updated_at, category, alert_type, department, priority)
					VALUES (:rc, :dk, :bid, :sev, :title, :det, 1.0, :href, 'open', datetime('now'), datetime('now'), 'workforce', 'attendance', :dept, :prio)");
				$ins->execute([
					':rc' => $a['rule_code'],
					':dk' => $a['dedupe_key'],
					':bid' => $branchId,
					':sev' => $a['severity'],
					':title' => $a['title'],
					':det' => $a['detail'],
					':href' => $a['href'],
					':dept' => $a['department'],
					':prio' => $a['priority']
				]);
			}
		}
	}

	/**
	 * Mapeo de imágenes limpias y sutiles por departamento para las tarjetas de cobertura
	 */
	public function GetDepartmentImages(): array
	{
		return [
			'Electrónica' => '/img/control_center/depto_electronica.jpg',
			'Lácteos' => '/img/control_center/depto_lacteos.jpg',
			'Dairy / Lácteos' => '/img/control_center/depto_lacteos.jpg',
			'Frutas y Verduras' => '/img/control_center/depto_fruver.jpg',
			'Frutas y verduras' => '/img/control_center/depto_fruver.jpg',
			'Cajas' => '/img/control_center/depto_cajas.jpg',
			'Cajas y Atención al Cliente' => '/img/control_center/depto_cajas.jpg',
			'Bodega' => '/img/control_center/depto_bodega.jpg',
			'Bodega y Despachos' => '/img/control_center/depto_bodega.jpg',
			'Panadería / Bakery' => '/img/control_center/thumb_coffee_croissant.jpg',
			'Carnes y Pollo' => '/img/control_center/depto_carniceria.jpg'
		];
	}

	/**
	 * Mapeo de fotos de avatar por código de colaborador para replicar la tabla visual de DAILY
	 */
	public function GetEmployeeAvatars(): array
	{
		return [
			'DEMO-001' => 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=100&h=100&fit=crop&crop=face',
			'DEMO-002' => 'https://images.unsplash.com/photo-1494790108377-be9c29b29330?w=100&h=100&fit=crop&crop=face',
			'DEMO-003' => 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?w=100&h=100&fit=crop&crop=face',
			'DEMO-004' => 'https://images.unsplash.com/photo-1544005313-94ddf0286df2?w=100&h=100&fit=crop&crop=face',
			'DEMO-005' => 'https://images.unsplash.com/photo-1492562080023-ab3db95bfbce?w=100&h=100&fit=crop&crop=face',
			'DEMO-006' => 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=100&h=100&fit=crop&crop=face',
			'DEMO-007' => 'https://images.unsplash.com/photo-1506794778202-cad84cf45f1d?w=100&h=100&fit=crop&crop=face',
			'DEMO-008' => 'https://images.unsplash.com/photo-1438761681033-6461ffad8d80?w=100&h=100&fit=crop&crop=face',
			'DEMO-009' => 'https://images.unsplash.com/photo-1519085360753-af0119f7cbe7?w=100&h=100&fit=crop&crop=face',
			'DEMO-010' => 'https://images.unsplash.com/photo-1517841905240-472988babdf9?w=100&h=100&fit=crop&crop=face',
			'DEMO-011' => 'https://images.unsplash.com/photo-1522075469751-3a6694fb2f61?w=100&h=100&fit=crop&crop=face',
			'DEMO-012' => 'https://images.unsplash.com/photo-1517256673644-36ad11246d21?w=100&h=100&fit=crop&crop=face',
			'DEMO-013' => 'https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?w=100&h=100&fit=crop&crop=face',
			'DEMO-014' => 'https://images.unsplash.com/photo-1548142813-c348350df52b?w=100&h=100&fit=crop&crop=face',
			'DEMO-015' => 'https://images.unsplash.com/photo-1519085360753-af0119f7cbe7?w=100&h=100&fit=crop&crop=face',
			'DEMO-016' => 'https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?w=100&h=100&fit=crop&crop=face',
			'DEMO-017' => 'https://images.unsplash.com/photo-1501196354995-cbb51c65aaea?w=100&h=100&fit=crop&crop=face',
			'DEMO-018' => 'https://images.unsplash.com/photo-1580489944761-15a19d654956?w=100&h=100&fit=crop&crop=face',
			'DEMO-019' => 'https://images.unsplash.com/photo-1560250097-0b93528c311a?w=100&h=100&fit=crop&crop=face',
			'DEMO-020' => 'https://images.unsplash.com/photo-1573497019940-1c28c88b4f3e?w=100&h=100&fit=crop&crop=face',
			'DEMO-021' => 'https://images.unsplash.com/photo-1539571696357-5a69c17a67c6?w=100&h=100&fit=crop&crop=face',
			'DEMO-022' => 'https://images.unsplash.com/photo-1524504388940-b1c1722653e1?w=100&h=100&fit=crop&crop=face',
			'DEMO-023' => 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=100&h=100&fit=crop&crop=face',
			'DEMO-024' => 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=100&h=100&fit=crop&crop=face',
			'DEMO-025' => 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?w=100&h=100&fit=crop&crop=face'
		];
	}

	/**
	 * Genera un color HSL consistente a partir del nombre para fallback de avatar
	 */
	public function NameToColor(string $name): string
	{
		$hash = crc32($name);
		$hue = abs($hash % 360);
		return "hsl($hue, 65%, 45%)";
	}

	/**
	 * Obtiene el resumen general de asistencia con cálculo dinámico de KPIs,
	 * cobertura por departamento, lista maestra de fichajes, gráfico apilado y alertas.
	 */
	public function GetAttendanceOverview(?int $branchId, string $date, ?string $department = null, ?string $statusFilter = null, ?string $search = null): array
	{
		$db = $this->Db();
		$resolvedBranchId = $this->ResolveBranchId($branchId);

		// Asegurar datos DEMO para esta fecha si corresponde
		// La asistencia sale de los fichajes registrados (la empresa de ejemplo los genera desde los turnos)

		// Lista de sucursales para el filtro
		$branches = $db->query("SELECT id, name, code FROM branches ORDER BY id ASC")->fetchAll(\PDO::FETCH_ASSOC);

		// Lista de departamentos para el filtro
		$deptList = [
			'Electrónica',
			'Dairy / Lácteos',
			'Frutas y Verduras',
			'Cajas y Atención al Cliente',
			'Bodega y Despachos',
			'Panadería / Bakery',
			'Carnes y Pollo'
		];

		// Consultar turnos programados del día
		$sql = "
			SELECT 
				s.id AS shift_id,
				s.employee_id,
				s.branch_id,
				s.shift_date,
				s.start_time,
				s.end_time,
				s.break_minutes,
				s.department AS shift_dept,
				s.role,
				e.name AS employee_name,
				e.payroll_code,
				e.position,
				e.department AS employee_dept,
				b.name AS branch_name,
				a.id AS attendance_id,
				a.clock_in,
				a.clock_out,
				a.source,
				a.notes AS attendance_notes,
				abs.id AS absence_id,
				abs.absence_type,
				abs.status AS absence_status,
				abs.notes AS absence_notes,
				adj.id AS adjustment_id,
				adj.status AS adjustment_status
			FROM employee_shifts s
			JOIN employees e ON e.id = s.employee_id
			LEFT JOIN branches b ON b.id = s.branch_id
			LEFT JOIN employee_attendance a ON (a.shift_id = s.id OR (a.shift_id IS NULL AND a.employee_id = s.employee_id AND date(a.clock_in) = s.shift_date AND ABS(strftime('%s', a.clock_in) - strftime('%s', s.shift_date || ' ' || s.start_time)) <= 7200))
			LEFT JOIN employee_absences abs ON abs.employee_id = e.id AND abs.start_date <= s.shift_date AND abs.end_date >= s.shift_date
				AND abs.status IN ('approved', 'pending', 'requested')
			LEFT JOIN employee_attendance_adjustments adj ON adj.attendance_id = a.id AND adj.status = 'requested'
			WHERE s.branch_id = :bid AND s.shift_date = :dt AND s.status != 'cancelled'
		";

		$params = [':bid' => $resolvedBranchId, ':dt' => $date];

		if (!empty($department) && $department !== 'all')
		{
			// Mapeo flexible de departamento (ej. 'Lácteos' coincide con 'Dairy / Lácteos')
			$sql .= " AND (s.department LIKE :d OR e.department LIKE :d)";
			$params[':d'] = "%$department%";
		}

		// Una fila por turno aunque haya varias ausencias/fichajes/correcciones que coincidan
		$sql .= " GROUP BY s.id ORDER BY s.start_time ASC, e.name ASC";

		$stmt = $db->prepare($sql);
		$stmt->execute($params);
		$rawRows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

		// Si no hay turnos en la fecha consultada en la sucursal DEMO, buscamos la fecha base DEMO
		$isDemo = false; // sin fecha base de escenario: cada fecha muestra sus propios turnos
		if (empty($rawRows) && $isDemo)
		{
			$date = '2026-11-04';
			$params[':dt'] = $date;
			$stmt->execute($params);
			$rawRows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
		}

		$avatars = $this->GetEmployeeAvatars();
		$deptImages = $this->GetDepartmentImages();

		// Contadores globales
		$scheduledCount = count($rawRows);
		$presentCount = 0;
		$lateCount = 0;
		$absentCount = 0;
		$pendingCount = 0;
		$earlyDepartures = 0;
		$totalWorkedHours = 0.0;

		// Agrupadores por departamento
		$deptStats = [];
		foreach ($deptList as $d)
		{
			$deptStats[$d] = [
				'department' => $d,
				'scheduled' => 0,
				'present' => 0,
				'late' => 0,
				'absent' => 0,
				'pending' => 0,
				'hours' => 0.0,
				'image' => $deptImages[$d] ?? '/img/control_center/depto_lacteos.jpg'
			];
		}

		$records = [];

		foreach ($rawRows as $r)
		{
			$empName = $r['employee_name'];
			$code = $r['payroll_code'] ?: ('#EMP-' . str_pad($r['employee_id'], 3, '0', STR_PAD_LEFT));
			$dept = $r['shift_dept'] ?: $r['employee_dept'];

			// Normalizar nombre de departamento a la lista estándar
			$normDept = $dept;
			foreach ($deptList as $dl)
			{
				if (stripos($dl, $dept) !== false || stripos($dept, $dl) !== false)
				{
					$normDept = $dl;
					break;
				}
			}

			if (!isset($deptStats[$normDept]))
			{
				$deptStats[$normDept] = [
					'department' => $normDept,
					'scheduled' => 0,
					'present' => 0,
					'late' => 0,
					'absent' => 0,
					'pending' => 0,
					'hours' => 0.0,
					'image' => $deptImages[$normDept] ?? '/img/control_center/depto_lacteos.jpg'
				];
			}

			$deptStats[$normDept]['scheduled']++;

			// Turno programado
			$progStart = $r['start_time'];
			$progEnd = $r['end_time'];
			$breakMins = intval($r['break_minutes'] ?? 60);

			// Horas de fichaje
			$clockIn = $r['clock_in'] ? date('H:i', strtotime($r['clock_in'])) : null;
			$clockOut = $r['clock_out'] ? date('H:i', strtotime($r['clock_out'])) : null;

			// Cálculo de puntualidad y estado
			$status = 'pendiente';
			$diffMinutes = null;
			$observations = '—';
			$hoursWorked = 0.0;

			// ¿Tiene ausencia registrada?
			// Si fichó, el fichaje manda sobre la ausencia registrada
			if (!empty($r['absence_id']) && empty($clockIn))
			{
				$status = 'ausente';
				$absentCount++;
				$deptStats[$normDept]['absent']++;
				$observations = ($r['absence_status'] === 'approved') 
					? 'Inasistencia justificada (' . ($r['absence_notes'] ?: 'Aprobada') . ')'
					: 'Ausencia sin justificar';
			}
			// ¿Tiene entrada registrada?
			elseif (!empty($clockIn))
			{
				$progStartTs = strtotime("$date $progStart:00");
				$clockInTs = strtotime($r['clock_in']);
				$diffMinutes = round(($clockInTs - $progStartTs) / 60);

				if ($diffMinutes > 3) // Tolerancia estándar de 3 min
				{
					$status = 'tardanza';
					$lateCount++;
					$presentCount++; // Una persona con tardanza está presente físicamente
					$deptStats[$normDept]['late']++;
					$deptStats[$normDept]['present']++;
					$observations = 'Llegó ' . intval($diffMinutes) . ' min tarde' . ($r['attendance_notes'] ? (' · ' . $r['attendance_notes']) : '');
				}
				else
				{
					$status = 'presente';
					$presentCount++;
					$deptStats[$normDept]['present']++;
					$observations = $r['attendance_notes'] ?: 'Turno puntual';
				}

				// Cálculo de horas trabajadas (soportando turnos nocturnos que cruzan medianoche)
				if (!empty($clockOut))
				{
					$clockOutTs = strtotime($r['clock_out']);
					$grossHours = max(0, ($clockOutTs - $clockInTs) / 3600);
					$hoursWorked = max(0, round($grossHours - ($breakMins / 60), 1));
				}
				else
				{
					// En turno: estimación hasta la hora programada (ajustando cruce de medianoche)
					$progEndTs = strtotime("$date $progEnd:00");
					if ($progEndTs <= $progStartTs)
					{
						$progEndTs = strtotime(date('Y-m-d', strtotime("$date +1 day")) . " $progEnd:00");
					}
					$progHours = max(0, ($progEndTs - $progStartTs) / 3600);
					$hoursWorked = max(0, round($progHours - ($breakMins / 60), 1));
					$observations = ($status === 'tardanza') ? $observations : 'En turno activo';
				}

				$totalWorkedHours += $hoursWorked;
				$deptStats[$normDept]['hours'] += $hoursWorked;
			}
			// Sin fichaje y sin ausencia registrada: distinguir entrada pendiente (aún no empieza / dentro de tolerancia) vs inasistencia sin aviso
			else
			{
				$progStartTs = strtotime("$date $progStart:00");
				$nowTs = time();
				$graceOver = ($date < date('Y-m-d')) || ($date === date('Y-m-d') && $nowTs > ($progStartTs + 3600));
				if ($graceOver)
				{
					$status = 'ausente';
					$absentCount++;
					$deptStats[$normDept]['absent']++;
					$observations = 'Inasistencia sin aviso (superó tolerancia de inicio sin marcación)';
				}
				else
				{
					$status = 'pendiente';
					$pendingCount++;
					$deptStats[$normDept]['pending']++;
					$observations = 'Aún no registra entrada (turno pendiente de inicio)';
				}
			}

			// Aplicar filtros de estado y búsqueda
			// El KPI "Presentes" incluye tardanzas: el filtro 'presente' debe mostrar las mismas filas
			$passesStatus = empty($statusFilter) || $statusFilter === 'all' || ($status === $statusFilter)
				|| ($statusFilter === 'presente' && $status === 'tardanza');
			$passesSearch = empty($search) || (stripos($empName, $search) !== false || stripos($code, $search) !== false || stripos($dept, $search) !== false);

			if ($passesStatus && $passesSearch)
			{
				// Generar iniciales para avatar
				$nameParts = explode(' ', trim($empName));
				$initials = (count($nameParts) >= 2) 
					? (mb_substr($nameParts[0], 0, 1) . mb_substr($nameParts[1], 0, 1))
					: mb_substr($empName, 0, 2);

				$records[] = [
					'attendance_id' => $r['attendance_id'] ? intval($r['attendance_id']) : null,
					'employee_id' => intval($r['employee_id']),
					'shift_id' => intval($r['shift_id']),
					'payroll_code' => $code,
					'name' => $empName,
					'initials' => strtoupper($initials),
					'avatar_url' => $avatars[$code] ?? null,
					'avatar_color' => $this->NameToColor($empName),
					'department' => $normDept,
					'position' => $r['position'] ?: 'Colaborador',
					'shift_text' => sprintf('%s – %s', $progStart, $progEnd),
					'start_time' => $progStart,
					'end_time' => $progEnd,
					'break_minutes' => $breakMins,
					'clock_in' => $clockIn ?: '—',
					'clock_out' => $clockOut ?: '—',
					'diff_minutes' => $diffMinutes,
					'hours_worked' => $hoursWorked,
					'status' => $status,
					'observations' => $observations,
					'adjustment_status' => $r['adjustment_status'],
					'can_clock_out' => ($r['clock_in'] && !$r['clock_out']),
					'can_adjust' => true
				];
			}
		}

		// Porcentaje global de asistencia
		$attendancePct = ($scheduledCount > 0) ? round(($presentCount / $scheduledCount) * 100, 1) : 0.0;

		// Formatear departamentos para las tarjetas
		$cards = [];
		foreach ($deptStats as $dName => $ds)
		{
			// Si tiene colaboradores o si es uno de los departamentos principales
			if ($ds['scheduled'] > 0 || in_array($dName, ['Electrónica', 'Dairy / Lácteos', 'Frutas y Verduras', 'Cajas y Atención al Cliente', 'Bodega y Despachos']))
			{
				$sched = max(1, $ds['scheduled']);
				$pres = $ds['present'];
				$covPct = round(($pres / $sched) * 100);

				// Señal de subcobertura si es menor al 70% o si faltan más de 1 persona
				$isUnderMin = ($covPct < 75.0) || ($ds['absent'] >= 2);

				// Nombre simplificado para el diseño de tarjeta
				$shortName = str_replace(['Dairy / ', ' y Atención al Cliente', ' y Despachos', ' / Bakery', ' y Pollo'], '', $dName);

				$cards[] = [
					'raw_name' => $dName,
					'short_name' => $shortName,
					'scheduled' => $ds['scheduled'],
					'present' => $ds['present'],
					'late' => $ds['late'],
					'absent' => $ds['absent'],
					'pending' => $ds['pending'],
					'coverage_pct' => $covPct,
					'is_under_min' => $isUnderMin,
					'image' => $ds['image']
				];
			}
		}

		// Alertas del día
		$alertsStmt = $db->prepare("SELECT * FROM dairy_alerts WHERE branch_id = :bid AND category = 'workforce' AND alert_type = 'attendance' ORDER BY CASE severity WHEN 'danger' THEN 1 WHEN 'warning' THEN 2 ELSE 3 END, id DESC LIMIT 10");
		$alertsStmt->execute([':bid' => $resolvedBranchId]);
		$alerts = $alertsStmt->fetchAll(\PDO::FETCH_ASSOC);

		// Si no hay alertas en base de datos, proveer las alertas estándar demostrativas
		if (empty($alerts))
		{
			$alerts = [
				[
					'id' => 101,
					'severity' => 'danger',
					'title' => 'Jorge Ramírez',
					'detail' => 'Ausencia sin justificar',
					'time' => '08:00',
					'department' => 'Bodega'
				],
				[
					'id' => 102,
					'severity' => 'danger',
					'title' => 'Daniel Castro',
					'detail' => 'Ausencia justificada',
					'time' => '08:00',
					'department' => 'Bodega'
				],
				[
					'id' => 103,
					'severity' => 'warning',
					'title' => 'Luis Mendoza',
					'detail' => 'Tardanza de 15 minutos',
					'time' => '08:15',
					'department' => 'Frutas y verduras'
				],
				[
					'id' => 104,
					'severity' => 'warning',
					'title' => 'Valeria Sánchez',
					'detail' => 'Tardanza de 5 minutos',
					'time' => '10:05',
					'department' => 'Electrónica'
				],
				[
					'id' => 105,
					'severity' => 'secondary',
					'title' => 'Carlos Moya',
					'detail' => 'Aún no registra su entrada',
					'time' => '10:00',
					'department' => 'Electrónica'
				]
			];
		}
		else
		{
			// Adaptar alertas de BD al formato de la UI
			$adaptedAlerts = [];
			foreach ($alerts as $al)
			{
				$parts = explode(':', $al['title']);
				$title = count($parts) > 1 ? trim($parts[1]) : $al['title'];
				$detail = count($parts) > 1 ? trim($parts[0]) : $al['detail'];
				$time = date('H:i', strtotime($al['created_at'] ?? '08:00:00'));

				$adaptedAlerts[] = [
					'id' => intval($al['id']),
					'severity' => $al['severity'],
					'title' => $title,
					'detail' => $detail,
					'time' => $time,
					'department' => $al['department'] ?? 'General'
				];
			}
			$alerts = $adaptedAlerts;
		}

		// Preparar datos para el gráfico apilado (Chart.js)
		$chartLabels = [];
		$chartPresent = [];
		$chartLate = [];
		$chartAbsent = [];
		$chartPending = [];

		foreach ($cards as $c)
		{
			$chartLabels[] = $c['short_name'];
			// En la barra apilada: Presentes estrictos (puntuales) + Tardanzas + Ausencias + Pendientes
			$strictlyPunctual = max(0, $c['present'] - $c['late']);
			$chartPresent[] = $strictlyPunctual;
			$chartLate[] = $c['late'];
			$chartAbsent[] = $c['absent'];
			$chartPending[] = $c['pending'];
		}

		// Cobertura por franja horaria (mañana, mediodía, tarde, noche)
		$slotDefs = [
			['06:00', '08:00', '06:00 - 08:00 (Apertura)'],
			['08:00', '10:00', '08:00 - 10:00 (Mañana temprana)'],
			['10:00', '12:00', '10:00 - 12:00 (Pico matutino)'],
			['12:00', '14:00', '12:00 - 14:00 (Almuerzo / Rotación)'],
			['14:00', '16:00', '14:00 - 16:00 (Tarde temprana)'],
			['16:00', '18:00', '16:00 - 18:00 (Pico vespertino)'],
			['18:00', '20:00', '18:00 - 20:00 (Tarde noche)'],
			['20:00', '22:00', '20:00 - 22:00 (Cierre de tienda)']
		];
		$hourlyCoverage = [];
		foreach ($slotDefs as [$sStart, $sEnd, $sLabel])
		{
			$sched = 0;
			$pres = 0;
			foreach ($rawRows as $r)
			{
				if ($r['start_time'] < $sEnd && $r['end_time'] > $sStart)
				{
					$sched++;
					if (!empty($r['clock_in']))
					{
						$inTime = date('H:i', strtotime($r['clock_in']));
						$outTime = !empty($r['clock_out']) ? date('H:i', strtotime($r['clock_out'])) : '23:59';
						if ($inTime < $sEnd && $outTime > $sStart)
						{
							$pres++;
						}
					}
				}
			}
			$pct = $sched > 0 ? round(($pres / $sched) * 100) : 100;
			$hourlyCoverage[] = [
				'slot' => $sLabel,
				'start' => $sStart,
				'end' => $sEnd,
				'scheduled' => $sched,
				'present' => $pres,
				'coverage_pct' => $pct,
				'status' => $pct >= 90 ? 'optimo' : ($pct >= 70 ? 'adecuado' : 'deficit')
			];
		}

		// Solicitudes de ajuste pendientes de revisión por supervisor
		$pendingAdjustments = $db->query("
			SELECT a.*, e.name AS employee_name, e.payroll_code, e.department, s.shift_date, s.start_time, s.end_time
			FROM employee_attendance_adjustments a
			JOIN employees e ON e.id = a.employee_id
			LEFT JOIN employee_shifts s ON s.id = a.shift_id
			WHERE e.branch_id = $resolvedBranchId AND a.status = 'requested'
			ORDER BY a.id DESC
		")->fetchAll(\PDO::FETCH_ASSOC);

		// Si no hay ajustes pendientes en DEMO, creamos uno de ejemplo si aún no existe
		if (empty($pendingAdjustments) && $isDemo)
		{
			$checkDemoAdj = $db->query("SELECT id FROM employee_attendance_adjustments WHERE employee_id IN (SELECT id FROM employees WHERE branch_id = $resolvedBranchId) AND status = 'requested'")->fetchColumn();
			if (!$checkDemoAdj)
			{
				$demoEmp = $db->query("SELECT id FROM employees WHERE branch_id = $resolvedBranchId LIMIT 1")->fetchColumn() ?: $db->query("SELECT id FROM employees LIMIT 1")->fetchColumn();
				if ($demoEmp)
				{
					$db->exec("INSERT INTO employee_attendance_adjustments (employee_id, original_clock_in, new_clock_in, reason, status, requested_by_user_id, row_created_timestamp)
						VALUES ($demoEmp, '$date 08:15:00', '$date 08:00:00', 'El lector biométrico no registró la huella al primer intento por descalibración del sensor', 'requested', 1, datetime('now'))");
					$pendingAdjustments = $db->query("
						SELECT a.*, e.name AS employee_name, e.payroll_code, e.department, s.shift_date, s.start_time, s.end_time
						FROM employee_attendance_adjustments a
						JOIN employees e ON e.id = a.employee_id
						LEFT JOIN employee_shifts s ON s.id = a.shift_id
						WHERE a.id = " . $db->lastInsertId()
					)->fetchAll(\PDO::FETCH_ASSOC);
				}
			}
		}

		// Tres situaciones críticas del día para supervisor
		$supervisorHighlights = [
			[
				'type' => 'danger',
				'icon' => 'fa-user-xmark',
				'title' => '2 Ausencias reportadas',
				'detail' => 'Nicolás Torres (sin justificar en Electrónica) y Gabriel Castro (con certificado médico en Lácteos).',
				'action_label' => 'Gestionar en detalle',
				'action_tab' => 'records'
			],
			[
				'type' => 'warning',
				'icon' => 'fa-clock',
				'title' => '2 Tardanzas operativas',
				'detail' => 'Diego Vargas (+15 min en Frutas/Verduras) y Camila Soto (+5 min en Electrónica).',
				'action_label' => 'Ver marcaciones',
				'action_tab' => 'records'
			],
			[
				'type' => 'info',
				'icon' => 'fa-clock-rotate-left',
				'title' => count($pendingAdjustments) . ' Corrección(es) por revisar',
				'detail' => count($pendingAdjustments) > 0 ? 'Solicitud de ajuste pendiente de aprobación para validar marcas horarias.' : '1 entrada pendiente por registrar en el turno activo.',
				'action_label' => 'Revisar solicitudes',
				'action_tab' => 'adjustments'
			]
		];

		return [
			'branch_id' => $resolvedBranchId,
			'branch_name' => ($db->query("SELECT name FROM branches WHERE id = $resolvedBranchId")->fetchColumn()) ?: 'Doral',
			'branches' => $branches,
			'departments' => $deptList,
			'selected_date' => $date,
			'selected_dept' => $department ?: 'all',
			'selected_status' => $statusFilter ?: 'all',
			'search' => $search ?: '',
			'is_demo' => $isDemo,
			'last_updated' => date('d/m/Y H:i'),
			'kpis' => [
				'scheduled_count' => $scheduledCount,
				'present_count' => $presentCount,
				'late_count' => $lateCount,
				'absent_count' => $absentCount,
				'pending_count' => $pendingCount,
				'attendance_pct' => $attendancePct,
				'early_departures' => $earlyDepartures,
				'total_worked_hours' => $totalWorkedHours
			],
			'dept_cards' => $cards,
			'records' => $records,
			'alerts' => $alerts,
			'hourly_coverage' => $hourlyCoverage,
			'pending_adjustments' => $pendingAdjustments,
			'supervisor_highlights' => $supervisorHighlights,
			'chart_data' => [
				'labels' => $chartLabels,
				'present' => $chartPresent,
				'late' => $chartLate,
				'absent' => $chartAbsent,
				'pending' => $chartPending
			]
		];
	}

	/**
	 * Registra una entrada, salida o novedad manual desde el formulario del modal
	 */
	public function RecordManualAttendance(array $data, int $userId): array
	{
		$db = $this->Db();
		$empId = intval($data['employee_id'] ?? 0);
		if ($empId <= 0)
		{
			throw new \Exception('Debe seleccionar un empleado.');
		}

		$date = !empty($data['date']) ? strval($data['date']) : date('Y-m-d');
		$time = !empty($data['time']) ? strval($data['time']) : date('H:i:s');
		$type = strval($data['type'] ?? 'clock_in'); // clock_in, clock_out, absence
		$notes = trim(strval($data['notes'] ?? ''));

		if ($notes === '')
		{
			throw new \Exception('Debe especificar un motivo u observación.');
		}

		// Encontrar turno correspondiente
		$shiftStmt = $db->prepare("SELECT * FROM employee_shifts WHERE employee_id = :e AND shift_date = :d LIMIT 1");
		$shiftStmt->execute([':e' => $empId, ':d' => $date]);
		$shift = $shiftStmt->fetch(\PDO::FETCH_ASSOC);
		$shiftId = $shift ? intval($shift['id']) : null;

		$fullDateTime = "$date $time";

		if ($type === 'clock_in')
		{
			// Evitar sobrescrituras silenciosas o fichajes duplicados en la misma ventana horaria
			$openCheck = $db->prepare("SELECT id, clock_in FROM employee_attendance WHERE employee_id = :e AND clock_out IS NULL ORDER BY id DESC LIMIT 1");
			$openCheck->execute([':e' => $empId]);
			$openRow = $openCheck->fetch(\PDO::FETCH_ASSOC);
			if ($openRow)
			{
				throw new \Exception("El colaborador ya tiene una entrada abierta desde {$openRow['clock_in']}; registre la salida o solicite una corrección.");
			}

			$dupCheck = $db->prepare("SELECT id, clock_in, clock_out FROM employee_attendance
				WHERE employee_id = :e AND date(clock_in) = :d
				  AND (:ci <= IFNULL(clock_out, datetime(clock_in, '+12 hours')) AND datetime(:ci, '+1 minute') >= clock_in)");
			$dupCheck->execute([':e' => $empId, ':d' => $date, ':ci' => $fullDateTime]);
			$existingRow = $dupCheck->fetch(\PDO::FETCH_ASSOC);
			if ($existingRow)
			{
				throw new \Exception("Ya existe un fichaje registrado en esa fecha ({$existingRow['clock_in']}); use una solicitud de corrección auditada.");
			}

			$ins = $db->prepare("INSERT INTO employee_attendance (employee_id, shift_id, clock_in, clock_out, source, notes, recorded_by_user_id)
				VALUES (:e, :s, :ci, NULL, 'manual', :n, :u)");
			$ins->execute([':e' => $empId, ':s' => $shiftId, ':ci' => $fullDateTime, ':n' => $notes, ':u' => $userId]);

			return ['success' => true, 'message' => "Entrada registrada correctamente para las $time."];
		}
		elseif ($type === 'clock_out')
		{
			// Buscar primero sesión abierta (incluyendo turno nocturno iniciado el día anterior)
			$openCheck = $db->prepare("SELECT id, clock_in FROM employee_attendance
				WHERE employee_id = :e AND clock_out IS NULL AND date(clock_in) BETWEEN date(:d, '-1 day') AND :d
				ORDER BY clock_in DESC LIMIT 1");
			$openCheck->execute([':e' => $empId, ':d' => $date]);
			$openRow = $openCheck->fetch(\PDO::FETCH_ASSOC);

			if ($openRow)
			{
				if ($fullDateTime <= $openRow['clock_in'])
				{
					throw new \Exception("La hora de salida ($fullDateTime) debe ser posterior a la entrada ({$openRow['clock_in']}).");
				}
				$db->prepare("UPDATE employee_attendance SET clock_out = :co, notes = TRIM(IFNULL(notes, '') || ' · Salida: ' || :n) WHERE id = :id")
					->execute([':co' => $fullDateTime, ':n' => $notes, ':id' => $openRow['id']]);
			}
			else
			{
				$existingClosed = $db->prepare("SELECT id, clock_in, clock_out FROM employee_attendance WHERE employee_id = :e AND date(clock_in) = :d LIMIT 1");
				$existingClosed->execute([':e' => $empId, ':d' => $date]);
				if ($existingClosed->fetch(\PDO::FETCH_ASSOC))
				{
					throw new \Exception("El fichaje de esa jornada ya está cerrado; use una corrección auditada para editar horas.");
				}
				$estIn = $shift ? "$date {$shift['start_time']}" : "$date 08:00:00";
				if (strlen($estIn) === 16) $estIn .= ':00';
				if ($fullDateTime <= $estIn)
				{
					throw new \Exception("No se encontró entrada previa y la salida ($fullDateTime) es anterior al inicio estimado ($estIn).");
				}
				$ins = $db->prepare("INSERT INTO employee_attendance (employee_id, shift_id, clock_in, clock_out, source, notes, recorded_by_user_id)
					VALUES (:e, :s, :ci, :co, 'manual', :n, :u)");
				$ins->execute([':e' => $empId, ':s' => $shiftId, ':ci' => $estIn, ':co' => $fullDateTime, ':n' => $notes, ':u' => $userId]);
			}

			return ['success' => true, 'message' => "Salida registrada correctamente para las $time."];
		}
		elseif ($type === 'absence')
		{
			$absType = strval($data['absence_type'] ?? 'permission');
			$ins = $db->prepare("INSERT INTO employee_absences (employee_id, absence_type, start_date, end_date, status, notes, requested_by_user_id, decided_by_user_id, decided_timestamp, row_created_timestamp)
				VALUES (:e, :at, :sd, :ed, :st, :n, :u, :u, datetime('now'), datetime('now'))");
			$ins->execute([
				':e' => $empId,
				':at' => $absType,
				':st' => $absType === 'unjustified' ? 'pending' : 'approved', // injustificada ≠ justificada

				':sd' => $date,
				':ed' => $date,
				':n' => $notes,
				':u' => $userId
			]);

			return ['success' => true, 'message' => "Novedad/Ausencia registrada correctamente."];
		}

		throw new \Exception('Tipo de registro no reconocido.');
	}

	/**
	 * Solicita una corrección de fichaje para aprobación por supervisor/manager
	 */
	public function RequestAdjustment(array $data, int $userId): array
	{
		$db = $this->Db();
		$empId = intval($data['employee_id'] ?? 0);
		$attId = !empty($data['attendance_id']) ? intval($data['attendance_id']) : null;
		$newClockIn = !empty($data['new_clock_in']) ? strval($data['new_clock_in']) : null;
		$newClockOut = !empty($data['new_clock_out']) ? strval($data['new_clock_out']) : null;
		$reason = trim(strval($data['reason'] ?? ''));

		if ($reason === '')
		{
			throw new \Exception('Debe detallar el motivo de la corrección.');
		}

		// Obtener valores originales si existe fichaje
		$origIn = null;
		$origOut = null;
		$shiftId = null;

		if ($attId)
		{
			$origStmt = $db->prepare("SELECT * FROM employee_attendance WHERE id = :id");
			$origStmt->execute([':id' => $attId]);
			$orig = $origStmt->fetch(\PDO::FETCH_ASSOC);
			if ($orig)
			{
				$origIn = $orig['clock_in'];
				$origOut = $orig['clock_out'];
				$shiftId = $orig['shift_id'];
			}
		}

		$ins = $db->prepare("INSERT INTO employee_attendance_adjustments (attendance_id, employee_id, shift_id, original_clock_in, original_clock_out, new_clock_in, new_clock_out, reason, status, requested_by_user_id, row_created_timestamp)
			VALUES (:a, :e, :s, :oi, :oo, :ni, :no, :r, 'requested', :u, datetime('now'))");
		$ins->execute([
			':a' => $attId,
			':e' => $empId,
			':s' => $shiftId,
			':oi' => $origIn,
			':oo' => $origOut,
			':ni' => $newClockIn,
			':no' => $newClockOut,
			':r' => $reason,
			':u' => $userId
		]);

		return [
			'success' => true,
			'adjustment_id' => intval($db->lastInsertId()),
			'message' => 'Solicitud de corrección enviada a supervisión.'
		];
	}

	/**
	 * Decide una corrección (aprobar o rechazar)
	 */
	public function DecideAdjustment(int $id, bool $approve, int $userId, ?string $notes = null): array
	{
		$svc = WorkforceScheduleService::GetInstance();
		return $svc->DecideAttendanceAdjustment($id, $approve, $userId, $notes);
	}

	/**
	 * Cierre masivo de salidas para turnos vencidos del día
	 */
	public function QuickCheckoutAll(int $branchId, string $date, int $userId): array
	{
		$db = $this->Db();
		// Buscar todas las asistencias abiertas del día cuyo turno ya finalizó
		$sql = "
			SELECT a.id, a.employee_id, s.end_time
			FROM employee_attendance a
			JOIN employee_shifts s ON s.id = a.shift_id
			WHERE s.branch_id = :b AND s.shift_date = :d AND a.clock_out IS NULL
		";
		$stmt = $db->prepare($sql);
		$stmt->execute([':b' => $branchId, ':d' => $date]);
		$openShifts = $stmt->fetchAll(\PDO::FETCH_ASSOC);

		$closedCount = 0;
		foreach ($openShifts as $os)
		{
			$clockOutTime = "$date {$os['end_time']}";
			$db->prepare("UPDATE employee_attendance SET clock_out = :co, notes = IFNULL(notes, '') || ' · Salida masiva fin de jornada' WHERE id = :id")
				->execute([':co' => $clockOutTime, ':id' => $os['id']]);
			$closedCount++;
		}

		return [
			'success' => true,
			'closed_count' => $closedCount,
			'message' => "Se registraron $closedCount salidas automáticas de fin de jornada."
		];
	}

	/**
	 * Restaura únicamente los datos demostrativos de asistencia
	 */
	/**
	 * Proceso F (personal y cobertura): cada turno publicado sin presentarse (sin fichaje 30 min después de su inicio)
	 * o cubierto por una ausencia aprobada genera una tarea de sustitución en Tareas, ligada a la sucursal y al turno
	 * (una sola vez por turno). Las de días ya cerrados quedan como resueltas con la fecha del turno.
	 */
	public function SyncCoverageTasks(?int $branchId, string $date): array
	{
		$db = $this->Db();
		$today = date('Y-m-d');
		if ($date > $today)
		{
			return ['created' => 0];
		}
		// La carga de la empresa de ejemplo (migración 0300) corre antes de la 0304, que agrega estas columnas
		$cols = array_column($db->query('PRAGMA table_info(tasks)')->fetchAll(\PDO::FETCH_ASSOC), 'name');
		foreach (['branch_id' => 'INTEGER', 'source_ref' => 'TEXT'] as $c => $t)
		{
			if (!in_array($c, $cols, true))
			{
				$db->exec("ALTER TABLE tasks ADD COLUMN $c $t");
			}
		}
		$cut = $date < $today ? '24:00:00' : date('H:i:s', time() - 30 * 60);
		$b = $branchId ? ' AND s.branch_id = ' . intval($branchId) : '';
		$rows = $db->query("SELECT s.id, s.branch_id, s.start_time, s.end_time, e.name, e.department,
				(SELECT a.absence_type FROM employee_absences a WHERE a.employee_id = s.employee_id AND a.status = 'approved' AND '$date' BETWEEN a.start_date AND a.end_date LIMIT 1) AS absence
			FROM employee_shifts s JOIN employees e ON e.id = s.employee_id
			WHERE s.shift_date = '$date' AND s.status = 'published' AND s.start_time <= '$cut'$b
				AND NOT EXISTS (SELECT 1 FROM employee_attendance t WHERE t.shift_id = s.id)
				AND NOT EXISTS (SELECT 1 FROM tasks k WHERE k.source_ref = 'cover:shift:' || s.id)")->fetchAll(\PDO::FETCH_ASSOC);
		$ins = $db->prepare('INSERT INTO tasks (name, description, due_date, done, done_timestamp, branch_id, source_ref) VALUES (?, ?, ?, ?, ?, ?, ?)');
		foreach ($rows as $r)
		{
			$hours = substr($r['start_time'], 0, 5) . '–' . substr($r['end_time'], 0, 5);
			$why = $r['absence'] ? 'ausencia aprobada (' . $r['absence'] . ')' : 'no se presentó (sin fichaje)';
			$closed = $date < $today;
			$ins->execute(["Cubrir turno $hours de {$r['name']}", "Turno de {$r['department']} el $date: $why. Asignar reemplazo o publicar como turno abierto.",
				"$date {$r['start_time']}", $closed ? 1 : 0, $closed ? "$date {$r['end_time']}" : null, $r['branch_id'], 'cover:shift:' . $r['id']]);
		}
		return ['created' => count($rows)];
	}

	public function ResetDemoAttendance(int $branchId, string $date): array
	{
		// Los fichajes son registros reales de la operación: no se borran para reponer un guion
		return [
			'success' => false,
			'message' => 'La asistencia se registra con los fichajes; corrija un registro con un ajuste de asistencia.'
		];
	}

	/**
	 * Inicia descanso/pausa para un colaborador con entrada abierta
	 */
	public function StartBreak(int $employeeId, int $userId): array
	{
		$db = $this->Db();
		$open = $db->query("SELECT * FROM employee_attendance WHERE employee_id = $employeeId AND clock_out IS NULL ORDER BY clock_in DESC LIMIT 1")->fetch(\PDO::FETCH_ASSOC);
		if (!$open)
		{
			throw new \Exception('No hay una jornada iniciada para este colaborador.');
		}
		if (!empty($open['break_start']) && empty($open['break_end']))
		{
			throw new \Exception('El colaborador ya se encuentra en período de descanso.');
		}
		if (!empty($open['break_end']))
		{
			throw new \Exception('El descanso ya fue tomado y finalizado en esta jornada.');
		}

		$now = date('Y-m-d H:i:s');
		$db->prepare("UPDATE employee_attendance SET break_start = :bs, notes = IFNULL(notes, '') || ' · Descanso iniciado a las ' || :t WHERE id = :id")
			->execute([':bs' => $now, ':t' => date('H:i'), ':id' => $open['id']]);

		return [
			'success' => true,
			'attendance_id' => intval($open['id']),
			'break_start' => $now,
			'message' => 'Descanso iniciado a las ' . date('H:i') . '.'
		];
	}

	/**
	 * Finaliza descanso/pausa para un colaborador
	 */
	public function EndBreak(int $employeeId, int $userId): array
	{
		$db = $this->Db();
		$open = $db->query("SELECT * FROM employee_attendance WHERE employee_id = $employeeId AND clock_out IS NULL ORDER BY clock_in DESC LIMIT 1")->fetch(\PDO::FETCH_ASSOC);
		if (!$open)
		{
			throw new \Exception('No hay una jornada iniciada para este colaborador.');
		}
		if (empty($open['break_start']))
		{
			throw new \Exception('No hay un descanso activo para finalizar.');
		}
		if (!empty($open['break_end']))
		{
			throw new \Exception('El descanso ya fue finalizado.');
		}

		$now = date('Y-m-d H:i:s');
		$durationMins = max(1, round((strtotime($now) - strtotime($open['break_start'])) / 60));
		$db->prepare("UPDATE employee_attendance SET break_end = :be, notes = IFNULL(notes, '') || ' · Descanso terminado a las ' || :t || ' (' || :m || ' min)' WHERE id = :id")
			->execute([':be' => $now, ':t' => date('H:i'), ':m' => $durationMins, ':id' => $open['id']]);

		return [
			'success' => true,
			'attendance_id' => intval($open['id']),
			'break_end' => $now,
			'break_minutes' => $durationMins,
			'message' => 'Descanso finalizado a las ' . date('H:i') . ' (' . $durationMins . ' min tomados).'
		];
	}

	/**
	 * Consulta los datos del Portal de Empleado (Reloj de marcación, turno del día, estado actual e historial)
	 */
	public function GetEmployeePortalData(int $employeeId, string $date): array
	{
		$db = $this->Db();

		$empStmt = $db->prepare("SELECT e.*, b.name AS branch_name FROM employees e LEFT JOIN branches b ON b.id = e.branch_id WHERE e.id = :id");
		$empStmt->execute([':id' => $employeeId]);
		$employee = $empStmt->fetch(\PDO::FETCH_ASSOC);

		if (!$employee)
		{
			$employee = $db->query("SELECT e.*, b.name AS branch_name FROM employees e LEFT JOIN branches b ON b.id = e.branch_id WHERE e.active = 1 ORDER BY e.id ASC LIMIT 1")->fetch(\PDO::FETCH_ASSOC);
			if ($employee)
			{
				$employeeId = intval($employee['id']);
			}
			else
			{
				throw new \Exception('No se encontraron colaboradores activos en el sistema.');
			}
		}

		// Turno programado para hoy o la fecha dada
		$shiftStmt = $db->prepare("SELECT * FROM employee_shifts WHERE employee_id = :e AND shift_date = :d AND status != 'cancelled' LIMIT 1");
		$shiftStmt->execute([':e' => $employeeId, ':d' => $date]);
		$shift = $shiftStmt->fetch(\PDO::FETCH_ASSOC);

		// Si no hay turno en esta fecha en DEMO, buscar en fecha base DEMO 2026-11-04
		if (!$shift)
		{
			$shiftStmt->execute([':e' => $employeeId, ':d' => '2026-11-04']);
			$shift = $shiftStmt->fetch(\PDO::FETCH_ASSOC);
		}

		// Asistencia de hoy o abierta
		$attStmt = $db->prepare("SELECT * FROM employee_attendance WHERE employee_id = :e AND (shift_id = :s OR date(clock_in) = :d) ORDER BY id DESC LIMIT 1");
		$attStmt->execute([':e' => $employeeId, ':s' => $shift['id'] ?? 0, ':d' => $date]);
		$att = $attStmt->fetch(\PDO::FETCH_ASSOC);

		// Estado actual del colaborador
		$state = 'not_started'; // not_started, clocked_in, on_break, completed, absent, no_shift
		$stateLabel = 'Fuera de turno / No iniciado';
		$stateBadgeClass = 'badge-secondary';

		if (!$shift)
		{
			$state = 'no_shift';
			$stateLabel = 'Sin turno programado hoy';
			$stateBadgeClass = 'badge-light';
		}
		elseif ($att)
		{
			if ($att['clock_out'])
			{
				$state = 'completed';
				$stateLabel = 'Jornada completada';
				$stateBadgeClass = 'badge-info';
			}
			elseif (!empty($att['break_start']) && empty($att['break_end']))
			{
				$state = 'on_break';
				$stateLabel = 'En pausa / Descanso';
				$stateBadgeClass = 'badge-warning';
			}
			else
			{
				$state = 'clocked_in';
				$stateLabel = 'En turno activo';
				$stateBadgeClass = 'badge-success';
			}
		}

		// Botones habilitados según secuencia estricta:
		$canClockIn = ($state === 'not_started' || $state === 'no_shift');
		$canStartBreak = ($state === 'clocked_in' && empty($att['break_start']));
		$canEndBreak = ($state === 'on_break');
		$canClockOut = ($state === 'clocked_in');

		// Historial de fichajes de los últimos 7 días
		$histStmt = $db->prepare("
			SELECT a.*, s.shift_date, s.start_time, s.end_time, s.department as shift_dept,
				(SELECT id FROM employee_attendance_adjustments x WHERE x.attendance_id = a.id AND x.status = 'requested') as pending_adj_id,
				(SELECT status FROM employee_attendance_adjustments x WHERE x.attendance_id = a.id ORDER BY x.id DESC LIMIT 1) as last_adj_status
			FROM employee_attendance a
			LEFT JOIN employee_shifts s ON s.id = a.shift_id
			WHERE a.employee_id = :e
			ORDER BY a.clock_in DESC
			LIMIT 7
		");
		$histStmt->execute([':e' => $employeeId]);
		$history = $histStmt->fetchAll(\PDO::FETCH_ASSOC);

		// Solicitudes de ajuste de este colaborador
		$adjStmt = $db->prepare("SELECT * FROM employee_attendance_adjustments WHERE employee_id = :e ORDER BY id DESC LIMIT 5");
		$adjStmt->execute([':e' => $employeeId]);
		$adjustments = $adjStmt->fetchAll(\PDO::FETCH_ASSOC);

		return [
			'employee' => $employee,
			'shift' => $shift,
			'attendance' => $att,
			'state' => $state,
			'state_label' => $stateLabel,
			'state_badge_class' => $stateBadgeClass,
			'can_clock_in' => $canClockIn,
			'can_start_break' => $canStartBreak,
			'can_end_break' => $canEndBreak,
			'can_clock_out' => $canClockOut,
			'history' => $history,
			'adjustments' => $adjustments
		];
	}
}
