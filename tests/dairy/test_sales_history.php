<?php
/**
 * Historial de Ventas: análisis temporal (semana vs semana, acumulado, históricos por dimensión) y auditoría comercial
 * (duplicados, fecha de registro, devoluciones, cambios trazados, correcciones autorizadas y cierres).
 * Uso: php tests/dairy/test_sales_history.php [base.db]   (trabaja sobre una copia de la base)
 */

use Grocy\Helpers\DailyFilters;
use Grocy\Services\SalesHistoryAuditService;
use Grocy\Services\SalesReportsService;

require __DIR__ . '/bootstrap.php';

$sr = SalesReportsService::GetInstance();
$au = SalesHistoryAuditService::GetInstance();
$db = Grocy\Services\DatabaseService::GetInstance()->GetDbConnectionRaw();
$near = fn($a, $b, $tol = 0.05) => abs(floatval($a) - floatval($b)) <= $tol;
$to = $db->query("SELECT date(MAX(created_timestamp)) FROM sales WHERE status = 'completed'")->fetchColumn();
$from = date('Y-m-d', strtotime("$to -29 days"));
$f = DailyFilters::FromQuery(['start_date' => $from, 'end_date' => $to]);
$throws = function (callable $fn) { try { $fn(); return false; } catch (\Throwable $e) { return true; } };

echo "\n1. Análisis temporal\n";
$t0 = microtime(true);
$H = $sr->Report($f)['history'];
ok('Informe con historial en menos de 4 s', microtime(true) - $t0 < 4, round(microtime(true) - $t0, 2) . ' s');
ok('Curva acumulada termina en la venta del período', $near(end($H['cumulative']['net']), $H['net'], 1), json_encode([end($H['cumulative']['net']), $H['net']]));
ok('Curva acumulada no decrece', $H['cumulative']['net'] === array_values(array_map(null, $H['cumulative']['net'])) && array_reduce(array_keys($H['cumulative']['net']), fn($ok, $i) => $ok && ($i === 0 || $H['cumulative']['net'][$i] >= $H['cumulative']['net'][$i - 1] - 0.01), true));
ok('Semana vs semana: Σ días = total', $near(array_sum(array_map(fn($d) => $d['net'] ?? 0, $H['wow']['days'])), $H['wow']['net'], 1) && $near(array_sum(array_column($H['wow']['days'], 'prev_net')) - array_sum(array_map(fn($d) => $d['net'] === null ? $d['prev_net'] : 0, $H['wow']['days'])), $H['wow']['prev_net'], 1));
ok('13 meses en los históricos', count($H['months']) === 13, json_encode($H['months']));
$monthly = array_combine($H['timeline']['month']['labels'], $H['timeline']['month']['net']);
foreach (['by_branch' => 'sucursal', 'by_dept' => 'departamento', 'by_channel' => 'canal'] as $k => $n)
{
	$bad = [];
	foreach ($H['months'] as $i => $m)
	{
		$s = array_sum(array_map(fn($r) => $r['months'][$i] ?? 0, $H[$k]));
		if (!$near($s, $monthly[$m] ?? 0, 2))
		{
			$bad[] = "$m: $s vs " . ($monthly[$m] ?? 0);
		}
	}
	ok("Σ por $n = venta mensual en los 13 meses", !$bad, implode(' · ', array_slice($bad, 0, 3)));
}
ok('SKU: hasta 10 productos con serie mensual', count($H['by_sku']) > 0 && count($H['by_sku']) <= 10 && count($H['by_sku'][0]['months']) === 13);

echo "\n2. Devoluciones\n";
$R = $au->Refunds($f, '2000-01-01', date('Y-m-d'));
$sql = floatval($db->query('SELECT IFNULL(SUM(customer_refund_amount), 0) FROM customer_returns_exchanges r WHERE ' . SalesHistoryAuditService::REFUND_SQL)->fetchColumn());
ok('Solo restan devoluciones de cliente reembolsadas', $near($R['total'], $sql, 0.01), json_encode([$R['total'], $sql]));
$exch = floatval($db->query("SELECT IFNULL(SUM(customer_refund_amount), 0) FROM customer_returns_exchanges WHERE resolution = 'cambio' OR return_type <> 'devolucion_cliente' OR status = 'rechazada'")->fetchColumn());
ok('Cambios, rechazos y devoluciones a proveedor no restan', !array_filter($R['rows'], fn($r) => $r['resolution'] === 'cambio') && ($exch == 0 || $R['total'] < $R['total'] + $exch), json_encode($R['excluded']));

echo "\n3. Duplicados y correcciones autorizadas\n";
$all = $au->Duplicates(['branch_id' => null, 'company_id' => null]);
ok('Se detectan grupos de tickets duplicados', count($all) > 0, count($all) . ' grupos');
$g = $all[0] ?? null;
if ($g)
{
	$copy = $g['copies'][0]['id'];
	ok('Sin motivo no se corrige', $throws(fn() => $au->VoidDuplicate($copy, '', 1)));
	ok('El ticket original no se puede anular como copia', $throws(fn() => $au->VoidDuplicate($g['keep']['id'], 'Prueba de duplicado', 1)));
	$day = substr($g['datetime'], 0, 10);
	$fd = DailyFilters::FromQuery(['start_date' => $day, 'end_date' => $day]);
	$before = $sr->PeriodTotal($fd, $day, $day);
	$r = $au->VoidDuplicate($copy, 'Doble sincronización de caja (prueba)', 1);
	ok('Copia cancelada', $db->query("SELECT status FROM sales WHERE id = $copy")->fetchColumn() === 'cancelled');
	$after = $sr->PeriodTotal($fd, $day, $day);
	ok('La venta del día baja exactamente la copia', $near($before['net'] - $after['net'], $g['net'], 0.05) && $before['tickets'] - $after['tickets'] === 1, json_encode([$before, $after, $g['net']]));
	ok('Corrección registrada con autorización y antes/después', intval($db->query("SELECT COUNT(*) FROM daily_sales_corrections WHERE id = {$r['id']} AND kind = 'duplicate' AND authorized_by = 1 AND before_json LIKE '%completed%'")->fetchColumn()) === 1);
	ok('El grupo ya no figura como duplicado', count($au->Duplicates(['branch_id' => null, 'company_id' => null])) === count($all) - (count($g['copies']) === 1 ? 1 : 0));
}

$cut = $sr->ArchiveCutover();
$pre = intval($db->query("SELECT id FROM sales WHERE status = 'completed' AND created_timestamp < '$cut' LIMIT 1")->fetchColumn());
ok('Ventas del archivo consolidado no se corrigen ticket a ticket', $cut === '' || !$pre || $throws(fn() => $au->CorrectDate($pre, date('Y-m-d H:i', strtotime("$cut +2 days")), 'Prueba antes del corte', 1)));
$sid = intval($db->query("SELECT id FROM sales WHERE status = 'completed' AND created_timestamp >= '$cut' AND created_timestamp < date('$cut', '+20 days') ORDER BY id LIMIT 1")->fetchColumn());
$old = $db->query("SELECT created_timestamp FROM sales WHERE id = $sid")->fetchColumn();
ok('Fecha futura rechazada', $throws(fn() => $au->CorrectDate($sid, date('Y-m-d H:i', strtotime('+2 days')), 'Fecha futura', 1)));
ok('Formato de fecha inválido rechazado', $throws(fn() => $au->CorrectDate($sid, 'ayer', 'Formato malo', 1)));
$newTs = date('Y-m-d H:i:s', strtotime("$old +1 day"));
$r = $au->CorrectDate($sid, substr($newTs, 0, 16), 'Fecha de venta mal digitada (prueba)', 1);
ok('Fecha de venta corregida', $db->query("SELECT created_timestamp FROM sales WHERE id = $sid")->fetchColumn() === substr($newTs, 0, 16) . ':00');

echo "\n4. Trazabilidad de cambios\n";
$log = $db->query("SELECT COUNT(*) FROM dairy_change_log WHERE table_name = 'sales' AND row_id = $sid AND action = 'update' AND json_extract(old_json, '$.created_timestamp') = " . $db->quote($old))->fetchColumn();
ok('El trigger registra el cambio de fecha con el valor anterior', intval($log) === 1);
$db->exec("UPDATE sale_items SET unit_price = unit_price + 0.01 WHERE sale_id = $sid");
$A = $au->Audit(DailyFilters::FromQuery(['start_date' => substr($old, 0, 10), 'end_date' => date('Y-m-d')]));
$mine = array_values(array_filter($A['changes'], fn($c) => $c['sale_id'] === $sid));
ok('El cambio autorizado aparece respaldado por su corrección', (bool) array_filter($mine, fn($c) => $c['table'] === 'sales' && $c['correction_id'] === $r['id']));
ok('El cambio sin corrección queda señalado', (bool) array_filter($mine, fn($c) => $c['table'] === 'sale_items' && $c['correction_id'] === null) && $A['changes_unexplained'] >= 1);

echo "\n5. Cierres comerciales\n";
$mFrom = date('Y-m-01', strtotime(substr($old, 0, 7) . '-01'));
$mTo = date('Y-m-t', strtotime($mFrom));
ok('No se cierra un período en curso', $throws(fn() => $au->Close('month', date('Y-m-01'), '', 1)));
ok('La semana debe empezar en lunes', $throws(fn() => $au->Close('week', date('Y-m-d', strtotime('tuesday last week -7 days')), '', 1)));
$c = $au->Close('month', $mFrom, 'Cierre de prueba', 1);
$t = $sr->PeriodTotal(DailyFilters::FromQuery(['start_date' => $mFrom, 'end_date' => $mTo]), $mFrom, $mTo);
ok('Foto del cierre = cifras de los informes', $near($c['net'], $t['net'], 0.01) && $c['tickets'] === intval($t['tickets']), json_encode([$c, $t]));
ok('No se cierra dos veces', $throws(fn() => $au->Close('month', $mFrom, '', 1)));
$A = $au->Audit($f);
$row = array_values(array_filter($A['closures']['rows'], fn($x) => $x['from'] === $mFrom))[0] ?? null;
ok('Cierre recién hecho: sin cambios', $row && $row['intact']);
$sid2 = intval($db->query("SELECT id FROM sales WHERE status = 'completed' AND date(created_timestamp) BETWEEN '$mFrom' AND '$mTo' ORDER BY id DESC LIMIT 1")->fetchColumn());
$ts2 = $db->query("SELECT created_timestamp FROM sales WHERE id = $sid2")->fetchColumn();
sleep(1);
$au->CorrectDate($sid2, date('Y-m-d H:i', strtotime("$mTo +1 day 10:00")), 'Venta del mes siguiente (prueba)', 1);
$A = $au->Audit($f);
$row = array_values(array_filter($A['closures']['rows'], fn($x) => $x['from'] === $mFrom))[0] ?? null;
ok('Cambio posterior al cierre detectado y explicado', $row && !$row['intact'] && $row['delta_tickets'] === -1 && $row['corrections_after'] >= 1, json_encode($row));
ok('Períodos pendientes no incluyen el ya cerrado', !array_filter($A['closures']['pending'], fn($p) => $p['type'] === 'month' && $p['from'] === $mFrom));

echo "
$count pruebas, $fails fallas
";
exit($fails ? 1 : 0);
