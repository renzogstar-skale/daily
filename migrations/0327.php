<?php

// DAILY — Devoluciones comerciales del POS (PosReturnService).
//
// - daily_pos_returns: cabecera de cada devolución (código, referencia única de la terminal para no registrarla dos
//   veces, venta, turno, resolución reembolso | nota de crédito | cambio, importe y autorización).
// - customer_returns_exchanges.pos_return_id: cada línea devuelta queda como solicitud de inspección física en el
//   módulo de devoluciones (/returns); el POS nunca reintegra stock.
// - daily_pos_refunds: reembolso por medio de pago (con tope del medio original). daily_credit_notes y sus movimientos:
//   notas de crédito emitidas por devoluciones y canjeadas en el POS.

$db = \Grocy\Services\DatabaseService::GetInstance()->GetDbConnectionRaw();

$db->exec("CREATE TABLE IF NOT EXISTS daily_pos_returns (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	code TEXT NOT NULL UNIQUE,
	return_ref TEXT NOT NULL UNIQUE,
	sale_id INTEGER NOT NULL,
	branch_id INTEGER NOT NULL,
	shift_id INTEGER,
	customer_id INTEGER,
	resolution TEXT NOT NULL, -- refund | credit_note | exchange
	amount REAL NOT NULL,
	items_count REAL NOT NULL DEFAULT 0,
	auth_id INTEGER,
	credit_note_id INTEGER,
	user_id INTEGER,
	notes TEXT,
	created_at DATETIME NOT NULL DEFAULT (datetime('now', 'localtime'))
);
CREATE INDEX IF NOT EXISTS ix_daily_pos_returns_sale ON daily_pos_returns(sale_id);
CREATE TABLE IF NOT EXISTS daily_pos_refunds (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	return_id INTEGER NOT NULL,
	sale_id INTEGER NOT NULL,
	shift_id INTEGER,
	method TEXT NOT NULL, -- cash | card | transfer | credit_note
	amount REAL NOT NULL,
	reference TEXT,
	created_at DATETIME NOT NULL DEFAULT (datetime('now', 'localtime'))
);
CREATE INDEX IF NOT EXISTS ix_daily_pos_refunds_sale ON daily_pos_refunds(sale_id);
CREATE INDEX IF NOT EXISTS ix_daily_pos_refunds_shift ON daily_pos_refunds(shift_id);
CREATE TABLE IF NOT EXISTS daily_credit_notes (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	code TEXT NOT NULL UNIQUE,
	customer_id INTEGER,
	branch_id INTEGER,
	return_id INTEGER,
	amount REAL NOT NULL,
	balance REAL NOT NULL,
	status TEXT NOT NULL DEFAULT 'active', -- active | used | void
	created_at DATETIME NOT NULL DEFAULT (datetime('now', 'localtime')),
	expires_at DATE
);
CREATE TABLE IF NOT EXISTS daily_credit_note_movements (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	note_id INTEGER NOT NULL,
	kind TEXT NOT NULL, -- issue | redeem
	amount REAL NOT NULL,
	sale_id INTEGER,
	return_id INTEGER,
	user_id INTEGER,
	created_at DATETIME NOT NULL DEFAULT (datetime('now', 'localtime'))
)");

$cols = array_column($db->query('PRAGMA table_info(customer_returns_exchanges)')->fetchAll(\PDO::FETCH_ASSOC), 'name');
if (!in_array('pos_return_id', $cols, true))
{
	$db->exec('ALTER TABLE customer_returns_exchanges ADD COLUMN pos_return_id INTEGER');
}

$set = $db->prepare("INSERT OR IGNORE INTO daily_system_settings (setting_key, setting_value, category, description) VALUES (?, ?, 'pos', ?)");
$set->execute(['pos_return_days', '30', 'Días desde la venta en que se acepta una devolución con ticket']);
$set->execute(['pos_return_auth_threshold', '50', 'Importe ($) desde el que un cajero sin permiso de supervisor necesita autorización para devolver']);
$set->execute(['pos_credit_note_days', '365', 'Vigencia (días) de una nota de crédito']);
