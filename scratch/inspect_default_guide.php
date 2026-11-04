<?php
$content = file_get_contents('views/layout/default.blade.php');
$lines = explode("\n", $content);
foreach ($lines as $i => $l) {
    if (strpos($l, 'daily_module_flow_guide') !== false) {
        for ($j = max(0, $i - 10); $j <= min(count($lines) - 1, $i + 15); $j++) {
            echo ($j + 1) . ": " . $lines[$j] . "\n";
        }
        break;
    }
}

