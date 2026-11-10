<?php

// DAILY — Detalle del archivo histórico comercial (empresa de ejemplo).
//
// daily_sales_archive_mix: mezcla de productos de cada sucursal × departamento (participación sobre la venta del
// departamento, precio unitario). SalesReportsService reparte cada celda del archivo con esta mezcla para dar detalle
// por producto; la suma por producto es exactamente la venta de la celda.
// daily_sales_archive_hours: participación de cada hora en la venta del día (curva por hora de los días archivados).
// Metas por departamento de cada sucursal: meta del departamento en la red × participación de la sucursal en ese
// departamento (daily_department_sales_targets con branch_id > 0).
// Solo con archivo de ejemplo (source = 'demo'); en una instalación real las tablas quedan vacías.

$db = \Grocy\Services\DatabaseService::GetInstance()->GetDbConnectionRaw();

$db->exec("CREATE TABLE IF NOT EXISTS daily_sales_archive_mix (
	branch_id INTEGER NOT NULL,
	department TEXT NOT NULL,
	product_id INTEGER NOT NULL,
	share REAL NOT NULL,
	unit_price REAL NOT NULL,
	PRIMARY KEY (branch_id, department, product_id)
)");
$db->exec("CREATE TABLE IF NOT EXISTS daily_sales_archive_hours (
	hour INTEGER PRIMARY KEY,
	share REAL NOT NULL
)");

$hasDemo = (int) $db->query("SELECT COUNT(*) FROM daily_sales_archive WHERE source = 'demo'")->fetchColumn() > 0;
if (!$hasDemo || (int) $db->query('SELECT COUNT(*) FROM daily_sales_archive_mix')->fetchColumn() > 0)
{
	return;
}

$cutover = date('Y-m-d', strtotime($db->query('SELECT MAX(day) FROM daily_sales_archive')->fetchColumn() . ' +1 day'));
$refTo = $db->query("SELECT date(MAX(created_timestamp)) FROM sales WHERE status = 'completed'")->fetchColumn();
$refFrom = date('Y-m-d', strtotime("$refTo -27 days"));
$case = [];
foreach (\Grocy\Services\DailyMetricsService::DEPARTMENTS as $k => $d)
{
	if ($d[2] !== null)
	{
		$case[] = 'WHEN p.department IN (' . implode(',', array_map([$db, 'quote'], $d[2])) . ') THEN ' . $db->quote($k);
	}
}
$deptCase = 'CASE ' . implode(' ', $case) . " ELSE 'otros' END";
$range = "s.status = 'completed' AND s.created_timestamp >= '" . max($refFrom, $cutover) . " 00:00:00' AND s.created_timestamp < '" . date('Y-m-d', strtotime("$refTo +1 day")) . " 00:00:00'";
$net = "si.subtotal * CASE WHEN t.ls > 0 THEN (s.total_amount - s.tax_amount) / t.ls ELSE 1 END";
$with = "WITH t AS (SELECT si.sale_id, SUM(si.subtotal) AS ls FROM sale_items si JOIN sales s ON s.id = si.sale_id WHERE $range GROUP BY si.sale_id)";

// Mezcla: los 15 productos con más venta de cada sucursal × departamento
$rows = $db->query("$with SELECT s.branch_id AS b, $deptCase AS dept, p.id AS pid, SUM($net) AS net, SUM(si.amount) AS units
	FROM sale_items si JOIN sales s ON s.id = si.sale_id JOIN t ON t.sale_id = s.id JOIN products p ON p.id = si.product_id
	WHERE $range GROUP BY b, dept, pid HAVING net > 0 ORDER BY b, dept, net DESC")->fetchAll(\PDO::FETCH_ASSOC);
$cells = [];
foreach ($rows as $r)
{
	$k = $r['b'] . '|' . $r['dept'];
	if (count($cells[$k] ?? []) < 15)
	{
		$cells[$k][] = $r;
	}
}
// Celdas del archivo sin mezcla propia: se usa la mezcla del departamento en toda la red
$networkMix = [];
foreach ($db->query("$with SELECT $deptCase AS dept, p.id AS pid, SUM($net) AS net, SUM(si.amount) AS units
	FROM sale_items si JOIN sales s ON s.id = si.sale_id JOIN t ON t.sale_id = s.id JOIN products p ON p.id = si.product_id
	WHERE $range GROUP BY dept, pid HAVING net > 0 ORDER BY dept, net DESC")->fetchAll(\PDO::FETCH_ASSOC) as $r)
{
	if (count($networkMix[$r['dept']] ?? []) < 15)
	{
		$networkMix[$r['dept']][] = $r;
	}
}

$db->beginTransaction();
$ins = $db->prepare('INSERT OR REPLACE INTO daily_sales_archive_mix (branch_id, department, product_id, share, unit_price) VALUES (:b, :d, :p, :s, :u)');
foreach ($db->query('SELECT DISTINCT branch_id, department FROM daily_sales_archive')->fetchAll(\PDO::FETCH_ASSOC) as $c)
{
	$list = $cells[$c['branch_id'] . '|' . $c['department']] ?? ($networkMix[$c['department']] ?? []);
	$sum = array_sum(array_map(fn($r) => floatval($r['net']), $list));
	foreach ($list as $r)
	{
		$ins->execute([':b' => $c['branch_id'], ':d' => $c['department'], ':p' => $r['pid'], ':s' => floatval($r['net']) / $sum,
			':u' => floatval($r['units']) > 0 ? max(0.05, floatval($r['net']) / floatval($r['units'])) : 10]);
	}
}

// Unidades del archivo coherentes con la mezcla (Σ unidades por producto = unidades de la celda)
$db->exec('UPDATE daily_sales_archive SET units = ROUND(net * IFNULL((SELECT SUM(m.share / m.unit_price) FROM daily_sales_archive_mix m
	WHERE m.branch_id = daily_sales_archive.branch_id AND m.department = daily_sales_archive.department), 0), 2)');

// Curva por hora de referencia
$hours = $db->query("$with SELECT CAST(strftime('%H', s.created_timestamp) AS INTEGER) AS h, SUM($net) AS net
	FROM sale_items si JOIN sales s ON s.id = si.sale_id JOIN t ON t.sale_id = s.id WHERE $range GROUP BY h")->fetchAll(\PDO::FETCH_ASSOC);
$hTotal = array_sum(array_map(fn($r) => floatval($r['net']), $hours));
$insH = $db->prepare('INSERT OR REPLACE INTO daily_sales_archive_hours (hour, share) VALUES (:h, :s)');
foreach ($hours as $r)
{
	if ($hTotal > 0 && floatval($r['net']) > 0)
	{
		$insH->execute([':h' => intval($r['h']), ':s' => floatval($r['net']) / $hTotal]);
	}
}

// Metas por departamento de cada sucursal: meta de la red × participación de la sucursal en el departamento
$share = [];
$deptTotal = [];
foreach ($db->query('SELECT branch_id, department, SUM(net) AS net FROM daily_sales_archive GROUP BY branch_id, department')->fetchAll(\PDO::FETCH_ASSOC) as $r)
{
	$share[$r['department']][intval($r['branch_id'])] = floatval($r['net']);
	$deptTotal[$r['department']] = ($deptTotal[$r['department']] ?? 0) + floatval($r['net']);
}
$insT = $db->prepare('INSERT OR IGNORE INTO daily_department_sales_targets (branch_id, period, department, sales_target, notes) VALUES (:b, :p, :d, :t, :n)');
foreach ($db->query('SELECT period, department, sales_target FROM daily_department_sales_targets WHERE branch_id = 0')->fetchAll(\PDO::FETCH_ASSOC) as $t)
{
	foreach ($share[$t['department']] ?? [] as $b => $v)
	{
		if ($deptTotal[$t['department']] > 0)
		{
			$insT->execute([':b' => $b, ':p' => $t['period'], ':d' => $t['department'], ':t' => round(floatval($t['sales_target']) * $v / $deptTotal[$t['department']] / 10) * 10,
				':n' => 'Meta de la empresa de ejemplo: meta del departamento × participación de la sucursal']);
		}
	}
}
$db->commit();
