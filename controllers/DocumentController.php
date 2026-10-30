<?php

namespace Grocy\Controllers;

use Grocy\Controllers\Users\PermissionMissingException;
use Grocy\Helpers\DairyAccess;
use Grocy\Services\DocumentService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Exception\HttpNotFoundException;

class DocumentController extends BaseController
{
	public function Show(Request $request, Response $response, array $args)
	{
		$perms = DairyAccess::DOCUMENTS[$args['type']] ?? null;
		if ($perms !== null && !DairyAccess::HasAny($perms))
		{
			throw new PermissionMissingException($request, $perms[0]);
		}

		try
		{
			$doc = DocumentService::GetInstance()->GetDocument($args['type'], intval($args['id']));
		}
		catch (\Exception $ex)
		{
			throw new HttpNotFoundException($request, $ex->getMessage());
		}

		return $this->RenderPage($response, 'dairy_document', ['doc' => $doc]);
	}
}
