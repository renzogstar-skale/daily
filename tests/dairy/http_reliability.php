<?php
/**
 * Confiabilidad por HTTP con concurrencia REAL (Etapa 26): 4 servidores PHP independientes sobre una COPIA de la base
 * de datos (nunca toca la original), autenticación activa y usuarios de prueba por rol (gerente, supervisor S1,
 * 2 empleados S1, 1 empleado S2). Cubre autenticación, permisos y aislamiento por sucursal, horarios (nocturnos,
 * conflictos, edición simultánea, publicación, fichaje simultáneo), tareas, escáner, mermas (validaciones, evidencia,
 * idempotencia por cuerpo y por cabecera X-Idempotency-Key, reenvío tras reconexión, aprobación/rechazo simultáneos,
 * sobreconsumo), revocación de permisos en caliente y conciliación kardex = stock.
 *
 *   php tests/dairy/http_reliability.php [ruta/a/grocy.db]
 *
 * Requiere puertos 8092–8095 libres. Crea y borra tests/dairy/.http_sandbox.
 */

$root = dirname(__DIR__, 2);
define('GROCY_DATAPATH', $root . '/data');
require_once $root . '/packages/autoload.php';
require_once $root . '/data/config.php';
require_once $root . '/config-dist.php';
if (GROCY_TIMEZONE !== '')
{
	date_default_timezone_set(GROCY_TIMEZONE);
}
$dbName = (GROCY_MODE === 'demo' || GROCY_MODE === 'prerelease') ? 'grocy_' . GROCY_DEFAULT_LOCALE . '.db' : 'grocy.db';
$src = $argv[1] ?? ($root . '/data/' . $dbName);
$SANDBOX = __DIR__ . '/.http_sandbox';
$rmrf = function ($dir) use (&$rmrf) {
	foreach (array_diff(@scandir($dir) ?: [], ['.', '..']) as $f)
	{
		is_dir("$dir/$f") ? $rmrf("$dir/$f") : @unlink("$dir/$f");
	}
	@rmdir($dir);
};
$rmrf($SANDBOX);
@mkdir($SANDBOX . '/data', 0777, true);
$cfg = file_get_contents($root . '/data/config.php');
$cfg = preg_replace(["/Setting\('MODE', '[a-z]+'\);/", "/Setting\('DISABLE_AUTH', (true|false)\);/"], ["Setting('MODE', 'production');", "Setting('DISABLE_AUTH', false);"], $cfg);
file_put_contents($SANDBOX . '/data/config.php', $cfg);
if (!is_file($src) || !copy($src, $SANDBOX . '/data/grocy.db'))
{
	fwrite(STDERR, "No se pudo copiar la base de datos $src\n");
	exit(2);
}
if (function_exists('imagecreatetruecolor'))
{
	imagejpeg(imagecreatetruecolor(16, 16), $SANDBOX . '/ev.jpg');
}
else
{
	file_put_contents($SANDBOX . '/ev.jpg', base64_decode('/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAAgGBgcGBQgHBwcJCQgKDBQNDAsLDBkSEw8UHRofHh0aHBwgJC4nICIsIxwcKDcpLDAxNDQ0Hyc5PTgyPC4zNDL/wAALCAABAAEBAREA/8QAFAABAAAAAAAAAAAAAAAAAAAACf/EABQQAQAAAAAAAAAAAAAAAAAAAAD/2gAIAQEAAD8AKp//2Q=='));
}

// Usuarios por rol, permisos, sucursales y claves API en la copia
$seed = new Pdo\Sqlite('sqlite:' . $SANDBOX . '/data/grocy.db');
$seed->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$seed->createFunction('dairy_user_id', fn() => null, 0);
$perm = fn($n) => intval($seed->query("SELECT id FROM permission_hierarchy WHERE name = '$n'")->fetchColumn());
$U = ['M' => intval($seed->query("SELECT up.user_id FROM user_permissions up JOIN permission_hierarchy p ON p.id = up.permission_id WHERE p.name = 'ADMIN' ORDER BY up.user_id LIMIT 1")->fetchColumn())];
$roles = ['A' => ['WASTE_REPORT', 'SCAN_OPERATE'], 'B' => ['WASTE_REPORT', 'SCAN_OPERATE'], 'S' => ['WASTE_APPROVE', 'ALERTS_MANAGE', 'TASKS', 'WASTE_REPORT', 'SCAN_OPERATE'], 'C' => ['WASTE_REPORT', 'SCAN_OPERATE']];
foreach ($roles as $k => $ps)
{
	$seed->prepare('INSERT INTO users (username, first_name, last_name, password) VALUES (?, ?, ?, ?)')->execute(['http_' . strtolower($k) . '_' . uniqid(), 'Prueba', $k, password_hash(bin2hex(random_bytes(8)), PASSWORD_DEFAULT)]);
	$U[$k] = intval($seed->lastInsertId());
	foreach ($ps as $p)
	{
		$seed->exec("INSERT INTO user_permissions (permission_id, user_id) VALUES ({$perm($p)}, {$U[$k]})");
	}
	$seed->exec("INSERT INTO user_branches (user_id, branch_id) VALUES ({$U[$k]}, " . ($k === 'C' ? 2 : 1) . ')');
}
$keys = ['k_mgr' => $U['M'], 'k_empA' => $U['A'], 'k_empB' => $U['B'], 'k_sup' => $U['S'], 'k_emp2' => $U['C'], 'k_expired' => $U['A']];
foreach ($keys as $k => $u)
{
	$seed->exec("INSERT INTO api_keys (api_key, user_id, expires, key_type) VALUES ('$k', $u, '" . ($k === 'k_expired' ? '2000-01-01' : '2999-01-01') . "', 'default')");
}
// Producto con código de barras, precio bajo (merma de 1 u. bajo WASTE_APPROVAL_MIN_COST) y ≥ 40 u. en una ubicación de S1
$FIX = $seed->query("SELECT s.product_id, s.location_id, (SELECT barcode FROM product_barcodes b WHERE b.product_id = s.product_id ORDER BY id LIMIT 1) AS bc
	FROM stock s JOIN locations l ON l.id = s.location_id JOIN products p ON p.id = s.product_id
	WHERE l.branch_id = 1 AND p.active = 1 AND EXISTS (SELECT 1 FROM product_barcodes b WHERE b.product_id = s.product_id)
		AND IFNULL((SELECT price FROM cache__products_last_purchased c WHERE c.product_id = s.product_id), 0) BETWEEN 0.5 AND 10
		AND NOT EXISTS (SELECT 1 FROM waste_records w WHERE w.product_id = s.product_id AND w.status = 'pending_approval')
	GROUP BY s.product_id, s.location_id HAVING SUM(s.amount) >= 40 ORDER BY SUM(s.amount) DESC LIMIT 1")->fetch(PDO::FETCH_NUM);
$high = "lower(IFNULL(risk_level, '')) IN ('alto', 'alta', 'crítico', 'crítica', 'critico', 'critica')";
$R_EV = intval($seed->query('SELECT id FROM waste_reasons WHERE active = 1 AND requires_evidence = 1 AND requires_approval = 1 ORDER BY id LIMIT 1')->fetchColumn());
$R_FREE = intval($seed->query("SELECT id FROM waste_reasons WHERE active = 1 AND requires_evidence = 0 AND requires_approval = 0 AND NOT ($high) ORDER BY id LIMIT 1")->fetchColumn());
$R_HIGH = intval($seed->query("SELECT id FROM waste_reasons WHERE active = 1 AND requires_evidence = 0 AND (requires_approval = 1 OR $high) ORDER BY ($high) DESC, id LIMIT 1")->fetchColumn());
// Ubicación en S2 para el traslado (la BD demo puede no tenerla)
if (!$seed->query('SELECT id FROM locations WHERE branch_id = 2 AND active = 1 AND IFNULL(is_transit, 0) = 0')->fetchColumn())
{
	$seed->exec("INSERT INTO locations (name, branch_id) VALUES ('Prueba S2 " . uniqid() . "', 2)");
}
$seed = null;
if (!$FIX || !$R_EV || !$R_FREE || !$R_HIGH || !$U['M'])
{
	fwrite(STDERR, "Datos maestros insuficientes (producto con código y stock en S1, causales con/sin aprobación y evidencia, usuario ADMIN)\n");
	$rmrf($SANDBOX);
	exit(2);
}

// 4 servidores independientes = peticiones realmente simultáneas sobre la misma BD
$procs = [];
$env = getenv();
$env['GROCY_DATAPATH'] = 'tests/dairy/.http_sandbox/data';
foreach ([8092, 8093, 8094, 8095] as $port)
{
	$log = $SANDBOX . "/server$port.log";
	$procs[] = proc_open([PHP_BINARY, '-S', "127.0.0.1:$port", '-t', 'public', 'router.php'], [0 => ['pipe', 'r'], 1 => ['file', $log, 'a'], 2 => ['file', $log, 'a']], $pipes, $root, $env);
}
register_shutdown_function(function () use (&$procs, $SANDBOX, $rmrf) {
	foreach ($procs as $p)
	{
		$st = proc_get_status($p);
		if (PHP_OS_FAMILY === 'Windows')
		{
			exec('taskkill /F /T /PID ' . $st['pid'] . ' 2>NUL');
		}
		proc_terminate($p);
		proc_close($p);
	}
	// Cierra las conexiones a la copia (las funciones flecha capturan $db) para poder borrarla en Windows
	foreach (array_keys($GLOBALS) as $k)
	{
		if ($k !== 'rmrf' && ($GLOBALS[$k] instanceof \PDO || $GLOBALS[$k] instanceof \Closure))
		{
			$GLOBALS[$k] = null;
		}
	}
	gc_collect_cycles();
	for ($i = 0; $i < 20 && is_dir($SANDBOX); $i++)
	{
		usleep(250000);
		$rmrf($SANDBOX);
	}
});
$ready = false;
for ($i = 0; $i < 40 && !$ready; $i++)
{
	usleep(250000);
	$ready = true;
	foreach ([8092, 8093, 8094, 8095] as $port)
	{
		$ready = $ready && @file_get_contents("http://127.0.0.1:$port/api/system/info", false, stream_context_create(['http' => ['ignore_errors' => true, 'timeout' => 2]])) !== false;
	}
}
if (!$ready)
{
	fwrite(STDERR, "Los servidores de prueba no respondieron (¿puertos 8092–8095 ocupados?)\n");
	exit(2);
}
// Aplica migraciones pendientes en la copia (como al abrir la app)
@file_get_contents('http://127.0.0.1:8092/', false, stream_context_create(['http' => ['header' => 'GROCY-API-KEY: k_mgr', 'ignore_errors' => true, 'follow_location' => 0]]));

const PORTS = [8092, 8093, 8094, 8095];
$fails = 0; $total = 0; $section = '';
function req($key, $method, $path, $body = null, $port = 8092, $hdr = [])
{
	$ch = curl_init("http://127.0.0.1:$port/api$path");
	$h = array_merge(['Accept: application/json'], $hdr);
	if ($key !== null) $h[] = 'GROCY-API-KEY: ' . $key;
	if ($body !== null) { $h[] = 'Content-Type: application/json'; curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body)); }
	curl_setopt_array($ch, [CURLOPT_CUSTOMREQUEST => $method, CURLOPT_HTTPHEADER => $h, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 60]);
	return $ch;
}
function call($key, $method, $path, $body = null)
{
	$ch = req($key, $method, $path, $body);
	$raw = curl_exec($ch);
	return [curl_getinfo($ch, CURLINFO_HTTP_CODE), json_decode($raw, true) ?? $raw];
}
// Lanza N peticiones a la vez repartidas en los 4 servidores
function parallel(array $reqs)
{
	$mh = curl_multi_init(); $hs = [];
	foreach ($reqs as $i => $r) { $hs[$i] = req($r[0], $r[1], $r[2], $r[3] ?? null, PORTS[$i % count(PORTS)], $r[4] ?? []); curl_multi_add_handle($mh, $hs[$i]); }
	do { curl_multi_exec($mh, $run); curl_multi_select($mh); } while ($run > 0);
	$out = [];
	foreach ($hs as $i => $h) { $out[$i] = [curl_getinfo($h, CURLINFO_HTTP_CODE), json_decode(curl_multi_getcontent($h), true)]; }
	return $out;
}
function check($label, $ok, $extra = '')
{
	global $fails, $total;
	$total++;
	if (!$ok) $fails++;
	echo ($ok ? '  OK   ' : '  FAIL ') . $label . ($extra !== '' && $extra !== null ? '  [' . mb_substr(is_string($extra) ? $extra : json_encode($extra, JSON_UNESCAPED_UNICODE), 0, 220) . ']' : '') . "\n";
}
function head($t) { echo "\n$t\n"; }
function err($r) { return is_array($r) ? ($r['error_message'] ?? json_encode($r, JSON_UNESCAPED_UNICODE)) : (string) $r; }
$db = new PDO('sqlite:' . $SANDBOX . '/data/grocy.db');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$val = fn($sql) => $db->query($sql)->fetchColumn();
$today = date('Y-m-d'); $monday = date('Y-m-d', strtotime('monday this week')); $tomorrow = date('Y-m-d', strtotime('+1 day'));
[$P, $LOC, $BC] = $FIX;
$stock = fn() => floatval($val("SELECT IFNULL(SUM(amount),0) FROM stock WHERE product_id = $P"));
$photo = function ($key) use ($SANDBOX) {
	$ch = curl_init('http://127.0.0.1:8092/api/waste/upload-photo');
	curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_HTTPHEADER => ['GROCY-API-KEY: ' . $key], CURLOPT_POSTFIELDS => ['photo' => new CURLFile($SANDBOX . '/ev.jpg', 'image/jpeg', 'ev.jpg')], CURLOPT_RETURNTRANSFER => true]);
	return json_decode(curl_exec($ch), true)['relative_url'] ?? null;
};
$waste = fn($key, $amount, $reason, $extra = []) => call($key, 'POST', '/waste/report', array_merge(['product_id' => $P, 'amount' => $amount, 'waste_reason_id' => $reason, 'branch_id' => 1, 'location_id' => $LOC, 'notes' => 'auditoría', 'idempotency_key' => 'k-' . uniqid('', true)], $extra));

$stockBase = $stock(); $logBase = floatval($val("SELECT IFNULL(SUM(amount),0) FROM stock_log WHERE product_id = $P AND undone = 0"));
// ------------------------------------------------------------------ Autenticación
head('A. Autenticación y sesiones');
[$c] = call(null, 'GET', '/m/me'); check('sin credenciales → 401', $c == 401, $c);
[$c] = call('k_inexistente', 'GET', '/m/me'); check('clave inválida → 401', $c == 401, $c);
[$c] = call('k_expired', 'GET', '/m/me'); check('clave vencida → 401', $c == 401, $c);
[$c, $r] = call('k_empA', 'GET', '/objects/api_keys'); check('empleado no lee api_keys (genérico /objects)', $c == 403 || $c == 400, "$c " . err($r));
[$c, $r] = call('k_empA', 'GET', '/users'); check('empleado no lista usuarios', $c == 403, "$c");
[$c, $r] = call('k_empA', 'POST', '/users', ['username' => 'x' . uniqid(), 'password' => 'x']); check('empleado no crea usuarios', $c == 403, "$c");
[$c, $r] = call('k_empA', 'GET', '/objects/stock'); check('empleado sin STOCK no lee /objects/stock', $c == 403 || $c == 400, "$c");
[$c, $r] = call('k_empA', 'PUT', '/objects/products/' . $P, ['name' => 'hack']); check('empleado no edita productos', $c == 403, "$c");

[$c, $r] = call('k_empA', 'GET', '/objects/sessions'); check('empleado no lee sesiones', $c == 403 || $c == 400, "$c");
[$c, $r] = call('k_emp2', 'GET', '/waste/records'); check('empleado S2 solo ve mermas de S2 (o no lista)', $c == 403 || count(array_filter($r['records'] ?? (is_array($r) ? $r : []), fn($x) => is_array($x) && intval($x['branch_id'] ?? 2) !== 2)) === 0, $c);

// ------------------------------------------------------------------ Horarios
head('B. Horarios: alta, conflictos, nocturno, publicación, permisos');
$emp = [];
foreach ([['k_mgr', 'Jorge López', 1, $U['A']], ['k_mgr', 'María Silva', 1, $U['B']], ['k_mgr', 'Ana Pérez', 2, $U['C']]] as $e)
{
	[$c, $r] = call($e[0], 'POST', '/schedule/employees', ['name' => $e[1], 'branch_id' => $e[2], 'user_id' => $e[3], 'position' => 'Auxiliar', 'weekly_hours' => 40]);
	$emp[$e[3]] = intval($r['id'] ?? 0);
}
check('3 empleados creados', count(array_filter($emp)) === 3, $emp);
[$c, $r] = call('k_mgr', 'POST', '/schedule/employees', ['name' => 'Otro', 'branch_id' => 1, 'user_id' => $U['A']]); check('usuario ya vinculado a otro empleado → error', $c >= 400, err($r));
$sh = fn($e, $d, $s, $en, $b = 30) => call('k_mgr', 'POST', '/schedule/shifts', ['employee_id' => $e, 'shift_date' => $d, 'start_time' => $s, 'end_time' => $en, 'break_minutes' => $b]);
$now = date('H:i'); $startA = date('H:i', strtotime('-30 minutes')); $endA = date('H:i', strtotime('+7 hours'));
[$c, $r] = $sh($emp[$U['A']], $today, $startA, $endA); $shiftA = intval($r['id'] ?? 0); check("turno A hoy $startA-$endA", $shiftA > 0, $r);
[$c, $r] = $sh($emp[$U['B']], $today, $startA, $endA); $shiftB = intval($r['id'] ?? 0); check('turno B hoy', $shiftB > 0, $r);
[$c, $r] = $sh($emp[$U['A']], $today, date('H:i', strtotime('+1 hour')), date('H:i', strtotime('+3 hours'))); check('turno solapado → rechazado', $c >= 400 || !empty($r['conflicts']), "$c " . err($r));
[$c, $r] = $sh($emp[$U['A']], $tomorrow, '22:00', '06:00', 30); $night = intval($r['id'] ?? 0); check('turno nocturno 22:00-06:00 aceptado', $night > 0, $r);
$nightHours = $db->query("SELECT shift_date, start_time, end_time FROM employee_shifts WHERE id = $night")->fetch(PDO::FETCH_ASSOC);
check('nocturno guarda fecha de inicio', ($nightHours['shift_date'] ?? '') === $tomorrow, $nightHours);
[$c, $r] = $sh($emp[$U['A']], date('Y-m-d', strtotime('+2 day')), '05:00', '08:00'); check('turno que solapa la salida del nocturno → conflicto', $c >= 400 || !empty($r['conflicts']) || !empty($r['warnings']), "$c " . err($r));
[$c, $r] = $sh($emp[$U['A']], $tomorrow, '10:00', '11:00', 60); check('descanso ≥ duración → rechazado', $c >= 400, err($r));
[$c, $r] = $sh($emp[$U['A']], $tomorrow, '25:00', '11:00'); check('hora inválida → rechazado', $c >= 400, err($r));
[$c, $r] = call('k_empA', 'POST', '/schedule/shifts', ['employee_id' => $emp[$U['A']], 'shift_date' => $tomorrow, 'start_time' => '12:00', 'end_time' => '13:00']); check('empleado no crea turnos', $c == 403, "$c");
[$c, $r] = call('k_empA', 'POST', '/schedule/week/publish', ['branch_id' => 1, 'week' => $monday]); check('empleado no publica', $c == 403, "$c");
[$c, $r] = call('k_mgr', 'POST', '/schedule/shifts', ['employee_id' => $emp[$U['C']], 'shift_date' => $today, 'start_time' => '08:00', 'end_time' => '16:00']); check('manager (ADMIN) sí agenda en S2', $c == 200, err($r));
// Edición concurrente del mismo turno (versión optimista)
$v = intval($val("SELECT version FROM employee_shifts WHERE id = $night"));
$res = parallel([
	['k_mgr', 'POST', '/schedule/shifts', ['id' => $night, 'version' => $v, 'employee_id' => $emp[$U['A']], 'shift_date' => $tomorrow, 'start_time' => '21:00', 'end_time' => '05:00', 'break_minutes' => 30]],
	['k_mgr', 'POST', '/schedule/shifts', ['id' => $night, 'version' => $v, 'employee_id' => $emp[$U['A']], 'shift_date' => $tomorrow, 'start_time' => '23:00', 'end_time' => '07:00', 'break_minutes' => 30]],
]);
$okN = count(array_filter($res, fn($x) => $x[0] == 200));
check('edición simultánea con misma versión: solo 1 gana', $okN === 1, array_map(fn($x) => $x[0] . ' ' . err($x[1]), $res));
check('historial registra cada cambio del turno', intval($val("SELECT COUNT(*) FROM employee_shift_changes WHERE shift_id = $night")) >= 2);
[$c, $r] = call('k_mgr', 'POST', '/schedule/week/publish', ['branch_id' => 1, 'week' => $monday]); check('publicar semana S1', $c == 200 && $r['published'] >= 2, $r);
if (date('w') == 0) { call('k_mgr', 'POST', '/schedule/week/publish', ['branch_id' => 1, 'week' => date('Y-m-d', strtotime('monday next week'))]); }
[$c, $r] = call('k_empA', 'GET', '/m/schedule'); check('empleado A ve solo sus turnos', $c == 200 && count(array_filter($r['data']['shifts'] ?? [], fn($s) => intval($s['employee_id']) !== $emp[$U['A']])) === 0, count($r['data']['shifts'] ?? []));
[$c, $r] = call('k_empA', 'GET', '/schedule/week?branch_id=1&week=' . $monday); $j = json_encode($r); check('empleado ve la semana sin costos ni borradores', $c == 403 || (strpos($j, 'hourly_cost') === false && strpos($j, 'labor_cost') === false && strpos($j, '"status":"planned"') === false), "$c");
[$c, $r] = call('k_empA', 'POST', '/schedule/clock-in', ['source' => 'mobile']); check('empleado A ficha entrada', $c == 200, err($r));
[$c, $r] = call('k_empA', 'POST', '/schedule/clock-in', ['source' => 'mobile']); check('doble fichaje de entrada → rechazado', $c >= 400, err($r));
$res = parallel([['k_empB', 'POST', '/schedule/clock-in', ['source' => 'mobile']], ['k_empB', 'POST', '/schedule/clock-in', ['source' => 'mobile']], ['k_empB', 'POST', '/schedule/clock-in', ['source' => 'mobile']]]);
check('fichaje simultáneo (3 toques): 1 sola asistencia abierta', intval($val("SELECT COUNT(*) FROM employee_attendance WHERE employee_id = {$emp[$U['B']]} AND clock_out IS NULL")) === 1, array_map(fn($x) => $x[0], $res));
[$c, $r] = call('k_empA', 'POST', '/schedule/clock-in', ['employee_id' => $emp[$U['B']], 'source' => 'mobile']); check('empleado no ficha por otro', $c >= 400 || intval($val("SELECT COUNT(*) FROM employee_attendance WHERE employee_id = {$emp[$U['B']]}")) === 1, "$c " . err($r));
[$c, $r] = call('k_mgr', 'GET', "/schedule/timesheet?branch_id=1&from=$monday&to=" . date('Y-m-d', strtotime($monday . ' +6 day')));
check('hoja de horas separa programadas / trabajadas', $c == 200 && strpos(json_encode($r), 'scheduled') !== false && strpos(json_encode($r), 'worked') !== false, "$c " . substr(json_encode($r), 0, 160));
[$c, $r] = call('k_mgr', 'GET', '/system/time'); check('PHP y SQLite usan la misma hora local', $c == 200 && substr($r['time_local'], 0, 16) === substr($r['time_local_sqlite3'], 0, 16), $r);

// ------------------------------------------------------------------ Tareas
head('C. Tareas: asignación directa del supervisor');
[$c, $r] = call('k_sup', 'POST', '/m/tasks', ['employee_id' => $emp[$U['A']], 'title' => 'Reponer góndola de leches']); $task = intval($r['id'] ?? 0); check('supervisor asigna tarea directa', $c == 200 && $task > 0, err($r));
check('tarea ligada al turno publicado de hoy', intval($val("SELECT shift_id FROM workforce_store_tasks WHERE id = $task")) === $shiftA);
[$c, $r] = call('k_empA', 'POST', '/m/tasks', ['employee_id' => $emp[$U['B']], 'title' => 'xxx']); check('empleado no asigna tareas', $c == 403, "$c");
[$c, $r] = call('k_sup', 'POST', '/m/tasks', ['employee_id' => $emp[$U['C']], 'title' => 'Tarea en S2']); check('supervisor S1 no asigna en S2', $c == 403, "$c " . err($r));
[$c, $r] = call('k_empA', 'GET', '/m/notifications'); check('empleado A recibe aviso "Nueva tarea"', strpos(json_encode($r, JSON_UNESCAPED_UNICODE), 'Nueva tarea: Reponer') !== false);
[$c, $r] = call('k_empB', 'POST', "/m/tasks/$task/complete", ['notes' => 'hecho']); check('otro empleado no completa tarea ajena', $c == 403, "$c");
$res = parallel([['k_empA', 'POST', "/m/tasks/$task/complete", ['notes' => 'listo 1']], ['k_empA', 'POST', "/m/tasks/$task/complete", ['notes' => 'listo 2']]]);
check('completar doble simultáneo: queda 1 cierre', $val("SELECT status FROM workforce_store_tasks WHERE id = $task") === 'completed' && count(array_filter($res, fn($x) => $x[0] == 200)) >= 1, array_map(fn($x) => $x[0] . ' ' . err($x[1]), $res));

// ------------------------------------------------------------------ Escáner
head('D. Escáner');
[$c, $r] = call('k_empA', 'GET', '/m/scan?code=' . $BC); check('código válido resuelve producto', $c == 200 && intval($r['product']['id'] ?? 0) === $P);
[$c, $r] = call('k_empA', 'GET', '/m/scan?code=999999999999'); check('código inexistente: respuesta controlada (sin 500)', $c < 500 && ($c >= 400 || ($r['kind'] ?? '') !== 'product'), "$c " . substr(json_encode($r, JSON_UNESCAPED_UNICODE), 0, 120));
[$c, $r] = call('k_empA', 'GET', '/m/scan?code='); check('código vacío → error claro', $c >= 400, err($r));
[$c, $r] = call('k_empA', 'GET', '/m/scan?code=' . urlencode("' OR 1=1 --")); check('inyección en código: sin 500', $c < 500, $c);
$res = parallel(array_fill(0, 6, ['k_empA', 'GET', '/m/scan?code=' . $BC]));
check('6 lecturas repetidas simultáneas: sin efectos (solo lectura)', count(array_filter($res, fn($x) => $x[0] == 200)) === 6);

// ------------------------------------------------------------------ Mermas: validaciones
head('E. Mermas: validaciones');
file_put_contents($SANDBOX . '/x.html', '<script>alert(1)</script>');
$ch = curl_init('http://127.0.0.1:8092/api/waste/upload-photo');
curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_HTTPHEADER => ['GROCY-API-KEY: k_empA'], CURLOPT_POSTFIELDS => ['photo' => new CURLFile($SANDBOX . '/x.html', 'image/jpeg', 'foto.jpg')], CURLOPT_RETURNTRANSFER => true]);
$up = curl_exec($ch);
check('subir un HTML disfrazado de foto → rechazado', curl_getinfo($ch, CURLINFO_HTTP_CODE) == 400, $up);
check('foto real se guarda con extensión según su contenido', preg_match('/\.jpg$/', (string) $photo('k_empA')) === 1);
[$c, $r] = call('k_empA', 'POST', '/waste/report', ['product_id' => 999999, 'amount' => 1, 'waste_reason_id' => $R_FREE, 'branch_id' => 1]); check('producto inexistente → error', $c >= 400 && $c < 500, "$c " . err($r));
[$c, $r] = $waste('k_empA', 0, $R_FREE); check('cantidad 0 → error', $c >= 400, err($r));
[$c, $r] = $waste('k_empA', -3, $R_FREE); check('cantidad negativa → error', $c >= 400, err($r));
[$c, $r] = $waste('k_empA', 100000, $R_FREE); check('cantidad mayor al stock → error', $c >= 400, err($r));
[$c, $r] = $waste('k_empA', 1, 999); check('causal inexistente → error', $c >= 400, err($r));
[$c, $r] = $waste('k_empA', 1, $R_EV); check('causal con evidencia obligatoria sin foto → error', $c >= 400, err($r));
[$c, $r] = $waste('k_empA', 1, $R_FREE, ['evidence_photo_path' => '../../config.php']); check('ruta de evidencia maliciosa no se acepta', $c >= 400 || strpos((string) $val('SELECT evidence_photo_path FROM waste_records ORDER BY id DESC LIMIT 1'), '..') === false, err($r));
[$c, $r] = $waste('k_emp2', 1, $R_FREE); check('empleado de S2 no registra merma en S1', $c == 403 || $c == 400, "$c " . err($r));
[$c, $r] = $waste('k_empA', 1.5, $R_FREE, ['product_id' => $P]); check('cantidad fraccionaria en producto por unidad', true, "$c " . err($r));

// ------------------------------------------------------------------ Mermas: idempotencia y offline
head('F. Mermas: idempotencia, reintentos y offline');
$s0 = $stock(); $key = 'off-' . uniqid();
$body = ['confirm_duplicate' => true, 'product_id' => $P, 'amount' => 1, 'waste_reason_id' => $R_FREE, 'branch_id' => 1, 'location_id' => $LOC, 'notes' => 'cola offline', 'idempotency_key' => $key];
$res = parallel(array_fill(0, 5, ['k_empA', 'POST', '/waste/report', $body]));
$cnt = intval($val("SELECT COUNT(*) FROM waste_records WHERE idempotency_key = '$key'"));
check('5 reenvíos simultáneos con la misma clave → 1 registro', $cnt === 1, array_map(fn($x) => $x[0], $res));
check('stock descontado una sola vez', abs($s0 - $stock() - 1) < 0.001, "$s0 → " . $stock());
check('todas las respuestas apuntan al mismo caso', count(array_unique(array_map(fn($x) => $x[1]['waste_id'] ?? null, array_filter($res, fn($x) => $x[0] == 200)))) === 1, array_map(fn($x) => $x[1]['waste_id'] ?? err($x[1]), $res));
[$c, $r] = call('k_empA', 'POST', '/waste/report', $body); check('reintento tardío (tras reconexión) no duplica', intval($val("SELECT COUNT(*) FROM waste_records WHERE idempotency_key = '$key'")) === 1 && $c < 500, "$c");
[$c, $r] = call('k_empB', 'POST', '/waste/report', $body); check('misma clave desde otro usuario no reutiliza el caso ajeno', $c >= 400 || intval($val("SELECT COUNT(*) FROM waste_records WHERE idempotency_key = '$key' AND reported_by_user_id = {$U['B']}")) === 0, "$c " . err($r));
check('kardex (stock_log) con 1 movimiento para la merma', intval($val("SELECT COUNT(*) FROM stock_log WHERE transaction_id = (SELECT transaction_id FROM waste_records WHERE idempotency_key = '$key') AND undone = 0")) >= 1);

// Cola sin conexión: reenvío con cabecera X-Idempotency-Key (middleware) desde 4 servidores
$hk = 'hdr-' . uniqid(); $s0 = $stock();
$hb = ['confirm_duplicate' => true, 'product_id' => $P, 'amount' => 1, 'waste_reason_id' => $R_FREE, 'branch_id' => 1, 'location_id' => $LOC, 'notes' => 'cola cabecera'];
$res = parallel(array_fill(0, 4, ['k_empA', 'POST', '/waste/report', $hb, ['X-Idempotency-Key: ' . $hk]]));
check('cabecera X-Idempotency-Key, 4 envíos simultáneos → 1 merma', abs($s0 - $stock() - 1) < 0.001 && count(array_filter($res, fn($x) => $x[0] >= 500)) === 0, array_map(fn($x) => $x[0], $res));
$ch = req('k_empA', 'POST', '/waste/report', $hb, 8093, ['X-Idempotency-Key: ' . $hk]); curl_exec($ch);
check('reconexión: reenvío con la misma cabecera no vuelve a descontar', abs($s0 - $stock() - 1) < 0.001 && curl_getinfo($ch, CURLINFO_HTTP_CODE) < 300, curl_getinfo($ch, CURLINFO_HTTP_CODE));
// Registros distintos simultáneos: números de caso únicos
$res = parallel(array_map(fn($i) => [$i % 2 ? 'k_empA' : 'k_empB', 'POST', '/waste/report', ['confirm_duplicate' => true, 'product_id' => $P, 'amount' => 1, 'waste_reason_id' => $R_FREE, 'branch_id' => 1, 'location_id' => $LOC, 'idempotency_key' => uniqid('par', true)]], range(0, 5)));
$ids = array_filter(array_map(fn($x) => $x[1]['waste_id'] ?? null, $res));
check('6 mermas distintas simultáneas: 6 registros, sin "database is locked"', count($ids) === 6, array_map(fn($x) => $x[0] . ' ' . err($x[1]), array_filter($res, fn($x) => $x[0] != 200)));
check('números de caso únicos', intval($val('SELECT COUNT(DISTINCT case_number) FROM waste_records WHERE id IN (' . implode(',', $ids ?: [0]) . ')')) === count($ids));

// ------------------------------------------------------------------ Mermas: aprobación
head('G. Mermas: aprobación, rechazo, segregación y doble contabilización');
$ph = $photo('k_empA');
[$c, $r] = $waste('k_empA', 2, $R_EV, ['evidence_photo_path' => $ph]); $w1 = intval($r['waste_id'] ?? 0); check('merma escaneada con foto queda por aprobar', ($r['status'] ?? '') === 'pending_approval', $r);
[$c, $r] = $waste('k_empB', 3, $R_HIGH); $w2 = intval($r['waste_id'] ?? 0); check('merma manual de riesgo "Alta" exige aprobación', ($r['status'] ?? '') === 'pending_approval', $r);
$s0 = $stock();
check('pendientes no descuentan stock', abs($s0 - $stock()) < 0.001);
$res = parallel(array_fill(0, 5, ['k_sup', 'POST', "/waste/$w1/approve", null]));
$s1 = $stock();
check('aprobar 5 veces a la vez: sin errores de bloqueo (idempotente)', count(array_filter($res, fn($x) => $x[0] == 200)) === 5, array_map(fn($x) => $x[0] . ' ' . err($x[1]), $res));
check('stock descontado exactamente 2', abs($s0 - $s1 - 2) < 0.001, "$s0 → $s1");
$res = parallel([['k_sup', 'POST', "/waste/$w2/approve", null], ['k_mgr', 'POST', "/waste/$w2/reject", ['rejection_reason' => 'no corresponde']]]);
$st = $val("SELECT status FROM waste_records WHERE id = $w2");
check('aprobar y rechazar a la vez → un único estado final', count(array_filter($res, fn($x) => $x[0] == 200)) === 1 && in_array($st, ['approved', 'rejected']), $st . ' ' . json_encode(array_map(fn($x) => $x[0], $res)));
check('stock coherente con el estado final', $st === 'approved' ? abs($s1 - $stock() - 3) < 0.001 : abs($s1 - $stock()) < 0.001, "$s1 → " . $stock());
[$c, $r] = $waste('k_sup', 2, $R_HIGH); $w3 = intval($r['waste_id'] ?? 0);
[$c, $r] = call('k_sup', 'POST', "/waste/$w3/approve"); check('supervisor no aprueba su propia merma', $c >= 400, err($r));
[$c, $r] = call('k_mgr', 'POST', "/waste/$w3/reject", []); check('rechazo sin motivo → error', $c >= 400, "$c " . err($r));
[$c, $r] = call('k_mgr', 'POST', "/waste/$w3/reject", ['rejection_reason' => 'Producto en buen estado']); check('rechazo con motivo', $c == 200, err($r));
[$c, $r] = call('k_mgr', 'POST', "/waste/$w3/approve"); check('no se aprueba una merma ya rechazada', $c >= 400, err($r));
// Merma en S2 y supervisor de S1
[$c, $r] = call('k_emp2', 'POST', '/waste/report', ['product_id' => $P, 'amount' => 1, 'waste_reason_id' => $R_HIGH, 'branch_id' => 2, 'notes' => 's2', 'idempotency_key' => uniqid()]);
$w4 = intval($r['waste_id'] ?? 0); check('empleado S2 reporta en S2', $w4 > 0 || $c >= 400, "$c " . err($r));
if ($w4) { [$c, $r] = call('k_sup', 'POST', "/waste/$w4/approve"); check('supervisor S1 no aprueba merma de S2', $c == 403, "$c " . err($r)); [$c, $r] = call('k_sup', 'GET', "/m/case/waste/$w4"); check('supervisor S1 no ve el caso de S2', $c == 403 || $c == 404, "$c"); }
// Sobreconsumo concurrente: dos mermas que juntas superan el stock de un lote
$avail = $stock();
$half = floor($avail / 2) + 1;
$ph2 = $photo('k_empA');
[$c, $r] = $waste('k_empA', $half, $R_EV, ['evidence_photo_path' => $ph2]); $wa = intval($r['waste_id'] ?? 0);
[$c, $r] = $waste('k_empB', $half, $R_EV, ['evidence_photo_path' => $ph2]); $wb = intval($r['waste_id'] ?? 0);
check('segunda merma que excede lo disponible (contando la pendiente) → rechazada', $wa > 0 && !$wb && $c == 400, err($r));
[$c, $r] = call('k_mgr', 'POST', '/stock/products/' . $P . '/consume', ['amount' => $avail - $half + 1, 'spoiled' => false]);
check('consumo directo posterior reduce el stock bajo la merma pendiente', $c == 200, "$c " . err($r));
$wb = 0;
$res = parallel([['k_mgr', 'POST', "/waste/$wa/approve", null], ['k_mgr', 'POST', "/waste/$wa/approve", null]]);
check('aprobación simultánea que excede stock: no queda negativo', $stock() >= -0.001, "$avail → " . $stock() . ' ' . json_encode(array_map(fn($x) => $x[0] . ' ' . err($x[1]), $res), JSON_UNESCAPED_UNICODE));
check('sin filas de stock negativas', intval($val('SELECT COUNT(*) FROM stock WHERE amount < 0')) === 0);
check('aprobación sin stock suficiente se rechaza con mensaje claro', count(array_filter($res, fn($x) => $x[0] == 200)) === 0 && $val("SELECT status FROM waste_records WHERE id = $wa") === 'pending_approval', array_map(fn($x) => $x[0] . ' ' . err($x[1]), $res));
[$c, $r] = call('k_empA', 'GET', '/m/notifications'); check('reportero notificado de su merma aprobada', strpos(json_encode($r, JSON_UNESCAPED_UNICODE), 'Merma aprobada') !== false);
[$c, $r] = call('k_sup', 'GET', '/m/notifications'); check('reportero notificado de su merma rechazada', strpos(json_encode($r, JSON_UNESCAPED_UNICODE), 'Merma rechazada') !== false);

// ------------------------------------------------------------------ Lecturas (antes sin permiso ni sucursal)
head('G2. Lecturas: permiso y aislamiento por sucursal');
[$c, $all] = call('k_mgr', 'GET', '/waste/records');
$s1 = array_values(array_filter(is_array($all) ? $all : [], fn($w) => intval($w['branch_id'] ?? 0) === 1));
check('gerente ve mermas de S1', $c === 200 && count($s1) > 0, "$c " . count($s1));
[$c, $r] = call('k_emp2', 'GET', '/waste/records');
check('empleado S2: listado solo con S2', $c === 200 && count(array_filter($r, fn($w) => intval($w['branch_id'] ?? 0) !== 2)) === 0, "$c " . count($r));
[$c] = call('k_emp2', 'GET', '/waste/records?branch_id=1'); check('empleado S2 pide mermas de S1 → 403', $c === 403, $c);
[$c] = call('k_emp2', 'GET', '/waste/analytics?branch_id=1'); check('analítica de S1 desde S2 → 403', $c === 403, $c);
[$c] = call('k_emp2', 'GET', '/waste/analytics'); check('analítica sin sucursal usa la propia', $c === 200, $c);
$ph = array_values(array_filter($s1, fn($w) => !empty($w['evidence_photo_path'])));
if ($ph)
{
	[$c] = call('k_emp2', 'GET', '/waste/photo/' . basename($ph[0]['evidence_photo_path'])); check('foto de evidencia de S1 desde S2 → 403', $c === 403, $c);
}
foreach (['/mrp/orders', '/mrp/calculate', '/suppliers', '/quality/quarantine', '/ai/alerts', '/markdown/active'] as $p)
{
	[$c] = call('k_empA', 'GET', $p); check("empleado sin permiso: GET $p → 403", $c === 403, $c);
}
[$c] = call('k_sup', 'GET', '/workforce/tasks?branch_id=2'); check('supervisor S1: tareas de S2 → 403', $c === 403, $c);
[$c] = call('k_sup', 'GET', '/workforce/tasks'); check('supervisor S1: tareas propias', $c === 200, $c);

// ------------------------------------------------------------------ Recepción de mercancía
head('G3. Recepción de mercancía: kardex, sucursal y reintento');
foreach (['A', 'C'] as $k)
{
	$db->exec("INSERT INTO user_permissions (permission_id, user_id) SELECT id, {$U[$k]} FROM permission_hierarchy WHERE name = 'PROCUREMENT_RECEIVE'");
}
$sup = intval($val('SELECT id FROM shopping_locations ORDER BY id LIMIT 1'));
[$c, $r] = call('k_mgr', 'POST', '/mrp/order/create', ['branch_id' => 1, 'supplier_id' => $sup, 'items' => [['product_id' => $P, 'amount' => 5, 'unit_price' => 1.5]]]);
$oid = intval($val('SELECT MAX(id) FROM procurement_orders WHERE branch_id = 1'));
check('gerente crea orden S1', $c === 200 && $oid > 0, "$c " . err($r));
[$c, $r] = call('k_mgr', 'POST', "/mrp/order/$oid/approve"); check('gerente aprueba la orden', $c === 200, "$c " . err($r));
[$c, $det] = call('k_mgr', 'GET', "/mrp/order/$oid");
$line = ['order_item_id' => intval($det['items'][0]['id'] ?? 0), 'delivered_amount' => 5, 'location_id' => $LOC, 'best_before_date' => date('Y-m-d', strtotime('+120 days'))];
$s0 = $stock(); $k0 = intval($val("SELECT COUNT(*) FROM stock_log WHERE product_id = $P"));
[$c] = call('k_emp2', 'POST', "/mrp/order/$oid/receive", ['items' => [$line]]); check('receptor de S2 no recibe orden de S1 → 403', $c === 403, $c);
[$c] = call('k_emp2', 'GET', "/mrp/order/$oid"); check('receptor de S2 no lee orden de S1 → 403', $c === 403, $c);
$res = parallel(array_fill(0, 3, ['k_empA', 'POST', "/mrp/order/$oid/receive", ['items' => [$line]]]));
check('3 recepciones simultáneas: una entra', count(array_filter($res, fn($x) => $x[0] === 200)) === 1, json_encode(array_column($res, 0)));
check('las rechazadas explican el motivo (no "database is locked")', count(array_filter($res, fn($x) => $x[0] !== 200 && stripos(json_encode($x[1]), 'locked') !== false)) === 0, mb_substr(json_encode(array_map(fn($x) => err($x[1]), $res), JSON_UNESCAPED_UNICODE), 0, 200));
check('stock +5 exactamente (sin doble contabilización)', abs($stock() - $s0 - 5) < 0.001, "$s0 → " . $stock());
check('kardex registra la entrada', intval($val("SELECT COUNT(*) FROM stock_log WHERE product_id = $P")) > $k0);
[$c] = call('k_empA', 'POST', "/mrp/order/$oid/receive", ['items' => [$line]]); check('reintento tras reconexión no vuelve a sumar', $c >= 400 && abs($stock() - $s0 - 5) < 0.001, "$c " . $stock());

// ------------------------------------------------------------------ Ventas y traslados concurrentes
head('G4. Ventas y traslados: reintentos y operaciones simultáneas');
$s0 = $stock();
$sale = ['branch_id' => 1, 'payment_method' => 'cash', 'items' => [['product_id' => $P, 'amount' => 1, 'unit_price' => floatval($val("SELECT IFNULL(NULLIF(sale_price, 0), 99) FROM products WHERE id = $P")), 'location_id' => $LOC]]];
$sk = 'pos-' . uniqid('', true);
$nSales = intval($val('SELECT COUNT(*) FROM sales'));
$res = parallel(array_fill(0, 3, ['k_mgr', 'POST', '/sales', $sale, ['X-Idempotency-Key: ' . $sk]]));
check('venta con la misma clave enviada 3 veces → 1 venta', intval($val('SELECT COUNT(*) FROM sales')) === $nSales + 1, json_encode(array_map(fn($x) => $x[0] . ' ' . mb_substr(err($x[1]), 0, 90), $res), JSON_UNESCAPED_UNICODE));
check('stock -1 exactamente', abs($s0 - $stock() - 1) < 0.001, "$s0 → " . $stock());
$ch = req('k_mgr', 'POST', '/sales', $sale, 8093, ['X-Idempotency-Key: ' . $sk]); $raw = curl_exec($ch);
check('reintento tras reconexión devuelve la misma venta', intval($val('SELECT COUNT(*) FROM sales')) === $nSales + 1 && curl_getinfo($ch, CURLINFO_HTTP_CODE) === 200, curl_getinfo($ch, CURLINFO_HTTP_CODE));
$sid = intval(array_values(array_filter(array_map(fn($x) => $x[1]['sale_id'] ?? 0, $res)))[0] ?? 0);
foreach (['SALES_VIEW', 'SALES_CANCEL'] as $p)
{
	$db->exec("INSERT INTO user_permissions (permission_id, user_id) SELECT id, {$U['C']} FROM permission_hierarchy WHERE name = '$p'");
}
[$c] = call('k_emp2', 'GET', "/sales/$sid"); check('venta de S1 vista desde S2 → 403', $c === 403, $c);
[$c] = call('k_emp2', 'POST', "/sales/$sid/cancel"); check('venta de S1 anulada desde S2 → 403', $c === 403, $c);
$res = parallel(array_fill(0, 3, ['k_mgr', 'POST', "/sales/$sid/cancel"]));
check('3 anulaciones simultáneas: una entra', count(array_filter($res, fn($x) => $x[0] === 200)) === 1, json_encode(array_map(fn($x) => $x[0] . ' ' . mb_substr(err($x[1]), 0, 40), $res), JSON_UNESCAPED_UNICODE));
check('stock reintegrado una sola vez', abs($stock() - $s0) < 0.001, "$s0 → " . $stock());
$LOC2 = intval($val('SELECT id FROM locations WHERE branch_id = 2 AND IFNULL(is_transit, 0) = 0 AND active = 1 ORDER BY id LIMIT 1'));
[$c, $r] = call('k_mgr', 'POST', '/transfers', ['from_location_id' => $LOC, 'to_location_id' => $LOC2, 'items' => [['product_id' => $P, 'amount' => 2]], 'notes' => 'auditoría']);
$tid = intval($r['id'] ?? 0);
check('traslado S1 → S2 enviado', $c === 200 && $tid > 0, "$c " . err($r));
$transit = intval($val('SELECT id FROM locations WHERE is_transit = 1 LIMIT 1'));
$res = parallel([['k_mgr', 'POST', "/transfers/$tid/receive", ['items' => [['product_id' => $P, 'received_amount' => 2]]]], ['k_mgr', 'POST', "/transfers/$tid/cancel", ['reason' => 'prueba concurrente']]]);
$st = $val("SELECT status FROM stock_transfers WHERE id = $tid");
check('recibir y anular a la vez → un solo resultado', count(array_filter($res, fn($x) => $x[0] === 200)) === 1 && in_array($st, ['received', 'cancelled'], true), "$st " . json_encode(array_column($res, 0)));
check('nada queda en tránsito y el total no cambia', abs(floatval($val("SELECT IFNULL(SUM(amount),0) FROM stock WHERE product_id = $P AND location_id = $transit"))) < 0.001 && abs($stock() - $s0) < 0.001, "$s0 → " . $stock());

// ------------------------------------------------------------------ Revocación de permisos
head('H. Cambio de permisos en caliente');
$db->exec("DELETE FROM user_permissions WHERE user_id = {$U['B']} AND permission_id = 32");
[$c, $r] = $waste('k_empB', 1, $R_FREE); check('permiso revocado se aplica en la siguiente petición', $c == 403, "$c " . err($r));
$db->exec("INSERT INTO user_permissions(permission_id, user_id) VALUES (32, {$U['B']})");
$db->exec("UPDATE api_keys SET expires = '2000-01-01' WHERE api_key = 'k_empB'");
[$c, $r] = call('k_empB', 'GET', '/m/me'); check('clave revocada/vencida corta la sesión', $c == 401, "$c");

// ------------------------------------------------------------------ Conciliación final
head('I. Conciliación de integridad');
check('cada merma aprobada tiene transaction_id', intval($val("SELECT COUNT(*) FROM waste_records WHERE status = 'approved' AND inventory_deducted = 1 AND IFNULL(transaction_id,'') = '' AND created_timestamp >= date('now','-1 day')")) === 0);
check('ninguna merma pendiente/rechazada movió stock', intval($val("SELECT COUNT(*) FROM waste_records w WHERE w.status IN ('pending_approval','rejected') AND w.transaction_id IS NOT NULL AND EXISTS (SELECT 1 FROM stock_log l WHERE l.transaction_id = w.transaction_id AND l.undone = 0)")) === 0);
check('sin claves de idempotencia duplicadas', intval($val("SELECT COUNT(*) FROM (SELECT idempotency_key FROM waste_records WHERE idempotency_key IS NOT NULL GROUP BY idempotency_key HAVING COUNT(*) > 1)")) === 0);
$dLog = floatval($val("SELECT IFNULL(SUM(amount),0) FROM stock_log WHERE product_id = $P AND undone = 0")) - $logBase; $dStock = $stock() - $stockBase;
check('Δ kardex del producto = Δ stock (todas las operaciones de la prueba)', abs($dLog - $dStock) < 0.01, "kardex $dLog vs stock $dStock");

echo "\n$total verificaciones, $fails fallos\n";
exit($fails > 0 ? 1 : 0);
