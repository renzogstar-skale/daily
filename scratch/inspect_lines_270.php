<?php
$content = file_get_contents('controllers/KpiController.php');
$lines = explode("\n", $content);
for ($j = 270; $j <= min(count($lines) - 1, 315); $j++) {
    echo ($j + 1) . ": " . $lines[$j] . "\n";
}

