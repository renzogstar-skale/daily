<?php

namespace Grocy\Controllers\Api;

use Grocy\Controllers\Users\User;
use Grocy\Services\MarkdownService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class MarkdownApiController extends BaseApiController
{
	public function GetActiveMarkdowns(Request $request, Response $response, array $args)
	{
		$this->DairyRead($request, [User::PERMISSION_SALES_POS, User::PERMISSION_SALES_VIEW, User::PERMISSION_WASTE_APPROVE, User::PERMISSION_KPIS_VIEW], null, false);
		try
		{
			return $this->ApiResponse($response, array_values(MarkdownService::GetInstance()->GetActiveMarkdowns()));
		}
		catch (\Exception $ex)
		{
			return $this->GenericErrorResponse($response, $ex->getMessage());
		}
	}

	public function GetRules(Request $request, Response $response, array $args)
	{
		$this->DairyRead($request, [User::PERMISSION_SALES_POS, User::PERMISSION_SALES_VIEW, User::PERMISSION_WASTE_APPROVE, User::PERMISSION_KPIS_VIEW], null, false);
		try
		{
			return $this->ApiResponse($response, MarkdownService::GetInstance()->GetRules());
		}
		catch (\Exception $ex)
		{
			return $this->GenericErrorResponse($response, $ex->getMessage());
		}
	}

	public function SaveRules(Request $request, Response $response, array $args)
	{
		User::CheckPermission($request, User::PERMISSION_WASTE_APPROVE);

		try
		{
			$body = $this->GetParsedAndFilteredRequestBody($request);
			$rules = MarkdownService::GetInstance()->SaveRules($body['rules'] ?? []);
			return $this->ApiResponse($response, ['success' => true, 'rules' => $rules]);
		}
		catch (\Exception $ex)
		{
			return $this->GenericErrorResponse($response, $ex->getMessage());
		}
	}
}
