<?php

namespace Grocy\Controllers\Api;

use Grocy\Controllers\Users\User;
use Grocy\Helpers\DairyAccess;
use Grocy\Services\AttendanceService;
use Grocy\Services\WorkforceScheduleService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class AttendanceApiController extends BaseApiController
{
	private function Svc(): AttendanceService
	{
		return AttendanceService::GetInstance();
	}

	private function Run(Response $response, callable $fn)
	{
		try
		{
			return $this->ApiResponse($response, $fn() ?? ['success' => true]);
		}
		catch (\Exception $ex)
		{
			return $this->GenericErrorResponse($response, $ex->getMessage());
		}
	}

	private function Body(Request $request): array
	{
		return $request->getParsedBody() ?? [];
	}

	public function GetOverview(Request $request, Response $response, array $args)
	{
		$q = $request->getQueryParams();
		$branchId = !empty($q['branch_id']) ? intval($q['branch_id']) : null;
		$date = !empty($q['date']) ? strval($q['date']) : '2026-11-04';
		$dept = !empty($q['department']) ? strval($q['department']) : 'all';
		$status = !empty($q['status']) ? strval($q['status']) : 'all';
		$search = !empty($q['search']) ? trim(strval($q['search'])) : null;

		return $this->Run($response, function () use ($branchId, $date, $dept, $status, $search) {
			return $this->Svc()->GetAttendanceOverview($branchId, $date, $dept, $status, $search);
		});
	}

	public function RecordAttendance(Request $request, Response $response, array $args)
	{
		User::CheckPermission($request, User::PERMISSION_WORKFORCE_ATTENDANCE_MANAGE);
		$body = $this->Body($request);

		return $this->Run($response, function () use ($body) {
			return $this->Svc()->RecordManualAttendance($body, GROCY_USER_ID);
		});
	}

	public function RequestAdjustment(Request $request, Response $response, array $args)
	{
		$body = $this->Body($request);

		return $this->Run($response, function () use ($body) {
			return $this->Svc()->RequestAdjustment($body, GROCY_USER_ID);
		});
	}

	public function DecideAdjustment(Request $request, Response $response, array $args)
	{
		User::CheckPermission($request, User::PERMISSION_WORKFORCE_SCHEDULE_MANAGE);
		$body = $this->Body($request);
		$adjId = intval($args['id']);
		$approve = !empty($body['approve']);
		$notes = !empty($body['notes']) ? strval($body['notes']) : null;

		return $this->Run($response, function () use ($adjId, $approve, $notes) {
			return $this->Svc()->DecideAdjustment($adjId, $approve, GROCY_USER_ID, $notes);
		});
	}

	public function QuickCheckoutAll(Request $request, Response $response, array $args)
	{
		User::CheckPermission($request, User::PERMISSION_WORKFORCE_ATTENDANCE_MANAGE);
		$body = $this->Body($request);
		$branchId = intval($body['branch_id'] ?? 1);
		$date = !empty($body['date']) ? strval($body['date']) : date('Y-m-d');

		return $this->Run($response, function () use ($branchId, $date) {
			return $this->Svc()->QuickCheckoutAll($branchId, $date, GROCY_USER_ID);
		});
	}

	public function ResetDemo(Request $request, Response $response, array $args)
	{
		User::CheckPermission($request, User::PERMISSION_WORKFORCE_SCHEDULE_MANAGE);
		$body = $this->Body($request);
		$branchId = intval($body['branch_id'] ?? 9);
		$date = !empty($body['date']) ? strval($body['date']) : '2026-11-04';

		return $this->Run($response, function () use ($branchId, $date) {
			return $this->Svc()->ResetDemoAttendance($branchId, $date);
		});
	}

	/**
	 * Sin employee_id se ficha al empleado vinculado al usuario. Fichar a otra persona exige permiso de asistencia
	 * y acceso a su sucursal (antes cualquier usuario podía fichar por otro).
	 */
	private function TargetEmployee(Request $request, array $body): int
	{
		$svc = WorkforceScheduleService::GetInstance();
		$own = $svc->GetEmployeeByUser(GROCY_USER_ID);
		$empId = intval($body['employee_id'] ?? 0);
		if ($empId <= 0)
		{
			if (!$own)
			{
				throw new \Exception('Su usuario no está vinculado a una ficha de empleado');
			}
			return intval($own->id);
		}
		if (!$own || intval($own->id) !== $empId)
		{
			User::CheckPermission($request, User::PERMISSION_WORKFORCE_ATTENDANCE_MANAGE);
			if (!DairyAccess::CanSeeBranch(intval($svc->GetEmployee($empId)->branch_id)))
			{
				throw new \Exception('No tiene acceso a la sucursal de ese empleado');
			}
		}
		return $empId;
	}

	private static function Geo(array $body): ?array
	{
		return isset($body['geo']) && is_array($body['geo']) ? $body['geo'] : null;
	}

	public function ClockIn(Request $request, Response $response, array $args)
	{
		$body = $this->Body($request);
		$notes = !empty($body['notes']) ? mb_substr(strval($body['notes']), 0, 300) : null;
		$source = in_array($body['source'] ?? '', ['web', 'mobile'], true) ? $body['source'] : 'web';

		return $this->Run($response, function () use ($request, $body, $notes, $source) {
			$empId = $this->TargetEmployee($request, $body);
			$own = WorkforceScheduleService::GetInstance()->GetEmployeeByUser(GROCY_USER_ID);
			return WorkforceScheduleService::GetInstance()->ClockIn($empId, ($own && intval($own->id) === $empId) ? $source : 'manager', GROCY_USER_ID, $notes, self::Geo($body));
		});
	}

	public function ClockOut(Request $request, Response $response, array $args)
	{
		$body = $this->Body($request);

		return $this->Run($response, function () use ($request, $body) {
			return WorkforceScheduleService::GetInstance()->ClockOut($this->TargetEmployee($request, $body), GROCY_USER_ID, self::Geo($body));
		});
	}

	public function StartBreak(Request $request, Response $response, array $args)
	{
		$body = $this->Body($request);
		$empId = intval($body['employee_id'] ?? 0);

		return $this->Run($response, function () use ($empId) {
			return $this->Svc()->StartBreak($empId, GROCY_USER_ID);
		});
	}

	public function EndBreak(Request $request, Response $response, array $args)
	{
		$body = $this->Body($request);
		$empId = intval($body['employee_id'] ?? 0);

		return $this->Run($response, function () use ($empId) {
			return $this->Svc()->EndBreak($empId, GROCY_USER_ID);
		});
	}
}
