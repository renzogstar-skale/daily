<?php

namespace Grocy\Controllers\Api;

use Grocy\Controllers\Users\User;
use Grocy\Helpers\DairyAccess;
use Grocy\Services\PosIngestService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

// Etapa 32 — Ingesta de ventas de POS externos: webhook/API (JSON), CSV, estado de la integración y reintentos
class PosIntegrationApiController extends BaseApiController
{
	private function Svc(): PosIngestService
	{
		return PosIngestService::GetInstance();
	}

	// POST /api/pos/ingest  {source, sales: [...], dry_run?}  — firma HMAC opcional (POS_WEBHOOK_SECRET)
	public function Ingest(Request $request, Response $response, array $args)
	{
		User::CheckPermission($request, User::PERMISSION_POS_INTEGRATION);
		try
		{
			$raw = strval($request->getBody());
			PosIngestService::VerifySignature($raw, $request->getHeaderLine('X-Dairy-Signature'));
			$body = json_decode($raw, true);
			if (!is_array($body) || !is_array($body['sales'] ?? null))
			{
				throw new \Exception('Cuerpo JSON inválido: se espera {"source": "...", "sales": [...]}');
			}
			return $this->ApiResponse($response, $this->Svc()->Ingest(strval($body['source'] ?? ''), $body['sales'], 'webhook', null, GROCY_USER_ID, !empty($body['dry_run'])));
		}
		catch (\Exception $ex)
		{
			return $this->GenericErrorResponse($response, $ex->getMessage());
		}
	}

	// POST /api/pos/import-csv  {source, csv, file_name?, dry_run?}
	public function ImportCsv(Request $request, Response $response, array $args)
	{
		User::CheckPermission($request, User::PERMISSION_POS_INTEGRATION);
		try
		{
			$body = $this->GetParsedAndFilteredRequestBody($request);
			$csv = strval($body['csv'] ?? '');
			if (strlen($csv) > 10 * 1024 * 1024)
			{
				throw new \Exception('Archivo mayor a 10 MB: divídalo');
			}
			$fileName = isset($body['file_name']) ? mb_substr(strval($body['file_name']), 0, 200) : null;
			return $this->ApiResponse($response, $this->Svc()->Ingest(strval($body['source'] ?? ''), PosIngestService::ParseCsv($csv), 'csv', $fileName, GROCY_USER_ID, !empty($body['dry_run'])));
		}
		catch (\Exception $ex)
		{
			return $this->GenericErrorResponse($response, $ex->getMessage());
		}
	}

	public function Status(Request $request, Response $response, array $args)
	{
		if (!DairyAccess::HasAny([User::PERMISSION_POS_INTEGRATION, User::PERMISSION_SALES_VIEW]))
		{
			User::CheckPermission($request, User::PERMISSION_POS_INTEGRATION);
		}
		try
		{
			$s = $this->Svc()->Status(DairyAccess::AllowedBranches());
			$s['can_import'] = DairyAccess::HasAny([User::PERMISSION_POS_INTEGRATION]);
			return $this->ApiResponse($response, $s);
		}
		catch (\Exception $ex)
		{
			return $this->GenericErrorResponse($response, $ex->getMessage());
		}
	}

	public function Retry(Request $request, Response $response, array $args)
	{
		User::CheckPermission($request, User::PERMISSION_POS_INTEGRATION);
		$id = intval($args['id']);
		try
		{
			$branch = $this->Svc()->GetExternalBranch($id);
		}
		catch (\Exception $ex)
		{
			return $this->GenericErrorResponse($response, $ex->getMessage());
		}
		if (($branch === null && DairyAccess::AllowedBranches() !== null) || ($branch !== null && !DairyAccess::CanSeeBranch($branch)))
		{
			throw new \Grocy\Controllers\Users\PermissionMissingException($request, 'BRANCH #' . ($branch ?? '?'));
		}
		try
		{
			return $this->ApiResponse($response, $this->Svc()->Retry($id, GROCY_USER_ID));
		}
		catch (\Exception $ex)
		{
			return $this->GenericErrorResponse($response, $ex->getMessage());
		}
	}
}
