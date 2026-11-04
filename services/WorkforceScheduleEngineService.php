<?php

namespace Grocy\Services;

/**
 * DAILY — Motor de programación mensual (Operaciones y Personal → Horarios y Cobertura).
 *
 * Orden de trabajo: 1) analiza el historial real, 2) calcula la rotación de cada persona, 3) propone el mes como BORRADOR.
 * El borrador nunca toca la programación oficial: solo Approve() la reemplaza, y un mes publicado exige Reopen() con motivo.
 * Prioridad de reglas: legales y de disponibilidad (ausencias, 3×1, descanso entre turnos, horas, compensatorios)
 * por encima de la rotación apertura/cierre y de la cobertura. Lo que no se puede cubrir se reporta; nunca se inventa personal.
 */
class WorkforceScheduleEngineService extends BaseService
{
	const OFF_TYPES = ['LIBRE', 'COMPENSATORIO', 'VACACIONES', 'INCAPACIDAD', 'PERMISO'];
	const ABSENCE_TYPES = ['VACACIONES', 'INCAPACIDAD', 'PERMISO'];
	const FALLBACK = ['A' => '7A7-14', 'C' => '7C14-21'];
	const ABSENCE_CODES = ['vacation' => ['VC', 'VACACIONES'], 'sick' => ['INC', 'INCAPACIDAD'], 'permission' => ['PERM', 'PERMISO']];
	// La planilla real promedia 42 h con semanas de 35/49/42/42: el tope legal se mide en cuatro semanas y una semana suelta llega a 49 h
	const MAX_WEEK_PEAK = 49.0;
	const AVERAGE_WEEKS = 4;
	// La planilla (hoja Trasnocho y Festivos) rota el turno nocturno en bloques de 7 días
	const NIGHT_BLOCK_DAYS = 7;
	// Domingo libre de cada grupo de la hoja "Empleados dominical" en octubre de 2026
	const SHEET_FREE_SUNDAY = ['A' => '2026-10-18', 'B' => '2026-10-25', 'C' => '2026-10-04', 'D' => '2026-10-11'];
	// Solo Ventas y Operaciones hacen turno nocturno
	const NIGHT_DEPARTMENTS = '/ventas|operaci/i';
	// Mayor racha sin descanso de la planilla real: no es un límite legal, solo avisa de lo que se sale del patrón
	const LONG_RUN_DAYS = 11;
	// Semanas hacia atrás en las que se busca adelantar un domingo libre que una fecha especial prohíbe
	const ADVANCE_SUNDAYS = 3;
	// Secuencia por omisión: reparto parejo de lunes a viernes
	const WEEKDAYS = [1, 2, 3, 4, 5];
	// Bloqueantes: ley e incompatibilidades. Obligatorias: empresa, subordinadas a la ley. Optimizables: equidad y rotación
	const ISSUE_CLASS = ['legal' => 'BLOQUEANTE', 'coverage' => 'OBLIGATORIA', 'warn' => 'OPTIMIZABLE'];
	const ISSUE_HELP = [
		'overlap' => ['Dos filas para la misma persona y fecha', ['Eliminar uno de los turnos'], false],
		'missing_day' => ['La persona no tiene turno, libre ni ausencia ese día', ['Volver a generar el mes', 'Asignar el día en el calendario'], false],
		'sunday_3x1' => ['Se trabajaron más de tres domingos seguidos', ['Adelantar el domingo libre', 'Dar el libre el domingo siguiente con autorización'], true],
		'sunday_cycle' => ['El libre no coincide con el ciclo de cuatro semanas', ['Revisar la excepción', 'Devolver el libre a su domingo'], false],
		'absence_conflict' => ['Hay un turno dentro de una ausencia aprobada', ['Quitar el turno', 'Buscar reemplazo'], false],
		'comp_invalid_day' => ['La política solo admite lunes a viernes sin festivos', ['Mover el compensatorio a un día hábil'], false],
		'comp_duplicate' => ['Un domingo solo da derecho a un compensatorio', ['Quitar el compensatorio repetido'], false],
		'comp_overdue' => ['No hubo fecha válida en la semana siguiente al domingo', ['Elegir una fecha hábil', 'Decidir con gerencia la reprogramación'], true],
		'comp_late' => ['El compensatorio quedó después de la semana siguiente al domingo', ['Adelantarlo a la semana siguiente'], false],
		'min_rest' => ['El turno anterior termina muy cerca del siguiente', ['Cambiar a un turno más tarde', 'Dar el día libre'], false],
		'weekly_hours' => ['La semana supera el tope', ['Dar un libre esa semana', 'Reasignar un turno'], false],
		'average_hours' => ['El promedio supera la jornada', ['Dar un libre en la ventana', 'Reasignar turnos'], false],
		'night_cap' => ['Hay más personas de noche que las permitidas', ['Dejar una sola persona'], false],
		'night_unauthorized' => ['La persona no está autorizada para nocturno', ['Asignar a alguien autorizado de Ventas u Operaciones'], false],
		'no_night' => ['Nadie autorizado está disponible esa noche', ['Revisar autorizaciones', 'Mover descansos'], true],
		'no_coverage' => ['El área queda sin apertura o sin cierre', ['Buscar reemplazo', 'Pedir apoyo autorizado de otra área'], true],
		'special_date' => ['La fecha tiene una regla especial de la empresa', ['Mover el descanso a otra fecha válida'], true],
		'restriction_partial' => ['El turno está fuera de la disponibilidad autorizada', ['Ajustar el turno', 'Revisar la restricción'], false],
		'shift_not_allowed' => ['El código no está entre los turnos permitidos', ['Cambiar a un turno permitido'], false],
		'long_run' => ['Muchos días seguidos sin descanso', ['Adelantar el compensatorio o un libre'], false],
	];

	private static ?WorkforceScheduleEngineService $instance = null;

	public static function GetInstance(): WorkforceScheduleEngineService
	{
		if (self::$instance === null)
		{
			self::$instance = new self();
		}
		return self::$instance;
	}

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

	private function Range(int $year, int $month): array
	{
		$start = sprintf('%04d-%02d-01', $year, $month);
		return [$start, date('Y-m-t', strtotime($start)), substr($start, 0, 7)];
	}

	private static function IsOff(array $r): bool
	{
		return in_array($r['shift_type'], self::OFF_TYPES, true);
	}

	/** Horas de descanso entre el final de un turno y el inicio del siguiente */
	private static function RestHours(string $d1, array $a, string $d2, array $b): float
	{
		$end = strtotime("$d1 {$a['end_time']} UTC");
		if ($a['end_time'] < $a['start_time'])
		{
			$end += 86400;
		}
		return (strtotime("$d2 {$b['start_time']} UTC") - $end) / 3600;
	}

	/** Días entre dos fechas por calendario: sumar 86400 segundos repite o pierde días cuando la zona horaria cambia de hora */
	private static function Dates(string $from, string $to): array
	{
		$out = [];
		for ($d = $from; $d <= $to; $d = self::NextDay($d))
		{
			$out[] = $d;
		}
		return $out;
	}

	private static function NextDay(string $d): string
	{
		return gmdate('Y-m-d', strtotime("$d 12:00 UTC") + 86400);
	}

	public function Collaborators(int $branchId): array
	{
		return $this->Rows("SELECT c.payroll_code, IFNULL(c.full_name, c.name) AS name, c.department, c.cargo, c.sunday_group, c.reference_sunday, c.reference_sunday_phase, c.branch_id,
				c.contract_type, c.allowed_shifts, c.comp_sequence, c.comp_sequence_start, e.id AS employee_id, e.weekly_hours, IFNULL(s.can_night, 0) AS can_night, s.secondary_areas, IFNULL(s.authorized_cross_coverage, 0) AS cross_ok
			FROM workforce_planilla_collaborators c JOIN employees e ON e.payroll_code = c.payroll_code LEFT JOIN workforce_skills_matrix s ON s.payroll_code = c.payroll_code
			WHERE c.status = 'ACTIVO' AND e.active = 1 AND c.branch_id = :b ORDER BY c.department, c.name", [':b' => $branchId]);
	}

	private function Policy(int $branchId, string $date): array
	{
		$company = intval($this->Db()->query('SELECT company_id FROM branches WHERE id = ' . $branchId)->fetchColumn()) ?: 1;
		return WorkforceAutoScheduleService::GetInstance()->GetCompanyPolicy($company, $date);
	}

	private function SapCodes(): array
	{
		$out = [];
		foreach ($this->Rows("SELECT code, start_time, end_time, worked_hours, classification FROM workforce_sap_shifts WHERE IFNULL(active, 1) = 1 AND start_time LIKE '__:__'") as $r)
		{
			$out[$r['code']] = $r;
		}
		return $out;
	}

	private function Absences(array $collabs, string $from, string $to): array
	{
		$byEmp = array_column($collabs, 'payroll_code', 'employee_id');
		$out = [];
		$mark = function (string $code, string $s, string $e, string $absType) use (&$out, $from, $to) {
			foreach (self::Dates(max($s, $from), min($e, $to)) as $d)
			{
				$out[$code][$d] = self::ABSENCE_CODES[$absType];
			}
		};
		foreach ($this->Rows("SELECT payroll_code, start_date, end_date FROM workforce_vacations WHERE status = 'approved' AND start_date <= :t AND end_date >= :f", [':f' => $from, ':t' => $to]) as $v)
		{
			$mark($v['payroll_code'], $v['start_date'], $v['end_date'], 'vacation');
		}
		foreach ($this->Rows("SELECT employee_id, absence_type, start_date, end_date FROM employee_absences WHERE status = 'approved' AND start_date <= :t AND end_date >= :f", [':f' => $from, ':t' => $to]) as $a)
		{
			$code = $byEmp[$a['employee_id']] ?? null;
			if ($code !== null && isset(self::ABSENCE_CODES[$a['absence_type']]))
			{
				$mark($code, $a['start_date'], $a['end_date'], $a['absence_type']);
			}
		}
		foreach ($this->Rows("SELECT payroll_code, start_date, end_date FROM workforce_employee_restrictions WHERE restriction_type = 'TOTAL' AND active = 1 AND start_date <= :t AND end_date >= :f", [':f' => $from, ':t' => $to]) as $r)
		{
			$mark($r['payroll_code'], $r['start_date'], $r['end_date'], 'permission');
		}
		return $out;
	}

	/** Reglas vigentes por fecha: ['NO_COMPENSATORY' => [fecha => motivo], 'NO_SUNDAY_FREE' => [...]] */
	public function SpecialDates(int $branchId, string $from, string $to): array
	{
		$out = ['NO_COMPENSATORY' => [], 'NO_SUNDAY_FREE' => []];
		foreach ($this->Rows('SELECT special_date, rule_type, reason FROM workforce_schedule_special_dates WHERE active = 1 AND special_date BETWEEN :f AND :t AND (branch_id IS NULL OR branch_id = :b)', [':f' => $from, ':t' => $to, ':b' => $branchId]) as $r)
		{
			$out[$r['rule_type']][$r['special_date']] = $r['reason'];
		}
		return $out;
	}

	/** Restricciones PARCIAL y NOCTURNO por persona que se cruzan con el período */
	public function Restrictions(string $from, string $to): array
	{
		$out = [];
		foreach ($this->Rows("SELECT * FROM workforce_employee_restrictions WHERE active = 1 AND restriction_type <> 'TOTAL' AND start_date <= :t AND end_date >= :f", [':f' => $from, ':t' => $to]) as $r)
		{
			$out[$r['payroll_code']][] = $r;
		}
		return $out;
	}

	private static function ActiveRestriction(array $list, string $type, string $date): ?array
	{
		foreach ($list as $r)
		{
			if ($r['restriction_type'] === $type && $r['start_date'] <= $date && $r['end_date'] >= $date)
			{
				return $r;
			}
		}
		return null;
	}

	/** La regla de departamento manda siempre; una restricción NOCTURNO explícita prevalece sobre la matriz de habilidades */
	private static function NightAllowed(array $c, string $date, array $restrictions): bool
	{
		if (!preg_match(self::NIGHT_DEPARTMENTS, (string) $c['department']))
		{
			return false;
		}
		$r = self::ActiveRestriction($restrictions[$c['payroll_code']] ?? [], 'NOCTURNO', $date);
		return $r !== null ? $r['night_mode'] === 'ALLOW' : intval($c['can_night']) === 1;
	}

	/** Motivo por el que el turno no cabe en la disponibilidad PARCIAL; null si cabe */
	private static function PartialViolation(array $r, string $date, array $cell): ?string
	{
		$days = json_decode((string) $r['weekdays'], true) ?: [];
		if ($days && !in_array(intval(date('N', strtotime($date))), array_map('intval', $days), true))
		{
			return 'no disponible ese día';
		}
		if (!empty($r['window_start']) && $cell['start_time'] < $r['window_start'])
		{
			return "inicia antes de las {$r['window_start']}";
		}
		$overnight = $cell['end_time'] < $cell['start_time'];
		if (!empty($r['window_end']) && ($overnight || $cell['end_time'] > $r['window_end']))
		{
			return "termina después de las {$r['window_end']}";
		}
		return null;
	}

	/**
	 * Empareja compensatorios y domingos trabajados: primero los que traen su domingo de origen y luego, en orden, los de la planilla,
	 * que no lo traen. Devuelve los domingos sin compensatorio.
	 */
	public static function UnmatchedSundays(array $workedSundays, array $comps): array
	{
		$open = array_flip($workedSundays);
		$free = [];
		foreach ($comps as $c)
		{
			if (!empty($c['origin']) && isset($open[$c['origin']]))
			{
				unset($open[$c['origin']]);
			}
			else
			{
				$free[] = $c['date'];
			}
		}
		sort($free);
		foreach ($free as $date)
		{
			foreach (array_keys($open) as $sunday)
			{
				if ($sunday < $date)
				{
					unset($open[$sunday]);
					break;
				}
			}
		}
		return array_keys($open);
	}

	/**
	 * Paso 1: historial real antes del mes. Para cada persona deduce el último domingo libre, los domingos trabajados seguidos,
	 * la rotación de su última semana y sus códigos habituales. Conserva la secuencia entre meses.
	 */
	public function AnalyzeHistory(int $branchId, int $year, int $month): array
	{
		[$start] = $this->Range($year, $month);
		$collabs = $this->Collaborators($branchId);
		$from = date('Y-m-d', strtotime("$start -120 days"));
		$rows = [];
		foreach ($this->Rows("SELECT m.payroll_code, m.shift_date, m.shift_code, m.shift_type, m.start_time, m.end_time, m.hours, m.is_sunday, m.compensatory_origin_date FROM workforce_monthly_schedules m
			WHERE m.shift_date >= :f AND m.shift_date < :s ORDER BY m.shift_date", [':f' => $from, ':s' => $start]) as $r)
		{
			$rows[$r['payroll_code']][$r['shift_date']] = $r;
		}
		$out = [];
		foreach ($collabs as $c)
		{
			$code = $c['payroll_code'];
			$lastFree = null;
			$cycle = WorkforceLaborRulesService::NewCycle();
			$codes = ['A' => [], 'C' => []];
			$lastWeek = null;
			$lastDay = null;
			$workedSundays = [];
			$comps = [];
			$lastOpen = null;
			$lastClose = null;
			$lastNight = null;
			$nightRun = 0;
			$counts = ['openings' => 0, 'closings' => 0, 'nights' => 0, 'comps' => 0];
			$weekHours = [];
			$absentDays = [];
			foreach ($rows[$code] ?? [] as $d => $r)
			{
				$lastDay = $r;
				$dow = intval(date('N', strtotime($d)));
				$monday = date('Y-m-d', strtotime("$d -" . ($dow - 1) . ' days'));
				$weekHours[$monday] = ($weekHours[$monday] ?? 0) + (self::IsOff($r) ? 0 : floatval($r['hours']));
				if (intval($r['is_sunday']) === 1)
				{
					$cycle = WorkforceLaborRulesService::Step($cycle, $d, self::SundayState($r));
					if ($r['shift_type'] === 'LIBRE')
					{
						$lastFree = $d;
					}
					elseif (!self::IsOff($r))
					{
						$workedSundays[] = $d;
					}
				}
				if ($r['shift_type'] === 'COMPENSATORIO')
				{
					$comps[] = ['date' => $d, 'origin' => $r['compensatory_origin_date']];
					$counts['comps']++;
				}
				if (in_array($r['shift_type'], self::ABSENCE_TYPES, true))
				{
					$absentDays[] = ['date' => $d, 'type' => $r['shift_type']];
				}
				$nightRun = $r['shift_type'] === 'NOCHE' ? $nightRun + 1 : 0;
				if ($r['shift_type'] === 'NOCHE')
				{
					$lastNight = $d;
					$counts['nights']++;
				}
				elseif (!self::IsOff($r))
				{
					$k = $r['start_time'] < '12:00' ? 'A' : 'C';
					$codes[$k][$r['shift_code']] = ($codes[$k][$r['shift_code']] ?? 0) + 1;
					$k === 'A' ? ($lastOpen = $d) && $counts['openings']++ : ($lastClose = $d) && $counts['closings']++;
					if ($lastWeek === null || $lastWeek['monday'] !== $monday)
					{
						$lastWeek = ['monday' => $monday, 'A' => 0, 'C' => 0];
					}
					$lastWeek[$k]++;
				}
			}
			arsort($codes['A']);
			arsort($codes['C']);
			// Domingos sin compensatorio, aunque la persona se haya ido de vacaciones: el derecho sigue vivo hasta disfrutarlo
			$pendingComps = self::UnmatchedSundays($workedSundays, $comps);
			$out[$code] = ['last_free_sunday' => $lastFree, 'cycle' => $cycle, 'worked_streak' => $cycle['streak'], 'comp_dates' => array_column($comps, 'date'), 'last_worked_sundays' => array_slice($workedSundays, -3), 'codes' => ['A' => array_key_first($codes['A']), 'C' => array_key_first($codes['C'])],
				'rotation_anchor' => $lastWeek === null ? null : ['monday' => $lastWeek['monday'], 'rot' => $lastWeek['A'] >= $lastWeek['C'] ? 'A' : 'C'],
				'last_day' => $lastDay, 'has_history' => isset($rows[$code]), 'last_opening' => $lastOpen, 'last_closing' => $lastClose, 'last_night' => $lastNight, 'night_run' => $nightRun,
				'pending_comps' => $pendingComps, 'absent_days' => count($absentDays), 'week_hours' => $weekHours] + $counts;
		}
		return $out;
	}

	/** Pasos 2 y 3: calcula la rotación y deja la propuesta del mes como borrador, sin tocar la programación oficial */
	public function BuildDraft(int $branchId, int $year, int $month, int $userId): array
	{
		[$start, $end, $period] = $this->Range($year, $month);
		$collabs = $this->Collaborators($branchId);
		if (!$collabs)
		{
			throw new \Exception('La sucursal no tiene colaboradores activos en la planilla');
		}
		$policy = $this->Policy($branchId, $start);
		$history = $this->AnalyzeHistory($branchId, $year, $month);
		$sap = $this->SapCodes();
		$absences = $this->Absences($collabs, $start, $end);
		$holidays = [];
		$auto = WorkforceAutoScheduleService::GetInstance();
		foreach ($auto->GetColombianHolidays($year) as $h)
		{
			$holidays[$h['holiday_date']] = $h['name'];
		}
		$firstEditable = max($start, date('Y-m-d', strtotime('+1 day')));
		$days = self::Dates($start, $end);
		$special = $this->SpecialDates($branchId, $start, date('Y-m-d', strtotime("$end +7 days")));
		$restrictions = $this->Restrictions($start, $end);

		// Lo ya transcurrido se conserva tal cual: la propuesta solo cambia días futuros
		$fixed = [];
		foreach ($this->Rows("SELECT * FROM workforce_monthly_schedules WHERE shift_date BETWEEN :s AND :end AND (shift_date < :e OR source = 'planilla')", [':s' => $start, ':end' => $end, ':e' => $firstEditable]) as $r)
		{
			$fixed[$r['payroll_code']][$r['shift_date']] = $r;
		}
		$rotation = $this->StaggerRotation($collabs, $history, $days, $absences);

		$grid = [];
		$info = [];
		$sundaysFree = [];
		foreach ($collabs as $c)
		{
			$code = $c['payroll_code'];
			$h = $history[$code];
			$info[$code] = $c + ['partial' => isset($restrictions[$code]) ? 1 : 0, 'pref' => [
				'A' => $this->AllowedPref($c, 'A', $h['codes']['A'] ?: (WorkforceAutoScheduleService::DEPT_SHIFT_PREFS[$c['department']]['A'] ?? self::FALLBACK['A']), $sap),
				'C' => $this->AllowedPref($c, 'C', $h['codes']['C'] ?: (WorkforceAutoScheduleService::DEPT_SHIFT_PREFS[$c['department']]['C'] ?? self::FALLBACK['C']), $sap)]];
			$cycle = $h['cycle'];
			if ($cycle['anchor'] === null && !$h['has_history'])
			{
				$s = $auto->CalculateSundayStatus($code, $start);
				$cycle['anchor'] = date('Y-m-d', strtotime(date('Y-m-d', strtotime("sunday this week $start")) . ' -' . (intval($s['phase']) * 7) . ' days'));
			}
			foreach ($days as $d)
			{
				$dow = intval(date('w', strtotime($d)));
				$isSun = $dow === 0;
				$base = ['date' => $d, 'is_sunday' => $isSun ? 1 : 0, 'is_holiday' => isset($holidays[$d]) ? 1 : 0, 'origin' => null, 'notes' => null];
				if (isset($fixed[$code][$d]))
				{
					$f = $fixed[$code][$d];
					$grid[$code][$d] = $base + ['code' => $f['shift_code'], 'shift_type' => $f['shift_type'], 'start_time' => $f['start_time'], 'end_time' => $f['end_time'], 'hours' => floatval($f['hours']), 'locked' => 1];
					$grid[$code][$d]['origin'] = $f['compensatory_origin_date'];
					$grid[$code][$d]['notes'] = $f['notes'];
					if ($isSun)
					{
						$cycle = WorkforceLaborRulesService::Step($cycle, $d, self::SundayState($f));
					}
					continue;
				}
				if (isset($absences[$code][$d]))
				{
					[$ac, $at] = $absences[$code][$d];
					$grid[$code][$d] = $base + self::OffCell($ac, $at);
					if ($isSun)
					{
						$cycle = WorkforceLaborRulesService::Step($cycle, $d, 'ABSENT');
					}
					continue;
				}
				if ($isSun)
				{
					if (WorkforceLaborRulesService::IsFreeDue($cycle, $d))
					{
						$grid[$code][$d] = $base + self::OffCell('LIBRE', 'LIBRE');
						$grid[$code][$d]['notes'] = 'Descanso dominical 3×1';
						$cycle = WorkforceLaborRulesService::Step($cycle, $d, 'FREE');
						continue;
					}
					$cycle = WorkforceLaborRulesService::Step($cycle, $d, 'WORKED');
				}
				$grid[$code][$d] = $base + $this->WorkCell($info[$code]['pref'][$rotation[$code][self::Monday($d)]], $sap);
			}
		}

		$exceptions = $this->ApplySpecialSundays($grid, $info, $days, $special['NO_SUNDAY_FREE'], $sap, $rotation);
		$this->ApplyPartial($grid, $info, $days, $restrictions, $sap);
		$this->AssignNights($grid, $info, $days, $sap, $policy, $history, $restrictions);
		$pending = $this->AssignCompensatories($grid, $info, $days, $holidays, $start, $end, $history, $special['NO_COMPENSATORY']);
		$minRest = floatval($policy['min_rest_hours'] ?? 12);
		$this->EnforceWeeklyHours($grid, $info, $days, $policy, $history, $restrictions);
		$this->RepairRest($grid, $info, $days, $sap, $minRest, $history);
		$this->RepairCoverage($grid, $info, $days, $sap, $minRest, $history);

		$db = $this->Db();
		$db->beginTransaction();
		try
		{
			$db->prepare("UPDATE workforce_schedule_drafts SET status = 'discarded', decided_by_user_id = :u, decided_at = datetime('now', 'localtime') WHERE period = :p AND branch_id = :b AND status = 'draft'")
				->execute([':u' => $userId, ':p' => $period, ':b' => $branchId]);
			$version = 1 + intval($this->Rows('SELECT COUNT(*) AS n FROM workforce_schedule_drafts WHERE period = :p AND branch_id = :b', [':p' => $period, ':b' => $branchId])[0]['n']);
			$db->prepare('INSERT INTO workforce_schedule_drafts (period, branch_id, created_by_user_id, version) VALUES (:p, :b, :u, :v)')->execute([':p' => $period, ':b' => $branchId, ':u' => $userId, ':v' => $version]);
			$draftId = intval($db->lastInsertId());
			$this->Log($draftId, 'generated', "Versión $version generada automáticamente", $userId);
			$ins = $db->prepare('INSERT INTO workforce_schedule_draft_rows (draft_id, payroll_code, employee_id, shift_date, shift_code, shift_type, start_time, end_time, hours, is_sunday, is_holiday, compensatory_origin_date, notes)
				VALUES (:di, :c, :e, :d, :code, :type, :st, :et, :h, :sun, :hol, :o, :n)');
			foreach ($grid as $code => $row)
			{
				foreach ($row as $d => $x)
				{
					$ins->execute([':di' => $draftId, ':c' => $code, ':e' => $info[$code]['employee_id'], ':d' => $d, ':code' => $x['code'], ':type' => $x['shift_type'], ':st' => $x['start_time'], ':et' => $x['end_time'],
						':h' => $x['hours'], ':sun' => $x['is_sunday'], ':hol' => $x['is_holiday'], ':o' => $x['origin'], ':n' => $x['notes']]);
				}
			}
			$db->commit();
		}
		catch (\Throwable $e)
		{
			$db->rollBack();
			throw $e;
		}
		return $this->SaveReport($draftId, ['pending_compensatories' => $pending, 'sunday_exceptions' => $exceptions]);
	}

	private static function SundayState(array $r): string
	{
		return $r['shift_type'] === 'LIBRE' ? 'FREE' : (self::IsOff($r) ? 'ABSENT' : 'WORKED');
	}

	/** Si la persona tiene turnos permitidos, su código habitual solo vale si está en la lista */
	private function AllowedPref(array $c, string $kind, string $code, array $sap): string
	{
		$allowed = json_decode((string) $c['allowed_shifts'], true) ?: [];
		if (!$allowed || in_array($code, $allowed, true))
		{
			return $code;
		}
		foreach ($allowed as $a)
		{
			if (isset($sap[$a]) && ($sap[$a]['start_time'] < '12:00') === ($kind === 'A'))
			{
				return $a;
			}
		}
		return $allowed[0];
	}

	/**
	 * Una fecha sin libre dominical obliga a trabajar a quien descansaba: para no llegar a cuatro domingos seguidos, su libre se adelanta al
	 * domingo anterior más cercano que deje al área con al menos la mitad de su gente, y el ciclo sigue desde esa fecha. Si ninguno sirve el
	 * libre no se mueve y la validación muestra el conflicto: nunca se oculta.
	 */
	private function ApplySpecialSundays(array &$grid, array $info, array $days, array $blocked, array $sap, array $rotation): array
	{
		$exceptions = [];
		foreach ($blocked as $date => $reason)
		{
			foreach ($info as $code => $c)
			{
				$cell = $grid[$code][$date] ?? null;
				if ($cell === null || !empty($cell['locked']) || $cell['shift_type'] !== 'LIBRE')
				{
					continue;
				}
				$grid[$code][$date] = array_merge($cell, $this->WorkCell($c['pref'][$rotation[$code][self::Monday($date)]], $sap), ['notes' => 'Domingo trabajado por fecha especial']);
				$moved = null;
				$size = count(array_filter($info, fn($o) => $o['department'] === $c['department']));
				for ($n = 1; $n <= self::ADVANCE_SUNDAYS && $moved === null; $n++)
				{
					$s = date('Y-m-d', strtotime("$date -" . ($n * 7) . ' days'));
					$x = $grid[$code][$s] ?? null;
					if ($x === null || !empty($x['locked']) || self::IsOff($x) || isset($blocked[$s]))
					{
						continue;
					}
					$working = count(array_filter(array_keys($info), fn($o) => $info[$o]['department'] === $c['department'] && isset($grid[$o][$s]) && !self::IsOff($grid[$o][$s])));
					if ($working - 1 >= max(1, intval(ceil($size / 2))))
					{
						$moved = $s;
					}
				}
				if ($moved !== null)
				{
					$grid[$code][$moved] = array_merge($grid[$code][$moved], self::OffCell('LIBRE', 'LIBRE'), ['notes' => "Domingo libre adelantado desde $date: $reason"]);
					foreach ($days as $d)
					{
						$x = $grid[$code][$d];
						if ($d <= $date || !$x['is_sunday'] || !empty($x['locked']) || in_array($x['shift_type'], self::ABSENCE_TYPES, true))
						{
							continue;
						}
						$due = WorkforceLaborRulesService::WeeksBetween($moved, $d) % WorkforceLaborRulesService::CYCLE_WEEKS === 0;
						if ($due && !isset($blocked[$d]))
						{
							$grid[$code][$d] = array_merge($x, self::OffCell('LIBRE', 'LIBRE'), ['notes' => 'Descanso dominical 3×1']);
						}
						elseif (!$due && $x['shift_type'] === 'LIBRE')
						{
							$grid[$code][$d] = array_merge($x, $this->WorkCell($c['pref'][$rotation[$code][self::Monday($d)]], $sap), ['notes' => null]);
						}
					}
				}
				$exceptions[] = ['payroll_code' => $code, 'name' => $c['name'], 'department' => $c['department'], 'original_sunday' => $date, 'new_sunday' => $moved, 'reason' => $reason];
			}
		}
		return $exceptions;
	}

	/** Disponibilidad PARCIAL: fuera de sus días queda libre; fuera de su franja usa el primer código SAP de igual duración que quepa */
	private function ApplyPartial(array &$grid, array $info, array $days, array $restrictions, array $sap): void
	{
		foreach ($restrictions as $code => $list)
		{
			foreach ($days as $d)
			{
				$x = $grid[$code][$d] ?? null;
				$r = self::ActiveRestriction($list, 'PARCIAL', $d);
				if ($x === null || $r === null || !empty($x['locked']) || self::IsOff($x) || self::PartialViolation($r, $d, $x) === null)
				{
					continue;
				}
				$fix = null;
				foreach ($sap as $sc => $s)
				{
					$cand = $this->WorkCell($sc, $sap);
					if (in_array($s['classification'], ['APERTURA', 'INTERMEDIO', 'CIERRE'], true) && abs(floatval($s['worked_hours']) - $x['hours']) < 0.6 && self::PartialViolation($r, $d, $cand) === null)
					{
						$fix = $cand;
						break;
					}
				}
				$grid[$code][$d] = $fix !== null ? array_merge($x, $fix, ['notes' => 'Ajustado a la disponibilidad parcial'])
					: array_merge($x, $x['is_sunday'] ? self::OffCell('PERM', 'PERMISO') : self::OffCell('LIBRE', 'LIBRE'), ['notes' => 'Fuera de la disponibilidad parcial: ' . $r['reason']]);
			}
		}
	}

	private static function OffCell(string $code, string $type): array
	{
		return ['code' => $code, 'shift_type' => $type, 'start_time' => '00:00', 'end_time' => '00:00', 'hours' => $code === 'COMP' ? 7.0 : 0.0];
	}

	private function WorkCell(string $code, array $sap): array
	{
		$s = $sap[$code] ?? null;
		if ($s === null)
		{
			$k = WorkforceAutoScheduleService::SAP_SHIFTS[$code] ?? WorkforceAutoScheduleService::SAP_SHIFTS[self::FALLBACK['A']];
			return ['code' => $code, 'shift_type' => $k['type'], 'start_time' => $k['start'], 'end_time' => $k['end'], 'hours' => floatval($k['hours'])];
		}
		return ['code' => $code, 'shift_type' => $s['classification'], 'start_time' => $s['start_time'], 'end_time' => $s['end_time'], 'hours' => round(floatval($s['worked_hours']), 2)];
	}

	private static function Monday(string $date): string
	{
		return date('Y-m-d', strtotime("$date -" . (intval(date('N', strtotime($date))) - 1) . ' days'));
	}

	private function RotationFor(string $code, string $monday, array $h): string
	{
		if ($h['rotation_anchor'] === null)
		{
			return WorkforceAutoScheduleService::GetInstance()->CalculateWeeklyRotation($code, $monday) === 'APERTURA' ? 'A' : 'C';
		}
		$weeks = intval(round((strtotime($monday) - strtotime($h['rotation_anchor']['monday'])) / 604800));
		return ($weeks % 2 === 0) === ($h['rotation_anchor']['rot'] === 'A') ? 'A' : 'C';
	}

	/**
	 * Rotación semanal apertura/cierre de cada persona, continuando su última semana real. Si un área queda cargada a un solo
	 * bloque, pasa al otro a quien lleve más turnos de ese lado, para repartir la cobertura de forma escalonada.
	 */
	private function StaggerRotation(array $collabs, array $history, array $days, array $absences): array
	{
		$out = [];
		$weeks = array_unique(array_map(fn($d) => self::Monday($d), $days));
		$balance = [];
		foreach ($collabs as $c)
		{
			$code = $c['payroll_code'];
			$balance[$code] = ($history[$code]['openings'] ?? 0) - ($history[$code]['closings'] ?? 0);
		}
		foreach ($weeks as $monday)
		{
			foreach ($collabs as $c)
			{
				$out[$c['payroll_code']][$monday] = $this->RotationFor($c['payroll_code'], $monday, $history[$c['payroll_code']]);
			}
			foreach (array_unique(array_column($collabs, 'department')) as $dept)
			{
				$present = [];
				foreach ($collabs as $c)
				{
					$code = $c['payroll_code'];
					$absent = 0;
					for ($i = 0; $i < 7; $i++)
					{
						$absent += isset($absences[$code][date('Y-m-d', strtotime("$monday +$i days"))]) ? 1 : 0;
					}
					if ($c['department'] === $dept && $absent < 4)
					{
						$present[] = $code;
					}
				}
				for ($guard = 0; $guard < count($present); $guard++)
				{
					$am = array_values(array_filter($present, fn($c) => $out[$c][$monday] === 'A'));
					$pm = array_values(array_filter($present, fn($c) => $out[$c][$monday] === 'C'));
					if (abs(count($am) - count($pm)) < 2)
					{
						break;
					}
					$heavy = count($am) > count($pm) ? 'A' : 'C';
					$side = $heavy === 'A' ? $am : $pm;
					usort($side, fn($x, $y) => $heavy === 'A' ? $balance[$y] <=> $balance[$x] : $balance[$x] <=> $balance[$y]);
					$out[$side[0]][$monday] = $heavy === 'A' ? 'C' : 'A';
				}
			}
			foreach ($collabs as $c)
			{
				$balance[$c['payroll_code']] += $out[$c['payroll_code']][$monday] === 'A' ? 1 : -1;
			}
		}
		return $out;
	}

	/**
	 * Noches en bloques de 7 días como en la planilla: termina el bloque que venga del mes anterior y sigue con quien lleve más tiempo sin
	 * trasnochar. Cada bloque exige a la persona disponible toda la semana y no deja su área sin relevo. Si nadie puede, el día se reporta.
	 */
	private function AssignNights(array &$grid, array $info, array $days, array $sap, array $policy, array $history, array $restrictions = []): void
	{
		$pool = array_keys(array_filter($info, fn($c) => preg_match(self::NIGHT_DEPARTMENTS, (string) $c['department']) === 1 && array_filter($days, fn($d) => self::NightAllowed($c, $d, $restrictions))));
		$cell = isset($sap['7N22-6']) ? $this->WorkCell('7N22-6', $sap) : null;
		if (!$pool || $cell === null)
		{
			return;
		}
		$open = array_values(array_filter($days, fn($d) => !array_filter(array_column($grid, $d), fn($x) => !empty($x['locked']))));
		$open = array_values(array_filter($days, fn($d) => $d >= ($open[0] ?? '9999')));
		$last = [];
		$current = null;
		$run = 0;
		foreach ($pool as $c)
		{
			$last[$c] = $history[$c]['last_night'] ?? '0000-00-00';
			$r = $history[$c]['night_run'] ?? 0;
			foreach ($days as $d)
			{
				if (!empty($grid[$c][$d]['locked']))
				{
					$r = $grid[$c][$d]['shift_type'] === 'NOCHE' ? $r + 1 : 0;
					$last[$c] = $grid[$c][$d]['shift_type'] === 'NOCHE' ? $d : $last[$c];
				}
			}
			$dayBefore = gmdate('Y-m-d', strtotime(($open[0] ?? $days[0]) . ' 12:00 UTC') - 86400);
			if ($r > 0 && $r < self::NIGHT_BLOCK_DAYS && $last[$c] === $dayBefore)
			{
				$current = $c;
				$run = $r;
			}
		}
		$assign = function (string $c, string $d) use (&$grid, $cell, &$last) {
			$grid[$c][$d] = array_merge($grid[$c][$d], $cell, ['notes' => 'Turno nocturno (bloque de ' . self::NIGHT_BLOCK_DAYS . ' días)']);
			$last[$c] = $d;
		};
		$usable = fn($c, $d) => empty($grid[$c][$d]['locked']) && !self::IsOff($grid[$c][$d]) && self::NightAllowed($info[$c], $d, $restrictions) && self::ActiveRestriction($restrictions[$c] ?? [], 'PARCIAL', $d) === null;
		$i = 0;
		while ($current !== null && $i < count($open) && $run < self::NIGHT_BLOCK_DAYS && $usable($current, $open[$i]))
		{
			$assign($current, $open[$i++]);
			$run++;
		}
		$previous = $current;
		while ($i < count($open))
		{
			$block = array_slice($open, $i, self::NIGHT_BLOCK_DAYS);
			// Si el bloque acabara en sábado, el descanso caería en domingo: lo incluye para que el relevo descanse el lunes
			$after = $open[$i + self::NIGHT_BLOCK_DAYS] ?? null;
			if ($after !== null && date('w', strtotime($after)) === '0')
			{
				$block[] = $after;
			}
			$cands =array_values(array_filter($pool, function ($c) use ($block, $usable, $previous, $grid, $info, $history, $cell) {
				$monday = self::Monday($block[0]);
				$sameWeek = count(array_filter($block, fn($d) => self::Monday($d) === $monday));
				if ($c === $previous || ($history[$c]['week_hours'][$monday] ?? 0) + $sameWeek * $cell['hours'] > self::MAX_WEEK_PEAK)
				{
					return false;
				}
				foreach ($block as $d)
				{
					if (!$usable($c, $d))
					{
						return false;
					}
					$mates = array_filter(array_keys($info), fn($o) => $o !== $c && $info[$o]['department'] === $info[$c]['department'] && !self::IsOff($grid[$o][$d]));
					if (count($mates) < 2)
					{
						return false;
					}
				}
				return true;
			}));
			usort($cands, fn($a, $b) => $last[$a] <=> $last[$b]);
			if (!$cands)
			{
				$i++;
				continue;
			}
			foreach ($block as $d)
			{
				$assign($cands[0], $d);
			}
			$previous = $cands[0];
			$i += count($block);
		}
	}

	/**
	 * Cada domingo trabajado genera un compensatorio en la semana siguiente, de lunes a viernes y sin festivos ni fechas vetadas (así salen las
	 * semanas 35/49/42/42 de la planilla). Sigue la secuencia configurada de la persona o, sin ella, el día de la semana que menos ha usado.
	 * Un domingo de un mes anterior (por ejemplo, antes de vacaciones) va en la primera fecha válida. Primero se busca dejar cubierto el bloque
	 * del área; lo que no cabe en el mes queda pendiente con su fecha límite y nunca se pierde ni se duplica.
	 */
	private function AssignCompensatories(array &$grid, array $info, array $days, array $holidays, string $start, string $end, array $history, array $noComp = []): array
	{
		$pending = [];
		$load = [];
		foreach ($info as $code => $c)
		{
			$worked = [];
			$taken = [];
			foreach ($days as $d)
			{
				$x = $grid[$code][$d];
				if ($x['is_sunday'] && !self::IsOff($x))
				{
					$worked[] = $d;
				}
				if ($x['shift_type'] === 'COMPENSATORIO')
				{
					$taken[] = ['date' => $d, 'origin' => $x['origin']];
				}
			}
			$origins = self::UnmatchedSundays(array_merge($history[$code]['pending_comps'] ?? [], $worked), $taken);
			sort($origins);
			$deptSize = count(array_filter($info, fn($o) => $o['department'] === $c['department']));
			$sequence = array_values(array_filter(array_map('intval', explode(',', (string) $c['comp_sequence']))));
			$since = $c['comp_sequence_start'] ?: '0000-01-01';
			$used = array_merge($history[$code]['comp_dates'] ?? [], array_column($taken, 'date'));
			$turn = count(array_filter($used, fn($d) => $d >= $since));
			$usage = array_fill_keys(self::WEEKDAYS, 0);
			foreach ($used as $d)
			{
				$w = intval(date('N', strtotime($d)));
				$usage[$w] = ($usage[$w] ?? 0) + 1;
			}
			$valid = function (string $d) use (&$grid, $code, $holidays, $noComp) {
				return isset($grid[$code][$d]) && intval(date('N', strtotime($d))) <= 5 && !isset($holidays[$d]) && !isset($noComp[$d]) && !self::IsOff($grid[$code][$d]) && empty($grid[$code][$d]['locked']) && $grid[$code][$d]['shift_type'] !== 'NOCHE';
			};
			foreach ($origins as $origin)
			{
				$due = WorkforceLaborRulesService::CompensatoryDueDate($origin);
				$carried = $origin < $start;
				$week = $carried ? [] : array_filter($days, fn($d) => $d > $origin && $d <= $due);
				$rest = array_filter($days, fn($d) => $carried || $d > $due);
				$target = $sequence ? $sequence[$turn % count($sequence)] : null;
				$best = null;
				// Dentro de la semana siguiente se elige el mejor día; fuera de ella, el primero válido. Sin el segundo paso se admite dejar un bloque débil
				foreach ([true, false] as $strict)
				{
					$bestScore = -INF;
					foreach ($week as $d)
					{
						$morning = $valid($d) && $grid[$code][$d]['start_time'] < '12:00';
						if (!$valid($d) || ($strict && $this->DeptWorking($grid, $info, $c['department'], $d, $morning) < 2))
						{
							continue;
						}
						if ($due > $end && ($load[$c['department']][$d] ?? 0) >= max(1, intdiv($deptSize, 4)))
						{
							continue;
						}
						$w = intval(date('N', strtotime($d)));
						$score = -($load[$c['department']][$d] ?? 0) * 10 + $this->DeptWorking($grid, $info, $c['department'], $d, $morning);
						$score += $target !== null ? 40 - 5 * abs($w - $target) : -$usage[$w] * 6;
						$score += $d === self::NextDay($origin) && $grid[$code][$origin]['start_time'] >= '12:00' ? 3 : 0;
						$before = $grid[$code][date('Y-m-d', strtotime("$d -1 day"))] ?? null;
						$score += $before !== null && $before['shift_type'] === 'NOCHE' ? 100 : 0;
						if ($score > $bestScore)
						{
							$best = $d;
							$bestScore = $score;
						}
					}
					if ($best === null && ($due <= $end || $carried))
					{
						foreach ($rest as $d)
						{
							$morning = $valid($d) && $grid[$code][$d]['start_time'] < '12:00';
							if ($valid($d) && (!$strict || $this->DeptWorking($grid, $info, $c['department'], $d, $morning) >= 2))
							{
								$best = $d;
								break;
							}
						}
					}
					if ($best !== null)
					{
						break;
					}
				}
				if ($best === null)
				{
					$pending[] = ['payroll_code' => $code, 'name' => $c['name'], 'origin_sunday' => $origin, 'due_date' => $due];
					continue;
				}
				$grid[$code][$best] = array_merge($grid[$code][$best], self::OffCell('COMP', 'COMPENSATORIO'), ['origin' => $origin, 'notes' => "Compensatorio por domingo $origin"]);
				$load[$c['department']][$best] = ($load[$c['department']][$best] ?? 0) + 1;
				$usage[intval(date('N', strtotime($best)))]++;
				$turn++;
			}
		}
		return $pending;
	}

	private function DeptWorking(array $grid, array $info, string $dept, string $d, bool $morning): int
	{
		$n = 0;
		foreach ($info as $code => $c)
		{
			$x = $grid[$code][$d] ?? null;
			if ($c['department'] === $dept && $x && !self::IsOff($x) && $x['shift_type'] !== 'NOCHE' && ($x['start_time'] < '12:00') === $morning)
			{
				$n++;
			}
		}
		return $n;
	}

	/** Horas por semana (lunes) sumando la historia real y lo propuesto */
	private static function WeekTotals(array $cells, array $h): array
	{
		$weeks = $h['week_hours'] ?? [];
		foreach ($cells as $d => $x)
		{
			$m = self::Monday($d);
			$weeks[$m] = ($weeks[$m] ?? 0) + (self::IsOff($x) ? 0 : floatval($x['hours']));
		}
		ksort($weeks);
		return $weeks;
	}

	/** Mayor promedio semanal de la ventana de cuatro semanas completas que termina en $monday; null si faltan semanas */
	private static function AverageEndingAt(array $weeks, string $monday): ?float
	{
		$sum = 0;
		for ($i = 0; $i < self::AVERAGE_WEEKS; $i++)
		{
			$m = date('Y-m-d', strtotime("$monday -" . ($i * 7) . ' days'));
			if (!isset($weeks[$m]))
			{
				return null;
			}
			$sum += $weeks[$m];
		}
		return $sum / self::AVERAGE_WEEKS;
	}

	/** Respeta el tope de una semana y el promedio contratado en cuatro semanas: el exceso pasa a libre en el día con más gente */
	private function EnforceWeeklyHours(array &$grid, array $info, array $days, array $policy, array $history, array $restrictions = []): void
	{
		$weeks = [];
		foreach ($days as $d)
		{
			$weeks[self::Monday($d)][] = $d;
		}
		foreach ($info as $code => $c)
		{
			$cap = min(floatval($c['weekly_hours']) ?: 42.0, floatval($policy['max_weekly_hours'] ?? 42));
			foreach ($weeks as $monday => $wd)
			{
				$partial = self::ActiveRestriction($restrictions[$code] ?? [], 'PARCIAL', $wd[0]);
				$limit = $partial !== null && $partial['max_weekly_hours'] !== null ? floatval($partial['max_weekly_hours']) : self::MAX_WEEK_PEAK;
				$over = function () use (&$grid, $code, $history, $monday, $cap, $limit) {
					$totals = self::WeekTotals($grid[$code], $history[$code]);
					$avg = self::AverageEndingAt($totals, $monday);
					return ($totals[$monday] ?? 0) > $limit + 0.01 || ($avg !== null && $avg > $cap + 0.01);
				};
				$guard = 0;
				while ($over() && $guard++ < 7)
				{
					$pick = null;
					$most = -1;
					// Una semana completa de noches no se puede recortar: el libre sale de las otras semanas de la ventana de cuatro
					$window = array_filter($days, fn($d) => $d >= date('Y-m-d', strtotime("$monday -21 days")) && $d <= end($wd));
					foreach ([$wd, $window] as $candidates)
					{
						foreach ($candidates as $d)
						{
							$x = $grid[$code][$d];
							if (self::IsOff($x) || !empty($x['locked']) || $x['is_sunday'] || $x['shift_type'] === 'NOCHE')
							{
								continue;
							}
							$n = $this->DeptWorking($grid, $info, $c['department'], $d, $x['start_time'] < '12:00');
							if ($n > $most)
							{
								$most = $n;
								$pick = $d;
							}
						}
						if ($pick !== null)
						{
							break;
						}
					}
					if ($pick === null)
					{
						break;
					}
					$grid[$code][$pick] = array_merge($grid[$code][$pick], self::OffCell('LIBRE', 'LIBRE'), ['notes' => 'Libre por tope de horas contratadas']);
				}
			}
		}
	}

	/** Cierre seguido de apertura sin el descanso mínimo: mueve el turno al primer código SAP que lo respeta o da el día libre */
	private function RepairRest(array &$grid, array $info, array $days, array $sap, float $minRest, array $history): void
	{
		uasort($sap, fn($a, $b) => $a['start_time'] <=> $b['start_time']);
		foreach ($info as $code => $c)
		{
			$prevD = null;
			$prev = $history[$code]['last_day'] ?? null;
			if ($prev)
			{
				$prevD = $prev['shift_date'];
			}
			foreach ($days as $d)
			{
				$x = $grid[$code][$d];
				if ($prev && !self::IsOff($prev) && !self::IsOff($x) && empty($x['locked']) && self::NextDay($prevD) === $d && self::RestHours($prevD, $prev, $d, $x) < $minRest)
				{
					$fix = null;
					foreach ($sap as $sc => $s)
					{
						if (in_array($s['classification'], ['INTERMEDIO', 'CIERRE'], true) && abs(floatval($s['worked_hours']) - $x['hours']) < 0.6 && self::RestHours($prevD, $prev, $d, $s) >= $minRest)
						{
							$fix = $sc;
							break;
						}
					}
					if ($fix !== null && $x['shift_type'] !== 'NOCHE')
					{
						$grid[$code][$d] = array_merge($x, $this->WorkCell($fix, $sap), ['notes' => 'Ajustado para respetar el descanso entre turnos']);
					}
					elseif (!$x['is_sunday'])
					{
						$grid[$code][$d] = array_merge($x, self::OffCell('LIBRE', 'LIBRE'), ['notes' => 'Descanso entre turnos']);
					}
				}
				$prev = $grid[$code][$d] + ['shift_date' => $d];
				$prevD = $d;
			}
		}
	}

	/** Si un área queda sin apertura o sin cierre pasa a alguien del otro bloque, siempre que el descanso lo permita */
	private function RepairCoverage(array &$grid, array $info, array $days, array $sap, float $minRest, array $history): void
	{
		$depts = array_unique(array_column($info, 'department'));
		foreach ($days as $i => $d)
		{
			foreach ($depts as $dept)
			{
				$members = array_keys(array_filter($info, fn($c) => $c['department'] === $dept));
				$am = array_values(array_filter($members, fn($c) => !self::IsOff($grid[$c][$d]) && $grid[$c][$d]['shift_type'] !== 'NOCHE' && $grid[$c][$d]['start_time'] < '12:00'));
				$pm = array_values(array_filter($members, fn($c) => !self::IsOff($grid[$c][$d]) && $grid[$c][$d]['shift_type'] !== 'NOCHE' && $grid[$c][$d]['start_time'] >= '12:00'));
				foreach ([[$am, $pm, 'A'], [$pm, $am, 'C']] as [$need, $donors, $kind])
				{
					if ($need || count($donors) < 2)
					{
						continue;
					}
					foreach ($donors as $code)
					{
						$x = $grid[$code][$d];
						if (!empty($x['locked']) || !empty($info[$code]['partial']))
						{
							continue;
						}
						$cell = array_merge($x, $this->WorkCell($info[$code]['pref'][$kind], $sap), ['notes' => 'Cambio de bloque para cubrir el área']);
						$pd = $days[$i - 1] ?? null;
						$nd = $days[$i + 1] ?? null;
						$before = $pd ? $grid[$code][$pd] : ($history[$code]['last_day'] ?? null);
						$pd = $pd ?: ($before['shift_date'] ?? null);
						$okPrev = $before === null || self::IsOff($before) || self::NextDay($pd) !== $d || self::RestHours($pd, $before, $d, $cell) >= $minRest;
						$okNext = !$nd || self::IsOff($grid[$code][$nd]) || self::RestHours($d, $cell, $nd, $grid[$code][$nd]) >= $minRest;
						if ($okPrev && $okNext)
						{
							$grid[$code][$d] = $cell;
							break;
						}
					}
				}
			}
		}
	}

	private function DraftRows(int $draftId): array
	{
		return $this->Rows('SELECT * FROM workforce_schedule_draft_rows WHERE draft_id = :d ORDER BY shift_date', [':d' => $draftId]);
	}

	private function Draft(int $draftId): array
	{
		$d = $this->Rows('SELECT * FROM workforce_schedule_drafts WHERE id = :i', [':i' => $draftId])[0] ?? null;
		if (!$d)
		{
			throw new \Exception('Borrador no encontrado');
		}
		return $d;
	}

	private function SaveReport(int $draftId, array $extra = []): array
	{
		$draft = $this->Draft($draftId);
		$old = json_decode((string) $draft['report_json'], true) ?: [];
		[$y, $m] = array_map('intval', explode('-', $draft['period']));
		$report = $this->Validate(intval($draft['branch_id']), $y, $m, $this->DraftRows($draftId));
		$report['pending_compensatories'] = $extra['pending_compensatories'] ?? ($old['pending_compensatories'] ?? []);
		$report['sunday_exceptions'] = $extra['sunday_exceptions'] ?? ($old['sunday_exceptions'] ?? []);
		$this->Db()->prepare('UPDATE workforce_schedule_drafts SET report_json = :r WHERE id = :i')->execute([':r' => json_encode($report), ':i' => $draftId]);
		return ['draft_id' => $draftId, 'period' => $draft['period'], 'branch_id' => intval($draft['branch_id']), 'status' => $draft['status'], 'report' => $report, 'changes' => $this->DiffAgainstOfficial($draftId)];
	}

	/** Cuántos días cambiarían respecto a lo oficial */
	private function DiffAgainstOfficial(int $draftId): array
	{
		$r = $this->Rows('SELECT COUNT(*) AS total, SUM(CASE WHEN m.id IS NULL THEN 1 ELSE 0 END) AS new_rows, SUM(CASE WHEN m.id IS NOT NULL AND m.shift_code <> r.shift_code THEN 1 ELSE 0 END) AS changed
			FROM workforce_schedule_draft_rows r LEFT JOIN workforce_monthly_schedules m ON m.payroll_code = r.payroll_code AND m.shift_date = r.shift_date WHERE r.draft_id = :d', [':d' => $draftId])[0];
		return ['total' => intval($r['total']), 'new' => intval($r['new_rows']), 'changed' => intval($r['changed'])];
	}

	/**
	 * Validación real de un mes (oficial o borrador). legal = bloquea la aprobación; coverage y warn = se muestran y el supervisor decide.
	 * Sin $rows valida lo oficial.
	 */
	public function Validate(int $branchId, int $year, int $month, ?array $rows = null): array
	{
		[$start, $end] = $this->Range($year, $month);
		$collabs = $this->Collaborators($branchId);
		$info = array_column($collabs, null, 'payroll_code');
		$policy = $this->Policy($branchId, $start);
		$minRest = floatval($policy['min_rest_hours'] ?? 12);
		$maxNights = max(1, intval($policy['max_nights_per_day'] ?? 1));
		if ($rows === null)
		{
			$rows = array_values(array_filter($this->Rows('SELECT * FROM workforce_monthly_schedules WHERE shift_date BETWEEN :s AND :e ORDER BY shift_date', [':s' => $start, ':e' => $end]), fn($r) => isset($info[$r['payroll_code']])));
		}
		$byEmp = [];
		foreach ($rows as $r)
		{
			$byEmp[$r['payroll_code']][$r['shift_date']] = $r;
		}
		$history = $this->AnalyzeHistory($branchId, $year, $month);
		$absences = $this->Absences($collabs, $start, $end);
		$holidays = [];
		foreach (WorkforceAutoScheduleService::GetInstance()->GetColombianHolidays($year) as $h)
		{
			$holidays[$h['holiday_date']] = true;
		}
		$special = $this->SpecialDates($branchId, $start, $end);
		$restrictions = $this->Restrictions($start, $end);
		$sapList = $this->SapCodes();
		$issues = [];
		$add = function (string $sev, string $type, string $date, ?string $code, string $detail) use (&$issues, $info) {
			$help = self::ISSUE_HELP[$type] ?? ['', [], false];
			$issues[] = ['severity' => $sev, 'class' => self::ISSUE_CLASS[$sev], 'type' => $type, 'date' => $date, 'payroll_code' => $code, 'name' => $code ? ($info[$code]['name'] ?? $code) : null, 'department' => $code ? ($info[$code]['department'] ?? null) : null, 'detail' => $detail,
				'cause' => $help[0], 'alternatives' => $help[1], 'needs_management' => $help[2]];
		};
		$nights = [];
		$allDays = self::Dates($start, $end);
		foreach ($rows as $r)
		{
			$key = $r['payroll_code'] . '|' . $r['shift_date'];
			if (isset($seen[$key]))
			{
				$add('legal', 'overlap', $r['shift_date'], $r['payroll_code'], 'Dos turnos el mismo día');
			}
			$seen[$key] = true;
		}
		foreach ($info as $code => $c)
		{
			foreach (array_diff($allDays, array_keys($byEmp[$code] ?? [])) as $d)
			{
				$add('legal', 'missing_day', $d, $code, 'Día sin programar');
			}
		}
		foreach ($byEmp as $code => $days)
		{
			$cycle = $history[$code]['cycle'] ?? WorkforceLaborRulesService::NewCycle();
			$prev = $history[$code]['last_day'] ?? null;
			$prevD = $prev['shift_date'] ?? null;
			$original = fn($r) => ($r['notes'] ?? '') === 'Planilla original';
			$run = 0;
			$workedSundays = $history[$code]['pending_comps'] ?? [];
			$monthComps = [];
			$compOrigins = [];
			foreach ($days as $d => $r)
			{
				$c = $info[$code] ?? null;
				if (intval($r['is_sunday']) === 1)
				{
					if ($r['shift_type'] === 'LIBRE' && $cycle['anchor'] !== null)
					{
						$gap = WorkforceLaborRulesService::WeeksBetween($cycle['anchor'], $d);
						if ($gap % 4 !== 0 && $cycle['streak'] !== 3 && !$original($r) && !str_contains((string) $r['notes'], 'adelantado'))
						{
							$add('warn', 'sunday_cycle', $d, $code, "Domingo libre fuera de ciclo: $gap semanas desde el anterior ({$cycle['anchor']})");
						}
					}
					$cycle = WorkforceLaborRulesService::Step($cycle, $d, self::SundayState($r));
					if ($cycle['streak'] > WorkforceLaborRulesService::MAX_SUNDAYS && !self::IsOff($r))
					{
						$add($original($r) ? 'warn' : 'legal', 'sunday_3x1', $d, $code, "Domingo trabajado n.º {$cycle['streak']} seguido: la regla 3×1 exige descanso");
					}
					if (!self::IsOff($r))
					{
						$workedSundays[] = $d;
					}
					if (isset($special['NO_SUNDAY_FREE'][$d]) && $r['shift_type'] === 'LIBRE' && !$original($r))
					{
						$add('coverage', 'special_date', $d, $code, 'Libre dominical en fecha restringida: ' . $special['NO_SUNDAY_FREE'][$d]);
					}
				}
				if ($r['shift_type'] === 'COMPENSATORIO')
				{
					$monthComps[] = ['date' => $d, 'origin' => $r['compensatory_origin_date'] ?? null];
					$origin = $r['compensatory_origin_date'] ?? null;
					if ($origin !== null && isset($compOrigins[$origin]))
					{
						$add('legal', 'comp_duplicate', $d, $code, "Segundo compensatorio por el domingo $origin (el primero es del {$compOrigins[$origin]})");
					}
					if ($origin !== null)
					{
						$compOrigins[$origin] = $d;
						if ($origin >= $start && $d > WorkforceLaborRulesService::CompensatoryDueDate($origin))
						{
							$add('warn', 'comp_late', $d, $code, "Compensatorio del domingo $origin después de su semana");
						}
					}
					if (isset($special['NO_COMPENSATORY'][$d]) && !$original($r))
					{
						$add('coverage', 'special_date', $d, $code, 'Compensatorio en fecha restringida: ' . $special['NO_COMPENSATORY'][$d]);
					}
				}
				$run = self::IsOff($r) ? 0 : $run + 1;
				if ($run === self::LONG_RUN_DAYS + 1)
				{
					$add('warn', 'long_run', $d, $code, "$run días seguidos sin descanso");
				}
				if ($c !== null && !self::IsOff($r) && !$original($r))
				{
					$partial = self::ActiveRestriction($restrictions[$code] ?? [], 'PARCIAL', $d);
					$why = $partial !== null ? self::PartialViolation($partial, $d, $r) : null;
					if ($why !== null)
					{
						$add('legal', 'restriction_partial', $d, $code, "Disponibilidad parcial: $why ({$partial['reason']})");
					}
					$allowed = json_decode((string) $c['allowed_shifts'], true) ?: [];
					if ($allowed && !in_array($r['shift_code'], $allowed, true))
					{
						$add('legal', 'shift_not_allowed', $d, $code, "El turno {$r['shift_code']} no está entre sus turnos permitidos");
					}
				}
				if ($c !== null && $r['shift_type'] === 'NOCHE' && !self::NightAllowed($c, $d, $restrictions))
				{
					$add($original($r) ? 'warn' : 'legal', 'night_unauthorized', $d, $code, "{$c['department']}: sin autorización para turno nocturno");
				}
				if (isset($absences[$code][$d]) && !self::IsOff($r))
				{
					$add(($r['notes'] ?? '') === 'Planilla original' ? 'warn' : 'legal', 'absence_conflict', $d, $code, 'Tiene turno durante ' . strtolower($absences[$code][$d][1]));
				}
				if ($r['shift_type'] === 'NOCHE')
				{
					$nights[$d] = ($nights[$d] ?? 0) + 1;
				}
				if ($r['shift_type'] === 'COMPENSATORIO' && (intval(date('N', strtotime($d))) > 5 || isset($holidays[$d])))
				{
					$add('legal', 'comp_invalid_day', $d, $code, 'Compensatorio fuera de lunes a viernes hábil');
				}
				if ($prev && !self::IsOff($prev) && !self::IsOff($r) && self::NextDay($prevD) === $d)
				{
					$rest = self::RestHours($prevD, $prev, $d, $r);
					if ($rest < $minRest)
					{
						// Lo que ya viene de la planilla original no se puede corregir: se informa sin bloquear
						$add(($r['notes'] ?? '') === 'Planilla original' ? 'warn' : 'legal', 'min_rest', $d, $code, number_format($rest, 1) . " h de descanso entre {$prev['shift_code']} y {$r['shift_code']} (mínimo $minRest h)");
					}
				}
				$prev = $r;
				$prevD = $d;
			}
			foreach (self::UnmatchedSundays($workedSundays, $monthComps) as $sunday)
			{
				$limit = WorkforceLaborRulesService::CompensatoryDueDate($sunday);
				if ($limit <= $end)
				{
					$add('coverage', 'comp_overdue', $sunday, $code, "Compensatorio del domingo $sunday sin asignar (límite $limit)");
				}
			}
			$cap = floatval($policy['max_weekly_hours'] ?? 42);
			$weekly = self::WeekTotals(array_intersect_key($days, array_flip($allDays)), $history[$code] ?? []);
			foreach ($weekly as $w => $h)
			{
				if ($w < self::Monday($start))
				{
					continue;
				}
				if ($h > self::MAX_WEEK_PEAK + 0.01)
				{
					$add('legal', 'weekly_hours', $w, $code, number_format($h, 1) . ' h en la semana (máximo ' . self::MAX_WEEK_PEAK . ' h)');
				}
				$avg = self::AverageEndingAt($weekly, $w);
				if ($avg !== null && $avg > $cap + 0.01)
				{
					// La semana que empezó el mes anterior ya está trabajada: se informa sin bloquear
					$add($w < $start ? 'warn' : 'legal', 'average_hours', $w, $code, number_format($avg, 1) . " h de promedio en cuatro semanas (máximo $cap h)");
				}
			}
		}
		foreach ($allDays as $d)
		{
			$n = $nights[$d] ?? 0;
			if ($n > $maxNights)
			{
				$add('legal', 'night_cap', $d, null, "$n personas en turno nocturno (máximo $maxNights)");
			}
			elseif ($n === 0 && array_filter($info, fn($c) => intval($c['can_night']) === 1))
			{
				$add('coverage', 'no_night', $d, null, 'Sin persona disponible para el turno nocturno');
			}
		}
		$coverage = $this->Coverage($branchId, $rows, $info, $start, $end);
		foreach ($coverage['days'] as $d => $areas)
		{
			foreach ($areas as $dept => $c)
			{
				if ($c['status'] === 'red')
				{
					$add('coverage', 'no_coverage', $d, null, "$dept: " . ($c['open'] === 0 ? 'sin apertura' : '') . ($c['open'] === 0 && $c['close'] === 0 ? ' y ' : '') . ($c['close'] === 0 ? 'sin cierre' : '') . $this->SupportHint($dept, $d, $byEmp, $info));
				}
			}
		}
		$counts = ['legal' => 0, 'coverage' => 0, 'warn' => 0];
		foreach ($issues as $i)
		{
			$counts[$i['severity']]++;
		}
		usort($issues, fn($a, $b) => array_search($a['severity'], ['legal', 'coverage', 'warn']) <=> array_search($b['severity'], ['legal', 'coverage', 'warn']) ?: $a['date'] <=> $b['date']);
		$byType = array_count_values(array_column($issues, 'type'));
		$equity = $this->Equity($byEmp);
		$t = fn(string ...$types) => array_sum(array_map(fn($x) => $byType[$x] ?? 0, $types));
		$checks = [['label' => 'Secuencia 3×1 y domingos', 'bad' => $t('sunday_3x1')], ['label' => 'Continuidad del ciclo dominical', 'bad' => $t('sunday_cycle')], ['label' => 'Cobertura de apertura y cierre', 'bad' => $t('no_coverage')],
			['label' => 'Descansos compensatorios', 'bad' => $t('comp_invalid_day', 'comp_duplicate', 'comp_overdue')], ['label' => 'Fechas especiales', 'bad' => $t('special_date')], ['label' => 'Restricciones individuales', 'bad' => $t('restriction_partial', 'shift_not_allowed')], ['label' => 'Vacaciones y ausencias', 'bad' => $t('absence_conflict')], ['label' => 'Horas máximas', 'bad' => $t('weekly_hours', 'average_hours')],
			['label' => 'Superposición de turnos y días sin programar', 'bad' => $t('overlap', 'missing_day')], ['label' => 'Turnos nocturnos', 'bad' => $t('night_cap', 'no_night', 'night_unauthorized')], ['label' => 'Descanso mínimo entre jornadas', 'bad' => $t('min_rest')]];
		return ['counts' => $counts, 'by_type' => $byType, 'issues' => array_slice($issues, 0, 300), 'coverage' => $coverage, 'equity' => $equity, 'checks' => $checks,
			'can_approve' => $counts['legal'] === 0];
	}

	/** Quién de otra área, autorizado para apoyar, trabaja ese día y deja a su propia área cubierta. Nunca asigna: solo propone */
	private function SupportHint(string $dept, string $d, array $byEmp, array $info): string
	{
		$names = [];
		foreach ($info as $code => $c)
		{
			$r = $byEmp[$code][$d] ?? null;
			if ($c['department'] === $dept || !intval($c['cross_ok']) || $r === null || self::IsOff($r) || $r['shift_type'] === 'NOCHE' || !in_array($dept, json_decode((string) $c['secondary_areas'], true) ?: [], true))
			{
				continue;
			}
			$same = array_filter(array_keys($info), fn($o) => $o !== $code && $info[$o]['department'] === $c['department'] && isset($byEmp[$o][$d]) && !self::IsOff($byEmp[$o][$d]) && ($byEmp[$o][$d]['start_time'] < '12:00') === ($r['start_time'] < '12:00'));
			if ($same)
			{
				$names[] = $c['name'];
			}
		}
		return $names ? '. Apoyo posible: ' . implode(', ', array_slice($names, 0, 3)) : '. Sin personal habilitado para apoyar';
	}

	/** Lo que el supervisor debe confirmar antes de aprobar: el motor no decide por él cuando los datos se contradicen o faltan */
	public function Confirmations(int $branchId, int $year, int $month): array
	{
		[$start, $end, $period] = $this->Range($year, $month);
		$collabs = $this->Collaborators($branchId);
		$info = array_column($collabs, null, 'payroll_code');
		$byEmployee = array_column($collabs, null, 'employee_id');
		$history = $this->AnalyzeHistory($branchId, $year, $month);
		$out = [];
		foreach ($this->Rows("SELECT id, employee_id, absence_type, start_date, end_date, status FROM employee_absences WHERE start_date <= :t AND end_date >= :f AND status IN ('approved', 'requested', 'pending')", [':f' => $start, ':t' => $end]) as $a)
		{
			$c = $byEmployee[$a['employee_id']] ?? null;
			if ($c === null)
			{
				continue;
			}
			if ($a['end_date'] === WorkforcePlanillaImportService::OPEN_END)
			{
				$out[] = ['type' => 'open_absence', 'absence_id' => intval($a['id']), 'payroll_code' => $c['payroll_code'], 'name' => $c['name'], 'department' => $c['department'], 'start_date' => $a['start_date'],
					'detail' => "{$c['name']}: incapacidad vigente desde {$a['start_date']} sin fecha de regreso. No se le asignan turnos hasta registrarla."];
			}
			elseif ($a['status'] !== 'approved' && isset(self::ABSENCE_CODES[$a['absence_type']]))
			{
				$out[] = ['type' => 'requested_absence', 'absence_id' => intval($a['id']), 'payroll_code' => $c['payroll_code'], 'name' => $c['name'], 'department' => $c['department'], 'absence_type' => $a['absence_type'], 'status' => $a['status'], 'start_date' => $a['start_date'], 'end_date' => $a['end_date'],
					'detail' => "{$c['name']}: solicitud de ausencia del {$a['start_date']} al {$a['end_date']} sin aprobar. La programación la ignora hasta que se decida."];
			}
		}
		$diff = [];
		$people = [];
		foreach ($collabs as $c)
		{
			$lastFree = $history[$c['payroll_code']]['last_free_sunday'] ?? null;
			$anchor = self::SHEET_FREE_SUNDAY[$c['sunday_group']] ?? null;
			if ($lastFree !== null && $anchor !== null && intval(round((strtotime($lastFree) - strtotime($anchor)) / 604800)) % 4 !== 0)
			{
				$diff[] = $c['name'] . " ({$c['sunday_group']}, último libre real $lastFree)";
				$weeks = intdiv(intval(round((strtotime($lastFree) - strtotime($anchor)) / 604800)) + 400, 4) * 4 - 400;
				$people[] = ['name' => $c['name'], 'department' => $c['department'], 'group' => $c['sunday_group'], 'sheet_free' => date('Y-m-d', strtotime("$anchor " . ($weeks >= 0 ? '+' : '') . ($weeks * 7) . ' days')), 'real_free' => $lastFree];
			}
		}
		if ($diff)
		{
			$out[] = ['type' => 'sunday_sheet_diff', 'people' => $people, 'summary' => count($diff) . ' persona(s) continúan su ciclo dominical real, distinto de la hoja.',
				'detail' => count($diff) . ' persona(s) con la hoja dominical distinta de la programación real; se continúa el ciclo real: ' . implode('; ', $diff)];
		}
		$temp = array_filter(array_keys($info), fn($c) => str_starts_with($c, 'TEMP'));
		if ($temp)
		{
			$out[] = ['type' => 'temporary_codes', 'people' => array_map(fn($c) => ['code' => $c, 'name' => $info[$c]['name'], 'department' => $info[$c]['department']], array_values($temp)),
				'detail' => 'Códigos temporales sin confirmar: ' . implode(', ', $temp)];
		}
		$noContract = array_values(array_filter($collabs, fn($c) => empty($c['contract_type'])));
		$noSequence = array_values(array_filter($collabs, fn($c) => empty($c['comp_sequence'])));
		if ($noContract || $noSequence)
		{
			$out[] = ['type' => 'pending_config', 'summary' => count($noContract) . ' sin tipo de contrato y ' . count($noSequence) . ' sin secuencia de compensatorio (se reparte por el día menos usado).',
				'detail' => 'Datos de planta pendientes de configuración: ' . count($noContract) . ' sin tipo de contrato, ' . count($noSequence) . ' sin secuencia de compensatorio.'];
		}
		$out[] = ['type' => 'legal_review', 'summary' => 'El ciclo 35/49/42/42 promedia 42 h en cuatro semanas; el art. 165 del CST habla de tres. Pendiente de concepto jurídico.',
			'detail' => 'La planilla promedia 42 h en cuatro semanas con picos de 49 h. El art. 165 del CST permite promediar turnos en tres semanas: RR. HH. o jurídica debe validar el ciclo.'];
		$prev = date('Y-m', strtotime("$start -1 day"));
		$approved = intval($this->Rows("SELECT COUNT(*) AS n FROM workforce_schedule_drafts WHERE period = :p AND branch_id = :b AND status = 'approved'", [':p' => $prev, ':b' => $branchId])[0]['n']);
		// Basta un mes casi completo de la planilla: el 1 de noviembre real no convierte a noviembre en mes confirmado
		$real = intval($this->Rows("SELECT COUNT(DISTINCT m.shift_date) AS n FROM workforce_monthly_schedules m JOIN workforce_planilla_collaborators c ON c.payroll_code = m.payroll_code WHERE c.branch_id = :b AND substr(m.shift_date, 1, 7) = :p AND m.source = 'planilla'", [':b' => $branchId, ':p' => $prev])[0]['n']);
		if ($approved === 0 && $real < 20)
		{
			$out[] = ['type' => 'previous_month', 'detail' => "El mes anterior ($prev) no fue aprobado con este generador: apruébelo primero para que este mes continúe la programación correcta."];
		}
		return $out;
	}

	/** Registra el regreso de una ausencia abierta */
	public function SetAbsenceEnd(int $absenceId, string $date): void
	{
		if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date))
		{
			throw new \Exception('Fecha de regreso inválida');
		}
		$this->Db()->prepare('UPDATE employee_absences SET end_date = :d WHERE id = :i AND end_date = :open')->execute([':d' => date('Y-m-d', strtotime("$date -1 day")), ':i' => $absenceId, ':open' => WorkforcePlanillaImportService::OPEN_END]);
	}

	private function Log(int $draftId, string $action, string $detail, int $userId): void
	{
		$this->Db()->prepare('INSERT INTO workforce_schedule_draft_log (draft_id, action, detail, user_id) VALUES (:d, :a, :t, :u)')->execute([':d' => $draftId, ':a' => $action, ':t' => $detail, ':u' => $userId]);
	}

	/** Edición manual de una celda del borrador; queda en la bitácora y el reporte se recalcula */
	public function UpdateDraftCell(int $draftId, string $code, string $date, string $shiftCode, int $userId): array
	{
		if ($this->Draft($draftId)['status'] !== 'draft')
		{
			throw new \Exception('El borrador ya fue decidido');
		}
		$current = $this->Rows('SELECT shift_code FROM workforce_schedule_draft_rows WHERE draft_id = :d AND payroll_code = :c AND shift_date = :day', [':d' => $draftId, ':c' => $code, ':day' => $date])[0] ?? null;
		if ($current === null)
		{
			throw new \Exception('Celda no encontrada en el borrador');
		}
		$off = ['LIBRE' => 'LIBRE', 'COMP' => 'COMPENSATORIO', 'VC' => 'VACACIONES', 'INC' => 'INCAPACIDAD', 'PERM' => 'PERMISO'];
		$sap = $this->SapCodes();
		if (!isset($off[$shiftCode]) && !isset($sap[$shiftCode]))
		{
			throw new \Exception("El código $shiftCode no existe en Turnos SAP");
		}
		$cell = isset($off[$shiftCode]) ? self::OffCell($shiftCode, $off[$shiftCode]) : $this->WorkCell($shiftCode, $sap);
		$this->Db()->prepare('UPDATE workforce_schedule_draft_rows SET shift_code = :code, shift_type = :t, start_time = :s, end_time = :e, hours = :h, notes = :n WHERE draft_id = :d AND payroll_code = :c AND shift_date = :day')
			->execute([':code' => $cell['code'], ':t' => $cell['shift_type'], ':s' => $cell['start_time'], ':e' => $cell['end_time'], ':h' => $cell['hours'], ':n' => 'Editado por el supervisor', ':d' => $draftId, ':c' => $code, ':day' => $date]);
		$this->Log($draftId, 'edited', "$code $date: {$current['shift_code']} → {$cell['code']}", $userId);
		return $this->SaveReport($draftId);
	}

	/** Recalcular: conserva las ediciones manuales, reequilibra aperturas y cierres y vuelve a validar */
	public function Recalculate(int $draftId, int $userId): array
	{
		$res = $this->Balance($draftId);
		$this->Log($draftId, 'recalculated', ($res['swaps'] ?? 0) . ' intercambio(s) de equilibrio', $userId);
		return $res;
	}

	/** Versiones del mes y quién generó, modificó y aprobó cada una */
	public function Versions(int $branchId, string $period): array
	{
		$users = "(SELECT IFNULL(NULLIF(TRIM(IFNULL(u.first_name, '') || ' ' || IFNULL(u.last_name, '')), ''), u.username) FROM users u WHERE u.id = %s)";
		$out = $this->Rows('SELECT d.id, d.version, d.status, d.created_at, d.decided_at, ' . sprintf($users, 'd.created_by_user_id') . ' AS created_by, ' . sprintf($users, 'd.decided_by_user_id') . ' AS decided_by
			FROM workforce_schedule_drafts d WHERE d.period = :p AND d.branch_id = :b ORDER BY d.id DESC LIMIT 12', [':p' => $period, ':b' => $branchId]);
		foreach ($out as &$v)
		{
			$v['log'] = $this->Rows('SELECT l.action, l.detail, l.created_at, ' . sprintf($users, 'l.user_id') . ' AS user FROM workforce_schedule_draft_log l WHERE l.draft_id = :d ORDER BY l.id DESC LIMIT 20', [':d' => $v['id']]);
		}
		return $out;
	}

	/** Filas del mes que se muestran y exportan: el borrador activo o, sin borrador, la programación oficial */
	public function PeriodRows(int $branchId, int $year, int $month): array
	{
		[$start, $end, $period] = $this->Range($year, $month);
		$draftId = $this->ActiveDraft($branchId, $period);
		if ($draftId)
		{
			return [$this->DraftRows($draftId), $draftId];
		}
		$codes = array_flip(array_column($this->Collaborators($branchId), 'payroll_code'));
		return [array_values(array_filter($this->Rows('SELECT * FROM workforce_monthly_schedules WHERE shift_date BETWEEN :s AND :e ORDER BY shift_date', [':s' => $start, ':e' => $end]), fn($r) => isset($codes[$r['payroll_code']]))), null];
	}

	public function ExportData(int $branchId, int $year, int $month): array
	{
		[$start, $end, $period] = $this->Range($year, $month);
		[$rows, $draftId] = $this->PeriodRows($branchId, $year, $month);
		$report = $this->Validate($branchId, $year, $month, $rows);
		return ['period' => $period, 'start' => $start, 'end' => $end, 'draft_id' => $draftId, 'rows' => $rows, 'report' => $report, 'control' => WorkforcePlantaService::GetInstance()->Control($branchId, $year, $month), 'collaborators' => $this->Collaborators($branchId), 'history' => $this->AnalyzeHistory($branchId, $year, $month),
			'sap' => $this->Rows('SELECT code, start_time, end_time, worked_hours, lunch_hours, classification, description FROM workforce_sap_shifts WHERE IFNULL(active, 1) = 1 ORDER BY start_time = \'\', start_time'),
			'holidays' => WorkforceAutoScheduleService::GetInstance()->GetColombianHolidays($year), 'confirmations' => $this->Confirmations($branchId, $year, $month)];
	}

	/** Equidad = 100 % si aperturas y cierres son iguales; baja con la diferencia relativa */
	private function Equity(array $byEmp): array
	{
		$rows = [];
		foreach ($byEmp as $code => $days)
		{
			$a = 0;
			$c = 0;
			foreach ($days as $r)
			{
				if (self::IsOff($r) || $r['shift_type'] === 'NOCHE')
				{
					continue;
				}
				$r['start_time'] < '12:00' ? $a++ : $c++;
			}
			$rows[$code] = ['openings' => $a, 'closings' => $c, 'equity_pct' => ($a + $c) > 0 ? round(100 - abs($a - $c) / ($a + $c) * 100, 1) : null];
		}
		$vals = array_filter(array_column($rows, 'equity_pct'), fn($v) => $v !== null);
		return ['average_pct' => $vals ? round(array_sum($vals) / count($vals), 1) : null, 'by_employee' => $rows];
	}

	/** Verde: apertura, cierre y dotación de hora pico. Amarillo: falta dotación pico. Rojo: sin apertura o sin cierre */
	private function Coverage(int $branchId, array $rows, array $info, string $start, string $end): array
	{
		$cfg = [];
		foreach ($this->Rows('SELECT department, min_staff_open, min_staff_peak FROM workforce_department_configs WHERE branch_id = :b', [':b' => $branchId]) as $r)
		{
			$cfg[$r['department']] = $r;
		}
		$days = [];
		$depts = array_unique(array_column($info, 'department'));
		foreach (self::Dates($start, $end) as $d)
		{
			foreach ($depts as $dept)
			{
				$days[$d][$dept] = ['open' => 0, 'close' => 0, 'night' => 0, 'peak' => 0];
			}
		}
		foreach ($rows as $r)
		{
			$dept = $info[$r['payroll_code']]['department'] ?? null;
			if ($dept === null || !isset($days[$r['shift_date']][$dept]) || self::IsOff($r))
			{
				continue;
			}
			$c = &$days[$r['shift_date']][$dept];
			if ($r['shift_type'] === 'NOCHE')
			{
				$c['night']++;
			}
			else
			{
				$r['start_time'] < '12:00' ? $c['open']++ : $c['close']++;
				$c['peak'] += ($r['start_time'] <= '13:00' && $r['end_time'] > '13:00') || ($r['start_time'] <= '18:00' && $r['end_time'] > '18:00') ? 1 : 0;
			}
			unset($c);
		}
		$summary = ['green' => 0, 'yellow' => 0, 'red' => 0];
		foreach ($days as $d => &$areas)
		{
			foreach ($areas as $dept => &$c)
			{
				$minOpen = max(1, intval($cfg[$dept]['min_staff_open'] ?? 1));
				$peak = max(1, min(count(array_filter($info, fn($x) => $x['department'] === $dept)), intval($cfg[$dept]['min_staff_peak'] ?? 1)));
				$c['required_peak'] = $peak;
				$c['status'] = ($c['open'] < 1 || $c['close'] < 1) ? 'red' : (($c['open'] < $minOpen || $c['close'] < $minOpen || $c['peak'] < $peak) ? 'yellow' : 'green');
				$summary[$c['status']]++;
			}
			unset($c);
		}
		unset($areas);
		return ['days' => $days, 'summary' => $summary];
	}

	/** Intercambia aperturas y cierres entre compañeros de área el mismo día hasta acercar a cada persona al equilibrio */
	public function Balance(int $draftId): array
	{
		$draft = $this->Draft($draftId);
		if ($draft['status'] !== 'draft')
		{
			throw new \Exception('Solo se equilibra un borrador');
		}
		[$y, $m] = array_map('intval', explode('-', $draft['period']));
		$branch = intval($draft['branch_id']);
		$info = array_column($this->Collaborators($branch), null, 'payroll_code');
		$minRest = floatval($this->Policy($branch, $draft['period'] . '-01')['min_rest_hours'] ?? 12);
		$today = date('Y-m-d');
		$by = [];
		foreach ($this->DraftRows($draftId) as $r)
		{
			$by[$r['payroll_code']][$r['shift_date']] = $r;
		}
		$upd = $this->Db()->prepare('UPDATE workforce_schedule_draft_rows SET shift_code = :c, shift_type = :t, start_time = :s, end_time = :e, hours = :h, notes = :n WHERE id = :i');
		$swaps = 0;
		$history = $this->AnalyzeHistory($branch, $y, $m);
		$restOk = function (string $code, string $d, array $cell) use (&$by, $minRest, $history) {
			$p = date('Y-m-d', strtotime("$d -1 day"));
			$n = date('Y-m-d', strtotime("$d +1 day"));
			$prev = $by[$code][$p] ?? (($history[$code]['last_day']['shift_date'] ?? null) === $p ? $history[$code]['last_day'] : null);
			return ($prev === null || self::IsOff($prev) || self::RestHours($p, $prev, $d, $cell) >= $minRest)
				&& (!isset($by[$code][$n]) || self::IsOff($by[$code][$n]) || self::RestHours($d, $cell, $n, $by[$code][$n]) >= $minRest);
		};
		for ($pass = 0; $pass < 60; $pass++)
		{
			$eq = $this->Equity($by)['by_employee'];
			$diff = [];
			foreach ($eq as $c => $e)
			{
				$diff[$c] = $e['openings'] - $e['closings'];
			}
			arsort($diff);
			$done = false;
			foreach ($diff as $a => $da)
			{
				if ($da < 3 || !isset($info[$a]))
				{
					break;
				}
				foreach (array_reverse($diff, true) as $b => $db)
				{
					if ($db > -1 || !isset($info[$b]) || $info[$a]['department'] !== $info[$b]['department'])
					{
						continue;
					}
					foreach ($by[$a] as $d => $ra)
					{
						$rb = $by[$b][$d] ?? null;
						if ($d <= $today || !$rb || $ra['notes'] === 'Planilla original' || $rb['notes'] === 'Planilla original' || self::IsOff($ra) || self::IsOff($rb) || $ra['shift_type'] === 'NOCHE' || $rb['shift_type'] === 'NOCHE' || $ra['start_time'] >= '12:00' || $rb['start_time'] < '12:00')
						{
							continue;
						}
						if (!$restOk($a, $d, $rb) || !$restOk($b, $d, $ra))
						{
							continue;
						}
						foreach (['shift_code', 'shift_type', 'start_time', 'end_time', 'hours'] as $f)
						{
							[$by[$a][$d][$f], $by[$b][$d][$f]] = [$rb[$f], $ra[$f]];
						}
						foreach ([$a, $b] as $c)
						{
							$x = $by[$c][$d];
							$upd->execute([':c' => $x['shift_code'], ':t' => $x['shift_type'], ':s' => $x['start_time'], ':e' => $x['end_time'], ':h' => $x['hours'], ':n' => 'Equilibrio apertura/cierre', ':i' => $x['id']]);
						}
						$swaps++;
						$done = true;
						break 3;
					}
				}
			}
			if (!$done)
			{
				break;
			}
		}
		return $this->SaveReport($draftId) + ['swaps' => $swaps];
	}

	/** Candidatos reales para cubrir un día: disponibles, con descanso suficiente y sin pasar el tope de horas. Primero su área, luego quienes tienen cobertura cruzada */
	public function FindReplacement(int $draftId, string $payrollCode, string $date): array
	{
		$draft = $this->Draft($draftId);
		$branch = intval($draft['branch_id']);
		$info = array_column($this->Collaborators($branch), null, 'payroll_code');
		$policy = $this->Policy($branch, $date);
		$minRest = floatval($policy['min_rest_hours'] ?? 12);
		$by = [];
		foreach ($this->DraftRows($draftId) as $r)
		{
			$by[$r['payroll_code']][$r['shift_date']] = $r;
		}
		$target = $by[$payrollCode][$date] ?? null;
		if (!$target || !isset($info[$payrollCode]))
		{
			throw new \Exception('Sin turno para ese colaborador y fecha');
		}
		if (self::IsOff($target))
		{
			throw new \Exception('Ese día no tiene turno que reemplazar');
		}
		$dept = $info[$payrollCode]['department'];
		$week = date('o-W', strtotime($date));
		$out = [];
		foreach ($info as $code => $c)
		{
			$x = $by[$code][$date] ?? null;
			if ($code === $payrollCode || !$x || $x['shift_type'] !== 'LIBRE' || intval($x['is_sunday']) === 1)
			{
				continue;
			}
			$same = $c['department'] === $dept;
			$cross = !$same && intval($c['cross_ok']) === 1 && stripos((string) $c['secondary_areas'], $dept) !== false;
			if ((!$same && !$cross) || ($target['shift_type'] === 'NOCHE' && !self::NightAllowed($c, $date, $this->Restrictions($date, $date))))
			{
				continue;
			}
			$p = date('Y-m-d', strtotime("$date -1 day"));
			$n = date('Y-m-d', strtotime("$date +1 day"));
			if ((isset($by[$code][$p]) && !self::IsOff($by[$code][$p]) && self::RestHours($p, $by[$code][$p], $date, $target) < $minRest)
				|| (isset($by[$code][$n]) && !self::IsOff($by[$code][$n]) && self::RestHours($date, $target, $n, $by[$code][$n]) < $minRest))
			{
				continue;
			}
			$hours = 0.0;
			foreach ($by[$code] as $d => $r)
			{
				if (date('o-W', strtotime($d)) === $week && !self::IsOff($r))
				{
					$hours += floatval($r['hours']);
				}
			}
			$cap = min(floatval($c['weekly_hours']) ?: 42.0, floatval($policy['max_weekly_hours'] ?? 42));
			if ($hours + floatval($target['hours']) > $cap + 0.01)
			{
				continue;
			}
			$out[] = ['payroll_code' => $code, 'name' => $c['name'], 'department' => $c['department'], 'same_area' => $same, 'week_hours' => round($hours, 1), 'reason' => ($same ? 'Misma área' : 'Cobertura cruzada autorizada') . ' · libre ese día · ' . round($hours, 1) . " h en la semana"];
		}
		usort($out, fn($a, $b) => [$b['same_area'], $a['week_hours']] <=> [$a['same_area'], $b['week_hours']]);
		return ['date' => $date, 'shift_code' => $target['shift_code'], 'candidates' => $out];
	}

	public function ApplyReplacement(int $draftId, string $from, string $to, string $date): array
	{
		$draft = $this->Draft($draftId);
		if ($draft['status'] !== 'draft')
		{
			throw new \Exception('Solo se modifica un borrador');
		}
		$cands = array_column($this->FindReplacement($draftId, $from, $date)['candidates'], 'payroll_code');
		if (!in_array($to, $cands, true))
		{
			throw new \Exception('El colaborador elegido ya no es un reemplazo válido');
		}
		$r = $this->Rows('SELECT * FROM workforce_schedule_draft_rows WHERE draft_id = :d AND shift_date = :s AND payroll_code = :c', [':d' => $draftId, ':s' => $date, ':c' => $from])[0];
		$set = $this->Db()->prepare('UPDATE workforce_schedule_draft_rows SET shift_code = :c, shift_type = :t, start_time = :s, end_time = :e, hours = :h, notes = :n WHERE draft_id = :d AND payroll_code = :p AND shift_date = :day');
		$set->execute([':c' => $r['shift_code'], ':t' => $r['shift_type'], ':s' => $r['start_time'], ':e' => $r['end_time'], ':h' => $r['hours'], ':n' => 'Reemplazo de ' . $from, ':d' => $draftId, ':p' => $to, ':day' => $date]);
		$set->execute([':c' => 'LIBRE', ':t' => 'LIBRE', ':s' => '00:00', ':e' => '00:00', ':h' => 0, ':n' => 'Reemplazado por ' . $to, ':d' => $draftId, ':p' => $from, ':day' => $date]);
		return $this->SaveReport($draftId);
	}

	public function GetDraft(int $draftId, bool $withRows = false): array
	{
		$d = $this->Draft($draftId);
		$out = ['draft_id' => $draftId, 'period' => $d['period'], 'branch_id' => intval($d['branch_id']), 'status' => $d['status'], 'created_at' => $d['created_at'], 'report' => json_decode((string) $d['report_json'], true), 'changes' => $this->DiffAgainstOfficial($draftId)];
		if ($withRows)
		{
			$names = array_column($this->Collaborators(intval($d['branch_id'])), null, 'payroll_code');
			$grid = [];
			$official = [];
			foreach ($this->Rows('SELECT payroll_code, shift_date, shift_code FROM workforce_monthly_schedules WHERE substr(shift_date, 1, 7) = :p', [':p' => $d['period']]) as $r)
			{
				$official[$r['payroll_code']][$r['shift_date']] = $r['shift_code'];
			}
			foreach ($this->DraftRows($draftId) as $r)
			{
				$c = $r['payroll_code'];
				$grid[$c] ??= ['payroll_code' => $c, 'name' => $names[$c]['name'] ?? $c, 'department' => $names[$c]['department'] ?? '', 'days' => []];
				$grid[$c]['days'][$r['shift_date']] = ['code' => $r['shift_code'], 'type' => $r['shift_type'], 'changed' => isset($official[$c][$r['shift_date']]) && $official[$c][$r['shift_date']] !== $r['shift_code']];
			}
			$out['grid'] = array_values($grid);
		}
		return $out;
	}

	public function ActiveDraft(int $branchId, string $period): ?int
	{
		$id = $this->Rows("SELECT id FROM workforce_schedule_drafts WHERE period = :p AND branch_id = :b AND status = 'draft' ORDER BY id DESC LIMIT 1", [':p' => $period, ':b' => $branchId])[0]['id'] ?? null;
		return $id === null ? null : intval($id);
	}

	private function IsPublished(int $branchId, string $period): bool
	{
		return intval($this->Rows("SELECT COUNT(*) AS n FROM workforce_monthly_schedules m JOIN workforce_planilla_collaborators c ON c.payroll_code = m.payroll_code WHERE c.branch_id = :b AND substr(m.shift_date, 1, 7) = :p AND m.status = 'published'", [':b' => $branchId, ':p' => $period])[0]['n']) > 0;
	}

	public function PublicationState(int $branchId, string $period): array
	{
		$open = $this->Rows('SELECT id, reason, created_at FROM workforce_schedule_reopens WHERE period = :p AND branch_id = :b AND consumed_at IS NULL ORDER BY id DESC LIMIT 1', [':p' => $period, ':b' => $branchId])[0] ?? null;
		return ['published' => $this->IsPublished($branchId, $period), 'reopened' => $open];
	}

	/** Un mes publicado solo se reemplaza tras reabrirlo con motivo; queda en la bitácora de turnos */
	public function Reopen(int $branchId, int $year, int $month, string $reason, int $userId): array
	{
		[, , $period] = $this->Range($year, $month);
		$reason = trim($reason);
		if (mb_strlen($reason) < 10)
		{
			throw new \Exception('Explique el motivo de reabrir el mes (mínimo 10 caracteres)');
		}
		$this->Db()->prepare('INSERT INTO workforce_schedule_reopens (period, branch_id, reason, user_id) VALUES (:p, :b, :r, :u)')->execute([':p' => $period, ':b' => $branchId, ':r' => $reason, ':u' => $userId]);
		WorkforceAutoScheduleService::GetInstance()->LogAudit(null, null, '', $period . '-01', 'REOPEN_MONTH', 'published', 'reopened', $reason, $userId);
		return $this->PublicationState($branchId, $period);
	}

	/** Aprobación del supervisor: reemplaza solo días futuros de esa sucursal y publica. Bloquea si queda algún incumplimiento legal */
	public function Approve(int $draftId, int $userId, bool $acceptCoverageGaps = false, bool $acceptConfirmations = false): array
	{
		$d = $this->Draft($draftId);
		if ($d['status'] !== 'draft')
		{
			throw new \Exception('El borrador ya fue decidido');
		}
		$branch = intval($d['branch_id']);
		$period = $d['period'];
		$state = $this->PublicationState($branch, $period);
		if ($state['published'] && !$state['reopened'])
		{
			throw new \Exception('El mes ya está publicado: reábralo con un motivo antes de reemplazarlo');
		}
		$res = $this->SaveReport($draftId);
		$report = $res['report'];
		if (!$report['can_approve'])
		{
			throw new \Exception($report['counts']['legal'] . ' incumplimiento(s) legal(es) sin resolver: no se puede aprobar');
		}
		if ($report['counts']['coverage'] > 0 && !$acceptCoverageGaps)
		{
			throw new \Exception($report['counts']['coverage'] . ' falta(s) de cobertura: confirme que las acepta para aprobar');
		}
		[$py, $pm] = array_map('intval', explode('-', $period));
		$confirmations = $this->Confirmations($branch, $py, $pm);
		if ($confirmations && !$acceptConfirmations)
		{
			throw new \Exception(count($confirmations) . ' punto(s) por confirmar antes de aprobar');
		}
		$this->Log($draftId, 'approved', 'Cobertura aceptada: ' . $report['counts']['coverage'] . ' falta(s); confirmaciones: ' . implode(' | ', array_column($confirmations, 'detail')), $userId);
		$from = max($period . '-01', date('Y-m-d', strtotime('+1 day')));
		$to = date('Y-m-t', strtotime($period . '-01'));
		$db = $this->Db();
		$codes = array_column($this->Collaborators($branch), 'payroll_code');
		$in = implode(',', array_map(fn($c) => $db->quote($c), $codes));
		$db->beginTransaction();
		try
		{
			$db->prepare("DELETE FROM workforce_monthly_schedules WHERE shift_date BETWEEN :f AND :t AND payroll_code IN ($in)")->execute([':f' => $from, ':t' => $to]);
			$db->prepare("INSERT INTO workforce_monthly_schedules (payroll_code, employee_id, shift_date, shift_code, shift_type, start_time, end_time, hours, is_sunday, is_holiday, compensatory_origin_date, notes, status)
				SELECT payroll_code, employee_id, shift_date, shift_code, shift_type, start_time, end_time, hours, is_sunday, is_holiday, compensatory_origin_date, notes, 'published' FROM workforce_schedule_draft_rows WHERE draft_id = :d AND shift_date BETWEEN :f AND :t")
				->execute([':d' => $draftId, ':f' => $from, ':t' => $to]);
			$db->prepare("DELETE FROM workforce_compensatory_bank WHERE payroll_code IN ($in) AND ((assigned_date BETWEEN :f AND :t) OR (status = 'PENDING' AND carried_over_month = :p))")->execute([':f' => $from, ':t' => $to, ':p' => $period]);
			$bank = $db->prepare('INSERT OR REPLACE INTO workforce_compensatory_bank (payroll_code, origin_sunday, assigned_date, status, carried_over_month, notes, entitlement, due_date) VALUES (:c, :o, :a, :s, :m, :n, :en, :due)');
			$sundays = $db->prepare("SELECT COUNT(*) FROM workforce_monthly_schedules WHERE payroll_code = :c AND is_sunday = 1 AND substr(shift_date, 1, 7) = :m AND shift_type NOT IN ('LIBRE', 'COMPENSATORIO', 'VACACIONES', 'INCAPACIDAD', 'PERMISO')");
			$kind = function (string $code, string $origin) use ($sundays) {
				$sundays->execute([':c' => $code, ':m' => substr($origin, 0, 7)]);
				return WorkforceLaborRulesService::SundayEntitlement(intval($sundays->fetchColumn()));
			};
			foreach ($this->Rows("SELECT payroll_code, compensatory_origin_date AS o, shift_date FROM workforce_schedule_draft_rows WHERE draft_id = :d AND shift_type = 'COMPENSATORIO' AND compensatory_origin_date IS NOT NULL AND shift_date BETWEEN :f AND :t", [':d' => $draftId, ':f' => $from, ':t' => $to]) as $b)
			{
				$bank->execute([':c' => $b['payroll_code'], ':o' => $b['o'], ':a' => $b['shift_date'], ':s' => 'SCHEDULED', ':m' => null, ':n' => "Compensatorio por domingo {$b['o']}", ':en' => $kind($b['payroll_code'], $b['o']), ':due' => WorkforceLaborRulesService::CompensatoryDueDate($b['o'])]);
			}
			foreach ($report['pending_compensatories'] ?? [] as $p)
			{
				$bank->execute([':c' => $p['payroll_code'], ':o' => $p['origin_sunday'], ':a' => null, ':s' => 'PENDING', ':m' => $period, ':n' => 'Compensatorio pendiente de asignar', ':en' => $kind($p['payroll_code'], $p['origin_sunday']), ':due' => WorkforceLaborRulesService::CompensatoryDueDate($p['origin_sunday'])]);
			}
			$exception = $db->prepare('INSERT OR REPLACE INTO workforce_sunday_exceptions (payroll_code, original_sunday, new_sunday, reason, draft_id, approved_by_user_id) VALUES (:c, :o, :n, :r, :d, :u)');
			foreach ($report['sunday_exceptions'] ?? [] as $e)
			{
				$exception->execute([':c' => $e['payroll_code'], ':o' => $e['original_sunday'], ':n' => $e['new_sunday'], ':r' => $e['reason'], ':d' => $draftId, ':u' => $userId]);
			}
			$db->prepare("UPDATE workforce_schedule_drafts SET status = 'approved', decided_by_user_id = :u, decided_at = datetime('now', 'localtime') WHERE id = :i")->execute([':u' => $userId, ':i' => $draftId]);
			$db->prepare("UPDATE workforce_schedule_reopens SET consumed_at = datetime('now', 'localtime') WHERE period = :p AND branch_id = :b AND consumed_at IS NULL")->execute([':p' => $period, ':b' => $branch]);
			$checklist = ['legal_issues' => 0, 'coverage_issues' => $report['counts']['coverage'], 'branch_id' => $branch];
			$db->prepare("INSERT INTO workforce_schedule_publications (period_name, start_date, end_date, status, average_equity_pct, night_coverage_ok, sundays_compliance_ok, compensatory_compliance_ok, validation_checklist, approved_by, approved_timestamp, published_by, published_timestamp)
				VALUES (:p, :s, :e, 'published', :eq, 1, 1, :comp, :chk, :u, datetime('now', 'localtime'), :u2, datetime('now', 'localtime'))")
				->execute([':p' => $period, ':s' => $period . '-01', ':e' => $to, ':eq' => $report['equity']['average_pct'], ':comp' => empty($report['pending_compensatories']) ? 1 : 0, ':chk' => json_encode($checklist), ':u' => $userId, ':u2' => $userId]);
			WorkforceAutoScheduleService::GetInstance()->SyncToEmployeeShifts($from, $to, $branch);
			$db->commit();
		}
		catch (\Throwable $e)
		{
			$db->rollBack();
			throw $e;
		}
		$this->RaiseCoverageAlerts($branch, $period, $report, $userId);
		return $this->GetDraft($draftId);
	}

	private function BranchOf(string $code): int
	{
		return intval($this->Rows('SELECT branch_id FROM workforce_planilla_collaborators WHERE payroll_code = :c', [':c' => $code])[0]['branch_id'] ?? 0);
	}

	/** Celda vigente: la del borrador activo del mes o, sin borrador, la oficial */
	private function CurrentCell(string $code, string $date): ?array
	{
		$draftId = $this->ActiveDraft($this->BranchOf($code), substr($date, 0, 7));
		$r = $draftId !== null ? $this->Rows('SELECT * FROM workforce_schedule_draft_rows WHERE draft_id = :d AND payroll_code = :c AND shift_date = :s', [':d' => $draftId, ':c' => $code, ':s' => $date])
			: $this->Rows('SELECT * FROM workforce_monthly_schedules WHERE payroll_code = :c AND shift_date = :s', [':c' => $code, ':s' => $date]);
		return $r[0] ?? null;
	}

	/** Escribe una celda en lo oficial y en el borrador activo. La historia confirmada de la planilla no se toca */
	private function WriteCell(string $code, string $date, array $cell, ?string $notes, ?string $origin = null): void
	{
		$set = 'SET shift_code = :k, shift_type = :t, start_time = :s, end_time = :e, hours = :h, notes = :n, compensatory_origin_date = :o';
		$p = [':k' => $cell['code'] ?? $cell['shift_code'], ':t' => $cell['shift_type'], ':s' => $cell['start_time'], ':e' => $cell['end_time'], ':h' => $cell['hours'], ':n' => $notes, ':o' => $origin, ':c' => $code, ':d' => $date];
		$this->Db()->prepare("UPDATE workforce_monthly_schedules $set WHERE payroll_code = :c AND shift_date = :d AND IFNULL(source, '') <> 'planilla'")->execute($p);
		$draftId = $this->ActiveDraft($this->BranchOf($code), substr($date, 0, 7));
		if ($draftId !== null)
		{
			$this->Db()->prepare("UPDATE workforce_schedule_draft_rows $set WHERE draft_id = :di AND payroll_code = :c AND shift_date = :d AND IFNULL(notes, '') <> 'Planilla original'")->execute($p + [':di' => $draftId]);
		}
	}

	/** Registra una ausencia sobre la programación. Un compensatorio desplazado vuelve a pendiente: el derecho no se pierde */
	public function MarkAbsence(string $code, string $from, string $to, array $off, string $note, int $userId): void
	{
		foreach (self::Dates($from, $to) as $d)
		{
			$x = $this->CurrentCell($code, $d);
			if ($x === null)
			{
				continue;
			}
			if ($x['shift_type'] === 'COMPENSATORIO' && !empty($x['compensatory_origin_date']))
			{
				$this->Db()->prepare("UPDATE workforce_compensatory_bank SET status = 'PENDING', assigned_date = NULL, previous_date = :d, reprogram_reason = :r WHERE payroll_code = :c AND origin_sunday = :o")
					->execute([':d' => $d, ':r' => $note, ':c' => $code, ':o' => $x['compensatory_origin_date']]);
			}
			$this->WriteCell($code, $d, self::OffCell($off[0], $off[1]), $note);
			WorkforceAutoScheduleService::GetInstance()->LogAudit(null, null, $code, $d, 'ABSENCE', $x['shift_code'], $off[0], $note, $userId);
		}
	}

	/** Mueve un compensatorio a otra fecha válida. Si ya tenía fecha, el turno del nuevo día pasa a esa fecha */
	public function RescheduleCompensatory(string $code, string $origin, string $date, string $reason, int $userId): void
	{
		$target = $this->CurrentCell($code, $date);
		$holidays = array_column(WorkforceAutoScheduleService::GetInstance()->GetColombianHolidays(intval(substr($date, 0, 4))), 'name', 'holiday_date');
		$branch = $this->BranchOf($code);
		if ($target === null || self::IsOff($target) || $target['shift_type'] === 'NOCHE')
		{
			throw new \DomainException("El $date no tiene un turno que se pueda cambiar por compensatorio");
		}
		if ($date <= $origin || intval(date('N', strtotime($date))) > 5 || isset($holidays[$date]) || isset($this->SpecialDates($branch, $date, $date)['NO_COMPENSATORY'][$date]))
		{
			throw new \DomainException("El $date no es válido para compensatorio");
		}
		$draftId = $this->ActiveDraft($branch, substr($date, 0, 7));
		$old = $this->Rows('SELECT shift_date FROM ' . ($draftId !== null ? "workforce_schedule_draft_rows WHERE draft_id = $draftId AND" : 'workforce_monthly_schedules WHERE') . " payroll_code = :c AND compensatory_origin_date = :o AND shift_type = 'COMPENSATORIO'", [':c' => $code, ':o' => $origin])[0]['shift_date'] ?? null;
		if ($old !== null)
		{
			$this->WriteCell($code, $old, $target, 'Cambiado por reprogramación de compensatorio');
		}
		$this->WriteCell($code, $date, self::OffCell('COMP', 'COMPENSATORIO'), "Compensatorio por domingo $origin (reprogramado)", $origin);
		$this->Db()->prepare("INSERT OR REPLACE INTO workforce_compensatory_bank (payroll_code, origin_sunday, assigned_date, status, notes, previous_date, reprogram_reason, due_date) VALUES (:c, :o, :a, 'RESCHEDULED', :n, :p, :r, :due)")
			->execute([':c' => $code, ':o' => $origin, ':a' => $date, ':n' => "Compensatorio por domingo $origin", ':p' => $old, ':r' => $reason, ':due' => WorkforceLaborRulesService::CompensatoryDueDate($origin)]);
		WorkforceAutoScheduleService::GetInstance()->LogAudit(null, null, $code, $date, 'COMP_RESCHEDULE', $old, $date, $reason, $userId);
	}

	public function ChangeShift(string $code, string $date, string $shiftCode, string $note, int $userId): void
	{
		$sap = $this->SapCodes();
		$current = $this->CurrentCell($code, $date);
		if ($current === null || !isset($sap[$shiftCode]))
		{
			throw new \DomainException('Turno o fecha no válidos para el cambio');
		}
		$this->WriteCell($code, $date, $this->WorkCell($shiftCode, $sap), $note);
		WorkforceAutoScheduleService::GetInstance()->LogAudit(null, null, $code, $date, 'SHIFT_CHANGE', $current['shift_code'], $shiftCode, $note, $userId);
	}

	public function SwapShifts(string $a, string $b, string $date, string $note, int $userId): void
	{
		$x = $this->CurrentCell($a, $date);
		$y = $this->CurrentCell($b, $date);
		if ($x === null || $y === null || $a === $b)
		{
			throw new \DomainException('Ambas personas deben tener programación ese día');
		}
		$this->WriteCell($a, $date, $y, $note, $y['compensatory_origin_date']);
		$this->WriteCell($b, $date, $x, $note, $x['compensatory_origin_date']);
		WorkforceAutoScheduleService::GetInstance()->LogAudit(null, null, $a, $date, 'SHIFT_SWAP', $x['shift_code'], $y['shift_code'], $note, $userId);
	}

	public function Discard(int $draftId, int $userId): void
	{
		$this->Db()->prepare("UPDATE workforce_schedule_drafts SET status = 'discarded', decided_by_user_id = :u, decided_at = datetime('now', 'localtime') WHERE id = :i AND status = 'draft'")->execute([':u' => $userId, ':i' => $draftId]);
		$this->Log($draftId, 'discarded', 'Borrador descartado', $userId);
	}

	/** Las faltas de cobertura aceptadas pasan al Centro de Alertas y a Tareas (una por mes y sucursal, sin duplicar) */
	private function RaiseCoverageAlerts(int $branchId, string $period, array $report, int $userId): void
	{
		$gaps = array_values(array_filter($report['issues'], fn($i) => $i['type'] === 'no_coverage'));
		if (!$gaps)
		{
			return;
		}
		$first = $gaps[0];
		$total = $report['by_type']['no_coverage'] ?? count($gaps);
		$title = "Falta de cobertura en $period: $total caso(s)";
		$detail = "Primer caso {$first['date']} · {$first['detail']}. Revise Horarios y Cobertura y busque reemplazo.";
		AlertCenterService::GetInstance()->Create(['title' => $title, 'detail' => $detail, 'description' => $detail, 'category' => 'staff', 'alert_type' => 'staff', 'severity' => 'warning', 'branch_id' => $branchId, 'client_token' => "sched-$period-$branchId-" . $total], $userId);
		$this->Db()->prepare('INSERT INTO tasks (name, description, due_date, done, assigned_to_user_id) VALUES (:n, :d, :due, 0, :u)')->execute([':n' => $title, ':d' => $detail, ':due' => $first['date'], ':u' => $userId]);
	}

	/** Panel de supervisión del mes (oficial o borrador activo): domingos, próximo domingo libre, horas, equidad y cobertura */
	public function Dashboard(int $branchId, int $year, int $month): array
	{
		[$start, $end, $period] = $this->Range($year, $month);
		$collabs = $this->Collaborators($branchId);
		[$rows, $draftId] = $this->PeriodRows($branchId, $year, $month);
		$report = $this->Validate($branchId, $year, $month, $rows);
		$history = $this->AnalyzeHistory($branchId, $year, $month);
		$today = date('Y-m-d');
		$future = [];
		foreach ($this->Rows("SELECT payroll_code, MIN(shift_date) AS d FROM workforce_monthly_schedules WHERE is_sunday = 1 AND shift_type = 'LIBRE' AND shift_date >= :t GROUP BY payroll_code", [':t' => $today]) as $r)
		{
			$future[$r['payroll_code']] = $r['d'];
		}
		$by = [];
		foreach ($rows as $r)
		{
			$by[$r['payroll_code']][] = $r;
		}
		$employees = [];
		foreach ($collabs as $c)
		{
			$code = $c['payroll_code'];
			$w = 0;
			$f = 0;
			$hours = 0.0;
			$next = null;
			$counts = ['morning' => 0, 'afternoon' => 0, 'night' => 0, 'absent' => 0];
			foreach ($by[$code] ?? [] as $r)
			{
				$off = self::IsOff($r);
				if (intval($r['is_sunday']) === 1)
				{
					$r['shift_type'] === 'LIBRE' ? $f++ : ($off ? null : $w++);
					if ($r['shift_type'] === 'LIBRE' && $r['shift_date'] >= $today && $next === null)
					{
						$next = $r['shift_date'];
					}
				}
				if (!$off)
				{
					$hours += floatval($r['hours']);
					$counts[$r['shift_type'] === 'NOCHE' ? 'night' : ($r['start_time'] < '12:00' ? 'morning' : 'afternoon')]++;
				}
				elseif (in_array($r['shift_type'], self::ABSENCE_TYPES, true))
				{
					$counts['absent']++;
				}
			}
			$e = $report['equity']['by_employee'][$code] ?? ['openings' => 0, 'closings' => 0, 'equity_pct' => null];
			$employees[] = ['payroll_code' => $code, 'name' => $c['name'], 'department' => $c['department'], 'group' => $c['sunday_group'], 'sundays_worked' => $w, 'sundays_free' => $f, 'next_free_sunday' => $next ?? ($future[$code] ?? null),
				'hours' => round($hours, 1), 'equity_pct' => $e['equity_pct'], 'openings' => $e['openings'], 'closings' => $e['closings'], 'can_night' => intval($c['can_night']),
				'history' => array_diff_key($history[$code], ['last_day' => 1, 'week_hours' => 1, 'codes' => 1, 'rotation_anchor' => 1])] + $counts;
		}
		$grid = [];
		foreach ($rows as $r)
		{
			$grid[$r['payroll_code']][$r['shift_date']] = ['code' => $r['shift_code'], 'type' => $r['shift_type'], 'start' => $r['start_time'], 'end' => $r['end_time'], 'hours' => floatval($r['hours']), 'notes' => $r['notes']];
		}
		$hourly = [];
		$focus = ($today >= $start && $today <= $end) ? $today : $start;
		foreach ($rows as $r)
		{
			if ($r['shift_date'] !== $focus || self::IsOff($r))
			{
				continue;
			}
			$s = intval(substr($r['start_time'], 0, 2));
			$e = intval(substr($r['end_time'], 0, 2));
			$e = $r['end_time'] < $r['start_time'] ? 24 : $e + (substr($r['end_time'], 3, 2) !== '00' ? 1 : 0);
			for ($h = $s; $h < $e; $h++)
			{
				$hourly[$h] = ($hourly[$h] ?? 0) + 1;
			}
		}
		ksort($hourly);
		return ['period' => $period, 'branch_id' => $branchId, 'source' => $draftId ? 'draft' : 'official', 'draft_id' => $draftId, 'state' => $this->PublicationState($branchId, $period), 'employees' => $employees, 'report' => $report,
			'hourly' => ['date' => $focus, 'hours' => $hourly], 'grid' => $grid, 'start' => $start, 'end' => $end, 'confirmations' => $this->Confirmations($branchId, $year, $month), 'versions' => $this->Versions($branchId, $period),
			'holidays' => array_column(WorkforceAutoScheduleService::GetInstance()->GetColombianHolidays($year), 'name', 'holiday_date'), 'sap_codes' => array_keys($this->SapCodes())];
	}

	/** Descansos y próximo domingo libre para la consulta móvil del empleado */
	public function EmployeeRest(int $employeeId): array
	{
		$today = date('Y-m-d');
		$rows = $this->Rows("SELECT shift_date, shift_type, is_sunday FROM workforce_monthly_schedules WHERE employee_id = :e AND status = 'published' AND shift_date BETWEEN :t AND date(:t, '+14 day') AND shift_type IN ('LIBRE', 'COMPENSATORIO', 'VACACIONES', 'INCAPACIDAD', 'PERMISO') ORDER BY shift_date", [':e' => $employeeId, ':t' => $today]);
		$next = $this->Rows("SELECT MIN(shift_date) AS d FROM workforce_monthly_schedules WHERE employee_id = :e AND status = 'published' AND is_sunday = 1 AND shift_type = 'LIBRE' AND shift_date >= :t", [':e' => $employeeId, ':t' => $today])[0]['d'] ?? null;
		return ['rest_days' => $rows, 'next_free_sunday' => $next];
	}
}
