<?php

// Etapa 9: traslados entre sucursales en dos pasos (envío → en tránsito → recepción en destino).
// El stock enviado pasa a una ubicación de tránsito sin sucursal: no cuenta como disponible en origen ni en destino.

use Grocy\Services\DatabaseService;

$db = DatabaseService::GetInstance()->GetDbConnectionRaw();

$locCols = array_column($db->query('PRAGMA table_info(locations)')->fetchAll(PDO::FETCH_ASSOC), 'name');
if (!in_array('is_transit', $locCols))
{
	$db->exec('ALTER TABLE locations ADD COLUMN is_transit TINYINT NOT NULL DEFAULT 0');
}
if (intval($db->query('SELECT COUNT(*) FROM locations WHERE is_transit = 1')->fetchColumn()) === 0)
{
	$db->exec("INSERT INTO locations (name, description, is_freezer, active, branch_id, is_transit) VALUES ('En tránsito (traslados)', 'Mercancía enviada entre sucursales pendiente de recepción. No usar para compras ni ventas.', 0, 1, NULL, 1)");
}

$db->exec("
CREATE TABLE IF NOT EXISTS stock_transfers (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	transfer_code TEXT UNIQUE NOT NULL,
	from_branch_id INTEGER NOT NULL,
	to_branch_id INTEGER NOT NULL,
	from_location_id INTEGER NOT NULL,
	to_location_id INTEGER NOT NULL,
	status TEXT NOT NULL DEFAULT 'in_transit',
	created_by_user_id INTEGER NOT NULL,
	sent_at DATETIME NOT NULL DEFAULT (datetime('now', 'localtime')),
	send_transaction_id TEXT,
	received_by_user_id INTEGER,
	received_at DATETIME,
	receive_transaction_id TEXT,
	total_sent_cost REAL NOT NULL DEFAULT 0,
	total_difference_cost REAL NOT NULL DEFAULT 0,
	notes TEXT,
	receive_notes TEXT
);

CREATE TABLE IF NOT EXISTS stock_transfer_items (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	transfer_id INTEGER NOT NULL REFERENCES stock_transfers(id),
	product_id INTEGER NOT NULL,
	stock_id TEXT NOT NULL,
	best_before_date DATE,
	unit_price REAL NOT NULL DEFAULT 0,
	sent_amount REAL NOT NULL,
	received_amount REAL,
	difference_amount REAL,
	waste_record_id INTEGER
);
");
