<?php
/**
 * Etapa 25 — Gestión completa de horarios: edición con versión (simultaneidad), historial inalterable, duplicar,
 * dividir, alta masiva, plantillas, deshacer borradores, publicación, turnos nocturnos y horario de verano,
 * correcciones de fichaje con aprobación (valor original conservado) y hoja de horas por categoría.
 */

use Grocy\Services\WorkforceScheduleService;

require __DIR__ . '/bootstrap.php';

echo "\nGestión de horarios (Etapa 25)\n";
$ws = WorkforceScheduleService::GetInstance();
$uid = GROCY_USER_ID;
$other = intval($val("SELECT id FROM users WHERE id != $uid AND id NOT IN (SELECT user_id FROM employees WHERE user_id IS NOT NULL) ORDER BY id LIMIT 1"));

$a = $ws->SaveEmployee(['name' => 'Horario A', 'branch_id' => 1, 'weekly_hours' => 40], $uid);
$b = $ws->SaveEmployee(['name' => 'Horario B', 'branch_id' => 1, 'weekly_hours' => 40], $uid);
$c = $ws->SaveEmployee(['name' => 'Horario C', 'branch_id' => 1, 'weekly_hours' => 40], $uid);
$mon = date('Y-m-d', strtotime('monday this week +21 day'));
$day = fn(int $i) => date('Y-m-d', strtotime("$mon +$i day"));

// --- Crear / editar / versión / historial
$s = $ws->SaveShift(['employee_id' => $a, 'shift_date' => $mon, 'start_time' => '07:00', 'end_time' => '15:00', 'break_minutes' => 30], $uid);
ok('crear turno: borrador versión 1', $s['status'] === 'planned' && $s['version'] === 1);
$e = $ws->SaveShift(['id' => $s['id'], 'version' => 1, 'employee_id' => $a, 'shift_date' => $mon, 'start_time' => '08:00', 'end_time' => '16:00', 'break_minutes' => 45, 'reason' => 'apertura tarde'], $uid);
ok('editar horario y descanso sube versión', $e['version'] === 2 && $ws->GetShift($s['id'])->start_time === '08:00' && intval($ws->GetShift($s['id'])->break_minutes) === 45);
expectError('edición con versión vieja (otra persona) se rechaza', fn() => $ws->SaveShift(['id' => $s['id'], 'version' => 1, 'employee_id' => $a, 'shift_date' => $mon, 'start_time' => '09:00', 'end_time' => '17:00'], $uid));
expectError('descanso mayor que el turno', fn() => $ws->SaveShift(['employee_id' => $a, 'shift_date' => $day(1), 'start_time' => '08:00', 'end_time' => '09:00', 'break_minutes' => 60], $uid));
$h = $ws->GetShiftHistory($s['id']);
$last = json_decode($h[0]->before_json, true);
ok('historial guarda valor anterior, nuevo, motivo y usuario', count($h) === 2 && $last['start_time'] === '07:00' && json_decode($h[0]->after_json, true)['start_time'] === '08:00' && $h[0]->reason === 'apertura tarde' && intval($h[0]->user_id) === $uid);
expectError('historial de turnos inalterable', fn() => $db->exec("UPDATE employee_shift_changes SET after_json = '{}' WHERE id = {$h[0]->id}"));
expectError('historial de turnos no se borra', fn() => $db->exec("DELETE FROM employee_shift_changes WHERE id = {$h[0]->id}"));

// --- Turno nocturno, solapamiento al día siguiente, límite diario
$n = $ws->SaveShift(['employee_id' => $b, 'shift_date' => $day(1), 'start_time' => '22:00', 'end_time' => '06:00'], $uid);
ok('turno nocturno = 8 h', WorkforceScheduleService::ShiftHours($ws->GetShift($n['id'])) == 8.0);
expectError('nocturno se superpone con turno temprano del día siguiente', fn() => $ws->SaveShift(['employee_id' => $b, 'shift_date' => $day(2), 'start_time' => '05:00', 'end_time' => '09:00'], $uid));
$long = $ws->SaveShift(['employee_id' => $c, 'shift_date' => $day(1), 'start_time' => '06:00', 'end_time' => '18:00'], $uid);
ok('aviso de límite diario (12 h > 10 h)', in_array('daily_limit', array_column($long['warnings'], 'code')));

// --- Horario de verano: 2026-03-08 02:00 en Nueva York se adelanta una hora
$tz = date_default_timezone_get();
date_default_timezone_set('America/New_York');
$dst = (object) ['shift_date' => '2026-03-07', 'start_time' => '22:00', 'end_time' => '06:00', 'break_minutes' => 0];
$dstBack = (object) ['shift_date' => '2026-10-31', 'start_time' => '22:00', 'end_time' => '06:00', 'break_minutes' => 0];
ok('nocturno en inicio de horario de verano = 7 h reales', WorkforceScheduleService::ShiftHours($dst) == 7.0, WorkforceScheduleService::ShiftHours($dst));
ok('nocturno en fin de horario de verano = 9 h reales', WorkforceScheduleService::ShiftHours($dstBack) == 9.0, WorkforceScheduleService::ShiftHours($dstBack));
date_default_timezone_set($tz);

// --- Duplicar, dividir, masivo, plantilla
$dup = $ws->DuplicateShift($s['id'], [$day(2), $day(3)], [$a], $uid);
ok('duplicar turno a 2 fechas', count($dup['created']) === 2 && count($dup['skipped']) === 0);
$dupConf = $ws->DuplicateShift($s['id'], [$day(1)], [$c], $uid); // C ya tiene 06–18 ese día
ok('duplicar reporta conflicto en vez de crear solapado', count($dupConf['created']) === 0 && count($dupConf['skipped']) === 1);
$sp = $ws->SplitShift($long['id'], '12:00', $b, $uid, 'cobertura compartida');
$s1 = $ws->GetShift($sp['first_id']);
$s2 = $ws->GetShift($sp['second_id']);
ok('dividir: primer tramo 06–12 y segundo 12–18 a otro empleado', $s1->end_time === '12:00' && $s2->start_time === '12:00' && $s2->end_time === '18:00' && intval($s2->employee_id) === $b);
expectError('dividir con tramo < 1 h', fn() => $ws->SplitShift($sp['first_id'], '06:30', null, $uid));
$bulk = $ws->BulkCreateShifts(['employee_ids' => [$a, $c], 'dates' => [$day(4), $day(5)], 'start_time' => '10:00', 'end_time' => '18:00', 'break_minutes' => 30, 'branch_id' => 1], $uid);
ok('alta masiva 2 empleados × 2 días', count($bulk['created']) === 4);

// --- Deshacer (lote masivo completo) y solo borradores
$ws->UndoLastChange(1, $uid);
$alive = intval($val('SELECT COUNT(*) FROM employee_shifts WHERE id IN (' . implode(',', $bulk['created']) . ") AND status != 'cancelled'"));
ok('deshacer revierte todo el lote masivo', $alive === 0);
$tpl = $ws->SaveTemplate(['branch_id' => 1, 'name' => 'Apertura', 'start_time' => '06:00', 'end_time' => '14:00', 'break_minutes' => 30, 'weekdays' => [6, 7]], $uid);
$ap = $ws->ApplyTemplate($tpl, $mon, [$c], $uid);
ok('plantilla aplica sáb y dom', count($ap['created']) === 2 && $ws->GetShift($ap['created'][0])->shift_date === $day(5));
$ws->UndoLastChange(1, $uid);
ok('deshacer plantilla', intval($val('SELECT COUNT(*) FROM employee_shifts WHERE id IN (' . implode(',', $ap['created']) . ") AND status != 'cancelled'")) === 0);
// Deshacer una edición restaura los valores anteriores
$ws->SaveShift(['id' => $dup['created'][0], 'employee_id' => $a, 'shift_date' => $day(2), 'start_time' => '11:00', 'end_time' => '19:00'], $uid);
$ws->UndoLastChange(1, $uid);
ok('deshacer edición restaura horario', $ws->GetShift($dup['created'][0])->start_time === '08:00');

// --- Publicar; lo publicado no se deshace; editar publicado queda en historial
$pub = $ws->PublishWeek(1, $mon, $uid);
ok('publicar semana', $pub >= 4 && $ws->GetShift($s['id'])->status === 'published');
$ws->SaveShift(['id' => $s['id'], 'employee_id' => $a, 'shift_date' => $mon, 'start_time' => '08:30', 'end_time' => '16:00', 'break_minutes' => 45, 'reason' => 'cambio publicado'], $uid);
expectError('no se deshace un turno ya publicado', fn() => $ws->UndoLastChange(1, $uid));
ok('editar turno publicado deja registro con estado publicado', json_decode($ws->GetShiftHistory($s['id'])[0]->before_json, true)['status'] === 'published');
$ws->CancelShift($dup['created'][1], $uid, 'ya no se necesita');
ok('anular turno guarda motivo', $ws->GetShiftHistory($dup['created'][1])[0]->reason === 'ya no se necesita');

// --- Fichajes: editar turno no cambia horas trabajadas; corrección con aprobación y valor original
$yest = date('Y-m-d', strtotime('-1 day'));
$db->exec("INSERT INTO employee_attendance (employee_id, clock_in, clock_out, source, recorded_by_user_id) VALUES ($a, '$yest 08:00:00', '$yest 16:00:00', 'web', $uid)");
$att = intval($db->lastInsertId());
$w0 = $ws->WorkedHoursByEmployee($yest, $yest)[$a] ?? 0;
expectError('fichaje cerrado no se modifica directamente (trigger)', fn() => $db->exec("UPDATE employee_attendance SET clock_out = '$yest 18:00:00' WHERE id = $att"));
expectError('fichaje no se borra (trigger)', fn() => $db->exec("DELETE FROM employee_attendance WHERE id = $att"));
expectError('corrección sin motivo', fn() => $ws->SaveAttendance(['id' => $att, 'clock_in' => "$yest 08:00", 'clock_out' => "$yest 17:00"], $uid));
$adj = $ws->SaveAttendance(['id' => $att, 'clock_in' => "$yest 08:00", 'clock_out' => "$yest 17:00", 'notes' => 'olvidó marcar salida'], $uid);
ok('solicitud de corrección no altera el fichaje', ($ws->WorkedHoursByEmployee($yest, $yest)[$a] ?? 0) == $w0);
expectError('segunda corrección pendiente del mismo fichaje', fn() => $ws->SaveAttendance(['id' => $att, 'clock_in' => "$yest 08:00", 'clock_out' => "$yest 17:30", 'notes' => 'x'], $uid));
expectError('quien solicita no aprueba', fn() => $ws->DecideAttendanceAdjustment($adj, true, $uid));
expectError('rechazo exige motivo', fn() => $ws->DecideAttendanceAdjustment($adj, false, $other));
$ws->DecideAttendanceAdjustment($adj, true, $other, 'verificado con cámara');
$row = $db->query("SELECT * FROM employee_attendance_adjustments WHERE id = $adj")->fetch(PDO::FETCH_OBJ);
ok('aprobada: conserva original, corregido, motivo, solicitante, aprobador y fecha', $row->original_clock_out === "$yest 16:00:00" && $row->new_clock_out === "$yest 17:00:00" && $row->reason === 'olvidó marcar salida' && intval($row->requested_by_user_id) === $uid && intval($row->decided_by_user_id) === $other && $row->decided_timestamp !== null && $row->status === 'approved');
ok('aprobada: fichaje actualizado (+1 h)', ($ws->WorkedHoursByEmployee($yest, $yest)[$a] ?? 0) == $w0 + 1);
expectError('corrección aprobada inalterable', fn() => $db->exec("UPDATE employee_attendance_adjustments SET status = 'rejected' WHERE id = $adj"));
expectError('corrección no se borra', fn() => $db->exec("DELETE FROM employee_attendance_adjustments WHERE id = $adj"));
// Empleado vinculado no aprueba sus propias horas
$db->exec("UPDATE employees SET user_id = $other WHERE id = $a");
$adj2 = $ws->SaveAttendance(['id' => $att, 'clock_in' => "$yest 07:30", 'clock_out' => "$yest 17:00", 'notes' => 'entró antes'], $uid);
expectError('empleado no aprueba sus propias horas', fn() => $ws->DecideAttendanceAdjustment($adj2, true, $other));
$ws->DecideAttendanceAdjustment($adj2, false, intval($val("SELECT id FROM users WHERE id NOT IN ($uid, $other) ORDER BY id LIMIT 1")) ?: 999, 'sin respaldo');
ok('rechazada: fichaje sin cambios', $val("SELECT clock_in FROM employee_attendance WHERE id = $att") === "$yest 08:00:00");
$db->exec("UPDATE employees SET user_id = NULL WHERE id = $a");
// Corrección obsoleta: el fichaje cambió después de pedirla
$db->exec("INSERT INTO employee_attendance (employee_id, clock_in, source) VALUES ($c, '$yest 06:00:00', 'web')");
$open = intval($db->lastInsertId());
$adj3 = $ws->SaveAttendance(['id' => $open, 'clock_in' => "$yest 06:15", 'notes' => 'hora real'], $uid);
$db->exec("UPDATE employee_attendance SET clock_out = '$yest 14:00:00' WHERE id = $open"); // salida normal (permitida)
expectError('aprobar corrección obsoleta se rechaza', fn() => $ws->DecideAttendanceAdjustment($adj3, true, $other));
// Editar el turno no cambia horas trabajadas
$sh = $ws->SaveShift(['employee_id' => $c, 'shift_date' => $yest, 'start_time' => '06:00', 'end_time' => '14:00'], $uid);
$wc = $ws->WorkedHoursByEmployee($yest, $yest)[$c] ?? 0;
$ws->SaveShift(['id' => $sh['id'], 'employee_id' => $c, 'shift_date' => $yest, 'start_time' => '07:00', 'end_time' => '13:00'], $uid);
ok('editar turno planificado no altera horas trabajadas', ($ws->WorkedHoursByEmployee($yest, $yest)[$c] ?? 0) == $wc);

// --- Hoja de horas por categoría
$ts = $ws->GetTimesheet(1, $yest, $yest);
$ra = array_values(array_filter($ts['rows'], fn($r) => $r['employee_id'] === $a))[0];
$rc = array_values(array_filter($ts['rows'], fn($r) => $r['employee_id'] === $c))[0];
ok('hoja: trabajadas, correcciones y sin pendientes (A)', $ra['worked'] == 9.0 && $ra['corrections'] === 1 && $ra['pending_review'] === 0, json_encode($ra));
ok('hoja: pendiente de revisión y borrador (C)', $rc['pending_review'] === 1 && $rc['scheduled_draft'] == 6.0, json_encode($rc));
foreach (['scheduled', 'worked', 'pending_review', 'overtime', 'breaks_hours', 'absence_days', 'time_off_days', 'manual_entries', 'corrections'] as $k)
{
	if (!array_key_exists($k, $ra))
	{
		ok("hoja incluye $k", false);
	}
}

echo "\n$count verificaciones, $fails fallos\n";
exit($fails > 0 ? 1 : 0);
