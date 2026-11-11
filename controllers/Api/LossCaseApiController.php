<?php

namespace Grocy\Controllers\Api;

use Grocy\Services\LossReconciliationService;
use Grocy\Services\MerchandiseService;
use Grocy\Services\ReconciliationService;
use Grocy\Services\SensitiveCaseService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/** Prevención de pérdidas. Los permisos y la sucursal se validan en SensitiveCaseService. */
class LossCaseApiController extends BaseApiController
{
	private function Svc(): SensitiveCaseService
	{
		return SensitiveCaseService::GetInstance();
	}

	private function GetParsedBody(Request $request): array
	{
		return (array) ($request->getParsedBody() ?? []);
	}

	private function Run(Response $response, callable $fn): Response
	{
		try
		{
			return $this->ApiResponse($response, $fn());
		}
		catch (\Exception $e)
		{
			return $this->GenericErrorResponse($response, $e->getMessage(), in_array($e->getCode(), [403, 404], true) ? $e->getCode() : 400);
		}
	}

	private function Branch(Request $request): ?int
	{
		$b = intval($request->getQueryParams()['branch_id'] ?? 0);
		return $b > 0 ? $b : null;
	}

	public function Dashboard(Request $request, Response $response, array $args)
	{
		$q = $request->getQueryParams();
		$branchId = $this->Branch($request);
		if ($branchId)
		{
			$q['branch_id'] = $branchId;
		}
		return $this->Run($response, fn() => $this->Svc()->Dashboard($q, $q['unit'] ?? 'day'));
	}

	public function Differences(Request $request, Response $response, array $args)
	{
		$q = $request->getQueryParams();
		return $this->Run($response, fn() => LossReconciliationService::GetInstance()->DifferencesBy($q['by'] ?? 'branch', $this->Branch($request), $q['from'] ?? null, $q['to'] ?? null));
	}

	public function Tasks(Request $request, Response $response, array $args)
	{
		return $this->Run($response, fn() => $this->Svc()->Tasks($this->Branch($request), GROCY_USER_ID));
	}

	public function SubmitCount(Request $request, Response $response, array $args)
	{
		return $this->Run($response, fn() => $this->Svc()->SubmitTaskCount(intval($args['id']), $this->GetParsedBody($request), GROCY_USER_ID));
	}

	public function SavePlan(Request $request, Response $response, array $args)
	{
		return $this->Run($response, fn() => ['id' => $this->Svc()->SavePlan($this->GetParsedBody($request), GROCY_USER_ID)]);
	}

	public function SetPlanActive(Request $request, Response $response, array $args)
	{
		return $this->Run($response, function () use ($request, $args) {
			$this->Svc()->SetPlanActive(intval($args['id']), !empty($this->GetParsedBody($request)['active']));
			return ['success' => true];
		});
	}

	public function SaveCategory(Request $request, Response $response, array $args)
	{
		return $this->Run($response, fn() => ['id' => $this->Svc()->SaveCategory($this->GetParsedBody($request))]);
	}

	public function Classify(Request $request, Response $response, array $args)
	{
		return $this->Run($response, function () use ($request, $args) {
			$c = intval($this->GetParsedBody($request)['category_id'] ?? 0);
			$this->Svc()->ClassifyProduct(intval($args['id']), $c > 0 ? $c : null);
			return ['success' => true];
		});
	}

	public function GetCase(Request $request, Response $response, array $args)
	{
		return $this->Run($response, fn() => $this->Svc()->Detail(intval($args['id'])));
	}

	public function RequestRecount(Request $request, Response $response, array $args)
	{
		return $this->Run($response, fn() => $this->Svc()->RequestRecount(intval($args['id']), trim($this->GetParsedBody($request)['note'] ?? ''), GROCY_USER_ID));
	}

	public function Assign(Request $request, Response $response, array $args)
	{
		$b = $this->GetParsedBody($request);
		return $this->Run($response, fn() => $this->Svc()->Assign(intval($args['id']), intval($b['user_id'] ?? 0), ($b['due_at'] ?? '') ?: null, GROCY_USER_ID));
	}

	public function Propose(Request $request, Response $response, array $args)
	{
		return $this->Run($response, fn() => $this->Svc()->ProposeAdjustment(intval($args['id']), trim($this->GetParsedBody($request)['reason'] ?? ''), GROCY_USER_ID));
	}

	public function Decide(Request $request, Response $response, array $args)
	{
		$b = $this->GetParsedBody($request);
		return $this->Run($response, fn() => ReconciliationService::GetInstance()->AuthorizeAdjustment(intval($args['id']), ($b['decision'] ?? '') === 'approved' ? 'approved' : 'rejected', trim($b['reason'] ?? ''), GROCY_USER_ID));
	}

	public function CloseCase(Request $request, Response $response, array $args)
	{
		$b = $this->GetParsedBody($request);
		$outcome = ($b['outcome'] ?? '') === 'resuelto' ? 'resuelto' : 'no_resuelto';
		$justification = trim($b['justification'] ?? ($b['reason'] ?? ''));
		return $this->Run($response, fn() => $this->Svc()->CloseCase(intval($args['id']), $outcome, $justification, GROCY_USER_ID));
	}

	public function CreateIncident(Request $request, Response $response, array $args)
	{
		$b = $this->GetParsedBody($request);
		return $this->Run($response, fn() => $this->Svc()->CreateManualIncident($b, GROCY_USER_ID));
	}

	public function ConfirmCause(Request $request, Response $response, array $args)
	{
		$b = $this->GetParsedBody($request);
		return $this->Run($response, fn() => $this->Svc()->ConfirmCause(intval($args['id']), trim((string)($b['cause'] ?? ($b['code'] ?? ''))), trim((string)($b['note'] ?? '')), GROCY_USER_ID));
	}

	public function Reopen(Request $request, Response $response, array $args)
	{
		$b = $this->GetParsedBody($request);
		return $this->Run($response, fn() => $this->Svc()->Reopen(intval($args['id']), $b['note'] ?? '', GROCY_USER_ID));
	}

	public function AddEvidence(Request $request, Response $response, array $args)
	{
		$b = $this->GetParsedBody($request);
		return $this->Run($response, fn() => $this->Svc()->AddEvidence(intval($args['id']), $b, GROCY_USER_ID));
	}

	public function ServeEvidence(Request $request, Response $response, array $args)
	{
		return $this->Run($response, function () use ($args) {
			$ev = $this->Svc()->EvidenceFile(intval($args['id']));
			if (!$ev)
			{
				throw new \Exception('Evidencia no encontrada', 404);
			}
			return $ev;
		});
	}

	public function Hypothesis(Request $request, Response $response, array $args)
	{
		$b = $this->GetParsedBody($request);
		return $this->Run($response, fn() => $this->Svc()->DecideHypothesis(intval($args['id']), $b['decision'] ?? 'descartada', $b['note'] ?? '', GROCY_USER_ID));
	}

	public function LogCall(Request $request, Response $response, array $args)
	{
		return $this->Run($response, fn() => $this->Svc()->LogCall(intval($args['id']), $this->GetParsedBody($request), GROCY_USER_ID));
	}

	public function GetCalls(Request $request, Response $response, array $args)
	{
		return $this->Run($response, fn() => $this->Svc()->GetCalls(intval($args['id'])));
	}

	public function GetThread(Request $request, Response $response, array $args)
	{
		return $this->Run($response, fn() => $this->Svc()->GetOrCreateCaseThread(intval($args['id']), GROCY_USER_ID));
	}

	public function SendMessage(Request $request, Response $response, array $args)
	{
		$b = $this->GetParsedBody($request);
		return $this->Run($response, fn() => $this->Svc()->SendCaseMessage(intval($args['id']), GROCY_USER_ID, $b['body'] ?? '', !empty($b['is_priority']), $b['attachment_url'] ?? null, $b['attachment_name'] ?? null));
	}

	public function Escalate(Request $request, Response $response, array $args)
	{
		$b = $this->GetParsedBody($request);
		return $this->Run($response, fn() => $this->Svc()->EscalateCase(intval($args['id']), $b['reason'] ?? null, GROCY_USER_ID));
	}

	public function Checkin(Request $request, Response $response, array $args)
	{
		$b = $this->GetParsedBody($request);
		return $this->Run($response, fn() => $this->Svc()->RegisterArrival(intval($args['id']), !empty($b['location_id']) ? intval($b['location_id']) : null, $b['zone_name'] ?? null, $b['notes'] ?? null, GROCY_USER_ID));
	}

	public function ScanVerify(Request $request, Response $response, array $args)
	{
		$b = $this->GetParsedBody($request);
		return $this->Run($response, fn() => $this->Svc()->ScanVerify(intval($args['id']), $b['query'] ?? '', isset($b['counted_qty']) ? floatval($b['counted_qty']) : null, (array)($b['scanned_serials'] ?? []), $b['lot'] ?? null, $b['expiry'] ?? null, isset($b['weight']) ? floatval($b['weight']) : null, GROCY_USER_ID));
	}

	public function AddTask(Request $request, Response $response, array $args)
	{
		return $this->Run($response, fn() => $this->Svc()->AddTask(intval($args['id']), $this->GetParsedBody($request), GROCY_USER_ID));
	}

	public function UpdateTaskStatus(Request $request, Response $response, array $args)
	{
		$b = $this->GetParsedBody($request);
		return $this->Run($response, fn() => $this->Svc()->UpdateTaskStatus(intval($args['id']), $b['status'] ?? 'completed', $b['result_notes'] ?? null, GROCY_USER_ID));
	}

	public function GetTasks(Request $request, Response $response, array $args)
	{
		return $this->Run($response, fn() => $this->Svc()->GetTasks(intval($args['id'])));
	}

	public function AddAction(Request $request, Response $response, array $args)
	{
		return $this->Run($response, fn() => $this->Svc()->AddAction(intval($args['id']), $this->GetParsedBody($request), GROCY_USER_ID));
	}

	public function DecideAction(Request $request, Response $response, array $args)
	{
		$b = $this->GetParsedBody($request);
		return $this->Run($response, fn() => $this->Svc()->DecideAction(intval($args['id']), $b['decision'] ?? 'approved', $b['notes'] ?? null, GROCY_USER_ID));
	}

	public function GetActions(Request $request, Response $response, array $args)
	{
		return $this->Run($response, fn() => $this->Svc()->GetActions(intval($args['id'])));
	}

	public function CreatePreventivePlan(Request $request, Response $response, array $args)
	{
		return $this->Run($response, fn() => $this->Svc()->CreatePreventivePlan(intval($args['id']), $this->GetParsedBody($request), GROCY_USER_ID));
	}

	public function GetPreventivePlans(Request $request, Response $response, array $args)
	{
		return $this->Run($response, fn() => $this->Svc()->GetPreventivePlans($this->Branch($request)));
	}

	public function GetStaff(Request $request, Response $response, array $args)
	{
		return $this->Run($response, fn() => $this->Svc()->StaffDirectory($this->Branch($request), $request->getQueryParams()['department'] ?? null));
	}

	public function GetMap(Request $request, Response $response, array $args)
	{
		return $this->Run($response, fn() => $this->Svc()->BranchOperationalMap($this->Branch($request)));
	}

	public function AddHandover(Request $request, Response $response, array $args)
	{
		$b = $this->GetParsedBody($request);
		return $this->Run($response, fn() => $this->Svc()->AddHandoverNote(intval($args['id']), $b['note'] ?? '', GROCY_USER_ID));
	}

	public function GetProductivity(Request $request, Response $response, array $args)
	{
		$q = $request->getQueryParams();
		return $this->Run($response, fn() => $this->Svc()->ProductivityMetrics($this->Branch($request), $q['from'] ?? null, $q['to'] ?? null));
	}

	// === MÉTODOS DEL MÓDULO CONTROL DE MERCANCÍAS ===
	public function Book(Request $request, Response $response, array $args)
	{
		return $this->Run($response, fn() => MerchandiseService::GetInstance()->Book($request->getQueryParams()));
	}

	public function RecordMovement(Request $request, Response $response, array $args)
	{
		return $this->Run($response, fn() => MerchandiseService::GetInstance()->RecordMovement($this->GetParsedBody($request), GROCY_USER_ID));
	}

	public function SearchTrace(Request $request, Response $response, array $args)
	{
		return $this->Run($response, fn() => MerchandiseService::GetInstance()->SearchTrace($request->getQueryParams()['query'] ?? ''));
	}

	public function TraceJourney(Request $request, Response $response, array $args)
	{
		return $this->Run($response, fn() => MerchandiseService::GetInstance()->TraceJourney(intval($args['id'])));
	}

	public function MerchandiseTab(Request $request, Response $response, array $args)
	{
		return $this->Run($response, fn() => MerchandiseService::GetInstance()->Tab(intval($args['tab']), $request->getQueryParams()));
	}

	public function RecordTransfer(Request $request, Response $response, array $args)
	{
		return $this->Run($response, fn() => MerchandiseService::GetInstance()->RecordTransfer($this->GetParsedBody($request), GROCY_USER_ID));
	}

	public function ReceiveTransfer(Request $request, Response $response, array $args)
	{
		return $this->Run($response, fn() => MerchandiseService::GetInstance()->ReceiveTransfer(intval($args['id']), $this->GetParsedBody($request), GROCY_USER_ID));
	}

	public function RequestAdjustment(Request $request, Response $response, array $args)
	{
		return $this->Run($response, fn() => MerchandiseService::GetInstance()->RequestAdjustment($this->GetParsedBody($request), GROCY_USER_ID));
	}

	public function DecideAdjustment(Request $request, Response $response, array $args)
	{
		return $this->Run($response, fn() => MerchandiseService::GetInstance()->DecideAdjustment(intval($args['id']), $this->GetParsedBody($request), GROCY_USER_ID));
	}

	public function DifferenceAction(Request $request, Response $response, array $args)
	{
		return $this->Run($response, fn() => MerchandiseService::GetInstance()->DifferenceAction(intval($args['id']), $this->GetParsedBody($request), GROCY_USER_ID));
	}

	public function ScanBarcode(Request $request, Response $response, array $args)
	{
		return $this->Run($response, fn() => MerchandiseService::GetInstance()->ScanBarcode($args['barcode'], $this->Branch($request)));
	}

	// === FASES 4 A 16: MÉTODOS DEL FLUJO GUIADO DE 15 PASOS Y EXPEDIENTE CENTRAL ===
	public function SaveWizardStep(Request $request, Response $response, array $args)
	{
		$b = $this->GetParsedBody($request);
		$step = intval($b['step'] ?? 1);
		$stepData = (array)($b['data'] ?? []);
		$notes = $b['notes'] ?? null;
		return $this->Run($response, fn() => $this->Svc()->SaveWizardStep(intval($args['id']), $step, $stepData, $notes, GROCY_USER_ID));
	}

	public function GetWizardState(Request $request, Response $response, array $args)
	{
		return $this->Run($response, fn() => $this->Svc()->GetWizardState(intval($args['id'])));
	}

	public function SaveRootCause(Request $request, Response $response, array $args)
	{
		$b = $this->GetParsedBody($request);
		$status = $b['status'] ?? 'sospechada';
		$cause = $b['confirmed_cause'] ?? null;
		$fiveWhys = (array)($b['five_whys'] ?? []);
		return $this->Run($response, fn() => $this->Svc()->SaveRootCause(intval($args['id']), $status, $cause, $fiveWhys, GROCY_USER_ID));
	}

	public function SaveFinancialRecovery(Request $request, Response $response, array $args)
	{
		return $this->Run($response, fn() => $this->Svc()->SaveFinancialRecovery(intval($args['id']), $this->GetParsedBody($request), GROCY_USER_ID));
	}

	public function RecordCctvAccess(Request $request, Response $response, array $args)
	{
		return $this->Run($response, fn() => $this->Svc()->RecordCctvAccess($this->GetParsedBody($request), GROCY_USER_ID));
	}

	public function CctvAccessLogs(Request $request, Response $response, array $args)
	{
		return $this->Run($response, fn() => $this->Svc()->CctvAccessLogs($this->Branch($request)));
	}

	public function PosAnomalies(Request $request, Response $response, array $args)
	{
		$q = $request->getQueryParams();
		return $this->Run($response, fn() => $this->Svc()->PosAnomaliesAndPatterns($this->Branch($request), $q['from'] ?? null, $q['to'] ?? null));
	}
}
