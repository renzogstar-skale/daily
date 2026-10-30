import urllib.request
import urllib.error
import json

base_url = 'http://localhost:8080'

try:
    apply_req = urllib.request.Request(
        f"{base_url}/api/workforce/peaks/recommendations/1/apply",
        data=b"{}",
        headers={'Content-Type': 'application/json'}
    )
    with urllib.request.urlopen(apply_req) as resp:
        print(resp.read().decode('utf-8'))
except urllib.error.HTTPError as e:
    print("HTTP Error:", e.code)
    print("Response Body:", e.read().decode('utf-8'))
