<?php

namespace Grocy\Controllers\Api;

use Grocy\Services\ReportBuilderService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Etapa 34: reportes personalizables y programados. Cada conjunto de datos exige su propio permiso y las
 * sucursales del usuario (lo valida ReportBuilderService::Run); las definiciones son del usuario que las crea.
 */
class ReportBuilderApiController extends BaseApiController
{
	private function Run(Response $response, callable $fn)
	{
		try
		{
			return $this->ApiResponse($response, $fn() ?? ['success' => true]);
		}
		catch (\Exception $ex)
		{
			return $this->GenericErrorResponse($response, $ex->getMessage(), in_array($ex->getCode(), [403, 404], true) ? $ex->getCode() : 400);
		}
	}

	private function Svc(): ReportBuilderService
	{
		return ReportBuilderService::GetInstance();
	}

	public function Catalog(Request $request, Response $response, array $args)
	{
		return $this->Run($response, fn() => ['datasets' => $this->Svc()->Catalog(), 'periods' => ReportBuilderService::PERIODS, 'schedules' => ReportBuilderService::SCHEDULES]);
	}

	public function Preview(Request $request, Response $response, array $args)
	{
		$b = $request->getParsedBody() ?? [];
		return $this->Run($response, function () use ($b) {
			$cfg = (array) ($b['config'] ?? []);
			$cfg['limit'] = min(200, intval($cfg['limit'] ?? 200));
			return $this->Svc()->Run(strval($b['dataset'] ?? ''), $cfg, GROCY_USER_ID);
		});
	}

	public function List(Request $request, Response $response, array $args)
	{
		return $this->Run($response, fn() => ['definitions' => $this->Svc()->ListFor(GROCY_USER_ID), 'runs' => $this->Svc()->Runs(GROCY_USER_ID)]);
	}

	public function Save(Request $request, Response $response, array $args)
	{
		$b = $request->getParsedBody() ?? [];
		return $this->Run($response, fn() => ['id' => $this->Svc()->Save($b, GROCY_USER_ID, isset($args['id']) ? intval($args['id']) : null)]);
	}

	public function Delete(Request $request, Response $response, array $args)
	{
		return $this->Run($response, function () use ($args) {
			$this->Svc()->Delete(intval($args['id']), GROCY_USER_ID);
			return ['success' => true];
		});
	}

	public function Execute(Request $request, Response $response, array $args)
	{
		return $this->Run($response, fn() => $this->Svc()->Execute(intval($args['id']), GROCY_USER_ID));
	}

	public function DownloadRun(Request $request, Response $response, array $args)
	{
		try
		{
			$run = $this->Svc()->GetRun(intval($args['id']), GROCY_USER_ID);
			if ($run['status'] !== 'ok')
			{
				throw new \Exception('La ejecución falló: ' . $run['error_message']);
			}
			$response->getBody()->write($run['csv']);
			$file = preg_replace('/[^A-Za-z0-9_-]+/', '_', $run['name']) . '_' . substr($run['row_created_timestamp'], 0, 10) . '.csv';
			return $response->withHeader('Content-Type', 'text/csv; charset=utf-8')->withHeader('Content-Disposition', 'attachment; filename="' . $file . '"');
		}
		catch (\Exception $ex)
		{
			return $this->GenericErrorResponse($response, $ex->getMessage(), in_array($ex->getCode(), [403, 404], true) ? $ex->getCode() : 400);
		}
	}
}
