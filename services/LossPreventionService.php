<?php

namespace Grocy\Services;

/**
 * Prevención de pérdidas sobre datos reales (sin hardware externo):
 *  - Stock fantasma: producto con stock en sistema y venta regular que lleva una racha sin ventas improbable.
 *  - Matriz ABC-XYZ: valor (ventas 90 d, Pareto 80/95) × variabilidad de la demanda semanal (CV de 12 semanas).
 *  - Pérdida total clasificada: conocida (merma por clase), desconocida (ajustes de inventario), salidas sin documento,
 *    ventas sin descargo de inventario (conciliación POS ↔ stock), rechazos en recepción y recuperación financiera.
 */
class LossPreventionService extends BaseService
{
	const XYZ_POLICY = [
		'AX' => 'Reposición automática MRP; conteo semanal; stock de seguridad bajo',
		'AY' => 'MRP con revisión semanal; vigilar estacionalidad',
		'AZ' => 'Pedido revisado por el gerente; stock de seguridad alto; vigilar stock fantasma',
		'BX' => 'Reposición automática; conteo mensual',
		'BY' => 'MRP estándar; conteo mensual',
		'BZ' => 'Pedido bajo demanda; evitar sobreinventario',
		'CX' => 'Lote económico; conteo trimestral',
		'CY' => 'Reducir surtido o frecuencia de pedido',
		'CZ' => 'Candidato a descatalogar o pedido solo contra demanda',
	];

	private function Db(): \PDO
	{
		return DatabaseService::GetInstance()->GetDbConnectionRaw();
	}

	private function Rows(string $sql, array $p = []): array
	{
		$stmt = $this->Db()->prepare($sql);
		$stmt->execute($p);
		return $stmt->fetchAll(\PDO::FETCH_ASSOC);
	}

	/**
	 * Stock fantasma. f = días con venta / días observados; la probabilidad de una racha de g días sin ventas siendo casual
	 * es (1 − f)^g. Se marca cuando es ≤ $maxProbability (regla phantom_stock) y la racha es de al menos 2 días.
	 */
	public function PhantomStock(?int $branchId, ?float $maxProbability = null, int $windowDays = 56): array
	{
		$maxProbability ??= floatval($this->Db()->query("SELECT IFNULL((SELECT threshold FROM dairy_alert_rules WHERE code = 'phantom_stock'), 0.05)")->fetchColumn());
		$today = date('Y-m-d');
		$since = date('Y-m-d', strtotime("-$windowDays days"));
		$bStock = $branchId ? ' AND l.branch_id = :b' : '';
		$bSale = $branchId ? ' AND s.branch_id = :b' : '';
		$p = $branchId ? [':b' => $branchId] : [];

		$stock = [];
		foreach ($this->Rows("SELECT st.product_id, p.name, SUM(st.amount) AS stock, SUM(st.amount * IFNULL(st.price, 0)) AS value,
				(SELECT st2.location_id FROM stock st2 JOIN locations l2 ON l2.id = st2.location_id WHERE st2.product_id = st.product_id AND IFNULL(l2.is_transit, 0) = 0" . ($branchId ? ' AND l2.branch_id = :b' : '') . " GROUP BY st2.location_id ORDER BY SUM(st2.amount) DESC LIMIT 1) AS location_id
			FROM stock st JOIN locations l ON l.id = st.location_id JOIN products p ON p.id = st.product_id
			WHERE IFNULL(l.is_transit, 0) = 0 AND p.active = 1$bStock GROUP BY st.product_id HAVING SUM(st.amount) > 0", $p) as $r)
		{
			$stock[$r['product_id']] = $r;
		}
		if (count($stock) === 0)
		{
			return [];
		}

		$sales = $this->Rows("SELECT si.product_id, COUNT(DISTINCT date(s.created_timestamp)) AS sale_days, SUM(si.amount) AS units, SUM(si.subtotal) / NULLIF(SUM(si.amount), 0) AS unit_price,
				MIN(date(s.created_timestamp)) AS first_sale, MAX(date(s.created_timestamp)) AS last_sale
			FROM sale_items si JOIN sales s ON s.id = si.sale_id
			WHERE s.status = 'completed' AND date(s.created_timestamp) >= :since$bSale GROUP BY si.product_id", $p + [':since' => $since]);
		$pending = array_flip(array_map('intval', $this->Db()->query("SELECT DISTINCT product_id FROM stock_cycle_counts WHERE status = 'pending'" . ($branchId ? ' AND branch_id = ' . intval($branchId) : ''))->fetchAll(\PDO::FETCH_COLUMN)));

		$out = [];
		foreach ($sales as $s)
		{
			$pid = intval($s['product_id']);
			if (!isset($stock[$pid]) || intval($s['sale_days']) < 4)
			{
				continue; // sin stock o sin historia suficiente para inferir una frecuencia
			}
			// Días observados: desde la primera venta de la ventana (un producto nuevo no se penaliza por días previos)
			$observed = max(1, (strtotime($today) - strtotime($s['first_sale'])) / 86400 + 1);
			$f = min(1.0, intval($s['sale_days']) / $observed);
			$gap = intval(round((strtotime($today) - strtotime($s['last_sale'])) / 86400));
			if ($gap < 2 || $f <= 0)
			{
				continue;
			}
			$prob = pow(1 - $f, $gap);
			if ($prob > $maxProbability)
			{
				continue;
			}
			$daily = floatval($s['units']) / $observed;
			$lostUnits = round($daily * $gap, 2);
			$out[] = [
				'product_id' => $pid,
				'product_name' => $stock[$pid]['name'],
				'location_id' => $stock[$pid]['location_id'] !== null ? intval($stock[$pid]['location_id']) : null,
				'system_stock' => round(floatval($stock[$pid]['stock']), 2),
				'stock_value' => round(floatval($stock[$pid]['value']), 2),
				'sale_days' => intval($s['sale_days']),
				'observed_days' => intval($observed),
				'sell_frequency_pct' => round($f * 100, 1),
				'last_sale' => $s['last_sale'],
				'days_without_sale' => $gap,
				'probability' => round($prob, 4),
				'lost_sales_units' => $lostUnits,
				'lost_sales_value' => round($lostUnits * floatval($s['unit_price']), 2),
				'count_pending' => isset($pending[$pid]),
			];
		}
		usort($out, fn($a, $b) => [$a['probability'], -$a['lost_sales_value']] <=> [$b['probability'], -$b['lost_sales_value']]);
		return $out;
	}

	/**
	 * Matriz ABC-XYZ por sucursal (o red). XYZ por coeficiente de variación de las unidades semanales (12 semanas):
	 * X ≤ 0.5, Y ≤ 1.0, Z > 1.0 o sin ventas.
	 */
	public function AbcXyz(?int $branchId): array
	{
		$weeks = 12;
		$since = date('Y-m-d', strtotime('-' . ($weeks * 7) . ' days'));
		$since90 = date('Y-m-d', strtotime('-90 days'));
		$bSale = $branchId ? ' AND s.branch_id = :b' : '';
		$p = $branchId ? [':b' => $branchId] : [];

		$revenue = [];
		foreach ($this->Rows("SELECT si.product_id, SUM(si.subtotal) AS rev FROM sale_items si JOIN sales s ON s.id = si.sale_id WHERE s.status = 'completed' AND date(s.created_timestamp) >= :d$bSale GROUP BY si.product_id", $p + [':d' => $since90]) as $r)
		{
			$revenue[intval($r['product_id'])] = floatval($r['rev']);
		}
		$weekly = [];
		foreach ($this->Rows("SELECT si.product_id, CAST((julianday(date(s.created_timestamp)) - julianday(:d)) / 7 AS INTEGER) AS w, SUM(si.amount) AS units
			FROM sale_items si JOIN sales s ON s.id = si.sale_id WHERE s.status = 'completed' AND date(s.created_timestamp) >= :d$bSale GROUP BY si.product_id, w", $p + [':d' => $since]) as $r)
		{
			$weekly[intval($r['product_id'])][min($weeks - 1, intval($r['w']))] = floatval($r['units']);
		}
		$bStock = $branchId ? ' AND l.branch_id = ' . intval($branchId) : '';
		$names = [];
		foreach ($this->Db()->query("SELECT p.id, p.name, IFNULL((SELECT SUM(st.amount) FROM stock st JOIN locations l ON l.id = st.location_id WHERE st.product_id = p.id AND IFNULL(l.is_transit, 0) = 0$bStock), 0) AS stock FROM products p WHERE p.active = 1")->fetchAll(\PDO::FETCH_ASSOC) as $r)
		{
			if (floatval($r['stock']) > 0 || isset($revenue[intval($r['id'])]))
			{
				$names[intval($r['id'])] = ['name' => $r['name'], 'stock' => round(floatval($r['stock']), 2)];
			}
		}

		$ids = array_keys($names);
		usort($ids, fn($a, $b) => ($revenue[$b] ?? 0) <=> ($revenue[$a] ?? 0));
		$total = array_sum($revenue);
		$cum = 0.0;
		$items = [];
		$matrix = [];
		foreach (['A', 'B', 'C'] as $a)
		{
			foreach (['X', 'Y', 'Z'] as $x)
			{
				$matrix[$a . $x] = ['count' => 0, 'revenue' => 0.0, 'policy' => self::XYZ_POLICY[$a . $x]];
			}
		}
		foreach ($ids as $id)
		{
			$rev = $revenue[$id] ?? 0.0;
			$cum += $rev;
			$cumPct = $total > 0 ? $cum / $total * 100 : 100;
			$abc = $rev <= 0 ? 'C' : ($cumPct <= 80 ? 'A' : ($cumPct <= 95 ? 'B' : 'C'));
			$series = [];
			for ($w = 0; $w < $weeks; $w++)
			{
				$series[] = $weekly[$id][$w] ?? 0.0;
			}
			$mean = array_sum($series) / $weeks;
			$cv = null;
			if ($mean > 0)
			{
				$var = array_sum(array_map(fn($v) => ($v - $mean) ** 2, $series)) / $weeks;
				$cv = sqrt($var) / $mean;
			}
			$xyz = $cv === null ? 'Z' : ($cv <= 0.5 ? 'X' : ($cv <= 1.0 ? 'Y' : 'Z'));
			$cell = $abc . $xyz;
			$matrix[$cell]['count']++;
			$matrix[$cell]['revenue'] += $rev;
			$items[] = ['product_id' => $id, 'product_name' => $names[$id]['name'], 'stock' => $names[$id]['stock'], 'revenue_90d' => round($rev, 2), 'weekly_mean' => round($mean, 2), 'cv' => $cv !== null ? round($cv, 2) : null, 'abc' => $abc, 'xyz' => $xyz, 'cell' => $cell];
		}
		foreach ($matrix as &$m)
		{
			$m['revenue'] = round($m['revenue'], 2);
		}
		return ['matrix' => $matrix, 'items' => $items, 'weeks' => $weeks];
	}

	/**
	 * Venta conciliada = la línea tiene transaction_id de stock o existe un consumo del mismo producto ±2 min
	 * (ventas anteriores a la migración 0270 no guardaban la transacción).
	 */
	public function PosReconciliation(?int $branchId, string $from, string $to): array
	{
		$b = $branchId ? ' AND s.branch_id = :b' : '';
		$p = [':f' => $from, ':t' => $to] + ($branchId ? [':b' => $branchId] : []);
		return $this->Rows("SELECT s.id AS sale_id, s.sale_number, s.branch_id, s.created_timestamp, si.product_id, IFNULL(pr.name, 'Producto #' || si.product_id) AS product_name, si.amount, si.subtotal,
				IFNULL(NULLIF(si.total_cost, 0), si.amount * IFNULL(si.unit_cost, 0)) AS cost
			FROM sale_items si JOIN sales s ON s.id = si.sale_id LEFT JOIN products pr ON pr.id = si.product_id
			WHERE s.status = 'completed' AND date(s.created_timestamp) BETWEEN :f AND :t$b
				AND si.transaction_id IS NULL
				AND NOT EXISTS (SELECT 1 FROM stock_log l WHERE l.product_id = si.product_id AND l.transaction_type = 'consume' AND l.spoiled = 0 AND l.undone = 0
					AND ABS(julianday(l.row_created_timestamp) - julianday(s.created_timestamp)) * 1440 <= 2)
			ORDER BY s.created_timestamp DESC", $p);
	}

	/**
	 * Consumos no explicados por venta, merma, cuarentena ni retiro (no incluye traslados ni ajustes, que son otros tipos).
	 */
	public function UnexplainedOutflows(?int $branchId, string $from, string $to): array
	{
		$b = $branchId ? ' AND loc.branch_id = :b' : '';
		$p = [':f' => $from, ':t' => $to] + ($branchId ? [':b' => $branchId] : []);
		return $this->Rows("SELECT l.id, l.transaction_id, l.product_id, IFNULL(pr.name, 'Producto #' || l.product_id) AS product_name, ABS(l.amount) AS amount, ABS(l.amount * IFNULL(l.price, 0)) AS value,
				l.row_created_timestamp, loc.branch_id, IFNULL(NULLIF(TRIM(IFNULL(u.first_name, '') || ' ' || IFNULL(u.last_name, '')), ''), IFNULL(u.username, 'sistema')) AS user_name
			FROM stock_log l LEFT JOIN locations loc ON loc.id = l.location_id LEFT JOIN products pr ON pr.id = l.product_id LEFT JOIN users u ON u.id = l.user_id
			WHERE l.transaction_type = 'consume' AND l.spoiled = 0 AND l.undone = 0 AND l.amount < 0 AND date(l.row_created_timestamp) BETWEEN :f AND :t$b
				AND (l.transaction_id IS NULL OR (
					l.transaction_id NOT IN (SELECT transaction_id FROM sale_items WHERE transaction_id IS NOT NULL)
					AND l.transaction_id NOT IN (SELECT transaction_id FROM waste_records WHERE transaction_id IS NOT NULL)))
				AND NOT EXISTS (SELECT 1 FROM sale_items si JOIN sales s ON s.id = si.sale_id WHERE si.product_id = l.product_id AND s.status = 'completed'
					AND ABS(julianday(l.row_created_timestamp) - julianday(s.created_timestamp)) * 1440 <= 2)
			ORDER BY value DESC", $p);
	}

	/**
	 * Pérdida total del período: bruta = conocida + desconocida neta (si es pérdida) + salidas sin documento; neta = bruta − recuperado.
	 */
	public function TotalLoss(?int $branchId, string $from, string $to): array
	{
		$p = [':f' => $from, ':t' => $to] + ($branchId ? [':b' => $branchId] : []);
		$bW = $branchId ? ' AND w.branch_id = :b' : '';

		$known = $this->Rows("SELECT IFNULL(NULLIF(r.akcal_classification, ''), IFNULL(r.category, 'sin clase')) AS class, COUNT(*) AS records, SUM(w.total_cost) AS cost
			FROM waste_records w LEFT JOIN waste_reasons r ON r.id = w.waste_reason_id
			WHERE w.status = 'approved' AND date(w.created_timestamp) BETWEEN :f AND :t$bW GROUP BY class ORDER BY cost DESC", $p);
		$knownTotal = array_sum(array_map(fn($r) => floatval($r['cost']), $known));

		$adj = $this->Rows("SELECT SUM(CASE WHEN l.amount < 0 THEN -l.amount * IFNULL(l.price, 0) ELSE 0 END) AS loss, SUM(CASE WHEN l.amount > 0 THEN l.amount * IFNULL(l.price, 0) ELSE 0 END) AS gain, COUNT(*) AS n
			FROM stock_log l LEFT JOIN locations loc ON loc.id = l.location_id
			WHERE l.transaction_type = 'inventory-correction' AND l.undone = 0 AND date(l.row_created_timestamp) BETWEEN :f AND :t" . ($branchId ? ' AND loc.branch_id = :b' : ''), $p)[0];
		$unknownLoss = floatval($adj['loss']);
		$unknownGain = floatval($adj['gain']);
		$unknownNet = $unknownLoss - $unknownGain;

		$outflows = $this->UnexplainedOutflows($branchId, $from, $to);
		$outflowTotal = array_sum(array_map(fn($r) => floatval($r['value']), $outflows));
		$pos = $this->PosReconciliation($branchId, $from, $to);
		$posTotal = array_sum(array_map(fn($r) => floatval($r['cost']), $pos));

		$bS = $branchId ? ' AND branch_id = :b' : '';
		$rejections = floatval($this->Rows("SELECT IFNULL(SUM(cost_impact), 0) AS v FROM supplier_incidents WHERE date(IFNULL(incident_date, row_created_timestamp)) BETWEEN :f AND :t$bS", $p)[0]['v']);
		$recovered = floatval($this->Rows("SELECT IFNULL(SUM(credit_recovered_amount), 0) AS v FROM supplier_returns WHERE date(created_timestamp) BETWEEN :f AND :t$bS", $p)[0]['v']);
		$sales = floatval($this->Rows("SELECT IFNULL(SUM(total_amount), 0) AS v FROM sales WHERE status = 'completed' AND date(created_timestamp) BETWEEN :f AND :t$bS", $p)[0]['v']);

		$gross = $knownTotal + max(0, $unknownNet) + $outflowTotal;
		$net = $gross - $recovered;
		return [
			'from' => $from, 'to' => $to, 'branch_id' => $branchId,
			'sales' => round($sales, 2),
			'known' => ['total' => round($knownTotal, 2), 'by_class' => $known],
			'unknown' => ['loss' => round($unknownLoss, 2), 'gain' => round($unknownGain, 2), 'net' => round($unknownNet, 2), 'adjustments' => intval($adj['n'])],
			'unexplained_outflows' => ['total' => round($outflowTotal, 2), 'count' => count($outflows), 'rows' => array_slice($outflows, 0, 25)],
			'pos_gap' => ['total' => round($posTotal, 2), 'count' => count($pos), 'sales' => count(array_unique(array_column($pos, 'sale_id'))), 'rows' => array_slice($pos, 0, 25)],
			'receiving_rejections' => round($rejections, 2),
			'recovered' => round($recovered, 2),
			'gross_loss' => round($gross, 2),
			'net_loss' => round($net, 2),
			'net_loss_pct_sales' => $sales > 0 ? round($net / $sales * 100, 2) : null,
		];
	}

	/**
	 * Lotes inmovilizados (rotación): entradas con stock en la sucursal, compradas hace ≥ STAGNANT_LOT_DAYS y sin salidas
	 * (consumo, venta, traslado) de esa ubicación en ese plazo. Recomendación por reglas verificables, en este orden:
	 * vencido → baja; dentro de una regla de rebaja → rebaja FEFO; otra sucursal lo vende → traslado; proveedor conocido
	 * y compra reciente → devolución (sujeta a acuerdo); si no → conteo de verificación (posible stock fantasma).
	 */
	public function StagnantLots(?int $branchId, ?int $days = null): array
	{
		$days ??= defined('GROCY_STAGNANT_LOT_DAYS') ? intval(GROCY_STAGNANT_LOT_DAYS) : 30;
		$since = date('Y-m-d', strtotime("-$days days"));
		$today = date('Y-m-d');
		$bl = $branchId ? ' AND l.branch_id = :b' : '';
		$p = [':since' => $since] + ($branchId ? [':b' => $branchId] : []);
		$rows = $this->Rows("SELECT s.stock_id, s.product_id, p.name AS product_name, s.location_id, l.name AS location_name, l.branch_id, b.name AS branch_name,
				s.amount, IFNULL(s.price, 0) AS price, s.amount * IFNULL(s.price, 0) AS value, s.purchased_date, s.best_before_date, p.shopping_location_id AS supplier_id,
				sl.name AS supplier_name, dl.lot_number,
				(SELECT MAX(date(sg.row_created_timestamp)) FROM stock_log sg WHERE sg.product_id = s.product_id AND sg.location_id = s.location_id AND sg.undone = 0
					AND sg.transaction_type IN ('consume', 'transfer_from')) AS last_outflow
			FROM stock s JOIN products p ON p.id = s.product_id JOIN locations l ON l.id = s.location_id LEFT JOIN branches b ON b.id = l.branch_id
			LEFT JOIN shopping_locations sl ON sl.id = p.shopping_location_id LEFT JOIN dairy_stock_lots dl ON dl.stock_id = s.stock_id
			WHERE s.amount > 0 AND IFNULL(l.is_transit, 0) = 0 AND date(s.purchased_date) <= :since $bl
			ORDER BY value DESC", $p);
		$rows = array_values(array_filter($rows, fn($r) => $r['last_outflow'] === null || $r['last_outflow'] < $since));
		$rules = $this->Rows('SELECT name, min_days, max_days, discount_pct FROM markdown_rules WHERE active = 1 ORDER BY min_days');
		$otherSales = $this->Db()->prepare("SELECT s.branch_id, b.name, SUM(si.amount) AS units FROM sale_items si JOIN sales s ON s.id = si.sale_id JOIN branches b ON b.id = s.branch_id
			WHERE si.product_id = :p AND s.branch_id <> :b AND s.status = 'completed' AND date(s.created_timestamp) >= :d GROUP BY s.branch_id ORDER BY units DESC LIMIT 1");
		foreach ($rows as &$r)
		{
			$hasExpiry = $r['best_before_date'] && $r['best_before_date'] !== '2999-12-31';
			$daysLeft = $hasExpiry ? intval((strtotime($r['best_before_date']) - strtotime($today)) / 86400) : null;
			$r['days_left'] = $daysLeft;
			$r['days_without_outflow'] = intval((strtotime($today) - strtotime($r['last_outflow'] ?? $r['purchased_date'])) / 86400);
			$rule = null;
			foreach ($rules as $mr)
			{
				if ($daysLeft !== null && $daysLeft >= intval($mr['min_days']) && $daysLeft <= intval($mr['max_days']))
				{
					$rule = $mr;
					break;
				}
			}
			$otherSales->execute([':p' => $r['product_id'], ':b' => intval($r['branch_id']), ':d' => date('Y-m-d', strtotime('-28 days'))]);
			$dest = $otherSales->fetch(\PDO::FETCH_ASSOC);
			if ($daysLeft !== null && $daysLeft < 0)
			{
				$r['action'] = 'write_off';
				$r['recommendation'] = 'Vencido: registrar merma por vencimiento';
			}
			elseif ($rule)
			{
				$r['action'] = 'markdown';
				$r['recommendation'] = "Rebaja «{$rule['name']}» −{$rule['discount_pct']} % (vence en $daysLeft d)";
			}
			elseif ($dest)
			{
				$r['action'] = 'transfer';
				$r['target_branch_id'] = intval($dest['branch_id']);
				$r['recommendation'] = "Trasladar a {$dest['name']} (vendió " . round(floatval($dest['units']), 1) . ' u. en 28 d)';
			}
			elseif ($r['supplier_id'] && $r['purchased_date'] >= date('Y-m-d', strtotime('-' . ($days * 2) . ' days')))
			{
				$r['action'] = 'return';
				$r['recommendation'] = "Evaluar devolución a {$r['supplier_name']} (verificar acuerdo comercial)";
			}
			else
			{
				$r['action'] = 'count';
				$r['recommendation'] = 'Conteo de verificación: sin salidas, posible stock fantasma';
			}
			$r['rule_basis'] = 'determinística';
		}
		unset($r);
		return ['days' => $days, 'since' => $since, 'lots' => $rows, 'total_value' => round(array_sum(array_column($rows, 'value')), 2), 'count' => count($rows)];
	}
}
