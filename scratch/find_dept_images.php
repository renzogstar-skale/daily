<?php
$files = glob('public/img/**/*.{jpg,png}', GLOB_BRACE);
foreach ($files as $f) {
    if (preg_match('/(pos|caja|electr|bodega|wareh|dispatch|lact|fruv|card|depto)/i', $f)) {
        echo "$f\n";
    }
}
