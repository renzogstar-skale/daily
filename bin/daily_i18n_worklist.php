<?php
// Construye la lista de textos pendientes de traducir (no cubiertos por el catálogo) agrupados por namespace.
// Uso: php bin/daily_i18n_worklist.php <static.json> [harvest.json] <carpeta_salida> [lineas_por_lote=350]
// Salida: <ns>.NN.txt con «id\ttexto» y index.json {id: [ns, texto]}.
ini_set('memory_limit', '1G');
require __DIR__ . '/../helpers/DailyI18n.php';
use Grocy\Helpers\DailyI18n;
$_COOKIE['daily_lang'] = 'en';

$args = array_slice($argv, 1);
$static = json_decode(file_get_contents($args[0]), true);
$harvest = (isset($args[1]) && is_file($args[1])) ? json_decode(file_get_contents($args[1]), true) : [];
$outDir = is_file($args[1] ?? '') ? $args[2] : $args[1];
$per = (int) ((is_file($args[1] ?? '') ? ($args[3] ?? 0) : ($args[2] ?? 0)) ?: 350);

function ns(string $file): string
{
	$b = preg_replace('/(\.blade)?\.(php|js)$/', '', basename($file));
	if (str_contains($file, 'waste_tabs')) return 'waste';
	if (str_starts_with($file, '/')) return 'route_' . trim(preg_replace('/[^a-z0-9]+/', '_', $file), '_');
	$b = preg_replace('/(ApiController|Controller|Service)$/', '', $b);
	return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $b));
}
function nkey(string $s): string
{
	$s = trim(preg_replace('/\s+/u', ' ', $s));
	return preg_replace('/[-+]?\d+(?:[.,:\/]\d+)*/', '{#}', $s);
}

$seen = [];   // clave normalizada → [ns, texto con {0}]
$count = [];
// Códigos/IDs (DAIRY-YOG12 · 3ae…, PO-2026-DD1) y textos que ya están en inglés no se traducen
function skipText(string $s): bool
{
	$t = preg_replace('/[-+]?\d+(?:[.,:\/]\d+)*/', ' ', $s);
	preg_match_all('/\pL+/u', $t, $w);
	$words = $w[0];
	$real = array_filter($words, fn ($x) => mb_strlen($x) >= 4 || (mb_strlen($x) >= 2 && preg_match('/^\p{Lu}?\p{Ll}+$/u', $x) && preg_match('/[aeiouáéíóú]/iu', $x) && mb_strlen($x) >= 3));
	if (!$real) return true;
	if (preg_match('/^[\p{Lu}\d\s\-_·:#\/.{}]+$/u', $s) && !preg_match('/\p{Lu}{4,}\s+\p{Lu}{3,}/u', $s) && mb_strlen($s) < 18) return true;
	if (preg_match('/\b[0-9a-f]{0,3}[a-f]{2}[0-9a-f]*\d[0-9a-f]*\b/', $s) && preg_match('/^[A-Z]{2,}-[A-Z]{2,}/', $s)) return true;
	$es = preg_match_all('/[áéíóúñ¿¡]|\b(de|del|la|las|el|los|en|por|para|con|sin|una?|y|que|se|al|su|sus|es|hoy|ver|más|está|son)\b/iu', $s);
	$en = preg_match_all('/\b(the|and|of|to|is|for|with|this|that|you|your|are|be|on|from|will|has|have|was|it|an|or|not|by|at|as|can|if|which|been|all|new|added|now|when)\b/i', $s);
	if ($en > 0 && $es === 0) return true;
	return false;
}
$add = function (string $s, string $ns, bool $dynamic = false) use (&$seen, &$count, &$add) {
	$s = trim(preg_replace('/\s+/u', ' ', $s));
	if (str_starts_with($s, '__TITLE__')) $s = substr($s, 9);
	if (!preg_match('/\pL{2}/u', $s) || mb_strlen($s) > 600 || skipText($s)) return;
	// Textos renderizados con datos: se traducen por segmentos (igual que el runtime)
	if ($dynamic)
	{
		$parts = preg_split('/\s+[·•|—–\/→]\s+|\s+-\s+|:\s+|\s*\(\s*|\s*\)\s*|,\s+|;\s+|\s+\+\s+|\s*=\s*/u', $s);
		$parts = array_values(array_filter(array_map('trim', $parts), fn ($p) => $p !== ''));
		if (count($parts) > 1 && DailyI18n::T($s) === $s)
		{
			foreach ($parts as $p) $add($p, $ns, false);
			return;
		}
		$s = preg_replace('/^[^\pL¿¡]+|[^\pL)]+$/u', '', $s);
		if ($s === '' || skipText($s)) return;
	}
	if (DailyI18n::T($s) !== $s) return;                 // ya cubierto
	$i = 0;
	$tpl = preg_replace_callback('/[-+]?\d+(?:[.,:\/]\d+)*/', function () use (&$i) { return '{' . ($i++) . '}'; }, $s);
	if (DailyI18n::T($tpl) !== $tpl) return;
	$k = nkey($s);
	$count[$k] = ($count[$k] ?? 0) + 1;
	if (!isset($seen[$k])) $seen[$k] = [$ns, $tpl];
};
foreach ($static as $file => $list) foreach ($list as $s) $add($s, ns($file));
foreach ($harvest as $route => $r) if ($route !== '/about') foreach ($r['texts'] ?? [] as $s) $add($s, ns($route), true);

// Textos presentes en 3 o más archivos → common
foreach ($seen as $k => &$v) if ($count[$k] >= 3) $v[0] = 'common';
unset($v);

@mkdir($outDir, 0777, true);
array_map('unlink', glob($outDir . '/*.txt'));
$byNs = [];
foreach ($seen as $k => $v) $byNs[$v[0]][] = $v[1];
ksort($byNs);
$index = [];
$id = 1;
$files = 0;
foreach ($byNs as $ns => $list)
{
	sort($list);
	foreach (array_chunk($list, $per) as $n => $chunk)
	{
		$lines = [];
		foreach ($chunk as $s) { $index[$id] = [$ns, $s]; $lines[] = $id . "\t" . $s; $id++; }
		file_put_contents(sprintf('%s/%s.%02d.txt', $outDir, $ns, $n), implode("\n", $lines) . "\n");
		$files++;
	}
}
file_put_contents($outDir . '/index.json', json_encode($index, JSON_UNESCAPED_UNICODE));
echo ($id - 1) . " textos pendientes en $files lotes, " . count($byNs) . " namespaces\n";
