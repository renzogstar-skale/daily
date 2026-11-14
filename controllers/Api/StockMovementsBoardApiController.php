<?php

namespace Grocy\Controllers\Api;

use Grocy\Controllers\Users\User;
use Grocy\Helpers\DailyFilters;
use Grocy\Helpers\DairyAccess;
use Grocy\Services\StockMovementsBoardService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/** Detalles filtrados y serie de stock del tablero «Stock & Movimientos» (/stockoverview) */
class StockMovementsBoardApiController extends BaseApiController
{
	private function Guard(Request $request): void
	{
		if (!DairyAccess::CanOpen('/stockoverview'))
		{
			throw new \Grocy\Controllers\Users\PermissionMissingException($request, User::PERMISSION_STOCK);
		}
	}

	public function Detail(Request $request, Response $response, array $args)
	{
		$this->Guard($request);
		try
		{
			$q = $request->getQueryParams();
			return $this->ApiResponse($response, StockMovementsBoardService::GetInstance()->Detail(strval($args['kind']), DailyFilters::FromQuery($q), $q));
		}
		catch (\Exception $ex)
		{
			return $this->GenericErrorResponse($response, $ex->getMessage());
		}
	}

	public function Series(Request $request, Response $response, array $args)
	{
		$this->Guard($request);
		try
		{
			$q = $request->getQueryParams();
			return $this->ApiResponse($response, StockMovementsBoardService::GetInstance()->SeriesFor(DailyFilters::FromQuery($q), intval($q['series_branch'] ?? 0), intval($q['series_product'] ?? 0)));
		}
		catch (\Exception $ex)
		{
			return $this->GenericErrorResponse($response, $ex->getMessage());
		}
	}
}
