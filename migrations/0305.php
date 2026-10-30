<?php

// DAILY — Motor único de automatización (Fase B): las reglas de detección (dairy_alert_rules) abren alertas y las
// automatizaciones (automation_rules) ejecutan acciones SI evento + condición → ENTONCES acción, con alcance
// (sucursal, departamento), horario, responsable, prioridad, enfriamiento, reintentos e historial de ejecuciones.
// Idempotencia: una ejecución por regla + evento + entidad (índice único). Fase A: cuentas de los empleados (modo demo).

if (!defined('GROCY_USER_ID'))
{
	define('GROCY_USER_ID', 1);
}
$db = \Grocy\Services\DatabaseService::GetInstance()->GetDbConnectionRaw();
$cols = fn(string $t) => array_column($db->query("PRAGMA table_info($t)")->fetchAll(\PDO::FETCH_ASSOC), 'name');
$add = function (string $t, string $c, string $def) use ($db, $cols) {
	if (!in_array($c, $cols($t), true))
	{
		$db->exec("ALTER TABLE $t ADD COLUMN $c $def");
	}
};

foreach ([
	'code' => 'TEXT', 'description' => 'TEXT', 'branch_id' => 'INTEGER', 'department' => 'TEXT', 'owner_user_id' => 'INTEGER',
	'priority' => "TEXT NOT NULL DEFAULT 'P3'", 'active_from' => 'TEXT', 'active_to' => 'TEXT', 'cooldown_minutes' => 'INTEGER NOT NULL DEFAULT 0',
	'max_retries' => 'INTEGER NOT NULL DEFAULT 3', 'updated_at' => 'DATETIME', 'updated_by_user_id' => 'INTEGER', 'last_run_at' => 'DATETIME'
] as $c => $def)
{
	$add('automation_rules', $c, $def);
}
$db->exec('CREATE UNIQUE INDEX IF NOT EXISTS ix_automation_rules_code ON automation_rules (code) WHERE code IS NOT NULL');

$db->exec("CREATE TABLE IF NOT EXISTS automation_executions (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	rule_id INTEGER NOT NULL REFERENCES automation_rules (id),
	event_trigger TEXT NOT NULL,
	idempotency_key TEXT NOT NULL,
	entity_type TEXT,
	entity_id INTEGER,
	branch_id INTEGER,
	product_id INTEGER,
	status TEXT NOT NULL, -- success | skipped | suppressed | deferred | retrying | failed
	attempts INTEGER NOT NULL DEFAULT 0,
	result_json TEXT,
	error TEXT,
	payload_json TEXT,
	created_at DATETIME NOT NULL,
	updated_at DATETIME NOT NULL
)");
$db->exec('CREATE UNIQUE INDEX IF NOT EXISTS ix_automation_exec_key ON automation_executions (idempotency_key)');
$db->exec('CREATE INDEX IF NOT EXISTS ix_automation_exec_rule ON automation_executions (rule_id, created_at)');
$db->exec('CREATE INDEX IF NOT EXISTS ix_automation_exec_status ON automation_executions (status)');

$db->exec("CREATE TABLE IF NOT EXISTS automation_rule_changes (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	rule_id INTEGER NOT NULL,
	user_id INTEGER,
	changes TEXT NOT NULL,
	created_at DATETIME NOT NULL
)");

// Automatizaciones base sobre las reglas de detección existentes (no tocan alertas ya abiertas: actúan sobre las nuevas)
$now = date('Y-m-d H:i:s');
$ins = $db->prepare("INSERT OR IGNORE INTO automation_rules (code, name, description, event_trigger, condition_json, action_type, action_payload_json, priority, is_active, created_at, updated_at)
	VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?)");
foreach ([
	['INV_RECOUNT', 'Diferencia de conteo → reconteo', 'Si un conteo difiere del teórico, se asigna un reconteo al responsable de la sucursal.', 'alert_created',
		['rule_code' => 'count_difference'], 'create_task', ['task_type' => 'recount', 'title' => 'Reconteo: {title}', 'due_hours' => 4], 'P2'],
	['INV_PHANTOM', 'Posible stock fantasma → conteo de verificación', 'Existencia teórica sin ventas: se pide un conteo físico antes de cualquier ajuste.', 'alert_created',
		['rule_code' => 'phantom_stock'], 'create_task', ['task_type' => 'recount', 'title' => 'Conteo de verificación: {title}', 'due_hours' => 8], 'P2'],
	['QUA_FEFO', 'Lote por vencer → tarea FEFO', 'Lote próximo a vencer: rotación FEFO y decisión (rebaja, traslado o donación).', 'alert_created',
		['rule_code' => 'expiring_lots'], 'create_task', ['task_type' => 'fefo', 'title' => 'Rotación FEFO: {title}', 'due_hours' => 8], 'P2'],
	['WASTE_HIGH', 'Merma de alto valor → aviso a gerencia', 'Merma sobre el umbral de la regla de detección: se notifica a los gestores de la sucursal.', 'alert_created',
		['rule_code' => 'high_value_waste'], 'notify', ['to' => 'managers', 'title' => 'Merma de alto valor: {title}'], 'P2'],
	['OPS_DISPATCH', 'Despacho con diferencia → aviso a supervisión', 'Despacho fuera de lo comprometido: se notifica a los gestores de la sucursal.', 'alert_created',
		['rule_code' => 'dispatch_variance'], 'notify', ['to' => 'managers', 'title' => 'Despacho con diferencia: {title}'], 'P3'],
	['STAFF_COVERAGE', 'Cobertura de personal → aviso al supervisor', 'Turno sin fichaje o sin publicar: se avisa a los responsables de la sucursal.', 'alert_created',
		['rule_code' => ['missing_punch', 'shift_unpublished']], 'notify', ['to' => 'managers', 'title' => 'Cobertura de personal: {title}'], 'P3'],
	['SALES_DROP', 'Caída de ventas → análisis de desviación', 'Venta bajo la referencia comparable: tarea de análisis para el responsable.', 'alert_created',
		['rule_code' => 'sales_drop'], 'create_task', ['task_type' => 'analysis', 'title' => 'Analizar desviación de ventas: {title}', 'due_hours' => 24], 'P3'],
	['ESC_CRITICAL', 'Alerta crítica escalada → prioridad P1', 'Una alerta de severidad crítica que se escala pasa a prioridad P1.', 'alert_escalated',
		['severity' => 'danger'], 'set_priority', ['priority' => 'P1', 'reason' => 'Escalada con severidad crítica (automatización)'], 'P1'],
] as [$code, $name, $desc, $event, $cond, $action, $payload, $prio])
{
	$ins->execute([$code, $name, $desc, $event, json_encode($cond, JSON_UNESCAPED_UNICODE), $action, json_encode($payload, JSON_UNESCAPED_UNICODE), $prio, $now, $now]);
}

// Fase A: empleados con cuenta propia (permisos según el cargo y acceso a su sucursal) para atribuir tareas y reconteos
\Grocy\Services\DailyCompanySeedService::GetInstance()->LinkEmployeeAccounts();
