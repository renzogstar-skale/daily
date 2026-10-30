<?php

namespace Grocy\Services;

/**
 * Documentos imprimibles con folio para cada movimiento DAIRY (equivalente a un documento de material SAP).
 * Folios: OC (orden de compra, PO-…), REC (recepción), TRA (traslado), MRM (merma), AJU (ajuste por conteo),
 * CUA (cuarentena), DEV (devolución / nota de crédito de proveedor), FAC (factura de proveedor conciliada). Los que no guardan folio propio lo derivan
 * de su id y año de creación, por lo que son únicos y estables.
 */
class DocumentService extends BaseService
{
	const TYPES = ['order', 'receipt', 'transfer', 'waste', 'adjustment', 'quarantine', 'supplier_return', 'invoice'];

	public static function Folio(string $prefix, int $id, ?string $date): string
	{
		return $prefix . '-' . substr($date ?: date('Y'), 0, 4) . '-' . str_pad((string) $id, 5, '0', STR_PAD_LEFT);
	}

	public function GetDocument(string $type, int $id): array
	{
		if (!in_array($type, self::TYPES))
		{
			throw new \Exception('Tipo de documento inválido');
		}
		$method = 'Doc' . str_replace('_', '', ucwords($type, '_'));
		$doc = $this->$method($id);
		$doc['steps'] = $this->ProcessSteps($type, $doc['state'] ?? 'done');
		return $doc;
	}

	private function DocOrder(int $id): array
	{
		$d = MrpService::GetInstance()->GetProcurementOrderDetail($id);
		$o = $d['order'];
		$rows = [];
		$total = 0.0;
		foreach ($d['items'] as $i)
		{
			$qty = floatval($i['approved_amount'] ?? $i['suggested_amount']);
			$rows[] = [$i['product_name'], $this->Qty($qty, $i['qu_name']), $this->Qty($i['received_amount'], $i['qu_name']), $this->Qty($i['rejected_amount'], $i['qu_name']), $this->Money($i['unit_price']), $this->Money($qty * $i['unit_price'])];
			$total += $qty * $i['unit_price'];
		}
		$refs = array_map(fn($r) => ['Recepción', $r['receipt_code'] . ' · ' . $r['received_at'], ['receipt', $r['id']]], $d['receipts']);
		return [
			'type_label' => 'Orden de compra',
			'folio' => $o['order_code'],
			'date' => $o['created_at'],
			'status' => $this->StatusLabel($o['status']),
			'state' => $o['status'],
			'parties' => [['Proveedor', $o['supplier_name']], ['Sucursal de entrega', $o['branch_name']], ['Entrega comprometida', $o['expected_delivery_date'] ?? '—']],
			'people' => [
				['Elaboró', $o['generated_by'] === 'mrp_engine' ? 'Motor MRP' : $o['generated_by'], $o['created_at']],
				['Aprobó', $this->UserName($o['approved_by_user_id']), $o['approved_at']],
				['Envió', $this->UserName($o['sent_by_user_id']), $o['sent_at']],
				['Cerró', $this->UserName($o['closed_by_user_id']), $o['closed_at']]
			],
			'columns' => ['Producto', 'Pedido', 'Recibido', 'Rechazado', 'Costo unit.', 'Importe'],
			'numeric_from' => 1,
			'rows' => $rows,
			'totals' => [['Total pedido', $this->Money($total)]],
			'refs' => $refs,
			'notes' => $o['close_reason'] ? 'Motivo de cierre/cancelación: ' . $o['close_reason'] : null
		];
	}

	private function DocInvoice(int $id): array
	{
		$i = $this->Fetch("
			SELECT i.*, o.order_code, IFNULL(sl.name, 'Proveedor #' || i.supplier_id) AS supplier_name, b.name AS branch_name
			FROM supplier_invoices i
			JOIN procurement_orders o ON o.id = i.order_id
			LEFT JOIN shopping_locations sl ON sl.id = i.supplier_id
			LEFT JOIN branches b ON b.id = i.branch_id
			WHERE i.id = :id", $id, 'La factura');
		$rows = [];
		foreach (SupplierInvoiceService::GetInstance()->GetInvoiceItems($id) as $l)
		{
			$rows[] = [
				$l['product_name'] . ($l['issues'] ? "
" . $l['issues'] : ''),
				$this->Qty($l['ordered_amount'], 'u'),
				$this->Qty($l['received_amount'], 'u'),
				$this->Qty($l['invoiced_amount'], 'u'),
				$this->Money($l['order_unit_price']),
				$this->Money($l['invoice_unit_price']),
				$this->Money($l['invoiced_amount'] * $l['invoice_unit_price']),
				floatval($l['amount_at_risk']) > 0 ? $this->Money($l['amount_at_risk']) : '—'
			];
		}
		return [
			'type_label' => 'Factura de proveedor',
			'folio' => $i['invoice_code'],
			'date' => $i['invoice_date'],
			'status' => $this->StatusLabel($i['status']),
			'state' => $i['status'],
			'parties' => [['Proveedor', $i['supplier_name']], ['Sucursal', $i['branch_name']], ['N.º factura del proveedor', $i['invoice_number']]],
			'people' => [['Registró', $this->UserName($i['created_by_user_id']), $i['created_timestamp']], ['Decidió', $this->UserName($i['decided_by_user_id']), $i['decided_timestamp']]],
			'columns' => ['Producto', 'Pedido', 'Recibido', 'Facturado', 'Precio OC', 'Precio factura', 'Importe', 'En riesgo'],
			'numeric_from' => 1,
			'rows' => $rows,
			'totals' => [['Subtotal', $this->Money($i['subtotal'])], ['Impuestos', $this->Money($i['tax_amount'])], ['Total factura', $this->Money($i['total'])], ['Monto en riesgo', $this->Money($i['amount_at_risk'])]],
			'refs' => [['Orden de compra', $i['order_code'], ['order', $i['order_id']]]],
			'notes' => trim(($i['notes'] ?? '') . ($i['decision_reason'] ? "
Decisión: " . $i['decision_reason'] : '')) ?: null
		];
	}

	private function DocReceipt(int $id): array
	{
		$db = DatabaseService::GetInstance()->GetDbConnectionRaw();
		$r = $this->Fetch("
			SELECT r.*, po.order_code, po.id AS order_id, sl.name AS supplier_name, b.name AS branch_name
			FROM procurement_receipts r
			JOIN procurement_orders po ON po.id = r.order_id
			LEFT JOIN shopping_locations sl ON sl.id = r.supplier_id
			LEFT JOIN branches b ON b.id = r.branch_id
			WHERE r.id = :id", $id, 'La recepción');
		$stmt = $db->prepare("
			SELECT ri.*, p.name AS product_name, IFNULL(qu.name, 'u') AS qu_name, l.name AS location_name
			FROM procurement_receipt_items ri
			JOIN products p ON p.id = ri.product_id
			LEFT JOIN quantity_units qu ON qu.id = p.qu_id_stock
			LEFT JOIN locations l ON l.id = ri.location_id
			WHERE ri.receipt_id = :id ORDER BY p.name");
		$stmt->execute([':id' => $id]);
		$rows = [];
		foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $i)
		{
			$rows[] = [
				$i['product_name'] . ($i['reject_reason'] ? "\nRechazo: " . $i['reject_reason'] : ''),
				$i['best_before_date'] ?? '—',
				$i['location_name'] ?? '—',
				$this->Qty($i['delivered_amount'], $i['qu_name']),
				$this->Qty($i['accepted_amount'], $i['qu_name']),
				$this->Qty($i['rejected_amount'], $i['qu_name']),
				$this->Money($i['unit_price']),
				$this->Money($i['accepted_amount'] * $i['unit_price'])
			];
		}
		return [
			'type_label' => 'Recepción de mercancía',
			'folio' => $r['receipt_code'],
			'date' => $r['received_at'],
			'status' => floatval($r['total_rejected_cost']) > 0 ? 'Con rechazos' : 'Conforme',
			'parties' => [['Proveedor', $r['supplier_name']], ['Sucursal', $r['branch_name']], ['Guía / factura proveedor', $r['supplier_document'] ?? '—'], ['Temperatura de llegada', $r['temperature_on_arrival'] !== null ? $r['temperature_on_arrival'] . ' °C' : '—']],
			'people' => [['Recibió', $this->UserName($r['received_by_user_id']), $r['received_at']]],
			'columns' => ['Producto', 'Vence', 'Ubicación', 'Entregado', 'Aceptado', 'Rechazado', 'Costo unit.', 'Ingresado'],
			'numeric_from' => 3,
			'rows' => $rows,
			'totals' => [['Ingresado al stock', $this->Money($r['total_accepted_cost'])], ['Rechazado (incidencia proveedor)', $this->Money($r['total_rejected_cost'])]],
			'refs' => [['Orden de compra', $r['order_code'], ['order', $r['order_id']]], ['Transacción de stock', $r['stock_transaction_id'] ?? '—']],
			'notes' => $r['notes']
		];
	}

	private function DocTransfer(int $id): array
	{
		$d = StockTransferService::GetInstance()->GetTransferDetail($id);
		$t = $d['transfer'];
		$rows = [];
		$wasteRefs = [];
		foreach ($d['lots'] as $l)
		{
			$rows[] = [$l['product_name'], $l['best_before_date'] ?? '—', $this->Qty($l['sent_amount'], $l['qu_name']), $l['received_amount'] === null ? '—' : $this->Qty($l['received_amount'], $l['qu_name']), $l['difference_amount'] === null ? '—' : $this->Qty($l['difference_amount'], $l['qu_name']), $this->Money($l['unit_price']), $this->Money($l['sent_amount'] * $l['unit_price'])];
			if ($l['waste_record_id'])
			{
				$wasteRefs[$l['waste_record_id']] = ['Merma por diferencia', $l['waste_case_number'], ['waste', $l['waste_record_id']]];
			}
		}
		return [
			'type_label' => 'Traslado entre sucursales',
			'folio' => $t['transfer_code'],
			'date' => $t['sent_at'],
			'status' => $this->StatusLabel($t['status']),
			'state' => $t['status'],
			'parties' => [['Origen', $t['from_branch_name'] . ' · ' . $t['from_location_name']], ['Destino', $t['to_branch_name'] . ' · ' . $t['to_location_name']]],
			'people' => [['Envió', $t['created_by_name'], $t['sent_at']], [$t['status'] === 'cancelled' ? 'Anuló' : 'Recibió', $t['received_by_name'] ?? '—', $t['received_at']]],
			'columns' => ['Producto', 'Lote (vence)', 'Enviado', 'Recibido', 'Diferencia', 'Costo unit.', 'Importe'],
			'numeric_from' => 2,
			'rows' => $rows,
			'totals' => [['Valor enviado', $this->Money($t['total_sent_cost'])], ['Diferencia (merma)', $this->Money($t['total_difference_cost'])]],
			'refs' => array_merge([['Transacción de envío', $t['send_transaction_id'] ?? '—'], ['Transacción de recepción', $t['receive_transaction_id'] ?? '—']], array_values($wasteRefs)),
			'notes' => trim(($t['notes'] ?? '') . "\n" . ($t['receive_notes'] ?? '')) ?: null
		];
	}

	private function DocWaste(int $id): array
	{
		$w = $this->Fetch("
			SELECT w.*, IFNULL(p.name, 'Producto #' || w.product_id || ' (eliminado)') AS product_name, IFNULL(qu.name, 'u') AS qu_name, r.code AS reason_code, r.name AS reason_name, r.shrink_type,
				b.name AS branch_name, l.name AS location_name
			FROM waste_records w
			LEFT JOIN products p ON p.id = w.product_id
			LEFT JOIN quantity_units qu ON qu.id = w.qu_id
			LEFT JOIN waste_reasons r ON r.id = w.waste_reason_id
			LEFT JOIN branches b ON b.id = w.branch_id
			LEFT JOIN locations l ON l.id = w.location_id
			WHERE w.id = :id", $id, 'La merma');
		return [
			'type_label' => 'Registro de merma',
			'folio' => $w['case_number'] ?: self::Folio('MRM', $id, $w['created_timestamp']),
			'date' => $w['created_timestamp'],
			'status' => $this->StatusLabel($w['status']),
			'state' => $w['status'],
			'parties' => [['Sucursal', $w['branch_name']], ['Ubicación', $w['location_name'] ?? '—'], ['Causal', trim(($w['reason_code'] ?? '') . ' ' . ($w['reason_name'] ?? ''))], ['Tipo de merma', $w['shrink_type'] ?? '—']],
			'people' => [['Reportó', $this->UserName($w['reported_by_user_id']), $w['created_timestamp']], ['Aprobó', $this->UserName($w['approved_by_user_id']), $w['approved_timestamp']]],
			'columns' => ['Producto', 'Lote', 'Vence', 'Cantidad', 'Costo unit.', 'Costo total'],
			'numeric_from' => 3,
			'rows' => [[$w['product_name'], $w['batch_number'] ?: ($w['stock_id'] ?? '—'), $w['best_before_date'] ?? '—', $this->Qty($w['amount'], $w['qu_name']), $this->Money($w['unit_cost']), $this->Money($w['total_cost'])]],
			'totals' => [['Pérdida', $this->Money($w['total_cost'])], ['Recuperado', $this->Money($w['financial_recovery_amount'] ?? 0)]],
			'refs' => [['Transacción de stock', $w['transaction_id'] ?? '—']],
			'notes' => trim(implode("\n", array_filter([$w['notes'], $w['root_cause_analysis'] ? 'Causa raíz: ' . $w['root_cause_analysis'] : null, $w['corrective_action_plan'] ? 'Acción correctiva: ' . $w['corrective_action_plan'] : null]))) ?: null
		];
	}

	private function DocAdjustment(int $id): array
	{
		$c = $this->Fetch("
			SELECT c.*, IFNULL(p.name, 'Producto #' || c.product_id || ' (eliminado)') AS product_name, IFNULL(qu.name, 'u') AS qu_name, b.name AS branch_name, l.name AS location_name
			FROM stock_cycle_counts c
			LEFT JOIN products p ON p.id = c.product_id
			LEFT JOIN quantity_units qu ON qu.id = p.qu_id_stock
			LEFT JOIN branches b ON b.id = c.branch_id
			LEFT JOIN locations l ON l.id = c.location_id
			WHERE c.id = :id", $id, 'El conteo');
		return [
			'type_label' => $c['status'] === 'adjusted' ? 'Ajuste de inventario por conteo' : 'Conteo cíclico',
			'folio' => self::Folio($c['status'] === 'adjusted' ? 'AJU' : 'CNT', $id, $c['created_timestamp']),
			'date' => $c['executed_date'] ?? $c['scheduled_date'],
			'status' => $this->StatusLabel($c['status']),
			'state' => $c['status'],
			'parties' => [['Sucursal', $c['branch_name']], ['Ubicación', $c['location_name'] ?? 'Todas']],
			'people' => [['Ejecutó', $this->UserName($c['executed_by_user_id']), $c['executed_date']], ['Aprobó ajuste', $this->UserName($c['supervisor_user_id']), null]],
			'columns' => ['Producto', 'Teórico', 'Contado', 'Diferencia', 'Valor diferencia'],
			'numeric_from' => 1,
			'rows' => [[$c['product_name'], $this->Qty($c['theoretical_stock'], $c['qu_name']), $this->Qty($c['counted_stock'], $c['qu_name']), $this->Qty($c['difference_amount'], $c['qu_name']), $this->Money($c['difference_cost'])]],
			'totals' => [['Valor de la diferencia', $this->Money($c['difference_cost'])]],
			'refs' => [['Transacción de ajuste', $c['adjustment_transaction_id'] ?? '—']],
			'notes' => $c['notes']
		];
	}

	private function DocQuarantine(int $id): array
	{
		$q = $this->Fetch("
			SELECT q.*, IFNULL(p.name, 'Producto #' || q.product_id || ' (eliminado)') AS product_name, IFNULL(qu.name, 'u') AS qu_name, b.name AS branch_name, l.name AS location_name, w.case_number AS waste_case_number
			FROM quarantine_items q
			LEFT JOIN products p ON p.id = q.product_id
			LEFT JOIN quantity_units qu ON qu.id = q.qu_id
			LEFT JOIN branches b ON b.id = q.branch_id
			LEFT JOIN locations l ON l.id = q.location_id
			LEFT JOIN waste_records w ON w.id = q.waste_record_id
			WHERE q.id = :id", $id, 'La cuarentena');
		$refs = [];
		if ($q['waste_record_id'])
		{
			$refs[] = ['Merma generada', $q['waste_case_number'] ?: self::Folio('MRM', intval($q['waste_record_id']), null), ['waste', $q['waste_record_id']]];
		}
		return [
			'type_label' => 'Bloqueo de calidad (cuarentena)',
			'folio' => self::Folio('CUA', $id, $q['entered_timestamp']),
			'date' => $q['entered_timestamp'],
			'status' => $this->StatusLabel($q['status']),
			'state' => $q['status'],
			'parties' => [['Sucursal', $q['branch_name']], ['Ubicación', $q['location_name'] ?? '—'], ['Motivo', $q['reason']]],
			'people' => [['Resolvió', $q['resolved_by'] ?? '—', $q['resolved_timestamp']]],
			'columns' => ['Producto', 'Lote', 'Cantidad'],
			'numeric_from' => 2,
			'rows' => [[$q['product_name'], $q['stock_id'] ?? '—', $this->Qty($q['amount'], $q['qu_name'])]],
			'totals' => [],
			'refs' => $refs,
			'notes' => $q['resolution_notes']
		];
	}

	private function DocSupplierReturn(int $id): array
	{
		$s = $this->Fetch("
			SELECT s.*, p.name AS product_name, IFNULL(qu.name, 'u') AS qu_name, sl.name AS supplier_name, b.name AS branch_name
			FROM supplier_returns s
			LEFT JOIN products p ON p.id = s.product_id
			LEFT JOIN quantity_units qu ON qu.id = s.qu_id
			LEFT JOIN shopping_locations sl ON sl.id = s.supplier_id
			LEFT JOIN branches b ON b.id = s.branch_id
			WHERE s.id = :id", $id, 'La devolución');
		return [
			'type_label' => 'Devolución / nota de crédito de proveedor',
			'folio' => $s['case_number'] ?: self::Folio('DEV', $id, $s['created_timestamp']),
			'date' => $s['created_timestamp'],
			'status' => $this->StatusLabel($s['status']),
			'state' => $s['status'],
			'parties' => [['Proveedor', $s['supplier_name']], ['Sucursal', $s['branch_name']], ['Motivo', $s['return_reason'] ?? $s['incident_type']]],
			'people' => [['Registró', $this->UserName($s['user_id']), $s['created_timestamp']], ['Resuelto', '', $s['resolved_timestamp']]],
			'columns' => ['Producto', 'Lote', 'Cantidad', 'Costo unit.', 'Pérdida'],
			'numeric_from' => 2,
			'rows' => [[$s['product_name'] ?? '—', $s['batch_number'] ?? '—', $this->Qty($s['amount'], $s['qu_name']), $this->Money($s['unit_cost']), $this->Money($s['total_loss_cost'])]],
			'totals' => [['Crédito solicitado', $this->Money($s['credit_requested_amount'])], ['Crédito recuperado', $this->Money($s['credit_recovered_amount'])], ['Pérdida no recuperada', $this->Money($s['loss_unrecovered_amount'])]],
			'refs' => [],
			'notes' => $s['notes']
		];
	}

	/**
	 * Paso a paso del proceso de cada documento y en cuál está: [título, ayuda, done|current|pending|cancelled]
	 */
	public function ProcessSteps(string $type, string $state): array
	{
		$flows = [
			'order' => [[['Elaborar', 'MRP o compra manual'], ['Aprobar', 'responsable de compras'], ['Enviar', 'fecha comprometida'], ['Recibir', 'contra la orden, con inspección'], ['Cerrar', 'recibida o con faltante']],
				['draft' => 1, 'approved' => 2, 'sent' => 3, 'partially_received' => 3, 'received' => 5, 'closed' => 5]],
			'receipt' => [[['Inspeccionar', 'temperatura y vencimiento'], ['Aceptar / rechazar', 'por línea'], ['Ingresar al stock', 'transacción de compra'], ['Incidencia', 'rechazos al proveedor']], []],
			'transfer' => [[['Enviar', 'sale del origen'], ['En tránsito', 'no disponible'], ['Recibir', 'confirma el destino'], ['Diferencias', 'merma de traslado']],
				['in_transit' => 2, 'received' => 4]],
			'waste' => [[['Registrar', 'causal, costo y evidencia'], ['Aprobar', 'otra persona'], ['Investigar', 'causa raíz'], ['Acción correctiva', 'plan con responsable'], ['Verificar y cerrar', 'eficacia de la acción']],
				['detected' => 1, 'reviewing' => 1, 'pending_approval' => 1, 'approved' => 2, 'investigating' => 2, 'action_assigned' => 3, 'executed' => 4, 'verified' => 5, 'closed' => 5]],
			'adjustment' => [[['Programar', 'producto y ubicación'], ['Contar', 'conteo físico'], ['Aprobar ajuste', 'supervisor distinto'], ['Contabilizar', 'ajuste de inventario']],
				['pending' => 1, 'discrepancy' => 2, 'completed' => 4, 'adjusted' => 4]],
			'quarantine' => [[['Bloquear lote', 'fuera de venta'], ['Inspeccionar', 'calidad'], ['Decidir', 'liberar, merma o devolver']],
				['in_quarantine' => 1, 'released' => 3, 'discarded_as_waste' => 3, 'returned_to_supplier' => 3]],
			'supplier_return' => [[['Registrar reclamo', 'lote y evidencia'], ['Solicitar nota de crédito', 'al proveedor'], ['Recuperar', 'NC recibida']],
				['pending_credit' => 2, 'credit_recovered' => 3]],
			'invoice' => [[['Registrar factura', 'contra OC con recepción'], ['Conciliar', 'pedido · recibido · facturado'], ['Decidir diferencias', 'otra persona, con motivo']],
				['matched' => 3, 'discrepancy' => 2, 'approved_with_difference' => 3, 'rejected' => 3]]
		];
		[$steps, $map] = $flows[$type];
		$cancelled = in_array($state, ['cancelled', 'rejected']);
		$current = $cancelled ? 1 : ($map[$state] ?? count($steps));
		$result = [];
		foreach ($steps as $i => [$label, $hint])
		{
			if ($i < $current)
			{
				$st = 'done';
			}
			elseif ($i === $current)
			{
				$st = $cancelled ? 'cancelled' : 'current';
			}
			else
			{
				$st = 'pending';
			}
			$result[] = [$label, $hint, $st];
		}
		if ($state === 'closed' && $type === 'order')
		{
			$result[4][1] = 'cerrada con faltante';
		}
		return $result;
	}

	private function Fetch(string $sql, int $id, string $label): array
	{
		$stmt = DatabaseService::GetInstance()->GetDbConnectionRaw()->prepare($sql);
		$stmt->execute([':id' => $id]);
		$row = $stmt->fetch(\PDO::FETCH_ASSOC);
		if (!$row)
		{
			throw new \Exception("{$label} #{$id} no existe");
		}
		return $row;
	}

	private function UserName($userId): string
	{
		if (empty($userId))
		{
			return '—';
		}
		$stmt = DatabaseService::GetInstance()->GetDbConnectionRaw()->prepare("SELECT IFNULL(NULLIF(TRIM(first_name || ' ' || IFNULL(last_name, '')), ''), username) FROM users WHERE id = :id");
		$stmt->execute([':id' => $userId]);
		return $stmt->fetchColumn() ?: '#' . $userId;
	}

	private function Qty($amount, string $unit): string
	{
		return rtrim(rtrim(number_format(floatval($amount), 2, '.', ','), '0'), '.') . ' ' . $unit;
	}

	private function Money($amount): string
	{
		return (floatval($amount) < 0 ? '-' : '') . '$' . number_format(abs(floatval($amount)), 2);
	}

	private function StatusLabel(?string $status): string
	{
		return [
			'draft' => 'Borrador', 'approved' => 'Aprobado', 'sent' => 'Enviada', 'partially_received' => 'Recepción parcial',
			'received' => 'Recibido', 'closed' => 'Cerrada con faltante', 'cancelled' => 'Anulado', 'in_transit' => 'En tránsito',
			'pending_approval' => 'Pendiente de aprobación', 'rejected' => 'Rechazado', 'pending' => 'Programado', 'completed' => 'Sin diferencia',
			'discrepancy' => 'Con diferencia', 'adjusted' => 'Ajustado', 'in_quarantine' => 'Bloqueado', 'released' => 'Liberado',
			'discarded_as_waste' => 'Dado de baja (merma)', 'returned_to_supplier' => 'Devuelto al proveedor',
			'pending_credit' => 'Pendiente de nota de crédito', 'credit_recovered' => 'Crédito recuperado',
			'matched' => 'Conciliada', 'approved_with_difference' => 'Aprobada con diferencia'
		][$status] ?? (string) $status;
	}
}
