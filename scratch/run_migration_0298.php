<?php
define('GROCY_DATAPATH', dirname(__DIR__) . '/data');
define('GROCY_USER_ID', 1);
require_once dirname(__DIR__) . '/packages/autoload.php';
require_once GROCY_DATAPATH . '/config.php';
require_once dirname(__DIR__) . '/config-dist.php';
if (GROCY_TIMEZONE !== '')
{
	date_default_timezone_set(GROCY_TIMEZONE);
}

\Grocy\Services\DatabaseMigrationService::GetInstance()->MigrateDatabase();
echo "MIGRATION 0298 EXECUTED SUCCESSFULLY ON GROCY_DATAPATH!\n";
