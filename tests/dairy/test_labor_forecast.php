<?php
/**
 * Módulo 17 — Planificación de personal según ventas: pronóstico, productividad, carga operativa sin doble conteo,
 * personas por franja, recomendación (ampliar / agregar / horas extra con permiso / solicitar cobertura), aplicación con
 * motivo e historial, deshacer, eventos, corrección aprendida, precisión, presupuesto laboral y sucursal sin historia.
 * Escenario controlado en una sucursal nueva: 10 tickets/día de $100 (09–18 h), 2 u. por ticket, 8 h fichadas/día.
 */

use Grocy\Services\BudgetService;
use Grocy\Services\LaborForecastService;
use Grocy\Services\WorkforceScheduleService;

require __DIR__ . '/bootstrap.php';

echo "\nPlanificación de personal según ventas (Módulo 17)\n";
$fc = LaborForecastService::GetInstance();
$ws = WorkforceScheduleService::GetInstance();
$uid = GROCY_USER_ID;
$today = date('Y-m-d');
$D = date('Y-m-d', strtotime('+1 day'));
$day = fn(int $n) => date('Y-m-d', strtotime("$today $n day"));

$db->exec("INSERT INTO branches (name, code) VALUES ('Prueba M17', 'M17T')");
$br = intval($db->lastInsertId());
$db->exec("INSERT INTO branches (name, code) VALUES ('Prueba M17 sin historia', 'M17N')");
$brEmpty = intval($db->lastInsertId());
$prod = $db->query('SELECT id, qu_id_stock FROM products ORDER BY id LIMIT 1')->fetch(PDO::FETCH_OBJ);
$supplier = intval($val('SELECT id FROM shopping_locations ORDER BY id LIMIT 1'));

// Ventas: 90 días cerrados, 10 tickets de $100 (una por hora 09–18), 2 unidades cada uno
$insSale = $db->prepare("INSERT INTO sales (sale_number, branch_id, user_id, subtotal, total_amount, status, created_timestamp) VALUES (:n, :b, 1, 100, 100, 'completed', :t)");
$insItem = $db->prepare('INSERT INTO sale_items (sale_id, product_id, amount, qu_id, unit_price, subtotal) VALUES (:s, :p, 2, :q, 50, 100)');
$db->beginTransaction();
for ($i = 1; $i <= 90; $i++)
{
	$d = $day(-$i);
	for ($h = 9; $h <= 18; $h++)
	{
		$insSale->execute([':n' => "M17-$d-$h", ':b' => $br, ':t' => sprintf('%s %02d:30:00', $d, $h)]);
		$insItem->execute([':s' => $db->lastInsertId(), ':p' => $prod->id, ':q' => $prod->qu_id_stock]);
	}
}
$db->commit();

$A = $ws->SaveEmployee(['name' => 'M17 Ana', 'branch_id' => $br, 'weekly_hours' => 40, 'hourly_cost' => 10]);
$B = $ws->SaveEmployee(['name' => 'M17 Beto', 'branch_id' => $br, 'weekly_hours' => 2, 'hourly_cost' => 9]);
$C = $ws->SaveEmployee(['name' => 'M17 Carla', 'branch_id' => $br, 'weekly_hours' => 40, 'hourly_cost' => 10]);
// 8 h fichadas por día en los 28 días cerrados → 28 000 / 224 h = 125 $/h
$insAtt = $db->prepare("INSERT INTO employee_attendance (employee_id, clock_in, clock_out, source) VALUES (:e, :i, :o, 'manager')");
for ($i = 1; $i <= 28; $i++)
{
	$insAtt->execute([':e' => $A, ':i' => $day(-$i) . ' 09:00:00', ':o' => $day(-$i) . ' 17:00:00']);
}
// Carga operativa del día: 1 entrega de 100 u., 1 conteo, 3 tareas de tienda
$db->prepare("INSERT INTO supplier_deliveries (supplier_id, branch_id, order_reference, order_date, expected_delivery_date, items_ordered) VALUES (:s, :b, 'M17-OC', :o, :d, 100)")->execute([':s' => $supplier, ':b' => $br, ':o' => $today, ':d' => $D]);
$db->prepare("INSERT INTO stock_cycle_counts (branch_id, product_id, scheduled_date, status) VALUES (:b, :p, :d, 'pending')")->execute([':b' => $br, ':p' => $prod->id, ':d' => $D]);
for ($i = 0; $i < 3; $i++)
{
	$db->prepare("INSERT INTO workforce_store_tasks (branch_id, task_type, title, due_time, status) VALUES (:b, 'cleaning', 'Limpieza M17', :d, 'pending')")->execute([':b' => $br, ':d' => "$D 10:00:00"]);
}
$sA = $ws->SaveShift(['employee_id' => $A, 'shift_date' => $D, 'start_time' => '09:00', 'end_time' => '17:00'], $uid);

// ---------------------------------------------------------------- 1. Pronóstico y productividad
$f = $fc->Forecast($br, $D);
ok('ventas proyectadas = $1 000 (mismo día de semana estable)', abs($f['sales']['projected'] - 1000) < 0.01, $f['sales']['projected']);
ok('tickets 10 y unidades 20 proyectados', abs($f['sales']['projected_tickets'] - 10) < 0.01 && abs($f['sales']['projected_units'] - 20) < 0.01);
ok('promedios 7/14/30/60/90 días = $1 000', count(array_filter($f['sales']['avg_daily'], fn($v) => abs($v - 1000) < 0.01)) === 5);
ok('8 mismos días de semana anteriores analizados', count($f['sales']['same_weekday']) === 8 && abs($f['sales']['same_weekday_avg'] - 1000) < 0.01);
ok('productividad histórica 125 $/h desde horas fichadas', $f['productivity']['source'] === 'worked' && abs($f['productivity']['value'] - 125) < 0.01, $f['productivity']['value']);
ok('horas de ventas = 1 000 ÷ 125 = 8 h', abs($f['hours']['sales'] - 8) < 0.01);
ok('sin estacionalidad (menos de un año de historia) ni corrección', !$f['sales']['factors']['seasonality']['applied'] && !$f['sales']['factors']['learned']['applied']);

// ---------------------------------------------------------------- 2. Carga operativa sin doble conteo
$t = array_column($f['tasks'], null, 'code');
ok('recepción: (20 min + 100 u. × 0,05) = 25 min contados completos (sin entregas históricas)', abs($t['receiving']['counted'] - 0.42) < 0.01, $t['receiving']['counted']);
ok('reposición = promedio histórico → 0 h adicionales (ya está en la productividad)', $t['restock']['hours'] > 0 && $t['restock']['counted'] == 0);
ok('conteo 4 min y 3 tareas × 10 min contados', abs($t['counts']['counted'] - 0.07) < 0.01 && abs($t['tasks']['counted'] - 0.5) < 0.01);
ok('horas de tareas = 0,98 h', abs($f['hours']['tasks'] - 0.98) < 0.02, $f['hours']['tasks']);

// ---------------------------------------------------------------- 3. Franjas y totales
$bands = array_column($f['bands'], null, 'label');
ok('6–9 sin atención: 0 necesarios', $bands['6:00–9:00']['needed'] === 0);
ok('9–12: ⌈3,03 h ÷ (3 × 0,9)⌉ = 2 necesarios, 1 programado, −1', $bands['9:00–12:00']['needed'] === 2 && $bands['9:00–12:00']['scheduled'] === 1 && $bands['9:00–12:00']['diff'] === -1, json_encode($bands['9:00–12:00']['work_hours']));
ok('12–15: 1 necesario y cubierto', $bands['12:00–15:00']['needed'] === 1 && $bands['12:00–15:00']['diff'] === 0);
ok('15–18 y 18–22: faltante por el turno que termina a las 17', $bands['15:00–18:00']['diff'] === -1 && $bands['18:00–22:00']['needed'] === 1 && $bands['18:00–22:00']['open_hours'] === 1);
ok('roles: ventas y operaciones en 9–12', $bands['9:00–12:00']['roles']['ventas']['hours'] > 2 && $bands['9:00–12:00']['roles']['operaciones']['hours'] > 0.5);
ok('horas requeridas 13, recomendadas 2 personas, 1 programada', $f['hours']['required'] === 13 && $f['staff']['recommended'] === 2 && $f['staff']['scheduled'] === 1 && $f['staff']['extra'] === 1);
ok('cobertura por hora = 8/13 = 61,5 % (exceso no compensa faltante)', $f['coverage_pct'] == 61.5, $f['coverage_pct']);
ok('alerta de faltante en 9–12 con acción de franja', count(array_filter($f['alerts'], fn($a) => $a['code'] === 'band_gap' && $a['action']['type'] === 'band')) === 3);
ok('respuesta: mañana necesitas 2, tienes 1, falta 1 en 9–12 y cómo cubrirlo', str_contains($f['answer'], 'mañana necesitas 2 personas') && str_contains($f['answer'], 'tienes 1 programada') && str_contains($f['answer'], '9:00–12:00') && str_contains($f['answer'], 'ampliando 1 turno'), $f['answer']);
ok('pronóstico guardado para aprendizaje', abs(floatval($val("SELECT projected_sales FROM labor_forecast_snapshots WHERE branch_id = $br AND forecast_date = '$D'")) - 1000) < 0.01);
ok('conector de nómina inactivo sin credenciales', $f['payroll_connector']['enabled'] === false);

// ---------------------------------------------------------------- 4. Disponibles por franja
$cand = $fc->BandCandidates($br, $D, 1);
$names = array_column($cand['available'], 'fits', 'name');
ok('franja 9–12: Carla disponible sin conflictos, Beto solo con horas extra, Ana ocupada', ($names['M17 Carla'] ?? null) === true && ($names['M17 Beto'] ?? null) === false && !isset($names['M17 Ana']));
ok('Carla primero en la lista (cabe sin horas extra)', $cand['available'][0]['name'] === 'M17 Carla');

// ---------------------------------------------------------------- 5. Recomendación (simulación)
$before = intval($val("SELECT COUNT(*) FROM employee_shifts WHERE branch_id = $br"));
$plan = $fc->Recommend($br, $D, false);
$types = array_column($plan['proposals'], 'type');
$ext = array_values(array_filter($plan['proposals'], fn($p) => $p['type'] === 'extend'))[0] ?? null;
$add = array_values(array_filter($plan['proposals'], fn($p) => $p['type'] === 'add'))[0] ?? null;
ok('simular no escribe turnos', intval($val("SELECT COUNT(*) FROM employee_shifts WHERE branch_id = $br")) === $before);
ok('amplía el turno de Ana 09–17 → 09–19 (límite diario 10 h)', $ext && $ext['employee_id'] === $A && $ext['end_time'] === '19:00', json_encode($ext));
ok('agrega turno 09–13 a Carla (Beto exigiría horas extra)', $add && $add['employee_id'] === $C && $add['start_time'] === '09:00' && $add['end_time'] === '13:00', json_encode($add));
ok('Beto aparece como requiere horas extra', in_array($B, array_column($plan['requires_overtime'], 'employee_id')));
ok('cobertura simulada 61,5 % → 100 %', $plan['before']['coverage_pct'] == 61.5 && $plan['after']['coverage_pct'] == 100.0 && $plan['after']['cost'] > $plan['before']['cost']);
$planOt = $fc->Recommend($br, $D, true);
$addOt = array_values(array_filter($planOt['proposals'], fn($p) => $p['type'] === 'add'))[0] ?? null;
ok('con horas extra aprobables se elige a Beto (menor costo)', $addOt && $addOt['employee_id'] === $B);

// ---------------------------------------------------------------- 6. Aplicar con motivo, permisos, deshacer
expectError('aplicar sin motivo', fn() => $fc->Apply($br, $D, $plan['proposals'], '', $uid, false));
expectError('aplicar horas extra sin permiso', fn() => $fc->Apply($br, $D, [$addOt], 'Pico de demanda', $uid, false));
expectError('propuesta con turno de otra fecha/empleado se rechaza', fn() => $fc->Apply($br, $D, [['type' => 'extend', 'shift_id' => $sA['id'], 'employee_id' => $C, 'start_time' => '09:00', 'end_time' => '19:00']], 'Pico de demanda', $uid, true));
$res = $fc->Apply($br, $D, $plan['proposals'], 'Pico de demanda', $uid, false);
ok('aplicadas 2 propuestas en un lote', count($res['applied']) === 2 && str_starts_with($res['batch'], 'fc-'));
ok('turno de Ana ahora 09–19 y Carla 09–13 en borrador', $ws->GetShift($sA['id'])->end_time === '19:00' && $val("SELECT status FROM employee_shifts WHERE employee_id = $C AND shift_date = '$D'") === 'planned');
ok('historial registra el motivo de cada cambio', intval($val("SELECT COUNT(*) FROM employee_shift_changes WHERE batch_key = '{$res['batch']}' AND reason LIKE 'Pico de demanda%'")) === 2);
$f2 = $fc->Forecast($br, $D);
ok('tras aplicar: cobertura 100 % y sin faltantes', $f2['coverage_pct'] == 100.0 && !array_filter($f2['bands'], fn($b) => $b['diff'] < 0) && str_contains($f2['answer'], 'suficiente'), $f2['answer']);
$ws->UndoLastChange($br, $uid);
ok('deshacer revierte el lote completo', $ws->GetShift($sA['id'])->end_time === '17:00' && $val("SELECT status FROM employee_shifts WHERE employee_id = $C AND shift_date = '$D'") === 'cancelled');

// ---------------------------------------------------------------- 7. Solicitud de cobertura
$rq = $fc->CreateCoverageRequest(['branch_id' => $br, 'request_date' => $D, 'start_time' => '12:00', 'end_time' => '15:00', 'staff_needed' => 1, 'reason' => 'Sin personal disponible'], $uid);
ok('solicitud de cobertura abierta visible en la franja', count($fc->BandCandidates($br, $D, 2)['open_requests']) === 1);
$fc->CloseCoverageRequest($rq, 'filled', $uid);
expectError('cerrar dos veces la misma solicitud', fn() => $fc->CloseCoverageRequest($rq, 'cancelled', $uid));
expectError('solicitud con horario inválido', fn() => $fc->CreateCoverageRequest(['branch_id' => $br, 'request_date' => $D, 'start_time' => '15:00', 'end_time' => '12:00', 'reason' => 'x'], $uid));

// ---------------------------------------------------------------- 8. Sin historia laboral: tickets y tareas completas
$db->exec("UPDATE employees SET branch_id = $brEmpty WHERE id = $A"); // sus fichajes dejan de contar para la sucursal (los fichajes no se borran)
$f3 = $fc->Forecast($br, $D, false);
$t3 = array_column($f3['tasks'], null, 'code');
ok('sin fichajes ni turnos pasados: tickets ÷ 20 por persona-hora = 0,5 h de ventas', $f3['productivity']['source'] === 'tickets' && abs($f3['hours']['sales'] - 0.5) < 0.01);
ok('productividad sin tareas → reposición se suma completa', !$f3['productivity']['includes_tasks'] && $t3['restock']['counted'] == $t3['restock']['hours'] && $t3['restock']['hours'] > 0);
$db->exec("UPDATE employees SET branch_id = $br WHERE id = $A");

// ---------------------------------------------------------------- 9. Eventos y presupuesto laboral
$ev = $fc->SaveEvent(['branch_id' => $br, 'event_date' => $D, 'kind' => 'promotion', 'name' => 'Promo yogurt', 'sales_factor' => 1.2, 'extra_hours' => 2], $uid);
$f4 = $fc->Forecast($br, $D, false);
ok('promoción ×1,2 → ventas $1 200 y +2 h operativas', abs($f4['sales']['projected'] - 1200) < 0.01 && abs(array_column($f4['tasks'], null, 'code')['events']['counted'] - 2) < 0.01);
ok('alerta: se proyecta 20 % más que el promedio del día, con la causa', count(array_filter($f4['alerts'], fn($a) => $a['code'] === 'sales_vs_weekday' && str_contains($a['title'], '20 % más') && str_contains($a['cause'], 'Promo yogurt'))) === 1);
expectError('evento con factor fuera de rango', fn() => $fc->SaveEvent(['branch_id' => $br, 'event_date' => $D, 'kind' => 'event', 'name' => 'x', 'sales_factor' => 9], $uid));
$fc->DeleteEvent($ev);
$daysInMonth = intval(date('t', strtotime($D)));
BudgetService::GetInstance()->SaveBudget(['branch_id' => $br, 'period' => substr($D, 0, 7), 'labor_budget_amount' => 50 * $daysInMonth], $uid);
$f5 = $fc->Forecast($br, $D, false);
ok('presupuesto laboral prorrateado $50/día y costo programado $80 → alerta', $f5['cost']['daily_budget'] == 50.0 && $f5['cost']['scheduled'] == 80.0 && count(array_filter($f5['alerts'], fn($a) => $a['code'] === 'over_budget')) === 1);

// ---------------------------------------------------------------- 10. Aprendizaje: precisión y corrección
$snap = $db->prepare('INSERT INTO labor_forecast_snapshots (branch_id, forecast_date, projected_sales, projected_tickets, required_hours, recommended_staff, scheduled_hours, productivity) VALUES (:b, :d, :p, 10, 8, 1, 8, 125)');
$snap->execute([':b' => $br, ':d' => $day(-1), ':p' => 900]);
foreach ([-2, -3, -4] as $n)
{
	$snap->execute([':b' => $br, ':d' => $day($n), ':p' => 800]);
}
$acc = $fc->Accuracy($br, 14);
$y = $acc['days'][0];
ok('ayer: proyectado 900 vs real 1 000 = +11,1 %', $y['date'] === $day(-1) && $y['actual_sales'] == 1000.0 && $y['sales_error_pct'] == 11.1, json_encode($y['sales_error_pct']));
ok('horas trabajadas 8, personal usado 1, productividad real 125', $y['worked_hours'] == 8.0 && $y['staff_used'] === 1 && $y['actual_productivity'] == 125.0);
ok('WAPE y sesgo de 4 días', $acc['wape_pct'] == round((100 + 600) / 4000 * 100, 1) && $acc['bias_pct'] == round(-700 / 4000 * 100, 1), $acc['wape_pct'] . ' / ' . $acc['bias_pct']);
ok('corrección aprendida acotada a ×1,15 (mediana 1,25)', $acc['correction']['applied'] && $acc['correction']['factor'] == 1.15);
$f6 = $fc->Forecast($br, $D, false);
ok('el pronóstico aplica la corrección aprendida → $1 150', abs($f6['sales']['projected'] - 1150) < 0.01, $f6['sales']['projected']);

// ---------------------------------------------------------------- 11. Sucursal sin historia
$fe = $fc->Forecast($brEmpty, $D, false);
ok('sin ventas: no inventa pronóstico', $fe['sales']['projected'] === null && !$fe['has_sales_history'] && $fe['alerts'][0]['code'] === 'no_history');
ok('sin ventas: mínimos de cobertura en horario configurado (06–22)', $fe['hours']['required'] === 16 && str_starts_with($fe['answer'], 'Sin ventas históricas'), $fe['hours']['required']);
expectError('fecha inválida', fn() => $fc->Forecast($br, '2026-13-45'));
expectError('franja inexistente', fn() => $fc->BandCandidates($br, $D, 99));

// ---------------------------------------------------------------- 12. Panel ejecutivo
$out = $fc->Outlook([$br]);
ok('resumen ejecutivo por sucursal para mañana', count($out) === 1 && $out[0]['recommended'] >= 1 && $out[0]['has_history']);

echo "\n$count verificaciones, $fails fallas\n";
exit($fails ? 1 : 0);
