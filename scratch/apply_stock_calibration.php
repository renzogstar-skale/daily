<?php
$db = new PDO('sqlite:data/grocy_es.db');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$DEPARTMENTS = [
    'tecnologia'        => ['Tecnología', ['Tecnología', 'Electrónica y Telefonía', 'Electrónica']],
    'electrodomesticos' => ['Electrodomésticos', ['Electrodomésticos']],
    'alimentos'         => ['Alimentos y Despensa', ['Alimentos', 'Abarrotes']],
    'hogar'             => ['Hogar', ['Hogar']],
    'accesorios'        => ['Accesorios', ['Accesorios']],
    'dairy'             => ['Lácteos y huevos', ['Lácteos y huevos', 'Lácteos']],
    'cuidado_personal'  => ['Cuidado Personal', ['Cuidado Personal', 'Cuidado personal']],
    'bebidas'           => ['Bebidas', ['Bebidas refrigeradas', 'Bebidas']],
    'carnes'            => ['Carnes y aves', ['Carnes', 'Carnes y aves']],
    'fruver'            => ['Frutas y verduras', ['Frutas y verduras']],
    'congelados'        => ['Congelados', ['Congelados']],
    'bakery'            => ['Panadería', ['Panadería']]
];

$deptOf = function (?string $dept) use ($DEPARTMENTS) {
    foreach ($DEPARTMENTS as $k => $d) {
        if (in_array($dept, $d[1], true)) {
            return $k;
        }
    }
    return 'otros';
};

$targetTotal = 14228481.0;
$targetPct = [
    'tecnologia'        => 24.5, // ~$3,485,977.85
    'electrodomesticos' => 20.0, // ~$2,845,696.20
    'alimentos'         => 15.5, // ~$2,205,414.56
    'hogar'             => 11.0, // ~$1,565,132.91
    'accesorios'        => 8.5,  // ~$1,209,420.89
    'dairy'             => 6.5,  // ~$924,851.27
    'cuidado_personal'  => 5.2,  // ~$739,881.01
    'bebidas'           => 4.2,  // ~$597,596.20
    'carnes'            => 2.3,  // ~$327,255.06
    'fruver'            => 1.2,  // ~$170,741.77
    'congelados'        => 0.7,  // ~$99,599.37
    'bakery'            => 0.4   // ~$56,913.92
];

// Current sums
$deptSums = [];
$st = $db->query("SELECT s.id, s.product_id, s.amount, IFNULL(s.price, IFNULL(plp.price, 0)) as cost, p.department
                  FROM stock s
                  JOIN products p ON p.id = s.product_id
                  LEFT JOIN cache__products_last_purchased plp ON plp.product_id = s.product_id");
$stockRows = $st->fetchAll(PDO::FETCH_ASSOC);

foreach ($stockRows as $r) {
    $k = $deptOf($r['department']);
    $deptSums[$k] = ($deptSums[$k] ?? 0) + floatval($r['amount']) * floatval($r['cost']);
}

$factors = [];
foreach ($targetPct as $k => $pct) {
    $cur = $deptSums[$k] ?? 0;
    $tgt = ($pct / 100) * $targetTotal;
    $factors[$k] = $cur > 0 ? ($tgt / $cur) : 1.0;
}

echo "Applying calibration factors...\n";
$db->beginTransaction();

// Update stock price per product department
foreach ($targetPct as $k => $pct) {
    $factor = $factors[$k];
    $depts = $DEPARTMENTS[$k][1];
    $inDepts = "'" . implode("','", $depts) . "'";
    
    // Update stock price
    $db->exec("UPDATE stock SET price = ROUND(price * $factor, 2) WHERE product_id IN (SELECT id FROM products WHERE department IN ($inDepts))");
    
    // Also update cache__products_last_purchased
    $db->exec("UPDATE cache__products_last_purchased SET price = ROUND(price * $factor, 2) WHERE product_id IN (SELECT id FROM products WHERE department IN ($inDepts))");
    
    // Scale sale_items COGS proportionally so rotation and coverage match real retail metrics (rot ~ 1.2x - 2.5x)
    $db->exec("UPDATE sale_items SET total_cost = ROUND(total_cost * $factor * 1.2, 2) WHERE product_id IN (SELECT id FROM products WHERE department IN ($inDepts))");
    $db->exec("UPDATE sale_items SET amount = ROUND(amount * 1.15, 0) WHERE product_id IN (SELECT id FROM products WHERE department IN ($inDepts))");
}

$db->commit();
echo "Calibration committed successfully!\n";

// Verify new sums
$newSums = [];
$newUnits = [];
$newTotal = 0;
$newTotalUnits = 0;
$st2 = $db->query("SELECT s.id, s.product_id, s.amount, IFNULL(s.price, IFNULL(plp.price, 0)) as cost, p.department
                   FROM stock s
                   JOIN products p ON p.id = s.product_id
                   LEFT JOIN cache__products_last_purchased plp ON plp.product_id = s.product_id");
foreach ($st2->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $k = $deptOf($r['department']);
    $v = floatval($r['amount']) * floatval($r['cost']);
    $newSums[$k] = ($newSums[$k] ?? 0) + $v;
    $newUnits[$k] = ($newUnits[$k] ?? 0) + floatval($r['amount']);
    $newTotal += $v;
    $newTotalUnits += floatval($r['amount']);
}

echo "\n=== CALIBRATED RESULTS (Total: $" . number_format($newTotal, 2) . ") ===\n";
echo sprintf("%-25s | %-14s | %-8s | %-10s\n", "Departamento", "Valor", "Part %", "Unidades");
echo str_repeat("-", 65) . "\n";
foreach ($targetPct as $k => $pct) {
    $v = $newSums[$k] ?? 0;
    $u = $newUnits[$k] ?? 0;
    $p = ($v / $newTotal) * 100;
    echo sprintf("%-25s | $%13.2f | %6.1f%% | %10.0f\n", $DEPARTMENTS[$k][0], $v, $p, $u);
}
