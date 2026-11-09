<?php
// Fusiona respuestas «id\tinglés» (lotes traducidos) con index.json del worklist en localization/daily/en/<ns>.json.
// Uso: php bin/daily_i18n_merge.php <index.json> <respuesta1.txt> [respuesta2.txt ...]
$root = dirname(__DIR__);
$index = json_decode(file_get_contents($argv[1]), true);
$byNs = [];
$n = 0;
$bad = 0;
foreach (array_slice($argv, 2) as $file)
{
	foreach (file($file, FILE_IGNORE_NEW_LINES) as $line)
	{
		if (!preg_match('/^(\d+)\t(.+)$/u', $line, $m)) continue;
		if (!isset($index[$m[1]])) { $bad++; continue; }
		[$ns, $es] = $index[$m[1]];
		$en = trim($m[2]);
		// Las plantillas deben conservar sus parámetros
		preg_match_all('/\{\d+\}|\{\*\}/', $es, $a);
		preg_match_all('/\{\d+\}|\{\*\}/', $en, $b);
		sort($a[0]); sort($b[0]);
		if ($a[0] !== $b[0]) { fwrite(STDERR, "parámetros distintos #{$m[1]}: $es → $en\n"); $bad++; continue; }
		if ($en === '' || $en === $es) continue;
		$byNs[$ns][$es] = $en;
		$n++;
	}
}
@mkdir($root . '/localization/daily/en', 0777, true);
foreach ($byNs as $ns => $map)
{
	$path = "$root/localization/daily/en/$ns.json";
	$cur = is_file($path) ? json_decode(file_get_contents($path), true) : [];
	$cur = array_merge($cur ?: [], $map);
	ksort($cur);
	file_put_contents($path, json_encode($cur, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
}
echo "$n traducciones en " . count($byNs) . " namespaces, $bad rechazadas\n";
