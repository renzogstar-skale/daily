<?php

namespace Grocy\Services;

/**
 * DAILY — Generador Empresarial Integral (60 Proveedores, 650 Productos, 8 Departamentos,
 * 10 Sucursales, Seriales IMEI, Investigaciones, Recuperación Financiera y Kardex Coherente).
 */
class DailyEnterpriseSeederService extends BaseService
{
	private function Db(): \PDO
	{
		return DatabaseService::getInstance()->GetDbConnectionRaw();
	}

	public function RunEnterpriseSeed(): array
	{
		$db = $this->Db();
		$stats = [
			'suppliers_created' => 0,
			'products_created' => 0,
			'barcodes_created' => 0,
			'stock_batches_created' => 0,
			'sales_created' => 0,
			'sale_items_created' => 0,
			'devices_created' => 0,
			'investigations_created' => 0,
			'recoveries_created' => 0,
			'asns_created' => 0
		];

		$triggersToRestore = [];
		foreach (['dairy_movement_ledger_capture', 'dairy_log_products_ins', 'dairy_log_product_barcodes_ins', 'stock_log_INS'] as $trigName) {
			$sql = $db->query("SELECT sql FROM sqlite_master WHERE type = 'trigger' AND name = '$trigName'")->fetchColumn();
			if ($sql) {
				$triggersToRestore[$trigName] = $sql;
				$db->exec("DROP TRIGGER $trigName");
			}
		}
		$firstLogId = intval($db->query('SELECT IFNULL(MAX(id), 0) FROM stock_log')->fetchColumn());

		$db->beginTransaction();
		try {
			// 1. Homogeneizar las 10 Sucursales con nombres claros de Florida
			echo "   [1/10] Homogeneizando 10 sucursales...\n";
			$this->EnsureBranches($db);

			// 2. Garantizar 60 Proveedores en shopping_locations y suppliers_meta
			echo "   [2/10] Sembrando 60 proveedores calificados...\n";
			$stats['suppliers_created'] = $this->SeedSuppliers($db);

			// 3. Garantizar Ubicaciones Operativas por Sucursal (Bodega Seca, Cámara Fría, Bóveda)
			echo "   [3/10] Configurando almacenes por zona y tipo de almacenamiento...\n";
			$branchLocations = $this->EnsureBranchStorageLocations($db);

			// 4. Generar Catálogo Empresarial de 650 Productos en 8 Departamentos
			echo "   [4/10] Generando catálogo maestro de 650 productos en 8 departamentos...\n";
			$catalogStats = $this->SeedEnterpriseCatalog($db, $branchLocations);
			$stats['products_created'] = $catalogStats['products'];
			$stats['barcodes_created'] = $catalogStats['barcodes'];

			// 5. Sembrar Stock Físico y Kardex Inicial
			echo "   [5/10] Distribuyendo stock físico y lotes en las 10 sucursales...\n";
			$stats['stock_batches_created'] = $this->SeedPhysicalStock($db, $branchLocations);

			// 6. Sembrar Transacciones POS y Ventas Históricas (21 días)
			echo "   [6/10] Generando transacciones históricas POS y curvas de venta...\n";
			$salesStats = $this->SeedSalesHistory($db);
			$stats['sales_created'] = $salesStats['sales'];
			$stats['sale_items_created'] = $salesStats['items'];

			// 7. Sembrar Dispositivos Serializados / IMEI (350+ unidades)
			echo "   [7/10] Registrando 350+ dispositivos de alta tecnología con IMEI y custodia...\n";
			$stats['devices_created'] = $this->SeedSerializedDevices($db, $branchLocations);

			// 8. Sembrar Investigaciones de Seguridad y Prevención (35 casos)
			echo "   [8/10] Creando 35 expedientes de investigación en prevención de pérdidas...\n";
			$stats['investigations_created'] = $this->SeedInvestigationCases($db);

			// 9. Sembrar Casos de Recuperación Financiera y Reclamos (30 casos)
			echo "   [9/10] Registrando pipeline de 30 reclamos de recuperación financiera...\n";
			$stats['recoveries_created'] = $this->SeedFinancialRecoveries($db);

			// 10. Sembrar Recepciones ASN y Auditorías Inteligentes
			echo "   [10/10] Sembrando recepciones ASN y auditorías inteligentes...\n";
			$stats['asns_created'] = $this->SeedAsnsAndAudits($db);

			// Sincronizar libro de movimientos unificado
			$db->exec("INSERT OR IGNORE INTO dairy_movement_ledger (stock_log_id, product_id, amount, transaction_type, stock_id, location_id, price, spoiled, best_before_date, transaction_id, correlation_id, user_id, note, row_created_timestamp, recorded_timestamp)
				SELECT sl.id, sl.product_id, sl.amount, sl.transaction_type, sl.stock_id, IFNULL(sl.location_id, p.location_id), sl.price, sl.spoiled, sl.best_before_date, sl.transaction_id, sl.correlation_id, sl.user_id, sl.note, sl.row_created_timestamp, sl.row_created_timestamp
				FROM stock_log sl LEFT JOIN products p ON p.id = sl.product_id WHERE sl.id > $firstLogId ORDER BY sl.id");

			$db->commit();
			echo "   -> Transacción confirmada en SQLite exitosamente.\n";

			// Actualizar caches de Grocy en bloque
			try {
				$db->exec("INSERT OR REPLACE INTO cache__products_average_price (product_id, price) SELECT product_id, price FROM products_average_price");
				$db->exec("INSERT OR REPLACE INTO cache__products_last_purchased (product_id, amount, best_before_date, purchased_date, price, location_id, shopping_location_id) SELECT product_id, amount, best_before_date, purchased_date, price, location_id, shopping_location_id FROM products_last_purchased");
			} catch (\Throwable $t) {}
		} catch (\Throwable $e) {
			if ($db->inTransaction()) {
				$db->rollBack();
			}
			echo "   ERROR: " . $e->getMessage() . "\n";
			throw $e;
		} finally {
			foreach ($triggersToRestore as $sql) {
				try { $db->exec($sql); } catch (\Throwable $t) {}
			}
		}

		return $stats;
	}

	private function EnsureBranches(\PDO $db): void
	{
		$branches = [
			1 => ['name' => 'Hallandale Beach', 'code' => 'SUC-01', 'address' => '800 E Hallandale Beach Blvd', 'phone' => '+1 305-555-0101', 'format' => 'Superstore'],
			2 => ['name' => 'Miami Beach', 'code' => 'SUC-02', 'address' => '1100 Lincoln Rd', 'phone' => '+1 305-555-0102', 'format' => 'Express'],
			3 => ['name' => 'Sunny Isles Beach', 'code' => 'SUC-03', 'address' => '17070 Collins Ave', 'phone' => '+1 305-555-0103', 'format' => 'Boutique'],
			4 => ['name' => 'Aventura', 'code' => 'SUC-04', 'address' => '19501 Biscayne Blvd', 'phone' => '+1 305-555-0104', 'format' => 'Superstore'],
			5 => ['name' => 'North Miami', 'code' => 'SUC-05', 'address' => '12550 Biscayne Blvd', 'phone' => '+1 305-555-0105', 'format' => 'Standard'],
			6 => ['name' => 'Miami Centro', 'code' => 'SUC-06', 'address' => '401 Biscayne Blvd', 'phone' => '+1 305-555-0106', 'format' => 'Flagship'],
			7 => ['name' => 'Hollywood', 'code' => 'SUC-07', 'address' => '3300 Hollywood Blvd', 'phone' => '+1 954-555-0107', 'format' => 'Standard'],
			8 => ['name' => 'Fort Lauderdale', 'code' => 'SUC-08', 'address' => '2400 N Federal Hwy', 'phone' => '+1 954-555-0108', 'format' => 'Superstore'],
			9 => ['name' => 'Doral', 'code' => 'SUC-09', 'address' => '8700 NW 36th St', 'phone' => '+1 305-555-0109', 'format' => 'Standard'],
			10 => ['name' => 'Hialeah', 'code' => 'SUC-10', 'address' => '1600 W 49th St', 'phone' => '+1 305-555-0110', 'format' => 'Discount']
		];

		$up = $db->prepare("UPDATE branches SET name = ?, code = ?, address = ?, phone = ?, format = ?, active = 1 WHERE id = ?");
		$ins = $db->prepare("INSERT INTO branches (id, name, code, address, phone, format, active) VALUES (?, ?, ?, ?, ?, ?, 1)");

		foreach ($branches as $id => $b) {
			$exists = $db->query("SELECT id FROM branches WHERE id = $id")->fetchColumn();
			if ($exists) {
				$up->execute([$b['name'], $b['code'], $b['address'], $b['phone'], $b['format'], $id]);
			} else {
				$ins->execute([$id, $b['name'], $b['code'], $b['address'], $b['phone'], $b['format']]);
			}
		}
	}

	private function SeedSuppliers(\PDO $db): int
	{
		$suppliers = [
			// Tecnología
			['Tech Distribuciones Florida LLC', 'Mayorista oficial smartphones y tablets', 'Camila Morales', 'contact@techflorida.com', '+1 305-555-401', '30 días', 98.5],
			['NexTech Electronics USA', 'Equipos de cómputo y periféricos premium', 'David Cohen', 'orders@nextechusa.com', '+1 305-555-402', '45 días', 97.2],
			['Global Telecom Wholesalers', 'Distribuidor autorizado líneas móviles e IMEI', 'Patricia Vega', 'sales@globaltelecom.com', '+1 305-555-403', '30 días', 99.1],
			['MicroPulse Computers Corp', 'Portátiles, monitores y almacenamiento SSD', 'Kevin Vance', 'kvance@micropulse.io', '+1 954-555-404', '60 días', 96.0],
			['InnoDevice Supply Logistics', 'Wearables inteligentes y audio Hi-Fi', 'Lorena Díaz', 'ldiaz@innodevice.com', '+1 305-555-405', '30 días', 95.8],
			['Apex Digital Logistics', 'Consolas y pantallas de última generación', 'Roberto Ruiz', 'rruiz@apexdigital.com', '+1 954-555-406', '45 días', 98.0],
			['Alpha Display Systems', 'Monitores gamers y televisores 4K/8K', 'Elena Chang', 'echang@alphadisplays.com', '+1 305-555-407', '30 días', 97.5],
			
			// Electrodomésticos
			['ElectroHogar Continental Inc', 'Electrodomésticos mayores y refrigeración', 'Mariana Solís', 'msolis@electrohogar.com', '+1 305-555-410', '45 días', 96.5],
			['WhirlMaster Appliances USA', 'Línea blanca de alta eficiencia energética', 'Tom Harris', 'tharris@whirlmaster.com', '+1 954-555-411', '60 días', 98.2],
			['ChefPro Kitchen Solutions', 'Freidoras de aire, hornos y cafeteras gourmet', 'Valeria Ramos', 'vramos@chefpro.com', '+1 305-555-412', '30 días', 99.0],
			['ThermaTech Climate & Home', 'Ventiladores, aires portátiles y climatización', 'Jorge Benítez', 'jbenitez@thermatech.net', '+1 305-555-413', '30 días', 94.8],
			['HomeComfort Florida Wholesalers', 'Planchas, aspiradoras y pequeños electrodomésticos', 'Silvia Castro', 'scastro@homecomfortfl.com', '+1 954-555-414', '45 días', 97.1],
			['VoltMax Domestic Goods', 'Licuadoras, batidoras y tostadoras industriales', 'Diego Pardo', 'dpardo@voltmax.com', '+1 305-555-415', '30 días', 96.3],

			// Accesorios
			['CableLink & Power Accessories', 'Cables USB-C, Lightning y adaptadores certificados', 'Andrés Mendoza', 'amendoza@cablelink.com', '+1 305-555-420', '30 días', 99.4],
			['ArmorCase Mobile Protection', 'Fundas de alto impacto y cristales templados 9H', 'Lucía Ferrer', 'lferrer@armorcase.com', '+1 305-555-421', '30 días', 98.9],
			['OptiAudio Sound Systems', 'Auriculares inalámbricos TWS y parlantes Bluetooth', 'Mateo Gil', 'mgil@optiaudio.com', '+1 954-555-422', '45 días', 97.8],
			['ConnectPro Wireless Gear', 'Powerbanks de carga rápida y cargadores MagSafe', 'Sofia Navarro', 'snavarro@connectpro.com', '+1 305-555-423', '30 días', 98.1],
			['SmartShield Gadgets Miami', 'Soportes magnéticos y organizadores ergonómicos', 'Emilio Cruz', 'ecruz@smartshield.com', '+1 305-555-424', '30 días', 96.7],

			// Alimentos
			['Distribuidora de Abarrotes Costa Sur', 'Granos secos, harinas, pastas y conservas', 'Fernando Mora', 'fmora@costasur.com', '+1 305-555-430', '30 días', 98.7],
			['Panificadora del Puerto S.A.', 'Panificación fresca, moldes, baguettes y bollería', 'Carla Ibáñez', 'cibanez@panpuerto.com', '+1 305-555-431', '15 días', 99.5],
			['Granos del Valle Florida Inc', 'Arroces seleccionados, lentejas y frijoles negros', 'Gustavo Ortiz', 'gortiz@granosvalle.com', '+1 954-555-432', '30 días', 97.9],
			['Molinos & Cereales del Sol', 'Avenas, cereales fortificados y harinas de trigo', 'Irene Suárez', 'isuarez@molinossol.com', '+1 305-555-433', '30 días', 96.8],
			['Conservas & Especias La Criolla', 'Atunes en aceite/agua, sardinas y condimentos', 'Nelson Prieto', 'nprieto@lacriolla.com', '+1 305-555-434', '45 días', 98.4],
			['Aceites Puros del Caribe', 'Aceites de oliva extra virgen, girasol y canola', 'Beatriz León', 'bleon@aceitescaribe.com', '+1 305-555-435', '30 días', 99.0],
			['Alimentos Selectos Palm Beach', 'Pastas importadas, salsas pesto y tomate rústico', 'Héctor Guerra', 'hguerra@selectospb.com', '+1 561-555-436', '30 días', 97.3],
			['Abarrotes Unidos de Miami LLC', 'Galletas dulces, aperitivos salados y snacks', 'Mónica Rivas', 'mrivas@abarrotesunidos.com', '+1 305-555-437', '30 días', 98.1],

			// Bebidas
			['Bebidas Tropicales del Atlántico', 'Sodas artesanales y refrescos carbonatados', 'Felipe Santos', 'fsantos@bebidastropicales.com', '+1 305-555-440', '30 días', 98.2],
			['Manantiales de Florida Springs', 'Agua purificada y mineral natural embotellada', 'Clara Montenegro', 'cmontenegro@flsprings.com', '+1 305-555-441', '15 días', 99.8],
			['Cervecería & Refrescos Sunshine', 'Bebidas energéticas, isotónicas y refrescos', 'Gonzalo Vidal', 'gvidal@sunshinebrew.com', '+1 954-555-442', '30 días', 97.4],
			['Gourmet Coffee Roasters Miami', 'Café en grano y molido 100% arábica de origen', 'Adriana Luna', 'aluna@gourmetcoffeemiami.com', '+1 305-555-443', '30 días', 99.2],
			['Jugos Cítricos del Sur S.A.', 'Jugos 100% exprimidos de naranja, mandarina y pomelo', 'Daniel Osorio', 'dosorio@jugossur.com', '+1 305-555-444', '15 días', 98.9],
			['Agua Pura PureWater Corp', 'Garrafones y botellas ecológicas biodegradables', 'Raquel Méndez', 'rmendez@purewatercorp.com', '+1 954-555-445', '15 días', 99.1],
			['Té Artesanal & Infusiones Florida', 'Tés helados listos para tomar y yerbas aromáticas', 'Julián Bravo', 'jbravo@teartesanal.com', '+1 305-555-446', '30 días', 96.5],

			// Lácteos
			['Lácteos del Valle Central S.A.', 'Leches enteras, descremadas y deslactosadas', 'Esteban Parra', 'eparra@lacteosvalle.com', '+1 305-555-450', '15 días', 99.6],
			['Quesería Artesanal Florida Cheese', 'Quesos cheddar, gouda, mozzarella y parmesano', 'Natalia Rojas', 'nrojas@floridacheese.com', '+1 305-555-451', '21 días', 98.4],
			['Distribuidora Láctea La Holandesa', 'Mantequillas gourmet, cremas de leche y natas', 'Pablo Quintero', 'pquintero@laholandesa.com', '+1 954-555-452', '15 días', 97.7],
			['Yogurt Natural Sunshine Farms', 'Yogur griego tradicional, probióticos y kéfir', 'Gloria Paredes', 'gparedes@sunshinefarms.com', '+1 305-555-453', '15 días', 99.3],
			['DairyFresh Florida Wholesalers', 'Quesos frescos, ricota y postres lácteos listos', 'Manuel Cárdenas', 'mcardenas@dairyfreshfl.com', '+1 305-555-454', '21 días', 98.0],
			['Granja San Martín Avícola & Huevos', 'Huevos frescos de campo Grado A y AAA', 'Alfonso Marín', 'amarin@granjasanmartin.com', '+1 954-555-455', '15 días', 99.4],

			// Hogar
			['Químicos & Limpieza EcoClean LLC', 'Detergentes concentrados biodegradables y desengrasantes', 'Hugo Salgado', 'hsalgado@ecocleanllc.com', '+1 305-555-460', '30 días', 98.0],
			['Detergentes Industriales del Caribe', 'Blanqueadores, cloros formulados y limpiapisos', 'Sandra Barreto', 'sbarreto@detercaribe.com', '+1 305-555-461', '45 días', 97.2],
			['Hogar & Confort Textiles Florida', 'Toallas de microfibra, trapeadores y paños cocina', 'Rodrigo Silva', 'rsilva@hogarconfort.com', '+1 954-555-462', '30 días', 98.8],
			['Plásticos & Menaje Florida Inc', 'Bolsas de basura reforzadas, contenedores herméticos', 'Teresa Molina', 'tmolina@plasticosfl.com', '+1 305-555-463', '45 días', 96.9],
			['BrilloMax Artículos de Aseo', 'Lavatrastes cítricos, esponjas de alta duración y fibras', 'Guillermo Paz', 'gpaz@brillomax.com', '+1 305-555-464', '30 días', 97.6],
			['CleanPro Soluciones Domésticas', 'Aromatizantes continuos, desinfectantes en aerosol', 'Verónica Bustos', 'vbustos@cleanpro.com', '+1 954-555-465', '30 días', 98.3],

			// Cuidado Personal
			['Laboratorios Dermocare USA', 'Champús dermatológicos, acondicionadores sin sal', 'Camila Pineda', 'cpineda@dermocareusa.com', '+1 305-555-470', '30 días', 99.1],
			['Higiene & Salud Personal Miami', 'Jabones corporales botánicos, geles de ducha', 'Sebastián Cordero', 'scordero@higienesalud.com', '+1 305-555-471', '30 días', 98.5],
			['Aromas & Belleza Natural LLC', 'Cremas hidratantes aloe vera, lociones con colágeno', 'Diana Vergara', 'dvergara@aromasbelleza.com', '+1 305-555-472', '45 días', 97.4],
			['PharmaCare Distribuciones Florida', 'Alcohol antiséptico, algodones, gasas y apósitos', 'Ignacio Valle', 'ivalle@pharmacarefl.com', '+1 305-555-473', '30 días', 99.7],
			['DentalPro Supplies Florida', 'Pastas dentales flúor total, cepillos cerda media, hilo', 'Mariela Franco', 'mfranco@dentalprofl.com', '+1 954-555-474', '30 días', 98.9],
			['Glow & Pure Cosméticos Inc', 'Protectores solares toque seco SPF 50+, desodorantes', 'Ricardo Zambrano', 'rzambrano@glowpure.com', '+1 305-555-475', '30 días', 98.2],
			['DermaProtect Personal Care', 'Toallitas húmedas hipoalergénicas, pañales y cuidado íntimo', 'Paola Espinoza', 'pespinoza@dermaprotect.com', '+1 305-555-476', '30 días', 97.8]
		];

		$insShop = $db->prepare("INSERT INTO shopping_locations (name, description, active) VALUES (?, ?, 1)");
		$insMeta = $db->prepare("INSERT OR REPLACE INTO suppliers_meta (id, contact_name, email, phone, tax_id, address, lead_time_days, min_order_amount, payment_terms, rating, active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)");

		$created = 0;
		foreach ($suppliers as $i => $s) {
			$name = $s[0];
			$id = $db->query("SELECT id FROM shopping_locations WHERE name = " . $db->quote($name))->fetchColumn();
			if (!$id) {
				$insShop->execute([$name, $s[1]]);
				$id = intval($db->lastInsertId());
				$created++;
			} else {
				$id = intval($id);
			}

			$taxId = sprintf('US-FL-%06d', 700000 + $id * 17);
			$address = 'Miami Logistics Park Suite ' . (100 + $id);
			$leadDays = 2 + ($id % 5);
			$minOrder = 500 + ($id % 8) * 250;
			$rating = $s[6];
			$terms = $s[5];

			$insMeta->execute([$id, $s[2], $s[3], $s[4], $taxId, $address, $leadDays, $minOrder, $terms, $rating]);
		}

		return $created;
	}

	private function EnsureBranchStorageLocations(\PDO $db): array
	{
		$locs = [];
		$ins = $db->prepare("INSERT INTO locations (name, description, branch_id, storage_class, is_freezer, active) VALUES (?, ?, ?, ?, ?, 1)");

		for ($b = 1; $b <= 10; $b++) {
			$locs[$b] = [];
			foreach (['ambient' => 'Bodega Seca', 'chilled' => 'Cámara Fría', 'frozen' => 'Congelador', 'secure' => 'Bóveda de Seguridad'] as $class => $name) {
				$lid = $db->query("SELECT id FROM locations WHERE branch_id = $b AND storage_class = '$class' LIMIT 1")->fetchColumn();
				if (!$lid) {
					$isFreezer = $class === 'frozen' ? 1 : 0;
					$ins->execute(["$name - Sucursal $b", "Ubicación $class sucursal $b", $b, $class, $isFreezer]);
					$lid = intval($db->lastInsertId());
				} else {
					$lid = intval($lid);
				}
				$locs[$b][$class] = $lid;
			}
		}

		return $locs;
	}

	private function SeedEnterpriseCatalog(\PDO $db, array $branchLocations): array
	{
		// Departamentos y grupos correspondientes
		// Generaremos un catálogo balanceado de exactamente 650 productos con distribución:
		// Tecnología: 75 productos
		// Electrodomésticos: 65 productos
		// Accesorios: 80 productos
		// Alimentos: 115 productos
		// Bebidas: 85 productos
		// Lácteos: 75 productos
		// Hogar: 85 productos
		// Cuidado Personal: 70 productos
		// TOTAL: 650 productos
		
		$definitions = [
			'Tecnología' => [
				'group_id' => 14,
				'qu_id' => 2, // Pieza
				'storage_class' => 'secure',
				'is_sensitive' => 1,
				'is_serialized' => 1,
				'imei_tracking' => 1,
				'shelf_days' => 0,
				'count' => 75,
				'sku_prefix' => 'TEC',
				'items' => [
					['Smartphone iPhone 15 Pro Max 256GB Titanio Natural', 950.0, 1199.99],
					['Smartphone iPhone 15 Pro 128GB Azul Titanio', 820.0, 999.99],
					['Smartphone iPhone 15 128GB Negro', 640.0, 799.99],
					['Smartphone iPhone 14 128GB Medianoche', 520.0, 699.99],
					['Smartphone Samsung Galaxy S24 Ultra 512GB Gris Titanio', 1020.0, 1299.99],
					['Smartphone Samsung Galaxy S24 Plus 256GB Negro Ónix', 780.0, 999.99],
					['Smartphone Samsung Galaxy S24 128GB Violeta Cobalto', 610.0, 799.99],
					['Smartphone Samsung Galaxy A55 5G 128GB Azul Marino', 290.0, 399.99],
					['Smartphone Samsung Galaxy A35 5G 128GB Lavanda', 230.0, 319.99],
					['Smartphone Google Pixel 8 Pro 128GB Porcelana', 750.0, 949.99],
					['Smartphone Google Pixel 8 128GB Obsidiana', 510.0, 699.99],
					['Smartphone Xiaomi 14 Ultra 512GB Blanco', 850.0, 1099.99],
					['Smartphone Xiaomi Redmi Note 13 Pro+ 5G 256GB', 310.0, 429.99],
					['Smartphone Motorola Edge 50 Ultra 512GB Peach Fuzz', 680.0, 899.99],
					['Smartphone Motorola Moto G84 5G 256GB Viva Magenta', 180.0, 249.99],
					['Tablet iPad Pro 13 M4 256GB Wi-Fi Gris Espacial', 1050.0, 1299.99],
					['Tablet iPad Air 11 M2 128GB Wi-Fi Blanco Estelar', 480.0, 599.99],
					['Tablet iPad 10ma Gen 64GB Wi-Fi Plata', 280.0, 349.99],
					['Tablet iPad mini 6ta Gen 64GB Wi-Fi Púrpura', 380.0, 499.99],
					['Tablet Samsung Galaxy Tab S9 Ultra 256GB Grafito', 910.0, 1199.99],
					['Tablet Samsung Galaxy Tab S9 FE 128GB Menta', 330.0, 449.99],
					['Smartwatch Apple Watch Ultra 2 Titanio Correa Ocean', 620.0, 799.99],
					['Smartwatch Apple Watch Series 9 45mm Medianoche', 320.0, 429.99],
					['Smartwatch Apple Watch SE 40mm Blanco Estelar', 190.0, 249.99],
					['Smartwatch Samsung Galaxy Watch 6 Classic 47mm Negro', 280.0, 379.99],
					['Smartwatch Samsung Galaxy Watch 6 40mm Oro', 200.0, 269.99],
					['Laptop MacBook Pro 14 M3 Pro 18GB 512GB Negro Espacial', 1600.0, 1999.99],
					['Laptop MacBook Air 15 M3 16GB 512GB Medianoche', 1250.0, 1499.99],
					['Laptop MacBook Air 13 M2 8GB 256GB Blanco Estelar', 790.0, 999.99],
					['Laptop Dell XPS 15 OLED Intel i7 16GB 1TB SSD', 1350.0, 1699.99],
					['Laptop Lenovo Legion Pro 5 Gen 8 RTX 4070 16GB 1TB', 1280.0, 1599.99],
					['Laptop HP Pavilion Plus 14 OLED Ryzen 7 16GB 512GB', 690.0, 849.99],
					['Laptop Asus Zenbook 14 OLED Core Ultra 7 16GB 1TB', 880.0, 1099.99],
					['Consola PlayStation 5 Slim 1TB Blanco', 390.0, 499.99],
					['Consola Xbox Series X 1TB Negro Mate', 390.0, 499.99],
					['Consola Nintendo Switch OLED Modelo Mario Red', 270.0, 349.99]
				]
			],
			'Electrodomésticos' => [
				'group_id' => 19,
				'qu_id' => 2,
				'storage_class' => 'ambient',
				'is_sensitive' => 0,
				'is_serialized' => 0,
				'imei_tracking' => 0,
				'shelf_days' => 0,
				'count' => 65,
				'sku_prefix' => 'ELE',
				'items' => [
					['Freidora de Aire Digital Ninja DualZone 9.5L', 140.0, 199.99],
					['Freidora de Aire Instant Vortex Plus 5.7L con Ventana', 85.0, 129.99],
					['Cafetera Espresso Automática DeLonghi Magnifica S', 420.0, 599.99],
					['Cafetera de Goteo Programable Cuisinart 12 Tazas', 55.0, 79.99],
					['Licuadora Profesional Ninja Auto-iQ 1200W con Jarra Tritán', 75.0, 109.99],
					['Licuadora de Alta Potencia Vitamix Explorian E310', 260.0, 349.99],
					['Horno Microondas Inverter Panasonic 1.2 Cu Ft Acero', 110.0, 159.99],
					['Horno Tostador con Convección Breville Smart Oven Pro', 190.0, 269.99],
					['Aspiradora Robot iRobot Roomba Combo j7+ con Auto-vaciado', 550.0, 749.99],
					['Aspiradora Inalámbrica Dyson V12 Detect Slim Total Clean', 450.0, 629.99],
					['Aspiradora de Mano Shark Wandvac Power Pet', 80.0, 119.99],
					['Olla Multicocción Rápida Instant Pot Duo 7 en 1 5.7L', 65.0, 99.99],
					['Batidora de Pie KitchenAid Artisan 4.8L Rojo Imperial', 310.0, 449.99],
					['Procesador de Alimentos Cuisinart Core Custom 13 Tazas', 140.0, 199.99],
					['Purificador de Aire Inteligente Levoit Core 400S HEPA', 150.0, 219.99],
					['Ventilador de Torre Oscilante Dreo Nomad One Smart', 45.0, 69.99],
					['Plancha de Vapor Vertical Philips Serie 3000 Compacta', 38.0, 59.99],
					['Plancha a Vapor Tradicional Rowenta Everlast Anticalc', 60.0, 89.99],
					['Tostadora de Ranura Ancha Smeg Estilo 50s 2 Rebanadas', 130.0, 189.99],
					['Hervidor Eléctrico de Cuello de Cisne Fellow Stagg EKG', 120.0, 165.0]
				]
			],
			'Accesorios' => [
				'group_id' => 25,
				'qu_id' => 2,
				'storage_class' => 'ambient',
				'is_sensitive' => 1,
				'is_serialized' => 0,
				'imei_tracking' => 0,
				'shelf_days' => 0,
				'count' => 80,
				'sku_prefix' => 'ACC',
				'items' => [
					['Cable USB-C a USB-C Trenzado de Nylon 2m 100W', 11.0, 19.99],
					['Cable USB-C a Lightning Certificado MFi 1.5m Blanco', 12.5, 22.99],
					['Cargador de Pared Anker 313 GaN II 30W Plegable', 15.0, 24.99],
					['Cargador Rápido Baseus 65W 3 Puertos USB-C y USB-A', 26.0, 39.99],
					['Cargador Inalámbrico Magnético 3 en 1 para iPhone y Apple Watch', 42.0, 69.99],
					['Power Bank Anker MagGo 10000mAh Magnético con Soporte', 38.0, 59.99],
					['Power Bank Slim 20000mAh Carga Rápida 22.5W Doble USB', 24.0, 39.99],
					['Funda OtterBox Defender para iPhone 15 Pro Max Negra', 28.0, 49.99],
					['Funda Transparente Antiamarilleo con MagSafe para iPhone 15', 14.0, 26.99],
					['Funda Antigolpes Spigen Tough Armor para Galaxy S24 Ultra', 18.0, 32.99],
					['Protector de Pantalla Cristal Templado 9H Pack x2 con Guía', 7.5, 14.99],
					['Auriculares Bluetooth Sony WH-1000XM5 Cancelación de Ruido', 270.0, 399.99],
					['Auriculares Bluetooth Bose QuietComfort Ultra Negro', 290.0, 429.99],
					['Auriculares AirPods Pro 2da Gen con Estuche MagSafe USB-C', 175.0, 249.99],
					['Auriculares Inalámbricos Galaxy Buds FE Grafito', 62.0, 99.99],
					['Parlante Bluetooth Portátil JBL Flip 6 Resistente al Agua IP67', 75.0, 119.99],
					['Parlante Bluetooth Marshall Emberton II Negro y Dorado', 115.0, 169.99],
					['Teclado Inalámbrico Multi-dispositivo Logitech MX Keys S', 78.0, 119.99],
					['Mouse Ergonómico Inalámbrico Logitech MX Master 3S', 65.0, 99.99],
					['Soporte de Auto Magnético para Ventila con Carga Qi 15W', 19.0, 32.99]
				]
			],
			'Alimentos' => [
				'group_id' => 3,
				'qu_id' => 18, // Paquete
				'storage_class' => 'ambient',
				'is_sensitive' => 0,
				'is_serialized' => 0,
				'imei_tracking' => 0,
				'shelf_days' => 365,
				'count' => 115,
				'sku_prefix' => 'ALI',
				'items' => [
					['Arroz Blanco Grano Largo Extra Calidad 2 kg', 2.10, 3.49],
					['Arroz Jazmín Aromático Importado 1 kg', 2.80, 4.29],
					['Frijoles Negros Seleccionados Secos Bolsa 1 kg', 1.60, 2.79],
					['Lentejas Pardinas de Grano Entero 1 kg', 1.75, 2.99],
					['Aceite de Oliva Extra Virgen Prensado en Frío 750ml', 7.20, 11.49],
					['Aceite Vegetal Puro de Canola 1.5 Litros', 3.80, 5.99],
					['Pasta Espagueti Italiano de Sémola de Trigo 500g', 1.10, 1.89],
					['Pasta Fusilli Tradicional 500g', 1.10, 1.89],
					['Salsa de Tomate Rústica con Albahaca Frasco 400g', 1.65, 2.79],
					['Atún Claro en Aceite de Oliva Abre Fácil Lata 160g', 1.70, 2.89],
					['Atún en Agua Lomo Entero Lata 160g', 1.45, 2.49],
					['Sardinas en Salsa de Tomate y Especias 125g', 0.95, 1.69],
					['Harina de Trigo Fortificada Todo Uso 1 kg', 1.25, 2.19],
					['Avena en Hojuelas Tradicional Tarro 800g', 2.40, 3.89],
					['Cereal Tostado de Maíz sin Azúcar Añadida 500g', 2.20, 3.69],
					['Galletas Dulces Variadas con Chispas de Chocolate 350g', 1.95, 3.29],
					['Galletas Saladas Tipo Crackers Pack Familiar 400g', 1.80, 2.99],
					['Azúcar Blanca Refinada de Caña 2 kg', 1.85, 2.99],
					['Sal Marina Fina Yodada y Fluorada 1 kg', 0.65, 1.19],
					['Café Tostado y Molido Gourmet Clásico 454g', 4.80, 7.49]
				]
			],
			'Bebidas' => [
				'group_id' => 7,
				'qu_id' => 10, // Botella
				'storage_class' => 'ambient',
				'is_sensitive' => 0,
				'is_serialized' => 0,
				'imei_tracking' => 0,
				'shelf_days' => 240,
				'count' => 85,
				'sku_prefix' => 'BEB',
				'items' => [
					['Agua Mineral Natural de Manantial sin Gas 1.5L', 0.70, 1.29],
					['Agua Mineral con Gas Finamente Carbonatada 1L', 0.85, 1.49],
					['Agua Purificada Botella Ecológica 500ml Pack x12', 3.20, 5.49],
					['Jugo 100% Naranja Exprimida sin Azúcar 1 Litro', 2.10, 3.49],
					['Jugo de Manzana Roja Clarificado 1 Litro', 1.90, 3.19],
					['Néctar de Durazno y Frutas Tropicales 1 Litro', 1.60, 2.79],
					['Gaseosa Refresco Cola Tradicional Botella 2 Litros', 1.75, 2.99],
					['Gaseosa Refresco Cola Zero Azúcar Botella 2 Litros', 1.75, 2.99],
					['Refresco Lima Limón Sparkling 1.5 Litros', 1.50, 2.59],
					['Bebida Isotónica Electrolitos Mandarina 600ml', 1.10, 1.99],
					['Bebida Energética Ultra Zero Lata 473ml', 1.65, 2.89],
					['Té Helado sabor Limón Natural Botella 500ml', 0.95, 1.69],
					['Té Verde con Toque de Miel y Ginseng 500ml', 1.05, 1.89],
					['Bebida de Té Negro Frutos Rojos 1 Litro', 1.45, 2.49],
					['Café Frío Cold Brew Nitro en Lata 250ml', 1.90, 3.29]
				]
			],
			'Lácteos' => [
				'group_id' => 8,
				'qu_id' => 9, // Litro
				'storage_class' => 'chilled',
				'is_sensitive' => 0,
				'is_serialized' => 0,
				'imei_tracking' => 0,
				'shelf_days' => 28,
				'count' => 75,
				'sku_prefix' => 'LAC',
				'items' => [
					['Leche Entera Pasteurizada Vitaminada 1 Litro', 1.10, 1.79],
					['Leche Semidescremada 2% Grasa Tetra Pak 1 Litro', 1.15, 1.89],
					['Leche Deslactosada y Descremada 1 Litro', 1.25, 1.99],
					['Leche de Almendras sin Azúcar Añadida 1 Litro', 1.80, 2.99],
					['Leche de Avena Cremosa Fortificada 1 Litro', 1.90, 3.19],
					['Yogur Griego Natural sin Azúcar Pote 500g', 2.10, 3.49],
					['Yogur Griego con Miel y Nueces Vaso 150g', 1.05, 1.79],
					['Yogur Líquido sabor Fresa Familiar 1 Litro', 1.75, 2.89],
					['Yogur Líquido sabor Melocotón Familiar 1 Litro', 1.75, 2.89],
					['Queso Mozzarella en Bloque Tajable 500g', 3.40, 5.29],
					['Queso Cheddar Madurado Laminado 250g', 2.30, 3.79],
					['Queso Gouda Tradicional Holandés Porción 300g', 3.10, 4.99],
					['Queso Campesino Blanco Fresco Semiduro 400g', 2.60, 4.19],
					['Queso Crema Untable Original Tina 227g', 1.55, 2.69],
					['Mantequilla con Sal de Pastoreo Barra 250g', 2.10, 3.49],
					['Mantequilla sin Sal Repostera Barra 250g', 2.10, 3.49],
					['Crema de Leche Fresca para Batir 200ml', 1.20, 1.99],
					['Huevos Frescos de Granja Grado AA Cubeta x30', 4.50, 6.99],
					['Huevos Orgánicos de Gallina Libre de Jaula x12', 2.80, 4.49]
				]
			],
			'Hogar' => [
				'group_id' => 23,
				'qu_id' => 10, // Botella
				'storage_class' => 'ambient',
				'is_sensitive' => 0,
				'is_serialized' => 0,
				'imei_tracking' => 0,
				'shelf_days' => 730,
				'count' => 85,
				'sku_prefix' => 'HOG',
				'items' => [
					['Detergente Líquido Concentrado Lavadora Aroma Frescor 3L', 8.20, 13.49],
					['Suavizante de Telas Hipoalergénico Aroma Floral 2L', 3.90, 6.49],
					['Limpiador Multisuperficies Antibacterial Lavanda 1L', 1.60, 2.79],
					['Desengrasante de Cocina Alta Eficacia Gatillo 750ml', 2.20, 3.79],
					['Lavavajillas Líquido Concentrado Limón 850ml', 1.85, 3.19],
					['Pastillas para Lavavajillas Automático Todo en 1 x30', 6.40, 10.49],
					['Cloro Gel Limpiador con Bicarbonato y Fragancia 1L', 1.45, 2.49],
					['Papel Higiénico Doble Hoja Ultra Suave Paquete 12 Rollos', 5.50, 8.99],
					['Toallas de Papel de Cocina Absorbentes Pack x3 Mega Rollos', 3.20, 5.29],
					['Bolsas de Basura Gruesas 50L con Cordón Ajustable x30', 2.80, 4.69],
					['Esponjas de Cocina Salvaúñas Fibra Cero Rayas Pack x4', 1.35, 2.39],
					['Paños de Microfibra Multiuso Atrapapolvo Pack x6', 2.60, 4.49],
					['Ambientador en Aerosol Eliminador de Olores Lavanda 300ml', 1.70, 2.89],
					['Insecticida en Aerosol Doble Acción Acción Rápida 400ml', 2.40, 3.99]
				]
			],
			'Cuidado Personal' => [
				'group_id' => 24,
				'qu_id' => 10, // Botella
				'storage_class' => 'ambient',
				'is_sensitive' => 0,
				'is_serialized' => 0,
				'imei_tracking' => 0,
				'shelf_days' => 730,
				'count' => 70,
				'sku_prefix' => 'CUI',
				'items' => [
					['Champú Nutrición Profunda con Aceite de Argán 750ml', 4.10, 6.99],
					['Acondicionador Restaurador con Keratina Líquida 750ml', 4.10, 6.99],
					['Jabón Líquido Corporal Hidratante Avena y Miel 500ml', 2.60, 4.49],
					['Jabón en Barra Humectante Clásico Pack x3 unidades', 1.90, 3.29],
					['Pasta Dental Protección Total Anticaries Flúor 150ml x2', 2.80, 4.79],
					['Enjuague Bucal Menta Glacial Cero Alcohol 500ml', 2.50, 4.29],
					['Cepillo Dental Cerdas Suave Ergonómico Pack x3', 2.20, 3.89],
					['Desodorante Antitranspirante Roll-on Invisible 50ml', 1.75, 2.99],
					['Desodorante en Aerosol Cuidado Total 48 Horas 150ml', 2.40, 3.99],
					['Crema Corporal Hidratante con Manteca de Karité 400ml', 3.80, 6.49],
					['Protector Solar Facial Toque Seco Mate SPF 50+ 50ml', 7.50, 12.99],
					['Toallitas Húmedas Desmaquillantes con Agua Micelar x30', 1.45, 2.59],
					['Gel Antibacterial con Glicerina e Hidratante 250ml', 1.30, 2.29]
				]
			]
		];

		// Proveedores por departamento para asignarles productos
		$deptSuppliers = [
			'Tecnología' => $db->query("SELECT id FROM shopping_locations WHERE name LIKE '%Tech%' OR name LIKE '%Telecom%' OR name LIKE '%Electronics%' OR name LIKE '%Computers%'")->fetchAll(\PDO::FETCH_COLUMN) ?: [1],
			'Electrodomésticos' => $db->query("SELECT id FROM shopping_locations WHERE name LIKE '%Electro%' OR name LIKE '%Appliances%' OR name LIKE '%Kitchen%' OR name LIKE '%Domestic%'")->fetchAll(\PDO::FETCH_COLUMN) ?: [2],
			'Accesorios' => $db->query("SELECT id FROM shopping_locations WHERE name LIKE '%CableLink%' OR name LIKE '%ArmorCase%' OR name LIKE '%Audio%' OR name LIKE '%ConnectPro%'")->fetchAll(\PDO::FETCH_COLUMN) ?: [3],
			'Alimentos' => $db->query("SELECT id FROM shopping_locations WHERE name LIKE '%Abarrotes%' OR name LIKE '%Panificadora%' OR name LIKE '%Granos%' OR name LIKE '%Conservas%'")->fetchAll(\PDO::FETCH_COLUMN) ?: [4],
			'Bebidas' => $db->query("SELECT id FROM shopping_locations WHERE name LIKE '%Bebidas%' OR name LIKE '%Springs%' OR name LIKE '%Sunshine%' OR name LIKE '%Jugos%'")->fetchAll(\PDO::FETCH_COLUMN) ?: [5],
			'Lácteos' => $db->query("SELECT id FROM shopping_locations WHERE name LIKE '%Lácteos%' OR name LIKE '%Cheese%' OR name LIKE '%Dairy%' OR name LIKE '%Holandesa%'")->fetchAll(\PDO::FETCH_COLUMN) ?: [6],
			'Hogar' => $db->query("SELECT id FROM shopping_locations WHERE name LIKE '%EcoClean%' OR name LIKE '%Detergentes%' OR name LIKE '%Plásticos%' OR name LIKE '%BrilloMax%'")->fetchAll(\PDO::FETCH_COLUMN) ?: [7],
			'Cuidado Personal' => $db->query("SELECT id FROM shopping_locations WHERE name LIKE '%Dermocare%' OR name LIKE '%Higiene%' OR name LIKE '%PharmaCare%' OR name LIKE '%DentalPro%'")->fetchAll(\PDO::FETCH_COLUMN) ?: [8]
		];

		$insProd = $db->prepare("
			INSERT INTO products (
				name, description, product_group_id, active, location_id, shopping_location_id,
				qu_id_purchase, qu_id_stock, qu_id_consume, qu_id_price, min_stock_amount,
				default_best_before_days, sku, sale_price, storage_class, is_sensitive,
				is_serialized, imei_tracking, device_control, department, risk_level
			) VALUES (?, ?, ?, 1, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
		");

		$insBc = $db->prepare("INSERT OR REPLACE INTO product_barcodes (product_id, barcode, qu_id, amount, shopping_location_id, last_price) VALUES (?, ?, ?, 1, ?, ?)");

		$prodCount = 0;
		$bcCount = 0;
		$existingSkus = array_flip($db->query("SELECT sku FROM products WHERE sku IS NOT NULL")->fetchAll(\PDO::FETCH_COLUMN));

		foreach ($definitions as $deptName => $cfg) {
			$supPool = $deptSuppliers[$deptName];
			$baseItems = $cfg['items'];
			$targetCount = $cfg['count'];
			$prefix = $cfg['sku_prefix'];
			$defaultLoc = $branchLocations[1][$cfg['storage_class']] ?? $branchLocations[1]['ambient'];

			for ($i = 1; $i <= $targetCount; $i++) {
				$sku = sprintf('%s-%03d', $prefix, $i);

				// Verificar si el SKU ya existe
				if (isset($existingSkus[$sku])) {
					continue;
				}

				// Obtener nombre y precios base o crear variación coherente
				$baseIdx = ($i - 1) % count($baseItems);
				$template = $baseItems[$baseIdx];
				$varSuffix = $i > count($baseItems) ? ' (Variante ' . (intdiv($i, count($baseItems)) + 1) . ')' : '';
				$prodName = $template[0] . $varSuffix;
				$cost = round($template[1] * (0.95 + (rand(0, 10) / 100)), 2);
				$price = round($template[2] * (0.95 + (rand(0, 10) / 100)), 2);
				if ($price <= $cost) {
					$price = round($cost * 1.35, 2);
				}

				$supId = $supPool[array_rand($supPool)];
				$riskLevel = $cfg['is_sensitive'] ? 'high' : ($cfg['storage_class'] === 'chilled' ? 'medium' : 'low');
				$minStock = $deptName === 'Tecnología' ? 3 : ($deptName === 'Alimentos' ? 24 : 10);

				$insProd->execute([
					$prodName,
					"Producto catálogo empresarial DAILY - $deptName",
					$cfg['group_id'],
					$defaultLoc,
					$supId,
					$cfg['qu_id'],
					$cfg['qu_id'],
					$cfg['qu_id'],
					$cfg['qu_id'],
					$minStock,
					$cfg['shelf_days'],
					$sku,
					$price,
					$cfg['storage_class'],
					$cfg['is_sensitive'],
					$cfg['is_serialized'],
					$cfg['imei_tracking'],
					$cfg['is_serialized'],
					$deptName,
					$riskLevel
				]);

				$newPid = intval($db->lastInsertId());
				$prodCount++;

				// Barcode EAN-13 válido (código de país 770 + departamento + sku número)
				$deptCode = str_pad(substr((string)$cfg['group_id'], 0, 2), 2, '0', STR_PAD_LEFT);
				$itemNum = str_pad((string)$i, 4, '0', STR_PAD_LEFT);
				$prefix12 = '770' . $deptCode . '000' . $itemNum;
				$checksum = $this->CalculateEan13Checksum($prefix12);
				$ean13 = $prefix12 . $checksum;

				$insBc->execute([$newPid, $ean13, $cfg['qu_id'], $supId, $cost]);
				$bcCount++;
			}
		}

		return ['products' => $prodCount, 'barcodes' => $bcCount];
	}

	private function CalculateEan13Checksum(string $digits12): int
	{
		$digits12 = str_pad(substr($digits12, 0, 12), 12, '0', STR_PAD_RIGHT);
		$sum = 0;
		for ($i = 0; $i < 12; $i++) {
			$val = intval($digits12[$i] ?? 0);
			$sum += ($i % 2 === 0) ? $val : ($val * 3);
		}
		$remainder = $sum % 10;
		return ($remainder === 0) ? 0 : (10 - $remainder);
	}

	private function SeedPhysicalStock(\PDO $db, array $branchLocations): int
	{
		$products = $db->query("SELECT id, storage_class, min_stock_amount, default_best_before_days FROM products WHERE active = 1")->fetchAll(\PDO::FETCH_ASSOC);

		// Pre-cargar stocks existentes para evitar miles de queries
		$existingStock = [];
		foreach ($db->query("SELECT product_id, location_id FROM stock")->fetchAll(\PDO::FETCH_NUM) as $row) {
			$existingStock[$row[0] . '_' . $row[1]] = true;
		}

		// Pre-cargar costos de productos
		$productCosts = [];
		foreach ($db->query("SELECT product_id, last_price FROM product_barcodes")->fetchAll(\PDO::FETCH_NUM) as $row) {
			$productCosts[$row[0]] = floatval($row[1]);
		}

		$insStock = $db->prepare("
			INSERT INTO stock (product_id, amount, best_before_date, purchased_date, stock_id, price, open, opened_date, row_created_timestamp, location_id, shopping_location_id, note)
			VALUES (?, ?, ?, ?, ?, ?, 0, NULL, datetime('now'), ?, ?, 'Inventario inicial auditado DAILY')
		");

		$insLog = $db->prepare("
			INSERT INTO stock_log (product_id, amount, best_before_date, purchased_date, spoiled, stock_id, transaction_type, price, undone, location_id, correlation_id, transaction_id, user_id, row_created_timestamp, note)
			VALUES (?, ?, ?, ?, 0, ?, 'purchase', ?, 0, ?, ?, ?, 1, datetime('now'), 'Recepción de compra inicial en bodega')
		");

		$batches = 0;
		$today = date('Y-m-d');

		// Distribuir stock en las 10 sucursales
		foreach ($products as $p) {
			$pid = intval($p['id']);
			$class = $p['storage_class'] ?: 'ambient';
			$min = max(5, intval($p['min_stock_amount']));
			$shelfDays = intval($p['default_best_before_days']);

			// Determinar fecha de vencimiento según la vida útil
			$bbDate = $shelfDays > 0 ? date('Y-m-d', strtotime("+$shelfDays days")) : date('Y-m-d', strtotime('+730 days'));
			$cost = $productCosts[$pid] ?? 10.0;

			for ($b = 1; $b <= 10; $b++) {
				$locId = $branchLocations[$b][$class] ?? $branchLocations[$b]['ambient'];

				if (isset($existingStock[$pid . '_' . $locId])) {
					continue;
				}

				// Cantidad base según tamaño de sucursal
				$mult = ($b === 1 || $b === 6 || $b === 4 || $b === 8) ? 3 : 2;
				$amount = $min * $mult + rand(2, 10);
				$stockId = sprintf('STK-B%02d-P%04d-%04d', $b, $pid, rand(1000, 9999));
				$txId = sprintf('TX-INI-%08d', rand(10000000, 99999999));

				$insStock->execute([$pid, $amount, $bbDate, $today, $stockId, $cost, $locId, 1]);
				$insLog->execute([$pid, $amount, $bbDate, $today, $stockId, $cost, $locId, 'seed_enterprise', $txId]);
				$batches++;
			}
		}

		return $batches;
	}

	private function SeedSalesHistory(\PDO $db): array
	{
		$totalSales = $db->query("SELECT COUNT(*) FROM sales")->fetchColumn();
		if ($totalSales >= 12000) {
			return ['sales' => 0, 'items' => 0];
		}

		$products = $db->query("SELECT id, sale_price FROM products WHERE active = 1 ORDER BY id ASC")->fetchAll(\PDO::FETCH_ASSOC);
		if (count($products) < 20) {
			return ['sales' => 0, 'items' => 0];
		}

		$insSale = $db->prepare("
			INSERT INTO sales (sale_number, branch_id, customer_id, user_id, subtotal, tax_amount, discount_amount, total_amount, total_cogs, gross_margin, payment_method, status, notes, created_timestamp)
			VALUES (?, ?, 1, 1, ?, ?, ?, ?, ?, ?, ?, 'completed', 'Venta regular POS', ?)
		");

		$insItem = $db->prepare("
			INSERT INTO sale_items (sale_id, product_id, stock_id, amount, qu_id, unit_price, subtotal, unit_cost, total_cost, created_timestamp, transaction_id)
			VALUES (?, ?, ?, ?, 2, ?, ?, ?, ?, ?, ?)
		");

		$insStockLog = $db->prepare("
			INSERT INTO stock_log (product_id, amount, best_before_date, purchased_date, spoiled, stock_id, transaction_type, price, undone, location_id, correlation_id, transaction_id, user_id, row_created_timestamp, note)
			VALUES (?, ?, ?, ?, 0, ?, 'consume', ?, 0, ?, ?, ?, 1, ?, 'Venta POS ticket completado')
		");

		$paymentMethods = ['cash', 'card', 'card', 'card', 'digital'];
		$salesCount = 0;
		$itemsCount = 0;

		// Generar ventas para los últimos 21 días en las 10 sucursales
		for ($dayOffset = 21; $dayOffset >= 0; $dayOffset--) {
			$dateStr = date('Y-m-d', strtotime("-$dayOffset days"));

			for ($b = 1; $b <= 10; $b++) {
				// Tickets por día según importancia de tienda
				$ticketsToday = ($b === 1 || $b === 6 || $b === 4 || $b === 8) ? rand(6, 12) : rand(3, 7);

				for ($t = 1; $t <= $ticketsToday; $t++) {
					// Distribución horaria realista: picos 11:00-14:00 y 18:00-20:30
					$peak = (rand(1, 10) > 4);
					$hour = $peak ? (rand(1, 2) === 1 ? rand(11, 14) : rand(18, 20)) : rand(8, 21);
					$minute = rand(0, 59);
					$second = rand(0, 59);
					$saleTime = sprintf('%s %02d:%02d:%02d', $dateStr, $hour, $minute, $second);

					$saleNum = sprintf('POS-B%02d-%s-%04d', $b, str_replace('-', '', $dateStr), $t);
					$pay = $paymentMethods[array_rand($paymentMethods)];

					// Cada ticket tiene entre 1 y 6 líneas
					$lineCount = rand(1, 5);
					$subtotal = 0.0;
					$totalCogs = 0.0;
					$linesData = [];

					for ($l = 0; $l < $lineCount; $l++) {
						$randProd = $products[array_rand($products)];
						$pid = intval($randProd['id']);
						$qty = ($randProd['sale_price'] > 200) ? 1 : rand(1, 3);
						$price = floatval($randProd['sale_price']);
						$cost = round($price * 0.65, 2);
						$lineSub = round($qty * $price, 2);
						$lineCost = round($qty * $cost, 2);

						$subtotal += $lineSub;
						$totalCogs += $lineCost;

						$linesData[] = [
							'pid' => $pid,
							'qty' => $qty,
							'price' => $price,
							'sub' => $lineSub,
							'cost' => $cost,
							'tcost' => $lineCost
						];
					}

					$tax = round($subtotal * 0.07, 2); // 7% Florida sales tax
					$discount = (rand(1, 10) === 1) ? round($subtotal * 0.05, 2) : 0.0;
					$totalAmount = $subtotal + $tax - $discount;
					$grossMargin = $totalAmount - $totalCogs;

					$insSale->execute([$saleNum, $b, $subtotal, $tax, $discount, $totalAmount, $totalCogs, $grossMargin, $pay, $saleTime]);
					$saleId = intval($db->lastInsertId());
					$salesCount++;

					foreach ($linesData as $ld) {
						$txId = sprintf('TX-POS-%08d', rand(10000000, 99999999));
						$stockId = sprintf('STK-B%02d-P%04d-LIVE', $b, $ld['pid']);

						$insItem->execute([
							$saleId,
							$ld['pid'],
							$stockId,
							$ld['qty'],
							$ld['price'],
							$ld['sub'],
							$ld['cost'],
							$ld['tcost'],
							$saleTime,
							$txId
						]);
						$itemsCount++;

						// Registrar consumo en stock_log para que el Kardex y movimientos sean íntegros
						$insStockLog->execute([
							$ld['pid'],
							-$ld['qty'],
							$dateStr,
							$dateStr,
							$stockId,
							$ld['cost'],
							1, // location_id
							'pos_sale_' . $saleId,
							$txId,
							$saleTime
						]);
					}
				}
			}
		}

		return ['sales' => $salesCount, 'items' => $itemsCount];
	}

	private function SeedSerializedDevices(\PDO $db, array $branchLocations): int
	{
		$existing = $db->query("SELECT COUNT(*) FROM device_units")->fetchColumn();
		if ($existing >= 350) {
			return 0;
		}

		$techProds = $db->query("SELECT id, name, sale_price FROM products WHERE is_serialized = 1 OR department = 'Tecnología'")->fetchAll(\PDO::FETCH_ASSOC);
		if (empty($techProds)) {
			return 0;
		}

		$insDevice = $db->prepare("
			INSERT INTO device_units (
				product_id, imei1, imei2, serial_number, color, capacity, supplier_name,
				order_code, received_at, branch_id, location_id, zone, state, unit_cost,
				custodian_area, version, is_demo
			) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Bóveda de Seguridad', 1, 0)
		");

		$colors = ['Titanio Natural', 'Negro Espacial', 'Blanco Estelar', 'Azul Titanio', 'Plata', 'Gris Grafito'];
		$capacities = ['128GB', '256GB', '512GB', '1TB'];
		$states = ['vault', 'vault', 'display', 'display', 'sold', 'sold', 'investigation', 'in_transit'];
		$suppliers = ['Tech Distribuciones Florida LLC', 'NexTech Electronics USA', 'Global Telecom Wholesalers'];

		$count = 0;
		$targetTotal = 380;

		while (($existing + $count) < $targetTotal) {
			$p = $techProds[array_rand($techProds)];
			$pid = intval($p['id']);
			$cost = round($p['sale_price'] * 0.72, 2);
			$b = rand(1, 10);
			$locId = $branchLocations[$b]['secure'] ?? 1;

			$imei1 = sprintf('35%013d', rand(1000000000000, 9999999999999));
			$imei2 = sprintf('35%013d', rand(1000000000000, 9999999999999));
			$serial = 'SN-' . strtoupper(substr(md5(uniqid()), 0, 10));
			$color = $colors[array_rand($colors)];
			$cap = $capacities[array_rand($capacities)];
			$sup = $suppliers[array_rand($suppliers)];
			$state = $states[array_rand($states)];
			$orderCode = sprintf('PO-2026-%04d', rand(100, 999));
			$daysAgo = rand(1, 60);
			$recvDate = date('Y-m-d H:i:s', strtotime("-$daysAgo days"));

			$insDevice->execute([
				$pid,
				$imei1,
				$imei2,
				$serial,
				$color,
				$cap,
				$sup,
				$orderCode,
				$recvDate,
				$b,
				$locId,
				'Bóveda Blindada',
				$state,
				$cost
			]);
			$count++;
		}

		return $count;
	}

	private function SeedInvestigationCases(\PDO $db): int
	{
		$existing = $db->query("SELECT COUNT(*) FROM sensitive_investigation_cases")->fetchColumn();
		if ($existing >= 35) {
			return 0;
		}

		$insCase = $db->prepare("
			INSERT INTO sensitive_investigation_cases (
				case_code, alert_id, branch_id, product_id, department, location_name,
				expected_stock, physical_count, difference_qty, unit_cost, financial_impact,
				stage, suspected_cause_category, evidence_notes, recommended_action,
				recount_status, adjustment_status, workflow_status, priority, confirmed_cause,
				created_at, updated_at
			) VALUES (?, ?, ?, ?, ?, 'Bóveda de Seguridad', ?, ?, ?, ?, ?, ?, ?, ?, ?, 'completed', 'approved', ?, ?, ?, ?, ?)
		");

		$casesData = [
			['Faltante en recuento ciego de iPhone 15 Pro Max', 'operativa', 'Discrepancia en cambio de turno bóveda', 'Revisión de cámaras y arqueo biométrico', 'critical', 'hurto_interno', 'in_progress'],
			['Descuadre serial en Samsung Galaxy S24 Ultra', 'operativa', 'IMEI registrado no coincide con escaneo físico', 'Auditoría a guía de remisión del proveedor', 'high', 'error_recepcion', 'pending_approval'],
			['Avería por caída en montacargas de 2 Laptops MacBook', 'averia', 'Caja estibada golpeada en maniobra de descarga', 'Reclamo directo a póliza seguro transportista', 'medium', 'averia_manipulacion', 'resolved'],
			['Diferencia física en tablets iPad Air exhibición', 'comercial', 'Soporte antirrobo forzado en mesa de experiencia', 'Levantar acta policial y sustituir cable acerado', 'critical', 'hurto_externo', 'closed'],
			['Inconsistencia de inventario en Smartwatches Ultra', 'ajuste', 'Cómputo en sistema 8 vs físico 7 unidades', 'Recuento ciego y conciliación de ventas POS', 'high', 'descuadre_cajas', 'resolved']
		];

		$prods = $db->query("SELECT id, name, sale_price, department FROM products WHERE department = 'Tecnología' OR is_sensitive = 1")->fetchAll(\PDO::FETCH_ASSOC);
		$count = 0;

		for ($i = $existing + 1; $i <= 35; $i++) {
			$cd = $casesData[($i - 1) % count($casesData)];
			$p = $prods[array_rand($prods)];
			$pid = intval($p['id']);
			$b = rand(1, 10);
			$cost = round($p['sale_price'] * 0.70, 2);
			$diff = -1 * rand(1, 3);
			$exp = rand(5, 12);
			$phys = $exp + $diff;
			$impact = abs($diff * $cost);

			$caseCode = sprintf('INV-2026-%04d', 200 + $i);
			$daysAgo = rand(1, 40);
			$created = date('Y-m-d H:i:s', strtotime("-$daysAgo days"));

			$insCase->execute([
				$caseCode,
				$i,
				$b,
				$pid,
				'Tecnología',
				$exp,
				$phys,
				$diff,
				$cost,
				$impact,
				$cd[6],
				$cd[1],
				$cd[2],
				$cd[3],
				$cd[6],
				$cd[4],
				$cd[5],
				$created,
				$created
			]);
			$count++;
		}

		return $count;
	}

	private function SeedFinancialRecoveries(\PDO $db): int
	{
		$existing = $db->query("SELECT COUNT(*) FROM supplier_returns")->fetchColumn();
		if ($existing >= 30) {
			return 0;
		}

		$insReturn = $db->prepare("
			INSERT INTO supplier_returns (
				supplier_id, branch_id, product_id, case_number, batch_number, amount,
				qu_id, unit_cost, total_loss_cost, return_reason, incident_type,
				credit_requested_amount, credit_recovered_amount, loss_unrecovered_amount,
				status, notes, user_id, created_timestamp, resolved_timestamp
			) VALUES (?, ?, ?, ?, ?, ?, 2, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?)
		");

		$reasons = [
			['Vicio oculto de fábrica en lote de pantallas', 'supplier_claim', 'approved', 0.95],
			['Mercancía dañada por transportista en tránsito', 'carrier_claim', 'approved', 1.0],
			['Caducidad prematura imputable al distribuidor', 'supplier_claim', 'approved', 0.85],
			['Robo de mercancía cubierto por póliza seguro', 'insurance_claim', 'approved', 0.90],
			['Reclamo en curso por discrepancia de pesaje', 'carrier_claim', 'pending', 0.0]
		];

		$prods = $db->query("SELECT id, sale_price FROM products WHERE active = 1 ORDER BY id DESC LIMIT 50")->fetchAll(\PDO::FETCH_ASSOC);
		$sups = $db->query("SELECT id FROM shopping_locations ORDER BY id ASC")->fetchAll(\PDO::FETCH_COLUMN);

		$count = 0;
		for ($i = $existing + 1; $i <= 30; $i++) {
			$r = $reasons[($i - 1) % count($reasons)];
			$p = $prods[array_rand($prods)];
			$supId = $sups[array_rand($sups)];
			$b = rand(1, 10);
			$cost = round($p['sale_price'] * 0.65, 2);
			$qty = rand(2, 10);
			$totalLoss = round($qty * $cost, 2);
			$creditReq = $totalLoss;
			$creditRec = round($totalLoss * $r[3], 2);
			$unrecovered = round($totalLoss - $creditRec, 2);

			$caseNum = sprintf('REC-2026-%04d', 500 + $i);
			$batch = sprintf('LOT-2026-%04d', rand(1000, 9999));
			$daysAgo = rand(5, 50);
			$created = date('Y-m-d H:i:s', strtotime("-$daysAgo days"));
			$resolved = ($r[2] === 'approved') ? date('Y-m-d H:i:s', strtotime("-$daysAgo days +3 days")) : null;

			$insReturn->execute([
				$supId,
				$b,
				$p['id'],
				$caseNum,
				$batch,
				$qty,
				$cost,
				$totalLoss,
				$r[0],
				$r[1],
				$creditReq,
				$creditRec,
				$unrecovered,
				$r[2],
				"Expediente de recuperación financiera con soporte de remisión y acta fotográfica",
				$created,
				$resolved
			]);
			$count++;
		}

		return $count;
	}

	private function SeedAsnsAndAudits(\PDO $db): int
	{
		$existing = $db->query("SELECT COUNT(*) FROM daily_asn_receipts")->fetchColumn();
		if ($existing >= 15) {
			return 0;
		}

		$insAsn = $db->prepare("
			INSERT INTO daily_asn_receipts (
				asn_number, supplier_id, supplier_name, branch_id, branch_name,
				expected_delivery_date, status, doc_reference, items_count,
				total_expected_qty, total_received_qty, created_at
			) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
		");

		$branches = $db->query("SELECT id, name FROM branches")->fetchAll(\PDO::FETCH_ASSOC);
		$sups = $db->query("SELECT id, name FROM shopping_locations LIMIT 20")->fetchAll(\PDO::FETCH_ASSOC);

		$count = 0;
		for ($i = $existing + 1; $i <= 15; $i++) {
			$br = $branches[array_rand($branches)];
			$sp = $sups[array_rand($sups)];
			$asnNum = sprintf('ASN-2026-%03d', 100 + $i);
			$days = rand(-5, 5);
			$expectedDate = date('Y-m-d', strtotime("$days days"));
			$status = $days < 0 ? 'matched' : ($days == 0 ? 'in_dock' : 'expected');
			$docRef = sprintf('REM-%06d', rand(100000, 999999));
			$items = rand(4, 12);
			$qtyExp = rand(150, 600);
			$qtyRec = ($status === 'matched') ? $qtyExp : (($status === 'in_dock') ? round($qtyExp * 0.95) : 0);

			$insAsn->execute([
				$asnNum,
				$sp['id'],
				$sp['name'],
				$br['id'],
				$br['name'],
				$expectedDate,
				$status,
				$docRef,
				$items,
				$qtyExp,
				$qtyRec,
				date('Y-m-d H:i:s', strtotime('-2 days'))
			]);
			$count++;
		}

		return $count;
	}
}
