<?php

// DAIRY Etapa 36: metodología del XXI Censo Nacional de Mermas (Fenalco/Sensormatic, 2023, datos 2022).
//  - loss_benchmarks: cifras del Censo como REFERENCIA externa identificada (fuente, edición, año). Nunca son datos de DAIRY.
//  - loss_targets: merma tolerada y presupuestada (% de ventas netas) por año y alcance (global / departamento / sucursal).
//  - admin_error_cases (+ log): 10 errores administrativos del Censo + EPC/RFID con flujo
//    detección → evidencia → conciliación → aprobación → corrección → verificación → cierre (detección deduplicada).
//  - waste_records: modalidad de hurto, canal de detección, mercancía recuperada, destino final y peso.
//  - loss_causes FPO (fraude POS) y FEL (fraude electrónico), con motivo de merma activo.
//  - loss_prevention_controls: costo de las medidas preventivas para medir efectividad y retorno.
//  - loss_settings: supuestos de equivalencias sociales (vacíos hasta que el negocio los documente).
// Idempotente.

$db = \Grocy\Services\DatabaseService::GetInstance()->GetDbConnectionRaw();
$cols = fn(string $t) => array_column($db->query("PRAGMA table_info($t)")->fetchAll(\PDO::FETCH_ASSOC), 'name');

$db->exec("CREATE TABLE IF NOT EXISTS loss_benchmarks (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	source TEXT NOT NULL,
	edition TEXT NOT NULL,
	data_year INTEGER NOT NULL,
	metric TEXT NOT NULL,
	segment TEXT NOT NULL DEFAULT '',
	value REAL NOT NULL,
	unit TEXT NOT NULL,
	note TEXT,
	UNIQUE (edition, data_year, metric, segment)
)");
$src = 'Censo Nacional de Mermas y Prevención de Pérdidas — Fenalco / Sensormatic';
$ed = 'XXI (2023)';
$bm = [
	// métrica, segmento, valor, unidad, nota
	['merma_real_venta', 'total', 1.70, '% ventas', 'Merma real a precio de venta, promedio de cadenas participantes'],
	['merma_tolerada_venta', 'total', 1.39, '% ventas', 'Merma tolerada (presupuestada por las cadenas) a venta'],
	['merma_real_costo', 'total', 1.29, '% ventas', 'Merma real a costo'],
	['merma_tolerada_costo', 'total', 1.06, '% ventas', 'Merma tolerada a costo'],
	['merma_formato_venta', 'Supermercado y club de descuentos', 1.51, '% ventas', null],
	['merma_formato_costo', 'Supermercado y club de descuentos', 1.15, '% ventas', null],
	['merma_formato_venta', 'Tiendas por departamentos', 2.18, '% ventas', null],
	['merma_formato_costo', 'Tiendas por departamentos', 1.66, '% ventas', null],
	['merma_formato_venta', 'Mejoramiento del hogar', 1.02, '% ventas', null],
	['merma_formato_costo', 'Mejoramiento del hogar', 0.78, '% ventas', null],
	['merma_formato_venta', 'Droguerías', 0.41, '% ventas', null],
	['merma_formato_costo', 'Droguerías', 0.39, '% ventas', null],
	['merma_depto_venta', 'Carnes frescas', 2.28, '% ventas', null],
	['merma_depto_venta', 'Frescos', 2.13, '% ventas', null],
	['merma_depto_venta', 'Cuidado personal', 2.05, '% ventas', null],
	['merma_depto_venta', 'Cuidado del hogar', 1.96, '% ventas', null],
	['merma_depto_venta', 'Farmacia / Droguería', 1.78, '% ventas', null],
	['merma_depto_venta', 'Abarrotes', 1.31, '% ventas', null],
	['composicion', 'conocida', 57.98, '% merma', 'Merma operativa conocida'],
	['composicion', 'desconocida', 42.02, '% merma', 'Merma operativa desconocida'],
	['desconocida', 'robo_interno', 5.50, '% merma', 'Participación sobre la merma total'],
	['desconocida', 'robo_externo', 12.44, '% merma', null],
	['desconocida', 'error_administrativo', 1.87, '% merma', null],
	['desconocida', 'error_proveedores', 4.21, '% merma', null],
	['desconocida', 'otros', 18.00, '% merma', 'Causas no tipificadas'],
	['error_admin', 'DOC', 15.8, '% frecuencia', 'Error en la elaboración y grabación de documentos'],
	['error_admin', 'REC', 13.6, '% frecuencia', 'Error en el registro en la recepción de mercancía'],
	['error_admin', 'BAJ', 11.0, '% frecuencia', 'No registro de desperdicios y bajas'],
	['error_admin', 'CON', 10.3, '% frecuencia', 'Error en conteo de toma física'],
	['error_admin', 'PRE', 9.6, '% frecuencia', 'No registro o registro errado de variaciones de precio'],
	['error_admin', 'DES', 8.1, '% frecuencia', 'Envío de mercancía a destino errado'],
	['error_admin', 'INT', 8.1, '% frecuencia', 'Problemas de integridad de procesos de sistemas'],
	['error_admin', 'MAR', 7.2, '% frecuencia', 'Error en la marcación de productos'],
	['error_admin', 'CAJ', 7.2, '% frecuencia', 'Error en la caja registradora'],
	['error_admin', 'DDS', 4.5, '% frecuencia', 'Dobles despachos de mercancía'],
	['recuperacion', 'merma_reducida', 17.47, '% merma', 'Porcentaje de la merma disminuido gracias a las recuperaciones'],
	['fraude_virtual', 'total', 0.17, '% ventas', 'Fraude en tiendas virtuales'],
	['impacto', 'merma_estimada', 753005488840, 'COP', 'Merma estimada del comercio colombiano'],
	['impacto', 'empleos_equivalentes', 37568, 'empleos/año', 'Equivalencia publicada por el Censo (supuesto implícito: merma ÷ costo anual de un empleo)'],
	['impacto', 'vis_equivalentes', 17594, 'viviendas', 'Equivalencia publicada por el Censo en Vivienda de Interés Social'],
];
$ins = $db->prepare('INSERT OR IGNORE INTO loss_benchmarks (source, edition, data_year, metric, segment, value, unit, note) VALUES (?, ?, 2022, ?, ?, ?, ?, ?)');
foreach ($bm as $b)
{
	$ins->execute([$src, $ed, $b[0], $b[1], $b[2], $b[3], $b[4]]);
}

$db->exec("CREATE TABLE IF NOT EXISTS loss_targets (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	year INTEGER NOT NULL,
	scope_type TEXT NOT NULL DEFAULT 'global' CHECK (scope_type IN ('global', 'department', 'branch')),
	scope_value TEXT NOT NULL DEFAULT '',
	tolerated_pct REAL,
	budget_pct REAL,
	basis TEXT NOT NULL DEFAULT 'cost' CHECK (basis IN ('cost', 'sale')),
	source TEXT,
	updated_by_user_id INTEGER,
	updated_timestamp DATETIME DEFAULT (datetime('now', 'localtime')),
	UNIQUE (year, scope_type, scope_value)
)");

$db->exec("CREATE TABLE IF NOT EXISTS admin_error_cases (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	case_number TEXT UNIQUE,
	category TEXT NOT NULL CHECK (category IN ('DOC', 'REC', 'BAJ', 'CON', 'PRE', 'DES', 'INT', 'MAR', 'CAJ', 'DDS', 'EPC')),
	source TEXT NOT NULL DEFAULT 'manual' CHECK (source IN ('auto', 'manual')),
	detection_key TEXT UNIQUE,
	branch_id INTEGER,
	product_id INTEGER,
	ref_type TEXT,
	ref_id INTEGER,
	ref_doc TEXT,
	title TEXT NOT NULL,
	detail TEXT,
	expected_value TEXT,
	actual_value TEXT,
	impact_cost REAL NOT NULL DEFAULT 0,
	status TEXT NOT NULL DEFAULT 'detected' CHECK (status IN ('detected', 'evidence', 'reconciled', 'approved', 'corrected', 'verified', 'closed', 'dismissed')),
	evidence_path TEXT,
	evidence_notes TEXT,
	reconciliation_notes TEXT,
	correction_notes TEXT,
	resolution TEXT,
	due_date DATE,
	created_by_user_id INTEGER,
	approved_by_user_id INTEGER,
	corrected_by_user_id INTEGER,
	verified_by_user_id INTEGER,
	created_timestamp DATETIME DEFAULT (datetime('now', 'localtime')),
	updated_timestamp DATETIME DEFAULT (datetime('now', 'localtime')),
	closed_timestamp DATETIME
)");
$db->exec('CREATE INDEX IF NOT EXISTS ix_admin_error_cases_status ON admin_error_cases (status, category)');
$db->exec("CREATE TABLE IF NOT EXISTS admin_error_case_log (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	case_id INTEGER NOT NULL,
	from_status TEXT,
	to_status TEXT NOT NULL,
	notes TEXT,
	user_id INTEGER,
	created_timestamp DATETIME DEFAULT (datetime('now', 'localtime'))
)");

$wc = $cols('waste_records');
foreach (['theft_modality' => 'TEXT', 'detection_channel' => 'TEXT', 'recovered_goods_value' => 'REAL', 'final_destination' => 'TEXT', 'weight_kg' => 'REAL'] as $c => $t)
{
	if (!in_array($c, $wc))
	{
		$db->exec("ALTER TABLE waste_records ADD COLUMN $c $t");
	}
}

$insC = $db->prepare('INSERT OR IGNORE INTO loss_causes (code, name, loss_class, responsible_area, approval_rule, approval_threshold, treatment, recoverable, is_definitive, investigation_days) VALUES (?, ?, ?, ?, ?, 0, ?, 1, 1, 7)');
$insC->execute(['FPO', 'Fraude en POS (devoluciones ficticias, anulaciones, bonos)', 'robo_interno', 'Prevención de pérdidas', 'Solo prevención de pérdidas o gerente; revisión humana obligatoria', 'Auditoría de transacciones POS; las alertas no prueban culpabilidad']);
$insC->execute(['FEL', 'Fraude electrónico (tarjeta, transferencia falsa, clonación de facturas)', 'robo_externo', 'Prevención de pérdidas', 'Prevención confirma con el soporte del medio de pago', 'Conciliar con el adquirente o el banco; denuncia y recuperación']);
foreach ([['FPO', 'FPO01', 'Fraude en POS confirmado'], ['FEL', 'FEL01', 'Fraude electrónico confirmado']] as [$cause, $code, $name])
{
	if (intval($db->query("SELECT COUNT(*) FROM waste_reasons WHERE loss_cause_code = '$cause'")->fetchColumn()) === 0)
	{
		$db->prepare("INSERT INTO waste_reasons (name, description, requires_evidence, requires_approval, active, code, category, responsible_area, risk_level, frequency, economic_impact, loss_category, akcal_classification, shrink_type, censo_category, loss_cause_code)
			VALUES (?, 'Solo casos confirmados tras revisión humana. Sin datos personales en la merma: la investigación vive en Prevención de pérdidas.', 1, 1, 1, ?, 'Seguridad', 'Prevención de pérdidas', 'Alto', 'Ocasional', 'Alto', 'operativa', 'operativa', 'desconocida', 'robo', ?)")
			->execute([$name, $code, $cause]);
	}
}

$db->exec("CREATE TABLE IF NOT EXISTS loss_prevention_controls (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	name TEXT NOT NULL,
	control_type TEXT NOT NULL,
	branch_id INTEGER,
	target_class TEXT,
	target_cause TEXT,
	start_date DATE NOT NULL,
	end_date DATE,
	monthly_cost REAL NOT NULL DEFAULT 0,
	notes TEXT,
	active INTEGER NOT NULL DEFAULT 1,
	created_by_user_id INTEGER,
	created_timestamp DATETIME DEFAULT (datetime('now', 'localtime'))
)");

$settings = [
	['social_country', 'Colombia', 'País de los supuestos de equivalencias sociales'],
	['social_currency', 'COP', 'Moneda de los supuestos'],
	['social_year', '', 'Año de vigencia de los supuestos'],
	['social_job_annual_cost', '', 'Costo anual total de un empleo (salario + prestaciones). Vacío = no se calcula'],
	['social_job_source', '', 'Fuente documentada del costo anual del empleo'],
	['social_vis_value', '', 'Valor de una Vivienda de Interés Social. Vacío = no se calcula'],
	['social_vis_source', '', 'Fuente documentada del valor VIS'],
	['admin_error_due_days', '5', 'Plazo para cerrar un caso de error administrativo'],
];
$ins = $db->prepare('INSERT OR IGNORE INTO loss_settings (key, value, description) VALUES (?, ?, ?)');
foreach ($settings as $s)
{
	$ins->execute($s);
}

if (class_exists('\Grocy\Helpers\DairyChangeLog'))
{
	\Grocy\Helpers\DairyChangeLog::InstallTriggers($db);
}
