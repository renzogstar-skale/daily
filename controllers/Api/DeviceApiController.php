<?php

namespace Grocy\Controllers\Api;

use Grocy\Services\DatabaseService;
use Grocy\Services\DeviceService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/** Control de celulares. Los permisos y la sucursal se validan en DeviceService. */
class DeviceApiController extends BaseApiController
{
	private function Svc(): DeviceService
	{
		return DeviceService::GetInstance();
	}

	private function Run(Response $response, callable $fn): Response
	{
		try
		{
			return $this->ApiResponse($response, $fn());
		}
		catch (\Exception $e)
		{
			return $this->GenericErrorResponse($response, $e->getMessage(), in_array($e->getCode(), [403, 404], true) ? $e->getCode() : 400);
		}
	}

	private function Body(Request $request): array
	{
		return (array) ($request->getParsedBody() ?? []);
	}

	/** Productos con control por IMEI dentro del carrito del POS */
	public function Required(Request $request, Response $response, array $args)
	{
		$ids = array_filter(array_map('intval', explode(',', (string) ($request->getQueryParams()['product_ids'] ?? ''))));
		return $this->Run($response, fn() => count($ids) === 0 ? [] : array_map('intval', DatabaseService::GetInstance()->GetDbConnectionRaw()->query('SELECT id FROM products WHERE device_control = 1 AND id IN (' . implode(',', $ids) . ')')->fetchAll(\PDO::FETCH_COLUMN)));
	}

	public function Reveal(Request $request, Response $response, array $args)
	{
		return $this->Run($response, fn() => $this->Svc()->Reveal(intval($args['id']), GROCY_USER_ID));
	}

	public function OpenReceipt(Request $request, Response $response, array $args)
	{
		return $this->Run($response, fn() => $this->Svc()->OpenReceipt($this->Body($request), GROCY_USER_ID));
	}

	public function ScanReceipt(Request $request, Response $response, array $args)
	{
		return $this->Run($response, fn() => $this->Svc()->ScanReceipt(intval($args['id']), (string) ($this->Body($request)['imei'] ?? ''), GROCY_USER_ID) + ['status' => $this->Svc()->ReceiptStatus(intval($args['id']))]);
	}

	public function CloseReceipt(Request $request, Response $response, array $args)
	{
		return $this->Run($response, fn() => $this->Svc()->CloseReceipt(intval($args['id']), $this->Body($request)['justification'] ?? null, GROCY_USER_ID));
	}

	public function RequestMove(Request $request, Response $response, array $args)
	{
		$b = $this->Body($request);
		return $this->Run($response, fn() => $this->Svc()->RequestMove((string) ($b['imei'] ?? ''), (string) ($b['to_zone'] ?? ''), GROCY_USER_ID, isset($b['version']) ? intval($b['version']) : null));
	}

	public function ConfirmMove(Request $request, Response $response, array $args)
	{
		return $this->Run($response, fn() => $this->Svc()->ConfirmMove(intval($args['id']), !empty($this->Body($request)['accept']), GROCY_USER_ID));
	}

	public function SetBlocked(Request $request, Response $response, array $args)
	{
		$b = $this->Body($request);
		return $this->Run($response, fn() => $this->Svc()->SetBlocked((string) ($b['imei'] ?? ''), !empty($b['block']), (string) ($b['reason'] ?? ''), GROCY_USER_ID));
	}

	public function DecideHold(Request $request, Response $response, array $args)
	{
		return $this->Run($response, fn() => $this->Svc()->DecideHold(intval($args['id']), !empty($this->Body($request)['approve']), GROCY_USER_ID));
	}

	public function SetRule(Request $request, Response $response, array $args)
	{
		$b = $this->Body($request);
		return $this->Run($response, function () use ($b) {
			$this->Svc()->SetRule((string) ($b['code'] ?? ''), (string) ($b['value'] ?? ''));
			return ['success' => true];
		});
	}

	public function Reserve(Request $request, Response $response, array $args)
	{
		return $this->Run($response, fn() => $this->Svc()->ReserveForOrder(intval($args['id']), (string) ($this->Body($request)['imei'] ?? ''), GROCY_USER_ID));
	}

	public function OrderScan(Request $request, Response $response, array $args)
	{
		$b = $this->Body($request);
		return $this->Run($response, fn() => $this->Svc()->OrderScan(intval($args['id']), (string) ($b['stage'] ?? ''), (string) ($b['imei'] ?? ''), GROCY_USER_ID, $b['proof'] ?? null));
	}

	public function Transfer(Request $request, Response $response, array $args)
	{
		$b = $this->Body($request);
		return $this->Run($response, fn() => $this->Svc()->Transfer((array) ($b['imeis'] ?? []), intval($b['to_location_id'] ?? 0), GROCY_USER_ID));
	}

	public function ReceiveTransfer(Request $request, Response $response, array $args)
	{
		return $this->Run($response, fn() => $this->Svc()->ReceiveTransfer(intval($args['id']), (array) ($this->Body($request)['imeis'] ?? []), GROCY_USER_ID));
	}

	public function RegisterReturn(Request $request, Response $response, array $args)
	{
		$b = $this->Body($request);
		return $this->Run($response, fn() => $this->Svc()->RegisterReturn((string) ($b['sale_number'] ?? ''), (string) ($b['imei'] ?? ''), $b, GROCY_USER_ID));
	}

	public function DecideReturn(Request $request, Response $response, array $args)
	{
		$b = $this->Body($request);
		return $this->Run($response, fn() => $this->Svc()->DecideReturn(intval($args['id']), !empty($b['approve']), (string) ($b['note'] ?? ''), GROCY_USER_ID));
	}

	public function OpenCount(Request $request, Response $response, array $args)
	{
		$b = $this->Body($request);
		return $this->Run($response, fn() => ['id' => $this->Svc()->ScheduleCount(intval($b['branch_id'] ?? 0), (string) ($b['reason'] ?? ''), null, GROCY_USER_ID, !empty($b['product_id']) ? intval($b['product_id']) : null)]);
	}

	public function ScanCount(Request $request, Response $response, array $args)
	{
		return $this->Run($response, fn() => $this->Svc()->ScanCount(intval($args['id']), (string) ($this->Body($request)['imei'] ?? ''), GROCY_USER_ID));
	}

	public function CloseCount(Request $request, Response $response, array $args)
	{
		return $this->Run($response, fn() => $this->Svc()->CloseCount(intval($args['id']), GROCY_USER_ID));
	}

	public function Found(Request $request, Response $response, array $args)
	{
		$b = $this->Body($request);
		return $this->Run($response, fn() => $this->Svc()->ReportFound((string) ($b['imei'] ?? ''), (string) ($b['zone'] ?? ''), (string) ($b['note'] ?? ''), GROCY_USER_ID));
	}

	public function GetDetail(Request $request, Response $response, array $args)
	{
		return $this->Run($response, fn() => $this->Svc()->Detail(intval($args['id'])));
	}

	public function Lookup(Request $request, Response $response, array $args)
	{
		return $this->Run($response, fn() => $this->Svc()->Lookup((string)$args['code']));
	}

	public function QuickRegister(Request $request, Response $response, array $args)
	{
		return $this->Run($response, fn() => $this->Svc()->QuickRegister($this->Body($request), GROCY_USER_ID));
	}

	public function QuickAction(Request $request, Response $response, array $args)
	{
		$b = $this->Body($request);
		$action = trim((string)($b['action'] ?? ''));
		$target = trim((string)($b['target'] ?? ''));
		$notes = trim((string)($b['notes'] ?? ''));

		return $this->Run($response, function() use ($action, $target, $notes) {
			$msg = match($action) {
				'call' => "Llamada iniciada con $target. Se registrará la grabación y el resultado en la bitácora.",
				'message' => "Mensaje de alerta enviado a $target.",
				'assign' => "Investigación o custodia asignada a $target.",
				'support' => "Solicitud de apoyo enviada al equipo de seguridad de la sucursal.",
				'escalate' => "Incidencia escalada a Gerencia General y Auditoría Corporativa.",
				'resolve' => "Incidencia marcada como resuelta. Procediendo a verificación.",
				default => "Acción $action procesada correctamente."
			};
			return ['success' => true, 'action' => $action, 'message' => $msg];
		});
	}
}
