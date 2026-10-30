import sqlite3

conn = sqlite3.connect('data/grocy_es.db')
cur = conn.cursor()

def dump_schema(table):
    print(f"\n--- {table} ---")
    cols = cur.execute(f"PRAGMA table_info({table})").fetchall()
    for c in cols:
        print(f"  {c[1]} ({c[2]})")
    row = cur.execute(f"SELECT * FROM {table} LIMIT 1").fetchone()
    if row:
        print("  Sample row:", row)

dump_schema('workforce_department_configs')
dump_schema('labor_calendar_events')
dump_schema('workforce_schedule_recommendations')
dump_schema('sales')
dump_schema('workforce_colombian_holidays')

conn.close()
