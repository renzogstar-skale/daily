<?php
require_once __DIR__ . '/bootstrap_cli.php';
$svc = \Grocy\Services\RecallService::GetInstance();
$stock = $svc->FindAffectedStock(30, null, null);
echo "Affected stock for product 30 (Leche entera):\n";
print_r($stock);
