<?php
$db = new PDO('sqlite:data/grocy_es.db');
$cols = $db->query("PRAGMA table_info(branches)")->fetchAll(PDO::FETCH_ASSOC);
echo "=== BRANCHES COLS ===\n";
foreach ($cols as $c) echo $c['name'] . "\n";

$branches = $db->query("SELECT * FROM branches")->fetchAll(PDO::FETCH_ASSOC);
print_r($branches);

$allTables = $db->query("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
echo "=== ALL TABLES (" . count($allTables) . ") ===\n";
print_r($allTables);

