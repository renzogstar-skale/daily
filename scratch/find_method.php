<?php
$content = file_get_contents('services/KpiService.php');
$lines = explode("\n", $content);
for ($j = 1880; $j <= 1905; $j++) {
    echo ($j + 1) . ": " . $lines[$j] . "\n";
}

