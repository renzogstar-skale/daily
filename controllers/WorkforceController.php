<?php

namespace Grocy\Controllers;

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

		$branches = $dbRaw->query("SELECT id, name FROM branches ORDER BY name")->fetchAll(\PDO::FETCH_OBJ);
		$products = $dbRaw->query("SELECT id, name, IFNULL(NULLIF(sale_price, 0), 3.99) AS default_sale_price FROM products WHERE active = 1 ORDER BY name")->fetchAll(\PDO::FETCH_OBJ);
		$reasons = $dbRaw->query("SELECT id, code, name, category FROM waste_reasons WHERE active = 1 ORDER BY code")->fetchAll(\PDO::FETCH_OBJ);

		$wfService = WorkforceService::GetInstance();
		$tasks = $wfService->GetStoreTasks($branchId, 'all');

		// Acuerdos de reuniones disponibles para convertir en tareas
		$meetingAgreements = [
			[
				'id' => 101,
				'title' => 'Reubicar sensores de temperatura en vitrinas lácteas de ' . \Grocy\Services\BranchService::NameById(1),
				'meeting' => 'Comité Operativo Semanal #43',
				'date' => '2026-10-26',
				'role' => 'supervisor',
				'process' => 'Equipos y Frío',
				'priority' => 'Alta',
				'due_hours' => 24
			],
			[
				'id' => 102,
				'title' => 'Refuerzo de reposición FEFO en yogurt griego previo a horario peak (17:00)',
				'meeting' => 'Minuta de Alineación Diaria',
				'date' => '2026-10-27',
				'role' => 'stocker',
				'process' => 'Rotación y Mermas',
				'priority' => 'Media',
				'due_hours' => 6
			],
			[
				'id' => 103,
				'title' => 'Auditoría física selectiva de quesos madurados por discrepancia en IRA',
				'meeting' => 'Comité de Control de Pérdidas',
				'date' => '2026-10-27',
				'role' => 'supervisor',
				'process' => 'Inventario',
				'priority' => 'Alta',
				'due_hours' => 12
			]
		];

		// Identificar acuerdos ya convertidos en tareas para enlazarlos sin duplicar
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

		// Contadores métricos de tareas
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

				$rawDb = \Grocy\Services\DatabaseService::GetInstance()->GetDbConnectionRaw();
		$certifications = $rawDb->query("SELECT * FROM daily_employee_certifications_ext ORDER BY id DESC")->fetchAll(\PDO::FETCH_ASSOC);
		$maintenanceLogs = $rawDb->query("SELECT * FROM daily_equipment_maintenance ORDER BY id DESC")->fetchAll(\PDO::FETCH_ASSOC);
		$operationalIncidents = $rawDb->query("SELECT * FROM daily_operational_incidents ORDER BY id DESC")->fetchAll(\PDO::FETCH_ASSOC);
return $this->RenderPage($response, 'workforce', [
			'branches' => $branches,
			'products' => $products,
			'reasons' => $reasons,
			'tasks' => $tasks,
			'selectedBranchId' => $branchId,
			'certifications' => $certifications,
			'maintenanceLogs' => $maintenanceLogs,
			'operationalIncidents' => $operationalIncidents,
			'meetingAgreements' => $meetingAgreements,
			'taskMetrics' => [
				'overdue' => $overdueCount,
				'blocked' => $blockedCount,
				'in_progress' => $inProgressCount,
				'completed' => $completedCount,
				'pending' => $pendingCount,
				'total' => count($tasks)
			]
		]);
	}
}
