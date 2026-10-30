<?php
/**
 * Autoservicio y comunicación: mensajería con lectura real, push (estado real del proveedor, sin simular entregas),
 * turnos abiertos (tomar, aprobar, cupos, conflictos, aprobación automática), confirmación de turno y geocerca opcional.
 */

use Grocy\Services\MessagingService;
use Grocy\Services\OpenShiftService;
use Grocy\Services\PushService;
use Grocy\Services\WorkforceScheduleService;

require __DIR__ . '/bootstrap.php';

$sched = WorkforceScheduleService::GetInstance();
$msg = MessagingService::GetInstance();
$push = PushService::GetInstance();
$open = OpenShiftService::GetInstance();

// Dos empleados activos de la misma sucursal vinculados a dos usuarios sin permisos de gerencia (en la copia de la BD)
$branch = intval($val('SELECT branch_id FROM employees WHERE active = 1 GROUP BY branch_id HAVING COUNT(*) >= 2 ORDER BY branch_id LIMIT 1'));
[$e1, $e2] = $db->query("SELECT id FROM employees WHERE active = 1 AND branch_id = $branch ORDER BY id LIMIT 2")->fetchAll(PDO::FETCH_COLUMN);
$mkUser = function (string $u) use ($db) {
	$db->prepare("INSERT INTO users (username, first_name, last_name, password) VALUES (?, ?, 'Prueba', 'x')")->execute([$u, ucfirst($u)]);
	return intval($db->lastInsertId());
};
$u1 = $mkUser('emp_a_' . getmypid());
$u2 = $mkUser('emp_b_' . getmypid());
$db->exec("UPDATE employees SET user_id = $u1 WHERE id = $e1");
$db->exec("UPDATE employees SET user_id = $u2 WHERE id = $e2");
$mgr = 1;
// Días de prueba libres para ambos (la empresa de ejemplo tiene turnos programados por adelantado)
$db->exec("DELETE FROM employee_shifts WHERE employee_id IN ($e1, $e2) AND shift_date BETWEEN date('now', 'localtime', '+43 days') AND date('now', 'localtime', '+50 days')");
echo "  Sucursal $branch · empleados #$e1 (usuario $u1) y #$e2 (usuario $u2)\n";

// Transporte HTTP falso para Expo: nunca sale a la red durante la prueba
$expo = ['mode' => 'ok', 'calls' => 0];
PushService::$transport = function (string $url, array $payload) use (&$expo) {
	$expo['calls']++;
	if ($expo['mode'] === 'down')
	{
		return [0, null, 'Could not resolve host'];
	}
	if (strpos($url, 'getReceipts') !== false)
	{
		return [200, ['data' => array_fill_keys($payload['ids'], ['status' => 'ok'])], null];
	}
	return [200, ['data' => array_map(fn($m) => $expo['mode'] === 'unregistered'
		? ['status' => 'error', 'message' => 'not registered', 'details' => ['error' => 'DeviceNotRegistered']]
		: ['status' => 'ok', 'id' => 'tkt-' . uniqid()], $payload)], null];
};

echo "\n1. Notificaciones push\n";
expectError('Token inválido rechazado', fn() => $push->Register($u1, 'token-falso'));
$push->Register($u1, 'ExponentPushToken[abcdefghijklmnop1]', 'Teléfono A');
ok('Dispositivo registrado', count($push->Devices($u1)) === 1);
$r = $push->Notify([$u1, $u2], 'test', 'Hola', 'Cuerpo');
ok('Con dispositivo: enviado a Expo con ticket', $r['sent'] === 1 && intval($val("SELECT COUNT(*) FROM dairy_push_outbox WHERE user_id = $u1 AND status = 'sent' AND ticket_id IS NOT NULL")) === 1, json_encode($r));
ok('Sin dispositivo: queda como no_device (no se simula envío)', $r['no_device'] === 1 && intval($val("SELECT COUNT(*) FROM dairy_push_outbox WHERE user_id = $u2 AND status = 'no_device'")) === 1);
$expo['mode'] = 'down';
$push->Notify([$u1], 'test', 'Sin red');
ok('Sin conexión: sigue en cola para reintento', $val("SELECT status FROM dairy_push_outbox WHERE user_id = $u1 ORDER BY id DESC LIMIT 1") === 'queued');
$expo['mode'] = 'ok';
ok('El programador reenvía lo pendiente', $push->Dispatch()['sent'] === 1);
$db->exec("UPDATE dairy_push_outbox SET sent_at = datetime('now', 'localtime', '-1 hour') WHERE status = 'sent'");
ok('Recibos de Expo registrados', $push->CheckReceipts()['ok'] === 2 && intval($val("SELECT COUNT(*) FROM dairy_push_outbox WHERE status = 'receipt_ok'")) === 2);
$expo['mode'] = 'unregistered';
$push->Notify([$u1], 'test', 'Token muerto');
ok('DeviceNotRegistered revoca el token', count($push->Devices($u1)) === 0 && $val("SELECT status FROM dairy_push_outbox WHERE user_id = $u1 ORDER BY id DESC LIMIT 1") === 'error');
$expo['mode'] = 'ok';
$push->Register($u1, 'ExponentPushToken[abcdefghijklmnop1]', 'Teléfono A');
ok('Volver a registrar reactiva el token', count($push->Devices($u1)) === 1);

echo "\n2. Mensajería\n";
$contacts = array_column($msg->Contacts($u1, null), 'id');
ok('El compañero de sucursal es contacto', in_array($u2, $contacts, true));
$t = $msg->OpenDirect($u1, $u2, null);
ok('Conversación 1:1 única por pareja', $msg->OpenDirect($u2, $u1, null) === $t);
$m1 = $msg->Send($u1, $t, 'Hola, ¿me cubres el sábado?', 'uuid-0001-abcd');
ok('Envío con aviso push al destinatario', $m1['id'] > 0 && isset($m1['push']['no_device']));
ok('Reintento con el mismo client_uuid no duplica', !empty($msg->Send($u1, $t, 'Hola, ¿me cubres el sábado?', 'uuid-0001-abcd')['duplicate']) && intval($val("SELECT COUNT(*) FROM dairy_messages WHERE thread_id = $t")) === 1);
ok('No leído para el destinatario', $msg->UnreadCount($u2) === 1 && $msg->UnreadCount($u1) === 0);
$view = $msg->Messages($u1, $t);
ok('Antes de abrir: leído por nadie', $view['messages'][0]['read_count'] === 0);
$msg->Messages($u2, $t);
ok('Al abrir se marca leído', $msg->UnreadCount($u2) === 0);
$view = $msg->Messages($u1, $t);
ok('Confirmación de lectura real', $view['messages'][0]['read_count'] === 1 && $view['messages'][0]['read_by'] === ['Emp_b_' . getmypid() . ' Prueba'], json_encode($view['messages'][0]['read_by']));
expectError('Mensaje vacío rechazado', fn() => $msg->Send($u1, $t, '   '));
expectError('Mensaje demasiado largo rechazado', fn() => $msg->Send($u1, $t, str_repeat('a', 2001)));
$outsider = $mkUser('ajeno_' . getmypid());
expectError('Quien no es miembro no lee la conversación', fn() => $msg->Messages($outsider, $t));
expectError('No se puede escribir a quien no es contacto', fn() => $msg->OpenDirect($u1, $outsider, [$branch]));
$g = $msg->CreateGroup($u1, 'Turno mañana', [$u2], null);
ok('Grupo creado', count($msg->Messages($u2, $g)['members']) === 2);
$a = $msg->CreateAnnouncement($mgr, $branch, 'Inventario', 'El domingo hay inventario general a las 6:00.');
ok('Aviso a la sucursal llega a los empleados vinculados', $a['recipients'] >= 2 && $msg->UnreadCount($u2) >= 1);
expectError('Los empleados no responden un aviso', fn() => $msg->Send($u2, $a['thread_id'], 'Ok'));

echo "\n3. Turnos abiertos\n";
$day = date('Y-m-d', strtotime('+45 days'));
$req = $open->Create(['branch_id' => $branch, 'request_date' => $day, 'start_time' => '06:00', 'end_time' => '10:00', 'staff_needed' => 1, 'reason' => 'Recepción de mercancía'], $mgr);
ok('Turno abierto publicado y avisado a la sucursal', $req['id'] > 0 && $req['notified_users'] >= 2);
$list = $open->ForEmployee($u1);
$mine = array_values(array_filter($list['open'], fn($r) => intval($r['id']) === $req['id']))[0] ?? null;
ok('El empleado lo ve con la validación previa', $mine && $mine['remaining'] === 1 && $mine['blocking'] === [], json_encode($mine['blocking'] ?? null));
$c1 = $open->Claim($req['id'], $u1);
$c2 = $open->Claim($req['id'], $u2);
ok('Dos empleados lo solicitan (pendientes)', $c1['status'] === 'pending' && $c2['status'] === 'pending');
expectError('No se puede pedir dos veces', fn() => $open->Claim($req['id'], $u1));
expectError('Un empleado no aprueba su propia solicitud', fn() => $open->Decide($c1['claim_id'], true, $u1));
$dec = $open->Decide($c1['claim_id'], true, $mgr, 'Ok');
$shift = $sched->GetShift($dec['shift_id']);
ok('Aprobar crea y publica el turno real', $shift->status === 'published' && intval($shift->employee_id) === intval($e1) && $shift->shift_date === $day);
ok('Cupo lleno: la solicitud se cierra y las demás se rechazan', $dec['request_closed'] && $val("SELECT status FROM labor_coverage_claims WHERE id = {$c2['claim_id']}") === 'rejected');
expectError('Ya no se puede tomar un turno cubierto', fn() => $open->Claim($req['id'], $u2));
ok('Queda en el historial del turno', intval($val("SELECT COUNT(*) FROM employee_shift_changes WHERE shift_id = {$shift->id} AND action IN ('create', 'publish')")) === 2);
// Conflicto: un segundo turno abierto que se solapa con el ya asignado
$req2 = $open->Create(['branch_id' => $branch, 'request_date' => $day, 'start_time' => '08:00', 'end_time' => '12:00', 'staff_needed' => 1, 'reason' => 'Refuerzo', 'auto_approve' => 1], $mgr);
expectError('Solape con su propio turno: no puede tomarlo', fn() => $open->Claim($req2['id'], $u1));
$auto = $open->Claim($req2['id'], $u2);
ok('Aprobación automática: el primero válido se queda con el turno', $auto['status'] === 'approved' && $auto['auto'] && $sched->GetShift($auto['shift_id'])->status === 'published');
$req3 = $open->Create(['branch_id' => $branch, 'request_date' => date('Y-m-d', strtotime('+46 days')), 'start_time' => '14:00', 'end_time' => '18:00', 'staff_needed' => 1, 'reason' => 'Tarde'], $mgr);
$c3 = $open->Claim($req3['id'], $u1);
$open->Withdraw($c3['claim_id'], $u1);
ok('El empleado retira su solicitud pendiente', $val("SELECT status FROM labor_coverage_claims WHERE id = {$c3['claim_id']}") === 'withdrawn');
ok('Puede volver a pedirlo después de retirarlo', $open->Claim($req3['id'], $u1)['status'] === 'pending');
ok('Bandeja del gerente con tomas pendientes', $open->ForManager([$branch])['pending'] >= 1);

echo "\n4. Confirmación de turno\n";
expectError('Otro empleado no puede confirmar el turno', fn() => $sched->ConfirmShift($shift->id, $u2));
$cf = $sched->ConfirmShift($shift->id, $u1);
ok('El empleado confirma su turno publicado', !$cf['already'] && $sched->GetShift($shift->id)->confirmed_at !== null);
ok('Confirmar de nuevo no duplica', $sched->ConfirmShift($shift->id, $u1)['already'] === true);
ok('La confirmación queda en el historial', intval($val("SELECT COUNT(*) FROM employee_shift_changes WHERE shift_id = {$shift->id} AND action = 'confirm'")) === 1);
$before = intval($val("SELECT COUNT(*) FROM dairy_push_outbox WHERE category = 'shift_changed' AND user_id = $u1"));
$sched->SaveShift(['id' => $shift->id, 'employee_id' => $e1, 'branch_id' => $branch, 'shift_date' => $day, 'start_time' => '06:30', 'end_time' => '10:00', 'break_minutes' => 0, 'reason' => 'Ajuste'], $mgr);
ok('Si el turno cambia de horario, la confirmación se borra', $sched->GetShift($shift->id)->confirmed_at === null);
ok('Y el empleado recibe el aviso del cambio', intval($val("SELECT COUNT(*) FROM dairy_push_outbox WHERE category = 'shift_changed' AND user_id = $u1")) === $before + 1);
$planned = $sched->SaveShift(['employee_id' => $e1, 'branch_id' => $branch, 'shift_date' => date('Y-m-d', strtotime('+47 days')), 'start_time' => '06:00', 'end_time' => '10:00'], $mgr);
expectError('Un turno en borrador no se confirma', fn() => $sched->ConfirmShift($planned['id'], $u1));
$sched->PublishWeek($branch, date('Y-m-d', strtotime('+47 days')), $mgr);
ok('Publicar la semana avisa por push al empleado', intval($val("SELECT COUNT(*) FROM dairy_push_outbox WHERE category = 'schedule_published' AND user_id = $u1")) >= 1);

echo "\n5. Geocerca opcional\n";
$db->exec("DELETE FROM employee_attendance WHERE employee_id = $e1 AND clock_out IS NULL");
$db->exec("UPDATE branches SET geofence_mode = 'off', latitude = 25.9812, longitude = -80.1484, geofence_radius_m = 150 WHERE id = $branch");
$ci = $sched->ClockIn(intval($e1), 'mobile', $u1, null, ['lat' => 25.9812, 'lng' => -80.1484, 'accuracy' => 10]);
ok('Geocerca apagada: no se guarda la ubicación aunque llegue', $val("SELECT IFNULL(clock_in_lat, 'null') || '|' || IFNULL(clock_in_geo_status, 'null') FROM employee_attendance WHERE id = {$ci['id']}") === 'null|null');
$sched->ClockOut(intval($e1), $u1);
$db->exec("UPDATE branches SET geofence_mode = 'record' WHERE id = $branch");
$ci = $sched->ClockIn(intval($e1), 'mobile', $u1, null, ['lat' => 25.9820, 'lng' => -80.1484, 'accuracy' => 12]);
ok('Modo registro: dentro del radio, se guarda el punto y la distancia', $ci['geo']['status'] === 'inside' && $ci['geo']['distance_m'] > 50 && $ci['geo']['distance_m'] < 150, json_encode($ci['geo']));
$co = $sched->ClockOut(intval($e1), $u1, ['lat' => 26.0500, 'lng' => -80.1484, 'accuracy' => 10]);
ok('Modo registro: fuera del radio se permite pero queda marcado', $co['geo']['status'] === 'outside');
$db->exec("UPDATE branches SET geofence_mode = 'enforce' WHERE id = $branch");
expectError('Modo exigir: fuera del radio no ficha', fn() => $sched->ClockIn(intval($e1), 'mobile', $u1, null, ['lat' => 26.0500, 'lng' => -80.1484, 'accuracy' => 10]));
expectError('Modo exigir: sin ubicación no ficha', fn() => $sched->ClockIn(intval($e1), 'mobile', $u1, null, null));
ok('El rechazo no deja fichajes abiertos', intval($val("SELECT COUNT(*) FROM employee_attendance WHERE employee_id = $e1 AND clock_out IS NULL")) === 0);
$ci = $sched->ClockIn(intval($e1), 'mobile', $u1, null, ['lat' => 25.9812, 'lng' => -80.1484, 'accuracy' => 8]);
ok('Modo exigir: dentro del radio ficha', $ci['geo']['status'] === 'inside');

echo "\n$count verificaciones, $fails fallidas\n";
exit($fails > 0 ? 1 : 0);
