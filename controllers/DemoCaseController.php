<?php

namespace Grocy\Controllers;

use Grocy\Controllers\Api\BaseApiController;
use Grocy\Services\DemoRetailCaseService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

// Caso demo de principio a fin (solo modo demo): recorrido, cifras de aceptación y enlaces al registro exacto de cada módulo
class DemoCaseController extends BaseApiController
{
	public function Overview(Request $request, Response $response, array $args)
	{
		$svc = DemoRetailCaseService::GetInstance();
		if (DemoRetailCaseService::Enabled())
		{
			$svc->EnsureMaster();
			$svc->Sync();
		}
		$s = $svc->Summary();
		return $this->RenderPage($response, 'demo_case', [
			'case' => $s,
			'links' => $svc->Links($s['refs']),
			'priorities' => $svc->Priorities(),
			'stepsDef' => DemoRetailCaseService::STEPS,
			'enabled' => DemoRetailCaseService::Enabled(),
			'canRun' => \Grocy\Helpers\DairyAccess::HasAny([\Grocy\Controllers\Users\User::PERMISSION_ADMIN]),
		]);
	}

	public function Summary(Request $request, Response $response, array $args)
	{
		try
		{
			$svc = DemoRetailCaseService::GetInstance();
			$svc->Sync();
			$s = $svc->Summary();
			return $this->ApiResponse($response, $s + ['links' => $svc->Links($s['refs']), 'priorities' => $svc->Priorities()]);
		}
		catch (\Throwable $ex)
		{
			return $this->GenericErrorResponse($response, $ex->getMessage());
		}
	}

	// Acciones: next (siguiente paso), pre_close, all, reset o un código de paso
	public function Action(Request $request, Response $response, array $args)
	{
		try
		{
			$svc = DemoRetailCaseService::GetInstance();
			$action = (string) $args['action'];
			$out = $action === 'reset' ? $svc->Reset() : $svc->Run($action, GROCY_USER_ID);
			return $this->ApiResponse($response, $out);
		}
		catch (\Throwable $ex)
		{
			return $this->GenericErrorResponse($response, $ex->getMessage());
		}
	}
}
