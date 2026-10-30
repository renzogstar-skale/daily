<?php

namespace Grocy\Services;

/**
 * Planificación de personal según ventas (referencia funcional: Workcloud / UKG Forecasting, adaptado a tienda).
 *
 * Pronóstico del día = 70 % media ponderada (4-3-2-1) del mismo día de las últimas 4 semanas
 *                    + 30 % (promedio diario de 28 días × índice del día de semana)
 *                    × estacionalidad (solo con más de un año de historia) × eventos del calendario × corrección aprendida.
 * Horas de ventas  = ventas proyectadas ÷ productividad histórica (ventas por hora trabajada o programada); sin historia
 *                    laboral, tickets proyectados ÷ WORKFORCE_TICKETS_PER_STAFF_HOUR.
 * Horas de tareas  = recepción + reposición + FEFO + conteos + mermas + tareas de tienda + eventos (estándares editables).
 *                    Si la productividad sale de horas reales (que ya incluyen las tareas habituales) solo se suma el
 *                    excedente de cada tarea sobre su promedio diario de la misma ventana, para no contar dos veces.
 * Personas por franja = ⌈horas de trabajo de la franja ÷ (duración × WORKFORCE_EFFECTIVE_RATIO)⌉, con el mínimo de los
 *                    requisitos de cobertura y WORKFORCE_MIN_STAFF_OPEN en horas de atención.
 * Nada se inventa: sin ventas históricas no hay pronóstico de ventas (solo tareas y mínimos) y se dice por qué.
 */
class LaborForecastService extends BaseService
{
	const ROLES = ['ventas' => 'Ventas y atención', 'reposicion' => 'Reposición', 'inventario' => 'Inventario', 'operaciones' => 'Operaciones'];
	const EVENT_KINDS = ['holiday' => 'Festivo', 'promotion' => 'Promoción', 'event' => 'Evento'];
	const BLOCKING_WARNINGS = ['availability', 'rest', 'daily_limit', 'absence_requested'];

	private function Db(): \PDO
	{
		return DatabaseService::GetInstance()->GetDbConnectionRaw();
	}

	private function Q(string $sql, array $params = []): \PDOStatement
	{
		$stmt = $this->Db()->prepare($sql);
		$stmt->execute($params);
		return $stmt;
	}

	private function Ws(): WorkforceScheduleService
	{
		return WorkforceScheduleService::GetInstance();
	}

	private static function Cfg(string $name, $default)
	{
		return defined('GROCY_' . $name) ? constant('GROCY_' . $name) : $default;
	}

	private static function D(string $date, int $days): string
	{
		return date('Y-m-d', strtotime("$date " . ($days >= 0 ? '+' : '') . "$days day"));
	}

	private static function ParseRange(string $r, array $default): array
	{
		return preg_match('/^(\d{1,2}):?\d{0,2}\s*-\s*(\d{1,2}):?\d{0,2}$/', trim($r), $m) && intval($m[1]) < intval($m[2]) && intval($m[2]) <= 24 ? [intval($m[1]), intval($m[2])] : $default;
	}

	public static function Bands(): array
	{
		$out = [];
		foreach (explode(',', strval(self::Cfg('WORKFORCE_FORECAST_BANDS', '06-09,09-12,12-15,15-18,18-22'))) as $b)
		{
			[$a, $z] = self::ParseRange($b, [-1, -1]);
			if ($a >= 0)
			{
				$out[] = [$a, $z];
			}
		}
		return $out ?: [[6, 9], [9, 12], [12, 15], [15, 18], [18, 22]];
	}

	private static function Hm(int $h): string
	{
		return $h >= 24 ? '23:59' : sprintf('%02d:00', $h);
	}

	private static function Median(array $v): float
	{
		sort($v);
		$n = count($v);
		return $n % 2 ? $v[intdiv($n, 2)] : ($v[$n / 2 - 1] + $v[$n / 2]) / 2;
	}

	/* ------------------------------------------------------------------ Historia de ventas */

	// Ventas, tickets y unidades por fecha (solo ventas completadas de la sucursal)
	private function DailySeries(int $branchId, string $from, string $to): array
	{
		$out = [];
		foreach ($this->Q("SELECT date(created_timestamp) d, SUM(total_amount) s, COUNT(*) t FROM sales WHERE branch_id = :b AND status = 'completed' AND date(created_timestamp) BETWEEN :f AND :t GROUP BY d", [':b' => $branchId, ':f' => $from, ':t' => $to]) as $r)
		{
			$out[$r['d']] = ['sales' => floatval($r['s']), 'tickets' => intval($r['t']), 'units' => 0.0];
		}
		foreach ($this->Q("SELECT date(s.created_timestamp) d, SUM(i.amount) u FROM sale_items i JOIN sales s ON s.id = i.sale_id WHERE s.branch_id = :b AND s.status = 'completed' AND date(s.created_timestamp) BETWEEN :f AND :t GROUP BY d", [':b' => $branchId, ':f' => $from, ':t' => $to]) as $r)
		{
			if (isset($out[$r['d']]))
			{
				$out[$r['d']]['units'] = floatval($r['u']);
			}
		}
		return $out;
	}

	private function FirstSaleDate(int $branchId): ?string
	{
		return $this->Q("SELECT MIN(date(created_timestamp)) FROM sales WHERE branch_id = :b AND status = 'completed'", [':b' => $branchId])->fetchColumn() ?: null;
	}

	// Promedio diario en los $days días que terminan en $to (días sin venta cuentan como 0; solo desde la primera venta)
	private static function Avg(array $series, string $to, int $days, string $first, string $key): ?float
	{
		$sum = 0.0;
		$n = 0;
		for ($i = 0; $i < $days; $i++)
		{
			$d = self::D($to, -$i);
			if ($d < $first)
			{
				break;
			}
			$sum += $series[$d][$key] ?? 0;
			$n++;
		}
		return $n > 0 ? $sum / $n : null;
	}

	private function Project(array $series, string $date, string $histTo, ?string $first, string $key): array
	{
		if (!$first || $histTo < $first)
		{
			return ['value' => null, 'method' => 'no_history', 'same_weekday' => [], 'same_weekday_avg' => null, 'avg28' => null];
		}
		$same = [];
		for ($k = 1; $k <= 8; $k++)
		{
			$d = self::D($date, -7 * $k);
			if ($d <= $histTo && $d >= $first)
			{
				$same[$d] = $series[$d][$key] ?? 0.0;
			}
		}
		$avg28 = self::Avg($series, $histTo, 28, $first, $key);
		$avg56 = self::Avg($series, $histTo, 56, $first, $key);
		$sameAvg = count($same) ? array_sum($same) / count($same) : null;
		if (count($same) >= 2)
		{
			$w = [4, 3, 2, 1];
			$num = $den = 0.0;
			foreach (array_slice(array_values($same), 0, 4) as $i => $v)
			{
				$num += $w[$i] * $v;
				$den += $w[$i];
			}
			$index = $avg56 > 0 ? $sameAvg / $avg56 : 1.0;
			return ['value' => 0.7 * ($num / $den) + 0.3 * ($avg28 * $index), 'method' => 'same_weekday_blend', 'same_weekday' => $same, 'same_weekday_avg' => $sameAvg, 'avg28' => $avg28, 'weekday_index' => round($index, 3)];
		}
		return ['value' => $avg28, 'method' => 'avg28', 'same_weekday' => $same, 'same_weekday_avg' => $sameAvg, 'avg28' => $avg28];
	}

	// Índice estacional: cómo cambió la venta el año pasado entre las 4 semanas previas y las 4 siguientes a esta fecha
	private function Seasonality(int $branchId, string $date, ?string $first): array
	{
		$ly = self::D($date, -364);
		if (!$first || $first > self::D($ly, -28))
		{
			return ['factor' => 1.0, 'applied' => false, 'note' => 'Sin un año de historia: no se aplica estacionalidad'];
		}
		$s = $this->DailySeries($branchId, self::D($ly, -28), self::D($ly, 27));
		$before = $after = 0.0;
		foreach ($s as $d => $v)
		{
			$d < $ly ? $before += $v['sales'] : $after += $v['sales'];
		}
		if ($before <= 0 || $after <= 0)
		{
			return ['factor' => 1.0, 'applied' => false, 'note' => 'Sin ventas suficientes el año anterior'];
		}
		$f = max(0.8, min(1.25, $after / $before));
		return ['factor' => round($f, 3), 'applied' => true, 'note' => 'Año anterior: ' . ($f >= 1 ? '+' : '') . round(($f - 1) * 100, 1) . ' % en las 4 semanas siguientes'];
	}

	// Corrección aprendida: mediana de real ÷ pronosticado de los últimos días cerrados (mín. 3, acotada a ±15 %)
	public function LearnedCorrection(int $branchId, string $before): array
	{
		$rows = $this->Q("SELECT forecast_date, projected_sales FROM labor_forecast_snapshots WHERE branch_id = :b AND forecast_date < :d AND forecast_date >= date(:d, '-56 day') AND projected_sales > 0 ORDER BY forecast_date DESC LIMIT 12", [':b' => $branchId, ':d' => min($before, date('Y-m-d'))])->fetchAll(\PDO::FETCH_ASSOC);
		$ratios = [];
		foreach ($rows as $r)
		{
			$actual = floatval($this->Q("SELECT IFNULL(SUM(total_amount), 0) FROM sales WHERE branch_id = :b AND status = 'completed' AND date(created_timestamp) = :d", [':b' => $branchId, ':d' => $r['forecast_date']])->fetchColumn());
			$ratios[] = $actual / floatval($r['projected_sales']);
		}
		if (count($ratios) < 3)
		{
			return ['factor' => 1.0, 'samples' => count($ratios), 'applied' => false];
		}
		return ['factor' => round(max(0.85, min(1.15, self::Median($ratios))), 3), 'samples' => count($ratios), 'applied' => true];
	}

	/**
	 * Factores de demanda por día futuro para el MRP (mismo pronóstico que el laboral, sin duplicar cálculo):
	 * eventos del calendario × corrección aprendida × estacionalidad anual. El patrón por día de semana lo aplica el MRP.
	 * Red (sin sucursal): solo eventos de toda la red; corrección y estacionalidad son por sucursal.
	 */
	public function DemandOutlook(?int $branchId, int $days): array
	{
		$today = date('Y-m-d');
		$to = self::D($today, $days);
		$events = $branchId
			? $this->GetEvents($branchId, self::D($today, 1), $to)
			: $this->Q('SELECT * FROM labor_calendar_events WHERE branch_id = 0 AND event_date BETWEEN :f AND :t ORDER BY event_date', [':f' => self::D($today, 1), ':t' => $to])->fetchAll(\PDO::FETCH_ASSOC);
		$byDate = [];
		foreach ($events as $e)
		{
			$byDate[$e['event_date']] = ($byDate[$e['event_date']] ?? 1.0) * max(0.1, floatval($e['sales_factor']));
		}
		$learn = $branchId ? $this->LearnedCorrection($branchId, $today) : ['factor' => 1.0, 'samples' => 0, 'applied' => false];
		$season = $branchId ? $this->Seasonality($branchId, self::D($today, 1), $this->FirstSaleDate($branchId)) : ['factor' => 1.0, 'applied' => false, 'note' => 'Red: estacionalidad por sucursal'];
		$factors = [];
		for ($k = 1; $k <= $days; $k++)
		{
			$factors[$k] = round(($byDate[self::D($today, $k)] ?? 1.0) * $learn['factor'] * $season['factor'], 4);
		}
		return [
			'factors' => $factors,
			'events' => array_map(fn($e) => ['date' => $e['event_date'], 'name' => $e['name'], 'sales_factor' => floatval($e['sales_factor'])], $events),
			'correction' => $learn,
			'seasonality' => $season,
			'applied' => !empty($events) || $learn['applied'] || $season['applied']
		];
	}

	private function Events(int $branchId, string $date): array
	{
		return $this->Q('SELECT * FROM labor_calendar_events WHERE event_date = :d AND branch_id IN (0, :b) ORDER BY id', [':d' => $date, ':b' => $branchId])->fetchAll(\PDO::FETCH_ASSOC);
	}

	// Participación por hora (ventas y tickets) del mismo día de semana en 8 semanas; si no hay, de los últimos 28 días
	private function HourlyProfile(int $branchId, string $date, string $histTo): array
	{
		$wd = date('w', strtotime($date));
		$run = function (string $extra, array $p) use ($branchId) {
			$sales = array_fill(0, 24, 0.0);
			$tickets = array_fill(0, 24, 0.0);
			foreach ($this->Q("SELECT CAST(strftime('%H', created_timestamp) AS INTEGER) h, SUM(total_amount) s, COUNT(*) t FROM sales WHERE branch_id = :b AND status = 'completed' $extra GROUP BY h", [':b' => $branchId] + $p) as $r)
			{
				$sales[intval($r['h'])] = floatval($r['s']);
				$tickets[intval($r['h'])] = floatval($r['t']);
			}
			return [$sales, $tickets];
		};
		[$s, $t] = $run("AND strftime('%w', created_timestamp) = :w AND date(created_timestamp) BETWEEN :f AND :t", [':w' => $wd, ':f' => self::D($date, -56), ':t' => $histTo]);
		$source = 'same_weekday';
		if (array_sum($t) == 0)
		{
			[$s, $t] = $run('AND date(created_timestamp) BETWEEN :f AND :t', [':f' => self::D($histTo, -27), ':t' => $histTo]);
			$source = 'last_28_days';
		}
		$ss = array_sum($s);
		$ts = array_sum($t);
		return [
			'sales' => array_map(fn($v) => $ss > 0 ? $v / $ss : 0, $s),
			'tickets' => array_map(fn($v) => $ts > 0 ? $v / $ts : 0, $t),
			'source' => $ts > 0 ? $source : 'none'
		];
	}

	// Participación de cada departamento (grupo de producto) en la venta de 28 días
	private function DepartmentShares(int $branchId, string $histTo): array
	{
		$rows = $this->Q("SELECT IFNULL(pg.name, 'Sin grupo') dep, SUM(i.subtotal) v FROM sale_items i JOIN sales s ON s.id = i.sale_id JOIN products p ON p.id = i.product_id LEFT JOIN product_groups pg ON pg.id = p.product_group_id
			WHERE s.branch_id = :b AND s.status = 'completed' AND date(s.created_timestamp) BETWEEN :f AND :t GROUP BY dep ORDER BY v DESC", [':b' => $branchId, ':f' => self::D($histTo, -27), ':t' => $histTo])->fetchAll(\PDO::FETCH_ASSOC);
		$tot = array_sum(array_column($rows, 'v'));
		return $tot > 0 ? array_map(fn($r) => ['department' => $r['dep'], 'share' => floatval($r['v']) / $tot], $rows) : [];
	}

	/* ------------------------------------------------------------------ Productividad y carga operativa */

	public function GetStandards(): array
	{
		$out = [];
		foreach ($this->Q('SELECT * FROM labor_task_standards ORDER BY role, code')->fetchAll(\PDO::FETCH_ASSOC) as $r)
		{
			$r['minutes'] = floatval($r['minutes']);
			$out[$r['code']] = $r;
		}
		return $out;
	}

	public function SaveStandards(array $rows): void
	{
		$known = $this->GetStandards();
		foreach ($rows as $code => $minutes)
		{
			if (!isset($known[$code]) || !is_numeric($minutes) || floatval($minutes) < 0 || floatval($minutes) > 600)
			{
				throw new \Exception("Estándar inválido: $code");
			}
		}
		foreach ($rows as $code => $minutes)
		{
			$this->Q('UPDATE labor_task_standards SET minutes = :m WHERE code = :c', [':m' => floatval($minutes), ':c' => $code]);
		}
	}

	// Ventas por hora laboral de los 28 días cerrados; fuente: horas fichadas, si no programadas, si no configuración/tickets
	private function Productivity(int $branchId, string $histTo): array
	{
		$from = self::D($histTo, -27);
		$sales = floatval($this->Q("SELECT IFNULL(SUM(total_amount), 0) FROM sales WHERE branch_id = :b AND status = 'completed' AND date(created_timestamp) BETWEEN :f AND :t", [':b' => $branchId, ':f' => $from, ':t' => $histTo])->fetchColumn());
		$worked = array_sum($this->Ws()->WorkedHoursByEmployee($from, $histTo, $branchId));
		$scheduled = 0.0;
		foreach ($this->Q("SELECT * FROM employee_shifts WHERE branch_id = :b AND status != 'cancelled' AND shift_date BETWEEN :f AND :t", [':b' => $branchId, ':f' => $from, ':t' => $histTo])->fetchAll(\PDO::FETCH_OBJ) as $s)
		{
			$scheduled += WorkforceScheduleService::ShiftHours($s);
		}
		$base = ['from' => $from, 'to' => $histTo, 'sales' => round($sales, 2), 'worked_hours' => round($worked, 1), 'scheduled_hours' => round($scheduled, 1)];
		if ($sales > 0 && $worked >= 8)
		{
			return $base + ['value' => round($sales / $worked, 2), 'source' => 'worked', 'includes_tasks' => true, 'label' => 'Ventas ÷ horas fichadas (28 días)'];
		}
		if ($sales > 0 && $scheduled >= 8)
		{
			return $base + ['value' => round($sales / $scheduled, 2), 'source' => 'scheduled', 'includes_tasks' => true, 'label' => 'Ventas ÷ horas programadas (28 días, sin fichajes)'];
		}
		$cfg = floatval(self::Cfg('WORKFORCE_SALES_PER_LABOR_HOUR', 0));
		if ($cfg > 0)
		{
			return $base + ['value' => $cfg, 'source' => 'config', 'includes_tasks' => false, 'label' => 'Configuración WORKFORCE_SALES_PER_LABOR_HOUR'];
		}
		return $base + ['value' => null, 'source' => 'tickets', 'includes_tasks' => false, 'tickets_per_staff_hour' => max(1, floatval(self::Cfg('WORKFORCE_TICKETS_PER_STAFF_HOUR', 20))), 'label' => 'Tickets ÷ ' . max(1, floatval(self::Cfg('WORKFORCE_TICKETS_PER_STAFF_HOUR', 20))) . ' por persona-hora (sin historia laboral)'];
	}

	// Cantidades operativas del día (lo esperado) y promedio diario realizado en la ventana de productividad
	private function TaskLoad(int $branchId, string $date, string $histTo, float $projectedUnits, array $series, ?string $first): array
	{
		$std = $this->GetStandards();
		$from = self::D($histTo, -27);
		$isToday = $date === date('Y-m-d');

		// Recepción: órdenes con entrega esperada y entregas de proveedor pendientes (sin repetir la misma referencia)
		$po = $this->Q("SELECT o.order_code, IFNULL(SUM(MAX(0, IFNULL(i.approved_amount, i.suggested_amount) - i.received_amount)), 0) u FROM procurement_orders o LEFT JOIN procurement_order_items i ON i.order_id = o.id
			WHERE o.branch_id = :b AND o.expected_delivery_date = :d AND o.status IN ('approved', 'sent', 'partially_received') GROUP BY o.id", [':b' => $branchId, ':d' => $date])->fetchAll(\PDO::FETCH_ASSOC);
		$codes = array_column($po, 'order_code');
		$deliveries = count($po);
		$recvUnits = array_sum(array_map('floatval', array_column($po, 'u')));
		foreach ($this->Q('SELECT order_reference, MAX(0, items_ordered - items_received) u FROM supplier_deliveries WHERE branch_id = :b AND expected_delivery_date = :d AND actual_delivery_date IS NULL', [':b' => $branchId, ':d' => $date])->fetchAll(\PDO::FETCH_ASSOC) as $r)
		{
			if (!in_array($r['order_reference'], $codes, true))
			{
				$deliveries++;
				$recvUnits += floatval($r['u']);
			}
		}
		$fefoDays = max(1, intval(self::Cfg('WORKFORCE_FEFO_DAYS', 3)));
		$fefoLots = intval($this->Q('SELECT COUNT(*) FROM stock s JOIN locations l ON l.id = s.location_id WHERE IFNULL(l.branch_id, 1) = :b AND s.amount > 0 AND s.best_before_date BETWEEN :d AND date(:d, :n)', [':b' => $branchId, ':d' => $date, ':n' => "+$fefoDays day"])->fetchColumn());
		$counts = intval($this->Q("SELECT COUNT(*) FROM stock_cycle_counts WHERE branch_id = :b AND status = 'pending' AND " . ($isToday ? 'scheduled_date <= :d' : 'scheduled_date = :d'), [':b' => $branchId, ':d' => $date])->fetchColumn());
		$tasks = intval($this->Q("SELECT COUNT(*) FROM workforce_store_tasks WHERE branch_id = :b AND status = 'pending' AND " . ($isToday ? '(due_time IS NULL OR date(due_time) <= :d)' : 'date(due_time) = :d'), [':b' => $branchId, ':d' => $date])->fetchColumn());
		$wasteAvg = floatval($this->Q('SELECT COUNT(*) FROM waste_records WHERE branch_id = :b AND date(created_timestamp) BETWEEN :f AND :t', [':b' => $branchId, ':f' => $from, ':t' => $histTo])->fetchColumn()) / 28;

		// Promedio diario realizado (base que ya está dentro de la productividad histórica)
		$days = $first ? max(1, min(28, intval((strtotime($histTo) - strtotime(max($from, $first))) / 86400) + 1)) : 28;
		$unitsAvg = $first ? (self::Avg($series, $histTo, 28, $first, 'units') ?? 0) : 0;
		$delivAvg = floatval($this->Q('SELECT COUNT(*) FROM supplier_deliveries WHERE branch_id = :b AND actual_delivery_date BETWEEN :f AND :t', [':b' => $branchId, ':f' => $from, ':t' => $histTo])->fetchColumn())
			+ floatval($this->Q('SELECT COUNT(*) FROM procurement_receipts r JOIN procurement_orders o ON o.id = r.order_id WHERE o.branch_id = :b AND date(r.received_at) BETWEEN :f AND :t', [':b' => $branchId, ':f' => $from, ':t' => $histTo])->fetchColumn());
		$countsAvg = floatval($this->Q("SELECT COUNT(*) FROM stock_cycle_counts WHERE branch_id = :b AND executed_date BETWEEN :f AND :t", [':b' => $branchId, ':f' => $from, ':t' => $histTo])->fetchColumn());
		$tasksAvg = floatval($this->Q('SELECT COUNT(*) FROM workforce_store_tasks WHERE branch_id = :b AND date(completed_at) BETWEEN :f AND :t', [':b' => $branchId, ':f' => $from, ':t' => $histTo])->fetchColumn());

		$m = fn(string $c) => $std[$c]['minutes'] ?? 0;
		$items = [
			['code' => 'receiving', 'label' => 'Recepción de mercancía', 'role' => 'operaciones', 'qty' => $deliveries . ' entregas · ' . round($recvUnits) . ' u.',
				'hours' => ($deliveries * $m('receiving_delivery') + $recvUnits * $m('receiving_unit')) / 60, 'baseline' => ($delivAvg / $days) * $m('receiving_delivery') / 60, 'deliveries' => $deliveries, 'units' => $recvUnits],
			['code' => 'restock', 'label' => 'Reposición', 'role' => 'reposicion', 'qty' => round($projectedUnits) . ' u. vendidas proyectadas',
				'hours' => $projectedUnits * $m('restock_unit') / 60, 'baseline' => $unitsAvg * $m('restock_unit') / 60],
			['code' => 'fefo', 'label' => 'FEFO / próximos a vencer', 'role' => 'reposicion', 'qty' => $fefoLots . " lotes vencen en ≤ $fefoDays días",
				'hours' => $fefoLots * $m('fefo_lot') / 60, 'baseline' => 0.0, 'lots' => $fefoLots],
			['code' => 'counts', 'label' => 'Conteos cíclicos', 'role' => 'inventario', 'qty' => $counts . ' SKU',
				'hours' => $counts * $m('cycle_count') / 60, 'baseline' => ($countsAvg / $days) * $m('cycle_count') / 60],
			['code' => 'waste', 'label' => 'Registro de mermas', 'role' => 'operaciones', 'qty' => round($wasteAvg, 1) . ' registros/día (promedio)',
				'hours' => $wasteAvg * $m('waste_record') / 60, 'baseline' => $wasteAvg * $m('waste_record') / 60],
			['code' => 'tasks', 'label' => 'Tareas de tienda pendientes', 'role' => 'operaciones', 'qty' => $tasks . ' tareas',
				'hours' => $tasks * $m('store_task') / 60, 'baseline' => ($tasksAvg / $days) * $m('store_task') / 60, 'pending' => $tasks],
		];
		return $items;
	}

	/* ------------------------------------------------------------------ Pronóstico del día */

	public function Forecast(int $branchId, string $date, bool $persist = true, bool $explain = true, array $scenario = []): array
	{
		$sc = self::ScenarioParams($scenario);
		if ($sc)
		{
			$persist = false; // un escenario nunca alimenta el aprendizaje ni toca datos
		}
		if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !strtotime($date))
		{
			throw new \Exception('Fecha inválida (AAAA-MM-DD)');
		}
		$branch = $this->Q('SELECT id, name FROM branches WHERE id = :b', [':b' => $branchId])->fetch(\PDO::FETCH_ASSOC);
		if (!$branch)
		{
			throw new \Exception('Sucursal no encontrada');
		}
		$today = date('Y-m-d');
		$histTo = min(self::D($date, -1), self::D($today, -1)); // solo días cerrados
		$first = $this->FirstSaleDate($branchId);
		$series = $this->DailySeries($branchId, self::D($histTo, -89), $histTo);
		$eff = max(0.5, min(1.0, floatval(self::Cfg('WORKFORCE_EFFECTIVE_RATIO', 0.9))));

		// 1. Ventas, tickets y unidades
		$ps = $this->Project($series, $date, $histTo, $first, 'sales');
		$pt = $this->Project($series, $date, $histTo, $first, 'tickets');
		$pu = $this->Project($series, $date, $histTo, $first, 'units');
		$season = $this->Seasonality($branchId, $date, $first);
		$events = $this->Events($branchId, $date);
		$eventFactor = 1.0;
		$eventHours = 0.0;
		foreach ($events as $e)
		{
			$eventFactor *= max(0.1, floatval($e['sales_factor']));
			$eventHours += max(0, floatval($e['extra_hours']));
		}
		$learn = $this->LearnedCorrection($branchId, $date);
		$factor = $season['factor'] * $eventFactor * $learn['factor'] * (1 + ($sc['sales_pct'] ?? 0) / 100);
		$projSales = $ps['value'] !== null ? round($ps['value'] * $factor, 2) : null;
		$projTickets = $pt['value'] !== null ? round($pt['value'] * $factor, 1) : null;
		$projUnits = $pu['value'] !== null ? round($pu['value'] * $factor, 1) : 0.0;
		$windows = [];
		foreach ([7, 14, 30, 60, 90] as $n)
		{
			$windows[$n] = $first ? (($a = self::Avg($series, $histTo, $n, $first, 'sales')) !== null ? round($a, 2) : null) : null;
		}

		// 2. Productividad y horas de ventas
		$prod = $this->Productivity($branchId, $histTo);
		if ($prod['value'])
		{
			$salesHours = $projSales !== null ? $projSales / $prod['value'] : 0.0;
		}
		else
		{
			$salesHours = $projTickets !== null ? $projTickets / $prod['tickets_per_staff_hour'] : 0.0;
		}

		$prodFactor = 1 + ($sc['productivity_pct'] ?? 0) / 100;
		$salesHours /= $prodFactor;

		// 3. Carga operativa (sin doble conteo si la productividad ya incluye las tareas habituales)
		$tasks = $this->TaskLoad($branchId, $date, $histTo, $projUnits, $series, $first);
		if (!empty($sc['extra_pallets']))
		{
			$mpp = max(1, floatval(self::Cfg('WORKFORCE_MINUTES_PER_PALLET', 20)));
			$tasks[] = ['code' => 'receiving', 'label' => 'Pallets adicionales (escenario)', 'role' => 'operaciones', 'qty' => $sc['extra_pallets'] . ' pallets × ' . $mpp . ' min', 'hours' => $sc['extra_pallets'] * $mpp / 60, 'baseline' => 0.0];
		}
		if ($eventHours > 0)
		{
			$tasks[] = ['code' => 'events', 'label' => 'Eventos del calendario', 'role' => 'operaciones', 'qty' => implode(', ', array_column($events, 'name')), 'hours' => $eventHours, 'baseline' => 0.0];
		}
		foreach ($tasks as &$t)
		{
			$t['counted'] = ($prod['includes_tasks'] ? max(0, $t['hours'] - $t['baseline']) : $t['hours']) / $prodFactor;
			$t['hours'] = round($t['hours'], 2);
			$t['baseline'] = round($t['baseline'], 2);
			$t['counted'] = round($t['counted'], 2);
		}
		unset($t);
		$taskHours = array_sum(array_column($tasks, 'counted'));

		// 4. Distribución por hora
		$profile = $this->HourlyProfile($branchId, $date, $histTo);
		$ws = $this->Ws();
		$dayShifts = $this->Q("SELECT s.*, e.name AS employee_name, e.hourly_cost FROM employee_shifts s JOIN employees e ON e.id = s.employee_id WHERE s.branch_id = :b AND s.status != 'cancelled' AND s.shift_date = :d ORDER BY s.start_time", [':b' => $branchId, ':d' => $date])->fetchAll(\PDO::FETCH_OBJ);
		$scenarioChanges = [];
		if (!empty($sc['absent_staff']) || !empty($sc['cut_hours']))
		{
			[$dayShifts, $scenarioChanges] = self::ApplyShiftScenario($dayShifts, $sc);
			$prev = $this->Q("SELECT * FROM employee_shifts WHERE branch_id = :b AND status != 'cancelled' AND shift_date = date(:d, '-1 day')", [':b' => $branchId, ':d' => $date])->fetchAll(\PDO::FETCH_OBJ);
			$cov = $ws->CoverageForDay($branchId, $date, array_merge($prev, $dayShifts));
		}
		else
		{
			$cov = $ws->CoverageForDay($branchId, $date);
		}
		[$oa, $oz] = self::ParseRange(strval(self::Cfg('WORKFORCE_STORE_HOURS', '06:00-22:00')), [6, 22]);
		$open = array_fill(0, 24, false);
		for ($h = 0; $h < 24; $h++)
		{
			$open[$h] = $profile['source'] !== 'none' ? $profile['sales'][$h] > 0 || $cov['required'][$h] > 0 : ($h >= $oa && $h < $oz) || $cov['required'][$h] > 0;
		}
		if ($profile['source'] !== 'none')
		{
			// Rellena huecos entre la primera y la última hora con venta (la tienda no cierra a media jornada)
			$hs = array_keys(array_filter($open));
			for ($h = min($hs); $h <= max($hs); $h++)
			{
				$open[$h] = true;
			}
		}
		$openHours = array_keys(array_filter($open));
		[$ra, $rz] = self::ParseRange(strval(self::Cfg('WORKFORCE_RECEIVING_WINDOW', '07:00-11:00')), [7, 11]);
		$recvHours = array_values(array_filter($openHours, fn($h) => $h >= $ra && $h < $rz)) ?: array_slice($openHours, 0, 3);
		$earlyHours = array_slice($openHours, 0, 3);
		$salesShare = $profile['source'] !== 'none' ? $profile['sales'] : array_map(fn($h) => $open[$h] && count($openHours) ? 1 / count($openHours) : 0, range(0, 23));

		$byHourRole = [];
		for ($h = 0; $h < 24; $h++)
		{
			$byHourRole[$h] = array_fill_keys(array_keys(self::ROLES), 0.0);
			$byHourRole[$h]['ventas'] = $salesHours * $salesShare[$h];
		}
		$spread = function (float $hours, array $hoursList, string $role) use (&$byHourRole) {
			foreach ($hoursList as $h)
			{
				$byHourRole[$h][$role] += $hours / max(1, count($hoursList));
			}
		};
		foreach ($tasks as $t)
		{
			if ($t['counted'] <= 0)
			{
				continue;
			}
			if ($t['code'] === 'restock' && !empty($sc['advance_restock']))
			{
				$spread($t['counted'], $earlyHours, 'reposicion');
			}
			elseif ($t['code'] === 'restock')
			{
				for ($h = 0; $h < 24; $h++)
				{
					$byHourRole[$h]['reposicion'] += $t['counted'] * $salesShare[$h];
				}
			}
			elseif ($t['code'] === 'receiving')
			{
				$spread($t['counted'], $recvHours, 'operaciones');
			}
			elseif (in_array($t['code'], ['fefo', 'counts']))
			{
				$spread($t['counted'], $earlyHours, $t['role']);
			}
			else
			{
				$spread($t['counted'], $openHours, $t['role']);
			}
		}

		$minOpen = max(0, intval(self::Cfg('WORKFORCE_MIN_STAFF_OPEN', 1)));
		$hourly = [];
		for ($h = 0; $h < 24; $h++)
		{
			$work = array_sum($byHourRole[$h]);
			$min = max($cov['required'][$h], $open[$h] ? $minOpen : 0);
			$hourly[$h] = ['hour' => $h, 'open' => $open[$h], 'work_hours' => round($work, 2), 'by_role' => array_map(fn($v) => round($v, 2), $byHourRole[$h]),
				'min_staff' => $min, 'needed' => max($min, $work > 0 ? intval(ceil($work / $eff - 1e-9)) : 0), 'scheduled' => $cov['staff'][$h]];
		}

		// 5. Franjas: ⌈horas de la franja ÷ (duración × eficiencia)⌉; cada hora de la franja exige esa dotación
		$bands = [];
		$inBand = array_fill(0, 24, null);
		foreach (self::Bands() as $bi => [$a, $z])
		{
			$w = 0.0;
			$min = 0;
			$sched = PHP_INT_MAX;
			$schedSum = 0;
			$roleHours = array_fill_keys(array_keys(self::ROLES), 0.0);
			$active = 0; // horas de la franja con atención o trabajo: la franja dura lo que la tienda está abierta en ella
			for ($h = $a; $h < $z; $h++)
			{
				$w += $hourly[$h]['work_hours'];
				foreach ($byHourRole[$h] as $r => $v)
				{
					$roleHours[$r] += $v;
				}
				if ($hourly[$h]['open'] || $hourly[$h]['work_hours'] > 0 || $hourly[$h]['min_staff'] > 0)
				{
					$active++;
					$min = max($min, $hourly[$h]['min_staff']);
					$sched = min($sched, $hourly[$h]['scheduled']);
					$schedSum += $hourly[$h]['scheduled'];
					$inBand[$h] = $bi;
				}
			}
			$len = max(1, $active);
			$needed = max($min, $w > 0 ? intval(ceil($w / ($len * $eff) - 1e-9)) : 0);
			$roleStaff = [];
			foreach ($roleHours as $r => $v)
			{
				$roleStaff[$r] = ['hours' => round($v, 2), 'staff' => $v > 0 ? round($v / ($len * $eff), 2) : 0];
			}
			$bands[] = ['index' => $bi, 'from' => $a, 'to' => $z, 'label' => sprintf('%d:00–%d:00', $a, $z), 'work_hours' => round($w, 2), 'needed' => $needed,
				'scheduled' => $sched === PHP_INT_MAX ? 0 : $sched, 'scheduled_avg' => round($schedSum / $len, 1), 'diff' => ($sched === PHP_INT_MAX ? 0 : $sched) - $needed, 'roles' => $roleStaff,
				'open_hours' => $active, 'peak_hour_needed' => max(array_map(fn($h) => $hourly[$h]['needed'], range($a, $z - 1)))];
		}
		foreach ($hourly as $h => &$x)
		{
			if ($inBand[$h] !== null)
			{
				$x['needed'] = max($x['min_staff'], $bands[$inBand[$h]]['needed']);
			}
		}
		unset($x);

		// 6. Totales del día
		$schedHours = 0.0;
		$schedCost = 0.0;
		$costKnown = true;
		foreach ($dayShifts as $s)
		{
			$s->hours = WorkforceScheduleService::ShiftHours($s);
			$schedHours += $s->hours;
			$schedCost += $s->hours * floatval($s->hourly_cost ?? 0);
			$costKnown = $costKnown && $s->hourly_cost !== null;
		}
		$requiredHours = array_sum(array_column($hourly, 'needed'));
		$covered = array_sum(array_map(fn($x) => min($x['needed'], $x['scheduled']), $hourly));
		$peak = max(array_column($hourly, 'needed'));
		$stdShift = max(4, floatval(self::Cfg('WORKFORCE_STANDARD_SHIFT_HOURS', 8)));
		$recommended = max($peak, intval(ceil($requiredHours / $stdShift - 1e-9)));
		$scheduledStaff = count(array_unique(array_map(fn($s) => intval($s->employee_id), $dayShifts)));
		$avgCost = floatval($this->Q('SELECT AVG(hourly_cost) FROM employees WHERE branch_id = :b AND active = 1 AND hourly_cost IS NOT NULL', [':b' => $branchId])->fetchColumn());
		$budget = BudgetService::GetInstance()->GetTargets($branchId, substr($date, 0, 7));
		$dailyBudget = isset($budget['labor_budget_amount']) && $budget['labor_budget_amount'] !== null ? round($budget['labor_budget_amount'] / intval(date('t', strtotime($date))), 2) : null;

		$dept = [];
		foreach ($this->DepartmentShares($branchId, $histTo) as $d)
		{
			$dept[] = ['department' => $d['department'], 'share_pct' => round($d['share'] * 100, 1), 'sales_hours' => round($salesHours * $d['share'], 2)];
		}

		$result = [
			'branch' => $branch,
			'date' => $date,
			'weekday' => intval(date('N', strtotime($date))),
			'history_to' => $histTo,
			'has_sales_history' => $ps['value'] !== null,
			'sales' => [
				'projected' => $projSales, 'projected_tickets' => $projTickets, 'projected_units' => $ps['value'] !== null ? $projUnits : null,
				'same_weekday_avg' => $ps['same_weekday_avg'] !== null ? round($ps['same_weekday_avg'], 2) : null,
				'same_weekday' => array_map(fn($v) => round($v, 2), $ps['same_weekday']),
				'avg_daily' => $windows, 'method' => $ps['method'], 'weekday_index' => $ps['weekday_index'] ?? null,
				'factors' => ['seasonality' => $season, 'events' => ['factor' => round($eventFactor, 3), 'items' => $events], 'learned' => $learn, 'total' => round($factor, 3)],
				'hourly_profile_source' => $profile['source']
			],
			'productivity' => $prod,
			'tasks' => $tasks,
			'hours' => ['sales' => round($salesHours, 2), 'tasks' => round($taskHours, 2), 'workload' => round($salesHours + $taskHours, 2), 'required' => $requiredHours, 'scheduled' => round($schedHours, 2), 'effective_ratio' => $eff],
			'staff' => ['recommended' => $recommended, 'scheduled' => $scheduledStaff, 'extra' => $recommended - $scheduledStaff, 'peak_concurrent' => $peak],
			'coverage_pct' => $requiredHours > 0 ? round($covered / $requiredHours * 100, 1) : null,
			'hourly' => array_values($hourly),
			'bands' => $bands,
			'departments' => $dept,
			'shifts' => array_map(fn($s) => ['id' => intval($s->id), 'employee_id' => intval($s->employee_id), 'employee_name' => $s->employee_name, 'start_time' => $s->start_time, 'end_time' => $s->end_time, 'hours' => $s->hours, 'status' => $s->status, 'version' => intval($s->version)], $dayShifts),
			'cost' => [
				'scheduled' => round($schedCost, 2), 'scheduled_complete' => $costKnown, 'recommended_estimate' => $avgCost > 0 ? round($requiredHours * $avgCost, 2) : null,
				'daily_budget' => $dailyBudget, 'pct_projected_sales' => $projSales > 0 ? round($schedCost / $projSales * 100, 1) : null
			],
			'payroll_connector' => self::PayrollConnectorStatus(),
		];
		if ($sc)
		{
			$result['scenario'] = ['params' => $sc, 'shift_changes' => $scenarioChanges];
		}
		$result['alerts'] = $this->Alerts($result);
		$result['answer'] = $explain ? $this->Answer($result) : null;

		if ($persist && $date >= $today)
		{
			$this->SaveSnapshot($result, $date === $today ? count(MrpService::GetInstance()->GetForecastAccuracy($branchId, 1)['stockouts'] ?? []) : null);
		}
		return $result;
	}

	private function SaveSnapshot(array $f, ?int $stockouts): void
	{
		$this->Q("INSERT INTO labor_forecast_snapshots (branch_id, forecast_date, projected_sales, projected_tickets, projected_units, sales_hours, task_hours, required_hours, recommended_staff, scheduled_staff, scheduled_hours, productivity, productivity_source, stockouts, detail_json, updated_timestamp)
			VALUES (:b, :d, :ps, :pt, :pu, :sh, :th, :rh, :rs, :ss, :sch, :p, :psrc, :so, :j, datetime('now', 'localtime'))
			ON CONFLICT (branch_id, forecast_date) DO UPDATE SET projected_sales = excluded.projected_sales, projected_tickets = excluded.projected_tickets, projected_units = excluded.projected_units,
				sales_hours = excluded.sales_hours, task_hours = excluded.task_hours, required_hours = excluded.required_hours, recommended_staff = excluded.recommended_staff,
				scheduled_staff = excluded.scheduled_staff, scheduled_hours = excluded.scheduled_hours, productivity = excluded.productivity, productivity_source = excluded.productivity_source,
				stockouts = IFNULL(excluded.stockouts, labor_forecast_snapshots.stockouts), detail_json = excluded.detail_json, updated_timestamp = excluded.updated_timestamp", [
			':b' => $f['branch']['id'], ':d' => $f['date'], ':ps' => $f['sales']['projected'], ':pt' => $f['sales']['projected_tickets'], ':pu' => $f['sales']['projected_units'],
			':sh' => $f['hours']['sales'], ':th' => $f['hours']['tasks'], ':rh' => $f['hours']['required'], ':rs' => $f['staff']['recommended'], ':ss' => $f['staff']['scheduled'],
			':sch' => $f['hours']['scheduled'], ':p' => $f['productivity']['value'], ':psrc' => $f['productivity']['source'], ':so' => $stockouts,
			':j' => json_encode(['bands' => array_map(fn($b) => ['label' => $b['label'], 'needed' => $b['needed'], 'scheduled' => $b['scheduled']], $f['bands']), 'tasks' => array_map(fn($t) => ['code' => $t['code'], 'hours' => $t['hours']], $f['tasks'])], JSON_UNESCAPED_UNICODE)
		]);
	}

	const SCENARIO_LIMITS = [
		'sales_pct' => [-90, 300], 'productivity_pct' => [-80, 200], 'extra_pallets' => [0, 500], 'absent_staff' => [0, 100], 'cut_hours' => [0, 200]
	];

	// Normaliza los parámetros del simulador; vacío = sin escenario
	public static function ScenarioParams(array $in): array
	{
		$out = [];
		foreach (self::SCENARIO_LIMITS as $k => [$lo, $hi])
		{
			if (!isset($in[$k]) || $in[$k] === '' || $in[$k] === null)
			{
				continue;
			}
			if (!is_numeric($in[$k]))
			{
				throw new \Exception("Parámetro de escenario inválido: $k");
			}
			$v = in_array($k, ['sales_pct', 'productivity_pct']) ? round(floatval($in[$k]), 1) : intval($in[$k]);
			if ($v < $lo || $v > $hi)
			{
				throw new \Exception("$k fuera de rango ($lo a $hi)");
			}
			if ($v != 0)
			{
				$out[$k] = $v;
			}
		}
		if (!empty($in['advance_restock']) && $in['advance_restock'] !== 'false')
		{
			$out['advance_restock'] = true;
		}
		return $out;
	}

	// Ausencias: quita los N turnos más largos (peor caso). Recorte: acorta 1 h por vez el turno que termina más tarde (mín. 1 h).
	// Trabaja sobre copias: nada se escribe en la BD.
	private static function ApplyShiftScenario(array $shifts, array $sc): array
	{
		$shifts = array_map(fn($s) => clone $s, $shifts);
		$changes = [];
		$n = intval($sc['absent_staff'] ?? 0);
		if ($n > 0)
		{
			usort($shifts, fn($a, $b) => (WorkforceScheduleService::ShiftHours($b) <=> WorkforceScheduleService::ShiftHours($a)) ?: (intval($a->id) <=> intval($b->id)));
			foreach (array_splice($shifts, 0, min($n, count($shifts))) as $s)
			{
				$changes[] = ['type' => 'absent', 'shift_id' => intval($s->id), 'employee_name' => $s->employee_name ?? null, 'hours' => WorkforceScheduleService::ShiftHours($s)];
			}
			if ($n > count($changes))
			{
				$changes[] = ['type' => 'absent_unmatched', 'count' => $n - count($changes)];
			}
		}
		$cut = intval($sc['cut_hours'] ?? 0);
		$done = 0;
		while ($done < $cut)
		{
			$best = null;
			foreach ($shifts as $i => $s)
			{
				[$a, $z] = WorkforceScheduleService::Span($s->shift_date, $s->start_time, $s->end_time);
				if ($z - $a >= 2 * 3600 && ($best === null || $z > $best[1]))
				{
					$best = [$i, $z];
				}
			}
			if ($best === null)
			{
				break;
			}
			$shifts[$best[0]]->end_time = date('H:i', $best[1] - 3600);
			$done++;
		}
		if ($cut > 0)
		{
			$changes[] = ['type' => 'cut', 'hours' => $done, 'unmatched' => $cut - $done];
		}
		usort($shifts, fn($a, $b) => strcmp($a->start_time, $b->start_time));
		return [$shifts, $changes];
	}

	private static function ScenarioSummary(array $f): array
	{
		$short = [];
		$gap = 0;
		foreach ($f['hourly'] as $h)
		{
			if ($h['needed'] > $h['scheduled'])
			{
				$gap += $h['needed'] - $h['scheduled'];
				$short[] = $h['hour'];
			}
		}
		$cov = $f['coverage_pct'];
		return [
			'projected_sales' => $f['sales']['projected'], 'workload_hours' => $f['hours']['workload'], 'task_hours' => $f['hours']['tasks'],
			'required_hours' => $f['hours']['required'], 'scheduled_hours' => $f['hours']['scheduled'],
			'recommended_staff' => $f['staff']['recommended'], 'scheduled_staff' => $f['staff']['scheduled'], 'coverage_pct' => $cov,
			'gap_staff_hours' => $gap, 'short_hours' => $short, 'scheduled_cost' => $f['cost']['scheduled'], 'recommended_cost' => $f['cost']['recommended_estimate'],
			'risk' => $cov === null ? 'sin_datos' : ($cov >= 95 ? 'bajo' : ($cov >= 85 ? 'medio' : 'alto')),
			'hourly' => array_values(array_map(fn($h) => ['hour' => $h['hour'], 'needed' => $h['needed'], 'scheduled' => $h['scheduled']], array_filter($f['hourly'], fn($h) => $h['open'] || $h['needed'] > 0 || $h['scheduled'] > 0)))
		];
	}

	// Simula sin escribir: base vs. escenario sobre los mismos datos
	public function Simulate(int $branchId, string $date, array $params): array
	{
		$sc = self::ScenarioParams($params);
		if (!$sc)
		{
			throw new \Exception('Indique al menos un cambio para simular');
		}
		$base = self::ScenarioSummary($this->Forecast($branchId, $date, false, false));
		$f = $this->Forecast($branchId, $date, false, false, $sc);
		$sim = self::ScenarioSummary($f);
		$delta = [];
		foreach (['projected_sales', 'workload_hours', 'required_hours', 'scheduled_hours', 'recommended_staff', 'coverage_pct', 'gap_staff_hours', 'scheduled_cost', 'recommended_cost'] as $k)
		{
			$delta[$k] = $base[$k] !== null && $sim[$k] !== null ? round($sim[$k] - $base[$k], 2) : null;
		}
		return ['branch_id' => $branchId, 'date' => $date, 'params' => $sc, 'shift_changes' => $f['scenario']['shift_changes'], 'base' => $base, 'scenario' => $sim, 'delta' => $delta,
			'has_sales_history' => $f['has_sales_history'], 'note' => 'Simulación: no modifica turnos, horarios publicados ni el aprendizaje del pronóstico.'];
	}

	public function SaveScenario(int $branchId, string $date, string $name, array $params, int $userId): int
	{
		$name = trim($name);
		if ($name === '' || mb_strlen($name) > 120)
		{
			throw new \Exception('Nombre del escenario requerido (máx. 120)');
		}
		$r = $this->Simulate($branchId, $date, $params);
		$this->Q('INSERT INTO labor_scenarios (branch_id, scenario_date, name, params_json, result_json, created_by) VALUES (:b, :d, :n, :p, :r, :u)', [
			':b' => $branchId, ':d' => $date, ':n' => $name, ':p' => json_encode($r['params']),
			':r' => json_encode(['base' => $r['base'], 'scenario' => $r['scenario'], 'delta' => $r['delta'], 'shift_changes' => $r['shift_changes']], JSON_UNESCAPED_UNICODE), ':u' => $userId
		]);
		return intval($this->Q('SELECT last_insert_rowid()')->fetchColumn());
	}

	public function GetScenarios(int $branchId): array
	{
		$rows = $this->Q('SELECT * FROM labor_scenarios WHERE branch_id = :b ORDER BY id DESC LIMIT 50', [':b' => $branchId])->fetchAll(\PDO::FETCH_ASSOC);
		return array_map(fn($r) => ['id' => intval($r['id']), 'branch_id' => intval($r['branch_id']), 'date' => $r['scenario_date'], 'name' => $r['name'], 'params' => json_decode($r['params_json'], true),
			'result' => json_decode($r['result_json'], true), 'created_by' => $r['created_by'] !== null ? intval($r['created_by']) : null, 'created' => $r['row_created_timestamp']], $rows);
	}

	public function GetScenarioBranch(int $id): int
	{
		$b = $this->Q('SELECT branch_id FROM labor_scenarios WHERE id = :id', [':id' => $id])->fetchColumn();
		if ($b === false)
		{
			throw new \Exception('Escenario no encontrado');
		}
		return intval($b);
	}

	public function DeleteScenario(int $id): void
	{
		$this->Q('DELETE FROM labor_scenarios WHERE id = :id', [':id' => $id]);
	}

	public static function PayrollConnectorStatus(): array
	{
		$provider = strtolower(trim(strval(self::Cfg('WORKFORCE_PAYROLL_CONNECTOR', ''))));
		$url = trim(strval(self::Cfg('WORKFORCE_PAYROLL_API_URL', '')));
		$key = trim(strval(self::Cfg('WORKFORCE_PAYROLL_API_KEY', '')));
		if ($provider === '')
		{
			return ['provider' => null, 'enabled' => false, 'message' => 'Sin conector de nómina (PAYCOM u otro) configurado: los horarios se gestionan solo en DAIRY.'];
		}
		if ($url === '' || $key === '')
		{
			return ['provider' => $provider, 'enabled' => false, 'message' => "Conector «{$provider}» declarado pero sin URL/credenciales autorizadas: inactivo."];
		}
		return ['provider' => $provider, 'enabled' => false, 'message' => "Credenciales de «{$provider}» presentes; la sincronización requiere validar la API del proveedor y permisos de la cuenta antes de activarse."];
	}

	/* ------------------------------------------------------------------ Alertas y respuesta */

	private function Alerts(array $f): array
	{
		$alerts = [];
		$dayNames = [1 => 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado', 'domingo'];
		$when = $f['date'] === date('Y-m-d') ? 'Hoy' : ($f['date'] === self::D(date('Y-m-d'), 1) ? 'Mañana' : 'El ' . date('d/m', strtotime($f['date'])));
		$s = $f['sales'];
		if ($s['projected'] === null)
		{
			$alerts[] = ['level' => 'info', 'code' => 'no_history', 'title' => 'Sin ventas históricas en la sucursal', 'cause' => 'No hay ventas completadas antes de esta fecha; la dotación se calcula solo con tareas y mínimos de cobertura.', 'action' => ['type' => 'link', 'label' => 'Abrir POS', 'href' => 'pos']];
		}
		elseif ($s['same_weekday_avg'] > 0)
		{
			$pct = round(($s['projected'] / $s['same_weekday_avg'] - 1) * 100);
			if (abs($pct) >= 10)
			{
				$causes = [];
				if ($s['factors']['events']['items'])
				{
					$causes[] = 'eventos: ' . implode(', ', array_column($s['factors']['events']['items'], 'name'));
				}
				if ($s['factors']['seasonality']['applied'])
				{
					$causes[] = 'estacionalidad ×' . $s['factors']['seasonality']['factor'];
				}
				if ($s['factors']['learned']['applied'])
				{
					$causes[] = 'corrección aprendida ×' . $s['factors']['learned']['factor'];
				}
				$causes[] = 'tendencia reciente (últimas 4 semanas pesan más)';
				$alerts[] = ['level' => $pct > 0 ? 'warning' : 'info', 'code' => 'sales_vs_weekday', 'title' => "$when se proyecta un " . abs($pct) . ' % ' . ($pct > 0 ? 'más' : 'menos') . ' de ventas que un ' . $dayNames[$f['weekday']] . ' promedio',
					'cause' => 'Proyección $' . number_format($s['projected'], 2) . ' vs. promedio de ' . count($s['same_weekday']) . ' ' . $dayNames[$f['weekday']] . ' $' . number_format($s['same_weekday_avg'], 2) . ' · ' . implode('; ', $causes) . '.', 'action' => ['type' => 'generate', 'label' => 'Ajustar horario']];
			}
		}
		foreach ($f['bands'] as $b)
		{
			if ($b['diff'] < 0)
			{
				$n = -$b['diff'];
				$alerts[] = ['level' => 'danger', 'code' => 'band_gap', 'title' => "Falta" . ($n > 1 ? 'n' : '') . " $n empleado" . ($n > 1 ? 's' : '') . " entre las {$b['label']}",
					'cause' => "Se necesitan {$b['needed']} personas simultáneas ({$b['work_hours']} h de trabajo en la franja) y hay {$b['scheduled']} programadas durante toda la franja.", 'action' => ['type' => 'band', 'label' => 'Asignar personal', 'band' => $b['index']]];
			}
		}
		// Recepción en hora pico
		$recv = array_values(array_filter($f['tasks'], fn($t) => $t['code'] === 'receiving' && $t['hours'] > 0));
		if ($recv && $f['sales']['hourly_profile_source'] !== 'none')
		{
			[$ra, $rz] = self::ParseRange(strval(self::Cfg('WORKFORCE_RECEIVING_WINDOW', '07:00-11:00')), [7, 11]);
			$byWork = $f['hourly'];
			usort($byWork, fn($x, $y) => $y['by_role']['ventas'] <=> $x['by_role']['ventas']);
			$peaks = array_map(fn($x) => $x['hour'], array_slice(array_filter($byWork, fn($x) => $x['by_role']['ventas'] > 0), 0, 3));
			$overlap = array_values(array_filter($peaks, fn($h) => $h >= $ra && $h < $rz));
			if ($overlap)
			{
				$alerts[] = ['level' => 'warning', 'code' => 'receiving_peak', 'title' => 'La recepción de mercancía coincide con el horario de mayor demanda',
					'cause' => $recv[0]['qty'] . " esperadas en la ventana {$ra}:00–{$rz}:00, que incluye la(s) hora(s) pico " . implode(', ', array_map(fn($h) => "$h:00", $overlap)) . '. Pida al proveedor otra hora o refuerce esa franja.', 'action' => ['type' => 'link', 'label' => 'Ver órdenes', 'href' => 'procurement']];
			}
		}
		// Redistribución sin contratar
		$excess = $deficit = 0;
		foreach ($f['hourly'] as $x)
		{
			if ($x['open'] || $x['scheduled'] > 0)
			{
				$excess += max(0, $x['scheduled'] - $x['needed']);
				$deficit += max(0, $x['needed'] - $x['scheduled']);
			}
		}
		if ($excess > 0 && $deficit > 0)
		{
			$alerts[] = ['level' => 'info', 'code' => 'redistribute', 'title' => 'Puedes redistribuir ' . min($excess, $deficit) . ' horas laborales sin contratar personal adicional',
				'cause' => "$excess persona-horas por encima de lo necesario en unas horas y $deficit persona-horas faltantes en otras.", 'action' => ['type' => 'generate', 'label' => 'Simular reorganización']];
		}
		// FEFO sin cobertura
		$fefo = array_values(array_filter($f['tasks'], fn($t) => $t['code'] === 'fefo' && $t['hours'] > 0));
		if ($fefo)
		{
			$early = array_slice(array_values(array_filter($f['hourly'], fn($x) => $x['open'])), 0, 3);
			$short = array_filter($early, fn($x) => $x['scheduled'] < $x['needed']);
			if ($short)
			{
				$alerts[] = ['level' => 'danger', 'code' => 'fefo_risk', 'title' => 'Existe riesgo de incumplimiento FEFO por falta de cobertura',
					'cause' => $fefo[0]['qty'] . ' (' . $fefo[0]['hours'] . ' h de rotación) y faltan personas en las primeras horas de apertura, cuando se programa la revisión.', 'action' => ['type' => 'link', 'label' => 'Ver vencimientos', 'href' => 'quality-expiry']];
			}
		}
		if ($f['cost']['daily_budget'] !== null && $f['cost']['scheduled'] > $f['cost']['daily_budget'])
		{
			$alerts[] = ['level' => 'warning', 'code' => 'over_budget', 'title' => 'Costo laboral programado sobre el presupuesto diario',
				'cause' => 'Programado $' . number_format($f['cost']['scheduled'], 2) . ' vs. presupuesto prorrateado $' . number_format($f['cost']['daily_budget'], 2) . '.', 'action' => ['type' => 'link', 'label' => 'Ver presupuesto', 'href' => 'cost-centers']];
		}
		return $alerts;
	}

	private function Answer(array $f, ?array $plan = null): string
	{
		$when = $f['date'] === date('Y-m-d') ? 'hoy' : ($f['date'] === self::D(date('Y-m-d'), 1) ? 'mañana' : 'el ' . date('d/m/Y', strtotime($f['date'])));
		$basis = $f['has_sales_history'] ? 'Según las ventas históricas, las operaciones programadas y los empleados disponibles' : 'Sin ventas históricas, solo con las tareas programadas y los mínimos de cobertura';
		$r = $f['staff']['recommended'];
		$s = $f['staff']['scheduled'];
		$text = "$basis, $when necesitas $r persona" . ($r === 1 ? '' : 's') . ". Actualmente tienes $s programada" . ($s === 1 ? '' : 's') . '.';
		$gaps = array_values(array_filter($f['bands'], fn($b) => $b['diff'] < 0));
		if (!$gaps)
		{
			return $text . ($r < $s ? ' La cobertura alcanza; hay ' . ($s - $r) . ' persona(s) por encima de lo recomendado.' : ' La cobertura por franja es suficiente.');
		}
		usort($gaps, fn($a, $b) => $a['diff'] <=> $b['diff']);
		$worst = $gaps[0];
		$text .= ' Te falta' . (-$worst['diff'] > 1 ? 'n ' : ' ') . (-$worst['diff']) . " entre las {$worst['label']}" . ($f['bands'] && $worst['work_hours'] == max(array_column($f['bands'], 'work_hours')) ? ', las horas de mayor demanda' : '');
		$plan = $plan ?? $this->Plan($f, false);
		$types = array_count_values(array_column($plan['proposals'], 'type'));
		if (!$plan['proposals'])
		{
			return $text . '; no hay empleados disponibles: solicita cobertura.';
		}
		$how = [];
		if (!empty($types['move']))
		{
			$how[] = 'reorganizando turnos';
		}
		if (!empty($types['extend']))
		{
			$how[] = 'ampliando ' . $types['extend'] . ' turno(s)';
		}
		if (!empty($types['add']))
		{
			$how[] = 'agregando ' . $types['add'] . ' turno(s) con personal disponible';
		}
		$tail = $how ? ' y puedes cubrirl' . (-$worst['diff'] > 1 ? 'os' : 'o') . ' ' . implode(', ', $how) : '';
		return $text . $tail . (!empty($types['request']) ? '; para el resto, solicita cobertura.' : '.');
	}

	/* ------------------------------------------------------------------ Disponibles por franja */

	public function BandCandidates(int $branchId, string $date, int $bandIndex): array
	{
		$bands = self::Bands();
		if (!isset($bands[$bandIndex]))
		{
			throw new \Exception('Franja inválida');
		}
		[$a, $z] = $bands[$bandIndex];
		$ws = $this->Ws();
		$monday = date('Y-m-d', strtotime('monday this week', strtotime($date)));
		$available = $extendable = [];
		foreach ($ws->GetEmployees($branchId) as $e)
		{
			$shifts = $this->Q("SELECT * FROM employee_shifts WHERE employee_id = :e AND status != 'cancelled' AND shift_date = :d", [':e' => $e->id, ':d' => $date])->fetchAll(\PDO::FETCH_OBJ);
			$week = 0.0;
			foreach ($this->Q("SELECT * FROM employee_shifts WHERE employee_id = :e AND status != 'cancelled' AND shift_date BETWEEN :m AND date(:m, '+6 day')", [':e' => $e->id, ':m' => $monday])->fetchAll(\PDO::FETCH_OBJ) as $w)
			{
				$week += WorkforceScheduleService::ShiftHours($w);
			}
			$info = ['employee_id' => intval($e->id), 'name' => $e->name, 'position' => $e->position, 'department' => $e->department, 'week_hours' => round($week, 1), 'contract_hours' => $ws->ContractHours($e), 'hourly_cost' => $e->hourly_cost !== null ? floatval($e->hourly_cost) : null];
			foreach ($shifts as $s)
			{
				// Ampliar: turno que termina al inicio de la franja o empieza al final
				$end = intval(substr($s->end_time, 0, 2));
				$start = intval(substr($s->start_time, 0, 2));
				$opt = null;
				if ($s->end_time === self::Hm($a) && $s->end_time > $s->start_time)
				{
					$opt = ['start_time' => $s->start_time, 'end_time' => self::Hm($z)];
				}
				elseif ($s->start_time === self::Hm($z))
				{
					$opt = ['start_time' => self::Hm($a), 'end_time' => $s->end_time];
				}
				if ($opt)
				{
					$issues = $ws->CheckConflicts(intval($e->id), $date, $opt['start_time'], $opt['end_time'], intval($s->break_minutes), intval($s->id), $s->role);
					if (!array_filter($issues, fn($i) => $i['level'] === 'error'))
					{
						$extendable[] = $info + ['shift_id' => intval($s->id), 'version' => intval($s->version), 'current' => $s->start_time . '–' . $s->end_time, 'proposed' => $opt, 'break_minutes' => intval($s->break_minutes), 'warnings' => array_values($issues)];
					}
				}
			}
			$issues = $ws->CheckConflicts(intval($e->id), $date, self::Hm($a), self::Hm($z), 0, 0, null);
			if (!array_filter($issues, fn($i) => $i['level'] === 'error'))
			{
				$available[] = $info + ['proposed' => ['start_time' => self::Hm($a), 'end_time' => self::Hm($z)], 'warnings' => array_values($issues), 'fits' => !array_filter($issues, fn($i) => in_array($i['code'], self::BLOCKING_WARNINGS) || $i['code'] === 'overtime')];
			}
		}
		usort($available, fn($x, $y) => [!$x['fits'], $x['week_hours']] <=> [!$y['fits'], $y['week_hours']]);
		$open = $this->Q("SELECT * FROM labor_coverage_requests WHERE branch_id = :b AND request_date = :d AND status = 'open' ORDER BY id", [':b' => $branchId, ':d' => $date])->fetchAll(\PDO::FETCH_ASSOC);
		return ['band' => ['index' => $bandIndex, 'from' => $a, 'to' => $z, 'label' => sprintf('%d:00–%d:00', $a, $z)], 'available' => $available, 'extendable' => $extendable, 'open_requests' => $open];
	}

	/* ------------------------------------------------------------------ Recomendación (simulación) y aplicación */

	// Plantilla de dotación por hora a partir de turnos simulados (misma regla que CoverageForDay: hora completa cubierta)
	private static function StaffFromSim(string $date, array $sim): array
	{
		$staff = array_fill(0, 24, 0);
		foreach ($sim as $s)
		{
			[$ss, $se] = WorkforceScheduleService::Span($s['shift_date'], $s['start_time'], $s['end_time']);
			for ($h = 0; $h < 24; $h++)
			{
				$hs = strtotime("$date " . sprintf('%02d:00', $h));
				if ($ss <= $hs && $se >= $hs + 3600)
				{
					$staff[$h]++;
				}
			}
		}
		return $staff;
	}

	private static function Metrics(array $needed, array $staff, array $sim, array $costs): array
	{
		$req = array_sum($needed);
		$cov = 0;
		$gapHours = 0;
		for ($h = 0; $h < 24; $h++)
		{
			$cov += min($needed[$h], $staff[$h]);
			$gapHours += max(0, $needed[$h] - $staff[$h]);
		}
		$hours = 0.0;
		$cost = 0.0;
		foreach ($sim as $s)
		{
			$hh = WorkforceScheduleService::ShiftHours((object) $s);
			$hours += $hh;
			$cost += $hh * floatval($costs[$s['employee_id']] ?? 0);
		}
		return ['coverage_pct' => $req > 0 ? round($cov / $req * 100, 1) : null, 'gap_staff_hours' => $gapHours, 'scheduled_hours' => round($hours, 2), 'cost' => round($cost, 2)];
	}

	/**
	 * Propuesta para cubrir faltantes del día, en este orden: 1) mover turnos que están completos en horas con exceso,
	 * 2) ampliar turnos contiguos al faltante, 3) agregar turnos con personal disponible, 4) solicitar cobertura.
	 * No escribe nada. Horas extra solo si $allowOvertime (quien aplica debe tener WORKFORCE_OVERTIME_APPROVE).
	 */
	public function Plan(array $f, bool $allowOvertime): array
	{
		$ws = $this->Ws();
		$date = $f['date'];
		$branchId = intval($f['branch']['id']);
		$needed = array_column($f['hourly'], 'needed');
		$open = array_column($f['hourly'], 'open');
		$openHours = array_keys(array_filter($open));
		$sim = [];
		foreach ($this->Q("SELECT * FROM employee_shifts WHERE branch_id = :b AND status != 'cancelled' AND shift_date BETWEEN date(:d, '-1 day') AND :d", [':b' => $branchId, ':d' => $date])->fetchAll(\PDO::FETCH_ASSOC) as $s)
		{
			$sim[intval($s['id'])] = $s;
		}
		$costs = [];
		$employees = $ws->GetEmployees($branchId);
		foreach ($employees as $e)
		{
			$costs[$e->id] = $e->hourly_cost;
		}
		$before = self::Metrics($needed, self::StaffFromSim($date, $sim), array_filter($sim, fn($s) => $s['shift_date'] === $date), $costs);
		$proposals = [];
		$requiresOvertime = [];
		$maxDaily = floatval(self::Cfg('WORKFORCE_MAX_DAILY_HOURS', 10)) ?: 12;
		$minShift = max(2, intval(self::Cfg('WORKFORCE_MIN_SHIFT_HOURS', 4)));
		$stdShift = max($minShift, intval(self::Cfg('WORKFORCE_STANDARD_SHIFT_HOURS', 8)));
		$started = fn($s) => $date < date('Y-m-d') || ($date === date('Y-m-d') && strtotime("$date {$s['start_time']}") <= time()) || $this->Q('SELECT 1 FROM employee_attendance WHERE shift_id = :id', [':id' => $s['id']])->fetchColumn();
		$acceptable = function (array $issues, int $employeeId) use ($allowOvertime, &$requiresOvertime) {
			if (array_filter($issues, fn($i) => $i['level'] === 'error' || in_array($i['code'], self::BLOCKING_WARNINGS)))
			{
				return false;
			}
			if (array_filter($issues, fn($i) => $i['code'] === 'overtime'))
			{
				$requiresOvertime[$employeeId] = true;
				return $allowOvertime;
			}
			return true;
		};
		$deficit = function () use (&$sim, $date, $needed) {
			$st = self::StaffFromSim($date, $sim);
			return array_map(fn($h) => $needed[$h] - $st[$h], range(0, 23));
		};
		$name = [];
		foreach ($employees as $e)
		{
			$name[$e->id] = $e->name;
		}
		$hourOf = fn(string $t) => intval(substr($t, 0, 2)) + (substr($t, 3, 2) !== '00' ? 1 : 0);

		// 1) Reorganizar: turnos del día cuyas horas están todas en exceso se mueven a donde falta gente
		foreach ($sim as $id => $s)
		{
			$d = $deficit();
			if ($s['shift_date'] !== $date || $s['end_time'] <= $s['start_time'] || $started($s) || max($d) <= 0)
			{
				continue;
			}
			$sh = intval(substr($s['start_time'], 0, 2));
			$eh = $hourOf($s['end_time']);
			$len = $eh - $sh;
			if ($len <= 0 || array_filter(range($sh, $eh - 1), fn($h) => $d[$h] > -1))
			{
				continue; // alguna hora del turno no tiene exceso: moverlo crearía otro faltante
			}
			$best = null;
			foreach ($openHours as $t)
			{
				if ($t + $len > 24 || $t === $sh)
				{
					continue;
				}
				$gain = 0;
				for ($h = $t; $h < $t + $len; $h++)
				{
					$gain += ($h >= $sh && $h < $eh) ? 0 : max(0, $d[$h]);
				}
				if ($gain > 0 && (!$best || $gain > $best['gain']))
				{
					$best = ['start' => $t, 'gain' => $gain];
				}
			}
			if (!$best)
			{
				continue;
			}
			$new = ['start_time' => self::Hm($best['start']), 'end_time' => self::Hm($best['start'] + $len)];
			$issues = $ws->CheckConflicts(intval($s['employee_id']), $date, $new['start_time'], $new['end_time'], intval($s['break_minutes']), $id, $s['role']);
			if ($acceptable($issues, intval($s['employee_id'])))
			{
				$proposals[] = ['type' => 'move', 'shift_id' => $id, 'version' => intval($s['version']), 'employee_id' => intval($s['employee_id']), 'employee_name' => $name[$s['employee_id']] ?? '#' . $s['employee_id'],
					'from' => $s['start_time'] . '–' . $s['end_time'], 'start_time' => $new['start_time'], 'end_time' => $new['end_time'], 'break_minutes' => intval($s['break_minutes']),
					'covers_hours' => $best['gain'], 'warnings' => array_values(array_filter($issues, fn($i) => $i['level'] === 'warning')), 'reason' => 'Reorganizar: el turno estaba en horas con exceso de personal'];
				$sim[$id]['start_time'] = $new['start_time'];
				$sim[$id]['end_time'] = $new['end_time'];
			}
		}

		// 2) Ampliar turnos contiguos al faltante (sin pasar el límite diario)
		foreach ($sim as $id => $s)
		{
			$d = $deficit();
			if ($s['shift_date'] !== $date || $s['end_time'] <= $s['start_time'] || max($d) <= 0 || $date < date('Y-m-d'))
			{
				continue;
			}
			$sh = intval(substr($s['start_time'], 0, 2));
			$eh = $hourOf($s['end_time']);
			$hours = WorkforceScheduleService::ShiftHours((object) $s);
			$room = intval(floor($maxDaily - $hours));
			$newS = $sh;
			$newE = $eh;
			while ($room > 0 && $newE < 24 && $d[$newE] > 0 && $open[$newE])
			{
				$newE++;
				$room--;
			}
			while ($room > 0 && $newS > 0 && $d[$newS - 1] > 0 && $open[$newS - 1] && !$started($s))
			{
				$newS--;
				$room--;
			}
			if ($newS === $sh && $newE === $eh)
			{
				continue;
			}
			$new = ['start_time' => $newS === $sh ? $s['start_time'] : self::Hm($newS), 'end_time' => $newE === $eh ? $s['end_time'] : self::Hm($newE)];
			$issues = $ws->CheckConflicts(intval($s['employee_id']), $date, $new['start_time'], $new['end_time'], intval($s['break_minutes']), $id, $s['role']);
			if ($acceptable($issues, intval($s['employee_id'])))
			{
				$proposals[] = ['type' => 'extend', 'shift_id' => $id, 'version' => intval($s['version']), 'employee_id' => intval($s['employee_id']), 'employee_name' => $name[$s['employee_id']] ?? '#' . $s['employee_id'],
					'from' => $s['start_time'] . '–' . $s['end_time'], 'start_time' => $new['start_time'], 'end_time' => $new['end_time'], 'break_minutes' => intval($s['break_minutes']),
					'covers_hours' => ($sh - $newS) + ($newE - $eh), 'warnings' => array_values(array_filter($issues, fn($i) => $i['level'] === 'warning')), 'reason' => 'Ampliar turno contiguo a la franja con faltante'];
				$sim[$id]['start_time'] = $new['start_time'];
				$sim[$id]['end_time'] = $new['end_time'];
			}
		}

		// 3) Agregar turnos con personal disponible; 4) lo que quede: solicitar cobertura
		$guard = 0;
		$handled = array_fill(0, 24, 0);
		while ($guard++ < 40 && $date >= date('Y-m-d'))
		{
			$d = $deficit();
			$run = null;
			for ($h = 0; $h < 24; $h++)
			{
				if ($d[$h] - $handled[$h] > 0)
				{
					$a = $h;
					while ($h < 24 && $d[$h] - $handled[$h] > 0)
					{
						$h++;
					}
					if (!$run || $h - $a > $run[1] - $run[0])
					{
						$run = [$a, $h];
					}
				}
			}
			if (!$run)
			{
				break;
			}
			[$a, $z] = $run;
			$len = max($minShift, min($stdShift, $z - $a));
			$start = $a;
			$end = $start + $len;
			$lastOpen = $openHours ? max($openHours) + 1 : 24;
			if ($end > $lastOpen)
			{
				$start = max($openHours ? min($openHours) : 0, $lastOpen - $len);
				$end = $start + $len;
			}
			$break = $len >= 6 ? 30 : 0;
			$placed = false;
			$cands = array_filter($employees, fn($e) => !array_filter($sim, fn($s) => intval($s['employee_id']) === intval($e->id) && $s['shift_date'] === $date));
			usort($cands, fn($x, $y) => [floatval($x->hourly_cost ?? 0)] <=> [floatval($y->hourly_cost ?? 0)]);
			foreach ($cands as $e)
			{
				$issues = $ws->CheckConflicts(intval($e->id), $date, self::Hm($start), self::Hm(min(24, $end)), $break, 0, null);
				if ($acceptable($issues, intval($e->id)))
				{
					$sim['new' . $guard] = ['id' => 0, 'employee_id' => $e->id, 'shift_date' => $date, 'start_time' => self::Hm($start), 'end_time' => self::Hm(min(24, $end)), 'break_minutes' => $break, 'role' => $e->position, 'version' => 0];
					$proposals[] = ['type' => 'add', 'shift_id' => null, 'employee_id' => intval($e->id), 'employee_name' => $e->name, 'from' => null, 'start_time' => self::Hm($start), 'end_time' => self::Hm(min(24, $end)),
						'break_minutes' => $break, 'covers_hours' => $z - $a, 'warnings' => array_values(array_filter($issues, fn($i) => $i['level'] === 'warning')), 'reason' => 'Agregar turno con personal disponible para la franja con faltante'];
					$placed = true;
					break;
				}
			}
			if (!$placed)
			{
				$max = max(array_map(fn($h) => $d[$h] - $handled[$h], range($a, $z - 1)));
				$proposals[] = ['type' => 'request', 'shift_id' => null, 'employee_id' => null, 'employee_name' => null, 'from' => null, 'start_time' => self::Hm($a), 'end_time' => self::Hm($z), 'staff_needed' => $max,
					'covers_hours' => 0, 'warnings' => [], 'reason' => 'Sin personal disponible sin conflictos' . ($allowOvertime ? '' : ' ni horas extra') . ': solicitar cobertura'];
				for ($h = $a; $h < $z; $h++)
				{
					$handled[$h] += $max;
				}
			}
		}

		$after = self::Metrics($needed, self::StaffFromSim($date, $sim), array_filter($sim, fn($s) => $s['shift_date'] === $date), $costs);
		return ['date' => $date, 'branch_id' => $branchId, 'proposals' => $proposals, 'before' => $before, 'after' => $after,
			'requires_overtime' => array_values(array_map(fn($id) => ['employee_id' => $id, 'name' => $name[$id] ?? '#' . $id], array_keys($requiresOvertime))),
			'overtime_allowed' => $allowOvertime, 'daily_budget' => $f['cost']['daily_budget']];
	}

	public function Recommend(int $branchId, string $date, bool $allowOvertime): array
	{
		$f = $this->Forecast($branchId, $date, false);
		$plan = $this->Plan($f, $allowOvertime);
		$plan['answer'] = $this->Answer($f, $plan);
		return $plan;
	}

	/**
	 * Aplica propuestas elegidas por el gerente (todas o ninguna, con bloqueo de escritura). Los turnos quedan con el motivo en
	 * el historial; los nuevos quedan en borrador hasta publicar (la publicación notifica a cada empleado).
	 */
	public function Apply(int $branchId, string $date, array $proposals, string $reason, int $userId, bool $canOvertime): array
	{
		$reason = trim($reason);
		if (mb_strlen($reason) < 5)
		{
			throw new \Exception('Indique el motivo del cambio (mínimo 5 caracteres)');
		}
		if (count($proposals) === 0 || count($proposals) > 50)
		{
			throw new \Exception('Seleccione entre 1 y 50 propuestas');
		}
		$ws = $this->Ws();
		$batch = 'fc-' . uniqid();
		return DatabaseService::GetInstance()->Locked(function () use ($ws, $branchId, $date, $proposals, $reason, $userId, $canOvertime, $batch) {
			$done = [];
			foreach ($proposals as $p)
			{
				$type = strval($p['type'] ?? '');
				$text = $reason . ' · ' . ($p['reason'] ?? $type);
				if ($type === 'request')
				{
					$this->CreateCoverageRequest(['branch_id' => $branchId, 'request_date' => $date, 'start_time' => $p['start_time'] ?? '', 'end_time' => $p['end_time'] ?? '', 'staff_needed' => $p['staff_needed'] ?? 1, 'role' => $p['role'] ?? null, 'reason' => $text], $userId);
					$done[] = ['type' => 'request'];
					continue;
				}
				if (!in_array($type, ['move', 'extend', 'add'], true))
				{
					throw new \Exception("Tipo de propuesta inválido: $type");
				}
				$employeeId = intval($p['employee_id'] ?? 0);
				$emp = $ws->GetEmployee($employeeId);
				if (intval($emp->branch_id) !== $branchId)
				{
					throw new \Exception('El empleado no pertenece a la sucursal');
				}
				$shiftId = intval($p['shift_id'] ?? 0);
				$cur = $shiftId ? $ws->GetShift($shiftId) : null;
				if ($cur && (intval($cur->branch_id) !== $branchId || $cur->shift_date !== $date || intval($cur->employee_id) !== $employeeId))
				{
					throw new \Exception("El turno #$shiftId no corresponde a la propuesta");
				}
				$issues = $ws->CheckConflicts($employeeId, $date, strval($p['start_time'] ?? ''), strval($p['end_time'] ?? ''), intval($p['break_minutes'] ?? 0), $shiftId, $cur->role ?? null);
				if (array_filter($issues, fn($i) => $i['code'] === 'overtime') && !$canOvertime)
				{
					throw new \Exception("La propuesta para {$emp->name} genera horas extra y usted no tiene permiso para aprobarlas");
				}
				$r = $ws->SaveShift([
					'id' => $shiftId ?: null, 'version' => $cur ? intval($p['version'] ?? $cur->version) : null, 'employee_id' => $employeeId, 'branch_id' => $branchId, 'shift_date' => $date,
					'start_time' => $p['start_time'] ?? '', 'end_time' => $p['end_time'] ?? '', 'break_minutes' => $cur ? intval($cur->break_minutes) : intval($p['break_minutes'] ?? 0),
					'department' => $cur->department ?? null, 'role' => $cur->role ?? null, 'notes' => $cur->notes ?? null, 'reason' => $text
				], $userId, $cur ? 'update' : 'create', $batch);
				$done[] = ['type' => $type, 'shift_id' => $r['id'], 'warnings' => $r['warnings']];
			}
			return ['applied' => $done, 'batch' => $batch];
		});
	}

	/* ------------------------------------------------------------------ Solicitudes de cobertura y calendario */

	public function CreateCoverageRequest(array $d, int $userId): int
	{
		$valid = fn($t) => is_string($t) && preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $t);
		if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', strval($d['request_date'] ?? '')) || !$valid($d['start_time'] ?? null) || !$valid($d['end_time'] ?? null) || $d['start_time'] >= $d['end_time'])
		{
			throw new \Exception('Fecha u horario de la solicitud inválido');
		}
		if (trim(strval($d['reason'] ?? '')) === '')
		{
			throw new \Exception('Indique el motivo de la solicitud');
		}
		$this->Q('INSERT INTO labor_coverage_requests (branch_id, request_date, start_time, end_time, staff_needed, role, reason, created_by_user_id) VALUES (:b, :d, :s, :e, :n, :r, :why, :u)', [
			':b' => intval($d['branch_id']), ':d' => $d['request_date'], ':s' => $d['start_time'], ':e' => $d['end_time'], ':n' => max(1, min(20, intval($d['staff_needed'] ?? 1))),
			':r' => trim(strval($d['role'] ?? '')) ?: null, ':why' => trim(strval($d['reason'])), ':u' => $userId
		]);
		return intval($this->Db()->lastInsertId());
	}

	public function GetCoverageRequest(int $id): array
	{
		$r = $this->Q('SELECT * FROM labor_coverage_requests WHERE id = :id', [':id' => $id])->fetch(\PDO::FETCH_ASSOC);
		if (!$r)
		{
			throw new \Exception('Solicitud no encontrada');
		}
		return $r;
	}

	public function CloseCoverageRequest(int $id, string $status, int $userId): void
	{
		if (!in_array($status, ['filled', 'cancelled'], true))
		{
			throw new \Exception('Estado inválido');
		}
		$n = $this->Q("UPDATE labor_coverage_requests SET status = :s, closed_by_user_id = :u, closed_timestamp = datetime('now', 'localtime') WHERE id = :id AND status = 'open'", [':s' => $status, ':u' => $userId, ':id' => $id])->rowCount();
		if ($n === 0)
		{
			throw new \Exception('La solicitud ya estaba cerrada');
		}
	}

	public function GetCoverageRequests(int $branchId, string $from): array
	{
		return $this->Q('SELECT * FROM labor_coverage_requests WHERE branch_id = :b AND request_date >= :f ORDER BY request_date, start_time', [':b' => $branchId, ':f' => $from])->fetchAll(\PDO::FETCH_ASSOC);
	}

	public function GetEvents(int $branchId, string $from, string $to): array
	{
		return $this->Q('SELECT * FROM labor_calendar_events WHERE branch_id IN (0, :b) AND event_date BETWEEN :f AND :t ORDER BY event_date', [':b' => $branchId, ':f' => $from, ':t' => $to])->fetchAll(\PDO::FETCH_ASSOC);
	}

	public function GetEvent(int $id): array
	{
		$r = $this->Q('SELECT * FROM labor_calendar_events WHERE id = :id', [':id' => $id])->fetch(\PDO::FETCH_ASSOC);
		if (!$r)
		{
			throw new \Exception('Evento no encontrado');
		}
		return $r;
	}

	public function SaveEvent(array $d, int $userId): int
	{
		$name = trim(strval($d['name'] ?? ''));
		$kind = strval($d['kind'] ?? 'event');
		$factor = floatval($d['sales_factor'] ?? 1);
		$extra = floatval($d['extra_hours'] ?? 0);
		if ($name === '' || !isset(self::EVENT_KINDS[$kind]) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', strval($d['event_date'] ?? '')))
		{
			throw new \Exception('Indique fecha, tipo y nombre del evento');
		}
		if ($factor < 0.1 || $factor > 5 || $extra < 0 || $extra > 200)
		{
			throw new \Exception('Factor de ventas (0.1–5) u horas adicionales (0–200) fuera de rango');
		}
		$this->Q('INSERT INTO labor_calendar_events (branch_id, event_date, kind, name, sales_factor, extra_hours, notes, created_by_user_id) VALUES (:b, :d, :k, :n, :f, :x, :o, :u)', [
			':b' => intval($d['branch_id'] ?? 0), ':d' => $d['event_date'], ':k' => $kind, ':n' => $name, ':f' => $factor, ':x' => $extra, ':o' => trim(strval($d['notes'] ?? '')) ?: null, ':u' => $userId
		]);
		return intval($this->Db()->lastInsertId());
	}

	public function DeleteEvent(int $id): void
	{
		$this->Q('DELETE FROM labor_calendar_events WHERE id = :id', [':id' => $id]);
	}

	/* ------------------------------------------------------------------ Aprendizaje: proyectado vs. real */

	public function Accuracy(int $branchId, int $days = 14): array
	{
		$days = max(1, min(60, $days));
		$today = date('Y-m-d');
		$rows = [];
		foreach ($this->Q('SELECT * FROM labor_forecast_snapshots WHERE branch_id = :b AND forecast_date < :t AND forecast_date >= date(:t, :n) ORDER BY forecast_date DESC', [':b' => $branchId, ':t' => $today, ':n' => "-$days day"])->fetchAll(\PDO::FETCH_ASSOC) as $s)
		{
			$d = $s['forecast_date'];
			$sale = $this->Q("SELECT IFNULL(SUM(total_amount), 0) s, COUNT(*) t FROM sales WHERE branch_id = :b AND status = 'completed' AND date(created_timestamp) = :d", [':b' => $branchId, ':d' => $d])->fetch(\PDO::FETCH_ASSOC);
			$worked = $this->Ws()->WorkedHoursByEmployee($d, $d, $branchId);
			$workedH = array_sum($worked);
			$staffUsed = count(array_filter($worked, fn($h) => $h > 0));
			$cost = 0.0;
			foreach ($worked as $eid => $h)
			{
				$cost += $h * floatval($this->Q('SELECT hourly_cost FROM employees WHERE id = :id', [':id' => $eid])->fetchColumn());
			}
			$tasksDone = intval($this->Q('SELECT COUNT(*) FROM workforce_store_tasks WHERE branch_id = :b AND date(completed_at) = :d', [':b' => $branchId, ':d' => $d])->fetchColumn());
			$tasksPending = intval($this->Q("SELECT COUNT(*) FROM workforce_store_tasks WHERE branch_id = :b AND date(due_time) = :d AND (status = 'pending' OR date(completed_at) > :d)", [':b' => $branchId, ':d' => $d])->fetchColumn());
			$waste = $this->Q('SELECT COUNT(*) n, IFNULL(SUM(total_cost), 0) c FROM waste_records WHERE branch_id = :b AND date(created_timestamp) = :d', [':b' => $branchId, ':d' => $d])->fetch(\PDO::FETCH_ASSOC);
			$proj = $s['projected_sales'] !== null ? floatval($s['projected_sales']) : null;
			$actual = floatval($sale['s']);
			$issues = [];
			if ($proj !== null && $proj > 0 && abs($actual - $proj) / $proj > 0.2)
			{
				$issues[] = 'Venta ' . ($actual > $proj ? 'superó' : 'quedó bajo') . ' el pronóstico en ' . round(abs($actual - $proj) / $proj * 100) . ' %';
			}
			if ($workedH > 0 && floatval($s['required_hours']) > 0 && $workedH < floatval($s['required_hours']) * 0.85)
			{
				$issues[] = 'Se trabajaron menos horas que las recomendadas';
			}
			if ($workedH > floatval($s['required_hours']) * 1.15 && floatval($s['required_hours']) > 0)
			{
				$issues[] = 'Se trabajaron más horas que las recomendadas';
			}
			if ($tasksPending > 0)
			{
				$issues[] = "$tasksPending tarea(s) del día sin completar a tiempo";
			}
			$rows[] = [
				'date' => $d, 'projected_sales' => $proj, 'actual_sales' => round($actual, 2), 'sales_error_pct' => $proj > 0 ? round(($actual - $proj) / $proj * 100, 1) : null,
				'projected_tickets' => $s['projected_tickets'] !== null ? floatval($s['projected_tickets']) : null, 'actual_tickets' => intval($sale['t']),
				'recommended_hours' => floatval($s['required_hours']), 'scheduled_hours' => floatval($s['scheduled_hours']), 'worked_hours' => round($workedH, 2),
				'recommended_staff' => intval($s['recommended_staff']), 'staff_used' => $staffUsed,
				'projected_productivity' => $s['productivity'] !== null ? floatval($s['productivity']) : null, 'actual_productivity' => $workedH > 0 ? round($actual / $workedH, 2) : null,
				'tasks_completed' => $tasksDone, 'tasks_pending' => $tasksPending, 'labor_cost' => round($cost, 2), 'stockouts' => $s['stockouts'] !== null ? intval($s['stockouts']) : null,
				'waste_records' => intval($waste['n']), 'waste_cost' => round(floatval($waste['c']), 2), 'issues' => $issues
			];
		}
		$withProj = array_filter($rows, fn($r) => $r['projected_sales'] > 0);
		$sumActual = array_sum(array_column($withProj, 'actual_sales'));
		$sumAbs = array_sum(array_map(fn($r) => abs($r['actual_sales'] - $r['projected_sales']), $withProj));
		$sumBias = array_sum(array_map(fn($r) => $r['projected_sales'] - $r['actual_sales'], $withProj));
		return [
			'days' => $rows,
			'wape_pct' => $sumActual > 0 ? round($sumAbs / $sumActual * 100, 1) : null,
			'bias_pct' => $sumActual > 0 ? round($sumBias / $sumActual * 100, 1) : null,
			'correction' => $this->LearnedCorrection($branchId, $today),
			'message' => count($rows) === 0 ? 'Aún no hay días cerrados con pronóstico guardado: el pronóstico se guarda cada vez que se consulta un día de hoy en adelante.' : null
		];
	}

	// Resumen para el Panel Ejecutivo: necesidad de mañana por sucursal
	public function Outlook(array $branchIds, ?string $date = null): array
	{
		$date = $date ?? self::D(date('Y-m-d'), 1);
		$out = [];
		foreach ($branchIds as $b)
		{
			try
			{
				$f = $this->Forecast(intval($b), $date, true, false);
				$gaps = array_values(array_filter($f['bands'], fn($x) => $x['diff'] < 0));
				$out[] = ['branch_id' => intval($b), 'branch' => $f['branch']['name'], 'date' => $date, 'projected_sales' => $f['sales']['projected'], 'recommended' => $f['staff']['recommended'],
					'scheduled' => $f['staff']['scheduled'], 'coverage_pct' => $f['coverage_pct'], 'gap_bands' => array_map(fn($x) => $x['label'] . ' (' . $x['diff'] . ')', $gaps), 'has_history' => $f['has_sales_history']];
			}
			catch (\Exception $ex)
			{
				// sucursal sin datos suficientes: se omite
			}
		}
		return $out;
	}
}
