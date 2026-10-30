<?php
/**
 * Etapa 32 — Ingesta de ventas de POS externos: CSV y webhook por el mismo servicio; exactamente una vez por ticket,
 * duplicados, conflictos, devoluciones/anulaciones totales, parcial rechazada, moneda, zona horaria, productos por
 * código/SKU, stock FEFO + kardex, fallo por stock y reintento, recuperación de «processing» huérfano, estado honesto.
 */

use Grocy\Services\PosIngestService;
use Grocy\Services\StockService;

require __DIR__ . '/bootstrap.php';

echo "\nIngesta de ventas de POS externos (Etapa 32)\n";
$pos = PosIngestService::GetInstance();
$stock = StockService::GetInstance();
$uid = GROCY_USER_ID;
$loc = $db->query("SELECT id FROM locations WHERE branch_id = 1 AND active = 1 AND IFNULL(is_transit, 0) = 0 ORDER BY id LIMIT 1")->fetchColumn();
$db->exec("UPDATE locations SET storage_class = NULL, capacity_units = NULL WHERE id = $loc");
$code = $val('SELECT code FROM branches WHERE id = 1') ?: '1';
$qu = intval($val('SELECT id FROM quantity_units ORDER BY id LIMIT 1'));
$db->exec("INSERT INTO products (name, location_id, qu_id_purchase, qu_id_stock, qu_id_consume, qu_id_price, default_best_before_days, sale_price, active, sku) VALUES ('Prueba E32 Yogur', $loc, $qu, $qu, $qu, $qu, 30, 5, 1, 'E32-SKU')");
$pid = intval($db->lastInsertId());
$db->exec("INSERT INTO product_barcodes (product_id, barcode) VALUES ($pid, 'E32BAR$pid')");
$early = date('Y-m-d', strtotime('+5 days'));
$late = date('Y-m-d', strtotime('+20 days'));
$stock->AddProduct($pid, 10, $early, StockService::TRANSACTION_TYPE_PURCHASE, date('Y-m-d'), 2.0, $loc);
$stock->AddProduct($pid, 10, $late, StockService::TRANSACTION_TYPE_PURCHASE, date('Y-m-d'), 3.0, $loc);
$qty = fn() => floatval($val("SELECT IFNULL(SUM(amount), 0) FROM stock WHERE product_id = $pid"));
$y = date('Y-m-d', strtotime('-1 day'));

// 1. CSV: 2 tickets (uno con 2 líneas), alias en español, punto y coma, coma decimal
$csv = "ticket;sucursal;fecha;metodo_pago;moneda;codigo_barras;sku;cantidad;precio_unitario;impuesto\n" .
	"T-1;$code;$y 10:15;card;USD;E32BAR$pid;;2;4,50;0,50\n" .
	"T-1;$code;$y 10:15;card;USD;;E32-SKU;1;5;0,25\n" .
	"T-2;$code;$y 18:40:00;cash;;E32BAR$pid;;3;5;\n";
$parsed = PosIngestService::ParseCsv($csv);
ok('CSV agrupa por ticket: 2 ventas, 2 líneas en T-1', count($parsed) === 2 && count($parsed[0]['items']) === 2);
ok('CSV suma impuesto por ticket (0,75)', abs($parsed[0]['tax_amount'] - 0.75) < 0.001);
$dry = $pos->Ingest('demo-pos', $parsed, 'csv', 'ventas.csv', $uid, true);
ok('prueba en seco valida 2 sin escribir', $dry['valid'] === 2 && $dry['batch_id'] === null && $qty() == 20 && intval($val("SELECT COUNT(*) FROM pos_external_sales")) === 0);
$r = $pos->Ingest('demo-pos', $parsed, 'csv', 'ventas.csv', $uid);
ok('CSV importa 2 ventas', $r['imported'] === 2 && $r['failed'] === 0, json_encode(array_column($r['results'], 'error')));
ok('stock descontado 6 u. (20 → 14)', abs($qty() - 14) < 0.001, $qty());
$s1 = $db->query("SELECT * FROM sales WHERE sale_number = 'POS-DEMO-POS-T-1'")->fetch(PDO::FETCH_ASSOC);
ok('venta T-1: número determinista, hora del POS, total 14, impuesto 0,75', $s1 && $s1['created_timestamp'] === "$y 10:15:00" && abs($s1['total_amount'] - 14) < 0.001 && abs($s1['tax_amount'] - 0.75) < 0.001, json_encode($s1));
ok('venta T-1: costo FEFO (3 u. del lote a $2 = $6)', abs($s1['total_cogs'] - 6) < 0.001, $s1['total_cogs']);
ok('precio del POS bajo la lista (4,50 < 5) aceptado como hecho', intval($val("SELECT COUNT(*) FROM sale_items WHERE sale_id = {$s1['id']} AND unit_price = 4.5")) === 1);
ok('kardex: consumos registrados para la venta', floatval($val("SELECT -SUM(amount) FROM stock_log WHERE product_id = $pid AND transaction_type = 'consume' AND undone = 0")) == 6);
ok('lote registrado con estado imported', intval($val("SELECT imported FROM pos_ingest_batches WHERE id = {$r['batch_id']}")) === 2);

// 2. Duplicados y conflictos
$r = $pos->Ingest('demo-pos', $parsed, 'csv', 'ventas.csv', $uid);
ok('reenvío idéntico: 2 duplicados, stock sin cambio', $r['duplicates'] === 2 && $r['imported'] === 0 && abs($qty() - 14) < 0.001);
$mod = $parsed;
$mod[1]['items'][0]['amount'] = 4;
$r = $pos->Ingest('demo-pos', [$mod[1]], 'webhook', null, $uid);
ok('mismo ticket con otro contenido: conflicto, no se toca', $r['failed'] === 1 && str_contains($r['results'][0]['error'], 'Conflicto') && abs($qty() - 14) < 0.001);
ok('mismo id en otro origen es otra venta', $pos->Ingest('otro-pos', [$parsed[1]], 'webhook', null, $uid)['imported'] === 1 && abs($qty() - 11) < 0.001);

// 3. Webhook JSON con zona horaria explícita y validaciones
$tsUtc = gmdate('Y-m-d\TH:i:s\Z', strtotime("$y 15:00:00"));
$r = $pos->Ingest('demo-pos', [['external_id' => 'W-1', 'branch_id' => 1, 'occurred_at' => $tsUtc, 'items' => [['product_id' => $pid, 'amount' => 1, 'unit_price' => 5]]]], 'webhook', null, $uid);
ok('webhook con hora UTC → hora local del servidor', $r['imported'] === 1 && $val("SELECT created_timestamp FROM sales WHERE sale_number = 'POS-DEMO-POS-W-1'") === "$y 15:00:00");
$bad = fn($s) => $pos->Ingest('demo-pos', [$s], 'webhook', null, $uid)['results'][0]['error'];
$base = ['branch_id' => 1, 'occurred_at' => "$y 12:00", 'items' => [['product_id' => $pid, 'amount' => 1, 'unit_price' => 5]]];
ok('moneda distinta rechazada (sin conversión inventada)', str_contains(strval($bad(['external_id' => 'B-1', 'currency' => 'EUR'] + $base)), 'EUR'));
ok('fecha futura rechazada', str_contains(strval($bad(['external_id' => 'B-2', 'occurred_at' => date('Y-m-d H:i', strtotime('+1 day'))] + $base)), 'futuro'));
ok('fecha ilegible rechazada', str_contains(strval($bad(['external_id' => 'B-3', 'occurred_at' => 'ayer'] + $base)), 'inválida'));
ok('sucursal desconocida rechazada', str_contains(strval($bad(['external_id' => 'B-4', 'branch_id' => 'NOPE'] + $base)), 'Sucursal desconocida'));
ok('producto desconocido rechazado', str_contains(strval($bad(['external_id' => 'B-5', 'items' => [['barcode' => 'NOEXISTE', 'amount' => 1, 'unit_price' => 1]]] + $base)), 'Producto no encontrado'));
ok('cantidad negativa rechazada (devolución va como refund)', str_contains(strval($bad(['external_id' => 'B-6', 'items' => [['product_id' => $pid, 'amount' => -1, 'unit_price' => 5]]] + $base)), 'refund'));
ok('external_id vacío rechazado', str_contains(strval($bad(['external_id' => ''] + $base)), 'external_id'));
ok('origen inválido rechazado', (function () use ($pos, $uid, $base) { try { $pos->Ingest('x', [$base], 'webhook', null, $uid); return false; } catch (\Exception $e) { return true; } })());
ok('fallidos quedan registrados con motivo', intval($val("SELECT COUNT(*) FROM pos_external_sales WHERE status = 'failed' AND error IS NOT NULL")) >= 6);
ok('ningún rechazo tocó el stock', abs($qty() - 10) < 0.001, $qty());

// 4. Stock insuficiente → falla; tras recibir mercancía, reintento importa una sola vez
$r = $pos->Ingest('demo-pos', [['external_id' => 'S-1', 'items' => [['product_id' => $pid, 'amount' => 50, 'unit_price' => 5]]] + $base], 'webhook', null, $uid);
ok('stock insuficiente: falla sin descontar', $r['failed'] === 1 && str_contains($r['results'][0]['error'], 'insuficiente') && abs($qty() - 10) < 0.001);
$stock->AddProduct($pid, 50, $late, StockService::TRANSACTION_TYPE_PURCHASE, date('Y-m-d'), 3.0, $loc);
$xid = intval($val("SELECT id FROM pos_external_sales WHERE external_id = 'S-1'"));
$rr = $pos->Retry($xid, $uid);
ok('reintento tras recepción: importada', $rr['status'] === 'imported' && abs($qty() - 10) < 0.001 && intval($val("SELECT attempts FROM pos_external_sales WHERE id = $xid")) === 2);
ok('reintentar una importada → error', (function () use ($pos, $xid, $uid) { try { $pos->Retry($xid, $uid); return false; } catch (\Exception $e) { return true; } })());

// 5. Devoluciones y anulaciones
$r = $pos->Ingest('demo-pos', [['external_id' => 'R-1', 'kind' => 'refund', 'original_external_id' => 'T-2', 'branch' => $code, 'occurred_at' => "$y 19:00", 'items' => [['product_id' => $pid, 'amount' => 1, 'unit_price' => 5]]]], 'webhook', null, $uid);
ok('devolución parcial rechazada (no soportada, se indica)', $r['failed'] === 1 && str_contains($r['results'][0]['error'], 'parcial'));
$r = $pos->Ingest('demo-pos', [['external_id' => 'R-2', 'kind' => 'refund', 'original_external_id' => 'T-2', 'branch' => $code, 'occurred_at' => "$y 19:00"]], 'webhook', null, $uid);
ok('devolución total: venta anulada y stock reintegrado (+3)', $r['imported'] === 1 && $val("SELECT status FROM sales WHERE sale_number = 'POS-DEMO-POS-T-2'") === 'cancelled' && abs($qty() - 13) < 0.001, $qty());
ok('original marcado refunded', $val("SELECT status FROM pos_external_sales WHERE source = 'demo-pos' AND external_id = 'T-2'") === 'refunded');
$r = $pos->Ingest('demo-pos', [['external_id' => 'R-3', 'kind' => 'void', 'original_external_id' => 'T-2', 'branch' => $code, 'occurred_at' => "$y 19:05"]], 'webhook', null, $uid);
ok('segunda anulación del mismo ticket rechazada, stock igual', $r['failed'] === 1 && abs($qty() - 13) < 0.001);
$r = $pos->Ingest('demo-pos', [['external_id' => 'V-1', 'kind' => 'void', 'original_external_id' => 'NO-LLEGO', 'branch' => $code, 'occurred_at' => "$y 19:05"]], 'webhook', null, $uid);
ok('anulación antes que la venta: pendiente de reintento', $r['failed'] === 1 && str_contains($r['results'][0]['error'], 'aún no importado'));
$r = $pos->Ingest('demo-pos', [
	['external_id' => 'V-2', 'kind' => 'void', 'original_external_id' => 'L-1', 'branch' => $code, 'occurred_at' => "$y 20:05"],
	['external_id' => 'L-1', 'items' => [['sku' => 'E32-SKU', 'amount' => 2, 'unit_price' => 5]]] + $base
], 'webhook', null, $uid);
ok('venta y anulación en el mismo lote (anulación primero): se ordena y ambas entran', $r['imported'] === 2 && abs($qty() - 13) < 0.001, json_encode(array_column($r['results'], 'error')));

// 6. Recuperación de una fila «processing» huérfana (corte tras crear la venta)
$db->exec("UPDATE pos_external_sales SET status = 'processing', updated_timestamp = datetime('now', 'localtime', '-10 minutes') WHERE source = 'demo-pos' AND external_id = 'W-1'");
$w1 = $db->query("SELECT payload_json FROM pos_external_sales WHERE external_id = 'W-1'")->fetchColumn();
$r = $pos->Ingest('demo-pos', [json_decode($w1, true)], 'webhook', null, $uid);
ok('processing huérfano con venta creada: se recupera sin duplicar', $r['imported'] === 1 && !empty($r['results'][0]['recovered']) && intval($val("SELECT COUNT(*) FROM sales WHERE sale_number = 'POS-DEMO-POS-W-1'")) === 1 && abs($qty() - 13) < 0.001);
$db->exec("UPDATE pos_external_sales SET status = 'processing', updated_timestamp = datetime('now', 'localtime') WHERE external_id = 'W-1'");
$r = $pos->Ingest('demo-pos', [json_decode($w1, true)], 'webhook', null, $uid);
ok('processing reciente (envío simultáneo): no se procesa dos veces', $r['failed'] === 1 && str_contains($r['results'][0]['error'], 'en proceso'));
$db->exec("UPDATE pos_external_sales SET status = 'imported' WHERE external_id = 'W-1'");

// 7. Firma y estado
ok('sin secreto configurado: firma no exigida', (function () { PosIngestService::VerifySignature('{}', ''); return true; })());
$st = $pos->Status(null);
ok('estado: extracción no configurada (nunca «conectado» ficticio)', $st['pull_connector']['state'] === 'not_configured');
ok('estado: orígenes con última venta y fallidos', count($st['sources']) === 2 && count($st['failed']) >= 1 && $st['sources'][0]['last_sale_at'] !== null);
ok('estado: zona horaria y moneda informadas', $st['timezone'] !== '' && $st['currency'] !== '');
ok('estado por sucursal ajena: vacío', count($pos->Status([999999])['sources']) === 0);

// 8. Conexión con el pronóstico laboral: las ventas importadas cuentan por hora real del POS
$h = intval($val("SELECT COUNT(*) FROM sales WHERE branch_id = 1 AND status = 'completed' AND notes LIKE 'POS externo%' AND strftime('%H', created_timestamp) IN ('10', '15', '12')"));
ok('ventas importadas en sales con la hora del POS (base del pronóstico)', $h >= 3, $h);
