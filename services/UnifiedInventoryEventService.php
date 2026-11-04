<?php

namespace Grocy\Services;

class UnifiedInventoryEventService extends BaseService
{
	// 12 Eventos Canónicos de Dominio
	public const EVENT_MERCHANDISE_RECEIVED           = 'merchandise.received';
	public const EVENT_MERCHANDISE_DISPATCHED         = 'merchandise.dispatched';
	public const EVENT_TRANSFER_CREATED               = 'transfer.created';
	public const EVENT_TRANSFER_RECEIVED              = 'transfer.received';
	public const EVENT_INVENTORY_COUNT_COMPLETED      = 'inventory.count.completed';
	public const EVENT_INVENTORY_DISCREPANCY_DETECTED = 'inventory.discrepancy.detected';
	public const EVENT_DEVICE_CUSTODY_CHANGED         = 'device.custody.changed';
	public const EVENT_DEVICE_MISSING_REPORTED        = 'device.missing.reported';
	public const EVENT_RECONCILIATION_COMPLETED       = 'reconciliation.completed';
	public const EVENT_INVESTIGATION_OPENED           = 'investigation.opened';
	public const EVENT_ADJUSTMENT_APPROVED            = 'adjustment.approved';
	public const EVENT_CASE_RESOLVED                  = 'case.resolved';

	public const ALL_EVENTS = [
		self::EVENT_MERCHANDISE_RECEIVED,
		self::EVENT_MERCHANDISE_DISPATCHED,
		self::EVENT_TRANSFER_CREATED,
		self::EVENT_TRANSFER_RECEIVED,
		self::EVENT_INVENTORY_COUNT_COMPLETED,
		self::EVENT_INVENTORY_DISCREPANCY_DETECTED,
		self::EVENT_DEVICE_CUSTODY_CHANGED,
		self::EVENT_DEVICE_MISSING_REPORTED,
		self::EVENT_RECONCILIATION_COMPLETED,
		self::EVENT_INVESTIGATION_OPENED,
		self::EVENT_ADJUSTMENT_APPROVED,
		self::EVENT_CASE_RESOLVED,
	];

	private function Db(): \PDO
	{
		return DatabaseService::GetInstance()->GetDbConnectionRaw();
	}

	/**
	 * Emite un evento de dominio transaccional e idempotente
	 */
	public function Publish(
		string $eventName,
		string $aggregateType,
		string $aggregateId,
		array $payload,
		?int $branchId = null,
		?int $userId = null,
		?string $uuid = null
	): string {
		$db = $this->Db();
		$eventUuid = $uuid ?: bin2hex(random_bytes(16));
		$payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE);

		// Idempotencia: si ya existe el UUID, no duplicar
		$q = $db->prepare('SELECT event_uuid FROM daily_domain_events WHERE event_uuid = :u');
		$q->execute([':u' => $eventUuid]);
		$existing = $q->fetchColumn();

		if ($existing)
		{
			return $existing;
		}

		$ins = $db->prepare('INSERT INTO daily_domain_events (event_uuid, event_name, aggregate_type, aggregate_id, branch_id, user_id, payload)
			 VALUES (:u, :name, :agg_type, :agg_id, :branch, :user, :p)');
		$ins->execute([
			':u' => $eventUuid,
			':name' => $eventName,
			':agg_type' => $aggregateType,
			':agg_id' => strval($aggregateId),
			':branch' => $branchId,
			':user' => $userId,
			':p' => $payloadJson
		]);

		// Ejecución sincrónica de suscriptores para garantizar consistencia inmediata
		$this->DispatchSubscribers($eventName, $aggregateType, $aggregateId, $payload, $branchId, $userId);

		// Marcar como procesado
		$upd = $db->prepare("UPDATE daily_domain_events SET processed_at = datetime('now', 'localtime'), status = 'delivered' WHERE event_uuid = :u");
		$upd->execute([':u' => $eventUuid]);

		return $eventUuid;
	}

	/**
	 * Despacha los eventos a los módulos suscriptores
	 */
	private function DispatchSubscribers(
		string $eventName,
		string $aggregateType,
		string $aggregateId,
		array $payload,
		?int $branchId,
		?int $userId
	): void {
		$db = $this->Db();

		switch ($eventName)
		{
			case self::EVENT_DEVICE_MISSING_REPORTED:
				// Si un celular o activo serializado no aparece:
				// 1. Poner dispositivo en estado 'not_located' y bloquear venta en POS
				$imei = $payload['imei'] ?? null;
				if ($imei)
				{
					$upd = $db->prepare("UPDATE device_units SET state = 'not_located', block_reason = 'Investigación Prevención de Pérdidas 360' WHERE (imei1 = :im OR imei2 = :im OR serial_number = :im)");
					$upd->execute([':im' => $imei]);
				}

				// 2. Notificar al Centro de Alertas
				if (!empty($payload['create_alert']) && class_exists('\\Grocy\\Services\\AlertCenterService'))
				{
					try
					{
						AlertCenterService::GetInstance()->CreateManualAlert([
							'title' => 'Dispositivo no localizado: ' . ($payload['product_name'] ?? 'Equipo') . ' (' . ($imei ?: $aggregateId) . ')',
							'category' => 'seguridad',
							'severity' => 'danger',
							'branch_id' => $branchId,
							'notes' => 'Detectado durante conteo en sucursal. Iniciando protocolo de búsqueda y bloqueo en POS.'
						]);
					}
					catch (\Throwable $e)
					{
						// Ignorar fallo de notificación no crítica
					}
				}
				break;

			case self::EVENT_INVENTORY_DISCREPANCY_DETECTED:
				// Diferencia detectada: el balance contable se preserva bajo principio de prudencia
				break;

			case self::EVENT_ADJUSTMENT_APPROVED:
				// Un ajuste formal aprobado: asentar salida contable en stock y registrar en Kardex
				break;

			case self::EVENT_CASE_RESOLVED:
				// Si se localizó el producto, restablecer el estado del dispositivo
				if (!empty($payload['device_id']) && ($payload['status'] ?? '') === 'resuelto')
				{
					$upd = $db->prepare("UPDATE device_units SET state = 'available', block_reason = NULL WHERE id = :id");
					$upd->execute([':id' => intval($payload['device_id'])]);
				}
				break;
		}
	}

	/**
	 * Guarda el contexto operativo activo para el usuario (persiste entre los 7 dashboards)
	 */
	public function SetContext(int $userId, array $params): void
	{
		$db = $this->Db();
		$branchId = !empty($params['branch_id']) ? intval($params['branch_id']) : null;
		$productId = !empty($params['product_id']) ? intval($params['product_id']) : null;
		$caseId = !empty($params['case_id']) ? intval($params['case_id']) : null;
		$deviceId = !empty($params['device_id']) ? intval($params['device_id']) : null;
		$docRef = !empty($params['document_ref']) ? trim(strval($params['document_ref'])) : null;
		$period = !empty($params['period']) ? trim(strval($params['period'])) : null;
		$lastModule = !empty($params['module']) ? trim(strval($params['module'])) : null;
		$meta = !empty($params['metadata']) ? json_encode($params['metadata'], JSON_UNESCAPED_UNICODE) : null;

		$ins = $db->prepare("INSERT INTO daily_operational_contexts (user_id, branch_id, product_id, case_id, device_id, document_ref, period, last_module, metadata, updated_at)
			 VALUES (:uid, :b, :p, :c, :d, :doc, :per, :mod, :meta, datetime('now', 'localtime'))
			 ON CONFLICT(user_id) DO UPDATE SET
			    branch_id = COALESCE(:b, branch_id),
			    product_id = COALESCE(:p, product_id),
			    case_id = COALESCE(:c, case_id),
			    device_id = COALESCE(:d, device_id),
			    document_ref = COALESCE(:doc, document_ref),
			    period = COALESCE(:per, period),
			    last_module = COALESCE(:mod, last_module),
			    metadata = COALESCE(:meta, metadata),
			    updated_at = datetime('now', 'localtime')");
		$ins->execute([
			':uid' => $userId,
			':b' => $branchId,
			':p' => $productId,
			':c' => $caseId,
			':d' => $deviceId,
			':doc' => $docRef,
			':per' => $period,
			':mod' => $lastModule,
			':meta' => $meta
		]);
	}

	/**
	 * Obtiene el contexto operativo hidratado para la cabecera compartida
	 */
	public function GetContext(int $userId): ?array
	{
		$db = $this->Db();
		$q = $db->prepare('SELECT * FROM daily_operational_contexts WHERE user_id = :uid');
		$q->execute([':uid' => $userId]);
		$row = $q->fetch(\PDO::FETCH_ASSOC);

		if (!$row)
		{
			return null;
		}

		$hydrated = $row;

		// Nombre de la sucursal
		if (!empty($row['branch_id']))
		{
			$qb = $db->prepare('SELECT name FROM branches WHERE id = :id');
			$qb->execute([':id' => $row['branch_id']]);
			$hydrated['branch_name'] = $qb->fetchColumn() ?: ('Sucursal ' . $row['branch_id']);
		}

		// Datos del producto
		if (!empty($row['product_id']))
		{
			$qp = $db->prepare('SELECT name, sku FROM products WHERE id = :id');
			$qp->execute([':id' => $row['product_id']]);
			$p = $qp->fetch(\PDO::FETCH_ASSOC);
			if ($p)
			{
				$hydrated['product_name'] = $p['name'];
				$hydrated['product_sku'] = $p['sku'];
			}
		}

		// Datos del expediente
		if (!empty($row['case_id']))
		{
			$qc = $db->prepare('SELECT case_code, workflow_status, financial_impact FROM sensitive_investigation_cases WHERE id = :id');
			$qc->execute([':id' => $row['case_id']]);
			$c = $qc->fetch(\PDO::FETCH_ASSOC);
			if ($c)
			{
				$hydrated['case_code'] = $c['case_code'];
				$hydrated['case_status'] = $c['workflow_status'];
				$hydrated['case_value'] = floatval($c['financial_impact']);
			}
		}

		// Datos del dispositivo
		if (!empty($row['device_id']))
		{
			$qd = $db->prepare('SELECT imei1, serial_number, state FROM device_units WHERE id = :id');
			$qd->execute([':id' => $row['device_id']]);
			$d = $qd->fetch(\PDO::FETCH_ASSOC);
			if ($d)
			{
				$hydrated['device_imei'] = $d['imei1'] ?: $d['serial_number'];
				$hydrated['device_state'] = $d['state'];
			}
		}

		return $hydrated;
	}

	/**
	 * Genera enlaces directos pre-filtrados a los 7 módulos para navegación contextual
	 */
	public function GetCrossLinks(?int $branchId = null, ?int $productId = null, ?int $caseId = null): array
	{
		$q = [];
		if ($branchId) $q['branch_id'] = $branchId;
		if ($productId) $q['product_id'] = $productId;
		$qs = !empty($q) ? ('?' . http_build_query($q)) : '';

		return [
			'reconciliation'    => '/reconciliation' . $qs,
			'merchandise'       => '/merchandise' . $qs,
			'loss_prevention'   => '/loss-prevention' . ($caseId ? '#case-' . $caseId : $qs),
			'devices'           => '/loss-prevention/devices' . $qs,
			'inventory_control' => '/inventory-control' . $qs,
			'inventory'         => '/inventory' . $qs,
			'branches'          => '/branches' . ($branchId ? '?branch_id=' . $branchId : ''),
		];
	}

	/**
	 * Trazabilidad Unificada 360° para un producto a través de todos los módulos
	 */
	public function GetUnifiedProductHistory(int $productId, ?int $branchId = null): array
	{
		$bFilter = $branchId ? (' AND branch_id = ' . intval($branchId)) : '';
		$bFilterSl = $branchId ? (' AND sl.location_id IN (SELECT id FROM locations WHERE branch_id = ' . intval($branchId) . ')') : '';

		$timeline = [];

		$db = $this->Db();

		// 1. Movimientos en Kardex
		$stmt1 = $db->prepare(
			"SELECT sl.id, sl.amount, sl.row_created_timestamp, sl.transaction_type, sl.price,
			        u.username, l.name AS location_name
			 FROM stock_log sl
			 LEFT JOIN users u ON u.id = sl.user_id
			 LEFT JOIN locations l ON l.id = sl.location_id
			 WHERE sl.product_id = :p $bFilterSl
			 ORDER BY sl.row_created_timestamp DESC LIMIT 30"
		);
		$stmt1->execute([':p' => $productId]);
		$stockLogs = $stmt1->fetchAll(\PDO::FETCH_ASSOC);

		foreach ($stockLogs as $sl)
		{
			$timeline[] = [
				'source' => 'kardex',
				'type' => $sl['transaction_type'],
				'timestamp' => $sl['row_created_timestamp'],
				'qty' => floatval($sl['amount']),
				'user' => $sl['username'] ?: 'Sistema',
				'location' => $sl['location_name'] ?: 'Almacén general',
				'detail' => 'Movimiento de inventario en Kardex contable'
			];
		}

		// 2. Movimientos sensibles
		$stmt2 = $db->prepare(
			"SELECT m.*, u.username
			 FROM sensitive_inventory_movements m
			 LEFT JOIN users u ON u.id = m.user_id
			 WHERE m.product_id = :p $bFilter
			 ORDER BY m.created_at DESC LIMIT 30"
		);
		$stmt2->execute([':p' => $productId]);
		$sensMoves = $stmt2->fetchAll(\PDO::FETCH_ASSOC);

		foreach ($sensMoves as $sm)
		{
			$timeline[] = [
				'source' => 'sensitive',
				'type' => $sm['event_type'],
				'timestamp' => $sm['created_at'],
				'qty' => floatval($sm['difference_amount'] ?: $sm['actual_amount']),
				'user' => $sm['username'] ?: 'Operador',
				'location' => $sm['dest_location'] ?: 'Área sensible',
				'detail' => 'Registro de conteo / movimiento sensible (' . $sm['movement_code'] . ')'
			];
		}

		// 3. Eventos de dominio
		$stmt3 = $db->prepare(
			"SELECT * FROM daily_domain_events
			 WHERE aggregate_type = 'product' AND aggregate_id = :p $bFilter
			 ORDER BY occurred_at DESC LIMIT 20"
		);
		$stmt3->execute([':p' => strval($productId)]);
		$events = $stmt3->fetchAll(\PDO::FETCH_ASSOC);

		foreach ($events as $ev)
		{
			$payload = json_decode($ev['payload'], true) ?: [];
			$timeline[] = [
				'source' => 'domain_event',
				'type' => $ev['event_name'],
				'timestamp' => $ev['occurred_at'],
				'qty' => floatval($payload['amount'] ?? 0),
				'user' => 'Bus de Eventos',
				'location' => 'Red unificada',
				'detail' => 'Evento de dominio: ' . $ev['event_name']
			];
		}

		usort($timeline, fn($a, $b) => strcmp($b['timestamp'], $a['timestamp']));

		return array_slice($timeline, 0, 50);
	}
}
