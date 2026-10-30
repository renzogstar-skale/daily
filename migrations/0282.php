<?php

// DAIRY Etapa 25: gestión completa de horarios.
// - Historial de cambios de turnos (valor anterior/nuevo, motivo, usuario) para auditar y deshacer borradores.
// - Versión del turno para detectar ediciones simultáneas.
// - Plantillas de turno recurrentes.
// - Correcciones de fichaje con valor original, corregido, motivo, solicitante, aprobación; el fichaje cerrado
//   solo cambia a través de una corrección aprobada (trigger) y nunca se borra.

$db = \Grocy\Services\DatabaseService::GetInstance()->GetDbConnectionRaw();

$cols = array_column($db->query('PRAGMA table_info(employee_shifts)')->fetchAll(\PDO::FETCH_ASSOC), 'name');
if (!in_array('version', $cols))
{
	$db->exec('ALTER TABLE employee_shifts ADD COLUMN version INTEGER NOT NULL DEFAULT 1');
}
if (!in_array('updated_by_user_id', $cols))
{
	$db->exec('ALTER TABLE employee_shifts ADD COLUMN updated_by_user_id INTEGER');
	$db->exec('ALTER TABLE employee_shifts ADD COLUMN updated_timestamp DATETIME');
}

$db->exec("
CREATE TABLE IF NOT EXISTS employee_shift_changes (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	shift_id INTEGER NOT NULL,
	branch_id INTEGER NOT NULL,
	action TEXT NOT NULL, -- create, update, move, cancel, publish, split, duplicate, swap, undo
	before_json TEXT,
	after_json TEXT,
	reason TEXT,
	batch_key TEXT, -- agrupa cambios de una misma operación (copiar semana, plantilla, masivo, dividir)
	user_id INTEGER,
	undone INTEGER NOT NULL DEFAULT 0,
	row_created_timestamp DATETIME DEFAULT (datetime('now', 'localtime'))
);
CREATE INDEX IF NOT EXISTS ix_shift_changes_shift ON employee_shift_changes (shift_id, id);
CREATE INDEX IF NOT EXISTS ix_shift_changes_branch ON employee_shift_changes (branch_id, id);

CREATE TABLE IF NOT EXISTS shift_templates (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	branch_id INTEGER NOT NULL,
	name TEXT NOT NULL,
	department TEXT,
	role TEXT,
	start_time TEXT NOT NULL,
	end_time TEXT NOT NULL,
	break_minutes INTEGER NOT NULL DEFAULT 0,
	weekdays TEXT NOT NULL DEFAULT '1,2,3,4,5', -- 1 = lunes … 7 = domingo
	active INTEGER NOT NULL DEFAULT 1,
	created_by_user_id INTEGER,
	row_created_timestamp DATETIME DEFAULT (datetime('now', 'localtime'))
);

CREATE TABLE IF NOT EXISTS employee_attendance_adjustments (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	attendance_id INTEGER, -- NULL = fichaje omitido (se crea al aprobar)
	employee_id INTEGER NOT NULL,
	shift_id INTEGER,
	original_clock_in DATETIME,
	original_clock_out DATETIME,
	new_clock_in DATETIME NOT NULL,
	new_clock_out DATETIME,
	reason TEXT NOT NULL,
	status TEXT NOT NULL DEFAULT 'requested', -- requested, applying, approved, rejected
	requested_by_user_id INTEGER NOT NULL,
	decided_by_user_id INTEGER,
	decided_timestamp DATETIME,
	decision_notes TEXT,
	row_created_timestamp DATETIME DEFAULT (datetime('now', 'localtime'))
);
CREATE INDEX IF NOT EXISTS ix_att_adj_att ON employee_attendance_adjustments (attendance_id, status);

DROP TRIGGER IF EXISTS employee_attendance_guard_update;
CREATE TRIGGER employee_attendance_guard_update BEFORE UPDATE ON employee_attendance
WHEN (NEW.clock_in IS NOT OLD.clock_in OR NEW.employee_id IS NOT OLD.employee_id OR (OLD.clock_out IS NOT NULL AND NEW.clock_out IS NOT OLD.clock_out))
	AND NOT EXISTS (SELECT 1 FROM employee_attendance_adjustments WHERE attendance_id = OLD.id AND status = 'applying')
BEGIN
	SELECT RAISE(ABORT, 'Un fichaje registrado solo se corrige mediante una corrección aprobada');
END;

DROP TRIGGER IF EXISTS employee_attendance_guard_delete;
CREATE TRIGGER employee_attendance_guard_delete BEFORE DELETE ON employee_attendance
BEGIN
	SELECT RAISE(ABORT, 'Los fichajes no se eliminan; registre una corrección');
END;

DROP TRIGGER IF EXISTS employee_attendance_adjustments_guard;
CREATE TRIGGER employee_attendance_adjustments_guard BEFORE UPDATE ON employee_attendance_adjustments
WHEN NEW.original_clock_in IS NOT OLD.original_clock_in OR NEW.original_clock_out IS NOT OLD.original_clock_out
	OR NEW.new_clock_in IS NOT OLD.new_clock_in OR NEW.new_clock_out IS NOT OLD.new_clock_out
	OR NEW.reason IS NOT OLD.reason OR NEW.requested_by_user_id IS NOT OLD.requested_by_user_id
	OR OLD.status IN ('approved', 'rejected')
BEGIN
	SELECT RAISE(ABORT, 'La corrección de fichaje es inalterable una vez registrada');
END;

DROP TRIGGER IF EXISTS employee_attendance_adjustments_nodelete;
CREATE TRIGGER employee_attendance_adjustments_nodelete BEFORE DELETE ON employee_attendance_adjustments
BEGIN
	SELECT RAISE(ABORT, 'Las correcciones de fichaje no se eliminan');
END;

DROP TRIGGER IF EXISTS employee_shift_changes_guard;
CREATE TRIGGER employee_shift_changes_guard BEFORE UPDATE ON employee_shift_changes
WHEN NEW.before_json IS NOT OLD.before_json OR NEW.after_json IS NOT OLD.after_json OR NEW.user_id IS NOT OLD.user_id OR NEW.action IS NOT OLD.action
BEGIN
	SELECT RAISE(ABORT, 'El historial de turnos es inalterable');
END;

DROP TRIGGER IF EXISTS employee_shift_changes_nodelete;
CREATE TRIGGER employee_shift_changes_nodelete BEFORE DELETE ON employee_shift_changes
BEGIN
	SELECT RAISE(ABORT, 'El historial de turnos no se elimina');
END;
");
