<?php

namespace Grocy\Services;

/**
 * Conteo cíclico por clase ABC (SAP MM "cycle counting"): clasifica los productos con stock de la sucursal
 * por ventas de los últimos 90 días (Pareto 80/95) y programa un conteo cuando venció su frecuencia
 * (CYCLE_COUNT_DAYS_A/B/C). No duplica conteos pendientes.
 */
class CycleCountService extends BaseService
{
	/**
	 * Registra el conteo físico. El stock del sistema se toma al momento de contar (no al programar: las ventas
	 * intermedias darían diferencias falsas) y solo se ejecuta un conteo pendiente; el UPDATE condicionado al estado
	 * evita que un reenvío o dos usuarios a la vez sobrescriban un conteo ya registrado o ajustado.
	 */
	/**
	 * Ajuste de inventario por conteo con diferencia: corrección en la ubicación contada (stock_log → kardex → libro),
	 * separada de la merma. Requiere aprobador distinto de quien contó.
	 */
	public function ApproveAdjustment(int $countId, int $approverUserId): array
	{
		$db = DatabaseService::GetInstance()->GetDbConnectionRaw();
		$stmtQ = $db->prepare("
			SELECT cc.*, p.default_best_before_days,
				IFNULL((SELECT SUM(s.amount * s.price) / NULLIF(SUM(s.amount), 0) FROM stock s WHERE s.product_id = cc.product_id AND s.price > 0), 0) AS unit_cost,
				IFNULL((SELECT SUM(s.amount) FROM stock s WHERE s.product_id = cc.product_id), 0) AS current_total
			FROM stock_cycle_counts cc JOIN products p ON p.id = cc.product_id
			WHERE cc.id = :id
		");
		$stmtQ->execute([':id' => $countId]);
		$row = $stmtQ->fetch(\PDO::FETCH_ASSOC);
		if (!$row)
		{
			throw new \Exception('Conteo cíclico no encontrado');
		}
		if ($row['status'] !== 'discrepancy')
		{
			throw new \Exception('Solo se ajustan conteos con diferencia pendiente');
		}
		if (!(defined('GROCY_WASTE_ALLOW_SELF_APPROVAL') && GROCY_WASTE_ALLOW_SELF_APPROVAL) && intval($row['executed_by_user_id']) === $approverUserId)
		{
			throw new \Exception('Segregación de funciones: quien ejecutó el conteo no puede aprobar el ajuste');
		}

		$diff = floatval($row['difference_amount']);
		$newAmount = max(0, floatval($row['current_total']) + $diff);
		$days = intval($row['default_best_before_days']);
		$bestBefore = $days > 0 ? date('Y-m-d', strtotime("+{$days} days")) : '2999-12-31';

		$db->beginTransaction();
		try
		{
			$transactionId = null;
			if (abs($diff) > 0.0001 && abs($newAmount - floatval($row['current_total'])) > 0.0001)
			{
				// Corrección en la ubicación contada (InventoryProduct de Grocy compara y descuenta contra el total de la red)
				$stock = StockService::GetInstance();
				$loc = !empty($row['location_id']) ? intval($row['location_id']) : null;
				if ($diff < 0)
				{
					$stock->ConsumeProduct(intval($row['product_id']), abs($diff), false, StockService::TRANSACTION_TYPE_INVENTORY_CORRECTION, 'default', null, $loc, $transactionId, false, true);
				}
				else
				{
					$transactionId = $stock->AddProduct(intval($row['product_id']), $diff, $bestBefore, StockService::TRANSACTION_TYPE_INVENTORY_CORRECTION, date('Y-m-d'),
						floatval($row['unit_cost']) > 0 ? floatval($row['unit_cost']) : null, $loc, null, $transactionId, 0, false, 'Ajuste por conteo cíclico CC-' . str_pad((string) $countId, 4, '0', STR_PAD_LEFT));
				}
			}
			$upd = $db->prepare("UPDATE stock_cycle_counts SET status = 'adjusted', supervisor_user_id = :uid, adjustment_transaction_id = :tid WHERE id = :id AND status = 'discrepancy'");
			$upd->execute([':uid' => $approverUserId, ':tid' => $transactionId, ':id' => $countId]);
			if ($upd->rowCount() !== 1)
			{
				throw new \Exception('El ajuste de este conteo ya fue registrado');
			}
			$db->commit();
			return ['success' => true, 'transaction_id' => $transactionId, 'difference' => $diff, 'message' => 'Ajuste de inventario contabilizado'];
		}
		catch (\Throwable $e)
		{
			if ($db->inTransaction())
			{
				$db->rollBack();
			}
			throw $e;
		}
	}

	public function Execute(int $countId, $countedStock, ?string $notes, int $userId): array
	{
		if ($countedStock === null || $countedStock === '' || !is_numeric($countedStock) || floatval($countedStock) < 0)
		{
			throw new \Exception('Ingrese la cantidad física contada (cero o mayor)');
		}
		$counted = floatval($countedStock);
		$db = DatabaseService::GetInstance()->GetDbConnectionRaw();
		$stmt = $db->prepare('SELECT * FROM stock_cycle_counts WHERE id = :id');
		$stmt->execute([':id' => $countId]);
		$row = $stmt->fetch(\PDO::FETCH_ASSOC);
		if (!$row)
		{
			throw new \Exception('Conteo cíclico no encontrado');
		}
		if ($row['status'] !== 'pending')
		{
			throw new \Exception('Este conteo ya fue registrado; programe uno nuevo para volver a contar');
		}

		$locationId = !empty($row['location_id']) ? intval($row['location_id']) : null;
		$params = [':pid' => intval($row['product_id'])];
		if ($locationId)
		{
			$params[':lid'] = $locationId;
		}
		$stmt = $db->prepare('SELECT IFNULL(SUM(amount), 0) AS theo, SUM(CASE WHEN price > 0 THEN amount * price END) / NULLIF(SUM(CASE WHEN price > 0 THEN amount END), 0) AS cost FROM stock WHERE product_id = :pid' . ($locationId ? ' AND location_id = :lid' : ''));
		$stmt->execute($params);
		$book = $stmt->fetch(\PDO::FETCH_ASSOC);
		$unitCost = floatval($book['cost'] ?? 0);
		if ($unitCost <= 0)
		{
			$unitCost = floatval($db->query('SELECT IFNULL((SELECT price FROM products_last_purchased WHERE product_id = ' . intval($row['product_id']) . '), 0)')->fetchColumn());
		}
		$theo = round(floatval($book['theo']), 4);
		$diff = round($counted - $theo, 4);
		$diffCost = round($diff * $unitCost, 2);
		$status = abs($diff) < 0.001 ? 'completed' : 'discrepancy';

		$upd = $db->prepare("UPDATE stock_cycle_counts SET theoretical_stock = :theo, counted_stock = :counted, difference_amount = :diff, difference_cost = :cost, status = :status, executed_date = :d, executed_by_user_id = :uid, notes = :notes WHERE id = :id AND status = 'pending'");
		$upd->execute([':theo' => $theo, ':counted' => $counted, ':diff' => $diff, ':cost' => $diffCost, ':status' => $status, ':d' => date('Y-m-d'), ':uid' => $userId, ':notes' => ($notes !== null && trim($notes) !== '') ? trim($notes) : $row['notes'], ':id' => $countId]);
		if ($upd->rowCount() !== 1)
		{
			throw new \Exception('Este conteo ya fue registrado por otro usuario');
		}

		return [
			'success' => true,
			'theoretical_stock' => $theo,
			'difference_amount' => $diff,
			'difference_cost' => $diffCost,
			'status' => $status,
			'message' => $status === 'completed' ? 'Conteo coincide con el sistema' : 'Diferencia de ' . ($diff > 0 ? '+' : '') . $diff . ' u. ($' . number_format($diffCost, 2) . '), pendiente de aprobación'
		];
	}

	/**
	 * Programa conteos para hoy (p. ej. sospecha de stock fantasma) en la ubicación de la sucursal con más stock;
	 * omite productos que ya tienen un conteo pendiente en la sucursal.
	 */
	public function ScheduleForProducts(int $branchId, array $productIds, string $note, int $userId): array
	{
		if ($branchId <= 0 || count($productIds) === 0)
		{
			throw new \Exception('Seleccione sucursal y productos');
		}
		$db = DatabaseService::GetInstance()->GetDbConnectionRaw();
		$loc = $db->prepare('SELECT s.location_id, SUM(s.amount) AS stock FROM stock s JOIN locations l ON l.id = s.location_id WHERE s.product_id = :p AND l.branch_id = :b AND IFNULL(l.is_transit, 0) = 0 GROUP BY s.location_id ORDER BY stock DESC LIMIT 1');
		$pending = $db->prepare("SELECT COUNT(*) FROM stock_cycle_counts WHERE product_id = :p AND branch_id = :b AND status = 'pending'");
		$insert = $db->prepare("INSERT INTO stock_cycle_counts (branch_id, product_id, location_id, scheduled_date, status, theoretical_stock, assigned_user_id, notes) VALUES (:b, :p, :l, :d, 'pending', :t, :u, :n)");
		$scheduled = $skipped = 0;
		foreach (array_unique(array_map('intval', $productIds)) as $pid)
		{
			$pending->execute([':p' => $pid, ':b' => $branchId]);
			$loc->execute([':p' => $pid, ':b' => $branchId]);
			$l = $loc->fetch(\PDO::FETCH_ASSOC);
			if (intval($pending->fetchColumn()) > 0 || !$l)
			{
				$skipped++;
				continue;
			}
			$insert->execute([':b' => $branchId, ':p' => $pid, ':l' => $l['location_id'], ':d' => date('Y-m-d'), ':t' => $l['stock'], ':u' => $userId, ':n' => $note]);
			$scheduled++;
		}
		return ['scheduled' => $scheduled, 'skipped' => $skipped, 'message' => "$scheduled conteo(s) ciego(s) programado(s)" . ($skipped ? " · $skipped ya tenían conteo pendiente o sin stock" : '')];
	}

	public function PlanByAbc(int $branchId, bool $dryRun, int $userId): array
	{
		if ($branchId <= 0)
		{
			throw new \Exception('Seleccione una sucursal');
		}
		$db = DatabaseService::GetInstance()->GetDbConnectionRaw();
		$today = date('Y-m-d');
		$since = date('Y-m-d', strtotime('-90 days'));

		// Una fila por producto: la ubicación de la sucursal con más stock (el ajuste se contabiliza ahí)
		$stmt = $db->prepare('SELECT x.product_id, p.name, x.location_id, x.stock FROM (SELECT s.product_id, s.location_id, SUM(s.amount) AS stock, ROW_NUMBER() OVER (PARTITION BY s.product_id ORDER BY SUM(s.amount) DESC) AS rn FROM stock s JOIN locations l ON l.id = s.location_id WHERE l.branch_id = :b GROUP BY s.product_id, s.location_id HAVING SUM(s.amount) > 0) x JOIN products p ON p.id = x.product_id WHERE x.rn = 1');
		$stmt->execute([':b' => $branchId]);
		$products = $stmt->fetchAll(\PDO::FETCH_OBJ);

		$stmt = $db->prepare("SELECT si.product_id, SUM(si.subtotal) FROM sale_items si JOIN sales s ON s.id = si.sale_id WHERE s.status = 'completed' AND s.branch_id = :b AND date(s.created_timestamp) >= :d GROUP BY si.product_id");
		$stmt->execute([':b' => $branchId, ':d' => $since]);
		$revenue = $stmt->fetchAll(\PDO::FETCH_KEY_PAIR);

		$stmt = $db->prepare("SELECT product_id, MAX(COALESCE(executed_date, scheduled_date)) AS last_date, SUM(status = 'pending') AS pending FROM stock_cycle_counts WHERE branch_id = :b AND status != 'cancelled' GROUP BY product_id");
		$stmt->execute([':b' => $branchId]);
		$history = [];
		foreach ($stmt->fetchAll(\PDO::FETCH_OBJ) as $h)
		{
			$history[$h->product_id] = $h;
		}

		usort($products, fn($a, $b) => floatval($revenue[$b->product_id] ?? 0) <=> floatval($revenue[$a->product_id] ?? 0));
		$total = array_sum(array_map('floatval', $revenue));
		$interval = ['A' => GROCY_CYCLE_COUNT_DAYS_A, 'B' => GROCY_CYCLE_COUNT_DAYS_B, 'C' => GROCY_CYCLE_COUNT_DAYS_C];

		$cumulative = 0.0;
		$due = [];
		$summary = ['A' => 0, 'B' => 0, 'C' => 0];
		foreach ($products as $p)
		{
			$rev = floatval($revenue[$p->product_id] ?? 0);
			$cumulative += $rev;
			$cumPct = $total > 0 ? $cumulative / $total * 100 : 100;
			$class = $rev <= 0 ? 'C' : ($cumPct <= 80 ? 'A' : ($cumPct <= 95 ? 'B' : 'C'));
			$summary[$class]++;

			$h = $history[$p->product_id] ?? null;
			if ($h && intval($h->pending) > 0)
			{
				continue;
			}
			$nextDate = $h ? date('Y-m-d', strtotime($h->last_date . ' +' . intval($interval[$class]) . ' days')) : $today;
			if ($nextDate > $today)
			{
				continue;
			}
			$due[] = ['product_id' => intval($p->product_id), 'product_name' => $p->name, 'location_id' => intval($p->location_id), 'abc_class' => $class, 'stock' => floatval($p->stock), 'last_count' => $h->last_date ?? null, 'revenue_90d' => round($rev, 2)];
		}

		if (!$dryRun && count($due) > 0)
		{
			$insert = $db->prepare("INSERT INTO stock_cycle_counts (branch_id, product_id, location_id, scheduled_date, status, theoretical_stock, assigned_user_id, notes) VALUES (:b, :p, :l, :d, 'pending', :t, :u, :n)");
			$db->beginTransaction();
			foreach ($due as $d)
			{
				$insert->execute([':b' => $branchId, ':p' => $d['product_id'], ':l' => $d['location_id'], ':d' => $today, ':t' => $d['stock'], ':u' => $userId, ':n' => 'Automático ABC: clase ' . $d['abc_class']]);
			}
			$db->commit();
		}

		return ['branch_id' => $branchId, 'dry_run' => $dryRun, 'classes' => $summary, 'interval_days' => $interval, 'due' => $due, 'scheduled' => $dryRun ? 0 : count($due)];
	}
}
