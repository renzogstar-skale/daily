<?php

namespace Grocy\Services;

class DailyExpansionSchemaService extends BaseService
{
	private function Db(): \PDO
	{
		return DatabaseService::getInstance()->GetDbConnectionRaw();
	}

	public function EnsureSchemaAndSeed(): void
	{
		$db = $this->Db();

		// 1. DAILY Action Center Items
		$db->exec("
			CREATE TABLE IF NOT EXISTS daily_action_center_items (
				id INTEGER PRIMARY KEY AUTOINCREMENT,
				code TEXT UNIQUE NOT NULL,
				title TEXT NOT NULL,
				description TEXT,
				source_module TEXT NOT NULL,
				entity_type TEXT,
				entity_id INTEGER,
				branch_id INTEGER,
				department TEXT DEFAULT 'Operaciones',
				priority TEXT DEFAULT 'medium',
				status TEXT DEFAULT 'pending',
				assigned_user_id INTEGER,
				due_date DATE,
				evidence_url TEXT,
				created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
				updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
				closed_at DATETIME
			);
			CREATE TABLE IF NOT EXISTS daily_action_logs (
				id INTEGER PRIMARY KEY AUTOINCREMENT,
				item_id INTEGER NOT NULL,
				action_type TEXT NOT NULL,
				notes TEXT,
				user_id INTEGER,
				created_at DATETIME DEFAULT CURRENT_TIMESTAMP
			);
		");

		// 2. Recepción ASN
		$db->exec("
			CREATE TABLE IF NOT EXISTS daily_asn_receipts (
				id INTEGER PRIMARY KEY AUTOINCREMENT,
				asn_number TEXT UNIQUE NOT NULL,
				supplier_id INTEGER,
				supplier_name TEXT NOT NULL,
				branch_id INTEGER,
				branch_name TEXT,
				expected_delivery_date DATE,
				status TEXT DEFAULT 'expected',
				doc_reference TEXT,
				items_count INTEGER DEFAULT 0,
				total_expected_qty REAL DEFAULT 0,
				total_received_qty REAL DEFAULT 0,
				created_at DATETIME DEFAULT CURRENT_TIMESTAMP
			);
			CREATE TABLE IF NOT EXISTS daily_asn_items (
				id INTEGER PRIMARY KEY AUTOINCREMENT,
				asn_id INTEGER NOT NULL,
				product_id INTEGER,
				product_name TEXT NOT NULL,
				product_barcode TEXT,
				expected_qty REAL DEFAULT 0,
				received_qty REAL DEFAULT 0,
				discrepancy_qty REAL DEFAULT 0,
				unit_cost REAL DEFAULT 0,
				notes TEXT
			);
		");

		// 3. Control de Precios y Promociones & Campañas
		$db->exec("
			CREATE TABLE IF NOT EXISTS daily_price_promotions (
				id INTEGER PRIMARY KEY AUTOINCREMENT,
				product_id INTEGER NOT NULL,
				product_name TEXT NOT NULL,
				branch_id INTEGER,
				regular_price REAL NOT NULL,
				promo_price REAL NOT NULL,
				discount_pct REAL NOT NULL,
				start_date DATE NOT NULL,
				end_date DATE NOT NULL,
				status TEXT DEFAULT 'active',
				margin_before_pct REAL,
				margin_after_pct REAL,
				campaign_name TEXT,
				approved_by_user_id INTEGER,
				created_at DATETIME DEFAULT CURRENT_TIMESTAMP
			);
			CREATE TABLE IF NOT EXISTS daily_campaign_evaluations (
				id INTEGER PRIMARY KEY AUTOINCREMENT,
				campaign_name TEXT NOT NULL,
				start_date DATE NOT NULL,
				end_date DATE NOT NULL,
				branch_id INTEGER,
				branch_name TEXT,
				sales_before REAL DEFAULT 0,
				sales_during REAL DEFAULT 0,
				sales_after REAL DEFAULT 0,
				units_sold INTEGER DEFAULT 0,
				uplift_pct REAL DEFAULT 0,
				campaign_cost REAL DEFAULT 0,
				financial_return REAL DEFAULT 0,
				roi_pct REAL DEFAULT 0
			);
		");

		// 4. POS Conciliación de Cajas
		$db->exec("
			CREATE TABLE IF NOT EXISTS daily_pos_cash_reconciliations (
				id INTEGER PRIMARY KEY AUTOINCREMENT,
				shift_code TEXT UNIQUE NOT NULL,
				cashier_user_id INTEGER,
				cashier_name TEXT NOT NULL,
				branch_id INTEGER,
				register_number TEXT NOT NULL,
				opened_at DATETIME,
				closed_at DATETIME,
				opening_balance REAL DEFAULT 0,
				cash_sales REAL DEFAULT 0,
				card_sales REAL DEFAULT 0,
				credit_sales REAL DEFAULT 0,
				returns_amount REAL DEFAULT 0,
				voids_amount REAL DEFAULT 0,
				cash_expected REAL DEFAULT 0,
				cash_counted REAL DEFAULT 0,
				difference REAL DEFAULT 0,
				status TEXT DEFAULT 'balanced',
				supervisor_user_id INTEGER,
				notes TEXT
			);
		");

		// 5. Gestión de Contratos & Proveedores
		$db->exec("
			CREATE TABLE IF NOT EXISTS daily_supplier_contracts (
				id INTEGER PRIMARY KEY AUTOINCREMENT,
				contract_code TEXT UNIQUE NOT NULL,
				supplier_id INTEGER,
				supplier_name TEXT NOT NULL,
				title TEXT NOT NULL,
				start_date DATE NOT NULL,
				end_date DATE NOT NULL,
				renewal_terms TEXT,
				minimum_order_val REAL DEFAULT 0,
				payment_terms_days INTEGER DEFAULT 30,
				discount_terms TEXT,
				status TEXT DEFAULT 'active',
				responsible_name TEXT,
				doc_file TEXT,
				created_at DATETIME DEFAULT CURRENT_TIMESTAMP
			);
		");

		// 6. Auditorías Inteligentes & Productos Dañados
		$db->exec("
			CREATE TABLE IF NOT EXISTS daily_smart_audits (
				id INTEGER PRIMARY KEY AUTOINCREMENT,
				audit_code TEXT UNIQUE NOT NULL,
				title TEXT NOT NULL,
				audit_type TEXT DEFAULT 'Discrepancia Recurrente',
				branch_id INTEGER,
				risk_level TEXT DEFAULT 'high',
				target_category_or_sku TEXT NOT NULL,
				trigger_reason TEXT,
				assigned_auditor_name TEXT,
				scheduled_date DATE,
				status TEXT DEFAULT 'scheduled',
				findings_summary TEXT
			);
			CREATE TABLE IF NOT EXISTS daily_damaged_products (
				id INTEGER PRIMARY KEY AUTOINCREMENT,
				incident_code TEXT UNIQUE NOT NULL,
				product_id INTEGER,
				product_name TEXT NOT NULL,
				barcode TEXT,
				quantity REAL NOT NULL,
				reason TEXT NOT NULL,
				classification TEXT DEFAULT 'Avería Operativa',
				unit_cost REAL DEFAULT 0,
				total_cost REAL DEFAULT 0,
				supplier_id INTEGER,
				supplier_name TEXT,
				recovery_potential REAL DEFAULT 0,
				photo_evidence TEXT,
				status TEXT DEFAULT 'pending_approval',
				disposition_action TEXT,
				created_at DATETIME DEFAULT CURRENT_TIMESTAMP
			);
		");

		// 7. Capacitación & Mantenimiento & Incidentes Operativos
		$db->exec("
			CREATE TABLE IF NOT EXISTS daily_employee_certifications_ext (
				id INTEGER PRIMARY KEY AUTOINCREMENT,
				employee_id INTEGER NOT NULL,
				employee_name TEXT NOT NULL,
				course_name TEXT NOT NULL,
				certification_code TEXT,
				issue_date DATE,
				expiry_date DATE,
				status TEXT DEFAULT 'valid',
				operating_restriction TEXT,
				issuer TEXT
			);
			CREATE TABLE IF NOT EXISTS daily_equipment_maintenance (
				id INTEGER PRIMARY KEY AUTOINCREMENT,
				equipment_id INTEGER,
				equipment_name TEXT NOT NULL,
				branch_id INTEGER,
				branch_name TEXT,
				maintenance_type TEXT DEFAULT 'preventive',
				status TEXT DEFAULT 'scheduled',
				scheduled_date DATE,
				completed_date DATE,
				technician TEXT,
				cost REAL DEFAULT 0,
				failure_details TEXT,
				resolution_notes TEXT
			);
			CREATE TABLE IF NOT EXISTS daily_operational_incidents (
				id INTEGER PRIMARY KEY AUTOINCREMENT,
				incident_code TEXT UNIQUE NOT NULL,
				title TEXT NOT NULL,
				severity TEXT DEFAULT 'medium',
				branch_id INTEGER,
				branch_name TEXT,
				department TEXT DEFAULT 'Piso de Venta',
				reported_by_name TEXT,
				occurred_at DATETIME,
				evidence_photo TEXT,
				corrective_action TEXT,
				status TEXT DEFAULT 'open',
				created_at DATETIME DEFAULT CURRENT_TIMESTAMP
			);
		");

		// 8. Presupuestos por Departamento
		$db->exec("
			CREATE TABLE IF NOT EXISTS daily_department_budgets (
				id INTEGER PRIMARY KEY AUTOINCREMENT,
				branch_id INTEGER,
				branch_name TEXT NOT NULL,
				department TEXT NOT NULL,
				fiscal_year INTEGER DEFAULT 2026,
				monthly_budget REAL DEFAULT 0,
				annual_budget REAL DEFAULT 0,
				actual_spent REAL DEFAULT 0,
				variance_pct REAL DEFAULT 0,
				alert_status TEXT DEFAULT 'on_track'
			);
		");

		$this->SeedDataIfEmpty();
	}

	private function SeedDataIfEmpty(): void
	{
		$db = $this->Db();

		// Seed Action Center
		$cnt = $db->query("SELECT COUNT(*) FROM daily_action_center_items")->fetchColumn();
		if ($cnt == 0) {
			$stmt = $db->prepare("
				INSERT INTO daily_action_center_items (code, title, description, source_module, entity_type, entity_id, branch_id, department, priority, status, due_date, evidence_url)
				VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
			");
			$items = [
				['ACT-2026-001', 'Aprobar orden de compra de reposición lácteos', 'Orden #PO-2026-041 supera umbral de $5,000 por alta demanda proyectada fin de mes.', '1. Planeación y Compras', 'procurement_orders', 41, 1, 'Compras', 'critical', 'pending', date('Y-m-d', strtotime('+1 day')), '/img/control_center/dispatch_card_doc.jpg'],
				['ACT-2026-002', 'Discrepancia en recepción ASN de Cárnicos', 'Aviso ASN-9902 presenta faltante de 12 kg de Pechuga de Pollo vs remisión física.', '2. Inventario y Almacenes', 'daily_asn_receipts', 1, 1, 'Recepción WMS', 'high', 'in_progress', date('Y-m-d', strtotime('+2 days')), '/img/control_center/pechuga_pollo.jpg'],
				['ACT-2026-003', 'Descuadre de arqueo en Caja 03 - Sucursal Norte', 'Diferencia de -$42.50 en arqueo nocturno de efectivo pendiente de visado.', '3. Ventas y Rentabilidad', 'daily_pos_cash_reconciliations', 1, 2, 'Cajas', 'high', 'pending', date('Y-m-d'), '/img/control_center/robo_control_pos.jpg'],
				['ACT-2026-004', 'Lote de Yogur Griego próximo a caducar (3 días)', 'Lote YG-202604 presenta 45 unidades sin rotación; se sugiere rebaja inmediata del 25%.', '5. Calidad y Vencimientos', 'stock_lots', 14, 1, 'Lácteos', 'critical', 'pending', date('Y-m-d', strtotime('+1 day')), '/img/control_center/yogur_griego.jpg'],
				['ACT-2026-005', 'Auditoría obligatoria de IMEI: Samsung Galaxy S24', 'Discrepancia detectada en recuento ciego de celulares; IMEI 354890123984711 en revisión.', '7. Prevención de Pérdidas', 'device_units', 3, 1, 'Electrónica', 'critical', 'in_progress', date('Y-m-d'), '/img/control_center/prod_galaxy_s24.svg'],
				['ACT-2026-006', 'Inspección de temperatura en Cámara Fría 02', 'Sensor registró 7.2°C durante 45 minutos (límite máximo permitido: 4.0°C).', '5. Calidad y Vencimientos', 'cold_chain_temperatures', 1, 1, 'Refrigerados', 'critical', 'in_progress', date('Y-m-d'), '/img/control_center/daily_loc_coldroom.jpg'],
				['ACT-2026-007', 'Certificación de Manipulación de Alimentos por vencer', '3 colaboradores de Carnicería requieren renovación de carnet antes del día 15.', '8. Operaciones y Personal', 'employee_certifications', 2, 1, 'Carnicería', 'medium', 'pending', date('Y-m-d', strtotime('+5 days')), null],
				['ACT-2026-008', 'Mantenimiento correctivo de Balanza Digital Muelle 1', 'Error de calibración en tara de pesaje; servicio técnico programado.', '8. Operaciones y Personal', 'equipment', 5, 1, 'Muelle', 'medium', 'pending', date('Y-m-d', strtotime('+3 days')), null],
				['ACT-2026-009', 'Aprobación de merma crítica por rotura en estiba', 'Rotura de 18 botellas de Aceite Vegetal durante maniobra de montacargas.', '6. Mermas y Prevención', 'waste_records', 8, 1, 'Abarrotes', 'high', 'pending', date('Y-m-d'), '/img/control_center/dest_merma.jpg'],
				['ACT-2026-010', 'Diferencia en etiqueta góndola vs POS en Tomates', 'Etiqueta física marca $2.10/kg mientras el POS cobra $2.45/kg.', '2. Inventario y Almacenes', 'price_discrepancies', 1, 1, 'Fruver', 'high', 'in_progress', date('Y-m-d'), '/img/control_center/precios_revisar_gondola.jpg']
			];
			foreach ($items as $it) {
				$stmt->execute($it);
			}
		}

		// Seed ASN Receipts
		$cntAsn = $db->query("SELECT COUNT(*) FROM daily_asn_receipts")->fetchColumn();
		if ($cntAsn == 0) {
			$db->exec("
				INSERT INTO daily_asn_receipts (asn_number, supplier_id, supplier_name, branch_id, branch_name, expected_delivery_date, status, doc_reference, items_count, total_expected_qty, total_received_qty)
				VALUES
				('ASN-2026-081', 1, 'Lácteos del Valle S.A.', 1, 'DAILY Central (Tienda 01)', date('now'), 'in_dock', 'REM-449102', 3, 250, 245),
				('ASN-2026-082', 2, 'Avícola Santa Rita', 1, 'DAILY Central (Tienda 01)', date('now', '+1 day'), 'expected', 'REM-88129', 2, 180, 0),
				('ASN-2026-083', 3, 'Distribuidora Fruver SAS', 2, 'DAILY Norte (Tienda 02)', date('now', '-1 day'), 'matched', 'REM-33104', 4, 400, 400);

				INSERT INTO daily_asn_items (asn_id, product_id, product_name, product_barcode, expected_qty, received_qty, discrepancy_qty, unit_cost, notes)
				VALUES
				(1, 1, 'Leche Entera 1L', '7701234567890', 150, 150, 0, 1.15, 'Conforme'),
				(1, 2, 'Yogur Griego 500g', '7701234567891', 60, 55, -5, 2.30, '5 unidades faltantes en embalaje'),
				(1, 3, 'Queso Campesino 500g', '7701234567892', 40, 40, 0, 3.40, 'Conforme'),
				(3, 4, 'Tomate Chonto', '7702000000001', 200, 200, 0, 1.20, 'Inspección conforme'),
				(3, 5, 'Pepino Cohombro', '7702000000002', 200, 200, 0, 0.90, 'Inspección conforme');
			");
		}

		// Seed Promociones y Campañas
		$cntPromo = $db->query("SELECT COUNT(*) FROM daily_price_promotions")->fetchColumn();
		if ($cntPromo == 0) {
			$db->exec("
				INSERT INTO daily_price_promotions (product_id, product_name, branch_id, regular_price, promo_price, discount_pct, start_date, end_date, status, margin_before_pct, margin_after_pct, campaign_name)
				VALUES
				(1, 'Leche Entera 1L', 1, 1.80, 1.49, 17.2, date('now', '-2 days'), date('now', '+5 days'), 'active', 36.1, 22.8, 'Quincena del Ahorro'),
				(2, 'Yogur Griego 500g', 1, 3.50, 2.80, 20.0, date('now', '-1 day'), date('now', '+6 days'), 'active', 34.3, 17.9, 'Festival de Lácteos'),
				(3, 'Queso Campesino 500g', 2, 4.90, 4.20, 14.3, date('now'), date('now', '+3 days'), 'active', 30.6, 19.0, 'Promoción Fin de Semana');

				INSERT INTO daily_campaign_evaluations (campaign_name, start_date, end_date, branch_id, branch_name, sales_before, sales_during, sales_after, units_sold, uplift_pct, campaign_cost, financial_return, roi_pct)
				VALUES
				('Quincena del Ahorro Lácteos', date('now', '-14 days'), date('now', '-1 day'), 1, 'DAILY Central', 14200, 19450, 15100, 4320, 36.9, 850, 4400, 417.6),
				('Festival de Frescura Fruver', date('now', '-21 days'), date('now', '-7 days'), 2, 'DAILY Norte', 9800, 13100, 10200, 6100, 33.7, 500, 2800, 460.0);
			");
		}

		// Seed POS Conciliación Cajas
		$cntCash = $db->query("SELECT COUNT(*) FROM daily_pos_cash_reconciliations")->fetchColumn();
		if ($cntCash == 0) {
			$db->exec("
				INSERT INTO daily_pos_cash_reconciliations (shift_code, cashier_user_id, cashier_name, branch_id, register_number, opened_at, closed_at, opening_balance, cash_sales, card_sales, credit_sales, returns_amount, voids_amount, cash_expected, cash_counted, difference, status, notes)
				VALUES
				('TUR-2026-0401', 2, 'Carlos Ruiz', 1, 'CAJA-01', datetime('now', '-9 hours'), datetime('now', '-1 hour'), 150.00, 840.50, 1120.00, 120.00, 15.00, 0.00, 975.50, 975.50, 0.00, 'balanced', 'Turno cerrado y conciliado sin novedades'),
				('TUR-2026-0402', 3, 'María Morales', 1, 'CAJA-02', datetime('now', '-8 hours'), datetime('now', '-30 minutes'), 150.00, 620.00, 980.00, 0.00, 0.00, 12.00, 770.00, 765.00, -5.00, 'discrepancy', 'Descuadre menor en monedas de cambio (-$5.00)'),
				('TUR-2026-0403', 4, 'Andrés Soto', 2, 'CAJA-01', datetime('now', '-10 hours'), datetime('now', '-2 hours'), 200.00, 1100.00, 1450.00, 0.00, 35.00, 0.00, 1265.00, 1265.00, 0.00, 'balanced', 'Arqueo conforme');
			");
		}

		// Seed Contratos
		$cntCont = $db->query("SELECT COUNT(*) FROM daily_supplier_contracts")->fetchColumn();
		if ($cntCont == 0) {
			$db->exec("
				INSERT INTO daily_supplier_contracts (contract_code, supplier_id, supplier_name, title, start_date, end_date, renewal_terms, minimum_order_val, payment_terms_days, discount_terms, status, responsible_name)
				VALUES
				('CTR-SUP-2025-01', 1, 'Lácteos del Valle S.A.', 'Suministro Exclusivo de Lácteos y Derivados', '2025-01-01', '2026-12-31', 'Renovación automática 12 meses', 2500.00, 45, '3% pronto pago < 15 días', 'active', 'Camila Ortiz (Gerente Compras)'),
				('CTR-SUP-2025-02', 2, 'Avícola Santa Rita', 'Abastecimiento Cárnico y Aves de Corral', '2025-06-01', '2026-05-31', 'Revisión semestral de volumen', 1800.00, 30, 'Rebate 2% por volumen trimestral > $20k', 'expiring_soon', 'Camila Ortiz (Gerente Compras)'),
				('CTR-SUP-2025-03', 3, 'Distribuidora Fruver SAS', 'Contrato Marco Frutas y Verduras Frescas', '2025-03-01', '2027-02-28', 'Precio indexado a central de abastos', 1000.00, 15, '1.5% pronto pago', 'active', 'Javier Peña (Comprador)');
			");
		}

		// Seed Presupuestos
		$cntBudg = $db->query("SELECT COUNT(*) FROM daily_department_budgets")->fetchColumn();
		if ($cntBudg == 0) {
			$db->exec("
				INSERT INTO daily_department_budgets (branch_id, branch_name, department, fiscal_year, monthly_budget, annual_budget, actual_spent, variance_pct, alert_status)
				VALUES
				(1, 'DAILY Central', 'Operaciones & Piso', 2026, 8500.00, 102000.00, 7920.00, -6.8, 'on_track'),
				(1, 'DAILY Central', 'Logística & Muelle', 2026, 4200.00, 50400.00, 4480.00, 6.7, 'warning'),
				(1, 'DAILY Central', 'Mantenimiento & Frío', 2026, 3100.00, 37200.00, 2950.00, -4.8, 'on_track'),
				(2, 'DAILY Norte', 'Operaciones & Piso', 2026, 6800.00, 81600.00, 6600.00, -2.9, 'on_track'),
				(2, 'DAILY Norte', 'Logística & Muelle', 2026, 3500.00, 42000.00, 3850.00, 10.0, 'warning');
			");
		}

		// Seed Certificaciones & Mantenimiento & Incidentes
		$cntCert = $db->query("SELECT COUNT(*) FROM daily_employee_certifications_ext")->fetchColumn();
		if ($cntCert == 0) {
			$db->exec("
				INSERT INTO daily_employee_certifications_ext (employee_id, employee_name, course_name, certification_code, issue_date, expiry_date, status, operating_restriction, issuer)
				VALUES
				(1, 'Carlos Ruiz', 'Manipulación Higiénica de Alimentos BPM', 'CERT-BPM-2025-01', '2025-05-10', '2026-05-10', 'valid', 'Habilitado para cárnicos y lácteos', 'INVIMA / SENA'),
				(2, 'María Morales', 'Operación Segura de Montacargas y Transpaletas', 'CERT-MONT-2024-88', '2024-11-15', '2026-11-15', 'valid', 'Habilitado muelle y estantería alta', 'Cruz Roja / ARL Sura'),
				(3, 'Andrés Soto', 'Control de Cadena de Frío y Puntos Críticos HACCP', 'CERT-HACCP-2025-14', '2025-02-01', '2026-02-01', 'expired', 'RESTRINGIDO: Exige recertificación', 'Bureau Veritas');

				INSERT INTO daily_equipment_maintenance (equipment_id, equipment_name, branch_id, branch_name, maintenance_type, status, scheduled_date, technician, cost, failure_details, resolution_notes)
				VALUES
				(1, 'Cámara Frigorífica 01 (Lácteos)', 1, 'DAILY Central', 'preventive', 'completed', date('now', '-5 days'), 'RefrigCenter SAS', 180.00, 'Revisión periódica de gas y compresor', 'Filtros limpios y calibración 2.5°C'),
				(2, 'Transpaleta Eléctrica Crown #03', 1, 'DAILY Central', 'corrective', 'in_progress', date('now'), 'Mecánica Industrial Bogotá', 320.00, 'Falla en batería y pistón de levante', 'En taller de reparación'),
				(3, 'Cámara de Congelados -18°C', 2, 'DAILY Norte', 'preventive', 'scheduled', date('now', '+4 days'), 'ClimaExpert', 210.00, 'Mantenimiento mensual preventivo', 'Programado');

				INSERT INTO daily_operational_incidents (incident_code, title, severity, branch_id, branch_name, department, reported_by_name, occurred_at, corrective_action, status)
				VALUES
				('INC-2026-01', 'Fuga menor de agua en condensador de lácteos', 'medium', 1, 'DAILY Central', 'Lácteos', 'Carlos Ruiz', datetime('now', '-1 day'), 'Válvula ajustada y bandeja de goteo sustituida', 'resolved'),
				('INC-2026-02', 'Caída de bulto de harina en pasillo 4', 'low', 1, 'DAILY Central', 'Abarrotes', 'María Morales', datetime('now', '-4 hours'), 'Limpieza inmediata y retiro a merma por rotura', 'closed');
			");
		}
	}
}
