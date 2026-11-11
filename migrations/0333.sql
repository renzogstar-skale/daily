-- Optimización #13 Despachos: idempotencia, etiquetas de despacho, incidencias logísticas y consistencia de estados OMS/WMS

ALTER TABLE customer_orders ADD COLUMN idempotency_key TEXT;
ALTER TABLE customer_orders ADD COLUMN route_name TEXT;
ALTER TABLE customer_orders ADD COLUMN label_code TEXT;
ALTER TABLE customer_orders ADD COLUMN label_printed_at DATETIME;
ALTER TABLE customer_orders ADD COLUMN conciliated_notes TEXT;

CREATE UNIQUE INDEX IF NOT EXISTS ux_co_order_code ON customer_orders(order_code);
CREATE UNIQUE INDEX IF NOT EXISTS ux_co_idempotency ON customer_orders(idempotency_key) WHERE idempotency_key IS NOT NULL AND idempotency_key <> '';

CREATE TABLE IF NOT EXISTS dispatch_logistics_incidents (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	order_id INTEGER NOT NULL REFERENCES customer_orders(id) ON DELETE CASCADE,
	branch_id INTEGER,
	incident_type TEXT NOT NULL,
	severity TEXT NOT NULL DEFAULT 'warning',
	description TEXT NOT NULL,
	reported_by_user_id INTEGER,
	resolution_status TEXT NOT NULL DEFAULT 'open',
	resolution_notes TEXT,
	resolved_by_user_id INTEGER,
	resolved_at DATETIME,
	created_at DATETIME NOT NULL DEFAULT (datetime('now', 'localtime'))
);

CREATE INDEX IF NOT EXISTS idx_dli_order_status ON dispatch_logistics_incidents(order_id, resolution_status);

-- Corregir estados logísticos contradictorios heredados
UPDATE customer_orders
SET delivery_status = 'in_transit'
WHERE status = 'dispatched' AND delivery_status = 'pending' AND delivery_type = 'delivery';

UPDATE customer_orders
SET delivery_status = CASE WHEN pickup_verified_at IS NOT NULL THEN 'delivered' ELSE 'ready_for_pickup' END
WHERE status = 'dispatched' AND delivery_status = 'pending' AND delivery_type = 'pickup';

-- Limpiar variaciones de etapa 'delivery' adheridas a pedidos aún en borrador o confirmados sin despacho
DELETE FROM dispatch_variances
WHERE stage = 'delivery'
  AND order_id IN (SELECT id FROM customer_orders WHERE status IN ('draft', 'confirmed'));

-- Generar etiqueta de despacho a pedidos empacados/despachados que no la tenían
UPDATE customer_orders
SET label_code = 'ETQ-' || order_code
WHERE status IN ('packed', 'dispatching', 'dispatched') AND (label_code IS NULL OR label_code = '');

-- Asegurar PIN de retiro no nulo en pedidos pickup
UPDATE customer_orders
SET pickup_pin = substr('000000' || (((id * 7919) + 104729) % 900000 + 100000), -6)
WHERE delivery_type = 'pickup' AND (pickup_pin IS NULL OR pickup_pin = '');

