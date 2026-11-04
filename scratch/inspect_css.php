<?php
$content = file_get_contents('public/css/daily_executive_panel.css');
echo "Length: " . strlen($content) . " bytes\n";
echo substr($content, 0, 500) . "\n";

