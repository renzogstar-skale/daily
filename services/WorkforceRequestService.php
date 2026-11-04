<?php

namespace Grocy\Services;

/**
 * DAILY — Solicitudes de personal: pendiente → aprobada o rechazada → aplicada. Al aprobar se aplica de inmediato sobre lo oficial y el
 * borrador activo y se revalidan los meses tocados. Si el cambio incumple una regla bloqueante, se revierte y queda aprobada con su conflicto.
 */
class WorkforceRequestService extends BaseService
{
	const TYPES = ['VACACIONES', 'PERMISO', 'CAMBIO_TURNO', 'COMPENSATORIO', 'RESTRICCION', 'INTERCAMBIO', 'INCAPACIDAD'];
	const OFF_CELL = ['VACACIONES' => ['VC', 'VACACIONES'], 'PERMISO' => ['PERM', 'PERMISO'], 'INCAPACIDAD' => ['INC', 'INCAPACIDAD']];

	private static ?WorkforceRequestService $instance = null;

	public static function GetInstance(): WorkforceRequestService
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

	public function Create(array $d, int $userId): int
	{
		$type = strtoupper(trim((string) ($d['request_type'] ?? '')));
		$code = trim((string) ($d['payroll_code'] ?? ''));
		$start = (string) ($d['start_date'] ?? '');
		$end = (string) ($d['end_date'] ?? $start);
		if (!in_array($type, self::TYPES, true))
		{
			throw new \Exception('Tipo de solicitud no válido');
		}
		if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end) || $end < $start)
		{
			throw new \Exception('Fechas de la solicitud no válidas');
		}
		$branch = $this->Rows('SELECT branch_id FROM workforce_planilla_collaborators WHERE payroll_code = :c', [':c' => $code])[0]['branch_id'] ?? null;
		if ($branch === null)
		{
			throw new \Exception('Colaborador no encontrado');
		}
		$payload = is_array($d['payload'] ?? null) ? $d['payload'] : [];
		$this->Db()->prepare('INSERT INTO workforce_requests (request_type, payroll_code, branch_id, start_date, end_date, payload_json, reason, created_by_user_id) VALUES (:t, :c, :b, :s, :e, :p, :r, :u)')
			->execute([':t' => $type, ':c' => $code, ':b' => $branch, ':s' => $start, ':e' => $end, ':p' => json_encode($payload), ':r' => $d['reason'] ?? null, ':u' => $userId]);
		return intval($this->Db()->lastInsertId());
	}

	public function ListFor(int $branchId, ?string $status = null): array
	{
		$sql = "SELECT r.*, IFNULL(c.full_name, c.name) AS name, c.department FROM workforce_requests r LEFT JOIN workforce_planilla_collaborators c ON c.payroll_code = r.payroll_code WHERE r.branch_id = :b";
		$p = [':b' => $branchId];
		if ($status !== null)
		{
			$sql .= ' AND r.status = :s';
			$p[':s'] = $status;
		}
		return array_map(fn($r) => ['payload' => json_decode((string) $r['payload_json'], true) ?: [], 'result' => json_decode((string) $r['result_json'], true)] + $r, $this->Rows($sql . ' ORDER BY r.id DESC LIMIT 200', $p));
	}

	private function Get(int $id): array
	{
		$r = $this->Rows('SELECT * FROM workforce_requests WHERE id = :i', [':i' => $id])[0] ?? null;
		if ($r === null)
		{
			throw new \Exception('Solicitud no encontrada');
		}
		return $r;
	}

	public function Decide(int $id, bool $approve, int $userId, ?string $notes = null): array
	{
		$r = $this->Get($id);
		if ($r['status'] !== 'pendiente')
		{
			throw new \Exception('La solicitud ya fue decidida');
		}
		$this->Db()->prepare("UPDATE workforce_requests SET status = :s, decided_by_user_id = :u, decided_at = datetime('now', 'localtime'), decision_notes = :n WHERE id = :i")
			->execute([':s' => $approve ? 'aprobada' : 'rechazada', ':u' => $userId, ':n' => $notes, ':i' => $id]);
		return $approve ? $this->Apply($id, $userId) : ['status' => 'rechazada'];
	}

	/** Aplica una solicitud aprobada. Reintentable: si tuvo un conflicto, se puede aplicar de nuevo cuando se resuelva */
	public function Apply(int $id, int $userId): array
	{
		$r = $this->Get($id);
		if ($r['status'] !== 'aprobada')
		{
			throw new \Exception('Solo se aplica una solicitud aprobada');
		}
		$en = WorkforceScheduleEngineService::GetInstance();
		$branch = intval($r['branch_id']);
		$payload = json_decode((string) $r['payload_json'], true) ?: [];
		$months = $this->Months($r['start_date'], $r['end_date']);
		$before = 0;
		foreach ($months as [$y, $m])
		{
			$before += $en->Validate($branch, $y, $m, $en->PeriodRows($branch, $y, $m)[0])['counts']['legal'];
		}
		$db = $this->Db();
		$db->beginTransaction();
		try
		{
			$this->Execute($r, $payload, $userId);
			$legal = 0;
			$coverage = [];
			foreach ($months as [$y, $m])
			{
				$report = $en->Validate($branch, $y, $m, $en->PeriodRows($branch, $y, $m)[0]);
				$legal += $report['counts']['legal'];
				$coverage = array_merge($coverage, array_values(array_filter($report['issues'], fn($i) => $i['type'] === 'no_coverage' && $i['date'] >= $r['start_date'] && $i['date'] <= $r['end_date'])));
			}
			if ($legal > $before)
			{
				throw new \DomainException(($legal - $before) . ' regla(s) bloqueante(s) nueva(s)');
			}
			$result = ['coverage_gaps' => array_slice($coverage, 0, 20), 'coverage_count' => count($coverage)];
			$db->prepare("UPDATE workforce_requests SET status = 'aplicada', applied_at = datetime('now', 'localtime'), result_json = :j WHERE id = :i")->execute([':j' => json_encode($result), ':i' => $id]);
			$db->commit();
		}
		catch (\DomainException $e)
		{
			$db->rollBack();
			$result = ['conflict' => 'No se aplicó: ' . $e->getMessage() . '. Requiere decisión de gerencia.'];
			$db->prepare('UPDATE workforce_requests SET result_json = :j WHERE id = :i')->execute([':j' => json_encode($result), ':i' => $id]);
			return ['status' => 'aprobada'] + $result;
		}
		catch (\Throwable $e)
		{
			$db->rollBack();
			throw $e;
		}
		foreach ($months as [$y, $m])
		{
			$f = sprintf('%04d-%02d-01', $y, $m);
			WorkforceAutoScheduleService::GetInstance()->SyncToEmployeeShifts($f, date('Y-m-t', strtotime($f)), $branch);
		}
		return ['status' => 'aplicada'] + $result;
	}

	private function Months(string $from, string $to): array
	{
		$out = [];
		for ($m = substr($from, 0, 7); $m <= substr($to, 0, 7); $m = date('Y-m', strtotime("$m-01 +1 month")))
		{
			$out[] = array_map('intval', explode('-', $m));
		}
		return $out;
	}

	private function Execute(array $r, array $payload, int $userId): void
	{
		$en = WorkforceScheduleEngineService::GetInstance();
		$code = $r['payroll_code'];
		$db = $this->Db();
		switch ($r['request_type'])
		{
			case 'VACACIONES':
				$db->prepare("INSERT INTO workforce_vacations (payroll_code, start_date, end_date, notes, status) VALUES (:c, :s, :e, :n, 'approved')")->execute([':c' => $code, ':s' => $r['start_date'], ':e' => $r['end_date'], ':n' => "Solicitud {$r['id']}"]);
				$en->MarkAbsence($code, $r['start_date'], $r['end_date'], self::OFF_CELL['VACACIONES'], "Solicitud {$r['id']}", $userId);
				break;
			case 'PERMISO':
			case 'INCAPACIDAD':
				$employee = $this->Rows('SELECT id FROM employees WHERE payroll_code = :c', [':c' => $code])[0]['id'] ?? null;
				$db->prepare("INSERT INTO employee_absences (employee_id, absence_type, start_date, end_date, status, notes, requested_by_user_id, decided_by_user_id, decided_timestamp) VALUES (:e, :t, :s, :f, 'approved', :n, :u, :u2, datetime('now', 'localtime'))")
					->execute([':e' => $employee, ':t' => $r['request_type'] === 'PERMISO' ? 'permission' : 'sick', ':s' => $r['start_date'], ':f' => $r['end_date'], ':n' => "Solicitud {$r['id']}", ':u' => $r['created_by_user_id'], ':u2' => $userId]);
				$en->MarkAbsence($code, $r['start_date'], $r['end_date'], self::OFF_CELL[$r['request_type']], "Solicitud {$r['id']}", $userId);
				break;
			case 'RESTRICCION':
				WorkforcePlantaService::GetInstance()->SaveRestriction(['payroll_code' => $code, 'start_date' => $r['start_date'], 'end_date' => $r['end_date'], 'reason' => $r['reason'] ?: "Solicitud {$r['id']}"] + $payload, $userId);
				if (($payload['restriction_type'] ?? '') === 'TOTAL')
				{
					$en->MarkAbsence($code, $r['start_date'], $r['end_date'], self::OFF_CELL['PERMISO'], 'Restricción total', $userId);
				}
				break;
			case 'COMPENSATORIO':
				$en->RescheduleCompensatory($code, (string) ($payload['origin_sunday'] ?? ''), $r['start_date'], $r['reason'] ?: "Solicitud {$r['id']}", $userId);
				break;
			case 'CAMBIO_TURNO':
				$en->ChangeShift($code, $r['start_date'], (string) ($payload['shift_code'] ?? ''), "Solicitud {$r['id']}", $userId);
				break;
			case 'INTERCAMBIO':
				$en->SwapShifts($code, (string) ($payload['with_payroll_code'] ?? ''), $r['start_date'], "Solicitud {$r['id']}", $userId);
				break;
		}
	}
}
