<?php

// DAILY — CRM de clientes retail (CustomerCrmService).
//
// - customers: identificadores normalizados (documento, correo, teléfono) para detectar duplicados, fusión
//   (merged_into_id) y marca del cliente anónimo de mostrador (is_anonymous), que nunca entra en métricas de clientes.
// - customer_consents: historial de consentimientos por finalidad; el vigente es el último registro de cada una.
// - customer_preferences: preferencias declaradas (canal, horario, idioma, observaciones).
// - customer_interactions: historial de atención (llamadas, visitas, reclamos, notas).
// - customer_requests: solicitudes comerciales con responsable, vencimiento y resolución.
// - customer_loyalty_ledger: movimientos de puntos (acumulación por venta, canje, ajuste, vencimiento); solo si el
//   programa está activo en daily_system_settings (crm_loyalty_enabled).
// - customer_audit_log: trazabilidad de consultas de datos personales y de cada cambio.
// - Permisos CUSTOMERS_PII (ver datos personales completos) y CUSTOMERS_MANAGE (editar, fusionar, consentimientos,
//   puntos), debajo de Ventas; se otorgan a quien hoy tiene SALES_SUPERVISE.

$db = \Grocy\Services\DatabaseService::GetInstance()->GetDbConnectionRaw();

$cols = array_column($db->query('PRAGMA table_info(customers)')->fetchAll(\PDO::FETCH_ASSOC), 'name');
$add = [
	'customer_type' => "TEXT DEFAULT 'Comercial'",
	'branch_id' => 'INTEGER',
	'tags' => 'TEXT',
	'notes' => 'TEXT',
	'credit_limit' => 'REAL',
	'tax_id_norm' => 'TEXT',
	'email_norm' => 'TEXT',
	'phone_norm' => 'TEXT',
	'name_norm' => 'TEXT',
	'birth_date' => 'DATE',
	'is_anonymous' => 'INTEGER NOT NULL DEFAULT 0',
	'merged_into_id' => 'INTEGER',
	'merged_at' => 'DATETIME',
	'created_by_user_id' => 'INTEGER',
	'updated_at' => 'DATETIME'
];
foreach ($add as $col => $def)
{
	if (!in_array($col, $cols, true))
	{
		$db->exec("ALTER TABLE customers ADD COLUMN $col $def");
	}
}

$db->exec("CREATE TABLE IF NOT EXISTS customer_consents (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	customer_id INTEGER NOT NULL,
	purpose TEXT NOT NULL, -- data_processing | marketing_email | marketing_sms | marketing_whatsapp | profiling
	granted INTEGER NOT NULL,
	source TEXT NOT NULL, -- pos | store | phone | web | form
	evidence TEXT,
	user_id INTEGER,
	created_at DATETIME NOT NULL DEFAULT (datetime('now', 'localtime'))
)");
$db->exec('CREATE INDEX IF NOT EXISTS ix_customer_consents_customer ON customer_consents (customer_id, purpose, id)');

$db->exec("CREATE TABLE IF NOT EXISTS customer_preferences (
	customer_id INTEGER NOT NULL,
	pref_key TEXT NOT NULL, -- preferred_channel | contact_time | language | remarks
	pref_value TEXT,
	updated_by INTEGER,
	updated_at DATETIME NOT NULL DEFAULT (datetime('now', 'localtime')),
	PRIMARY KEY (customer_id, pref_key)
)");

$db->exec("CREATE TABLE IF NOT EXISTS customer_requests (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	code TEXT NOT NULL UNIQUE,
	customer_id INTEGER NOT NULL,
	branch_id INTEGER,
	category TEXT NOT NULL, -- quote | special_order | credit | complaint | invoice | product | other
	subject TEXT NOT NULL,
	detail TEXT,
	priority TEXT NOT NULL DEFAULT 'normal', -- low | normal | high | urgent
	status TEXT NOT NULL DEFAULT 'open', -- open | in_progress | waiting_customer | resolved | cancelled
	assigned_user_id INTEGER,
	due_date DATE,
	sale_id INTEGER,
	resolution TEXT,
	created_by INTEGER,
	created_at DATETIME NOT NULL DEFAULT (datetime('now', 'localtime')),
	updated_at DATETIME,
	resolved_at DATETIME
)");
$db->exec('CREATE INDEX IF NOT EXISTS ix_customer_requests_customer ON customer_requests (customer_id)');
$db->exec('CREATE INDEX IF NOT EXISTS ix_customer_requests_status ON customer_requests (status, due_date)');

$db->exec("CREATE TABLE IF NOT EXISTS customer_interactions (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	customer_id INTEGER NOT NULL,
	kind TEXT NOT NULL, -- call | visit | email | whatsapp | complaint | note | request
	summary TEXT NOT NULL,
	outcome TEXT,
	request_id INTEGER,
	branch_id INTEGER,
	user_id INTEGER,
	created_at DATETIME NOT NULL DEFAULT (datetime('now', 'localtime'))
)");
$db->exec('CREATE INDEX IF NOT EXISTS ix_customer_interactions_customer ON customer_interactions (customer_id, created_at)');

$db->exec("CREATE TABLE IF NOT EXISTS customer_loyalty_ledger (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	customer_id INTEGER NOT NULL,
	kind TEXT NOT NULL, -- earn | redeem | adjust | expire
	points INTEGER NOT NULL,
	sale_id INTEGER,
	reason TEXT,
	user_id INTEGER,
	created_at DATETIME NOT NULL DEFAULT (datetime('now', 'localtime'))
)");
$db->exec('CREATE INDEX IF NOT EXISTS ix_customer_loyalty_customer ON customer_loyalty_ledger (customer_id)');
$db->exec("CREATE UNIQUE INDEX IF NOT EXISTS ux_customer_loyalty_earn ON customer_loyalty_ledger (sale_id) WHERE kind = 'earn'");

$db->exec("CREATE TABLE IF NOT EXISTS customer_audit_log (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	customer_id INTEGER,
	user_id INTEGER,
	action TEXT NOT NULL, -- view_pii | create | update | merge | consent | preference | loyalty | request | interaction | export
	detail TEXT,
	created_at DATETIME NOT NULL DEFAULT (datetime('now', 'localtime'))
)");
$db->exec('CREATE INDEX IF NOT EXISTS ix_customer_audit_customer ON customer_audit_log (customer_id, id)');

$db->exec('CREATE INDEX IF NOT EXISTS ix_sales_customer_status ON sales (customer_id, status, created_timestamp)');

// Cliente anónimo de mostrador: el POS usa el id 1 como «Público general»
$db->exec("UPDATE customers SET is_anonymous = 1 WHERE id = 1");

// Identificadores normalizados de los clientes existentes
$rows = $db->query('SELECT id, name, tax_id, email, phone FROM customers')->fetchAll(\PDO::FETCH_ASSOC);
$upd = $db->prepare('UPDATE customers SET tax_id_norm = ?, email_norm = ?, phone_norm = ?, name_norm = ? WHERE id = ?');
foreach ($rows as $r)
{
	$n = \Grocy\Services\CustomerCrmService::NormalizeIdentifiers($r);
	$upd->execute([$n['tax_id_norm'], $n['email_norm'], $n['phone_norm'], $n['name_norm'], $r['id']]);
}

$set = $db->prepare("INSERT OR IGNORE INTO daily_system_settings (setting_key, setting_value, category, description) VALUES (?, ?, 'crm', ?)");
$set->execute(['crm_loyalty_enabled', '0', 'Programa de puntos activo (1) o no contemplado en el plan comercial (0)']);
$set->execute(['crm_loyalty_amount_per_point', '10', 'Importe neto de compra ($) que otorga 1 punto']);
$set->execute(['crm_loyalty_point_value', '0.10', 'Valor ($) de 1 punto al canjearlo']);
$set->execute(['crm_loyalty_start', date('Y-m-d'), 'Fecha desde la que las compras acumulan puntos']);
$set->execute(['crm_inactive_days', '60', 'Días sin compra a partir de los cuales un cliente se considera inactivo']);
$set->execute(['crm_new_days', '30', 'Días desde la primera compra en que un cliente se considera nuevo']);

// Permisos del CRM (debajo de Ventas) y asignación a supervisores de ventas
$parent = $db->query("SELECT id FROM permission_hierarchy WHERE name = 'SALES'")->fetchColumn();
$ins = $db->prepare('INSERT OR IGNORE INTO permission_hierarchy (name, parent) VALUES (?, ?)');
$ins->execute(['CUSTOMERS_PII', $parent ?: null]);
$ins->execute(['CUSTOMERS_MANAGE', $parent ?: null]);
foreach (['CUSTOMERS_PII', 'CUSTOMERS_MANAGE'] as $perm)
{
	$db->exec("INSERT INTO user_permissions (permission_id, user_id)
		SELECT (SELECT id FROM permission_hierarchy WHERE name = '$perm'), up.user_id FROM user_permissions up
		JOIN permission_hierarchy p ON p.id = up.permission_id
		WHERE p.name = 'SALES_SUPERVISE'
		AND NOT EXISTS (SELECT 1 FROM user_permissions x WHERE x.user_id = up.user_id AND x.permission_id = (SELECT id FROM permission_hierarchy WHERE name = '$perm'))");
}
