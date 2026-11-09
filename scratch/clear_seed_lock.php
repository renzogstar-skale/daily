<?php
$db = new PDO('sqlite:data/grocy_es.db');
$db->exec("DELETE FROM daily_seed_runs WHERE code LIKE '%_lock'");
echo "Cleared lock. Seed runs:\n";
$runs = $db->query("SELECT * FROM daily_seed_runs")->fetchAll(PDO::FETCH_ASSOC);
print_r($runs);

