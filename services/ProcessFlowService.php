<?php

namespace Grocy\Services;

use Grocy\Helpers\DairyAccess;

/**
 * DAILY Process Flow — expediente unificado de cualquier documento operativo.
 *
 * El progreso NO se guarda: se deriva de las columnas reales del documento (estado, fechas y responsables de cada paso).
 * Un paso posterior hecho sin el anterior aparece «omitido», nunca «completado». Los flujos manuales del motor
 * WorkflowService ligados al mismo documento se anexan sin duplicar datos.
 */
class ProcessFlowService extends BaseService
{
	/** tipo => [nombre, ruta del módulo dueño (permisos), etiqueta del módulo] */
	const TYPES = [
		'procurement_order' => ['Orden de compra', '/suppliers', 'Proveedores & Compras'],
		'customer_order' => ['Pedido de cliente', '/orders', 'Pedidos de Clientes'],
		'waste_record' => ['Registro de merma', '/waste/approvals', 'Aprobaciones de Merma'],
		'stock_transfer' => ['Traslado entre tiendas', '/transfers', 'Traslados entre Tiendas'],
		'customer_return' => ['Devolución de cliente', '/returns', 'Devoluciones e Incidencias'],
		'alert' => ['Alerta operativa', '/alerts', 'Centro de Alertas'],
		'inventory_adjustment' => ['Ajuste de inventario', '/inventory-control', 'Control de Inventario'],
		'quarantine' => ['Cuarentena / bloqueo', '/waste?fefo_estado=cuarentena#tab-fefo', 'Calidad & Vencimientos'],
		'count_session' => ['Conteo físico', '/inventory', 'Conteos Físicos'],
		'store_task' => ['Tarea de tienda', '/workforce', 'Tareas y Roles'],
	];

	const STATE_LABEL = ['done' => 'Completado', 'current' => 'En proceso', 'blocked' => 'Bloqueado', 'pending' => 'Pendiente',
		'skipped' => 'Omitido', 'failed' => 'Interrumpido', 'cancelled' => 'No ejecutado', 'na' => 'No aplica'];

	private array $UserNames = [];

	private function Db(): \PDO
	{
		return DatabaseService::GetInstance()->GetDbConnectionRaw();
	}

	private function Row(string $sql, array $p = []): ?array
	{
		$st = $this->Db()->prepare($sql);
		$st->execute($p);
		return $st->fetch(\PDO::FETCH_ASSOC) ?: null;
	}

	private function Rows(string $sql, array $p = []): array
	{
		$st = $this->Db()->prepare($sql);
		$st->execute($p);
		return $st->fetchAll(\PDO::FETCH_ASSOC);
	}

	private function UserName($id): ?string
	{
		if ($id === null || $id === '')
		{
			return null;
		}
		$id = intval($id);
		if (!array_key_exists($id, $this->UserNames))
		{
			$this->UserNames[$id] = $this->Row('SELECT display_name FROM users_dto WHERE id = :id', [':id' => $id])['display_name'] ?? ('Usuario #' . $id);
		}
		return $this->UserNames[$id];
	}

	public function Types(): array
	{
		$out = [];
		foreach (self::TYPES as $code => [$name, $route, $module])
		{
			if (DairyAccess::CanOpen($route))
			{
				$out[] = ['code' => $code, 'name' => $name, 'module' => $module, 'href' => $route];
			}
		}
		return $out;
	}

	/** Expediente completo de un documento. Exige permiso del módulo dueño y acceso a la sucursal. */
	public function Get(string $type, int $id): array
	{
		if (!isset(self::TYPES[$type]))
		{
			throw new \Exception('Tipo de proceso desconocido: ' . $type);
		}
		if (!DairyAccess::CanOpen(self::TYPES[$type][1]))
		{
			throw new \Exception('No tiene permiso para consultar ' . self::TYPES[$type][2]);
		}
		$flow = $this->{'Flow' . str_replace('_', '', ucwords($type, '_'))}($id);
		if ($flow['branch_id'] !== null && !DairyAccess::CanSeeBranch(intval($flow['branch_id'])))
		{
			throw new \Exception('No tiene acceso a la sucursal de este expediente');
		}
		$flow['workflows'] = array_map(fn($w) => [
			'id' => $w['id'], 'name' => $w['definition_name'], 'status' => $w['status_label'], 'progress_pct' => $w['progress_pct'], 'current_step' => $w['current_step'],
		], WorkflowService::GetInstance()->FindBySource($type, (string) $id));
		return $flow;
	}

	/** Bandeja: documentos abiertos por tipo con su paso actual, responsable y vencimiento (más urgentes primero) */
	public function Inbox(array $f = [], int $perType = 20): array
	{
		$active = [
			'procurement_order' => "SELECT id FROM procurement_orders WHERE status IN ('draft', 'approved', 'sent', 'partially_received') ORDER BY IFNULL(expected_delivery_date, '9999'), id DESC",
			'customer_order' => "SELECT id FROM customer_orders WHERE status NOT IN ('cancelled') AND (status <> 'dispatched' OR conciliated_at IS NULL) ORDER BY IFNULL(requested_date, '9999'), id DESC",
			'waste_record' => "SELECT id FROM waste_records WHERE status = 'pending_approval' OR (closed_timestamp IS NULL AND workflow_status NOT IN ('closed')) ORDER BY (status = 'pending_approval') DESC, total_cost DESC",
			'stock_transfer' => "SELECT id FROM stock_transfers WHERE status IN ('approved', 'in_transit') ORDER BY sent_at",
			'customer_return' => "SELECT id FROM customer_returns_exchanges WHERE status <> 'cerrada' ORDER BY created_at",
			'alert' => "SELECT id FROM dairy_alerts WHERE status NOT IN ('closed', 'dismissed') ORDER BY IFNULL(priority_score, 0) DESC, IFNULL(due_at, '9999')",
			'inventory_adjustment' => "SELECT id FROM inventory_adjustments WHERE status = 'pending_approval' OR (status = 'approved' AND IFNULL(inventory_applied, 0) = 0) ORDER BY created_timestamp",
			'quarantine' => "SELECT id FROM quarantine_items WHERE status = 'in_quarantine' ORDER BY entered_timestamp",
			'count_session' => "SELECT id FROM daily_physical_count_sessions WHERE status NOT IN ('reconciled', 'cancelled') ORDER BY created_at",
			'store_task' => "SELECT id FROM workforce_store_tasks WHERE status IN ('pending', 'in_progress', 'blocked') ORDER BY IFNULL(due_time, '9999')",
		];
		$out = [];
		foreach ($active as $type => $sql)
		{
			if (!empty($f['type']) && $f['type'] !== $type || !DairyAccess::CanOpen(self::TYPES[$type][1]))
			{
				continue;
			}
			$items = [];
			$total = 0;
			foreach ($this->Rows($sql) as $r)
			{
				try
				{
					$flow = $this->Get($type, intval($r['id']));
				}
				catch (\Throwable $e)
				{
					continue; // sucursal no visible
				}
				if (isset($f['branch_id']) && $f['branch_id'] !== '' && intval($flow['branch_id']) !== intval($f['branch_id']))
				{
					continue;
				}
				$total++;
				if (count($items) < $perType)
				{
					unset($flow['history'], $flow['steps'], $flow['evidence']);
					$items[] = $flow;
				}
			}
			$out[] = ['type' => $type, 'name' => self::TYPES[$type][0], 'module' => self::TYPES[$type][2], 'href' => self::TYPES[$type][1], 'total' => $total,
				'overdue' => count(array_filter($items, fn($i) => $i['overdue'])), 'unassigned' => count(array_filter($items, fn($i) => $i['owner'] === null)), 'items' => $items];
		}
		return $out;
	}

	/** Reglas del Centro de Alertas que señalan una operación detenida o inconsistente (no un riesgo comercial) */
	const EXCEPTION_RULES = ['expired_lot_unblocked', 'waste_pending_deducted', 'transfer_without_movement', 'pos_unreconciled', 'negative_stock', 'cash_shift_stale',
		'waste_approval_stale', 'order_unconciliated', 'po_not_sent', 'return_no_destination', 'tasks_unassigned_overdue', 'transfer_pending', 'quarantine_stale', 'po_late', 'dispatch_variance'];

	/**
	 * Centro de Excepciones: operaciones bloqueadas o inconsistentes. Lee las alertas activas de las reglas de integridad
	 * (misma fuente que el Centro de Alertas, sin duplicar registros), agrupadas por regla, con responsable y vencimiento.
	 */
	public function Exceptions(?int $branchId = null): array
	{
		if (!DairyAccess::CanOpen('/alerts'))
		{
			throw new \Exception('No tiene permiso para consultar el Centro de Alertas');
		}
		$in = implode(',', array_map(fn($c) => $this->Db()->quote($c), self::EXCEPTION_RULES));
		$act = implode(',', array_map(fn($c) => $this->Db()->quote($c), AlertCenterService::ACTIVE));
		$w = ["a.rule_code IN ($in)", "a.status IN ($act)"];
		$p = [];
		if ($branchId)
		{
			$w[] = 'a.branch_id = :b';
			$p[':b'] = $branchId;
		}
		$allowed = DairyAccess::AllowedBranches();
		if ($allowed !== null)
		{
			$w[] = 'a.branch_id IN (' . implode(',', array_map('intval', $allowed ?: [0])) . ')';
		}
		$rows = $this->Rows('SELECT a.id, a.rule_code, a.branch_id, a.severity, a.title, a.detail, a.value, a.href, a.status, a.owner_user_id, a.due_at, a.created_at, r.name AS rule_name, r.recommended_action
			FROM dairy_alerts a LEFT JOIN dairy_alert_rules r ON r.code = a.rule_code WHERE ' . implode(' AND ', $w) . "
			ORDER BY CASE a.severity WHEN 'danger' THEN 0 WHEN 'warning' THEN 1 ELSE 2 END, IFNULL(a.due_at, '9999')", $p);
		$now = date('Y-m-d H:i:s');
		$groups = [];
		foreach ($rows as $r)
		{
			$g = &$groups[$r['rule_code']];
			$g ??= ['rule_code' => $r['rule_code'], 'name' => $r['rule_name'] ?? $r['rule_code'], 'severity' => $r['severity'], 'action' => $r['recommended_action'],
				'count' => 0, 'value' => 0.0, 'overdue' => 0, 'unassigned' => 0, 'items' => []];
			$g['count']++;
			$g['value'] += floatval($r['value']);
			$g['overdue'] += $r['due_at'] && $r['due_at'] < $now ? 1 : 0;
			$g['unassigned'] += $r['owner_user_id'] ? 0 : 1;
			if (count($g['items']) < 10)
			{
				$g['items'][] = ['alert_id' => intval($r['id']), 'title' => $r['title'], 'detail' => $r['detail'], 'href' => $r['href'], 'status' => AlertCenterService::STATUS[$r['status']] ?? $r['status'],
					'owner' => $this->UserName($r['owner_user_id']), 'due_at' => $r['due_at'], 'overdue' => $r['due_at'] && $r['due_at'] < $now];
			}
			unset($g);
		}
		$groups = array_values($groups);
		return ['total' => array_sum(array_column($groups, 'count')), 'critical' => array_sum(array_map(fn($g) => $g['severity'] === 'danger' ? $g['count'] : 0, $groups)),
			'value' => round(array_sum(array_column($groups, 'value')), 2), 'groups' => $groups];
	}

	// ── Construcción común ──────────────────────────────────────────────────────

	/** Paso: $at = fecha (o true) si se ejecutó; null si no. $na = el paso no aplica a este documento. */
	private function S(string $code, string $label, $at, $by = null, bool $na = false, ?string $note = null): array
	{
		return ['code' => $code, 'label' => $label, 'at' => $at === true ? null : $at, 'done' => !$na && !empty($at), 'na' => $na, 'by' => $this->UserName($by), 'note' => $note];
	}

	/**
	 * $base: id, code, title, branch_id, status, status_label, created_at, due_at, priority, owner_id, responsible_role, related, history, evidence, next_module
	 * $terminal: motivo si el documento terminó sin completar (anulado/rechazado); $blocked: motivo si el paso actual está detenido.
	 */
	private function Build(string $type, array $base, array $steps, ?string $terminal = null, ?string $blocked = null): array
	{
		$last = -1;
		foreach ($steps as $i => $s)
		{
			if ($s['done'])
			{
				$last = $i;
			}
		}
		$current = null;
		$next = null;
		foreach ($steps as $i => &$s)
		{
			if ($s['na'])
			{
				$s['state'] = 'na';
			}
			elseif ($s['done'])
			{
				$s['state'] = 'done';
			}
			elseif ($i < $last)
			{
				$s['state'] = 'skipped';
			}
			elseif ($current === null)
			{
				$s['state'] = $terminal !== null ? 'failed' : ($blocked !== null ? 'blocked' : 'current');
				$s['note'] = $s['note'] ?? ($terminal ?? $blocked);
				$current = $s;
			}
			else
			{
				$s['state'] = $terminal !== null ? 'cancelled' : 'pending';
				if ($next === null && $terminal === null)
				{
					$next = $s;
				}
			}
			$s['state_label'] = self::STATE_LABEL[$s['state']];
		}
		unset($s);
		$applicable = array_filter($steps, fn($s) => !$s['na']);
		$done = count(array_filter($applicable, fn($s) => $s['done']));
		$closed = $terminal !== null || $current === null;
		$due = $base['due_at'] ?? null;
		[$name, $route, $module] = self::TYPES[$type];
		return [
			'type' => $type, 'type_name' => $name, 'id' => intval($base['id']), 'code' => $base['code'], 'title' => $base['title'],
			'branch_id' => isset($base['branch_id']) && $base['branch_id'] !== null ? intval($base['branch_id']) : null,
			'status' => $base['status'], 'status_label' => $base['status_label'] ?? $base['status'],
			'outcome' => $terminal !== null ? 'interrupted' : ($closed ? 'closed' : ($blocked !== null ? 'blocked' : 'active')),
			'progress_pct' => $applicable ? intval(round($done / count($applicable) * 100)) : 0,
			'steps_done' => $done, 'steps_total' => count($applicable),
			'current_step' => $current['label'] ?? null, 'next_step' => $next['label'] ?? null,
			'owner' => !empty($base['owner_id']) ? ['id' => intval($base['owner_id']), 'name' => $this->UserName($base['owner_id'])] : null,
			'responsible_role' => $base['responsible_role'] ?? null,
			'created_at' => $base['created_at'] ?? null, 'due_at' => $due, 'priority' => $base['priority'] ?? 'normal',
			'overdue' => !$closed && $due !== null && $due !== '' && strtotime($due) < time(),
			'module' => $module, 'href' => $base['href'] ?? $route, 'next_module' => $base['next_module'] ?? null,
			'steps' => $steps,
			'evidence' => array_values(array_filter($base['evidence'] ?? [])),
			'history' => $this->SortHistory($base['history'] ?? [], $steps),
			'related' => array_values(array_filter($base['related'] ?? [])),
		];
	}

	/** Historial: eventos propios del documento; si no hay bitácora, los pasos fechados (hechos reales, no inventados) */
	private function SortHistory(array $history, array $steps): array
	{
		if (!$history)
		{
			foreach ($steps as $s)
			{
				if ($s['done'] && $s['at'])
				{
					$history[] = ['at' => $s['at'], 'user' => $s['by'], 'action' => $s['label'], 'note' => $s['note']];
				}
			}
		}
		usort($history, fn($a, $b) => strcmp((string) $a['at'], (string) $b['at']));
		return $history;
	}

	private function Rel(string $type, $id, string $label, ?string $href = null): ?array
	{
		return $id ? ['type' => $type, 'id' => is_numeric($id) ? intval($id) : $id, 'label' => $label, 'href' => $href ?? (self::TYPES[$type][1] ?? null)] : null;
	}

	private function Must(?array $row, string $what): array
	{
		if (!$row)
		{
			throw new \Exception($what . ' no encontrado');
		}
		return $row;
	}

	// ── Flujos por tipo ─────────────────────────────────────────────────────────

	private function FlowProcurementOrder(int $id): array
	{
		$o = $this->Must($this->Row('SELECT o.*, s.name AS supplier FROM procurement_orders o LEFT JOIN shopping_locations s ON s.id = o.supplier_id WHERE o.id = :id', [':id' => $id]), 'Orden de compra');
		$receipts = $this->Rows('SELECT * FROM procurement_receipts WHERE order_id = :id ORDER BY id', [':id' => $id]);
		$invoices = $this->Rows('SELECT * FROM supplier_invoices WHERE order_id = :id ORDER BY id', [':id' => $id]);
		$rec = $receipts[0] ?? null;
		$fullyReceived = in_array($o['status'], ['received', 'closed'], true);
		$okInv = array_values(array_filter($invoices, fn($i) => in_array($i['status'], ['matched', 'approved_with_difference'], true)));
		$blocked = null;
		if ($invoices && !$okInv && in_array($invoices[count($invoices) - 1]['status'], ['discrepancy'], true))
		{
			$blocked = 'Factura con diferencias: requiere decisión';
		}
		$steps = [
			$this->S('created', 'Propuesta registrada', $o['created_at']),
			$this->S('approved', 'Aprobación de compra', $o['approved_at'], $o['approved_by_user_id']),
			$this->S('sent', 'Enviada al proveedor', $o['sent_at'], $o['sent_by_user_id']),
			$this->S('received', 'Recepción en tienda', $fullyReceived && $rec ? $rec['received_at'] : null, $rec['received_by_user_id'] ?? null, false,
				$o['status'] === 'partially_received' ? 'Recepción parcial: ' . count($receipts) . ' entrega(s)' : null),
			$this->S('stock', 'Entrada a stock y Kardex', $rec && $rec['stock_transaction_id'] && $fullyReceived ? $rec['received_at'] : null),
			$this->S('invoice', 'Conciliación con factura', $okInv[0]['decided_timestamp'] ?? ($okInv[0]['created_timestamp'] ?? null), $okInv[0]['decided_by_user_id'] ?? null),
			$this->S('closed', 'Cierre de la orden', $o['closed_at'], $o['closed_by_user_id']),
		];
		$terminal = in_array($o['status'], ['rejected', 'cancelled'], true) ? ($o['status'] === 'rejected' ? 'Rechazada' : 'Anulada') . ($o['close_reason'] ? ': ' . $o['close_reason'] : '') : null;
		$related = [];
		foreach ($receipts as $r)
		{
			$related[] = $this->Rel('receipt', $r['id'], 'Recepción ' . $r['receipt_code'] . ' · $' . number_format(floatval($r['total_accepted_cost']), 2), '/merchandise');
			$related[] = $this->Rel('stock_transaction', $r['stock_transaction_id'], 'Movimiento de stock ' . $r['stock_transaction_id'], '/kardex');
		}
		foreach ($invoices as $inv)
		{
			$related[] = $this->Rel('supplier_invoice', $inv['id'], 'Factura ' . $inv['invoice_number'] . ' (' . $inv['status'] . ')', '/suppliers');
		}
		$role = ['draft' => 'Jefe de compras (aprobar)', 'approved' => 'Compras (enviar al proveedor)', 'sent' => 'Recepción de tienda', 'partially_received' => 'Recepción de tienda',
			'received' => 'Cuentas por pagar (conciliar factura)'][$o['status']] ?? null;
		return $this->Build('procurement_order', [
			'id' => $id, 'code' => $o['order_code'], 'title' => 'OC ' . $o['order_code'] . ' · ' . ($o['supplier'] ?? 'Proveedor'), 'branch_id' => $o['branch_id'],
			'status' => $o['status'], 'created_at' => $o['created_at'], 'due_at' => $o['expected_delivery_date'], 'owner_id' => null, 'responsible_role' => $role,
			'priority' => 'normal', 'related' => $related, 'next_module' => 'Control de Mercancías → Stock → Kardex',
		], $steps, $terminal, $blocked);
	}

	private function FlowCustomerOrder(int $id): array
	{
		$o = $this->Must($this->Row('SELECT * FROM customer_orders WHERE id = :id', [':id' => $id]), 'Pedido');
		$pickup = $o['delivery_type'] === 'pickup';
		$steps = [
			$this->S('created', 'Pedido creado', $o['created_at'], $o['created_by_user_id']),
			$this->S('confirmed', 'Confirmado y reservado', $o['confirmed_at'], $o['confirmed_by_user_id']),
			$this->S('picked', 'Picking por escáner', $o['picked_at'], $o['picked_by_user_id']),
			$this->S('packed', 'Empacado', $o['packed_at'], $o['packed_by_user_id']),
			$this->S('dispatched', $pickup ? 'Entregado en tienda' : 'Despachado', $o['dispatched_at'], $o['dispatched_by_user_id']),
			$this->S('delivered', $pickup ? 'Retiro verificado' : 'Entrega confirmada', $pickup ? $o['pickup_verified_at'] : ($o['delivery_status'] === 'delivered' ? ($o['delivery_time'] ?: $o['dispatched_at']) : null), $pickup ? $o['pickup_verified_by'] : null),
			$this->S('conciliated', 'Conciliación y cierre', $o['conciliated_at'], $o['conciliated_by_user_id']),
		];
		$returns = $this->Rows('SELECT id, return_code, status FROM customer_returns_exchanges WHERE order_id = :id', [':id' => $id]);
		$var = intval($this->Row('SELECT COUNT(*) n FROM dispatch_variances WHERE order_id = :id', [':id' => $id])['n']);
		$related = [$this->Rel('sale', $o['sale_id'], 'Venta #' . $o['sale_id'] . ' (descarga de inventario)', '/sales')];
		foreach ($returns as $r)
		{
			$related[] = $this->Rel('customer_return', $r['id'], 'Devolución ' . $r['return_code'] . ' (' . $r['status'] . ')');
		}
		// Responsable nominal solo mientras se prepara; despachado sin conciliar pasa al rol de supervisión
		$owner = in_array($o['status'], ['dispatched', 'cancelled'], true) ? null : ($o['packed_by_user_id'] ?? $o['picked_by_user_id'] ?? $o['confirmed_by_user_id'] ?? $o['created_by_user_id']);
		$role = ['draft' => 'Atención al cliente (confirmar)', 'confirmed' => 'Preparador (picking)', 'picking' => 'Preparador (picking)', 'picked' => 'Empaque', 'packed' => 'Despacho', 'dispatching' => 'Despacho'][$o['status']] ?? ($o['conciliated_at'] ? null : 'Supervisor de despacho (conciliar)');
		return $this->Build('customer_order', [
			'id' => $id, 'code' => $o['order_code'], 'title' => 'Pedido ' . $o['order_code'], 'branch_id' => $o['branch_id'], 'status' => $o['status'],
			'status_label' => CustomerOrderService::STATUS[$o['status']] ?? $o['status'], 'created_at' => $o['created_at'], 'due_at' => $o['requested_date'],
			'owner_id' => $owner, 'responsible_role' => $role, 'related' => $related, 'href' => $o['status'] === 'dispatched' ? '/dispatch' : '/orders',
			'evidence' => [$o['delivery_evidence_url'] ? ['kind' => 'photo', 'reference' => $o['delivery_evidence_url'], 'description' => 'Evidencia de entrega'] : null,
				$var ? ['kind' => 'note', 'reference' => $var . ' diferencia(s) de despacho registradas', 'description' => null] : null],
			'next_module' => 'Venta → Inventario → Rentabilidad → Panel Ejecutivo',
		], $steps, $o['status'] === 'cancelled' ? 'Anulado' . ($o['cancel_reason'] ? ': ' . $o['cancel_reason'] : '') : null);
	}

	private function FlowWasteRecord(int $id): array
	{
		$w = $this->Must($this->Row('SELECT w.*, r.name AS reason, r.requires_evidence, r.requires_approval, p.name AS product FROM waste_records w
			LEFT JOIN waste_reasons r ON r.id = w.waste_reason_id LEFT JOIN products p ON p.id = w.product_id WHERE w.id = :id', [':id' => $id]), 'Registro de merma');
		$decided = in_array($w['status'], ['approved', 'rejected'], true);
		$ws = (string) $w['workflow_status'];
		$order = ['detected' => 0, 'reviewing' => 1, 'investigating' => 1, 'action_assigned' => 2, 'executed' => 3, 'verified' => 4, 'approved' => 2, 'closed' => 5];
		$wsPos = $order[$ws] ?? 0;
		$steps = [
			$this->S('registered', 'Merma registrada', $w['created_timestamp'], $w['reported_by_user_id']),
			$this->S('evidence', 'Evidencia adjunta', $w['evidence_photo_path'] ? $w['created_timestamp'] : null, null, empty($w['requires_evidence']) && !$w['evidence_photo_path']),
			$this->S('approval', 'Aprobación', $decided ? ($w['approved_timestamp'] ?: true) : null, $w['approved_by_user_id'], empty($w['requires_approval']) && !$decided && $w['status'] !== 'pending_approval'),
			$this->S('inventory', 'Descuento de inventario', intval($w['inventory_deducted']) ? ($w['approved_timestamp'] ?: true) : null, null, $w['status'] === 'rejected'),
			$this->S('action', 'Causa raíz y acción correctiva', $wsPos >= 2 && ($w['root_cause_analysis'] || $w['corrective_action_plan'] || $wsPos >= 3) ? true : null, $w['investigator_user_id']),
			$this->S('verified', 'Verificación', $w['verified_by_user_id'] ? true : null, $w['verified_by_user_id']),
			$this->S('closed', 'Cierre del caso', $w['closed_timestamp']),
		];
		$history = array_map(fn($l) => ['at' => $l['created_timestamp'], 'user' => $this->UserName($l['user_id']), 'action' => $l['action_title'],
			'note' => trim(($l['previous_status'] ? $l['previous_status'] . ' → ' : '') . ($l['new_status'] ?? '') . ($l['action_details'] ? ' · ' . $l['action_details'] : ''))],
			$this->Rows('SELECT * FROM waste_action_logs WHERE waste_record_id = :id ORDER BY id', [':id' => $id]));
		$recovery = $this->Rows('SELECT id, status, credit_recovered_amount FROM supplier_returns WHERE case_number = :c', [':c' => (string) $w['case_number']]);
		$related = [
			$this->Rel('stock_transaction', $w['transaction_id'], 'Movimiento de stock ' . $w['transaction_id'], '/kardex'),
			$this->Rel('inventory_adjustment', $w['inventory_adjustment_id'], 'Ajuste de inventario #' . $w['inventory_adjustment_id']),
			$this->Rel('damage_incident', $w['damage_incident_id'], 'Incidente de avería #' . $w['damage_incident_id'], '/returns'),
		];
		foreach ($recovery as $r)
		{
			$related[] = $this->Rel('supplier_return', $r['id'], 'Reclamo a proveedor (' . $r['status'] . ') · recuperado $' . number_format(floatval($r['credit_recovered_amount']), 2), '/waste#tab-recuperacion');
		}
		$role = $w['status'] === 'pending_approval' ? 'Aprobador de merma (segregado de quien registra)' : ($w['closed_timestamp'] ? null : 'Investigador / jefe de área');
		return $this->Build('waste_record', [
			'id' => $id, 'code' => $w['case_number'] ?: ('MER-' . $id), 'title' => ($w['product'] ?? 'Producto') . ' · ' . ($w['reason'] ?? 'Merma') . ' · $' . number_format(floatval($w['total_cost']), 2),
			'branch_id' => $w['branch_id'], 'status' => $w['status'], 'status_label' => ['pending_approval' => 'Pendiente de aprobación', 'approved' => 'Aprobada', 'rejected' => 'Rechazada'][$w['status']] ?? $w['status'],
			'created_at' => $w['created_timestamp'], 'due_at' => $w['due_date'], 'owner_id' => $w['investigator_user_id'], 'responsible_role' => $role,
			'priority' => floatval($w['total_cost']) >= 500 ? 'high' : 'normal', 'history' => $history, 'related' => $related,
			'href' => $w['status'] === 'pending_approval' ? '/waste/approvals' : '/waste#tab-investigaciones',
			'evidence' => [$w['evidence_photo_path'] ? ['kind' => 'photo', 'reference' => $w['evidence_photo_path'], 'description' => 'Foto de la merma'] : null],
			'next_module' => 'Inventario → Kardex → Matriz de Causales → Recuperación',
		], $steps, $w['status'] === 'rejected' ? 'Rechazada' . ($w['resolution_notes'] ? ': ' . $w['resolution_notes'] : '') : null);
	}

	private function FlowStockTransfer(int $id): array
	{
		$t = $this->Must($this->Row('SELECT * FROM stock_transfers WHERE id = :id', [':id' => $id]), 'Traslado');
		$items = $this->Rows('SELECT * FROM stock_transfer_items WHERE transfer_id = :id', [':id' => $id]);
		$diff = array_filter($items, fn($i) => abs(floatval($i['difference_amount'])) > 0.0001);
		$unresolved = array_filter($diff, fn($i) => empty($i['waste_record_id']));
		$received = $t['status'] === 'received';
		$steps = [
			$this->S('requested', 'Solicitud', $t['sent_at'], $t['created_by_user_id']),
			$this->S('dispatched', 'Despacho desde origen', $t['send_transaction_id'] ? $t['sent_at'] : null, $t['created_by_user_id']),
			$this->S('received', 'Recepción en destino', $received ? $t['received_at'] : null, $received ? $t['received_by_user_id'] : null),
			$this->S('conciliated', 'Conciliación de diferencias', $received && !$unresolved ? $t['received_at'] : null, null, false, $diff ? count($diff) . ' línea(s) con diferencia · $' . number_format(floatval($t['total_difference_cost']), 2) : null),
		];
		$related = [$this->Rel('stock_transaction', $t['send_transaction_id'], 'Salida de origen ' . $t['send_transaction_id'], '/kardex'),
			$this->Rel('stock_transaction', $received ? $t['receive_transaction_id'] : null, 'Entrada en destino ' . $t['receive_transaction_id'], '/kardex')];
		foreach ($diff as $i)
		{
			$related[] = $this->Rel('waste_record', $i['waste_record_id'], 'Merma por diferencia #' . $i['waste_record_id']);
		}
		return $this->Build('stock_transfer', [
			'id' => $id, 'code' => $t['transfer_code'], 'title' => 'Traslado ' . $t['transfer_code'] . ' · sucursal ' . $t['from_branch_id'] . ' → ' . $t['to_branch_id'],
			'branch_id' => $t['to_branch_id'], 'status' => $t['status'], 'status_label' => ['in_transit' => 'En tránsito', 'received' => 'Recibido', 'cancelled' => 'Anulado', 'approved' => 'Aprobado'][$t['status']] ?? $t['status'],
			'created_at' => $t['sent_at'], 'due_at' => $t['status'] === 'in_transit' && $t['sent_at'] ? date('Y-m-d H:i:s', strtotime($t['sent_at']) + 72 * 3600) : null,
			'owner_id' => $received ? $t['received_by_user_id'] : null, 'responsible_role' => $received ? null : 'Recepción de la sucursal destino',
			'related' => $related, 'next_module' => 'Stock destino → Kardex → Conciliación',
		], $steps, $t['status'] === 'cancelled' ? 'Anulado' . ($t['receive_notes'] ? ': ' . $t['receive_notes'] : '') : null);
	}

	private function FlowCustomerReturn(int $id): array
	{
		$r = $this->Must($this->Row('SELECT * FROM customer_returns_exchanges WHERE id = :id', [':id' => $id]), 'Devolución');
		$seq = [
			'solicitud' => 'Solicitud',
			'recepcion' => 'Recepción física',
			'inspeccion' => 'Inspección',
			'clasificacion' => 'Clasificación',
			'disposicion' => 'Disposición autorizada',
			'conciliacion' => 'Conciliación',
		];
		$stMap = [
			'identificacion' => 'recepcion',
			'pending_inspection' => 'recepcion',
			'aprobada' => 'clasificacion',
			'conciliada' => 'conciliacion',
			'cerrada' => 'conciliacion',
		];
		$curStatus = $stMap[$r['status']] ?? $r['status'];
		$pos = array_search($curStatus, array_keys($seq), true);
		$at = [
			'solicitud' => $r['created_at'],
			'recepcion' => $r['received_at'] ?? null,
			'inspeccion' => $r['inspected_at'] ?? null,
			'disposicion' => $r['disposition_authorized_at'] ?? ($r['approved_at'] ?? null),
			'conciliacion' => $r['conciliated_at'] ?? null,
		];
		$by = [
			'recepcion' => $r['received_by_user_id'] ?? null,
			'inspeccion' => $r['inspected_by_user_id'] ?? null,
			'disposicion' => $r['disposition_authorized_by'] ?? ($r['approved_by_user_id'] ?? null),
			'conciliacion' => $r['conciliated_by_user_id'] ?? null,
		];
		$steps = [];
		$i = 0;
		foreach ($seq as $code => $label)
		{
			$done = $pos !== false && $i <= $pos;
			$steps[] = $this->S($code, $label, $done ? ($at[$code] ?? true) : null, $by[$code] ?? null);
			$i++;
		}
		$related = [$this->Rel('customer_order', $r['order_id'], 'Pedido de origen #' . $r['order_id']),
			$this->Rel('waste_record', $r['waste_record_id'], 'Merma generada #' . $r['waste_record_id']),
			$this->Rel('stock_booking', $r['stock_booking_id'], 'Reingreso a stock ' . ($r['stock_reentry_tx_id'] ?? $r['stock_booking_id']), '/kardex')];
		return $this->Build('customer_return', [
			'id' => $id, 'code' => $r['rma_number'] ?: $r['return_code'], 'title' => 'Devolución ' . ($r['rma_number'] ?: $r['return_code']) . ' · ' . $r['return_type'], 'branch_id' => $r['branch_id'],
			'status' => $curStatus, 'status_label' => $seq[$curStatus] ?? $curStatus, 'created_at' => $r['created_at'],
			'owner_id' => $r['inspected_by_user_id'], 'responsible_role' => $curStatus === 'conciliacion' ? null : 'Encargado de devoluciones logísticas',
			'evidence' => [$r['evidence_photo_url'] ? ['kind' => 'photo', 'reference' => $r['evidence_photo_url'], 'description' => 'Foto de inspección'] : null],
			'related' => $related, 'next_module' => 'Inventario, RTV o merma',
		], $steps);
	}

	private function FlowAlert(int $id): array
	{
		$a = $this->Must($this->Row('SELECT * FROM dairy_alerts WHERE id = :id', [':id' => $id]), 'Alerta');
		$closed = in_array($a['status'], ['closed', 'dismissed'], true);
		$events = $this->Rows('SELECT * FROM dairy_alert_events WHERE alert_id = :id ORDER BY id', [':id' => $id]);
		$firstEv = fn(array $acts) => current(array_filter($events, fn($e) => in_array($e['action'], $acts, true))) ?: null;
		$inv = $firstEv(['investigating', 'investigate', 'status_investigating', 'start']);
		$steps = [
			$this->S('detected', 'Detección', $a['created_at']),
			$this->S('assigned', 'Asignación de responsable', $a['owner_user_id'] ? ($a['first_response_at'] ?: true) : null, $a['owner_user_id']),
			$this->S('investigating', 'Investigación / acción', $inv['created_at'] ?? (in_array($a['status'], ['investigating', 'in_progress', 'pending_approval', 'waiting_approval'], true) || $a['resolved_at'] ? true : null), $inv['user_id'] ?? null),
			$this->S('resolved', 'Resolución', $a['resolved_at'], $a['resolved_by_user_id'], false, $a['resolution']),
			$this->S('verified', 'Verificación y aprobación', $a['approved_at'], $a['approved_by_user_id']),
			$this->S('closed', 'Cierre', $a['status'] === 'closed' ? ($a['approved_at'] ?: $a['resolved_at'] ?: true) : null),
		];
		$history = array_map(fn($e) => ['at' => $e['created_at'], 'user' => $this->UserName($e['user_id']), 'action' => $e['action'], 'note' => $e['note']], $events);
		$tasks = $this->Rows("SELECT id, title, status FROM workforce_store_tasks WHERE source_type = 'alert' AND source_id = :id", [':id' => $id]);
		$related = [$a['href'] ? ['type' => 'origin', 'id' => $a['rule_code'], 'label' => 'Resolver en el módulo de origen', 'href' => $a['href']] : null];
		foreach ($tasks as $t)
		{
			$related[] = $this->Rel('store_task', $t['id'], 'Tarea: ' . $t['title'] . ' (' . $t['status'] . ')');
		}
		return $this->Build('alert', [
			'id' => $id, 'code' => 'ALR-' . $id, 'title' => $a['title'], 'branch_id' => $a['branch_id'], 'status' => $a['status'],
			'status_label' => AlertCenterService::STATUS[$a['status']] ?? $a['status'], 'created_at' => $a['created_at'], 'due_at' => $a['due_at'],
			'priority' => $a['priority'] ?: $a['severity'], 'owner_id' => $a['owner_user_id'], 'responsible_role' => $a['owner_user_id'] ? null : 'Supervisor de alertas (asignar)',
			'history' => $history, 'related' => $related, 'next_module' => 'Módulo de origen · tarea de tienda',
		], $steps, $a['status'] === 'dismissed' ? 'Descartada' . ($a['resolution'] ? ': ' . $a['resolution'] : '') : null,
			in_array($a['status'], ['waiting_info'], true) ? 'Esperando información' : null);
	}

	private function FlowInventoryAdjustment(int $id): array
	{
		$a = $this->Must($this->Row('SELECT * FROM inventory_adjustments WHERE id = :id', [':id' => $id]), 'Ajuste');
		$steps = [
			$this->S('requested', 'Diferencia detectada y solicitada', $a['created_timestamp'], $a['requested_by_user_id']),
			$this->S('evidence', 'Evidencia', $a['evidence_photo_path'] ? $a['created_timestamp'] : null, null, !$a['evidence_photo_path']),
			$this->S('approval', 'Aprobación', in_array($a['status'], ['approved'], true) ? ($a['approved_timestamp'] ?: true) : null, $a['approved_by_user_id'], !intval($a['requires_manager_approval']) && $a['status'] !== 'pending_approval'),
			$this->S('applied', 'Ajuste aplicado a stock', intval($a['inventory_applied']) ? ($a['approved_timestamp'] ?: true) : null),
		];
		return $this->Build('inventory_adjustment', [
			'id' => $id, 'code' => $a['adjustment_number'], 'title' => 'Ajuste ' . $a['adjustment_number'] . ' · ' . $a['difference_amount'] . ' u · $' . number_format(floatval($a['total_cost_impact']), 2),
			'branch_id' => $a['branch_id'], 'status' => $a['status'], 'created_at' => $a['created_timestamp'], 'owner_id' => $a['approved_by_user_id'],
			'responsible_role' => $a['status'] === 'pending_approval' ? 'Gerente (aprobación de ajuste)' : null,
			'evidence' => [$a['evidence_photo_path'] ? ['kind' => 'photo', 'reference' => $a['evidence_photo_path'], 'description' => 'Evidencia del conteo'] : null],
			'related' => [$this->Rel('stock_transaction', $a['transaction_id'], 'Movimiento de ajuste ' . $a['transaction_id'], '/kardex')],
			'next_module' => 'Stock → Kardex',
		], $steps, $a['status'] === 'rejected' ? 'Rechazado' . ($a['resolution_notes'] ? ': ' . $a['resolution_notes'] : '') : null);
	}

	private function FlowQuarantine(int $id): array
	{
		$q = $this->Must($this->Row('SELECT q.*, p.name AS product FROM quarantine_items q LEFT JOIN products p ON p.id = q.product_id WHERE q.id = :id', [':id' => $id]), 'Cuarentena');
		$resolved = $q['status'] !== 'in_quarantine';
		$steps = [
			$this->S('detected', 'Detección', $q['entered_timestamp']),
			$this->S('blocked', 'Bloqueo de venta (cuarentena)', $q['entered_timestamp']),
			$this->S('decision', 'Decisión: liberar, devolver o mermar', $resolved ? ($q['resolved_timestamp'] ?: true) : null, $q['resolved_by'], false, $q['resolution_notes']),
			$this->S('closed', 'Cierre', $resolved ? $q['resolved_timestamp'] : null),
		];
		return $this->Build('quarantine', [
			'id' => $id, 'code' => 'CUA-' . $id, 'title' => ($q['product'] ?? 'Producto') . ' · ' . $q['amount'] . ' u · ' . $q['reason'], 'branch_id' => $q['branch_id'],
			'status' => $q['status'], 'created_at' => $q['entered_timestamp'], 'due_at' => $resolved ? null : date('Y-m-d H:i:s', strtotime($q['entered_timestamp']) + 7 * 86400),
			'owner_id' => $q['resolved_by'], 'responsible_role' => $resolved ? null : 'Calidad (inspección y decisión)',
			'related' => [$this->Rel('waste_record', $q['waste_record_id'], 'Merma #' . $q['waste_record_id'])], 'next_module' => 'Stock liberado o Merma',
		], $steps);
	}

	private function FlowCountSession(int $id): array
	{
		$c = $this->Must($this->Row('SELECT * FROM daily_physical_count_sessions WHERE id = :id', [':id' => $id]), 'Conteo');
		$seq = ['planned', 'in_progress', 'in_review', 'completed', 'reconciled'];
		$pos = array_search($c['status'], $seq, true);
		$pos = $pos === false ? -1 : $pos;
		$steps = [
			$this->S('planned', 'Planificación y asignación', $c['created_at']),
			$this->S('counting', 'Conteo', $pos >= 2 ? ($c['end_time'] ?: true) : null, null, false, $pos === 1 ? 'Conteo en curso desde ' . $c['start_time'] : null),
			$this->S('review', 'Revisión de diferencias', $pos >= 3 ? true : null, null, false, $c['differences_units'] !== null ? $c['differences_units'] . ' u · $' . number_format(floatval($c['differences_value']), 2) : null),
			$this->S('approved', 'Aprobación del supervisor', $pos >= 3 ? ($c['end_time'] ?: true) : null),
			$this->S('reconciled', 'Ajuste y cierre', $pos >= 4 ? true : null),
		];
		return $this->Build('count_session', [
			'id' => $id, 'code' => $c['code'], 'title' => 'Conteo ' . $c['code'] . ' · ' . ($c['branch_name'] ?? '') . ' · ' . $c['count_type'], 'branch_id' => $c['branch_id'],
			'status' => $c['status'], 'created_at' => $c['created_at'], 'responsible_role' => trim(($c['assigned_to'] ? 'Contador: ' . $c['assigned_to'] : '') . ($c['supervisor'] ? ' · Supervisor: ' . $c['supervisor'] : '')) ?: null,
			'next_module' => 'Ajuste de inventario → Kardex',
		], $steps, $c['status'] === 'cancelled' ? 'Anulado' : null);
	}

	private function FlowStoreTask(int $id): array
	{
		$t = $this->Must($this->Row('SELECT * FROM workforce_store_tasks WHERE id = :id', [':id' => $id]), 'Tarea');
		$owner = $t['assigned_user_id'] ?: null;
		$steps = [
			$this->S('created', 'Tarea creada', $t['created_timestamp'] ?: true), // la fila existe: la tarea se creó aunque la carga antigua no guardara la hora
			$this->S('assigned', 'Asignada', $owner || $t['assigned_employee_id'] ? true : null, $owner, false, $t['assigned_role'] ? 'Rol: ' . $t['assigned_role'] : null),
			$this->S('executing', 'Ejecución', in_array($t['status'], ['in_progress', 'completed'], true) ? true : null),
			$this->S('evidence', 'Evidencia', $t['evidence_photo_path'] ? ($t['completed_at'] ?: true) : null, null, !$t['evidence_photo_path']),
			$this->S('completed', 'Cierre', $t['status'] === 'completed' ? ($t['completed_at'] ?: true) : null, $t['completed_by_user_id']),
		];
		$srcMap = ['alert' => 'alert', 'waste' => 'waste_record'];
		return $this->Build('store_task', [
			'id' => $id, 'code' => 'TAR-' . $id, 'title' => $t['title'], 'branch_id' => $t['branch_id'], 'status' => $t['status'], 'created_at' => $t['created_timestamp'],
			'due_at' => $t['due_time'], 'owner_id' => $owner, 'responsible_role' => $owner ? null : ($t['assigned_role'] ?: 'Sin responsable: asignar'),
			'evidence' => [$t['evidence_photo_path'] ? ['kind' => 'photo', 'reference' => $t['evidence_photo_path'], 'description' => 'Evidencia'] : null],
			'related' => [isset($srcMap[(string) $t['source_type']]) ? $this->Rel($srcMap[$t['source_type']], $t['source_id'], 'Origen: ' . self::TYPES[$srcMap[$t['source_type']]][0] . ' #' . $t['source_id']) : null],
			'next_module' => 'Productividad operativa',
		], $steps, null, $t['status'] === 'blocked' ? 'Tarea bloqueada' : null);
	}
}
