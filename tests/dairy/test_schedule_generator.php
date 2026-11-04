<?php
/**
 * Generador con personal real: importa la historia, continúa el ciclo 3×1 individual, el bloque nocturno en curso y la rotación,
 * bloquea lo que debe bloquear y exporta un XLSX válido. Trabaja sobre una copia de la base.
 */

use Grocy\Services\WorkforcePlanillaImportService;
use Grocy\Services\WorkforceScheduleEngineService;
use Grocy\Services\WorkforceScheduleXlsxService;

require __DIR__ . '/bootstrap.php';

$en = WorkforceScheduleEngineService::GetInstance();
$uid = GROCY_USER_ID;

// Historia mínima sintética: 4 domingos con libre el 18/10 para Delgado y bloque nocturno en curso para Mora
$days = [];
for ($d = '2026-09-28'; $d <= '2026-11-01'; $d = gmdate('Y-m-d', strtotime("$d 12:00 UTC") + 86400))
{
	$days[] = $d;
}
$schedule = [];
$sundays = ['2026-10-04', '2026-10-11', '2026-10-18', '2026-10-25'];
foreach ($db->query('SELECT payroll_code FROM workforce_planilla_collaborators WHERE branch_id = 1 ORDER BY payroll_code')->fetchAll(PDO::FETCH_COLUMN) as $i => $code)
{
	$free = ['38041537' => '2026-10-18', 'TEMP2' => '2026-10-25', '38031757' => '2026-10-04'][$code] ?? $sundays[$i % 4];
	foreach ($days as $n => $d)
	{
		$schedule[$code][$d] = $d === $free ? 'LBRE' : (date('N', strtotime($d)) === '1' ? 'COMP' : ((intdiv($n, 7) + $i) % 2 === 0 ? '7A7-14' : '7C14-21'));
	}
}
foreach (['2026-10-29', '2026-10-30', '2026-10-31', '2026-11-01'] as $d)
{
	$schedule['TEMP2'][$d] = '7N22-6';
}
$schedule['38035662'] = array_fill_keys($days, 'INC');
$schedule['38031757']['2026-11-01'] = 'VC';
$imp = WorkforcePlanillaImportService::GetInstance()->Import(['schedule' => $schedule, 'sap' => []], $uid);
ok('Importa sin códigos desconocidos', !$imp['unknown_codes'] && !$imp['unknown_people'], json_encode($imp['unknown_codes']));
ok('Incapacidad vigente queda abierta', count($imp['open_sick_leaves']) === 1);
ok('Grupos de la hoja dominical', $val("SELECT sunday_group FROM workforce_planilla_collaborators WHERE payroll_code = '38053738'") === 'B');
ok('Nombre completo con tildes', $val("SELECT full_name FROM workforce_planilla_collaborators WHERE payroll_code = '38035376'") === 'Beyoncé Knowles');
ok('Quien ha trasnochado queda habilitado', intval($val("SELECT can_night FROM workforce_skills_matrix WHERE payroll_code = 'TEMP2'")) === 1);

$d = $en->BuildDraft(1, 2026, 11, $uid);
$cell = fn($code, $date) => $val("SELECT shift_code FROM workforce_schedule_draft_rows WHERE draft_id = {$d['draft_id']} AND payroll_code = '$code' AND shift_date = '$date'");
ok('Noviembre tiene 30 días pese al cambio de hora', intval($val("SELECT COUNT(DISTINCT shift_date) FROM workforce_schedule_draft_rows WHERE draft_id = {$d['draft_id']}")) === 30);
ok('El 30 de noviembre existe y no hay fechas repetidas', $cell('38041537', '2026-11-30') !== false);
ok('El 1 de noviembre conserva la planilla', $cell('TEMP2', '2026-11-01') === '7N22-6');
ok('3×1: libre 18/10 → 15/11', $cell('38041537', '2026-11-15') === 'LIBRE', $cell('38041537', '2026-11-15'));
ok('3×1: trabaja el 8/11', $cell('38041537', '2026-11-08') !== 'LIBRE');
ok('Bloque nocturno continúa hasta el 4/11', $cell('TEMP2', '2026-11-04') === '7N22-6', $cell('TEMP2', '2026-11-04'));
ok('Tras el bloque no hay turno el 5/11', in_array($cell('TEMP2', '2026-11-05'), ['COMP', 'LIBRE'], true), $cell('TEMP2', '2026-11-05'));
ok('Vacaciones respetadas', $cell('38039919', '2026-11-10') === 'VC' && $cell('38063818', '2026-11-20') === 'VC');
ok('Ciclo sigue dentro de vacaciones: el libre del 1/11 cayó en VC, tocaba el 29/11 y esa fecha lo adelanta', $cell('38031757', '2026-11-29') !== 'LIBRE' && in_array('LIBRE', [$cell('38031757', '2026-11-22'), $cell('38031757', '2026-11-15'), $cell('38031757', '2026-11-08')], true));
ok('Sin turnos durante la incapacidad abierta', $cell('38035662', '2026-11-20') === 'INC');
ok('Sin incumplimientos obligatorios', $d['report']['counts']['legal'] === 0, json_encode(array_slice($d['report']['issues'], 0, 3), JSON_UNESCAPED_UNICODE));
ok('Máximo una noche por día', ($d['report']['by_type']['night_cap'] ?? 0) === 0);

$conf = array_column($en->Confirmations(1, 2026, 11), 'type');
ok('Pide confirmar incapacidad sin regreso y códigos temporales', in_array('open_absence', $conf, true) && in_array('temporary_codes', $conf, true), implode(',', $conf));

$items = $en->Confirmations(1, 2026, 11);
$open = array_values(array_filter($items, fn($c) => $c['type'] === 'open_absence'))[0];
ok('La ausencia abierta trae lo que necesita la sección', !empty($open['absence_id']) && !empty($open['name']) && !empty($open['start_date']));
$temp = array_values(array_filter($items, fn($c) => $c['type'] === 'temporary_codes'))[0];
ok('Los códigos temporales traen a cada persona', count($temp['people']) === 2 && !empty($temp['people'][0]['name']));
foreach (array_filter($items, fn($c) => $c['type'] === 'sunday_sheet_diff') as $s)
{
	$p = $s['people'][0];
	ok('La hoja y el ciclo real difieren en cada fila', $p['sheet_free'] !== $p['real_free'] && date('w', strtotime($p['sheet_free'])) === '0', json_encode($p));
}
$asked = $db->query("SELECT e.id FROM employees e WHERE e.payroll_code = '38053744'")->fetchColumn();
$db->exec("INSERT INTO employee_absences (employee_id, absence_type, start_date, end_date, status) VALUES ($asked, 'vacation', '2026-11-10', '2026-11-11', 'requested')");
$askedId = intval($db->lastInsertId());
$found = array_values(array_filter($en->Confirmations(1, 2026, 11), fn($c) => ($c['absence_id'] ?? 0) === $askedId));
ok('La solicitud aparece con su id para decidirla', count($found) === 1 && $found[0]['status'] === 'requested');
\Grocy\Services\WorkforceScheduleService::GetInstance()->DecideAbsence($askedId, false, 999);
ok('Una vez decidida sale de la lista', !array_filter($en->Confirmations(1, 2026, 11), fn($c) => ($c['absence_id'] ?? 0) === $askedId));

$before = $val("SELECT shift_code FROM workforce_schedule_draft_rows WHERE draft_id = {$d['draft_id']} AND payroll_code = '38041537' AND shift_date = '2026-11-10'");
$en->UpdateDraftCell($d['draft_id'], '38041537', '2026-11-10', 'LIBRE', $uid);
ok('Edición manual queda en bitácora', intval($val("SELECT COUNT(*) FROM workforce_schedule_draft_log WHERE draft_id = {$d['draft_id']} AND action = 'edited'")) === 1);
expectError('Código fuera de Turnos SAP', fn() => $en->UpdateDraftCell($d['draft_id'], '38041537', '2026-11-10', 'XX', $uid));
$en->UpdateDraftCell($d['draft_id'], '38041537', '2026-11-10', $before, $uid);

echo "\n== Bloqueos de aprobación y exportación ==\n";
expectError('Mes publicado exige reabrir', fn() => $en->Approve($d['draft_id'], $uid, true, true));
$en->Reopen(1, 2026, 11, 'Reemplazo por el generador con personal real', $uid);
expectError('Sin aceptar confirmaciones no aprueba', fn() => $en->Approve($d['draft_id'], $uid, true, false));

$x = new ZipArchive();
$file = tempnam(sys_get_temp_dir(), 'gen');
file_put_contents($file, WorkforceScheduleXlsxService::GetInstance()->Export(1, 2026, 11));
ok('XLSX abre como paquete', $x->open($file) === true);
ok('Nueve hojas', $x->numFiles === 14, $x->numFiles);
foreach (range(1, 9) as $n)
{
	ok("Hoja $n es XML válido", simplexml_load_string($x->getFromName("xl/worksheets/sheet$n.xml")) !== false);
}
ok('Horas por fórmula sobre Turnos SAP', str_contains($x->getFromName('xl/worksheets/sheet1.xml'), 'SUMPRODUCT(SUMIF('));
ok('Nombre sugerido', WorkforceScheduleXlsxService::FileName(2026, 11) === 'PROGRAMACION_DAILY_NOVIEMBRE_2026.xlsx');
$x->close();
@unlink($file);

$legalDraft = $en->UpdateDraftCell($d['draft_id'], '38041537', '2026-11-12', '7A7-14', $uid);
$en->UpdateDraftCell($d['draft_id'], '38063818', '2026-11-10', '7A7-14', $uid);
expectError('No descarga con restricción obligatoria incumplida', fn() => WorkforceScheduleXlsxService::GetInstance()->Export(1, 2026, 11));
expectError('No aprueba con restricción obligatoria incumplida', fn() => $en->Approve($d['draft_id'], $uid, true, true));
$en->UpdateDraftCell($d['draft_id'], '38063818', '2026-11-10', 'VC', $uid);

echo "\n== Continuidad entre meses ==\n";
$en->Approve($d['draft_id'], $uid, true, true);
$dec = $en->BuildDraft(1, 2026, 12, $uid);
ok('Diciembre sin incumplimientos obligatorios', $dec['report']['counts']['legal'] === 0, json_encode($dec['report']['by_type']));
$freeNov = $val("SELECT MAX(shift_date) FROM workforce_monthly_schedules WHERE payroll_code = '38041537' AND is_sunday = 1 AND shift_type = 'LIBRE' AND shift_date LIKE '2026-11%'");
ok('Último libre de noviembre es el 15', $freeNov === '2026-11-15', $freeNov);
ok('Ciclo 3×1 no se reinicia: siguiente libre 13/12', $val("SELECT shift_code FROM workforce_schedule_draft_rows WHERE draft_id = {$dec['draft_id']} AND payroll_code = '38041537' AND shift_date = '2026-12-13'") === 'LIBRE');
$en->Reopen(1, 2026, 12, 'Reemplazo por el generador con personal real', $uid);
$en->Approve($dec['draft_id'], $uid, true, true);
$jan = $en->BuildDraft(1, 2027, 1, $uid);
ok('Enero sin incumplimientos obligatorios', $jan['report']['counts']['legal'] === 0, json_encode(array_slice($jan['report']['issues'], 0, 4), JSON_UNESCAPED_UNICODE));
ok('Un domingo no deja dos compensatorios pendientes', intval($val("SELECT COUNT(*) FROM (SELECT 1 FROM workforce_compensatory_bank WHERE status = 'PENDING' GROUP BY payroll_code, origin_sunday HAVING COUNT(*) > 1)")) === 0);

echo "\n$count pruebas, $fails fallos\n";
exit($fails > 0 ? 1 : 0);
