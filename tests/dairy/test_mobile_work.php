<?php
/**
 * DAIRY Mobile por rol (Etapa 24): rol derivado de permisos, estado de turno y próxima acción, centro de tareas
 * sin duplicados (alerta → tarea → cierre de la alerta), permisos en el servidor, equipo, notificaciones y escáner.
 */

use Grocy\Helpers\DairyAccess;
use Grocy\Services\AlertCenterService;
use Grocy\Services\MobileWorkService;
use Grocy\Services\WorkforceScheduleService;

require __DIR__ . '/bootstrap.php';

$svc = MobileWorkService::GetInstance();
$sched = WorkforceScheduleService::GetInstance();
$uid = GROCY_USER_ID;
$now = date('Y-m-d H:i:s');
$today = date('Y-m-d');

// Cambia los permisos del usuario de la prueba y limpia la caché de DairyAccess
function setPerms(array $perms)
{
	global $db, $uid;
	$db->exec("DELETE FROM user_permissions WHERE user_id = $uid");
	foreach ($perms as $p)
	{
		$db->exec("INSERT INTO user_permissions (user_id, permission_id) SELECT $uid, id FROM permission_hierarchy WHERE name = '$p'");
	}
	$r = new ReflectionClass(DairyAccess::class);
	foreach (['cache' => [], 'user' => null] as $k => $v)
	{
		$pr = $r->getProperty($k);
		$pr->setValue(null, $v);
	}
}

// Empleado vinculado al usuario, con turno que empezó hace 20 minutos (publicado) y uno en borrador mañana
$db->exec("DELETE FROM employees WHERE user_id = $uid");
$db->exec("INSERT INTO employees (user_id, name, branch_id, position, department, weekly_hours, active) VALUES ($uid, 'Prueba Móvil', 1, 'Reponedor', 'Lácteos', 40, 1)");
$emp = intval($db->lastInsertId());
$db->exec("INSERT INTO employees (user_id, name, branch_id, position, active) VALUES (NULL, 'Compañera Prueba', 1, 'Cajera', 1)");
$mate = intval($db->lastInsertId());
$start = date('H:i', strtotime('-20 minutes'));
$end = date('H:i', strtotime('+6 hours'));
$db->exec("INSERT INTO employee_shifts (employee_id, branch_id, shift_date, start_time, end_time, break_minutes, status) VALUES ($emp, 1, '$today', '$start', '$end', 30, 'published')");
$shiftId = intval($db->lastInsertId());
$db->exec("INSERT INTO employee_shifts (employee_id, branch_id, shift_date, start_time, end_time, status) VALUES ($emp, 1, date('now', 'localtime', '+1 day'), '08:00', '16:00', 'planned')");
$db->exec("INSERT INTO employee_shifts (employee_id, branch_id, shift_date, start_time, end_time, status) VALUES ($emp, 1, date('now', 'localtime', '+2 day'), '08:00', '16:00', 'published')");
$shift2 = intval($db->lastInsertId());

// Tareas: una para el rol "clerk", una para supervisor, una asignada a otra persona y una vencida asignada al usuario
$db->exec("UPDATE workforce_store_tasks SET status = 'completed' WHERE status = 'pending'");
// Alertas abiertas de la BD (p. ej. inventario sensible de la empresa de ejemplo) no deben adelantarse a la tarea de la prueba
$db->exec("UPDATE dairy_alerts SET status = 'closed' WHERE status NOT IN ('closed', 'dismissed')");
$soon = date('Y-m-d H:i:s', strtotime('+1 hour'));
$late = date('Y-m-d H:i:s', strtotime('-2 hour'));
$db->exec("INSERT INTO workforce_store_tasks (branch_id, task_type, assigned_role, title, due_time, status, created_timestamp) VALUES
	(1, 'x', 'clerk', 'T clerk', '$soon', 'pending', '$now'),
	(1, 'x', 'supervisor', 'T supervisor', '$soon', 'pending', '$now'),
	(1, 'x', 'clerk', 'T de otro', '$soon', 'pending', '$now')");
$db->exec("UPDATE workforce_store_tasks SET assigned_user_id = (SELECT id FROM users WHERE id != $uid LIMIT 1) WHERE title = 'T de otro'");
$db->exec("INSERT INTO workforce_store_tasks (branch_id, task_type, assigned_role, assigned_user_id, title, due_time, status, created_timestamp) VALUES (1, 'x', 'clerk', $uid, 'T mía vencida', '$late', 'pending', '$now')");
$titles = fn(array $items) => array_column(array_filter($items, fn($i) => $i['type'] === 'task'), 'title');

echo "\n1. Empleado: rol, turno y próxima acción\n";
setPerms(['TASKS', 'WASTE_REPORT', 'STOCK_INVENTORY']);
ok('rol = empleado', MobileWorkService::Role() === 'employee');
ok('no aprueba ni ve equipo', !MobileWorkService::CanApprove() && !MobileWorkService::CanSeeTeam());
$st = $svc->ShiftState($uid);
ok('turno empezado sin fichar = atrasado', $st['state'] === 'late', $st['state']);
$t = $svc->Today(1, $uid, 'employee');
ok('próxima acción = marcar entrada', $t['next_action']['kind'] === 'clock_in');
ok('sin bloque de equipo', !isset($t['team']));
$sched->ClockIn($emp, 'mobile', $uid);
ok('tras fichar: en turno', $svc->ShiftState($uid)['state'] === 'on_shift');
$t = $svc->Today(1, $uid, 'employee');
ok('próxima acción = tarea vencida', $t['next_action']['kind'] === 'open' && $t['next_action']['title'] === 'T mía vencida', $t['next_action']['title']);

echo "\n2. Empleado: tareas propias\n";
$mine = $titles($svc->Tasks('mine', 1, $uid, 'employee'));
ok('ve tarea del rol y la asignada', in_array('T clerk', $mine, true) && in_array('T mía vencida', $mine, true));
ok('no ve la de supervisor ni la de otra persona', !in_array('T supervisor', $mine, true) && !in_array('T de otro', $mine, true), implode(', ', $mine));
ok('la vencida va primero', $mine[0] === 'T mía vencida');
$my = $svc->MySchedule($uid);
ok('mi horario oculta turnos en borrador', count(array_filter($my['shifts'], fn($s) => $s->status === 'planned')) === 0 && count($my['shifts']) === 2);
ok('compañeros para cambio de turno (sin mí)', in_array($mate, array_column($my['coworkers'], 'id'), true) && !in_array($emp, array_column($my['coworkers'], 'id'), true));
$prod = $db->query('SELECT p.id FROM products p WHERE p.active = 1 LIMIT 1')->fetchColumn();
$acts = array_column($svc->ScanActions(['kind' => 'product', 'product' => ['id' => $prod], 'parsed' => []]), 'code');
ok('escáner: solo acciones permitidas', in_array('waste', $acts, true) && in_array('count', $acts, true) && !in_array('sku', $acts, true) && !in_array('move', $acts, true), implode(',', $acts));

echo "\n3. Gerente: alerta → tarea → cierre sin duplicados\n";
setPerms(['ADMIN']);
ok('rol = gerente', MobileWorkService::Role() === 'manager');
$db->exec("INSERT INTO dairy_alerts (rule_code, dedupe_key, branch_id, severity, title, detail, status, created_at, updated_at, last_seen_at) VALUES ('phantom_stock', 'test-m24', 1, 'danger', 'Alerta prueba móvil', 'detalle', 'open', '$now', '$now', '$now')");
$alert = intval($db->lastInsertId());
$r1 = $svc->TaskFromAlert($alert, $uid, null, $uid);
$r2 = $svc->TaskFromAlert($alert, $uid, null, $uid);
ok('crea la tarea una sola vez', $r1['created'] && !$r2['created'] && $r1['id'] === $r2['id']);
ok('alerta asignada al responsable', intval($val("SELECT owner_user_id FROM dairy_alerts WHERE id = $alert")) === $uid);
$team = $svc->Tasks('team', 1, $uid, 'manager');
ok('en el centro la alerta aparece solo como tarea', count(array_filter($team, fn($i) => $i['type'] === 'alert' && $i['id'] === $alert)) === 0 && count(array_filter($team, fn($i) => $i['type'] === 'task' && $i['source_id'] === $alert)) === 1);
expectError('completar sin nota falla (cierra la alerta)', fn() => $svc->CompleteTask($r1['id'], '', null, $uid));
ok('la tarea sigue pendiente tras el error', $val("SELECT status FROM workforce_store_tasks WHERE id = {$r1['id']}") === 'pending');
$c = $svc->CompleteTask($r1['id'], 'Se recontó la góndola', null, $uid);
ok('completar cierra la alerta de origen', $c['alert_status'] === 'closed' && $val("SELECT status FROM dairy_alerts WHERE id = $alert") === 'closed', $c['message']);
expectError('no se completa dos veces', fn() => $svc->CompleteTask($r1['id'], 'otra vez', null, $uid));

echo "\n4. Equipo, solicitudes y notificaciones\n";
$team = $svc->Team(1, $today);
$me = array_values(array_filter($team['people'], fn($p) => $p['employee_id'] === $emp))[0] ?? null;
ok('equipo: empleado en turno (fichó tarde)', $me && $me['status'] === 'late', $me['status'] ?? '');
$other = intval($val("SELECT id FROM users WHERE id != $uid ORDER BY id LIMIT 1"));
$ownAbs = $sched->RequestAbsence(['employee_id' => $emp, 'absence_type' => 'vacation', 'start_date' => date('Y-m-d', strtotime('+10 day')), 'end_date' => date('Y-m-d', strtotime('+11 day'))], $uid);
$absId = $sched->RequestAbsence(['employee_id' => $mate, 'absence_type' => 'sick', 'start_date' => date('Y-m-d', strtotime('+3 day')), 'end_date' => date('Y-m-d', strtotime('+3 day'))], $uid);
$db->exec("UPDATE employee_absences SET requested_by_user_id = $other WHERE id = $absId");
$db->exec("INSERT INTO employee_shifts (employee_id, branch_id, shift_date, start_time, end_time, status) VALUES ($mate, 1, date('now', 'localtime', '+4 day'), '08:00', '16:00', 'published')");
$mateShift = intval($db->lastInsertId());
$sched->RequestSwap($mateShift, $emp, $other, 'cita médica');
$sched->RequestSwap($shift2, $mate, $uid, 'propio');
$appr = $svc->Tasks('approve', 1, $uid, 'manager');
ok('por aprobar incluye ausencia y cambio de turno de otros', count(array_filter($appr, fn($i) => $i['type'] === 'absence' && $i['id'] === $absId)) === 1 && count(array_filter($appr, fn($i) => $i['type'] === 'swap')) === 1);
ok('no incluye mis propias solicitudes (segregación)', count(array_filter($appr, fn($i) => $i['type'] === 'absence' && $i['id'] === $ownAbs)) === 0);
$n = $svc->Notifications(1, $uid, 'manager');
$groups = array_filter($n['items'], fn($i) => str_starts_with($i['key'], 'appr:absence:'));
ok('aprobaciones agrupadas por tipo (una notificación)', count($groups) === 1);
ok('claves únicas', count(array_unique(array_column($n['items'], 'key'))) === count($n['items']));
$svc->SetSetting($uid, 'dairy_m_notif_seen', date('Y-m-d H:i:s', time() + 1));
ok('marcar vistas → 0 sin leer', $svc->Notifications(1, $uid, 'manager')['unread'] === 0);
$svc->SetSetting($uid, 'dairy_m_notif_prefs', json_encode(['shifts' => true, 'tasks' => true, 'approvals' => false, 'alerts' => true]));
ok('preferencia desactiva aprobaciones', count(array_filter($svc->Notifications(1, $uid, 'manager')['items'], fn($i) => $i['type'] === 'approvals')) === 0);
$sched->DecideAbsence($ownAbs, true, $other);
$n = $svc->Notifications(1, $uid, 'manager');
ok('decisión de mi ausencia me notifica', count(array_filter($n['items'], fn($i) => $i['key'] === "abs:$ownAbs:approved")) === 1);

echo "\n" . ($fails ? "$fails de $count FALLARON" : "$count OK") . "\n";
exit($fails ? 1 : 0);
