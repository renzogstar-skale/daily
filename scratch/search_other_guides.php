<?php
$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator('views'));
foreach ($files as $file) {
    if ($file->isFile() && $file->getExtension() === 'php') {
        $content = file_get_contents($file->getPathname());
        if (stripos($content, 'daily_module_flow_guide') !== false) {
            echo "flow_guide: " . $file->getPathname() . "\n";
        }
        if (stripos($content, 'daily_step_guide') !== false) {
            echo "step_guide: " . $file->getPathname() . "\n";
        }
    }
}

