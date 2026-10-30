<?php
$db = new PDO("sqlite:data/grocy_es.db");
$cols = $db->query('PRAGMA table_info(branches)')->fetchAll(PDO::FETCH_ASSOC);
foreach($cols as $c) echo $c['name'] . ', ';
echo "\n";
$res = $db->query("SELECT id, name, code, active FROM branches ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
print_r($res);
