<?php
$db = new PDO('sqlite:data/grocy_es.db');

$DEPARTMENTS = [
    'tecnologia' => ['Tecnología', '#2563EB', ['Tecnología', 'Electrónica y Telefonía', 'Electrónica']],
    'electrodomesticos' => ['Electrodomésticos', '#0D9488', ['Electrodomésticos']],
    'accesorios' => ['Accesorios', '#6366F1', ['Accesorios']],
    'alimentos' => ['Alimentos y Despensa', '#D97706', ['Alimentos', 'Abarrotes']],
    'hogar' => ['Hogar', '#854D0E', ['Hogar']],
    'cuidado_personal' => ['Cuidado Personal', '#EC4899', ['Cuidado Personal', 'Cuidado personal']],
    'dairy' => ['Lácteos y huevos', '#FFB800', ['Lácteos y huevos', 'Lácteos']],
    'bebidas' => ['Bebidas', '#0EA5E9', ['Bebidas refrigeradas', 'Bebidas']],
    'carnes' => ['Carnes', '#E11D48', ['Carnes', 'Carnes y aves']],
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

$sql = "SELECT p.department, SUM(s.amount) as total_units, SUM(s.amount * IFNULL(s.price, IFNULL(plp.price, 0))) as total_val
        FROM stock s
        JOIN products p ON p.id = s.product_id
        LEFT JOIN cache__products_last_purchased plp ON plp.product_id = s.product_id
        GROUP BY p.department";
$rows = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);

$byDept = [];
$totalVal = 0;
$totalUnits = 0;
foreach ($rows as $r) {
    $k = $deptOf($r['department']);
    $byDept[$k]['key'] = $k;
    $byDept[$k]['name'] = $DEPARTMENTS[$k][0];
    $byDept[$k]['val'] = ($byDept[$k]['val'] ?? 0) + floatval($r['total_val']);
    $byDept[$k]['units'] = ($byDept[$k]['units'] ?? 0) + floatval($r['total_units']);
    $totalVal += floatval($r['total_val']);
    $totalUnits += floatval($r['total_units']);
}

uasort($byDept, fn($a, $b) => $b['val'] <=> $a['val']);

echo sprintf("TOTAL VAL: $%.2f | UNITS: %.0f\n\n", $totalVal, $totalUnits);
echo sprintf("%-25s | %-15s | %-8s | %-10s\n", "Departamento", "Valor", "% Part", "Unidades");
echo str_repeat("-", 65) . "\n";
foreach ($byDept as $d) {
    $pct = ($d['val'] / $totalVal) * 100;
    echo sprintf("%-25s | $%14.2f | %6.2f%% | %10.0f\n", $d['name'], $d['val'], $pct, $d['units']);
}
