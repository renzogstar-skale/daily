import sqlite3

conn = sqlite3.connect('data/grocy_es.db')
c = conn.cursor()
tables = [r[0] for r in c.execute("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name").fetchall()]
print(f'Total tables: {len(tables)}')
relevant = [t for t in tables if any(k in t for k in ['order', 'dispatch', 'return', 'waste', 'alert', 'scan', 'bakery', 'quality', 'loss', 'expiry'])]
print('Relevant tables in DB:')
for t in relevant:
    cnt = c.execute(f"SELECT COUNT(*) FROM {t}").fetchone()[0]
    print(f"  {t} ({cnt} rows)")

conn.close()
