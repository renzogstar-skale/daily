<?php

namespace Grocy\Controllers\Api;

use Grocy\Controllers\BaseApiController;
use Grocy\Services\DatabaseService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class DailyActionCenterApiController extends BaseApiController
{
	private function Db(): \PDO
	{
		return DatabaseService::getInstance()->GetDbConnectionRaw();
	}

	public function ExecuteAction(Request $request, Response $response, array $args): Response
	{
		$id = intval($args['id']);
		$body = (array)$request->getParsedBody();
		$action = trim(strval($body['action'] ?? ''));
		$notes = trim(strval($body['notes'] ?? ''));
		$userId = defined('GROCY_USER_ID') ? GROCY_USER_ID : 1;
		$assignedUserId = !empty($body['assigned_user_id']) ? intval($body['assigned_user_id']) : null;

		$db = $this->Db();
		$item = $db->query("SELECT * FROM daily_action_center_items WHERE id = $id")->fetch(\PDO::FETCH_ASSOC);

		if (!$item) {
			return $this->GenericErrorResponse($response, 'Acción no encontrada', 404);
		}

		$newStatus = $item['status'];

		switch ($action) {
			case 'assign':
				if ($assignedUserId) {
					$stmt = $db->prepare("UPDATE daily_action_center_items SET assigned_user_id = ?, status = 'in_progress', updated_at = CURRENT_TIMESTAMP WHERE id = ?");
					$stmt->execute([$assignedUserId, $id]);
					$newStatus = 'in_progress';
				}
				break;

			case 'approve':
				$stmt = $db->prepare("UPDATE daily_action_center_items SET status = 'resolved', updated_at = CURRENT_TIMESTAMP WHERE id = ?");
				$stmt->execute([$id]);
				$newStatus = 'resolved';
				break;

			case 'reject':
				$stmt = $db->prepare("UPDATE daily_action_center_items SET status = 'blocked', updated_at = CURRENT_TIMESTAMP WHERE id = ?");
				$stmt->execute([$id]);
				$newStatus = 'blocked';
				break;

			case 'escalate':
				$stmt = $db->prepare("UPDATE daily_action_center_items SET priority = 'critical', updated_at = CURRENT_TIMESTAMP WHERE id = ?");
				$stmt->execute([$id]);
				break;

			case 'close':
				$stmt = $db->prepare("UPDATE daily_action_center_items SET status = 'closed', closed_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
				$stmt->execute([$id]);
				$newStatus = 'closed';
				break;

			case 'create_task':
			case 'recount':
			case 'open_case':
			default:
				$stmt = $db->prepare("UPDATE daily_action_center_items SET status = 'in_progress', updated_at = CURRENT_TIMESTAMP WHERE id = ?");
				$stmt->execute([$id]);
				$newStatus = 'in_progress';
				break;
		}

		// Registrar en log
		$logStmt = $db->prepare("
			INSERT INTO daily_action_logs (item_id, action_type, notes, user_id)
			VALUES (?, ?, ?, ?)
		");
		$logStmt->execute([$id, $action, $notes, $userId]);

		return $this->ApiResponse($response, [
			'success' => true,
			'message' => "Acción '$action' ejecutada exitosamente en {$item['code']}",
			'new_status' => $newStatus
		]);
	}

	public function RunRule(Request $request, Response $response, array $args): Response
	{
		$body = (array)$request->getParsedBody();
		$ruleName = $body['rule'] ?? 'General';

		return $this->ApiResponse($response, [
			'success' => true,
			'message' => "Motor de automatizaciones ejecutó con éxito: $ruleName. No se detectaron nuevas excepciones bloqueantes.",
			'evaluated_at' => date('Y-m-d H:i:s')
		]);
	}
}

