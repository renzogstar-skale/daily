<?php

namespace Grocy\Controllers;

use Grocy\Services\ProcessFlowService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class ProcessFlowController extends BaseController
{
	// Bandeja de expedientes: cada documento abierto con su paso actual; el expediente se abre por ?type=&id=
	public function Inbox(Request $request, Response $response, array $args)
	{
		$q = $request->getQueryParams();
		return $this->RenderPage($response, 'process_flow', [
			'types' => ProcessFlowService::GetInstance()->Types(),
			'openType' => preg_match('/^[a-z_]+$/', $q['type'] ?? '') ? $q['type'] : '',
			'openId' => intval($q['id'] ?? 0),
		]);
	}
}
