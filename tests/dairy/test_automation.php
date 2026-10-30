<?php
/**
 * Motor de automatización (Fase B): SI evento + condición → ENTONCES acción, con idempotencia, alcance, horario,
 * reintentos e historial; enlace con el Centro de Alertas (alerta creada / escalada). Fase A: empleados con cuenta.
 *
 *   php tests/dairy/test_automation.php [ruta/a/grocy.db]
 */

use Grocy\Services\AlertCenterService;
use Grocy\Services\AutomationEngineService;

require __DIR__ . '/bootstrap.php';

$auto = AutomationEngineService::GetInstance();
$alerts = AlertCenterService::GetInstance();
$uid = GROCY_USER_ID;

echo "\n1. Estructura y datos base\n";
ok('tabla de ejecuciones', (bool) $val("SELECT 1 FROM sqlite_master WHERE name = 'automation_executions'"));
ok('historial de cambios de automatizaciones', (bool) $val("SELECT 1 FROM sqlite_master WHERE name = 'automation_rule_changes'"));
ok('8 automatizaciones base con código', intval($val("SELECT COUNT(*) FROM automation_rules WHERE code IN ('INV_RECOUNT','INV_PHANTOM','QUA_FEFO','WASTE_HIGH','OPS_DISPATCH','STAFF_COVERAGE','SALES_DROP','ESC_CRITICAL')")) === 8);
if (GROCY_MODE === 'demo')
{
	ok('empleados activos con cuenta propia', intval($val('SELECT COUNT(*) FROM employees WHERE active = 1 AND user_id IS NULL')) === 0, $val('SELECT COUNT(*) FROM employees WHERE user_id IS NOT NULL') . ' vinculados');
	ok('cuentas de empleados sin ADMIN', intval($val("SELECT COUNT(*) FROM users u JOIN user_permissions_resolved p ON p.user_id = u.id AND p.permission_name = 'ADMIN' WHERE u.username LIKE 'emp%'")) === 0);
}

echo "\n2. Condiciones\n";
ok('igualdad', $auto->Matches(['rule_code' => 'x'], ['rule_code' => 'x']));
ok('lista', $auto->Matches(['rule_code' => ['a', 'b']], ['rule_code' => 'b']) && !$auto->Matches(['rule_code' => ['a', 'b']], ['rule_code' => 'c']));
ok('numérica >= y <', $auto->Matches(['value' => '>=2'], ['value' => 2]) && !$auto->Matches(['value' => '<2'], ['value' => 2]));
ok('campo ausente no cumple', !$auto->Matches(['value' => '>1'], []));
ok('distinto', $auto->Matches(['severity' => '!=info'], ['severity' => 'danger']));

echo "\n3. Alerta creada → tarea con responsable (idempotente)\n";
$db->exec('UPDATE automation_rules SET is_active = 0');
$branch = intval($val('SELECT id FROM branches WHERE active = 1 ORDER BY id LIMIT 1'));
$other = intval($val("SELECT id FROM branches WHERE active = 1 AND id <> $branch ORDER BY id LIMIT 1"));
$rid = $auto->SaveRule(null, ['name' => 'Prueba inventario → tarea', 'event_trigger' => 'alert_created', 'condition_json' => '{"category": "inventory", "value": ">=2"}',
	'action_type' => 'create_task', 'action_payload_json' => '{"title": "Reconteo: {title}", "due_hours": 4, "task_type": "recount"}', 'priority' => 'P2', 'is_active' => 1, 'owner_user_id' => $uid], $uid);
$ridOther = $auto->SaveRule(null, ['name' => 'Prueba otra sucursal', 'event_trigger' => 'alert_created', 'condition_json' => '{"category": "inventory"}',
	'action_type' => 'create_task', 'action_payload_json' => '{}', 'is_active' => 1, 'branch_id' => $other, 'owner_user_id' => $uid], $uid);
$a = $alerts->Create(['title' => 'Prueba: diferencia de 2 audífonos', 'category' => 'inventory', 'branch_id' => $branch, 'value' => 2, 'severity' => 'warning'], $uid);
$aid = intval($a['id']);
$ex = $db->query("SELECT * FROM automation_executions WHERE rule_id = $rid")->fetchAll(PDO::FETCH_ASSOC);
ok('una ejecución correcta', count($ex) === 1 && $ex[0]['status'] === 'success', $ex[0]['error'] ?? '');
$task = $db->query("SELECT * FROM workforce_store_tasks WHERE source_type = 'alert' AND source_id = $aid")->fetchAll(PDO::FETCH_ASSOC);
ok('tarea ligada a la alerta con responsable y sucursal', count($task) === 1 && intval($task[0]['assigned_user_id']) === $uid && intval($task[0]['branch_id']) === $branch && str_starts_with($task[0]['title'], 'Reconteo: '));
ok('bitácora de la alerta registra la automatización', (bool) $val("SELECT 1 FROM dairy_alert_events WHERE alert_id = $aid AND action = 'automation'"));
ok('fuera de alcance: la regla de otra sucursal no se ejecuta', intval($val("SELECT COUNT(*) FROM automation_executions WHERE rule_id = $ridOther")) === 0);
$again = $auto->OnAlert('alert_created', $aid);
ok('reprocesar el mismo evento no duplica', ($again[0]['status'] ?? '') === 'duplicate' && intval($val("SELECT COUNT(*) FROM workforce_store_tasks WHERE source_type = 'alert' AND source_id = $aid")) === 1);
$small = $alerts->Create(['title' => 'Prueba: diferencia de 1 unidad', 'category' => 'inventory', 'branch_id' => $branch, 'value' => 1], $uid);
ok('condición numérica no cumplida: sin ejecución', intval($val("SELECT COUNT(*) FROM automation_executions WHERE rule_id = $rid AND entity_id = " . intval($small['id']))) === 0);

echo "\n4. Fallas, reintentos y horario\n";
$db->exec('UPDATE automation_rules SET is_active = 0');
$db->exec("INSERT INTO automation_rules (name, event_trigger, condition_json, action_type, action_payload_json, is_active, max_retries, created_at) VALUES ('Prueba acción inválida', 'alert_created', '{\"category\": \"quality\"}', 'no_existe', '{}', 1, 2, datetime('now'))");
$bad = intval($db->lastInsertId());
$alerts->Create(['title' => 'Prueba: calidad con acción inválida', 'category' => 'quality', 'branch_id' => $branch], $uid);
ok('primer intento fallido queda en reintento', $val("SELECT status FROM automation_executions WHERE rule_id = $bad") === 'retrying');
$auto->RetryPending();
ok('agotados los intentos queda fallida con error', $val("SELECT status || '|' || attempts FROM automation_executions WHERE rule_id = $bad") === 'failed|2' && (bool) $val("SELECT error FROM automation_executions WHERE rule_id = $bad"));
expectError('no se reintenta manualmente una correcta', fn() => $auto->Retry(intval($ex[0]['id'])));

$now = time();
$from = date('H:i', $now + 7200);
$to = date('H:i', $now + 10800);
$db->exec('UPDATE automation_rules SET is_active = 0');
$hid = $auto->SaveRule(null, ['name' => 'Prueba en horario', 'event_trigger' => 'alert_created', 'condition_json' => '{"category": "sales"}', 'action_type' => 'notify',
	'action_payload_json' => '{"to": "managers", "title": "Aviso: {title}"}', 'is_active' => 1, 'active_from' => $from, 'active_to' => $to], $uid);
$sales = $alerts->Create(['title' => 'Prueba: ventas fuera de horario', 'category' => 'sales', 'branch_id' => $branch], $uid);
ok('fuera del horario queda diferida', $val("SELECT status FROM automation_executions WHERE rule_id = $hid") === 'deferred');
$auto->SaveRule($hid, ['active_from' => '', 'active_to' => ''], $uid);
$auto->RetryPending();
ok('en horario se ejecuta y notifica', $val("SELECT status FROM automation_executions WHERE rule_id = $hid") === 'success'
	&& (bool) $val("SELECT 1 FROM dairy_alert_notifications WHERE alert_id = " . intval($sales['id']) . " AND kind = 'automation'"));

echo "\n5. Escalamiento → prioridad\n";
$db->exec("UPDATE automation_rules SET is_active = CASE WHEN code = 'ESC_CRITICAL' THEN 1 ELSE 0 END");
$crit = $alerts->Create(['title' => 'Prueba: crítica a escalar', 'category' => 'operations', 'branch_id' => $branch, 'severity' => 'danger', 'priority' => 'P3', 'detail' => 'Prueba de prioridad justificada'], $uid);
$cid = intval($crit['id']);
$alerts->Escalate($cid, 'Prueba de escalamiento manual', $uid);
ok('alerta crítica escalada pasa a P1', $val("SELECT priority FROM dairy_alerts WHERE id = $cid") === 'P1', $val("SELECT error FROM automation_executions e JOIN automation_rules r ON r.id = e.rule_id WHERE r.code = 'ESC_CRITICAL' AND e.entity_id = $cid"));

echo "\n6. Configuración\n";
expectError('JSON de condición no válido', fn() => $auto->SaveRule(null, ['name' => 'Inválida', 'event_trigger' => 'alert_created', 'condition_json' => '{x', 'action_type' => 'notify'], $uid));
expectError('horario incompleto', fn() => $auto->SaveRule($rid, ['active_from' => '08:00', 'active_to' => ''], $uid));
$auto->SaveRule($rid, ['cooldown_minutes' => 30], $uid);
ok('historial de cambios registra el campo', str_contains((string) $val("SELECT changes FROM automation_rule_changes WHERE rule_id = $rid ORDER BY id DESC LIMIT 1"), 'cooldown_minutes'));
ok('evento de merma sin reglas no falla', is_array($auto->TriggerEvent('waste_approved', ['waste_record_id' => 1, 'branch_id' => $branch])));

echo "\n$count verificaciones, $fails fallidas\n";
exit($fails > 0 ? 1 : 0);
