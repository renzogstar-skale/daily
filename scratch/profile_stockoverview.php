<?php
define('GROCY_DATAPATH', __DIR__ . '/../data');
require __DIR__ . '/../packages/autoload.php';
require __DIR__ . '/../app.php';
$db = \Grocy\Services\DatabaseService::GetInstance()->GetDbConnectionRaw();
$t = function ($label, $fn) { $a = microtime(true); $r = $fn(); printf("%-32s %6.0f ms\n", $label, (microtime(true) - $a) * 1000); return $r; };
$t('GetInventoryHealth', fn() => \Grocy\Services\DairyInventoryService::GetInstance()->GetInventoryHealth());
$t('valuation overview', fn() => $db->query('SELECT IFNULL(SUM(value), 0) FROM uihelper_stock_current_overview')->fetchColumn());
$t('stockLots', fn() => count($db->query('SELECT s.id FROM stock s JOIN products p ON s.product_id = p.id LEFT JOIN locations l ON s.location_id = l.id ORDER BY s.best_before_date')->fetchAll()));
$t('overview rows', fn() => count($db->query('SELECT * FROM uihelper_stock_current_overview WHERE is_in_stock_or_below_min_stock = 1')->fetchAll()));
$t('GetCurrentStockLocations', fn() => \Grocy\Services\StockService::GetInstance()->GetCurrentStockLocations());
$t('userfield values', fn() => \Grocy\Services\UserfieldsService::GetInstance()->GetAllValues('products'));
$t('StockExecutive', fn() => \Grocy\Services\StockExecutiveOverviewService::GetInstance()->GetExecutiveData([]));
