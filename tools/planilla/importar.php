<?php
/**
 * Carga en la base de DAILY el JSON de extraer_planilla.py. Haga una copia de data/grocy_*.db antes de ejecutarlo.
 * Uso: php tools/planilla/importar.php <planilla.json>
 */

$root = dirname(__DIR__, 2);
define('GROCY_DATAPATH', $root . '/data');
define('GROCY_USER_ID', 1);
require_once $root . '/packages/autoload.php';
require_once $root . '/data/config.php';
require_once $root . '/config-dist.php';
if (GROCY_TIMEZONE !== '')
{
	date_default_timezone_set(GROCY_TIMEZONE);
}

\Grocy\Services\DatabaseMigrationService::GetInstance()->MigrateDatabase();
$result = \Grocy\Services\WorkforcePlanillaImportService::GetInstance()->Import(json_decode(file_get_contents($argv[1]), true));
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), "\n";
