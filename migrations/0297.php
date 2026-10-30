<?php

// DAIRY - Motor Inteligente de Horarios y Cobertura según Ventas (Módulo 35)
// Tablas de configuración departamental de productividad (SPLH, unidades/h, dotación mínima),
// recomendaciones de ajuste laboral equitativo y columnas de horas mínimas contractuales y carga laboral en empleados.

$db = \Grocy\Services\DatabaseService::GetInstance()->GetDbConnectionRaw();

// 1. Columnas en empleados para horas mínimas garantizadas y factor de cargas sociales/beneficios
$empCols = array_column($db->query('PRAGMA table_info(employees)')->fetchAll(\PDO::FETCH_ASSOC), 'name');
if (!in_array('min_weekly_hours', $empCols, true))
{
	$db->exec('ALTER TABLE employees ADD COLUMN min_weekly_hours REAL DEFAULT 24');
}
if (!in_array('labor_burden_pct', $empCols, true))
{
	$db->exec('ALTER TABLE employees ADD COLUMN labor_burden_pct REAL DEFAULT 0.22');
}

// 2. Columna en sucursales para porcentaje objetivo de presupuesto laboral sobre ventas
$branchCols = array_column($db->query('PRAGMA table_info(branches)')->fetchAll(\PDO::FETCH_ASSOC), 'name');
if (!in_array('target_labor_pct', $branchCols, true))
{
	$db->exec('ALTER TABLE branches ADD COLUMN target_labor_pct REAL DEFAULT 8.5');
}

// 3. Tabla de parámetros de productividad y cobertura por departamento
$db->exec("
CREATE TABLE IF NOT EXISTS workforce_department_configs (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	branch_id INTEGER NOT NULL DEFAULT 1,
	department TEXT NOT NULL,
	target_splh REAL NOT NULL DEFAULT 140.0,
	units_per_hour REAL NOT NULL DEFAULT 45.0,
	min_staff_open INTEGER NOT NULL DEFAULT 1,
	min_staff_peak INTEGER NOT NULL DEFAULT 2,
	sales_share_pct REAL NOT NULL DEFAULT 15.0,
	updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	UNIQUE(branch_id, department)
);

CREATE TABLE IF NOT EXISTS workforce_schedule_recommendations (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	branch_id INTEGER NOT NULL,
	week_date DATE NOT NULL,
	employee_id INTEGER NOT NULL,
	shift_id INTEGER,
	department TEXT NOT NULL,
	change_type TEXT NOT NULL, -- 'adjust_hours', 'extend', 'add', 'move', 'cancel'
	original_hours REAL NOT NULL DEFAULT 0.0,
	recommended_hours REAL NOT NULL DEFAULT 0.0,
	delta_hours REAL NOT NULL DEFAULT 0.0,
	savings_amount REAL NOT NULL DEFAULT 0.0,
	reason TEXT NOT NULL,
	legal_validation TEXT,
	guaranteed_min_hours REAL NOT NULL DEFAULT 24.0,
	contract_hours REAL NOT NULL DEFAULT 40.0,
	status TEXT NOT NULL DEFAULT 'pending', -- 'pending', 'approved', 'rejected', 'modified'
	decision_notes TEXT,
	decided_by_user_id INTEGER,
	decided_timestamp DATETIME,
	created_timestamp DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS ix_wf_recs_branch_week ON workforce_schedule_recommendations(branch_id, week_date, status);
CREATE INDEX IF NOT EXISTS ix_wf_recs_employee ON workforce_schedule_recommendations(employee_id, week_date);
");

// 4. Semilla inicial de estándares departamentales si está vacía
$count = intval($db->query('SELECT COUNT(*) FROM workforce_department_configs')->fetchColumn());
if ($count === 0)
{
	$defaults = [
		['Dairy', 145.0, 48.0, 1, 2, 28.0],
		['Grocery', 165.0, 55.0, 1, 3, 24.0],
		['Produce', 125.0, 38.0, 1, 2, 16.0],
		['Carnes', 115.0, 32.0, 1, 2, 14.0],
		['Panadería', 105.0, 28.0, 1, 2, 8.0],
		['Caja', 95.0, 35.0, 2, 4, 10.0],
		['Reposición', 130.0, 50.0, 1, 2, 0.0],
		['Mantenimiento', 100.0, 20.0, 1, 1, 0.0],
		['Administración', 150.0, 20.0, 1, 1, 0.0]
	];
	$stmt = $db->prepare('INSERT OR IGNORE INTO workforce_department_configs (branch_id, department, target_splh, units_per_hour, min_staff_open, min_staff_peak, sales_share_pct) VALUES (1, :dept, :splh, :uph, :mopen, :mpeak, :share)');
	foreach ($defaults as [$dept, $splh, $uph, $mopen, $mpeak, $share])
	{
		$stmt->execute([
			':dept' => $dept,
			':splh' => $splh,
			':uph' => $uph,
			':mopen' => $mopen,
			':mpeak' => $mpeak,
			':share' => $share
		]);
	}
}
