import urllib.request
import json
import sqlite3

# 1. Update Karen Delgado shift on 2026-11-05
url = 'http://localhost:8080/api/schedule/monthly/shift'
payload = {
    'payroll_code': '38041537',
    'shift_date': '2026-11-05',
    'shift_code': '7C14.3-21.3',
    'notes': 'Prueba funcional sincronización automática'
}

req = urllib.request.Request(
    url,
    data=json.dumps(payload).encode('utf-8'),
    headers={'Content-Type': 'application/json'}
)

try:
    resp = urllib.request.urlopen(req)
    res_data = json.loads(resp.read().decode('utf-8'))
    print("API Response:", res_data)
except Exception as e:
    print("API Error:", e)

# 2. Check DB tables
db = sqlite3.connect('data/grocy_es.db')
c = db.cursor()

# workforce_monthly_schedules
c.execute("SELECT shift_code, shift_type, start_time, end_time, hours, notes FROM workforce_monthly_schedules WHERE payroll_code = '38041537' AND shift_date = '2026-11-05'")
m_row = c.fetchone()
print("workforce_monthly_schedules row:", m_row)

# employee_shifts
c.execute("SELECT e.name, s.shift_date, s.start_time, s.end_time, s.department FROM employee_shifts s JOIN employees e ON e.id = s.employee_id WHERE e.payroll_code = '38041537' AND s.shift_date = '2026-11-05'")
e_row = c.fetchone()
print("employee_shifts row:", e_row)

# workforce_shift_audit_log
c.execute("SELECT payroll_code, shift_date, action, old_value, new_value, reason, timestamp FROM workforce_shift_audit_log ORDER BY id DESC LIMIT 1")
a_row = c.fetchone()
print("workforce_shift_audit_log row:", a_row)

# 3. Verify in views
views_to_check = ['weekly', 'monthly', 'equity', 'branch', 'area', 'employee', 'list']
for v in views_to_check:
    v_url = f'http://localhost:8080/schedule?view={v}&employee=38041537&week=2026-11-02'
    html = urllib.request.urlopen(v_url).read().decode('utf-8', errors='ignore')
    found = ('7C14.3-21.3' in html) or ('14:30' in html) or ('Delgado' in html)
    print(f"View '{v}' contains updated shift/collaborator: {found}")
