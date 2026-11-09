<?php
/**
 * Suscripciones DAILY de punta a punta por HTTP, con inicio de sesión real de las cuatro cuentas de evaluación
 * (essential, professional, business, enterprise) sobre una COPIA de la BD en modo producción.
 * Verifica: menú por plan, rutas directas, APIs de lectura/escritura, herencia, roles, móvil y cambio de plan en caliente.
 *
 *   php tests/dairy/http_subscription.php [ruta/a/grocy.db] [--serve]
 *
 * Requiere el puerto 8097 libre. Crea y borra tests/dairy/.sub_sandbox.
 */

$root = dirname(__DIR__, 2);
define('GROCY_DATAPATH', $root . '/data');
require_once $root . '/packages/autoload.php';
require_once $root . '/data/config.php';
require_once $root . '/config-dist.php';
$dbName = (GROCY_MODE === 'demo' || GROCY_MODE === 'prerelease') ? 'grocy_' . GROCY_DEFAULT_LOCALE . '.db' : 'grocy.db';
$SERVE = in_array('--serve', $argv, true);
$args = array_values(array_filter(array_slice($argv, 1), fn ($a) => $a !== '--serve'));
$src = $args[0] ?? ($root . '/data/' . $dbName);
$SANDBOX = __DIR__ . '/.sub_sandbox';
$rmrf = function ($dir) use (&$rmrf) {
	foreach (array_diff(@scandir($dir) ?: [], ['.', '..']) as $f)
	{
		is_dir("$dir/$f") ? $rmrf("$dir/$f") : @unlink("$dir/$f");
	}
	@rmdir($dir);
};
$rmrf($SANDBOX);
@mkdir($SANDBOX . '/data/viewcache', 0777, true);
// Producción con autenticación real: en demo todas las solicitudes serían del usuario 1
$cfg = preg_replace(["/Setting\('MODE', '[a-z]+'\);/", "/Setting\('DISABLE_AUTH', (true|false)\);/"], ["Setting('MODE', 'production');", "Setting('DISABLE_AUTH', false);"], file_get_contents($root . '/data/config.php'));
file_put_contents($SANDBOX . '/data/config.php', $cfg);
if (!is_file($src) || !copy($src, $SANDBOX . '/data/grocy.db'))
{
	fwrite(STDERR, "No se pudo copiar la base de datos $src\n");
	exit(2);
}
$PASS = 'PlanTest!2026';
$pdo = new PDO('sqlite:' . $SANDBOX . '/data/grocy.db');
$pdo->prepare("UPDATE users SET password = ? WHERE username IN ('essential', 'professional', 'business', 'enterprise') OR id = 1")->execute([password_hash($PASS, PASSWORD_DEFAULT)]);
$ADMIN = $pdo->query('SELECT username FROM users WHERE id = 1')->fetchColumn();
$pdo = null;

$PORT = 8097;
$env = getenv();
$env['GROCY_DATAPATH'] = 'tests/dairy/.sub_sandbox/data';
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

function req(string $who, string $method, string $path, ?array $body = null, array $cookies = []): array
{
	global $PORT, $SANDBOX;
	$ch = curl_init("http://127.0.0.1:$PORT$path");
	$jar = "$SANDBOX/cookies_$who.txt";
	$opts = [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 120, CURLOPT_FOLLOWLOCATION => false, CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar, CURLOPT_CUSTOMREQUEST => $method];
	if ($body !== null)
	{
		$isForm = $path === '/login';
		$opts[CURLOPT_POSTFIELDS] = $isForm ? http_build_query($body) : json_encode($body);
		$opts[CURLOPT_HTTPHEADER] = [$isForm ? 'Content-Type: application/x-www-form-urlencoded' : 'Content-Type: application/json'];
	}
	if ($cookies)
	{
		$opts[CURLOPT_COOKIE] = implode('; ', array_map(fn ($k, $v) => "$k=$v", array_keys($cookies), $cookies));
	}
	curl_setopt_array($ch, $opts);
	$res = (string) curl_exec($ch);
	return [curl_getinfo($ch, CURLINFO_HTTP_CODE), $res];
}

$fails = 0;
$count = 0;
function check(string $label, bool $ok, $extra = '')
{
	global $fails, $count;
	$count++;
	if (!$ok)
	{
		$fails++;
	}
	echo ($ok ? '  OK   ' : '  FAIL ') . $label . ($extra !== '' ? "  [$extra]" : '') . "\n";
}

// Arranque: la primera solicitud aplica las migraciones (incluida 0321)
for ($i = 0; $i < 60; $i++)
{
	[$code] = req('boot', 'GET', '/login');
	if ($code === 200)
	{
		break;
	}
	usleep(500000);
}
check('servidor y migraciones', $code === 200, "HTTP $code");

// Modo servidor para el E2E de navegador (tests/dairy/e2e_subscription_plans.js): queda activo hasta cerrar stdin
if ($SERVE)
{
	echo "READY $PORT $ADMIN $PASS
";
	while (fgets(STDIN) !== false)
	{
	}
	exit(0);
}

$PLANS = ['essential', 'professional', 'business', 'enterprise'];
foreach (array_merge($PLANS, [$ADMIN]) as $u)
{
	[$code] = req($u, 'POST', '/login', ['username' => $u, 'password' => $PASS]);
	[$c2] = req($u, 'GET', '/api/subscription/capabilities');
	check("inicio de sesión: $u", $code === 302 && $c2 === 200, "login $code / api $c2");
}

// Páginas representativas: plan mínimo que las incluye
$PAGES = [
	'/today' => 'essential', '/sales' => 'essential', '/pos' => 'essential', '/pos/returns' => 'essential', '/stockoverview' => 'essential',
	'/kardex' => 'essential', '/inventory' => 'essential', '/scan' => 'essential', '/waste' => 'essential', '/waste/report' => 'essential', '/reports' => 'essential', '/m' => 'essential',
	'/waste/approvals' => 'professional', '/operations/scheduling' => 'professional', '/operations/attendance' => 'professional', '/branches' => 'professional', '/transfers' => 'professional', '/kpis' => 'professional',
	'/dispatches' => 'business', '/returns' => 'business', '/mrp' => 'business', '/suppliers' => 'business', '/reconciliation' => 'business', '/locations/map' => 'business', '/warehouse' => 'business',
	'/loss-prevention' => 'enterprise', '/loss-prevention/devices' => 'enterprise', '/audit-log' => 'enterprise', '/audits' => 'enterprise',
];
$rank = array_flip($PLANS);
foreach ($PLANS as $plan)
{
	echo "\n== $plan ==\n";
	$bad = [];
	foreach ($PAGES as $page => $min)
	{
		[$code, $body] = req($plan, 'GET', $page);
		$allowed = $rank[$plan] >= $rank[$min];
		$isLock = str_contains($body, 'data-testid="plan-required"');
		$phpErr = preg_match('/<b>(Fatal error|Parse error|Warning)<\/b>|Uncaught [A-Z]\w+(Exception|Error)/', $body);
		if ($allowed ? ($code !== 200 || $isLock || $phpErr) : ($code !== 403 || !$isLock || !str_contains($body, 'data-testid="see-plans"')))
		{
			$bad[] = "{$page}→{$code}" . ($isLock ? ' (bloqueo)' : '') . ($phpErr ? ' (error PHP)' : '');
		}
	}
	check("$plan: rutas directas protegidas y tableros autorizados abren", count($bad) === 0, implode(', ', $bad));

	// Menú lateral: módulos contratados arriba; superiores solo en «Ampliar plan»
	[, $body] = req($plan, 'GET', '/stockoverview');
	$main = explode('data-cat="upgrade"', $body)[0];
	$leaks = [];
	foreach ($PAGES as $page => $min)
	{
		if ($rank[$plan] < $rank[$min] && preg_match('#class="dnav-link discrete-link"\s+href="(?:https?://[^/"]+)?' . preg_quote($page, '#') . '"#', $main))
		{
			$leaks[] = $page;
		}
	}
	check("$plan: el menú no ofrece módulos no contratados", count($leaks) === 0, implode(', ', $leaks));
	check("$plan: sección «Ampliar plan» " . ($plan === 'enterprise' ? 'ausente' : 'presente'), str_contains($body, 'data-cat="upgrade"') === ($plan !== 'enterprise'));
	check("$plan: plan visible en el encabezado", str_contains($body, 'window.DAILY_SUBSCRIPTION') && str_contains($body, '"plan":"' . $plan . '"'));
}

// APIs: lectura y escritura no autorizadas devuelven 403 con code=plan_required y el plan requerido
echo "\n== APIs ==\n";
$API = [
	['essential', 'POST', '/api/waste/1/approve', [], 'professional'],
	['essential', 'POST', '/api/waste/reasons', ['name' => 'x'], 'professional'],
	['essential', 'GET', '/api/schedule/week', null, 'professional'],
	['professional', 'GET', '/api/retail/dispatch/analytics', null, 'business'],
	['professional', 'POST', '/api/mrp/orders/generate', [], 'business'],
	['business', 'GET', '/api/devices/required', null, 'enterprise'],
	['business', 'GET', '/api/audit-log/export', null, 'enterprise'],
	['business', 'GET', '/api/loss-control/dashboard', null, 'enterprise'],
	['business', 'POST', '/api/branches/user-access/1', ['branch_ids' => [1]], 'enterprise'],
];
foreach ($API as [$who, $m, $path, $body, $need])
{
	[$code, $res] = req($who, $m, $path, $body);
	$j = json_decode($res, true) ?: [];
	check("$who $m $path → 403 requiere $need", $code === 403 && ($j['code'] ?? '') === 'plan_required' && ($j['required_plan'] ?? '') === $need, "HTTP $code " . substr($res, 0, 120));
}
foreach ([['essential', '/api/waste/reasons'], ['essential', '/api/stockoverview/executive'], ['enterprise', '/api/devices/required'], ['business', '/api/retail/dispatch/analytics']] as [$who, $path])
{
	[$code, $res] = req($who, 'GET', $path);
	check("$who GET $path permitido", $code === 200, "HTTP $code " . substr($res, 0, 100));
}
// Exportación protegida por plan
[$code] = req('essential', 'GET', '/api/reports/loss-executive/history');
check('essential: exportes de prevención bloqueados', $code === 403);

// Roles dentro del plan: un Empleado no abre lo que su rol no permite aunque el plan lo incluya
echo "\n== Roles ==\n";
[$code] = req('enterprise', 'GET', '/kardex', null, ['daily_simulated_role' => 'empleado']);
check('enterprise + rol empleado: Kardex bloqueado por rol', $code === 403);
[$code] = req('enterprise', 'GET', '/pos', null, ['daily_simulated_role' => 'empleado']);
check('enterprise + rol empleado: POS disponible', $code === 200);
[$code, $body] = req('essential', 'GET', '/operations/attendance', null, ['daily_simulated_role' => 'empleado']);
check('essential + rol empleado: asistencia bloqueada por plan (pantalla de plan)', $code === 403 && str_contains($body, 'plan-required'));
[$code] = req('professional', 'GET', '/users', null, ['daily_simulated_role' => 'supervisor']);
check('professional + supervisor: usuarios bloqueado por rol', $code === 403);

// Móvil: mismas restricciones
echo "\n== Móvil ==\n";
[$c, $res] = req('essential', 'GET', '/api/m/me');
$me = json_decode($res, true) ?: [];
check('essential móvil: sin aprobaciones ni equipo', $c === 200 && ($me['plan'] ?? '') === 'essential' && empty($me['can_approve']) && empty($me['can_team']) && !in_array('/mrp', $me['web'] ?? [], true), json_encode(array_intersect_key($me, array_flip(['plan', 'can_approve', 'can_team', 'web']))));
[$c] = req('essential', 'GET', '/api/m/approvals');
check('essential móvil: API de aprobaciones bloqueada', $c === 403);
[$c] = req('essential', 'GET', '/api/m/home');
check('essential móvil: inicio disponible', $c === 200);
[$c, $res] = req('enterprise', 'GET', '/api/m/me');
$me = json_decode($res, true) ?: [];
check('enterprise móvil: herramientas completas', $c === 200 && ($me['plan'] ?? '') === 'enterprise' && in_array('/mrp', $me['web'] ?? [], true));

// Vista previa: nunca amplía
[$code] = req('essential', 'GET', '/loss-prevention', null, ['daily_simulated_plan' => 'enterprise']);
check('cookie de vista previa no amplía el plan', $code === 403);

// Cambio de plan en caliente (sin cerrar sesión) y downgrade sin pérdida de datos
echo "\n== Cambios de plan ==\n";
[$code] = req('essential', 'POST', '/api/subscription/change', ['user_id' => 0, 'plan' => 'enterprise']);
check('una cuenta de evaluación no se amplía a sí misma', $code === 403);
[$code, $res] = req('essential', 'POST', '/api/subscription/request', ['plan' => 'professional', 'message' => 'Necesitamos aprobaciones']);
$reqId = json_decode($res, true)['id'] ?? 0;
check('solicitud de upgrade registrada', $code === 200 && $reqId > 0, substr($res, 0, 100));
[$code] = req($ADMIN, 'POST', "/api/subscription/requests/$reqId/decide", ['decision' => 'approved']);
check('administrador aprueba la solicitud', $code === 200);
[$code] = req('essential', 'GET', '/waste/approvals');
check('upgrade activo en la misma sesión', $code === 200);
[$c1, $before] = req('essential', 'GET', '/api/waste/records?limit=5');
[$code] = req($ADMIN, 'POST', '/api/subscription/change', ['user_id' => intval(json_decode(req('essential', 'GET', '/api/m/me')[1], true)['user_id'] ?? 0), 'plan' => 'essential']);
check('downgrade confirmado', $code === 200);
[$code] = req('essential', 'GET', '/waste/approvals');
check('downgrade restringe en la misma sesión', $code === 403);
[$c2, $after] = req('essential', 'GET', '/api/waste/records?limit=5');
check('downgrade conserva el historial de mermas', $c1 === 200 && $c2 === 200 && $before === $after);
[$code, $res] = req('essential', 'GET', '/api/subscription');
$ov = json_decode($res, true) ?: [];
check('panel: historial con upgrade y downgrade', $code === 200 && count(array_filter($ov['history'] ?? [], fn ($h) => in_array($h['event'], ['upgrade', 'downgrade'], true))) >= 2);
[$code, $body] = req('essential', 'GET', '/subscription');
check('panel de suscripción renderiza', $code === 200 && str_contains($body, 'id="planes"') && str_contains($body, 'Precio por cotización'));
check('panel sin precios inventados', !preg_match('/(USD|US\$|\$\s?\d+[.,]?\d*\s?\/\s?mes|per month)/i', strip_tags(explode('<script', $body)[0])));

// Suscripción suspendida: modo consulta
[$code] = req($ADMIN, 'POST', '/api/subscription/change', ['status' => 'suspended']);
[$cw, $rw] = req($ADMIN, 'POST', '/api/waste/1/approve', []);
[$cr] = req($ADMIN, 'GET', '/api/waste/reasons');
check('suspendida: escritura bloqueada, lectura permitida', $code === 200 && $cw === 403 && (json_decode($rw, true)['code'] ?? '') === 'subscription_inactive' && $cr === 200, "$cw / $cr");
req($ADMIN, 'POST', '/api/subscription/change', ['status' => 'active']);

$log = @file_get_contents($SANDBOX . '/server.log') ?: '';
check('sin errores fatales en el servidor', !preg_match('/PHP Fatal|Uncaught/', $log), substr(implode(' | ', array_slice(preg_grep('/Fatal|Uncaught/', explode("\n", $log)), 0, 3)), 0, 300));

echo "\n$count verificaciones, $fails fallidas\n";
exit($fails > 0 ? 1 : 0);
