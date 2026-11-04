<?php
/**
 * Motor de programación: continuidad 3×1 entre meses, descanso, ausencias, nocturnos, compensatorios, borrador sin efecto
 * sobre lo oficial, bloqueo de meses publicados y aprobación con sincronización.
 */

use Grocy\Services\WorkforceScheduleEngineService;

require __DIR__ . '/bootstrap.php';

$en = WorkforceScheduleEngineService::GetInstance();
$uid = GROCY_USER_ID;
$fp = fn() => md5(json_encode($db->query('SELECT payroll_code, shift_date, shift_code, status FROM workforce_monthly_schedules ORDER BY 1, 2')->fetchAll(PDO::FETCH_ASSOC)));

foreach ([1, 9] as $branch)
{
	echo "\n== Sucursal $branch · 2026-12 ==\n";
	$before = $fp();
	$live = $en->Validate($branch, 2026, 12);
	echo '   oficial: ' . json_encode($live['by_type']) . "\n";
	$d = $en->BuildDraft($branch, 2026, 12, $uid);
	$r = $d['report'];
	echo '   borrador: ' . json_encode($r['by_type']) . ' cobertura ' . json_encode($r['coverage']['summary']) . ' equidad ' . $r['equity']['average_pct'] . " cambios " . json_encode($d['changes']) . "\n";
	ok('Borrador no toca lo oficial', $fp() === $before);
	ok('Sin domingos > 3 seguidos', ($r['by_type']['sunday_3x1'] ?? 0) === 0, $r['by_type']['sunday_3x1'] ?? 0);
	ok('Descanso mínimo respetado', ($r['by_type']['min_rest'] ?? 0) === 0, $r['by_type']['min_rest'] ?? 0);
	ok('Sin turnos durante ausencias', ($r['by_type']['absence_conflict'] ?? 0) === 0);
	ok('Máximo de nocturnos', ($r['by_type']['night_cap'] ?? 0) === 0);
	ok('Compensatorios en día válido', ($r['by_type']['comp_invalid_day'] ?? 0) === 0);
	ok('Horas semanales', ($r['by_type']['weekly_hours'] ?? 0) === 0, $r['by_type']['weekly_hours'] ?? 0);
	$dup = $db->query("SELECT COUNT(*) FROM (SELECT 1 FROM workforce_schedule_draft_rows WHERE draft_id = {$d['draft_id']} GROUP BY payroll_code, shift_date HAVING COUNT(*) > 1)")->fetchColumn();
	ok('Sin duplicados', intval($dup) === 0);
	$b = $en->Balance($d['draft_id']);
	echo "   equilibrio: {$b['swaps']} cambios, equidad {$b['report']['equity']['average_pct']}\n";
	ok('Equilibrio no rompe reglas legales', $b['report']['counts']['legal'] === 0, json_encode($b['report']['by_type']));
	ok('Equidad no empeora', $b['report']['equity']['average_pct'] >= $r['equity']['average_pct']);
}

echo "\n== Aprobación ==\n";
$d = $en->BuildDraft(1, 2026, 12, $uid);
expectError('Mes publicado sin reabrir no se aprueba', fn() => $en->Approve($d['draft_id'], $uid, true, true));
expectError('Reabrir exige motivo', fn() => $en->Reopen(1, 2026, 12, 'corto', $uid));
$en->Reopen(1, 2026, 12, 'Corrección de incumplimientos del 3x1', $uid);
$other = $db->query("SELECT COUNT(*) FROM workforce_monthly_schedules m JOIN workforce_planilla_collaborators c ON c.payroll_code = m.payroll_code WHERE c.branch_id = 9 AND m.shift_date LIKE '2026-12%'")->fetchColumn();
$res = $en->Approve($d['draft_id'], $uid, true, true);
ok('Borrador aprobado', $res['status'] === 'approved');
ok('Otra sucursal intacta', $db->query("SELECT COUNT(*) FROM workforce_monthly_schedules m JOIN workforce_planilla_collaborators c ON c.payroll_code = m.payroll_code WHERE c.branch_id = 9 AND m.shift_date LIKE '2026-12%'")->fetchColumn() === $other);
ok('Lo oficial queda sin incumplimientos legales', $en->Validate(1, 2026, 12)['counts']['legal'] === 0, json_encode($en->Validate(1, 2026, 12)['by_type']));
ok('Compensatorio no llega como turno', intval($db->query("SELECT COUNT(*) FROM employee_shifts WHERE branch_id = 1 AND shift_date LIKE '2026-12%' AND start_time = end_time")->fetchColumn()) === 0);
expectError('Un borrador aprobado no se aprueba dos veces', fn() => $en->Approve($d['draft_id'], $uid, true, true));

echo "\n== Continuidad 2027-01 ==\n";
$n = $en->BuildDraft(1, 2027, 1, $uid);
ok('Enero continúa el 3×1 de diciembre', ($n['report']['by_type']['sunday_3x1'] ?? 0) === 0);
$sun = $db->query("SELECT r.payroll_code, r.shift_date, r.shift_type FROM (SELECT payroll_code, shift_date, shift_type FROM workforce_monthly_schedules WHERE is_sunday = 1 AND shift_date LIKE '2026-12%' UNION ALL SELECT payroll_code, shift_date, shift_type FROM workforce_schedule_draft_rows WHERE draft_id = {$n['draft_id']} AND is_sunday = 1) r JOIN workforce_planilla_collaborators c ON c.payroll_code = r.payroll_code WHERE c.branch_id = 1 ORDER BY 1, 2")->fetchAll(PDO::FETCH_ASSOC);
$runs = [];
$max = 0;
foreach ($sun as $s)
{
	$runs[$s['payroll_code']] = in_array($s['shift_type'], ['APERTURA', 'CIERRE', 'NOCHE', 'INTERMEDIO', 'PARCIAL_MANANA', 'PARCIAL_MEDIO', 'PARCIAL_TARDE'], true) ? ($runs[$s['payroll_code']] ?? 0) + 1 : 0;
	$max = max($max, $runs[$s['payroll_code']]);
}
ok('Máximo 3 domingos seguidos entre meses', $max <= 3, $max);

echo "\n$count verificaciones, $fails fallidas\n";
exit($fails > 0 ? 1 : 0);
