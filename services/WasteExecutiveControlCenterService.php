<?php

namespace Grocy\Services;

use Grocy\Helpers\DairyAccess;

/**
 * Mermas · Centro de Control Ejecutivo (modelo Zebra Workcloud Actionable Intelligence).
 * Fuente única sobre waste_records aprobados, ventas netas y metas presupuestarias.
 *
 * Separa estrictamente:
 * - Merma Real Física: operativa + comercial (vencimientos, rebajas, errores de procedimiento) + averías/roturas
 * - Ajustes Contables: correcciones de inventario/conteos (akcal_classification = 'ajuste' o códigos AJU/A01/A02)
 * - Seguridad/Hurto (robo_interno, robo_externo, M03, M04, FPO01, FEL01): gestionado en Prevención de Pérdidas (/loss-prevention)
 */
class WasteExecutiveControlCenterService extends BaseService
{
	private static ?WasteExecutiveControlCenterService $instance = null;

	const TYPES = [
		'operativa' => ['Operativa', '#DC2626'],
		'comercial' => ['Comercial / Vencimientos', '#F59E0B'],
		'averia' => ['Averías y Roturas', '#F97316']
	];

	public static function GetInstance(): WasteExecutiveControlCenterService
	{
		if (self::$instance === null)
		{
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function GetExecutiveData(
		?int $branchId = null,
		?string $date = null,
		?string $department = null,
		?string $causal = null,
		string $metric = 'pct',
		?string $startDate = null,
		?string $endDate = null
	): array {
		return $this->BuildExecutiveDataRaw($branchId, $date, $department, $causal, $metric, $startDate, $endDate);
	}

	private function Db(): \PDO
	{
		return DatabaseService::GetInstance()->GetDbConnectionRaw();
	}

	/** SQL fragmento para excluir causales de seguridad/hurto en el módulo de Mermas */
	private static function SqlExcludeSecurity(): string
	{
		return "IFNULL(r.censo_category, '') NOT IN ('robo_interno', 'robo_externo') AND UPPER(IFNULL(r.code, '')) NOT IN ('M03', 'M04', 'FPO01', 'FEL01')";
	}

	/** SQL fragmento para detectar ajustes contables (no son merma física real) */
	private static function SqlIsAdjustment(): string
	{
		return "(IFNULL(r.akcal_classification, 'operativa') = 'ajuste' OR IFNULL(w.loss_category, '') = 'ajuste' OR UPPER(IFNULL(r.code, '')) IN ('AJU', 'A01', 'A02'))";
	}

	/**
	 * Merma aprobada agrupada.
	 * @param string $scope 'real' (merma física sin ajustes/hurto), 'adjustments' (solo ajustes contables), 'all' (según filtro causal explícito)
	 */
	private function WasteBy(?int $branchId, string $from, string $to, ?array $deptKeys, ?string $causal, ?string $group = null, string $scope = 'real'): array
	{
		$m = DailyMetricsService::GetInstance();
		$where = "w.status = 'approved' AND date(w.created_timestamp) BETWEEN :f AND :t AND " . $m->DeptSql($deptKeys)
			. ' AND ' . self::SqlExcludeSecurity()
			. ($branchId ? ' AND w.branch_id = ' . intval($branchId) : '');
		$p = [':f' => $from, ':t' => $to];

		if ($scope === 'real' && $causal !== 'ajuste')
		{
			$where .= ' AND NOT ' . self::SqlIsAdjustment();
		}
		elseif ($scope === 'adjustments')
		{
			$where .= ' AND ' . self::SqlIsAdjustment();
		}

		if ($causal !== null && $causal !== '' && $causal !== 'todas' && $causal !== 'todos')
		{
			if (ctype_digit($causal))
			{
				$where .= ' AND w.waste_reason_id = ' . intval($causal);
			}
			else
			{
				$where .= ' AND (IFNULL(r.akcal_classification, \'operativa\') = :c OR IFNULL(w.loss_category, \'\') = :c)';
				$p[':c'] = $causal;
			}
		}

		$sel = $group ? "$group AS g, " : '';
		$st = $this->Db()->prepare("SELECT {$sel}COUNT(*) AS n, IFNULL(SUM(w.total_cost), 0) AS cost, IFNULL(SUM(w.amount), 0) AS units, IFNULL(SUM(w.financial_recovery_amount), 0) AS recovered
			FROM waste_records w JOIN products p ON p.id = w.product_id LEFT JOIN waste_reasons r ON r.id = w.waste_reason_id WHERE $where" . ($group ? ' GROUP BY g' : ''));
		$st->execute($p);
		$rows = $st->fetchAll(\PDO::FETCH_ASSOC);
		return $group ? $rows : ($rows[0] ?? ['n' => 0, 'cost' => 0, 'units' => 0, 'recovered' => 0]);
	}

	/** Trazo SVG (120×20) de una serie */
	private static function Spark(array $values): string
	{
		$n = count($values);
		if ($n < 2)
		{
			return 'M0,10 L120,10';
		}
		$max = max($values);
		$min = min($values);
		$span = ($max - $min) ?: 1;
		$pts = [];
		foreach (array_values($values) as $i => $v)
		{
			$pts[] = round($i * 120 / ($n - 1), 1) . ',' . round(18 - ($v - $min) / $span * 14, 1);
		}
		return 'M' . implode(' L', $pts);
	}

	private static function Arrow(?float $d, string $suffix = '%', int $dec = 1): string
	{
		return $d === null ? '' : (($d > 0 ? '↑ +' : ($d < 0 ? '↓ ' : '→ ')) . number_format($d, $dec) . $suffix);
	}

	private function BuildExecutiveDataRaw(?int $branchId, ?string $date, ?string $department, ?string $causal, string $metric, ?string $startDate = null, ?string $endDate = null): array
	{
		$m = DailyMetricsService::GetInstance();
		$date = ($date && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) ? $date : date('Y-m-d');
		$from = ($startDate && preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)) ? $startDate : substr($date, 0, 8) . '01';
		$to = ($endDate && preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) ? $endDate : $date;
		if ($from > $to)
		{
			[$from, $to] = [$to, $from];
		}

		$deptKeys = $m->DepartmentFilter($department);
		$causal = ($causal && $causal !== 'todas' && $causal !== 'todos') ? $causal : null;

		// Período comparativo equivalente: mismos días del mes anterior si inicia el día 1 del mismo mes, si no el tramo inmediato anterior equivalente
		$dayOfMonth = intval(date('j', strtotime($to)));
		if (substr($from, 8, 2) === '01' && substr($from, 0, 7) === substr($to, 0, 7))
		{
			$prevFrom = date('Y-m-01', strtotime("$from -1 month"));
			$prevTo = date('Y-m-d', min(strtotime("$prevFrom +" . ($dayOfMonth - 1) . ' days'), strtotime(date('Y-m-t', strtotime($prevFrom)))));
		}
		else
		{
			$spanDays = max(1, intval(round((strtotime($to) - strtotime($from)) / 86400)) + 1);
			$prevTo = date('Y-m-d', strtotime($from . ' -1 day'));
			$prevFrom = date('Y-m-d', strtotime($prevTo . ' -' . ($spanDays - 1) . ' days'));
		}

		$monthLabel = ['', 'Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];

		$sales = $m->Sales($branchId, $from, $to, $deptKeys);
		$salesPrev = $m->Sales($branchId, $prevFrom, $prevTo, $deptKeys);

		// Merma real (física) vs Ajustes contables (separados)
		$w = $this->WasteBy($branchId, $from, $to, $deptKeys, $causal, null, 'real');
		$wPrev = $this->WasteBy($branchId, $prevFrom, $prevTo, $deptKeys, $causal, null, 'real');
		$wAdj = $this->WasteBy($branchId, $from, $to, $deptKeys, null, null, 'adjustments');
		$wAdjPrev = $this->WasteBy($branchId, $prevFrom, $prevTo, $deptKeys, null, null, 'adjustments');

		$cost = round(floatval($w['cost']), 2);
		$units = round(floatval($w['units']), 2);
		$costPrev = floatval($wPrev['cost']);
		$adjCost = round(floatval($wAdj['cost']), 2);
		$adjUnits = round(floatval($wAdj['units']), 2);
		$adjCostPrev = floatval($wAdjPrev['cost']);

		$pct = $sales['has_data'] ? $m->WastePct($cost, $sales) : null;
		$pctPrev = $salesPrev['has_data'] ? $m->WastePct($costPrev, $salesPrev) : null;

		$targets = $m->Targets($branchId, $to);
		$targetPct = floatval($targets['waste_target_pct']);

		$pendingSql = "SELECT COUNT(*), IFNULL(SUM(w.total_cost), 0), IFNULL(SUM(w.amount), 0) FROM waste_records w LEFT JOIN waste_reasons r ON r.id = w.waste_reason_id WHERE w.status = 'pending_approval' AND " . self::SqlExcludeSecurity() . ($branchId ? ' AND w.branch_id = ' . intval($branchId) : '');
		$pending = $this->Db()->query($pendingSql)->fetch(\PDO::FETCH_NUM);
		$noData = intval($w['n']) === 0 && $adjCost <= 0 && !$sales['has_data'];

		$byType = array_column($this->WasteBy($branchId, $from, $to, $deptKeys, $causal, "IFNULL(r.akcal_classification, 'operativa')", 'real'), null, 'g');
		$byTypePrev = array_column($this->WasteBy($branchId, $prevFrom, $prevTo, $deptKeys, $causal, "IFNULL(r.akcal_classification, 'operativa')", 'real'), null, 'g');
		$tc = fn($k, $src) => floatval($src[$k]['cost'] ?? 0);
		$tu = fn($k, $src) => floatval($src[$k]['units'] ?? 0);
		$share = fn($v) => $cost > 0 ? number_format($v / $cost * 100, 1) . '% de merma real' : 'Sin merma en el período';
		$dPct = fn($a, $b) => $b > 0 ? round(($a - $b) / $b * 100, 1) : null;

		// Serie diaria de 14 días para los trazos (sobre merma física real)
		$daily = $this->WasteBy($branchId, date('Y-m-d', strtotime("$to -13 days")), $to, $deptKeys, $causal, 'date(w.created_timestamp)', 'real');
		$dailyMap = array_column($daily, 'cost', 'g');
		$series = [];
		for ($i = 13; $i >= 0; $i--)
		{
			$series[] = floatval($dailyMap[date('Y-m-d', strtotime("$to -$i days"))] ?? 0);
		}
		$spark = self::Spark($series);

		// Vencimientos & Errores de procedimiento explícitos (Features 5 & 8)
		$branchWhere = $branchId ? ' AND w.branch_id = ' . intval($branchId) : '';
		$deptWhere = ' AND ' . $m->DeptSql($deptKeys) . ' AND ' . self::SqlExcludeSecurity() . ' AND NOT ' . self::SqlIsAdjustment();
		$expirySt = $this->Db()->prepare("SELECT IFNULL(SUM(w.total_cost), 0), IFNULL(SUM(w.amount), 0) FROM waste_records w JOIN waste_reasons r ON r.id = w.waste_reason_id JOIN products p ON p.id = w.product_id
			WHERE w.status = 'approved' AND date(w.created_timestamp) BETWEEN :f AND :t AND (r.name LIKE '%encim%' OR r.name LIKE '%caduc%' OR r.name LIKE '%FEFO%' OR r.censo_category = 'vencimientos' OR UPPER(IFNULL(r.code, '')) IN ('M01', 'CAD'))$deptWhere$branchWhere");
		$expirySt->execute([':f' => $from, ':t' => $to]);
		[$expiryCost, $expiryUnits] = array_map('floatval', $expirySt->fetch(\PDO::FETCH_NUM) ?: [0, 0]);

		$procSt = $this->Db()->prepare("SELECT IFNULL(SUM(w.total_cost), 0), IFNULL(SUM(w.amount), 0), COUNT(*) FROM waste_records w JOIN waste_reasons r ON r.id = w.waste_reason_id JOIN products p ON p.id = w.product_id
			WHERE w.status = 'approved' AND date(w.created_timestamp) BETWEEN :f AND :t AND (r.censo_category = 'error_administrativo' OR UPPER(IFNULL(r.code, '')) IN ('PERR', 'EMA', 'M07') OR LOWER(r.name) LIKE '%procedim%' OR LOWER(r.name) LIKE '%etiquet%' OR LOWER(r.name) LIKE '%recibo%')$deptWhere$branchWhere");
		$procSt->execute([':f' => $from, ':t' => $to]);
		[$procCost, $procUnits, $procCount] = $procSt->fetch(\PDO::FETCH_NUM) ?: [0, 0, 0];

		// Recuperación financiera real (mermas + devoluciones a proveedor)
		$claimsSt = $this->Db()->prepare("SELECT COUNT(*), SUM(CASE WHEN status = 'credit_recovered' THEN 1 ELSE 0 END), SUM(CASE WHEN status = 'pending_credit' THEN 1 ELSE 0 END), IFNULL(SUM(credit_requested_amount), 0), IFNULL(SUM(credit_recovered_amount), 0)
			FROM supplier_returns WHERE date(created_timestamp) BETWEEN :f AND :t" . ($branchId ? ' AND branch_id = ' . intval($branchId) : ''));
		$claimsSt->execute([':f' => $from, ':t' => $to]);
		$claims = $claimsSt->fetch(\PDO::FETCH_NUM) ?: [0, 0, 0, 0, 0];
		$recovered = floatval($w['recovered']) + floatval($claims[4]);

		// KPIs principales (unificados en fuente única: valor $, unidades, % sobre ventas netas)
		$kpi = fn($label, $value, $delta, $good, $sub, $color, $extra = []) => array_merge(['label' => $label, 'value' => $value, 'delta' => $delta, 'delta_is_good' => $good, 'subtext' => $sub, 'sparkline' => $spark, 'spark_color' => $color], $extra);
		$dTotal = $dPct($cost, $costPrev);
		$dPp = ($pct !== null && $pctPrev !== null) ? round($pct - $pctPrev, 2) : null;
		$kpis = [
			'total_waste' => $kpi(
				'Merma real total',
				'$' . number_format($cost, 2),
				self::Arrow($dTotal),
				$dTotal !== null && $dTotal <= 0,
				number_format($units, 1) . ' un. · ' . (intval($pending[0]) > 0 ? intval($pending[0]) . ' por aprobar ($' . number_format(floatval($pending[1]), 0) . ')' : 'sin ajustes contables'),
				'#EF4444',
				['raw_cost' => $cost, 'units' => $units, 'count' => intval($w['n']), 'pending_count' => intval($pending[0]), 'pending_cost' => floatval($pending[1])]
			),
			'waste_sales' => $kpi(
				'Merma / ventas netas',
				$pct === null ? 'Sin información' : number_format($pct, 2) . '%',
				self::Arrow($dPp, ' pp', 2),
				$dPp !== null && $dPp <= 0,
				'Meta: ≤ ' . number_format($targetPct, 2) . '% (base ventas netas' . ($targets['sources']['waste_pct'] === 'config' ? ')' : ' · presupuesto)'),
				'#EF4444',
				['raw_pct' => $pct, 'raw_sales' => floatval($sales['net_sales']), 'target_pct' => $targetPct]
			),
			'operative' => $kpi('Merma operativa', '$' . number_format($tc('operativa', $byType), 2), self::Arrow($dPct($tc('operativa', $byType), $tc('operativa', $byTypePrev))), $tc('operativa', $byType) <= $tc('operativa', $byTypePrev), number_format($tu('operativa', $byType), 1) . ' un. · ' . $share($tc('operativa', $byType)), '#EF4444'),
			'commercial' => $kpi('Merma comercial', '$' . number_format($tc('comercial', $byType), 2), self::Arrow($dPct($tc('comercial', $byType), $tc('comercial', $byTypePrev))), $tc('comercial', $byType) <= $tc('comercial', $byTypePrev), number_format($tu('comercial', $byType), 1) . ' un. · ' . $share($tc('comercial', $byType)), '#F59E0B'),
			'breakage' => $kpi('Averías y roturas', '$' . number_format($tc('averia', $byType), 2), self::Arrow($dPct($tc('averia', $byType), $tc('averia', $byTypePrev))), $tc('averia', $byType) <= $tc('averia', $byTypePrev), number_format($tu('averia', $byType), 1) . ' un. · ' . $share($tc('averia', $byType)), '#F97316'),
			'adjustments' => $kpi('Ajustes contables (separado)', '$' . number_format($adjCost, 2), self::Arrow($dPct($adjCost, $adjCostPrev)), $adjCost <= $adjCostPrev, number_format($adjUnits, 1) . ' un. · excluidos de la merma física real', '#10B981', ['raw_cost' => $adjCost, 'units' => $adjUnits]),
			'recovery' => $kpi('Recuperación financiera', '$' . number_format($recovered, 2), '', true, $recovered > 0 ? intval($claims[1]) . ' notas de crédito recuperadas' : 'Sin recuperaciones registradas en el período', '#10B981'),
			'avoidable' => $kpi('Vencimientos evitables', '$' . number_format($expiryCost, 2), '', false, number_format($expiryUnits, 1) . ' un. vencidas · prevenible con FEFO y rebajas', '#EF4444'),
			'procedural_errors' => $kpi('Errores de procedimiento', '$' . number_format(floatval($procCost), 2), '', false, intval($procCount) . ' incidentes (' . number_format(floatval($procUnits), 1) . ' un.) en operación/registro', '#6366F1')
		];

		// Evolución mensual del año vs. año anterior (SOLO datos reales en DB; null si no existe histórico)
		$year = intval(date('Y', strtotime($to)));
		$monthIdx = intval(date('n', strtotime($to)));
		$evo = ['labels' => array_slice($monthLabel, 1), 'pct' => ['current' => [], 'previous' => [], 'target' => []], 'money' => ['current' => [], 'previous' => [], 'target' => []]];

		foreach ([$year => 'current', $year - 1 => 'previous'] as $y => $key)
		{
			for ($mo = 1; $mo <= 12; $mo++)
			{
				$f = sprintf('%04d-%02d-01', $y, $mo);
				$t = date('Y-m-t', strtotime($f));
				if ($f > $to && $key === 'current')
				{
					$evo['pct'][$key][] = null;
					$evo['money'][$key][] = null;
					$evo['pct']['target'][] = $targetPct;
					$evo['money']['target'][] = null;
					continue;
				}
				$endCap = $key === 'current' ? min($t, $to) : $t;
				$s = $m->Sales($branchId, $f, $endCap, $deptKeys);
				$wm = $this->WasteBy($branchId, $f, $endCap, $deptKeys, $causal, null, 'real');
				$wc = floatval($wm['cost']);
				$hasAnyWaste = intval($wm['n']) > 0;
				$valMoney = ($s['has_data'] || $hasAnyWaste) ? round($wc, 0) : null;
				$valPct = $s['has_data'] ? $m->WastePct($wc, $s) : null;

				$evo['money'][$key][] = $valMoney;
				$evo['pct'][$key][] = $valPct;
				if ($key === 'current')
				{
					$evo['pct']['target'][] = $targetPct;
					$evo['money']['target'][] = $s['has_data'] ? round($s['net_sales'] * $targetPct / 100, 0) : null;
				}
			}
		}

		// Por cada $1,000 (equivale a pct * 10)
		$evo['per1k'] = [
			'current' => array_map(fn($v) => $v !== null ? round($v * 10, 2) : null, $evo['pct']['current']),
			'previous' => array_map(fn($v) => $v !== null ? round($v * 10, 2) : null, $evo['pct']['previous']),
			'target' => array_map(fn($v) => $v !== null ? round($v * 10, 2) : null, $evo['pct']['target'])
		];

		$sameMonthLyPct = $evo['pct']['previous'][$monthIdx - 1] ?? null;
		$sameMonthLyMoney = $evo['money']['previous'][$monthIdx - 1] ?? null;
		$sameMonthLyPer1k = $sameMonthLyPct !== null ? round($sameMonthLyPct * 10, 2) : null;

		$daysInMonth = intval(date('t', strtotime($to)));
		$projMoney = ($dayOfMonth > 0 && ($cost > 0 || $sales['has_data'])) ? round($cost / $dayOfMonth * $daysInMonth, 0) : null;
		$projPer1k = $pct !== null ? round($pct * 10, 2) : null;

		$dPctPp = ($pct !== null && $sameMonthLyPct !== null) ? round($pct - $sameMonthLyPct, 2) : null;
		$dMoneyPct = ($cost > 0 && $sameMonthLyMoney !== null && $sameMonthLyMoney > 0) ? round(($cost - $sameMonthLyMoney) / $sameMonthLyMoney * 100, 1) : null;
		$dPer1k = ($pct !== null && $sameMonthLyPct !== null) ? round(($pct - $sameMonthLyPct) * 10, 2) : null;

		$dProjPp = ($pct !== null) ? round($pct - $targetPct, 2) : null;
		$expectedMonthSales = $dayOfMonth > 0 ? ($sales['net_sales'] / $dayOfMonth * $daysInMonth) : 0;
		$targetMonthMoney = $expectedMonthSales > 0 ? ($targetPct / 100 * $expectedMonthSales) : 0;
		$dProjMoney = ($projMoney !== null && $targetMonthMoney > 0) ? round(($projMoney - $targetMonthMoney) / $targetMonthMoney * 100, 1) : null;

		$evolution = $evo + [
			'current_month' => [
				'label' => 'Período (' . $monthLabel[$monthIdx] . ' ' . $year . ')',
				'value' => $pct === null ? 'Sin información' : number_format($pct, 2) . '%',
				'value_pct' => $pct === null ? 'Sin información' : number_format($pct, 2) . '%',
				'value_money' => '$' . number_format($cost, 0),
				'value_per1k' => $pct === null ? 'Sin información' : '$' . number_format($pct * 10, 2),
				'delta' => self::Arrow($dPctPp, ' pp', 2),
				'delta_pct' => self::Arrow($dPctPp, ' pp', 2),
				'delta_money' => self::Arrow($dMoneyPct, '%', 1),
				'delta_per1k' => self::Arrow($dPer1k, '', 2),
				'subtext' => $sameMonthLyPct !== null ? ('vs. año anterior (' . number_format($sameMonthLyPct, 2) . '%)') : 'Sin histórico registrado del año anterior',
				'subtext_pct' => $sameMonthLyPct !== null ? ('vs. año anterior (' . number_format($sameMonthLyPct, 2) . '%)') : 'Sin histórico registrado del año anterior',
				'subtext_money' => $sameMonthLyMoney !== null ? ('vs. año anterior ($' . number_format($sameMonthLyMoney, 0) . ')') : 'Sin histórico registrado del año anterior',
				'subtext_per1k' => $sameMonthLyPer1k !== null ? ('vs. año anterior ($' . number_format($sameMonthLyPer1k, 2) . '/$1,000)') : 'Sin histórico registrado del año anterior'
			],
			'closing_projection' => [
				'label' => 'Proyección de cierre',
				'value' => $projMoney === null ? 'Sin información' : '$' . number_format($projMoney, 0),
				'value_pct' => $pct === null ? 'Sin información' : number_format($pct, 2) . '%',
				'value_money' => $projMoney === null ? 'Sin información' : '$' . number_format($projMoney, 0),
				'value_per1k' => $projPer1k === null ? 'Sin información' : '$' . number_format($projPer1k, 2),
				'delta' => self::Arrow($dProjPp, ' pp', 2),
				'delta_pct' => self::Arrow($dProjPp, ' pp', 2),
				'delta_money' => self::Arrow($dProjMoney, '%', 1),
				'delta_per1k' => $dProjPp !== null ? self::Arrow($dProjPp * 10, '', 2) : '',
				'subtext' => 'ritmo diario del período; % vs. meta (' . number_format($targetPct, 2) . '%)',
				'subtext_pct' => 'ritmo diario del período; % vs. meta (' . number_format($targetPct, 2) . '%)',
				'subtext_money' => 'proyección mensual según ritmo diario actual',
				'subtext_per1k' => 'estimado fin de mes por cada $1,000 de venta'
			]
		];

		$donut = [];
		foreach (self::TYPES as $k => [$label, $color])
		{
			$v = $tc($k, $byType);
			$donut[] = ['label' => $label, 'amount' => round($v, 2), 'units' => round($tu($k, $byType), 1), 'share' => $cost > 0 ? round($v / $cost * 100, 1) : 0, 'color' => $color];
		}

		// Merma por departamento sobre la venta del mismo departamento
		$deptBar = [];
		foreach (DailyMetricsService::DEPARTMENTS as $k => [$name])
		{
			if ($deptKeys !== null && !in_array($k, $deptKeys, true))
			{
				continue;
			}
			$wd = $this->WasteBy($branchId, $from, $to, [$k], $causal, null, 'real');
			$wc = floatval($wd['cost']);
			$wu = floatval($wd['units']);
			$s = $m->Sales($branchId, $from, $to, [$k]);
			if ($wc <= 0 && !$s['has_data'])
			{
				continue;
			}
			$dp = $s['net_sales'] > 0 ? round($wc / $s['net_sales'] * 100, 2) : null;
			$deptBar[] = [
				'key' => $k,
				'name' => $name,
				'pct' => $dp ?? 0,
				'amount' => round($wc, 2),
				'units' => round($wu, 1),
				'sales' => round(floatval($s['net_sales']), 2),
				'color' => $dp === null ? '#64748B' : ($dp > $targetPct * 1.5 ? '#EF4444' : ($dp > $targetPct ? '#F59E0B' : '#10B981')),
				'over_target' => $dp !== null && $dp > $targetPct,
				'note' => $dp === null ? 'Sin ventas del departamento' : ''
			];
		}
		usort($deptBar, fn($a, $b) => $b['pct'] <=> $a['pct']);

		// Mapa de calor: merma física por día de semana y hora del registro (últimas 4 semanas hasta $to)
		$hmFrom = date('Y-m-d', strtotime("$to -27 days"));
		$hm = $this->WasteBy($branchId, $hmFrom, $to, $deptKeys, $causal, "CAST(strftime('%w', w.created_timestamp) AS INTEGER) * 100 + CAST(strftime('%H', w.created_timestamp) AS INTEGER)", 'real');
		$hmMap = array_column($hm, 'cost', 'g');
		$hmMax = max(1, max(array_map('floatval', $hmMap ?: [0])));
		$hmTotal = array_sum(array_map('floatval', $hmMap));
		$days = ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'];
		$hours = ['06', '07', '08', '09', '10', '11', '12', '13', '14', '15', '16', '17', '18', '19'];
		$grid = [];
		foreach ($days as $i => $dn)
		{
			$wd = $i === 6 ? 0 : $i + 1;
			$cells = [];
			foreach ($hours as $hh)
			{
				$v = floatval($hmMap[$wd * 100 + intval($hh)] ?? 0);
				$int = intval(round($v / $hmMax * 100));
				$cells[] = [
					'day' => $dn,
					'hour' => "$hh:00",
					'intensity' => $int,
					'color' => self::GetHeatmapColor($int),
					'merma_pct' => $hmTotal > 0 ? number_format($v / $hmTotal * 100, 1) . '% de 4 sem.' : '0%',
					'amount' => '$' . number_format($v / 4, 0) . '/día'
				];
			}
			$grid[] = ['day' => $dn, 'cells' => $cells];
		}

		// Top 10 productos por costo de merma real en el período
		$top = $this->WasteBy($branchId, $from, $to, $deptKeys, $causal, 'w.product_id', 'real');
		usort($top, fn($a, $b) => floatval($b['cost']) <=> floatval($a['cost']));
		$icons = [
			'tecnologia' => '📱',
			'electrodomesticos' => '🔌',
			'alimentos' => '🥫',
			'hogar' => '🏠',
			'accesorios' => '🎧',
			'dairy' => '🥛',
			'cuidado_personal' => '🧴',
			'bebidas' => '🧃',
			'carnes' => '🥩',
			'fruver' => '🥬',
			'congelados' => '❄️',
			'bakery' => '🍞',
			'electronica' => '📱',
			'otros' => '📦'
		];
		$topProducts = [];
		foreach (array_slice($top, 0, 10) as $i => $r)
		{
			$p = $this->Db()->query('SELECT name, sku, department FROM products WHERE id = ' . intval($r['g']))->fetch(\PDO::FETCH_ASSOC);
			if (!$p)
			{
				continue;
			}
			$dk = 'otros';
			foreach (DailyMetricsService::DEPARTMENTS as $k => $d)
			{
				if ($d[2] !== null && in_array($p['department'], $d[2], true))
				{
					$dk = $k;
				}
			}
			$psStmt = $this->Db()->prepare("SELECT IFNULL(SUM(si.subtotal), 0) FROM sale_items si JOIN sales s ON s.id = si.sale_id WHERE s.status = 'completed' AND si.product_id = :pid AND date(s.created_timestamp) BETWEEN :f AND :t" . ($branchId ? ' AND s.branch_id = ' . intval($branchId) : ''));
			$psStmt->execute([':pid' => intval($r['g']), ':f' => $from, ':t' => $to]);
			$ps = floatval($psStmt->fetchColumn());
			$topProducts[] = [
				'rank' => count($topProducts) + 1,
				'product_id' => intval($r['g']),
				'name' => $p['name'],
				'sku' => $p['sku'],
				'dept' => DailyMetricsService::DEPARTMENTS[$dk][0],
				'amount' => '$' . number_format(floatval($r['cost']), 2),
				'raw_cost' => round(floatval($r['cost']), 2),
				'units' => round(floatval($r['units']), 1),
				'pct_sales' => $ps > 0 ? number_format(floatval($r['cost']) / $ps * 100, 2) . '%' : 'Sin ventas',
				'icon' => $icons[$dk] ?? '📦'
			];
		}

		// Causas (Pareto de merma real)
		$causesRows = $this->WasteBy($branchId, $from, $to, $deptKeys, $causal, "IFNULL(r.name, 'Sin motivo')", 'real');
		usort($causesRows, fn($a, $b) => floatval($b['cost']) <=> floatval($a['cost']));
		$palette = ['#EF4444', '#F97316', '#F59E0B', '#EAB308', '#10B981', '#059669', '#14B8A6'];
		$causes = [];
		$acc = 0.0;
		foreach (array_slice($causesRows, 0, 7) as $i => $r)
		{
			$v = floatval($r['cost']);
			$acc += $v;
			$causes[] = [
				'name' => $r['g'],
				'amount' => round($v, 2),
				'units' => round(floatval($r['units']), 1),
				'pct' => $cost > 0 ? round($v / $cost * 100, 1) : 0,
				'color' => $palette[$i],
				'pareto' => $cost > 0 ? round($acc / $cost * 100, 1) : 0
			];
		}

		// Sucursales: misma definición física; la red es la suma
		$branchesTable = [];
		foreach (BranchService::GetInstance()->GetBranches() as $br)
		{
			$b = intval($br->id);
			if (!DairyAccess::CanSeeBranch($b))
			{
				continue;
			}
			$s = $m->Sales($b, $from, $to, $deptKeys);
			$sp = $m->Sales($b, $prevFrom, $prevTo, $deptKeys);
			$wb = $this->WasteBy($b, $from, $to, $deptKeys, $causal, null, 'real');
			$wc = floatval($wb['cost']);
			$wp = floatval($this->WasteBy($b, $prevFrom, $prevTo, $deptKeys, $causal, null, 'real')['cost']);
			$bp = $s['has_data'] ? $m->WastePct($wc, $s) : null;
			$bpp = $sp['has_data'] ? $m->WastePct($wp, $sp) : null;
			$status = $bp === null ? 'secondary' : ($bp > $targetPct * 1.5 ? 'danger' : ($bp > $targetPct ? 'warning' : 'success'));
			$branchesTable[] = [
				'id' => sprintf('%02d', $b),
				'branch_id' => $b,
				'name' => $br->name,
				'sales' => $s['has_data'] ? '$' . number_format($s['net_sales'], 0) : 'Sin información',
				'waste' => '$' . number_format($wc, 2),
				'units' => round(floatval($wb['units']), 1),
				'pct' => $bp === null ? 'Sin información' : number_format($bp, 2) . '%',
				'pct_num' => $bp,
				'delta' => ($bp !== null && $bpp !== null) ? self::Arrow(round($bp - $bpp, 2), ' pp', 2) : '',
				'status' => $status,
				'dot' => ['danger' => '🔴', 'warning' => '🟡', 'success' => '🟢', 'secondary' => '⚪'][$status]
			];
		}
		usort($branchesTable, fn($a, $b) => ($b['pct_num'] ?? -1) <=> ($a['pct_num'] ?? -1));

		$financialRecovery = [
			'recovered_amount' => '$' . number_format($recovered, 2),
			'raw_recovered' => $recovered,
			'delta_pct' => '',
			'claims_count' => intval($claims[0]),
			'claims_closed' => intval($claims[1]),
			'claims_in_process' => intval($claims[2]),
			'requested_amount' => floatval($claims[3]),
			'recovery_rate' => floatval($claims[3]) > 0 ? round(floatval($claims[4]) / floatval($claims[3]) * 100) : null,
			'target_rate' => 80
		];

		// Feature 18: Indicadores reales de reducción de pérdidas (Actionable Intelligence)
		$planStats = $this->Db()->query("SELECT COUNT(*) AS total, SUM(CASE WHEN status IN ('closed','verified','completed') THEN 1 ELSE 0 END) AS closed_cnt FROM waste_action_plans" . ($branchId ? ' WHERE branch_id = ' . intval($branchId) : ''))->fetch(\PDO::FETCH_ASSOC);
		$totalPlans = intval($planStats['total'] ?? 0);
		$closedPlans = intval($planStats['closed_cnt'] ?? 0);

		$evSt = $this->Db()->prepare("SELECT COUNT(*) AS req, SUM(CASE WHEN TRIM(IFNULL(w.evidence_photo_path, '')) <> '' THEN 1 ELSE 0 END) AS with_ev
			FROM waste_records w JOIN waste_reasons r ON r.id = w.waste_reason_id
			WHERE w.status <> 'rejected' AND r.requires_evidence = 1 AND date(w.created_timestamp) BETWEEN :f AND :t" . ($branchId ? ' AND w.branch_id = ' . intval($branchId) : ''));
		$evSt->execute([':f' => $from, ':t' => $to]);
		$evRow = $evSt->fetch(\PDO::FETCH_ASSOC);
		$evReq = intval($evRow['req'] ?? 0);
		$evWith = intval($evRow['with_ev'] ?? 0);

		$slaSt = $this->Db()->prepare("SELECT AVG(MAX(0, julianday(COALESCE(closed_timestamp, approved_timestamp)) - julianday(created_timestamp)))
			FROM waste_records WHERE status = 'approved' AND COALESCE(closed_timestamp, approved_timestamp) IS NOT NULL AND date(created_timestamp) BETWEEN :f AND :t" . ($branchId ? ' AND branch_id = ' . intval($branchId) : ''));
		$slaSt->execute([':f' => $from, ':t' => $to]);
		$slaVal = $slaSt->fetchColumn();

		$reincRows = $this->Db()->query("SELECT COUNT(DISTINCT product_id) AS all_skus, SUM(CASE WHEN cnt > 1 THEN 1 ELSE 0 END) AS repeat_skus FROM (
			SELECT product_id, COUNT(*) AS cnt FROM waste_records WHERE status = 'approved' AND date(created_timestamp) >= date('now', '-30 days')" . ($branchId ? ' AND branch_id = ' . intval($branchId) : '') . " GROUP BY product_id
		)")->fetch(\PDO::FETCH_ASSOC);
		$allSkus30 = intval($reincRows['all_skus'] ?? 0);
		$repeatSkus30 = intval($reincRows['repeat_skus'] ?? 0);

		$avoidedLoss = ($dPp !== null && $dPp < 0 && $sales['net_sales'] > 0)
			? round(abs($dPp) / 100 * floatval($sales['net_sales']), 2)
			: 0.0;

		$reductionMetrics = [
			'pct_delta_pp' => $dPp,
			'avoided_loss_amount' => $avoidedLoss,
			'plan_closure_pct' => $totalPlans > 0 ? round($closedPlans / $totalPlans * 100, 1) : null,
			'plans_total' => $totalPlans,
			'plans_closed' => $closedPlans,
			'evidence_compliance_pct' => $evReq > 0 ? round($evWith / $evReq * 100, 1) : null,
			'evidence_required' => $evReq,
			'evidence_missing' => max(0, $evReq - $evWith),
			'avg_resolution_days' => $slaVal !== false && $slaVal !== null ? round(floatval($slaVal), 1) : null,
			'reincidence_rate_pct' => $allSkus30 > 0 ? round($repeatSkus30 / $allSkus30 * 100, 1) : null
		];

		$inv = $m->Inventory($branchId);
		$qs = $branchId ? '?branch_id=' . $branchId : '';
		$priorityDecisions = [];
		if ($inv['expiring_products'] > 0)
		{
			$priorityDecisions[] = [
				'id' => 'dec_expiry',
				'severity' => 'danger',
				'icon' => 'fa-triangle-exclamation',
				'title' => 'Vencimientos próximos (FEFO)',
				'description' => $inv['expiring_products'] . ' producto(s) vencen en ≤ 7 días. Costo expuesto: $' . number_format($inv['expiring_value'], 0) . '.',
				'badge_text' => 'Ejecutar FEFO / rebaja',
				'badge_class' => 'badge-danger',
				'href' => '/waste#tab-fefo' . $qs
			];
		}
		if (intval($pending[0]) > 0)
		{
			$priorityDecisions[] = [
				'id' => 'dec_pending',
				'severity' => 'warning',
				'icon' => 'fa-scale-unbalanced',
				'title' => 'Mermas por aprobar',
				'description' => intval($pending[0]) . ' registro(s) por $' . number_format(floatval($pending[1]), 2) . ' (' . number_format(floatval($pending[2]), 1) . ' un.): requieren validación antes de descargar inventario.',
				'badge_text' => 'Ir a aprobaciones',
				'badge_class' => 'badge-warning',
				'href' => '/waste/approvals' . $qs
			];
		}
		if ($reductionMetrics['evidence_missing'] > 0)
		{
			$priorityDecisions[] = [
				'id' => 'dec_evidence',
				'severity' => 'danger',
				'icon' => 'fa-camera',
				'title' => 'Evidencia fotográfica pendiente',
				'description' => $reductionMetrics['evidence_missing'] . ' incidente(s) con causal obligatoria aún no tienen foto de evidencia.',
				'badge_text' => 'Auditar casos',
				'badge_class' => 'badge-danger',
				'href' => '/waste#tab-clasificacion' . $qs
			];
		}
		if ($causes)
		{
			$priorityDecisions[] = [
				'id' => 'dec_cause',
				'severity' => 'success',
				'icon' => 'fa-bullseye',
				'title' => 'Causa #1 de Pareto: ' . $causes[0]['name'],
				'description' => number_format($causes[0]['pct'], 1) . '% de la merma física del período ($' . number_format($causes[0]['amount'], 2) . ').',
				'badge_text' => 'Acción correctiva',
				'badge_class' => 'badge-success',
				'href' => '/waste#tab-acciones' . $qs
			];
		}

		return [
			'date' => $to,
			'branch_id' => $branchId,
			'no_data' => $noData,
			'department' => $department ?: 'todos',
			'causal' => $causal ?: 'todas',
			'metric' => $metric,
			'period' => ['from' => $from, 'to' => $to, 'compare_from' => $prevFrom, 'compare_to' => $prevTo],
			'target_pct' => $targetPct,
			'last_updated' => date('d/m/Y H:i'),
			'kpis' => $kpis,
			'reduction_metrics' => $reductionMetrics,
			'evolution' => $evolution,
			'donut_types' => ['total' => '$' . number_format($cost, 2), 'units' => $units, 'items' => $donut],
			'departments_bar' => $deptBar,
			'heatmap' => ['days' => $days, 'hours' => $hours, 'grid' => $grid],
			'top_products' => $topProducts,
			'causes' => $causes,
			'branches_table' => $branchesTable,
			'recovery' => $financialRecovery,
			'priority_decisions' => $priorityDecisions
		];
	}

	private static function GetHeatmapColor(int $intensity): string
	{
		if ($intensity <= 25) return '#bbf7d0';
		if ($intensity <= 45) return '#fef08a';
		if ($intensity <= 65) return '#fed7aa';
		if ($intensity <= 85) return '#fb923c';
		return '#ef4444';
	}
}
