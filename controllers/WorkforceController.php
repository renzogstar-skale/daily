<?php

namespace Grocy\Controllers;

use Grocy\Controllers\Users\User;
use Grocy\Helpers\DairyAccess;
use Grocy\Services\BranchService;
use Grocy\Services\DatabaseService;
use Grocy\Services\WorkforceService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class WorkforceController extends BaseController
{
	public function StoreFloorView(Request $request, Response $response, array $args)
	{
		$dbRaw = DatabaseService::GetInstance()->GetDbConnectionRaw();
		$params = $request->getQueryParams();
		$branchId = isset($params['branch_id']) ? intval($params['branch_id']) : 1;

		$isEmpSimulated = DairyAccess::GetSimulatedRole() === 'empleado';
		$canManage = !$isEmpSimulated && DairyAccess::HasAny([
			User::PERMISSION_WORKFORCE_SCHEDULE_MANAGE,
			User::PERMISSION_WORKFORCE_ATTENDANCE_MANAGE,
			User::PERMISSION_ADMIN
		]);

		$branches = DairyAccess::FilterBranches($dbRaw->query("SELECT id, name FROM branches WHERE active = 1 ORDER BY name")->fetchAll(\PDO::FETCH_OBJ));

		$wfService = WorkforceService::GetInstance();
		$tasks = $wfService->GetStoreTasks($branchId, 'all');

		if (!$canManage)
		{
			$tasks = array_values(array_filter($tasks, fn($t) => !in_array(strtolower($t->assigned_role ?? ''), ['manager', 'supervisor'], true)));
		}

		// Acuerdos operativos de personal (sin mermas ni equipos de infraestructura)
		$meetingAgreements = [
			[
				'id' => 101,
				'title' => 'Validar apertura de cajas rápidas y check-in de apertura en ' . BranchService::NameById($branchId),
				'meeting' => 'Comité Operativo Semanal #43',
				'date' => date('Y-m-d'),
				'role' => 'supervisor',
				'process' => 'Apertura y Cobertura',
				'priority' => 'Alta',
				'due_hours' => 24
			],
			[
				'id' => 102,
				'title' => 'Refuerzo de rotación de personal en piso previo a franja peak (17:00)',
				'meeting' => 'Minuta de Alineación Diaria',
				'date' => date('Y-m-d'),
				'role' => 'stocker',
				'process' => 'Cobertura Horaria',
				'priority' => 'Media',
				'due_hours' => 6
			],
			[
				'id' => 103,
				'title' => 'Revisión de pausas activas y relevos de descanso en turno de cierre',
				'meeting' => 'Comité Operativo de Personal',
				'date' => date('Y-m-d'),
				'role' => 'cashier',
				'process' => 'Descansos y Turnos',
				'priority' => 'Alta',
				'due_hours' => 12
			]
		];

		$agreementTaskMap = [];
		foreach ($tasks as $t)
		{
			if (($t->source_type ?? '') === 'agreement' && !empty($t->source_id))
			{
				$agreementTaskMap[intval($t->source_id)] = intval($t->id);
			}
		}

		foreach ($meetingAgreements as &$agr)
		{
			$agr['linked_task_id'] = $agreementTaskMap[$agr['id']] ?? null;
		}
		unset($agr);

		$nowTs = time();
		$overdueCount = 0;
		$blockedCount = 0;
		$inProgressCount = 0;
		$completedCount = 0;
		$pendingCount = 0;

		foreach ($tasks as $t)
		{
			$st = strtolower($t->status ?? 'pending');
			$isOverdue = ($st !== 'completed' && !empty($t->due_time) && strtotime($t->due_time) < $nowTs);
			if ($isOverdue)
			{
				$overdueCount++;
			}

			if ($st === 'completed')
			{
				$completedCount++;
			}
			elseif ($st === 'blocked')
			{
				$blockedCount++;
			}
			elseif ($st === 'in_progress')
			{
				$inProgressCount++;
			}
			else
			{
				$pendingCount++;
			}
		}

		$certifications = $canManage ? $dbRaw->query("SELECT * FROM daily_employee_certifications_ext ORDER BY id DESC")->fetchAll(\PDO::FETCH_ASSOC) : [];
		$operationalIncidents = $canManage ? $dbRaw->query("SELECT * FROM daily_operational_incidents ORDER BY id DESC")->fetchAll(\PDO::FETCH_ASSOC) : [];
		$totalTasks = count($tasks);
		$completionPct = $totalTasks > 0 ? round(($completedCount / $totalTasks) * 100, 1) : 100.0;

		return $this->RenderPage($response, 'workforce', [
			'branches' => $branches,
			'tasks' => $tasks,
			'selectedBranchId' => $branchId,
			'canManage' => $canManage,
			'certifications' => $certifications,
			'operationalIncidents' => $operationalIncidents,
			'meetingAgreements' => $canManage ? $meetingAgreements : [],
			'taskMetrics' => [
				'overdue' => $overdueCount,
				'blocked' => $blockedCount,
				'in_progress' => $inProgressCount,
				'completed' => $completedCount,
				'pending' => $pendingCount,
				'total' => $totalTasks,
				'completion_pct' => $completionPct
			]
		]);
	}
}
