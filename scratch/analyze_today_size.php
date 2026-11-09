<?php
chdir(__DIR__ . '/../public');
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/today';
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';
$_SERVER['SERVER_NAME'] = 'localhost';
$_SERVER['SERVER_PORT'] = '8080';
$_SERVER['HTTP_HOST'] = 'localhost:8080';

ob_start();
require __DIR__ . '/../public/index.php';
$html = ob_get_clean();

file_put_contents(__DIR__ . '/today_dump.html', $html);

// Analyze tag counts and largest sections
echo "Saved " . strlen($html) . " bytes to today_dump.html\n";

preg_match_all('/<([a-z0-9]+)/i', $html, $m);
echo "Total HTML elements: " . count($m[0]) . "\n";

// Check for massive inline scripts, modals, or tables
preg_match_all('/<script[\s\S]*?<\/script>/i', $html, $scripts);
$scriptBytes = array_sum(array_map('strlen', $scripts[0]));
echo "Total Script bytes: " . $scriptBytes . " in " . count($scripts[0]) . " script tags\n";

preg_match_all('/<table[\s\S]*?<\/table>/i', $html, $tables);
$tableBytes = array_sum(array_map('strlen', $tables[0]));
echo "Total Table bytes: " . $tableBytes . " in " . count($tables[0]) . " tables\n";

preg_match_all('/<div[^>]*class=["\']([^"\']*)["\']/i', $html, $divs);
$classFreq = array_count_values($divs[1]);
arsort($classFreq);
echo "Top div classes:\n";
print_r(array_slice($classFreq, 0, 10));

