<?php
define('GROCY_DATAPATH', __DIR__ . '/../data');
require __DIR__ . '/../app.php';
$db = \Grocy\Services\DatabaseService::GetInstance()->GetDbConnectionRaw();
$rows = $db->query("SELECT id, name, tax_id, email, phone, branch_id FROM customers LIMIT 8")->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($rows, JSON_PRETTY_PRINT);
