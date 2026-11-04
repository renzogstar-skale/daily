<?php

// DAILY — Roles de prevención de pérdidas. Cada rol corre en su propio proceso porque GROCY_USER_ID es constante.
// Sin DAILY_LP_ROLE actúa como lanzador; el expediente DEMO-001 es de Hallandale Beach (sucursal 1).

$role = getenv('DAILY_LP_ROLE');
if (!$role)
{
	$fails = 0;
	foreach (['empleado' => 99901, 'supervisor' => 99902, 'manager' => 99903, 'otra_sucursal' => 99904] as $r => $uid)
	{
		putenv("DAILY_LP_ROLE=$r");
		putenv("DAIRY_TEST_USER_ID=$uid");
		echo "\n== $r\n";
		passthru(PHP_BINARY . ' ' . escapeshellarg(__FILE__) . ' ' . escapeshellarg($argv[1] ?? ''), $code);
		$fails += $code;
	}
	echo $fails ? "\nFALLAS EN ROLES\n" : "\nRoles: 0 fallos\n";
	exit($fails ? 1 : 0);
}
if (($argv[1] ?? '') === '')
{
	unset($argv[1]);
}

require __DIR__ . '/bootstrap.php';

use Grocy\Controllers\Users\User;
use Grocy\Services\ReconciliationService;
use Grocy\Services\SensitiveCaseService;

$uid = GROCY_USER_ID;
$perms = [
	'empleado' => [User::PERMISSION_SCAN_OPERATE, User::PERMISSION_STOCK_INVENTORY],
	'supervisor' => [User::PERMISSION_LOSS_PREVENTION],
	'manager' => [User::PERMISSION_INVENTORY_ADJUST_APPROVE, User::PERMISSION_LOSS_PREVENTION],
	'otra_sucursal' => [User::PERMISSION_LOSS_PREVENTION, User::PERMISSION_INVENTORY_ADJUST_APPROVE, User::PERMISSION_SCAN_OPERATE],
][$role];
$db->prepare("INSERT INTO users (id, username, password) VALUES (?, ?, 'x')")->execute([$uid, "lp_$role"]);
foreach ($perms as $p)
{
	$db->prepare('INSERT INTO user_permissions (permission_id, user_id) SELECT id, ? FROM permission_hierarchy WHERE name = ?')->execute([$uid, $p]);
}
if ($role === 'otra_sucursal')
{
	$db->prepare('INSERT INTO user_branches (user_id, branch_id) VALUES (?, 2)')->execute([$uid]);
}

$svc = SensitiveCaseService::GetInstance();
$rec = ReconciliationService::GetInstance();
$d1 = intval($val("SELECT id FROM sensitive_investigation_cases WHERE case_code = 'DEMO-001'"));
$d2 = intval($val("SELECT id FROM sensitive_investigation_cases WHERE case_code = 'DEMO-002'"));
$forbidden = function (string $label, callable $fn) {
	try
	{
		$fn();
		ok("$label: debía rechazarse", false);
	}
	catch (\Throwable $e)
	{
		ok($label, $e->getCode() === 403, $e->getMessage());
	}
};

if ($role === 'empleado')
{
	ok('ve sus conteos', count($svc->Tasks(1, $uid)) > 0);
	ok('adjunta evidencia', $svc->AddEvidence($d1, ['title' => 'Foto de la vitrina'], $uid) > 0);
	$forbidden('no consulta el expediente', fn() => $svc->Detail($d1));
	$forbidden('no propone ajustes', fn() => $svc->ProposeAdjustment($d1, 'x', $uid));
	$forbidden('no solicita reconteos', fn() => $svc->RequestRecount($d1, 'x', $uid));
	$forbidden('no aprueba', fn() => $rec->AuthorizeAdjustment($d1, 'approved', 'x', $uid));
	$forbidden('no cierra', fn() => $svc->CloseCase($d1, 'resuelto', 'cierre indebido', $uid));
	$forbidden('no programa conteos', fn() => $svc->SavePlan(['branch_id' => 1, 'category_id' => 1, 'frequency' => 'daily'], $uid));
}
elseif ($role === 'supervisor')
{
	ok('consulta el expediente', count($svc->Detail($d1)['timeline']) > 0);
	ok('propone el ajuste', $svc->ProposeAdjustment($d1, 'Sin evidencia de ubicación', $uid)['success']);
	$forbidden('no aprueba su propia propuesta', fn() => $rec->AuthorizeAdjustment($d1, 'approved', 'x', $uid));
	$forbidden('no cierra', fn() => $svc->CloseCase($d1, 'no_resuelto', 'sin causa', $uid));
}
elseif ($role === 'manager')
{
	$svc->ProposeAdjustment($d1, 'Propuesto por este mismo usuario', $uid);
	expectError('no aprueba lo que propuso (segregación)', fn() => $rec->AuthorizeAdjustment($d1, 'approved', 'x', $uid));
	expectError('cierre exige justificación', fn() => $svc->CloseCase($d1, 'no_resuelto', ' ', $uid));
	expectError('no se cierra como no resuelto sin investigar', fn() => $svc->CloseCase($d2, 'no_resuelto', 'Sin reconteo todavía', $uid));
	ok('cierra con justificación', $svc->CloseCase($d1, 'no_resuelto', 'Sin causa demostrable tras revisar documentos', $uid)['success']);
	ok('bitácora conserva quién cerró', intval($val("SELECT user_id FROM sensitive_case_events WHERE case_id = $d1 ORDER BY id DESC LIMIT 1")) === $uid);
}
else
{
	$forbidden('no abre expediente de otra sucursal', fn() => $svc->Detail($d1));
	$forbidden('no cuenta en otra sucursal', fn() => $svc->RegisterCount(1, 9001, 6, [], $uid));
	$forbidden('no cierra expediente de otra sucursal', fn() => $svc->CloseCase($d1, 'resuelto', 'cierre indebido', $uid));
	ok('su resumen no incluye la sucursal ajena', $svc->Summary()['open_value'] == 0);
	ok('su lista tampoco', count($svc->Cases()) === 0);
}

echo "$count verificaciones · $fails FALLAS\n";
exit($fails ? 1 : 0);
