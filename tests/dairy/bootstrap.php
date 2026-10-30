<?php
/**
 * Arranque común de las pruebas DAIRY: copia la base de datos a un directorio temporal, carga Grocy y migra.
 * Usuario de la prueba: variable de entorno DAIRY_TEST_USER_ID (por defecto 1).
 * Base de datos de origen: primer argumento o data/grocy[_<cultura>].db.
 */

$root = dirname(__DIR__, 2);
$tmp = sys_get_temp_dir() . '/dairy_tests_' . getmypid();
@mkdir($tmp . '/viewcache', 0777, true);
copy($root . '/data/config.php', $tmp . '/config.php');

define('GROCY_DATAPATH', $tmp);
define('GROCY_USER_ID', intval(getenv('DAIRY_TEST_USER_ID') ?: 1));
require_once $root . '/packages/autoload.php';
require_once $tmp . '/config.php';
require_once $root . '/config-dist.php';
if (GROCY_TIMEZONE !== '')
{
	date_default_timezone_set(GROCY_TIMEZONE);
}

$dbName = (GROCY_MODE === 'demo' || GROCY_MODE === 'prerelease') ? 'grocy_' . (defined('GROCY_DEMO_DB_SUFFIX') ? GROCY_DEMO_DB_SUFFIX : GROCY_DEFAULT_LOCALE) . '.db' : 'grocy.db';
$src = $argv[1] ?? ($root . '/data/' . $dbName);
if (!is_file($src) || !copy($src, $tmp . '/' . $dbName))
{
	fwrite(STDERR, "No se pudo copiar la base de datos $src\n");
	exit(2);
}

\Grocy\Services\DatabaseMigrationService::GetInstance()->MigrateDatabase();
$db = \Grocy\Services\DatabaseService::GetInstance()->GetDbConnectionRaw();

$fails = 0;
$count = 0;
function ok(string $label, bool $cond, $info = '')
{
	global $fails, $count;
	$count++;
	if (!$cond)
	{
		$fails++;
	}
	echo ($cond ? '  OK   ' : '  FAIL ') . $label . ($info !== '' ? "  [$info]" : '') . "\n";
}
function expectError(string $label, callable $fn)
{
	try
	{
		$fn();
		ok($label . ' (debía fallar)', false);
	}
	catch (\Throwable $e)
	{
		ok($label, true, $e->getMessage());
	}
}
$val = fn(string $sql) => $db->query($sql)->fetchColumn();
