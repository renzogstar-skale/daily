<?php
/**
 * Stock & Movimientos: conciliación con el inventario consolidado, participaciones, filtros por departamento
 * y sucursal, alertas y movimientos del contexto, y actualización tras operaciones reales (recepción y merma).
 */

use Grocy\Services\DailyMetricsService;
use Grocy\Services\ReconciliationService;
use Grocy\Services\StockExecutiveOverviewService;
use Grocy\Services\StockOverviewInsightsService;
use Grocy\Services\WasteService;

require __DIR__ . '/bootstrap.php';

$svc = StockExecutiveOverviewService::GetInstance();
$dm = DailyMetricsService::GetInstance();
$eq = fn($a, $b, $tol = 0.02) => abs(floatval($a) - floatval($b)) <= $tol;
$data = fn(array $f = []) => $svc->GetExecutiveData($f);

$all = $data();
$ov = $all['dept_overview'];
$rec = $all['meta']['reconciliation'];

echo "\n1. Conciliación del consolidado\n";
ok('Valor = inventario consolidado (HOY/Panel)', $eq($all['kpis']['inventory_value']['numeric'], $dm->Inventory(null)['value'], 0.05), $all['kpis']['inventory_value']['numeric'] . ' vs ' . $dm->Inventory(null)['value']);
ok('Valor = suma de departamentos', $eq($ov['total_value'], $all['kpis']['inventory_value']['numeric'], 0.05));
ok('Valor = suma de sucursales', $eq($rec['branches_sum_valuation'], $rec['kpi_valuation'], 0.05));
ok('Unidades = suma de departamentos', $eq($rec['donut_sum_units'], $rec['kpi_units'], 1));
ok('Conciliación reportada', $rec['is_reconciled'] === true);
ok('Participaciones suman 100.0', $eq(array_sum(array_column($ov['rows'], 'share_pct')), 100.0, 0.001));
ok('Sin departamentos duplicados', count($ov['rows']) === count(array_unique(array_column($ov['rows'], 'name'))));
$x = $all['kpis']['stock_units']['extra'];
ok('Disponible + reservado + bloqueado = unidades', $eq($x['available'] + $x['reserved'] + $x['blocked'], $x['total'], 1));
ok('Críticos + preventivos = bajo mínimo', $all['kpis']['below_min']['extra']['critical'] + $all['kpis']['below_min']['extra']['preventive'] === $all['kpis']['below_min']['numeric']);
ok('Bajo mínimo = regla compartida', $all['kpis']['below_min']['numeric'] === $dm->StockStatusCounts(null, 30)['below_min']);
ok('Alertas = tarjetas', $all['alerts']['below_min']['total'] === $all['kpis']['below_min']['numeric'] && $all['alerts']['out_of_stock']['total'] === $all['kpis']['out_of_stock']['numeric'] && $all['alerts']['expiring']['total'] === $all['kpis']['expiring_soon']['numeric']);
ok('Riesgo por bandas = valor en riesgo', $eq(array_sum(array_column($all['kpis']['risk_value']['extra']['bands'], 'value')), $all['kpis']['risk_value']['numeric'], 1));

echo "\n2. Filtro por departamento\n";
$top = null;
foreach ($ov['rows'] as $r)
{
	if ($r['filterable'] && ($top === null || $r['below_min'] > $top['below_min']))
	{
		$top = $r;
	}
}
$dept = $data(['department' => $top['id']]);
ok('Tarjeta de valor = fila del departamento', $eq($dept['kpis']['inventory_value']['numeric'], $top['value'], 0.05));
ok('Bajo mínimo = fila del departamento', $dept['kpis']['below_min']['numeric'] === $top['below_min']);
ok('Ranking conserva todos los departamentos', count($dept['dept_overview']['rows']) === count($ov['rows']));
ok('Departamento marcado', $dept['dept_overview']['selected'] === $top['id']);
ok('Conciliado con filtro', $dept['meta']['reconciliation']['is_reconciled'] === true);
$depts = array_unique(array_column($dept['alerts']['below_min']['rows'], 'dept_key'));
ok('Alertas solo del departamento', $depts === [] || array_values($depts) === [$top['id']]);
$depts = array_unique(array_column($dept['movements']['tabs']['todos'], 'dept_key'));
ok('Movimientos solo del departamento', $depts === [] || array_values($depts) === [$top['id']]);
$oppDepts = [];
foreach ($dept['opportunities'] as $o)
{
	$oppDepts = array_merge($oppDepts, array_column($o['items'], 'dept_key'));
}
ok('Oportunidades solo del departamento', array_diff(array_unique($oppDepts), [$top['id']]) === []);

echo "\n3. Filtro por sucursal\n";
$sum = 0.0;
foreach ($all['branches_table'] as $b)
{
	$sum += $data(['branch_id' => $b['id']])['kpis']['inventory_value']['numeric'];
}
ok('Suma por sucursal = consolidado', $eq($sum, $all['kpis']['inventory_value']['numeric'], 0.1));
$bid = intval($all['branches_table'][0]['id']);
$both = $data(['branch_id' => $bid, 'department' => $top['id'], 'period' => 'week']);
ok('Sucursal, departamento y período se conservan', intval($both['filters']['branch_id']) === $bid && $both['filters']['department'] === $top['id'] && $both['filters']['period'] === 'week');

echo "\n4. Movimientos\n";
$ids = array_column($all['movements']['tabs']['todos'], 'mov_id');
ok('Sin registros duplicados', count($ids) === count(array_unique($ids)));
ok('Sin tramos de tránsito', !array_filter($all['movements']['ledger'], fn($r) => $r['branch_id'] === 0));
foreach (['entradas' => ['entradas'], 'ventas' => ['salidas', 'despachos'], 'traslados' => ['traslados'], 'mermas' => ['mermas'], 'ajustes' => ['ajustes'], 'devoluciones' => ['devoluciones']] as $tab => $keys)
{
	ok("Pestaña $tab solo con su tipo", array_diff(array_unique(array_column($all['movements']['tabs'][$tab], 'category_key')), $keys) === []);
}

echo "\n5. Sincronización con operaciones reales\n";
$pick = $db->query("SELECT p.id, l.branch_id, p.department FROM stock s JOIN products p ON p.id = s.product_id JOIN locations l ON l.id = s.location_id
	WHERE IFNULL(l.is_transit, 0) = 0 AND s.amount >= 5 AND IFNULL(p.is_serialized, 0) = 0 AND p.active = 1 AND TRIM(IFNULL(p.department, '')) <> '' AND s.price > 0 ORDER BY s.amount DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$key = null;
foreach (DailyMetricsService::DEPARTMENTS as $k => $d)
{
	if ($d[2] !== null && in_array($pick['department'], $d[2], true))
	{
		$key = $k;
	}
}
$key = $key ?? 'otros';
$row = function (array $d) use ($key) {
	return array_values(array_filter($d['dept_overview']['rows'], fn($r) => $r['id'] === $key))[0];
};
$before = $data();
ReconciliationService::GetInstance()->RegisterGoodsIn(['product_id' => intval($pick['id']), 'branch_id' => intval($pick['branch_id']), 'expected_qty' => 100, 'received_qty' => 100, 'unit_cost' => 2.5, 'operation_id' => 'test-so-' . uniqid()], GROCY_USER_ID);
$after = $data();
ok('Recepción suma 100 u. al departamento', $eq($row($after)['units'] - $row($before)['units'], 100, 1), $row($after)['units'] - $row($before)['units']);
ok('Recepción suma 100 u. al total', $eq($after['kpis']['stock_units']['numeric'] - $before['kpis']['stock_units']['numeric'], 100, 1));
ok('Sigue conciliado tras recepción', $after['meta']['reconciliation']['is_reconciled'] === true);

$pair = $db->query("SELECT s.stock_id, s.location_id, s.amount, s.product_id, l.branch_id FROM stock s JOIN locations l ON l.id = s.location_id WHERE s.product_id = " . intval($pick['id']) . " AND l.branch_id = " . intval($pick['branch_id']) . " AND IFNULL(l.is_transit, 0) = 0 ORDER BY s.amount DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$w = (array) WasteService::GetInstance()->ReportWaste(intval($pick['id']), 1, 33, intval($pick['branch_id']), intval($pair['location_id']), $pair['stock_id'], 'Prueba de sincronización', null, null, null, null, null, null, 'test-so-w-' . uniqid(), GROCY_USER_ID);
$wid = intval($w['waste_record_id'] ?? $w['id'] ?? 0);
$status = $db->query("SELECT status FROM waste_records WHERE id = $wid")->fetchColumn();
$afterW = $data();
$expected = $status === 'pending_approval' ? 0 : 1;
ok("Merma ($status) ajusta el departamento", $eq($row($after)['units'] - $row($afterW)['units'], $expected, 1));
ok('Sigue conciliado tras merma', $afterW['meta']['reconciliation']['is_reconciled'] === true);

echo "\n6. Permisos\n";
$perms = StockOverviewInsightsService::Permissions();
foreach (['replenish', 'review', 'task', 'kardex', 'investigate', 'lot', 'transfer', 'quality', 'waste', 'export'] as $p)
{
	ok("Permiso $p definido", array_key_exists($p, $perms));
}

echo "
$count verificaciones, $fails fallidas
";
exit($fails > 0 ? 1 : 0);
