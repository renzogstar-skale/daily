<?php

// DAIRY Módulo 17: planificación de personal según ventas (pronóstico laboral).
// - labor_forecast_snapshots: último pronóstico emitido para cada día (se congela cuando el día pasa) para comparar con lo real.
// - labor_calendar_events: festivos, promociones y eventos que el gerente registra con su efecto esperado en ventas/horas.
// - labor_task_standards: minutos estándar por unidad de cada tarea operativa (editables; valores iniciales de referencia).
// - labor_coverage_requests: solicitudes de cobertura abiertas cuando no hay personal disponible para una franja.
// - dairy_budgets.labor_budget_amount: presupuesto laboral mensual.
// - Permiso WORKFORCE_OVERTIME_APPROVE (bajo WORKFORCE) para aprobar horas extra en recomendaciones.

$db = \Grocy\Services\DatabaseService::GetInstance()->GetDbConnectionRaw();

$db->exec("CREATE TABLE IF NOT EXISTS labor_forecast_snapshots (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	branch_id INTEGER NOT NULL,
	forecast_date DATE NOT NULL,
	projected_sales REAL,
	projected_tickets REAL,
	projected_units REAL,
	sales_hours REAL,
	task_hours REAL,
	required_hours REAL,
	recommended_staff INTEGER,
	scheduled_staff INTEGER,
	scheduled_hours REAL,
	productivity REAL,
	productivity_source TEXT,
	stockouts INTEGER,
	detail_json TEXT,
	updated_timestamp DATETIME DEFAULT (datetime('now', 'localtime')),
	UNIQUE (branch_id, forecast_date)
)");

$db->exec("CREATE TABLE IF NOT EXISTS labor_calendar_events (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	branch_id INTEGER NOT NULL DEFAULT 0, -- 0 = todas las sucursales
	event_date DATE NOT NULL,
	kind TEXT NOT NULL DEFAULT 'event', -- holiday, promotion, event
	name TEXT NOT NULL,
	sales_factor REAL NOT NULL DEFAULT 1.0, -- 1.15 = +15 % de ventas esperadas
	extra_hours REAL NOT NULL DEFAULT 0, -- horas operativas adicionales (montaje, degustación…)
	notes TEXT,
	created_by_user_id INTEGER,
	row_created_timestamp DATETIME DEFAULT (datetime('now', 'localtime'))
)");
$db->exec('CREATE INDEX IF NOT EXISTS ix_labor_calendar_events_date ON labor_calendar_events(event_date)');

$db->exec("CREATE TABLE IF NOT EXISTS labor_task_standards (
	code TEXT PRIMARY KEY,
	name TEXT NOT NULL,
	role TEXT NOT NULL, -- ventas, reposicion, inventario, operaciones
	minutes REAL NOT NULL,
	unit TEXT NOT NULL
)");
$ins = $db->prepare('INSERT OR IGNORE INTO labor_task_standards (code, name, role, minutes, unit) VALUES (:c, :n, :r, :m, :u)');
foreach ([
	['receiving_delivery', 'Recepción: por entrega', 'operaciones', 20, 'entrega'],
	['receiving_unit', 'Recepción: por unidad', 'operaciones', 0.05, 'unidad'],
	['restock_unit', 'Reposición: por unidad vendida', 'reposicion', 0.08, 'unidad'],
	['fefo_lot', 'FEFO / rotación: por lote próximo a vencer', 'reposicion', 2, 'lote'],
	['cycle_count', 'Conteo cíclico: por SKU', 'inventario', 4, 'SKU'],
	['waste_record', 'Registro de merma', 'operaciones', 5, 'registro'],
	['store_task', 'Tarea de tienda pendiente (limpieza, cadena de frío…)', 'operaciones', 10, 'tarea'],
] as [$c, $n, $r, $m, $u])
{
	$ins->execute([':c' => $c, ':n' => $n, ':r' => $r, ':m' => $m, ':u' => $u]);
}

$db->exec("CREATE TABLE IF NOT EXISTS labor_coverage_requests (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	branch_id INTEGER NOT NULL,
	request_date DATE NOT NULL,
	start_time TEXT NOT NULL,
	end_time TEXT NOT NULL,
	staff_needed INTEGER NOT NULL DEFAULT 1,
	role TEXT,
	reason TEXT NOT NULL,
	status TEXT NOT NULL DEFAULT 'open', -- open, filled, cancelled
	created_by_user_id INTEGER,
	closed_by_user_id INTEGER,
	closed_timestamp DATETIME,
	row_created_timestamp DATETIME DEFAULT (datetime('now', 'localtime'))
)");

$cols = $db->query('PRAGMA table_info(dairy_budgets)')->fetchAll(\PDO::FETCH_COLUMN, 1);
if (!in_array('labor_budget_amount', $cols))
{
	$db->exec('ALTER TABLE dairy_budgets ADD COLUMN labor_budget_amount REAL');
}

$parent = intval($db->query("SELECT id FROM permission_hierarchy WHERE name = 'WORKFORCE'")->fetchColumn());
if ($parent > 0)
{
	$db->prepare('INSERT OR IGNORE INTO permission_hierarchy (name, parent) VALUES (:n, :p)')->execute([':n' => 'WORKFORCE_OVERTIME_APPROVE', ':p' => $parent]);
}
