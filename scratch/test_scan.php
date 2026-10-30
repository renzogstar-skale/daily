<?php
define('GROCY_DATAPATH', __DIR__ . '/../data');
define('GROCY_USER_ID', 1);
require __DIR__ . '/../app.php';
$res = \Grocy\Services\AlertCenterService::GetInstance()->Scan(1, 1, true);
echo json_encode($res, JSON_PRETTY_PRINT);
