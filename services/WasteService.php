<?php

namespace Grocy\Services;

class WasteService extends BaseService
{
	/**
	 * Get Waste Reasons (Matriz de Causales) with optional filtering and aggregate stats
	 */
	public function GetWasteReasons(bool $activeOnly = true, ?string $category = null, ?string $riskLevel = null, ?string $area = null, bool $excludeSecurityReasons = false)
	{
		$sql = "
			SELECT 
				wr.*,
				COUNT(w.id) AS case_count,
				IFNULL(SUM(w.total_cost), 0) AS total_loss_cost,
				IFNULL(SUM(w.amount), 0) AS total_units_lost
			FROM waste_reasons wr
			LEFT JOIN waste_records w ON wr.id = w.waste_reason_id
			WHERE 1=1
		";
		$params = [];

		if ($activeOnly)
		{
			$sql .= " AND wr.active = 1";
		}
		if ($excludeSecurityReasons)
		{
			$sql .= " AND IFNULL(wr.censo_category, '') NOT IN ('robo_interno', 'robo_externo') AND UPPER(IFNULL(wr.code, '')) NOT IN ('M03', 'M04', 'FPO01', 'FEL01')";
		}
		if (!empty($category) && $category !== 'all')
		{
			$sql .= " AND wr.category = :category";
			$params[':category'] = $category;
		}
		if (!empty($riskLevel) && $riskLevel !== 'all')
		{
			$sql .= " AND wr.risk_level = :risk_level";
			$params[':risk_level'] = $riskLevel;
		}
		if (!empty($area) && $area !== 'all')
		{
			$sql .= " AND wr.responsible_area = :responsible_area";
			$params[':responsible_area'] = $area;
		}

		$sql .= " GROUP BY wr.id ORDER BY wr.code ASC, wr.name ASC";

		$stmt = DatabaseService::GetInstance()->GetDbConnectionRaw()->prepare($sql);
		$stmt->execute($params);
		return $stmt->fetchAll(\PDO::FETCH_OBJ);
	}

	public function GetWasteReason(int $id)
	{
		$sql = "
			SELECT 
				wr.*,
				COUNT(w.id) AS case_count,
				IFNULL(SUM(w.total_cost), 0) AS total_loss_cost,
				IFNULL(SUM(w.amount), 0) AS total_units_lost
			FROM waste_reasons wr
			LEFT JOIN waste_records w ON wr.id = w.waste_reason_id
			WHERE wr.id = :id
			GROUP BY wr.id
		";
		$stmt = DatabaseService::GetInstance()->GetDbConnectionRaw()->prepare($sql);
		$stmt->execute([':id' => $id]);
		return $stmt->fetch(\PDO::FETCH_OBJ);
	}

	public function SaveWasteReason(array $data)
	{
		$id = !empty($data['id']) ? intval($data['id']) : null;
		$code = strtoupper(trim($data['code'] ?? 'M00'));
		$name = trim($data['name'] ?? '');
		$category = trim($data['category'] ?? 'General');
		$description = trim($data['description'] ?? '');
		$responsibleArea = trim($data['responsible_area'] ?? 'Operaciones');
		$preventiveActions = trim($data['preventive_actions'] ?? '');
		$associatedKpi = trim($data['associated_kpi'] ?? '% Merma');
		$riskLevel = trim($data['risk_level'] ?? 'Medio');
		$frequency = trim($data['frequency'] ?? 'Ocasional');
		$economicImpact = trim($data['economic_impact'] ?? 'Medio');
		$requiresEvidence = !empty($data['requires_evidence']) ? 1 : 0;
		$requiresApproval = !empty($data['requires_approval']) ? 1 : 0;
		$active = isset($data['active']) ? intval($data['active']) : 1;
		$icon = trim($data['icon'] ?? 'fa-solid fa-tag');
		$colorClass = trim($data['color_class'] ?? 'secondary');
		$shrinkType = ($data['shrink_type'] ?? 'conocida') === 'desconocida' ? 'desconocida' : 'conocida';
		$censoCategory = trim($data['censo_category'] ?? 'desperdicios');
		if (!in_array($censoCategory, ['vencimientos', 'desperdicios', 'robo_externo', 'robo_interno', 'error_administrativo', 'error_proveedor', 'otros']))
		{
			$censoCategory = 'otros';
		}

		if (empty($code) || empty($name))
		{
			throw new \Exception('El código y el nombre de la causal son obligatorios');
		}

		$dbRaw = DatabaseService::GetInstance()->GetDbConnectionRaw();

		if ($id !== null && $id > 0)
		{
			// Check code uniqueness excluding current id
			$chk = $dbRaw->prepare("SELECT COUNT(*) FROM waste_reasons WHERE code = :code AND id != :id");
			$chk->execute([':code' => $code, ':id' => $id]);
			if ($chk->fetchColumn() > 0)
			{
				throw new \Exception("Ya existe otra causal con el código '{$code}'");
			}

			$stmt = $dbRaw->prepare("
				UPDATE waste_reasons SET
					code = :code,
					name = :name,
					category = :category,
					description = :description,
					responsible_area = :responsible_area,
					preventive_actions = :preventive_actions,
					associated_kpi = :associated_kpi,
					risk_level = :risk_level,
					frequency = :frequency,
					economic_impact = :economic_impact,
					requires_evidence = :requires_evidence,
					requires_approval = :requires_approval,
					active = :active,
					icon = :icon,
					color_class = :color_class,
					shrink_type = :shrink_type,
					censo_category = :censo_category
				WHERE id = :id
			");
			$stmt->execute([
				':id' => $id,
				':code' => $code,
				':name' => $name,
				':category' => $category,
				':description' => $description,
				':responsible_area' => $responsibleArea,
				':preventive_actions' => $preventiveActions,
				':associated_kpi' => $associatedKpi,
				':risk_level' => $riskLevel,
				':frequency' => $frequency,
				':economic_impact' => $economicImpact,
				':requires_evidence' => $requiresEvidence,
				':requires_approval' => $requiresApproval,
				':active' => $active,
				':icon' => $icon,
				':color_class' => $colorClass,
				':shrink_type' => $shrinkType,
				':censo_category' => $censoCategory
			]);
			return $this->GetWasteReason($id);
		}
		else
		{
			// Check code uniqueness
			$chk = $dbRaw->prepare("SELECT COUNT(*) FROM waste_reasons WHERE code = :code");
			$chk->execute([':code' => $code]);
			if ($chk->fetchColumn() > 0)
			{
				throw new \Exception("Ya existe una causal registrada con el código '{$code}'");
			}

			$stmt = $dbRaw->prepare("
				INSERT INTO waste_reasons (
					code, name, category, description, responsible_area, preventive_actions,
					associated_kpi, risk_level, frequency, economic_impact, requires_evidence,
					requires_approval, active, icon, color_class, shrink_type, censo_category
				) VALUES (
					:code, :name, :category, :description, :responsible_area, :preventive_actions,
					:associated_kpi, :risk_level, :frequency, :economic_impact, :requires_evidence,
					:requires_approval, :active, :icon, :color_class, :shrink_type, :censo_category
				)
			");
			$stmt->execute([
				':code' => $code,
				':name' => $name,
				':category' => $category,
				':description' => $description,
				':responsible_area' => $responsibleArea,
				':preventive_actions' => $preventiveActions,
				':associated_kpi' => $associatedKpi,
				':risk_level' => $riskLevel,
				':frequency' => $frequency,
				':economic_impact' => $economicImpact,
				':requires_evidence' => $requiresEvidence,
				':requires_approval' => $requiresApproval,
				':active' => $active,
				':icon' => $icon,
				':color_class' => $colorClass,
				':shrink_type' => $shrinkType,
				':censo_category' => $censoCategory
			]);
			$newId = intval($dbRaw->lastInsertId());
			return $this->GetWasteReason($newId);
		}
	}

	public function ToggleWasteReasonActive(int $id)
	{
		$dbRaw = DatabaseService::GetInstance()->GetDbConnectionRaw();
		$stmt = $dbRaw->prepare("UPDATE waste_reasons SET active = CASE WHEN active = 1 THEN 0 ELSE 1 END WHERE id = :id");
		$stmt->execute([':id' => $id]);
		return $this->GetWasteReason($id);
	}

	public function GetWasteCategories(): array
	{
		$dbRaw = DatabaseService::GetInstance()->GetDbConnectionRaw();
		return $dbRaw->query("SELECT DISTINCT category FROM waste_reasons WHERE active = 1 ORDER BY category ASC")->fetchAll(\PDO::FETCH_COLUMN);
	}

	public function GetResponsibleAreas(): array
	{
		$dbRaw = DatabaseService::GetInstance()->GetDbConnectionRaw();
		return $dbRaw->query("SELECT DISTINCT responsible_area FROM waste_reasons WHERE active = 1 ORDER BY responsible_area ASC")->fetchAll(\PDO::FETCH_COLUMN);
	}

	/**
	 * Get Waste Case Records with comprehensive joins & filters
	 */
	public function GetWasteRecords(
		?int $branchId = null,
		?string $status = null,
		?string $workflowStatus = null,
		?string $riskLevel = null,
		?string $category = null,
		?string $startDate = null,
		?string $endDate = null,
		?int $reasonId = null
	) {
		$sql = "
			SELECT 
				w.*,
				p.name AS product_name,
				p.sku AS product_sku,
				qu.name AS qu_name,
				wr.code AS reason_code,
				wr.name AS reason_name,
				wr.category AS reason_category,
				wr.risk_level AS reason_risk_level,
				wr.requires_evidence AS requires_evidence,
				wr.color_class AS reason_color_class,
				wr.icon AS reason_icon,
				wr.responsible_area AS reason_area,
				b.name AS branch_name,
				l.name AS location_name,
				u_rep.username AS reported_by_username,
				u_app.username AS approved_by_username,
				u_inv.username AS investigator_username
			FROM waste_records w
			JOIN products p ON w.product_id = p.id
			LEFT JOIN quantity_units qu ON w.qu_id = qu.id
			JOIN waste_reasons wr ON w.waste_reason_id = wr.id
			JOIN branches b ON w.branch_id = b.id
			LEFT JOIN locations l ON w.location_id = l.id
			JOIN users u_rep ON w.reported_by_user_id = u_rep.id
			LEFT JOIN users u_app ON w.approved_by_user_id = u_app.id
			LEFT JOIN users u_inv ON w.investigator_user_id = u_inv.id
			WHERE 1=1
		";

		$params = [];
		if ($branchId !== null && $branchId > 0)
		{
			$sql .= " AND w.branch_id = :branch_id";
			$params[':branch_id'] = $branchId;
		}
		if ($status !== null && !empty($status) && $status !== 'all')
		{
			$sql .= " AND w.status = :status";
			$params[':status'] = $status;
		}
		if ($workflowStatus !== null && !empty($workflowStatus) && $workflowStatus !== 'all')
		{
			$sql .= " AND w.workflow_status = :workflow_status";
			$params[':workflow_status'] = $workflowStatus;
		}
		if ($riskLevel !== null && !empty($riskLevel) && $riskLevel !== 'all')
		{
			$sql .= " AND wr.risk_level = :risk_level";
			$params[':risk_level'] = $riskLevel;
		}
		if ($category !== null && !empty($category) && $category !== 'all')
		{
			$sql .= " AND wr.category = :category";
			$params[':category'] = $category;
		}
		if ($reasonId !== null && $reasonId > 0)
		{
			$sql .= " AND w.waste_reason_id = :reason_id";
			$params[':reason_id'] = $reasonId;
		}
		if ($startDate !== null && !empty($startDate))
		{
			$sql .= " AND date(w.created_timestamp) >= :start_date";
			$params[':start_date'] = $startDate;
		}
		if ($endDate !== null && !empty($endDate))
		{
			$sql .= " AND date(w.created_timestamp) <= :end_date";
			$params[':end_date'] = $endDate;
		}

		$sql .= " ORDER BY w.case_number DESC, w.created_timestamp DESC";

		$stmt = DatabaseService::GetInstance()->GetDbConnectionRaw()->prepare($sql);
		$stmt->execute($params);
		return $stmt->fetchAll(\PDO::FETCH_OBJ);
	}

	public function GetWasteRecord(int $id)
	{
		$sql = "
			SELECT 
				w.*,
				p.name AS product_name,
				p.sku AS product_sku,
				qu.name AS qu_name,
				wr.code AS reason_code,
				wr.name AS reason_name,
				wr.category AS reason_category,
				wr.risk_level AS reason_risk_level,
				wr.responsible_area AS reason_area,
				wr.preventive_actions AS reason_preventive_actions,
				wr.associated_kpi AS reason_kpi,
				wr.color_class AS reason_color_class,
				wr.icon AS reason_icon,
				b.name AS branch_name,
				l.name AS location_name,
				u_rep.username AS reported_by_username,
				u_app.username AS approved_by_username,
				u_inv.username AS investigator_username,
				u_ver.username AS verified_by_username
			FROM waste_records w
			JOIN products p ON w.product_id = p.id
			JOIN quantity_units qu ON w.qu_id = qu.id
			JOIN waste_reasons wr ON w.waste_reason_id = wr.id
			JOIN branches b ON w.branch_id = b.id
			LEFT JOIN locations l ON w.location_id = l.id
			JOIN users u_rep ON w.reported_by_user_id = u_rep.id
			LEFT JOIN users u_app ON w.approved_by_user_id = u_app.id
			LEFT JOIN users u_inv ON w.investigator_user_id = u_inv.id
			LEFT JOIN users u_ver ON w.verified_by_user_id = u_ver.id
			WHERE w.id = :id
		";
		$stmt = DatabaseService::GetInstance()->GetDbConnectionRaw()->prepare($sql);
		$stmt->execute([':id' => $id]);
		$record = $stmt->fetch(\PDO::FETCH_OBJ);

		if ($record)
		{
			// Load action logs
			$stmtLogs = DatabaseService::GetInstance()->GetDbConnectionRaw()->prepare("
				SELECT l.*, u.username
				FROM waste_action_logs l
				JOIN users u ON l.user_id = u.id
				WHERE l.waste_record_id = :record_id
				ORDER BY l.created_timestamp DESC
			");
			$stmtLogs->execute([':record_id' => $id]);
			$record->action_logs = $stmtLogs->fetchAll(\PDO::FETCH_OBJ);
		}

		return $record;
	}

	/** Ubicación de la sucursal desde la que se descuenta: la que alcanza la cantidad con el lote más próximo a vencer */
	private function BranchLocation(int $productId, int $branchId, float $amount): int
	{
		$st = DatabaseService::GetInstance()->GetDbConnectionRaw()->prepare('SELECT s.location_id FROM stock s JOIN locations l ON l.id = s.location_id
			WHERE s.product_id = :p AND l.branch_id = :b AND IFNULL(l.is_transit, 0) = 0 AND s.amount > 0
			GROUP BY s.location_id ORDER BY (SUM(s.amount) >= :a) DESC, MIN(s.best_before_date), SUM(s.amount) DESC LIMIT 1');
		$st->execute([':p' => $productId, ':b' => $branchId, ':a' => $amount]);
		$loc = intval($st->fetchColumn());
		if ($loc <= 0)
		{
			throw new \Exception('El producto no tiene existencias en la sucursal de la merma');
		}
		return $loc;
	}

	/**
	 * Report Waste Case (Atomic, Idempotent, with Investigation Tracking)
	 */
	public function ReportWaste(
		int $productId,
		float $amount,
		int $wasteReasonId,
		int $branchId = 1,
		?int $locationId = null,
		string $specificStockEntryId = 'default',
		?string $notes = null,
		?string $evidencePhotoPath = null,
		?string $subReason = null,
		?string $batchNumber = null,
		?string $bestBeforeDate = null,
		?int $investigatorUserId = null,
		?string $dueDate = null,
		?string $idempotencyKey = null,
		int $userId = GROCY_USER_ID,
		bool $forceApproval = false,
		bool $confirmDuplicate = false
	) {
		DeviceService::AssertNotControlled($productId, 'darlo de baja');
		if ($amount <= 0)
		{
			throw new \Exception('La cantidad a registrar como merma debe ser mayor a 0');
		}

		$dbRaw = DatabaseService::GetInstance()->GetDbConnectionRaw();
		$idempotencyKey = !empty($idempotencyKey) ? mb_substr(strval($idempotencyKey), 0, 100) : null;

		// Idempotency check: if already reported with this key, return it without re-deducting stock
		// (se repite dentro de la transacción de escritura para reenvíos simultáneos)
		$existing = $this->FindByIdempotencyKey($idempotencyKey, $userId);
		if ($existing)
		{
			return $existing;
		}

		// Evidencia: solo fotos subidas por /waste/upload-photo (sin rutas arbitrarias)
		$evidencePhotoPath = $evidencePhotoPath !== null ? trim(strval($evidencePhotoPath)) : null;
		if ($evidencePhotoPath === '')
		{
			$evidencePhotoPath = null;
		}
		if (!empty($evidencePhotoPath) && !preg_match('#^(/api/waste/photo/)?waste_[A-Za-z0-9_.-]+\.(jpe?g|png|webp|gif|heic)$#i', $evidencePhotoPath))
		{
			throw new \Exception('Evidencia inválida: adjunte la foto desde la cámara o galería');
		}

		$product = $this->DB->products($productId);
		if (!$product)
		{
			throw new \Exception('El producto no existe');
		}

		if ($wasteReasonId <= 0)
		{
			throw new \Exception('Debe seleccionar una causal válida de merma');
		}
		$reason = $this->DB->waste_reasons($wasteReasonId);
		if (!$reason)
		{
			throw new \Exception('El motivo de merma seleccionado no es válido');
		}
		if (intval($reason->active ?? 1) !== 1)
		{
			throw new \Exception('La causal de merma seleccionada está inactiva');
		}

		if (intval($reason->requires_evidence) === 1 && empty($evidencePhotoPath))
		{
			throw new \Exception('Este motivo de merma requiere fotografía de evidencia obligatoria');
		}

		if (!$confirmDuplicate)
		{
			$this->AssertNotDuplicate($productId, $amount, $wasteReasonId, $branchId, $userId);
		}

		// Política de aprobación: causal que la exige, impacto estimado ≥ WASTE_APPROVAL_MIN_COST o causal de riesgo alto
		if ($forceApproval)
		{
			$status = 'approved';
		}
		else
		{
			$status = $this->RequiresApproval($reason, $this->EstimateUnitCost($productId, $locationId) * $amount) ? 'pending_approval' : 'approved';
		}

		$workflowStatus = ($status === 'approved') ? 'action_assigned' : 'detected';
		// Causal no definitiva (origen sin confirmar): nace como investigación con plazo y responsable
		$cause = LossIntelligenceService::GetInstance()->CauseForReason($wasteReasonId);
		if ($cause && intval($cause['is_definitive']) === 0)
		{
			$workflowStatus = 'investigating';
			$dueDate = $dueDate ?: date('Y-m-d', strtotime('+' . max(1, intval($cause['investigation_days'])) . ' days'));
			$investigatorUserId = $investigatorUserId ?: $userId;
		}
		$transactionId = null;
		$unitCost = 0.0;
		$totalCost = 0.0;
		$inventoryDeducted = 0;

		DatabaseService::GetInstance()->BeginWriteTransaction();

		try
		{
			$existing = $this->FindByIdempotencyKey($idempotencyKey, $userId);
			if ($existing)
			{
				$dbRaw->rollBack();
				return $existing;
			}

			// Por aprobar: la cantidad debe existir hoy (al aprobar se vuelve a validar al descontar)
			if ($status !== 'approved')
			{
				$avail = $dbRaw->prepare('SELECT IFNULL(SUM(s.amount), 0) FROM stock s LEFT JOIN locations l ON l.id = s.location_id WHERE s.product_id = :p'
					. ($locationId ? ' AND s.location_id = :l' : ' AND IFNULL(l.branch_id, 1) = CAST(:b AS INTEGER)') . ($specificStockEntryId !== 'default' ? ' AND s.stock_id = :s' : ''));
				$avail->execute(array_merge([':p' => $productId], $locationId ? [':l' => $locationId] : [':b' => $branchId], $specificStockEntryId !== 'default' ? [':s' => $specificStockEntryId] : []));
				$available = floatval($avail->fetchColumn());
				$pending = $dbRaw->prepare("SELECT IFNULL(SUM(amount), 0) FROM waste_records WHERE product_id = :p AND branch_id = :b AND status = 'pending_approval' AND inventory_deducted = 0" . ($locationId ? ' AND location_id = :l' : ''));
				$pending->execute(array_merge([':p' => $productId, ':b' => $branchId], $locationId ? [':l' => $locationId] : []));
				$available -= floatval($pending->fetchColumn());
				if ($amount > $available + 0.0001)
				{
					throw new \Exception('Cantidad mayor a la disponible (' . round(max(0, $available), 2) . ' sin contar mermas por aprobar)');
				}
			}

			// Sin ubicación ni lote: se descuenta en la sucursal de la merma (sin ella, el consumo FEFO tomaría stock de cualquier sucursal)
			if (!$locationId && $specificStockEntryId === 'default')
			{
				$locationId = $this->BranchLocation($productId, $branchId, $amount);
			}

			if ($status === 'approved')
			{
				// Deduct from stock immediately
				$stockService = StockService::GetInstance();
				$transactionId = $stockService->ConsumeProduct(
					$productId,
					$amount,
					true, // spoiled = true
					StockService::TRANSACTION_TYPE_CONSUME,
					$specificStockEntryId,
					null,
					$locationId,
					$transactionId,
					false,
					true // consumeExactAmount = true
				);

				if (empty(trim(strval($transactionId))))
				{
					throw new \Exception('Error de integridad: no se pudo generar la transacción de descuento de inventario');
				}
				$inventoryDeducted = 1;

				// Retrieve consumed entries from stock_log to calculate accurate cost
				$consumedEntries = $this->DB->stock_log()->where('transaction_id = :1 AND spoiled = 1', $transactionId)->fetchAll();
				$costSum = 0.0;
				$amountSum = 0.0;
				foreach ($consumedEntries as $entry)
				{
					$entryAmount = abs($entry->amount);
					$costSum += ($entryAmount * floatval($entry->price));
					$amountSum += $entryAmount;
				}

				if ($amountSum > 0)
				{
					$totalCost = $costSum;
					$unitCost = $totalCost / $amountSum;
				}
				else
				{
					$plp = $this->DB->cache__products_last_purchased()->where('product_id', $productId)->fetch();
					$unitCost = floatval($plp->price ?? 0);
					$totalCost = $unitCost * $amount;
				}
			}
			else
			{
				$plp = $this->DB->cache__products_last_purchased()->where('product_id', $productId)->fetch();
				$unitCost = floatval($plp->price ?? 0);
				$totalCost = $unitCost * $amount;
				$inventoryDeducted = 0;
			}

			// Generate sequential case number: MRM-YYYYMM-XXXX
			$prefix = 'MRM-' . date('Ym') . '-';
			$nextSeq = intval($dbRaw->query("SELECT IFNULL(MAX(CAST(substr(case_number, " . (strlen($prefix) + 1) . ") AS INTEGER)), 0) + 1 FROM waste_records WHERE case_number LIKE '{$prefix}%'")->fetchColumn());
			$caseNumber = sprintf("%s%04d", $prefix, $nextSeq);

			$insStmt = $dbRaw->prepare("
				INSERT INTO waste_records (
					case_number, branch_id, product_id, stock_id, location_id, amount,
					qu_id, unit_cost, total_cost, waste_reason_id, sub_reason, batch_number,
					best_before_date, notes, evidence_photo_path, reported_by_user_id,
					status, workflow_status, investigator_user_id, due_date, approved_by_user_id,
					approved_timestamp, transaction_id, inventory_deducted, idempotency_key
				) VALUES (
					:case_number, :branch_id, :product_id, :stock_id, :location_id, :amount,
					:qu_id, :unit_cost, :total_cost, :waste_reason_id, :sub_reason, :batch_number,
					:best_before_date, :notes, :evidence_photo_path, :reported_by_user_id,
					:status, :workflow_status, :investigator_user_id, :due_date, :approved_by_user_id,
					:approved_timestamp, :transaction_id, :inventory_deducted, :idempotency_key
				)
			");

			$insStmt->execute([
				':case_number' => $caseNumber,
				':branch_id' => $branchId,
				':product_id' => $productId,
				':stock_id' => ($specificStockEntryId !== 'default') ? $specificStockEntryId : null,
				':location_id' => $locationId,
				':amount' => $amount,
				':qu_id' => $product->qu_id_stock,
				':unit_cost' => round($unitCost, 4),
				':total_cost' => round($totalCost, 2),
				':waste_reason_id' => $wasteReasonId,
				':sub_reason' => $subReason,
				':batch_number' => $batchNumber,
				':best_before_date' => $bestBeforeDate,
				':notes' => $notes,
				':evidence_photo_path' => $evidencePhotoPath,
				':reported_by_user_id' => $userId,
				':status' => $status,
				':workflow_status' => $workflowStatus,
				':investigator_user_id' => $investigatorUserId,
				':due_date' => $dueDate,
				':approved_by_user_id' => ($status === 'approved') ? $userId : null,
				':approved_timestamp' => ($status === 'approved') ? date('Y-m-d H:i:s') : null,
				':transaction_id' => $transactionId,
				':inventory_deducted' => $inventoryDeducted,
				':idempotency_key' => $idempotencyKey
			]);

			$newRecordId = intval($dbRaw->lastInsertId());

			// Turno vigente de quien registra (si es empleado con turno): contexto para análisis por turno
			$activeShift = WorkforceScheduleService::GetInstance()->ActiveShiftForUser($userId);
			if ($activeShift)
			{
				$dbRaw->prepare('UPDATE waste_records SET shift_id = :s WHERE id = :id')->execute([':s' => $activeShift->id, ':id' => $newRecordId]);
			}
			if ($status === 'approved')
			{
				$this->AfterApproved($newRecordId, round($totalCost, 2), $userId);
			}

			// Insert audit log
			$logStmt = $dbRaw->prepare("
				INSERT INTO waste_action_logs (waste_record_id, user_id, previous_status, new_status, action_title, action_details)
				VALUES (:record_id, :user_id, NULL, :new_status, :title, :details)
			");
			$logStmt->execute([
				':record_id' => $newRecordId,
				':user_id' => $userId,
				':new_status' => $workflowStatus,
				':title' => 'Incidente de merma registrado',
				':details' => "Reporte creado como '{$caseNumber}'. Causal: {$reason->code} - {$reason->name}. Costo estimado: $" . number_format($totalCost, 2)
			]);

			$dbRaw->commit();
			return $this->GetWasteRecord($newRecordId);
		}
		catch (\Throwable $ex)
		{
			if ($dbRaw->inTransaction())
			{
				$dbRaw->rollBack();
			}
			throw $ex;
		}
	}

	const CASE_WORKFLOW = ['detected', 'reviewing', 'investigating', 'approved', 'action_assigned', 'executed', 'verified', 'closed'];

	/** Estados válidos y sin saltos que dejen la merma sin decidir: no se ejecuta, verifica ni cierra antes de aprobar o rechazar */
	private function AssertCaseTransition($record, string $from, string $to): void
	{
		if ($to === $from)
		{
			return;
		}
		if (!in_array($to, self::CASE_WORKFLOW, true))
		{
			throw new \Exception('Estado de caso inválido: ' . $to);
		}
		if ($from === 'closed')
		{
			throw new \Exception('El caso ya está cerrado');
		}
		if ($record->status === 'pending_approval' && in_array($to, ['executed', 'verified', 'closed'], true))
		{
			throw new \Exception('La merma está pendiente de aprobación: apruébela o recházela antes de ejecutar, verificar o cerrar el caso');
		}
	}

	/**
	 * Update Case Investigation, Workflow Status & Action Plan
	 */
	public function UpdateCaseWorkflow(int $caseId, array $data, int $userId = GROCY_USER_ID)
	{
		$record = $this->GetWasteRecord($caseId);
		if (!$record)
		{
			throw new \Exception('Caso de merma no encontrado');
		}

		$previousWorkflowStatus = $record->workflow_status;
		$newWorkflowStatus = trim($data['workflow_status'] ?? $previousWorkflowStatus);
		$this->AssertCaseTransition($record, (string) $previousWorkflowStatus, $newWorkflowStatus);
		$investigatorUserId = isset($data['investigator_user_id']) ? (!empty($data['investigator_user_id']) ? intval($data['investigator_user_id']) : null) : $record->investigator_user_id;
		$dueDate = $data['due_date'] ?? $record->due_date;
		$rootCause = $data['root_cause_analysis'] ?? $record->root_cause_analysis;
		$correctiveAction = $data['corrective_action_plan'] ?? $record->corrective_action_plan;
		$preventiveMeasures = $data['preventive_measures_implemented'] ?? $record->preventive_measures_implemented;
		$resolutionNotes = $data['resolution_notes'] ?? $record->resolution_notes;
		$financialRecovery = isset($data['financial_recovery_amount']) ? floatval($data['financial_recovery_amount']) : floatval($record->financial_recovery_amount);

		$closedTimestamp = $record->closed_timestamp;
		$verifiedUserId = $record->verified_by_user_id;

		if ($newWorkflowStatus === 'closed' && empty($closedTimestamp))
		{
			$closedTimestamp = date('Y-m-d H:i:s');
			$verifiedUserId = $userId;
		}
		elseif ($newWorkflowStatus === 'verified' && empty($verifiedUserId))
		{
			$verifiedUserId = $userId;
		}

		$dbRaw = DatabaseService::GetInstance()->GetDbConnectionRaw();
		$dbRaw->beginTransaction();

		try
		{
			$stmt = $dbRaw->prepare("
				UPDATE waste_records SET
					workflow_status = :workflow_status,
					investigator_user_id = :investigator_user_id,
					due_date = :due_date,
					root_cause_analysis = :root_cause_analysis,
					corrective_action_plan = :corrective_action_plan,
					preventive_measures_implemented = :preventive_measures_implemented,
					resolution_notes = :resolution_notes,
					financial_recovery_amount = :financial_recovery_amount,
					verified_by_user_id = :verified_by_user_id,
					closed_timestamp = :closed_timestamp
				WHERE id = :id
			");

			$stmt->execute([
				':id' => $caseId,
				':workflow_status' => $newWorkflowStatus,
				':investigator_user_id' => $investigatorUserId,
				':due_date' => $dueDate,
				':root_cause_analysis' => $rootCause,
				':corrective_action_plan' => $correctiveAction,
				':preventive_measures_implemented' => $preventiveMeasures,
				':resolution_notes' => $resolutionNotes,
				':financial_recovery_amount' => $financialRecovery,
				':verified_by_user_id' => $verifiedUserId,
				':closed_timestamp' => $closedTimestamp
			]);

			// Record audit log
			$actionTitle = 'Actualización de investigación';
			if ($previousWorkflowStatus !== $newWorkflowStatus)
			{
				$actionTitle = "Transición de estado: {$previousWorkflowStatus} → {$newWorkflowStatus}";
			}

			$logDetails = !empty($resolutionNotes) ? $resolutionNotes : "Investigación actualizada. Plan correctivo y medidas registradas.";

			$stmtLog = $dbRaw->prepare("
				INSERT INTO waste_action_logs (waste_record_id, user_id, previous_status, new_status, action_title, action_details)
				VALUES (:record_id, :user_id, :prev_status, :new_status, :title, :details)
			");
			$stmtLog->execute([
				':record_id' => $caseId,
				':user_id' => $userId,
				':prev_status' => $previousWorkflowStatus,
				':new_status' => $newWorkflowStatus,
				':title' => $actionTitle,
				':details' => $logDetails
			]);

			$dbRaw->commit();
			return $this->GetWasteRecord($caseId);
		}
		catch (\Exception $ex)
		{
			$dbRaw->rollBack();
			throw $ex;
		}
	}

	/**
	 * Approve Waste (Idempotent - Guarantees single stock deduction)
	 */
	public function ApproveWaste(int $wasteRecordId, int $approverUserId = GROCY_USER_ID)
	{
		$wasteRecord = $this->GetWasteRecord($wasteRecordId);
		if (!$wasteRecord)
		{
			throw new \Exception('Registro de merma no encontrado');
		}

		if ($wasteRecord->status === 'approved')
		{
			return $wasteRecord; // Already approved, idempotent
		}

		if ($wasteRecord->status !== 'pending_approval')
		{
			throw new \Exception('El registro ya fue dictaminado como ' . $wasteRecord->status);
		}

		$reason = $this->DB->waste_reasons(intval($wasteRecord->waste_reason_id));
		if ($reason && intval($reason->requires_evidence) === 1 && empty(trim((string) ($wasteRecord->evidence_photo_path ?? ''))))
		{
			throw new \Exception('Este motivo de merma requiere fotografía de evidencia antes de aprobar. Adjúntela en el caso.');
		}

		$this->AssertSegregationOfDuties($wasteRecord, $approverUserId);
		$this->AssertApprovalLevel(max(floatval($wasteRecord->total_cost), $this->EstimateUnitCost(intval($wasteRecord->product_id), $wasteRecord->location_id ? intval($wasteRecord->location_id) : null) * floatval($wasteRecord->amount)));

		$dbRaw = DatabaseService::GetInstance()->BeginWriteTransaction();

		try
		{
			// Releer con el bloqueo tomado: otra aprobación o rechazo simultáneo pudo decidirla
			$wasteRecord = $this->GetWasteRecord($wasteRecordId);
			if ($wasteRecord->status === 'approved')
			{
				$dbRaw->rollBack();
				return $wasteRecord;
			}
			if ($wasteRecord->status !== 'pending_approval')
			{
				throw new \Exception('El registro ya fue dictaminado como ' . $wasteRecord->status);
			}
			if ($reason && intval($reason->requires_evidence) === 1 && empty(trim((string) ($wasteRecord->evidence_photo_path ?? ''))))
			{
				throw new \Exception('Este motivo de merma requiere fotografía de evidencia antes de aprobar. Adjúntela en el caso.');
			}
			$transactionId = $wasteRecord->transaction_id;
			$unitCost = $wasteRecord->unit_cost;
			$totalCost = $wasteRecord->total_cost;

			// Only deduct from stock if not already deducted with a valid stock transaction
			if (intval($wasteRecord->inventory_deducted) !== 1 || empty(trim((string) $transactionId)))
			{
				$transactionId = null;
				$stockService = StockService::GetInstance();
				$approveLocation = $wasteRecord->location_id ?: (empty($wasteRecord->stock_id) ? $this->BranchLocation(intval($wasteRecord->product_id), intval($wasteRecord->branch_id), floatval($wasteRecord->amount)) : null);
				$transactionId = $stockService->ConsumeProduct(
					$wasteRecord->product_id,
					$wasteRecord->amount,
					true,
					StockService::TRANSACTION_TYPE_CONSUME,
					$wasteRecord->stock_id ?? 'default',
					null,
					$approveLocation,
					$transactionId,
					false,
					true
				);
				if (empty(trim((string) $transactionId)))
				{
					throw new \Exception('No se pudo registrar la transacción de descarga de inventario');
				}

				// Recalculate actual cost from stock_log
				$consumedEntries = $this->DB->stock_log()->where('transaction_id = :1 AND spoiled = 1', $transactionId)->fetchAll();
				$costSum = 0.0;
				$amountSum = 0.0;
				foreach ($consumedEntries as $entry)
				{
					$entryAmount = abs($entry->amount);
					$costSum += ($entryAmount * floatval($entry->price));
					$amountSum += $entryAmount;
				}

				if ($amountSum > 0)
				{
					$unitCost = $costSum / $amountSum;
					$totalCost = $costSum;
				}
			}

			$stmt = $dbRaw->prepare("
				UPDATE waste_records SET
					status = 'approved',
					workflow_status = CASE WHEN workflow_status = 'investigating' THEN 'investigating' ELSE 'action_assigned' END,
					approved_by_user_id = :approver_id,
					approved_timestamp = :appr_time,
					unit_cost = :unit_cost,
					total_cost = :total_cost,
					transaction_id = :tx_id,
					inventory_deducted = 1
				WHERE id = :id
			");

			$stmt->execute([
				':id' => $wasteRecordId,
				':approver_id' => $approverUserId,
				':appr_time' => date('Y-m-d H:i:s'),
				':unit_cost' => round($unitCost, 4),
				':total_cost' => round($totalCost, 2),
				':tx_id' => $transactionId
			]);

			$stmtLog = $dbRaw->prepare("
				INSERT INTO waste_action_logs (waste_record_id, user_id, previous_status, new_status, action_title, action_details)
				VALUES (:record_id, :user_id, 'pending_approval', 'approved', 'Merma aprobada y descontada', 'Autorizada por supervisor. Existencias descargadas de inventario contable.')
			");
			$stmtLog->execute([
				':record_id' => $wasteRecordId,
				':user_id' => $approverUserId
			]);

			$this->AfterApproved($wasteRecordId, round($totalCost, 2), $approverUserId);

			$dbRaw->commit();
			return $this->GetWasteRecord($wasteRecordId);
		}
		catch (\Exception $ex)
		{
			$dbRaw->rollBack();
			if (strpos($ex->getMessage(), 'Amount to be consumed cannot be') !== false)
			{
				throw new \Exception('Stock insuficiente para aprobar: la existencia actual es menor a la merma (pudo venderse o moverse después del reporte). Rechácela o pida un conteo.');
			}
			throw $ex;
		}
	}

	/**
	 * Merma aprobada (descuento de stock ya hecho): tarea correctiva para el supervisor de la sucursal si la pérdida
	 * alcanza WASTE_CORRECTIVE_TASK_MIN_COST, y reglas de automatización configuradas (evento waste_approved).
	 * Corre dentro de la transacción de la aprobación.
	 */
	private function AfterApproved(int $wasteRecordId, float $totalCost, int $userId): void
	{
		$db = DatabaseService::GetInstance()->GetDbConnectionRaw();
		$w = $db->query('SELECT w.*, p.name AS product_name, r.code AS reason_code, r.name AS reason_name, r.preventive_actions FROM waste_records w LEFT JOIN products p ON p.id = w.product_id LEFT JOIN waste_reasons r ON r.id = w.waste_reason_id WHERE w.id = ' . $wasteRecordId)->fetch(\PDO::FETCH_OBJ);
		$min = defined('GROCY_WASTE_CORRECTIVE_TASK_MIN_COST') ? floatval(GROCY_WASTE_CORRECTIVE_TASK_MIN_COST) : 20;
		$exists = $db->query("SELECT 1 FROM workforce_store_tasks WHERE source_type = 'waste' AND source_id = $wasteRecordId")->fetchColumn();
		if ($w && $min >= 0 && $totalCost >= $min && !$exists)
		{
			$db->prepare("INSERT INTO workforce_store_tasks (branch_id, task_type, assigned_role, title, description, due_time, status, source_type, source_id, created_timestamp)
				VALUES (:b, 'corrective_action', 'supervisor', :t, :d, datetime('now', 'localtime', '+1 day'), 'pending', 'waste', :id, datetime('now', 'localtime'))")->execute([
				':b' => intval($w->branch_id ?: 1),
				':t' => 'Acción correctiva ' . ($w->case_number ?: '#' . $wasteRecordId) . ': ' . ($w->product_name ?: 'producto'),
				':d' => 'Merma aprobada de $' . number_format($totalCost, 2) . ' (' . trim(($w->reason_code ?? '') . ' ' . ($w->reason_name ?? '')) . '). Verificar la causa en sitio y aplicar: ' . ($w->preventive_actions ?: 'la medida preventiva de la causal') . '.',
				':id' => $wasteRecordId
			]);
		}
		AutomationEngineService::GetInstance()->TriggerEvent('waste_approved', [
			'waste_record_id' => $wasteRecordId, 'branch_id' => intval($w->branch_id ?? 1), 'product_id' => intval($w->product_id ?? 0),
			'total_cost' => $totalCost, 'reason_code' => $w->reason_code ?? null, 'amount' => floatval($w->amount ?? 0), 'stock_id' => $w->stock_id ?? null
		]);
	}

	// Costo unitario aplicable: promedio ponderado del stock (de la ubicación si se indica) o último precio de compra
	public function EstimateUnitCost(int $productId, ?int $locationId = null): float
	{
		$db = DatabaseService::GetInstance()->GetDbConnectionRaw();
		$sql = 'SELECT SUM(amount * IFNULL(price, 0)) / NULLIF(SUM(amount), 0) FROM stock WHERE product_id = :p AND amount > 0' . ($locationId ? ' AND location_id = :l' : '');
		$stmt = $db->prepare($sql);
		$stmt->execute($locationId ? [':p' => $productId, ':l' => $locationId] : [':p' => $productId]);
		$avg = floatval($stmt->fetchColumn());
		if ($avg <= 0 && $locationId)
		{
			return $this->EstimateUnitCost($productId, null);
		}
		if ($avg <= 0)
		{
			$plp = $this->DB->cache__products_last_purchased()->where('product_id', $productId)->fetch();
			$avg = floatval($plp->price ?? 0);
		}
		return round($avg, 4);
	}

	public function RequiresApproval($reason, float $estimatedCost): bool
	{
		$minCost = defined('GROCY_WASTE_APPROVAL_MIN_COST') ? floatval(GROCY_WASTE_APPROVAL_MIN_COST) : 0;
		$risk = mb_strtolower(trim((string) ($reason->risk_level ?? '')));
		return intval($reason->requires_approval) === 1
			|| ($minCost > 0 && $estimatedCost >= $minCost)
			|| in_array($risk, ['alto', 'alta', 'crítico', 'crítica', 'critico', 'critica'], true);
	}

	// Aprobación por importe: sobre WASTE_HIGH_VALUE_COST se exige el permiso WASTE_APPROVE_HIGH (gerente)
	public function AssertApprovalLevel(float $cost): void
	{
		$limit = defined('GROCY_WASTE_HIGH_VALUE_COST') ? floatval(GROCY_WASTE_HIGH_VALUE_COST) : 0;
		if ($limit > 0 && $cost >= $limit && !\Grocy\Helpers\DairyAccess::HasAny(['WASTE_APPROVE_HIGH']))
		{
			throw new \Exception('Merma de alto valor ($' . number_format($cost, 2) . ' ≥ $' . number_format($limit, 2) . '): requiere aprobación de un gerente (permiso WASTE_APPROVE_HIGH)');
		}
	}

	// Mismo producto, cantidad, causal, sucursal y usuario dentro de WASTE_DUPLICATE_WINDOW_MINUTES → posible doble registro
	private function FindByIdempotencyKey(?string $key, int $userId)
	{
		if (empty($key))
		{
			return null;
		}
		$stmt = DatabaseService::GetInstance()->GetDbConnectionRaw()->prepare('SELECT id, reported_by_user_id FROM waste_records WHERE idempotency_key = :k');
		$stmt->execute([':k' => $key]);
		$row = $stmt->fetch(\PDO::FETCH_ASSOC);
		if (!$row)
		{
			return null;
		}
		if (intval($row['reported_by_user_id']) !== $userId)
		{
			throw new \Exception('La clave de operación ya fue usada por otro registro; reintente desde el formulario');
		}
		return $this->GetWasteRecord(intval($row['id']));
	}

	private function AssertNotDuplicate(int $productId, float $amount, int $reasonId, int $branchId, int $userId): void
	{
		$minutes = defined('GROCY_WASTE_DUPLICATE_WINDOW_MINUTES') ? intval(GROCY_WASTE_DUPLICATE_WINDOW_MINUTES) : 0;
		if ($minutes <= 0)
		{
			return;
		}
		$stmt = DatabaseService::GetInstance()->GetDbConnectionRaw()->prepare("SELECT case_number, created_timestamp FROM waste_records
			WHERE product_id = :p AND abs(amount - :a) < 0.0001 AND waste_reason_id = :r AND branch_id = :b AND reported_by_user_id = :u
			AND status <> 'rejected' AND created_timestamp >= datetime('now', 'localtime', :since) ORDER BY id DESC LIMIT 1");
		$stmt->execute([':p' => $productId, ':a' => $amount, ':r' => $reasonId, ':b' => $branchId, ':u' => $userId, ':since' => '-' . $minutes . ' minutes']);
		$dup = $stmt->fetch(\PDO::FETCH_ASSOC);
		if ($dup)
		{
			throw new \Exception("Posible duplicado: {$dup['case_number']} con el mismo producto, cantidad y causal se registró a las " . substr($dup['created_timestamp'], 11, 5) . '. Confirme para registrarlo de nuevo.');
		}
	}

	// Segregación de funciones: quien reporta una merma no puede dictaminarla (configurable con WASTE_ALLOW_SELF_APPROVAL)
	private function AssertSegregationOfDuties($wasteRecord, int $approverUserId): void
	{
		if (defined('GROCY_WASTE_ALLOW_SELF_APPROVAL') && GROCY_WASTE_ALLOW_SELF_APPROVAL)
		{
			return;
		}

		if (intval($wasteRecord->reported_by_user_id) === $approverUserId)
		{
			throw new \Exception('Segregación de funciones: el usuario que reportó la merma no puede aprobarla ni rechazarla');
		}
	}

	/**
	 * Pedir información a quien reportó una merma pendiente: queda en la bitácora del caso; el estado no cambia (sigue por aprobar).
	 */
	public function RequestWasteInfo(int $wasteRecordId, string $note, int $userId = GROCY_USER_ID)
	{
		$wasteRecord = $this->GetWasteRecord($wasteRecordId);
		if (!$wasteRecord)
		{
			throw new \Exception('Registro de merma no encontrado');
		}
		if ($wasteRecord->status !== 'pending_approval')
		{
			throw new \Exception('El registro ya fue dictaminado como ' . $wasteRecord->status);
		}
		DatabaseService::GetInstance()->GetDbConnectionRaw()->prepare("
			INSERT INTO waste_action_logs (waste_record_id, user_id, previous_status, new_status, action_title, action_details)
			VALUES (:record_id, :user_id, 'pending_approval', 'pending_approval', 'Información solicitada', :details)
		")->execute([':record_id' => $wasteRecordId, ':user_id' => $userId, ':details' => $note]);
		return $wasteRecord;
	}

	public function RejectWaste(int $wasteRecordId, int $approverUserId = GROCY_USER_ID, string $rejectionReason = '')
	{
		$wasteRecord = $this->GetWasteRecord($wasteRecordId);
		if (!$wasteRecord)
		{
			throw new \Exception('Registro de merma no encontrado');
		}

		if ($wasteRecord->status !== 'pending_approval')
		{
			throw new \Exception('El registro ya ha sido procesado');
		}

		$this->AssertSegregationOfDuties($wasteRecord, $approverUserId);

		$currentNotes = $wasteRecord->notes ?? '';
		$updatedNotes = trim($currentNotes . "\n[Rechazado por usuario #$approverUserId: " . $rejectionReason . ']');

		$dbRaw = DatabaseService::GetInstance()->BeginWriteTransaction();

		try
		{
			// Releer con el bloqueo tomado: una aprobación simultánea pudo decidirla
			if ($dbRaw->query('SELECT status FROM waste_records WHERE id = ' . intval($wasteRecordId))->fetchColumn() !== 'pending_approval')
			{
				throw new \Exception('El registro ya ha sido procesado');
			}
			$stmt = $dbRaw->prepare("
				UPDATE waste_records SET
					status = 'rejected',
					workflow_status = 'closed',
					approved_by_user_id = :approver_id,
					approved_timestamp = :appr_time,
					closed_timestamp = :closed_time,
					notes = :notes
				WHERE id = :id
			");
			$stmt->execute([
				':id' => $wasteRecordId,
				':approver_id' => $approverUserId,
				':appr_time' => date('Y-m-d H:i:s'),
				':closed_time' => date('Y-m-d H:i:s'),
				':notes' => $updatedNotes
			]);

			$stmtLog = $dbRaw->prepare("
				INSERT INTO waste_action_logs (waste_record_id, user_id, previous_status, new_status, action_title, action_details)
				VALUES (:record_id, :user_id, 'pending_approval', 'rejected', 'Merma rechazada', :details)
			");
			$stmtLog->execute([
				':record_id' => $wasteRecordId,
				':user_id' => $approverUserId,
				':details' => "Rechazada por supervisor. Motivo: {$rejectionReason}. No se afectó inventario."
			]);

			$dbRaw->commit();
			return $this->GetWasteRecord($wasteRecordId);
		}
		catch (\Exception $ex)
		{
			$dbRaw->rollBack();
			throw $ex;
		}
	}

	public function GetPendingApprovalsCount(?int $branchId = null): int
	{
		$dbRaw = DatabaseService::GetInstance()->GetDbConnectionRaw();
		$sql = "SELECT COUNT(*) FROM waste_records WHERE status = 'pending_approval'";
		if ($branchId !== null && $branchId > 0)
		{
			$sql .= " AND branch_id = " . intval($branchId);
		}
		return intval($dbRaw->query($sql)->fetchColumn());
	}

	/**
	 * Comprehensive Analytics & Pareto Engine for Waste
	 */
	public function GetWasteAnalytics(?int $branchId = null, ?string $period = 'month', ?string $startDate = null, ?string $endDate = null): array
	{
		$dbRaw = DatabaseService::GetInstance()->GetDbConnectionRaw();

		if (empty($startDate) || empty($endDate))
		{
			$today = date('Y-m-d');
			switch ($period)
			{
				case 'day':
					$startDate = $today;
					$endDate = $today;
					break;
				case 'week':
					$startDate = date('Y-m-d', strtotime('monday this week'));
					$endDate = date('Y-m-d', strtotime('sunday this week'));
					break;
				case 'year':
					$startDate = date('Y-01-01');
					$endDate = date('Y-12-31');
					break;
				case 'month':
				default:
					$startDate = date('Y-m-01');
					$endDate = date('Y-m-t');
					break;
			}
		}

		$branchCondition = "";
		$branchParams = [];
		if ($branchId !== null && $branchId > 0)
		{
			$branchCondition = " AND w.branch_id = :branch_id";
			$branchParams[':branch_id'] = $branchId;
		}

		// 1. Financial KPI Summary
		$summarySql = "
			SELECT 
				COUNT(*) AS total_cases,
				IFNULL(SUM(w.amount), 0) AS total_units,
				IFNULL(SUM(w.total_cost), 0) AS total_loss_cost,
				IFNULL(SUM(w.financial_recovery_amount), 0) AS total_recovered_amount,
				(IFNULL(SUM(w.total_cost), 0) - IFNULL(SUM(w.financial_recovery_amount), 0)) AS net_loss_cost,
				COUNT(CASE WHEN w.status = 'pending_approval' THEN 1 END) AS pending_approvals,
				COUNT(CASE WHEN w.status = 'approved' THEN 1 END) AS approved_count,
				COUNT(CASE WHEN w.status = 'rejected' THEN 1 END) AS rejected_count,
				COUNT(CASE WHEN w.workflow_status IN ('detected', 'investigating', 'action_assigned') THEN 1 END) AS open_cases_count,
				COUNT(CASE WHEN w.workflow_status = 'closed' THEN 1 END) AS closed_cases_count,
				COUNT(CASE WHEN w.workflow_status != 'closed' AND w.due_date IS NOT NULL AND w.due_date < date('now') THEN 1 END) AS overdue_cases_count
			FROM waste_records w
			WHERE date(w.created_timestamp) >= :start_date
				AND date(w.created_timestamp) <= :end_date
				{$branchCondition}
		";
		$summaryParams = array_merge([':start_date' => $startDate, ':end_date' => $endDate], $branchParams);
		$stmtSum = $dbRaw->prepare($summarySql);
		$stmtSum->execute($summaryParams);
		$summary = $stmtSum->fetch(\PDO::FETCH_OBJ);

		$totalLoss = floatval($summary->total_loss_cost ?? 0);
		$totalRecovered = floatval($summary->total_recovered_amount ?? 0);
		$recoveryRate = ($totalLoss > 0) ? round(($totalRecovered / $totalLoss) * 100, 1) : 0.0;

		// 2. Pareto 80/20 Analysis by Causal
		$paretoSql = "
			SELECT 
				wr.id AS reason_id,
				wr.code AS reason_code,
				wr.name AS reason_name,
				wr.category AS reason_category,
				wr.risk_level,
				wr.color_class,
				COUNT(w.id) AS incidents_count,
				IFNULL(SUM(w.amount), 0) AS units_lost,
				IFNULL(SUM(w.total_cost), 0) AS loss_cost
			FROM waste_records w
			JOIN waste_reasons wr ON w.waste_reason_id = wr.id
			WHERE date(w.created_timestamp) >= :start_date
				AND date(w.created_timestamp) <= :end_date
				{$branchCondition}
			GROUP BY wr.id, wr.code, wr.name, wr.category, wr.risk_level, wr.color_class
			ORDER BY loss_cost DESC, incidents_count DESC
		";
		$stmtPareto = $dbRaw->prepare($paretoSql);
		$stmtPareto->execute($summaryParams);
		$rawPareto = $stmtPareto->fetchAll(\PDO::FETCH_OBJ);

		$paretoData = [];
		$runningCost = 0.0;
		foreach ($rawPareto as $row)
		{
			$cost = floatval($row->loss_cost);
			$runningCost += $cost;
			$cumPct = ($totalLoss > 0) ? round(($runningCost / $totalLoss) * 100, 1) : 100.0;

			$zone = 'Zona C (Bajo impacto)';
			if ($cumPct <= 80.0)
			{
				$zone = 'Zona A (80% del impacto vital)';
			}
			elseif ($cumPct <= 95.0)
			{
				$zone = 'Zona B (Impacto intermedio)';
			}

			$paretoData[] = [
				'reason_id' => $row->reason_id,
				'reason_code' => $row->reason_code,
				'reason_name' => $row->reason_name,
				'category' => $row->reason_category,
				'risk_level' => $row->risk_level,
				'color_class' => $row->color_class,
				'incidents_count' => intval($row->incidents_count),
				'units_lost' => floatval($row->units_lost),
				'loss_cost' => round($cost, 2),
				'percentage_of_total' => ($totalLoss > 0) ? round(($cost / $totalLoss) * 100, 1) : 0,
				'cumulative_percentage' => $cumPct,
				'pareto_zone' => $zone
			];
		}

		// 3. Loss by Category / Department
		$catSql = "
			SELECT 
				wr.category,
				COUNT(w.id) AS count,
				IFNULL(SUM(w.total_cost), 0) AS total_cost
			FROM waste_records w
			JOIN waste_reasons wr ON w.waste_reason_id = wr.id
			WHERE date(w.created_timestamp) >= :start_date
				AND date(w.created_timestamp) <= :end_date
				{$branchCondition}
			GROUP BY wr.category
			ORDER BY total_cost DESC
		";
		$stmtCat = $dbRaw->prepare($catSql);
		$stmtCat->execute($summaryParams);
		$lossByCategory = $stmtCat->fetchAll(\PDO::FETCH_OBJ);

		// 4. Loss by Branch
		$branchLossSql = "
			SELECT 
				b.id AS branch_id,
				b.name AS branch_name,
				b.code AS branch_code,
				COUNT(w.id) AS cases_count,
				IFNULL(SUM(w.total_cost), 0) AS total_cost
			FROM waste_records w
			JOIN branches b ON w.branch_id = b.id
			WHERE date(w.created_timestamp) >= :start_date
				AND date(w.created_timestamp) <= :end_date
			GROUP BY b.id, b.name, b.code
			ORDER BY total_cost DESC
		";
		$stmtBranchLoss = $dbRaw->prepare($branchLossSql);
		$stmtBranchLoss->execute([':start_date' => $startDate, ':end_date' => $endDate]);
		$lossByBranch = $stmtBranchLoss->fetchAll(\PDO::FETCH_OBJ);

		// 5. Top 10 High-Loss Products
		$prodSql = "
			SELECT 
				p.id AS product_id,
				p.name AS product_name,
				p.sku AS product_sku,
				qu.name AS qu_name,
				COUNT(w.id) AS waste_events,
				SUM(w.amount) AS total_units,
				SUM(w.total_cost) AS total_cost
			FROM waste_records w
			JOIN products p ON w.product_id = p.id
			JOIN quantity_units qu ON w.qu_id = qu.id
			WHERE date(w.created_timestamp) >= :start_date
				AND date(w.created_timestamp) <= :end_date
				{$branchCondition}
			GROUP BY p.id, p.name, p.sku, qu.name
			ORDER BY total_cost DESC
			LIMIT 10
		";
		$stmtProd = $dbRaw->prepare($prodSql);
		$stmtProd->execute($summaryParams);
		$topLossProducts = $stmtProd->fetchAll(\PDO::FETCH_OBJ);

		// 6. Workflow Status Distribution
		$workflowSql = "
			SELECT 
				w.workflow_status,
				COUNT(*) AS count,
				IFNULL(SUM(w.total_cost), 0) AS total_cost
			FROM waste_records w
			WHERE date(w.created_timestamp) >= :start_date
				AND date(w.created_timestamp) <= :end_date
				{$branchCondition}
			GROUP BY w.workflow_status
		";
		$stmtWf = $dbRaw->prepare($workflowSql);
		$stmtWf->execute($summaryParams);
		$workflowDistribution = $stmtWf->fetchAll(\PDO::FETCH_OBJ);

		// 7. Time Series Trend
		$timelineSql = "
			SELECT 
				date(w.created_timestamp) AS date_point,
				COUNT(w.id) AS count,
				IFNULL(SUM(w.total_cost), 0) AS cost
			FROM waste_records w
			WHERE date(w.created_timestamp) >= :start_date
				AND date(w.created_timestamp) <= :end_date
				{$branchCondition}
			GROUP BY date(w.created_timestamp)
			ORDER BY date_point ASC
		";
		$stmtTimeline = $dbRaw->prepare($timelineSql);
		$stmtTimeline->execute($summaryParams);
		$timelineRows = $stmtTimeline->fetchAll(\PDO::FETCH_OBJ);

		$timelineMap = [];
		foreach ($timelineRows as $tr)
		{
			$timelineMap[$tr->date_point] = floatval($tr->cost);
		}

		$currentTs = strtotime($startDate);
		$endTs = strtotime(min($endDate, date('Y-m-d')));
		$monthsShort = ['', 'Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
		$continuousTimeline = [];
		while ($currentTs <= $endTs)
		{
			$dtStr = date('Y-m-d', $currentTs);
			$label = date('d', $currentTs) . ' ' . ($monthsShort[intval(date('n', $currentTs))] ?? '');
			$continuousTimeline[] = [
				'date' => $dtStr,
				'label' => $label,
				'cost' => round($timelineMap[$dtStr] ?? 0.0, 2)
			];
			$currentTs = strtotime('+1 day', $currentTs);
		}

		// 8. Smart Proactive Waste Alarms
		$smartAlerts = [];

		// Overdue investigations
		$overdueSql = "
			SELECT w.id, w.case_number, p.name AS product_name, w.total_cost, w.due_date, u.username AS investigator_username
			FROM waste_records w
			JOIN products p ON w.product_id = p.id
			LEFT JOIN users u ON w.investigator_user_id = u.id
			WHERE w.workflow_status NOT IN ('closed', 'verified')
				AND w.due_date IS NOT NULL
				AND w.due_date < date('now')
			ORDER BY w.due_date ASC LIMIT 5
		";
		$overdueItems = $dbRaw->query($overdueSql)->fetchAll(\PDO::FETCH_OBJ);
		if (!empty($overdueItems))
		{
			$smartAlerts[] = [
				'type' => 'overdue_cases',
				'severity' => 'danger',
				'icon' => 'fa-solid fa-clock-rotate-left',
				'title' => count($overdueItems) . ' Investigaciones de Merma Vencidas',
				'description' => 'Existen casos asignados que han superado su fecha límite de resolución sin medidas preventivas cerradas.',
				'items' => $overdueItems
			];
		}

		// High value single-event losses (> $50)
		$highLossSql = "
			SELECT w.id, w.case_number, p.name AS product_name, w.total_cost, wr.name AS reason_name, b.name AS branch_name
			FROM waste_records w
			JOIN products p ON w.product_id = p.id
			JOIN waste_reasons wr ON w.waste_reason_id = wr.id
			JOIN branches b ON w.branch_id = b.id
			WHERE w.total_cost >= 50
				AND date(w.created_timestamp) >= date('now', '-30 days')
			ORDER BY w.total_cost DESC LIMIT 5
		";
		$highLossItems = $dbRaw->query($highLossSql)->fetchAll(\PDO::FETCH_OBJ);
		if (!empty($highLossItems))
		{
			$smartAlerts[] = [
				'type' => 'high_value_loss',
				'severity' => 'warning',
				'icon' => 'fa-solid fa-circle-exclamation',
				'title' => count($highLossItems) . ' Pérdidas Críticas de Alto Impacto',
				'description' => 'Incidentes individuales de merma superiores a $50 que requieren auditoría especial y dictamen de gerencia.',
				'items' => $highLossItems
			];
		}

		return [
			'period' => $period,
			'start_date' => $startDate,
			'end_date' => $endDate,
			'summary' => $summary,
			'recovery_rate' => $recoveryRate,
			'pareto' => $paretoData,
			'loss_by_category' => $lossByCategory,
			'loss_by_branch' => $lossByBranch,
			'top_products' => $topLossProducts,
			'workflow_distribution' => $workflowDistribution,
			'timeline' => $continuousTimeline,
			'alerts' => $smartAlerts
		];
	}

	public function GetWasteSummary(?int $branchId = null, ?string $startDate = null, ?string $endDate = null)
	{
		$analytics = $this->GetWasteAnalytics($branchId, 'month', $startDate, $endDate);
		return $analytics['summary'];
	}

	/**
	 * Adjunta o reemplaza fotografía de evidencia en un incidente de merma ya registrado.
	 */
	public function AttachEvidence(int $wasteRecordId, string $evidencePhotoPath, int $userId = GROCY_USER_ID)
	{
		$evidencePhotoPath = trim($evidencePhotoPath);
		if (empty($evidencePhotoPath) || !preg_match('#^(/api/waste/photo/)?waste_[A-Za-z0-9_.-]+\.(jpe?g|png|webp|gif|heic)$#i', $evidencePhotoPath))
		{
			throw new \Exception('Evidencia inválida: adjunte la foto desde la cámara o galería');
		}
		$record = $this->GetWasteRecord($wasteRecordId);
		if (!$record)
		{
			throw new \Exception('Registro de merma no encontrado');
		}
		if (strpos($evidencePhotoPath, '/api/waste/photo/') !== 0)
		{
			$evidencePhotoPath = '/api/waste/photo/' . ltrim($evidencePhotoPath, '/');
		}

		$dbRaw = DatabaseService::GetInstance()->GetDbConnectionRaw();
		$dbRaw->prepare('UPDATE waste_records SET evidence_photo_path = :p WHERE id = :id')
			->execute([':p' => $evidencePhotoPath, ':id' => $wasteRecordId]);

		$dbRaw->prepare("
			INSERT INTO waste_action_logs (waste_record_id, user_id, previous_status, new_status, action_title, action_details)
			VALUES (:record_id, :user_id, :prev, :prev, 'Evidencia fotográfica adjuntada', :details)
		")->execute([
			':record_id' => $wasteRecordId,
			':user_id' => $userId,
			':prev' => $record->status,
			':details' => 'Evidencia registrada: ' . basename($evidencePhotoPath)
		]);

		return $this->GetWasteRecord($wasteRecordId);
	}

	/**
	 * Corrige banderas incoherentes donde una merma aún pendiente fue marcada con inventory_deducted=1 sin transaction_id.
	 */
	public function ReconcileIntegrity(): int
	{
		$dbRaw = DatabaseService::GetInstance()->GetDbConnectionRaw();
		$stmt = $dbRaw->prepare("
			UPDATE waste_records
			SET inventory_deducted = 0
			WHERE status = 'pending_approval'
			  AND inventory_deducted = 1
			  AND TRIM(IFNULL(transaction_id, '')) = ''
		");
		$stmt->execute();
		return $stmt->rowCount();
	}
}
