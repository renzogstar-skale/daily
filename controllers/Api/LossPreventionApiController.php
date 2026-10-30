<?php

namespace Grocy\Controllers\Api;

use Grocy\Controllers\Users\User;
use Grocy\Helpers\DairyAccess;
use Grocy\Services\AlertCenterService;
use Grocy\Services\CycleCountService;
use Grocy\Services\LossPreventionService;
use Grocy\Services\RecallService;
use Grocy\Services\StoreAuditService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Etapa 19: control de inventario (stock fantasma, ABC-XYZ), pérdida total, retiros de producto, auditorías de tienda
 * y centro de alertas. Cada acción valida su permiso y la sucursal del registro (DairyAccess).
 */
class LossPreventionApiController extends BaseApiController
{
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

	private function Branch(Request $request): ?int
	{
		$q = $request->getQueryParams();
		return !empty($q['branch_id']) ? intval($q['branch_id']) : null;
	}

	private function RequireBranch(?int $branchId): void
	{
		if (!DairyAccess::CanSeeBranch($branchId))
		{
			throw new \Exception('No tiene acceso a la sucursal de este registro');
		}
	}

	/* ---------------- Control de inventario ---------------- */

	public function Phantom(Request $request, Response $response, array $args)
	{
		User::CheckPermission($request, User::PERMISSION_STOCK);
		return $this->Run($response, fn() => LossPreventionService::GetInstance()->PhantomStock($this->Branch($request)));
	}

	public function AbcXyz(Request $request, Response $response, array $args)
	{
		User::CheckPermission($request, User::PERMISSION_STOCK);
		return $this->Run($response, fn() => LossPreventionService::GetInstance()->AbcXyz($this->Branch($request)));
	}

	public function ScheduleCounts(Request $request, Response $response, array $args)
	{
		User::CheckPermission($request, User::PERMISSION_STOCK_INVENTORY);
		$b = $this->Body($request);
		return $this->Run($response, fn() => CycleCountService::GetInstance()->ScheduleForProducts(intval($b['branch_id'] ?? 0), (array) ($b['product_ids'] ?? []), trim(strval($b['note'] ?? '')) ?: 'Conteo ciego por sospecha de stock fantasma', GROCY_USER_ID));
	}

	public function TotalLoss(Request $request, Response $response, array $args)
	{
		User::CheckPermission($request, User::PERMISSION_WASTE_APPROVE);
		$q = $request->getQueryParams();
		return $this->Run($response, fn() => LossPreventionService::GetInstance()->TotalLoss($this->Branch($request), $q['from'] ?? date('Y-m-01'), $q['to'] ?? date('Y-m-d')));
	}

	/* ---------------- Retiros de producto ---------------- */

	public function RecallPreview(Request $request, Response $response, array $args)
	{
		User::CheckPermission($request, User::PERMISSION_RECALL_MANAGE);
		$q = $request->getQueryParams();
		return $this->Run($response, fn() => RecallService::GetInstance()->FindAffectedStock(intval($q['product_id'] ?? 0), ($q['best_before_from'] ?? '') ?: null, ($q['best_before_to'] ?? '') ?: null));
	}

	public function GetRecall(Request $request, Response $response, array $args)
	{
		User::CheckPermission($request, User::PERMISSION_QUALITY_QUARANTINE);
		return $this->Run($response, fn() => RecallService::GetInstance()->GetRecall(intval($args['id'])));
	}

	public function CreateRecall(Request $request, Response $response, array $args)
	{
		User::CheckPermission($request, User::PERMISSION_RECALL_MANAGE);
		return $this->Run($response, fn() => RecallService::GetInstance()->CreateRecall($this->Body($request), GROCY_USER_ID));
	}

	public function ConfirmRecallItem(Request $request, Response $response, array $args)
	{
		User::CheckPermission($request, User::PERMISSION_QUALITY_QUARANTINE);
		$b = $this->Body($request);
		return $this->Run($response, function () use ($args, $b) {
			$branch = \Grocy\Services\DatabaseService::GetInstance()->GetDbConnectionRaw()->query('SELECT branch_id FROM product_recall_items WHERE id = ' . intval($args['itemId']))->fetchColumn();
			$this->RequireBranch($branch !== false && $branch !== null ? intval($branch) : null);
			return RecallService::GetInstance()->ConfirmItem(intval($args['itemId']), $b['found_amount'] ?? null, $b['notes'] ?? null, GROCY_USER_ID);
		});
	}

	public function CloseRecall(Request $request, Response $response, array $args)
	{
		User::CheckPermission($request, User::PERMISSION_RECALL_MANAGE);
		return $this->Run($response, fn() => RecallService::GetInstance()->CloseRecall(intval($args['id']), $this->Body($request)['notes'] ?? null, GROCY_USER_ID));
	}

	public function CancelRecall(Request $request, Response $response, array $args)
	{
		User::CheckPermission($request, User::PERMISSION_RECALL_MANAGE);
		return $this->Run($response, fn() => RecallService::GetInstance()->CancelRecall(intval($args['id']), strval($this->Body($request)['reason'] ?? ''), GROCY_USER_ID));
	}

	/* ---------------- Auditorías de tienda ---------------- */

	public function GetAudit(Request $request, Response $response, array $args)
	{
		User::CheckPermission($request, User::PERMISSION_AUDIT_PERFORM);
		return $this->Run($response, function () use ($args) {
			$a = StoreAuditService::GetInstance()->GetAudit(intval($args['id']));
			$this->RequireBranch(intval($a['branch_id']));
			return $a;
		});
	}

	public function StartAudit(Request $request, Response $response, array $args)
	{
		User::CheckPermission($request, User::PERMISSION_AUDIT_PERFORM);
		$b = $this->Body($request);
		return $this->Run($response, fn() => StoreAuditService::GetInstance()->StartAudit(intval($b['template_id'] ?? 0), intval($b['branch_id'] ?? 0), GROCY_USER_ID));
	}

	public function SaveAuditAnswers(Request $request, Response $response, array $args)
	{
		User::CheckPermission($request, User::PERMISSION_AUDIT_PERFORM);
		$b = $this->Body($request);
		return $this->Run($response, function () use ($args, $b) {
			$svc = StoreAuditService::GetInstance();
			$this->RequireBranch(intval($svc->GetAudit(intval($args['id']))['branch_id']));
			$complete = !empty($b['complete']) && $b['complete'] !== 'false';
			return $svc->SaveAnswers(intval($args['id']), (array) ($b['answers'] ?? []), $complete, $b['notes'] ?? null, GROCY_USER_ID);
		});
	}

	public function SaveAuditTemplate(Request $request, Response $response, array $args)
	{
		User::CheckPermission($request, User::PERMISSION_ALERTS_MANAGE);
		return $this->Run($response, fn() => ['id' => StoreAuditService::GetInstance()->SaveTemplate($this->Body($request))]);
	}

	/* ---------------- Centro de alertas ---------------- */

	public function GetAlerts(Request $request, Response $response, array $args)
	{
		$q = $request->getQueryParams();
		return $this->Run($response, fn() => AlertCenterService::GetInstance()->GetAlerts($q['view'] ?? 'active', $this->Branch($request), DairyAccess::AllowedBranches(), !empty($q['mine']) ? GROCY_USER_ID : null));
	}

	public function GetAlertEvents(Request $request, Response $response, array $args)
	{
		return $this->Run($response, function () use ($args) {
			$this->AlertBranch(intval($args['id']));
			return AlertCenterService::GetInstance()->GetEvents(intval($args['id']));
		});
	}

	public function ScanAlerts(Request $request, Response $response, array $args)
	{
		$b = $this->Body($request);
		$branch = !empty($b['branch_id']) ? intval($b['branch_id']) : null;
		// Un usuario restringido solo puede barrer sus sucursales; toda la red requiere ALERTS_MANAGE
		if ($branch === null || DairyAccess::AllowedBranches() === null)
		{
			User::CheckPermission($request, User::PERMISSION_ALERTS_MANAGE);
		}
		return $this->Run($response, fn() => AlertCenterService::GetInstance()->Scan($branch, GROCY_USER_ID));
	}

	private function AlertBranch(int $id): array
	{
		$a = \Grocy\Services\DatabaseService::GetInstance()->GetDbConnectionRaw()->query('SELECT branch_id, owner_user_id FROM dairy_alerts WHERE id = ' . $id)->fetch(\PDO::FETCH_ASSOC);
		if (!$a)
		{
			throw new \Exception('Alerta no encontrada');
		}
		$this->RequireBranch($a['branch_id'] !== null ? intval($a['branch_id']) : null);
		return $a;
	}

	public function SaveAlertRule(Request $request, Response $response, array $args)
	{
		User::CheckPermission($request, User::PERMISSION_ALERTS_MANAGE);
		return $this->Run($response, fn() => AlertCenterService::GetInstance()->SaveRule($args['code'], $this->Body($request)));
	}
}
