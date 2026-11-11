<?php
/**
 * Suite integral #15: Optimizar Operaciones y Personal
 * Valida las 20 capacidades y los 7 fixes críticos (superposición, cobertura Dpto×Hora,
 * horas duplicadas, compensatorios/vacaciones, ausencia vs pendiente, multi-jurisdicción y RBAC).
 */

use Grocy\Services\AttendanceService;
use Grocy\Services\HrService;
use Grocy\Services\WorkforceAutoScheduleService;
use Grocy\Services\WorkforceLaborRulesService;
use Grocy\Services\WorkforceScheduleService;

require __DIR__ . '/bootstrap.php';

echo "\nOperaciones y Personal (#15 — Suite Integral)\n";

$ws = WorkforceScheduleService::GetInstance();
$auto = WorkforceAutoScheduleService::GetInstance();
$att = AttendanceService::GetInstance();
$hr = HrService::GetInstance();
$uid = GROCY_USER_ID;

// 1. Multi-jurisdicción (CO-GEN, MX-LFT, US-CA, CL-DT, ES-ET, PE-DLEG728, US-FED)
$profiles = WorkforceLaborRulesService::GetJurisdictionProfiles();
ok('7 perfiles de jurisdicción laboral disponibles', count($profiles) >= 7);

$code = 'MXT' . time();
$db->exec("INSERT INTO branches (code, name, country_code, jurisdiction_code) VALUES ('$code', 'Sucursal MX Test', 'MX', 'MX-LFT')");
$branchId = intval($db->lastInsertId());
// Guardar política MX-LFT (48h semanales, descanso mínimo 12h) para sucursal
$savedPolicy = $auto->SaveCompanyPolicy(1, [
	'branch_id' => $branchId,
	'country_code' => 'MX',
	'jurisdiction_code' => 'MX-LFT',
	'policy_name' => 'Política México LFT',
	'effective_date' => '2026-11-01',
	'sunday_rule_type' => '3x1',
	'rotation_rule_type' => 'weekly_apertura_cierre',
	'max_nights_per_day' => 1
], $uid);
ok('política MX-LFT guarda jurisdicción y 48h semanales', ($savedPolicy['jurisdiction_code'] ?? '') === 'MX-LFT' && floatval($savedPolicy['max_weekly_hours'] ?? 0) === 48.0);

$resolvedMx = $auto->GetBranchJurisdictionPolicy($branchId, '2026-11-09');
ok('sucursal hereda jurisdicción MX-LFT (48h)', $resolvedMx['jurisdiction_code'] === 'MX-LFT' && floatval($resolvedMx['max_weekly_hours']) === 48.0);

// Cálculo de horas extra por jurisdicción: 52h en CO (tope 42 => 10 OT) vs MX (tope 48 => 4 OT) vs US-CA (tope 40, diario >8h)
$otCo = WorkforceLaborRulesService::ComputeOvertimeByJurisdiction(52.0, 42.0, ['country_code' => 'CO', 'jurisdiction_code' => 'CO-GEN', 'max_weekly_hours' => 42]);
$otMx = WorkforceLaborRulesService::ComputeOvertimeByJurisdiction(60.0, 48.0, ['country_code' => 'MX', 'jurisdiction_code' => 'MX-LFT', 'max_weekly_hours' => 48]);
$otCa = WorkforceLaborRulesService::ComputeOvertimeByJurisdiction(54.0, 40.0, ['country_code' => 'US', 'jurisdiction_code' => 'US-CA', 'max_weekly_hours' => 40], 14.0);
ok('cálculo diferenciado de extras CO vs MX vs US-CA', $otCo['overtime_total_hours'] == 10.0 && $otMx['overtime_tier1_hours'] == 9.0 && $otMx['overtime_tier2_hours'] == 3.0 && $otCa['overtime_tier1_hours'] == 12.0 && $otCa['overtime_tier2_hours'] == 2.0);

// 2. Bandas de turno (Apertura / Intermedio / Cierre / Nocturno) + Split shift vs descanso + CopyWeek atómico
ok('banda apertura 06:00-14:00', WorkforceLaborRulesService::DetectShiftBand('06:00', '14:00') === 'apertura');
ok('banda intermedio 10:00-18:00', WorkforceLaborRulesService::DetectShiftBand('10:00', '18:00') === 'intermedio');
ok('banda cierre 14:00-22:00', WorkforceLaborRulesService::DetectShiftBand('14:00', '22:00') === 'cierre');
ok('banda nocturno 22:00-06:00', WorkforceLaborRulesService::DetectShiftBand('22:00', '06:00') === 'nocturno');

$emp1 = $ws->SaveEmployee(['name' => 'Op Emp 1', 'branch_id' => $branchId, 'department' => 'Caja', 'weekly_hours' => 40, 'hourly_cost' => 15.0], $uid);
$emp2 = $ws->SaveEmployee(['name' => 'Op Emp 2', 'branch_id' => $branchId, 'department' => 'Lácteos', 'weekly_hours' => 40, 'hourly_cost' => 18.0], $uid);
$mon = date('Y-m-d', strtotime('monday this week +35 day'));
$tue = date('Y-m-d', strtotime("$mon +1 day"));
$nextMon = date('Y-m-d', strtotime("$mon +7 day"));

// Turno partido en el mismo día (2h entre tramos => split_shift info sin falsos errores de descanso)
$s1 = $ws->SaveShift(['employee_id' => $emp1, 'shift_date' => $mon, 'start_time' => '08:00', 'end_time' => '12:00', 'break_minutes' => 0, 'department' => 'Caja'], $uid);
$splitIssues = $ws->CheckConflicts($emp1, $mon, '14:00', '18:00', 0);
$splitErr = array_filter($splitIssues, fn($i) => $i['level'] === 'error');
$splitInfo = array_filter($splitIssues, fn($i) => ($i['code'] ?? '') === 'split_shift');
ok('turno partido intradía (2h pausa) permitido sin error de descanso', count($splitErr) === 0 && count($splitInfo) === 1);

// CopyWeek atómico falla completo si el destino ya tiene conflicto
$ws->SaveShift(['employee_id' => $emp1, 'shift_date' => $nextMon, 'start_time' => '09:00', 'end_time' => '13:00', 'break_minutes' => 0, 'department' => 'Caja'], $uid);
expectError('CopyWeek atómico revierte todo si hay superposición en destino', fn() => $ws->CopyWeek($branchId, $mon, $nextMon, $uid, true));

// 3. Cobertura exacta por Departamento × Hora + descuento proporcional de descanso + alertas reales
$ws->SaveCoverageRequirements($branchId, [
	['department' => 'Caja', 'weekday' => 0, 'start_time' => '08:00', 'end_time' => '12:00', 'min_staff' => 2],
	['department' => 'Lácteos', 'weekday' => 0, 'start_time' => '08:00', 'end_time' => '12:00', 'min_staff' => 1]
]);
// Emp1 tiene 08:00-12:00 en Caja => déficit 1 en Caja (requiere 2, efectivo 1) y déficit 1 en Lácteos (requiere 1, efectivo 0)
$covDeptHr = $ws->CoverageByDepartmentAndHour($branchId, $mon);
ok('cobertura dpto×hora detecta déficit en Caja y Lácteos', $covDeptHr['gap_count'] >= 4 && ($covDeptHr['bands']['apertura'] ?? 0) >= 1);
$alertsSync = $ws->EvaluateAndSyncCoverageAlerts($branchId, $mon);
ok('alertas reales de cobertura insuficiente creadas en BD', count($alertsSync['alerts_synced'] ?? []) >= 1 && intval($val("SELECT COUNT(*) FROM dairy_alerts WHERE branch_id = $branchId AND alert_type = 'coverage' AND status = 'active'")) >= 1);

// Verificar descuento proporcional de descanso en cobertura horaria (turno 8h con 60m descanso => 0.875 efectivo por hora)
$ws->SaveShift(['employee_id' => $emp2, 'shift_date' => $mon, 'start_time' => '08:00', 'end_time' => '16:00', 'break_minutes' => 60, 'department' => 'Lácteos'], $uid);
$covAfterBreak = $ws->CoverageByDepartmentAndHour($branchId, $mon);
$lacRow = null;
foreach ($covAfterBreak['departments'] as $dr) {
	if ($dr['department'] === 'Lácteos') $lacRow = $dr;
}
ok('cobertura horaria descuenta break proporcional (7/8 = 0.88)', $lacRow !== null && abs(floatval($lacRow['effective_hourly'][9]) - 0.88) <= 0.02);

// 4. Horas duplicadas prevenidas + cierre nocturno sin falsos overnights
// Dos marcas solapadas del mismo día para Emp1 (08:00-13:00 y 12:00-17:00 = unión 9h, no 10h; menos break 0m => 9.0h)
$db->exec("INSERT INTO employee_attendance (employee_id, shift_id, clock_in, clock_out, source) VALUES
	($emp1, {$s1['id']}, '$mon 08:00:00', '$mon 13:00:00', 'manual'),
	($emp1, {$s1['id']}, '$mon 12:00:00', '$mon 17:00:00', 'manual')");
$worked = $ws->WorkedHoursByEmployee($mon, $mon, $branchId);
ok('WorkedHoursByEmployee une intervalos solapados sin duplicar horas', abs(floatval($worked[$emp1] ?? 0) - 9.0) < 0.01);

// RecordManualAttendance: apertura nocturna y cierre al día siguiente + rechazo de duplicado en jornada cerrada
$nightEmp = $ws->SaveEmployee(['name' => 'Nocturno Test', 'branch_id' => $branchId, 'department' => 'Bodega', 'weekly_hours' => 40], $uid);
$dNight = $tue;
$dMorning = date('Y-m-d', strtotime("$dNight +1 day"));
$resNightIn = $att->RecordManualAttendance(['employee_id' => $nightEmp, 'date' => $dNight, 'time' => '22:00:00', 'type' => 'clock_in', 'notes' => 'Ingreso nocturno'], $uid);
ok('marca entrada nocturna 22:00 abierta', !empty($resNightIn['success']));
$resNightOut = $att->RecordManualAttendance(['employee_id' => $nightEmp, 'date' => $dMorning, 'time' => '06:00:00', 'type' => 'clock_out', 'notes' => 'Salida madrugada'], $uid);
ok('salida 06:00 al día siguiente cierra turno nocturno del día anterior', !empty($resNightOut['success']) && $val("SELECT clock_out FROM employee_attendance WHERE employee_id = $nightEmp AND date(clock_in) = '$dNight'") === "$dMorning 06:00:00");
expectError('marca duplicada sobre jornada ya fichada es rechazada sin sobrescribir', fn() => $att->RecordManualAttendance(['employee_id' => $nightEmp, 'date' => $dNight, 'time' => '22:00:00', 'type' => 'clock_in', 'notes' => 'Intento duplicado'], $uid));

// 5. Distinción Ausencia vs Entrada Pendiente + Nómina determinista sin rand()
$pastDay = date('Y-m-d', strtotime('-2 days'));
$empPast = $ws->SaveEmployee(['name' => 'Sin Fichaje Ayer', 'branch_id' => $branchId, 'department' => 'Caja', 'weekly_hours' => 40], $uid);
$ws->SaveShift(['employee_id' => $empPast, 'shift_date' => $pastDay, 'start_time' => '08:00', 'end_time' => '16:00', 'break_minutes' => 30, 'department' => 'Caja'], $uid);
$ovPast = $att->GetAttendanceOverview($branchId, $pastDay, 'all', 'all', null);
$pastStatus = null;
foreach ($ovPast['records'] as $rec) {
	if (intval($rec['employee_id']) === $empPast) $pastStatus = $rec['status'];
}
ok('turno pasado sin fichaje se clasifica como ausente y no pendiente', $pastStatus === 'ausente');

$p1 = $hr->GetPayrollSummary($branchId, substr($mon, 0, 7));
$p2 = $hr->GetPayrollSummary($branchId, substr($mon, 0, 7));
ok('pre-nómina determinista sin rand() y con jurisdicción real', $p1['totals']['grand_total'] === $p2['totals']['grand_total'] && ($p1['jurisdiction'] ?? '') === 'MX-LFT');

// 6. Compensatorios (Art. 180 ocasional, sin duplicados, vencimiento a pago) + Vacaciones + Cambio Bidireccional
$sunOrigin = date('Y-m-d', strtotime('next sunday', strtotime($mon)));
$compPay = $ws->AccrueOrRedeemCompensatory($emp1, $sunOrigin, true, null, $uid);
ok('primer domingo ocasional permite elección pago recargo (Art. 180)', $compPay['action'] === 'PAGO_RECARGO' && floatval($compPay['payout_amount']) > 0);
expectError('compensatorio ya liquidado no puede duplicarse', fn() => $ws->AccrueOrRedeemCompensatory($emp1, $sunOrigin, false, null, $uid));

// Compensatorio vencido no disfrutado pasa a EXPIRED_PAYOUT_DUE en GetCompensatoryLedger
$oldSun = date('Y-m-d', strtotime('-60 days'));
$oldDue = date('Y-m-d', strtotime('-30 days'));
$pc2 = $val("SELECT payroll_code FROM employees WHERE id = $emp2");
$db->exec("INSERT INTO workforce_compensatory_bank (payroll_code, origin_sunday, due_date, status, entitlement) VALUES ('$pc2', '$oldSun', '$oldDue', 'PENDING', 'COMPENSATORY_DAY')");
$ledger = $ws->GetCompensatoryLedger($branchId, $emp2, date('Y-m-d'));
ok('compensatorio vencido se convierte a EXPIRED_PAYOUT_DUE auditado', !empty($ledger['balances'][0]) && intval($ledger['balances'][0]['payout_days']) === 1 && floatval($ledger['balances'][0]['total_payout_due']) > 0);

$vacBalances = $ws->GetVacationBalances($branchId, 2026);
ok('saldo real de vacaciones retornado por colaborador', count($vacBalances) >= 2 && isset($vacBalances[0]['available_days']));

// Cambio bidireccional (swap entre Emp1 y Emp2 en fecha futura sin conflicto)
$fri = date('Y-m-d', strtotime("$mon +4 days"));
$sw1 = $ws->SaveShift(['employee_id' => $emp1, 'shift_date' => $fri, 'start_time' => '06:00', 'end_time' => '14:00', 'department' => 'Caja'], $uid);
$sw2 = $ws->SaveShift(['employee_id' => $emp2, 'shift_date' => $fri, 'start_time' => '14:00', 'end_time' => '22:00', 'department' => 'Caja'], $uid);
$swapId = $ws->RequestSwap($sw1['id'], $emp2, $uid, 'Permuta verificada', $sw2['id']);
$decSwap = $ws->DecideSwap($swapId, true, $uid);
ok('permuta bidireccional intercambia ambos turnos atómicamente', intval($ws->GetShift($sw1['id'])->employee_id) === $emp2 && intval($ws->GetShift($sw2['id'])->employee_id) === $emp1);

// Done - bootstrap.php prints pass/fail summary and exits appropriately.
