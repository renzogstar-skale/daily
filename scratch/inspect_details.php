<?php
$db = new PDO('sqlite:data/grocy_es.db');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "--- supplier_invoices schema ---\n";
echo $db->query("SELECT sql FROM sqlite_master WHERE name='supplier_invoices'")->fetchColumn() . "\n";
echo "--- supplier_invoice_items schema ---\n";
echo $db->query("SELECT sql FROM sqlite_master WHERE name='supplier_invoice_items'")->fetchColumn() . "\n";

echo "--- procurement_orders statuses ---\n";
$stmt = $db->query("SELECT status, count(*) FROM procurement_orders GROUP BY status");
while ($r = $stmt->fetch(PDO::FETCH_NUM)) {
    echo "Status: {$r[0]} => {$r[1]}\n";
}

echo "--- stock_transfers statuses ---\n";
$stmt = $db->query("SELECT status, count(*) FROM stock_transfers GROUP BY status");
while ($r = $stmt->fetch(PDO::FETCH_NUM)) {
    echo "Status: {$r[0]} => {$r[1]}\n";
}

