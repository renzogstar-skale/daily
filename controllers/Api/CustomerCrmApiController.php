<?php

namespace Grocy\Controllers\Api;

use Grocy\Controllers\Users\PermissionMissingException;
use Grocy\Controllers\Users\User;
use Grocy\Helpers\DairyAccess;
use Grocy\Services\CustomerCrmService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/** CRM de clientes (CustomerCrmService): ficha, alta/edición, fusión, consentimientos, preferencias, atención, solicitudes y puntos */
class CustomerCrmApiController extends BaseApiController
{
	private function Run(Request $request, Response $response, callable $fn): Response
	{
		// Acceso base al CRM: ventas o caja; los permisos finos (PII, gestión) los aplica el servicio
		if (!DairyAccess::HasAny([User::PERMISSION_SALES_VIEW, User::PERMISSION_SALES_POS]))
		{
			throw new PermissionMissingException($request, User::PERMISSION_SALES_VIEW);
		}
		try
		{
			return $this->ApiResponse($response, $fn($request->getMethod() === 'GET' ? $request->getQueryParams() : ($this->GetParsedAndFilteredRequestBody($request) ?: [])));
		}
		catch (PermissionMissingException $e)
		{
			throw $e;
		}
		catch (\Throwable $e)
		{
			return $this->GenericErrorResponse($response, $e->getMessage(), 400);
		}
	}

	private function Branch(array $q): ?int
	{
		$b = intval($q['branch_id'] ?? 0);
		return $b > 0 && DairyAccess::CanSeeBranch($b) ? $b : null;
	}

	public function Directory(Request $request, Response $response, array $args): Response
	{
		return $this->Run($request, $response, fn($q) => CustomerCrmService::GetInstance()->Directory($this->Branch($q), $q));
	}

	public function Profile(Request $request, Response $response, array $args): Response
	{
		return $this->Run($request, $response, fn($q) => CustomerCrmService::GetInstance()->Profile(intval($args['customerId'])));
	}

	public function Save(Request $request, Response $response, array $args): Response
	{
		return $this->Run($request, $response, fn($d) => CustomerCrmService::GetInstance()->SaveCustomer($d + ['id' => $args['customerId'] ?? null]));
	}

	public function SetActive(Request $request, Response $response, array $args): Response
	{
		return $this->Run($request, $response, fn($d) => CustomerCrmService::GetInstance()->SetActive(intval($args['customerId']), !empty($d['active'])));
	}

	public function Merge(Request $request, Response $response, array $args): Response
	{
		return $this->Run($request, $response, fn($d) => CustomerCrmService::GetInstance()->Merge(intval($d['source_id'] ?? 0), intval($d['target_id'] ?? 0)));
	}

	public function Consent(Request $request, Response $response, array $args): Response
	{
		return $this->Run($request, $response, fn($d) => CustomerCrmService::GetInstance()->SetConsent(intval($args['customerId']), (string)($d['purpose'] ?? ''), !empty($d['granted']), (string)($d['source'] ?? ''), $d['evidence'] ?? null));
	}

	public function Preferences(Request $request, Response $response, array $args): Response
	{
		return $this->Run($request, $response, fn($d) => CustomerCrmService::GetInstance()->SetPreferences(intval($args['customerId']), $d));
	}

	public function Interaction(Request $request, Response $response, array $args): Response
	{
		return $this->Run($request, $response, fn($d) => CustomerCrmService::GetInstance()->AddInteraction(intval($args['customerId']), $d));
	}

	public function SaveRequest(Request $request, Response $response, array $args): Response
	{
		return $this->Run($request, $response, fn($d) => CustomerCrmService::GetInstance()->SaveRequest($d + ['id' => $args['requestId'] ?? null]));
	}

	public function CreditDecision(Request $request, Response $response, array $args): Response
	{
		return $this->Run($request, $response, fn($d) => CustomerCrmService::GetInstance()->DecideCreditRequest(
			intval($args['requestId']),
			(string)($d['decision'] ?? ''),
			(string)($d['note'] ?? ''),
			isset($d['approved_limit']) && $d['approved_limit'] !== '' ? floatval($d['approved_limit']) : null
		));
	}

	public function SaveCampaign(Request $request, Response $response, array $args): Response
	{
		return $this->Run($request, $response, fn($d) => CustomerCrmService::GetInstance()->SaveCampaign($d));
	}

	public function ToggleAutomation(Request $request, Response $response, array $args): Response
	{
		return $this->Run($request, $response, fn($d) => CustomerCrmService::GetInstance()->ToggleAutomation(intval($args['ruleId']), !empty($d['active'])));
	}

	public function RunAutomations(Request $request, Response $response, array $args): Response
	{
		return $this->Run($request, $response, fn($d) => CustomerCrmService::GetInstance()->RunAutomations(intval($d['branch_id'] ?? 0) ?: null, intval($d['days'] ?? 30)));
	}

	public function Loyalty(Request $request, Response $response, array $args): Response
	{
		return $this->Run($request, $response, fn($d) => CustomerCrmService::GetInstance()->LoyaltyMove(intval($args['customerId']), (string)($d['kind'] ?? ''), intval($d['points'] ?? 0), (string)($d['reason'] ?? '')));
	}

	public function Settings(Request $request, Response $response, array $args): Response
	{
		return $this->Run($request, $response, fn($d) => CustomerCrmService::GetInstance()->SaveSettings($d));
	}

	public function Export(Request $request, Response $response, array $args): Response
	{
		if (!DairyAccess::HasAny([User::PERMISSION_SALES_VIEW]))
		{
			throw new PermissionMissingException($request, User::PERMISSION_SALES_VIEW);
		}
		$q = $request->getQueryParams();
		$response->getBody()->write(CustomerCrmService::GetInstance()->ExportCsv($this->Branch($q), $q));
		return $response->withHeader('Content-Type', 'text/csv; charset=UTF-8')
			->withHeader('Content-Disposition', 'attachment; filename="clientes_' . date('Ymd') . '.csv"');
	}
}
