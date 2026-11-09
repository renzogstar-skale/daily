<?php
define('GROCY_DATAPATH', __DIR__ . '/../data');
require_once __DIR__ . '/../packages/autoload.php';
require_once __DIR__ . '/../data/config.php';
require_once __DIR__ . '/../config-dist.php';

use Grocy\Services\DatabaseService;
use Grocy\Services\DailyMetricsService;
use Grocy\Services\StockoutService;
use Grocy\Services\BudgetService;
use Grocy\Helpers\DailyFilters;

class DepartmentPerformanceServicePrototype
{
    private function Db(): \PDO
    {
        return DatabaseService::GetInstance()->GetDbConnectionRaw();
    }

    public function GetData(array $f): array
    {
        $db = $this->Db();
        $metrics = DailyMetricsService::GetInstance();
        $deptDefs = DailyMetricsService::DEPARTMENTS;

        $from = $f['start_date'];
        $to = $f['end_date'];
        $lyFrom = date('Y-m-d', strtotime("$from -1 year"));
        $lyTo = date('Y-m-d', strtotime("$to -1 year"));

        $daysTotal = max(1, (int)round((strtotime($to) - strtotime($from)) / 86400) + 1);
        $ppTo = date('Y-m-d', strtotime("$from -1 day"));
        $ppFrom = date('Y-m-d', strtotime("$ppTo -" . ($daysTotal - 1) . ' days'));

        $bSql = DailyFilters::BranchSql($f, 's.branch_id');
        $costExpr = 'IFNULL(NULLIF(si.total_cost, 0), si.amount * IFNULL(si.unit_cost, 0))';

        // Overall sales for the period (for % share denominator)
        $stTotal = $db->prepare("SELECT IFNULL(SUM(si.subtotal), 0) FROM sale_items si JOIN sales s ON s.id = si.sale_id WHERE s.status = 'completed' AND date(s.created_timestamp) BETWEEN :f AND :t AND $bSql");
        $stTotal->execute([':f' => $from, ':t' => $to]);
        $totalSalesTY = floatval($stTotal->fetchColumn());

        $stTotalLY = $db->prepare("SELECT IFNULL(SUM(si.subtotal), 0) FROM sale_items si JOIN sales s ON s.id = si.sale_id WHERE s.status = 'completed' AND date(s.created_timestamp) BETWEEN :f AND :t AND $bSql");
        $stTotalLY->execute([':f' => $lyFrom, ':t' => $lyTo]);
        $totalSalesLY = floatval($stTotalLY->fetchColumn());

        $stTotalPP = $db->prepare("SELECT IFNULL(SUM(si.subtotal), 0) FROM sale_items si JOIN sales s ON s.id = si.sale_id WHERE s.status = 'completed' AND date(s.created_timestamp) BETWEEN :f AND :t AND $bSql");
        $stTotalPP->execute([':f' => $ppFrom, ':t' => $ppTo]);
        $totalSalesPP = floatval($stTotalPP->fetchColumn());

        // Targets from BudgetService
        $branchId = $f['branch_id'] ?? null;
        $periodMonth = date('Y-m', strtotime($to));
        $targets = BudgetService::GetInstance()->GetTargets($branchId, $periodMonth);
        $targetSalesMonthly = floatval($targets['sales_target'] ?? 0);
        $totalTarget = ($targetSalesMonthly > 0) ? round($targetSalesMonthly * ($daysTotal / 30.0), 2) : round(45000.0 * ($daysTotal / 30.0), 2);

        $departments = [];
        $hourlyTotal = array_fill(0, 24, 0.0);

        foreach ($deptDefs as $key => [$name, $color, $rawDepts])
        {
            $deptSql = $metrics->DeptSql([$key]);

            // TY by department
            $sqlDept = "SELECT 
                            IFNULL(SUM(si.subtotal), 0) as net, 
                            IFNULL(SUM(si.amount), 0) as units, 
                            COUNT(DISTINCT s.id) as tickets,
                            IFNULL(SUM($costExpr), 0) as cogs
                        FROM sale_items si 
                        JOIN sales s ON s.id = si.sale_id 
                        JOIN products p ON p.id = si.product_id
                        WHERE s.status = 'completed' AND date(s.created_timestamp) BETWEEN :f AND :t AND $bSql AND $deptSql";

            $st = $db->prepare($sqlDept);
            $st->execute([':f' => $from, ':t' => $to]);
            $rowTY = $st->fetch(\PDO::FETCH_ASSOC);

            // LY by department
            $stLY = $db->prepare($sqlDept);
            $stLY->execute([':f' => $lyFrom, ':t' => $lyTo]);
            $rowLY = $stLY->fetch(\PDO::FETCH_ASSOC);

            // PP by department
            $stPP = $db->prepare($sqlDept);
            $stPP->execute([':f' => $ppFrom, ':t' => $ppTo]);
            $rowPP = $stPP->fetch(\PDO::FETCH_ASSOC);

            $netTY = round(floatval($rowTY['net']), 2);
            $netLY = round(floatval($rowLY['net']), 2);
            $netPP = round(floatval($rowPP['net']), 2);
            $cogsTY = round(floatval($rowTY['cogs']), 2);

            $deltaLY = ($netLY > 0) ? round((($netTY - $netLY) / $netLY) * 100, 1) : null;
            $deltaPP = ($netPP > 0) ? round((($netTY - $netPP) / $netPP) * 100, 1) : null;
            $sharePct = ($totalSalesTY > 0) ? round(($netTY / $totalSalesTY) * 100, 1) : 0.0;

            // Hourly curve for this department
            $sqlHourly = "SELECT CAST(strftime('%H', s.created_timestamp) AS INTEGER) as h, IFNULL(SUM(si.subtotal), 0) as v
                          FROM sale_items si JOIN sales s ON s.id = si.sale_id JOIN products p ON p.id = si.product_id
                          WHERE s.status = 'completed' AND date(s.created_timestamp) BETWEEN :f AND :t AND $bSql AND $deptSql
                          GROUP BY h";
            $stH = $db->prepare($sqlHourly);
            $stH->execute([':f' => $from, ':t' => $to]);
            $deptHourly = array_fill(0, 24, 0.0);
            foreach ($stH->fetchAll(\PDO::FETCH_ASSOC) as $hr) {
                $hInt = intval($hr['h']);
                $vFloat = round(floatval($hr['v']), 2);
                $deptHourly[$hInt] = $vFloat;
                $hourlyTotal[$hInt] = round($hourlyTotal[$hInt] + $vFloat, 2);
            }

            // Categories (product_groups) in this department
            $sqlCats = "SELECT 
                            IFNULL(pg.id, 0) as category_id,
                            IFNULL(pg.name, 'Sin categoría') as category_name,
                            COUNT(DISTINCT p.id) as products_count,
                            IFNULL(SUM(si.amount), 0) as units,
                            IFNULL(SUM(si.subtotal), 0) as net,
                            IFNULL(SUM($costExpr), 0) as cogs
                        FROM sale_items si 
                        JOIN sales s ON s.id = si.sale_id 
                        JOIN products p ON p.id = si.product_id
                        LEFT JOIN product_groups pg ON pg.id = p.product_group_id
                        WHERE s.status = 'completed' AND date(s.created_timestamp) BETWEEN :f AND :t AND $bSql AND $deptSql
                        GROUP BY pg.id
                        ORDER BY net DESC";
            $stC = $db->prepare($sqlCats);
            $stC->execute([':f' => $from, ':t' => $to]);
            $categories = [];
            foreach ($stC->fetchAll(\PDO::FETCH_ASSOC) as $cr) {
                $cNet = round(floatval($cr['net']), 2);
                $cCogs = round(floatval($cr['cogs']), 2);
                $cMargin = round($cNet - $cCogs, 2);
                $cMarginPct = ($cNet > 0 && $cCogs <= $cNet * 3) ? round(($cMargin / $cNet) * 100, 1) : null;
                $categories[] = [
                    'id' => intval($cr['category_id']),
                    'name' => trim(preg_replace('/^\d{2}\s+/', '', (string)$cr['category_name'])),
                    'products_count' => intval($cr['products_count']),
                    'units' => round(floatval($cr['units']), 2),
                    'net' => $cNet,
                    'cogs' => $cCogs,
                    'margin' => $cMargin,
                    'margin_pct' => $cMarginPct,
                    'share_dept_pct' => ($netTY > 0) ? round(($cNet / $netTY) * 100, 1) : 0.0
                ];
            }

            // Top 5 products
            $sqlTop = "SELECT p.id, p.name, p.sku, IFNULL(pg.name, 'General') as category,
                              SUM(si.amount) as units, SUM(si.subtotal) as net,
                              SUM($costExpr) as cogs
                       FROM sale_items si
                       JOIN sales s ON s.id = si.sale_id
                       JOIN products p ON p.id = si.product_id
                       LEFT JOIN product_groups pg ON pg.id = p.product_group_id
                       WHERE s.status = 'completed' AND date(s.created_timestamp) BETWEEN :f AND :t AND $bSql AND $deptSql
                       GROUP BY p.id
                       ORDER BY net DESC
                       LIMIT 5";
            $stTop = $db->prepare($sqlTop);
            $stTop->execute([':f' => $from, ':t' => $to]);
            $topProducts = [];
            foreach ($stTop->fetchAll(\PDO::FETCH_ASSOC) as $tp) {
                $pNet = round(floatval($tp['net']), 2);
                $pCogs = round(floatval($tp['cogs']), 2);
                $topProducts[] = [
                    'id' => intval($tp['id']),
                    'name' => $tp['name'],
                    'sku' => $tp['sku'],
                    'category' => trim(preg_replace('/^\d{2}\s+/', '', (string)$tp['category'])),
                    'units' => round(floatval($tp['units']), 2),
                    'net' => $pNet,
                    'cogs' => $pCogs,
                    'margin' => round($pNet - $pCogs, 2),
                    'margin_pct' => ($pNet > 0 && $pCogs <= $pNet * 3) ? round((($pNet - $pCogs) / $pNet) * 100, 1) : null
                ];
            }

            // Bottom 5 products (menor venta, with sales > 0)
            $sqlBottom = "SELECT p.id, p.name, p.sku, IFNULL(pg.name, 'General') as category,
                                 SUM(si.amount) as units, SUM(si.subtotal) as net,
                                 SUM($costExpr) as cogs
                          FROM sale_items si
                          JOIN sales s ON s.id = si.sale_id
                          JOIN products p ON p.id = si.product_id
                          LEFT JOIN product_groups pg ON pg.id = p.product_group_id
                          WHERE s.status = 'completed' AND date(s.created_timestamp) BETWEEN :f AND :t AND $bSql AND $deptSql
                          GROUP BY p.id
                          HAVING net > 0
                          ORDER BY net ASC
                          LIMIT 5";
            $stBot = $db->prepare($sqlBottom);
            $stBot->execute([':f' => $from, ':t' => $to]);
            $bottomProducts = [];
            foreach ($stBot->fetchAll(\PDO::FETCH_ASSOC) as $bp) {
                $pNet = round(floatval($bp['net']), 2);
                $pCogs = round(floatval($bp['cogs']), 2);
                $bottomProducts[] = [
                    'id' => intval($bp['id']),
                    'name' => $bp['name'],
                    'sku' => $bp['sku'],
                    'category' => trim(preg_replace('/^\d{2}\s+/', '', (string)$bp['category'])),
                    'units' => round(floatval($bp['units']), 2),
                    'net' => $pNet,
                    'cogs' => $pCogs,
                    'margin' => round($pNet - $pCogs, 2),
                    'margin_pct' => ($pNet > 0 && $pCogs <= $pNet * 3) ? round((($pNet - $pCogs) / $pNet) * 100, 1) : null
                ];
            }

            // Out-of-stock products in this department
            $fDept = array_merge($f, ['department' => $key]);
            $stockoutData = StockoutService::GetInstance()->List($fDept);
            $outOfStock = [];
            foreach ($stockoutData['rows'] ?? [] as $so) {
                if ($so['status'] === 'out' || $so['status'] === 'blocked') {
                    $outOfStock[] = [
                        'id' => intval($so['id']),
                        'name' => $so['name'],
                        'sku' => $so['sku'],
                        'category' => $so['category'],
                        'physical_stock' => $so['stock_registered'],
                        'available_stock' => $so['stock_available'],
                        'min_stock' => $so['min_stock'],
                        'status' => $so['status'],
                        'status_label' => $so['status_label']
                    ];
                }
            }

            $deptTarget = round($totalTarget * ($sharePct / 100.0), 2);
            $deptCompliance = ($deptTarget > 0) ? round(($netTY / $deptTarget) * 100, 1) : 0.0;

            $departments[$key] = [
                'key' => $key,
                'name' => $name,
                'color' => $color,
                'ty_net' => $netTY,
                'ly_net' => $netLY,
                'pp_net' => $netPP,
                'units' => round(floatval($rowTY['units']), 2),
                'tickets' => intval($rowTY['tickets']),
                'cogs' => $cogsTY,
                'margin' => round($netTY - $cogsTY, 2),
                'margin_pct' => ($netTY > 0 && $cogsTY <= $netTY * 3) ? round((($netTY - $cogsTY) / $netTY) * 100, 1) : null,
                'delta_ly' => $deltaLY,
                'delta_pp' => $deltaPP,
                'share_pct' => $sharePct,
                'target' => $deptTarget,
                'compliance_pct' => $deptCompliance,
                'hourly' => $deptHourly,
                'categories' => $categories,
                'top_products' => $topProducts,
                'bottom_products' => $bottomProducts,
                'out_of_stock' => array_slice($outOfStock, 0, 10),
                'out_of_stock_count' => count($outOfStock)
            ];
        }

        // Calculate Department Highlights (Top sales, Top growth, Top drop)
        $deptsWithSales = array_filter($departments, fn($d) => $d['ty_net'] > 0);
        
        // Top sales
        usort($deptsWithSales, fn($a, $b) => $b['ty_net'] <=> $a['ty_net']);
        $topSalesDept = !empty($deptsWithSales) ? $deptsWithSales[0] : null;

        // Top growth & Top drop
        // First try by delta_ly, if all LY are 0/null, use delta_pp for real ranking
        $deptsWithGrowthLY = array_filter($departments, fn($d) => $d['delta_ly'] !== null);
        $deptsWithGrowthPP = array_filter($departments, fn($d) => $d['delta_pp'] !== null);

        $growthList = !empty($deptsWithGrowthLY) ? $deptsWithGrowthLY : $deptsWithGrowthPP;
        $isLyBased = !empty($deptsWithGrowthLY);

        usort($growthList, fn($a, $b) => ($isLyBased ? ($b['delta_ly'] <=> $a['delta_ly']) : ($b['delta_pp'] <=> $a['delta_pp'])));
        $topGrowthDept = !empty($growthList) ? $growthList[0] : null;

        usort($growthList, fn($a, $b) => ($isLyBased ? ($a['delta_ly'] <=> $b['delta_ly']) : ($a['delta_pp'] <=> $b['delta_pp'])));
        $topDropDept = !empty($growthList) ? $growthList[0] : null;

        $overallDeltaLY = ($totalSalesLY > 0) ? round((($totalSalesTY - $totalSalesLY) / $totalSalesLY) * 100, 1) : null;
        $overallDeltaPP = ($totalSalesPP > 0) ? round((($totalSalesTY - $totalSalesPP) / $totalSalesPP) * 100, 1) : null;
        $overallCompliance = ($totalTarget > 0) ? round(($totalSalesTY / $totalTarget) * 100, 1) : 0.0;

        return [
            'period' => [
                'from' => $from,
                'to' => $to,
                'label' => DailyFilters::PeriodLabel($from, $to),
                'ly_from' => $lyFrom,
                'ly_to' => $lyTo,
                'ly_label' => DailyFilters::PeriodLabel($lyFrom, $lyTo),
                'pp_from' => $ppFrom,
                'pp_to' => $ppTo,
                'pp_label' => DailyFilters::PeriodLabel($ppFrom, $ppTo),
                'days' => $daysTotal
            ],
            'kpis' => [
                'total_ty' => $totalSalesTY,
                'total_ly' => $totalSalesLY,
                'delta_ly' => $overallDeltaLY,
                'total_pp' => $totalSalesPP,
                'delta_pp' => $overallDeltaPP,
                'target' => $totalTarget,
                'compliance_pct' => $overallCompliance,
                'top_sales' => $topSalesDept ? ['name' => $topSalesDept['name'], 'net' => $topSalesDept['ty_net'], 'color' => $topSalesDept['color']] : null,
                'top_growth' => $topGrowthDept ? [
                    'name' => $topGrowthDept['name'], 
                    'delta' => $isLyBased ? $topGrowthDept['delta_ly'] : $topGrowthDept['delta_pp'],
                    'basis' => $isLyBased ? 'LY' : 'PP',
                    'color' => $topGrowthDept['color']
                ] : null,
                'top_drop' => $topDropDept ? [
                    'name' => $topDropDept['name'], 
                    'delta' => $isLyBased ? $topDropDept['delta_ly'] : $topDropDept['delta_pp'],
                    'basis' => $isLyBased ? 'LY' : 'PP',
                    'color' => $topDropDept['color']
                ] : null,
            ],
            'departments' => array_values($departments),
            'hourly_total' => $hourlyTotal
        ];
    }
}

$proto = new DepartmentPerformanceServicePrototype();
$data = $proto->GetData(DailyFilters::FromQuery(['start_date' => '2026-10-07', 'end_date' => '2026-11-05']));
echo "KPIs:\n";
print_r($data['kpis']);
echo "Dept count: " . count($data['departments']) . "\n";
echo "First dept sample (Lácteos y huevos):\n";
$dairy = array_values(array_filter($data['departments'], fn($d) => $d['key'] === 'dairy'))[0];
echo "Categories in dairy: " . count($dairy['categories']) . "\n";
echo "Top products: " . count($dairy['top_products']) . "\n";
echo "Out of stock: " . $dairy['out_of_stock_count'] . "\n";

