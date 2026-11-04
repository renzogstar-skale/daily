<?php

namespace Grocy\Controllers;

use Grocy\Services\DatabaseService;
use Grocy\Services\KardexService;
use Grocy\Services\MovementLedgerService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class KardexController extends BaseController
{
	public function Overview(Request $request, Response $response, array $args)
	{
		$q = $request->getQueryParams();
		$productId = intval($q['product_id'] ?? 0);
		$branchId = !empty($q['branch_id']) ? intval($q['branch_id']) : null;
		$stockId = !empty($q['stock_id']) ? $q['stock_id'] : null;
		$from = !empty($q['from']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $q['from']) ? $q['from'] : '2026-08-01';
		$to = !empty($q['to']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $q['to']) ? $q['to'] : date('Y-m-d');

		$dbRaw = DatabaseService::GetInstance()->GetDbConnectionRaw();
		$products = $dbRaw->query('
			SELECT p.id, p.name, p.active, p.sku FROM products p
			WHERE p.active = 1 OR EXISTS (SELECT 1 FROM stock_log l WHERE l.product_id = p.id)
			ORDER BY p.active DESC, p.name
		')->fetchAll(\PDO::FETCH_ASSOC);

		$anomaliesRaw = MovementLedgerService::GetInstance()->Anomalies($branchId, 30);
		
		// Enrich anomalies with product, location, branch, and stock_log details
		$enrichedItems = [];
		$uniqueMovementsMap = [];
		$logsById = [];
		$logIds = array_map('intval', array_column($anomaliesRaw['items'], 'stock_log_id'));
		foreach (array_chunk(array_values(array_unique($logIds)), 500) as $idChunk)
		{
			$logRows = $dbRaw->query('
				SELECT l.*, loc.name AS location_name, b.name AS branch_name, p.sku, qu.name AS qu_name
				FROM stock_log l
				LEFT JOIN products p ON p.id = l.product_id
				LEFT JOIN quantity_units qu ON qu.id = p.qu_id_stock
				LEFT JOIN locations loc ON loc.id = l.location_id
				LEFT JOIN branches b ON b.id = loc.branch_id
				WHERE l.id IN (' . implode(',', $idChunk) . ')
			')->fetchAll(\PDO::FETCH_ASSOC);
			foreach ($logRows as $logRow)
			{
				$logsById[intval($logRow['id'])] = $logRow;
			}
		}
		$documents = KardexService::GetInstance()->ResolveDocuments(array_values(array_unique(array_filter(array_column($logsById, 'transaction_id')))));
		$candidatesByProduct = [];

		foreach ($anomaliesRaw['items'] as $item)
		{
			$logId = intval($item['stock_log_id']);
			$uniqueMovementsMap[$logId] = true;

			$logData = $logsById[$logId] ?? [];

			$type = $item['type'];
			$label = match ($type) {
				'waste_without_case' => 'Merma sin caso',
				'duplicate' => 'Posible duplicado',
				'off_hours' => 'Fuera de horario',
				'large_correction' => 'Corrección alta',
				'undo_heavy' => 'Anulación reiterada',
				default => ucfirst($type)
			};
			$longLabel = match ($type) {
				'waste_without_case' => 'Merma sin caso vinculado',
				'duplicate' => 'Posibles duplicados',
				'off_hours' => 'Fuera del horario configurado',
				'large_correction' => 'Corrección de alto valor',
				default => ucfirst($type)
			};
			$badgeClass = match ($type) {
				'waste_without_case' => 'kx-badge-amber',
				'duplicate' => 'kx-badge-red',
				'off_hours' => 'kx-badge-slate',
				default => 'kx-badge-neutral'
			};
			$description = match ($type) {
				'waste_without_case' => 'Consumo registrado como vencido o dañado sin vínculo visible a un caso de merma. Revisar causal, autorización y evidencia.',
				'duplicate' => 'Mismo producto, tipo y cantidad registrado dos veces en menos de 2 minutos. Confirmar que no sea un doble registro antes de actuar.',
				'off_hours' => 'Movimiento registrado fuera del horario de tienda configurado (06:00–22:00 h). Revisar turno y autorización.',
				'large_correction' => 'Corrección de inventario por importe inusualmente alto. Requiere verificación de conteo físico.',
				default => $item['detail']
			};

			$pName = $item['product_name'] ?? '';
			$productImage = match (true) {
				str_contains(strtolower($pName), 'leche entera') => '/img/control_center/leche_entera_galon.jpg',
				str_contains(strtolower($pName), 'leche descremada') => '/img/control_center/leche_descremada.jpg',
				str_contains(strtolower($pName), 'leche') => '/img/control_center/card_leche.jpg',
				str_contains(strtolower($pName), 'tomate') => '/img/control_center/tomate.jpg',
				str_contains(strtolower($pName), 'piment') => '/img/control_center/pimenton.jpg',
				str_contains(strtolower($pName), 'pepino') => '/img/control_center/pepino.jpg',
				str_contains(strtolower($pName), 'yogur') => '/img/control_center/yogur_griego.jpg',
				str_contains(strtolower($pName), 'huevo') => '/img/control_center/huevos_grandes.jpg',
				str_contains(strtolower($pName), 'queso') => '/img/control_center/queso_mozzarella.jpg',
				default => '/img/control_center/leche_entera_galon.jpg'
			};

			$txId = $logData['transaction_id'] ?? null;
			$document = $txId ? ($documents[$txId] ?? null) : null;

			// Candidate waste cases for linking if waste_without_case
			$candidateWasteCases = [];
			if ($type === 'waste_without_case' && !empty($item['product_id']))
			{
				if (!isset($candidatesByProduct[$item['product_id']]))
				{
					$cwStmt = $dbRaw->prepare("SELECT id, IFNULL(case_number, 'MRM-' || id) AS case_number, created_timestamp, total_cost, amount FROM waste_records WHERE product_id = :pid AND (transaction_id IS NULL OR transaction_id = '') ORDER BY id DESC LIMIT 5");
					$cwStmt->execute([':pid' => $item['product_id']]);
					$candidatesByProduct[$item['product_id']] = $cwStmt->fetchAll(\PDO::FETCH_ASSOC);
				}
				$candidateWasteCases = $candidatesByProduct[$item['product_id']];
			}

			$enrichedItems[] = array_merge($item, [
				'label' => $label,
				'long_label' => $longLabel,
				'badge_class' => $badgeClass,
				'description' => $description,
				'product_image' => $productImage,
				'sku' => $logData['sku'] ?? 'DAIRY-MLK-001',
				'qu_name' => $logData['qu_name'] ?? 'Galón',
				'stock_id' => $logData['stock_id'] ?? null,
				'best_before_date' => $logData['best_before_date'] ?? null,
				'location_name' => $logData['location_name'] ?? 'Góndola Refrigerada Dairy',
				'branch_name' => $logData['branch_name'] ?? 'Sin sucursal',
				'transaction_id' => $txId,
				'document' => $document,
				'candidate_waste_cases' => $candidateWasteCases,
				'note' => $logData['note'] ?? null,
				'amount' => floatval($logData['amount'] ?? $item['amount']),
				'price' => floatval($logData['price'] ?? 0),
				'formatted_date' => substr($item['timestamp'], 0, 16)
			]);
		}

		$anomaliesData = [
			'count' => count($enrichedItems),
			'unique_movements_count' => count($uniqueMovementsMap),
			'counts' => [
				'waste_without_case' => $anomaliesRaw['counts']['waste_without_case'] ?? 0,
				'duplicate' => $anomaliesRaw['counts']['duplicate'] ?? 0,
				'off_hours' => $anomaliesRaw['counts']['off_hours'] ?? 0,
				'large_correction' => $anomaliesRaw['counts']['large_correction'] ?? 0,
				'undo_heavy' => $anomaliesRaw['counts']['undo_heavy'] ?? 0,
			],
			'items' => $enrichedItems
		];

		$kardex = null;
		if ($productId > 0)
		{
			$kardex = KardexService::GetInstance()->GetKardex($productId, $branchId, $stockId, $from, $to);
		}

		return $this->RenderPage($response, 'kardex', [
			'products' => $products,
			'branches' => $dbRaw->query('SELECT id, name FROM branches ORDER BY id')->fetchAll(\PDO::FETCH_ASSOC),
			'kardex' => $kardex,
			'anomalies' => $anomaliesData,
			'anomaliesJson' => json_encode($enrichedItems, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP),
			'filters' => [
				'product_id' => $productId,
				'branch_id' => $branchId,
				'stock_id' => $stockId,
				'from' => $from,
				'to' => $to
			]
		]);
	}
}
