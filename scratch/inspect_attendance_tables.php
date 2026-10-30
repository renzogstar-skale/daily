<?php
$db = new PDO('sqlite:data/grocy_es.db');
$stmt = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND (name LIKE '%attend%' OR name LIKE '%shift%' OR name LIKE '%workforce%' OR name LIKE '%time%' OR name LIKE '%punch%' OR name LIKE '%employee%') ORDER BY name");
$tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
echo "Matching tables:\n";
print_r($tables);

foreach ($tables as $t) {
    echo "\n--- Schema: $t ---\n";
    $cols = $db->query("PRAGMA table_info($t)")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($cols as $c) {
        echo "  {$c['name']} ({$c['type']})\n";
    }
}
