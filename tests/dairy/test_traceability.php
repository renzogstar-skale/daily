<?php
/**
 * Etapa 23: trazabilidad de punta a punta de un lote: OC → recepción con lote y temperatura → traslado a otra sucursal
 * (con diferencia en tránsito) → venta a cliente → merma → salida sin documento → vencimiento; conciliación y búsquedas
 * hacia atrás (venta, recepción, traslado) y por GS1.
 */

use Grocy\Services\MrpService;
use Grocy\Services\SalesService;
use Grocy\Services\StockService;
use Grocy\Services\StockTransferService;
use Grocy\Services\TraceabilityService;
use Grocy\Services\WasteService;

require __DIR__ . '/bootstrap.php';

$trace = TraceabilityService::GetInstance();
$stock = StockService::GetInstance();
$locA = intval($val('SELECT id FROM locations WHERE branch_id = 1 AND active = 1 AND IFNULL(is_transit, 0) = 0 ORDER BY id LIMIT 1'));
$branch2 = intval($val('SELECT id FROM branches WHERE id <> 1 ORDER BY id LIMIT 1'));
$db->exec("INSERT INTO locations (name, branch_id) VALUES ('E23 Cámara sucursal 2', $branch2)");
$locB = intval($db->lastInsertId());
$db->exec("UPDATE locations SET storage_class = NULL WHERE id IN ($locA, $locB)");
$qu = intval($val('SELECT id FROM quantity_units ORDER BY id LIMIT 1'));
$db->exec("INSERT INTO products (name, location_id, qu_id_purchase, qu_id_stock, qu_id_consume, qu_id_price, default_best_before_days, sale_price, active) VALUES ('Prueba E23 Queso fresco', $locA, $qu, $qu, $qu, $qu, 20, 6, 1)");
$pid = intval($db->lastInsertId());
$body = '778' . str_pad((string) $pid, 9, '0', STR_PAD_LEFT);
$sum = 0;
for ($i = 0; $i < 12; $i++)
{
	$sum += intval($body[$i]) * ($i % 2 === 0 ? 1 : 3);
}
$ean = $body . ((10 - $sum % 10) % 10);
$db->exec("INSERT INTO product_barcodes (product_id, barcode) VALUES ($pid, '$ean')");
$supplier = intval($val('SELECT id FROM shopping_locations WHERE active = 1 ORDER BY id LIMIT 1'));
$supplierName = $val("SELECT name FROM shopping_locations WHERE id = $supplier");
$customer = intval($val('SELECT id FROM customers WHERE active = 1 ORDER BY id LIMIT 1'));
$customerName = $val("SELECT name FROM customers WHERE id = $customer");
$lot = 'TR23-' . $pid;
echo "  Producto #$pid · lote $lot · sucursal destino #$branch2\n";

// ---------------------------------------------------------------- 1. Entrada
echo "\n1. Origen: OC → recepción con lote del proveedor\n";
$mrp = MrpService::GetInstance();
$order = $mrp->CreateProcurementOrder(['branch_id' => 1, 'supplier_id' => $supplier, 'items' => [['product_id' => $pid, 'amount' => 30, 'unit_price' => 3]]], 1);
$mrp->ApproveProcurementOrder($order['id'], 1);
$itemId = intval($val("SELECT id FROM procurement_order_items WHERE order_id = {$order['id']}"));
$rec = $mrp->ReceiveProcurementOrder($order['id'], ['temperature_on_arrival' => 4.5, 'items' => [['order_item_id' => $itemId, 'delivered_amount' => 30, 'best_before_date' => date('Y-m-d', strtotime('+20 days')), 'location_id' => $locA, 'lot_number' => $lot]]], 1);
$sid = $val("SELECT stock_id FROM dairy_stock_lots WHERE lot_number = '$lot'");
ok('Recepción por /mrp registra el lote del proveedor', !empty($sid), $rec['receipt_code']);

// ---------------------------------------------------------------- 2. Recorrido
echo "\n2. Recorrido: traslado, venta, merma, salida sin documento\n";
$tr = StockTransferService::GetInstance();
$t = $tr->CreateTransfer(['from_location_id' => $locA, 'to_location_id' => $locB, 'items' => [['product_id' => $pid, 'amount' => 10]]], 1);
$tr->ReceiveTransfer($t['id'], ['items' => [['product_id' => $pid, 'received_amount' => 9]], 'notes' => 'llegó 1 menos'], 1);
ok('Traslado con 1 u. de diferencia en tránsito', floatval($val("SELECT SUM(amount) FROM stock WHERE product_id = $pid AND location_id = $locB")) == 9);
$sale = SalesService::GetInstance()->CreateSale(['branch_id' => 1, 'customer_id' => $customer, 'items' => [['product_id' => $pid, 'amount' => 5, 'unit_price' => 6]]], 1);
$saleNumber = is_object($sale) ? $sale->sale_number : $sale['sale_number'];
$reason = intval($val('SELECT id FROM waste_records LIMIT 0') ?: $val("SELECT id FROM waste_reasons WHERE active = 1 AND requires_evidence = 0 ORDER BY id LIMIT 1"));
WasteService::GetInstance()->ReportWaste($pid, 2, $reason, 1, $locA, 'default', 'E23', null, null, $lot, null, null, null, 'e23-w1', 1, true);
$stock->ConsumeProduct($pid, 1, false, StockService::TRANSACTION_TYPE_CONSUME, 'default', null, $locA);

// ---------------------------------------------------------------- 3. Traza hacia adelante
echo "\n3. Traza del lote (hacia adelante)\n";
$r = $trace->Trace($lot);
$g = $r['groups'][0] ?? null;
ok('Un grupo para el lote', count($r['groups']) === 1 && $g['lot_number'] === $lot);
$o = $g['origin'][0] ?? [];
ok('Origen: proveedor, OC, recepción y temperatura', ($o['receipt']['receipt_code'] ?? '') === $rec['receipt_code'] && ($o['receipt']['order_code'] ?? '') === $order['order_code'] && $o['supplier'] === $supplierName && floatval($o['receipt']['temperature_on_arrival']) == 4.5, json_encode($o['receipt'] ?? null));
$s = $g['summary'];
ok('Destinos: recibido 30, vendido 5, merma 3 (2 + 1 en tránsito), sin documento 1', $s['received'] == 30 && $s['sold'] == 5 && $s['wasted'] == 3 && $s['consumed_undocumented'] == 1, json_encode($s));
ok('Saldo 21 y concilia con los movimientos', $s['on_hand'] == 21 && $s['balanced'] === true, $s['on_hand'] . ' / ' . $s['difference']);
ok('Cliente que lo recibió (para retiros)', count($g['customers']) === 1 && $g['customers'][0]['customer_name'] === $customerName && $g['customers'][0]['qty'] == 5 && $g['customers'][0]['sales'][0]['sale_number'] === $saleNumber);
ok('Sucursales alcanzadas incluyen la de destino', count($g['branches']) === 2, implode(', ', $g['branches']));
$docTypes = array_values(array_unique(array_filter(array_map(fn($e) => $e['document']['type'] ?? null, $g['events']))));
ok('Línea de tiempo enlaza recepción, traslado, venta y merma', !array_diff(['receipt', 'transfer', 'sale', 'waste'], $docTypes), implode(',', $docTypes));
ok('Alerta: salida sin documento', count(array_filter($g['alerts'], fn($a) => str_contains($a[1], 'sin documento'))) === 1);
ok('Saldo actual por ubicación (A y B)', count(array_filter($g['current'], fn($c) => floatval($c['amount']) > 0)) === 2);

// ---------------------------------------------------------------- 4. Traza hacia atrás y por GS1
echo "\n4. Traza hacia atrás (venta, recepción, traslado) y por GS1\n";
foreach (['venta' => $saleNumber, 'recepción' => $rec['receipt_code'], 'traslado' => $t['transfer_code'], 'GS1' => "(01)0$ean(10)$lot", 'stock_id' => $sid] as $label => $qstr)
{
	$rb = $trace->Trace($qstr);
	ok("Desde $label llega al lote $lot y su proveedor", ($rb['groups'][0]['lot_number'] ?? null) === $lot && ($rb['groups'][0]['origin'][0]['supplier'] ?? null) === $supplierName, $qstr);
}
ok('Consulta sin coincidencias no inventa', count($trace->Trace('NO-EXISTE-E23')['groups']) === 0);

// ---------------------------------------------------------------- 5. Entrada sin recepción y vencimiento
echo "\n5. Entrada directa sin recepción y lote vencido\n";
$tx = null;
$stock->AddProduct($pid, 4, date('Y-m-d', strtotime('-2 days')), StockService::TRANSACTION_TYPE_PURCHASE, date('Y-m-d'), 3, $locA, null, $tx);
$sid2 = $val("SELECT stock_id FROM stock_log WHERE transaction_id = '$tx'");
$g2 = $trace->Trace($sid2)['groups'][0];
ok('Entrada directa: alerta de compra sin recepción contra OC', count(array_filter($g2['alerts'], fn($a) => str_contains($a[1], 'sin recepción'))) === 1);
ok('Lote vencido con stock: alerta y evento de vencimiento', $g2['expired'] && count(array_filter($g2['alerts'], fn($a) => str_contains($a[1], 'vencido'))) === 1 && count(array_filter($g2['events'], fn($e) => $e['category'] === 'expiry')) === 1);
ok('Lista de lotes del producto incluye ambos', count($trace->ProductLots($pid)) >= 2);

echo "\n$count verificaciones, $fails fallos\n";
exit($fails > 0 ? 1 : 0);
