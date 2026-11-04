<?php
$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator('views'));
foreach ($files as $file) {
    if ($file->isFile() && $file->getExtension() === 'php') {
        $content = file_get_contents($file->getPathname());
        if (stripos($content, 'Guía Operativa') !== false || stripos($content, 'Guia Operativa') !== false || stripos($content, 'CICLO 2') !== false || stripos($content, 'Ocultar Guía') !== false || stripos($content, 'Ocultar Guia') !== false) {
            echo $file->getPathname() . "\n";
        }
    }
}

