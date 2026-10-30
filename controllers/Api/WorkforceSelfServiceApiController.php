<?php

namespace Grocy\Controllers\Api;

use Grocy\Controllers\Users\User;
use Grocy\Helpers\DairyAccess;
use Grocy\Services\MessagingService;
use Grocy\Services\OpenShiftService;
use Grocy\Services\PushService;
use Grocy\Services\WorkforceScheduleService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Autoservicio y comunicación (web móvil /m y app nativa): mensajes, push, turnos abiertos, confirmación de turno
 * y geocerca. Cada acción valida usuario, vínculo con su ficha de empleado, permiso y sucursal en el servidor.
 */
class WorkforceSelfServiceApiController extends BaseApiController
{
	private function Run(Response $response, callable $fn)
	{
		try
		{
			return $this->ApiResponse($response, $fn() ?? ['success' => true]);
		}
		catch (\Exception $ex)
		{
			$code = intval($ex->getCode());
			return $this->GenericErrorResponse($response, $ex->getMessage(), in_array($code, [403, 404], true) ? $code : 400);
		}
	}

	private function Body(Request $request): array
	{
		return $request->getParsedBody() ?? [];
	}

	private function Uid(): int
	{
		return defined('GROCY_USER_ID') ? GROCY_USER_ID : 1;
	}

	private function NeedManage(?int $branchId = null): void
	{
		if (!DairyAccess::HasAny([User::PERMISSION_WORKFORCE_SCHEDULE_MANAGE, User::PERMISSION_ADMIN]))
		{
			throw new \Exception('No tiene permiso para administrar horarios', 403);
		}
		if ($branchId !== null && !DairyAccess::CanSeeBranch($branchId))
		{
			throw new \Exception('No tiene acceso a esa sucursal', 403);
		}
	}

	private function ManagedBranches(): array
	{
		$allowed = DairyAccess::AllowedBranches();
		if ($allowed !== null)
		{
			return $allowed;
		}
		return array_map('intval', \Grocy\Services\DatabaseService::GetInstance()->GetDbConnectionRaw()->query('SELECT id FROM branches WHERE active = 1')->fetchAll(\PDO::FETCH_COLUMN));
	}

	/* ------------------------------------------------------------------ Mensajes */

	public function Threads(Request $request, Response $response, array $args)
	{
		return $this->Run($response, fn() => MessagingService::GetInstance()->Threads($this->Uid()));
	}

	public function Contacts(Request $request, Response $response, array $args)
	{
		return $this->Run($response, fn() => [
			'contacts' => MessagingService::GetInstance()->Contacts($this->Uid(), DairyAccess::AllowedBranches()),
			'can_announce' => DairyAccess::HasAny([User::PERMISSION_WORKFORCE_SCHEDULE_MANAGE, User::PERMISSION_ADMIN]),
			'branches' => array_map(fn($b) => ['id' => intval($b->id), 'name' => $b->name], DairyAccess::FilterBranches(\Grocy\Services\DatabaseService::GetInstance()->GetDbConnectionRaw()->query('SELECT id, name FROM branches WHERE active = 1 ORDER BY name')->fetchAll(\PDO::FETCH_OBJ), 'id'))
		]);
	}

	public function OpenDirect(Request $request, Response $response, array $args)
	{
		return $this->Run($response, fn() => ['thread_id' => MessagingService::GetInstance()->OpenDirect($this->Uid(), intval($this->Body($request)['user_id'] ?? 0), DairyAccess::AllowedBranches())]);
	}

	public function CreateGroup(Request $request, Response $response, array $args)
	{
		return $this->Run($response, function () use ($request) {
			$b = $this->Body($request);
			return ['thread_id' => MessagingService::GetInstance()->CreateGroup($this->Uid(), strval($b['title'] ?? ''), (array) ($b['member_ids'] ?? []), DairyAccess::AllowedBranches())];
		});
	}

	public function Announce(Request $request, Response $response, array $args)
	{
		return $this->Run($response, function () use ($request) {
			$b = $this->Body($request);
			$branch = intval($b['branch_id'] ?? 0);
			$this->NeedManage($branch);
			return MessagingService::GetInstance()->CreateAnnouncement($this->Uid(), $branch, strval($b['title'] ?? ''), strval($b['body'] ?? ''));
		});
	}

	public function Messages(Request $request, Response $response, array $args)
	{
		return $this->Run($response, function () use ($request, $args) {
			$q = $request->getQueryParams();
			return MessagingService::GetInstance()->Messages($this->Uid(), intval($args['id']), !empty($q['before']) ? intval($q['before']) : null);
		});
	}

	public function Send(Request $request, Response $response, array $args)
	{
		return $this->Run($response, function () use ($request, $args) {
			$b = $this->Body($request);
			$uuid = isset($b['client_uuid']) && preg_match('/^[A-Za-z0-9-]{8,64}$/', strval($b['client_uuid'])) ? strval($b['client_uuid']) : null;
			return MessagingService::GetInstance()->Send($this->Uid(), intval($args['id']), strval($b['body'] ?? ''), $uuid);
		});
	}

	/* ------------------------------------------------------------------ Push */

	public function RegisterPush(Request $request, Response $response, array $args)
	{
		return $this->Run($response, function () use ($request) {
			$b = $this->Body($request);
			return PushService::GetInstance()->Register($this->Uid(), strval($b['token'] ?? ''), isset($b['device_name']) ? strval($b['device_name']) : null);
		});
	}

	public function UnregisterPush(Request $request, Response $response, array $args)
	{
		return $this->Run($response, fn() => ['revoked' => PushService::GetInstance()->Unregister($this->Uid(), strval($this->Body($request)['token'] ?? ''))]);
	}

	public function PushDevices(Request $request, Response $response, array $args)
	{
		return $this->Run($response, fn() => ['devices' => PushService::GetInstance()->Devices($this->Uid())]);
	}

	/* ------------------------------------------------------------------ Turnos abiertos y confirmación */

	public function OpenShifts(Request $request, Response $response, array $args)
	{
		return $this->Run($response, function () {
			$d = OpenShiftService::GetInstance()->ForEmployee($this->Uid());
			$d['can_manage'] = DairyAccess::HasAny([User::PERMISSION_WORKFORCE_SCHEDULE_MANAGE, User::PERMISSION_ADMIN]);
			$d['manager'] = $d['can_manage'] ? OpenShiftService::GetInstance()->ForManager($this->ManagedBranches()) : null;
			return $d;
		});
	}

	public function CreateOpenShift(Request $request, Response $response, array $args)
	{
		return $this->Run($response, function () use ($request) {
			$b = $this->Body($request);
			$this->NeedManage(intval($b['branch_id'] ?? 0));
			return OpenShiftService::GetInstance()->Create($b, $this->Uid());
		});
	}

	public function Claim(Request $request, Response $response, array $args)
	{
		return $this->Run($response, fn() => OpenShiftService::GetInstance()->Claim(intval($args['id']), $this->Uid(), isset($this->Body($request)['notes']) ? mb_substr(strval($this->Body($request)['notes']), 0, 300) : null));
	}

	public function Withdraw(Request $request, Response $response, array $args)
	{
		return $this->Run($response, function () use ($args) {
			OpenShiftService::GetInstance()->Withdraw(intval($args['id']), $this->Uid());
			return ['success' => true];
		});
	}

	public function DecideClaim(Request $request, Response $response, array $args)
	{
		return $this->Run($response, function () use ($request, $args) {
			$branch = \Grocy\Services\DatabaseService::GetInstance()->GetDbConnectionRaw()->query('SELECT r.branch_id FROM labor_coverage_claims c JOIN labor_coverage_requests r ON r.id = c.request_id WHERE c.id = ' . intval($args['id']))->fetchColumn();
			if ($branch === false)
			{
				throw new \Exception('Solicitud no encontrada', 404);
			}
			$this->NeedManage(intval($branch));
			$b = $this->Body($request);
			return OpenShiftService::GetInstance()->Decide(intval($args['id']), !empty($b['approve']), $this->Uid(), isset($b['notes']) ? mb_substr(strval($b['notes']), 0, 300) : null);
		});
	}

	public function ConfirmShift(Request $request, Response $response, array $args)
	{
		return $this->Run($response, fn() => WorkforceScheduleService::GetInstance()->ConfirmShift(intval($args['id']), $this->Uid()));
	}

	/* ------------------------------------------------------------------ Geocerca */

	/** Modo de geocerca de la sucursal del empleado: el cliente pide ubicación solo si la sucursal la usa */
	public function MyGeofence(Request $request, Response $response, array $args)
	{
		return $this->Run($response, function () {
			$emp = WorkforceScheduleService::GetInstance()->GetEmployeeByUser($this->Uid());
			if (!$emp)
			{
				return ['mode' => 'off', 'linked' => false];
			}
			$b = \Grocy\Services\DatabaseService::GetInstance()->GetDbConnectionRaw()->query('SELECT name, geofence_mode, geofence_radius_m, latitude IS NOT NULL AS configured FROM branches WHERE id = ' . intval($emp->branch_id))->fetch(\PDO::FETCH_ASSOC);
			return ['linked' => true, 'branch' => $b['name'], 'mode' => $b['geofence_mode'] ?: 'off', 'radius_m' => intval($b['geofence_radius_m']), 'configured' => (bool) $b['configured'],
				'privacy' => 'Solo se registra un punto de ubicación al marcar entrada o salida. DAILY no rastrea su ubicación durante el turno.'];
		});
	}

	public function SaveGeofence(Request $request, Response $response, array $args)
	{
		return $this->Run($response, function () use ($request) {
			if (!DairyAccess::HasAny([User::PERMISSION_BRANCH_MANAGE, User::PERMISSION_ADMIN]))
			{
				throw new \Exception('No tiene permiso para configurar sucursales', 403);
			}
			$b = $this->Body($request);
			$branch = intval($b['branch_id'] ?? 0);
			if (!DairyAccess::CanSeeBranch($branch))
			{
				throw new \Exception('No tiene acceso a esa sucursal', 403);
			}
			$mode = in_array($b['mode'] ?? 'off', ['off', 'record', 'enforce'], true) ? $b['mode'] : 'off';
			$lat = isset($b['latitude']) && $b['latitude'] !== '' ? floatval($b['latitude']) : null;
			$lng = isset($b['longitude']) && $b['longitude'] !== '' ? floatval($b['longitude']) : null;
			if (($lat === null) !== ($lng === null) || ($lat !== null && (abs($lat) > 90 || abs($lng) > 180)))
			{
				throw new \Exception('Coordenadas inválidas');
			}
			if ($mode === 'enforce' && $lat === null)
			{
				throw new \Exception('Para exigir ubicación, registre primero las coordenadas de la sucursal');
			}
			$radius = max(30, min(2000, intval($b['radius_m'] ?? 150)));
			\Grocy\Services\DatabaseService::GetInstance()->GetDbConnectionRaw()->prepare('UPDATE branches SET latitude = ?, longitude = ?, geofence_radius_m = ?, geofence_mode = ? WHERE id = ?')->execute([$lat, $lng, $radius, $mode, $branch]);
			return ['branch_id' => $branch, 'mode' => $mode, 'radius_m' => $radius];
		});
	}
}
