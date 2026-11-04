<?php

namespace Grocy\Controllers\Api;

use Grocy\Controllers\Users\PermissionMissingException;
use Grocy\Controllers\Users\User;
use Grocy\Services\InventoryMovementService;
use Grocy\Services\KpiCatalogService;
use Grocy\Services\MasterDataService;
use Grocy\Services\WorkflowService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

// Fase 1 — servicios compartidos: flujos operativos, catálogo de KPI, motor de movimientos y datos maestros
class CoreArchitectureApiController extends BaseApiController
{
	const WORKFLOW_PERMS = [User::PERMISSION_TASKS, User::PERMISSION_ALERTS_SUPERVISE, User::PERMISSION_KPIS_VIEW, User::PERMISSION_PROCUREMENT_MANAGE, User::PERMISSION_STOCK];

	private function Run(Response $response, callable $fn)
	{
		try
		{
			return $this->ApiResponse($response, $fn());
		}
		catch (PermissionMissingException $ex)
		{
			throw $ex;
		}
		catch (\Throwable $ex)
		{
			return $this->GenericErrorResponse($response, $ex->getMessage());
		}
	}

	private static function Body(Request $request): array
	{
		return $request->getParsedBody() ?? [];
	}

	private static function Key(Request $request, array $b): ?string
	{
		$k = trim((string) ($b['request_key'] ?? $request->getHeaderLine('X-Request-Key')));
		return $k !== '' ? substr($k, 0, 100) : null;
	}

	// ── Flujos ──
	public function WorkflowDefinitions(Request $request, Response $response, array $args)
	{
		$this->DairyRead($request, self::WORKFLOW_PERMS, null, false);
		return $this->Run($response, fn() => WorkflowService::GetInstance()->Definitions(empty($request->getQueryParams()['all'])));
	}

	public function SaveWorkflowDefinition(Request $request, Response $response, array $args)
	{
		User::CheckPermission($request, User::PERMISSION_ADMIN);
		return $this->Run($response, fn() => WorkflowService::GetInstance()->SaveDefinition(self::Body($request)));
	}

	public function Workflows(Request $request, Response $response, array $args)
	{
		$this->DairyRead($request, self::WORKFLOW_PERMS, self::QueryBranch($request), false);
		return $this->Run($response, fn() => WorkflowService::GetInstance()->List($request->getQueryParams(), intval($request->getQueryParams()['limit'] ?? 200)));
	}

	public function WorkflowStats(Request $request, Response $response, array $args)
	{
		$b = $this->DairyRead($request, self::WORKFLOW_PERMS, self::QueryBranch($request), false);
		return $this->Run($response, fn() => WorkflowService::GetInstance()->Stats($b));
	}

	public function Workflow(Request $request, Response $response, array $args)
	{
		$this->DairyRead($request, self::WORKFLOW_PERMS, null, false);
		return $this->Run($response, fn() => WorkflowService::GetInstance()->Get(intval($args['id'])));
	}

	public function StartWorkflow(Request $request, Response $response, array $args)
	{
		$this->DairyRead($request, self::WORKFLOW_PERMS, null, false);
		$b = self::Body($request);
		return $this->Run($response, fn() => WorkflowService::GetInstance()->Start((string) ($b['definition_code'] ?? ''), (string) ($b['source_type'] ?? ''), (string) ($b['source_id'] ?? ''), $b));
	}

	// action: complete | skip | approve | reject | assign | evidence | comment | close | cancel
	public function WorkflowAction(Request $request, Response $response, array $args)
	{
		$this->DairyRead($request, self::WORKFLOW_PERMS, null, false);
		$b = self::Body($request);
		$id = intval($args['id']);
		$key = self::Key($request, $b);
		$svc = WorkflowService::GetInstance();
		return $this->Run($response, fn() => match ($args['action']) {
			'complete' => $svc->CompleteStep($id, $b['step_code'] ?? null, $b['notes'] ?? null, $key),
			'skip' => $svc->SkipStep($id, (string) ($b['step_code'] ?? ''), (string) ($b['reason'] ?? ''), $key),
			'approve' => $svc->Approve($id, $b['notes'] ?? null, $key),
			'reject' => $svc->Reject($id, (string) ($b['notes'] ?? ''), $key),
			'assign' => $svc->Assign($id, isset($b['owner_user_id']) && $b['owner_user_id'] !== '' ? intval($b['owner_user_id']) : null, $key),
			'evidence' => $svc->AddEvidence($id, (string) ($b['kind'] ?? ''), (string) ($b['reference'] ?? ''), $b['description'] ?? null, $b['step_code'] ?? null, $key),
			'comment' => $svc->Comment($id, (string) ($b['text'] ?? ''), $key),
			'close' => $svc->Close($id, $b['result'] ?? null, $b['notes'] ?? null, $key),
			'cancel' => $svc->Cancel($id, (string) ($b['reason'] ?? ''), $key),
			default => throw new \Exception('Acción desconocida: ' . $args['action']),
		});
	}

	// ── KPI ──
	public function KpiCatalog(Request $request, Response $response, array $args)
	{
		$this->DairyRead($request, [User::PERMISSION_KPIS_VIEW], null, false);
		return $this->Run($response, fn() => KpiCatalogService::GetInstance()->Catalog());
	}

	public function KpiCompute(Request $request, Response $response, array $args)
	{
		$q = $request->getQueryParams();
		$b = $this->DairyRead($request, [User::PERMISSION_KPIS_VIEW], self::QueryBranch($request), false);
		$codes = array_values(array_filter(explode(',', (string) ($q['codes'] ?? ''))));
		return $this->Run($response, fn() => KpiCatalogService::GetInstance()->Compute($codes, $b, $q['start'] ?? date('Y-m-01'), $q['end'] ?? date('Y-m-d')));
	}

	public function SaveKpiSettings(Request $request, Response $response, array $args)
	{
		User::CheckPermission($request, User::PERMISSION_ADMIN);
		return $this->Run($response, fn() => KpiCatalogService::GetInstance()->SaveSettings($args['code'], self::Body($request)));
	}

	// ── Movimientos ──
	public function MovementTypes(Request $request, Response $response, array $args)
	{
		$this->DairyRead($request, [User::PERMISSION_STOCK], null, false);
		return $this->Run($response, fn() => InventoryMovementService::GetInstance()->Types());
	}

	public function MovementLedger(Request $request, Response $response, array $args)
	{
		$this->DairyRead($request, [User::PERMISSION_STOCK], self::QueryBranch($request), false);
		$q = $request->getQueryParams();
		return $this->Run($response, fn() => InventoryMovementService::GetInstance()->Ledger($q, intval($q['limit'] ?? 500)));
	}

	public function MovementPosition(Request $request, Response $response, array $args)
	{
		$b = $this->DairyRead($request, [User::PERMISSION_STOCK], self::QueryBranch($request), true);
		return $this->Run($response, function () use ($args, $b) {
			if ($b === null)
			{
				throw new \Exception('Indique branch_id');
			}
			return InventoryMovementService::GetInstance()->Position(intval($args['productId']), $b);
		});
	}

	public function MovementDuplicates(Request $request, Response $response, array $args)
	{
		$this->DairyRead($request, [User::PERMISSION_STOCK], null, false);
		$q = $request->getQueryParams();
		return $this->Run($response, fn() => InventoryMovementService::GetInstance()->DuplicateCheck(max(1, min(90, intval($q['days'] ?? 7))), max(1, min(60, intval($q['seconds'] ?? 5)))));
	}

	// ── Datos maestros ──
	public function MasterRegistry(Request $request, Response $response, array $args)
	{
		$this->DairyRead($request, [User::PERMISSION_DATA_IMPORT], null, false);
		return $this->Run($response, fn() => MasterDataService::GetInstance()->Registry());
	}

	public function MasterIntegrity(Request $request, Response $response, array $args)
	{
		$this->DairyRead($request, [User::PERMISSION_DATA_IMPORT], null, false);
		return $this->Run($response, fn() => MasterDataService::GetInstance()->Integrity());
	}
}
