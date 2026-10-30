<?php
/**
 * Acceso por sucursal: corre como un usuario sin ADMIN restringido a una sucursal (sobre su propia copia de la BD).
 */

putenv('DAIRY_TEST_USER_ID=2');
require __DIR__ . '/bootstrap.php';

use Grocy\Helpers\DairyAccess;
use Grocy\Middleware\DairyPermissionMiddleware;
use Grocy\Services\StockTransferService;

$branches = $db->query('SELECT id FROM branches ORDER BY id')->fetchAll(PDO::FETCH_COLUMN);
if (count($branches) < 2)
{
	ok('se necesitan al menos 2 sucursales', false);
	exit(1);
}
[$own, $other] = [intval($branches[1]), intval($branches[0])];

// Usuario 2 sin ADMIN, con permisos operativos y restringido a $own
$db->exec('DELETE FROM user_permissions WHERE user_id = 2');
foreach (['STOCK', 'STOCK_TRANSFER', 'WASTE_REPORT', 'KPIS_VIEW'] as $p)
{
	$db->exec("INSERT INTO user_permissions (user_id, permission_id) SELECT 2, id FROM permission_hierarchy WHERE name = '$p'");
}
$db->exec("DELETE FROM user_branches WHERE user_id = 2; INSERT INTO user_branches (user_id, branch_id) VALUES (2, $own)");

ok('usuario restringido a su sucursal', DairyAccess::AllowedBranches() === [$own], json_encode(DairyAccess::AllowedBranches()));
ok('no ve otra sucursal', !DairyAccess::CanSeeBranch($other) && DairyAccess::CanSeeBranch($own));
ok('filtra listados de sucursales', count(DairyAccess::FilterBranches([['id' => $own], ['id' => $other], ['branch_id' => $other]])) === 1);

// Middleware: ScopeToBranches (privado) con solicitudes simuladas
$factory = new \Slim\Psr7\Factory\ServerRequestFactory();
$mw = new ReflectionMethod(DairyPermissionMiddleware::class, 'ScopeToBranches');
$mwObj = (new ReflectionClass(DairyPermissionMiddleware::class))->newInstanceWithoutConstructor();
$req = $factory->createServerRequest('GET', '/kpis');
$scoped = $mw->invoke($mwObj, $req, '/kpis', [], [$own]);
ok('sin branch_id usa su sucursal', ($scoped->getQueryParams()['branch_id'] ?? null) === strval($own));
expectError('branch_id ajeno en la query → 403', fn() => $mw->invoke($mwObj, $req->withQueryParams(['branch_id' => $other]), '/kpis', [], [$own]));
expectError('ruta /branch/{otra}/stock → 403', fn() => $mw->invoke($mwObj, $req, '/branch/{branchId}/stock', ['branchId' => $other], [$own]));
$post = $factory->createServerRequest('POST', '/api/waste/report')->withParsedBody(['product_id' => 1]);
ok('cuerpo de API DAIRY sin branch_id se completa', ($mw->invoke($mwObj, $post, '/api/waste/report', [], [$own])->getParsedBody()['branch_id'] ?? null) === $own);
expectError('cuerpo con branch_id ajeno → 403', fn() => $mw->invoke($mwObj, $post->withParsedBody(['branch_id' => $other]), '/api/waste/report', [], [$own]));
expectError('presupuesto de la red (branch_id 0) → 403', fn() => $mw->invoke($mwObj, $post->withParsedBody(['branch_id' => 0]), '/api/budgets', [], [$own]));
$grocy = $factory->createServerRequest('POST', '/api/stock/products/1/consume')->withParsedBody(['amount' => 1]);
ok('APIs nativas de Grocy no reciben campos extra', !array_key_exists('branch_id', $mw->invoke($mwObj, $grocy, '/api/stock/products/{productId}/consume', [], [$own])->getParsedBody()));

// Traslados: no puede enviar desde otra sucursal
$locOther = intval($val("SELECT id FROM locations WHERE branch_id = $other AND IFNULL(is_transit, 0) = 0 LIMIT 1"));
$locOwn = intval($val("SELECT id FROM locations WHERE branch_id = $own AND IFNULL(is_transit, 0) = 0 LIMIT 1"));
if ($locOther && $locOwn)
{
	expectError('traslado desde otra sucursal bloqueado', fn() => StockTransferService::GetInstance()->CreateTransfer(['from_location_id' => $locOther, 'to_location_id' => $locOwn, 'items' => [['product_id' => 1, 'amount' => 1]]], 2));
}
else
{
	echo "  --   traslado: la sucursal $own no tiene ubicaciones (omitido)\n";
}

echo "\n$count verificaciones, $fails fallidas\n";
exit($fails > 0 ? 1 : 0);
