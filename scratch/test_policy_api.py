import urllib.request
import json
import sqlite3

url = "http://localhost:8080/api/schedule/policies"
test_data = {
    "company_id": 1,
    "policy_name": "Politica de Prueba Versionada 2x1",
    "effective_date": "2027-01-01",
    "sunday_rule_type": "2x1",
    "rotation_rule_type": "weekly_apertura_cierre",
    "max_nights_per_day": 2,
    "max_weekly_hours": 40.0,
    "min_rest_hours": 12.0
}

req = urllib.request.Request(
    url,
    data=json.dumps(test_data).encode("utf-8"),
    headers={"Content-Type": "application/json"}
)

with urllib.request.urlopen(req) as resp:
    res = json.loads(resp.read().decode("utf-8"))
    print("Save policy response:", json.dumps(res, indent=2))
    assert res["version"] == 2
    assert res["sunday_rule_type"] == "2x1"
    assert res["max_nights_per_day"] == 2

# Verify history
with urllib.request.urlopen("http://localhost:8080/api/schedule/policies?company_id=1") as resp:
    history_res = json.loads(resp.read().decode("utf-8"))
    print("\nTotal policies in history:", len(history_res["history"]))
    for p in history_res["history"]:
        print(f"  v{p['version']} - {p['policy_name']} (eff: {p['effective_date']}, type: {p['sunday_rule_type']})")

# Clean up version 2 from DB to leave clean default state for customer
conn = sqlite3.connect("data/grocy_es.db")
c = conn.cursor()
c.execute("DELETE FROM workforce_company_policies WHERE company_id = 1 AND version = 2")
conn.commit()
conn.close()
print("\nCleaned up test version 2. Company Policy API test PASSED!")
