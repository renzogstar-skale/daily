<?php

// Integridad histórica: movimientos DAIRY (mermas, ventas, stock_log) que apuntan a productos eliminados quedaban fuera
// de los KPIs (JOIN products). Se recrean esos productos como inactivos "(histórico)" con su id original; no se borra ni
// reasigna ningún movimiento. Los productos inactivos no aparecen en listas ni en compras/ventas.

use Grocy\Services\DatabaseService;

$db = DatabaseService::GetInstance()->GetDbConnectionRaw();

$missing = $db->query("
	SELECT x.product_id, MAX(x.qu_id) AS qu_id, MAX(x.location_id) AS location_id
	FROM (
		SELECT product_id, qu_id, location_id FROM waste_records
		UNION ALL SELECT product_id, qu_id, NULL FROM sale_items
		UNION ALL SELECT product_id, NULL, location_id FROM stock_log
	) x
	LEFT JOIN products p ON p.id = x.product_id
	WHERE p.id IS NULL AND x.product_id IS NOT NULL
	GROUP BY x.product_id
")->fetchAll(PDO::FETCH_ASSOC);

if (!empty($missing))
{
	$defaultQu = intval($db->query('SELECT id FROM quantity_units ORDER BY id LIMIT 1')->fetchColumn());
	$defaultLoc = intval($db->query('SELECT id FROM locations WHERE IFNULL(is_transit, 0) = 0 ORDER BY id LIMIT 1')->fetchColumn());
	$validQu = array_flip($db->query('SELECT id FROM quantity_units')->fetchAll(PDO::FETCH_COLUMN));
	$validLoc = array_flip($db->query('SELECT id FROM locations')->fetchAll(PDO::FETCH_COLUMN));

	$stmt = $db->prepare("
		INSERT INTO products (id, name, description, location_id, qu_id_purchase, qu_id_stock, active)
		VALUES (:id, :name, 'Producto eliminado; recreado inactivo para conservar el historial de mermas y ventas.', :loc, :qu, :qu2, 0)
	");
	foreach ($missing as $m)
	{
		$qu = isset($validQu[$m['qu_id']]) ? intval($m['qu_id']) : $defaultQu;
		$loc = isset($validLoc[$m['location_id']]) ? intval($m['location_id']) : $defaultLoc;
		$stmt->execute([':id' => $m['product_id'], ':name' => 'Producto #' . $m['product_id'] . ' (histórico)', ':loc' => $loc, ':qu' => $qu, ':qu2' => $qu]);
	}
}
