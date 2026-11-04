<?php
$db = new PDO('sqlite:data/grocy_es.db');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$targetTotal = 14228481.0;

// Target distribution and healthy target coverage days (between 12 and 45 days)
$targets = [
    'Tecnología'               => ['val' => 0.245 * $targetTotal, 'cov' => 28.0],
    'Electrodomésticos'        => ['val' => 0.200 * $targetTotal, 'cov' => 32.0],
    'Alimentos'                => ['val' => 0.150 * $targetTotal, 'cov' => 24.0],
    'Abarrotes'                => ['val' => 0.005 * $targetTotal, 'cov' => 20.0],
    'Hogar'                    => ['val' => 0.110 * $targetTotal, 'cov' => 35.0],
    'Accesorios'               => ['val' => 0.085 * $targetTotal, 'cov' => 30.0],
    'Lácteos'                  => ['val' => 0.045 * $targetTotal, 'cov' => 18.0],
    'Lácteos y huevos'         => ['val' => 0.020 * $targetTotal, 'cov' => 16.0],
    'Cuidado Personal'         => ['val' => 0.052 * $targetTotal, 'cov' => 26.0],
    'Bebidas'                  => ['val' => 0.038 * $targetTotal, 'cov' => 22.0],
    'Bebidas refrigeradas'     => ['val' => 0.004 * $targetTotal, 'cov' => 18.0],
    'Carnes'                   => ['val' => 0.018 * $targetTotal, 'cov' => 14.0],
    'Carnes y aves'            => ['val' => 0.005 * $targetTotal, 'cov' => 15.0],
    'Frutas y verduras'        => ['val' => 0.012 * $targetTotal, 'cov' => 12.0],
    'Congelados'               => ['val' => 0.007 * $targetTotal, 'cov' => 25.0],
    'Panadería'                => ['val' => 0.004 * $targetTotal, 'cov' => 10.0]
];

$date28 = date('Y-m-d', strtotime('-28 days'));

echo "Calculating healthy stock levels and prices...\n";
$db->beginTransaction();

foreach ($targets as $dept => $cfg) {
    $targetVal = $cfg['val'];
    $targetCov = $cfg['cov'];
    
    // Get 28d sold units and COGS for this department
    $soldRow = $db->query("SELECT SUM(si.amount) as sold_units, SUM(si.total_cost) as cogs
                           FROM sale_items si JOIN sales s ON s.id = si.sale_id JOIN products p ON p.id = si.product_id
                           WHERE s.status = 'completed' AND date(s.created_timestamp) >= '$date28' AND p.department = '$dept'")->fetch(PDO::FETCH_ASSOC);
    
    $soldUnits = floatval($soldRow['sold_units'] ?? 0);
    $soldCogs = floatval($soldRow['cogs'] ?? 0);
    
    // If sold units is low, boost sale_items so retail velocity is realistic
    $dailySold = $soldUnits / 28.0;
    if ($dailySold <= 0) {
        $dailySold = 10.0;
    }
    
    // Target stock units based on desired coverage: Stock = dailySold * targetCov
    // If daily sold was small (e.g. 15 units/day), let's ensure daily sold matches a multi-store chain
    // For 10 stores, daily sold for a whole department should be at least ~30 to 100 units
    $idealDaily = max($dailySold, ($targetVal / 100) / 28.0);
    $targetStockUnits = round($idealDaily * $targetCov);
    
    // Current stock rows
    $stockRows = $db->query("SELECT s.id, s.amount, s.price FROM stock s JOIN products p ON p.id = s.product_id WHERE p.department = '$dept'")->fetchAll(PDO::FETCH_ASSOC);
    $curUnits = array_sum(array_column($stockRows, 'amount'));
    
    if ($curUnits > 0 && count($stockRows) > 0) {
        $unitFactor = max(0.1, $targetStockUnits / $curUnits);
        
        // Update amounts
        $db->exec("UPDATE stock SET amount = MAX(1, ROUND(amount * $unitFactor, 0)) WHERE product_id IN (SELECT id FROM products WHERE department = '$dept')");
        
        // Now calculate new sum of amounts
        $newUnits = $db->query("SELECT SUM(amount) FROM stock WHERE product_id IN (SELECT id FROM products WHERE department = '$dept')")->fetchColumn();
        
        // Now calculate price so that newUnits * price = targetVal
        $curVal = $db->query("SELECT SUM(s.amount * s.price) FROM stock s JOIN products p ON p.id = s.product_id WHERE p.department = '$dept'")->fetchColumn();
        if ($curVal > 0) {
            $priceFactor = $targetVal / $curVal;
            $db->exec("UPDATE stock SET price = ROUND(price * $priceFactor, 2) WHERE product_id IN (SELECT id FROM products WHERE department = '$dept')");
            $db->exec("UPDATE cache__products_last_purchased SET price = ROUND(price * $priceFactor, 2) WHERE product_id IN (SELECT id FROM products WHERE department = '$dept')");
        }
        
        // Scale 28d sales to match desired rotation (~1.2x - 2.5x)
        $targetPeriodCogs = $targetVal * (28.0 / $targetCov);
        if ($soldCogs > 0) {
            $cogsFactor = $targetPeriodCogs / $soldCogs;
            $db->exec("UPDATE sale_items SET total_cost = ROUND(total_cost * $cogsFactor, 2) WHERE product_id IN (SELECT id FROM products WHERE department = '$dept')");
        }
        if ($soldUnits > 0) {
            $targetSoldUnits = ($newUnits / $targetCov) * 28.0;
            $soldUnitFactor = $targetSoldUnits / $soldUnits;
            $db->exec("UPDATE sale_items SET amount = MAX(1, ROUND(amount * $soldUnitFactor, 0)) WHERE product_id IN (SELECT id FROM products WHERE department = '$dept')");
        }
    }
}

$db->commit();
echo "Committed perfectly balanced stock and velocity metrics!\n";
