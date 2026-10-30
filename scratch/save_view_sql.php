<?php
$db = new PDO("sqlite:data/grocy_es.db");
$stmt = $db->query("SELECT sql FROM sqlite_master WHERE name = 'user_permissions_resolved'");
file_put_contents('scratch/view_sql.txt', $stmt->fetchColumn());
echo "Saved to scratch/view_sql.txt\n";
