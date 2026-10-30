import sqlite3
import datetime
import calendar
import json

con = sqlite3.connect('c:/Users/renzo/grocy/grocy/data/grocy_es.db')
cur = con.cursor()

# Load collaborators
cur.execute("""
    SELECT c.payroll_code, c.name, c.department, c.cargo, c.sunday_group, 
           c.last_week_oct_rotation, c.next_nov_w1_rotation, e.id as employee_id
    FROM workforce_planilla_collaborators c
    JOIN employees e ON e.payroll_code = c.payroll_code
    WHERE c.status = 'ACTIVO'
    ORDER BY c.department, c.name
""")
collabs = cur.fetchall()
print(f"Loaded {len(collabs)} collaborators.")

# Load night eligibility
cur.execute("SELECT payroll_code, can_night FROM workforce_skills_matrix")
night_map = dict(cur.fetchall())

# Load holidays for 2026-2027
cur.execute("SELECT holiday_date, name FROM workforce_colombian_holidays")
holiday_map = dict(cur.fetchall())

# Load vacations
cur.execute("SELECT payroll_code, start_date, end_date FROM workforce_vacations WHERE status = 'approved'")
vacations_list = cur.fetchall()

def is_on_vacation(pcode, dt_str):
    for code, s_dt, e_dt in vacations_list:
        if code == pcode and s_dt <= dt_str <= e_dt:
            return True
    return False

print("Vacations loaded:", len(vacations_list))
con.close()
