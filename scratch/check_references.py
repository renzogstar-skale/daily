import sqlite3

con = sqlite3.connect('c:/Users/renzo/grocy/grocy/data/grocy_es.db')
cur = con.cursor()

tables_to_check = [
    ('employee_shifts', 'employee_id'),
    ('employee_absences', 'employee_id'),
    ('employee_availability', 'employee_id'),
    ('shift_swap_requests', 'from_employee_id'),
    ('employee_certifications', 'employee_id'),
    ('workforce_store_tasks', 'assigned_employee_id'),
    ('workforce_schedule_recommendations', 'employee_id'),
]

for tbl, col in tables_to_check:
    cur.execute(f"""
        SELECT COUNT(*) 
        FROM {tbl} t
        JOIN employees e ON e.id = t.{col}
        WHERE e.active = 0
    """)
    cnt = cur.fetchone()[0]
    print(f"Table {tbl}.{col} referencing inactive employees: {cnt}")
