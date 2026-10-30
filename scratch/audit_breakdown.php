<?php
$db = new PDO("sqlite:data/grocy_es.db");
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "=== EMPLOYEES PER BRANCH ===\n";
$stmt = $db->query("SELECT branch_id, count(*) as total, sum(active) as active_cnt FROM employees GROUP BY branch_id");
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
    echo "Branch {$r['branch_id']}: {$r['total']} employees ({$r['active_cnt']} active)\n";
}

echo "\n=== SHIFTS PER BRANCH ===\n";
$stmt = $db->query("SELECT branch_id, count(*) as total, min(shift_date) as d_min, max(shift_date) as d_max FROM employee_shifts GROUP BY branch_id");
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
    echo "Branch {$r['branch_id']}: {$r['total']} shifts (from {$r['d_min']} to {$r['d_max']})\n";
}

echo "\n=== ATTENDANCE SUMMARY ===\n";
$stmt = $db->query("SELECT count(*) as total, count(clock_out) as with_out FROM employee_attendance");
$r = $stmt->fetch(PDO::FETCH_ASSOC);
echo "Attendance records: {$r['total']}, with clock_out: {$r['with_out']}\n";

echo "\n=== SALES PER BRANCH ===\n";
$stmt = $db->query("SELECT branch_id, count(*) as tx_count, sum(total_amount) as total_val, min(created_timestamp) as d_min, max(created_timestamp) as d_max FROM sales GROUP BY branch_id");
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
    echo "Branch {$r['branch_id']}: {$r['tx_count']} sales, Total: \${$r['total_val']} ({$r['d_min']} to {$r['d_max']})\n";
}

echo "\n=== SENSITIVE PRODUCTS ===\n";
$stmt = $db->query("SELECT id, name, is_sensitive, sale_price, location_id FROM products WHERE is_sensitive = 1");
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
    echo "Product #{$r['id']}: {$r['name']} | sensitive={$r['is_sensitive']} | price={$r['sale_price']} | loc={$r['location_id']}\n";
}

echo "\n=== INVESTIGATION CASES / RECONCILIATIONS ===\n";
$stmt = $db->query("SELECT id, case_code, stage, branch_id, product_id, physical_count, expected_stock, difference_qty, financial_impact FROM sensitive_investigation_cases");
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
    echo "Case {$r['case_code']} (ID {$r['id']}): stage={$r['stage']}, branch={$r['branch_id']}, prod={$r['product_id']}, expected={$r['expected_stock']}, physical={$r['physical_count']}, diff={$r['difference_qty']}, impact=\${$r['financial_impact']}\n";
}

echo "\n=== ALERTS SUMMARY ===\n";
$stmt = $db->query("SELECT category, severity, status, count(*) as cnt FROM dairy_alerts GROUP BY category, severity, status");
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
    echo "Cat: {$r['category']} | Sev: {$r['severity']} | Status: {$r['status']} | Count: {$r['cnt']}\n";
}
