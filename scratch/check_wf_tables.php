<?php
$db = new PDO('sqlite:data/grocy_es.db');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$tables = ['workforce_shift_audit_log', 'workforce_collaborators', 'workforce_shifts'];
foreach ($tables as $t) {
    $exists = $db->query("SELECT sql FROM sqlite_master WHERE name='$t'")->fetchColumn();
    echo "--- $t ---\n" . ($exists ?: "DOES NOT EXIST") . "\n";
}

