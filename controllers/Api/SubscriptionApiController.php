<?php

namespace Grocy\Controllers\Api;

use Grocy\Helpers\DailySubscription;
use Grocy\Services\SubscriptionService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class SubscriptionApiController extends BaseApiController
{
	public function Get(Request $request, Response $response, array $args)
	{
		return $this->ApiResponse($response, SubscriptionService::GetInstance()->Overview());
	}

	// Estado mínimo para el front-end (menú, móvil): plan y capacidades efectivas
	public function Capabilities(Request $request, Response $response, array $args)
	{
		return $this->ApiResponse($response, DailySubscription::ClientState());
	}

	public function RequestUpgrade(Request $request, Response $response, array $args)
	{
		try
		{
			return $this->ApiResponse($response, SubscriptionService::GetInstance()->RequestUpgrade($this->GetParsedAndFilteredRequestBody($request) ?? []));
		}
		catch (\Exception $ex)
		{
			return $this->GenericErrorResponse($response, $ex->getMessage(), str_contains($ex->getMessage(), 'autorizado') ? 403 : 400);
		}
	}

	public function Change(Request $request, Response $response, array $args)
	{
		try
		{
			return $this->ApiResponse($response, SubscriptionService::GetInstance()->Change($this->GetParsedAndFilteredRequestBody($request) ?? []));
		}
		catch (\Exception $ex)
		{
			return $this->GenericErrorResponse($response, $ex->getMessage(), str_contains($ex->getMessage(), 'administrador') ? 403 : 400);
		}
	}

	public function DecideRequest(Request $request, Response $response, array $args)
	{
		try
		{
			return $this->ApiResponse($response, SubscriptionService::GetInstance()->DecideRequest(intval($args['id']), $this->GetParsedAndFilteredRequestBody($request) ?? []));
		}
		catch (\Exception $ex)
		{
			return $this->GenericErrorResponse($response, $ex->getMessage(), str_contains($ex->getMessage(), 'administrador') ? 403 : 400);
		}
	}
}
