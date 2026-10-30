<?php

// Enterprise Platform Migration: Intelligent Waste Matrix (Causales M01-M14 Alkosto + D01-D10 Dairy)
// Advanced Case Tracking, Workflow, Prevention & Audit Logs
// Executed inside DatabaseMigrationService context

use Grocy\Services\DatabaseService;

$db = DatabaseService::GetInstance();

// 1. Extend waste_reasons table
$cols = $db->ExecuteDbQuery("PRAGMA table_info(waste_reasons)")->fetchAll(\PDO::FETCH_ASSOC);
$existing = array_column($cols, 'name');

$reasonsColsToAdd = [
	'code' => "ALTER TABLE waste_reasons ADD COLUMN code TEXT DEFAULT 'M00';",
	'category' => "ALTER TABLE waste_reasons ADD COLUMN category TEXT DEFAULT 'General';",
	'responsible_area' => "ALTER TABLE waste_reasons ADD COLUMN responsible_area TEXT DEFAULT 'Operaciones';",
	'preventive_actions' => "ALTER TABLE waste_reasons ADD COLUMN preventive_actions TEXT DEFAULT NULL;",
	'associated_kpi' => "ALTER TABLE waste_reasons ADD COLUMN associated_kpi TEXT DEFAULT NULL;",
	'risk_level' => "ALTER TABLE waste_reasons ADD COLUMN risk_level TEXT DEFAULT 'Medio';",
	'frequency' => "ALTER TABLE waste_reasons ADD COLUMN frequency TEXT DEFAULT 'Ocasional';",
	'economic_impact' => "ALTER TABLE waste_reasons ADD COLUMN economic_impact TEXT DEFAULT 'Medio';",
	'icon' => "ALTER TABLE waste_reasons ADD COLUMN icon TEXT DEFAULT 'fa-solid fa-tag';",
	'color_class' => "ALTER TABLE waste_reasons ADD COLUMN color_class TEXT DEFAULT 'secondary';"
];

foreach ($reasonsColsToAdd as $col => $sql)
{
	if (!in_array($col, $existing))
	{
		$db->ExecuteDbStatement($sql);
	}
}

// 2. Extend waste_records table
$colsRec = $db->ExecuteDbQuery("PRAGMA table_info(waste_records)")->fetchAll(\PDO::FETCH_ASSOC);
$existingRec = array_column($colsRec, 'name');

$recColsToAdd = [
	'case_number' => "ALTER TABLE waste_records ADD COLUMN case_number TEXT DEFAULT NULL;",
	'sub_reason' => "ALTER TABLE waste_records ADD COLUMN sub_reason TEXT DEFAULT NULL;",
	'batch_number' => "ALTER TABLE waste_records ADD COLUMN batch_number TEXT DEFAULT NULL;",
	'best_before_date' => "ALTER TABLE waste_records ADD COLUMN best_before_date DATE DEFAULT NULL;",
	'workflow_status' => "ALTER TABLE waste_records ADD COLUMN workflow_status TEXT DEFAULT 'detected';",
	'investigator_user_id' => "ALTER TABLE waste_records ADD COLUMN investigator_user_id INTEGER DEFAULT NULL;",
	'due_date' => "ALTER TABLE waste_records ADD COLUMN due_date DATE DEFAULT NULL;",
	'resolution_notes' => "ALTER TABLE waste_records ADD COLUMN resolution_notes TEXT DEFAULT NULL;",
	'corrective_action_plan' => "ALTER TABLE waste_records ADD COLUMN corrective_action_plan TEXT DEFAULT NULL;",
	'root_cause_analysis' => "ALTER TABLE waste_records ADD COLUMN root_cause_analysis TEXT DEFAULT NULL;",
	'preventive_measures_implemented' => "ALTER TABLE waste_records ADD COLUMN preventive_measures_implemented TEXT DEFAULT NULL;",
	'financial_recovery_amount' => "ALTER TABLE waste_records ADD COLUMN financial_recovery_amount REAL DEFAULT 0.0;",
	'verified_by_user_id' => "ALTER TABLE waste_records ADD COLUMN verified_by_user_id INTEGER DEFAULT NULL;",
	'closed_timestamp' => "ALTER TABLE waste_records ADD COLUMN closed_timestamp DATETIME DEFAULT NULL;",
	'inventory_deducted' => "ALTER TABLE waste_records ADD COLUMN inventory_deducted INTEGER DEFAULT 1;",
	'idempotency_key' => "ALTER TABLE waste_records ADD COLUMN idempotency_key TEXT DEFAULT NULL;"
];

foreach ($recColsToAdd as $col => $sql)
{
	if (!in_array($col, $existingRec))
	{
		$db->ExecuteDbStatement($sql);
	}
}

// 3. Create waste_action_logs table for audit & workflow history
$db->ExecuteDbStatement("
CREATE TABLE IF NOT EXISTS waste_action_logs (
	id INTEGER PRIMARY KEY AUTOINCREMENT UNIQUE,
	waste_record_id INTEGER NOT NULL REFERENCES waste_records(id) ON DELETE CASCADE,
	user_id INTEGER NOT NULL REFERENCES users(id),
	previous_status TEXT,
	new_status TEXT,
	action_title TEXT NOT NULL,
	action_details TEXT,
	created_timestamp DATETIME DEFAULT (datetime('now', 'localtime'))
);
");

// 4. Seed / Synchronize the 24 Standardized Causales (14 Alkosto M01-M14 + 10 Dairy D01-D10)
$standardCausales = [
	// --- ALKOSTO M01 - M14 ---
	[
		'code' => 'M01',
		'name' => 'Avería en manipulación / Tienda',
		'category' => 'Averías',
		'description' => 'Rotura o daño ocasionado por caída, mala estiba o manipulación por clientes o personal en piso de venta.',
		'responsible_area' => 'Piso de Venta / Reposición',
		'preventive_actions' => 'Capacitación continua en estiba segura; no sobrellenar góndolas; uso obligatorio de carretillas protegidas.',
		'associated_kpi' => '% Merma Manipulación Tienda',
		'risk_level' => 'Medio',
		'frequency' => 'Frecuente',
		'economic_impact' => 'Medio',
		'requires_evidence' => 1,
		'requires_approval' => 0,
		'icon' => 'fa-solid fa-box-open',
		'color_class' => 'warning'
	],
	[
		'code' => 'M02',
		'name' => 'Avería en transporte / Despacho',
		'category' => 'Despacho y Transporte',
		'description' => 'Mercancía aplastada o rota durante el traslado entre centro de distribución y sucursal.',
		'responsible_area' => 'Logística y Transporte',
		'preventive_actions' => 'Trincado obligatorio de pallets con fajas y esquineros; auditoría de estiba antes de cerrar compuertas de camión.',
		'associated_kpi' => '% Daño en Transporte',
		'risk_level' => 'Alto',
		'frequency' => 'Ocasional',
		'economic_impact' => 'Alto',
		'requires_evidence' => 1,
		'requires_approval' => 1,
		'icon' => 'fa-solid fa-truck-ramp-box',
		'color_class' => 'danger'
	],
	[
		'code' => 'M03',
		'name' => 'Hurto interno / Malintencionado',
		'category' => 'Hurto y Seguridad',
		'description' => 'Sustracción no autorizada de producto perpetrada por colaboradores o personal de contrata.',
		'responsible_area' => 'Seguridad y Vigilancia',
		'preventive_actions' => 'Control de accesos biométrico a bodegas; monitoreo CCTV en puntos ciegos; arqueos sorpresivos de inventario.',
		'associated_kpi' => 'Tasa de Pérdida Desconocida Interna',
		'risk_level' => 'Crítico',
		'frequency' => 'Ocasional',
		'economic_impact' => 'Alto',
		'requires_evidence' => 0,
		'requires_approval' => 1,
		'icon' => 'fa-solid fa-user-secret',
		'color_class' => 'danger'
	],
	[
		'code' => 'M04',
		'name' => 'Hurto externo / Farderos',
		'category' => 'Hurto y Seguridad',
		'description' => 'Sustracción de producto por clientes o visitantes en sala de ventas sin pasar por línea de caja.',
		'responsible_area' => 'Seguridad / Tienda',
		'preventive_actions' => 'Etiquetado antihurto acústico-magnético en quesos finos y artículos de alto valor; patrullaje disuasivo.',
		'associated_kpi' => 'Índice de Sustracción en Sala',
		'risk_level' => 'Medio',
		'frequency' => 'Frecuente',
		'economic_impact' => 'Medio',
		'requires_evidence' => 0,
		'requires_approval' => 1,
		'icon' => 'fa-solid fa-shield-halved',
		'color_class' => 'warning'
	],
	[
		'code' => 'M05',
		'name' => 'Mercancía no recibida / Error proveedor',
		'category' => 'Recepción y Proveedores',
		'description' => 'Faltante de producto facturado por el proveedor pero ausente físicamente al descargar en muelle.',
		'responsible_area' => 'Recepción de Mercancía',
		'preventive_actions' => 'Conteo ciego al 100% en plataforma; cotejo automático con orden de compra antes de firmar remisión conforme.',
		'associated_kpi' => 'OTIF Recepción - Exactitud Factura',
		'risk_level' => 'Alto',
		'frequency' => 'Ocasional',
		'economic_impact' => 'Alto',
		'requires_evidence' => 1,
		'requires_approval' => 1,
		'icon' => 'fa-solid fa-file-invoice-dollar',
		'color_class' => 'warning'
	],
	[
		'code' => 'M06',
		'name' => 'Diferencia de inventario / Descuadre cíclico',
		'category' => 'Inventario y Auditoría',
		'description' => 'Discrepancia no explicada entre existencias registradas en sistema y conteo físico en auditoría.',
		'responsible_area' => 'Control de Inventarios',
		'preventive_actions' => 'Conteo cíclico diario por clase ABC; conciliación de diferencias en menos de 24 horas; bloqueo de movimientos durante conteo.',
		'associated_kpi' => 'Exactitud de Registro de Inventario (IRA)',
		'risk_level' => 'Medio',
		'frequency' => 'Frecuente',
		'economic_impact' => 'Medio',
		'requires_evidence' => 0,
		'requires_approval' => 1,
		'icon' => 'fa-solid fa-scale-unbalanced',
		'color_class' => 'info'
	],
	[
		'code' => 'M07',
		'name' => 'Error en despacho a sucursal / Traslado',
		'category' => 'Despacho y Traslados',
		'description' => 'Diferencia cuantitativa o de código en transferencias inter-sucursales por error en picking.',
		'responsible_area' => 'Centro de Distribución',
		'preventive_actions' => 'Lectura con escáner de código de barras para cada bulto al preparar transferencias; confirmación de recepción en destino.',
		'associated_kpi' => 'Exactitud en Picking y Traslados',
		'risk_level' => 'Medio',
		'frequency' => 'Ocasional',
		'economic_impact' => 'Medio',
		'requires_evidence' => 0,
		'requires_approval' => 0,
		'icon' => 'fa-solid fa-dolly',
		'color_class' => 'info'
	],
	[
		'code' => 'M08',
		'name' => 'Devolución de cliente en mal estado',
		'category' => 'Devoluciones',
		'description' => 'Producto devuelto por el comprador que perdió cadena de frío o presenta empaque vulnerado.',
		'responsible_area' => 'Servicio al Cliente / Caja',
		'preventive_actions' => 'Inspección organoléptica y toma de temperatura al recibir devolución antes de procesar nota de crédito.',
		'associated_kpi' => '% Devoluciones No Aptas',
		'risk_level' => 'Bajo',
		'frequency' => 'Ocasional',
		'economic_impact' => 'Bajo',
		'requires_evidence' => 1,
		'requires_approval' => 0,
		'icon' => 'fa-solid fa-rotate-left',
		'color_class' => 'secondary'
	],
	[
		'code' => 'M09',
		'name' => 'Producto obsoleto / Fuera de catálogo',
		'category' => 'Obsolescencia',
		'description' => 'Mercancía descontinuada comercialmente sin venta durante más de 60 días.',
		'responsible_area' => 'Compras / Comercial',
		'preventive_actions' => 'Alertas automáticas de baja rotación a los 40 días; armado de paquetes promocionales antes del vencimiento.',
		'associated_kpi' => 'Días de Inventario Sin Rotación (DSI)',
		'risk_level' => 'Medio',
		'frequency' => 'Rara',
		'economic_impact' => 'Medio',
		'requires_evidence' => 0,
		'requires_approval' => 1,
		'icon' => 'fa-solid fa-calendar-xmark',
		'color_class' => 'secondary'
	],
	[
		'code' => 'M10',
		'name' => 'Error administrativo / Facturación errónea',
		'category' => 'Administración',
		'description' => 'Ajuste contable derivado de captura incorrecta de unidades, bonificaciones o cancelaciones extemporáneas.',
		'responsible_area' => 'Administración y Finanzas',
		'preventive_actions' => 'Doble validación en notas de crédito manuales; sincronización automática entre POS y contabilidad.',
		'associated_kpi' => 'Tasa de Ajustes Administrativos',
		'risk_level' => 'Bajo',
		'frequency' => 'Rara',
		'economic_impact' => 'Bajo',
		'requires_evidence' => 0,
		'requires_approval' => 1,
		'icon' => 'fa-solid fa-calculator',
		'color_class' => 'secondary'
	],
	[
		'code' => 'M11',
		'name' => 'Mercancía vencida en bodega',
		'category' => 'Inventario y Vencimientos',
		'description' => 'Lotes de producto que expiraron almacenados en bodega principal antes de ser enviados a tienda.',
		'responsible_area' => 'Almacén Central',
		'preventive_actions' => 'Auditoría semanal de lotes en racks; bloqueo de despacho si la vida útil residual es menor al 30%; rotación FEFO estricta.',
		'associated_kpi' => '% Merma Caducidad en Bodega',
		'risk_level' => 'Alto',
		'frequency' => 'Ocasional',
		'economic_impact' => 'Alto',
		'requires_evidence' => 1,
		'requires_approval' => 1,
		'icon' => 'fa-solid fa-hourglass-end',
		'color_class' => 'danger'
	],
	[
		'code' => 'M12',
		'name' => 'Calidad defectuosa de origen',
		'category' => 'Proveedores',
		'description' => 'Defecto de formulación, acidez prematura o contaminación en lote entregado por fabricante.',
		'responsible_area' => 'Control de Calidad / Compras',
		'preventive_actions' => 'Muestreo organoléptico y microbiológico en recepción; penalización y nota de débito inmediata al proveedor.',
		'associated_kpi' => 'Índice de Rechazos por Calidad',
		'risk_level' => 'Alto',
		'frequency' => 'Ocasional',
		'economic_impact' => 'Alto',
		'requires_evidence' => 1,
		'requires_approval' => 1,
		'icon' => 'fa-solid fa-ban',
		'color_class' => 'danger'
	],
	[
		'code' => 'M13',
		'name' => 'Rechazo en garantía comercial',
		'category' => 'Garantías',
		'description' => 'Producto devuelto a proveedor cuya garantía comercial fue denegada por plazo vencido.',
		'responsible_area' => 'Comercial / Proveedores',
		'preventive_actions' => 'Gestión ágil del reclamo al fabricante dentro del plazo de garantía; resguardo en zona de no conformes.',
		'associated_kpi' => 'Tasa de Recuperación por Garantía',
		'risk_level' => 'Bajo',
		'frequency' => 'Rara',
		'economic_impact' => 'Bajo',
		'requires_evidence' => 1,
		'requires_approval' => 0,
		'icon' => 'fa-solid fa-certificate',
		'color_class' => 'info'
	],
	[
		'code' => 'M14',
		'name' => 'Empaque deteriorado por embalaje defectuoso',
		'category' => 'Proveedores y Averías',
		'description' => 'Cajas colapsadas o botellas fisuradas debido a cartón deficiente o paletizado inestable de fábrica.',
		'responsible_area' => 'Recepción / Almacén',
		'preventive_actions' => 'Exigir normas de corrugado y paletizado homologadas a proveedores; rechazo de pallets deformados en muelle.',
		'associated_kpi' => '% Daño por Embalaje Proveedor',
		'risk_level' => 'Medio',
		'frequency' => 'Ocasional',
		'economic_impact' => 'Medio',
		'requires_evidence' => 1,
		'requires_approval' => 0,
		'icon' => 'fa-solid fa-box',
		'color_class' => 'warning'
	],

	// --- DAIRY OPERACIONES ESPECÍFICAS D01 - D10 ---
	[
		'code' => 'D01',
		'name' => 'Ruptura de cadena de frío',
		'category' => 'Cadena de Frío',
		'description' => 'Exposición prolongada a temperatura superior a 6°C durante traslado, muelle o descarga.',
		'responsible_area' => 'Logística / Almacén Frío',
		'preventive_actions' => 'Monitoreo continuo IoT con dataloggers; descarga prioritaria en muelle climatizado (< 15 min).',
		'associated_kpi' => 'Incidentes de Pérdida Térmica',
		'risk_level' => 'Crítico',
		'frequency' => 'Ocasional',
		'economic_impact' => 'Severo',
		'requires_evidence' => 1,
		'requires_approval' => 1,
		'icon' => 'fa-solid fa-snowflake',
		'color_class' => 'danger'
	],
	[
		'code' => 'D02',
		'name' => 'Temperatura fuera de rango en cámara / sensor',
		'category' => 'Cadena de Frío',
		'description' => 'Desviación térmica prolongada por falla electromecánica de compresor o evaporador en cámara.',
		'responsible_area' => 'Mantenimiento / Operaciones',
		'preventive_actions' => 'Mantenimiento preventivo quincenal a compresores; alertas SMS/Email en tiempo real ante desviación > 4°C.',
		'associated_kpi' => 'Horas de Desviación Térmica',
		'risk_level' => 'Crítico',
		'frequency' => 'Ocasional',
		'economic_impact' => 'Severo',
		'requires_evidence' => 1,
		'requires_approval' => 1,
		'icon' => 'fa-solid fa-temperature-arrow-up',
		'color_class' => 'danger'
	],
	[
		'code' => 'D03',
		'name' => 'Vencimiento de lácteos por rotación deficiente',
		'category' => 'Calidad Láctea',
		'description' => 'Leches, yogures o cremas que alcanzaron fecha de vencimiento sin ser comercializados.',
		'responsible_area' => 'Piso de Venta / Almacén',
		'preventive_actions' => 'Revisión diaria matutina de fechas de caducidad; aplicación de etiqueta de descuento de salida rápida (30-50% off) a 3 días del vencimiento.',
		'associated_kpi' => 'Índice de Merma Láctea FEFO',
		'risk_level' => 'Alto',
		'frequency' => 'Frecuente',
		'economic_impact' => 'Alto',
		'requires_evidence' => 1,
		'requires_approval' => 0,
		'icon' => 'fa-solid fa-calendar-times',
		'color_class' => 'danger'
	],
	[
		'code' => 'D04',
		'name' => 'Producto deteriorado / Fermentación o corte',
		'category' => 'Calidad Láctea',
		'description' => 'Leche agria o cortada, yogur abombado o queso enmohecido antes de su fecha teórica de caducidad.',
		'responsible_area' => 'Control de Calidad',
		'preventive_actions' => 'Inspección visual de envases hinchados o abombados; toma de pH aleatoria; aislamiento preventivo de lote.',
		'associated_kpi' => 'Tasa de Pérdida Organoléptica',
		'risk_level' => 'Alto',
		'frequency' => 'Ocasional',
		'economic_impact' => 'Medio',
		'requires_evidence' => 1,
		'requires_approval' => 0,
		'icon' => 'fa-solid fa-flask-vial',
		'color_class' => 'danger'
	],
	[
		'code' => 'D05',
		'name' => 'Empaque abierto, punzado o dañado',
		'category' => 'Averías',
		'description' => 'Bolsa de leche perforada, sello de aluminio roto en pote de yogur o empaque al vacío con fuga.',
		'responsible_area' => 'Piso de Venta / Bodega',
		'preventive_actions' => 'Eliminación de cuchillas descubiertas al desempacar termoencogibles; no apilar más de 4 camas de cajas de leche.',
		'associated_kpi' => 'Rotura de Envases Lácteos',
		'risk_level' => 'Medio',
		'frequency' => 'Frecuente',
		'economic_impact' => 'Medio',
		'requires_evidence' => 1,
		'requires_approval' => 0,
		'icon' => 'fa-solid fa-box-tissue',
		'color_class' => 'warning'
	],
	[
		'code' => 'D06',
		'name' => 'Exceso de abastecimiento / Sobre-pedido',
		'category' => 'Compras y Abastecimiento',
		'description' => 'Compra que excede la demanda promedio proyectada, saturando capacidad de frío y superando vida útil.',
		'responsible_area' => 'Compras / Reabastecimiento',
		'preventive_actions' => 'Cálculo algorítmico de orden de compra sugerida según venta promedio diaria ponderada; tope máximo de días de inventario = 40% de vida útil.',
		'associated_kpi' => 'Días de Inventario vs Vida Útil',
		'risk_level' => 'Medio',
		'frequency' => 'Ocasional',
		'economic_impact' => 'Alto',
		'requires_evidence' => 0,
		'requires_approval' => 1,
		'icon' => 'fa-solid fa-chart-line-down',
		'color_class' => 'warning'
	],
	[
		'code' => 'D07',
		'name' => 'Rotación FEFO incumplida en exhibición',
		'category' => 'Piso de Venta',
		'description' => 'Colocación incorrecta de producto nuevo al frente en góndola refrigerada, dejando productos antiguos atrás.',
		'responsible_area' => 'Supervisión de Tienda',
		'preventive_actions' => 'Auditoría sorpresa de góndola: producto nuevo SIEMPRE al fondo, producto con menor vida útil SIEMPRE al frente.',
		'associated_kpi' => 'Cumplimiento de Auditoría FEFO',
		'risk_level' => 'Alto',
		'frequency' => 'Frecuente',
		'economic_impact' => 'Alto',
		'requires_evidence' => 1,
		'requires_approval' => 0,
		'icon' => 'fa-solid fa-arrow-down-short-wide',
		'color_class' => 'warning'
	],
	[
		'code' => 'D08',
		'name' => 'Producto mal almacenado / Incompatibilidad',
		'category' => 'Almacenamiento',
		'description' => 'Contacto con olores extraños, exceso de humedad o estiba sin pallet adecuado provocando daño.',
		'responsible_area' => 'Almacén Central / Cámaras',
		'preventive_actions' => 'Zonificación estricta: quesos madurados separados de yogures frescos; estibas plásticas sanitarias lavadas; no apoyar en el suelo.',
		'associated_kpi' => 'Incidencias de Almacenamiento Inadecuado',
		'risk_level' => 'Medio',
		'frequency' => 'Ocasional',
		'economic_impact' => 'Medio',
		'requires_evidence' => 1,
		'requires_approval' => 0,
		'icon' => 'fa-solid fa-warehouse',
		'color_class' => 'info'
	],
	[
		'code' => 'D09',
		'name' => 'Producto retirado por alerta sanitaria',
		'category' => 'Regulatorio y Calidad',
		'description' => 'Retiro forzoso de lote por orden de autoridad sanitaria o comunicado de laboratorio fabricante.',
		'responsible_area' => 'Aseguramiento de Calidad',
		'preventive_actions' => 'Bloqueo de código en POS en menos de 10 minutos tras alerta sanitaria; inmovilización inmediata de stock en cuarentena.',
		'associated_kpi' => 'Tiempo de Retiro de Lote (Recall Time)',
		'risk_level' => 'Crítico',
		'frequency' => 'Rara',
		'economic_impact' => 'Severo',
		'requires_evidence' => 1,
		'requires_approval' => 1,
		'icon' => 'fa-solid fa-triangle-exclamation',
		'color_class' => 'danger'
	],
	[
		'code' => 'D10',
		'name' => 'Error de etiquetado o fecha ilegible',
		'category' => 'Recepción y Etiquetado',
		'description' => 'Lote recibido con impresión borrosa de lote o caducidad que imposibilita verificación en góndola.',
		'responsible_area' => 'Recepción / Empaque',
		'preventive_actions' => 'Revisión de fechador de tinta al recibir; re-etiquetado supervisado con barcode interno Grocycode con fecha verificada.',
		'associated_kpi' => '% Lotes con Fecha Ilegible',
		'risk_level' => 'Medio',
		'frequency' => 'Ocasional',
		'economic_impact' => 'Bajo',
		'requires_evidence' => 1,
		'requires_approval' => 0,
		'icon' => 'fa-solid fa-barcode',
		'color_class' => 'info'
	]
];

foreach ($standardCausales as $sc)
{
	$existingReason = $db->ExecuteDbQuery("SELECT id FROM waste_reasons WHERE code = :code", [':code' => $sc['code']])->fetch(\PDO::FETCH_OBJ);
	if ($existingReason)
	{
		$db->ExecuteDbStatement("
			UPDATE waste_reasons SET
				name = :name,
				category = :category,
				description = :description,
				responsible_area = :responsible_area,
				preventive_actions = :preventive_actions,
				associated_kpi = :associated_kpi,
				risk_level = :risk_level,
				frequency = :frequency,
				economic_impact = :economic_impact,
				requires_evidence = :requires_evidence,
				requires_approval = :requires_approval,
				icon = :icon,
				color_class = :color_class,
				active = 1
			WHERE id = :id
		", [
			':id' => $existingReason->id,
			':name' => $sc['name'],
			':category' => $sc['category'],
			':description' => $sc['description'],
			':responsible_area' => $sc['responsible_area'],
			':preventive_actions' => $sc['preventive_actions'],
			':associated_kpi' => $sc['associated_kpi'],
			':risk_level' => $sc['risk_level'],
			':frequency' => $sc['frequency'],
			':economic_impact' => $sc['economic_impact'],
			':requires_evidence' => $sc['requires_evidence'],
			':requires_approval' => $sc['requires_approval'],
			':icon' => $sc['icon'],
			':color_class' => $sc['color_class']
		]);
	}
	else
	{
		$db->ExecuteDbStatement("
			INSERT INTO waste_reasons (
				code, name, category, description, responsible_area, preventive_actions,
				associated_kpi, risk_level, frequency, economic_impact, requires_evidence,
				requires_approval, active, icon, color_class
			) VALUES (
				:code, :name, :category, :description, :responsible_area, :preventive_actions,
				:associated_kpi, :risk_level, :frequency, :economic_impact, :requires_evidence,
				:requires_approval, 1, :icon, :color_class
			)
		", [
			':code' => $sc['code'],
			':name' => $sc['name'],
			':category' => $sc['category'],
			':description' => $sc['description'],
			':responsible_area' => $sc['responsible_area'],
			':preventive_actions' => $sc['preventive_actions'],
			':associated_kpi' => $sc['associated_kpi'],
			':risk_level' => $sc['risk_level'],
			':frequency' => $sc['frequency'],
			':economic_impact' => $sc['economic_impact'],
			':requires_evidence' => $sc['requires_evidence'],
			':requires_approval' => $sc['requires_approval'],
			':icon' => $sc['icon'],
			':color_class' => $sc['color_class']
		]);
	}
}

// Deactivate old generic reasons (IDs 1-6 with code M00) so only standard catalog is active
$db->ExecuteDbStatement("UPDATE waste_reasons SET active = 0 WHERE code = 'M00';");

// 5. Backfill existing waste_records with Case Numbers, Workflow Statuses and Stock Deducted flag
$records = $db->ExecuteDbQuery("SELECT id, created_timestamp, status, waste_reason_id, case_number FROM waste_records ORDER BY id ASC")->fetchAll(\PDO::FETCH_OBJ);
foreach ($records as $idx => $rec)
{
	$updates = [];
	$params = [':id' => $rec->id];

	if (empty($rec->case_number))
	{
		$datePart = date('Ym', strtotime($rec->created_timestamp ?? 'now'));
		$caseNum = sprintf("MRM-%s-%04d", $datePart, $rec->id);
		$updates[] = "case_number = :case_number";
		$params[':case_number'] = $caseNum;
	}

	$targetStatus = 'closed';
	if ($rec->status === 'pending_approval')
	{
		$targetStatus = 'detected';
	}
	elseif ($rec->status === 'rejected')
	{
		$targetStatus = 'closed';
	}
	else
	{
		// Approved records map to closed or verified
		$targetStatus = ($rec->id % 2 === 0) ? 'closed' : 'verified';
	}
	$updates[] = "workflow_status = :workflow_status";
	$params[':workflow_status'] = $targetStatus;

	$updates[] = "inventory_deducted = :inv_ded";
	$params[':inv_ded'] = ($rec->status === 'approved') ? 1 : 0;

	if (!empty($updates))
	{
		$db->ExecuteDbStatement("UPDATE waste_records SET " . implode(', ', $updates) . " WHERE id = :id", $params);
	}
}
