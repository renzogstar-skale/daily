<?php

namespace Grocy\Controllers;

use Grocy\Controllers\Users\User;
use Grocy\Helpers\DairyAccess;
use Grocy\Services\DairyDynamicScheduleService;
use Grocy\Services\DatabaseService;
use Grocy\Services\WorkforceScheduleEquivalenceService;
use Grocy\Services\WorkforceScheduleService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class ScheduleController extends BaseController
{
	public function Overview(Request $request, Response $response, array $args)
	{
		$svc = WorkforceScheduleService::GetInstance();
		$dynSvc = DairyDynamicScheduleService::GetInstance();
		$eqSvc = WorkforceScheduleEquivalenceService::GetInstance();
		$q = $request->getQueryParams();
		$db = DatabaseService::GetInstance()->GetDbConnectionRaw();
		$canManage = DairyAccess::HasAny([User::PERMISSION_WORKFORCE_SCHEDULE_MANAGE]);
		$canAttendance = DairyAccess::HasAny([User::PERMISSION_WORKFORCE_ATTENDANCE_MANAGE, User::PERMISSION_WORKFORCE_SCHEDULE_MANAGE]);
		$canForecast = DairyAccess::HasAny([User::PERMISSION_WORKFORCE_SCHEDULE_MANAGE, User::PERMISSION_KPIS_VIEW]);
		$my = $svc->GetMySchedule(GROCY_USER_ID);

		$demoSvc = \Grocy\Services\DemoSchedulingScenarioService::GetInstance();
		$demoBranchId = $demoSvc->EnsureDemoBranch();

		// La empresa de ejemplo ya tiene su personal y turnos: no se re-siembra el escenario guionizado al abrir la página

		$branchId = !empty($q['branch_id']) ? intval($q['branch_id']) : \Grocy\Services\AttendanceService::GetInstance()->ResolveBranchId(null);
		// Semana DEMO por defecto; un ?week= inválido o que no sea lunes se normaliza al lunes de esa semana
		$monday = '2026-11-02';
		if (!empty($q['week']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $q['week']) && strtotime($q['week']) !== false)
		{
			$monday = date('Y-m-d', strtotime('monday this week', strtotime($q['week'])));
		}
		$sunday = date('Y-m-d', strtotime("$monday +6 day"));
		$from28 = date('Y-m-d', strtotime('-27 days'));

		$weekData = $svc->GetWeek($branchId, $monday);

		// Motor Inteligente de Planificación según Ventas
		$dynamicPlan = $dynSvc->CalculateDynamicPlan($branchId, $monday, $q);
		$kpis = $dynamicPlan['kpis'];
		$analytics = $dynamicPlan['analytics'];
		$sidebar = $dynamicPlan['sidebar'];
		$deptConfigs = $dynSvc->GetDepartmentConfigs($branchId);

		// DAILY: Motor Inteligente de Horarios, Equivalencia y Planilla 2026
		$equityData = $eqSvc->GetEquityDashboardData($branchId, $q);

		// Order ONLY real employees by department and name
		$orderedEmployees = $weekData['employees'];
		usort($orderedEmployees, function($a, $b) {
			if ($a->department === $b->department) {
				return strcmp($a->name, $b->name);
			}
			return strcmp($a->department, $b->department);
		});

		// Recommended adjustments mapping for employees (respecting contracts and fair distribution)
		$empRecMap = [];
		foreach ($sidebar['recommendations'] as $rec)
		{
			if (!empty($rec['employee_id']))
			{
				$empRecMap[$rec['employee_id']] = $rec;
			}
		}

		foreach ($orderedEmployees as &$emp)
		{
			$empId = intval($emp->id);
			if (isset($empRecMap[$empId]))
			{
				$emp->original_hours = $empRecMap[$empId]['original_hours'] . 'h';
				$emp->recommended_hours = $empRecMap[$empId]['recommended_hours'] . 'h';
				$emp->hours_delta = ($empRecMap[$empId]['delta_hours'] >= 0 ? '+' : '') . $empRecMap[$empId]['delta_hours'] . 'h';
				$emp->rec_reason = $empRecMap[$empId]['reason'];
			}
			else
			{
				$tot = $weekData['totals'][$emp->id]['scheduled'] ?? ($emp->weekly_hours ?: 42);
				$emp->original_hours = $tot . 'h';
				$emp->recommended_hours = $tot . 'h';
				$emp->hours_delta = '0h';
				$emp->rec_reason = 'Horario validado según planilla oficial';
			}
		}
		unset($emp);
		$weekData['employees'] = $orderedEmployees;

		// Bottom cards data
		$bottomCards = [
			'requests' => [
				[
					'name' => 'Lionel Messi',
					'type' => 'Capacitación externa',
					'dates' => '4 - 6 Nov',
					'status' => 'Pendiente',
					'badge_class' => 'sch-badge-warning',
					'avatar' => 'LM',
					'avatar_color' => '#2563eb'
				],
				[
					'name' => 'Taylor Swift',
					'type' => 'Permiso médico especialista',
					'dates' => '5 Nov',
					'status' => 'Requiere aprobación',
					'badge_class' => 'sch-badge-danger',
					'avatar' => 'TS',
					'avatar_color' => '#7c3aed'
				],
				[
					'name' => 'Cristiano Ronaldo',
					'type' => 'Permuta con Shakira Mebarak',
					'dates' => '4 Nov',
					'status' => 'Por revisar',
					'badge_class' => 'sch-badge-info',
					'avatar' => 'CR',
					'avatar_color' => '#0d9488'
				]
			],
			'hourly_coverage' => $analytics['hourly_coverage'],
			'productivity' => [
				'rate' => 94,
				'metrics' => [
					['name' => 'Asistencia', 'value' => '94%', 'delta' => '↑ +2%', 'tone' => 'success', 'color' => '#10b981'],
					['name' => 'Puntualidad', 'value' => '92%', 'delta' => '↑ +1%', 'tone' => 'success', 'color' => '#06b6d4'],
					['name' => 'Cumplimiento de turnos', 'value' => '96%', 'delta' => '↑ +3%', 'tone' => 'success', 'color' => '#3b82f6'],
					['name' => 'Horas extra', 'value' => $kpis['overtime']['sub'], 'delta' => '↓ -32%', 'tone' => 'success', 'color' => '#f59e0b']
				]
			]
		];

		// Selected shift for the detailed drawer
		$selectedShift = [
			'date_formatted' => 'Lunes 2 de noviembre',
			'start_time' => '10:00',
			'end_time' => '20:00',
			'duration' => '9.5h',
			'department' => 'Accesorios',
			'role' => 'E.ACCESORIOS',
			'employee_name' => 'Lionel Messi',
			'employee_id' => 1164,
			'employee_avatar' => 'LM',
			'status' => 'Confirmado',
			'branch' => \Grocy\Services\BranchService::NameById(1),
			'cost' => '$152.00'
		];

		// Monthly Schedule Engine 2026-2030+
		$selectedYear = isset($q['year']) ? intval($q['year']) : 2026;
		$selectedMonth = isset($q['month_num']) ? intval($q['month_num']) : (isset($q['month']) && preg_match('/^\d{4}-(\d{2})$/', $q['month'], $mMatch) ? intval($mMatch[1]) : 11);
		if (isset($q['month']) && preg_match('/^(\d{4})-(\d{2})$/', $q['month'], $mMatch))
		{
			$selectedYear = intval($mMatch[1]);
			$selectedMonth = intval($mMatch[2]);
		}

		$autoSchedSvc = \Grocy\Services\WorkforceAutoScheduleService::GetInstance();
		$monthlyScheduleData = $autoSchedSvc->GetMonthSchedule($branchId, $selectedYear, $selectedMonth, true);
		$skillsMatrix = $autoSchedSvc->GetSkillsMatrix();
		$colombianHolidays = $autoSchedSvc->GetColombianHolidays($selectedYear);
		$vacationsList = $autoSchedSvc->GetVacations();

		// Data for Specialized Views: Por Sucursal, Por Área, Por Empleado, Lista de Turnos
		$selectedPayrollCode = !empty($q['employee']) ? strval($q['employee']) : null;
		$branchViewData = $autoSchedSvc->GetBranchViewData($branchId, $monday);
		$areaViewData = $autoSchedSvc->GetAreaViewData($branchId, $monday);
		$employeeViewData = $autoSchedSvc->GetEmployeeViewData($branchId, $selectedPayrollCode, $monday);

		$shiftListFilters = [
			'start_date' => $q['start_date'] ?? $monday,
			'end_date' => $q['end_date'] ?? date('Y-m-d', strtotime("$monday +13 days")),
			'payroll_code' => $q['payroll_code'] ?? null,
			'department' => $q['department'] ?? null,
			'shift_type' => $q['shift_type'] ?? null,
			'search' => $q['search'] ?? null,
			'limit' => 200
		];
		$shiftListViewData = $autoSchedSvc->GetShiftListViewData($branchId, $shiftListFilters);

		$peakEngineSvc = \Grocy\Services\WorkforcePeakEngineService::GetInstance();
		$peakDashboardData = $peakEngineSvc->GetPeakDashboardData($branchId, $monday);

		// DEMO Scenario Data & 30-min Coverage Matrix
		$matrixDate = !empty($q['matrix_date']) ? strval($q['matrix_date']) : date('Y-m-d');
		$matrixDept = !empty($q['matrix_dept']) ? strval($q['matrix_dept']) : 'all';
		$demandScenario = !empty($q['demand_scenario']) ? strval($q['demand_scenario']) : 'normal';
		$coverageMatrix30Min = $demoSvc->GetCoverageMatrix30Min($branchId, $matrixDate, $matrixDept, $demandScenario);
		$validationScenarios = $demoSvc->GetValidationScenarios();
		$demoAreas = $demoSvc->GetAreasDefinition();
		$demoEmployees = $demoSvc->GetEmployeesDefinition();
		$simulatedScenarios = [
			'normal' => 'Día habitual de operaciones',
			'low' => 'Día de baja actividad (Martes regular)',
			'weekend_peak' => 'Fin de semana de mayor demanda (Sábado pico)',
			'promo_elec' => 'Promoción especial de Electrónica (Cyber/TecnoDay)',
			'intake_dairy_fruver' => 'Recepción mercancía Lácteos y Fruver (06:00–11:00)',
			'bodega_surge' => 'Pico de preparación en Bodega (E-Commerce/Despachos)'
		];

		return $this->RenderPage($response, 'schedule', [
			'viewJsExists' => false,
			'branchId' => $branchId,
			'demoBranchId' => $demoBranchId,
			'isDemoScenario' => ($branchId === $demoBranchId),
			'demoScenarioName' => \Grocy\Services\DemoSchedulingScenarioService::SCENARIO_NAME,
			'coverageMatrix30Min' => $coverageMatrix30Min,
			'validationScenarios' => $validationScenarios,
			'demoAreas' => $demoAreas,
			'demoEmployees' => $demoEmployees,
			'simulatedScenarios' => $simulatedScenarios,
			'currentMatrixDate' => $matrixDate,
			'currentMatrixDept' => $matrixDept,
			'currentDemandScenario' => $demandScenario,
			'branches' => DairyAccess::FilterBranches($db->query('SELECT id, name FROM branches ORDER BY id')->fetchAll(\PDO::FETCH_OBJ)),
			'equityData' => $equityData,
			'peakDashboardData' => $peakDashboardData,
			'monthlyScheduleData' => $monthlyScheduleData,
			'branchViewData' => $branchViewData,
			'areaViewData' => $areaViewData,
			'employeeViewData' => $employeeViewData,
			'shiftListViewData' => $shiftListViewData,
			'selectedYear' => $selectedYear,
			'selectedMonth' => $selectedMonth,
			'skillsMatrix' => $skillsMatrix,
			'colombianHolidays' => $colombianHolidays,
			'vacationsList' => $vacationsList,
			'week' => $weekData,
			'monday' => $monday,
			'sunday' => $sunday,
			'kpis' => $kpis,
			'analytics' => $analytics,
			'sidebar' => $sidebar,
			'dynamicPlan' => $dynamicPlan,
			'departments' => $dynamicPlan['departments'],
			'budget' => $dynamicPlan['budget'],
			'sales' => $dynamicPlan['sales'],
			'deptConfigs' => $deptConfigs,
			'bottomCards' => $bottomCards,
			'selectedShift' => $selectedShift,
			'month' => preg_match('/^\d{4}-\d{2}$/', $q['month'] ?? '') ? $q['month'] : substr($monday, 0, 7),
			'monthSummary' => $svc->GetMonthSummary($branchId, preg_match('/^\d{4}-\d{2}$/', $q['month'] ?? '') ? $q['month'] : substr($monday, 0, 7)),
			'my' => $my,
			'canManage' => $canManage,
			'canAttendance' => $canAttendance,
			'canForecast' => $canForecast,
			'canOvertime' => DairyAccess::HasAny([User::PERMISSION_WORKFORCE_OVERTIME_APPROVE]),
			'forecastBands' => \Grocy\Services\LaborForecastService::Bands(),
			'allEmployees' => $canManage ? $svc->GetEmployees($branchId, true) : [],
			'users' => $canManage ? $db->query("SELECT id, username, TRIM(IFNULL(first_name, '') || ' ' || IFNULL(last_name, '')) AS display FROM users ORDER BY username")->fetchAll(\PDO::FETCH_OBJ) : [],
			'requests' => $canManage ? $svc->GetPendingRequests($branchId) : ['absences' => [], 'swaps' => []],
			'attendance' => $canAttendance ? $svc->GetAttendance($branchId, $monday, $sunday) : [],
			'productivity' => $canManage ? $svc->GetProductivity($branchId, $monday, $sunday) : [],
			'coverageReqs' => $svc->GetCoverageRequirements($branchId),
			'certifications' => $canManage ? $svc->GetCertifications($branchId) : [],
			'roleRequirements' => $canManage ? $svc->GetRoleRequirements() : [],
			'wasteBands' => $canManage ? $svc->GetWasteByTimeBand($branchId, $from28, date('Y-m-d')) : [],
			'absenceTypes' => WorkforceScheduleService::ABSENCE_TYPES
		]);
	}
}
