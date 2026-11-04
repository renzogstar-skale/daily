<?php

namespace Grocy\Controllers\Api;

use Grocy\Services\ProcessFlowService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

// DAILY Process Flow: expediente y bandeja derivados del estado real de cada documento (solo lectura)
class ProcessFlowApiController extends BaseApiController
{
	private function Run(Response $response, callable $fn)
	{
		try
		{
			return $this->ApiResponse($response, $fn());
		}
		catch (\Throwable $ex)
		{
			return $this->GenericErrorResponse($response, $ex->getMessage());
		}
	}

	public function Types(Request $request, Response $response, array $args)
	{
		return $this->Run($response, fn() => ProcessFlowService::GetInstance()->Types());
	}

	public function Inbox(Request $request, Response $response, array $args)
	{
		$q = $request->getQueryParams();
		return $this->Run($response, fn() => ProcessFlowService::GetInstance()->Inbox($q, max(1, min(100, intval($q['limit'] ?? 20)))));
	}

	public function Exceptions(Request $request, Response $response, array $args)
	{
		$b = intval($request->getQueryParams()['branch_id'] ?? 0);
		return $this->Run($response, fn() => ProcessFlowService::GetInstance()->Exceptions($b ?: null));
	}

	public function Get(Request $request, Response $response, array $args)
	{
		return $this->Run($response, fn() => ProcessFlowService::GetInstance()->Get((string) $args['type'], intval($args['id'])));
	}
}
