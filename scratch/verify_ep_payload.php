<?php
define('GROCY_DATAPATH', __DIR__ . '/../data');
require_once __DIR__ . '/../app.php';
$kpiService = \Grocy\Services\KpiService::GetInstance();
$kpiData = $kpiService->GetExecutiveDashboardData(null, 'month', null, null, date('Y-m-d'));
$laborOutlook = \Grocy\Services\LaborForecastService::GetInstance()->Outlook([1, 2, 3]);
$ep = $kpiService->BuildExecutivePanelOverview(null, 'month', $kpiData, $laborOutlook, 12);

echo "ExecutivePanel Keys:\n";
print_r(array_keys($ep));

echo "\nPhoto KPIs count: " . count($ep['photo_kpis']) . "\n";
foreach ($ep['photo_kpis'] as $k => $v) {
    echo "  $k: {$v['label']} => {$v['formatted_value']} ({$v['subtitle']}) [{$v['delta']}]\n";
}

echo "\nColombia Branches count: " . count($ep['colombia_branches']) . "\n";
foreach ($ep['colombia_branches'] as $b) {
    echo "  #{$b['id']} {$b['full_label']} => {$b['status_label']} ({$b['compliance_pct']}%)\n";
}

echo "\nTop Departments count: " . count($ep['top_departments']) . "\n";
foreach ($ep['top_departments'] as $d) {
    echo "  {$d['name']} => {$d['formatted']} ({$d['share_pct']}%)\n";
}

echo "\nPriority Alerts count: " . count($ep['priority_alerts_list']) . "\n";
foreach ($ep['priority_alerts_list'] as $a) {
    echo "  [{$a['level']}] {$a['title']} · {$a['source']} ({$a['time_ago']})\n";
}

echo "\nUrgent Actions count: " . count($ep['urgent_actions_list']) . "\n";
foreach ($ep['urgent_actions_list'] as $u) {
    echo "  {$u['label']}\n";
}

echo "\nProcess Cards count: " . count($ep['process_cards']) . "\n";
foreach ($ep['process_cards'] as $p) {
    echo "  {$p['name']} [{$p['status_label']}] => {$p['description']}\n";
}

