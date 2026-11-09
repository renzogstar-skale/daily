<?php

namespace Grocy\Controllers;

use Grocy\Helpers\DailySubscription;
use Grocy\Services\SubscriptionService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class SubscriptionController extends BaseController
{
	public function Overview(Request $request, Response $response, array $args)
	{
		return $this->RenderPage($response, 'subscription', ['overview' => SubscriptionService::GetInstance()->Overview()]);
	}

	// Vista previa de un plan inferior al contratado (solo reduce; DailySubscription ignora valores superiores)
	public function SetPreviewPlan(Request $request, Response $response, array $args)
	{
		$plan = strval($request->getQueryParams()['plan'] ?? '');
		$cookie = DailySubscription::PlanRank($plan) >= 0 ? "daily_simulated_plan=$plan; Path=/; SameSite=Lax" : 'daily_simulated_plan=; Path=/; Max-Age=0; SameSite=Lax';
		$back = $request->getServerParams()['HTTP_REFERER'] ?? $this->AppContainer->get('UrlManager')->ConstructUrl('/today');
		return $response->withHeader('Set-Cookie', $cookie)->withHeader('Location', $back)->withStatus(302);
	}
}
