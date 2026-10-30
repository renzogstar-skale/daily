<?php

// DAIRY Etapa 18: libro de movimientos inalterable. Cada fila nueva de stock_log se copia (trigger) a un libro
// append-only: no admite DELETE ni cambios de datos; solo se escribe una vez su sello SHA-256 encadenado
// (MovementLedgerService::Seal). stock_log sigue siendo de Grocy (fusión de productos y división de lotes la
// modifican legítimamente); la verificación compara ambos y muestra cualquier cambio o borrado posterior.

use Grocy\Services\DatabaseService;

$db = DatabaseService::GetInstance()->GetDbConnectionRaw();
$db->exec("
CREATE TABLE IF NOT EXISTS dairy_movement_ledger (
	stock_log_id INTEGER NOT NULL PRIMARY KEY,
	product_id INTEGER,
	amount REAL,
	transaction_type TEXT,
	stock_id TEXT,
	location_id INTEGER,
	price REAL,
	spoiled INTEGER,
	best_before_date TEXT,
	transaction_id TEXT,
	correlation_id TEXT,
	user_id INTEGER,
	note TEXT,
	row_created_timestamp TEXT,
	recorded_timestamp DATETIME DEFAULT (datetime('now', 'localtime')),
	prev_hash TEXT,
	hash TEXT,
	sealed_timestamp DATETIME
);

CREATE TRIGGER IF NOT EXISTS dairy_movement_ledger_capture AFTER INSERT ON stock_log
BEGIN
	INSERT OR IGNORE INTO dairy_movement_ledger (stock_log_id, product_id, amount, transaction_type, stock_id, location_id, price, spoiled, best_before_date, transaction_id, correlation_id, user_id, note, row_created_timestamp)
	VALUES (NEW.id, NEW.product_id, NEW.amount, NEW.transaction_type, NEW.stock_id, IFNULL(NEW.location_id, (SELECT location_id FROM products WHERE id = NEW.product_id)), NEW.price, NEW.spoiled, NEW.best_before_date, NEW.transaction_id, NEW.correlation_id, NEW.user_id, NEW.note, NEW.row_created_timestamp);
END;

CREATE TRIGGER IF NOT EXISTS dairy_movement_ledger_no_delete BEFORE DELETE ON dairy_movement_ledger
BEGIN
	SELECT RAISE(ABORT, 'El libro de movimientos es inalterable: no se pueden borrar registros');
END;

CREATE TRIGGER IF NOT EXISTS dairy_movement_ledger_no_update BEFORE UPDATE ON dairy_movement_ledger
WHEN OLD.hash IS NOT NULL
	OR NEW.stock_log_id IS NOT OLD.stock_log_id OR NEW.product_id IS NOT OLD.product_id OR NEW.amount IS NOT OLD.amount
	OR NEW.transaction_type IS NOT OLD.transaction_type OR NEW.stock_id IS NOT OLD.stock_id OR NEW.location_id IS NOT OLD.location_id
	OR NEW.price IS NOT OLD.price OR NEW.spoiled IS NOT OLD.spoiled OR NEW.best_before_date IS NOT OLD.best_before_date
	OR NEW.transaction_id IS NOT OLD.transaction_id OR NEW.correlation_id IS NOT OLD.correlation_id OR NEW.user_id IS NOT OLD.user_id
	OR NEW.note IS NOT OLD.note OR NEW.row_created_timestamp IS NOT OLD.row_created_timestamp OR NEW.recorded_timestamp IS NOT OLD.recorded_timestamp
BEGIN
	SELECT RAISE(ABORT, 'El libro de movimientos es inalterable: solo se puede sellar una vez');
END;
");

// Línea base: los movimientos existentes entran al libro tal como están hoy
$db->exec("
INSERT OR IGNORE INTO dairy_movement_ledger (stock_log_id, product_id, amount, transaction_type, stock_id, location_id, price, spoiled, best_before_date, transaction_id, correlation_id, user_id, note, row_created_timestamp)
SELECT id, product_id, amount, transaction_type, stock_id, location_id, price, spoiled, best_before_date, transaction_id, correlation_id, user_id, note, row_created_timestamp FROM stock_log ORDER BY id
");
