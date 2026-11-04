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

$targetTotal = 14228481.0;
$targetPct = [
    'tecnologia'        => 24.5, // $3,485,977.85
    'electrodomesticos' => 20.0, // $2,845,696.20
    'alimentos'         => 15.5, // $2,205,414.56
    'hogar'             => 11.0, // $1,565,132.91
    'accesorios'        => 8.5,  // $1,209,420.89
    'dairy'             => 6.5,  // $924,851.27
    'cuidado_personal'  => 5.2,  // $739,881.01
    'bebidas'           => 4.2,  // $597,596.20
    'carnes'            => 2.3,  // $327,255.06
    'fruver'            => 1.2,  // $170,741.77
    'congelados'        => 0.7,  // $99,599.37
    'bakery'            => 0.3,  // $42,685.44
    'otros'             => 0.1   // $14,228.48
];

// Calculate current sum per dept
$sums = [];
$rows = $db->query("SELECT s.id, s.product_id, s.amount, IFNULL(s.price, IFNULL(plp.price, 0)) as cost, p.department
                    FROM stock s
                    JOIN products p ON p.id = s.product_id
                    LEFT JOIN cache__products_last_purchased plp ON plp.product_id = s.product_id")->fetchAll(PDO::FETCH_ASSOC);

foreach ($rows as $r) {
    $k = $deptOf($r['department']);
    $sums[$k] = ($sums[$k] ?? 0) + floatval($r['amount']) * floatval($r['cost']);
}

echo "=== PROPOSED CALIBRATION PREVIEW ===\n";
$newTotal = 0;
foreach ($targetPct as $k => $pct) {
    $c = $sums[$k] ?? 0;
    $t = ($pct / 100) * $targetTotal;
    $f = $c > 0 ? ($t / $c) : 1.0;
    $newTotal += $t;
    echo sprintf("%-22s: Current $%10.2f -> Target $%10.2f (%5.1f%%) [Factor %.4fx]\n", 
        $DEPARTMENTS[$k][0], $c, $t, $pct, $f);
}
echo sprintf("TOTAL: $%12.2f (100.0%%)\n", $newTotal);
