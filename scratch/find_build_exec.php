<?php
$content = file_get_contents('controllers/KpiController.php');
$lines = explode("\n", $content);
foreach ($lines as $i => $l) {
    if (strpos($l, 'BuildExecutivePanelOverview') !== false) {
        echo ($i + 1) . ": " . trim($l) . "\n";
    }
}

