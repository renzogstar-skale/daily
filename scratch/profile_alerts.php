<?php
chdir(__DIR__ . '/../public');
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/alerts';
$_SERVER['SCRIPT_NAME'] = '/index.php';

require __DIR__ . '/../packages/autoload.php';
define('GROCY_DATAPATH', __DIR__ . '/../data');
require __DIR__ . '/../app.php';

$svc = \Grocy\Services\AlertCenterService::GetInstance();
$t0 = microtime(true);

$step = function($name) use (&$t0) {
    $now = microtime(true);
    echo "$name: " . round(($now - $t0) * 1000, 2) . "ms\n";
    $t0 = $now;
};

$t0 = microtime(true);
$scan = $svc->ScanIfStale();
$step("ScanIfStale");

$filters = \Grocy\Controllers\Api\AlertCenterApiController::Filters([]);
$step("Filters");

$list = $svc->List($filters, 'all', 1, 25, 'priority');
$step("List");

$dash = $svc->Dashboard($filters, '7d', 30, null, null);
$step("Dashboard");

$avoided = $svc->AvoidedLosses(null, $filters['allowed']);
$step("AvoidedLosses");

$rules = $svc->GetRules();
$step("GetRules");

$branches = \Grocy\Services\BranchService::GetInstance()->GetBranches();
$step("GetBranches");

$depts = $svc->Departments();
$step("Departments");

$views = $svc->Views(1);
$step("Views");

$notifications = $svc->UserNotifications(1, 15);
$step("UserNotifications");
