-- Optimización #16 Planeación y Compras:
-- Confirmación de proveedores, costos de adquisición (flete/impuestos/descuento -> landed cost),
-- separación entre recepción administrativa y movimiento físico de inventario,
-- condiciones comerciales estructuradas en proveedores e idempotencia anti-compras duplicadas.

ALTER TABLE procurement_orders ADD COLUMN created_by_user_id INTEGER;
ALTER TABLE procurement_orders ADD COLUMN confirmed_at DATETIME;
ALTER TABLE procurement_orders ADD COLUMN confirmed_by_supplier_contact TEXT;
ALTER TABLE procurement_orders ADD COLUMN supplier_confirmation_ref TEXT;
ALTER TABLE procurement_orders ADD COLUMN confirmed_delivery_date DATE;
ALTER TABLE procurement_orders ADD COLUMN freight_cost REAL NOT NULL DEFAULT 0;
ALTER TABLE procurement_orders ADD COLUMN tax_cost REAL NOT NULL DEFAULT 0;
ALTER TABLE procurement_orders ADD COLUMN other_acquisition_cost REAL NOT NULL DEFAULT 0;
ALTER TABLE procurement_orders ADD COLUMN discount_amount REAL NOT NULL DEFAULT 0;
ALTER TABLE procurement_orders ADD COLUMN reception_workflow TEXT NOT NULL DEFAULT 'standard';
ALTER TABLE procurement_orders ADD COLUMN idempotency_key TEXT;

ALTER TABLE procurement_order_items ADD COLUMN confirmed_amount REAL;
ALTER TABLE procurement_order_items ADD COLUMN landed_unit_cost REAL;

ALTER TABLE procurement_receipts ADD COLUMN reception_stage TEXT NOT NULL DEFAULT 'completed';
ALTER TABLE procurement_receipts ADD COLUMN admin_recorded_at DATETIME;
ALTER TABLE procurement_receipts ADD COLUMN admin_recorded_by_user_id INTEGER;
ALTER TABLE procurement_receipts ADD COLUMN physical_posted_at DATETIME;
ALTER TABLE procurement_receipts ADD COLUMN physical_posted_by_user_id INTEGER;
ALTER TABLE procurement_receipts ADD COLUMN freight_allocated REAL NOT NULL DEFAULT 0;
ALTER TABLE procurement_receipts ADD COLUMN landed_total_cost REAL NOT NULL DEFAULT 0;
ALTER TABLE procurement_receipts ADD COLUMN invoice_id INTEGER;

ALTER TABLE suppliers_meta ADD COLUMN credit_days INTEGER NOT NULL DEFAULT 15;
ALTER TABLE suppliers_meta ADD COLUMN currency_code TEXT NOT NULL DEFAULT 'USD';
ALTER TABLE suppliers_meta ADD COLUMN incoterm TEXT;
ALTER TABLE suppliers_meta ADD COLUMN commercial_terms_verified INTEGER NOT NULL DEFAULT 0;

CREATE TABLE IF NOT EXISTS procurement_rfqs (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	rfq_code TEXT NOT NULL UNIQUE,
	supplier_id INTEGER NOT NULL,
	branch_id INTEGER NOT NULL,
	title TEXT NOT NULL,
	items_json TEXT NOT NULL,
	status TEXT NOT NULL DEFAULT 'Enviada',
	notes TEXT,
	deadline_date DATE,
	created_by_user_id INTEGER,
	created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS procurement_supply_events (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	branch_id INTEGER,
	supplier_id INTEGER,
	order_id INTEGER,
	receipt_id INTEGER,
	event_type TEXT NOT NULL,
	reference_code TEXT,
	actor_user_id INTEGER,
	summary TEXT NOT NULL,
	payload_json TEXT,
	created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE UNIQUE INDEX IF NOT EXISTS idx_procurement_orders_idem ON procurement_orders(idempotency_key) WHERE idempotency_key IS NOT NULL;

UPDATE suppliers_meta SET commercial_terms_verified = 1
WHERE IFNULL(lead_time_days, 0) > 0 AND TRIM(IFNULL(payment_terms, '')) != '';

