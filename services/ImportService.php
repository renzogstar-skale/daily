<?php

namespace Grocy\Services;

/**
 * Importación de maestros desde CSV (exportado de Excel «CSV UTF-8», separador , ; o tabulador).
 * Flujo: validar (dry run, no escribe) → confirmar (una transacción; si una fila falla no se guarda nada).
 * Valida obligatorios, formatos, referencias (unidad, grupo, ubicación, sucursal, proveedor) y duplicados en el archivo
 * y en la base. Modo «create» rechaza existentes; «upsert» los actualiza. Cada cambio queda en dairy_change_log (triggers)
 * y cada importación en dairy_import_jobs.
 */
class ImportService extends BaseService
{
	const MAX_ROWS = 5000;

	const ENTITIES = [
		'products' => ['label' => 'Productos', 'columns' => ['name' => 'nombre*', 'sku' => 'sku', 'barcode' => 'codigo_barras', 'product_group' => 'grupo', 'unit' => 'unidad', 'location' => 'ubicacion', 'supplier' => 'proveedor',
			'min_stock_amount' => 'stock_minimo', 'max_stock_amount' => 'stock_maximo', 'default_best_before_days' => 'vida_util_dias', 'sale_price' => 'precio_venta', 'brand' => 'marca', 'storage_class' => 'clase_almacenamiento']],
		'suppliers' => ['label' => 'Proveedores', 'columns' => ['name' => 'nombre*', 'description' => 'descripcion']],
		'customers' => ['label' => 'Clientes', 'columns' => ['name' => 'nombre*', 'tax_id' => 'documento', 'phone' => 'telefono', 'email' => 'correo', 'address' => 'direccion']],
		'locations' => ['label' => 'Ubicaciones', 'columns' => ['name' => 'nombre*', 'branch' => 'sucursal*', 'warehouse' => 'almacen', 'zone' => 'zona', 'aisle' => 'pasillo', 'shelf' => 'estante', 'bin' => 'posicion',
			'label_code' => 'etiqueta', 'capacity_units' => 'capacidad', 'storage_class' => 'clase_almacenamiento']],
	];
	const STORAGE_CLASSES = ['ambient' => 'ambiente', 'chilled' => 'refrigerado', 'frozen' => 'congelado'];

	private array $cache = [];

	private function Db(): \PDO
	{
		return DatabaseService::GetInstance()->GetDbConnectionRaw();
	}

	private function Map(string $sql): array
	{
		$out = [];
		foreach ($this->Db()->query($sql)->fetchAll(\PDO::FETCH_NUM) as $r)
		{
			$out[$this->Key($r[1])] = intval($r[0]);
		}
		return $out;
	}

	private function Key($v): string
	{
		return mb_strtolower(trim(preg_replace('/\s+/u', ' ', (string) $v)));
	}

	public function Template(string $entity): array
	{
		$def = self::ENTITIES[$entity] ?? throw new \Exception('Entidad de importación no soportada');
		return array_map(fn($h) => rtrim($h, '*'), $def['columns']);
	}

	public function ParseCsv(string $content): array
	{
		$content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
		if (!mb_check_encoding($content, 'UTF-8'))
		{
			$content = mb_convert_encoding($content, 'UTF-8', 'Windows-1252'); // CSV de Excel en codificación regional
		}
		$lines = preg_split('/\r\n|\n|\r/', trim($content));
		if (count($lines) < 2)
		{
			throw new \Exception('El archivo no tiene filas de datos (la primera fila debe ser el encabezado)');
		}
		$first = $lines[0];
		$sep = substr_count($first, ';') > substr_count($first, ',') ? ';' : (substr_count($first, "\t") > substr_count($first, ',') ? "\t" : ',');
		$rows = [];
		$fh = fopen('php://temp', 'r+');
		fwrite($fh, implode("\n", $lines));
		rewind($fh);
		while (($r = fgetcsv($fh, 0, $sep, '"', '')) !== false)
		{
			$rows[] = $r;
		}
		fclose($fh);
		$header = array_map(fn($h) => $this->Key(rtrim($h, '*')), array_shift($rows));
		return [$header, $rows];
	}

	public function Run(string $entity, string $content, bool $dryRun, string $mode, ?string $fileName, int $userId): array
	{
		$def = self::ENTITIES[$entity] ?? throw new \Exception('Entidad de importación no soportada');
		if (!in_array($mode, ['create', 'upsert']))
		{
			throw new \Exception('Modo inválido (create | upsert)');
		}
		$this->cache = [];
		[$header, $data] = $this->ParseCsv($content);
		if (count($data) > self::MAX_ROWS)
		{
			throw new \Exception('Máximo ' . self::MAX_ROWS . ' filas por archivo');
		}
		$colIndex = [];
		foreach ($def['columns'] as $field => $label)
		{
			$i = array_search($this->Key(rtrim($label, '*')), $header, true);
			$i = $i === false ? array_search($field, $header, true) : $i;
			if ($i === false && str_ends_with($label, '*'))
			{
				throw new \Exception('Falta la columna obligatoria «' . rtrim($label, '*') . '». Descargue la plantilla.');
			}
			if ($i !== false)
			{
				$colIndex[$field] = $i;
			}
		}
		$unknown = array_values(array_diff($header, array_map(fn($l) => $this->Key(rtrim($l, '*')), $def['columns']), array_keys($def['columns'])));

		$results = [];
		$seen = [];
		foreach ($data as $n => $cells)
		{
			if (count(array_filter($cells, fn($c) => trim((string) $c) !== '')) === 0)
			{
				continue;
			}
			$row = [];
			foreach ($colIndex as $field => $i)
			{
				$row[$field] = trim((string) ($cells[$i] ?? ''));
			}
			$res = $this->{'Validate' . ucfirst($entity)}($row, $mode);
			$res['line'] = $n + 2;
			foreach ($res['unique_keys'] ?? [] as $uk)
			{
				if (isset($seen[$uk]))
				{
					$res['errors'][] = "Duplicado dentro del archivo (igual a la fila {$seen[$uk]})";
				}
				$seen[$uk] = $res['line'];
			}
			unset($res['unique_keys']);
			$results[] = $res;
		}
		$rejected = count(array_filter($results, fn($r) => count($r['errors']) > 0));
		$summary = ['entity' => $entity, 'label' => $def['label'], 'rows' => count($results), 'valid' => count($results) - $rejected, 'rejected' => $rejected,
			'to_create' => count(array_filter($results, fn($r) => !$r['errors'] && $r['action'] === 'create')),
			'to_update' => count(array_filter($results, fn($r) => !$r['errors'] && $r['action'] === 'update')),
			'ignored_columns' => $unknown, 'results' => $results, 'dry_run' => $dryRun];
		if ($dryRun)
		{
			return $summary;
		}
		if ($rejected > 0)
		{
			throw new \Exception("$rejected fila(s) con errores: corríjalas y vuelva a validar. No se guardó nada.");
		}

		$db = $this->Db();
		$db->beginTransaction();
		try
		{
			foreach ($results as $r)
			{
				$this->{'Save' . ucfirst($entity)}($r);
			}
			$db->prepare('INSERT INTO dairy_import_jobs (entity, file_name, rows_total, rows_created, rows_updated, rows_rejected, user_id, created_at) VALUES (:e, :f, :t, :c, :u, 0, :usr, :at)')
				->execute([':e' => $entity, ':f' => $fileName, ':t' => $summary['rows'], ':c' => $summary['to_create'], ':u' => $summary['to_update'], ':usr' => $userId, ':at' => date('Y-m-d H:i:s')]);
			$db->commit();
		}
		catch (\Throwable $e)
		{
			$db->rollBack();
			throw new \Exception('Importación revertida: ' . $e->getMessage());
		}
		$summary['dry_run'] = false;
		$summary['message'] = "{$summary['to_create']} creado(s), {$summary['to_update']} actualizado(s)";
		return $summary;
	}

	private function Num(array &$errors, array $row, string $field, string $label, bool $int = false): ?float
	{
		$v = str_replace(',', '.', $row[$field] ?? '');
		if ($v === '')
		{
			return null;
		}
		if (!is_numeric($v) || floatval($v) < 0 || ($int && floor(floatval($v)) != floatval($v)))
		{
			$errors[] = "$label debe ser un número " . ($int ? 'entero ' : '') . 'mayor o igual a 0';
			return null;
		}
		return floatval($v);
	}

	private function ValidateProducts(array $row, string $mode): array
	{
		$maps = $this->cache[__FUNCTION__] ??= [
			'products' => $this->Map('SELECT id, name FROM products'),
			'sku' => $this->Map("SELECT id, sku FROM products WHERE IFNULL(sku, '') <> ''"),
			'barcodes' => $this->Map('SELECT product_id, barcode FROM product_barcodes'),
			'groups' => $this->Map('SELECT id, name FROM product_groups'),
			'units' => $this->Map('SELECT id, name FROM quantity_units'),
			'locations' => $this->Map('SELECT id, name FROM locations WHERE IFNULL(is_transit, 0) = 0') + $this->Map("SELECT id, label_code FROM locations WHERE IFNULL(label_code, '') <> ''"),
			'suppliers' => $this->Map('SELECT id, name FROM shopping_locations'),
		];
		$e = [];
		$name = $row['name'] ?? '';
		if ($name === '' || mb_strlen($name) > 120)
		{
			$e[] = 'Nombre obligatorio (máx. 120 caracteres)';
		}
		$existing = $maps['products'][$this->Key($name)] ?? null;
		if (!empty($row['sku']) && isset($maps['sku'][$this->Key($row['sku'])]) && $maps['sku'][$this->Key($row['sku'])] !== $existing)
		{
			$e[] = "SKU {$row['sku']} ya pertenece a otro producto";
		}
		if (!empty($row['barcode']))
		{
			if (!preg_match('/^[0-9A-Za-z\-\.]{4,40}$/', $row['barcode']))
			{
				$e[] = 'Código de barras con caracteres inválidos';
			}
			elseif (ctype_digit($row['barcode']) && in_array(strlen($row['barcode']), [8, 12, 13, 14]) && !ScanService::GetInstance()->ValidCheckDigit($row['barcode']))
			{
				$e[] = 'Código EAN/UPC con dígito verificador inválido';
			}
			$owner = $maps['barcodes'][$this->Key($row['barcode'])] ?? null;
			if ($owner !== null && $owner !== $existing)
			{
				$e[] = "Código de barras {$row['barcode']} ya asignado a otro producto";
			}
		}
		$ref = function (string $field, string $map, string $label) use (&$e, $row, $maps)
		{
			if (($row[$field] ?? '') === '')
			{
				return null;
			}
			$id = $maps[$map][$this->Key($row[$field])] ?? null;
			if ($id === null)
			{
				$e[] = "$label «{$row[$field]}» no existe";
			}
			return $id;
		};
		$out = [
			'name' => $name, 'sku' => $row['sku'] ?? null, 'barcode' => $row['barcode'] ?? null, 'brand' => $row['brand'] ?? null,
			'product_group_id' => $ref('product_group', 'groups', 'Grupo'), 'qu_id' => $ref('unit', 'units', 'Unidad'),
			'location_id' => $ref('location', 'locations', 'Ubicación'), 'shopping_location_id' => $ref('supplier', 'suppliers', 'Proveedor'),
			'min_stock_amount' => $this->Num($e, $row, 'min_stock_amount', 'Stock mínimo'), 'max_stock_amount' => $this->Num($e, $row, 'max_stock_amount', 'Stock máximo'),
			'default_best_before_days' => $this->Num($e, $row, 'default_best_before_days', 'Vida útil', true), 'sale_price' => $this->Num($e, $row, 'sale_price', 'Precio de venta'),
			'storage_class' => $this->StorageClass($e, $row['storage_class'] ?? ''),
		];
		if ($out['min_stock_amount'] !== null && $out['max_stock_amount'] !== null && $out['max_stock_amount'] < $out['min_stock_amount'])
		{
			$e[] = 'Stock máximo menor que el mínimo';
		}
		if ($existing === null && ($out['qu_id'] === null || $out['location_id'] === null) && !$e)
		{
			$e[] = 'Producto nuevo: unidad y ubicación son obligatorias';
		}
		if ($existing !== null && $mode === 'create')
		{
			$e[] = "Ya existe un producto «{$name}» (use el modo actualizar)";
		}
		return ['action' => $existing ? 'update' : 'create', 'id' => $existing, 'data' => $out, 'errors' => $e, 'warnings' => [],
			'unique_keys' => array_filter(['n:' . $this->Key($name), !empty($row['barcode']) ? 'b:' . $row['barcode'] : null, !empty($row['sku']) ? 's:' . $this->Key($row['sku']) : null])];
	}

	private function SaveProducts(array $r): void
	{
		$d = $r['data'];
		$db = $this->Db();
		$set = array_filter(['name' => $d['name'], 'sku' => $d['sku'] ?: null, 'brand' => $d['brand'] ?: null, 'product_group_id' => $d['product_group_id'], 'location_id' => $d['location_id'],
			'shopping_location_id' => $d['shopping_location_id'], 'min_stock_amount' => $d['min_stock_amount'], 'max_stock_amount' => $d['max_stock_amount'],
			'default_best_before_days' => $d['default_best_before_days'], 'sale_price' => $d['sale_price'], 'storage_class' => $d['storage_class']], fn($v) => $v !== null);
		if ($r['action'] === 'create')
		{
			$set += ['qu_id_purchase' => $d['qu_id'], 'qu_id_stock' => $d['qu_id'], 'qu_id_consume' => $d['qu_id'], 'qu_id_price' => $d['qu_id'], 'active' => 1];
			$db->prepare('INSERT INTO products (' . implode(', ', array_keys($set)) . ') VALUES (:' . implode(', :', array_keys($set)) . ')')->execute(array_combine(array_map(fn($k) => ":$k", array_keys($set)), array_values($set)));
			$id = intval($db->lastInsertId());
		}
		else
		{
			$id = intval($r['id']);
			unset($set['name']);
			if ($set)
			{
				$db->prepare('UPDATE products SET ' . implode(', ', array_map(fn($k) => "$k = :$k", array_keys($set))) . ' WHERE id = :id')->execute(array_combine(array_map(fn($k) => ":$k", array_keys($set)), array_values($set)) + [':id' => $id]);
			}
		}
		if (!empty($d['barcode']))
		{
			$db->prepare('INSERT INTO product_barcodes (product_id, barcode) SELECT :p, :b WHERE NOT EXISTS (SELECT 1 FROM product_barcodes WHERE barcode = :b)')->execute([':p' => $id, ':b' => $d['barcode']]);
		}
	}

	private function StorageClass(array &$errors, string $value): ?string
	{
		$k = $this->Key($value);
		if ($k === '')
		{
			return null;
		}
		$key = array_search($k, self::STORAGE_CLASSES, true) ?: (isset(self::STORAGE_CLASSES[$k]) ? $k : null);
		if ($key === null)
		{
			$errors[] = 'Clase de almacenamiento: ambiente, refrigerado o congelado';
		}
		return $key;
	}

	private function ValidateSuppliers(array $row, string $mode): array
	{
		$map = $this->cache[__FUNCTION__] ??= $this->Map('SELECT id, name FROM shopping_locations');
		$e = [];
		$name = $row['name'] ?? '';
		if ($name === '' || mb_strlen($name) > 120)
		{
			$e[] = 'Nombre obligatorio (máx. 120 caracteres)';
		}
		$existing = $map[$this->Key($name)] ?? null;
		if ($existing !== null && $mode === 'create')
		{
			$e[] = "Ya existe el proveedor «{$name}»";
		}
		return ['action' => $existing ? 'update' : 'create', 'id' => $existing, 'data' => ['name' => $name, 'description' => $row['description'] ?? null], 'errors' => $e, 'warnings' => [], 'unique_keys' => ['n:' . $this->Key($name)]];
	}

	private function SaveSuppliers(array $r): void
	{
		if ($r['action'] === 'create')
		{
			$this->Db()->prepare('INSERT INTO shopping_locations (name, description, active) VALUES (:n, :d, 1)')->execute([':n' => $r['data']['name'], ':d' => $r['data']['description'] ?: null]);
		}
		elseif (($r['data']['description'] ?? '') !== '')
		{
			$this->Db()->prepare('UPDATE shopping_locations SET description = :d WHERE id = :id')->execute([':d' => $r['data']['description'], ':id' => $r['id']]);
		}
	}

	private function ValidateCustomers(array $row, string $mode): array
	{
		$maps = $this->cache[__FUNCTION__] ??= ['tax' => $this->Map("SELECT id, tax_id FROM customers WHERE IFNULL(tax_id, '') <> ''"), 'name' => $this->Map('SELECT id, name FROM customers')];
		$e = [];
		$w = [];
		$name = $row['name'] ?? '';
		if ($name === '' || mb_strlen($name) > 120)
		{
			$e[] = 'Nombre obligatorio (máx. 120 caracteres)';
		}
		$tax = strtoupper(preg_replace('/[\s\.]/', '', $row['tax_id'] ?? ''));
		if ($tax !== '' && !preg_match('/^[0-9A-Z\-]{5,20}$/', $tax))
		{
			$e[] = 'Documento con formato inválido (5–20 letras/números)';
		}
		if (($row['email'] ?? '') !== '' && !filter_var($row['email'], FILTER_VALIDATE_EMAIL))
		{
			$e[] = 'Correo inválido';
		}
		if (($row['phone'] ?? '') !== '' && !preg_match('/^[0-9+\-\s()]{6,20}$/', $row['phone']))
		{
			$e[] = 'Teléfono inválido';
		}
		$existing = $tax !== '' ? ($maps['tax'][$this->Key($tax)] ?? null) : null;
		if ($existing === null && $tax === '' && isset($maps['name'][$this->Key($name)]))
		{
			$w[] = 'Ya existe un cliente con ese nombre y sin documento: revise si es el mismo';
		}
		if ($existing !== null && $mode === 'create')
		{
			$e[] = "Ya existe un cliente con documento $tax";
		}
		return ['action' => $existing ? 'update' : 'create', 'id' => $existing, 'errors' => $e, 'warnings' => $w,
			'data' => ['name' => $name, 'tax_id' => $tax ?: null, 'phone' => $row['phone'] ?? null, 'email' => $row['email'] ?? null, 'address' => $row['address'] ?? null],
			'unique_keys' => $tax !== '' ? ['t:' . $tax] : ['n:' . $this->Key($name)]];
	}

	private function SaveCustomers(array $r): void
	{
		$d = $r['data'];
		if ($r['action'] === 'create')
		{
			$this->Db()->prepare('INSERT INTO customers (tax_id, name, phone, email, address, active, created_timestamp) VALUES (:t, :n, :p, :e, :a, 1, :c)')
				->execute([':t' => $d['tax_id'], ':n' => $d['name'], ':p' => $d['phone'] ?: null, ':e' => $d['email'] ?: null, ':a' => $d['address'] ?: null, ':c' => date('Y-m-d H:i:s')]);
		}
		else
		{
			$this->Db()->prepare('UPDATE customers SET name = :n, phone = COALESCE(:p, phone), email = COALESCE(:e, email), address = COALESCE(:a, address) WHERE id = :id')
				->execute([':n' => $d['name'], ':p' => $d['phone'] ?: null, ':e' => $d['email'] ?: null, ':a' => $d['address'] ?: null, ':id' => $r['id']]);
		}
	}

	private function ValidateLocations(array $row, string $mode): array
	{
		$maps = $this->cache[__FUNCTION__] ??= ['branches' => $this->Map('SELECT id, name FROM branches') + $this->Map("SELECT id, code FROM branches WHERE IFNULL(code, '') <> ''"),
			'labels' => $this->Map("SELECT id, label_code FROM locations WHERE IFNULL(label_code, '') <> ''"), 'names' => $this->Map('SELECT id, name FROM locations')];
		$e = [];
		$name = $row['name'] ?? '';
		if ($name === '' || mb_strlen($name) > 80)
		{
			$e[] = 'Nombre obligatorio (máx. 80 caracteres)';
		}
		$branchId = $maps['branches'][$this->Key($row['branch'] ?? '')] ?? null;
		if ($branchId === null)
		{
			$e[] = 'Sucursal «' . ($row['branch'] ?? '') . '» no existe';
		}
		elseif (!\Grocy\Helpers\DairyAccess::CanSeeBranch($branchId))
		{
			$e[] = 'Sin acceso a la sucursal';
		}
		$label = strtoupper($row['label_code'] ?? '');
		if ($label !== '' && !preg_match('/^LOC-[A-Z0-9\-]{1,30}$/', $label))
		{
			$e[] = 'Etiqueta debe tener el formato LOC-… (letras, números, guiones)';
		}
		$existing = $label !== '' ? ($maps['labels'][$this->Key($label)] ?? null) : ($maps['names'][$this->Key($name)] ?? null);
		if ($existing !== null && $mode === 'create')
		{
			$e[] = 'La ubicación ya existe';
		}
		$class = $this->Key($row['storage_class'] ?? '');
		$classKey = $class === '' ? null : (array_search($class, self::STORAGE_CLASSES, true) ?: (isset(self::STORAGE_CLASSES[$class]) ? $class : false));
		if ($classKey === false)
		{
			$e[] = 'Clase de almacenamiento: ambiente, refrigerado o congelado';
		}
		return ['action' => $existing ? 'update' : 'create', 'id' => $existing, 'errors' => $e, 'warnings' => [],
			'data' => ['name' => $name, 'branch_id' => $branchId, 'warehouse' => $row['warehouse'] ?? null, 'zone' => $row['zone'] ?? null, 'aisle' => $row['aisle'] ?? null, 'shelf' => $row['shelf'] ?? null,
				'bin' => $row['bin'] ?? null, 'label_code' => $label ?: null, 'capacity_units' => $this->Num($e, $row, 'capacity_units', 'Capacidad'), 'storage_class' => $classKey ?: null],
			'unique_keys' => array_filter(['n:' . $branchId . ':' . $this->Key($name), $label !== '' ? 'l:' . $label : null])];
	}

	private function SaveLocations(array $r): void
	{
		$d = array_map(fn($v) => $v === '' ? null : $v, $r['data']);
		$db = $this->Db();
		if ($r['action'] === 'create')
		{
			$db->prepare('INSERT INTO locations (name, branch_id, warehouse, zone, aisle, shelf, bin, label_code, capacity_units, storage_class, active) VALUES (:name, :branch_id, :warehouse, :zone, :aisle, :shelf, :bin, :label_code, :capacity_units, :storage_class, 1)')
				->execute(array_combine(array_map(fn($k) => ":$k", array_keys($d)), array_values($d)));
			$id = intval($db->lastInsertId());
			if (!$d['label_code'])
			{
				$db->exec("UPDATE locations SET label_code = 'LOC-' || printf('%05d', id) WHERE id = $id");
			}
		}
		else
		{
			$db->prepare('UPDATE locations SET name = :name, branch_id = :branch_id, warehouse = :warehouse, zone = :zone, aisle = :aisle, shelf = :shelf, bin = :bin,
				label_code = COALESCE(:label_code, label_code), capacity_units = :capacity_units, storage_class = COALESCE(:storage_class, storage_class) WHERE id = :id')
				->execute(array_combine(array_map(fn($k) => ":$k", array_keys($d)), array_values($d)) + [':id' => $r['id']]);
		}
	}

	public function Export(string $entity): array
	{
		$sql = match ($entity)
		{
			'products' => "SELECT p.name, p.sku, (SELECT MIN(barcode) FROM product_barcodes WHERE product_id = p.id) AS barcode, pg.name AS product_group, qu.name AS unit, l.name AS location, sl.name AS supplier,
				p.min_stock_amount, p.max_stock_amount, p.default_best_before_days, p.sale_price, p.brand, p.storage_class FROM products p LEFT JOIN product_groups pg ON pg.id = p.product_group_id
				LEFT JOIN quantity_units qu ON qu.id = p.qu_id_stock LEFT JOIN locations l ON l.id = p.location_id LEFT JOIN shopping_locations sl ON sl.id = p.shopping_location_id WHERE p.active = 1 ORDER BY p.name",
			'suppliers' => 'SELECT name, description FROM shopping_locations WHERE active = 1 ORDER BY name',
			'customers' => 'SELECT name, tax_id, phone, email, address FROM customers WHERE active = 1 ORDER BY name',
			'locations' => "SELECT l.name, b.name AS branch, l.warehouse, l.zone, l.aisle, l.shelf, l.bin, l.label_code, l.capacity_units, l.storage_class FROM locations l LEFT JOIN branches b ON b.id = l.branch_id
				WHERE l.active = 1 AND IFNULL(l.is_transit, 0) = 0 ORDER BY b.id, l.name",
			default => throw new \Exception('Entidad de importación no soportada'),
		};
		$rows = $this->Db()->query($sql)->fetchAll(\PDO::FETCH_ASSOC);
		if ($entity === 'locations' || $entity === 'products')
		{
			foreach ($rows as &$r)
			{
				$r['storage_class'] = self::STORAGE_CLASSES[$r['storage_class'] ?? ''] ?? $r['storage_class'];
			}
			unset($r);
		}
		return $rows;
	}

	public function History(string $table, int $rowId, int $limit = 50): array
	{
		if (!in_array($table, ['products', 'product_barcodes', 'shopping_locations', 'customers', 'locations', 'waste_reasons']))
		{
			throw new \Exception('Tabla sin historial');
		}
		$stmt = $this->Db()->prepare('SELECT c.*, u.username FROM dairy_change_log c LEFT JOIN users u ON u.id = c.user_id WHERE c.table_name = :t AND c.row_id = :r ORDER BY c.id DESC LIMIT ' . intval($limit));
		$stmt->execute([':t' => $table, ':r' => $rowId]);
		$out = [];
		foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $c)
		{
			$old = $c['old_json'] ? json_decode($c['old_json'], true) : [];
			$new = $c['new_json'] ? json_decode($c['new_json'], true) : [];
			$changes = [];
			foreach (array_unique(array_merge(array_keys($old), array_keys($new))) as $k)
			{
				if (($old[$k] ?? null) != ($new[$k] ?? null) && $k !== 'row_created_timestamp')
				{
					$changes[] = ['field' => $k, 'from' => $old[$k] ?? null, 'to' => $new[$k] ?? null];
				}
			}
			$out[] = ['id' => intval($c['id']), 'action' => $c['action'], 'user' => $c['username'], 'changed_at' => $c['changed_at'], 'changes' => $changes];
		}
		return $out;
	}

	public function Jobs(int $limit = 20): array
	{
		return $this->Db()->query('SELECT j.*, u.username FROM dairy_import_jobs j LEFT JOIN users u ON u.id = j.user_id ORDER BY j.id DESC LIMIT ' . intval($limit))->fetchAll(\PDO::FETCH_ASSOC);
	}
}
