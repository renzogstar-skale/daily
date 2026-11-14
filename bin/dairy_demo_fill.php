<?php
/**
 * Datos de ejemplo para módulos que en demo quedaban en 0, creados con los servicios reales.
 * Idempotente por marca en daily_seed_runs. Lo incluye bin/dairy_catchup.php; también: php bin/dairy_demo_fill.php
 */
if (!defined('GROCY_DATAPATH'))
{
	$root = dirname(__DIR__);
	define('GROCY_DATAPATH', $root . '/data');
	define('GROCY_USER_ID', 1);
	require $root . '/packages/autoload.php';
	require $root . '/data/config.php';
	require $root . '/config-dist.php';
	date_default_timezone_set(GROCY_TIMEZONE ?: date_default_timezone_get());
}
if (GROCY_MODE !== 'demo')
{
	return;
}
$db = Grocy\Services\DatabaseService::GetInstance()->GetDbConnectionRaw();
$out = ['errors' => []];
$done = fn($m) => (bool) $db->query('SELECT COUNT(*) FROM daily_seed_runs WHERE code = ' . $db->quote($m))->fetchColumn();
$mark = fn($m) => $db->prepare('INSERT OR IGNORE INTO daily_seed_runs (code, run_at) VALUES (?, ?)')->execute([$m, date('Y-m-d H:i:s')]);
$step = function (string $code, callable $fn) use (&$out, $done, $mark)
{
	if ($done($code))
	{
		return;
	}
	try
	{
		$out[$code] = $fn();
		$mark($code);
	}
	catch (Throwable $e)
	{
		$out['errors'][] = "$code: " . $e->getMessage();
	}
};

// 1) Retiros de producto: uno cerrado (histórico) y uno abierto con un lote confirmado
$step('demo_fill_recalls', function () use ($db)
{
	if ($db->query('SELECT COUNT(*) FROM product_recalls')->fetchColumn())
	{
		return 0;
	}
	$svc = Grocy\Services\RecallService::GetInstance();
	$pids = $db->query("SELECT p.id FROM products p JOIN stock s ON s.product_id = p.id JOIN locations l ON l.id = s.location_id
		WHERE p.active = 1 AND IFNULL(p.device_control, 0) = 0 AND IFNULL(l.is_transit, 0) = 0 AND s.amount > 0
			AND s.stock_id NOT IN (SELECT stock_id FROM quarantine_items WHERE status = 'in_quarantine')
		GROUP BY p.id HAVING SUM(s.amount) >= 4 ORDER BY COUNT(DISTINCT l.branch_id), SUM(s.amount) LIMIT 10")->fetchAll(PDO::FETCH_COLUMN);
	$reasons = ['Aviso del proveedor: posible contaminación cruzada en el lote', 'Hallazgo de calidad: empaque con sello defectuoso'];
	$n = 0;
	foreach ($pids as $pid)
	{
		if ($n >= 2)
		{
			break;
		}
		$i = $n;
		if (!Grocy\Services\RecallService::GetInstance()->FindAffectedStock(intval($pid), null, null))
		{
			continue;
		}
		$r = $svc->CreateRecall(['product_id' => intval($pid), 'reason' => $reasons[$i], 'severity' => 'class_2', 'disposition' => 'destroy'], 1);
		$items = $db->query('SELECT id, amount FROM product_recall_items WHERE recall_id = ' . intval($r['id']))->fetchAll(PDO::FETCH_ASSOC);
		foreach ($items as $k => $it)
		{
			if ($i === 0 || $k === 0)
			{
				$svc->ConfirmItem(intval($it['id']), floatval($it['amount']), 'Separado en zona de cuarentena', 1);
			}
		}
		if ($i === 0)
		{
			$svc->CloseRecall(intval($r['id']), 'Lote destruido con acta', 1);
		}
		$n++;
	}
	return $n;
});

// 2) Sesiones de escaneo: un conteo guiado cerrado y uno abierto en la primera sucursal
$step('demo_fill_scan', function () use ($db)
{
	if ($db->query('SELECT COUNT(*) FROM dairy_scan_sessions')->fetchColumn())
	{
		return 0;
	}
	$svc = Grocy\Services\ScanService::GetInstance();
	$loc = $db->query("SELECT l.id, l.branch_id FROM locations l JOIN stock s ON s.location_id = l.id WHERE l.active = 1 AND IFNULL(l.is_transit, 0) = 0 AND l.branch_id IS NOT NULL
		GROUP BY l.id HAVING COUNT(DISTINCT s.product_id) >= 6 ORDER BY l.branch_id, l.id LIMIT 1")->fetch(PDO::FETCH_ASSOC);
	$prods = $db->query('SELECT product_id, SUM(amount) a FROM stock WHERE location_id = ' . intval($loc['id']) . ' GROUP BY product_id ORDER BY product_id LIMIT 6')->fetchAll(PDO::FETCH_ASSOC);
	$n = 0;
	foreach ([true, false] as $j => $close)
	{
		$s = $svc->OpenSession(['type' => 'count', 'branch_id' => intval($loc['branch_id']), 'location_id' => intval($loc['id']), 'device_id' => 'PDA-DEMO-0' . ($j + 1), 'notes' => 'Conteo de ejemplo'], 1);
		$sid = intval($s['id'] ?? $s['session']['id']);
		foreach (array_slice($prods, $j * 3, 3) as $k => $p)
		{
			$svc->AddLine($sid, ['client_uuid' => "demo-scan-$j-$k-" . $p['product_id'], 'product_id' => intval($p['product_id']), 'amount' => max(1, floor(floatval($p['a'])) - ($k === 1 ? 1 : 0)), 'device_id' => 'PDA-DEMO-0' . ($j + 1)], 1);
		}
		if ($close)
		{
			$svc->CloseSession($sid, [], 1);
		}
		$n++;
	}
	return $n;
});

// 3) POS: autorizaciones de supervisor por sucursal (descuento y reimpresión) y una devolución sobre el umbral
// pedida por un cajero sin permiso de supervisor (flujo Authorize → TakeAuthorization en PosReturnService)
$step('demo_fill_pos_auth_' . date('Ymd'), function () use ($db)
{
	$pos = Grocy\Services\PosService::GetInstance();
	$thr = $pos->Settings()['return_auth_threshold'];
	$ins = $db->prepare("INSERT INTO daily_pos_authorizations (kind, branch_id, sale_id, requested_by, approved_by, amount, pct, reason, status, created_at, used_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
	$n = 0;
	foreach ($db->query('SELECT id FROM branches WHERE id <= 10 ORDER BY id')->fetchAll(PDO::FETCH_COLUMN) as $b)
	{
		$sup = intval($db->query("SELECT ub.user_id FROM user_branches ub JOIN user_permissions_resolved p ON p.user_id = ub.user_id AND p.permission_name = 'SALES_SUPERVISE' WHERE ub.branch_id = $b ORDER BY ub.user_id LIMIT 1")->fetchColumn() ?: 1);
		$cashier = intval($db->query("SELECT ub.user_id FROM user_branches ub WHERE ub.branch_id = $b AND ub.user_id NOT IN (SELECT user_id FROM user_permissions_resolved WHERE permission_name IN ('SALES_SUPERVISE', 'ADMIN')) ORDER BY ub.user_id LIMIT 1")->fetchColumn() ?: $sup);
		$sales = $db->query("SELECT id, total_amount, created_timestamp FROM sales WHERE branch_id = $b AND status = 'completed' AND date(created_timestamp) = date('now', 'localtime') ORDER BY id LIMIT 2")->fetchAll(PDO::FETCH_ASSOC);
		foreach ($sales as $k => $s)
		{
			$at = date('Y-m-d H:i:s', strtotime($s['created_timestamp']) - 60);
			$k === 0
				? $ins->execute(['discount', $b, $s['id'], $cashier, $sup, round($s['total_amount'] * 0.1, 2), 10, 'Cliente frecuente, descuento comercial', 'used', $at, $s['created_timestamp']])
				: $ins->execute(['reprint', $b, $s['id'], $cashier, $sup, null, null, 'Ticket ilegible, cliente pide copia', 'used', $s['created_timestamp'], $s['created_timestamp']]);
			$n++;
		}
		// Devolución autorizada: venta de hoy cuyo valor devolvible supera el umbral
		if ($cashier === $sup)
		{
			continue;
		}
		$svc = Grocy\Services\PosReturnService::GetInstance();
		foreach ($db->query("SELECT id FROM sales WHERE branch_id = $b AND status = 'completed' AND total_amount > " . ($thr + 5) . " AND date(created_timestamp) = date('now', 'localtime') ORDER BY id DESC LIMIT 15")->fetchAll(PDO::FETCH_COLUMN) as $sid)
		{
			$E = $svc->Eligibility(intval($sid));
			if (!$E['eligible'] || !$E['lines'])
			{
				continue;
			}
			$lines = [];
			$amt = 0;
			foreach ($E['lines'] as $l)
			{
				$q = floatval($l['returnable']);
				$pu = floatval($l['unit_refund']);
				if ($q <= 0 || $pu <= 0)
				{
					continue;
				}
				$lines[] = ['sale_item_id' => $l['sale_item_id'], 'quantity' => $q, 'reason' => 'no_deseado', 'condition' => 'sin_abrir'];
				$amt += $q * $pu;
				if ($amt > $thr + 5)
				{
					break;
				}
			}
			if ($amt <= $thr + 5)
			{
				continue;
			}
			$ins->execute(['refund', $b, $sid, $cashier, $sup, ceil($amt) + 1, null, 'Devolución sobre el umbral revisada por el supervisor', 'granted', date('Y-m-d H:i:s'), null]);
			$svc->Process(['return_ref' => 'demo-rauth-' . date('Ymd') . "-$b-0000", 'sale_id' => intval($sid), 'register' => 'Caja 1', 'resolution' => 'credit_note',
				'lines' => $lines, 'auth_id' => intval($db->lastInsertId()), 'notes' => 'Ejemplo: devolución con autorización'], $cashier);
			$n++;
			break;
		}
	}
	return $n;
});

// 4) Facturas de proveedor en cada sucursal sobre OC recibidas: cotejada, con diferencia de precio y aprobada con diferencia
$step('demo_fill_invoices', function () use ($db)
{
	$svc = Grocy\Services\SupplierInvoiceService::GetInstance();
	$n = 0;
	foreach ($db->query('SELECT id FROM branches WHERE id <= 10 ORDER BY id')->fetchAll(PDO::FETCH_COLUMN) as $b)
	{
		if ($db->query("SELECT COUNT(*) FROM supplier_invoices WHERE branch_id = $b")->fetchColumn() >= 3)
		{
			continue;
		}
		$orders = $db->query("SELECT o.id FROM procurement_orders o WHERE o.branch_id = $b AND o.status = 'received'
			AND o.id NOT IN (SELECT order_id FROM supplier_invoices) ORDER BY o.id DESC LIMIT 3")->fetchAll(PDO::FETCH_COLUMN);
		foreach ($orders as $k => $oid)
		{
			$items = [];
			$sub = 0;
			foreach ($db->query("SELECT id, unit_price, IFNULL(NULLIF(received_amount, 0), IFNULL(approved_amount, suggested_amount)) q FROM procurement_order_items WHERE order_id = $oid")->fetchAll(PDO::FETCH_ASSOC) as $j => $it)
			{
				// Una línea facturada 6 % por encima del precio de la OC en la segunda y tercera factura
				$price = round(floatval($it['unit_price']) * ($k > 0 && $j === 0 ? 1.06 : 1), 2);
				$items[] = ['order_item_id' => intval($it['id']), 'invoiced_amount' => floatval($it['q']), 'unit_price' => $price];
				$sub += $price * floatval($it['q']);
			}
			if (!$items)
			{
				continue;
			}
			$r = $svc->RegisterInvoice(intval($oid), ['invoice_number' => sprintf('FE-%02d%05d', $b, $oid), 'invoice_date' => date('Y-m-d', strtotime('-' . (2 + $k * 3) . ' days')),
				'tax_amount' => round($sub * 0.07, 2), 'items' => $items, 'notes' => 'Factura electrónica cotejada contra OC y recepción'], 1);
			$id = intval($r['id'] ?? $db->query('SELECT MAX(id) FROM supplier_invoices')->fetchColumn());
			if ($k === 2 && $db->query("SELECT status FROM supplier_invoices WHERE id = $id")->fetchColumn() === 'discrepancy')
			{
				$svc->DecideInvoice($id, true, 'Alza de precio pactada con el proveedor por correo', intval($db->query("SELECT user_id FROM user_permissions_resolved WHERE permission_name = 'ADMIN' AND user_id <> 1 ORDER BY user_id LIMIT 1")->fetchColumn()));
			}
			$n++;
		}
	}
	return $n;
});

// 5) Programa de puntos activo ($1 = 1 punto desde enero): acumula compras reales y registra algunos canjes
$step('demo_fill_loyalty', function () use ($db)
{
	$crm = Grocy\Services\CustomerCrmService::GetInstance();
	$crm->SaveSettings(['loyalty_enabled' => '1', 'amount_per_point' => 1, 'point_value' => 0.01, 'loyalty_start' => '2026-01-01']);
	$n = $crm->SyncEarned();
	foreach ($db->query("SELECT customer_id FROM customer_loyalty_ledger GROUP BY customer_id HAVING SUM(points) > 800 ORDER BY SUM(points) DESC LIMIT 5")->fetchAll(PDO::FETCH_COLUMN) as $k => $cid)
	{
		$crm->LoyaltyMove(intval($cid), 'redeem', [500, 300, 250, 400, 200][$k], 'Canje en caja por descuento en compra');
	}
	return $n;
});

// 6) Reclamos a proveedor del mes por mermas aprobadas (mismo registro que SaveSupplierReturn) y notas de crédito
// recibidas en la mitad (mismo efecto que RecordSupplierCredit)
$step('demo_fill_supplier_claims_' . date('Ym'), function () use ($db)
{
	$ws = $db->query("SELECT w.id, w.product_id, w.branch_id, w.amount, w.total_cost, p.shopping_location_id, p.qu_id_stock FROM waste_records w JOIN products p ON p.id = w.product_id
		WHERE w.status = 'approved' AND w.total_cost > 3 AND p.shopping_location_id IS NOT NULL AND strftime('%Y-%m', w.created_timestamp) = strftime('%Y-%m', 'now', 'localtime')
		GROUP BY w.branch_id ORDER BY w.branch_id LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
	$ins = $db->prepare("INSERT INTO supplier_returns (supplier_id, branch_id, product_id, case_number, amount, qu_id, unit_cost, total_loss_cost, return_reason, incident_type, credit_requested_amount, credit_recovered_amount, loss_unrecovered_amount, status, notes, user_id, created_timestamp)
		VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'damaged', ?, 0, ?, 'pending_credit', ?, 1, ?)");
	$cred = $db->prepare("UPDATE supplier_returns SET credit_recovered_amount = :rec, loss_unrecovered_amount = MAX(0, total_loss_cost - :rec), status = 'credit_recovered',
		resolved_timestamp = :ts WHERE id = :id");
	$n = 0;
	foreach ($ws as $k => $w)
	{
		$next = intval($db->query('SELECT IFNULL(MAX(id), 0) + 1 FROM supplier_returns')->fetchColumn());
		$cost = round(floatval($w['total_cost']), 2);
		$at = date('Y-m-d H:i:s', strtotime('-' . (1 + $k % 6) . ' days'));
		$ins->execute([$w['shopping_location_id'], $w['branch_id'], $w['product_id'], 'RCL-' . date('Y') . '-' . str_pad((string) $next, 4, '0', STR_PAD_LEFT), $w['amount'], $w['qu_id_stock'],
			round($cost / max(0.001, floatval($w['amount'])), 4), $cost, 'Producto recibido con defecto de origen (merma #' . $w['id'] . ')', $cost, $cost, 'Reclamo con fotos y acta de merma', $at]);
		if ($k % 2 === 0)
		{
			$cred->execute([':rec' => round($cost * 0.9, 2), ':ts' => date('Y-m-d H:i:s', strtotime($at) + 86400), ':id' => intval($db->lastInsertId())]);
		}
		$n++;
	}
	return $n;
});

// 7) Traslados del mes entre sucursales: recibidos completos, con faltante (diferencia valorizada) y uno en tránsito
$step('demo_fill_transfers_' . date('Ym'), function () use ($db)
{
	$svc = Grocy\Services\StockTransferService::GetInstance();
	$pairs = [[1, 2], [3, 4], [5, 6], [7, 8], [9, 10]];
	$n = 0;
	foreach ($pairs as $k => [$fb, $tb])
	{
		$from = intval($db->query("SELECT l.id FROM locations l JOIN stock s ON s.location_id = l.id WHERE l.branch_id = $fb AND l.active = 1 AND IFNULL(l.is_transit, 0) = 0 GROUP BY l.id ORDER BY SUM(s.amount) DESC LIMIT 1")->fetchColumn());
		$to = intval($db->query("SELECT id FROM locations WHERE branch_id = $tb AND active = 1 AND IFNULL(is_transit, 0) = 0
			AND IFNULL(storage_class, '') = (SELECT IFNULL(storage_class, '') FROM locations WHERE id = $from) ORDER BY id LIMIT 1")->fetchColumn());
		$prods = $db->query("SELECT s.product_id FROM stock s JOIN products p ON p.id = s.product_id WHERE s.location_id = $from AND p.active = 1 AND IFNULL(p.device_control, 0) = 0
			AND s.stock_id NOT IN (SELECT stock_id FROM quarantine_items WHERE status = 'in_quarantine') GROUP BY s.product_id HAVING SUM(s.amount) >= 12 ORDER BY (s.product_id * 7 + $k) % 23 LIMIT 3")->fetchAll(PDO::FETCH_COLUMN);
		if (!$from || !$to || count($prods) < 2)
		{
			continue;
		}
		$items = array_map(fn($p) => ['product_id' => intval($p), 'amount' => 6], $prods);
		$t = $svc->CreateTransfer(['from_location_id' => $from, 'to_location_id' => $to, 'items' => $items, 'notes' => 'Reabastecimiento entre sucursales'], 1);
		$n++;
		if ($k === 4)
		{
			continue; // queda en tránsito
		}
		// Faltante de 1 unidad en la primera línea en dos de cada cuatro
		$recv = array_map(fn($it, $j) => ['product_id' => $it['product_id'], 'received_amount' => $j === 0 && $k % 2 === 1 ? 5 : 6], $items, array_keys($items));
		$svc->ReceiveTransfer(intval($t['id']), ['items' => $recv, 'notes' => $k % 2 ? 'Falta 1 unidad, caja abierta en el camión' : 'Recibido conforme'], 1);
	}
	return $n;
});

// 7b) Doral Mercado (sucursal 11) arranca sin stock: abastecimiento inicial desde Doral (9) u otra sucursal por clase de almacenamiento
$step('demo_fill_stock_branch11', function () use ($db)
{
	$svc = Grocy\Services\StockTransferService::GetInstance();
	$n = 0;
	foreach ($db->query("SELECT id, storage_class FROM locations WHERE branch_id = 11 AND active = 1 AND IFNULL(is_transit, 0) = 0 ORDER BY id")->fetchAll(PDO::FETCH_ASSOC) as $to)
	{
		if ($db->query('SELECT IFNULL(SUM(amount), 0) FROM stock WHERE location_id = ' . intval($to['id']))->fetchColumn() > 0)
		{
			continue;
		}
		$cls = $db->quote((string) $to['storage_class']);
		// El tránsito es de clase ambiente: solo pasan productos sin clase o de la clase del destino que el tránsito acepte
		$from = intval($db->query("SELECT l.id FROM locations l JOIN stock s ON s.location_id = l.id JOIN products p ON p.id = s.product_id
			WHERE l.branch_id IS NOT NULL AND l.branch_id <> 11 AND l.active = 1 AND IFNULL(l.is_transit, 0) = 0 AND IFNULL(p.storage_class, '') IN ('', $cls)
			AND IFNULL(p.storage_class, '') IN ('', (SELECT IFNULL(storage_class, '') FROM locations WHERE is_transit = 1 LIMIT 1))
			GROUP BY l.id HAVING COUNT(DISTINCT s.product_id) >= 3 ORDER BY l.branch_id = 9 DESC, IFNULL(l.storage_class, '') = $cls DESC, SUM(s.amount) DESC LIMIT 1")->fetchColumn());
		if (!$from)
		{
			continue;
		}
		$prods = $db->query("SELECT s.product_id FROM stock s JOIN products p ON p.id = s.product_id WHERE s.location_id = $from AND p.active = 1 AND IFNULL(p.device_control, 0) = 0 AND IFNULL(p.storage_class, '') IN ('', $cls) AND IFNULL(p.storage_class, '') IN ('', (SELECT IFNULL(storage_class, '') FROM locations WHERE is_transit = 1 LIMIT 1))
			AND s.stock_id NOT IN (SELECT stock_id FROM quarantine_items WHERE status = 'in_quarantine') GROUP BY s.product_id HAVING SUM(s.amount) >= 10 ORDER BY IFNULL(p.storage_class, '') = '' DESC, SUM(s.amount) DESC LIMIT 6")->fetchAll(PDO::FETCH_COLUMN);
		if (!$prods)
		{
			continue;
		}
		$items = array_map(fn($p) => ['product_id' => intval($p), 'amount' => 8], $prods);
		$t = $svc->CreateTransfer(['from_location_id' => $from, 'to_location_id' => intval($to['id']), 'items' => $items, 'notes' => 'Abastecimiento inicial Doral Mercado'], 1);
		$svc->ReceiveTransfer(intval($t['id']), ['items' => array_map(fn($it) => ['product_id' => $it['product_id'], 'received_amount' => 8], $items), 'notes' => 'Recibido conforme'], 1);
		$n++;
	}
	return $n;
});

// 7c) Mermas del mes en departamentos no perecederos (avería en manipulación): Hogar, Cuidado Personal, Alimentos,
// Bebidas y Accesorios; aprobadas por otro administrador (segregación)
$step('demo_fill_waste_nonfood_' . date('Ym'), function () use ($db)
{
	$ws = Grocy\Services\WasteService::GetInstance();
	$approver = intval($db->query("SELECT user_id FROM user_permissions_resolved WHERE permission_name = 'ADMIN' AND user_id <> 1 ORDER BY user_id LIMIT 1")->fetchColumn());
	$n = 0;
	foreach (['Hogar', 'Cuidado Personal', 'Alimentos', 'Bebidas', 'Accesorios'] as $k => $dept)
	{
		$c = $db->query("SELECT p.id, l.branch_id FROM products p JOIN stock s ON s.product_id = p.id JOIN locations l ON l.id = s.location_id
			WHERE p.active = 1 AND IFNULL(p.device_control, 0) = 0 AND p.department = " . $db->quote($dept) . " AND l.branch_id BETWEEN 1 AND 10 AND IFNULL(l.is_transit, 0) = 0
			AND s.stock_id NOT IN (SELECT stock_id FROM quarantine_items WHERE status = 'in_quarantine')
			GROUP BY p.id, l.branch_id HAVING SUM(s.amount) >= 4 ORDER BY (p.id * 7 + l.branch_id) % 19 LIMIT 2")->fetchAll(PDO::FETCH_ASSOC);
		foreach ($c as $j => $r)
		{
			$ws->ReportWaste(intval($r['id']), 1, $j ? 35 : 34, intval($r['branch_id']), null, 'default', $j ? 'Empaque golpeado al reponer góndola' : 'Unidad dañada en manipulación de bodega',
				null, null, null, null, null, null, null, 1, false, true);
			$id = intval($db->query('SELECT MAX(id) FROM waste_records')->fetchColumn());
			if ($approver && $db->query("SELECT status FROM waste_records WHERE id = $id")->fetchColumn() === 'pending_approval')
			{
				$ws->ApproveWaste($id, $approver);
			}
			$n++;
		}
	}
	return $n;
});

// 7d) Informe ejecutivo de pérdidas del día anterior si el programador quedó atrasado (incluye reclamos y mermas de ejemplo)
$step('demo_fill_loss_report_' . date('Ymd'), function () use ($db)
{
	if ($db->query("SELECT COUNT(*) FROM dcr_executive_reports WHERE period_type = 'daily' AND status = 'publicado' AND report_date = date('now', 'localtime')")->fetchColumn())
	{
		return 0;
	}
	return Grocy\Services\LossExecutiveReportService::GetInstance()->Generate('daily', 'catch_up', null, date('Y-m-d'))['code'];
});

// 8) Tareas de piso con plazo vencido: el personal las cierra como en una tienda real (≈ 75 % a tiempo, 10 % con
// retraso, 5 % en curso y el resto queda atrasado). Corre en cada relleno; solo toca tareas pendientes ya vencidas.
$step('demo_fill_tasks_' . date('YmdH'), function () use ($db)
{
	$now = date('Y-m-d H:i:s');
	$rows = $db->query("SELECT t.id, t.due_time, t.branch_id, IFNULL(t.assigned_user_id, (SELECT user_id FROM user_branches WHERE branch_id = t.branch_id ORDER BY user_id LIMIT 1)) uid
		FROM workforce_store_tasks t WHERE t.status = 'pending' AND t.due_time < " . $db->quote($now))->fetchAll(PDO::FETCH_ASSOC);
	$done = $db->prepare("UPDATE workforce_store_tasks SET status = 'completed', completed_at = ?, completed_by_user_id = ? WHERE id = ? AND status = 'pending'");
	$prog = $db->prepare("UPDATE workforce_store_tasks SET status = 'in_progress' WHERE id = ? AND status = 'pending'");
	$n = 0;
	foreach ($rows as $r)
	{
		$h = (intval($r['id']) * 37) % 20;
		$due = strtotime($r['due_time']);
		if ($h < 15)
		{
			$done->execute([date('Y-m-d H:i:s', $due - 600 - ($h * 97) % 3000), $r['uid'] ?: 1, $r['id']]);
		}
		elseif ($h < 17)
		{
			$at = min(time(), $due + 900 + ($h * 131) % 5400);
			$done->execute([date('Y-m-d H:i:s', $at), $r['uid'] ?: 1, $r['id']]);
		}
		elseif ($h === 17)
		{
			$prog->execute([$r['id']]);
		}
		else
		{
			continue;
		}
		$n++;
	}
	return $n;
});

// 12) Stock & Movimientos: traslado con recepción parcial (saldo en tránsito) y conteos con diferencia: uno ajustado y aprobado, otro por aprobar
$step('demo_fill_stock_board_v1', function () use ($db)
{
	$res = [];
	$tsvc = Grocy\Services\StockTransferService::GetInstance();
	$from = intval($db->query("SELECT l.id FROM locations l JOIN stock s ON s.location_id = l.id JOIN products p ON p.id = s.product_id WHERE l.branch_id = 3 AND l.active = 1 AND IFNULL(l.is_transit, 0) = 0
		AND IFNULL(p.device_control, 0) = 0 AND IFNULL(p.is_serialized, 0) = 0 GROUP BY l.id ORDER BY SUM(s.amount) DESC LIMIT 1")->fetchColumn());
	$to = intval($db->query("SELECT id FROM locations WHERE branch_id = 1 AND active = 1 AND IFNULL(is_transit, 0) = 0 AND IFNULL(storage_class, '') = (SELECT IFNULL(storage_class, '') FROM locations WHERE id = $from) ORDER BY id LIMIT 1")->fetchColumn());
	$prods = $db->query("SELECT s.product_id FROM stock s JOIN products p ON p.id = s.product_id WHERE s.location_id = $from AND p.active = 1 AND IFNULL(p.device_control, 0) = 0 AND IFNULL(p.is_serialized, 0) = 0
		AND s.stock_id NOT IN (SELECT stock_id FROM quarantine_items WHERE status = 'in_quarantine') GROUP BY s.product_id HAVING SUM(s.amount) >= 20 ORDER BY SUM(s.amount) DESC LIMIT 2")->fetchAll(PDO::FETCH_COLUMN);
	if ($from && $to && count($prods) === 2)
	{
		$t = $tsvc->CreateTransfer(['from_location_id' => $from, 'to_location_id' => $to, 'items' => [['product_id' => intval($prods[0]), 'amount' => 12], ['product_id' => intval($prods[1]), 'amount' => 10]], 'notes' => 'Reabastecimiento Sunny Isles → Hallandale'], 1);
		$tsvc->ReceiveTransfer(intval($t['id']), ['items' => [['product_id' => intval($prods[0]), 'received_amount' => 8], ['product_id' => intval($prods[1]), 'received_amount' => 4]], 'partial' => 1, 'notes' => 'Primer camión; el resto llega en el segundo'], 1);
		$res['partial_transfer'] = $t['id'];
	}
	$cc = Grocy\Services\CycleCountService::GetInstance();
	$pick = fn(string $not) => intval($db->query("SELECT s.product_id FROM stock s JOIN locations l ON l.id = s.location_id JOIN products p ON p.id = s.product_id WHERE l.branch_id = 1 AND p.active = 1
		AND IFNULL(p.is_serialized, 0) = 0 AND p.id NOT IN ($not) AND NOT EXISTS (SELECT 1 FROM stock_cycle_counts c WHERE c.product_id = s.product_id AND c.branch_id = 1 AND c.status IN ('pending', 'discrepancy'))
		GROUP BY s.product_id HAVING SUM(s.amount) >= 10 ORDER BY (s.product_id * 13) % 97 LIMIT 1")->fetchColumn());
	$exclude = implode(',', array_map('intval', $prods ?: [0]));
	foreach (['adjusted' => 3, 'pending' => 1] as $kind => $missing)
	{
		$pid = $pick($exclude);
		if ($pid <= 0)
		{
			continue;
		}
		$exclude .= ',' . $pid;
		$cc->ScheduleForProducts(1, [$pid], 'Conteo cíclico de góndola', 1);
		$cid = intval($db->query("SELECT id FROM stock_cycle_counts WHERE product_id = $pid AND branch_id = 1 AND status = 'pending' ORDER BY id DESC LIMIT 1")->fetchColumn());
		$theo = floatval($db->query("SELECT IFNULL(SUM(amount), 0) FROM stock WHERE product_id = $pid AND location_id = (SELECT location_id FROM stock_cycle_counts WHERE id = $cid)")->fetchColumn());
		$cc->Execute($cid, max(0, $theo - $missing), $kind === 'adjusted' ? 'Faltan 3 unidades en góndola; reconteo confirmado' : 'Falta 1 unidad; espera aprobación', 1);
		if ($kind === 'adjusted')
		{
			$cc->ApproveAdjustment($cid, 2); // segregación: aprueba otro usuario
		}
		$res['count_' . $kind] = $cid;
	}
	return $res;
});

echo date('Y-m-d H:i:s') . ' demo: relleno ' . json_encode($out, JSON_UNESCAPED_UNICODE), "\n";
