-- Optimización #15 Operaciones y Personal:
-- Reglas laborales multijurisdicción (país / jurisdicción), libro mayor de compensatorios y vacaciones,
-- clasificación horaria de turnos (apertura/cierre/nocturno), intercambios bidireccionales y auditoría de cobertura.

-- 1. País y jurisdicción en sucursales y políticas laborales
ALTER TABLE branches ADD COLUMN country_code TEXT NOT NULL DEFAULT 'CO';
ALTER TABLE branches ADD COLUMN jurisdiction_code TEXT NOT NULL DEFAULT 'CO-GEN';

ALTER TABLE workforce_company_policies ADD COLUMN country_code TEXT NOT NULL DEFAULT 'CO';
ALTER TABLE workforce_company_policies ADD COLUMN jurisdiction_code TEXT NOT NULL DEFAULT 'CO-GEN';
ALTER TABLE workforce_company_policies ADD COLUMN night_start_hour INTEGER NOT NULL DEFAULT 19;
ALTER TABLE workforce_company_policies ADD COLUMN night_end_hour INTEGER NOT NULL DEFAULT 6;
ALTER TABLE workforce_company_policies ADD COLUMN night_premium_pct REAL NOT NULL DEFAULT 0.35;
ALTER TABLE workforce_company_policies ADD COLUMN holiday_premium_pct REAL NOT NULL DEFAULT 0.90;
ALTER TABLE workforce_company_policies ADD COLUMN overtime_day_pct REAL NOT NULL DEFAULT 0.25;
ALTER TABLE workforce_company_policies ADD COLUMN overtime_night_pct REAL NOT NULL DEFAULT 0.75;
ALTER TABLE workforce_company_policies ADD COLUMN max_daily_hours REAL NOT NULL DEFAULT 10.0;
ALTER TABLE workforce_company_policies ADD COLUMN max_daily_overtime REAL NOT NULL DEFAULT 2.0;
ALTER TABLE workforce_company_policies ADD COLUMN max_weekly_overtime REAL NOT NULL DEFAULT 12.0;

-- 2. Catálogo de perfiles laborales por país y jurisdicción
CREATE TABLE IF NOT EXISTS workforce_jurisdiction_profiles (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	country_code TEXT NOT NULL,
	jurisdiction_code TEXT NOT NULL,
	country_name TEXT NOT NULL,
	jurisdiction_name TEXT NOT NULL,
	legal_reference TEXT NOT NULL,
	max_weekly_hours REAL NOT NULL,
	max_daily_hours REAL NOT NULL,
	min_rest_hours REAL NOT NULL,
	max_daily_overtime REAL NOT NULL,
	max_weekly_overtime REAL NOT NULL,
	night_start_hour INTEGER NOT NULL,
	night_end_hour INTEGER NOT NULL,
	night_premium_pct REAL NOT NULL,
	sunday_holiday_premium_pct REAL NOT NULL,
	overtime_day_pct REAL NOT NULL,
	overtime_night_pct REAL NOT NULL,
	sunday_rule_type TEXT NOT NULL DEFAULT '3x1',
	annual_vacation_days REAL NOT NULL DEFAULT 15.0,
	compensatory_expire_days INTEGER NOT NULL DEFAULT 14,
	currency_code TEXT NOT NULL DEFAULT 'USD',
	active INTEGER NOT NULL DEFAULT 1,
	UNIQUE(country_code, jurisdiction_code)
);

INSERT OR IGNORE INTO workforce_jurisdiction_profiles
	(country_code, jurisdiction_code, country_name, jurisdiction_name, legal_reference, max_weekly_hours, max_daily_hours, min_rest_hours, max_daily_overtime, max_weekly_overtime, night_start_hour, night_end_hour, night_premium_pct, sunday_holiday_premium_pct, overtime_day_pct, overtime_night_pct, sunday_rule_type, annual_vacation_days, compensatory_expire_days, currency_code)
VALUES
	('CO', 'CO-GEN', 'Colombia', 'Nacional (CST / Ley 2101 / Ley 2466)', 'CST Art. 160-179, Ley 2101/2021, Ley 2466/2025', 42.0, 10.0, 12.0, 2.0, 12.0, 19, 6, 0.35, 0.90, 0.25, 0.75, '3x1', 15.0, 14, 'COP'),
	('MX', 'MX-LFT', 'México', 'Federal (Ley Federal del Trabajo)', 'LFT Art. 58-73 (48h diurna / 42h nocturna / prima dom. 25%)', 48.0, 11.0, 12.0, 3.0, 9.0, 20, 6, 0.00, 0.25, 1.00, 1.00, '1x1', 12.0, 30, 'MXN'),
	('PE', 'PE-DLEG728', 'Perú', 'Régimen General (D.Leg. 728 / DS 007-2002)', 'Constitución Art. 25, D.Leg. 728 (48h, nocturno 35%, +25%/+35%)', 48.0, 10.0, 12.0, 4.0, 16.0, 22, 6, 0.35, 1.00, 0.25, 0.35, '2x1', 30.0, 30, 'PEN'),
	('CL', 'CL-DT', 'Chile', 'Comercio y Retail (Ley 21.561)', 'Código del Trabajo Chile & Ley 21.561 (40h / 2 dom. libres mes)', 40.0, 10.0, 12.0, 2.0, 12.0, 21, 7, 0.30, 0.30, 0.50, 0.50, '2x1', 15.0, 15, 'CLP'),
	('ES', 'ES-ET', 'España', 'Estatuto de los Trabajadores (Art. 34)', 'ET Art. 34-37 (40h, 12h descanso diario, 36h semanal, 80h/año)', 40.0, 9.0, 12.0, 2.0, 8.0, 22, 6, 0.25, 0.75, 0.25, 0.50, '2x1', 22.0, 120, 'EUR'),
	('US', 'US-FED', 'Estados Unidos', 'Federal (FLSA)', 'Fair Labor Standards Act (40h umbral 1.5x semanal)', 40.0, 12.0, 8.0, 4.0, 20.0, 22, 6, 0.00, 0.00, 0.50, 0.50, '1x1', 10.0, 30, 'USD'),
	('US', 'US-CA', 'Estados Unidos', 'California (CA Labor Code)', 'CA Labor Code Sec. 510 (1.5x >8h/día, 2x >12h/día, 7mo día)', 40.0, 12.0, 10.0, 4.0, 20.0, 22, 6, 0.10, 0.50, 0.50, 1.00, '1x1', 10.0, 30, 'USD');

-- 3. Clasificación operativa explícita de turnos en employee_shifts
ALTER TABLE employee_shifts ADD COLUMN shift_band TEXT;
ALTER TABLE employee_shifts ADD COLUMN is_overnight INTEGER NOT NULL DEFAULT 0;
ALTER TABLE employee_shifts ADD COLUMN compensatory_origin_date DATE;

UPDATE employee_shifts
SET
	is_overnight = CASE WHEN end_time <= start_time THEN 1 ELSE 0 END,
	shift_band = CASE
		WHEN end_time <= start_time OR start_time >= '21:00' OR start_time < '04:00' THEN 'nocturno'
		WHEN start_time < '10:30' THEN 'apertura'
		WHEN end_time >= '20:00' OR start_time >= '13:30' THEN 'cierre'
		ELSE 'intermedio'
	END
WHERE shift_band IS NULL;

-- 4. Intercambios bidireccionales / cesiones
ALTER TABLE shift_swap_requests ADD COLUMN swap_mode TEXT NOT NULL DEFAULT 'transfer';
ALTER TABLE shift_swap_requests ADD COLUMN target_shift_id INTEGER;

-- 5. Banco y libro mayor (Ledger) de Compensatorios y Vacaciones
ALTER TABLE workforce_compensatory_bank ADD COLUMN payout_amount REAL NOT NULL DEFAULT 0;
ALTER TABLE workforce_compensatory_bank ADD COLUMN redeemed_shift_id INTEGER;
ALTER TABLE workforce_compensatory_bank ADD COLUMN expired_timestamp DATETIME;

CREATE TABLE IF NOT EXISTS workforce_compensatory_ledger (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	employee_id INTEGER,
	payroll_code TEXT NOT NULL,
	branch_id INTEGER DEFAULT 1,
	movement_type TEXT NOT NULL, -- ACCRUAL | REDEEMED | PAYOUT | EXPIRED | ADJUSTMENT
	hours REAL NOT NULL DEFAULT 8.0,
	origin_date DATE,
	due_date DATE,
	shift_id INTEGER,
	payout_amount REAL NOT NULL DEFAULT 0,
	status TEXT NOT NULL DEFAULT 'ACTIVE',
	notes TEXT,
	created_by_user_id INTEGER,
	created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS ix_wcl_emp ON workforce_compensatory_ledger(payroll_code, created_at);

CREATE TABLE IF NOT EXISTS workforce_vacation_balances (
	id INTEGER PRIMARY KEY AUTOINCREMENT,
	employee_id INTEGER NOT NULL,
	payroll_code TEXT,
	period_year INTEGER NOT NULL,
	accrued_days REAL NOT NULL DEFAULT 15.0,
	taken_days REAL NOT NULL DEFAULT 0.0,
	pending_days REAL NOT NULL DEFAULT 0.0,
	updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	UNIQUE(employee_id, period_year)
);

-- Sembrar libro mayor desde el banco de compensatorios existente sin duplicar
INSERT INTO workforce_compensatory_ledger (employee_id, payroll_code, branch_id, movement_type, hours, origin_date, due_date, status, notes)
SELECT
	e.id,
	cb.payroll_code,
	COALESCE(e.branch_id, 1),
	CASE WHEN cb.status IN ('SCHEDULED', 'TAKEN') THEN 'REDEEMED' ELSE 'ACCRUAL' END,
	CASE WHEN cb.status IN ('SCHEDULED', 'TAKEN') THEN -8.0 ELSE 8.0 END,
	cb.origin_sunday,
	cb.due_date,
	cb.status,
	COALESCE(cb.notes, 'Saldo histórico sincronizado')
FROM workforce_compensatory_bank cb
LEFT JOIN employees e ON e.payroll_code = cb.payroll_code
WHERE NOT EXISTS (SELECT 1 FROM workforce_compensatory_ledger l WHERE l.payroll_code = cb.payroll_code AND l.origin_date = cb.origin_sunday);

-- Sembrar saldos anuales de vacaciones para empleados activos
INSERT OR IGNORE INTO workforce_vacation_balances (employee_id, payroll_code, period_year, accrued_days, taken_days, pending_days)
SELECT
	e.id,
	e.payroll_code,
	2026,
	15.0,
	COALESCE((SELECT SUM(CAST(julianday(v.end_date) - julianday(v.start_date) + 1 AS REAL))
	          FROM workforce_vacations v
	          WHERE v.payroll_code = e.payroll_code AND COALESCE(v.status, 'APPROVED') = 'APPROVED'), 0.0),
	COALESCE((SELECT SUM(CAST(julianday(a.end_date) - julianday(a.start_date) + 1 AS REAL))
	          FROM employee_absences a
	          WHERE a.employee_id = e.id AND a.absence_type = 'vacation' AND a.status = 'requested'), 0.0)
FROM employees e
WHERE e.active = 1;

