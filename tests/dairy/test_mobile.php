<?php
/**
 * DAIRY Mobile (manager, /m): clasificación de pérdidas sin doble conteo, drill-down coherente
 * (departamento ⊂ total, producto ⊂ departamento), bandeja de prioridades, conteo ciego y búsqueda.
 */

use Grocy\Controllers\Api\MobileApiController;
use Grocy\Services\MobileManagerService;

require __DIR__ . '/bootstrap.php';

$m = MobileManagerService::GetInstance();
$from = date('Y-01-01');
$to = date('Y-m-d');
$sum = fn(array $rows, string $k) => round(array_sum(array_map(fn($r) => floatval($r[$k]), $rows)), 2);

echo "\n1. Períodos\n";
[$f, $t] = MobileApiController::Period(['period' => '7d']);
ok('7 días = hoy − 6', $f === date('Y-m-d', strtotime('-6 days')) && $t === date('Y-m-d'));
[$f, $t] = MobileApiController::Period(['period' => 'custom', 'from' => '2026-02-10', 'to' => '2026-02-01']);
ok('rango invertido → mes en curso', $f === date('Y-m-01'));
ok('período anterior de igual duración', MobileManagerService::PreviousPeriod('2026-03-01', '2026-03-10') === ['2026-02-19', '2026-02-28']);

echo "\n2. Ventas: drill-down coherente\n";
$s = $m->Sales(null, $from, $to);
$byGroup = $sum($s['by_group'], 'total');
// Ventas se muestran sin IVA: líneas prorrateadas con SalesService::SQL_ITEM_NET_SALES
$lines = round(floatval($db->query("SELECT IFNULL(SUM(" . \Grocy\Services\SalesService::SQL_ITEM_NET_SALES . "), 0) FROM sale_items si JOIN sales s ON s.id = si.sale_id WHERE s.status = 'completed' AND date(s.created_timestamp) BETWEEN '$from' AND '$to'")->fetchColumn()), 2);
ok('Σ departamentos = Σ líneas vendidas (sin IVA)', abs($byGroup - $lines) < 0.01, "$byGroup vs $lines");
ok('total mostrado = neta (cobrado − impuestos)', abs($s['totals']['total'] - $s['totals']['net']) < 0.01 && abs($s['totals']['net'] - ($s['totals']['total_with_tax'] - $s['totals']['tax'])) < 0.01);
ok('devoluciones = ventas anuladas', $s['totals']['returns_count'] == intval($db->query("SELECT COUNT(*) FROM sales WHERE status = 'cancelled' AND date(created_timestamp) BETWEEN '$from' AND '$to'")->fetchColumn()));
ok('serie diaria cubre el período', count($s['daily']) === intval((strtotime($to) - strtotime($from)) / 86400) + 1);
$g = $s['by_group'][0];
$sg = $m->Sales(null, $from, $to, intval($g['id']));
ok('total del departamento = su fila en el general', abs($sg['totals']['total'] - floatval($g['total'])) < 0.01, $sg['totals']['total'] . ' vs ' . $g['total']);
ok('Σ productos del departamento = departamento', abs($sum($sg['by_product'], 'total') - floatval($g['total'])) < 0.01);
$p = $sg['by_product'][0];
$sp = $m->Sales(null, $from, $to, intval($g['id']), intval($p['id']));
ok('producto = su fila en el departamento', abs($sp['totals']['total'] - floatval($p['total'])) < 0.01);

echo "\n3. Mermas: clasificación sin doble conteo\n";
$w = $m->Waste(null, $from, $to);
$approved = round(floatval($db->query("SELECT IFNULL(SUM(total_cost), 0) FROM waste_records WHERE status = 'approved' AND date(created_timestamp) BETWEEN '$from' AND '$to'")->fetchColumn()), 2);
$bk = $w['buckets'];
ok('operativa + comercial + por clasificar (registros) = aprobadas', abs($bk['operativa']['cost'] + $bk['comercial']['cost'] + $bk['por_clasificar']['cost'] - $approved) < 0.01);
ok('total = aprobadas + diferencias netas de inventario', abs($w['total'] - ($approved + max(0, $w['inventory_diff']['net']))) < 0.01, $w['total']);
ok('mermas por aprobar no suman', abs($w['total'] - $approved - $bk['por_clasificar']['inventory_diff']) < 0.01 && isset($w['pending']['records']));
ok('% del total suma 100', abs($bk['operativa']['pct_total'] + $bk['comercial']['pct_total'] + $bk['por_clasificar']['pct_total'] - 100) <= 0.2);
$wc = $m->Waste(null, $from, $to, 'comercial');
ok('filtro comercial = su segmento', abs($sum($wc['by_reason'], 'cost') - $bk['comercial']['cost']) < 0.01 || count($wc['by_reason']) === 15);
ok('Σ causas = aprobadas', abs($sum($m->Waste(null, $from, $to)['by_group'], 'cost') - $approved) < 0.01);

// Merma nueva pendiente: aparece en la bandeja y no en el total; al aprobarla, sí
$prod = $db->query("SELECT st.product_id, st.location_id, l.branch_id FROM stock st JOIN locations l ON l.id = st.location_id WHERE st.amount >= 2 AND IFNULL(l.is_transit, 0) = 0 AND l.branch_id IS NOT NULL LIMIT 1")->fetch(PDO::FETCH_OBJ);
$reason = $db->query("SELECT id FROM waste_reasons WHERE active = 1 AND requires_approval = 1 AND requires_evidence = 0 LIMIT 1")->fetchColumn();
if ($prod && $reason)
{
	$before = $m->Waste(null, date('Y-m-d'), date('Y-m-d'));
	$rec = \Grocy\Services\WasteService::GetInstance()->ReportWaste(intval($prod->product_id), 1, intval($reason), intval($prod->branch_id), intval($prod->location_id), 'default', 'prueba móvil');
	$rid = intval(is_object($rec) ? $rec->id : ($rec['id'] ?? 0));
	$after = $m->Waste(null, date('Y-m-d'), date('Y-m-d'));
	$status = $db->query("SELECT status FROM waste_records WHERE id = $rid")->fetchColumn();
	ok('merma por aprobar: total sin cambio', $status !== 'pending_approval' || abs($after['total'] - $before['total']) < 0.01, $status);
	$inbox = $m->Inbox(null, null);
	ok('merma por aprobar en la bandeja', $status !== 'pending_approval' || count(array_filter($inbox, fn($i) => $i['type'] === 'waste' && $i['id'] === $rid)) === 1);
	$case = $m->WasteCase($rid);
	ok('caso: producto, causal, clase, responsable', $case && $case['reason'] && $case['bucket'] && $case['reported_by'] !== null);
}
else
{
	ok('datos para merma de prueba', false);
}

echo "\n4. Inventario y SKU\n";
$inv = $m->Inventory(null);
ok('Σ departamentos = valor total', abs($sum($inv['by_group'], 'value') - $inv['totals']['value']) < 0.05);
ok('estados válidos', count(array_filter($inv['items'], fn($i) => !in_array($i['state'], ['agotado', 'bajo', 'vencido', 'por_vencer', 'ok'], true))) === 0);
$sku = $m->Sku(intval($inv['items'][0]['id']), null);
ok('SKU: Σ ubicaciones = existencia', abs($sum($sku['locations'], 'qty') - $sku['qty']) < 0.01);
ok('SKU: historial de movimientos', is_array($sku['movements']));

echo "\n5. Conteo ciego\n";
$pc = $db->query("SELECT id FROM stock_cycle_counts WHERE status = 'pending' LIMIT 1")->fetchColumn();
if ($pc)
{
	$c = $m->CountCase(intval($pc));
	ok('pendiente: stock del sistema oculto', $c['theoretical_stock'] === null);
	ok('pendiente: oculto también en la lista de inventario', count(array_filter($inv['counts'], fn($x) => $x['status'] === 'pending' && $x['theoretical_stock'] !== null)) === 0);
}

echo "\n6. Búsqueda\n";
$bc = $db->query('SELECT pb.barcode, pb.product_id FROM product_barcodes pb JOIN products p ON p.id = pb.product_id WHERE p.active = 1 LIMIT 1')->fetch(PDO::FETCH_OBJ);
if ($bc)
{
	ok('código de barras exacto → SKU', count(array_filter($m->Search($bc->barcode, null), fn($r) => $r['type'] === 'sku' && $r['id'] == $bc->product_id)) === 1);
}
ok('menos de 2 letras → vacío', $m->Search('a', null) === []);

echo "\n$count pruebas, $fails fallos\n";
exit($fails ? 1 : 0);
