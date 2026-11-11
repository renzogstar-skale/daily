<?php

namespace Grocy\Controllers\Api;

use Grocy\Controllers\Users\User;
use Grocy\Helpers\DairyAccess;
use Grocy\Services\HrService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class HrApiController extends BaseApiController
{
	private function RequireManager(Request $request): void
	{
		if (DairyAccess::GetSimulatedRole() === 'empleado' || !DairyAccess::HasAny([User::PERMISSION_WORKFORCE_SCHEDULE_MANAGE, User::PERMISSION_ADMIN]))
		{
			throw new \Grocy\Middleware\PermissionMissingException($request, User::PERMISSION_WORKFORCE_SCHEDULE_MANAGE);
		}
	}

	public function UpdateCandidateStage(Request $request, Response $response, array $args)
	{
		$this->RequireManager($request);
		$data = $request->getParsedBody();
		$candidateId = intval($args['id'] ?? ($data['id'] ?? 0));
		$stage = $data['stage'] ?? 'postulado';
		$notes = $data['notes'] ?? null;

		$success = HrService::GetInstance()->UpdateCandidateStage($candidateId, $stage, $notes);

		return $this->ApiResponse($response, [
			'success' => $success,
			'candidate_id' => $candidateId,
			'stage' => $stage
		]);
	}

	public function ToggleOnboardingTask(Request $request, Response $response, array $args)
	{
		$this->RequireManager($request);
		$data = $request->getParsedBody();
		$taskId = intval($args['id'] ?? ($data['id'] ?? 0));
		$completed = !empty($data['completed']);

		$success = HrService::GetInstance()->ToggleOnboardingTask($taskId, $completed);

		return $this->ApiResponse($response, [
			'success' => $success,
			'task_id' => $taskId,
			'completed' => $completed
		]);
	}

	public function DecideRequest(Request $request, Response $response, array $args)
	{
		$this->RequireManager($request);
		$data = $request->getParsedBody();
		$requestId = intval($args['id'] ?? ($data['id'] ?? 0));
		$decision = $data['decision'] ?? 'approved';
		$notes = $data['notes'] ?? null;

		$success = HrService::GetInstance()->DecideSwapRequest($requestId, $decision, $notes);

		return $this->ApiResponse($response, [
			'success' => $success,
			'request_id' => $requestId,
			'status' => $decision
		]);
	}

	public function PostCommunication(Request $request, Response $response, array $args)
	{
		$this->RequireManager($request);
		$data = $request->getParsedBody();
		$branchId = intval($data['branch_id'] ?? 1);
		$title = trim($data['title'] ?? '');
		$message = trim($data['message'] ?? '');
		$priority = $data['priority'] ?? 'normal';
		$category = $data['category'] ?? 'general';

		if (empty($title) || empty($message))
		{
			return $this->GenericErrorResponse($response, 'Título y mensaje son obligatorios', 400);
		}

		$commId = HrService::GetInstance()->PostCommunication($branchId, $title, $message, $priority, $category);

		return $this->ApiResponse($response, [
			'success' => true,
			'communication_id' => $commId
		]);
	}
}

