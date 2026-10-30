import sqlite3

con = sqlite3.connect('c:/Users/renzo/grocy/grocy/data/grocy_es.db')
cur = con.cursor()

cur.execute("UPDATE workforce_planilla_collaborators SET name = 'TRIANA CARREÑO LUCIANA PATRICIA' WHERE payroll_code = '38039919'")
cur.execute("UPDATE workforce_planilla_collaborators SET name = 'MUÑOZ SOLORZA JONATAN ESTIVEN' WHERE payroll_code = '38082394'")
cur.execute("UPDATE workforce_planilla_collaborators SET name = 'ZAMUDIO CAJAMARCA HENRY ALEJANDRO' WHERE payroll_code = '38061237'")
cur.execute("UPDATE workforce_planilla_collaborators SET department = 'Ventas y Operación' WHERE department LIKE 'Ventas y Operaci%'")
con.commit()
print("Updated workforce_planilla_collaborators encoding successfully.")
con.close()
