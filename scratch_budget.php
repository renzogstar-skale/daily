<?php
require __DIR__ . '/tests/dairy/bootstrap.php';

$db = Grocy\Services\DatabaseService::GetInstance()->GetDbConnectionRaw();
$db->exec("UPDATE dairy_budgets SET sales_target = 114000 WHERE period = '2026-11' AND branch_id = 0");

$t = Grocy\Services\DailyMetricsService::GetInstance()->DailySalesTarget(null, '2026-11-02');
echo "New daily sales target for network: $" . $t . "\n";

$pulse = Grocy\Services\TodayExecutiveSalesService::GetInstance()->ExecutivePulse(null, '2026-11-02');
echo "Sales Net: " . $pulse['kpis']['sales_net']['value'] . "\n";
echo "Compliance Value: " . $pulse['kpis']['target_compliance']['value'] . "\n";
echo "Gap: " . $pulse['kpis']['target_compliance']['gap_pp'] . "\n";
echo "Target text: " . $pulse['kpis']['target_compliance']['target_text'] . "\n";

