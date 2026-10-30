<?php

// Enterprise Platform Migration: AKCAL 2015 Framework Integration
// 4 Classifications: Merma Operativa, Merma Comercial, Ajustes, Averías
// Cycle Counts (Inventarios Selectivos), Supplier Returns (Devoluciones), Action Plans (Planes de Acción)

use Grocy\Services\DatabaseService;

$db = DatabaseService::GetInstance()->GetDbConnectionRaw();

// 1. Add akcal_classification to waste_reasons if not exists
$cols = $db->query("PRAGMA table_info(waste_reasons)")->fetchAll(\PDO::FETCH_COLUMN, 1);
if (!in_array('akcal_classification', $cols))
{
	$db->exec("ALTER TABLE waste_reasons ADD COLUMN akcal_classification TEXT DEFAULT 'operativa'");
}

// Map the 24 standardized causales into the 4 AKCAL pillars
// - 'operativa': manipulación, refrigeración, rotación FEFO, roturas internas, vencimientos en tienda
// - 'comercial': sobreabastecimiento, devoluciones cliente, producto obsoleto, garantías, liquidaciones
// - 'ajuste': diferencias de inventario, descuadre cíclico, errores de conteo
// - 'averia': averías en transporte, embalaje defectuoso, roturas de envase, calidad de origen
$classMap = [
	// Dairy específicas D01 - D10
	'D01' => 'operativa', // Ruptura de cadena de frío
	'D02' => 'operativa', // Temperatura fuera de rango en cámara
	'D03' => 'operativa', // Vencimiento de lácteos por rotación deficiente
	'D04' => 'operativa', // Producto deteriorado / Fermentación
	'D05' => 'averia',    // Empaque abierto, punzado o dañado
	'D06' => 'comercial', // Exceso de abastecimiento / Sobre-pedido
	'D07' => 'operativa', // Rotación FEFO incumplida en exhibición
	'D08' => 'operativa', // Producto mal almacenado / Incompatibilidad
	'D09' => 'operativa', // Producto retirado por alerta sanitaria
	'D10' => 'averia',    // Error de etiquetado o fecha ilegible
	// Alkosto M01 - M14
	'M01' => 'operativa', // Avería en manipulación / Tienda
	'M02' => 'averia',    // Avería en transporte / Despacho
	'M03' => 'operativa', // Hurto interno / Malintencionado
	'M04' => 'operativa', // Hurto externo / Farderos
	'M05' => 'averia',    // Mercancía no recibida / Error proveedor
	'M06' => 'ajuste',    // Diferencia de inventario / Descuadre cíclico
	'M07' => 'operativa', // Error en despacho a sucursal / Traslado
	'M08' => 'comercial', // Devolución de cliente en mal estado
	'M09' => 'comercial', // Producto obsoleto / Fuera de catálogo
	'M10' => 'comercial', // Error administrativo / Facturación errónea
	'M11' => 'operativa', // Mercancía vencida en bodega
	'M12' => 'averia',    // Calidad defectuosa de origen (Proveedor)
	'M13' => 'comercial', // Rechazo en garantía comercial
	'M14' => 'averia'     // Empaque deteriorado por embalaje defectuoso
];

$stmtUpdClass = $db->prepare("UPDATE waste_reasons SET akcal_classification = :cls WHERE code = :code");
foreach ($classMap as $code => $cls)
{
	$stmtUpdClass->execute([':cls' => $cls, ':code' => $code]);
}

// 2. Create table for Selectivo / Cíclico: stock_cycle_counts
$db->exec("
CREATE TABLE IF NOT EXISTS stock_cycle_counts (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	branch_id INTEGER NOT NULL DEFAULT 1,
	product_id INTEGER NOT NULL,
	location_id INTEGER,
	scheduled_date DATE NOT NULL,
	executed_date DATE,
	status TEXT NOT NULL DEFAULT 'pending', -- pending, completed, discrepancy, cancelled
	theoretical_stock REAL NOT NULL DEFAULT 0,
	counted_stock REAL,
	difference_amount REAL DEFAULT 0,
	difference_cost REAL DEFAULT 0,
	assigned_user_id INTEGER,
	executed_by_user_id INTEGER,
	supervisor_user_id INTEGER,
	notes TEXT,
	created_timestamp DATETIME DEFAULT (datetime('now', 'localtime')),
	FOREIGN KEY(product_id) REFERENCES products(id),
	FOREIGN KEY(branch_id) REFERENCES branches(id),
	FOREIGN KEY(location_id) REFERENCES locations(id)
);
");

// 3. Create table for Devoluciones a Proveedores: supplier_returns
$db->exec("
CREATE TABLE IF NOT EXISTS supplier_returns (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	supplier_id INTEGER NOT NULL,
	branch_id INTEGER NOT NULL DEFAULT 1,
	product_id INTEGER NOT NULL,
	delivery_id INTEGER,
	case_number TEXT NOT NULL,
	batch_number TEXT,
	amount REAL NOT NULL,
	qu_id INTEGER NOT NULL,
	unit_cost REAL NOT NULL,
	total_loss_cost REAL NOT NULL,
	return_reason TEXT NOT NULL,
	incident_type TEXT DEFAULT 'quality_issue', -- temperature, expired, package_broken, missing
	credit_requested_amount REAL NOT NULL DEFAULT 0,
	credit_recovered_amount REAL NOT NULL DEFAULT 0,
	loss_unrecovered_amount REAL NOT NULL DEFAULT 0,
	status TEXT NOT NULL DEFAULT 'pending_credit', -- pending_credit, credit_recovered, rejected
	notes TEXT,
	evidence_photo_path TEXT,
	user_id INTEGER NOT NULL DEFAULT 1,
	created_timestamp DATETIME DEFAULT (datetime('now', 'localtime')),
	resolved_timestamp DATETIME,
	FOREIGN KEY(supplier_id) REFERENCES shopping_locations(id),
	FOREIGN KEY(branch_id) REFERENCES branches(id),
	FOREIGN KEY(product_id) REFERENCES products(id)
);
");

// 4. Create table for Planes de Acción AKCAL: waste_action_plans
// Format: Actividad -> Problema -> Seguimiento -> Estado
$db->exec("
CREATE TABLE IF NOT EXISTS waste_action_plans (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	activity_title TEXT NOT NULL,
	problem_description TEXT NOT NULL,
	follow_up_actions TEXT NOT NULL,
	waste_record_id INTEGER,
	waste_reason_id INTEGER,
	branch_id INTEGER NOT NULL DEFAULT 1,
	assigned_user_id INTEGER NOT NULL DEFAULT 1,
	priority TEXT NOT NULL DEFAULT 'alta', -- baja, media, alta, critica
	start_date DATE NOT NULL,
	due_date DATE NOT NULL,
	status TEXT NOT NULL DEFAULT 'in_progress', -- open, in_progress, completed, overdue
	evidence_notes TEXT,
	verified_by_user_id INTEGER,
	cost_avoided_estimate REAL DEFAULT 0,
	created_timestamp DATETIME DEFAULT (datetime('now', 'localtime')),
	updated_timestamp DATETIME DEFAULT (datetime('now', 'localtime')),
	FOREIGN KEY(waste_record_id) REFERENCES waste_records(id),
	FOREIGN KEY(waste_reason_id) REFERENCES waste_reasons(id),
	FOREIGN KEY(branch_id) REFERENCES branches(id),
	FOREIGN KEY(assigned_user_id) REFERENCES users(id)
);
");

// Datos de demostración (conteos, devoluciones, planes): solo en modo demo, no en producción
if (GROCY_MODE === 'demo' || GROCY_MODE === 'prerelease')
{
	// 5. Seed Initial Demo Records for AKCAL Methodology

	// Seed Cycle Counts (Conteos selectivos en productos sensibles: Leche, Huevos, Quesos, Yogur)
	$chkCounts = $db->query("SELECT COUNT(*) FROM stock_cycle_counts")->fetchColumn();
	if ($chkCounts == 0)
	{
		$sensitiveProducts = $db->query("
			SELECT p.id, p.location_id, IFNULL((SELECT SUM(amount) FROM stock WHERE product_id = p.id), 20) as current_stock,
			       COALESCE((SELECT price FROM stock_log WHERE product_id = p.id ORDER BY id DESC LIMIT 1), p.sale_price, 2.5) as unit_cost
			FROM products p 
			WHERE p.active = 1 AND (p.name LIKE '%Leche%' OR p.name LIKE '%Huevo%' OR p.name LIKE '%Queso%' OR p.name LIKE '%Yogur%')
			LIMIT 6
		")->fetchAll(\PDO::FETCH_ASSOC);

		$stmtInsertCount = $db->prepare("
			INSERT INTO stock_cycle_counts (branch_id, product_id, location_id, scheduled_date, executed_date, status, theoretical_stock, counted_stock, difference_amount, difference_cost, assigned_user_id, executed_by_user_id, supervisor_user_id, notes)
			VALUES (:branch_id, :product_id, :location_id, :scheduled_date, :executed_date, :status, :theoretical_stock, :counted_stock, :difference_amount, :difference_cost, 1, 1, 1, :notes)
		");

		$today = date('Y-m-d');
		$yesterday = date('Y-m-d', strtotime('-1 day'));
		$tomorrow = date('Y-m-d', strtotime('+1 day'));

		$i = 0;
		foreach ($sensitiveProducts as $sp)
		{
			$theo = floatval($sp['current_stock']);
			$cost = floatval($sp['unit_cost']);
			if ($i % 3 == 0)
			{
				// Conteo completado con exactitud 100%
				$stmtInsertCount->execute([
					':branch_id' => 1,
					':product_id' => $sp['id'],
					':location_id' => $sp['location_id'] ?: 4,
					':scheduled_date' => $yesterday,
					':executed_date' => $yesterday,
					':status' => 'completed',
					':theoretical_stock' => $theo,
					':counted_stock' => $theo,
					':difference_amount' => 0,
					':difference_cost' => 0,
					':notes' => 'Conteo selectivo matutino conforme al 100%. Saldo físico coincide con sistema.'
				]);
			}
			elseif ($i % 3 == 1)
			{
				// Conteo con discrepancia auditada (-1 unidad)
				$diff = -1.0;
				$stmtInsertCount->execute([
					':branch_id' => 1,
					':product_id' => $sp['id'],
					':location_id' => $sp['location_id'] ?: 4,
					':scheduled_date' => $today,
					':executed_date' => $today,
					':status' => 'discrepancy',
					':theoretical_stock' => $theo,
					':counted_stock' => max(0, $theo + $diff),
					':difference_amount' => $diff,
					':difference_cost' => round($diff * $cost, 2),
					':notes' => 'Faltante de 1 unidad detectado en estiba posterior. Derivado a verificación de mermas M06.'
				]);
			}
			else
			{
				// Conteo programado pendiente
				$stmtInsertCount->execute([
					':branch_id' => 1,
					':product_id' => $sp['id'],
					':location_id' => $sp['location_id'] ?: 4,
					':scheduled_date' => $tomorrow,
					':executed_date' => null,
					':status' => 'pending',
					':theoretical_stock' => $theo,
					':counted_stock' => null,
					':difference_amount' => 0,
					':difference_cost' => 0,
					':notes' => 'Inventario selectivo programado para auditoría de rotación FEFO.'
				]);
			}
			$i++;
		}
	}

	// Seed Supplier Returns (Devoluciones consolidadas)
	$chkReturns = $db->query("SELECT COUNT(*) FROM supplier_returns")->fetchColumn();
	if ($chkReturns == 0)
	{
		$supplierId = $db->query("SELECT id FROM shopping_locations WHERE active = 1 LIMIT 1")->fetchColumn() ?: 1;
		$prod = $db->query("SELECT id, qu_id_stock FROM products WHERE active = 1 LIMIT 1")->fetch(\PDO::FETCH_ASSOC);

		$stmtInsertReturn = $db->prepare("
			INSERT INTO supplier_returns (supplier_id, branch_id, product_id, case_number, batch_number, amount, qu_id, unit_cost, total_loss_cost, return_reason, incident_type, credit_requested_amount, credit_recovered_amount, loss_unrecovered_amount, status, notes, user_id, resolved_timestamp)
			VALUES (:supplier_id, 1, :product_id, :case_number, :batch_number, :amount, :qu_id, :unit_cost, :total_loss_cost, :return_reason, :incident_type, :credit_requested, :credit_recovered, :loss_unrecovered, :status, :notes, 1, :resolved_time)
		");

		// Caso 1: Crédito recuperado mediante nota de crédito
		$stmtInsertReturn->execute([
			':supplier_id' => $supplierId,
			':product_id' => $prod['id'] ?: 1,
			':case_number' => 'DEV-202610-001',
			':batch_number' => 'LOT-SUP-9021',
			':amount' => 10.0,
			':qu_id' => $prod['qu_id_stock'] ?: 3,
			':unit_cost' => 2.40,
			':total_loss_cost' => 24.00,
			':return_reason' => 'Empaques de leche colapsados al recibir tarima del camión refrigerado',
			':incident_type' => 'package_broken',
			':credit_requested' => 24.00,
			':credit_recovered' => 24.00,
			':loss_unrecovered' => 0.00,
			':status' => 'credit_recovered',
			':notes' => 'Proveedor emitió Nota de Crédito NC-8821. Reembolso acreditado 100%.',
			':resolved_time' => date('Y-m-d H:i:s')
		]);

		// Caso 2: Devolución pendiente de dictamen
		$stmtInsertReturn->execute([
			':supplier_id' => $supplierId,
			':product_id' => $prod['id'] ?: 1,
			':case_number' => 'DEV-202610-002',
			':batch_number' => 'LOT-SUP-9044',
			':amount' => 5.0,
			':qu_id' => $prod['qu_id_stock'] ?: 3,
			':unit_cost' => 3.10,
			':total_loss_cost' => 15.50,
			':return_reason' => 'Termógrafo del furgón registró 9.2°C durante el traslado (Desvío crítico)',
			':incident_type' => 'temperature',
			':credit_requested' => 15.50,
			':credit_recovered' => 0.00,
			':loss_unrecovered' => 15.50,
			':status' => 'pending_credit',
			':notes' => 'Enviada reclamación formal con acta de muelle firmada por transportista.',
			':resolved_time' => null
		]);
	}

	// Seed Action Plans (Planes de Acción AKCAL: Actividad -> Problema -> Seguimiento -> Estado)
	$chkPlans = $db->query("SELECT COUNT(*) FROM waste_action_plans")->fetchColumn();
	if ($chkPlans == 0)
	{
		$reasonCold = $db->query("SELECT id FROM waste_reasons WHERE code = 'D01' LIMIT 1")->fetchColumn() ?: 1;
		$reasonFefo = $db->query("SELECT id FROM waste_reasons WHERE code = 'D03' LIMIT 1")->fetchColumn() ?: 2;
		$reasonPack = $db->query("SELECT id FROM waste_reasons WHERE code = 'D05' LIMIT 1")->fetchColumn() ?: 3;

		$stmtInsertPlan = $db->prepare("
			INSERT INTO waste_action_plans (activity_title, problem_description, follow_up_actions, waste_reason_id, branch_id, assigned_user_id, priority, start_date, due_date, status, evidence_notes, cost_avoided_estimate)
			VALUES (:activity, :problem, :follow_up, :reason_id, 1, 1, :priority, :start_date, :due_date, :status, :evidence, :cost_avoided)
		");

		// Plan 1: Cadena de Frío
		$stmtInsertPlan->execute([
			':activity' => 'Instalación de Cortinas Nocturnas y Calibración Termostatos',
			':problem' => 'Fuga de frío en góndolas abiertas durante la madrugada, elevando temperatura a 6.5°C.',
			':follow_up' => '1. Colocación de cortinas térmicas al cierre diario. 2. Ronda de verificación con termómetro láser a las 22:00 y 06:00.',
			':reason_id' => $reasonCold,
			':priority' => 'critica',
			':start_date' => date('Y-m-d', strtotime('-5 days')),
			':due_date' => date('Y-m-d', strtotime('+2 days')),
			':status' => 'in_progress',
			':evidence' => 'Cortinas instaladas en lácteos 1 y 2. Pendiente calibración de sonda digital.',
			':cost_avoided' => 350.00
		]);

		// Plan 2: Rotación FEFO
		$stmtInsertPlan->execute([
			':activity' => 'Auditoría Diaria FEFO en Góndola de Yogures y Leche',
			':problem' => 'Reponedores colocaban mercancía nueva al frente ocultando lotes con 4 días de vencimiento.',
			':follow_up' => 'Capacitación a reponedores; protocolo de sacar lote anterior al frente antes de estibar el nuevo.',
			':reason_id' => $reasonFefo,
			':priority' => 'alta',
			':start_date' => date('Y-m-d', strtotime('-10 days')),
			':due_date' => date('Y-m-d', strtotime('-1 day')),
			':status' => 'completed',
			':evidence' => 'Checklist firmado durante 7 días consecutivos. Cero vencimientos en el período.',
			':cost_avoided' => 180.00
		]);

		// Plan 3: Empaques y Estiba
		$stmtInsertPlan->execute([
			':activity' => 'Limitación de Altura de Estiba en Cámara de Quesos',
			':problem' => 'Cajas de queso prensadas por sobrepeso de estiba (> 5 niveles), provocando fisuras de sellado.',
			':follow_up' => 'Señalización en pared de cámara: máximo 4 camas por tarima. Uso obligatorio de esquineros de cartón.',
			':reason_id' => $reasonPack,
			':priority' => 'media',
			':start_date' => date('Y-m-d'),
			':due_date' => date('Y-m-d', strtotime('+7 days')),
			':status' => 'open',
			':evidence' => 'Carteles de altura máxima impresos para colocación en muelle y cámara.',
			':cost_avoided' => 120.00
		]);
	}
}
