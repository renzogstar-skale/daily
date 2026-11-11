-- Optimización #14 Devoluciones Logísticas:
-- RMA único, idempotencia física, recepción UPC, inspección y estado, clasificación multipropósito
-- (reventa, daño, RTV, reparación), autorización estricta de reingreso a stock, conciliación de unidades,
-- fotografías de evidencia y bitácora de auditoría.

ALTER TABLE customer_returns_exchanges ADD COLUMN rma_number TEXT;
ALTER TABLE customer_returns_exchanges ADD COLUMN idempotency_key TEXT;
ALTER TABLE customer_returns_exchanges ADD COLUMN reference_doc TEXT;
ALTER TABLE customer_returns_exchanges ADD COLUMN supplier_id INTEGER;
ALTER TABLE customer_returns_exchanges ADD COLUMN upc_scanned TEXT;
ALTER TABLE customer_returns_exchanges ADD COLUMN received_quantity REAL NOT NULL DEFAULT 0;
ALTER TABLE customer_returns_exchanges ADD COLUMN received_at DATETIME;
ALTER TABLE customer_returns_exchanges ADD COLUMN received_by_user_id INTEGER;
ALTER TABLE customer_returns_exchanges ADD COLUMN condition_grade TEXT NOT NULL DEFAULT 'sin_inspeccionar';
ALTER TABLE customer_returns_exchanges ADD COLUMN resalable_quantity REAL NOT NULL DEFAULT 0;
ALTER TABLE customer_returns_exchanges ADD COLUMN damaged_quantity REAL NOT NULL DEFAULT 0;
ALTER TABLE customer_returns_exchanges ADD COLUMN repairable_quantity REAL NOT NULL DEFAULT 0;
ALTER TABLE customer_returns_exchanges ADD COLUMN rtv_quantity REAL NOT NULL DEFAULT 0;
ALTER TABLE customer_returns_exchanges ADD COLUMN repair_notes TEXT;
ALTER TABLE customer_returns_exchanges ADD COLUMN target_location_id INTEGER;
ALTER TABLE customer_returns_exchanges ADD COLUMN disposition_authorized_by INTEGER;
ALTER TABLE customer_returns_exchanges ADD COLUMN disposition_authorized_at DATETIME;
ALTER TABLE customer_returns_exchanges ADD COLUMN stock_reentered INTEGER NOT NULL DEFAULT 0;
ALTER TABLE customer_returns_exchanges ADD COLUMN stock_reentry_tx_id TEXT;
ALTER TABLE customer_returns_exchanges ADD COLUMN conciliated_quantity REAL NOT NULL DEFAULT 0;
ALTER TABLE customer_returns_exchanges ADD COLUMN conciliated_at DATETIME;
ALTER TABLE customer_returns_exchanges ADD COLUMN conciliated_by_user_id INTEGER;
ALTER TABLE customer_returns_exchanges ADD COLUMN conciliation_notes TEXT;

CREATE UNIQUE INDEX IF NOT EXISTS ux_cre_rma_number
	ON customer_returns_exchanges(rma_number)
	WHERE rma_number IS NOT NULL AND rma_number <> '';

CREATE UNIQUE INDEX IF NOT EXISTS ux_cre_idempotency
	ON customer_returns_exchanges(idempotency_key)
	WHERE idempotency_key IS NOT NULL AND idempotency_key <> '';

CREATE TABLE IF NOT EXISTS return_audit_log (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	return_id INTEGER NOT NULL REFERENCES customer_returns_exchanges(id) ON DELETE CASCADE,
	stage TEXT NOT NULL,
	action TEXT NOT NULL,
	details TEXT,
	previous_status TEXT,
	new_status TEXT,
	qty_snapshot REAL,
	evidence_url TEXT,
	user_id INTEGER NOT NULL DEFAULT 1,
	created_at DATETIME NOT NULL DEFAULT (datetime('now', 'localtime'))
);

CREATE INDEX IF NOT EXISTS idx_ral_return_id ON return_audit_log(return_id, created_at);

CREATE TABLE IF NOT EXISTS return_evidence_photos (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	return_id INTEGER NOT NULL REFERENCES customer_returns_exchanges(id) ON DELETE CASCADE,
	photo_data TEXT NOT NULL,
	caption TEXT,
	stage TEXT NOT NULL DEFAULT 'inspeccion',
	uploaded_by_user_id INTEGER NOT NULL DEFAULT 1,
	created_at DATETIME NOT NULL DEFAULT (datetime('now', 'localtime'))
);

CREATE INDEX IF NOT EXISTS idx_rep_return_id ON return_evidence_photos(return_id, created_at);

-- Separar devoluciones físicas logísticas vs devoluciones comerciales POS
UPDATE customer_returns_exchanges
SET is_pos_return = 0
WHERE sale_id IS NULL
  AND pos_return_id IS NULL
  AND (ticket_number IS NULL OR ticket_number = '');

-- Asignar RMA único a todos los registros existentes
UPDATE customer_returns_exchanges
SET rma_number = 'RMA-' || strftime('%Y', IFNULL(created_at, datetime('now'))) || '-' || printf('%04d', id)
WHERE rma_number IS NULL OR rma_number = '';

-- Normalizar etapas heredadas al ciclo canónico de 6 etapas:
-- solicitud -> recepcion -> inspeccion -> clasificacion -> disposicion -> conciliacion
UPDATE customer_returns_exchanges
SET status = CASE status
	WHEN 'identificacion' THEN 'recepcion'
	WHEN 'pending_inspection' THEN 'recepcion'
	WHEN 'aprobada' THEN 'clasificacion'
	WHEN 'conciliada' THEN 'conciliacion'
	WHEN 'cerrada' THEN 'conciliacion'
	ELSE status
END
WHERE status IN ('identificacion', 'pending_inspection', 'aprobada', 'conciliada', 'cerrada');

-- Normalizar recepción física y estado de inspección en registros históricos
UPDATE customer_returns_exchanges
SET received_quantity = quantity,
	received_at = IFNULL(received_at, created_at),
	received_by_user_id = IFNULL(received_by_user_id, IFNULL(inspected_by_user_id, 1))
WHERE status NOT IN ('solicitud', 'rechazada')
  AND received_quantity = 0;

UPDATE customer_returns_exchanges
SET condition_grade = CASE
	WHEN classification IN ('reincorporacion') AND condition_state IN ('sin_uso', 'sin_abrir') THEN 'intacto'
	WHEN classification IN ('devolucion_proveedor') THEN 'defectuoso_fabrica'
	WHEN classification IN ('recuperacion', 'reparacion') THEN 'reparable'
	WHEN return_type = 'vencido' THEN 'vencido'
	WHEN classification IN ('merma_definitiva', 'destruccion') OR condition_state IN ('danado', 'no_apto_reventa') THEN 'danado'
	WHEN classification IN ('cuarentena', 'donacion') THEN 'empaque_abierto'
	ELSE 'empaque_abierto'
END,
	inspected_at = IFNULL(inspected_at, created_at),
	inspected_by_user_id = IFNULL(inspected_by_user_id, 1)
WHERE condition_grade = 'sin_inspeccionar'
  AND status IN ('inspeccion', 'clasificacion', 'disposicion', 'conciliacion');

UPDATE customer_returns_exchanges
SET resalable_quantity = CASE WHEN classification = 'reincorporacion' THEN received_quantity ELSE 0 END,
	damaged_quantity = CASE WHEN classification IN ('merma_definitiva', 'destruccion', 'donacion') THEN received_quantity ELSE 0 END,
	rtv_quantity = CASE WHEN classification = 'devolucion_proveedor' THEN received_quantity ELSE 0 END,
	repairable_quantity = CASE WHEN classification IN ('reparacion', 'recuperacion') THEN received_quantity ELSE 0 END
WHERE received_quantity > 0
  AND (resalable_quantity + damaged_quantity + rtv_quantity + repairable_quantity) = 0;

UPDATE customer_returns_exchanges
SET conciliated_quantity = received_quantity,
	conciliated_at = IFNULL(conciliated_at, IFNULL(approved_at, created_at)),
	conciliated_by_user_id = IFNULL(conciliated_by_user_id, IFNULL(approved_by_user_id, 1)),
	disposition_authorized_by = IFNULL(disposition_authorized_by, approved_by_user_id),
	disposition_authorized_at = IFNULL(disposition_authorized_at, approved_at)
WHERE status = 'conciliacion'
  AND conciliated_quantity = 0;

-- Poblar evidencia existente en return_evidence_photos si aún no existe
INSERT INTO return_evidence_photos (return_id, photo_data, caption, stage, uploaded_by_user_id, created_at)
SELECT id, evidence_photo_url, IFNULL(inspection_notes, 'Evidencia registrada en inspección'), 'inspeccion', IFNULL(inspected_by_user_id, 1), IFNULL(inspected_at, created_at)
FROM customer_returns_exchanges
WHERE evidence_photo_url IS NOT NULL
  AND evidence_photo_url <> ''
  AND NOT EXISTS (SELECT 1 FROM return_evidence_photos p WHERE p.return_id = customer_returns_exchanges.id);

-- Sembrar trazabilidad inicial en bitácora de auditoría para casos previos
INSERT INTO return_audit_log (return_id, stage, action, details, previous_status, new_status, qty_snapshot, user_id, created_at)
SELECT id, status, 'migracion_inicial', 'Caso normalizado al ciclo logístico de 6 etapas (RMA: ' || rma_number || ')', NULL, status, CASE WHEN received_quantity > 0 THEN received_quantity ELSE quantity END, IFNULL(approved_by_user_id, 1), created_at
FROM customer_returns_exchanges
WHERE NOT EXISTS (SELECT 1 FROM return_audit_log l WHERE l.return_id = customer_returns_exchanges.id);
