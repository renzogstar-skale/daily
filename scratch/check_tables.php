<?php
$db = new PDO('sqlite:data/grocy_es.db');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$tables = [
    'supplier_invoices',
    'supplier_invoice_items',
    'procurement_orders',
    'procurement_order_items',
    'stock_transfers',
    'pos_transactions',
    'pos_sales',
    'workforce_store_tasks',
    'schedule_audits',
    'barcode_scans',
    'customer_returns_exchanges',
    'loss_preventive_plans'
];

foreach ($tables as $t) {
    $exists = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='$t'")->fetchColumn();
    if ($exists) {
        $count = $db->query("SELECT count(*) FROM $t")->fetchColumn();
        echo "Table $t EXISTS, count: $count\n";
    } else {
        echo "Table $t DOES NOT EXIST\n";
    }
}

