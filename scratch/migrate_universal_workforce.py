import sqlite3

db = sqlite3.connect('data/grocy_es.db')
c = db.cursor()

# 1. Create companies table
c.execute("""
CREATE TABLE IF NOT EXISTS companies (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    code TEXT NOT NULL UNIQUE,
    country TEXT NOT NULL DEFAULT 'CO',
    currency TEXT NOT NULL DEFAULT 'COP',
    timezone TEXT NOT NULL DEFAULT 'America/Bogota',
    active INTEGER NOT NULL DEFAULT 1,
    created_timestamp DATETIME DEFAULT (datetime('now', 'localtime'))
)
""")

# Seed default companies
c.execute("""
INSERT OR IGNORE INTO companies (id, name, code, country, currency, timezone, active)
VALUES (1, 'DAILY Enterprise — Cliente Matriz', 'DAILY-MATRIZ', 'CO', 'COP', 'America/Bogota', 1)
""")
c.execute("""
INSERT OR IGNORE INTO companies (id, name, code, country, currency, timezone, active)
VALUES (2, '[DEMO] Retail Corp Colombia', 'RETAIL-CORP-DEMO', 'CO', 'COP', 'America/Bogota', 1)
""")

# 2. Create workforce_company_policies table
c.execute("""
CREATE TABLE IF NOT EXISTS workforce_company_policies (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    company_id INTEGER NOT NULL,
    version INTEGER NOT NULL DEFAULT 1,
    effective_date DATE NOT NULL,
    policy_name TEXT NOT NULL,
    sunday_rule_type TEXT NOT NULL DEFAULT '3x1',
    sunday_cycle_length INTEGER NOT NULL DEFAULT 4,
    sunday_free_phase INTEGER NOT NULL DEFAULT 0,
    rotation_rule_type TEXT NOT NULL DEFAULT 'weekly_apertura_cierre',
    rotation_cycle_weeks INTEGER NOT NULL DEFAULT 2,
    max_nights_per_day INTEGER NOT NULL DEFAULT 1,
    compensatory_enabled INTEGER NOT NULL DEFAULT 1,
    compensatory_days_rule TEXT NOT NULL DEFAULT 'MON_FRI_NO_HOLIDAYS',
    max_weekly_hours REAL NOT NULL DEFAULT 42.0,
    min_rest_hours REAL NOT NULL DEFAULT 12.0,
    min_shift_hours REAL NOT NULL DEFAULT 4.0,
    max_shift_hours REAL NOT NULL DEFAULT 10.0,
    active INTEGER NOT NULL DEFAULT 1,
    created_by_user_id INTEGER DEFAULT 1,
    created_timestamp DATETIME DEFAULT (datetime('now', 'localtime')),
    updated_timestamp DATETIME DEFAULT (datetime('now', 'localtime')),
    FOREIGN KEY(company_id) REFERENCES companies(id)
)
""")

# Seed default policy v1 for company 1
c.execute("""
INSERT OR IGNORE INTO workforce_company_policies 
(id, company_id, version, effective_date, policy_name, sunday_rule_type, sunday_cycle_length, sunday_free_phase, rotation_rule_type, rotation_cycle_weeks, max_nights_per_day, compensatory_enabled, compensatory_days_rule, max_weekly_hours, min_rest_hours, active)
VALUES (1, 1, 1, '2026-01-01', 'Política Oficial Colombia Ley 2101 / Ley 51 (3x1)', '3x1', 4, 0, 'weekly_apertura_cierre', 2, 1, 1, 'MON_FRI_NO_HOLIDAYS', 42.0, 12.0, 1)
""")

# 3. Add columns to branches
c.execute("PRAGMA table_info(branches)")
branch_cols = [col[1] for col in c.fetchall()]
if 'company_id' not in branch_cols:
    c.execute("ALTER TABLE branches ADD COLUMN company_id INTEGER DEFAULT 1")
    print("Added company_id to branches")

# 4. Add columns to workforce_planilla_collaborators
c.execute("PRAGMA table_info(workforce_planilla_collaborators)")
collab_cols = [col[1] for col in c.fetchall()]

new_cols = [
    ('company_id', 'INTEGER DEFAULT 1'),
    ('reference_sunday', 'DATE'),
    ('reference_sunday_phase', 'INTEGER DEFAULT 0'),
    ('reference_rotation_monday', 'DATE'),
    ('reference_rotation_phase', 'TEXT DEFAULT "APERTURA"'),
    ('custom_night_authorized', 'INTEGER DEFAULT 0')
]

for col_name, col_def in new_cols:
    if col_name not in collab_cols:
        c.execute(f"ALTER TABLE workforce_planilla_collaborators ADD COLUMN {col_name} {col_def}")
        print(f"Added {col_name} to workforce_planilla_collaborators")

# 5. Add columns to employees
c.execute("PRAGMA table_info(employees)")
emp_cols = [col[1] for col in c.fetchall()]

for col_name, col_def in new_cols:
    if col_name not in emp_cols:
        c.execute(f"ALTER TABLE employees ADD COLUMN {col_name} {col_def}")
        print(f"Added {col_name} to employees")

# 6. Initialize individual reference values for all 23 collaborators
# Groups mapping:
# Group D: Phase 0 on 2026-11-01 (LIBRE) -> includes EDDY SEBASTIAN CUBIDES LINARES
# Group C: Phase 1 on 2026-11-01 (TRABAJA, free on Nov 22)
# Group B: Phase 2 on 2026-11-01 (TRABAJA, free on Nov 15)
# Group A: Phase 3 on 2026-11-01 (TRABAJA, free on Nov 8)

group_phases = {
    'D': 0, # Free Nov 1, Nov 29, Dec 27
    'C': 1, # Free Nov 22, Dec 20, Jan 17
    'B': 2, # Free Nov 15, Dec 13, Jan 10
    'A': 3  # Free Nov 8, Dec 6, Jan 3
}

c.execute("SELECT payroll_code, name, sunday_group, next_nov_w1_rotation FROM workforce_planilla_collaborators")
rows = c.fetchall()

for pcode, name, s_group, rot_w1 in rows:
    # Phase on 2026-11-01
    phase = group_phases.get(s_group, 0)
    rot_phase = rot_w1 if rot_w1 in ('APERTURA', 'CIERRE') else 'APERTURA'
    
    # Specific verification for Sebastián Cubides:
    if pcode == '38037244':
        phase = 0 # Must strictly be 0 (LIBRE on 2026-11-01, 2026-11-29, 2026-12-27)
        rot_phase = 'APERTURA'

    c.execute("""
        UPDATE workforce_planilla_collaborators 
        SET company_id = 1,
            reference_sunday = '2026-11-01',
            reference_sunday_phase = ?,
            reference_rotation_monday = '2026-11-02',
            reference_rotation_phase = ?
        WHERE payroll_code = ?
    """, (phase, rot_phase, pcode))

    c.execute("""
        UPDATE employees 
        SET company_id = 1,
            reference_sunday = '2026-11-01',
            reference_sunday_phase = ?,
            reference_rotation_monday = '2026-11-02',
            reference_rotation_phase = ?
        WHERE payroll_code = ?
    """, (phase, rot_phase, pcode))

db.commit()
print("Migration completed successfully.")
