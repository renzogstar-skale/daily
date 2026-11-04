<?php
$content = file_get_contents('views/executive_dashboard.blade.php');
$lines = explode("\n", $content);
foreach ($lines as $i => $l) {
    if (strpos($l, 'photo_kpis') !== false || strpos($l, 'colombia_branches') !== false || strpos($l, 'sales_day_curve') !== false) {
        echo ($i + 1) . ": " . trim($l) . "\n";
    }
}

