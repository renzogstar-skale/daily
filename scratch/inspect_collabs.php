<?php
$db = new PDO('sqlite:data/grocy_es.db');
echo "=== workforce_planilla_collaborators PRAGMA ===\n";
$stmt = $db->query("PRAGMA table_info(workforce_planilla_collaborators)");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

echo "=== COUNT COLLABORATORS ===\n";
$stmt = $db->query("SELECT COUNT(*) FROM workforce_planilla_collaborators");
echo $stmt->fetchColumn() . "\n";

echo "=== SAMPLE COLLABORATORS ===\n";
$stmt = $db->query("SELECT * FROM workforce_planilla_collaborators LIMIT 5");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
