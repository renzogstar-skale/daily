<?php
$db = new PDO('sqlite:data/grocy_es.db');
echo "=== BRANCHES PRAGMA ===\n";
print_r($db->query('PRAGMA table_info(branches)')->fetchAll(PDO::FETCH_ASSOC));
echo "=== CURRENT BRANCHES ===\n";
print_r($db->query('SELECT * FROM branches')->fetchAll(PDO::FETCH_ASSOC));
