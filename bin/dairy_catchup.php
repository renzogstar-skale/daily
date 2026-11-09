<?php
/**
 * DAIRY — empresa de ejemplo: completa las horas simuladas que falten (DailyCompanySeedService::CatchUp).
 * Lo lanza la propia app en segundo plano (DemoCatchUpInBackground) para no bloquear la petición web;
 * también puede ejecutarse a mano: php bin/dairy_catchup.php
 */

$root = dirname(__DIR__);
define('GROCY_DATAPATH', getenv('GROCY_DATAPATH') ?: $root . '/data');
require_once $root . '/packages/autoload.php';
require_once GROCY_DATAPATH . '/config.php';
require_once $root . '/config-dist.php';
if (GROCY_TIMEZONE !== '')
{
	date_default_timezone_set(GROCY_TIMEZONE);
}
if (GROCY_MODE !== 'demo')
{
	exit(0);
}

$r = \Grocy\Services\DailyCompanySeedService::GetInstance()->CatchUp(300);
if (isset($r['until']))
{
	echo date('Y-m-d H:i:s') . " demo: operación simulada {$r['from']} → {$r['until']} en {$r['seconds']} s\n";
	// Deja listo el tablero de entrada (Stock) para que el próximo inicio de sesión no lo recalcule
	try
	{
		\Grocy\Services\StockExecutiveOverviewService::GetInstance()->WarmExecutiveData();
	}
	catch (\Throwable $e)
	{
	}
}
