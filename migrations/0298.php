<?php

// DAILY — Sistema de Trazabilidad, Conciliación e Inventario Sensible (Módulo 36)
// 1. Ingreso de mercancía -> 2. Devoluciones -> 3. Despachos -> 4. Inventario sensible -> 5. Alertas e Investigaciones
// Comparte la fuente centralizada de movimientos y agrega trazabilidad serial/IMEI,
// ledger unificado de conciliación, motor de evaluación de intervalos y expedientes de investigación.

$db = \Grocy\Services\DatabaseService::GetInstance()->GetDbConnectionRaw();

// 1. Columnas en productos para clasificación sensible, serie/IMEI y departamento operativo
$prodCols = array_column($db->query('PRAGMA table_info(products)')->fetchAll(\PDO::FETCH_ASSOC), 'name');
if (!in_array('is_sensitive', $prodCols, true))
{
	$db->exec('ALTER TABLE products ADD COLUMN is_sensitive INTEGER NOT NULL DEFAULT 0');
}
if (!in_array('is_serialized', $prodCols, true))
{
	$db->exec('ALTER TABLE products ADD COLUMN is_serialized INTEGER NOT NULL DEFAULT 0');
}
if (!in_array('imei_tracking', $prodCols, true))
{
	$db->exec('ALTER TABLE products ADD COLUMN imei_tracking INTEGER NOT NULL DEFAULT 0');
}
if (!in_array('department', $prodCols, true))
{
	$db->exec("ALTER TABLE products ADD COLUMN department TEXT DEFAULT 'General'");
}

// 2. Columnas en dairy_stock_lots para IMEI y estado del serial
$lotCols = array_column($db->query('PRAGMA table_info(dairy_stock_lots)')->fetchAll(\PDO::FETCH_ASSOC), 'name');
if (!in_array('imei', $lotCols, true))
{
	$db->exec('ALTER TABLE dairy_stock_lots ADD COLUMN imei TEXT');
}
if (!in_array('serial_status', $lotCols, true))
{
	$db->exec("ALTER TABLE dairy_stock_lots ADD COLUMN serial_status TEXT DEFAULT 'active'");
}

// 3. Crear grupo de producto "Electrónica y Telefonía" si no existe
$electGroup = $db->query("SELECT id FROM product_groups WHERE name LIKE '%Electr%' OR name LIKE '%Telefon%'")->fetchColumn();
if (!$electGroup)
{
	$db->exec("INSERT INTO product_groups (name, description) VALUES ('08 Electrónica y Telefonía', 'Equipos móviles, accesorios y tecnología con control por IMEI/Serie')");
	$electGroupId = intval($db->lastInsertId());
}
else
{
	$electGroupId = intval($electGroup);
}

// 4. Tabla unificada de movimientos del centro de trazabilidad y conciliación
$db->exec("
CREATE TABLE IF NOT EXISTS sensitive_inventory_movements (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	movement_code TEXT NOT NULL UNIQUE,
	branch_id INTEGER NOT NULL DEFAULT 1,
	product_id INTEGER NOT NULL,
	event_type TEXT NOT NULL, -- 'reception', 'return_exchange', 'dispatch', 'transfer', 'sale', 'cycle_count', 'adjustment'
	warehouse_type TEXT NOT NULL DEFAULT 'physical', -- 'physical', 'digital_reserve', 'digital_transit'
	status TEXT NOT NULL DEFAULT 'confirmed', -- 'solicitado', 'en_transito', 'confirmado', 'cancelado'
	origin_location TEXT,
	dest_location TEXT,
	expected_amount REAL NOT NULL DEFAULT 0.0,
	actual_amount REAL NOT NULL DEFAULT 0.0,
	accepted_amount REAL NOT NULL DEFAULT 0.0,
	difference_amount REAL NOT NULL DEFAULT 0.0,
	unit_cost REAL NOT NULL DEFAULT 0.0,
	financial_impact REAL NOT NULL DEFAULT 0.0,
	document_type TEXT, -- 'OC', 'REC', 'DEV', 'PED', 'TRA', 'VTA', 'CNT', 'AJU'
	document_code TEXT,
	serial_number TEXT,
	imei TEXT,
	batch_number TEXT,
	supplier_name TEXT,
	customer_name TEXT,
	disposition TEXT, -- 'disponible', 'cuarentena', 'otra_bodega', 'merma_definitiva'
	notes TEXT,
	user_id INTEGER,
	authorized_by_user_id INTEGER,
	created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	confirmed_at DATETIME
);

CREATE INDEX IF NOT EXISTS ix_sim_prod_branch ON sensitive_inventory_movements(product_id, branch_id, created_at);
CREATE INDEX IF NOT EXISTS ix_sim_event ON sensitive_inventory_movements(event_type, created_at);
CREATE INDEX IF NOT EXISTS ix_sim_status ON sensitive_inventory_movements(status);
CREATE INDEX IF NOT EXISTS ix_sim_code ON sensitive_inventory_movements(movement_code);

CREATE TABLE IF NOT EXISTS sensitive_investigation_cases (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	case_code TEXT NOT NULL UNIQUE, -- 'INV-2026-0001'
	alert_id INTEGER,
	branch_id INTEGER NOT NULL DEFAULT 1,
	product_id INTEGER NOT NULL,
	department TEXT,
	location_name TEXT,
	expected_stock REAL NOT NULL DEFAULT 0.0,
	physical_count REAL NOT NULL DEFAULT 0.0,
	difference_qty REAL NOT NULL DEFAULT 0.0,
	unit_cost REAL NOT NULL DEFAULT 0.0,
	financial_impact REAL NOT NULL DEFAULT 0.0,
	stage TEXT NOT NULL DEFAULT 'preliminar', -- 'preliminar', 'faltante_confirmado', 'perdida_reconocida', 'ajustado', 'cerrado'
	audit_interval_start DATETIME,
	audit_interval_end DATETIME,
	audit_interval_tx_count INTEGER DEFAULT 0,
	suspected_cause_category TEXT, -- 'error_recepcion', 'desvio_despacho', 'omision_pos', 'reubicacion_fisica', 'indeterminada'
	evidence_notes TEXT,
	recommended_action TEXT,
	recount_status TEXT NOT NULL DEFAULT 'pending', -- 'pending', 'completed', 'not_required'
	recount_qty REAL,
	recount_notes TEXT,
	adjustment_status TEXT NOT NULL DEFAULT 'pending', -- 'pending', 'approved', 'rejected'
	adjusted_by_user_id INTEGER,
	adjusted_at DATETIME,
	closed_by_user_id INTEGER,
	closed_at DATETIME,
	resolution_notes TEXT,
	created_by_user_id INTEGER,
	created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS ix_sic_branch_prod ON sensitive_investigation_cases(branch_id, product_id, stage);
CREATE INDEX IF NOT EXISTS ix_sic_stage ON sensitive_investigation_cases(stage);

CREATE TABLE IF NOT EXISTS sensitive_investigation_evidence (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	case_id INTEGER NOT NULL,
	evidence_type TEXT NOT NULL, -- 'note', 'photo', 'document', 'recount', 'log'
	title TEXT NOT NULL,
	description TEXT,
	file_path TEXT,
	user_id INTEGER,
	created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS ix_sie_case ON sensitive_investigation_evidence(case_id);
");

// 5. Sembrar producto sensible Celular / Smartphone (ejemplo exacto del requerimiento)
$phoneId = $db->query("SELECT id FROM products WHERE sku = 'TECH-SMP-001' OR name LIKE '%Smartphone Galaxy%'")->fetchColumn();
if (!$phoneId)
{
	$quPiece = $db->query("SELECT id FROM quantity_units WHERE name LIKE '%Pieza%' OR name LIKE '%Unidad%' LIMIT 1")->fetchColumn() ?: 3;
	$locMatriz = $db->query("SELECT id FROM locations WHERE branch_id = 1 AND active = 1 AND is_freezer = 0 LIMIT 1")->fetchColumn() ?: 1;

	$db->exec("
		INSERT INTO products (
			name, description, product_group_id, active, location_id, qu_id_purchase, qu_id_stock,
			min_stock_amount, max_stock_amount, default_best_before_days, picture_file_name,
			sku, brand, subcategory, sale_price, is_sensitive, is_serialized, imei_tracking, department
		) VALUES (
			'Smartphone Galaxy Pro 5G 128GB',
			'Dispositivo móvil de alta gama con control estricto de serie e IMEI. Inventario de alto valor y custodia sensible.',
			$electGroupId, 1, $locMatriz, $quPiece, $quPiece,
			4, 20, 730, 'daily_scan_phone.jpg',
			'TECH-SMP-001', 'Samsung', 'Smartphones', 799.00, 1, 1, 1, 'Electrónica y Telefonía'
		)
	");
	$phoneId = intval($db->lastInsertId());

	// Código de barras UPC
	$db->exec("INSERT OR IGNORE INTO product_barcodes (product_id, barcode, qu_id) VALUES ($phoneId, '750100009999', $quPiece)");

	// Existencia en stock (6 unidades físicas en stock actual con costo 520.00)
	$stockIdPhone = 'STK-SMP-' . date('Ymd');
	$db->exec("
		INSERT INTO stock (product_id, amount, best_before_date, price, location_id, stock_id)
		VALUES ($phoneId, 6.0, '2028-12-31', 520.00, $locMatriz, '$stockIdPhone')
	");

	// Registro de series / IMEIs en dairy_stock_lots
	$imeis = [
		'358920110482904' => 'active',
		'358920110482905' => 'active',
		'358920110482906' => 'active',
		'358920110482907' => 'active',
		'358920110482908' => 'active',
		'358920110482909' => 'active',
	];
	$insLot = $db->prepare("INSERT OR REPLACE INTO dairy_stock_lots (stock_id, product_id, lot_number, serial_number, imei, serial_status, created_at) VALUES ('$stockIdPhone', $phoneId, 'LOT-SMP-2026A', :sn, :imei, :st, '2026-10-10 09:30:00')");
	foreach ($imeis as $imeiVal => $st)
	{
		$insLot->execute([':sn' => 'SN-' . substr($imeiVal, -6), ':imei' => $imeiVal, ':st' => $st]);
	}
}
else
{
	$phoneId = intval($phoneId);
	$db->exec("UPDATE products SET is_sensitive = 1, is_serialized = 1, imei_tracking = 1, department = 'Electrónica y Telefonía', picture_file_name = 'daily_scan_phone.jpg' WHERE id = $phoneId");
}

// 6. Sembrar historial cronológico completo de los 5 procesos para el celular
$hasMovs = intval($db->query("SELECT COUNT(*) FROM sensitive_inventory_movements WHERE product_id = $phoneId")->fetchColumn());
if ($hasMovs === 0)
{
	$locMatrizName = 'Bodega Central - Almacén Seguro';
	$locSafe = 'Zona Custodia / Jaula Electrónica';

	// 1. Ingreso de mercancía: 10 unidades esperadas, 10 recibidas con OC y proveedor
	$db->exec("
		INSERT INTO sensitive_inventory_movements (
			movement_code, branch_id, product_id, event_type, warehouse_type, status,
			origin_location, dest_location, expected_amount, actual_amount, accepted_amount, difference_amount,
			unit_cost, financial_impact, document_type, document_code, imei, supplier_name,
			notes, user_id, created_at, confirmed_at
		) VALUES (
			'ING-2026-00089', 1, $phoneId, 'reception', 'physical', 'confirmado',
			'TechGlobal Distribuciones S.A.', '$locSafe', 10.0, 10.0, 10.0, 0.0,
			520.00, 5200.00, 'OC', 'OC-2026-0089', 'Lote 10 IMEIs (358920110482901–910)', 'TechGlobal Distribuciones',
			'Recepción completa con precinto de seguridad intacto y verificación física de 10 números IMEI.', 1,
			'2026-10-10 10:15:00', '2026-10-10 10:45:00'
		)
	");

	// 2. Inventario selectivo 1: Conteo ciego inicial verifica 10 unidades OK
	$db->exec("
		INSERT INTO sensitive_inventory_movements (
			movement_code, branch_id, product_id, event_type, warehouse_type, status,
			origin_location, dest_location, expected_amount, actual_amount, accepted_amount, difference_amount,
			unit_cost, financial_impact, document_type, document_code,
			notes, user_id, created_at, confirmed_at
		) VALUES (
			'CNT-2026-00102', 1, $phoneId, 'cycle_count', 'physical', 'confirmado',
			'$locSafe', '$locSafe', 10.0, 10.0, 10.0, 0.0,
			520.00, 0.0, 'CNT', 'CNT-2026-00102',
			'Conteo selectivo quincenal verificado al 100%. Sin diferencias.', 2,
			'2026-10-15 18:00:00', '2026-10-15 18:30:00'
		)
	");

	// 3. Venta registrada: 1 unidad vendida en sala (IMEI 358920110482901)
	$db->exec("
		INSERT INTO sensitive_inventory_movements (
			movement_code, branch_id, product_id, event_type, warehouse_type, status,
			origin_location, dest_location, expected_amount, actual_amount, accepted_amount, difference_amount,
			unit_cost, financial_impact, document_type, document_code, imei, customer_name,
			notes, user_id, created_at, confirmed_at
		) VALUES (
			'VTA-2026-00045', 1, $phoneId, 'sale', 'physical', 'confirmado',
			'$locSafe', 'Cliente Final (Caja 02)', 1.0, 1.0, 1.0, 0.0,
			520.00, 799.00, 'VTA', 'TKT-2026-00045', '358920110482901', 'Consumidor Final',
			'Venta POS registrada con validación de IMEI y activación de garantía comercial.', 3,
			'2026-10-18 14:22:00', '2026-10-18 14:22:00'
		)
	");

	// 4. Despacho/Traslado: 1 unidad transferida a Sucursal Norte (IMEI 358920110482902)
	$db->exec("
		INSERT INTO sensitive_inventory_movements (
			movement_code, branch_id, product_id, event_type, warehouse_type, status,
			origin_location, dest_location, expected_amount, actual_amount, accepted_amount, difference_amount,
			unit_cost, financial_impact, document_type, document_code, imei,
			notes, user_id, created_at, confirmed_at
		) VALUES (
			'TRA-2026-00012', 1, $phoneId, 'transfer', 'physical', 'confirmado',
			'Sucursal Matriz (Jaula)', 'Sucursal Norte (Bodega)', 1.0, 1.0, 1.0, 0.0,
			520.00, 520.00, 'TRA', 'TRA-2026-00012', '358920110482902',
			'Traslado inter-tiendas confirmado y recibido conforme en destino.', 2,
			'2026-10-21 11:05:00', '2026-10-21 16:30:00'
		)
	");

	// 5. Movimiento de bodega digital: 1 unidad reservada para Click & Collect (IMEI 358920110482903)
	$db->exec("
		INSERT INTO sensitive_inventory_movements (
			movement_code, branch_id, product_id, event_type, warehouse_type, status,
			origin_location, dest_location, expected_amount, actual_amount, accepted_amount, difference_amount,
			unit_cost, financial_impact, document_type, document_code, imei, customer_name,
			notes, user_id, created_at, confirmed_at
		) VALUES (
			'RES-2026-00078', 1, $phoneId, 'dispatch', 'digital_reserve', 'solicitado',
			'$locSafe', 'Reserva E-commerce / Casillero Pick-up #04', 1.0, 1.0, 1.0, 0.0,
			520.00, 799.00, 'PED', 'PED-2026-00078', '358920110482903', 'Carlos Mario Valenzuela',
			'Reserva digital para retiro en tienda programado. No deducida de la propiedad legal del inventario.', 1,
			'2026-10-24 16:40:00', NULL
		)
	");

	// 6. Conteo sensible reciente: el inventario esperado en sistema es 8 (10 recibidos - 1 vendido - 1 trasladado),
	// pero el conteo físico en la jaula registra 6 unidades físicas -> diferencia de 2 unidades!
	$db->exec("
		INSERT INTO sensitive_inventory_movements (
			movement_code, branch_id, product_id, event_type, warehouse_type, status,
			origin_location, dest_location, expected_amount, actual_amount, accepted_amount, difference_amount,
			unit_cost, financial_impact, document_type, document_code,
			notes, user_id, created_at, confirmed_at
		) VALUES (
			'CNT-2026-00144', 1, $phoneId, 'cycle_count', 'physical', 'confirmado',
			'$locSafe', '$locSafe', 8.0, 6.0, 6.0, -2.0,
			520.00, -1040.00, 'CNT', 'CNT-2026-00144',
			'Conteo físico sensible: 6 celulares contados físicamente en jaula vs 8 unidades esperadas en sistema. Descuadre de 2 unidades.', 2,
			'2026-10-26 18:30:00', '2026-10-26 18:45:00'
		)
	");

	// 7. Generar Alerta en dairy_alerts según regla operativa
	$alertTitle = 'Diferencia en inventario sensible: Smartphone Galaxy Pro 5G (2 u.)';
	$alertDetail = 'Conteo físico registró 6 celulares vs 8 esperados (diferencia de 2 u. · $1,040.00). Intervalo de verificación: 2026-10-15 al 2026-10-26 (3 movimientos registrados). Requiere reconteo guiado y verificación de reservas digitales.';
	
	$db->exec("
		INSERT INTO dairy_alerts (
			rule_code, dedupe_key, branch_id, severity, title, detail, value, href, status,
			owner_user_id, due_at, source, category, alert_type, department, product_id,
			priority, priority_score, priority_reason, impact_bucket, impact_kind, created_at, updated_at
		) VALUES (
			'count_difference', 'sensitive_phone_diff_20261026', 1, 'danger',
			'$alertTitle',
			'$alertDetail',
			1040.00, '/reconciliation?product_id=$phoneId', 'investigating',
			1, datetime('now', '+24 hours'), 'rule', 'inventory', 'count_diff', 'Electrónica y Telefonía', $phoneId,
			'P1', 95, 'Severidad crítica (+60) · Impacto $1,040.00 (+25) · Producto sensible serializado (+10)',
			'inventory', 'estimated', '2026-10-26 18:45:00', '2026-10-26 18:45:00'
		)
	");
	$alertId = intval($db->lastInsertId());

	// 8. Crear Caso de Investigación Automático (INV-2026-0001)
	$db->exec("
		INSERT INTO sensitive_investigation_cases (
			case_code, alert_id, branch_id, product_id, department, location_name,
			expected_stock, physical_count, difference_qty, unit_cost, financial_impact,
			stage, audit_interval_start, audit_interval_end, audit_interval_tx_count,
			suspected_cause_category, evidence_notes, recommended_action, recount_status,
			adjustment_status, created_by_user_id, created_at, updated_at
		) VALUES (
			'INV-2026-0001', $alertId, 1, $phoneId, 'Electrónica y Telefonía', '$locSafe',
			8.0, 6.0, -2.0, 520.00, 1040.00,
			'preliminar', '2026-10-15 18:00:00', '2026-10-26 18:30:00', 3,
			'desvio_despacho',
			'Diferencia detectada en conteo ciego de cierre de turno. Existe una reserva e-commerce PED-2026-0078 asignada a casillero Pick-up que podría no haber sido registrada en el conteo de la jaula física. IMEIs contados presentes: 358920110482904 al 909. IMEIs faltantes por conciliar: 358920110482903 (posible pick-up) y 358920110482910.',
			'1. Ejecutar reconteo guiado con escaneo directo de IMEI.\n2. Auditar casillero Click & Collect #04 para verificar presencia del IMEI 358920110482903.\n3. Revisar cámaras de la jaula en intervalo 2026-10-15 a 2026-10-26 si persiste diferencia.',
			'pending', 'pending', 1, '2026-10-26 18:45:00', '2026-10-26 18:45:00'
		)
	");
	$caseId = intval($db->lastInsertId());

	// Evidencias iniciales anexas
	$db->exec("
		INSERT INTO sensitive_investigation_evidence (case_id, evidence_type, title, description, user_id, created_at)
		VALUES ($caseId, 'log', 'Intervalo de Auditoría Delimitado', 'Último conteo correcto registrado el 2026-10-15 (10 u.). Desde esa fecha ocurrieron 3 movimientos: 1 venta POS, 1 traslado confirmado a Sucursal Norte y 1 reserva digital.', 1, '2026-10-26 18:46:00')
	");
	$db->exec("
		INSERT INTO sensitive_investigation_evidence (case_id, evidence_type, title, description, user_id, created_at)
		VALUES ($caseId, 'note', 'Verificación de Casillero Digital Requerida', 'La reserva digital PED-2026-0078 contiene el IMEI 358920110482903. Si el equipo fue llevado a la zona de entrega sin confirmación de salida, explica el 50% de la discrepancia.', 1, '2026-10-26 18:47:00')
	");
}

// 7. Sembrar movimientos operativos para otros productos sensibles (Queso Parmesano, Licores, Leche)
$parmesanoId = $db->query("SELECT id FROM products WHERE sku = 'DAIRY-CHE-099' OR name LIKE '%mozzarella%' OR name LIKE '%cheddar%' LIMIT 1")->fetchColumn() ?: 41;
$parmCount = intval($db->query("SELECT COUNT(*) FROM sensitive_inventory_movements WHERE product_id = $parmesanoId")->fetchColumn());
if ($parmCount === 0 && $parmesanoId)
{
	$db->exec("UPDATE products SET is_sensitive = 1 WHERE id = $parmesanoId");
	// Devolución con reincorporación
	$db->exec("
		INSERT INTO sensitive_inventory_movements (
			movement_code, branch_id, product_id, event_type, warehouse_type, status,
			origin_location, dest_location, expected_amount, actual_amount, accepted_amount, difference_amount,
			unit_cost, financial_impact, document_type, document_code, disposition,
			notes, user_id, created_at, confirmed_at
		) VALUES (
			'DEV-2026-00018', 1, $parmesanoId, 'return_exchange', 'physical', 'confirmado',
			'Cliente en Mostrador', 'Cámara de Frío 01 (Lácteos)', 2.0, 2.0, 2.0, 0.0,
			4.99, 9.98, 'DEV', 'DEV-2026-00018', 'reincorporacion',
			'Cambio comercial por preferencia de empaque. Producto sellado al vacío, cadena de frío verificada (3.8 °C) y reincorporado a inventario apto.', 2,
			'2026-10-25 15:10:00', '2026-10-25 15:20:00'
		)
	");
	// Traslado en tránsito a Sucursal Norte
	$db->exec("
		INSERT INTO sensitive_inventory_movements (
			movement_code, branch_id, product_id, event_type, warehouse_type, status,
			origin_location, dest_location, expected_amount, actual_amount, accepted_amount, difference_amount,
			unit_cost, financial_impact, document_type, document_code,
			notes, user_id, created_at, confirmed_at
		) VALUES (
			'TRA-2026-00034', 1, $parmesanoId, 'transfer', 'physical', 'en_transito',
			'Sucursal Matriz (Cámara)', 'Sucursal Norte (Recepción)', 15.0, 15.0, 0.0, 0.0,
			4.99, 74.85, 'TRA', 'TRA-2026-00034',
			'Despacho inter-sucursal en camión refrigerado con registrador termógrafo. En tránsito, pendiente confirmación en destino.', 1,
			'2026-10-26 16:00:00', NULL
		)
	");
}

// 8. Producto de alta rotación (Leche) con devolución enviada a cuarentena y merma definitiva
$milkId = 30; // Leche entera 1 galón
$milkCount = intval($db->query("SELECT COUNT(*) FROM sensitive_inventory_movements WHERE product_id = $milkId")->fetchColumn());
if ($milkCount === 0)
{
	$db->exec("
		INSERT INTO sensitive_inventory_movements (
			movement_code, branch_id, product_id, event_type, warehouse_type, status,
			origin_location, dest_location, expected_amount, actual_amount, accepted_amount, difference_amount,
			unit_cost, financial_impact, document_type, document_code, disposition,
			notes, user_id, created_at, confirmed_at
		) VALUES (
			'DEV-2026-00022', 1, $milkId, 'return_exchange', 'physical', 'confirmado',
			'Devolución Domicilio (Ruta 03)', 'Área de Cuarentena Técnica', 3.0, 3.0, 3.0, 0.0,
			2.85, 8.55, 'DEV', 'DEV-2026-00022', 'cuarentena',
			'Devolución de cliente por golpe en transporte. Enviado a cuarentena técnica para inspección de integridad.', 3,
			'2026-10-26 12:15:00', '2026-10-26 12:30:00'
		)
	");
	$db->exec("
		INSERT INTO sensitive_inventory_movements (
			movement_code, branch_id, product_id, event_type, warehouse_type, status,
			origin_location, dest_location, expected_amount, actual_amount, accepted_amount, difference_amount,
			unit_cost, financial_impact, document_type, document_code, disposition,
			notes, user_id, created_at, confirmed_at
		) VALUES (
			'MRM-2026-00067', 1, $milkId, 'return_exchange', 'physical', 'confirmado',
			'Área de Cuarentena Técnica', 'Baja Definitiva / Destrucción', 3.0, 3.0, 3.0, 0.0,
			2.85, 8.55, 'DEV', 'MRM-2026-00067', 'merma_definitiva',
			'Inspección sanitaria confirmó rotura de precinto y pérdida de hermeticidad. Baja definitiva a costo con registro en Merma.', 1,
			'2026-10-26 14:00:00', '2026-10-26 14:15:00'
		)
	");
}
