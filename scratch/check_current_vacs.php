<?php
require_once __DIR__ . '/../tests/dairy/bootstrap.php';
$db = Grocy\Services\DatabaseService::getInstance()->GetDbConnection();

$collabs = [
    '38042452' => 'Méndez Sánchez Marisol (vac hasta 2 nov)',
    '38031757' => 'Jara Pava Julio César (vac hasta 3 nov)',
    '38039919' => 'Triana Carreño Luciana (vac 3-23 nov)',
    '38063818' => 'Rubio Daza Brayer Stifer (vac 3-23 nov)'
];

foreach ($collabs as $code => $label) {
    echo "=== $label ($code) ===\n";
    $shifts = $db->query("SELECT shift_date, shift_code FROM workforce_monthly_schedules WHERE payroll_code='$code' AND shift_date BETWEEN '2026-11-01' AND '2026-11-06' ORDER BY shift_date")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($shifts as $s) {
        echo "  {$s['shift_date']}: {$s['shift_code']}\n";
    }
}
