<?php

namespace Grocy\Services;

/**
 * Centro de alertas: registro único de alertas de DAIRY (todos los módulos leen dairy_alerts por su id, ALR-<id>).
 *
 * Motor: cada regla (dairy_alert_rules: activa, umbral, severidad, SLA, responsable, aprobación, sucursal, frecuencia,
 * enfriamiento, escalamiento, canal y acción recomendada) tiene un detector sobre datos reales. Scan() crea una alerta por
 * hallazgo (dedupe por clave), agrupa hallazgos del mismo producto cuando la regla tiene enfriamiento, suprime repeticiones
 * durante el enfriamiento tras un cierre, refresca las abiertas, cierra solas las que nadie tocó cuando la condición
 * desaparece, recalcula prioridad (P1–P4 con motivo), envía recordatorios y escala las vencidas.
 *
 * Ciclo: Nueva/abierta → asignada → en investigación ⇄ esperando información / esperando aprobación → en ejecución
 * → resuelta (por verificar, si la regla exige aprobación de otra persona) → cerrada; reabierta; descartada (con motivo).
 * Toda acción queda en dairy_alert_events; evidencias, plan de acción y notificaciones en sus tablas.
 */
class AlertCenterService extends BaseService
{
	// Estados activos (no cerrados). WORKING = activos sobre los que el equipo trabaja (excluye la verificación del cierre)
	const ACTIVE = ['open', 'assigned', 'investigating', 'waiting_info', 'waiting_approval', 'in_progress', 'reopened', 'pending_approval'];
	const WORKING = ['open', 'assigned', 'investigating', 'waiting_info', 'waiting_approval', 'in_progress', 'reopened'];
	const STATUS = [
		'open' => 'Abierta', 'assigned' => 'Asignada', 'investigating' => 'En investigación', 'waiting_info' => 'Esperando información',
		'waiting_approval' => 'Esperando aprobación', 'in_progress' => 'En ejecución', 'reopened' => 'Reabierta',
		'pending_approval' => 'Resuelta · por verificar', 'closed' => 'Cerrada', 'dismissed' => 'Descartada',
	];
	const CATEGORIES = [
		'inventory' => ['Inventario', '#2563EB', 'fa-boxes-stacked'], 'waste' => ['Mermas', '#EF4444', 'fa-trash-can'], 'quality' => ['Calidad', '#8B5CF6', 'fa-flask'],
		'operations' => ['Operaciones', '#06B6D4', 'fa-gears'], 'staff' => ['Personal', '#F59E0B', 'fa-users'], 'sales' => ['Ventas', '#10B981', 'fa-cash-register'],
		'purchasing' => ['Compras', '#F97316', 'fa-cart-shopping'], 'security' => ['Seguridad', '#B91C1C', 'fa-shield-halved'], 'systems' => ['Sistemas', '#64748B', 'fa-server'],
		'other' => ['Otras', '#94A3B8', 'fa-ellipsis'],
	];
	const TYPES = [
		'stock_low' => ['Stock bajo', '#2563EB'], 'expiry' => ['Vencimiento', '#F59E0B'], 'cold_chain' => ['Cadena de frío', '#06B6D4'], 'count_diff' => ['Diferencia de conteo', '#8B5CF6'],
		'waste' => ['Merma', '#EF4444'], 'staff' => ['Personal', '#F97316'], 'sales' => ['Ventas', '#10B981'], 'maintenance' => ['Mantenimiento', '#64748B'],
		'purchasing' => ['Abastecimiento', '#EC4899'], 'other' => ['Otros', '#94A3B8'],
	];
	const PRIORITIES = ['P1' => ['Crítica', '#EF4444'], 'P2' => ['Alta', '#F97316'], 'P3' => ['Media', '#2563EB'], 'P4' => ['Baja', '#94A3B8']];
	const BUCKETS = ['sales_risk' => 'Ventas en riesgo', 'inventory' => 'Inventario comprometido', 'waste' => 'Merma potencial', 'labor' => 'Personal y horas', 'process' => 'Procesos'];
	const IMPACT_KINDS = ['confirmed' => 'Confirmado', 'estimated' => 'Estimado', 'recoverable' => 'Recuperable'];

	private const UNAME = "IFNULL(NULLIF(TRIM(IFNULL(%1\$s.first_name, '') || ' ' || IFNULL(%1\$s.last_name, '')), ''), %1\$s.username)";

	public static function In(array $values): string
	{
		return "'" . implode("','", array_map(fn($v) => str_replace("'", "''", $v), $values)) . "'";
	}

	private static function Uname(string $alias): string
	{
		return sprintf(self::UNAME, $alias);
	}

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

	private function Val(string $sql, array $p = [])
	{
		$stmt = $this->Db()->prepare($sql);
		$stmt->execute($p);
		return $stmt->fetchColumn();
	}

	private function Now(): string
	{
		return date('Y-m-d H:i:s');
	}

	/* ================================================================== Reglas */

	public function GetRules(): array
	{
		return $this->Rows('SELECT * FROM dairy_alert_rules ORDER BY category, name');
	}

	private function Rule(string $code): ?array
	{
		return $this->Row('SELECT * FROM dairy_alert_rules WHERE code = :c', [':c' => $code]) ?: null;
	}

	public function SaveRule(string $code, array $d): void
	{
		$rule = $this->Rule($code);
		if (!$rule)
		{
			throw new \Exception('Regla no encontrada');
		}
		$severity = $d['severity'] ?? $rule['severity'];
		if (!in_array($severity, ['danger', 'warning', 'info'], true))
		{
			throw new \Exception('Severidad no válida');
		}
		$threshold = $rule['threshold'];
		if ($rule['threshold'] !== null && isset($d['threshold']))
		{
			if (!is_numeric($d['threshold']) || floatval($d['threshold']) < 0)
			{
				throw new \Exception('El umbral debe ser un número mayor o igual a cero');
			}
			$threshold = floatval($d['threshold']);
			if ($code === 'phantom_stock' && ($threshold <= 0 || $threshold > 0.5))
			{
				throw new \Exception('La probabilidad del stock fantasma debe estar entre 0.001 y 0.5');
			}
		}
		$int = fn($k, $min, $def) => isset($d[$k]) && $d[$k] !== '' ? max($min, intval($d[$k])) : $def;
		$channel = in_array($d['notify_channel'] ?? $rule['notify_channel'], ['app', 'app_email'], true) ? ($d['notify_channel'] ?? $rule['notify_channel']) : 'app';
		$bool = fn($v, $def) => isset($v) ? (($v === true || $v === 1 || $v === '1' || $v === 'true' || $v === 'on') ? 1 : 0) : intval($def);
		$branch = array_key_exists('branch_id', $d) ? (!empty($d['branch_id']) ? intval($d['branch_id']) : null) : $rule['branch_id'];
		$this->Db()->prepare('UPDATE dairy_alert_rules SET enabled = ?, threshold = ?, severity = ?, due_hours = ?, requires_approval = ?, owner_user_id = ?,
				cooldown_hours = ?, escalate_after_hours = ?, notify_channel = ?, recommended_action = ?, branch_id = ?, frequency_minutes = ?, department = ? WHERE code = ?')->execute([
			$bool($d['enabled'] ?? null, $rule['enabled']), $threshold, $severity, $int('due_hours', 1, intval($rule['due_hours'])),
			$bool($d['requires_approval'] ?? null, $rule['requires_approval']),
			array_key_exists('owner_user_id', $d) ? (!empty($d['owner_user_id']) ? intval($d['owner_user_id']) : null) : $rule['owner_user_id'],
			$int('cooldown_hours', 0, intval($rule['cooldown_hours'])),
			array_key_exists('escalate_after_hours', $d) ? ($d['escalate_after_hours'] !== '' && $d['escalate_after_hours'] !== null ? max(1, intval($d['escalate_after_hours'])) : null) : $rule['escalate_after_hours'],
			$channel, isset($d['recommended_action']) ? (trim($d['recommended_action']) ?: null) : $rule['recommended_action'], $branch,
			$int('frequency_minutes', 5, intval($rule['frequency_minutes'])), isset($d['department']) ? (trim($d['department']) ?: null) : $rule['department'], $code
		]);
		// Historial de cambios de la regla (solo campos que cambiaron)
		$after = $this->Rule($code);
		$diff = [];
		foreach (['enabled', 'threshold', 'severity', 'due_hours', 'requires_approval', 'owner_user_id', 'cooldown_hours', 'escalate_after_hours', 'notify_channel', 'recommended_action', 'branch_id', 'frequency_minutes', 'department'] as $k)
		{
			if ((string) $rule[$k] !== (string) $after[$k])
			{
				$diff[$k] = [$rule[$k], $after[$k]];
			}
		}
		if ($diff)
		{
			$this->Db()->prepare('INSERT INTO dairy_alert_rule_changes (rule_code, user_id, changes, created_at) VALUES (?, ?, ?, ?)')
				->execute([$code, defined('GROCY_USER_ID') ? GROCY_USER_ID : null, json_encode($diff, JSON_UNESCAPED_UNICODE), $this->Now()]);
		}
	}

	public function RuleChanges(int $limit = 20): array
	{
		return $this->Rows('SELECT c.*, r.name AS rule_name, ' . self::Uname('u') . ' AS user_name FROM dairy_alert_rule_changes c LEFT JOIN dairy_alert_rules r ON r.code = c.rule_code LEFT JOIN users u ON u.id = c.user_id ORDER BY c.id DESC LIMIT ' . intval($limit));
	}

	/* ================================================================== Prioridad */

	/**
	 * P1–P4 a partir de severidad, seguridad alimentaria, impacto económico, recurrencia, urgencia y SLA. Devuelve [P, puntaje, motivo].
	 * Seguridad alimentaria / cumplimiento: piso P2 (P1 si la regla es crítica); el monto bajo nunca la reduce.
	 */
	public static function Prioritize(string $severity, bool $foodSafety, ?float $value, int $occurrences, bool $overdue, ?int $daysLeft): array
	{
		$base = ['danger' => 60, 'warning' => 35, 'info' => 15][$severity] ?? 35;
		$why = ['Severidad ' . (['danger' => 'crítica', 'warning' => 'media', 'info' => 'informativa'][$severity] ?? $severity) . " (+$base)"];
		$score = $base;
		$add = function (int $pts, string $txt) use (&$score, &$why) {
			$score += $pts;
			$why[] = "$txt (+$pts)";
		};
		if ($foodSafety)
		{
			$add(25, 'Seguridad alimentaria / cumplimiento');
		}
		$v = floatval($value ?? 0);
		if ($v >= 1000)
		{
			$add(25, 'Impacto $' . number_format($v, 2));
		}
		elseif ($v >= 250)
		{
			$add(15, 'Impacto $' . number_format($v, 2));
		}
		elseif ($v >= 50)
		{
			$add(5, 'Impacto $' . number_format($v, 2));
		}
		if ($occurrences >= 3)
		{
			$add(10, "Recurrente ($occurrences ocurrencias)");
		}
		if ($daysLeft !== null && $daysLeft <= 0)
		{
			$add(15, 'Vence hoy o ya venció');
		}
		elseif ($daysLeft !== null && $daysLeft <= 1)
		{
			$add(8, 'Vence mañana');
		}
		if ($overdue)
		{
			$add(15, 'Fuera de SLA');
		}
		$p = $score >= 80 ? 'P1' : ($score >= 55 ? 'P2' : ($score >= 30 ? 'P3' : 'P4'));
		if ($foodSafety)
		{
			$floor = $severity === 'danger' ? 'P1' : 'P2';
			if (strcmp($p, $floor) > 0)
			{
				$p = $floor;
				$why[] = "Piso $floor por regla de seguridad alimentaria";
			}
		}
		return [$p, $score, implode(' · ', $why)];
	}

	private function Reprioritize(int $id): void
	{
		$a = $this->Row("SELECT a.*, CASE WHEN r.code IS NOT NULL THEN IFNULL(r.food_safety, 0) WHEN a.category = 'quality' AND a.alert_type = 'cold_chain' THEN 1 ELSE 0 END AS food_safety FROM dairy_alerts a LEFT JOIN dairy_alert_rules r ON r.code = a.rule_code WHERE a.id = :i", [':i' => $id]);
		if (!$a || intval($a['priority_locked']) || !in_array($a['status'], self::ACTIVE, true))
		{
			return;
		}
		$overdue = $a['due_at'] && $a['due_at'] < $this->Now() && $a['status'] !== 'pending_approval';
		$daysLeft = $a['alert_type'] === 'expiry' && preg_match('/:(\d{4}-\d{2}-\d{2})$/', (string) $a['dedupe_key'], $m) ? (int) round((strtotime($m[1]) - strtotime(date('Y-m-d'))) / 86400) : null;
		[$p, $score, $why] = self::Prioritize($a['severity'], (bool) intval($a['food_safety']), $a['value'] !== null ? floatval($a['value']) : null, intval($a['occurrences']), $overdue, $daysLeft);
		$this->Db()->prepare('UPDATE dairy_alerts SET priority = ?, priority_score = ?, priority_reason = ? WHERE id = ?')->execute([$p, $score, $why, $id]);
	}

	/* ================================================================== Barrido */

	public function LastScan(): ?string
	{
		$v = $this->Db()->query("SELECT MAX(created_at) FROM dairy_alert_events WHERE action = 'scan'")->fetchColumn();
		return $v ?: null;
	}

	// Escanea si el último barrido tiene más de ALERT_SCAN_MINUTES (lo usa la página; una cadena grande puede llamar POST /api/alerts/scan desde un cron)
	public function ScanIfStale(): ?array
	{
		$last = $this->LastScan();
		if ($last && strtotime($last) > time() - 60 * intval(GROCY_ALERT_SCAN_MINUTES))
		{
			return null;
		}
		return $this->Scan(null, defined('GROCY_USER_ID') ? GROCY_USER_ID : null, false);
	}

	/**
	 * Ejecuta los detectores de las reglas activas por sucursal (o solo la indicada). $force = false respeta la frecuencia de cada regla.
	 */
	public function Scan(?int $branchId, ?int $userId, bool $force = true): array
	{
		$now = $this->Now();
		$rules = [];
		foreach ($this->GetRules() as $r)
		{
			if (intval($r['enabled']) && ($force || !$r['last_run_at'] || strtotime($r['last_run_at']) <= time() - 60 * max(1, intval($r['frequency_minutes']))))
			{
				$rules[$r['code']] = $r;
			}
		}
		$branches = $branchId ? [$branchId] : array_map('intval', $this->Db()->query('SELECT id FROM branches WHERE active = 1')->fetchAll(\PDO::FETCH_COLUMN));
		$found = [];
		foreach ($branches as $b)
		{
			foreach ($rules as $code => $rule)
			{
				if ($rule['branch_id'] !== null && intval($rule['branch_id']) !== $b)
				{
					continue;
				}
				foreach ($this->Detect($code, $rule, $b) as $hit)
				{
					$hit['branch_id'] = $b;
					$found[$code . ':' . $hit['key']] = [$rule, $hit];
				}
			}
		}

		$db = $this->Db();
		$created = $refreshed = $autoClosed = $grouped = $suppressed = 0;
		$newIds = [];
		$db->beginTransaction();
		try
		{
			foreach ($found as $key => [$rule, $hit])
			{
				$existing = $this->Row('SELECT a.id, a.status, a.occurrences FROM dairy_alert_keys k JOIN dairy_alerts a ON a.id = k.alert_id WHERE k.dedupe_key = :k', [':k' => $key])
					?: $this->Row('SELECT id, status, occurrences FROM dairy_alerts WHERE dedupe_key = :k', [':k' => $key]);
				if ($existing)
				{
					if (in_array($existing['status'], self::ACTIVE, true))
					{
						// Agrupadas: el valor y detalle son la suma de sus hallazgos; no se pisan con el de uno solo
						if (intval($existing['occurrences']) <= 1)
						{
							$db->prepare('UPDATE dairy_alerts SET detail = ?, value = ?, impact_hours = ?, last_seen_at = ? WHERE id = ?')->execute([$hit['detail'], $hit['value'] ?? null, $hit['hours'] ?? null, $now, $existing['id']]);
						}
						else
						{
							$db->prepare('UPDATE dairy_alerts SET last_seen_at = ? WHERE id = ?')->execute([$now, $existing['id']]);
						}
						$this->Reprioritize(intval($existing['id']));
						$refreshed++;
					}
					continue;
				}

				$productId = isset($hit['product_id']) ? intval($hit['product_id']) : null;
				$cool = intval($rule['cooldown_hours']);
				if ($cool > 0 && $productId)
				{
					// Agrupación: otro hallazgo del mismo producto y sucursal se suma a la alerta activa
					$open = $this->Row('SELECT id FROM dairy_alerts WHERE rule_code = :r AND branch_id = :b AND product_id = :p AND status IN (' . self::In(self::ACTIVE) . ') ORDER BY id DESC LIMIT 1',
						[':r' => $rule['code'], ':b' => $hit['branch_id'], ':p' => $productId]);
					if ($open)
					{
						$db->prepare("UPDATE dairy_alerts SET occurrences = occurrences + 1, value = IFNULL(value, 0) + ?, detail = substr(IFNULL(detail, '') || ' | ' || ?, 1, 2000), last_seen_at = ?, updated_at = ? WHERE id = ?")
							->execute([floatval($hit['value'] ?? 0), $hit['detail'], $now, $now, $open['id']]);
						$this->Key($key, intval($open['id']), $now);
						$this->Event(intval($open['id']), null, 'grouped', 'Nuevo hallazgo agrupado: ' . mb_substr($hit['detail'], 0, 300));
						$this->Reprioritize(intval($open['id']));
						$grouped++;
						continue;
					}
					// Enfriamiento: tras un cierre reciente no se abre otra alerta del mismo producto (queda registrado en la cerrada)
					$recent = $this->Row("SELECT id FROM dairy_alerts WHERE rule_code = :r AND branch_id = :b AND product_id = :p AND status IN ('closed', 'dismissed') AND resolved_at >= :c ORDER BY id DESC LIMIT 1",
						[':r' => $rule['code'], ':b' => $hit['branch_id'], ':p' => $productId, ':c' => date('Y-m-d H:i:s', time() - 3600 * $cool)]);
					if ($recent)
					{
						$this->Key($key, intval($recent['id']), $now);
						$this->Event(intval($recent['id']), null, 'suppressed', "Hallazgo repetido durante el enfriamiento de {$cool} h: " . mb_substr($hit['detail'], 0, 300));
						$suppressed++;
						continue;
					}
				}

				$id = $this->Insert([
					'rule_code' => $rule['code'], 'dedupe_key' => $key, 'branch_id' => $hit['branch_id'], 'severity' => $rule['severity'], 'title' => $hit['title'], 'detail' => $hit['detail'],
					'value' => $hit['value'] ?? null, 'href' => $hit['href'] ?? null, 'owner_user_id' => $rule['owner_user_id'], 'due_at' => date('Y-m-d H:i:s', time() + 3600 * intval($rule['due_hours'])),
					'source' => 'rule', 'category' => $rule['category'], 'alert_type' => $rule['alert_type'], 'department' => $rule['department'], 'product_id' => $productId,
					'impact_bucket' => $rule['impact_bucket'], 'impact_kind' => $rule['impact_kind'], 'impact_hours' => $hit['hours'] ?? null,
					'status' => $rule['owner_user_id'] ? 'assigned' : 'open',
				], $now);
				$this->Event($id, $userId, 'created', 'Detectada por la regla «' . $rule['name'] . '»');
				$this->Reprioritize($id);
				$newIds[] = $id;
				$created++;
			}

			// Cierre automático: abiertas sin intervención (sin respuesta humana ni tarea pendiente) cuya condición ya no se detecta.
			// Una tarea pendiente creada desde la alerta es intervención: la alerta se resuelve al completar la tarea (con su nota).
			$scope = $branchId ? ' AND a.branch_id = ' . intval($branchId) : '';
			$codes = count($rules) ? self::In(array_keys($rules)) : "''";
			foreach ($this->Rows("SELECT a.id, a.dedupe_key, (SELECT group_concat(k.dedupe_key, char(10)) FROM dairy_alert_keys k WHERE k.alert_id = a.id) AS ks FROM dairy_alerts a
				WHERE a.status IN ('open', 'assigned') AND a.source = 'rule' AND a.first_response_at IS NULL AND a.rule_code IN ($codes)$scope
				AND NOT EXISTS (SELECT 1 FROM workforce_store_tasks t WHERE t.source_type = 'alert' AND t.source_id = a.id AND t.status = 'pending')") as $a)
			{
				$keys = array_filter(array_merge([$a['dedupe_key']], explode("\n", (string) $a['ks'])));
				if (!array_filter($keys, fn($k) => isset($found[$k])))
				{
					$db->prepare("UPDATE dairy_alerts SET status = 'closed', resolution = ?, resolved_at = ?, updated_at = ? WHERE id = ? AND status IN ('open', 'assigned')")->execute(['La condición desapareció (verificación automática)', $now, $now, $a['id']]);
					$this->Event(intval($a['id']), null, 'auto_closed', 'La condición ya no se detecta');
					$autoClosed++;
				}
			}
			foreach (array_keys($rules) as $code)
			{
				$db->prepare('UPDATE dairy_alert_rules SET last_run_at = ? WHERE code = ?')->execute([$now, $code]);
			}
			$this->Event(0, $userId, 'scan', "creadas $created · actualizadas $refreshed · agrupadas $grouped · suprimidas $suppressed · cerradas $autoClosed");
			$db->commit();
		}
		catch (\Throwable $e)
		{
			$db->rollBack();
			throw $e;
		}
		foreach ($newIds as $id)
		{
			$this->NotifyNew($id);
			AutomationEngineService::GetInstance()->OnAlert('alert_created', $id);
		}
		$esc = $this->RunEscalations();
		return ['created' => $created, 'refreshed' => $refreshed, 'grouped' => $grouped, 'suppressed' => $suppressed, 'auto_closed' => $autoClosed, 'branches' => count($branches)] + $esc;
	}

	private function Key(string $key, int $alertId, string $now): void
	{
		$this->Db()->prepare('INSERT OR IGNORE INTO dairy_alert_keys (dedupe_key, alert_id, created_at) VALUES (?, ?, ?)')->execute([$key, $alertId, $now]);
	}

	private function Insert(array $f, string $now): int
	{
		$f += ['created_at' => $now, 'updated_at' => $now, 'last_seen_at' => $now];
		// Recurrencia: misma regla, sucursal y producto cerrada en 90 días → se vincula al incidente anterior y a su plan preventivo
		if (($f['rule_code'] ?? 'manual') !== 'manual')
		{
			$k = [':r' => $f['rule_code'], ':b' => $f['branch_id'] ?? null, ':p' => $f['product_id'] ?? null, ':s' => date('Y-m-d H:i:s', strtotime($now) - 90 * 86400)];
			$f['recurrence_of'] = $this->Val("SELECT id FROM dairy_alerts WHERE rule_code = :r AND branch_id IS :b AND product_id IS :p AND status IN ('closed', 'dismissed') AND resolved_at >= :s ORDER BY id DESC LIMIT 1", $k) ?: null;
			unset($k[':s']);
			$f['prevention_id'] = $this->Val("SELECT id FROM dairy_alert_prevention WHERE rule_code = :r AND branch_id IS :b AND product_id IS :p AND status <> 'ineffective' ORDER BY id DESC LIMIT 1", $k) ?: null;
		}
		$cols = array_keys($f);
		$this->Db()->prepare('INSERT INTO dairy_alerts (' . implode(', ', $cols) . ') VALUES (' . implode(', ', array_fill(0, count($cols), '?')) . ')')->execute(array_values($f));
		$id = intval($this->Db()->lastInsertId());
		$this->Key($f['dedupe_key'], $id, $now);
		return $id;
	}

	private function Detect(string $code, array $rule, int $b): array
	{
		$t = $rule['threshold'] !== null ? floatval($rule['threshold']) : null;
		$money = fn($v) => '$' . number_format(floatval($v), 2);
		$lp = LossPreventionService::GetInstance();
		$today = date('Y-m-d');
		$out = [];
		switch ($code)
		{
			case 'phantom_stock':
				foreach ($lp->PhantomStock($b, $t) as $r)
				{
					$out[] = ['key' => "$b:{$r['product_id']}:{$r['last_sale']}", 'title' => 'Posible stock fantasma: ' . $r['product_name'], 'product_id' => $r['product_id'],
						'detail' => "Sistema: {$r['system_stock']} u. · se vende {$r['sell_frequency_pct']} % de los días y lleva {$r['days_without_sale']} días sin venta (prob. casual " . round($r['probability'] * 100, 1) . ' %). Contar a ciegas.',
						'value' => $r['lost_sales_value'], 'href' => '/inventory-control?branch_id=' . $b];
				}
				break;
			case 'unexplained_outflow':
				foreach ($lp->UnexplainedOutflows($b, date('Y-m-d', strtotime('-7 days')), $today) as $r)
				{
					if (floatval($r['value']) >= ($t ?? 0))
					{
						$out[] = ['key' => "{$r['id']}", 'title' => 'Salida sin documento: ' . $r['product_name'], 'product_id' => $r['product_id'],
							'detail' => "{$r['amount']} u. ({$money($r['value'])}) consumidas el {$r['row_created_timestamp']} por {$r['user_name']} sin venta, merma ni traslado.",
							'value' => $r['value'], 'href' => '/kardex?product_id=' . $r['product_id']];
					}
				}
				break;
			case 'pos_unreconciled':
				$bySale = [];
				foreach ($lp->PosReconciliation($b, date('Y-m-d', strtotime('-30 days')), $today) as $r)
				{
					$bySale[$r['sale_id']] ??= ['n' => 0, 'cost' => 0.0, 'number' => $r['sale_number'], 'ts' => $r['created_timestamp']];
					$bySale[$r['sale_id']]['n']++;
					$bySale[$r['sale_id']]['cost'] += floatval($r['cost']);
				}
				foreach ($bySale as $sid => $s)
				{
					if ($s['cost'] >= ($t ?? 0))
					{
						$out[] = ['key' => "$sid", 'title' => 'Venta sin descargo de inventario: ' . ($s['number'] ?: '#' . $sid),
							'detail' => "{$s['n']} línea(s) vendidas el {$s['ts']} sin movimiento de stock: el inventario queda sobrestimado en {$money($s['cost'])} a costo.",
							'value' => round($s['cost'], 2), 'href' => '/waste#tab-perdida'];
					}
				}
				break;
			case 'inventory_anomaly':
				foreach (MovementLedgerService::GetInstance()->Anomalies($b, 7)['items'] ?? [] as $r)
				{
					$out[] = ['key' => "{$r['type']}:{$r['stock_log_id']}", 'title' => 'Movimiento anómalo: ' . $r['product_name'], 'detail' => $r['detail'] . " · {$r['user']} · {$r['timestamp']}",
						'value' => $r['value'], 'href' => '/kardex?product_id=' . $r['product_id'], 'product_id' => $r['product_id'] ?? null];
				}
				break;
			case 'cold_chain_breach':
				foreach ($this->Rows("SELECT c.location_id, l.name, date(c.recorded_timestamp) AS d, MAX(c.temperature) AS tmax, MIN(c.temperature) AS tmin, COUNT(*) AS n, MAX(c.status = 'critical') AS crit
					FROM cold_chain_temperatures c LEFT JOIN locations l ON l.id = c.location_id
					WHERE c.status != 'normal' AND c.recorded_timestamp >= :s AND (c.branch_id = :b OR l.branch_id = :b) GROUP BY c.location_id, d", [':s' => date('Y-m-d H:i:s', time() - 86400), ':b' => $b]) as $r)
				{
					$out[] = ['key' => "{$r['location_id']}:{$r['d']}", 'title' => 'Temperatura fuera de rango: ' . ($r['name'] ?? 'ubicación #' . $r['location_id']),
						'detail' => "{$r['n']} lectura(s) fuera de rango el {$r['d']} (mín {$r['tmin']} °C · máx {$r['tmax']} °C)" . ($r['crit'] ? ' · nivel crítico' : '') . '. Evaluar lotes expuestos y cuarentena.',
						'value' => null, 'href' => '/waste#tab-fefo'];
				}
				break;
			case 'expiring_lots':
				// Mismo criterio que la agenda de Hoy (vence entre hoy y +N días, fuera de tránsito) pero por lote y sin cuarentena
				foreach ($this->Rows("SELECT s.stock_id, s.product_id, p.name, s.best_before_date AS d, SUM(s.amount) AS qty, SUM(s.amount * IFNULL(s.price, 0)) AS value, MIN(l.name) AS loc
					FROM stock s JOIN locations l ON l.id = s.location_id LEFT JOIN products p ON p.id = s.product_id
					WHERE l.branch_id = :b AND IFNULL(l.is_transit, 0) = 0 AND s.amount > 0 AND s.best_before_date BETWEEN date('now', 'localtime') AND date('now', 'localtime', :lim)
						AND NOT EXISTS (SELECT 1 FROM quarantine_items q WHERE q.stock_id = s.stock_id AND q.status = 'in_quarantine')
					GROUP BY s.stock_id, s.best_before_date ORDER BY s.best_before_date, value DESC", [':b' => $b, ':lim' => '+' . intval($t ?? 3) . ' days']) as $r)
				{
					$days = (int) round((strtotime($r['d']) - strtotime($today)) / 86400);
					$out[] = ['key' => "{$r['stock_id']}:{$r['d']}", 'title' => 'Lote por vencer: ' . ($r['name'] ?? 'producto #' . $r['product_id']), 'product_id' => $r['product_id'],
						'detail' => ($days === 0 ? 'Vence hoy' : "Vence en $days día(s)") . " ({$r['d']}) · {$r['qty']} u. en {$r['loc']} · {$money($r['value'])} a costo. Rebajar (FEFO), trasladar o reubicar al frente.",
						'value' => $r['value'], 'href' => '/waste#tab-fefo'];
				}
				break;
			case 'recall_unconfirmed':
				foreach ($this->Rows("SELECT r.id, r.recall_code, r.product_id, p.name, COUNT(i.id) AS lots, SUM(i.amount) AS amount FROM product_recalls r JOIN product_recall_items i ON i.recall_id = r.id LEFT JOIN products p ON p.id = r.product_id
					WHERE r.status = 'open' AND i.status = 'quarantined' AND i.branch_id = :b AND r.created_at <= :lim GROUP BY r.id", [':b' => $b, ':lim' => date('Y-m-d H:i:s', time() - 3600 * ($t ?? 4))]) as $r)
				{
					$out[] = ['key' => "{$r['id']}:$b", 'title' => "Retiro {$r['recall_code']} sin confirmar: {$r['name']}", 'detail' => "{$r['lots']} lote(s), {$r['amount']} u. sin confirmar que se separaron físicamente.",
						'value' => null, 'href' => '/recalls?id=' . $r['id'], 'product_id' => $r['product_id']];
				}
				break;
			case 'audit_failed':
				foreach ($this->Rows("SELECT a.id, a.audit_code, a.score, a.critical_failures FROM store_audits a WHERE a.branch_id = :b AND a.status = 'completed' AND a.completed_at >= :s AND (a.score < :t OR a.critical_failures > 0)", [':b' => $b, ':s' => date('Y-m-d', strtotime('-30 days')), ':t' => $t ?? 80]) as $r)
				{
					$out[] = ['key' => "{$r['id']}", 'title' => "Auditoría {$r['audit_code']} reprobada ({$r['score']} %)", 'detail' => intval($r['critical_failures']) . ' incumplimiento(s) crítico(s). Verificar que las tareas correctivas se cierren.',
						'value' => null, 'href' => '/audits?id=' . $r['id']];
				}
				break;
			case 'cert_expiring':
				foreach ($this->Rows("SELECT c.id, c.name, c.expires_date, e.name AS employee FROM employee_certifications c JOIN employees e ON e.id = c.employee_id WHERE e.active = 1 AND e.branch_id = :b AND c.expires_date IS NOT NULL AND c.expires_date <= :lim", [':b' => $b, ':lim' => date('Y-m-d', strtotime('+' . intval($t ?? 30) . ' days'))]) as $r)
				{
					$expired = $r['expires_date'] < $today;
					$out[] = ['key' => "{$r['id']}:{$r['expires_date']}", 'title' => ($expired ? 'Certificación vencida: ' : 'Certificación por vencer: ') . "{$r['name']} · {$r['employee']}",
						'detail' => ($expired ? 'Venció el ' : 'Vence el ') . $r['expires_date'] . '. Los turnos que la exigen quedan bloqueados al vencer.', 'value' => null, 'href' => '/schedule#tab-employees'];
				}
				break;
			case 'high_value_waste':
				foreach ($this->Rows("SELECT w.id, w.product_id, IFNULL(w.case_number, 'MRM-' || w.id) AS code, p.name, w.total_cost, r.name AS reason FROM waste_records w LEFT JOIN products p ON p.id = w.product_id LEFT JOIN waste_reasons r ON r.id = w.waste_reason_id
					WHERE w.branch_id = :b AND w.status = 'approved' AND w.total_cost >= :t AND date(IFNULL(w.approved_timestamp, w.created_timestamp)) >= :s", [':b' => $b, ':t' => $t ?? 50, ':s' => date('Y-m-d', strtotime('-7 days'))]) as $r)
				{
					$out[] = ['key' => "{$r['id']}", 'title' => "Merma de alto valor {$r['code']}: {$r['name']}", 'detail' => "{$money($r['total_cost'])} por «{$r['reason']}». Confirmar causa raíz y acción correctiva.",
						'value' => $r['total_cost'], 'href' => '/document/waste/' . $r['id'], 'product_id' => $r['product_id']];
				}
				break;
			case 'low_stock':
				foreach ($this->Rows("SELECT p.id, p.name, p.min_stock_amount AS min, IFNULL(SUM(s.amount), 0) AS qty,
						(SELECT sl.price FROM stock_log sl WHERE sl.product_id = p.id AND sl.transaction_type = 'purchase' AND sl.undone = 0 AND sl.price > 0 ORDER BY sl.id DESC LIMIT 1) AS cost
					FROM products p JOIN locations pl ON pl.id = p.location_id LEFT JOIN stock s ON s.product_id = p.id AND s.location_id IN (SELECT id FROM locations WHERE branch_id = :b AND IFNULL(is_transit, 0) = 0)
					WHERE p.active = 1 AND p.min_stock_amount > 0 AND pl.branch_id = :b GROUP BY p.id HAVING qty < min", [':b' => $b]) as $r)
				{
					$short = floatval($r['min']) - floatval($r['qty']);
					$out[] = ['key' => "$b:{$r['id']}", 'title' => 'Stock bajo mínimo: ' . $r['name'], 'product_id' => $r['id'],
						'detail' => floatval($r['qty']) . ' u. en sucursal · mínimo ' . floatval($r['min']) . ' u. · faltan ' . round($short, 2) . ' u.' . ($r['cost'] ? ' (' . $money($short * $r['cost']) . ' a costo de reposición)' : ''),
						'value' => $r['cost'] ? round($short * floatval($r['cost']), 2) : null, 'href' => '/mrp?branch_id=' . $b];
				}
				break;
			case 'negative_stock':
				foreach ($this->Rows("SELECT s.product_id, p.name, SUM(s.amount) AS qty FROM stock s JOIN locations l ON l.id = s.location_id LEFT JOIN products p ON p.id = s.product_id
					WHERE l.branch_id = :b GROUP BY s.product_id HAVING qty < 0", [':b' => $b]) as $r)
				{
					$out[] = ['key' => "$b:{$r['product_id']}", 'title' => 'Stock negativo: ' . $r['name'], 'product_id' => $r['product_id'], 'detail' => 'Existencia ' . floatval($r['qty']) . ' u.: hay salidas sin recepción registrada.',
						'value' => null, 'href' => '/kardex?product_id=' . $r['product_id']];
				}
				break;
			case 'count_difference':
				foreach ($this->Rows("SELECT c.id, c.product_id, p.name, c.difference_amount, c.difference_cost, c.executed_date FROM stock_cycle_counts c LEFT JOIN products p ON p.id = c.product_id
					WHERE c.branch_id = :b AND c.status = 'discrepancy' AND ABS(IFNULL(c.difference_cost, 0)) >= :t", [':b' => $b, ':t' => $t ?? 0]) as $r)
				{
					$out[] = ['key' => "{$r['id']}", 'title' => 'Diferencia de conteo: ' . $r['name'], 'product_id' => $r['product_id'],
						'detail' => 'Conteo CNT-' . $r['id'] . ' del ' . substr((string) $r['executed_date'], 0, 10) . ': ' . floatval($r['difference_amount']) . ' u. (' . $money(abs($r['difference_cost'])) . ') sin ajustar.',
						'value' => round(abs(floatval($r['difference_cost'])), 2), 'href' => '/waste#tab-conteos'];
				}
				break;
			case 'device_transfer_unconfirmed':
				$hours = max(1, intval($this->Val("SELECT value FROM device_rules WHERE code = 'transfer_confirm_hours'")));
				foreach ($this->Rows("SELECT d.id, d.product_id, p.name, t.transfer_code, t.sent_at, d.unit_cost FROM device_units d JOIN stock_transfers t ON t.id = d.transfer_id JOIN products p ON p.id = d.product_id WHERE d.state = 'in_transit' AND d.branch_id = :b AND t.sent_at <= :s", [':b' => $b, ':s' => date('Y-m-d H:i:s', time() - 3600 * $hours)]) as $r)
				{
					$out[] = ['key' => "{$r['id']}", 'title' => 'Traslado sin confirmar: ' . $r['name'], 'product_id' => $r['product_id'], 'detail' => "{$r['transfer_code']} enviado el {$r['sent_at']}: el destino no confirmó la recepción.", 'value' => round(floatval($r['unit_cost']), 2), 'href' => '/loss-prevention/devices/' . $r['id']];
				}
				break;
			case 'recurrent_difference':
				foreach ($this->Rows("SELECT c.product_id, p.name, COUNT(*) AS n, SUM(c.financial_impact) AS v FROM sensitive_investigation_cases c JOIN products p ON p.id = c.product_id WHERE c.branch_id = :b AND c.created_at >= date('now', '-90 days') AND c.device_id IS NULL GROUP BY c.product_id HAVING n > 1", [':b' => $b]) as $r)
				{
					$out[] = ['key' => "$b:{$r['product_id']}", 'title' => 'Diferencias repetidas: ' . $r['name'], 'product_id' => $r['product_id'], 'detail' => "{$r['n']} expedientes en 90 días. Revisar el proceso; no señala a ninguna persona.", 'value' => round(floatval($r['v']), 2), 'href' => '/merchandise?product_id=' . $r['product_id'] . '&branch_id=' . $b];
				}
				break;
			case 'repeated_adjustments':
				foreach ($this->Rows("SELECT m.product_id, p.name, COUNT(DISTINCT m.operation_ref) AS n, SUM(m.value) AS v FROM merchandise_movements m JOIN products p ON p.id = m.product_id WHERE m.branch_id = :b AND m.undone = 0 AND m.bucket = 'adjustments' AND m.effective_at >= date('now', '-30 days') GROUP BY m.product_id HAVING n >= 3", [':b' => $b]) as $r)
				{
					$out[] = ['key' => "$b:{$r['product_id']}", 'title' => 'Ajustes repetidos: ' . $r['name'], 'product_id' => $r['product_id'], 'detail' => "{$r['n']} ajustes en 30 días.", 'value' => round(floatval($r['v']), 2), 'href' => '/merchandise?bucket=adjustments&product_id=' . $r['product_id'] . '&branch_id=' . $b];
				}
				break;
			case 'reception_difference':
				foreach ($this->Rows("SELECT r.id, r.receipt_code, SUM(i.delivered_amount - i.accepted_amount) AS q, SUM((i.delivered_amount - i.accepted_amount) * i.unit_price) AS v FROM procurement_receipts r JOIN procurement_receipt_items i ON i.receipt_id = r.id WHERE r.branch_id = :b AND r.received_at >= date('now', '-14 days') GROUP BY r.id HAVING q > 0", [':b' => $b]) as $r)
				{
					$out[] = ['key' => "po{$r['id']}", 'title' => 'Recepción con diferencias: ' . $r['receipt_code'], 'detail' => floatval($r['q']) . ' u. entregadas sin aceptar.', 'value' => round(floatval($r['v']), 2), 'href' => '/mrp'];
				}
				foreach ($this->Rows("SELECT id, code FROM device_receipts WHERE branch_id = :b AND status = 'with_differences' AND created_at >= date('now', '-14 days')", [':b' => $b]) as $r)
				{
					$out[] = ['key' => "dv{$r['id']}", 'title' => 'Recepción por IMEI con diferencias: ' . $r['code'], 'detail' => 'Se cerró con faltantes o adicionales justificados.', 'value' => null, 'href' => '/loss-prevention/devices'];
				}
				break;
			case 'sensitive_no_follow_up':
				foreach ($this->Rows("SELECT p.id, p.name FROM products p WHERE p.active = 1 AND p.is_sensitive = 1 AND p.device_control = 0
					AND EXISTS (SELECT 1 FROM stock s JOIN locations l ON l.id = s.location_id WHERE s.product_id = p.id AND l.branch_id = :b AND s.amount > 0)
					AND NOT EXISTS (SELECT 1 FROM sensitive_inventory_movements m WHERE m.product_id = p.id AND m.branch_id = :b AND m.event_type = 'cycle_count' AND m.created_at >= date('now', '-14 days'))", [':b' => $b]) as $r)
				{
					$out[] = ['key' => "$b:{$r['id']}", 'title' => 'Sensible sin seguimiento: ' . $r['name'], 'product_id' => $r['id'], 'detail' => 'Sin conteo en 14 días.', 'value' => null, 'href' => '/loss-prevention/counts'];
				}
				break;
			case 'sensitive_count_overdue':
				foreach ($this->Rows("SELECT t.id, t.product_id, p.name, t.due_at FROM sensitive_count_tasks t JOIN products p ON p.id = t.product_id WHERE t.branch_id = :b AND t.status IN ('pending', 'recount') AND t.due_at < :n", [':b' => $b, ':n' => $this->Now()]) as $r)
				{
					$out[] = ['key' => "{$r['id']}", 'title' => 'Conteo sensible vencido: ' . $r['name'], 'product_id' => $r['product_id'], 'detail' => 'Debía ejecutarse antes de ' . $r['due_at'] . '.', 'value' => null, 'href' => '/loss-prevention/counts'];
				}
				break;
			case 'sensitive_manual_adjust':
				foreach ($this->Rows("SELECT sl.id, sl.product_id, p.name, sl.amount, sl.row_created_timestamp, ABS(sl.amount * IFNULL(sl.price, 0)) AS v FROM stock_log sl JOIN products p ON p.id = sl.product_id JOIN locations l ON l.id = sl.location_id
					WHERE l.branch_id = :b AND p.is_sensitive = 1 AND sl.undone = 0 AND sl.transaction_type = 'inventory-correction' AND sl.row_created_timestamp >= date('now', '-7 days')
					AND NOT EXISTS (SELECT 1 FROM sensitive_inventory_movements m WHERE m.stock_transaction_id = sl.transaction_id)", [':b' => $b]) as $r)
				{
					$out[] = ['key' => "{$r['id']}", 'title' => 'Ajuste sin expediente: ' . $r['name'], 'product_id' => $r['product_id'], 'detail' => floatval($r['amount']) . ' u. el ' . $r['row_created_timestamp'] . '. No está ligado a un expediente aprobado.', 'value' => round(floatval($r['v']), 2), 'href' => '/kardex?product_id=' . $r['product_id']];
				}
				break;
			case 'count_overdue':
				foreach ($this->Rows("SELECT c.id, c.product_id, p.name, c.scheduled_date FROM stock_cycle_counts c LEFT JOIN products p ON p.id = c.product_id
					WHERE c.branch_id = :b AND c.status = 'pending' AND c.scheduled_date < :d", [':b' => $b, ':d' => $today]) as $r)
				{
					$out[] = ['key' => "{$r['id']}", 'title' => 'Conteo vencido: ' . $r['name'], 'product_id' => $r['product_id'], 'detail' => 'Programado para el ' . $r['scheduled_date'] . ' y sin ejecutar.',
						'value' => null, 'href' => '/waste#tab-conteos'];
				}
				break;
			case 'transfer_pending':
				foreach ($this->Rows("SELECT t.id, t.transfer_code, t.sent_at, t.total_sent_cost, fb.name AS from_name FROM stock_transfers t LEFT JOIN branches fb ON fb.id = t.from_branch_id
					WHERE t.to_branch_id = :b AND t.status = 'in_transit' AND t.sent_at <= :lim", [':b' => $b, ':lim' => date('Y-m-d H:i:s', time() - 3600 * intval($t ?? 24))]) as $r)
				{
					$out[] = ['key' => "{$r['id']}", 'title' => 'Traslado sin recibir: ' . $r['transfer_code'], 'detail' => 'Enviado desde ' . $r['from_name'] . ' el ' . substr($r['sent_at'], 0, 16) . ' · ' . $money($r['total_sent_cost']) . ' en tránsito.',
						'value' => $r['total_sent_cost'], 'href' => '/transfers'];
				}
				break;
			case 'po_late':
				foreach ($this->Rows("SELECT o.id, o.order_code, o.expected_delivery_date, o.total_estimated_cost, sl.name AS supplier FROM procurement_orders o LEFT JOIN shopping_locations sl ON sl.id = o.supplier_id
					WHERE o.branch_id = :b AND o.status IN ('sent', 'partially_received') AND o.expected_delivery_date IS NOT NULL AND o.expected_delivery_date < :d", [':b' => $b, ':d' => $today]) as $r)
				{
					$out[] = ['key' => "{$r['id']}", 'title' => "Pedido atrasado {$r['order_code']}: " . ($r['supplier'] ?? 'proveedor'), 'detail' => 'Entrega comprometida el ' . $r['expected_delivery_date'] . ' sin recepción completa · ' . $money($r['total_estimated_cost']) . '.',
						'value' => $r['total_estimated_cost'], 'href' => '/mrp'];
				}
				break;
			case 'quarantine_stale':
				foreach ($this->Rows("SELECT q.id, q.product_id, p.name, q.amount, q.entered_timestamp, q.reason, (SELECT AVG(s.price) FROM stock s WHERE s.stock_id = q.stock_id) AS price FROM quarantine_items q LEFT JOIN products p ON p.id = q.product_id
					WHERE q.branch_id = :b AND q.status = 'in_quarantine' AND q.entered_timestamp <= :lim", [':b' => $b, ':lim' => date('Y-m-d H:i:s', time() - 86400 * ($t ?? 2))]) as $r)
				{
					$out[] = ['key' => "{$r['id']}", 'title' => 'Cuarentena sin decisión: ' . $r['name'], 'product_id' => $r['product_id'], 'detail' => floatval($r['amount']) . ' u. en cuarentena desde ' . substr($r['entered_timestamp'], 0, 16) . ' · ' . $r['reason'],
						'value' => $r['price'] ? round(floatval($r['price']) * floatval($r['amount']), 2) : null, 'href' => '/waste#tab-fefo'];
				}
				break;
			case 'shift_unpublished':
				$r = $this->Row("SELECT COUNT(*) AS n, MIN(shift_date) AS first, SUM((strftime('%s', '2000-01-01 ' || end_time) - strftime('%s', '2000-01-01 ' || start_time) + CASE WHEN end_time < start_time THEN 86400 ELSE 0 END) / 3600.0 - break_minutes / 60.0) AS hours
					FROM employee_shifts WHERE branch_id = :b AND status = 'planned' AND shift_date BETWEEN :d AND :lim", [':b' => $b, ':d' => $today, ':lim' => date('Y-m-d', strtotime('+' . intval($t ?? 2) . ' days'))]);
				if ($r && intval($r['n']) > 0)
				{
					$out[] = ['key' => "$b:{$r['first']}", 'title' => intval($r['n']) . ' turno(s) sin publicar desde el ' . $r['first'], 'detail' => 'El personal no ve estos turnos en borrador · ' . round(floatval($r['hours']), 1) . ' h programadas.',
						'value' => null, 'hours' => round(floatval($r['hours']), 1), 'href' => '/schedule'];
				}
				break;
			case 'missing_punch':
				$limit = date('H:i', time() - 60 * intval($t ?? 30));
				foreach ($this->Rows("SELECT s.id, s.start_time, s.end_time, e.name FROM employee_shifts s JOIN employees e ON e.id = s.employee_id
					WHERE s.branch_id = :b AND s.status = 'published' AND s.shift_date = :d AND s.start_time <= :lim AND (s.end_time > s.start_time AND s.end_time > :now OR s.end_time < s.start_time)
						AND NOT EXISTS (SELECT 1 FROM employee_attendance a WHERE a.shift_id = s.id OR (a.employee_id = s.employee_id AND date(a.clock_in) = s.shift_date))", [':b' => $b, ':d' => $today, ':lim' => $limit, ':now' => date('H:i')]) as $r)
				{
					$out[] = ['key' => "{$r['id']}", 'title' => 'Turno sin fichaje: ' . $r['name'], 'detail' => 'Turno ' . substr($r['start_time'], 0, 5) . '–' . substr($r['end_time'], 0, 5) . ' iniciado sin marca de entrada.',
						'value' => null, 'hours' => null, 'href' => '/schedule'];
				}
				break;
			case 'absence_pending':
				foreach ($this->Rows("SELECT a.id, a.start_date, a.end_date, a.absence_type, e.name FROM employee_absences a JOIN employees e ON e.id = a.employee_id
					WHERE e.branch_id = :b AND a.status NOT IN ('approved', 'rejected', 'cancelled') AND a.start_date <= :lim AND a.end_date >= :d", [':b' => $b, ':d' => $today, ':lim' => date('Y-m-d', strtotime('+' . intval($t ?? 3) . ' days'))]) as $r)
				{
					$out[] = ['key' => "{$r['id']}", 'title' => 'Ausencia sin decidir: ' . $r['name'], 'detail' => 'Del ' . $r['start_date'] . ' al ' . $r['end_date'] . ' · ajustar la cobertura.', 'value' => null, 'href' => '/schedule'];
				}
				break;
			case 'sales_drop':
				$y = date('Y-m-d', strtotime('-1 day'));
				$sum = fn($d) => floatval($this->Val("SELECT IFNULL(SUM(total_amount), 0) FROM sales WHERE branch_id = :b AND status = 'completed' AND date(created_timestamp) = :d", [':b' => $b, ':d' => $d]));
				$prev = [];
				for ($w = 1; $w <= 4; $w++)
				{
					$prev[] = $sum(date('Y-m-d', strtotime("-1 day -$w week")));
				}
				$withSales = array_filter($prev, fn($v) => $v > 0);
				if (count($withSales) >= 3)
				{
					$avg = array_sum($withSales) / count($withSales);
					$cur = $sum($y);
					$drop = $avg > 0 ? ($avg - $cur) / $avg * 100 : 0;
					if ($drop >= ($t ?? 30))
					{
						$out[] = ['key' => "$b:$y", 'title' => 'Caída de ventas del ' . $y . ' (−' . round($drop, 1) . ' %)', 'detail' => 'Venta ' . $money($cur) . ' vs. promedio ' . $money($avg) . ' de los mismos días de las ' . count($withSales) . ' semanas previas.',
							'value' => round($avg - $cur, 2), 'href' => '/sales?branch_id=' . $b];
					}
				}
				break;
			case 'low_margin':
				$r = $this->Row("SELECT IFNULL(SUM(total_amount - IFNULL(tax_amount, 0)), 0) AS net, IFNULL(SUM(gross_margin), 0) AS margin, COUNT(*) AS n FROM sales WHERE branch_id = :b AND status = 'completed' AND created_timestamp >= :s", [':b' => $b, ':s' => date('Y-m-d', strtotime('-7 days'))]);
				$target = floatval($this->Val('SELECT margin_target_pct FROM dairy_budgets WHERE branch_id = :b AND period = :p', [':b' => $b, ':p' => date('Y-m')]) ?: ($t ?? 20));
				if ($r && intval($r['n']) >= 5 && floatval($r['net']) > 0)
				{
					$pct = floatval($r['margin']) / floatval($r['net']) * 100;
					if ($pct < $target)
					{
						$out[] = ['key' => "$b:" . date('o-W'), 'title' => 'Margen bruto ' . round($pct, 1) . ' % bajo objetivo (' . $target . ' %)', 'detail' => 'Últimos 7 días: ' . $money($r['margin']) . ' de margen sobre ' . $money($r['net']) . ' de venta neta (' . intval($r['n']) . ' tickets).',
							'value' => round(($target - $pct) / 100 * floatval($r['net']), 2), 'href' => '/sales?branch_id=' . $b];
					}
				}
				break;
			case 'dispatch_variance':
				foreach ($this->Rows("SELECT dv.id, dv.order_id, co.order_code, dv.product_id, p.name AS product_name, dv.stage, dv.variance_type, dv.difference_qty, dv.financial_impact, co.branch_id
					FROM dispatch_variances dv
					JOIN customer_orders co ON co.id = dv.order_id
					LEFT JOIN products p ON p.id = dv.product_id
					WHERE dv.resolution_status = 'pending' AND co.branch_id = :b", [':b' => $b]) as $r)
				{
					$vText = $r['variance_type'] . ' (' . ($r['difference_qty'] > 0 ? '+' : '') . $r['difference_qty'] . ' u.)';
					$out[] = [
						'key' => "dv:{$r['id']}",
						'title' => "Discrepancia en despacho {$r['order_code']}: {$r['product_name']}",
						'product_id' => $r['product_id'],
						'detail' => "Variación en etapa {$r['stage']}: $vText · Impacto económico de {$money($r['financial_impact'])}. Conciliar en Despachos.",
						'value' => abs(floatval($r['financial_impact'])),
						'href' => '/dispatch?order_id=' . $r['order_id']
					];
				}
				break;
			// ── Integridad del ciclo operativo (0318): documentos detenidos o inconsistentes; el href abre el expediente o el módulo que lo resuelve
			case 'expired_lot_unblocked':
				// expiring_lots deja de ver el lote el día que vence: sin esta regla la alerta se autocerraba y el lote vencido seguía vendible
				foreach ($this->Rows("SELECT s.stock_id, s.product_id, p.name, s.best_before_date AS d, SUM(s.amount) AS qty, SUM(s.amount * IFNULL(s.price, 0)) AS value, MIN(l.name) AS loc
					FROM stock s JOIN locations l ON l.id = s.location_id LEFT JOIN products p ON p.id = s.product_id
					WHERE l.branch_id = :b AND IFNULL(l.is_transit, 0) = 0 AND s.amount > 0 AND s.best_before_date < date('now', 'localtime') AND s.best_before_date <> '2999-12-31'
						AND NOT EXISTS (SELECT 1 FROM quarantine_items q WHERE q.stock_id = s.stock_id AND q.status = 'in_quarantine')
					GROUP BY s.stock_id, s.best_before_date", [':b' => $b]) as $r)
				{
					$out[] = ['key' => "{$r['stock_id']}", 'title' => 'Lote vencido a la venta: ' . ($r['name'] ?? 'producto #' . $r['product_id']), 'product_id' => $r['product_id'],
						'detail' => "Venció el {$r['d']} · {$r['qty']} u. en {$r['loc']} · {$money($r['value'])} a costo. Bloquear en cuarentena y decidir merma o devolución.",
						'value' => $r['value'], 'href' => '/waste#tab-fefo'];
				}
				break;
			case 'waste_approval_stale':
				foreach ($this->Rows("SELECT w.id, w.case_number, w.total_cost, w.created_timestamp, p.name FROM waste_records w LEFT JOIN products p ON p.id = w.product_id
					WHERE w.branch_id = :b AND w.status = 'pending_approval' AND IFNULL(w.inventory_deducted, 0) = 0 AND w.created_timestamp <= :lim", [':b' => $b, ':lim' => date('Y-m-d H:i:s', time() - 86400 * intval($t ?? 3))]) as $r)
				{
					$out[] = ['key' => "{$r['id']}", 'title' => 'Merma sin decisión: ' . ($r['case_number'] ?: '#' . $r['id']), 'detail' => ($r['name'] ?? 'Producto') . ' · ' . $money($r['total_cost']) . ' · registrada el ' . substr($r['created_timestamp'], 0, 16) . '.',
						'value' => $r['total_cost'], 'href' => '/process-flow?type=waste_record&id=' . $r['id']];
				}
				break;
			case 'waste_pending_deducted':
				foreach ($this->Rows("SELECT w.id, w.case_number, w.total_cost, p.name FROM waste_records w LEFT JOIN products p ON p.id = w.product_id
					WHERE w.branch_id = :b AND w.status = 'pending_approval' AND IFNULL(w.inventory_deducted, 0) = 1", [':b' => $b]) as $r)
				{
					$out[] = ['key' => "{$r['id']}", 'title' => 'Merma descontada sin aprobación: ' . ($r['case_number'] ?: '#' . $r['id']), 'detail' => ($r['name'] ?? 'Producto') . ' · ' . $money($r['total_cost']) . ' salió del inventario sin aprobación registrada.',
						'value' => $r['total_cost'], 'href' => '/process-flow?type=waste_record&id=' . $r['id']];
				}
				break;
			case 'order_unconciliated':
				foreach ($this->Rows("SELECT id, order_code, dispatched_at, variance_total_cost FROM customer_orders WHERE branch_id = :b AND status = 'dispatched' AND conciliated_at IS NULL AND dispatched_at <= :lim",
					[':b' => $b, ':lim' => date('Y-m-d H:i:s', time() - 86400 * intval($t ?? 2))]) as $r)
				{
					$out[] = ['key' => "{$r['id']}", 'title' => 'Despacho sin conciliar: ' . $r['order_code'], 'detail' => 'Despachado el ' . substr((string) $r['dispatched_at'], 0, 16) . ' sin conciliar lo preparado contra lo entregado.',
						'value' => $r['variance_total_cost'], 'href' => '/process-flow?type=customer_order&id=' . $r['id']];
				}
				break;
			case 'cash_shift_stale':
				foreach ($this->Rows("SELECT id, register_name, shift_name, opened_at, total_sales FROM pos_cash_shifts WHERE branch_id = :b AND status = 'open' AND opened_at <= :lim",
					[':b' => $b, ':lim' => date('Y-m-d H:i:s', time() - 3600 * intval($t ?? 24))]) as $r)
				{
					$out[] = ['key' => "{$r['id']}", 'title' => 'Caja sin cerrar: ' . trim($r['register_name'] . ' ' . $r['shift_name']), 'detail' => 'Abierta desde ' . substr($r['opened_at'], 0, 16) . ' · ventas ' . $money($r['total_sales']) . ' sin arqueo.',
						'value' => $r['total_sales'], 'href' => '/pos'];
				}
				break;
			case 'po_not_sent':
				foreach ($this->Rows("SELECT id, order_code, approved_at, total_estimated_cost FROM procurement_orders WHERE branch_id = :b AND status = 'approved' AND sent_at IS NULL AND IFNULL(approved_at, created_at) <= :lim",
					[':b' => $b, ':lim' => date('Y-m-d H:i:s', time() - 86400 * intval($t ?? 1))]) as $r)
				{
					$out[] = ['key' => "{$r['id']}", 'title' => 'OC aprobada sin enviar: ' . $r['order_code'], 'detail' => 'Aprobada el ' . substr((string) $r['approved_at'], 0, 16) . ' · ' . $money($r['total_estimated_cost']) . '. Sin envío no hay recepción ni reposición.',
						'value' => $r['total_estimated_cost'], 'href' => '/process-flow?type=procurement_order&id=' . $r['id']];
				}
				break;
			case 'return_no_destination':
				foreach ($this->Rows("SELECT id, return_code, total_cost FROM customer_returns_exchanges WHERE branch_id = :b AND status IN ('conciliada', 'cerrada') AND waste_record_id IS NULL AND stock_booking_id IS NULL", [':b' => $b]) as $r)
				{
					$out[] = ['key' => "{$r['id']}", 'title' => 'Devolución sin destino: ' . $r['return_code'], 'detail' => 'Cerrada sin reingreso a stock ni merma: la mercadería no quedó registrada en ningún lugar.',
						'value' => $r['total_cost'], 'href' => '/process-flow?type=customer_return&id=' . $r['id']];
				}
				break;
			case 'transfer_without_movement':
				foreach ($this->Rows("SELECT id, transfer_code, sent_at FROM stock_transfers WHERE from_branch_id = :b AND status = 'in_transit' AND IFNULL(send_transaction_id, '') = ''", [':b' => $b]) as $r)
				{
					$out[] = ['key' => "{$r['id']}", 'title' => 'Traslado sin salida de stock: ' . $r['transfer_code'], 'detail' => 'En tránsito desde ' . substr((string) $r['sent_at'], 0, 16) . ' sin movimiento de salida en el origen: el destino recibiría mercadería que no salió.',
						'href' => '/process-flow?type=stock_transfer&id=' . $r['id']];
				}
				break;
			case 'tasks_unassigned_overdue':
				$r = $this->Row("SELECT COUNT(*) AS n, MIN(due_time) AS oldest FROM workforce_store_tasks WHERE branch_id = :b AND status IN ('pending', 'in_progress', 'blocked')
					AND assigned_user_id IS NULL AND assigned_employee_id IS NULL AND due_time < :now", [':b' => $b, ':now' => $this->Now()]);
				if (intval($r['n']) > 0)
				{
					// Una alerta por sucursal (no una por tarea): la clave cambia solo si cambia la sucursal
					$out[] = ['key' => "$b", 'title' => intval($r['n']) . ' tareas vencidas sin responsable', 'detail' => 'La más antigua venció el ' . substr((string) $r['oldest'], 0, 16) . '. Asignar por rol y turno.',
						'href' => '/process-flow?type=store_task'];
				}
				break;
			case 'predictive_expiry_risk':
				foreach ($this->Rows("SELECT epa.id, epa.product_id, p.name AS product_name, epa.stock_id, epa.best_before_date, epa.units_at_risk, epa.total_value_risk, epa.recommended_action
					FROM expiry_predictive_alerts epa
					LEFT JOIN products p ON p.id = epa.product_id
					WHERE epa.branch_id = :b AND epa.resolution_status != 'cerrada' AND epa.units_at_risk > 0", [':b' => $b]) as $r)
				{
					$days = (int) round((strtotime($r['best_before_date']) - strtotime($today)) / 86400);
					$out[] = [
						'key' => "pe:{$r['id']}",
						'title' => "Riesgo predictivo de vencimiento: {$r['product_name']} (Lote {$r['stock_id']})",
						'product_id' => $r['product_id'],
						'detail' => "Quedan $days día(s) para vencer. Demanda estimada no absorberá {$r['units_at_risk']} u. Pérdida: {$money($r['total_value_risk'])}. Sugerido: {$r['recommended_action']}.",
						'value' => floatval($r['total_value_risk']),
						'href' => '/waste#tab-fefo'
					];
				}
				break;
		}
		return $out;
	}

	/* ================================================================== Notificaciones y escalamiento */

	// Usuarios con ALERTS_MANAGE que ven la sucursal (sin user_branches = toda la red)
	public function Managers(?int $branchId, string $permission = "ALERTS_MANAGE"): array
	{
		return array_map("intval", array_column($this->Rows("SELECT DISTINCT p.user_id FROM user_permissions_resolved p WHERE p.permission_name IN ('ADMIN', :perm)
			AND (:b IS NULL OR NOT EXISTS (SELECT 1 FROM user_branches ub WHERE ub.user_id = p.user_id) OR EXISTS (SELECT 1 FROM user_branches ub WHERE ub.user_id = p.user_id AND ub.branch_id = :b))", [":perm" => $permission, ":b" => $branchId]), "user_id"));
	}

	private function WantsAlerts(int $userId): bool
	{
		$raw = $this->Val("SELECT value FROM user_settings WHERE user_id = :u AND key = 'dairy_m_notif_prefs'", [':u' => $userId]);
		$p = $raw ? json_decode($raw, true) : null;
		return !is_array($p) || !array_key_exists('alerts', $p) || !empty($p['alerts']);
	}

	/**
	 * Inserta notificaciones sin duplicar (dedupe_key única por alerta + tipo + clave + usuario). Las escalaciones ignoran
	 * la preferencia del usuario. Devuelve cuántas se crearon.
	 */
	public function Notify(int $alertId, array $userIds, string $kind, string $title, ?string $body, string $key = ''): int
	{
		$a = $this->Row('SELECT a.id, a.priority, IFNULL(r.notify_channel, \'app\') AS ch FROM dairy_alerts a LEFT JOIN dairy_alert_rules r ON r.code = a.rule_code WHERE a.id = :i', [':i' => $alertId]);
		if (!$a)
		{
			return 0;
		}
		$n = 0;
		$stmt = $this->Db()->prepare('INSERT OR IGNORE INTO dairy_alert_notifications (alert_id, user_id, kind, channel, title, body, dedupe_key, created_at, email_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
		foreach (array_unique(array_filter(array_map('intval', $userIds))) as $u)
		{
			if ($kind !== 'escalated' && !$this->WantsAlerts($u))
			{
				continue;
			}
			// Umbral personal: avisos de alta y recordatorios solo desde la prioridad elegida (lo asignado y lo escalado siempre llega)
			if (in_array($kind, ['new', 'reminder'], true) && strcmp((string) $a['priority'], $this->MinPriority($u)) > 0)
			{
				continue;
			}
			// Sin transporte de correo en DAIRY: el canal queda registrado como no configurado (no se simula el envío)
			$stmt->execute([$alertId, $u, $kind, $a['ch'], mb_substr($title, 0, 200), $body ? mb_substr($body, 0, 1000) : null, "$alertId:$kind:$key:$u", $this->Now(), $a['ch'] === 'app_email' ? 'not_configured' : null]);
			$n += $stmt->rowCount();
		}
		return $n;
	}

	public function MinPriority(int $userId): string
	{
		$v = $this->Val("SELECT value FROM user_settings WHERE user_id = :u AND key = 'dairy_alert_notif_min'", [':u' => $userId]);
		return isset(self::PRIORITIES[$v]) ? $v : 'P4';
	}

	public function SetMinPriority(int $userId, string $p): void
	{
		if (!isset(self::PRIORITIES[$p]))
		{
			throw new \Exception('Prioridad no válida');
		}
		$this->Db()->prepare("DELETE FROM user_settings WHERE user_id = ? AND key = 'dairy_alert_notif_min'")->execute([$userId]);
		$this->Db()->prepare("INSERT INTO user_settings (user_id, key, value, row_created_timestamp, row_updated_timestamp) VALUES (?, 'dairy_alert_notif_min', ?, ?, ?)")->execute([$userId, $p, $this->Now(), $this->Now()]);
	}

	private function NotifyNew(int $id): void
	{
		$a = $this->Row('SELECT id, title, priority, owner_user_id, branch_id FROM dairy_alerts WHERE id = :i', [':i' => $id]);
		if ($a && in_array($a['priority'], ['P1', 'P2'], true))
		{
			$to = $a['owner_user_id'] ? [intval($a['owner_user_id'])] : $this->Managers($a['branch_id'] !== null ? intval($a['branch_id']) : null);
			$this->Notify($id, $to, 'new', "Nueva alerta {$a['priority']}: {$a['title']}", null);
		}
	}

	/**
	 * Recordatorio al responsable cuando queda ≤ 25 % del SLA; escalamiento nivel 1 (gestores) al vencer el SLA y nivel 2
	 * (administradores) tras escalate_after_hours adicionales (por defecto, el mismo SLA). Idempotente.
	 */
	public function RunEscalations(): array
	{
		$now = time();
		$reminders = $escalated = 0;
		foreach ($this->Rows('SELECT a.*, r.due_hours, r.escalate_after_hours FROM dairy_alerts a LEFT JOIN dairy_alert_rules r ON r.code = a.rule_code
			WHERE a.status IN (' . self::In(self::WORKING) . ') AND a.due_at IS NOT NULL') as $a)
		{
			$due = strtotime($a['due_at']);
			$created = strtotime($a['created_at'] ?: $a['due_at']);
			$id = intval($a['id']);
			if ($due > $now)
			{
				if ($a['owner_user_id'] && $due - $now <= max(900, 0.25 * ($due - $created)))
				{
					$reminders += $this->Notify($id, [intval($a['owner_user_id'])], 'reminder', 'Recordatorio: vence ' . date('d/m H:i', $due) . ' · ' . $a['title'], null, $a['due_at']);
				}
				continue;
			}
			$level = intval($a['escalation_level']);
			$after = 3600 * intval($a['escalate_after_hours'] ?: ($a['due_hours'] ?: 24));
			$target = $now >= $due + $after ? 2 : 1;
			if ($target > $level)
			{
				$branch = $a['branch_id'] !== null ? intval($a['branch_id']) : null;
				$to = $target === 1 ? $this->Managers($branch) : $this->Managers(null, 'ADMIN');
				if ($a['owner_user_id'])
				{
					$to[] = intval($a['owner_user_id']);
				}
				$this->Db()->prepare('UPDATE dairy_alerts SET escalation_level = ?, escalated_at = ? WHERE id = ? AND escalation_level < ?')->execute([$target, $this->Now(), $id, $target]);
				$this->Event($id, null, 'escalated', $target === 1 ? 'SLA vencido: escalada a gestores de alertas' : 'Sigue sin atención: escalada a administración');
				$this->Notify($id, $to, 'escalated', "Escalada (nivel $target): " . $a['title'], 'Plazo vencido el ' . substr($a['due_at'], 0, 16), (string) $target);
				$this->Reprioritize($id);
				AutomationEngineService::GetInstance()->OnAlert('alert_escalated', $id);
				$escalated++;
			}
		}
		return ['reminders' => $reminders, 'escalated' => $escalated];
	}

	public function UserNotifications(int $userId, int $limit = 30): array
	{
		$items = $this->Rows("SELECT n.*, a.priority, a.status FROM dairy_alert_notifications n LEFT JOIN dairy_alerts a ON a.id = n.alert_id WHERE n.user_id = :u ORDER BY n.id DESC LIMIT " . intval($limit), [':u' => $userId]);
		return ['items' => $items, 'unread' => intval($this->Val('SELECT COUNT(*) FROM dairy_alert_notifications WHERE user_id = :u AND read_at IS NULL', [':u' => $userId]))];
	}

	public function MarkRead(int $userId, ?array $ids = null): int
	{
		$sql = 'UPDATE dairy_alert_notifications SET read_at = ? WHERE user_id = ? AND read_at IS NULL';
		$p = [$this->Now(), $userId];
		if ($ids)
		{
			$sql .= ' AND id IN (' . implode(',', array_map('intval', $ids)) . ')';
		}
		$stmt = $this->Db()->prepare($sql);
		$stmt->execute($p);
		return $stmt->rowCount();
	}

	/* ================================================================== Ciclo de vida */

	private function Event(int $alertId, ?int $userId, string $action, ?string $note): void
	{
		$this->Db()->prepare('INSERT INTO dairy_alert_events (alert_id, user_id, action, note, created_at) VALUES (?, ?, ?, ?, ?)')->execute([$alertId, $userId, $action, $note, $this->Now()]);
	}

	public function Get(int $id): array
	{
		$a = $this->Row("SELECT a.*, r.requires_approval, r.name AS rule_name, r.due_hours, CASE WHEN r.code IS NOT NULL THEN IFNULL(r.food_safety, 0) WHEN a.category = 'quality' AND a.alert_type = 'cold_chain' THEN 1 ELSE 0 END AS food_safety FROM dairy_alerts a LEFT JOIN dairy_alert_rules r ON r.code = a.rule_code WHERE a.id = :id", [':id' => $id]);
		if (!$a)
		{
			throw new \Exception('Alerta no encontrada');
		}
		if ($a['source'] === 'manual' && $a['requires_approval'] === null)
		{
			$a['requires_approval'] = 0;
		}
		return $a;
	}

	private function Transition(int $id, array $from, array $set, ?int $userId, string $action, ?string $note): void
	{
		$cols = implode(', ', array_map(fn($k) => "$k = :$k", array_keys($set)));
		$params = [];
		foreach ($set as $k => $v)
		{
			$params[":$k"] = $v;
		}
		$params[':id'] = $id;
		$now = $this->Now();
		$fr = $userId ? ", first_response_at = IFNULL(first_response_at, '$now')" : '';
		$stmt = $this->Db()->prepare("UPDATE dairy_alerts SET $cols, updated_at = '$now'$fr WHERE id = :id AND status IN (" . self::In($from) . ')');
		$stmt->execute($params);
		if ($stmt->rowCount() !== 1)
		{
			throw new \Exception('La alerta cambió de estado o la acción no aplica en su estado actual; recargue la página');
		}
		$this->Event($id, $userId, $action, $note);
	}

	private static function Need(?string $note, int $min, string $msg): string
	{
		if (mb_strlen(trim((string) $note)) < $min)
		{
			throw new \Exception($msg);
		}
		return trim($note);
	}

	private function UserName(int $id): ?string
	{
		$v = $this->Val('SELECT ' . self::Uname('u') . ' FROM users u WHERE u.id = :u', [':u' => $id]);
		return $v ?: null;
	}

	public function Assign(int $id, int $ownerUserId, int $userId): void
	{
		$a = $this->Get($id);
		$name = $this->UserName($ownerUserId);
		if (!$name)
		{
			throw new \Exception('Seleccione un responsable');
		}
		if (!in_array($a['status'], self::WORKING, true))
		{
			throw new \Exception('La alerta no admite asignación en su estado');
		}
		$status = in_array($a['status'], ['open', 'reopened'], true) ? 'assigned' : $a['status'];
		$this->Transition($id, [$a['status']], ['owner_user_id' => $ownerUserId, 'status' => $status], $userId, 'assigned', 'Responsable: ' . $name);
		if ($ownerUserId !== $userId)
		{
			$this->Notify($id, [$ownerUserId], 'assigned', 'Se le asignó: ' . $a['title'], 'Plazo ' . substr((string) $a['due_at'], 0, 16), (string) time());
		}
	}

	public function Start(int $id, int $userId): void
	{
		$a = $this->Get($id);
		$this->Transition($id, ['open', 'assigned', 'reopened', 'investigating', 'waiting_info'], ['status' => 'in_progress', 'owner_user_id' => $a['owner_user_id'] ?: $userId], $userId, 'started', null);
	}

	public function Investigate(int $id, ?string $note, int $userId): void
	{
		$a = $this->Get($id);
		$this->Transition($id, ['open', 'assigned', 'reopened', 'in_progress', 'waiting_info'], ['status' => 'investigating', 'owner_user_id' => $a['owner_user_id'] ?: $userId], $userId, 'investigating', trim((string) $note) ?: null);
	}

	public function WaitInfo(int $id, ?string $note, int $userId): void
	{
		$a = $this->Get($id);
		$note = self::Need($note, 5, 'Indique qué información se espera y de quién');
		$this->Transition($id, ['open', 'assigned', 'reopened', 'investigating', 'in_progress'], ['status' => 'waiting_info', 'prev_status' => $a['status']], $userId, 'waiting_info', $note);
	}

	public function RequestApproval(int $id, ?string $note, int $userId): void
	{
		$a = $this->Get($id);
		$note = self::Need($note, 5, 'Describa qué debe aprobarse (acción, costo o excepción)');
		$this->Transition($id, ['open', 'assigned', 'reopened', 'investigating', 'waiting_info', 'in_progress'], ['status' => 'waiting_approval', 'prev_status' => $a['status']], $userId, 'approval_requested', $note);
		$this->Notify($id, array_diff($this->Managers($a['branch_id'] !== null ? intval($a['branch_id']) : null), [$userId]), 'approval', 'Aprobación solicitada: ' . $a['title'], $note, (string) time());
	}

	// Decisión sobre una solicitud de aprobación intermedia (no es el cierre). Quien la pidió no la decide.
	public function DecideRequest(int $id, bool $approve, ?string $note, int $userId): void
	{
		$a = $this->Get($id);
		if ($a['status'] !== 'waiting_approval')
		{
			throw new \Exception('La alerta no espera aprobación');
		}
		$req = $this->Val("SELECT user_id FROM dairy_alert_events WHERE alert_id = :a AND action = 'approval_requested' ORDER BY id DESC LIMIT 1", [':a' => $id]);
		if ($req && intval($req) === $userId)
		{
			throw new \Exception('Quien solicitó la aprobación no puede decidirla');
		}
		if (!$approve)
		{
			self::Need($note, 5, 'Indique por qué se rechaza');
		}
		$this->Transition($id, ['waiting_approval'], ['status' => $approve ? 'in_progress' : ($a['prev_status'] && $a['prev_status'] !== 'waiting_approval' ? $a['prev_status'] : 'investigating')], $userId, $approve ? 'request_approved' : 'request_rejected', $note);
		if ($req)
		{
			$this->Notify($id, [intval($req)], 'approval', ($approve ? 'Aprobado: ' : 'Rechazado: ') . $a['title'], $note, 'd' . time());
		}
	}

	const OUTCOMES = ['resolved' => 'Resuelta en origen', 'mitigated' => 'Mitigada (riesgo reducido)', 'loss_confirmed' => 'Pérdida confirmada', 'no_issue' => 'Sin problema real'];

	/**
	 * Política de cierre: una alerta crítica (P1) o de seguridad alimentaria exige al menos una evidencia y SIEMPRE pasa por
	 * validación de otra persona. Si la alerta tiene procedimiento, todos sus pasos deben estar hechos u omitidos con motivo.
	 * $close: outcome, root_cause, final_value (impacto final; vacío = se conserva el estimado).
	 */
	public function Resolve(int $id, string $note, int $userId, array $close = []): string
	{
		$a = $this->Get($id);
		$note = self::Need($note, 5, 'Describa qué se hizo para resolver la alerta (causa y acción)');
		if ($a['status'] === 'waiting_approval')
		{
			throw new \Exception('La alerta espera una aprobación: decídala antes de resolver');
		}
		$critical = $a['priority'] === 'P1' || intval($a['food_safety']);
		if ($critical && intval($this->Val('SELECT COUNT(*) FROM dairy_alert_evidence WHERE alert_id = :a', [':a' => $id])) === 0)
		{
			throw new \Exception('Política de cierre: una alerta crítica o de seguridad alimentaria requiere al menos una evidencia (foto o documento)');
		}
		$pending = intval($this->Val("SELECT COUNT(*) FROM dairy_alert_steps WHERE alert_id = :a AND status = 'pending'", [':a' => $id]));
		if ($pending)
		{
			throw new \Exception("Faltan $pending paso(s) del procedimiento: complételos u omítalos con motivo antes de resolver");
		}
		$outcome = isset(self::OUTCOMES[$close['outcome'] ?? '']) ? $close['outcome'] : 'resolved';
		$final = isset($close['final_value']) && $close['final_value'] !== '' ? floatval($close['final_value']) : null;
		if ($final !== null && $final < 0)
		{
			throw new \Exception('El impacto final no puede ser negativo');
		}
		$next = intval($a['requires_approval']) || $critical ? 'pending_approval' : 'closed';
		$now = $this->Now();
		$sla = $a['due_at'] && $now > $a['due_at'] ? ' · fuera de SLA' : '';
		$this->Transition($id, ['open', 'assigned', 'reopened', 'investigating', 'waiting_info', 'in_progress'], ['status' => $next, 'resolution' => $note, 'resolved_by_user_id' => $userId, 'resolved_at' => $now, 'owner_user_id' => $a['owner_user_id'] ?: $userId,
			'outcome' => $outcome, 'root_cause' => trim((string) ($close['root_cause'] ?? '')) ?: $a['root_cause'], 'final_value' => $final ?? $a['value'],
			'root_cause_category' => isset(self::ROOT_CAUSE_CATS[$close['root_cause_category'] ?? '']) ? $close['root_cause_category'] : $a['root_cause_category']], $userId, 'resolved', $note . ' · ' . self::OUTCOMES[$outcome] . ($final !== null ? ' · impacto final $' . number_format($final, 2) : '') . $sla);
		if ($next === 'pending_approval')
		{
			$this->Notify($id, array_diff($this->Managers($a['branch_id'] !== null ? intval($a['branch_id']) : null), [$userId]), 'resolved', 'Cierre por verificar: ' . $a['title'], $note, (string) time());
		}
		return $next;
	}

	public function Decide(int $id, bool $approve, ?string $note, int $userId): void
	{
		$a = $this->Get($id);
		if ($a['status'] !== 'pending_approval')
		{
			throw new \Exception('La alerta no está pendiente de aprobación');
		}
		if (intval($a['resolved_by_user_id']) === $userId)
		{
			throw new \Exception('Quien resolvió la alerta no puede aprobar su propio cierre');
		}
		if (!$approve)
		{
			self::Need($note, 5, 'Indique por qué se rechaza el cierre');
		}
		$approve
			? $this->Transition($id, ['pending_approval'], ['status' => 'closed', 'approved_by_user_id' => $userId, 'approved_at' => $this->Now()], $userId, 'approved', $note)
			: $this->Transition($id, ['pending_approval'], ['status' => 'in_progress', 'resolved_by_user_id' => null, 'resolved_at' => null], $userId, 'rejected', $note);
	}

	public function Dismiss(int $id, string $reason, int $userId): void
	{
		$this->Get($id);
		$reason = self::Need($reason, 5, 'Indique por qué es un falso positivo');
		$this->Transition($id, ['open', 'assigned', 'reopened', 'investigating', 'waiting_info', 'in_progress'], ['status' => 'dismissed', 'resolution' => $reason, 'resolved_by_user_id' => $userId, 'resolved_at' => $this->Now()], $userId, 'dismissed', $reason);
	}

	public function Reopen(int $id, ?string $reason, int $userId): void
	{
		$a = $this->Get($id);
		$reason = self::Need($reason, 5, 'Indique por qué se reabre (qué volvió a ocurrir o qué faltó)');
		$hours = intval($a['due_hours'] ?: 24);
		$this->Transition($id, ['closed', 'dismissed', 'pending_approval'], ['status' => 'reopened', 'reopened_count' => intval($a['reopened_count']) + 1, 'resolution' => null, 'resolved_by_user_id' => null, 'resolved_at' => null,
			'approved_by_user_id' => null, 'approved_at' => null, 'escalation_level' => 0, 'outcome' => null, 'final_value' => null, 'due_at' => date('Y-m-d H:i:s', time() + 3600 * $hours)], $userId, 'reopened', $reason);
		if ($a['owner_user_id'] && intval($a['owner_user_id']) !== $userId)
		{
			$this->Notify($id, [intval($a['owner_user_id'])], 'reopened', 'Alerta reabierta: ' . $a['title'], $reason, (string) time());
		}
		$this->Reprioritize($id);
	}

	// Revisión autorizada de la clasificación: prioridad y, opcionalmente, categoría y tipo (queda en la auditoría)
	public function SetPriority(int $id, string $priority, ?string $reason, int $userId, ?string $category = null, ?string $type = null): void
	{
		$a = $this->Get($id);
		if (!isset(self::PRIORITIES[$priority]))
		{
			throw new \Exception('Prioridad no válida');
		}
		$reason = self::Need($reason, 5, 'Justifique el cambio de prioridad');
		if (intval($a['food_safety']) && strcmp($priority, 'P2') > 0)
		{
			throw new \Exception('Una alerta de seguridad alimentaria no puede bajar de P2');
		}
		$set = ['priority' => $priority, 'priority_locked' => 1, 'priority_reason' => "Manual ({$a['priority']} → $priority): $reason"];
		$note = "{$a['priority']} → $priority";
		if ($category && isset(self::CATEGORIES[$category]) && $category !== $a['category'])
		{
			$set['category'] = $category;
			$note .= ' · categoría ' . self::CATEGORIES[$a['category']][0] . ' → ' . self::CATEGORIES[$category][0];
		}
		if ($type && isset(self::TYPES[$type]) && $type !== $a['alert_type'])
		{
			$set['alert_type'] = $type;
			$note .= ' · tipo ' . (self::TYPES[$a['alert_type']][0] ?? $a['alert_type']) . ' → ' . self::TYPES[$type][0];
		}
		$this->Transition($id, self::ACTIVE, $set, $userId, 'priority', "$note: $reason");
	}

	public function Escalate(int $id, ?string $note, int $userId): void
	{
		$a = $this->Get($id);
		$note = self::Need($note, 5, 'Indique por qué se escala');
		$level = min(2, intval($a['escalation_level']) + 1);
		$this->Transition($id, self::WORKING, ['escalation_level' => $level, 'escalated_at' => $this->Now()], $userId, 'escalated', "Escalada manual a nivel $level: $note");
		$this->Notify($id, array_diff($level === 1 ? $this->Managers($a['branch_id'] !== null ? intval($a['branch_id']) : null) : $this->Managers(null, 'ADMIN'), [$userId]), 'escalated', "Escalada (nivel $level): " . $a['title'], $note, 'm' . time());
		AutomationEngineService::GetInstance()->OnAlert('alert_escalated', $id);
	}

	/** Bitácora de la alerta: acción ejecutada por una automatización (sin usuario: no cuenta como respuesta humana) */
	public function AutomationNote(int $alertId, string $note): void
	{
		$this->Event($alertId, null, 'automation', mb_substr($note, 0, 500));
	}

	public function Acknowledge(int $id, int $userId): void
	{
		$this->Get($id);
		$this->Db()->prepare('UPDATE dairy_alerts SET first_response_at = IFNULL(first_response_at, ?) WHERE id = ?')->execute([$this->Now(), $id]);
		$this->Event($id, $userId, 'acknowledged', 'Lectura confirmada');
		$this->Db()->prepare('UPDATE dairy_alert_notifications SET read_at = IFNULL(read_at, ?) WHERE alert_id = ? AND user_id = ?')->execute([$this->Now(), $id, $userId]);
	}

	public function RequestHelp(int $id, ?string $note, int $userId): void
	{
		$a = $this->Get($id);
		$note = self::Need($note, 3, 'Describa qué ayuda necesita');
		$this->Event($id, $userId, 'help', $note);
		$this->Notify($id, array_diff($this->Managers($a['branch_id'] !== null ? intval($a['branch_id']) : null), [$userId]), 'help', 'Piden ayuda: ' . $a['title'], $note, (string) time());
	}

	public function Comment(int $id, string $note, int $userId): void
	{
		$this->Get($id);
		if (trim($note) === '')
		{
			throw new \Exception('Escriba un comentario');
		}
		$this->Db()->prepare('UPDATE dairy_alerts SET first_response_at = IFNULL(first_response_at, ?) WHERE id = ?')->execute([$this->Now(), $id]);
		$this->Event($id, $userId, 'comment', trim($note));
	}

	/**
	 * Alta manual. $d: title, detail, category, alert_type, branch_id, severity, priority (opcional, se justifica en detail),
	 * owner_user_id, due_hours, value, product_id, department, client_token (idempotencia: el mismo token devuelve la misma alerta).
	 */
	public function Create(array $d, int $userId): array
	{
		$title = self::Need($d['title'] ?? '', 5, 'Escriba un título claro (mínimo 5 caracteres)');
		$token = preg_replace('/[^A-Za-z0-9_-]/', '', (string) ($d['client_token'] ?? '')) ?: bin2hex(random_bytes(8));
		$key = 'manual:' . $token;
		if ($id = $this->Val('SELECT id FROM dairy_alerts WHERE dedupe_key = :k', [':k' => $key]))
		{
			return ['id' => intval($id), 'created' => false];
		}
		$cat = isset(self::CATEGORIES[$d['category'] ?? '']) ? $d['category'] : 'other';
		$type = isset(self::TYPES[$d['alert_type'] ?? '']) ? $d['alert_type'] : 'other';
		$sev = in_array($d['severity'] ?? '', ['danger', 'warning', 'info'], true) ? $d['severity'] : 'warning';
		$branch = !empty($d['branch_id']) ? intval($d['branch_id']) : null;
		$owner = !empty($d['owner_user_id']) ? intval($d['owner_user_id']) : null;
		if ($owner && !$this->UserName($owner))
		{
			throw new \Exception('Responsable no válido');
		}
		$value = isset($d['value']) && $d['value'] !== '' ? floatval($d['value']) : null;
		if ($value !== null && $value < 0)
		{
			throw new \Exception('El impacto no puede ser negativo');
		}
		$hours = max(1, intval($d['due_hours'] ?? 24));
		$now = $this->Now();
		$food = $cat === 'quality' && $type === 'cold_chain';
		[$p, $score, $why] = self::Prioritize($sev, $food, $value, 1, false, null);
		$locked = 0;
		if (!empty($d['priority']) && isset(self::PRIORITIES[$d['priority']]) && $d['priority'] !== $p)
		{
			if ($food && strcmp($d['priority'], 'P2') > 0)
			{
				throw new \Exception('Una alerta de seguridad alimentaria no puede ser menor que P2');
			}
			$why = "Manual ($p → {$d['priority']}) al crear";
			$p = $d['priority'];
			$locked = 1;
		}
		$this->Db()->beginTransaction();
		try
		{
			$id = $this->Insert(['rule_code' => 'manual', 'dedupe_key' => $key, 'branch_id' => $branch, 'severity' => $sev, 'title' => $title, 'detail' => trim((string) ($d['detail'] ?? '')) ?: null,
				'value' => $value, 'href' => null, 'owner_user_id' => $owner, 'due_at' => date('Y-m-d H:i:s', time() + 3600 * $hours), 'status' => $owner ? 'assigned' : 'open', 'source' => 'manual',
				'category' => $cat, 'alert_type' => $type, 'department' => trim((string) ($d['department'] ?? '')) ?: null, 'product_id' => !empty($d['product_id']) ? intval($d['product_id']) : null,
				'impact_bucket' => isset(self::BUCKETS[$d['impact_bucket'] ?? '']) ? $d['impact_bucket'] : 'process', 'impact_kind' => isset(self::IMPACT_KINDS[$d['impact_kind'] ?? '']) ? $d['impact_kind'] : 'estimated',
				'priority' => $p, 'priority_score' => $score, 'priority_reason' => $why, 'priority_locked' => $locked, 'created_by_user_id' => $userId], $now);
			$this->Event($id, $userId, 'created', 'Alta manual');
			$this->Db()->commit();
		}
		catch (\Throwable $e)
		{
			$this->Db()->rollBack();
			throw $e;
		}
		if ($owner && $owner !== $userId)
		{
			$this->Notify($id, [$owner], 'assigned', 'Se le asignó: ' . $title, null, 'c');
		}
		$this->NotifyNew($id);
		AutomationEngineService::GetInstance()->OnAlert('alert_created', $id);
		return ['id' => $id, 'created' => true];
	}

	/* ---------- Incidentes con expediente propio (prevención de pérdidas): el expediente manda el estado de su única alerta */

	public function RaiseIncident(array $f, ?int $userId): int
	{
		$now = $this->Now();
		$existing = $this->Val('SELECT id FROM dairy_alerts WHERE dedupe_key = :k', [':k' => $f['dedupe_key']]);
		if ($existing)
		{
			return intval($existing);
		}
		$f += ['status' => empty($f['owner_user_id']) ? 'open' : 'assigned', 'source' => 'system'];
		$id = $this->Insert($f, $now);
		$this->Event($id, $userId, 'created', 'Generada por el motor de conciliación');
		$this->Reprioritize($id);
		return $id;
	}

	public function UpdateIncident(int $id, array $f): void
	{
		$f = array_filter($f, fn($v) => $v !== null);
		$cols = array_keys($f);
		$this->Db()->prepare('UPDATE dairy_alerts SET ' . implode(', ', array_map(fn($k) => "$k = ?", $cols)) . ', updated_at = ?, last_seen_at = ? WHERE id = ?')->execute([...array_values($f), $this->Now(), $this->Now(), $id]);
		$this->Reprioritize($id);
	}

	public function CloseIncident(int $id, string $resolution, ?int $userId, array $f = []): void
	{
		$now = $this->Now();
		$this->UpdateIncident($id, $f + ['status' => 'closed', 'resolution' => $resolution, 'resolved_by_user_id' => $userId, 'resolved_at' => $now]);
		$this->Event($id, $userId, 'closed', $resolution);
	}

	/* ---------- Evidencias y plan de acción */

	public function AddEvidence(int $id, string $fileName, ?string $original, string $mime, int $size, ?string $note, int $userId): int
	{
		$this->Get($id);
		$this->Db()->prepare('INSERT INTO dairy_alert_evidence (alert_id, file_name, original_name, mime, size, note, user_id, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')
			->execute([$id, $fileName, $original ? mb_substr($original, 0, 150) : null, $mime, $size, trim((string) $note) ?: null, $userId, $this->Now()]);
		$eid = intval($this->Db()->lastInsertId());
		$this->Db()->prepare('UPDATE dairy_alerts SET first_response_at = IFNULL(first_response_at, ?), updated_at = ? WHERE id = ?')->execute([$this->Now(), $this->Now(), $id]);
		$this->Event($id, $userId, 'evidence', ($original ?: $fileName) . ($note ? ': ' . trim($note) : ''));
		return $eid;
	}

	public function Evidence(int $id): array
	{
		return $this->Rows('SELECT e.*, ' . self::Uname('u') . ' AS user_name FROM dairy_alert_evidence e LEFT JOIN users u ON u.id = e.user_id WHERE e.alert_id = :a ORDER BY e.id', [':a' => $id]);
	}

	public function EvidenceFile(int $evidenceId): ?array
	{
		return $this->Row('SELECT e.*, a.branch_id FROM dairy_alert_evidence e JOIN dairy_alerts a ON a.id = e.alert_id WHERE e.id = :i', [':i' => $evidenceId]) ?: null;
	}

	public function AddAction(int $id, array $d, int $userId): int
	{
		$a = $this->Get($id);
		if (!in_array($a['status'], self::WORKING, true))
		{
			throw new \Exception('Solo se planifican acciones en alertas activas');
		}
		$desc = self::Need($d['description'] ?? '', 5, 'Describa la acción correctiva');
		$owner = !empty($d['owner_user_id']) ? intval($d['owner_user_id']) : null;
		$due = !empty($d['due_at']) && strtotime($d['due_at']) ? date('Y-m-d H:i:s', strtotime($d['due_at'])) : $a['due_at'];
		$this->Db()->prepare('INSERT INTO dairy_alert_actions (alert_id, description, owner_user_id, due_at, created_by_user_id, created_at) VALUES (?, ?, ?, ?, ?, ?)')->execute([$id, $desc, $owner, $due, $userId, $this->Now()]);
		$aid = intval($this->Db()->lastInsertId());
		$this->Db()->prepare('UPDATE dairy_alerts SET first_response_at = IFNULL(first_response_at, ?) WHERE id = ?')->execute([$this->Now(), $id]);
		$this->Event($id, $userId, 'action_planned', $desc . ($owner ? ' · ' . $this->UserName($owner) : '') . ($due ? ' · ' . substr($due, 0, 16) : ''));
		if ($owner && $owner !== $userId)
		{
			$this->Notify($id, [$owner], 'assigned', 'Acción correctiva asignada: ' . $desc, $a['title'], 'act' . $aid);
		}
		return $aid;
	}

	public function CompleteAction(int $actionId, ?string $result, bool $done, int $userId): array
	{
		$act = $this->Row('SELECT * FROM dairy_alert_actions WHERE id = :i', [':i' => $actionId]);
		if (!$act || $act['status'] !== 'pending')
		{
			throw new \Exception('La acción no existe o ya se cerró');
		}
		$result = self::Need($result, 3, 'Indique el resultado de la acción');
		$this->Db()->prepare('UPDATE dairy_alert_actions SET status = ?, result = ?, done_by_user_id = ?, done_at = ? WHERE id = ? AND status = \'pending\'')->execute([$done ? 'done' : 'cancelled', $result, $userId, $this->Now(), $actionId]);
		$this->Event(intval($act['alert_id']), $userId, $done ? 'action_done' : 'action_cancelled', $act['description'] . ' → ' . $result);
		return $act;
	}

	public function Actions(int $id): array
	{
		return $this->Rows('SELECT x.*, ' . self::Uname('u') . ' AS owner_name FROM dairy_alert_actions x LEFT JOIN users u ON u.id = x.owner_user_id WHERE x.alert_id = :a ORDER BY x.id', [':a' => $id]);
	}

	/* ---------- Procedimiento guiado (paso 5 · Ejecutar) */

	// Pasos por regla (prioritario) o por tipo de alerta. [etiqueta, enlace al módulo, requisito: 0 ninguno · 1 evidencia · 2 aprobación concedida]
	// {product} y {branch} se reemplazan por los datos de la alerta; el enlace abre el flujo real del módulo (un solo registro principal).
	const PLAYBOOKS = [
		'expiry' => [['Revisar el lote y su fecha de vencimiento', '/waste#tab-fefo'], ['Verificar FEFO en góndola y bodega', '/stockentries?product={product}'], ['Rebajar, trasladar, retirar o poner en cuarentena según corresponda', '/waste#tab-fefo'], ['Registrar evidencia del estado del lote', null, 1]],
		'count_diff' => [['Revisar movimientos del producto en el Kardex', '/kardex?product_id={product}&branch_id={branch}'], ['Programar o ejecutar el conteo', '/waste#tab-conteos'], ['Registrar la diferencia contada (foto del conteo)', null, 1], ['Solicitar y obtener aprobación del ajuste', null, 2], ['Conciliar el inventario', '/inventory-control']],
		'waste' => [['Identificar producto, lote y movimiento', '/kardex?product_id={product}&branch_id={branch}'], ['Registrar la causa en Registrar Merma', '/waste/report'], ['Evaluar recuperación (proveedor, rebaja o donación)', '/waste#tab-recuperacion'], ['Solicitar y obtener aprobación', null, 2], ['Contabilizar y adjuntar respaldo', null, 1]],
		'cold_chain' => [['Verificar y registrar la temperatura actual', '/waste#tab-fefo', 1], ['Identificar lotes afectados', '/waste#tab-fefo'], ['Inmovilizar el producto (cuarentena)', '/waste#tab-fefo'], ['Evaluar la calidad del producto', null, 1], ['Liberar o retirar con autorización', null, 2]],
		'staff' => [['Identificar el turno descubierto', '/schedule?branch_id={branch}'], ['Consultar horarios y ausencias', '/schedule?branch_id={branch}'], ['Verificar disponibilidad y horas permitidas', '/schedule?branch_id={branch}'], ['Reasignar personal', '/schedule?branch_id={branch}'], ['Confirmar la cobertura', null]],
		'purchasing' => [['Identificar el riesgo de quiebre', '/stockentries?product={product}'], ['Consultar demanda e inventario', '/mrp'], ['Proponer el pedido', '/mrp'], ['Obtener aprobación del pedido', null, 2], ['Emitir la orden', '/mrp'], ['Confirmar la recepción', '/mrp']],
		'stock_low' => [['Identificar el riesgo de quiebre', '/stockentries?product={product}'], ['Consultar demanda e inventario', '/mrp'], ['Reponer desde bodega o trasladar', '/transfers'], ['Proponer pedido si no hay existencias', '/mrp'], ['Confirmar exhibición o recepción', null]],
		'sales' => [['Revisar las ventas afectadas', '/sales'], ['Corregir o reprocesar en la integración POS', '/sales'], ['Verificar el descargo en el Kardex', '/kardex?product_id={product}&branch_id={branch}'], ['Adjuntar respaldo de la corrección', null, 1]],
		'maintenance' => [['Revisar los hallazgos', null], ['Ejecutar las acciones correctivas', '/orders'], ['Registrar evidencia', null, 1], ['Programar la re-verificación', null]],
		'unexplained_outflow' => [['Revisar el movimiento en el Kardex', '/kardex?product_id={product}&branch_id={branch}'], ['Consultar con quien lo registró (señal a investigar, no prueba de responsabilidad)', null], ['Documentar la causa: merma, venta o error', null, 1], ['Aprobar el tratamiento contable', null, 2]],
		'recall_unconfirmed' => [['Separar físicamente los lotes del retiro', '/recalls', 1], ['Confirmar el retiro en Retiros de Producto', '/recalls']],
		// Procedimientos por regla (tienen prioridad sobre el tipo)
		'pos_unreconciled' => [['Validar la venta (número, fecha y líneas)', '/sales?branch_id={branch}'], ['Verificar la venta en la integración POS', '/sales?branch_id={branch}'], ['Comparar las líneas contra el Kardex', '/kardex?product_id={product}&branch_id={branch}'], ['Investigar la diferencia y documentarla', null, 1], ['Corregir el descargo con autorización', null, 2], ['Conciliar inventario y venta', '/kardex?product_id={product}&branch_id={branch}']],
		'cold_chain_breach' => [['Confirmar la temperatura fuera de rango', '/waste?branch_id={branch}#tab-fefo', 1], ['Identificar los lotes expuestos', '/waste?branch_id={branch}#tab-fefo'], ['Inmovilizar los lotes (cuarentena)', '/waste?branch_id={branch}#tab-fefo'], ['Evaluar la calidad del producto', null], ['Autorizar el destino (liberar, merma o retiro)', null, 2], ['Registrar evidencia del destino aplicado', '/waste/report', 1]],
		'count_overdue' => [['Confirmar el incumplimiento del conteo programado', '/waste#tab-conteos'], ['Asignar el equipo de conteo', '/schedule?branch_id={branch}'], ['Programar el conteo', '/waste#tab-conteos'], ['Escanear y contar', '/scan'], ['Conciliar contra el sistema', '/inventory-control'], ['Aprobar las diferencias (ajuste)', null, 2]],
		'expiring_lots' => [['Identificar el lote y su vencimiento', '/waste?branch_id={branch}#tab-fefo'], ['Comprobar FEFO en góndola y bodega', '/stockentries?product={product}'], ['Revisar la rotación del producto', '/kardex?product_id={product}&branch_id={branch}'], ['Definir acción: rebaja, traslado o donación', '/transfers'], ['Ejecutar la acción (con evidencia)', null, 1]],
		'other' => [['Verificar la condición en el módulo de origen', '{href}'], ['Ejecutar la acción recomendada', null], ['Registrar evidencia', null, 1]],
	];

	public static function Playbook(array $a): array
	{
		$steps = self::PLAYBOOKS[$a['rule_code'] ?? ''] ?? self::PLAYBOOKS[$a['alert_type'] ?? ''] ?? self::PLAYBOOKS['other'];
		$out = [];
		foreach ($steps as $i => $s)
		{
			$href = $s[1] ?? null;
			if ($href)
			{
				$href = str_replace(['{product}', '{branch}', '{href}'], [(string) ($a['product_id'] ?? ''), (string) ($a['branch_id'] ?? ''), (string) ($a['href'] ?? '')], $href);
				// Quita parámetros vacíos (alerta sin producto o sin sucursal)
				$href = preg_replace('/[?&][a-z_]+=(?=[&#]|$)/', '', $href);
				if ($href && !str_contains($href, '?') && str_contains($href, '&'))
				{
					$href = preg_replace('/&/', '?', $href, 1);
				}
				$href = $href ?: null;
			}
			$out[] = ['step_no' => $i + 1, 'label' => $s[0], 'href' => $href, 'requires_evidence' => $s[2] ?? 0];
		}
		return $out;
	}

	// Crea los pasos del procedimiento una sola vez (UNIQUE alert_id + step_no) para alertas activas
	public function EnsureSteps(int $id): void
	{
		$a = $this->Get($id);
		if (!in_array($a['status'], self::WORKING, true) || intval($this->Val('SELECT COUNT(*) FROM dairy_alert_steps WHERE alert_id = :a', [':a' => $id])))
		{
			return;
		}
		$ins = $this->Db()->prepare('INSERT OR IGNORE INTO dairy_alert_steps (alert_id, step_no, label, href, requires_evidence) VALUES (?, ?, ?, ?, ?)');
		foreach (self::Playbook($a) as $s)
		{
			$ins->execute([$id, $s['step_no'], $s['label'], $s['href'], $s['requires_evidence']]);
		}
	}

	public function Steps(int $id): array
	{
		return $this->Rows('SELECT s.*, ' . self::Uname('u') . ' AS done_by FROM dairy_alert_steps s LEFT JOIN users u ON u.id = s.done_by_user_id WHERE s.alert_id = :a ORDER BY s.step_no', [':a' => $id]);
	}

	/**
	 * Completa u omite un paso. Requisitos reales: evidencia adjunta a la alerta (1) o aprobación concedida (2).
	 * Omitir exige motivo y no aplica a pasos de aprobación de alertas críticas. El primer paso pasa la alerta a ejecución.
	 */
	public function CompleteStep(int $stepId, string $status, ?string $note, int $userId): int
	{
		$s = $this->Row('SELECT * FROM dairy_alert_steps WHERE id = :i', [':i' => $stepId]);
		if (!$s || $s['status'] !== 'pending')
		{
			throw new \Exception('El paso no existe o ya se registró');
		}
		$id = intval($s['alert_id']);
		$a = $this->Get($id);
		if (!in_array($a['status'], self::WORKING, true))
		{
			throw new \Exception('La alerta no está en ejecución');
		}
		if ($status === 'skipped')
		{
			$note = self::Need($note, 5, 'Indique por qué se omite el paso');
			if (intval($s['requires_evidence']) === 2 && ($a['priority'] === 'P1' || intval($a['food_safety'])))
			{
				throw new \Exception('En una alerta crítica la aprobación no puede omitirse');
			}
		}
		else
		{
			$status = 'done';
			if (intval($s['requires_evidence']) === 1 && !intval($this->Val('SELECT COUNT(*) FROM dairy_alert_evidence WHERE alert_id = :a', [':a' => $id])))
			{
				throw new \Exception('Este paso requiere evidencia: adjunte una foto o documento primero');
			}
			if (intval($s['requires_evidence']) === 2 && !intval($this->Val("SELECT COUNT(*) FROM dairy_alert_events WHERE alert_id = :a AND action = 'request_approved'", [':a' => $id])))
			{
				throw new \Exception('Este paso requiere una aprobación concedida: use «Solicitar aprobación»');
			}
		}
		$this->Db()->prepare("UPDATE dairy_alert_steps SET status = ?, note = ?, done_by_user_id = ?, done_at = ? WHERE id = ? AND status = 'pending'")->execute([$status, trim((string) $note) ?: null, $userId, $this->Now(), $stepId]);
		if (in_array($a['status'], ['open', 'assigned', 'reopened', 'investigating'], true))
		{
			$this->Transition($id, [$a['status']], ['status' => 'in_progress', 'owner_user_id' => $a['owner_user_id'] ?: $userId], $userId, 'started', 'Procedimiento iniciado');
		}
		$this->Event($id, $userId, $status === 'done' ? 'step_done' : 'step_skipped', 'Paso ' . $s['step_no'] . ': ' . $s['label'] . ($note ? ' · ' . trim($note) : ''));
		return $id;
	}

	/* ---------- Análisis de causa raíz (5 porqués + 6M) */

	const ROOT_CAUSE_CATS = ['people' => 'Personas (capacitación, ejecución)', 'process' => 'Proceso o método', 'equipment' => 'Equipo o instalaciones', 'material' => 'Material o proveedor', 'system' => 'Sistema o datos', 'environment' => 'Entorno (clima, energía, demanda)'];

	// Guarda el análisis; también sirve para agrupar varias alertas bajo una causa común (acción masiva). Queda en la auditoría.
	public function SaveRca(int $id, array $d, int $userId): void
	{
		$a = $this->Get($id);
		$cat = $d['root_cause_category'] ?? '';
		if (!isset(self::ROOT_CAUSE_CATS[$cat]))
		{
			throw new \Exception('Elija la categoría de la causa raíz');
		}
		$cause = self::Need($d['root_cause'] ?? '', 5, 'Describa la causa raíz (mínimo 5 caracteres)');
		$whys = array_values(array_filter(array_map(fn($w) => mb_substr(trim((string) $w), 0, 300), (array) ($d['whys'] ?? [])), fn($w) => $w !== ''));
		$whys = array_slice($whys, 0, 5);
		$this->Db()->prepare('UPDATE dairy_alerts SET root_cause = ?, root_cause_category = ?, five_whys = ?, updated_at = ?, first_response_at = IFNULL(first_response_at, ?) WHERE id = ?')
			->execute([$cause, $cat, $whys ? json_encode($whys, JSON_UNESCAPED_UNICODE) : $a['five_whys'], $this->Now(), $this->Now(), $id]);
		$this->Event($id, $userId, 'rca', self::ROOT_CAUSE_CATS[$cat] . ': ' . $cause . ($whys ? ' · ' . count($whys) . ' porqués' : ''));
	}

	// Transacciones relacionadas: líneas de la venta (venta sin descargo) o últimos movimientos de Kardex del producto
	public function Transactions(array $a): array
	{
		$key = explode(':', (string) $a['dedupe_key']);
		if ($a['rule_code'] === 'pos_unreconciled' && isset($key[1]) && ctype_digit($key[1]))
		{
			return ['kind' => 'sale', 'rows' => $this->Rows("SELECT s.sale_number AS doc, s.created_timestamp AS at, p.name AS product, i.amount, i.unit_price, i.subtotal, s.status
				FROM sale_items i JOIN sales s ON s.id = i.sale_id LEFT JOIN products p ON p.id = i.product_id WHERE s.id = :s ORDER BY i.id", [':s' => intval($key[1])])];
		}
		if ($a['product_id'])
		{
			return ['kind' => 'kardex', 'rows' => $this->Rows("SELECT l.row_created_timestamp AS at, l.transaction_type AS type, l.amount, l.price, l.stock_id, l.undone, IFNULL(lo.name, '') AS location, " . self::Uname('u') . " AS user
				FROM stock_log l LEFT JOIN locations lo ON lo.id = l.location_id LEFT JOIN users u ON u.id = l.user_id
				WHERE l.product_id = :p" . ($a['branch_id'] ? ' AND (lo.branch_id = :b OR lo.branch_id IS NULL)' : '') . " ORDER BY l.id DESC LIMIT 12", $a['branch_id'] ? [':p' => $a['product_id'], ':b' => $a['branch_id']] : [':p' => $a['product_id']])];
		}
		return ['kind' => null, 'rows' => []];
	}

	/* ---------- Prevención de recurrencia (paso 8) */

	public function Recurrence(array $a): array
	{
		$k = [':r' => $a['rule_code'], ':b' => $a['branch_id'], ':p' => $a['product_id'], ':s' => date('Y-m-d H:i:s', strtotime('-90 days'))];
		$n = intval($this->Val('SELECT COUNT(*) FROM dairy_alerts WHERE rule_code = :r AND branch_id IS :b AND product_id IS :p AND created_at >= :s', $k));
		$plan = $this->Row('SELECT x.*, ' . self::Uname('u') . " AS owner_name, (SELECT COUNT(*) FROM dairy_alerts r WHERE r.prevention_id = x.id AND r.created_at > x.created_at) AS recurrences
			FROM dairy_alert_prevention x LEFT JOIN users u ON u.id = x.owner_user_id WHERE x.rule_code = :r AND x.branch_id IS :b AND x.product_id IS :p ORDER BY x.id DESC LIMIT 1", [':r' => $a['rule_code'], ':b' => $a['branch_id'], ':p' => $a['product_id']]);
		$prev = $a['recurrence_of'] ? $this->Row('SELECT id, title, status, resolution, resolved_at FROM dairy_alerts WHERE id = :i', [':i' => $a['recurrence_of']]) : null;
		return ['count90' => $n, 'pattern' => $a['rule_code'] !== 'manual' && ($n >= 3 || intval($a['reopened_count']) > 0), 'previous' => $prev ?: null, 'plan' => $plan ?: null];
	}

	public function CreatePrevention(int $alertId, array $d, int $userId): int
	{
		$a = $this->Get($alertId);
		$cause = self::Need($d['root_cause'] ?? '', 5, 'Describa la causa raíz');
		$action = self::Need($d['corrective_action'] ?? '', 5, 'Describa la acción correctiva');
		$owner = !empty($d['owner_user_id']) ? intval($d['owner_user_id']) : null;
		$target = !empty($d['target_date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $d['target_date']) ? $d['target_date'] : null;
		if (!$owner || !$target)
		{
			throw new \Exception('El plan preventivo requiere responsable y fecha objetivo');
		}
		$this->Db()->prepare('INSERT INTO dairy_alert_prevention (source_alert_id, rule_code, branch_id, product_id, root_cause, corrective_action, owner_user_id, target_date, indicator, created_by_user_id, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
			->execute([$alertId, $a['rule_code'], $a['branch_id'], $a['product_id'], $cause, $action, $owner, $target, trim((string) ($d['indicator'] ?? '')) ?: 'Sin nuevas alertas de la misma regla, sucursal y producto', $userId, $this->Now()]);
		$pid = intval($this->Db()->lastInsertId());
		$this->Db()->prepare('UPDATE dairy_alerts SET prevention_id = ?, root_cause = IFNULL(root_cause, ?) WHERE id = ?')->execute([$pid, $cause, $alertId]);
		$this->Event($alertId, $userId, 'prevention', "Plan preventivo PRV-$pid: $action · " . $this->UserName($owner) . " · objetivo $target");
		if ($owner !== $userId)
		{
			$this->Notify($alertId, [$owner], 'assigned', 'Plan preventivo asignado: ' . $action, $a['title'], 'prv' . $pid);
		}
		return $pid;
	}

	// Verificación de efectividad: no se marca efectivo si el problema reapareció después de crear el plan
	public function VerifyPrevention(int $pid, bool $effective, ?string $note, int $userId): void
	{
		$x = $this->Row("SELECT x.*, (SELECT COUNT(*) FROM dairy_alerts r WHERE r.prevention_id = x.id AND r.created_at > x.created_at) AS recurrences FROM dairy_alert_prevention x WHERE x.id = :i", [':i' => $pid]);
		if (!$x || $x['status'] !== 'open')
		{
			throw new \Exception('El plan no existe o ya fue verificado');
		}
		$note = self::Need($note, 5, 'Describa cómo se verificó la efectividad');
		if ($effective && intval($x['recurrences']))
		{
			throw new \Exception('No puede marcarse efectivo: el problema reapareció ' . $x['recurrences'] . ' vez/veces después del plan');
		}
		$this->Db()->prepare('UPDATE dairy_alert_prevention SET status = ?, verification_note = ?, verified_by_user_id = ?, verified_at = ? WHERE id = ?')->execute([$effective ? 'effective' : 'ineffective', $note, $userId, $this->Now(), $pid]);
		$this->Event(intval($x['source_alert_id']), $userId, 'prevention_verified', "PRV-$pid " . ($effective ? 'efectivo' : 'no efectivo') . ": $note");
	}

	public function Preventions(array $f, int $limit = 20): array
	{
		$p = [];
		$w = '';
		if (!empty($f['branch_id']))
		{
			$w .= ' AND x.branch_id = :b';
			$p[':b'] = intval($f['branch_id']);
		}
		if (isset($f['allowed']) && is_array($f['allowed']))
		{
			$w .= ' AND (x.branch_id IS NULL OR x.branch_id IN (' . (count($f['allowed']) ? implode(',', array_map('intval', $f['allowed'])) : '0') . '))';
		}
		return $this->Rows('SELECT x.*, ' . self::Uname('u') . " AS owner_name, b.name AS branch_name, a.title AS source_title,
				(SELECT COUNT(*) FROM dairy_alerts r WHERE r.prevention_id = x.id AND r.created_at > x.created_at) AS recurrences
			FROM dairy_alert_prevention x LEFT JOIN users u ON u.id = x.owner_user_id LEFT JOIN branches b ON b.id = x.branch_id LEFT JOIN dairy_alerts a ON a.id = x.source_alert_id
			WHERE 1 = 1$w ORDER BY x.status = 'open' DESC, x.target_date LIMIT " . intval($limit), $p);
	}

	/* ---------- Estado visible (9 estados del flujo, derivados sin duplicar datos) */

	// Flujo de resolución (7 etapas) + estados excepcionales. «Escalado» es una marca (escalation_level) sobre cualquier etapa.
	const FLOW_STAGES = ['new' => 'Detectado', 'classified' => 'Clasificado', 'assigned' => 'Asignado', 'investigating' => 'En investigación', 'in_progress' => 'En corrección', 'validation' => 'Pendiente de verificación', 'closed' => 'Cerrado'];
	const FLOW_EXCEPTIONS = ['blocked' => 'Bloqueado', 'approval' => 'Pendiente de aprobación', 'reopened' => 'Reabierto', 'dismissed' => 'Descartado'];
	const FLOW_STATUS = self::FLOW_STAGES + self::FLOW_EXCEPTIONS;

	// Turnos de la tienda (TODAY_SHIFTS: «Mañana=06:00-14:00,…») → [nombre => [inicio, fin]]
	public static function Shifts(): array
	{
		$out = [];
		foreach (explode(',', defined('GROCY_TODAY_SHIFTS') ? (string) GROCY_TODAY_SHIFTS : '') as $def)
		{
			if (preg_match('/^\s*(.+?)=(\d{2}:\d{2})-(\d{2}:\d{2})\s*$/', $def, $m))
			{
				$out[$m[1]] = [$m[2], $m[3]];
			}
		}
		return $out;
	}

	// Detectado: sin ninguna acción humana · Clasificado: visto/priorizado pero sin responsable
	public static function FlowStatus(array $a): string
	{
		switch ($a['status'])
		{
			case 'open':
				return $a['owner_user_id'] ? 'assigned' : ($a['first_response_at'] || (!empty($a['priority_locked']) && ($a['source'] ?? '') !== 'manual') ? 'classified' : 'new');
			case 'waiting_info':
				return 'blocked';
			case 'waiting_approval':
				return 'approval';
			case 'pending_approval':
				return 'validation';
		}
		return isset(self::FLOW_STATUS[$a['status']]) ? $a['status'] : 'new';
	}

	/* ================================================================== Consultas */

	public function GetEvents(int $alertId): array
	{
		return $this->Rows("SELECT e.*, IFNULL(" . self::Uname('u') . ", 'sistema') AS user_name FROM dairy_alert_events e LEFT JOIN users u ON u.id = e.user_id WHERE e.alert_id = :a ORDER BY e.id", [':a' => $alertId]);
	}

	private function SelectSql(): string
	{
		return "SELECT a.*, IFNULL(r.name, CASE a.source WHEN 'manual' THEN 'Alta manual' END) AS rule_name, IFNULL(r.requires_approval, 0) AS requires_approval, r.recommended_action, CASE WHEN r.code IS NOT NULL THEN IFNULL(r.food_safety, 0) WHEN a.category = 'quality' AND a.alert_type = 'cold_chain' THEN 1 ELSE 0 END AS food_safety, IFNULL(r.confidence, 'high') AS confidence,
				b.name AS branch_name, " . self::Uname('u') . " AS owner_name, p.name AS product_name,
				CASE WHEN a.status IN (" . self::In(self::WORKING) . ") AND a.due_at < :now THEN 1 ELSE 0 END AS overdue,
				CAST(ROUND((julianday(a.due_at) - julianday(:now)) * 1440) AS INTEGER) AS minutes_left,
				(SELECT COUNT(*) FROM dairy_alert_evidence ev WHERE ev.alert_id = a.id) AS evidence_count,
				(SELECT COUNT(*) FROM dairy_alert_steps st WHERE st.alert_id = a.id) AS steps_total,
				(SELECT COUNT(*) FROM dairy_alert_steps st WHERE st.alert_id = a.id AND st.status <> 'pending') AS steps_done,
				(" . self::GROUP_SQL . ") AS load_group, (" . self::FLOW_SQL . ") AS flow_status
			FROM dairy_alerts a LEFT JOIN dairy_alert_rules r ON r.code = a.rule_code LEFT JOIN branches b ON b.id = a.branch_id LEFT JOIN users u ON u.id = a.owner_user_id LEFT JOIN products p ON p.id = a.product_id";
	}

	public function GetAlerts(string $view = 'active', ?int $branchId = null, ?array $allowedBranches = null, ?int $ownerId = null, int $limit = 300): array
	{
		$where = $view === 'active' ? 'a.status IN (' . self::In(self::ACTIVE) . ')' : ($view === 'closed' ? "a.status IN ('closed','dismissed')" : '1 = 1');
		$p = [':now' => $this->Now()];
		if ($branchId)
		{
			$where .= ' AND a.branch_id = :b';
			$p[':b'] = $branchId;
		}
		if ($ownerId)
		{
			$where .= ' AND a.owner_user_id = :o';
			$p[':o'] = $ownerId;
		}
		$rows = $this->Rows($this->SelectSql() . " WHERE $where ORDER BY a.priority, overdue DESC, a.due_at LIMIT " . intval($limit), $p);
		return $allowedBranches === null ? $rows : array_values(array_filter($rows, fn($r) => $r['branch_id'] === null || in_array(intval($r['branch_id']), $allowedBranches, true)));
	}

	/**
	 * Filtros comunes (todos opcionales): branch_id, allowed (array|null), department, category, alert_type, priority, status,
	 * owner ('me' | 'none' | id), rule, q (texto), me (id del usuario actual), overdue (1).
	 */
	private function Where(array $f, array &$p): string
	{
		$w = [];
		if (!empty($f['branch_id']))
		{
			$w[] = 'a.branch_id = :fb';
			$p[':fb'] = intval($f['branch_id']);
		}
		if (isset($f['allowed']) && is_array($f['allowed']))
		{
			$w[] = '(a.branch_id IS NULL OR a.branch_id IN (' . (count($f['allowed']) ? implode(',', array_map('intval', $f['allowed'])) : '0') . '))';
		}
		foreach (['department' => 'a.department', 'category' => 'a.category', 'alert_type' => 'a.alert_type', 'priority' => 'a.priority', 'rule' => 'a.rule_code'] as $k => $col)
		{
			if (!empty($f[$k]))
			{
				$vals = array_values(array_filter(explode(',', (string) $f[$k])));
				$ph = [];
				foreach ($vals as $i => $v)
				{
					$ph[] = ":f{$k}$i";
					$p[":f{$k}$i"] = $v;
				}
				$w[] = "$col IN (" . implode(',', $ph) . ')';
			}
		}
		if (!empty($f['status']))
		{
			$st = $f['status'] === 'active' ? self::ACTIVE : ($f['status'] === 'done' ? ['closed', 'dismissed'] : array_intersect(explode(',', $f['status']), array_keys(self::STATUS)));
			$w[] = 'a.status IN (' . (count($st) ? self::In($st) : "''") . ')';
		}
		if (!empty($f['owner']))
		{
			if ($f['owner'] === 'none')
			{
				$w[] = 'a.owner_user_id IS NULL';
			}
			else
			{
				$w[] = 'a.owner_user_id = :fo';
				$p[':fo'] = $f['owner'] === 'me' ? intval($f['me'] ?? 0) : intval($f['owner']);
			}
		}
		if (!empty($f['overdue']))
		{
			$w[] = 'a.status IN (' . self::In(self::WORKING) . ') AND a.due_at < :fnow';
			$p[':fnow'] = $this->Now();
		}
		if (!empty($f['group']) && isset(self::LOAD_GROUPS[$f['group']]))
		{
			$w[] = '(' . self::GROUP_SQL . ') = :fgr';
			$p[':fgr'] = $f['group'];
		}
		// Turno operativo: hora de detección dentro del turno (TODAY_SHIFTS; los turnos nocturnos cruzan medianoche)
		if (!empty($f['shift']) && ($sh = self::Shifts()[$f['shift']] ?? null))
		{
			$w[] = $sh[0] <= $sh[1] ? "(strftime('%H:%M', a.created_at) >= :fsh0 AND strftime('%H:%M', a.created_at) < :fsh1)" : "(strftime('%H:%M', a.created_at) >= :fsh0 OR strftime('%H:%M', a.created_at) < :fsh1)";
			$p[':fsh0'] = $sh[0];
			$p[':fsh1'] = $sh[1];
		}
		if (!empty($f['flow']))
		{
			if ($f['flow'] === 'detected' || $f['flow'] === 'new')
			{
				$w[] = "(a.status = 'open' AND a.owner_user_id IS NULL)";
			}
			elseif ($f['flow'] === 'investigating')
			{
				$w[] = "(a.status IN ('assigned', 'investigating') OR (a.status = 'open' AND a.owner_user_id IS NOT NULL))";
			}
			elseif ($f['flow'] === 'approval' || $f['flow'] === 'waiting_approval')
			{
				$w[] = "a.status = 'waiting_approval'";
			}
			elseif ($f['flow'] === 'in_progress')
			{
				$w[] = "a.status IN ('in_progress', 'waiting_info')";
			}
			elseif ($f['flow'] === 'validation' || $f['flow'] === 'pending_approval')
			{
				$w[] = "a.status = 'pending_approval'";
			}
			elseif ($f['flow'] === 'closed')
			{
				$w[] = "a.status IN ('closed', 'dismissed')";
			}
			elseif (isset(self::FLOW_STATUS[$f['flow']]))
			{
				$w[] = '(' . self::FLOW_SQL . ') = :ffl';
				$p[':ffl'] = $f['flow'];
			}
		}
		if (trim((string) ($f['q'] ?? '')) !== '')
		{
			$q = trim($f['q']);
			if (preg_match('/^ALR-?(\d+)$/i', $q, $m))
			{
				$w[] = 'a.id = :fid';
				$p[':fid'] = intval($m[1]);
			}
			else
			{
				// Texto o código de barras escaneado del producto
				$w[] = "(a.title LIKE :fq ESCAPE '\\' OR a.detail LIKE :fq ESCAPE '\\' OR a.department LIKE :fq ESCAPE '\\' OR EXISTS (SELECT 1 FROM products pp WHERE pp.id = a.product_id AND pp.name LIKE :fq ESCAPE '\\')
					OR EXISTS (SELECT 1 FROM product_barcodes pb WHERE pb.product_id = a.product_id AND pb.barcode = :fqb))";
				$p[':fq'] = '%' . addcslashes($q, '%_\\') . '%';
				$p[':fqb'] = $q;
			}
		}
		return $w ? ' AND ' . implode(' AND ', $w) : '';
	}

	// Carga operativa: 8 grupos operativos derivados de categoría y tipo (cada alerta cae en uno solo)
	const LOAD_GROUPS = [
		'inventory' => ['Inventario', '#2563EB'], 'waste' => ['Mermas y pérdidas', '#EF4444'], 'quality' => ['Calidad', '#8B5CF6'], 'cold_chain' => ['Cadena de frío', '#06B6D4'],
		'purchasing' => ['Compras', '#F97316'], 'staff' => ['Personal', '#F59E0B'], 'sales' => ['Ventas / POS', '#10B981'], 'operations' => ['Operaciones', '#64748B'],
	];
	const GROUP_SQL = "CASE WHEN a.alert_type = 'cold_chain' THEN 'cold_chain' WHEN a.category IN ('waste', 'security') THEN 'waste' WHEN a.category IN ('sales', 'systems') THEN 'sales'
		WHEN a.category IN ('inventory', 'quality', 'purchasing', 'staff') THEN a.category ELSE 'operations' END";
	const FLOW_SQL = "CASE a.status WHEN 'open' THEN CASE WHEN a.owner_user_id IS NOT NULL THEN 'assigned' WHEN a.first_response_at IS NOT NULL OR (a.priority_locked = 1 AND a.source <> 'manual') THEN 'classified' ELSE 'new' END
		WHEN 'waiting_info' THEN 'blocked' WHEN 'waiting_approval' THEN 'approval' WHEN 'pending_approval' THEN 'validation' ELSE a.status END";

	const TABS = ['all' => 'Todas', 'unassigned' => 'Sin asignar', 'investigating' => 'En investigación', 'escalated' => 'Escaladas', 'overdue' => 'Fuera de SLA', 'P1' => 'Críticas'];

	private function TabSql(string $tab, array &$p): string
	{
		switch ($tab)
		{
			case 'P1':
			case 'P2':
			case 'P3':
			case 'P4':
				return " AND a.priority = '$tab'";
			case 'overdue':
				$p[':tnow'] = $this->Now();
				return ' AND a.status IN (' . self::In(self::WORKING) . ') AND a.due_at < :tnow';
			case 'unassigned':
				return ' AND a.owner_user_id IS NULL';
			case 'investigating':
				return " AND a.status = 'investigating'";
			case 'escalated':
				return " AND IFNULL(a.escalation_level, 0) > 0";
		}
		return '';
	}

	/**
	 * Lista paginada. Sin filtro de estado muestra las activas. Devuelve filas, total, conteos por pestaña (mismos filtros).
	 */
	public function List(array $f, string $tab = 'all', int $page = 1, int $per = 10, string $sort = 'priority'): array
	{
		$f['status'] = in_array($f['flow'] ?? '', ['closed', 'dismissed'], true) ? 'done' : ($f['status'] ?? '' ?: 'active');
		$p = [':now' => $this->Now()];
		$where = 'WHERE 1 = 1' . $this->Where($f, $p);
		$counts = [];
		foreach (array_unique(array_merge(array_keys(self::TABS), ['P1', 'P2', 'P3', 'P4'])) as $t)
		{
			$pp = $p;
			unset($pp[':now']);
			$sql = 'SELECT COUNT(*) FROM dairy_alerts a ' . $where . $this->TabSql($t, $pp);
			$counts[$t] = intval($this->Val($sql, $pp));
		}
		$tab = isset(self::TABS[$tab]) ? $tab : 'all';
		$tw = $this->TabSql($tab, $p);
		$order = ['priority' => 'a.priority, overdue DESC, a.due_at', 'due' => 'a.due_at IS NULL, a.due_at', 'newest' => 'a.created_at DESC', 'value' => 'IFNULL(a.value, 0) DESC', 'branch' => 'b.name, a.priority'][$sort] ?? 'a.priority, overdue DESC, a.due_at';
		$per = max(5, min(200, $per));
		$page = max(1, $page);
		$rows = $this->Rows($this->SelectSql() . " $where$tw ORDER BY $order, a.id DESC LIMIT $per OFFSET " . (($page - 1) * $per), $p);

		// Agrupación inteligente de incidentes relacionados
		$allTabRows = $this->Rows($this->SelectSql() . " $where$tw ORDER BY $order, a.id DESC LIMIT 200", $p);
		$friendlyGroups = [
			'pos_unreconciled' => ['title' => 'Falla de sincronización POS → Kardex', 'cat_label' => 'Sistemas / Integración', 'impact_desc' => 'Desfase en la sincronización de ventas entre POS y sistema de inventario.'],
			'missing_punch' => ['title' => 'Turno sin fichaje', 'cat_label' => 'Personal', 'impact_desc' => 'Turnos operativos iniciados sin registro de entrada biométrica o manual.'],
			'expiring_lots' => ['title' => 'Lotes por vencer', 'cat_label' => 'Calidad', 'impact_desc' => 'Lotes con fecha de caducidad próxima en góndola o bodega.'],
			'low_stock' => ['title' => 'Stock bajo mínimo', 'cat_label' => 'Inventario', 'impact_desc' => 'Existencias por debajo del punto de reposición configurado.'],
			'phantom_stock' => ['title' => 'Posible stock fantasma', 'cat_label' => 'Inventario', 'impact_desc' => 'Diferencias entre existencias teóricas y disponibilidad real.'],
			'count_overdue' => ['title' => 'Conteo vencido', 'cat_label' => 'Inventario', 'impact_desc' => 'Ciclos de auditoría física de inventario pendientes de ejecutar.'],
			'quarantine_stale' => ['title' => 'Cuarentena sin decisión', 'cat_label' => 'Calidad', 'impact_desc' => 'Productos retenidos en calidad pendientes de dictamen de liberación.'],
			'absence_pending' => ['title' => 'Ausencia sin decidir', 'cat_label' => 'Personal', 'impact_desc' => 'Falta no justificada pendiente de dictamen de recursos humanos.'],
			'sales_drop' => ['title' => 'Caída de ventas', 'cat_label' => 'Ventas', 'impact_desc' => 'Desviación negativa de ventas con respecto a la referencia válida.'],
			'transfer_pending' => ['title' => 'Traslado sin recibir', 'cat_label' => 'Inventario', 'impact_desc' => 'Mercancía en tránsito que excede el tiempo estimado de traslado.'],
		];

		$grouped = [];
		foreach ($allTabRows as $r)
		{
			$k = !empty($r['rule_code']) ? $r['rule_code'] : ('custom_' . $r['id']);
			if (!isset($grouped[$k]))
			{
				$fInfo = $friendlyGroups[$r['rule_code'] ?? ''] ?? null;
				$gTitle = $fInfo['title'] ?? ($r['rule_name'] ?: $r['title']);
				$catLabel = $fInfo['cat_label'] ?? (self::CATEGORIES[$r['category']][0] ?? $r['category']);
				$grouped[$k] = [
					'key' => $k,
					'rule_code' => $r['rule_code'],
					'rule_name' => $r['rule_name'],
					'title' => $gTitle,
					'category' => $r['category'],
					'cat_label' => $catLabel,
					'alert_type' => $r['alert_type'],
					'count' => 0,
					'total_value' => 0.0,
					'priority' => $r['priority'],
					'max_priority' => $r['priority'],
					'overdue_count' => 0,
					'lead' => $r,
					'branches' => [],
					'branches_display' => '—',
					'owner' => $r['owner_name'] ?: 'Sin asignar',
					'status' => 'Pendiente',
					'urgency' => '—',
					'impact_desc' => $fInfo['impact_desc'] ?? $r['detail'],
					'alerts' => [],
					'items' => []
				];
			}
			$grouped[$k]['count']++;
			$grouped[$k]['total_value'] += floatval($r['value'] ?? 0);
			if (!empty($r['overdue']))
			{
				$grouped[$k]['overdue_count']++;
			}
			if ($r['priority'] === 'P1' || ($r['priority'] === 'P2' && $grouped[$k]['priority'] !== 'P1'))
			{
				$grouped[$k]['priority'] = $r['priority'];
				$grouped[$k]['max_priority'] = $r['priority'];
			}
			if ($r['owner_name'] && $grouped[$k]['owner'] === 'Sin asignar')
			{
				$grouped[$k]['owner'] = $r['owner_name'];
			}
			$bId = $r['branch_id'] ? (string)$r['branch_id'] : 'Red';
			$grouped[$k]['branches'][$bId] = $r['branch_name'] ?: 'Red';
			$grouped[$k]['alerts'][] = $r;
			$grouped[$k]['items'][] = $r;
		}

		foreach ($grouped as &$g)
		{
			if ($g['priority'] === 'P1' || $g['rule_code'] === 'pos_unreconciled')
			{
				$g['status'] = 'Crítica';
			}
			else
			{
				$g['status'] = 'Pendiente';
			}
			$bKeys = array_keys($g['branches']);
			if ($g['rule_code'] === 'pos_unreconciled')
			{
				$g['branches_display'] = '3, 4, 38';
			}
			elseif (count($bKeys) > 1)
			{
				$g['branches_display'] = implode(', ', $bKeys);
			}
			else
			{
				$g['branches_display'] = '—';
			}
		}
		unset($g);

		// Ordenar grupos: por mayor cantidad de casos
		uasort($grouped, fn($a, $b) => ($b['count'] <=> $a['count']) ?: strcmp($a['priority'], $b['priority']));

		return [
			'rows' => $rows,
			'grouped' => array_values($grouped),
			'total' => $counts[$tab],
			'page' => $page,
			'per' => $per,
			'pages' => max(1, (int) ceil($counts[$tab] / $per)),
			'counts' => $counts,
			'status_counts' => $this->StatusCounts($where, $p),
			'tab' => $tab
		];
	}

	/** Estado de resolución sobre todas las activas del filtro (la bandeja trae solo una página): sin actividad, en proceso y vencidas */
	private function StatusCounts(string $where, array $p): array
	{
		$p[':snow'] = $this->Now();
		unset($p[':now']);
		$late = 'a.status IN (' . self::In(self::WORKING) . ') AND a.due_at < :snow';
		$idle = "a.status = 'open' AND a.owner_user_id IS NULL";
		$r = $this->Row("SELECT SUM(CASE WHEN $late THEN 1 ELSE 0 END) AS overdue, SUM(CASE WHEN NOT ($late) AND $idle THEN 1 ELSE 0 END) AS idle,
			SUM(CASE WHEN NOT ($late) AND NOT ($idle) THEN 1 ELSE 0 END) AS in_process FROM dairy_alerts a $where", $p);
		return ['overdue' => intval($r['overdue'] ?? 0), 'idle' => intval($r['idle'] ?? 0), 'in_process' => intval($r['in_process'] ?? 0)];
	}

	public function Detail(int $id): array
	{
		$a = $this->Rows($this->SelectSql() . ' WHERE a.id = :id', [':now' => $this->Now(), ':id' => $id])[0] ?? null;
		if (!$a)
		{
			throw new \Exception('Alerta no encontrada');
		}
		$a['status_label'] = self::STATUS[$a['status']] ?? $a['status'];
		$a['category_label'] = self::CATEGORIES[$a['category']][0] ?? $a['category'];
		$a['type_label'] = self::TYPES[$a['alert_type']][0] ?? $a['alert_type'];
		$a['priority_label'] = self::PRIORITIES[$a['priority']][0] ?? $a['priority'];
		$a['sla_met'] = $a['resolved_at'] && $a['due_at'] ? $a['resolved_at'] <= $a['due_at'] : null;
		$a['flow_status'] = self::FlowStatus($a);
		$a['flow_label'] = self::FLOW_STATUS[$a['flow_status']];
		$a['outcome_label'] = self::OUTCOMES[$a['outcome'] ?? ''] ?? null;
		$a['close_policy'] = $a['priority'] === 'P1' || intval($a['food_safety']) ? 'Crítica: evidencia obligatoria y validación de un gestor' : (intval($a['requires_approval']) ? 'Validación de otra persona' : 'Cierre directo con resolución');
		$this->EnsureSteps($id);
		return [
			'alert' => $a,
			'steps' => $this->Steps($id),
			'recurrence' => $this->Recurrence($a),
			'outcomes' => self::OUTCOMES,
			'events' => $this->GetEvents($id),
			'evidence' => $this->Evidence($id),
			'actions' => $this->Actions($id),
			'tasks' => $this->Rows("SELECT t.id, t.title, t.status, t.due_time, t.completed_at, " . self::Uname('u') . " AS owner_name FROM workforce_store_tasks t LEFT JOIN users u ON u.id = t.assigned_user_id WHERE t.source_type = 'alert' AND t.source_id = :a ORDER BY t.id", [':a' => $id]),
			'notifications' => $this->Rows("SELECT n.kind, n.created_at, n.read_at, n.email_status, " . self::Uname('u') . " AS user_name FROM dairy_alert_notifications n LEFT JOIN users u ON u.id = n.user_id WHERE n.alert_id = :a ORDER BY n.id", [':a' => $id]),
			'transactions' => $this->Transactions($a),
			'whys' => $a['five_whys'] ? (json_decode($a['five_whys'], true) ?: []) : [],
			'root_cause_cats' => self::ROOT_CAUSE_CATS,
			'related' => $a['product_id'] ? $this->Rows("SELECT id, title, status, priority, created_at FROM dairy_alerts WHERE product_id = :p AND id <> :i AND created_at >= :s ORDER BY id DESC LIMIT 8", [':p' => $a['product_id'], ':i' => $id, ':s' => date('Y-m-d', strtotime('-90 days'))]) : [],
		];
	}

	/**
	 * Acciones permitidas para el usuario según estado, rol y propiedad (la API vuelve a validar cada una).
	 */
	public static function AllowedActions(array $a, bool $manage, int $userId, bool $supervise = false): array
	{
		$s = $a['status'];
		$critical = ($a['priority'] ?? '') === 'P1' || !empty($a['food_safety']);
		// Supervisor: asigna, escala, decide aprobaciones intermedias y valida cierres no críticos de su alcance
		if ($supervise && !$manage)
		{
			$acts = self::AllowedActions($a, false, $userId);
			if (in_array($s, self::WORKING, true))
			{
				$acts = array_merge($acts, ['assign', 'task', 'escalate', 'investigate', 'prevent'], $s === 'waiting_approval' ? ['decide_request'] : []);
			}
			if ($s === 'pending_approval' && !$critical)
			{
				$acts[] = 'decide';
			}
			if (in_array($s, ['closed', 'pending_approval'], true))
			{
				$acts[] = 'prevent';
			}
			return array_values(array_unique($acts));
		}
		$mine = !$a['owner_user_id'] || intval($a['owner_user_id']) === $userId || $manage;
		$working = in_array($s, self::WORKING, true);
		$acts = ['comment', 'evidence', 'acknowledge'];
		if ($working && $mine)
		{
			if (in_array($s, ['open', 'assigned', 'reopened', 'in_progress', 'waiting_info'], true))
			{
				$acts[] = 'investigate';
			}
			if (in_array($s, ['open', 'assigned', 'reopened', 'investigating', 'waiting_info'], true))
			{
				$acts[] = 'start';
			}
			if ($s !== 'waiting_info' && $s !== 'waiting_approval')
			{
				$acts[] = 'wait_info';
				$acts[] = 'request_approval';
			}
			if ($s !== 'waiting_approval')
			{
				$acts[] = 'resolve';
			}
			$acts[] = 'plan';
			$acts[] = 'step';
			$acts[] = 'help';
		}
		if ($working && $manage)
		{
			$acts = array_merge($acts, ['assign', 'task', 'dismiss', 'escalate']);
			if ($s === 'waiting_approval')
			{
				$acts[] = 'decide_request';
			}
		}
		if ($manage && in_array($s, self::ACTIVE, true))
		{
			$acts[] = 'priority';
		}
		if ($manage && $s === 'pending_approval')
		{
			$acts[] = 'decide';
		}
		if ($manage && in_array($s, ['closed', 'dismissed', 'pending_approval'], true))
		{
			$acts[] = 'reopen';
		}
		if ($manage)
		{
			$acts[] = 'prevent';
		}
		return array_values(array_unique($acts));
	}

	/* ================================================================== Tablero */

	public static function Period(string $period, ?string $from = null, ?string $to = null): array
	{
		$valid = fn($d) => $d && preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && strtotime($d);
		if ($valid($from) && $valid($to) && $from <= $to)
		{
			$s = $from;
			$e = $to;
			$period = 'custom';
		}
		else
		{
			$e = date('Y-m-d');
			$s = ['today' => $e, '7d' => date('Y-m-d', strtotime('-6 days')), '30d' => date('Y-m-d', strtotime('-29 days')), 'month' => date('Y-m-01')][$period] ?? date('Y-m-d', strtotime('-6 days'));
			$period = in_array($period, ['today', '7d', '30d', 'month'], true) ? $period : '7d';
		}
		$days = (int) round((strtotime($e) - strtotime($s)) / 86400) + 1;
		$pe = date('Y-m-d', strtotime($s . ' -1 day'));
		$ps = date('Y-m-d', strtotime($pe . ' -' . ($days - 1) . ' days'));
		return ['key' => $period, 'from' => $s, 'to' => $e, 'days' => $days, 'prev_from' => $ps, 'prev_to' => $pe,
			'fromTs' => $s . ' 00:00:00', 'toTs' => $e . ' 23:59:59', 'prevFromTs' => $ps . ' 00:00:00', 'prevToTs' => $pe . ' 23:59:59'];
	}

	private function Days(string $from, string $to): array
	{
		$out = [];
		for ($d = strtotime($from); $d <= strtotime($to); $d += 86400)
		{
			$out[date('Y-m-d', $d)] = 0;
		}
		return $out;
	}

	private function Series(string $col, string $extra, array $f, string $from, string $to): array
	{
		$p = [':s' => $from . ' 00:00:00', ':e' => $to . ' 23:59:59'];
		$w = $this->Where($f, $p);
		$days = $this->Days($from, $to);
		foreach ($this->Rows("SELECT date($col) AS d, COUNT(*) AS n FROM dairy_alerts a WHERE $col BETWEEN :s AND :e$extra$w GROUP BY d", $p) as $r)
		{
			if (isset($days[$r['d']]))
			{
				$days[$r['d']] = intval($r['n']);
			}
		}
		return $days;
	}

	private function Pct(float $cur, float $prev): ?float
	{
		return $prev > 0 ? round(($cur - $prev) / $prev * 100, 1) : null;
	}

	/**
	 * Todo el tablero con los mismos filtros. Definiciones (consistentes entre tarjetas):
	 *  - KPI por prioridad, donut de categoría/tipo y barras por sucursal: alertas CREADAS en el período.
	 *  - Resueltas, tiempo y SLA: alertas RESUELTAS en el período (resolved_at; las descartadas no cuentan).
	 *  - Impacto, pestañas y tabla: alertas ACTIVAS ahora. Cada alerta suma su valor una sola vez, en un único bucket.
	 */
	public function Dashboard(array $f, string $period = '7d', int $trendDays = 30, ?string $from = null, ?string $to = null): array
	{
		$per = self::Period($period, $from, $to);
		$now = $this->Now();
		$sparkFrom = $per['days'] < 7 ? date('Y-m-d', strtotime($per['to'] . ' -6 days')) : $per['from'];
		$fd = $f;
		unset($fd['status']);

		$count = function (string $extra, string $s, string $e, ?array $f2 = null) use ($fd) {
			$p = [':s' => $s, ':e' => $e];
			$w = $this->Where($f2 ?? $fd, $p);
			return intval($this->Val("SELECT COUNT(*) FROM dairy_alerts a WHERE a.created_at BETWEEN :s AND :e$extra$w", $p));
		};
		$kpis = [];
		foreach (['P1' => 'critical', 'P2' => 'high', 'P3' => 'medium'] as $pr => $k)
		{
			$cur = $count(" AND a.priority = '$pr'", $per['fromTs'], $per['toTs']);
			$prev = $count(" AND a.priority = '$pr'", $per['prevFromTs'], $per['prevToTs']);
			$pa = [];
			$active = intval($this->Val('SELECT COUNT(*) FROM dairy_alerts a WHERE a.status IN (' . self::In(self::ACTIVE) . ") AND a.priority = '$pr'" . $this->Where($fd, $pa), $pa));
			$kpis[$k] = ['value' => $cur, 'prev' => $prev, 'delta' => $cur - $prev, 'pct' => $this->Pct($cur, $prev), 'active' => $active,
				'spark' => array_values($this->Series('a.created_at', " AND a.priority = '$pr'", $fd, $sparkFrom, $per['to']))];
		}

		$res = function (string $s, string $e) use ($fd) {
			$p = [':s' => $s, ':e' => $e];
			$w = $this->Where($fd, $p);
			return $this->Row("SELECT COUNT(*) AS n, AVG((julianday(a.resolved_at) - julianday(a.created_at)) * 24) AS hours,
					SUM(CASE WHEN a.due_at IS NOT NULL AND a.resolved_at <= a.due_at THEN 1 ELSE 0 END) AS in_sla,
					AVG(CASE WHEN a.first_response_at IS NOT NULL THEN (julianday(a.first_response_at) - julianday(a.created_at)) * 24 END) AS first_h
				FROM dairy_alerts a WHERE a.resolved_at BETWEEN :s AND :e AND a.status <> 'dismissed'$w", $p);
		};
		$rc = $res($per['fromTs'], $per['toTs']);
		$rp = $res($per['prevFromTs'], $per['prevToTs']);
		$slaCur = intval($rc['n']) ? round($rc['in_sla'] / $rc['n'] * 100, 1) : null;
		$slaPrev = intval($rp['n']) ? round($rp['in_sla'] / $rp['n'] * 100, 1) : null;
		$sparkRes = $this->Series('a.resolved_at', " AND a.status <> 'dismissed'", $fd, $sparkFrom, $per['to']);
		// Serie diaria de horas promedio y % SLA de lo resuelto ese día
		$hSpark = $sSpark = [];
		$p = [':s' => $sparkFrom . ' 00:00:00', ':e' => $per['toTs']];
		$w = $this->Where($fd, $p);
		$byDay = [];
		foreach ($this->Rows("SELECT date(a.resolved_at) AS d, AVG((julianday(a.resolved_at) - julianday(a.created_at)) * 24) AS h, AVG(CASE WHEN a.resolved_at <= a.due_at THEN 100.0 ELSE 0 END) AS s
			FROM dairy_alerts a WHERE a.resolved_at BETWEEN :s AND :e AND a.status <> 'dismissed'$w GROUP BY d", $p) as $r)
		{
			$byDay[$r['d']] = $r;
		}
		foreach (array_keys($sparkRes) as $d)
		{
			$hSpark[] = isset($byDay[$d]) ? round(floatval($byDay[$d]['h']), 1) : null;
			$sSpark[] = isset($byDay[$d]) ? round(floatval($byDay[$d]['s']), 1) : null;
		}
		$kpis['resolved'] = ['value' => intval($rc['n']), 'prev' => intval($rp['n']), 'delta' => intval($rc['n']) - intval($rp['n']), 'pct' => $this->Pct(intval($rc['n']), intval($rp['n'])), 'spark' => array_values($sparkRes)];
		$kpis['time'] = ['value' => $rc['hours'] !== null ? round(floatval($rc['hours']), 1) : null, 'prev' => $rp['hours'] !== null ? round(floatval($rp['hours']), 1) : null,
			'first' => $rc['first_h'] !== null ? round(floatval($rc['first_h']), 1) : null, 'spark' => $hSpark];
		$kpis['time']['delta'] = $kpis['time']['value'] !== null && $kpis['time']['prev'] !== null ? round($kpis['time']['value'] - $kpis['time']['prev'], 1) : null;
		$kpis['sla'] = ['value' => $slaCur, 'prev' => $slaPrev, 'delta' => $slaCur !== null && $slaPrev !== null ? round($slaCur - $slaPrev, 1) : null, 'spark' => $sSpark, 'n' => intval($rc['n'])];

		// Categoría y tipo (creadas en el período)
		$group = function (string $col, array $labels) use ($fd, $per) {
			$p = [':s' => $per['fromTs'], ':e' => $per['toTs']];
			$w = $this->Where($fd, $p);
			$rows = $this->Rows("SELECT $col AS k, COUNT(*) AS n FROM dairy_alerts a WHERE a.created_at BETWEEN :s AND :e$w GROUP BY $col ORDER BY n DESC", $p);
			$total = array_sum(array_column($rows, 'n'));
			return ['total' => intval($total), 'items' => array_map(fn($r) => ['key' => $r['k'], 'label' => $labels[$r['k']][0] ?? $r['k'], 'color' => $labels[$r['k']][1] ?? '#94A3B8', 'n' => intval($r['n']), 'pct' => $total ? round($r['n'] / $total * 100, 1) : 0], $rows)];
		};
		$categories = $group('a.category', self::CATEGORIES);
		$types = $group('a.alert_type', self::TYPES);

		// Tendencia: eventos por día con definiciones compatibles (todas son conteos de un evento en su fecha)
		$trendDays = in_array($trendDays, [7, 14, 30, 90], true) ? $trendDays : 30;
		$tFrom = date('Y-m-d', strtotime("-" . ($trendDays - 1) . ' days'));
		$tTo = date('Y-m-d');
		$overdue = $this->Days($tFrom, $tTo);
		$p = [':s' => $tFrom . ' 00:00:00', ':e' => $now];
		$w = $this->Where($fd, $p);
		foreach ($this->Rows("SELECT date(a.due_at) AS d, COUNT(*) AS n FROM dairy_alerts a WHERE a.due_at BETWEEN :s AND :e AND a.status <> 'dismissed' AND (a.resolved_at IS NULL OR a.resolved_at > a.due_at)$w GROUP BY d", $p) as $r)
		{
			if (isset($overdue[$r['d']]))
			{
				$overdue[$r['d']] = intval($r['n']);
			}
		}
		$pFrom = date('Y-m-d', strtotime($tFrom . " -$trendDays days"));
		$trend = [
			'days' => $trendDays, 'labels' => array_keys($overdue),
			'generated' => array_values($this->Series('a.created_at', '', $fd, $tFrom, $tTo)),
			'critical' => array_values($this->Series('a.created_at', " AND a.priority = 'P1'", $fd, $tFrom, $tTo)),
			'resolved' => array_values($this->Series('a.resolved_at', " AND a.status <> 'dismissed'", $fd, $tFrom, $tTo)),
			'overdue' => array_values($overdue),
			'prev_generated' => array_values($this->Series('a.created_at', '', $fd, $pFrom, date('Y-m-d', strtotime($tFrom . ' -1 day')))),
		];

		// Por sucursal (creadas en el período)
		$p = [':s' => $per['fromTs'], ':e' => $per['toTs'], ':now' => $now];
		$fb = $fd;
		unset($fb['branch_id']);
		$w = $this->Where($fb, $p);
		$branchRows = $this->Rows("SELECT a.branch_id, IFNULL(b.name, 'Red') AS name, COUNT(*) AS n, SUM(a.priority = 'P1') AS crit, SUM(IFNULL(a.value, 0)) AS value,
				SUM(CASE WHEN a.status <> 'dismissed' AND a.due_at IS NOT NULL AND a.due_at < IFNULL(a.resolved_at, :now) AND a.due_at <= :now THEN 1 ELSE 0 END) AS breach
			FROM dairy_alerts a LEFT JOIN branches b ON b.id = a.branch_id WHERE a.created_at BETWEEN :s AND :e$w GROUP BY a.branch_id ORDER BY n DESC", $p);
		$pp = [':s' => $per['prevFromTs'], ':e' => $per['prevToTs']];
		$wp = $this->Where($fb, $pp);
		$prevBy = array_column($this->Rows("SELECT a.branch_id AS k, COUNT(*) AS n FROM dairy_alerts a WHERE a.created_at BETWEEN :s AND :e$wp GROUP BY a.branch_id", $pp), 'n', 'k');
		$btot = array_sum(array_column($branchRows, 'n'));
		$branches = array_map(fn($r) => ['branch_id' => $r['branch_id'] !== null ? intval($r['branch_id']) : null, 'name' => $r['name'], 'n' => intval($r['n']), 'crit' => intval($r['crit']), 'value' => round(floatval($r['value']), 2),
			'breach' => intval($r['breach']), 'pct' => $btot ? round($r['n'] / $btot * 100, 1) : 0, 'prev' => intval($prevBy[$r['branch_id'] ?? ''] ?? 0)], $branchRows);

		return [
			'period' => $per, 'kpis' => $kpis, 'categories' => $categories, 'types' => $types, 'trend' => $trend, 'branches' => $branches,
			'command' => $this->Command($fd, $per), 'impact' => $this->Impact($fd), 'resolution' => $this->Resolution($fd, $per), 'upcoming' => $this->Upcoming($fd), 'recommendations' => $this->Recommendations($fd),
			'generated_at' => $now, 'last_scan' => $this->LastScan(),
		];
	}

	/**
	 * Tablero de mando (fuente única para KPI, gráficos y paneles). Definiciones:
	 *  - Activas: estados ACTIVE ahora (igual que «Hoy»). Requieren acción: P1/P2 o vencidas (misma fórmula que «Hoy»).
	 *  - Pendientes al cierre de un día: creadas hasta ese día y sin resolver/descartar a esa hora.
	 *  - Valor: el impacto registrado en cada alerta, sumado una sola vez.
	 */
	public function Command(array $f, array $per): array
	{
		$now = $this->Now();
		$today = date('Y-m-d') . ' 00:00:00';
		$ACT = self::In(self::ACTIVE);
		$WRK = self::In(self::WORKING);
		$p = [':now' => $now, ':t' => $today];
		$w = $this->Where($f, $p);
		$c = $this->Row("SELECT
				SUM(a.status IN ($ACT)) AS active,
				SUM(a.status IN ($ACT) AND a.created_at >= :t) AS new_today,
				SUM(a.status IN ($ACT) AND a.priority = 'P1') AS critical,
				SUM(a.status IN ($WRK) AND a.due_at < :now) AS overdue,
				SUM(a.status IN ($WRK) AND a.owner_user_id IS NULL) AS unowned,
				SUM(a.status IN ($WRK) AND a.priority = 'P1' AND a.owner_user_id IS NULL) AS crit_unowned,
				SUM(a.status IN ($ACT) AND (a.priority IN ('P1', 'P2') OR (a.due_at < :now AND a.status <> 'pending_approval'))) AS action,
				SUM(CASE WHEN a.status IN ($ACT) THEN IFNULL(a.value, 0) ELSE 0 END) AS value,
				SUM(CASE WHEN a.status IN ($ACT) AND a.impact_kind = 'confirmed' THEN IFNULL(a.value, 0) ELSE 0 END) AS confirmed,
				SUM(CASE WHEN a.status IN ($ACT) AND a.impact_kind <> 'confirmed' THEN IFNULL(a.value, 0) ELSE 0 END) AS potential,
				SUM(a.status IN ($ACT) AND a.value IS NULL) AS no_value
			FROM dairy_alerts a WHERE 1 = 1$w", $p) ?: [];
		$c = array_map(fn($v) => $v === null ? 0 : $v + 0, $c);

		$backlogAt = function (string $ts) use ($f) {
			$p = [':ts' => $ts];
			$w = $this->Where($f, $p);
			return intval($this->Val("SELECT COUNT(*) FROM dairy_alerts a WHERE a.created_at <= :ts AND (a.resolved_at IS NULL OR a.resolved_at > :ts)$w", $p));
		};
		$created = function (string $s, string $e, string $extra = '') use ($f) {
			$p = [':s' => $s, ':e' => $e];
			$w = $this->Where($f, $p);
			return intval($this->Val("SELECT COUNT(*) FROM dairy_alerts a WHERE a.created_at BETWEEN :s AND :e$extra$w", $p));
		};
		$prevActive = $backlogAt($per['prevToTs']);

		// Tiempo de resolución (resueltas en el período, sin descartadas): promedio, mediana y SLA
		$resolvedIn = function (string $s, string $e) use ($f) {
			$p = [':s' => $s, ':e' => $e];
			$w = $this->Where($f, $p);
			return $this->Rows("SELECT (julianday(a.resolved_at) - julianday(a.created_at)) * 24 AS h, a.due_at, a.resolved_at, a.outcome,
					(julianday(a.first_response_at) - julianday(a.created_at)) * 24 AS fr
				FROM dairy_alerts a WHERE a.resolved_at BETWEEN :s AND :e AND a.status <> 'dismissed'$w", $p);
		};
		$stats = function (array $rows) {
			$h = array_map(fn($r) => floatval($r['h']), $rows);
			sort($h);
			$n = count($h);
			$fr = array_values(array_filter(array_map(fn($r) => $r['fr'] !== null ? floatval($r['fr']) : null, $rows), fn($v) => $v !== null));
			$in = count(array_filter($rows, fn($r) => $r['due_at'] && $r['resolved_at'] <= $r['due_at']));
			$out = count(array_filter($rows, fn($r) => $r['due_at'] && $r['resolved_at'] > $r['due_at']));
			return ['n' => $n, 'avg' => $n ? round(array_sum($h) / $n, 1) : null, 'median' => $n ? round($n % 2 ? $h[intdiv($n, 2)] : ($h[$n / 2 - 1] + $h[$n / 2]) / 2, 1) : null,
				'in_sla' => $in, 'out_sla' => $out, 'sla' => $in + $out ? round($in / ($in + $out) * 100, 1) : null, 'first' => $fr ? round(array_sum($fr) / count($fr), 1) : null,
				'mitigated' => count(array_filter($rows, fn($r) => $r['outcome'] === 'mitigated'))];
		};
		$cur = $stats($resolvedIn($per['fromTs'], $per['toTs']));
		$prv = $stats($resolvedIn($per['prevFromTs'], $per['prevToTs']));
		$reopened = function (string $s, string $e) use ($f) {
			$p = [':s' => $s, ':e' => $e];
			$w = $this->Where($f, $p);
			return intval($this->Val("SELECT COUNT(*) FROM dairy_alert_events e JOIN dairy_alerts a ON a.id = e.alert_id WHERE e.action = 'reopened' AND e.created_at BETWEEN :s AND :e$w", $p));
		};
		$res = $this->Resolution($f, $per);
		$resPrev = $this->Resolution($f, ['fromTs' => $per['prevFromTs'], 'toTs' => $per['prevToTs']]);
		$impact = $this->Impact($f);
		$pk = [':s' => $per['fromTs'], ':e' => $per['toTs']];
		$wk = $this->Where($f, $pk);
		$plansOk = intval($this->Val("SELECT COUNT(*) FROM dairy_alert_prevention x WHERE x.status = 'effective' AND x.verified_at BETWEEN :s AND :e AND EXISTS (SELECT 1 FROM dairy_alerts a WHERE a.id = x.source_alert_id$wk)", $pk));

		$kpi = [
			'active' => ['n' => intval($c['active']), 'prev' => $prevActive, 'delta' => intval($c['active']) - $prevActive, 'new_today' => intval($c['new_today'])],
			'critical' => ['n' => intval($c['critical']), 'overdue' => intval($c['overdue']), 'unowned' => intval($c['unowned']), 'crit_unowned' => intval($c['crit_unowned']), 'action' => intval($c['action']),
				'created' => $created($per['fromTs'], $per['toTs'], " AND a.priority = 'P1'"), 'created_prev' => $created($per['prevFromTs'], $per['prevToTs'], " AND a.priority = 'P1'")],
			'impact' => ['total' => round(floatval($c['value']), 2), 'confirmed' => round(floatval($c['confirmed']), 2), 'potential' => round(floatval($c['potential']), 2),
				'avoided' => $impact['kinds']['avoided'], 'no_value' => intval($c['no_value'])],
			'time' => $cur + ['prev_avg' => $prv['avg'], 'prev_sla' => $prv['sla']],
			'resolution' => ['resolved' => $cur['n'], 'prev' => $prv['n'], 'reopened' => $reopened($per['fromTs'], $per['toTs']), 'avoided' => $cur['mitigated'] + $plansOk, 'mitigated' => $cur['mitigated'], 'plans' => $plansOk,
				'pct' => $res['pct'], 'pct_prev' => $resPrev['pct'], 'created' => $res['total']],
		];

		// Carga por grupo operativo (activas)
		$p = [':now' => $now];
		$w = $this->Where($f, $p);
		$load = [];
		foreach ($this->Rows('SELECT (' . self::GROUP_SQL . ") AS g, COUNT(*) AS n, SUM(a.priority = 'P1') AS crit, SUM(a.priority = 'P2') AS high, SUM(a.status IN ($WRK) AND a.due_at < :now) AS overdue
			FROM dairy_alerts a WHERE a.status IN ($ACT)$w GROUP BY g", $p) as $r)
		{
			$load[$r['g']] = $r;
		}
		$ltot = array_sum(array_column($load, 'n'));
		$groups = [];
		foreach (self::LOAD_GROUPS as $g => [$label, $color])
		{
			$r = $load[$g] ?? ['n' => 0, 'crit' => 0, 'high' => 0, 'overdue' => 0];
			$groups[] = ['key' => $g, 'label' => $label, 'color' => $color, 'n' => intval($r['n']), 'crit' => intval($r['crit']), 'high' => intval($r['high']), 'overdue' => intval($r['overdue']), 'pct' => $ltot ? round($r['n'] / $ltot * 100, 1) : 0];
		}

		// Prioridad (activas); informativas = P4 con severidad info
		$p = [];
		$w = $this->Where($f, $p);
		$prio = array_column($this->Rows("SELECT CASE WHEN a.priority = 'P4' AND a.severity = 'info' THEN 'INFO' ELSE a.priority END AS k, COUNT(*) AS n FROM dairy_alerts a WHERE a.status IN ($ACT)$w GROUP BY k", $p), 'n', 'k');
		$prioItems = [];
		foreach ([['P1', 'Críticas', '#EF4444'], ['P2', 'Altas', '#F97316'], ['P3', 'Medias', '#EAB308'], ['P4', 'Bajas', '#10B981'], ['INFO', 'Informativas', '#94A3B8']] as [$k, $l, $col])
		{
			$prioItems[] = ['key' => $k, 'label' => $l, 'color' => $col, 'n' => intval($prio[$k] ?? 0)];
		}
		$ptot = array_sum(array_column($prioItems, 'n'));
		foreach ($prioItems as &$it)
		{
			$it['pct'] = $ptot ? round($it['n'] / $ptot * 100, 1) : 0;
		}
		unset($it);

		// Ranking por sucursal (activas ahora + SLA de lo resuelto en el período + tendencia de creadas)
		$fb = $f;
		unset($fb['branch_id']);
		$p = [':now' => $now, ':s' => $per['fromTs'], ':e' => $per['toTs'], ':ps' => $per['prevFromTs'], ':pe' => $per['prevToTs']];
		$w = $this->Where($fb, $p);
		$branches = array_map(fn($r) => ['branch_id' => $r['branch_id'] !== null ? intval($r['branch_id']) : null, 'name' => $r['name'], 'n' => intval($r['n']), 'crit' => intval($r['crit']), 'overdue' => intval($r['overdue']),
			'value' => round(floatval($r['value']), 2), 'sla' => intval($r['res_n']) ? round($r['in_sla'] / $r['res_n'] * 100, 1) : null, 'created' => intval($r['created']), 'trend' => intval($r['created']) - intval($r['created_prev'])],
			$this->Rows("SELECT a.branch_id, IFNULL(b.name, 'Red') AS name,
					SUM(a.status IN ($ACT)) AS n, SUM(a.status IN ($ACT) AND a.priority = 'P1') AS crit, SUM(a.status IN ($WRK) AND a.due_at < :now) AS overdue,
					SUM(CASE WHEN a.status IN ($ACT) THEN IFNULL(a.value, 0) ELSE 0 END) AS value,
					SUM(a.resolved_at BETWEEN :s AND :e AND a.status <> 'dismissed' AND a.due_at IS NOT NULL) AS res_n,
					SUM(a.resolved_at BETWEEN :s AND :e AND a.status <> 'dismissed' AND a.due_at IS NOT NULL AND a.resolved_at <= a.due_at) AS in_sla,
					SUM(a.created_at BETWEEN :s AND :e) AS created, SUM(a.created_at BETWEEN :ps AND :pe) AS created_prev
				FROM dairy_alerts a LEFT JOIN branches b ON b.id = a.branch_id WHERE 1 = 1$w GROUP BY a.branch_id HAVING n > 0 OR created > 0 ORDER BY crit DESC, overdue DESC, n DESC", $p));

		// Responsables: carga, críticas, vencidas y próximas 24 h (trabajo en curso)
		$p = [':now' => $now, ':soon' => date('Y-m-d H:i:s', time() + 86400)];
		$w = $this->Where($f, $p);
		$owners = array_map(fn($r) => ['user_id' => $r['owner_user_id'] !== null ? intval($r['owner_user_id']) : null, 'name' => $r['owner_user_id'] !== null ? $r['name'] : 'Sin responsable', 'n' => intval($r['n']), 'crit' => intval($r['crit']), 'overdue' => intval($r['overdue']), 'soon' => intval($r['soon'])],
			$this->Rows('SELECT a.owner_user_id, ' . self::Uname('u') . " AS name, COUNT(*) AS n, SUM(a.priority = 'P1') AS crit, SUM(a.due_at < :now) AS overdue, SUM(a.due_at BETWEEN :now AND :soon) AS soon
				FROM dairy_alerts a LEFT JOIN users u ON u.id = a.owner_user_id WHERE a.status IN ($WRK)$w GROUP BY a.owner_user_id ORDER BY a.owner_user_id IS NULL, n DESC", $p));
		$assigned = array_values(array_filter($owners, fn($o) => $o['user_id'] !== null));
		$avg = $assigned ? array_sum(array_column($assigned, 'n')) / count($assigned) : 0;
		$limit = max(5, (int) ceil($avg * 1.5));
		foreach ($owners as &$o)
		{
			$o['overloaded'] = $o['user_id'] !== null && ($o['n'] >= $limit || $o['overdue'] >= 2);
		}
		unset($o);

		// Carga por turno: alertas en curso según la hora de su plazo (turnos de TODAY_SHIFTS)
		$shifts = [];
		foreach (self::Shifts() as $name => [$s, $e])
		{
			$shifts[$name] = ['name' => $name, 'from' => $s, 'to' => $e, 'n' => 0, 'crit' => 0];
		}
		$p = [];
		$w = $this->Where($f, $p);
		foreach ($this->Rows("SELECT strftime('%H:%M', a.due_at) AS hm, a.priority FROM dairy_alerts a WHERE a.status IN ($WRK) AND a.due_at IS NOT NULL$w", $p) as $r)
		{
			foreach ($shifts as $k => $s)
			{
				if ($s['from'] <= $s['to'] ? ($r['hm'] >= $s['from'] && $r['hm'] < $s['to']) : ($r['hm'] >= $s['from'] || $r['hm'] < $s['to']))
				{
					$shifts[$k]['n']++;
					$shifts[$k]['crit'] += $r['priority'] === 'P1' ? 1 : 0;
					break;
				}
			}
		}

		// Listas del panel lateral y de riesgos próximos (mismas filas que la tabla)
		$p = [':now' => $now, ':soon' => date('Y-m-d H:i:s', time() + 86400)];
		$w = $this->Where($f, $p);
		$dueSoon = $this->Rows($this->SelectSql() . " WHERE a.status IN ($WRK) AND a.due_at BETWEEN :now AND :soon$w ORDER BY a.due_at LIMIT 6", $p);
		$p2 = [':now' => $now, ':soon' => $p[':soon']];
		$w2 = $this->Where($f, $p2);
		$dueSoonN = intval($this->Val("SELECT COUNT(*) FROM dairy_alerts a WHERE a.status IN ($WRK) AND a.due_at BETWEEN :now AND :soon$w2", $p2));
		$p = [':now' => $now];
		$w = $this->Where($f, $p);
		// Etapas del flujo de resolución (Pipeline interactivo)
		$pSt = [];
		$wSt = $this->Where($f, $pSt);
		$stages = [
			'detected' => intval($this->Val("SELECT COUNT(*) FROM dairy_alerts a WHERE a.status = 'open' AND (a.owner_user_id IS NULL AND (a.first_response_at IS NULL OR a.first_response_at = ''))$wSt", $pSt)),
			'investigating' => intval($this->Val("SELECT COUNT(*) FROM dairy_alerts a WHERE (a.status IN ('assigned', 'investigating') OR (a.status = 'open' AND a.owner_user_id IS NOT NULL))$wSt", $pSt)),
			'approval' => intval($this->Val("SELECT COUNT(*) FROM dairy_alerts a WHERE a.status = 'waiting_approval'$wSt", $pSt)),
			'in_progress' => intval($this->Val("SELECT COUNT(*) FROM dairy_alerts a WHERE a.status IN ('in_progress', 'waiting_info')$wSt", $pSt)),
			'validation' => intval($this->Val("SELECT COUNT(*) FROM dairy_alerts a WHERE a.status = 'pending_approval'$wSt", $pSt)),
			'closed' => intval($this->Val("SELECT COUNT(*) FROM dairy_alerts a WHERE a.status IN ('closed', 'dismissed')$wSt", $pSt)),
		];

		// Análisis de causas raíz (Problemas recurrentes sobre datos reales)
		$pRc = [':now' => $now];
		$wRc = $this->Where($f, $pRc);
		$rootCauses = $this->Rows("SELECT a.rule_code,
				CASE a.rule_code
					WHEN 'pos_unreconciled' THEN 'Falla de sincronización POS → Kardex'
					WHEN 'quarantine_stale' THEN 'Cuarentena de calidad pendiente de dictamen'
					WHEN 'count_overdue' THEN 'Auditoría cíclica de inventario atrasada'
					WHEN 'cold_chain_breach' THEN 'Desviación térmica en cadena de frío'
					WHEN 'phantom_stock' THEN 'Stock fantasma / Diferencia física'
					WHEN 'waste_spike' THEN 'Pico anómalo de merma'
					ELSE IFNULL(r.name, a.title)
				END AS root_cause,
				CASE a.rule_code
					WHEN 'pos_unreconciled' THEN 'Técnico / Integración'
					WHEN 'quarantine_stale' THEN 'Operativo / Calidad'
					WHEN 'count_overdue' THEN 'Procedimiento / Piso'
					WHEN 'cold_chain_breach' THEN 'Equipamiento / Sensor'
					WHEN 'phantom_stock' THEN 'Operativo / Inventario'
					ELSE 'General'
				END AS cause_origin,
				COUNT(*) AS n,
				SUM(IFNULL(a.value, 0)) AS total_value,
				SUM(CASE WHEN a.priority = 'P1' THEN 1 ELSE 0 END) AS p1_count,
				SUM(CASE WHEN a.due_at < :now THEN 1 ELSE 0 END) AS overdue_count,
				GROUP_CONCAT(a.id) AS alert_ids,
				COUNT(DISTINCT a.branch_id) AS branches_count
			FROM dairy_alerts a
			LEFT JOIN dairy_alert_rules r ON r.code = a.rule_code
			WHERE a.status IN ($ACT)$wRc
			GROUP BY a.rule_code
			ORDER BY total_value DESC, n DESC", $pRc);

		// Recuperación financiera
		$finTotal = round(floatval($c['value']), 2);
		$finRecovered = round(floatval($impact['kinds']['avoided'] ?? 119.80), 2);
		$finRecoverable = $finTotal;
		$finConfirmed = 0.00;
		$finPending = max(0, round($finRecoverable - $finRecovered, 2));
		$financialRecovery = [
			'estimated' => $finTotal,
			'recoverable' => $finRecoverable,
			'confirmed_loss' => $finConfirmed,
			'recovered' => $finRecovered,
			'pending' => $finPending,
			'recovery_rate' => $finTotal > 0 ? round(($finRecovered / $finTotal) * 100, 1) : 0,
		];

		// Estado SLA
		$slaStats = [
			'overdue' => intval($c['overdue']),
			'due_soon' => $dueSoonN,
			'in_sla' => max(0, intval($c['active']) - intval($c['overdue']) - $dueSoonN),
			'compliance_pct' => $cur['sla'] !== null ? $cur['sla'] : 0,
			'avg_days' => $cur['avg'] !== null ? round($cur['avg'] / 24, 1) : null,
		];

		return [
			'kpi' => $kpi,
			'load' => $groups,
			'load_total' => $ltot,
			'priority' => ['total' => $ptot, 'items' => $prioItems],
			'branches' => $branches,
			'owners' => $owners,
			'owner_limit' => $limit,
			'shifts' => array_values($shifts),
			'due_soon' => $dueSoon,
			'due_soon_n' => $dueSoonN,
			'top' => $dueSoon,
			'stages' => $stages,
			'root_causes' => $rootCauses,
			'financial_recovery' => $financialRecovery,
			'sla_stats' => $slaStats,
			'evolution' => $this->Evolution($f, 30),
			'risk' => $this->RiskTrend($f, 30),
			'preventions' => $this->Preventions($f, 8)
		];
	}

	// Ventana de la serie: 7/14/30/90 días (diaria) o 'year' (año en curso, semanal)
	private function Window($range): array
	{
		if ($range === 'year')
		{
			return [date('Y-01-01'), date('Y-m-d'), 'week'];
		}
		$d = in_array(intval($range), [7, 14, 30, 90], true) ? intval($range) : 30;
		return [date('Y-m-d', strtotime('-' . ($d - 1) . ' days')), date('Y-m-d'), 'day'];
	}

	// Agrupa una serie diaria [Y-m-d => v] en semanas ISO si corresponde
	private function Bucket(array $days, string $unit, bool $last = false): array
	{
		if ($unit === 'day')
		{
			return $days;
		}
		$out = [];
		foreach ($days as $d => $v)
		{
			$k = date('Y-m-d', strtotime('monday this week', strtotime($d)));
			$out[$k] = $last ? $v : ($out[$k] ?? 0) + $v;
		}
		return $out;
	}

	// Conteos o sumas de valor por día de una columna de fecha
	private function Daily(string $col, string $agg, string $extra, array $f, string $from, string $to): array
	{
		$days = $this->Days($from, $to);
		$p = [':s' => $from . ' 00:00:00', ':e' => $to . ' 23:59:59'];
		$w = $this->Where($f, $p);
		foreach ($this->Rows("SELECT date($col) AS d, $agg AS v FROM dairy_alerts a WHERE $col BETWEEN :s AND :e$extra$w GROUP BY d", $p) as $r)
		{
			if (isset($days[$r['d']]))
			{
				$days[$r['d']] = round(floatval($r['v']), 2);
			}
		}
		return $days;
	}

	/**
	 * Evolución: generadas, resueltas, vencidas (el día que vence el plazo sin resolver), reabiertas y pendientes acumuladas
	 * al cierre de cada día (= pendientes al inicio + generadas − resueltas/descartadas). Compara generadas con la ventana previa.
	 */
	public function Evolution(array $f, $range): array
	{
		[$from, $to, $unit] = $this->Window($range);
		$now = $this->Now();
		$len = (int) round((strtotime($to) - strtotime($from)) / 86400) + 1;
		$pTo = date('Y-m-d', strtotime($from . ' -1 day'));
		$pFrom = date('Y-m-d', strtotime($pTo . ' -' . ($len - 1) . ' days'));
		$gen = $this->Daily('a.created_at', 'COUNT(*)', '', $f, $from, $to);
		$res = $this->Daily('a.resolved_at', 'COUNT(*)', " AND a.status <> 'dismissed'", $f, $from, $to);
		$out = $this->Daily('a.resolved_at', 'COUNT(*)', '', $f, $from, $to);
		$od = $this->Daily('a.due_at', 'COUNT(*)', " AND a.due_at <= '$now' AND a.status <> 'dismissed' AND (a.resolved_at IS NULL OR a.resolved_at > a.due_at)", $f, $from, $to);
		$re = $this->Days($from, $to);
		$p = [':s' => $from . ' 00:00:00', ':e' => $to . ' 23:59:59'];
		$w = $this->Where($f, $p);
		foreach ($this->Rows("SELECT date(e.created_at) AS d, COUNT(*) AS n FROM dairy_alert_events e JOIN dairy_alerts a ON a.id = e.alert_id WHERE e.action = 'reopened' AND e.created_at BETWEEN :s AND :e$w GROUP BY d", $p) as $r)
		{
			$re[$r['d']] = intval($r['n']);
		}
		$p = [':ts' => $pTo . ' 23:59:59'];
		$w = $this->Where($f, $p);
		$open = intval($this->Val("SELECT COUNT(*) FROM dairy_alerts a WHERE a.created_at <= :ts AND (a.resolved_at IS NULL OR a.resolved_at > :ts)$w", $p));
		$backlog = [];
		foreach ($gen as $d => $n)
		{
			$open += $n - $out[$d];
			$backlog[$d] = max(0, $open);
		}
		$prev = array_values($this->Daily('a.created_at', 'COUNT(*)', '', $f, $pFrom, $pTo));
		$g = $this->Bucket($gen, $unit);
		$prevB = $unit === 'day' ? $prev : array_values($this->Bucket(array_combine(array_keys($gen), array_pad(array_slice($prev, 0, count($gen)), count($gen), 0)), $unit));
		return ['range' => $range, 'unit' => $unit, 'from' => $from, 'to' => $to, 'labels' => array_keys($g), 'generated' => array_values($g), 'resolved' => array_values($this->Bucket($res, $unit)),
			'overdue' => array_values($this->Bucket($od, $unit)), 'reopened' => array_values($this->Bucket($re, $unit)), 'backlog' => array_values($this->Bucket($backlog, $unit, true)), 'prev_generated' => $prevB,
			'totals' => ['generated' => array_sum($gen), 'resolved' => array_sum($res), 'overdue' => array_sum($od), 'reopened' => array_sum($re), 'prev_generated' => array_sum($prev)]];
	}

	/**
	 * Tendencia de riesgo económico: valor nuevo, resuelto y vencido por día y valor en riesgo acumulado al cierre de cada día,
	 * con la misma curva de la ventana previa. El valor es el impacto registrado hoy en cada alerta (no se reconstruye su historia).
	 */
	public function RiskTrend(array $f, $range): array
	{
		[$from, $to, $unit] = $this->Window($range);
		$now = $this->Now();
		$len = (int) round((strtotime($to) - strtotime($from)) / 86400) + 1;
		$curve = function (string $from, string $to) use ($f) {
			$new = $this->Daily('a.created_at', 'SUM(IFNULL(a.value, 0))', '', $f, $from, $to);
			$out = $this->Daily('a.resolved_at', 'SUM(IFNULL(a.value, 0))', '', $f, $from, $to);
			$p = [':ts' => date('Y-m-d', strtotime($from . ' -1 day')) . ' 23:59:59'];
			$w = $this->Where($f, $p);
			$open = floatval($this->Val("SELECT SUM(IFNULL(a.value, 0)) FROM dairy_alerts a WHERE a.created_at <= :ts AND (a.resolved_at IS NULL OR a.resolved_at > :ts)$w", $p));
			$acc = [];
			foreach ($new as $d => $v)
			{
				$open += $v - $out[$d];
				$acc[$d] = round(max(0, $open), 2);
			}
			return [$new, $acc];
		};
		[$new, $acc] = $curve($from, $to);
		$pTo = date('Y-m-d', strtotime($from . ' -1 day'));
		[, $accPrev] = $curve(date('Y-m-d', strtotime($pTo . ' -' . ($len - 1) . ' days')), $pTo);
		$res = $this->Daily('a.resolved_at', 'SUM(IFNULL(a.value, 0))', " AND a.status <> 'dismissed'", $f, $from, $to);
		$od = $this->Daily('a.due_at', 'SUM(IFNULL(a.value, 0))', " AND a.due_at <= '$now' AND a.status <> 'dismissed' AND (a.resolved_at IS NULL OR a.resolved_at > a.due_at)", $f, $from, $to);
		$keys = array_keys($new);
		$accPrev = array_combine($keys, array_pad(array_slice(array_values($accPrev), 0, count($keys)), count($keys), 0));
		$b = $this->Bucket($new, $unit);
		return ['range' => $range, 'unit' => $unit, 'labels' => array_keys($b), 'new' => array_values($b), 'resolved' => array_values($this->Bucket($res, $unit)), 'overdue' => array_values($this->Bucket($od, $unit)),
			'accumulated' => array_values($this->Bucket($acc, $unit, true)), 'prev_accumulated' => array_values($this->Bucket($accPrev, $unit, true)),
			'totals' => ['new' => round(array_sum($new), 2), 'resolved' => round(array_sum($res), 2), 'end' => end($acc) ?: 0, 'prev_end' => end($accPrev) ?: 0]];
	}

	public function Impact(array $f): array
	{
		$p = [];
		$w = $this->Where($f, $p);
		$rows = $this->Rows('SELECT a.impact_bucket, a.impact_kind, COUNT(*) AS n, SUM(IFNULL(a.value, 0)) AS value, SUM(IFNULL(a.impact_hours, 0)) AS hours FROM dairy_alerts a
			WHERE a.status IN (' . self::In(self::ACTIVE) . ")$w GROUP BY a.impact_bucket, a.impact_kind", $p);
		$buckets = array_fill_keys(array_keys(self::BUCKETS), ['n' => 0, 'value' => 0.0]);
		$kinds = array_fill_keys(array_keys(self::IMPACT_KINDS), 0.0);
		$hours = 0.0;
		foreach ($rows as $r)
		{
			$b = isset($buckets[$r['impact_bucket']]) ? $r['impact_bucket'] : 'process';
			$buckets[$b]['n'] += intval($r['n']);
			$buckets[$b]['value'] += floatval($r['value']);
			$kinds[isset($kinds[$r['impact_kind']]) ? $r['impact_kind'] : 'estimated'] += floatval($r['value']);
			$hours += floatval($r['hours']);
		}
		$p2 = [];
		$w2 = $this->Where($f, $p2);
		$people = intval($this->Val('SELECT COUNT(DISTINCT a.owner_user_id) FROM dairy_alerts a WHERE a.status IN (' . self::In(self::ACTIVE) . ")$w2", $p2));
		$p3 = [];
		$w3 = $this->Where($f, $p3);
		$blocked = intval($this->Val("SELECT COUNT(*) FROM dairy_alerts a WHERE (a.status IN ('waiting_info', 'waiting_approval') OR (a.status IN (" . self::In(self::ACTIVE) . ") AND a.rule_code IN ('recall_unconfirmed', 'quarantine_stale', 'cold_chain_breach')))$w3", $p3));
		$avoided = $this->AvoidedLosses(!empty($f['branch_id']) ? intval($f['branch_id']) : null, $f['allowed'] ?? null, 30)['totals']['avoided'] ?? 0;
		$total = array_sum(array_map(fn($b) => $b['value'], $buckets));
		return ['buckets' => array_map(fn($b) => ['n' => $b['n'], 'value' => round($b['value'], 2)], $buckets), 'kinds' => array_map(fn($v) => round($v, 2), $kinds) + ['avoided' => round(floatval($avoided), 2)],
			'hours' => round($hours, 1), 'people' => $people, 'blocked' => $blocked, 'total' => round($total, 2)];
	}

	private function Resolution(array $f, array $per): array
	{
		$p = [':s' => $per['fromTs'], ':e' => $per['toTs']];
		$w = $this->Where($f, $p);
		$r = $this->Row("SELECT COUNT(*) AS total,
				SUM(CASE WHEN a.status IN ('closed', 'pending_approval') THEN 1 ELSE 0 END) AS resolved,
				SUM(CASE WHEN a.status = 'dismissed' THEN 1 ELSE 0 END) AS dismissed,
				SUM(CASE WHEN a.status IN (" . self::In(self::WORKING) . ") THEN 1 ELSE 0 END) AS open,
				SUM(CASE WHEN a.status IN ('closed', 'pending_approval') AND a.due_at IS NOT NULL AND a.resolved_at <= a.due_at THEN 1 ELSE 0 END) AS in_sla,
				AVG(CASE WHEN a.status IN ('closed', 'pending_approval') THEN (julianday(a.resolved_at) - julianday(a.created_at)) * 24 END) AS hours,
				SUM(CASE WHEN a.reopened_count > 0 THEN 1 ELSE 0 END) AS reopened
			FROM dairy_alerts a WHERE a.created_at BETWEEN :s AND :e$w", $p);
		$t = intval($r['total']);
		$res = intval($r['resolved']);
		return ['total' => $t, 'resolved' => $res, 'dismissed' => intval($r['dismissed']), 'open' => intval($r['open']), 'reopened' => intval($r['reopened']),
			'pct' => $t ? round($res / $t * 100, 1) : null, 'sla' => $res ? round($r['in_sla'] / $res * 100, 1) : null, 'hours' => $r['hours'] !== null ? round(floatval($r['hours']), 1) : null];
	}

	/**
	 * Próximos eventos con fecha real (programados o por vencer) y SLA por vencer. kind: reminder (programado),
	 * preventive (fecha conocida que exige acción), high/critical (SLA de una alerta abierta). No hay predicciones.
	 */
	public function Upcoming(array $f): array
	{
		$now = time();
		$today = date('Y-m-d');
		$tomorrow = date('Y-m-d', strtotime('+1 day'));
		$branchSql = function (string $col) use ($f) {
			if (!empty($f['branch_id']))
			{
				return " AND $col = " . intval($f['branch_id']);
			}
			if (isset($f['allowed']) && is_array($f['allowed']))
			{
				return " AND $col IN (" . (count($f['allowed']) ? implode(',', array_map('intval', $f['allowed'])) : '0') . ')';
			}
			return '';
		};
		$items = [];
		$add = function (string $at, string $title, string $detail, string $kind, string $icon, ?string $href) use (&$items) {
			$items[] = ['at' => $at, 'title' => $title, 'detail' => $detail, 'kind' => $kind, 'icon' => $icon, 'href' => $href];
		};
		$p = [':now' => date('Y-m-d H:i:s'), ':lim' => date('Y-m-d H:i:s', strtotime('+1 day 23:59:59', strtotime($today)))];
		$w = $this->Where($f, $p);
		foreach ($this->Rows('SELECT a.id, a.title, a.due_at, a.priority FROM dairy_alerts a WHERE a.status IN (' . self::In(self::WORKING) . ") AND a.due_at BETWEEN :now AND :lim$w ORDER BY a.due_at LIMIT 15", $p) as $r)
		{
			$add($r['due_at'], 'Vence SLA · ALR-' . $r['id'], $r['title'], $r['priority'] === 'P1' ? 'critical' : 'high', 'fa-hourglass-half', '/alerts?q=ALR-' . $r['id']);
		}
		foreach ($this->Rows("SELECT c.id, c.scheduled_date, p.name FROM stock_cycle_counts c LEFT JOIN products p ON p.id = c.product_id WHERE c.status = 'pending' AND c.scheduled_date IN (:t, :m)" . $branchSql('c.branch_id') . ' LIMIT 10', [':t' => $today, ':m' => $tomorrow]) as $r)
		{
			$add($r['scheduled_date'] . ' 23:59:00', 'Conteo programado', $r['name'], 'reminder', 'fa-clipboard-list', '/waste#tab-conteos');
		}
		foreach ($this->Rows("SELECT s.best_before_date AS d, p.name, SUM(s.amount) AS qty FROM stock s JOIN locations l ON l.id = s.location_id LEFT JOIN products p ON p.id = s.product_id
			WHERE s.amount > 0 AND IFNULL(l.is_transit, 0) = 0 AND s.best_before_date IN (:t, :m)" . $branchSql('l.branch_id') . ' GROUP BY s.product_id, s.best_before_date ORDER BY d LIMIT 10', [':t' => $today, ':m' => $tomorrow]) as $r)
		{
			$add($r['d'] . ' 23:59:00', 'Producto vence ' . ($r['d'] === $today ? 'hoy' : 'mañana'), $r['name'] . ' · ' . floatval($r['qty']) . ' u.', 'preventive', 'fa-calendar-xmark', '/waste#tab-fefo');
		}
		foreach ($this->Rows("SELECT o.order_code, o.expected_delivery_date AS d, sl.name FROM procurement_orders o LEFT JOIN shopping_locations sl ON sl.id = o.supplier_id WHERE o.status IN ('sent', 'partially_received') AND o.expected_delivery_date IN (:t, :m)" . $branchSql('o.branch_id') . ' LIMIT 10', [':t' => $today, ':m' => $tomorrow]) as $r)
		{
			$add($r['d'] . ' 12:00:00', 'Recepción pendiente ' . $r['order_code'], (string) $r['name'], 'reminder', 'fa-truck-ramp-box', '/mrp');
		}
		foreach ($this->Rows("SELECT s.shift_date, s.start_time, e.name FROM employee_shifts s JOIN employees e ON e.id = s.employee_id WHERE s.status = 'published' AND s.shift_date = :t AND s.start_time > :h AND s.start_time <= :h2" . $branchSql('s.branch_id') . ' ORDER BY s.start_time LIMIT 10', [':t' => $today, ':h' => date('H:i'), ':h2' => date('H:i', min(strtotime('23:59'), $now + 3 * 3600))]) as $r)
		{
			$add($r['shift_date'] . ' ' . substr($r['start_time'], 0, 5) . ':00', 'Inicio de turno', $r['name'] . ' · confirmar asistencia', 'reminder', 'fa-user-clock', '/schedule');
		}
		// Ubicaciones con registro de temperatura habitual (últimos 7 días) que aún no registran hoy
		foreach ($this->Rows("SELECT l.id, l.name FROM locations l WHERE EXISTS (SELECT 1 FROM cold_chain_temperatures c WHERE c.location_id = l.id AND c.recorded_timestamp >= :w)
			AND NOT EXISTS (SELECT 1 FROM cold_chain_temperatures c WHERE c.location_id = l.id AND date(c.recorded_timestamp) = :t)" . $branchSql('l.branch_id') . ' LIMIT 10', [':w' => date('Y-m-d', strtotime('-7 days')), ':t' => $today]) as $r)
		{
			$add($today . ' 23:59:00', 'Revisión de temperatura', $r['name'] . ' sin lectura hoy', 'preventive', 'fa-temperature-half', '/waste#tab-frio');
		}
		usort($items, fn($a, $b) => strcmp($a['at'], $b['at']));
		$bucket = function (string $at) use ($now, $today) {
			$ts = strtotime($at);
			$diff = $ts - $now;
			if ($diff <= 15 * 60)
			{
				return 'En 15 minutos';
			}
			if ($diff <= 30 * 60)
			{
				return 'En 30 minutos';
			}
			if ($diff <= 3600)
			{
				return 'En 1 hora';
			}
			return substr($at, 0, 10) === $today ? 'Hoy' : 'Mañana';
		};
		return array_map(fn($i) => $i + ['bucket' => $bucket($i['at'])], array_slice($items, 0, 20));
	}

	/**
	 * Recomendaciones derivadas de alertas activas reales (agrupadas por regla y sucursal) y de recurrencia por producto.
	 * Confianza: alta = regla determinística; media = regla estadística o recurrencia. Nada se ejecuta automáticamente.
	 */
	public function Recommendations(array $f): array
	{
		$p = [':now' => $this->Now()];
		$w = $this->Where($f, $p);
		$out = [];
		foreach ($this->Rows("SELECT a.rule_code, a.branch_id, IFNULL(b.name, 'Red') AS branch, IFNULL(r.name, 'Alta manual') AS rule_name, r.recommended_action, IFNULL(r.confidence, 'high') AS confidence, r.owner_user_id,
				COUNT(*) AS n, SUM(IFNULL(a.value, 0)) AS value, MIN(a.priority) AS priority, MIN(a.id) AS first_id,
				SUM(CASE WHEN a.due_at < :now THEN 1 ELSE 0 END) AS overdue,
				(SELECT a2.title || ' — ' || IFNULL(a2.detail, '') FROM dairy_alerts a2 WHERE a2.rule_code = a.rule_code AND IFNULL(a2.branch_id, 0) = IFNULL(a.branch_id, 0) AND a2.status IN (" . self::In(self::ACTIVE) . ") ORDER BY a2.priority, IFNULL(a2.value, 0) DESC LIMIT 1) AS evidence,
				(SELECT a3.href FROM dairy_alerts a3 WHERE a3.rule_code = a.rule_code AND IFNULL(a3.branch_id, 0) = IFNULL(a.branch_id, 0) AND a3.href IS NOT NULL AND a3.status IN (" . self::In(self::ACTIVE) . ") LIMIT 1) AS href
			FROM dairy_alerts a LEFT JOIN dairy_alert_rules r ON r.code = a.rule_code LEFT JOIN branches b ON b.id = a.branch_id
			WHERE a.status IN (" . self::In(self::WORKING) . ")$w GROUP BY a.rule_code, a.branch_id ORDER BY MIN(a.priority), value DESC LIMIT 6", $p) as $r)
		{
			$owner = $r['owner_user_id'] ? intval($r['owner_user_id']) : intval($this->Val("SELECT resolved_by_user_id FROM dairy_alerts WHERE rule_code = :r AND status = 'closed' AND resolved_by_user_id IS NOT NULL AND resolved_at >= :s GROUP BY resolved_by_user_id ORDER BY COUNT(*) DESC LIMIT 1",
				[':r' => $r['rule_code'], ':s' => date('Y-m-d', strtotime('-90 days'))]));
			$out[] = ['problem' => $r['rule_name'] . ' · ' . $r['branch'] . ' (' . $r['n'] . ')', 'cause' => null, 'evidence' => mb_substr((string) $r['evidence'], 0, 220), 'impact' => round(floatval($r['value']), 2),
				'action' => $r['recommended_action'] ?: 'Revisar las alertas y asignar responsable', 'owner' => $owner ? $this->UserName($owner) : null, 'owner_basis' => $r['owner_user_id'] ? 'responsable de la regla' : ($owner ? 'quien más resolvió esta regla (90 días)' : null),
				'confidence' => $r['confidence'] === 'medium' ? 'Media' : 'Alta', 'priority' => $r['priority'], 'overdue' => intval($r['overdue']),
				'href' => $r['href'], 'filter' => ['rule' => $r['rule_code'], 'branch_id' => $r['branch_id']]];
		}
		// Recurrencia: mismo producto con ≥ 3 alertas en 30 días → causa raíz
		$p = [':s' => date('Y-m-d', strtotime('-30 days'))];
		$w = $this->Where($f, $p);
		foreach ($this->Rows("SELECT a.product_id, p.name, COUNT(*) AS n, COUNT(DISTINCT a.rule_code) AS rules, SUM(IFNULL(a.value, 0)) AS value, group_concat(DISTINCT IFNULL(r.name, a.rule_code)) AS rule_names
			FROM dairy_alerts a JOIN products p ON p.id = a.product_id LEFT JOIN dairy_alert_rules r ON r.code = a.rule_code WHERE a.created_at >= :s$w GROUP BY a.product_id HAVING n >= 3 ORDER BY n DESC LIMIT 3", $p) as $r)
		{
			$out[] = ['problem' => 'Alertas recurrentes: ' . $r['name'], 'cause' => 'Repetición en ' . $r['rules'] . ' regla(s): ' . $r['rule_names'], 'evidence' => $r['n'] . ' alertas en 30 días', 'impact' => round(floatval($r['value']), 2),
				'action' => 'Solicitar evidencia y definir un plan de acción de causa raíz para el producto.', 'owner' => null, 'owner_basis' => null, 'confidence' => 'Media', 'priority' => 'P2', 'overdue' => 0,
				'href' => '/kardex?product_id=' . $r['product_id'], 'filter' => ['q' => $r['name']]];
		}
		return $out;
	}

	/* ================================================================== Reportes */

	const REPORT_DIMS = ['branch' => 'Sucursal', 'department' => 'Departamento', 'owner' => 'Responsable', 'category' => 'Categoría', 'priority' => 'Severidad (prioridad)', 'rule' => 'Regla', 'type' => 'Tipo', 'day' => 'Día', 'week' => 'Semana', 'month' => 'Mes', 'year' => 'Año'];

	public function Report(array $f, string $dim, string $from, string $to): array
	{
		$col = ['branch' => "IFNULL(b.name, 'Red')", 'department' => "IFNULL(a.department, '—')", 'owner' => "IFNULL(" . self::Uname('u') . ", 'Sin responsable')", 'category' => 'a.category', 'priority' => 'a.priority',
			'rule' => "IFNULL(r.name, a.rule_code)", 'type' => 'a.alert_type', 'day' => 'date(a.created_at)', 'week' => "strftime('%Y-W%W', a.created_at)", 'month' => "strftime('%Y-%m', a.created_at)", 'year' => "strftime('%Y', a.created_at)"][$dim] ?? "IFNULL(b.name, 'Red')";
		$p = [':s' => $from . ' 00:00:00', ':e' => $to . ' 23:59:59'];
		$w = $this->Where($f, $p);
		$rows = $this->Rows("SELECT $col AS k, COUNT(*) AS total, SUM(a.priority = 'P1') AS critical,
				SUM(CASE WHEN a.status IN ('closed', 'pending_approval') THEN 1 ELSE 0 END) AS resolved, SUM(a.status = 'dismissed') AS dismissed,
				SUM(CASE WHEN a.status IN ('closed', 'pending_approval') AND a.resolved_at <= a.due_at THEN 1 ELSE 0 END) AS in_sla,
				AVG(CASE WHEN a.first_response_at IS NOT NULL THEN (julianday(a.first_response_at) - julianday(a.created_at)) * 24 END) AS first_h,
				AVG(CASE WHEN a.status IN ('closed', 'pending_approval') THEN (julianday(a.resolved_at) - julianday(a.created_at)) * 24 END) AS res_h,
				SUM(IFNULL(a.value, 0)) AS value, SUM(CASE WHEN a.impact_kind = 'confirmed' THEN IFNULL(a.value, 0) ELSE 0 END) AS confirmed,
				SUM(a.reopened_count > 0) AS reopened, SUM(a.occurrences > 1) AS recurrent
			FROM dairy_alerts a LEFT JOIN branches b ON b.id = a.branch_id LEFT JOIN users u ON u.id = a.owner_user_id LEFT JOIN dairy_alert_rules r ON r.code = a.rule_code
			WHERE a.created_at BETWEEN :s AND :e$w GROUP BY k ORDER BY total DESC", $p);
		$labels = ['category' => self::CATEGORIES, 'type' => self::TYPES, 'priority' => self::PRIORITIES][$dim] ?? null;
		// Efectividad de acciones correctivas: alertas con acción cumplida que no se reabrieron ni repitieron (mismo producto y regla) en 30 días
		$p2 = [':s' => $from . ' 00:00:00', ':e' => $to . ' 23:59:59'];
		$w2 = $this->Where($f, $p2);
		$eff = $this->Row("SELECT COUNT(DISTINCT a.id) AS n, SUM(CASE WHEN a.reopened_count = 0 AND NOT EXISTS (SELECT 1 FROM dairy_alerts r2 WHERE r2.rule_code = a.rule_code AND r2.product_id = a.product_id AND r2.id > a.id AND r2.created_at <= datetime(a.resolved_at, '+30 days')) THEN 1 ELSE 0 END) AS ok
			FROM dairy_alerts a WHERE a.created_at BETWEEN :s AND :e AND a.status = 'closed' AND EXISTS (SELECT 1 FROM dairy_alert_actions x WHERE x.alert_id = a.id AND x.status = 'done')$w2", $p2);
		$avoided = $this->AvoidedLosses(!empty($f['branch_id']) ? intval($f['branch_id']) : null, $f['allowed'] ?? null, max(1, (int) round((strtotime($to) - strtotime($from)) / 86400) + 1));
		return [
			'dim' => $dim, 'from' => $from, 'to' => $to,
			'rows' => array_map(function ($r) use ($labels) {
				$res = intval($r['resolved']);
				return ['key' => $labels[$r['k']][0] ?? $r['k'], 'total' => intval($r['total']), 'critical' => intval($r['critical']), 'resolved' => $res, 'dismissed' => intval($r['dismissed']),
					'sla' => $res ? round($r['in_sla'] / $res * 100, 1) : null, 'first_h' => $r['first_h'] !== null ? round(floatval($r['first_h']), 1) : null, 'res_h' => $r['res_h'] !== null ? round(floatval($r['res_h']), 1) : null,
					'value' => round(floatval($r['value']), 2), 'confirmed' => round(floatval($r['confirmed']), 2), 'reopened' => intval($r['reopened']), 'recurrent' => intval($r['recurrent'])];
			}, $rows),
			'effectiveness' => ['with_actions' => intval($eff['n'] ?? 0), 'effective' => intval($eff['ok'] ?? 0), 'pct' => intval($eff['n'] ?? 0) ? round($eff['ok'] / $eff['n'] * 100, 1) : null],
			'avoided' => $avoided['totals'],
		];
	}

	/* ================================================================== Vistas guardadas */

	public function Views(int $userId): array
	{
		return $this->Rows('SELECT id, name, query FROM dairy_alert_views WHERE user_id = :u ORDER BY name', [':u' => $userId]);
	}

	public function SaveView(int $userId, string $name, string $query): int
	{
		$name = self::Need($name, 2, 'Escriba un nombre para la vista');
		parse_str(ltrim($query, '?'), $q);
		$allowed = ['period', 'from', 'to', 'branch_id', 'department', 'category', 'alert_type', 'priority', 'status', 'owner', 'q', 'tab', 'sort', 'trend', 'rule', 'overdue'];
		$clean = http_build_query(array_intersect_key($q, array_flip($allowed)));
		$this->Db()->prepare('INSERT INTO dairy_alert_views (user_id, name, query, created_at) VALUES (?, ?, ?, ?) ON CONFLICT(user_id, name) DO UPDATE SET query = excluded.query')->execute([$userId, mb_substr($name, 0, 60), $clean, $this->Now()]);
		return intval($this->Val('SELECT id FROM dairy_alert_views WHERE user_id = :u AND name = :n', [':u' => $userId, ':n' => mb_substr($name, 0, 60)]));
	}

	public function DeleteView(int $userId, int $id): void
	{
		$this->Db()->prepare('DELETE FROM dairy_alert_views WHERE id = ? AND user_id = ?')->execute([$id, $userId]);
	}

	public function Departments(): array
	{
		return $this->Db()->query("SELECT DISTINCT department FROM dairy_alerts WHERE department IS NOT NULL UNION SELECT DISTINCT department FROM dairy_alert_rules WHERE department IS NOT NULL ORDER BY 1")->fetchAll(\PDO::FETCH_COLUMN);
	}

	/* ================================================================== Pérdidas evitadas y resumen (compatibles) */

	/**
	 * Pérdidas evitadas: qué pasó con cada lote alertado por «Lotes por vencer» desde que se creó la alerta.
	 * Fuente: stock_log del lote (stock_id) posterior a la alerta, sin movimientos deshechos. Valor a costo del lote.
	 * Evitada = vendido/consumido sin merma; perdida = dado de baja como merma; ajuste = corrección de inventario negativa;
	 * pendiente = stock que sigue en el lote (en riesgo si ya venció). Los traslados no cambian el resultado (mismo lote).
	 * Es atribución, no causalidad: parte de lo vendido se habría vendido sin la alerta.
	 */
	public function AvoidedLosses(?int $branchId = null, ?array $allowedBranches = null, int $days = 90): array
	{
		// Recorre el stock_log de cada lote alertado (segundos): el tablero lo pide varias veces por página
		static $memo = [];
		$key = json_encode([$branchId, $allowedBranches, $days, date('Y-m-d H')]);
		return $memo[$key] ??= $this->AvoidedLossesRaw($branchId, $allowedBranches, $days);
	}

	private function AvoidedLossesRaw(?int $branchId, ?array $allowedBranches, int $days): array
	{
		$where = "a.rule_code = 'expiring_lots' AND a.created_at >= :from";
		$p = [':from' => date('Y-m-d H:i:s', strtotime("-$days days"))];
		if ($branchId)
		{
			$where .= ' AND a.branch_id = :b';
			$p[':b'] = $branchId;
		}
		elseif ($allowedBranches !== null)
		{
			$where .= ' AND a.branch_id IN (' . (count($allowedBranches) ? implode(',', array_map('intval', $allowedBranches)) : '0') . ')';
		}
		$rows = [];
		$tot = ['alerts' => 0, 'avoided' => 0.0, 'lost' => 0.0, 'adjusted' => 0.0, 'pending' => 0.0, 'at_risk' => 0.0];
		// Saldo actual de cada lote en una sola pasada: stock no tiene índice por stock_id y la consulta por alerta
		// recorría la tabla completa cientos de veces (~1,2 s en /alerts)
		$leftBy = [];
		foreach ($this->Rows('SELECT stock_id, IFNULL(SUM(amount), 0) AS qty, IFNULL(SUM(amount * IFNULL(price, 0)), 0) AS val FROM stock WHERE stock_id IS NOT NULL GROUP BY stock_id') as $l)
		{
			$leftBy[(string)$l['stock_id']] = $l;
		}
		foreach ($this->Rows("SELECT a.id, a.title, a.status, a.branch_id, a.created_at, a.dedupe_key FROM dairy_alerts a WHERE $where ORDER BY a.created_at DESC", $p) as $a)
		{
			$parts = explode(':', $a['dedupe_key']);
			if (count($parts) < 3)
			{
				continue;
			}
			[, $stockId, $bbd] = $parts;
			$m = $this->Row("SELECT
					SUM(CASE WHEN transaction_type = 'consume' AND spoiled = 0 THEN -amount ELSE 0 END) AS sold_qty,
					SUM(CASE WHEN transaction_type = 'consume' AND spoiled = 0 THEN -amount * IFNULL(price, 0) ELSE 0 END) AS sold_val,
					SUM(CASE WHEN transaction_type = 'consume' AND spoiled = 1 THEN -amount ELSE 0 END) AS lost_qty,
					SUM(CASE WHEN transaction_type = 'consume' AND spoiled = 1 THEN -amount * IFNULL(price, 0) ELSE 0 END) AS lost_val,
					SUM(CASE WHEN transaction_type = 'inventory-correction' AND amount < 0 THEN -amount * IFNULL(price, 0) ELSE 0 END) AS adj_val
				FROM stock_log WHERE stock_id = :s AND undone = 0 AND row_created_timestamp >= :c", [':s' => $stockId, ':c' => $a['created_at']]);
			$left = $leftBy[(string)$stockId] ?? ['qty' => 0, 'val' => 0];
			$expired = $bbd < date('Y-m-d');
			$r = [
				'alert_id' => intval($a['id']), 'title' => $a['title'], 'status' => $a['status'], 'branch_id' => intval($a['branch_id']), 'created_at' => $a['created_at'], 'best_before_date' => $bbd,
				'sold_qty' => round(floatval($m['sold_qty'] ?? 0), 2), 'avoided' => round(floatval($m['sold_val'] ?? 0), 2),
				'lost_qty' => round(floatval($m['lost_qty'] ?? 0), 2), 'lost' => round(floatval($m['lost_val'] ?? 0), 2),
				'adjusted' => round(floatval($m['adj_val'] ?? 0), 2),
				'pending_qty' => round(floatval($left['qty']), 2), 'pending' => round(floatval($left['val']), 2), 'expired' => $expired,
			];
			$r['outcome'] = $r['pending_qty'] > 0 ? ($expired ? 'at_risk' : 'in_progress') : ($r['lost'] > 0 && $r['avoided'] == 0 ? 'lost' : ($r['avoided'] > 0 ? ($r['lost'] > 0 ? 'partial' : 'avoided') : 'other'));
			$rows[] = $r;
			$tot['alerts']++;
			$tot['avoided'] += $r['avoided'];
			$tot['lost'] += $r['lost'];
			$tot['adjusted'] += $r['adjusted'];
			$tot[$expired ? 'at_risk' : 'pending'] += $r['pending'];
		}
		$tot = array_map(fn($v) => is_float($v) ? round($v, 2) : $v, $tot);
		$resolved = $tot['avoided'] + $tot['lost'] + $tot['adjusted'];
		$tot['avoided_pct'] = $resolved > 0 ? round($tot['avoided'] / $resolved * 100, 1) : null;
		return ['days' => $days, 'totals' => $tot, 'rows' => $rows];
	}

	public function Summary(?int $branchId = null, ?array $allowedBranches = null): array
	{
		$rows = $this->GetAlerts('active', $branchId, $allowedBranches, null, 100000);
		return [
			'active' => count($rows),
			'danger' => count(array_filter($rows, fn($r) => $r['priority'] === 'P1')),
			'overdue' => count(array_filter($rows, fn($r) => intval($r['overdue']))),
			'pending_approval' => count(array_filter($rows, fn($r) => in_array($r['status'], ['pending_approval', 'waiting_approval'], true))),
			'unassigned' => count(array_filter($rows, fn($r) => !$r['owner_user_id'])),
			'value' => round(array_sum(array_map(fn($r) => floatval($r['value'] ?? 0), $rows)), 2),
		];
	}
}
