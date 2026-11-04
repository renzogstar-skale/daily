<?php
$db = new PDO('sqlite:data/grocy_es.db');

// Check current sales in the last 28 days
$date28 = date('Y-m-d', strtotime('-28 days'));
echo "Checking sales since $date28...\n";
$cnt = $db->query("SELECT COUNT(*) FROM sale_items si JOIN sales s ON s.id = si.sale_id WHERE s.status = 'completed' AND date(s.created_timestamp) >= '$date28'")->fetchColumn();
echo "Sales items in last 28 days: $cnt\n";
