<?php

namespace Grocy\Services;

class AIAdvisorService extends BaseService
{
	public function GetSmartAlerts(?int $branchId = null): array
	{
		$alerts = [];
		$dbRaw = DatabaseService::GetInstance()->GetDbConnectionRaw();

		// 1. Critical Expiry Risk (Productos próximos a caducar en <= 7 días o ya caducados)
		$expirySql = "
			SELECT 
				p.id AS product_id,
				p.name AS product_name,
				qu.name AS qu_name,
				s.stock_id,
				s.amount,
				s.best_before_date,
				IFNULL(s.price, IFNULL(plp.price, 0)) AS unit_price,
				(s.amount * IFNULL(s.price, IFNULL(plp.price, 0))) AS potential_loss,
				ROUND(julianday(s.best_before_date) - julianday('now')) AS days_left
			FROM stock s
			JOIN products p ON s.product_id = p.id
			JOIN quantity_units qu ON p.qu_id_stock = qu.id
			JOIN locations l ON s.location_id = l.id
			LEFT JOIN cache__products_last_purchased plp ON p.id = plp.product_id
			WHERE s.amount > 0
				AND s.best_before_date IS NOT NULL
				AND s.best_before_date != '2999-12-31'
				AND julianday(s.best_before_date) - julianday('now') <= 7
		";
		if ($branchId !== null && $branchId > 0)
		{
			$expirySql .= " AND l.branch_id = " . intval($branchId);
		}
		$expirySql .= " ORDER BY days_left ASC LIMIT 10";

		$stmtExp = $dbRaw->query($expirySql);
		$expiringLots = $stmtExp->fetchAll(\PDO::FETCH_OBJ);

		$totalLossRisk = 0.0;
		$expiringItemsSummary = [];
		foreach ($expiringLots as $lot)
		{
			$loss = floatval($lot->potential_loss);
			$totalLossRisk += $loss;
			$days = intval($lot->days_left);
			$urgency = ($days < 0) ? "Vencido hace " . abs($days) . " días" : (($days == 0) ? "Vence hoy" : "Vence en $days días");
			$expiringItemsSummary[] = "{$lot->product_name} ({$lot->amount} {$lot->qu_name}, $urgency, Riesgo: $" . number_format($loss, 2) . ")";
		}

		if ($totalLossRisk > 0)
		{
			$alerts[] = [
				'id' => 'alert_expiry_risk',
				'type' => 'risk',
				'severity' => ($totalLossRisk > 500) ? 'danger' : 'warning',
				'icon' => 'fa-exclamation-triangle',
				'title' => 'Riesgo de Merma por Caducidad Inminente',
				'metric_value' => '$' . number_format($totalLossRisk, 2),
				'metric_label' => 'Valor monetario en riesgo',
				'description' => 'Se detectaron ' . count($expiringLots) . ' lotes con fecha de vencimiento crítica (≤ 7 días) o ya caducados.',
				'details' => $expiringItemsSummary,
				'recommendation' => 'Priorizar venta inmediata aplicando promociones de salida rápida, rotación FEFO hacia exhibición prioritaria o donación antes de generar merma total.'
			];
		}

		// 2. Anomaly Detection on Waste (Productos con merma anormalmente alta en los últimos 30 días)
		$wasteAnomalySql = "
			SELECT 
				p.name AS product_name,
				qu.name AS qu_name,
				COUNT(w.id) AS waste_events,
				SUM(w.amount) AS total_wasted_units,
				SUM(w.total_cost) AS total_wasted_cost
			FROM waste_records w
			JOIN products p ON w.product_id = p.id
			JOIN quantity_units qu ON w.qu_id = qu.id
			WHERE w.status = 'approved'
				AND date(w.created_timestamp) >= date('now', '-30 days')
		";
		if ($branchId !== null && $branchId > 0)
		{
			$wasteAnomalySql .= " AND w.branch_id = " . intval($branchId);
		}
		$wasteAnomalySql .= " GROUP BY p.id, p.name, qu.name HAVING total_wasted_cost > 100 ORDER BY total_wasted_cost DESC LIMIT 5";

		$stmtWaste = $dbRaw->query($wasteAnomalySql);
		$wasteAnomalies = $stmtWaste->fetchAll(\PDO::FETCH_OBJ);

		if (!empty($wasteAnomalies))
		{
			$wasteDetails = [];
			$totalAnomalyCost = 0.0;
			foreach ($wasteAnomalies as $wa)
			{
				$totalAnomalyCost += floatval($wa->total_wasted_cost);
				$wasteDetails[] = "{$wa->product_name}: {$wa->total_wasted_units} {$wa->qu_name} mermadas en {$wa->waste_events} eventos ($" . number_format($wa->total_wasted_cost, 2) . ")";
			}

			$alerts[] = [
				'id' => 'alert_waste_anomaly',
				'type' => 'anomaly',
				'severity' => 'danger',
				'icon' => 'fa-dumpster-fire',
				'title' => 'Anomalía de Desperdicio Recurrente',
				'metric_value' => '$' . number_format($totalAnomalyCost, 2),
				'metric_label' => 'Pérdida acumulada en 30 días',
				'description' => 'Existen productos con pérdidas repetidas que exceden el umbral de tolerancia operativa normal.',
				'details' => $wasteDetails,
				'recommendation' => 'Auditar las condiciones de almacenamiento, cadena de frío, manipulación por empleados y calibración de recepción para estos artículos críticos.'
			];
		}

		// 3. Stockout Risk & Replenishment Opportunity (Productos con stock por debajo del mínimo o sin existencias)
		$stockoutSql = "
			SELECT 
				p.id AS product_id,
				p.name AS product_name,
				qu.name AS qu_name,
				p.min_stock_amount,
				IFNULL(cur.amount, 0) AS current_stock
			FROM products p
			JOIN quantity_units qu ON p.qu_id_stock = qu.id
			LEFT JOIN (
				SELECT product_id, SUM(amount) AS amount FROM stock GROUP BY product_id
			) cur ON p.id = cur.product_id
			WHERE p.min_stock_amount > 0
				AND IFNULL(cur.amount, 0) <= p.min_stock_amount
				AND p.active = 1
			ORDER BY (IFNULL(cur.amount, 0) - p.min_stock_amount) ASC
			LIMIT 10
		";

		$stmtStockout = $dbRaw->query($stockoutSql);
		$stockoutItems = $stmtStockout->fetchAll(\PDO::FETCH_OBJ);

		if (!empty($stockoutItems))
		{
			$stockDetails = [];
			foreach ($stockoutItems as $si)
			{
				$status = ($si->current_stock <= 0) ? "AGOTADO (0)" : "Stock Crítico: {$si->current_stock}";
				$stockDetails[] = "{$si->product_name}: $status / Mínimo requerido: {$si->min_stock_amount} {$si->qu_name}";
			}

			$alerts[] = [
				'id' => 'alert_replenishment',
				'type' => 'replenishment',
				'severity' => 'warning',
				'icon' => 'fa-truck-loading',
				'title' => 'Oportunidad de Reposición Urgente',
				'metric_value' => count($stockoutItems) . ' Productos',
				'metric_label' => 'Por debajo del stock de seguridad',
				'description' => 'Artículos en riesgo inminente de quiebre de inventario que pueden ocasionar ventas perdidas.',
				'details' => $stockDetails,
				'recommendation' => 'Emitir órdenes de compra a proveedores habituales o realizar transferencias inter-sucursales de forma prioritaria.'
			];
		}

		// 4. Cold Chain Temperature Anomalies
		$coldSql = "
			SELECT cct.*, l.name AS location_name, l.is_freezer
			FROM cold_chain_temperatures cct
			JOIN locations l ON cct.location_id = l.id
			WHERE cct.status IN ('warning', 'critical')
				AND date(cct.recorded_timestamp) >= date('now', '-3 days')
		";
		if ($branchId !== null && $branchId > 0)
		{
			$coldSql .= " AND cct.branch_id = " . intval($branchId);
		}
		$coldSql .= " ORDER BY cct.recorded_timestamp DESC LIMIT 5";
		$coldAnomalies = $dbRaw->query($coldSql)->fetchAll(\PDO::FETCH_OBJ);

		if (!empty($coldAnomalies))
		{
			$coldDetails = [];
			$isCritical = false;
			foreach ($coldAnomalies as $ca)
			{
				if ($ca->status === 'critical') $isCritical = true;
				$action = !empty($ca->action_taken) ? " - Acción: {$ca->action_taken}" : " - Requiere revisión";
				$coldDetails[] = "{$ca->location_name}: {$ca->temperature}°C ({$ca->recorded_timestamp}){$action}";
			}

			$alerts[] = [
				'id' => 'alert_cold_chain',
				'type' => 'cold_chain',
				'severity' => $isCritical ? 'danger' : 'warning',
				'icon' => 'fa-temperature-low',
				'title' => 'Desviación en Cadena de Frío',
				'metric_value' => count($coldAnomalies) . ' Desviaciones',
				'metric_label' => 'Equipos con anomalía térmica',
				'description' => 'Se han registrado fluctuaciones fuera del rango seguro en cámaras o refrigeradores de lácteos.',
				'details' => $coldDetails,
				'recommendation' => 'Revisar burletes, termostatos y compresores inmediatamente. Efectuar inspección de calidad a los lotes resguardados.'
			];
		}

		// 5. Active Quarantine Lots
		$quarantineSql = "
			SELECT qi.*, p.name AS product_name, qu.name AS qu_name
			FROM quarantine_items qi
			JOIN products p ON qi.product_id = p.id
			JOIN quantity_units qu ON qi.qu_id = qu.id
			WHERE qi.status = 'in_quarantine'
		";
		if ($branchId !== null && $branchId > 0)
		{
			$quarantineSql .= " AND qi.branch_id = " . intval($branchId);
		}
		$quarantineItems = $dbRaw->query($quarantineSql)->fetchAll(\PDO::FETCH_OBJ);

		if (!empty($quarantineItems))
		{
			$qDetails = [];
			foreach ($quarantineItems as $qi)
			{
				$qDetails[] = "{$qi->product_name} (Lote: {$qi->stock_id}): {$qi->amount} {$qi->qu_name} retenidos por '{$qi->reason}'";
			}

			$alerts[] = [
				'id' => 'alert_quarantine',
				'type' => 'quarantine',
				'severity' => 'danger',
				'icon' => 'fa-shield-halved',
				'title' => 'Lotes Retenidos en Cuarentena',
				'metric_value' => count($quarantineItems) . ' Retenciones',
				'metric_label' => 'Lotes aislados de venta',
				'description' => 'Productos bloqueados temporalmente por sospecha de alteración organoléptica o temperatura.',
				'details' => $qDetails,
				'recommendation' => 'Emitir dictamen de calidad: autorizar liberación a góndola o descartar a merma registrando el motivo correspondiente.'
			];
		}

		// 6. Supplier Incidents & OTIF Risk
		$incidentSql = "
			SELECT si.*, sl.name AS supplier_name, p.name AS product_name
			FROM supplier_incidents si
			JOIN shopping_locations sl ON si.supplier_id = sl.id
			LEFT JOIN products p ON si.product_id = p.id
			WHERE si.status IN ('open', 'claimed') AND si.severity IN ('high', 'critical')
		";
		if ($branchId !== null && $branchId > 0)
		{
			$incidentSql .= " AND si.branch_id = " . intval($branchId);
		}
		$incidents = $dbRaw->query($incidentSql)->fetchAll(\PDO::FETCH_OBJ);

		if (!empty($incidents))
		{
			$incDetails = [];
			foreach ($incidents as $inc)
			{
				$incDetails[] = "{$inc->supplier_name}: Reclamo por {$inc->incident_type} - {$inc->description}";
			}

			$alerts[] = [
				'id' => 'alert_supplier_risk',
				'type' => 'supplier_risk',
				'severity' => 'warning',
				'icon' => 'fa-truck-ramp-box',
				'title' => 'Incidencias Activas con Proveedores',
				'metric_value' => count($incidents) . ' Reclamos',
				'metric_label' => 'Incidencias críticas pendientes',
				'description' => 'Existen reclamos abiertos por demoras o productos no conformes que impactan el nivel de servicio.',
				'details' => $incDetails,
				'recommendation' => 'Exigir nota de crédito o reposición inmediata y contrastar condiciones con otros proveedores en el comparador.'
			];
		}

		// 7. Waste Matrix Causal Analysis (Detección de Causal Dominante)
		$dominantCausalSql = "
			SELECT 
				wr.code, wr.name, wr.category, wr.responsible_area, wr.preventive_actions, wr.associated_kpi,
				COUNT(w.id) AS incidents_count,
				SUM(w.total_cost) AS total_loss_cost
			FROM waste_records w
			JOIN waste_reasons wr ON w.waste_reason_id = wr.id
			WHERE date(w.created_timestamp) >= date('now', '-30 days')
		";
		if ($branchId !== null && $branchId > 0)
		{
			$dominantCausalSql .= " AND w.branch_id = " . intval($branchId);
		}
		$dominantCausalSql .= " GROUP BY wr.id ORDER BY total_loss_cost DESC LIMIT 1";
		$dominantCausal = $dbRaw->query($dominantCausalSql)->fetch(\PDO::FETCH_OBJ);

		if ($dominantCausal && floatval($dominantCausal->total_loss_cost) > 0)
		{
			$alerts[] = [
				'id' => 'alert_waste_causal',
				'type' => 'waste_causal',
				'severity' => (floatval($dominantCausal->total_loss_cost) > 50) ? 'danger' : 'warning',
				'icon' => 'fa-diagram-project',
				'title' => "Causal Dominante de Merma: [{$dominantCausal->code}] {$dominantCausal->name}",
				'metric_value' => '$' . number_format($dominantCausal->total_loss_cost, 2),
				'metric_label' => "Pérdida por {$dominantCausal->incidents_count} incidentes (30 días)",
				'description' => "El análisis de Pareto identifica esta causa en '{$dominantCausal->category}' como la mayor fuente de merma. Área responsable: {$dominantCausal->responsible_area}. KPI: {$dominantCausal->associated_kpi}.",
				'details' => [
					"Protocolo preventivo oficial: " . ($dominantCausal->preventive_actions ?: 'Revisar matriz de causales'),
					"Impacto acumulado en el período: $" . number_format($dominantCausal->total_loss_cost, 2) . " en {$dominantCausal->incidents_count} casos."
				],
				'recommendation' => "Implementar la acción preventiva catalogada: " . ($dominantCausal->preventive_actions ?: 'Revisar matriz de causales') . " y auditar a {$dominantCausal->responsible_area}."
			];
		}

		// 8. Overdue Waste Investigations (Casos con fecha límite vencida)
		$overdueCasesSql = "
			SELECT w.id, w.case_number, p.name AS product_name, w.total_cost, w.due_date, u.username AS investigator_username
			FROM waste_records w
			JOIN products p ON w.product_id = p.id
			LEFT JOIN users u ON w.investigator_user_id = u.id
			WHERE w.workflow_status NOT IN ('closed', 'verified')
				AND w.due_date IS NOT NULL
				AND w.due_date < date('now')
		";
		if ($branchId !== null && $branchId > 0)
		{
			$overdueCasesSql .= " AND w.branch_id = " . intval($branchId);
		}
		$overdueCasesSql .= " ORDER BY w.due_date ASC LIMIT 5";
		$overdueCases = $dbRaw->query($overdueCasesSql)->fetchAll(\PDO::FETCH_OBJ);

		if (!empty($overdueCases))
		{
			$overdueDetails = [];
			$totOverdue = 0.0;
			foreach ($overdueCases as $oc)
			{
				$totOverdue += floatval($oc->total_cost);
				$inv = $oc->investigator_username ?: 'Sin asignar';
				$overdueDetails[] = "{$oc->case_number} ({$oc->product_name}, Vencido: {$oc->due_date}, Resp: {$inv}, Pérdida: $" . number_format($oc->total_cost, 2) . ")";
			}

			$alerts[] = [
				'id' => 'alert_waste_overdue',
				'type' => 'waste_overdue',
				'severity' => 'danger',
				'icon' => 'fa-clock-rotate-left',
				'title' => count($overdueCases) . ' Investigaciones de Merma Vencidas',
				'metric_value' => '$' . number_format($totOverdue, 2),
				'metric_label' => 'Pérdida en casos fuera de plazo',
				'description' => 'Existen casos de merma con plazo de resolución expirado sin cierre de plan correctivo ni verificación formal.',
				'details' => $overdueDetails,
				'recommendation' => 'Exigir dictamen de causa raíz y plan preventivo inmediato a los responsables asignados para cerrar los expedientes.'
			];
		}

		// 9. Healthy operations benchmark indicator
		if (empty($alerts))
		{
			$alerts[] = [
				'id' => 'alert_optimal',
				'type' => 'info',
				'severity' => 'success',
				'icon' => 'fa-shield-check',
				'title' => 'Operaciones en Rango Óptimo',
				'metric_value' => '100% Estable',
				'metric_label' => 'Sin anomalías críticas',
				'description' => 'No se detectaron riesgos de caducidad inmediata ni desviaciones estadísticas en el inventario.',
				'details' => ['La rotación de stock y mermas se encuentran dentro de las tolerancias establecidas.'],
				'recommendation' => 'Continuar con las buenas prácticas de registro en compras y punto de venta.'
			];
		}

		return $alerts;
	}
}
