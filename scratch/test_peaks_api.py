import urllib.request
import urllib.error
import json

base_url = 'http://localhost:8080'

# 1. Test Dashboard API
print("Testing GET /api/workforce/peaks/dashboard...")
req = urllib.request.Request(f"{base_url}/api/workforce/peaks/dashboard?branch_id=1")
with urllib.request.urlopen(req) as resp:
    data = json.loads(resp.read().decode('utf-8'))
    print("Dashboard API success!")
    print("  KPIs:", data['kpis'])
    print("  Area standards count:", len(data['area_standards']))
    print("  Commercial events count:", len(data['commercial_events']))
    print("  Detected peaks:", data['detected_peaks'])

# 2. Test Simulate API
print("\nTesting POST /api/workforce/peaks/simulate ($75,000 sales)...")
sim_payload = json.dumps({'branch_id': 1, 'daily_sales': 75000}).encode('utf-8')
sim_req = urllib.request.Request(
    f"{base_url}/api/workforce/peaks/simulate",
    data=sim_payload,
    headers={'Content-Type': 'application/json'}
)
with urllib.request.urlopen(sim_req) as resp:
    sim_data = json.loads(resp.read().decode('utf-8'))
    print(f"  Total staff required: {sim_data['total_staff_required']} colaboradores")
    print(f"  SPLH projected: ${sim_data['splh_projected']} /h")
    print(f"  Labor cost %: {sim_data['labor_pct_of_sales']}%")
    print("  Area distribution:")
    for area, d in sim_data['area_distribution'].items():
        print(f"    - {area}: {d['staff']} colab (workload {d['workload_minutes']} min)")

# 3. Test Apply Recommendation API
print("\nTesting POST /api/workforce/peaks/recommendations/1/apply...")
apply_req = urllib.request.Request(
    f"{base_url}/api/workforce/peaks/recommendations/1/apply",
    data=b"{}",
    headers={'Content-Type': 'application/json'}
)
with urllib.request.urlopen(apply_req) as resp:
    apply_data = json.loads(resp.read().decode('utf-8'))
    print("  Apply success:", apply_data)

print("\nALL API TESTS PASSED!")
