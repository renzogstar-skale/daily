<?php
$content = file_get_contents('services/KpiService.php');
$lines = explode("\n", $content);
for ($j = 2040; $j <= min(count($lines) - 1, 2150); $j++) {
    echo ($j + 1) . ": " . $lines[$j] . "\n";
}

