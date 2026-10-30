<?php

namespace Grocy\Services;

/**
 * Integridad y control de movimientos de inventario.
 * - Seal(): sella con SHA-256 encadenado los movimientos aún sin sello del libro inalterable (migración 0276).
 * - Verify(): recalcula la cadena y compara el libro con stock_log: detecta movimientos borrados, modificados,
 *   no registrados en el libro o una cadena alterada (edición directa del archivo de base de datos).
 * - Anomalies(): reglas explicables sobre movimientos reales (no estadística opaca): duplicados probables,
 *   correcciones de inventario de alto valor, mermas descontadas sin caso, movimientos fuera de horario y
 *   anulaciones repetidas por usuario.
 */
class MovementLedgerService extends BaseService
{
	const FIELDS = ['stock_log_id', 'product_id', 'amount', 'transaction_type', 'stock_id', 'location_id', 'price', 'spoiled', 'best_before_date', 'transaction_id', 'correlation_id', 'user_id', 'note', 'row_created_timestamp', 'recorded_timestamp'];
	const COMPARED = ['product_id' => 'producto', 'amount' => 'cantidad', 'transaction_type' => 'tipo', 'stock_id' => 'lote', 'price' => 'precio', 'spoiled' => 'merma'];

	public function Seal(): int
	{
		$db = DatabaseService::GetInstance()->GetDbConnectionRaw();
		$db->beginTransaction();
		try
		{
			$prev = (string) $db->query('SELECT hash FROM dairy_movement_ledger WHERE hash IS NOT NULL ORDER BY stock_log_id DESC LIMIT 1')->fetchColumn();
			$rows = $db->query('SELECT * FROM dairy_movement_ledger WHERE hash IS NULL ORDER BY stock_log_id')->fetchAll(\PDO::FETCH_ASSOC);
			$upd = $db->prepare('UPDATE dairy_movement_ledger SET prev_hash = :p, hash = :h, sealed_timestamp = :t WHERE stock_log_id = :id AND hash IS NULL');
			foreach ($rows as $r)
			{
				$hash = $this->Hash($prev, $r);
				$upd->execute([':p' => $prev, ':h' => $hash, ':t' => date('Y-m-d H:i:s'), ':id' => $r['stock_log_id']]);
				$prev = $hash;
			}
			$db->commit();
			return count($rows);
		}
		catch (\Throwable $ex)
		{
			$db->rollBack();
			throw $ex;
		}
	}

	public function Verify(int $limit = 100): array
	{
		$this->Seal();
		$db = DatabaseService::GetInstance()->GetDbConnectionRaw();
		$issues = [];
		$add = function (string $type, string $severity, int $logId, string $detail, $productId = null) use (&$issues) {
			$issues[] = ['type' => $type, 'severity' => $severity, 'stock_log_id' => $logId, 'product_id' => $productId, 'detail' => $detail];
		};

		// 1. Cadena de sellos
		$prev = '';
		$sealed = 0;
		$chainOk = true;
		foreach ($db->query('SELECT * FROM dairy_movement_ledger WHERE hash IS NOT NULL ORDER BY stock_log_id', \PDO::FETCH_ASSOC) as $r)
		{
			$sealed++;
			if ((string) $r['prev_hash'] !== $prev || $this->Hash($prev, $r) !== $r['hash'])
			{
				if ($chainOk)
				{
					$add('chain', 'danger', intval($r['stock_log_id']), 'El sello no coincide: el libro fue editado fuera de DAIRY a partir de este movimiento', $r['product_id']);
				}
				$chainOk = false;
			}
			$prev = $r['hash'];
		}

		// 2. Libro ↔ stock_log
		$deleted = $db->query('SELECT g.stock_log_id, g.product_id, g.amount, g.transaction_type FROM dairy_movement_ledger g LEFT JOIN stock_log l ON l.id = g.stock_log_id WHERE l.id IS NULL ORDER BY g.stock_log_id')->fetchAll(\PDO::FETCH_ASSOC);
		foreach ($deleted as $d)
		{
			$add('deleted', 'danger', intval($d['stock_log_id']), "Movimiento borrado del historial ({$d['transaction_type']}, {$d['amount']} u.)", $d['product_id']);
		}
		$cols = implode(', ', array_map(fn($c) => "g.$c AS g_$c, l.$c AS l_$c", array_keys(self::COMPARED)));
		$where = implode(' OR ', array_map(fn($c) => "g.$c IS NOT l.$c", array_keys(self::COMPARED)));
		foreach ($db->query("SELECT g.stock_log_id, $cols FROM dairy_movement_ledger g JOIN stock_log l ON l.id = g.stock_log_id WHERE $where ORDER BY g.stock_log_id", \PDO::FETCH_ASSOC) as $m)
		{
			$changes = [];
			foreach (self::COMPARED as $c => $label)
			{
				if ((string) $m["g_$c"] !== (string) $m["l_$c"] && !(is_numeric($m["g_$c"]) && is_numeric($m["l_$c"]) && abs($m["g_$c"] - $m["l_$c"]) < 0.00001))
				{
					$changes[] = "$label {$m["g_$c"]} → {$m["l_$c"]}";
				}
			}
			if ($changes)
			{
				$add('modified', 'warning', intval($m['stock_log_id']), 'Modificado después de registrarse: ' . implode(', ', $changes) . ' (la fusión de productos o la división de lotes también lo hacen)', $m['l_product_id'] ?? $m['g_product_id']);
			}
		}
		foreach ($db->query('SELECT l.id, l.product_id FROM stock_log l LEFT JOIN dairy_movement_ledger g ON g.stock_log_id = l.id WHERE g.stock_log_id IS NULL ORDER BY l.id', \PDO::FETCH_ASSOC) as $u)
		{
			$add('unrecorded', 'danger', intval($u['id']), 'Movimiento que no pasó por el libro (captura desactivada o inserción directa)', $u['product_id']);
		}

		$counts = array_count_values(array_column($issues, 'type'));
		return [
			'ok' => count($issues) === 0,
			'chain_ok' => $chainOk,
			'sealed' => $sealed,
			'movements' => intval($db->query('SELECT COUNT(*) FROM stock_log')->fetchColumn()),
			'counts' => $counts,
			'issues' => array_slice($issues, 0, $limit),
			'checked_at' => date('Y-m-d H:i:s')
		];
	}

	public function Anomalies(?int $branchId = null, int $days = 30): array
	{
		$db = DatabaseService::GetInstance()->GetDbConnectionRaw();
		$since = date('Y-m-d', strtotime('-' . max(1, $days) . ' days'));
		$branch = $branchId ? ' AND loc.branch_id = ' . intval($branchId) : '';
		$base = "FROM stock_log l JOIN products p ON p.id = l.product_id LEFT JOIN locations loc ON loc.id = l.location_id LEFT JOIN users u ON u.id = l.user_id
			WHERE date(l.row_created_timestamp) >= '$since'$branch";
		$user = "IFNULL(NULLIF(TRIM(IFNULL(u.first_name, '') || ' ' || IFNULL(u.last_name, '')), ''), IFNULL(u.username, 'sistema'))";
		$out = [];
		$push = function (string $type, string $severity, array $r, string $detail) use (&$out) {
			$out[] = ['type' => $type, 'severity' => $severity, 'stock_log_id' => intval($r['id']), 'product_id' => intval($r['product_id']), 'product_name' => $r['product_name'], 'amount' => floatval($r['amount']), 'value' => round(abs(floatval($r['amount']) * floatval($r['price'] ?? 0)), 2), 'timestamp' => $r['row_created_timestamp'], 'user' => $r['user_name'], 'detail' => $detail];
		};

		// Duplicado probable: mismo producto, tipo, cantidad y usuario en ≤ 2 min, otra transacción
		foreach ($db->query("SELECT l.*, p.name AS product_name, $user AS user_name $base AND l.undone = 0 AND EXISTS (
				SELECT 1 FROM stock_log o WHERE o.id < l.id AND o.undone = 0 AND o.product_id = l.product_id AND o.transaction_type = l.transaction_type
				AND o.amount = l.amount AND IFNULL(o.user_id, 0) = IFNULL(l.user_id, 0) AND IFNULL(o.transaction_id, '') != IFNULL(l.transaction_id, '')
				AND ABS(strftime('%s', l.row_created_timestamp) - strftime('%s', o.row_created_timestamp)) <= 120)
			ORDER BY l.id DESC", \PDO::FETCH_ASSOC) as $r)
		{
			$push('duplicate', 'warning', $r, 'Mismo producto, tipo y cantidad registrado dos veces en menos de 2 minutos: confirmar que no sea un doble registro');
		}

		// Corrección de inventario de alto valor: ≥ mínimo configurable y ≥ media + 3σ de las correcciones de 90 días
		$vals = $db->query("SELECT ABS(amount * IFNULL(price, 0)) FROM stock_log WHERE transaction_type = 'inventory-correction' AND undone = 0 AND date(row_created_timestamp) >= date('now', '-90 days')")->fetchAll(\PDO::FETCH_COLUMN);
		$threshold = floatval(GROCY_INVENTORY_ANOMALY_MIN_VALUE);
		if (count($vals) >= 5)
		{
			$mean = array_sum($vals) / count($vals);
			$sd = sqrt(array_sum(array_map(fn($v) => ($v - $mean) ** 2, $vals)) / count($vals));
			$threshold = max($threshold, $mean + 3 * $sd);
		}
		foreach ($db->query("SELECT l.*, p.name AS product_name, $user AS user_name $base AND l.undone = 0 AND l.transaction_type = 'inventory-correction' AND ABS(l.amount * IFNULL(l.price, 0)) >= " . floatval($threshold) . ' ORDER BY ABS(l.amount * IFNULL(l.price, 0)) DESC', \PDO::FETCH_ASSOC) as $r)
		{
			$push('large_correction', 'danger', $r, 'Corrección de inventario de $' . number_format(abs($r['amount'] * $r['price']), 2) . ' (umbral $' . number_format($threshold, 2) . '): verificar el conteo que la respalda');
		}

		// Merma descontada en Grocy sin caso de merma (sin causal, aprobación ni evidencia)
		foreach ($db->query("SELECT l.*, p.name AS product_name, $user AS user_name $base AND l.undone = 0 AND l.transaction_type = 'consume' AND l.spoiled = 1
			AND NOT EXISTS (SELECT 1 FROM waste_records w WHERE w.transaction_id = l.transaction_id) ORDER BY l.id DESC", \PDO::FETCH_ASSOC) as $r)
		{
			$push('waste_without_case', 'warning', $r, 'Consumo marcado como vencido/dañado fuera de Registrar merma: no tiene causal, aprobación ni evidencia');
		}

		// Fuera del horario de tienda (STORE_OPEN_HOUR–STORE_CLOSE_HOUR)
		$open = intval(GROCY_STORE_OPEN_HOUR);
		$close = intval(GROCY_STORE_CLOSE_HOUR);
		foreach ($db->query("SELECT l.*, p.name AS product_name, $user AS user_name $base AND l.undone = 0 AND l.transaction_type != 'product-opened'
			AND (CAST(strftime('%H', l.row_created_timestamp) AS INTEGER) < $open OR CAST(strftime('%H', l.row_created_timestamp) AS INTEGER) >= $close) ORDER BY l.id DESC", \PDO::FETCH_ASSOC) as $r)
		{
			$push('off_hours', 'info', $r, "Registrado a las " . substr($r['row_created_timestamp'], 11, 5) . " (horario $open:00–$close:00)");
		}

		// Usuarios con anulaciones repetidas
		foreach ($db->query("SELECT MAX(l.id) AS id, MAX(l.product_id) AS product_id, COUNT(*) || ' movimientos' AS product_name, SUM(l.amount) AS amount, NULL AS price, MAX(l.undone_timestamp) AS row_created_timestamp, $user AS user_name, COUNT(*) AS n
			$base AND l.undone = 1 GROUP BY l.user_id HAVING COUNT(*) >= " . intval(GROCY_INVENTORY_ANOMALY_UNDO_COUNT), \PDO::FETCH_ASSOC) as $r)
		{
			$push('undo_heavy', 'warning', $r, "{$r['user_name']} anuló {$r['n']} movimientos en $days días");
		}

		$rank = ['danger' => 0, 'warning' => 1, 'info' => 2];
		usort($out, fn($a, $b) => [$rank[$a['severity']], -$a['value']] <=> [$rank[$b['severity']], -$b['value']]);
		return ['since' => $since, 'count' => count($out), 'counts' => array_count_values(array_column($out, 'type')), 'items' => $out];
	}

	private function Hash(string $prev, array $row): string
	{
		$data = [];
		foreach (self::FIELDS as $f)
		{
			$v = $row[$f] ?? null;
			$data[$f] = $v === null ? null : (is_numeric($v) ? rtrim(rtrim(sprintf('%.6F', $v), '0'), '.') : (string) $v);
		}
		return hash('sha256', $prev . '|' . json_encode($data, JSON_UNESCAPED_UNICODE));
	}
}
