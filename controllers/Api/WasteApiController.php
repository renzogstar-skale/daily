<?php

namespace Grocy\Controllers\Api;

use Grocy\Controllers\Users\User;
use Grocy\Services\WasteService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class WasteApiController extends BaseApiController
{
	public function GetWasteReasons(Request $request, Response $response, array $args)
	{
		$params = $request->getQueryParams();
		$activeOnly = !isset($params['all']);
		$category = $params['category'] ?? null;
		$riskLevel = $params['risk_level'] ?? null;
		$area = $params['area'] ?? null;

		$wasteService = WasteService::GetInstance();
		return $this->ApiResponse($response, $wasteService->GetWasteReasons($activeOnly, $category, $riskLevel, $area));
	}

	public function GetWasteReason(Request $request, Response $response, array $args)
	{
		$id = intval($args['reasonId']);
		$wasteService = WasteService::GetInstance();
		$reason = $wasteService->GetWasteReason($id);
		if (!$reason)
		{
			return $this->GenericErrorResponse($response, 'Causal no encontrada', 404);
		}
		return $this->ApiResponse($response, $reason);
	}

	public function SaveWasteReason(Request $request, Response $response, array $args)
	{
		User::CheckPermission($request, User::PERMISSION_WASTE_REASONS_EDIT);

		try
		{
			$requestBody = $this->GetParsedAndFilteredRequestBody($request);
			$wasteService = WasteService::GetInstance();
			$saved = $wasteService->SaveWasteReason($requestBody);
			return $this->ApiResponse($response, [
				'success' => true,
				'reason' => $saved,
				'message' => 'Causal de merma guardada exitosamente'
			]);
		}
		catch (\Exception $ex)
		{
			return $this->GenericErrorResponse($response, $ex->getMessage());
		}
	}

	public function ToggleWasteReasonActive(Request $request, Response $response, array $args)
	{
		User::CheckPermission($request, User::PERMISSION_WASTE_REASONS_EDIT);

		try
		{
			$id = intval($args['reasonId']);
			$wasteService = WasteService::GetInstance();
			$toggled = $wasteService->ToggleWasteReasonActive($id);
			return $this->ApiResponse($response, [
				'success' => true,
				'reason' => $toggled,
				'message' => ($toggled->active == 1) ? 'Causal reactivada' : 'Causal desactivada (preservando historial)'
			]);
		}
		catch (\Exception $ex)
		{
			return $this->GenericErrorResponse($response, $ex->getMessage());
		}
	}

	public function GetWasteRecords(Request $request, Response $response, array $args)
	{
		$this->AssertCanRead($request);
		$params = $request->getQueryParams();
		$branchId = $this->ScopeBranch($request, $params);
		$status = $params['status'] ?? null;
		$workflowStatus = $params['workflow_status'] ?? null;
		$riskLevel = $params['risk_level'] ?? null;
		$category = $params['category'] ?? null;
		$startDate = $params['start_date'] ?? null;
		$endDate = $params['end_date'] ?? null;
		$reasonId = isset($params['reason_id']) && $params['reason_id'] !== '' ? intval($params['reason_id']) : null;

		$wasteService = WasteService::GetInstance();
		$records = $wasteService->GetWasteRecords(
			$branchId,
			$status,
			$workflowStatus,
			$riskLevel,
			$category,
			$startDate,
			$endDate,
			$reasonId
		);
		return $this->ApiResponse($response, \Grocy\Helpers\DairyAccess::FilterBranches($records));
	}

	public function GetCaseDetails(Request $request, Response $response, array $args)
	{
		$this->AssertWasteBranch($request, intval($args['caseId']));
		$caseId = intval($args['caseId']);
		$wasteService = WasteService::GetInstance();
		$record = $wasteService->GetWasteRecord($caseId);
		if (!$record)
		{
			return $this->GenericErrorResponse($response, 'Caso no encontrado', 404);
		}
		return $this->ApiResponse($response, $record);
	}

	public function ReportWaste(Request $request, Response $response, array $args)
	{
		User::CheckPermission($request, User::PERMISSION_WASTE_REPORT);

		try
		{
			$requestBody = $this->GetParsedAndFilteredRequestBody($request);

			$productId = intval($requestBody['product_id'] ?? 0);
			$amount = floatval($requestBody['amount'] ?? 0);
			$wasteReasonId = intval($requestBody['waste_reason_id'] ?? 0);
			$branchId = intval($requestBody['branch_id'] ?? 1);
			$locationId = !empty($requestBody['location_id']) ? intval($requestBody['location_id']) : null;
			$specificStockEntryId = $requestBody['stock_id'] ?? 'default';
			$notes = $requestBody['notes'] ?? null;
			$evidencePhotoPath = $requestBody['evidence_photo_path'] ?? null;
			$subReason = $requestBody['sub_reason'] ?? null;
			$batchNumber = $requestBody['batch_number'] ?? null;
			$bestBeforeDate = $requestBody['best_before_date'] ?? null;
			$investigatorUserId = !empty($requestBody['investigator_user_id']) ? intval($requestBody['investigator_user_id']) : null;
			$dueDate = $requestBody['due_date'] ?? null;
			$idempotencyKey = $requestBody['idempotency_key'] ?? null;

			// Etapa 36: destino final y peso (todos); modalidad/canal de hurto solo con permiso de prevención. Se validan antes de crear la merma.
			$canTheft = \Grocy\Helpers\DairyAccess::HasAny([User::PERMISSION_LOSS_PREVENTION, User::PERMISSION_WASTE_APPROVE, User::PERMISSION_ADMIN]);
			$details = array_filter(array_intersect_key($requestBody, array_flip($canTheft ? ['final_destination', 'weight_kg', 'theft_modality', 'detection_channel', 'recovered_goods_value'] : ['final_destination', 'weight_kg'])), fn($v) => $v !== null && $v !== '');
			if (isset($details['final_destination']) && !isset(\Grocy\Services\LossCensusService::DESTINATIONS[$details['final_destination']]))
			{
				throw new \Exception('Destino final inválido');
			}
			if ((isset($details['weight_kg']) && floatval($details['weight_kg']) < 0) || (isset($details['recovered_goods_value']) && floatval($details['recovered_goods_value']) < 0))
			{
				throw new \Exception('El peso y la recuperación no pueden ser negativos');
			}
			if (isset($details['theft_modality']) && !in_array($details['theft_modality'], array_merge(...array_values(array_map('array_keys', \Grocy\Services\LossCensusService::MODALITIES))), true))
			{
				throw new \Exception('Modalidad de hurto inválida');
			}
			if (isset($details['detection_channel']) && !isset(\Grocy\Services\LossCensusService::CHANNELS[$details['detection_channel']]))
			{
				throw new \Exception('Canal de detección inválido');
			}

			$wasteService = WasteService::GetInstance();
			$wasteRow = $wasteService->ReportWaste(
				$productId,
				$amount,
				$wasteReasonId,
				$branchId,
				$locationId,
				$specificStockEntryId,
				$notes,
				$evidencePhotoPath,
				$subReason,
				$batchNumber,
				$bestBeforeDate,
				$investigatorUserId,
				$dueDate,
				$idempotencyKey,
				GROCY_USER_ID,
				false,
				!empty($requestBody['confirm_duplicate'])
			);

			// Etapa 36: destino final, peso y (con permiso de prevención) modalidad/canal de hurto
			if ($details)
			{
				\Grocy\Services\LossCensusService::GetInstance()->SetWasteDetails(intval($wasteRow->id), $details, $canTheft);
			}

			return $this->ApiResponse($response, [
				'success' => true,
				'waste_id' => $wasteRow->id,
				'case_number' => $wasteRow->case_number,
				'status' => $wasteRow->status,
				'workflow_status' => $wasteRow->workflow_status,
				'total_cost' => $wasteRow->total_cost,
				'message' => ($wasteRow->status === 'approved')
					? "Caso {$wasteRow->case_number} registrado y existencias descontadas con éxito"
					: "Caso {$wasteRow->case_number} registrado formalmente en espera de autorización previa"
			]);
		}
		catch (\Exception $ex)
		{
			return $this->GenericErrorResponse($response, $ex->getMessage());
		}
	}

	public function UpdateCaseInvestigation(Request $request, Response $response, array $args)
	{
		User::CheckPermission($request, User::PERMISSION_WASTE_APPROVE);
		$this->AssertWasteBranch($request, intval($args['caseId']));

		try
		{
			$caseId = intval($args['caseId']);
			$requestBody = $this->GetParsedAndFilteredRequestBody($request);

			$wasteService = WasteService::GetInstance();
			$updated = $wasteService->UpdateCaseWorkflow($caseId, $requestBody, GROCY_USER_ID);

			return $this->ApiResponse($response, [
				'success' => true,
				'case' => $updated,
				'message' => "Investigación del caso {$updated->case_number} actualizada (Estado: {$updated->workflow_status})"
			]);
		}
		catch (\Exception $ex)
		{
			return $this->GenericErrorResponse($response, $ex->getMessage());
		}
	}

	// Aprobar o rechazar exige acceso a la sucursal de la merma (el filtro por branch_id de la petición no cubre el id en la ruta)
	private function AssertCanRead(Request $request): void
	{
		if (!\Grocy\Helpers\DairyAccess::HasAny([User::PERMISSION_WASTE_REPORT, User::PERMISSION_WASTE_APPROVE, User::PERMISSION_KPIS_VIEW]))
		{
			throw new \Grocy\Controllers\Users\PermissionMissingException($request, User::PERMISSION_WASTE_REPORT);
		}
	}

	// Sucursal pedida validada; sin sucursal y con acceso restringido a una sola: esa (analítica exige una)
	private function ScopeBranch(Request $request, array $params, bool $mustBeSingle = false): ?int
	{
		$branchId = isset($params['branch_id']) && $params['branch_id'] !== '' ? intval($params['branch_id']) : null;
		if ($branchId !== null && !\Grocy\Helpers\DairyAccess::CanSeeBranch($branchId))
		{
			throw new \Grocy\Controllers\Users\PermissionMissingException($request, 'BRANCH #' . $branchId);
		}
		$allowed = \Grocy\Helpers\DairyAccess::AllowedBranches();
		if ($branchId === null && $allowed !== null && $mustBeSingle)
		{
			if (count($allowed) !== 1)
			{
				throw new \Grocy\Controllers\Users\PermissionMissingException($request, 'branch_id');
			}
			$branchId = $allowed[0];
		}
		return $branchId;
	}

	private function AssertWasteBranch(Request $request, int $wasteId): void
	{
		$branch = \Grocy\Services\DatabaseService::GetInstance()->GetDbConnectionRaw()->query('SELECT branch_id FROM waste_records WHERE id = ' . $wasteId)->fetchColumn();
		if ($branch !== false && !\Grocy\Helpers\DairyAccess::CanSeeBranch(intval($branch)))
		{
			throw new \Grocy\Controllers\Users\PermissionMissingException($request, 'BRANCH #' . intval($branch));
		}
	}

	public function ApproveWaste(Request $request, Response $response, array $args)
	{
		User::CheckPermission($request, User::PERMISSION_WASTE_APPROVE);
		$this->AssertWasteBranch($request, intval($args['wasteId']));

		try
		{
			$wasteId = intval($args['wasteId']);
			$wasteService = WasteService::GetInstance();
			$wasteRecord = $wasteService->ApproveWaste($wasteId, GROCY_USER_ID);

			return $this->ApiResponse($response, [
				'success' => true,
				'waste_id' => $wasteRecord->id,
				'case_number' => $wasteRecord->case_number,
				'status' => $wasteRecord->status,
				'message' => "Caso {$wasteRecord->case_number} autorizado y descontado del inventario"
			]);
		}
		catch (\Exception $ex)
		{
			return $this->GenericErrorResponse($response, $ex->getMessage());
		}
	}

	public function RejectWaste(Request $request, Response $response, array $args)
	{
		User::CheckPermission($request, User::PERMISSION_WASTE_APPROVE);
		$this->AssertWasteBranch($request, intval($args['wasteId']));

		try
		{
			$wasteId = intval($args['wasteId']);
			$requestBody = $this->GetParsedAndFilteredRequestBody($request);
			$reason = trim(strval($requestBody['rejection_reason'] ?? ''));
			if (mb_strlen($reason) < 5)
			{
				throw new \Exception('Indique el motivo del rechazo (mínimo 5 caracteres): lo verá quien reportó la merma');
			}

			$wasteService = WasteService::GetInstance();
			$wasteRecord = $wasteService->RejectWaste($wasteId, GROCY_USER_ID, $reason);

			return $this->ApiResponse($response, [
				'success' => true,
				'waste_id' => $wasteRecord->id,
				'case_number' => $wasteRecord->case_number,
				'status' => $wasteRecord->status,
				'message' => "Caso {$wasteRecord->case_number} rechazado correctamente"
			]);
		}
		catch (\Exception $ex)
		{
			return $this->GenericErrorResponse($response, $ex->getMessage());
		}
	}

	public function RequestWasteInfo(Request $request, Response $response, array $args)
	{
		User::CheckPermission($request, User::PERMISSION_WASTE_APPROVE);
		$this->AssertWasteBranch($request, intval($args['wasteId']));

		try
		{
			$note = trim(strval($this->GetParsedAndFilteredRequestBody($request)['note'] ?? ''));
			if (mb_strlen($note) < 5)
			{
				throw new \Exception('Indique qué información falta (mínimo 5 caracteres)');
			}
			$wasteRecord = WasteService::GetInstance()->RequestWasteInfo(intval($args['wasteId']), $note, GROCY_USER_ID);
			return $this->ApiResponse($response, ['success' => true, 'waste_id' => $wasteRecord->id, 'message' => "Información solicitada para {$wasteRecord->case_number}"]);
		}
		catch (\Exception $ex)
		{
			return $this->GenericErrorResponse($response, $ex->getMessage());
		}
	}

	public function GetWasteAnalytics(Request $request, Response $response, array $args)
	{
		$this->AssertCanRead($request);
		$params = $request->getQueryParams();
		$branchId = $this->ScopeBranch($request, $params, true);
		$period = $params['period'] ?? 'month';
		$startDate = $params['start_date'] ?? null;
		$endDate = $params['end_date'] ?? null;

		$wasteService = WasteService::GetInstance();
		$analytics = $wasteService->GetWasteAnalytics($branchId, $period, $startDate, $endDate);
		return $this->ApiResponse($response, $analytics);
	}

	public function UploadWastePhoto(Request $request, Response $response, array $args)
	{
		User::CheckPermission($request, User::PERMISSION_WASTE_REPORT);

		try
		{
			$uploadedFiles = $request->getUploadedFiles();
			if (empty($uploadedFiles['photo']))
			{
				throw new \Exception('No se recibió ningún archivo');
			}

			$photo = $uploadedFiles['photo'];
			if ($photo->getError() !== UPLOAD_ERR_OK)
			{
				throw new \Exception('Error al subir el archivo');
			}

			$storageDir = GROCY_DATAPATH . '/storage/waste';
			if (!file_exists($storageDir))
			{
				mkdir($storageDir, 0777, true);
			}

			// Solo imágenes reales (la extensión se deduce del contenido, no del nombre enviado) y hasta 10 MB
			if ($photo->getSize() > 10 * 1024 * 1024)
			{
				throw new \Exception('La foto supera 10 MB');
			}
			$mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer((string) $photo->getStream());
			$types = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif', 'image/heic' => 'heic', 'image/heif' => 'heic'];
			if (!isset($types[$mime]))
			{
				throw new \Exception('El archivo no es una imagen válida (JPG, PNG, WEBP, GIF o HEIC)');
			}
			$safeFileName = 'waste_' . date('Ymd_His') . '_' . uniqid() . '.' . $types[$mime];
			$targetPath = $storageDir . '/' . $safeFileName;

			$photo->moveTo($targetPath);

			return $this->ApiResponse($response, [
				'success' => true,
				'file_name' => $safeFileName,
				'relative_url' => '/api/waste/photo/' . $safeFileName
			]);
		}
		catch (\Exception $ex)
		{
			return $this->GenericErrorResponse($response, $ex->getMessage());
		}
	}

	public function ServeWastePhoto(Request $request, Response $response, array $args)
	{
		$this->AssertCanRead($request);
		$fileName = basename($args['fileName']);
		$filePath = GROCY_DATAPATH . '/storage/waste/' . $fileName;
		// La evidencia hereda la sucursal de la merma que la usa
		$stmt = \Grocy\Services\DatabaseService::GetInstance()->GetDbConnectionRaw()->prepare('SELECT branch_id FROM waste_records WHERE evidence_photo_path LIKE :f');
		$stmt->execute([':f' => '%' . $fileName]);
		foreach ($stmt->fetchAll(\PDO::FETCH_COLUMN) as $b)
		{
			if (!\Grocy\Helpers\DairyAccess::CanSeeBranch(intval($b)))
			{
				throw new \Grocy\Controllers\Users\PermissionMissingException($request, 'BRANCH #' . intval($b));
			}
		}

		if (!file_exists($filePath))
		{
			return $response->withStatus(404);
		}

		$mimeType = mime_content_type($filePath);
		$response = $response->withHeader('Content-Type', $mimeType);
		$response->getBody()->write(file_get_contents($filePath));
		return $response;
	}

	public function SaveActionPlan(Request $request, Response $response, array $args)
	{
		User::CheckPermission($request, User::PERMISSION_WASTE_APPROVE);

		try
		{
			$body = $this->GetParsedAndFilteredRequestBody($request);
			$db = \Grocy\Services\DatabaseService::GetInstance()->GetDbConnectionRaw();
			$stmt = $db->prepare("
				INSERT INTO waste_action_plans (activity_title, problem_description, follow_up_actions, waste_reason_id, branch_id, assigned_user_id, priority, start_date, due_date, status, cost_avoided_estimate)
				VALUES (:activity, :problem, :follow_up, :reason_id, :branch_id, :user_id, :priority, :start_date, :due_date, 'in_progress', :cost_avoided)
			");
			$stmt->execute([
				':activity' => $body['activity_title'],
				':problem' => $body['problem_description'],
				':follow_up' => $body['follow_up_actions'],
				':reason_id' => !empty($body['waste_reason_id']) ? intval($body['waste_reason_id']) : null,
				':branch_id' => intval($body['branch_id'] ?? 1),
				':user_id' => intval($body['assigned_user_id'] ?? GROCY_USER_ID),
				':priority' => $body['priority'] ?? 'alta',
				':start_date' => $body['start_date'] ?? date('Y-m-d'),
				':due_date' => $body['due_date'] ?? date('Y-m-d', strtotime('+7 days')),
				':cost_avoided' => floatval($body['cost_avoided_estimate'] ?? 0)
			]);
			return $this->ApiResponse($response, ['success' => true, 'id' => intval($db->lastInsertId()), 'message' => 'Plan de acción AKCAL creado exitosamente']);
		}
		catch (\Exception $ex)
		{
			return $this->GenericErrorResponse($response, $ex->getMessage());
		}
	}

	public function UpdateActionPlanStatus(Request $request, Response $response, array $args)
	{
		User::CheckPermission($request, User::PERMISSION_WASTE_APPROVE);

		try
		{
			$planId = intval($args['planId']);
			$body = $this->GetParsedAndFilteredRequestBody($request);
			$db = \Grocy\Services\DatabaseService::GetInstance()->GetDbConnectionRaw();
			$stmt = $db->prepare("
				UPDATE waste_action_plans SET
					status = :status,
					evidence_notes = COALESCE(:notes, evidence_notes),
					updated_timestamp = datetime('now', 'localtime')
				WHERE id = :id
			");
			$stmt->execute([
				':id' => $planId,
				':status' => $body['status'],
				':notes' => $body['evidence_notes'] ?? null
			]);
			return $this->ApiResponse($response, ['success' => true, 'message' => 'Estado de plan de acción actualizado']);
		}
		catch (\Exception $ex)
		{
			return $this->GenericErrorResponse($response, $ex->getMessage());
		}
	}

	public function SaveSupplierReturn(Request $request, Response $response, array $args)
	{
		User::CheckPermission($request, User::PERMISSION_WASTE_APPROVE);

		try
		{
			$body = $this->GetParsedAndFilteredRequestBody($request);
			if (empty($body['supplier_id']) || empty($body['product_id']) || floatval($body['amount'] ?? 0) <= 0)
			{
				throw new \Exception('Proveedor, producto y cantidad son obligatorios');
			}
			$productId = intval($body['product_id']);
			$amount = floatval($body['amount']);

			$db = \Grocy\Services\DatabaseService::GetInstance()->GetDbConnectionRaw();
			$stmtP = $db->prepare("
				SELECT p.qu_id_stock,
					IFNULL((SELECT SUM(s.amount * s.price) / NULLIF(SUM(s.amount), 0) FROM stock s WHERE s.product_id = p.id AND s.price > 0),
						IFNULL((SELECT plp.price FROM products_last_purchased plp WHERE plp.product_id = p.id), 0)) AS unit_cost
				FROM products p WHERE p.id = :id
			");
			$stmtP->execute([':id' => $productId]);
			$prod = $stmtP->fetch(\PDO::FETCH_ASSOC);
			if (!$prod)
			{
				throw new \Exception('Producto no encontrado');
			}
			$unitCost = round(floatval($prod['unit_cost']), 4);
			$totalLoss = round($unitCost * $amount, 2);
			$requested = isset($body['credit_requested_amount']) && $body['credit_requested_amount'] !== '' ? floatval($body['credit_requested_amount']) : $totalLoss;

			$next = intval($db->query('SELECT IFNULL(MAX(id), 0) + 1 FROM supplier_returns')->fetchColumn());
			$stmt = $db->prepare("
				INSERT INTO supplier_returns (supplier_id, branch_id, product_id, case_number, batch_number, amount, qu_id, unit_cost, total_loss_cost, return_reason, incident_type, credit_requested_amount, credit_recovered_amount, loss_unrecovered_amount, status, notes, user_id)
				VALUES (:supplier_id, :branch_id, :product_id, :case_number, :batch, :amount, :qu_id, :unit_cost, :total_loss, :reason, :type, :requested, 0, :unrecovered, 'pending_credit', :notes, :user_id)
			");
			$stmt->execute([
				':supplier_id' => intval($body['supplier_id']),
				':branch_id' => intval($body['branch_id'] ?? 1),
				':product_id' => $productId,
				':case_number' => 'RCL-' . date('Y') . '-' . str_pad((string) $next, 4, '0', STR_PAD_LEFT),
				':batch' => $body['batch_number'] ?? null,
				':amount' => $amount,
				':qu_id' => $prod['qu_id_stock'],
				':unit_cost' => $unitCost,
				':total_loss' => $totalLoss,
				':reason' => $body['return_reason'] ?? null,
				':type' => $body['incident_type'] ?? 'other',
				':requested' => $requested,
				':unrecovered' => $totalLoss,
				':notes' => $body['notes'] ?? null,
				':user_id' => GROCY_USER_ID
			]);
			return $this->ApiResponse($response, ['success' => true, 'id' => intval($db->lastInsertId()), 'message' => 'Reclamo a proveedor registrado']);
		}
		catch (\Exception $ex)
		{
			return $this->GenericErrorResponse($response, $ex->getMessage());
		}
	}

	public function RecordSupplierCredit(Request $request, Response $response, array $args)
	{
		User::CheckPermission($request, User::PERMISSION_WASTE_APPROVE);

		try
		{
			$returnId = intval($args['returnId']);
			$body = $this->GetParsedAndFilteredRequestBody($request);
			$recovered = floatval($body['credit_recovered_amount'] ?? 0);

			$db = \Grocy\Services\DatabaseService::GetInstance()->GetDbConnectionRaw();
			$stmt = $db->prepare("
				UPDATE supplier_returns SET
					credit_recovered_amount = :rec,
					loss_unrecovered_amount = MAX(0, total_loss_cost - :rec),
					status = 'credit_recovered',
					notes = COALESCE(:notes, notes),
					resolved_timestamp = datetime('now', 'localtime')
				WHERE id = :id
			");
			$stmt->execute([':rec' => $recovered, ':notes' => $body['notes'] ?? null, ':id' => $returnId]);
			if ($stmt->rowCount() === 0)
			{
				throw new \Exception('Reclamo no encontrado');
			}
			return $this->ApiResponse($response, ['success' => true, 'message' => 'Nota de crédito registrada']);
		}
		catch (\Exception $ex)
		{
			return $this->GenericErrorResponse($response, $ex->getMessage());
		}
	}

	public function ScheduleCycleCount(Request $request, Response $response, array $args)
	{
		User::CheckPermission($request, User::PERMISSION_STOCK_INVENTORY);

		try
		{
			$body = $this->GetParsedAndFilteredRequestBody($request);
			$productId = intval($body['product_id'] ?? 0);
			if ($productId <= 0)
			{
				throw new \Exception('Seleccione un producto');
			}
			$locationId = !empty($body['location_id']) ? intval($body['location_id']) : null;

			$db = \Grocy\Services\DatabaseService::GetInstance()->GetDbConnectionRaw();

			// Stock teórico al momento de programar (por ubicación si se indica)
			$stmtTheo = $db->prepare('SELECT IFNULL(SUM(amount), 0) FROM stock WHERE product_id = :pid' . ($locationId ? ' AND location_id = :lid' : ''));
			$stmtTheo->execute($locationId ? [':pid' => $productId, ':lid' => $locationId] : [':pid' => $productId]);
			$theoretical = floatval($stmtTheo->fetchColumn());

			$stmt = $db->prepare("
				INSERT INTO stock_cycle_counts (branch_id, product_id, location_id, scheduled_date, status, theoretical_stock, assigned_user_id, notes)
				VALUES (:branch_id, :product_id, :location_id, :scheduled_date, 'pending', :theo, :user_id, :notes)
			");
			$stmt->execute([
				':branch_id' => intval($body['branch_id'] ?? 1),
				':product_id' => $productId,
				':location_id' => $locationId,
				':scheduled_date' => $body['scheduled_date'] ?? date('Y-m-d'),
				':theo' => $theoretical,
				':user_id' => intval($body['assigned_user_id'] ?? GROCY_USER_ID),
				':notes' => $body['notes'] ?? null
			]);
			return $this->ApiResponse($response, ['success' => true, 'id' => intval($db->lastInsertId()), 'message' => 'Conteo cíclico programado']);
		}
		catch (\Exception $ex)
		{
			return $this->GenericErrorResponse($response, $ex->getMessage());
		}
	}

	// Programación automática por clase ABC (dry_run = solo vista previa)
	public function AutoScheduleCycleCounts(Request $request, Response $response, array $args)
	{
		User::CheckPermission($request, User::PERMISSION_STOCK_INVENTORY);

		try
		{
			$body = $this->GetParsedAndFilteredRequestBody($request);
			$result = \Grocy\Services\CycleCountService::GetInstance()->PlanByAbc(intval($body['branch_id'] ?? 0), !empty($body['dry_run']), GROCY_USER_ID);
			$result['message'] = $result['scheduled'] . ' conteos programados por clase ABC';
			return $this->ApiResponse($response, $result);
		}
		catch (\Exception $ex)
		{
			return $this->GenericErrorResponse($response, $ex->getMessage());
		}
	}

	// Ajuste de inventario por conteo (movimiento de corrección, separado de la merma). Requiere aprobador distinto de quien contó.
	public function ApproveCycleCountAdjustment(Request $request, Response $response, array $args)
	{
		User::CheckPermission($request, User::PERMISSION_INVENTORY_ADJUST_APPROVE);

		try
		{
			return $this->ApiResponse($response, \Grocy\Services\CycleCountService::GetInstance()->ApproveAdjustment(intval($args['countId']), intval(GROCY_USER_ID)));
		}
		catch (\Exception $ex)
		{
			return $this->GenericErrorResponse($response, $ex->getMessage());
		}
	}

	public function MoveToQuarantine(Request $request, Response $response, array $args)
	{
		$data = $request->getParsedBody();
		if (empty($data['product_id']) || empty($data['stock_id']) || empty($data['amount']))
		{
			return $this->GenericErrorResponse($response, 'Producto, lote y cantidad son obligatorios', 400);
		}
		$id = \Grocy\Services\QualityExpiryService::GetInstance()->MoveToQuarantine($data);
		return $this->ApiResponse($response, [
			'success' => true,
			'quarantine_id' => $id,
			'message' => 'Lote aislado en cuarentena correctamente'
		]);
	}

	public function ExecuteCycleCount(Request $request, Response $response, array $args)
	{
		User::CheckPermission($request, User::PERMISSION_STOCK_INVENTORY);

		try
		{
			$body = $this->GetParsedAndFilteredRequestBody($request);
			return $this->ApiResponse($response, \Grocy\Services\CycleCountService::GetInstance()->Execute(intval($args['countId']), $body['counted_stock'] ?? null, $body['notes'] ?? null, GROCY_USER_ID));
		}
		catch (\Exception $ex)
		{
			return $this->GenericErrorResponse($response, $ex->getMessage());
		}
	}
}
