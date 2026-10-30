<?php

// Enterprise Platform Migration: Dairy Inventory Attributes, Product Groups, Units and Cold Locations
// Executed inside DatabaseMigrationService context

use Grocy\Services\DatabaseService;

$db = DatabaseService::GetInstance();

// 1. Extend products table with commercial attributes
$productColumns = $db->ExecuteDbQuery("PRAGMA table_info(products)")->fetchAll(\PDO::FETCH_ASSOC);
$existingCols = array_column($productColumns, 'name');

$colsToAdd = [
	'sku' => "ALTER TABLE products ADD COLUMN sku TEXT DEFAULT NULL;",
	'brand' => "ALTER TABLE products ADD COLUMN brand TEXT DEFAULT NULL;",
	'subcategory' => "ALTER TABLE products ADD COLUMN subcategory TEXT DEFAULT NULL;",
	'sale_price' => "ALTER TABLE products ADD COLUMN sale_price REAL DEFAULT 0.0;",
	'max_stock_amount' => "ALTER TABLE products ADD COLUMN max_stock_amount REAL DEFAULT 0.0;"
];

foreach ($colsToAdd as $colName => $alterSql)
{
	if (!in_array($colName, $existingCols))
	{
		$db->ExecuteDbStatement($alterSql);
	}
}

$db->ExecuteDbStatement("CREATE INDEX IF NOT EXISTS ix_products_sku ON products(sku);");

// 2. Insert Dairy Product Groups / Categories
$dairyGroups = [
	['name' => 'Lácteos y Leches', 'description' => 'Leches enteras, descremadas, chocolatadas y especiales fluidas'],
	['name' => 'Huevos y Granja', 'description' => 'Huevos frescos grandes, orgánicos y de campo'],
	['name' => 'Yogures y Probióticos', 'description' => 'Yogur griego, batidos, bebibles y kéfir'],
	['name' => 'Quesos y Untables', 'description' => 'Queso cheddar, mozzarella, queso crema y cottage'],
	['name' => 'Mantequillas y Cremas', 'description' => 'Mantequillas con/sin sal, cremas para café y batidas'],
	['name' => 'Bebidas Refrigeradas', 'description' => 'Jugos prensados en frío y bebidas proteicas refrigeradas']
];

foreach ($dairyGroups as $group)
{
	$exists = $db->ExecuteDbQuery("SELECT COUNT(*) FROM product_groups WHERE name = :name", [':name' => $group['name']])->fetchColumn();
	if ($exists == 0)
	{
		$db->ExecuteDbStatement(
			"INSERT INTO product_groups (name, description, active) VALUES (:name, :description, 1)",
			[':name' => $group['name'], ':description' => $group['description']]
		);
	}
}

// 3. Insert Commercial Quantity Units
$dairyUnits = [
	['name' => 'Galón', 'name_plural' => 'Galones', 'description' => 'Envase de 1 galón (3.785 L)'],
	['name' => '1/2 Galón', 'name_plural' => '1/2 Galones', 'description' => 'Envase de medio galón (1.89 L)'],
	['name' => 'Pote', 'name_plural' => 'Potes', 'description' => 'Pote individual comercial'],
	['name' => 'Paquete', 'name_plural' => 'Paquetes', 'description' => 'Paquete o pack sellado']
];

foreach ($dairyUnits as $unit)
{
	$exists = $db->ExecuteDbQuery("SELECT COUNT(*) FROM quantity_units WHERE name = :name", [':name' => $unit['name']])->fetchColumn();
	if ($exists == 0)
	{
		$db->ExecuteDbStatement(
			"INSERT INTO quantity_units (name, name_plural, description, active) VALUES (:name, :name_plural, :description, 1)",
			[':name' => $unit['name'], ':name_plural' => $unit['name_plural'], ':description' => $unit['description']]
		);
	}
}

// 4. Insert Cold Storage Locations
$coldLocations = [
	['name' => 'Cámara Fría de Lácteos', 'description' => 'Almacén refrigerado principal (2°C - 4°C)', 'is_freezer' => 0, 'branch_id' => 1],
	['name' => 'Góndola Refrigerada Dairy', 'description' => 'Vitrina exhibidora de autoservicio (2°C - 5°C)', 'is_freezer' => 0, 'branch_id' => 1],
	['name' => 'Exhibidor de Huevos', 'description' => 'Módulo de huevos frescos en piso de venta', 'is_freezer' => 0, 'branch_id' => 1]
];

foreach ($coldLocations as $loc)
{
	$exists = $db->ExecuteDbQuery("SELECT COUNT(*) FROM locations WHERE name = :name", [':name' => $loc['name']])->fetchColumn();
	if ($exists == 0)
	{
		$db->ExecuteDbStatement(
			"INSERT INTO locations (name, description, is_freezer, branch_id, active) VALUES (:name, :description, :is_freezer, :branch_id, 1)",
			[
				':name' => $loc['name'],
				':description' => $loc['description'],
				':is_freezer' => $loc['is_freezer'],
				':branch_id' => $loc['branch_id']
			]
		);
	}
}
