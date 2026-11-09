<?php
$db = new PDO('sqlite:data/grocy_es.db');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "--- TASKS SCHEMA ---\n";
echo $db->query("SELECT sql FROM sqlite_master WHERE name = 'tasks'")->fetchColumn() . "\n";

echo "--- TASKS COUNT ---\n";
echo "Count: " . $db->query("SELECT count(*) FROM tasks")->fetchColumn() . "\n";

echo "--- TASKS ROWS ---\n";
$rows = $db->query("SELECT * FROM tasks LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
print_r($rows);

