<?php

// DAILY — Archivo histórico comercial y metas de venta por departamento (informes de Ventas y Rentabilidad).
//
// daily_sales_archive: ventas consolidadas por día × sucursal × departamento anteriores al inicio de las ventas
// transaccionales (p. ej. importadas del POS anterior). No tiene detalle por ticket, producto ni hora y no mueve
// inventario: solo lo leen los informes comerciales (SalesReportsService), nunca los módulos que concilian ventas
// contra existencias. tickets = tickets con el departamento; branch_tickets = tickets de la sucursal en el día
// (informado en una sola fila por día y sucursal para poder sumarlo).
//
// daily_department_sales_targets: meta mensual de ventas netas por departamento (clave de DailyMetricsService::DEPARTMENTS)
// para la red (branch_id = 0) o una sucursal.
//
// Empresa de ejemplo: si existe la semilla daily_company_v1 se generan 2024-01-01 → día previo a las ventas
// transaccionales con el perfil real de la demo (mezcla sucursal × departamento, margen, ticket y día de la semana),
// crecimiento anual y estacionalidad, más metas mensuales (sin sobrescribir metas existentes). Determinista.

$db = \Grocy\Services\DatabaseService::GetInstance()->GetDbConnectionRaw();

$db->exec("CREATE TABLE IF NOT EXISTS daily_sales_archive (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	day DATE NOT NULL,
	branch_id INTEGER NOT NULL,
	department TEXT NOT NULL,
	net REAL NOT NULL DEFAULT 0,
	cogs REAL NOT NULL DEFAULT 0,
	units REAL NOT NULL DEFAULT 0,
	tickets INTEGER NOT NULL DEFAULT 0,
	branch_tickets INTEGER NOT NULL DEFAULT 0,
	source TEXT NOT NULL DEFAULT 'import',
	UNIQUE (day, branch_id, department)
)");
$db->exec('CREATE INDEX IF NOT EXISTS ix_daily_sales_archive_branch_day ON daily_sales_archive (branch_id, day)');

$db->exec("CREATE TABLE IF NOT EXISTS daily_department_sales_targets (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	branch_id INTEGER NOT NULL DEFAULT 0,
	period TEXT NOT NULL,
	department TEXT NOT NULL,
	sales_target REAL NOT NULL,
	notes TEXT,
	updated_timestamp DATETIME DEFAULT (datetime('now', 'localtime')),
	UNIQUE (branch_id, period, department)
)");

$isDemo = (int) $db->query("SELECT COUNT(*) FROM sqlite_master WHERE type = 'table' AND name = 'daily_seed_runs'")->fetchColumn() > 0
	&& (int) $db->query("SELECT COUNT(*) FROM daily_seed_runs WHERE code = 'daily_company_v1'")->fetchColumn() > 0;
if (!$isDemo || (int) $db->query('SELECT COUNT(*) FROM daily_sales_archive')->fetchColumn() > 0)
{
	return;
}

// Inicio de las ventas transaccionales: primer día con al menos 20 tickets completados
$cutover = $db->query("SELECT d FROM (SELECT date(created_timestamp) AS d, COUNT(*) AS n FROM sales WHERE status = 'completed' GROUP BY d) WHERE n >= 20 ORDER BY d LIMIT 1")->fetchColumn();
$refTo = $db->query("SELECT date(MAX(created_timestamp)) FROM sales WHERE status = 'completed'")->fetchColumn();
if (!$cutover || !$refTo)
{
	return;
}
$refFrom = date('Y-m-d', strtotime("$refTo -27 days"));

// Departamento de cada producto (misma regla que DailyMetricsService::DeptSql)
$case = [];
foreach (\Grocy\Services\DailyMetricsService::DEPARTMENTS as $k => $d)
{
	if ($d[2] !== null)
	{
		$case[] = 'WHEN p.department IN (' . implode(',', array_map([$db, 'quote'], $d[2])) . ') THEN ' . $db->quote($k);
	}
}
$deptCase = 'CASE ' . implode(' ', $case) . " ELSE 'otros' END";
$range = "s.status = 'completed' AND s.created_timestamp >= '$refFrom 00:00:00' AND s.created_timestamp < '" . date('Y-m-d', strtotime("$refTo +1 day")) . " 00:00:00'";

// Perfil de referencia (últimos 28 días): venta, costo, unidades y tickets por sucursal × departamento
$profile = $db->query("WITH t AS (SELECT si.sale_id, SUM(si.subtotal) AS ls FROM sale_items si JOIN sales s ON s.id = si.sale_id WHERE $range GROUP BY si.sale_id)
	SELECT s.branch_id AS b, $deptCase AS dept,
		SUM(si.subtotal * CASE WHEN t.ls > 0 THEN (s.total_amount - s.tax_amount) / t.ls ELSE 1 END) AS net,
		SUM(si.total_cost) AS cogs, SUM(si.amount) AS units, COUNT(DISTINCT s.id) AS tickets
	FROM sale_items si JOIN sales s ON s.id = si.sale_id JOIN t ON t.sale_id = s.id JOIN products p ON p.id = si.product_id
	WHERE $range GROUP BY b, dept")->fetchAll(\PDO::FETCH_ASSOC);
$branchTickets = [];
foreach ($db->query("SELECT s.branch_id AS b, COUNT(*) AS n FROM sales s WHERE $range GROUP BY b")->fetchAll(\PDO::FETCH_ASSOC) as $r)
{
	$branchTickets[intval($r['b'])] = intval($r['n']);
}
$total = array_sum(array_map(fn($r) => floatval($r['net']), $profile));
if ($total <= 0)
{
	return;
}
$cells = [];
$branchNet = [];
foreach ($profile as $r)
{
	$net = floatval($r['net']);
	if ($net <= 0)
	{
		continue;
	}
	$b = intval($r['b']);
	$margin = $net > 0 && floatval($r['cogs']) <= $net * 3 ? 1 - floatval($r['cogs']) / $net : 0.38;
	$cells[] = ['b' => $b, 'dept' => $r['dept'], 'share' => $net / $total, 'margin' => max(0.12, min(0.65, $margin)),
		'unit_price' => floatval($r['units']) > 0 ? $net / floatval($r['units']) : 10.0, 'ticket_ratio' => $branchTickets[$b] > 0 ? intval($r['tickets']) / $branchTickets[$b] : 0.3];
	$branchNet[$b] = ($branchNet[$b] ?? 0) + $net;
}
$avgTicket = [];
foreach ($branchNet as $b => $n)
{
	$avgTicket[$b] = ($branchTickets[$b] ?? 0) > 0 ? $n / $branchTickets[$b] : 100.0;
}

// Nivel diario de la red en 2026 (perfil) y su evolución: 2024 ≈ 72 %, crecimiento lineal hasta 97 % al corte
$level = $total / 28;
$start = '2024-01-01';
$span = max(1, (strtotime($cutover) - strtotime($start)) / 86400);
$season = [0.88, 0.86, 0.94, 0.96, 1.0, 0.98, 1.0, 1.02, 0.97, 1.0, 1.08, 1.26];
$weekday = [1.0, 0.92, 0.9, 0.95, 0.98, 1.08, 1.17]; // domingo..sábado
$noise = function (string $key, float $amp): float {
	return 1 + (((crc32($key) % 2001) / 1000) - 1) * $amp;
};
$special = function (string $d): float {
	$md = substr($d, 5);
	if ($md === '12-24') return 1.45;
	if ($md === '12-31') return 1.25;
	if ($md === '01-01') return 0.55;
	if ($md === '12-25') return 0.6;
	// Viernes negro: cuarto viernes de noviembre
	if (substr($md, 0, 2) === '11' && date('w', strtotime($d)) === '5' && intval(substr($d, 8, 2)) >= 22 && intval(substr($d, 8, 2)) <= 28) return 1.65;
	return 1.0;
};
$dayNet = function (string $d) use ($level, $start, $span, $season, $weekday, $noise, $special): float {
	$t = (strtotime($d) - strtotime($start)) / 86400 / $span;
	return $level * (0.72 + 0.25 * $t) * $season[intval(substr($d, 5, 2)) - 1] * $weekday[intval(date('w', strtotime($d)))] * $special($d) * $noise('d' . $d, 0.07);
};

$ins = $db->prepare('INSERT OR IGNORE INTO daily_sales_archive (day, branch_id, department, net, cogs, units, tickets, branch_tickets, source)
	VALUES (:d, :b, :dept, :net, :cogs, :units, :t, :bt, :src)');
$monthNet = [];
$db->beginTransaction();
for ($d = $start; $d < $cutover; $d = date('Y-m-d', strtotime("$d +1 day")))
{
	$net = $dayNet($d);
	$first = [];
	$byBranch = [];
	foreach ($cells as $c)
	{
		$v = $net * $c['share'] * $noise("c$d|{$c['b']}|{$c['dept']}", 0.10);
		$byBranch[$c['b']] = ($byBranch[$c['b']] ?? 0) + $v;
	}
	foreach ($cells as $c)
	{
		$v = round($net * $c['share'] * $noise("c$d|{$c['b']}|{$c['dept']}", 0.10), 2);
		$bt = isset($first[$c['b']]) ? 0 : max(1, (int) round($byBranch[$c['b']] / $avgTicket[$c['b']]));
		$first[$c['b']] = true;
		$tk = max(1, (int) round(($byBranch[$c['b']] / $avgTicket[$c['b']]) * $c['ticket_ratio']));
		$m = max(0.1, $c['margin'] + (($noise("m$d|{$c['b']}|{$c['dept']}", 1) - 1) * 0.015));
		$ins->execute([':d' => $d, ':b' => $c['b'], ':dept' => $c['dept'], ':net' => $v, ':cogs' => round($v * (1 - $m), 2),
			':units' => round($v / max(0.5, $c['unit_price']), 2), ':t' => $tk, ':bt' => $bt, ':src' => 'demo']);
		$monthNet[substr($d, 0, 7)][$c['b']][$c['dept']] = ($monthNet[substr($d, 0, 7)][$c['b']][$c['dept']] ?? 0) + $v;
	}
}

// Metas: meses del archivo (venta del archivo + 4 %) y meses transaccionales sin meta (ritmo esperado + 4 %).
// Las metas de dairy_budgets existentes no se modifican; la meta por departamento sigue la mezcla del perfil.
$expected = function (string $ym) use ($dayNet): float {
	$s = 0;
	for ($ts = strtotime("$ym-01"), $e = strtotime(date('Y-m-t', strtotime("$ym-01"))); $ts <= $e; $ts = strtotime(date('Y-m-d', $ts) . ' +1 day'))
	{
		$s += $dayNet(date('Y-m-d', $ts));
	}
	return $s;
};
$months = [];
for ($ts = strtotime('2024-01-01'); $ts <= strtotime(substr($refTo, 0, 4) . '-12-01'); $ts = strtotime(date('Y-m-01', $ts) . ' +1 month'))
{
	$months[] = date('Y-m', $ts);
}
$existing = [];
foreach ($db->query('SELECT branch_id, period, sales_target FROM dairy_budgets WHERE sales_target IS NOT NULL')->fetchAll(\PDO::FETCH_ASSOC) as $r)
{
	$existing[intval($r['branch_id'])][$r['period']] = floatval($r['sales_target']);
}
$budget = $db->prepare("INSERT OR IGNORE INTO dairy_budgets (branch_id, period, sales_target, waste_target_pct, margin_target_pct, dsi_target_days, notes, updated_by_user_id)
	VALUES (:b, :p, :t, 2, 30, 10, :n, 1)");
$fillBudget = $db->prepare('UPDATE dairy_budgets SET sales_target = :t WHERE branch_id = :b AND period = :p AND sales_target IS NULL');
$deptTarget = $db->prepare('INSERT OR IGNORE INTO daily_department_sales_targets (branch_id, period, department, sales_target, notes) VALUES (0, :p, :dept, :t, :n)');
$deptShare = [];
foreach ($cells as $c)
{
	$deptShare[$c['dept']] = ($deptShare[$c['dept']] ?? 0) + $c['share'];
}
foreach ($months as $ym)
{
	$base = isset($monthNet[$ym]) ? null : $expected($ym);
	$networkTarget = 0;
	foreach ($branchNet as $b => $bn)
	{
		$bt = isset($monthNet[$ym][$b]) ? array_sum($monthNet[$ym][$b]) * 1.04 : $base * ($bn / $total) * 1.04;
		$bt = round($bt / 10) * 10;
		if (!isset($existing[$b][$ym]))
		{
			$budget->execute([':b' => $b, ':p' => $ym, ':t' => $bt, ':n' => 'Meta de la empresa de ejemplo (archivo histórico / ritmo esperado + 4 %)']);
			$fillBudget->execute([':b' => $b, ':p' => $ym, ':t' => $bt]);
		}
		$networkTarget += $existing[$b][$ym] ?? $bt;
	}
	if (!isset($existing[0][$ym]))
	{
		$budget->execute([':b' => 0, ':p' => $ym, ':t' => $networkTarget, ':n' => 'Suma de las metas de las sucursales']);
		$fillBudget->execute([':b' => 0, ':p' => $ym, ':t' => $networkTarget]);
	}
	$network = $existing[0][$ym] ?? $networkTarget;
	foreach ($deptShare as $dept => $sh)
	{
		$deptTarget->execute([':p' => $ym, ':dept' => $dept, ':t' => round($network * $sh / 10) * 10, ':n' => 'Meta de la empresa de ejemplo: meta de la red × mezcla de ventas del departamento']);
	}
}
$db->commit();
