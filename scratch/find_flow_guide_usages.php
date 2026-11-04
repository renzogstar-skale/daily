<?php
$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator('views'));
foreach ($files as $file) {
    if ($file->isFile() && $file->getExtension() === 'php') {
        $content = file_get_contents($file->getPathname());
        if (strpos($content, 'daily_module_flow_guide') !== false) {
            echo $file->getPathname() . "\n";
        }
    }
}

