<?php
$db = new PDO('sqlite:data/grocy_es.db');
$c = $db->query("SELECT count(*) FROM sales WHERE date(created_timestamp) = '2026-11-04'")->fetchColumn();
echo "Sales on 2026-11-04 so far: $c\n";

$sum = $db->query("SELECT IFNULL(SUM(total_amount), 0) FROM sales WHERE date(created_timestamp) = '2026-11-04'")->fetchColumn();
echo "Total sales amount today: $$sum\n";

$until = $db->query("SELECT run_at FROM daily_seed_runs WHERE code = 'daily_company_v1_until'")->fetchColumn();
echo "Seed until: $until\n";

