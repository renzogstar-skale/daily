<?php

// DAIRY Centro de Alertas v4: análisis de causa raíz (5 porqués + categoría 6M), historial de cambios de reglas y
// umbral de notificaciones por usuario (evita notificaciones excesivas). Solo agrega columnas/tablas. Idempotente.

$db = \Grocy\Services\DatabaseService::GetInstance()->GetDbConnectionRaw();

$have = array_column($db->query('PRAGMA table_info(dairy_alerts)')->fetchAll(\PDO::FETCH_ASSOC), 'name');
foreach (['root_cause_category' => 'TEXT', 'five_whys' => 'TEXT'] as $name => $def)
{
	if (!in_array($name, $have, true))
	{
		$db->exec("ALTER TABLE dairy_alerts ADD COLUMN $name $def");
	}
}

$db->exec("
CREATE TABLE IF NOT EXISTS dairy_alert_rule_changes (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	rule_code TEXT NOT NULL,
	user_id INTEGER,
	changes TEXT NOT NULL, -- JSON {campo: [antes, después]}
	created_at DATETIME NOT NULL
);
CREATE INDEX IF NOT EXISTS ix_dairy_alert_rule_changes ON dairy_alert_rule_changes(rule_code, id);
CREATE INDEX IF NOT EXISTS ix_dairy_alert_events_action ON dairy_alert_events(action, created_at);
");
