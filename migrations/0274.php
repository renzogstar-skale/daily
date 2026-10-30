<?php

// DAIRY Etapa 14: facturas de proveedor y conciliación de 3 vías (orden de compra ↔ recepción ↔ factura)

use Grocy\Services\DatabaseService;

DatabaseService::GetInstance()->GetDbConnectionRaw()->exec("
CREATE TABLE IF NOT EXISTS supplier_invoices (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	invoice_code TEXT NOT NULL UNIQUE, -- FAC-AAAA-NNNNN (folio interno)
	supplier_id INTEGER NOT NULL,
	order_id INTEGER NOT NULL,
	branch_id INTEGER,
	invoice_number TEXT NOT NULL, -- número del proveedor
	invoice_date DATE NOT NULL,
	subtotal REAL NOT NULL DEFAULT 0,
	tax_amount REAL NOT NULL DEFAULT 0,
	total REAL NOT NULL DEFAULT 0,
	amount_at_risk REAL NOT NULL DEFAULT 0, -- cobrado sin recibir + sobreprecio
	status TEXT NOT NULL DEFAULT 'matched', -- matched, discrepancy, approved_with_difference, rejected
	notes TEXT,
	decision_reason TEXT,
	created_by_user_id INTEGER,
	created_timestamp DATETIME,
	decided_by_user_id INTEGER,
	decided_timestamp DATETIME,
	UNIQUE (supplier_id, invoice_number)
);

CREATE TABLE IF NOT EXISTS supplier_invoice_items (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	invoice_id INTEGER NOT NULL,
	order_item_id INTEGER NOT NULL,
	product_id INTEGER NOT NULL,
	ordered_amount REAL NOT NULL DEFAULT 0,
	received_amount REAL NOT NULL DEFAULT 0,
	previously_invoiced REAL NOT NULL DEFAULT 0,
	invoiced_amount REAL NOT NULL DEFAULT 0,
	order_unit_price REAL,
	invoice_unit_price REAL NOT NULL DEFAULT 0,
	qty_difference REAL NOT NULL DEFAULT 0, -- facturado acumulado − recibido (positivo = cobro de lo no recibido)
	price_difference_pct REAL,
	amount_at_risk REAL NOT NULL DEFAULT 0,
	issues TEXT -- códigos separados por coma: qty_over_received, qty_over_ordered, price_over_po
);
CREATE INDEX IF NOT EXISTS ix_supplier_invoice_items_oi ON supplier_invoice_items (order_item_id);
");
