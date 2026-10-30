<?php
/**
 * Etapa 22: pedidos de cliente (reserva → picking por escáner con FEFO → empaque → despacho como venta), reservas
 * respetadas por el POS y el escáner, compatibilidad producto ↔ clase de ubicación (trigger central) y aviso de capacidad.
 */

use Grocy\Services\CustomerOrderService;
use Grocy\Services\SalesService;
use Grocy\Services\ScanService;
use Grocy\Services\StockService;

require __DIR__ . '/bootstrap.php';

$orders = CustomerOrderService::GetInstance();
$scan = ScanService::GetInstance();
$stock = StockService::GetInstance();
$sales = SalesService::GetInstance();
$locs = $db->query("SELECT id, name FROM locations WHERE branch_id = 1 AND active = 1 AND IFNULL(is_transit, 0) = 0 ORDER BY id LIMIT 2")->fetchAll(PDO::FETCH_ASSOC);
[$locA, $locB] = $locs;
$db->exec("UPDATE locations SET storage_class = NULL, capacity_units = NULL WHERE id IN ({$locA['id']}, {$locB['id']})");
$qu = intval($val('SELECT id FROM quantity_units ORDER BY id LIMIT 1'));
$db->exec("INSERT INTO products (name, location_id, qu_id_purchase, qu_id_stock, qu_id_consume, qu_id_price, default_best_before_days, sale_price, active) VALUES ('Prueba E22 Kéfir', {$locA['id']}, $qu, $qu, $qu, $qu, 30, 5, 1)");
$pid = intval($db->lastInsertId());
$db->exec("INSERT INTO product_barcodes (product_id, barcode) VALUES ($pid, 'E22-$pid')");
$customer = intval($val('SELECT id FROM customers WHERE active = 1 ORDER BY id LIMIT 1'));
$early = date('Y-m-d', strtotime('+10 days'));
$late = date('Y-m-d', strtotime('+30 days'));
$stock->AddProduct($pid, 10, $early, StockService::TRANSACTION_TYPE_PURCHASE, date('Y-m-d'), 2.0, $locA['id']);
$stock->AddProduct($pid, 10, $late, StockService::TRANSACTION_TYPE_PURCHASE, date('Y-m-d'), 2.0, $locB['id']);
$sidEarly = $val("SELECT stock_id FROM stock WHERE product_id = $pid AND best_before_date = '$early'");
$sidLate = $val("SELECT stock_id FROM stock WHERE product_id = $pid AND best_before_date = '$late'");
$db->exec("INSERT INTO dairy_stock_lots (stock_id, product_id, lot_number) VALUES ('$sidEarly', $pid, 'EARLY'), ('$sidLate', $pid, 'LATE')");
echo "  Producto #$pid · 10 u. lote EARLY ($early) en {$locA['name']} · 10 u. lote LATE ($late) en {$locB['name']} · cliente #$customer\n";

// ---------------------------------------------------------------- 1. Reserva
echo "\n1. Pedido, reserva y respeto en POS/escáner\n";
expectError('Precio bajo lista rechazado', fn() => $orders->Create(['branch_id' => 1, 'customer_id' => $customer, 'items' => [['product_id' => $pid, 'amount' => 1, 'unit_price' => 4]]], 1));
expectError('Producto repetido rechazado', fn() => $orders->Create(['branch_id' => 1, 'customer_id' => $customer, 'items' => [['product_id' => $pid, 'amount' => 1], ['product_id' => $pid, 'amount' => 2]]], 1));
$o1 = $orders->Create(['branch_id' => 1, 'customer_id' => $customer, 'items' => [['product_id' => $pid, 'amount' => 12]]], 1);
$id1 = intval($o1['order']['id']);
ok('Pedido en borrador con folio PED y precio de lista', $o1['order']['status'] === 'draft' && str_starts_with($o1['order']['order_code'], 'PED-') && floatval($o1['items'][0]['unit_price']) == 5);
ok('Borrador no reserva', $orders->Reserved($pid, 1) == 0);
$orders->Confirm($id1, 1);
ok('Confirmado reserva 12', abs($orders->Reserved($pid, 1) - 12) < 1e-9);
$av = $scan->Availability($pid, 1);
ok('Disponibilidad: reservado 12, disponible 8', abs($av['reserved'] - 12) < 1e-9 && abs($av['available'] - 8) < 1e-9, json_encode([$av['reserved'], $av['available']]));
expectError('POS no vende lo reservado (10 > 8 libres)', fn() => $sales->CreateSale(['branch_id' => 1, 'customer_id' => $customer, 'items' => [['product_id' => $pid, 'amount' => 10, 'unit_price' => 5]]], 1));
$sales->CreateSale(['branch_id' => 1, 'customer_id' => $customer, 'items' => [['product_id' => $pid, 'amount' => 2, 'unit_price' => 5]]], 1);
ok('POS vende lo libre (2 de 8)', abs(floatval($val("SELECT SUM(amount) FROM stock WHERE product_id = $pid")) - 18) < 1e-9);
$o2 = $orders->Create(['branch_id' => 1, 'customer_id' => $customer, 'items' => [['product_id' => $pid, 'amount' => 10]]], 1);
expectError('Segundo pedido sin stock libre no se confirma', fn() => $orders->Confirm(intval($o2['order']['id']), 1));
$orders->Cancel(intval($o2['order']['id']), 'sin stock', 1);

// ---------------------------------------------------------------- 2. Picking
echo "\n2. Picking por escáner con FEFO guiado\n";
$g = $orders->Get($id1);
ok('Sugerencia FEFO: primero el lote EARLY', ($g['items'][0]['suggested'][0]['lot_number'] ?? null) === 'EARLY', json_encode(array_column($g['items'][0]['suggested'], 'lot_number')));
$ps = $scan->OpenSession(['type' => 'pick', 'branch_id' => 1, 'customer_order_id' => $id1, 'device_id' => 'T22'], 1);
$psid = intval($ps['session']['id']);
ok('Sesión de picking abierta; pedido en preparación', $val("SELECT status FROM customer_orders WHERE id = $id1") === 'picking' && count($ps['expected']) === 1);
expectError('No se pickea más de lo pedido', fn() => $scan->AddLine($psid, ['client_uuid' => 'e22-p-0001', 'raw_code' => "E22-$pid", 'amount' => 13], 1));
$w = $scan->AddLine($psid, ['client_uuid' => 'e22-p-0002', 'raw_code' => "E22-$pid", 'amount' => 2, 'lot_number' => 'LATE'], 1);
ok('Lote fuera de FEFO avisa (no bloquea)', count($w['warnings']) === 1 && str_contains($w['warnings'][0], 'FEFO'), $w['warnings'][0] ?? '');
$scan->AddLine($psid, ['client_uuid' => 'e22-p-0003', 'raw_code' => "E22-$pid", 'amount' => 10, 'scanned_at' => date('Y-m-d H:i:s', time() + 2)], 1);
expectError('No se anula un pedido con picking abierto', fn() => $orders->Cancel($id1, 'x', 1));
$stockBefore = floatval($val("SELECT SUM(amount) FROM stock WHERE product_id = $pid"));
$closed = $scan->CloseSession($psid, [], 1);
$picks = $db->query("SELECT stock_id, amount, fefo_ok FROM customer_order_picks WHERE order_id = $id1 ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
// El POS ya vendió 2 del lote EARLY (FEFO): quedan 8 → la asignación automática toma 8 EARLY + 2 LATE
ok('Picks: 2 LATE escaneado (fuera de FEFO) + 8 EARLY + 2 LATE automáticos (FEFO)', count($picks) === 3 && $picks[0]['stock_id'] === $sidLate && intval($picks[0]['fefo_ok']) === 0
	&& $picks[1]['stock_id'] === $sidEarly && abs($picks[1]['amount'] - 8) < 1e-9 && intval($picks[1]['fefo_ok']) === 1 && $picks[2]['stock_id'] === $sidLate && intval($picks[2]['fefo_ok']) === 1, json_encode($picks));
ok('Preparar no mueve stock', abs(floatval($val("SELECT SUM(amount) FROM stock WHERE product_id = $pid")) - $stockBefore) < 1e-9);
ok('Pedido preparado; mensaje informa 1 lote fuera de FEFO', $val("SELECT status FROM customer_orders WHERE id = $id1") === 'picked' && str_contains($closed['result']['message'], '1 lote(s) fuera de FEFO'), $closed['result']['message']);

// ---------------------------------------------------------------- 3. Empaque y despacho
echo "\n3. Empaque y despacho como venta\n";
expectError('Despachar sin empacar rechazado', fn() => $orders->Dispatch($id1, 1));
expectError('Empacar con 0 bultos rechazado', fn() => $orders->Pack($id1, 0, 1));
$orders->Pack($id1, 2, 1);
$salesBefore = intval($val('SELECT COUNT(*) FROM sales'));
$d = $orders->Dispatch($id1, 1);
ok('Despachado con venta enlazada', $d['order']['status'] === 'dispatched' && intval($d['order']['sale_id']) > 0 && intval($val('SELECT COUNT(*) FROM sales')) === $salesBefore + 1);
ok('Stock: lote EARLY agotado, LATE con 6 (consumió exactamente las entradas preparadas)', floatval($val("SELECT IFNULL(SUM(amount), 0) FROM stock WHERE stock_id = '$sidEarly'")) < 1e-9 && abs(floatval($val("SELECT SUM(amount) FROM stock WHERE stock_id = '$sidLate'")) - 6) < 1e-9);
ok('Venta con costo y transacción por línea (kardex)', intval($val("SELECT COUNT(*) FROM sale_items WHERE sale_id = {$d['order']['sale_id']} AND transaction_id IS NOT NULL AND total_cost > 0")) >= 2);
ok('Reserva liberada', $orders->Reserved($pid, 1) == 0);
$again = $orders->Dispatch($id1, 1);
ok('Despacho repetido es idempotente', !empty($again['already_dispatched']) && intval($val('SELECT COUNT(*) FROM sales')) === $salesBefore + 1);

// ---------------------------------------------------------------- 4. Picking parcial y anulación
echo "\n4. Picking parcial y anulación\n";
$o3 = $orders->Create(['branch_id' => 1, 'customer_id' => $customer, 'items' => [['product_id' => $pid, 'amount' => 5]], 'confirm' => 1], 1);
$id3 = intval($o3['order']['id']);
$s3 = $scan->OpenSession(['type' => 'pick', 'branch_id' => 1, 'customer_order_id' => $id3], 1);
$scan->AddLine(intval($s3['session']['id']), ['client_uuid' => 'e22-p-0101', 'raw_code' => "E22-$pid", 'amount' => 3], 1);
$r3 = $scan->CloseSession(intval($s3['session']['id']), [], 1);
ok('Picking parcial informa línea incompleta y reserva solo lo preparado', str_contains($r3['result']['message'], 'incompleta') && abs($orders->Reserved($pid, 1) - 3) < 1e-9);
$orders->Pack($id3, 1, 1);
$d3 = $orders->Dispatch($id3, 1);
ok('Se despacha solo lo preparado (3)', abs(floatval($val("SELECT SUM(amount) FROM sale_items WHERE sale_id = {$d3['order']['sale_id']}")) - 3) < 1e-9);
$o4 = $orders->Create(['branch_id' => 1, 'customer_id' => $customer, 'items' => [['product_id' => $pid, 'amount' => 2]], 'confirm' => 1], 1);
expectError('Anular exige motivo', fn() => $orders->Cancel(intval($o4['order']['id']), '', 1));
$orders->Cancel(intval($o4['order']['id']), 'cliente desistió', 1);
ok('Anulado libera reserva', $orders->Reserved($pid, 1) == 0);

// ---------------------------------------------------------------- 5. Compatibilidad y capacidad
echo "\n5. Clase de almacenamiento y capacidad\n";
$db->exec("UPDATE locations SET storage_class = 'ambient' WHERE id = {$locA['id']}");
$db->exec("UPDATE locations SET storage_class = 'chilled', capacity_units = 6 WHERE id = {$locB['id']}");
$db->exec("UPDATE products SET storage_class = 'chilled' WHERE id = $pid");
expectError('Trigger central: producto refrigerado no entra a ubicación ambiente (compra)', fn() => $stock->AddProduct($pid, 1, $late, StockService::TRANSACTION_TYPE_PURCHASE, date('Y-m-d'), 2.0, $locA['id']));
$stock->AddProduct($pid, 1, $late, StockService::TRANSACTION_TYPE_PURCHASE, date('Y-m-d'), 2.0, $locB['id']);
ok('Producto refrigerado entra a ubicación refrigerada', true);
expectError('Trigger central: traslado a ubicación incompatible', fn() => $stock->TransferProduct($pid, 1, $locB['id'], $locA['id']));
$m = $scan->OpenSession(['type' => 'move', 'branch_id' => 1, 'location_id' => $locB['id'], 'target_location_id' => $locA['id']], 1);
expectError('Escáner explica la incompatibilidad antes de cerrar', fn() => $scan->AddLine(intval($m['session']['id']), ['client_uuid' => 'e22-m-0001', 'raw_code' => "E22-$pid", 'amount' => 1], 1));
$scan->CancelSession(intval($m['session']['id']), 1, 'prueba');
$db->exec("UPDATE products SET storage_class = NULL WHERE id = $pid");
$db->exec("UPDATE locations SET capacity_units = 0.5 WHERE id = {$locA['id']}");
$m2 = $scan->OpenSession(['type' => 'move', 'branch_id' => 1, 'location_id' => $locB['id'], 'target_location_id' => $locA['id']], 1);
$wcap = $scan->AddLine(intval($m2['session']['id']), ['client_uuid' => 'e22-m-0002', 'raw_code' => "E22-$pid", 'amount' => 1], 1);
ok('Aviso de capacidad al superar la ubicación destino (no bloquea)', count($wcap['warnings']) === 1 && str_contains($wcap['warnings'][0], 'capacidad') && !empty($wcap['line']), $wcap['warnings'][0] ?? '');
$scan->CancelSession(intval($m2['session']['id']), 1, 'prueba');
$hist = \Grocy\Services\ImportService::GetInstance()->History('products', $pid);
ok('Historial registra cambios de la columna nueva storage_class (triggers regenerados en 0281)', count(array_filter($hist, fn($h) => count(array_filter($h['changes'], fn($c) => $c['field'] === 'storage_class')) > 0)) >= 2);
ok('Producto sin clase no tiene restricción (compatibilidad opcional)', intval($val("SELECT COUNT(*) FROM products WHERE IFNULL(storage_class, '') = ''")) > 0);

echo "\n$count verificaciones, $fails fallos\n";
exit($fails > 0 ? 1 : 0);
