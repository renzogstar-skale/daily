<?php
/**
 * Caso demo Yogur natural 500 g (DAI-YOG-500, lote LAC-001, Hallandale Beach): recorrido Compras → Finanzas con los servicios reales,
 * estado antes/después de cada hito, cifras de aceptación, idempotencia (reintento de aprobación), mismas cifras en los
 * tableros (Ventas, HOY/Panel, Centros de costo) y restablecimiento.
 */

use Grocy\Services\DemoRetailCaseService;
use Grocy\Services\WasteService;

require __DIR__ . '/bootstrap.php';

$svc = DemoRetailCaseService::GetInstance();
$eq = fn($a, $b) => abs(floatval($a) - floatval($b)) < 0.006;
$svc->Reset();
$ledgerBefore = intval($val('SELECT COUNT(*) FROM dairy_movement_ledger'));
// Línea base del libro (puede traer un sello roto previo, ajeno al caso): el caso no debe agregar hallazgos
$issues = fn() => json_encode(\Grocy\Services\MovementLedgerService::GetInstance()->Verify(1000)['counts'] ?? []);
$base = $issues();
// Tableros: HOY/Panel (DailyMetricsService), Centros de costo y Ventas leen la misma base; se mide la diferencia que suma el caso
$dm = \Grocy\Services\DailyMetricsService::GetInstance();
$cc = \Grocy\Services\CostCenterService::GetInstance();
$today = date('Y-m-d');
$board = function () use ($dm, $cc, $today) {
	$s = $dm->Sales(1, $today, $today);
	$w = $dm->Waste(1, $today, $today);
	$b = array_column($cc->GetReport(date('Y-m'), 1)['branches'] ?? [], null, 'id')[1] ?? [];
	return ['net' => $s['net_sales'], 'cogs' => $s['cogs'] ?? 0, 'waste' => $w['cost'], 'cc_sales' => $b['sales'] ?? 0, 'cc_waste' => $b['waste'] ?? 0];
};
$b0 = $board();

echo "\n1. Compras y recepción\n";
$svc->Run('po');
$s = $svc->Summary();
ok('Orden por 60 u.', $eq($s['po']['ordered'], 60), json_encode($s['po']));
ok('Antes de recibir: stock 0', $eq($s['stock']['qty'], 0));
$svc->Run('receive');
$s = $svc->Summary();
ok('Recibidas 48, pendientes 12', $eq($s['po']['received'], 48) && $eq($s['po']['pending'], 12), json_encode($s['po']));
ok('Stock 48 en bodega ($96.00)', $eq($s['stock']['store'], 48) && $eq($s['stock']['value'], 96), json_encode($s['stock']));
ok('Lote LAC-001 registrado', $val('SELECT lot_number FROM dairy_stock_lots WHERE stock_id = ' . $db->quote($s['refs']['stock_id'])) === 'LAC-001');

echo "\n2. Traslado interno y POS\n";
$svc->Run('move');
$s = $svc->Summary();
ok('Traslado conserva 48 (12 bodega + 36 góndola)', $eq($s['stock']['qty'], 48) && $eq($s['stock']['store'], 12) && $eq($s['stock']['shelf'], 36), json_encode($s['stock']));
$svc->Run('pos');
$s = $svc->Summary();
ok('POS 26 u. = $91.00', $eq($s['sales']['pos_qty'], 26) && $eq($s['sales']['pos'], 91), json_encode($s['sales']));

echo "\n3. Pedido: reserva, picking y entrega\n";
$svc->Run('reserve');
$s = $svc->Summary();
ok('Reserva reduce el disponible, no el físico', $eq($s['stock']['qty'], 22) && $eq($s['stock']['reserved'], 4) && $eq($s['stock']['available'], 18), json_encode($s['stock']));
ok('Reserva no es venta', $eq($s['sales']['order'], 0));
$svc->Run('pick');
$svc->Run('deliver');
$s = $svc->Summary();
ok('Entrega: venta $14.00 por 4 u. (una sola vez)', $eq($s['sales']['order'], 14) && $eq($s['sales']['order_qty'], 4), json_encode($s['sales']));
ok('Salida física al despachar: 18 u., sin reserva', $eq($s['stock']['qty'], 18) && $eq($s['stock']['reserved'], 0), json_encode($s['stock']));
ok('Pedido entregado', $val('SELECT delivery_status FROM customer_orders WHERE id = ' . intval($s['refs']['order_id'])) === 'delivered');
ok('Cliente ve la compra del pedido', intval($val('SELECT customer_id FROM sales WHERE id = ' . intval($s['refs']['order_sale_id']))) === DemoRetailCaseService::CUSTOMER_ID);

echo "\n4. Calidad, tarea FEFO y merma (estado previo al cierre)\n";
$svc->Run('pre_close');
$s = $svc->Summary();
ok('Tarea FEFO completada y 6 u. bloqueadas', $eq($s['stock']['blocked'], 6) && $val('SELECT status FROM workforce_store_tasks WHERE id = ' . intval($s['refs']['task_id'])) === 'completed', json_encode($s['stock']));
ok('Merma pendiente: impacto $12.00 sin descontar', $eq($s['waste']['pending_cost'], 12) && $eq($s['waste']['cost'], 0) && $eq($s['stock']['qty'], 18), json_encode($s['waste']));
$pri = $svc->Priorities();
ok('HOY: 3–5 prioridades con responsable y acción', count($pri) >= 3 && count($pri) <= 5 && !array_filter($pri, fn($p) => empty($p['who']) || empty($p['href'])), json_encode(array_column($pri, 'what')));
ok('Prioridad de merma por aprobar', in_array(false, array_column(array_filter($pri, fn($p) => $p['key'] === 'waste'), 'done'), true));

echo "\n5. Aprobación (y reintento) + gastos\n";
$svc->Run('all');
WasteService::GetInstance()->ApproveWaste(intval($s['refs']['waste_id']), DemoRetailCaseService::APPROVER_USER_ID);
$svc->Run('approve');
$s = $svc->Summary();
ok('Una sola salida de merma en Kardex', $s['waste']['moves'] === 1, $s['waste']['moves']);
ok('Merma 6 u. = $12.00', $eq($s['waste']['qty'], 6) && $eq($s['waste']['cost'], 12), json_encode($s['waste']));
ok('Bloqueo cerrado y vinculado a la merma', intval($val('SELECT waste_record_id FROM quarantine_items WHERE id = ' . intval($s['refs']['quarantine_id']))) === intval($s['refs']['waste_id']));
ok('Stock final 12 u. en bodega = $24.00', $eq($s['stock']['qty'], 12) && $eq($s['stock']['store'], 12) && $eq($s['stock']['value'], 24), json_encode($s['stock']));
ok('Sin reservas ni bloqueos; disponibles 12', $eq($s['stock']['reserved'], 0) && $eq($s['stock']['blocked'], 0) && $eq($s['stock']['available'], 12));
ok('Orden mantiene 12 pendientes', $eq($s['po']['pending'], 12));
$p = $s['pnl'];
ok('Ventas $105.00 / 30 u.', $eq($p['net'], 105) && $eq($s['sales']['qty'], 30), json_encode($p));
ok('Costo $60.00, utilidad bruta $45.00', $eq($p['cogs'], 60) && $eq($p['gross'], 45));
ok('Merma $12.00, gastos $20.00, resultado $13.00', $eq($p['waste'], 12) && $eq($p['opex'], 20) && $eq($p['result'], 13));
ok('Prioridades actualizadas (merma resuelta)', in_array(true, array_column(array_filter($svc->Priorities(), fn($x) => $x['key'] === 'waste'), 'done'), true));
ok('Alerta FEFO resuelta', !in_array($val('SELECT status FROM dairy_alerts WHERE id = ' . intval($s['refs']['alert_fefo_id'])), \Grocy\Services\AlertCenterService::WORKING, true));
$b1 = $board();
ok('HOY/Panel: la sucursal suma $105.00 de venta y $60.00 de costo', $eq($b1['net'] - $b0['net'], 105) && $eq($b1['cogs'] - $b0['cogs'], 60), json_encode([$b0, $b1]));
ok('HOY/Panel: merma aprobada suma $12.00 una vez', $eq($b1['waste'] - $b0['waste'], 12));
ok('Centros de costo: mismas diferencias', $eq($b1['cc_sales'] - $b0['cc_sales'], 105) && $eq($b1['cc_waste'] - $b0['cc_waste'], 12), json_encode([$b0['cc_sales'], $b1['cc_sales']]));
$again = $svc->Run('all');
ok('Repetir el caso no crea registros', $again['ran'] === [], json_encode($again));
ok('Libro sin hallazgos nuevos tras el caso', $issues() === $base, $issues() . ' vs ' . $base);

echo "\n6. Restablecer\n";
$svc->Reset();
$s = $svc->Summary();
ok('Sin stock, ventas ni mermas del caso', $eq($s['stock']['qty'], 0) && $eq($s['sales']['net'], 0) && $eq($s['waste']['cost'], 0) && $s['po'] === null, json_encode([$s['stock'], $s['sales']]));
ok('Libro vuelve al tamaño inicial', intval($val('SELECT COUNT(*) FROM dairy_movement_ledger')) === $ledgerBefore);
ok('Libro sin hallazgos nuevos tras restablecer y resellar', $issues() === $base, $issues() . ' vs ' . $base);
ok('Producto maestro conservado', $val('SELECT sku FROM products WHERE id = 9900') === 'DAI-YOG-500');

echo "\n$count verificaciones, $fails fallas\n";
exit($fails ? 1 : 0);
