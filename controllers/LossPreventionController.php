<?php

namespace Grocy\Controllers;

use Grocy\Controllers\Api\AlertCenterApiController;
use Grocy\Controllers\Users\User;
use Grocy\Helpers\DairyAccess;
use Grocy\Services\AlertCenterService;
use Grocy\Services\DatabaseService;
use Grocy\Services\InventoryControlService;
use Grocy\Services\LossPreventionService;
use Grocy\Services\RecallService;
use Grocy\Services\StoreAuditService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class LossPreventionController extends BaseController
{
	private function Raw(): \PDO
	{
		return DatabaseService::GetInstance()->GetDbConnectionRaw();
	}

	private function Branches(): array
	{
		return DairyAccess::FilterBranches($this->Raw()->query('SELECT id, name FROM branches WHERE active = 1 ORDER BY id')->fetchAll(\PDO::FETCH_ASSOC));
	}

	private function BranchParam(Request $request, bool $required): ?int
	{
		$q = $request->getQueryParams();
		if (!empty($q['branch_id']))
		{
			return intval($q['branch_id']);
		}
		$allowed = DairyAccess::AllowedBranches();
		if ($allowed !== null)
		{
			return $allowed[0];
		}
		return $required ? intval($this->Branches()[0]['id'] ?? 1) : null;
	}

	private function Users(): array
	{
		return $this->Raw()->query("SELECT id, IFNULL(NULLIF(TRIM(IFNULL(first_name, '') || ' ' || IFNULL(last_name, '')), ''), username) AS name FROM users ORDER BY name")->fetchAll(\PDO::FETCH_ASSOC);
	}

	public function InventoryControl(Request $request, Response $response, array $args)
	{
		$branchId = $this->BranchParam($request, true);
		$queryParams = $request->getQueryParams();
		$icService = InventoryControlService::GetInstance();
		$controlData = $icService->GetControlData($branchId, $queryParams);

		return $this->RenderPage($response, 'inventory_control', array_merge($controlData, [
			'branchId' => $branchId,
			'branches' => $this->Branches(),
			'canCount' => DairyAccess::HasAny([User::PERMISSION_STOCK_INVENTORY]),
			'canAdjust' => DairyAccess::HasAny([User::PERMISSION_STOCK_EDIT, User::PERMISSION_WASTE_APPROVE]),
		]));
	}

	public function Recalls(Request $request, Response $response, array $args)
	{
		$svc = RecallService::GetInstance();
		$q = $request->getQueryParams();
		$selected = null;
		if (!empty($q['id']))
		{
			try
			{
				$selected = $svc->GetRecall(intval($q['id']));
			}
			catch (\Exception $e)
			{
				$selected = null;
			}
		}
		return $this->RenderPage($response, 'recalls', [
			'recalls' => $svc->GetRecalls(DairyAccess::AllowedBranches()),
			'selected' => $selected,
			'products' => $this->Raw()->query('SELECT id, name FROM products WHERE active = 1 ORDER BY name')->fetchAll(\PDO::FETCH_ASSOC),
			'suppliers' => $this->Raw()->query('SELECT id, name FROM shopping_locations WHERE active = 1 ORDER BY name')->fetchAll(\PDO::FETCH_ASSOC),
			'branches' => $this->Branches(),
			'canManage' => DairyAccess::HasAny([User::PERMISSION_RECALL_MANAGE]),
			'severities' => RecallService::SEVERITY,
			'dispositions' => RecallService::DISPOSITION,
		]);
	}

	public function Audits(Request $request, Response $response, array $args)
	{
		$svc = StoreAuditService::GetInstance();
		$q = $request->getQueryParams();
		$selected = null;
		if (!empty($q['id']))
		{
			try
			{
				$selected = $svc->GetAudit(intval($q['id']));
				if (!DairyAccess::CanSeeBranch(intval($selected['branch_id'])))
				{
					$selected = null;
				}
			}
			catch (\Exception $e)
			{
				$selected = null;
			}
		}
		$allowed = DairyAccess::AllowedBranches();
		return $this->RenderPage($response, 'store_audits', [
			'templates' => $svc->GetTemplates(),
			'audits' => $svc->GetAudits($allowed),
			'comparison' => $svc->BranchComparison(90, $allowed),
			'branches' => $this->Branches(),
			'selected' => $selected,
			'canEditTemplates' => DairyAccess::HasAny([User::PERMISSION_ALERTS_MANAGE]),
		]);
	}

	public function Alerts(Request $request, Response $response, array $args)
	{
		$svc = AlertCenterService::GetInstance();
		$q = $request->getQueryParams();
		$scan = null;
		try
		{
			$scan = $svc->ScanIfStale();
		}
		catch (\Throwable $e)
		{
			$scan = ['error' => $e->getMessage()];
		}
		$canSee = DairyAccess::CanOpen('/alerts');
		try
		{
			$filters = AlertCenterApiController::Filters($q);
		}
		catch (\Exception $e)
		{
			$filters = AlertCenterApiController::Filters(array_diff_key($q, ['branch_id' => 1]));
		}
		$tab = $q['tab'] ?? 'all';
		$period = $q['period'] ?? '7d';
		$trend = intval($q['trend'] ?? 30);
		$manage = DairyAccess::HasAny([User::PERMISSION_ALERTS_MANAGE]);
		$supervise = AlertCenterApiController::Supervise();
		$list = $svc->List($filters, $tab, intval($q['page'] ?? 1), 25, $q['sort'] ?? 'priority');
		foreach ($list['rows'] as &$a)
		{
			$a['allowed'] = AlertCenterService::AllowedActions($a, $manage, GROCY_USER_ID, $supervise);
		}
		unset($a);
		return $this->RenderPage($response, 'alert_center', [
			'dash' => $canSee ? $svc->Dashboard($filters, $period, $trend, $q['from'] ?? null, $q['to'] ?? null) : null,
			'list' => $list,
			'filters' => $filters,
			'query' => array_intersect_key($q, array_flip(['period', 'from', 'to', 'trend', 'tab', 'sort', 'branch_id', 'department', 'category', 'alert_type', 'priority', 'status', 'owner', 'q', 'rule', 'overdue', 'group', 'flow', 'shift'])),
			'avoided' => $canSee ? $svc->AvoidedLosses(!empty($filters['branch_id']) ? intval($filters['branch_id']) : null, $filters['allowed']) : null,
			'rules' => $svc->GetRules(),
			'lastScan' => $svc->LastScan(),
			'scan' => $scan,
			'branches' => $this->Branches(),
			'users' => $this->Users(),
			'departments' => $svc->Departments(),
			'views' => $svc->Views(GROCY_USER_ID),
			'notifications' => $svc->UserNotifications(GROCY_USER_ID, 15),
			'canManage' => $manage,
			'canSupervise' => $supervise,
			'canSee' => $canSee,
			'statusLabels' => AlertCenterService::STATUS,
		]);
	}
}
