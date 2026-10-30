<?php

namespace Grocy\Services;

class WorkforceService extends BaseService
{
	/**
	 * Get floor tasks for the mobile workforce view
	 */
	public function GetStoreTasks(int $branchId = 1, string $status = 'pending')
	{
		$dbRaw = DatabaseService::GetInstance()->GetDbConnectionRaw();

		// Auto-seed daily routine checklists if none exist for today
		$this->EnsureDailyChecklists($branchId);

		$sql = "
			SELECT 
				t.*,
				b.name AS branch_name,
				IFNULL(NULLIF(TRIM(u.first_name || ' ' || IFNULL(u.last_name, '')), ''), u.username) AS assigned_user_name,
				IFNULL(NULLIF(TRIM(cu.first_name || ' ' || IFNULL(cu.last_name, '')), ''), cu.username) AS completed_by_name
			FROM workforce_store_tasks t
			LEFT JOIN branches b ON t.branch_id = b.id
			LEFT JOIN users u ON t.assigned_user_id = u.id
			LEFT JOIN users cu ON t.completed_by_user_id = cu.id
			WHERE t.branch_id = :branch_id
		";
		$params = [':branch_id' => $branchId];

		if ($status !== 'all')
		{
			$sql .= " AND t.status = :status";
			$params[':status'] = $status;
		}

		$sql .= " ORDER BY t.status DESC, t.due_time ASC, t.id DESC";

		$stmt = $dbRaw->prepare($sql);
		$stmt->execute($params);
		return $stmt->fetchAll(\PDO::FETCH_OBJ);
	}

	/**
	 * Ensure daily routine tasks exist for the branch (Cold chain check, FEFO sweep, etc.)
	 */
	private function EnsureDailyChecklists(int $branchId)
	{
		$dbRaw = DatabaseService::GetInstance()->GetDbConnectionRaw();
		$today = date('Y-m-d');

		$stmt = $dbRaw->prepare("
			SELECT COUNT(*) FROM workforce_store_tasks 
			WHERE branch_id = :branch_id AND DATE(due_time) = :today
		");
		$stmt->execute([':branch_id' => $branchId, ':today' => $today]);

		if ($stmt->fetchColumn() == 0)
		{
			$routines = [
				[
					'task_type' => 'cold_chain_check',
					'assigned_role' => 'clerk',
					'title' => 'Control Térmico Vitrinas Lácteas (Turno Mañana 07:00)',
					'description' => 'Verificar que la temperatura de vitrinas de lácteos y quesos se encuentre entre 2°C y 6°C.',
					'due_time' => $today . ' 07:30:00'
				],
				[
					'task_type' => 'fefo_sweep',
					'assigned_role' => 'clerk',
					'title' => 'Barrido FEFO & Liquidación Preventiva (-30%)',
					'description' => 'Revisar lotes de leche fresca y yogur que venzan en las próximas 48 horas y aplicar etiqueta de liquidación en góndola.',
					'due_time' => $today . ' 10:00:00'
				],
				[
					'task_type' => 'stock_count',
					'assigned_role' => 'supervisor',
					'title' => 'Conteo Cíclico Sensible (Categoría Quesos)',
					'description' => 'Auditar conteo físico de las 5 referencias de queso de mayor costo para verificar exactitud de registro (IRA).',
					'due_time' => $today . ' 16:00:00'
				],
				[
					'task_type' => 'cold_chain_check',
					'assigned_role' => 'clerk',
					'title' => 'Inspección Térmica de Cierre de Turno (21:00)',
					'description' => 'Asegurar cortinas de noche de cámaras y registrar temperatura antes de cierre de local.',
					'due_time' => $today . ' 21:00:00'
				]
			];

			$insertStmt = $dbRaw->prepare("
				INSERT INTO workforce_store_tasks (branch_id, task_type, assigned_role, title, description, due_time, status)
				VALUES (:branch_id, :task_type, :assigned_role, :title, :description, :due_time, 'pending')
			");

			foreach ($routines as $r)
			{
				$insertStmt->execute([
					':branch_id' => $branchId,
					':task_type' => $r['task_type'],
					':assigned_role' => $r['assigned_role'],
					':title' => $r['title'],
					':description' => $r['description'],
					':due_time' => $r['due_time']
				]);
			}
		}

		// Asegurar que exista al menos una tarea bloqueada para la demo de operaciones
		$stmtBlocked = $dbRaw->prepare("SELECT COUNT(*) FROM workforce_store_tasks WHERE branch_id = :branch_id AND status = 'blocked'");
		$stmtBlocked->execute([':branch_id' => $branchId]);
		if ($stmtBlocked->fetchColumn() == 0)
		{
			$dbRaw->prepare("
				INSERT INTO workforce_store_tasks (branch_id, task_type, assigned_role, title, description, due_time, status, source_type, source_id, created_timestamp)
				VALUES (:branch_id, 'corrective_action', 'supervisor', 'Calibración y cambio de sensor en Vitrina Lácteos #2', 'Bloqueada: En espera de llegada de sonda térmica de repuesto por parte del servicio técnico Frío Industrial.', :due_time, 'blocked', 'equipment', 2, datetime('now', 'localtime'))
			")->execute([
				':branch_id' => $branchId,
				':due_time' => date('Y-m-d H:i:s', time() + 7200)
			]);
		}
	}

	/**
	 * Complete a workforce floor task
	 */
	public function CompleteStoreTask(int $taskId, array $data, int $userId)
	{
		$dbRaw = DatabaseService::GetInstance()->GetDbConnectionRaw();
		$notes = $data['notes'] ?? '';
		$evidencePath = $data['evidence_photo_path'] ?? null;

		$stmt = $dbRaw->prepare("
			UPDATE workforce_store_tasks 
			SET status = 'completed',
				evidence_photo_path = :evidence,
				description = description || ' | Finalizado: ' || :notes,
				completed_at = :completed_at,
				completed_by_user_id = :user_id
			WHERE id = :id AND status = 'pending'
		");

		$stmt->execute([
			':evidence' => $evidencePath,
			':notes' => $notes,
			':user_id' => $userId,
			':completed_at' => date('Y-m-d H:i:s'),
			':id' => $taskId
		]);

		if ($stmt->rowCount() === 0)
		{
			throw new \Exception("La tarea #{$taskId} no existe o ya ha sido completada.");
		}

		return true;
	}

	/**
	 * Merma rápida desde Piso de Tienda: mismo flujo que Registrar merma (WasteService::ReportWaste: causal válida,
	 * evidencia obligatoria, aprobación, turno, idempotencia). Antes insertaba directo con un estado sin flujo.
	 */
	public function QuickWasteReport(array $data, int $userId)
	{
		$dbRaw = DatabaseService::GetInstance()->GetDbConnectionRaw();
		$reasonCode = trim((string) ($data['reason_code'] ?? ''));
		$stmt = $dbRaw->prepare('SELECT id FROM waste_reasons WHERE code = :code AND active = 1 LIMIT 1');
		$stmt->execute([':code' => $reasonCode]);
		$reasonId = intval($stmt->fetchColumn());
		if ($reasonId <= 0)
		{
			throw new \Exception('Seleccione una causal de merma válida');
		}
		$notes = trim((string) ($data['notes'] ?? ''));

		$row = WasteService::GetInstance()->ReportWaste(
			intval($data['product_id'] ?? 0), floatval($data['amount'] ?? 0), $reasonId, intval($data['branch_id'] ?? 1),
			!empty($data['location_id']) ? intval($data['location_id']) : null, 'default',
			$notes !== '' ? $notes : 'Reporte rápido desde Piso de Tienda', $data['evidence_photo_path'] ?? null,
			null, null, null, null, null, $data['idempotency_key'] ?? null, $userId, false, !empty($data['confirm_duplicate'])
		);

		return [
			'success' => true,
			'waste_record_id' => intval($row->id),
			'case_number' => $row->case_number,
			'total_cost' => floatval($row->total_cost),
			'status' => $row->status,
			'message' => $row->status === 'approved'
				? "Caso {$row->case_number} registrado y stock descontado"
				: "Caso {$row->case_number} registrado; pendiente de aprobación del supervisor"
		];
	}
}
