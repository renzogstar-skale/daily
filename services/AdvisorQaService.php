<?php

namespace Grocy\Services;

/**
 * Arturito IA — preguntas en lenguaje natural respondidas SOLO con datos del sistema.
 * Motor determinístico por intención (sin servicio externo). Cada respuesta separa:
 *   observed       → cifras leídas de la base de datos
 *   estimate       → cálculos/pronósticos (método indicado)
 *   recommendation → sugerencias; nunca se ejecutan solas: enlazan a la página donde un usuario las aprueba y, si traen
 *                    'action', el usuario puede confirmarlas con vista previa (AiActionService, permiso propio y bitácora)
 * Un narrador LLM opcional puede redactar el texto a partir de estos mismos hechos (ver AdvisorNarrator).
 */
class AdvisorQaService extends BaseService
{
	const SUGGESTIONS = [
		'¿Por qué aumentaron las mermas este mes?',
		'¿Hay anomalías de merma?',
		'¿Cuáles son los productos críticos?',
		'¿Cuánto debo reponer?',
		'¿Qué acciones reducen el desperdicio?',
		'Compara las sucursales',
		'Compara este mes con el anterior'
	];

	private function Db(): \PDO
	{
		return DatabaseService::GetInstance()->GetDbConnectionRaw();
	}

	public function Ask(string $question, ?int $branchId = null): array
	{
		$q = mb_strtolower(trim($question));
		$q = strtr($q, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u']);
		$has = fn(...$w) => (bool) array_filter($w, fn($x) => str_contains($q, $x));

		if ($q === '')
		{
			$intent = 'help';
		}
		elseif ($has('anomal', 'atipic', 'raro', 'inusual'))
		{
			$intent = 'anomalies';
		}
		elseif ($has('sucursal', 'tienda', 'branch') && $has('compar', 'mejor', 'peor', 'ranking'))
		{
			$intent = 'branches';
		}
		elseif ($has('compar', 'anterior', 'pasado', 'interanual', 'periodo'))
		{
			$intent = 'periods';
		}
		elseif ($has('repon', 'reposicion', 'comprar', 'pedido', 'pedir', 'orden de compra', 'abastec'))
		{
			$intent = 'replenish';
		}
		elseif ($has('critic', 'riesgo', 'vencer', 'vence', 'caduc'))
		{
			$intent = 'critical';
		}
		elseif ($has('reduc', 'acción', 'accion', 'prevenir', 'evitar', 'desperdicio', 'recomien'))
		{
			$intent = 'actions';
		}
		elseif ($has('por que', 'porque', 'aument', 'subi', 'creci', 'causa'))
		{
			$intent = 'why';
		}
		else
		{
			$intent = 'help';
		}

		$answer = $this->{'Answer' . ucfirst($intent)}($branchId);
		$answer['intent'] = $intent;
		$answer['question'] = $question;
		$answer['branch_id'] = $branchId;
		$answer['generated_at'] = date('Y-m-d H:i:s');
		$answer['suggestions'] = self::SUGGESTIONS;
		return $answer;
	}

	/* ---------------------------------------------------------------- utilidades de datos */

	private function Money(float $v): string
	{
		return '$' . number_format($v, 2);
	}

	private function BranchSql(?int $branchId, string $col = 'w.branch_id'): string
	{
		return $branchId ? " AND $col = " . intval($branchId) : '';
	}

	// Mes en curso hasta hoy vs. mismos días del mes anterior
	private function Windows(): array
	{
		$day = intval(date('j'));
		$prevStart = date('Y-m-01', strtotime('first day of last month'));
		$prevEnd = date('Y-m-d', min(strtotime(date('Y-m-t', strtotime($prevStart))), strtotime($prevStart . ' +' . ($day - 1) . ' days')));
		return [[date('Y-m-01'), date('Y-m-d')], [$prevStart, $prevEnd]];
	}

	private function WasteBy(string $groupSql, string $labelSql, string $from, string $to, ?int $branchId, string $join = ''): array
	{
		$stmt = $this->Db()->prepare("SELECT $groupSql AS k, $labelSql AS label, SUM(w.total_cost) AS cost, SUM(w.amount) AS units, COUNT(*) AS n
			FROM waste_records w LEFT JOIN waste_reasons r ON r.id = w.waste_reason_id LEFT JOIN products p ON p.id = w.product_id $join
			WHERE w.status = 'approved' AND date(w.created_timestamp) BETWEEN :f AND :t" . $this->BranchSql($branchId) . " GROUP BY k");
		$stmt->execute([':f' => $from, ':t' => $to]);
		$out = [];
		foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $r)
		{
			$out[$r['k']] = $r;
		}
		return $out;
	}

	private function Delta(array $cur, array $prev): array
	{
		$rows = [];
		foreach (array_unique(array_merge(array_keys($cur), array_keys($prev))) as $k)
		{
			$c = floatval($cur[$k]['cost'] ?? 0);
			$p = floatval($prev[$k]['cost'] ?? 0);
			$rows[] = ['label' => $cur[$k]['label'] ?? $prev[$k]['label'] ?? strval($k), 'current' => round($c, 2), 'previous' => round($p, 2), 'delta' => round($c - $p, 2)];
		}
		usort($rows, fn($a, $b) => $b['delta'] <=> $a['delta']);
		return $rows;
	}

	/* ---------------------------------------------------------------- intenciones */

	private function AnswerWhy(?int $branchId): array
	{
		[[$cs, $ce], [$ps, $pe]] = $this->Windows();
		$byReason = $this->Delta($this->WasteBy('w.waste_reason_id', "IFNULL(r.code || ' ' || r.name, 'Sin causal')", $cs, $ce, $branchId), $this->WasteBy('w.waste_reason_id', "IFNULL(r.code || ' ' || r.name, 'Sin causal')", $ps, $pe, $branchId));
		$byProduct = $this->Delta($this->WasteBy('w.product_id', "IFNULL(p.name, 'Producto #' || w.product_id)", $cs, $ce, $branchId), $this->WasteBy('w.product_id', "IFNULL(p.name, 'Producto #' || w.product_id)", $ps, $pe, $branchId));
		$cur = array_sum(array_column($byReason, 'current'));
		$prev = array_sum(array_column($byReason, 'previous'));
		$diff = $cur - $prev;

		$observed = ["Merma aprobada del {$cs} al {$ce}: " . $this->Money($cur) . " vs " . $this->Money($prev) . " en los mismos días del mes anterior ({$ps} al {$pe}) → " . ($diff >= 0 ? 'sube ' : 'baja ') . $this->Money(abs($diff)) . ($prev > 0 ? ' (' . round($diff / $prev * 100, 1) . ' %)' : '') . '.'];
		$up = array_values(array_filter($byReason, fn($r) => $r['delta'] > 0));
		foreach (array_slice($up, 0, 3) as $r)
		{
			$observed[] = "Causal {$r['label']}: " . $this->Money($r['previous']) . ' → ' . $this->Money($r['current']) . ' (+' . $this->Money($r['delta']) . ').';
		}
		foreach (array_slice(array_filter($byProduct, fn($r) => $r['delta'] > 0), 0, 3) as $r)
		{
			$observed[] = "Producto {$r['label']}: +" . $this->Money($r['delta']) . ' frente al mes anterior.';
		}

		$recs = [];
		if ($diff > 0 && count($up))
		{
			$top = $this->Db()->prepare("SELECT preventive_actions FROM waste_reasons WHERE IFNULL(code || ' ' || name, '') = :l");
			$top->execute([':l' => $up[0]['label']]);
			$prevAction = $top->fetchColumn();
			$share = $diff > 0 ? round($up[0]['delta'] / $diff * 100) : 0;
			$recs[] = ['text' => "La causal {$up[0]['label']} explica ~{$share} % del aumento. " . ($prevAction ? "Medida preventiva registrada: {$prevAction}." : 'Defina su medida preventiva en Causales.'), 'href' => '/waste#tab-prevencion'];
			$recs[] = ['text' => 'Abra un plan de acción para los 3 productos con mayor aumento y verifique la causa en sitio (5 porqués).', 'href' => '/waste', 'action' => 'action_plan'];
		}
		elseif ($diff <= 0)
		{
			$recs[] = ['text' => 'La merma no aumentó frente al mismo tramo del mes anterior; mantener controles de FEFO y temperatura.', 'href' => '/quality'];
		}

		return [
			'title' => $diff > 0 ? 'Por qué aumentó la merma' : 'Evolución de la merma',
			'sections' => [
				['type' => 'observed', 'items' => $observed, 'table' => ['columns' => ['Causal', 'Mes anterior', 'Mes actual', 'Δ'], 'rows' => array_map(fn($r) => [$r['label'], $this->Money($r['previous']), $this->Money($r['current']), ($r['delta'] >= 0 ? '+' : '') . $this->Money($r['delta'])], array_slice($byReason, 0, 6))]],
				['type' => 'recommendation', 'items' => $recs]
			],
			'sources' => ['waste_records (aprobadas)', 'waste_reasons']
		];
	}

	private function AnswerAnomalies(?int $branchId): array
	{
		// Producto cuya merma de los últimos 7 días supera la media + 2σ de sus 8 semanas anteriores
		$stmt = $this->Db()->prepare("SELECT w.product_id, IFNULL(p.name, 'Producto #' || w.product_id) AS name,
				CAST((julianday(date('now', 'localtime')) - julianday(date(w.created_timestamp))) / 7 AS INTEGER) AS wk, SUM(w.total_cost) AS cost
			FROM waste_records w LEFT JOIN products p ON p.id = w.product_id
			WHERE w.status = 'approved' AND date(w.created_timestamp) > date('now', 'localtime', '-63 days')" . $this->BranchSql($branchId) . '
			GROUP BY w.product_id, wk');
		$stmt->execute();
		$series = [];
		foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $r)
		{
			$series[$r['product_id']]['name'] = $r['name'];
			$series[$r['product_id']]['w'][intval($r['wk'])] = floatval($r['cost']);
		}
		$anom = [];
		foreach ($series as $s)
		{
			$hist = [];
			for ($k = 1; $k <= 8; $k++)
			{
				$hist[] = $s['w'][$k] ?? 0;
			}
			$mean = array_sum($hist) / 8;
			$sd = sqrt(array_sum(array_map(fn($x) => ($x - $mean) ** 2, $hist)) / 8);
			$now = $s['w'][0] ?? 0;
			$limit = $mean + 2 * $sd;
			if ($now > 0 && $now > $limit && $now - $mean >= 5)
			{
				$anom[] = [$s['name'], $this->Money($now), $this->Money($mean), $this->Money($limit)];
			}
		}
		usort($anom, fn($a, $b) => floatval(substr($b[1], 1)) <=> floatval(substr($a[1], 1)));

		// Mermas registradas fuera de horario de turnos (si hay turnos cargados) — dato, no culpa
		return [
			'title' => count($anom) ? count($anom) . ' anomalías de merma esta semana' : 'Sin anomalías de merma esta semana',
			'sections' => [
				['type' => 'observed', 'items' => count($anom) ? ['Productos cuya merma de los últimos 7 días supera su media semanal + 2 desviaciones (8 semanas previas):'] : ['Ningún producto supera su media semanal + 2 desviaciones de las 8 semanas previas.'], 'table' => count($anom) ? ['columns' => ['Producto', 'Últimos 7 días', 'Media semanal', 'Umbral (μ+2σ)'], 'rows' => array_slice($anom, 0, 8)] : null],
				['type' => 'estimate', 'items' => ['Método: control estadístico por producto (μ + 2σ); requiere al menos un evento previo para tener referencia.']],
				['type' => 'recommendation', 'items' => count($anom) ? [['text' => 'Investigue primero el producto con mayor desvío: revise lote, temperatura de la cámara y registro de recepción.', 'href' => '/waste']] : []]
			],
			'sources' => ['waste_records (aprobadas, 63 días)']
		];
	}

	private function AnswerCritical(?int $branchId): array
	{
		$b = $branchId ? ' AND l.branch_id = ' . intval($branchId) : '';
		$exp = $this->Db()->query("SELECT p.name, SUM(s.amount) AS qty, MIN(s.best_before_date) AS bbd, SUM(s.amount * IFNULL(s.price, 0)) AS value
			FROM stock s JOIN products p ON p.id = s.product_id LEFT JOIN locations l ON l.id = s.location_id
			WHERE s.best_before_date <= date('now', 'localtime', '+3 days') AND s.best_before_date != '2999-12-31' $b
			GROUP BY p.id ORDER BY value DESC LIMIT 8")->fetchAll(\PDO::FETCH_NUM);
		[[$cs, $ce]] = $this->Windows();
		$top = array_slice($this->Delta($this->WasteBy('w.product_id', "IFNULL(p.name, 'Producto #' || w.product_id)", $cs, $ce, $branchId), []), 0, 5);
		$fc = MrpService::GetInstance()->GetForecastAccuracy($branchId, 4);

		$items = [];
		$items[] = count($exp) . ' productos con lotes vencidos o que vencen en ≤ 3 días (' . $this->Money(array_sum(array_column($exp, 3))) . ' a costo).';
		$items[] = count($fc['stockouts']) . ' productos en quiebre (vendieron en 28 días y no tienen stock libre)' . (count($fc['stockouts']) ? ': ' . implode(', ', array_slice(array_column($fc['stockouts'], 'product_name'), 0, 5)) . '.' : '.');
		if (count($top))
		{
			$items[] = 'Mayor merma del mes: ' . implode('; ', array_map(fn($r) => $r['label'] . ' ' . $this->Money($r['current']), $top)) . '.';
		}
		return [
			'title' => 'Productos críticos',
			'sections' => [
				['type' => 'observed', 'items' => $items, 'table' => count($exp) ? ['columns' => ['Producto', 'Cantidad', 'Vence', 'Valor'], 'rows' => array_map(fn($r) => [$r[0], round($r[1], 2), $r[2], $this->Money(floatval($r[3]))], $exp)] : null],
				['type' => 'recommendation', 'items' => array_values(array_filter([
					count($exp) ? ['text' => 'Aplique rebaja FEFO o traslado a los lotes por vencer antes de que se conviertan en merma (requiere aprobación en POS/Calidad).', 'href' => '/quality', 'action' => 'fefo_task'] : null,
					count($fc['stockouts']) ? ['text' => 'Revise las órdenes sugeridas para los productos en quiebre.', 'href' => '/mrp'] : null
				]))]
			],
			'sources' => ['stock (lotes)', 'waste_records', 'sale_items (28 días)']
		];
	}

	private function AnswerReplenish(?int $branchId): array
	{
		$mrp = MrpService::GetInstance()->CalculateMrp($branchId ?: 1);
		$need = array_values(array_filter($mrp['items'], fn($i) => ($i['status'] ?? '') === 'reorder_needed' && floatval($i['suggested_order'] ?? 0) > 0));
		usort($need, fn($a, $b) => floatval($b['estimated_cost'] ?? 0) <=> floatval($a['estimated_cost'] ?? 0));
		$fc = MrpService::GetInstance()->GetForecastAccuracy($branchId ?: 1, 4);
		$rows = array_map(fn($i) => [
			$i['product_name'], round(floatval($i['current_stock'] ?? 0), 2), round(floatval($i['vmd'] ?? 0), 2),
			round(floatval($i['suggested_order'] ?? 0), 2), $this->Money(floatval($i['estimated_cost'] ?? 0))
		], array_slice($need, 0, 10));
		return [
			'title' => count($need) . ' productos a reponer',
			'sections' => [
				['type' => 'observed', 'items' => ['Stock actual y venta diaria de los últimos 28 días por producto (sucursal ' . ($branchId ?: 1) . ').'], 'table' => count($rows) ? ['columns' => ['Producto', 'Stock', 'Venta/día', 'Sugerido', 'Costo'], 'rows' => $rows] : null],
				['type' => 'estimate', 'items' => [
					'Cantidades del MRP: demanda ponderada 4 semanas × (lead time + revisión) + stock de seguridad − stock neto (descuenta vencidos, cuarentena y merma proyectada; suma OC abiertas), tope por vida útil.',
					'Precisión reciente del pronóstico: WAPE ' . ($fc['wape_pct'] ?? '—') . ' %, sesgo ' . ($fc['bias_pct'] ?? '—') . ' %' . (($fc['wape_pct'] ?? 0) > 50 ? ' — error alto: revise las cantidades antes de aprobar.' : '.')
				]],
				['type' => 'recommendation', 'items' => count($need) ? [['text' => 'Genere las órdenes en borrador desde MRP; un usuario con permiso de compras debe aprobarlas y enviarlas.', 'href' => '/mrp', 'action' => 'draft_po']] : []]
			],
			'sources' => ['MrpService::CalculateMrp', 'sale_items', 'stock', 'procurement_orders']
		];
	}

	private function AnswerActions(?int $branchId): array
	{
		[[$cs, $ce]] = $this->Windows();
		$reasons = $this->Db()->prepare("SELECT r.code || ' ' || r.name AS label, r.preventive_actions, SUM(w.total_cost) AS cost FROM waste_records w JOIN waste_reasons r ON r.id = w.waste_reason_id
			WHERE w.status = 'approved' AND date(w.created_timestamp) BETWEEN :f AND :t" . $this->BranchSql($branchId) . ' GROUP BY r.id ORDER BY cost DESC LIMIT 3');
		$reasons->execute([':f' => $cs, ':t' => $ce]);
		$recs = [];
		foreach ($reasons->fetchAll(\PDO::FETCH_ASSOC) as $r)
		{
			$recs[] = ['text' => "{$r['label']} (" . $this->Money(floatval($r['cost'])) . ' este mes): ' . ($r['preventive_actions'] ?: 'sin medida preventiva registrada; defínala en Causales.'), 'href' => '/waste#tab-prevencion'];
		}
		$mrp = MrpService::GetInstance()->CalculateMrp($branchId ?: 1);
		$over = array_values(array_filter($mrp['items'], fn($i) => ($i['status'] ?? '') === 'overstock_risk'));
		if (count($over))
		{
			$recs[] = ['text' => count($over) . ' productos con cobertura mayor al 80 % de su vida útil (' . implode(', ', array_slice(array_column($over, 'product_name'), 0, 3)) . '): reduzca o pause pedidos.', 'href' => '/mrp'];
		}
		$plans = $this->Db()->query("SELECT COUNT(*), IFNULL(SUM(cost_avoided_estimate), 0) FROM waste_action_plans WHERE status IN ('completed', 'verified')" . ($branchId ? ' AND branch_id = ' . intval($branchId) : ''))->fetch(\PDO::FETCH_NUM);
		$open = intval($this->Db()->query("SELECT COUNT(*) FROM workforce_store_tasks WHERE status = 'pending' AND source_type = 'waste'" . ($branchId ? ' AND branch_id = ' . intval($branchId) : ''))->fetchColumn());
		return [
			'title' => 'Acciones para reducir el desperdicio',
			'sections' => [
				['type' => 'observed', 'items' => ["{$open} acciones correctivas de merma pendientes en piso.", intval($plans[0]) . ' planes de acción cerrados.']],
				['type' => 'estimate', 'items' => [floatval($plans[1]) > 0 ? 'Ahorro estimado declarado en planes cerrados: ' . $this->Money(floatval($plans[1])) . ' (estimación del responsable, no verificada contra merma real).' : 'Sin ahorro estimado registrado en los planes cerrados.']],
				['type' => 'recommendation', 'items' => $recs]
			],
			'sources' => ['waste_reasons.preventive_actions', 'MrpService (sobreinventario)', 'waste_action_plans', 'workforce_store_tasks']
		];
	}

	private function AnswerBranches(?int $branchId): array
	{
		$r = CostCenterService::GetInstance()->GetReport(date('Y-m'));
		$r['branches'] = \Grocy\Helpers\DairyAccess::FilterBranches($r['branches']);
		$rows = array_map(fn($b) => [$b['name'], $this->Money($b['sales']), $this->Money($b['waste']), $b['waste_pct'] !== null ? $b['waste_pct'] . ' %' : '—', $b['net_margin_pct'] !== null ? $b['net_margin_pct'] . ' %' : '—'], $r['branches']);
		$withSales = array_values(array_filter($r['branches'], fn($b) => $b['waste_pct'] !== null));
		usort($withSales, fn($a, $b) => $b['waste_pct'] <=> $a['waste_pct']);
		$items = ['Mes en curso por centro de costo (ventas, merma aprobada y margen tras merma).'];
		if (count($withSales) >= 2)
		{
			$items[] = "Mayor merma relativa: {$withSales[0]['name']} ({$withSales[0]['waste_pct']} %); menor: " . end($withSales)['name'] . ' (' . end($withSales)['waste_pct'] . ' %).';
		}
		foreach ($r['branches'] as $b)
		{
			if ($b['sales'] == 0 && $b['waste'] > 0)
			{
				$items[] = "{$b['name']} registra merma sin ventas en el mes.";
			}
		}
		return [
			'title' => 'Comparación de sucursales',
			'sections' => [
				['type' => 'observed', 'items' => $items, 'table' => ['columns' => ['Sucursal', 'Ventas', 'Merma', 'Merma %', 'Margen tras merma'], 'rows' => $rows]],
				['type' => 'recommendation', 'items' => count($withSales) ? [['text' => "Revise el detalle por categoría de {$withSales[0]['name']}.", 'href' => '/cost-centers?branch_id=' . ($withSales[0]['id'] ?? '')]] : []]
			],
			'sources' => ['CostCenterService (sale_items, waste_records)']
		];
	}

	private function AnswerPeriods(?int $branchId): array
	{
		$k = KpiService::GetInstance()->GetExecutiveDashboardData($branchId, 'month');
		$d = $k['kpi_deltas'];
		$y = $k['yoy'];
		return [
			'title' => 'Este mes frente a periodos anteriores',
			'sections' => [
				['type' => 'observed', 'items' => [
					'Ventas ' . $this->Money($k['total_sales']) . " ({$d['label']}: " . ($d['sales']['delta_pct'] >= 0 ? '+' : '') . $d['sales']['delta_pct'] . ' %).',
					'Merma ' . $this->Money($k['total_waste_cost']) . ' = ' . $k['waste_ratio_percentage'] . ' % s/' . $k['waste_pct_base']['label'] . " ({$d['label']}: " . ($d['waste']['delta_pct'] >= 0 ? '+' : '') . $d['waste']['delta_pct'] . ' %).',
					'Margen bruto ' . $k['margin_percentage'] . ' % (' . ($d['margin']['points_delta'] >= 0 ? '+' : '') . $d['margin']['points_delta'] . ' pts).',
					$y['has_data'] ? 'Interanual (' . $y['label'] . '): ventas ' . ($y['sales_delta_pct'] ?? '—') . ' %, merma ' . ($y['waste_delta_pct'] ?? '—') . ' %.' : 'Sin datos del mismo periodo del año anterior.'
				]],
				['type' => 'estimate', 'items' => ['Si el periodo anterior no tuvo registros en los mismos días, la comparación usa el mes anterior completo prorrateado por día.', 'Proyección al cierre al ritmo actual: ventas ' . $this->Money($k['month_projection']['projected_sales']) . ', merma ' . $this->Money($k['month_projection']['projected_waste']) . ' (' . $k['month_projection']['projected_waste_ratio_pct'] . ' %).']],
				['type' => 'recommendation', 'items' => $k['waste_ratio_percentage'] > $k['month_projection']['waste_target_ratio_pct'] ? [['text' => 'La merma supera la meta (' . $k['month_projection']['waste_target_ratio_pct'] . ' %): pregunte "¿Por qué aumentaron las mermas?" para ver las causas.', 'href' => null]] : []]
			],
			'sources' => ['KpiService (sales, waste_records, presupuesto)']
		];
	}

	private function AnswerHelp(?int $branchId): array
	{
		return [
			'title' => 'Puedo responder con los datos de DAIRY',
			'sections' => [['type' => 'observed', 'items' => ['Pregunte por: causas del aumento de merma, anomalías, productos críticos, reposición, acciones para reducir desperdicio, comparación de sucursales o de periodos.']]],
			'sources' => []
		];
	}
}
