<?php

namespace Grocy\Controllers\Api;

use Grocy\Controllers\Users\User;
use Grocy\Helpers\DairyAccess;
use Grocy\Services\CustomerOrderService;
use Grocy\Services\DairyExportService;
use Grocy\Services\ImportService;
use Grocy\Services\LossPreventionService;
use Grocy\Services\ScanService;
use Grocy\Services\WasteService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Etapa 21: operaciones con escáner, lotes inmovilizados, importación/exportación de maestros y exportación de mermas.
 * Cada acción valida permiso (RBAC) y sucursal (DairyAccess); la lógica vive en los servicios.
 */
class OperationsApiController extends BaseApiController
{
	private function Run(Response $response, callable $fn)
	{
		try
		{
			return $this->ApiResponse($response, $fn() ?? ['success' => true]);
		}
		catch (\Exception $ex)
		{
			return $this->GenericErrorResponse($response, $ex->getMessage());
		}
	}

	private function Body(Request $request): array
	{
		return $request->getParsedBody() ?? [];
	}

	private function Branch(Request $request): ?int
	{
		$q = $request->getQueryParams();
		$b = !empty($q['branch_id']) ? intval($q['branch_id']) : null;
		if ($b !== null && !DairyAccess::CanSeeBranch($b))
		{
			throw new \Exception('No tiene acceso a esta sucursal');
		}
		return $b;
	}

	private function File(Response $response, array $file, string $name)
	{
		$response->getBody()->write($file['body']);
		return $response->withHeader('Content-Type', $file['type'])
			->withHeader('Content-Disposition', 'attachment; filename="' . $name . '-' . date('Ymd-His') . '.' . $file['ext'] . '"')
			->withHeader('Cache-Control', 'no-store');
	}

	/* ---------------- Escáner ---------------- */

	public function Resolve(Request $request, Response $response, array $args)
	{
		User::CheckPermission($request, User::PERMISSION_STOCK);
		return $this->Run($response, fn() => ScanService::GetInstance()->Resolve((string) ($request->getQueryParams()['code'] ?? ''), $this->Branch($request)));
	}

	public function FormData(Request $request, Response $response, array $args)
	{
		User::CheckPermission($request, User::PERMISSION_SCAN_OPERATE);
		return $this->Run($response, fn() => ScanService::GetInstance()->FormData($this->Branch($request)));
	}

	public function Sessions(Request $request, Response $response, array $args)
	{
		User::CheckPermission($request, User::PERMISSION_SCAN_OPERATE);
		return $this->Run($response, fn() => ScanService::GetInstance()->ListSessions($this->Branch($request), GROCY_USER_ID));
	}

	public function GetSession(Request $request, Response $response, array $args)
	{
		User::CheckPermission($request, User::PERMISSION_SCAN_OPERATE);
		return $this->Run($response, fn() => ScanService::GetInstance()->GetSession(intval($args['id'])));
	}

	public function OpenSession(Request $request, Response $response, array $args)
	{
		User::CheckPermission($request, User::PERMISSION_SCAN_OPERATE);
		$body = $this->Body($request);
		$perm = match ($body['type'] ?? '')
		{
			'pick' => User::PERMISSION_ORDERS_PICK,
			'count' => User::PERMISSION_STOCK_INVENTORY,
			'move' => User::PERMISSION_STOCK_TRANSFER,
			'receive' => User::PERMISSION_PROCUREMENT_RECEIVE,
			default => User::PERMISSION_SCAN_OPERATE,
		};
		User::CheckPermission($request, $perm);
		return $this->Run($response, fn() => ScanService::GetInstance()->OpenSession($body, GROCY_USER_ID));
	}

	public function AddLine(Request $request, Response $response, array $args)
	{
		User::CheckPermission($request, User::PERMISSION_SCAN_OPERATE);
		return $this->Run($response, fn() => ScanService::GetInstance()->AddLine(intval($args['id']), $this->Body($request), GROCY_USER_ID));
	}

	public function VoidLine(Request $request, Response $response, array $args)
	{
		User::CheckPermission($request, User::PERMISSION_SCAN_OPERATE);
		return $this->Run($response, fn() => ScanService::GetInstance()->VoidLine(intval($args['lineId']), GROCY_USER_ID));
	}

	public function CloseSession(Request $request, Response $response, array $args)
	{
		User::CheckPermission($request, User::PERMISSION_SCAN_OPERATE);
		return $this->Run($response, fn() => ScanService::GetInstance()->CloseSession(intval($args['id']), $this->Body($request), GROCY_USER_ID));
	}

	public function CancelSession(Request $request, Response $response, array $args)
	{
		User::CheckPermission($request, User::PERMISSION_SCAN_OPERATE);
		return $this->Run($response, fn() => ScanService::GetInstance()->CancelSession(intval($args['id']), GROCY_USER_ID, (string) ($this->Body($request)['reason'] ?? '')));
	}

	/* ---------------- Rotación ---------------- */

	public function StagnantLots(Request $request, Response $response, array $args)
	{
		User::CheckPermission($request, User::PERMISSION_STOCK);
		$q = $request->getQueryParams();
		return $this->Run($response, fn() => LossPreventionService::GetInstance()->StagnantLots($this->Branch($request), !empty($q['days']) ? max(7, intval($q['days'])) : null));
	}

	/* ---------------- Importación / exportación de maestros ---------------- */

	public function ImportRun(Request $request, Response $response, array $args)
	{
		User::CheckPermission($request, User::PERMISSION_DATA_IMPORT);
		$b = $this->Body($request);
		return $this->Run($response, fn() => ImportService::GetInstance()->Run($args['entity'], (string) ($b['content'] ?? ''), !empty($b['dry_run']), (string) ($b['mode'] ?? 'create'), $b['file_name'] ?? null, GROCY_USER_ID));
	}

	public function ImportJobs(Request $request, Response $response, array $args)
	{
		User::CheckPermission($request, User::PERMISSION_DATA_IMPORT);
		return $this->Run($response, fn() => ImportService::GetInstance()->Jobs());
	}

	public function Template(Request $request, Response $response, array $args)
	{
		User::CheckPermission($request, User::PERMISSION_DATA_IMPORT);
		try
		{
			$cols = ImportService::GetInstance()->Template($args['entity']);
			$fmt = $request->getQueryParams()['format'] ?? 'csv';
			$rows = ($request->getQueryParams()['with_data'] ?? '') === '1' ? ImportService::GetInstance()->Export($args['entity']) : [];
			return $this->File($response, DairyExportService::GetInstance()->Render($cols, $rows, $fmt, $args['entity']), 'dairy-' . $args['entity']);
		}
		catch (\Exception $ex)
		{
			return $this->GenericErrorResponse($response, $ex->getMessage());
		}
	}

	public function History(Request $request, Response $response, array $args)
	{
		User::CheckPermission($request, User::PERMISSION_STOCK);
		return $this->Run($response, fn() => ImportService::GetInstance()->History($args['table'], intval($args['id'])));
	}

	/* ---------------- Trazabilidad ---------------- */

	public function Trace(Request $request, Response $response, array $args)
	{
		User::CheckPermission($request, User::PERMISSION_STOCK);
		$q = $request->getQueryParams();
		return $this->Run($response, fn() => \Grocy\Services\TraceabilityService::GetInstance()->Trace((string) ($q['q'] ?? ''), !empty($q['product_id']) ? intval($q['product_id']) : null));
	}

	/* ---------------- Pedidos de cliente ---------------- */

	public function Orders(Request $request, Response $response, array $args)
	{
		if (!\Grocy\Helpers\DairyAccess::HasAny([User::PERMISSION_ORDERS_MANAGE, User::PERMISSION_ORDERS_PICK]))
		{
			User::CheckPermission($request, User::PERMISSION_ORDERS_MANAGE);
		}
		$q = $request->getQueryParams();
		return $this->Run($response, fn() => CustomerOrderService::GetInstance()->List($this->Branch($request), $q['status'] ?? null));
	}

	public function GetOrder(Request $request, Response $response, array $args)
	{
		if (!\Grocy\Helpers\DairyAccess::HasAny([User::PERMISSION_ORDERS_MANAGE, User::PERMISSION_ORDERS_PICK]))
		{
			User::CheckPermission($request, User::PERMISSION_ORDERS_MANAGE);
		}
		return $this->Run($response, fn() => CustomerOrderService::GetInstance()->Get(intval($args['id'])));
	}

	public function CreateOrder(Request $request, Response $response, array $args)
	{
		User::CheckPermission($request, User::PERMISSION_ORDERS_MANAGE);
		return $this->Run($response, fn() => CustomerOrderService::GetInstance()->Create($this->Body($request), GROCY_USER_ID));
	}

	public function OrderAction(Request $request, Response $response, array $args)
	{
		$id = intval($args['id']);
		$b = $this->Body($request);
		$svc = CustomerOrderService::GetInstance();
		switch ($args['action'])
		{
			case 'confirm':
				User::CheckPermission($request, User::PERMISSION_ORDERS_MANAGE);
				return $this->Run($response, fn() => $svc->Confirm($id, GROCY_USER_ID));
			case 'pick-fefo':
				User::CheckPermission($request, User::PERMISSION_ORDERS_PICK);
				return $this->Run($response, fn() => $svc->QuickPickFefo($id, GROCY_USER_ID));
			case 'pack':
				User::CheckPermission($request, User::PERMISSION_ORDERS_PICK);
				return $this->Run($response, fn() => $svc->Pack($id, intval($b['packages'] ?? 0), GROCY_USER_ID));
			case 'dispatch':
				User::CheckPermission($request, User::PERMISSION_ORDERS_MANAGE);
				User::CheckPermission($request, User::PERMISSION_SALES_POS);
				return $this->Run($response, fn() => $svc->Dispatch($id, GROCY_USER_ID));
			case 'cancel':
				User::CheckPermission($request, User::PERMISSION_ORDERS_MANAGE);
				return $this->Run($response, fn() => $svc->Cancel($id, (string) ($b['reason'] ?? ''), GROCY_USER_ID));
		}
		return $this->GenericErrorResponse($response, 'Acción no válida');
	}

	/* ---------------- Exportación de mermas ---------------- */

	public function WasteExport(Request $request, Response $response, array $args)
	{
		User::CheckPermission($request, User::PERMISSION_WASTE_REPORT);
		try
		{
			$q = $request->getQueryParams();
			$branch = $this->Branch($request);
			$rows = WasteService::GetInstance()->GetWasteRecords($branch, $q['status'] ?? null, null, null, $q['category'] ?? null, $q['from'] ?? null, $q['to'] ?? null, !empty($q['reason_id']) ? intval($q['reason_id']) : null);
			$rows = array_values(array_filter($rows, fn($r) => DairyAccess::CanSeeBranch(intval($r->branch_id))
				&& (empty($q['product_id']) || intval($r->product_id) === intval($q['product_id']))
				&& (empty($q['user_id']) || intval($r->reported_by_user_id) === intval($q['user_id']))));
			$cols = ['case_number' => 'Caso', 'created_timestamp' => 'Fecha', 'branch_name' => 'Sucursal', 'location_name' => 'Ubicación', 'product_name' => 'Producto', 'product_sku' => 'SKU',
				'batch_number' => 'Lote', 'amount' => 'Cantidad', 'qu_name' => 'Unidad', 'reason_code' => 'Causal', 'reason_name' => 'Descripción causal', 'reason_category' => 'Categoría',
				'unit_cost' => 'Costo unitario', 'total_cost' => 'Costo total', 'status' => 'Estado', 'reported_by_username' => 'Reportó', 'approved_by_username' => 'Aprobó',
				'approved_timestamp' => 'Fecha aprobación', 'transaction_id' => 'Transacción stock', 'notes' => 'Observaciones'];
			return $this->File($response, DairyExportService::GetInstance()->Render($cols, $rows, $q['format'] ?? 'csv', 'Mermas'), 'dairy-mermas');
		}
		catch (\Exception $ex)
		{
			return $this->GenericErrorResponse($response, $ex->getMessage());
		}
	}
}
