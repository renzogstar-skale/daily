<?php

namespace Grocy\Services;

/**
 * What-if: rebaja FEFO vs. no intervenir para un lote próximo a vencer. Solo lee (nunca escribe).
 * Hechos: precio de venta del producto, costo real de sus lotes (o última compra), venta diaria de 28 días, vencimiento.
 * Supuestos (los elige el usuario y se devuelven explícitos): % del lote que se vende con la rebaja.
 * Sin precio de venta o sin costo registrado no se simula (antes se usaban 3,99 y 65 % del precio: datos inventados).
 */
class SimulatorService extends BaseService
{
	public function SimulateWasteVsMarkdown(int $productId, float $lotAmount, float $markdownDiscountPct = 0.30, ?float $sellThroughPct = null, ?int $daysLeft = null, ?int $branchId = null)
	{
		$db = DatabaseService::GetInstance()->GetDbConnectionRaw();
		if ($lotAmount <= 0 || $lotAmount > 100000)
		{
			throw new \Exception('Cantidad del lote fuera de rango');
		}
		if ($markdownDiscountPct < 0 || $markdownDiscountPct >= 1)
		{
			throw new \Exception('Descuento fuera de rango (0–99 %)');
		}
		$sellThrough = $sellThroughPct ?? 0.85;
		if ($sellThrough <= 0 || $sellThrough > 1)
		{
			throw new \Exception('El % de venta esperado con rebaja debe estar entre 1 y 100');
		}

		$st = $db->prepare('SELECT p.id, p.name, p.sale_price, pg.name AS category_name, qu.name AS qu_name FROM products p LEFT JOIN product_groups pg ON p.product_group_id = pg.id LEFT JOIN quantity_units qu ON p.qu_id_stock = qu.id WHERE p.id = :id');
		$st->execute([':id' => $productId]);
		$product = $st->fetch(\PDO::FETCH_OBJ);
		if (!$product)
		{
			throw new \Exception("Producto #{$productId} no encontrado");
		}
		$salePrice = floatval($product->sale_price);
		if ($salePrice <= 0)
		{
			throw new \Exception("«{$product->name}» no tiene precio de venta registrado: cárguelo en el producto para simular");
		}

		$bFilter = $branchId ? ' AND s.location_id IN (SELECT id FROM locations WHERE branch_id = ' . intval($branchId) . ')' : '';
		$lot = $db->query("SELECT SUM(s.amount) AS qty, SUM(s.amount * IFNULL(s.price, 0)) AS val, SUM(CASE WHEN IFNULL(s.price, 0) > 0 THEN s.amount ELSE 0 END) AS priced, MIN(CASE WHEN s.best_before_date != '2999-12-31' THEN s.best_before_date END) AS bbd FROM stock s WHERE s.product_id = " . intval($productId) . $bFilter)->fetch(\PDO::FETCH_ASSOC);
		$costSource = 'stock (costo promedio de los lotes actuales)';
		$costUnit = floatval($lot['priced'] ?? 0) > 0 ? floatval($lot['val']) / floatval($lot['priced']) : 0.0;
		if ($costUnit <= 0)
		{
			$costUnit = floatval($db->query("SELECT price FROM stock_log WHERE product_id = " . intval($productId) . " AND transaction_type = 'purchase' AND undone = 0 AND IFNULL(price, 0) > 0 ORDER BY id DESC LIMIT 1")->fetchColumn());
			$costSource = 'stock_log (última compra)';
		}
		if ($costUnit <= 0)
		{
			$costUnit = floatval($db->query("SELECT SUM(total_cost) / SUM(amount) FROM (SELECT total_cost, amount FROM sale_items WHERE product_id = " . intval($productId) . " AND IFNULL(unit_cost, 0) > 0 AND amount > 0 ORDER BY id DESC LIMIT 20)")->fetchColumn());
			$costSource = 'sale_items (costo de las últimas ventas)';
		}
		if ($costUnit <= 0)
		{
			throw new \Exception("«{$product->name}» no tiene costo registrado (ni en lotes, compras ni ventas): no se puede valorizar la merma");
		}
		$costUnit = round($costUnit, 4);

		if ($daysLeft === null)
		{
			$daysLeft = !empty($lot['bbd']) ? max(0, (int) round((strtotime($lot['bbd']) - strtotime(date('Y-m-d'))) / 86400)) : 3;
		}
		$daysLeft = max(0, min(365, $daysLeft));

		$sb = $branchId ? ' AND s.branch_id = ' . intval($branchId) : '';
		$units28 = floatval($db->query("SELECT IFNULL(SUM(i.amount), 0) FROM sale_items i JOIN sales s ON s.id = i.sale_id WHERE i.product_id = " . intval($productId) . " AND s.status = 'completed' AND date(s.created_timestamp) >= date('now', 'localtime', '-28 days')" . $sb)->fetchColumn());
		$vmd = round($units28 / 28, 3);

		// A: sin intervención → vende a su ritmo histórico hasta el vencimiento; el resto se descarta a costo
		$soldA = round(min($lotAmount, $vmd * $daysLeft), 2);
		$wasteA = round($lotAmount - $soldA, 2);
		$revenueA = round($soldA * $salePrice, 2);
		$lossA = round($wasteA * $costUnit, 2);

		// B: rebaja → se vende lo mayor entre el ritmo histórico y el % esperado con rebaja (supuesto del usuario)
		$discounted = round($salePrice * (1 - $markdownDiscountPct), 2);
		$soldB = round(max($soldA, min($lotAmount, $lotAmount * $sellThrough)), 2);
		$wasteB = round($lotAmount - $soldB, 2);
		$revenueB = round($soldB * $discounted, 2);
		$lossB = round($wasteB * $costUnit, 2);

		$lossAvoided = round($lossA - $lossB, 2);
		$netA = round($revenueA - $lotAmount * $costUnit, 2);
		$netB = round($revenueB - $lotAmount * $costUnit, 2);
		$delta = round($netB - $netA, 2);
		$lotCost = round($lotAmount * $costUnit, 2);

		return [
			'product' => [
				'id' => $product->id, 'name' => $product->name, 'regular_price' => $salePrice, 'cost_unit' => $costUnit,
				'category' => $product->category_name ?? 'Sin categoría', 'lot_amount' => $lotAmount, 'qu_name' => $product->qu_name ?? 'u',
				'daily_sales' => $vmd, 'days_left' => $daysLeft
			],
			'scenario_a_waste' => [
				'title' => 'Escenario A: sin intervención (vende a su ritmo, el resto vence)',
				'sold_units' => $soldA, 'discarded_units' => $wasteA, 'revenue_recovered' => $revenueA, 'total_cogs_lost' => $lossA,
				'net_impact' => $netA, 'recovery_rate_pct' => $lotCost > 0 ? round($revenueA / $lotCost * 100, 1) : 0.0
			],
			'scenario_b_markdown' => [
				'title' => 'Escenario B: rebaja FEFO (-' . round($markdownDiscountPct * 100) . ' %)',
				'discounted_price' => $discounted, 'sold_units' => $soldB, 'discarded_units' => $wasteB, 'revenue_recovered' => $revenueB,
				'total_cogs_lost' => $lossB, 'net_impact' => $netB, 'cash_protected' => $revenueB, 'loss_avoided' => $lossAvoided,
				'recovery_rate_pct' => $lotCost > 0 ? round($revenueB / $lotCost * 100, 1) : 0.0
			],
			'financial_roi' => [
				'loss_avoided_usd' => $lossAvoided,
				'net_delta_usd' => $delta,
				'cash_flow_improvement_usd' => round($revenueB - $revenueA, 2),
				'recommendation' => $delta > 0
					? "Rebajar conviene: el resultado mejora \${$delta} y se evitan \${$lossAvoided} de merma a costo. Requiere autorización de la rebaja en POS/Calidad."
					: ($soldA >= $lotAmount ? 'No rebajar: al ritmo de venta actual el lote se vende completo antes de vencer.' : "No rebajar con este descuento: el resultado empeora \$" . abs($delta) . '. Pruebe un descuento menor o traslado.')
			],
			'facts' => [
				"Precio de venta: \${$salePrice} (products.sale_price)",
				"Costo unitario: \${$costUnit} ({$costSource})",
				"Venta diaria de 28 días: {$vmd} u. (sale_items)",
				"Días para vencer: {$daysLeft}" . (!empty($lot['bbd']) ? " (lote más próximo {$lot['bbd']})" : ' (sin fecha en stock: valor ingresado o 3 por defecto)'),
			],
			'assumptions' => [
				'Con la rebaja se vende el ' . round($sellThrough * 100) . ' % del lote' . ($sellThroughPct === null ? ' (valor por defecto; ajústelo con su experiencia)' : ' (valor ingresado)') . '.',
				'Lo no vendido antes del vencimiento se descarta a costo; no incluye recuperación de proveedor.',
			],
			'writes' => false
		];
	}
}
