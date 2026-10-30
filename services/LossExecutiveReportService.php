<?php

namespace Grocy\Services;

/**
 * Informe Ejecutivo de Pérdidas y Novedades — Control 360 (Director de Mermas).
 *
 * No calcula indicadores propios: consolida las definiciones únicas ya existentes para que el informe muestre
 * exactamente lo mismo que los módulos de origen con los mismos filtros y período:
 *  - Pérdida confirmada, % sobre ventas netas, recuperación y período anterior: LossIntelligenceService (módulo Mermas).
 *  - Inventario y ventas netas por sucursal: DailyMetricsService (Inventario / Panel ejecutivo).
 *  - Novedades: lectura directa de los registros operativos (ajustes, recepciones, traslados, mermas, errores
 *    administrativos, expedientes de investigación y alertas). Ninguna novedad se infiere sin un registro que la respalde.
 * Corte: el día anterior a la fecha de publicación. Si una sucursal no registra ventas hasta el corte, el informe se publica
 * como «preliminar» (nunca como consolidado definitivo) y se muestra la hora de su última actualización.
 * Los informes publicados son inalterables (triggers de la migración 0302): regenerar crea un informe nuevo.
 */
class LossExecutiveReportService extends BaseService
{
	const PERIODS = ['daily' => 'Diario', 'weekly' => 'Semanal', 'monthly' => 'Mensual', 'annual' => 'Anual'];
	const NOVELTY_TYPES = [
		'faltante' => 'Faltante detectado', 'sobrante' => 'Sobrante identificado', 'recepcion' => 'Diferencia de recepción',
		'despacho' => 'Posible error de despacho', 'documental' => 'Error documental', 'averia' => 'Mercancía averiada',
		'vencido' => 'Mercancía vencida', 'ajuste_pendiente' => 'Ajuste pendiente de autorización',
		'sensible' => 'Producto sensible con diferencia', 'investigacion_abierta' => 'Investigación abierta',
		'investigacion_cerrada' => 'Investigación cerrada', 'alerta_critica' => 'Alerta crítica sin resolver'
	];
	const EXPIRED_CAUSES = ['EXP', 'FEF'];
	const DAMAGE_CAUSES = ['ROT', 'AVE', 'DEX', 'DET', 'CFR', 'TRA', 'EMA', 'ALM'];
	const LIST_LIMIT = 25;

	private function Db(): \PDO
	{
		return DatabaseService::GetInstance()->GetDbConnectionRaw();
	}

	private function Rows(string $sql, array $p = []): array
	{
		$st = $this->Db()->prepare($sql);
		$st->execute($p);
		return $st->fetchAll(\PDO::FETCH_ASSOC);
	}

	private function Val(string $sql, array $p = [])
	{
		$st = $this->Db()->prepare($sql);
		$st->execute($p);
		return $st->fetchColumn();
	}

	/** [desde, hasta] del período; el corte es el día anterior a la publicación */
	public static function Period(string $type, string $reportDate): array
	{
		$to = date('Y-m-d', strtotime($reportDate . ' -1 day'));
		switch ($type)
		{
			case 'weekly':
				return [date('Y-m-d', strtotime($to . ' -6 days')), $to];
			case 'monthly':
				return [date('Y-m-01', strtotime($to)), $to];
			case 'annual':
				return [date('Y-01-01', strtotime($to)), $to];
			default:
				return [$to, $to];
		}
	}

	private static function Pct(float $part, float $whole): ?float
	{
		return $whole != 0.0 ? round($part / $whole * 100, 2) : null;
	}

	/* ================================================================ Construcción */

	public function Build(string $type, string $reportDate): array
	{
		if (!isset(self::PERIODS[$type]))
		{
			throw new \Exception('Tipo de informe no válido');
		}
		[$from, $to] = self::Period($type, $reportDate);
		$li = LossIntelligenceService::GetInstance();
		$dm = DailyMetricsService::GetInstance();

		$branches = $this->Rows('SELECT id, name, code, format FROM branches WHERE active = 1 ORDER BY id');
		$sync = $this->SyncStatus($to);
		$synced = count(array_filter($branches, fn($b) => $sync[intval($b['id'])]['synced'] ?? false));
		$preliminary = $synced < count($branches);

		// A. Resumen (misma fuente que Mermas y que Inventario)
		$summary = $li->Summary([], $from, $to);
		$inventory = $dm->Inventory(null);
		$investigation = $this->InInvestigation();
		$target = LossCensusService::GetInstance()->Target(intval(substr($to, 0, 4)));
		$pct = $summary['total']['pct_sales'];
		$prev = floatval($summary['prev_period_total']);

		// Pérdida por sucursal desde los mismos eventos (la suma por sucursal = total de la red)
		$events = $li->Events([], $from, $to);
		$lossByBranch = [];
		foreach ($events as $e)
		{
			$b = intval($e['branch_id']);
			$lossByBranch[$b] = ($lossByBranch[$b] ?? 0) + $e['cost'];
		}

		// B. Novedades
		$novelties = $this->Novelties($from, $to);

		// C. Comparativo entre sucursales
		$branchRows = [];
		foreach ($branches as $b)
		{
			$id = intval($b['id']);
			$sales = $dm->Sales($id, $from, $to);
			$loss = round($lossByBranch[$id] ?? 0, 2);
			$bPct = self::Pct($loss, floatval($sales['net_sales']));
			$nov = count(array_filter($novelties['items'], fn($n) => intval($n['branch_id']) === $id));
			$crit = count(array_filter($novelties['items'], fn($n) => intval($n['branch_id']) === $id && $n['priority'] === 'alta'));
			$bTarget = LossCensusService::GetInstance()->Target(intval(substr($to, 0, 4)), $id);
			$branchRows[] = [
				'id' => $id, 'name' => $b['name'], 'code' => $b['code'], 'format' => $b['format'],
				'inventory' => $dm->Inventory($id)['value'], 'net_sales' => floatval($sales['net_sales']),
				'loss' => $loss, 'pct' => $bPct, 'novelties' => $nov, 'critical' => $crit,
				'status' => self::Light($bPct, $bTarget, $crit),
				'sync' => $sync[$id] ?? ['synced' => false, 'last_sale_at' => null, 'last_activity_at' => null],
				'drill' => '/waste?branch_id=' . $id . '&start_date=' . $from . '&end_date=' . $to
			];
		}
		usort($branchRows, fn($a, $b) => $b['loss'] <=> $a['loss']);

		$payload = [
			'title' => 'Informe Ejecutivo de Pérdidas y Novedades — Control 360',
			'period_type' => $type, 'period_label' => self::PERIODS[$type], 'from' => $from, 'to' => $to,
			'report_date' => $reportDate, 'generated_at' => date('Y-m-d H:i:s'), 'timezone' => date_default_timezone_get(),
			'completeness' => $preliminary ? 'preliminar' : 'definitivo',
			'summary' => [
				'branches_total' => count($branches), 'branches_synced' => $synced,
				'inventory_value' => $inventory['value'], 'inventory_units' => $inventory['units'],
				'confirmed_loss' => $summary['total']['value'], 'net_sales' => $summary['net_sales'], 'loss_pct_sales' => $pct,
				'by_class' => $summary['by_class'], 'events' => $summary['events'],
				'recovered' => $summary['recovery']['recovered'], 'recoverable' => $summary['recovery']['recoverable'],
				'prev_period_loss' => $prev, 'variation_pct' => $prev != 0.0 ? round(($summary['total']['value'] - $prev) / abs($prev) * 100, 2) : null,
				'in_investigation' => $investigation,
				'target' => $target,
				'status' => self::Light($pct, $target, count(array_filter($novelties['items'], fn($n) => $n['priority'] === 'alta'))),
				'sources' => ['loss' => 'Mermas (LossIntelligenceService::Summary)', 'inventory' => 'Inventario (DailyMetricsService::Inventory, sin tránsito, al momento de generar)']
			],
			'novelties' => $novelties,
			'branches' => $branchRows,
			'products' => $this->Products($events, $from, $to),
			'cedis' => $this->Cedis($branches),
			'integrity' => $this->Integrity(),
		];
		$payload['actions'] = $this->Actions($payload, $reportDate);
		return $payload;
	}

	/** Semáforo: crítico si supera la tolerancia o hay novedades críticas; atención si supera el presupuesto */
	private static function Light(?float $pct, ?array $target, int $critical): array
	{
		if ($pct === null)
		{
			$s = $critical > 0 ? 'atencion' : 'sin_datos';
		}
		elseif ($target && $target['tolerated_pct'] !== null && $pct > $target['tolerated_pct'])
		{
			$s = 'critico';
		}
		elseif (($target && $target['budget_pct'] !== null && $pct > $target['budget_pct']) || $critical > 0)
		{
			$s = 'atencion';
		}
		elseif (!$target)
		{
			$s = 'sin_meta';
		}
		else
		{
			$s = 'controlado';
		}
		$labels = ['critico' => 'Crítico', 'atencion' => 'Atención', 'controlado' => 'Controlado', 'sin_meta' => 'Sin meta configurada', 'sin_datos' => 'Sin ventas en el período'];
		return ['code' => $s, 'label' => $labels[$s]];
	}

	/** Sucursal sincronizada = registra ventas hasta la fecha de corte. Última actualización = último registro operativo. */
	public function SyncStatus(string $to): array
	{
		$out = [];
		foreach ($this->Rows("SELECT b.id,
				(SELECT MAX(s.created_timestamp) FROM sales s WHERE s.branch_id = b.id AND s.status = 'completed') AS last_sale_at,
				(SELECT MAX(w.created_timestamp) FROM waste_records w WHERE w.branch_id = b.id) AS last_waste_at,
				(SELECT MAX(l.row_created_timestamp) FROM stock_log l JOIN locations loc ON loc.id = l.location_id WHERE loc.branch_id = b.id) AS last_stock_at
			FROM branches b WHERE b.active = 1") as $r)
		{
			$last = max(array_filter([$r['last_sale_at'], $r['last_waste_at'], $r['last_stock_at']]) ?: [null]);
			$out[intval($r['id'])] = [
				'synced' => $r['last_sale_at'] !== null && substr($r['last_sale_at'], 0, 10) >= $to,
				'last_sale_at' => $r['last_sale_at'],
				'last_activity_at' => $last
			];
		}
		return $out;
	}

	/** Valor en investigación: componentes por separado (no se suman a la pérdida confirmada) */
	private function InInvestigation(): array
	{
		$cases = $this->Rows("SELECT COUNT(*) AS n, IFNULL(SUM(ABS(financial_impact)), 0) AS v FROM sensitive_investigation_cases WHERE stage NOT IN ('cerrado', 'ajustado')")[0];
		$waste = $this->Rows("SELECT COUNT(*) AS n, IFNULL(SUM(total_cost), 0) AS v FROM waste_records WHERE status = 'pending_approval'")[0];
		$adj = $this->Rows("SELECT COUNT(*) AS n, IFNULL(SUM(ABS(total_cost_impact)), 0) AS v FROM inventory_adjustments WHERE status = 'pending_approval'")[0];
		$admin = $this->Rows("SELECT COUNT(*) AS n, IFNULL(SUM(ABS(impact_cost)), 0) AS v FROM admin_error_cases WHERE status != 'closed'")[0];
		$parts = [
			'cases' => ['label' => 'Expedientes de investigación abiertos', 'count' => intval($cases['n']), 'value' => round(floatval($cases['v']), 2)],
			'waste' => ['label' => 'Mermas pendientes de aprobación', 'count' => intval($waste['n']), 'value' => round(floatval($waste['v']), 2)],
			'adjustments' => ['label' => 'Ajustes de inventario pendientes', 'count' => intval($adj['n']), 'value' => round(floatval($adj['v']), 2)],
			'admin_errors' => ['label' => 'Errores administrativos abiertos', 'count' => intval($admin['n']), 'value' => round(floatval($admin['v']), 2)],
		];
		return ['parts' => $parts, 'count' => array_sum(array_column($parts, 'count')), 'value' => round(array_sum(array_column($parts, 'value')), 2)];
	}

	/* ================================================================ Novedades */

	private function Novelties(string $from, string $to): array
	{
		$items = [];
		$user = "TRIM(IFNULL(u.first_name, '') || ' ' || IFNULL(u.last_name, ''))";
		$p = [':f' => $from, ':t' => $to];
		$add = function (string $type, array $r, string $priority, string $action, string $href) use (&$items) {
			$items[] = [
				'type' => $type, 'type_label' => self::NOVELTY_TYPES[$type], 'priority' => $priority,
				'branch_id' => intval($r['branch_id'] ?? 0), 'branch' => $r['branch'] ?? null,
				'product_id' => isset($r['product_id']) ? intval($r['product_id']) : null, 'product' => $r['product'] ?? null, 'sku' => $r['sku'] ?? null,
				'qty' => isset($r['qty']) ? round(floatval($r['qty']), 2) : null, 'value' => round(floatval($r['value'] ?? 0), 2),
				'date' => $r['d'] ?? null, 'responsible' => trim(strval($r['resp'] ?? '')) ?: 'Sin asignar',
				'status' => $r['status'] ?? null, 'doc' => $r['doc'] ?? null, 'action' => $action, 'href' => $href
			];
		};

		// Faltantes / sobrantes: ajustes de inventario registrados en el período
		foreach ($this->Rows("SELECT ia.adjustment_number AS doc, ia.branch_id, b.name AS branch, ia.product_id, p.name AS product, p.sku, ia.difference_amount AS qty,
				ia.total_cost_impact AS value, date(ia.created_timestamp) AS d, $user AS resp, ia.status, IFNULL(p.is_sensitive, 0) AS sens
			FROM inventory_adjustments ia JOIN products p ON p.id = ia.product_id LEFT JOIN branches b ON b.id = ia.branch_id LEFT JOIN users u ON u.id = ia.requested_by_user_id
			WHERE date(ia.created_timestamp) BETWEEN :f AND :t ORDER BY ABS(ia.total_cost_impact) DESC", $p) as $r)
		{
			$short = floatval($r['qty']) < 0;
			$r['value'] = abs(floatval($r['value']));
			$add($short ? 'faltante' : 'sobrante', $r, $r['status'] === 'pending_approval' ? 'alta' : 'media',
				$r['status'] === 'pending_approval' ? 'Autorizar o rechazar el ajuste con evidencia de reconteo' : 'Verificar causa raíz documentada', '/inventory-control');
			if ($r['sens'])
			{
				$add('sensible', $r, 'alta', 'Reconteo independiente del producto sensible', '/reconciliation');
			}
		}
		// Diferencias de recepción: entregado ≠ aceptado o con rechazo
		foreach ($this->Rows("SELECT pr.receipt_code AS doc, pr.branch_id, b.name AS branch, pri.product_id, p.name AS product, p.sku,
				(pri.delivered_amount - pri.accepted_amount) AS qty, (pri.delivered_amount - pri.accepted_amount) * IFNULL(pri.unit_price, 0) AS value,
				date(pr.received_at) AS d, $user AS resp, CASE WHEN pri.incident_id IS NULL THEN 'sin incidencia' ELSE 'con incidencia' END AS status, pri.reject_reason
			FROM procurement_receipt_items pri JOIN procurement_receipts pr ON pr.id = pri.receipt_id JOIN products p ON p.id = pri.product_id
			LEFT JOIN branches b ON b.id = pr.branch_id LEFT JOIN users u ON u.id = pr.received_by_user_id
			WHERE date(pr.received_at) BETWEEN :f AND :t AND ABS(pri.delivered_amount - pri.accepted_amount) > 0.0001
			ORDER BY value DESC", $p) as $r)
		{
			$add('recepcion', $r, $r['status'] === 'sin incidencia' ? 'media' : 'baja',
				$r['status'] === 'sin incidencia' ? 'Abrir reclamo al proveedor y registrar la incidencia' : 'Dar seguimiento al crédito del proveedor', '/mrp');
		}
		// Despacho: diferencias en traslados recibidos en el período y traslados en tránsito > 7 días
		foreach ($this->Rows("SELECT st.transfer_code AS doc, st.to_branch_id AS branch_id, b.name AS branch, sti.product_id, p.name AS product, p.sku,
				sti.difference_amount AS qty, ABS(sti.difference_amount) * IFNULL(sti.unit_price, 0) AS value, date(st.received_at) AS d, $user AS resp, st.status
			FROM stock_transfer_items sti JOIN stock_transfers st ON st.id = sti.transfer_id JOIN products p ON p.id = sti.product_id
			LEFT JOIN branches b ON b.id = st.to_branch_id LEFT JOIN users u ON u.id = st.received_by_user_id
			WHERE st.status = 'received' AND date(st.received_at) BETWEEN :f AND :t AND ABS(IFNULL(sti.difference_amount, 0)) > 0.0001", $p) as $r)
		{
			$add('despacho', $r, 'media', 'Validar despacho de origen y recepción de destino', '/transfers');
		}
		foreach ($this->Rows("SELECT st.transfer_code AS doc, st.from_branch_id AS branch_id, b.name AS branch, NULL AS product_id,
				'Traslado a ' || IFNULL(b2.name, '#' || st.to_branch_id) AS product, NULL AS sku, NULL AS qty, st.total_sent_cost AS value, date(st.sent_at) AS d, $user AS resp, st.status
			FROM stock_transfers st LEFT JOIN branches b ON b.id = st.from_branch_id LEFT JOIN branches b2 ON b2.id = st.to_branch_id LEFT JOIN users u ON u.id = st.created_by_user_id
			WHERE st.status NOT IN ('received', 'cancelled') AND st.sent_at IS NOT NULL AND date(st.sent_at) <= date(:t, '-7 days')", [':t' => $to]) as $r)
		{
			$r['status'] = 'en tránsito > 7 días';
			$add('despacho', $r, 'alta', 'Confirmar recepción en destino o investigar mercancía en tránsito', '/transfers');
		}
		// Errores documentales abiertos
		foreach ($this->Rows("SELECT ae.case_number AS doc, ae.branch_id, b.name AS branch, ae.product_id, p.name AS product, p.sku, NULL AS qty, ABS(ae.impact_cost) AS value,
				date(ae.created_timestamp) AS d, $user AS resp, ae.status, ae.title
			FROM admin_error_cases ae LEFT JOIN products p ON p.id = ae.product_id LEFT JOIN branches b ON b.id = ae.branch_id LEFT JOIN users u ON u.id = ae.created_by_user_id
			WHERE ae.status != 'closed' ORDER BY value DESC") as $r)
		{
			$r['product'] = $r['product'] ?: $r['title'];
			$add('documental', $r, 'media', 'Corregir el documento y verificar el procedimiento', '/waste/tab/errores');
		}
		// Averías y vencidos (mermas del período, aprobadas o pendientes)
		foreach ($this->Rows("SELECT w.case_number AS doc, w.branch_id, b.name AS branch, w.product_id, p.name AS product, p.sku, w.amount AS qty, w.total_cost AS value,
				date(w.created_timestamp) AS d, $user AS resp, w.status, IFNULL(r.loss_cause_code, '') AS cause
			FROM waste_records w JOIN products p ON p.id = w.product_id LEFT JOIN branches b ON b.id = w.branch_id
			LEFT JOIN waste_reasons r ON r.id = w.waste_reason_id LEFT JOIN users u ON u.id = w.reported_by_user_id
			WHERE w.status IN ('approved', 'pending_approval') AND date(w.created_timestamp) BETWEEN :f AND :t ORDER BY w.total_cost DESC", $p) as $r)
		{
			if (in_array($r['cause'], self::EXPIRED_CAUSES, true))
			{
				$add('vencido', $r, 'baja', 'Revisar rotación FEFO y pedido sugerido', '/quality');
			}
			elseif (in_array($r['cause'], self::DAMAGE_CAUSES, true))
			{
				$add('averia', $r, 'baja', 'Revisar manipulación y reclamar al responsable', '/waste');
			}
		}
		// Ajustes y mermas pendientes de autorización (cualquier fecha: siguen abiertos)
		foreach ($this->Rows("SELECT w.case_number AS doc, w.branch_id, b.name AS branch, w.product_id, p.name AS product, p.sku, w.amount AS qty, w.total_cost AS value,
				date(w.created_timestamp) AS d, $user AS resp, 'pendiente de aprobación' AS status
			FROM waste_records w JOIN products p ON p.id = w.product_id LEFT JOIN branches b ON b.id = w.branch_id LEFT JOIN users u ON u.id = w.reported_by_user_id
			WHERE w.status = 'pending_approval' ORDER BY w.total_cost DESC") as $r)
		{
			$add('ajuste_pendiente', $r, floatval($r['value']) >= 100 ? 'alta' : 'media', 'Aprobar o rechazar en la bandeja de autorizaciones', '/waste/approvals');
		}
		// Expedientes de investigación: abiertos y cerrados en el período
		foreach ($this->Rows("SELECT c.case_code AS doc, c.branch_id, b.name AS branch, c.product_id, p.name AS product, p.sku, c.difference_qty AS qty, ABS(c.financial_impact) AS value,
				date(c.created_at) AS d, $user AS resp, c.stage AS status, date(c.closed_at) AS closed_d, IFNULL(p.is_sensitive, 0) AS sens
			FROM sensitive_investigation_cases c JOIN products p ON p.id = c.product_id LEFT JOIN branches b ON b.id = c.branch_id LEFT JOIN users u ON u.id = c.created_by_user_id
			WHERE c.stage NOT IN ('cerrado', 'ajustado') OR date(c.closed_at) BETWEEN :f AND :t", $p) as $r)
		{
			$open = !in_array($r['status'], ['cerrado', 'ajustado'], true);
			$add($open ? 'investigacion_abierta' : 'investigacion_cerrada', $r, $open ? 'alta' : 'baja',
				$open ? 'Completar reconteo y documentar la causa' : 'Verificar acción preventiva', '/reconciliation');
			if ($open && $r['sens'])
			{
				$add('sensible', $r, 'alta', 'Reconteo independiente del producto sensible', '/reconciliation');
			}
		}
		// Alertas críticas sin resolver
		foreach ($this->Rows("SELECT 'ALR-' || a.id AS doc, a.branch_id, b.name AS branch, a.product_id, IFNULL(p.name, a.title) AS product, p.sku, NULL AS qty, 0 AS value,
				date(a.created_at) AS d, $user AS resp, a.status, a.title
			FROM dairy_alerts a LEFT JOIN products p ON p.id = a.product_id LEFT JOIN branches b ON b.id = a.branch_id LEFT JOIN users u ON u.id = a.owner_user_id
			WHERE a.severity = 'danger' AND a.status IN ('open', 'investigating') ORDER BY a.created_at DESC") as $r)
		{
			$add('alerta_critica', $r, 'alta', 'Atender la alerta: ' . $r['title'], '/alerts');
		}

		$byType = [];
		foreach (self::NOVELTY_TYPES as $k => $label)
		{
			$of = array_values(array_filter($items, fn($i) => $i['type'] === $k));
			$byType[$k] = ['label' => $label, 'count' => count($of), 'value' => round(array_sum(array_column($of, 'value')), 2)];
		}
		$rank = ['alta' => 0, 'media' => 1, 'baja' => 2];
		usort($items, fn($a, $b) => [$rank[$a['priority']], -$a['value']] <=> [$rank[$b['priority']], -$b['value']]);
		return ['by_type' => $byType, 'total' => count($items), 'items' => $items];
	}

	/* ================================================================ Productos */

	private function Products(array $events, string $from, string $to): array
	{
		$byProd = [];
		foreach ($events as $e)
		{
			$pid = intval($e['product_id']);
			$byProd[$pid]['cost'] = ($byProd[$pid]['cost'] ?? 0) + $e['cost'];
			$byProd[$pid]['units'] = ($byProd[$pid]['units'] ?? 0) + $e['units'];
			if ($e['loss_class'] === 'ajuste')
			{
				$byProd[$pid]['adj_units'] = ($byProd[$pid]['adj_units'] ?? 0) + abs($e['units']);
			}
		}
		$names = LossIntelligenceService::GetInstance()->ProductNames(array_keys($byProd));
		$row = fn($pid, $d) => ['product_id' => $pid, 'product' => $names[$pid]['name'] ?? ('#' . $pid), 'sku' => $names[$pid]['sku'] ?? null, 'barcode' => $names[$pid]['barcode'] ?? null, 'cost' => round($d['cost'] ?? 0, 2), 'units' => round($d['units'] ?? 0, 2), 'adj_units' => round($d['adj_units'] ?? 0, 2)];
		$all = array_map($row, array_keys($byProd), $byProd);
		$topCost = $all;
		usort($topCost, fn($a, $b) => $b['cost'] <=> $a['cost']);
		$topUnits = array_values(array_filter($all, fn($r) => $r['adj_units'] > 0));
		usort($topUnits, fn($a, $b) => $b['adj_units'] <=> $a['adj_units']);

		// Recurrentes: pérdida en 3 o más semanas distintas de los últimos 90 días
		$since = date('Y-m-d', strtotime($to . ' -89 days'));
		$weeks = [];
		foreach (LossIntelligenceService::GetInstance()->Events([], $since, $to) as $e)
		{
			if ($e['cost'] > 0)
			{
				$weeks[intval($e['product_id'])][date('o-W', strtotime($e['d']))] = true;
				$weeks[intval($e['product_id'])]['_cost'] = ($weeks[intval($e['product_id'])]['_cost'] ?? 0) + $e['cost'];
			}
		}
		$recurring = [];
		foreach ($weeks as $pid => $w)
		{
			$n = count($w) - 1;
			if ($n >= 3)
			{
				$recurring[] = ['product_id' => $pid, 'weeks' => $n, 'cost_90d' => round($w['_cost'], 2)];
			}
		}
		usort($recurring, fn($a, $b) => [$b['weeks'], $b['cost_90d']] <=> [$a['weeks'], $a['cost_90d']]);
		$recurring = array_slice($recurring, 0, 10);
		$rn = LossIntelligenceService::GetInstance()->ProductNames(array_column($recurring, 'product_id'));
		foreach ($recurring as &$r)
		{
			$r['product'] = $rn[$r['product_id']]['name'] ?? ('#' . $r['product_id']);
			$r['sku'] = $rn[$r['product_id']]['sku'] ?? null;
		}
		unset($r);

		// Sensibles sin conteo en los últimos 30 días (conteo = ajuste, corrección de inventario o conteo del centro de conciliación)
		$lim = date('Y-m-d', strtotime($to . ' -30 days'));
		$sensitive = $this->Rows("SELECT p.id AS product_id, p.name AS product, p.sku,
				MAX(IFNULL((SELECT MAX(date(ia.created_timestamp)) FROM inventory_adjustments ia WHERE ia.product_id = p.id), ''),
					IFNULL((SELECT MAX(date(l.row_created_timestamp)) FROM stock_log l WHERE l.product_id = p.id AND l.transaction_type = 'inventory-correction'), ''),
					IFNULL((SELECT MAX(date(m.created_at)) FROM sensitive_inventory_movements m WHERE m.product_id = p.id AND m.event_type = 'cycle_count'), '')) AS last_count
			FROM products p WHERE p.active = 1 AND IFNULL(p.is_sensitive, 0) = 1");
		$sensitive = array_values(array_filter($sensitive, fn($r) => $r['last_count'] === '' || $r['last_count'] < $lim));

		return [
			'top_cost' => array_slice($topCost, 0, 10),
			'top_units' => array_slice($topUnits, 0, 10),
			'recurring' => $recurring,
			'sensitive_no_count' => $sensitive,
		];
	}

	/** Conciliación sucursal–CEDIS: solo si existe una instalación registrada como CEDIS (branches.format = 'cedis') */
	private function Cedis(array $branches): array
	{
		$cedis = array_values(array_filter($branches, fn($b) => strtolower(strval($b['format'])) === 'cedis'));
		if (!$cedis)
		{
			return ['available' => false, 'items' => [],
				'message' => 'No hay ningún CEDIS registrado en DAILY ni integración con el WMS del CEDIS: la conciliación sucursal–CEDIS no se puede calcular y no se muestran datos.'];
		}
		$ids = implode(',', array_map(fn($b) => intval($b['id']), $cedis));
		$items = $this->Rows("SELECT st.transfer_code, st.status, b.name AS branch, p.name AS product, sti.sent_amount, sti.received_amount, sti.difference_amount
			FROM stock_transfers st JOIN stock_transfer_items sti ON sti.transfer_id = st.id JOIN products p ON p.id = sti.product_id LEFT JOIN branches b ON b.id = st.to_branch_id
			WHERE st.from_branch_id IN ($ids) AND (ABS(IFNULL(sti.difference_amount, 0)) > 0.0001 OR st.status NOT IN ('received', 'cancelled')) ORDER BY st.id DESC LIMIT 50");
		return ['available' => true, 'items' => $items, 'message' => null];
	}

	private function Integrity(): array
	{
		try
		{
			$v = MovementLedgerService::GetInstance()->Verify(20);
			return ['ledger_ok' => $v['ok'], 'chain_ok' => $v['chain_ok'], 'movements' => $v['movements'], 'issues' => count($v['issues']), 'counts' => $v['counts']];
		}
		catch (\Throwable $e)
		{
			return ['ledger_ok' => null, 'error' => $e->getMessage()];
		}
	}

	/* ================================================================ Acciones */

	private function Actions(array $r, string $reportDate): array
	{
		$out = [];
		$due = fn(int $days) => date('Y-m-d', strtotime($reportDate . " +$days days"));
		$add = function (string $what, string $cause, string $causeKind, string $evidence, string $who, string $action, int $days, string $status, string $href) use (&$out, $due) {
			$out[] = ['what' => $what, 'cause' => $cause, 'cause_kind' => $causeKind, 'evidence' => $evidence, 'who' => $who, 'action' => $action, 'due' => $due($days), 'status' => $status, 'href' => $href];
		};
		foreach ($r['branches'] as $b)
		{
			if ($b['status']['code'] === 'critico')
			{
				$add("{$b['name']}: merma de {$b['pct']}% sobre ventas, por encima de la tolerancia", 'Por determinar con el detalle de causales', 'pendiente',
					'Eventos de pérdida del período en Mermas', 'Gerente de sucursal + Prevención de pérdidas', 'Revisar causales principales y plan de acción', 2, 'Abierta', $b['drill']);
			}
			if (!$b['sync']['synced'])
			{
				$add("{$b['name']}: sin ventas registradas hasta el corte ({$r['to']})", 'Sucursal sin sincronizar o sin operación', 'pendiente',
					'Última venta: ' . ($b['sync']['last_sale_at'] ?: 'nunca'), 'Sistemas / Gerente de sucursal', 'Sincronizar POS y regenerar el informe', 1, 'Abierta', '/sales');
			}
		}
		$groups = [];
		foreach ($r['novelties']['items'] as $n)
		{
			if ($n['priority'] === 'alta')
			{
				$groups[$n['type']][] = $n;
			}
		}
		$who = ['faltante' => 'Supervisor de inventario', 'sobrante' => 'Supervisor de inventario', 'despacho' => 'Logística / CEDIS', 'ajuste_pendiente' => 'Gerente con permiso de aprobación',
			'sensible' => 'Prevención de pérdidas', 'investigacion_abierta' => 'Prevención de pérdidas', 'alerta_critica' => 'Responsable de la alerta'];
		foreach ($groups as $type => $list)
		{
			$first = $list[0];
			$value = round(array_sum(array_column($list, 'value')), 2);
			$add(count($list) . ' × ' . self::NOVELTY_TYPES[$type] . ($value > 0 ? ' ($' . number_format($value, 2) . ')' : ''),
				$type === 'despacho' ? 'Probable: mercancía no confirmada en destino' : 'Por confirmar con evidencia',
				$type === 'investigacion_cerrada' ? 'confirmada' : 'probable',
				'Documentos: ' . implode(', ', array_slice(array_filter(array_column($list, 'doc')), 0, 4)),
				$who[$type] ?? 'Prevención de pérdidas', $first['action'], 1, 'Abierta', $first['href']);
		}
		if ($r['products']['sensitive_no_count'])
		{
			$add(count($r['products']['sensitive_no_count']) . ' productos sensibles sin conteo en 30 días', 'Conteo cíclico no ejecutado', 'confirmada',
				'Sin ajustes ni conteos registrados', 'Supervisor de inventario', 'Programar conteo ciego', 3, 'Abierta', '/reconciliation');
		}
		if (($r['integrity']['ledger_ok'] ?? true) === false)
		{
			$add('Libro de movimientos con inconsistencias (' . $r['integrity']['issues'] . ')', 'Movimientos modificados, borrados o sin sello', 'confirmada',
				'Verificación del libro inalterable', 'Auditoría / Sistemas', 'Revisar el control de integridad de movimientos', 1, 'Abierta', '/inventory-control');
		}
		return $out;
	}

	/* ================================================================ Publicación, programación e historial */

	public function Generate(string $type, string $trigger, ?int $userId, ?string $reportDate = null): array
	{
		$reportDate = $reportDate ?: date('Y-m-d');
		$t0 = microtime(true);
		$code = 'DCR-' . strtoupper(substr($type, 0, 1)) . '-' . str_replace('-', '', $reportDate) . '-' . substr(bin2hex(random_bytes(3)), 0, 6);
		[$from, $to] = self::Period($type, $reportDate);
		$db = $this->Db();
		$ins = $db->prepare('INSERT INTO dcr_executive_reports (report_code, period_type, period_from, period_to, report_date, completeness, branches_total, branches_synced,
			trigger_type, status, payload_json, payload_sha256, error_message, generated_by_user_id, generated_at, duration_ms) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
		try
		{
			$payload = $this->Build($type, $reportDate);
			$payload['report_code'] = $code;
			$json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
			$ins->execute([$code, $type, $from, $to, $reportDate, $payload['completeness'], $payload['summary']['branches_total'], $payload['summary']['branches_synced'],
				$trigger, 'publicado', $json, hash('sha256', $json), null, $userId, date('Y-m-d H:i:s'), intval((microtime(true) - $t0) * 1000)]);
			$id = intval($db->lastInsertId());
			$delivered = $this->Deliver($id, $type);
			return ['id' => $id, 'code' => $code, 'status' => 'publicado', 'completeness' => $payload['completeness'], 'recipients' => $delivered];
		}
		catch (\Throwable $e)
		{
			// El fallo queda registrado: nunca se marca como entregado
			$ins->execute([$code, $type, $from, $to, $reportDate, 'preliminar', 0, 0, $trigger, 'error', null, null, mb_substr($e->getMessage(), 0, 1000), $userId, date('Y-m-d H:i:s'), intval((microtime(true) - $t0) * 1000)]);
			return ['id' => intval($db->lastInsertId()), 'code' => $code, 'status' => 'error', 'error' => $e->getMessage(), 'recipients' => 0];
		}
	}

	/** Destinatarios: los configurados o, si no hay, usuarios con LOSS_PREVENTION o ADMIN */
	public function Recipients(string $type): array
	{
		$cfg = $this->Rows('SELECT recipient_user_ids FROM dcr_report_schedules WHERE period_type = ?', [$type])[0]['recipient_user_ids'] ?? null;
		$ids = array_values(array_filter(array_map('intval', json_decode(strval($cfg), true) ?: [])));
		if ($ids)
		{
			return $ids;
		}
		return array_map('intval', array_column($this->Rows("SELECT DISTINCT up.user_id FROM user_permissions up JOIN permission_hierarchy ph ON ph.id = up.permission_id
			WHERE ph.name IN ('LOSS_PREVENTION', 'ADMIN')"), 'user_id'));
	}

	/** Entrega = publicación en la plataforma por destinatario. El correo no está configurado: se registra como tal, sin simular el envío. */
	private function Deliver(int $reportId, string $type): int
	{
		$now = date('Y-m-d H:i:s');
		$st = $this->Db()->prepare('INSERT OR IGNORE INTO dcr_report_deliveries (report_id, user_id, channel, status, detail, created_at) VALUES (?, ?, ?, ?, ?, ?)');
		$users = $this->Recipients($type);
		foreach ($users as $u)
		{
			$st->execute([$reportId, $u, 'app', 'publicado', 'Disponible en Reportes › Informe ejecutivo de pérdidas', $now]);
			$st->execute([$reportId, $u, 'email', 'no_configurado', 'DAILY no tiene transporte de correo configurado', $now]);
		}
		return count($users);
	}

	public function MarkRead(int $reportId, int $userId): void
	{
		$this->Db()->prepare("UPDATE dcr_report_deliveries SET status = 'leido', read_at = ? WHERE report_id = ? AND user_id = ? AND channel = 'app' AND read_at IS NULL")
			->execute([date('Y-m-d H:i:s'), $reportId, $userId]);
	}

	public static function NextRun(string $runTime, string $type, ?int $from = null): string
	{
		$from = $from ?? time();
		[$h, $m] = array_map('intval', explode(':', $runTime . ':0'));
		$at = fn(string $day) => strtotime(sprintf('%s %02d:%02d:00', $day, $h, $m));
		$day = date('Y-m-d', $from);
		switch ($type)
		{
			case 'weekly':
				$cand = $at(date('Y-m-d', strtotime('monday this week', $from)));
				$next = $cand > $from ? $cand : $at(date('Y-m-d', strtotime('monday next week', $from)));
				break;
			case 'monthly':
				$cand = $at(date('Y-m-01', $from));
				$next = $cand > $from ? $cand : $at(date('Y-m-01', strtotime('first day of next month', $from)));
				break;
			case 'annual':
				$cand = $at(date('Y-01-01', $from));
				$next = $cand > $from ? $cand : $at((intval(date('Y', $from)) + 1) . '-01-01');
				break;
			default:
				$cand = $at($day);
				$next = $cand > $from ? $cand : $at(date('Y-m-d', strtotime($day . ' +1 day')));
		}
		return date('Y-m-d H:i:s', $next);
	}

	/**
	 * Ejecuta las programaciones vencidas. Lo llama bin/dairy_scheduler.php (programador del sistema) y, como respaldo,
	 * la página del informe al abrirse (trigger «catch_up»): si nadie abre la página ni corre el programador, no se genera.
	 */
	public function RunDue(string $trigger = 'schedule', ?int $now = null): array
	{
		$now = $now ?? time();
		$out = [];
		foreach ($this->Rows("SELECT * FROM dcr_report_schedules WHERE active = 1 AND next_run_at IS NOT NULL AND next_run_at <= ?", [date('Y-m-d H:i:s', $now)]) as $s)
		{
			$reportDate = substr($s['next_run_at'], 0, 10);
			$res = $this->Generate($s['period_type'], $trigger, null, $reportDate);
			$this->Db()->prepare('UPDATE dcr_report_schedules SET last_run_at = ?, next_run_at = ? WHERE id = ?')
				->execute([date('Y-m-d H:i:s', $now), self::NextRun($s['run_time'], $s['period_type'], $now), $s['id']]);
			$out[] = ['period_type' => $s['period_type']] + $res;
		}
		return $out;
	}

	public function Schedules(): array
	{
		return $this->Rows('SELECT * FROM dcr_report_schedules ORDER BY id');
	}

	public function SaveSchedule(string $type, array $d, int $userId): void
	{
		if (!isset(self::PERIODS[$type]))
		{
			throw new \Exception('Tipo de informe no válido');
		}
		$time = strval($d['run_time'] ?? '09:00');
		if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $time))
		{
			throw new \Exception('Hora no válida (HH:MM)');
		}
		$active = !empty($d['active']) ? 1 : 0;
		$recipients = array_values(array_filter(array_map('intval', (array) ($d['recipient_user_ids'] ?? []))));
		$this->Db()->prepare('UPDATE dcr_report_schedules SET run_time = ?, active = ?, recipient_user_ids = ?, next_run_at = ?, updated_by_user_id = ?, updated_timestamp = ? WHERE period_type = ?')
			->execute([$time, $active, $recipients ? json_encode($recipients) : null, $active ? self::NextRun($time, $type) : null, $userId, date('Y-m-d H:i:s'), $type]);
	}

	public function History(?string $type = null, int $limit = 30): array
	{
		$w = $type ? ' WHERE r.period_type = :t' : '';
		$p = $type ? [':t' => $type] : [];
		return $this->Rows("SELECT r.id, r.report_code, r.period_type, r.period_from, r.period_to, r.report_date, r.completeness, r.branches_total, r.branches_synced,
				r.trigger_type, r.status, r.error_message, r.generated_at, r.duration_ms, r.payload_sha256,
				(SELECT COUNT(*) FROM dcr_report_deliveries d WHERE d.report_id = r.id AND d.channel = 'app') AS recipients,
				(SELECT COUNT(*) FROM dcr_report_deliveries d WHERE d.report_id = r.id AND d.channel = 'app' AND d.read_at IS NOT NULL) AS read_count
			FROM dcr_executive_reports r$w ORDER BY r.id DESC LIMIT " . intval($limit), $p);
	}

	public function Get(int $id): array
	{
		$r = $this->Rows('SELECT * FROM dcr_executive_reports WHERE id = ?', [$id])[0] ?? null;
		if (!$r)
		{
			throw new \Exception("Informe #$id no encontrado");
		}
		$r['payload'] = $r['payload_json'] ? json_decode($r['payload_json'], true) : null;
		$r['integrity_ok'] = $r['payload_json'] ? hash('sha256', $r['payload_json']) === $r['payload_sha256'] : null;
		unset($r['payload_json']);
		$r['deliveries'] = $this->Rows("SELECT d.*, TRIM(IFNULL(u.first_name, '') || ' ' || IFNULL(u.last_name, '')) AS user_name, u.username FROM dcr_report_deliveries d LEFT JOIN users u ON u.id = d.user_id WHERE d.report_id = ? ORDER BY d.user_id, d.channel", [$id]);
		return $r;
	}

	public function Latest(string $type = 'daily'): ?int
	{
		$id = $this->Val("SELECT id FROM dcr_executive_reports WHERE period_type = ? AND status = 'publicado' ORDER BY id DESC LIMIT 1", [$type]);
		return $id ? intval($id) : null;
	}

	/* ================================================================ Exportación Excel (.xlsx, una hoja por sección) */

	public function ExportXlsx(int $id): string
	{
		$r = $this->Get($id);
		$p = $r['payload'];
		if (!$p)
		{
			throw new \Exception('El informe no tiene contenido (falló su generación)');
		}
		$s = $p['summary'];
		$sheets = [];
		$sheets['Resumen'] = [
			['Informe', $p['title']], ['Código', $r['report_code']], ['Período', $p['period_label'] . ' ' . $p['from'] . ' a ' . $p['to']],
			['Estado de consolidación', $p['completeness']], ['Generado', $p['generated_at'] . ' (' . $p['timezone'] . ')'],
			['Sucursales incluidas', $s['branches_total']], ['Sucursales sincronizadas', $s['branches_synced']],
			['Valor del inventario', $s['inventory_value']], ['Pérdida confirmada', $s['confirmed_loss']], ['Ventas netas', $s['net_sales']],
			['% merma sobre ventas netas', $s['loss_pct_sales']], ['Recuperación financiera', $s['recovered']], ['Pérdida período anterior', $s['prev_period_loss']],
			['Variación %', $s['variation_pct']], ['Valor en investigación', $s['in_investigation']['value']], ['Estado general', $s['status']['label']],
		];
		$sheets['Sucursales'] = array_merge([['Sucursal', 'Inventario', 'Ventas netas', 'Pérdida', '% Merma', 'Novedades', 'Críticas', 'Estado', 'Sincronizada', 'Última venta']],
			array_map(fn($b) => [$b['name'], $b['inventory'], $b['net_sales'], $b['loss'], $b['pct'], $b['novelties'], $b['critical'], $b['status']['label'], $b['sync']['synced'] ? 'Sí' : 'No', $b['sync']['last_sale_at']], $p['branches']));
		$sheets['Novedades'] = array_merge([['Prioridad', 'Tipo', 'Sucursal', 'Producto', 'SKU', 'Cantidad', 'Valor', 'Fecha', 'Responsable', 'Estado', 'Documento', 'Acción requerida']],
			array_map(fn($n) => [$n['priority'], $n['type_label'], $n['branch'], $n['product'], $n['sku'], $n['qty'], $n['value'], $n['date'], $n['responsible'], $n['status'], $n['doc'], $n['action']], $p['novelties']['items']));
		$sheets['Productos'] = array_merge([['Lista', 'Producto', 'Costo', 'Unidades', 'Unidades ajustadas', 'SKU', 'UPC/EAN']],
			array_map(fn($x) => ['Top pérdida $', $x['product'], $x['cost'], $x['units'], $x['adj_units'], $x['sku'], $x['barcode']], $p['products']['top_cost']),
			array_map(fn($x) => ['Top diferencia u.', $x['product'], $x['cost'], $x['units'], $x['adj_units'], $x['sku'], $x['barcode']], $p['products']['top_units']),
			array_map(fn($x) => ['Recurrente (' . $x['weeks'] . ' sem.)', $x['product'], $x['cost_90d'], null, null], $p['products']['recurring']),
			array_map(fn($x) => ['Sensible sin conteo', $x['product'], null, null, null], $p['products']['sensitive_no_count']));
		$sheets['Acciones'] = array_merge([['Qué ocurrió', 'Causa', 'Tipo de causa', 'Evidencia', 'Responsable', 'Acción correctiva', 'Fecha límite', 'Estado']],
			array_map(fn($a) => [$a['what'], $a['cause'], $a['cause_kind'], $a['evidence'], $a['who'], $a['action'], $a['due'], $a['status']], $p['actions']));
		return self::Xlsx($sheets);
	}

	private static function Xlsx(array $sheets): string
	{
		$file = tempnam(sys_get_temp_dir(), 'dcr') . '.xlsx';
		$zip = new \ZipArchive();
		$zip->open($file, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
		$esc = fn($v) => htmlspecialchars(strval($v), ENT_XML1 | ENT_QUOTES, 'UTF-8');
		$col = function (int $i) {
			$s = '';
			for ($i++; $i > 0; $i = intdiv($i - 1, 26))
			{
				$s = chr(65 + ($i - 1) % 26) . $s;
			}
			return $s;
		};
		$wbSheets = $rels = $ct = '';
		$n = 0;
		foreach ($sheets as $name => $rows)
		{
			$n++;
			$xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';
			foreach (array_values($rows) as $ri => $row)
			{
				$xml .= '<row r="' . ($ri + 1) . '">';
				foreach (array_values($row) as $ci => $v)
				{
					$ref = $col($ci) . ($ri + 1);
					if ($v === null || $v === '')
					{
						continue;
					}
					$xml .= (is_int($v) || is_float($v)) ? '<c r="' . $ref . '"><v>' . $v . '</v></c>' : '<c r="' . $ref . '" t="inlineStr"><is><t>' . $esc($v) . '</t></is></c>';
				}
				$xml .= '</row>';
			}
			$zip->addFromString("xl/worksheets/sheet$n.xml", $xml . '</sheetData></worksheet>');
			$wbSheets .= '<sheet name="' . $esc($name) . '" sheetId="' . $n . '" r:id="rId' . $n . '"/>';
			$rels .= '<Relationship Id="rId' . $n . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $n . '.xml"/>';
			$ct .= '<Override PartName="/xl/worksheets/sheet' . $n . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
		}
		$zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>' . $ct . '</Types>');
		$zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
		$zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>' . $wbSheets . '</sheets></workbook>');
		$zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' . $rels . '</Relationships>');
		$zip->close();
		$data = file_get_contents($file);
		@unlink($file);
		return $data;
	}
}
