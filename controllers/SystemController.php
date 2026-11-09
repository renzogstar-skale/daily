<?php

namespace Grocy\Controllers;

use Grocy\Services\ApplicationService;
use Grocy\Services\DatabaseMigrationService;
use Grocy\Services\DemoDataGeneratorService;
use Grocy\Services\SessionService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class SystemController extends BaseController
{
	public function About(Request $request, Response $response, array $args)
	{
		return $this->RenderPage($response, 'about', [
			'systemInfo' => ApplicationService::GetInstance()->GetSystemInfo(),
			'versionInfo' => ApplicationService::GetInstance()->GetInstalledVersion(),
			'changelog' => ApplicationService::GetInstance()->GetChangelog()
		]);
	}

	public function BarcodeScannerTesting(Request $request, Response $response, array $args)
	{
		return $this->RenderPage($response, 'barcodescannertesting');
	}

	public function Root(Request $request, Response $response, array $args)
	{
		// Schema migration is done here
		$databaseMigrationService = DatabaseMigrationService::GetInstance();
		$databaseMigrationService->MigrateDatabase();

		if (GROCY_MODE === 'dev' || GROCY_MODE === 'demo' || GROCY_MODE === 'prerelease')
		{
			$demoDataGeneratorService = DemoDataGeneratorService::GetInstance();
			$demoDataGeneratorService->PopulateDemoData(isset($request->getQueryParams()['nodemodata']));
		}

		// Visitantes sin sesión ven el sitio comercial; usuarios con sesión entran a la aplicación como antes
		$token = $request->getCookieParams()[SessionService::SESSION_TOKEN_COOKIE_NAME_ACCESS] ?? '';
		if ($token !== '' && SessionService::GetInstance()->ValidateToken(SessionService::SESSION_TOKEN_TYPE_ACCESS, $token))
		{
			return $this->AppEntry($request, $response, $args);
		}

		return (new SiteController($this->AppContainer))->Home($request, $response, $args);
	}

	public function AppEntry(Request $request, Response $response, array $args)
	{
		return $response->withRedirect($this->AppContainer->get('UrlManager')->ConstructUrl($this->GetEntryPageRelative()));
	}

	public function SetSimulatedRole(Request $request, Response $response, array $args)
	{
		$role = $request->getQueryParams()['role'] ?? 'manager';
		if (!in_array($role, ['manager', 'supervisor', 'empleado', 'demo'], true))
		{
			$role = 'manager';
		}
		setcookie('daily_simulated_role', $role, time() + 86400 * 30, '/');

		$redirectUrl = $request->getServerParams()['HTTP_REFERER'] ?? $this->AppContainer->get('UrlManager')->ConstructUrl('/today');
		if ($role === 'demo')
		{
			$redirectUrl = $this->AppContainer->get('UrlManager')->ConstructUrl('/sales');
		}
		elseif ($role === 'empleado' && !str_contains($redirectUrl, 'attendance') && !str_contains($redirectUrl, 'schedule') && !str_contains($redirectUrl, 'pos'))
		{
			$redirectUrl = $this->AppContainer->get('UrlManager')->ConstructUrl('/operations/attendance?role_view=employee');
		}

		return $response->withHeader('Set-Cookie', "daily_simulated_role=$role; Path=/; SameSite=Lax")->withRedirect($redirectUrl);
	}

	public function Manifest(Request $request, Response $response, array $args)
	{
		$data = explode('#', base64_decode($request->getQueryParams()['data']));

		$urlManager = $this->AppContainer->get('UrlManager');
		$shortcuts = [];
		foreach ([['Hoy', '/today'], ['Registrar merma', '/waste/report'], ['Inventario', '/stockoverview'], ['Planificación MRP', '/mrp']] as $sc)
		{
			if (\Grocy\Helpers\DairyAccess::CanOpen($sc[1]))
			{
				$shortcuts[] = ['name' => $sc[0], 'short_name' => $sc[0], 'url' => $urlManager->ConstructUrl($sc[1]), 'icons' => [['src' => './img/icon-1024.png', 'sizes' => '1024x1024', 'type' => 'image/png']]];
			}
		}

		$manifest = [
			'name' => 'Dairy ' . $data[0],
			'short_name' => 'Dairy',
			'description' => 'Control de mermas, inventario y rentabilidad',
			'lang' => 'es',
			'icons' => [
				['src' => './img/icon-1024.png', 'sizes' => '1024x1024', 'type' => 'image/png', 'purpose' => 'any'],
				['src' => './img/icon.svg', 'sizes' => 'any', 'type' => 'image/svg+xml', 'purpose' => 'any']
			],
			'start_url' => $data[1],
			'scope' => $urlManager->ConstructUrl('/', true),
			'background_color' => '#f8fafc',
			'theme_color' => '#0284c7',
			'display' => 'standalone',
			'orientation' => 'any',
			'shortcuts' => $shortcuts
		];

		$response->getBody()->write(json_encode($manifest));
		return $response->withHeader('Content-Type', 'application/json');
	}

	private function GetEntryPageRelative()
	{
		if (defined('GROCY_ENTRY_PAGE'))
		{
			$entryPage = constant('GROCY_ENTRY_PAGE');
		}
		else
		{
			$entryPage = 'stock';
		}

		// Stock
		if ($entryPage === 'stock' && constant('GROCY_FEATURE_FLAG_STOCK'))
		{
			return '/stockoverview';
		}

		// Shoppinglist
		if ($entryPage === 'shoppinglist' && constant('GROCY_FEATURE_FLAG_SHOPPINGLIST'))
		{
			return '/shoppinglist';
		}

		// Recipes
		if ($entryPage === 'recipes' && constant('GROCY_FEATURE_FLAG_RECIPES'))
		{
			return '/recipes';
		}

		// Chores
		if ($entryPage === 'chores' && constant('GROCY_FEATURE_FLAG_CHORES'))
		{
			return '/choresoverview';
		}

		// Tasks
		if ($entryPage === 'tasks' && constant('GROCY_FEATURE_FLAG_TASKS'))
		{
			return '/tasks';
		}

		// Batteries
		if ($entryPage === 'batteries' && constant('GROCY_FEATURE_FLAG_BATTERIES'))
		{
			return '/batteriesoverview';
		}

		if ($entryPage === 'equipment' && constant('GROCY_FEATURE_FLAG_EQUIPMENT'))
		{
			return '/equipment';
		}

		// Calendar
		if ($entryPage === 'calendar' && constant('GROCY_FEATURE_FLAG_CALENDAR'))
		{
			return '/calendar';
		}

		// Meal Plan
		if ($entryPage === 'mealplan' && constant('GROCY_FEATURE_FLAG_RECIPES_MEALPLAN'))
		{
			return '/mealplan';
		}

		return '/about';
	}
}
