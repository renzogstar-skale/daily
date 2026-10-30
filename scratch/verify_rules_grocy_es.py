import sqlite3

con = sqlite3.connect('c:/Users/renzo/grocy/grocy/data/grocy_es.db')
cur = con.cursor()

# 1. Holidays check for COMP
cur.execute("SELECT * FROM workforce_monthly_schedules WHERE shift_code='COMP' AND shift_date IN ('2026-11-02', '2026-11-16')")
comps_on_holidays = cur.fetchall()
print("COMP on Nov 2 or 16 holidays:", len(comps_on_holidays))

# 2. Night shift check
cur.execute("""
    SELECT shift_date, count(*) 
    FROM workforce_monthly_schedules 
    WHERE shift_code LIKE '7N%' AND shift_date BETWEEN '2026-11-01' AND '2026-11-30'
    GROUP BY shift_date 
    HAVING count(*) != 1
""")
bad_nights = cur.fetchall()
print("Days with != 1 night shift:", len(bad_nights))

# 3. Sunday 3x1 check
sundays = ['2026-11-01', '2026-11-08', '2026-11-15', '2026-11-22', '2026-11-29']
for sun in sundays:
    cur.execute(f"SELECT count(*) FROM workforce_monthly_schedules WHERE shift_date='{sun}' AND shift_code IN ('LIBRE', 'LBRE', 'VC')")
    resting_or_vac = cur.fetchone()[0]
    cur.execute(f"SELECT count(*) FROM workforce_monthly_schedules WHERE shift_date='{sun}' AND shift_code NOT IN ('LIBRE', 'LBRE', 'VC')")
    working = cur.fetchone()[0]
    print(f"Sunday {sun}: {working} working, {resting_or_vac} resting/vacation (Total: {working+resting_or_vac})")

# 4. Total collaborators in Nov 2026
cur.execute("SELECT count(DISTINCT payroll_code) FROM workforce_monthly_schedules WHERE shift_date BETWEEN '2026-11-01' AND '2026-11-30'")
print("Distinct collaborators in Nov 2026:", cur.fetchone()[0])
