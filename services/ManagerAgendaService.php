<?php

namespace Grocy\Services;

/**
 * Agenda del gerente: decisiones y tareas pendientes hoy, cada una con datos reales, impacto y la página donde se resuelve.
 * Prioridad: danger (pérdida en curso o control incumplido) > warning (vence pronto) > info (rutina).
 */
class ManagerAgendaService extends BaseService
{
	public function GetAgenda(?int $branchId = null): array
	{
		$db = DatabaseService::GetInstance()->GetDbConnectionRaw();
		$b = fn(string $col) => $branchId ? " AND {$col} = " . intval($branchId) : '';
		$stockBranch = $branchId ? ' AND l.branch_id = ' . intval($branchId) : '';
		$q = fn(string $sql) => $db->query($sql)->fetchAll(\PDO::FETCH_ASSOC);
		$items = [];

		// Vencidos todavía en stock: pérdida segura si no se retiran
		$rows = $q("
			SELECT p.name, s.best_before_date AS detail, SUM(s.amount) AS qty, SUM(s.amount * IFNULL(s.price, 0)) AS value
			FROM stock s JOIN products p ON p.id = s.product_id JOIN locations l ON l.id = s.location_id
			WHERE s.best_before_date < date('now', 'localtime') AND IFNULL(l.is_transit, 0) = 0 $stockBranch
			GROUP BY s.product_id, s.best_before_date ORDER BY value DESC");
		$items[] = $this->Item('expired', 'danger', 'fa-skull-crossbones', 'Retirar productos vencidos', 'Están en exhibición o bodega: registrar la merma y retirarlos hoy.', $rows, '/waste/report', 'Registrar merma', 'vencido');

		// Por vencer en 3 días: última oportunidad de rebaja o traslado
		$rows = $q("
			SELECT p.name, s.best_before_date AS detail, SUM(s.amount) AS qty, SUM(s.amount * IFNULL(s.price, 0)) AS value
			FROM stock s JOIN products p ON p.id = s.product_id JOIN locations l ON l.id = s.location_id
			WHERE s.best_before_date BETWEEN date('now', 'localtime') AND date('now', 'localtime', '+3 day') AND IFNULL(l.is_transit, 0) = 0 $stockBranch
			GROUP BY s.product_id, s.best_before_date ORDER BY s.best_before_date, value DESC");
		$items[] = $this->Item('expiring', 'warning', 'fa-hourglass-half', 'Rebajar o trasladar lo que vence en 3 días', 'Decidir rebaja FEFO, traslado a otra sucursal o donación antes de que sea merma.', $rows, '/waste?fefo_estado=rotacion#tab-fefo', 'Ver vencimientos', 'vence');

		// Mermas por aprobar (segregación: no puede aprobarlas quien las reportó)
		$rows = $q("
			SELECT IFNULL(w.case_number, 'MRM-' || w.id) || ' · ' || IFNULL(p.name, '#' || w.product_id) AS name, r.name AS detail, w.amount AS qty, w.total_cost AS value, 'waste' AS doc_type, w.id AS doc_id, w.created_timestamp AS since, w.reported_by_user_id AS reporter_id
			FROM waste_records w LEFT JOIN products p ON p.id = w.product_id LEFT JOIN waste_reasons r ON r.id = w.waste_reason_id
			WHERE w.status = 'pending_approval'" . $b('w.branch_id') . ' ORDER BY w.total_cost DESC');
		$items[] = $this->Item('waste_approvals', 'danger', 'fa-clipboard-check', 'Aprobar o rechazar mermas', 'El stock no se descuenta hasta aprobarlas: el inventario está sobrestimado.', $rows, '/waste/approvals', 'Ir a aprobaciones');

		// Ajustes de conteo con diferencia
		$rows = $q("
			SELECT p.name, 'teórico ' || c.theoretical_stock || ' / contado ' || c.counted_stock AS detail, c.difference_amount AS qty, ABS(c.difference_cost) AS value, 'adjustment' AS doc_type, c.id AS doc_id
			FROM stock_cycle_counts c JOIN products p ON p.id = c.product_id
			WHERE c.status = 'discrepancy'" . $b('c.branch_id') . ' ORDER BY ABS(c.difference_cost) DESC');
		$items[] = $this->Item('count_adjustments', 'warning', 'fa-list-check', 'Aprobar ajustes de inventario', 'Conteos con diferencia sin contabilizar: revisar y aprobar el ajuste (otra persona).', $rows, '/waste#tab-conteos', 'Ir a conteos');

		// Órdenes de compra
		$rows = $q("
			SELECT po.order_code || ' · ' || IFNULL(sl.name, '') AS name, 'comprometida ' || po.expected_delivery_date AS detail, NULL AS qty, po.total_estimated_cost AS value, 'order' AS doc_type, po.id AS doc_id
			FROM procurement_orders po LEFT JOIN shopping_locations sl ON sl.id = po.supplier_id
			WHERE po.status IN ('sent', 'partially_received') AND po.expected_delivery_date < date('now', 'localtime')" . $b('po.branch_id') . ' ORDER BY po.expected_delivery_date');
		$items[] = $this->Item('late_orders', 'danger', 'fa-truck-clock', 'Reclamar órdenes atrasadas', 'El proveedor no entregó en la fecha comprometida: riesgo de quiebre de stock.', $rows, '/mrp', 'Ver órdenes');

		$rows = $q("
			SELECT po.order_code || ' · ' || IFNULL(sl.name, '') AS name, (SELECT COUNT(*) FROM procurement_order_items i WHERE i.order_id = po.id) || ' líneas' AS detail, NULL AS qty, po.total_estimated_cost AS value, 'order' AS doc_type, po.id AS doc_id
			FROM procurement_orders po LEFT JOIN shopping_locations sl ON sl.id = po.supplier_id
			WHERE po.status = 'draft'" . $b('po.branch_id') . ' ORDER BY po.total_estimated_cost DESC');
		$items[] = $this->Item('draft_orders', 'info', 'fa-file-signature', 'Aprobar órdenes de compra', 'Borradores generados por el MRP esperando aprobación.', $rows, '/mrp', 'Ir a MRP');

		$rows = $q("
			SELECT po.order_code || ' · ' || IFNULL(sl.name, '') AS name, 'llega ' || po.expected_delivery_date AS detail, NULL AS qty, po.total_estimated_cost AS value, 'order' AS doc_type, po.id AS doc_id
			FROM procurement_orders po LEFT JOIN shopping_locations sl ON sl.id = po.supplier_id
			WHERE po.status IN ('sent', 'partially_received') AND po.expected_delivery_date >= date('now', 'localtime') AND po.expected_delivery_date <= date('now', 'localtime', '+1 day')" . $b('po.branch_id'));
		$items[] = $this->Item('incoming_orders', 'info', 'fa-truck-ramp-box', 'Preparar recepción de hoy y mañana', 'Inspeccionar temperatura y vencimiento al recibir contra la orden.', $rows, '/mrp', 'Recibir');

		// Traslados atrasados en tránsito
		$rows = $q("
			SELECT t.transfer_code || ' · ' || bf.name || ' → ' || bt.name AS name, 'enviado ' || t.sent_at AS detail, NULL AS qty, t.total_sent_cost AS value, 'transfer' AS doc_type, t.id AS doc_id
			FROM stock_transfers t JOIN branches bf ON bf.id = t.from_branch_id JOIN branches bt ON bt.id = t.to_branch_id
			WHERE t.status = 'in_transit' AND t.sent_at < datetime('now', 'localtime', '-2 day')" . ($branchId ? ' AND (t.from_branch_id = ' . intval($branchId) . ' OR t.to_branch_id = ' . intval($branchId) . ')' : '') . ' ORDER BY t.sent_at');
		$items[] = $this->Item('late_transfers', 'warning', 'fa-truck-fast', 'Confirmar traslados en tránsito > 48 h', 'Mercancía fuera de inventario disponible: confirmar recepción o investigar.', $rows, '/transfers', 'Ver traslados');

		// Pedidos de clientes con stock reservado pendientes de preparar o despachar (Etapa 22)
		$rows = $q("
			SELECT co.order_code || ' · ' || c.name AS name, CASE co.status WHEN 'confirmed' THEN 'por preparar' WHEN 'picking' THEN 'en preparación' WHEN 'picked' THEN 'por empacar' ELSE 'por despachar' END
				|| IFNULL(' · entrega ' || co.requested_date, '') AS detail, NULL AS qty, (SELECT SUM(i.amount * i.unit_price) FROM customer_order_items i WHERE i.order_id = co.id) AS value, NULL AS doc_type, co.id AS doc_id
			FROM customer_orders co JOIN customers c ON c.id = co.customer_id
			WHERE co.status IN ('confirmed', 'picking', 'picked', 'packed')" . $b('co.branch_id') . " ORDER BY IFNULL(co.requested_date, '9999-12-31'), co.id");
		$items[] = $this->Item('customer_orders', 'info', 'fa-boxes-packing', 'Preparar y despachar pedidos de clientes', 'El stock está reservado: el POS no puede venderlo hasta despachar o anular.', $rows, '/orders', 'Ver pedidos');

		// Cuarentenas abiertas
		$rows = $q("
			SELECT p.name, q.reason AS detail, q.amount AS qty, NULL AS value, 'quarantine' AS doc_type, q.id AS doc_id
			FROM quarantine_items q JOIN products p ON p.id = q.product_id
			WHERE q.status = 'in_quarantine'" . $b('q.branch_id') . ' ORDER BY q.entered_timestamp');
		$items[] = $this->Item('quarantine', 'warning', 'fa-shield-virus', 'Decidir lotes en cuarentena', 'Liberar, dar de baja o devolver al proveedor.', $rows, '/waste?fefo_estado=cuarentena#tab-fefo', 'Ir a calidad');

		// Casos de merma con plazo vencido
		$rows = $q("
			SELECT IFNULL(w.case_number, 'MRM-' || w.id) || ' · ' || IFNULL(p.name, '#' || w.product_id) AS name, 'plazo ' || w.due_date AS detail, NULL AS qty, w.total_cost AS value, 'waste' AS doc_type, w.id AS doc_id
			FROM waste_records w LEFT JOIN products p ON p.id = w.product_id
			WHERE w.workflow_status NOT IN ('closed', 'verified') AND w.due_date IS NOT NULL AND w.due_date < date('now', 'localtime')" . $b('w.branch_id') . ' ORDER BY w.due_date');
		$items[] = $this->Item('overdue_cases', 'warning', 'fa-magnifying-glass', 'Cerrar investigaciones de merma vencidas', 'Causa raíz o acción correctiva fuera de plazo.', $rows, '/waste', 'Centro de control');

		// Reclamos al proveedor sin recuperar
		$rows = $q("
			SELECT IFNULL(sr.case_number, 'DEV-' || sr.id) || ' · ' || IFNULL(sl.name, '') AS name, sr.return_reason AS detail, NULL AS qty, sr.credit_requested_amount AS value, 'supplier_return' AS doc_type, sr.id AS doc_id
			FROM supplier_returns sr LEFT JOIN shopping_locations sl ON sl.id = sr.supplier_id
			WHERE sr.status = 'pending_credit'" . $b('sr.branch_id') . ' ORDER BY sr.credit_requested_amount DESC');
		$items[] = $this->Item('credits', 'info', 'fa-file-invoice-dollar', 'Cobrar notas de crédito', 'Pérdidas atribuibles al proveedor pendientes de recuperar.', $rows, '/waste#tab-recuperacion', 'Ver reclamos');

		// Facturas de proveedor con diferencias contra OC/recepción (conciliación de 3 vías)
		$rows = $q("
			SELECT i.invoice_code || ' · ' || IFNULL(sl.name, '') AS name, 'factura ' || i.invoice_number AS detail, NULL AS qty, i.amount_at_risk AS value
			FROM supplier_invoices i LEFT JOIN shopping_locations sl ON sl.id = i.supplier_id
			WHERE i.status = 'discrepancy'" . $b('i.branch_id') . ' ORDER BY i.amount_at_risk DESC');
		$items[] = $this->Item('invoices', 'warning', 'fa-file-invoice-dollar', 'Facturas con diferencias', 'El proveedor cobra cantidades no recibidas o precios sobre la orden: aprobar o rechazar antes de pagar.', $rows, '/mrp#invoices', 'Conciliar');

		// Retiros de producto: lotes que la sucursal aún no confirma como separados
		$rows = $q("
			SELECT r.recall_code || ' · ' || IFNULL(p.name, '') AS name, IFNULL(l.name, '') || ' · lote ' || i.stock_id AS detail, i.amount AS qty, NULL AS value
			FROM product_recall_items i JOIN product_recalls r ON r.id = i.recall_id LEFT JOIN products p ON p.id = r.product_id LEFT JOIN locations l ON l.id = i.location_id
			WHERE r.status = 'open' AND i.status = 'quarantined'" . $b('i.branch_id') . ' ORDER BY r.severity, r.id');
		$items[] = $this->Item('recalls', 'danger', 'fa-rotate-left', 'Separar lotes de un retiro de producto', 'Están bloqueados para la venta: retirarlos de góndola y confirmar la cantidad encontrada.', $rows, '/recalls', 'Ver retiros');

		// Centro de alertas: críticas o fuera de plazo sin cerrar (hora de PHP)
		$now = $db->quote(date('Y-m-d H:i:s'));
		$rows = $q("
			SELECT a.title AS name, CASE WHEN a.due_at < $now THEN 'venció ' || substr(a.due_at, 1, 16) ELSE 'plazo ' || substr(a.due_at, 1, 16) END AS detail, NULL AS qty, a.value AS value
			FROM dairy_alerts a WHERE a.status IN (" . \Grocy\Services\AlertCenterService::In(\Grocy\Services\AlertCenterService::ACTIVE) . ") AND (a.priority = 'P1' OR (a.due_at < $now AND a.status != 'pending_approval'))" . $b('a.branch_id') . ' ORDER BY a.due_at');
		$items[] = $this->Item('alerts', 'danger', 'fa-bell', 'Alertas críticas o vencidas', 'Hallazgos de prevención de pérdidas sin cerrar: asignar responsable y resolver.', $rows, '/alerts', 'Centro de alertas');

		// Escalamiento: tareas de piso no completadas pasado su plazo + margen (STORE_TASK_ESCALATION_MINUTES); las vencidas salen solo en su tarjeta; hora de PHP, no de SQLite (UTC)
		$escalateBefore = $db->quote(date('Y-m-d H:i:s', time() - 60 * intval(GROCY_STORE_TASK_ESCALATION_MINUTES)));

		// Acciones correctivas generadas por mermas aprobadas (valor = pérdida que las originó)
		$rows = $q("
			SELECT t.title AS name, IFNULL(t.due_time, '') AS detail, NULL AS qty, w.total_cost AS value, 'waste' AS doc_type, t.source_id AS doc_id, t.id AS task_id, t.task_type AS area, t.due_time AS due
			FROM workforce_store_tasks t LEFT JOIN waste_records w ON w.id = t.source_id
			WHERE t.status = 'pending' AND t.source_type = 'waste' AND (t.due_time IS NULL OR t.due_time >= $escalateBefore)" . $b('t.branch_id') . ' ORDER BY t.due_time');
		$items[] = $this->Item('corrective', 'warning', 'fa-screwdriver-wrench', 'Acciones correctivas de merma', 'Mermas aprobadas sobre el umbral: verificar la causa en sitio para que no se repita.', $rows, '/workforce', 'Piso de tienda');

		// Escalamiento al gerente
		$rows = $q("
			SELECT t.title AS name, 'venció ' || t.due_time || ' · rol ' || t.assigned_role || IFNULL(' · ' || NULLIF(TRIM(u.first_name || ' ' || IFNULL(u.last_name, '')), ''), '') AS detail, NULL AS qty, NULL AS value, t.id AS task_id, t.task_type AS area, t.due_time AS due
			FROM workforce_store_tasks t LEFT JOIN users u ON u.id = t.assigned_user_id
			WHERE t.status = 'pending' AND t.due_time IS NOT NULL AND t.due_time < $escalateBefore" . $b('t.branch_id') . ' ORDER BY t.due_time');
		$items[] = $this->Item('tasks_overdue', 'danger', 'fa-bell', 'Tareas de piso vencidas', 'No se completaron a tiempo (temperatura, rotación, limpieza o correctivas): reasignar o verificar en sitio.', $rows, '/workforce', 'Piso de tienda');

		// Tareas de piso pendientes dentro de plazo
		$rows = $q("
			SELECT title AS name, IFNULL(due_time, '') AS detail, NULL AS qty, NULL AS value, id AS task_id, task_type AS area, due_time AS due
			FROM workforce_store_tasks WHERE status = 'pending' AND IFNULL(source_type, '') != 'waste' AND (due_time IS NULL OR due_time >= $escalateBefore)" . $b('branch_id') . ' ORDER BY due_time');
		$items[] = $this->Item('tasks', 'info', 'fa-person-walking', 'Tareas de piso pendientes', 'Rotación, temperatura, limpieza y reposición asignadas al personal.', $rows, '/workforce', 'Piso de tienda');

		$items = array_values(array_filter($items, fn($i) => $i['count'] > 0));
		$rank = ['danger' => 0, 'warning' => 1, 'info' => 2];
		usort($items, fn($a, $b) => [$rank[$a['tone']], -$a['value']] <=> [$rank[$b['tone']], -$b['value']]);

		return $items;
	}

	private function Item(string $key, string $tone, string $icon, string $title, string $why, array $rows, string $href, string $action, ?string $detailPrefix = null): array
	{
		$value = array_sum(array_map(fn($r) => floatval($r['value'] ?? 0), $rows));
		foreach ($rows as &$r)
		{
			if ($detailPrefix && $r['detail'])
			{
				$r['detail'] = $detailPrefix . ' ' . $r['detail'];
			}
		}
		return [
			'key' => $key, 'tone' => $tone, 'icon' => $icon, 'title' => $title, 'why' => $why,
			'count' => count($rows), 'value' => round($value, 2), 'rows' => array_slice($rows, 0, 5),
			'href' => $href, 'action' => $action
		];
	}
}
