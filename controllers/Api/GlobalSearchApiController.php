<?php

namespace Grocy\Controllers\Api;

use Grocy\Controllers\Users\User;
use Grocy\Helpers\DairyAccess;
use Grocy\Services\DatabaseService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

// Buscador global del header (Ctrl+K): productos, lotes, casos de merma y proveedores; cada grupo solo si el usuario puede abrir su destino
class GlobalSearchApiController extends BaseApiController
{
	public function Search(Request $request, Response $response, array $args)
	{
		try
		{
			$q = trim(mb_substr(strval($request->getQueryParams()['q'] ?? ''), 0, 80));
			if (mb_strlen($q) < 2)
			{
				return $this->ApiResponse($response, []);
			}
			$db = DatabaseService::GetInstance()->GetDbConnectionRaw();
			$rows = function (string $sql, array $p) use ($db) {
				$s = $db->prepare($sql);
				$s->execute($p);
				return $s->fetchAll(\PDO::FETCH_ASSOC);
			};
			$like = '%' . $q . '%';
			$out = [];

			if (DairyAccess::CanOpen('/kardex'))
			{
				foreach ($rows("SELECT DISTINCT p.id, p.name, p.sku FROM products p LEFT JOIN product_barcodes pb ON pb.product_id = p.id
					WHERE p.active = 1 AND (p.name LIKE :q OR p.sku LIKE :q OR pb.barcode = :exact) ORDER BY p.name LIMIT 8", [':q' => $like, ':exact' => $q]) as $r)
				{
					$out[] = ['type' => 'product', 'title' => $r['name'], 'sub' => $r['sku'] ? 'SKU ' . $r['sku'] : 'Producto', 'href' => '/kardex?product_id=' . $r['id']];
				}
			}

			if (DairyAccess::CanOpen('/traceability'))
			{
				foreach ($rows("SELECT l.lot_number, IFNULL(l.serial_number, '') AS serial, IFNULL(p.name, '') AS product FROM dairy_stock_lots l LEFT JOIN products p ON p.id = l.product_id
					WHERE l.lot_number LIKE :q OR l.serial_number LIKE :q GROUP BY l.lot_number, l.product_id ORDER BY l.created_at DESC LIMIT 6", [':q' => $like]) as $r)
				{
					$code = $r['lot_number'] ?: $r['serial'];
					$out[] = ['type' => 'lot', 'title' => 'Lote ' . $code, 'sub' => $r['product'], 'href' => '/traceability?q=' . rawurlencode($code)];
				}
			}

			if (DairyAccess::HasAny(DairyAccess::DOCUMENTS['waste']))
			{
				$allowed = DairyAccess::AllowedBranches();
				$bW = $allowed !== null ? ' AND w.branch_id IN (' . implode(',', array_map('intval', $allowed)) . ')' : '';
				foreach ($rows("SELECT w.id, IFNULL(w.case_number, 'MRM-' || w.id) AS code, IFNULL(p.name, '') AS product, w.status FROM waste_records w LEFT JOIN products p ON p.id = w.product_id
					WHERE (w.case_number LIKE :q OR p.name LIKE :q)$bW ORDER BY w.id DESC LIMIT 6", [':q' => $like]) as $r)
				{
					$out[] = ['type' => 'case', 'title' => $r['code'], 'sub' => 'Merma · ' . $r['product'], 'href' => '/document/waste/' . $r['id']];
				}
			}

			if (DairyAccess::CanOpen('/suppliers'))
			{
				foreach ($rows('SELECT id, name FROM shopping_locations WHERE active = 1 AND name LIKE :q ORDER BY name LIMIT 5', [':q' => $like]) as $r)
				{
					$out[] = ['type' => 'supplier', 'title' => $r['name'], 'sub' => 'Proveedor', 'href' => '/suppliers'];
				}
			}

			return $this->ApiResponse($response, $out);
		}
		catch (\Throwable $ex)
		{
			return $this->GenericErrorResponse($response, $ex->getMessage());
		}
	}
}
