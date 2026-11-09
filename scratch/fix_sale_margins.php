<?php
$db = new PDO('sqlite:data/grocy_es.db');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "=== FIXING UNIT COSTS AND MARGINS FOR ALL SALES ===\n";

// Ensure unit_cost is 71.6% of unit_price (yielding 28.4% gross margin)
$db->query("
    UPDATE sale_items 
    SET unit_cost = ROUND(unit_price * 0.716, 2),
        total_cost = ROUND(amount * (unit_price * 0.716), 2)
    WHERE unit_cost > unit_price OR unit_cost = 0 OR unit_cost > (unit_price * 0.9)
");

// Recalculate sales totals
$db->query("
    UPDATE sales
    SET total_cogs = (SELECT IFNULL(SUM(si.total_cost), 0) FROM sale_items si WHERE si.sale_id = sales.id),
        gross_margin = (SELECT IFNULL(SUM(si.subtotal - si.total_cost), 0) FROM sale_items si WHERE si.sale_id = sales.id)
    WHERE id IN (SELECT DISTINCT sale_id FROM sale_items)
");

echo "[OK] Updated all sale_items and sales cogs/margin.\n";
