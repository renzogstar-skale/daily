<?php

namespace Grocy\Helpers;

use Psr\Http\Message\ServerRequestInterface;
use Slim\Exception\HttpForbiddenException;

/**
 * Funcionalidad fuera del plan contratado (o escritura con la suscripción inactiva).
 * ExceptionController la convierte en la pantalla "Requiere un plan superior" (web) o en JSON con code (API).
 */
class PlanRequiredException extends HttpForbiddenException
{
	public string $Capability;
	public string $Reason;

	public function __construct(ServerRequestInterface $request, string $capability, string $reason = 'plan_required')
	{
		$this->Capability = $capability;
		$this->Reason = $reason;
		parent::__construct($request, $reason === 'subscription_inactive' ? 'Subscription inactive' : 'Plan required: ' . $capability);
	}
}
