<?php

namespace Grocy\Controllers\Api;

use Grocy\Controllers\Users\PermissionMissingException;
use Grocy\Controllers\Users\User;
use Grocy\Helpers\DairyAccess;
use Grocy\Services\InventoryOperationsService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/** Inventario y Almacenes (InventoryOperationsService): posición, ubicaciones, kardex, anomalías, reposición, reservas, auditoría, escaneo y ajustes */
class InventoryOpsApiController extends BaseApiController
{
	private function Run(Request $request, Response $response, callable $fn, array $permissions = [User::PERMISSION_STOCK]): Response
	{
		if (!DairyAccess::HasAny($permissions))
		{
			throw new PermissionMissingException($request, $permissions[0]);
		}
		try
		{
			return $this->ApiResponse($response, $fn($request->getMethod() === 'GET' ? $request->getQueryParams() : ($this->GetParsedAndFilteredRequestBody($request) ?: [])));
		}
		catch (PermissionMissingException $e)
		{
			throw $e;
		}
		catch (\Throwable $e)
		{
			return $this->GenericErrorResponse($response, $e->getMessage(), 400);
		}
	}

	private static function Svc(): InventoryOperationsService
	{
		return InventoryOperationsService::GetInstance();
	}

	public function Summary(Request $request, Response $response, array $args): Response
	{
		return $this->Run($request, $response, fn($q) => self::Svc()->Summary($q['branch_id'] ?? null));
	}

	public function Positions(Request $request, Response $response, array $args): Response
	{
		return $this->Run($request, $response, fn($q) => self::Svc()->Positions($q));
	}

	public function Locations(Request $request, Response $response, array $args): Response
	{
		return $this->Run($request, $response, fn($q) => self::Svc()->Locations($q['branch_id'] ?? null));
	}

	public function Kardex(Request $request, Response $response, array $args): Response
	{
		return $this->Run($request, $response, fn($q) => self::Svc()->Kardex(intval($q['product_id'] ?? 0), $q['branch_id'] ?? null, $q['from'] ?? null, $q['to'] ?? null, intval($q['limit'] ?? 300)));
	}

	public function Anomalies(Request $request, Response $response, array $args): Response
	{
		return $this->Run($request, $response, fn($q) => self::Svc()->Anomalies($q['branch_id'] ?? null));
	}

	public function Transfers(Request $request, Response $response, array $args): Response
	{
		return $this->Run($request, $response, fn($q) => self::Svc()->Transfers($q['branch_id'] ?? null));
	}

	public function Aging(Request $request, Response $response, array $args): Response
	{
		return $this->Run($request, $response, fn($q) => self::Svc()->Aging($q['branch_id'] ?? null));
	}

	public function Coverage(Request $request, Response $response, array $args): Response
	{
		return $this->Run($request, $response, fn($q) => self::Svc()->Coverage($q['branch_id'] ?? null, intval($q['limit'] ?? 100), strval($q['sort'] ?? 'risk')));
	}

	public function Lots(Request $request, Response $response, array $args): Response
	{
		return $this->Run($request, $response, fn($q) => self::Svc()->Lots($q['branch_id'] ?? null, intval($q['days'] ?? 30), strval($q['q'] ?? '')));
	}

	public function Replenishment(Request $request, Response $response, array $args): Response
	{
		return $this->Run($request, $response, fn($q) => self::Svc()->Replenishment($q['branch_id'] ?? null, intval($q['limit'] ?? 200)));
	}

	public function CreateReplenishment(Request $request, Response $response, array $args): Response
	{
		return $this->Run($request, $response, fn($d) => self::Svc()->CreateReplenishmentTasks(intval($d['branch_id'] ?? 0), is_array($d['items'] ?? null) ? $d['items'] : []), [User::PERMISSION_STOCK_TRANSFER]);
	}

	public function ConfirmReplenishment(Request $request, Response $response, array $args): Response
	{
		return $this->Run($request, $response, fn($d) => self::Svc()->ConfirmReplenishment(intval($args['taskId']), $d['amount'] ?? null), [User::PERMISSION_STOCK_TRANSFER]);
	}

	public function CancelReplenishment(Request $request, Response $response, array $args): Response
	{
		return $this->Run($request, $response, fn($d) => self::Svc()->CancelReplenishment(intval($args['taskId']), strval($d['reason'] ?? '')), [User::PERMISSION_STOCK_TRANSFER]);
	}

	public function Reservations(Request $request, Response $response, array $args): Response
	{
		return $this->Run($request, $response, fn($q) => self::Svc()->Reservations($q['branch_id'] ?? null));
	}

	public function Audit(Request $request, Response $response, array $args): Response
	{
		return $this->Run($request, $response, fn($q) => self::Svc()->Audit($q));
	}

	public function Scan(Request $request, Response $response, array $args): Response
	{
		return $this->Run($request, $response, fn($q) => self::Svc()->Scan(rawurldecode(strval($args['code'] ?? '')), $q['branch_id'] ?? null), [User::PERMISSION_STOCK, User::PERMISSION_SCAN_OPERATE]);
	}

	public function Adjustments(Request $request, Response $response, array $args): Response
	{
		return $this->Run($request, $response, fn($q) => self::Svc()->Adjustments($q));
	}

	public function RequestAdjustment(Request $request, Response $response, array $args): Response
	{
		return $this->Run($request, $response, function ($d) {
			if (!isset($d['product_id'], $d['new_amount']) || !is_numeric($d['new_amount']))
			{
				throw new \Exception('Producto y cantidad contada son obligatorios');
			}
			$r = self::Svc()->RequestAdjustment(intval($d['product_id']), floatval($d['new_amount']), !empty($d['location_id']) ? intval($d['location_id']) : null,
				['note' => $d['note'] ?? null, 'source' => in_array($d['source'] ?? '', ['scan', 'count_form'], true) ? $d['source'] : 'manual', 'request_key' => $d['request_key'] ?? null,
					'best_before_date' => $d['best_before_date'] ?? null, 'price' => $d['price'] ?? null]);
			return $r + ['pending_approval' => !empty($r['adjustment'])];
		}, [User::PERMISSION_STOCK_INVENTORY]);
	}

	public function ApproveAdjustment(Request $request, Response $response, array $args): Response
	{
		return $this->Run($request, $response, fn($d) => self::Svc()->ApproveAdjustment(intval($args['adjustmentId']), $d['notes'] ?? null), [User::PERMISSION_INVENTORY_ADJUST_APPROVE]);
	}

	public function RejectAdjustment(Request $request, Response $response, array $args): Response
	{
		return $this->Run($request, $response, fn($d) => self::Svc()->RejectAdjustment(intval($args['adjustmentId']), strval($d['reason'] ?? '')), [User::PERMISSION_INVENTORY_ADJUST_APPROVE]);
	}
}
