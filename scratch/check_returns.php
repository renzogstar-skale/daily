<?php
$db = new PDO('sqlite:data/grocy_es.db');
$tables = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name LIKE '%return%'")->fetchAll(PDO::FETCH_COLUMN);
print_r($tables);
$salesCancelled = $db->query("SELECT count(*) FROM sales WHERE status = 'cancelled'")->fetchColumn();
echo "Sales with cancelled status: $salesCancelled\n";
