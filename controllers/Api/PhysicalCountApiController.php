<?php

namespace Grocy\Controllers\Api;

use Grocy\Controllers\Users\User;
use Grocy\Services\PhysicalInventoryService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class PhysicalCountApiController extends BaseApiController
{
	public function CreateCount(Request $request, Response $response, array $args)
	{
		User::CheckPermission($request, User::PERMISSION_STOCK_INVENTORY);
		$body = $this->GetParsedAndFilteredRequestBody($request);
		try
		{
			$r = PhysicalInventoryService::GetInstance()->CreatePhysicalCount((array)$body, GROCY_USER_ID);
			return $this->ApiResponse($response, $r);
		}
		catch (\Throwable $ex)
		{
			return $this->GenericErrorResponse($response, $ex->getMessage());
		}
	}

	public function StartCount(Request $request, Response $response, array $args)
	{
		User::CheckPermission($request, User::PERMISSION_STOCK_INVENTORY);
		try
		{
			$r = PhysicalInventoryService::GetInstance()->StartCount(intval($args['id']), GROCY_USER_ID);
			return $this->ApiResponse($response, $r);
		}
		catch (\Throwable $ex)
		{
			return $this->GenericErrorResponse($response, $ex->getMessage());
		}
	}

	public function ScanItem(Request $request, Response $response, array $args)
	{
		User::CheckPermission($request, User::PERMISSION_STOCK_INVENTORY);
		$body = $this->GetParsedAndFilteredRequestBody($request);
		try
		{
			$code = strval($body['code'] ?? $body['barcode'] ?? '');
			$qty = floatval($body['amount'] ?? 1);
			$imei = !empty($body['imei']) ? strval($body['imei']) : null;
			$r = PhysicalInventoryService::GetInstance()->ScanAndCountItem(intval($args['id']), $code, $qty, GROCY_USER_ID, $imei);
			return $this->ApiResponse($response, $r);
		}
		catch (\Throwable $ex)
		{
			return $this->GenericErrorResponse($response, $ex->getMessage());
		}
	}

	public function ReconcileCount(Request $request, Response $response, array $args)
	{
		User::CheckPermission($request, User::PERMISSION_STOCK_EDIT);
		try
		{
			$r = PhysicalInventoryService::GetInstance()->ApproveAndReconcile(intval($args['id']), GROCY_USER_ID);
			return $this->ApiResponse($response, $r);
		}
		catch (\Throwable $ex)
		{
			return $this->GenericErrorResponse($response, $ex->getMessage());
		}
	}
}
