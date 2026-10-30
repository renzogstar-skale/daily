import sqlite3

con = sqlite3.connect('c:/Users/renzo/grocy/grocy/data/grocy_es.db')
cur = con.cursor()

# 1. Create audit log table
cur.execute("""
CREATE TABLE IF NOT EXISTS workforce_shift_audit_log (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    shift_id INTEGER,
    employee_id INTEGER,
    payroll_code TEXT,
    shift_date DATE NOT NULL,
    action TEXT NOT NULL,
    old_value TEXT,
    new_value TEXT,
    reason TEXT,
    user_id INTEGER DEFAULT 1,
    user_name TEXT DEFAULT 'Manager',
    timestamp DATETIME DEFAULT (datetime('now', 'localtime'))
)
""")

# 2. Check and ensure 8 branches (1 Real + 7 Demo)
cur.execute("SELECT id, name FROM branches ORDER BY id")
existing_branches = cur.fetchall()
print("Existing branches:", existing_branches)

# Ensure branch 1 is Sucursal Matriz (Real)
cur.execute("UPDATE branches SET name = 'Sucursal Matriz' WHERE id = 1")

# Ensure demo branches are explicitly identified as [DEMO]
demo_branches = [
    (2, '[DEMO] Sucursal Norte', 'SUC-02', 'Av. Norte 120, Bogotá', '601-555-0102'),
    (3, '[DEMO] Sucursal Poniente', 'SUC-03', 'Calle 80 #68-30, Bogotá', '601-555-0103'),
    (4, '[DEMO] Sucursal Sur', 'SUC-04', 'Autopista Sur #45-10, Bogotá', '601-555-0104'),
    (5, '[DEMO] Sucursal Chapinero', 'SUC-05', 'Carrera 13 #58-20, Bogotá', '601-555-0105'),
    (6, '[DEMO] Sucursal Salitre', 'SUC-06', 'Av. Esperanza #68B-15, Bogotá', '601-555-0106'),
    (7, '[DEMO] Sucursal Fontibón', 'SUC-07', 'Calle 17 #98-40, Bogotá', '601-555-0107'),
    (8, '[DEMO] Sucursal Cedritos', 'SUC-08', 'Calle 140 #11-50, Bogotá', '601-555-0108')
]

for bid, bname, bcode, baddr, bphone in demo_branches:
    cur.execute("""
        INSERT INTO branches (id, name, code, address, phone, active, format, sales_area_m2, target_labor_pct)
        VALUES (?, ?, ?, ?, ?, 1, 'supermarket', 850.0, 8.5)
        ON CONFLICT(id) DO UPDATE SET name = excluded.name, code = excluded.code, address = excluded.address, phone = excluded.phone
    """, (bid, bname, bcode, baddr, bphone))

con.commit()

cur.execute("SELECT id, name, code FROM branches ORDER BY id")
print("Updated branches:", cur.fetchall())
con.close()
print("Setup complete.")
