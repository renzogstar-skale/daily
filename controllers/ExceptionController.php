<?php

namespace Grocy\Controllers;

use DI\Container;
use Grocy\Controllers\Api\BaseApiController;
use Grocy\Helpers\DailyI18n;
use Grocy\Helpers\DailySubscription;
use Grocy\Helpers\PlanRequiredException;
use Grocy\Services\ApplicationService;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Slim\Exception\HttpException;
use Slim\Exception\HttpForbiddenException;
use Slim\Exception\HttpNotFoundException;
use Throwable;

class ExceptionController extends BaseApiController
{
	public function __construct(Container $container, ResponseFactoryInterface $responseFactory)
	{
		parent::__construct($container);
		$this->ResponseFactory = $responseFactory;
	}

	private $ResponseFactory;

	public function __invoke(ServerRequestInterface $request, Throwable $exception, bool $displayErrorDetails, bool $logErrors, bool $logErrorDetails, ?LoggerInterface $logger = null)
	{
		if (!defined('GROCY_LOCALE'))
		{
			define('GROCY_LOCALE', GROCY_DEFAULT_LOCALE);
		}

		$response = $this->ResponseFactory->createResponse();
		$isApiRoute = str_contains($request->getUri()->getPath(), '/api/');

		if (!defined('GROCY_AUTHENTICATED'))
		{
			define('GROCY_AUTHENTICATED', false);
		}

		if ($exception instanceof PlanRequiredException)
		{
			$inactive = $exception->Reason === 'subscription_inactive';
			$info = $inactive ? ['capability' => null, 'current_plan' => DailySubscription::Plan(), 'current_plan_name' => DailySubscription::PlanName(DailySubscription::Plan())] : DailySubscription::LockInfo($exception->Capability);
			if ($isApiRoute)
			{
				$msg = $inactive
					? DailyI18n::T('La suscripción no está activa: DAILY quedó en modo consulta.')
					: sprintf(DailyI18n::T('Esta función requiere el plan %s.'), $info['required_plan_name']);
				return $this->ApiResponse($response->withStatus(403)->withHeader('Content-Type', 'application/json'), array_merge(['error_message' => $msg, 'code' => $exception->Reason], $info));
			}
			return $this->RenderPage($response->withStatus(403), 'subscription_locked', ['lock' => $info, 'inactive' => $inactive]);
		}

		if ($isApiRoute)
		{
			$status = 500;
			if ($exception instanceof HttpException)
			{
				$status = $exception->getCode();
			}

			$data = [
				'error_message' => $exception->getMessage()
			];

			if ($displayErrorDetails)
			{
				$data['error_details'] = [
					'stack_trace' => $exception->getTraceAsString(),
					'file' => $exception->getFile(),
					'line' => $exception->getLine()
				];
			}

			return $this->ApiResponse($response->withStatus($status)->withHeader('Content-Type', 'application/json'), $data);
		}

		if ($exception instanceof HttpNotFoundException)
		{
			if (!defined('GROCY_AUTHENTICATED'))
			{
				define('GROCY_AUTHENTICATED', false);
			}

			return $this->RenderPage($response->withStatus(404), 'errors/404', [
				'exception' => $exception
			]);
		}

		if ($exception instanceof HttpForbiddenException)
		{
			return $this->RenderPage($response->withStatus(403), 'errors/403', [
				'exception' => $exception
			]);
		}

		return $this->RenderPage($response->withStatus(500), 'errors/500', [
			'exception' => $exception,
			'systemInfo' => ApplicationService::GetInstance()->GetSystemInfo()
		]);
	}
}
