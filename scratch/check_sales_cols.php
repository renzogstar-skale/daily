<?php
$db = new PDO("sqlite:data/grocy_es.db");
$st = $db->query("PRAGMA table_info(sales)");
echo implode(', ', array_column($st->fetchAll(PDO::FETCH_ASSOC), 'name')) . "\n";
