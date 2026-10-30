<?php
/**
 * Etapa 30 — «Módulos conectados» respeta permisos: un usuario solo de ventas no ve enlaces a inventario, compras ni KPIs.
 */

putenv('DAIRY_TEST_USER_ID=2');
require __DIR__ . '/bootstrap.php';

use Grocy\Helpers\DairyContextNav;

$db->exec('DELETE FROM user_permissions WHERE user_id = 2');
foreach (['SALES_VIEW', 'SALES_POS'] as $p)
{
	$db->exec("INSERT INTO user_permissions (user_id, permission_id) SELECT 2, id FROM permission_hierarchy WHERE name = '$p'");
}
$routes = fn($nav) => array_map(fn($l) => $l['route'], array_merge($nav['in'] ?? [], $nav['out'] ?? []));
$r = $routes(DairyContextNav::ForView('sales_history'));
ok('ve el POS (tiene permiso)', in_array('/pos', $r, true), json_encode($r));
ok('no ve Stock, MRP, Panel Ejecutivo ni Centros de Costo (/schedule es autoservicio: no está en DairyAccess::PAGES)', !array_intersect($r, ['/stockoverview', '/mrp', '/kpis', '/cost-centers']), json_encode($r));
ok('no ve el enlace al Mapa del Sistema (solo ADMIN)', !\Grocy\Helpers\DairyAccess::CanOpen('/system-map'));

echo "\n$count verificaciones, $fails fallos\n";
exit($fails > 0 ? 1 : 0);
