<?php

namespace Grocy\Services;

/**
 * Notificaciones push a la app nativa (Expo Push API).
 *
 * Nunca se declara una notificación como entregada sin respuesta del proveedor:
 *  - no_device: el usuario no tiene ningún dispositivo registrado (queda registrado; no hay envío).
 *  - queued → sent: Expo aceptó el mensaje y devolvió un ticket.
 *  - receipt_ok / receipt_error: recibo de Expo (entregado a APNs/FCM o rechazado). Expo no confirma la lectura en el teléfono.
 *  - error: Expo rechazó el envío o no hubo conexión. DeviceNotRegistered revoca el token.
 * Sin dispositivos registrados no se hace ninguna llamada de red.
 */
class PushService extends BaseService
{
	const EXPO_SEND = 'https://exp.host/--/api/v2/push/send';
	const EXPO_RECEIPTS = 'https://exp.host/--/api/v2/push/getReceipts';

	/** Transporte HTTP reemplazable en pruebas: fn(string $url, array $payload): array{0:int,1:?array,2:?string} */
	public static $transport = null;

	private function Db(): \PDO
	{
		return DatabaseService::GetInstance()->GetDbConnectionRaw();
	}

	private function Rows(string $sql, array $p = []): array
	{
		$st = $this->Db()->prepare($sql);
		$st->execute($p);
		return $st->fetchAll(\PDO::FETCH_ASSOC);
	}

	public static function ValidToken(string $t): bool
	{
		return (bool) preg_match('/^Expo(nent)?PushToken\[[A-Za-z0-9_\-]{10,}\]$/', $t);
	}

	public function Register(int $userId, string $token, ?string $deviceName = null): array
	{
		$token = trim($token);
		if (!self::ValidToken($token))
		{
			throw new \Exception('Token de notificaciones no válido');
		}
		$now = date('Y-m-d H:i:s');
		$name = $deviceName !== null ? mb_substr(trim($deviceName), 0, 80) : null;
		// Un token pertenece a un solo usuario: si el teléfono cambia de usuario, el token pasa al nuevo
		$this->Db()->prepare('INSERT INTO dairy_push_devices (user_id, platform, token, device_name, created_at, last_seen_at) VALUES (?, ?, ?, ?, ?, ?)
			ON CONFLICT(token) DO UPDATE SET user_id = excluded.user_id, device_name = COALESCE(excluded.device_name, device_name), last_seen_at = excluded.last_seen_at, revoked_at = NULL, revoked_reason = NULL')
			->execute([$userId, 'expo', $token, $name, $now, $now]);
		return $this->Rows('SELECT id, device_name, created_at, last_seen_at FROM dairy_push_devices WHERE token = ?', [$token])[0];
	}

	public function Unregister(int $userId, string $token): bool
	{
		$st = $this->Db()->prepare("UPDATE dairy_push_devices SET revoked_at = ?, revoked_reason = 'logout' WHERE token = ? AND user_id = ? AND revoked_at IS NULL");
		$st->execute([date('Y-m-d H:i:s'), trim($token), $userId]);
		return $st->rowCount() > 0;
	}

	public function Devices(int $userId): array
	{
		return $this->Rows('SELECT id, device_name, created_at, last_seen_at FROM dairy_push_devices WHERE user_id = ? AND revoked_at IS NULL ORDER BY last_seen_at DESC', [$userId]);
	}

	/**
	 * Encola un aviso para cada usuario (una fila por dispositivo) e intenta enviarlo de inmediato.
	 * Devuelve el resumen real: cuántos quedaron sin dispositivo, enviados o con error.
	 */
	public function Notify(array $userIds, string $category, string $title, ?string $body = null, array $data = [], bool $dispatch = true): array
	{
		$userIds = array_values(array_unique(array_filter(array_map('intval', $userIds))));
		if (!$userIds)
		{
			return ['queued' => 0, 'no_device' => 0];
		}
		$now = date('Y-m-d H:i:s');
		$ins = $this->Db()->prepare('INSERT INTO dairy_push_outbox (user_id, device_id, category, title, body, data_json, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
		$ids = [];
		$noDevice = 0;
		$json = json_encode($data + ['category' => $category], JSON_UNESCAPED_UNICODE);
		foreach ($userIds as $u)
		{
			$devices = $this->Rows('SELECT id FROM dairy_push_devices WHERE user_id = ? AND revoked_at IS NULL', [$u]);
			if (!$devices)
			{
				$ins->execute([$u, null, $category, mb_substr($title, 0, 120), $body !== null ? mb_substr($body, 0, 240) : null, $json, 'no_device', $now]);
				$noDevice++;
				continue;
			}
			foreach ($devices as $d)
			{
				$ins->execute([$u, $d['id'], $category, mb_substr($title, 0, 120), $body !== null ? mb_substr($body, 0, 240) : null, $json, 'queued', $now]);
				$ids[] = intval($this->Db()->lastInsertId());
			}
		}
		$sent = ['sent' => 0, 'error' => 0];
		if ($dispatch && $ids)
		{
			try
			{
				$sent = $this->Dispatch($ids);
			}
			catch (\Throwable $e)
			{
				// Quedan en cola para el programador; nunca se marcan como enviadas
			}
		}
		return ['queued' => count($ids), 'no_device' => $noDevice] + $sent;
	}

	/** Envía las filas en cola (todas o las indicadas), en lotes de 100 como exige Expo */
	public function Dispatch(?array $ids = null, int $limit = 300): array
	{
		$w = $ids ? ' AND o.id IN (' . implode(',', array_map('intval', $ids)) . ')' : '';
		$rows = $this->Rows("SELECT o.*, d.token FROM dairy_push_outbox o JOIN dairy_push_devices d ON d.id = o.device_id
			WHERE o.status = 'queued' AND d.revoked_at IS NULL AND o.attempts < 5$w ORDER BY o.id LIMIT " . intval($limit));
		$out = ['sent' => 0, 'error' => 0];
		foreach (array_chunk($rows, 100) as $chunk)
		{
			$payload = array_map(fn($r) => ['to' => $r['token'], 'title' => $r['title'], 'body' => $r['body'], 'data' => json_decode($r['data_json'] ?: '{}', true), 'sound' => 'default', 'priority' => 'high'], $chunk);
			[$code, $resp, $err] = $this->Http(self::EXPO_SEND, $payload);
			$now = date('Y-m-d H:i:s');
			$tickets = is_array($resp['data'] ?? null) ? $resp['data'] : [];
			foreach ($chunk as $i => $r)
			{
				$t = $tickets[$i] ?? null;
				if ($code === 200 && ($t['status'] ?? '') === 'ok')
				{
					$this->Db()->prepare("UPDATE dairy_push_outbox SET status = 'sent', ticket_id = ?, sent_at = ?, attempts = attempts + 1, error = NULL WHERE id = ?")->execute([$t['id'] ?? null, $now, $r['id']]);
					$out['sent']++;
					continue;
				}
				$msg = $t['message'] ?? ($err ?: ('HTTP ' . $code));
				$detail = $t['details']['error'] ?? null;
				// Sin conexión o error del servidor: se reintenta luego; rechazo explícito: error definitivo
				$retry = $t === null && ($code === 0 || $code >= 500);
				$this->Db()->prepare('UPDATE dairy_push_outbox SET status = ?, error = ?, attempts = attempts + 1 WHERE id = ?')->execute([$retry ? 'queued' : 'error', mb_substr(strval($msg), 0, 300), $r['id']]);
				if ($detail === 'DeviceNotRegistered')
				{
					$this->Db()->prepare("UPDATE dairy_push_devices SET revoked_at = ?, revoked_reason = 'DeviceNotRegistered' WHERE id = ?")->execute([$now, $r['device_id']]);
				}
				$out['error']++;
			}
		}
		return $out;
	}

	/** Consulta los recibos de Expo de los enviados (Expo los guarda ~24 h) */
	public function CheckReceipts(int $limit = 300): array
	{
		$rows = $this->Rows("SELECT id, ticket_id, device_id FROM dairy_push_outbox WHERE status = 'sent' AND ticket_id IS NOT NULL AND sent_at <= datetime('now', 'localtime', '-15 minutes') ORDER BY id LIMIT " . intval($limit));
		$out = ['ok' => 0, 'error' => 0];
		if (!$rows)
		{
			return $out;
		}
		[$code, $resp] = $this->Http(self::EXPO_RECEIPTS, ['ids' => array_column($rows, 'ticket_id')]);
		if ($code !== 200)
		{
			return $out;
		}
		$now = date('Y-m-d H:i:s');
		foreach ($rows as $r)
		{
			$rc = $resp['data'][$r['ticket_id']] ?? null;
			if (!$rc)
			{
				continue;
			}
			$ok = ($rc['status'] ?? '') === 'ok';
			$this->Db()->prepare('UPDATE dairy_push_outbox SET status = ?, receipt_at = ?, error = ? WHERE id = ?')->execute([$ok ? 'receipt_ok' : 'receipt_error', $now, $ok ? null : mb_substr(strval($rc['message'] ?? ''), 0, 300), $r['id']]);
			if (($rc['details']['error'] ?? null) === 'DeviceNotRegistered')
			{
				$this->Db()->prepare("UPDATE dairy_push_devices SET revoked_at = ?, revoked_reason = 'DeviceNotRegistered' WHERE id = ?")->execute([$now, $r['device_id']]);
			}
			$out[$ok ? 'ok' : 'error']++;
		}
		return $out;
	}

	public function Summary(int $days = 7): array
	{
		return $this->Rows("SELECT category, status, COUNT(*) AS n FROM dairy_push_outbox WHERE created_at >= datetime('now', 'localtime', ?) GROUP BY category, status ORDER BY category, status", ['-' . intval($days) . ' days']);
	}

	private function Http(string $url, array $payload): array
	{
		if (is_callable(self::$transport))
		{
			return (self::$transport)($url, $payload);
		}
		$ch = curl_init($url);
		curl_setopt_array($ch, [
			CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 6, CURLOPT_CONNECTTIMEOUT => 3,
			CURLOPT_HTTPHEADER => ['Accept: application/json', 'Content-Type: application/json'],
			CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE)
		]);
		$raw = curl_exec($ch);
		$code = intval(curl_getinfo($ch, CURLINFO_HTTP_CODE));
		$err = $raw === false ? curl_error($ch) : null;
		curl_close($ch);
		return [$code, $raw !== false ? json_decode($raw, true) : null, $err];
	}
}
