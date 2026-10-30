<?php

namespace Grocy\Controllers\Api;

use Grocy\Controllers\Users\User;
use Grocy\Services\MovementLedgerService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class MovementControlApiController extends BaseApiController
{
	// Sella lo pendiente y verifica el historial completo (libro inalterable ↔ stock_log)
	public function VerifyLedger(Request $request, Response $response, array $args)
	{
		User::CheckPermission($request, User::PERMISSION_STOCK);
		try
		{
			return $this->ApiResponse($response, MovementLedgerService::GetInstance()->Verify());
		}
		catch (\Exception $ex)
		{
			return $this->GenericErrorResponse($response, $ex->getMessage());
		}
	}

	public function GetAnomalies(Request $request, Response $response, array $args)
	{
		User::CheckPermission($request, User::PERMISSION_STOCK);
		try
		{
			$q = $request->getQueryParams();
			$branchId = !empty($q['branch_id']) ? intval($q['branch_id']) : null;
			$days = min(365, max(1, intval($q['days'] ?? 30)));
			return $this->ApiResponse($response, MovementLedgerService::GetInstance()->Anomalies($branchId, $days));
		}
		catch (\Exception $ex)
		{
			return $this->GenericErrorResponse($response, $ex->getMessage());
		}
	}

	public function LinkWasteCase(Request $request, Response $response, array $args)
	{
		User::CheckPermission($request, User::PERMISSION_STOCK);
		try
		{
			$logId = intval($args['id']);
			$body = $this->GetParsedBody($request);
			$wasteId = intval($body['waste_record_id'] ?? 0);
			if ($logId <= 0 || $wasteId <= 0)
			{
				throw new \Exception('ID de movimiento o caso de merma no válido');
			}

			$db = \Grocy\Services\DatabaseService::GetInstance()->GetDbConnectionRaw();
			$log = $db->query("SELECT * FROM stock_log WHERE id = $logId")->fetch(\PDO::FETCH_ASSOC);
			if (!$log)
			{
				throw new \Exception('Movimiento no encontrado');
			}
			$waste = $db->query("SELECT * FROM waste_records WHERE id = $wasteId")->fetch(\PDO::FETCH_ASSOC);
			if (!$waste)
			{
				throw new \Exception('Caso de merma no encontrado');
			}

			$txId = $log['transaction_id'] ?: uniqid('tx_', true);
			$db->prepare("UPDATE waste_records SET transaction_id = :tx, inventory_deducted = 1 WHERE id = :wid")
				->execute([':tx' => $txId, ':wid' => $wasteId]);
			$user = \Grocy\Services\UsersService::GetInstance()->GetUser(GROCY_USER_ID);
			$userName = $user ? $user->username : 'usuario';
			$caseNum = $waste['case_number'] ?: ('MRM-' . $waste['id']);
			$note = trim(($log['note'] ?? '') . " [Vinculado a caso {$caseNum} por $userName]");
			$db->prepare("UPDATE stock_log SET note = :n, transaction_id = :tx WHERE id = :id")
				->execute([':n' => $note, ':tx' => $txId, ':id' => $logId]);

			return $this->ApiResponse($response, [
				'ok' => true,
				'message' => "Movimiento #$logId vinculado exitosamente al caso {$caseNum}. No se descontó inventario adicional."
			]);
		}
		catch (\Exception $ex)
		{
			return $this->GenericErrorResponse($response, $ex->getMessage());
		}
	}

	public function JustifyMovement(Request $request, Response $response, array $args)
	{
		User::CheckPermission($request, User::PERMISSION_STOCK);
		try
		{
			$logId = intval($args['id']);
			$body = $this->GetParsedBody($request);
			$reason = trim($body['reason'] ?? '');
			$actionType = $body['action_type'] ?? 'justified';
			if ($logId <= 0 || empty($reason))
			{
				throw new \Exception('Debe ingresar un motivo o justificación');
			}

			$db = \Grocy\Services\DatabaseService::GetInstance()->GetDbConnectionRaw();
			$log = $db->query("SELECT * FROM stock_log WHERE id = $logId")->fetch(\PDO::FETCH_ASSOC);
			if (!$log)
			{
				throw new \Exception('Movimiento no encontrado');
			}

			$user = \Grocy\Services\UsersService::GetInstance()->GetUser(GROCY_USER_ID);
			$userName = $user ? $user->username : 'usuario';
			$tag = $actionType === 'investigation' ? 'Derivado a investigación' : 'Hallazgo justificado';
			$date = date('Y-m-d H:i');
			$note = trim(($log['note'] ?? '') . " [$tag por $userName ($date): $reason]");
			$db->prepare("UPDATE stock_log SET note = :n WHERE id = :id")
				->execute([':n' => $note, ':id' => $logId]);

			return $this->ApiResponse($response, [
				'ok' => true,
				'message' => "Observación registrada con éxito en el movimiento #$logId."
			]);
		}
		catch (\Exception $ex)
		{
			return $this->GenericErrorResponse($response, $ex->getMessage());
		}
	}
}
