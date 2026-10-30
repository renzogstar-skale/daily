import sqlite3

db = sqlite3.connect('data/grocy_es.db')
c = db.cursor()

c.execute('SELECT count(*) FROM workforce_shift_audit_log')
cnt = c.fetchone()[0]
print(f'Audit log entries count: {cnt}')

c.execute("SELECT count(*) FROM workforce_monthly_schedules WHERE shift_date BETWEEN '2026-11-01' AND '2026-11-30'")
m_cnt = c.fetchone()[0]
print(f'November monthly shifts count: {m_cnt}')

c.execute("SELECT count(DISTINCT payroll_code) FROM workforce_monthly_schedules WHERE shift_date BETWEEN '2026-11-01' AND '2026-11-30'")
c_cnt = c.fetchone()[0]
print(f'Distinct collaborators in November: {c_cnt}')

c.execute('SELECT id, name, code FROM branches ORDER BY id')
branches = c.fetchall()
print(f'Total branches: {len(branches)}')
for b in branches:
    print(f'  Branch {b[0]}: {b[1]} ({b[2]})')

# Test shift update with audit log
from datetime import datetime
print("\nTesting UpdateShift via SQLite:")
c.execute("SELECT shift_code FROM workforce_monthly_schedules WHERE payroll_code = '38041537' AND shift_date = '2026-11-05'")
row = c.fetchone()
print(f"Current shift on 2026-11-05 for 38041537 (Karen Delgado): {row[0] if row else 'None'}")
