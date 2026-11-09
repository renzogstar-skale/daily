<?php
$db = new PDO('sqlite:data/grocy_es.db');
$tables = ['sales', 'sale_items', 'stock_log', 'stock', 'pos_external_sales', 'waste_records', 'budgets'];
foreach($tables as $t) {
    try {
        $c = $db->query("SELECT COUNT(*) FROM $t WHERE created_timestamp LIKE '2025%' OR row_created_timestamp LIKE '2025%'")->fetchColumn();
        echo "$t 2025 rows: $c\n";
    } catch (Exception $e) {
        // ignore
    }
}

