<?php

// DAILY — Configuración comercial de departamentos (Ventas por departamento).
//
// daily_sales_departments: código propio de cada empresa (p. ej. «90»), nombre, color, orden y valores de
// products.department que agrupa cada departamento. DailyMetricsService::Departments() la combina con la definición
// base (DailyMetricsService::DEPARTMENTS); la clave interna no cambia, así metas y archivo histórico siguen enlazados.
// Los códigos no son universales: solo la empresa de ejemplo (archivo histórico source = 'demo') recibe códigos.

$db = \Grocy\Services\DatabaseService::GetInstance()->GetDbConnectionRaw();

$db->exec("CREATE TABLE IF NOT EXISTS daily_sales_departments (
	key TEXT PRIMARY KEY,
	code TEXT,
	name TEXT NOT NULL,
	color TEXT,
	sort_order INTEGER NOT NULL DEFAULT 0,
	source_values TEXT,
	updated_timestamp DATETIME DEFAULT (datetime('now', 'localtime'))
)");

if ((int) $db->query('SELECT COUNT(*) FROM daily_sales_departments')->fetchColumn() > 0)
{
	return;
}

$demo = false;
try
{
	$demo = (int) $db->query("SELECT COUNT(*) FROM daily_sales_archive WHERE source = 'demo'")->fetchColumn() > 0;
}
catch (\Throwable $e) {}
$codes = $demo ? ['congelados' => '90', 'dairy' => '91', 'carnes' => '93'] : [];

$ins = $db->prepare('INSERT INTO daily_sales_departments (key, code, name, color, sort_order, source_values) VALUES (:k, :c, :n, :col, :o, :v)');
$i = 0;
foreach (\Grocy\Services\DailyMetricsService::DEPARTMENTS as $k => $d)
{
	$ins->execute([':k' => $k, ':c' => $codes[$k] ?? null, ':n' => $d[0], ':col' => $d[1], ':o' => ($i += 10), ':v' => $d[2] === null ? null : json_encode($d[2], JSON_UNESCAPED_UNICODE)]);
}
// Empresa de ejemplo: Pescadería configurada sin productos todavía (aparece como «Sin ventas», no se oculta)
if ($demo)
{
	$ins->execute([':k' => 'pescaderia', ':c' => '92', ':n' => 'Pescadería', ':col' => '#0891B2', ':o' => 125, ':v' => json_encode(['Pescadería', 'Pescados y mariscos'], JSON_UNESCAPED_UNICODE)]);
}
