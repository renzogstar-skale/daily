<?php

namespace Grocy\Controllers;

use Grocy\Helpers\DairyAccess;
use Grocy\Services\LossExecutiveReportService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class LossExecutiveReportController extends BaseController
{
	public function Overview(Request $request, Response $response, array $args)
	{
		$svc = LossExecutiveReportService::GetInstance();
		$q = $request->getQueryParams();
		$userId = defined('GROCY_USER_ID') ? GROCY_USER_ID : 1;
		// Informe de toda la red: solo para usuarios sin restricción de sucursal
		$networkUser = DairyAccess::AllowedBranches() === null;
		$caughtUp = [];
		if ($networkUser)
		{
			// Respaldo del programador del sistema: pone al día las programaciones vencidas al abrir la página
			try
			{
				$caughtUp = $svc->RunDue('catch_up');
			}
			catch (\Throwable $e)
			{
				$caughtUp = [['status' => 'error', 'error' => $e->getMessage()]];
			}
		}
		$type = isset(LossExecutiveReportService::PERIODS[$q['type'] ?? '']) ? $q['type'] : 'daily';
		$id = !empty($q['id']) ? intval($q['id']) : $svc->Latest($type);
		$report = ($networkUser && $id) ? $svc->Get($id) : null;
		if ($report)
		{
			$svc->MarkRead($report['id'], $userId);
		}
		return $this->RenderPage($response, 'loss_executive_report', [
			'networkUser' => $networkUser,
			'canManage' => DairyAccess::HasAny([\Grocy\Controllers\Users\User::PERMISSION_LOSS_PREVENTION, \Grocy\Controllers\Users\User::PERMISSION_ADMIN]),
			'report' => $report,
			'type' => $type,
			'history' => $networkUser ? $svc->History(null, 40) : [],
			'schedules' => $svc->Schedules(),
			'caughtUp' => $caughtUp,
			'print' => !empty($q['print'])
		]);
	}
}
