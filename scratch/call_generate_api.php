<?php
$ch = curl_init("http://localhost:8080/api/schedule/monthly/generate");
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    "branch_id" => 1,
    "year" => 2026,
    "month" => 11,
    "publish" => true
]));
curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$res = curl_exec($ch);
echo "Response from /api/schedule/monthly/generate:\n" . $res . "\n";
