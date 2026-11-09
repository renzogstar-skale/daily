<?php
$db = new PDO('sqlite:data/grocy_es.db');
$stmt = $db->query("SELECT id, sale_number, status, created_timestamp, total_amount FROM sales WHERE status = 'cancelled'");
$cancelled = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "Cancelled sales count: " . count($cancelled) . "\n";
print_r($cancelled);

$stmt2 = $db->query("SELECT min(created_timestamp), max(created_timestamp), count(*) FROM sales");
print_r($stmt2->fetch(PDO::FETCH_ASSOC));

