<?php
define('GROCY_DATAPATH', __DIR__ . '/../data');
require_once __DIR__ . '/../app.php';
$kpiService = \Grocy\Services\KpiService::GetInstance();
$data = $kpiService->GetExecutiveDashboardData(null, 'month', null, null, date('Y-m-d'));
echo "Keys in GetExecutiveDashboardData:\n";
print_r(array_keys($data));

echo "\nPhoto KPIs:\n";
print_r($data['photo_kpis']);

echo "\nBranch counts:\n";
print_r($data['branch_counts']);

echo "\nTop departments:\n";
print_r($data['top_departments']);
