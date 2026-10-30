<?php

namespace Grocy\Services;

/**
 * DAIRY Mobile (manager): lecturas agregadas para la app /m con navegación progresiva
 * General → Departamento (grupo de producto) → Producto/SKU → Registro.
 * Solo lee; las acciones usan las APIs existentes (mermas, conteos, alertas, traslados).
 *
 * Clasificación de pérdidas sin doble conteo:
 *  - Merma operativa / comercial: mermas APROBADAS según la clase de la causal (waste_reasons.loss_category;
 *    'averia' cuenta como operativa). La merma descontada genera un consumo, no una corrección de inventario.
 *  - Por clasificar: mermas aprobadas con causal 'ajuste' + diferencias netas de inventario (correcciones en stock_log).
 *  - En proceso (no suman): mermas por aprobar y diferencias de conteo sin ajustar.
 */
class MobileManagerService extends BaseService
{
	const BUCKET_SQL = "CASE IFNULL(r.loss_category, 'operativa') WHEN 'comercial' THEN 'comercial' WHEN 'ajuste' THEN 'por_clasificar' ELSE 'operativa' END";

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

	private function One(string $sql, array $p = [])
	{
		$stmt = $this->Db()->prepare($sql);
		$stmt->execute($p);
		return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
	}

	private function Val(string $sql, array $p = []): float
	{
		$stmt = $this->Db()->prepare($sql);
		$stmt->execute($p);
		return floatval($stmt->fetchColumn());
	}

	// Período anterior de igual duración (para tendencias)
	public static function PreviousPeriod(string $from, string $to): array
	{
		$days = (int) round((strtotime($to) - strtotime($from)) / 86400) + 1;
		return [date('Y-m-d', strtotime($from . " -$days days")), date('Y-m-d', strtotime($from . ' -1 day'))];
	}

	private static function Pct(float $now, float $before): ?float
	{
		return $before != 0 ? round(($now - $before) / abs($before) * 100, 1) : null;
	}

	/* ---------------------------------------------------------------- Ventas */

	private function SalesTotals(?int $branchId, string $from, string $to): array
	{
		$p = [':f' => $from, ':t' => $to] + ($branchId ? [':b' => $branchId] : []);
		$b = $branchId ? ' AND branch_id = :b' : '';
		$r = $this->One("SELECT
				IFNULL(SUM(CASE WHEN status = 'completed' THEN subtotal END), 0) AS gross,
				IFNULL(SUM(CASE WHEN status = 'completed' THEN discount_amount END), 0) AS discounts,
				IFNULL(SUM(CASE WHEN status = 'completed' THEN tax_amount END), 0) AS tax,
				IFNULL(SUM(CASE WHEN status = 'completed' THEN total_amount END), 0) AS total,
				IFNULL(SUM(CASE WHEN status = 'completed' THEN total_cogs END), 0) AS cogs,
				SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS tickets,
				SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) AS returns_count,
				IFNULL(SUM(CASE WHEN status = 'cancelled' THEN total_amount - IFNULL(tax_amount, 0) END), 0) AS returns_amount
			FROM sales WHERE date(created_timestamp) BETWEEN :f AND :t$b", $p);
		$r = array_map('floatval', $r);
		// Neta = total cobrado − impuestos (descuentos ya restados en total); margen solo si hay costo registrado.
		// 'total' se expone SIN IVA (definición de Ventas); el cobrado con IVA queda en 'total_with_tax'.
		$r['net'] = round($r['total'] - $r['tax'], 2);
		$r['total_with_tax'] = $r['total'];
		$r['total'] = $r['net'];
		$r['margin'] = $r['cogs'] > 0 ? round($r['net'] - $r['cogs'], 2) : null;
		$r['margin_pct'] = $r['cogs'] > 0 && $r['net'] > 0 ? round(($r['net'] - $r['cogs']) / $r['net'] * 100, 1) : null;
		$r['avg_ticket'] = $r['tickets'] > 0 ? round($r['total'] / $r['tickets'], 2) : 0;
		return $r;
	}

	private function DailySeries(string $sql, array $p, string $from, string $to): array
	{
		$map = [];
		foreach ($this->Rows($sql, $p) as $r)
		{
			$map[$r['d']] = floatval($r['v']);
		}
		$out = [];
		for ($t = strtotime($from); $t <= strtotime($to); $t += 86400)
		{
			$d = date('Y-m-d', $t);
			$out[] = ['d' => $d, 'v' => round($map[$d] ?? 0, 2)];
		}
		return $out;
	}

	public function Sales(?int $branchId, string $from, string $to, ?int $groupId = null, ?int $productId = null): array
	{
		[$pf, $pt] = self::PreviousPeriod($from, $to);
		$p = [':f' => $from, ':t' => $to] + ($branchId ? [':b' => $branchId] : []);
		$bS = $branchId ? ' AND s.branch_id = :b' : '';
		$scope = '';
		if ($productId)
		{
			$scope = ' AND si.product_id = :pid';
			$p[':pid'] = $productId;
		}
		elseif ($groupId !== null)
		{
			$scope = $groupId > 0 ? ' AND pr.product_group_id = :g' : ' AND pr.product_group_id IS NULL';
			if ($groupId > 0)
			{
				$p[':g'] = $groupId;
			}
		}
		$base = "FROM sale_items si JOIN sales s ON s.id = si.sale_id LEFT JOIN products pr ON pr.id = si.product_id LEFT JOIN product_groups g ON g.id = pr.product_group_id
			WHERE s.status = 'completed' AND date(s.created_timestamp) BETWEEN :f AND :t$bS$scope";
		$cost = 'IFNULL(NULLIF(si.total_cost, 0), si.amount * IFNULL(si.unit_cost, 0))';
		// Ventas se muestran SIN IVA en todas las cifras (KPI, series, departamentos, productos, sucursales)
		$iNet = SalesService::SQL_ITEM_NET_SALES;
		$sNet = SalesService::SQL_NET_SALES;

		$out = ['from' => $from, 'to' => $to, 'prev_from' => $pf, 'prev_to' => $pt];
		$scoped = $productId || $groupId !== null;
		if (!$scoped)
		{
			$out['totals'] = $this->SalesTotals($branchId, $from, $to);
			$out['prev'] = $this->SalesTotals($branchId, $pf, $pt);
			$out['totals']['delta_pct'] = self::Pct($out['totals']['total'], $out['prev']['total']);
		}
		else
		{
			$t = $this->One("SELECT IFNULL(SUM($iNet), 0) AS total, IFNULL(SUM(si.amount), 0) AS units, IFNULL(SUM($cost), 0) AS cogs, COUNT(DISTINCT s.id) AS tickets $base", $p);
			$pp = $p;
			$pp[':f'] = $pf;
			$pp[':t'] = $pt;
			$prevRow = $this->One("SELECT IFNULL(SUM($iNet), 0) AS total, COUNT(DISTINCT s.id) AS tickets $base", $pp);
			$prev = floatval($prevRow['total'] ?? 0);
			$prevTickets = intval($prevRow['tickets'] ?? 0);
			$out['prev'] = ['total' => $prev, 'tickets' => $prevTickets, 'avg_ticket' => $prevTickets > 0 ? round($prev / $prevTickets, 2) : 0];
			$t = array_map('floatval', $t);
			$t['avg_ticket'] = $t['tickets'] > 0 ? round($t['total'] / $t['tickets'], 2) : 0;
			$t['returns_count'] = 0; // devoluciones: solo se calculan a nivel de ticket (vista sin filtro de grupo/producto)
			$t['returns_amount'] = 0;
			$t['margin'] = $t['cogs'] > 0 ? round($t['total'] - $t['cogs'], 2) : null;
			$t['margin_pct'] = $t['cogs'] > 0 && $t['total'] > 0 ? round(($t['total'] - $t['cogs']) / $t['total'] * 100, 1) : null;
			$t['delta_pct'] = self::Pct($t['total'], $prev);
			$out['totals'] = $t;
		}

		$out['daily'] = $this->DailySeries("SELECT date(s.created_timestamp) AS d, SUM($iNet) AS v $base GROUP BY d", $p, $from, $to);
		$out['by_hour'] = $this->Rows("SELECT CAST(strftime('%H', s.created_timestamp) AS INTEGER) AS h, SUM($iNet) AS total, COUNT(DISTINCT s.id) AS tickets $base GROUP BY h ORDER BY h", $p);
		if (!$scoped)
		{
			$out['by_group'] = $this->Rows("SELECT IFNULL(pr.product_group_id, 0) AS id, IFNULL(g.name, 'Sin departamento') AS name, SUM($iNet) AS total, SUM(si.amount) AS units,
					SUM($cost) AS cogs $base GROUP BY 1 ORDER BY total DESC", $p);
			$out['by_branch'] = $branchId ? [] : $this->Rows("SELECT s.branch_id AS id, IFNULL(b.name, 'Sucursal ' || s.branch_id) AS name, SUM($sNet) AS total, COUNT(*) AS tickets
				FROM sales s LEFT JOIN branches b ON b.id = s.branch_id WHERE s.status = 'completed' AND date(s.created_timestamp) BETWEEN :f AND :t GROUP BY s.branch_id ORDER BY total DESC", [':f' => $from, ':t' => $to]);
		}
		if (!$productId)
		{
			$out['by_product'] = $this->Rows("SELECT si.product_id AS id, IFNULL(pr.name, 'Producto #' || si.product_id) AS name, pr.sku, SUM($iNet) AS total, SUM(si.amount) AS units,
					SUM($cost) AS cogs, COUNT(DISTINCT s.id) AS tickets $base GROUP BY si.product_id ORDER BY total DESC LIMIT 50", $p);
		}
		$out['lines'] = $this->Rows("SELECT s.id AS sale_id, s.sale_number, s.created_timestamp, s.branch_id, si.product_id, IFNULL(pr.name, 'Producto #' || si.product_id) AS product_name,
				si.amount, si.unit_price, si.subtotal, $cost AS cost, s.payment_method $base ORDER BY s.created_timestamp DESC LIMIT 40", $p);
		if (!$scoped)
		{
			$out['returns'] = $this->Rows("SELECT id AS sale_id, sale_number, created_timestamp, total_amount - IFNULL(tax_amount, 0) AS total_amount, notes FROM sales
				WHERE status = 'cancelled' AND date(created_timestamp) BETWEEN :f AND :t" . ($branchId ? ' AND branch_id = :b' : '') . ' ORDER BY created_timestamp DESC LIMIT 20',
				[':f' => $from, ':t' => $to] + ($branchId ? [':b' => $branchId] : []));
			// Relación venta ↔ merma ↔ inventario por departamento
			$out['relation'] = $this->Relation($branchId, $from, $to);
		}
		return $out;
	}

	private function Relation(?int $branchId, string $from, string $to): array
	{
		$p = [':f' => $from, ':t' => $to] + ($branchId ? [':b' => $branchId] : []);
		$iNet = SalesService::SQL_ITEM_NET_SALES;
		$rows = [];
		foreach ($this->Rows("SELECT IFNULL(pr.product_group_id, 0) AS id, SUM($iNet) AS v FROM sale_items si JOIN sales s ON s.id = si.sale_id LEFT JOIN products pr ON pr.id = si.product_id
			WHERE s.status = 'completed' AND date(s.created_timestamp) BETWEEN :f AND :t" . ($branchId ? ' AND s.branch_id = :b' : '') . ' GROUP BY 1', $p) as $r)
		{
			$rows[$r['id']]['sales'] = floatval($r['v']);
		}
		foreach ($this->Rows("SELECT IFNULL(pr.product_group_id, 0) AS id, SUM(w.total_cost) AS v FROM waste_records w LEFT JOIN products pr ON pr.id = w.product_id
			WHERE w.status = 'approved' AND date(w.created_timestamp) BETWEEN :f AND :t" . ($branchId ? ' AND w.branch_id = :b' : '') . ' GROUP BY 1', $p) as $r)
		{
			$rows[$r['id']]['waste'] = floatval($r['v']);
		}
		foreach ($this->Rows('SELECT IFNULL(pr.product_group_id, 0) AS id, SUM(st.amount * IFNULL(st.price, 0)) AS v FROM stock st JOIN locations l ON l.id = st.location_id
			LEFT JOIN products pr ON pr.id = st.product_id WHERE IFNULL(l.is_transit, 0) = 0' . ($branchId ? ' AND l.branch_id = :b' : '') . ' GROUP BY 1', $branchId ? [':b' => $branchId] : []) as $r)
		{
			$rows[$r['id']]['stock'] = floatval($r['v']);
		}
		$names = [0 => 'Sin departamento'];
		foreach ($this->Rows('SELECT id, name FROM product_groups') as $g)
		{
			$names[$g['id']] = $g['name'];
		}
		$out = [];
		foreach ($rows as $id => $r)
		{
			$s = $r['sales'] ?? 0;
			$w = $r['waste'] ?? 0;
			$out[] = ['id' => $id, 'name' => $names[$id] ?? ('#' . $id), 'sales' => round($s, 2), 'waste' => round($w, 2), 'stock' => round($r['stock'] ?? 0, 2),
				'waste_pct' => $s > 0 ? round($w / $s * 100, 2) : null];
		}
		usort($out, fn($a, $b) => $b['sales'] <=> $a['sales'] ?: $b['waste'] <=> $a['waste']);
		return $out;
	}

	/* ---------------------------------------------------------------- Mermas */

	public function Waste(?int $branchId, string $from, string $to, ?string $bucket = null, ?int $groupId = null, ?int $productId = null): array
	{
		[$pf, $pt] = self::PreviousPeriod($from, $to);
		$bk = self::BUCKET_SQL;
		$p = [':f' => $from, ':t' => $to] + ($branchId ? [':b' => $branchId] : []);
		$where = 'date(w.created_timestamp) BETWEEN :f AND :t' . ($branchId ? ' AND w.branch_id = :b' : '');
		if ($bucket && in_array($bucket, ['operativa', 'comercial', 'por_clasificar'], true))
		{
			$where .= " AND $bk = :bucket";
			$p[':bucket'] = $bucket;
		}
		if ($productId)
		{
			$where .= ' AND w.product_id = :pid';
			$p[':pid'] = $productId;
		}
		elseif ($groupId !== null)
		{
			$where .= $groupId > 0 ? ' AND pr.product_group_id = :g' : ' AND pr.product_group_id IS NULL';
			if ($groupId > 0)
			{
				$p[':g'] = $groupId;
			}
		}
		$from_ = 'FROM waste_records w LEFT JOIN waste_reasons r ON r.id = w.waste_reason_id LEFT JOIN products pr ON pr.id = w.product_id LEFT JOIN product_groups g ON g.id = pr.product_group_id';
		$appr = "$from_ WHERE w.status = 'approved' AND $where";

		$buckets = [];
		foreach ($this->Rows("SELECT $bk AS bucket, COUNT(*) AS records, SUM(w.amount) AS units, SUM(w.total_cost) AS cost $appr GROUP BY bucket", $p) as $r)
		{
			$buckets[$r['bucket']] = ['records' => intval($r['records']), 'units' => floatval($r['units']), 'cost' => round(floatval($r['cost']), 2)];
		}
		foreach (['operativa', 'comercial', 'por_clasificar'] as $k)
		{
			$buckets[$k] ??= ['records' => 0, 'units' => 0, 'cost' => 0];
		}

		$out = ['from' => $from, 'to' => $to, 'bucket' => $bucket, 'buckets' => $buckets];

		// Diferencias de inventario (correcciones netas) y conteos con diferencia sin ajustar: solo en la vista general
		if (!$productId && $groupId === null && (!$bucket || $bucket === 'por_clasificar'))
		{
			$lp = LossPreventionService::GetInstance()->TotalLoss($branchId, $from, $to);
			$out['inventory_diff'] = $lp['unknown'];
			$out['unexplained'] = ['total' => $lp['unexplained_outflows']['total'], 'count' => $lp['unexplained_outflows']['count']];
			$out['recovered'] = $lp['recovered'];
			$out['sales'] = $lp['sales'];
			$out['buckets']['por_clasificar']['inventory_diff'] = round(max(0, $lp['unknown']['net']), 2);
		}
		$known = $buckets['operativa']['cost'] + $buckets['comercial']['cost'];
		$unclassified = $buckets['por_clasificar']['cost'] + ($out['buckets']['por_clasificar']['inventory_diff'] ?? 0);
		$out['total'] = round($known + $unclassified, 2);
		$out['known'] = round($known, 2);
		$out['unclassified'] = round($unclassified, 2);
		$sales = $out['sales'] ?? $this->Val("SELECT IFNULL(SUM(total_amount), 0) FROM sales WHERE status = 'completed' AND date(created_timestamp) BETWEEN :f AND :t" . ($branchId ? ' AND branch_id = :b' : ''),
			[':f' => $from, ':t' => $to] + ($branchId ? [':b' => $branchId] : []));
		$out['sales'] = round($sales, 2);
		$out['pct_sales'] = $sales > 0 ? round($out['total'] / $sales * 100, 2) : null;
		foreach (['operativa', 'comercial', 'por_clasificar'] as $k)
		{
			$c = $out['buckets'][$k]['cost'] + ($out['buckets'][$k]['inventory_diff'] ?? 0);
			$out['buckets'][$k]['pct_total'] = $out['total'] > 0 ? round($c / $out['total'] * 100, 1) : 0;
			$out['buckets'][$k]['pct_sales'] = $sales > 0 ? round($c / $sales * 100, 2) : null;
		}
		$pp = $p;
		$pp[':f'] = $pf;
		$pp[':t'] = $pt;
		$prevKnown = $this->Val("SELECT IFNULL(SUM(w.total_cost), 0) $appr", $pp);
		$nowKnown = $this->Val("SELECT IFNULL(SUM(w.total_cost), 0) $appr", $p);
		$out['delta_pct'] = self::Pct($nowKnown, $prevKnown);

		$out['pending'] = $this->One("SELECT COUNT(*) AS records, IFNULL(SUM(w.total_cost), 0) AS cost $from_ WHERE w.status = 'pending_approval' AND $where", $p);
		$out['by_reason'] = $this->Rows("SELECT w.waste_reason_id AS id, IFNULL(r.name, 'Sin causal') AS name, $bk AS bucket, COUNT(*) AS records, SUM(w.amount) AS units, SUM(w.total_cost) AS cost
			$appr GROUP BY w.waste_reason_id ORDER BY cost DESC LIMIT 15", $p);
		$out['by_group'] = $this->Rows("SELECT IFNULL(pr.product_group_id, 0) AS id, IFNULL(g.name, 'Sin departamento') AS name, COUNT(*) AS records, SUM(w.amount) AS units, SUM(w.total_cost) AS cost
			$appr GROUP BY 1 ORDER BY cost DESC", $p);
		$out['by_product'] = $productId ? [] : $this->Rows("SELECT w.product_id AS id, IFNULL(pr.name, 'Producto #' || w.product_id) AS name, pr.sku, COUNT(*) AS records, SUM(w.amount) AS units, SUM(w.total_cost) AS cost
			$appr GROUP BY w.product_id ORDER BY cost DESC LIMIT 30", $p);
		$out['by_area'] = $this->Rows("SELECT IFNULL(NULLIF(r.responsible_area, ''), 'Sin área') AS name, COUNT(*) AS records, SUM(w.total_cost) AS cost $appr GROUP BY 1 ORDER BY cost DESC LIMIT 10", $p);
		$out['by_user'] = $this->Rows("SELECT w.reported_by_user_id AS id, " . self::UserName('u') . " AS name, COUNT(*) AS records, SUM(w.total_cost) AS cost
			$from_ LEFT JOIN users u ON u.id = w.reported_by_user_id WHERE w.status = 'approved' AND $where GROUP BY w.reported_by_user_id ORDER BY cost DESC LIMIT 10", $p);
		$out['daily'] = $this->DailySeries("SELECT date(w.created_timestamp) AS d, SUM(w.total_cost) AS v $appr GROUP BY d", $p, $from, $to);
		$out['records'] = $this->Rows("SELECT w.id, w.case_number, w.created_timestamp, w.product_id, IFNULL(pr.name, 'Producto #' || w.product_id) AS product_name, pr.sku, w.amount, w.total_cost,
				IFNULL(r.name, 'Sin causal') AS reason, $bk AS bucket, w.status, w.workflow_status, w.evidence_photo_path, " . self::UserName('u') . " AS reported_by
			$from_ LEFT JOIN users u ON u.id = w.reported_by_user_id WHERE $where ORDER BY w.created_timestamp DESC LIMIT 40", $p);
		// Indicadores del Centro de Control web: misma fuente y fórmulas (LossIntelligenceService)
		if (!$bucket)
		{
			$lf = ['branch_id' => $branchId, 'group_id' => $groupId > 0 ? $groupId : null, 'format' => null, 'loss_class' => null, 'supplier_id' => null, 'product_id' => $productId, 'cause' => null];
			$out['control'] = LossIntelligenceService::GetInstance()->Summary($lf, $from, $to);
			$out['control']['can_theft'] = \Grocy\Helpers\DairyAccess::HasAny([\Grocy\Controllers\Users\User::PERMISSION_LOSS_PREVENTION, \Grocy\Controllers\Users\User::PERMISSION_WASTE_APPROVE, \Grocy\Controllers\Users\User::PERMISSION_ADMIN]);
		}
		return $out;
	}

	private static function UserName(string $alias): string
	{
		return "IFNULL(NULLIF(TRIM(IFNULL($alias.first_name, '') || ' ' || IFNULL($alias.last_name, '')), ''), IFNULL($alias.username, 'sistema'))";
	}

	/* ---------------------------------------------------------------- Inventario */

	public function Inventory(?int $branchId, ?int $groupId = null, ?string $filter = null): array
	{
		$p = $branchId ? [':b' => $branchId] : [];
		$bL = $branchId ? ' AND l.branch_id = :b' : '';
		$stockQ = "SELECT p.id, p.name, p.sku, p.min_stock_amount, IFNULL(p.product_group_id, 0) AS group_id, IFNULL(g.name, 'Sin departamento') AS group_name,
				IFNULL(SUM(st.amount), 0) AS qty, IFNULL(SUM(st.amount * IFNULL(st.price, 0)), 0) AS value,
				IFNULL(SUM(CASE WHEN st.open = 0 THEN st.amount END), 0) AS available, MIN(st.best_before_date) AS next_expiry,
				qu.name AS unit
			FROM products p LEFT JOIN product_groups g ON g.id = p.product_group_id LEFT JOIN quantity_units qu ON qu.id = p.qu_id_stock
			LEFT JOIN (SELECT st.* FROM stock st JOIN locations l ON l.id = st.location_id WHERE IFNULL(l.is_transit, 0) = 0$bL) st ON st.product_id = p.id
			WHERE p.active = 1" . ($groupId !== null ? ($groupId > 0 ? ' AND p.product_group_id = ' . intval($groupId) : ' AND p.product_group_id IS NULL') : '') . '
			GROUP BY p.id';
		$all = $this->Rows($stockQ, $p);
		$today = date('Y-m-d');
		$soon = date('Y-m-d', strtotime('+7 days'));
		foreach ($all as &$r)
		{
			$r['qty'] = floatval($r['qty']);
			$r['value'] = round(floatval($r['value']), 2);
			$r['available'] = floatval($r['available']);
			$min = floatval($r['min_stock_amount']);
			$r['state'] = $r['qty'] <= 0 ? 'agotado' : ($min > 0 && $r['qty'] < $min ? 'bajo' : ($r['next_expiry'] && $r['next_expiry'] !== '2999-12-31' && $r['next_expiry'] < $today ? 'vencido' : ($r['next_expiry'] && $r['next_expiry'] <= $soon ? 'por_vencer' : 'ok')));
			$r['suggest'] = $min > 0 && $r['qty'] < $min ? round($min - $r['qty'], 2) : 0;
		}
		unset($r);

		$out = [
			'totals' => [
				'skus' => count($all),
				'with_stock' => count(array_filter($all, fn($r) => $r['qty'] > 0)),
				'units' => round(array_sum(array_column($all, 'qty')), 2),
				'value' => round(array_sum(array_column($all, 'value')), 2),
				'agotado' => count(array_filter($all, fn($r) => $r['state'] === 'agotado')),
				'bajo' => count(array_filter($all, fn($r) => $r['state'] === 'bajo')),
				'vencido' => count(array_filter($all, fn($r) => $r['state'] === 'vencido')),
				'por_vencer' => count(array_filter($all, fn($r) => $r['state'] === 'por_vencer')),
			],
		];
		$out['totals']['availability_pct'] = $out['totals']['skus'] > 0 ? round($out['totals']['with_stock'] / $out['totals']['skus'] * 100, 1) : null;

		if ($groupId === null)
		{
			$groups = [];
			foreach ($all as $r)
			{
				$g = &$groups[$r['group_id']];
				$g ??= ['id' => intval($r['group_id']), 'name' => $r['group_name'], 'skus' => 0, 'value' => 0, 'critical' => 0];
				$g['skus']++;
				$g['value'] += $r['value'];
				$g['critical'] += $r['state'] !== 'ok' ? 1 : 0;
				unset($g);
			}
			usort($groups, fn($a, $b) => $b['critical'] <=> $a['critical'] ?: $b['value'] <=> $a['value']);
			$out['by_group'] = array_map(fn($g) => $g + ['value' => round($g['value'], 2)], $groups);
		}

		$rank = ['vencido' => 0, 'agotado' => 1, 'bajo' => 2, 'por_vencer' => 3, 'ok' => 4];
		$items = $filter && isset($rank[$filter]) ? array_values(array_filter($all, fn($r) => $r['state'] === $filter)) : $all;
		if ($filter === 'critico')
		{
			$items = array_values(array_filter($all, fn($r) => $r['state'] !== 'ok'));
		}
		usort($items, fn($a, $b) => $rank[$a['state']] <=> $rank[$b['state']] ?: $b['value'] <=> $a['value']);
		$out['items'] = array_slice($items, 0, 80);

		// Conteos, transferencias, vencimientos, movimientos recientes y reposición
		$bC = $branchId ? ' AND c.branch_id = :b' : '';
		$out['counts'] = $this->Rows("SELECT c.id, c.product_id, p.name AS product_name, c.status, c.scheduled_date, c.counted_stock, c.difference_amount, c.difference_cost,
				CASE WHEN c.status = 'pending' THEN NULL ELSE c.theoretical_stock END AS theoretical_stock
			FROM stock_cycle_counts c JOIN products p ON p.id = c.product_id WHERE c.status IN ('pending', 'discrepancy')$bC ORDER BY c.status = 'discrepancy' DESC, c.scheduled_date", $p);
		$out['transfers'] = $this->Rows("SELECT t.id, t.transfer_code, t.status, t.sent_at, fb.name AS from_branch, tb.name AS to_branch, t.total_sent_cost
			FROM stock_transfers t LEFT JOIN branches fb ON fb.id = t.from_branch_id LEFT JOIN branches tb ON tb.id = t.to_branch_id
			WHERE t.status = 'in_transit'" . ($branchId ? ' AND (t.from_branch_id = :b OR t.to_branch_id = :b)' : '') . ' ORDER BY t.sent_at', $p);
		$out['expiring'] = $this->Rows("SELECT st.product_id, p.name AS product_name, st.best_before_date, SUM(st.amount) AS qty, SUM(st.amount * IFNULL(st.price, 0)) AS value, l.name AS location
			FROM stock st JOIN products p ON p.id = st.product_id JOIN locations l ON l.id = st.location_id
			WHERE IFNULL(l.is_transit, 0) = 0 AND st.best_before_date <> '2999-12-31' AND st.best_before_date <= :soon$bL
			GROUP BY st.product_id, st.best_before_date, st.location_id ORDER BY st.best_before_date LIMIT 30", $p + [':soon' => $soon]);
		$out['movements'] = $this->Movements($branchId, null, 25);
		$out['restock'] = array_values(array_filter($all, fn($r) => $r['suggest'] > 0));
		return $out;
	}

	public function Movements(?int $branchId, ?int $productId, int $limit = 30): array
	{
		$p = ($branchId ? [':b' => $branchId] : []) + ($productId ? [':pid' => $productId] : []);
		return $this->Rows("SELECT sl.id, sl.product_id, p.name AS product_name, sl.transaction_type, sl.amount, sl.price, sl.spoiled, sl.undone, sl.row_created_timestamp, sl.note,
				l.name AS location, " . self::UserName('u') . " AS user_name
			FROM stock_log sl JOIN products p ON p.id = sl.product_id LEFT JOIN locations l ON l.id = sl.location_id LEFT JOIN users u ON u.id = sl.user_id
			WHERE sl.transaction_type NOT IN ('stock-edit-old', 'product-opened')" . ($branchId ? ' AND l.branch_id = :b' : '') . ($productId ? ' AND sl.product_id = :pid' : '') . "
			ORDER BY sl.id DESC LIMIT $limit", $p);
	}

	/* ---------------------------------------------------------------- SKU */

	public function Sku(int $productId, ?int $branchId): ?array
	{
		$p = $this->One("SELECT p.id, p.name, p.sku, p.brand, p.min_stock_amount, p.max_stock_amount, p.sale_price, p.picture_file_name, p.product_group_id, IFNULL(g.name, 'Sin departamento') AS group_name,
				qu.name AS unit, (SELECT barcode FROM product_barcodes WHERE product_id = p.id ORDER BY id LIMIT 1) AS barcode
			FROM products p LEFT JOIN product_groups g ON g.id = p.product_group_id LEFT JOIN quantity_units qu ON qu.id = p.qu_id_stock WHERE p.id = :id", [':id' => $productId]);
		if (!$p)
		{
			return null;
		}
		$bp = $branchId ? [':b' => $branchId] : [];
		$bL = $branchId ? ' AND l.branch_id = :b' : '';
		$p['locations'] = $this->Rows("SELECT l.id, l.name, l.branch_id, SUM(st.amount) AS qty, SUM(CASE WHEN st.open = 0 THEN st.amount ELSE 0 END) AS available, SUM(st.amount * IFNULL(st.price, 0)) AS value
			FROM stock st JOIN locations l ON l.id = st.location_id WHERE st.product_id = :id AND IFNULL(l.is_transit, 0) = 0$bL GROUP BY l.id ORDER BY qty DESC", [':id' => $productId] + $bp);
		$p['lots'] = $this->Rows("SELECT st.stock_id, st.best_before_date, st.amount, st.price, st.open, l.name AS location FROM stock st JOIN locations l ON l.id = st.location_id
			WHERE st.product_id = :id AND IFNULL(l.is_transit, 0) = 0$bL ORDER BY st.best_before_date LIMIT 20", [':id' => $productId] + $bp);
		$p['qty'] = round(array_sum(array_map(fn($r) => floatval($r['qty']), $p['locations'])), 2);
		$p['available'] = round(array_sum(array_map(fn($r) => floatval($r['available']), $p['locations'])), 2);
		$p['value'] = round(array_sum(array_map(fn($r) => floatval($r['value']), $p['locations'])), 2);
		$from = date('Y-m-d', strtotime('-29 days'));
		$to = date('Y-m-d');
		$bS = $branchId ? ' AND s.branch_id = :b' : '';
		$p['sales30'] = $this->One("SELECT IFNULL(SUM(si.amount), 0) AS units, IFNULL(SUM(si.subtotal), 0) AS total, COUNT(DISTINCT s.id) AS tickets
			FROM sale_items si JOIN sales s ON s.id = si.sale_id WHERE s.status = 'completed' AND si.product_id = :id AND date(s.created_timestamp) BETWEEN :f AND :t$bS", [':id' => $productId, ':f' => $from, ':t' => $to] + $bp);
		$daily = floatval($p['sales30']['units']) / 30;
		$p['coverage_days'] = $daily > 0 ? round($p['available'] / $daily, 1) : null;
		$bW = $branchId ? ' AND w.branch_id = :b' : '';
		$p['waste'] = $this->Rows("SELECT w.id, w.case_number, w.created_timestamp, w.amount, w.total_cost, w.status, IFNULL(r.name, 'Sin causal') AS reason, " . self::BUCKET_SQL . " AS bucket
			FROM waste_records w LEFT JOIN waste_reasons r ON r.id = w.waste_reason_id WHERE w.product_id = :id$bW ORDER BY w.created_timestamp DESC LIMIT 15", [':id' => $productId] + $bp);
		$bC = $branchId ? ' AND c.branch_id = :b' : '';
		$p['counts'] = $this->Rows("SELECT c.id, c.status, c.scheduled_date, c.executed_date, c.counted_stock, c.difference_amount, c.difference_cost,
				CASE WHEN c.status = 'pending' THEN NULL ELSE c.theoretical_stock END AS theoretical_stock
			FROM stock_cycle_counts c WHERE c.product_id = :id$bC ORDER BY c.id DESC LIMIT 10", [':id' => $productId] + $bp);
		$p['movements'] = $this->Movements($branchId, $productId, 40);
		return $p;
	}

	/* ---------------------------------------------------------------- Centro de Control: bandeja de prioridades */

	/**
	 * Casos accionables ordenados por severidad, impacto y antigüedad. Tipos: waste (merma por aprobar o en investigación),
	 * count (conteo con diferencia o pendiente), alert (centro de alertas), transfer (traslado por recibir).
	 */
	public function Inbox(?int $branchId, ?array $allowedBranches): array
	{
		$p = $branchId ? [':b' => $branchId] : [];
		$items = [];
		$today = date('Y-m-d');
		foreach ($this->Rows("SELECT w.id, IFNULL(w.case_number, 'MRM-' || w.id) AS ref, IFNULL(pr.name, '#' || w.product_id) AS product_name, w.amount, w.total_cost, w.status, w.workflow_status,
				w.created_timestamp, w.due_date, IFNULL(r.name, 'Sin causal') AS reason, w.branch_id, w.investigator_user_id
			FROM waste_records w LEFT JOIN products pr ON pr.id = w.product_id LEFT JOIN waste_reasons r ON r.id = w.waste_reason_id
			WHERE (w.status = 'pending_approval' OR (w.status = 'approved' AND w.workflow_status IN ('detected', 'reviewing', 'investigating', 'action_assigned', 'executed')))" . ($branchId ? ' AND w.branch_id = :b' : ''), $p) as $r)
		{
			$pending = $r['status'] === 'pending_approval';
			$overdue = $r['due_date'] && $r['due_date'] < $today;
			$items[] = ['type' => 'waste', 'id' => intval($r['id']), 'ref' => $r['ref'], 'branch_id' => intval($r['branch_id']),
				'title' => ($pending ? 'Aprobar merma · ' : 'Investigación · ') . $r['product_name'],
				'detail' => $r['reason'] . ' · ' . rtrim(rtrim(number_format(floatval($r['amount']), 2, '.', ''), '0'), '.') . ' u.',
				'value' => round(floatval($r['total_cost']), 2), 'severity' => $pending || $overdue ? 'high' : 'medium',
				'status' => $pending ? 'Por aprobar' : self::WorkflowLabel($r['workflow_status']), 'overdue' => $overdue,
				'created_at' => $r['created_timestamp'], 'due' => $r['due_date']];
		}
		foreach ($this->Rows("SELECT c.id, c.status, c.difference_amount, c.difference_cost, c.scheduled_date, c.branch_id, p.name AS product_name, c.created_timestamp
			FROM stock_cycle_counts c JOIN products p ON p.id = c.product_id WHERE c.status IN ('pending', 'discrepancy')" . ($branchId ? ' AND c.branch_id = :b' : ''), $p) as $r)
		{
			$disc = $r['status'] === 'discrepancy';
			$items[] = ['type' => 'count', 'id' => intval($r['id']), 'ref' => 'CC-' . $r['id'], 'branch_id' => intval($r['branch_id']),
				'title' => ($disc ? 'Diferencia de conteo · ' : 'Contar · ') . $r['product_name'],
				'detail' => $disc ? 'Diferencia ' . floatval($r['difference_amount']) . ' u. por ajustar o recontar' : 'Conteo ciego programado ' . $r['scheduled_date'],
				'value' => $disc ? round(abs(floatval($r['difference_cost'])), 2) : 0, 'severity' => $disc ? 'high' : ($r['scheduled_date'] < $today ? 'medium' : 'low'),
				'status' => $disc ? 'Diferencia' : 'Pendiente', 'overdue' => !$disc && $r['scheduled_date'] < $today, 'created_at' => $r['created_timestamp'], 'due' => $r['scheduled_date']];
		}
		$sev = ['danger' => 'high', 'warning' => 'medium'];
		foreach (AlertCenterService::GetInstance()->GetAlerts('active', $branchId, $allowedBranches, null, 200) as $a)
		{
			$a = (array) $a;
			$items[] = ['type' => 'alert', 'id' => intval($a['id']), 'ref' => 'ALR-' . $a['id'], 'branch_id' => intval($a['branch_id'] ?? 0),
				'title' => $a['title'], 'detail' => (string) ($a['detail'] ?? ''), 'value' => round(floatval($a['value'] ?? 0), 2),
				'severity' => $sev[$a['severity']] ?? 'low', 'status' => self::AlertLabel($a['status']),
				'overdue' => !empty($a['due_at']) && $a['due_at'] < date('Y-m-d H:i:s'), 'created_at' => $a['created_at'], 'due' => $a['due_at'] ?? null];
		}
		foreach ($this->Rows("SELECT t.id, t.transfer_code, t.sent_at, t.total_sent_cost, t.to_branch_id, fb.name AS from_branch FROM stock_transfers t LEFT JOIN branches fb ON fb.id = t.from_branch_id
			WHERE t.status = 'in_transit'" . ($branchId ? ' AND t.to_branch_id = :b' : ''), $p) as $r)
		{
			$old = $r['sent_at'] && strtotime($r['sent_at']) < time() - 48 * 3600;
			$items[] = ['type' => 'transfer', 'id' => intval($r['id']), 'ref' => $r['transfer_code'], 'branch_id' => intval($r['to_branch_id']),
				'title' => 'Recibir traslado · ' . $r['transfer_code'], 'detail' => 'Desde ' . $r['from_branch'], 'value' => round(floatval($r['total_sent_cost']), 2),
				'severity' => $old ? 'high' : 'medium', 'status' => 'En tránsito', 'overdue' => $old, 'created_at' => $r['sent_at'], 'due' => null];
		}
		if ($allowedBranches !== null)
		{
			$items = array_values(array_filter($items, fn($i) => in_array($i['branch_id'], $allowedBranches, true)));
		}
		$rank = ['high' => 0, 'medium' => 1, 'low' => 2];
		usort($items, fn($a, $b) => $rank[$a['severity']] <=> $rank[$b['severity']] ?: ($b['overdue'] <=> $a['overdue']) ?: $b['value'] <=> $a['value'] ?: strcmp($a['created_at'] ?? '', $b['created_at'] ?? ''));
		return $items;
	}

	public static function WorkflowLabel(?string $s): string
	{
		return ['reported' => 'Reportada', 'detected' => 'Detectada', 'action_assigned' => 'Acción asignada', 'reviewing' => 'En revisión', 'investigating' => 'En investigación', 'approved' => 'Aprobada', 'executed' => 'Acción ejecutada',
			'verified' => 'Verificada', 'closed' => 'Cerrada', 'rejected' => 'Rechazada'][$s] ?? (string) $s;
	}

	public static function AlertLabel(?string $s): string
	{
		return AlertCenterService::STATUS[$s] ?? (string) $s;
	}

	public function WasteCase(int $id): ?array
	{
		$r = $this->One("SELECT w.*, IFNULL(pr.name, 'Producto #' || w.product_id) AS product_name, pr.sku, IFNULL(g.name, 'Sin departamento') AS group_name, IFNULL(r.name, 'Sin causal') AS reason,
				r.responsible_area, r.requires_approval, " . self::BUCKET_SQL . " AS bucket, l.name AS location_name, b.name AS branch_name,
				" . self::UserName('u') . " AS reported_by, " . self::UserName('ua') . " AS approved_by, " . self::UserName('ui') . " AS investigator
			FROM waste_records w LEFT JOIN products pr ON pr.id = w.product_id LEFT JOIN product_groups g ON g.id = pr.product_group_id LEFT JOIN waste_reasons r ON r.id = w.waste_reason_id
			LEFT JOIN locations l ON l.id = w.location_id LEFT JOIN branches b ON b.id = w.branch_id
			LEFT JOIN users u ON u.id = w.reported_by_user_id LEFT JOIN users ua ON ua.id = w.approved_by_user_id LEFT JOIN users ui ON ui.id = w.investigator_user_id
			WHERE w.id = :id", [':id' => $id]);
		if (!$r)
		{
			return null;
		}
		if ($r['approved_by_user_id'] === null)
		{
			$r['approved_by'] = null;
		}
		if ($r['investigator_user_id'] === null)
		{
			$r['investigator'] = null;
		}
		$r['workflow_label'] = self::WorkflowLabel($r['workflow_status']);
		// Evidencia: solo se enlaza si el archivo existe (datos antiguos guardan nombres sin archivo)
		$r['evidence_url'] = null;
		$r['evidence_missing'] = false;
		if (!empty($r['evidence_photo_path']))
		{
			$name = basename(strval($r['evidence_photo_path']));
			$exists = is_file(GROCY_DATAPATH . '/storage/waste/' . $name);
			$r['evidence_url'] = $exists ? '/api/waste/photo/' . rawurlencode($name) : null;
			$r['evidence_missing'] = !$exists;
		}
		$r['history'] = $this->Rows('SELECT a.created_timestamp, a.action_title, a.action_details, a.previous_status, a.new_status, ' . self::UserName('u') . ' AS user_name
			FROM waste_action_logs a LEFT JOIN users u ON u.id = a.user_id WHERE a.waste_record_id = :id ORDER BY a.id DESC', [':id' => $id]);
		return $r;
	}

	public function CountCase(int $id): ?array
	{
		$r = $this->One("SELECT c.*, p.name AS product_name, p.sku, l.name AS location_name, b.name AS branch_name,
				" . self::UserName('ue') . " AS executed_by, " . self::UserName('ua') . " AS assigned_to
			FROM stock_cycle_counts c JOIN products p ON p.id = c.product_id LEFT JOIN locations l ON l.id = c.location_id LEFT JOIN branches b ON b.id = c.branch_id
			LEFT JOIN users ue ON ue.id = c.executed_by_user_id LEFT JOIN users ua ON ua.id = c.assigned_user_id WHERE c.id = :id", [':id' => $id]);
		if (!$r)
		{
			return null;
		}
		if ($r['status'] === 'pending')
		{
			$r['theoretical_stock'] = null; // conteo ciego
		}
		if ($r['executed_by_user_id'] === null)
		{
			$r['executed_by'] = null;
		}
		if ($r['assigned_user_id'] === null)
		{
			$r['assigned_to'] = null;
		}
		return $r;
	}

	/* ---------------------------------------------------------------- Inicio */

	public function Home(?int $branchId, string $from, string $to, ?array $allowedBranches): array
	{
		$sales = $this->Sales($branchId, $from, $to);
		$waste = $this->Waste($branchId, $from, $to);
		$inv = $this->Inventory($branchId);
		$inbox = $this->Inbox($branchId, $allowedBranches);
		$diffs = array_values(array_filter($inv['counts'], fn($c) => $c['status'] === 'discrepancy'));
		return [
			'from' => $from, 'to' => $to,
			'sales' => ['totals' => $sales['totals'], 'prev_total' => $sales['prev']['total'], 'daily' => $sales['daily'], 'top_groups' => array_slice($sales['by_group'], 0, 3)],
			'waste' => ['total' => $waste['total'], 'pct_sales' => $waste['pct_sales'], 'delta_pct' => $waste['delta_pct'], 'buckets' => $waste['buckets'], 'pending' => $waste['pending'], 'daily' => $waste['daily']],
			'inventory' => $inv['totals'],
			'differences' => ['count' => count($diffs), 'cost' => round(array_sum(array_map(fn($c) => abs(floatval($c['difference_cost'])), $diffs)), 2),
				'inventory_net' => $waste['inventory_diff']['net'] ?? 0],
			'inbox' => ['total' => count($inbox), 'high' => count(array_filter($inbox, fn($i) => $i['severity'] === 'high')), 'top' => array_slice($inbox, 0, 5)],
		];
	}

	/* ---------------------------------------------------------------- Búsqueda global */

	public function Search(string $q, ?int $branchId): array
	{
		$q = trim($q);
		if (mb_strlen($q) < 2)
		{
			return [];
		}
		$like = '%' . $q . '%';
		$out = [];
		foreach ($this->Rows("SELECT DISTINCT p.id, p.name, p.sku FROM products p LEFT JOIN product_barcodes pb ON pb.product_id = p.id
			WHERE p.active = 1 AND (p.name LIKE :q OR p.sku LIKE :q OR pb.barcode = :exact) ORDER BY p.name LIMIT 12", [':q' => $like, ':exact' => $q]) as $r)
		{
			$out[] = ['type' => 'sku', 'id' => intval($r['id']), 'title' => $r['name'], 'sub' => $r['sku'] ? 'SKU ' . $r['sku'] : 'Producto'];
		}
		$bW = $branchId ? ' AND w.branch_id = :b' : '';
		foreach ($this->Rows("SELECT w.id, w.case_number, IFNULL(p.name, '') AS product_name FROM waste_records w LEFT JOIN products p ON p.id = w.product_id
			WHERE w.case_number LIKE :q$bW ORDER BY w.id DESC LIMIT 6", [':q' => $like] + ($branchId ? [':b' => $branchId] : [])) as $r)
		{
			$out[] = ['type' => 'waste', 'id' => intval($r['id']), 'title' => $r['case_number'], 'sub' => 'Merma · ' . $r['product_name']];
		}
		foreach ($this->Rows('SELECT g.id, g.name FROM product_groups g WHERE g.name LIKE :q LIMIT 4', [':q' => $like]) as $r)
		{
			$out[] = ['type' => 'group', 'id' => intval($r['id']), 'title' => $r['name'], 'sub' => 'Departamento'];
		}
		$bS = $branchId ? ' AND branch_id = :b' : '';
		foreach ($this->Rows("SELECT id, sale_number, total_amount, status FROM sales WHERE sale_number LIKE :q$bS ORDER BY id DESC LIMIT 4", [':q' => $like] + ($branchId ? [':b' => $branchId] : [])) as $r)
		{
			$out[] = ['type' => 'sale', 'id' => intval($r['id']), 'title' => $r['sale_number'], 'sub' => 'Venta · ' . ($r['status'] === 'cancelled' ? 'anulada' : 'completada')];
		}
		return $out;
	}

	public function Sale(int $id): ?array
	{
		$s = $this->One('SELECT s.*, b.name AS branch_name, ' . self::UserName('u') . ' AS cashier FROM sales s LEFT JOIN branches b ON b.id = s.branch_id LEFT JOIN users u ON u.id = s.user_id WHERE s.id = :id', [':id' => $id]);
		if (!$s)
		{
			return null;
		}
		$s['items'] = $this->Rows("SELECT si.product_id, IFNULL(p.name, 'Producto #' || si.product_id) AS product_name, si.amount, si.unit_price, si.subtotal,
			IFNULL(NULLIF(si.total_cost, 0), si.amount * IFNULL(si.unit_cost, 0)) AS cost FROM sale_items si LEFT JOIN products p ON p.id = si.product_id WHERE si.sale_id = :id", [':id' => $id]);
		return $s;
	}

	/* ---------------------------------------------------------------- Catálogos para captura rápida */

	public function Lookups(): array
	{
		return [
			// Ordenados por uso en 90 días: la captura muestra primero los habituales
			'reasons' => $this->Rows("SELECT r.id, r.name, r.requires_evidence, r.requires_approval, " . self::BUCKET_SQL . " AS bucket,
				(SELECT COUNT(*) FROM waste_records w WHERE w.waste_reason_id = r.id AND w.created_timestamp >= date('now', '-90 days')) AS uses
				FROM waste_reasons r WHERE r.active = 1 ORDER BY uses DESC, r.name"),
			'locations' => $this->Rows('SELECT id, name, branch_id FROM locations WHERE active = 1 AND IFNULL(is_transit, 0) = 0 ORDER BY name'),
			'branches' => $this->Rows('SELECT id, name, code FROM branches WHERE active = 1 ORDER BY id'),
			'groups' => $this->Rows('SELECT id, name FROM product_groups WHERE IFNULL(active, 1) = 1 ORDER BY name'),
			'users' => $this->Rows('SELECT u.id, ' . self::UserName('u') . ' AS name FROM users u ORDER BY name'),
		];
	}
}
