<?php

namespace Grocy\Controllers\Api;

use Grocy\Controllers\Users\User;
use Grocy\Helpers\DairyAccess;
use Grocy\Services\LossIntelligenceService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Etapa 35: API del Centro de Control de pérdidas (misma lógica para web y /m).
 * Lectura: WASTE_REPORT / WASTE_APPROVE / KPIS_VIEW con alcance de sucursal. Hurto: LOSS_PREVENTION o WASTE_APPROVE.
 * Escritura de precios: WASTE_REPORT detecta/valida/corrige; aprobar y cerrar exige WASTE_APPROVE o LOSS_PREVENTION.
 */
class LossIntelligenceApiController extends BaseApiController
{
	const READ = [User::PERMISSION_WASTE_REPORT, User::PERMISSION_WASTE_APPROVE, User::PERMISSION_KPIS_VIEW];

	private function Run(Response $response, callable $fn)
	{
		try
		{
			return $this->ApiResponse($response, $fn() ?? ['success' => true]);
		}
		catch (\Grocy\Controllers\Users\PermissionMissingException $ex)
		{
			throw $ex;
		}
		catch (\Exception $ex)
		{
			return $this->GenericErrorResponse($response, $ex->getMessage());
		}
	}

	private function Ctx(Request $request, array $perms = self::READ): array
	{
		$q = $request->getQueryParams();
		$f = LossIntelligenceService::Filters($q);
		$f['branch_id'] = $this->DairyRead($request, $perms, $f['branch_id']);
		$from = !empty($q['from']) ? substr($q['from'], 0, 10) : date('Y-m-01');
		$to = !empty($q['to']) ? substr($q['to'], 0, 10) : date('Y-m-d');
		if ($from > $to)
		{
			throw new \Exception('Rango de fechas inválido');
		}
		return [$f, $from, $to, $q];
	}

	private static function CanApprove(): bool
	{
		return DairyAccess::HasAny([User::PERMISSION_WASTE_APPROVE, User::PERMISSION_LOSS_PREVENTION, User::PERMISSION_ADMIN]);
	}

	public function Summary(Request $request, Response $response, array $args)
	{
		return $this->Run($response, function () use ($request) {
			[$f, $from, $to] = $this->Ctx($request);
			return LossIntelligenceService::GetInstance()->Summary($f, $from, $to);
		});
	}

	public function YearComparison(Request $request, Response $response, array $args)
	{
		return $this->Run($response, function () use ($request) {
			[$f, , , $q] = $this->Ctx($request);
			$years = array_values(array_filter(array_map('intval', explode(',', strval($q['years'] ?? '')))));
			if (!$years)
			{
				$years = [intval(date('Y')) - 1, intval(date('Y'))];
			}
			if (count($years) > 6)
			{
				throw new \Exception('Máximo 6 años por comparación');
			}
			return LossIntelligenceService::GetInstance()->YearComparison($f, $years, strval($q['metric'] ?? 'cost'));
		});
	}

	public function Causes(Request $request, Response $response, array $args)
	{
		return $this->Run($response, function () use ($request) {
			[$f, $from, $to] = $this->Ctx($request);
			return LossIntelligenceService::GetInstance()->CauseMatrix($f, $from, $to);
		});
	}

	public function Classification(Request $request, Response $response, array $args)
	{
		return $this->Run($response, function () use ($request) {
			[$f, $from, $to] = $this->Ctx($request);
			return LossIntelligenceService::GetInstance()->Classification($f, $from, $to);
		});
	}

	public function Theft(Request $request, Response $response, array $args)
	{
		return $this->Run($response, function () use ($request) {
			[$f, $from, $to] = $this->Ctx($request, [User::PERMISSION_LOSS_PREVENTION, User::PERMISSION_WASTE_APPROVE]);
			return LossIntelligenceService::GetInstance()->Theft($f, $from, $to);
		});
	}

	public function Formats(Request $request, Response $response, array $args)
	{
		return $this->Run($response, function () use ($request) {
			[$f, $from, $to] = $this->Ctx($request, [User::PERMISSION_KPIS_VIEW, User::PERMISSION_WASTE_APPROVE]);
			return LossIntelligenceService::GetInstance()->Formats($f, $from, $to);
		});
	}

	public function Financial(Request $request, Response $response, array $args)
	{
		return $this->Run($response, function () use ($request) {
			[$f, $from, $to] = $this->Ctx($request, [User::PERMISSION_KPIS_VIEW, User::PERMISSION_WASTE_APPROVE]);
			return LossIntelligenceService::GetInstance()->Financial($f, $from, $to);
		});
	}

	public function Ranking(Request $request, Response $response, array $args)
	{
		return $this->Run($response, function () use ($request) {
			[$f] = $this->Ctx($request, [User::PERMISSION_STOCK_INVENTORY, User::PERMISSION_WASTE_APPROVE, User::PERMISSION_WASTE_REPORT]);
			return LossIntelligenceService::GetInstance()->SensitiveRanking($f['branch_id']);
		});
	}

	public function ScheduleCounts(Request $request, Response $response, array $args)
	{
		User::CheckPermission($request, User::PERMISSION_STOCK_INVENTORY);
		$b = $request->getParsedBody() ?? [];
		$branchId = intval($b['branch_id'] ?? 0);
		$branchId = $this->DairyRead($request, [User::PERMISSION_STOCK_INVENTORY], $branchId ?: null) ?? 0;
		if ($branchId <= 0)
		{
			return $this->GenericErrorResponse($response, 'Seleccione una sucursal');
		}
		$prio = $b['priorities'] ?? ['urgente', 'alta'];
		$prio = array_values(array_intersect(is_array($prio) ? $prio : explode(',', strval($prio)), ['urgente', 'alta', 'media', 'baja']));
		return $this->Run($response, fn() => LossIntelligenceService::GetInstance()->ScheduleRiskCounts($branchId, GROCY_USER_ID, $prio ?: ['urgente']));
	}

	public function ColdChain(Request $request, Response $response, array $args)
	{
		return $this->Run($response, function () use ($request) {
			[$f, $from, $to] = $this->Ctx($request, [User::PERMISSION_QUALITY_INSPECT, User::PERMISSION_QUALITY_QUARANTINE, User::PERMISSION_WASTE_APPROVE, User::PERMISSION_WASTE_REPORT]);
			return LossIntelligenceService::GetInstance()->ColdChain($f['branch_id'], $from, $to);
		});
	}

	public function Perishables(Request $request, Response $response, array $args)
	{
		return $this->Run($response, function () use ($request) {
			[$f, $from, $to] = $this->Ctx($request);
			return LossIntelligenceService::GetInstance()->Perishables($f, $from, $to);
		});
	}

	/* ---------------- Precios y marcación ---------------- */

	public function PriceIssues(Request $request, Response $response, array $args)
	{
		return $this->Run($response, function () use ($request) {
			[$f, , , $q] = $this->Ctx($request);
			return LossIntelligenceService::GetInstance()->PriceIssues($f['branch_id'], $q['status'] ?? null);
		});
	}

	public function DetectPriceIssues(Request $request, Response $response, array $args)
	{
		User::CheckPermission($request, User::PERMISSION_WASTE_REPORT);
		$b = $request->getParsedBody() ?? [];
		$branchId = $this->DairyRead($request, [User::PERMISSION_WASTE_REPORT], !empty($b['branch_id']) ? intval($b['branch_id']) : null);
		$from = !empty($b['from']) ? substr($b['from'], 0, 10) : date('Y-m-d', strtotime('-30 days'));
		$to = !empty($b['to']) ? substr($b['to'], 0, 10) : date('Y-m-d');
		return $this->Run($response, fn() => LossIntelligenceService::GetInstance()->DetectPriceIssues($branchId, $from, $to, GROCY_USER_ID));
	}

	public function CreatePriceIssue(Request $request, Response $response, array $args)
	{
		User::CheckPermission($request, User::PERMISSION_WASTE_REPORT);
		$b = $request->getParsedBody() ?? [];
		if (!empty($b['branch_id']) && !DairyAccess::CanSeeBranch(intval($b['branch_id'])))
		{
			return $this->GenericErrorResponse($response, 'No tiene acceso a esa sucursal');
		}
		return $this->Run($response, fn() => ['id' => LossIntelligenceService::GetInstance()->CreatePriceIssue($b, GROCY_USER_ID)]);
	}

	public function AdvancePriceIssue(Request $request, Response $response, array $args)
	{
		User::CheckPermission($request, User::PERMISSION_WASTE_REPORT);
		$b = $request->getParsedBody() ?? [];
		$id = intval($args['id']);
		$row = \Grocy\Services\DatabaseService::GetInstance()->GetDbConnectionRaw()->query('SELECT branch_id FROM price_discrepancies WHERE id = ' . $id)->fetch(\PDO::FETCH_ASSOC);
		if ($row && $row['branch_id'] !== null && !DairyAccess::CanSeeBranch(intval($row['branch_id'])))
		{
			return $this->GenericErrorResponse($response, 'No tiene acceso a la sucursal de este registro');
		}
		return $this->Run($response, fn() => LossIntelligenceService::GetInstance()->AdvancePriceIssue($id, strval($b['to'] ?? ''), $b, GROCY_USER_ID, self::CanApprove()));
	}

	/* ---------------- Perecederos ---------------- */

	public function SaveYieldLot(Request $request, Response $response, array $args)
	{
		User::CheckPermission($request, User::PERMISSION_WASTE_REPORT);
		$b = $request->getParsedBody() ?? [];
		if (!DairyAccess::CanSeeBranch(intval($b['branch_id'] ?? 0)))
		{
			return $this->GenericErrorResponse($response, 'No tiene acceso a esa sucursal');
		}
		return $this->Run($response, fn() => ['id' => LossIntelligenceService::GetInstance()->SaveYieldLot($b, GROCY_USER_ID)]);
	}

	public function CloseYieldLot(Request $request, Response $response, array $args)
	{
		User::CheckPermission($request, User::PERMISSION_WASTE_APPROVE);
		$b = $request->getParsedBody() ?? [];
		return $this->Run($response, fn() => LossIntelligenceService::GetInstance()->CloseYieldLot(intval($args['id']), isset($b['remaining_kg']) && $b['remaining_kg'] !== '' ? floatval($b['remaining_kg']) : null, GROCY_USER_ID));
	}

	/* ---------------- Configuración ---------------- */

	public function SaveSettings(Request $request, Response $response, array $args)
	{
		User::CheckPermission($request, User::PERMISSION_WASTE_REASONS_EDIT);
		$b = $request->getParsedBody() ?? [];
		return $this->Run($response, function () use ($b) {
			$settings = (array) ($b['settings'] ?? []);
			foreach ($b as $k => $v)
			{
				// Formularios planos: settings[clave]
				if (preg_match('/^settings\[([a-z_]+)\]$/', strval($k), $m))
				{
					$settings[$m[1]] = $v;
				}
			}
			foreach ($settings as $k => $v)
			{
				LossIntelligenceService::GetInstance()->SaveSetting(strval($k), strval($v));
			}
			return ['success' => true];
		});
	}

	public function SetReasonCause(Request $request, Response $response, array $args)
	{
		User::CheckPermission($request, User::PERMISSION_WASTE_REASONS_EDIT);
		$b = $request->getParsedBody() ?? [];
		$db = \Grocy\Services\DatabaseService::GetInstance()->GetDbConnectionRaw();
		$code = strval($b['cause'] ?? '');
		$st = $db->prepare('SELECT COUNT(*) FROM loss_causes WHERE code = ? AND active = 1');
		$st->execute([$code]);
		if (intval($st->fetchColumn()) === 0)
		{
			return $this->GenericErrorResponse($response, 'Causal inválida');
		}
		$db->prepare('UPDATE waste_reasons SET loss_cause_code = ? WHERE id = ?')->execute([$code, intval($args['id'])]);
		return $this->ApiResponse($response, ['success' => true]);
	}

	public function SaveBranchFormat(Request $request, Response $response, array $args)
	{
		User::CheckPermission($request, User::PERMISSION_BRANCH_MANAGE);
		$b = $request->getParsedBody() ?? [];
		$area = isset($b['sales_area_m2']) && $b['sales_area_m2'] !== '' ? floatval($b['sales_area_m2']) : null;
		if ($area !== null && $area <= 0)
		{
			return $this->GenericErrorResponse($response, 'La superficie debe ser mayor que 0');
		}
		\Grocy\Services\DatabaseService::GetInstance()->GetDbConnectionRaw()->prepare('UPDATE branches SET format = ?, sales_area_m2 = ? WHERE id = ?')
			->execute([trim(strval($b['format'] ?? '')) ?: null, $area, intval($args['id'])]);
		return $this->ApiResponse($response, ['success' => true]);
	}

	public function ControlCenterExecutive(Request $request, Response $response, array $args)
	{
		return $this->Run($response, function () use ($request) {
			$q = $request->getQueryParams();
			$branchId = !empty($q['branch_id']) ? intval($q['branch_id']) : null;
			$date = !empty($q['date']) ? $q['date'] : date('Y-m-d');
			$dept = !empty($q['department']) ? $q['department'] : null;
			$causal = !empty($q['causal']) ? $q['causal'] : null;
			$metric = !empty($q['metric']) ? $q['metric'] : 'pct';

			return \Grocy\Services\WasteExecutiveControlCenterService::GetInstance()->GetExecutiveData(
				$branchId,
				$date,
				$dept,
				$causal,
				$metric
			);
		});
	}
}
