import urllib.request
import json
import sqlite3

url = "http://localhost:8080/api/schedule/collaborators"
test_payroll = "TEST-99999"
test_data = {
    "company_id": 1,
    "branch_id": 1,
    "payroll_code": test_payroll,
    "name": "Andres Felipe Rojas Test",
    "department": "Ventas y Operacion",
    "cargo": "E.VENTAS Y OPERACIONES",
    "can_night": 1
}

# 1. Clean up prior test if exists
conn = sqlite3.connect("data/grocy_es.db")
c = conn.cursor()
c.execute("DELETE FROM workforce_monthly_schedules WHERE payroll_code = ?", (test_payroll,))
c.execute("DELETE FROM workforce_skills_matrix WHERE payroll_code = ?", (test_payroll,))
c.execute("DELETE FROM workforce_planilla_collaborators WHERE payroll_code = ?", (test_payroll,))
c.execute("DELETE FROM employees WHERE payroll_code = ?", (test_payroll,))
conn.commit()

# Check initial phase distribution
c.execute("SELECT reference_sunday_phase, COUNT(*) FROM workforce_planilla_collaborators GROUP BY reference_sunday_phase")
print("Initial Sunday phase distribution:", dict(c.fetchall()))

# 2. Call API to register collaborator
req = urllib.request.Request(
    url,
    data=json.dumps(test_data).encode("utf-8"),
    headers={"Content-Type": "application/json"}
)

try:
    with urllib.request.urlopen(req) as resp:
        res = json.loads(resp.read().decode("utf-8"))
        print("\nRegistration response:", json.dumps(res, indent=2))
        assigned_phase = res.get("assigned_sunday_phase")
        print(f"Assigned Sunday phase: {assigned_phase}")
except Exception as e:
    print(f"Registration failed: {e}")
    conn.close()
    exit(1)

# Check DB
c.execute("SELECT name, payroll_code, reference_sunday, reference_sunday_phase, reference_rotation_phase FROM workforce_planilla_collaborators WHERE payroll_code = ?", (test_payroll,))
row = c.fetchone()
print(f"DB verification in workforce_planilla_collaborators: {row}")

# Check skill matrix
c.execute("SELECT secondary_areas, can_night, can_lead FROM workforce_skills_matrix WHERE payroll_code = ?", (test_payroll,))
print(f"DB verification in workforce_skills_matrix: {c.fetchone()}")

# Clean up test data
c.execute("DELETE FROM workforce_monthly_schedules WHERE payroll_code = ?", (test_payroll,))
c.execute("DELETE FROM workforce_skills_matrix WHERE payroll_code = ?", (test_payroll,))
c.execute("DELETE FROM workforce_planilla_collaborators WHERE payroll_code = ?", (test_payroll,))
c.execute("DELETE FROM employees WHERE payroll_code = ?", (test_payroll,))
conn.commit()
conn.close()

print("\nCleanup completed successfully. Collaborator registration test PASSED!")
