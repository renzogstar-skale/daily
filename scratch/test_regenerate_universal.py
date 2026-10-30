import sqlite3
import urllib.request
import json

# Call GenerateMonth for Nov 2026, Dec 2026, Jan 2027 via API or PHP
import subprocess

cmd = "php -r \"require_once 'app.php'; $svc = \Grocy\Services\WorkforceAutoScheduleService::GetInstance(); $svc->GenerateMonth(1, 2026, 11, true); $svc->GenerateMonth(1, 2026, 12, true); $svc->GenerateMonth(1, 2027, 1, true);\""
res = subprocess.run(cmd, shell=True, capture_output=True, text=True)
print("PHP Execution Returncode:", res.returncode)
if res.stderr:
    print("PHP Stderr:", res.stderr)

db = sqlite3.connect('data/grocy_es.db')
c = db.cursor()

# 1. Verify Sebastián Cubides (payroll code: 38037244)
c.execute("""
    SELECT shift_date, shift_code, shift_type, hours, notes 
    FROM workforce_monthly_schedules 
    WHERE payroll_code = '38037244' AND is_sunday = 1 AND shift_date BETWEEN '2026-11-01' AND '2027-01-31'
    ORDER BY shift_date
""")
rows = c.fetchall()
print("\n=== SEBASTIÁN CUBIDES (38037244) SUNDAY SCHEDULE ===")
seb_ok = True
expected_seb = {
    '2026-11-01': 'LIBRE',
    '2026-11-08': '7A7-14', # or other work shift
    '2026-11-15': '7A7-14',
    '2026-11-22': '7A7-14',
    '2026-11-29': 'LIBRE',
    '2026-12-06': '7A7-14',
    '2026-12-13': '7A7-14',
    '2026-12-20': '7A7-14',
    '2026-12-27': 'LIBRE'
}

for r in rows:
    dt, code, stype, h, notes = r
    is_free = (code == 'LIBRE')
    print(f"Domingo {dt}: Code={code}, Type={stype}, Hours={h}")
    if dt in expected_seb:
        exp = expected_seb[dt]
        if exp == 'LIBRE' and not is_free:
            print(f"  --> ERROR: Expected LIBRE on {dt}, got {code}")
            seb_ok = False
        elif exp != 'LIBRE' and is_free:
            print(f"  --> ERROR: Expected TRABAJA on {dt}, got LIBRE")
            seb_ok = False

print("\nSEBASTIÁN VERIFICATION:", "PASÓ PERFECTO" if seb_ok else "FALLÓ")

# 2. Verify all other 22 collaborators have their own distinct Sunday schedules
c.execute("""
    SELECT c.name, s.shift_date, s.shift_code
    FROM workforce_monthly_schedules s
    JOIN workforce_planilla_collaborators c ON c.payroll_code = s.payroll_code
    WHERE s.shift_date IN ('2026-11-01', '2026-11-08', '2026-11-15', '2026-11-22', '2026-11-29')
      AND s.is_sunday = 1
    ORDER BY s.shift_date, c.name
""")
sunday_all = c.fetchall()

by_date = {}
for name, dt, code in sunday_all:
    by_date.setdefault(dt, {})[name] = code

print("\n=== ESTADÍSTICA DE DOMINGOS EN NOVIEMBRE 2026 ===")
for dt, emps in by_date.items():
    libres = [n for n, cd in emps.items() if cd == 'LIBRE']
    trabajan = [n for n, cd in emps.items() if cd != 'LIBRE']
    print(f"Domingo {dt}: {len(libres)} en Descanso / {len(trabajan)} en Turno")

# 3. Verify exactly 1 night shift per day
c.execute("""
    SELECT shift_date, count(*) 
    FROM workforce_monthly_schedules 
    WHERE shift_code = '7N22-6' AND shift_date BETWEEN '2026-11-01' AND '2027-01-31'
    GROUP BY shift_date 
    HAVING count(*) != 1
""")
bad_nights = c.fetchall()
print(f"\nDías con número de nocturnos != 1 entre Nov 2026 y Ene 2027: {len(bad_nights)}")
