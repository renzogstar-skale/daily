<?php

namespace Grocy\Controllers\Api;

use Grocy\Controllers\Users\User;
use Grocy\Services\WorkforceService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class WorkforceApiController extends BaseApiController
{
	public function GetTasks(Request $request, Response $response, array $args)
	{
		$params = $request->getQueryParams();
		$branchId = $this->DairyRead($request, [User::PERMISSION_TASKS, User::PERMISSION_WORKFORCE, User::PERMISSION_WORKFORCE_SCHEDULE_MANAGE], self::QueryBranch($request)) ?? 1;
		$status = isset($params['status']) ? $params['status'] : 'pending';

		$wfService = WorkforceService::GetInstance();
		return $this->ApiResponse($response, $wfService->GetStoreTasks($branchId, $status));
	}

	public function CompleteTask(Request $request, Response $response, array $args)
	{
		User::CheckPermission($request, User::PERMISSION_TASKS_MARK_COMPLETED);

		try
		{
			$taskId = intval($args['taskId']);
			$b = \Grocy\Services\DatabaseService::GetInstance()->GetDbConnectionRaw()->query('SELECT branch_id FROM workforce_store_tasks WHERE id = ' . $taskId)->fetchColumn();
			$this->DairyRead($request, [User::PERMISSION_TASKS_MARK_COMPLETED], $b === false || $b === null ? null : intval($b), false);
			$body = $this->GetParsedAndFilteredRequestBody($request);
			$wfService = WorkforceService::GetInstance();
			$wfService->CompleteStoreTask($taskId, $body, GROCY_USER_ID);

			return $this->ApiResponse($response, [
				'success' => true,
				'message' => "Tarea de tienda #{$taskId} completada con éxito"
			]);
		}
		catch (\Exception $ex)
		{
			return $this->GenericErrorResponse($response, $ex->getMessage());
		}
	}

	public function QuickWasteReport(Request $request, Response $response, array $args)
	{
		User::CheckPermission($request, User::PERMISSION_WASTE_REPORT);

		try
		{
			$body = $this->GetParsedAndFilteredRequestBody($request);
			$wfService = WorkforceService::GetInstance();
			$result = $wfService->QuickWasteReport($body, GROCY_USER_ID);

			return $this->ApiResponse($response, $result);
		}
		catch (\Exception $ex)
		{
			return $this->GenericErrorResponse($response, $ex->getMessage());
		}
	}

	public function CreateAgreementTask(Request $request, Response $response, array $args)
	{
		User::CheckPermission($request, User::PERMISSION_TASKS);

		try
		{
			$body = $this->GetParsedAndFilteredRequestBody($request);
			$agreementId = intval($body['agreement_id'] ?? 0);
			$branchId = intval($body['branch_id'] ?? 1);
			$title = trim((string)($body['title'] ?? 'Acuerdo de reunión'));
			$role = trim((string)($body['role'] ?? 'clerk'));
			$hours = intval($body['due_hours'] ?? 24);

			$dbRaw = \Grocy\Services\DatabaseService::GetInstance()->GetDbConnectionRaw();

			// Si ya existe una tarea para este acuerdo, la enlazamos sin crear otra copia
			if ($agreementId > 0)
			{
				$stmtCheck = $dbRaw->prepare("SELECT id FROM workforce_store_tasks WHERE source_type = 'agreement' AND source_id = :aid");
				$stmtCheck->execute([':aid' => $agreementId]);
				$existingId = $stmtCheck->fetchColumn();

				if ($existingId)
				{
					return $this->ApiResponse($response, [
						'success' => true,
						'linked' => true,
						'task_id' => intval($existingId),
						'message' => "El acuerdo ya estaba vinculado a la tarea #{$existingId} existente."
					]);
				}
			}

			$dueTime = date('Y-m-d H:i:s', time() + ($hours * 3600));
			$stmt = $dbRaw->prepare("
				INSERT INTO workforce_store_tasks (branch_id, task_type, assigned_role, title, description, due_time, status, source_type, source_id, created_timestamp)
				VALUES (:branch_id, 'agreement', :role, :title, :desc, :due_time, 'pending', 'agreement', :aid, datetime('now', 'localtime'))
			");

			$stmt->execute([
				':branch_id' => $branchId,
				':role' => $role,
				':title' => $title,
				':desc' => 'Tarea derivada de acuerdo de reunión operativa (Acuerdo #' . $agreementId . '). Seguimiento de compromisos.',
				':due_time' => $dueTime,
				':aid' => $agreementId > 0 ? $agreementId : null
			]);

			$newTaskId = intval($dbRaw->lastInsertId());

			return $this->ApiResponse($response, [
				'success' => true,
				'linked' => false,
				'task_id' => $newTaskId,
				'message' => "Acuerdo convertido en tarea #{$newTaskId} correctamente."
			]);
		}
		catch (\Exception $ex)
		{
			return $this->GenericErrorResponse($response, $ex->getMessage());
		}
	}

	public function UpdateTaskStatus(Request $request, Response $response, array $args)
	{
		User::CheckPermission($request, User::PERMISSION_TASKS);

		try
		{
			$taskId = intval($args['taskId']);
			$body = $this->GetParsedAndFilteredRequestBody($request);
			$newStatus = trim((string)($body['status'] ?? 'pending'));
			$validStatuses = ['pending', 'in_progress', 'blocked', 'completed'];

			if (!in_array($newStatus, $validStatuses))
			{
				throw new \Exception("Estado inválido: {$newStatus}");
			}

			$dbRaw = \Grocy\Services\DatabaseService::GetInstance()->GetDbConnectionRaw();
			$stmt = $dbRaw->prepare("UPDATE workforce_store_tasks SET status = :status WHERE id = :id");
			$stmt->execute([':status' => $newStatus, ':id' => $taskId]);

			return $this->ApiResponse($response, [
				'success' => true,
				'task_id' => $taskId,
				'status' => $newStatus,
				'message' => "Estado de la tarea #{$taskId} actualizado a {$newStatus}"
			]);
		}
		catch (\Exception $ex)
		{
			return $this->GenericErrorResponse($response, $ex->getMessage());
		}
	}
}
