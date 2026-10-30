<?php

namespace Grocy\Controllers\Api;

use Grocy\Controllers\Users\User;
use Grocy\Helpers\DairyAccess;
use Grocy\Services\MobileWorkService;
use Grocy\Services\ScanService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * DAIRY Mobile por rol: Hoy, tareas, equipo, mi horario, notificaciones, preferencias y escáner.
 * Cada endpoint valida permisos y sucursal en el servidor (ocultar botones no basta).
 */
class MobileWorkApiController extends BaseApiController
{
	private function Run(Response $response, callable $fn)
	{
		try
		{
			return $this->ApiResponse($response, $fn());
		}
		catch (\Exception $ex)
		{
			$code = intval($ex->getCode());
			return $this->GenericErrorResponse($response, $ex->getMessage(), in_array($code, [403, 404], true) ? $code : 400);
		}
	}

	private function Svc(): MobileWorkService
	{
		return MobileWorkService::GetInstance();
	}

	private function Need(array $perms): void
	{
		if (!DairyAccess::HasAny($perms))
		{
			throw new \Exception('No tiene permiso para esta acción', 403);
		}
	}

	private function Branch(Request $request): ?int
	{
		$q = $request->getQueryParams();
		$b = !empty($q['branch_id']) ? intval($q['branch_id']) : null;
		if ($b !== null && !DairyAccess::CanSeeBranch($b))
		{
			throw new \Exception('No tiene acceso a esa sucursal', 403);
		}
		$allowed = DairyAccess::AllowedBranches();
		return $b ?? ($allowed !== null ? $allowed[0] : null);
	}

	private function Body(Request $request): array
	{
		return $request->getParsedBody() ?? [];
	}

	public function Today(Request $request, Response $response, array $args)
	{
		return $this->Run($response, fn() => $this->Svc()->Today($this->Branch($request), GROCY_USER_ID, MobileWorkService::Role()));
	}

	public function Tasks(Request $request, Response $response, array $args)
	{
		return $this->Run($response, function () use ($request) {
			$scope = $request->getQueryParams()['scope'] ?? 'mine';
			if ($scope === 'approve' && !MobileWorkService::CanApprove())
			{
				throw new \Exception('Su perfil no aprueba solicitudes', 403);
			}
			if ($scope === 'team')
			{
				$this->Need([User::PERMISSION_TASKS, User::PERMISSION_WASTE_APPROVE, User::PERMISSION_ALERTS_MANAGE, User::PERMISSION_KPIS_VIEW, User::PERMISSION_WORKFORCE_ATTENDANCE_MANAGE]);
				if (MobileWorkService::Role() === 'employee')
				{
					throw new \Exception('La vista de equipo es para supervisores y gerentes', 403);
				}
			}
			$scope = in_array($scope, ['mine', 'approve', 'team'], true) ? $scope : 'mine';
			return ['scope' => $scope, 'items' => $this->Svc()->Tasks($scope, $this->Branch($request), GROCY_USER_ID, MobileWorkService::Role())];
		});
	}

	public function Task(Request $request, Response $response, array $args)
	{
		return $this->Run($response, function () use ($args) {
			$t = $this->Svc()->GetTask(intval($args['id']));
			if (!$t)
			{
				throw new \Exception('Tarea no encontrada', 404);
			}
			if (!DairyAccess::CanSeeBranch(intval($t['branch_id'])))
			{
				throw new \Exception('No tiene acceso a la sucursal de esta tarea', 403);
			}
			$mine = $this->Svc()->IsAssignedTo($t, GROCY_USER_ID);
			$t['can_complete'] = $t['status'] === 'pending' && ($mine || DairyAccess::HasAny([User::PERMISSION_TASKS_MARK_COMPLETED]));
			$t['note_required'] = $t['source_type'] === 'alert';
			return $t;
		});
	}

	public function CompleteTask(Request $request, Response $response, array $args)
	{
		return $this->Run($response, function () use ($request, $args) {
			$t = $this->Svc()->GetTask(intval($args['id']));
			if (!$t)
			{
				throw new \Exception('Tarea no encontrada', 404);
			}
			if (!DairyAccess::CanSeeBranch(intval($t['branch_id'])))
			{
				throw new \Exception('No tiene acceso a la sucursal de esta tarea', 403);
			}
			if (!$this->Svc()->IsAssignedTo($t, GROCY_USER_ID) && !DairyAccess::HasAny([User::PERMISSION_TASKS_MARK_COMPLETED]))
			{
				throw new \Exception('Solo la persona asignada o quien tenga permiso de completar tareas puede cerrarla', 403);
			}
			$b = $this->Body($request);
			$photo = isset($b['evidence_photo_path']) && preg_match('#^/?[A-Za-z0-9_./-]+$#', (string) $b['evidence_photo_path']) && strpos((string) $b['evidence_photo_path'], '..') === false ? (string) $b['evidence_photo_path'] : null;
			return $this->Svc()->CompleteTask(intval($args['id']), mb_substr(strval($b['notes'] ?? ''), 0, 1000), $photo, GROCY_USER_ID);
		});
	}

	public function TaskFromAlert(Request $request, Response $response, array $args)
	{
		return $this->Run($response, function () use ($request, $args) {
			$this->Need([User::PERMISSION_ALERTS_MANAGE]);
			$b = $this->Body($request);
			$branch = \Grocy\Services\DatabaseService::GetInstance()->GetDbConnectionRaw()->query('SELECT branch_id FROM dairy_alerts WHERE id = ' . intval($args['id']))->fetchColumn();
			if ($branch && !DairyAccess::CanSeeBranch(intval($branch)))
			{
				throw new \Exception('No tiene acceso a la sucursal de esta alerta', 403);
			}
			return $this->Svc()->TaskFromAlert(intval($args['id']), intval($b['user_id'] ?? 0), isset($b['due']) ? strval($b['due']) : null, GROCY_USER_ID);
		});
	}

	public function CreateTask(Request $request, Response $response, array $args)
	{
		return $this->Run($response, function () use ($request) {
			$this->Need([User::PERMISSION_TASKS, User::PERMISSION_WORKFORCE_SCHEDULE_MANAGE, User::PERMISSION_WORKFORCE_ATTENDANCE_MANAGE, User::PERMISSION_ALERTS_MANAGE]);
			if (MobileWorkService::Role() === 'employee')
			{
				throw new \Exception('Solo supervisores y gerentes asignan tareas', 403);
			}
			$b = $this->Body($request);
			$branch = $this->Svc()->EmployeeBranch(intval($b['employee_id'] ?? 0));
			if ($branch !== null && !DairyAccess::CanSeeBranch($branch))
			{
				throw new \Exception('No tiene acceso a la sucursal de ese empleado', 403);
			}
			return $this->Svc()->CreateTask($b, GROCY_USER_ID);
		});
	}

	public function Team(Request $request, Response $response, array $args)
	{
		return $this->Run($response, function () use ($request) {
			if (!MobileWorkService::CanSeeTeam())
			{
				throw new \Exception('No tiene permiso para ver el equipo', 403);
			}
			$q = $request->getQueryParams();
			$b = $this->Branch($request) ?? ($this->Svc()->Employee(GROCY_USER_ID)['branch_id'] ?? 1);
			$date = isset($q['date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $q['date']) ? $q['date'] : date('Y-m-d');
			$d = $this->Svc()->Team(intval($b), $date);
			$d['can_edit'] = DairyAccess::HasAny([User::PERMISSION_WORKFORCE_SCHEDULE_MANAGE]);
			$d['can_attendance'] = DairyAccess::HasAny([User::PERMISSION_WORKFORCE_ATTENDANCE_MANAGE]);
			$d['can_task'] = MobileWorkService::Role() !== 'employee' && DairyAccess::HasAny([User::PERMISSION_TASKS, User::PERMISSION_WORKFORCE_SCHEDULE_MANAGE, User::PERMISSION_WORKFORCE_ATTENDANCE_MANAGE, User::PERMISSION_ALERTS_MANAGE]);
			$d['requests'] = $d['can_edit'] ? $this->Svc()->PendingPeopleRequests([intval($b)], GROCY_USER_ID) : [];
			return $d;
		});
	}

	public function Schedule(Request $request, Response $response, array $args)
	{
		return $this->Run($response, fn() => ['linked' => ($d = $this->Svc()->MySchedule(GROCY_USER_ID)) !== null, 'data' => $d, 'state' => $this->Svc()->ShiftState(GROCY_USER_ID)]);
	}

	public function Notifications(Request $request, Response $response, array $args)
	{
		return $this->Run($response, fn() => $this->Svc()->Notifications($this->Branch($request), GROCY_USER_ID, MobileWorkService::Role()));
	}

	public function NotificationsSeen(Request $request, Response $response, array $args)
	{
		return $this->Run($response, function () {
			$this->Svc()->SetSetting(GROCY_USER_ID, 'dairy_m_notif_seen', date('Y-m-d H:i:s'));
			return ['success' => true];
		});
	}

	public function SavePrefs(Request $request, Response $response, array $args)
	{
		return $this->Run($response, function () use ($request) {
			$b = $this->Body($request);
			if (isset($b['notifications']) && is_array($b['notifications']))
			{
				$p = [];
				foreach (array_keys(MobileWorkService::NOTIF_TYPES) as $k)
				{
					$p[$k] = !empty($b['notifications'][$k]);
				}
				$this->Svc()->SetSetting(GROCY_USER_ID, 'dairy_m_notif_prefs', json_encode($p));
			}
			if (isset($b['onboarded']))
			{
				$this->Svc()->SetSetting(GROCY_USER_ID, 'dairy_m_onboarded', $b['onboarded'] ? '1' : '0');
			}
			return ['success' => true, 'prefs' => $this->Svc()->Prefs(GROCY_USER_ID)];
		});
	}

	public function Scan(Request $request, Response $response, array $args)
	{
		return $this->Run($response, function () use ($request) {
			$this->Need([User::PERMISSION_STOCK, User::PERMISSION_SCAN_OPERATE, User::PERMISSION_WASTE_REPORT, User::PERMISSION_STOCK_INVENTORY, User::PERMISSION_STOCK_TRANSFER]);
			$code = trim(strval($request->getQueryParams()['code'] ?? ''));
			if ($code === '')
			{
				throw new \Exception('Ingrese o escanee un código');
			}
			$r = ScanService::GetInstance()->Resolve($code, $this->Branch($request));
			// Sin permiso de inventario: solo identificación del producto (sin existencias ni lotes)
			if (!DairyAccess::HasAny([User::PERMISSION_STOCK]))
			{
				unset($r['entries'], $r['stock']);
				if (isset($r['availability']) && !DairyAccess::HasAny([User::PERMISSION_WASTE_REPORT, User::PERMISSION_STOCK_TRANSFER]))
				{
					unset($r['availability']);
				}
			}
			$r['actions'] = $this->Svc()->ScanActions($r);
			return $r;
		});
	}

	/* ---------------- Operaciones de piso (app nativa y /m): mismas reglas y servicios que la web */

	/** Sucursal de la operación: la indicada (si tiene acceso) o la del empleado */
	private function OpBranch(array $b): int
	{
		$bid = !empty($b['branch_id']) ? intval($b['branch_id']) : 0;
		if ($bid > 0 && !DairyAccess::CanSeeBranch($bid))
		{
			throw new \Exception('No tiene acceso a esa sucursal', 403);
		}
		if ($bid <= 0)
		{
			$allowed = DairyAccess::AllowedBranches();
			if ($allowed === null)
			{
				throw new \Exception('Seleccione la sucursal de la operación');
			}
			$bid = intval($allowed[0]);
		}
		return $bid;
	}

	/** Recepción de mercancía escaneada: entra a inventario, kardex y libro (Conciliación), idempotente por operation_id */
	public function OpReceive(Request $request, Response $response, array $args)
	{
		return $this->Run($response, function () use ($request) {
			$this->Need([User::PERMISSION_STOCK_PURCHASE]);
			$b = $this->Body($request);
			$qty = floatval($b['quantity'] ?? 0);
			return \Grocy\Services\ReconciliationService::GetInstance()->RegisterGoodsIn(['product_id' => intval($b['product_id'] ?? 0), 'branch_id' => $this->OpBranch($b),
				'expected_qty' => floatval($b['expected_quantity'] ?? $qty), 'received_qty' => $qty, 'accepted_qty' => $qty, 'unit_cost' => floatval($b['unit_cost'] ?? 0),
				'supplier_name' => trim(strval($b['supplier_name'] ?? '')), 'po_code' => trim(strval($b['document'] ?? '')), 'notes' => 'Recepción desde el teléfono', 'operation_id' => strval($b['operation_id'] ?? '') ?: null], GROCY_USER_ID);
		});
	}

	/** Conteo físico: programa y ejecuta un conteo ciego; la diferencia queda pendiente de aprobación (no ajusta stock) */
	public function OpCount(Request $request, Response $response, array $args)
	{
		return $this->Run($response, function () use ($request) {
			$this->Need([User::PERMISSION_STOCK_INVENTORY]);
			$b = $this->Body($request);
			$pid = intval($b['product_id'] ?? 0);
			$bid = $this->OpBranch($b);
			$db = \Grocy\Services\DatabaseService::GetInstance()->GetDbConnectionRaw();
			$cc = \Grocy\Services\CycleCountService::GetInstance();
			$pending = intval($db->query("SELECT id FROM stock_cycle_counts WHERE branch_id = $bid AND product_id = $pid AND status = 'pending' ORDER BY id DESC LIMIT 1")->fetchColumn());
			if (!$pending)
			{
				$cc->ScheduleForProducts($bid, [$pid], 'Conteo desde el teléfono', GROCY_USER_ID);
				$pending = intval($db->query("SELECT id FROM stock_cycle_counts WHERE branch_id = $bid AND product_id = $pid AND status = 'pending' ORDER BY id DESC LIMIT 1")->fetchColumn());
			}
			if (!$pending)
			{
				throw new \Exception('El producto no tiene existencias registradas en la sucursal para contar');
			}
			return ['count_id' => $pending] + $cc->Execute($pending, $b['counted'] ?? null, trim(strval($b['notes'] ?? '')) ?: null, GROCY_USER_ID);
		});
	}

	public function OpWasteReasons(Request $request, Response $response, array $args)
	{
		return $this->Run($response, function () {
			$this->Need([User::PERMISSION_WASTE_REPORT]);
			return ['reasons' => \Grocy\Services\DatabaseService::GetInstance()->GetDbConnectionRaw()->query('SELECT id, name FROM waste_reasons WHERE IFNULL(active, 1) = 1 ORDER BY name')->fetchAll(\PDO::FETCH_ASSOC)];
		});
	}

	/** Merma desde el teléfono: mismo registro que /waste/report (aprobación según monto), idempotente por operation_id */
	public function OpWaste(Request $request, Response $response, array $args)
	{
		return $this->Run($response, function () use ($request) {
			$this->Need([User::PERMISSION_WASTE_REPORT]);
			$b = $this->Body($request);
			$w = (array) \Grocy\Services\WasteService::GetInstance()->ReportWaste(intval($b['product_id'] ?? 0), floatval($b['quantity'] ?? 0), intval($b['reason_id'] ?? 0), $this->OpBranch($b), null,
				trim(strval($b['stock_id'] ?? '')) ?: 'default', trim(strval($b['notes'] ?? '')) ?: 'Registrada desde el teléfono', null, null, null, null, null, null, strval($b['operation_id'] ?? '') ?: null, GROCY_USER_ID);
			$id = intval($w['waste_record_id'] ?? $w['id'] ?? 0);
			$status = $id ? \Grocy\Services\DatabaseService::GetInstance()->GetDbConnectionRaw()->query("SELECT status FROM waste_records WHERE id = $id")->fetchColumn() : null;
			return ['success' => true, 'id' => $id, 'status' => $status, 'message' => $status === 'pending_approval' ? 'Merma enviada a aprobación: el stock se descuenta al aprobarla' : 'Merma registrada y descontada del inventario'];
		});
	}

	/** Aprobaciones pendientes que el usuario puede decidir: mermas y ajustes por conteo */
	public function Approvals(Request $request, Response $response, array $args)
	{
		return $this->Run($response, function () use ($request) {
			$db = \Grocy\Services\DatabaseService::GetInstance()->GetDbConnectionRaw();
			$allowed = DairyAccess::AllowedBranches();
			$bw = $allowed === null ? '' : ' AND w.branch_id IN (' . implode(',', array_map('intval', $allowed ?: [0])) . ')';
			$bc = $allowed === null ? '' : ' AND c.branch_id IN (' . implode(',', array_map('intval', $allowed ?: [0])) . ')';
			$out = ['waste' => [], 'counts' => []];
			if (DairyAccess::HasAny([User::PERMISSION_WASTE_APPROVE]))
			{
				$out['waste'] = $db->query("SELECT w.id, p.name AS product, w.amount, w.total_cost, w.created_timestamp, b.name AS branch, r.name AS reason FROM waste_records w
					JOIN products p ON p.id = w.product_id LEFT JOIN branches b ON b.id = w.branch_id LEFT JOIN waste_reasons r ON r.id = w.waste_reason_id
					WHERE w.status = 'pending_approval'$bw ORDER BY w.created_timestamp DESC LIMIT 50")->fetchAll(\PDO::FETCH_ASSOC);
			}
			if (DairyAccess::HasAny([User::PERMISSION_INVENTORY_ADJUST_APPROVE]))
			{
				$out['counts'] = $db->query("SELECT c.id, p.name AS product, c.theoretical_stock, c.counted_stock, c.difference_amount, c.difference_cost, c.executed_date, b.name AS branch FROM stock_cycle_counts c
					JOIN products p ON p.id = c.product_id LEFT JOIN branches b ON b.id = c.branch_id WHERE c.status = 'discrepancy'$bc ORDER BY c.executed_date DESC LIMIT 50")->fetchAll(\PDO::FETCH_ASSOC);
			}
			return $out;
		});
	}

	public function DecideApproval(Request $request, Response $response, array $args)
	{
		return $this->Run($response, function () use ($request, $args) {
			$b = $this->Body($request);
			$id = intval($args['id']);
			$approve = !empty($b['approve']);
			if ($args['kind'] === 'waste')
			{
				$this->Need([User::PERMISSION_WASTE_APPROVE]);
				$svc = \Grocy\Services\WasteService::GetInstance();
				$approve ? $svc->ApproveWaste($id, GROCY_USER_ID) : $svc->RejectWaste($id, GROCY_USER_ID, trim(strval($b['reason'] ?? '')) ?: 'Rechazada desde el teléfono');
				return ['success' => true, 'message' => $approve ? 'Merma aprobada: stock y kardex actualizados' : 'Merma rechazada'];
			}
			if ($args['kind'] === 'count')
			{
				$this->Need([User::PERMISSION_INVENTORY_ADJUST_APPROVE]);
				if (!$approve)
				{
					throw new \Exception('Un ajuste por conteo no se rechaza desde el teléfono: programe un reconteo o abra una investigación en la web');
				}
				return \Grocy\Services\CycleCountService::GetInstance()->ApproveAdjustment($id, intval(GROCY_USER_ID));
			}
			throw new \Exception('Tipo de aprobación no válido');
		});
	}
}
