<?php

namespace Grocy\Middleware;

use Grocy\Services\DatabaseService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

/**
 * POST/PUT a la API con cabecera X-Idempotency-Key (cola sin conexión, reintentos de red): la primera petición se
 * ejecuta y su respuesta 2xx se guarda; un reenvío con la misma clave devuelve esa respuesta sin volver a ejecutar
 * (cabecera X-Idempotent-Replay: 1). Dos envíos simultáneos: el segundo recibe 409. Si la operación falla, la clave
 * se libera para poder reintentar. La clave es por usuario y ruta: otra ruta o usuario con la misma clave → 422.
 */
class DairyIdempotencyMiddleware extends BaseMiddleware
{
	const RETENTION_DAYS = 30;

	public function __invoke(Request $request, RequestHandler $handler): Response
	{
		$key = trim($request->getHeaderLine('X-Idempotency-Key'));
		if ($key === '' || !in_array($request->getMethod(), ['POST', 'PUT'], true) || strpos($request->getUri()->getPath(), '/api/') === false || !defined('GROCY_USER_ID'))
		{
			return $handler->handle($request);
		}
		if (!preg_match('/^[A-Za-z0-9_.:-]{8,100}$/', $key))
		{
			return $this->Json(400, ['error_message' => 'X-Idempotency-Key inválida (8–100 caracteres alfanuméricos)']);
		}

		$db = DatabaseService::GetInstance()->GetDbConnectionRaw();
		$path = $request->getMethod() . ' ' . $request->getUri()->getPath();
		$userId = intval(GROCY_USER_ID);

		// Una petición que murió a mitad (fatal, corte) no debe bloquear la clave para siempre
		$db->prepare("DELETE FROM dairy_idempotency_keys WHERE idempotency_key = :k AND status_code IS NULL AND created_timestamp < datetime('now', 'localtime', '-2 minutes')")->execute([':k' => $key]);

		$insert = $db->prepare('INSERT OR IGNORE INTO dairy_idempotency_keys (idempotency_key, user_id, request_path) VALUES (:k, :u, :p)');
		$insert->execute([':k' => $key, ':u' => $userId, ':p' => $path]);
		if ($insert->rowCount() === 0)
		{
			$stmt = $db->prepare('SELECT * FROM dairy_idempotency_keys WHERE idempotency_key = :k');
			$stmt->execute([':k' => $key]);
			$row = $stmt->fetch(\PDO::FETCH_ASSOC);
			if (!$row || intval($row['user_id']) !== $userId || $row['request_path'] !== $path)
			{
				return $this->Json(422, ['error_message' => 'La clave de idempotencia ya se usó en otra operación']);
			}
			if ($row['status_code'] === null)
			{
				return $this->Json(409, ['error_message' => 'Esta operación ya se está procesando']);
			}
			$response = $this->ResponseFactory->createResponse(intval($row['status_code']))->withHeader('Content-Type', 'application/json')->withHeader('X-Idempotent-Replay', '1');
			$response->getBody()->write((string) $row['response_body']);
			return $response;
		}

		try
		{
			$response = $handler->handle($request);
		}
		catch (\Throwable $ex)
		{
			$db->prepare('DELETE FROM dairy_idempotency_keys WHERE idempotency_key = :k')->execute([':k' => $key]);
			throw $ex;
		}

		if ($response->getStatusCode() >= 200 && $response->getStatusCode() < 300)
		{
			$body = (string) $response->getBody();
			$db->prepare('UPDATE dairy_idempotency_keys SET status_code = :s, response_body = :b WHERE idempotency_key = :k')->execute([':s' => $response->getStatusCode(), ':b' => $body, ':k' => $key]);
		}
		else
		{
			$db->prepare('DELETE FROM dairy_idempotency_keys WHERE idempotency_key = :k')->execute([':k' => $key]);
		}

		if (mt_rand(1, 200) === 1)
		{
			$db->exec("DELETE FROM dairy_idempotency_keys WHERE created_timestamp < datetime('now', 'localtime', '-" . self::RETENTION_DAYS . " days')");
		}
		return $response;
	}

	private function Json(int $status, array $data): Response
	{
		$response = $this->ResponseFactory->createResponse($status)->withHeader('Content-Type', 'application/json');
		$response->getBody()->write(json_encode($data));
		return $response;
	}
}
