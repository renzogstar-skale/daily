import sqlite3

con = sqlite3.connect('c:/Users/renzo/grocy/grocy/data/grocy_es.db')
cur = con.cursor()

# 1. Nights check across 2026-11 to 2027-02 (120 days)
cur.execute("""
    SELECT shift_date, count(*) 
    FROM workforce_monthly_schedules 
    WHERE shift_code LIKE '7N%' AND shift_date BETWEEN '2026-11-01' AND '2027-02-28'
    GROUP BY shift_date 
    HAVING count(*) != 1
""")
bad_nights = cur.fetchall()
print("Total days with != 1 night shift from Nov 2026 to Feb 2027 (120 days):", len(bad_nights))

# 2. Holidays check
cur.execute("""
    SELECT m.shift_date, m.payroll_code, h.name
    FROM workforce_monthly_schedules m
    JOIN workforce_colombian_holidays h ON h.holiday_date = m.shift_date
    WHERE m.shift_code = 'COMP' AND m.shift_date BETWEEN '2026-11-01' AND '2027-02-28'
""")
bad_comps = cur.fetchall()
print("Total compensatories assigned on official Colombian holidays:", len(bad_comps))

# 3. Verify publication records
cur.execute("SELECT period_name, start_date, end_date, status, published_by FROM workforce_schedule_publications ORDER BY period_name")
print("\nPublications table:")
for row in cur.fetchall():
    print(row)
