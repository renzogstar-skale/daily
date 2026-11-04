<?php
$content = file_get_contents('views/executive_dashboard.blade.php');
$lines = explode("\n", $content);
foreach ($lines as $i => $l) {
    if (preg_match('/<!--\s*={4,}\s*([0-9]+\.[^=]+)={4,}/', $l, $m)) {
        echo ($i + 1) . ": " . trim($m[1]) . "\n";
    }
}

