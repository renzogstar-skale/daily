<?php
$db = new PDO('sqlite:data/grocy_es.db');

$dept = 'Lácteos y huevos';
$stmt = $db->prepare("
    SELECT 
        IFNULL(pg.name, 'Sin categoría') as category,
        COUNT(DISTINCT p.id) as products_count,
        SUM(si.amount) as units,
        SUM(si.subtotal) as net,
        SUM(IFNULL(NULLIF(si.total_cost, 0), si.amount * IFNULL(si.unit_cost, 0))) as cogs
    FROM sale_items si
    JOIN sales s ON s.id = si.sale_id
    JOIN products p ON p.id = si.product_id
    LEFT JOIN product_groups pg ON pg.id = p.product_group_id
    WHERE s.status = 'completed' AND p.department IN ('Lácteos', 'Lácteos y huevos')
    GROUP BY pg.id
    ORDER BY net DESC
");
$stmt->execute();
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

