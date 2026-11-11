<?php

// DAILY — CRM Retail (CustomerCrmService): extensión para campañas de retención, reglas de automatización,
// flujo separado de aprobación financiera para crédito, enlace logístico de reclamos de entrega a Despachos
// y adaptación regional (país, identificación fiscal, teléfonos y moneda).

$db = \Grocy\Services\DatabaseService::GetInstance()->GetDbConnectionRaw();

$db->exec("CREATE TABLE IF NOT EXISTS crm_campaigns (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	code TEXT NOT NULL UNIQUE,
	name TEXT NOT NULL,
	branch_id INTEGER,
	segment TEXT NOT NULL DEFAULT 'all', -- champions | loyal | potential | attention | at_risk | hibernating | none | all
	channel TEXT NOT NULL DEFAULT 'whatsapp', -- email | sms | whatsapp | pos | multichannel
	offer TEXT NOT NULL,
	status TEXT NOT NULL DEFAULT 'active', -- active | scheduled | paused | completed
	starts_at DATE NOT NULL,
	ends_at DATE NOT NULL,
	created_by INTEGER,
	created_at DATETIME NOT NULL DEFAULT (datetime('now', 'localtime')),
	updated_at DATETIME
)");
$db->exec('CREATE INDEX IF NOT EXISTS ix_crm_campaigns_status ON crm_campaigns (status, branch_id)');

$db->exec("CREATE TABLE IF NOT EXISTS crm_automation_rules (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	code TEXT NOT NULL UNIQUE,
	name TEXT NOT NULL,
	trigger_type TEXT NOT NULL, -- inactivity_risk | welcome_first_purchase | sla_escalation | incomplete_profile
	threshold_days INTEGER NOT NULL DEFAULT 30,
	min_amount REAL NOT NULL DEFAULT 0,
	target_segment TEXT,
	action_text TEXT NOT NULL,
	branch_id INTEGER,
	active INTEGER NOT NULL DEFAULT 1,
	last_run_at DATETIME,
	last_matches_count INTEGER NOT NULL DEFAULT 0,
	total_runs INTEGER NOT NULL DEFAULT 0,
	created_at DATETIME NOT NULL DEFAULT (datetime('now', 'localtime'))
)");

$reqCols = array_column($db->query('PRAGMA table_info(customer_requests)')->fetchAll(\PDO::FETCH_ASSOC), 'name');
$addReq = [
	'dispatch_order_id' => 'INTEGER',
	'requested_credit_limit' => 'REAL',
	'financial_status' => 'TEXT', // pending_finance | approved_finance | rejected_finance
	'financial_decided_by' => 'INTEGER',
	'financial_decided_at' => 'DATETIME',
	'financial_note' => 'TEXT'
];
foreach ($addReq as $col => $def)
{
	if (!in_array($col, $reqCols, true))
	{
		$db->exec("ALTER TABLE customer_requests ADD COLUMN $col $def");
	}
}

// Campañas iniciales del catálogo CRM (las métricas de conversión e ingresos siempre se calculan de ventas reales)
if (intval($db->query('SELECT COUNT(*) FROM crm_campaigns')->fetchColumn()) === 0)
{
	$today = date('Y-m-d');
	$ins = $db->prepare('INSERT OR IGNORE INTO crm_campaigns (code, name, branch_id, segment, channel, offer, status, starts_at, ends_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
	$ins->execute(['CMP-' . date('Y') . '-01', 'Reactivación de clientes en riesgo', null, 'at_risk', 'whatsapp', 'Cupón -15% en compras superiores a 80 + puntos dobles', 'active', date('Y-m-d', strtotime('-20 days')), date('Y-m-d', strtotime('+15 days'))]);
	$ins->execute(['CMP-' . date('Y') . '-02', 'Bienvenida y segunda compra', null, 'potential', 'sms', 'Bono de fidelización en segunda compra identificada en POS', 'active', date('Y-m-d', strtotime('-25 days')), date('Y-m-d', strtotime('+20 days'))]);
	$ins->execute(['CMP-' . date('Y') . '-03', 'Programa de lealtad semanal', null, 'loyal', 'email', 'Multiplicador de puntos en categorías frescas durante el mes', 'scheduled', $today, date('Y-m-d', strtotime('+30 days'))]);
}

// Reglas de automatización (last_matches_count = 0 hasta evaluarse contra la base real)
if (intval($db->query('SELECT COUNT(*) FROM crm_automation_rules')->fetchColumn()) === 0)
{
	$insR = $db->prepare('INSERT OR IGNORE INTO crm_automation_rules (code, name, trigger_type, threshold_days, min_amount, target_segment, action_text, active) VALUES (?, ?, ?, ?, ?, ?, ?, 1)');
	$insR->execute(['AUT-RISK', 'Alerta de riesgo por inactividad', 'inactivity_risk', 30, 0, 'at_risk', 'Identificar clientes frecuentes sin compras recientes para campaña de recuperación']);
	$insR->execute(['AUT-WELCOME', 'Seguimiento a compradores recientes', 'welcome_first_purchase', 14, 0, 'potential', 'Incentivar segunda compra en nuevos clientes identificados']);
	$insR->execute(['AUT-SLA', 'Escalamiento de casos fuera de SLA', 'sla_escalation', 0, 0, null, 'Priorizar solicitudes abiertas cuya fecha límite ya venció']);
	$insR->execute(['AUT-IDQ', 'Completitud de identificación fiscal y contacto', 'incomplete_profile', 0, 0, null, 'Marcar fichas sin documento, correo ni teléfono para completar en POS']);
}

// Inferir país inicial desde los parámetros institucionales existentes sin sobrescribir configuración previa
$cfg = array_column($db->query("SELECT setting_key, setting_value FROM daily_system_settings WHERE setting_key IN ('company_tax_id','timezone','default_currency','crm_country')")->fetchAll(\PDO::FETCH_ASSOC), 'setting_value', 'setting_key');
$inferredCountry = 'CO';
$taxStr = strtoupper((string)($cfg['company_tax_id'] ?? ''));
$tz = (string)($cfg['timezone'] ?? '');
$cur = strtoupper((string)($cfg['default_currency'] ?? 'USD'));
if (str_contains($taxStr, 'RFC') || $tz === 'America/Mexico_City' || $cur === 'MXN')
{
	$inferredCountry = 'MX';
}
elseif (str_contains($taxStr, 'RIF') || $tz === 'America/Caracas' || $cur === 'VES')
{
	$inferredCountry = 'VE';
}
elseif (str_contains($taxStr, 'CIF') || str_contains($taxStr, 'NIF') || $cur === 'EUR')
{
	$inferredCountry = 'ES';
}
elseif (str_contains($taxStr, 'NIT') || $tz === 'America/Bogota' || $cur === 'COP')
{
	$inferredCountry = 'CO';
}
elseif ($tz === 'America/New_York' || $cur === 'USD')
{
	$inferredCountry = 'US';
}

$set = $db->prepare("INSERT OR IGNORE INTO daily_system_settings (setting_key, setting_value, category, description) VALUES (?, ?, 'crm', ?)");
$set->execute(['crm_country', $inferredCountry, 'País de operación del CRM (CO, US, MX, VE, ES) para formatos fiscales y telefónicos']);
$set->execute(['crm_repurchase_goal_pct', '65', 'Meta (%) de tasa de recompra para el tablero CRM']);

