-- DAILY — 12. Optimizar Prevención de Pérdidas
-- 1) Normalizar estados heredados en español para métricas y listados consistentes
UPDATE sensitive_investigation_cases SET workflow_status = 'resuelto' WHERE workflow_status IN ('closed', 'resolved');
UPDATE sensitive_investigation_cases SET workflow_status = 'en_investigacion' WHERE workflow_status = 'in_progress';
UPDATE sensitive_investigation_cases SET workflow_status = 'pendiente_aprobacion' WHERE workflow_status = 'pending_approval';

-- 2) Garantizar que ningún expediente quede sin responsable asignado
UPDATE sensitive_investigation_cases
SET assigned_user_id = COALESCE(
	NULLIF(created_by_user_id, 0),
	(SELECT ub.user_id FROM user_branches ub WHERE ub.branch_id = sensitive_investigation_cases.branch_id ORDER BY ub.user_id LIMIT 1),
	(SELECT id FROM users ORDER BY id LIMIT 1)
)
WHERE assigned_user_id IS NULL OR assigned_user_id = 0;

-- 3) Tabla de auditoría estricta de accesos a CCTV, datos sensibles e IMEI
CREATE TABLE IF NOT EXISTS security_access_audit_log (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	user_id INTEGER NOT NULL,
	branch_id INTEGER,
	case_id INTEGER,
	device_id INTEGER,
	access_type TEXT NOT NULL, -- cctv_view | cctv_attach | imei_reveal | evidence_view | pos_audit | case_view
	resource_ref TEXT,
	authorized_by TEXT,
	ip_address TEXT,
	notes TEXT,
	created_at DATETIME NOT NULL DEFAULT (datetime('now', 'localtime'))
);
CREATE INDEX IF NOT EXISTS ix_security_access_audit_type_date ON security_access_audit_log (access_type, created_at);
CREATE INDEX IF NOT EXISTS ix_security_access_audit_case ON security_access_audit_log (case_id, created_at);

