<?php

namespace Grocy\Controllers;

use Grocy\Controllers\Users\User;
use Grocy\Helpers\DairyAccess;
use Grocy\Services\ProductEconomicsService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class ProductEconomicsController extends BaseController
{
	private function RequireAdmin(): void
	{
		if (defined('GROCY_USER_ID') && !DairyAccess::HasAny([User::PERMISSION_ADMIN]))
		{
			throw new \Exception('Acceso restringido: Se requiere rol de Administrador para acceder a la Economía de DAILY', 403);
		}
	}

	public function Dashboard(Request $request, Response $response, array $args): Response
	{
		$this->RequireAdmin();

		$q = $request->getQueryParams();
		$period = $q['period'] ?? date('Y-m');

		$svc = ProductEconomicsService::GetInstance();
		$summary = $svc->GetFinancialSummary($period);
		$expenses = $svc->GetInfrastructureExpenses($period);
		$clients = $svc->GetClientsProfitability();
		$alerts = $svc->GetFinancialAlerts();

		// Simulación inicial por defecto para prospecto mediano (5 tiendas, plan enterprise)
		$defaultSim = $svc->SimulatePricing([
			'stores_count' => 5,
			'users_per_store' => 5,
			'plan' => 'enterprise_360',
			'support_tier' => 'standard_8x5',
			'target_margin_pct' => 85.0,
			'serialized_devices_volume' => 120
		]);

		return $this->RenderPage($response, 'admin_economics', [
			'period' => $period,
			'summary' => $summary,
			'expenses' => $expenses,
			'clients' => $clients,
			'alerts' => $alerts,
			'defaultSimulation' => $defaultSim,
		]);
	}

	public function SimulateApi(Request $request, Response $response, array $args): Response
	{
		$this->RequireAdmin();
		$body = json_decode((string)$request->getBody(), true) ?: $request->getParsedBody() ?: [];

		$svc = ProductEconomicsService::GetInstance();
		$result = $svc->SimulatePricing($body);

		$response->getBody()->write(json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
		return $response->withHeader('Content-Type', 'application/json');
	}

	public function AddExpenseApi(Request $request, Response $response, array $args): Response
	{
		$this->RequireAdmin();
		$body = json_decode((string)$request->getBody(), true) ?: $request->getParsedBody() ?: [];

		$svc = ProductEconomicsService::GetInstance();
		$userId = defined('GROCY_USER_ID') ? (int)GROCY_USER_ID : 1;
		$result = $svc->AddExpense($body, $userId);

		$response->getBody()->write(json_encode($result, JSON_UNESCAPED_UNICODE));
		return $response->withHeader('Content-Type', 'application/json');
	}
}
