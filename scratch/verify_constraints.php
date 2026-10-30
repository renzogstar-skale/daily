<?php
require_once __DIR__ . '/../tests/dairy/bootstrap.php';
$db = Grocy\Services\DatabaseService::getInstance()->GetDbConnection();

$hcomps = $db->query("SELECT * FROM workforce_monthly_schedules WHERE shift_code='COMP' AND shift_date IN ('2026-11-02', '2026-11-16')")->fetchAll(PDO::FETCH_ASSOC);
echo "Compensatories on Nov 2 or Nov 16 holidays: " . count($hcomps) . "\n";
foreach ($hcomps as $c) {
    echo "  CONFLICT: {$c['payroll_code']} on {$c['shift_date']}\n";
}

$nights = $db->query("SELECT shift_date, count(*) as c FROM workforce_monthly_schedules WHERE shift_code LIKE '7N%' AND shift_date BETWEEN '2026-11-01' AND '2026-11-30' GROUP BY shift_date HAVING c != 1")->fetchAll(PDO::FETCH_ASSOC);
echo "Days with night shift != 1: " . count($nights) . "\n";

$sundays = ['2026-11-01', '2026-11-08', '2026-11-15', '2026-11-22', '2026-11-29'];
foreach ($sundays as $sun) {
    $free = $db->query("SELECT count(*) FROM workforce_monthly_schedules WHERE shift_date='$sun' AND (shift_code='LIBRE' OR shift_code='VC')")->fetchColumn();
    $work = $db->query("SELECT count(*) FROM workforce_monthly_schedules WHERE shift_date='$sun' AND shift_code NOT IN ('LIBRE', 'VC')")->fetchColumn();
    echo "Sunday $sun: $work working, $free resting/vacation (Total: " . ($work+$free) . ")\n";
}
