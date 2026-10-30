<?php

// DAIRY Centro de Alertas v3: procedimiento guiado por alerta (pasos con evidencia), cierre con impacto final y resultado,
// vínculo de recurrencia con el incidente anterior, planes preventivos con verificación de efectividad y rol Supervisor.
// Solo agrega columnas/tablas/permisos; no altera datos operativos. Idempotente.

$db = \Grocy\Services\DatabaseService::GetInstance()->GetDbConnectionRaw();

$have = array_column($db->query('PRAGMA table_info(dairy_alerts)')->fetchAll(\PDO::FETCH_ASSOC), 'name');
foreach ([
	'recurrence_of' => 'INTEGER', // alerta cerrada anterior de la misma regla, sucursal y producto (90 días)
	'root_cause' => 'TEXT',
	'outcome' => 'TEXT', // resolved | mitigated | no_issue | loss_confirmed
	'final_value' => 'REAL', // impacto final registrado al cerrar
	'prevention_id' => 'INTEGER',
] as $name => $def)
{
	if (!in_array($name, $have, true))
	{
		$db->exec("ALTER TABLE dairy_alerts ADD COLUMN $name $def");
	}
}

$db->exec("
CREATE TABLE IF NOT EXISTS dairy_alert_steps (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	alert_id INTEGER NOT NULL REFERENCES dairy_alerts(id),
	step_no INTEGER NOT NULL,
	label TEXT NOT NULL,
	href TEXT,
	requires_evidence INTEGER NOT NULL DEFAULT 0,
	status TEXT NOT NULL DEFAULT 'pending', -- pending | done | skipped
	note TEXT,
	done_by_user_id INTEGER,
	done_at DATETIME,
	UNIQUE (alert_id, step_no)
);

CREATE TABLE IF NOT EXISTS dairy_alert_prevention (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	source_alert_id INTEGER NOT NULL,
	rule_code TEXT,
	branch_id INTEGER,
	product_id INTEGER,
	root_cause TEXT NOT NULL,
	corrective_action TEXT NOT NULL,
	owner_user_id INTEGER,
	target_date DATE,
	indicator TEXT,
	status TEXT NOT NULL DEFAULT 'open', -- open | effective | ineffective
	verification_note TEXT,
	verified_by_user_id INTEGER,
	verified_at DATETIME,
	created_by_user_id INTEGER,
	created_at DATETIME NOT NULL
);
CREATE INDEX IF NOT EXISTS ix_dairy_alert_prevention_key ON dairy_alert_prevention(rule_code, branch_id, product_id, status);
CREATE INDEX IF NOT EXISTS ix_dairy_alerts_due ON dairy_alerts(status, due_at);
");

// Supervisor de alertas: asigna, investiga, escala y valida dentro de su alcance (bajo LOSS_PREVENTION)
$group = intval($db->query("SELECT id FROM permission_hierarchy WHERE name = 'LOSS_PREVENTION'")->fetchColumn());
if ($group)
{
	$db->prepare('INSERT OR IGNORE INTO permission_hierarchy (name, parent) VALUES (?, ?)')->execute(['ALERTS_SUPERVISE', $group]);
}
