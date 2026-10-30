import sqlite3

db = sqlite3.connect('data/grocy_es.db')
c = db.cursor()

c.execute("SELECT payroll_code, name, cargo, department, sunday_group, last_week_oct_rotation, next_nov_w1_rotation FROM workforce_planilla_collaborators ORDER BY department, name")
for r in c.fetchall():
    print(r)
