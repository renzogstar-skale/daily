<?php
$content = file_get_contents('services/KpiService.php');
$lines = explode("\n", $content);
foreach ($lines as $i => $l) {
    if (strpos($l, 'Calle 80') !== false) {
        for ($j = max(0, $i - 10); $j <= min(count($lines) - 1, $i + 30); $j++) {
            echo ($j + 1) . ": " . $lines[$j] . "\n";
        }
        break;
    }
}

