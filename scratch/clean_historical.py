import sqlite3

con = sqlite3.connect('c:/Users/renzo/grocy/grocy/data/grocy_es.db')
cur = con.cursor()

# Remove HIST_ entries
cur.execute("DELETE FROM workforce_planilla_collaborators WHERE payroll_code LIKE 'HIST_%'")
con.commit()

cur.execute("SELECT count(*), payroll_code, name, department FROM workforce_planilla_collaborators GROUP BY payroll_code ORDER BY department, name")
rows = cur.fetchall()
print(f"Total colaboradores en planilla: {len(rows)}")
for r in rows:
    print(r)
con.close()
