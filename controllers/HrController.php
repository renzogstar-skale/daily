<?php

namespace Grocy\Controllers;

use Grocy\Helpers\DairyAccess;
use Grocy\Services\BranchService;
use Grocy\Services\HrService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class HrController extends BaseController
{
	public function Dashboard(Request $request, Response $response, array $args)
	{
		$hrService = HrService::GetInstance();
		$branchService = BranchService::GetInstance();

		$branches = DairyAccess::FilterBranches($branchService->GetBranches());
		$q = $request->getQueryParams();

		$firstBranchId = !empty($branches) ? intval(is_object($branches[0]) ? $branches[0]->id : $branches[0]['id']) : 1;
		$selectedBranchId = isset($q['branch_id']) && intval($q['branch_id']) > 0
			? intval($q['branch_id'])
			: $firstBranchId;

		$selectedPeriod = !empty($q['period']) ? $q['period'] : 'current_month';
		$activeTab = !empty($q['tab']) ? $q['tab'] : 'resumen';

		// Cargar datos consolidados
		$dashboardData = $hrService->GetDashboardData($selectedBranchId, $selectedPeriod);
		$collaborators = $hrService->GetCollaborators($selectedBranchId, $q);
		$hiring = $hrService->GetHiringPipeline($selectedBranchId);
		$schedules = $hrService->GetSchedulesSummary($selectedBranchId, $q['week'] ?? '2026-11-02');
		$attendance = $hrService->GetAttendanceSummary($selectedBranchId, $q['date'] ?? '2026-11-04');
		$payroll = $hrService->GetPayrollSummary($selectedBranchId, $q['payroll_period'] ?? '2026-10');
		$productivity = $hrService->GetProductivityData($selectedBranchId);
		$training = $hrService->GetTrainingData($selectedBranchId);
		$communications = $hrService->GetCommunicationsData($selectedBranchId);

		return $this->RenderPage($response, 'hr_dashboard', [
			'branches' => $branches,
			'selectedBranchId' => $selectedBranchId,
			'selectedPeriod' => $selectedPeriod,
			'activeTab' => $activeTab,
			'dashboard' => $dashboardData,
			'collaborators' => $collaborators,
			'hiring' => $hiring,
			'schedules' => $schedules,
			'attendance' => $attendance,
			'payroll' => $payroll,
			'productivity' => $productivity,
			'training' => $training,
			'communications' => $communications,
		]);
	}

	public function ExportPayrollCsv(Request $request, Response $response, array $args)
	{
		$hrService = HrService::GetInstance();
		$q = $request->getQueryParams();
		$branchId = isset($q['branch_id']) ? intval($q['branch_id']) : 1;
		$period = !empty($q['period']) ? $q['period'] : '2026-10';

		$csv = $hrService->GeneratePayrollCsv($branchId, $period);

		$filename = "nomina_daily_sucursal_{$branchId}_{$period}.csv";
		$response->getBody()->write($csv);

		return $response
			->withHeader('Content-Type', 'text/csv; charset=UTF-8')
			->withHeader('Content-Disposition', "attachment; filename=\"{$filename}\"");
	}
}
