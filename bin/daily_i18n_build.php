<?php
// Compila localization/daily/en/*.json (un namespace por módulo) en public/i18n/daily_en.js.
// Uso: php bin/daily_i18n_build.php
$root = dirname(__DIR__);
$all = [];
$dups = 0;
foreach (glob($root . '/localization/daily/en/*.json') as $file)
{
	$part = json_decode(file_get_contents($file), true);
	if (!is_array($part))
	{
		fwrite(STDERR, 'JSON inválido: ' . basename($file) . "\n");
		exit(1);
	}
	foreach ($part as $es => $en)
	{
		$k = trim(preg_replace('/\s+/u', ' ', (string) $es));
		if ($k === '' || !is_string($en) || $en === '')
		{
			continue;
		}
		if (isset($all[$k]))
		{
			$dups++;
			continue;
		}
		$all[$k] = $en;
	}
}
@mkdir($root . '/public/i18n', 0777, true);
$js = "/* Generado por bin/daily_i18n_build.php desde localization/daily/en — no editar a mano. */\nwindow.DAILY_I18N_EN=" .
	json_encode($all, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ";\n";
file_put_contents($root . '/public/i18n/daily_en.js', $js);
echo count($all) . " entradas, $dups duplicadas omitidas, " . round(strlen($js) / 1024) . " KB\n";
