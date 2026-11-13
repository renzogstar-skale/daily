<?php

use Grocy\Controllers\ExceptionController;
use Grocy\Helpers\SlimBladeView;
use Grocy\Helpers\UrlManager;
use Grocy\Middleware\LocaleMiddleware;
use Psr\Container\ContainerInterface as Container;
use Slim\Factory\AppFactory;

// Load composer dependencies
require_once __DIR__ . '/packages/autoload.php';

// Load config files
require_once GROCY_DATAPATH . '/config.php';
require_once __DIR__ . '/config-dist.php'; // For not in own config defined values we use the default ones

// Zona horaria de PHP: debe coincidir con la del sistema operativo, que SQLite usa en datetime('now', 'localtime')
if (GROCY_TIMEZONE !== '')
{
	date_default_timezone_set(GROCY_TIMEZONE);
}

// Error reporting definitions
if (GROCY_MODE === 'dev')
{
	error_reporting(E_ALL);
}
else
{
	error_reporting(E_ALL ^ (E_NOTICE | E_WARNING | E_DEPRECATED));
}

// Definitions for dev/demo/prerelease mode
if ((GROCY_MODE === 'dev' || GROCY_MODE === 'demo' || GROCY_MODE === 'prerelease') && !defined('GROCY_USER_ID'))
{
	define('GROCY_USER_ID', 1);
}

// Definitions for disabled authentication mode
if (GROCY_DISABLE_AUTH === true)
{
	if (!defined('GROCY_USER_ID'))
	{
		define('GROCY_USER_ID', 1);
	}
}

// Check if any invalid entries in config.php have been made
try
{
	(new Grocy\Helpers\ConfigurationValidator())->validateConfig();
}
catch (\Grocy\Helpers\EInvalidConfig $ex)
{
	http_response_code(500);
	exit('Invalid setting in config.php: ' . $ex->getMessage());
}

// Create data/viewcache folder if it doesn't exist
$viewcachePath = GROCY_DATAPATH . '/viewcache';
if (!file_exists($viewcachePath))
{
	mkdir($viewcachePath);
}

// Empty data/viewcache when and trigger database migrations when:
// The version changed (so when an update was done)
// GROCY_BASE_URL OR GROCY_BASE_PATH changed
// routes.php changed (in every mode: a stale route cache maps URLs to the wrong handlers)
$hash = hash('sha256', file_get_contents(__DIR__ . '/version.json') . GROCY_BASE_URL . GROCY_BASE_PATH . file_get_contents(__DIR__ . '/routes.php'));
$hashCacheFile = $viewcachePath . "/$hash.txt";
// Solo GET/HEAD: un POST redirigido perdería su cuerpo; la migración la hará la siguiente navegación
if (!file_exists($hashCacheFile) && in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['GET', 'HEAD'], true))
{
	EmptyFolder($viewcachePath);
	touch($hashCacheFile);

	if (function_exists('opcache_reset'))
	{
		opcache_reset();
	}

	// Schema migration happens on the root route, so redirect to there and back to the requested page
	// (antes caía siempre en la portada: la primera visita a /sales, /pos o una llamada /api devolvía la página de inicio)
	$next = (string) ($_SERVER['REQUEST_URI'] ?? '/');
	$basePath = rtrim((string) parse_url(GROCY_BASE_URL, PHP_URL_PATH), '/');
	if ($basePath !== '' && str_starts_with($next, $basePath))
	{
		$next = substr($next, strlen($basePath));
	}
	$isRoot = $next === '' || $next === '/' || str_starts_with($next, '/?');
	header('Location: ' . (new UrlManager(GROCY_BASE_URL))->ConstructUrl('/') . ($isRoot ? '' : '?next=' . rawurlencode($next)));
	exit();
}

// Setup base application
AppFactory::setContainer(new DI\Container());
$app = AppFactory::create();

$container = $app->getContainer();
$container->set('view', function (Container $container)
{
	return new SlimBladeView(__DIR__ . '/views', GROCY_DATAPATH . '/viewcache');
});

$container->set('UrlManager', function (Container $container)
{
	return new UrlManager(GROCY_BASE_URL);
});

$container->set('ApiKeyHeaderName', function (Container $container)
{
	return 'GROCY-API-KEY';
});

// Load routes from separate file
require_once __DIR__ . '/routes.php';

// Set base path if defined
if (!empty(GROCY_BASE_PATH))
{
	$app->setBasePath(GROCY_BASE_PATH);
}

$app->add(new \Grocy\Middleware\DailyI18nExportMiddleware($container, $app->getResponseFactory()));
$app->add(new \Grocy\Middleware\DairyIdempotencyMiddleware($container, $app->getResponseFactory()));
$app->add(new \Grocy\Middleware\DairyPermissionMiddleware($container, $app->getResponseFactory()));
$app->add(new LocaleMiddleware($container, $app->getResponseFactory()));
$authMiddlewareClass = GROCY_AUTH_CLASS;
$app->add(new $authMiddlewareClass($container, $app->getResponseFactory()));

// Add default middleware
$app->addBodyParsingMiddleware();
$app->addRoutingMiddleware();
$errorMiddleware = $app->addErrorMiddleware(true, false, false);
$errorMiddleware->setDefaultErrorHandler(new ExceptionController($container, $app->getResponseFactory()));

$app->getRouteCollector()->setCacheFile(GROCY_DATAPATH . '/viewcache/route_cache.php');

ob_clean(); // No response output before here
$app->run();
