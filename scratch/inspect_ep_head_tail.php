<?php
$content = file_get_contents('views/executive_dashboard.blade.php');
$lines = explode("\n", $content);
echo "Lines 1 to 50:\n";
for ($i = 0; $i < 50; $i++) {
    echo ($i + 1) . ": " . $lines[$i] . "\n";
}
echo "\nLast 100 lines:\n";
for ($i = max(0, count($lines) - 100); $i < count($lines); $i++) {
    echo ($i + 1) . ": " . $lines[$i] . "\n";
}

