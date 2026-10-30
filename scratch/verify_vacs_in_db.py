import sqlite3

con = sqlite3.connect('c:/Users/renzo/grocy/grocy/data/grocy_es.db')
cur = con.cursor()

print("VC shifts count in grocy_es.db:")
cur.execute("SELECT count(*) FROM workforce_monthly_schedules WHERE shift_code='VC'")
print("Count:", cur.fetchone()[0])

print("\nVacation shifts for the 4 collaborators in November:")
cur.execute("""
    SELECT payroll_code, shift_date, shift_code 
    FROM workforce_monthly_schedules 
    WHERE shift_code='VC' AND shift_date BETWEEN '2026-11-01' AND '2026-11-30'
    ORDER BY payroll_code, shift_date
""")
rows = cur.fetchall()
for r in rows:
    print(r)
