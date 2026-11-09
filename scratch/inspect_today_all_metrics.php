<?php
$db = new PDO('sqlite:data/grocy_es.db');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$today = '2026-11-04';

echo "--- CHECKING METRICS ON $today ---\n";
echo "Sales: " . $db->query("SELECT count(*) FROM sales WHERE date(created_timestamp) = '$today'")->fetchColumn() . "\n";
echo "Stock log (moves): " . $db->query("SELECT count(*) FROM stock_log WHERE date(row_created_timestamp) = '$today'")->fetchColumn() . "\n";
echo "Waste transactions: " . $db->query("SELECT count(*) FROM stock_log WHERE transaction_type='consume' AND transaction_subtype='waste' AND date(row_created_timestamp) = '$today'")->fetchColumn() . "\n";
echo "Workforce shifts: " . $db->query("SELECT count(*) FROM workforce_shift_schedules WHERE shift_date = '$today'")->fetchColumn() . "\n";
echo "Alerts: " . $db->query("SELECT count(*) FROM alert_center_incidents WHERE date(created_timestamp) = '$today'")->fetchColumn() . "\n";

