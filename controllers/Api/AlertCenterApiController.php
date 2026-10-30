<?php

namespace Grocy\Controllers\Api;

use Grocy\Controllers\Users\User;
use Grocy\Helpers\DairyAccess;
use Grocy\Services\AlertCenterService;
use Grocy\Services\AutomationEngineService;
use Grocy\Services\DatabaseService;
use Grocy\Services\MobileWorkService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Centro de alertas (web y DAIRY Mobile usan los mismos endpoints). Reglas de acceso:
 *  - Ver el centro: DairyAccess::CanOpen('/alerts'). Sin ese acceso, cada persona ve y atiende solo las alertas a su nombre.
 *  - Asignar, descartar, escalar, cambiar prioridad, reabrir, aprobar y acciones masivas: ALERTS_MANAGE.
 *  - Atender/investigar/resolver: el responsable asignado o un gestor. Toda alerta valida la sucursal (aislamiento).
 */
class AlertCenterApiController extends BaseApiController
{
	const MANAGE_ACTIONS = ['assign', 'dismiss', 'escalate', 'priority', 'reopen', 'decide', 'decide_request', 'task'];
	const OWNER_ACTIONS = ['start', 'investigate', 'wait_info', 'request_approval', 'resolve', 'plan'];

	private function Run(Response $response, callable $fn)
	{
		try
		{
			return $this->ApiResponse($response, $fn() ?? ['success' => true]);
		}
		catch (\Exception $ex)
		{
			$code = intval($ex->getCode());
			return $this->GenericErrorResponse($response, $ex->getMessage(), $code >= 400 && $code < 500 ? $code : 400);
		}
	}

	private function Svc(): AlertCenterService
	{
		return AlertCenterService::GetInstance();
	}

	private function Body(Request $request): array
	{
		return $request->getParsedBody() ?? [];
	}

	private static function Manage(): bool
	{
		return DairyAccess::HasAny([User::PERMISSION_ALERTS_MANAGE]);
	}

	// Supervisor: asigna, escala, decide aprobaciones intermedias, valida cierres no críticos e investiga en su alcance de sucursales
	const SUPERVISE_ACTIONS = ['assign', 'escalate', 'decide', 'decide_request', 'task', 'prevent'];

	public static function Supervise(): bool
	{
		return DairyAccess::HasAny([User::PERMISSION_ALERTS_SUPERVISE]);
	}

	// Filtros desde la URL (mismos nombres que la página, para compartir vistas)
	public static function Filters(array $q): array
	{
		$f = ['allowed' => DairyAccess::AllowedBranches(), 'me' => GROCY_USER_ID];
		foreach (['branch_id', 'department', 'category', 'alert_type', 'priority', 'status', 'owner', 'q', 'rule', 'overdue', 'group', 'flow', 'shift'] as $k)
		{
			if (isset($q[$k]) && $q[$k] !== '')
			{
				$f[$k] = is_string($q[$k]) ? trim($q[$k]) : $q[$k];
			}
		}
		if (!empty($f['branch_id']) && !DairyAccess::CanSeeBranch(intval($f['branch_id'])))
		{
			throw new \Exception('No tiene acceso a esa sucursal', 403);
		}
		if (!DairyAccess::CanOpen('/alerts'))
		{
			$f['owner'] = 'me';
		}
		return $f;
	}

	private function Alert(int $id): array
	{
		$a = DatabaseService::GetInstance()->GetDbConnectionRaw()->query('SELECT id, branch_id, owner_user_id, status FROM dairy_alerts WHERE id = ' . $id)->fetch(\PDO::FETCH_ASSOC);
		if (!$a)
		{
			throw new \Exception('Alerta no encontrada', 404);
		}
		if (!DairyAccess::CanSeeBranch($a['branch_id'] !== null ? intval($a['branch_id']) : null))
		{
			throw new \Exception('No tiene acceso a la sucursal de esta alerta', 403);
		}
		if (!DairyAccess::CanOpen('/alerts') && intval($a['owner_user_id']) !== GROCY_USER_ID)
		{
			throw new \Exception('Solo puede ver las alertas asignadas a usted', 403);
		}
		return $a;
	}

	public function Dashboard(Request $request, Response $response, array $args)
	{
		return $this->Run($response, function () use ($request) {
			if (!DairyAccess::CanOpen('/alerts'))
			{
				throw new \Exception('No tiene permiso para ver el tablero de alertas', 403);
			}
			$q = $request->getQueryParams();
			return $this->Svc()->Dashboard(self::Filters($q), $q['period'] ?? '7d', intval($q['trend'] ?? 30), $q['from'] ?? null, $q['to'] ?? null);
		});
	}

	public function List(Request $request, Response $response, array $args)
	{
		return $this->Run($response, function () use ($request) {
			$q = $request->getQueryParams();
			$r = $this->Svc()->List(self::Filters($q), $q['tab'] ?? 'all', intval($q['page'] ?? 1), intval($q['per'] ?? 25), $q['sort'] ?? 'priority');
			$manage = self::Manage();
			$sup = self::Supervise();
			foreach ($r['rows'] as &$a)
			{
				$a['allowed'] = AlertCenterService::AllowedActions($a, $manage, GROCY_USER_ID, $sup);
				$a['flow_label'] = AlertCenterService::FLOW_STATUS[$a['flow_status']] ?? $a['flow_status'];
				$a['status_label'] = AlertCenterService::STATUS[$a['status']] ?? $a['status'];
			}
			return $r;
		});
	}

	public function Detail(Request $request, Response $response, array $args)
	{
		return $this->Run($response, function () use ($args) {
			$id = intval($args['id']);
			$this->Alert($id);
			$d = $this->Svc()->Detail($id);
			$d['allowed'] = AlertCenterService::AllowedActions($d['alert'], self::Manage(), GROCY_USER_ID, self::Supervise());
			$d['role'] = self::Manage() ? 'manager' : (self::Supervise() ? 'supervisor' : 'employee');
			return $d;
		});
	}

	public function Create(Request $request, Response $response, array $args)
	{
		return $this->Run($response, function () use ($request) {
			$b = $this->Body($request);
			if (!DairyAccess::CanOpen('/alerts'))
			{
				throw new \Exception('No tiene permiso para crear alertas', 403);
			}
			if (!empty($b['branch_id']) && !DairyAccess::CanSeeBranch(intval($b['branch_id'])))
			{
				throw new \Exception('No tiene acceso a esa sucursal', 403);
			}
			// Sin sucursal = alerta de red: solo un gestor sin restricción de sucursal
			if (empty($b['branch_id']) && (DairyAccess::AllowedBranches() !== null || !self::Manage()))
			{
				throw new \Exception('Seleccione la sucursal de la alerta');
			}
			if (!empty($b['owner_user_id']) && intval($b['owner_user_id']) !== GROCY_USER_ID && !self::Manage())
			{
				throw new \Exception('Solo un gestor de alertas asigna a otra persona', 403);
			}
			$r = $this->Svc()->Create($b, GROCY_USER_ID);
			return $r + ['success' => true, 'message' => $r['created'] ? 'Alerta ALR-' . $r['id'] . ' creada' : 'La alerta ya existía (ALR-' . $r['id'] . ')'];
		});
	}

	private function DoAction(int $id, string $action, array $b): array
	{
		$svc = $this->Svc();
		$a = $this->Alert($id);
		$manage = self::Manage();
		$sup = self::Supervise();
		if ((in_array($action, self::MANAGE_ACTIONS, true) || $action === 'prevent') && !$manage && !($sup && in_array($action, self::SUPERVISE_ACTIONS, true)))
		{
			throw new \Exception('Acción reservada a gestores de alertas', 403);
		}
		if (in_array($action, self::OWNER_ACTIONS, true) && $a['owner_user_id'] && intval($a['owner_user_id']) !== GROCY_USER_ID && !$manage && !($sup && $action === 'investigate'))
		{
			throw new \Exception('Solo el responsable asignado o un gestor de alertas puede atenderla', 403);
		}
		// Validar el cierre de una alerta crítica o de seguridad alimentaria es exclusivo del gestor (manager)
		if ($action === 'decide' && !$manage)
		{
			$full = $svc->Get($id);
			if ($full['priority'] === 'P1' || intval($full['food_safety']))
			{
				throw new \Exception('El cierre de una alerta crítica lo valida un gestor de alertas', 403);
			}
		}
		$note = isset($b['note']) ? strval($b['note']) : null;
		$yes = fn($k) => !empty($b[$k]) && $b[$k] !== 'false' && $b[$k] !== '0';
		switch ($action)
		{
			case 'assign':
				$svc->Assign($id, intval($b['owner_user_id'] ?? 0), GROCY_USER_ID);
				return ['message' => 'Responsable asignado'];
			case 'start':
				$svc->Start($id, GROCY_USER_ID);
				return ['message' => 'Alerta en ejecución'];
			case 'investigate':
				$svc->Investigate($id, $note, GROCY_USER_ID);
				return ['message' => 'Investigación iniciada'];
			case 'wait_info':
				$svc->WaitInfo($id, $note, GROCY_USER_ID);
				return ['message' => 'En espera de información'];
			case 'request_approval':
				$svc->RequestApproval($id, $note, GROCY_USER_ID);
				return ['message' => 'Aprobación solicitada'];
			case 'decide_request':
				$svc->DecideRequest($id, $yes('approve'), $note, GROCY_USER_ID);
				return ['message' => $yes('approve') ? 'Aprobado: vuelve a ejecución' : 'Rechazado'];
			case 'resolve':
				$next = $svc->Resolve($id, strval($note), GROCY_USER_ID, array_intersect_key($b, array_flip(['outcome', 'root_cause', 'final_value'])));
				return ['status' => $next, 'message' => $next === 'closed' ? 'Alerta cerrada' : 'Resuelta: pendiente de verificación por otra persona'];
			case 'decide':
				$svc->Decide($id, $yes('approve'), $note, GROCY_USER_ID);
				return ['message' => $yes('approve') ? 'Cierre verificado' : 'Cierre rechazado: vuelve a ejecución'];
			case 'dismiss':
				$svc->Dismiss($id, strval($note), GROCY_USER_ID);
				return ['message' => 'Alerta descartada'];
			case 'reopen':
				$svc->Reopen($id, $note, GROCY_USER_ID);
				return ['message' => 'Alerta reabierta'];
			case 'priority':
				$svc->SetPriority($id, strval($b['priority'] ?? ''), $note, GROCY_USER_ID, $b['category'] ?? null, $b['alert_type'] ?? null);
				return ['message' => 'Clasificación actualizada'];
			case 'step':
				$step = DatabaseService::GetInstance()->GetDbConnectionRaw()->query('SELECT alert_id FROM dairy_alert_steps WHERE id = ' . intval($b['step_id'] ?? 0))->fetchColumn();
				if (intval($step) !== $id)
				{
					throw new \Exception('El paso no pertenece a esta alerta');
				}
				if ($a['owner_user_id'] && intval($a['owner_user_id']) !== GROCY_USER_ID && !$manage && !$sup)
				{
					throw new \Exception('Solo el responsable asignado ejecuta el procedimiento', 403);
				}
				$svc->CompleteStep(intval($b['step_id']), ($b['status'] ?? 'done') === 'skipped' ? 'skipped' : 'done', $note, GROCY_USER_ID);
				return ['message' => ($b['status'] ?? 'done') === 'skipped' ? 'Paso omitido con motivo' : 'Paso completado'];
			case 'prevent':
				$pid = $svc->CreatePrevention($id, $b, GROCY_USER_ID);
				return ['id' => $pid, 'message' => "Plan preventivo PRV-$pid creado"];
			case 'escalate':
				$svc->Escalate($id, $note, GROCY_USER_ID);
				return ['message' => 'Alerta escalada'];
			case 'acknowledge':
				$svc->Acknowledge($id, GROCY_USER_ID);
				return ['message' => 'Lectura confirmada'];
			case 'help':
				$svc->RequestHelp($id, $note, GROCY_USER_ID);
				return ['message' => 'Solicitud de ayuda enviada'];
			case 'comment':
				$svc->Comment($id, strval($note), GROCY_USER_ID);
				return ['message' => 'Comentario agregado'];
			case 'plan':
				$aid = $svc->AddAction($id, $b, GROCY_USER_ID);
				return ['id' => $aid, 'message' => 'Acción correctiva agregada'];
			case 'task':
				return MobileWorkService::GetInstance()->TaskFromAlert($id, intval($b['owner_user_id'] ?? $b['user_id'] ?? 0), isset($b['due']) ? strval($b['due']) : null, GROCY_USER_ID);
		}
		throw new \Exception('Acción no válida');
	}

	public function Action(Request $request, Response $response, array $args)
	{
		return $this->Run($response, fn() => ['success' => true] + $this->DoAction(intval($args['id']), $args['action'], $this->Body($request)));
	}

	// Series de los gráficos (evolución y riesgo económico) con los mismos filtros de la página
	public function Series(Request $request, Response $response, array $args)
	{
		return $this->Run($response, function () use ($request) {
			if (!DairyAccess::CanOpen('/alerts'))
			{
				throw new \Exception('No tiene permiso para ver el tablero de alertas', 403);
			}
			$q = $request->getQueryParams();
			$f = self::Filters($q);
			unset($f['status']);
			$range = ($q['range'] ?? '30') === 'year' ? 'year' : intval($q['range'] ?? 30);
			return ($q['kind'] ?? '') === 'risk' ? $this->Svc()->RiskTrend($f, $range) : $this->Svc()->Evolution($f, $range);
		});
	}

	public function VerifyPrevention(Request $request, Response $response, array $args)
	{
		return $this->Run($response, function () use ($request, $args) {
			if (!self::Manage() && !self::Supervise())
			{
				throw new \Exception('Solo un supervisor o gestor verifica la efectividad', 403);
			}
			$src = DatabaseService::GetInstance()->GetDbConnectionRaw()->query('SELECT source_alert_id FROM dairy_alert_prevention WHERE id = ' . intval($args['pid']))->fetchColumn();
			if (!$src)
			{
				throw new \Exception('Plan no encontrado', 404);
			}
			$this->Alert(intval($src));
			$b = $this->Body($request);
			$ok = !empty($b['effective']) && $b['effective'] !== '0';
			$this->Svc()->VerifyPrevention(intval($args['pid']), $ok, $b['note'] ?? null, GROCY_USER_ID);
			return ['success' => true, 'message' => $ok ? 'Plan verificado como efectivo' : 'Plan marcado como no efectivo'];
		});
	}

	// Operaciones masivas: cada alerta se procesa por separado con sus propias validaciones (una que falla no detiene las demás)
	public function Bulk(Request $request, Response $response, array $args)
	{
		return $this->Run($response, function () use ($request) {
			$b = $this->Body($request);
			$action = strval($b['action'] ?? '');
			if (!in_array($action, ['assign', 'start', 'investigate', 'escalate', 'priority', 'dismiss', 'comment', 'acknowledge'], true))
			{
				throw new \Exception('Acción masiva no permitida');
			}
			if (!self::Manage() && !in_array($action, ['comment', 'acknowledge'], true))
			{
				throw new \Exception('Las acciones masivas son para gestores de alertas', 403);
			}
			$ids = array_slice(array_unique(array_map('intval', (array) ($b['ids'] ?? []))), 0, 200);
			if (!$ids)
			{
				throw new \Exception('Seleccione al menos una alerta');
			}
			$ok = [];
			$errors = [];
			foreach ($ids as $id)
			{
				try
				{
					$this->DoAction($id, $action, $b);
					$ok[] = $id;
				}
				catch (\Exception $ex)
				{
					$errors[] = ['id' => $id, 'error' => $ex->getMessage()];
				}
			}
			return ['success' => count($errors) === 0, 'ok' => $ok, 'errors' => $errors, 'message' => count($ok) . ' alerta(s) actualizada(s)' . ($errors ? ' · ' . count($errors) . ' con error' : '')];
		});
	}

	public function CompleteAction(Request $request, Response $response, array $args)
	{
		return $this->Run($response, function () use ($request, $args) {
			$b = $this->Body($request);
			$act = DatabaseService::GetInstance()->GetDbConnectionRaw()->query('SELECT alert_id, owner_user_id FROM dairy_alert_actions WHERE id = ' . intval($args['actionId']))->fetch(\PDO::FETCH_ASSOC);
			if (!$act)
			{
				throw new \Exception('Acción no encontrada', 404);
			}
			$this->Alert(intval($act['alert_id']));
			if ($act['owner_user_id'] && intval($act['owner_user_id']) !== GROCY_USER_ID && !self::Manage())
			{
				throw new \Exception('Solo el responsable de la acción o un gestor la cierra', 403);
			}
			$done = !isset($b['done']) || ($b['done'] !== false && $b['done'] !== 'false' && $b['done'] !== '0');
			$this->Svc()->CompleteAction(intval($args['actionId']), $b['result'] ?? null, $done, GROCY_USER_ID);
			return ['success' => true, 'message' => $done ? 'Acción completada' : 'Acción cancelada'];
		});
	}

	public function UploadEvidence(Request $request, Response $response, array $args)
	{
		return $this->Run($response, function () use ($request, $args) {
			$id = intval($args['id']);
			$this->Alert($id);
			$file = $request->getUploadedFiles()['file'] ?? null;
			if (!$file || $file->getError() !== UPLOAD_ERR_OK)
			{
				throw new \Exception('No se recibió el archivo');
			}
			if ($file->getSize() > 10 * 1024 * 1024)
			{
				throw new \Exception('El archivo supera 10 MB');
			}
			// Tipo por contenido, no por nombre: imágenes y PDF
			$mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer((string) $file->getStream());
			$types = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif', 'image/heic' => 'heic', 'image/heif' => 'heic', 'application/pdf' => 'pdf'];
			if (!isset($types[$mime]))
			{
				throw new \Exception('Formato no admitido (JPG, PNG, WEBP, GIF, HEIC o PDF)');
			}
			$dir = GROCY_DATAPATH . '/storage/alerts';
			if (!is_dir($dir))
			{
				mkdir($dir, 0777, true);
			}
			$name = 'alr' . $id . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $types[$mime];
			$file->moveTo($dir . '/' . $name);
			$b = $request->getParsedBody() ?? [];
			$eid = $this->Svc()->AddEvidence($id, $name, $file->getClientFilename(), $mime, intval($file->getSize()), $b['note'] ?? null, GROCY_USER_ID);
			return ['success' => true, 'id' => $eid, 'message' => 'Evidencia adjuntada'];
		});
	}

	public function ServeEvidence(Request $request, Response $response, array $args)
	{
		try
		{
			$e = $this->Svc()->EvidenceFile(intval($args['evidenceId']));
			if (!$e)
			{
				throw new \Exception('Evidencia no encontrada', 404);
			}
			$this->Alert(intval($e['alert_id']));
			$path = GROCY_DATAPATH . '/storage/alerts/' . basename($e['file_name']);
			if (!is_file($path))
			{
				throw new \Exception('El archivo ya no está disponible', 404);
			}
			$response->getBody()->write(file_get_contents($path));
			return $response->withHeader('Content-Type', $e['mime'])->withHeader('Cache-Control', 'private, max-age=86400')
				->withHeader('Content-Disposition', 'inline; filename="' . str_replace('"', '', $e['original_name'] ?: $e['file_name']) . '"');
		}
		catch (\Exception $ex)
		{
			return $this->GenericErrorResponse($response, $ex->getMessage(), intval($ex->getCode()) ?: 400);
		}
	}

	public function Notifications(Request $request, Response $response, array $args)
	{
		return $this->Run($response, fn() => $this->Svc()->UserNotifications(GROCY_USER_ID));
	}

	public function NotificationsRead(Request $request, Response $response, array $args)
	{
		return $this->Run($response, function () use ($request) {
			$b = $this->Body($request);
			return ['success' => true, 'updated' => $this->Svc()->MarkRead(GROCY_USER_ID, !empty($b['ids']) ? (array) $b['ids'] : null)];
		});
	}

	public function Escalations(Request $request, Response $response, array $args)
	{
		return $this->Run($response, function () use ($request) {
			User::CheckPermission($request, User::PERMISSION_ALERTS_MANAGE);
			return $this->Svc()->RunEscalations();
		});
	}

	public function Report(Request $request, Response $response, array $args)
	{
		return $this->Run($response, function () use ($request) {
			if (!DairyAccess::CanOpen('/alerts'))
			{
				throw new \Exception('No tiene permiso para ver reportes de alertas', 403);
			}
			$q = $request->getQueryParams();
			$per = AlertCenterService::Period($q['period'] ?? '30d', $q['from'] ?? null, $q['to'] ?? null);
			return $this->Svc()->Report(self::Filters($q), $q['dim'] ?? 'branch', $per['from'], $per['to']);
		});
	}

	// CSV (UTF-8 con BOM: abre en Excel) de todas las alertas que cumplen los filtros, no solo la página visible
	public function Export(Request $request, Response $response, array $args)
	{
		try
		{
			if (!DairyAccess::CanOpen('/alerts'))
			{
				throw new \Exception('No tiene permiso para exportar alertas', 403);
			}
			$q = $request->getQueryParams();
			$rows = $this->Svc()->List(self::Filters($q), $q['tab'] ?? 'all', 1, 200, $q['sort'] ?? 'priority');
			$all = $rows['rows'];
			for ($p = 2; $p <= min($rows['pages'], 25); $p++)
			{
				$all = array_merge($all, $this->Svc()->List(self::Filters($q), $q['tab'] ?? 'all', $p, 200, $q['sort'] ?? 'priority')['rows']);
			}
			$out = fopen('php://temp', 'w+');
			fwrite($out, "\xEF\xBB\xBF");
			fputcsv($out, ['ID', 'Prioridad', 'Motivo prioridad', 'Categoría', 'Tipo', 'Título', 'Detalle', 'Sucursal', 'Departamento', 'Producto', 'Impacto $', 'Tipo de impacto', 'Estado', 'Responsable', 'Creada', 'Plazo SLA', 'Vencida', 'Resuelta', 'Resolución', 'Escalamiento', 'Reaperturas', 'Regla'], ';', '"', '\\');
			foreach ($all as $a)
			{
				fputcsv($out, ['ALR-' . $a['id'], $a['priority'], $a['priority_reason'], AlertCenterService::CATEGORIES[$a['category']][0] ?? $a['category'], AlertCenterService::TYPES[$a['alert_type']][0] ?? $a['alert_type'],
					$a['title'], $a['detail'], $a['branch_name'] ?? 'Red', $a['department'], $a['product_name'], $a['value'] !== null ? number_format(floatval($a['value']), 2, ',', '') : '', AlertCenterService::IMPACT_KINDS[$a['impact_kind']] ?? $a['impact_kind'],
					AlertCenterService::STATUS[$a['status']] ?? $a['status'], $a['owner_name'], $a['created_at'], $a['due_at'], intval($a['overdue']) ? 'Sí' : 'No', $a['resolved_at'], $a['resolution'], $a['escalation_level'], $a['reopened_count'], $a['rule_name']], ';', '"', '\\');
			}
			rewind($out);
			$response->getBody()->write(stream_get_contents($out));
			return $response->withHeader('Content-Type', 'text/csv; charset=utf-8')->withHeader('Content-Disposition', 'attachment; filename="alertas_' . date('Ymd_Hi') . '.csv"');
		}
		catch (\Exception $ex)
		{
			return $this->GenericErrorResponse($response, $ex->getMessage(), intval($ex->getCode()) ?: 400);
		}
	}

	public function Views(Request $request, Response $response, array $args)
	{
		return $this->Run($response, fn() => $this->Svc()->Views(GROCY_USER_ID));
	}

	public function SaveView(Request $request, Response $response, array $args)
	{
		return $this->Run($response, function () use ($request) {
			$b = $this->Body($request);
			return ['success' => true, 'id' => $this->Svc()->SaveView(GROCY_USER_ID, strval($b['name'] ?? ''), strval($b['query'] ?? '')), 'message' => 'Vista guardada'];
		});
	}

	public function DeleteView(Request $request, Response $response, array $args)
	{
		return $this->Run($response, function () use ($args) {
			$this->Svc()->DeleteView(GROCY_USER_ID, intval($args['viewId']));
			return ['success' => true, 'message' => 'Vista eliminada'];
		});
	}

	/* ---------- Reglas de detección y automatizaciones (gestores de alertas) */

	public function AutomationConfig(Request $request, Response $response, array $args)
	{
		return $this->Run($response, function () use ($request) {
			User::CheckPermission($request, User::PERMISSION_ALERTS_MANAGE);
			$db = DatabaseService::GetInstance()->GetDbConnectionRaw();
			$auto = AutomationEngineService::GetInstance();
			return [
				'detection' => $this->Svc()->GetRules(),
				'automations' => $auto->Rules(),
				'executions' => $auto->Executions(null, 50),
				'events' => AutomationEngineService::EVENTS,
				'actions' => AutomationEngineService::ACTIONS,
				'branches' => $db->query('SELECT id, name FROM branches WHERE active = 1 ORDER BY name')->fetchAll(\PDO::FETCH_ASSOC),
				'users' => $db->query("SELECT u.id, IFNULL(NULLIF(TRIM(IFNULL(u.first_name, '') || ' ' || IFNULL(u.last_name, '')), ''), u.username) AS name FROM users u
					WHERE EXISTS (SELECT 1 FROM user_permissions_resolved p WHERE p.user_id = u.id AND p.permission_name IN ('ADMIN', 'ALERTS_MANAGE', 'WORKFORCE_SCHEDULE_MANAGE')) ORDER BY name")->fetchAll(\PDO::FETCH_ASSOC)
			];
		});
	}

	public function AutomationSave(Request $request, Response $response, array $args)
	{
		return $this->Run($response, function () use ($request, $args) {
			User::CheckPermission($request, User::PERMISSION_ALERTS_MANAGE);
			$id = AutomationEngineService::GetInstance()->SaveRule(!empty($args['ruleId']) ? intval($args['ruleId']) : null, $this->Body($request), GROCY_USER_ID);
			return ['id' => $id, 'message' => 'Automatización guardada'];
		});
	}

	public function AutomationExecutions(Request $request, Response $response, array $args)
	{
		return $this->Run($response, function () use ($request, $args) {
			User::CheckPermission($request, User::PERMISSION_ALERTS_MANAGE);
			$auto = AutomationEngineService::GetInstance();
			$id = intval($args['ruleId']);
			return ['executions' => $auto->Executions($id, 100), 'changes' => $auto->RuleChanges($id)];
		});
	}

	public function AutomationRetry(Request $request, Response $response, array $args)
	{
		return $this->Run($response, function () use ($request, $args) {
			User::CheckPermission($request, User::PERMISSION_ALERTS_MANAGE);
			return AutomationEngineService::GetInstance()->Retry(intval($args['execId']));
		});
	}
}
