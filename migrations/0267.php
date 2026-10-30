<?php

// Etapa 8: ciclo de compras. Estados completos de la orden (enviada, recepción parcial, recibida, cerrada),
// recepción contra orden con entrada de stock y reglas de recepción por producto (vida útil remanente y temperatura)

use Grocy\Services\DatabaseService;

$db = DatabaseService::GetInstance()->GetDbConnectionRaw();

$orderCols = array_column($db->query('PRAGMA table_info(procurement_orders)')->fetchAll(PDO::FETCH_ASSOC), 'name');
foreach ([
	'expected_delivery_date' => 'DATE',
	'sent_at' => 'DATETIME',
	'sent_by_user_id' => 'INTEGER',
	'closed_at' => 'DATETIME',
	'closed_by_user_id' => 'INTEGER',
	'close_reason' => 'TEXT'
] as $col => $type)
{
	if (!in_array($col, $orderCols))
	{
		$db->exec("ALTER TABLE procurement_orders ADD COLUMN $col $type");
	}
}

$itemCols = array_column($db->query('PRAGMA table_info(procurement_order_items)')->fetchAll(PDO::FETCH_ASSOC), 'name');
if (!in_array('received_amount', $itemCols))
{
	$db->exec('ALTER TABLE procurement_order_items ADD COLUMN received_amount REAL NOT NULL DEFAULT 0');
}
if (!in_array('rejected_amount', $itemCols))
{
	$db->exec('ALTER TABLE procurement_order_items ADD COLUMN rejected_amount REAL NOT NULL DEFAULT 0');
}

$db->exec("
CREATE TABLE IF NOT EXISTS procurement_receipts (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	receipt_code TEXT UNIQUE NOT NULL,
	order_id INTEGER NOT NULL REFERENCES procurement_orders(id),
	supplier_id INTEGER NOT NULL,
	branch_id INTEGER NOT NULL,
	received_by_user_id INTEGER NOT NULL,
	received_at DATETIME NOT NULL DEFAULT (datetime('now', 'localtime')),
	supplier_document TEXT,
	temperature_on_arrival REAL,
	stock_transaction_id TEXT,
	delivery_id INTEGER,
	total_accepted_cost REAL NOT NULL DEFAULT 0,
	total_rejected_cost REAL NOT NULL DEFAULT 0,
	notes TEXT
);

CREATE TABLE IF NOT EXISTS procurement_receipt_items (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	receipt_id INTEGER NOT NULL REFERENCES procurement_receipts(id),
	order_item_id INTEGER NOT NULL REFERENCES procurement_order_items(id),
	product_id INTEGER NOT NULL,
	delivered_amount REAL NOT NULL DEFAULT 0,
	accepted_amount REAL NOT NULL DEFAULT 0,
	rejected_amount REAL NOT NULL DEFAULT 0,
	reject_reason TEXT,
	best_before_date DATE,
	location_id INTEGER,
	unit_price REAL NOT NULL DEFAULT 0,
	incident_id INTEGER
);

CREATE TABLE IF NOT EXISTS product_receiving_rules (
	product_id INTEGER PRIMARY KEY REFERENCES products(id) ON DELETE CASCADE,
	min_remaining_shelf_life_days INTEGER,
	min_temperature REAL,
	max_temperature REAL,
	row_created_timestamp DATETIME DEFAULT (datetime('now', 'localtime'))
);
");
