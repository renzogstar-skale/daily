<?php
chdir(__DIR__ . '/../public');
require __DIR__ . '/../packages/autoload.php';
define('GROCY_DATAPATH', __DIR__ . '/../data');
require __DIR__ . '/../app.php';

$svc = \Grocy\Services\AlertCenterService::GetInstance();
$fd = [];
$per = \Grocy\Services\AlertCenterService::Period('7d', null, null);

$t0 = microtime(true);
$step = function($name) use (&$t0) {
    $now = microtime(true);
    echo "$name: " . round(($now - $t0) * 1000, 2) . "ms\n";
    $t0 = $now;
};

// Test subparts of Dashboard
$cmd = $svc->Command($fd, $per);
$step("Command");

$impact = $svc->Impact($fd);
$step("Impact");

$res = $svc->Resolution($fd, $per);
$step("Resolution");

$upcoming = $svc->Upcoming($fd);
$step("Upcoming");

$recs = $svc->Recommendations($fd);
$step("Recommendations");

