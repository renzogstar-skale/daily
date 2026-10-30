<?php
/**
 * Libro de movimientos inalterable (migración 0276) y detección de movimientos anómalos.
 */

use Grocy\Services\MovementLedgerService;
use Grocy\Services\StockService;

require __DIR__ . '/bootstrap.php';

$ml = MovementLedgerService::GetInstance();
$stock = StockService::GetInstance();
$p = $db->query('SELECT p.id, p.location_id FROM products p JOIN locations l ON l.id = p.location_id WHERE p.active = 1 AND l.branch_id = 1 ORDER BY p.id LIMIT 1')->fetch(PDO::FETCH_OBJ);

echo "\n1. Captura y sello\n";
$v0 = $ml->Verify();
ok('línea base: libro = historial, cadena íntegra', $v0['ok'] && $v0['chain_ok'] && $v0['sealed'] === $v0['movements'], json_encode($v0['counts']));
$tx = $stock->AddProduct($p->id, 10, date('Y-m-d', strtotime('+30 days')), StockService::TRANSACTION_TYPE_PURCHASE, date('Y-m-d'), 2.5, $p->location_id);
$logId = intval($val("SELECT MAX(id) FROM stock_log WHERE transaction_id = '$tx'"));
ok('movimiento nuevo entra al libro por trigger', intval($val("SELECT COUNT(*) FROM dairy_movement_ledger WHERE stock_log_id = $logId")) === 1);
ok('se sella al verificar', $ml->Verify()['ok'] && $val("SELECT hash FROM dairy_movement_ledger WHERE stock_log_id = $logId") !== null);

echo "\n2. Inalterable\n";
expectError('no se puede borrar del libro', fn() => $db->exec("DELETE FROM dairy_movement_ledger WHERE stock_log_id = $logId"));
expectError('no se puede cambiar la cantidad', fn() => $db->exec("UPDATE dairy_movement_ledger SET amount = 1 WHERE stock_log_id = $logId"));
expectError('no se puede re-sellar', fn() => $db->exec("UPDATE dairy_movement_ledger SET hash = 'x' WHERE stock_log_id = $logId"));

echo "\n3. Detección de alteraciones\n";
$db->exec("UPDATE stock_log SET amount = 3 WHERE id = $logId");
$v = $ml->Verify();
ok('cambio de cantidad en stock_log → "modificado"', ($v['counts']['modified'] ?? 0) === 1 && str_contains($v['issues'][0]['detail'], 'cantidad 10 → 3'), $v['issues'][0]['detail'] ?? '');
$db->exec("UPDATE stock_log SET amount = 10 WHERE id = $logId");
$db->exec("DELETE FROM stock_log WHERE id = $logId");
ok('borrado en stock_log → "borrado"', ($ml->Verify()['counts']['deleted'] ?? 0) === 1);
$db->exec('DROP TRIGGER dairy_movement_ledger_no_update');
$db->exec("UPDATE dairy_movement_ledger SET amount = 999 WHERE stock_log_id = (SELECT MIN(stock_log_id) FROM dairy_movement_ledger)");
$v = $ml->Verify();
ok('edición directa del libro (sin triggers) → cadena rota', !$v['chain_ok'] && ($v['counts']['chain'] ?? 0) === 1);
$db->exec('DROP TRIGGER dairy_movement_ledger_capture');
$tx2 = $stock->AddProduct($p->id, 1, date('Y-m-d', strtotime('+30 days')), StockService::TRANSACTION_TYPE_PURCHASE, date('Y-m-d'), 2.5, $p->location_id);
ok('movimiento sin pasar por el libro → "no registrado"', ($ml->Verify()['counts']['unrecorded'] ?? 0) >= 1);

echo "\n4. Movimientos anómalos\n";
$t = date('Y-m-d') . ' 03:15:00';
$ins = $db->prepare("INSERT INTO stock_log (product_id, amount, best_before_date, purchased_date, stock_id, transaction_type, price, undone, location_id, transaction_id, user_id, row_created_timestamp, spoiled) VALUES (:p, :a, '2999-12-31', date('now'), :s, :t, :pr, :u, :l, :tx, 1, :ts, :sp)");
$mk = fn($a, $type, $price, $ts, $sp = 0, $u = 0) => $ins->execute([':p' => $p->id, ':a' => $a, ':s' => uniqid(), ':t' => $type, ':pr' => $price, ':u' => $u, ':l' => $p->location_id, ':tx' => uniqid('tx'), ':ts' => $ts, ':sp' => $sp]);
$mk(-4, 'consume', 2.5, date('Y-m-d') . ' 10:00:00');
$mk(-4, 'consume', 2.5, date('Y-m-d') . ' 10:01:00');
$mk(-500, 'inventory-correction', 2.5, date('Y-m-d') . ' 11:00:00');
$mk(-2, 'consume', 2.5, date('Y-m-d') . ' 12:00:00', 1);
$mk(1, 'purchase', 2.5, $t);
for ($i = 0; $i < GROCY_INVENTORY_ANOMALY_UNDO_COUNT; $i++)
{
	$mk(-1, 'consume', 2.5, date('Y-m-d') . ' 13:0' . $i . ':00', 0, 1);
}
$a = $ml->Anomalies(1, 30);
$types = $a['counts'];
ok('duplicado probable (misma cantidad en 1 min)', ($types['duplicate'] ?? 0) >= 1);
ok('corrección de alto valor ($1250)', ($types['large_correction'] ?? 0) >= 1 && $a['items'][0]['type'] === 'large_correction');
ok('merma sin caso', ($types['waste_without_case'] ?? 0) >= 1);
ok('fuera de horario (03:15)', ($types['off_hours'] ?? 0) >= 1);
ok('anulaciones repetidas por usuario', ($types['undo_heavy'] ?? 0) >= 1);
ok('otra sucursal no ve estos movimientos', ($ml->Anomalies(999, 30)['count']) === 0);

echo "\n$count verificaciones, $fails fallidas\n";
exit($fails > 0 ? 1 : 0);
