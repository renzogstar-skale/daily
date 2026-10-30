<?php
/**
 * Etapa 34 — permisos por HTTP real: acciones de Arturito, reportes personalizables y simulador (modo producción, claves por rol).
 * Sandbox propio (copia de la BD) en el puerto 8099; nunca toca la base original.
 *
 *   php tests/dairy/http_stage34.php [ruta/a/grocy.db]
 */

$root = dirname(__DIR__, 2);
define('GROCY_DATAPATH', $root . '/data');
require_once $root . '/packages/autoload.php';
require_once $root . '/data/config.php';
require_once $root . '/config-dist.php';
$dbName = (GROCY_MODE === 'demo' || GROCY_MODE === 'prerelease') ? 'grocy_' . GROCY_DEFAULT_LOCALE . '.db' : 'grocy.db';
$src = $argv[1] ?? ($root . '/data/' . $dbName);
$SANDBOX = __DIR__ . '/.s34_sandbox';
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

$PORT = 8099;
$env = getenv();
$env['GROCY_DATAPATH'] = 'tests/dairy/.s34_sandbox/data';
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
@file_get_contents("http://127.0.0.1:$PORT/", false, stream_context_create(['http' => ['ignore_errors' => true, 'follow_location' => 0, 'timeout' => 120]]));
$db = new Pdo\Sqlite('sqlite:' . $SANDBOX . '/data/grocy.db');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->createFunction('dairy_user_id', fn() => null, 0);
for ($i = 0; $i < 60 && !$db->query("SELECT 1 FROM sqlite_master WHERE name = 'dairy_report_runs'")->fetchColumn(); $i++)
{
	usleep(250000);
}
$perm = fn($n) => intval($db->query("SELECT id FROM permission_hierarchy WHERE name = '$n'")->fetchColumn());
$admin = intval($db->query("SELECT up.user_id FROM user_permissions up JOIN permission_hierarchy p ON p.id = up.permission_id WHERE p.name = 'ADMIN' ORDER BY up.user_id LIMIT 1")->fetchColumn());
$keys = ['k_admin' => $admin];
foreach (['k_kpi' => ['KPIS_VIEW'], 'k_buy' => ['KPIS_VIEW', 'PROCUREMENT_MANAGE'], 'k_sales' => ['SALES_VIEW'], 'k_none' => ['STOCK']] as $k => $ps)
{
	$db->prepare('INSERT INTO users (username, first_name, last_name, password) VALUES (?, ?, ?, ?)')->execute([$k . '_' . uniqid(), 'Prueba', $k, password_hash(bin2hex(random_bytes(8)), PASSWORD_DEFAULT)]);
	$u = intval($db->lastInsertId());
	foreach ($ps as $p)
	{
		$db->exec("INSERT INTO user_permissions (permission_id, user_id) VALUES ({$perm($p)}, $u)");
	}
	$db->exec("INSERT INTO user_branches (user_id, branch_id) VALUES ($u, 1)");
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
	curl_setopt_array($ch, [CURLOPT_CUSTOMREQUEST => $method, CURLOPT_HTTPHEADER => $h, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 120]);
	$raw = curl_exec($ch);
	return [curl_getinfo($ch, CURLINFO_HTTP_CODE), json_decode($raw, true) ?? $raw, $raw];
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
$cnt = fn($t) => intval($db->query("SELECT COUNT(*) FROM $t")->fetchColumn());

echo "== Acciones de Arturito\n";
[$c] = call('k_none', 'POST', '/ai/actions/preview', ['type' => 'draft_po', 'branch_id' => 1]);
check('sin permiso de compras: vista previa de OC → 403', $c === 403, $c);
[$c] = call('k_kpi', 'POST', '/ai/actions/execute', ['type' => 'draft_po', 'branch_id' => 1, 'confirm' => true]);
check('solo KPIs (sin compras): ejecutar OC → 403', $c === 403, $c);
[$c, $b] = call('k_buy', 'POST', '/ai/actions/preview', ['type' => 'draft_po', 'branch_id' => 1]);
check('compras: vista previa 200 con resumen', $c === 200 && !empty($b['summary']), $b);
[$c] = call('k_buy', 'POST', '/ai/actions/preview', ['type' => 'draft_po', 'branch_id' => 2]);
check('compras de la sucursal 1: otra sucursal → 403', $c === 403, $c);
$o0 = $cnt('procurement_orders');
[$c, $b] = call('k_buy', 'POST', '/ai/actions/execute', ['type' => 'draft_po', 'branch_id' => 1, 'confirm' => 'true']);
check('confirm debe ser booleano true (texto no vale) → 400 sin escribir', $c === 400 && $cnt('procurement_orders') === $o0, $b);
[$c, $b] = call('k_buy', 'POST', '/ai/actions/execute', ['type' => 'draft_po', 'branch_id' => 1, 'confirm' => true, 'question' => '¿qué reponer?']);
check('confirmada: 200 y bitácora (o nada que ejecutar)', ($c === 200 && intval($db->query('SELECT COUNT(*) FROM dairy_ai_actions WHERE user_id = ' . $keys['k_buy'])->fetchColumn()) === 1) || ($c === 400 && str_contains(json_encode($b, JSON_UNESCAPED_UNICODE), 'Nada que ejecutar')), [$c, $b]);
[$c] = call('k_kpi', 'POST', '/ai/actions/preview', ['type' => 'drop_tables']);
check('tipo desconocido → 400', $c === 400, $c);

echo "== Reportes personalizables\n";
[$c, $b] = call('k_none', 'GET', '/reports/catalog');
check('sin permisos de datos: catálogo sin ventas/mermas/compras', $c === 200 && !array_intersect(array_keys($b['datasets']), ['sales', 'sales_products', 'waste', 'purchases', 'shifts', 'audits', 'alerts']), array_keys($b['datasets'] ?? []));
[$c, $b] = call('k_sales', 'GET', '/reports/catalog');
check('ventas: solo conjuntos de ventas', $c === 200 && !array_diff(array_keys($b['datasets']), ['sales', 'sales_products']) && isset($b['datasets']['sales']), array_keys($b['datasets'] ?? []));
[$c] = call('k_sales', 'POST', '/reports/preview', ['dataset' => 'waste', 'config' => ['columns' => ['cost']]]);
check('ventas pidiendo mermas → 403', $c === 403, $c);
[$c] = call('k_sales', 'POST', '/reports/preview', ['dataset' => 'sales', 'config' => ['columns' => ['total'], 'branch_id' => 2]]);
check('ventas sucursal 1 pidiendo la 2 → 403', $c === 403, $c);
[$c, $b] = call('k_sales', 'POST', '/reports/preview', ['dataset' => 'sales', 'config' => ['columns' => ['branch', 'total'], 'period' => 'ytd']]);
$b1 = $db->query('SELECT name FROM branches WHERE id = 1')->fetchColumn();
check('vista previa filtra a su sucursal', $c === 200 && !array_diff(array_unique(array_column($b['rows'], 0)), [$b1]), [$c, array_unique(array_column($b['rows'] ?? [], 0))]);
[$c, $b] = call('k_sales', 'POST', '/reports/definitions', ['name' => 'Mis ventas', 'dataset' => 'sales', 'config' => ['columns' => ['date', 'total']], 'schedule' => 'weekly', 'schedule_hour' => 8]);
$defId = $b['id'] ?? 0;
check('guarda definición semanal', $c === 200 && $defId > 0, $b);
[$c] = call('k_admin', 'POST', "/reports/definitions/$defId/run");
check('ADMIN puede ejecutar la definición de otro', $c === 200, $c);
[$c] = call('k_buy', 'POST', "/reports/definitions/$defId/run");
check('otro usuario no puede ejecutarla → 404', $c === 404, $c);
[$c, $b] = call('k_sales', 'POST', "/reports/definitions/$defId/run");
check('dueño la ejecuta', $c === 200 && ($b['status'] ?? '') === 'ok', $b);
[$c, , $raw] = call('k_sales', 'GET', '/reports/runs/' . ($b['run_id'] ?? 0) . '/csv');
check('descarga CSV con BOM y encabezados', $c === 200 && str_starts_with($raw, "\xEF\xBB\xBF") && str_contains($raw, 'Fecha;Total'), substr($raw, 0, 40));
[$c] = call('k_buy', 'GET', '/reports/runs/' . ($b['run_id'] ?? 0) . '/csv');
check('CSV de otro usuario → 404', $c === 404, $c);
[$c, $b] = call('k_sales', 'GET', '/reports/definitions');
check('lista solo las propias', $c === 200 && count($b['definitions']) === 1 && count($b['runs']) >= 1, count($b['definitions'] ?? []));

echo "== Simulador\n";
[$c] = call('k_none', 'POST', '/simulator/evaluate', ['product_id' => 1, 'lot_amount' => 5]);
check('sin KPIs → 403', $c === 403, $c);
[$c, $b] = call('k_kpi', 'POST', '/simulator/evaluate', ['product_id' => 0, 'lot_amount' => 5]);
check('sin producto → 400 con mensaje', $c === 400 && str_contains(json_encode($b, JSON_UNESCAPED_UNICODE), 'producto'), $b);

echo "\n$total verificaciones, $fails fallos\n";
exit($fails > 0 ? 1 : 0);
