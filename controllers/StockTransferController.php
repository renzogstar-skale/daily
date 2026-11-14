<?php

namespace Grocy\Controllers;

use Grocy\Services\DatabaseService;
use Grocy\Services\StockTransferService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class StockTransferController extends BaseController
{
	public function Overview(Request $request, Response $response, array $args)
	{
		$params = $request->getQueryParams();
		$status = isset($params['status']) && $params['status'] !== '' ? $params['status'] : null;
		$service = StockTransferService::GetInstance();
		$dbRaw = DatabaseService::GetInstance()->GetDbConnectionRaw();

		// Sucursal del contexto (selector del encabezado) y sucursales permitidas: origen o destino
		$branchId = !empty($params['branch_id']) ? intval($params['branch_id']) : null;
		$allowed = \Grocy\Helpers\DairyAccess::AllowedBranches();
		if ($branchId !== null && $allowed !== null && !in_array($branchId, $allowed, true))
		{
			$branchId = null;
		}
		$visible = function (array $rows) use ($branchId, $allowed) {
			return array_values(array_filter($rows, function ($t) use ($branchId, $allowed) {
				$ends = [intval($t['from_branch_id']), intval($t['to_branch_id'])];
				return ($branchId === null || in_array($branchId, $ends, true)) && ($allowed === null || array_intersect($ends, $allowed));
			}));
		};
		$allTransfers = $visible($service->GetTransfers(null, null, 1000));
		$filteredTransfers = $status ? $visible($service->GetTransfers(null, $status, 1000)) : $allTransfers;
		$inTransit = array_values(array_filter($allTransfers, fn($t) => in_array($t['status'], ['in_transit', 'partially_received'], true)));
		$received = array_values(array_filter($allTransfers, fn($t) => $t['status'] === 'received'));

		$transitCount = count($inTransit);
		$transitValue = array_sum(array_map(fn($t) => floatval($t['total_sent_cost']), $inTransit));

		// Antigüedad: > 48 horas (2 días)
		$nowTs = time();
		$oldTransit = array_values(array_filter($inTransit, function($t) use ($nowTs) {
			$sentTs = strtotime($t['sent_at'] ?? '');
			return $sentTs > 0 && ($nowTs - $sentTs) >= (48 * 3600);
		}));
		$oldTransitCount = count($oldTransit);
		$alertTransfer = !empty($oldTransit) ? $oldTransit[0] : (!empty($inTransit) ? $inTransit[0] : null);

		$curMonth = date('Y-m');
		$monthDiff = array_sum(array_map(function($t) use ($curMonth) {
			if (substr($t['received_at'] ?? '', 0, 7) === $curMonth)
			{
				return floatval($t['total_difference_cost'] ?? 0);
			}
			return 0.0;
		}, $allTransfers));

		$branches = $dbRaw->query("SELECT id, name FROM branches ORDER BY id ASC")->fetchAll(\PDO::FETCH_ASSOC);

		// Pre-cargar detalle de cada transfer para interactividad fluida
		$detailsMap = [];
		foreach ($allTransfers as $t)
		{
			$tid = intval($t['id']);
			try {
				$detailsMap[$tid] = $service->GetTransferDetail($tid);
			} catch (\Throwable $e) {}
		}

		// Transfer activo predeterminado: el de atención prioritaria o el primero en tránsito
		$activeId = isset($params['id']) && isset($detailsMap[intval($params['id'])]) 
			? intval($params['id']) 
			: ($alertTransfer ? intval($alertTransfer['id']) : (isset($allTransfers[0]) ? intval($allTransfers[0]['id']) : 0));
		$activeDetail = $detailsMap[$activeId] ?? null;

		return $this->RenderPage($response, 'transfers', [
			'transfers' => $filteredTransfers,
			'allTransfers' => $allTransfers,
			'inTransit' => $inTransit,
			'received' => $received,
			'transitCount' => $transitCount,
			'transitValue' => $transitValue,
			'oldTransitCount' => $oldTransitCount,
			'monthDiff' => $monthDiff,
			'alertTransfer' => $alertTransfer,
			'branches' => $branches,
			'activeTransfer' => $activeDetail['transfer'] ?? null,
			'activeDetail' => $activeDetail,
			'detailsMapJson' => json_encode($detailsMap),
			'formData' => $service->GetFormData(),
			'selectedStatus' => $status
		]);
	}
}
