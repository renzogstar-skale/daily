<?php
// Extrae textos en español de vistas Blade, JS de DAILY y PHP (etiquetas/mensajes) para el catálogo i18n.
// Uso: php bin/daily_i18n_extract.php <salida.json>  → {"archivo": ["texto", ...]}
ini_set("pcre.backtrack_limit", "50000000"); ini_set("pcre.jit", "0");
$root = dirname(__DIR__);
$out = [];

function isSpanishish(string $s): bool
{
	$s = trim($s);
	if (mb_strlen($s) < 2 || !preg_match('/\pL{2}/u', $s)) return false;
	if (preg_match('/^[a-z0-9_\-\.\/#:]+$/', $s)) return false;           // identificadores, rutas, clases
	if (preg_match('/^(fa-|btn|col-|text-|bg-|d-|m[trblxy]?-|p[trblxy]?-|https?:|\$|#|\.)/', $s)) return false;
	if (preg_match('/[{};=<>]|=>|\(\)|function\b|var\b|\breturn\b/', $s)) return false;
	return (bool) preg_match('/[áéíóúñÁÉÍÓÚÑ¿¡]|\b(de|del|la|las|el|los|en|por|para|con|sin|un|una|y|o|al|se|que|no|es|su|sus|hoy|total|ventas|stock|tienda|sucursal|producto|productos|merma|mermas|alerta|alertas|guardar|cancelar|cerrar|buscar|filtrar|ver|nuevo|nueva|editar|eliminar|estado|fecha|hora|cantidad|precio|costo|valor|motivo|responsable|pendiente|pendientes|activo|activa|todos|todas|exportar|imprimir|resumen|detalle|sin datos|semana|mes|año|días|día|turno|empleado|empleados|tarea|tareas|inventario|conteo|traslado|pedido|pedidos|cliente|clientes|proveedor|proveedores|compra|compras|caso|casos|acción|acciones|alto|alta|medio|media|bajo|baja|crítico|crítica|sí|abierto|abierta|cerrado|cerrada|vencido|vencida|aprobar|rechazar|enviar|recibir|agregar|registrar|confirmar|aplicar|limpiar|volver|siguiente|anterior|cargando|error|aviso|nombre|código|tipo|categoría|departamento|ubicación|almacén|lote|vence|vencimiento|unidades|meta|margen|utilidad)\b/iu', $s);
}

function add(array &$out, string $file, string $s): void
{
	$s = trim(preg_replace('/\s+/u', ' ', html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
	if (isSpanishish($s)) $out[$file][$s] = 1;
}

function bladeText(string $src, string $file, array &$out): void
{
	$src = preg_replace('/\{\{--.*?--\}\}/s', '', $src);
	$src = preg_replace('/@php\b.*?@endphp/s', ' ', $src);
	$src = preg_replace('/<style\b.*?<\/style>/si', ' ', $src);
	// Scripts embebidos: se tratan como JS
	$src = preg_replace_callback('/<script\b[^>]*>(.*?)<\/script>/si', function ($m) use ($file, &$out) { jsText($m[1], $file, $out); return ' '; }, $src);
	foreach (['title', 'placeholder', 'aria-label', 'alt', 'data-original-title', 'data-content', 'data-title', 'value', 'label'] as $a)
	{
		if (preg_match_all('/\s' . preg_quote($a) . '="([^"]*)"/u', $src, $m))
			foreach ($m[1] as $v) foreach (preg_split('/\{\{.*?\}\}|\{!!.*?!!\}|@\w+(\(.*?\))?/s', $v) as $p) add($out, $file, $p);
	}
	// Cadenas PHP dentro de {{ }} y @if(...): 'texto'
	if (preg_match_all('/\{\{(.*?)\}\}|\{!!(.*?)!!\}/s', $src, $m))
		foreach (array_merge($m[1], $m[2]) as $expr) phpStrings($expr, $file, $out);
	$text = preg_replace('/<[^>]+>/s', "\n", $src);
	foreach (preg_split('/\{\{.*?\}\}|\{!!.*?!!\}|\n|@(?:if|elseif|foreach|for|while|isset|empty|unless|switch|case|include|push|endpush|section|endsection|stack|extends|yield|endif|else|endforeach|endfor|endwhile|endisset|endempty|endunless|endswitch|break|continue|csrf|json)\b(?:\s*\((?:[^()]|\((?:[^()]|\([^()]*\))*\))*\))?/s', $text) as $p) add($out, $file, $p);
}

function jsText(string $src, string $file, array &$out): void
{
	$src = preg_replace('#/\*.*?\*/#s', ' ', $src);
	$src = preg_replace('#(^|[^:\\\\\'"])//[^\n]*#', '$1', $src);
	if (preg_match_all('/\'((?:[^\'\\\\\n]|\\\\.)*)\'|"((?:[^"\\\\\n]|\\\\.)*)"|`((?:[^`\\\\]|\\\\.)*)`/su', $src, $m, PREG_SET_ORDER))
	{
		foreach ($m as $g)
		{
			$s = $g[3] ?? ($g[2] ?? $g[1]);
			if ($s === '' && isset($g[2])) $s = $g[2];
			if ($s === '') $s = $g[1];
			$s = stripcslashes($s);
			// Fragmentos estáticos de plantillas `${}` y de HTML embebido
			foreach (preg_split('/\$\{(?:[^{}]|\{(?:[^{}]|\{[^{}]*\})*\})*\}/s', $s) as $frag)
			{
				if (preg_match('/<[a-z\/!]/i', $frag))
				{
					foreach (['title', 'placeholder', 'aria-label', 'alt', 'data-original-title'] as $a)
						if (preg_match_all('/' . $a . '=\\\\?["\']([^"\']*)/u', $frag, $am)) foreach ($am[1] as $v) add($out, $file, $v);
					foreach (preg_split('/<[^>]*>?|^[^<]*>/s', $frag) as $p) add($out, $file, $p);
				}
				else add($out, $file, $frag);
			}
		}
	}
}

function phpStrings(string $src, string $file, array &$out): void
{
	if (preg_match_all('/\'((?:[^\'\\\\]|\\\\.)*)\'|"((?:[^"\\\\$]|\\\\.)*)"/s', $src, $m, PREG_SET_ORDER))
		foreach ($m as $g)
		{
			$s = isset($g[2]) && $g[2] !== '' ? stripcslashes($g[2]) : str_replace(["\\'", '\\\\'], ["'", '\\'], $g[1]);
			if (preg_match('/^(SELECT|INSERT|UPDATE|DELETE|CREATE|WITH|ALTER|DROP)\b|\bFROM\b|\bWHERE\b|\bJOIN\b/i', $s)) continue;
			if (preg_match('/<[a-z\/]/i', $s)) { foreach (preg_split('/<[^>]*>/s', $s) as $p) add($out, $file, $p); continue; }
			foreach (preg_split('/%[sd]|\{[a-z_0-9]+\}/', $s) as $p) add($out, $file, $p);
		}
}

$rii = function ($dir, $re) use ($root) {
	$res = [];
	$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/' . $dir, FilesystemIterator::SKIP_DOTS));
	foreach ($it as $f) if (preg_match($re, $f->getFilename())) $res[] = $f->getPathname();
	return $res;
};
foreach ($rii('views', '/\.blade\.php$/') as $f) bladeText(file_get_contents($f), substr($f, strlen($root) + 1), $out);
foreach (array_merge($rii('public/viewjs', '/\.js$/'), glob($root . '/public/js/dairy_*.js'), glob($root . '/public/js/daily_*.js'), [$root . '/public/js/grocy.js']) as $f)
	if (!str_contains($f, 'daily_i18n')) jsText(file_get_contents($f), substr($f, strlen($root) + 1), $out);
foreach (array_merge($rii('controllers', '/\.php$/'), $rii('services', '/\.php$/'), $rii('helpers', '/\.php$/')) as $f)
	if (!str_contains($f, 'DailyI18n')) phpStrings(file_get_contents($f), substr(str_replace('\\', '/', $f), strlen($root) + 1), $out);

$res = [];
$total = [];
foreach ($out as $f => $set) { $res[str_replace('\\', '/', $f)] = array_keys($set); foreach ($set as $k => $_) $total[$k] = 1; }
file_put_contents($argv[1], json_encode($res, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
echo count($res) . ' archivos, ' . count($total) . " textos únicos\n";
