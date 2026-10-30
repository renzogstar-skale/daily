<?php

namespace Grocy\Services;

/**
 * Mensajería interna del equipo sin compartir teléfonos.
 *  - direct: conversación 1:1 (una sola por pareja).
 *  - group: miembros elegidos.
 *  - announcement: aviso del gerente a una sucursal; los empleados leen pero no responden.
 * Lectura real: cada miembro guarda el último mensaje leído; "leído por" se calcula de esos punteros, nunca se supone.
 * Contactos: usuarios vinculados a empleados de las sucursales que el remitente puede ver, más usuarios de red (sin restricción).
 */
class MessagingService extends BaseService
{
	const MAX_BODY = 2000;
	const MAX_PER_MINUTE = 30;

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

	private function Val(string $sql, array $p = [])
	{
		$st = $this->Db()->prepare($sql);
		$st->execute($p);
		return $st->fetchColumn();
	}

	private static function Name(array $u): string
	{
		return trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? '')) ?: strval($u['username'] ?? ('#' . ($u['id'] ?? '')));
	}

	/** $allowedBranches null = toda la red */
	public function Contacts(int $userId, ?array $allowedBranches): array
	{
		$bw = $allowedBranches === null ? '' : ' AND e.branch_id IN (' . (implode(',', array_map('intval', $allowedBranches)) ?: '0') . ')';
		$rows = $this->Rows("SELECT u.id, u.username, u.first_name, u.last_name, e.position, e.branch_id, b.name AS branch
			FROM users u JOIN employees e ON e.user_id = u.id AND e.active = 1 LEFT JOIN branches b ON b.id = e.branch_id
			WHERE u.id != :u$bw
			UNION
			SELECT u.id, u.username, u.first_name, u.last_name, 'Gerencia', NULL, 'Red'
			FROM users u WHERE u.id != :u AND u.id NOT IN (SELECT user_id FROM employees WHERE user_id IS NOT NULL)
				AND u.id IN (SELECT up.user_id FROM user_permissions up JOIN permission_hierarchy ph ON ph.id = up.permission_id WHERE ph.name IN ('ADMIN', 'WORKFORCE_SCHEDULE_MANAGE'))
			ORDER BY 3, 4", [':u' => $userId]);
		return array_map(fn($r) => ['id' => intval($r['id']), 'name' => self::Name($r), 'position' => $r['position'], 'branch_id' => $r['branch_id'] !== null ? intval($r['branch_id']) : null, 'branch' => $r['branch']], $rows);
	}

	private function AssertContacts(int $userId, array $ids, ?array $allowedBranches): void
	{
		$ok = array_column($this->Contacts($userId, $allowedBranches), 'id');
		foreach ($ids as $id)
		{
			if (!in_array(intval($id), $ok, true))
			{
				throw new \Exception('No puede escribirle a ese usuario', 403);
			}
		}
	}

	public function OpenDirect(int $userId, int $otherId, ?array $allowedBranches): int
	{
		if ($otherId === $userId)
		{
			throw new \Exception('Elija a otra persona');
		}
		$this->AssertContacts($userId, [$otherId], $allowedBranches);
		$key = min($userId, $otherId) . ':' . max($userId, $otherId);
		$id = $this->Val('SELECT id FROM dairy_message_threads WHERE direct_key = ?', [$key]);
		if ($id)
		{
			return intval($id);
		}
		return $this->CreateThread('direct', null, null, $userId, [$userId, $otherId], $key);
	}

	public function CreateGroup(int $userId, string $title, array $memberIds, ?array $allowedBranches): int
	{
		$title = trim($title);
		if ($title === '' || mb_strlen($title) > 80)
		{
			throw new \Exception('Indique un nombre de grupo (máx. 80 caracteres)');
		}
		$memberIds = array_values(array_unique(array_filter(array_map('intval', $memberIds), fn($m) => $m !== $userId)));
		if (count($memberIds) < 1 || count($memberIds) > 200)
		{
			throw new \Exception('El grupo necesita entre 1 y 200 integrantes además de usted');
		}
		$this->AssertContacts($userId, $memberIds, $allowedBranches);
		return $this->CreateThread('group', $title, null, $userId, array_merge([$userId], $memberIds));
	}

	/** Aviso a toda una sucursal (solo quien administra horarios). Los destinatarios no pueden responder. */
	public function CreateAnnouncement(int $userId, int $branchId, string $title, string $body): array
	{
		$members = array_map('intval', array_column($this->Rows('SELECT DISTINCT user_id FROM employees WHERE branch_id = ? AND active = 1 AND user_id IS NOT NULL AND user_id != ?', [$branchId, $userId]), 'user_id'));
		$id = $this->CreateThread('announcement', trim($title) ?: 'Aviso', $branchId, $userId, array_merge([$userId], $members));
		$this->Db()->prepare('UPDATE dairy_message_members SET can_post = 0 WHERE thread_id = ? AND user_id != ?')->execute([$id, $userId]);
		$msg = $this->Send($userId, $id, $body);
		return ['thread_id' => $id, 'recipients' => count($members), 'message' => $msg];
	}

	private function CreateThread(string $kind, ?string $title, ?int $branchId, int $userId, array $members, ?string $directKey = null): int
	{
		$now = date('Y-m-d H:i:s');
		$db = $this->Db();
		$db->prepare('INSERT INTO dairy_message_threads (kind, title, branch_id, direct_key, created_by_user_id, created_at) VALUES (?, ?, ?, ?, ?, ?)')
			->execute([$kind, $title, $branchId, $directKey, $userId, $now]);
		$id = intval($db->lastInsertId());
		$ins = $db->prepare('INSERT OR IGNORE INTO dairy_message_members (thread_id, user_id, joined_at) VALUES (?, ?, ?)');
		foreach (array_unique($members) as $m)
		{
			$ins->execute([$id, $m, $now]);
		}
		return $id;
	}

	private function Member(int $threadId, int $userId): array
	{
		$m = $this->Rows('SELECT * FROM dairy_message_members WHERE thread_id = ? AND user_id = ?', [$threadId, $userId])[0] ?? null;
		if (!$m)
		{
			throw new \Exception('Conversación no encontrada', 404);
		}
		return $m;
	}

	public function Send(int $userId, int $threadId, string $body, ?string $clientUuid = null): array
	{
		$m = $this->Member($threadId, $userId);
		if (!intval($m['can_post']))
		{
			throw new \Exception('Este aviso no admite respuestas', 403);
		}
		$body = trim(str_replace("\r\n", "\n", $body));
		if ($body === '')
		{
			throw new \Exception('Escriba un mensaje');
		}
		if (mb_strlen($body) > self::MAX_BODY)
		{
			throw new \Exception('El mensaje supera ' . self::MAX_BODY . ' caracteres');
		}
		if ($clientUuid !== null && ($dup = $this->Rows('SELECT * FROM dairy_messages WHERE client_uuid = ?', [$clientUuid])[0] ?? null))
		{
			return $dup + ['duplicate' => true];
		}
		$recent = intval($this->Val("SELECT COUNT(*) FROM dairy_messages WHERE sender_user_id = ? AND created_at >= datetime('now', 'localtime', '-1 minute')", [$userId]));
		if ($recent >= self::MAX_PER_MINUTE)
		{
			throw new \Exception('Demasiados mensajes seguidos; espere un minuto');
		}
		$now = date('Y-m-d H:i:s');
		$db = $this->Db();
		$db->prepare('INSERT INTO dairy_messages (thread_id, sender_user_id, body, client_uuid, created_at) VALUES (?, ?, ?, ?, ?)')->execute([$threadId, $userId, $body, $clientUuid, $now]);
		$id = intval($db->lastInsertId());
		$db->prepare('UPDATE dairy_message_threads SET last_message_at = ? WHERE id = ?')->execute([$now, $threadId]);
		// El remitente leyó su propio mensaje
		$db->prepare('UPDATE dairy_message_members SET last_read_message_id = ?, last_read_at = ? WHERE thread_id = ? AND user_id = ?')->execute([$id, $now, $threadId, $userId]);

		$others = array_map('intval', array_column($this->Rows('SELECT user_id FROM dairy_message_members WHERE thread_id = ? AND user_id != ?', [$threadId, $userId]), 'user_id'));
		$sender = $this->Rows('SELECT id, username, first_name, last_name FROM users WHERE id = ?', [$userId])[0] ?? ['id' => $userId];
		$thread = $this->Rows('SELECT kind, title FROM dairy_message_threads WHERE id = ?', [$threadId])[0];
		$push = PushService::GetInstance()->Notify($others, 'message', $thread['kind'] === 'direct' ? self::Name($sender) : ($thread['title'] . ' · ' . self::Name($sender)),
			mb_substr($body, 0, 140), ['thread_id' => $threadId, 'message_id' => $id, 'route' => 'messages/' . $threadId]);
		return ['id' => $id, 'thread_id' => $threadId, 'body' => $body, 'created_at' => $now, 'push' => $push];
	}

	public function Threads(int $userId): array
	{
		$rows = $this->Rows("SELECT t.*, m.last_read_message_id, m.can_post,
				(SELECT COUNT(*) FROM dairy_messages x WHERE x.thread_id = t.id AND x.id > m.last_read_message_id AND x.sender_user_id != :u) AS unread,
				(SELECT body FROM dairy_messages x WHERE x.thread_id = t.id ORDER BY x.id DESC LIMIT 1) AS last_body,
				(SELECT sender_user_id FROM dairy_messages x WHERE x.thread_id = t.id ORDER BY x.id DESC LIMIT 1) AS last_sender
			FROM dairy_message_threads t JOIN dairy_message_members m ON m.thread_id = t.id AND m.user_id = :u
			ORDER BY IFNULL(t.last_message_at, t.created_at) DESC LIMIT 100", [':u' => $userId]);
		foreach ($rows as &$r)
		{
			$r['members'] = $this->MembersOf(intval($r['id']));
			if ($r['kind'] === 'direct')
			{
				$other = array_values(array_filter($r['members'], fn($m) => $m['user_id'] !== $userId))[0] ?? null;
				$r['title'] = $other['name'] ?? 'Conversación';
			}
			$r['unread'] = intval($r['unread']);
			$r['can_post'] = intval($r['can_post']) === 1;
		}
		return ['threads' => $rows, 'unread' => array_sum(array_column($rows, 'unread'))];
	}

	private function MembersOf(int $threadId): array
	{
		return array_map(fn($r) => ['user_id' => intval($r['id']), 'name' => self::Name($r), 'last_read_message_id' => intval($r['last_read_message_id']), 'last_read_at' => $r['last_read_at']],
			$this->Rows('SELECT u.id, u.username, u.first_name, u.last_name, m.last_read_message_id, m.last_read_at FROM dairy_message_members m JOIN users u ON u.id = m.user_id WHERE m.thread_id = ? ORDER BY u.first_name', [$threadId]));
	}

	/** Mensajes (paginados hacia atrás) con "leído por" real; marca como leído hasta el último mostrado */
	public function Messages(int $userId, int $threadId, ?int $beforeId = null, int $limit = 50): array
	{
		$this->Member($threadId, $userId);
		$limit = max(1, min(100, $limit));
		$p = [':t' => $threadId];
		$w = '';
		if ($beforeId)
		{
			$w = ' AND x.id < :b';
			$p[':b'] = $beforeId;
		}
		$msgs = array_reverse($this->Rows("SELECT x.id, x.sender_user_id, x.body, x.created_at, u.username, u.first_name, u.last_name FROM dairy_messages x LEFT JOIN users u ON u.id = x.sender_user_id
			WHERE x.thread_id = :t$w ORDER BY x.id DESC LIMIT $limit", $p));
		$members = $this->MembersOf($threadId);
		foreach ($msgs as &$m)
		{
			$m['id'] = intval($m['id']);
			$m['sender_user_id'] = intval($m['sender_user_id']);
			$m['sender'] = self::Name(['id' => $m['sender_user_id']] + $m);
			$m['mine'] = $m['sender_user_id'] === $userId;
			$readers = array_values(array_filter($members, fn($x) => $x['user_id'] !== $m['sender_user_id'] && $x['last_read_message_id'] >= $m['id']));
			$m['read_by'] = array_column($readers, 'name');
			$m['read_count'] = count($readers);
			$m['recipients'] = count($members) - 1;
			unset($m['username'], $m['first_name'], $m['last_name']);
		}
		unset($m);
		if ($msgs && !$beforeId)
		{
			$this->MarkRead($userId, $threadId, end($msgs)['id']);
		}
		$t = $this->Rows('SELECT id, kind, title, branch_id FROM dairy_message_threads WHERE id = ?', [$threadId])[0];
		if ($t['kind'] === 'direct')
		{
			$t['title'] = (array_values(array_filter($members, fn($x) => $x['user_id'] !== $userId))[0]['name'] ?? 'Conversación');
		}
		return ['thread' => $t + ['can_post' => intval($this->Member($threadId, $userId)['can_post']) === 1], 'members' => $members, 'messages' => $msgs, 'has_more' => count($msgs) === $limit];
	}

	public function MarkRead(int $userId, int $threadId, int $messageId): void
	{
		$this->Db()->prepare('UPDATE dairy_message_members SET last_read_message_id = ?, last_read_at = ? WHERE thread_id = ? AND user_id = ? AND last_read_message_id < ?')
			->execute([$messageId, date('Y-m-d H:i:s'), $threadId, $userId, $messageId]);
	}

	public function UnreadCount(int $userId): int
	{
		return intval($this->Val('SELECT COUNT(*) FROM dairy_messages x JOIN dairy_message_members m ON m.thread_id = x.thread_id AND m.user_id = ? WHERE x.id > m.last_read_message_id AND x.sender_user_id != ?', [$userId, $userId]));
	}
}
