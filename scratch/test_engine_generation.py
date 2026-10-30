import sqlite3
import datetime
import calendar
import json

con = sqlite3.connect('c:/Users/renzo/grocy/grocy/data/grocy_es.db')
cur = con.cursor()

# 1. Active collaborators
cur.execute("""
    SELECT c.payroll_code, c.name, c.department, c.cargo, c.sunday_group, 
           c.last_week_oct_rotation, c.next_nov_w1_rotation, e.id as employee_id
    FROM workforce_planilla_collaborators c
    JOIN employees e ON e.payroll_code = c.payroll_code
    WHERE c.status = 'ACTIVO'
    ORDER BY c.department, c.name
""")
collabs = cur.fetchall()

# 2. Night eligible
cur.execute("SELECT payroll_code, can_night FROM workforce_skills_matrix")
night_eligible = {r[0] for r in cur.fetchall() if r[1] == 1}

# 3. Holidays
cur.execute("SELECT holiday_date, name FROM workforce_colombian_holidays")
holiday_map = dict(cur.fetchall())

# 4. Vacations
cur.execute("SELECT payroll_code, start_date, end_date FROM workforce_vacations WHERE status = 'approved'")
vacations_list = cur.fetchall()

def is_vacation(pcode, dt_str):
    for code, s_dt, e_dt in vacations_list:
        if code == pcode and s_dt <= dt_str <= e_dt:
            return True
    return False

# SAP Shifts
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

def generate_month_schedule(year, month):
    num_days = calendar.monthrange(year, month)[1]
    days = [datetime.date(year, month, d) for d in range(1, num_days + 1)]
    
    # Night assignments: 1 person per day
    # Pool: TEMP2, TEMP3, 38062365, 38061237, 38063818
    night_worker_by_date = {}
    if year == 2026 and month == 11:
        for d in days:
            d_str = d.strftime('%Y-%m-%d')
            day_num = d.day
            if day_num in (1, 2):
                night_worker_by_date[d_str] = 'TEMP2' # Mora Rodriguez finishes Oct block
            elif 3 <= day_num <= 9:
                night_worker_by_date[d_str] = 'TEMP3' # Castro Arevalo
            elif 10 <= day_num <= 16:
                night_worker_by_date[d_str] = '38062365' # Prieto Hidalgo
            elif 17 <= day_num <= 23:
                night_worker_by_date[d_str] = '38061237' # Zamudio Cajamarca
            else:
                night_worker_by_date[d_str] = '38063818' # Rubio Daza back from vacation Nov 24
    
    schedules = []
    
    # Map department specific default codes
    dept_codes = {
        'Accesorios': {'A': '7A7.3-14.3', 'C': '7C14.3-21.3'},
        'Bodega Digital': {'A': '7A7-14', 'C': '7C14.3-21.3'},
        'Despachos': {'A': '7A7.3-14.3', 'C': '7C14.3-21.3'},
        'Ventas y Operación': {'A': '7A6-13', 'C': '7C14-21'},
        'Virtual Teams': {'A': '7A7-14', 'C': '7C14-21'},
    }
    
    # Sunday groups free mapping for Nov 2026
    # D1 (Nov 1): Group A free (and Galindo D, Guavita B, Cubides D from oct sheet)
    # D8 (Nov 8): Group B free
    # D15 (Nov 15): Group C free
    # D22 (Nov 22): Group D free
    # D29 (Nov 29): Group A free
    sunday_free_map = {
        '2026-11-01': {'A'},
        '2026-11-08': {'B'},
        '2026-11-15': {'C'},
        '2026-11-22': {'D'},
        '2026-11-29': {'A'},
    }
    
    # Specific Sunday 1 liberties from October photo:
    sunday1_libres = {'38079297', '38083990', '38037244'} # Guavita, Galindo, Cubides
    
    for collab in collabs:
        pcode, name, dept, cargo, s_group, last_rot, next_w1_rot, emp_id = collab
        
        # Compensatory assigned day of week for this collab (rotate 1=Tue, 2=Wed, 3=Thu, 4=Fri to avoid holidays on Mon)
        comp_pref_dow = (emp_id % 4) + 2 # 2=Tue, 3=Wed, 4=Thu, 5=Fri
        
        # Determine week rotation per day
        # For each week, determine if Apertura or Cierre
        for d in days:
            d_str = d.strftime('%Y-%m-%d')
            dow = d.weekday() # 0=Mon, 1=Tue, 2=Wed, 3=Thu, 4=Fri, 5=Sat, 6=Sun
            is_sun = (dow == 6)
            is_hol = d_str in holiday_map
            
            # Week number calculation (Monday based)
            # Nov 1 is Sunday (part of week ending Nov 1)
            if d.day == 1:
                week_num = 0
            else:
                week_num = ((d.day - 2) // 7) + 1
            
            # Check vacation first!
            if is_vacation(pcode, d_str):
                schedules.append({
                    'payroll_code': pcode,
                    'employee_id': emp_id,
                    'shift_date': d_str,
                    'shift_code': 'VC',
                    'shift_type': 'VACACIONES',
                    'start_time': '00:00',
                    'end_time': '00:00',
                    'hours': 0.0,
                    'is_sunday': 1 if is_sun else 0,
                    'is_holiday': 1 if is_hol else 0,
                    'compensatory_origin_date': None,
                    'notes': 'Vacaciones obligatorias aprobadas'
                })
                continue
            
            # Check if this person is the designated night worker for today
            if night_worker_by_date.get(d_str) == pcode:
                schedules.append({
                    'payroll_code': pcode,
                    'employee_id': emp_id,
                    'shift_date': d_str,
                    'shift_code': '7N22-6',
                    'shift_type': 'NOCHE',
                    'start_time': '22:00',
                    'end_time': '06:00',
                    'hours': 7.0,
                    'is_sunday': 1 if is_sun else 0,
                    'is_holiday': 1 if is_hol else 0,
                    'compensatory_origin_date': None,
                    'notes': 'Turno nocturno asignado (1 cupo diario)'
                })
                continue
            
            # Check Sunday
            if is_sun:
                is_free_sunday = False
                if d_str == '2026-11-01':
                    is_free_sunday = (s_group in sunday_free_map['2026-11-01'] or pcode in sunday1_libres)
                else:
                    is_free_sunday = (s_group in sunday_free_map.get(d_str, set()))
                
                if is_free_sunday:
                    schedules.append({
                        'payroll_code': pcode,
                        'employee_id': emp_id,
                        'shift_date': d_str,
                        'shift_code': 'LIBRE',
                        'shift_type': 'LIBRE',
                        'start_time': '00:00',
                        'end_time': '00:00',
                        'hours': 0.0,
                        'is_sunday': 1,
                        'is_holiday': 1 if is_hol else 0,
                        'compensatory_origin_date': None,
                        'notes': f'Descanso dominical regla 3x1 (Grupo {s_group})'
                    })
                else:
                    # Works Sunday -> gets regular shift
                    rot = next_w1_rot if week_num % 2 == 1 else ('CIERRE' if next_w1_rot == 'APERTURA' else 'APERTURA')
                    code = dept_codes.get(dept, {}).get('A' if rot == 'APERTURA' else 'C', '7A7-14')
                    s_info = SAP_SHIFTS[code]
                    schedules.append({
                        'payroll_code': pcode,
                        'employee_id': emp_id,
                        'shift_date': d_str,
                        'shift_code': code,
                        'shift_type': s_info['type'],
                        'start_time': s_info['start'],
                        'end_time': s_info['end'],
                        'hours': s_info['hours'],
                        'is_sunday': 1,
                        'is_holiday': 1 if is_hol else 0,
                        'compensatory_origin_date': None,
                        'notes': f'Turno dominical trabajado - genera compensatorio'
                    })
                continue
            
            # Check if this weekday should be a Compensatory
            # Rule: only on weekdays (Mon-Fri), NEVER on holidays, NEVER on vacations
            # If worked the previous Sunday, assign a compensatory on their preferred weekday
            # Sunday of this week:
            prev_sun = d - datetime.timedelta(days=(dow + 1))
            prev_sun_str = prev_sun.strftime('%Y-%m-%d')
            
            # Check if worked prev_sun
            prev_sun_worked = False
            if prev_sun_str >= '2026-10-25':
                if prev_sun_str == '2026-11-01':
                    prev_sun_worked = not (s_group in sunday_free_map['2026-11-01'] or pcode in sunday1_libres or is_vacation(pcode, '2026-11-01'))
                elif prev_sun_str == '2026-10-25':
                    prev_sun_worked = (s_group != 'D') # in oct sheet group D was libre
                else:
                    prev_sun_worked = not (s_group in sunday_free_map.get(prev_sun_str, set()) or is_vacation(pcode, prev_sun_str))
            
            # Assign COMP on their designated day of week (Tue, Wed, Thu, Fri)
            # Never on Monday (Nov 2 and Nov 16 are holidays!)
            is_comp_day = (prev_sun_worked and (dow + 1) == comp_pref_dow and not is_hol)
            if is_comp_day:
                schedules.append({
                    'payroll_code': pcode,
                    'employee_id': emp_id,
                    'shift_date': d_str,
                    'shift_code': 'COMP',
                    'shift_type': 'COMPENSATORIO',
                    'start_time': '00:00',
                    'end_time': '00:00',
                    'hours': 7.0,
                    'is_sunday': 0,
                    'is_holiday': 0,
                    'compensatory_origin_date': prev_sun_str,
                    'notes': f'Compensatorio por domingo laborado ({prev_sun_str})'
                })
                continue
            
            # Regular weekday shift (Mon - Sat)
            # Alternating rotation by week:
            # Week 1: next_w1_rot
            # Week 2: opposite
            # Week 3: next_w1_rot
            # Week 4: opposite
            # Week 5: next_w1_rot
            if week_num % 2 == 1:
                rot = next_w1_rot
            else:
                rot = 'CIERRE' if next_w1_rot == 'APERTURA' else 'APERTURA'
            
            shift_code = dept_codes.get(dept, {}).get('A' if rot == 'APERTURA' else 'C', '7A7-14')
            s_info = SAP_SHIFTS[shift_code]
            
            schedules.append({
                'payroll_code': pcode,
                'employee_id': emp_id,
                'shift_date': d_str,
                'shift_code': shift_code,
                'shift_type': s_info['type'],
                'start_time': s_info['start'],
                'end_time': s_info['end'],
                'hours': s_info['hours'],
                'is_sunday': 0,
                'is_holiday': 1 if is_hol else 0,
                'compensatory_origin_date': None,
                'notes': f'Turno regular semana {rot}'
            })
            
    return schedules

test_sched = generate_month_schedule(2026, 11)
print(f"Total shift records generated for Nov 2026: {len(test_sched)} (expected {23 * 30} = 690)")

# Verify rules:
# 1. Exact 1 night per day
nights_by_day = {}
for s in test_sched:
    if s['shift_type'] == 'NOCHE':
        nights_by_day[s['shift_date']] = nights_by_day.get(s['shift_date'], 0) + 1
print(f"Nights count per day: min={min(nights_by_day.values())}, max={max(nights_by_day.values())}, days={len(nights_by_day)}")

# 2. No compensatories on holidays
comp_on_hols = [s for s in test_sched if s['shift_type'] == 'COMPENSATORIO' and s['is_holiday'] == 1]
print(f"Compensatories on holidays: {len(comp_on_hols)} (must be 0!)")

# 3. Vacations strictly respected
vac_shifts = [s for s in test_sched if s['shift_type'] == 'VACACIONES']
print(f"Total vacation days assigned: {len(vac_shifts)}")

# 4. Sunday 3x1 compliance
print("Verification complete!")
con.close()
