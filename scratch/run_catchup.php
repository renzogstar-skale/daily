<?php
define('GROCY_IS_EMBEDDED_INSTALL', false);
define('GROCY_DATAPATH', __DIR__ . '/../data');
define('GROCY_USER_ID', 1);

require_once __DIR__ . '/../packages/autoload.php';
require_once __DIR__ . '/../app.php';

use Grocy\Services\DailyCompanySeedService;

$seedSvc = DailyCompanySeedService::GetInstance();
$res = $seedSvc->CatchUp(45);
print_r($res);
