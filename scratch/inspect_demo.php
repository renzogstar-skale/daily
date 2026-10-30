<?php
$root = dirname(__DIR__);
define('GROCY_DATAPATH', $root . '/data');
require_once $root . '/packages/autoload.php';
require_once $root . '/data/config.php';
require_once $root . '/config-dist.php';

$dbName = (GROCY_MODE === 'demo' || GROCY_MODE === 'prerelease') ? 'grocy_' . (defined('GROCY_DEMO_DB_SUFFIX') ? GROCY_DEMO_DB_SUFFIX : GROCY_DEFAULT_LOCALE) . '.db' : 'grocy.db';
$dbPath = $root . '/data/' . $dbName;

echo "Connecting to $dbPath\n";
$db = new PDO("sqlite:" . $dbPath);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "=== TABLES CHECK ===" . PHP_EOL;
$tables = ['branches', 'products', 'product_groups', 'locations', 'sales', 'sale_items', 'stock_log', 'stock', 'users', 'workforce_shifts', 'attendance_logs', 'cycle_counts', 'inventory_reconciliations', 'system_alerts', 'workforce_tasks', 'loss_incidents'];
foreach ($tables as $t) {
    try {
        $cnt = $db->query("SELECT count(*) FROM $t")->fetchColumn();
        echo str_pad($t, 25) . ": $cnt rows\n";
    } catch (Exception $e) {
        echo str_pad($t, 25) . ": ERROR (" . $e->getMessage() . ")\n";
    }
}

echo "\n=== BRANCHES ===" . PHP_EOL;
$branches = $db->query("SELECT id, name, code, is_active FROM branches")->fetchAll(PDO::FETCH_ASSOC);
foreach ($branches as $b) {
    echo "#{$b['id']} - {$b['name']} ({$b['code']}) active={$b['is_active']}\n";
}

echo "\n=== ROLES / USERS SAMPLE ===" . PHP_EOL;
$users = $db->query("SELECT id, username, first_name, last_name, role, branch_id FROM users LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
foreach ($users as $u) {
    echo "#{$u['id']} {$u['username']} ({$u['first_name']} {$u['last_name']}) - role: " . ($u['role'] ?? 'none') . " branch: " . ($u['branch_id'] ?? 'none') . "\n";
}
