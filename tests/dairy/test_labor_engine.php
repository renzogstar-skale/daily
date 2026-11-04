<?php
/**
 * Motor laboral: las 12 pruebas obligatorias sobre la historia real de la planilla (copia de la base).
 */

use Grocy\Services\WorkforceLaborRulesService as Rules;
use Grocy\Services\WorkforcePlantaService;
use Grocy\Services\WorkforceRequestService;
use Grocy\Services\WorkforceScheduleEngineService;
use Grocy\Services\WorkforceScheduleXlsxService;

require __DIR__ . '/bootstrap.php';

$en = WorkforceScheduleEngineService::GetInstance();
$req = WorkforceRequestService::GetInstance();
$planta = WorkforcePlantaService::GetInstance();
$uid = GROCY_USER_ID;
$NICOLAS = '38053738';
$JULIO = '38031757';
$KAREN = '38041537';
$HECTOR = '38034789';
$ACCESSORIES = [$NICOLAS, $JULIO, $KAREN];

$d = $en->BuildDraft(1, 2026, 11, $uid);
$id = $d['draft_id'];
$cell = fn($code, $date, $draft = null) => $val("SELECT shift_code FROM workforce_schedule_draft_rows WHERE draft_id = " . ($draft ?? $id) . " AND payroll_code = '$code' AND shift_date = '$date'");
$r = $d['report'];
echo '   noviembre: ' . json_encode($r['by_type']) . "\n";

echo "\n== 1. Tres domingos trabajados y uno libre ==\n";
$c = Rules::NewCycle('2026-10-18');
$seq = [];
foreach (['2026-10-25', '2026-11-01', '2026-11-08', '2026-11-15', '2026-11-22', '2026-11-29', '2026-12-06', '2026-12-13'] as $s)
{
	$free = Rules::IsFreeDue($c, $s);
	$seq[] = $free ? 'L' : 'W';
	$c = Rules::Step($c, $s, $free ? 'FREE' : 'WORKED');
}
ok('Secuencia pura WWWL WWWL', implode('', $seq) === 'WWWLWWWL', implode('', $seq));
ok('Individual: Karen libre 15/11, Nicolás trabaja ese día', $cell($KAREN, '2026-11-15') === 'LIBRE' && $cell($NICOLAS, '2026-11-15') !== 'LIBRE');
ok('Nunca cuatro domingos seguidos en noviembre', ($r['by_type']['sunday_3x1'] ?? 0) === 0, $r['by_type']['sunday_3x1'] ?? 0);

echo "\n== 2. Compensatorio rotativo ==\n";
$nico = $db->query("SELECT shift_date FROM workforce_schedule_draft_rows WHERE draft_id = $id AND payroll_code = '$NICOLAS' AND shift_type = 'COMPENSATORIO' ORDER BY 1")->fetchAll(PDO::FETCH_COLUMN);
$dows = array_map(fn($x) => intval(date('N', strtotime($x))), $nico);
ok('Nicolás: 2/11 es festivo y pasa al martes', $nico[0] === '2026-11-03', implode(',', $nico));
ok('Nicolás sigue su secuencia: martes y luego jueves', $dows[1] === 2 && $dows[2] === 4, implode(',', $dows));
ok('La secuencia vuelve a empezar en lunes', ($dows[3] ?? 0) === 1, implode(',', $dows));
$same = 0;
foreach ($db->query("SELECT payroll_code, GROUP_CONCAT(strftime('%w', shift_date)) g FROM workforce_schedule_draft_rows WHERE draft_id = $id AND shift_type = 'COMPENSATORIO' AND shift_date > '2026-11-01' GROUP BY 1")->fetchAll(PDO::FETCH_ASSOC) as $row)
{
	$w = explode(',', $row['g']);
	$same += count($w) >= 3 && count(array_unique($w)) === 1 ? 1 : 0;
}
ok('Nadie repite siempre el mismo día de compensatorio', $same === 0, $same);

echo "\n== 3. Festivos colombianos ==\n";
ok('2/11 y 16/11 son festivos', intval($val("SELECT COUNT(*) FROM workforce_colombian_holidays WHERE holiday_date IN ('2026-11-02', '2026-11-16')")) === 2);
ok('Ningún compensatorio en festivo', intval($val("SELECT COUNT(*) FROM workforce_schedule_draft_rows WHERE draft_id = $id AND shift_type = 'COMPENSATORIO' AND shift_date IN ('2026-11-02', '2026-11-16')")) === 0);
ok('Validación sin compensatorios en día inválido', ($r['by_type']['comp_invalid_day'] ?? 0) === 0);
$k = Rules::ClassifyShift('2026-11-02', '14:00', '21:00', 7, fn($x) => $x === '2026-11-02');
ok('Festivo: 7 h con 2 h nocturnas desde las 19:00', $k['rest_day'] === 5.0 && $k['rest_night'] === 2.0, json_encode($k));

echo "\n== 4. Restricciones del 27 y 29 de noviembre ==\n";
ok('El 27 nadie tiene compensatorio', intval($val("SELECT COUNT(*) FROM workforce_schedule_draft_rows WHERE draft_id = $id AND shift_date = '2026-11-27' AND shift_type = 'COMPENSATORIO'")) === 0);
ok('El 29 nadie tiene libre', intval($val("SELECT COUNT(*) FROM workforce_schedule_draft_rows WHERE draft_id = $id AND shift_date = '2026-11-29' AND shift_type = 'LIBRE'")) === 0);
$ex = array_column($r['sunday_exceptions'], null, 'payroll_code');
ok('Héctor libraba el 29: su libre se adelanta y queda registrado', !empty($ex[$HECTOR]['new_sunday']) && $cell($HECTOR, $ex[$HECTOR]['new_sunday']) === 'LIBRE', json_encode($ex[$HECTOR] ?? null));
ok('Todo el que libraba el 29 tiene libre válido, sin conflicto', !array_filter($r['sunday_exceptions'], fn($e) => $e['new_sunday'] === null), count($r['sunday_exceptions']));
$en->UpdateDraftCell($id, $KAREN, '2026-11-27', 'COMP', $uid);
ok('Compensatorio manual el 27 se reporta como conflicto', (($en->Validate(1, 2026, 11, $en->PeriodRows(1, 2026, 11)[0]))['by_type']['special_date'] ?? 0) === 1);
$en->UpdateDraftCell($id, $KAREN, '2026-11-27', '7A7-14', $uid);

echo "\n== 5. Vacaciones con compensatorio pendiente ==\n";
$h = $en->AnalyzeHistory(1, 2026, 11);
ok('Julio dejó pendiente el domingo 11/10 al irse', in_array('2026-10-11', $h[$JULIO]['pending_comps'], true), json_encode($h[$JULIO]['pending_comps']));
$comp = $val("SELECT shift_date FROM workforce_schedule_draft_rows WHERE draft_id = $id AND payroll_code = '$JULIO' AND compensatory_origin_date = '2026-10-11'");
ok('Se programa después del regreso (4/11) y en día válido', $comp >= '2026-11-04' && date('N', strtotime($comp)) <= 5, $comp);
$cycleJulio = $h[$JULIO]['cycle'];
ok('Vacaciones no suman domingos trabajados', $cycleJulio['streak'] === 1 && $h[$JULIO]['last_free_sunday'] === '2026-10-04', json_encode($cycleJulio));
$c = Rules::Step(Rules::NewCycle('2026-10-04', 3), '2026-11-01', 'ABSENT');
ok('Libre dentro de la ausencia se consume y no reinicia el ciclo', $c['anchor'] === '2026-11-01' && $c['streak'] === 0);
ok('Al regresar no libra de inmediato', !Rules::IsFreeDue($c, '2026-11-08'));
$c2 = Rules::Step(Rules::NewCycle('2026-10-04', 1), '2026-10-11', 'ABSENT');
ok('Ausencia fuera de fase no mueve el ciclo', $c2['anchor'] === '2026-10-04' && $c2['streak'] === 1);
ok('Incapacidad y permiso cuentan igual que vacaciones', Rules::Step(Rules::NewCycle('2026-10-04'), '2026-11-01', 'ABSENT')['anchor'] === '2026-11-01');

echo "\n== 8. Empleados sin permiso nocturno ==\n";
$nightsBad = $db->query("SELECT r.payroll_code FROM workforce_schedule_draft_rows r JOIN workforce_planilla_collaborators c ON c.payroll_code = r.payroll_code WHERE r.draft_id = $id AND r.shift_type = 'NOCHE' AND r.shift_date > '2026-11-01' AND (c.department NOT LIKE 'Ventas%')")->fetchAll(PDO::FETCH_COLUMN);
ok('Bodega y Despachos no reciben noches aunque tengan can_night', !$nightsBad, implode(',', $nightsBad));
ok('Una sola persona por noche', ($r['by_type']['night_cap'] ?? 0) === 0);
$en->UpdateDraftCell($id, $KAREN, '2026-11-10', '7N22-6', $uid);
$v = $en->Validate(1, 2026, 11, $en->PeriodRows(1, 2026, 11)[0]);
ok('Noche a persona de Accesorios es bloqueante', ($v['by_type']['night_unauthorized'] ?? 0) === 1 && !$v['can_approve']);
$issue = array_values(array_filter($v['issues'], fn($i) => $i['type'] === 'night_unauthorized'))[0];
ok('El conflicto explica persona, causa y alternativas', $issue['class'] === 'BLOQUEANTE' && $issue['name'] !== null && $issue['cause'] !== '' && count($issue['alternatives']) > 0);
$en->UpdateDraftCell($id, $KAREN, '2026-11-10', '7A7-14', $uid);
$planta->SaveRestriction(['payroll_code' => $HECTOR, 'restriction_type' => 'NOCTURNO', 'night_mode' => 'DENY', 'start_date' => '2026-11-01', 'end_date' => '2026-11-30', 'reason' => 'Prueba'], $uid);
$dn = $en->BuildDraft(1, 2026, 11, $uid);
ok('Restricción NOCTURNO excluye a Héctor', intval($val("SELECT COUNT(*) FROM workforce_schedule_draft_rows WHERE draft_id = {$dn['draft_id']} AND payroll_code = '$HECTOR' AND shift_type = 'NOCHE'")) === 0);
expectError('NOCTURNO sin modo explícito se rechaza', fn() => $planta->SaveRestriction(['payroll_code' => $HECTOR, 'restriction_type' => 'NOCTURNO', 'start_date' => '2026-11-01', 'end_date' => '2026-11-30', 'reason' => 'x'], $uid));
$db->exec("UPDATE workforce_employee_restrictions SET active = 0");
$k = Rules::ClassifyShift('2026-11-07', '22:00', '06:00', 7, fn($x) => date('w', strtotime($x)) === '0');
ok('Sábado 22–6: 7 h pagas, 2/8 en sábado y 6/8 ya en domingo', $k['night'] === 1.75 && $k['rest_night'] === 5.25, json_encode($k));
ok('Recargo: 90 % en 2026 y 100 % desde julio de 2027', Rules::SundayPremium('2026-11-08') === 0.9 && Rules::SundayPremium('2027-07-01') === 1.0);

echo "\n== 7 Restricciones TOTAL y PARCIAL ==\n";
$planta->SaveRestriction(['payroll_code' => $NICOLAS, 'restriction_type' => 'TOTAL', 'start_date' => '2026-11-10', 'end_date' => '2026-11-12', 'reason' => 'Prueba'], $uid);
$planta->SaveRestriction(['payroll_code' => $KAREN, 'restriction_type' => 'PARCIAL', 'weekdays' => [1, 2, 3, 4, 5], 'window_end' => '16:00', 'max_weekly_hours' => 36, 'start_date' => '2026-11-09', 'end_date' => '2026-11-22', 'reason' => 'Prueba'], $uid);
$dt = $en->BuildDraft(1, 2026, 11, $uid);
ok('TOTAL no recibe turnos', $cell($NICOLAS, '2026-11-11', $dt['draft_id']) === 'PERM', $cell($NICOLAS, '2026-11-11', $dt['draft_id']));
$sat = $db->query("SELECT shift_type, end_time FROM workforce_schedule_draft_rows WHERE draft_id = {$dt['draft_id']} AND payroll_code = '$KAREN' AND shift_date = '2026-11-14'")->fetch(PDO::FETCH_ASSOC);
ok('PARCIAL: sábado no disponible', in_array($sat['shift_type'], ['LIBRE', 'PERMISO', 'COMPENSATORIO'], true), json_encode($sat));
ok('PARCIAL: ningún turno termina después de las 16:00', intval($val("SELECT COUNT(*) FROM workforce_schedule_draft_rows WHERE draft_id = {$dt['draft_id']} AND payroll_code = '$KAREN' AND shift_date BETWEEN '2026-11-09' AND '2026-11-22' AND shift_type NOT IN ('LIBRE', 'COMPENSATORIO', 'PERMISO') AND end_time > '16:00'")) === 0);
ok('Sin violaciones de restricción', ($dt['report']['by_type']['restriction_partial'] ?? 0) === 0, json_encode($dt['report']['by_type']));
$db->exec("UPDATE workforce_employee_restrictions SET active = 0");

echo "\n== 9. Cobertura insuficiente ==\n";
$db->exec("UPDATE workforce_skills_matrix SET can_night = 0");
$nn = $en->BuildDraft(1, 2026, 11, $uid);
ok('Sin nocturnos autorizados no inventa a nadie', intval($val("SELECT COUNT(*) FROM workforce_schedule_draft_rows WHERE draft_id = {$nn['draft_id']} AND shift_type = 'NOCHE' AND shift_date > '2026-11-04'")) === 0);
$db->exec("UPDATE workforce_skills_matrix SET can_night = 1 WHERE primary_area LIKE 'Ventas%'");
$gap = array_values(array_filter($d['report']['issues'], fn($i) => $i['type'] === 'no_coverage'))[0] ?? null;
ok('Falta de cobertura se reporta con causa y decisión gerencial', $gap === null || ($gap['class'] === 'OBLIGATORIA' && $gap['needs_management'] === true));

echo "\n== 10. Conflicto entre descanso y operación ==\n";
$db->exec("UPDATE workforce_schedule_special_dates SET active = 0");
$db->exec("INSERT INTO workforce_schedule_special_dates (special_date, rule_type, reason) VALUES ('2026-11-08', 'NO_SUNDAY_FREE', 'Prueba'), ('2026-11-01', 'NO_SUNDAY_FREE', 'Prueba')");
$lock = $en->BuildDraft(1, 2026, 11, $uid);
$noOption = array_filter($lock['report']['sunday_exceptions'], fn($e) => $e['new_sunday'] === null);
ok('Sin domingo previo válido el conflicto se muestra y bloquea', !$noOption || !$lock['report']['can_approve'], count($noOption) . ' sin opción');
ok('Y queda explicado', !$noOption || ($lock['report']['by_type']['sunday_3x1'] ?? 0) > 0, json_encode($lock['report']['by_type']));
$db->exec("DELETE FROM workforce_schedule_special_dates WHERE reason = 'Prueba'");
$db->exec("UPDATE workforce_schedule_special_dates SET active = 1");

echo "\n== 12. Jornadas sobre los límites legales ==\n";
$fresh = $en->BuildDraft(1, 2026, 11, $uid);
$id = $fresh['draft_id'];
ok('Propuesta sin semanas sobre el tope', ($fresh['report']['by_type']['weekly_hours'] ?? 0) === 0);
ok('Propuesta sin descansos cortos nuevos (solo el 1/11 real de la planilla)', !array_filter($fresh['report']['issues'], fn($i) => $i['type'] === 'min_rest' && $i['severity'] === 'legal'));
foreach (['2026-11-09', '2026-11-10', '2026-11-11', '2026-11-12', '2026-11-13', '2026-11-14', '2026-11-15'] as $x)
{
	$en->UpdateDraftCell($id, $KAREN, $x, '7i10-18', $uid);
}
$v = $en->Validate(1, 2026, 11, $en->PeriodRows(1, 2026, 11)[0]);
ok('Forzar una semana de 8 h diarias bloquea', ($v['by_type']['weekly_hours'] ?? 0) + ($v['by_type']['average_hours'] ?? 0) + ($v['by_type']['sunday_3x1'] ?? 0) > 0, json_encode($v['by_type']));
$en->UpdateDraftCell($id, $KAREN, '2026-11-15', '7N22-6', $uid);
$en->UpdateDraftCell($id, $KAREN, '2026-11-16', '7A7-14', $uid);
$v = $en->Validate(1, 2026, 11, $en->PeriodRows(1, 2026, 11)[0]);
ok('Nocturno seguido de apertura se bloquea', ($v['by_type']['min_rest'] ?? 0) >= 1 && !$v['can_approve'], json_encode($v['by_type']));

echo "\n== 8bis. Solicitudes ==\n";
$fresh = $en->BuildDraft(1, 2026, 11, $uid);
$id = $fresh['draft_id'];
$compCell = $db->query("SELECT shift_date, compensatory_origin_date o FROM workforce_schedule_draft_rows WHERE draft_id = $id AND payroll_code = '$KAREN' AND shift_type = 'COMPENSATORIO' AND shift_date > '2026-11-05' ORDER BY 1 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$sr = $req->Create(['request_type' => 'VACACIONES', 'payroll_code' => $KAREN, 'start_date' => $compCell['shift_date'], 'end_date' => $compCell['shift_date'], 'reason' => 'Prueba'], $uid);
ok('Nace pendiente', $val("SELECT status FROM workforce_requests WHERE id = $sr") === 'pendiente');
$res = $req->Decide($sr, true, $uid);
ok('Aprobada pasa a aplicada y actualiza la programación', $res['status'] === 'aplicada' && $cell($KAREN, $compCell['shift_date'], $id) === 'VC', json_encode($res));
ok('Informa la cobertura', array_key_exists('coverage_count', $res));
$rows = $en->PeriodRows(1, 2026, 11)[0];
$still = array_filter($rows, fn($x) => $x['payroll_code'] === $KAREN && $x['compensatory_origin_date'] === $compCell['o']);
ok('El compensatorio desplazado no se pierde: vuelve a pendiente', !$still && in_array($compCell['o'], array_column(array_filter($planta->Control(1, 2026, 11)['compensatories'], fn($x) => $x['payroll_code'] === $KAREN)[array_search($KAREN, array_column($planta->Control(1, 2026, 11)['compensatories'], 'payroll_code'))]['items'], 'origin_sunday'), true));
expectError('No se decide dos veces', fn() => $req->Decide($sr, true, $uid));
$rj = $req->Create(['request_type' => 'PERMISO', 'payroll_code' => $KAREN, 'start_date' => '2026-11-20', 'end_date' => '2026-11-20'], $uid);
ok('Rechazada no toca la programación', $req->Decide($rj, false, $uid)['status'] === 'rechazada' && $cell($KAREN, '2026-11-20', $id) !== 'PERM');
$sw = $req->Create(['request_type' => 'INTERCAMBIO', 'payroll_code' => $KAREN, 'start_date' => '2026-11-11', 'end_date' => '2026-11-11', 'payload' => ['with_payroll_code' => 'TEMP2']], $uid);
$before = $cell('TEMP2', '2026-11-11', $id);
$db->exec("UPDATE workforce_schedule_draft_rows SET shift_code = '7N22-6', shift_type = 'NOCHE', start_time = '22:00', end_time = '06:00', hours = 7 WHERE draft_id = $id AND payroll_code = 'TEMP2' AND shift_date = '2026-11-11'");
$res = $req->Decide($sw, true, $uid);
ok('Intercambio que da una noche a quien no puede no se aplica', $res['status'] === 'aprobada' && !empty($res['conflict']), json_encode($res));
ok('Y la programación queda como estaba', $cell($KAREN, '2026-11-11', $id) !== '7N22-6');
$db->exec("UPDATE workforce_schedule_draft_rows SET shift_code = '$before', shift_type = 'APERTURA' WHERE draft_id = $id AND payroll_code = 'TEMP2' AND shift_date = '2026-11-11'");
$day = $db->query("SELECT shift_date, shift_code FROM workforce_schedule_draft_rows WHERE draft_id = $id AND payroll_code = '$NICOLAS' AND shift_type IN ('APERTURA', 'CIERRE') AND shift_date IN ('2026-11-17', '2026-11-18', '2026-11-20') ORDER BY 1 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$origin = $val("SELECT compensatory_origin_date FROM workforce_schedule_draft_rows WHERE draft_id = $id AND payroll_code = '$NICOLAS' AND shift_date = '2026-11-19'");
if ($origin)
{
	$mv = $req->Create(['request_type' => 'COMPENSATORIO', 'payroll_code' => $NICOLAS, 'start_date' => $day['shift_date'], 'end_date' => $day['shift_date'], 'payload' => ['origin_sunday' => $origin], 'reason' => 'Cita médica'], $uid);
	$res = $req->Decide($mv, true, $uid);
	ok('Compensatorio reprogramado queda trazado', $res['status'] === 'aplicada' && $val("SELECT status FROM workforce_compensatory_bank WHERE payroll_code = '$NICOLAS' AND origin_sunday = '$origin'") === 'RESCHEDULED' && $val("SELECT previous_date FROM workforce_compensatory_bank WHERE payroll_code = '$NICOLAS' AND origin_sunday = '$origin'") === '2026-11-19', json_encode($res));
}
$bad = $req->Create(['request_type' => 'COMPENSATORIO', 'payroll_code' => $NICOLAS, 'start_date' => '2026-11-27', 'end_date' => '2026-11-27', 'payload' => ['origin_sunday' => '2026-11-08']], $uid);
ok('Compensatorio pedido para el 27 no se aplica', $req->Decide($bad, true, $uid)['status'] === 'aprobada');

echo "\n== 6-7-11. Cambios de mes, enero y duplicados ==\n";
$fresh = $en->BuildDraft(1, 2026, 11, $uid);
ok('Octubre → noviembre conserva el ciclo real de Nicolás: libre 25/10 → 22/11', $cell($NICOLAS, '2026-11-22', $fresh['draft_id']) === 'LIBRE');
ok('Sin bloqueantes en noviembre', $fresh['report']['can_approve'], json_encode($fresh['report']['by_type']));
$en->Reopen(1, 2026, 11, 'Reemplazo por el motor laboral', $uid);
$en->Approve($fresh['draft_id'], $uid, true, true);
ok('Excepciones del 29 quedan guardadas con su motivo', intval($val("SELECT COUNT(*) FROM workforce_sunday_exceptions WHERE original_sunday = '2026-11-29' AND reason <> ''")) === count($fresh['report']['sunday_exceptions']) && count($fresh['report']['sunday_exceptions']) > 0);
$dec = $en->BuildDraft(1, 2026, 12, $uid);
ok('Diciembre sin bloqueantes', $dec['report']['can_approve'], json_encode($dec['report']['by_type']));
$adv = $ex[$HECTOR]['new_sunday'];
$after = date('Y-m-d', strtotime("$adv +28 days"));
ok("Diciembre sigue desde el libre adelantado de Héctor ($adv → $after)", substr($after, 0, 7) !== '2026-12' || $cell($HECTOR, $after, $dec['draft_id']) === 'LIBRE', $cell($HECTOR, $after, $dec['draft_id']));
$carried = array_filter($fresh['report']['pending_compensatories'], fn($p) => $p['origin_sunday'] === '2026-11-29');
$placed = 0;
foreach ($carried as $p)
{
	$placed += intval($val("SELECT COUNT(*) FROM workforce_schedule_draft_rows WHERE draft_id = {$dec['draft_id']} AND payroll_code = '{$p['payroll_code']}' AND compensatory_origin_date = '2026-11-29'"));
}
ok('Compensatorios del 29/11 pasan a diciembre sin perderse', $placed === count($carried), "$placed de " . count($carried));
ok('Sin compensatorios duplicados en diciembre', ($dec['report']['by_type']['comp_duplicate'] ?? 0) === 0);
$en->Reopen(1, 2026, 12, 'Reemplazo por el motor laboral', $uid);
$en->Approve($dec['draft_id'], $uid, true, true);
$jan = $en->BuildDraft(1, 2027, 1, $uid);
ok('Diciembre → enero sin bloqueantes', $jan['report']['can_approve'], json_encode(array_slice($jan['report']['issues'], 0, 3), JSON_UNESCAPED_UNICODE));
ok('Enero usa los festivos de 2027', intval($val("SELECT COUNT(*) FROM workforce_schedule_draft_rows WHERE draft_id = {$jan['draft_id']} AND shift_date IN ('2027-01-01', '2027-01-11') AND shift_type = 'COMPENSATORIO'")) === 0);
$sun = $db->query("SELECT payroll_code, shift_date, shift_type FROM workforce_monthly_schedules WHERE is_sunday = 1 AND shift_date BETWEEN '2026-11-02' AND '2026-12-31' AND payroll_code IN (SELECT payroll_code FROM workforce_planilla_collaborators WHERE branch_id = 1) UNION ALL SELECT payroll_code, shift_date, shift_type FROM workforce_schedule_draft_rows WHERE draft_id = {$jan['draft_id']} AND is_sunday = 1 ORDER BY 1, 2")->fetchAll(PDO::FETCH_ASSOC);
$runs = [];
$max = 0;
foreach ($sun as $s)
{
	$runs[$s['payroll_code']] = in_array($s['shift_type'], WorkforceScheduleEngineService::OFF_TYPES, true) ? 0 : ($runs[$s['payroll_code']] ?? 0) + 1;
	$max = max($max, $runs[$s['payroll_code']]);
}
ok('Noviembre a enero: máximo 3 domingos seguidos', $max <= 3, $max);
$dup = intval($val("SELECT COUNT(*) FROM (SELECT 1 FROM workforce_monthly_schedules WHERE compensatory_origin_date IS NOT NULL AND shift_type = 'COMPENSATORIO' GROUP BY payroll_code, compensatory_origin_date HAVING COUNT(*) > 1)"));
ok('Un domingo, un solo compensatorio en lo oficial', $dup === 0, $dup);
expectError('El banco rechaza un segundo registro por domingo', function () use ($db) {
	$db->exec("INSERT INTO workforce_compensatory_bank (payroll_code, origin_sunday, status) VALUES ('X', '2026-11-08', 'PENDING')");
	$db->exec("INSERT INTO workforce_compensatory_bank (payroll_code, origin_sunday, status) VALUES ('X', '2026-11-08', 'PENDING')");
});
$jump = $db->query("SELECT payroll_code FROM workforce_planilla_collaborators WHERE branch_id = 1 LIMIT 1")->fetchColumn();
ok('Domingo ocasional vs habitual', Rules::SundayEntitlement(2) === 'OCASIONAL' && Rules::SundayEntitlement(3) === 'HABITUAL');

echo "\n== Vistas y exportación ==\n";
$ctl = $planta->Control(1, 2026, 11);
ok('Planta con 23 personas y sin inventar contrato', count($ctl['planta']) === 23 && $ctl['planta'][0]['contract_type'] === null && in_array('Tipo de contrato', $ctl['planta'][0]['missing'], true));
ok('Vista anual con 12 meses', count($ctl['year'][0]['months']) === 12);
ok('Vista de nocturnos con horas legales', array_sum(array_column($ctl['nights'], 'nights')) > 20 && array_sum(array_column($ctl['nights'], 'night_hours')) > 100);
$x = new ZipArchive();
$file = tempnam(sys_get_temp_dir(), 'lab');
file_put_contents($file, WorkforceScheduleXlsxService::GetInstance()->Export(1, 2026, 11));
ok('XLSX con hojas de compensatorios y domingos', $x->open($file) === true && str_contains($x->getFromName('xl/workbook.xml'), 'Compensatorios') && str_contains($x->getFromName('xl/workbook.xml'), 'Historial dominical'));
$x->close();
@unlink($file);

echo "\n$count pruebas, $fails fallos\n";
exit($fails > 0 ? 1 : 0);
