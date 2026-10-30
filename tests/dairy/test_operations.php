<?php
/**
 * Etapa 21: escáner (GS1, ubicaciones, sesiones de conteo/movimiento/recepción con idempotencia, rebote y series),
 * disponibilidad por estado, política de aprobación de mermas (importe, duplicados), importación CSV con historial,
 * lotes inmovilizados y exportación CSV/Excel.
 */

use Grocy\Services\DairyExportService;
use Grocy\Services\ImportService;
use Grocy\Services\LossPreventionService;
use Grocy\Services\MrpService;
use Grocy\Services\ScanService;
use Grocy\Services\StockService;
use Grocy\Services\WasteService;

require __DIR__ . '/bootstrap.php';

$scan = ScanService::GetInstance();
$stock = StockService::GetInstance();
$locs = $db->query("SELECT id, label_code FROM locations WHERE branch_id = 1 AND active = 1 AND IFNULL(is_transit, 0) = 0 ORDER BY id LIMIT 2")->fetchAll(PDO::FETCH_ASSOC);
if (count($locs) < 2)
{
	ok('dos ubicaciones en sucursal 1', false);
	exit(1);
}
[$locA, $locB] = $locs;
$qu = intval($val('SELECT id FROM quantity_units ORDER BY id LIMIT 1'));
$db->exec("INSERT INTO products (name, location_id, qu_id_purchase, qu_id_stock, qu_id_consume, qu_id_price, default_best_before_days, active) VALUES ('Prueba E21 Yogur', {$locA['id']}, $qu, $qu, $qu, $qu, 20, 1)");
$pid = intval($db->lastInsertId());
// EAN-13 con dígito verificador calculado
$body = '779' . str_pad((string) $pid, 9, '0', STR_PAD_LEFT);
$sum = 0;
for ($i = 0; $i < 12; $i++)
{
	$sum += intval($body[$i]) * ($i % 2 === 0 ? 1 : 3);
}
$ean = $body . ((10 - $sum % 10) % 10);
$db->exec("INSERT INTO product_barcodes (product_id, barcode) VALUES ($pid, '$ean')");
$gtin = '0' . $ean;
echo "  Producto #$pid EAN $ean · ubicaciones {$locA['label_code']} / {$locB['label_code']}\n";

// ---------------------------------------------------------------- 1. Interpretación
echo "\n1. Códigos GS1, EAN y ubicaciones\n";
$p = $scan->ParseCode("(01)$gtin(17)261200(10)L-77(21)SN1");
ok('GS1 con paréntesis: GTIN, lote, serie', $p['gtin'] === $gtin && $p['lot'] === 'L-77' && $p['serial'] === 'SN1');
ok('AI 17 con día 00 = fin de mes', $p['best_before'] === '2026-12-31', $p['best_before']);
$p = $scan->ParseCode("]C101{$gtin}17270115" . "10LOTE9\x1D3103001250");
ok('GS1 crudo con FNC1 (]C1, GS) y peso 3103', $p['lot'] === 'LOTE9' && $p['best_before'] === '2027-01-15' && abs($p['ais']['3103'] - 1.25) < 1e-9, json_encode($p['ais']));
expectError('GTIN con dígito verificador inválido', fn() => $scan->ParseCode('(01)' . substr($gtin, 0, 13) . (($gtin[13] + 1) % 10)));
ok('EAN-13 plano resuelve producto', $scan->FindProductByCode($ean)['id'] == $pid);
ok('GTIN-14 con cero inicial resuelve el mismo producto', $scan->FindProductByCode($gtin)['id'] == $pid);
$r = $scan->Resolve(strtolower($locA['label_code']), 1);
ok('Etiqueta de ubicación resuelve ubicación', $r['kind'] === 'location' && $r['location']['id'] == $locA['id']);
ok('Código desconocido no inventa producto', $scan->Resolve('ZZ-NO-EXISTE', 1)['kind'] === 'unknown');

// ---------------------------------------------------------------- 2. Conteo guiado
echo "\n2. Conteo guiado (ciego, idempotente, rebote, series)\n";
$stock->AddProduct($pid, 10, date('Y-m-d', strtotime('+20 days')), StockService::TRANSACTION_TYPE_PURCHASE, date('Y-m-d'), 2.0, $locA['id']);
$s = $scan->OpenSession(['type' => 'count', 'branch_id' => 1, 'location_id' => $locA['id'], 'device_id' => 'TEST-DEV'], 1);
$sid = intval($s['session']['id']);
ok('Sesión de conteo abierta con folio SCN', str_starts_with($s['session']['folio'], 'SCN-'));
$exp = array_values(array_filter($s['expected'], fn($e) => $e['product_id'] == $pid));
ok('Conteo ciego: no revela stock del sistema', count($exp) === 1 && $exp[0]['system_amount'] === null);
$t0 = date('Y-m-d H:i:s');
$scan->AddLine($sid, ['client_uuid' => 'e21-c-0001', 'raw_code' => $ean, 'amount' => 5, 'device_id' => 'TEST-DEV', 'scanned_at' => $t0], 1);
$retry = $scan->AddLine($sid, ['client_uuid' => 'e21-c-0001', 'raw_code' => $ean, 'amount' => 5, 'device_id' => 'TEST-DEV', 'scanned_at' => $t0], 1);
ok('Reintento con el mismo client_uuid no duplica', !empty($retry['duplicate']) && intval($val("SELECT COUNT(*) FROM dairy_scan_lines WHERE session_id = $sid")) === 1);
$b = $scan->AddLine($sid, ['client_uuid' => 'e21-c-0002', 'raw_code' => $ean, 'amount' => 5, 'device_id' => 'TEST-DEV', 'scanned_at' => $t0], 1);
ok('Rebote del gatillo (misma lectura < 400 ms) ignorado', !empty($b['ignored']));
$c = $scan->AddLine($sid, ['client_uuid' => 'e21-c-0002b', 'raw_code' => $ean, 'amount' => 1, 'device_id' => 'TEST-DEV', 'scanned_at' => date('Y-m-d H:i:s', strtotime($t0) + 1)], 1);
ok('Mismo EAN 1 s después sí suma (escaneo por unidad)', empty($c['ignored']) && !empty($c['line']));
$scan->AddLine($sid, ['client_uuid' => 'e21-c-0003', 'raw_code' => $ean, 'amount' => 3, 'device_id' => 'TEST-DEV', 'scanned_at' => date('Y-m-d H:i:s', strtotime($t0) + 5)], 1);
$scan->AddLine($sid, ['client_uuid' => 'e21-c-0004', 'raw_code' => "(01)$gtin(21)SER-1", 'device_id' => 'TEST-DEV'], 1);
expectError('Serie repetida en la sesión', fn() => $scan->AddLine($sid, ['client_uuid' => 'e21-c-0005', 'raw_code' => "(01)$gtin(21)SER-1", 'device_id' => 'OTRO'], 1));
expectError('Cantidad 0 rechazada', fn() => $scan->AddLine($sid, ['client_uuid' => 'e21-c-0006', 'raw_code' => $ean, 'amount' => 0], 1));
expectError('Etiqueta de ubicación como producto rechazada', fn() => $scan->AddLine($sid, ['client_uuid' => 'e21-c-0007', 'raw_code' => $locB['label_code']], 1));
$line = $scan->AddLine($sid, ['client_uuid' => 'e21-c-0008', 'raw_code' => $ean, 'amount' => 2, 'device_id' => 'X'], 1)['line'];
$scan->VoidLine(intval($line['id']), 1);
$sess = $scan->GetSession($sid);
$tot = array_values(array_filter($sess['totals'], fn($t) => $t['product_id'] == $pid))[0];
ok('Total contado = 5 + 1 + 3 + 1 (anulada no suma)', abs($tot['amount'] - 10) < 1e-9, $tot['amount']);
$before = floatval($val("SELECT SUM(amount) FROM stock WHERE product_id = $pid"));
$closed = $scan->CloseSession($sid, [], 1);
$cnt = array_values(array_filter($closed['result']['counts'], fn($c) => $c['product_id'] ?? null) ?: $closed['result']['counts']);
$row = $db->query("SELECT * FROM stock_cycle_counts WHERE product_id = $pid ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
ok('Cierre crea conteo sin diferencia (10 = 10)', $row && abs(floatval($row['difference_amount'])) < 1e-9 && $row['status'] !== 'pending', json_encode($row));
ok('El stock no cambia hasta aprobar el ajuste', abs(floatval($val("SELECT SUM(amount) FROM stock WHERE product_id = $pid")) - $before) < 1e-9);
ok('Cierre repetido es idempotente', !empty($scan->CloseSession($sid, [], 1)['already_closed']) && intval($val("SELECT COUNT(*) FROM stock_cycle_counts WHERE product_id = $pid")) === 1);
expectError('No se agregan lecturas a una sesión cerrada', fn() => $scan->AddLine($sid, ['client_uuid' => 'e21-c-0009', 'raw_code' => $ean], 1));

// ---------------------------------------------------------------- 3. Movimiento
echo "\n3. Movimiento entre ubicaciones\n";
$m = $scan->OpenSession(['type' => 'move', 'branch_id' => 1, 'location_id' => $locA['id'], 'target_location_id' => $locB['id']], 1);
$mid = intval($m['session']['id']);
expectError('No mueve más de lo que hay en el origen', fn() => $scan->AddLine($mid, ['client_uuid' => 'e21-m-0001', 'raw_code' => $ean, 'amount' => 11], 1));
$scan->AddLine($mid, ['client_uuid' => 'e21-m-0002', 'raw_code' => $ean, 'amount' => 4], 1);
$scan->CloseSession($mid, [], 1);
ok('4 u. pasan de A a B', abs(floatval($val("SELECT IFNULL(SUM(amount),0) FROM stock WHERE product_id = $pid AND location_id = {$locB['id']}")) - 4) < 1e-9);
ok('Movimiento en stock_log (transfer_to)', intval($val("SELECT COUNT(*) FROM stock_log WHERE product_id = $pid AND transaction_type = 'transfer_to' AND undone = 0")) >= 1);
expectError('Origen = destino rechazado', fn() => $scan->OpenSession(['type' => 'move', 'branch_id' => 1, 'location_id' => $locA['id'], 'target_location_id' => $locA['id']], 1));

// ---------------------------------------------------------------- 4. Recepción contra OC
echo "\n4. Recepción por escáner contra OC con lote y vencimiento\n";
$supplier = intval($val('SELECT id FROM shopping_locations WHERE active = 1 ORDER BY id LIMIT 1'));
$mrp = MrpService::GetInstance();
$order = $mrp->CreateProcurementOrder(['branch_id' => 1, 'supplier_id' => $supplier, 'items' => [['product_id' => $pid, 'amount' => 12, 'unit_price' => 2.2]]], 1);
$mrp->ApproveProcurementOrder($order['id'], 1);
$rcv = $scan->OpenSession(['type' => 'receive', 'branch_id' => 1, 'order_id' => $order['id'], 'location_id' => $locA['id']], 1);
$rid = intval($rcv['session']['id']);
$bbd = date('ymd', strtotime('+18 days'));
$scan->AddLine($rid, ['client_uuid' => 'e21-r-0001', 'raw_code' => "(01)$gtin(17)$bbd(10)LOT-E21", 'amount' => 10], 1);
expectError('No recibe más de lo pendiente en la OC', fn() => $scan->AddLine($rid, ['client_uuid' => 'e21-r-0002', 'raw_code' => $ean, 'amount' => 3], 1));
$stockBefore = floatval($val("SELECT SUM(amount) FROM stock WHERE product_id = $pid"));
$res = $scan->CloseSession($rid, ['temperature_on_arrival' => 4], 1);
ok('Recepción REC creada y stock +10', str_starts_with($res['result']['receipts'][0]['receipt_code'] ?? '', 'REC-') && abs(floatval($val("SELECT SUM(amount) FROM stock WHERE product_id = $pid")) - $stockBefore - 10) < 1e-9, $res['result']['message'] ?? '');
ok('Lote asociado al stock_id recibido', intval($val("SELECT COUNT(*) FROM dairy_stock_lots WHERE product_id = $pid AND lot_number = 'LOT-E21'")) >= 1);
$byLot = $scan->Resolve('LOT-E21', 1);
ok('Búsqueda por lote encuentra producto y entrada', $byLot['kind'] === 'product' && $byLot['product']['id'] == $pid && count(array_filter($byLot['entries'], fn($e) => !empty($e['matches_lot']))) >= 1);
ok('Vencimiento GS1 llega al stock', intval($val("SELECT COUNT(*) FROM stock WHERE product_id = $pid AND best_before_date = '" . date('Y-m-d', strtotime('+18 days')) . "'")) >= 1);

// ---------------------------------------------------------------- 5. Disponibilidad y política de merma
echo "\n5. Disponibilidad y política de aprobación de mermas\n";
$reason = $db->query("SELECT * FROM waste_reasons WHERE active = 1 AND requires_evidence = 0 AND requires_approval = 0 AND lower(IFNULL(risk_level, '')) NOT IN ('alto', 'crítico', 'critico') ORDER BY id LIMIT 1")->fetch(PDO::FETCH_OBJ);
$ws = WasteService::GetInstance();
if ($reason)
{
	$small = $ws->ReportWaste($pid, 1, intval($reason->id), 1, intval($locA['id']), 'default', 'E21 menor', null, null, null, null, null, null, 'e21-w-1', 1);
	ok('Merma bajo el umbral con causal sin aprobación se contabiliza', $small->status === 'approved' && intval($small->inventory_deducted) === 1);
	$db->exec("UPDATE stock SET price = 30 WHERE product_id = $pid");
	$big = $ws->ReportWaste($pid, 2, intval($reason->id), 1, intval($locA['id']), 'default', 'E21 mayor', null, null, null, null, null, null, 'e21-w-2', 1);
	ok('Impacto ≥ WASTE_APPROVAL_MIN_COST exige aprobación aunque la causal no', $big->status === 'pending_approval' && intval($big->inventory_deducted) === 0, '$' . $big->total_cost);
	$av = $scan->Availability($pid, 1);
	ok('Merma pendiente = comprometido; disponible lo descuenta', abs($av['committed_waste'] - 2) < 1e-9 && abs($av['available'] - ($av['physical'] - $av['quarantine'] - $av['expired'] - 2)) < 1e-9 && $av['reserved'] == 0);
	expectError('Duplicado (mismo producto/cantidad/causal en 10 min) exige confirmación', fn() => $ws->ReportWaste($pid, 2, intval($reason->id), 1, intval($locA['id']), 'default', 'E21 dup', null, null, null, null, null, null, 'e21-w-3', 1));
	$conf = $ws->ReportWaste($pid, 2, intval($reason->id), 1, intval($locA['id']), 'default', 'E21 dup confirmado', null, null, null, null, null, null, 'e21-w-4', 1, false, true);
	ok('Con confirmación explícita se registra', intval($conf->id) > 0);
	$again = $ws->ReportWaste($pid, 2, intval($reason->id), 1, intval($locA['id']), 'default', 'x', null, null, null, null, null, null, 'e21-w-4', 1);
	ok('Misma clave de idempotencia devuelve el mismo caso', intval($again->id) === intval($conf->id));
}
else
{
	ok('causal sin aprobación/evidencia disponible', false);
}

// ---------------------------------------------------------------- 6. Importación y historial
echo "\n6. Importación CSV con validación, duplicados e historial\n";
$imp = ImportService::GetInstance();
$unit = $val('SELECT name FROM quantity_units ORDER BY id LIMIT 1');
$locName = $val("SELECT name FROM locations WHERE id = {$locA['id']}");
$csv = "nombre;codigo_barras;unidad;ubicacion;stock_minimo;vida_util_dias\n"
	. "E21 Queso Import;;$unit;$locName;5;30\n"
	. "E21 Queso Import;;$unit;$locName;5;30\n"
	. ";;$unit;$locName;1;1\n"
	. "E21 Leche Import;$ean;$unit;$locName;-2;x\n";
$dry = $imp->Run('products', $csv, true, 'create', 't.csv', 1);
$errs = array_map(fn($r) => implode(' | ', $r['errors']), $dry['results']);
ok('Validación: duplicado en archivo, nombre vacío, código ajeno, números inválidos', $dry['rejected'] === 3 && str_contains($errs[1], 'Duplicado') && str_contains($errs[2], 'Nombre') && str_contains($errs[3], 'otro producto') && str_contains($errs[3], 'Stock mínimo'), implode(' // ', $errs));
expectError('Confirmar con errores no guarda nada', fn() => $imp->Run('products', $csv, false, 'create', 't.csv', 1));
ok('Nada se guardó', intval($val("SELECT COUNT(*) FROM products WHERE name LIKE 'E21 % Import'")) === 0);
$okCsv = "nombre,unidad,ubicacion,stock_minimo,vida_util_dias\nE21 Queso Import,$unit,$locName,5,30\n";
$done = $imp->Run('products', $okCsv, false, 'create', 'ok.csv', 1);
$newId = intval($val("SELECT id FROM products WHERE name = 'E21 Queso Import'"));
ok('Importación crea el producto y registra el trabajo', $done['to_create'] === 1 && $newId > 0 && intval($val("SELECT COUNT(*) FROM dairy_import_jobs WHERE file_name = 'ok.csv'")) === 1);
$imp->Run('products', "nombre,stock_minimo\nE21 Queso Import,8\n", false, 'upsert', 'up.csv', 1);
$hist = $imp->History('products', $newId);
$minChange = array_values(array_filter($hist[0]['changes'] ?? [], fn($c) => $c['field'] === 'min_stock_amount'));
ok('Historial: alta + cambio 5 → 8 con usuario', count($hist) === 2 && $hist[1]['action'] === 'insert' && count($minChange) === 1 && floatval($minChange[0]['to']) == 8 && $hist[0]['user'] !== null, json_encode($minChange));
expectError('Modo crear rechaza existente', fn() => $imp->Run('products', "nombre,stock_minimo\nE21 Queso Import,9\n", false, 'create', 'x.csv', 1));
$cust = $imp->Run('customers', "nombre,documento,correo\nCliente E21,900123456,no-es-correo\n", true, 'create', null, 1);
ok('Cliente con correo inválido rechazado', $cust['rejected'] === 1);
$locImp = $imp->Run('locations', "nombre,sucursal,almacen,zona,pasillo,estante,posicion,clase_almacenamiento,capacidad\nE21 Rack A1,1,Bodega,Frío,A,1,01,refrigerado,40\n", true, 'create', null, 1);
ok('Sucursal inexistente rechazada (se usa nombre o código)', $locImp['rejected'] === 1, json_encode($locImp['results'][0]['errors']));
$branchName = $val('SELECT name FROM branches WHERE id = 1');
$locDone = $imp->Run('locations', "nombre,sucursal,almacen,zona,pasillo,estante,posicion,clase_almacenamiento,capacidad\nE21 Rack A1,$branchName,Bodega,Frío,A,1,01,refrigerado,40\n", false, 'create', null, 1);
$newLoc = $db->query("SELECT * FROM locations WHERE name = 'E21 Rack A1'")->fetch(PDO::FETCH_ASSOC);
ok('Ubicación importada con etiqueta LOC- y clase', $newLoc && str_starts_with($newLoc['label_code'], 'LOC-') && $newLoc['storage_class'] === 'chilled' && $newLoc['aisle'] === 'A');
ok('Ubicación nueva resoluble por etiqueta', $scan->Resolve($newLoc['label_code'], 1)['location']['path'][1] === 'Bodega');
$db->exec("INSERT INTO locations (name, branch_id, label_code) VALUES ('E21 Sin etiqueta', 1, '')");
$auto = $db->query("SELECT id, label_code FROM locations WHERE name = 'E21 Sin etiqueta'")->fetch(PDO::FETCH_ASSOC);
ok('Ubicación creada sin etiqueta recibe LOC-<id> (formulario envía vacío)', $auto['label_code'] === sprintf('LOC-%05d', $auto['id']), $auto['label_code']);
$db->exec("UPDATE locations SET label_code = 'loc-e21-x' WHERE id = {$auto['id']}");
ok('Etiqueta editada se normaliza a mayúsculas', $val("SELECT label_code FROM locations WHERE id = {$auto['id']}") === 'LOC-E21-X');
expectError('Etiqueta duplicada rechazada', fn() => $db->exec("UPDATE locations SET label_code = 'LOC-E21-X' WHERE id = {$locA['id']}"));

// ---------------------------------------------------------------- 7. Lotes inmovilizados y exportación
echo "\n7. Lotes inmovilizados y exportación\n";
$stock->AddProduct($pid, 6, date('Y-m-d', strtotime('+90 days')), StockService::TRANSACTION_TYPE_PURCHASE, date('Y-m-d', strtotime('-45 days')), 1.5, $locB['id']);
$st = LossPreventionService::GetInstance()->StagnantLots(1);
$mine = array_values(array_filter($st['lots'], fn($l) => $l['product_id'] == $pid && $l['location_id'] == $locB['id'] && $l['purchased_date'] <= date('Y-m-d', strtotime('-30 days'))));
ok('Lote comprado hace 45 d sin salidas en la ubicación B aparece con recomendación', count($mine) === 1 && !empty($mine[0]['recommendation']), $mine[0]['recommendation'] ?? '');
$ex = DairyExportService::GetInstance();
$xls = $ex->Render(['a' => 'Producto', 'b' => 'Costo'], [['a' => 'Leche <1L> & "x"', 'b' => 2.5], ['a' => '=HYPERLINK("x")', 'b' => '007']], 'xls', 'Mermas');
ok('Excel: XML válido con número y texto escapado', $xls['ext'] === 'xls' && simplexml_load_string($xls['body']) !== false && str_contains($xls['body'], 'ss:Type="Number">2.5') && str_contains($xls['body'], 'ss:Type="String">007'));
$csvOut = $ex->Render(['a' => 'Producto'], [['a' => '=1+1']], 'csv')['body'];
ok('CSV con BOM y fórmula neutralizada', str_starts_with($csvOut, "\xEF\xBB\xBF") && str_contains($csvOut, "'=1+1"));

echo "\n$count verificaciones, $fails fallos\n";
exit($fails > 0 ? 1 : 0);
