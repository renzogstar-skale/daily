<?php

namespace Grocy\Controllers\Api;

use Grocy\Controllers\Users\User;
use Grocy\Services\SystemMapService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

// Mapa del Sistema: solo administradores. El análisis es estático (lee archivos del repositorio) y no ejecuta pruebas.
class SystemMapApiController extends BaseApiController
{
	public function Get(Request $request, Response $response, array $args)
	{
		User::CheckPermission($request, User::PERMISSION_ADMIN);
		try
		{
			return $this->ApiResponse($response, SystemMapService::GetInstance()->Get());
		}
		catch (\Throwable $ex)
		{
			return $this->GenericErrorResponse($response, $ex->getMessage(), 500);
		}
	}

	public function Refresh(Request $request, Response $response, array $args)
	{
		User::CheckPermission($request, User::PERMISSION_ADMIN);
		try
		{
			return $this->ApiResponse($response, SystemMapService::GetInstance()->Refresh('user#' . GROCY_USER_ID));
		}
		catch (\Throwable $ex)
		{
			return $this->GenericErrorResponse($response, $ex->getMessage(), 500);
		}
	}

	// format: json | mermaid-mindmap | mermaid-flowchart | markdown
	public function Export(Request $request, Response $response, array $args)
	{
		User::CheckPermission($request, User::PERMISSION_ADMIN);
		$svc = SystemMapService::GetInstance();
		$map = $svc->Get();
		[$body, $type, $name] = match ($args['format']) {
			'json' => [json_encode($map, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT), 'application/json', 'dairy-system-map.json'],
			'mermaid-mindmap' => [$svc->Mermaid($map, 'mindmap'), 'text/plain', 'dairy-mindmap.mmd'],
			'mermaid-flowchart' => [$svc->Mermaid($map, 'flowchart'), 'text/plain', 'dairy-connections.mmd'],
			'markdown' => [$svc->Report($map), 'text/markdown', 'DAIRY_SYSTEM_MAP.md'],
			default => [null, null, null],
		};
		if ($body === null)
		{
			return $this->GenericErrorResponse($response, 'Formato no soportado: json, mermaid-mindmap, mermaid-flowchart o markdown');
		}
		$response->getBody()->write($body);
		return $response->withHeader('Content-Type', $type . '; charset=utf-8')->withHeader('Content-Disposition', 'attachment; filename="' . $name . '"');
	}
}
