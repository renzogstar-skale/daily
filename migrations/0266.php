<?php

// Enterprise Platform Migration: tablas de órdenes del MRP (antes solo existían vía script manual en scratch/)
// y múltiplo de empaque por proveedor-producto para el redondeo de pedidos

use Grocy\Services\DatabaseService;

$db = DatabaseService::GetInstance()->GetDbConnectionRaw();

$db->exec("
CREATE TABLE IF NOT EXISTS procurement_orders (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	order_code TEXT UNIQUE NOT NULL,
	branch_id INTEGER NOT NULL,
	supplier_id INTEGER NOT NULL,
	status TEXT NOT NULL DEFAULT 'draft',
	generated_by TEXT NOT NULL DEFAULT 'mrp_engine',
	total_estimated_cost REAL NOT NULL DEFAULT 0.0,
	approved_by_user_id INTEGER,
	created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
	approved_at DATETIME
);

CREATE TABLE IF NOT EXISTS procurement_order_items (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	order_id INTEGER NOT NULL,
	product_id INTEGER NOT NULL,
	suggested_amount REAL NOT NULL,
	approved_amount REAL,
	unit_price REAL NOT NULL,
	vmd_daily_sales REAL DEFAULT 0.0,
	days_coverage_projected REAL DEFAULT 0.0
);

CREATE TABLE IF NOT EXISTS automation_rules (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	name TEXT NOT NULL,
	event_trigger TEXT NOT NULL,
	condition_json TEXT NOT NULL,
	action_type TEXT NOT NULL,
	action_payload_json TEXT NOT NULL,
	is_active INTEGER NOT NULL DEFAULT 1,
	created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS workforce_store_tasks (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	branch_id INTEGER NOT NULL,
	task_type TEXT NOT NULL,
	assigned_role TEXT NOT NULL DEFAULT 'clerk',
	assigned_user_id INTEGER,
	title TEXT NOT NULL,
	description TEXT,
	evidence_photo_path TEXT,
	due_time DATETIME,
	status TEXT NOT NULL DEFAULT 'pending',
	completed_at DATETIME,
	completed_by_user_id INTEGER
);
");

$cols = array_column($db->query('PRAGMA table_info(supplier_product_prices)')->fetchAll(PDO::FETCH_ASSOC), 'name');
if (!in_array('order_multiple', $cols))
{
	$db->exec('ALTER TABLE supplier_product_prices ADD COLUMN order_multiple REAL DEFAULT 1');
}
