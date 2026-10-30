<?php
/**
 * DAIRY — programador de tareas (Etapa 34). Ejecutar cada 15–60 min desde el Programador de tareas de Windows o cron:
 *   php bin/dairy_scheduler.php            → reportes programados vencidos + barrido de alertas
 *   php bin/dairy_scheduler.php --dry-run  → solo lista lo que está vencido
 * Cada reporte corre con los permisos y sucursales de su dueño. Sin sesión web.
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
\Grocy\Services\DatabaseMigrationService::GetInstance()->MigrateDatabase();

$dry = in_array('--dry-run', $argv, true);
$db = \Grocy\Services\DatabaseService::GetInstance()->GetDbConnectionRaw();
if ($dry)
{
	foreach ($db->query("SELECT id, name, schedule, next_run_at FROM dairy_report_definitions WHERE active = 1 AND schedule != 'none' AND next_run_at <= datetime('now', 'localtime')") as $r)
	{
		echo "vencido #{$r['id']} {$r['name']} ({$r['schedule']}, {$r['next_run_at']})\n";
	}
	exit(0);
}
$runs = \Grocy\Services\ReportBuilderService::GetInstance()->RunDue();
foreach ($runs as $r)
{
	echo date('Y-m-d H:i:s') . " reporte #{$r['id']}: {$r['status']} · {$r['rows']} filas" . ($r['error'] ? " · {$r['error']}" : '') . "\n";
}
try
{
	// Push: reintenta los pendientes (sin conexión al encolar) y consulta los recibos de Expo
	$push = \Grocy\Services\PushService::GetInstance();
	$d = $push->Dispatch();
	$rc = $push->CheckReceipts();
	if ($d['sent'] + $d['error'] + $rc['ok'] + $rc['error'] > 0)
	{
		echo date('Y-m-d H:i:s') . " push: enviados {$d['sent']} · error {$d['error']} · recibos ok {$rc['ok']} · recibos con error {$rc['error']}\n";
	}
}
catch (\Throwable $e)
{
	echo 'push: error ' . $e->getMessage() . "\n";
}
try
{
	foreach (\Grocy\Services\LossExecutiveReportService::GetInstance()->RunDue('schedule') as $r)
	{
		echo date('Y-m-d H:i:s') . " informe ejecutivo {$r['period_type']} {$r['code']}: {$r['status']}" . (isset($r['completeness']) ? " · {$r['completeness']} · {$r['recipients']} destinatarios" : '') . (isset($r['error']) ? " · {$r['error']}" : '') . "\n";
	}
}
catch (\Throwable $e)
{
	echo 'informe ejecutivo: error ' . $e->getMessage() . "\n";
}
try
{
	$scan =\Grocy\Services\AlertCenterService::GetInstance()->Scan(null, null);
	echo date('Y-m-d H:i:s') . " alertas: creadas {$scan['created']} · actualizadas {$scan['refreshed']} · cerradas {$scan['auto_closed']}\n";
}
catch (\Throwable $e)
{
	echo 'alertas: error ' . $e->getMessage() . "\n";
}
try
{
	$auto = \Grocy\Services\AutomationEngineService::GetInstance()->RetryPending();
	echo date('Y-m-d H:i:s') . " automatizaciones: reintentadas {$auto['retried']} · correctas {$auto['success']} · fallidas {$auto['failed']}\n";
}
catch (\Throwable $e)
{
	echo 'automatizaciones: error ' . $e->getMessage() . "\n";
}
exit(count(array_filter($runs, fn($r) => $r['status'] !== 'ok')) > 0 ? 1 : 0);
if (GROCY_MODE === 'demo')
{
	try
	{
		// Empresa de ejemplo: continúa la operación simulada hasta la última hora completa
		$c = \Grocy\Services\DailyCompanySeedService::GetInstance()->CatchUp(300);
		if (isset($c['until']))
		{
			echo date('Y-m-d H:i:s') . " demo: operación simulada {$c['from']} → {$c['until']} · ventas " . ($c['sales'] ?? 0) . "\n";
		}
	}
	catch (\Throwable $e)
	{
		echo 'demo: error ' . $e->getMessage() . "\n";
	}
}
