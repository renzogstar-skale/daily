<?php

// DAILY — Catálogo único de sucursales de la demo (10 sucursales ficticias)
// Renombra las sucursales existentes por id (sin tocar sus registros asociados) y agrega las faltantes.
// Correspondencia documentada en docs/estrategia/10_sucursales_demo.md

$db = \Grocy\Services\DatabaseService::GetInstance()->GetDbConnectionRaw();

// id => [nombre nuevo, código si la sucursal no existe todavía]
$catalog = [
	1 => ['Hallandale Beach', 'SUC-01'],
	2 => ['Miami Beach', 'SUC-02'],
	3 => ['Sunny Isles Beach', 'SUC-03'],
	4 => ['Aventura', 'SUC-04'],
	5 => ['North Miami Beach', 'SUC-05'],
	6 => ['Miami', 'SUC-06'],
	7 => ['Hollywood', 'SUC-07'],
	8 => ['Fort Lauderdale', 'SUC-08'],
	9 => ['Doral', 'DEMO-TI'],
	10 => ['Hialeah', 'SUC-10']
];

$exists = $db->prepare('SELECT COUNT(*) FROM branches WHERE id = ?');
$rename = $db->prepare('UPDATE branches SET name = ?, address = NULL, phone = NULL WHERE id = ?');
$insert = $db->prepare('INSERT INTO branches (id, name, code, active, company_id) VALUES (?, ?, ?, 1, 1)');
foreach ($catalog as $id => [$name, $code])
{
	$exists->execute([$id]);
	if (intval($exists->fetchColumn()) > 0)
	{
		$rename->execute([$name, $id]);
	}
	else
	{
		$insert->execute([$id, $name, $code]);
	}
}

// Textos libres de la demo que nombraban las sucursales anteriores
$texts = [
	['locations', 'name', 'Poniente', 'Sunny Isles Beach'],
	['locations', 'description', 'Poniente', 'Sunny Isles Beach'],
	['sales', 'notes', 'Matriz', 'Hallandale Beach'],
	['sales', 'notes', 'Poniente', 'Sunny Isles Beach'],
	['dairy_alerts', 'detail', 'Sucursal Matriz', 'Hallandale Beach'],
	['sensitive_inventory_movements', 'origin_location', 'Sucursal Matriz', 'Hallandale Beach'],
	['sensitive_inventory_movements', 'dest_location', 'Sucursal Norte', 'Miami Beach'],
	['sensitive_investigation_evidence', 'description', 'Sucursal Norte', 'Miami Beach']
];
$tables = $db->query("SELECT name FROM sqlite_master WHERE type = 'table'")->fetchAll(\PDO::FETCH_COLUMN);
foreach ($texts as [$table, $column, $old, $new])
{
	if (in_array($table, $tables, true))
	{
		$db->prepare("UPDATE $table SET $column = REPLACE($column, ?, ?) WHERE $column LIKE ?")->execute([$old, $new, '%' . $old . '%']);
	}
}
