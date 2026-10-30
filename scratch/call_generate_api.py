import urllib.request
import json
import sqlite3

def call_generate(year, month):
    url = 'http://localhost:8080/api/schedule/monthly/generate'
    payload = {
        'branch_id': 1,
        'year': year,
        'month': month,
        'publish': True
    }
    req = urllib.request.Request(
        url,
        data=json.dumps(payload).encode('utf-8'),
        headers={'Content-Type': 'application/json'}
    )
    resp = urllib.request.urlopen(req)
    data = json.loads(resp.read().decode('utf-8'))
    print(f"Generated {year}-{month:02d}: success={data.get('success')}, total={data.get('total_shifts')}")
    return data

call_generate(2026, 11)
call_generate(2026, 12)
call_generate(2027, 1)

# Now check DB for Sebastián Cubides (38037244)
db = sqlite3.connect('data/grocy_es.db')
c = db.cursor()

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
    '2026-11-08': 'TRABAJA',
    '2026-11-15': 'TRABAJA',
    '2026-11-22': 'TRABAJA',
    '2026-11-29': 'LIBRE',
    '2026-12-06': 'TRABAJA',
    '2026-12-13': 'TRABAJA',
    '2026-12-20': 'TRABAJA',
    '2026-12-27': 'LIBRE'
}

for r in rows:
    dt, code, stype, h, notes = r
    is_free = (code == 'LIBRE')
    status_str = 'LIBRE' if is_free else 'TRABAJA'
    print(f"Domingo {dt}: Status={status_str} (Code={code}, Type={stype}, Hours={h}) -> Notes: {notes}")
    if dt in expected_seb:
        exp = expected_seb[dt]
        if status_str != exp:
            print(f"  --> ERROR: Expected {exp} on {dt}, got {status_str}")
            seb_ok = False
        else:
            print(f"  --> OK: Correctly {status_str}")

print("\nSEBASTIÁN VERIFICATION RESULT:", "PASÓ PERFECTO 100%" if seb_ok else "FALLÓ")
