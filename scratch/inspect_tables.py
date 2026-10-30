import sqlite3

conn = sqlite3.connect('data/grocy_es.db')
cur = conn.cursor()

tables = [t[0] for t in cur.execute("SELECT name FROM sqlite_master WHERE type='table'").fetchall()]
print("Matching tables:")
for t in sorted(tables):
    if any(k in t.lower() for k in ['workforce', 'schedule', 'shift', 'sale', 'employee', 'peak', 'forecast', 'event', 'holiday']):
        print(f" - {t}")

print("\nEmployees count:", cur.execute("SELECT COUNT(*) FROM employees").fetchone()[0])
print("Employee shifts count:", cur.execute("SELECT COUNT(*) FROM employee_shifts").fetchone()[0])
print("Sales count:", cur.execute("SELECT COUNT(*) FROM sales").fetchone()[0] if 'sales' in tables else 'no sales table')

conn.close()
