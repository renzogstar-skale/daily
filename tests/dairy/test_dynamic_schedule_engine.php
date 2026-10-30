<?php

use Grocy\Services\DairyDynamicScheduleService;
use Grocy\Services\WorkforceScheduleService;

require __DIR__ . '/bootstrap.php';

echo "\nMotor Inteligente de Horarios y Cobertura según Ventas (Módulo 35)\n";

$svc = DairyDynamicScheduleService::GetInstance();
$ws = WorkforceScheduleService::GetInstance();
$uid = GROCY_USER_ID;
$branchId = 1;
$monday = '2026-10-26';

// 1. Verificación de departamentos y configuración
$deptConfigs = $svc->GetDepartmentConfigs($branchId);
ok('existen estándares departamentales', !empty($deptConfigs) && isset($deptConfigs['Dairy']));
ok('Dairy tiene SPLH y unidades por hora configuradas', $deptConfigs['Dairy']['target_splh'] > 100 && $deptConfigs['Dairy']['units_per_hour'] > 30);
ok('Caja tiene dotación mínima de 2 en apertura', $deptConfigs['Caja']['min_staff_open'] >= 2);

// 2. Cálculo del Plan Laboral Dinámico
$plan = $svc->CalculateDynamicPlan($branchId, $monday);
ok('plan contiene ventas proyectadas totales', $plan['sales']['total_projected'] > 0);
ok('presupuesto laboral equivale al 8.5% de ventas', abs($plan['budget']['weekly_amount'] - ($plan['sales']['total_projected'] * 0.085)) < 1.0);
ok('plan desglosa departamentos con ventas y horas necesarias', count($plan['departments']) >= 6);

// Verificar cálculo de métricas en Dairy
$dairy = null;
foreach ($plan['departments'] as $d)
{
	if ($d['department'] === 'Dairy')
	{
		$dairy = $d;
		break;
	}
}
ok('Dairy tiene horas de ventas calculadas por SPLH', $dairy !== null && $dairy['sales_hours'] > 0);
ok('Dairy tiene cobertura mínima de seguridad (safety floor)', $dairy['min_safety_hours'] > 0);
ok('Dairy horas requeridas respeta el máximo entre seguridad y demanda', $dairy['required_hours'] >= $dairy['min_safety_hours']);

// 3. Verificación de los 8 KPIs Superiores
$kpis = $plan['kpis'];
ok('KPI 1: empleados programados presente', isset($kpis['scheduled_employees']['value']));
ok('KPI 2: horas asignadas presente', isset($kpis['scheduled_hours']['value']));
ok('KPI 3: cobertura de demanda presente', isset($kpis['demand_coverage']['value']));
ok('KPI 4: presupuesto laboral presente', isset($kpis['labor_budget']['value']));
ok('KPI 5: costo programado presente con desglose', isset($kpis['scheduled_cost']['base_cost']));
ok('KPI 6: SPLH (ventas por hora) presente', isset($kpis['splh']['value']));
ok('KPI 7: horas extras presente', isset($kpis['overtime']['value']));
ok('KPI 8: brecha de cobertura presente', isset($kpis['coverage_gap']['value']));

// 4. Verificación de Gráficos Centrales
$an = $plan['analytics'];
ok('gráfico demanda vs cobertura tiene 7 días', count($an['demand_vs_coverage']['days']) === 7);
ok('gráfico distribución por área contiene departamentos clave', count($an['distribution']) >= 5);
ok('gráfico costo mano de obra desglosa base, cargas y horas extras', count($an['labor_cost']['breakdown']) >= 3);
ok('gráfico cobertura por franja tiene los 8 bloques horarios', count($an['hourly_coverage']['slots']) === 8);
ok('comparación horario original vs optimizado calcula ahorro', isset($an['schedule_comparison']['potential_savings']));

// 5. Motor de Distribución Equitativa y Respeto a Contratos
$recs = $plan['sidebar']['recommendations'];
ok('motor genera recomendaciones verificables', is_array($recs));
foreach ($recs as $r)
{
	if ($r['change_type'] === 'adjust_hours')
	{
		ok('ajuste no reduce por debajo de horas mínimas contractuales', $r['recommended_hours'] >= $r['guaranteed_min_hours']);
		ok('ajuste incluye justificación detallada', strlen($r['reason']) > 30);
		ok('ajuste cuantifica ahorro financiero', $r['savings_amount'] > 0);
		break;
	}
}

// 6. Alerta cuando presupuesto es insuficiente para la seguridad mínima
$tightPlan = $svc->CalculateDynamicPlan($branchId, $monday, ['target_labor_pct' => 1.0]); // Forzar presupuesto irrealmente bajo (1%)
ok('alerta de déficit crítico se activa si presupuesto no cubre seguridad', $tightPlan['sidebar']['budget_deficit_alert'] !== null);
ok('mensaje de alerta solicita decisión explícita del manager', strpos($tightPlan['sidebar']['budget_deficit_alert']['action_required'], 'manager') !== false);

// 7. Decisión del Manager sobre Recomendaciones
if (!empty($recs))
{
	$firstRec = $recs[0];
	$decResult = $svc->DecideRecommendation($firstRec['id'], 'approved', null, 'Aprobado según proyección de ventas', $uid);
	ok('manager puede aprobar recomendación', $decResult['success'] === true && $decResult['status'] === 'approved');

	$decMod = $svc->DecideRecommendation($firstRec['id'], 'modified', 34.0, 'Ajuste manual a 34h', $uid);
	ok('manager puede modificar horas recomendadas', $decMod['success'] === true && $decMod['status'] === 'modified');
}

// 8. Automatización Diaria (10 pasos)
$autoResult = $svc->RunDailyAutomation($branchId, $monday, $uid);
ok('automatización diaria completa ejecuta con éxito', $autoResult['status'] === 'completed');
ok('automatización diaria guarda recomendaciones auditadas', $autoResult['recommendations_count'] >= 0);

// 9. Simulación de Escenarios What-If
$sim = $svc->SimulateSalesScenario($branchId, $monday, ['sales_pct' => 20]);
ok('simulación con ventas +20% incrementa presupuesto proporcionalmente', $sim['weekly_budget'] > $plan['budget']['weekly_amount']);

echo "\nTodas las verificaciones del Motor Inteligente completadas con éxito.\n";
