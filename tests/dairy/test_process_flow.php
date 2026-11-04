<?php
/**
 * DAILY Process Flow: el expediente se deriva del estado real y avanza con las operaciones reales.
 *  1. OC: propuesta → aprobación → envío → recepción → stock/Kardex (relectura tras cada operación)
 *  2. Merma pendiente: el paso actual es la aprobación; no se puede cerrar el caso sin aprobar; aprobada descuenta inventario
 *  3. Pasos omitidos se muestran como «omitido», nunca «completado»; anulados quedan interrumpidos
 *  4. Consultar expedientes y bandeja no escribe movimientos (stock_log intacto)
 *  5. Errores: tipo desconocido, documento inexistente
 */

use Grocy\Services\MrpService;
use Grocy\Services\ProcessFlowService;
use Grocy\Services\WasteService;

require __DIR__ . '/bootstrap.php';

$pf = ProcessFlowService::GetInstance();
$state = fn(array $f, string $code) => current(array_filter($f['steps'], fn($s) => $s['code'] === $code))['state'] ?? null;
$logCount = fn() => intval($val('SELECT COUNT(*) FROM stock_log'));

echo "\n1. Orden de compra de punta a punta\n";
$product = $db->query("SELECT p.id, p.location_id, p.default_best_before_days FROM products p JOIN locations l ON l.id = p.location_id WHERE p.active = 1 AND l.branch_id = 1 AND p.default_best_before_days > 0 ORDER BY p.id LIMIT 1")->fetch(PDO::FETCH_OBJ);
$supplier = intval($val('SELECT id FROM shopping_locations WHERE active = 1 ORDER BY id LIMIT 1'));
$mrp = MrpService::GetInstance();
$order = $mrp->CreateProcurementOrder(['branch_id' => 1, 'supplier_id' => $supplier, 'items' => [['product_id' => $product->id, 'amount' => 10, 'unit_price' => 2]]], 1);
$f = $pf->Get('procurement_order', $order['id']);
ok('OC nueva: paso actual = aprobación', $f['current_step'] === 'Aprobación de compra' && $state($f, 'created') === 'done' && $f['progress_pct'] < 20, $f['current_step'] . ' ' . $f['progress_pct'] . '%');
ok('responsable por rol definido', $f['responsible_role'] !== null, (string) $f['responsible_role']);
$mrp->ApproveProcurementOrder($order['id'], 1);
$mrp->SendProcurementOrder($order['id'], 1, date('Y-m-d'));
$f = $pf->Get('procurement_order', $order['id']);
ok('tras aprobar y enviar: paso actual = recepción', $f['current_step'] === 'Recepción en tienda' && $state($f, 'approved') === 'done' && $state($f, 'sent') === 'done', $f['current_step']);
$detail = $mrp->GetProcurementOrderDetail($order['id']);
$mrp->ReceiveProcurementOrder($order['id'], ['items' => [['order_item_id' => $detail['items'][0]['id'], 'delivered_amount' => 10,
	'best_before_date' => date('Y-m-d', strtotime('+' . $product->default_best_before_days . ' days')), 'location_id' => $product->location_id]]], 1);
$f = $pf->Get('procurement_order', $order['id']);
ok('recibida: recepción y stock/Kardex completados', $state($f, 'received') === 'done' && $state($f, 'stock') === 'done', $f['current_step'] ?? 'fin');
ok('documentos relacionados: recepción y movimiento de stock', count(array_filter($f['related'], fn($r) => $r['type'] === 'stock_transaction')) === 1 && count(array_filter($f['related'], fn($r) => $r['type'] === 'receipt')) === 1);
$tx = current(array_filter($f['related'], fn($r) => $r['type'] === 'stock_transaction'))['id'];
ok('el movimiento enlazado existe en stock_log', intval($val("SELECT COUNT(*) FROM stock_log WHERE transaction_id = " . $db->quote($tx))) > 0);
ok('siguiente módulo declarado', str_contains((string) $f['next_module'], 'Kardex'));

echo "\n2. Merma: aprobación obligatoria antes de cerrar\n";
$ws = WasteService::GetInstance();
$pending = $db->query("SELECT id FROM waste_records WHERE status = 'pending_approval' AND IFNULL(inventory_deducted, 0) = 0 ORDER BY id LIMIT 1")->fetchColumn();
if ($pending)
{
	$f = $pf->Get('waste_record', intval($pending));
	ok('merma pendiente: paso actual = aprobación', $f['current_step'] === 'Aprobación', (string) $f['current_step']);
	ok('responsable: aprobador segregado', str_contains((string) $f['responsible_role'], 'Aprobador'));
	expectError('cerrar caso de merma sin aprobar', fn() => $ws->UpdateCaseWorkflow(intval($pending), ['workflow_status' => 'closed'], 1));
	expectError('estado de caso inexistente', fn() => $ws->UpdateCaseWorkflow(intval($pending), ['workflow_status' => 'hecho'], 1));
	ok('el intento rechazado no cambió el registro', $val("SELECT COUNT(*) FROM waste_records WHERE id = $pending AND closed_timestamp IS NULL AND workflow_status <> 'closed'") == 1);
}
else
{
	ok('existe una merma pendiente en la base de prueba', false);
}

echo "\n3. Pasos omitidos e interrumpidos\n";
$skipped = $db->query("SELECT id FROM dairy_alerts WHERE status = 'closed' AND approved_at IS NULL ORDER BY id LIMIT 1")->fetchColumn();
if ($skipped)
{
	$f = $pf->Get('alert', intval($skipped));
	ok('alerta cerrada sin verificación: «verified» omitido, no completado', $state($f, 'verified') === 'skipped' && $state($f, 'closed') === 'done');
	ok('progreso < 100% cuando hubo pasos omitidos', $f['progress_pct'] < 100, $f['progress_pct'] . '%');
}
$cancelled = $db->query("SELECT id FROM customer_orders WHERE status = 'cancelled' LIMIT 1")->fetchColumn();
if ($cancelled)
{
	$f = $pf->Get('customer_order', intval($cancelled));
	ok('pedido anulado: expediente interrumpido', $f['outcome'] === 'interrupted' && $f['next_step'] === null);
}
$o2 = $mrp->CreateProcurementOrder(['branch_id' => 1, 'supplier_id' => $supplier, 'items' => [['product_id' => $product->id, 'amount' => 1, 'unit_price' => 2]]], 1);
$db->exec("UPDATE procurement_orders SET status = 'cancelled', close_reason = 'prueba' WHERE id = " . intval($o2['id']));
$f = $pf->Get('procurement_order', $o2['id']);
ok('OC anulada: paso actual «interrumpido» con motivo', $f['outcome'] === 'interrupted' && $state($f, 'approved') === 'failed' && str_contains((string) current($f['steps'])['label'], 'Propuesta'));

echo "\n4. Consultar no genera movimientos\n";
$before = $logCount();
$inbox = $pf->Inbox([], 5);
foreach (['customer_order', 'stock_transfer', 'quarantine', 'count_session', 'store_task', 'inventory_adjustment', 'customer_return'] as $t)
{
	$any = current(array_filter($inbox, fn($g) => $g['type'] === $t));
	if ($any && $any['items'])
	{
		$x = $pf->Get($t, $any['items'][0]['id']);
		ok("expediente $t con pasos, historial y responsable o rol", count($x['steps']) >= 3 && is_array($x['history']) && ($x['owner'] !== null || $x['responsible_role'] !== null), $x['code']);
	}
}
ok('bandeja cubre los 10 procesos', count($inbox) === count(ProcessFlowService::TYPES), count($inbox));
ok('stock_log sin cambios tras consultar', $logCount() === $before);

echo "\n5. Errores controlados\n";
expectError('tipo desconocido', fn() => $pf->Get('nope', 1));
expectError('documento inexistente', fn() => $pf->Get('customer_order', 99999999));

echo "\n6. Reglas de integridad → Centro de Excepciones → automatización\n";
$ac = \Grocy\Services\AlertCenterService::GetInstance();
$auto = \Grocy\Services\AutomationEngineService::GetInstance();
// Lote vencido real: se fuerza el vencimiento de un lote con stock en la sucursal 1 (copia de prueba)
$lot = $db->query("SELECT s.stock_id, s.product_id, SUM(s.amount) AS qty FROM stock s JOIN locations l ON l.id = s.location_id WHERE l.branch_id = 1 AND IFNULL(l.is_transit, 0) = 0 AND s.amount > 0
	AND NOT EXISTS (SELECT 1 FROM quarantine_items q WHERE q.stock_id = s.stock_id AND q.status = 'in_quarantine') GROUP BY s.stock_id ORDER BY s.id LIMIT 1")->fetch(PDO::FETCH_OBJ);
$db->exec("UPDATE stock SET best_before_date = date('now', 'localtime', '-1 day') WHERE stock_id = " . $db->quote($lot->stock_id));
$ac->Scan(1, 1);
$alertId = intval($val("SELECT id FROM dairy_alerts WHERE dedupe_key = " . $db->quote('expired_lot_unblocked:' . $lot->stock_id) . " AND status = 'open'"));
ok('lote vencido genera alerta expired_lot_unblocked', $alertId > 0);
$exc = $pf->Exceptions(1);
$grp = current(array_filter($exc['groups'], fn($g) => $g['rule_code'] === 'expired_lot_unblocked'));
ok('aparece en el Centro de Excepciones con acción recomendada', $grp && $grp['count'] >= 1 && $grp['action'] !== null);
ok('expediente de la alerta enlaza el módulo de origen', in_array('/quality', array_column($pf->Get('alert', $alertId)['related'], 'href'), true));
ok('regla de bloqueo creada inactiva (requiere autorización)', $val("SELECT is_active FROM automation_rules WHERE code = 'QUA_EXPIRED_BLOCK'") === 0 || $val("SELECT is_active FROM automation_rules WHERE code = 'QUA_EXPIRED_BLOCK'") === '0');
$db->exec("UPDATE automation_rules SET is_active = 1 WHERE code = 'QUA_EXPIRED_BLOCK'");
$logBefore = $logCount();
$auto->OnAlert('alert_created', $alertId);
$held = floatval($val("SELECT IFNULL(SUM(amount), 0) FROM quarantine_items WHERE stock_id = " . $db->quote($lot->stock_id) . " AND status = 'in_quarantine' AND location_id IS NOT NULL"));
ok('automatización aísla el stock real del lote, con ubicación', abs($held - floatval($val("SELECT SUM(s.amount) FROM stock s JOIN locations l ON l.id = s.location_id WHERE l.branch_id = 1 AND s.stock_id = " . $db->quote($lot->stock_id)))) < 0.001, "$held u");
$auto->OnAlert('alert_created', $alertId);
ok('reintento del mismo evento no duplica la cuarentena', abs(floatval($val("SELECT IFNULL(SUM(amount), 0) FROM quarantine_items WHERE stock_id = " . $db->quote($lot->stock_id) . " AND status = 'in_quarantine'")) - $held) < 0.001);
ok('bloquear no mueve stock (sin movimientos nuevos)', $logCount() === $logBefore);
$ac->Scan(1, 1);
ok('tras el bloqueo la excepción se autocierra', $val("SELECT status FROM dairy_alerts WHERE id = $alertId") === 'closed');

echo "
$count verificaciones, $fails fallidas
";
exit($fails > 0 ? 1 : 0);
