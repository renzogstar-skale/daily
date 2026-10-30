<?php
/**
 * Etapa 31 — Simulador de escenarios de personal: ventas ±%, productividad, pallets adicionales, ausencias, recorte de horas,
 * reposición adelantada; validación de parámetros; nada se escribe en turnos ni en el aprendizaje; guardar/listar/eliminar.
 * Escenario controlado: $1 000/día (10 tickets 09–18 h), 125 $/h fichados → 8 h de ventas; 2 turnos 09–17.
 */

use Grocy\Services\LaborForecastService;
use Grocy\Services\WorkforceScheduleService;

require __DIR__ . '/bootstrap.php';

echo "\nSimulador de escenarios de personal (Etapa 31)\n";
$fc = LaborForecastService::GetInstance();
$ws = WorkforceScheduleService::GetInstance();
$uid = GROCY_USER_ID;
$today = date('Y-m-d');
$D = date('Y-m-d', strtotime('+1 day'));
$day = fn(int $n) => date('Y-m-d', strtotime("$today $n day"));

$db->exec("INSERT INTO branches (name, code) VALUES ('Prueba E31', 'E31T')");
$br = intval($db->lastInsertId());
$prod = $db->query('SELECT id, qu_id_stock FROM products ORDER BY id LIMIT 1')->fetch(PDO::FETCH_OBJ);
$insSale = $db->prepare("INSERT INTO sales (sale_number, branch_id, user_id, subtotal, total_amount, status, created_timestamp) VALUES (:n, :b, 1, 100, 100, 'completed', :t)");
$insItem = $db->prepare('INSERT INTO sale_items (sale_id, product_id, amount, qu_id, unit_price, subtotal) VALUES (:s, :p, 2, :q, 50, 100)');
$db->beginTransaction();
for ($i = 1; $i <= 60; $i++)
{
	for ($h = 9; $h <= 18; $h++)
	{
		$insSale->execute([':n' => 'E31-' . $day(-$i) . "-$h", ':b' => $br, ':t' => sprintf('%s %02d:30:00', $day(-$i), $h)]);
		$insItem->execute([':s' => $db->lastInsertId(), ':p' => $prod->id, ':q' => $prod->qu_id_stock]);
	}
}
$db->commit();
$A = $ws->SaveEmployee(['name' => 'E31 Ana', 'branch_id' => $br, 'weekly_hours' => 40, 'hourly_cost' => 10]);
$B = $ws->SaveEmployee(['name' => 'E31 Beto', 'branch_id' => $br, 'weekly_hours' => 40, 'hourly_cost' => 10]);
$insAtt = $db->prepare("INSERT INTO employee_attendance (employee_id, clock_in, clock_out, source) VALUES (:e, :i, :o, 'manager')");
for ($i = 1; $i <= 28; $i++)
{
	$insAtt->execute([':e' => $A, ':i' => $day(-$i) . ' 09:00:00', ':o' => $day(-$i) . ' 17:00:00']);
}
$ws->SaveShift(['employee_id' => $A, 'shift_date' => $D, 'start_time' => '09:00', 'end_time' => '17:00'], $uid);
$ws->SaveShift(['employee_id' => $B, 'shift_date' => $D, 'start_time' => '10:00', 'end_time' => '19:00'], $uid);

$shiftsBefore = $db->query("SELECT id, start_time, end_time, status, version FROM employee_shifts WHERE branch_id = $br ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
$snapBefore = intval($val("SELECT COUNT(*) FROM labor_forecast_snapshots WHERE branch_id = $br"));
$base = $fc->Forecast($br, $D, false, false);

// 1. Ventas +20 %
$r = $fc->Simulate($br, $D, ['sales_pct' => 20]);
ok('ventas +20 %: $1 000 → $1 200', abs($r['base']['projected_sales'] - 1000) < 0.01 && abs($r['scenario']['projected_sales'] - 1200) < 0.01, $r['scenario']['projected_sales']);
ok('ventas +20 %: carga de trabajo sube 1,6 h (8 h de ventas × 20 %)', abs($r['delta']['workload_hours'] - 1.6) < 0.02, $r['delta']['workload_hours']);
ok('base del simulador = pronóstico normal', $r['base']['required_hours'] === $base['hours']['required'] && $r['base']['coverage_pct'] === $base['coverage_pct']);
ok('ventas +20 % no baja horas requeridas', $r['scenario']['required_hours'] >= $r['base']['required_hours']);

// 2. Productividad +100 % → horas de ventas a la mitad
$r = $fc->Simulate($br, $D, ['productivity_pct' => 100]);
ok('productividad +100 %: carga de trabajo ≤ mitad + tareas', $r['scenario']['workload_hours'] <= $r['base']['workload_hours'] / 2 + 0.01, $r['scenario']['workload_hours']);

// 3. 15 pallets adicionales × 20 min = 5 h en la ventana de recepción
$r = $fc->Simulate($br, $D, ['extra_pallets' => 15]);
ok('15 pallets: +5 h de carga (20 min/pallet)', abs(($r['scenario']['task_hours'] - $r['base']['task_hours']) - 5) < 0.02, $r['scenario']['task_hours'] - $r['base']['task_hours']);
ok('15 pallets: faltante de cobertura aumenta', $r['scenario']['gap_staff_hours'] > $r['base']['gap_staff_hours']);

// 4. Faltan dos empleados
$r = $fc->Simulate($br, $D, ['absent_staff' => 2]);
ok('2 ausentes: personal programado 2 → 0', $r['base']['scheduled_staff'] === 2 && $r['scenario']['scheduled_staff'] === 0);
ok('2 ausentes: horas programadas 0 y costo 0', $r['scenario']['scheduled_hours'] == 0 && $r['scenario']['scheduled_cost'] == 0);
ok('2 ausentes: riesgo alto y cobertura 0 %', $r['scenario']['risk'] === 'alto' && $r['scenario']['coverage_pct'] == 0);
ok('2 ausentes: cambios listan los turnos quitados', count(array_filter($r['shift_changes'], fn($c) => $c['type'] === 'absent')) === 2);
$r = $fc->Simulate($br, $D, ['absent_staff' => 1]);
ok('1 ausente: se quita el turno más largo (Beto 9 h)', $r['shift_changes'][0]['employee_name'] === 'E31 Beto' && $r['scenario']['scheduled_staff'] === 1);
$r = $fc->Simulate($br, $D, ['absent_staff' => 5]);
ok('5 ausentes con 2 turnos: informa 3 sin turno que quitar', end($r['shift_changes'])['type'] === 'absent_unmatched' && end($r['shift_changes'])['count'] === 3);

// 5. Recortar 3 horas: Beto 10–19 → 10–16
$r = $fc->Simulate($br, $D, ['cut_hours' => 3]);
ok('recorte 3 h: horas programadas −3', abs($r['delta']['scheduled_hours'] + 3) < 0.01, $r['delta']['scheduled_hours']);
ok('recorte 3 h: costo programado −$30', abs($r['delta']['scheduled_cost'] + 30) < 0.01, $r['delta']['scheduled_cost']);

// 6. Reposición adelantada: misma carga total, distinta distribución
$r = $fc->Simulate($br, $D, ['advance_restock' => true, 'sales_pct' => 0.0]);
ok('reposición adelantada: carga total igual', abs($r['delta']['workload_hours']) < 0.01);

// 7. Validación
$err = function ($p) use ($fc, $br, $D) { try { $fc->Simulate($br, $D, $p); return null; } catch (\Exception $e) { return $e->getMessage(); } };
ok('sin cambios → error claro', str_contains(strval($err([])), 'al menos un cambio'));
ok('ventas −95 % fuera de rango', str_contains(strval($err(['sales_pct' => -95])), 'fuera de rango'));
ok('valor no numérico rechazado', str_contains(strval($err(['extra_pallets' => 'muchos'])), 'inválido'));

// 8. Nada productivo cambia
$shiftsAfter = $db->query("SELECT id, start_time, end_time, status, version FROM employee_shifts WHERE branch_id = $br ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
ok('los turnos reales no cambian tras simular', $shiftsAfter === $shiftsBefore);
ok('el simulador no guarda snapshots de aprendizaje', intval($val("SELECT COUNT(*) FROM labor_forecast_snapshots WHERE branch_id = $br")) === $snapBefore);
$f2 = $fc->Forecast($br, $D, false, false);
ok('el pronóstico normal sigue igual', $f2['hours']['required'] === $base['hours']['required'] && $f2['coverage_pct'] === $base['coverage_pct']);

// 9. Guardar, comparar, eliminar
$id1 = $fc->SaveScenario($br, $D, '+20 % ventas', ['sales_pct' => 20], $uid);
$id2 = $fc->SaveScenario($br, $D, 'Faltan 2', ['absent_staff' => 2], $uid);
$list = $fc->GetScenarios($br);
ok('2 escenarios guardados, el último primero', count($list) === 2 && $list[0]['id'] === $id2 && $list[1]['name'] === '+20 % ventas');
ok('escenario guardado conserva parámetros y resultado', $list[1]['params'] == ['sales_pct' => 20] && abs($list[1]['result']['scenario']['projected_sales'] - 1200) < 0.01);
ok('sucursal del escenario recuperable para permisos', $fc->GetScenarioBranch($id1) === $br);
$e = null; try { $fc->SaveScenario($br, $D, '  ', ['sales_pct' => 5], $uid); } catch (\Exception $x) { $e = $x->getMessage(); }
ok('nombre vacío rechazado', $e !== null);
$fc->DeleteScenario($id1);
ok('eliminar deja 1 escenario', count($fc->GetScenarios($br)) === 1);
$e = null; try { $fc->GetScenarioBranch($id1); } catch (\Exception $x) { $e = $x->getMessage(); }
ok('escenario eliminado → no encontrado', $e === 'Escenario no encontrado');
