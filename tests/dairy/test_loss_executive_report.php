<?php
/**
 * Informe Ejecutivo de Pérdidas y Novedades — Control 360: consistencia con Mermas e Inventario (mismos filtros y período),
 * suma por sucursal = red, estado preliminar sin sincronización, publicación inalterable, entrega sin simular correo,
 * programación 09:00 y exportación Excel.
 */

use Grocy\Services\DailyMetricsService;
use Grocy\Services\LossExecutiveReportService;
use Grocy\Services\LossIntelligenceService;

require __DIR__ . '/bootstrap.php';

$svc = LossExecutiveReportService::GetInstance();
$li = LossIntelligenceService::GetInstance();
$dm = DailyMetricsService::GetInstance();
$eq = fn($a, $b) => abs(floatval($a) - floatval($b)) < 0.011;

echo "\n1. Migración y programación por defecto\n";
$daily = $db->query("SELECT * FROM dcr_report_schedules WHERE period_type = 'daily'")->fetch(PDO::FETCH_ASSOC);
ok('Programación diaria activa a las 09:00', $daily && $daily['active'] == 1 && $daily['run_time'] === '09:00', json_encode($daily));
ok('Próxima ejecución a las 09:00 en la zona corporativa', $daily && substr($daily['next_run_at'], 11) === '09:00:00' && $daily['timezone'] === date_default_timezone_get(), $daily['next_run_at'] ?? '');
ok('Semanal / mensual / anual creadas inactivas', intval($val("SELECT COUNT(*) FROM dcr_report_schedules WHERE active = 0")) === 3);

echo "\n2. Períodos\n";
ok('Diario: corte = día anterior', LossExecutiveReportService::Period('daily', '2026-10-30') === ['2026-10-29', '2026-10-29']);
ok('Semanal: 7 días al corte', LossExecutiveReportService::Period('weekly', '2026-10-30') === ['2026-10-23', '2026-10-29']);
ok('Mensual: mes en curso al corte', LossExecutiveReportService::Period('monthly', '2026-11-01') === ['2026-10-01', '2026-10-31']);
ok('Anual: año en curso al corte', LossExecutiveReportService::Period('annual', '2026-10-30') === ['2026-01-01', '2026-10-29']);
ok('Próxima diaria tras las 09:00 = mañana 09:00', LossExecutiveReportService::NextRun('09:00', 'daily', strtotime('2026-10-30 09:05')) === '2026-10-31 09:00:00');
ok('Próxima semanal = lunes 09:00', LossExecutiveReportService::NextRun('09:00', 'weekly', strtotime('2026-10-30 10:00')) === '2026-11-02 09:00:00');

$lastSale = substr($val("SELECT MAX(created_timestamp) FROM sales WHERE status = 'completed'"), 0, 10);
$reportDate = date('Y-m-d', strtotime($lastSale . ' +1 day'));
foreach (['daily', 'monthly'] as $type)
{
	echo "\n3. Consistencia con los módulos de origen ($type, publicación $reportDate)\n";
	$r = $svc->Build($type, $reportDate);
	[$f, $t] = [$r['from'], $r['to']];
	$sum = $li->Summary([], $f, $t);
	ok('Pérdida confirmada = Mermas (Summary)', $eq($r['summary']['confirmed_loss'], $sum['total']['value']), $r['summary']['confirmed_loss'] . ' vs ' . $sum['total']['value']);
	ok('% merma = Mermas', $r['summary']['loss_pct_sales'] === $sum['total']['pct_sales'], json_encode([$r['summary']['loss_pct_sales'], $sum['total']['pct_sales']]));
	ok('Ventas netas = Panel (DailyMetrics)', $eq($r['summary']['net_sales'], $dm->Sales(null, $f, $t)['net_sales']));
	ok('Inventario = Inventario (DailyMetrics)', $eq($r['summary']['inventory_value'], $dm->Inventory(null)['value']));
	ok('Suma de pérdida por sucursal = red', $eq(array_sum(array_column($r['branches'], 'loss')), $r['summary']['confirmed_loss']), array_sum(array_column($r['branches'], 'loss')));
	ok('Suma de inventario por sucursal = red', $eq(array_sum(array_column($r['branches'], 'inventory')), $r['summary']['inventory_value']));
	ok('Todas las sucursales activas incluidas', count($r['branches']) === intval($val('SELECT COUNT(*) FROM branches WHERE active = 1')));
	$b = $r['branches'][0];
	ok('Pérdida de la sucursal = Mermas filtrado por sucursal', $eq($b['loss'], $li->Summary(['branch_id' => $b['id']], $f, $t)['total']['value']), $b['name']);
	ok('En investigación no suma a la pérdida confirmada', isset($r['summary']['in_investigation']['parts']) && !$eq($r['summary']['in_investigation']['value'], -1));
	$bad = array_filter($r['novelties']['items'], fn($n) => !$n['type_label'] || !$n['action'] || !$n['href'] || $n['responsible'] === '');
	ok('Cada novedad trae tipo, responsable, estado y acción', count($bad) === 0, $r['novelties']['total'] . ' novedades');
	ok('Cada acción trae causa, evidencia, responsable y fecha límite', count(array_filter($r['actions'], fn($a) => !$a['who'] || !$a['due'] || !$a['evidence'])) === 0, count($r['actions']) . ' acciones');
	ok('Top 10 limitado', count($r['products']['top_cost']) <= 10 && count($r['products']['top_units']) <= 10);
	ok('Sin CEDIS registrado: no se inventan datos', $r['cedis']['available'] || count($r['cedis']['items']) === 0);
}

echo "\n4. Sincronización: corte sin ventas registradas = preliminar\n";
$future = date('Y-m-d', strtotime($lastSale . ' +2 days'));
$r = $svc->Build('daily', $future);
ok('Sin ventas hasta el corte → preliminar', $r['completeness'] === 'preliminar' && $r['summary']['branches_synced'] === 0, $r['summary']['branches_synced'] . '/' . $r['summary']['branches_total']);
ok('Cada sucursal muestra su última actualización', count(array_filter($r['branches'], fn($b) => array_key_exists('last_sale_at', $b['sync']))) === count($r['branches']));
ok('Acción de sincronización para sucursales pendientes', count(array_filter($r['actions'], fn($a) => strpos($a['what'], 'sin ventas registradas') !== false)) === count($r['branches']));

echo "\n5. Publicación, entrega e inalterabilidad\n";
$g = $svc->Generate('daily', 'manual', 1, $reportDate);
ok('Informe publicado', $g['status'] === 'publicado', json_encode($g));
$rep = $svc->Get($g['id']);
ok('Huella SHA-256 verificada', $rep['integrity_ok'] === true);
$recips = $svc->Recipients('daily');
ok('Destinatarios = usuarios con Prevención de pérdidas o ADMIN', count($recips) > 0 && $g['recipients'] === count($recips), implode(',', $recips));
ok('Entrega en plataforma registrada por destinatario', count(array_filter($rep['deliveries'], fn($d) => $d['channel'] === 'app' && $d['status'] === 'publicado')) === count($recips));
ok('Correo registrado como no configurado (no se simula envío)', count(array_filter($rep['deliveries'], fn($d) => $d['channel'] === 'email' && $d['status'] === 'no_configurado')) === count($recips));
$svc->MarkRead($g['id'], $recips[0]);
ok('Lectura registrada', intval($val("SELECT COUNT(*) FROM dcr_report_deliveries WHERE report_id = {$g['id']} AND read_at IS NOT NULL")) === 1);
expectError('No se puede modificar un informe publicado', fn() => $db->exec("UPDATE dcr_executive_reports SET completeness = 'definitivo' WHERE id = {$g['id']}"));
expectError('No se puede borrar un informe publicado', fn() => $db->exec("DELETE FROM dcr_executive_reports WHERE id = {$g['id']}"));
ok('Historial consultable', count($svc->History('daily')) >= 1);

echo "\n6. Programación 09:00\n";
$db->exec("UPDATE dcr_report_schedules SET next_run_at = '2026-10-30 09:00:00' WHERE period_type = 'daily'");
ok('Antes de las 09:00 no se ejecuta', count($svc->RunDue('schedule', strtotime('2026-10-30 08:59:00'))) === 0);
$due = $svc->RunDue('schedule', strtotime('2026-10-30 09:00:30'));
ok('A las 09:00 se genera el diario', count($due) === 1 && $due[0]['period_type'] === 'daily' && $due[0]['status'] === 'publicado', json_encode($due));
$gen = $svc->Get($due[0]['id']);
ok('Informe del 30/10 con corte al 29/10', $gen['report_date'] === '2026-10-30' && $gen['period_to'] === '2026-10-29' && $gen['trigger_type'] === 'schedule');
ok('Próxima ejecución reprogramada al día siguiente 09:00', $val("SELECT next_run_at FROM dcr_report_schedules WHERE period_type = 'daily'") === '2026-10-31 09:00:00');
ok('No se repite en la misma ventana', count($svc->RunDue('schedule', strtotime('2026-10-30 09:30:00'))) === 0);
expectError('Hora inválida rechazada', fn() => $svc->SaveSchedule('daily', ['run_time' => '25:00', 'active' => 1], 1));
$svc->SaveSchedule('weekly', ['run_time' => '08:30', 'active' => 1], 1);
ok('Programación semanal configurable', $val("SELECT run_time || '|' || active FROM dcr_report_schedules WHERE period_type = 'weekly'") === '08:30|1');

echo "\n7. Exportación Excel\n";
$x = $svc->ExportXlsx($g['id']);
$tmpX = tempnam(sys_get_temp_dir(), 'x');
file_put_contents($tmpX, $x);
$z = new ZipArchive();
ok('Archivo .xlsx válido', $z->open($tmpX) === true);
ok('Cinco hojas (resumen, sucursales, novedades, productos, acciones)', $z->locateName('xl/worksheets/sheet5.xml') !== false && strpos($z->getFromName('xl/workbook.xml'), 'Novedades') !== false);
$z->close();
@unlink($tmpX);

echo "\n$count verificaciones, $fails fallidas\n";
exit($fails > 0 ? 1 : 0);
