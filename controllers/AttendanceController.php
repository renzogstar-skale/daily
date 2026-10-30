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
		$canManage = DairyAccess::HasAny([User::PERMISSION_WORKFORCE_SCHEDULE_MANAGE]);
		$canAttendance = DairyAccess::HasAny([User::PERMISSION_WORKFORCE_ATTENDANCE_MANAGE, User::PERMISSION_WORKFORCE_SCHEDULE_MANAGE, User::PERMISSION_WORKFORCE]);
		$canOvertime = DairyAccess::HasAny([User::PERMISSION_WORKFORCE_OVERTIME_APPROVE]);

		$q = $request->getQueryParams();
		$attSvc = AttendanceService::GetInstance();
		$demoSvc = DemoSchedulingScenarioService::GetInstance();

		// Resolver sucursal: predeterminado DEMO Tienda Integral si no se pasa branch_id
		$demoBranchId = $demoSvc->EnsureDemoBranch();
		$branchId = $attSvc->ResolveBranchId(!empty($q['branch_id']) ? intval($q['branch_id']) : null);

		// Si es la sucursal DEMO y no se especifica fecha, usar la fecha de demostración con 25 turnos: 2026-11-04
		$defaultDate = date('Y-m-d');
		$date = !empty($q['date']) ? strval($q['date']) : $defaultDate;
		$dept = !empty($q['department']) ? strval($q['department']) : 'all';
		$status = !empty($q['status']) ? strval($q['status']) : 'all';
		$search = !empty($q['search']) ? trim(strval($q['search'])) : null;

		$overview = $attSvc->GetAttendanceOverview($branchId, $date, $dept, $status, $search);

		$db = DatabaseService::GetInstance()->GetDbConnectionRaw();
		$employees = $db->query("SELECT id, name, payroll_code, department, position FROM employees WHERE branch_id = $branchId AND active = 1 ORDER BY department, name")->fetchAll(\PDO::FETCH_ASSOC);

		// Manejo de rol (Empleado vs Supervisor)
		$defaultRoleView = (DairyAccess::GetSimulatedRole() === 'empleado') ? 'employee' : 'supervisor';
		$roleView = (!empty($q['role_view']) && in_array($q['role_view'], ['employee', 'supervisor'])) ? $q['role_view'] : $defaultRoleView;
		$empIds = array_column($employees, 'id');
		$reqEmpId = !empty($q['employee_id']) ? intval($q['employee_id']) : 0;
		$selectedEmployeeId = in_array($reqEmpId, $empIds) ? $reqEmpId : ($empIds[0] ?? 1);
		$employeePortal = $attSvc->GetEmployeePortalData($selectedEmployeeId, $date);

		return $this->RenderPage($response, 'attendance', [
			'overview' => $overview,
			'employees' => $employees,
			'canManage' => $canManage,
			'canAttendance' => $canAttendance,
			'canOvertime' => $canOvertime,
			'currentUserId' => GROCY_USER_ID,
			'currentUserName' => 'rglovine',
			'currentUserRole' => 'Gerente / Administrador',
			'roleView' => $roleView,
			'selectedEmployeeId' => $selectedEmployeeId,
			'employeePortal' => $employeePortal
		]);
	}
}
