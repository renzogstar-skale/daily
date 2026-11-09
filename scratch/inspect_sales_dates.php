<?php
$db = new PDO('sqlite:data/grocy_es.db');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "--- sales schema ---\n";
echo $db->query("SELECT sql FROM sqlite_master WHERE name='sales'")->fetchColumn() . "\n";

echo "--- sale_items schema ---\n";
echo $db->query("SELECT sql FROM sqlite_master WHERE name='sale_items'")->fetchColumn() . "\n";

$countToday = $db->query("SELECT count(*) FROM sales WHERE date(created_timestamp) = '2026-11-04'")->fetchColumn();
echo "Sales on 2026-11-04: $countToday\n";

$countYday = $db->query("SELECT count(*) FROM sales WHERE date(created_timestamp) = '2026-11-03'")->fetchColumn();
echo "Sales on 2026-11-03: $countYday\n";

$countWk = $db->query("SELECT count(*) FROM sales WHERE date(created_timestamp) = '2026-10-28'")->fetchColumn();
echo "Sales on 2026-10-28: $countWk\n";

