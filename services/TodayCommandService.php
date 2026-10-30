<?php

namespace Grocy\Services;

/**
 * Hoy — Centro de Comando Operativo Diario. Una sola fuente de datos para /today, todo calculado desde tablas reales.
 *
 * Cola unificada de decisiones: cada decisión tiene un ID único "fuente:id". Reglas antiduplicación:
 *  - Un lote vencido o por vencer que ya tiene merma pendiente, cuarentena abierta o retiro en curso no se cuenta otra vez:
 *    la decisión vive en la merma, la cuarentena o el retiro.
 *  - Una alerta «Lote por vencer» (expiring_lots) o «Retiro sin confirmar» (recall_unconfirmed) cuyo lote/retiro ya está en la cola
 *    se fusiona en esa decisión (se marca «alerta vinculada»), no suma.
 *  - Las tareas de piso dentro de plazo son ejecución, no decisiones; solo las vencidas (plazo + margen de escalamiento) son decisiones.
 *
 * Puntuación de prioridad (0–100), explicable factor por factor:
 *  severidad (crítica 30 · alta 20 · media 10 · baja 4) + seguridad alimentaria 25 + vencimiento (vencida 20 + 1/día hasta 5 ·
 *  vence hoy 12 · ≤ 3 días 6) + riesgo económico (5·log10(1+valor), máx. 15) + dependencia operativa 8 + antigüedad (2/día, máx. 10).
 *  Etiqueta: ≥ 60 Crítica · ≥ 40 Alta · ≥ 20 Media · resto Baja.
 */
class TodayCommandService extends BaseService
{
	// Tipos de decisión cuyo histórico se reconstruye (tienen fecha de apertura y de cierre)
	const HISTORY_SOURCES = ['waste', 'alert', 'quarantine', 'credit'];

	const CATEGORIES = [
		'alerts' => 'Alertas',
		'waste' => 'Mermas por aprobar',
		'counts' => 'Conteos',
		'quality' => 'Calidad',
		'purchasing' => 'Compras',
		'operations' => 'Operaciones',
		'other' => 'Otras decisiones'
	];

	private \PDO $pdo;
	private ?int $branch = null;
	private string $now;
	private string $today;

	public function Get(?int $branchId, bool $showPeople = false, ?callable $canOpen = null): array
	{
		$this->pdo = DatabaseService::GetInstance()->GetDbConnectionRaw();
		$this->branch = $branchId;
		$this->now = date('Y-m-d H:i:s');
		$this->today = date('Y-m-d');

		$decisions = $this->Decisions();
		if ($canOpen)
		{
			// Solo decisiones cuyo módulo el usuario puede abrir; todos los totales salen de esta lista filtrada
			$decisions = array_values(array_filter($decisions, fn($d) => $canOpen(explode('#', $d['href'])[0])));
		}
		$execution = $this->Execution($showPeople);
		$alerts = $this->AlertsSummary();
		$approvals = $this->Approvals();
		$risks = $this->Risks();
		// Desde el 1 de enero (mínimo 30 días): alimenta los filtros 7 d · 14 d · 30 d · Año actual
		$history = $this->History(max(30, intval(date('z')) + 1));

		$open = count($decisions);
		$urgent = count(array_filter($decisions, fn($d) => $d['score'] >= 60));
		$overdue = count(array_filter($decisions, fn($d) => $d['state'] === 'overdue'));
		$confirmed = round(array_sum(array_map(fn($d) => $d['value_kind'] === 'confirmed' ? $d['value'] : 0, $decisions)), 2);
		$estimated = round(array_sum(array_map(fn($d) => $d['value_kind'] === 'estimated' ? $d['value'] : 0, $decisions)), 2);
		$valueByCat = [];
		foreach ($decisions as $d)
		{
			if ($d['value'] > 0)
			{
				$valueByCat[$d['type']] = ($valueByCat[$d['type']] ?? 0) + $d['value'];
			}
		}
		arsort($valueByCat);

		$snap = [
			'decisions' => $open, 'urgent' => $urgent, 'overdue' => $overdue,
			'value_confirmed' => $confirmed, 'value_estimated' => $estimated,
			'alerts_critical' => $alerts['critical'], 'tasks_done' => $execution['done'], 'tasks_total' => $execution['total'],
			'alerts_action' => $alerts['action'] ?? 0, 'approvals' => $approvals['total']
		];
		$yesterday = $this->Snapshot($snap);

		$byCat = array_fill_keys(array_keys(self::CATEGORIES), 0);
		foreach ($decisions as $d)
		{
			$byCat[$d['cat']]++;
		}

		return [
			'now' => $this->now,
			'shift' => $this->CurrentShift(),
			'last_scan' => $this->Val('SELECT MAX(last_seen_at) FROM dairy_alerts'),
			'decisions' => $decisions,
			'summary' => $snap + ['value_by_type' => $valueByCat, 'linked_alerts' => array_sum(array_map(fn($d) => count($d['linked']), $decisions)),
				'history_covered' => count(array_filter($decisions, fn($d) => in_array(explode(':', $d['uid'])[0], self::HISTORY_SOURCES, true)))],
			'yesterday' => $yesterday,
			'by_category' => $byCat,
			'execution' => $execution,
			'alerts' => $alerts,
			'approvals' => $approvals,
			'risks' => $risks,
			'prevention' => $this->Prevention($risks),
			'team' => $this->Team($execution, $decisions),
			'activity' => $this->Activity(),
			'history' => $history,
			'hourly' => $this->Hourly(),
			'comparison' => $this->Comparison()
		];
	}

	// ---------------------------------------------------------------- Cola unificada

	private function Decisions(): array
	{
		$out = [];
		$stockB = $this->B('l.branch_id');
		$excluded = "s.stock_id NOT IN (SELECT stock_id FROM waste_records WHERE status = 'pending_approval' AND stock_id IS NOT NULL)
			AND s.stock_id NOT IN (SELECT stock_id FROM quarantine_items WHERE status = 'in_quarantine' AND stock_id IS NOT NULL)
			AND s.stock_id NOT IN (SELECT stock_id FROM product_recall_items WHERE status = 'quarantined' AND stock_id IS NOT NULL)";
		$t = $this->pdo->quote($this->today);
		$t3 = $this->pdo->quote(date('Y-m-d', strtotime('+3 day')));
		$stockIdx = [];

		foreach ($this->Rows("SELECT s.product_id, p.name, s.best_before_date AS bbd, SUM(s.amount) AS qty, SUM(s.amount * IFNULL(s.price, 0)) AS value, GROUP_CONCAT(s.stock_id) AS sids, l.branch_id, b.name AS branch
			FROM stock s JOIN products p ON p.id = s.product_id JOIN locations l ON l.id = s.location_id LEFT JOIN branches b ON b.id = l.branch_id
			WHERE s.best_before_date <= $t3 AND s.best_before_date >= '2000-01-01' AND IFNULL(l.is_transit, 0) = 0 AND $excluded $stockB
			GROUP BY s.product_id, s.best_before_date, l.branch_id") as $r)
		{
			$expired = $r['bbd'] < $this->today;
			$uid = ($expired ? 'expired:' : 'expiring:') . $r['product_id'] . ':' . $r['bbd'] . ':' . $r['branch_id'];
			foreach (explode(',', (string) $r['sids']) as $sid)
			{
				$stockIdx[$sid] = $uid;
			}
			$days = intval(round((strtotime($r['bbd']) - strtotime($this->today)) / 86400));
			$out[$uid] = $this->D($uid, $expired ? 'Vencido en stock' : 'Vence pronto', 'quality', $r['name'],
				$this->Qty($r['qty']) . ' u. · ' . ($expired ? 'venció ' : 'vence ') . $this->Day($r['bbd']), $r,
				floatval($r['value']), $expired ? 'confirmed' : 'estimated', null, $r['bbd'] . ' 23:59:59', null,
				$expired ? 'Retiro pendiente' : ($days === 0 ? 'Vence hoy' : "Vence en $days d"), $expired ? 'Registrar merma' : 'Rebajar o trasladar',
				$expired ? '/waste/report' : '/quality', null, $expired ? 'critical' : ($days <= 1 ? 'high' : 'medium'), true, $expired ? 'Producto vencido a la venta' : null);
		}

		foreach ($this->Rows("SELECT w.id, IFNULL(w.case_number, 'MRM-' || w.id) AS code, IFNULL(p.name, '#' || w.product_id) AS name, w.amount, w.total_cost, w.created_timestamp, w.branch_id, b.name AS branch, r.name AS reason
			FROM waste_records w LEFT JOIN products p ON p.id = w.product_id LEFT JOIN waste_reasons r ON r.id = w.waste_reason_id LEFT JOIN branches b ON b.id = w.branch_id
			WHERE w.status = 'pending_approval'" . $this->B('w.branch_id')) as $r)
		{
			$uid = 'waste:' . $r['id'];
			$out[$uid] = $this->D($uid, 'Merma por aprobar', 'waste', $r['code'] . ' · ' . $r['name'], $this->Qty($r['amount']) . ' u. · ' . ($r['reason'] ?? 'sin causal'), $r,
				floatval($r['total_cost']), 'confirmed', null, null, $r['created_timestamp'], 'Esperando aprobación', 'Aprobar o rechazar', '/waste/approvals', ['waste', $r['id']], 'high', false,
				'Stock sobrestimado hasta aprobarla');
		}

		foreach ($this->Rows("SELECT c.id, p.name, c.theoretical_stock, c.counted_stock, c.difference_cost, c.executed_date, c.branch_id, b.name AS branch
			FROM stock_cycle_counts c JOIN products p ON p.id = c.product_id LEFT JOIN branches b ON b.id = c.branch_id
			WHERE c.status = 'discrepancy'" . $this->B('c.branch_id')) as $r)
		{
			$uid = 'count:' . $r['id'];
			$out[$uid] = $this->D($uid, 'Ajuste de inventario', 'counts', $r['name'], 'teórico ' . $this->Qty($r['theoretical_stock']) . ' · contado ' . $this->Qty($r['counted_stock']), $r,
				abs(floatval($r['difference_cost'])), 'confirmed', null, null, $r['executed_date'], 'Diferencia sin ajustar', 'Aprobar ajuste', '/waste#tab-conteos', ['adjustment', $r['id']], 'medium', false,
				'Kardex distinto al físico');
		}

		foreach ($this->Rows("SELECT q.id, q.stock_id, p.name, q.reason, q.amount, q.amount * IFNULL(s.price, 0) AS value, q.entered_timestamp, q.branch_id, b.name AS branch
			FROM quarantine_items q JOIN products p ON p.id = q.product_id LEFT JOIN stock s ON s.stock_id = q.stock_id LEFT JOIN branches b ON b.id = q.branch_id
			WHERE q.status = 'in_quarantine'" . $this->B('q.branch_id')) as $r)
		{
			$uid = 'quarantine:' . $r['id'];
			$out[$uid] = $this->D($uid, 'Lote en cuarentena', 'quality', $r['name'], $this->Qty($r['amount']) . ' u. · ' . ($r['reason'] ?: 'sin motivo'), $r,
				floatval($r['value']), 'estimated', null, null, $r['entered_timestamp'], 'Bloqueado', 'Liberar, dar de baja o devolver', '/quality', ['quarantine', $r['id']], 'high', true, null);
		}

		$recallIdx = [];
		foreach ($this->Rows("SELECT r.id, r.recall_code, IFNULL(p.name, '') AS name, COUNT(i.id) AS lots, SUM(i.amount) AS qty, r.created_at, i.branch_id, b.name AS branch
			FROM product_recalls r JOIN product_recall_items i ON i.recall_id = r.id LEFT JOIN products p ON p.id = r.product_id LEFT JOIN branches b ON b.id = i.branch_id
			WHERE r.status = 'open' AND i.status = 'quarantined'" . $this->B('i.branch_id') . ' GROUP BY r.id, i.branch_id') as $r)
		{
			$uid = 'recall:' . $r['id'] . ':' . $r['branch_id'];
			$recallIdx[$r['id'] . ':' . $r['branch_id']] = $uid;
			$out[$uid] = $this->D($uid, 'Retiro de producto', 'quality', $r['recall_code'] . ' · ' . $r['name'], $r['lots'] . ' lote(s) · ' . $this->Qty($r['qty']) . ' u. por separar', $r,
				0, null, null, null, $r['created_at'], 'Sin confirmar', 'Separar lotes', '/recalls', null, 'critical', true, 'Bloqueado para la venta');
		}

		$poB = $this->B('po.branch_id');
		foreach ($this->Rows("SELECT po.id, po.order_code, IFNULL(sl.name, '') AS supplier, po.status, po.expected_delivery_date, po.total_estimated_cost, po.created_at, po.branch_id, b.name AS branch
			FROM procurement_orders po LEFT JOIN shopping_locations sl ON sl.id = po.supplier_id LEFT JOIN branches b ON b.id = po.branch_id
			WHERE (po.status = 'draft' OR (po.status IN ('sent', 'partially_received') AND po.expected_delivery_date < $t))$poB") as $r)
		{
			$late = $r['status'] !== 'draft';
			$uid = 'order:' . $r['id'];
			$out[$uid] = $this->D($uid, $late ? 'Orden atrasada' : 'Orden en borrador', 'purchasing', $r['order_code'] . ' · ' . $r['supplier'],
				$late ? 'comprometida ' . $this->Day($r['expected_delivery_date']) : 'generada por MRP', $r, floatval($r['total_estimated_cost']), 'estimated', null,
				$late ? $r['expected_delivery_date'] . ' 23:59:59' : null, $r['created_at'], $late ? 'No entregada' : 'Por aprobar', $late ? 'Reclamar al proveedor' : 'Aprobar orden', '/mrp', ['order', $r['id']],
				$late ? 'high' : 'low', false, $late ? 'Riesgo de quiebre de stock' : null);
		}

		foreach ($this->Rows("SELECT i.id, i.invoice_code, i.invoice_number, IFNULL(sl.name, '') AS supplier, i.amount_at_risk, i.created_timestamp, i.branch_id, b.name AS branch
			FROM supplier_invoices i LEFT JOIN shopping_locations sl ON sl.id = i.supplier_id LEFT JOIN branches b ON b.id = i.branch_id
			WHERE i.status = 'discrepancy'" . $this->B('i.branch_id')) as $r)
		{
			$uid = 'invoice:' . $r['id'];
			$out[$uid] = $this->D($uid, 'Factura con diferencias', 'purchasing', $r['invoice_code'] . ' · ' . $r['supplier'], 'factura ' . $r['invoice_number'], $r,
				floatval($r['amount_at_risk']), 'confirmed', null, null, $r['created_timestamp'], 'Por conciliar', 'Conciliar antes de pagar', '/mrp#invoices', null, 'medium', false, 'Bloquea el pago');
		}

		foreach ($this->Rows("SELECT sr.id, IFNULL(sr.case_number, 'DEV-' || sr.id) AS code, IFNULL(sl.name, '') AS supplier, sr.return_reason, sr.credit_requested_amount, sr.created_timestamp, sr.branch_id, b.name AS branch
			FROM supplier_returns sr LEFT JOIN shopping_locations sl ON sl.id = sr.supplier_id LEFT JOIN branches b ON b.id = sr.branch_id
			WHERE sr.status = 'pending_credit'" . $this->B('sr.branch_id')) as $r)
		{
			$uid = 'credit:' . $r['id'];
			$out[$uid] = $this->D($uid, 'Nota de crédito', 'purchasing', $r['code'] . ' · ' . $r['supplier'], $r['return_reason'] ?: '', $r,
				floatval($r['credit_requested_amount']), 'confirmed', null, null, $r['created_timestamp'], 'Por cobrar', 'Gestionar reclamo', '/waste#tab-recuperacion', ['supplier_return', $r['id']], 'low', false, null);
		}

		foreach ($this->Rows("SELECT t.id, t.transfer_code, bf.name AS f, bt.name AS tt, t.sent_at, t.total_sent_cost, t.to_branch_id AS branch_id, bt.name AS branch
			FROM stock_transfers t JOIN branches bf ON bf.id = t.from_branch_id JOIN branches bt ON bt.id = t.to_branch_id
			WHERE t.status = 'in_transit' AND t.sent_at < " . $this->pdo->quote(date('Y-m-d H:i:s', strtotime('-2 day')))
			. ($this->branch ? " AND (t.from_branch_id = {$this->branch} OR t.to_branch_id = {$this->branch})" : '')) as $r)
		{
			$uid = 'transfer:' . $r['id'];
			$out[$uid] = $this->D($uid, 'Traslado > 48 h', 'operations', $r['transfer_code'] . ' · ' . $r['f'] . ' → ' . $r['tt'], 'enviado ' . $this->Day($r['sent_at']), $r,
				floatval($r['total_sent_cost']), 'estimated', null, null, $r['sent_at'], 'En tránsito', 'Confirmar recepción', '/transfers', ['transfer', $r['id']], 'medium', false, 'Stock fuera de inventario disponible');
		}

		foreach ($this->Rows("SELECT co.id, co.order_code, c.name AS customer, co.status, co.requested_date, co.created_at, co.branch_id, b.name AS branch,
				(SELECT SUM(i.amount * i.unit_price) FROM customer_order_items i WHERE i.order_id = co.id) AS value
			FROM customer_orders co JOIN customers c ON c.id = co.customer_id LEFT JOIN branches b ON b.id = co.branch_id
			WHERE co.status IN ('confirmed', 'picking', 'picked', 'packed')" . $this->B('co.branch_id')) as $r)
		{
			$uid = 'customer_order:' . $r['id'];
			$labels = ['confirmed' => 'Por preparar', 'picking' => 'En preparación', 'picked' => 'Por empacar', 'packed' => 'Por despachar'];
			$out[$uid] = $this->D($uid, 'Pedido de cliente', 'operations', $r['order_code'] . ' · ' . $r['customer'], $r['requested_date'] ? 'entrega ' . $this->Day($r['requested_date']) : '', $r,
				floatval($r['value']), 'estimated', null, $r['requested_date'] ? $r['requested_date'] . ' 23:59:59' : null, $r['created_at'], $labels[$r['status']], 'Preparar y despachar', '/orders', null, 'low', false,
				'Stock reservado');
		}

		foreach ($this->Rows("SELECT w.id, IFNULL(w.case_number, 'MRM-' || w.id) AS code, IFNULL(p.name, '') AS name, w.due_date, w.total_cost, w.created_timestamp, w.branch_id, b.name AS branch,
				NULLIF(TRIM(IFNULL(u.first_name, '') || ' ' || IFNULL(u.last_name, '')), '') AS owner
			FROM waste_records w LEFT JOIN products p ON p.id = w.product_id LEFT JOIN branches b ON b.id = w.branch_id LEFT JOIN users u ON u.id = w.investigator_user_id
			WHERE w.status = 'approved' AND w.workflow_status NOT IN ('closed', 'verified') AND w.due_date IS NOT NULL AND w.due_date < $t" . $this->B('w.branch_id')) as $r)
		{
			$uid = 'investigation:' . $r['id'];
			$out[$uid] = $this->D($uid, 'Investigación vencida', 'other', $r['code'] . ' · ' . $r['name'], 'plazo ' . $this->Day($r['due_date']), $r,
				0, null, $r['owner'], $r['due_date'] . ' 23:59:59', $r['created_timestamp'], 'Fuera de plazo', 'Cerrar causa raíz', '/waste', ['waste', $r['id']], 'medium', false, null);
		}

		// Alertas críticas o vencidas (las de cierre por aprobar entran también: la decisión es del gestor)
		$now = $this->pdo->quote($this->now);
		foreach ($this->Rows("SELECT a.id, a.rule_code, a.dedupe_key, a.severity, a.priority, a.title, a.detail, a.value, a.status, a.due_at, a.created_at, a.branch_id, b.name AS branch,
				NULLIF(TRIM(IFNULL(u.first_name, '') || ' ' || IFNULL(u.last_name, '')), '') AS owner
			FROM dairy_alerts a LEFT JOIN branches b ON b.id = a.branch_id LEFT JOIN users u ON u.id = a.owner_user_id
			WHERE a.status IN (" . \Grocy\Services\AlertCenterService::In(\Grocy\Services\AlertCenterService::ACTIVE) . ") AND (a.priority IN ('P1', 'P2') OR a.status IN ('pending_approval', 'waiting_approval') OR a.due_at < $now)" . $this->B('a.branch_id')) as $r)
		{
			$parts = explode(':', (string) $r['dedupe_key']);
			$target = null;
			if ($r['rule_code'] === 'expiring_lots' && isset($parts[1]))
			{
				$target = $stockIdx[$parts[1]] ?? null;
			}
			elseif ($r['rule_code'] === 'recall_unconfirmed' && isset($parts[1], $parts[2]))
			{
				$target = $recallIdx[$parts[1] . ':' . $parts[2]] ?? null;
			}
			if ($target && isset($out[$target]))
			{
				$out[$target]['linked'][] = 'alert:' . $r['id'];
				continue;
			}
			$uid = 'alert:' . $r['id'];
			$states = \Grocy\Services\AlertCenterService::STATUS;
			$food = in_array($r['rule_code'], ['cold_chain_breach', 'expiring_lots', 'recall_unconfirmed', 'quarantine_stale'], true);
			$out[$uid] = $this->D($uid, 'Alerta', 'alerts', $r['title'], $r['detail'] ?? '', $r, floatval($r['value']), $r['value'] > 0 ? 'estimated' : null, $r['owner'], $r['due_at'], $r['created_at'],
				$states[$r['status']] ?? $r['status'], $r['status'] === 'pending_approval' ? 'Aprobar cierre' : ($r['status'] === 'waiting_approval' ? 'Decidir aprobación' : ($r['owner'] ? 'Dar seguimiento' : 'Asignar responsable')), '/alerts?q=ALR-' . $r['id'], null,
				$r['priority'] === 'P1' ? 'critical' : ($r['priority'] === 'P2' ? 'high' : 'medium'), $food, null);
			if (in_array($r['status'], ['in_progress', 'investigating', 'waiting_info'], true))
			{
				$out[$uid]['state'] = $out[$uid]['state'] === 'overdue' ? 'overdue' : 'in_progress';
			}
		}

		// Tareas de piso vencidas (plazo + margen de escalamiento): una decisión por tipo de tarea y rol, no una por tarea repetida
		$esc = $this->pdo->quote(date('Y-m-d H:i:s', time() - 60 * intval(GROCY_STORE_TASK_ESCALATION_MINUTES)));
		$types = ['cold_chain_check' => 'Controles de temperatura', 'fefo_sweep' => 'Rotaciones FEFO', 'stock_count' => 'Conteos de piso', 'corrective_action' => 'Acciones correctivas'];
		foreach ($this->Rows("SELECT t.task_type, IFNULL(t.assigned_role, '') AS role, t.branch_id, b.name AS branch, COUNT(*) AS n, MIN(t.due_time) AS oldest, MAX(t.due_time) AS latest,
				SUM(CASE WHEN DATE(t.due_time) = " . $this->pdo->quote($this->today) . " THEN 1 ELSE 0 END) AS today
			FROM workforce_store_tasks t LEFT JOIN branches b ON b.id = t.branch_id
			WHERE t.status = 'pending' AND t.due_time IS NOT NULL AND t.due_time < $esc" . $this->B('t.branch_id') . ' GROUP BY t.task_type, t.assigned_role, t.branch_id') as $r)
		{
			$uid = 'tasks_overdue:' . $r['task_type'] . ':' . $r['role'] . ':' . $r['branch_id'];
			$out[$uid] = $this->D($uid, 'Tareas no ejecutadas', 'operations', ($types[$r['task_type']] ?? 'Tareas de piso') . ' sin ejecutar',
				$r['n'] . ' tarea(s) · ' . $r['today'] . ' de hoy · desde ' . $this->Day($r['oldest']), $r, 0, null, $r['role'] ? 'Rol ' . $r['role'] : null, $r['latest'], null,
				$r['n'] . ' vencida(s)', 'Reasignar o verificar en sitio', '/workforce', null, intval($r['n']) >= 5 ? 'high' : 'medium', $r['task_type'] === 'cold_chain_check', null);
			$out[$uid]['tasks'] = intval($r['n']);
		}

		$out = array_values($out);
		foreach ($out as &$d)
		{
			$this->Score($d);
		}
		unset($d);
		usort($out, fn($a, $b) => [$b['score'], $b['value']] <=> [$a['score'], $a['value']]);
		return $out;
	}

	private function D(string $uid, string $type, string $cat, string $subject, string $detail, array $r, float $value, ?string $valueKind, ?string $owner, ?string $due, ?string $opened, string $status, string $action, string $href, ?array $doc, string $severity, bool $food, ?string $blocking): array
	{
		$state = ($due && $due < $this->now) ? 'overdue' : 'open';
		return [
			'uid' => $uid, 'type' => $type, 'cat' => $cat, 'subject' => $subject, 'detail' => $detail,
			'branch_id' => isset($r['branch_id']) ? intval($r['branch_id']) : null, 'branch' => $r['branch'] ?? null,
			'value' => round($value, 2), 'value_kind' => $value > 0 ? $valueKind : null,
			'owner' => $owner, 'due' => $due, 'opened' => $opened, 'status' => $status, 'state' => $state,
			'action' => $action, 'href' => $href, 'doc' => $doc, 'severity' => $severity, 'food' => $food, 'blocking' => $blocking, 'linked' => []
		];
	}

	private function Score(array &$d): void
	{
		$f = [];
		$f[] = ['Severidad ' . ['critical' => 'crítica', 'high' => 'alta', 'medium' => 'media', 'low' => 'baja'][$d['severity']], ['critical' => 30, 'high' => 20, 'medium' => 10, 'low' => 4][$d['severity']]];
		if ($d['food'])
		{
			$f[] = ['Seguridad alimentaria', 25];
		}
		if ($d['due'])
		{
			$late = (time() - strtotime($d['due'])) / 86400;
			if ($late > 0)
			{
				$f[] = ['Vencida hace ' . max(1, intval(ceil($late))) . ' d', 20 + min(5, intval(floor($late)))];
			}
			elseif (substr($d['due'], 0, 10) === $this->today)
			{
				$f[] = ['Vence hoy', 12];
			}
			elseif ($late > -3)
			{
				$f[] = ['Vence en ≤ 3 días', 6];
			}
		}
		if ($d['value'] > 0)
		{
			$f[] = ['Impacto $' . number_format($d['value'], 2), round(min(15, 5 * log10(1 + $d['value'])), 1)];
		}
		if ($d['blocking'])
		{
			$f[] = [$d['blocking'], 8];
		}
		if ($d['opened'] && $d['state'] !== 'overdue')
		{
			$age = (time() - strtotime($d['opened'])) / 86400;
			if ($age >= 1)
			{
				$f[] = ['Pendiente hace ' . intval($age) . ' d', min(10, 2 * intval($age))];
			}
		}
		$d['score'] = intval(min(100, round(array_sum(array_column($f, 1)))));
		$d['factors'] = $f;
		$d['priority'] = $d['score'] >= 60 ? 'Crítica' : ($d['score'] >= 40 ? 'Alta' : ($d['score'] >= 20 ? 'Media' : 'Baja'));
		$d['tone'] = $d['score'] >= 60 ? 'danger' : ($d['score'] >= 40 ? 'warning' : ($d['score'] >= 20 ? 'info' : 'neutral'));
	}

	// ---------------------------------------------------------------- Ejecución y equipo

	private function Execution(bool $showPeople): array
	{
		$t = $this->pdo->quote($this->today);
		$rows = $this->Rows("SELECT t.id, t.title, t.task_type, t.assigned_role, t.status, t.due_time, t.completed_at, t.assigned_user_id, t.assigned_employee_id,
				NULLIF(TRIM(IFNULL(u.first_name, '') || ' ' || IFNULL(u.last_name, '')), '') AS person
			FROM workforce_store_tasks t LEFT JOIN users u ON u.id = t.assigned_user_id
			WHERE DATE(t.due_time) = $t" . $this->B('t.branch_id') . ' ORDER BY t.due_time');
		$shifts = $this->Shifts();
		$seg = ['shift' => [], 'role' => [], 'person' => []];
		$tot = ['done' => 0, 'in_progress' => 0, 'pending' => 0, 'overdue' => 0];
		$upcoming = [];
		foreach ($rows as $r)
		{
			$k = $r['status'] === 'completed' ? 'done' : ($r['status'] === 'in_progress' ? 'in_progress' : ($r['due_time'] < $this->now ? 'overdue' : 'pending'));
			$tot[$k]++;
			$keys = ['shift' => $this->ShiftOf(substr($r['due_time'], 11, 5), $shifts), 'role' => $r['assigned_role'] ?: 'Sin rol'];
			if ($showPeople)
			{
				$keys['person'] = $r['person'] ?? 'Sin persona asignada';
			}
			foreach ($keys as $g => $name)
			{
				$seg[$g][$name] = $seg[$g][$name] ?? ['done' => 0, 'in_progress' => 0, 'pending' => 0, 'overdue' => 0];
				$seg[$g][$name][$k]++;
			}
			if ($k === 'pending' && count($upcoming) < 4)
			{
				$upcoming[] = $r;
			}
		}
		$backlog = intval($this->Val("SELECT COUNT(*) FROM workforce_store_tasks WHERE status = 'pending' AND due_time < $t" . $this->B('branch_id')));
		$total = array_sum($tot);
		return $tot + [
			'total' => $total, 'pct' => $total ? round(100 * $tot['done'] / $total) : null, 'backlog' => $backlog,
			'segments' => $seg, 'upcoming' => $upcoming, 'show_people' => $showPeople
		];
	}

	private function Team(array $exec, array $decisions): array
	{
		$t = $this->pdo->quote($this->today);
		$scheduled = $this->Rows("SELECT IFNULL(NULLIF(s.role, ''), IFNULL(NULLIF(s.department, ''), 'Sin rol')) AS role, COUNT(DISTINCT s.employee_id) AS n,
				COUNT(DISTINCT CASE WHEN a.clock_in IS NOT NULL AND a.clock_out IS NULL THEN s.employee_id END) AS present
			FROM employee_shifts s LEFT JOIN employee_attendance a ON a.shift_id = s.id
			WHERE s.shift_date = $t AND IFNULL(s.status, '') NOT IN ('cancelled', 'absent')" . $this->B('s.branch_id') . ' GROUP BY 1');
		$hasAttendance = intval($this->Val("SELECT COUNT(*) FROM employee_attendance a JOIN employee_shifts s ON s.id = a.shift_id WHERE s.shift_date = $t" . $this->B('s.branch_id'))) > 0;
		$people = array_column($scheduled, 'n', 'role');
		$present = array_column($scheduled, 'present', 'role');

		$roles = [];
		foreach ($exec['segments']['role'] as $role => $c)
		{
			$open = $c['pending'] + $c['overdue'] + $c['in_progress'];
			$n = intval($people[$role] ?? 0);
			$roles[] = ['role' => $role, 'assigned' => array_sum($c), 'done' => $c['done'], 'open' => $open, 'overdue' => $c['overdue'],
				'people' => $n ?: null, 'present' => $hasAttendance ? intval($present[$role] ?? 0) : null,
				'per_person' => $n ? round($open / $n, 1) : null, 'overload' => $n && $open / $n > 8];
		}
		usort($roles, fn($a, $b) => $b['open'] <=> $a['open']);

		$unassigned = intval($this->Val("SELECT COUNT(*) FROM workforce_store_tasks WHERE status = 'pending' AND assigned_user_id IS NULL AND assigned_employee_id IS NULL AND DATE(due_time) <= $t" . $this->B('branch_id')));
		$uncovered = array_values(array_filter($decisions, fn($d) => $d['score'] >= 40 && $d['owner'] === null && in_array($d['cat'], ['alerts', 'quality'], true)));
		return [
			'scheduled' => array_sum($people), 'has_schedule' => count($scheduled) > 0, 'has_attendance' => $hasAttendance,
			'present' => $hasAttendance ? array_sum($present) : null, 'roles' => $roles, 'unassigned' => $unassigned,
			'uncovered' => count($uncovered), 'uncovered_top' => array_slice($uncovered, 0, 3)
		];
	}

	// ---------------------------------------------------------------- Alertas, aprobaciones, riesgos

	private function AlertsSummary(): array
	{
		$b = $this->B('branch_id');
		$now = $this->pdo->quote($this->now);
		$t = $this->pdo->quote($this->today . ' 00:00:00');
		$r = $this->Row("SELECT
				SUM(CASE WHEN status IN (" . \Grocy\Services\AlertCenterService::In(\Grocy\Services\AlertCenterService::ACTIVE) . ") AND priority = 'P1' THEN 1 ELSE 0 END) AS critical,
				SUM(CASE WHEN status IN (" . \Grocy\Services\AlertCenterService::In(\Grocy\Services\AlertCenterService::WORKING) . ") AND due_at < $now THEN 1 ELSE 0 END) AS overdue,
				SUM(CASE WHEN status IN (" . \Grocy\Services\AlertCenterService::In(\Grocy\Services\AlertCenterService::ACTIVE) . ") AND (priority IN ('P1', 'P2') OR (due_at < $now AND status != 'pending_approval')) THEN 1 ELSE 0 END) AS action,
				SUM(CASE WHEN status IN (" . \Grocy\Services\AlertCenterService::In(\Grocy\Services\AlertCenterService::ACTIVE) . ") THEN 1 ELSE 0 END) AS open,
				SUM(CASE WHEN created_at >= $t THEN 1 ELSE 0 END) AS new,
				SUM(CASE WHEN status = 'closed' AND resolved_at >= $t THEN 1 ELSE 0 END) AS resolved,
				SUM(CASE WHEN status IN (" . \Grocy\Services\AlertCenterService::In(\Grocy\Services\AlertCenterService::WORKING) . ") AND owner_user_id IS NULL THEN 1 ELSE 0 END) AS unowned
			FROM dairy_alerts WHERE 1 = 1$b");
		return array_map('intval', $r ?: []);
	}

	private function Approvals(): array
	{
		$items = [];
		foreach ($this->Rows("SELECT w.id, IFNULL(w.case_number, 'MRM-' || w.id) AS code, IFNULL(p.name, '#' || w.product_id) AS name, w.amount, w.total_cost, w.created_timestamp, w.reported_by_user_id, p.picture_file_name AS picture,
				w.evidence_photo_path, w.waste_reason_id, r.name AS reason, IFNULL(r.requires_evidence, 0) AS requires_evidence, b.name AS branch,
				NULLIF(TRIM(IFNULL(u.first_name, '') || ' ' || IFNULL(u.last_name, '')), '') AS reporter,
				(SELECT l.created_timestamp FROM waste_action_logs l WHERE l.waste_record_id = w.id AND l.action_title = 'Información solicitada' ORDER BY l.id DESC LIMIT 1) AS info_requested
			FROM waste_records w LEFT JOIN products p ON p.id = w.product_id LEFT JOIN waste_reasons r ON r.id = w.waste_reason_id LEFT JOIN branches b ON b.id = w.branch_id LEFT JOIN users u ON u.id = w.reported_by_user_id
			WHERE w.status = 'pending_approval'" . $this->B('w.branch_id')) as $r)
		{
			$missing = [];
			if (empty($r['waste_reason_id']))
			{
				$missing[] = 'causal';
			}
			if (floatval($r['amount']) <= 0)
			{
				$missing[] = 'cantidad';
			}
			if (intval($r['requires_evidence']) === 1 && empty($r['evidence_photo_path']))
			{
				$missing[] = 'evidencia';
			}
			$items[] = ['kind' => 'waste', 'id' => intval($r['id']), 'label' => 'Merma', 'subject' => $r['code'] . ' · ' . $r['name'], 'qty' => $r['amount'], 'value' => floatval($r['total_cost']),
				'reason' => $r['reason'], 'evidence' => !empty($r['evidence_photo_path']), 'evidence_required' => intval($r['requires_evidence']) === 1, 'since' => $r['created_timestamp'],
				'by' => $r['reporter'], 'self' => intval($r['reported_by_user_id']) === intval(GROCY_USER_ID), 'missing' => $missing, 'branch' => $r['branch'],
				'status' => $r['info_requested'] ? 'Información solicitada' : 'Esperando aprobación', 'href' => '/waste/approvals', 'code' => $r['code'], 'picture' => $r['picture'], 'photo' => $r['evidence_photo_path'] ?: null];
		}
		foreach ($this->Rows("SELECT c.id, p.name, c.difference_amount, c.difference_cost, c.executed_date, c.executed_by_user_id, b.name AS branch, p.picture_file_name AS picture,
				NULLIF(TRIM(IFNULL(u.first_name, '') || ' ' || IFNULL(u.last_name, '')), '') AS counter
			FROM stock_cycle_counts c JOIN products p ON p.id = c.product_id LEFT JOIN branches b ON b.id = c.branch_id LEFT JOIN users u ON u.id = c.executed_by_user_id
			WHERE c.status = 'discrepancy'" . $this->B('c.branch_id')) as $r)
		{
			$items[] = ['kind' => 'count', 'id' => intval($r['id']), 'label' => 'Ajuste de inventario', 'subject' => $r['name'], 'qty' => $r['difference_amount'], 'value' => abs(floatval($r['difference_cost'])),
				'reason' => 'Diferencia de conteo', 'evidence' => true, 'evidence_required' => false, 'since' => $r['executed_date'], 'by' => $r['counter'],
				'self' => intval($r['executed_by_user_id']) === intval(GROCY_USER_ID), 'missing' => [], 'branch' => $r['branch'], 'status' => 'Diferencia sin ajustar', 'href' => '/waste#tab-conteos', 'code' => 'CNT-' . $r['id'], 'picture' => $r['picture'], 'photo' => null];
		}
		foreach ($this->Rows("SELECT a.id, a.title, a.value, a.resolution, a.updated_at, a.resolved_by_user_id, b.name AS branch,
				NULLIF(TRIM(IFNULL(u.first_name, '') || ' ' || IFNULL(u.last_name, '')), '') AS resolver
			FROM dairy_alerts a LEFT JOIN branches b ON b.id = a.branch_id LEFT JOIN users u ON u.id = a.resolved_by_user_id
			WHERE a.status = 'pending_approval'" . $this->B('a.branch_id')) as $r)
		{
			$items[] = ['kind' => 'alert', 'id' => intval($r['id']), 'label' => 'Cierre de alerta', 'subject' => $r['title'], 'qty' => null, 'value' => floatval($r['value']),
				'reason' => $r['resolution'], 'evidence' => !empty($r['resolution']), 'evidence_required' => false, 'since' => $r['updated_at'], 'by' => $r['resolver'],
				'self' => intval($r['resolved_by_user_id']) === intval(GROCY_USER_ID), 'missing' => [], 'branch' => $r['branch'], 'status' => 'Cierre por aprobar', 'href' => '/alerts', 'code' => 'ALR-' . $r['id'], 'picture' => null, 'photo' => null];
		}
		$drafts = intval($this->Val("SELECT COUNT(*) FROM procurement_orders WHERE status = 'draft'" . $this->B('branch_id')));
		$invoices = intval($this->Val("SELECT COUNT(*) FROM supplier_invoices WHERE status = 'discrepancy'" . $this->B('branch_id')));

		usort($items, fn($a, $b) => strcmp((string) $a['since'], (string) $b['since']));
		$oldest = $items ? $items[0]['since'] : null;
		usort($items, fn($a, $b) => [count($b['missing']) === 0, $b['value']] <=> [count($a['missing']) === 0, $a['value']]);
		$count = fn($k) => count(array_filter($items, fn($i) => $i['kind'] === $k));
		return [
			'items' => $items, 'waste' => $count('waste'), 'counts' => $count('count'), 'alerts' => $count('alert'),
			'orders' => $drafts, 'invoices' => $invoices, 'total' => count($items) + $drafts + $invoices, 'oldest' => $oldest
		];
	}

	private function Risks(): array
	{
		$t = $this->pdo->quote($this->today);
		$t3 = $this->pdo->quote(date('Y-m-d', strtotime('+3 day')));
		$cold = $this->Row("SELECT COUNT(DISTINCT c.location_id) AS locations, COUNT(*) AS readings, MAX(c.recorded_timestamp) AS last
			FROM cold_chain_temperatures c WHERE c.status != 'normal' AND DATE(c.recorded_timestamp) = $t" . $this->B('c.branch_id'));
		$exp = $this->Row("SELECT COUNT(DISTINCT s.stock_id) AS lots, SUM(s.amount * IFNULL(s.price, 0)) AS value
			FROM stock s JOIN locations l ON l.id = s.location_id WHERE s.best_before_date BETWEEN $t AND $t3 AND IFNULL(l.is_transit, 0) = 0" . $this->B('l.branch_id'));
		$q = $this->Row("SELECT COUNT(*) AS lots, SUM(q.amount * IFNULL(s.price, 0)) AS value FROM quarantine_items q LEFT JOIN stock s ON s.stock_id = q.stock_id WHERE q.status = 'in_quarantine'" . $this->B('q.branch_id'));
		$counts = $this->Row("SELECT SUM(CASE WHEN status = 'pending' AND scheduled_date <= $t THEN 1 ELSE 0 END) AS due, SUM(CASE WHEN status = 'discrepancy' THEN 1 ELSE 0 END) AS diff FROM stock_cycle_counts WHERE 1 = 1" . $this->B('branch_id'));
		$price = $this->Row("SELECT COUNT(*) AS n, SUM(IFNULL(impact_amount, 0)) AS value FROM price_discrepancies WHERE status NOT IN ('closed', 'rejected')" . $this->B('branch_id'));
		$receipts = $this->Row("SELECT COUNT(*) AS n, SUM(total_rejected_cost) AS value FROM procurement_receipts WHERE total_rejected_cost > 0 AND received_at >= " . $this->pdo->quote(date('Y-m-d', strtotime('-7 day'))) . $this->B('branch_id'));
		$returns = $this->Row("SELECT COUNT(*) AS n, SUM(credit_requested_amount) AS value FROM supplier_returns WHERE status = 'pending_credit'" . $this->B('branch_id'));
		$n = fn($r, $k = 'n') => intval($r[$k] ?? 0);
		$v = fn($r) => round(floatval($r['value'] ?? 0), 2);
		return [
			'cold' => ['n' => $n($cold, 'locations'), 'readings' => $n($cold, 'readings'), 'last' => $cold['last'] ?? null],
			'expiring' => ['n' => $n($exp, 'lots'), 'value' => $v($exp)],
			'quarantine' => ['n' => $n($q, 'lots'), 'value' => $v($q)],
			'counts' => ['n' => $n($counts, 'due'), 'diff' => $n($counts, 'diff')],
			'price' => ['n' => $n($price), 'value' => $v($price)],
			'receipts' => ['n' => $n($receipts), 'value' => $v($receipts)],
			'returns' => ['n' => $n($returns), 'value' => $v($returns)]
		];
	}

	private function Prevention(array $r): array
	{
		$o = [
			['n' => $r['cold']['n'], 'sev' => 3, 'icon' => 'fa-temperature-high', 'title' => 'Temperatura fuera de rango hoy', 'detail' => $r['cold']['n'] . ' ubicación(es) · ' . $r['cold']['readings'] . ' lectura(s)', 'value' => null, 'href' => '/quality', 'action' => 'Revisar cadena de frío'],
			['n' => $r['expiring']['n'], 'sev' => 2, 'icon' => 'fa-hourglass-half', 'title' => 'Lotes que vencen en 3 días', 'detail' => $r['expiring']['n'] . ' lote(s) a la venta', 'value' => $r['expiring']['value'], 'href' => '/quality', 'action' => 'Rebajar o trasladar'],
			['n' => $r['quarantine']['n'], 'sev' => 2, 'icon' => 'fa-shield-virus', 'title' => 'Mercancía en cuarentena', 'detail' => $r['quarantine']['n'] . ' lote(s) bloqueado(s)', 'value' => $r['quarantine']['value'], 'href' => '/quality', 'action' => 'Dictaminar'],
			['n' => $r['counts']['n'], 'sev' => 1, 'icon' => 'fa-list-check', 'title' => 'Conteos programados sin ejecutar', 'detail' => $r['counts']['n'] . ' conteo(s) para hoy o antes', 'value' => null, 'href' => '/waste#tab-conteos', 'action' => 'Contar'],
			['n' => $r['receipts']['n'], 'sev' => 1, 'icon' => 'fa-truck-ramp-box', 'title' => 'Diferencias de recepción (7 días)', 'detail' => $r['receipts']['n'] . ' recepción(es) con rechazo', 'value' => $r['receipts']['value'], 'href' => '/mrp', 'action' => 'Reclamar'],
			['n' => $r['price']['n'], 'sev' => 1, 'icon' => 'fa-tags', 'title' => 'Errores de precio abiertos', 'detail' => $r['price']['n'] . ' diferencia(s) POS / góndola', 'value' => $r['price']['value'], 'href' => '/waste#tab-precios', 'action' => 'Corregir precio'],
			['n' => $r['returns']['n'], 'sev' => 0, 'icon' => 'fa-file-invoice-dollar', 'title' => 'Devoluciones a proveedor sin crédito', 'detail' => $r['returns']['n'] . ' reclamo(s) pendiente(s)', 'value' => $r['returns']['value'], 'href' => '/waste#tab-recuperacion', 'action' => 'Gestionar'],
		];
		$o = array_values(array_filter($o, fn($x) => $x['n'] > 0));
		usort($o, fn($a, $b) => [$b['sev'], $b['value'] ?? 0] <=> [$a['sev'], $a['value'] ?? 0]);
		return array_slice($o, 0, 5);
	}

	// ---------------------------------------------------------------- Series

	/**
	 * Decisiones abiertas/nuevas/resueltas por día, reconstruidas con fechas reales de apertura y cierre.
	 * Cobertura: mermas por aprobar, alertas críticas o con plazo, cuarentenas y reclamos a proveedor (HISTORY_SOURCES).
	 * Fuera del histórico: lotes vencidos en stock, conteos, órdenes y pedidos (sin fecha de cierre) y tareas de piso (se miden en Cumplimiento).
	 */
	private function Intervals(int $days = 40): array
	{
		$from = $this->pdo->quote(date('Y-m-d', strtotime("-$days day")));
		// [apertura, cierre, plazo]: solo las alertas tienen plazo (due_at); el resto nunca cuenta como «vencida»
		$sql = [
			"SELECT created_timestamp AS o, CASE WHEN status != 'pending_approval' THEN approved_timestamp END AS c, NULL AS du FROM waste_records
				WHERE (status = 'pending_approval' OR (approved_timestamp > datetime(created_timestamp, '+1 minute'))) AND (approved_timestamp IS NULL OR approved_timestamp >= $from)" . $this->B('branch_id'),
			"SELECT created_at AS o, CASE WHEN status IN ('closed', 'dismissed') THEN IFNULL(resolved_at, updated_at) END AS c, due_at AS du FROM dairy_alerts
				WHERE (severity = 'danger' OR due_at IS NOT NULL) AND (resolved_at IS NULL OR resolved_at >= $from)" . $this->B('branch_id'),
			"SELECT entered_timestamp AS o, CASE WHEN status != 'in_quarantine' THEN resolved_timestamp END AS c, NULL AS du FROM quarantine_items WHERE (resolved_timestamp IS NULL OR resolved_timestamp >= $from)" . $this->B('branch_id'),
			"SELECT created_timestamp AS o, CASE WHEN status != 'pending_credit' THEN resolved_timestamp END AS c, NULL AS du FROM supplier_returns WHERE (resolved_timestamp IS NULL OR resolved_timestamp >= $from)" . $this->B('branch_id'),
		];
		$out = [];
		foreach ($sql as $s)
		{
			foreach ($this->Rows($s) as $r)
			{
				if ($r['o'])
				{
					$out[] = [$r['o'], $r['c'], $r['du']];
				}
			}
		}
		return $out;
	}

	private function History(int $days): array
	{
		$iv = $this->Intervals($days + 10);
		$rows = [];
		for ($i = $days - 1; $i >= 0; $i--)
		{
			$d = date('Y-m-d', strtotime("-$i day"));
			$start = "$d 00:00:00";
			$end = $i === 0 ? $this->now : "$d 23:59:59";
			$open = $new = $res = $late = 0;
			foreach ($iv as [$o, $c, $du])
			{
				if ($o <= $end && ($c === null || $c > $end))
				{
					$open++;
					if ($du !== null && $du < $end)
					{
						$late++;
					}
				}
				if ($o >= $start && $o <= $end)
				{
					$new++;
				}
				if ($c !== null && $c >= $start && $c <= $end)
				{
					$res++;
				}
			}
			$rows[] = ['d' => $d, 'open' => $open, 'new' => $new, 'resolved' => $res, 'overdue' => $late];
		}
		return $rows;
	}

	private function OpenAt(array $iv, string $at): int
	{
		return count(array_filter($iv, fn($x) => $x[0] <= $at && ($x[1] === null || $x[1] > $at)));
	}

	/**
	 * Eventos de hoy por hora y severidad (reglas fijas, documentadas en la vista):
	 *  crítico: alertas «danger» y lecturas de temperatura críticas · alto: alertas «warning», lecturas en advertencia y controles de frío vencidos ·
	 *  medio: mermas registradas y otras tareas de piso vencidas · bajo: alertas informativas y mermas esperando aprobación.
	 */
	private function Hourly(): array
	{
		$t = $this->pdo->quote($this->today);
		$now = $this->pdo->quote($this->now);
		$h = fn($col) => "CAST(strftime('%H', $col) AS INTEGER)";
		$sql = [
			"SELECT {$h('created_at')}, CASE priority WHEN 'P1' THEN 'critical' WHEN 'P2' THEN 'high' WHEN 'P3' THEN 'medium' ELSE 'low' END, COUNT(*) FROM dairy_alerts WHERE DATE(created_at) = $t" . $this->B('branch_id') . ' GROUP BY 1, 2',
			"SELECT {$h('recorded_timestamp')}, CASE status WHEN 'critical' THEN 'critical' ELSE 'high' END, COUNT(*) FROM cold_chain_temperatures WHERE DATE(recorded_timestamp) = $t AND status != 'normal'" . $this->B('branch_id') . ' GROUP BY 1, 2',
			"SELECT {$h('created_timestamp')}, CASE status WHEN 'pending_approval' THEN 'low' ELSE 'medium' END, COUNT(*) FROM waste_records WHERE DATE(created_timestamp) = $t AND status != 'rejected'" . $this->B('branch_id') . ' GROUP BY 1, 2',
			"SELECT {$h('due_time')}, CASE task_type WHEN 'cold_chain_check' THEN 'high' ELSE 'medium' END, COUNT(*) FROM workforce_store_tasks WHERE DATE(due_time) = $t AND status = 'pending' AND due_time < $now" . $this->B('branch_id') . ' GROUP BY 1, 2'
		];
		$out = array_fill_keys(['critical', 'high', 'medium', 'low'], array_fill(0, 24, 0));
		foreach ($sql as $s)
		{
			foreach ($this->pdo->query($s)->fetchAll(\PDO::FETCH_NUM) as [$hour, $sev, $n])
			{
				$out[$sev][intval($hour)] += intval($n);
			}
		}
		return $out;
	}

	/**
	 * Hoy contra el mismo día de la semana anterior, ambos cortados a la misma hora (el día parcial nunca se compara con uno completo).
	 */
	private function Comparison(): array
	{
		$elapsed = time() - strtotime($this->today);
		$win = function (int $daysAgo) use ($elapsed) {
			$s = strtotime($this->today) - $daysAgo * 86400;
			return [date('Y-m-d H:i:s', $s), date('Y-m-d H:i:s', $s + $elapsed)];
		};
		$iv = $this->Intervals();
		$calc = function (array $w) use ($iv) {
			[$s, $e] = array_map(fn($x) => $this->pdo->quote($x), $w);
			$b = $this->B('branch_id');
			$tasks = intval($this->Val("SELECT COUNT(*) FROM workforce_store_tasks WHERE status = 'completed' AND completed_at BETWEEN $s AND $e$b"));
			$times = array_merge(
				$this->pdo->query("SELECT (julianday(resolved_at) - julianday(created_at)) * 24 FROM dairy_alerts WHERE status = 'closed' AND resolved_at BETWEEN $s AND $e$b")->fetchAll(\PDO::FETCH_COLUMN),
				$this->pdo->query("SELECT (julianday(approved_timestamp) - julianday(created_timestamp)) * 24 FROM waste_records WHERE approved_timestamp BETWEEN $s AND $e AND approved_timestamp > datetime(created_timestamp, '+1 minute')$b")->fetchAll(\PDO::FETCH_COLUMN)
			);
			$overdue = intval($this->Val("SELECT COUNT(*) FROM dairy_alerts WHERE due_at <= $e AND created_at <= $e AND (resolved_at IS NULL OR resolved_at > $e) AND status != 'dismissed'$b"));
			$closed = intval($this->Val("SELECT COUNT(*) FROM dairy_alerts WHERE status = 'closed' AND resolved_at BETWEEN $s AND $e$b"))
				+ intval($this->Val("SELECT COUNT(*) FROM quarantine_items WHERE status != 'in_quarantine' AND resolved_timestamp BETWEEN $s AND $e$b"))
				+ intval($this->Val("SELECT COUNT(*) FROM supplier_returns WHERE status != 'pending_credit' AND resolved_timestamp BETWEEN $s AND $e$b"));
			$waste = round(floatval($this->Val("SELECT IFNULL(SUM(total_cost), 0) FROM waste_records WHERE status != 'rejected' AND created_timestamp BETWEEN $s AND $e$b")), 2);
			return [
				'waste' => $waste,
				'tasks' => $tasks,
				'mttr' => $times ? round(array_sum($times) / count($times), 1) : null,
				'overdue_alerts' => $overdue,
				'pending' => $this->OpenAt($iv, $w[1]),
				'closed_risks' => $closed
			];
		};
		$w0 = $win(0);
		$w7 = $win(7);
		return ['cutoff' => substr($w0[1], 11, 5), 'ref_date' => substr($w7[0], 0, 10), 'today' => $calc($w0), 'ref' => $calc($w7)];
	}

	// ---------------------------------------------------------------- Actividad

	private function Activity(): array
	{
		$from = $this->pdo->quote(date('Y-m-d H:i:s', strtotime('-7 day')));
		$who = "NULLIF(TRIM(IFNULL(u.first_name, '') || ' ' || IFNULL(u.last_name, '')), '')";
		$sql = [
			"SELECT w.approved_timestamp AS at, CASE w.status WHEN 'approved' THEN 'Merma aprobada' ELSE 'Merma rechazada' END AS action, IFNULL(w.case_number, 'MRM-' || w.id) || ' · ' || IFNULL(p.name, '') AS subject,
				'$' || printf('%.2f', w.total_cost) AS result, $who AS who, CASE w.status WHEN 'approved' THEN 'success' ELSE 'neutral' END AS tone, '/document/waste/' || w.id AS href, w.branch_id
			FROM waste_records w LEFT JOIN products p ON p.id = w.product_id LEFT JOIN users u ON u.id = w.approved_by_user_id
			WHERE w.status IN ('approved', 'rejected') AND w.approved_timestamp >= $from AND w.approved_timestamp > datetime(w.created_timestamp, '+1 minute')" . $this->B('w.branch_id'),
			"SELECT a.resolved_at AS at, CASE a.status WHEN 'closed' THEN 'Alerta resuelta' ELSE 'Alerta descartada' END AS action, a.title AS subject, IFNULL(a.resolution, '') AS result, $who AS who,
				CASE a.status WHEN 'closed' THEN 'success' ELSE 'neutral' END AS tone, '/alerts' AS href, a.branch_id
			FROM dairy_alerts a LEFT JOIN users u ON u.id = a.resolved_by_user_id WHERE a.status IN ('closed', 'dismissed') AND a.resolved_at >= $from" . $this->B('a.branch_id'),
			"SELECT q.resolved_timestamp AS at, 'Cuarentena dictaminada' AS action, p.name AS subject, IFNULL(q.resolution_notes, q.status) AS result, $who AS who, 'success' AS tone, '/quality' AS href, q.branch_id
			FROM quarantine_items q JOIN products p ON p.id = q.product_id LEFT JOIN users u ON u.id = q.resolved_by WHERE q.status != 'in_quarantine' AND q.resolved_timestamp >= $from" . $this->B('q.branch_id'),
			"SELECT sr.resolved_timestamp AS at, 'Reclamo a proveedor cerrado' AS action, IFNULL(sr.case_number, 'DEV-' || sr.id) AS subject, 'recuperado $' || printf('%.2f', IFNULL(sr.credit_recovered_amount, 0)) AS result, NULL AS who, 'success' AS tone, '/waste#tab-recuperacion' AS href, sr.branch_id
			FROM supplier_returns sr WHERE sr.status != 'pending_credit' AND sr.resolved_timestamp >= $from" . $this->B('sr.branch_id'),
			"SELECT t.completed_at AS at, 'Tarea completada' AS action, t.title AS subject, CASE WHEN t.completed_at > t.due_time THEN 'fuera de plazo' ELSE 'a tiempo' END AS result, $who AS who,
				CASE WHEN t.completed_at > t.due_time THEN 'warning' ELSE 'success' END AS tone, '/workforce' AS href, t.branch_id
			FROM workforce_store_tasks t LEFT JOIN users u ON u.id = t.completed_by_user_id WHERE t.status = 'completed' AND t.completed_at >= $from" . $this->B('t.branch_id'),
			"SELECT c.executed_date AS at, CASE c.status WHEN 'adjusted' THEN 'Ajuste autorizado' ELSE 'Conteo completado' END AS action, p.name AS subject,
				CASE WHEN IFNULL(c.difference_amount, 0) = 0 THEN 'sin diferencia' ELSE 'diferencia ' || c.difference_amount || ' u.' END AS result, $who AS who, 'success' AS tone, '/waste#tab-conteos' AS href, c.branch_id
			FROM stock_cycle_counts c JOIN products p ON p.id = c.product_id LEFT JOIN users u ON u.id = c.executed_by_user_id
			WHERE c.status IN ('completed', 'adjusted') AND c.executed_date >= " . $this->pdo->quote(date('Y-m-d', strtotime('-7 day'))) . $this->B('c.branch_id')
		];
		$out = [];
		foreach ($sql as $s)
		{
			$out = array_merge($out, $this->Rows($s . ' ORDER BY 1 DESC LIMIT 25'));
		}
		usort($out, fn($a, $b) => strcmp($b['at'], $a['at']));

		// Agrupa eventos repetidos (misma acción y asunto el mismo día): una línea con «×N», no N líneas iguales
		$branches = array_column($this->Rows('SELECT id, name FROM branches'), 'name', 'id');
		$groups = [];
		foreach ($out as $e)
		{
			$k = $e['action'] . '|' . $e['subject'] . '|' . substr($e['at'], 0, 10);
			if (isset($groups[$k]))
			{
				$groups[$k]['count']++;
				if ($e['who'] && !in_array($e['who'], $groups[$k]['people'], true))
				{
					$groups[$k]['people'][] = $e['who'];
				}
				continue;
			}
			$groups[$k] = $e + ['count' => 1, 'people' => $e['who'] ? [$e['who']] : [], 'branch' => $branches[$e['branch_id']] ?? null];
		}
		return array_slice(array_values($groups), 0, 12);
	}

	// ---------------------------------------------------------------- Soporte

	/**
	 * Guarda el estado del día (una fila por día y alcance, se sobrescribe) y devuelve el último registro de ayer, si existe.
	 */
	private function Snapshot(array $s): ?array
	{
		$key = $this->branch ? strval($this->branch) : 'all';
		try
		{
			$this->pdo->prepare('INSERT INTO today_snapshots (snap_date, scope, data, updated_at) VALUES (?, ?, ?, ?)
				ON CONFLICT(snap_date, scope) DO UPDATE SET data = excluded.data, updated_at = excluded.updated_at')
				->execute([$this->today, $key, json_encode($s), $this->now]);
			$st = $this->pdo->prepare('SELECT data, updated_at FROM today_snapshots WHERE snap_date = ? AND scope = ?');
			$st->execute([date('Y-m-d', strtotime('-1 day')), $key]);
			$r = $st->fetch(\PDO::FETCH_ASSOC);
			return $r ? json_decode($r['data'], true) + ['at' => $r['updated_at']] : null;
		}
		catch (\Throwable $e)
		{
			return null; // tabla aún no migrada
		}
	}

	private function Shifts(): array
	{
		$out = [];
		foreach (explode(',', (string) GROCY_TODAY_SHIFTS) as $def)
		{
			if (preg_match('/^\s*(.+?)=(\d{2}:\d{2})-(\d{2}:\d{2})\s*$/', $def, $m))
			{
				$out[] = [$m[1], $m[2], $m[3]];
			}
		}
		return $out;
	}

	private function ShiftOf(string $hm, array $shifts): string
	{
		foreach ($shifts as [$name, $s, $e])
		{
			if ($s <= $e ? ($hm >= $s && $hm < $e) : ($hm >= $s || $hm < $e))
			{
				return $name;
			}
		}
		return 'Fuera de turno';
	}

	private function CurrentShift(): ?array
	{
		$shifts = $this->Shifts();
		$name = $this->ShiftOf(date('H:i'), $shifts);
		foreach ($shifts as $s)
		{
			if ($s[0] === $name)
			{
				return ['name' => $name, 'from' => $s[1], 'to' => $s[2]];
			}
		}
		return null;
	}

	private function B(string $col): string
	{
		return $this->branch ? " AND $col = " . intval($this->branch) : '';
	}

	private function Rows(string $sql): array
	{
		return $this->pdo->query($sql)->fetchAll(\PDO::FETCH_ASSOC);
	}

	private function Row(string $sql): ?array
	{
		return $this->pdo->query($sql)->fetch(\PDO::FETCH_ASSOC) ?: null;
	}

	private function Val(string $sql)
	{
		return $this->pdo->query($sql)->fetchColumn();
	}

	private function Qty($v): string
	{
		return rtrim(rtrim(number_format(floatval($v), 2), '0'), '.');
	}

	private function Day(?string $d): string
	{
		return $d ? date('d/m', strtotime($d)) : '';
	}
}
