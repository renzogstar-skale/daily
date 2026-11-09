<?php

namespace Grocy\Middleware;

use Grocy\Controllers\Users\PermissionMissingException;
use Grocy\Helpers\DailySubscription;
use Grocy\Helpers\DairyAccess;
use Grocy\Helpers\PlanRequiredException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Routing\RouteContext;

/**
 * 1. Bloquea (403) las páginas DAIRY a usuarios sin ninguno de sus permisos (mapa en DairyAccess::PAGES).
 * 2. Usuarios con sucursales asignadas (user_branches): rechaza (403) cualquier branch_id / from_branch_id ajeno
 *    en ruta, query o cuerpo; sin branch_id en la query (o "red consolidada") se usa su primera sucursal, y en el
 *    cuerpo de las APIs DAIRY se completa igual para que ningún servicio caiga en su sucursal por defecto.
 */
class DairyPermissionMiddleware extends BaseMiddleware
{
	// Prefijos de API DAIRY cuyos servicios aceptan branch_id en el cuerpo
	const BODY_SCOPED = ['/api/waste', '/api/inventory-control', '/api/loss', '/api/audits', '/api/alerts', '/api/ai/', '/api/workforce', '/api/schedule', '/api/mrp', '/api/quality', '/api/procurement', '/api/suppliers', '/api/sales', '/api/markdown'];

	public function __invoke(Request $request, RequestHandler $handler): Response
	{
		$route = RouteContext::fromRequest($request)->getRoute();
		if ($route !== null && defined('GROCY_USER_ID'))
		{
			if (DairyAccess::IsHiddenInDemo($route->getPattern()))
			{
				return $this->ResponseFactory->createResponse(302)->withHeader('Location', $this->AppContainer->get('UrlManager')->ConstructUrl('/sales'));
			}

			// Plan contratado primero (qué ofrece DAILY), luego rol (qué puede hacer el usuario) y sucursales
			$method = $request->getMethod();
			$planMissing = DailySubscription::MissingForRoute($route->getPattern(), $method);
			if ($planMissing !== null)
			{
				throw new PlanRequiredException($request, $planMissing);
			}
			if (DailySubscription::WriteBlockedByStatus($route->getPattern(), $method))
			{
				throw new PlanRequiredException($request, 'subscription', 'subscription_inactive');
			}

			// Rol operativo (Supervisor / Empleado): las páginas DAILY que su rol no abre se bloquean también por URL directa
			$simRole = DairyAccess::GetSimulatedRole();
			if (in_array($simRole, ['empleado', 'supervisor'], true) && isset(DairyAccess::PAGES[$route->getPattern()]) && !DairyAccess::CanOpen($route->getPattern()))
			{
				throw new PermissionMissingException($request, 'ROLE ' . $simRole);
			}

			$missing = DairyAccess::MissingFor($route->getPattern());
			if ($missing !== null)
			{
				throw new PermissionMissingException($request, $missing);
			}

			$allowed = DairyAccess::AllowedBranches();
			if ($allowed !== null)
			{
				$request = $this->ScopeToBranches($request, $route->getPattern(), $route->getArguments(), $allowed);
			}
		}

		return $handler->handle($request);
	}

	private function ScopeToBranches(Request $request, string $pattern, array $args, array $allowed): Request
	{
		$deny = function ($value) use ($request, $allowed) {
			if (!in_array(intval($value), $allowed, true))
			{
				throw new PermissionMissingException($request, 'BRANCH #' . intval($value));
			}
		};

		if (isset($args['branchId']))
		{
			$deny($args['branchId']);
		}

		$query = $request->getQueryParams();
		if (!isset($query['branch_id']) || $query['branch_id'] === '' || $query['branch_id'] === '0')
		{
			$query['branch_id'] = strval($allowed[0]);
			$request = $request->withQueryParams($query);
		}
		else
		{
			$deny($query['branch_id']);
		}

		$body = $request->getParsedBody();
		if (is_array($body))
		{
			foreach (['branch_id', 'from_branch_id'] as $key)
			{
				if (array_key_exists($key, $body) && $body[$key] !== null && $body[$key] !== '')
				{
					$deny($body[$key]);
				}
			}
			$scoped = count(array_filter(self::BODY_SCOPED, fn($prefix) => str_starts_with($pattern, $prefix))) > 0;
			if ($scoped && (!array_key_exists('branch_id', $body) || $body['branch_id'] === null || $body['branch_id'] === ''))
			{
				$body['branch_id'] = $allowed[0];
				$request = $request->withParsedBody($body);
			}
		}

		return $request;
	}
}
