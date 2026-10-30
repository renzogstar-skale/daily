<?php

namespace Grocy\Services;

use Grocy\Controllers\Users\User;

/**
 * Reportes personalizables y programados. El usuario no escribe SQL: elige un conjunto de datos del catálogo,
 * columnas, período, sucursal y orden. Seguridad: permiso del conjunto + sucursales del dueño, revalidados en
 * cada ejecución (también las programadas, que corren sin sesión desde bin/dairy_scheduler.php o al abrir /reports).
 */
class ReportBuilderService extends BaseService
{
	// sql: FROM/JOIN/WHERE base; {DATE} = columna de fecha; {BRANCH} = columna de sucursal. cols: clave => [etiqueta, expresión]
	const DATASETS = [
		'sales' => ['label' => 'Ventas por ticket', 'permission' => User::PERMISSION_SALES_VIEW, 'date' => 's.created_timestamp', 'branch' => 's.branch_id',
			'from' => 'sales s LEFT JOIN branches b ON b.id = s.branch_id', 'where' => "s.status != 'cancelled'",
			'cols' => ['date' => ['Fecha', 's.created_timestamp'], 'number' => ['Ticket', 's.sale_number'], 'branch' => ['Sucursal', 'b.name'], 'payment' => ['Medio de pago', 's.payment_method'],
				'subtotal' => ['Subtotal', 's.subtotal'], 'discount' => ['Descuento', 's.discount_amount'], 'tax' => ['Impuesto', 's.tax_amount'], 'total' => ['Total', 's.total_amount'], 'cogs' => ['Costo de ventas', 's.total_cogs'], 'margin' => ['Margen', 's.gross_margin'], 'status' => ['Estado', 's.status']]],
		'sales_products' => ['label' => 'Ventas por producto', 'permission' => User::PERMISSION_SALES_VIEW, 'date' => 's.created_timestamp', 'branch' => 's.branch_id',
			'from' => 'sale_items i JOIN sales s ON s.id = i.sale_id LEFT JOIN products p ON p.id = i.product_id LEFT JOIN product_groups pg ON pg.id = p.product_group_id LEFT JOIN branches b ON b.id = s.branch_id', 'where' => "s.status != 'cancelled'",
			'group' => 'i.product_id',
			'cols' => ['product' => ['Producto', 'p.name'], 'category' => ['Categoría', 'pg.name'], 'units' => ['Unidades', 'SUM(i.amount)'], 'sales' => ['Venta', 'SUM(i.subtotal)'], 'cogs' => ['Costo', 'SUM(i.total_cost)'], 'margin' => ['Margen', 'SUM(i.subtotal - IFNULL(i.total_cost, 0))'], 'tickets' => ['Tickets', 'COUNT(DISTINCT s.id)']]],
		'waste' => ['label' => 'Mermas', 'permission' => User::PERMISSION_WASTE_APPROVE, 'date' => 'w.created_timestamp', 'branch' => 'w.branch_id',
			'from' => 'waste_records w LEFT JOIN products p ON p.id = w.product_id LEFT JOIN waste_reasons r ON r.id = w.waste_reason_id LEFT JOIN branches b ON b.id = w.branch_id', 'where' => '1 = 1',
			'cols' => ['date' => ['Fecha', 'w.created_timestamp'], 'case' => ['Caso', 'w.case_number'], 'branch' => ['Sucursal', 'b.name'], 'product' => ['Producto', 'p.name'], 'reason' => ['Causal', "r.code || ' ' || r.name"], 'amount' => ['Cantidad', 'w.amount'],
				'cost' => ['Costo', 'w.total_cost'], 'recovered' => ['Recuperado', 'w.financial_recovery_amount'], 'status' => ['Estado', 'w.status'], 'lot' => ['Lote', 'w.batch_number']]],
		'kardex' => ['label' => 'Movimientos de inventario (kardex)', 'permission' => User::PERMISSION_STOCK, 'date' => 'l.row_created_timestamp', 'branch' => 'loc.branch_id',
			'from' => 'stock_log l LEFT JOIN products p ON p.id = l.product_id LEFT JOIN locations loc ON loc.id = l.location_id LEFT JOIN users u ON u.id = l.user_id', 'where' => 'l.undone = 0',
			'cols' => ['date' => ['Fecha', 'l.row_created_timestamp'], 'product' => ['Producto', 'p.name'], 'type' => ['Tipo', 'l.transaction_type'], 'amount' => ['Cantidad', 'l.amount'], 'price' => ['Costo unitario', 'l.price'],
				'value' => ['Valor', 'l.amount * IFNULL(l.price, 0)'], 'spoiled' => ['Merma', 'l.spoiled'], 'location' => ['Ubicación', 'loc.name'], 'lot' => ['Lote', 'l.stock_id'], 'user' => ['Usuario', 'u.username']]],
		'stock' => ['label' => 'Inventario valorizado por lote (actual)', 'permission' => User::PERMISSION_STOCK, 'date' => null, 'branch' => 'loc.branch_id',
			'from' => 'stock st JOIN products p ON p.id = st.product_id LEFT JOIN product_groups pg ON pg.id = p.product_group_id LEFT JOIN locations loc ON loc.id = st.location_id', 'where' => 'st.amount > 0',
			'cols' => ['product' => ['Producto', 'p.name'], 'category' => ['Categoría', 'pg.name'], 'location' => ['Ubicación', 'loc.name'], 'amount' => ['Cantidad', 'st.amount'], 'price' => ['Costo unitario', 'st.price'],
				'value' => ['Valor', 'st.amount * IFNULL(st.price, 0)'], 'best_before' => ['Vence', 'st.best_before_date'], 'lot' => ['Lote', 'st.stock_id']]],
		'purchases' => ['label' => 'Órdenes de compra', 'permission' => User::PERMISSION_PROCUREMENT_MANAGE, 'date' => 'po.created_at', 'branch' => 'po.branch_id',
			'from' => 'procurement_orders po LEFT JOIN shopping_locations sup ON sup.id = po.supplier_id LEFT JOIN branches b ON b.id = po.branch_id', 'where' => '1 = 1',
			'cols' => ['date' => ['Fecha', 'po.created_at'], 'code' => ['Orden', 'po.order_code'], 'supplier' => ['Proveedor', 'sup.name'], 'branch' => ['Sucursal', 'b.name'], 'status' => ['Estado', 'po.status'],
				'cost' => ['Costo estimado', 'po.total_estimated_cost'], 'expected' => ['Entrega esperada', 'po.expected_delivery_date']]],
		'shifts' => ['label' => 'Horarios (turnos)', 'permission' => User::PERMISSION_WORKFORCE_SCHEDULE_MANAGE, 'date' => 'sh.shift_date', 'branch' => 'sh.branch_id',
			'from' => 'employee_shifts sh JOIN employees e ON e.id = sh.employee_id LEFT JOIN branches b ON b.id = sh.branch_id', 'where' => "sh.status != 'cancelled'",
			'cols' => ['date' => ['Fecha', 'sh.shift_date'], 'employee' => ['Empleado', 'e.name'], 'branch' => ['Sucursal', 'b.name'], 'department' => ['Departamento', 'sh.department'], 'start' => ['Inicio', 'sh.start_time'], 'end' => ['Fin', 'sh.end_time'],
				'hours' => ['Horas', "ROUND((julianday('2000-01-01 ' || sh.end_time) - julianday('2000-01-01 ' || sh.start_time)) * 24 + CASE WHEN sh.end_time < sh.start_time THEN 24 ELSE 0 END - IFNULL(sh.break_minutes, 0) / 60.0, 2)"],
				'cost' => ['Costo', "ROUND(((julianday('2000-01-01 ' || sh.end_time) - julianday('2000-01-01 ' || sh.start_time)) * 24 + CASE WHEN sh.end_time < sh.start_time THEN 24 ELSE 0 END - IFNULL(sh.break_minutes, 0) / 60.0) * IFNULL(e.hourly_cost, 0), 2)"], 'status' => ['Estado', 'sh.status']]],
		'audits' => ['label' => 'Auditorías de tienda', 'permission' => User::PERMISSION_LOSS_PREVENTION, 'date' => 'a.started_at', 'branch' => 'a.branch_id',
			'from' => 'store_audits a LEFT JOIN branches b ON b.id = a.branch_id LEFT JOIN users u ON u.id = a.auditor_user_id', 'where' => '1 = 1',
			'cols' => ['date' => ['Inicio', 'a.started_at'], 'code' => ['Auditoría', 'a.audit_code'], 'branch' => ['Sucursal', 'b.name'], 'auditor' => ['Auditor', 'u.username'], 'status' => ['Estado', 'a.status'],
				'score' => ['Puntaje', 'a.score'], 'critical' => ['Fallas críticas', 'a.critical_failures'], 'completed' => ['Cierre', 'a.completed_at']]],
		'alerts' => ['label' => 'Alertas', 'permission' => User::PERMISSION_ALERTS_MANAGE, 'date' => 'al.created_at', 'branch' => 'al.branch_id',
			'from' => 'dairy_alerts al LEFT JOIN branches b ON b.id = al.branch_id LEFT JOIN users u ON u.id = al.owner_user_id', 'where' => '1 = 1',
			'cols' => ['date' => ['Creada', 'al.created_at'], 'rule' => ['Regla', 'al.rule_code'], 'title' => ['Alerta', 'al.title'], 'branch' => ['Sucursal', 'b.name'], 'severity' => ['Severidad', 'al.severity'], 'priority' => ['Prioridad', 'al.priority'], 'category' => ['Categoría', 'al.category'],
				'status' => ['Estado', 'al.status'], 'owner' => ['Responsable', 'u.username'], 'due' => ['Plazo', 'al.due_at'], 'resolved' => ['Resuelta', 'al.resolved_at'], 'value' => ['Valor', 'al.value']]],
	];
	const PERIODS = ['7d' => 'Últimos 7 días', '30d' => 'Últimos 30 días', '90d' => 'Últimos 90 días', 'mtd' => 'Mes en curso', 'prev_month' => 'Mes anterior', 'ytd' => 'Año en curso'];
	const SCHEDULES = ['none' => 'Sin programar', 'daily' => 'Diario', 'weekly' => 'Semanal (lunes)', 'monthly' => 'Mensual (día 1)'];
	const MAX_ROWS = 5000;

	private function Db(): \PDO
	{
		return DatabaseService::GetInstance()->GetDbConnectionRaw();
	}

	/** Catálogo visible para el usuario actual (solo conjuntos con permiso). */
	public function Catalog(): array
	{
		$out = [];
		foreach (self::DATASETS as $k => $d)
		{
			if (\Grocy\Helpers\DairyAccess::HasAny([$d['permission'], User::PERMISSION_ADMIN]))
			{
				$out[$k] = ['label' => $d['label'], 'has_date' => $d['date'] !== null, 'columns' => array_map(fn($c) => $c[0], $d['cols'])];
			}
		}
		return $out;
	}

	public static function Period(string $p, ?string $now = null): array
	{
		$t = $now ? strtotime($now) : time();
		$d = fn(string $m) => date('Y-m-d', strtotime($m, $t));
		switch ($p)
		{
			case '7d': return [$d('-6 days'), date('Y-m-d', $t)];
			case '90d': return [$d('-89 days'), date('Y-m-d', $t)];
			case 'mtd': return [date('Y-m-01', $t), date('Y-m-d', $t)];
			case 'prev_month': return [$d('first day of last month'), $d('last day of last month')];
			case 'ytd': return [date('Y-01-01', $t), date('Y-m-d', $t)];
			default: return [$d('-29 days'), date('Y-m-d', $t)];
		}
	}

	/** Valida y normaliza la configuración (rechaza columnas/orden que no estén en el catálogo). */
	public static function Normalize(string $dataset, array $c): array
	{
		$ds = self::DATASETS[$dataset] ?? null;
		if (!$ds)
		{
			throw new \Exception('Conjunto de datos no válido');
		}
		$cols = array_values(array_filter((array) ($c['columns'] ?? []), fn($k) => isset($ds['cols'][$k])));
		if (!count($cols))
		{
			throw new \Exception('Elija al menos una columna');
		}
		$sort = isset($ds['cols'][$c['sort'] ?? '']) ? $c['sort'] : $cols[0];
		return [
			'columns' => $cols,
			'period' => isset(self::PERIODS[$c['period'] ?? '']) ? $c['period'] : '30d',
			'branch_id' => !empty($c['branch_id']) ? intval($c['branch_id']) : null,
			'sort' => $sort,
			'dir' => strtolower(strval($c['dir'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC',
			'limit' => max(1, min(self::MAX_ROWS, intval($c['limit'] ?? 1000))),
		];
	}

	/** Permisos y sucursales de un usuario por id (para ejecuciones sin sesión). */
	private function UserCan(int $userId, string $permission): bool
	{
		$st = $this->Db()->prepare("SELECT COUNT(*) FROM user_permissions_resolved WHERE user_id = :u AND permission_name IN (:p, 'ADMIN')");
		$st->execute([':u' => $userId, ':p' => $permission]);
		return intval($st->fetchColumn()) > 0;
	}

	private function UserBranches(int $userId): ?array
	{
		if ($this->UserCan($userId, User::PERMISSION_ADMIN))
		{
			return null;
		}
		$st = $this->Db()->prepare('SELECT branch_id FROM user_branches WHERE user_id = :u');
		$st->execute([':u' => $userId]);
		$ids = array_map('intval', $st->fetchAll(\PDO::FETCH_COLUMN));
		return count($ids) ? $ids : null;
	}

	/** Ejecuta un reporte para un usuario; devuelve columnas, filas y período. */
	public function Run(string $dataset, array $config, int $userId, ?string $now = null): array
	{
		$cfg = self::Normalize($dataset, $config);
		$ds = self::DATASETS[$dataset];
		if (!$this->UserCan($userId, $ds['permission']))
		{
			throw new \Exception('Sin permiso para el conjunto «' . $ds['label'] . '»', 403);
		}
		$allowed = $this->UserBranches($userId);
		if ($cfg['branch_id'] !== null && $allowed !== null && !in_array($cfg['branch_id'], $allowed, true))
		{
			throw new \Exception('Sin acceso a la sucursal #' . $cfg['branch_id'], 403);
		}
		$where = [$ds['where']];
		$params = [];
		[$from, $to] = self::Period($cfg['period'], $now);
		if ($ds['date'])
		{
			$where[] = "date({$ds['date']}) BETWEEN :f AND :t";
			$params[':f'] = $from;
			$params[':t'] = $to;
		}
		if ($cfg['branch_id'] !== null)
		{
			$where[] = "{$ds['branch']} = :b";
			$params[':b'] = $cfg['branch_id'];
		}
		elseif ($allowed !== null)
		{
			$where[] = "{$ds['branch']} IN (" . implode(',', $allowed) . ')';
		}
		$select = implode(', ', array_map(fn($k) => $ds['cols'][$k][1] . ' AS "' . $k . '"', $cfg['columns']));
		$sortExpr = in_array($cfg['sort'], $cfg['columns'], true) ? '"' . $cfg['sort'] . '"' : $ds['cols'][$cfg['sort']][1];
		$sql = "SELECT $select FROM {$ds['from']} WHERE " . implode(' AND ', $where) . (isset($ds['group']) ? " GROUP BY {$ds['group']}" : '') . " ORDER BY $sortExpr {$cfg['dir']} LIMIT " . ($cfg['limit'] + 1);
		$st = $this->Db()->prepare($sql);
		$st->execute($params);
		$rows = $st->fetchAll(\PDO::FETCH_NUM);
		$truncated = count($rows) > $cfg['limit'];
		return [
			'dataset' => $dataset, 'label' => $ds['label'],
			'columns' => array_map(fn($k) => $ds['cols'][$k][0], $cfg['columns']),
			'rows' => array_slice($rows, 0, $cfg['limit']),
			'truncated' => $truncated,
			'period' => $ds['date'] ? [$from, $to] : null,
			'config' => $cfg,
		];
	}

	public static function ToCsv(array $r): string
	{
		$h = fopen('php://temp', 'w+');
		fwrite($h, "\xEF\xBB\xBF");
		fputcsv($h, $r['columns'], ';', '"', '');
		foreach ($r['rows'] as $row)
		{
			fputcsv($h, $row, ';', '"', '');
		}
		rewind($h);
		$csv = stream_get_contents($h);
		fclose($h);
		return $csv;
	}

	/* ---------------------------------------------------------------- Definiciones guardadas y programación */

	public static function NextRun(string $schedule, int $hour, ?int $from = null): ?string
	{
		$from ??= time();
		$hour = max(0, min(23, $hour));
		switch ($schedule)
		{
			case 'daily':
				$t = strtotime(date('Y-m-d', $from) . sprintf(' %02d:00:00', $hour));
				return date('Y-m-d H:i:s', $t > $from ? $t : strtotime('+1 day', $t));
			case 'weekly':
				$t = strtotime('monday this week ' . sprintf('%02d:00', $hour), $from);
				return date('Y-m-d H:i:s', $t > $from ? $t : strtotime('+1 week', $t));
			case 'monthly':
				$t = strtotime(date('Y-m-01', $from) . sprintf(' %02d:00:00', $hour));
				return date('Y-m-d H:i:s', $t > $from ? $t : strtotime('first day of next month ' . sprintf('%02d:00', $hour), $from));
		}
		return null;
	}

	public function Save(array $d, int $userId, ?int $id = null): int
	{
		$name = trim(mb_substr(strval($d['name'] ?? ''), 0, 120));
		if (mb_strlen($name) < 3)
		{
			throw new \Exception('Indique un nombre (mínimo 3 caracteres)');
		}
		$dataset = strval($d['dataset'] ?? '');
		$cfg = self::Normalize($dataset, (array) ($d['config'] ?? []));
		if (!$this->UserCan($userId, self::DATASETS[$dataset]['permission']))
		{
			throw new \Exception('Sin permiso para el conjunto elegido', 403);
		}
		$schedule = isset(self::SCHEDULES[$d['schedule'] ?? '']) ? $d['schedule'] : 'none';
		$hour = max(0, min(23, intval($d['schedule_hour'] ?? 7)));
		$next = self::NextRun($schedule, $hour);
		$db = $this->Db();
		if ($id)
		{
			$this->Get($id, $userId);
			$db->prepare('UPDATE dairy_report_definitions SET name = ?, dataset = ?, config_json = ?, schedule = ?, schedule_hour = ?, next_run_at = ?, updated_timestamp = ? WHERE id = ?')
				->execute([$name, $dataset, json_encode($cfg), $schedule, $hour, $next, date('Y-m-d H:i:s'), $id]);
			return $id;
		}
		$db->prepare('INSERT INTO dairy_report_definitions (name, dataset, config_json, schedule, schedule_hour, next_run_at, owner_user_id) VALUES (?, ?, ?, ?, ?, ?, ?)')
			->execute([$name, $dataset, json_encode($cfg), $schedule, $hour, $next, $userId]);
		return intval($db->lastInsertId());
	}

	/** Una definición; solo su dueño (o ADMIN) la ve, edita o ejecuta. */
	public function Get(int $id, int $userId): array
	{
		$st = $this->Db()->prepare('SELECT * FROM dairy_report_definitions WHERE id = :id AND active = 1');
		$st->execute([':id' => $id]);
		$r = $st->fetch(\PDO::FETCH_ASSOC);
		if (!$r || (intval($r['owner_user_id']) !== $userId && !$this->UserCan($userId, User::PERMISSION_ADMIN)))
		{
			throw new \Exception('Reporte no encontrado', 404);
		}
		$r['config'] = json_decode($r['config_json'], true);
		return $r;
	}

	public function ListFor(int $userId): array
	{
		$st = $this->Db()->prepare("SELECT d.*, (SELECT COUNT(*) FROM dairy_report_runs r WHERE r.definition_id = d.id) AS runs,
			(SELECT r.id FROM dairy_report_runs r WHERE r.definition_id = d.id AND r.status = 'ok' ORDER BY r.id DESC LIMIT 1) AS last_run_id,
			(SELECT r.status FROM dairy_report_runs r WHERE r.definition_id = d.id ORDER BY r.id DESC LIMIT 1) AS last_status
			FROM dairy_report_definitions d WHERE d.active = 1 AND d.owner_user_id = :u ORDER BY d.name");
		$st->execute([':u' => $userId]);
		return array_map(function ($r) {
			$r['config'] = json_decode($r['config_json'], true);
			$r['dataset_label'] = self::DATASETS[$r['dataset']]['label'] ?? $r['dataset'];
			return $r;
		}, $st->fetchAll(\PDO::FETCH_ASSOC));
	}

	public function Delete(int $id, int $userId): void
	{
		$this->Get($id, $userId);
		$this->Db()->prepare('UPDATE dairy_report_definitions SET active = 0, updated_timestamp = ? WHERE id = ?')->execute([date('Y-m-d H:i:s'), $id]);
	}

	/** Ejecuta una definición y guarda el CSV; los errores también quedan registrados. */
	public function Execute(int $id, int $userId, string $trigger = 'manual', ?string $now = null): array
	{
		$def = $this->Get($id, $userId);
		$owner = intval($def['owner_user_id']);
		$db = $this->Db();
		try
		{
			$r = $this->Run($def['dataset'], $def['config'], $owner, $now);
			$db->prepare("INSERT INTO dairy_report_runs (definition_id, trigger_type, status, period_from, period_to, row_count, csv, user_id) VALUES (?, ?, 'ok', ?, ?, ?, ?, ?)")
				->execute([$id, $trigger, $r['period'][0] ?? null, $r['period'][1] ?? null, count($r['rows']), self::ToCsv($r), $userId]);
			$runId = intval($db->lastInsertId());
			$status = 'ok';
		}
		catch (\Throwable $e)
		{
			$db->prepare("INSERT INTO dairy_report_runs (definition_id, trigger_type, status, error_message, user_id) VALUES (?, ?, 'failed', ?, ?)")->execute([$id, $trigger, $e->getMessage(), $userId]);
			$runId = intval($db->lastInsertId());
			$status = 'failed';
			$r = ['rows' => [], 'error' => $e->getMessage()];
		}
		$db->prepare('UPDATE dairy_report_definitions SET last_run_at = ?, next_run_at = ? WHERE id = ?')
			->execute([date('Y-m-d H:i:s', $now ? strtotime($now) : time()), self::NextRun($def['schedule'], intval($def['schedule_hour']), $now ? strtotime($now) : null), $id]);
		// Conserva las últimas 20 ejecuciones por reporte
		$db->prepare('DELETE FROM dairy_report_runs WHERE definition_id = ? AND id NOT IN (SELECT id FROM dairy_report_runs WHERE definition_id = ? ORDER BY id DESC LIMIT 20)')->execute([$id, $id]);
		return ['run_id' => $runId, 'status' => $status, 'rows' => count($r['rows']), 'error' => $r['error'] ?? null];
	}

	/** Programador: ejecuta las definiciones vencidas (cada una con los permisos de su dueño). */
	public function RunDue(?string $now = null): array
	{
		$nowStr = $now ?? date('Y-m-d H:i:s');
		$st = $this->Db()->prepare("SELECT id, owner_user_id FROM dairy_report_definitions WHERE active = 1 AND schedule != 'none' AND next_run_at IS NOT NULL AND next_run_at <= :n ORDER BY next_run_at LIMIT 50");
		$st->execute([':n' => $nowStr]);
		$out = [];
		foreach ($st->fetchAll(\PDO::FETCH_ASSOC) as $d)
		{
			$out[] = ['id' => intval($d['id'])] + $this->Execute(intval($d['id']), intval($d['owner_user_id']), 'schedule', $now);
		}
		return $out;
	}

	public function GetRun(int $runId, int $userId): array
	{
		$st = $this->Db()->prepare('SELECT * FROM dairy_report_runs WHERE id = :id');
		$st->execute([':id' => $runId]);
		$r = $st->fetch(\PDO::FETCH_ASSOC);
		if (!$r)
		{
			throw new \Exception('Ejecución no encontrada', 404);
		}
		$def = $this->Get(intval($r['definition_id']), $userId);
		$r['name'] = $def['name'];
		return $r;
	}

	public function Runs(int $userId, int $limit = 15): array
	{
		$st = $this->Db()->prepare('SELECT r.id, r.definition_id, r.trigger_type, r.status, r.period_from, r.period_to, r.row_count, r.error_message, r.row_created_timestamp, d.name
			FROM dairy_report_runs r JOIN dairy_report_definitions d ON d.id = r.definition_id WHERE d.owner_user_id = :u AND d.active = 1 ORDER BY r.id DESC LIMIT ' . intval($limit));
		$st->execute([':u' => $userId]);
		return $st->fetchAll(\PDO::FETCH_ASSOC);
	}
}
