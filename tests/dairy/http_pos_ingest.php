<?php
/**
 * Etapa 32 — Ingesta POS por HTTP real (modo producción, claves por rol, firma HMAC del webhook, envíos simultáneos).
 * Sandbox propio (copia de la BD) en el puerto 8098; nunca toca la base original.
 *
 *   php tests/dairy/http_pos_ingest.php [ruta/a/grocy.db]
 */

$root = dirname(__DIR__, 2);
define('GROCY_DATAPATH', $root . '/data');
require_once $root . '/packages/autoload.php';
require_once $root . '/data/config.php';
require_once $root . '/config-dist.php';
$dbName = (GROCY_MODE === 'demo' || GROCY_MODE === 'prerelease') ? 'grocy_' . GROCY_DEFAULT_LOCALE . '.db' : 'grocy.db';
$src = $argv[1] ?? ($root . '/data/' . $dbName);
$SANDBOX = __DIR__ . '/.pos_sandbox';
$SECRET = 'e32-secreto';
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
file_put_contents($SANDBOX . '/data/config.php', $cfg . "\nSetting('POS_WEBHOOK_SECRET', '$SECRET');\n");
if (!is_file($src) || !copy($src, $SANDBOX . '/data/grocy.db'))
{
	fwrite(STDERR, "No se pudo copiar la base de datos $src\n");
	exit(2);
}

$PORT = 8098;
$env = getenv();
$env['GROCY_DATAPATH'] = 'tests/dairy/.pos_sandbox/data';
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
for ($i = 0; $i < 40 && !$db->query("SELECT 1 FROM sqlite_master WHERE name = 'pos_external_sales'")->fetchColumn(); $i++)
{
	usleep(250000);
}

$loc = intval($db->query("SELECT id FROM locations WHERE branch_id = 1 AND active = 1 AND IFNULL(is_transit, 0) = 0 ORDER BY id LIMIT 1")->fetchColumn());
$other = intval($db->query('SELECT id FROM branches WHERE id != 1 ORDER BY id LIMIT 1')->fetchColumn());
$db->exec("UPDATE locations SET storage_class = NULL, capacity_units = NULL WHERE id = $loc");
$qu = intval($db->query('SELECT id FROM quantity_units ORDER BY id LIMIT 1')->fetchColumn());
$db->exec("INSERT INTO products (name, location_id, qu_id_purchase, qu_id_stock, qu_id_consume, qu_id_price, default_best_before_days, sale_price, active) VALUES ('HTTP E32', $loc, $qu, $qu, $qu, $qu, 30, 5, 1)");
$pid = intval($db->lastInsertId());
$db->exec("INSERT INTO stock (product_id, amount, best_before_date, purchased_date, stock_id, price, location_id) VALUES ($pid, 20, date('now', '+20 days'), date('now'), 'E32STK', 2, $loc)");
$qty = fn() => floatval($db->query("SELECT IFNULL(SUM(amount), 0) FROM stock WHERE product_id = $pid")->fetchColumn());

$perm = fn($n) => intval($db->query("SELECT id FROM permission_hierarchy WHERE name = '$n'")->fetchColumn());
$admin = intval($db->query("SELECT up.user_id FROM user_permissions up JOIN permission_hierarchy p ON p.id = up.permission_id WHERE p.name = 'ADMIN' ORDER BY up.user_id LIMIT 1")->fetchColumn());
$keys = ['k_admin' => $admin];
foreach (['k_view' => ['SALES_VIEW'], 'k_pos' => ['POS_INTEGRATION'], 'k_none' => ['STOCK']] as $k => $ps)
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
function req($key, $method, $path, $body = null, $sign = true, $raw = null)
{
	global $PORT, $SECRET;
	$ch = curl_init("http://127.0.0.1:$PORT/api$path");
	$h = ['Accept: application/json', 'GROCY-API-KEY: ' . $key];
	if ($body !== null || $raw !== null)
	{
		$payload = $raw ?? json_encode($body);
		$h[] = 'Content-Type: application/json';
		if ($sign)
		{
			$h[] = 'X-Dairy-Signature: sha256=' . hash_hmac('sha256', $payload, $SECRET);
		}
		curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
	}
	curl_setopt_array($ch, [CURLOPT_CUSTOMREQUEST => $method, CURLOPT_HTTPHEADER => $h, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 60]);
	return $ch;
}
function call($key, $method, $path, $body = null, $sign = true)
{
	$ch = req($key, $method, $path, $body, $sign);
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
$err = fn($r) => is_array($r) ? ($r['error_message'] ?? json_encode(array_column($r['results'] ?? [], 'error'))) : substr(strval($r), 0, 120);
$y = date('Y-m-d', strtotime('-1 day'));
$sale = fn($id, $n = 1, $br = 1) => ['external_id' => $id, 'branch_id' => $br, 'occurred_at' => "$y 11:00", 'payment_method' => 'card', 'items' => [['product_id' => $pid, 'amount' => $n, 'unit_price' => 5]]];

echo "\nIngesta POS por HTTP (Etapa 32)\n";
// Permisos
[$c] = call('k_none', 'POST', '/pos/ingest', ['source' => 'tienda', 'sales' => [$sale('H-0')]]);
check('sin permiso: ingesta 403', $c === 403, $c);
[$c] = call('k_view', 'POST', '/pos/ingest', ['source' => 'tienda', 'sales' => [$sale('H-0')]]);
check('solo «ver ventas»: ingesta 403', $c === 403, $c);
[$c, $r] = call('k_view', 'GET', '/pos/status');
check('«ver ventas» ve el estado sin poder importar', $c === 200 && $r['can_import'] === false && $r['webhook']['signature_required'] === true, $err($r));
[$c] = call('k_none', 'GET', '/pos/status');
check('sin permiso: estado 403', $c === 403, $c);

// Firma HMAC
[$c, $r] = call('k_pos', 'POST', '/pos/ingest', ['source' => 'tienda', 'sales' => [$sale('H-1')]], false);
check('webhook sin firma → 400 y nada importado', $c === 400 && str_contains($err($r), 'Firma') && abs($qty() - 20) < 0.001, $err($r));
$ch = req('k_pos', 'POST', '/pos/ingest', null, false, json_encode(['source' => 'tienda', 'sales' => [$sale('H-1')]]));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json', 'GROCY-API-KEY: k_pos', 'Content-Type: application/json', 'X-Dairy-Signature: sha256=' . str_repeat('0', 64)]);
curl_exec($ch);
check('firma incorrecta → 400', curl_getinfo($ch, CURLINFO_HTTP_CODE) === 400);
[$c, $r] = call('k_pos', 'POST', '/pos/ingest', ['source' => 'tienda', 'sales' => [$sale('H-1', 2)]]);
check('webhook firmado: importa y descuenta stock', $c === 200 && $r['imported'] === 1 && abs($qty() - 18) < 0.001, $err($r));
[$c, $r] = call('k_pos', 'POST', '/pos/ingest', ['source' => 'tienda', 'sales' => [$sale('H-1', 2)]]);
check('reenvío del webhook: duplicado', $c === 200 && $r['duplicates'] === 1 && abs($qty() - 18) < 0.001, $err($r));
[$c, $r] = call('k_pos', 'POST', '/pos/ingest', ['source' => 'tienda', 'dry_run' => true, 'sales' => [$sale('H-DRY')]]);
check('prueba en seco por API: válida sin escribir', $c === 200 && $r['valid'] === 1 && abs($qty() - 18) < 0.001, $err($r));

// Aislamiento por sucursal
[$c, $r] = call('k_pos', 'POST', '/pos/ingest', ['source' => 'tienda', 'sales' => [$sale('H-X', 1, $other)]]);
check('venta de otra sucursal rechazada por fila', $c === 200 && $r['failed'] === 1 && str_contains($r['results'][0]['error'], 'Sin acceso'), $err($r));
$xid = intval($db->query("SELECT id FROM pos_external_sales WHERE external_id = 'H-X'")->fetchColumn());
[$c] = call('k_pos', 'POST', "/pos/external/$xid/retry", []);
check('reintento de fila sin sucursal propia → 403', $c === 403, $c);
[$c, $r] = call('k_admin', 'POST', "/pos/external/$xid/retry", []);
check('administrador puede reintentar (sin stock en esa sucursal: sigue fallida con motivo)', $c === 200 && $r['status'] === 'failed' && str_contains(strval($r['error']), 'insuficiente') && abs($qty() - 18) < 0.001, json_encode($r));

// CSV
$csv = "ticket,sucursal,fecha,producto_id,cantidad,precio_unitario\nC-1,1,$y 09:30,$pid,1,5\nC-2,1,$y 09:45,$pid,1,5\n";
[$c, $r] = call('k_pos', 'POST', '/pos/import-csv', ['source' => 'tienda', 'csv' => $csv, 'file_name' => 'cierre.csv']);
check('CSV por API importa 2', $c === 200 && $r['imported'] === 2 && $r['channel'] === 'csv', $err($r));
[$c, $r] = call('k_pos', 'POST', '/pos/import-csv', ['source' => 'tienda', 'csv' => "a,b\n1,2"]);
check('CSV sin columnas obligatorias → 400 con nombre de columna', $c === 400 && str_contains($err($r), 'external_id'), $err($r));

// Concurrencia real: el mismo ticket enviado 4 veces a la vez → una sola venta
$before = $qty();
$mh = curl_multi_init();
$hs = [];
for ($i = 0; $i < 4; $i++)
{
	$hs[] = $h = req('k_pos', 'POST', '/pos/ingest', ['source' => 'tienda', 'sales' => [$sale('H-CONC', 3)]]);
	curl_multi_add_handle($mh, $h);
}
do
{
	curl_multi_exec($mh, $running);
	curl_multi_select($mh);
} while ($running > 0);
$codes = array_map(fn($h) => curl_getinfo($h, CURLINFO_HTTP_CODE), $hs);
check('4 envíos simultáneos del mismo ticket → 1 venta', intval($db->query("SELECT COUNT(*) FROM sales WHERE sale_number = 'POS-TIENDA-H-CONC'")->fetchColumn()) === 1, implode(',', $codes));
check('stock descontado una sola vez (−3)', abs($qty() - ($before - 3)) < 0.001, $before . ' → ' . $qty());
[$c, $r] = call('k_pos', 'POST', '/pos/ingest', ['source' => 'tienda', 'sales' => [$sale('H-CONC', 3)]]);
check('tras la carrera, el reenvío es duplicado', $c === 200 && $r['duplicates'] === 1, $err($r));

// Devolución total por webhook
[$c, $r] = call('k_pos', 'POST', '/pos/ingest', ['source' => 'tienda', 'sales' => [['external_id' => 'H-R1', 'kind' => 'refund', 'original_external_id' => 'H-1', 'branch_id' => 1, 'occurred_at' => "$y 12:00"]]]);
check('devolución total reintegra stock (+2)', $c === 200 && $r['imported'] === 1 && abs($qty() - ($before - 1)) < 0.001, $err($r));

[$c, $r] = call('k_admin', 'GET', '/pos/status');
check('estado: origen «tienda» con última venta y lotes', $c === 200 && ($r['sources'][0]['source'] ?? '') === 'tienda' && count($r['batches']) > 0 && $r['pull_connector']['state'] === 'not_configured', $err($r));

$log = @file_get_contents($SANDBOX . '/server.log') ?: '';
check('sin errores PHP en el servidor', !preg_match('/PHP (Fatal|Warning|Notice|Deprecated)/', $log), substr($log, 0, 300));
echo "\n$total verificaciones, $fails fallos\n";
exit($fails > 0 ? 1 : 0);
