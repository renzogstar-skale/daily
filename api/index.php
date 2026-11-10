<?php

// Entrada para Vercel: la base demo se copia a /tmp (único disco escribible) y se reinicia con cada instancia nueva
$seed = __DIR__ . '/../vercel/data';
$data = '/tmp/data';

if (!file_exists($data . '/grocy_es.db'))
{
	foreach (['', '/viewcache', '/storage', '/plugins', '/logs'] as $dir)
	{
		@mkdir($data . $dir, 0777, true);
	}
	copy($seed . '/config.php', $data . '/config.php');
	copy($seed . '/grocy_es.db', $data . '/grocy_es.db');
}

define('GROCY_IS_EMBEDDED_INSTALL', false);
define('GROCY_DATAPATH', $data);

if (extension_loaded('zlib') && strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') === false)
{
	ini_set('zlib.output_compression', '1');
}

require_once __DIR__ . '/../app.php';
