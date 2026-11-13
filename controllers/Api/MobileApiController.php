<?php

namespace Grocy\Controllers\Api;

use Grocy\Controllers\Users\User;
use Grocy\Helpers\DailySubscription;
use Grocy\Helpers\DairyAccess;
use Grocy\Services\AlertCenterService;
use Grocy\Services\MobileManagerService;
use Grocy\Services\StockTransferService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * DAIRY Mobile (manager): lecturas para /m. Las acciones se ejecutan con las APIs existentes;
 * aquí se calcula qué acciones puede ver el usuario en cada caso (según permisos, estado y segregación).
 */
class MobileApiController extends BaseApiController
{
	private function Run(Response $response, callable $fn)
	{
		try
		{
			return $this->ApiResponse($response, $fn());
		}
		catch (\Exception $ex)
		{
			return $this->GenericErrorResponse($response, $ex->getMessage(), $ex->getCode() === 403 ? 403 : ($ex->getCode() === 404 ? 404 : 400));
		}
	}

	private function Need(array $perms): void
	{
		if (!DairyAccess::HasAny($perms))
		{
			throw new \Exception('No tiene permiso para ver esta información', 403);
		}
	}

	// Sucursal pedida (validada); usuario con sucursales asignadas y sin selección → su primera sucursal
	private function Branch(Request $request): ?int
	{
		$q = $request->getQueryParams();
		$b = !empty($q['branch_id']) ? intval($q['branch_id']) : null;
		$allowed = DairyAccess::AllowedBranches();
		if ($b !== null && !DairyAccess::CanSeeBranch($b))
		{
			throw new \Exception('No tiene acceso a esa sucursal', 403);
		}
		return $b ?? ($allowed !== null ? $allowed[0] : null);
	}

	private function RequireBranch($branchId): void
	{
		if ($branchId !== null && !DairyAccess::CanSeeBranch(intval($branchId)))
		{
			throw new \Exception('No tiene acceso a la sucursal de este registro', 403);
		}
	}

	public static function Period(array $q): array
	{
		$valid = fn($d) => is_string($d) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $d);
		$today = date('Y-m-d');
		switch ($q['period'] ?? 'month')
		{
			case 'today':
				return [$today, $today];
			case '7d':
				return [date('Y-m-d', strtotime('-6 days')), $today];
			case '30d':
				return [date('Y-m-d', strtotime('-29 days')), $today];
			case 'year':
				return [date('Y-01-01'), $today];
			case 'custom':
				if ($valid($q['from'] ?? null) && $valid($q['to'] ?? null) && $q['from'] <= $q['to'])
				{
					return [$q['from'], $q['to']];
				}
		}
		return [date('Y-m-01'), $today];
	}

	private function Svc(): MobileManagerService
	{
		return MobileManagerService::GetInstance();
	}

	private static function OptInt(array $q, string $k): ?int
	{
		return isset($q[$k]) && $q[$k] !== '' ? intval($q[$k]) : null;
	}

	public function Me(Request $request, Response $response, array $args)
	{
		return $this->Run($response, function () {
			$perms = [];
			foreach ((new \ReflectionClass(User::class))->getConstants() as $k => $v)
			{
				if (str_starts_with($k, 'PERMISSION_') && DairyAccess::HasAny([$v]))
				{
					$perms[] = $v;
				}
			}
			$st = \Grocy\Services\DatabaseService::GetInstance()->GetDbConnectionRaw()->prepare('SELECT username, first_name, last_name FROM users WHERE id = :id');
			$st->execute([':id' => GROCY_USER_ID]);
			$u = $st->fetch(\PDO::FETCH_OBJ) ?: null;
			$allowed = DairyAccess::AllowedBranches();
			$branches = DairyAccess::FilterBranches($this->Svc()->Lookups()['branches']);
			return ['user_id' => GROCY_USER_ID, 'name' => trim(($u->first_name ?? '') . ' ' . ($u->last_name ?? '')) ?: ($u->username ?? ''),
				'permissions' => $perms, 'branches' => $branches, 'all_branches' => $allowed === null,
				'self_approval' => defined('GROCY_WASTE_ALLOW_SELF_APPROVAL') && GROCY_WASTE_ALLOW_SELF_APPROVAL, 'currency' => defined('GROCY_CURRENCY') ? GROCY_CURRENCY : '',
				'role' => ($role = \Grocy\Services\MobileWorkService::Role()), 'role_label' => \Grocy\Services\MobileWorkService::ROLE_LABELS[$role],
				// Plan contratado: la app móvil aplica las mismas restricciones que la web (las APIs también las validan)
				'plan' => DailySubscription::Plan(), 'capabilities' => DailySubscription::Current()['capabilities'],
				'employee' => DailySubscription::Has('workforce.schedule') ? \Grocy\Services\MobileWorkService::GetInstance()->Employee(GROCY_USER_ID) : null,
				'can_approve' => \Grocy\Services\MobileWorkService::CanApprove() && DailySubscription::Has('waste.approve'),
				'can_team' => \Grocy\Services\MobileWorkService::CanSeeTeam() && DailySubscription::Has('workforce.schedule'), 'can_ai' => false,
				'onboarded' => \Grocy\Services\MobileWorkService::GetInstance()->GetSetting(GROCY_USER_ID, 'dairy_m_onboarded') === '1',
				'web' => array_values(array_filter(['/schedule', '/workforce', '/kardex', '/transfers', '/mrp', '/suppliers', '/recalls', '/alerts', '/kpis', '/reports', '/branches', '/scan', '/orders', '/traceability', '/audits', '/inventory-control'], fn($p) => DairyAccess::CanOpen($p) && DailySubscription::MissingForRoute($p) === null))];
		});
	}

	public function Home(Request $request, Response $response, array $args)
	{
		return $this->Run($response, function () use ($request) {
			$this->Need([User::PERMISSION_KPIS_VIEW, User::PERMISSION_WASTE_APPROVE, User::PERMISSION_STOCK, User::PERMISSION_SALES_VIEW]);
			[$f, $t] = self::Period($request->getQueryParams());
			$d = $this->Svc()->Home($this->Branch($request), $f, $t, DairyAccess::AllowedBranches());
			// Bloques sin permiso de lectura: se omiten (no se muestran ceros falsos)
			if (!DairyAccess::HasAny([User::PERMISSION_SALES_VIEW, User::PERMISSION_KPIS_VIEW]))
			{
				unset($d['sales']);
			}
			if (!DairyAccess::HasAny([User::PERMISSION_STOCK]))
			{
				unset($d['inventory'], $d['differences']);
			}
			return $d;
		});
	}

	public function Sales(Request $request, Response $response, array $args)
	{
		return $this->Run($response, function () use ($request) {
			$this->Need([User::PERMISSION_SALES_VIEW, User::PERMISSION_KPIS_VIEW]);
			$q = $request->getQueryParams();
			[$f, $t] = self::Period($q);
			return $this->Svc()->Sales($this->Branch($request), $f, $t, self::OptInt($q, 'group_id'), self::OptInt($q, 'product_id'));
		});
	}

	public function Sale(Request $request, Response $response, array $args)
	{
		return $this->Run($response, function () use ($args) {
			$this->Need([User::PERMISSION_SALES_VIEW, User::PERMISSION_KPIS_VIEW]);
			$s = $this->Svc()->Sale(intval($args['id']));
			if (!$s)
			{
				throw new \Exception('Venta no encontrada', 404);
			}
			$this->RequireBranch($s['branch_id']);
			$s['can_cancel'] = $s['status'] === 'completed' && DairyAccess::HasAny([User::PERMISSION_SALES_CANCEL]);
			return $s;
		});
	}

	public function Waste(Request $request, Response $response, array $args)
	{
		return $this->Run($response, function () use ($request) {
			$this->Need([User::PERMISSION_WASTE_REPORT, User::PERMISSION_WASTE_APPROVE, User::PERMISSION_KPIS_VIEW]);
			$q = $request->getQueryParams();
			[$f, $t] = self::Period($q);
			return $this->Svc()->Waste($this->Branch($request), $f, $t, $q['bucket'] ?? null, self::OptInt($q, 'group_id'), self::OptInt($q, 'product_id'));
		});
	}

	public function Inventory(Request $request, Response $response, array $args)
	{
		return $this->Run($response, function () use ($request) {
			$this->Need([User::PERMISSION_STOCK]);
			$q = $request->getQueryParams();
			return $this->Svc()->Inventory($this->Branch($request), self::OptInt($q, 'group_id'), $q['filter'] ?? null);
		});
	}

	public function Sku(Request $request, Response $response, array $args)
	{
		return $this->Run($response, function () use ($request, $args) {
			$this->Need([User::PERMISSION_STOCK, User::PERMISSION_WASTE_REPORT, User::PERMISSION_STOCK_INVENTORY, User::PERMISSION_STOCK_TRANSFER]);
			$d = $this->Svc()->Sku(intval($args['id']), $this->Branch($request));
			if (!$d)
			{
				throw new \Exception('Producto no encontrado', 404);
			}
			$d['actions'] = array_values(array_filter([
				DairyAccess::HasAny([User::PERMISSION_WASTE_REPORT]) ? 'waste' : null,
				DairyAccess::HasAny([User::PERMISSION_STOCK_INVENTORY]) ? 'count' : null,
				DairyAccess::HasAny([User::PERMISSION_STOCK_INVENTORY]) ? 'adjust' : null,
				DairyAccess::HasAny([User::PERMISSION_STOCK_TRANSFER]) ? 'move' : null,
			]));
			return $d;
		});
	}

	public function Inbox(Request $request, Response $response, array $args)
	{
		return $this->Run($response, function () use ($request) {
			$this->Need([User::PERMISSION_WASTE_APPROVE, User::PERMISSION_ALERTS_MANAGE, User::PERMISSION_KPIS_VIEW, User::PERMISSION_STOCK_INVENTORY]);
			$items = $this->Svc()->Inbox($this->Branch($request), DairyAccess::AllowedBranches());
			// Solo los tipos que el usuario puede abrir
			$can = [
				'waste' => DairyAccess::HasAny([User::PERMISSION_WASTE_APPROVE, User::PERMISSION_WASTE_REPORT]),
				'count' => DairyAccess::HasAny([User::PERMISSION_STOCK_INVENTORY, User::PERMISSION_INVENTORY_ADJUST_APPROVE]),
				'alert' => DairyAccess::CanOpen('/alerts'),
				'transfer' => DairyAccess::HasAny([User::PERMISSION_STOCK_TRANSFER]),
			];
			return array_values(array_filter($items, fn($i) => $can[$i['type']] ?? false));
		});
	}

	public function CaseDetail(Request $request, Response $response, array $args)
	{
		return $this->Run($response, function () use ($args) {
			$id = intval($args['id']);
			$me = GROCY_USER_ID;
			$selfOk = defined('GROCY_WASTE_ALLOW_SELF_APPROVAL') && GROCY_WASTE_ALLOW_SELF_APPROVAL;
			switch ($args['type'])
			{
				case 'waste':
					$this->Need([User::PERMISSION_WASTE_APPROVE, User::PERMISSION_WASTE_REPORT]);
					$c = $this->Svc()->WasteCase($id);
					if (!$c)
					{
						throw new \Exception('Caso no encontrado', 404);
					}
					$this->RequireBranch($c['branch_id']);
					$approve = DairyAccess::HasAny([User::PERMISSION_WASTE_APPROVE]);
					$actions = [];
					$note = null;
					if ($c['status'] === 'pending_approval' && $approve)
					{
						if ($selfOk || intval($c['reported_by_user_id']) !== $me)
						{
							$actions = ['approve', 'reject'];
						}
						else
						{
							$note = 'Usted reportó esta merma: otra persona debe aprobarla (segregación de funciones).';
						}
						$actions[] = 'assign';
					}
					elseif ($c['status'] === 'approved' && $approve && !in_array($c['workflow_status'], ['closed', 'verified'], true))
					{
						$actions = ['investigate', 'assign', 'correct', 'close'];
					}
					return ['type' => 'waste', 'case' => $c, 'actions' => $actions, 'note' => $note];

				case 'count':
					$this->Need([User::PERMISSION_STOCK_INVENTORY, User::PERMISSION_INVENTORY_ADJUST_APPROVE]);
					$c = $this->Svc()->CountCase($id);
					if (!$c)
					{
						throw new \Exception('Conteo no encontrado', 404);
					}
					$this->RequireBranch($c['branch_id']);
					$actions = [];
					$note = null;
					if ($c['status'] === 'pending' && DairyAccess::HasAny([User::PERMISSION_STOCK_INVENTORY]))
					{
						$actions[] = 'execute';
					}
					if ($c['status'] === 'discrepancy')
					{
						if (DairyAccess::HasAny([User::PERMISSION_INVENTORY_ADJUST_APPROVE]))
						{
							if ($selfOk || intval($c['executed_by_user_id']) !== $me)
							{
								$actions[] = 'approve';
							}
							else
							{
								$note = 'Usted ejecutó este conteo: otra persona debe aprobar el ajuste.';
							}
						}
						if (DairyAccess::HasAny([User::PERMISSION_STOCK_INVENTORY]))
						{
							$actions[] = 'recount';
						}
					}
					return ['type' => 'count', 'case' => $c, 'actions' => $actions, 'note' => $note];

				case 'alert':
					// Mismo expediente y mismas reglas que la web: sin acceso al centro, solo las alertas propias
					$svc = AlertCenterService::GetInstance();
					$raw = \Grocy\Services\DatabaseService::GetInstance()->GetDbConnectionRaw()->query('SELECT branch_id, owner_user_id FROM dairy_alerts WHERE id = ' . $id)->fetch(\PDO::FETCH_ASSOC);
					if (!$raw || !DairyAccess::CanSeeBranch($raw['branch_id'] !== null ? intval($raw['branch_id']) : null))
					{
						throw new \Exception('Alerta no encontrada', 404);
					}
					if (!DairyAccess::CanOpen('/alerts') && intval($raw['owner_user_id']) !== $me)
					{
						throw new \Exception('No tiene permiso para ver esta alerta', 403);
					}
					$det = $svc->Detail($id);
					$a = $det['alert'];
					$manage = DairyAccess::HasAny([User::PERMISSION_ALERTS_MANAGE]);
					$actions = AlertCenterService::AllowedActions($a, $manage, $me);
					return ['type' => 'alert', 'case' => $a, 'events' => $det['events'], 'evidence' => $det['evidence'], 'plan' => $det['actions'], 'actions' => $actions, 'note' => null];

				case 'transfer':
					$this->Need([User::PERMISSION_STOCK_TRANSFER]);
					$d = StockTransferService::GetInstance()->GetTransferDetail($id);
					$t = $d['transfer'];
					if (!DairyAccess::CanSeeBranch(intval($t['from_branch_id'])) && !DairyAccess::CanSeeBranch(intval($t['to_branch_id'])))
					{
						throw new \Exception('No tiene acceso a este traslado', 403);
					}
					$actions = $t['status'] === 'in_transit' && DairyAccess::CanSeeBranch(intval($t['to_branch_id'])) ? ['receive'] : [];
					return ['type' => 'transfer', 'case' => $t, 'products' => $d['products'], 'actions' => $actions, 'note' => null];
			}
			throw new \Exception('Tipo de caso no válido', 404);
		});
	}

	public function Search(Request $request, Response $response, array $args)
	{
		return $this->Run($response, function () use ($request) {
			$q = $request->getQueryParams();
			$rows = $this->Svc()->Search(strval($q['q'] ?? ''), $this->Branch($request));
			$can = [
				'sku' => DairyAccess::HasAny([User::PERMISSION_STOCK, User::PERMISSION_WASTE_REPORT, User::PERMISSION_STOCK_INVENTORY, User::PERMISSION_STOCK_TRANSFER]),
				'group' => DairyAccess::HasAny([User::PERMISSION_STOCK, User::PERMISSION_SALES_VIEW, User::PERMISSION_KPIS_VIEW]),
				'waste' => DairyAccess::HasAny([User::PERMISSION_WASTE_APPROVE, User::PERMISSION_WASTE_REPORT]),
				'sale' => DairyAccess::HasAny([User::PERMISSION_SALES_VIEW, User::PERMISSION_KPIS_VIEW]),
			];
			return array_values(array_filter($rows, fn($r) => $can[$r['type']] ?? false));
		});
	}

	public function Lookups(Request $request, Response $response, array $args)
	{
		return $this->Run($response, function () {
			$l = $this->Svc()->Lookups();
			$l['branches'] = DairyAccess::FilterBranches($l['branches']);
			$l['locations'] = DairyAccess::FilterBranches($l['locations']);
			// La lista de personas solo hace falta para asignar responsables
			if (!DairyAccess::HasAny([User::PERMISSION_WASTE_APPROVE, User::PERMISSION_ALERTS_MANAGE]))
			{
				$l['users'] = [];
			}
			return $l;
		});
	}
}
