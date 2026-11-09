<?php

namespace Grocy\Services;

use Grocy\Helpers\DailyFilters;

/**
 * Productos agotados por sucursal, con la misma regla que HOY y el Panel (DailyMetricsService::StockStatusCounts):
 *  - Surtido de la sucursal: producto activo, no serializado, con stock mínimo > 0 (el mínimo declara que la tienda lo maneja).
 *  - Agotado: existencia física en ubicaciones de la sucursal (sin tránsito) ≤ 0.
 *  - Sin disponible para venta: hay existencia física, pero cuarentena + vencido + merma pendiente + reservas la consumen
 *    (misma fórmula de disponible que ScanService::Availability).
 * Con búsqueda de producto/SKU se muestran también los productos con disponible, para responder «¿hay o no hay?».
 */
class StockoutService extends BaseService
{
	const STATES = ['out' => 'Agotado', 'blocked' => 'Sin disponible para venta', 'low' => 'Bajo mínimo', 'ok' => 'Con disponible'];

	private function Db(): \PDO
	{
		return DatabaseService::GetInstance()->GetDbConnectionRaw();
	}

	private function ByBranchProduct(string $sql): array
	{
		$out = [];
		foreach ($this->Db()->query($sql)->fetchAll(\PDO::FETCH_ASSOC) as $r)
		{
			$out[$r['b'] . '|' . $r['p']] = floatval($r['v']);
		}
		return $out;
	}

	public function List(array $f): array
	{
		$db = $this->Db();
		$branchIds = DailyFilters::BranchIds($f) ?? array_map('intval', $db->query('SELECT id FROM branches WHERE IFNULL(active, 1) = 1')->fetchAll(\PDO::FETCH_COLUMN));
		$in = implode(',', $branchIds ?: [0]);
		$searching = $f['product'] !== null;
		$products = $db->query('SELECT p.id, p.name, p.sku, p.department, p.min_stock_amount, g.name AS category FROM products p LEFT JOIN product_groups g ON g.id = p.product_group_id
			WHERE p.active = 1 AND IFNULL(p.is_serialized, 0) = 0 AND ' . ($searching ? '1 = 1' : 'p.min_stock_amount > 0') . ' AND ' . DailyFilters::ProductSql($f))->fetchAll(\PDO::FETCH_ASSOC);
		if (!$products)
		{
			return ['rows' => [], 'counts' => ['out' => 0, 'blocked' => 0], 'branches' => count($branchIds), 'searching' => $searching];
		}
		$pin = implode(',', array_map(fn($p) => intval($p['id']), $products));
		$today = date('Y-m-d');
		$loc = "FROM stock s JOIN locations l ON l.id = s.location_id WHERE IFNULL(l.is_transit, 0) = 0 AND l.branch_id IN ($in) AND s.product_id IN ($pin)";
		$physical = $this->ByBranchProduct("SELECT l.branch_id AS b, s.product_id AS p, SUM(s.amount) AS v $loc GROUP BY 1, 2");
		$expired = $this->ByBranchProduct("SELECT l.branch_id AS b, s.product_id AS p, SUM(s.amount) AS v $loc AND s.best_before_date < '$today' AND s.best_before_date <> '2999-12-31' GROUP BY 1, 2");
		$quarantine = $this->ByBranchProduct("SELECT branch_id AS b, product_id AS p, SUM(amount) AS v FROM quarantine_items WHERE status = 'in_quarantine' AND branch_id IN ($in) AND product_id IN ($pin) GROUP BY 1, 2");
		$waste = $this->ByBranchProduct("SELECT branch_id AS b, product_id AS p, SUM(amount) AS v FROM waste_records WHERE status = 'pending_approval' AND IFNULL(inventory_deducted, 0) = 0 AND branch_id IN ($in) AND product_id IN ($pin) GROUP BY 1, 2");
		$reserved = $this->ByBranchProduct("SELECT o.branch_id AS b, i.product_id AS p, SUM(CASE WHEN o.status IN ('picked', 'packed', 'dispatching') THEN i.picked_amount ELSE i.amount END) AS v
			FROM customer_order_items i JOIN customer_orders o ON o.id = i.order_id WHERE o.status IN ('" . implode("', '", CustomerOrderService::RESERVING) . "') AND o.branch_id IN ($in) AND i.product_id IN ($pin) GROUP BY 1, 2");
		$lastMove = [];
		foreach ($db->query("SELECT l.branch_id AS b, sl.product_id AS p, MAX(sl.row_created_timestamp) AS ts, sl.transaction_type AS t FROM stock_log sl JOIN locations l ON l.id = sl.location_id
			WHERE l.branch_id IN ($in) AND sl.product_id IN ($pin) AND IFNULL(sl.undone, 0) = 0 GROUP BY 1, 2")->fetchAll(\PDO::FETCH_ASSOC) as $r)
		{
			$lastMove[$r['b'] . '|' . $r['p']] = ['ts' => $r['ts'], 'type' => $r['t']];
		}
		// Reposición en curso: orden de compra abierta de la sucursal con el producto, o traslado en tránsito hacia ella
		$po = [];
		foreach ($db->query("SELECT o.branch_id AS b, i.product_id AS p, o.order_code, o.status, o.expected_delivery_date, IFNULL(i.approved_amount, i.suggested_amount) AS qty
			FROM procurement_order_items i JOIN procurement_orders o ON o.id = i.order_id WHERE o.status IN ('draft', 'approved', 'sent') AND o.branch_id IN ($in) AND i.product_id IN ($pin)
			ORDER BY o.expected_delivery_date")->fetchAll(\PDO::FETCH_ASSOC) as $r)
		{
			$po[$r['b'] . '|' . $r['p']] = $po[$r['b'] . '|' . $r['p']] ?? $r;
		}
		$transfers = [];
		try
		{
			foreach ($db->query("SELECT t.to_branch_id AS b, i.product_id AS p, t.transfer_code AS code, t.status FROM stock_transfers t JOIN stock_transfer_items i ON i.transfer_id = t.id
				WHERE t.status IN ('in_transit', 'sent', 'dispatched') AND t.to_branch_id IN ($in) AND i.product_id IN ($pin)")->fetchAll(\PDO::FETCH_ASSOC) as $r)
			{
				$transfers[$r['b'] . '|' . $r['p']] = $r;
			}
		}
		catch (\Throwable $e)
		{
			// Sin módulo de traslados con ese esquema: la reposición se informa solo con órdenes de compra
		}
		$branchNames = $db->query("SELECT id, name FROM branches WHERE id IN ($in)")->fetchAll(\PDO::FETCH_KEY_PAIR);
		$poLabels = ['draft' => 'borrador', 'approved' => 'aprobada', 'sent' => 'enviada al proveedor'];

		$rows = [];
		$counts = ['out' => 0, 'blocked' => 0];
		foreach ($branchIds as $b)
		{
			foreach ($products as $p)
			{
				$key = $b . '|' . $p['id'];
				$phys = round($physical[$key] ?? 0.0, 2);
				$avail = max(0, round($phys - ($quarantine[$key] ?? 0) - ($expired[$key] ?? 0) - ($waste[$key] ?? 0) - ($reserved[$key] ?? 0), 2));
				$min = floatval($p['min_stock_amount']);
				$state = $phys <= 0 ? 'out' : ($avail <= 0 ? 'blocked' : ($min > 0 && $phys < $min ? 'low' : 'ok'));
				if (isset($counts[$state]))
				{
					$counts[$state]++;
				}
				if (!$searching && !isset($counts[$state]))
				{
					continue;
				}
				if ($searching && $phys <= 0 && !isset($physical[$key]) && !isset($lastMove[$key]) && $min <= 0)
				{
					continue; // producto que la sucursal nunca manejó
				}
				$repl = isset($po[$key]) ? 'Orden ' . $po[$key]['order_code'] . ' ' . ($poLabels[$po[$key]['status']] ?? $po[$key]['status']) . ($po[$key]['expected_delivery_date'] ? ', entrega ' . date('d/m/Y', strtotime($po[$key]['expected_delivery_date'])) : '')
					: (isset($transfers[$key]) ? 'Traslado ' . $transfers[$key]['code'] . ' en tránsito' : null);
				$rows[] = [
					'branch_id' => $b, 'branch' => $branchNames[$b] ?? ('Sucursal ' . $b), 'product_id' => intval($p['id']), 'name' => $p['name'], 'sku' => $p['sku'],
					'department' => $p['department'] ?: 'Sin departamento', 'category' => trim(preg_replace('/^\d{2}\s+/', '', (string) $p['category'])) ?: 'Sin categoría',
					'physical' => $phys, 'available' => $avail, 'min' => $min, 'state' => $state, 'state_label' => self::STATES[$state],
					'last_move' => $lastMove[$key]['ts'] ?? null, 'last_move_type' => $lastMove[$key]['type'] ?? null,
					'replenishment' => $repl, 'replenishment_state' => $repl ? 'en_curso' : ($state === 'ok' ? 'no_requerida' : 'sin_reposicion')
				];
			}
		}
		usort($rows, fn($a, $b) => [array_search($a['state'], array_keys(self::STATES)), $a['branch'], $a['name']] <=> [array_search($b['state'], array_keys(self::STATES)), $b['branch'], $b['name']]);
		return ['rows' => $rows, 'counts' => $counts, 'branches' => count($branchIds), 'searching' => $searching];
	}
}
