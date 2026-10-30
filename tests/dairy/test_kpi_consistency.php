<?php
/**
 * Coherencia de KPI de ventas entre pantallas: mismo período y alcance → mismo margen.
 *   php tests/dairy/test_kpi_consistency.php [ruta/a/grocy.db]
 * Fuente común: SalesService::MarginPct sobre venta neta de IVA (sales.gross_margin = total − IVA − costo).
 */

use Grocy\Services\BranchService;
use Grocy\Services\CostCenterService;
use Grocy\Services\KpiService;
use Grocy\Services\SalesService;

require __DIR__ . '/bootstrap.php';

$month = $val("SELECT strftime('%Y-%m', created_timestamp) FROM sales WHERE status = 'completed' GROUP BY 1 ORDER BY COUNT(*) DESC LIMIT 1");
if (!$month)
{
	echo "  --   sin ventas completadas (omitido)\n";
	exit(0);
}
$start = $month . '-01';
$end = date('Y-m-t', strtotime($start));
echo "\nPeríodo $start .. $end\n";

$sql = fn(string $where) => $db->query("SELECT IFNULL(SUM(total_amount), 0) AS t, IFNULL(SUM(tax_amount), 0) AS x, IFNULL(SUM(gross_margin), 0) AS m FROM sales WHERE status = 'completed' AND date(created_timestamp) BETWEEN '$start' AND '$end' $where")->fetch(PDO::FETCH_OBJ);
$near = fn($a, $b, $tol = 0.11) => abs(floatval($a) - floatval($b)) <= $tol;

// Red completa
$ref = $sql('');
$refPct = SalesService::MarginPct($ref->m, $ref->t - $ref->x) ?? 0;
$kpi = KpiService::GetInstance()->GetExecutiveDashboardData(null, 'custom', $start, $end);
ok('KPI ejecutivo: margen % sobre venta neta de IVA', $near($kpi['margin_percentage'], SalesService::MarginPct($ref->m, $ref->t - $ref->x, 2) ?? 0, 0.011), "kpi {$kpi['margin_percentage']} · ref $refPct");

// Vistas por línea (producto, categoría): deben coincidir entre sí y, si las líneas cuadran con la cabecera, con el KPI
$lineSql = fn(string $where) => $db->query('SELECT IFNULL(SUM(' . SalesService::SQL_ITEM_NET_SALES . "), 0) - IFNULL(SUM(si.total_cost), 0) FROM sale_items si JOIN sales s ON s.id = si.sale_id WHERE s.status = 'completed' AND date(s.created_timestamp) BETWEEN '$start' AND '$end' $where")->fetchColumn();
$lineMargin = floatval($lineSql(''));
$profit = array_sum(array_column($kpi['deep_products'], 'gross_profit'));
ok('Rentabilidad por producto incluye todas las líneas vendidas', $near($profit, $lineMargin, 1.0), round($profit, 2) . ' vs ' . round($lineMargin, 2));

$cc = CostCenterService::GetInstance()->GetReport($month);
ok('Centro de costos: margen = rentabilidad por producto', $near($cc['total']['margin'], $profit, 1.0), $cc['total']['margin'] . ' vs ' . round($profit, 2));

$bad = intval($val("SELECT COUNT(*) FROM sales s WHERE s.status = 'completed' AND date(s.created_timestamp) BETWEEN '$start' AND '$end' AND (ABS(IFNULL((SELECT SUM(subtotal) FROM sale_items WHERE sale_id = s.id), 0) - s.total_amount) > 0.05 OR ABS(IFNULL((SELECT SUM(total_cost) FROM sale_items WHERE sale_id = s.id), 0) - s.total_cogs) > 0.05)"));
if ($bad === 0)
{
	ok('Centro de costos: margen total = Σ sales.gross_margin', $near($cc['total']['margin'], $ref->m, 1.0), $cc['total']['margin'] . ' vs ' . round($ref->m, 2));
	ok('Centro de costos: margen % = KPI ejecutivo', $near($cc['total']['margin_pct'], $refPct), "{$cc['total']['margin_pct']} vs $refPct");
}
else
{
	echo "  AVISO $bad ventas con líneas que no cuadran con su cabecera (total/costo): las vistas por línea difieren del KPI por cabecera\n";
}

// Por sucursal: ranking del KPI, scorecard de sucursales y centro de costos
$scores = [];
foreach (BranchService::GetInstance()->GetBranchScorecards($start, $end) as $s)
{
	$scores[intval($s['id'])] = $s;
}
$ccBranch = array_column($cc['branches'], null, 'id');
foreach ($kpi['branch_ranking'] as $r)
{
	$bid = intval($r['branch_id'] ?? $r['id'] ?? 0);
	$b = $sql("AND branch_id = $bid");
	if ($b->t <= 0)
	{
		continue;
	}
	$pct = SalesService::MarginPct($b->m, $b->t - $b->x) ?? 0;
	ok("Sucursal #$bid: ranking KPI = referencia", $near($r['margin_pct'], $pct), "{$r['margin_pct']} vs $pct");
	if (isset($scores[$bid]))
	{
		ok("Sucursal #$bid: scorecard = ranking KPI", $near($scores[$bid]['margin_pct'], $r['margin_pct']), "{$scores[$bid]['margin_pct']} vs {$r['margin_pct']}");
	}
	if (isset($ccBranch[$bid]))
	{
		$lm = floatval($lineSql("AND s.branch_id = $bid"));
		ok("Sucursal #$bid: centro de costos = margen por líneas", $near($ccBranch[$bid]['margin'], $lm, 1.0), "{$ccBranch[$bid]['margin']} vs " . round($lm, 2));
	}
}

// Costo faltante ≠ costo cero: debe quedar visible
$missing = intval($val("SELECT COUNT(*) FROM sale_items si JOIN sales s ON s.id = si.sale_id WHERE s.status = 'completed' AND IFNULL(si.unit_cost, 0) = 0 AND date(s.created_timestamp) BETWEEN '$start' AND '$end'"));
ok('Líneas sin costo expuestas en rentabilidad', array_sum(array_column($kpi['deep_products'], 'lines_without_cost')) === $missing, "$missing líneas");

echo "\n$count verificaciones, $fails fallidas\n";
exit($fails > 0 ? 1 : 0);
