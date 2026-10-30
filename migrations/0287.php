<?php

// DAIRY Etapa 32: ingesta de ventas de POS externos (CSV, webhook/API). Cada venta externa se registra una sola vez por
// (origen, id externo) — reenvíos idénticos = duplicado, mismo id con otro contenido = conflicto — y se materializa con
// SalesService::CreateSale (descuenta stock FEFO, kardex, costo). Devoluciones/anulaciones totales usan CancelSale.
// Permiso POS_INTEGRATION (bajo el grupo de ventas): configurar e importar ventas externas.

$db = \Grocy\Services\DatabaseService::GetInstance()->GetDbConnectionRaw();
$db->exec("CREATE TABLE IF NOT EXISTS pos_ingest_batches (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	source TEXT NOT NULL,
	channel TEXT NOT NULL,
	file_name TEXT,
	received INTEGER NOT NULL DEFAULT 0,
	imported INTEGER NOT NULL DEFAULT 0,
	duplicates INTEGER NOT NULL DEFAULT 0,
	failed INTEGER NOT NULL DEFAULT 0,
	user_id INTEGER,
	row_created_timestamp DATETIME DEFAULT (datetime('now', 'localtime'))
)");
$db->exec("CREATE TABLE IF NOT EXISTS pos_external_sales (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	batch_id INTEGER,
	source TEXT NOT NULL,
	external_id TEXT NOT NULL,
	kind TEXT NOT NULL DEFAULT 'sale',
	branch_id INTEGER,
	occurred_at DATETIME,
	payload_json TEXT NOT NULL,
	payload_hash TEXT NOT NULL,
	status TEXT NOT NULL,
	sale_id INTEGER,
	error TEXT,
	attempts INTEGER NOT NULL DEFAULT 1,
	updated_timestamp DATETIME DEFAULT (datetime('now', 'localtime')),
	UNIQUE (source, external_id)
)");
$db->exec('CREATE INDEX IF NOT EXISTS ix_pos_external_status ON pos_external_sales (status, branch_id)');

$parent = $db->query("SELECT parent FROM permission_hierarchy WHERE name = 'SALES_POS'")->fetchColumn();
if ($parent !== false && $parent !== null)
{
	$db->prepare('INSERT OR IGNORE INTO permission_hierarchy (name, parent) VALUES (:n, :p)')->execute([':n' => 'POS_INTEGRATION', ':p' => intval($parent)]);
}
