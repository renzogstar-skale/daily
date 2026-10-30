<?php
$db = new PDO("sqlite:data/grocy_es.db");
$stmt = $db->query("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name");
$tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
echo "Total tables in grocy_es.db: " . count($tables) . "\n";
echo implode(", ", $tables) . "\n";
