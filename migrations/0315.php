<?php

// DAILY — Módulo Administrativo: Economía de DAILY (Finanzas Internas SaaS, FinOps y Rentabilidad)

$db = \Grocy\Services\DatabaseService::GetInstance()->GetDbConnectionRaw();

// 1. Registro de Gastos de Infraestructura y Servicios Cloud
$db->exec("CREATE TABLE IF NOT EXISTS daily_saas_expenses (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	category TEXT NOT NULL, -- compute | database | storage | network | monitoring | backups | other
	provider TEXT NOT NULL, -- Hetzner | AWS | Supabase | Cloudflare | Fly.io | Sentry
	description TEXT,
	amount_usd REAL NOT NULL,
	billing_period TEXT NOT NULL, -- YYYY-MM
	is_estimate INTEGER NOT NULL DEFAULT 0, -- 1 si es proyectado/calculado, 0 si es factura real
	invoice_ref TEXT,
	recorded_by_user_id INTEGER,
	created_at DATETIME DEFAULT (datetime('now', 'localtime'))
)");
$db->exec('CREATE INDEX IF NOT EXISTS ix_dse_period ON daily_saas_expenses (billing_period, category)');

// 2. Contratos y Clientes SaaS para medición de rentabilidad unitaria
$db->exec("CREATE TABLE IF NOT EXISTS daily_saas_contracts (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	company_name TEXT NOT NULL,
	contact_email TEXT,
	stores_count INTEGER NOT NULL DEFAULT 1,
	users_count INTEGER NOT NULL DEFAULT 5,
	plan_code TEXT NOT NULL, -- basic | full_retail | enterprise_360
	plan_name TEXT NOT NULL,
	mrr_usd REAL NOT NULL,
	support_tier TEXT NOT NULL DEFAULT 'standard_8x5', -- standard_8x5 | premium_24x7
	storage_gb_used REAL DEFAULT 2.5,
	start_date DATE NOT NULL,
	renewal_date DATE NOT NULL,
	status TEXT NOT NULL DEFAULT 'active', -- active | trial | churned | pending
	created_at DATETIME DEFAULT (datetime('now', 'localtime'))
)");
$db->exec('CREATE INDEX IF NOT EXISTS ix_dsc_status ON daily_saas_contracts (status)');

// Semilla inicial si está vacía: Escenario Piloto + Cadena Retail 10 Tiendas (Demo Oficial)
$hasExp = (int)$db->query("SELECT COUNT(*) FROM daily_saas_expenses")->fetchColumn();
if ($hasExp === 0)
{
	$period = date('Y-m');
	$now = date('Y-m-d H:i:s');
	$insExp = $db->prepare("INSERT INTO daily_saas_expenses (category, provider, description, amount_usd, billing_period, is_estimate, invoice_ref, recorded_by_user_id, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?)");

	$insExp->execute(['compute', 'Hetzner Cloud', 'VPS Dedicado 8 vCPU / 32 GB RAM (Cluster DAILY)', 38.50, $period, 0, 'INV-2026-11-0941', $now]);
	$insExp->execute(['database', 'PostgreSQL HA', 'Motor Relacional Replicado WAL con backup diario', 24.00, $period, 0, 'INV-2026-11-0942', $now]);
	$insExp->execute(['storage', 'Cloudflare R2', 'Evidencias fotográficas, actas y CCTV (Zero Egress Fees)', 8.20, $period, 0, 'INV-2026-11-0943', $now]);
	$insExp->execute(['network', 'Cloudflare Pro', 'CDN, SSL, WAF y protección DDoS empresarial', 20.00, $period, 0, 'INV-2026-11-0944', $now]);
	$insExp->execute(['monitoring', 'Uptime & Logs', 'Monitoreo de latencia, alertas y trazabilidad auditada', 12.00, $period, 0, 'INV-2026-11-0945', $now]);
}

$hasCont = (int)$db->query("SELECT COUNT(*) FROM daily_saas_contracts")->fetchColumn();
if ($hasCont === 0)
{
	$now = date('Y-m-d H:i:s');
	$today = date('Y-m-d');
	$renewal = date('Y-m-d', strtotime('+1 year'));
	$insCont = $db->prepare("INSERT INTO daily_saas_contracts (company_name, contact_email, stores_count, users_count, plan_code, plan_name, mrr_usd, support_tier, storage_gb_used, start_date, renewal_date, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', ?)");

	// Cliente 1: Cadena Retail Demo Oficial (10 Tiendas, Plan Prevención 360°)
	$insCont->execute(['Supermercados Daily Retail S.A.', 'operaciones@dailyretail.com', 10, 48, 'enterprise_360', 'DAILY Enterprise 360°', 990.00, 'premium_24x7', 14.8, $today, $renewal, $now]);
	// Cliente 2: Piloto Hipermercado Central (1 Tienda, Plan Full Retail)
	$insCont->execute(['Distribuidora Andina de Alimentos', 'gerencia@distandina.com', 1, 8, 'full_retail', 'DAILY Full Retail', 120.00, 'standard_8x5', 2.1, $today, $renewal, $now]);
}
