<?php
$db = new PDO('sqlite:data/grocy_es.db');

// Check sales distribution by year
$years = $db->query("SELECT strftime('%Y', created_timestamp) as yr, COUNT(*) as cnt, SUM(total_amount - tax_amount) as net FROM sales WHERE status = 'completed' GROUP BY yr")->fetchAll(PDO::FETCH_ASSOC);
echo "=== SALES BY YEAR ===\n";
print_r($years);

// Check if there are sales in 2025
$sales2025 = $db->query("SELECT MIN(created_timestamp), MAX(created_timestamp), COUNT(*) FROM sales WHERE status = 'completed' AND created_timestamp LIKE '2025%'")->fetchAll(PDO::FETCH_ASSOC);
echo "=== SALES 2025 ===\n";
print_r($sales2025);

