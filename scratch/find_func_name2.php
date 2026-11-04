<?php
$content = file_get_contents('services/KpiService.php');
$lines = explode("\n", $content);
for ($j = 1890; $j >= 1000; $j--) {
    if (strpos($lines[$j], 'function ') !== false) {
        echo ($j + 1) . ": " . $lines[$j] . "\n";
        break;
    }
}

