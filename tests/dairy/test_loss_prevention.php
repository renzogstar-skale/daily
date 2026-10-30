<?php
/**
 * Etapa 19: stock fantasma, ABC-XYZ, pérdida total y conciliación POS, retiros de producto, auditorías de tienda,
 * certificaciones, cobertura por demanda y centro de alertas (ciclo completo con segregación y cierre automático).
 */

use Grocy\Services\AlertCenterService;
use Grocy\Services\CycleCountService;
use Grocy\Services\LossPreventionService;
use Grocy\Services\ManagerAgendaService;
use Grocy\Services\RecallService;
use Grocy\Services\StockService;
use Grocy\Services\StoreAuditService;
use Grocy\Services\WorkforceScheduleService;

require __DIR__ . '/bootstrap.php';

$stock = StockService::GetInstance();
$lp = LossPreventionService::GetInstance();
$since = date('Y-m-d', strtotime('-56 days'));
$p = $db->query("SELECT p.id, p.name, p.location_id FROM products p JOIN locations l ON l.id = p.location_id WHERE p.active = 1 AND l.branch_id = 1
	AND p.id NOT IN (SELECT si.product_id FROM sale_items si JOIN sales s ON s.id = si.sale_id WHERE date(s.created_timestamp) >= '$since') ORDER BY p.id LIMIT 1")->fetch(PDO::FETCH_OBJ);
if (!$p)
{
	// Con la empresa de ejemplo todo el surtido vende: producto propio de la prueba
	$loc = intval($val('SELECT id FROM locations WHERE branch_id = 1 AND IFNULL(is_transit, 0) = 0 ORDER BY id LIMIT 1'));
	$qu = intval($val('SELECT id FROM quantity_units ORDER BY id LIMIT 1'));
	$db->exec("INSERT INTO products (name, active, location_id, qu_id_purchase, qu_id_stock, qu_id_consume, qu_id_price, default_best_before_days, sku) VALUES ('Producto prueba stock fantasma', 1, $loc, $qu, $qu, $qu, $qu, 60, 'TEST-LP-001')");
	$p = $db->query('SELECT id, name, location_id FROM products WHERE id = ' . intval($db->lastInsertId()))->fetch(PDO::FETCH_OBJ);
}
echo "  Producto #{$p->id} {$p->name}\n";

// ---------------------------------------------------------------- 1. Stock fantasma
echo "\n1. Stock fantasma y conteo ciego\n";
$stock->AddProduct($p->id, 60, date('Y-m-d', strtotime('+60 days')), StockService::TRANSACTION_TYPE_PURCHASE, date('Y-m-d'), 2.5, $p->location_id);
// 25 días seguidos con venta (2 u./día, 10:15) y los últimos 6 sin ninguna: ventas sin movimiento de stock (como en la BD demo)
$insSale = $db->prepare("INSERT INTO sales (sale_number, branch_id, user_id, subtotal, tax_amount, discount_amount, total_amount, total_cogs, gross_margin, payment_method, status, created_timestamp) VALUES (?, 1, 1, 8, 0, 0, 8, 5, 3, 'cash', 'completed', ?)");
$insItem = $db->prepare('INSERT INTO sale_items (sale_id, product_id, amount, qu_id, unit_price, subtotal, unit_cost, total_cost, created_timestamp) VALUES (?, ?, 2, 1, 4, 8, 2.5, 5, ?)');
for ($d = 30; $d >= 6; $d--)
{
	$ts = date('Y-m-d', strtotime("-$d days")) . ' 10:15:00';
	$insSale->execute(['T19-' . $d . '-' . uniqid(), $ts]);
	$insItem->execute([intval($db->lastInsertId()), $p->id, $ts]);
}
$phantom = array_values(array_filter($lp->PhantomStock(1), fn($r) => $r['product_id'] == $p->id));
ok('detecta stock fantasma (vende 25/31 días, 6 días sin venta)', count($phantom) === 1 && $phantom[0]['days_without_sale'] === 6, json_encode($phantom[0] ?? null));
ok('probabilidad casual (1−f)^g < 0.1 %', count($phantom) && $phantom[0]['probability'] < 0.001, $phantom[0]['probability'] ?? '');
ok('venta perdida estimada = VMD × días', count($phantom) && abs($phantom[0]['lost_sales_units'] - round(50 / 31 * 6, 2)) < 0.05, $phantom[0]['lost_sales_units'] ?? '');
ok('otra sucursal no lo ve', count(array_filter($lp->PhantomStock(2), fn($r) => $r['product_id'] == $p->id)) === 0);
$cc = CycleCountService::GetInstance();
$r1 = $cc->ScheduleForProducts(1, [$p->id], 'Conteo ciego por stock fantasma', 1);
$r2 = $cc->ScheduleForProducts(1, [$p->id], 'x', 1);
ok('programa un conteo ciego y no duplica', $r1['scheduled'] === 1 && $r2['scheduled'] === 0 && $r2['skipped'] === 1);
ok('fila marcada con conteo pendiente', array_values(array_filter($lp->PhantomStock(1), fn($r) => $r['product_id'] == $p->id))[0]['count_pending'] === true);

// ---------------------------------------------------------------- 2. ABC-XYZ
echo "\n2. Matriz ABC-XYZ\n";
$m = $lp->AbcXyz(1);
$mine = array_values(array_filter($m['items'], fn($i) => $i['product_id'] == $p->id))[0] ?? null;
ok('9 celdas y suma = productos clasificados', count($m['matrix']) === 9 && array_sum(array_column($m['matrix'], 'count')) === count($m['items']));
ok('producto clasificado con CV y política', $mine && $mine['cv'] !== null && isset(LossPreventionService::XYZ_POLICY[$mine['cell']]), json_encode($mine));

// ---------------------------------------------------------------- 3. Pérdida total y conciliación POS
echo "\n3. Pérdida total, salidas sin documento y conciliación POS\n";
$from = date('Y-m-d', strtotime('-31 days'));
$pos = array_filter($lp->PosReconciliation(1, $from, date('Y-m-d')), fn($r) => $r['product_id'] == $p->id);
ok('25 líneas vendidas sin descargo de inventario', count($pos) === 25, count($pos));
$before = $lp->TotalLoss(1, $from, date('Y-m-d'));
$txOut = $stock->ConsumeProduct($p->id, 3, false, StockService::TRANSACTION_TYPE_CONSUME, 'default', null, $p->location_id);
$outValue = floatval($val("SELECT SUM(ABS(amount * price)) FROM stock_log WHERE transaction_id = '$txOut'"));
$after = $lp->TotalLoss(1, $from, date('Y-m-d'));
ok('consumo sin venta ni merma = salida sin documento (valor FEFO real)', $outValue > 0 && abs($after['unexplained_outflows']['total'] - $before['unexplained_outflows']['total'] - $outValue) < 0.01, $outValue);
ok('pérdida bruta = conocida + desconocida + sin documento', abs($after['gross_loss'] - ($after['known']['total'] + max(0, $after['unknown']['net']) + $after['unexplained_outflows']['total'])) < 0.01);
ok('pérdida neta = bruta − recuperado', abs($after['net_loss'] - ($after['gross_loss'] - $after['recovered'])) < 0.01);
ok('ventas sin descargo no suman a la pérdida', abs(($after['gross_loss'] - $before['gross_loss']) - $outValue) < 0.01);

// ---------------------------------------------------------------- 4. Alertas: detección, sin duplicados
echo "\n4. Centro de alertas: barrido\n";
$al = AlertCenterService::GetInstance();
$s1 = $al->Scan(null, 1);
$s2 = $al->Scan(null, 1);
$phAlert = $db->query("SELECT * FROM dairy_alerts WHERE rule_code = 'phantom_stock' AND dedupe_key LIKE 'phantom_stock:1:{$p->id}:%'")->fetch(PDO::FETCH_ASSOC);
ok('alerta de stock fantasma creada con plazo', $phAlert && $phAlert['status'] === 'open' && $phAlert['due_at'] > date('Y-m-d H:i:s'));
ok('alerta de salida sin documento', intval($val("SELECT COUNT(*) FROM dairy_alerts WHERE rule_code = 'unexplained_outflow' AND title LIKE '%" . str_replace("'", "''", $p->name) . "%'")) >= 1);
ok('alertas de venta sin descargo (una por venta)', intval($val("SELECT COUNT(*) FROM dairy_alerts WHERE rule_code = 'pos_unreconciled' AND title LIKE '%T19-%'")) === 25);
ok('segundo barrido no duplica', $s2['created'] === 0 && $s2['refreshed'] >= $s1['created'], json_encode([$s1, $s2]));

// ---------------------------------------------------------------- 5. Retiro de producto
echo "\n5. Retiro de producto multisucursal\n";
$rs = RecallService::GetInstance();
$freeBefore = floatval($val("SELECT SUM(amount) FROM stock WHERE product_id = {$p->id}"));
expectError('motivo obligatorio', fn() => $rs->CreateRecall(['product_id' => $p->id, 'reason' => 'x'], 1));
$supplier = intval($val('SELECT id FROM shopping_locations ORDER BY id LIMIT 1'));
$rc = $rs->CreateRecall(['product_id' => $p->id, 'reason' => 'Aviso del proveedor: lote contaminado', 'severity' => 'class_1', 'disposition' => 'return_supplier', 'supplier_id' => $supplier], 1);
$items = $db->query("SELECT * FROM product_recall_items WHERE recall_id = {$rc['id']}")->fetchAll(PDO::FETCH_ASSOC);
$qty = array_sum(array_column($items, 'amount'));
ok('retiene todo el stock libre en cuarentena', abs($qty - $freeBefore) < 0.001 && intval($val("SELECT COUNT(*) FROM quarantine_items WHERE status = 'in_quarantine' AND id IN (SELECT quarantine_id FROM product_recall_items WHERE recall_id = {$rc['id']})")) === count($items), "$qty de $freeBefore");
ok('folio RET-AAAA-NNNNN', (bool) preg_match('/^RET-\d{4}-\d{5}$/', $rc['recall_code']));
ok('agenda muestra lotes por separar', count(array_filter(ManagerAgendaService::GetInstance()->GetAgenda(1), fn($i) => $i['key'] === 'recalls')) === 1);
ok('crear otro retiro igual no encuentra stock libre', (function () use ($rs, $p) { try { $rs->CreateRecall(['product_id' => $p->id, 'reason' => 'Duplicado de prueba'], 1); return false; } catch (\Exception $e) { return true; } })());
expectError('no cierra con lotes sin confirmar', fn() => $rs->CloseRecall($rc['id'], null, 1));
expectError('diferencia sin explicación', fn() => $rs->ConfirmItem(intval($items[0]['id']), floatval($items[0]['amount']) - 1, '', 2));
$rs->ConfirmItem(intval($items[0]['id']), floatval($items[0]['amount']) - 1, 'Una unidad no estaba en góndola', 2);
foreach (array_slice($items, 1) as $it)
{
	$rs->ConfirmItem(intval($it['id']), floatval($it['amount']), null, 2);
}
expectError('no se confirma dos veces', fn() => $rs->ConfirmItem(intval($items[0]['id']), 1, 'x', 2));
$close = $rs->CloseRecall($rc['id'], 'Destrucción y reclamo', 1);
ok('cierre da de baja todo el stock retenido', abs(floatval($val("SELECT IFNULL(SUM(amount), 0) FROM stock WHERE product_id = {$p->id}"))) < 0.001 && abs($close['written_off_amount'] - $qty) < 0.001, json_encode($close));
ok('bajas con transacción de stock (merma trazable)', intval($val("SELECT COUNT(*) FROM waste_records w JOIN product_recall_items i ON i.waste_record_id = w.id WHERE i.recall_id = {$rc['id']} AND w.transaction_id IS NOT NULL")) === count($items));
ok('reclamo de crédito al proveedor por el costo real', abs(floatval($val("SELECT credit_requested_amount FROM supplier_returns WHERE id = " . intval($close['supplier_return_id']))) - $close['written_off_cost']) < 0.01 && $close['written_off_cost'] > 0);

// ---------------------------------------------------------------- 6. Auditoría de tienda
echo "\n6. Auditoría de tienda\n";
$as = StoreAuditService::GetInstance();
$tpl = $as->GetTemplates()[0];
$audit = $as->StartAudit(intval($tpl['id']), 1, 2);
ok('reanudar devuelve la misma auditoría en curso', $as->StartAudit(intval($tpl['id']), 1, 2)['id'] === $audit['id']);
expectError('no cierra sin responder todo', fn() => $as->SaveAnswers($audit['id'], [], true, null, 2));
$crit = array_values(array_filter($tpl['items'], fn($i) => intval($i['is_critical'])))[0];
$answers = array_map(fn($i) => ['item_id' => $i['id'], 'result' => $i['id'] == $crit['id'] ? 'fail' : 'pass'], $tpl['items']);
expectError('incumplimiento exige hallazgo', fn() => $as->SaveAnswers($audit['id'], $answers, true, null, 2));
$answers = array_map(fn($a) => $a + ['comment' => $a['result'] === 'fail' ? 'Cámara 2 a 8 °C sin registro' : ''], $answers);
$res = $as->SaveAnswers($audit['id'], $answers, true, null, 2);
$tw = array_sum(array_column($tpl['items'], 'weight'));
ok('puntaje ponderado', abs($res['score'] - round(($tw - $crit['weight']) / $tw * 100, 1)) < 0.01 && $res['critical_failures'] === 1, json_encode($res));
$task = $db->query("SELECT * FROM workforce_store_tasks WHERE source_type = 'audit' AND source_id = {$audit['id']}")->fetch(PDO::FETCH_ASSOC);
ok('tarea correctiva crítica con plazo de 24 h', $task && str_starts_with($task['title'], 'CRÍTICO') && abs(strtotime($task['due_time']) - time() - 86400) < 120);
expectError('auditoría cerrada no se modifica', fn() => $as->SaveAnswers($audit['id'], $answers, true, null, 2));

// ---------------------------------------------------------------- 7. Certificaciones y cobertura por demanda
echo "\n7. Certificaciones y cobertura por demanda\n";
$ws = WorkforceScheduleService::GetInstance();
$empId = $ws->SaveEmployee(['name' => 'Prueba Certificación', 'branch_id' => 1, 'position' => 'Auxiliar', 'department' => 'Lácteos']);
$ws->SaveRoleRequirements([['role' => 'Carnicero', 'certification' => 'Manipulación de alimentos']]);
$monday = date('Y-m-d', strtotime('monday next week'));
expectError('rol sin la certificación exigida', fn() => $ws->SaveShift(['employee_id' => $empId, 'shift_date' => $monday, 'start_time' => '08:00', 'end_time' => '12:00', 'role' => 'Carnicero'], 1));
ok('otro rol no exige la certificación', $ws->SaveShift(['employee_id' => $empId, 'shift_date' => $monday, 'start_time' => '08:00', 'end_time' => '12:00', 'role' => 'Cajero'], 1)['id'] > 0);
$ws->SaveCertification(['employee_id' => $empId, 'name' => 'Manipulación de alimentos', 'expires_date' => date('Y-m-d', strtotime('-1 day'))]);
expectError('certificación vencida bloquea', fn() => $ws->SaveShift(['employee_id' => $empId, 'shift_date' => date('Y-m-d', strtotime($monday . ' +1 day')), 'start_time' => '08:00', 'end_time' => '12:00', 'role' => 'Carnicero'], 1));
$ws->SaveCertification(['employee_id' => $empId, 'name' => 'Manipulación de alimentos', 'expires_date' => date('Y-m-d', strtotime('+10 days'))]);
ok('certificación vigente permite el turno', $ws->SaveShift(['employee_id' => $empId, 'shift_date' => date('Y-m-d', strtotime($monday . ' +1 day')), 'start_time' => '08:00', 'end_time' => '12:00', 'role' => 'Carnicero'], 1)['id'] > 0);
expectError('fechas invertidas', fn() => $ws->SaveCertification(['employee_id' => $empId, 'name' => 'X', 'issued_date' => '2026-05-01', 'expires_date' => '2026-01-01']));
$sug = $ws->SuggestCoverage(1, 8);
$at10 = array_filter($sug['rows'], fn($r) => $r['start_time'] <= '10:00' && $r['end_time'] > '10:00');
ok('cobertura sugerida incluye la franja de las 10 h con ventas', count($at10) > 0 && min(array_column($sug['rows'], 'min_staff')) >= 1, json_encode(array_values($at10)[0] ?? null));
ok('costo laboral vs venta calculado', isset($sug['labor']['sales']) && $sug['labor']['sales'] > 0);

// ---------------------------------------------------------------- 8. Ciclo de vida de alertas
echo "\n8. Alertas: ciclo de vida, segregación y cierre automático\n";
$s3 = $al->Scan(1, 1);
$auditAlert = $db->query("SELECT * FROM dairy_alerts WHERE rule_code = 'audit_failed' AND dedupe_key = 'audit_failed:{$audit['id']}'")->fetch(PDO::FETCH_ASSOC);
$certAlert = intval($val("SELECT COUNT(*) FROM dairy_alerts WHERE rule_code = 'cert_expiring' AND title LIKE '%Prueba Certificación%'"));
ok('auditoría reprobada genera alerta', (bool) $auditAlert);
ok('certificación por vencer genera alerta', $certAlert >= 1);
$id = intval($auditAlert['id']);
$al->Assign($id, 2, 1);
$al->Start($id, 2);
expectError('resolver exige descripción', fn() => $al->Resolve($id, 'ok', 2));
$next = $al->Resolve($id, 'Técnico reparó la cámara; registro de temperatura retomado', 2);
ok('regla con aprobación pasa a "por aprobar"', $next === 'pending_approval');
expectError('quien resolvió no aprueba su cierre', fn() => $al->Decide($id, true, null, 2));
$al->Decide($id, false, 'Falta evidencia del registro', 1);
ok('rechazo vuelve a en curso', $val("SELECT status FROM dairy_alerts WHERE id = $id") === 'in_progress');
$al->Resolve($id, 'Adjunto registro del turno completo', 2);
$al->Decide($id, true, null, 1);
ok('aprobado por otra persona → cerrada', $val("SELECT status FROM dairy_alerts WHERE id = $id") === 'closed');
ok('bitácora completa (creada, asignada, en curso, resuelta ×2, rechazada, aprobada)', intval($val("SELECT COUNT(*) FROM dairy_alerts a JOIN dairy_alert_events e ON e.alert_id = a.id WHERE a.id = $id")) === 7);
expectError('no se actúa sobre una cerrada', fn() => $al->Start($id, 1));
$al->Scan(1, 1);
ok('alerta cerrada no se reabre al volver a barrer', $val("SELECT status FROM dairy_alerts WHERE id = $id") === 'closed' && intval($val("SELECT COUNT(*) FROM dairy_alerts WHERE dedupe_key = 'audit_failed:{$audit['id']}'")) === 1);

// Cierre automático: el stock fantasma desaparece si el producto vuelve a venderse (aquí: ya no tiene stock tras el retiro)
$phId = intval($phAlert['id']);
ok('stock fantasma abierto se cierra solo cuando la condición desaparece', $val("SELECT status FROM dairy_alerts WHERE id = $phId") === 'closed' && str_contains($val("SELECT resolution FROM dairy_alerts WHERE id = $phId"), 'automática'));
$dis = intval($val("SELECT id FROM dairy_alerts WHERE rule_code = 'pos_unreconciled' AND status = 'open' LIMIT 1"));
expectError('descartar exige motivo', fn() => $al->Dismiss($dis, 'no', 1));
$al->Dismiss($dis, 'Venta de prueba cargada directo en BD', 1);
ok('descartada con motivo', $val("SELECT status FROM dairy_alerts WHERE id = $dis") === 'dismissed');
$al->SaveRule('high_value_waste', ['threshold' => 10, 'enabled' => 0]);
ok('regla configurable (umbral y desactivación)', floatval($val("SELECT threshold FROM dairy_alert_rules WHERE code = 'high_value_waste'")) == 10 && $val("SELECT enabled FROM dairy_alert_rules WHERE code = 'high_value_waste'") == 0);
expectError('umbral inválido', fn() => $al->SaveRule('phantom_stock', ['threshold' => 2]));
$sum = $al->Summary(1);
ok('resumen coherente con la lista', $sum['active'] === count($al->GetAlerts('active', 1)));

echo "\n$count verificaciones, $fails fallidas\n";
exit($fails > 0 ? 1 : 0);
