import sqlite3

con = sqlite3.connect('c:/Users/renzo/grocy/grocy/data/grocy_es.db')
cur = con.cursor()

# Clean up names and departments
cur.execute("UPDATE employees SET name = 'TRIANA CARREÑO LUCIANA PATRICIA' WHERE payroll_code = '38039919'")
cur.execute("UPDATE employees SET name = 'MUÑOZ SOLORZA JONATAN ESTIVEN' WHERE payroll_code = '38082394'")
cur.execute("UPDATE employees SET name = 'ZAMUDIO CAJAMARCA HENRY ALEJANDRO' WHERE payroll_code = '38061237'")
cur.execute("UPDATE employees SET department = 'Ventas y Operación' WHERE department LIKE 'Ventas y Operaci%'")

# Clean up employee_absences to reference real active employees
cur.execute("""
    UPDATE employee_absences 
    SET employee_id = 1164, start_date = '2026-11-04', end_date = '2026-11-06', notes = 'Capacitación externa de producto'
    WHERE id = 1
""")
cur.execute("""
    UPDATE employee_absences 
    SET employee_id = 1170, start_date = '2026-11-05', end_date = '2026-11-05', notes = 'Cita médica especialista'
    WHERE id = 2
""")

# Clean up shift_swap_requests
# Find a real shift for 1165
cur.execute("SELECT id FROM employee_shifts WHERE employee_id = 1165 AND shift_date = '2026-11-04' LIMIT 1")
s_row = cur.fetchone()
shift_id = s_row[0] if s_row else 1
cur.execute(f"""
    UPDATE shift_swap_requests 
    SET shift_id = {shift_id}, from_employee_id = 1165, to_employee_id = 1166, notes = 'Permuta de turno acordada entre compañeros'
    WHERE id = 1
""")

con.commit()
print("Updated employees, absences, and swap requests successfully.")

cur.execute("SELECT id, payroll_code, name, department, active FROM employees WHERE active = 1 ORDER BY department, name")
for r in cur.fetchall():
    print(r)
con.close()
