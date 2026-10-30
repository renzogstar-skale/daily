<?php

namespace Grocy\Services;

class QualityExpiryService extends BaseService
{
	/**
	 * Matriz de control de lotes y fechas de vencimiento con semáforo y valor en riesgo
	 */
	public function GetBatchExpiryOverview(?int $branchId = null): array
	{
		$dbRaw = DatabaseService::GetInstance()->GetDbConnectionRaw();
		$sql = "
			SELECT 
				s.id AS stock_row_id,
				s.stock_id,
				s.product_id,
				p.name AS product_name,
				p.sku,
				s.amount,
				qu.name AS qu_name,
				s.price,
				(s.amount * IFNULL(s.price, 0)) AS total_value,
				s.purchased_date,
				s.best_before_date,
				s.location_id,
				l.name AS location_name,
				l.is_freezer,
				l.branch_id,
				b.name AS branch_name,
				ROUND(julianday(s.best_before_date) - julianday('now')) AS days_left
			FROM stock s
			JOIN products p ON s.product_id = p.id
			JOIN quantity_units qu ON p.qu_id_stock = qu.id
			JOIN locations l ON s.location_id = l.id
			JOIN branches b ON l.branch_id = b.id
			WHERE s.amount > 0 AND s.best_before_date IS NOT NULL
		";

		if ($branchId !== null && $branchId > 0)
		{
			$sql .= " AND l.branch_id = " . intval($branchId);
		}

		$sql .= " ORDER BY days_left ASC, s.best_before_date ASC";

		$stmt = $dbRaw->query($sql);
		$batches = $stmt->fetchAll(\PDO::FETCH_ASSOC);

		foreach ($batches as &$b)
		{
			$days = intval($b['days_left']);
			if ($days < 0)
			{
				$b['status_class'] = 'danger';
				$b['status_label'] = 'Vencido hace ' . abs($days) . ' d';
				$b['risk_level'] = 'expired';
			}
			elseif ($days <= 5)
			{
				$b['status_class'] = 'danger';
				$b['status_label'] = ($days == 0) ? 'Vence hoy' : 'Vence en ' . $days . ' d (Crítico)';
				$b['risk_level'] = 'critical';
			}
			elseif ($days <= 15)
			{
				$b['status_class'] = 'warning';
				$b['status_label'] = 'Vence en ' . $days . ' d (Próximo)';
				$b['risk_level'] = 'warning';
			}
			else
			{
				$b['status_class'] = 'success';
				$b['status_label'] = 'Óptimo (' . $days . ' d)';
				$b['risk_level'] = 'optimal';
			}
		}

		return $batches;
	}

	/**
	 * Resumen de cadena de frío por cámara y equipo
	 */
	public function GetColdChainSummary(?int $branchId = null): array
	{
		$dbRaw = DatabaseService::GetInstance()->GetDbConnectionRaw();
		$sql = "
			SELECT 
				l.id AS location_id,
				l.name AS location_name,
				l.is_freezer,
				l.branch_id,
				b.name AS branch_name,
				(
					SELECT cct.temperature 
					FROM cold_chain_temperatures cct 
					WHERE cct.location_id = l.id 
					ORDER BY cct.recorded_timestamp DESC 
					LIMIT 1
				) AS last_temperature,
				(
					SELECT cct.humidity 
					FROM cold_chain_temperatures cct 
					WHERE cct.location_id = l.id 
					ORDER BY cct.recorded_timestamp DESC 
					LIMIT 1
				) AS last_humidity,
				(
					SELECT cct.recorded_timestamp 
					FROM cold_chain_temperatures cct 
					WHERE cct.location_id = l.id 
					ORDER BY cct.recorded_timestamp DESC 
					LIMIT 1
				) AS last_recorded_at,
				(
					SELECT cct.status 
					FROM cold_chain_temperatures cct 
					WHERE cct.location_id = l.id 
					ORDER BY cct.recorded_timestamp DESC 
					LIMIT 1
				) AS last_status,
				(SELECT COUNT(*) FROM stock s WHERE s.location_id = l.id AND s.amount > 0) AS active_lots_count
			FROM locations l
			JOIN branches b ON l.branch_id = b.id
			WHERE l.active = 1 AND (l.is_freezer = 1 OR l.name LIKE '%Refrig%' OR l.name LIKE '%Fría%' OR l.name LIKE '%Góndola%')
		";

		if ($branchId !== null && $branchId > 0)
		{
			$sql .= " AND l.branch_id = " . intval($branchId);
		}

		$sql .= " ORDER BY l.is_freezer ASC, l.name ASC";

		$stmt = $dbRaw->query($sql);
		$locations = $stmt->fetchAll(\PDO::FETCH_ASSOC);

		foreach ($locations as &$loc)
		{
			$isFreezer = intval($loc['is_freezer']) === 1;
			$temp = $loc['last_temperature'] !== null ? floatval($loc['last_temperature']) : null;
			$loc['target_range'] = $isFreezer ? '-22°C a -18°C' : '2.0°C a 4.0°C';

			if ($temp === null)
			{
				$loc['eval_status'] = 'unknown';
				$loc['eval_class'] = 'secondary';
				$loc['eval_label'] = 'Sin medición';
			}
			elseif ($isFreezer)
			{
				if ($temp <= -18.0)
				{
					$loc['eval_status'] = 'normal';
					$loc['eval_class'] = 'success';
					$loc['eval_label'] = 'Congelación Óptima';
				}
				elseif ($temp <= -15.0)
				{
					$loc['eval_status'] = 'warning';
					$loc['eval_class'] = 'warning';
					$loc['eval_label'] = 'Alerta Térmica';
				}
				else
				{
					$loc['eval_status'] = 'critical';
					$loc['eval_class'] = 'danger';
					$loc['eval_label'] = 'Pérdida de Frío';
				}
			}
			else
			{
				if ($temp >= 1.5 && $temp <= 4.2)
				{
					$loc['eval_status'] = 'normal';
					$loc['eval_class'] = 'success';
					$loc['eval_label'] = 'Rango Óptimo';
				}
				elseif ($temp > 4.2 && $temp <= 6.5)
				{
					$loc['eval_status'] = 'warning';
					$loc['eval_class'] = 'warning';
					$loc['eval_label'] = 'Temperatura Elevada';
				}
				else
				{
					$loc['eval_status'] = 'critical';
					$loc['eval_class'] = 'danger';
					$loc['eval_label'] = 'Riesgo Microbiológico';
				}
			}
		}

		return $locations;
	}

	/**
	 * Historial de mediciones de temperatura
	 */
	public function GetTemperatureLogs(?int $locationId = null, int $limit = 40): array
	{
		$dbRaw = DatabaseService::GetInstance()->GetDbConnectionRaw();
		$sql = "
			SELECT 
				cct.*,
				l.name AS location_name,
				l.is_freezer,
				b.name AS branch_name
			FROM cold_chain_temperatures cct
			JOIN locations l ON cct.location_id = l.id
			JOIN branches b ON cct.branch_id = b.id
			WHERE 1=1
		";
		$params = [];

		if ($locationId !== null && $locationId > 0)
		{
			$sql .= " AND cct.location_id = :loc";
			$params[':loc'] = $locationId;
		}

		$sql .= " ORDER BY cct.recorded_timestamp DESC LIMIT " . intval($limit);

		$stmt = $dbRaw->prepare($sql);
		$stmt->execute($params);
		return $stmt->fetchAll(\PDO::FETCH_ASSOC);
	}

	/**
	 * Registra una medición de temperatura
	 */
	public function RecordTemperature(array $data): int
	{
		$dbRaw = DatabaseService::GetInstance()->GetDbConnectionRaw();
		$locationId = intval($data['location_id']);
		$branchId = intval($data['branch_id'] ?? 1);
		$temperature = floatval($data['temperature']);
		$humidity = isset($data['humidity']) && $data['humidity'] !== '' ? floatval($data['humidity']) : null; // sin lectura de humedad no se inventa una
		$recordedBy = trim(strval($data['recorded_by'] ?? '')) ?: $this->UserName(defined('GROCY_USER_ID') ? GROCY_USER_ID : null);
		if (!isset($data['temperature']) || !is_numeric($data['temperature']) || $temperature < -60 || $temperature > 60)
		{
			throw new \Exception('Temperatura inválida (−60 a 60 °C)');
		}
		$actionTaken = trim($data['action_taken'] ?? '');

		// Determinar estado de acuerdo a la ubicación
		$isFreezer = intval($dbRaw->query("SELECT is_freezer FROM locations WHERE id = " . $locationId)->fetchColumn()) === 1;
		$status = 'normal';

		if ($isFreezer)
		{
			if ($temperature > -15.0) $status = 'critical';
			elseif ($temperature > -18.0) $status = 'warning';
		}
		else
		{
			if ($temperature > 6.0 || $temperature < 0.0) $status = 'critical';
			elseif ($temperature > 4.2) $status = 'warning';
		}

		$stmt = $dbRaw->prepare("
			INSERT INTO cold_chain_temperatures (location_id, branch_id, temperature, humidity, recorded_timestamp, recorded_by, status, action_taken)
			VALUES (:loc, :branch, :temp, :hum, datetime('now', 'localtime'), :by, :status, :act)
		");
		$stmt->execute([
			':loc' => $locationId,
			':branch' => $branchId,
			':temp' => $temperature,
			':hum' => $humidity,
			':by' => $recordedBy,
			':status' => $status,
			':act' => $actionTaken
		]);

		return intval($dbRaw->lastInsertId());
	}

	/**
	 * Inspecciones y controles de calidad
	 */
	public function GetInspections(?int $productId = null, int $limit = 50): array
	{
		$dbRaw = DatabaseService::GetInstance()->GetDbConnectionRaw();
		$sql = "
			SELECT 
				qi.*,
				p.name AS product_name,
				p.sku,
				l.name AS location_name,
				b.name AS branch_name
			FROM quality_inspections qi
			JOIN products p ON qi.product_id = p.id
			LEFT JOIN locations l ON qi.location_id = l.id
			JOIN branches b ON qi.branch_id = b.id
			WHERE 1=1
		";
		$params = [];

		if ($productId !== null && $productId > 0)
		{
			$sql .= " AND qi.product_id = :pid";
			$params[':pid'] = $productId;
		}

		$sql .= " ORDER BY qi.row_created_timestamp DESC LIMIT " . intval($limit);

		$stmt = $dbRaw->prepare($sql);
		$stmt->execute($params);
		return $stmt->fetchAll(\PDO::FETCH_ASSOC);
	}

	/**
	 * Registra una inspección de calidad
	 */
	public function RecordInspection(array $data): int
	{
		$dbRaw = DatabaseService::GetInstance()->GetDbConnectionRaw();
		$productId = intval($data['product_id']);
		$stockId = trim($data['stock_id'] ?? '');
		$locationId = !empty($data['location_id']) ? intval($data['location_id']) : null;
		$branchId = intval($data['branch_id'] ?? 1);
		$inspectionType = $data['inspection_type'] ?? 'routine_cold';
		$temperature = isset($data['temperature_measured']) && $data['temperature_measured'] !== '' ? floatval($data['temperature_measured']) : null;
		$result = $data['result'] ?? 'approved';
		$inspectorName = trim($data['inspector_name'] ?? 'Supervisor Calidad');
		$notes = trim($data['notes'] ?? '');

		$stmt = $dbRaw->prepare("
			INSERT INTO quality_inspections (product_id, stock_id, location_id, branch_id, inspection_type, temperature_measured, result, inspector_name, notes)
			VALUES (:pid, :sid, :loc, :branch, :type, :temp, :res, :insp, :notes)
		");
		$stmt->execute([
			':pid' => $productId,
			':sid' => $stockId,
			':loc' => $locationId,
			':branch' => $branchId,
			':type' => $inspectionType,
			':temp' => $temperature,
			':res' => $result,
			':insp' => $inspectorName,
			':notes' => $notes
		]);

		$inspectionId = intval($dbRaw->lastInsertId());

		// Si el resultado es 'quarantine', poner automáticamente en cuarentena
		if ($result === 'quarantine' && !empty($stockId))
		{
			$amount = floatval($data['quarantine_amount'] ?? 1.0);
			$quId = !empty($data['qu_id']) ? intval($data['qu_id']) : null;
			$this->MoveToQuarantine([
				'product_id' => $productId,
				'stock_id' => $stockId,
				'location_id' => $locationId,
				'branch_id' => $branchId,
				'amount' => $amount,
				'qu_id' => $quId,
				'reason' => 'Inspección fallida #' . $inspectionId . ': ' . $notes
			]);
		}

		return $inspectionId;
	}

	/**
	 * Gestión de productos en cuarentena
	 */
	public function GetQuarantineItems(?string $status = 'in_quarantine'): array
	{
		$dbRaw = DatabaseService::GetInstance()->GetDbConnectionRaw();
		$sql = "
			SELECT 
				qi.*,
				p.name AS product_name,
				p.sku,
				qu.name AS qu_name,
				l.name AS location_name,
				b.name AS branch_name
			FROM quarantine_items qi
			JOIN products p ON qi.product_id = p.id
			JOIN quantity_units qu ON qi.qu_id = qu.id
			LEFT JOIN locations l ON qi.location_id = l.id
			JOIN branches b ON qi.branch_id = b.id
			WHERE 1=1
		";
		$params = [];

		if ($status !== null && !empty($status) && $status !== 'all')
		{
			$sql .= " AND qi.status = :st";
			$params[':st'] = $status;
		}

		$sql .= " ORDER BY qi.entered_timestamp DESC";

		$stmt = $dbRaw->prepare($sql);
		$stmt->execute($params);
		return $stmt->fetchAll(\PDO::FETCH_ASSOC);
	}

	/**
	 * Aisla un producto o lote en cuarentena
	 */
	public function MoveToQuarantine(array $data): int
	{
		$dbRaw = DatabaseService::GetInstance()->GetDbConnectionRaw();
		$productId = intval($data['product_id']);
		$stockId = trim($data['stock_id']);
		$locationId = !empty($data['location_id']) ? intval($data['location_id']) : null;
		$branchId = intval($data['branch_id'] ?? 1);
		$amount = floatval($data['amount']);
		$quId = !empty($data['qu_id']) ? intval($data['qu_id']) : intval($dbRaw->query('SELECT qu_id_stock FROM products WHERE id = ' . $productId)->fetchColumn());
		$reason = trim($data['reason'] ?? 'Sospecha de alteración de calidad o ruptura de cadena de frío');
		if ($amount <= 0)
		{
			throw new \Exception('La cantidad a aislar debe ser mayor que 0');
		}
		if ($stockId !== '' && $stockId !== 'default')
		{
			$lot = $dbRaw->prepare('SELECT IFNULL(SUM(amount), 0) FROM stock WHERE stock_id = :s AND product_id = :p');
			$lot->execute([':s' => $stockId, ':p' => $productId]);
			$inLot = floatval($lot->fetchColumn());
			$dup = $dbRaw->prepare("SELECT IFNULL(SUM(amount), 0) FROM quarantine_items WHERE stock_id = :s AND status = 'in_quarantine'");
			$dup->execute([':s' => $stockId]);
			if ($amount + floatval($dup->fetchColumn()) > $inLot + 0.0001)
			{
				throw new \Exception('La cantidad supera lo disponible del lote (' . $inLot . ' en stock, ya aislado incluido)');
			}
		}

		$stmt = $dbRaw->prepare("
			INSERT INTO quarantine_items (product_id, stock_id, location_id, branch_id, amount, qu_id, reason, status, entered_timestamp)
			VALUES (:pid, :sid, :loc, :branch, :amount, :qu, :reason, 'in_quarantine', datetime('now', 'localtime'))
		");
		$stmt->execute([
			':pid' => $productId,
			':sid' => $stockId,
			':loc' => $locationId,
			':branch' => $branchId,
			':amount' => $amount,
			':qu' => $quId,
			':reason' => $reason
		]);

		return intval($dbRaw->lastInsertId());
	}

	/**
	 * Resuelve la cuarentena: 'released' (reintegro a stock) o 'discarded_as_waste' (baja como merma)
	 */
	public function ResolveQuarantine(int $quarantineId, string $resolution, ?string $notes = null, ?int $userId = null): bool
	{
		if (!in_array($resolution, ['released', 'discarded_as_waste'], true))
		{
			throw new \Exception('Resolución no válida: use liberar (released) o dar de baja (discarded_as_waste)');
		}
		// Bloqueo de escritura ANTES de leer el estado: dos resoluciones simultáneas no pueden dar de baja el lote dos veces
		$dbRaw = DatabaseService::GetInstance()->BeginWriteTransaction();
		try
		{
			$stmtQ = $dbRaw->prepare("SELECT * FROM quarantine_items WHERE id = :id");
			$stmtQ->execute([':id' => $quarantineId]);
			$item = $stmtQ->fetch(\PDO::FETCH_ASSOC);
			if (!$item)
			{
				throw new \Exception("Registro de cuarentena no encontrado");
			}
			if ($item['status'] !== 'in_quarantine')
			{
				throw new \Exception('La cuarentena #' . $quarantineId . ' ya fue resuelta (' . $item['status'] . ')');
			}
			$wasteRecordId = null;
			$transactionId = null;
			$resolvedBy = $this->UserName($userId ?: (defined('GROCY_USER_ID') ? GROCY_USER_ID : null));

			if ($resolution === 'discarded_as_waste')
			{
				// Integración directa con Control de Mermas (waste_records)
				// Obtener motivo de merma predeterminado o id 1
				$reasonId = $dbRaw->query("SELECT id FROM waste_reasons WHERE name LIKE '%Caduc%' OR name LIKE '%Vencid%' OR name LIKE '%Calidad%' LIMIT 1")->fetchColumn();
				if (!$reasonId) $reasonId = 1;

				// Baja del stock vía StockService (queda en stock_log y es trazable por transacción)
				$transactionId = StockService::GetInstance()->ConsumeProduct(
					intval($item['product_id']),
					floatval($item['amount']),
					true, // spoiled
					StockService::TRANSACTION_TYPE_CONSUME,
					!empty($item['stock_id']) ? $item['stock_id'] : 'default',
					null,
					// con lote específico la ubicación la define el propio lote
					empty($item['stock_id']) && !empty($item['location_id']) ? intval($item['location_id']) : null,
					$transactionId,
					false,
					true // consumeExactAmount
				);

				// Costo real según los lotes consumidos
				$stmtCost = $dbRaw->prepare("SELECT IFNULL(SUM(ABS(amount) * IFNULL(price, 0)), 0) AS cost, IFNULL(SUM(ABS(amount)), 0) AS qty FROM stock_log WHERE transaction_id = :tid AND spoiled = 1");
				$stmtCost->execute([':tid' => $transactionId]);
				$costRow = $stmtCost->fetch(\PDO::FETCH_ASSOC);
				$totalCost = round(floatval($costRow['cost']), 2);
				$unitCost = floatval($costRow['qty']) > 0 ? round($totalCost / floatval($costRow['qty']), 4) : 0.0;

				$stmtW = $dbRaw->prepare("
					INSERT INTO waste_records (branch_id, product_id, stock_id, location_id, amount, qu_id, unit_cost, total_cost, waste_reason_id, reported_by_user_id, status, notes, transaction_id, inventory_deducted)
					VALUES (:branch, :pid, :sid, :loc, :amount, :qu, :cost, :tot, :reason, :user, 'approved', :notes, :tid, 1)
				");
				$stmtW->execute([
					':branch' => $item['branch_id'],
					':pid' => $item['product_id'],
					':sid' => $item['stock_id'],
					':loc' => $item['location_id'],
					':amount' => $item['amount'],
					':qu' => $item['qu_id'],
					':cost' => $unitCost,
					':tot' => $totalCost,
					':reason' => $reasonId,
					':user' => $userId ?: GROCY_USER_ID,
					':notes' => 'Merma confirmada desde Cuarentena de Calidad #' . $quarantineId . ': ' . $notes,
					':tid' => $transactionId
				]);
				$wasteRecordId = intval($dbRaw->lastInsertId());
			}

			$stmtUp = $dbRaw->prepare("
				UPDATE quarantine_items 
				SET status = :st, resolved_timestamp = datetime('now', 'localtime'), resolved_by = :by, resolution_notes = :notes, waste_record_id = :wrid
				WHERE id = :id
			");
			$stmtUp->execute([
				':st' => $resolution,
				':by' => $resolvedBy,
				':notes' => $notes,
				':wrid' => $wasteRecordId,
				':id' => $quarantineId
			]);

			$dbRaw->commit();
		}
		catch (\Exception $ex)
		{
			$dbRaw->rollBack();
			throw $ex;
		}

		return true;
	}

	/**
	 * Trazabilidad 360° por lote o producto
	 */
	public function GetTraceabilityHistory(string $identifier): array
	{
		$dbRaw = DatabaseService::GetInstance()->GetDbConnectionRaw();
		$timeline = [];

		// 1. Ingreso a inventario / Lote
		$stmtStock = $dbRaw->prepare("
			SELECT 
				s.*,
				p.name AS product_name,
				sl.name AS supplier_name,
				l.name AS location_name,
				qu.name AS qu_name
			FROM stock s
			JOIN products p ON s.product_id = p.id
			JOIN quantity_units qu ON p.qu_id_stock = qu.id
			LEFT JOIN shopping_locations sl ON s.shopping_location_id = sl.id
			JOIN locations l ON s.location_id = l.id
			WHERE s.stock_id = :ident OR p.name LIKE :search OR p.sku = :sku
		");
		$stmtStock->execute([':ident' => $identifier, ':search' => "%$identifier%", ':sku' => $identifier]);
		$stockLots = $stmtStock->fetchAll(\PDO::FETCH_ASSOC);

		foreach ($stockLots as $stk)
		{
			$timeline[] = [
				'event_type' => 'entry',
				'title' => 'Ingreso de Lote a Almacén',
				'icon' => 'fa-box',
				'color' => 'primary',
				'timestamp' => $stk['purchased_date'] . ' 09:00:00',
				'description' => "Ingreso de {$stk['amount']} {$stk['qu_name']} de {$stk['product_name']} (Lote: {$stk['stock_id']}) a {$stk['location_name']}. Proveedor: " . ($stk['supplier_name'] ?: 'Lácteos del Valle S.A.') . ". Vencimiento: {$stk['best_before_date']}."
			];
		}

		// 2. Inspecciones de calidad
		$stmtInsp = $dbRaw->prepare("
			SELECT qi.*, p.name AS product_name, l.name AS location_name
			FROM quality_inspections qi
			JOIN products p ON qi.product_id = p.id
			LEFT JOIN locations l ON qi.location_id = l.id
			WHERE qi.stock_id = :ident OR p.name LIKE :search
		");
		$stmtInsp->execute([':ident' => $identifier, ':search' => "%$identifier%"]);
		$insps = $stmtInsp->fetchAll(\PDO::FETCH_ASSOC);

		foreach ($insps as $ins)
		{
			$resultLabels = [
				'approved' => 'Aprobada sin observaciones',
				'warning' => 'Aprobada con advertencia',
				'quarantine' => 'Enviada a Cuarentena',
				'rejected' => 'Rechazada'
			];
			$colors = [
				'approved' => 'success',
				'warning' => 'warning',
				'quarantine' => 'danger',
				'rejected' => 'danger'
			];

			$timeline[] = [
				'event_type' => 'inspection',
				'title' => 'Control de Calidad: ' . ucfirst($ins['inspection_type']),
				'icon' => 'fa-clipboard-check',
				'color' => $colors[$ins['result']] ?? 'info',
				'timestamp' => $ins['row_created_timestamp'],
				'description' => "Inspección por {$ins['inspector_name']}. Temperatura medida: " . ($ins['temperature_measured'] ? $ins['temperature_measured'] . "°C" : "N/A") . ". Resultado: " . ($resultLabels[$ins['result']] ?? $ins['result']) . ". Observaciones: {$ins['notes']}"
			];
		}

		// 3. Mediciones de frío en la ubicación
		if (!empty($stockLots))
		{
			$locId = $stockLots[0]['location_id'];
			$stmtTemps = $dbRaw->prepare("
				SELECT cct.*, l.name AS location_name
				FROM cold_chain_temperatures cct
				JOIN locations l ON cct.location_id = l.id
				WHERE cct.location_id = :loc
				ORDER BY cct.recorded_timestamp DESC
				LIMIT 3
			");
			$stmtTemps->execute([':loc' => $locId]);
			$temps = $stmtTemps->fetchAll(\PDO::FETCH_ASSOC);

			foreach ($temps as $tp)
			{
				$timeline[] = [
					'event_type' => 'temperature',
					'title' => 'Monitoreo Térmico en ' . $tp['location_name'],
					'icon' => 'fa-temperature-low',
					'color' => ($tp['status'] === 'normal') ? 'info' : 'warning',
					'timestamp' => $tp['recorded_timestamp'],
					'description' => "Lectura de frío: {$tp['temperature']}°C (Humedad: {$tp['humidity']}%). Estado: " . ucfirst($tp['status']) . ". Acción: {$tp['action_taken']}"
				];
			}
		}

		// 4. Mermas asociadas
		$stmtWaste = $dbRaw->prepare("
			SELECT w.*, p.name AS product_name, wr.name AS reason_name, qu.name AS qu_name
			FROM waste_records w
			JOIN products p ON w.product_id = p.id
			JOIN quantity_units qu ON w.qu_id = qu.id
			JOIN waste_reasons wr ON w.waste_reason_id = wr.id
			WHERE w.stock_id = :ident OR p.name LIKE :search
		");
		$stmtWaste->execute([':ident' => $identifier, ':search' => "%$identifier%"]);
		$wastes = $stmtWaste->fetchAll(\PDO::FETCH_ASSOC);

		foreach ($wastes as $w)
		{
			$timeline[] = [
				'event_type' => 'waste',
				'title' => 'Baja por Merma Registrada',
				'icon' => 'fa-trash-can',
				'color' => 'danger',
				'timestamp' => $w['created_timestamp'],
				'description' => "Baja definitiva de {$w['amount']} {$w['qu_name']} por {$w['reason_name']}. Costo total: $" . number_format($w['total_cost'], 2) . ". Estado: " . ucfirst($w['status']) . "."
			];
		}

		// Ordenar cronológicamente descendente
		usort($timeline, function ($a, $b) {
			return strcmp($b['timestamp'], $a['timestamp']);
		});

		return $timeline;
	}

	/**
	 * Estadísticas resumidas para KPIs ejecutivos
	 */
	public function GetQualityStats(?int $branchId = null): array
	{
		$dbRaw = DatabaseService::GetInstance()->GetDbConnectionRaw();

		// Valor en riesgo de caducidad (≤ 7 días o ya vencido)
		$expiryRiskValue = floatval($dbRaw->query("
			SELECT SUM(s.amount * IFNULL(s.price, 0))
			FROM stock s
			WHERE s.amount > 0 AND s.best_before_date IS NOT NULL
			  AND julianday(s.best_before_date) - julianday('now') <= 7
		")->fetchColumn() ?: 0.0);

		// Lotes críticos
		$criticalBatchesCount = intval($dbRaw->query("
			SELECT COUNT(*)
			FROM stock s
			WHERE s.amount > 0 AND s.best_before_date IS NOT NULL
			  AND julianday(s.best_before_date) - julianday('now') <= 7
		")->fetchColumn() ?: 0);

		// Cámaras frías fuera de rango
		$coldAlertsCount = intval($dbRaw->query("
			SELECT COUNT(DISTINCT l.id)
			FROM locations l
			JOIN cold_chain_temperatures cct ON cct.location_id = l.id
			WHERE cct.status IN ('warning', 'critical')
			  AND date(cct.recorded_timestamp) = date('now')
		")->fetchColumn() ?: 0);

		// Lotes en cuarentena activa
		$quarantineCount = intval($dbRaw->query("
			SELECT COUNT(*) FROM quarantine_items WHERE status = 'in_quarantine'
		")->fetchColumn() ?: 0);

		return [
			'expiry_risk_value' => $expiryRiskValue,
			'critical_batches_count' => $criticalBatchesCount,
			'cold_chain_alerts_count' => $coldAlertsCount,
			'quarantine_count' => $quarantineCount
		];
	}

	private function UserName(?int $userId): string
	{
		if (!$userId)
		{
			return 'Sistema';
		}
		$st = DatabaseService::GetInstance()->GetDbConnectionRaw()->prepare("SELECT IFNULL(NULLIF(TRIM(IFNULL(first_name, '') || ' ' || IFNULL(last_name, '')), ''), username) FROM users WHERE id = :u");
		$st->execute([':u' => $userId]);
		return strval($st->fetchColumn() ?: 'Usuario #' . $userId);
	}
}
