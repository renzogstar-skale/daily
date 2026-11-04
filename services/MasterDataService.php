<?php

namespace Grocy\Services;

/**
 * Datos maestros (Fase 1.1): un registro de qué tabla es la fuente canónica de cada entidad y un control de
 * integridad que detecta duplicados y referencias rotas. No crea entidades nuevas: los módulos deben referenciar
 * estas tablas en vez de guardar copias en texto.
 */
class MasterDataService extends BaseService
{
	/** entidad → tabla canónica, clave y pantalla de mantenimiento */
	const REGISTRY = [
		'products' => ['label' => 'Productos (SKU)', 'table' => 'products', 'screen' => '/products', 'note' => 'SKU = products.id; UPC/EAN y códigos internos en product_barcodes'],
		'barcodes' => ['label' => 'UPC / códigos', 'table' => 'product_barcodes', 'screen' => '/products', 'note' => 'Un código pertenece a un solo producto'],
		'branches' => ['label' => 'Sucursales', 'table' => 'branches', 'screen' => '/branches', 'note' => ''],
		'locations' => ['label' => 'Almacenes y ubicaciones', 'table' => 'locations', 'screen' => '/locations', 'note' => 'Almacén = locations.warehouse; zona/pasillo/estante/nivel en la misma fila; branch_id obligatorio salvo tránsito'],
		'departments' => ['label' => 'Departamentos', 'table' => 'product_groups', 'screen' => '/productgroups', 'note' => 'Departamento comercial = grupo de producto'],
		'suppliers' => ['label' => 'Proveedores', 'table' => 'shopping_locations', 'screen' => '/suppliers', 'note' => 'Datos comerciales en suppliers_meta (mismo id)'],
		'customers' => ['label' => 'Clientes', 'table' => 'customers', 'screen' => '/customers', 'note' => ''],
		'employees' => ['label' => 'Colaboradores', 'table' => 'employees', 'screen' => '/schedule#tab-employees', 'note' => 'employees.user_id enlaza con el usuario del sistema'],
		'users' => ['label' => 'Usuarios', 'table' => 'users', 'screen' => '/users', 'note' => ''],
		'quantity_units' => ['label' => 'Unidades de medida', 'table' => 'quantity_units', 'screen' => '/quantityunits', 'note' => ''],
		'lots' => ['label' => 'Lotes y vencimientos', 'table' => 'dairy_stock_lots', 'screen' => '/traceability', 'note' => 'Lote ↔ stock.stock_id; vencimiento en stock.best_before_date'],
		'serials' => ['label' => 'IMEI y números de serie', 'table' => 'device_units', 'screen' => '/loss-prevention/devices', 'note' => ''],
		'task_categories' => ['label' => 'Categorías de tareas', 'table' => 'task_categories', 'screen' => '/taskcategories', 'note' => ''],
		'cost_centers' => ['label' => 'Centros de costo', 'table' => 'branches', 'screen' => '/cost-centers', 'note' => 'Centro de costo = sucursal × departamento (CostCenterService); sin tabla propia'],
	];

	private function Db(): \PDO
	{
		return DatabaseService::GetInstance()->GetDbConnectionRaw();
	}

	private function Rows(string $sql): array
	{
		return $this->Db()->query($sql)->fetchAll(\PDO::FETCH_ASSOC);
	}

	public function Registry(): array
	{
		$out = [];
		foreach (self::REGISTRY as $code => $e)
		{
			$out[] = ['code' => $code] + $e + ['rows' => intval($this->Db()->query('SELECT COUNT(*) FROM ' . $e['table'])->fetchColumn())];
		}
		return $out;
	}

	/** Cada control: código, severidad, descripción, cantidad y hasta 20 ejemplos */
	public function Integrity(): array
	{
		$checks = [
			['duplicate_barcodes', 'high', 'Código de barras asignado a más de un producto (el escáner no sabe cuál elegir)',
				'SELECT barcode, COUNT(DISTINCT product_id) AS products, group_concat(DISTINCT product_id) AS product_ids FROM product_barcodes GROUP BY barcode HAVING COUNT(DISTINCT product_id) > 1'],
			['duplicate_product_names', 'medium', 'Productos activos con el mismo nombre (posible SKU duplicado)',
				"SELECT lower(trim(name)) AS name, COUNT(*) AS n, group_concat(id) AS product_ids FROM products WHERE active = 1 GROUP BY lower(trim(name)) HAVING COUNT(*) > 1"],
			['products_invalid_unit', 'high', 'Productos con unidad de stock inexistente',
				'SELECT p.id, p.name, p.qu_id_stock FROM products p LEFT JOIN quantity_units q ON q.id = p.qu_id_stock WHERE q.id IS NULL'],
			['products_invalid_group', 'low', 'Productos con departamento (grupo) inexistente',
				'SELECT p.id, p.name, p.product_group_id FROM products p LEFT JOIN product_groups g ON g.id = p.product_group_id WHERE p.product_group_id IS NOT NULL AND g.id IS NULL'],
			['locations_without_branch', 'high', 'Ubicaciones activas sin sucursal válida (su stock no suma en ninguna sucursal)',
				'SELECT l.id, l.name, l.branch_id FROM locations l LEFT JOIN branches b ON b.id = l.branch_id WHERE l.active = 1 AND IFNULL(l.is_transit, 0) = 0 AND b.id IS NULL'],
			['stock_in_missing_location', 'high', 'Stock en ubicaciones inexistentes',
				'SELECT s.location_id, COUNT(*) AS rows_, SUM(s.amount) AS amount FROM stock s LEFT JOIN locations l ON l.id = s.location_id WHERE l.id IS NULL GROUP BY s.location_id'],
			['duplicate_location_labels', 'medium', 'Etiqueta de ubicación repetida',
				"SELECT label_code, COUNT(*) AS n, group_concat(id) AS location_ids FROM locations WHERE IFNULL(label_code, '') <> '' GROUP BY label_code HAVING COUNT(*) > 1"],
			['suppliers_without_terms', 'low', 'Proveedores sin ficha comercial (plazo de entrega, condiciones)',
				'SELECT s.id, s.name FROM shopping_locations s LEFT JOIN suppliers_meta m ON m.id = s.id WHERE m.id IS NULL'],
			['device_supplier_as_text', 'medium', 'Equipos con proveedor escrito a mano que no existe en el maestro de proveedores',
				"SELECT d.supplier_name, COUNT(*) AS n FROM device_units d WHERE IFNULL(d.supplier_name, '') <> '' AND NOT EXISTS (SELECT 1 FROM shopping_locations s WHERE lower(trim(s.name)) = lower(trim(d.supplier_name))) GROUP BY d.supplier_name"],
			['duplicate_imei', 'high', 'IMEI repetido en más de un equipo',
				"SELECT imei1, COUNT(*) AS n, group_concat(id) AS device_ids FROM device_units WHERE IFNULL(imei1, '') <> '' GROUP BY imei1 HAVING COUNT(*) > 1"],
			['duplicate_customer_tax_id', 'medium', 'Clientes con el mismo documento tributario',
				"SELECT tax_id, COUNT(*) AS n, group_concat(id) AS customer_ids FROM customers WHERE IFNULL(tax_id, '') <> '' GROUP BY tax_id HAVING COUNT(*) > 1"],
			['employees_missing_branch', 'medium', 'Colaboradores activos sin sucursal válida',
				'SELECT e.id, e.name, e.branch_id FROM employees e LEFT JOIN branches b ON b.id = e.branch_id WHERE e.active = 1 AND b.id IS NULL'],
			['employees_missing_user', 'low', 'Colaboradores enlazados a un usuario inexistente',
				'SELECT e.id, e.name, e.user_id FROM employees e LEFT JOIN users u ON u.id = e.user_id WHERE e.user_id IS NOT NULL AND u.id IS NULL'],
			['lots_without_stock_trace', 'low', 'Lotes registrados sin ningún movimiento de stock',
				'SELECT l.stock_id, l.product_id, l.lot_number FROM dairy_stock_lots l WHERE NOT EXISTS (SELECT 1 FROM stock_log sl WHERE sl.stock_id = l.stock_id)'],
		];
		$out = [];
		foreach ($checks as [$code, $sev, $desc, $sql])
		{
			$rows = $this->Rows($sql);
			$out[] = ['code' => $code, 'severity' => $sev, 'description' => $desc, 'count' => count($rows), 'examples' => array_slice($rows, 0, 20)];
		}
		return ['checked_at' => date('Y-m-d H:i:s'), 'issues' => array_sum(array_column($out, 'count')), 'checks' => $out];
	}
}
