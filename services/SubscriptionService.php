<?php

namespace Grocy\Services;

use Grocy\Controllers\Users\User;
use Grocy\Helpers\DailySubscription;
use Grocy\Helpers\DairyAccess;

/**
 * Administración de suscripciones: estado, solicitudes de ampliación, cambios de plan e historial.
 * Un cambio de plan nunca borra datos: un downgrade solo restringe el acceso (DailySubscription + middleware).
 */
class SubscriptionService extends BaseService
{
	const STATUSES = ['active', 'trial', 'suspended', 'expired', 'cancelled'];

	private function Raw(): \PDO
	{
		return DatabaseService::GetInstance()->GetDbConnectionRaw();
	}

	// Confirma cambios de plan: administrador de la empresa que no sea una cuenta de evaluación
	public function CanManage(): bool
	{
		return defined('GROCY_USER_ID') && DairyAccess::HasAny([User::PERMISSION_ADMIN]) && DailySubscription::Current()['scope'] !== 'user';
	}

	public function CanRequest(): bool
	{
		return defined('GROCY_USER_ID') && DairyAccess::HasAny([User::PERMISSION_ADMIN, User::PERMISSION_KPIS_VIEW, User::PERMISSION_BRANCH_MANAGE]);
	}

	public function Overview(): array
	{
		$sub = DailySubscription::Current();
		$cid = $sub['company_id'];
		$company = $this->Raw()->query('SELECT id, name, code FROM companies WHERE id = ' . intval($cid))->fetch(\PDO::FETCH_ASSOC) ?: ['id' => $cid, 'name' => 'DAILY', 'code' => ''];
		$history = $this->Raw()->prepare("SELECT h.*, u.username AS changed_by_name FROM daily_subscription_history h LEFT JOIN users u ON u.id = h.changed_by WHERE h.company_id = :c AND (h.scope = 'company' OR h.user_id = :u) ORDER BY h.id DESC LIMIT 50");
		$history->execute([':c' => $cid, ':u' => GROCY_USER_ID]);
		$requests = $this->Raw()->prepare('SELECT r.*, u.username AS requested_by_name FROM daily_subscription_requests r LEFT JOIN users u ON u.id = r.requested_by WHERE r.company_id = :c ' . ($this->CanManage() ? '' : 'AND r.requested_by = ' . intval(GROCY_USER_ID)) . ' ORDER BY r.id DESC LIMIT 30');
		$requests->execute([':c' => $cid]);
		$plans = [];
		foreach (DailySubscription::PLANS as $p)
		{
			$plans[] = [
				'id' => $p,
				'name' => DailySubscription::PlanName($p),
				'icon' => DailySubscription::PLAN_INFO[$p]['icon'],
				'tagline' => DailySubscription::L(DailySubscription::PLAN_INFO[$p]),
				'added' => array_map(fn ($c) => ['id' => $c, 'label' => DailySubscription::L(DailySubscription::CAPABILITIES[$c])], DailySubscription::AddedIn($p)),
				'current' => $p === $sub['contracted_plan'],
			];
		}
		return [
			'company' => $company,
			'subscription' => [
				'plan' => $sub['plan'],
				'plan_name' => DailySubscription::PlanName($sub['plan']),
				'contracted_plan' => $sub['contracted_plan'],
				'preview' => !empty($sub['preview']),
				'status' => $sub['status'],
				'active' => $sub['active'],
				'scope' => $sub['scope'],
				'activated_at' => $sub['activated_at'],
				'renews_at' => $sub['renews_at'],
				'expires_at' => $sub['expires_at'],
			],
			'capabilities' => $sub['capabilities'],
			'plans' => $plans,
			'matrix' => DailySubscription::Matrix(),
			'history' => $history->fetchAll(\PDO::FETCH_ASSOC),
			'requests' => $requests->fetchAll(\PDO::FETCH_ASSOC),
			'can_manage' => $this->CanManage(),
			'can_request' => $this->CanRequest(),
		];
	}

	public function RequestUpgrade(array $d): array
	{
		if (!$this->CanRequest())
		{
			throw new \Exception('No autorizado para solicitar cambios de plan');
		}
		$sub = DailySubscription::Current();
		$plan = strval($d['plan'] ?? '');
		if (DailySubscription::PlanRank($plan) < 0 || $plan === $sub['contracted_plan'])
		{
			throw new \Exception('Plan solicitado no válido');
		}
		$pending = $this->Raw()->prepare("SELECT id FROM daily_subscription_requests WHERE company_id = :c AND requested_by = :u AND requested_plan = :p AND status = 'pending'");
		$pending->execute([':c' => $sub['company_id'], ':u' => GROCY_USER_ID, ':p' => $plan]);
		if ($id = $pending->fetchColumn())
		{
			return ['id' => intval($id), 'duplicate' => true];
		}
		$this->Raw()->prepare('INSERT INTO daily_subscription_requests (company_id, requested_by, current_plan, requested_plan, message, contact) VALUES (?, ?, ?, ?, ?, ?)')
			->execute([$sub['company_id'], GROCY_USER_ID, $sub['contracted_plan'], $plan, mb_substr(trim(strval($d['message'] ?? '')), 0, 1000), mb_substr(trim(strval($d['contact'] ?? '')), 0, 200)]);
		$id = intval($this->Raw()->lastInsertId());
		$this->Log($sub['company_id'], 'company', null, 'upgrade_requested', $sub['contracted_plan'], $plan, null, null, 'Solicitud #' . $id);
		return ['id' => $id, 'duplicate' => false];
	}

	/**
	 * Confirma un cambio (el "backend de facturación"): plan, estado y fechas de la empresa, o el plan de una cuenta de
	 * evaluación (user_id). Los permisos cambian en la siguiente solicitud, sin cerrar sesión.
	 */
	public function Change(array $d): array
	{
		if (!$this->CanManage())
		{
			throw new \Exception('Solo un administrador de la suscripción puede confirmar cambios de plan');
		}
		$plan = isset($d['plan']) && $d['plan'] !== '' ? strval($d['plan']) : null;
		if ($plan !== null && DailySubscription::PlanRank($plan) < 0)
		{
			throw new \Exception('Plan no válido');
		}
		$note = mb_substr(trim(strval($d['note'] ?? '')), 0, 500) ?: null;

		if (!empty($d['user_id']))
		{
			$uid = intval($d['user_id']);
			$prev = $this->Raw()->query('SELECT plan FROM daily_subscription_user_overrides WHERE user_id = ' . $uid)->fetchColumn();
			if ($prev === false || $plan === null)
			{
				throw new \Exception('La cuenta no es de evaluación o falta el plan');
			}
			$this->Raw()->prepare('UPDATE daily_subscription_user_overrides SET plan = ?, row_updated_timestamp = datetime(\'now\', \'localtime\') WHERE user_id = ?')->execute([$plan, $uid]);
			$this->Log(DailySubscription::CompanyId($uid), 'user', $uid, $this->EventFor($prev, $plan), $prev, $plan, null, null, $note);
			DailySubscription::Reset();
			return ['user_id' => $uid, 'plan' => $plan];
		}

		$cid = DailySubscription::Current()['company_id'];
		$row = $this->Raw()->query('SELECT * FROM daily_subscriptions WHERE company_id = ' . intval($cid))->fetch(\PDO::FETCH_ASSOC);
		if (!$row)
		{
			$this->Raw()->prepare("INSERT INTO daily_subscriptions (company_id, plan, status, activated_at) VALUES (?, 'enterprise', 'active', date('now', 'localtime'))")->execute([$cid]);
			$row = ['plan' => 'enterprise', 'status' => 'active', 'renews_at' => null, 'expires_at' => null];
		}
		$status = isset($d['status']) && $d['status'] !== '' ? strval($d['status']) : $row['status'];
		if (!in_array($status, self::STATUSES, true))
		{
			throw new \Exception('Estado no válido');
		}
		$date = function ($key) use ($d, $row) {
			if (!array_key_exists($key, $d))
			{
				return $row[$key];
			}
			$v = trim(strval($d[$key]));
			if ($v !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $v))
			{
				throw new \Exception('Fecha no válida: ' . $key);
			}
			return $v === '' ? null : $v;
		};
		$newPlan = $plan ?? $row['plan'];
		$this->Raw()->prepare("UPDATE daily_subscriptions SET plan = ?, status = ?, renews_at = ?, expires_at = ?, activated_at = CASE WHEN ? != plan THEN date('now', 'localtime') ELSE activated_at END, updated_by = ?, row_updated_timestamp = datetime('now', 'localtime') WHERE company_id = ?")
			->execute([$newPlan, $status, $date('renews_at'), $date('expires_at'), $newPlan, GROCY_USER_ID, $cid]);
		$event = $newPlan !== $row['plan'] ? $this->EventFor($row['plan'], $newPlan) : ($status !== $row['status'] ? 'status_changed' : 'dates_changed');
		$this->Log($cid, 'company', null, $event, $row['plan'], $newPlan, $row['status'], $status, $note);
		DailySubscription::Reset();
		return ['company_id' => $cid, 'plan' => $newPlan, 'status' => $status];
	}

	public function DecideRequest(int $id, array $d): array
	{
		if (!$this->CanManage())
		{
			throw new \Exception('Solo un administrador de la suscripción puede decidir solicitudes');
		}
		$req = $this->Raw()->query('SELECT * FROM daily_subscription_requests WHERE id = ' . $id)->fetch(\PDO::FETCH_ASSOC);
		if (!$req || $req['status'] !== 'pending')
		{
			throw new \Exception('Solicitud no encontrada o ya decidida');
		}
		$decision = ($d['decision'] ?? '') === 'approved' ? 'approved' : 'rejected';
		$this->Raw()->prepare("UPDATE daily_subscription_requests SET status = ?, decided_by = ?, decided_at = datetime('now', 'localtime') WHERE id = ?")->execute([$decision, GROCY_USER_ID, $id]);
		if ($decision === 'approved')
		{
			$override = $this->Raw()->query('SELECT 1 FROM daily_subscription_user_overrides WHERE user_id = ' . intval($req['requested_by']))->fetchColumn();
			$this->Change(['plan' => $req['requested_plan'], 'user_id' => $override ? $req['requested_by'] : null, 'note' => 'Solicitud #' . $id . ' aprobada']);
		}
		else
		{
			$this->Log($req['company_id'], 'company', null, 'upgrade_rejected', $req['current_plan'], $req['requested_plan'], null, null, 'Solicitud #' . $id);
		}
		return ['id' => $id, 'status' => $decision];
	}

	private function EventFor(string $from, string $to): string
	{
		return DailySubscription::PlanRank($to) > DailySubscription::PlanRank($from) ? 'upgrade' : (DailySubscription::PlanRank($to) < DailySubscription::PlanRank($from) ? 'downgrade' : 'unchanged');
	}

	private function Log($cid, string $scope, $uid, string $event, $from, $to, $fromStatus, $toStatus, $note): void
	{
		$this->Raw()->prepare('INSERT INTO daily_subscription_history (company_id, user_id, scope, event, from_plan, to_plan, from_status, to_status, note, changed_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
			->execute([$cid, $uid, $scope, $event, $from, $to, $fromStatus, $toStatus, $note, defined('GROCY_USER_ID') ? GROCY_USER_ID : null]);
	}
}
