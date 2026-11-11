<?php
/**
 * Ejemplos del día para Supervisión y Control (modo demo): ventas con descuento comercial y devoluciones del POS
 * hechas con los servicios reales (stock FEFO, libro, eventos). Idempotente: client_ref / return_ref fijos por fecha.
 * Lo incluye bin/dairy_catchup.php tras cada relleno demo; también: php bin/dairy_demo_examples.php
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
// Solo con la tienda abierta (las ventas de ejemplo quedan con la hora actual)
if (GROCY_MODE !== 'demo' || intval(date('G')) < 9 || intval(date('G')) > 20)
{
	return;
}
$db = Grocy\Services\DatabaseService::GetInstance()->GetDbConnectionRaw();
$day = date('Ymd');
$out = ['discount_sales' => 0, 'returns' => 0, 'errors' => []];

// 1) Una venta con descuento por sucursal (6 sucursales), con productos de stock libre en esa sucursal
$branches = $db->query('SELECT id FROM branches ORDER BY id LIMIT 6')->fetchAll(PDO::FETCH_COLUMN);
foreach ($branches as $i => $b)
{
	$ref = "demo-dsc-$day-$b-0000";
	if ($db->query('SELECT id FROM sales WHERE client_ref = ' . $db->quote($ref))->fetchColumn())
	{
		continue;
	}
	$prods = $db->query("SELECT p.id, p.sale_price FROM products p JOIN stock s ON s.product_id = p.id
		JOIN locations l ON l.id = s.location_id
		WHERE p.active = 1 AND p.sale_price > 2 AND IFNULL(p.device_control, 0) = 0 AND IFNULL(l.branch_id, 0) = " . intval($b) . "
		GROUP BY p.id HAVING SUM(s.amount) >= 5 ORDER BY (p.id * 37 + $i * 11) % 101 LIMIT 3")->fetchAll(PDO::FETCH_ASSOC);
	if (count($prods) < 2)
	{
		$out['errors'][] = "sucursal $b sin stock libre";
		continue;
	}
	$items = array_map(fn($p) => ['product_id' => intval($p['id']), 'amount' => 1 + ($p['id'] % 2), 'unit_price' => floatval($p['sale_price'])], $prods);
	$sub = array_sum(array_map(fn($it) => $it['amount'] * $it['unit_price'], $items));
	try
	{
		Grocy\Services\SalesService::GetInstance()->CreateSale([
			'branch_id' => intval($b), 'customer_id' => 1, 'payment_method' => $i % 2 ? 'card' : 'cash',
			'notes' => 'Descuento comercial ' . [10, 5, 15, 8, 12, 10][$i] . '% (cliente frecuente)',
			'items' => $items, 'ticket_discount_amount' => round($sub * [10, 5, 15, 8, 12, 10][$i] / 100, 2),
			'client_ref' => $ref, 'event_uuid' => 'sale-confirmed-' . $ref,
		]);
		$out['discount_sales']++;
	}
	catch (Throwable $e)
	{
		$out['errors'][] = "venta $b: " . $e->getMessage();
	}
}

// 2) Tres devoluciones del POS sobre ventas de hoy (motivos distintos), van a inspección en /returns
$reasons = [['defectuoso', 'abierto', 'credit_note'], ['no_deseado', 'sin_abrir', 'credit_note'], ['mal_estado', 'danado', 'exchange']];
$sales = $db->query("SELECT s.id FROM sales s WHERE s.status = 'completed' AND date(s.created_timestamp) = date('now', 'localtime')
	AND time(s.created_timestamp) <= time('now', 'localtime') AND IFNULL(s.client_ref, '') NOT LIKE 'demo-dsc-%'
	AND s.sale_number NOT LIKE 'VTA-2026-99%' ORDER BY s.id LIMIT 40")->fetchAll(PDO::FETCH_COLUMN);
$svc = Grocy\Services\PosReturnService::GetInstance();
$n = 0;
foreach ($sales as $sid)
{
	if ($n >= 3)
	{
		break;
	}
	$ref = "demo-ret-$day-$n-000000";
	if ($db->query('SELECT id FROM daily_pos_returns WHERE return_ref = ' . $db->quote($ref))->fetchColumn())
	{
		$n++;
		continue;
	}
	try
	{
		$E = $svc->Eligibility(intval($sid));
		if (!$E['eligible'] || !$E['lines'])
		{
			continue;
		}
		$l = $E['lines'][0];
		[$reason, $cond, $res] = $reasons[$n];
		$svc->Process(['return_ref' => $ref, 'sale_id' => intval($sid), 'register' => 'Caja 1', 'resolution' => $res,
			'lines' => [['sale_item_id' => $l['sale_item_id'], 'quantity' => 1, 'reason' => $reason, 'condition' => $cond]],
			'notes' => 'Ejemplo del día'], 1);
		$out['returns']++;
		$n++;
	}
	catch (Throwable $e)
	{
		$out['errors'][] = "devolución $sid: " . $e->getMessage();
	}
}
// 3) Alertas de hoy tomadas por su responsable: 6 en ejecución y 3 en investigación (dentro del plazo)
$have = intval($db->query("SELECT COUNT(*) FROM dairy_alerts WHERE status IN ('in_progress', 'investigating') AND date(created_at) = date('now', 'localtime')")->fetchColumn());
$al = Grocy\Services\AlertCenterService::GetInstance();
$ids = $db->query("SELECT id, IFNULL(owner_user_id, 1) FROM dairy_alerts WHERE status IN ('open', 'assigned') AND date(created_at) = date('now', 'localtime')
	AND (due_at IS NULL OR due_at > datetime('now', 'localtime')) ORDER BY priority, id LIMIT " . max(0, 9 - $have))->fetchAll(PDO::FETCH_NUM);
$out['alerts'] = 0;
foreach ($ids as $k => [$id, $owner])
{
	try
	{
		$k < 6 ? $al->Start(intval($id), intval($owner)) : $al->Investigate(intval($id), 'Revisando con el encargado de la sucursal', intval($owner));
		$out['alerts']++;
	}
	catch (Throwable $e)
	{
		$out['errors'][] = "alerta $id: " . $e->getMessage();
	}
}
// 4) Mermas del día (la semilla registra las roturas al cerrar el día): una rotura/vencido en 4 sucursales
$out['waste'] = 0;
if (!$db->query("SELECT COUNT(*) FROM waste_records WHERE date(created_timestamp) = date('now', 'localtime') AND status IN ('approved', 'pending_approval')")->fetchColumn())
{
	foreach (array_slice($branches, 0, 4) as $i => $b)
	{
		$pid = $db->query("SELECT p.id FROM products p JOIN stock s ON s.product_id = p.id JOIN locations l ON l.id = s.location_id
			WHERE p.active = 1 AND IFNULL(p.device_control, 0) = 0 AND p.id IN (30, 34, 37, 39, 50, 15, 13) AND l.branch_id = " . intval($b) . "
			GROUP BY p.id HAVING SUM(s.amount) >= 6 ORDER BY (p.id + $i * 7) % 13 LIMIT 1")->fetchColumn();
		if (!$pid)
		{
			continue;
		}
		try
		{
			Grocy\Services\WasteService::GetInstance()->ReportWaste(intval($pid), 1 + $i % 2, [35, 34][$i % 2], intval($b), null, 'default', 'Registro en piso de venta',
				null, null, null, null, null, null, null, 1, false, true);
			$out['waste']++;
		}
		catch (Throwable $e)
		{
			$out['errors'][] = "merma $b: " . $e->getMessage();
		}
	}
}

// 4b) Mermas de perecederos de mayor costo hasta ~1 % de la venta neta del día (vencidos y producto dañado); las
// pendientes de aprobación se aprueban como lo haría el supervisor
$net = floatval($db->query("SELECT IFNULL(SUM(total_amount - tax_amount), 0) FROM sales WHERE status = 'completed' AND date(created_timestamp) = date('now', 'localtime')")->fetchColumn());
$cost = fn() => floatval($db->query("SELECT IFNULL(SUM(total_cost), 0) FROM waste_records WHERE date(created_timestamp) = date('now', 'localtime') AND status IN ('approved', 'pending_approval')")->fetchColumn());
$cands = $db->query("SELECT p.id, l.branch_id, MAX(s.price) AS c FROM products p JOIN stock s ON s.product_id = p.id JOIN locations l ON l.id = s.location_id
	WHERE p.active = 1 AND IFNULL(p.device_control, 0) = 0 AND p.department IN ('Carnes', 'Carnes y aves', 'Lácteos', 'Lácteos y huevos', 'Pescadería', 'Panadería', 'Frutas y verduras')
	AND s.price BETWEEN 3 AND 40 GROUP BY p.id, l.branch_id HAVING SUM(s.amount) >= 8 ORDER BY (p.id * 13 + l.branch_id * 7) % 17")->fetchAll(PDO::FETCH_ASSOC);
$used = [];
foreach ($cands as $k => $c)
{
	if ($cost() >= $net * 0.01 || $k > 30)
	{
		break;
	}
	if (isset($used[$c['branch_id']]) && $used[$c['branch_id']] >= 2)
	{
		continue;
	}
	try
	{
		Grocy\Services\WasteService::GetInstance()->ReportWaste(intval($c['id']), 2 + $k % 3, [35, 34][$k % 2], intval($c['branch_id']), null, 'default',
			$k % 2 ? 'Empaque roto en góndola' : 'Producto vencido retirado en revisión de la mañana', null, null, null, null, null, null, null, 1, false, true);
		$used[$c['branch_id']] = ($used[$c['branch_id']] ?? 0) + 1;
		$out['waste']++;
	}
	catch (Throwable $e)
	{
		$out['errors'][] = "merma {$c['id']}: " . $e->getMessage();
	}
}
foreach ($db->query("SELECT id FROM waste_records WHERE status = 'pending_approval' AND date(created_timestamp) = date('now', 'localtime') ORDER BY id LIMIT 20")->fetchAll(PDO::FETCH_COLUMN) as $k => $wid)
{
	// Una queda pendiente como ejemplo de aprobación por hacer
	if ($k === 0)
	{
		continue;
	}
	try
	{
		$approver = intval($db->query("SELECT user_id FROM user_permissions_resolved WHERE permission_name = 'ADMIN' AND user_id <> 1 ORDER BY user_id LIMIT 1")->fetchColumn());
		Grocy\Services\WasteService::GetInstance()->ApproveWaste(intval($wid), $approver);
	}
	catch (Throwable $e)
	{
		$out['errors'][] = "aprobar $wid: " . $e->getMessage();
	}
}
$out['waste_pct'] = $net > 0 ? round($cost() / $net * 100, 2) : null;

// 5) Alertas críticas activas sin responsable: se asignan a un gestor de su sucursal
$out['assigned'] = 0;
// Una vez por día (marca en daily_seed_runs)
$mark = 'daily_examples_assign_' . $day;
foreach ($db->query("SELECT COUNT(*) FROM daily_seed_runs WHERE code = " . $db->quote($mark))->fetchColumn() ? [] : $db->query("SELECT id, branch_id FROM dairy_alerts WHERE status = 'open' AND owner_user_id IS NULL AND priority IN ('P1', 'P2') ORDER BY priority, id LIMIT 12")->fetchAll(PDO::FETCH_NUM) as [$id, $b])
{
	$mgr = $al->Managers($b ? intval($b) : null);
	$local = $b ? $db->query('SELECT user_id FROM user_branches WHERE branch_id = ' . intval($b))->fetchAll(PDO::FETCH_COLUMN) : [];
	$pick = array_values(array_intersect($mgr, array_map('intval', $local)));
	$owner = $pick[0] ?? ($mgr[0] ?? 1);
	try
	{
		$al->Assign(intval($id), $owner, 1);
		$out['assigned']++;
	}
	catch (Throwable $e)
	{
		$out['errors'][] = "asignar $id: " . $e->getMessage();
	}
}
$db->prepare('INSERT OR IGNORE INTO daily_seed_runs (code, run_at) VALUES (?, ?)')->execute([$mark, date('Y-m-d H:i:s')]);
echo date('Y-m-d H:i:s') . ' demo: ejemplos del día ' . json_encode($out, JSON_UNESCAPED_UNICODE), "\n";
