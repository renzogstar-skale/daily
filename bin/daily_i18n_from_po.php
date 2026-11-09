<?php
// Genera localization/daily/en/00_grocy_core.json invirtiendo las traducciones de Grocy (es → msgid en inglés).
// Uso: php bin/daily_i18n_from_po.php
$root = dirname(__DIR__);
$map = [];
foreach (glob($root . '/localization/es/*.po') as $file)
{
	$txt = file_get_contents($file);
	preg_match_all('/^msgid ("(?:[^"\\\\]|\\\\.)*"(?:\s*\n"(?:[^"\\\\]|\\\\.)*")*)\s*\n(?:msgid_plural ("(?:[^"\\\\]|\\\\.)*")\s*\n)?((?:msgstr(?:\[\d\])? "(?:[^"\\\\]|\\\\.)*"(?:\s*\n"(?:[^"\\\\]|\\\\.)*")*\s*\n?)+)/m', $txt, $m, PREG_SET_ORDER);
	foreach ($m as $g)
	{
		$id = po($g[1]);
		$plural = isset($g[2]) && $g[2] !== '' ? po($g[2]) : null;
		preg_match_all('/msgstr(?:\[(\d)\])? ("(?:[^"\\\\]|\\\\.)*"(?:\s*\n"(?:[^"\\\\]|\\\\.)*")*)/', $g[3], $ms, PREG_SET_ORDER);
		foreach ($ms as $s)
		{
			$es = po($s[2]);
			$en = ($s[1] !== '' && (int) $s[1] > 0 && $plural) ? $plural : $id;
			if ($es === '' || $id === '' || $es === $en || preg_match('/^[a-z0-9]+([_\-][a-z0-9]+)+$|^[a-z]{1,3}(_[A-Z]{2})?$/', $en)) continue;
			$k = tpl($es);
			$v = tpl($en);
			if (!preg_match('/\pL{2}/u', $k) || isset($map[$k])) continue;
			$map[$k] = $v;
		}
	}
}
function po(string $q): string
{
	preg_match_all('/"((?:[^"\\\\]|\\\\.)*)"/', $q, $p);
	return trim(preg_replace('/\s+/u', ' ', stripcslashes(implode('', $p[1]))));
}
function tpl(string $s): string
{
	$i = 0;
	return preg_replace_callback('/%(\d\$)?[sd]/', function () use (&$i) { return '{' . ($i++) . '}'; }, $s);
}
@mkdir($root . '/localization/daily/en', 0777, true);
file_put_contents($root . '/localization/daily/en/00_grocy_core.json', json_encode($map, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
echo count($map) . " entradas\n";
