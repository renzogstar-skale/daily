<?php

namespace Grocy\Services;

/**
 * Carga la programación real de la planilla (JSON de tools/planilla/extraer_planilla.py) como historia confirmada.
 * Es lo que permite continuar el patrón real: reemplaza los días importados de cada colaborador, marca el origen 'planilla'
 * para que el motor no los reescriba, actualiza quién ha hecho noches y registra las incapacidades que siguen abiertas.
 */
class WorkforcePlanillaImportService extends BaseService
{
	/** Fecha final de una ausencia vigente de la que aún no se conoce el regreso */
	const OPEN_END = '9999-12-31';
	const OFF_MAP = ['LBRE' => ['LIBRE', 'LIBRE'], 'COMP' => ['COMP', 'COMPENSATORIO'], 'VC' => ['VC', 'VACACIONES'], 'INC' => ['INC', 'INCAPACIDAD'], 'LIC' => ['LIC', 'PERMISO'], 'PERM' => ['PERM', 'PERMISO']];

	private static ?WorkforcePlanillaImportService $instance = null;

	public static function GetInstance(): WorkforcePlanillaImportService
	{
		if (self::$instance === null)
		{
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function Import(array $payload, int $userId = 1): array
	{
		$db = DatabaseService::GetInstance()->GetDbConnectionRaw();
		$sap = [];
		foreach ($db->query('SELECT code, start_time, end_time, worked_hours, classification FROM workforce_sap_shifts')->fetchAll(\PDO::FETCH_ASSOC) as $s)
		{
			$sap[$s['code']] = $s;
		}
		$collabs = [];
		foreach ($db->query('SELECT c.payroll_code, e.id AS employee_id FROM workforce_planilla_collaborators c JOIN employees e ON e.payroll_code = c.payroll_code')->fetchAll(\PDO::FETCH_ASSOC) as $c)
		{
			$collabs[$c['payroll_code']] = intval($c['employee_id']);
		}
		$pause = $db->prepare('UPDATE workforce_sap_shifts SET lunch_hours = :l, description = :d WHERE code = :c');
		$holidays = [];
		$auto = WorkforceAutoScheduleService::GetInstance();
		$del = $db->prepare('DELETE FROM workforce_monthly_schedules WHERE payroll_code = :c AND shift_date = :d');
		$ins = $db->prepare("INSERT INTO workforce_monthly_schedules (payroll_code, employee_id, shift_date, shift_code, shift_type, start_time, end_time, hours, is_sunday, is_holiday, notes, status, source)
			VALUES (:c, :e, :d, :code, :type, :st, :et, :h, :sun, :hol, 'Planilla original', 'published', 'planilla')");
		$rows = 0;
		$unknownCodes = [];
		$unknownPeople = [];
		$nights = [];
		$from = '9999-12-31';
		$to = '0000-00-00';

		$db->beginTransaction();
		try
		{
			foreach ($payload['sap'] ?? [] as $s)
			{
				if ($s['pause'] !== '' || floatval($s['lunch']) > 0)
				{
					$pause->execute([':l' => floatval($s['lunch']), ':d' => trim('Pausa saludable ' . $s['pause'] . ' ' . $s['pause_length']), ':c' => $s['code']]);
				}
			}
			foreach ($payload['schedule'] as $code => $days)
			{
				if (!isset($collabs[$code]))
				{
					$unknownPeople[] = $code;
					continue;
				}
				foreach ($days as $date => $raw)
				{
					$year = intval(substr($date, 0, 4));
					if (!isset($holidays[$year]))
					{
						$holidays[$year] = array_flip(array_column($auto->GetColombianHolidays($year), 'holiday_date'));
					}
					$s = $sap[$raw] ?? null;
					if (isset(self::OFF_MAP[$raw]))
					{
						[$cell, $type] = self::OFF_MAP[$raw];
						$st = $et = '00:00';
					}
					elseif ($s !== null)
					{
						$cell = $raw;
						$type = $s['classification'];
						$st = $s['start_time'];
						$et = $s['end_time'];
					}
					else
					{
						$unknownCodes[$raw] = ($unknownCodes[$raw] ?? 0) + 1;
						continue;
					}
					$hours = $raw === 'LIC' ? 7.0 : round(floatval($s['worked_hours'] ?? 0), 2);
					$del->execute([':c' => $code, ':d' => $date]);
					$ins->execute([':c' => $code, ':e' => $collabs[$code], ':d' => $date, ':code' => $cell, ':type' => $type, ':st' => $st, ':et' => $et, ':h' => $hours,
						':sun' => date('w', strtotime($date)) === '0' ? 1 : 0, ':hol' => isset($holidays[$year][$date]) ? 1 : 0]);
					$rows++;
					$from = min($from, $date);
					$to = max($to, $date);
					if ($type === 'NOCHE')
					{
						$nights[$code] = true;
					}
				}
			}
			// Quienes realmente han hecho noches forman el grupo rotativo habilitado
			$night = $db->prepare('UPDATE workforce_skills_matrix SET can_night = 1 WHERE payroll_code = :c');
			foreach (array_keys($nights) as $code)
			{
				$night->execute([':c' => $code]);
			}
			$open = $this->RegisterOpenSickLeaves($db, $payload['schedule'], $collabs, $to, $userId);
			// Los compensatorios generados sobre la programación anterior dejan de valer para los días reemplazados
			$in = implode(',', array_map(fn($c) => $db->quote($c), array_keys($collabs)));
			$db->prepare("DELETE FROM workforce_compensatory_bank WHERE payroll_code IN ($in) AND origin_sunday BETWEEN :f AND :t")->execute([':f' => $from, ':t' => $to]);
			$db->commit();
		}
		catch (\Throwable $e)
		{
			$db->rollBack();
			throw $e;
		}
		foreach ($db->query('SELECT DISTINCT branch_id FROM workforce_planilla_collaborators')->fetchAll(\PDO::FETCH_COLUMN) as $branch)
		{
			$auto->SyncToEmployeeShifts($from, $to, intval($branch));
		}
		return ['rows' => $rows, 'from' => $from, 'to' => $to, 'sheets_used' => $payload['sheets_used'] ?? [], 'sheets_skipped' => $payload['sheets_skipped'] ?? [], 'night_pool' => array_keys($nights),
			'open_sick_leaves' => $open, 'unknown_codes' => $unknownCodes, 'unknown_people' => $unknownPeople];
	}

	/** Si el último día importado de alguien es INC, la incapacidad sigue abierta: se registra sin fecha de regreso hasta que se confirme */
	private function RegisterOpenSickLeaves(\PDO $db, array $schedule, array $collabs, string $lastDate, int $userId): array
	{
		$open = [];
		$exists = $db->prepare("SELECT COUNT(*) FROM employee_absences WHERE employee_id = :e AND absence_type = 'sick' AND status = 'approved' AND end_date = :end");
		$ins = $db->prepare("INSERT INTO employee_absences (employee_id, absence_type, start_date, end_date, status, notes, requested_by_user_id, decided_by_user_id, decided_timestamp)
			VALUES (:e, 'sick', :s, :end, 'approved', 'Incapacidad vigente en la planilla original, sin fecha de regreso', :u, :u2, datetime('now', 'localtime'))");
		foreach ($schedule as $code => $days)
		{
			if (!isset($collabs[$code]) || ($days[$lastDate] ?? '') !== 'INC')
			{
				continue;
			}
			ksort($days);
			$start = $lastDate;
			foreach (array_reverse($days, true) as $d => $v)
			{
				if ($v !== 'INC')
				{
					break;
				}
				$start = $d;
			}
			$open[] = ['payroll_code' => $code, 'since' => $start];
			$exists->execute([':e' => $collabs[$code], ':end' => self::OPEN_END]);
			if (intval($exists->fetchColumn()) === 0)
			{
				$ins->execute([':e' => $collabs[$code], ':s' => $start, ':end' => self::OPEN_END, ':u' => $userId, ':u2' => $userId]);
			}
		}
		return $open;
	}
}
