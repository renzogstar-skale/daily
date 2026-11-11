<?php

namespace Grocy\Controllers;

use Grocy\Controllers\Users\User;
use Grocy\Helpers\DairyAccess;
use Grocy\Services\AttendanceService;
use Grocy\Services\DatabaseService;
use Grocy\Services\DemoSchedulingScenarioService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class AttendanceController extends BaseController
{
	public function Overview(Request $request, Response $response, array $args)
	{
		$isEmpSimulated = DairyAccess::GetSimulatedRole() === 'empleado';
		$canManage = !$isEmpSimulated && DairyAccess::HasAny([User::PERMISSION_WORKFORCE_SCHEDULE_MANAGE]);
		$canAttendance = !$isEmpSimulated && DairyAccess::HasAny([User::PERMISSION_WORKFORCE_ATTENDANCE_MANAGE, User::PERMISSION_WORKFORCE_SCHEDULE_MANAGE]);
		$canOvertime = !$isEmpSimulated && DairyAccess::HasAny([User::PERMISSION_WORKFORCE_OVERTIME_APPROVE]);

		$q = $request->getQueryParams();
		$attSvc = AttendanceService::GetInstance();
		$demoSvc = DemoSchedulingScenarioService::GetInstance();

		// Resolver sucursal: predeterminado DEMO Tienda Integral si no se pasa branch_id
		$demoBranchId = $demoSvc->EnsureDemoBranch();
		$branchId = $attSvc->ResolveBranchId(!empty($q['branch_id']) ? intval($q['branch_id']) : null);

		$defaultDate = date('Y-m-d');
		$date = !empty($q['date']) ? strval($q['date']) : $defaultDate;
		$dept = !empty($q['department']) ? strval($q['department']) : 'all';
		$status = !empty($q['status']) ? strval($q['status']) : 'all';
		$search = !empty($q['search']) ? trim(strval($q['search'])) : null;

		$db = DatabaseService::GetInstance()->GetDbConnectionRaw();
		$allBranchEmployees = $db->query("SELECT id, user_id, name, payroll_code, department, position FROM employees WHERE branch_id = $branchId AND active = 1 ORDER BY department, name")->fetchAll(\PDO::FETCH_ASSOC);

		// Manejo estricto de rol (Empleado NO puede escalar a Vista Supervisor ni consultar fichas de compañeros)
		$ownEmp = \Grocy\Services\WorkforceScheduleService::GetInstance()->GetEmployeeByUser(GROCY_USER_ID);
		if ($canAttendance || $canManage)
		{
			$roleView = (!empty($q['role_view']) && in_array($q['role_view'], ['employee', 'supervisor'], true)) ? $q['role_view'] : 'supervisor';
			$employees = $allBranchEmployees;
			$empIds = array_column($employees, 'id');
			$reqEmpId = !empty($q['employee_id']) ? intval($q['employee_id']) : 0;
			$selectedEmployeeId = in_array($reqEmpId, $empIds) ? $reqEmpId : ($empIds[0] ?? 1);
			$overview = $attSvc->GetAttendanceOverview($branchId, $date, $dept, $status, $search);
		}
		else
		{
			$roleView = 'employee';
			$ownId = $ownEmp ? intval($ownEmp->id) : intval($allBranchEmployees[0]['id'] ?? 1);
			$employees = array_values(array_filter($allBranchEmployees, fn($e) => intval($e['id']) === $ownId));
			if (empty($employees) && !empty($allBranchEmployees))
			{
				$employees = [$allBranchEmployees[0]];
			}
			$selectedEmployeeId = intval($employees[0]['id'] ?? $ownId);
			$overview = [
				'branch_id' => $branchId,
				'branch_name' => \Grocy\Services\BranchService::NameById($branchId),
				'selected_date' => $date,
				'date' => $date,
				'selected_dept' => 'all',
				'selected_status' => 'all',
				'branches' => [],
				'departments' => [],
				'department_images' => [],
				'kpis' => ['scheduled_count' => 0, 'present_count' => 0, 'late_count' => 0, 'absent_count' => 0, 'pending_count' => 0, 'attendance_pct' => 0, 'worked_hours' => 0],
				'records' => [],
				'pending_adjustments' => [],
				'dept_coverage' => [],
				'hourly_coverage' => [],
				'alerts' => [],
				'supervisor_highlights' => []
			];
		}

		$employeePortal = $attSvc->GetEmployeePortalData($selectedEmployeeId, $date);
		$coverageByDeptHour = ($canAttendance || $canManage)
			? \Grocy\Services\WorkforceScheduleService::GetInstance()->CoverageByDepartmentAndHour($branchId, $date)
			: ['hours' => [], 'departments' => []];

		return $this->RenderPage($response, 'attendance', [
			'overview' => $overview,
			'employees' => $employees,
			'canManage' => $canManage,
			'canAttendance' => $canAttendance,
			'canOvertime' => $canOvertime,
			'coverageByDeptHour' => $coverageByDeptHour,
			'currentUserId' => GROCY_USER_ID,
			'currentUserName' => defined('GROCY_USER_USERNAME') ? GROCY_USER_USERNAME : 'Usuario',
			'currentUserRole' => ($canAttendance || $canManage) ? 'Supervisor / Gerente' : 'Colaborador',
			'roleView' => $roleView,
			'selectedEmployeeId' => $selectedEmployeeId,
			'employeePortal' => $employeePortal
		]);
	}
}
