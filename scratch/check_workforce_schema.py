import sqlite3

db = sqlite3.connect('data/grocy_es.db')
c = db.cursor()

c.execute("SELECT name FROM sqlite_master WHERE type='table' AND (name LIKE 'workforce%' OR name IN ('employees', 'branches', 'users'))")
tables = [r[0] for r in c.fetchall()]
print("Tables:", tables)

for t in tables:
    c.execute(f"PRAGMA table_info({t})")
    cols = [f"{col[1]} ({col[2]})" for col in c.fetchall()]
    print(f"\nTable: {t}\n  Columns: {', '.join(cols)}")
