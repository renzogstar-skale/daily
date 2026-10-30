<?php

namespace Grocy\Services;

/**
 * Auditorías de tienda multisucursal: plantillas de checklist ponderadas (ítems críticos), ejecución por sucursal,
 * puntaje = peso cumplido / peso evaluado, y cada incumplimiento abre una tarea correctiva en Piso de Tienda
 * (workforce_store_tasks, origen 'audit') con plazo de 24 h si es crítico o 72 h si no; así el escalamiento existente
 * (Etapa 17) la lleva a /today si vence sin cerrarse.
 */
class StoreAuditService extends BaseService
{
	private function Db(): \PDO
	{
		return DatabaseService::GetInstance()->GetDbConnectionRaw();
	}

	private function Rows(string $sql, array $p = []): array
	{
		$stmt = $this->Db()->prepare($sql);
		$stmt->execute($p);
		return $stmt->fetchAll(\PDO::FETCH_ASSOC);
	}

	private function Row(string $sql, array $p = [])
	{
		$stmt = $this->Db()->prepare($sql);
		$stmt->execute($p);
		return $stmt->fetch(\PDO::FETCH_ASSOC);
	}

	public function GetTemplates(bool $activeOnly = true): array
	{
		$templates = $this->Rows('SELECT * FROM audit_templates' . ($activeOnly ? ' WHERE active = 1' : '') . ' ORDER BY name');
		foreach ($templates as &$t)
		{
			$t['items'] = $this->Rows('SELECT * FROM audit_template_items WHERE template_id = :t AND active = 1 ORDER BY sort_order, id', [':t' => $t['id']]);
		}
		return $templates;
	}

	public function SaveTemplate(array $d): int
	{
		$name = trim($d['name'] ?? '');
		$items = array_values(array_filter($d['items'] ?? [], fn($i) => trim($i['question'] ?? '') !== ''));
		if ($name === '' || count($items) === 0)
		{
			throw new \Exception('La plantilla necesita nombre y al menos un punto a evaluar');
		}
		$db = $this->Db();
		$db->beginTransaction();
		try
		{
			$id = intval($d['id'] ?? 0);
			if ($id > 0)
			{
				$db->prepare('UPDATE audit_templates SET name = ?, area = ? WHERE id = ?')->execute([$name, $d['area'] ?? null, $id]);
			}
			else
			{
				$db->prepare("INSERT INTO audit_templates (name, area, created_at) VALUES (?, ?, ?)")->execute([$name, $d['area'] ?? null, date('Y-m-d H:i:s')]);
				$id = intval($db->lastInsertId());
			}
			// Los ítems retirados se desactivan (las auditorías anteriores conservan su respuesta)
			$keep = [];
			foreach ($items as $n => $i)
			{
				$weight = max(0.5, floatval($i['weight'] ?? 1));
				$crit = !empty($i['is_critical']) && $i['is_critical'] !== 'false' ? 1 : 0;
				if (!empty($i['id']))
				{
					$db->prepare('UPDATE audit_template_items SET question = ?, weight = ?, is_critical = ?, sort_order = ?, active = 1 WHERE id = ? AND template_id = ?')->execute([trim($i['question']), $weight, $crit, $n + 1, intval($i['id']), $id]);
					$keep[] = intval($i['id']);
				}
				else
				{
					$db->prepare('INSERT INTO audit_template_items (template_id, question, weight, is_critical, sort_order) VALUES (?, ?, ?, ?, ?)')->execute([$id, trim($i['question']), $weight, $crit, $n + 1]);
					$keep[] = intval($db->lastInsertId());
				}
			}
			$db->exec('UPDATE audit_template_items SET active = 0 WHERE template_id = ' . $id . ' AND id NOT IN (' . implode(',', $keep) . ')');
			$db->commit();
			return $id;
		}
		catch (\Throwable $e)
		{
			$db->rollBack();
			throw $e;
		}
	}

	public function StartAudit(int $templateId, int $branchId, int $userId): array
	{
		if ($branchId <= 0 || !$this->Row('SELECT id FROM branches WHERE id = :b', [':b' => $branchId]))
		{
			throw new \Exception('Seleccione la sucursal a auditar');
		}
		if (!$this->Row('SELECT id FROM audit_templates WHERE id = :t AND active = 1', [':t' => $templateId]))
		{
			throw new \Exception('Seleccione una plantilla activa');
		}
		$open = $this->Row("SELECT id, audit_code FROM store_audits WHERE template_id = :t AND branch_id = :b AND status = 'in_progress'", [':t' => $templateId, ':b' => $branchId]);
		if ($open)
		{
			return ['id' => intval($open['id']), 'audit_code' => $open['audit_code'], 'resumed' => true];
		}
		$db = $this->Db();
		$next = intval($db->query('SELECT IFNULL(MAX(id), 0) + 1 FROM store_audits')->fetchColumn());
		$code = 'AUD-' . date('Y') . '-' . str_pad((string) $next, 5, '0', STR_PAD_LEFT);
		$db->prepare("INSERT INTO store_audits (audit_code, template_id, branch_id, status, auditor_user_id, started_at) VALUES (?, ?, ?, 'in_progress', ?, ?)")->execute([$code, $templateId, $branchId, $userId, date('Y-m-d H:i:s')]);
		return ['id' => intval($db->lastInsertId()), 'audit_code' => $code, 'resumed' => false];
	}

	/**
	 * Guarda respuestas (borrador) y, con $complete, cierra la auditoría: exige responder todo, comentario en cada
	 * incumplimiento, calcula el puntaje y crea las tareas correctivas.
	 */
	public function SaveAnswers(int $auditId, array $answers, bool $complete, ?string $notes, int $userId): array
	{
		$audit = $this->Row('SELECT * FROM store_audits WHERE id = :id', [':id' => $auditId]);
		if (!$audit)
		{
			throw new \Exception('Auditoría no encontrada');
		}
		if ($audit['status'] !== 'in_progress')
		{
			throw new \Exception('La auditoría ya está cerrada');
		}
		$items = [];
		foreach ($this->Rows('SELECT * FROM audit_template_items WHERE template_id = :t AND active = 1', [':t' => $audit['template_id']]) as $i)
		{
			$items[intval($i['id'])] = $i;
		}
		$db = $this->Db();
		$upsert = $db->prepare('INSERT INTO store_audit_answers (audit_id, item_id, result, comment) VALUES (:a, :i, :r, :c) ON CONFLICT(audit_id, item_id) DO UPDATE SET result = excluded.result, comment = excluded.comment');
		foreach ($answers as $a)
		{
			$itemId = intval($a['item_id'] ?? 0);
			$result = $a['result'] ?? '';
			if (!isset($items[$itemId]) || !in_array($result, ['pass', 'fail', 'na'], true))
			{
				continue;
			}
			$upsert->execute([':a' => $auditId, ':i' => $itemId, ':r' => $result, ':c' => trim($a['comment'] ?? '') ?: null]);
		}
		if (!$complete)
		{
			return ['success' => true, 'message' => 'Avance guardado'];
		}

		$saved = [];
		foreach ($this->Rows('SELECT * FROM store_audit_answers WHERE audit_id = :a', [':a' => $auditId]) as $s)
		{
			$saved[intval($s['item_id'])] = $s;
		}
		$missing = array_diff(array_keys($items), array_keys($saved));
		if (count($missing) > 0)
		{
			throw new \Exception('Faltan ' . count($missing) . ' punto(s) por evaluar');
		}
		foreach ($saved as $iid => $s)
		{
			if ($s['result'] === 'fail' && mb_strlen(trim($s['comment'] ?? '')) < 3)
			{
				throw new \Exception('Describa el hallazgo de: ' . $items[$iid]['question']);
			}
		}

		$evaluated = 0.0;
		$passed = 0.0;
		$critical = 0;
		$fails = [];
		foreach ($saved as $iid => $s)
		{
			if (!isset($items[$iid]) || $s['result'] === 'na')
			{
				continue;
			}
			$w = floatval($items[$iid]['weight']);
			$evaluated += $w;
			if ($s['result'] === 'pass')
			{
				$passed += $w;
			}
			else
			{
				$critical += intval($items[$iid]['is_critical']);
				$fails[] = [$s, $items[$iid]];
			}
		}
		$score = $evaluated > 0 ? round($passed / $evaluated * 100, 1) : 100.0;

		$db->beginTransaction();
		try
		{
			$task = $db->prepare("INSERT INTO workforce_store_tasks (branch_id, task_type, assigned_role, title, description, due_time, status, source_type, source_id, created_timestamp) VALUES (?, 'audit_corrective', 'supervisor', ?, ?, ?, 'pending', 'audit', ?, ?)");
			$link = $db->prepare('UPDATE store_audit_answers SET task_id = ? WHERE id = ?');
			foreach ($fails as [$s, $item])
			{
				$due = date('Y-m-d H:i:s', time() + 3600 * (intval($item['is_critical']) ? 24 : 72));
				$task->execute([$audit['branch_id'], ($item['is_critical'] ? 'CRÍTICO · ' : '') . 'Corregir: ' . $item['question'], $audit['audit_code'] . ' · ' . $s['comment'], $due, $auditId, date('Y-m-d H:i:s')]);
				$link->execute([intval($db->lastInsertId()), $s['id']]);
			}
			$db->prepare("UPDATE store_audits SET status = 'completed', completed_at = ?, score = ?, critical_failures = ?, notes = ? WHERE id = ? AND status = 'in_progress'")
				->execute([date('Y-m-d H:i:s'), $score, $critical, $notes, $auditId]);
			$db->commit();
		}
		catch (\Throwable $e)
		{
			$db->rollBack();
			throw $e;
		}
		return ['success' => true, 'score' => $score, 'critical_failures' => $critical, 'corrective_tasks' => count($fails),
			'message' => "Auditoría cerrada: $score %" . (count($fails) ? ' · ' . count($fails) . ' tarea(s) correctiva(s) asignada(s)' : ' · sin hallazgos')];
	}

	public function GetAudits(?array $allowedBranches = null, int $limit = 100): array
	{
		$rows = $this->Rows("SELECT a.*, t.name AS template_name, b.name AS branch_name,
				IFNULL(NULLIF(TRIM(IFNULL(u.first_name, '') || ' ' || IFNULL(u.last_name, '')), ''), u.username) AS auditor,
				(SELECT COUNT(*) FROM store_audit_answers x WHERE x.audit_id = a.id AND x.result = 'fail') AS fails,
				(SELECT COUNT(*) FROM store_audit_answers x JOIN workforce_store_tasks wt ON wt.id = x.task_id WHERE x.audit_id = a.id AND wt.status = 'pending') AS open_tasks
			FROM store_audits a LEFT JOIN audit_templates t ON t.id = a.template_id LEFT JOIN branches b ON b.id = a.branch_id LEFT JOIN users u ON u.id = a.auditor_user_id
			ORDER BY (a.status = 'in_progress') DESC, a.id DESC LIMIT " . intval($limit));
		return $allowedBranches === null ? $rows : array_values(array_filter($rows, fn($r) => in_array(intval($r['branch_id']), $allowedBranches, true)));
	}

	public function GetAudit(int $auditId): array
	{
		$audit = $this->Row('SELECT a.*, t.name AS template_name, b.name AS branch_name FROM store_audits a LEFT JOIN audit_templates t ON t.id = a.template_id LEFT JOIN branches b ON b.id = a.branch_id WHERE a.id = :id', [':id' => $auditId]);
		if (!$audit)
		{
			throw new \Exception('Auditoría no encontrada');
		}
		$audit['items'] = $this->Rows("SELECT i.id AS item_id, i.question, i.weight, i.is_critical, x.result, x.comment, x.task_id, wt.status AS task_status
			FROM audit_template_items i LEFT JOIN store_audit_answers x ON x.item_id = i.id AND x.audit_id = :a LEFT JOIN workforce_store_tasks wt ON wt.id = x.task_id
			WHERE i.template_id = :t AND (i.active = 1 OR x.id IS NOT NULL) ORDER BY i.sort_order, i.id", [':a' => $auditId, ':t' => $audit['template_id']]);
		return $audit;
	}

	// Comparativo multisucursal de los últimos $days días
	public function BranchComparison(int $days = 90, ?array $allowedBranches = null): array
	{
		$since = date('Y-m-d', strtotime("-$days days"));
		$rows = $this->Rows("SELECT b.id AS branch_id, b.name AS branch_name,
				COUNT(a.id) AS audits, ROUND(AVG(a.score), 1) AS avg_score,
				(SELECT a2.score FROM store_audits a2 WHERE a2.branch_id = b.id AND a2.status = 'completed' ORDER BY a2.completed_at DESC LIMIT 1) AS last_score,
				(SELECT MAX(a2.completed_at) FROM store_audits a2 WHERE a2.branch_id = b.id AND a2.status = 'completed') AS last_audit,
				IFNULL(SUM(a.critical_failures), 0) AS critical_failures,
				(SELECT COUNT(*) FROM workforce_store_tasks wt WHERE wt.branch_id = b.id AND wt.source_type = 'audit' AND wt.status = 'pending') AS open_tasks
			FROM branches b LEFT JOIN store_audits a ON a.branch_id = b.id AND a.status = 'completed' AND date(a.completed_at) >= :s
			WHERE b.active = 1 GROUP BY b.id ORDER BY avg_score IS NULL, avg_score", [':s' => $since]);
		return $allowedBranches === null ? $rows : array_values(array_filter($rows, fn($r) => in_array(intval($r['branch_id']), $allowedBranches, true)));
	}
}
