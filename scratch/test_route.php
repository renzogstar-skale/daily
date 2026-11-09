<?php
chdir(__DIR__ . '/../public');
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = $argv[1] ?? '/today';
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';
$_SERVER['SERVER_NAME'] = 'localhost';
$_SERVER['SERVER_PORT'] = '8080';
$_SERVER['HTTP_HOST'] = 'localhost:8080';

$t0 = microtime(true);
ob_start();
try {
    require __DIR__ . '/../public/index.php';
    $out = ob_get_clean();
    $t1 = microtime(true);
    echo "Route: " . $_SERVER['REQUEST_URI'] . " - Time: " . round(($t1 - $t0) * 1000, 2) . "ms - Length: " . strlen($out) . "\n";
} catch (\Throwable $e) {
    ob_end_clean();
    echo "Error on " . $_SERVER['REQUEST_URI'] . ": " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine() . "\n";
}

