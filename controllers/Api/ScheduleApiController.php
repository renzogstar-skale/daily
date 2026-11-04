<?php

namespace Grocy\Controllers\Api;

use Grocy\Controllers\Users\User;
use Grocy\Helpers\DairyAccess;
use Grocy\Services\DairyDynamicScheduleService;
use Grocy\Services\LaborForecastService;
use Grocy\Services\WorkforceAutoScheduleService;
use Grocy\Services\WorkforceScheduleEquivalenceService;
use Grocy\Services\WorkforceScheduleService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Personal y turnos. Gestión: WORKFORCE_SCHEDULE_MANAGE; asistencia de terceros: WORKFORCE_ATTENDANCE_MANAGE.
 * Cada empleado vinculado a un usuario puede fichar, ver su horario y pedir ausencias/cambios de sus propios turnos.
 */
class ScheduleApiController extends BaseApiController
{
	private function Svc(): WorkforceScheduleService
	{
		return WorkforceScheduleService::GetInstance();
	}

	private function Fc(): LaborForecastService
	{
		return LaborForecastService::GetInstance();
	}

	private function Dyn(): DairyDynamicScheduleService
	{
		return DairyDynamicScheduleService::GetInstance();
	}

	private function Eq(): WorkforceScheduleEquivalenceService
	{
		return WorkforceScheduleEquivalenceService::GetInstance();
	}

	private function Auto(): WorkforceAutoScheduleService
	{
		return WorkforceAutoScheduleService::GetInstance();
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

	private function Manage(Request $request): void
	{
		User::CheckPermission($request, User::PERMISSION_WORKFORCE_SCHEDULE_MANAGE);
	}

	private function ForecastPerm(Request $request): void
	{
		if (!DairyAccess::HasAny([User::PERMISSION_WORKFORCE_SCHEDULE_MANAGE, User::PERMISSION_KPIS_VIEW]))
		{
			User::CheckPermission($request, User::PERMISSION_WORKFORCE_SCHEDULE_MANAGE);
		}
	}

	private function Branch(int $branchId): int
	{
		if ($branchId <= 0)
		{
			throw new \Exception('Indique la sucursal (branch_id)');
		}
		if (!DairyAccess::CanSeeBranch($branchId))
		{
			throw new \Exception('Sin acceso a esa sucursal');
		}
		return $branchId;
	}

	private function Day(?string $date): string
	{
		$d = $date ?: date('Y-m-d');
		if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $d))
		{
			throw new \Exception('Fecha inválida (AAAA-MM-DD)');
		}
		return $d;
	}

	public function GetWeek(Request $request, Response $response, array $args)
	{
		$q = $request->getQueryParams();
		return $this->Run($response, function () use ($q) {
			$branchId = $this->Branch(intval($q['branch_id'] ?? 1) ?: 1);
			$monday = $q['week'] ?? date('Y-m-d', strtotime('monday this week'));
			$w = $this->Svc()->GetWeek($branchId, $monday);
			if (!DairyAccess::HasAny([User::PERMISSION_WORKFORCE_SCHEDULE_MANAGE, User::PERMISSION_WORKFORCE_ATTENDANCE_MANAGE]))
			{
				unset($w['labor_cost']);
				$w['planned_count'] = 0;
			}
			return $w;
		});
	}

	public function GetMySchedule(Request $request, Response $response, array $args)
	{
		return $this->Run($response, function () {
			return $this->Svc()->GetMySchedule(GROCY_USER_ID);
		});
	}

	public function SaveEmployee(Request $request, Response $response, array $args)
	{
		$this->Manage($request);
		$body = $this->Body($request);
		return $this->Run($response, function () use ($body) {
			$id = $this->Svc()->SaveEmployee($body, GROCY_USER_ID);
			return ['id' => $id];
		});
	}

	public function SetAvailability(Request $request, Response $response, array $args)
	{
		$this->Manage($request);
		$body = $this->Body($request);
		return $this->Run($response, function () use ($body, $args) {
			$this->Svc()->SetAvailability(intval($args['id']), (array)($body['availability'] ?? []));
			return ['success' => true];
		});
	}

	public function CheckShift(Request $request, Response $response, array $args)
	{
		$body = $this->Body($request);
		return $this->Run($response, function () use ($body) {
			return $this->Svc()->CheckConflicts(
				intval($body['employee_id'] ?? 0),
				strval($body['shift_date'] ?? ''),
				strval($body['start_time'] ?? ''),
				strval($body['end_time'] ?? ''),
				intval($body['break_minutes'] ?? 0),
				!empty($body['exclude_shift_id']) ? intval($body['exclude_shift_id']) : null,
				$body['role'] ?? null
			);
		});
	}

	public function SaveShift(Request $request, Response $response, array $args)
	{
		$this->Manage($request);
		$body = $this->Body($request);
		return $this->Run($response, function () use ($body) {
			return $this->Svc()->SaveShift($body, GROCY_USER_ID);
		});
	}

	public function MoveShift(Request $request, Response $response, array $args)
	{
		$this->Manage($request);
		$body = $this->Body($request);
		return $this->Run($response, function () use ($body, $args) {
			return $this->Svc()->MoveShift(intval($args['id']), strval($body['shift_date']), strval($body['start_time']), strval($body['end_time']), intval($body['version']), GROCY_USER_ID);
		});
	}

	public function CancelShift(Request $request, Response $response, array $args)
	{
		$this->Manage($request);
		$body = $this->Body($request);
		return $this->Run($response, function () use ($body, $args) {
			$this->Svc()->CancelShift(intval($args['id']), strval($body['reason'] ?? 'Anulación manual'), intval($body['version'] ?? 0), GROCY_USER_ID);
			return ['success' => true];
		});
	}

	public function PublishWeek(Request $request, Response $response, array $args)
	{
		$this->Manage($request);
		$body = $this->Body($request);
		return $this->Run($response, function () use ($body) {
			$branchId = $this->Branch(intval($body['branch_id'] ?? 1));
			$monday = strval($body['week'] ?? date('Y-m-d', strtotime('monday this week')));
			return $this->Svc()->PublishWeek($branchId, $monday, GROCY_USER_ID);
		});
	}

	public function CopyWeek(Request $request, Response $response, array $args)
	{
		$this->Manage($request);
		$body = $this->Body($request);
		return $this->Run($response, function () use ($body) {
			$branchId = $this->Branch(intval($body['branch_id'] ?? 1));
			return $this->Svc()->CopyWeek($branchId, strval($body['from_week']), strval($body['to_week']), GROCY_USER_ID);
		});
	}

	public function SaveCoverage(Request $request, Response $response, array $args)
	{
		$this->Manage($request);
		$body = $this->Body($request);
		return $this->Run($response, function () use ($body) {
			$branchId = $this->Branch(intval($body['branch_id'] ?? 1));
			$this->Svc()->SaveCoverageRequirements($branchId, (array)($body['requirements'] ?? []));
			return ['success' => true];
		});
	}

	public function SuggestCoverage(Request $request, Response $response, array $args)
	{
		$this->Manage($request);
		$q = $request->getQueryParams();
		return $this->Run($response, function () use ($q) {
			$branchId = $this->Branch(intval($q['branch_id'] ?? 1));
			return $this->Svc()->SuggestCoverageFromSales($branchId, intval($q['weeks'] ?? 4));
		});
	}

	public function SaveCertification(Request $request, Response $response, array $args)
	{
		$this->Manage($request);
		$body = $this->Body($request);
		return $this->Run($response, function () use ($body) {
			return ['id' => $this->Svc()->SaveCertification($body)];
		});
	}

	public function DeleteCertification(Request $request, Response $response, array $args)
	{
		$this->Manage($request);
		return $this->Run($response, function () use ($args) {
			$this->Svc()->DeleteCertification(intval($args['id']));
			return ['success' => true];
		});
	}

	public function SaveRoleRequirements(Request $request, Response $response, array $args)
	{
		$this->Manage($request);
		$body = $this->Body($request);
		return $this->Run($response, function () use ($body) {
			$this->Svc()->SaveRoleRequirements((array)($body['requirements'] ?? []));
			return ['success' => true];
		});
	}

	public function BulkShifts(Request $request, Response $response, array $args)
	{
		$this->Manage($request);
		$body = $this->Body($request);
		return $this->Run($response, function () use ($body) {
			return $this->Svc()->BulkShifts($body, GROCY_USER_ID);
		});
	}

	public function DuplicateShift(Request $request, Response $response, array $args)
	{
		$this->Manage($request);
		$body = $this->Body($request);
		return $this->Run($response, function () use ($body, $args) {
			return $this->Svc()->DuplicateShift(intval($args['id']), (array)($body['dates'] ?? []), GROCY_USER_ID);
		});
	}

	public function SplitShift(Request $request, Response $response, array $args)
	{
		$this->Manage($request);
		$body = $this->Body($request);
		return $this->Run($response, function () use ($body, $args) {
			return $this->Svc()->SplitShift(intval($args['id']), strval($body['split_time']), !empty($body['second_employee_id']) ? intval($body['second_employee_id']) : null, intval($body['version']), GROCY_USER_ID);
		});
	}

	public function ShiftHistory(Request $request, Response $response, array $args)
	{
		$this->Manage($request);
		return $this->Run($response, function () use ($args) {
			return $this->Svc()->ShiftHistory(intval($args['id']));
		});
	}

	public function ChangeLog(Request $request, Response $response, array $args)
	{
		$this->Manage($request);
		$q = $request->getQueryParams();
		return $this->Run($response, function () use ($q) {
			$branchId = $this->Branch(intval($q['branch_id'] ?? 1));
			return $this->Svc()->ChangeLog($branchId, intval($q['limit'] ?? 50));
		});
	}

	public function Undo(Request $request, Response $response, array $args)
	{
		$this->Manage($request);
		$body = $this->Body($request);
		return $this->Run($response, function () use ($body) {
			return $this->Svc()->Undo(intval($body['change_id']), GROCY_USER_ID);
		});
	}

	public function GetTemplates(Request $request, Response $response, array $args)
	{
		$this->Manage($request);
		$q = $request->getQueryParams();
		return $this->Run($response, function () use ($q) {
			$branchId = $this->Branch(intval($q['branch_id'] ?? 1));
			return $this->Svc()->GetTemplates($branchId);
		});
	}

	public function SaveTemplate(Request $request, Response $response, array $args)
	{
		$this->Manage($request);
		$body = $this->Body($request);
		return $this->Run($response, function () use ($body) {
			$branchId = $this->Branch(intval($body['branch_id'] ?? 1));
			return ['id' => $this->Svc()->SaveTemplate($branchId, $body, GROCY_USER_ID)];
		});
	}

	public function DeleteTemplate(Request $request, Response $response, array $args)
	{
		$this->Manage($request);
		return $this->Run($response, function () use ($args) {
			$this->Svc()->DeleteTemplate(intval($args['id']));
			return ['success' => true];
		});
	}

	public function ApplyTemplate(Request $request, Response $response, array $args)
	{
		$this->Manage($request);
		$body = $this->Body($request);
		return $this->Run($response, function () use ($body, $args) {
			return $this->Svc()->ApplyTemplate(intval($args['id']), strval($body['week']), (array)($body['employee_ids'] ?? []), GROCY_USER_ID);
		});
	}

	public function GetTimesheet(Request $request, Response $response, array $args)
	{
		$this->Manage($request);
		$q = $request->getQueryParams();
		return $this->Run($response, function () use ($q) {
			$branchId = $this->Branch(intval($q['branch_id'] ?? 1));
			return $this->Svc()->GetTimesheet($branchId, strval($q['from']), strval($q['to']));
		});
	}

	public function GetAttendance(Request $request, Response $response, array $args)
	{
		$this->Manage($request);
		$q = $request->getQueryParams();
		return $this->Run($response, function () use ($q) {
			$branchId = $this->Branch(intval($q['branch_id'] ?? 1));
			return $this->Svc()->GetAttendance($branchId, strval($q['from']), strval($q['to']));
		});
	}

	public function GetAdjustments(Request $request, Response $response, array $args)
	{
		$this->Manage($request);
		$q = $request->getQueryParams();
		return $this->Run($response, function () use ($q) {
			$branchId = $this->Branch(intval($q['branch_id'] ?? 1));
			return $this->Svc()->GetPendingAdjustments($branchId);
		});
	}

	public function DecideAdjustment(Request $request, Response $response, array $args)
	{
		$this->Manage($request);
		$body = $this->Body($request);
		return $this->Run($response, function () use ($body, $args) {
			return $this->Svc()->DecideAdjustment(intval($args['id']), strval($body['status']), strval($body['notes'] ?? ''), GROCY_USER_ID);
		});
	}

	public function Forecast(Request $request, Response $response, array $args)
	{
		$this->ForecastPerm($request);
		$q = $request->getQueryParams();
		return $this->Run($response, function () use ($q) {
			$f = $this->Fc()->Forecast($this->Branch(intval($q['branch_id'] ?? 0)), $this->Day($q['date'] ?? null));
			$f['can_manage'] = DairyAccess::HasAny([User::PERMISSION_WORKFORCE_SCHEDULE_MANAGE]);
			$f['can_overtime'] = DairyAccess::HasAny([User::PERMISSION_WORKFORCE_OVERTIME_APPROVE]);
			if (!$f['can_manage'])
			{
				$f['shifts'] = [];
			}
			return $f;
		});
	}

	public function ForecastBand(Request $request, Response $response, array $args)
	{
		$this->Manage($request);
		$q = $request->getQueryParams();
		return $this->Run($response, function () use ($q) {
			return $this->Fc()->BandCandidates($this->Branch(intval($q['branch_id'] ?? 0)), $this->Day($q['date'] ?? null), intval($q['band'] ?? -1));
		});
	}

	public function ForecastRecommend(Request $request, Response $response, array $args)
	{
		$this->Manage($request);
		$body = $this->Body($request);
		return $this->Run($response, function () use ($body) {
			$canOt = DairyAccess::HasAny([User::PERMISSION_WORKFORCE_OVERTIME_APPROVE]);
			$plan = $this->Fc()->Recommend($this->Branch(intval($body['branch_id'] ?? 0)), $this->Day($body['date'] ?? null), $canOt && !empty($body['allow_overtime']));
			$plan['can_overtime'] = $canOt;
			return $plan;
		});
	}

	public function ForecastApply(Request $request, Response $response, array $args)
	{
		$this->Manage($request);
		$body = $this->Body($request);
		return $this->Run($response, function () use ($body) {
			return $this->Fc()->Apply($this->Branch(intval($body['branch_id'] ?? 0)), $this->Day($body['date'] ?? null), is_array($body['proposals'] ?? null) ? $body['proposals'] : [],
				strval($body['reason'] ?? ''), GROCY_USER_ID, DairyAccess::HasAny([User::PERMISSION_WORKFORCE_OVERTIME_APPROVE]));
		});
	}

	public function ForecastAccuracy(Request $request, Response $response, array $args)
	{
		$this->ForecastPerm($request);
		$q = $request->getQueryParams();
		return $this->Run($response, fn() => $this->Fc()->Accuracy($this->Branch(intval($q['branch_id'] ?? 0)), intval($q['days'] ?? 14)));
	}

	public function ForecastEvents(Request $request, Response $response, array $args)
	{
		$this->ForecastPerm($request);
		$q = $request->getQueryParams();
		return $this->Run($response, fn() => $this->Fc()->GetEvents($this->Branch(intval($q['branch_id'] ?? 0)), $this->Day($q['from'] ?? date('Y-m-d')), $this->Day($q['to'] ?? date('Y-m-d', strtotime('+60 day')))));
	}

	public function SaveForecastEvent(Request $request, Response $response, array $args)
	{
		$this->Manage($request);
		$body = $this->Body($request);
		return $this->Run($response, function () use ($body) {
			$b = intval($body['branch_id'] ?? 0);
			if ($b > 0)
			{
				$this->Branch($b);
			}
			return ['id' => $this->Fc()->SaveEvent($body, GROCY_USER_ID)];
		});
	}

	public function DeleteForecastEvent(Request $request, Response $response, array $args)
	{
		$this->Manage($request);
		return $this->Run($response, function () use ($args) {
			$e = $this->Fc()->GetEvent(intval($args['id']));
			if (intval($e['branch_id']) > 0)
			{
				$this->Branch(intval($e['branch_id']));
			}
			$this->Fc()->DeleteEvent(intval($args['id']));
		});
	}

	public function SaveForecastStandards(Request $request, Response $response, array $args)
	{
		$this->Manage($request);
		$body = $this->Body($request);
		return $this->Run($response, function () use ($body) {
			$this->Fc()->SaveStandards(is_array($body['standards'] ?? null) ? $body['standards'] : []);
		});
	}

	public function SimulateScenario(Request $request, Response $response, array $args)
	{
		$this->Manage($request);
		$body = $this->Body($request);
		return $this->Run($response, function () use ($body) {
			return $this->Fc()->Simulate($this->Branch(intval($body['branch_id'] ?? 0)), $this->Day($body['date'] ?? null), (array)($body['params'] ?? []));
		});
	}

	public function Scenarios(Request $request, Response $response, array $args)
	{
		$this->Manage($request);
		$q = $request->getQueryParams();
		return $this->Run($response, function () use ($q) {
			return $this->Fc()->GetScenarios($this->Branch(intval($q['branch_id'] ?? 0)));
		});
	}

	public function SaveScenario(Request $request, Response $response, array $args)
	{
		$this->Manage($request);
		$body = $this->Body($request);
		return $this->Run($response, function () use ($body) {
			return ['id' => $this->Fc()->SaveScenario($this->Branch(intval($body['branch_id'] ?? 0)), $this->Day($body['date'] ?? null), strval($body['name'] ?? ''), (array)($body['params'] ?? []), GROCY_USER_ID)];
		});
	}

	public function DeleteScenario(Request $request, Response $response, array $args)
	{
		$this->Manage($request);
		return $this->Run($response, function () use ($args) {
			$this->Branch($this->Fc()->GetScenarioBranch(intval($args['id'])));
			$this->Fc()->DeleteScenario(intval($args['id']));
		});
	}

	public function CreateCoverageRequest(Request $request, Response $response, array $args)
	{
		$this->Manage($request);
		$body = $this->Body($request);
		return $this->Run($response, function () use ($body) {
			$this->Branch(intval($body['branch_id'] ?? 0));
			return ['id' => $this->Fc()->CreateCoverageRequest($body, GROCY_USER_ID)];
		});
	}

	public function CloseCoverageRequest(Request $request, Response $response, array $args)
	{
		$this->Manage($request);
		$body = $this->Body($request);
		return $this->Run($response, function () use ($body, $args) {
			$this->Branch(intval($this->Fc()->GetCoverageRequest(intval($args['id']))['branch_id']));
			$this->Fc()->CloseCoverageRequest(intval($args['id']), strval($body['status'] ?? ''), GROCY_USER_ID);
		});
	}

	/* =========================================================================
	   DAIRY - Motor Inteligente de Horarios y Cobertura según Ventas (Módulo 35)
	   ========================================================================= */

	public function DynamicPlan(Request $request, Response $response, array $args)
	{
		$this->ForecastPerm($request);
		$q = $request->getQueryParams();
		return $this->Run($response, function () use ($q) {
			$branchId = $this->Branch(intval($q['branch_id'] ?? 1) ?: 1);
			$monday = strval($q['week'] ?? date('Y-m-d', strtotime('monday this week')));
			return $this->Dyn()->CalculateDynamicPlan($branchId, $monday, $q);
		});
	}

	public function DynamicPlanOptimize(Request $request, Response $response, array $args)
	{
		$this->Manage($request);
		$body = $this->Body($request);
		return $this->Run($response, function () use ($body) {
			$branchId = $this->Branch(intval($body['branch_id'] ?? 1) ?: 1);
			$monday = strval($body['week'] ?? date('Y-m-d', strtotime('monday this week')));
			return $this->Dyn()->CalculateDynamicPlan($branchId, $monday, $body);
		});
	}

	public function DecideRecommendation(Request $request, Response $response, array $args)
	{
		$this->Manage($request);
		$body = $this->Body($request);
		return $this->Run($response, function () use ($body, $args) {
			$recId = intval($args['id'] ?? $body['id'] ?? 0);
			$status = strval($body['status'] ?? 'approved');
			$notes = strval($body['notes'] ?? 'Decisión de manager');
			$modHours = isset($body['modified_hours']) && is_numeric($body['modified_hours']) ? floatval($body['modified_hours']) : null;
			return $this->Dyn()->DecideRecommendation($recId, $status, $modHours, $notes, GROCY_USER_ID);
		});
	}

	public function RunDailyAutomation(Request $request, Response $response, array $args)
	{
		$this->Manage($request);
		$body = $this->Body($request);
		return $this->Run($response, function () use ($body) {
			$branchId = $this->Branch(intval($body['branch_id'] ?? 1) ?: 1);
			$date = !empty($body['date']) ? strval($body['date']) : date('Y-m-d');
			return $this->Dyn()->RunDailyAutomation($branchId, $date, GROCY_USER_ID);
		});
	}

	public function GetDepartmentConfigs(Request $request, Response $response, array $args)
	{
		$this->ForecastPerm($request);
		$q = $request->getQueryParams();
		return $this->Run($response, function () use ($q) {
			$branchId = $this->Branch(intval($q['branch_id'] ?? 1) ?: 1);
			return array_values($this->Dyn()->GetDepartmentConfigs($branchId));
		});
	}

	public function SaveDepartmentConfigs(Request $request, Response $response, array $args)
	{
		$this->Manage($request);
		$body = $this->Body($request);
		return $this->Run($response, function () use ($body) {
			$branchId = $this->Branch(intval($body['branch_id'] ?? 1) ?: 1);
			$configs = is_array($body['configs'] ?? null) ? $body['configs'] : [];
			$this->Dyn()->SaveDepartmentConfigs($branchId, $configs);
			return ['success' => true];
		});
	}

	public function SimulateSalesScenario(Request $request, Response $response, array $args)
	{
		$this->Manage($request);
		$body = $this->Body($request);
		return $this->Run($response, function () use ($body) {
			$branchId = $this->Branch(intval($body['branch_id'] ?? 1) ?: 1);
			$date = !empty($body['date']) ? strval($body['date']) : date('Y-m-d');
			return $this->Dyn()->SimulateSalesScenario($branchId, $date, (array)($body['params'] ?? $body));
		});
	}

	/* ------------------------------------------------------------------
	 * DAILY: Motor Inteligente de Horarios, Equivalencia y Planilla 2026
	 * ------------------------------------------------------------------ */

	public function GetEquity(Request $request, Response $response, array $args)
	{
		$q = $request->getQueryParams();
		return $this->Run($response, function () use ($q) {
			$branchId = intval($q['branch_id'] ?? 1) ?: 1;
			return $this->Eq()->GetEquityDashboardData($branchId, $q);
		});
	}

	public function GenerateEquitySchedule(Request $request, Response $response, array $args)
	{
		$this->Manage($request);
		$body = $this->Body($request);
		return $this->Run($response, function () use ($body) {
			$branchId = intval($body['branch_id'] ?? 1) ?: 1;
			return $this->Eq()->GenerateSchedule($branchId, $body);
		});
	}

	public function PublishEquitySchedule(Request $request, Response $response, array $args)
	{
		$this->Manage($request);
		$body = $this->Body($request);
		return $this->Run($response, function () use ($body) {
			$branchId = intval($body['branch_id'] ?? 1) ?: 1;
			$manager = !empty($body['manager_name']) ? strval($body['manager_name']) : 'Gerente General';
			return $this->Eq()->PublishSchedule($branchId, $manager);
		});
	}

	public function PreviewShiftSwap(Request $request, Response $response, array $args)
	{
		$this->Manage($request);
		$body = $this->Body($request);
		return $this->Run($response, function () use ($body) {
			$codeA = strval($body['employee_a_code'] ?? '');
			$dateA = strval($body['date_a'] ?? '');
			$codeB = strval($body['employee_b_code'] ?? '');
			$dateB = strval($body['date_b'] ?? '');

			if (!$codeA || !$dateA || !$codeB || !$dateB)
			{
				throw new \Exception('Se requieren los colaboradores y fechas de ambos turnos.');
			}
			return $this->Eq()->SimulateShiftSwap($codeA, $dateA, $codeB, $dateB);
		});
	}

	public function ApplyShiftSwap(Request $request, Response $response, array $args)
	{
		$this->Manage($request);
		$body = $this->Body($request);
		return $this->Run($response, function () use ($body) {
			$codeA = strval($body['employee_a_code'] ?? '');
			$dateA = strval($body['date_a'] ?? '');
			$codeB = strval($body['employee_b_code'] ?? '');
			$dateB = strval($body['date_b'] ?? '');
			$manager = !empty($body['manager_name']) ? strval($body['manager_name']) : 'Gerente de Operaciones';
			$notes = !empty($body['notes']) ? strval($body['notes']) : '';

			return $this->Eq()->ExecuteShiftSwap($codeA, $dateA, $codeB, $dateB, $manager, $notes);
		});
	}

	public function UpdateShift(Request $request, Response $response, array $args)
	{
		$this->Manage($request);
		$body = $this->Body($request);
		return $this->Run($response, function () use ($body) {
			$code = strval($body['payroll_code'] ?? '');
			$date = strval($body['shift_date'] ?? '');
			$newCode = strval($body['shift_code'] ?? '');
			$manager = !empty($body['manager_name']) ? strval($body['manager_name']) : 'Gerente';

			if (!$code || !$date || !$newCode)
			{
				throw new \Exception('Código de empleado, fecha y código de turno son obligatorios.');
			}
			return $this->Eq()->UpdateIndividualShift($code, $date, $newCode, $manager);
		});
	}

	/* =========================================================================
	 * DAILY PROGRAMACIÓN CONTINUA AUTOMÁTICA 2026 - 2030+
	 * ========================================================================= */

	public function GetMonthlySchedule(Request $request, Response $response, array $args)
	{
		$q = $request->getQueryParams();
		$branchId = isset($q['branch_id']) ? intval($q['branch_id']) : 1;
		$year = isset($q['year']) ? intval($q['year']) : 2026;
		$month = isset($q['month']) ? intval($q['month']) : 11;
		return $this->Run($response, function () use ($branchId, $year, $month) {
			return $this->Auto()->GetMonthSchedule($branchId, $year, $month);
		});
	}

	public function GenerateMonthlySchedule(Request $request, Response $response, array $args)
	{
		$this->Manage($request);
		$body = $this->Body($request);
		$branchId = isset($body['branch_id']) ? intval($body['branch_id']) : 1;
		$year = isset($body['year']) ? intval($body['year']) : 2026;
		$month = isset($body['month']) ? intval($body['month']) : 11;
		$publish = !empty($body['publish']);
		return $this->Run($response, function () use ($branchId, $year, $month, $publish) {
			return $this->Auto()->GenerateMonth($branchId, $year, $month, $publish);
		});
	}

	private function Engine(): \Grocy\Services\WorkforceScheduleEngineService
	{
		return \Grocy\Services\WorkforceScheduleEngineService::GetInstance();
	}

	private function Ym(array $d): array
	{
		return [intval($d['branch_id'] ?? 1), intval($d['year'] ?? date('Y')), intval($d['month'] ?? date('n'))];
	}

	public function EngineDashboard(Request $request, Response $response, array $args)
	{
		$this->Manage($request);
		[$b, $y, $m] = $this->Ym($request->getQueryParams());
		return $this->Run($response, fn() => $this->Engine()->Dashboard($b, $y, $m));
	}

	public function EngineValidate(Request $request, Response $response, array $args)
	{
		$this->Manage($request);
		[$b, $y, $m] = $this->Ym($request->getQueryParams());
		return $this->Run($response, fn() => $this->Engine()->Validate($b, $y, $m));
	}

	public function EngineBuild(Request $request, Response $response, array $args)
	{
		$this->Manage($request);
		[$b, $y, $m] = $this->Ym($this->Body($request));
		return $this->Run($response, fn() => $this->Engine()->BuildDraft($b, $y, $m, GROCY_USER_ID));
	}

	public function EngineDraft(Request $request, Response $response, array $args)
	{
		$this->Manage($request);
		return $this->Run($response, fn() => $this->Engine()->GetDraft(intval($args['id']), true));
	}

	public function EngineBalance(Request $request, Response $response, array $args)
	{
		$this->Manage($request);
		return $this->Run($response, fn() => $this->Engine()->Balance(intval($args['id'])));
	}

	public function EngineReplacement(Request $request, Response $response, array $args)
	{
		$this->Manage($request);
		$q = $request->getQueryParams();
		return $this->Run($response, fn() => $this->Engine()->FindReplacement(intval($args['id']), strval($q['payroll_code'] ?? ''), strval($q['date'] ?? '')));
	}

	public function EngineApplyReplacement(Request $request, Response $response, array $args)
	{
		$this->Manage($request);
		$b = $this->Body($request);
		return $this->Run($response, fn() => $this->Engine()->ApplyReplacement(intval($args['id']), strval($b['from'] ?? ''), strval($b['to'] ?? ''), strval($b['date'] ?? '')));
	}

	public function EngineApprove(Request $request, Response $response, array $args)
	{
		$this->Manage($request);
		$b = $this->Body($request);
		return $this->Run($response, fn() => $this->Engine()->Approve(intval($args['id']), GROCY_USER_ID, !empty($b['accept_coverage_gaps']), !empty($b['accept_confirmations'])));
	}

	public function EngineRecalculate(Request $request, Response $response, array $args)
	{
		$this->Manage($request);
		return $this->Run($response, fn() => $this->Engine()->Recalculate(intval($args['id']), GROCY_USER_ID));
	}

	public function EngineUpdateCell(Request $request, Response $response, array $args)
	{
		$this->Manage($request);
		$b = $this->Body($request);
		return $this->Run($response, fn() => $this->Engine()->UpdateDraftCell(intval($args['id']), strval($b['payroll_code'] ?? ''), strval($b['date'] ?? ''), strval($b['shift_code'] ?? ''), GROCY_USER_ID));
	}

	public function EngineAbsenceEnd(Request $request, Response $response, array $args)
	{
		$this->Manage($request);
		$b = $this->Body($request);
		return $this->Run($response, function () use ($args, $b) {
			$this->Engine()->SetAbsenceEnd(intval($args['id']), strval($b['return_date'] ?? ''));
			return ['success' => true];
		});
	}

	public function EngineDecideAbsence(Request $request, Response $response, array $args)
	{
		$this->Manage($request);
		$b = $this->Body($request);
		return $this->Run($response, fn() => \Grocy\Services\WorkforceScheduleService::GetInstance()->DecideAbsence(intval($args['id']), !empty($b['approve']), GROCY_USER_ID));
	}

	public function EngineExport(Request $request, Response $response, array $args)
	{
		$this->Manage($request);
		[$b, $y, $m] = $this->Ym($request->getQueryParams());
		try
		{
			$content = \Grocy\Services\WorkforceScheduleXlsxService::GetInstance()->Export($b, $y, $m);
		}
		catch (\Throwable $e)
		{
			return $this->GenericErrorResponse($response, $e->getMessage());
		}
		$response->getBody()->write($content);
		return $response->withHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
			->withHeader('Content-Disposition', 'attachment; filename="' . \Grocy\Services\WorkforceScheduleXlsxService::FileName($y, $m) . '"');
	}

	public function EngineControl(Request $request, Response $response, array $args)
	{
		$this->Manage($request);
		[$b, $y, $m] = $this->Ym($request->getQueryParams());
		return $this->Run($response, fn() => \Grocy\Services\WorkforcePlantaService::GetInstance()->Control($b, $y, $m));
	}

	public function EngineSaveRestriction(Request $request, Response $response, array $args)
	{
		$this->Manage($request);
		$d = $this->Body($request);
		return $this->Run($response, fn() => ['id' => \Grocy\Services\WorkforcePlantaService::GetInstance()->SaveRestriction($d, GROCY_USER_ID)]);
	}

	public function EngineDeleteRestriction(Request $request, Response $response, array $args)
	{
		$this->Manage($request);
		return $this->Run($response, function () use ($args) {
			\Grocy\Services\WorkforcePlantaService::GetInstance()->DeactivateRestriction(intval($args['id']));
			return ['success' => true];
		});
	}

	public function EngineSaveConfig(Request $request, Response $response, array $args)
	{
		$this->Manage($request);
		$d = $this->Body($request);
		return $this->Run($response, function () use ($args, $d) {
			\Grocy\Services\WorkforcePlantaService::GetInstance()->SaveConfig(strval($args['code']), $d);
			return ['success' => true];
		});
	}

	public function EngineCreateRequest(Request $request, Response $response, array $args)
	{
		$this->Manage($request);
		$d = $this->Body($request);
		return $this->Run($response, fn() => ['id' => \Grocy\Services\WorkforceRequestService::GetInstance()->Create($d, GROCY_USER_ID)]);
	}

	public function EngineDecideRequest(Request $request, Response $response, array $args)
	{
		$this->Manage($request);
		$d = $this->Body($request);
		return $this->Run($response, fn() => \Grocy\Services\WorkforceRequestService::GetInstance()->Decide(intval($args['id']), !empty($d['approve']), GROCY_USER_ID, $d['notes'] ?? null));
	}

	public function EngineApplyRequest(Request $request, Response $response, array $args)
	{
		$this->Manage($request);
		return $this->Run($response, fn() => \Grocy\Services\WorkforceRequestService::GetInstance()->Apply(intval($args['id']), GROCY_USER_ID));
	}

	public function EngineDiscard(Request $request, Response $response, array $args)
	{
		$this->Manage($request);
		return $this->Run($response, function () use ($args) {
			$this->Engine()->Discard(intval($args['id']), GROCY_USER_ID);
			return ['success' => true];
		});
	}

	public function EngineReopen(Request $request, Response $response, array $args)
	{
		$this->Manage($request);
		$d = $this->Body($request);
		[$b, $y, $m] = $this->Ym($d);
		return $this->Run($response, fn() => $this->Engine()->Reopen($b, $y, $m, strval($d['reason'] ?? ''), GROCY_USER_ID));
	}

	public function PublishMonthlySchedule(Request $request, Response $response, array $args)
	{
		$this->Manage($request);
		$body = $this->Body($request);
		$branchId = isset($body['branch_id']) ? intval($body['branch_id']) : 1;
		$year = isset($body['year']) ? intval($body['year']) : 2026;
		$month = isset($body['month']) ? intval($body['month']) : 11;
		return $this->Run($response, function () use ($branchId, $year, $month) {
			return $this->Auto()->PublishMonth($branchId, $year, $month, GROCY_USER_ID);
		});
	}

	public function UpdateMonthlyShift(Request $request, Response $response, array $args)
	{
		$this->Manage($request);
		$body = $this->Body($request);
		return $this->Run($response, function () use ($body) {
			$code = strval($body['payroll_code'] ?? '');
			$date = strval($body['shift_date'] ?? '');
			$shiftCode = strval($body['shift_code'] ?? '');
			$notes = !empty($body['notes']) ? strval($body['notes']) : null;
			return $this->Auto()->UpdateShift($code, $date, $shiftCode, $notes);
		});
	}

	public function ExportMonthlySchedule(Request $request, Response $response, array $args)
	{
		$q = $request->getQueryParams();
		$branchId = isset($q['branch_id']) ? intval($q['branch_id']) : 1;
		$year = isset($q['year']) ? intval($q['year']) : 2026;
		$month = isset($q['month']) ? intval($q['month']) : 11;
		$csv = $this->Auto()->ExportCsv($branchId, $year, $month);
		$filename = sprintf('DAILY_Programacion_SAP_%04d_%02d.csv', $year, $month);
		$response->getBody()->write($csv);
		return $response
			->withHeader('Content-Type', 'text/csv; charset=utf-8')
			->withHeader('Content-Disposition', 'attachment; filename="' . $filename . '"');
	}

	public function GetSkillsMatrix(Request $request, Response $response, array $args)
	{
		return $this->Run($response, function () {
			return $this->Auto()->GetSkillsMatrix();
		});
	}

	public function UpdateSkillsMatrix(Request $request, Response $response, array $args)
	{
		$this->Manage($request);
		$body = $this->Body($request);
		$code = strval($body['payroll_code'] ?? '');
		return $this->Run($response, function () use ($code, $body) {
			return $this->Auto()->UpdateSkill($code, $body);
		});
	}

	public function GetVacations(Request $request, Response $response, array $args)
	{
		$q = $request->getQueryParams();
		$month = $q['month'] ?? null;
		return $this->Run($response, function () use ($month) {
			return $this->Auto()->GetVacations($month);
		});
	}

	public function SaveVacation(Request $request, Response $response, array $args)
	{
		$this->Manage($request);
		$body = $this->Body($request);
		return $this->Run($response, function () use ($body) {
			return $this->Auto()->RegisterVacation(
				strval($body['payroll_code'] ?? ''),
				strval($body['start_date'] ?? ''),
				strval($body['end_date'] ?? ''),
				!empty($body['notes']) ? strval($body['notes']) : null
			);
		});
	}

	public function GetHolidays(Request $request, Response $response, array $args)
	{
		$q = $request->getQueryParams();
		$year = isset($q['year']) ? intval($q['year']) : 2026;
		return $this->Run($response, function () use ($year) {
			return $this->Auto()->GetColombianHolidays($year);
		});
	}

	public function GetCompanyPolicies(Request $request, Response $response, array $args)
	{
		$q = $request->getQueryParams();
		$companyId = isset($q['company_id']) ? intval($q['company_id']) : 1;
		return $this->Run($response, function () use ($companyId) {
			return [
				'active_policy' => $this->Auto()->GetCompanyPolicy($companyId),
				'history' => $this->Auto()->GetAllCompanyPolicies($companyId)
			];
		});
	}

	public function SaveCompanyPolicy(Request $request, Response $response, array $args)
	{
		$this->Manage($request);
		$body = $this->Body($request);
		$companyId = isset($body['company_id']) ? intval($body['company_id']) : 1;
		return $this->Run($response, function () use ($companyId, $body) {
			return $this->Auto()->SaveCompanyPolicy($companyId, $body, GROCY_USER_ID);
		});
	}

	public function RegisterCollaborator(Request $request, Response $response, array $args)
	{
		$this->Manage($request);
		$body = $this->Body($request);
		return $this->Run($response, function () use ($body) {
			return $this->Auto()->RegisterNewCollaborator($body, GROCY_USER_ID);
		});
	}

	/* =========================================================================
	   DEMO · Tienda Integral — Escenario de Demostración Aislado (25 Personas)
	   ========================================================================= */

	public function RestoreDemoScenario(Request $request, Response $response, array $args)
	{
		$this->Manage($request);
		return $this->Run($response, function () {
			return \Grocy\Services\DemoSchedulingScenarioService::GetInstance()->ResetDemoScenario();
		});
	}

	public function GetDemoCoverageMatrix(Request $request, Response $response, array $args)
	{
		$q = $request->getQueryParams();
		$demoSvc = \Grocy\Services\DemoSchedulingScenarioService::GetInstance();
		$branchId = !empty($q['branch_id']) ? intval($q['branch_id']) : \Grocy\Services\AttendanceService::GetInstance()->ResolveBranchId(null);
		$date = !empty($q['date']) ? strval($q['date']) : date('Y-m-d');
		$dept = !empty($q['department']) ? strval($q['department']) : 'all';
		$scenario = !empty($q['scenario']) ? strval($q['scenario']) : 'normal';

		return $this->Run($response, function () use ($demoSvc, $branchId, $date, $dept, $scenario) {
			return $demoSvc->GetCoverageMatrix30Min($branchId, $date, $dept, $scenario);
		});
	}

	public function GetDemoValidationScenarios(Request $request, Response $response, array $args)
	{
		return $this->Run($response, function () {
			return \Grocy\Services\DemoSchedulingScenarioService::GetInstance()->GetValidationScenarios();
		});
	}

	public function ApplyDemoValidationTestCase(Request $request, Response $response, array $args)
	{
		$this->Manage($request);
		$body = $this->Body($request);
		$caseKey = strval($body['case_id'] ?? 'caso_1_dia_habitual');

		return $this->Run($response, function () use ($caseKey) {
			$demoSvc = \Grocy\Services\DemoSchedulingScenarioService::GetInstance();
			$scenarios = $demoSvc->GetValidationScenarios();
			if (!isset($scenarios[$caseKey]))
			{
				throw new \Exception("Caso de prueba $caseKey no encontrado");
			}
			$sc = $scenarios[$caseKey];
			return [
				'success' => true,
				'case' => $sc,
				'message' => "Caso de validación activado: {$sc['title']}"
			];
		});
	}
}


