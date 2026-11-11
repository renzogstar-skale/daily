<?php

namespace Grocy\Services;

use Grocy\Helpers\DailyFilters;

/**
 * Auditoría de la historia comercial (pestaña Historial de Ventas), al estilo de una auditoría de ventas minorista:
 * - Tickets duplicados: misma sucursal, misma fecha y hora, mismo total y mismas líneas (p. ej. doble sincronización).
 * - Registro tardío: líneas grabadas en un día posterior a la fecha de venta. Los informes cuentan la fecha de venta.
 * - Devoluciones: solo las devoluciones de cliente reembolsadas restan, en la fecha de la devolución y por el importe
 *   devuelto; cambios, rechazos y devoluciones a proveedor no son ventas devueltas.
 * - Cambios históricos: toda modificación de sales/sale_items queda en dairy_change_log (migración 0325); se marca si
 *   la respalda una corrección autorizada (daily_sales_corrections).
 * - Cierres comerciales: foto de las cifras de una semana o un mes; si después cambian, se señala y se explica.
 */
class SalesHistoryAuditService extends BaseService
{
	// Devolución que resta venta: cliente, reembolso o crédito en tienda, reembolso completado y no rechazada
	public const REFUND_SQL = "r.return_type = 'devolucion_cliente' AND r.resolution IN ('reembolso', 'credito_tienda')
		AND r.refund_status = 'completed' AND IFNULL(r.status, '') NOT IN ('rechazada', 'anulada') AND IFNULL(r.customer_refund_amount, 0) > 0";

	private function Db(): \PDO
	{
		return DatabaseService::GetInstance()->GetDbConnectionRaw();
	}

	private function Users(): array
	{
		$u = [];
		foreach ($this->Db()->query("SELECT id, TRIM(IFNULL(first_name, '') || ' ' || IFNULL(last_name, '')) AS n, username FROM users")->fetchAll(\PDO::FETCH_ASSOC) as $r)
		{
			$u[intval($r['id'])] = $r['n'] !== '' ? $r['n'] : $r['username'];
		}
		return $u;
	}

	/** Grupos de tickets duplicados con los filtros de sucursal y período ($from/$to null = todo el historial) */
	public function Duplicates(array $f, ?string $from = null, ?string $to = null): array
	{
		$w = "s.status = 'completed' AND " . DailyFilters::BranchSql($f, 's.branch_id');
		$args = [];
		if ($from !== null)
		{
			$w .= ' AND s.created_timestamp >= :f AND s.created_timestamp < :t';
			$args = [':f' => "$from 00:00:00", ':t' => date('Y-m-d', strtotime("$to +1 day")) . ' 00:00:00'];
		}
		$st = $this->Db()->prepare("WITH fp AS (
				SELECT s.id, s.sale_number, s.branch_id, s.created_timestamp, s.total_amount, s.tax_amount,
					(SELECT group_concat(k, ',') FROM (SELECT si.product_id || 'x' || si.amount || '@' || si.subtotal AS k FROM sale_items si WHERE si.sale_id = s.id ORDER BY k)) AS lines
				FROM sales s WHERE $w)
			SELECT a.*, b.name AS branch FROM fp a LEFT JOIN branches b ON b.id = a.branch_id
			WHERE EXISTS (SELECT 1 FROM fp x WHERE x.id <> a.id AND x.branch_id = a.branch_id AND x.created_timestamp = a.created_timestamp
				AND x.total_amount = a.total_amount AND IFNULL(x.lines, '') = IFNULL(a.lines, ''))
			ORDER BY a.created_timestamp, a.id");
		$st->execute($args);
		$groups = [];
		foreach ($st->fetchAll(\PDO::FETCH_ASSOC) as $r)
		{
			$k = $r['branch_id'] . '|' . $r['created_timestamp'] . '|' . $r['total_amount'] . '|' . $r['lines'];
			$groups[$k] ??= ['datetime' => $r['created_timestamp'], 'branch' => $r['branch'], 'net' => round($r['total_amount'] - $r['tax_amount'], 2), 'sales' => []];
			$groups[$k]['sales'][] = ['id' => intval($r['id']), 'number' => $r['sale_number']];
		}
		// Se conserva el primer ticket registrado; los demás son la copia
		$out = [];
		foreach ($groups as $g)
		{
			$g['keep'] = $g['sales'][0];
			$g['copies'] = array_slice($g['sales'], 1);
			$g['excess'] = round($g['net'] * count($g['copies']), 2);
			unset($g['sales']);
			$out[] = $g;
		}
		return $out;
	}

	/** Tickets cuyas líneas se grabaron en un día posterior a la fecha de venta */
	private function LateRegistrations(array $f, string $from, string $to): array
	{
		$st = $this->Db()->prepare("SELECT s.id, s.sale_number, s.created_timestamp AS sold_at, MAX(si.created_timestamp) AS recorded_at, s.total_amount - s.tax_amount AS net, b.name AS branch
			FROM sales s JOIN sale_items si ON si.sale_id = s.id LEFT JOIN branches b ON b.id = s.branch_id
			WHERE s.status = 'completed' AND " . DailyFilters::BranchSql($f, 's.branch_id') . "
				AND ((s.created_timestamp >= :f AND s.created_timestamp < :t) OR (si.created_timestamp >= :f AND si.created_timestamp < :t))
			GROUP BY s.id HAVING date(MAX(si.created_timestamp)) > date(s.created_timestamp) ORDER BY s.created_timestamp DESC");
		$st->execute([':f' => "$from 00:00:00", ':t' => date('Y-m-d', strtotime("$to +1 day")) . ' 00:00:00']);
		$out = [];
		foreach ($st->fetchAll(\PDO::FETCH_ASSOC) as $r)
		{
			$out[] = ['id' => intval($r['id']), 'number' => $r['sale_number'], 'branch' => $r['branch'], 'sold_at' => $r['sold_at'], 'recorded_at' => $r['recorded_at'],
				'lag_days' => intval(round((strtotime(substr($r['recorded_at'], 0, 10)) - strtotime(substr($r['sold_at'], 0, 10))) / 86400)), 'net' => round(floatval($r['net']), 2),
				// El registro tardío cae dentro del período, pero la venta se cuenta en su fecha real
				'counted_in_period' => substr($r['sold_at'], 0, 10) >= $from && substr($r['sold_at'], 0, 10) <= $to];
		}
		return $out;
	}

	/** Devoluciones de clientes del período: reembolsadas (restan) y excluidas con su motivo */
	public function Refunds(array $f, string $from, string $to): array
	{
		$pw = DailyFilters::ProductSql($f);
		$st = $this->Db()->prepare("SELECT r.id, r.return_code, r.return_type, r.resolution, r.status, r.refund_status, r.customer_refund_amount AS amount, date(r.created_at) AS day,
				r.sale_id, IFNULL(s.sale_number, r.ticket_number) AS ticket, s.created_timestamp AS sold_at, p.name AS product, b.name AS branch, (" . self::REFUND_SQL . ") AS counts
			FROM customer_returns_exchanges r LEFT JOIN sales s ON s.id = r.sale_id LEFT JOIN products p ON p.id = r.product_id LEFT JOIN branches b ON b.id = r.branch_id
			WHERE date(r.created_at) BETWEEN :f AND :t AND " . DailyFilters::BranchSql($f, 'r.branch_id') . " AND (r.product_id IS NULL OR $pw)
			ORDER BY r.created_at DESC");
		$st->execute([':f' => $from, ':t' => $to]);
		$rows = [];
		$total = 0.0;
		$byDay = [];
		$excluded = [];
		foreach ($st->fetchAll(\PDO::FETCH_ASSOC) as $r)
		{
			if ($r['counts'])
			{
				$total += $r['amount'];
				$byDay[$r['day']] = round(($byDay[$r['day']] ?? 0) + $r['amount'], 2);
				$rows[] = ['code' => $r['return_code'], 'day' => $r['day'], 'ticket' => $r['ticket'], 'sold_at' => $r['sold_at'], 'product' => $r['product'], 'branch' => $r['branch'],
					'amount' => round(floatval($r['amount']), 2), 'resolution' => $r['resolution'],
					'other_period' => $r['sold_at'] !== null && (substr($r['sold_at'], 0, 10) < $from || substr($r['sold_at'], 0, 10) > $to)];
				continue;
			}
			$why = $r['return_type'] !== 'devolucion_cliente' ? 'no es devolución de cliente' : ($r['resolution'] === 'cambio' ? 'cambio de producto' :
				(in_array($r['status'], ['rechazada', 'anulada'], true) || $r['resolution'] === 'rechazo_justificado' ? 'rechazada' : 'reembolso no completado'));
			$excluded[$why] = ($excluded[$why] ?? 0) + 1;
		}
		return ['total' => round($total, 2), 'count' => count($rows), 'rows' => $rows, 'by_day' => $byDay, 'excluded' => $excluded];
	}

	/** Cambios a ventas de días anteriores (fecha del cambio posterior a la fecha de venta) */
	private function Changes(array $f, string $from, string $to, int $limit = 60): array
	{
		$st = $this->Db()->prepare("SELECT c.id, c.table_name, c.row_id, c.action, c.old_json, c.new_json, c.user_id, c.changed_at,
				IFNULL(json_extract(c.old_json, '$.sale_id'), json_extract(c.new_json, '$.sale_id')) AS item_sale
			FROM dairy_change_log c WHERE c.table_name IN ('sales', 'sale_items') AND date(c.changed_at) >= :f ORDER BY c.id DESC LIMIT 2000");
		$st->execute([':f' => $from]);
		$rows = $st->fetchAll(\PDO::FETCH_ASSOC);
		$saleIds = array_unique(array_map(fn($r) => intval($r['table_name'] === 'sales' ? $r['row_id'] : $r['item_sale']), $rows));
		$sales = [];
		$corr = [];
		if ($saleIds)
		{
			$in = implode(',', $saleIds);
			foreach ($this->Db()->query("SELECT id, sale_number, branch_id, created_timestamp FROM sales WHERE id IN ($in)")->fetchAll(\PDO::FETCH_ASSOC) as $s)
			{
				$sales[intval($s['id'])] = $s;
			}
			foreach ($this->Db()->query("SELECT id, sale_id, kind, created_at FROM daily_sales_corrections WHERE sale_id IN ($in)")->fetchAll(\PDO::FETCH_ASSOC) as $k)
			{
				$corr[intval($k['sale_id'])][] = $k;
			}
		}
		$allowed = DailyFilters::BranchIds($f);
		$users = $this->Users();
		$out = [];
		$unexplained = 0;
		foreach ($rows as $r)
		{
			$sid = intval($r['table_name'] === 'sales' ? $r['row_id'] : $r['item_sale']);
			$old = json_decode((string) $r['old_json'], true) ?: [];
			$new = json_decode((string) $r['new_json'], true) ?: [];
			$saleDay = substr($sales[$sid]['created_timestamp'] ?? ($old['created_timestamp'] ?? ''), 0, 10);
			$branch = intval($sales[$sid]['branch_id'] ?? ($old['branch_id'] ?? 0));
			// Solo cambios a ventas de días anteriores dentro del período consultado
			if ($saleDay === '' || $saleDay >= substr($r['changed_at'], 0, 10) || substr($r['changed_at'], 0, 10) > $to || ($allowed !== null && $r['table_name'] === 'sales' && !in_array($branch, $allowed, true)))
			{
				continue;
			}
			$fields = [];
			foreach ($r['action'] === 'update' ? $old : [] as $k => $v)
			{
				if (array_key_exists($k, $new) && (string) $new[$k] !== (string) $v)
				{
					$fields[] = ['field' => $k, 'old' => $v, 'new' => $new[$k]];
				}
			}
			// Una corrección respalda solo el campo que corrige: fecha de venta (date) o estado del ticket (duplicate)
			$by = null;
			$changed = array_column($fields, 'field');
			foreach ($corr[$sid] ?? [] as $k)
			{
				if ($r['table_name'] === 'sales' && in_array($k['kind'] === 'date' ? 'created_timestamp' : 'status', $changed, true)
					&& abs(strtotime($k['created_at']) - strtotime($r['changed_at'])) <= 120)
				{
					$by = intval($k['id']);
				}
			}
			// La cancelación por el flujo de caja (status → cancelled con usuario) es una operación autorizada del POS
			$posCancel = $r['table_name'] === 'sales' && ($new['status'] ?? null) === 'cancelled' && $r['user_id'] !== null;
			if ($by === null && !$posCancel)
			{
				$unexplained++;
			}
			if (count($out) < $limit)
			{
				$out[] = ['id' => intval($r['id']), 'changed_at' => $r['changed_at'], 'sale_id' => $sid, 'ticket' => $sales[$sid]['sale_number'] ?? ($old['sale_number'] ?? ('#' . $sid)),
					'sale_day' => $saleDay, 'table' => $r['table_name'], 'action' => $r['action'], 'fields' => array_slice($fields, 0, 4),
					'user' => $r['user_id'] !== null ? ($users[intval($r['user_id'])] ?? ('Usuario ' . $r['user_id'])) : 'Sistema (sin usuario)',
					'correction_id' => $by, 'pos_cancel' => $posCancel];
			}
		}
		return ['rows' => $out, 'unexplained' => $unexplained];
	}

	private function Corrections(int $limit = 50): array
	{
		$users = $this->Users();
		$rows = $this->Db()->query("SELECT c.*, s.sale_number FROM daily_sales_corrections c LEFT JOIN sales s ON s.id = c.sale_id ORDER BY c.id DESC LIMIT " . intval($limit))->fetchAll(\PDO::FETCH_ASSOC);
		return array_map(fn($r) => ['id' => intval($r['id']), 'created_at' => $r['created_at'], 'kind' => $r['kind'], 'ticket' => $r['sale_number'] ?? ('#' . $r['sale_id']),
			'reason' => $r['reason'], 'before' => json_decode((string) $r['before_json'], true), 'after' => json_decode((string) $r['after_json'], true),
			'user' => $r['authorized_by'] !== null ? ($users[intval($r['authorized_by'])] ?? ('Usuario ' . $r['authorized_by'])) : '—'], $rows);
	}

	/** Cifras de la empresa completa de un período (las mismas que los informes) más devoluciones */
	private function Snapshot(string $from, string $to): array
	{
		$f = DailyFilters::FromQuery(['start_date' => $from, 'end_date' => $to]);
		$t = SalesReportsService::GetInstance()->PeriodTotal($f, $from, $to);
		return ['net' => round($t['net'], 2), 'tickets' => intval($t['tickets']), 'refunds' => $this->Refunds($f, $from, $to)['total']];
	}

	/** Cierres registrados (con verificación contra las cifras actuales) y períodos terminados pendientes de cierre */
	private function Closures(string $to): array
	{
		$users = $this->Users();
		$out = [];
		$closed = [];
		foreach ($this->Db()->query('SELECT * FROM daily_sales_closures ORDER BY period_from DESC, period_type LIMIT 24')->fetchAll(\PDO::FETCH_ASSOC) as $c)
		{
			$now = $this->Snapshot($c['period_from'], $c['period_to']);
			$dNet = round($now['net'] - $c['net'], 2);
			$dTk = $now['tickets'] - intval($c['tickets']);
			$corr = $this->Db()->prepare("SELECT COUNT(*) FROM daily_sales_corrections k JOIN sales s ON s.id = k.sale_id
				WHERE k.created_at > :ca AND (date(s.created_timestamp) BETWEEN :f AND :t OR date(json_extract(k.before_json, '$.created_timestamp')) BETWEEN :f AND :t)");
			$corr->execute([':ca' => $c['closed_at'], ':f' => $c['period_from'], ':t' => $c['period_to']]);
			$closed[$c['period_type'] . $c['period_from']] = true;
			$out[] = ['id' => intval($c['id']), 'type' => $c['period_type'], 'from' => $c['period_from'], 'to' => $c['period_to'], 'net' => floatval($c['net']), 'tickets' => intval($c['tickets']),
				'refunds' => floatval($c['refunds']), 'closed_at' => $c['closed_at'], 'notes' => $c['notes'], 'user' => $users[intval($c['closed_by'])] ?? '—',
				'now_net' => $now['net'], 'now_tickets' => $now['tickets'], 'delta_net' => $dNet, 'delta_tickets' => $dTk,
				'intact' => abs($dNet) < 0.01 && $dTk === 0, 'corrections_after' => intval($corr->fetchColumn())];
		}
		// Pendientes: los 6 últimos meses y las 4 últimas semanas ya terminados (a la fecha de hoy) sin cierre
		$pending = [];
		$today = date('Y-m-d');
		$m = date('Y-m-01', strtotime(min($to, $today)));
		for ($i = 0; $i < 7; $i++)
		{
			$mf = date('Y-m-01', strtotime("$m -$i month"));
			$mt = date('Y-m-t', strtotime($mf));
			if ($mt < $today && !isset($closed['month' . $mf]))
			{
				$pending[] = ['type' => 'month', 'from' => $mf, 'to' => $mt];
			}
		}
		$mon = date('Y-m-d', strtotime('monday this week', strtotime(min($to, $today))));
		for ($i = 1; $i <= 4; $i++)
		{
			$wf = date('Y-m-d', strtotime("$mon -" . (7 * $i) . ' days'));
			if (!isset($closed['week' . $wf]))
			{
				$pending[] = ['type' => 'week', 'from' => $wf, 'to' => date('Y-m-d', strtotime("$wf +6 days"))];
			}
		}
		return ['rows' => $out, 'pending' => array_slice($pending, 0, 8)];
	}

	/** Todo lo que muestra el bloque de auditoría del Historial con los filtros compartidos */
	public function Audit(array $f): array
	{
		$from = $f['start_date'];
		$to = $f['end_date'];
		$dups = $this->Duplicates($f, $from, $to);
		$late = $this->LateRegistrations($f, $from, $to);
		$changes = $this->Changes($f, $from, $to);
		return [
			'duplicates' => $dups,
			'duplicates_excess' => round(array_sum(array_column($dups, 'excess')), 2),
			'duplicates_all_time' => count($this->Duplicates($f)),
			'late' => array_slice($late, 0, 50),
			'late_count' => count($late),
			'refunds' => $this->Refunds($f, $from, $to),
			'changes' => $changes['rows'],
			'changes_unexplained' => $changes['unexplained'],
			'corrections' => $this->Corrections(),
			'closures' => $this->Closures($to)
		];
	}

	/** Anula la copia de un ticket duplicado (reintegra inventario con el flujo de cancelación) y registra la corrección */
	public function VoidDuplicate(int $saleId, string $reason, int $userId): array
	{
		$reason = trim($reason);
		if (mb_strlen($reason) < 5)
		{
			throw new \Exception('Indique el motivo de la corrección');
		}
		$group = null;
		foreach ($this->Duplicates(['branch_id' => null, 'company_id' => null]) as $g)
		{
			if (in_array($saleId, array_column($g['copies'], 'id'), true))
			{
				$group = $g;
			}
		}
		if ($group === null)
		{
			throw new \Exception('El ticket no es la copia de un duplicado (debe coincidir sucursal, fecha y hora, total y líneas con un ticket anterior)');
		}
		$before = $this->Db()->query('SELECT id, sale_number, status, total_amount, created_timestamp FROM sales WHERE id = ' . $saleId)->fetch(\PDO::FETCH_ASSOC);
		SalesService::GetInstance()->CancelSale($saleId, $userId);
		$after = $this->Db()->query('SELECT status FROM sales WHERE id = ' . $saleId)->fetch(\PDO::FETCH_ASSOC);
		$this->Db()->prepare('INSERT INTO daily_sales_corrections (sale_id, kind, reason, related_sale_id, before_json, after_json, authorized_by) VALUES (?, ?, ?, ?, ?, ?, ?)')
			->execute([$saleId, 'duplicate', $reason, $group['keep']['id'], json_encode($before), json_encode($after + ['kept' => $group['keep']['number']]), $userId]);
		return ['id' => intval($this->Db()->lastInsertId()), 'ticket' => $before['sale_number'], 'kept' => $group['keep']['number']];
	}

	/** Corrige la fecha de venta de un ticket (p. ej. a la fecha real de registro) con motivo y autorización */
	public function CorrectDate(int $saleId, string $datetime, string $reason, int $userId): array
	{
		$reason = trim($reason);
		if (mb_strlen($reason) < 5)
		{
			throw new \Exception('Indique el motivo de la corrección');
		}
		$datetime = str_replace('T', ' ', trim($datetime));
		if (strlen($datetime) === 16)
		{
			$datetime .= ':00';
		}
		if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $datetime) || strtotime($datetime) === false || $datetime > date('Y-m-d H:i:s'))
		{
			throw new \Exception('Fecha de venta no válida');
		}
		$before = $this->Db()->query('SELECT id, sale_number, status, created_timestamp FROM sales WHERE id = ' . $saleId)->fetch(\PDO::FETCH_ASSOC);
		if (!$before || $before['status'] !== 'completed')
		{
			throw new \Exception('Solo se corrige la fecha de ventas completadas');
		}
		if ($before['created_timestamp'] === $datetime)
		{
			throw new \Exception('La fecha indicada es la misma');
		}
		// Antes del corte, la historia oficial es el archivo consolidado: mover un ticket hacia o desde ahí lo haría desaparecer o duplicarse
		$cut = SalesReportsService::GetInstance()->ArchiveCutover();
		if ($cut !== '' && (substr($datetime, 0, 10) < $cut || substr($before['created_timestamp'], 0, 10) < $cut))
		{
			throw new \Exception('Las ventas anteriores al ' . date('d/m/Y', strtotime($cut)) . ' están consolidadas en el archivo histórico y no se corrigen ticket a ticket');
		}
		$db = $this->Db();
		$db->beginTransaction();
		try
		{
			$db->prepare('UPDATE sales SET created_timestamp = ? WHERE id = ?')->execute([$datetime, $saleId]);
			$db->prepare('INSERT INTO daily_sales_corrections (sale_id, kind, reason, before_json, after_json, authorized_by) VALUES (?, ?, ?, ?, ?, ?)')
				->execute([$saleId, 'date', $reason, json_encode(['created_timestamp' => $before['created_timestamp']]), json_encode(['created_timestamp' => $datetime]), $userId]);
			$id = intval($db->lastInsertId());
			$db->commit();
		}
		catch (\Throwable $e)
		{
			$db->rollBack();
			throw $e;
		}
		return ['id' => $id, 'ticket' => $before['sale_number']];
	}

	/** Registra el cierre comercial de una semana (desde lunes) o un mes terminado, con la foto de sus cifras */
	public function Close(string $type, string $from, string $notes, int $userId): array
	{
		if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) || !in_array($type, ['week', 'month'], true))
		{
			throw new \Exception('Período no válido');
		}
		if ($type === 'week')
		{
			if (date('N', strtotime($from)) !== '1')
			{
				throw new \Exception('La semana comercial empieza el lunes');
			}
			$to = date('Y-m-d', strtotime("$from +6 days"));
		}
		else
		{
			$from = date('Y-m-01', strtotime($from));
			$to = date('Y-m-t', strtotime($from));
		}
		if ($to >= date('Y-m-d'))
		{
			throw new \Exception('Solo se cierran períodos terminados');
		}
		$exists = $this->Db()->prepare('SELECT COUNT(*) FROM daily_sales_closures WHERE period_type = ? AND period_from = ?');
		$exists->execute([$type, $from]);
		if (intval($exists->fetchColumn()) > 0)
		{
			throw new \Exception('Ese período ya tiene cierre comercial');
		}
		$s = $this->Snapshot($from, $to);
		$this->Db()->prepare('INSERT INTO daily_sales_closures (period_type, period_from, period_to, net, tickets, refunds, notes, closed_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')
			->execute([$type, $from, $to, $s['net'], $s['tickets'], $s['refunds'], trim($notes) ?: null, $userId]);
		return ['id' => intval($this->Db()->lastInsertId()), 'from' => $from, 'to' => $to] + $s;
	}
}
