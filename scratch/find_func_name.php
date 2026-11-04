<?php
$content = file_get_contents('services/KpiService.php');
$lines = explode("\n", $content);
for ($j = 1680; $j <= 1750; $j++) {
    if (strpos($lines[$j], 'function ') !== false) {
        echo ($j + 1) . ": " . $lines[$j] . "\n";
    }
}

