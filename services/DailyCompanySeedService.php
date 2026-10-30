<?php

namespace Grocy\Services;

/**
 * DAILY — Empresa de ejemplo coherente para las 10 sucursales ficticias.
 *
 * Todo pasa por los servicios de la aplicación (compras → recepción → stock/kardex, POS → venta con costo FEFO,
 * pedidos → reserva → preparación → despacho como venta, mermas → consumo de stock), de modo que inventario,
 * movimientos, ventas, mermas y KPI salen de los mismos registros. Las operaciones se ejecutan día a día sobre las
 * últimas N jornadas; al terminar cada una se fechan sus filas con la hora simulada (las recepciones se validan con la
 * fecha real y luego se reubican en su día). El libro de movimientos se completa al final con las fechas definitivas.
 *
 * Idempotente: se registra en daily_seed_runs y no se repite.
 */
class DailyCompanySeedService extends BaseService
{
	const CODE = 'daily_company_v1';

	// Escala media: tickets por día de una sucursal de tamaño 1 y líneas por canasta
	const TICKETS_PER_DAY = 40;
	const BASKET_LINES = [1 => 14, 2 => 18, 3 => 18, 4 => 16, 5 => 12, 6 => 10, 7 => 7, 8 => 5];
	const USER_ID = 1;

	// Sucursal => tamaño relativo (tickets por día)
	const BRANCH_SIZE = [1 => 1.25, 2 => 1.1, 3 => 0.85, 4 => 1.15, 5 => 0.8, 6 => 1.3, 7 => 0.95, 8 => 1.05, 9 => 0.9, 10 => 0.7];

	// Producto => [peso de venta, costo unitario, proveedor (id existente, 5 = hortícola, o nombre de un proveedor nuevo)]
	const CATALOG = [
		30 => [100, 3.10, 1], 31 => [50, 3.00, 1], 32 => [40, 3.40, 1], 33 => [30, 2.80, 1],
		34 => [70, 2.40, 3], 35 => [30, 3.50, 3], 36 => [20, 4.20, 3],
		37 => [40, 1.10, 1], 38 => [30, 0.95, 1], 39 => [30, 1.30, 4],
		40 => [40, 2.80, 1], 41 => [40, 3.10, 1], 42 => [30, 1.85, 1], 43 => [30, 2.90, 1], 44 => [30, 2.90, 1],
		45 => [30, 2.20, 1], 46 => [20, 2.00, 1], 47 => [20, 1.50, 1], 48 => [20, 2.10, 1],
		49 => [20, 2.80, 4], 50 => [40, 3.20, 4], 51 => [20, 2.40, 4], 186 => [30, 0.42, 4],
		12 => [20, 1.90, 5], 13 => [20, 0.95, 5], 14 => [10, 0.80, 5], 15 => [30, 1.20, 5],
		172 => [20, 4.80, 2], 173 => [20, 5.50, 2], 174 => [10, 10.50, 2],
		176 => [40, 1.45, 'Panificadora del Puerto'], 177 => [35, 0.70, 'Panificadora del Puerto'], 178 => [20, 2.10, 'Panificadora del Puerto'],
		179 => [20, 1.35, 'Distribuidora de Abarrotes Costa Sur'], 180 => [20, 0.68, 'Distribuidora de Abarrotes Costa Sur'],
		181 => [12, 2.30, 'Distribuidora de Abarrotes Costa Sur'], 182 => [15, 1.55, 'Distribuidora de Abarrotes Costa Sur'],
		183 => [12, 1.30, 'Congelados del Litoral'], 184 => [10, 3.40, 'Congelados del Litoral'], 185 => [10, 2.75, 'Congelados del Litoral'],
		175 => [1, 560.00, 'Distribuidora Tecnológica Atlántico'], 187 => [3, 27.50, 'Distribuidora Tecnológica Atlántico'], 188 => [4, 8.40, 'Distribuidora Tecnológica Atlántico']
	];

	// Artículos nuevos de los demás departamentos: id => [SKU, nombre, departamento, grupo, unidad, precio, vida útil (días), almacenamiento, ubicación en Hallandale Beach, mínimo]
	const EXTRA_PRODUCTS = [
		176 => ['BAK-PAN-001', 'Pan blanco de molde', 'Panadería', '02 Productos de panadería', 'Paquete', 3.49, 7, 'ambient', 3, 8],
		177 => ['BAK-BAG-001', 'Baguette', 'Panadería', '02 Productos de panadería', 'Pieza', 1.99, 4, 'ambient', 3, 10],
		178 => ['BAK-CRO-001', 'Croissant de mantequilla x4', 'Panadería', '02 Productos de panadería', 'Paquete', 4.99, 5, 'ambient', 3, 6],
		179 => ['ABA-ARR-001', 'Arroz blanco 2 lb', 'Abarrotes', '03 Comida en lata', 'Paquete', 2.79, 365, 'ambient', 5, 10],
		180 => ['ABA-FRI-001', 'Frijoles negros en lata', 'Abarrotes', '03 Comida en lata', 'Lata', 1.49, 540, 'ambient', 5, 12],
		181 => ['ABA-ACE-001', 'Aceite vegetal 1 L', 'Abarrotes', '03 Comida en lata', 'Botella', 4.29, 365, 'ambient', 5, 8],
		182 => ['ABA-GAL-001', 'Galletas de chocolate', 'Abarrotes', '01 Dulces', 'Paquete', 3.29, 180, 'ambient', 4, 10],
		183 => ['CON-VEG-001', 'Vegetales mixtos congelados 1 lb', 'Congelados', '09 Congelados', 'Paquete', 2.99, 240, 'frozen', 6, 8],
		184 => ['CON-PIZ-001', 'Pizza congelada', 'Congelados', '09 Congelados', 'Pieza', 6.99, 180, 'frozen', 6, 6],
		185 => ['CON-HEL-001', 'Helado de vainilla 1/2 galón', 'Congelados', '09 Congelados', 'Pote', 5.99, 180, 'frozen', 6, 6],
		186 => ['BEV-AGU-001', 'Agua mineral 1,5 L', 'Bebidas', '07 Bebidas', 'Botella', 1.29, 365, 'ambient', 3, 12],
		187 => ['TECH-AUD-001', 'Audífonos inalámbricos', 'Electrónica y Telefonía', '08 Electrónica y Telefonía', 'Pieza', 49.99, 730, 'ambient', 3, 3],
		188 => ['TECH-CAR-001', 'Cargador USB-C 20 W', 'Electrónica y Telefonía', '08 Electrónica y Telefonía', 'Pieza', 19.99, 730, 'ambient', 3, 4]
	];

	// Temperatura de llegada por tipo de almacenamiento (°C): [mínima, máxima]
	const ARRIVAL_TEMP = ['chilled' => [2.5, 4.0], 'frozen' => [-20.0, -17.0], 'ambient' => [18.0, 24.0]];

	private array $suppliers = [];

	// Productos de larga vida útil usados en pedidos de clientes comerciales
	const ORDER_PRODUCTS = [40, 41, 42, 43, 44];

	private \PDO $db;
	private array $timeCols = [];
	private array $stats = [];

	public function Run(int $days = 58): array
	{
		$this->db = DatabaseService::GetInstance()->GetDbConnectionRaw();
		$this->db->exec('CREATE TABLE IF NOT EXISTS daily_seed_runs (code TEXT PRIMARY KEY, run_at DATETIME, summary TEXT)');
		if ($this->db->query("SELECT COUNT(*) FROM daily_seed_runs WHERE code = '" . self::CODE . "'")->fetchColumn() > 0)
		{
			return ['skipped' => true];
		}
		// Una carga interrumpida no se repite encima (duplicaría ventas y movimientos): restaurar el respaldo antes
		if ($this->db->query("SELECT COUNT(*) FROM daily_seed_runs WHERE code = '" . self::CODE . "_started'")->fetchColumn() > 0)
		{
			throw new \Exception('La carga de la empresa de ejemplo se interrumpió antes: restaure la base de datos respaldada antes de reintentar');
		}
		$this->db->prepare('INSERT INTO daily_seed_runs (code, run_at) VALUES (?, ?)')->execute([self::CODE . '_started', date('Y-m-d H:i:s')]);
		mt_srand(20261028);
		@set_time_limit(0);
		$this->db->exec('PRAGMA synchronous = OFF');

		$this->EnsureExtraProducts();
		$this->NormalizeCatalog();
		$this->VoidLegacySales($days);
		$fruverSupplier = $this->EnsureSupplier('Proveedor Hortícola Regional');
		$this->EnsureNamedSuppliers();
		$this->EnsureCatalogExtension();
		$locations = $this->EnsureBranchLocations();
		$customers = $this->EnsureCustomers();
		$this->EnsureEmployeesAndShifts($days);
		$this->stats += $this->LinkEmployeeAccounts();

		// El libro de movimientos se completa al final con las fechas simuladas
		$ledgerTrigger = $this->db->query("SELECT sql FROM sqlite_master WHERE type = 'trigger' AND name = 'dairy_movement_ledger_capture'")->fetchColumn();
		$firstLogId = intval($this->db->query('SELECT IFNULL(MAX(id), 0) FROM stock_log')->fetchColumn());
		if ($ledgerTrigger)
		{
			$this->db->exec('DROP TRIGGER dairy_movement_ledger_capture');
		}
		try
		{
			$plan = $this->BuildSalesPlan($days);
			for ($d = -$days + 1; $d <= 0; $d++)
			{
				$date = date('Y-m-d', strtotime("$d days"));
				foreach (array_keys(self::BRANCH_SIZE) as $b)
				{
					$this->WriteOffExpired($b, $date);
					$this->Receive($b, $d, $days, $date, $plan, $locations, $fruverSupplier);
					$this->Orders($b, $d, $date, $customers);
					foreach ($plan[$b][$d] ?? [] as $ticket)
					{
						$this->Sell($b, $date, $ticket);
					}
					$this->Breakage($b, $d, $date);
				}
				$this->Attendance($date, 0, $d === 0 ? intval(date('G')) : 24);
				AttendanceService::GetInstance()->SyncCoverageTasks(null, $date);
				$this->Transfers($d, $date, $locations);
				$this->CycleCounts($d, $date);
			}
			$this->ReturnOneSalePerBranch();
		}
		finally
		{
			$this->db->exec("INSERT OR IGNORE INTO dairy_movement_ledger (stock_log_id, product_id, amount, transaction_type, stock_id, location_id, price, spoiled, best_before_date, transaction_id, correlation_id, user_id, note, row_created_timestamp, recorded_timestamp)
				SELECT sl.id, sl.product_id, sl.amount, sl.transaction_type, sl.stock_id, IFNULL(sl.location_id, p.location_id), sl.price, sl.spoiled, sl.best_before_date, sl.transaction_id, sl.correlation_id, sl.user_id, sl.note, sl.row_created_timestamp, sl.row_created_timestamp
				FROM stock_log sl LEFT JOIN products p ON p.id = sl.product_id WHERE sl.id > $firstLogId ORDER BY sl.id");
			if ($ledgerTrigger)
			{
				$this->db->exec($ledgerTrigger);
			}
		}

		$this->Temperatures($days, $locations);
		$this->Budgets();
		try
		{
			$scan = AlertCenterService::GetInstance()->Scan(null, null);
			$this->stats['alerts_created'] = $scan['created'] ?? 0;
		}
		catch (\Throwable $e)
		{
			$this->stats['alerts_error'] = $e->getMessage();
		}
		$this->db->exec('PRAGMA synchronous = FULL');
		$this->db->prepare('INSERT INTO daily_seed_runs (code, run_at, summary) VALUES (?, ?, ?)')->execute([self::CODE, date('Y-m-d H:i:s'), json_encode($this->stats)]);
		return $this->stats;
	}

	/* ---------------------------------------------------------------- Catálogo único */

	private function NormalizeCatalog(): void
	{
		$db = $this->db;
		// SKU duplicados (DAIRY-YOG-002 y DAIRY-CRM-001 estaban en dos productos) y artículos activos sin SKU
		$sku = [38 => 'DAIRY-YOG-001', 45 => 'DAIRY-CRM-004', 12 => 'FRV-PIM-001', 13 => 'FRV-PEP-001', 14 => 'FRV-RAB-001', 15 => 'FRV-TOM-001',
			172 => 'MEA-POL-001', 173 => 'MEA-PEC-001', 174 => 'MEA-RES-001'];
		$up = $db->prepare('UPDATE products SET sku = ? WHERE id = ? AND NOT EXISTS (SELECT 1 FROM products o WHERE o.sku = ? AND o.id <> ?)');
		foreach ($sku as $id => $code)
		{
			$up->execute([$code, $id, $code, $id]);
		}
		// Artículos históricos inactivos: identificador propio para que aparezcan igual en reportes de historial
		$db->exec("UPDATE products SET sku = 'LEG-' || printf('%04d', id) WHERE active = 0 AND IFNULL(sku, '') = ''");

		// Precio de lista explícito (antes 0: el POS lo deducía del costo con un margen por defecto)
		$price = [12 => 2.99, 13 => 1.49, 14 => 1.29, 15 => 1.99, 172 => 7.49, 173 => 8.99, 174 => 16.99];
		$up = $db->prepare('UPDATE products SET sale_price = ? WHERE id = ? AND IFNULL(sale_price, 0) = 0');
		foreach ($price as $id => $p)
		{
			$up->execute([$p, $id]);
		}
		// Carnes: la unidad 1 no existe en quantity_units → Kilogramo
		if ($db->query('SELECT COUNT(*) FROM quantity_units WHERE id = 1')->fetchColumn() == 0)
		{
			$kg = intval($db->query("SELECT id FROM quantity_units WHERE name = 'Kilogramo'")->fetchColumn());
			if ($kg > 0)
			{
				$db->exec("UPDATE products SET qu_id_stock = $kg, qu_id_purchase = $kg, qu_id_consume = $kg, qu_id_price = $kg WHERE qu_id_stock = 1");
			}
		}

		// Departamento único por grupo de producto (antes todos «General»)
		$dept = [8 => 'Lácteos y huevos', 9 => 'Lácteos y huevos', 10 => 'Lácteos y huevos', 11 => 'Lácteos y huevos', 12 => 'Lácteos y huevos',
			6 => 'Lácteos y huevos', 13 => 'Bebidas refrigeradas', 7 => 'Bebidas refrigeradas', 5 => 'Frutas y verduras', 4 => 'Carnes',
			2 => 'Panadería', 14 => 'Electrónica y Telefonía', 1 => 'Abarrotes', 3 => 'Abarrotes'];
		$up = $db->prepare('UPDATE products SET department = ? WHERE product_group_id = ?');
		foreach ($dept as $g => $name)
		{
			$up->execute([$name, $g]);
		}

		// Duplicados históricos inactivos: se conservan (tienen ventas), con referencia al artículo vigente
		$legacy = [5 => 34, 9 => 37, 10 => 40, 23 => 30];
		$up = $db->prepare("UPDATE products SET description = ? WHERE id = ? AND active = 0 AND IFNULL(description, '') = ''");
		foreach ($legacy as $old => $new)
		{
			$n = $db->query("SELECT name || ' (' || sku || ')' FROM products WHERE id = $new")->fetchColumn();
			$up->execute(["Artículo histórico inactivo, conservado por su historial de ventas. Artículo vigente equivalente: $n.", $old]);
		}

		// Proveedor habitual de cada producto
		$up = $db->prepare('UPDATE products SET shopping_location_id = ? WHERE id = ?');
		foreach (self::CATALOG as $pid => [, , $sup])
		{
			if (is_int($sup) && $sup !== 5)
			{
				$up->execute([$sup, $pid]);
			}
		}

		// Cabeceras de venta con subtotal vacío cuyo total sí coincide con sus líneas
		$db->exec('UPDATE sales SET subtotal = ROUND(total_amount + discount_amount - tax_amount, 2)
			WHERE subtotal = 0 AND total_amount > 0 AND ABS((SELECT IFNULL(SUM(subtotal), 0) FROM sale_items WHERE sale_id = sales.id) - total_amount) < 0.05');

		// Textos visibles heredados con la palabra «demo» (los identificadores internos no cambian)
		$db->exec("UPDATE products SET description = 'Producto perecedero' WHERE description = 'Producto perecedero demo'");
		$db->exec("UPDATE sales SET notes = REPLACE(notes, 'Venta mostrador demo', 'Venta mostrador') WHERE notes LIKE 'Venta mostrador demo%'");
		$db->exec("UPDATE pos_cash_shifts SET notes = REPLACE(notes, 'turno demo', 'turno') WHERE notes LIKE '%turno demo%'");
		$db->exec("UPDATE companies SET name = 'Empresa de ejemplo DAILY' WHERE name LIKE '[DEMO]%'");

		// Clase de almacenamiento: un solo nombre para refrigerado
		$db->exec("UPDATE locations SET storage_class = 'chilled' WHERE storage_class = 'refrigerated'");
		$db->exec("UPDATE locations SET name = REPLACE(name, 'Norte', 'Miami Beach'), description = REPLACE(description, 'sucursal Norte', 'sucursal Miami Beach') WHERE branch_id = 2 AND (name LIKE '%Norte%' OR description LIKE '%Norte%')");
		$db->exec("UPDATE locations SET description = REPLACE(description, 'Norte', 'Miami Beach') WHERE branch_id = 2 AND description LIKE '%Norte%'");
	}

	/**
	 * Ventas cargadas antes sin pasar por el POS (sin descargo de inventario) dentro del período que ahora se registra con
	 * la operación completa: duplicarían las ventas sin mover stock. Se conservan (cabecera y líneas) con estado «void»
	 * (Anulada), que ningún indicador suma ni cuenta como devolución.
	 */
	private function VoidLegacySales(int $days): void
	{
		$from = date('Y-m-d', strtotime('-' . ($days - 1) . ' days'));
		$n = $this->db->exec("UPDATE sales SET status = 'void', notes = TRIM(IFNULL(notes, '') || ' · Anulada: carga previa sin descargo de inventario, reemplazada por la operación registrada')
			WHERE status = 'completed' AND date(created_timestamp) >= '$from'
			AND NOT EXISTS (SELECT 1 FROM sale_items si JOIN stock_log l ON l.transaction_id = si.transaction_id WHERE si.sale_id = sales.id)");
		$this->stats['legacy_sales_voided'] = $n;
	}

	private function EnsureSupplier(string $name): int
	{
		$id = $this->db->prepare('SELECT id FROM shopping_locations WHERE name = ?');
		$id->execute([$name]);
		$found = intval($id->fetchColumn());
		if ($found === 0)
		{
			$this->db->prepare('INSERT INTO shopping_locations (name, description, active) VALUES (?, ?, 1)')->execute([$name, 'Proveedor ficticio de frutas y verduras']);
			$found = intval($this->db->lastInsertId());
		}
		$up = $this->db->prepare('UPDATE products SET shopping_location_id = ? WHERE id = ?');
		foreach (self::CATALOG as $pid => [, , $sup])
		{
			if ($sup === 5)
			{
				$up->execute([$found, $pid]);
			}
		}
		return $found;
	}

	/** Artículos de panadería, abarrotes, congelados, bebidas y electrónica (mismo id, SKU, nombre y unidad en todo DAILY) */
	private function EnsureExtraProducts(): void
	{
		$db = $this->db;
		$group = function (string $name) use ($db): int {
			$q = $db->prepare('SELECT id FROM product_groups WHERE name = ?');
			$q->execute([$name]);
			$id = intval($q->fetchColumn());
			if ($id === 0)
			{
				$db->prepare('INSERT INTO product_groups (name) VALUES (?)')->execute([$name]);
				$id = intval($db->lastInsertId());
			}
			return $id;
		};
		$ins = $db->prepare('INSERT INTO products (id, name, description, product_group_id, active, location_id, qu_id_purchase, qu_id_stock, qu_id_consume, qu_id_price, min_stock_amount,
			default_best_before_days, sku, sale_price, storage_class, department, should_not_be_frozen) VALUES (?, ?, ?, ?, 1, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)');
		foreach (self::EXTRA_PRODUCTS as $id => [$sku, $name, $dept, $groupName, $unit, $price, $life, $storage, $loc, $min])
		{
			$taken = $db->prepare('SELECT COUNT(*) FROM products WHERE id = ? OR sku = ?');
			$taken->execute([$id, $sku]);
			if (intval($taken->fetchColumn()) > 0)
			{
				continue;
			}
			$qu = $db->prepare('SELECT id FROM quantity_units WHERE name = ?');
			$qu->execute([$unit]);
			$quId = intval($qu->fetchColumn()) ?: 2;
			$ins->execute([$id, $name, 'Artículo de la empresa de ejemplo', $group($groupName), $loc, $quId, $quId, $quId, $quId, $min, $life, $sku, $price, $storage, $dept]);
		}
		// El smartphone existente entra al surtido con precio y vida útil ya registrados
		$db->exec("UPDATE products SET storage_class = 'ambient' WHERE id = 175 AND IFNULL(storage_class, '') = ''");
	}

	/** Proveedores nuevos (nombres genéricos) de los departamentos agregados */
	private function EnsureNamedSuppliers(): void
	{
		$up = $this->db->prepare('UPDATE products SET shopping_location_id = ? WHERE id = ?');
		foreach (self::CATALOG as $pid => [, , $sup])
		{
			if (!is_string($sup))
			{
				continue;
			}
			if (!isset($this->suppliers[$sup]))
			{
				$q = $this->db->prepare('SELECT id FROM shopping_locations WHERE name = ?');
				$q->execute([$sup]);
				$id = intval($q->fetchColumn());
				if ($id === 0)
				{
					$this->db->prepare('INSERT INTO shopping_locations (name, description, active) VALUES (?, ?, 1)')->execute([$sup, 'Proveedor ficticio de la empresa de ejemplo']);
					$id = intval($this->db->lastInsertId());
				}
				$this->suppliers[$sup] = $id;
			}
			$up->execute([$this->suppliers[$sup], $pid]);
		}
	}

	private function SupplierId($sup, int $fruverSupplier): int
	{
		return is_string($sup) ? $this->suppliers[$sup] : ($sup === 5 ? $fruverSupplier : $sup);
	}

	/** Sucursal => tipo de almacenamiento => ubicación de recepción. Crea cámara, góndola, bodega seca y congelador donde falten. */
	private function EnsureBranchLocations(): array
	{
		$out = [];
		$ins = $this->db->prepare('INSERT INTO locations (name, description, branch_id, storage_class, is_freezer, active) VALUES (?, ?, ?, ?, ?, 1)');
		foreach ($this->db->query('SELECT id, name FROM branches ORDER BY id')->fetchAll(\PDO::FETCH_ASSOC) as $br)
		{
			$b = intval($br['id']);
			if ($b === 1)
			{
				$out[1] = ['chilled' => 7, 'ambient' => 3, 'frozen' => 6]; // Cámara Fría de Lácteos, Despensa, Congelador
				continue;
			}
			$has = $this->db->query("SELECT id FROM locations WHERE branch_id = $b AND IFNULL(is_transit, 0) = 0 ORDER BY id")->fetchAll(\PDO::FETCH_COLUMN);
			if (count($has) === 0)
			{
				$ins->execute(['Cámara Fría ' . $br['name'], 'Cámara principal refrigerada sucursal ' . $br['name'], $b, 'chilled', 0]);
				$has[] = intval($this->db->lastInsertId());
				$ins->execute(['Góndola Refrigerada ' . $br['name'], 'Exhibición lácteos piso de venta ' . $br['name'], $b, 'chilled', 0]);
			}
			$out[$b]['chilled'] = intval($has[0]);
			foreach (['ambient' => ['Bodega Seca ', 'Abarrotes, panadería, bebidas y electrónica · sucursal ', 0], 'frozen' => ['Congelador ', 'Cámara de congelados · sucursal ', 1]] as $class => [$name, $desc, $freezer])
			{
				$id = intval($this->db->query("SELECT id FROM locations WHERE branch_id = $b AND storage_class = '$class' AND IFNULL(is_transit, 0) = 0 ORDER BY id LIMIT 1")->fetchColumn());
				if ($id === 0)
				{
					$ins->execute([$name . $br['name'], $desc . $br['name'], $b, $class, $freezer]);
					$id = intval($this->db->lastInsertId());
				}
				$out[$b][$class] = $id;
			}
		}
		return $out;
	}

	private function StorageOf(int $pid): string
	{
		$s = (string) $this->db->query("SELECT IFNULL(storage_class, '') FROM products WHERE id = $pid")->fetchColumn();
		return isset(self::ARRIVAL_TEMP[$s]) ? $s : 'chilled';
	}

	/** Sucursal => [clientes comerciales]. Nombres genéricos, sin datos personales. */
	private function EnsureCustomers(): array
	{
		$out = [];
		$ins = $this->db->prepare("INSERT INTO customers (name, customer_type, branch_id, active, notes) VALUES (?, ?, ?, 1, 'Cliente ficticio de la empresa de ejemplo')");
		foreach ($this->db->query('SELECT id, code FROM branches ORDER BY id')->fetchAll(\PDO::FETCH_ASSOC) as $br)
		{
			$b = intval($br['id']);
			$n = sprintf('%02d', $b);
			foreach ([["Cafetería cliente $n-A", 'Comercial'], ["Comedor institucional $n-B", 'Institucional']] as [$name, $type])
			{
				$q = $this->db->prepare('SELECT id FROM customers WHERE name = ?');
				$q->execute([$name]);
				$id = intval($q->fetchColumn());
				if ($id === 0)
				{
					$ins->execute([$name, $type, $b]);
					$id = intval($this->db->lastInsertId());
				}
				$out[$b][] = $id;
			}
		}
		return $out;
	}

	private function EnsureEmployeesAndShifts(int $days): void
	{
		$roles = [['Responsable de tienda', 'Gerencia', 26.0, 40], ['Cajero/a', 'Cajas y Atención al Cliente', 16.5, 40], ['Cajero/a', 'Cajas y Atención al Cliente', 16.5, 30],
			['Auxiliar de lácteos', 'Dairy / Lácteos', 17.0, 40], ['Auxiliar de lácteos', 'Dairy / Lácteos', 17.0, 30], ['Recepción y bodega', 'Bodega y Despachos', 17.5, 40],
			['Auxiliar de frutas y verduras', 'Frutas y Verduras', 16.5, 30], ['Auxiliar de carnes', 'Carnes y Pollo', 18.0, 30]];
		$insE = $this->db->prepare('INSERT INTO employees (name, branch_id, position, department, weekly_hours, hourly_cost, active, payroll_code, min_weekly_hours) VALUES (?, ?, ?, ?, ?, ?, 1, ?, 24)');
		$insS = $this->db->prepare("INSERT INTO employee_shifts (employee_id, branch_id, shift_date, start_time, end_time, break_minutes, department, role, status, notes, created_by_user_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'published', 'Turno programado', 1)");
		$count = 0;
		foreach (array_keys(self::BRANCH_SIZE) as $b)
		{
			if ($this->db->query("SELECT COUNT(*) FROM employees WHERE branch_id = $b AND active = 1")->fetchColumn() > 0)
			{
				continue;
			}
			foreach ($roles as $i => [$position, $dept, $cost, $hours])
			{
				$code = sprintf('S%02d-%03d', $b, $i + 1);
				$insE->execute([sprintf('Colaborador %02d-%02d', $b, $i + 1), $b, $position, $dept, $hours, $cost, $code]);
				$eid = intval($this->db->lastInsertId());
				$count++;
				// Turnos: días trabajados según la jornada semanal, de los últimos días a dos semanas adelante
				$perWeek = $hours >= 40 ? 5 : 4;
				$early = $i % 2 === 0;
				for ($d = -$days + 1; $d <= 14; $d++)
				{
					$ts = strtotime("$d days");
					$dow = intval(date('N', $ts));
					if ((($dow + $i) % 7) >= $perWeek)
					{
						continue;
					}
					$len = $hours >= 40 ? 8 : 7;
					$start = $early ? 7 : 13;
					$insS->execute([$eid, $b, date('Y-m-d', $ts), sprintf('%02d:00', $start), sprintf('%02d:30', $start + $len), 30, $dept, $position]);
				}
			}
		}
		$this->stats['employees_created'] = $count;
	}

	/* ---------------------------------------------------------------- Plan de ventas */

	/** Sucursal => día => tickets [hora, minuto, pago, [producto => cantidad]] */
	/**
	 * Continúa la operación de la empresa de ejemplo hasta la última hora completa (solo modo demo).
	 * Determinista: el plan de cada día sale de una semilla fija por fecha y sucursal, así que el resultado
	 * no depende de cuándo ni cuántas veces se ejecute. Reutiliza los mismos servicios que la carga original
	 * (venta FEFO, recepción de OC, retiro de vencidos, roturas), por lo que cada movimiento llega a Ventas,
	 * Inventario, Mermas, HOY y el Panel con un único registro.
	 */
	public function CatchUp(int $maxSeconds = 60): array
	{
		$this->db = DatabaseService::GetInstance()->GetDbConnectionRaw();
		$base = $this->db->query("SELECT run_at FROM daily_seed_runs WHERE code = '" . self::CODE . "'")->fetchColumn();
		if (!$base)
		{
			return ['skipped' => 'sin empresa de ejemplo'];
		}
		$until = $this->db->query("SELECT run_at FROM daily_seed_runs WHERE code = '" . self::CODE . "_until'")->fetchColumn();
		if (!$until)
		{
			// La carga original cubrió hasta la hora en que se ejecutó (las horas posteriores de ese día quedaron sin vender)
			$started = $this->db->query("SELECT run_at FROM daily_seed_runs WHERE code = '" . self::CODE . "_started'")->fetchColumn() ?: $base;
			$until = substr($started, 0, 13) . ':00:00';
		}
		$target = date('Y-m-d H:00:00');
		if ($until >= $target)
		{
			return ['up_to_date' => $until];
		}
		// Una sola ejecución a la vez (bloqueo con vencimiento por si un proceso se cortó)
		$this->db->exec("DELETE FROM daily_seed_runs WHERE code = '" . self::CODE . "_lock' AND run_at < datetime('now', 'localtime', '-15 minutes')");
		if ($this->db->exec("INSERT OR IGNORE INTO daily_seed_runs (code, run_at) VALUES ('" . self::CODE . "_lock', datetime('now', 'localtime'))") !== 1)
		{
			return ['busy' => true];
		}
		@set_time_limit(0);
		$t0 = microtime(true);
		$seedStart = date('Y-m-d', strtotime(substr($base, 0, 10) . ' -57 days'));
		$fruverSupplier = $this->EnsureSupplier('Proveedor Hortícola Regional');
		$this->EnsureNamedSuppliers();
		$locations = $this->EnsureBranchLocations();
		$ins = $this->db->prepare('INSERT INTO cold_chain_temperatures (location_id, branch_id, temperature, humidity, recorded_timestamp, recorded_by, status, action_taken) VALUES (?, ?, ?, ?, ?, ?, ?, NULL)');

		$ledgerTrigger = $this->db->query("SELECT sql FROM sqlite_master WHERE type = 'trigger' AND name = 'dairy_movement_ledger_capture'")->fetchColumn();
		$firstLogId = intval($this->db->query('SELECT IFNULL(MAX(id), 0) FROM stock_log')->fetchColumn());
		if ($ledgerTrigger)
		{
			$this->db->exec('DROP TRIGGER dairy_movement_ledger_capture');
		}
		$reached = $until;
		$newDay = false;
		try
		{
			$cursor = $until;
			while ($cursor < $target && microtime(true) - $t0 < $maxSeconds)
			{
				$date = substr($cursor, 0, 10);
				$hFrom = intval(substr($cursor, 11, 2));
				// Avanza por bloques de hasta 4 horas para respetar el tiempo máximo
				$hTo = $date < substr($target, 0, 10) ? min(24, $hFrom + 4) : min(intval(substr($target, 11, 2)), $hFrom + 4);
				$offset = intval(round((strtotime($date) - strtotime($seedStart)) / 86400));
				if ($date === date('Y-m-d') && $this->db->query("SELECT COUNT(*) FROM dairy_budgets WHERE period = '" . date('Y-m') . "'")->fetchColumn() == 0)
				{
					$this->Budgets();
				}
				foreach (array_keys(self::BRANCH_SIZE) as $b)
				{
					// Plan del día y de los 7 siguientes (para las compras): mismo resultado en cada ejecución
					$plan = [];
					for ($x = 0; $x <= 7; $x++)
					{
						$plan[$b][$x] = $this->DayPlan($b, date('Y-m-d', strtotime("$date +$x days")));
					}
					mt_srand(crc32("ops|$date|$b|$hFrom"));
					if ($hFrom < 8 && $hTo >= 8)
					{
						$newDay = true;
						$this->WriteOffExpired($b, $date);
						$this->Receive($b, 0, $offset + 1, $date, $plan, $locations, $fruverSupplier, 7);
						$ins->execute([$locations[$b]['frozen'], $b, round(-19.5 + mt_rand(0, 25) / 10, 1), null, "$date 07:30:00", 'Registro de turno', 'normal']);
						$ins->execute([$locations[$b]['chilled'], $b, round(2.4 + mt_rand(0, 19) / 10, 1), mt_rand(72, 86), "$date 07:30:00", 'Registro de turno', 'normal']);
					}
					if ($hFrom <= 15 && $hTo >= 16)
					{
						$ins->execute([$locations[$b]['chilled'], $b, round(2.4 + mt_rand(0, 19) / 10, 1), mt_rand(72, 86), "$date 15:30:00", 'Registro de turno', 'normal']);
					}
					foreach ($plan[$b][0] as $ticket)
					{
						if ($ticket[0] >= $hFrom && $ticket[0] < $hTo)
						{
							$this->Sell($b, $date, $ticket);
						}
					}
					// Las roturas se registran al cerrar el día (el día de la carga original ya tiene las suyas)
					if ($hTo === 24 && $date > substr($base, 0, 10))
					{
						$this->Breakage($b, $offset, $date);
					}
				}
				// Fichajes de los turnos del tramo y tareas para cubrir los turnos sin presentarse
				$this->Attendance($date, $hFrom, $hTo);
				AttendanceService::GetInstance()->SyncCoverageTasks(null, $date);
				$cursor = $hTo === 24 ? date('Y-m-d 00:00:00', strtotime("$date +1 day")) : sprintf('%s %02d:00:00', $date, $hTo);
				$reached = $cursor;
				$this->db->prepare("INSERT OR REPLACE INTO daily_seed_runs (code, run_at, summary) VALUES (?, ?, ?)")->execute([self::CODE . '_until', $reached, json_encode($this->stats)]);
			}
		}
		finally
		{
			$this->db->exec("INSERT OR IGNORE INTO dairy_movement_ledger (stock_log_id, product_id, amount, transaction_type, stock_id, location_id, price, spoiled, best_before_date, transaction_id, correlation_id, user_id, note, row_created_timestamp, recorded_timestamp)
				SELECT sl.id, sl.product_id, sl.amount, sl.transaction_type, sl.stock_id, IFNULL(sl.location_id, p.location_id), sl.price, sl.spoiled, sl.best_before_date, sl.transaction_id, sl.correlation_id, sl.user_id, sl.note, sl.row_created_timestamp, sl.row_created_timestamp
				FROM stock_log sl LEFT JOIN products p ON p.id = sl.product_id WHERE sl.id > $firstLogId ORDER BY sl.id");
			if ($ledgerTrigger)
			{
				$this->db->exec($ledgerTrigger);
			}
			$this->db->exec("DELETE FROM daily_seed_runs WHERE code = '" . self::CODE . "_lock'");
		}
		if ($newDay)
		{
			try
			{
				AlertCenterService::GetInstance()->Scan(null, null);
			}
			catch (\Throwable $e)
			{
				$this->stats['alerts_error'] = $e->getMessage();
			}
		}
		return ['from' => $until, 'until' => $reached, 'seconds' => round(microtime(true) - $t0, 1)] + $this->stats;
	}

	/**
	 * Fichajes de los turnos que empiezan en [hFrom, hTo) del día, deterministas por turno:
	 * 3 % no se presenta (sin fichaje → alerta de turno sin marca y tarea de sustitución), 2 % ausencia justificada
	 * registrada (enfermedad, aprobada), 12 % llega tarde 5–25 min, el resto a tiempo. La salida se marca cuando el turno termina.
	 */
	private function Attendance(string $date, int $hFrom, int $hTo): void
	{
		$db = $this->db;
		$from = sprintf('%02d:00:00', $hFrom);
		$to = $hTo >= 24 ? '24:00:00' : sprintf('%02d:00:00', $hTo);
		$limit = $hTo >= 24 ? date('Y-m-d H:i:s', strtotime("$date +1 day")) : "$date $to";
		$shiftEnd = function (string $d, string $start, string $end): int {
			$e = strtotime("$d $end");
			return $end <= $start ? $e + 86400 : $e;
		};
		// Salidas de los turnos (de ayer o de hoy) que terminaron antes del cierre del tramo; un empleado solo puede tener un fichaje abierto
		foreach ($db->query("SELECT a.id, s.id AS shift_id, s.shift_date, s.start_time, s.end_time FROM employee_attendance a JOIN employee_shifts s ON s.id = a.shift_id
			WHERE s.shift_date BETWEEN date('$date', '-1 day') AND '$date' AND a.clock_out IS NULL")->fetchAll(\PDO::FETCH_ASSOC) as $a)
		{
			$end = $shiftEnd($a['shift_date'], $a['start_time'], $a['end_time']);
			if (date('Y-m-d H:i:s', $end) <= $limit)
			{
				mt_srand(crc32('out|' . $a['shift_id']));
				$db->prepare('UPDATE employee_attendance SET clock_out = ? WHERE id = ?')->execute([date('Y-m-d H:i:s', $end + mt_rand(-3, 12) * 60), $a['id']]);
			}
		}
		$shifts = $db->query("SELECT s.id, s.employee_id, s.start_time, s.end_time, e.user_id FROM employee_shifts s JOIN employees e ON e.id = s.employee_id
			WHERE s.shift_date = '$date' AND s.status <> 'cancelled' AND s.start_time >= '$from' AND s.start_time < '$to'
			AND NOT EXISTS (SELECT 1 FROM employee_attendance a WHERE a.shift_id = s.id) ORDER BY s.start_time")->fetchAll(\PDO::FETCH_ASSOC);
		$ins = $db->prepare("INSERT INTO employee_attendance (employee_id, shift_id, clock_in, clock_out, source, notes, recorded_by_user_id) VALUES (?, ?, ?, ?, 'biometric', ?, ?)");
		$abs = $db->prepare("INSERT INTO employee_absences (employee_id, absence_type, start_date, end_date, status, notes, requested_by_user_id, decided_by_user_id, decided_timestamp, row_created_timestamp) VALUES (?, 'sick', ?, ?, 'approved', ?, ?, ?, ?, ?)");
		$open = $db->prepare('SELECT COUNT(*) FROM employee_attendance WHERE employee_id = ? AND clock_out IS NULL');
		foreach ($shifts as $s)
		{
			mt_srand(crc32('att|' . $s['id']));
			$r = mt_rand(1, 100);
			if ($r <= 3)
			{
				$this->stats['no_shows'] = ($this->stats['no_shows'] ?? 0) + 1;
				continue;
			}
			if ($r <= 5)
			{
				$abs->execute([$s['employee_id'], $date, $date, 'Certificado médico presentado', $s['user_id'] ?: self::USER_ID, self::USER_ID, "$date 07:00:00", "$date 06:30:00"]);
				$this->stats['absences'] = ($this->stats['absences'] ?? 0) + 1;
				continue;
			}
			$delay = $r <= 17 ? mt_rand(5, 25) : mt_rand(-6, 3);
			$in = date('Y-m-d H:i:s', strtotime("$date {$s['start_time']}") + $delay * 60 + mt_rand(0, 59));
			$end = $shiftEnd($date, $s['start_time'], $s['end_time']);
			mt_srand(crc32('out|' . $s['id']));
			$out = date('Y-m-d H:i:s', $end) <= $limit ? date('Y-m-d H:i:s', $end + mt_rand(-3, 12) * 60) : null;
			if ($out === null)
			{
				$open->execute([$s['employee_id']]);
				if (intval($open->fetchColumn()) > 0)
				{
					continue; // turno doble con el anterior aún abierto: se ficha al cerrar el primero
				}
			}
			$ins->execute([$s['employee_id'], $s['id'], $in, $out, $delay > 4 ? "Llegada con $delay min de retraso" : null, $s['user_id'] ?: self::USER_ID]);
			$this->stats['punches'] = ($this->stats['punches'] ?? 0) + 1;
		}
	}

	/**
	 * Cuentas de usuario para los empleados de la empresa de ejemplo (solo modo demo), con permisos según el cargo
	 * y acceso a su sucursal. La contraseña es aleatoria y no se muestra: el ingreso es por clave API o restableciéndola.
	 */
	public function LinkEmployeeAccounts(): array
	{
		if (GROCY_MODE !== 'demo')
		{
			return ['skipped' => 'solo en modo demo'];
		}
		$this->db = DatabaseService::GetInstance()->GetDbConnectionRaw();
		$perm = $this->db->query('SELECT name, id FROM permission_hierarchy')->fetchAll(\PDO::FETCH_KEY_PAIR);
		$sets = [
			'lead' => ['WORKFORCE', 'WORKFORCE_SCHEDULE_MANAGE', 'WORKFORCE_ATTENDANCE_MANAGE', 'TASKS', 'TASKS_MARK_COMPLETED', 'STOCK', 'STOCK_PURCHASE', 'STOCK_INVENTORY', 'WASTE_REPORT', 'WASTE_APPROVE', 'INVENTORY_ADJUST_APPROVE', 'SALES'],
			'cashier' => ['SALES', 'STOCK', 'TASKS_MARK_COMPLETED'],
			'staff' => ['STOCK', 'STOCK_PURCHASE', 'STOCK_INVENTORY', 'WASTE_REPORT', 'TASKS_MARK_COMPLETED']
		];
		$users = UsersService::GetInstance();
		$addPerm = $this->db->prepare('INSERT OR IGNORE INTO user_permissions (user_id, permission_id) VALUES (?, ?)');
		$addBranch = $this->db->prepare('INSERT OR IGNORE INTO user_branches (user_id, branch_id) VALUES (?, ?)');
		$link = $this->db->prepare('UPDATE employees SET user_id = ? WHERE id = ? AND user_id IS NULL');
		$n = 0;
		foreach ($this->db->query('SELECT id, name, branch_id, position FROM employees WHERE user_id IS NULL AND active = 1 ORDER BY id')->fetchAll(\PDO::FETCH_ASSOC) as $e)
		{
			$username = sprintf('emp%04d', $e['id']);
			$existing = $this->db->query('SELECT id FROM users WHERE username = ' . $this->db->quote($username))->fetchColumn();
			if (!$existing)
			{
				$parts = preg_split('/\s+/', trim($e['name']), 2);
				$users->CreateUser($username, $parts[0], $parts[1] ?? '', bin2hex(random_bytes(16)));
				$existing = $this->db->query('SELECT id FROM users WHERE username = ' . $this->db->quote($username))->fetchColumn();
				// CreateUser concede los permisos por defecto de la instalación (ADMIN): el empleado solo recibe los de su cargo
				$this->db->exec('DELETE FROM user_permissions WHERE user_id = ' . intval($existing));
			}
			$uid = intval($existing);
			$pos = mb_strtolower($e['position']);
			$set = (str_contains($pos, 'responsable') || str_contains($pos, 'supervisor') || str_contains($pos, 'gerente')) ? 'lead' : (str_contains($pos, 'cajer') ? 'cashier' : 'staff');
			foreach ($sets[$set] as $p)
			{
				if (isset($perm[$p]))
				{
					$addPerm->execute([$uid, $perm[$p]]);
				}
			}
			$addBranch->execute([$uid, $e['branch_id']]);
			$link->execute([$uid, $e['id']]);
			$n++;
		}
		return ['linked' => $n];
	}

	/** Al abrir HOY o el Panel en modo demo: completa las horas que falten (no hace nada fuera de demo ni si ya está al día) */
	public static function DemoCatchUp(): void
	{
		if (GROCY_MODE !== 'demo')
		{
			return;
		}
		try
		{
			self::GetInstance()->CatchUp(20);
		}
		catch (\Throwable $e)
		{
			// La página se muestra igual con lo ya registrado
		}
	}

	/** Tickets de un día y sucursal (hora, minuto, medio de pago, artículos): mismas reglas que la carga original */
	private function DayPlan(int $b, string $date): array
	{
		static $cache = [];
		if (isset($cache["$b|$date"]))
		{
			return $cache["$b|$date"];
		}
		$hours = [8 => 6, 9 => 5, 10 => 6, 11 => 8, 12 => 10, 13 => 9, 14 => 6, 15 => 5, 16 => 6, 17 => 9, 18 => 10, 19 => 7, 20 => 3];
		$weekday = [1 => 0.9, 2 => 0.85, 3 => 0.9, 4 => 0.95, 5 => 1.1, 6 => 1.3, 7 => 1.15];
		$weights = array_map(fn($c) => $c[0], $this->Catalog());
		mt_srand(crc32("plan|$date|$b"));
		$n = max(5, intval(round(self::TICKETS_PER_DAY * self::BRANCH_SIZE[$b] * $weekday[intval(date('N', strtotime($date)))] + mt_rand(-3, 3))));
		$out = [];
		for ($t = 0; $t < $n; $t++)
		{
			$h = $this->Pick($hours);
			$lines = $this->Pick(self::BASKET_LINES);
			$items = [];
			for ($l = 0; $l < $lines; $l++)
			{
				$pid = $this->Pick($weights);
				$items[$pid] = ($items[$pid] ?? 0) + $this->Pick([1 => 60, 2 => 30, 3 => 10]);
			}
			$out[] = [$h, mt_rand(0, 59), $this->Pick(['card' => 50, 'cash' => 40, 'transfer' => 10]), $items];
		}
		usort($out, fn($x, $y) => ($x[0] * 60 + $x[1]) <=> ($y[0] * 60 + $y[1]));
		return $cache["$b|$date"] = $out;
	}

	/* ---------------------------------------------------------------- Surtido ampliado (escala media) */

	// Primer id del surtido ampliado (fijo: el mismo producto conserva id, SKU, EAN, nombre y precio en todo DAILY)
	const EXT_FIRST_ID = 1001;

	/**
	 * Plantillas por departamento: [código, departamento, grupo, almacenamiento, ubicación en Hallandale, vida útil (días), margen,
	 * proveedor (id o nombre), marcas, artículos [nombre, costo base, unidad], variantes [sufijo, factor de costo], cantidad]
	 */
	private function ExtTemplates(): array
	{
		return [
			['LAC', 'Lácteos y huevos', 'Lácteos y Leches', 'chilled', 8, 21, 0.30, 1, ['Del Valle', 'Pradera'],
				[['Yogur natural', 0.95, 'Vaso'], ['Yogur de fresa', 0.99, 'Vaso'], ['Yogur de vainilla', 0.99, 'Vaso'], ['Leche descremada', 2.60, 'Botella'], ['Leche sin lactosa', 3.20, 'Botella'],
				 ['Queso fresco', 2.90, 'Paquete'], ['Queso mozzarella', 3.40, 'Paquete'], ['Queso gouda', 3.80, 'Paquete'], ['Mantequilla con sal', 2.70, 'Paquete'], ['Crema agria', 1.60, 'Pote'],
				 ['Queso crema', 1.90, 'Paquete'], ['Bebida de almendra', 2.50, 'Botella'], ['Requesón', 2.20, 'Pote'], ['Leche chocolatada', 1.40, 'Botella'], ['Natilla', 1.10, 'Vaso']],
				[['', 1.0], [' familiar', 1.8], [' light', 1.05]], 45],
			['BEB', 'Bebidas refrigeradas', 'Bebidas Refrigeradas', 'ambient', 3, 300, 0.32, 4, ['Brisa', 'Monte Claro'],
				[['Jugo de naranja', 1.90, 'Botella'], ['Jugo de manzana', 1.80, 'Botella'], ['Refresco de cola', 0.85, 'Botella'], ['Refresco de limón', 0.80, 'Botella'], ['Agua con gas', 0.70, 'Botella'],
				 ['Té helado', 1.10, 'Botella'], ['Bebida isotónica', 1.05, 'Botella'], ['Café frío', 1.60, 'Lata'], ['Agua de coco', 1.70, 'Botella'], ['Limonada', 1.30, 'Botella'],
				 ['Bebida energética', 1.50, 'Lata'], ['Néctar de mango', 1.40, 'Botella']],
				[[' 500 ml', 1.0], [' 1,5 L', 2.1], [' pack x6', 5.2]], 35],
			['FRU', 'Frutas y verduras', '05 Verduras/Frutas', 'chilled', 2, 7, 0.38, 5, ['Del Campo'],
				[['Manzana roja', 0.55, 'Pieza'], ['Banano', 0.25, 'Pieza'], ['Naranja', 0.45, 'Pieza'], ['Limón', 0.20, 'Pieza'], ['Aguacate', 0.95, 'Pieza'], ['Cebolla blanca', 0.35, 'Pieza'],
				 ['Papa', 0.30, 'Pieza'], ['Zanahoria', 0.20, 'Pieza'], ['Lechuga romana', 0.90, 'Pieza'], ['Pepino', 0.40, 'Pieza'], ['Brócoli', 1.10, 'Pieza'], ['Fresas 1 lb', 2.20, 'Paquete'],
				 ['Uvas verdes 1 lb', 2.40, 'Paquete'], ['Piña', 1.80, 'Pieza'], ['Mango', 0.85, 'Pieza'], ['Espinaca 10 oz', 1.70, 'Paquete'], ['Ajo', 0.35, 'Pieza'], ['Cilantro', 0.45, 'Paquete']],
				[['', 1.0], [' orgánico', 1.45]], 35],
			['CAR', 'Carnes', '04 Productos de carnicería', 'chilled', 2, 5, 0.27, 2, ['Frigorífica Nacional'],
				[['Carne molida 1 lb', 3.90, 'Paquete'], ['Pechuga de pollo 1 lb', 3.10, 'Paquete'], ['Muslos de pollo 1 lb', 2.20, 'Paquete'], ['Chuleta de cerdo 1 lb', 3.40, 'Paquete'],
				 ['Bistec de res 1 lb', 6.80, 'Paquete'], ['Salchicha de cerdo', 3.00, 'Paquete'], ['Tocino', 3.60, 'Paquete'], ['Jamón de pavo', 2.90, 'Paquete'], ['Costilla de cerdo 1 lb', 3.70, 'Paquete'],
				 ['Filete de tilapia 1 lb', 4.20, 'Paquete'], ['Camarón 1 lb', 6.90, 'Paquete'], ['Lomo de res 1 lb', 8.50, 'Paquete'], ['Alitas de pollo 1 lb', 2.80, 'Paquete']],
				[['', 1.0], [' premium', 1.35]], 25],
			['PAN', 'Panadería', '02 Productos de panadería', 'ambient', 3, 6, 0.45, 'Panificadora del Puerto', ['Del Puerto'],
				[['Pan integral', 1.60, 'Paquete'], ['Pan de hot dog x8', 1.30, 'Paquete'], ['Pan de hamburguesa x8', 1.40, 'Paquete'], ['Tortillas de harina x10', 1.20, 'Paquete'],
				 ['Muffin de arándano', 0.70, 'Pieza'], ['Donas glaseadas x6', 2.10, 'Paquete'], ['Pan de ajo', 1.50, 'Paquete'], ['Galletas de avena', 1.80, 'Paquete'], ['Pan dulce', 0.45, 'Pieza'], ['Bagels x6', 2.00, 'Paquete']],
				[['', 1.0], [' artesanal', 1.4]], 20],
			['ABA', 'Abarrotes', '03 Comida en lata', 'ambient', 5, 360, 0.30, 'Distribuidora de Abarrotes Costa Sur', ['Costa Sur', 'La Despensa'],
				[['Pasta espagueti 1 lb', 0.90, 'Paquete'], ['Pasta penne 1 lb', 0.90, 'Paquete'], ['Salsa de tomate', 1.10, 'Lata'], ['Atún en agua', 1.05, 'Lata'], ['Maíz dulce', 0.75, 'Lata'],
				 ['Lentejas 1 lb', 1.00, 'Paquete'], ['Azúcar 4 lb', 2.40, 'Paquete'], ['Harina de trigo 5 lb', 2.30, 'Paquete'], ['Sal 26 oz', 0.60, 'Paquete'], ['Café molido 12 oz', 4.50, 'Paquete'],
				 ['Cereal de maíz', 2.60, 'Paquete'], ['Avena 18 oz', 2.10, 'Paquete'], ['Mantequilla de maní', 2.40, 'Pote'], ['Mermelada de fresa', 2.00, 'Pote'], ['Mayonesa', 2.30, 'Pote'],
				 ['Kétchup', 1.70, 'Botella'], ['Mostaza', 1.10, 'Botella'], ['Vinagre blanco', 0.95, 'Botella'], ['Papas fritas', 1.60, 'Paquete'], ['Palomitas para microondas', 2.20, 'Paquete'],
				 ['Detergente líquido', 5.90, 'Botella'], ['Papel higiénico x12', 6.40, 'Paquete'], ['Toallas de papel x6', 5.20, 'Paquete'], ['Jabón de manos', 1.80, 'Botella'], ['Lavaplatos líquido', 1.90, 'Botella'],
				 ['Bolsas de basura x30', 3.80, 'Paquete'], ['Pasta dental', 1.70, 'Pieza'], ['Champú', 3.10, 'Botella'], ['Desodorante', 2.60, 'Pieza'], ['Pañales talla 3', 9.80, 'Paquete']],
				[['', 1.0], [' económico', 0.82]], 60],
			['CON', 'Congelados', '09 Congelados', 'frozen', 6, 240, 0.33, 'Congelados del Litoral', ['Litoral'],
				[['Papas a la francesa 2 lb', 2.10, 'Paquete'], ['Nuggets de pollo', 3.90, 'Paquete'], ['Hamburguesas congeladas x4', 4.40, 'Paquete'], ['Burritos congelados', 1.20, 'Pieza'],
				 ['Lasaña congelada', 4.80, 'Pieza'], ['Waffles congelados', 1.90, 'Paquete'], ['Fresas congeladas', 2.80, 'Paquete'], ['Maíz congelado', 1.10, 'Paquete'], ['Camarón congelado', 6.20, 'Paquete'],
				 ['Paletas de fruta x6', 2.70, 'Paquete'], ['Helado de chocolate', 3.60, 'Pote'], ['Empanadas congeladas', 3.20, 'Paquete'], ['Pescado empanizado', 3.70, 'Paquete']],
				[['', 1.0], [' familiar', 1.7]], 25],
			['TEC', 'Electrónica y Telefonía', '08 Electrónica y Telefonía', 'ambient', 3, 730, 0.38, 'Distribuidora Tecnológica Atlántico', ['Atlántico'],
				[['Cable USB-C 1 m', 3.20, 'Pieza'], ['Cable Lightning 1 m', 3.60, 'Pieza'], ['Batería externa 10000 mAh', 12.50, 'Pieza'], ['Protector de pantalla', 2.40, 'Pieza'],
				 ['Funda para celular', 4.10, 'Pieza'], ['Pilas AA x4', 2.80, 'Paquete'], ['Pilas AAA x4', 2.80, 'Paquete'], ['Bombillo LED', 2.10, 'Pieza'], ['Memoria USB 64 GB', 5.60, 'Pieza'],
				 ['Parlante bluetooth', 16.00, 'Pieza'], ['Audífonos con cable', 5.20, 'Pieza'], ['Cargador de auto', 4.90, 'Pieza']],
				[['', 1.0]], 12],
		];
	}

	/** EAN-13 de uso interno (prefijo 20: códigos de tienda, no colisionan con productos reales) con dígito verificador */
	public static function Ean13(int $seq): string
	{
		$base = '20' . str_pad(strval($seq), 10, '0', STR_PAD_LEFT);
		$sum = 0;
		for ($i = 0; $i < 12; $i++)
		{
			$sum += intval($base[$i]) * ($i % 2 === 0 ? 1 : 3);
		}
		return $base . ((10 - $sum % 10) % 10);
	}

	/**
	 * Crea el surtido ampliado (si falta) y códigos EAN-13 para todo el catálogo sin código.
	 * Determinista: misma lista, ids, SKU, precios y pesos de venta en cada ejecución.
	 */
	private function EnsureCatalogExtension(): void
	{
		$db = $this->db;
		$groupId = function (string $name) use ($db): int {
			$q = $db->prepare('SELECT id FROM product_groups WHERE name = ?');
			$q->execute([$name]);
			$id = intval($q->fetchColumn());
			if ($id === 0)
			{
				$db->prepare('INSERT INTO product_groups (name) VALUES (?)')->execute([$name]);
				$id = intval($db->lastInsertId());
			}
			return $id;
		};
		$quId = function (string $unit) use ($db): int {
			$q = $db->prepare('SELECT id FROM quantity_units WHERE name = ?');
			$q->execute([$unit]);
			return intval($q->fetchColumn()) ?: 2;
		};
		$ins = $db->prepare("INSERT OR IGNORE INTO products (id, name, description, product_group_id, active, location_id, shopping_location_id, qu_id_purchase, qu_id_stock, qu_id_consume, qu_id_price,
			min_stock_amount, max_stock_amount, default_best_before_days, sku, brand, sale_price, storage_class, department, should_not_be_frozen)
			VALUES (?, ?, 'Artículo de la empresa de ejemplo (surtido ampliado)', ?, 1, ?, NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
		$id = self::EXT_FIRST_ID;
		foreach ($this->ExtTemplates() as [$code, $dept, $group, $storage, $loc, $life, $margin, $sup, $brands, $items, $variants, $count])
		{
			$made = 0;
			foreach ($variants as $vi => [$vSuffix, $vFactor])
			{
				foreach ($items as $ii => [$name, $cost, $unit])
				{
					if ($made >= $count)
					{
						break 2;
					}
					$brand = $brands[($ii + $vi) % count($brands)];
					$unitCost = round($cost * $vFactor, 2);
					$price = round($unitCost / (1 - $margin) - 0.01, 2) + 0.0;
					$price = floor($price) + ($price - floor($price) < 0.5 ? 0.49 : 0.99);
					$min = $unitCost > 10 ? 2 : ($life <= 7 ? 8 : 6);
					$ins->execute([$id, trim("$name$vSuffix") . " $brand", $groupId($group), $loc, $quId($unit), $quId($unit), $quId($unit), $quId($unit),
						$min, $min * 4, $life, sprintf('%s-%04d', $code, $id), $brand, $price, $storage, $dept, $storage === 'frozen' ? 0 : 1]);
					$id++;
					$made++;
				}
			}
		}
		// Proveedor del producto (id existente o por nombre)
		foreach ($this->ExtCatalog() as $pid => [, , $sup])
		{
			$db->prepare('UPDATE products SET shopping_location_id = ? WHERE id = ? AND shopping_location_id IS NULL')->execute([is_string($sup) ? ($this->suppliers[$sup] ?? null) : $sup, $pid]);
		}
		// EAN-13 para todo producto activo sin código
		$bc = $db->prepare('INSERT INTO product_barcodes (product_id, barcode, note) VALUES (?, ?, ?)');
		foreach ($db->query('SELECT p.id FROM products p WHERE p.active = 1 AND NOT EXISTS (SELECT 1 FROM product_barcodes b WHERE b.product_id = p.id)')->fetchAll(\PDO::FETCH_COLUMN) as $pid)
		{
			$bc->execute([$pid, self::Ean13(intval($pid)), 'EAN-13 interno de la empresa de ejemplo']);
		}
	}

	/** Surtido ampliado como entradas de catálogo: [peso de venta, costo, proveedor]; el peso baja con la posición (cola larga) */
	private function ExtCatalog(): array
	{
		static $cache = null;
		if ($cache !== null)
		{
			return $cache;
		}
		$cache = [];
		$id = self::EXT_FIRST_ID;
		foreach ($this->ExtTemplates() as [, , , , , , , $sup, , $items, $variants, $count])
		{
			$made = 0;
			foreach ($variants as $vi => [, $vFactor])
			{
				foreach ($items as $ii => [, $cost])
				{
					if ($made >= $count)
					{
						break 2;
					}
					// Más vendidos: los primeros artículos y la variante base (curva ABC realista)
					$weight = max(2, intval(round(26 / (1 + $ii * 0.35) / (1 + $vi))));
					$cache[$id] = [$weight, round($cost * $vFactor, 2), $sup];
					$id++;
					$made++;
				}
			}
		}
		return $cache;
	}

	/** Catálogo completo de la simulación: surtido original + ampliado (solo los que existen en la base) */
	private function Catalog(): array
	{
		static $cache = null;
		if ($cache !== null)
		{
			return $cache;
		}
		$have = array_flip(array_map('intval', $this->db->query('SELECT id FROM products WHERE active = 1')->fetchAll(\PDO::FETCH_COLUMN)));
		return $cache = array_intersect_key(self::CATALOG + $this->ExtCatalog(), $have);
	}

	private function BuildSalesPlan(int $days): array
	{
		$hours = [8 => 6, 9 => 5, 10 => 6, 11 => 8, 12 => 10, 13 => 9, 14 => 6, 15 => 5, 16 => 6, 17 => 9, 18 => 10, 19 => 7, 20 => 3];
		$weekday = [1 => 0.9, 2 => 0.85, 3 => 0.9, 4 => 0.95, 5 => 1.1, 6 => 1.3, 7 => 1.15];
		$weights = array_map(fn($c) => $c[0], $this->Catalog());
		$plan = [];
		$nowHour = intval(date('G'));
		foreach (self::BRANCH_SIZE as $b => $size)
		{
			for ($d = -$days + 1; $d <= 0; $d++)
			{
				$dow = intval(date('N', strtotime("$d days")));
				$n = max(5, intval(round(self::TICKETS_PER_DAY * $size * $weekday[$dow] + mt_rand(-3, 3))));
				for ($t = 0; $t < $n; $t++)
				{
					$h = $this->Pick($hours);
					if ($d === 0 && $h >= $nowHour)
					{
						continue;
					}
					$lines = $this->Pick(self::BASKET_LINES);
					$items = [];
					for ($l = 0; $l < $lines; $l++)
					{
						$pid = $this->Pick($weights);
						$items[$pid] = ($items[$pid] ?? 0) + $this->Pick([1 => 60, 2 => 30, 3 => 10]);
					}
					$plan[$b][$d][] = [$h, mt_rand(0, 59), $this->Pick(['card' => 50, 'cash' => 40, 'transfer' => 10]), $items];
				}
				if (isset($plan[$b][$d]))
				{
					usort($plan[$b][$d], fn($x, $y) => ($x[0] * 60 + $x[1]) <=> ($y[0] * 60 + $y[1]));
				}
			}
		}
		return $plan;
	}

	private function Pick(array $weights)
	{
		$r = mt_rand(1, array_sum($weights));
		foreach ($weights as $k => $w)
		{
			$r -= $w;
			if ($r <= 0)
			{
				return $k;
			}
		}
		return array_key_first($weights);
	}

	/* ---------------------------------------------------------------- Operaciones del día */

	/** Compras por proveedor: lácteos, huevos y bebidas semanal; frutas, verduras y carnes cada 3–4 días */
	private function Receive(int $b, int $d, int $days, string $date, array $plan, array $locations, int $fruverSupplier, int $horizon = 0): void
	{
		$offset = $d + $days - 1; // 0 = primer día
		$weekly = $offset % 7 === 0;
		$twice = $offset % 7 === 0 || $offset % 7 === 3;
		$bySupplier = [];
		foreach ($this->Catalog() as $pid => [, $cost, $sup])
		{
			$short = in_array($sup, [2, 5], true) || $sup === 'Panificadora del Puerto';
			if (!($short ? $twice : $weekly))
			{
				continue;
			}
			$cover = $short ? ($offset % 7 === 0 ? 3 : 4) : 7;
			$need = 0;
			for ($x = $d; $x < $d + $cover && $x <= $horizon; $x++)
			{
				foreach ($plan[$b][$x] ?? [] as $t)
				{
					$need += $t[3][$pid] ?? 0;
				}
			}
			// Vigentes en stock (lo vencido se retira cada mañana); perecederos cortos sin colchón para no sobre-comprar
			$onHand = floatval($this->db->query("SELECT IFNULL(SUM(s.amount), 0) FROM stock s JOIN locations l ON l.id = s.location_id WHERE s.product_id = $pid AND l.branch_id = $b AND IFNULL(l.is_transit, 0) = 0 AND s.best_before_date >= '$date'")->fetchColumn());
			$min = floatval($this->db->query("SELECT min_stock_amount FROM products WHERE id = $pid")->fetchColumn());
			$target = $short ? $need * 1.05 : $need * 1.08 + $min * 0.5 * self::BRANCH_SIZE[$b];
			$qty = ceil(max(0, $target - $onHand));
			if ($qty <= 0)
			{
				continue;
			}
			// Una orden por proveedor y tipo de almacenamiento (la temperatura de llegada se mide por entrega)
			$bySupplier[$this->SupplierId($sup, $fruverSupplier) . '|' . $this->StorageOf($pid)][] = ['product_id' => $pid, 'amount' => $qty, 'unit_price' => $cost];
		}
		$mrp = MrpService::GetInstance();
		foreach ($bySupplier as $key => $items)
		{
			[$supplierId, $storage] = explode('|', $key);
			$supplierId = intval($supplierId);
			[$tMin, $tMax] = self::ARRIVAL_TEMP[$storage];
			$ts = $date . ' 07:' . sprintf('%02d', mt_rand(5, 50)) . ':00';
			$mark = $this->Mark();
			$po = $mrp->CreateProcurementOrder(['branch_id' => $b, 'supplier_id' => $supplierId, 'items' => $items], self::USER_ID);
			$mrp->ApproveProcurementOrder($po['id'], self::USER_ID);
			$mrp->SendProcurementOrder($po['id'], self::USER_ID, date('Y-m-d'));
			$detail = $mrp->GetProcurementOrderDetail($po['id']);
			$lines = [];
			$bbdBySim = [];
			foreach ($detail['items'] as $it)
			{
				$life = max(1, intval($it['default_best_before_days']));
				$lines[] = ['order_item_id' => $it['id'], 'delivered_amount' => $it['pending_amount'], 'rejected_amount' => 0,
					'best_before_date' => date('Y-m-d', strtotime("+$life days")), 'location_id' => $b === 1 ? intval($it['product_location_id']) : $locations[$b][$storage],
					'lot_number' => sprintf('L%s-%02d-%d', date('ymd', strtotime($date)), $b, $it['product_id'])];
				$bbdBySim[intval($it['product_id'])] = date('Y-m-d', strtotime("$date +$life days"));
			}
			$rec = $mrp->ReceiveProcurementOrder($po['id'], ['items' => $lines, 'temperature_on_arrival' => round($tMin + mt_rand(0, intval(($tMax - $tMin) * 10)) / 10, 1), 'supplier_document' => 'FAC-' . $po['order_code']], self::USER_ID);
			// Vencimiento relativo al día de la recepción (la validación de vida útil remanente usa la fecha real)
			$updS = $this->db->prepare('UPDATE stock SET best_before_date = ? WHERE product_id = ? AND id > ?');
			$updL = $this->db->prepare('UPDATE stock_log SET best_before_date = ? WHERE product_id = ? AND id > ?');
			$updR = $this->db->prepare('UPDATE procurement_receipt_items SET best_before_date = ? WHERE product_id = ? AND receipt_id = ?');
			foreach ($bbdBySim as $pid => $bbd)
			{
				$updS->execute([$bbd, $pid, $mark['stock']]);
				$updL->execute([$bbd, $pid, $mark['stock_log']]);
				$updR->execute([$bbd, $pid, $rec['id']]);
			}
			$this->db->prepare('UPDATE procurement_orders SET expected_delivery_date = ? WHERE id = ?')->execute([substr($ts, 0, 10), $po['id']]);
			$this->db->prepare("UPDATE supplier_deliveries SET expected_delivery_date = ?, status = 'on_time' WHERE id > ?")->execute([substr($ts, 0, 10), $mark['supplier_deliveries']]);
			$this->Stamp($mark, $ts);
			$this->stats['receipts'] = ($this->stats['receipts'] ?? 0) + 1;
		}
	}

	private function Sell(int $b, string $date, array $ticket): void
	{
		[$h, $m, $pay, $want] = $ticket;
		$items = [];
		foreach ($want as $pid => $qty)
		{
			$free = $this->FreeStock($pid, $b);
			$qty = min($qty, floor($free));
			if ($qty >= 1)
			{
				$price = floatval($this->db->query("SELECT sale_price FROM products WHERE id = $pid")->fetchColumn());
				$items[] = ['product_id' => $pid, 'amount' => $qty, 'unit_price' => $price];
			}
		}
		if (count($items) === 0)
		{
			$this->stats['tickets_skipped'] = ($this->stats['tickets_skipped'] ?? 0) + 1;
			return;
		}
		$mark = $this->Mark();
		for ($try = 0; $try < 3; $try++)
		{
			try
			{
				SalesService::GetInstance()->CreateSale(['branch_id' => $b, 'customer_id' => 1, 'payment_method' => $pay, 'items' => $items], self::USER_ID);
				$this->Stamp($mark, sprintf('%s %02d:%02d:%02d', $date, $h, $m, mt_rand(0, 59)));
				$this->stats['sales'] = ($this->stats['sales'] ?? 0) + 1;
				return;
			}
			catch (\Throwable $e)
			{
				// Número de venta repetido (sufijo aleatorio): se reintenta
				if (strpos($e->getMessage(), 'sale_number') === false)
				{
					$this->stats['sale_errors'][] = $e->getMessage();
					return;
				}
				usleep(2000);
			}
		}
	}

	private function FreeStock(int $pid, int $b): float
	{
		$phys = floatval($this->db->query("SELECT IFNULL(SUM(s.amount), 0) FROM stock s JOIN locations l ON l.id = s.location_id WHERE s.product_id = $pid AND l.branch_id = $b AND IFNULL(l.is_transit, 0) = 0")->fetchColumn());
		$q = floatval($this->db->query("SELECT IFNULL(SUM(amount), 0) FROM quarantine_items WHERE product_id = $pid AND branch_id = $b AND status = 'in_quarantine'")->fetchColumn());
		return $phys - $q - CustomerOrderService::GetInstance()->Reserved($pid, $b);
	}

	/** Retiro de lotes vencidos al abrir la jornada: merma por vencimiento que descuenta el lote exacto */
	private function WriteOffExpired(int $b, string $date): void
	{
		$reason = 33; // Vencimiento
		$lots = $this->db->query("SELECT s.product_id, s.stock_id, s.location_id, SUM(s.amount) AS amount, MIN(s.best_before_date) AS bbd FROM stock s JOIN locations l ON l.id = s.location_id
			WHERE l.branch_id = $b AND IFNULL(l.is_transit, 0) = 0 AND s.best_before_date < '$date' AND s.best_before_date <> '2999-12-31' AND s.product_id IN (" . implode(',', array_keys($this->Catalog())) . ")
			GROUP BY s.product_id, s.stock_id, s.location_id HAVING SUM(s.amount) > 0")->fetchAll(\PDO::FETCH_ASSOC);
		foreach ($lots as $lot)
		{
			$mark = $this->Mark();
			try
			{
				WasteService::GetInstance()->ReportWaste(intval($lot['product_id']), floatval($lot['amount']), $reason, $b, intval($lot['location_id']), $lot['stock_id'],
					'Retiro de lote vencido el ' . $lot['bbd'], null, null, null, $lot['bbd'], null, null, null, self::USER_ID, true, true);
				$this->Stamp($mark, $date . ' 06:00:00');
				$this->stats['waste_expired'] = ($this->stats['waste_expired'] ?? 0) + 1;
			}
			catch (\Throwable $e)
			{
				$this->stats['waste_errors'][] = $e->getMessage();
			}
		}
	}

	/** Roturas y daños en piso de venta (algunas quedan pendientes de aprobación según el monto) */
	private function Breakage(int $b, int $d, string $date): void
	{
		if (mt_rand(1, 100) > 30)
		{
			return;
		}
		$pid = $this->Pick([30 => 4, 34 => 4, 37 => 2, 39 => 2, 50 => 2, 15 => 2, 13 => 1]);
		$qty = $this->Pick([1 => 60, 2 => 30, 3 => 10]);
		// Hora del registro decidida antes: en el día en curso nunca queda fechada en el futuro
		$hour = mt_rand(9, 19);
		$minute = mt_rand(0, 59);
		if ($date === date('Y-m-d') && $hour >= intval(date('G')))
		{
			return;
		}
		if ($this->FreeStock($pid, $b) < $qty)
		{
			return;
		}
		$mark = $this->Mark();
		try
		{
			WasteService::GetInstance()->ReportWaste($pid, $qty, $this->Pick([35 => 60, 34 => 40]), $b, null, 'default', 'Registro en piso de venta',
				null, null, null, null, null, null, null, self::USER_ID, false, true);
			$this->Stamp($mark, sprintf('%s %02d:%02d:00', $date, $hour, $minute));
			$this->stats['waste_breakage'] = ($this->stats['waste_breakage'] ?? 0) + 1;
		}
		catch (\Throwable $e)
		{
			$this->stats['waste_errors'][] = $e->getMessage();
		}
	}

	/** Pedidos de clientes comerciales: despachados en el período; hoy uno confirmado (reserva) y uno en borrador */
	private function Orders(int $b, int $d, string $date, array $customers): void
	{
		$svc = CustomerOrderService::GetInstance();
		$make = function (int $customer) use ($b) {
			$items = [];
			foreach (array_rand(array_flip(self::ORDER_PRODUCTS), 2) as $pid)
			{
				$items[] = ['product_id' => $pid, 'amount' => mt_rand(3, 6)];
			}
			return ['branch_id' => $b, 'customer_id' => $customer, 'items' => $items, 'delivery_type' => 'delivery', 'notes' => 'Pedido semanal del cliente'];
		};
		$enough = function (array $order) use ($b) {
			foreach ($order['items'] as $it)
			{
				if (CustomerOrderService::GetInstance()->Free($it['product_id'], $b) < $it['amount'] + 4)
				{
					return false;
				}
			}
			return true;
		};
		try
		{
			if ($d < 0 && (-$d) % 7 === 4)
			{
				$data = $make($customers[$b][abs($d) % 2]);
				if (!$enough($data))
				{
					return;
				}
				$mark = $this->Mark();
				$o = $svc->Create($data + ['confirm' => true], self::USER_ID);
				$svc->QuickPickFefo($o['order']['id'] ?? $o['id'], self::USER_ID);
				$id = $o['order']['id'] ?? $o['id'];
				$svc->Pack($id, mt_rand(1, 3), self::USER_ID);
				$svc->Dispatch($id, self::USER_ID);
				$this->Stamp($mark, $date . ' 10:30:00');
				$this->db->prepare("UPDATE customer_orders SET created_at = ?, confirmed_at = ?, picked_at = ?, packed_at = ?, dispatched_at = ?, requested_date = ?, delivery_status = 'delivered' WHERE id = ?")
					->execute([date('Y-m-d H:i:s', strtotime("$date 08:10 -1 day")), date('Y-m-d H:i:s', strtotime("$date 08:40 -1 day")), "$date 09:20:00", "$date 09:45:00", "$date 10:30:00", $date, $id]);
				$this->stats['orders_dispatched'] = ($this->stats['orders_dispatched'] ?? 0) + 1;
			}
			elseif ($d === 0)
			{
				$tomorrow = date('Y-m-d', strtotime('+1 day'));
				$data = $make($customers[$b][0]);
				if ($enough($data))
				{
					$svc->Create($data + ['confirm' => true, 'requested_date' => $tomorrow], self::USER_ID);
					$this->stats['orders_confirmed'] = ($this->stats['orders_confirmed'] ?? 0) + 1;
				}
				$svc->Create($make($customers[$b][1]) + ['requested_date' => date('Y-m-d', strtotime('+2 days'))], self::USER_ID);
				$this->stats['orders_draft'] = ($this->stats['orders_draft'] ?? 0) + 1;
			}
		}
		catch (\Throwable $e)
		{
			$this->stats['order_errors'][] = "S$b d$d: " . $e->getMessage();
		}
	}

	/** Devolución total de una venta por sucursal: la venta queda anulada y su stock vuelve al lote original */
	private function ReturnOneSalePerBranch(): void
	{
		foreach (array_keys(self::BRANCH_SIZE) as $b)
		{
			$sale = $this->db->query("SELECT id, created_timestamp FROM sales WHERE branch_id = $b AND status = 'completed' AND notes IS NULL AND date(created_timestamp) = date('now', 'localtime', '-2 days') ORDER BY id LIMIT 1")->fetch(\PDO::FETCH_ASSOC);
			if (!$sale)
			{
				continue;
			}
			$mark = $this->Mark();
			try
			{
				SalesService::GetInstance()->CancelSale(intval($sale['id']), self::USER_ID);
				$this->Stamp($mark, date('Y-m-d H:i:s', strtotime($sale['created_timestamp'] . ' +3 hours')));
				$this->stats['sales_returned'] = ($this->stats['sales_returned'] ?? 0) + 1;
			}
			catch (\Throwable $e)
			{
				$this->stats['sale_errors'][] = 'Devolución: ' . $e->getMessage();
			}
		}
	}

	/**
	 * Traslados entre sucursales de artículos de larga vida útil (dos por semana). Se reciben en la tarde; uno llega con una
	 * unidad faltante (diferencia registrada como merma de traslado) y el de ayer queda en tránsito, pendiente de recepción.
	 */
	private function Transfers(int $d, string $date, array $locations): void
	{
		$days = [-24 => [1, 4], -20 => [6, 10], -17 => [4, 7], -13 => [1, 9], -10 => [8, 2], -6 => [6, 5], -3 => [1, 3], -1 => [4, 10]];
		if (!isset($days[$d]))
		{
			return;
		}
		[$from, $to] = $days[$d];
		$svc = StockTransferService::GetInstance();
		$candidates = [179, 180, 181, 182, 40, 41, 43, 44];
		$picked = [];
		foreach ($candidates as $pid)
		{
			$qty = 4 + (abs($d) % 3);
			if ($this->FreeStock($pid, $from) >= $qty + 8)
			{
				$picked[$this->StorageOf($pid)][] = ['product_id' => $pid, 'amount' => $qty];
			}
		}
		// Un traslado por tipo de almacenamiento (origen y destino de la misma clase)
		foreach ($picked as $storage => $items)
		{
			$items = array_slice($items, 0, 2);
			$fromLoc = intval($this->db->query("SELECT s.location_id FROM stock s JOIN locations l ON l.id = s.location_id WHERE l.branch_id = $from AND s.product_id = {$items[0]['product_id']} AND IFNULL(l.is_transit, 0) = 0 GROUP BY s.location_id ORDER BY SUM(s.amount) DESC LIMIT 1")->fetchColumn());
			foreach ($items as $i => $it)
			{
				$has = $this->db->query("SELECT IFNULL(SUM(amount), 0) FROM stock WHERE location_id = $fromLoc AND product_id = {$it['product_id']}")->fetchColumn();
				if (floatval($has) < $it['amount'])
				{
					unset($items[$i]);
				}
			}
			if (!$items || $fromLoc === 0)
			{
				continue;
			}
			$mark = $this->Mark();
			try
			{
				$t = $svc->CreateTransfer(['from_location_id' => $fromLoc, 'to_location_id' => $locations[$to][$storage], 'items' => array_values($items), 'notes' => 'Reposición entre sucursales'], self::USER_ID);
				$this->Stamp($mark, "$date 11:15:00");
				$this->stats['transfers_sent'] = ($this->stats['transfers_sent'] ?? 0) + 1;
				if ($d === -1)
				{
					continue;
				}
				$mark = $this->Mark();
				$received = [];
				foreach (array_values($items) as $i => $it)
				{
					$received[] = ['product_id' => $it['product_id'], 'received_amount' => $it['amount'] - (($d === -13 && $i === 0) ? 1 : 0)];
				}
				$svc->ReceiveTransfer($t['id'], ['items' => $received, 'notes' => $d === -13 ? 'Llegó una unidad menos; se informó a la sucursal de origen' : null], self::USER_ID);
				$this->Stamp($mark, "$date 16:40:00");
				$this->db->prepare('UPDATE stock_transfers SET received_at = ? WHERE id = ?')->execute(["$date 16:40:00", $t['id']]);
				$this->stats['transfers_received'] = ($this->stats['transfers_received'] ?? 0) + 1;
			}
			catch (\Throwable $e)
			{
				$this->stats['transfer_errors'][] = "d$d: " . $e->getMessage();
			}
		}
	}

	/**
	 * Conteos cíclicos semanales por sucursal (dos artículos). La mayoría cuadra; algunos muestran faltante y quedan con
	 * diferencia pendiente de aprobación por una persona distinta de quien contó (el ajuste no se contabiliza solo).
	 */
	private function CycleCounts(int $d, string $date): void
	{
		if (!in_array($d, [-21, -14, -7, -1], true))
		{
			return;
		}
		$svc = CycleCountService::GetInstance();
		foreach (array_keys(self::BRANCH_SIZE) as $b)
		{
			$pids = $this->db->query("SELECT s.product_id FROM stock s JOIN locations l ON l.id = s.location_id WHERE l.branch_id = $b AND IFNULL(l.is_transit, 0) = 0 AND s.amount > 0
				AND s.product_id IN (" . implode(',', array_keys($this->Catalog())) . ") GROUP BY s.product_id ORDER BY SUM(s.amount * IFNULL(s.price, 0)) DESC LIMIT 2 OFFSET " . (abs($d) % 4))->fetchAll(\PDO::FETCH_COLUMN);
			if (!$pids)
			{
				continue;
			}
			$mark = $this->Mark();
			try
			{
				$svc->ScheduleForProducts($b, array_map('intval', $pids), 'Conteo cíclico semanal', self::USER_ID);
				$ids = $this->db->query("SELECT id, theoretical_stock FROM stock_cycle_counts WHERE id > {$mark['stock_cycle_counts']} ORDER BY id")->fetchAll(\PDO::FETCH_ASSOC);
				foreach ($ids as $i => $c)
				{
					// La sucursal 7 y la 3 con faltante en el último conteo; el resto cuadra
					$short = $d === -1 && $i === 0 && in_array($b, [3, 7], true) ? min(2, floatval($c['theoretical_stock'])) : 0;
					$svc->Execute(intval($c['id']), floatval($c['theoretical_stock']) - $short, $short > 0 ? 'Faltante en góndola; se revisa con video' : 'Conteo coincide', self::USER_ID);
				}
				$this->Stamp($mark, "$date 06:45:00");
				$this->stats['cycle_counts'] = ($this->stats['cycle_counts'] ?? 0) + count($ids);
			}
			catch (\Throwable $e)
			{
				$this->stats['count_errors'][] = "S$b d$d: " . $e->getMessage();
			}
		}
	}

	/* ---------------------------------------------------------------- Calidad y metas */

	private function Temperatures(int $days, array $locations): void
	{
		$ins = $this->db->prepare('INSERT INTO cold_chain_temperatures (location_id, branch_id, temperature, humidity, recorded_timestamp, recorded_by, status, action_taken) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
		$n = 0;
		foreach ($locations as $b => $locs)
		{
			$loc = $locs['chilled'];
			for ($d = -$days + 1; $d <= 0; $d++)
			{
				foreach (['07:30', '15:30'] as $hm)
				{
					if ($d === 0 && intval(substr($hm, 0, 2)) >= intval(date('G')))
					{
						continue;
					}
					// Congelador: una lectura por turno de apertura
					if ($hm === '07:30')
					{
						$ins->execute([$locs['frozen'], $b, round(-19.5 + mt_rand(0, 25) / 10, 1), null, date('Y-m-d', strtotime("$d days")) . " $hm:00", 'Registro de turno', 'normal', null]);
						$n++;
					}
					$t = round(2.4 + mt_rand(0, 19) / 10, 1);
					$excursion = ($b === 5 && $d === -9 && $hm === '15:30') || ($b === 8 && $d === -3 && $hm === '07:30');
					if ($excursion)
					{
						$t = 6.8;
					}
					$ins->execute([$loc, $b, $t, mt_rand(72, 86), date('Y-m-d', strtotime("$d days")) . " $hm:00", 'Registro de turno', $excursion ? 'warning' : 'normal',
						$excursion ? 'Puerta de cámara revisada y temperatura recuperada en 40 minutos' : null]);
					$n++;
				}
			}
		}
		$this->stats['temperatures'] = $n;
	}

	/** Metas del mes en curso por sucursal (a partir del ritmo de venta del período) y meta de red = suma */
	private function Budgets(): void
	{
		$period = date('Y-m');
		$from = date('Y-m-d', strtotime('-27 days'));
		$daysInMonth = intval(date('t'));
		$sum = 0.0;
		$svc = BudgetService::GetInstance();
		foreach (array_keys(self::BRANCH_SIZE) as $b)
		{
			$avg = floatval($this->db->query("SELECT IFNULL(SUM(total_amount - tax_amount), 0) / 28.0 FROM sales WHERE status = 'completed' AND branch_id = $b AND date(created_timestamp) >= '$from'")->fetchColumn());
			$target = round($avg * $daysInMonth * 1.05, -1);
			$sum += $target;
			$svc->SaveBudget(['branch_id' => $b, 'period' => $period, 'sales_target' => $target, 'waste_target_pct' => 2.0, 'margin_target_pct' => 30, 'dsi_target_days' => 10,
				'notes' => 'Meta de la empresa de ejemplo: ritmo de venta de los últimos 28 días + 5 %'], self::USER_ID);
		}
		$svc->SaveBudget(['branch_id' => 0, 'period' => $period, 'sales_target' => $sum, 'waste_target_pct' => 2.0, 'margin_target_pct' => 30, 'dsi_target_days' => 10,
			'notes' => 'Suma de las metas de las 10 sucursales'], self::USER_ID);
		$this->stats['budget_network'] = $sum;
	}

	/* ---------------------------------------------------------------- Reloj simulado */

	const STAMP_TABLES = ['stock_log', 'stock', 'sales', 'sale_items', 'waste_records', 'waste_action_logs', 'procurement_orders', 'procurement_receipts', 'procurement_receipt_items',
		'supplier_deliveries', 'supplier_incidents', 'customer_orders', 'customer_order_picks', 'dairy_stock_lots', 'dairy_alerts', 'stock_transfers', 'stock_cycle_counts'];
	const TIME_COLUMNS = ['row_created_timestamp', 'created_timestamp', 'created_at', 'approved_timestamp', 'approved_at', 'sent_at', 'received_at', 'confirmed_at', 'picked_at', 'packed_at',
		'dispatched_at', 'closed_at', 'timestamp', 'undone_timestamp'];
	const DATE_COLUMNS = ['purchased_date', 'used_date', 'incident_date', 'order_date', 'actual_delivery_date', 'scheduled_date', 'executed_date'];

	private function Mark(): array
	{
		$m = [];
		foreach (self::STAMP_TABLES as $t)
		{
			$pk = $t === 'dairy_stock_lots' ? 'rowid' : 'id';
			$m[$t] = intval($this->db->query("SELECT IFNULL(MAX($pk), 0) FROM $t")->fetchColumn());
		}
		return $m;
	}

	private function Stamp(array $mark, string $ts): void
	{
		$date = substr($ts, 0, 10);
		foreach (self::STAMP_TABLES as $t)
		{
			if (!isset($this->timeCols[$t]))
			{
				$cols = array_column($this->db->query("PRAGMA table_info($t)")->fetchAll(\PDO::FETCH_ASSOC), 'name');
				$this->timeCols[$t] = [array_values(array_intersect(self::TIME_COLUMNS, $cols)), array_values(array_intersect(self::DATE_COLUMNS, $cols))];
			}
			[$tc, $dc] = $this->timeCols[$t];
			$set = [];
			foreach ($tc as $c)
			{
				$set[] = "$c = CASE WHEN $c IS NULL THEN NULL ELSE :ts END";
			}
			foreach ($dc as $c)
			{
				$set[] = "$c = CASE WHEN $c IS NULL THEN NULL ELSE :d END";
			}
			if (!$set)
			{
				continue;
			}
			$pk = $t === 'dairy_stock_lots' ? 'rowid' : 'id';
			$params = [':id' => $mark[$t]];
			if ($tc)
			{
				$params[':ts'] = $ts;
			}
			if ($dc)
			{
				$params[':d'] = $date;
			}
			$this->db->prepare("UPDATE $t SET " . implode(', ', $set) . " WHERE $pk > :id")->execute($params);
		}
		// Número de venta con la fecha de la venta (no la del momento de la carga)
		$this->db->prepare("UPDATE sales SET sale_number = 'VTA-' || :d || '-' || printf('%05d', id) WHERE id > :id AND sale_number LIKE 'VTA-%'")->execute([':d' => str_replace('-', '', $date), ':id' => $mark['sales']]);
	}
}
