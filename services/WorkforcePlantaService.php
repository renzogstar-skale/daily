<?php

namespace Grocy\Services;

/**
 * DAILY — Planta de personal y vistas de control: registro maestro, restricciones, historial dominical, compensatorios, nocturnos y año.
 * Todo se calcula desde la programación real; un dato que la planilla no trae queda como pendiente de configuración, nunca se supone.
 */
class WorkforcePlantaService extends BaseService
{
	const RESTRICTION_TYPES = ['PARCIAL', 'NOCTURNO', 'TOTAL'];

	private static ?WorkforcePlantaService $instance = null;

	public static function GetInstance(): WorkforcePlantaService
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

	public function SaveRestriction(array $d, int $userId): int
	{
		$type = strtoupper((string) ($d['restriction_type'] ?? ''));
		$start = (string) ($d['start_date'] ?? '');
		$end = (string) ($d['end_date'] ?? '');
		$reason = trim((string) ($d['reason'] ?? ''));
		if (!in_array($type, self::RESTRICTION_TYPES, true))
		{
			throw new \Exception('Tipo de restricción no válido');
		}
		if ($start === '' || $end === '' || $end < $start)
		{
			throw new \Exception('Indique el inicio y el fin de la restricción');
		}
		if ($reason === '')
		{
			throw new \Exception('Indique el motivo de la restricción');
		}
		$mode = $type === 'NOCTURNO' ? strtoupper((string) ($d['night_mode'] ?? '')) : null;
		if ($type === 'NOCTURNO' && !in_array($mode, ['DENY', 'ALLOW'], true))
		{
			throw new \Exception('La restricción nocturna debe decir si prohíbe o habilita');
		}
		$days = $d['weekdays'] ?? null;
		$this->Db()->prepare('INSERT INTO workforce_employee_restrictions (payroll_code, restriction_type, start_date, end_date, weekdays, window_start, window_end, max_weekly_hours, night_mode, reason, notes, approved_by, created_by_user_id)
			VALUES (:c, :t, :s, :e, :w, :ws, :we, :h, :m, :r, :n, :a, :u)')
			->execute([':c' => $d['payroll_code'] ?? '', ':t' => $type, ':s' => $start, ':e' => $end, ':w' => is_array($days) && $days ? json_encode(array_map('intval', $days)) : null, ':ws' => ($d['window_start'] ?? '') ?: null, ':we' => ($d['window_end'] ?? '') ?: null,
				':h' => is_numeric($d['max_weekly_hours'] ?? null) ? floatval($d['max_weekly_hours']) : null, ':m' => $mode, ':r' => $reason, ':n' => $d['notes'] ?? null, ':a' => $d['approved_by'] ?? null, ':u' => $userId]);
		return intval($this->Db()->lastInsertId());
	}

	public function DeactivateRestriction(int $id): void
	{
		$this->Db()->prepare('UPDATE workforce_employee_restrictions SET active = 0 WHERE id = :i')->execute([':i' => $id]);
	}

	public function SaveConfig(string $code, array $d): void
	{
		$sequence = trim((string) ($d['comp_sequence'] ?? ''));
		if ($sequence !== '' && !preg_match('/^[1-5](,[1-5])*$/', $sequence))
		{
			throw new \Exception('La secuencia usa días de 1 (lunes) a 5 (viernes) separados por comas');
		}
		$allowed = array_values(array_filter(array_map('trim', explode(',', (string) ($d['allowed_shifts'] ?? '')))));
		$this->Db()->prepare('UPDATE workforce_planilla_collaborators SET contract_type = :t, allowed_shifts = :a, comp_sequence = :s, comp_sequence_start = :st WHERE payroll_code = :c')
			->execute([':t' => trim((string) ($d['contract_type'] ?? '')) ?: null, ':a' => $allowed ? json_encode($allowed) : null, ':s' => $sequence ?: null, ':st' => $sequence ? (($d['comp_sequence_start'] ?? '') ?: date('Y-m-d')) : null, ':c' => $code]);
	}

	/** Filas de un período mezclando lo oficial con el borrador activo de cada mes */
	private function Cells(array $codes, string $from, string $to, int $branchId): array
	{
		$out = [];
		$en = WorkforceScheduleEngineService::GetInstance();
		for ($m = substr($from, 0, 7); $m <= substr($to, 0, 7); $m = date('Y-m', strtotime("$m-01 +1 month")))
		{
			[$y, $n] = array_map('intval', explode('-', $m));
			foreach ($en->PeriodRows($branchId, $y, $n)[0] as $r)
			{
				if (isset($codes[$r['payroll_code']]) && $r['shift_date'] >= $from && $r['shift_date'] <= $to)
				{
					$out[$r['payroll_code']][$r['shift_date']] = $r;
				}
			}
		}
		return $out;
	}

	/** Todo lo que muestran las vistas de control del mes */
	public function Control(int $branchId, int $year, int $month): array
	{
		$en = WorkforceScheduleEngineService::GetInstance();
		$start = sprintf('%04d-%02d-01', $year, $month);
		$end = date('Y-m-t', strtotime($start));
		$today = date('Y-m-d');
		$collabs = $en->Collaborators($branchId);
		$codes = array_flip(array_column($collabs, 'payroll_code'));
		$first = '2026-08-31';
		$cells = $this->Cells($codes, min($first, date('Y-m-d', strtotime("$start -120 days"))), $end, $branchId);
		$holidays = [];
		$auto = WorkforceAutoScheduleService::GetInstance();
		foreach (range($year - 1, $year + 1) as $y)
		{
			foreach ($auto->GetColombianHolidays($y) as $h)
			{
				$holidays[$h['holiday_date']] = $h['name'];
			}
		}
		$isRest = fn(string $d) => date('w', strtotime($d)) === '0' || isset($holidays[$d]);
		$exceptions = $this->Rows('SELECT * FROM workforce_sunday_exceptions ORDER BY original_sunday');
		$byException = [];
		foreach ($exceptions as $e)
		{
			$byException[$e['payroll_code']][] = $e;
		}
		$bank = [];
		foreach ($this->Rows('SELECT * FROM workforce_compensatory_bank ORDER BY origin_sunday') as $b)
		{
			$bank[$b['payroll_code']][$b['origin_sunday']] = $b;
		}
		$restrictions = $en->Restrictions($start, $end);
		$sap = array_column($this->Rows('SELECT code, lunch_hours FROM workforce_sap_shifts'), 'lunch_hours', 'code');
		$availability = [];
		foreach ($this->Rows('SELECT employee_id, COUNT(DISTINCT weekday) AS n FROM employee_availability GROUP BY employee_id') as $a)
		{
			$availability[$a['employee_id']] = intval($a['n']);
		}
		$planta = $sundays = $comps = $nights = $vacations = [];
		foreach ($collabs as $c)
		{
			$code = $c['payroll_code'];
			$cycle = WorkforceLaborRulesService::NewCycle();
			$ledger = [];
			$opens = $closes = $nightsTotal = 0;
			$worked = [];
			$compRows = [];
			$week = [];
			$n = ['nights' => 0, 'effective' => 0.0, 'night_hours' => 0.0, 'rest_hours' => 0.0, 'premium_hours' => 0.0, 'worked_hours' => 0.0, 'legal_night_all' => 0.0];
			foreach ($cells[$code] ?? [] as $d => $r)
			{
				$off = in_array($r['shift_type'], WorkforceScheduleEngineService::OFF_TYPES, true);
				if (intval($r['is_sunday']) === 1)
				{
					$state = $r['shift_type'] === 'LIBRE' ? 'FREE' : ($off ? 'ABSENT' : 'WORKED');
					$cycle = WorkforceLaborRulesService::Step($cycle, $d, $state);
					$ledger[] = ['date' => $d, 'status' => $off ? $r['shift_type'] : 'TRABAJADO', 'holiday' => isset($holidays[$d]) ? 1 : 0, 'streak' => $cycle['streak'], 'notes' => $r['notes']];
					if (!$off)
					{
						$worked[] = $d;
					}
				}
				if ($r['shift_type'] === 'COMPENSATORIO')
				{
					$compRows[] = ['date' => $d, 'origin' => $r['compensatory_origin_date']];
				}
				if ($off)
				{
					if (in_array($r['shift_type'], WorkforceScheduleEngineService::ABSENCE_TYPES, true) && $d >= $start && $d <= $end)
					{
						$vacations[$code][$r['shift_type']][] = $d;
					}
					continue;
				}
				$r['shift_type'] === 'NOCHE' ? $nightsTotal++ : ($r['start_time'] < '12:00' ? $opens++ : $closes++);
				if ($d >= $start && $d <= $end)
				{
					$k = WorkforceLaborRulesService::ClassifyShift($d, $r['start_time'], $r['end_time'], floatval($r['hours']), $isRest);
					$n['worked_hours'] += floatval($r['hours']);
					$n['legal_night_all'] += $k['night'] + $k['rest_night'];
					$n['premium_hours'] += $k['premium_hours'];
					if ($r['shift_type'] === 'NOCHE')
					{
						$n['nights']++;
						$n['effective'] += floatval($r['hours']);
						$n['night_hours'] += $k['night'] + $k['rest_night'];
						$n['rest_hours'] += $k['rest_day'] + $k['rest_night'];
						$monday = date('Y-m-d', strtotime("$d -" . (intval(date('N', strtotime($d))) - 1) . ' days'));
						$week[$monday] = ($week[$monday] ?? 0) + 1;
					}
				}
			}
			$next = WorkforceLaborRulesService::NextFreeSunday($cycle, max($today, $end));
			foreach (array_reverse($ledger) as $l)
			{
				if ($l['date'] >= $today && $l['status'] === 'LIBRE')
				{
					$next = $l['date'];
				}
			}
			$open = WorkforceScheduleEngineService::UnmatchedSundays($worked, $compRows);
			$list = [];
			foreach ($compRows as $x)
			{
				$origin = $x['origin'];
				$b = $origin !== null ? ($bank[$code][$origin] ?? null) : null;
				$list[] = ['origin_sunday' => $origin, 'date' => $x['date'], 'status' => $b !== null && $b['status'] === 'RESCHEDULED' ? 'REPROGRAMADO' : ($x['date'] < $today ? 'DISFRUTADO' : 'PROGRAMADO'), 'previous_date' => $b['previous_date'] ?? null, 'reason' => $b['reprogram_reason'] ?? null, 'entitlement' => $b['entitlement'] ?? null];
			}
			foreach ($open as $origin)
			{
				$due = WorkforceLaborRulesService::CompensatoryDueDate($origin);
				$list[] = ['origin_sunday' => $origin, 'date' => null, 'status' => 'PENDIENTE', 'due_date' => $due, 'overdue' => $due < $today ? 1 : 0, 'entitlement' => $bank[$code][$origin]['entitlement'] ?? null];
			}
			$comps[] = ['payroll_code' => $code, 'name' => $c['name'], 'department' => $c['department'], 'sequence' => $c['comp_sequence'], 'items' => array_values(array_filter($list, fn($x) => ($x['date'] ?? $end) >= date('Y-m-d', strtotime("$start -14 days"))))];
			$sundays[] = ['payroll_code' => $code, 'name' => $c['name'], 'department' => $c['department'], 'ledger' => array_slice($ledger, -9), 'exceptions' => $byException[$code] ?? [], 'streak' => $cycle['streak'], 'next_free' => $next];
			$capacity = round(floatval($c['weekly_hours']) * (intval(date('t', strtotime($start))) / 7), 1);
			$canNight = preg_match(WorkforceScheduleEngineService::NIGHT_DEPARTMENTS, (string) $c['department']) === 1 && intval($c['can_night']) === 1;
			$nights[] = ['payroll_code' => $code, 'name' => $c['name'], 'department' => $c['department'], 'authorized' => $canNight ? 1 : 0, 'total_history' => $nightsTotal, 'max_week' => $week ? max($week) : 0, 'capacity_hours' => $capacity] + array_map(fn($v) => round($v, 2), $n);
			$own = $restrictions[$code] ?? [];
			$vacs = $vacations[$code] ?? [];
			$planta[] = ['payroll_code' => $code, 'temporary' => str_starts_with($code, 'TEMP') ? 1 : 0, 'name' => $c['name'], 'department' => $c['department'], 'cargo' => $c['cargo'], 'contract_type' => $c['contract_type'], 'weekly_hours' => floatval($c['weekly_hours']), 'status' => 'ACTIVO',
				'availability_days' => $availability[$c['employee_id']] ?? null, 'allowed_shifts' => json_decode((string) $c['allowed_shifts'], true) ?: [], 'comp_sequence' => $c['comp_sequence'], 'restrictions' => array_values($own),
				'vacation_days' => count($vacs['VACACIONES'] ?? []), 'sick_days' => count($vacs['INCAPACIDAD'] ?? []), 'sunday_streak' => $cycle['streak'], 'next_free_sunday' => $next, 'pending_comps' => count($open), 'nights_total' => $nightsTotal, 'openings' => $opens, 'closings' => $closes,
				'missing' => array_values(array_filter([empty($c['contract_type']) ? 'Tipo de contrato' : null, isset($availability[$c['employee_id']]) ? null : 'Disponibilidad', empty($c['comp_sequence']) ? 'Secuencia de compensatorio' : null, str_starts_with($code, 'TEMP') ? 'Código definitivo' : null]))];
		}
		$absences = [];
		foreach ($vacations as $code => $types)
		{
			$c = array_values(array_filter($collabs, fn($x) => strval($x['payroll_code']) === strval($code)))[0];
			foreach ($types as $type => $ds)
			{
				$absences[] = ['name' => $c['name'], 'department' => $c['department'], 'type' => $type, 'from' => min($ds), 'to' => max($ds), 'days' => count($ds)];
			}
		}
		return ['planta' => $planta, 'sundays' => $sundays, 'compensatories' => $comps, 'nights' => $nights, 'absences' => $absences, 'year' => $this->YearView($branchId, $year, $collabs),
			'requests' => WorkforceRequestService::GetInstance()->ListFor($branchId), 'special_dates' => $this->Rows('SELECT * FROM workforce_schedule_special_dates WHERE active = 1 AND substr(special_date, 1, 4) = :y ORDER BY special_date', [':y' => strval($year)]),
			'rates' => ['night_from' => WorkforceLaborRulesService::NightStartHour($start), 'night_pct' => WorkforceLaborRulesService::NIGHT_PREMIUM * 100, 'sunday_pct' => WorkforceLaborRulesService::SundayPremium($start) * 100]];
	}

	/** Resumen del año por persona y mes; los meses sin programación muestran solo la proyección de domingos libres */
	public function YearView(int $branchId, int $year, ?array $collabs = null): array
	{
		$en = WorkforceScheduleEngineService::GetInstance();
		$collabs ??= $en->Collaborators($branchId);
		$codes = array_flip(array_column($collabs, 'payroll_code'));
		$cells = $this->Cells($codes, "$year-01-01", "$year-12-31", $branchId);
		$out = [];
		foreach ($collabs as $c)
		{
			$code = $c['payroll_code'];
			$months = array_fill(1, 12, null);
			$cycle = WorkforceLaborRulesService::NewCycle();
			foreach ($cells[$code] ?? [] as $d => $r)
			{
				$m = intval(substr($d, 5, 2));
				$months[$m] ??= ['hours' => 0.0, 'sundays' => 0, 'free' => 0, 'comps' => 0, 'nights' => 0, 'absent' => 0, 'projected' => []];
				$off = in_array($r['shift_type'], WorkforceScheduleEngineService::OFF_TYPES, true);
				$months[$m]['hours'] += $off ? 0 : floatval($r['hours']);
				$months[$m]['comps'] += $r['shift_type'] === 'COMPENSATORIO' ? 1 : 0;
				$months[$m]['nights'] += $r['shift_type'] === 'NOCHE' ? 1 : 0;
				$months[$m]['absent'] += in_array($r['shift_type'], WorkforceScheduleEngineService::ABSENCE_TYPES, true) ? 1 : 0;
				if (intval($r['is_sunday']) === 1)
				{
					$r['shift_type'] === 'LIBRE' ? $months[$m]['free']++ : ($off ? null : $months[$m]['sundays']++);
					$cycle = WorkforceLaborRulesService::Step($cycle, $d, $r['shift_type'] === 'LIBRE' ? 'FREE' : ($off ? 'ABSENT' : 'WORKED'));
				}
			}
			if ($cycle['anchor'] !== null)
			{
				for ($s = WorkforceLaborRulesService::NextFreeSunday($cycle, $cycle['anchor']); $s !== null && $s <= "$year-12-31"; $s = date('Y-m-d', strtotime("$s 12:00 +28 days")))
				{
					$m = intval(substr($s, 5, 2));
					if ($s >= "$year-01-01" && $months[$m] === null)
					{
						$months[$m] = ['projected' => []];
					}
					if ($s >= "$year-01-01" && isset($months[$m]['projected']) && !isset($months[$m]['hours']))
					{
						$months[$m]['projected'][] = $s;
					}
				}
			}
			$out[] = ['payroll_code' => $code, 'name' => $c['name'], 'department' => $c['department'], 'months' => $months];
		}
		return $out;
	}
}
