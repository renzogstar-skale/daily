<?php
require_once __DIR__ . '/../tests/dairy/bootstrap.php';

$ch = curl_init('http://localhost:8080/schedule');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$html = curl_exec($ch);
curl_close($ch);

echo "schMonthlySection: " . (strpos($html, 'schMonthlySection') !== false ? 'FOUND' : 'MISSING') . "\n";
echo "schSkillsSection: " . (strpos($html, 'schSkillsSection') !== false ? 'FOUND' : 'MISSING') . "\n";
echo "schHolidaysSection: " . (strpos($html, 'schHolidaysSection') !== false ? 'FOUND' : 'MISSING') . "\n";
echo "schStandardViewsContainer: " . (strpos($html, 'schStandardViewsContainer') !== false ? 'FOUND' : 'MISSING') . "\n";
echo "schedule.js tag: " . (preg_match('/src="[^"]*viewjs\/schedule\.js[^"]*"/', $html, $m) ? $m[0] : 'MISSING') . "\n";
