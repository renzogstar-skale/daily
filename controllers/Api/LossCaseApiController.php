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
		return $this->Run($response, function () use ($b) {
			$productId = intval($b['product_id'] ?? ($b['product'] ?? 0));
			$branchId = intval($b['branch_id'] ?? ($b['branch'] ?? 1));
			$qty = floatval($b['difference_qty'] ?? ($b['qty'] ?? 1));
			$priority = $b['priority'] ?? 'medium';
			$notes = trim($b['notes'] ?? ($b['note'] ?? 'Incidencia manual registrada en Centro de Control'));
			$zone = trim($b['zone'] ?? 'Bodega Principal');

			$db = \Grocy\Services\DatabaseService::GetInstance()->GetDbConnectionRaw();
			$prod = $db->query("SELECT * FROM products WHERE id = " . intval($productId))->fetch(\PDO::FETCH_ASSOC);
			if (!$prod)
			{
				throw new \Exception('Producto no encontrado');
			}

			$unitCost = floatval($prod['sale_price'] ?? 100.0) * 0.7;
			$diff = -abs($qty);
			$impact = round(abs($diff) * $unitCost, 2);
			$now = date('Y-m-d H:i:s');
			$nextNum = intval($db->query('SELECT IFNULL(MAX(id), 0) + 1 FROM sensitive_investigation_cases')->fetchColumn());
			$caseCode = 'INV-' . date('Y') . '-' . sprintf('%05d', $nextNum);

			$stmt = $db->prepare("
				INSERT INTO sensitive_investigation_cases (
					case_code, branch_id, product_id, department, location_name,
					expected_stock, physical_count, difference_qty, unit_cost, financial_impact,
					stage, workflow_status, priority, zone, shift, suspected_cause_category,
					evidence_notes, created_by_user_id, created_at, updated_at
				) VALUES (
					:code, :bid, :pid, :dept, :loc,
					:exp, :phys, :diff, :cost, :impact,
					'preliminar', 'en_investigacion', :prio, :zone, 'Turno General', 'indeterminada',
					:notes, :uid, :now, :now
				)
			");
			$stmt->execute([
				':code' => $caseCode,
				':bid' => $branchId,
				':pid' => $productId,
				':dept' => $prod['department'] ?: 'General',
				':loc' => $zone,
				':exp' => 10.0,
				':phys' => 10.0 - abs($diff),
				':diff' => $diff,
				':cost' => $unitCost,
				':impact' => $impact,
				':prio' => $priority,
				':zone' => $zone,
				':notes' => $notes,
				':uid' => GROCY_USER_ID,
				':now' => $now
			]);
			$newId = intval($db->lastInsertId());

			$db->prepare("
				INSERT INTO sensitive_case_events (case_id, action, from_status, to_status, note, user_id, created_at)
				VALUES (:cid, 'case_created', 'detectado', 'en_investigacion', :note, :uid, :now)
			")->execute([
				':cid' => $newId,
				':note' => "Expediente creado manualmente: $notes",
				':uid' => GROCY_USER_ID,
				':now' => $now
			]);

			return [
				'id' => $newId,
				'case_code' => $caseCode,
				'financial_impact' => $impact,
				'difference_qty' => $diff
			];
		});
	}

	public function ConfirmCause(Request $request, Response $response, array $args)
	{
		$b = $this->GetParsedBody($request);
		return $this->Run($response, fn() => $this->Svc()->ConfirmCause(intval($args['id']), $b['cause'] ?? '', GROCY_USER_ID));
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
		return $this->Run($response, fn() => $this->Svc()->GetEvidence(intval($args['id'])));
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
}
