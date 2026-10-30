<?php

// DAIRY Etapa 35: Inteligencia de Mermas, Pérdidas y Prevención.
//  - loss_causes: matriz canónica de causales (código único, clase, responsable, regla de aprobación, tratamiento).
//    'INV' = investigación pendiente (no es clasificación definitiva).
//  - waste_reasons.loss_cause_code: cada motivo existente apunta a una causal canónica (mapeo por palabras clave).
//  - branches.format / sales_area_m2: comparación entre formatos sin confundir pérdida absoluta y relativa.
//  - price_discrepancies (+ log): flujo Detección → … → Cierre de errores de precio, marcación, código de barras y EPC.
//  - perishable_yield_lots: rendimiento de cárnicos, avícolas y fruver (tolerancia técnica vs. merma evitable).
//  - loss_settings: tolerancias, rangos de temperatura y definición del indicador VIS (vacía hasta que el negocio la defina).
// Idempotente.

$db = \Grocy\Services\DatabaseService::GetInstance()->GetDbConnectionRaw();
$cols = fn(string $t) => array_column($db->query("PRAGMA table_info($t)")->fetchAll(\PDO::FETCH_ASSOC), 'name');

$db->exec("CREATE TABLE IF NOT EXISTS loss_causes (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	code TEXT NOT NULL UNIQUE,
	name TEXT NOT NULL,
	description TEXT,
	loss_class TEXT NOT NULL CHECK (loss_class IN ('operativa', 'comercial', 'ajuste', 'averia', 'robo_interno', 'robo_externo', 'investigacion')),
	responsible_area TEXT,
	approval_rule TEXT,
	approval_threshold REAL NOT NULL DEFAULT 0,
	treatment TEXT,
	recoverable INTEGER NOT NULL DEFAULT 0,
	is_definitive INTEGER NOT NULL DEFAULT 1,
	investigation_days INTEGER NOT NULL DEFAULT 7,
	active INTEGER NOT NULL DEFAULT 1,
	row_created_timestamp DATETIME DEFAULT (datetime('now', 'localtime'))
)");

$causes = [
	// code, name, class, área responsable, regla de aprobación, umbral $, tratamiento, recuperable
	['ROT', 'Rotura de producto', 'operativa', 'Operaciones de tienda', 'Supervisor aprueba; > umbral requiere gerente', 50, 'Registrar con foto, revisar exhibición y manipulación', 0],
	['EXP', 'Expiración / vencimiento', 'comercial', 'Comercial / Categoría', 'Supervisor aprueba; reincidencia por SKU escala a gerente', 100, 'Ajustar pedido y rotación FEFO; rebaja preventiva', 0],
	['DEX', 'Daño externo (proveedor / cliente)', 'averia', 'Recepción', 'Supervisor aprueba con evidencia', 50, 'Reclamar al proveedor cuando aplique', 1],
	['CFR', 'Falla de cadena de frío', 'operativa', 'Mantenimiento / Calidad', 'Calidad evalúa; gerente autoriza la baja', 0, 'Cuarentena, evaluación de calidad y decisión autorizada', 0],
	['AVE', 'Avería', 'averia', 'Operaciones de tienda', 'Supervisor aprueba; > umbral requiere gerente', 50, 'Recuperar unidades vendibles; baja del resto', 1],
	['AJI', 'Ajuste injustificado de inventario', 'ajuste', 'Control de inventario', 'Gerente aprueba siempre', 0, 'Reconteo ciego e investigación de la diferencia', 0],
	['EPR', 'Error de precio', 'comercial', 'Comercial / Precios', 'Gerente aprueba la corrección; sincronizar POS', 0, 'Flujo de control de precios', 0],
	['EMA', 'Error de marcación / etiquetado', 'comercial', 'Comercial / Precios', 'Supervisor valida; gerente aprueba', 0, 'Reetiquetar y verificar góndola', 0],
	['EPC', 'Error EPC / RFID', 'comercial', 'Control de inventario', 'Gerente aprueba', 0, 'Conciliar lectura RFID, catálogo e inventario', 0],
	['RIN', 'Robo interno', 'robo_interno', 'Prevención de pérdidas', 'Solo prevención de pérdidas o gerente; revisión humana obligatoria', 0, 'Investigación confidencial; las alertas no prueban culpabilidad', 1],
	['REX', 'Robo externo', 'robo_externo', 'Prevención de pérdidas', 'Supervisor registra; prevención confirma', 0, 'Reforzar zonas calientes; denuncia y recuperación', 1],
	['DER', 'Derrames y pérdidas de líquidos', 'operativa', 'Operaciones de tienda', 'Supervisor aprueba', 50, 'Revisar empaques y estiba', 0],
	['MAN', 'Pérdida por manipulación', 'operativa', 'Operaciones de tienda', 'Supervisor aprueba', 50, 'Capacitación y procedimiento de manipulación', 0],
	['REC', 'Diferencias de recepción', 'operativa', 'Recepción', 'Supervisor de recepción aprueba', 0, 'Reclamo al proveedor y 3-way match', 1],
	['DET', 'Pérdida por deterioro', 'operativa', 'Calidad', 'Supervisor aprueba', 50, 'Revisar conservación y rotación', 0],
	['FEF', 'Error de rotación FEFO', 'operativa', 'Operaciones de tienda', 'Supervisor aprueba', 50, 'Reordenar exhibición por vencimiento', 0],
	['TRA', 'Daño durante traslado / despacho', 'operativa', 'Logística', 'Supervisor aprueba', 50, 'Revisar embalaje y transportista', 1],
	['ALM', 'Falla de almacenamiento / conservación', 'operativa', 'Bodega', 'Supervisor aprueba', 50, 'Corregir clase de almacenamiento y estiba', 0],
	['NOV', 'Producto no vendido / sobre-pedido', 'comercial', 'Comercial / Compras', 'Supervisor aprueba', 100, 'Ajustar pronóstico y pedido', 0],
	['LIQ', 'Rebajas, liquidaciones, muestras y promociones', 'comercial', 'Comercial', 'Gerente aprueba la promoción', 0, 'Medir retorno de la promoción', 0],
	['INV', 'Pendiente de investigación (origen no confirmado)', 'investigacion', 'Prevención de pérdidas', 'No es definitiva: requiere investigación con plazo y responsable', 0, 'Reclasificar a una causal definitiva al cerrar', 0],
];
$ins = $db->prepare('INSERT OR IGNORE INTO loss_causes (code, name, loss_class, responsible_area, approval_rule, approval_threshold, treatment, recoverable, is_definitive, investigation_days) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
foreach ($causes as $c)
{
	$ins->execute([$c[0], $c[1], $c[2], $c[3], $c[4], $c[5], $c[6], $c[7], $c[0] === 'INV' ? 0 : 1, $c[0] === 'INV' ? 5 : 7]);
}

if (!in_array('loss_cause_code', $cols('waste_reasons')))
{
	$db->exec('ALTER TABLE waste_reasons ADD COLUMN loss_cause_code TEXT');
}

// Mapeo de motivos existentes → causal canónica (orden = prioridad; primera coincidencia gana)
$map = [
	['hurto interno', 'RIN'], ['robo interno', 'RIN'], ['malintencionado', 'RIN'],
	['hurto externo', 'REX'], ['robo externo', 'REX'], ['fardero', 'REX'], ['hurto', 'REX'],
	['no identificado', 'INV'], ['faltante', 'INV'], ['desconocid', 'INV'],
	['cadena de frío', 'CFR'], ['refrigeración', 'CFR'], ['temperatura', 'CFR'],
	['fefo', 'FEF'], ['rotación', 'FEF'],
	['etiquet', 'EMA'], ['marcación', 'EMA'], ['fecha ilegible', 'EMA'],
	['precio', 'EPR'], ['epc', 'EPC'], ['rfid', 'EPC'],
	['diferencia de inventario', 'AJI'], ['descuadre', 'AJI'], ['administrativo', 'AJI'], ['facturación', 'AJI'],
	['traslado', 'TRA'], ['transporte', 'TRA'], ['despacho', 'TRA'],
	['no recibida', 'REC'], ['proveedor', 'REC'], ['recepción', 'REC'], ['calidad defectuosa', 'REC'],
	['derrame', 'DER'], ['líquido', 'DER'],
	['mal almacenado', 'ALM'], ['almacenamiento', 'ALM'], ['incompatibilidad', 'ALM'],
	['sobre-pedido', 'NOV'], ['exceso de abastecimiento', 'NOV'], ['obsoleto', 'NOV'], ['no vendido', 'NOV'],
	['muestra', 'LIQ'], ['degustación', 'LIQ'], ['promoci', 'LIQ'], ['rebaja', 'LIQ'], ['liquidaci', 'LIQ'],
	['vencid', 'EXP'], ['vencimiento', 'EXP'], ['caducidad', 'EXP'],
	['empaque', 'AVE'], ['avería', 'AVE'], ['averia', 'AVE'],
	['rotura', 'ROT'], ['ruptura', 'ROT'], ['roto', 'ROT'],
	['garantía', 'DEX'], ['dañado', 'DEX'],
	['manipulación', 'MAN'],
	['deterior', 'DET'], ['fermentación', 'DET'], ['alerta sanitaria', 'DET'], ['devolución de cliente', 'DET'],
];
$fallback = ['operativa' => 'MAN', 'comercial' => 'NOV', 'ajuste' => 'AJI', 'averia' => 'AVE'];
$upd = $db->prepare('UPDATE waste_reasons SET loss_cause_code = ? WHERE id = ?');
foreach ($db->query("SELECT id, name, loss_category FROM waste_reasons WHERE IFNULL(loss_cause_code, '') = ''")->fetchAll(\PDO::FETCH_ASSOC) as $r)
{
	$name = mb_strtolower($r['name']);
	$code = null;
	foreach ($map as [$needle, $c])
	{
		if (mb_strpos($name, $needle) !== false)
		{
			$code = $c;
			break;
		}
	}
	$upd->execute([$code ?? ($fallback[$r['loss_category']] ?? 'INV'), $r['id']]);
}

// Motivo activo para diferencias sin origen confirmado: nace como investigación (no es clasificación definitiva)
if (intval($db->query("SELECT COUNT(*) FROM waste_reasons WHERE loss_cause_code = 'INV' AND active = 1")->fetchColumn()) === 0)
{
	$db->exec("INSERT INTO waste_reasons (name, description, requires_evidence, requires_approval, active, code, category, responsible_area, risk_level, frequency, economic_impact, loss_category, akcal_classification, shrink_type, censo_category, loss_cause_code)
		VALUES ('Diferencia sin origen confirmado (investigación)', 'Se abre una investigación con plazo y responsable; al cerrarla se reclasifica a una causal definitiva. Nunca se etiqueta como robo sin confirmación.',
		0, 1, 1, 'INV01', 'General', 'Prevención de pérdidas', 'Alto', 'Ocasional', 'Medio', 'operativa', 'operativa', 'desconocida', 'otros', 'INV')");
}

// Formato comercial y superficie de cada sucursal (null = sin dato confiable)
$bc = $cols('branches');
if (!in_array('format', $bc))
{
	$db->exec('ALTER TABLE branches ADD COLUMN format TEXT');
}
if (!in_array('sales_area_m2', $bc))
{
	$db->exec('ALTER TABLE branches ADD COLUMN sales_area_m2 REAL');
}

$db->exec("CREATE TABLE IF NOT EXISTS price_discrepancies (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	uid TEXT NOT NULL UNIQUE,
	kind TEXT NOT NULL CHECK (kind IN ('pos_vs_authorized', 'shelf_vs_pos', 'expired_promo', 'discount', 'wrong_label', 'barcode_sku', 'branch_diff', 'epc')),
	source TEXT NOT NULL DEFAULT 'manual',
	product_id INTEGER,
	branch_id INTEGER,
	barcode TEXT,
	authorized_price REAL,
	pos_price REAL,
	shelf_price REAL,
	units_affected REAL NOT NULL DEFAULT 0,
	impact_type TEXT CHECK (impact_type IN ('sobrecobro', 'subcobro', 'devolucion', 'perdida_comercial', 'sin_impacto')),
	impact_amount REAL NOT NULL DEFAULT 0,
	impact_verified INTEGER NOT NULL DEFAULT 0,
	proposed_price REAL,
	status TEXT NOT NULL DEFAULT 'detected' CHECK (status IN ('detected', 'validated', 'evidence', 'corrected', 'approved', 'pos_synced', 'verified', 'closed', 'rejected')),
	evidence_path TEXT,
	pos_sync_reference TEXT,
	detail TEXT,
	notes TEXT,
	detected_by_user_id INTEGER,
	corrected_by_user_id INTEGER,
	approved_by_user_id INTEGER,
	created_timestamp DATETIME DEFAULT (datetime('now', 'localtime')),
	updated_timestamp DATETIME
)");
$db->exec('CREATE INDEX IF NOT EXISTS ix_price_disc_status ON price_discrepancies (status, branch_id)');
$db->exec("CREATE TABLE IF NOT EXISTS price_discrepancy_log (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	discrepancy_id INTEGER NOT NULL,
	from_status TEXT,
	to_status TEXT NOT NULL,
	user_id INTEGER,
	note TEXT,
	created_timestamp DATETIME DEFAULT (datetime('now', 'localtime'))
)");

$db->exec("CREATE TABLE IF NOT EXISTS perishable_yield_lots (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	uid TEXT NOT NULL UNIQUE,
	kind TEXT NOT NULL CHECK (kind IN ('pollo', 'carne', 'fruver')),
	product_id INTEGER NOT NULL,
	branch_id INTEGER NOT NULL,
	lot_number TEXT,
	received_date DATE NOT NULL,
	received_kg REAL NOT NULL CHECK (received_kg > 0),
	drain_water_kg REAL NOT NULL DEFAULT 0,
	drain_blood_kg REAL NOT NULL DEFAULT 0,
	dehydration_kg REAL NOT NULL DEFAULT 0,
	ripening_kg REAL NOT NULL DEFAULT 0,
	bruise_kg REAL NOT NULL DEFAULT 0,
	transport_kg REAL NOT NULL DEFAULT 0,
	deterioration_kg REAL NOT NULL DEFAULT 0,
	expired_kg REAL NOT NULL DEFAULT 0,
	discarded_kg REAL NOT NULL DEFAULT 0,
	sold_kg REAL NOT NULL DEFAULT 0,
	remaining_kg REAL,
	unit_cost_kg REAL NOT NULL DEFAULT 0,
	sale_price_kg REAL,
	tolerance_natural_pct REAL NOT NULL,
	tolerance_avoidable_pct REAL NOT NULL,
	status TEXT NOT NULL DEFAULT 'open' CHECK (status IN ('open', 'closed')),
	notes TEXT,
	created_by_user_id INTEGER,
	closed_by_user_id INTEGER,
	closed_timestamp DATETIME,
	created_timestamp DATETIME DEFAULT (datetime('now', 'localtime'))
)");

$db->exec('CREATE TABLE IF NOT EXISTS loss_settings (key TEXT PRIMARY KEY, value TEXT, description TEXT)');
$settings = [
	['tolerance_natural_pollo', '3', 'Drenaje (agua + sangre) tolerado en pollo, % del peso recibido'],
	['tolerance_natural_carne', '2', 'Drenaje tolerado en carne, % del peso recibido'],
	['tolerance_natural_fruver', '5', 'Deshidratación tolerada en fruver, % del peso recibido'],
	['tolerance_avoidable_pct', '2', 'Merma evitable aceptada (descarte, golpes, deterioro), % del peso recibido'],
	['cold_max_refrigerated', '5', 'Temperatura máxima en refrigeración (°C)'],
	['cold_min_refrigerated', '0', 'Temperatura mínima en refrigeración (°C)'],
	['cold_max_freezer', '-15', 'Temperatura máxima en congelación (°C)'],
	['vis_definition', '', 'Definición exacta del indicador VIS en el negocio (pendiente de definir: no se calcula hasta tenerla)'],
	['vis_source', '', 'Fuente de datos del indicador VIS'],
	['investigation_default_days', '5', 'Plazo por defecto de una investigación pendiente'],
];
$ins = $db->prepare('INSERT OR IGNORE INTO loss_settings (key, value, description) VALUES (?, ?, ?)');
foreach ($settings as $s)
{
	$ins->execute($s);
}

// Triggers de historial de waste_reasons/branches fijan columnas: reinstalarlos con las nuevas
if (class_exists('\Grocy\Helpers\DairyChangeLog'))
{
	\Grocy\Helpers\DairyChangeLog::InstallTriggers($db);
}
