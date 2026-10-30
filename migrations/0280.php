<?php

// DAIRY Etapa 22: compatibilidad producto ↔ clase de ubicación y pedidos de cliente (reserva, picking, empaque, despacho).
// Solo agrega columnas, tablas y triggers; los productos existentes quedan sin clase (sin restricción).

use Grocy\Services\DatabaseService;

$db = DatabaseService::GetInstance()->GetDbConnectionRaw();

if (!in_array('storage_class', array_column($db->query('PRAGMA table_info(products)')->fetchAll(\PDO::FETCH_ASSOC), 'name')))
{
	$db->exec('ALTER TABLE products ADD COLUMN storage_class TEXT');
}
if (!in_array('customer_order_id', array_column($db->query('PRAGMA table_info(dairy_scan_sessions)')->fetchAll(\PDO::FETCH_ASSOC), 'name')))
{
	$db->exec('ALTER TABLE dairy_scan_sessions ADD COLUMN customer_order_id INTEGER');
}

$db->exec("
-- Regla central: un producto con clase (ambiente/refrigerado/congelado) no entra a una ubicación con otra clase.
-- Aplica a toda ruta que mueva stock (compra, traslado, inventario, escáner). Ubicaciones sin clase (p. ej. tránsito) se aceptan.
DROP TRIGGER IF EXISTS dairy_stock_storage_class_ins;
CREATE TRIGGER dairy_stock_storage_class_ins BEFORE INSERT ON stock
WHEN (SELECT IFNULL(p.storage_class, '') FROM products p WHERE p.id = NEW.product_id) <> ''
	AND (SELECT IFNULL(l.storage_class, '') FROM locations l WHERE l.id = NEW.location_id) <> ''
	AND (SELECT p.storage_class FROM products p WHERE p.id = NEW.product_id) <> (SELECT l.storage_class FROM locations l WHERE l.id = NEW.location_id)
BEGIN
	SELECT RAISE(ABORT, 'DAIRY: clase de almacenamiento incompatible entre el producto y la ubicación (ambiente / refrigerado / congelado)');
END;
DROP TRIGGER IF EXISTS dairy_stock_storage_class_upd;
CREATE TRIGGER dairy_stock_storage_class_upd BEFORE UPDATE OF location_id ON stock
WHEN (SELECT IFNULL(p.storage_class, '') FROM products p WHERE p.id = NEW.product_id) <> ''
	AND (SELECT IFNULL(l.storage_class, '') FROM locations l WHERE l.id = NEW.location_id) <> ''
	AND (SELECT p.storage_class FROM products p WHERE p.id = NEW.product_id) <> (SELECT l.storage_class FROM locations l WHERE l.id = NEW.location_id)
BEGIN
	SELECT RAISE(ABORT, 'DAIRY: clase de almacenamiento incompatible entre el producto y la ubicación (ambiente / refrigerado / congelado)');
END;

-- Pedidos de cliente: borrador → confirmado (reserva) → en preparación → preparado → empacado → despachado (venta)
CREATE TABLE IF NOT EXISTS customer_orders (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	order_code TEXT NOT NULL UNIQUE,
	branch_id INTEGER NOT NULL,
	customer_id INTEGER NOT NULL,
	status TEXT NOT NULL DEFAULT 'draft', -- draft | confirmed | picking | picked | packed | dispatching | dispatched | cancelled
	requested_date DATE,
	notes TEXT,
	packages INTEGER,
	sale_id INTEGER,
	created_by_user_id INTEGER, created_at DATETIME NOT NULL,
	confirmed_by_user_id INTEGER, confirmed_at DATETIME,
	picked_by_user_id INTEGER, picked_at DATETIME,
	packed_by_user_id INTEGER, packed_at DATETIME,
	dispatched_by_user_id INTEGER, dispatched_at DATETIME,
	cancelled_by_user_id INTEGER, cancelled_at DATETIME, cancel_reason TEXT
);
CREATE INDEX IF NOT EXISTS ix_customer_orders_branch_status ON customer_orders(branch_id, status);
CREATE TABLE IF NOT EXISTS customer_order_items (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	order_id INTEGER NOT NULL,
	product_id INTEGER NOT NULL,
	amount REAL NOT NULL,
	unit_price REAL NOT NULL DEFAULT 0,
	picked_amount REAL NOT NULL DEFAULT 0
);
CREATE INDEX IF NOT EXISTS ix_customer_order_items_order ON customer_order_items(order_id);
-- Lo preparado, por entrada de stock (lote) y ubicación: el despacho consume exactamente estas entradas
CREATE TABLE IF NOT EXISTS customer_order_picks (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	order_id INTEGER NOT NULL,
	item_id INTEGER NOT NULL,
	product_id INTEGER NOT NULL,
	stock_id TEXT NOT NULL,
	location_id INTEGER NOT NULL,
	amount REAL NOT NULL,
	lot_number TEXT,
	fefo_ok INTEGER NOT NULL DEFAULT 1,
	scan_session_id INTEGER,
	user_id INTEGER,
	picked_at DATETIME NOT NULL
);
CREATE INDEX IF NOT EXISTS ix_customer_order_picks_order ON customer_order_picks(order_id);
");

$insert = $db->prepare('INSERT OR IGNORE INTO permission_hierarchy (name, parent) VALUES (:name, :parent)');
$parent = $db->query("SELECT id FROM permission_hierarchy WHERE name = 'SALES'")->fetchColumn() ?: $db->query("SELECT id FROM permission_hierarchy WHERE name = 'ADMIN'")->fetchColumn();
foreach (['ORDERS_MANAGE', 'ORDERS_PICK'] as $perm)
{
	$insert->execute([':name' => $perm, ':parent' => intval($parent)]);
}
