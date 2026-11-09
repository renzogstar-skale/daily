<?php
/**
 * Suscripciones DAILY: catálogo, herencia acumulativa, mapa ruta → capacidad contra las rutas reales, menú por plan,
 * cambios de plan (upgrade/downgrade sin pérdida de datos) y suscripción inactiva. Corre sobre una copia de la BD.
 *
 *   php tests/dairy/test_subscription_plans.php
 */

require __DIR__ . '/bootstrap.php';

use Grocy\Helpers\DailySubscription as DS;
use Grocy\Helpers\DairyNavigation;
use Grocy\Services\SubscriptionService;

$asPlan = function (?string $plan) use ($db) {
	$db->exec('DELETE FROM daily_subscription_user_overrides WHERE user_id = 1');
	if ($plan)
	{
		$db->prepare('INSERT INTO daily_subscription_user_overrides (user_id, plan) VALUES (1, ?)')->execute([$plan]);
	}
	DS::Reset();
};

// 1. Catálogo y herencia
$prev = [];
foreach (DS::PLANS as $p)
{
	$caps = DS::CapabilitiesFor($p);
	ok("$p incluye todo el plan anterior", count(array_diff($prev, $caps)) === 0);
	ok("$p agrega capacidades propias", count(DS::AddedIn($p)) > 0);
	$prev = $caps;
}
ok('Enterprise incluye todo el catálogo', count(DS::CapabilitiesFor('enterprise')) === count(DS::CAPABILITIES));
foreach (DS::CAPABILITIES as $id => $c)
{
	if (!preg_match('/^[a-z0-9_]+\.[a-z_]+$/', $id) || !isset(DS::GROUPS[$c['group']]) || DS::PlanRank($c['plan']) < 0 || empty($c['en']))
	{
		ok("capacidad bien formada: $id", false);
	}
}
foreach (['waste.approve', 'waste.investigate', 'workforce.schedule', 'workforce.attendance', 'branches.manage', 'transfers.manage'] as $c)
{
	ok("$c pertenece a Professional", DS::RequiredPlan($c) === 'professional');
}
foreach (['dispatch.manage', 'logistics.returns', 'planning.mrp', 'procurement.manage', 'reconciliation.manage', 'warehouse.locations'] as $c)
{
	ok("$c pertenece a Business", DS::RequiredPlan($c) === 'business');
}
foreach (['loss_prevention.access', 'devices.imei', 'branches.roles', 'audit.view'] as $c)
{
	ok("$c pertenece a Enterprise", DS::RequiredPlan($c) === 'enterprise');
}

// 2. Mapa de rutas: casos de la matriz oficial
$expect = [
	['/pos/returns', 'GET', 'pos.returns'], ['/api/pos/returns/process', 'POST', 'pos.returns'], ['/returns', 'GET', 'logistics.returns'],
	['/api/retail/returns/{id}/advance', 'POST', 'logistics.returns'], ['/pos', 'GET', 'pos.access'], ['/kardex', 'GET', 'inventory.kardex'],
	['/inventory', 'GET', 'inventory.count'], ['/inventory/stockouts', 'GET', 'inventory.view'], ['/scan', 'GET', 'scanner.access'],
	['/m', 'GET', 'mobile.access'], ['/waste/report', 'GET', 'waste.register'], ['/api/waste/records', 'POST', 'waste.register'],
	['GET /api/waste/reasons', 'GET', 'waste.register'], ['/api/waste/reasons', 'POST', 'waste.approve'], ['/api/waste/{wasteId}/approve', 'POST', 'waste.approve'],
	['/api/waste/cases/{caseId}/investigation', 'POST', 'waste.investigate'], ['/today', 'GET', 'today.view'], ['/reports', 'GET', 'reports.basic'],
	['/operations/scheduling', 'GET', 'workforce.schedule'], ['/api/attendance/clock-in', 'POST', 'workforce.attendance'], ['/branches', 'GET', 'branches.manage'],
	['/transfers', 'GET', 'transfers.manage'], ['/api/stock/products/{productId}/transfer', 'POST', 'transfers.manage'], ['/dispatches', 'GET', 'dispatch.manage'],
	['/mrp', 'GET', 'planning.mrp'], ['/suppliers', 'GET', 'procurement.manage'], ['/reconciliation', 'GET', 'reconciliation.manage'],
	['/locations/map', 'GET', 'warehouse.locations'], ['/loss-prevention', 'GET', 'loss_prevention.access'], ['/loss-prevention/devices', 'GET', 'devices.imei'],
	['/api/devices/register', 'POST', 'devices.imei'], ['/audit-log', 'GET', 'audit.view'], ['/api/audit-log/export', 'GET', 'audit.view'],
	['/api/branches/user-access/{userId}', 'POST', 'branches.roles'], ['/api/workforce/quick-waste', 'POST', 'waste.register'],
	['/api/waste/export', 'GET', 'waste.register'], ['/api/reports/loss-executive/{id}/xlsx', 'GET', 'loss_prevention.access'],
	['/login', 'GET', null], ['/subscription', 'GET', null], ['/api/subscription/request', 'POST', null], ['/users', 'GET', null], ['/possible', 'GET', null],
];
foreach ($expect as [$route, $method, $cap])
{
	$route = preg_replace('/^[A-Z]+ /', '', $route);
	ok("ruta $method $route → " . ($cap ?? 'plataforma'), DS::CapabilityForRoute($route, $method) === $cap, DS::CapabilityForRoute($route, $method));
}

// 3. Cada regla corresponde a rutas reales y cada capacidad con ruta existe en el catálogo
$src = file_get_contents(dirname(__DIR__, 2) . '/routes.php');
$web = substr($src, 0, strpos($src, "\$app->group('/api'"));
$api = substr($src, strpos($src, "\$app->group('/api'"));
preg_match_all("/->(?:get|post|put|delete|patch)\('([^']+)'/", $web, $m1);
preg_match_all("/->(?:get|post|put|delete|patch)\('([^']+)'/", $api, $m2);
$routes = array_unique(array_merge($m1[1], array_map(fn ($r) => '/api' . $r, $m2[1])));
foreach (DS::ROUTES as $rule => $cap)
{
	$prefix = preg_replace('/^[A-Z]+ /', '', $rule);
	$hit = count(array_filter($routes, fn ($r) => $r === $prefix || str_starts_with($r, $prefix . '/'))) > 0;
	if (!$hit || !isset(DS::CAPABILITIES[$cap]))
	{
		ok("regla $rule apunta a rutas reales y a una capacidad del catálogo", false);
	}
}
ok('reglas de rutas verificadas', true, count(DS::ROUTES) . ' reglas / ' . count($routes) . ' rutas');

// 4. Menú por plan: solo módulos contratados; lo superior va a «Ampliar plan»; planes superiores ven más
$counts = [];
foreach (DS::PLANS as $p)
{
	$asPlan($p);
	$nav = DairyNavigation::Build('today', fn ($s) => $s);
	$routesInMenu = [];
	foreach ($nav as $cat)
	{
		foreach ($cat['items'] as $it)
		{
			$routesInMenu[] = $it['route'];
		}
	}
	$leak = array_filter($routesInMenu, fn ($r) => !str_starts_with($r, '#') && DS::MissingForRoute($r) !== null);
	ok("$p: el menú no muestra módulos no contratados", count($leak) === 0, implode(', ', $leak));
	$locked = DairyNavigation::Locked(fn ($s) => $s);
	$bad = array_filter(array_keys($locked), fn ($lp) => DS::PlanRank($lp) <= DS::PlanRank($p));
	ok("$p: «Ampliar plan» solo ofrece planes superiores", count($bad) === 0);
	ok("$p: " . ($p === 'enterprise' ? 'sin' : 'con') . ' sección de ampliación', ($p === 'enterprise') === (count($locked) === 0));
	$counts[$p] = count($routesInMenu);
	$css = DS::LockedSelectors();
	ok("$p: herramientas internas bloqueadas = " . count($css), $p !== 'enterprise' ? count($css) > 0 : count($css) === 0);
}
ok('planes superiores ven igual o más módulos', $counts['essential'] < $counts['professional'] && $counts['professional'] < $counts['business'] && $counts['business'] < $counts['enterprise'], json_encode($counts));
$asPlan('essential');
ok('Essential conserva sus módulos (POS, devoluciones POS, Kardex, conteos, escáner, mermas, HOY)', count(array_filter(['/pos', '/pos/returns', '/kardex', '/inventory', '/scan', '/waste/report', '/today', '/reports'], fn ($r) => DS::MissingForRoute($r) === null)) === 8);
ok('Essential no abre aprobaciones ni devoluciones logísticas', DS::MissingForRoute('/waste/approvals') === 'waste.approve' && DS::MissingForRoute('/returns') === 'logistics.returns');

// 5. Vista previa: solo reduce
$asPlan('professional');
$_COOKIE['daily_simulated_plan'] = 'enterprise';
DS::Reset();
ok('la vista previa no amplía el plan', DS::Plan() === 'professional');
$_COOKIE['daily_simulated_plan'] = 'essential';
DS::Reset();
ok('la vista previa reduce el plan', DS::Plan() === 'essential' && !empty(DS::Current()['preview']));
unset($_COOKIE['daily_simulated_plan']);

// 6. Cambios de plan (empresa) sin pérdida de datos e historial
$asPlan(null);
$svc = SubscriptionService::GetInstance();
$before = intval($db->query('SELECT (SELECT COUNT(*) FROM waste_records) + (SELECT COUNT(*) FROM stock_log)')->fetchColumn());
$hist = intval($db->query('SELECT COUNT(*) FROM daily_subscription_history')->fetchColumn());
ok('el administrador puede confirmar cambios', $svc->CanManage());
$svc->Change(['plan' => 'essential', 'note' => 'prueba downgrade']);
ok('downgrade aplicado en la misma sesión', DS::Plan() === 'essential' && DS::MissingForRoute('/loss-prevention') !== null);
$svc->Change(['plan' => 'business']);
ok('upgrade aplicado en la misma sesión', DS::Plan() === 'business' && DS::MissingForRoute('/dispatches') === null && DS::MissingForRoute('/audit-log') !== null);
ok('los cambios no borran datos', intval($db->query('SELECT (SELECT COUNT(*) FROM waste_records) + (SELECT COUNT(*) FROM stock_log)')->fetchColumn()) === $before);
$events = $db->query('SELECT event FROM daily_subscription_history ORDER BY id DESC LIMIT 2')->fetchAll(PDO::FETCH_COLUMN);
ok('historial registra downgrade y upgrade', intval($db->query('SELECT COUNT(*) FROM daily_subscription_history')->fetchColumn()) === $hist + 2 && $events === ['upgrade', 'downgrade'], json_encode($events));
expectError('plan inexistente rechazado', fn () => $svc->Change(['plan' => 'platinum']));

// 7. Solicitud de ampliación → aprobación
$req = $svc->RequestUpgrade(['plan' => 'enterprise', 'message' => 'IMEI']);
ok('solicitud creada', $req['id'] > 0 && !$req['duplicate']);
ok('solicitud duplicada no se repite', $svc->RequestUpgrade(['plan' => 'enterprise'])['duplicate'] === true);
$svc->DecideRequest($req['id'], ['decision' => 'approved']);
ok('aprobar la solicitud activa el plan', DS::Plan() === 'enterprise');

// 8. Suscripción inactiva: modo consulta
$svc->Change(['status' => 'suspended']);
ok('suspendida: bloquea escrituras', DS::WriteBlockedByStatus('/api/waste/records', 'POST'));
ok('suspendida: permite lecturas', !DS::WriteBlockedByStatus('/api/waste/records', 'GET'));
ok('suspendida: permite gestionar la suscripción', !DS::WriteBlockedByStatus('/api/subscription/request', 'POST'));
$svc->Change(['status' => 'active', 'expires_at' => date('Y-m-d', strtotime('-1 day'))]);
ok('vencida por fecha: inactiva', !DS::IsActive());
$svc->Change(['expires_at' => '']);
ok('reactivada', DS::IsActive());

// 9. Cuenta de evaluación: no puede confirmar cambios
$asPlan('professional');
ok('cuenta de evaluación no confirma cambios de plan', !$svc->CanManage());
expectError('cuenta de evaluación: cambio rechazado', fn () => $svc->Change(['plan' => 'enterprise']));
$asPlan(null);

echo "
$count verificaciones, $fails fallidas
";
exit($fails > 0 ? 1 : 0);
