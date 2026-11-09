<?php

// DAILY — Suscripciones por plan (Essential, Professional, Business, Enterprise).
// Suscripción por empresa, historial de cambios, solicitudes de ampliación y plan propio para cuentas de evaluación.
// Las empresas existentes quedan en Enterprise activa: implantar los planes no quita funciones ni datos.

$db = \Grocy\Services\DatabaseService::GetInstance()->GetDbConnectionRaw();

$db->exec("CREATE TABLE IF NOT EXISTS daily_subscriptions (
	company_id INTEGER PRIMARY KEY REFERENCES companies(id),
	plan TEXT NOT NULL DEFAULT 'essential' CHECK (plan IN ('essential', 'professional', 'business', 'enterprise')),
	status TEXT NOT NULL DEFAULT 'active' CHECK (status IN ('active', 'trial', 'suspended', 'expired', 'cancelled')),
	activated_at DATE,
	renews_at DATE,
	expires_at DATE,
	notes TEXT,
	updated_by INTEGER,
	row_updated_timestamp DATETIME DEFAULT (datetime('now', 'localtime'))
)");

$db->exec("CREATE TABLE IF NOT EXISTS daily_subscription_history (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	company_id INTEGER,
	user_id INTEGER,
	scope TEXT NOT NULL DEFAULT 'company',
	event TEXT NOT NULL,
	from_plan TEXT,
	to_plan TEXT,
	from_status TEXT,
	to_status TEXT,
	note TEXT,
	changed_by INTEGER,
	row_created_timestamp DATETIME DEFAULT (datetime('now', 'localtime'))
)");

$db->exec("CREATE TABLE IF NOT EXISTS daily_subscription_requests (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	company_id INTEGER NOT NULL,
	requested_by INTEGER NOT NULL,
	current_plan TEXT NOT NULL,
	requested_plan TEXT NOT NULL,
	message TEXT,
	contact TEXT,
	status TEXT NOT NULL DEFAULT 'pending' CHECK (status IN ('pending', 'approved', 'rejected', 'cancelled')),
	decided_by INTEGER,
	decided_at DATETIME,
	row_created_timestamp DATETIME DEFAULT (datetime('now', 'localtime'))
)");

$db->exec("CREATE TABLE IF NOT EXISTS daily_subscription_user_overrides (
	user_id INTEGER PRIMARY KEY REFERENCES users(id),
	plan TEXT NOT NULL CHECK (plan IN ('essential', 'professional', 'business', 'enterprise')),
	reason TEXT,
	row_updated_timestamp DATETIME DEFAULT (datetime('now', 'localtime'))
)");

$today = date('Y-m-d');
$renews = date('Y-m-d', strtotime('+1 year'));
foreach ($db->query('SELECT id FROM companies')->fetchAll(\PDO::FETCH_COLUMN) as $cid)
{
	$exists = $db->query('SELECT COUNT(*) FROM daily_subscriptions WHERE company_id = ' . intval($cid))->fetchColumn();
	if (!$exists)
	{
		$db->prepare("INSERT INTO daily_subscriptions (company_id, plan, status, activated_at, renews_at, notes) VALUES (?, 'enterprise', 'active', ?, ?, 'Plan inicial al implantar suscripciones')")->execute([$cid, $today, $renews]);
		$db->prepare("INSERT INTO daily_subscription_history (company_id, scope, event, to_plan, to_status, note) VALUES (?, 'company', 'activated', 'enterprise', 'active', 'Implantación de planes')")->execute([$cid]);
	}
}

// Cuentas de evaluación, una por plan: rol administrador para que solo el plan marque la diferencia.
// Si ya existen se conservan su contraseña y datos; si faltan se crean con contraseña DailyPlan2026 (cambiarla al usar).
$adminId = $db->query("SELECT id FROM permission_hierarchy WHERE name = 'ADMIN'")->fetchColumn();
foreach (['essential', 'professional', 'business', 'enterprise'] as $plan)
{
	$uid = $db->query("SELECT id FROM users WHERE username = '$plan'")->fetchColumn();
	if (!$uid)
	{
		$db->prepare("INSERT INTO users (username, first_name, last_name, password) VALUES (?, 'Plan', ?, ?)")->execute([$plan, ucfirst($plan), password_hash('DailyPlan2026', PASSWORD_DEFAULT)]);
		$uid = $db->lastInsertId();
	}
	$db->exec('DELETE FROM user_permissions WHERE user_id = ' . intval($uid));
	if ($adminId)
	{
		$db->exec('INSERT INTO user_permissions (user_id, permission_id) VALUES (' . intval($uid) . ', ' . intval($adminId) . ')');
	}
	$db->prepare("INSERT OR REPLACE INTO daily_subscription_user_overrides (user_id, plan, reason) VALUES (?, ?, 'Cuenta de evaluación del plan')")->execute([$uid, $plan]);
	$db->prepare("INSERT INTO daily_subscription_history (company_id, user_id, scope, event, to_plan, to_status, note) VALUES (1, ?, 'user', 'activated', ?, 'active', 'Cuenta de evaluación')")->execute([$uid, $plan]);
}
