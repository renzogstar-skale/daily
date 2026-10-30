<?php
/**
 * Confiabilidad (Etapa 26): idempotencia y numeración de mermas, disponible al reportar, evidencia, aprobación
 * idempotente tras releer con bloqueo, una sola entrada abierta por empleado, comparación de parámetros enlazados
 * como texto (afinidad SQLite), avisos de decisión de merma y de turno reasignado, tarea directa del supervisor.
 * La concurrencia real entre procesos se prueba por HTTP en http_reliability.php.
 */

use Grocy\Services\MobileWorkService;
use Grocy\Services\ScanService;
use Grocy\Services\WasteService;
use Grocy\Services\WorkforceScheduleService;

require __DIR__ . '/bootstrap.php';

$ws = WasteService::GetInstance();
$sched = WorkforceScheduleService::GetInstance();
$mw = MobileWorkService::GetInstance();
$uid = GROCY_USER_ID;
$other = intval($val("SELECT id FROM users WHERE id <> $uid ORDER BY id LIMIT 1"));
$p = $db->query("SELECT s.product_id, s.location_id, SUM(s.amount) AS amount FROM stock s JOIN locations l ON l.id = s.location_id WHERE l.branch_id = 1 GROUP BY s.product_id, s.location_id HAVING SUM(s.amount) >= 10 ORDER BY s.product_id LIMIT 1")->fetch(PDO::FETCH_OBJ);
$free = intval($val("SELECT id FROM waste_reasons WHERE active = 1 AND requires_evidence = 0 AND requires_approval = 0 AND lower(IFNULL(risk_level, '')) NOT IN ('alto', 'alta', 'crítico', 'crítica', 'critico', 'critica') ORDER BY id LIMIT 1"));
$appr = intval($val("SELECT id FROM waste_reasons WHERE active = 1 AND requires_evidence = 0 AND requires_approval = 1 ORDER BY id LIMIT 1"));
$stock = fn() => floatval($val("SELECT IFNULL(SUM(amount), 0) FROM stock WHERE product_id = {$p->product_id} AND location_id = {$p->location_id}"));
$pendingHere = fn() => floatval($val("SELECT IFNULL(SUM(amount), 0) FROM waste_records WHERE product_id = {$p->product_id} AND branch_id = 1 AND location_id = {$p->location_id} AND status = 'pending_approval' AND inventory_deducted = 0"));

echo "\n1. Índices de integridad (migración 0283)\n";
$idx = $db->query("SELECT name FROM sqlite_master WHERE type = 'index' AND name IN ('ux_waste_records_idempotency', 'ux_waste_records_case_number', 'ux_employee_attendance_open')")->fetchAll(PDO::FETCH_COLUMN);
ok('3 índices únicos creados', count($idx) === 3, implode(', ', $idx));
ok('PHP y SQLite usan la misma hora local', date('Y-m-d H:i') === substr($val("SELECT datetime('now', 'localtime')"), 0, 16), date_default_timezone_get());

echo "\n2. Mermas: idempotencia, evidencia, numeración y disponible\n";
$key = 'rel-' . bin2hex(random_bytes(6));
$a = $ws->ReportWaste($p->product_id, 1, $free, 1, $p->location_id, 'default', 'rel', null, null, null, null, null, null, $key, $uid, false, true);
$b = $ws->ReportWaste($p->product_id, 1, $free, 1, $p->location_id, 'default', 'rel', null, null, null, null, null, null, $key, $uid, false, true);
ok('misma clave, mismo usuario → mismo caso', intval($a->id) === intval($b->id));
expectError('misma clave desde otro usuario → error (no expone el caso ajeno)', fn() => $ws->ReportWaste($p->product_id, 1, $free, 1, $p->location_id, 'default', 'x', null, null, null, null, null, null, $key, $other, false, true));
expectError('inserción directa con clave repetida → índice único', fn() => $db->exec("INSERT INTO waste_records (branch_id, product_id, amount, qu_id, waste_reason_id, reported_by_user_id, idempotency_key) SELECT branch_id, product_id, 1, qu_id, waste_reason_id, reported_by_user_id, idempotency_key FROM waste_records WHERE id = {$a->id}"));
expectError('ruta de evidencia arbitraria → error', fn() => $ws->ReportWaste($p->product_id, 1, $free, 1, $p->location_id, 'default', 'x', '../../data/config.php', null, null, null, null, null, null, $uid, false, true));
$okPhoto = $ws->ReportWaste($p->product_id, 1, $free, 1, $p->location_id, 'default', 'x', '/api/waste/photo/waste_20261020_120000_abc123.jpg', null, null, null, null, null, null, $uid, false, true);
ok('foto subida por /waste/upload-photo se acepta', !empty($okPhoto->evidence_photo_path));
$prefix = 'MRM-' . date('Ym') . '-';
$db->exec("UPDATE waste_records SET case_number = '{$prefix}9000' WHERE id = {$okPhoto->id}");
$next = $ws->ReportWaste($p->product_id, 1, $free, 1, $p->location_id, 'default', 'x', null, null, null, null, null, null, null, $uid, false, true);
ok('número de caso = máximo + 1 (no COUNT + 1)', $next->case_number === $prefix . '9001', $next->case_number);
$avail = $stock() - $pendingHere();
expectError('pendiente mayor al disponible → error', fn() => $ws->ReportWaste($p->product_id, $avail + 1, $appr, 1, $p->location_id, 'default', 'x', null, null, null, null, null, null, null, $uid, false, true));
$big = $ws->ReportWaste($p->product_id, $avail - 1, $appr, 1, $p->location_id, 'default', 'grande', null, null, null, null, null, null, null, $uid, false, true);
ok('pendiente dentro del disponible se registra', $big->status === 'pending_approval', $big->status);
expectError('la pendiente reserva: otra que la supera → error', fn() => $ws->ReportWaste($p->product_id, 2, $appr, 1, $p->location_id, 'default', 'x', null, null, null, null, null, null, null, $uid, false, true));
ok('riesgo "Alta" exige aprobación', $ws->RequiresApproval((object) ['requires_approval' => 0, 'risk_level' => 'Alta'], 1));

echo "\n3. Aprobación y rechazo releen el estado\n";
$s0 = $stock();
$db->exec("UPDATE waste_records SET reported_by_user_id = $other WHERE id = {$big->id}"); // segregación: aprueba otra persona
$r1 = $ws->ApproveWaste(intval($big->id), $uid);
$r2 = $ws->ApproveWaste(intval($big->id), $uid);
ok('aprobar dos veces: misma respuesta aprobada', $r1->status === 'approved' && $r2->status === 'approved');
ok('stock descontado una sola vez', abs($s0 - $stock() - ($avail - 1)) < 0.001, "$s0 → " . $stock());
expectError('rechazar una aprobada → error', fn() => $ws->RejectWaste(intval($big->id), $uid, 'tarde'));

echo "\n4. Fichaje: una sola entrada abierta\n";
$empUser = intval($val('SELECT id FROM users WHERE id NOT IN (SELECT user_id FROM employees WHERE user_id IS NOT NULL) ORDER BY id DESC LIMIT 1'));
$emp = $sched->SaveEmployee(['name' => 'Prueba confiabilidad', 'branch_id' => 1, 'user_id' => $empUser ?: null]);
$sched->ClockIn($emp, 'mobile', $uid);
expectError('segunda entrada abierta → error', fn() => $sched->ClockIn($emp, 'mobile', $uid));
expectError('inserción directa de otra entrada abierta → índice único', fn() => $db->exec("INSERT INTO employee_attendance (employee_id, clock_in, source) VALUES ($emp, datetime('now', 'localtime'), 'web')"));
$sched->ClockOut($emp, $uid);
expectError('salida ya registrada → error', fn() => $sched->ClockOut($emp, $uid));

echo "\n5. Parámetros enlazados como texto (afinidad SQLite)\n";
$loc = intval($val('SELECT id FROM locations WHERE branch_id = 1 ORDER BY id LIMIT 1'));
$s1 = ScanService::GetInstance()->OpenSession(['type' => 'count', 'branch_id' => 1, 'location_id' => $loc], $uid);
$s2 = ScanService::GetInstance()->OpenSession(['type' => 'count', 'branch_id' => 1, 'location_id' => $loc], $uid);
ok('sesión de escaneo abierta se reanuda (antes creaba otra)', intval($s1['id'] ?? $s1['session']['id'] ?? 0) === intval($s2['id'] ?? $s2['session']['id'] ?? -1) && !empty($s2['resumed']));

echo "\n6. Avisos y tareas\n";
if ($empUser)
{
	$wid = $ws->ReportWaste($p->product_id, 1, $appr, 1, $p->location_id, 'default', 'aviso', null, null, null, null, null, null, null, $empUser, false, true);
	$ws->RejectWaste(intval($wid->id), $uid, 'Producto en buen estado');
	$n = json_encode($mw->Notifications(1, $empUser, 'employee'), JSON_UNESCAPED_UNICODE);
	ok('quien reportó recibe "Merma rechazada"', strpos($n, 'Merma rechazada') !== false);
	$today = date('Y-m-d', strtotime('+1 day'));
	$sh = $sched->SaveShift(['employee_id' => $emp, 'shift_date' => $today, 'start_time' => '08:00', 'end_time' => '12:00'], $uid);
	$sched->PublishWeek(1, date('Y-m-d', strtotime('monday this week', strtotime($today))), $uid);
	$t = $mw->CreateTask(['employee_id' => $emp, 'title' => 'Reponer exhibición', 'due' => $today . ' 09:00'], $uid);
	$row = $db->query("SELECT * FROM workforce_store_tasks WHERE id = {$t['id']}")->fetch(PDO::FETCH_ASSOC);
	ok('tarea directa: empleado, usuario y turno del día', intval($row['assigned_employee_id']) === $emp && intval($row['assigned_user_id']) === $empUser && intval($row['shift_id']) === intval($sh['id']));
	ok('empleado recibe "Nueva tarea"', strpos(json_encode($mw->Notifications(1, $empUser, 'employee'), JSON_UNESCAPED_UNICODE), 'Nueva tarea: Reponer') !== false);
	expectError('tarea sin descripción → error', fn() => $mw->CreateTask(['employee_id' => $emp, 'title' => ' '], $uid));
	// Turno publicado reasignado a otra persona: quien lo perdía recibe el aviso (json_extract vs parámetro de texto)
	$emp2 = $sched->SaveEmployee(['name' => 'Prueba reasignación', 'branch_id' => 1]);
	$v = intval($val("SELECT version FROM employee_shifts WHERE id = {$sh['id']}"));
	$sched->SaveShift(['id' => $sh['id'], 'version' => $v, 'employee_id' => $emp2, 'shift_date' => $today, 'start_time' => '08:00', 'end_time' => '12:00', 'reason' => 'cobertura'], $uid);
	$n = json_encode($mw->Notifications(1, $empUser, 'employee'), JSON_UNESCAPED_UNICODE);
	ok('quien pierde un turno publicado recibe el aviso', strpos($n, 'shchg:') !== false, mb_substr($n, 0, 160));
}
else
{
	ok('hay un usuario libre para vincular al empleado de prueba', false);
}

echo "\n$count verificaciones, $fails fallos\n";
exit($fails > 0 ? 1 : 0);
