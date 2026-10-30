import sqlite3
import datetime
import calendar
import json

con = sqlite3.connect('c:/Users/renzo/grocy/grocy/data/grocy_es.db')
cur = con.cursor()

# 1. Load active collaborators
cur.execute("""
    SELECT c.payroll_code, c.name, c.department, c.cargo, c.sunday_group, 
           c.last_week_oct_rotation, c.next_nov_w1_rotation, e.id as employee_id
    FROM workforce_planilla_collaborators c
    JOIN employees e ON e.payroll_code = c.payroll_code
    WHERE c.status = 'ACTIVO'
    ORDER BY c.department, c.name
""")
collabs = cur.fetchall()

# 2. Load night eligibility
cur.execute("SELECT payroll_code, can_night FROM workforce_skills_matrix")
night_eligible = {r[0] for r in cur.fetchall() if r[1] == 1}

# 3. Load holidays
cur.execute("SELECT holiday_date, name FROM workforce_colombian_holidays")
holiday_map = dict(cur.fetchall())

# 4. Load vacations
cur.execute("SELECT payroll_code, start_date, end_date FROM workforce_vacations WHERE status = 'approved'")
vacations_list = cur.fetchall()

def is_vacation(pcode, dt_str):
    for code, s_dt, e_dt in vacations_list:
        if code == pcode and s_dt <= dt_str <= e_dt:
            return True
    return False

# SAP Shift definitions
SAP_SHIFTS = {
    '7A6-13': {'type': 'APERTURA', 'start': '06:00', 'end': '13:00', 'hours': 7.0},
    '7A7-14': {'type': 'APERTURA', 'start': '07:00', 'end': '14:00', 'hours': 7.0},
    '7A7.3-14.3': {'type': 'APERTURA', 'start': '07:30', 'end': '14:30', 'hours': 7.0},
    '7C13-20': {'type': 'CIERRE', 'start': '13:00', 'end': '20:00', 'hours': 7.0},
    '7C14-21': {'type': 'CIERRE', 'start': '14:00', 'end': '21:00', 'hours': 7.0},
    '7C14.3-21.3': {'type': 'CIERRE', 'start': '14:30', 'end': '21:30', 'hours': 7.0},
    '7N22-6': {'type': 'NOCHE', 'start': '22:00', 'end': '06:00', 'hours': 7.0},
    'COMP': {'type': 'COMPENSATORIO', 'start': '00:00', 'end': '00:00', 'hours': 7.0},
    'LIBRE': {'type': 'LIBRE', 'start': '00:00', 'end': '00:00', 'hours': 0.0},
    'VC': {'type': 'VACACIONES', 'start': '00:00', 'end': '00:00', 'hours': 0.0},
}

print(f"Collabs: {len(collabs)}, Night eligible: {night_eligible}")
con.close()
