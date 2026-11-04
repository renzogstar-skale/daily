<?php

// DAILY Fase 1 — Arquitectura central: motor de flujos operativos, ajustes del catálogo de KPI
// y libro de disponibilidad (eventos que cambian disponibilidad sin mover stock físico).
// El stock físico sigue en stock/stock_log (+ dairy_movement_ledger sellado y la vista merchandise_movements).

$db = \Grocy\Services\DatabaseService::GetInstance()->GetDbConnectionRaw();

// 1. Motor de flujos: definición (pasos configurables por proceso) → instancia ligada a un documento origen → historial inmutable
$db->exec("CREATE TABLE IF NOT EXISTS daily_workflow_definitions (
	code TEXT NOT NULL PRIMARY KEY,
	name TEXT NOT NULL,
	module TEXT NOT NULL,
	operation_type TEXT NOT NULL,
	steps_json TEXT NOT NULL, -- [{code, name, requires_approval, approver_permission, requires_evidence, optional}]
	default_due_hours INTEGER,
	default_priority TEXT NOT NULL DEFAULT 'normal',
	active INTEGER NOT NULL DEFAULT 1,
	created_at DATETIME DEFAULT (datetime('now', 'localtime'))
)");

$db->exec("CREATE TABLE IF NOT EXISTS daily_workflow_instances (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	uuid TEXT NOT NULL UNIQUE,
	definition_code TEXT NOT NULL REFERENCES daily_workflow_definitions(code),
	operation_type TEXT NOT NULL,
	source_type TEXT NOT NULL,
	source_id TEXT NOT NULL,
	branch_id INTEGER,
	title TEXT NOT NULL,
	status TEXT NOT NULL DEFAULT 'open', -- open | in_progress | pending_approval | completed | rejected | cancelled | closed
	current_step TEXT,
	priority TEXT NOT NULL DEFAULT 'normal', -- low | normal | high | critical
	owner_user_id INTEGER,
	due_at DATETIME,
	result TEXT,
	result_notes TEXT,
	created_by_user_id INTEGER,
	created_at DATETIME NOT NULL,
	updated_at DATETIME NOT NULL,
	closed_at DATETIME,
	closed_by_user_id INTEGER,
	UNIQUE (definition_code, source_type, source_id)
)");
$db->exec('CREATE INDEX IF NOT EXISTS ix_dwi_status ON daily_workflow_instances (status, due_at)');
$db->exec('CREATE INDEX IF NOT EXISTS ix_dwi_branch ON daily_workflow_instances (branch_id, status)');
$db->exec('CREATE INDEX IF NOT EXISTS ix_dwi_owner ON daily_workflow_instances (owner_user_id, status)');

$db->exec("CREATE TABLE IF NOT EXISTS daily_workflow_step_states (
	instance_id INTEGER NOT NULL REFERENCES daily_workflow_instances(id),
	step_code TEXT NOT NULL,
	position INTEGER NOT NULL,
	status TEXT NOT NULL DEFAULT 'pending', -- pending | done | skipped | approved | rejected
	completed_by_user_id INTEGER,
	completed_at DATETIME,
	notes TEXT,
	PRIMARY KEY (instance_id, step_code)
)");

$db->exec("CREATE TABLE IF NOT EXISTS daily_workflow_evidence (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	instance_id INTEGER NOT NULL REFERENCES daily_workflow_instances(id),
	step_code TEXT,
	kind TEXT NOT NULL, -- photo | document | note | link | reference
	reference TEXT NOT NULL,
	description TEXT,
	user_id INTEGER,
	created_at DATETIME NOT NULL
)");
$db->exec('CREATE INDEX IF NOT EXISTS ix_dwe_instance ON daily_workflow_evidence (instance_id)');

// Historial: request_key hace idempotentes los reintentos (misma acción enviada dos veces = un solo evento)
$db->exec("CREATE TABLE IF NOT EXISTS daily_workflow_events (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	instance_id INTEGER NOT NULL REFERENCES daily_workflow_instances(id),
	event_type TEXT NOT NULL,
	step_code TEXT,
	from_status TEXT,
	to_status TEXT,
	user_id INTEGER,
	payload TEXT,
	request_key TEXT,
	created_at DATETIME NOT NULL
)");
$db->exec('CREATE INDEX IF NOT EXISTS ix_dwev_instance ON daily_workflow_events (instance_id, id)');
$db->exec('CREATE UNIQUE INDEX IF NOT EXISTS ux_dwev_request ON daily_workflow_events (instance_id, request_key) WHERE request_key IS NOT NULL');
$db->exec("CREATE TRIGGER IF NOT EXISTS daily_workflow_events_no_update BEFORE UPDATE ON daily_workflow_events
	BEGIN SELECT RAISE(ABORT, 'El historial de flujos es inmutable'); END");
$db->exec("CREATE TRIGGER IF NOT EXISTS daily_workflow_events_no_delete BEFORE DELETE ON daily_workflow_events
	BEGIN SELECT RAISE(ABORT, 'El historial de flujos es inmutable'); END");

// Definiciones iniciales (no reemplaza ediciones posteriores)
$defs = [
	['opening_checklist', 'Checklist de apertura', 'today', 'checklist', [
		['code' => 'security', 'name' => 'Desactivar alarma y revisar accesos'],
		['code' => 'cold_chain', 'name' => 'Registrar temperaturas de cámaras', 'requires_evidence' => true],
		['code' => 'cash', 'name' => 'Abrir cajas con fondo verificado'],
		['code' => 'shelves', 'name' => 'Revisar góndolas y vencimientos del día'],
		['code' => 'supervisor_ok', 'name' => 'Conformidad del supervisor', 'requires_approval' => true, 'approver_permission' => 'ALERTS_SUPERVISE'],
	], 2, 'high'],
	['closing_checklist', 'Checklist de cierre', 'today', 'checklist', [
		['code' => 'cash_close', 'name' => 'Cierre y arqueo de cajas', 'requires_evidence' => true],
		['code' => 'pending_waste', 'name' => 'Mermas del día registradas'],
		['code' => 'cold_chain', 'name' => 'Temperaturas de cierre', 'requires_evidence' => true],
		['code' => 'security', 'name' => 'Cierre de accesos y alarma'],
		['code' => 'supervisor_ok', 'name' => 'Conformidad del supervisor', 'requires_approval' => true, 'approver_permission' => 'ALERTS_SUPERVISE'],
	], 3, 'high'],
	['purchase_proposal', 'Propuesta de compra', 'mrp', 'purchase', [
		['code' => 'analyze', 'name' => 'Analizar demanda'],
		['code' => 'calculate', 'name' => 'Calcular necesidades'],
		['code' => 'review', 'name' => 'Revisar propuesta'],
		['code' => 'approve', 'name' => 'Aprobar propuesta', 'requires_approval' => true, 'approver_permission' => 'PROCUREMENT_MANAGE'],
		['code' => 'order', 'name' => 'Crear orden de compra'],
	], 48, 'normal'],
	['operational_task', 'Tarea operativa', 'operations', 'task', [
		['code' => 'execute', 'name' => 'Ejecutar'],
		['code' => 'verify', 'name' => 'Verificar resultado', 'optional' => true],
	], 24, 'normal'],
];
$ins = $db->prepare('INSERT OR IGNORE INTO daily_workflow_definitions (code, name, module, operation_type, steps_json, default_due_hours, default_priority) VALUES (?, ?, ?, ?, ?, ?, ?)');
foreach ($defs as [$code, $name, $module, $op, $steps, $due, $prio])
{
	$ins->execute([$code, $name, $module, $op, json_encode($steps, JSON_UNESCAPED_UNICODE), $due, $prio]);
}

// 2. KPI: la fórmula vive en código (KpiCatalogService); aquí solo lo configurable por empresa
$db->exec("CREATE TABLE IF NOT EXISTS daily_kpi_settings (
	code TEXT NOT NULL PRIMARY KEY,
	owner_user_id INTEGER,
	target REAL,
	warn_threshold REAL,
	critical_threshold REAL,
	updated_by_user_id INTEGER,
	updated_at DATETIME
)");

// 3. Libro de disponibilidad: reserva/liberación (pedidos) y bloqueo/desbloqueo (cuarentena) derivados de sus documentos.
//    Vista y no tabla: no duplica datos ni puede desincronizarse del documento origen.
$db->exec('DROP VIEW IF EXISTS inventory_availability_events');
$db->exec("CREATE VIEW inventory_availability_events AS
SELECT 'QB-' || q.id AS event_code, 'block' AS movement_type, 'blocked' AS bucket, q.product_id, q.branch_id, q.location_id, q.stock_id,
	q.amount, 'quarantine' AS source_type, q.id AS source_id, q.reason AS note, NULL AS user_id, q.entered_timestamp AS occurred_at
FROM quarantine_items q
UNION ALL
SELECT 'QU-' || q.id, 'unblock', 'blocked', q.product_id, q.branch_id, q.location_id, q.stock_id,
	-q.amount, 'quarantine', q.id, q.status || IFNULL(': ' || q.resolution_notes, ''), NULL, q.resolved_timestamp
FROM quarantine_items q WHERE q.status <> 'in_quarantine' AND q.resolved_timestamp IS NOT NULL
UNION ALL
SELECT 'OR-' || i.id, 'reserve', 'reserved', i.product_id, o.branch_id, NULL, NULL,
	i.amount, 'customer_order', o.id, o.order_code, o.confirmed_by_user_id, o.confirmed_at
FROM customer_order_items i JOIN customer_orders o ON o.id = i.order_id WHERE o.confirmed_at IS NOT NULL
UNION ALL
-- Al cerrar el picking la reserva pasa de lo pedido a lo preparado (CustomerOrderService::Reserved)
SELECT 'OA-' || i.id, 'reserve_adjust', 'reserved', i.product_id, o.branch_id, NULL, NULL,
	i.picked_amount - i.amount, 'customer_order', o.id, o.order_code || ' preparado', o.picked_by_user_id, o.picked_at
FROM customer_order_items i JOIN customer_orders o ON o.id = i.order_id
WHERE o.confirmed_at IS NOT NULL AND o.picked_at IS NOT NULL AND i.picked_amount <> i.amount
UNION ALL
SELECT 'OL-' || i.id, 'release', 'reserved', i.product_id, o.branch_id, NULL, NULL,
	-(CASE WHEN o.picked_at IS NOT NULL THEN i.picked_amount ELSE i.amount END), 'customer_order', o.id, CASE WHEN o.status = 'cancelled' THEN 'anulado' ELSE 'despachado' END,
	IFNULL(o.dispatched_by_user_id, o.cancelled_by_user_id), IFNULL(o.dispatched_at, o.cancelled_at)
FROM customer_order_items i JOIN customer_orders o ON o.id = i.order_id
WHERE o.confirmed_at IS NOT NULL AND o.status IN ('dispatched', 'cancelled')");
