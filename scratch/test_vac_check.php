<?php
require_once __DIR__ . '/../tests/dairy/bootstrap.php';
$db = Grocy\Services\DatabaseService::getInstance()->GetDbConnection();

$vacStmt = $db->prepare("SELECT * FROM workforce_vacations WHERE status = 'approved' AND start_date <= '2026-11-30' AND end_date >= '2026-11-01'");
$vacStmt->execute();
$vacations = $vacStmt->fetchAll(PDO::FETCH_ASSOC);

print_r($vacations);

foreach ($vacations as $v) {
    $pcode = '38042452';
    $dStr = '2026-11-01';
    $m = ($v['payroll_code'] === $pcode && $v['start_date'] <= $dStr && $v['end_date'] >= $dStr);
    echo "Check {$v['payroll_code']} for $pcode on $dStr: " . ($m ? "MATCH" : "NO") . "\n";
}
