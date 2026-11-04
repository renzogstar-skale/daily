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

$byDeptLots = [];
foreach ($lots as $l) {
    $k = $deptOf($l['department']);
    $byDeptLots[$k][] = $l;
}

$targetTotal = 14228481.0;
$targetPct = [
    'tecnologia'        => 24.5, // ~$3,485,977
    'electrodomesticos' => 20.0, // ~$2,845,696
    'alimentos'         => 15.5, // ~$2,205,414
    'hogar'             => 11.0, // ~$1,565,132
    'accesorios'        => 8.5,  // ~$1,209,420
    'dairy'             => 6.5,  // ~$924,851
    'cuidado_personal'  => 5.2,  // ~$739,881
    'bebidas'           => 4.2,  // ~$597,596
    'carnes'            => 2.3,  // ~$327,255
    'fruver'            => 1.2,  // ~$170,741
    'congelados'        => 0.7,  // ~$99,599
    'bakery'            => 0.3,  // ~$42,685
    'otros'             => 0.1   // ~$14,228
];

echo "Counts of lots per dept:\n";
foreach ($DEPARTMENTS as $k => $d) {
    echo sprintf("%-22s: %4d lots\n", $d[0], count($byDeptLots[$k] ?? []));
}
