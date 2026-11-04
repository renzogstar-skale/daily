<?php

// Proceso auxiliar de test_device_control.php: compite por el mismo equipo sobre la misma base de datos.
// php race_device.php <datapath> <imei> <zona> <hora-de-salida>

$root = dirname(__DIR__, 2);
define('GROCY_DATAPATH', $argv[1]);
define('GROCY_USER_ID', 1);
require_once $root . '/packages/autoload.php';
require_once $argv[1] . '/config.php';
require_once $root . '/config-dist.php';

$svc = \Grocy\Services\DeviceService::GetInstance();
\Grocy\Services\DatabaseService::GetInstance()->GetDbConnectionRaw();
while (microtime(true) < floatval($argv[4]))
{
	usleep(200);
}
try
{
	$svc->RequestMove($argv[2], $argv[3], 1);
	echo 'WIN';
}
catch (\Throwable $e)
{
	echo 'LOSE:' . $e->getMessage();
}
