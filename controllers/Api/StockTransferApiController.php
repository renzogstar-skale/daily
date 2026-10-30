<?php

namespace Grocy\Controllers\Api;

use Grocy\Controllers\Users\User;
use Grocy\Services\StockTransferService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class StockTransferApiController extends BaseApiController
{
	public function GetTransfer(Request $request, Response $response, array $args)
	{
		// Visible si el usuario ve el origen o el destino
		$t = \Grocy\Services\DatabaseService::GetInstance()->GetDbConnectionRaw()->query('SELECT from_branch_id, to_branch_id FROM stock_transfers WHERE id = ' . intval($args['transferId']))->fetch(\PDO::FETCH_ASSOC);
		$this->DairyRead($request, [User::PERMISSION_STOCK_TRANSFER, User::PERMISSION_STOCK, User::PERMISSION_KPIS_VIEW], null, false);
		if ($t && !\Grocy\Helpers\DairyAccess::CanSeeBranch(intval($t['from_branch_id'])) && !\Grocy\Helpers\DairyAccess::CanSeeBranch(intval($t['to_branch_id'])))
		{
			throw new \Grocy\Controllers\Users\PermissionMissingException($request, 'BRANCH #' . intval($t['from_branch_id']));
		}
		try
		{
			return $this->ApiResponse($response, StockTransferService::GetInstance()->GetTransferDetail(intval($args['transferId'])));
		}
		catch (\Exception $ex)
		{
			return $this->GenericErrorResponse($response, $ex->getMessage());
		}
	}

	public function CreateTransfer(Request $request, Response $response, array $args)
	{
		User::CheckPermission($request, User::PERMISSION_STOCK_TRANSFER);

		try
		{
			return $this->ApiResponse($response, StockTransferService::GetInstance()->CreateTransfer($this->GetParsedAndFilteredRequestBody($request), GROCY_USER_ID));
		}
		catch (\Exception $ex)
		{
			return $this->GenericErrorResponse($response, $ex->getMessage());
		}
	}

	public function ReceiveTransfer(Request $request, Response $response, array $args)
	{
		User::CheckPermission($request, User::PERMISSION_STOCK_TRANSFER);

		try
		{
			return $this->ApiResponse($response, StockTransferService::GetInstance()->ReceiveTransfer(intval($args['transferId']), $this->GetParsedAndFilteredRequestBody($request), GROCY_USER_ID));
		}
		catch (\Exception $ex)
		{
			return $this->GenericErrorResponse($response, $ex->getMessage());
		}
	}

	public function CancelTransfer(Request $request, Response $response, array $args)
	{
		User::CheckPermission($request, User::PERMISSION_STOCK_TRANSFER);

		try
		{
			$body = $this->GetParsedAndFilteredRequestBody($request);
			StockTransferService::GetInstance()->CancelTransfer(intval($args['transferId']), GROCY_USER_ID, $body['reason'] ?? '');
			return $this->ApiResponse($response, ['success' => true, 'message' => 'Traslado anulado: el stock volvió al origen']);
		}
		catch (\Exception $ex)
		{
			return $this->GenericErrorResponse($response, $ex->getMessage());
		}
	}
}
