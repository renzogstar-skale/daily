<?php

namespace Grocy\Controllers\Api;

use Grocy\Controllers\Users\User;
use Grocy\Helpers\DairyAccess;
use Grocy\Services\LossExecutiveReportService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class LossExecutiveReportApiController extends BaseApiController
{
	const MANAGE = [User::PERMISSION_LOSS_PREVENTION];
	const READ = [User::PERMISSION_LOSS_PREVENTION, User::PERMISSION_KPIS_VIEW];

	private function Svc(): LossExecutiveReportService
	{
		return LossExecutiveReportService::GetInstance();
	}

	private function Guard(Request $request, array $perms): int
	{
		if (!DairyAccess::HasAny(array_merge($perms, [User::PERMISSION_ADMIN])))
		{
			throw new \Grocy\Controllers\Users\PermissionMissingException($request, $perms[0]);
		}
		// El informe consolida toda la red
		if (DairyAccess::AllowedBranches() !== null)
		{
			throw new \Grocy\Controllers\Users\PermissionMissingException($request, 'Acceso a toda la red de sucursales');
		}
		return defined('GROCY_USER_ID') ? GROCY_USER_ID : 1;
	}

	private function Run(Response $response, callable $fn)
	{
		try
		{
			return $this->ApiResponse($response, $fn() ?? ['success' => true]);
		}
		catch (\Grocy\Controllers\Users\PermissionMissingException $ex)
		{
			throw $ex;
		}
		catch (\Exception $ex)
		{
			return $this->GenericErrorResponse($response, $ex->getMessage());
		}
	}

	public function Generate(Request $request, Response $response, array $args)
	{
		return $this->Run($response, function () use ($request) {
			$userId = $this->Guard($request, self::MANAGE);
			$b = $request->getParsedBody() ?? [];
			$type = strval($b['period_type'] ?? 'daily');
			if (!isset(LossExecutiveReportService::PERIODS[$type]))
			{
				throw new \Exception('Tipo de informe no válido');
			}
			$date = !empty($b['report_date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $b['report_date']) ? $b['report_date'] : null;
			return $this->Svc()->Generate($type, 'manual', $userId, $date);
		});
	}

	public function Get(Request $request, Response $response, array $args)
	{
		return $this->Run($response, function () use ($request, $args) {
			$this->Guard($request, self::READ);
			return $this->Svc()->Get(intval($args['id']));
		});
	}

	public function History(Request $request, Response $response, array $args)
	{
		return $this->Run($response, function () use ($request) {
			$this->Guard($request, self::READ);
			$t = $request->getQueryParams()['type'] ?? null;
			return $this->Svc()->History(isset(LossExecutiveReportService::PERIODS[$t]) ? $t : null);
		});
	}

	public function SaveSchedule(Request $request, Response $response, array $args)
	{
		return $this->Run($response, function () use ($request, $args) {
			$userId = $this->Guard($request, self::MANAGE);
			$this->Svc()->SaveSchedule(strval($args['type']), $request->getParsedBody() ?? [], $userId);
			return $this->Svc()->Schedules();
		});
	}

	public function ExportXlsx(Request $request, Response $response, array $args)
	{
		try
		{
			$this->Guard($request, self::READ);
			$id = intval($args['id']);
			$data = $this->Svc()->ExportXlsx($id);
			$code = $this->Svc()->Get($id)['report_code'];
			$response->getBody()->write($data);
			return $response->withHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
				->withHeader('Content-Disposition', 'attachment; filename="' . $code . '.xlsx"');
		}
		catch (\Grocy\Controllers\Users\PermissionMissingException $ex)
		{
			throw $ex;
		}
		catch (\Exception $ex)
		{
			return $this->GenericErrorResponse($response, $ex->getMessage());
		}
	}
}
