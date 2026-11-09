<?php

namespace Grocy\Controllers\Api;

use Grocy\Services\ApplicationService;
use Grocy\Services\DatabaseService;
use Grocy\Services\LocalizationService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class SystemApiController extends BaseApiController
{
	public function GetConfig(Request $request, Response $response, array $args)
	{
		try
		{
			$constants = get_defined_constants();

			// Some GROCY_* constants are not really config settings and therefore should not be exposed
			unset(
				$constants['GROCY_AUTHENTICATED'],
				$constants['GROCY_DATAPATH'],
				$constants['GROCY_IS_EMBEDDED_INSTALL'],
				$constants['GROCY_REVERSE_PROXY_AUTH_HEADER'],
				$constants['GROCY_REVERSE_PROXY_AUTH_USE_ENV'],
				$constants['GROCY_LDAP_ADDRESS'],
				$constants['GROCY_LDAP_BASE_DN'],
				$constants['GROCY_LDAP_BIND_DN'],
				$constants['GROCY_LDAP_BIND_PW'],
				$constants['GROCY_LDAP_USER_FILTER'],
				$constants['GROCY_LDAP_UID_ATTR'],
				$constants['GROCY_USER_USERNAME'],
				$constants['GROCY_USER_PICTURE_FILE_NAME'],
				$constants['GROCY_USER_ID']
			);

			$returnArray = [];
			foreach ($constants as $constant => $value)
			{
				if (substr($constant, 0, 6) === 'GROCY_')
				{
					$returnArray[substr($constant, 6)] = $value;
				}
			}

			return $this->ApiResponse($response, $returnArray);
		}
		catch (\Exception $ex)
		{
			return $this->GenericErrorResponse($response, $ex->getMessage());
		}
	}

	public function GetDbChangedTime(Request $request, Response $response, array $args)
	{
		return $this->ApiResponse($response, [
			'changed_time' => DatabaseService::GetInstance()->GetDbChangedTime()
		]);
	}

	public function GetSystemInfo(Request $request, Response $response, array $args)
	{
		return $this->ApiResponse($response, ApplicationService::GetInstance()->GetSystemInfo());
	}

	public function GetSystemTime(Request $request, Response $response, array $args)
	{
		try
		{
			$offset = 0;
			$params = $request->getQueryParams();
			if (isset($params['offset']))
			{
				if (filter_var($params['offset'], FILTER_VALIDATE_INT) === false)
				{
					throw new \Exception('Query parameter "offset" is not a valid integer');
				}

				$offset = $params['offset'];
			}

			return $this->ApiResponse($response, ApplicationService::GetInstance()->GetSystemTime($offset));
		}
		catch (\Exception $ex)
		{
			return $this->GenericErrorResponse($response, $ex->getMessage());
		}
	}

	public function LogMissingLocalization(Request $request, Response $response, array $args)
	{
		if (GROCY_MODE === 'dev')
		{
			try
			{
				$requestBody = $this->GetParsedAndFilteredRequestBody($request);

				LocalizationService::GetInstance()->CheckAndAddMissingTranslationToPot($requestBody['text']);
				return $this->EmptyApiResponse($response);
			}
			catch (\Exception $ex)
			{
				return $this->GenericErrorResponse($response, $ex->getMessage());
			}
		}
	}

	/**
	 * Errores de JavaScript del navegador (public/js/daily_errors.js): una línea JSON por error en data/logs/client_errors.jsonl.
	 * El archivo se rota al pasar de 2 MB para que un error repetido no llene el disco.
	 */
	public function LogClientError(Request $request, Response $response, array $args)
	{
		$body = json_decode((string) $request->getBody(), true);
		if (!is_array($body))
		{
			return $this->GenericErrorResponse($response, 'Invalid body', 400);
		}
		$clip = fn ($v, int $n) => mb_substr(is_scalar($v) ? (string) $v : json_encode($v), 0, $n);
		$entry = [
			'at' => date('Y-m-d H:i:s'),
			'user' => defined('GROCY_USER_ID') ? GROCY_USER_ID : null,
			'kind' => $clip($body['kind'] ?? 'error', 20),
			'page' => $clip($body['page'] ?? '', 300),
			'msg' => $clip($body['msg'] ?? '', 500),
			'src' => $clip($body['src'] ?? '', 300),
			'stack' => $clip($body['stack'] ?? '', 2000),
			'ua' => $clip($request->getHeaderLine('User-Agent'), 200),
		];
		$dir = GROCY_DATAPATH . '/logs';
		if (!is_dir($dir))
		{
			@mkdir($dir, 0777, true);
		}
		$file = $dir . '/client_errors.jsonl';
		if (is_file($file) && filesize($file) > 2 * 1024 * 1024)
		{
			@rename($file, $file . '.1');
		}
		file_put_contents($file, json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n", FILE_APPEND | LOCK_EX);
		return $this->EmptyApiResponse($response);
	}

	public function GetLocalizationStrings(Request $request, Response $response, array $args)
	{
		return $this->ApiResponse($response, json_decode(LocalizationService::GetInstance()->GetPoAsJsonString()), true);
	}
}
