<?php

// Enterprise Platform Migration: Suppliers & Procurement + Quality & Expiry
// Executed inside DatabaseMigrationService context

use Grocy\Services\DatabaseService;

$db = DatabaseService::GetInstance();

// -------------------------------------------------------------
// 1. Proveedores y Abastecimiento (Suppliers & Procurement)
// -------------------------------------------------------------

// Extend / Meta table for suppliers (linked 1:1 with shopping_locations)
$db->ExecuteDbStatement("
CREATE TABLE IF NOT EXISTS suppliers_meta (
	id INTEGER PRIMARY KEY REFERENCES shopping_locations(id) ON DELETE CASCADE,
	contact_name TEXT,
	email TEXT,
	phone TEXT,
	tax_id TEXT,
	address TEXT,
	lead_time_days INTEGER NOT NULL DEFAULT 2,
	min_order_amount REAL NOT NULL DEFAULT 0.0,
	payment_terms TEXT DEFAULT 'Crédito 15 días',
	rating REAL NOT NULL DEFAULT 4.8,
	notes TEXT,
	active INTEGER NOT NULL DEFAULT 1,
	row_created_timestamp DATETIME DEFAULT (datetime('now', 'localtime'))
);
");

// Historial de precios y cotizaciones por proveedor y producto
$db->ExecuteDbStatement("
CREATE TABLE IF NOT EXISTS supplier_product_prices (
	id INTEGER PRIMARY KEY AUTOINCREMENT UNIQUE,
	supplier_id INTEGER NOT NULL REFERENCES shopping_locations(id) ON DELETE CASCADE,
	product_id INTEGER NOT NULL REFERENCES products(id) ON DELETE CASCADE,
	price REAL NOT NULL,
	min_order_qty REAL NOT NULL DEFAULT 1.0,
	delivery_lead_days INTEGER NOT NULL DEFAULT 2,
	valid_from DATE NOT NULL DEFAULT (date('now')),
	valid_to DATE,
	is_current INTEGER NOT NULL DEFAULT 1,
	notes TEXT,
	row_created_timestamp DATETIME DEFAULT (datetime('now', 'localtime'))
);
");
$db->ExecuteDbStatement("CREATE INDEX IF NOT EXISTS ix_supp_prod ON supplier_product_prices(supplier_id, product_id);");

// Evaluación del cumplimiento de entregas (OTIF)
$db->ExecuteDbStatement("
CREATE TABLE IF NOT EXISTS supplier_deliveries (
	id INTEGER PRIMARY KEY AUTOINCREMENT UNIQUE,
	supplier_id INTEGER NOT NULL REFERENCES shopping_locations(id) ON DELETE CASCADE,
	branch_id INTEGER NOT NULL DEFAULT 1 REFERENCES branches(id),
	order_reference TEXT NOT NULL,
	order_date DATE NOT NULL,
	expected_delivery_date DATE NOT NULL,
	actual_delivery_date DATE,
	status TEXT NOT NULL DEFAULT 'on_time',
	items_ordered REAL NOT NULL DEFAULT 0,
	items_received REAL NOT NULL DEFAULT 0,
	temperature_on_arrival REAL DEFAULT 3.2,
	compliance_rating INTEGER NOT NULL DEFAULT 5,
	notes TEXT,
	row_created_timestamp DATETIME DEFAULT (datetime('now', 'localtime'))
);
");

// Registro de incidencias con proveedores
$db->ExecuteDbStatement("
CREATE TABLE IF NOT EXISTS supplier_incidents (
	id INTEGER PRIMARY KEY AUTOINCREMENT UNIQUE,
	supplier_id INTEGER NOT NULL REFERENCES shopping_locations(id) ON DELETE CASCADE,
	product_id INTEGER REFERENCES products(id) ON DELETE SET NULL,
	branch_id INTEGER NOT NULL DEFAULT 1 REFERENCES branches(id),
	incident_date DATE NOT NULL DEFAULT (date('now')),
	incident_type TEXT NOT NULL,
	severity TEXT NOT NULL DEFAULT 'medium',
	status TEXT NOT NULL DEFAULT 'open',
	description TEXT NOT NULL,
	resolution_notes TEXT,
	cost_impact REAL NOT NULL DEFAULT 0.0,
	row_created_timestamp DATETIME DEFAULT (datetime('now', 'localtime'))
);
");

// -------------------------------------------------------------
// 2. Calidad y Caducidades (Quality & Cold Chain Management)
// -------------------------------------------------------------

// Registro de temperaturas de refrigeradores y congeladores
$db->ExecuteDbStatement("
CREATE TABLE IF NOT EXISTS cold_chain_temperatures (
	id INTEGER PRIMARY KEY AUTOINCREMENT UNIQUE,
	location_id INTEGER NOT NULL REFERENCES locations(id),
	branch_id INTEGER NOT NULL DEFAULT 1 REFERENCES branches(id),
	temperature REAL NOT NULL,
	humidity REAL DEFAULT 80.0,
	recorded_timestamp DATETIME DEFAULT (datetime('now', 'localtime')),
	recorded_by TEXT DEFAULT 'Sensor IoT / Control Automático',
	status TEXT NOT NULL DEFAULT 'normal',
	action_taken TEXT
);
");
$db->ExecuteDbStatement("CREATE INDEX IF NOT EXISTS ix_cct_loc_date ON cold_chain_temperatures(location_id, recorded_timestamp);");

// Inspecciones y controles de calidad
$db->ExecuteDbStatement("
CREATE TABLE IF NOT EXISTS quality_inspections (
	id INTEGER PRIMARY KEY AUTOINCREMENT UNIQUE,
	product_id INTEGER NOT NULL REFERENCES products(id) ON DELETE CASCADE,
	stock_id TEXT,
	location_id INTEGER REFERENCES locations(id),
	branch_id INTEGER NOT NULL DEFAULT 1 REFERENCES branches(id),
	inspection_type TEXT NOT NULL,
	temperature_measured REAL,
	result TEXT NOT NULL DEFAULT 'approved',
	inspector_name TEXT NOT NULL,
	criteria_details TEXT,
	notes TEXT,
	row_created_timestamp DATETIME DEFAULT (datetime('now', 'localtime'))
);
");

// Gestión de productos en cuarentena
$db->ExecuteDbStatement("
CREATE TABLE IF NOT EXISTS quarantine_items (
	id INTEGER PRIMARY KEY AUTOINCREMENT UNIQUE,
	product_id INTEGER NOT NULL REFERENCES products(id),
	stock_id TEXT NOT NULL,
	location_id INTEGER REFERENCES locations(id),
	branch_id INTEGER NOT NULL DEFAULT 1 REFERENCES branches(id),
	amount REAL NOT NULL,
	qu_id INTEGER NOT NULL REFERENCES quantity_units(id),
	reason TEXT NOT NULL,
	status TEXT NOT NULL DEFAULT 'in_quarantine',
	entered_timestamp DATETIME DEFAULT (datetime('now', 'localtime')),
	resolved_timestamp DATETIME,
	resolved_by TEXT,
	resolution_notes TEXT,
	waste_record_id INTEGER REFERENCES waste_records(id)
);
");

// -------------------------------------------------------------
// 3. Seed inicial especializado para lácteos y perecederos
// -------------------------------------------------------------

// Actualizar o insertar proveedores reales de lácteos
$suppliers = [
	[
		'id' => 1,
		'name' => 'Lácteos del Valle S.A.',
		'description' => 'Productor primario certificado de leche pasteurizada, cremas y derivados fluidos',
		'contact_name' => 'Ing. Carlos Mendoza',
		'email' => 'ventas@lacteosdelvalle.com',
		'phone' => '+52 55 4321 8899',
		'tax_id' => 'LVA-920412-LK1',
		'address' => 'Carretera Km 14, Parque Agroindustrial Valle',
		'lead_time_days' => 1,
		'min_order_amount' => 150.0,
		'payment_terms' => 'Crédito 15 días',
		'rating' => 4.9,
		'notes' => 'Flota propia con refrigeración monitoreada por GPS y termógrafos certificados.'
	],
	[
		'id' => 2,
		'name' => 'Distribuidora Frigorífica Nacional',
		'description' => 'Mayorista de quesos madurados, mozzarellas, mantequillas y yogures importados',
		'contact_name' => 'Lic. Mariana Garza',
		'email' => 'mgarza@frigorificanacional.com',
		'phone' => '+52 55 8899 3322',
		'tax_id' => 'DFN-080315-TR8',
		'address' => 'Av. Central de Abastos Bodega E-42',
		'lead_time_days' => 2,
		'min_order_amount' => 300.0,
		'payment_terms' => 'Crédito 30 días',
		'rating' => 4.7,
		'notes' => 'Proveedor preferente para quesos en bloque y presentaciones institucionales.'
	],
	[
		'id' => 3,
		'name' => 'Granja Avícola San Juan',
		'description' => 'Suministro directo de huevos frescos clasificados (grandes y orgánicos de libre pastoreo)',
		'contact_name' => 'Don Roberto Santos',
		'email' => 'pedidos@granjasanjuan.com',
		'phone' => '+52 55 7766 5544',
		'tax_id' => 'GAS-140620-PQ4',
		'address' => 'Granja San Juan, Ejido Los Fresnos',
		'lead_time_days' => 2,
		'min_order_amount' => 100.0,
		'payment_terms' => 'Contado contra entrega (2% dto)',
		'rating' => 4.8,
		'notes' => 'Entregas en camiones isotérmicos con temperatura controlada a 18°C.'
	],
	[
		'id' => 4,
		'name' => 'Bebidas & Probióticos del Centro',
		'description' => 'Elaboración artesanal y a escala de kéfir, yogur griego funcional y jugos prensados en frío',
		'contact_name' => 'Dra. Elena Ramos',
		'email' => 'eramos@probioticoscentro.com',
		'phone' => '+52 55 3344 1122',
		'tax_id' => 'BPC-191008-MN2',
		'address' => 'Calle Industrial 88, Zona Fría',
		'lead_time_days' => 3,
		'min_order_amount' => 200.0,
		'payment_terms' => 'Crédito 15 días',
		'rating' => 4.6,
		'notes' => 'Cadena ultra-fría (2°C a 4°C obligatoria). Requiere inspección estricta de acidez a la recepción.'
	]
];

foreach ($suppliers as $s)
{
	// Insert or update shopping_locations
	$exists = $db->ExecuteDbQuery("SELECT COUNT(*) FROM shopping_locations WHERE id = " . intval($s['id']))->fetchColumn();
	if ($exists == 0)
	{
		$db->ExecuteDbStatement(
			"INSERT INTO shopping_locations (id, name, description, active) VALUES (:id, :name, :description, 1)",
			[':id' => $s['id'], ':name' => $s['name'], ':description' => $s['description']]
		);
	}
	else
	{
		$db->ExecuteDbStatement(
			"UPDATE shopping_locations SET name = :name, description = :description WHERE id = :id",
			[':id' => $s['id'], ':name' => $s['name'], ':description' => $s['description']]
		);
	}

	// Insert or update suppliers_meta
	$metaExists = $db->ExecuteDbQuery("SELECT COUNT(*) FROM suppliers_meta WHERE id = " . intval($s['id']))->fetchColumn();
	if ($metaExists == 0)
	{
		$db->ExecuteDbStatement(
			"INSERT INTO suppliers_meta (id, contact_name, email, phone, tax_id, address, lead_time_days, min_order_amount, payment_terms, rating, notes, active) 
			 VALUES (:id, :contact_name, :email, :phone, :tax_id, :address, :lead_time_days, :min_order_amount, :payment_terms, :rating, :notes, 1)",
			[
				':id' => $s['id'],
				':contact_name' => $s['contact_name'],
				':email' => $s['email'],
				':phone' => $s['phone'],
				':tax_id' => $s['tax_id'],
				':address' => $s['address'],
				':lead_time_days' => $s['lead_time_days'],
				':min_order_amount' => $s['min_order_amount'],
				':payment_terms' => $s['payment_terms'],
				':rating' => $s['rating'],
				':notes' => $s['notes']
			]
		);
	}
}

// Datos transaccionales de demostración (precios, entregas, incidencias, temperaturas, inspecciones, cuarentena):
// solo en modo demo; referencian productos/lotes del set de demo y no deben cargarse en producción
if (GROCY_MODE === 'demo' || GROCY_MODE === 'prerelease')
{
	// Precios comparativos por proveedor para productos lácteos clave
	$priceSeeds = [
		// Leche entera 1 galón (Prod 30)
		['supplier_id' => 1, 'product_id' => 30, 'price' => 3.10, 'min_order_qty' => 20, 'lead_days' => 1, 'notes' => 'Precio preferencial directo de planta'],
		['supplier_id' => 2, 'product_id' => 30, 'price' => 3.35, 'min_order_qty' => 10, 'lead_days' => 2, 'notes' => 'Distribuidor mayorista'],
	
		// Leche 2% 1 galón (Prod 31)
		['supplier_id' => 1, 'product_id' => 31, 'price' => 3.00, 'min_order_qty' => 20, 'lead_days' => 1, 'notes' => 'Planta principal'],
		['supplier_id' => 2, 'product_id' => 31, 'price' => 3.25, 'min_order_qty' => 10, 'lead_days' => 2, 'notes' => 'Opción secundaria'],
	
		// Huevos grandes 12 unidades (Prod 34)
		['supplier_id' => 3, 'product_id' => 34, 'price' => 2.40, 'min_order_qty' => 30, 'lead_days' => 2, 'notes' => 'Directo de granja'],
		['supplier_id' => 2, 'product_id' => 34, 'price' => 2.70, 'min_order_qty' => 15, 'lead_days' => 2, 'notes' => 'Revendedor'],
	
		// Queso mozzarella (Prod 41)
		['supplier_id' => 2, 'product_id' => 41, 'price' => 3.40, 'min_order_qty' => 10, 'lead_days' => 2, 'notes' => 'Importado de primera calidad'],
		['supplier_id' => 1, 'product_id' => 41, 'price' => 3.65, 'min_order_qty' => 15, 'lead_days' => 2, 'notes' => 'Nacional artesanal'],

		// Yogur griego natural (Prod 37)
		['supplier_id' => 4, 'product_id' => 37, 'price' => 1.10, 'min_order_qty' => 25, 'lead_days' => 3, 'notes' => 'Especialidad alta proteína'],
		['supplier_id' => 2, 'product_id' => 37, 'price' => 1.25, 'min_order_qty' => 20, 'lead_days' => 2, 'notes' => 'Marca estándar'],

		// Mantequilla sin sal (Prod 43)
		['supplier_id' => 1, 'product_id' => 43, 'price' => 2.10, 'min_order_qty' => 15, 'lead_days' => 1, 'notes' => '82% grasa láctea'],
		['supplier_id' => 2, 'product_id' => 43, 'price' => 2.25, 'min_order_qty' => 10, 'lead_days' => 2, 'notes' => 'Presentación comercial']
	];

	foreach ($priceSeeds as $ps)
	{
		$pExists = $db->ExecuteDbQuery(
			"SELECT COUNT(*) FROM supplier_product_prices WHERE supplier_id = :sid AND product_id = :pid",
			[':sid' => $ps['supplier_id'], ':pid' => $ps['product_id']]
		)->fetchColumn();

		if ($pExists == 0)
		{
			$db->ExecuteDbStatement(
				"INSERT INTO supplier_product_prices (supplier_id, product_id, price, min_order_qty, delivery_lead_days, valid_from, is_current, notes)
				 VALUES (:sid, :pid, :price, :moq, :lead, date('now', '-30 days'), 1, :notes)",
				[
					':sid' => $ps['supplier_id'],
					':pid' => $ps['product_id'],
					':price' => $ps['price'],
					':moq' => $ps['min_order_qty'],
					':lead' => $ps['lead_days'],
					':notes' => $ps['notes']
				]
			);
		}
	}

	// Historial de entregas (Cumplimiento de proveedores OTIF)
	$deliverySeeds = [
		[
			'supplier_id' => 1,
			'order_ref' => 'OC-2026-088',
			'order_date' => date('Y-m-d', strtotime('-5 days')),
			'exp_date' => date('Y-m-d', strtotime('-4 days')),
			'act_date' => date('Y-m-d', strtotime('-4 days')),
			'status' => 'on_time',
			'ordered' => 200,
			'received' => 200,
			'temp' => 3.2,
			'rating' => 5,
			'notes' => 'Entrega perfecta a 3.2°C con sello de seguridad intacto.'
		],
		[
			'supplier_id' => 1,
			'order_ref' => 'OC-2026-081',
			'order_date' => date('Y-m-d', strtotime('-12 days')),
			'exp_date' => date('Y-m-d', strtotime('-11 days')),
			'act_date' => date('Y-m-d', strtotime('-11 days')),
			'status' => 'on_time',
			'ordered' => 180,
			'received' => 180,
			'temp' => 3.5,
			'rating' => 5,
			'notes' => 'Recepción conforme en Cámara Fría.'
		],
		[
			'supplier_id' => 2,
			'order_ref' => 'OC-2026-079',
			'order_date' => date('Y-m-d', strtotime('-14 days')),
			'exp_date' => date('Y-m-d', strtotime('-12 days')),
			'act_date' => date('Y-m-d', strtotime('-11 days')),
			'status' => 'delayed',
			'ordered' => 120,
			'received' => 120,
			'temp' => 4.1,
			'rating' => 3,
			'notes' => '1 día de retraso por congestión en carretera. Temperatura al límite aceptable.'
		],
		[
			'supplier_id' => 3,
			'order_ref' => 'OC-2026-085',
			'order_date' => date('Y-m-d', strtotime('-7 days')),
			'exp_date' => date('Y-m-d', strtotime('-5 days')),
			'act_date' => date('Y-m-d', strtotime('-5 days')),
			'status' => 'on_time',
			'ordered' => 150,
			'received' => 150,
			'temp' => 17.8,
			'rating' => 5,
			'notes' => 'Huevos frescos entregados en temperatura óptima y sin roturas.'
		],
		[
			'supplier_id' => 4,
			'order_ref' => 'OC-2026-072',
			'order_date' => date('Y-m-d', strtotime('-18 days')),
			'exp_date' => date('Y-m-d', strtotime('-15 days')),
			'act_date' => date('Y-m-d', strtotime('-15 days')),
			'status' => 'partial',
			'ordered' => 80,
			'received' => 70,
			'temp' => 3.8,
			'rating' => 4,
			'notes' => 'Faltaron 10 potes de Kéfir por rotura en planta. Proveedor acreditó nota de crédito.'
		]
	];

	foreach ($deliverySeeds as $ds)
	{
		$dExists = $db->ExecuteDbQuery(
			"SELECT COUNT(*) FROM supplier_deliveries WHERE order_reference = :ref",
			[':ref' => $ds['order_ref']]
		)->fetchColumn();

		if ($dExists == 0)
		{
			$db->ExecuteDbStatement(
				"INSERT INTO supplier_deliveries (supplier_id, branch_id, order_reference, order_date, expected_delivery_date, actual_delivery_date, status, items_ordered, items_received, temperature_on_arrival, compliance_rating, notes)
				 VALUES (:sid, 1, :ref, :odate, :edate, :adate, :status, :ordered, :received, :temp, :rating, :notes)",
				[
					':sid' => $ds['supplier_id'],
					':ref' => $ds['order_ref'],
					':odate' => $ds['order_date'],
					':edate' => $ds['exp_date'],
					':adate' => $ds['act_date'],
					':status' => $ds['status'],
					':ordered' => $ds['ordered'],
					':received' => $ds['received'],
					':temp' => $ds['temp'],
					':rating' => $ds['rating'],
					':notes' => $ds['notes']
				]
			);
		}
	}

	// Registro de incidencias con proveedores
	$incidentSeeds = [
		[
			'supplier_id' => 2,
			'product_id' => 40,
			'incident_date' => date('Y-m-d', strtotime('-10 days')),
			'type' => 'damaged_packaging',
			'severity' => 'medium',
			'status' => 'resolved',
			'description' => 'Caja de 10 paquetes de queso cheddar laminado con perforación exterior por estiba defectuosa.',
			'resolution' => 'Proveedor envió nota de crédito por $34.00 y reemplazó las unidades en la siguiente entrega.',
			'cost' => 34.0
		],
		[
			'supplier_id' => 4,
			'product_id' => 49,
			'incident_date' => date('Y-m-d', strtotime('-2 days')),
			'type' => 'near_expiration',
			'severity' => 'low',
			'status' => 'claimed',
			'description' => 'Lote de Kéfir recibido con solo 8 días de vida útil restante en lugar del estándar acordado de 18 días.',
			'resolution' => 'Reclamo en curso. Se solicitó descuento del 25% para salida inmediata en góndola.',
			'cost' => 22.0
		]
	];

	foreach ($incidentSeeds as $inc)
	{
		$incExists = $db->ExecuteDbQuery(
			"SELECT COUNT(*) FROM supplier_incidents WHERE supplier_id = :sid AND description = :desc",
			[':sid' => $inc['supplier_id'], ':desc' => $inc['description']]
		)->fetchColumn();

		if ($incExists == 0)
		{
			$db->ExecuteDbStatement(
				"INSERT INTO supplier_incidents (supplier_id, product_id, branch_id, incident_date, incident_type, severity, status, description, resolution_notes, cost_impact)
				 VALUES (:sid, :pid, 1, :idate, :type, :sev, :st, :desc, :res, :cost)",
				[
					':sid' => $inc['supplier_id'],
					':pid' => $inc['product_id'],
					':idate' => $inc['incident_date'],
					':type' => $inc['type'],
					':sev' => $inc['severity'],
					':st' => $inc['status'],
					':desc' => $inc['description'],
					':res' => $inc['resolution'],
					':cost' => $inc['cost']
				]
			);
		}
	}

	// Registros de temperatura de frío (Refrigeradores y Congeladores)
	$tempSeeds = [
		// Cámara Fría de Lácteos (id 7)
		['loc' => 7, 'temp' => 2.8, 'hum' => 84, 'hours' => 1, 'status' => 'normal', 'action' => 'Ciclo de refrigeración estable.'],
		['loc' => 7, 'temp' => 3.1, 'hum' => 82, 'hours' => 3, 'status' => 'normal', 'action' => 'Control automático sin novedades.'],
		['loc' => 7, 'temp' => 3.4, 'hum' => 85, 'hours' => 6, 'status' => 'normal', 'action' => 'Cierre de turno nocturno.'],
		['loc' => 7, 'temp' => 2.9, 'hum' => 83, 'hours' => 12, 'status' => 'normal', 'action' => 'Inspección matutina.'],
	
		// Góndola Refrigerada Dairy (id 8)
		['loc' => 8, 'temp' => 3.8, 'hum' => 78, 'hours' => 1, 'status' => 'normal', 'action' => 'Piso de venta con alta afluencia.'],
		['loc' => 8, 'temp' => 4.2, 'hum' => 76, 'hours' => 4, 'status' => 'warning', 'action' => 'Alerta preventiva: cortina nocturna colocada y compresor verificado.'],
		['loc' => 8, 'temp' => 3.6, 'hum' => 79, 'hours' => 8, 'status' => 'normal', 'action' => 'Temperatura recuperada a rango seguro.'],

		// Congelador (id 6)
		['loc' => 6, 'temp' => -19.4, 'hum' => 65, 'hours' => 2, 'status' => 'normal', 'action' => 'Congelación profunda óptima.'],
		['loc' => 6, 'temp' => -19.0, 'hum' => 64, 'hours' => 7, 'status' => 'normal', 'action' => 'Monitoreo continuo.'],

		// Refrigerador General (id 2)
		['loc' => 2, 'temp' => 3.0, 'hum' => 80, 'hours' => 2, 'status' => 'normal', 'action' => 'Verificación rutinaria.']
	];

	foreach ($tempSeeds as $ts)
	{
		$timeStr = date('Y-m-d H:i:s', strtotime("-{$ts['hours']} hours"));
		$tExists = $db->ExecuteDbQuery(
			"SELECT COUNT(*) FROM cold_chain_temperatures WHERE location_id = :loc AND recorded_timestamp = :ts",
			[':loc' => $ts['loc'], ':ts' => $timeStr]
		)->fetchColumn();

		if ($tExists == 0)
		{
			$db->ExecuteDbStatement(
				"INSERT INTO cold_chain_temperatures (location_id, branch_id, temperature, humidity, recorded_timestamp, recorded_by, status, action_taken)
				 VALUES (:loc, 1, :temp, :hum, :ts, 'Sensor IoT / Control Automático', :status, :act)",
				[
					':loc' => $ts['loc'],
					':temp' => $ts['temp'],
					':hum' => $ts['hum'],
					':ts' => $timeStr,
					':status' => $ts['status'],
					':act' => $ts['action']
				]
			);
		}
	}

	// Inspecciones de calidad
	$inspectionSeeds = [
		[
			'product_id' => 30, // Leche entera 1 galón
			'stock_id' => '6acea2b588ff5',
			'location_id' => 7,
			'type' => 'reception',
			'temp' => 3.2,
			'result' => 'approved',
			'inspector' => 'Q.F.B. Laura Beltrán',
			'notes' => 'Acidez 15°D dentro de norma. Olor y color lácteo característico. Hermeticidad 100% aprobada.'
		],
		[
			'product_id' => 41, // Queso mozzarella
			'stock_id' => '6acea2b678c12',
			'location_id' => 7,
			'type' => 'routine_cold',
			'temp' => 3.4,
			'result' => 'approved',
			'inspector' => 'Supervisor Calidad Mario Díaz',
			'notes' => 'Sellado al vacío íntegro sin presencia de suero libre ni gas.'
		],
		[
			'product_id' => 37, // Yogur griego
			'stock_id' => '6acea2b627a7f',
			'location_id' => 8,
			'type' => 'organoleptic',
			'temp' => 3.9,
			'result' => 'approved',
			'inspector' => 'Q.F.B. Laura Beltrán',
			'notes' => 'Textura cremosa uniforme, sin sinéresis ni separación de fases.'
		]
	];

	foreach ($inspectionSeeds as $insp)
	{
		$inspExists = $db->ExecuteDbQuery(
			"SELECT COUNT(*) FROM quality_inspections WHERE product_id = :pid AND inspector_name = :insp",
			[':pid' => $insp['product_id'], ':insp' => $insp['inspector']]
		)->fetchColumn();

		if ($inspExists == 0)
		{
			$db->ExecuteDbStatement(
				"INSERT INTO quality_inspections (product_id, stock_id, location_id, branch_id, inspection_type, temperature_measured, result, inspector_name, notes)
				 VALUES (:pid, :sid, :loc, 1, :type, :temp, :res, :insp, :notes)",
				[
					':pid' => $insp['product_id'],
					':sid' => $insp['stock_id'],
					':loc' => $insp['location_id'],
					':type' => $insp['type'],
					':temp' => $insp['temp'],
					':res' => $insp['result'],
					':insp' => $insp['inspector'],
					':notes' => $insp['notes']
				]
			);
		}
	}

	// 1 lote en cuarentena preventiva para pruebas de gestión y descarte a merma
	$qExists = $db->ExecuteDbQuery("SELECT COUNT(*) FROM quarantine_items WHERE product_id = 30 AND status = 'in_quarantine'")->fetchColumn();
	if ($qExists == 0)
	{
		$db->ExecuteDbStatement(
			"INSERT INTO quarantine_items (product_id, stock_id, location_id, branch_id, amount, qu_id, reason, status, entered_timestamp, resolved_by, resolution_notes)
			 VALUES (30, '6acea2b588ff5', 7, 1, 8, 3, 'Sospecha de micro-fisura en empaque durante descarga de tarima', 'in_quarantine', datetime('now', '-1 days'), NULL, NULL)"
		);
	}
}
