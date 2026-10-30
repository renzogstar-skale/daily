<?php
/**
 * Módulo 17 — permisos y aislamiento del pronóstico laboral por HTTP real (modo producción, claves API por rol).
 * Sandbox propio (copia de la BD) en el puerto 8097; nunca toca la base original.
 *
 *   php tests/dairy/http_labor_forecast.php [ruta/a/grocy.db]
 */

$root = dirname(__DIR__, 2);
define('GROCY_DATAPATH', $root . '/data');
require_once $root . '/packages/autoload.php';
require_once $root . '/data/config.php';
require_once $root . '/config-dist.php';
$dbName = (GROCY_MODE === 'demo' || GROCY_MODE === 'prerelease') ? 'grocy_' . GROCY_DEFAULT_LOCALE . '.db' : 'grocy.db';
$src = $argv[1] ?? ($root . '/data/' . $dbName);
$SANDBOX = __DIR__ . '/.labor_sandbox';
$rmrf = function ($dir) use (&$rmrf) {
	foreach (array_diff(@scandir($dir) ?: [], ['.', '..']) as $f)
	{
		is_dir("$dir/$f") ? $rmrf("$dir/$f") : @unlink("$dir/$f");
	}
	@rmdir($dir);
};
$rmrf($SANDBOX);
@mkdir($SANDBOX . '/data/viewcache', 0777, true);
$cfg = preg_replace(["/Setting\('MODE', '[a-z]+'\);/", "/Setting\('DISABLE_AUTH', (true|false)\);/"], ["Setting('MODE', 'production');", "Setting('DISABLE_AUTH', false);"], file_get_contents($root . '/data/config.php'));
file_put_contents($SANDBOX . '/data/config.php', $cfg);
if (!is_file($src) || !copy($src, $SANDBOX . '/data/grocy.db'))
{
	fwrite(STDERR, "No se pudo copiar la base de datos $src\n");
	exit(2);
}

$PORT = 8097;
$env = getenv();
$env['GROCY_DATAPATH'] = 'tests/dairy/.labor_sandbox/data';
$proc = proc_open([PHP_BINARY, '-d', 'opcache.enable=0', '-S', "127.0.0.1:$PORT", '-t', 'public', 'router.php'], [0 => ['pipe', 'r'], 1 => ['file', $SANDBOX . '/server.log', 'a'], 2 => ['file', $SANDBOX . '/server.log', 'a']], $pipes, $root, $env);
register_shutdown_function(function () use ($proc, $SANDBOX, $rmrf) {
	$st = proc_get_status($proc);
	if (PHP_OS_FAMILY === 'Windows')
	{
		exec('taskkill /F /T /PID ' . $st['pid'] . ' 2>NUL');
	}
	proc_terminate($proc);
	proc_close($proc);
	for ($i = 0; $i < 20 && is_dir($SANDBOX); $i++)
	{
		usleep(250000);
		$rmrf($SANDBOX);
	}
});
for ($i = 0; $i < 40 && !@fsockopen('127.0.0.1', $PORT); $i++)
{
	usleep(250000);
}
// Primera visita: migraciones
@file_get_contents("http://127.0.0.1:$PORT/", false, stream_context_create(['http' => ['ignore_errors' => true, 'follow_location' => 0]]));

$db = new Pdo\Sqlite('sqlite:' . $SANDBOX . '/data/grocy.db');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->createFunction('dairy_user_id', fn() => null, 0);
for ($i = 0; $i < 40 && !$db->query("SELECT 1 FROM sqlite_master WHERE name = 'labor_forecast_snapshots'")->fetchColumn(); $i++)
{
	usleep(250000);
}

// Escenario: sucursal propia, 10 tickets/día de $100 en 28 días, Ana 09–17 mañana, Beto con contrato de 2 h, Carla libre
$today = date('Y-m-d');
$D = date('Y-m-d', strtotime('+1 day'));
$db->exec("INSERT INTO branches (name, code) VALUES ('HTTP M17', 'HM17')");
$br = intval($db->lastInsertId());
$other = intval($db->query("SELECT id FROM branches WHERE id != $br ORDER BY id LIMIT 1")->fetchColumn());
$ins = $db->prepare("INSERT INTO sales (sale_number, branch_id, user_id, subtotal, total_amount, status, created_timestamp) VALUES (:n, :b, 1, 100, 100, 'completed', :t)");
$db->beginTransaction();
for ($i = 1; $i <= 56; $i++)
{
	$d = date('Y-m-d', strtotime("$today -$i day"));
	for ($h = 9; $h <= 18; $h++)
	{
		$ins->execute([':n' => "HM17-$d-$h", ':b' => $br, ':t' => sprintf('%s %02d:30:00', $d, $h)]);
	}
}
$db->commit();
$emp = [];
foreach ([['Ana', 40, 10], ['Beto', 2, 9], ['Carla', 40, 10]] as [$n, $w, $c])
{
	$db->prepare('INSERT INTO employees (name, branch_id, weekly_hours, hourly_cost) VALUES (?, ?, ?, ?)')->execute(["HTTP $n", $br, $w, $c]);
	$emp[$n] = intval($db->lastInsertId());
}
$db->prepare("INSERT INTO employee_shifts (employee_id, branch_id, shift_date, start_time, end_time, status) VALUES (?, ?, ?, '09:00', '17:00', 'planned')")->execute([$emp['Ana'], $br, $D]);
// Mínimo de 2 personas en 09–12: obliga a agregar a alguien además de ampliar a Ana
$db->exec("INSERT INTO coverage_requirements (branch_id, weekday, start_time, end_time, min_staff) VALUES ($br, 0, '09:00', '12:00', 2)");

$perm = fn($n) => intval($db->query("SELECT id FROM permission_hierarchy WHERE name = '$n'")->fetchColumn());
$admin = intval($db->query("SELECT up.user_id FROM user_permissions up JOIN permission_hierarchy p ON p.id = up.permission_id WHERE p.name = 'ADMIN' ORDER BY up.user_id LIMIT 1")->fetchColumn());
$keys = ['k_admin' => $admin];
foreach (['k_none' => ['STOCK'], 'k_kpi' => ['KPIS_VIEW'], 'k_mgr' => ['WORKFORCE_SCHEDULE_MANAGE'], 'k_ot' => ['WORKFORCE_SCHEDULE_MANAGE', 'WORKFORCE_OVERTIME_APPROVE']] as $k => $ps)
{
	$db->prepare('INSERT INTO users (username, first_name, last_name, password) VALUES (?, ?, ?, ?)')->execute([$k . '_' . uniqid(), 'Prueba', $k, password_hash(bin2hex(random_bytes(8)), PASSWORD_DEFAULT)]);
	$u = intval($db->lastInsertId());
	foreach ($ps as $p)
	{
		$db->exec("INSERT INTO user_permissions (permission_id, user_id) VALUES ({$perm($p)}, $u)");
	}
	$db->exec("INSERT INTO user_branches (user_id, branch_id) VALUES ($u, $br)");
	$keys[$k] = $u;
}
foreach ($keys as $k => $u)
{
	$db->exec("INSERT INTO api_keys (api_key, user_id, expires, key_type) VALUES ('$k', $u, '2999-01-01', 'default')");
}

$fails = 0;
$total = 0;
function call($key, $method, $path, $body = null)
{
	global $PORT;
	$ch = curl_init("http://127.0.0.1:$PORT/api$path");
	$h = ['Accept: application/json', 'GROCY-API-KEY: ' . $key];
	if ($body !== null)
	{
		$h[] = 'Content-Type: application/json';
		curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
	}
	curl_setopt_array($ch, [CURLOPT_CUSTOMREQUEST => $method, CURLOPT_HTTPHEADER => $h, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 60]);
	$raw = curl_exec($ch);
	return [curl_getinfo($ch, CURLINFO_HTTP_CODE), json_decode($raw, true) ?? $raw];
}
function check($label, $ok, $extra = '')
{
	global $fails, $total;
	$total++;
	if (!$ok)
	{
		$fails++;
	}
	echo ($ok ? '  OK   ' : '  FAIL ') . $label . ($extra !== '' && $extra !== null ? '  [' . mb_substr(is_string($extra) ? $extra : json_encode($extra, JSON_UNESCAPED_UNICODE), 0, 200) . ']' : '') . "\n";
}
$err = fn($r) => is_array($r) ? ($r['error_message'] ?? '') : substr(strval($r), 0, 120);

echo "\nPronóstico laboral por HTTP (Módulo 17)\n";
$q = "/schedule/forecast?branch_id=$br&date=$D";
[$c] = call('k_none', 'GET', $q);
check('sin permiso de personal ni indicadores → 403', $c === 403, $c);
[$c, $r] = call('k_kpi', 'GET', $q);
check('indicadores: ve el pronóstico agregado', $c === 200 && $r['staff']['recommended'] >= 1 && $r['can_manage'] === false && $r['shifts'] === [], $c . ' ' . $err($r));
[$c, $r] = call('k_kpi', 'GET', "/schedule/forecast?branch_id=$other&date=$D");
check('indicadores: otra sucursal → rechazado', $c === 403 || $c === 400, $c . ' ' . $err($r));
[$c] = call('k_kpi', 'POST', '/schedule/forecast/recommend', ['branch_id' => $br, 'date' => $D]);
check('indicadores: no puede simular', $c === 403, $c);
[$c] = call('k_kpi', 'POST', '/schedule/forecast/apply', ['branch_id' => $br, 'date' => $D, 'proposals' => [], 'reason' => 'x']);
check('indicadores: no puede aplicar', $c === 403, $c);
[$c] = call('k_kpi', 'GET', "/schedule/forecast/band?branch_id=$br&date=$D&band=1");
check('indicadores: no ve personal disponible por franja', $c === 403, $c);

[$c, $f] = call('k_mgr', 'GET', $q);
check('gerente: ve turnos y puede gestionar, sin permiso de horas extra', $c === 200 && $f['can_manage'] && !$f['can_overtime'] && count($f['shifts']) === 1);
[$c, $plan] = call('k_mgr', 'POST', '/schedule/forecast/recommend', ['branch_id' => $br, 'date' => $D, 'allow_overtime' => 1]);
check('gerente sin permiso: allow_overtime se ignora', $c === 200 && $plan['overtime_allowed'] === false && !in_array($emp['Beto'], array_column($plan['proposals'], 'employee_id')), json_encode(array_column($plan['proposals'], 'employee_name')));
[$c, $planOt] = call('k_ot', 'POST', '/schedule/forecast/recommend', ['branch_id' => $br, 'date' => $D, 'allow_overtime' => 1]);
$otProp = array_values(array_filter($planOt['proposals'] ?? [], fn($p) => $p['employee_id'] === $emp['Beto']));
check('con permiso de horas extra: propone a Beto (menor costo)', $c === 200 && $planOt['overtime_allowed'] === true && count($otProp) === 1);
[$c, $r] = call('k_mgr', 'POST', '/schedule/forecast/apply', ['branch_id' => $br, 'date' => $D, 'proposals' => $otProp, 'reason' => 'Cobertura pico']);
check('gerente no aprueba horas extra al aplicar', $c === 400 && str_contains($err($r), 'horas extra'), $err($r));
[$c, $r] = call('k_mgr', 'POST', '/schedule/forecast/apply', ['branch_id' => $other, 'date' => $D, 'proposals' => $plan['proposals'], 'reason' => 'Cobertura pico']);
check('gerente no aplica en otra sucursal', $c === 403 || $c === 400, $c . ' ' . $err($r));
[$c, $r] = call('k_mgr', 'POST', '/schedule/forecast/apply', ['branch_id' => $br, 'date' => $D, 'proposals' => $plan['proposals'], 'reason' => 'Cobertura pico']);
check('gerente aplica la recomendación sin horas extra', $c === 200 && count($r['applied']) === count($plan['proposals']), $c . ' ' . $err($r));
check('motivo y usuario en el historial', intval($db->query("SELECT COUNT(*) FROM employee_shift_changes WHERE batch_key = '" . ($r['batch'] ?? '') . "' AND reason LIKE 'Cobertura pico%' AND user_id = {$keys['k_mgr']}")->fetchColumn()) === count($plan['proposals']));
[$c, $r] = call('k_ot', 'POST', '/schedule/forecast/apply', ['branch_id' => $br, 'date' => $D, 'proposals' => [['type' => 'add', 'employee_id' => $emp['Beto'], 'start_time' => '18:00', 'end_time' => '22:00']], 'reason' => 'Cierre con horas extra']);
check('con permiso: aprueba turno con horas extra', $c === 200 && in_array('overtime', array_column($r['applied'][0]['warnings'] ?? [], 'code')), $c . ' ' . $err($r));

[$c, $r] = call('k_mgr', 'POST', '/schedule/forecast/events', ['branch_id' => 0, 'event_date' => $D, 'kind' => 'holiday', 'name' => 'Festivo red']);
check('gerente de una sucursal no crea eventos de toda la red', $c === 400 || $c === 403, $c . ' ' . $err($r));
[$c, $r] = call('k_mgr', 'POST', '/schedule/forecast/events', ['branch_id' => $br, 'event_date' => $D, 'kind' => 'promotion', 'name' => 'Promo', 'sales_factor' => 1.1]);
check('gerente crea evento de su sucursal', $c === 200 && $r['id'] > 0, $err($r));
[$c, $r] = call('k_mgr', 'POST', '/schedule/forecast/standards', ['standards' => ['restock_unit' => 0.1]]);
check('gerente de una sucursal no cambia estándares de la red', $c === 400, $err($r));
[$c, $r] = call('k_admin', 'POST', '/schedule/forecast/standards', ['standards' => ['restock_unit' => 0.1]]);
check('administrador cambia estándares', $c === 200, $err($r));
[$c, $r] = call('k_admin', 'POST', '/schedule/forecast/standards', ['standards' => ['inexistente' => 1]]);
check('estándar inexistente rechazado', $c === 400, $err($r));
[$c, $r] = call('k_mgr', 'POST', '/schedule/coverage-requests', ['branch_id' => $br, 'request_date' => $D, 'start_time' => '12:00', 'end_time' => '15:00', 'reason' => 'Falta personal']);
check('gerente crea solicitud de cobertura', $c === 200 && $r['id'] > 0, $err($r));
[$c] = call('k_kpi', 'POST', "/schedule/coverage-requests/{$r['id']}/close", ['status' => 'filled']);
check('indicadores no cierra solicitudes', $c === 403, $c);
[$c, $r] = call('k_kpi', 'GET', "/schedule/forecast/accuracy?branch_id=$br");
check('indicadores ve precisión del pronóstico', $c === 200 && array_key_exists('wape_pct', $r), $c);

// Etapa 31 — simulador de escenarios (visitar / aplica migraciones pendientes de la copia, p. ej. 0286)
$ch = curl_init("http://127.0.0.1:$PORT/");
curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 120, CURLOPT_HTTPHEADER => ['GROCY-API-KEY: k_admin']]);
curl_exec($ch);
$shiftsBefore = $db->query("SELECT id, start_time, end_time, status, version FROM employee_shifts WHERE branch_id = $br ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
[$c] = call('k_none', 'POST', '/schedule/scenarios/simulate', ['branch_id' => $br, 'date' => $D, 'params' => ['sales_pct' => 20]]);
check('sin permiso no simula escenarios', $c === 403, $c);
[$c] = call('k_kpi', 'POST', '/schedule/scenarios/simulate', ['branch_id' => $br, 'date' => $D, 'params' => ['sales_pct' => 20]]);
check('indicadores (solo lectura) no simula escenarios', $c === 403, $c);
[$c, $r] = call('k_mgr', 'POST', '/schedule/scenarios/simulate', ['branch_id' => $br, 'date' => $D, 'params' => ['sales_pct' => 20, 'absent_staff' => 1]]);
check('gerente simula +20 % ventas y 1 ausente', $c === 200 && $r['scenario']['projected_sales'] > $r['base']['projected_sales'] && $r['scenario']['scheduled_staff'] <= $r['base']['scheduled_staff'], $err($r));
[$c, $r] = call('k_mgr', 'POST', '/schedule/scenarios/simulate', ['branch_id' => $br, 'date' => $D, 'params' => ['sales_pct' => 999]]);
check('parámetro fuera de rango → 400', $c === 400, $err($r));
[$c, $r] = call('k_mgr', 'POST', '/schedule/scenarios/simulate', ['branch_id' => $other, 'date' => $D, 'params' => ['sales_pct' => 20]]);
check('gerente no simula en otra sucursal', $c === 403 || $c === 400, $c . ' ' . $err($r));
[$c, $r] = call('k_mgr', 'POST', '/schedule/scenarios', ['branch_id' => $br, 'date' => $D, 'name' => 'Promo fuerte', 'params' => ['sales_pct' => 30]]);
check('gerente guarda escenario', $c === 200 && $r['id'] > 0, $err($r));
$scId = $r['id'] ?? 0;
[$c, $r] = call('k_mgr', 'GET', "/schedule/scenarios?branch_id=$br");
check('gerente lista escenarios guardados', $c === 200 && ($r[0]['name'] ?? '') === 'Promo fuerte', $err($r));
$db->exec("INSERT INTO labor_scenarios (branch_id, scenario_date, name, params_json, result_json) VALUES ($other, '$D', 'Ajeno', '{}', '{}')");
$foreign = intval($db->lastInsertId());
[$c] = call('k_mgr', 'POST', "/schedule/scenarios/$foreign/delete");
check('gerente no elimina escenario de otra sucursal', $c === 403 || $c === 400, $c);
[$c] = call('k_mgr', 'POST', "/schedule/scenarios/$scId/delete");
check('gerente elimina su escenario', $c === 200 || $c === 204, $c);
$shiftsAfter = $db->query("SELECT id, start_time, end_time, status, version FROM employee_shifts WHERE branch_id = $br ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
check('simular/guardar escenarios no modifica turnos', $shiftsAfter === $shiftsBefore);

$log = @file_get_contents($SANDBOX . '/server.log') ?: '';
check('sin errores PHP en el servidor', !preg_match('/PHP (Fatal|Warning|Notice|Deprecated)/', $log), substr($log, 0, 300));

echo "\n$total verificaciones, $fails fallos\n";
exit($fails > 0 ? 1 : 0);
