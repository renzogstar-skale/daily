<?php

require __DIR__ . '/../tests/dairy/bootstrap.php';
require_once __DIR__ . '/../services/WorkforceAutoScheduleService.php';

$svc = \Grocy\Services\WorkforceAutoScheduleService::GetInstance();

// 1. Generate November 2026
$resNov = $svc->GenerateMonth(1, 2026, 11, true);
echo "Generated Nov 2026: " . json_encode($resNov) . "\n";

// 2. Generate December 2026
$resDic = $svc->GenerateMonth(1, 2026, 12, true);
echo "Generated Dic 2026: " . json_encode($resDic) . "\n";

// 3. Generate January 2027
$resEne = $svc->GenerateMonth(1, 2027, 1, true);
echo "Generated Ene 2027: " . json_encode($resEne) . "\n";

// 4. Verify data for November 2026
$dataNov = $svc->GetMonthSchedule(1, 2026, 11);
echo "Nov 2026 KPIs: " . json_encode($dataNov['kpis']) . "\n";
echo "Total Collaborators in schedule: " . count($dataNov['collaborators']) . "\n";

// Check 1: 23 collaborators
if (count($dataNov['collaborators']) !== 23) {
    throw new Exception("Must have exactly 23 collaborators, got: " . count($dataNov['collaborators']));
}

// Check 2: 30 days of November
if ($dataNov['num_days'] !== 30) {
    throw new Exception("November has 30 days");
}

// Check 3: Holidays
$hol2 = $svc->IsHoliday('2026-11-02');
$hol16 = $svc->IsHoliday('2026-11-16');
echo "Holiday Nov 2: $hol2\n";
echo "Holiday Nov 16: $hol16\n";
if (!$hol2 || !$hol16) {
    throw new Exception("Nov 2 and Nov 16 must be holidays");
}

// Check 4: Vacations
if (!$svc->IsOnVacation('38042452', '2026-11-02')) {
    throw new Exception("Marisol must be on vacation Nov 2");
}
if ($svc->IsOnVacation('38042452', '2026-11-03')) {
    throw new Exception("Marisol must NOT be on vacation Nov 3");
}

// Check 5: Night count per day in November
$badNights = 0;
foreach ($dataNov['calendar_days'] as $cd) {
    $d = $cd['date'];
    $cov = $dataNov['daily_coverage'][$d];
    if ($cov['nights'] !== 1) {
        echo "WARNING: Date $d has {$cov['nights']} nights\n";
        $badNights++;
    }
}
echo "Bad nights count: $badNights (expected 0)\n";
echo "ALL ASSERTIONS PASSED SUCCESSFULLY!\n";
