<?php
$db = new PDO('sqlite:data/grocy_es.db');

echo "=== PRODUCTS GROUPED BY DEPARTMENT ===\n";
$rows = $db->query("SELECT IFNULL(department, '') as dept, count(*) as count FROM products GROUP BY department ORDER BY count DESC")->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $r) {
    echo sprintf("%-30s : %d\n", $r['dept'] === '' ? '(VACÍO / NULL)' : $r['dept'], $r['count']);
}

echo "\n=== STOCK VALUE GROUPED BY PRODUCT DEPARTMENT ===\n";
$sql = "SELECT IFNULL(p.department, '') as dept, COUNT(DISTINCT s.product_id) as products_with_stock, SUM(s.amount) as total_units, SUM(s.amount * IFNULL(s.price, IFNULL(plp.price, 0))) as total_val
        FROM stock s
        JOIN products p ON p.id = s.product_id
        LEFT JOIN cache__products_last_purchased plp ON plp.product_id = s.product_id
        GROUP BY p.department
        ORDER BY total_val DESC";
$rows2 = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows2 as $r) {
    echo sprintf("%-30s | Prods: %4d | Units: %8.1f | Val: $%12.2f\n", 
        $r['dept'] === '' ? '(VACÍO / NULL)' : $r['dept'], 
        $r['products_with_stock'], 
        $r['total_units'], 
        $r['total_val']);
}
