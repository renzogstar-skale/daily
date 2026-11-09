<?php
/**
 * DAIRY — precalcula el tablero de entrada (Stock) en data/cache para que cargue de inmediato tras iniciar sesión.
 * Lo lanza la pantalla de login en segundo plano (StockExecutiveOverviewService::WarmInBackground).
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
@set_time_limit(0);
\Grocy\Services\StockExecutiveOverviewService::GetInstance()->WarmExecutiveData();
