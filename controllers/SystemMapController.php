<?php

namespace Grocy\Controllers;

use Grocy\Controllers\Users\User;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class SystemMapController extends BaseController
{
	public function Overview(Request $request, Response $response, array $args)
	{
		User::CheckPermission($request, User::PERMISSION_ADMIN);
		// Raíz del repositorio para los enlaces «abrir en el editor» (vscode://file/...)
		return $this->RenderPage($response, 'system_map', ['repoRoot' => str_replace('\\', '/', dirname(__DIR__))]);
	}
}
