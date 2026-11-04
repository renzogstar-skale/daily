<?php
$db = new PDO('sqlite:data/grocy_es.db');

$tables = [
    'branches',
    'product_groups',
    'products',
    'suppliers',
    'employees',
    'locations',
    'stock',
    'stock_log',
    'sales',
    'sale_items',
    'waste_records',
    'customer_orders',
    'customer_order_lines',
    'physical_counts',
    'loss_cases',
    'transfers',
    'pos_cash_shifts',
    'daily_action_center_items'
];

echo "=== INVENTARIO DE REGISTROS ACTUALES ===\n";
foreach ($tables as $t) {
    try {
        $count = $db->query("SELECT COUNT(*) FROM $t")->fetchColumn();
        echo sprintf("%-30s: %d\n", $t, $count);
    } catch (\Throwable $e) {
        echo sprintf("%-30s: [NO EXISTE / ERROR]\n", $t);
    }
}

echo "\n=== SUCURSALES ACTUALES ===\n";
$branches = $db->query("SELECT id, name, city FROM branches")->fetchAll(PDO::FETCH_ASSOC);
foreach ($branches as $b) {
    echo "ID {$b['id']}: {$b['name']} ({$b['city']})\n";
}

echo "\n=== GRUPOS DE PRODUCTOS / DEPARTAMENTOS ACTUALES ===\n";
$pg = $db->query("SELECT id, name FROM product_groups")->fetchAll(PDO::FETCH_ASSOC);
foreach ($pg as $g) {
    echo "ID {$g['id']}: {$g['name']}\n";
}

