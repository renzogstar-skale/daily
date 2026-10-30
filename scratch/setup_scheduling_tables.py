import sqlite3
import datetime

con = sqlite3.connect('c:/Users/renzo/grocy/grocy/data/grocy_es.db')
cur = con.cursor()

# 1. workforce_colombian_holidays
cur.execute("""
CREATE TABLE IF NOT EXISTS workforce_colombian_holidays (
    holiday_date DATE PRIMARY KEY,
    name TEXT NOT NULL,
    holiday_type TEXT NOT NULL,
    year INTEGER NOT NULL,
    notes TEXT
)
""")

def get_easter_date(year):
    a = year % 19
    b = year // 100
    c = year % 100
    d = b // 4
    e = b % 4
    f = (b + 8) // 25
    g = (b - f + 1) // 3
    h = (19 * a + b - d - g + 15) % 30
    i = c // 4
    k = c % 4
    l = (32 + 2 * e + 2 * i - h - k) % 7
    m = (a + 11 * h + 22 * l) // 451
    month = (h + l - 7 * m + 114) // 31
    day = ((h + l - 7 * m + 114) % 31) + 1
    return datetime.date(year, month, day)

def move_to_next_monday(dt):
    if dt.weekday() == 0:
        return dt
    days_to_add = (7 - dt.weekday()) % 7
    return dt + datetime.timedelta(days=days_to_add)

def get_colombian_holidays(year):
    easter = get_easter_date(year)
    holidays = []
    holidays.append((datetime.date(year, 1, 1), "Año Nuevo", "fijo"))
    holidays.append((datetime.date(year, 5, 1), "Día del Trabajo", "fijo"))
    holidays.append((datetime.date(year, 7, 20), "Grito de Independencia", "fijo"))
    holidays.append((datetime.date(year, 8, 7), "Batalla de Boyacá", "fijo"))
    holidays.append((datetime.date(year, 12, 8), "Inmaculada Concepción", "fijo"))
    holidays.append((datetime.date(year, 12, 25), "Navidad", "fijo"))
    holidays.append((move_to_next_monday(datetime.date(year, 1, 6)), "Reyes Magos", "emiliani"))
    holidays.append((move_to_next_monday(datetime.date(year, 3, 19)), "San José", "emiliani"))
    holidays.append((move_to_next_monday(datetime.date(year, 6, 29)), "San Pedro y San Pablo", "emiliani"))
    holidays.append((move_to_next_monday(datetime.date(year, 8, 15)), "Asunción de la Virgen", "emiliani"))
    holidays.append((move_to_next_monday(datetime.date(year, 10, 12)), "Día de la Raza", "emiliani"))
    holidays.append((move_to_next_monday(datetime.date(year, 11, 1)), "Todos los Santos", "emiliani"))
    holidays.append((move_to_next_monday(datetime.date(year, 11, 11)), "Independencia de Cartagena", "emiliani"))
    holidays.append((easter - datetime.timedelta(days=3), "Jueves Santo", "religioso"))
    holidays.append((easter - datetime.timedelta(days=2), "Viernes Santo", "religioso"))
    holidays.append((move_to_next_monday(easter + datetime.timedelta(days=39)), "Ascensión del Señor", "emiliani_religioso"))
    holidays.append((move_to_next_monday(easter + datetime.timedelta(days=60)), "Corpus Christi", "emiliani_religioso"))
    holidays.append((move_to_next_monday(easter + datetime.timedelta(days=68)), "Sagrado Corazón de Jesús", "emiliani_religioso"))
    return holidays

# Populate holidays 2026-2035
for yr in range(2026, 2036):
    hols = get_colombian_holidays(yr)
    for dt, name, htype in hols:
        cur.execute("""
            INSERT OR REPLACE INTO workforce_colombian_holidays (holiday_date, name, holiday_type, year)
            VALUES (?, ?, ?, ?)
        """, (dt.strftime('%Y-%m-%d'), name, htype, yr))

# 2. workforce_vacations
cur.execute("""
CREATE TABLE IF NOT EXISTS workforce_vacations (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    payroll_code TEXT NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    notes TEXT,
    status TEXT DEFAULT 'approved',
    created_timestamp DATETIME DEFAULT (datetime('now', 'localtime'))
)
""")

# Seed mandatory November 2026 vacations
# Marisol Méndez Sánchez (38042452): Hasta el 2 de noviembre inclusive
# Julio César Jara Pava (38031757): Hasta el 3 de noviembre inclusive
# Luciana Patricia Triana Carreño (38039919): Del 3 al 23 de noviembre inclusive
# Brayer Stifer Rubio Daza (38063818): Del 3 al 23 de noviembre inclusive
cur.execute("DELETE FROM workforce_vacations")
vacations_seed = [
    ('38042452', '2026-10-15', '2026-11-02', 'Vacaciones autorizadas octubre-noviembre'),
    ('38031757', '2026-10-15', '2026-11-03', 'Vacaciones autorizadas octubre-noviembre'),
    ('38039919', '2026-11-03', '2026-11-23', 'Vacaciones obligatorias noviembre 2026'),
    ('38063818', '2026-11-03', '2026-11-23', 'Vacaciones obligatorias noviembre 2026'),
]
for code, s_dt, e_dt, notes in vacations_seed:
    cur.execute("""
        INSERT INTO workforce_vacations (payroll_code, start_date, end_date, notes, status)
        VALUES (?, ?, ?, ?, 'approved')
    """, (code, s_dt, e_dt, notes))

# 3. workforce_skills_matrix
cur.execute("""
CREATE TABLE IF NOT EXISTS workforce_skills_matrix (
    payroll_code TEXT PRIMARY KEY,
    name TEXT NOT NULL,
    primary_area TEXT NOT NULL,
    secondary_areas TEXT DEFAULT '[]',
    can_night INTEGER DEFAULT 0,
    can_lead INTEGER DEFAULT 0,
    can_cashier INTEGER DEFAULT 0,
    authorized_cross_coverage INTEGER DEFAULT 1,
    min_weekly_hours REAL DEFAULT 42.0,
    notes TEXT
)
""")

# Seed 23 collaborators skills
cur.execute("SELECT payroll_code, name, department, cargo FROM workforce_planilla_collaborators ORDER BY department, name")
collabs = cur.fetchall()

# Who can do night shifts?
# From October spreadsheet: Rubio Daza (38063818), Mora Rodriguez (TEMP2), Prieto Hidalgo (38062365), Castro Arevalo (TEMP3), Zamudio Cajamarca (38061237), Melo Gonzalez (38039996), Lozano Bejarano (38046518)
night_eligible = {'38063818', 'TEMP2', '38062365', 'TEMP3', '38061237', '38039996', '38046518'}

cur.execute("DELETE FROM workforce_skills_matrix")
for code, name, dept, cargo in collabs:
    sec = []
    if dept == 'Accesorios':
        sec = ['Apple', 'Ventas']
    elif dept == 'Bodega Digital':
        sec = ['Despachos', 'Operaciones']
    elif dept == 'Despachos':
        sec = ['Bodega Digital', 'Operaciones']
    elif dept == 'Ventas y Operación':
        sec = ['Apple', 'Accesorios', 'Operaciones']
    elif dept == 'Virtual Teams':
        sec = ['Bodega Digital', 'Ventas']
    import json
    cur.execute("""
        INSERT INTO workforce_skills_matrix (payroll_code, name, primary_area, secondary_areas, can_night, can_lead, can_cashier, authorized_cross_coverage)
        VALUES (?, ?, ?, ?, ?, ?, ?, 1)
    """, (code, name, dept, json.dumps(sec), 1 if code in night_eligible else 0, 1 if 'LEAD' in cargo or 'SUPERVISOR' in cargo else 0, 1))

# 4. workforce_compensatory_bank
cur.execute("""
CREATE TABLE IF NOT EXISTS workforce_compensatory_bank (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    payroll_code TEXT NOT NULL,
    origin_sunday DATE NOT NULL,
    assigned_date DATE,
    status TEXT DEFAULT 'PENDIENTE',
    carried_over_month TEXT,
    notes TEXT,
    created_timestamp DATETIME DEFAULT (datetime('now', 'localtime'))
)
""")

con.commit()
print("Tables created and seeded successfully.")
cur.execute("SELECT count(*) FROM workforce_colombian_holidays")
print("Holidays seeded:", cur.fetchone()[0])
cur.execute("SELECT count(*) FROM workforce_vacations")
print("Vacations seeded:", cur.fetchone()[0])
cur.execute("SELECT count(*) FROM workforce_skills_matrix")
print("Skills matrix seeded:", cur.fetchone()[0])
con.close()
