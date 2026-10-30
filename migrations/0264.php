<?php

// Enterprise Platform Migration: Rebaja automática FEFO (markdown por días al vencimiento)
// El lote con vencimiento más próximo de cada producto determina el % de rebaja permitido en POS

use Grocy\Services\DatabaseService;

$db = DatabaseService::GetInstance()->GetDbConnectionRaw();

$db->exec("
CREATE TABLE IF NOT EXISTS markdown_rules (
	id INTEGER PRIMARY KEY AUTOINCREMENT UNIQUE,
	name TEXT NOT NULL,
	min_days INTEGER NOT NULL,
	max_days INTEGER NOT NULL,
	discount_pct REAL NOT NULL,
	active TINYINT NOT NULL DEFAULT 1,
	row_created_timestamp DATETIME DEFAULT (datetime('now', 'localtime'))
)
");

if ($db->query("SELECT COUNT(*) FROM markdown_rules")->fetchColumn() == 0)
{
	$db->exec("
		INSERT INTO markdown_rules (name, min_days, max_days, discount_pct, active) VALUES
		('Liquidación final', 0, 1, 50, 1),
		('Urgencia máxima', 2, 3, 30, 1),
		('Atención media', 4, 7, 15, 1)
	");
}
