<?php

// Enterprise Platform Migration: Branches, Waste Records, Sales, and Roles
// Executed inside DatabaseMigrationService context

use Grocy\Services\DatabaseService;

$db = DatabaseService::GetInstance();

// 1. Branches (Multi-sucursal)
$db->ExecuteDbStatement("
CREATE TABLE IF NOT EXISTS branches (
	id INTEGER PRIMARY KEY AUTOINCREMENT UNIQUE,
	name TEXT NOT NULL,
	code TEXT UNIQUE NOT NULL,
	address TEXT,
	phone TEXT,
	active INTEGER NOT NULL DEFAULT 1,
	row_created_timestamp DATETIME DEFAULT (datetime('now', 'localtime'))
);
");

// Check if branch_id column exists in locations
$columns = $db->ExecuteDbQuery("PRAGMA table_info(locations)")->fetchAll(\PDO::FETCH_ASSOC);
$hasBranchId = false;
foreach ($columns as $col)
{
	if ($col['name'] === 'branch_id')
	{
		$hasBranchId = true;
		break;
	}
}
if (!$hasBranchId)
{
	$db->ExecuteDbStatement("ALTER TABLE locations ADD COLUMN branch_id INTEGER REFERENCES branches(id) DEFAULT 1;");
}

// 2. Waste Reasons (Motivos de Merma)
$db->ExecuteDbStatement("
CREATE TABLE IF NOT EXISTS waste_reasons (
	id INTEGER PRIMARY KEY AUTOINCREMENT UNIQUE,
	name TEXT NOT NULL,
	description TEXT,
	requires_evidence INTEGER NOT NULL DEFAULT 0,
	requires_approval INTEGER NOT NULL DEFAULT 0,
	active INTEGER NOT NULL DEFAULT 1,
	row_created_timestamp DATETIME DEFAULT (datetime('now', 'localtime'))
);
");

// 3. Waste Records (Registros de Merma y Desperdicio)
$db->ExecuteDbStatement("
CREATE TABLE IF NOT EXISTS waste_records (
	id INTEGER PRIMARY KEY AUTOINCREMENT UNIQUE,
	branch_id INTEGER NOT NULL REFERENCES branches(id),
	product_id INTEGER NOT NULL REFERENCES products(id),
	stock_id TEXT,
	location_id INTEGER REFERENCES locations(id),
	amount REAL NOT NULL,
	qu_id INTEGER NOT NULL REFERENCES quantity_units(id),
	unit_cost REAL NOT NULL DEFAULT 0,
	total_cost REAL NOT NULL DEFAULT 0,
	waste_reason_id INTEGER NOT NULL REFERENCES waste_reasons(id),
	notes TEXT,
	evidence_photo_path TEXT,
	reported_by_user_id INTEGER NOT NULL REFERENCES users(id),
	status TEXT NOT NULL DEFAULT 'approved',
	approved_by_user_id INTEGER REFERENCES users(id),
	approved_timestamp DATETIME,
	transaction_id TEXT,
	created_timestamp DATETIME DEFAULT (datetime('now', 'localtime'))
);
");

// 4. Customers (Clientes comerciales)
$db->ExecuteDbStatement("
CREATE TABLE IF NOT EXISTS customers (
	id INTEGER PRIMARY KEY AUTOINCREMENT UNIQUE,
	tax_id TEXT,
	name TEXT NOT NULL,
	phone TEXT,
	email TEXT,
	address TEXT,
	active INTEGER NOT NULL DEFAULT 1,
	created_timestamp DATETIME DEFAULT (datetime('now', 'localtime'))
);
");

// 5. Sales (Encabezado de Ventas)
$db->ExecuteDbStatement("
CREATE TABLE IF NOT EXISTS sales (
	id INTEGER PRIMARY KEY AUTOINCREMENT UNIQUE,
	sale_number TEXT UNIQUE NOT NULL,
	branch_id INTEGER NOT NULL REFERENCES branches(id),
	customer_id INTEGER REFERENCES customers(id),
	user_id INTEGER NOT NULL REFERENCES users(id),
	subtotal REAL NOT NULL DEFAULT 0,
	tax_amount REAL NOT NULL DEFAULT 0,
	discount_amount REAL NOT NULL DEFAULT 0,
	total_amount REAL NOT NULL DEFAULT 0,
	total_cogs REAL NOT NULL DEFAULT 0,
	gross_margin REAL NOT NULL DEFAULT 0,
	payment_method TEXT NOT NULL DEFAULT 'cash',
	status TEXT NOT NULL DEFAULT 'completed',
	notes TEXT,
	created_timestamp DATETIME DEFAULT (datetime('now', 'localtime'))
);
");

// 6. Sale Items (Detalle de productos vendidos y costos)
$db->ExecuteDbStatement("
CREATE TABLE IF NOT EXISTS sale_items (
	id INTEGER PRIMARY KEY AUTOINCREMENT UNIQUE,
	sale_id INTEGER NOT NULL REFERENCES sales(id),
	product_id INTEGER NOT NULL REFERENCES products(id),
	stock_id TEXT,
	amount REAL NOT NULL,
	qu_id INTEGER NOT NULL REFERENCES quantity_units(id),
	unit_price REAL NOT NULL,
	subtotal REAL NOT NULL,
	unit_cost REAL NOT NULL DEFAULT 0,
	total_cost REAL NOT NULL DEFAULT 0,
	created_timestamp DATETIME DEFAULT (datetime('now', 'localtime'))
);
");

// 7. Roles & User Roles (RBAC Empresarial)
$db->ExecuteDbStatement("
CREATE TABLE IF NOT EXISTS roles (
	id INTEGER PRIMARY KEY AUTOINCREMENT UNIQUE,
	name TEXT NOT NULL UNIQUE,
	description TEXT,
	permissions TEXT,
	row_created_timestamp DATETIME DEFAULT (datetime('now', 'localtime'))
);

CREATE TABLE IF NOT EXISTS user_roles (
	id INTEGER PRIMARY KEY AUTOINCREMENT UNIQUE,
	user_id INTEGER NOT NULL REFERENCES users(id),
	role_id INTEGER NOT NULL REFERENCES roles(id),
	branch_id INTEGER REFERENCES branches(id),
	row_created_timestamp DATETIME DEFAULT (datetime('now', 'localtime'))
);
");

// Seed default branch if empty
$branchCount = $db->ExecuteDbQuery("SELECT COUNT(*) FROM branches")->fetchColumn();
if ($branchCount == 0)
{
	$db->ExecuteDbStatement("
		INSERT INTO branches (id, name, code, address, phone, active)
		VALUES (1, 'Sucursal Matriz Centro', 'SUC-01', 'Av. Reforma #500, Centro', '+52 55 1234 5678', 1);
	");
}

// Seed default waste reasons if empty
$wasteReasonsCount = $db->ExecuteDbQuery("SELECT COUNT(*) FROM waste_reasons")->fetchColumn();
if ($wasteReasonsCount == 0)
{
	$db->ExecuteDbStatement("
		INSERT INTO waste_reasons (name, description, requires_evidence, requires_approval, active) VALUES
		('Caducidad / Vencimiento', 'Producto vencido o superó su fecha límite de consumo preferente', 0, 0, 1),
		('Deterioro físico / Rotura', 'Empaque dañado, caída, rotura o mal estado físico', 1, 0, 1),
		('Merma operativa / Manipulación', 'Pérdida por corte, preparación o manipulación en tienda', 0, 0, 1),
		('Pérdida / Faltante no identificado', 'Diferencia en conteo o posible sustracción/extravío', 0, 1, 1),
		('Muestra / Degustación comercial', 'Producto utilizado para exhibición, cata o promoción comercial', 0, 0, 1),
		('Falla de refrigeración / Cadena de frío', 'Pérdida de temperatura requerida para conservación segura', 1, 1, 1);
	");
}

// Seed default customer if empty
$customerCount = $db->ExecuteDbQuery("SELECT COUNT(*) FROM customers")->fetchColumn();
if ($customerCount == 0)
{
	$db->ExecuteDbStatement("
		INSERT INTO customers (id, tax_id, name, phone, email, address, active)
		VALUES (1, 'XAXX010101000', 'Público General / Mostrador', '555-000-0000', 'ventas@mostrador.com', 'Mostrador Local', 1);
	");
}

// Seed default roles if empty
$roleCount = $db->ExecuteDbQuery("SELECT COUNT(*) FROM roles")->fetchColumn();
if ($roleCount == 0)
{
	$db->ExecuteDbStatement("
		INSERT INTO roles (id, name, description, permissions) VALUES
		(1, 'Gerente / Administrador', 'Acceso total a inventarios, ventas, costos, autorizaciones y reportes financieros', '*'),
		(2, 'Vendedor / Cajero', 'Acceso a Punto de Venta, consulta de stock y registro de tickets de venta', 'pos_access,sales_view,sales_create,stock_view'),
		(3, 'Bodeguero / Reponedor', 'Acceso a entradas de compras, transferencias, conteo físico y registro de mermas', 'stock_purchase,stock_transfer,stock_inventory,waste_report');
	");
}

// Assign user 1 (admin) to role 1 if not assigned
$userRoleCount = $db->ExecuteDbQuery("SELECT COUNT(*) FROM user_roles WHERE user_id = 1")->fetchColumn();
if ($userRoleCount == 0)
{
	$db->ExecuteDbStatement("
		INSERT INTO user_roles (user_id, role_id, branch_id) VALUES (1, 1, 1);
	");
}
