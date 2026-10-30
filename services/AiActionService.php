<?php

namespace Grocy\Services;

use Grocy\Controllers\Users\User;

/**
 * Acciones que Arturito puede proponer. Regla: la IA solo propone; una persona con el permiso de la acción
 * revisa la vista previa (qué se creará, con qué datos) y confirma. Lo creado sigue su flujo normal:
 * la orden queda en borrador (otra aprobación de compras), el plan y la tarea quedan pendientes.
 */
class AiActionService extends BaseService
{
	const TYPES = [
		'draft_po' => ['label' => 'Generar órdenes de compra en borrador', 'permission' => User::PERMISSION_PROCUREMENT_MANAGE],
		'action_plan' => ['label' => 'Abrir plan de acción de merma', 'permission' => User::PERMISSION_WASTE_APPROVE],
		'fefo_task' => ['label' => 'Crear tarea FEFO en piso de tienda', 'permission' => User::PERMISSION_ALERTS_MANAGE],
	];

	private function Db(): \PDO
	{
		return DatabaseService::GetInstance()->GetDbConnectionRaw();
	}

	public static function Permission(string $type): string
	{
		if (!isset(self::TYPES[$type]))
		{
			throw new \Exception('Acción no reconocida');
		}
		return self::TYPES[$type]['permission'];
	}

	/** Vista previa sin escribir nada: exactamente lo que se creará. */
	public function Preview(string $type, ?int $branchId, array $params = []): array
	{
		$branchId = $branchId ?: 1;
		switch (self::Permission($type) ? $type : '')
		{
			case 'draft_po':
				$mrp = MrpService::GetInstance()->CalculateMrp($branchId);
				$need = array_values(array_filter($mrp['items'], fn($i) => ($i['status'] ?? '') === 'reorder_needed' && floatval($i['suggested_order']) > 0 && !empty($i['supplier_id'])));
				$suppliers = array_unique(array_column($need, 'supplier_id'));
				return [
					'type' => $type, 'label' => self::TYPES[$type]['label'], 'branch_id' => $branchId,
					'summary' => count($need) . ' producto(s) en ' . count($suppliers) . ' orden(es) en borrador · costo estimado $' . number_format(array_sum(array_column($need, 'estimated_cost')), 2),
					'lines' => array_map(fn($i) => $i['product_name'] . ': ' . $i['suggested_order'] . ' ' . $i['qu_name'] . ' · ' . $i['supplier_name'], array_slice($need, 0, 15)),
					'can_execute' => count($need) > 0,
					'note' => 'Las órdenes quedan en borrador: un usuario de compras debe aprobarlas y enviarlas en /mrp.'
				];
			case 'action_plan':
				[$from, $to] = [date('Y-m-01'), date('Y-m-d')];
				$st = $this->Db()->prepare("SELECT r.id, r.code || ' ' || r.name AS label, r.preventive_actions, SUM(w.total_cost) AS cost, COUNT(*) AS n FROM waste_records w JOIN waste_reasons r ON r.id = w.waste_reason_id
					WHERE w.status = 'approved' AND w.branch_id = :b AND date(w.created_timestamp) BETWEEN :f AND :t GROUP BY r.id ORDER BY cost DESC LIMIT 1");
				$st->execute([':b' => $branchId, ':f' => $from, ':t' => $to]);
				$r = $st->fetch(\PDO::FETCH_ASSOC);
				if (!$r)
				{
					return ['type' => $type, 'label' => self::TYPES[$type]['label'], 'branch_id' => $branchId, 'summary' => 'Sin merma aprobada este mes en la sucursal: no hay causal para el plan.', 'lines' => [], 'can_execute' => false];
				}
				return [
					'type' => $type, 'label' => self::TYPES[$type]['label'], 'branch_id' => $branchId, 'waste_reason_id' => intval($r['id']),
					'summary' => "Plan para la causal {$r['label']}: {$r['n']} registro(s), $" . number_format(floatval($r['cost']), 2) . " este mes",
					'lines' => ['Actividad: Reducir merma por ' . $r['label'], 'Medida preventiva: ' . ($r['preventive_actions'] ?: 'por definir'), 'Plazo: 7 días · prioridad alta · responsable: usted'],
					'can_execute' => true,
					'note' => 'El ahorro estimado queda en 0: lo declara el responsable al cerrar (no lo inventa la IA).'
				];
			case 'fefo_task':
				$st = $this->Db()->prepare("SELECT p.name, s.best_before_date AS d, SUM(s.amount) AS qty FROM stock s JOIN locations l ON l.id = s.location_id JOIN products p ON p.id = s.product_id
					WHERE l.branch_id = :b AND IFNULL(l.is_transit, 0) = 0 AND s.amount > 0 AND s.best_before_date BETWEEN date('now', 'localtime') AND date('now', 'localtime', '+3 days')
					GROUP BY s.product_id, s.best_before_date ORDER BY s.best_before_date LIMIT 20");
				$st->execute([':b' => $branchId]);
				$lots = $st->fetchAll(\PDO::FETCH_ASSOC);
				return [
					'type' => $type, 'label' => self::TYPES[$type]['label'], 'branch_id' => $branchId,
					'summary' => count($lots) . ' lote(s) vencen en ≤ 3 días: tarea de rotación FEFO (al frente, rebaja o traslado)',
					'lines' => array_map(fn($l) => "{$l['name']} · {$l['qty']} u. · vence {$l['d']}", $lots),
					'can_execute' => count($lots) > 0,
					'note' => 'La tarea queda pendiente para el equipo de la sucursal; la rebaja se autoriza en POS/Calidad.'
				];
		}
		throw new \Exception('Acción no reconocida');
	}

	/** Ejecuta tras confirmación explícita. El permiso lo valida el controlador antes de llamar. */
	public function Execute(string $type, ?int $branchId, bool $confirmed, int $userId, ?string $question = null): array
	{
		if (!$confirmed)
		{
			throw new \Exception('La acción requiere confirmación explícita');
		}
		$preview = $this->Preview($type, $branchId, []);
		if (empty($preview['can_execute']))
		{
			throw new \Exception('Nada que ejecutar: ' . $preview['summary']);
		}
		$b = $preview['branch_id'];
		$db = $this->Db();
		$db->prepare('INSERT INTO dairy_ai_actions (action_type, branch_id, params_json, question, status, user_id) VALUES (?, ?, ?, ?, ?, ?)')
			->execute([$type, $b, json_encode(['preview' => $preview['summary']], JSON_UNESCAPED_UNICODE), $question ? mb_substr($question, 0, 500) : null, 'running', $userId]);
		$logId = intval($db->lastInsertId());
		try
		{
			switch ($type)
			{
				case 'draft_po':
					$res = MrpService::GetInstance()->GenerateOrdersFromMrp($b, $userId);
					$result = ['message' => 'Órdenes en borrador creadas; apruébelas en /mrp', 'detail' => $res, 'href' => '/mrp'];
					break;
				case 'action_plan':
					$db->prepare("INSERT INTO waste_action_plans (activity_title, problem_description, follow_up_actions, waste_reason_id, branch_id, assigned_user_id, priority, start_date, due_date, status, cost_avoided_estimate)
						VALUES (?, ?, ?, ?, ?, ?, 'alta', ?, ?, 'in_progress', 0)")
						->execute([mb_substr(str_replace('Actividad: ', '', $preview['lines'][0]), 0, 200), $preview['summary'] . ' (propuesto por Arturito, confirmado por el usuario)', str_replace('Medida preventiva: ', '', $preview['lines'][1]), $preview['waste_reason_id'], $b, $userId, date('Y-m-d'), date('Y-m-d', strtotime('+7 days'))]);
					$result = ['message' => 'Plan de acción #' . $db->lastInsertId() . ' creado', 'id' => intval($db->lastInsertId()), 'href' => '/waste#tab-planes'];
					break;
				case 'fefo_task':
					$db->prepare("INSERT INTO workforce_store_tasks (branch_id, task_type, assigned_role, title, description, due_time, status, source_type, source_id, created_timestamp)
						VALUES (?, 'fefo_sweep', 'clerk', ?, ?, datetime('now', 'localtime', '+4 hours'), 'pending', 'ai', ?, datetime('now', 'localtime'))")
						->execute([$b, 'Rotación FEFO: ' . count($preview['lines']) . ' lote(s) por vencer', implode("\n", $preview['lines']), $logId]);
					$result = ['message' => 'Tarea TAR-' . $db->lastInsertId() . ' creada en Piso de Tienda', 'id' => intval($db->lastInsertId()), 'href' => '/workforce'];
					break;
				default:
					throw new \Exception('Acción no reconocida');
			}
			$db->prepare("UPDATE dairy_ai_actions SET status = 'executed', result_json = ? WHERE id = ?")->execute([json_encode($result, JSON_UNESCAPED_UNICODE), $logId]);
			return $result + ['log_id' => $logId];
		}
		catch (\Throwable $e)
		{
			$db->prepare("UPDATE dairy_ai_actions SET status = 'failed', error_message = ? WHERE id = ?")->execute([$e->getMessage(), $logId]);
			throw $e;
		}
	}

	public function History(int $limit = 20): array
	{
		return $this->Db()->query('SELECT a.*, u.username FROM dairy_ai_actions a LEFT JOIN users u ON u.id = a.user_id ORDER BY a.id DESC LIMIT ' . intval($limit))->fetchAll(\PDO::FETCH_ASSOC);
	}
}
