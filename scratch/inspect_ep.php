<?php
$content = file_get_contents('views/executive_dashboard.blade.php');
$lines = explode("\n", $content);
echo "Total lines: " . count($lines) . "\n";
foreach ($lines as $i => $l) {
    if (preg_match('/<section|<header|<div class=["\']ep-/i', $l) && $i < 300) {
        echo ($i + 1) . ": " . trim($l) . "\n";
    }
}

