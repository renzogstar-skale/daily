<?php
/**
 * Genera el Mapa del Sistema con EVIDENCIA de ejecución y lo exporta.
 *
 *   php tests/dairy/system_map_build.php [--no-tests] [--full]
 *
 * 1. Corre run.php (unitarias, todas sobre copia de la BD) y http_sweep.php (todas las rutas GET; puerto 8096)
 *    — con --full también http_reliability.php y http_labor_forecast.php. --no-tests reutiliza la evidencia previa.
 * 2. Guarda la evidencia en data/system_map/evidence.json y el análisis en data/system_map/map.json (lo lee /system-map).
 * 3. Exporta docs/system-map/: system-map.json, mindmap.mmd, connections.mmd y SYSTEM_MAP.md.
 * No modifica la base de datos real (las pruebas trabajan sobre copias).
 */

$root = dirname(__DIR__, 2);
define('GROCY_DATAPATH', $root . '/data');
require_once $root . '/packages/autoload.php';
require_once $root . '/data/config.php';
require_once $root . '/config-dist.php';
if (GROCY_TIMEZONE !== '')
{
	date_default_timezone_set(GROCY_TIMEZONE);
}

$args = array_slice($argv, 1);
$dir = GROCY_DATAPATH . '/system_map';
@mkdir($dir, 0777, true);
$evFile = $dir . '/evidence.json';
$evidence = is_file($evFile) ? (json_decode(file_get_contents($evFile), true) ?: []) : [];

$run = function (string $script) use ($root): array {
	$out = [];
	$t = microtime(true);
	exec(escapeshellarg(PHP_BINARY) . ' -d opcache.enable=0 ' . escapeshellarg("$root/tests/dairy/$script") . ' 2>&1', $out, $code);
	echo "  $script → código $code (" . round(microtime(true) - $t) . " s)\n";
	return [$out, $code];
};

if (!in_array('--no-tests', $args, true))
{
	echo "Ejecutando pruebas (copias de la BD)…\n";
	// Unitarias: run.php imprime su bloque y luego «== test_x.php ==» por archivo, cada uno con «N verificaciones, M fallidas»
	[$out, $code] = $run('run.php');
	$files = [];
	$cur = 'run.php';
	foreach ($out as $l)
	{
		if (preg_match('/^== (test_\w+\.php) ==/', $l, $m))
		{
			$cur = $m[1];
			continue;
		}
		// Resúmenes aceptados: «N verificaciones, M fallidas/fallos», «N pruebas, M fallos», «N OK», «M de N FALLARON»
		if (preg_match('/^(\d+) (?:verificaciones|pruebas), (\d+) fall/', trim($l), $m))
		{
			$files[$cur] = ['ok' => intval($m[2]) === 0, 'checks' => intval($m[1]), 'fails' => intval($m[2])];
		}
		elseif (preg_match('/^(\d+) OK$/', trim($l), $m))
		{
			$files[$cur] = ['ok' => true, 'checks' => intval($m[1]), 'fails' => 0];
		}
		elseif (preg_match('/^(\d+) de (\d+) FALLARON$/', trim($l), $m))
		{
			$files[$cur] = ['ok' => false, 'checks' => intval($m[2]), 'fails' => intval($m[1])];
		}
		elseif (str_starts_with(ltrim($l), 'FAIL') || preg_match('/^\s+FAIL /', $l))
		{
			$files[$cur]['failed_checks'][] = trim($l);
		}
	}
	foreach (glob("$root/tests/dairy/test_*.php") as $f)
	{
		// Un archivo que terminó con error fatal no imprime resumen: se registra como fallido
		$files[basename($f)] ??= ['ok' => false, 'checks' => 0, 'fails' => 1, 'failed_checks' => ['sin resumen (error fatal o salida anticipada)']];
	}
	$evidence['tests'] = ['at' => date('Y-m-d H:i:s'), 'exit' => $code, 'files' => $files];

	[$out, $code] = $run('http_sweep.php');
	$routes = [];
	$total = $fails = 0;
	foreach ($out as $l)
	{
		if (preg_match('/^\s+(OK|FAIL)\s+(\/\S*)(.*)$/', $l, $m))
		{
			$detail = preg_match('/\[(.*)\]\s*$/', $m[3], $d) ? $d[1] : trim($m[3]);
			$routes[$m[2]] = ['ok' => $m[1] === 'OK', 'detail' => mb_substr($detail, 0, 200)];
		}
		if (preg_match('/^(\d+) verificaciones, (\d+) fallos/', trim($l), $m))
		{
			[$total, $fails] = [intval($m[1]), intval($m[2])];
		}
	}
	$evidence['sweep'] = ['at' => date('Y-m-d H:i:s'), 'exit' => $code, 'total' => $total, 'fails' => $fails, 'routes' => $routes];

	if (in_array('--full', $args, true))
	{
		foreach (['http_reliability.php', 'http_labor_forecast.php'] as $s)
		{
			[$out, $code] = $run($s);
			$sum = preg_grep('/^\d+ verificaciones/', array_map('trim', $out));
			preg_match('/^(\d+) verificaciones, (\d+)/', (string) end($sum), $m);
			$evidence['http'][$s] = ['at' => date('Y-m-d H:i:s'), 'ok' => $code === 0, 'checks' => intval($m[1] ?? 0), 'fails' => intval($m[2] ?? 0)];
			$evidence['tests']['files'][$s] = ['ok' => $code === 0, 'checks' => intval($m[1] ?? 0), 'fails' => intval($m[2] ?? 0)];
		}
	}
	file_put_contents($evFile, json_encode($evidence, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
}

$svc = \Grocy\Services\SystemMapService::GetInstance();
$map = $svc->Refresh('cli');
$docs = "$root/docs/system-map";
@mkdir($docs, 0777, true);
file_put_contents("$docs/system-map.json", json_encode($map, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
file_put_contents("$docs/mindmap.mmd", $svc->Mermaid($map, 'mindmap'));
file_put_contents("$docs/connections.mmd", $svc->Mermaid($map, 'flowchart'));
file_put_contents("$docs/SYSTEM_MAP.md", $svc->Report($map));

$s = $map['stats'];
echo "\nMapa: {$s['modules']} módulos, {$s['routes_web']} rutas web, {$s['routes_api']} endpoints, {$s['services']} servicios, {$s['tables']} tablas, {$map['duration_ms']} ms\n";
echo 'Estados: ' . json_encode($s['status']) . "\n";
echo "Conexiones verificadas {$s['connections_verified']}, no verificadas {$s['connections_unverified']}, propuestas {$s['proposals']}; hallazgos {$s['issues']}\n";
echo "Exportado en docs/system-map/\n";
