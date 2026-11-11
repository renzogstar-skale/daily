<?php
/**
 * Punto de venta (PosService): proceso completo desde el escaneo hasta el cierre de caja, sobre una copia de la base.
 * Apertura → escaneo → cotización con impuesto → descuento autorizado → pago mixto → reintento sin doble cobro →
 * evento único → suspensión/recuperación → reimpresión controlada → anulación autorizada → retiro → arqueo y cierre.
 * Uso: php tests/dairy/test_pos.php [base.db]
 */

use Grocy\Services\PosService;

require __DIR__ . '/bootstrap.php';

$pos = PosService::GetInstance();
$db = Grocy\Services\DatabaseService::GetInstance()->GetDbConnectionRaw();
$near = fn($a, $b, $tol = 0.005) => abs(floatval($a) - floatval($b)) <= $tol;
$throws = function (callable $fn, string $needle = '') { try { $fn(); return false; } catch (\Throwable $e) { return $needle === '' || stripos($e->getMessage(), $needle) !== false; } };
$uuid = fn() => bin2hex(random_bytes(16));
$uid = GROCY_USER_ID;

// Supervisor y cajero de prueba
$users = Grocy\Services\UsersService::GetInstance();
$users->CreateUser('sup_pos_test', 'Super', 'Visor', 'Clave-Sup-1');
$users->CreateUser('caj_pos_test', 'Caja', 'Uno', 'Clave-Caj-1');
$supId = intval($db->query("SELECT id FROM users WHERE username = 'sup_pos_test'")->fetchColumn());
$permId = intval($db->query("SELECT id FROM permission_hierarchy WHERE name = 'SALES_SUPERVISE'")->fetchColumn());
// Los usuarios nuevos reciben permisos por defecto: se dejan solo los de la prueba
$db->exec("DELETE FROM user_permissions WHERE user_id IN (SELECT id FROM users WHERE username IN ('sup_pos_test', 'caj_pos_test'))");
$db->prepare('INSERT INTO user_permissions (permission_id, user_id) VALUES (?, ?)')->execute([$permId, $supId]);

// Sucursal y productos con stock y precio (uno con impuesto y otro sin impuesto si existen)
$set = $pos->Settings();
$branch = 1;
$prods = $db->query("SELECT p.id, p.department, SUM(s.amount) AS stock FROM products p JOIN stock s ON s.product_id = p.id JOIN locations l ON l.id = s.location_id
	WHERE l.branch_id = $branch AND IFNULL(l.is_transit, 0) = 0 AND p.sale_price > 0 AND p.id NOT IN (SELECT product_id FROM quarantine_items WHERE status = 'in_quarantine')
	GROUP BY p.id HAVING SUM(s.amount) >= 6 ORDER BY p.id")->fetchAll(\PDO::FETCH_ASSOC);
$prods = array_values(array_filter($prods, fn($p) => $p['stock'] - Grocy\Services\CustomerOrderService::GetInstance()->Reserved(intval($p['id']), $branch, null) >= 6));
$dept = function ($p) { foreach (Grocy\Services\DailyMetricsService::Departments() as $k => $d) { if (in_array($p['department'], $d[2] ?? [], true)) return $k; } return 'otros'; };
$taxed = null; $free = null;
foreach ($prods as $p)
{
	$r = floatval($set['tax_rates'][$dept($p)] ?? $set['default_tax_rate']);
	if ($r > 0 && !$taxed) $taxed = $p + ['rate' => $r];
	if ($r == 0 && !$free) $free = $p + ['rate' => 0];
}
$A = $taxed ?: $prods[0];
$B = $free ?: $prods[1];
echo "Productos: {$A['id']} (impuesto " . ($A['rate'] ?? '?') . " %), {$B['id']} (impuesto " . ($B['rate'] ?? '?') . " %)\n";
$stockOf = fn($pid) => floatval($db->query("SELECT IFNULL(SUM(s.amount), 0) FROM stock s JOIN locations l ON l.id = s.location_id WHERE s.product_id = $pid AND l.branch_id = $branch")->fetchColumn());

echo "\n1. Apertura de caja\n";
$reg = 'Caja Prueba';
ok('Sin turno abierto no se vende', $throws(fn() => $pos->Checkout(['client_ref' => $uuid(), 'branch_id' => $branch, 'register' => $reg, 'items' => [['product_id' => $A['id'], 'amount' => 1]], 'expected_total' => 0, 'payments' => []], $uid), 'cerrada'));
$sh = $pos->OpenShift($branch, $reg, 150, 'Mañana', $uid);
ok('Turno abierto con fondo', $sh['status'] === 'open' && $near($sh['expected_cash'], 150));
ok('No se abren dos turnos en la misma caja', $throws(fn() => $pos->OpenShift($branch, $reg, 100, 'Tarde', $uid), 'ya tiene'));

echo "\n2. Escaneo, carrito, impuesto y descuento autorizado\n";
$code = $db->query("SELECT barcode FROM product_barcodes WHERE product_id = {$A['id']} LIMIT 1")->fetchColumn() ?: $db->query("SELECT sku FROM products WHERE id = {$A['id']}")->fetchColumn();
$found = intval($db->query('SELECT p.id FROM products p LEFT JOIN product_barcodes pb ON pb.product_id = p.id WHERE pb.barcode = ' . $db->quote($code) . ' OR p.sku = ' . $db->quote($code) . ' LIMIT 1')->fetchColumn());
ok('El código escaneado encuentra el producto', $found === intval($A['id']), $code);
$items = [['product_id' => $A['id'], 'amount' => 2], ['product_id' => $B['id'], 'amount' => 1], ['product_id' => $A['id'], 'amount' => 1]];
$q0 = $pos->Quote($items);
ok('Líneas repetidas se agrupan', count($q0['lines']) === 2 && $q0['lines'][0]['amount'] == 3);
ok('Total = subtotal − descuento + impuesto', $near($q0['total'], $q0['subtotal'] - $q0['discount'] + $q0['tax']));
ok('Impuesto por departamento sobre la base', $near($q0['tax'], array_sum(array_map(fn($l) => round($l['subtotal'] * $l['tax_rate'] / 100, 2), $q0['lines'])), 0.011));
$q = $pos->Quote($items, ['type' => 'pct', 'value' => 10]);
ok('Descuento 10 % e impuesto sobre la base descontada', $near($q['discount'], round($q0['subtotal'] * 0.1, 2)) && $q['tax'] <= $q0['tax'] + 0.001);
$pay = fn($q, $extra = []) => [['method' => 'cash', 'amount' => round($q['total'] - 5, 2), 'tendered' => round($q['total'] - 5, 2) + 20], ['method' => 'card', 'amount' => 5, 'reference' => 'AUT-123456']];
$base = ['branch_id' => $branch, 'register' => $reg, 'customer_id' => 1, 'items' => $items];
ok('Descuento sin autorización rechazado', $throws(fn() => $pos->Checkout($base + ['client_ref' => $uuid(), 'discount' => ['type' => 'pct', 'value' => 10, 'reason' => 'Cliente frecuente'], 'expected_total' => $q['total'], 'payments' => $pay($q)], $uid), 'autoriza'));
ok('Clave de supervisor errónea rechazada', $throws(fn() => $pos->Authorize('discount', 'sup_pos_test', 'mala', 'Cliente frecuente', ['pct' => 10, 'branch_id' => $branch], $uid), 'incorrectos'));
ok('Usuario sin permiso de supervisor no autoriza', $throws(fn() => $pos->Authorize('discount', 'caj_pos_test', 'Clave-Caj-1', 'Cliente frecuente', ['pct' => 10], $uid), 'permiso'));
ok('Descuento sobre el máximo del supervisor rechazado', $throws(fn() => $pos->Authorize('discount', 'sup_pos_test', 'Clave-Sup-1', 'x x x', ['pct' => $set['supervisor_max_discount_pct'] + 1], $uid), 'máximo'));
$auth = $pos->Authorize('discount', 'sup_pos_test', 'Clave-Sup-1', 'Cliente frecuente', ['pct' => 10, 'branch_id' => $branch], $uid);
ok('Total distinto del calculado rechazado (no se cobra otro total)', $throws(fn() => $pos->Checkout($base + ['client_ref' => $uuid(), 'discount' => ['type' => 'pct', 'value' => 10, 'reason' => 'Cliente frecuente', 'auth_id' => $auth['id']], 'expected_total' => $q['total'] + 1, 'payments' => $pay($q)], $uid), 'no coincide'));
ok('Pagos que no suman el total rechazados', $throws(fn() => $pos->Checkout($base + ['client_ref' => $uuid(), 'discount' => ['type' => 'pct', 'value' => 10, 'reason' => 'Cliente frecuente', 'auth_id' => $auth['id']], 'expected_total' => $q['total'], 'payments' => [['method' => 'cash', 'amount' => 1, 'tendered' => 1]]], $uid), 'no cubren'));
ok('Número de tarjeta en la referencia rechazado', $throws(fn() => $pos->Checkout($base + ['client_ref' => $uuid(), 'discount' => ['type' => 'pct', 'value' => 10, 'reason' => 'Cliente frecuente', 'auth_id' => $auth['id']], 'expected_total' => $q['total'], 'payments' => [['method' => 'card', 'amount' => $q['total'], 'reference' => '4111 1111 1111 1111']]], $uid), 'tarjeta'));

echo "\n3. Cobro mixto, idempotencia y evento único\n";
$stA = $stockOf($A['id']); $stB = $stockOf($B['id']);
$ref = $uuid();
$t0 = microtime(true);
$r = $pos->Checkout($base + ['client_ref' => $ref, 'discount' => ['type' => 'pct', 'value' => 10, 'reason' => 'Cliente frecuente', 'auth_id' => $auth['id']], 'expected_total' => $q['total'], 'payments' => $pay($q)], $uid);
$ms = round((microtime(true) - $t0) * 1000);
$sid = $r['sale']['id'];
ok('Venta registrada con total cobrado = registrado', $near($r['sale']['total'], $q['total']) && $r['sale']['payment_method'] === 'mixed', $ms . ' ms');
ok('Pagos guardados: efectivo con cambio y tarjeta solo con autorización', count($r['payments']) === 2 && $near($r['payments'][0]['change_given'], 20) && $r['payments'][1]['reference'] === 'AUT-123456');
ok('Subtotal − descuentos + impuesto = total registrado', $near($r['sale']['subtotal'] - $r['sale']['discount'] + $r['sale']['tax'], $r['sale']['total'], 0.011), json_encode($r['sale']));
ok('Σ pagos = total', $near(array_sum(array_column($r['payments'], 'amount')), $r['sale']['total']));
ok('Stock descontado una vez', $near($stockOf($A['id']), $stA - 3, 0.001) && $near($stockOf($B['id']), $stB - 1, 0.001));
ok('Autorización consumida y ligada a la venta', $db->query("SELECT status || ':' || sale_id FROM daily_pos_authorizations WHERE id = {$auth['id']}")->fetchColumn() === "used:$sid");
ok('La autorización no se reutiliza', $throws(fn() => $pos->Checkout($base + ['client_ref' => $uuid(), 'discount' => ['type' => 'pct', 'value' => 10, 'reason' => 'Cliente frecuente', 'auth_id' => $auth['id']], 'expected_total' => $q['total'], 'payments' => $pay($q)], $uid), 'autoriza'));
$again = $pos->Checkout($base + ['client_ref' => $ref, 'discount' => ['type' => 'pct', 'value' => 10, 'reason' => 'Cliente frecuente', 'auth_id' => $auth['id']], 'expected_total' => $q['total'], 'payments' => $pay($q)], $uid);
ok('Reintento con el mismo client_ref: misma venta, sin doble cobro', $again['replayed'] && $again['sale']['id'] === $sid && intval($db->query("SELECT COUNT(*) FROM sales WHERE client_ref = '$ref'")->fetchColumn()) === 1
	&& intval($db->query("SELECT COUNT(*) FROM daily_pos_payments WHERE sale_id = $sid")->fetchColumn()) === 2 && $near($stockOf($A['id']), $stA - 3, 0.001));
ok('Un solo evento de venta confirmada', intval($db->query("SELECT COUNT(*) FROM daily_domain_events WHERE event_uuid = 'sale-confirmed-$ref'")->fetchColumn()) === 1);
ok('Costo y margen registrados (rentabilidad)', floatval($db->query("SELECT total_cogs FROM sales WHERE id = $sid")->fetchColumn()) > 0);
ok('Índice único de client_ref en la base', $throws(fn() => $db->exec("UPDATE sales SET client_ref = '$ref' WHERE id = (SELECT MIN(id) FROM sales)")));

echo "\n4. Sin conexión\n";
$q2 = $pos->Quote([['product_id' => $B['id'], 'amount' => 1]]);
ok('Sin conexión: tarjeta sin código de autorización rechazada', $throws(fn() => $pos->Checkout(['client_ref' => $uuid(), 'branch_id' => $branch, 'register' => $reg, 'items' => [['product_id' => $B['id'], 'amount' => 1]], 'expected_total' => $q2['total'],
	'payments' => [['method' => 'card', 'amount' => $q2['total']]], 'offline_created_at' => date('c', time() - 600), 'shift_id' => $sh['id']], $uid), 'autorización'));
$offRef = $uuid();
$off = $pos->Checkout(['client_ref' => $offRef, 'branch_id' => $branch, 'register' => $reg, 'items' => [['product_id' => $B['id'], 'amount' => 1]], 'expected_total' => $q2['total'],
	'payments' => [['method' => 'cash', 'amount' => $q2['total'], 'tendered' => $q2['total']]], 'offline_created_at' => date('Y-m-d H:i:s', time() - 600), 'shift_id' => $sh['id']], $uid);
ok('Venta sin conexión con su hora real y su turno', $off['sale']['datetime'] === date('Y-m-d H:i:s', time() - 600) || abs(strtotime($off['sale']['datetime']) - (time() - 600)) < 5);
$off2 = $pos->Checkout(['client_ref' => $offRef, 'branch_id' => $branch, 'register' => $reg, 'items' => [['product_id' => $B['id'], 'amount' => 1]], 'expected_total' => $q2['total'],
	'payments' => [['method' => 'cash', 'amount' => $q2['total'], 'tendered' => $q2['total']]], 'offline_created_at' => date('Y-m-d H:i:s', time() - 600), 'shift_id' => $sh['id']], $uid);
ok('Resincronización duplicada no crea otra venta', $off2['replayed'] && $off2['sale']['id'] === $off['sale']['id']);

echo "\n5. Suspensión, recibo y anulación\n";
$held = Grocy\Services\SalesService::GetInstance()->HoldSale(['branch_id' => $branch, 'items' => [['product_id' => $A['id'], 'amount' => 1, 'unit_price' => 1]], 'label' => 'Cliente busca billetera'], $uid);
$rec = $pos->Recall(intval($held->id), $branch);
ok('Ticket suspendido recuperado con sus líneas', count($rec['items']) === 1);
ok('No se recupera dos veces', $throws(fn() => $pos->Recall(intval($held->id), $branch), 'ya fue'));
$rp = $pos->Reprint($sid, null, $uid);
ok('Primera reimpresión libre y numerada', $rp['copy_no'] === 1);
ok('Siguiente reimpresión exige autorización', $throws(fn() => $pos->Reprint($sid, null, $uid), 'autoriza'));
$ra = $pos->Authorize('reprint', 'sup_pos_test', 'Clave-Sup-1', 'Cliente perdió el recibo', ['sale_id' => $sid, 'branch_id' => $branch], $uid);
ok('Reimpresión autorizada', $pos->Reprint($sid, $ra['id'], $uid)['copy_no'] === 2);
$stA2 = $stockOf($A['id']);
ok('Anulación sin autorización rechazada', $throws(fn() => $pos->Void($sid, 'Error de cobro', null, $uid), 'autoriza'));
$va = $pos->Authorize('void', 'sup_pos_test', 'Clave-Sup-1', 'Error de cobro', ['sale_id' => $sid, 'branch_id' => $branch], $uid);
$v = $pos->Void($sid, 'Error de cobro', $va['id'], $uid);
ok('Venta anulada y stock reintegrado', $v['sale']['status'] === 'cancelled' && $near($stockOf($A['id']), $stA2 + 3, 0.001));
ok('No se anula dos veces', $throws(fn() => $pos->Void($sid, 'Otra vez', $va['id'], $uid), 'ya está'));
$tx = $pos->Transactions($branch, ['shift_id' => $sh['id']]);
ok('Consulta de transacciones del turno', count($tx) === 2 && in_array('cancelled', array_column($tx, 'status'), true));

echo "\n6. Arqueo y cierre\n";
$sum = $pos->ShiftSummary($sh['id']);
$cashSales = round($off['sale']['total'], 2);
ok('Efectivo esperado = fondo + efectivo de ventas vigentes (anulada excluida)', $near($sum['expected_cash'], 150 + $cashSales), json_encode([$sum['expected_cash'], 150 + $cashSales]));
ok('Retiro mayor que la gaveta rechazado', $throws(fn() => $pos->CashMovement($sh['id'], 'drop', 99999, 'Bóveda', $uid), 'supera'));
$pos->CashMovement($sh['id'], 'drop', 100, 'Retiro a bóveda', $uid);
$exp = $pos->ShiftSummary($sh['id'])['expected_cash'];
ok('El retiro baja el efectivo esperado', $near($exp, 50 + $cashSales));
ok('Conteo por denominación que no cuadra rechazado', $throws(fn() => $pos->CloseShift($sh['id'], $exp, ['20' => 1], '', null, $uid), 'denominación'));
ok('Diferencia fuera de tolerancia sin autorización rechazada', $throws(fn() => $pos->CloseShift($sh['id'], $exp - 20, null, 'Faltante en gaveta', null, $uid), 'autoriza'));
$ca = $pos->Authorize('cash_difference', 'sup_pos_test', 'Clave-Sup-1', 'Faltante en gaveta', ['shift_id' => $sh['id'], 'branch_id' => $branch, 'amount' => -20], $uid);
$closed = $pos->CloseShift($sh['id'], $exp - 20, null, 'Faltante en gaveta', $ca['id'], $uid);
ok('Turno cerrado con diferencia −20 autorizada', $closed['status'] === 'closed' && $near($closed['cash_difference'], -20) && $closed['difference_reason'] === 'Faltante en gaveta');
ok('Con el turno cerrado no se vende en esa caja', $throws(fn() => $pos->Checkout(['client_ref' => $uuid(), 'branch_id' => $branch, 'register' => $reg, 'items' => [['product_id' => $B['id'], 'amount' => 1]], 'expected_total' => $q2['total'], 'payments' => [['method' => 'cash', 'amount' => $q2['total']]]], $uid), 'cerrada'));

echo "\n7. Canje de puntos en POS y reverso al anular\n";
$crm = Grocy\Services\CustomerCrmService::GetInstance();
$crm->SaveSettings(['loyalty_enabled' => 1, 'amount_per_point' => 10, 'point_value' => 1.0, 'loyalty_start' => '2020-01-01']);
$custPt = $crm->SaveCustomer(['name' => 'Cliente Puntos POS', 'phone' => '+57 310 999 8877', 'confirm_duplicate' => 1])['id'];
$crm->LoyaltyMove($custPt, 'adjust', 50, 'Saldo inicial prueba POS');
$shPt = $pos->OpenShift($branch, 'Caja Puntos', 100.0, 'Tarde', $uid);
ok('Público General no canjea puntos en POS', $throws(fn() => $pos->Checkout(['client_ref' => $uuid(), 'branch_id' => $branch, 'register' => 'Caja Puntos', 'customer_id' => 1, 'items' => [['product_id' => $B['id'], 'amount' => 1]], 'expected_total' => $q2['total'], 'payments' => [['method' => 'points', 'amount' => $q2['total']]]], $uid), 'registrado'));
$ptRes = $pos->Checkout(['client_ref' => $uuid(), 'branch_id' => $branch, 'register' => 'Caja Puntos', 'customer_id' => $custPt, 'items' => [['product_id' => $B['id'], 'amount' => 1]], 'expected_total' => $q2['total'], 'payments' => [['method' => 'points', 'amount' => $q2['total']]]], $uid);
$balAfter = $crm->Loyalty($custPt)['balance'];
ok('POS descuenta puntos atómicamente al cobrar', $ptRes['sale']['payment_method'] === 'points' && $balAfter < 50);
$vPtAuth = $pos->Authorize('void', 'sup_pos_test', 'Clave-Sup-1', 'Reverso canje', ['sale_id' => $ptRes['sale']['id'], 'branch_id' => $branch], $uid);
$pos->Void($ptRes['sale']['id'], 'Reverso canje puntos', $vPtAuth['id'], $uid);
ok('Anulación en POS reintegra los puntos canjeados', $crm->Loyalty($custPt)['balance'] >= 50);

echo "\n$count pruebas, $fails fallas\n";
exit($fails ? 1 : 0);
