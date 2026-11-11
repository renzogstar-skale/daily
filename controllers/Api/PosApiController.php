<?php

namespace Grocy\Controllers\Api;

use Grocy\Controllers\Users\PermissionMissingException;
use Grocy\Controllers\Users\User;
use Grocy\Helpers\DairyAccess;
use Grocy\Services\PosService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/** Punto de venta (PosService): cotización, cobro idempotente, autorizaciones, turnos, arqueo, recibos y consulta */
class PosApiController extends BaseApiController
{
	private function Run(Request $request, Response $response, string $perm, callable $fn): Response
	{
		User::CheckPermission($request, $perm);
		try
		{
			return $this->ApiResponse($response, $fn($request->getMethod() === 'GET' ? [] : ($this->GetParsedAndFilteredRequestBody($request) ?: [])));
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

	private function Branch(Request $request, int $branchId): int
	{
		if ($branchId <= 0 || !DairyAccess::CanSeeBranch($branchId))
		{
			throw new PermissionMissingException($request, 'BRANCH #' . $branchId);
		}
		return $branchId;
	}

	private function SaleBranch(Request $request, int $saleId): void
	{
		$b = \Grocy\Services\DatabaseService::GetInstance()->GetDbConnectionRaw()->query('SELECT branch_id FROM sales WHERE id = ' . $saleId)->fetchColumn();
		if ($b !== false)
		{
			$this->Branch($request, intval($b));
		}
	}

	private function ShiftBranch(Request $request, int $shiftId): void
	{
		$b = \Grocy\Services\DatabaseService::GetInstance()->GetDbConnectionRaw()->query('SELECT branch_id FROM pos_cash_shifts WHERE id = ' . $shiftId)->fetchColumn();
		if ($b !== false)
		{
			$this->Branch($request, intval($b));
		}
	}

	public function Quote(Request $request, Response $response, array $args)
	{
		return $this->Run($request, $response, User::PERMISSION_SALES_POS, fn($b) => PosService::GetInstance()->Quote($b['items'] ?? [], $b['discount'] ?? []));
	}

	public function Checkout(Request $request, Response $response, array $args)
	{
		return $this->Run($request, $response, User::PERMISSION_SALES_POS, function ($b) use ($request) {
			$this->Branch($request, intval($b['branch_id'] ?? 0));
			return PosService::GetInstance()->Checkout($b, GROCY_USER_ID);
		});
	}

	public function Authorize(Request $request, Response $response, array $args)
	{
		$request->getBody()->rewind();
		return $this->Run($request, $response, User::PERMISSION_SALES_POS, fn($b) => PosService::GetInstance()->Authorize((string) ($b['kind'] ?? ''), (string) ($b['username'] ?? ''),
			(string) ((json_decode((string) $request->getBody(), true) ?: [])['password'] ?? ''), (string) ($b['reason'] ?? ''), array_intersect_key($b, array_flip(['branch_id', 'shift_id', 'sale_id', 'amount', 'pct'])), GROCY_USER_ID));
	}

	public function CurrentShift(Request $request, Response $response, array $args)
	{
		return $this->Run($request, $response, User::PERMISSION_SALES_POS, function () use ($request) {
			$q = $request->getQueryParams();
			$branchId = $this->Branch($request, intval($q['branch_id'] ?? 0));
			$svc = PosService::GetInstance();
			$s = $svc->OpenShiftFor($branchId, (string) ($q['register'] ?? 'Caja 1'));
			return ['shift' => $s ? $svc->ShiftSummary(intval($s['id'])) : null, 'registers' => $svc->Registers($branchId)];
		});
	}

	public function OpenShift(Request $request, Response $response, array $args)
	{
		return $this->Run($request, $response, User::PERMISSION_SALES_POS, function ($b) use ($request) {
			return PosService::GetInstance()->OpenShift($this->Branch($request, intval($b['branch_id'] ?? 0)), (string) ($b['register'] ?? ''), floatval($b['opening_amount'] ?? -1),
				(string) ($b['shift_name'] ?? ''), GROCY_USER_ID);
		});
	}

	public function GetShift(Request $request, Response $response, array $args)
	{
		return $this->Run($request, $response, User::PERMISSION_SALES_POS, function () use ($request, $args) {
			$this->ShiftBranch($request, intval($args['id']));
			return PosService::GetInstance()->ShiftSummary(intval($args['id']));
		});
	}

	public function CashMovement(Request $request, Response $response, array $args)
	{
		return $this->Run($request, $response, User::PERMISSION_SALES_POS, function ($b) use ($request, $args) {
			$this->ShiftBranch($request, intval($args['id']));
			return PosService::GetInstance()->CashMovement(intval($args['id']), (string) ($b['kind'] ?? ''), floatval($b['amount'] ?? 0), (string) ($b['reason'] ?? ''), GROCY_USER_ID);
		});
	}

	public function CloseShift(Request $request, Response $response, array $args)
	{
		return $this->Run($request, $response, User::PERMISSION_SALES_POS, function ($b) use ($request, $args) {
			$this->ShiftBranch($request, intval($args['id']));
			if (!isset($b['counted_cash']) || $b['counted_cash'] === '')
			{
				throw new \Exception('Ingrese el efectivo contado en la gaveta');
			}
			return PosService::GetInstance()->CloseShift(intval($args['id']), floatval($b['counted_cash']), is_array($b['breakdown'] ?? null) ? $b['breakdown'] : null,
				(string) ($b['reason'] ?? ''), !empty($b['auth_id']) ? intval($b['auth_id']) : null, GROCY_USER_ID);
		});
	}

	public function Transactions(Request $request, Response $response, array $args)
	{
		return $this->Run($request, $response, User::PERMISSION_SALES_POS, function () use ($request) {
			$q = $request->getQueryParams();
			return PosService::GetInstance()->Transactions($this->Branch($request, intval($q['branch_id'] ?? 0)), $q);
		});
	}

	public function GetSale(Request $request, Response $response, array $args)
	{
		return $this->Run($request, $response, User::PERMISSION_SALES_POS, function () use ($request, $args) {
			$this->SaleBranch($request, intval($args['id']));
			return PosService::GetInstance()->SaleResult(intval($args['id']));
		});
	}

	public function VoidSale(Request $request, Response $response, array $args)
	{
		return $this->Run($request, $response, User::PERMISSION_SALES_POS, function ($b) use ($request, $args) {
			$this->SaleBranch($request, intval($args['id']));
			return PosService::GetInstance()->Void(intval($args['id']), (string) ($b['reason'] ?? ''), !empty($b['auth_id']) ? intval($b['auth_id']) : null, GROCY_USER_ID);
		});
	}

	public function Reprint(Request $request, Response $response, array $args)
	{
		return $this->Run($request, $response, User::PERMISSION_SALES_POS, function ($b) use ($request, $args) {
			$this->SaleBranch($request, intval($args['id']));
			return PosService::GetInstance()->Reprint(intval($args['id']), !empty($b['auth_id']) ? intval($b['auth_id']) : null, GROCY_USER_ID);
		});
	}

	public function Recall(Request $request, Response $response, array $args)
	{
		return $this->Run($request, $response, User::PERMISSION_SALES_POS, function ($b) use ($request, $args) {
			return PosService::GetInstance()->Recall(intval($args['holdId']), $this->Branch($request, intval($b['branch_id'] ?? 0)));
		});
	}

	public function Lookup(Request $request, Response $response, array $args)
	{
		return $this->Run($request, $response, User::PERMISSION_SALES_POS, function () use ($request) {
			$code = trim((string) ($request->getQueryParams()['code'] ?? ''));
			if ($code === '')
			{
				throw new \Exception('Escanee o escriba un código');
			}
			$db = \Grocy\Services\DatabaseService::GetInstance()->GetDbConnectionRaw();
			$st = $db->prepare('SELECT p.id FROM products p LEFT JOIN product_barcodes pb ON pb.product_id = p.id WHERE pb.barcode = :c OR p.sku = :c LIMIT 1');
			$st->execute([':c' => $code]);
			$id = $st->fetchColumn();
			if (!$id)
			{
				throw new \Exception("Código {$code} no encontrado");
			}
			return ['product_id' => intval($id)];
		});
	}

	/* ---------------------------------------------------------------- devoluciones comerciales */

	public function ReturnLookup(Request $request, Response $response, array $args)
	{
		return $this->Run($request, $response, User::PERMISSION_SALES_POS, function () use ($request) {
			$q = $request->getQueryParams();
			$branchId = $this->Branch($request, intval($q['branch_id'] ?? 0));
			$svc = \Grocy\Services\PosReturnService::GetInstance();
			$id = !empty($q['sale_id']) ? intval($q['sale_id']) : $svc->Find((string) ($q['q'] ?? ''), $branchId);
			if (!$id)
			{
				throw new \Exception('No se encontró una venta de esta sucursal con ese ticket, transacción o referencia');
			}
			$e = $svc->Eligibility($id);
			if ($e['sale']['branch_id'] !== $branchId)
			{
				throw new \Exception('La venta pertenece a otra sucursal');
			}
			return $e;
		});
	}

	public function ReturnProcess(Request $request, Response $response, array $args)
	{
		return $this->Run($request, $response, User::PERMISSION_SALES_POS, function ($b) use ($request) {
			$this->SaleBranch($request, intval($b['sale_id'] ?? 0));
			return \Grocy\Services\PosReturnService::GetInstance()->Process($b, GROCY_USER_ID);
		});
	}

	public function ReturnGet(Request $request, Response $response, array $args)
	{
		return $this->Run($request, $response, User::PERMISSION_SALES_POS, function () use ($request, $args) {
			$r = \Grocy\Services\PosReturnService::GetInstance()->Result(intval($args['id']));
			$this->Branch($request, intval($r['return']['branch_id']));
			return $r;
		});
	}

	public function ReturnHistory(Request $request, Response $response, array $args)
	{
		return $this->Run($request, $response, User::PERMISSION_SALES_POS, function () use ($request) {
			$q = $request->getQueryParams();
			$d = fn($v, $def) => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $v) ? $v : $def;
			return \Grocy\Services\PosReturnService::GetInstance()->History($this->Branch($request, intval($q['branch_id'] ?? 0)), $d($q['from'] ?? '', date('Y-m-d', strtotime('-29 days'))), $d($q['to'] ?? '', date('Y-m-d')), (string) ($q['q'] ?? ''));
		});
	}

	public function CreditNote(Request $request, Response $response, array $args)
	{
		return $this->Run($request, $response, User::PERMISSION_SALES_POS, function () use ($request, $args) {
			$n = \Grocy\Services\PosReturnService::GetInstance()->CreditNote(urldecode($args['code']));
			$this->Branch($request, intval($n['branch_id']));
			return $n;
		});
	}

	public function CustomerLoyalty(Request $request, Response $response, array $args)
	{
		return $this->Run($request, $response, User::PERMISSION_SALES_POS, function () use ($args) {
			$id = intval($args['customerId']);
			$crm = \Grocy\Services\CustomerCrmService::GetInstance();
			$row = DatabaseService::GetInstance()->GetDbConnectionRaw()->query('SELECT id, name, is_anonymous FROM customers WHERE id = ' . $id)->fetch(\PDO::FETCH_ASSOC);
			if (!$row || intval($row['is_anonymous']) === 1)
			{
				return ['enabled' => $crm->Settings()['loyalty_enabled'], 'anonymous' => true, 'balance' => 0, 'balance_value' => 0.0];
			}
			$loy = $crm->Loyalty($id);
			return $loy + ['anonymous' => false, 'customer_name' => $row['name']];
		});
	}
}
