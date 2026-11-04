<?php
$db = new PDO('sqlite:data/grocy_es.db');
$cols = $db->query('PRAGMA table_info(device_units)')->fetchAll(PDO::FETCH_ASSOC);
echo "=== device_units cols ===\n";
foreach ($cols as $c) echo "  {$c['name']} ({$c['type']})\n";
$first = $db->query('SELECT * FROM device_units LIMIT 2')->fetchAll(PDO::FETCH_ASSOC);
print_r($first);
