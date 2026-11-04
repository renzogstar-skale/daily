<?php

// DAILY — Prevención de Pérdidas 360°: Centro de comando, llamadas gerenciales, tareas multi-área,
// mapas de sucursal, escalamientos, acciones correctivas y planes preventivos.

$db = \Grocy\Services\DatabaseService::GetInstance()->GetDbConnectionRaw();

$add = function (string $table, string $col, string $def) use ($db) {
	$cols = array_column($db->query("PRAGMA table_info($table)")->fetchAll(\PDO::FETCH_ASSOC), 'name');
	if (!in_array($col, $cols, true))
	{
		$db->exec("ALTER TABLE $table ADD COLUMN $col $def");
	}
};

// 1. Ampliación de expedientes de pérdidas
$add('sensitive_investigation_cases', 'priority', "TEXT NOT NULL DEFAULT 'medium'"); // low | medium | high | urgent
$add('sensitive_investigation_cases', 'company_id', 'INTEGER');
$add('sensitive_investigation_cases', 'zone', 'TEXT');
$add('sensitive_investigation_cases', 'shift', 'TEXT');
$add('sensitive_investigation_cases', 'escalation_level', 'INTEGER NOT NULL DEFAULT 0');
$add('sensitive_investigation_cases', 'escalated_at', 'DATETIME');
$add('sensitive_investigation_cases', 'first_response_at', 'DATETIME');
$add('sensitive_investigation_cases', 'arrival_at', 'DATETIME');
$add('sensitive_investigation_cases', 'tools_required', 'TEXT');
$add('sensitive_investigation_cases', 'preventive_plan_id', 'INTEGER');
$add('sensitive_investigation_cases', 'closed_reason', 'TEXT');

// 2. Registro de llamadas gerenciales
$db->exec("CREATE TABLE IF NOT EXISTS sensitive_case_calls (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	case_id INTEGER NOT NULL REFERENCES sensitive_investigation_cases(id) ON DELETE CASCADE,
	contact_employee_id INTEGER,
	contact_user_id INTEGER,
	contact_name TEXT,
	contact_phone TEXT,
	contact_role TEXT,
	call_type TEXT NOT NULL DEFAULT 'outbound',
	call_result TEXT NOT NULL, -- answered | no_answer | busy | voicemail | callback | wrong_number
	duration_seconds INTEGER DEFAULT 0,
	notes TEXT,
	follow_up_at DATETIME,
	caller_user_id INTEGER NOT NULL,
	created_at DATETIME DEFAULT (datetime('now', 'localtime'))
)");
$db->exec('CREATE INDEX IF NOT EXISTS ix_scc_case ON sensitive_case_calls (case_id, created_at)');

// 3. Tareas multi-área por expediente
$db->exec("CREATE TABLE IF NOT EXISTS sensitive_case_tasks (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	case_id INTEGER NOT NULL REFERENCES sensitive_investigation_cases(id) ON DELETE CASCADE,
	title TEXT NOT NULL,
	description TEXT,
	department TEXT, -- Digital | Inventario | POS | Despachos | Prevención de Pérdidas | Gerencia
	assigned_user_id INTEGER,
	assigned_employee_id INTEGER,
	priority TEXT NOT NULL DEFAULT 'medium', -- low | medium | high | urgent
	status TEXT NOT NULL DEFAULT 'pending', -- pending | assigned | in_progress | in_verification | completed | cancelled
	due_at DATETIME,
	evidence_required INTEGER NOT NULL DEFAULT 0,
	result_notes TEXT,
	escalation_level INTEGER NOT NULL DEFAULT 0,
	escalated_at DATETIME,
	completed_by_user_id INTEGER,
	completed_at DATETIME,
	created_by_user_id INTEGER,
	created_at DATETIME DEFAULT (datetime('now', 'localtime')),
	updated_at DATETIME DEFAULT (datetime('now', 'localtime'))
)");
$db->exec('CREATE INDEX IF NOT EXISTS ix_sct_case ON sensitive_case_tasks (case_id, status)');

// 4. Llegadas al lugar / Check-in in situ
$db->exec("CREATE TABLE IF NOT EXISTS sensitive_case_checkins (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	case_id INTEGER NOT NULL REFERENCES sensitive_investigation_cases(id) ON DELETE CASCADE,
	user_id INTEGER NOT NULL,
	location_id INTEGER,
	zone_name TEXT,
	arrived_at DATETIME DEFAULT (datetime('now', 'localtime')),
	notes TEXT
)");
$db->exec('CREATE INDEX IF NOT EXISTS ix_scc_checkin ON sensitive_case_checkins (case_id)');

// 5. Acciones correctivas formalizadas
$db->exec("CREATE TABLE IF NOT EXISTS sensitive_case_actions (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	case_id INTEGER NOT NULL REFERENCES sensitive_investigation_cases(id) ON DELETE CASCADE,
	action_type TEXT NOT NULL,
	title TEXT NOT NULL,
	description TEXT,
	responsible_user_id INTEGER,
	due_at DATETIME,
	status TEXT NOT NULL DEFAULT 'pending', -- pending | in_progress | completed | rejected
	requires_manager_approval INTEGER NOT NULL DEFAULT 0,
	approved_by_user_id INTEGER,
	approved_at DATETIME,
	result TEXT,
	created_by_user_id INTEGER,
	created_at DATETIME DEFAULT (datetime('now', 'localtime')),
	completed_at DATETIME
)");
$db->exec('CREATE INDEX IF NOT EXISTS ix_sca_case ON sensitive_case_actions (case_id, status)');

// 6. Planes de acción preventivos
$db->exec("CREATE TABLE IF NOT EXISTS sensitive_case_preventive_plans (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	case_id INTEGER NOT NULL REFERENCES sensitive_investigation_cases(id) ON DELETE CASCADE,
	branch_id INTEGER NOT NULL,
	product_id INTEGER,
	plan_type TEXT NOT NULL,
	title TEXT NOT NULL,
	description TEXT,
	responsible_user_id INTEGER,
	target_date DATE,
	frequency_days INTEGER DEFAULT 0,
	status TEXT NOT NULL DEFAULT 'active', -- active | completed | cancelled
	compliance_score REAL DEFAULT 100,
	notes TEXT,
	created_by_user_id INTEGER,
	created_at DATETIME DEFAULT (datetime('now', 'localtime'))
)");
$db->exec('CREATE INDEX IF NOT EXISTS ix_scpp_case ON sensitive_case_preventive_plans (case_id, branch_id)');

// 7. Hilos de mensajes vinculados a expediente y mensajes prioritarios
$add('dairy_message_threads', 'case_id', 'INTEGER');
$add('dairy_messages', 'is_priority', 'INTEGER NOT NULL DEFAULT 0');
$add('dairy_messages', 'attachment_url', 'TEXT');
$add('dairy_messages', 'attachment_name', 'TEXT');

// 8. Campos de contacto y supervisor en empleados
$add('employees', 'phone', 'TEXT');
$add('employees', 'extension', 'TEXT');
$add('employees', 'supervisor_name', 'TEXT');

// 9. Tipo de zona física en ubicaciones
$add('locations', 'zone_type', 'TEXT');

// Actualizar teléfonos de ejemplo si no existen
$db->exec("UPDATE employees SET phone = '+57 300 234 ' || SUBSTR(1000 + id, 2), extension = '10' || (id % 90 + 10) WHERE phone IS NULL OR phone = ''");
$db->exec("UPDATE employees SET supervisor_name = 'Carlos M. (Gerente de Operaciones)' WHERE supervisor_name IS NULL OR supervisor_name = ''");

// Asignar tipo de zona a ubicaciones comunes
$db->exec("UPDATE locations SET zone = 'Bodega', zone_type = 'bodega' WHERE (zone IS NULL OR zone = '') AND (name LIKE '%Bodega%' OR name LIKE '%Despensa%' OR name LIKE '%Cámara%')");
$db->exec("UPDATE locations SET zone = 'Piso de venta', zone_type = 'piso_venta' WHERE (zone IS NULL OR zone = '') AND (name LIKE '%Góndola%' OR name LIKE '%Estante%' OR name LIKE '%Exhibidor%')");
$db->exec("UPDATE locations SET zone = 'Congelador', zone_type = 'bodega' WHERE (zone IS NULL OR zone = '') AND name LIKE '%Congelador%'");

