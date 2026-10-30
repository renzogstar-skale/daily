<?php
/**
 * Barrido HTTP de todas las rutas GET sin parámetros (web y API) sobre una COPIA de la base de datos.
 * Falla si alguna responde 5xx, muestra errores PHP o devuelve error sin ser una ruta que exige parámetros.
 * También verifica que una caché de rutas desactualizada se invalide sola (si no, las URL llegan al controlador
 * equivocado: /api/m/notifications devolvía los datos de /api/m/schedule).
 *
 *   php tests/dairy/http_sweep.php [ruta/a/grocy.db]
 *
 * Requiere el puerto 8096 libre. Crea y borra tests/dairy/.sweep_sandbox.
 */

$root = dirname(__DIR__, 2);
define('GROCY_DATAPATH', $root . '/data');
require_once $root . '/packages/autoload.php';
require_once $root . '/data/config.php';
require_once $root . '/config-dist.php';
$dbName = (GROCY_MODE === 'demo' || GROCY_MODE === 'prerelease') ? 'grocy_' . GROCY_DEFAULT_LOCALE . '.db' : 'grocy.db';
$src = $argv[1] ?? ($root . '/data/' . $dbName);
$SANDBOX = __DIR__ . '/.sweep_sandbox';
$rmrf = function ($dir) use (&$rmrf) {
	foreach (array_diff(@scandir($dir) ?: [], ['.', '..']) as $f)
	{
		is_dir("$dir/$f") ? $rmrf("$dir/$f") : @unlink("$dir/$f");
	}
	@rmdir($dir);
};
$rmrf($SANDBOX);
@mkdir($SANDBOX . '/data/viewcache', 0777, true);
// Modo demo con la BD copiada: inicio de sesión automático como administrador (ve todas las pantallas)
$cfg = preg_replace(["/Setting\('MODE', '[a-z]+'\);/", "/Setting\('DISABLE_AUTH', (true|false)\);/"], ["Setting('MODE', 'demo');", "Setting('DISABLE_AUTH', false);"], file_get_contents($root . '/data/config.php'));
file_put_contents($SANDBOX . '/data/config.php', $cfg);
if (!is_file($src) || !copy($src, $SANDBOX . '/data/grocy_' . GROCY_DEFAULT_LOCALE . '.db'))
{
	fwrite(STDERR, "No se pudo copiar la base de datos $src\n");
	exit(2);
}

$PORT = 8096;
$env = getenv();
$env['GROCY_DATAPATH'] = 'tests/dairy/.sweep_sandbox/data';
$proc = proc_open([PHP_BINARY, '-S', "127.0.0.1:$PORT", '-t', 'public', 'router.php'], [0 => ['pipe', 'r'], 1 => ['file', $SANDBOX . '/server.log', 'a'], 2 => ['file', $SANDBOX . '/server.log', 'a']], $pipes, $root, $env);
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

function get($path)
{
	global $PORT;
	$ch = curl_init("http://127.0.0.1:$PORT$path");
	curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 90, CURLOPT_FOLLOWLOCATION => false]);
	$t = microtime(true);
	$body = (string) curl_exec($ch);
	return [curl_getinfo($ch, CURLINFO_HTTP_CODE), $body, (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE), intval((microtime(true) - $t) * 1000)];
}
$fails = 0; $total = 0;
function check($label, $ok, $extra = '')
{
	global $fails, $total;
	$total++;
	if (!$ok) { $fails++; }
	echo ($ok ? '  OK   ' : '  FAIL ') . $label . ($extra !== '' ? "  [$extra]" : '') . "\n";
}

$ready = false;
for ($i = 0; $i < 40 && !$ready; $i++)
{
	usleep(250000);
	$ready = get('/api/system/info')[0] === 200;
}
if (!$ready)
{
	fwrite(STDERR, "El servidor de prueba no respondió (¿puerto $PORT ocupado?)\n");
	exit(2);
}
get('/'); // migraciones pendientes en la copia

// Rutas GET sin parámetros, separando web (grupo '') de API (grupo '/api')
$routes = [];
$prefix = '';
foreach (file($root . '/routes.php') as $l)
{
	if (str_contains($l, "\$app->group('/api'"))
	{
		$prefix = '/api';
	}
	if (preg_match("/->get\\('(\\/[^'{]*)'/", $l, $m))
	{
		$routes[] = $prefix . $m[1];
	}
}
$routes = array_values(array_unique($routes));
// Rutas que exigen parámetros o hardware: 400 con mensaje claro es el comportamiento correcto
$needsInput = ['/api/print/shoppinglist/thermal', '/api/scan/resolve', '/api/traceability', '/api/m/scan', '/api/schedule/coverage/suggest', '/api/schedule/changes', '/api/schedule/templates', '/api/schedule/timesheet', '/api/schedule/attendance', '/api/schedule/adjustments', '/api/schedule/forecast', '/api/schedule/forecast/band', '/api/schedule/forecast/accuracy', '/api/schedule/forecast/events', '/api/schedule/scenarios'];
$skip = ['/logout', '/api/calendar/ical'];
$phpErr = '#(<b>(?:Warning|Notice|Fatal error|Deprecated)</b>|Whoops|Uncaught [A-Za-z\\\\]+Exception|Undefined (?:variable|array key|index) )#';

echo "\n1. Barrido de rutas GET (" . count($routes) . ")\n";
$slow = [];
foreach ($routes as $r)
{
	if (in_array($r, $skip, true))
	{
		continue;
	}
	[$code, $body, $ct, $ms] = get($r);
	if ($ms > 2000)
	{
		$slow[] = "$r {$ms}ms";
	}
	$snippet = substr(preg_replace('/\s+/', ' ', strip_tags($body)), 0, 160);
	if (in_array($r, $needsInput, true))
	{
		$msg = json_decode($body, true)['error_message'] ?? '';
		check("$r exige datos y lo explica", $code === 400 && $msg !== '', "$code $msg");
		continue;
	}
	$bad = $code >= 400 || $code === 0 || (str_contains($ct, 'html') && preg_match($phpErr, $body)) || (str_contains($ct, 'json') && isset(json_decode($body, true)['error_message']) && $r !== '/api/openapi/specification');
	check($r, !$bad, $bad ? "$code $snippet" : '');
}

echo "\n2. Rutas y controladores correctos\n";
$n = json_decode(get('/api/m/notifications?branch_id=1')[1], true);
check('/api/m/notifications devuelve notificaciones (items, unread, types)', is_array($n) && isset($n['items'], $n['unread'], $n['types']), json_encode(array_keys((array) $n)));
$s = json_decode(get('/api/m/schedule')[1], true);
check('/api/m/schedule devuelve el horario propio (linked)', is_array($s) && array_key_exists('linked', $s), json_encode(array_keys((array) $s)));

echo "\n3. Caché de rutas desactualizada se invalida sola\n";
$cacheFile = $SANDBOX . '/data/viewcache/route_cache.php';
$cache = (string) @file_get_contents($cacheFile);
preg_match("#'/api/m/notifications' => '(route\\d+)'#", $cache, $a);
preg_match("#'/api/m/schedule' => '(route\\d+)'#", $cache, $b);
check('caché de rutas generada', $cache !== '' && $a && $b);
if ($a && $b)
{
	// Simula una caché de una versión anterior de routes.php: rutas cruzadas y su marca de versión vieja
	file_put_contents($cacheFile, strtr($cache, ["'/api/m/notifications' => '{$a[1]}'" => "'/api/m/notifications' => '{$b[1]}'", "'/api/m/schedule' => '{$b[1]}'" => "'/api/m/schedule' => '{$a[1]}'"]));
	foreach (glob($SANDBOX . '/data/viewcache/*.txt') as $f)
	{
		unlink($f);
	}
	// Marca válida para la misma versión/URL: solo cambió routes.php (una caché que no lo considera no se invalidaría)
	touch($SANDBOX . '/data/viewcache/' . hash('sha256', file_get_contents($root . '/version.json') . GROCY_BASE_URL . GROCY_BASE_PATH) . '.txt');
	$first = get('/api/m/notifications?branch_id=1')[0];
	check('primera petición tras el cambio vacía la caché y redirige a / (migraciones)', $first === 302, strval($first));
	get('/');
	$n = json_decode(get('/api/m/notifications?branch_id=1')[1], true);
	check('tras cambiar routes.php, /api/m/notifications sigue llegando a su controlador', is_array($n) && isset($n['items']), json_encode(array_keys((array) $n)));
}

echo "\n4. Rendimiento (informativo)\n";
echo $slow ? '  Rutas > 2 s: ' . implode(', ', $slow) . "\n" : "  Ninguna ruta tardó más de 2 s\n";

echo "\n$total verificaciones, $fails fallos\n";
exit($fails ? 1 : 0);
