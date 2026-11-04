<?php
$db = new PDO('sqlite:data/grocy_es.db');

$DEPARTMENTS = [
    'tecnologia' => ['Tecnología', '#2563EB', ['Tecnología', 'Electrónica y Telefonía', 'Electrónica']],
    'electrodomesticos' => ['Electrodomésticos', '#0D9488', ['Electrodomésticos']],
    'alimentos' => ['Alimentos y Despensa', '#D97706', ['Alimentos', 'Abarrotes']],
    'hogar' => ['Hogar', '#854D0E', ['Hogar']],
    'accesorios' => ['Accesorios', '#6366F1', ['Accesorios']],
    'dairy' => ['Lácteos y huevos', '#FFB800', ['Lácteos y huevos', 'Lácteos']],
    'cuidado_personal' => ['Cuidado Personal', '#EC4899', ['Cuidado Personal', 'Cuidado personal']],
    'bebidas' => ['Bebidas', '#0EA5E9', ['Bebidas refrigeradas', 'Bebidas']],
    'carnes' => ['Carnes y aves', '#E11D48', ['Carnes', 'Carnes y aves']],
    'fruver' => ['Frutas y verduras', '#16A34A', ['Frutas y verduras']],
    'congelados' => ['Congelados', '#38BDF8', ['Congelados']],
    'bakery' => ['Panadería', '#8B5CF6', ['Panadería']],
    'otros' => ['Otros', '#64748B', ['General', 'Otros']]
];

$deptOf = function (?string $dept) use ($DEPARTMENTS) {
    foreach ($DEPARTMENTS as $k => $d) {
        if ($d[2] !== null && in_array($dept, $d[2], true)) {
            return $k;
        }
    }
    return 'otros';
};

$sql = "SELECT s.id, s.product_id, s.amount, IFNULL(s.price, IFNULL(plp.price, 0)) as cost, p.department
        FROM stock s
        JOIN products p ON p.id = s.product_id
        LEFT JOIN cache__products_last_purchased plp ON plp.product_id = s.product_id";
$lots = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);

// Current values by dept
$currentVal = [];
$currentUnits = [];
foreach ($lots as $l) {
    $k = $deptOf($l['department']);
    $v = floatval($l['amount']) * floatval($l['cost']);
    $currentVal[$k] = ($currentVal[$k] ?? 0) + $v;
    $currentUnits[$k] = ($currentUnits[$k] ?? 0) + floatval($l['amount']);
}

$targetTotal = 14228481.0;
// Target percentages
$targetPct = [
    'tecnologia' => 24.5,
    'electrodomesticos' => 20.0,
    'alimentos' => 15.5,
    'hogar' => 11.0,
    'accesorios' => 8.5,
    'dairy' => 6.5,
    'cuidado_personal' => 5.2,
    'bebidas' => 4.2,
    'carnes' => 2.3,
    'fruver' => 1.2,
    'congelados' => 0.7,
    'bakery' => 0.3,
    'otros' => 0.1
];

echo "=== DEPARTMENT PROJECTIONS ===\n";
echo sprintf("%-22s | %-12s | %-12s | %-8s | %-8s\n", "Departamento", "Actual Val", "Target Val", "Target %", "Factor");
echo str_repeat("-", 70) . "\n";
foreach ($targetPct as $k => $pct) {
    $tgtVal = ($pct / 100) * $targetTotal;
    $cVal = $currentVal[$k] ?? 0;
    $factor = $cVal > 0 ? ($tgtVal / $cVal) : 1.0;
    echo sprintf("%-22s | $%10.2f | $%10.2f | %6.1f%% | %7.3fx\n", 
        $DEPARTMENTS[$k][0], $cVal, $tgtVal, $pct, $factor);
}
