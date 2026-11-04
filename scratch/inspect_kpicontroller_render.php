<?php
$content = file_get_contents('controllers/KpiController.php');
$lines = explode("\n", $content);
for ($j = 210; $j <= 245; $j++) {
    if (isset($lines[$j])) {
        echo ($j + 1) . ": " . $lines[$j] . "\n";
    }
}

