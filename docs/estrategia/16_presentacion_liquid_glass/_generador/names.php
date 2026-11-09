<?php
// Extrae nombres de personas de la BD para anonimizar capturas (salida solo al scratchpad).
$db = new PDO('sqlite:C:/Users/renzo/grocy/grocy/data/grocy_es.db');
$out = [];
$tables = $db->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);
$want = ['employees', 'users', 'workforce_planilla_collaborators'];
foreach ($want as $t) {
	if (!in_array($t, $tables)) continue;
	$cols = array_column($db->query("PRAGMA table_info($t)")->fetchAll(PDO::FETCH_ASSOC), 'name');
	$nc = array_values(array_filter($cols, fn($c) => preg_match('/^(first_?name|last_?name|name|full_?name|display_name|nombre.*|apellido.*)$/i', $c)));
	if (!$nc) continue;
	foreach ($db->query("SELECT " . implode(',', $nc) . " FROM $t") as $r) {
		$parts = [];
		foreach ($nc as $c) { $v = trim((string)$r[$c]); if ($v !== '') $parts[] = $v; }
		$full = trim(implode(' ', $parts));
		if ($full === '' || preg_match('/^(emp\d+|admin|demo|Colaborador)/i', $full)) continue;
		$out[] = $full;
		foreach (preg_split('/\s+/', $full) as $w) if (mb_strlen($w) >= 5) $out[] = $w;
	}
}
$out = array_values(array_unique($out));
file_put_contents(__DIR__ . '/names.json', json_encode($out, JSON_UNESCAPED_UNICODE));
echo count($out) . " nombres\n";
$prod = mb_strtolower(implode(' ', $db->query("SELECT name FROM products UNION SELECT name FROM locations UNION SELECT name FROM shopping_locations")->fetchAll(PDO::FETCH_COLUMN)));
$keep = array_values(array_filter($out, fn($n) => str_contains($n, ' ') || !preg_match('/\b' . preg_quote(mb_strtolower($n), '/') . '\b/u', $prod)));
file_put_contents(__DIR__ . '/names.json', json_encode($keep, JSON_UNESCAPED_UNICODE));
echo (count($out) - count($keep)) . " palabras quitadas por chocar con productos\n";
