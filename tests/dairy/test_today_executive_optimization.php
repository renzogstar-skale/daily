<?php
require __DIR__ . '/bootstrap.php';

echo "=== Test Suite: Optimización Panel Ejecutivo y HOY (Módulo 17) ===\n";

$todayExec = \Grocy\Services\TodayExecutiveSalesService::GetInstance();
$todayCmd = \Grocy\Services\TodayCommandService::GetInstance();
$kpiSvc = \Grocy\Services\KpiService::GetInstance();
$m = \Grocy\Services\DailyMetricsService::GetInstance();

// 1. HOY: Cumplimiento real > 100% sin tope y frescura real de base de datos
$d = $todayExec->GetExecutiveData(null, '2026-11-10');
$comp = $d['kpis']['target_compliance'] ?? [];
ok('HOY: target_compliance expone raw_numeric y numeric reales sin recorte en 100%',
	$comp['raw_numeric'] !== null && floatval($comp['numeric']) === floatval($comp['raw_numeric']),
	['numeric' => $comp['numeric'] ?? null, 'raw' => $comp['raw_numeric'] ?? null]);
ok('HOY: red 2026-11-10 muestra >100% real (' . ($comp['value'] ?? '') . ')',
	floatval($comp['numeric'] ?? 0) > 100.0,
	$comp['value'] ?? null);

ok('HOY: last_updated proviene de registros reales (no reloj ficticio vacío) y expone last_updated_iso',
	!empty($d['last_updated']) && !empty($d['last_updated_iso']),
	['last_updated' => $d['last_updated'] ?? null, 'iso' => $d['last_updated_iso'] ?? null]);

ok('HOY: corrective_actions reales poblados',
	isset($d['corrective_actions']['total'], $d['corrective_actions']['open'], $d['corrective_actions']['overdue']),
	$d['corrective_actions'] ?? []);

// 2. HOY: Procesos enriquecidos (desviación, impacto económico, tendencia, responsable, actualización, enlace)
$procs = array_column($d['process_statuses'] ?? [], null, 'key');
ok('HOY: 7 procesos completos con metadatos de gobierno',
	count($procs) === 7,
	array_keys($procs));
foreach (['purchasing', 'inventory', 'sales', 'dispatch', 'workforce', 'quality', 'waste'] as $pk)
{
	$p = $procs[$pk] ?? null;
	ok("HOY proceso '$pk' tiene deviation, economic_impact_label, trend_label, owner, last_updated",
		$p && array_key_exists('deviation', $p) && array_key_exists('economic_impact_label', $p)
		&& !empty($p['trend_label']) && !empty($p['owner']) && !empty($p['last_updated']),
		$p['owner'] ?? null);
}
ok('HOY: proceso despachos enlaza a /dispatches',
	isset($procs['dispatch']) && strpos($procs['dispatch']['href'], '/dispatches') === 0,
	$procs['dispatch']['href'] ?? null);

// 3. TodayCommandService anclado a fecha histórica sin contaminar hoy
$cmdHist = $todayCmd->Get(1, true, fn($p) => true, '2026-11-08');
ok('TodayCommandService::Get acepta $date histórica y ancla d[now] a 2026-11-08',
	strpos($cmdHist['now'] ?? '', '2026-11-08') === 0,
	$cmdHist['now'] ?? null);

// 4. HOY: fecha sin ventas devuelve "Sin información" (sin ceros inventados)
$dEmpty = $todayExec->GetExecutiveData(1, '2019-01-05');
ok('HOY: fecha sin ventas devuelve Sin información en ventas netas y cumplimiento',
	$dEmpty['kpis']['sales_net']['value'] === 'Sin información' && $dEmpty['kpis']['target_compliance']['numeric'] === null,
	['sales' => $dEmpty['kpis']['sales_net']['value'], 'comp' => $dEmpty['kpis']['target_compliance']['value']]);

// 5. Panel Ejecutivo: KpiService & BuildExecutivePanelOverview reales y sin demo hardcodeado
$kpiData = $kpiSvc->GetExecutiveDashboardData(null, 'month', null, null, '2026-11-10');
$panel = $kpiSvc->BuildExecutivePanelOverview(null, 'month', $kpiData, [], 0);

ok('Panel Ejecutivo eliminó claves de maqueta (colombia_branches, photo_kpis, top_departments)',
	!isset($panel['colombia_branches']) && !isset($panel['photo_kpis']) && !isset($panel['top_departments']));

ok('Panel Ejecutivo expone financial_summary, corrective_actions, kpi_definitions y department_breakdown reales',
	!empty($panel['financial_summary']['has_data'])
	&& isset($panel['corrective_actions']['total'])
	&& !empty($panel['kpi_definitions'])
	&& !empty($panel['department_breakdown']),
	['depts' => count($panel['department_breakdown'] ?? []), 'defs' => count($panel['kpi_definitions'] ?? [])]);

// Contribución neta = margen bruto - (merma - recuperación)
$fs = $panel['financial_summary'];
$expectedNetOp = round($fs['gross_margin'] - $fs['net_waste_cost'], 2);
ok('Panel financiero: contribución operativa = margen bruto − merma neta',
	abs($fs['net_operating_contribution'] - $expectedNetOp) < 0.02,
	[$fs['net_operating_contribution'], $expectedNetOp]);

// Ranking excluye ubicaciones en tránsito y expone compliance_pct sin tope
$r1 = null;
foreach ($kpiData['branch_ranking'] as $br)
{
	if (intval($br['branch_id']) === 1) { $r1 = $br; break; }
}
$stockNoTransit1 = floatval($db->query("SELECT IFNULL(SUM(s.amount * IFNULL(s.price, IFNULL(plp.price, 0))), 0) FROM stock s JOIN locations l ON s.location_id = l.id JOIN products p ON s.product_id = p.id LEFT JOIN cache__products_last_purchased plp ON p.id = plp.product_id WHERE l.branch_id = 1 AND IFNULL(l.is_transit, 0) = 0")->fetchColumn());
ok('Ranking sucursal 1 excluye inventario en tránsito',
	$r1 && abs(floatval($r1['inventory_valuation']) - $stockNoTransit1) < 1.0,
	[$r1['inventory_valuation'] ?? null, $stockNoTransit1]);
ok('Ranking incluye compliance_pct y coverage_days reales',
	$r1 && array_key_exists('compliance_pct', $r1) && array_key_exists('coverage_days', $r1),
	[$r1['compliance_pct'] ?? null, $r1['coverage_days'] ?? null]);

// 6. KpiService filtrado por sucursal aisla supplier_deliveries y operations_status enlaza a /dispatches & /operations/scheduling
$panelBranch1 = $kpiSvc->BuildExecutivePanelOverview(1, 'day', $kpiSvc->GetExecutiveDashboardData(1, 'day', null, null, '2026-11-10'), [], 0);
$ops1 = array_column($panelBranch1['operations_status'] ?? [], null, 'key');
ok('Panel operations_status: despachos apunta a /dispatches y personal a /operations/scheduling',
	($ops1['despachos']['url'] ?? '') === '/dispatches' && ($ops1['personal']['url'] ?? '') === '/operations/scheduling',
	[$ops1['despachos']['url'] ?? null, $ops1['personal']['url'] ?? null]);
ok('Panel operations_status incluye deviation, economic_impact_label, owner y last_updated',
	!empty($ops1['ventas']['owner']) && array_key_exists('deviation', $ops1['ventas']) && !empty($ops1['ventas']['last_updated']),
	json_encode($ops1['ventas'] ?? []));

// 7. Validación de errores y rangos personalizados en KpiService
$badCustom = $kpiSvc->GetExecutiveDashboardData(null, 'custom', 'no-es-fecha', '2026-99-99', '2026-11-10');
ok('KpiService custom con fechas malformadas cae a rango mensual válido sin error SQL',
	preg_match('/^\d{4}-\d{2}-\d{2}$/', $badCustom['start_date'] ?? '') === 1
	&& preg_match('/^\d{4}-\d{2}-\d{2}$/', $badCustom['end_date'] ?? '') === 1,
	json_encode(['start' => $badCustom['start_date'] ?? null, 'end' => $badCustom['end_date'] ?? null]));

$invCustom = $kpiSvc->GetExecutiveDashboardData(1, 'custom', '2026-11-10', '2026-11-01');
ok('KpiService custom con fechas invertidas normaliza start <= end',
	($invCustom['start_date'] ?? '') === '2026-11-01' && ($invCustom['end_date'] ?? '') === '2026-11-10',
	json_encode(['start' => $invCustom['start_date'] ?? null, 'end' => $invCustom['end_date'] ?? null]));

$emptyKpi = $kpiSvc->GetExecutiveDashboardData(1, 'day', null, null, '2019-01-05');
$emptyPanel = $kpiSvc->BuildExecutivePanelOverview(1, 'day', $emptyKpi, [], 0);
ok('Panel Ejecutivo en fecha sin ventas informa gross_sales=0 y margin_pct=null sin NaN',
	floatval($emptyPanel['financial_summary']['gross_sales'] ?? -1) === 0.0
	&& array_key_exists('margin_pct', $emptyPanel['financial_summary'])
	&& $emptyPanel['financial_summary']['margin_pct'] === null
	&& !is_nan(floatval($emptyKpi['margin_percentage'] ?? 0)),
	json_encode($emptyPanel['financial_summary'] ?? []));

// 8. Consistencia compartida HOY vs Panel Ejecutivo (mismo contexto: sucursal 1, 2026-11-10, período día)
$todayS1 = $todayExec->GetExecutiveData(1, '2026-11-10');
$panelKpiS1 = $kpiSvc->GetExecutiveDashboardData(1, 'day', null, null, '2026-11-10');
ok('Cálculo compartido: ventas netas HOY coinciden con Panel Ejecutivo (sucursal 1 · 2026-11-10)',
	abs(floatval($todayS1['kpis']['sales_net']['numeric'] ?? 0) - floatval($panelKpiS1['net_sales'] ?? -1)) < 0.05,
	json_encode([$todayS1['kpis']['sales_net']['numeric'] ?? null, $panelKpiS1['net_sales'] ?? null]));

echo "\nResumen: $count pruebas, $fails fallidas\n";
exit($fails ? 1 : 0);
