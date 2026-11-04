<?php

// DAILY — Generador de programación con personal real: nombres completos y grupos de la hoja "Empleados dominical",
// origen de cada turno (la planilla original es historia confirmada y el motor no la reescribe) y bitácora por versión.

$db = \Grocy\Services\DatabaseService::GetInstance()->GetDbConnectionRaw();

$hasColumn = fn(string $table, string $col) => in_array($col, array_column($db->query("PRAGMA table_info($table)")->fetchAll(\PDO::FETCH_ASSOC), 'name'), true);

if (!$hasColumn('workforce_planilla_collaborators', 'full_name'))
{
	$db->exec('ALTER TABLE workforce_planilla_collaborators ADD COLUMN full_name TEXT');
}
if (!$hasColumn('workforce_monthly_schedules', 'source'))
{
	$db->exec('ALTER TABLE workforce_monthly_schedules ADD COLUMN source TEXT');
}
if (!$hasColumn('workforce_schedule_drafts', 'version'))
{
	$db->exec('ALTER TABLE workforce_schedule_drafts ADD COLUMN version INTEGER NOT NULL DEFAULT 1');
}

$db->exec("CREATE TABLE IF NOT EXISTS workforce_schedule_draft_log (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	draft_id INTEGER NOT NULL REFERENCES workforce_schedule_drafts (id) ON DELETE CASCADE,
	action TEXT NOT NULL, -- generated | edited | recalculated | replaced | approved | discarded
	detail TEXT,
	user_id INTEGER,
	created_at DATETIME DEFAULT (datetime('now', 'localtime'))
)");

// Encabezado de la hoja Turnos SAP que quedó cargado como turno, y licencia remunerada mal clasificada como cierre
$db->exec("DELETE FROM workforce_sap_shifts WHERE code LIKE 'C_digo PHTD'");
$db->exec("UPDATE workforce_sap_shifts SET classification = 'PERMISO' WHERE code = 'LIC'");

$people = [
	['38041537', 'Lionel Messi', 'A'], ['38053738', 'Cristiano Ronaldo', 'B'], ['38031757', 'Shakira Mebarak', 'C'],
	['38042452', 'Bad Bunny', 'D'], ['38039996', 'Karol G', 'A'], ['38039919', 'Dwayne Johnson', 'B'], ['38048219', 'Taylor Swift', 'C'],
	['38046518', 'Selena Gomez', 'D'], ['38053744', 'Leonardo DiCaprio', 'A'],
	['38063818', 'Jennifer Lopez', 'B'], ['38034789', 'Will Smith', 'C'], ['38061237', 'Tom Cruise', 'D'], ['38062365', 'Rihanna Fenty', 'A'],
	['38035685', 'Drake Graham', 'B'], ['38035376', 'Beyoncé Knowles', 'C'], ['38079297', 'Justin Bieber', 'D'], ['38082394', 'Ariana Grande', 'A'],
	['38083990', 'Brad Pitt', 'B'], ['TEMP2', 'Dua Lipa', 'C'], ['TEMP3', 'Robert Downey Jr.', 'D'], ['38035662', 'Margot Robbie', 'C'],
	['38050957', 'Billie Eilish', 'A'], ['38037244', 'Vin Diesel', 'B'],
];
$upd = $db->prepare('UPDATE workforce_planilla_collaborators SET full_name = :n, sunday_group = :g WHERE payroll_code = :c');
foreach ($people as [$code, $name, $group])
{
	$upd->execute([':n' => $name, ':g' => $group, ':c' => $code]);
}
