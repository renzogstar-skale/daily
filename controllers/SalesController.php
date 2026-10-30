<?php

namespace Grocy\Controllers;

use Grocy\Helpers\DairyAccess;
use Grocy\Services\BranchService;
use Grocy\Services\BudgetService;
use Grocy\Services\DatabaseService;
use Grocy\Services\LossPreventionService;
use Grocy\Services\MarkdownService;
use Grocy\Services\MobileManagerService;
use Grocy\Services\SalesService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class SalesController extends BaseController
{
	public function Pos(Request $request, Response $response, array $args)
	{
		$branchService = BranchService::GetInstance();
		$salesService = SalesService::GetInstance();
		$salesService->EnsurePosTables();

		$branches = DairyAccess::FilterBranches($branchService->GetBranches());
		$queryParams = $request->getQueryParams();
		$firstBranchId = !empty($branches) ? intval(is_object($branches[0]) ? $branches[0]->id : $branches[0]['id']) : 1;
		$selectedBranchId = isset($queryParams['branch_id']) && intval($queryParams['branch_id']) > 0
			? intval($queryParams['branch_id'])
			: $firstBranchId;

		$dbRaw = DatabaseService::GetInstance()->GetDbConnectionRaw();
		$markupFactor = 1.0 + (floatval(GROCY_SALE_PRICE_DEFAULT_MARKUP_PCT) / 100.0);

		// Productos activos con respaldo de costo desde cache__products_last_purchased o lotes en stock
		$sql = "
			SELECT 
				p.id,
				p.name,
				p.sku,
				p.qu_id_stock,
				IFNULL(p.min_stock_amount, 0) AS min_stock_amount,
				qu.name AS qu_name,
				IFNULL(pg.name, 'General') AS category_name,
				COALESCE(NULLIF(plp.price, 0), NULLIF(lot_cost.max_price, 0), 0) AS cost_price,
				COALESCE(
					NULLIF(p.sale_price, 0),
					ROUND(COALESCE(NULLIF(plp.price, 0), NULLIF(lot_cost.max_price, 0), 1.0) * {$markupFactor}, 2)
				) AS default_sale_price,
				IFNULL(cur.amount, 0) AS stock_amount,
				cur.next_bbd,
				IFNULL(cur.lots_count, 0) AS fefo_lots_count,
				p.picture_file_name
			FROM products p
			JOIN quantity_units qu ON p.qu_id_stock = qu.id
			LEFT JOIN product_groups pg ON p.product_group_id = pg.id
			LEFT JOIN cache__products_last_purchased plp ON p.id = plp.product_id
			LEFT JOIN (
				SELECT product_id, MAX(price) AS max_price
				FROM stock
				WHERE IFNULL(price, 0) > 0
				GROUP BY product_id
			) lot_cost ON p.id = lot_cost.product_id
			LEFT JOIN (
				SELECT
					s.product_id,
					SUM(s.amount) AS amount,
					MIN(CASE WHEN s.best_before_date IS NOT NULL AND s.best_before_date != '2999-12-31' THEN s.best_before_date ELSE NULL END) AS next_bbd,
					COUNT(DISTINCT CASE WHEN s.best_before_date IS NOT NULL AND s.best_before_date != '2999-12-31' THEN s.stock_id ELSE NULL END) AS lots_count
				FROM stock s
				JOIN locations l ON l.id = s.location_id
				WHERE IFNULL(l.is_transit, 0) = 0 AND s.amount > 0
				GROUP BY s.product_id
			) cur ON p.id = cur.product_id
			WHERE p.active = 1
			ORDER BY CASE WHEN IFNULL(cur.amount, 0) > 0 THEN 0 ELSE 1 END, p.name ASC
		";
		$stmt = $dbRaw->query($sql);
		$products = $stmt->fetchAll(\PDO::FETCH_OBJ);

		// Existencias vendibles por sucursal y producto (descontando cuarentena activa)
		$stmtBranchStock = $dbRaw->query("
			SELECT
				s.product_id,
				l.branch_id,
				SUM(s.amount) AS amount,
				MIN(CASE WHEN s.best_before_date IS NOT NULL AND s.best_before_date != '2999-12-31' THEN s.best_before_date ELSE NULL END) AS next_bbd,
				COUNT(DISTINCT CASE WHEN s.best_before_date IS NOT NULL AND s.best_before_date != '2999-12-31' THEN s.stock_id ELSE NULL END) AS lots_count
			FROM stock s
			JOIN locations l ON l.id = s.location_id
			WHERE IFNULL(l.is_transit, 0) = 0 AND s.amount > 0
			GROUP BY s.product_id, l.branch_id
		");
		$branchStockMap = [];
		$branchBbdMap = [];
		$branchLotsMap = [];
		foreach ($stmtBranchStock->fetchAll(\PDO::FETCH_OBJ) as $bs)
		{
			$pid = intval($bs->product_id);
			$bid = intval($bs->branch_id);
			$branchStockMap[$pid][$bid] = round(floatval($bs->amount), 2);
			$branchBbdMap[$pid][$bid] = $bs->next_bbd;
			$branchLotsMap[$pid][$bid] = intval($bs->lots_count);
		}

		// Descontar unidades en cuarentena activa por sucursal
		$stmtQuar = $dbRaw->query("
			SELECT product_id, branch_id, SUM(amount) AS q_amount
			FROM quarantine_items
			WHERE status = 'in_quarantine'
			GROUP BY product_id, branch_id
		");
		foreach ($stmtQuar->fetchAll(\PDO::FETCH_OBJ) as $qr)
		{
			$pid = intval($qr->product_id);
			$bid = intval($qr->branch_id);
			if (isset($branchStockMap[$pid][$bid]))
			{
				$branchStockMap[$pid][$bid] = max(0.0, round($branchStockMap[$pid][$bid] - floatval($qr->q_amount), 2));
			}
		}

		// Códigos de barras por producto para búsqueda y lector tipo teclado
		$stmtBarcodes = $dbRaw->query("SELECT product_id, barcode FROM product_barcodes WHERE IFNULL(barcode, '') != ''");
		$barcodesByProduct = [];
		foreach ($stmtBarcodes->fetchAll(\PDO::FETCH_OBJ) as $bc)
		{
			$pid = intval($bc->product_id);
			$barcodesByProduct[$pid][] = trim((string)$bc->barcode);
		}

		$markdowns = MarkdownService::GetInstance()->GetActiveMarkdowns();
		$categoriesSet = [];

		foreach ($products as $prod)
		{
			$pid = intval($prod->id);
			$prod->branch_stocks = $branchStockMap[$pid] ?? [];
			$prod->branch_bbds = $branchBbdMap[$pid] ?? [];
			$prod->branch_lots = $branchLotsMap[$pid] ?? [];
			$prod->barcodes = $barcodesByProduct[$pid] ?? [];
			$prod->barcodes_str = implode(' ', $prod->barcodes);
			$prod->image_url = $this->ResolvePosProductImage((string)$prod->name, (string)$prod->category_name);
			$prod->has_fefo = !empty($prod->next_bbd) && intval($prod->fefo_lots_count) > 0;
			$prod->markdown_pct = isset($markdowns[$pid]) ? floatval($markdowns[$pid]['discount_pct']) : 0.0;
			$prod->effective_price = $prod->markdown_pct > 0
				? round(floatval($prod->default_sale_price) * (1.0 - ($prod->markdown_pct / 100.0)), 2)
				: round(floatval($prod->default_sale_price), 2);

			$cat = trim((string)$prod->category_name) ?: 'General';
			$categoriesSet[$cat] = ($categoriesSet[$cat] ?? 0) + 1;
		}

		$customers = $this->DB->customers()->where('active = 1')->orderBy('id')->fetchAll();
		$currentShift = $salesService->GetCurrentShift($selectedBranchId);
		$heldSales = $salesService->GetHeldSales($selectedBranchId);
		$recentSales = $salesService->GetSales($selectedBranchId, null, null, 25);

		return $this->RenderPage($response, 'pos', [
			'products' => $products,
			'categories' => array_keys($categoriesSet),
			'branches' => $branches,
			'selectedBranchId' => $selectedBranchId,
			'customers' => $customers,
			'markdowns' => $markdowns,
			'currentShift' => $currentShift,
			'heldSales' => $heldSales,
			'recentSales' => $recentSales
		]);
	}

	private function ResolvePosProductImage(string $productName, string $categoryName): string
	{
		$n = mb_strtolower($productName, 'UTF-8');
		$c = mb_strtolower($categoryName, 'UTF-8');

		if (strpos($n, 'leche entera') !== false) return '/img/control_center/leche_entera_galon.jpg';
		if (strpos($n, 'leche descremada') !== false || strpos($n, 'deslactosada') !== false) return '/img/control_center/leche_descremada.jpg';
		if (strpos($n, 'leche') !== false) return '/img/control_center/leche_entera_1l.jpg';
		if (strpos($n, 'yogur') !== false || strpos($n, 'kefir') !== false || strpos($n, 'probi') !== false) return '/img/control_center/yogur_griego.jpg';
		if (strpos($n, 'mozzarella') !== false) return '/img/control_center/queso_mozzarella.jpg';
		if (strpos($n, 'cheddar') !== false) return '/img/control_center/queso_cheddar.jpg';
		if (strpos($n, 'parmesano') !== false) return '/img/control_center/queso_parmesano.jpg';
		if (strpos($n, 'cottage') !== false || strpos($n, 'untable') !== false || strpos($n, 'queso') !== false) return '/img/control_center/queso_cottage.jpg';
		if (strpos($n, 'huevo') !== false) return '/img/control_center/huevos_grandes.jpg';
		if (strpos($n, 'mantequilla') !== false) return '/img/control_center/mantequilla_cremas.jpg';
		if (strpos($n, 'crema') !== false) return '/img/control_center/crema_de_leche.jpg';
		if (strpos($n, 'prote') !== false || strpos($n, 'bebida') !== false || strpos($n, 'jugo') !== false) return '/img/control_center/bebida_proteica.jpg';
		if (strpos($n, 'tomate') !== false) return '/img/control_center/tomate.jpg';
		if (strpos($n, 'pepino') !== false) return '/img/control_center/pepino.jpg';
		if (strpos($n, 'piment') !== false) return '/img/control_center/pimenton.jpg';
		if (strpos($n, 'rábano') !== false || strpos($n, 'rabano') !== false || strpos($n, 'verdura') !== false) return '/img/control_center/verduras_frutas.jpg';
		if (strpos($n, 'pollo') !== false || strpos($n, 'pechuga') !== false) return '/img/control_center/pechuga_pollo.jpg';
		if (strpos($n, 'res') !== false || strpos($n, 'lomo') !== false || strpos($n, 'carne') !== false) return '/img/control_center/carne_picada.jpg';
		if (strpos($n, 'phone') !== false || strpos($n, 'scan') !== false || strpos($c, 'electr') !== false) return '/img/control_center/daily_scan_phone.jpg';

		if (strpos($c, 'lácteos') !== false || strpos($c, 'leche') !== false) return '/img/control_center/lacteos_leches.jpg';
		if (strpos($c, 'queso') !== false) return '/img/control_center/quesos_untables.jpg';
		if (strpos($c, 'yogur') !== false) return '/img/control_center/yogures_probioticos.jpg';
		if (strpos($c, 'verdura') !== false || strpos($c, 'fruta') !== false) return '/img/control_center/verduras_frutas.jpg';
		if (strpos($c, 'carne') !== false || strpos($c, 'carnicer') !== false) return '/img/control_center/productos_carniceria.jpg';
		if (strpos($c, 'huevo') !== false) return '/img/control_center/huevos_granja.jpg';
		if (strpos($c, 'mantequilla') !== false || strpos($c, 'crema') !== false) return '/img/control_center/mantequilla_cremas.jpg';
		if (strpos($c, 'bebida') !== false) return '/img/control_center/bebidas_refrigeradas.jpg';

		return '/img/control_center/lacteos_leches.jpg';
	}

	public function SalesHistory(Request $request, Response $response, array $args)
	{
		$params = $request->getQueryParams();
		$branchId = isset($params['branch_id']) && $params['branch_id'] !== '' ? intval($params['branch_id']) : null;
		$groupId = isset($params['group_id']) && $params['group_id'] !== '' ? intval($params['group_id']) : null;
		$productId = isset($params['product_id']) && $params['product_id'] !== '' ? intval($params['product_id']) : null;
		$activeTab = $params['tab'] ?? 'resumen'; // 'resumen', 'analisis', 'detalle'
		$compareWith = isset($params['compare_with']) && in_array($params['compare_with'], ['prev_period', 'year_ago', 'target'], true)
			? $params['compare_with']
			: 'prev_period';

		// Un solo período para transacciones, análisis y conciliación (por defecto, últimos 30 días)
		$isDate = fn($v) => is_string($v) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $v);
		$endDate = $isDate($params['end_date'] ?? null) ? $params['end_date'] : date('Y-m-d');
		$startDate = $isDate($params['start_date'] ?? null) ? $params['start_date'] : date('Y-m-d', strtotime($endDate . ' -29 days'));
		if ($startDate > $endDate)
		{
			[$startDate, $endDate] = [$endDate, $startDate];
		}

		$salesService = SalesService::GetInstance();
		$branchService = BranchService::GetInstance();
		$db = DatabaseService::GetInstance()->GetDbConnectionRaw();

		$cleanLabel = function (?string $txt): string {
			$s = trim(preg_replace('/^\[DEMO\]\s*/i', '', (string)$txt));
			$s = preg_replace('/^\d{2}\s+/', '', $s);
			if ($s === 'Verduras/Frutas')
			{
				return 'Verduras y Frutas';
			}
			return $s;
		};

		$rawBranchesList = DairyAccess::FilterBranches($branchService->GetBranches());
		$branches = [];
		foreach ($rawBranchesList as $bItem)
		{
			$bObj = is_object($bItem) ? clone $bItem : (object)$bItem;
			$bObj->name = $cleanLabel($bObj->name ?? '');
			$branches[] = $bObj;
		}

		$rawProductGroups = $db->query("SELECT id, name FROM product_groups ORDER BY name")->fetchAll(\PDO::FETCH_ASSOC);
		$productGroups = [];
		foreach ($rawProductGroups as $pgRow)
		{
			$productGroups[] = [
				'id' => intval($pgRow['id']),
				'name' => $cleanLabel($pgRow['name']),
				'raw_name' => $pgRow['name']
			];
		}

		// Datos maestros seleccionados para la navegación en cascada y breadcrumbs
		$selectedBranch = null;
		if ($branchId)
		{
			foreach ($branches as $b)
			{
				$bid = is_object($b) ? $b->id : $b['id'];
				if (intval($bid) === $branchId)
				{
					$selectedBranch = is_object($b) ? $b : (object)$b;
					break;
				}
			}
		}

		$selectedGroup = null;
		if ($groupId !== null)
		{
			if ($groupId === 0)
			{
				$selectedGroup = (object)['id' => 0, 'name' => 'Sin departamento'];
			}
			else
			{
				foreach ($productGroups as $g)
				{
					if (intval($g['id']) === $groupId)
					{
						$selectedGroup = (object)$g;
						break;
					}
				}
			}
		}

		$selectedProduct = null;
		if ($productId)
		{
			$stmtProd = $db->prepare("SELECT p.id, p.name, p.sku, p.product_group_id, pg.name as group_name FROM products p LEFT JOIN product_groups pg ON pg.id = p.product_group_id WHERE p.id = :id");
			$stmtProd->execute([':id' => $productId]);
			$selectedProduct = $stmtProd->fetch(\PDO::FETCH_OBJ);
			if ($selectedProduct && !$selectedGroup && $selectedProduct->product_group_id)
			{
				$groupId = intval($selectedProduct->product_group_id);
				foreach ($productGroups as $g)
				{
					if (intval($g['id']) === $groupId)
					{
						$selectedGroup = (object)$g;
						break;
					}
				}
			}
		}

		// Recuperar transacciones filtradas según la cascada (conservando group_id === 0 para "Sin departamento")
		$sales = $salesService->GetSales($branchId ?: null, $startDate, $endDate, 500, $groupId, $productId ?: null);
		foreach ($sales as $sItem)
		{
			if (isset($sItem->branch_name))
			{
				$sItem->branch_name = $cleanLabel($sItem->branch_name);
			}
		}

		// Análisis comercial (mismo motor que /m y Panel Ejecutivo)
		$analysis = MobileManagerService::GetInstance()->Sales($branchId ?: null, $startDate, $endDate, $groupId, $productId ?: null);
		$unlinked = LossPreventionService::GetInstance()->PosReconciliation($branchId ?: null, $startDate, $endDate);

		// Termómetro comercial y metas presupuestarias
		$budgetSvc = BudgetService::GetInstance();
		$periodMonth = date('Y-m', strtotime($endDate));
		$targets = $budgetSvc->GetTargets($branchId, $periodMonth);

		$daysTotal = max(1, (int)round((strtotime($endDate) - strtotime($startDate)) / 86400) + 1);
		$daysElapsed = min($daysTotal, max(1, (int)round((strtotime(min($endDate, date('Y-m-d'))) - strtotime($startDate)) / 86400) + 1));
		$daysRemaining = max(0, $daysTotal - $daysElapsed);
		$isClosedPeriod = ($daysRemaining === 0 || $endDate < date('Y-m-d'));

		$salesNet = floatval($analysis['totals']['total'] ?? 0);
		$targetSalesMonthly = floatval($targets['sales_target'] ?? 0);
		// El presupuesto es de toda la sucursal/red: con filtro de departamento o producto se usa la referencia del período anterior
		if ($targetSalesMonthly <= 0 || $groupId !== null || $productId)
		{
			$prevTotalForGoal = floatval($analysis['prev']['total'] ?? 0);
			$targetGoal = $prevTotalForGoal > 0 ? round($prevTotalForGoal * 1.05, 0) : round(45000.0 * ($daysTotal / 30.0), 0);
			$targetSource = 'benchmark_proporcional';
		}
		else
		{
			$targetGoal = round($targetSalesMonthly * ($daysTotal / 30.0), 2);
			$targetSource = $targets['sources']['sales'] ?? 'presupuesto';
		}

		$compliancePct = $targetGoal > 0 ? round(($salesNet / $targetGoal) * 100, 1) : null;
		$pendingAmount = $targetGoal > 0 ? max(0.0, round($targetGoal - $salesNet, 2)) : 0.0;
		$avgDailySales = $daysElapsed > 0 ? ($salesNet / $daysElapsed) : 0.0;
		$dailyGoal = $daysTotal > 0 ? ($targetGoal / $daysTotal) : 0.0;
		$dailyRhythmDeltaPct = $dailyGoal > 0 ? round((($avgDailySales - $dailyGoal) / $dailyGoal) * 100, 1) : 0.0;
		// Si el período ya terminó, muestra el resultado de cierre sin proyectar ventas futuras
		$projectedClosing = $isClosedPeriod ? round($salesNet, 2) : round($salesNet + ($avgDailySales * $daysRemaining), 2);
		$projectedVariancePct = $targetGoal > 0 ? round((($projectedClosing - $targetGoal) / $targetGoal) * 100, 1) : 0.0;

		$thermometer = [
			'sales_net' => $salesNet,
			'target_goal' => $targetGoal,
			'has_target' => ($targetGoal > 0),
			'compliance_pct' => $compliancePct ?? 0.0,
			'pending_amount' => $pendingAmount,
			'days_total' => $daysTotal,
			'days_elapsed' => $daysElapsed,
			'days_remaining' => $daysRemaining,
			'is_closed' => $isClosedPeriod,
			'avg_daily_sales' => round($avgDailySales, 2),
			'daily_goal' => round($dailyGoal, 2),
			'daily_rhythm_delta_pct' => $dailyRhythmDeltaPct,
			'projected_closing' => $projectedClosing,
			'projected_variance_pct' => $projectedVariancePct,
			'target_source' => $targetSource,
			'explanation' => $isClosedPeriod
				? "Período finalizado ({$daysElapsed}/{$daysTotal} días transcurridos). Resultado de cierre real: \$" . number_format($salesNet, 2) . " frente a la meta de \$" . number_format($targetGoal, 2) . "."
				: "Proyección lineal ponderada: Venta acumulada (\$" . number_format($salesNet, 2) . ") + [Venta promedio diaria (\$" . number_format($avgDailySales, 2) . ") × " . $daysRemaining . " días restantes]."
		];

		// Filtros comunes para consultas complementarias (zonas, año anterior, serie diaria anterior, sucursales)
		$pFilter = [':f' => $startDate, ':t' => $endDate];
		$iNet = SalesService::SQL_ITEM_NET_SALES;
		$sNet = SalesService::SQL_NET_SALES;
		$costExpr = 'IFNULL(NULLIF(si.total_cost, 0), si.amount * IFNULL(si.unit_cost, 0))';
		$bSql = $branchId ? ' AND s.branch_id = :b' : '';
		if ($branchId)
		{
			$pFilter[':b'] = $branchId;
		}
		$gSql = '';
		if ($productId)
		{
			$gSql = ' AND si.product_id = :pid';
			$pFilter[':pid'] = $productId;
		}
		elseif ($groupId !== null && $groupId > 0)
		{
			$gSql = ' AND si.product_id IN (SELECT id FROM products WHERE product_group_id = :gid)';
			$pFilter[':gid'] = $groupId;
		}
		elseif ($groupId !== null && $groupId === 0)
		{
			$gSql = ' AND (si.product_id NOT IN (SELECT id FROM products WHERE product_group_id IS NOT NULL))';
		}

		// Serie diaria del período anterior equivalente para la Curva de Ventas Diaria
		$prevFrom = $analysis['prev_from'] ?? date('Y-m-d', strtotime($startDate . " -{$daysTotal} days"));
		$prevTo = $analysis['prev_to'] ?? date('Y-m-d', strtotime($startDate . ' -1 day'));
		$pPrevDaily = array_merge($pFilter, [':f' => $prevFrom, ':t' => $prevTo]);
		$stmtPrevDaily = $db->prepare("
			SELECT date(s.created_timestamp) AS d, SUM($iNet) AS v
			FROM sale_items si
			JOIN sales s ON s.id = si.sale_id
			LEFT JOIN products p ON p.id = si.product_id
			WHERE s.status = 'completed' AND date(s.created_timestamp) BETWEEN :f AND :t $bSql $gSql
			GROUP BY d
		");
		$stmtPrevDaily->execute($pPrevDaily);
		$prevDailyMap = [];
		foreach ($stmtPrevDaily->fetchAll(\PDO::FETCH_ASSOC) as $pdRow)
		{
			$prevDailyMap[$pdRow['d']] = round(floatval($pdRow['v']), 2);
		}
		$prevDailySeries = [];
		for ($ts = strtotime($prevFrom); $ts <= strtotime($prevTo); $ts += 86400)
		{
			$dKey = date('Y-m-d', $ts);
			$prevDailySeries[] = [
				'd' => $dKey,
				'v' => $prevDailyMap[$dKey] ?? 0.0
			];
		}

		// Desempeño de Ventas por Sucursal (con ventas netas, vs. período anterior, margen bruto y transacciones)
		if ($groupId !== null || $productId)
		{
			$stmtBranches = $db->prepare("
				SELECT
					s.branch_id AS id,
					IFNULL(b.name, 'Sucursal ' || s.branch_id) AS name,
					SUM($iNet) AS total,
					SUM($costExpr) AS cogs,
					COUNT(DISTINCT s.id) AS tickets
				FROM sale_items si
				JOIN sales s ON s.id = si.sale_id
				LEFT JOIN branches b ON b.id = s.branch_id
				LEFT JOIN products p ON p.id = si.product_id
				WHERE s.status = 'completed' AND date(s.created_timestamp) BETWEEN :f AND :t $bSql $gSql
				GROUP BY s.branch_id
				ORDER BY total DESC
			");
			$stmtBranches->execute($pFilter);
			$rawBranches = $stmtBranches->fetchAll(\PDO::FETCH_ASSOC);

			$stmtPrevBranches = $db->prepare("
				SELECT
					s.branch_id AS id,
					SUM($iNet) AS total
				FROM sale_items si
				JOIN sales s ON s.id = si.sale_id
				LEFT JOIN products p ON p.id = si.product_id
				WHERE s.status = 'completed' AND date(s.created_timestamp) BETWEEN :f AND :t $bSql $gSql
				GROUP BY s.branch_id
			");
			$stmtPrevBranches->execute($pPrevDaily);
			$rawPrevBranches = $stmtPrevBranches->fetchAll(\PDO::FETCH_ASSOC);
		}
		else
		{
			$pBr = [':f' => $startDate, ':t' => $endDate] + ($branchId ? [':b' => $branchId] : []);
			$stmtBranches = $db->prepare("
				SELECT
					s.branch_id AS id,
					IFNULL(b.name, 'Sucursal ' || s.branch_id) AS name,
					SUM($sNet) AS total,
					SUM(s.total_cogs) AS cogs,
					SUM(s.gross_margin) AS margin,
					COUNT(DISTINCT s.id) AS tickets
				FROM sales s
				LEFT JOIN branches b ON b.id = s.branch_id
				WHERE s.status = 'completed' AND date(s.created_timestamp) BETWEEN :f AND :t" . ($branchId ? ' AND s.branch_id = :b' : '') . "
				GROUP BY s.branch_id
				ORDER BY total DESC
			");
			$stmtBranches->execute($pBr);
			$rawBranches = $stmtBranches->fetchAll(\PDO::FETCH_ASSOC);

			$pPrevBr = [':f' => $prevFrom, ':t' => $prevTo] + ($branchId ? [':b' => $branchId] : []);
			$stmtPrevBranches = $db->prepare("
				SELECT
					s.branch_id AS id,
					SUM($sNet) AS total
				FROM sales s
				WHERE s.status = 'completed' AND date(s.created_timestamp) BETWEEN :f AND :t" . ($branchId ? ' AND s.branch_id = :b' : '') . "
				GROUP BY s.branch_id
			");
			$stmtPrevBranches->execute($pPrevBr);
			$rawPrevBranches = $stmtPrevBranches->fetchAll(\PDO::FETCH_ASSOC);
		}

		$prevBranchMap = [];
		foreach ($rawPrevBranches as $pbr)
		{
			$prevBranchMap[intval($pbr['id'])] = round(floatval($pbr['total']), 2);
		}

		$branchSalesById = [];
		foreach ($rawBranches as $rb)
		{
			$bid = intval($rb['id']);
			$bTot = round(floatval($rb['total']), 2);
			$bCogs = round(floatval($rb['cogs']), 2);
			$bMargin = isset($rb['margin']) ? round(floatval($rb['margin']), 2) : ($bCogs > 0 ? round($bTot - $bCogs, 2) : null);
			$bMarginPct = ($bTot > 0 && $bCogs > 0 && $bMargin !== null) ? round(($bMargin / $bTot) * 100, 1) : null;
			$prevBrTot = $prevBranchMap[$bid] ?? 0.0;
			$vsPrevPct = ($bTot > 0 && $prevBrTot > 0) ? round((($bTot - $prevBrTot) / $prevBrTot) * 100, 1) : null;
			$branchSalesById[$bid] = [
				'id' => $bid,
				'name' => $cleanLabel($rb['name']),
				'total' => $bTot,
				'prev_total' => $prevBrTot,
				'vs_prev_pct' => $vsPrevPct,
				'cogs' => $bCogs,
				'margin' => $bMargin,
				'margin_pct' => $bMarginPct,
				'tickets' => intval($rb['tickets']),
				'share_pct' => $salesNet > 0 ? round(($bTot / $salesNet) * 100, 1) : 0.0,
				'has_activity' => ($bTot > 0 || intval($rb['tickets']) > 0)
			];
		}

		// Si no hay filtro de sucursal única, completar con las sucursales activas permitidas (hasta mostrar al menos las 5 principales de la red)
		$branchesSummary = array_values($branchSalesById);
		if (!$branchId)
		{
			foreach ($branches as $bObj)
			{
				if (count($branchesSummary) >= 5)
				{
					break;
				}
				$bid = intval(is_object($bObj) ? $bObj->id : $bObj['id']);
				$bName = $cleanLabel(is_object($bObj) ? $bObj->name : $bObj['name']);
				if (!isset($branchSalesById[$bid]))
				{
					$branchesSummary[] = [
						'id' => $bid,
						'name' => $bName,
						'total' => 0.0,
						'prev_total' => $prevBranchMap[$bid] ?? 0.0,
						'vs_prev_pct' => null,
						'cogs' => 0.0,
						'margin' => null,
						'margin_pct' => null,
						'tickets' => 0,
						'share_pct' => 0.0,
						'has_activity' => false
					];
				}
			}
		}

		// Participación por Departamento conciliada con el total de Ventas Netas ($salesNet)
		$rawGroups = $analysis['by_group'] ?? [];
		if (empty($rawGroups) && ($groupId !== null || $productId))
		{
			$stmtScopedGroups = $db->prepare("
				SELECT
					IFNULL(p.product_group_id, 0) AS id,
					IFNULL(g.name, 'Sin departamento') AS name,
					SUM($iNet) AS total,
					SUM(si.amount) AS units,
					SUM($costExpr) AS cogs
				FROM sale_items si
				JOIN sales s ON s.id = si.sale_id
				LEFT JOIN products p ON p.id = si.product_id
				LEFT JOIN product_groups g ON g.id = p.product_group_id
				WHERE s.status = 'completed' AND date(s.created_timestamp) BETWEEN :f AND :t $bSql $gSql
				GROUP BY 1
				ORDER BY total DESC
			");
			$stmtScopedGroups->execute($pFilter);
			$rawGroups = $stmtScopedGroups->fetchAll(\PDO::FETCH_ASSOC);
		}
		$rawGroupSum = array_sum(array_map(fn($g) => floatval($g['total']), $rawGroups));
		$palette = ['#10B981', '#3B82F6', '#F59E0B', '#A855F7', '#84CC16', '#64748B', '#06B6D4', '#EC4899'];
		$departmentsDonut = [];
		$unclassifiedSales = 0.0;
		$unclassifiedPct = 0.0;
		$unclassifiedUnits = 0.0;

		if (!empty($rawGroups) && $rawGroupSum > 0)
		{
			foreach ($rawGroups as $rg)
			{
				if (intval($rg['id']) === 0 || $rg['name'] === 'Sin departamento')
				{
					$rRatio = floatval($rg['total']) / $rawGroupSum;
					$unclassifiedSales = round($salesNet * $rRatio, 2);
					$unclassifiedPct = round($rRatio * 100.0, 1);
					$unclassifiedUnits = round(floatval($rg['units'] ?? 0), 0);
				}
			}

			$topGroups = array_slice($rawGroups, 0, 5);
			$otherGroups = array_slice($rawGroups, 5);
			$runningVal = 0.0;
			$runningPct = 0.0;
			$sliceCount = count($topGroups) + (count($otherGroups) > 0 ? 1 : 0);

			foreach ($topGroups as $idx => $g)
			{
				$isLast = ($idx === $sliceCount - 1);
				$ratio = floatval($g['total']) / $rawGroupSum;
				$val = $isLast ? round(max(0.0, $salesNet - $runningVal), 2) : round($salesNet * $ratio, 2);
				$pct = $isLast ? round(max(0.0, 100.0 - $runningPct), 1) : round($ratio * 100.0, 1);
				$runningVal += $val;
				$runningPct += $pct;
				$gCogs = floatval($g['cogs'] ?? 0);
				$gMargin = $gCogs > 0 ? round(floatval($g['total']) - $gCogs, 2) : null;
				$gId = intval($g['id']);
				$gCleanName = $gId === 0 ? 'Sin departamento' : $cleanLabel($g['name']);
				$departmentsDonut[] = [
					'id' => $gId,
					'name' => $gCleanName,
					'raw_name' => $g['name'],
					'total' => $val,
					'raw_total' => round(floatval($g['total']), 2),
					'share_pct' => $pct,
					'units' => round(floatval($g['units'] ?? 0), 0),
					'margin' => $gMargin,
					'color' => $palette[$idx % count($palette)]
				];
			}

			if (count($otherGroups) > 0)
			{
				$otherUnits = array_sum(array_map(fn($og) => floatval($og['units'] ?? 0), $otherGroups));
				$otherRaw = array_sum(array_map(fn($og) => floatval($og['total'] ?? 0), $otherGroups));
				$otherCogs = array_sum(array_map(fn($og) => floatval($og['cogs'] ?? 0), $otherGroups));
				$departmentsDonut[] = [
					'id' => null,
					'name' => 'Otros',
					'raw_name' => 'Otros',
					'total' => round(max(0.0, $salesNet - $runningVal), 2),
					'raw_total' => round($otherRaw, 2),
					'share_pct' => round(max(0.0, 100.0 - $runningPct), 1),
					'units' => round($otherUnits, 0),
					'margin' => $otherCogs > 0 ? round($otherRaw - $otherCogs, 2) : null,
					'color' => '#64748B'
				];
			}
		}

		// Mapa de categorías por producto para resolver miniaturas reales de productos
		$prodCatMap = [];
		foreach ($db->query("SELECT p.id, IFNULL(g.name, '') AS cat_name FROM products p LEFT JOIN product_groups g ON g.id = p.product_group_id")->fetchAll(\PDO::FETCH_ASSOC) as $pcRow)
		{
			$prodCatMap[intval($pcRow['id'])] = $pcRow['cat_name'];
		}

		$rawByProduct = $analysis['by_product'] ?? [];
		if (empty($rawByProduct) && $productId)
		{
			$stmtSingleProd = $db->prepare("
				SELECT
					si.product_id AS id,
					IFNULL(p.name, 'Producto #' || si.product_id) AS name,
					p.sku,
					SUM($iNet) AS total,
					SUM(si.amount) AS units,
					SUM($costExpr) AS cogs,
					COUNT(DISTINCT s.id) AS tickets
				FROM sale_items si
				JOIN sales s ON s.id = si.sale_id
				LEFT JOIN products p ON p.id = si.product_id
				WHERE s.status = 'completed' AND date(s.created_timestamp) BETWEEN :f AND :t $bSql $gSql
				GROUP BY si.product_id
			");
			$stmtSingleProd->execute($pFilter);
			$rawByProduct = $stmtSingleProd->fetchAll(\PDO::FETCH_ASSOC);
		}

		// Productos Destacados: Top 5 por Ventas y Top 5 por Margen (solo productos con costo válido en Mayor margen)
		$allProducts = [];
		foreach ($rawByProduct as $p)
		{
			$pid = intval($p['id']);
			$pTot = round(floatval($p['total']), 2);
			$pCogs = round(floatval($p['cogs']), 2);
			$hasCost = ($pCogs > 0);
			$pMargin = ($hasCost && $pTot > 0) ? round($pTot - $pCogs, 2) : null;
			$pMarginPct = ($pMargin !== null && $pTot > 0) ? round(($pMargin / $pTot) * 100.0, 1) : null;
			$shortName = trim(preg_replace('/\s*\(histórico\)\s*/iu', '', (string)$p['name']));
			$allProducts[] = [
				'id' => $pid,
				'name' => $shortName,
				'full_name' => $p['name'],
				'sku' => $p['sku'] ?? null,
				'image_url' => $this->ResolvePosProductImage((string)$p['name'], $prodCatMap[$pid] ?? ''),
				'total' => $pTot,
				'cogs' => $pCogs,
				'has_cost' => $hasCost,
				'margin' => $pMargin,
				'margin_pct' => $pMarginPct,
				'units' => round(floatval($p['units']), 0),
				'tickets' => intval($p['tickets'])
			];
		}

		$topProductsBySales = array_slice($allProducts, 0, 5);
		$productsWithMargin = array_values(array_filter($allProducts, fn($p) => $p['has_cost'] && $p['margin_pct'] !== null && $p['total'] > 0));
		usort($productsWithMargin, fn($a, $b) => ($b['margin_pct'] <=> $a['margin_pct']) ?: ($b['total'] <=> $a['total']));
		$topProductsByMargin = array_slice($productsWithMargin, 0, 5);
		$productsMissingCostCount = count(array_filter($allProducts, fn($p) => !$p['has_cost']));

		// Calidad y Cobertura de Datos Comerciales del período
		$stmtQuality = $db->prepare("
			SELECT
				COUNT(*) AS total_lines,
				SUM(CASE WHEN $costExpr <= 0 THEN 1 ELSE 0 END) AS lines_without_cost,
				COUNT(DISTINCT CASE WHEN $costExpr <= 0 THEN si.product_id END) AS prods_without_cost,
				IFNULL(SUM($iNet), 0) AS total_item_sales,
				IFNULL(SUM(CASE WHEN $costExpr > 0 THEN $iNet ELSE 0 END), 0) AS sales_with_cost,
				MAX(s.created_timestamp) AS last_sale_ts
			FROM sale_items si
			JOIN sales s ON s.id = si.sale_id
			LEFT JOIN products p ON p.id = si.product_id
			WHERE s.status = 'completed' AND date(s.created_timestamp) BETWEEN :f AND :t $bSql $gSql
		");
		$stmtQuality->execute($pFilter);
		$qRow = $stmtQuality->fetch(\PDO::FETCH_ASSOC) ?: [];
		$totalSaleLines = intval($qRow['total_lines'] ?? 0);
		$linesWithoutCost = intval($qRow['lines_without_cost'] ?? 0);
		$prodsWithoutCost = max($productsMissingCostCount, intval($qRow['prods_without_cost'] ?? 0));
		$totalItemSales = floatval($qRow['total_item_sales'] ?? 0);
		$salesWithCost = floatval($qRow['sales_with_cost'] ?? 0);
		$costCoveragePct = $totalItemSales > 0 ? round(($salesWithCost / $totalItemSales) * 100.0, 1) : 100.0;
		$lineCostCoveragePct = $totalSaleLines > 0 ? round((($totalSaleLines - $linesWithoutCost) / $totalSaleLines) * 100.0, 1) : 100.0;
		$lastSaleTs = !empty($qRow['last_sale_ts']) ? date('d/m/Y H:i', strtotime($qRow['last_sale_ts'])) : date('d/m/Y H:i');

		$dataQuality = [
			'total_lines' => $totalSaleLines,
			'lines_without_cost' => $linesWithoutCost,
			'products_without_cost' => $prodsWithoutCost,
			'cost_coverage_pct' => $costCoveragePct,
			'line_cost_coverage_pct' => $lineCostCoveragePct,
			'unclassified_sales' => $unclassifiedSales,
			'unclassified_pct' => $unclassifiedPct,
			'unclassified_units' => $unclassifiedUnits,
			'unlinked_lines' => count($unlinked),
			'unlinked_pct' => $totalSaleLines > 0 ? round((count($unlinked) / max($totalSaleLines, count($unlinked))) * 100.0, 1) : 0.0,
			'last_updated' => $lastSaleTs
		];

		// Zonas físicas de venta (tienda física vs tráfico)
		$stmtZones = $db->prepare("
			SELECT 
				IFNULL(l.name, 'Zona Central de Piso') as zone_name,
				IFNULL(l.is_freezer, 0) as is_freezer,
				SUM($iNet) as total,
				SUM(si.amount) as units,
				COUNT(DISTINCT s.id) as tickets
			FROM sale_items si
			JOIN sales s ON s.id = si.sale_id
			JOIN products p ON p.id = si.product_id
			LEFT JOIN locations l ON l.id = p.location_id
			WHERE s.status = 'completed' AND date(s.created_timestamp) BETWEEN :f AND :t $bSql $gSql
			GROUP BY p.location_id
			ORDER BY total DESC
		");
		$stmtZones->execute($pFilter);
		$salesZones = $stmtZones->fetchAll(\PDO::FETCH_ASSOC);

		// Comparativo año anterior (mismo período comparable hace 365 días)
		$yearAgoStart = date('Y-m-d', strtotime($startDate . ' -1 year'));
		$yearAgoEnd = date('Y-m-d', strtotime($endDate . ' -1 year'));
		$pYear = array_merge($pFilter, [':f' => $yearAgoStart, ':t' => $yearAgoEnd]);
		$stmtYear = $db->prepare("SELECT IFNULL(SUM($iNet), 0) AS total, COUNT(DISTINCT s.id) AS tickets FROM sale_items si JOIN sales s ON s.id = si.sale_id WHERE s.status = 'completed' AND date(s.created_timestamp) BETWEEN :f AND :t $bSql $gSql");
		$stmtYear->execute($pYear);
		$yearAgoData = $stmtYear->fetch(\PDO::FETCH_ASSOC);
		$yearAgoTotal = floatval($yearAgoData['total'] ?? 0);
		$yearAgoTickets = intval($yearAgoData['tickets'] ?? 0);
		$yearAgoDeltaPct = $yearAgoTotal > 0 ? round((($salesNet - $yearAgoTotal) / $yearAgoTotal) * 100, 1) : null;

		// Comparativo equivalente anterior
		$prevTotal = floatval($analysis['prev']['total'] ?? 0);
		$prevTickets = intval($analysis['prev']['tickets'] ?? 0);
		$prevDeltaPct = $analysis['totals']['delta_pct'] ?? null;

		$comparisons = [
			'real' => ['sales' => $salesNet, 'tickets' => intval($analysis['totals']['tickets'] ?? 0)],
			'target' => ['sales' => $targetGoal, 'gap' => $salesNet - $targetGoal, 'pct' => $compliancePct ?? 0.0],
			'previous_period' => [
				'start' => $analysis['prev_from'],
				'end' => $analysis['prev_to'],
				'sales' => $prevTotal,
				'tickets' => $prevTickets,
				'delta_pct' => $prevDeltaPct,
				'days' => $daysTotal,
				'is_partial' => ($daysTotal < 28)
			],
			'year_ago' => [
				'start' => $yearAgoStart,
				'end' => $yearAgoEnd,
				'sales' => $yearAgoTotal,
				'tickets' => $yearAgoTickets,
				'delta_pct' => $yearAgoDeltaPct,
				'has_data' => ($yearAgoTotal > 0)
			]
		];

		// Oportunidades y Hallazgos Comerciales Accionables (sin mapa de calor ni tratar Sin departamento como crecimiento)
		$avgTicketNow = floatval($analysis['totals']['avg_ticket'] ?? 0);
		$avgTicketPrev = floatval($analysis['prev']['avg_ticket'] ?? 0);
		$avgTicketDelta = ($avgTicketNow > 0 && $avgTicketPrev > 0) ? round((($avgTicketNow - $avgTicketPrev) / $avgTicketPrev) * 100.0, 1) : null;

		$highlights = [];
		if ($unclassifiedPct > 0)
		{
			$highlights[] = [
				'tone' => 'amber',
				'icon' => 'fa-triangle-exclamation',
				'title' => 'Departamento con baja clasificación',
				'desc' => "Sin departamento concentra <strong>" . number_format($unclassifiedPct, 1) . "%</strong> de las ventas (\$" . number_format($unclassifiedSales, 2) . ").",
				'action_label' => 'Revisar y clasificar',
				'action_url' => '?group_id=0&start_date=' . $startDate . '&end_date=' . $endDate . ($branchId ? '&branch_id=' . $branchId : '') . '&tab=detalle'
			];
		}
		elseif ($pendingAmount > 0)
		{
			$highlights[] = [
				'tone' => 'amber',
				'icon' => 'fa-bullseye',
				'title' => 'Brecha frente a la meta comercial',
				'desc' => "Faltan <strong>\$" . number_format($pendingAmount, 2) . "</strong> para cubrir la meta del período (" . number_format($compliancePct ?? 0, 1) . "% de avance).",
				'action_label' => 'Ver análisis',
				'action_tab' => 'tab-analisis'
			];
		}
		else
		{
			$highlights[] = [
				'tone' => 'amber',
				'icon' => 'fa-store',
				'title' => 'Desempeño por sucursal',
				'desc' => "Revisa la contribución y margen bruto de las sucursales activas en el período.",
				'action_label' => 'Ver sucursales',
				'action_tab' => 'tab-analisis'
			];
		}

		if ($avgTicketDelta !== null && $avgTicketDelta < 0)
		{
			$highlights[] = [
				'tone' => 'blue',
				'icon' => 'fa-receipt',
				'title' => 'Ticket promedio bajo',
				'desc' => "El ticket promedio está <strong>" . number_format(abs($avgTicketDelta), 1) . "%</strong> por debajo del período anterior (\$" . number_format($avgTicketNow, 2) . " vs. \$" . number_format($avgTicketPrev, 2) . ").",
				'action_label' => 'Ver estrategias',
				'action_tab' => 'tab-analisis'
			];
		}
		else
		{
			$highlights[] = [
				'tone' => 'blue',
				'icon' => 'fa-receipt',
				'title' => 'Impulsar ticket promedio',
				'desc' => "Ticket promedio actual de <strong>\$" . number_format($avgTicketNow, 2) . "</strong>. Potenciar venta cruzada en categorías de alto margen.",
				'action_label' => 'Ver estrategias',
				'action_tab' => 'tab-analisis'
			];
		}

		if ($prodsWithoutCost > 0 || count($unlinked) > 0)
		{
			$highlights[] = [
				'tone' => 'red',
				'icon' => 'fa-box-open',
				'title' => 'Productos sin costo / conciliación',
				'desc' => $prodsWithoutCost > 0
					? "<strong>{$prodsWithoutCost} producto(s)</strong> sin costo registrado ({$linesWithoutCost} líneas) y <strong>" . count($unlinked) . " línea(s)</strong> por conciliar."
					: "<strong>" . count($unlinked) . " línea(s)</strong> de venta requieren conciliación con inventario.",
				'action_label' => 'Completar costos',
				'action_tab' => 'tab-detalle',
				'action_subtab' => $prodsWithoutCost > 0 ? 'subtab-sku' : 'subtab-conciliacion'
			];
		}
		else
		{
			$highlights[] = [
				'tone' => 'green',
				'icon' => 'fa-circle-check',
				'title' => 'Costos e inventario conciliados',
				'desc' => "100% de las líneas vendidas tienen costo de lote y movimiento de inventario vinculado.",
				'action_label' => 'Ver detalle',
				'action_tab' => 'tab-detalle'
			];
		}

		return $this->RenderPage($response, 'sales_history', [
			'sales' => $sales,
			'branches' => $branches,
			'productGroups' => $productGroups,
			'selectedBranchId' => $branchId,
			'selectedGroupId' => $groupId,
			'selectedProductId' => $productId,
			'selectedBranch' => $selectedBranch,
			'selectedGroup' => $selectedGroup,
			'selectedProduct' => $selectedProduct,
			'activeTab' => $activeTab,
			'compareWith' => $compareWith,
			'startDate' => $startDate,
			'endDate' => $endDate,
			'analysis' => $analysis,
			'prevDailySeries' => $prevDailySeries,
			'branchesSummary' => $branchesSummary,
			'departmentsDonut' => $departmentsDonut,
			'topProductsBySales' => $topProductsBySales,
			'topProductsByMargin' => $topProductsByMargin,
			'allProducts' => $allProducts,
			'dataQuality' => $dataQuality,
			'unlinked' => $unlinked,
			'thermometer' => $thermometer,
			'salesZones' => $salesZones,
			'comparisons' => $comparisons,
			'highlights' => array_slice($highlights, 0, 3)
		]);
	}

	public function CustomersList(Request $request, Response $response, array $args)
	{
		$params = $request->getQueryParams();
		$salesService = SalesService::GetInstance();
		$branchService = BranchService::GetInstance();
		$branches = DairyAccess::FilterBranches($branchService->GetBranches());

		$directory = $salesService->GetCustomersDirectory();
		$customers = $directory['customers'];
		$recentOrdersAll = $directory['recent_orders_all'];

		$selectedCustomerId = isset($params['id']) && intval($params['id']) > 0
			? intval($params['id'])
			: (!empty($customers) ? intval($customers[0]['id']) : null);

		return $this->RenderPage($response, 'customers', [
			'customers' => $customers,
			'recentOrdersAll' => $recentOrdersAll,
			'branches' => $branches,
			'selectedCustomerId' => $selectedCustomerId
		]);
	}
}
