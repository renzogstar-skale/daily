<?php

namespace Grocy\Controllers\Api;

use Grocy\Controllers\Users\User;
use Grocy\Helpers\DairyAccess;
use Grocy\Services\DatabaseService;
use Grocy\Services\LossCensusService;
use Grocy\Services\LossIntelligenceService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Etapa 36: API de la metodología del Censo de Mermas (misma lógica para la web y /m).
 * Lectura: WASTE_REPORT / WASTE_APPROVE / KPIS_VIEW con alcance de sucursal. Hurto: LOSS_PREVENTION o WASTE_APPROVE.
 * Errores administrativos: WASTE_REPORT detecta, registra evidencia, concilia y corrige; aprobar, verificar y descartar
 * exigen WASTE_APPROVE o LOSS_PREVENTION (con segregación). Metas, controles y supuestos: WASTE_REASONS_EDIT.
 */
class LossCensusApiController extends BaseApiController
{
	const READ = [User::PERMISSION_WASTE_REPORT, User::PERMISSION_WASTE_APPROVE, User::PERMISSION_KPIS_VIEW];
	const THEFT = [User::PERMISSION_LOSS_PREVENTION, User::PERMISSION_WASTE_APPROVE];

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
		$from = !empty($q['from']) ? substr($q['from'], 0, 10) : date('Y-01-01');
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

	private static function CanTheft(): bool
	{
		return DairyAccess::HasAny([User::PERMISSION_LOSS_PREVENTION, User::PERMISSION_WASTE_APPROVE, User::PERMISSION_ADMIN]);
	}

	private function Body(Request $request): array
	{
		$b = $request->getParsedBody();
		return is_array($b) ? $b : [];
	}

	public function Hierarchy(Request $request, Response $response, array $args)
	{
		return $this->Run($response, function () use ($request) {
			[$f, $from, $to, $q] = $this->Ctx($request);
			$s = LossCensusService::GetInstance();
			$out = $s->Hierarchy($f, $from, $to);
			$out['monthly'] = $s->Monthly($f, intval($q['year'] ?? substr($to, 0, 4)));
			return $out;
		});
	}

	public function Departments(Request $request, Response $response, array $args)
	{
		return $this->Run($response, function () use ($request) {
			[$f, $from, $to] = $this->Ctx($request);
			return LossCensusService::GetInstance()->Departments($f, $from, $to);
		});
	}

	public function Benchmarks(Request $request, Response $response, array $args)
	{
		return $this->Run($response, function () use ($request) {
			$this->Ctx($request);
			return ['benchmarks' => LossCensusService::GetInstance()->Benchmarks(), 'notice' => 'Cifras del Censo: referencia histórica externa, no son registros de DAIRY.'];
		});
	}

	public function Targets(Request $request, Response $response, array $args)
	{
		return $this->Run($response, function () use ($request) {
			$this->Ctx($request);
			return ['targets' => LossCensusService::GetInstance()->Targets()];
		});
	}

	public function SaveTarget(Request $request, Response $response, array $args)
	{
		User::CheckPermission($request, User::PERMISSION_WASTE_REASONS_EDIT);
		$b = $this->Body($request);
		if (($b['scope_type'] ?? '') === 'branch' && !DairyAccess::CanSeeBranch(intval($b['scope_value'] ?? 0)))
		{
			return $this->GenericErrorResponse($response, 'No tiene acceso a esa sucursal');
		}
		return $this->Run($response, fn() => LossCensusService::GetInstance()->SaveTarget($b, GROCY_USER_ID));
	}

	public function AdminErrors(Request $request, Response $response, array $args)
	{
		return $this->Run($response, function () use ($request) {
			[$f, , , $q] = $this->Ctx($request);
			$d = LossCensusService::GetInstance()->AdminErrors($f['branch_id'], $q['status'] ?? 'open', $q['category'] ?? null);
			$d['can_approve'] = self::CanApprove();
			return $d;
		});
	}

	public function AdminErrorHistory(Request $request, Response $response, array $args)
	{
		return $this->Run($response, function () use ($request, $args) {
			$this->Ctx($request);
			$this->AssertCaseBranch(intval($args['id']));
			return ['history' => LossCensusService::GetInstance()->CaseHistory(intval($args['id']))];
		});
	}

	public function DetectAdminErrors(Request $request, Response $response, array $args)
	{
		User::CheckPermission($request, User::PERMISSION_WASTE_REPORT);
		$b = $this->Body($request);
		$branchId = $this->DairyRead($request, [User::PERMISSION_WASTE_REPORT], !empty($b['branch_id']) ? intval($b['branch_id']) : null);
		$from = !empty($b['from']) ? substr($b['from'], 0, 10) : date('Y-m-d', strtotime('-90 days'));
		$to = !empty($b['to']) ? substr($b['to'], 0, 10) : date('Y-m-d');
		return $this->Run($response, fn() => LossCensusService::GetInstance()->DetectAdminErrors($branchId, $from, $to, GROCY_USER_ID));
	}

	public function CreateAdminError(Request $request, Response $response, array $args)
	{
		User::CheckPermission($request, User::PERMISSION_WASTE_REPORT);
		$b = $this->Body($request);
		if (!empty($b['branch_id']) && !DairyAccess::CanSeeBranch(intval($b['branch_id'])))
		{
			return $this->GenericErrorResponse($response, 'No tiene acceso a esa sucursal');
		}
		$b['idempotency_key'] = $b['idempotency_key'] ?? $request->getHeaderLine('X-Idempotency-Key');
		return $this->Run($response, fn() => ['id' => LossCensusService::GetInstance()->CreateAdminError($b, GROCY_USER_ID)]);
	}

	private function AssertCaseBranch(int $id): void
	{
		$row = DatabaseService::GetInstance()->GetDbConnectionRaw()->query('SELECT branch_id FROM admin_error_cases WHERE id = ' . $id)->fetch(\PDO::FETCH_ASSOC);
		if ($row && $row['branch_id'] !== null && !DairyAccess::CanSeeBranch(intval($row['branch_id'])))
		{
			throw new \Exception('No tiene acceso a la sucursal de este caso');
		}
	}

	public function AdvanceAdminError(Request $request, Response $response, array $args)
	{
		User::CheckPermission($request, User::PERMISSION_WASTE_REPORT);
		$b = $this->Body($request);
		return $this->Run($response, function () use ($b, $args) {
			$id = intval($args['id']);
			$this->AssertCaseBranch($id);
			return LossCensusService::GetInstance()->AdvanceAdminError($id, strval($b['to'] ?? ''), $b, GROCY_USER_ID, self::CanApprove());
		});
	}

	public function Theft(Request $request, Response $response, array $args)
	{
		return $this->Run($response, function () use ($request) {
			[$f, $from, $to] = $this->Ctx($request, self::THEFT);
			if (!self::CanTheft())
			{
				throw new \Exception('Requiere permiso de prevención de pérdidas');
			}
			return LossCensusService::GetInstance()->TheftIntel($f, $from, $to);
		});
	}

	public function WasteDetails(Request $request, Response $response, array $args)
	{
		User::CheckPermission($request, User::PERMISSION_WASTE_REPORT);
		$b = $this->Body($request);
		return $this->Run($response, function () use ($b, $args) {
			$id = intval($args['id']);
			$row = DatabaseService::GetInstance()->GetDbConnectionRaw()->query('SELECT branch_id FROM waste_records WHERE id = ' . $id)->fetch(\PDO::FETCH_ASSOC);
			if ($row && !DairyAccess::CanSeeBranch(intval($row['branch_id'])))
			{
				throw new \Exception('No tiene acceso a la sucursal de esta merma');
			}
			LossCensusService::GetInstance()->SetWasteDetails($id, $b, self::CanTheft());
			return ['success' => true];
		});
	}

	public function Perishables(Request $request, Response $response, array $args)
	{
		return $this->Run($response, function () use ($request) {
			[$f, $from, $to] = $this->Ctx($request);
			return LossCensusService::GetInstance()->PerishableDestinations($f, $from, $to);
		});
	}

	public function Economic(Request $request, Response $response, array $args)
	{
		return $this->Run($response, function () use ($request) {
			[$f, $from, $to] = $this->Ctx($request);
			$d = LossCensusService::GetInstance()->Economic($f, $from, $to);
			if (!self::CanTheft())
			{
				$d['theft'] = null;
			}
			return $d;
		});
	}

	public function Controls(Request $request, Response $response, array $args)
	{
		return $this->Run($response, function () use ($request) {
			[$f, $from, $to] = $this->Ctx($request);
			return LossCensusService::GetInstance()->Controls($f, $from, $to);
		});
	}

	public function SaveControl(Request $request, Response $response, array $args)
	{
		User::CheckPermission($request, User::PERMISSION_WASTE_REASONS_EDIT);
		$b = $this->Body($request);
		if (!empty($b['branch_id']) && !DairyAccess::CanSeeBranch(intval($b['branch_id'])))
		{
			return $this->GenericErrorResponse($response, 'No tiene acceso a esa sucursal');
		}
		return $this->Run($response, fn() => ['id' => LossCensusService::GetInstance()->SaveControl($b, GROCY_USER_ID)]);
	}

	public function SaveSocial(Request $request, Response $response, array $args)
	{
		User::CheckPermission($request, User::PERMISSION_WASTE_REASONS_EDIT);
		$b = $this->Body($request);
		return $this->Run($response, function () use ($b) {
			$keys = ['social_country', 'social_currency', 'social_year', 'social_job_annual_cost', 'social_job_source', 'social_vis_value', 'social_vis_source'];
			foreach (['social_job_annual_cost', 'social_vis_value'] as $k)
			{
				if (isset($b[$k]) && $b[$k] !== '' && (!is_numeric($b[$k]) || floatval($b[$k]) <= 0))
				{
					throw new \Exception('Los supuestos deben ser valores positivos');
				}
			}
			if ((!empty($b['social_job_annual_cost']) && trim(strval($b['social_job_source'] ?? '')) === '') || (!empty($b['social_vis_value']) && trim(strval($b['social_vis_source'] ?? '')) === ''))
			{
				throw new \Exception('Cada supuesto requiere su fuente documentada');
			}
			$li = LossIntelligenceService::GetInstance();
			foreach ($keys as $k)
			{
				if (array_key_exists($k, $b))
				{
					$li->SaveSetting($k, strval($b[$k]));
				}
			}
			return ['success' => true];
		});
	}
}
