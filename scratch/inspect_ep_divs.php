<?php
$content = file_get_contents('views/executive_dashboard.blade.php');
$lines = explode("\n", $content);
foreach ($lines as $i => $l) {
    if (preg_match('/<(section|div class=["\']exec-[a-z-]+["\'])/', $l, $m)) {
        echo ($i + 1) . ": " . trim($l) . "\n";
    }
}

