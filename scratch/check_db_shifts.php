<?php
require_once __DIR__ . '/../tests/dairy/bootstrap.php';
$db = Grocy\Services\DatabaseService::getInstance()->GetDbConnection();

echo "Méndez (38042452) Nov 1-3:\n";
$r = $db->query("SELECT shift_date, shift_code FROM workforce_monthly_schedules WHERE payroll_code='38042452' AND shift_date BETWEEN '2026-11-01' AND '2026-11-05'")->fetchAll(PDO::FETCH_ASSOC);
print_r($r);

echo "Triana (38039919) Nov 1-6:\n";
$r2 = $db->query("SELECT shift_date, shift_code FROM workforce_monthly_schedules WHERE payroll_code='38039919' AND shift_date BETWEEN '2026-11-01' AND '2026-11-06'")->fetchAll(PDO::FETCH_ASSOC);
print_r($r2);
