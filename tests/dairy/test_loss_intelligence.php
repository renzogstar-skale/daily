<?php
/**
 * Etapa 35: Inteligencia de mermas, pérdidas y prevención (Centro de Control /waste).
 * Cálculos (% sobre ventas, distribución, interanual, recuperación), acumulados anuales, comparación de años,
 * Top 10 de hurto (unidades y $), causal indefinida ⇒ investigación, anti doble conteo, flujo de precios con
 * segregación, rendimiento de cárnicos/fruver, cadena de frío, conteos selectivos y permisos.
 */

use Grocy\Services\LossIntelligenceService;
use Grocy\Services\StockService;
use Grocy\Services\WasteService;

require __DIR__ . '/bootstrap.php';

$li = LossIntelligenceService::GetInstance();
$ws = WasteService::GetInstance();
$stock = StockService::GetInstance();
$near = fn($a, $b, $eps = 0.011) => $a !== null && abs(floatval($a) - floatval($b)) < $eps;
$Y = intval(date('Y'));
$base = ['branch_id' => null, 'group_id' => null, 'format' => null, 'loss_class' => null, 'supplier_id' => null, 'product_id' => null];

// Sandbox aislado: un año sin datos (Y-3 / Y-2) para controlar todas las cifras
$yA = $Y - 3;
$yB = $Y - 2;
$db->exec("INSERT INTO branches (name, code, active, format, sales_area_m2) VALUES ('Sucursal Prueba 35', 'T35', 1, 'Express', 200)");
$bid = intval($db->lastInsertId());
$db->exec("INSERT INTO locations (name, branch_id, active) VALUES ('Bodega T35', $bid, 1)");
$loc = intval($db->lastInsertId());
$db->exec("INSERT INTO product_groups (name, active) VALUES ('Fruver T35', 1)");
$grp = intval($db->lastInsertId());
$qu = intval($val('SELECT id FROM quantity_units ORDER BY id LIMIT 1'));
$mk = function (string $name, float $price) use ($db, $grp, $loc, $qu) {
	$db->prepare('INSERT INTO products (name, product_group_id, location_id, qu_id_purchase, qu_id_stock, qu_id_consume, qu_id_price, sale_price, sku, active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)')
		->execute([$name, $grp, $loc, $qu, $qu, $qu, $qu, $price, 'T35-' . substr(md5($name), 0, 6)]);
	return intval($db->lastInsertId());
};
$pA = $mk('Manzana T35', 4);
$pB = $mk('Licor T35', 50);
$pC = $mk('Pollo T35', 10);
$f = ['branch_id' => $bid] + $base;

$reason = fn(string $code) => intval($val("SELECT id FROM waste_reasons WHERE loss_cause_code = '$code' AND active = 1 ORDER BY id LIMIT 1"));
$insW = $db->prepare("INSERT INTO waste_records (case_number, branch_id, product_id, amount, qu_id, unit_cost, total_cost, waste_reason_id, reported_by_user_id, status, workflow_status, created_timestamp, financial_recovery_amount, transaction_id)
	VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, ?, 'closed', ?, ?, ?)");
$w = function (string $date, int $pid, float $units, float $unitCost, string $cause, string $status = 'approved', float $rec = 0, ?string $tx = null) use ($insW, $bid, $qu, $reason) {
	$insW->execute(['T35-' . uniqid(), $bid, $pid, $units, $qu, $unitCost, round($units * $unitCost, 2), $reason($cause), $status, "$date 10:00:00", $rec, $tx]);
};
$sale = function (string $date, float $net, float $gm, int $pid = 0, float $units = 0, float $unitPrice = 0) use ($db, $bid, $qu) {
	$db->prepare("INSERT INTO sales (sale_number, branch_id, user_id, subtotal, tax_amount, discount_amount, total_amount, total_cogs, gross_margin, payment_method, status, created_timestamp) VALUES (?, ?, 1, ?, 0, 0, ?, ?, ?, 'cash', 'completed', ?)")
		->execute(['T35-' . uniqid(), $bid, $net, $net, $net - $gm, $gm, "$date 12:00:00"]);
	if ($pid)
	{
		$db->prepare('INSERT INTO sale_items (sale_id, product_id, amount, qu_id, unit_price, subtotal, unit_cost, total_cost, created_timestamp) VALUES (?, ?, ?, ?, ?, ?, 0, 0, ?)')
			->execute([intval($db->lastInsertId()), $pid, $units, $qu, $unitPrice, $units * $unitPrice, "$date 12:00:00"]);
	}
};

echo "\n1. Migración y causales canónicas\n";
ok('loss_causes con códigos únicos (≥ 15 + INV)', intval($val('SELECT COUNT(DISTINCT code) FROM loss_causes')) >= 16 && intval($val('SELECT COUNT(*) FROM loss_causes')) === intval($val('SELECT COUNT(DISTINCT code) FROM loss_causes')));
ok('todos los motivos tienen causal canónica', intval($val("SELECT COUNT(*) FROM waste_reasons WHERE IFNULL(loss_cause_code, '') = '' OR loss_cause_code NOT IN (SELECT code FROM loss_causes)")) === 0);
ok('INV no es definitiva', intval($val("SELECT is_definitive FROM loss_causes WHERE code = 'INV'")) === 0);
ok('hay motivos de robo interno y externo', $reason('RIN') > 0 && $reason('REX') > 0);

echo "\n2. Cálculos del resumen (fórmulas del Centro de Control)\n";
$w("$yB-01-10", $pA, 10, 2, 'MAN');            // operativa 20
$w("$yB-01-12", $pA, 5, 2, 'EXP');             // comercial 10
$w("$yB-01-15", $pB, 2, 30, 'REX', 'approved', 15); // robo externo 60 (recuperado 15)
$w("$yB-01-16", $pB, 1, 30, 'RIN');            // robo interno 30
$w("$yB-01-17", $pA, 3, 2, 'AVE');             // avería 6
$w("$yB-01-18", $pA, 50, 2, 'MAN', 'pending_approval'); // por aprobar: no suma
$w("$yB-01-19", $pA, 50, 2, 'MAN', 'rejected');         // rechazada: no suma
$sale("$yB-01-20", 1000, 300);
$s = $li->Summary($f, "$yB-01-01", "$yB-01-31");
ok('merma total = 126 (solo aprobadas)', $near($s['total']['value'], 126), $s['total']['value']);
ok('% merma sobre ventas netas = 126/1000×100 = 12.6', $near($s['total']['pct_sales'], 12.6), $s['total']['pct_sales']);
ok('operativa 20 = 2% ventas y 15.87% del total', $near($s['operativa']['value'], 20) && $near($s['operativa']['pct_sales'], 2) && $near($s['operativa']['share'], 15.87), json_encode($s['operativa']));
ok('comercial 10, avería 6', $near($s['comercial']['value'], 10) && $near($s['averia']['value'], 6));
ok('robo 90 = interno 30 + externo 60', $near($s['robo']['value'], 90) && $near($s['robo']['internal'], 30) && $near($s['robo']['external'], 60));
ok('pendientes separados (1 por $100)', $s['pending']['count'] === 1 && $near($s['pending']['value'], 100));
ok('impacto en margen: 30% → 17.4% (12.6 pp)', $near($s['margin_impact']['margin_before_pct'], 30) && $near($s['margin_impact']['margin_after_pct'], 17.4) && $near($s['margin_impact']['pp'], 12.6), json_encode($s['margin_impact']));
ok('valor de venta potencial separado del costo (10×4+5×4+2×50+1×50+3×4 = 222)', $near($s['sale_value'], 222), $s['sale_value']);
ok('recuperación: 15 de 96 recuperables (robo 90 + avería 6) = 15.63%', $near($s['recovery']['recovered'], 15) && $near($s['recovery']['pct'], 15.63, 0.02), json_encode($s['recovery']));
$sY = $li->Summary($f, ($yB + 1) . '-01-01', ($yB + 1) . '-01-31');
ok('variación interanual: (0 − 126)/126 = −100%', $near($sY['yoy']['pct'], -100) && $near($sY['yoy']['prev'], 126), json_encode($sY['yoy']));
ok('sin dotación ⇒ merma por empleado/hora = null (no inventa)', $s['labor']['per_employee'] === null && $s['labor']['per_hour'] === null);
$db->exec("INSERT INTO employees (name, branch_id, active) VALUES ('Emp T35', $bid, 1)");
$emp = intval($db->lastInsertId());
$db->exec("INSERT INTO employee_shifts (employee_id, branch_id, shift_date, start_time, end_time, break_minutes, status) VALUES ($emp, $bid, '$yB-01-10', '08:00', '17:00', 60, 'published')");
$s = $li->Summary($f, "$yB-01-01", "$yB-01-31");
ok('merma por empleado 126 y por hora 126/8 = 15.75 (turnos programados)', $near($s['labor']['per_employee'], 126) && $near($s['labor']['per_hour'], 15.75) && $s['labor']['source'] === 'turnos programados', json_encode($s['labor']));
ok('cada KPI tiene enlace a su detalle', str_contains($s['operativa']['drill'], 'loss_class=operativa') && str_contains($s['robo']['drill'], '#tab-robo'));

echo "\n3. Anti doble conteo (ajustes vs. mermas)\n";
$stock->AddProduct($pA, 100, date('Y-m-d', strtotime('+30 days')), StockService::TRANSACTION_TYPE_PURCHASE, date('Y-m-d'), 2, $loc);
$stock->InventoryProduct($pA, 90, null, $loc, 2);
$txAdj = $val("SELECT transaction_id FROM stock_log WHERE product_id = $pA AND transaction_type = 'inventory-correction' ORDER BY id DESC LIMIT 1");
$today = date('Y-m-d');
$ev = $li->Events(['branch_id' => $bid] + $base, $today, $today);
$adj = array_values(array_filter($ev, fn($e) => $e['loss_class'] === 'ajuste'));
ok('ajuste de inventario −10 u. × $2 = $20 como evento A único', count($adj) === 1 && $near($adj[0]['cost'], 20) && str_starts_with($adj[0]['uid'], 'A'), json_encode($adj));
// Una merma que reutiliza la transacción del ajuste no debe contarse dos veces
$w($today, $pA, 10, 2, 'AJI', 'approved', 0, $txAdj);
$ev = $li->Events(['branch_id' => $bid] + $base, $today, $today);
ok('misma transacción en merma y ajuste ⇒ cuenta una vez ($20)', $near(array_sum(array_column($ev, 'cost')), 20), array_sum(array_column($ev, 'cost')));
ok('identificadores de evento únicos', count($ev) === count(array_unique(array_column($ev, 'uid'))));
// Avería con merma vinculada no suma; sin merma sí
$db->exec("INSERT INTO damage_incidents (incident_number, product_id, branch_id, damage_type, units_affected, units_discarded, unit_cost, total_cost_affected, discard_loss_cost, status, reported_by_user_id, created_timestamp) VALUES ('DI-T35a', $pA, $bid, 'golpe', 2, 2, 2, 4, 4, 'closed', 1, '$today 09:00:00')");
$db->exec("INSERT INTO damage_incidents (incident_number, product_id, branch_id, damage_type, units_affected, units_discarded, unit_cost, total_cost_affected, discard_loss_cost, status, reported_by_user_id, created_timestamp) VALUES ('DI-T35b', $pA, $bid, 'golpe', 3, 3, 2, 6, 6, 'discarded_to_waste', 1, '$today 09:00:00')");
$ev = $li->Events(['branch_id' => $bid] + $base, $today, $today);
ok('avería cerrada sin merma suma $4; la enviada a merma no', $near(array_sum(array_column(array_filter($ev, fn($e) => $e['src'] === 'damage'), 'cost')), 4));

echo "\n4. Acumulado anual y comparación de años\n";
$w("$yA-01-05", $pA, 10, 2, 'MAN'); // 20
$w("$yA-03-05", $pA, 20, 2, 'MAN'); // 40
$w("$yA-06-05", $pA, 5, 2, 'EXP');  // 10
$w("$yB-03-05", $pA, 25, 2, 'MAN'); // + 50 (ene ya tiene 126)
$sale("$yA-01-20", 500, 100);
$sale("$yA-03-20", 500, 100);
$cmp = $li->YearComparison($f, [$yA, $yB], 'cost');
$a = $cmp['years'][0];
$b = $cmp['years'][1];
ok("acumulado $yA: ene 20, mar 60, jun 70, dic 70", $a['cumulative'][0] == 20 && $a['cumulative'][2] == 60 && $a['cumulative'][5] == 70 && $a['cumulative'][11] == 70, json_encode($a['cumulative']));
ok("mensual $yA: feb 0, mar 40", $a['monthly'][1] == 0 && $a['monthly'][2] == 40);
ok("acumulado $yB a dic = 126 + 50 = 176", $near($b['cumulative'][11], 176), $b['cumulative'][11]);
$c = $cmp['comparison'];
ok('diferencia absoluta (176 − 70 = 106) y % (+151.43%) a diciembre', $near($c['diff'], 106) && $near($c['pct'], 151.43, 0.02) && $c['trend'] === 'deterioro', json_encode([$c['diff'], $c['pct'], $c['trend']]));
ok('explicación generada con datos (mes y meses con mayor pérdida)', count($c['explanation']) >= 2 && str_contains($c['explanation'][1], 'Ene'), implode(' | ', $c['explanation']));
ok('mes con mayor pérdida identificado', $b['top_months'][0]['month'] === 'Ene');
$cmpPct = $li->YearComparison($f, [$yA], 'pct');
ok("% de ventas acumulado $yA a marzo = 60/1000 = 6%", $near($cmpPct['years'][0]['cumulative'][2], 6), $cmpPct['years'][0]['cumulative'][2]);
$cmpU = $li->YearComparison($f, [$yA], 'units');
ok('unidades acumuladas = 35', $near($cmpU['years'][0]['cumulative'][11], 35));
$cur = $li->YearComparison($f, [$Y], 'cost');
ok('meses futuros del año en curso = null', intval(date('n')) === 12 || $cur['years'][0]['cumulative'][11] === null);
$cmpEmpty = $li->YearComparison($f, [$yA - 5, $yA], 'cost');
ok('año sin histórico ⇒ explicación pide importar (no inventa)', $cmpEmpty['comparison']['trend'] === 'sin_historico');
$cmpF = $li->YearComparison(['branch_id' => $bid, 'loss_class' => 'comercial'] + $base, [$yA], 'cost');
ok('filtro por clase (comercial) = 10', $near($cmpF['years'][0]['cumulative'][11], 10));
$cmpP = $li->YearComparison(['branch_id' => $bid, 'product_id' => $pB] + $base, [$yB], 'cost');
ok('filtro por SKU (licor) = 90', $near($cmpP['years'][0]['cumulative'][11], 90));

echo "\n5. Top 10 de hurto (solo confirmados)\n";
$w("$yB-02-01", $pA, 40, 1, 'REX');  // 40 u. / $40
$w("$yB-02-02", $pB, 1, 30, 'REX', 'pending_approval'); // no confirmado
$w("$yB-02-03", $pA, 7, 2, 'INV');    // bajo investigación, nunca robo
$t = $li->Theft($f, "$yB-01-01", "$yB-12-31");
ok('ranking por unidades: manzana (40) antes que licor (3)', $t['top_units'][0]['product_id'] === $pA && $near($t['top_units'][0]['units'], 40) && $near($t['top_units'][1]['units'], 3), json_encode(array_map(fn($x) => [$x['name'], $x['units']], $t['top_units'])));
ok('ranking por dinero: licor ($90) antes que manzana ($40)', $t['top_cost'][0]['product_id'] === $pB && $near($t['top_cost'][0]['cost'], 90));
ok('% del total de hurtos: 90/130 = 69.23%', $near($t['top_cost'][0]['pct'], 69.23, 0.02));
ok('tipo interno/externo por SKU', $t['top_cost'][0]['type'] === 'Externo / Interno' || $t['top_cost'][0]['type'] === 'Interno / Externo', $t['top_cost'][0]['type']);
ok('pendientes de aprobación no cuentan como hurto', $t['incidents'] === 3);
ok('diferencias bajo investigación separadas ($14)', $t['investigation']['count'] === 1 && $near($t['investigation']['cost'], 14));
ok('interno 30 / externo 100', $near($t['internal'], 30) && $near($t['external'], 100));

echo "\n6. Matriz de causales y Pareto\n";
$m = $li->CauseMatrix($f, "$yB-01-01", "$yB-12-31");
$codes = array_column($m['causes'], 'code');
ok('Pareto ordenado y acumulado llega a 100%', $m['causes'][0]['cost'] >= $m['causes'][1]['cost'] && $near(max(array_column($m['causes'], 'cum_pct')), 100));
ok('distribución % = costo causal / total', $near(array_sum(array_map(fn($c) => $c['pct'] ?? 0, $m['causes'])), 100, 0.1));

echo "\n7. Causal indefinida ⇒ investigación con plazo y responsable\n";
$stock->AddProduct($pC, 10, date('Y-m-d', strtotime('+10 days')), StockService::TRANSACTION_TYPE_PURCHASE, date('Y-m-d'), 3, $loc);
$rec = $ws->ReportWaste($pC, 1, $reason('INV'), $bid, $loc, 'default', 'faltante sin explicación');
$row = $db->query('SELECT workflow_status, due_date, investigator_user_id FROM waste_records WHERE id = ' . intval($rec->id ?? $rec['id']))->fetch(PDO::FETCH_ASSOC);
ok('workflow = investigating, con plazo y responsable', $row['workflow_status'] === 'investigating' && $row['due_date'] >= date('Y-m-d') && intval($row['investigator_user_id']) > 0, json_encode($row));

echo "\n8. Control de precios: detección, impacto y flujo con segregación\n";
$sale(date('Y-m-d'), 24, 0, $pB, 3, 45); // autorizado 50, cobrado 45 ⇒ subcobro 15
$r = $li->DetectPriceIssues($bid, date('Y-m-d', strtotime('-1 day')), date('Y-m-d'), 1);
$r2 = $li->DetectPriceIssues($bid, date('Y-m-d', strtotime('-1 day')), date('Y-m-d'), 1);
$d = $db->query("SELECT * FROM price_discrepancies WHERE product_id = $pB AND kind = 'pos_vs_authorized'")->fetch(PDO::FETCH_ASSOC);
ok('detecta POS ≠ autorizado como subcobro $15', $d && $d['impact_type'] === 'subcobro' && $near($d['impact_amount'], 15), json_encode($d));
ok('detección idempotente', $r2['created'] === 0);
ok('impacto sin verificar hasta validar', intval($d['impact_verified']) === 0);
[$it, $imp] = LossIntelligenceService::PriceImpact('pos_vs_authorized', 10, 12, null, 5);
ok('sobrecobro separado (cobra 12 vs 10 × 5 = $10)', $it === 'sobrecobro' && $near($imp, 10));
$id = intval($d['id']);
expectError('no permite saltar pasos (detected → approved)', fn() => $li->AdvancePriceIssue($id, 'approved', [], 2, true));
$li->AdvancePriceIssue($id, 'validated', ['units_affected' => 3], 1, true);
expectError('evidencia obligatoria', fn() => $li->AdvancePriceIssue($id, 'evidence', [], 1, true));
$li->AdvancePriceIssue($id, 'evidence', ['evidence_path' => 'foto_ticket.jpg'], 1, true);
$li->AdvancePriceIssue($id, 'corrected', ['proposed_price' => 50], 1, true);
$priceBefore = floatval($val("SELECT sale_price FROM products WHERE id = $pB"));
expectError('sin permiso no aprueba', fn() => $li->AdvancePriceIssue($id, 'approved', [], 2, false));
expectError('quien corrige no aprueba (segregación)', fn() => $li->AdvancePriceIssue($id, 'approved', [], 1, true));
ok('el precio no cambia antes de aprobar', $near($priceBefore, 50));
$li->AdvancePriceIssue($id, 'approved', [], 2, true);
expectError('sincronización POS exige referencia', fn() => $li->AdvancePriceIssue($id, 'pos_synced', [], 2, true));
$li->AdvancePriceIssue($id, 'pos_synced', ['pos_sync_reference' => 'LOTE-POS-1'], 2, true);
$li->AdvancePriceIssue($id, 'verified', [], 1, true);
$li->AdvancePriceIssue($id, 'closed', [], 2, true);
ok('flujo completo con 7 transiciones auditadas', intval($val("SELECT COUNT(*) FROM price_discrepancy_log WHERE discrepancy_id = $id")) === 7 && $val("SELECT status FROM price_discrepancies WHERE id = $id") === 'closed');
$pi = $li->PriceIssues($bid);
ok('impacto comprobado: subcobro $15', $near($pi['summary']['verified_impact']['subcobro'], 15));
$skuB = $val("SELECT sku FROM products WHERE id = $pB");
$db->exec("INSERT INTO product_barcodes (product_id, barcode, qu_id, amount) VALUES ($pA, '$skuB', $qu, 1)");
$li->DetectPriceIssues($bid, date('Y-m-d'), date('Y-m-d'), 1);
ok('detecta código de barras que resuelve otro SKU', intval($val("SELECT COUNT(*) FROM price_discrepancies WHERE kind = 'barcode_sku' AND barcode = '$skuB'")) === 1);

echo "\n9. Pollo: drenaje vs. descarte y tolerancias\n";
$lotId = $li->SaveYieldLot(['kind' => 'pollo', 'product_id' => $pC, 'branch_id' => $bid, 'received_kg' => 100, 'drain_water_kg' => 2, 'drain_blood_kg' => 1, 'discarded_kg' => 2, 'sold_kg' => 90, 'unit_cost_kg' => 3], 1);
$lot = $db->query("SELECT * FROM perishable_yield_lots WHERE id = $lotId")->fetch(PDO::FETCH_ASSOC);
$cy = LossIntelligenceService::YieldCalc($lot);
ok('100 kg: drenaje 3 kg (3%) natural, descarte 2 kg (2%) evitable', $near($cy['natural_kg'], 3) && $near($cy['avoidable_kg'], 2) && $near($cy['natural_pct'], 3) && $near($cy['avoidable_pct'], 2));
ok('disponible para venta = 97 kg; costo natural $9, evitable $6', $near($cy['available_kg'], 97) && $near($cy['natural_cost'], 9) && $near($cy['avoidable_cost'], 6));
ok('no excede tolerancias (3% y 2%)', !$cy['natural_exceeded'] && !$cy['avoidable_exceeded']);
$li->SaveYieldLot(['id' => $lotId, 'kind' => 'pollo', 'product_id' => $pC, 'branch_id' => $bid, 'received_kg' => 100, 'drain_water_kg' => 3, 'drain_blood_kg' => 1, 'discarded_kg' => 2, 'sold_kg' => 90, 'unit_cost_kg' => 3], 1);
$cy = LossIntelligenceService::YieldCalc($db->query("SELECT * FROM perishable_yield_lots WHERE id = $lotId")->fetch(PDO::FETCH_ASSOC));
ok('drenaje 4% excede tolerancia 3% (exceso 1 kg = $3)', $cy['natural_exceeded'] && $near($cy['excess_natural_kg'], 1) && $near($cy['excess_natural_cost'], 3));
$li->CloseYieldLot($lotId, 2, 1);
$cy = LossIntelligenceService::YieldCalc($db->query("SELECT * FROM perishable_yield_lots WHERE id = $lotId")->fetch(PDO::FETCH_ASSOC));
ok('lote cerrado: diferencia teórico vs. real = 100−4−2−90−2 = 2 kg', $near($cy['gap_kg'], 2));
expectError('pérdidas + ventas > recibido', fn() => $li->SaveYieldLot(['kind' => 'carne', 'product_id' => $pC, 'branch_id' => $bid, 'received_kg' => 10, 'discarded_kg' => 6, 'sold_kg' => 5], 1));
$li->SaveYieldLot(['kind' => 'fruver', 'product_id' => $pA, 'branch_id' => $bid, 'received_kg' => 50, 'dehydration_kg' => 1, 'bruise_kg' => 2, 'ripening_kg' => 1, 'sold_kg' => 40, 'unit_cost_kg' => 1], 1);
$per = $li->Perishables($f, date('Y-m-01'), date('Y-m-t'));
ok('fruver por familia: 4 kg de 50 = 8%', $near($per['fruver_families']['Fruver T35']['loss_pct'] ?? null, 8), json_encode($per['fruver_families']));
ok('la tolerancia técnica no suma a la merma total', $near($li->Summary($f, date('Y-m-01'), date('Y-m-t'))['total']['value'], array_sum(array_column($li->Events($f, date('Y-m-01'), date('Y-m-t')), 'cost'))));

echo "\n10. Cadena de frío\n";
$db->exec("UPDATE locations SET is_freezer = 0 WHERE id = $loc");
$t0 = date('Y-m-d');
foreach ([['08:00', 3.0], ['09:00', 8.5], ['10:00', 9.0], ['11:30', 4.0]] as [$h, $tmp])
{
	$db->exec("INSERT INTO cold_chain_temperatures (location_id, branch_id, temperature, recorded_timestamp, recorded_by, status) VALUES ($loc, $bid, $tmp, '$t0 $h:00', 'T35', 'normal')");
}
$cc = $li->ColdChain($bid, $t0, $t0);
$e = $cc['excursions'][0] ?? null;
ok('excursión detectada 09:00→11:30 (150 min), máx 9 °C', $e && $e['minutes'] === 150 && $near($e['max_temp'], 9), json_encode($e ? [$e['minutes'], $e['max_temp']] : null));
ok('lotes potencialmente comprometidos listados, sin liberación automática', $e && count($e['lots']) >= 1 && $e['value_at_risk'] > 0);

echo "\n11. Conteos selectivos por riesgo\n";
$rk = $li->SensitiveRanking($bid);
$top = $rk['products'][0];
ok('ranking ordenado por puntaje con factores', $top['score'] >= end($rk['products'])['score'] && isset($top['factors']['theft']));
ok('el producto con hurto, merma y diferencias aparece en prioridad', in_array($pA, array_column(array_slice($rk['products'], 0, 5), 'product_id')) || in_array($pB, array_column(array_slice($rk['products'], 0, 5), 'product_id')));
$sc = $li->ScheduleRiskCounts($bid, 1, ['urgente', 'alta', 'media', 'baja']);
ok('programa conteos ciegos para productos de riesgo con stock', intval($sc['scheduled'] ?? 0) >= 1, json_encode($sc));

echo "\n12. Formatos y sucursales (relativo vs. absoluto)\n";
$fm = $li->Formats(['branch_id' => null] + $base, "$yB-01-01", "$yB-01-31");
$row = array_values(array_filter($fm['branches'], fn($r) => $r['branch_id'] === $bid))[0];
ok('operativa en $ y % de ventas por sucursal', $near($row['operativa'], 20) && $near($row['operativa_pct'], 2));
ok('merma por m² = 126/200 = 0.63', $near($row['per_m2'], 0.63));
ok('formato «Express» agregado', in_array('Express', array_column($fm['formats'], 'format')));
ok('mapa de calor sucursal × departamento', isset($fm['heatmap']['values']['Sucursal Prueba 35']['Fruver T35']));

echo "\n13. Impacto financiero y VIS\n";
$fin = $li->Financial($f, "$yB-01-01", "$yB-01-31");
ok('utilidad operativa estimada = margen − merma = 300 − 126 = 174', $near($fin['operating_profit_est'], 174));
ok('VIS sin definición ⇒ no se calcula', $fin['vis']['defined'] === false && $fin['vis']['value'] === null);
expectError('parámetro desconocido rechazado', fn() => $li->SaveSetting('no_existe', '1'));

echo "\n" . ($fails === 0 ? "OK: $count verificaciones" : "FALLAS: $fails de $count") . "\n";
exit($fails === 0 ? 0 : 1);
