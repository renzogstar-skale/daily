<?php
$db = new PDO('sqlite:data/grocy_es.db');
$branches = $db->query("SELECT id, name, code FROM branches ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
foreach ($branches as $b) {
    echo "ID {$b['id']}: {$b['name']} ({$b['code']})\n";
}

$suppliersCount = $db->query("SELECT COUNT(*) FROM shopping_locations")->fetchColumn();
echo "Suppliers (shopping_locations): $suppliersCount\n";

