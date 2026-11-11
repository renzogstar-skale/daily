<?php
/**
 * Devoluciones comerciales del POS (PosReturnService) sobre una copia de la base: búsqueda, elegibilidad, reembolso al
 * medio original con topes, nota de crédito y cambio, autorización por importe y rol, duplicados, inspección sin
 * reingreso automático de stock, comprobante, historial e indicadores.
 * Uso: php tests/dairy/test_pos_returns.php [base.db]
 */

use Grocy\Services\PosReturnService;
use Grocy\Services\PosService;

require __DIR__ . '/bootstrap.php';

$pos = PosService::GetInstance();
$ret = PosReturnService::GetInstance();
$db = Grocy\Services\DatabaseService::GetInstance()->GetDbConnectionRaw();
$near = fn($a, $b, $tol = 0.005) => abs(floatval($a) - floatval($b)) <= $tol;
$throws = function (callable $fn, string $needle = '') { try { $fn(); return false; } catch (\Throwable $e) { return $needle === '' || stripos($e->getMessage(), $needle) !== false; } };
$uuid = fn() => bin2hex(random_bytes(16));
$admin = GROCY_USER_ID;
$branch = 1;
$reg = 'Caja Dev';

$users = Grocy\Services\UsersService::GetInstance();
$users->CreateUser('sup_ret_test', 'Super', 'Dev', 'Clave-Sup-2');
$users->CreateUser('caj_ret_test', 'Caja', 'Dev', 'Clave-Caj-2');
$sup = intval($db->query("SELECT id FROM users WHERE username = 'sup_ret_test'")->fetchColumn());
$caj = intval($db->query("SELECT id FROM users WHERE username = 'caj_ret_test'")->fetchColumn());
$db->exec("DELETE FROM user_permissions WHERE user_id IN ($sup, $caj)");
$db->exec("INSERT INTO user_permissions (permission_id, user_id) SELECT id, $sup FROM permission_hierarchy WHERE name = 'SALES_SUPERVISE'");
$db->exec("INSERT INTO user_permissions (permission_id, user_id) SELECT id, $caj FROM permission_hierarchy WHERE name = 'SALES_POS'");

$prods = $db->query("SELECT p.id, SUM(s.amount) AS stock FROM products p JOIN stock s ON s.product_id = p.id JOIN locations l ON l.id = s.location_id
	WHERE l.branch_id = $branch AND IFNULL(l.is_transit, 0) = 0 AND p.sale_price >= 5 AND p.id NOT IN (SELECT product_id FROM quarantine_items WHERE status = 'in_quarantine')
	GROUP BY p.id HAVING SUM(s.amount) >= 10 ORDER BY p.id")->fetchAll(\PDO::FETCH_ASSOC);
$prods = array_values(array_filter($prods, fn($p) => $p['stock'] - Grocy\Services\CustomerOrderService::GetInstance()->Reserved(intval($p['id']), $branch, null) >= 10));
[$A, $B] = [$prods[0], $prods[1]];
$stockOf = fn($pid) => floatval($db->query("SELECT IFNULL(SUM(s.amount), 0) FROM stock s JOIN locations l ON l.id = s.location_id WHERE s.product_id = $pid AND l.branch_id = $branch")->fetchColumn());

// Venta original: 3 A + 1 B con descuento del 10 % (autorizado) y pago mixto efectivo + tarjeta
$sh = $pos->OpenShift($branch, $reg, 500, 'Mañana', $admin);
$items = [['product_id' => $A['id'], 'amount' => 3], ['product_id' => $B['id'], 'amount' => 1]];
$q = $pos->Quote($items, ['type' => 'pct', 'value' => 10]);
$card = round($q['total'] / 3, 2);
$cash = round($q['total'] - $card, 2);
$saleRef = $uuid();
$da = $pos->Authorize('discount', 'sup_ret_test', 'Clave-Sup-2', 'Promoción', ['pct' => $q['discount_pct'], 'branch_id' => $branch], $admin);
$sale = $pos->Checkout(['client_ref' => $saleRef, 'branch_id' => $branch, 'register' => $reg, 'items' => $items, 'expected_total' => $q['total'],
	'discount' => ['type' => 'pct', 'value' => 10, 'reason' => 'Promoción', 'auth_id' => $da['id']], 'payments' => [['method' => 'cash', 'amount' => $cash, 'tendered' => $cash], ['method' => 'card', 'amount' => $card, 'reference' => 'AUT-777']]], $admin);
$sid = $sale['sale']['id'];
echo "Venta {$sale['sale']['number']}: total {$q['total']} (efectivo $cash, tarjeta $card)\n";

echo "\n1. Búsqueda y elegibilidad\n";
ok('Por número de ticket', $ret->Find($sale['sale']['number'], $branch) === $sid);
ok('Por referencia de la transacción del POS', $ret->Find($saleRef, $branch) === $sid);
ok('Por referencia de pago', $ret->Find('AUT-777', $branch) === $sid);
ok('Otra sucursal no la encuentra', $ret->Find($sale['sale']['number'], 2) === null);
$E = $ret->Eligibility($sid);
$LA = array_values(array_filter($E['lines'], fn($l) => $l['product_id'] == $A['id']))[0];
$LB = array_values(array_filter($E['lines'], fn($l) => $l['product_id'] == $B['id']))[0];
ok('Elegible con líneas devolvibles', $E['eligible'] && $LA['returnable'] == 3 && $LB['returnable'] == 1);
ok('Lo pagado por las líneas = total cobrado (descuento e impuesto incluidos)', $near($LA['paid'] + $LB['paid'], $q['total'], 0.011));
ok('Topes por medio de pago = lo cobrado en cada medio', $near($E['refundable_by_method']['cash'], $cash) && $near($E['refundable_by_method']['card'], $card));

echo "\n2. Reembolso parcial en efectivo\n";
$base = ['sale_id' => $sid, 'register' => $reg, 'resolution' => 'refund'];
$line = fn($l, $q, $r = 'no_deseado', $c = 'sin_abrir') => ['sale_item_id' => $l['sale_item_id'], 'quantity' => $q, 'reason' => $r, 'condition' => $c];
$one = round($LA['unit_refund'], 2);
ok('Más unidades que las vendidas rechazado', $throws(fn() => $ret->Process($base + ['return_ref' => $uuid(), 'lines' => [$line($LA, 4)], 'refunds' => [['method' => 'cash', 'amount' => 999]]], $admin), 'solo quedan'));
ok('Sin motivo rechazado', $throws(fn() => $ret->Process($base + ['return_ref' => $uuid(), 'lines' => [['sale_item_id' => $LA['sale_item_id'], 'quantity' => 1]], 'refunds' => [['method' => 'cash', 'amount' => $one]]], $admin), 'motivo'));
ok('Reembolso a un medio no usado rechazado', $throws(fn() => $ret->Process($base + ['return_ref' => $uuid(), 'lines' => [$line($LA, 1)], 'refunds' => [['method' => 'transfer', 'amount' => $one, 'reference' => 'TR-1']]], $admin), 'medio con que se pagó'));
ok('Reembolso distinto del importe devuelto rechazado', $throws(fn() => $ret->Process($base + ['return_ref' => $uuid(), 'lines' => [$line($LA, 1)], 'refunds' => [['method' => 'cash', 'amount' => $one + 5]]], $admin), 'exactamente'));
ok('Tarjeta sin código de reverso rechazada', $throws(fn() => $ret->Process($base + ['return_ref' => $uuid(), 'lines' => [$line($LA, 1)], 'refunds' => [['method' => 'card', 'amount' => min($one, $card)]]], $admin), 'reverso'));
$stA = $stockOf($A['id']);
$expBefore = $pos->ShiftSummary($sh['id'])['expected_cash'];
$rr = $uuid();
$r1 = $ret->Process($base + ['return_ref' => $rr, 'lines' => [$line($LA, 1)], 'refunds' => [['method' => 'cash', 'amount' => $one]]], $admin);
ok('Devolución registrada con comprobante', $r1['return']['code'] !== '' && $near($r1['return']['amount'], $one) && count($r1['lines']) === 1);
ok('El efectivo devuelto sale del arqueo del turno', $near($pos->ShiftSummary($sh['id'])['expected_cash'], $expBefore - $one));
ok('El stock vendible no cambia (no hay reingreso automático)', $near($stockOf($A['id']), $stA, 0.0001));
ok('Solicitud de inspección física creada en /returns', $db->query("SELECT status || ':' || classification FROM customer_returns_exchanges WHERE pos_return_id = {$r1['return']['id']}")->fetchColumn() === 'solicitud:pendiente_inspeccion');
$again = $ret->Process($base + ['return_ref' => $rr, 'lines' => [$line($LA, 1)], 'refunds' => [['method' => 'cash', 'amount' => $one]]], $admin);
ok('Reintento con la misma referencia: misma devolución, sin segundo reembolso', $again['replayed'] && $again['return']['id'] === $r1['return']['id']
	&& intval($db->query("SELECT COUNT(*) FROM daily_pos_refunds WHERE sale_id = $sid")->fetchColumn()) === 1);

echo "\n3. Autorización por importe y rol\n";
$E = $ret->Eligibility($sid);
$LA = array_values(array_filter($E['lines'], fn($l) => $l['product_id'] == $A['id']))[0];
$rest = round($LA['paid'] - $LA['refunded'], 2);
$set = $pos->Settings();
$needs = $rest > $set['return_auth_threshold'];
$payRest = [['method' => 'cash', 'amount' => min($rest, $E['refundable_by_method']['cash'])]];
if ($rest > $E['refundable_by_method']['cash'])
{
	$payRest[] = ['method' => 'card', 'amount' => round($rest - $E['refundable_by_method']['cash'], 2), 'reference' => 'REV-1'];
}
$db->exec("UPDATE daily_system_settings SET setting_value = '1' WHERE setting_key = 'pos_return_auth_threshold'");
ok('Cajero sin permiso: devolución sobre el umbral exige autorización', $throws(fn() => $ret->Process($base + ['return_ref' => $uuid(), 'lines' => [$line($LA, 2, 'defectuoso', 'danado')], 'refunds' => $payRest], $caj), 'autoriza'));
$au = $pos->Authorize('refund', 'sup_ret_test', 'Clave-Sup-2', 'Producto defectuoso', ['amount' => $rest, 'branch_id' => $branch], $caj);
$r2 = $ret->Process($base + ['return_ref' => $uuid(), 'lines' => [$line($LA, 2, 'defectuoso', 'danado')], 'refunds' => $payRest, 'auth_id' => $au['id']], $caj);
ok('Con autorización: devuelve exactamente el saldo pagado de la línea', $near($r2['return']['amount'], $rest) && intval($r2['return']['auth_id']) === $au['id']);
ok('Producto dañado queda en inspección (no se reintegra)', $db->query("SELECT condition_state || ':' || status FROM customer_returns_exchanges WHERE pos_return_id = {$r2['return']['id']}")->fetchColumn() === 'danado:solicitud');
ok('Línea agotada: nueva devolución rechazada (sin duplicar)', $throws(fn() => $ret->Process($base + ['return_ref' => $uuid(), 'lines' => [$line($LA, 1)], 'refunds' => [['method' => 'cash', 'amount' => 1]]], $admin), 'solo quedan'));
$db->exec("UPDATE daily_system_settings SET setting_value = '50' WHERE setting_key = 'pos_return_auth_threshold'");
$E = $ret->Eligibility($sid);
ok('Topes por medio descuentan lo ya reembolsado', $near($E['refundable_by_method']['cash'] + ($E['refundable_by_method']['card'] ?? 0), $q['total'] - $one - $rest, 0.011));

echo "\n4. Nota de crédito y cambio\n";
$LB = array_values(array_filter($E['lines'], fn($l) => $l['product_id'] == $B['id']))[0];
$r3 = $ret->Process(['sale_id' => $sid, 'register' => $reg, 'resolution' => 'exchange', 'return_ref' => $uuid(), 'lines' => [$line($LB, 1, 'equivocado')]], $admin);
$code = $r3['return']['credit_note'];
ok('Cambio emite nota de crédito por lo pagado', $code && $near($r3['return']['credit_balance'], $LB['paid']));
ok('Venta totalmente devuelta: total devuelto = total cobrado', $near(floatval($db->query("SELECT SUM(amount) FROM daily_pos_returns WHERE sale_id = $sid")->fetchColumn()), $q['total'], 0.011));
ok('No queda nada por devolver', $ret->Eligibility($sid)['refundable_total'] < 0.011);
$q2 = $pos->Quote([['product_id' => $A['id'], 'amount' => 1]]);
$use = min($q2['total'], floatval($r3['return']['credit_balance']));
$pays = [['method' => 'credit_note', 'amount' => $use, 'reference' => $code]];
if ($q2['total'] - $use > 0.001) $pays[] = ['method' => 'cash', 'amount' => round($q2['total'] - $use, 2), 'tendered' => round($q2['total'] - $use, 2)];
ok('Nota de crédito inexistente rechazada en el POS', $throws(fn() => $pos->Checkout(['client_ref' => $uuid(), 'branch_id' => $branch, 'register' => $reg, 'items' => [['product_id' => $A['id'], 'amount' => 1]], 'expected_total' => $q2['total'],
	'payments' => [['method' => 'credit_note', 'amount' => $q2['total'], 'reference' => 'NC-NOEXISTE']]], $admin), 'nota de crédito'));
$ex = $pos->Checkout(['client_ref' => $uuid(), 'branch_id' => $branch, 'register' => $reg, 'items' => [['product_id' => $A['id'], 'amount' => 1]], 'expected_total' => $q2['total'], 'payments' => $pays], $admin);
$note = $ret->CreditNote($code);
ok('Canje en el POS descuenta el saldo', $near($note['balance'], floatval($r3['return']['credit_balance']) - $use) && count($note['movements']) === 2);
if ($note['balance'] < 0.005)
{
	ok('Nota agotada: no se vuelve a usar', $throws(fn() => $pos->Checkout(['client_ref' => $uuid(), 'branch_id' => $branch, 'register' => $reg, 'items' => [['product_id' => $A['id'], 'amount' => 1]], 'expected_total' => $q2['total'],
		'payments' => [['method' => 'credit_note', 'amount' => $q2['total'], 'reference' => $code]]], $admin), 'nota de crédito'));
}
else
{
	ok('No se canjea más que el saldo', $throws(fn() => $pos->Checkout(['client_ref' => $uuid(), 'branch_id' => $branch, 'register' => $reg, 'items' => [['product_id' => $A['id'], 'amount' => 3]], 'expected_total' => $pos->Quote([['product_id' => $A['id'], 'amount' => 3]])['total'],
		'payments' => [['method' => 'credit_note', 'amount' => $pos->Quote([['product_id' => $A['id'], 'amount' => 3]])['total'], 'reference' => $code]]], $admin), 'saldo'));
}

echo "\n5. Ventas no elegibles\n";
$pq = $pos->Quote([['product_id' => $B['id'], 'amount' => 1]]);
$s2 = $pos->Checkout(['client_ref' => $uuid(), 'branch_id' => $branch, 'register' => $reg, 'items' => [['product_id' => $B['id'], 'amount' => 1]], 'expected_total' => $pq['total'], 'payments' => [['method' => 'cash', 'amount' => $pq['total']]]], $admin);
$va = $pos->Authorize('void', 'sup_ret_test', 'Clave-Sup-2', 'Error', ['sale_id' => $s2['sale']['id']], $admin);
$pos->Void($s2['sale']['id'], 'Error', $va['id'], $admin);
ok('Venta anulada: no admite devolución', !$ret->Eligibility($s2['sale']['id'])['eligible']);
$s3 = $pos->Checkout(['client_ref' => $uuid(), 'branch_id' => $branch, 'register' => $reg, 'items' => [['product_id' => $B['id'], 'amount' => 1]], 'expected_total' => $pq['total'], 'payments' => [['method' => 'cash', 'amount' => $pq['total']]]], $admin);
$db->exec("UPDATE sales SET created_timestamp = datetime('now', '-60 days') WHERE id = {$s3['sale']['id']}");
$E3 = $ret->Eligibility($s3['sale']['id']);
ok('Fuera de plazo: no elegible', !$E3['eligible'] && $throws(fn() => $ret->Process(['sale_id' => $s3['sale']['id'], 'register' => $reg, 'resolution' => 'credit_note', 'return_ref' => $uuid(), 'lines' => [$line($E3['lines'][0], 1)]], $admin), 'plazo'));
$legacy = intval($db->query("SELECT id FROM sales WHERE status = 'completed' AND payment_method = 'mixed' AND id NOT IN (SELECT sale_id FROM daily_pos_payments) AND created_timestamp >= date('now', '-20 days') LIMIT 1")->fetchColumn());
if ($legacy)
{
	ok('Venta mixta anterior sin desglose: solo nota de crédito', !$ret->Eligibility($legacy)['refundable_by_method']);
}

echo "\n6. Historial, indicadores y ventas\n";
$H = $ret->History($branch, date('Y-m-d'), date('Y-m-d'));
ok('Historial con las devoluciones del día', count(array_filter($H, fn($h) => in_array($h['code'], [$r1['return']['code'], $r2['return']['code'], $r3['return']['code']], true))) === 3);
$K = $ret->Kpis($branch, date('Y-m-d'), date('Y-m-d'));
ok('Tasa de devolución = devuelto ÷ vendido', $K['rate_amount_pct'] !== null && $near($K['rate_amount_pct'], round($K['amount'] / $K['sales_amount'] * 100, 2), 0.01) && $K['pending_inspection'] >= 3);
$hist = Grocy\Services\SalesHistoryAuditService::GetInstance()->Refunds(Grocy\Helpers\DailyFilters::FromQuery(['branch_id' => $branch]), date('Y-m-d'), date('Y-m-d'));
ok('El Historial de ventas descuenta los reembolsos y notas de crédito', $hist['total'] + 0.011 >= $q['total']);
ok('Un solo evento por devolución', intval($db->query("SELECT COUNT(*) FROM daily_domain_events WHERE event_uuid = 'return-$rr'")->fetchColumn()) === 1);

echo "\n$count pruebas, $fails fallas\n";
exit($fails ? 1 : 0);
