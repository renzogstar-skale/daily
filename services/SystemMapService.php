<?php

namespace Grocy\Services;

use Grocy\Helpers\DairyAccess;
use Grocy\Helpers\SystemMapCatalog;

/**
 * Mapa del Sistema (/system-map): análisis ESTÁTICO y de solo lectura del repositorio.
 * Lee routes.php, controladores, vistas Blade, JS, servicios, migraciones y pruebas; cruza las llamadas del frontend
 * con las rutas registradas, los permisos (DairyAccess::PAGES + permisos usados en cada endpoint), las tablas reales
 * (sqlite_master) y la evidencia de ejecución que deja tests/dairy/system_map_build.php (barrido HTTP + pruebas).
 * No ejecuta código del proyecto ni escribe nada fuera de data/system_map/.
 *
 * Estados: verified (carga sin errores + prueba automática que pasa), partial (funciona con observaciones o sin prueba
 * de sus operaciones), error (falla confirmada con evidencia), unverified (sin evidencia de ejecución), proposed.
 */
class SystemMapService extends BaseService
{
	const SCHEMA = 1;

	// Controladores del Grocy original (git ls-files): sus APIs son públicas para clientes externos
	const CORE_CONTROLLERS = ['BatteriesApiController', 'CalendarApiController', 'ChoresApiController', 'FilesApiController', 'GenericEntityApiController', 'OpenApiController', 'PrintApiController', 'RecipesApiController', 'StockApiController', 'SystemApiController', 'TasksApiController', 'UsersApiController', 'BatteriesController', 'CalendarController', 'ChoresController', 'EquipmentController', 'GenericEntityController', 'LoginController', 'RecipesController', 'StockController', 'StockReportsController', 'SystemController', 'TasksController', 'UsersController'];

	// Archivos JS del frontend que usan ayudantes propios get()/post() relativos a /api
	const API_HELPER_FILES = ['public/js/dairy_m.js', 'public/js/dairy_mobile.js', 'public/js/dairy_offline.js'];

	private string $root;
	private array $files = [];
	private array $controllers = [];
	private array $methods = [];
	private array $routes = [];
	private array $apiRoutes = [];
	private array $tables = [];
	private array $services = [];
	private array $jsCorpus = [];

	public function __construct()
	{
		parent::__construct();
		$this->root = dirname(__DIR__);
	}

	public static function DataDir(): string
	{
		return GROCY_DATAPATH . '/system_map';
	}

	// Último análisis guardado; si no existe, lo genera
	public function Get(): array
	{
		$file = self::DataDir() . '/map.json';
		if (is_file($file))
		{
			$map = json_decode((string) file_get_contents($file), true);
			if (is_array($map) && ($map['schema'] ?? 0) === self::SCHEMA)
			{
				return $map;
			}
		}
		return $this->Refresh();
	}

	// Re-analiza y guarda (procedimiento seguro: solo lectura del repositorio + escritura en data/system_map)
	public function Refresh(?string $by = null): array
	{
		$map = $this->Analyze();
		$map['generated_by'] = $by;
		$dir = self::DataDir();
		if (!is_dir($dir))
		{
			mkdir($dir, 0777, true);
		}
		file_put_contents($dir . '/map.json', json_encode($map, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
		return $map;
	}

	public function Evidence(): array
	{
		$file = self::DataDir() . '/evidence.json';
		$e = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
		return is_array($e) ? $e : [];
	}

	public function Analyze(): array
	{
		$t0 = microtime(true);
		$this->LoadTables();
		$this->ParseControllers();
		$this->ParseRoutes();
		$this->ParseServices();
		$this->LoadJsCorpus();
		$evidence = $this->Evidence();
		$tests = $this->ParseTests($evidence);

		$categories = [];
		foreach (SystemMapCatalog::CATEGORIES as $id => [$name, $icon, $color])
		{
			$categories[] = ['id' => $id, 'name' => $name, 'icon' => $icon, 'color' => $color];
		}

		// 1. Módulos: menú lateral + extras del catálogo
		$modules = [];
		foreach ($this->ParseNav() as $n)
		{
			$modules[$n['id']] = $n;
		}
		foreach (SystemMapCatalog::EXTRA_MODULES as $id => [$cat, $name, $route, $desc, $more])
		{
			$modules[$id] = ['id' => $id, 'category' => $cat, 'name' => $name, 'route' => $route, 'description' => $desc, 'in_nav' => false, 'nav_views' => [], 'extra_routes' => $more, 'icon' => SystemMapCatalog::CATEGORIES[$cat][1]];
		}
		$modules['system_map'] ??= ['id' => 'system_map', 'category' => 'admin', 'name' => 'Mapa del Sistema', 'route' => '/system-map', 'description' => 'Este mapa', 'in_nav' => false, 'nav_views' => [], 'extra_routes' => [], 'icon' => 'fa-sitemap'];

		// Rutas web asignadas a algún módulo; el resto queda en «Rutas sin módulo»
		$webGet = array_filter($this->routes, fn($r) => $r['group'] === 'web' && $r['method'] === 'GET');
		$viewToRoutes = [];
		foreach ($webGet as $r)
		{
			foreach ($r['views'] as $v)
			{
				$viewToRoutes[$v][] = $r['path'];
			}
		}
		$assigned = [];
		$issues = [];
		$mobileServices = [];
		$mobileControllers = [];
		$mobileCalls = $this->CallsIn(['public/js/dairy_m.js', 'public/js/dairy_offline.js']);
		foreach ($mobileCalls as $c)
		{
			foreach ($c['routes'] as $ri)
			{
				$mobileControllers[$this->apiRoutes[$ri]['controller'] ?? ''] = true;
				foreach ($this->apiRoutes[$ri]['services'] as $s)
				{
					$mobileServices[$s] = true;
				}
			}
		}

		foreach ($modules as $id => &$m)
		{
			$path = explode('#', $m['route'])[0];
			$tab = str_contains($m['route'], '#') ? explode('#', $m['route'])[1] : null;
			$paths = array_values(array_unique(array_merge([$path], $m['extra_routes'])));
			foreach ($m['nav_views'] as $v)
			{
				foreach ($viewToRoutes[$v] ?? [] as $p)
				{
					$paths[] = $p;
				}
			}
			$paths = array_values(array_unique($paths));
			// El simulador vive dentro de /ai-advisor: no se apropia de sus rutas
			$owned = $tab ? [] : $paths;
			foreach ($owned as $p)
			{
				$assigned[$p] = $id;
			}
			$m = $this->BuildModule($m, $paths, $tab, $tests, $evidence, $mobileServices, $mobileControllers);
		}
		unset($m);

		// 2. Conexiones operativas comprobadas contra el código
		$connections = [];
		foreach (SystemMapCatalog::CONNECTIONS as [$from, $to, $label, $file, $pattern])
		{
			$hit = $this->Find($file, $pattern);
			$connections[] = ['from' => $from, 'to' => $to, 'label' => $label, 'status' => $hit ? 'verified' : 'unverified', 'kind' => 'operational',
				'evidence' => $hit ?: ['file' => $file, 'pattern' => $pattern, 'note' => 'Patrón no encontrado: conexión no comprobada']];
		}
		$proposals = [];
		foreach (SystemMapCatalog::PROPOSALS as [$pid, $from, $to, $title, $source, $file, $pattern])
		{
			$hit = $this->Find($file, $pattern);
			$proposals[] = ['id' => $pid, 'from' => $from, 'to' => $to, 'title' => $title, 'source' => $source, 'maybe_implemented' => $hit ?: null];
			$connections[] = ['from' => $from, 'to' => $to, 'label' => $title, 'status' => 'proposed', 'kind' => 'proposal', 'evidence' => ['source' => $source]];
		}
		foreach ($connections as $c)
		{
			foreach (['from', 'to'] as $end)
			{
				if (isset($modules[$c[$end]]))
				{
					$modules[$c[$end]]['connections'][] = ['with' => $c[$end === 'from' ? 'to' : 'from'], 'dir' => $end === 'from' ? 'out' : 'in', 'label' => $c['label'], 'status' => $c['status']];
				}
			}
		}

		// 3. Hallazgos
		$issues = array_merge($issues, $this->CollectIssues($modules, $assigned, $connections));
		foreach (SystemMapCatalog::KNOWN_ISSUES as [$kid, $prio, $type, $mod, $title, $doc, $rec, $file, $pattern, $mustExist])
		{
			$hit = $this->Find($file, $pattern);
			$open = $mustExist ? (bool) $hit : !$hit;
			$issues[] = ['id' => $kid, 'severity' => $prio, 'type' => $type, 'module' => $mod, 'title' => $title, 'confidence' => $open ? 'documented' : 'maybe_fixed',
				'evidence' => [['doc' => $doc], $hit ?: ['file' => $file, 'note' => ($mustExist ? 'No se encontró ' : 'No existe ') . "«{$pattern}» en {$file}"]],
				'recommendation' => $rec . ($open ? '' : ' — La condición ya no se cumple en el código: revisar y cerrar.')];
		}
		usort($issues, fn($a, $b) => [$a['severity'], $a['module']] <=> [$b['severity'], $b['module']]);
		foreach ($issues as $i)
		{
			if (isset($modules[$i['module']]))
			{
				$modules[$i['module']]['issues'][] = $i['id'];
			}
		}
		foreach ($modules as &$m)
		{
			$this->FinalStatus($m, $issues);
		}
		unset($m);

		// 4. Rutas sin módulo (inventario completo)
		$unassigned = [];
		foreach ($webGet as $r)
		{
			if (!isset($assigned[$r['path']]))
			{
				$unassigned[] = $r['path'];
			}
		}

		$routesOut = [];
		foreach ($this->routes as $r)
		{
			$routesOut[] = ['method' => $r['method'], 'path' => $r['path'], 'group' => $r['group'], 'handler' => $r['controller'] ? $r['controller'] . '::' . $r['action'] : 'closure (redirección)',
				'line' => $r['line'], 'module' => $assigned[$r['path']] ?? null, 'handler_ok' => $r['handler_ok'], 'views' => $r['views'], 'permissions' => $r['permissions'],
				'callers' => count($r['callers'] ?? []), 'sweep' => $evidence['sweep']['routes'][$r['path']] ?? null, 'core' => in_array($r['controller'], self::CORE_CONTROLLERS, true)];
		}

		$statusCount = array_count_values(array_column($modules, 'status'));
		return [
			'schema' => self::SCHEMA,
			'generated_at' => date('Y-m-d H:i:s'),
			'duration_ms' => intval((microtime(true) - $t0) * 1000),
			'stats' => [
				'modules' => count($modules), 'routes_web' => count(array_filter($this->routes, fn($r) => $r['group'] === 'web')), 'routes_api' => count($this->apiRoutes),
				'controllers' => count($this->controllers), 'services' => count($this->services), 'views' => count(glob($this->root . '/views/*.blade.php')) + count(glob($this->root . '/views/*/*.blade.php')),
				'viewjs' => count(glob($this->root . '/public/viewjs/*.js')), 'tables' => count($this->tables), 'migrations' => count(glob($this->root . '/migrations/*')),
				'tests' => count($tests), 'connections_verified' => count(array_filter($connections, fn($c) => $c['status'] === 'verified')),
				'connections_unverified' => count(array_filter($connections, fn($c) => $c['status'] === 'unverified')), 'proposals' => count($proposals),
				'issues' => count($issues), 'status' => $statusCount,
			],
			'evidence' => [
				'sweep' => isset($evidence['sweep']) ? array_diff_key($evidence['sweep'], ['routes' => 1]) : null,
				'tests' => $evidence['tests']['files'] ?? null, 'tests_at' => $evidence['tests']['at'] ?? null,
				'http' => $evidence['http'] ?? null,
			],
			'categories' => $categories,
			'modules' => array_values($modules),
			'connections' => $connections,
			'proposals' => $proposals,
			'issues' => $issues,
			'routes' => $routesOut,
			'unassigned_routes' => $unassigned,
			'architecture' => $this->Architecture($tests),
		];
	}

	// ------------------------------------------------------------------ exportaciones

	const STATUS_LABEL = ['verified' => 'Verificado', 'partial' => 'Parcial', 'error' => 'Error confirmado', 'unverified' => 'No verificado', 'proposed' => 'Propuesto'];

	private static function MmText(string $s): string
	{
		return str_replace(['"', '(', ')', '[', ']', '{', '}', '<', '>', '#', ';'], ["'", ' ', ' ', ' ', ' ', ' ', ' ', ' ', ' ', 'n.', ','], $s);
	}

	// kind = mindmap (jerarquía) | flowchart (conexiones)
	public function Mermaid(array $map, string $kind): string
	{
		$mods = array_column($map['modules'], null, 'id');
		if ($kind === 'flowchart')
		{
			$out = ["flowchart LR"];
			foreach ($map['categories'] as $c)
			{
				$ids = array_filter($map['modules'], fn($m) => $m['category'] === $c['id']);
				if (!$ids)
				{
					continue;
				}
				$out[] = "  subgraph {$c['id']}[\"" . self::MmText($c['name']) . "\"]";
				foreach ($ids as $m)
				{
					$out[] = "    {$m['id']}[\"" . self::MmText($m['name']) . "\"]:::{$m['status']}";
				}
				$out[] = '  end';
			}
			foreach ($map['connections'] as $c)
			{
				if (!isset($mods[$c['from']], $mods[$c['to']]))
				{
					continue;
				}
				$arrow = $c['status'] === 'verified' ? '-->' : '-.->';
				$out[] = "  {$c['from']} $arrow|\"" . self::MmText(mb_substr($c['label'], 0, 50)) . ($c['status'] === 'proposed' ? ' (propuesta)' : ($c['status'] === 'unverified' ? ' (no verificada)' : '')) . "\"| {$c['to']}";
			}
			$out[] = '  classDef verified fill:#dcfce7,stroke:#16a34a';
			$out[] = '  classDef partial fill:#fef9c3,stroke:#ca8a04';
			$out[] = '  classDef error fill:#fee2e2,stroke:#dc2626';
			$out[] = '  classDef unverified fill:#f1f5f9,stroke:#94a3b8';
			return implode("\n", $out) . "\n";
		}
		$out = ['mindmap', '  root((DAIRY))'];
		foreach ($map['categories'] as $c)
		{
			$ms = array_filter($map['modules'], fn($m) => $m['category'] === $c['id']);
			if (!$ms && $c['id'] !== 'arquitectura')
			{
				continue;
			}
			$out[] = '    ' . self::MmText($c['name']);
			foreach ($ms as $m)
			{
				$out[] = '      ' . self::MmText($m['name'] . ' · ' . self::STATUS_LABEL[$m['status']]);
				foreach (array_slice($m['tabs'], 0, 12) as $t)
				{
					$out[] = '        ' . self::MmText($t['label']);
				}
			}
			if ($c['id'] === 'arquitectura')
			{
				foreach ($map['architecture']['layers'] as $l)
				{
					$out[] = '      ' . self::MmText($l['name']);
				}
			}
		}
		return implode("\n", $out) . "\n";
	}

	// Informe Markdown: inventario, auditoría, problemas priorizados, incompletos y recomendaciones
	public function Report(array $map): string
	{
		$s = $map['stats'];
		$L = self::STATUS_LABEL;
		$cat = array_column($map['categories'], 'name', 'id');
		$o = [];
		$o[] = '# DAIRY — Mapa del Sistema (informe generado)';
		$o[] = '';
		$o[] = "Generado: {$map['generated_at']} por `SystemMapService` (análisis estático) + evidencia de `tests/dairy/system_map_build.php`. Ver en la app: `/system-map` (solo ADMIN).";
		$o[] = '';
		$o[] = '**Criterio de estado** — Verificado: la pantalla carga sin errores en el barrido HTTP **y** al menos una prueba automática que la cubre pasa. Parcial: carga, pero sin prueba de sus operaciones o con hallazgos abiertos. Error: falla confirmada (ruta/controlador/vista inexistente, llamada a API inexistente, barrido o prueba fallida). No verificado: sin evidencia de ejecución. Propuesto: no implementado.';
		$o[] = '';
		$ev = $map['evidence'];
		$o[] = '## Evidencia de ejecución';
		$o[] = '- Barrido HTTP: ' . ($ev['sweep'] ? "{$ev['sweep']['at']} — {$ev['sweep']['total']} verificaciones, {$ev['sweep']['fails']} fallos" : 'no ejecutado');
		foreach ((array) $ev['tests'] as $f => $r)
		{
			$o[] = "- `$f`: " . ($r['ok'] ? 'OK' : 'FALLA') . " ({$r['checks']} verificaciones, {$r['fails']} fallidas)";
		}
		foreach ((array) $ev['http'] as $f => $r)
		{
			$o[] = "- `$f`: " . ($r['ok'] ? 'OK' : 'FALLA') . " ({$r['checks']} verificaciones, {$r['fails']} fallos) {$r['at']}";
		}
		$o[] = '';
		$o[] = '## Resumen';
		$o[] = "| Módulos | Rutas web | Endpoints API | Controladores | Servicios | Vistas | Tablas | Migraciones | Conexiones verificadas | Propuestas | Hallazgos |";
		$o[] = '|---|---|---|---|---|---|---|---|---|---|---|';
		$o[] = "| {$s['modules']} | {$s['routes_web']} | {$s['routes_api']} | {$s['controllers']} | {$s['services']} | {$s['views']} | {$s['tables']} | {$s['migrations']} | {$s['connections_verified']} | {$s['proposals']} | {$s['issues']} |";
		$o[] = '';
		$o[] = 'Estados: ' . implode(' · ', array_map(fn($k, $v) => ($L[$k] ?? $k) . " $v", array_keys($s['status']), $s['status']));
		$o[] = '';
		$o[] = '## Inventario de módulos, rutas y funcionalidades';
		foreach ($map['categories'] as $c)
		{
			$ms = array_filter($map['modules'], fn($m) => $m['category'] === $c['id']);
			if (!$ms)
			{
				continue;
			}
			$o[] = "### {$c['name']}";
			$o[] = '| Módulo | Ruta | Estado | Pestañas | Acciones | Endpoints | Tablas | Pruebas | Móvil |';
			$o[] = '|---|---|---|---|---|---|---|---|---|';
			foreach ($ms as $m)
			{
				$o[] = "| {$m['name']} | `{$m['route']}` | {$L[$m['status']]} | " . count($m['tabs']) . ' | ' . count($m['buttons']) . ' | ' . count($m['endpoints']) . ' | ' . count($m['tables']) . ' | ' . count($m['tests']) . ' | ' . ($m['in_mobile'] ? 'sí' : '—') . ' |';
			}
			$o[] = '';
		}
		$o[] = '## Problemas priorizados';
		$o[] = '| Prioridad | Tipo | Módulo | Hallazgo | Confianza | Evidencia | Recomendación |';
		$o[] = '|---|---|---|---|---|---|---|';
		$conf = ['confirmed' => 'Confirmado', 'documented' => 'Documentado', 'suspected' => 'Sospecha', 'maybe_fixed' => 'Posiblemente resuelto'];
		foreach ($map['issues'] as $i)
		{
			$evs = implode('; ', array_map(fn($e) => isset($e['file']) ? "`{$e['file']}" . (isset($e['line']) ? ":{$e['line']}" : '') . '`' : ($e['doc'] ?? $e['test'] ?? ''), $i['evidence']));
			$o[] = "| {$i['severity']} | {$i['type']} | {$i['module']} | " . str_replace('|', '\|', $i['title']) . " | {$conf[$i['confidence']]} | $evs | " . str_replace('|', '\|', $i['recommendation']) . ' |';
		}
		$o[] = '';
		$o[] = '## Módulos incompletos o sin verificar';
		foreach ($map['modules'] as $m)
		{
			if ($m['status'] !== 'verified')
			{
				$o[] = "- **{$m['name']}** (`{$m['route']}`, {$L[$m['status']]}): " . implode('; ', $m['status_reasons']);
			}
		}
		$o[] = '';
		$o[] = '## Conexiones entre módulos';
		$o[] = '| Desde | Hacia | Qué fluye | Estado | Evidencia |';
		$o[] = '|---|---|---|---|---|';
		$names = array_column($map['modules'], 'name', 'id');
		foreach ($map['connections'] as $c)
		{
			$e = $c['evidence'];
			$evs = isset($e['line']) ? "`{$e['file']}:{$e['line']}`" : ($e['source'] ?? ($e['note'] ?? ''));
			$o[] = '| ' . ($names[$c['from']] ?? $c['from']) . ' | ' . ($names[$c['to']] ?? $c['to']) . " | {$c['label']} | " . ['verified' => 'Comprobada', 'unverified' => 'No comprobada', 'proposed' => 'Propuesta'][$c['status']] . " | $evs |";
		}
		$o[] = '';
		$o[] = '## Recomendaciones para integrar mejor el sistema';
		foreach ($map['proposals'] as $p)
		{
			$o[] = "- {$p['title']} (" . ($names[$p['from']] ?? $p['from']) . ' → ' . ($names[$p['to']] ?? $p['to']) . "). Fuente: {$p['source']}" . ($p['maybe_implemented'] ? " — **revisar: hay indicios en `{$p['maybe_implemented']['file']}:{$p['maybe_implemented']['line']}`**" : '');
		}
		$o[] = '';
		$o[] = '## Arquitectura';
		foreach ($map['architecture']['layers'] as $l)
		{
			$o[] = "- **{$l['name']}**: {$l['description']}";
		}
		$o[] = '';
		$o[] = '### Servicios (dependencias y tablas)';
		$o[] = '| Servicio | Líneas | Depende de | Tablas | Usado por |';
		$o[] = '|---|---|---|---|---|';
		foreach ($map['architecture']['services'] as $sv)
		{
			$o[] = "| {$sv['name']} | {$sv['lines']} | " . implode(', ', $sv['deps']) . ' | ' . implode(', ', array_slice($sv['tables'], 0, 12)) . (count($sv['tables']) > 12 ? ' …' : '') . ' | ' . implode(', ', array_slice($sv['used_by'], 0, 8)) . ' |';
		}
		$o[] = '';
		$o[] = '## Rutas sin módulo asignado';
		$o[] = implode(', ', array_map(fn($p) => "`$p`", $map['unassigned_routes']));
		$o[] = '';
		$o[] = '## Inventario completo de rutas';
		$o[] = '| Método | Ruta | Controlador | Módulo | Permisos en el método | Consumidores | Barrido |';
		$o[] = '|---|---|---|---|---|---|---|';
		foreach ($map['routes'] as $r)
		{
			$sw = $r['sweep'] ? ($r['sweep']['ok'] ? 'OK' : 'FALLA') : '—';
			$o[] = "| {$r['method']} | `{$r['path']}` | {$r['handler']} | " . ($r['module'] ?? '') . ' | ' . implode(', ', $r['permissions']) . " | {$r['callers']} | $sw |";
		}
		return implode("\n", $o) . "\n";
	}

	// ------------------------------------------------------------------ módulo

	private function BuildModule(array $m, array $paths, ?string $tab, array $tests, array $evidence, array $mobileServices, array $mobileControllers): array
	{
		$screens = [];
		$views = [];
		$services = [];
		$permissions = [];
		$files = [];
		foreach ($paths as $p)
		{
			$r = $this->RouteFor('GET', $p, 'web');
			if (!$r)
			{
				$screens[] = ['path' => $p, 'exists' => false];
				continue;
			}
			$screens[] = ['path' => $p, 'exists' => true, 'handler' => $r['controller'] ? $r['controller'] . '::' . $r['action'] : 'closure', 'handler_ok' => $r['handler_ok'], 'views' => $r['views'],
				'line' => $r['line'], 'sweep' => $evidence['sweep']['routes'][$p] ?? null, 'page_permissions' => DairyAccess::PAGES[$p] ?? []];
			$views = array_merge($views, $r['views']);
			$services = array_merge($services, $r['services']);
			$permissions = array_merge($permissions, DairyAccess::PAGES[$p] ?? [], $r['permissions']);
			if ($r['file'])
			{
				$files[] = ['path' => $r['file'], 'line' => $r['method_line']];
			}
		}
		$views = array_values(array_unique($views));

		// Contenido de las vistas: pestañas, acciones, formularios, modales y llamadas a la API
		$tabs = $buttons = $forms = $modals = $calls = $suspects = [];
		foreach ($views as $v)
		{
			$ui = $this->ParseView($v);
			$files = array_merge($files, $ui['files']);
			$tabs = array_merge($tabs, $ui['tabs']);
			$buttons = array_merge($buttons, $ui['buttons']);
			$forms = array_merge($forms, $ui['forms']);
			$modals = array_merge($modals, $ui['modals']);
			$calls = array_merge($calls, $ui['calls']);
			$suspects = array_merge($suspects, $ui['suspects']);
		}
		if ($tab)
		{
			$tabs = array_values(array_filter($tabs, fn($t) => $t['id'] === $tab));
		}

		// Endpoints usados por el frontend del módulo
		$endpoints = [];
		$broken = [];
		foreach ($calls as $c)
		{
			if (!$c['routes'])
			{
				$broken[] = $c;
				continue;
			}
			foreach ($c['routes'] as $ri)
			{
				$a = $this->apiRoutes[$ri];
				$key = $a['method'] . ' ' . $a['path'];
				$endpoints[$key] ??= ['method' => $a['method'], 'path' => $a['path'], 'handler' => $a['controller'] . '::' . $a['action'], 'permissions' => $a['permissions'], 'services' => $a['services'],
					'line' => $a['line'], 'from' => [], 'sweep' => $evidence['sweep']['routes'][$a['path']] ?? null, 'tested_by' => $this->TestsFor($a, $tests)];
				$endpoints[$key]['from'][] = $c['file'] . ':' . $c['line'];
				$services = array_merge($services, $a['services']);
				$permissions = array_merge($permissions, $a['permissions']);
			}
		}
		foreach ($endpoints as &$e)
		{
			$e['from'] = array_values(array_unique($e['from']));
		}
		unset($e);

		$services = array_values(array_unique(array_filter($services)));
		$tables = [];
		foreach ($services as $s)
		{
			$tables = array_merge($tables, $this->services[$s]['tables'] ?? []);
		}
		foreach ($screens as $s)
		{
			if (!empty($s['handler']) && str_contains($s['handler'], '::'))
			{
				$tables = array_merge($tables, $this->methods[$s['handler']]['tables'] ?? []);
			}
		}
		$permissions = array_values(array_unique(array_filter($permissions)));
		sort($permissions);

		// Pruebas que tocan sus servicios o endpoints, con su resultado
		$testHits = [];
		foreach ($tests as $name => $t)
		{
			$why = array_values(array_intersect($services, $t['services']));
			foreach ($endpoints as $e)
			{
				if (in_array($e['path'], $t['paths'], true))
				{
					$why[] = $e['path'];
				}
			}
			if ($why)
			{
				$testHits[] = ['file' => $name, 'ok' => $t['ok'], 'checks' => $t['checks'], 'fails' => $t['fails'], 'via' => array_slice(array_values(array_unique($why)), 0, 6)];
			}
		}

		$controllers = array_unique(array_map(fn($e) => explode('::', $e['handler'])[0], $endpoints));
		$inMobile = $m['id'] === 'mobile' || count(array_intersect($services, array_keys($mobileServices))) > 0 || count(array_intersect($controllers, array_keys($mobileControllers))) > 0;

		$m['screens'] = $screens;
		$m['views'] = $views;
		$m['tabs'] = $this->Uniq($tabs, 'id');
		$m['buttons'] = $this->Uniq($buttons, 'key');
		$m['forms'] = $this->Uniq($forms, 'key');
		$m['modals'] = $this->Uniq($modals, 'id');
		$m['endpoints'] = array_values($endpoints);
		$m['broken_calls'] = $broken;
		$m['suspect_buttons'] = $suspects;
		$m['services'] = $services;
		$m['tables'] = array_values(array_unique($tables));
		$m['permissions'] = $permissions;
		$m['roles'] = $this->RolesFor($permissions, $screens);
		$m['tests'] = $testHits;
		$m['in_mobile'] = $inMobile;
		$m['files'] = $this->Uniq($files, 'path');
		$m['connections'] = [];
		$m['issues'] = [];
		$m['url'] = str_contains($m['route'], '{') ? null : $m['route'];
		return $m;
	}

	private function FinalStatus(array &$m, array $issues): void
	{
		$reasons = [];
		$err = false;
		$main = $m['screens'][0] ?? null;
		foreach ($m['screens'] as $s)
		{
			if (!$s['exists'])
			{
				$err = true;
				$reasons[] = "Ruta {$s['path']} no registrada en routes.php";
			}
			elseif (!$s['handler_ok'])
			{
				$err = true;
				$reasons[] = "Controlador/vista de {$s['path']} inexistente";
			}
			elseif ($s['sweep'] && !$s['sweep']['ok'])
			{
				$err = true;
				$reasons[] = "Barrido HTTP falló en {$s['path']}: {$s['sweep']['detail']}";
			}
		}
		if ($m['broken_calls'])
		{
			$err = true;
			$reasons[] = count($m['broken_calls']) . ' llamada(s) a API sin ruta registrada';
		}
		foreach ($m['tests'] as $t)
		{
			if ($t['ok'] === false)
			{
				$err = true;
				$reasons[] = "Prueba {$t['file']} con fallos";
			}
		}
		$open = array_filter($issues, fn($i) => $i['module'] === $m['id'] && $i['confidence'] !== 'maybe_fixed' && $i['severity'] !== 'Info');
		$passing = array_filter($m['tests'], fn($t) => $t['ok'] === true);
		$swept = $main && $main['exists'] && $main['sweep'] && $main['sweep']['ok'];
		if ($err)
		{
			$status = 'error';
		}
		elseif (!$swept && !$passing)
		{
			$status = 'unverified';
			$reasons[] = $main && str_contains($main['path'], '{') ? 'Ruta con parámetros: el barrido no la abre y no hay prueba automática' : 'Sin evidencia de ejecución (correr tests/dairy/system_map_build.php)';
		}
		elseif ($open || !$passing || !$swept)
		{
			$status = 'partial';
			if (!$swept)
			{
				$reasons[] = 'Pantalla no abierta por el barrido (ruta con parámetros)';
			}
			if (!$passing)
			{
				$reasons[] = 'Carga sin errores, pero ninguna prueba automática cubre sus operaciones';
			}
			foreach ($open as $i)
			{
				$reasons[] = "{$i['severity']}: {$i['title']}";
			}
		}
		else
		{
			$status = 'verified';
			$reasons[] = 'Barrido HTTP sin errores y ' . count($passing) . ' prueba(s) automática(s) que pasan';
		}
		$m['status'] = $status;
		$m['status_reasons'] = $reasons;
	}

	private function RolesFor(array $perms, array $screens): array
	{
		$pagePerms = [];
		foreach ($screens as $s)
		{
			$pagePerms = array_merge($pagePerms, $s['page_permissions'] ?? []);
		}
		$roles = ['ADMIN (todo)'];
		if (!$pagePerms)
		{
			$roles[] = 'Cualquier usuario autenticado puede abrir la pantalla; las acciones exigen los permisos listados';
			return $roles;
		}
		$manager = ['KPIS_VIEW', 'WORKFORCE_SCHEDULE_MANAGE', 'BRANCH_MANAGE'];
		$super = ['WASTE_APPROVE', 'INVENTORY_ADJUST_APPROVE', 'ALERTS_MANAGE', 'WORKFORCE_ATTENDANCE_MANAGE'];
		$pagePerms = array_unique($pagePerms);
		if (array_intersect($pagePerms, $manager))
		{
			$roles[] = 'Gerente';
		}
		if (array_intersect($pagePerms, $super))
		{
			$roles[] = 'Supervisor';
		}
		if (array_diff($pagePerms, $manager, $super))
		{
			$roles[] = 'Cualquier rol con: ' . implode(' o ', array_diff($pagePerms, $manager, $super));
		}
		return $roles;
	}

	// ------------------------------------------------------------------ hallazgos automáticos

	private function CollectIssues(array $modules, array $assigned, array $connections): array
	{
		$out = [];
		$n = 0;
		foreach ($this->routes as $r)
		{
			if ($r['controller'] && !$r['handler_ok'])
			{
				$out[] = ['id' => 'a' . (++$n), 'severity' => 'P1', 'type' => 'Error de navegación', 'module' => $assigned[$r['path']] ?? 'arquitectura', 'confidence' => 'confirmed',
					'title' => "{$r['method']} {$r['path']} apunta a {$r['controller']}::{$r['action']}, que no existe" . ($r['views_missing'] ? ' o renderiza una vista inexistente' : ''),
					'evidence' => [['file' => 'routes.php', 'line' => $r['line']]], 'recommendation' => 'Crear el método/vista o quitar la ruta'];
			}
		}
		foreach ($modules as $m)
		{
			foreach ($m['broken_calls'] as $c)
			{
				$out[] = ['id' => 'a' . (++$n), 'severity' => 'P1', 'type' => 'API desconectada', 'module' => $m['id'], 'confidence' => $c['method_mismatch'] ? 'confirmed' : 'confirmed',
					'title' => ($c['method_mismatch'] ? "Método {$c['method']} no admitido en " : "Llamada a ruta inexistente ") . "/api{$c['path']}",
					'evidence' => [['file' => $c['file'], 'line' => $c['line'], 'snippet' => $c['raw']]], 'recommendation' => 'Corregir la URL/método o registrar el endpoint en routes.php'];
			}
			foreach ($m['suspect_buttons'] as $b)
			{
				$out[] = ['id' => 'a' . (++$n), 'severity' => 'P3', 'type' => 'Botón sin acción (sospecha)', 'module' => $m['id'], 'confidence' => 'suspected',
					'title' => "Botón «{$b['label']}» sin manejador detectable", 'evidence' => [['file' => $b['file'], 'line' => $b['line'], 'snippet' => $b['html']]],
					'recommendation' => 'Verificar en navegador; si no hace nada, conectar o quitar'];
			}
			foreach ($m['screens'] as $s)
			{
				if ($s['exists'] && $s['sweep'] && !$s['sweep']['ok'])
				{
					$out[] = ['id' => 'a' . (++$n), 'severity' => 'P1', 'type' => 'Pantalla con error', 'module' => $m['id'], 'confidence' => 'confirmed',
						'title' => "GET {$s['path']} falló en el barrido HTTP", 'evidence' => [['test' => 'tests/dairy/http_sweep.php', 'detail' => $s['sweep']['detail']]], 'recommendation' => 'Revisar el error mostrado'];
				}
			}
			foreach ($m['tests'] as $t)
			{
				if ($t['ok'] === false)
				{
					$out[] = ['id' => 'a' . (++$n), 'severity' => 'P1', 'type' => 'Prueba fallida', 'module' => $m['id'], 'confidence' => 'confirmed',
						'title' => "{$t['file']}: {$t['fails']} verificación(es) fallida(s)", 'evidence' => [['test' => 'tests/dairy/' . $t['file']]], 'recommendation' => 'Correr la prueba y corregir'];
				}
			}
			$linked = array_filter($connections, fn($c) => ($c['from'] === $m['id'] || $c['to'] === $m['id']) && $c['status'] === 'verified');
			if (!$m['in_nav'] && !$linked && !in_array($m['category'], ['heredado', 'admin', 'maestros'], true) && $m['id'] !== 'system_map')
			{
				$out[] = ['id' => 'a' . (++$n), 'severity' => 'P3', 'type' => 'Módulo aislado', 'module' => $m['id'], 'confidence' => 'confirmed',
					'title' => "{$m['name']} no está en el menú ni tiene conexiones verificadas", 'evidence' => [['file' => 'helpers/DairyNavigation.php', 'note' => 'ausente del menú lateral']], 'recommendation' => 'Enlazarlo desde el módulo que lo usa'];
			}
			if ($m['category'] === 'heredado' && !$m['in_nav'])
			{
				$out[] = ['id' => 'a' . (++$n), 'severity' => 'Info', 'type' => 'Módulo oculto', 'module' => $m['id'], 'confidence' => 'confirmed',
					'title' => "{$m['name']}: ruta activa de Grocy que no aparece en el menú DAIRY", 'evidence' => [['file' => 'routes.php', 'line' => $m['screens'][0]['line'] ?? null]],
					'recommendation' => 'Decidir: ocultar con feature flag de Grocy o integrarlo'];
			}
		}
		// APIs DAIRY sin consumidor en el frontend ni en pruebas
		// Agrupadas por módulo; las cubiertas por pruebas siguen siendo APIs sin pantalla (útiles para integraciones)
		$orphans = [];
		foreach ($this->apiRoutes as $a)
		{
			if (!in_array($a['controller'], self::CORE_CONTROLLERS, true) && empty($a['callers']))
			{
				$orphans[$this->ModuleOfApi($a, $modules)][] = $a;
			}
		}
		foreach ($orphans as $mod => $list)
		{
			$untested = array_filter($list, fn($a) => !$a['tested']);
			$out[] = ['id' => 'a' . (++$n), 'severity' => $untested ? 'P3' : 'Info', 'type' => 'API sin consumidor', 'module' => $mod, 'confidence' => 'confirmed',
				'title' => count($list) . ' endpoint(s) sin llamada desde la web ni la app móvil' . ($untested ? ' (' . count($untested) . ' sin prueba que los use)' : ' (todos usados por pruebas)'),
				'evidence' => array_map(fn($a) => ['file' => 'routes.php', 'line' => $a['line'], 'snippet' => "{$a['method']} {$a['path']}" . ($a['tested'] ? ' · probado' : '')], $list),
				'recommendation' => 'Conectarlos a la UI, documentarlos como API de integración o retirarlos'];
		}
		// Servicios sin uso
		foreach ($this->services as $name => $s)
		{
			if (!$s['used_by'] && !in_array($name, ['BaseService', 'DatabaseService', 'SystemMapService', 'DatabaseMigrationService', 'DemoDataGeneratorService', 'LocalizationService', 'SessionService', 'ApplicationService', 'ApiKeyService'], true))
			{
				$out[] = ['id' => 'a' . (++$n), 'severity' => 'P3', 'type' => 'Código sin uso', 'module' => 'arquitectura', 'confidence' => 'confirmed',
					'title' => "$name no es referenciado por ningún controlador, servicio, middleware ni helper", 'evidence' => [['file' => "services/$name.php", 'line' => 1]],
					'recommendation' => 'Eliminarlo o conectarlo (verificar antes que no lo use un plugin)'];
			}
		}
		// Duplicados: dos rutas web que renderizan la misma vista
		$byView = [];
		foreach ($this->routes as $r)
		{
			if ($r['group'] === 'web' && $r['method'] === 'GET')
			{
				foreach ($r['views'] as $v)
				{
					$byView[$v][] = $r['path'];
				}
			}
		}
		foreach ($byView as $v => $ps)
		{
			if (count($ps) > 1 && !preg_match('/form$|^(stockentryform|userobjectform)/', $v))
			{
				$out[] = ['id' => 'a' . (++$n), 'severity' => 'Info', 'type' => 'Pantalla duplicada', 'module' => $assigned[$ps[0]] ?? 'arquitectura', 'confidence' => 'confirmed',
					'title' => "La vista $v se sirve desde " . implode(', ', $ps), 'evidence' => [['file' => "views/$v.blade.php"]], 'recommendation' => 'Mantener una ruta canónica'];
			}
		}
		return $out;
	}

	private function ModuleOfApi(array $a, array $modules): string
	{
		$first = str_replace('-', '_', $a['segs'][0] ?? '');
		$alias = ['markdown' => 'quality', 'kpi' => 'kpis', 'ai' => 'ai_advisor', 'procurement' => 'suppliers', 'loss' => 'inventory_control', 'm' => 'mobile', 'budgets' => 'kpis',
			'simulator' => 'simulator', 'stock' => 'stockoverview', 'change_history' => 'data_import', 'workforce' => 'workforce', 'recalls' => 'recalls', 'audits' => 'audits'];
		$cand = isset($modules[$first]) ? $first : ($alias[$first] ?? null);
		if ($first === 'waste' && ($a['segs'][1] ?? '') === 'export')
		{
			$cand = 'waste';
		}
		if ($cand && isset($modules[$cand]))
		{
			return $cand;
		}
		foreach ($modules as $m)
		{
			foreach ($m['endpoints'] as $e)
			{
				if (explode('::', $e['handler'])[0] === $a['controller'])
				{
					return $m['id'];
				}
			}
		}
		return 'arquitectura';
	}

	// ------------------------------------------------------------------ parsers

	private function Read(string $rel): string
	{
		if (!array_key_exists($rel, $this->files))
		{
			$f = $this->root . '/' . $rel;
			$this->files[$rel] = is_file($f) ? (string) file_get_contents($f) : '';
		}
		return $this->files[$rel];
	}

	private function Line(string $text, int $offset): int
	{
		return substr_count(substr($text, 0, $offset), "\n") + 1;
	}

	// Primer match del patrón en el archivo → evidencia archivo:línea
	private function Find(string $rel, string $pattern): ?array
	{
		$t = $this->Read($rel);
		if ($t !== '' && preg_match('~' . str_replace('~', '\~', $pattern) . '~', $t, $m, PREG_OFFSET_CAPTURE))
		{
			$line = $this->Line($t, $m[0][1]);
			return ['file' => $rel, 'line' => $line, 'snippet' => trim(mb_substr(explode("\n", $t)[$line - 1] ?? '', 0, 180))];
		}
		return null;
	}

	private function LoadTables(): void
	{
		try
		{
			foreach ($this->getDatabaseService()->GetDbConnectionRaw()->query("SELECT name, type FROM sqlite_master WHERE type IN ('table', 'view') AND name NOT LIKE 'sqlite_%'")->fetchAll(\PDO::FETCH_ASSOC) as $r)
			{
				$this->tables[strtolower($r['name'])] = ['name' => $r['name'], 'type' => $r['type'], 'used_by' => []];
			}
		}
		catch (\Throwable $e)
		{
			$this->tables = [];
		}
	}

	private function getDatabaseService(): DatabaseService
	{
		return DatabaseService::GetInstance();
	}

	private function TablesIn(string $code): array
	{
		preg_match_all('/\b(?:FROM|JOIN|INTO|UPDATE|TABLE)\s+[`"]?([a-z_][a-z0-9_]*)/i', $code, $m);
		preg_match_all('/->getDatabase\(\)->([a-z_][a-z0-9_]*)\(|\$this->DB->([a-z_][a-z0-9_]*)\(/', $code, $m2);
		$names = array_map('strtolower', array_merge($m[1], $m2[1], $m2[2]));
		return array_values(array_unique(array_filter($names, fn($n) => isset($this->tables[$n]))));
	}

	private function Methods(string $text): array
	{
		$out = [];
		preg_match_all('/(?:public|protected|private)\s+(?:static\s+)?function\s+(\w+)\s*\(/', $text, $m, PREG_OFFSET_CAPTURE);
		$n = count($m[0]);
		for ($i = 0; $i < $n; $i++)
		{
			$start = $m[0][$i][1];
			$end = $i + 1 < $n ? $m[0][$i + 1][1] : strlen($text);
			$out[$m[1][$i][0]] = ['body' => substr($text, $start, $end - $start), 'line' => $this->Line($text, $start)];
		}
		return $out;
	}

	private function ServicesIn(string $code): array
	{
		preg_match_all('/\b([A-Z][A-Za-z]*Service)::GetInstance|\$this->get([A-Z][A-Za-z]*Service)\(/', $code, $m);
		return array_values(array_unique(array_filter(array_merge($m[1], $m[2]), fn($s) => $s !== '' && $s !== 'DatabaseService' && $s !== 'LocalizationService')));
	}

	private function ParseControllers(): void
	{
		foreach (array_merge(glob($this->root . '/controllers/*.php'), glob($this->root . '/controllers/Api/*.php')) as $f)
		{
			$rel = substr(str_replace('\\', '/', $f), strlen(str_replace('\\', '/', $this->root)) + 1);
			$class = basename($f, '.php');
			$text = $this->Read($rel);
			$this->controllers[$class] = $rel;
			foreach ($this->Methods($text) as $name => $mm)
			{
				preg_match_all("/RenderPage\(\s*\\\$response\s*,\s*'([^']+)'/", $mm['body'], $v);
				preg_match_all('/PERMISSION_([A-Z_]+)/', $mm['body'], $p);
				$this->methods["$class::$name"] = ['file' => $rel, 'line' => $mm['line'], 'views' => array_values(array_unique($v[1])), 'services' => $this->ServicesIn($mm['body']),
					'permissions' => array_values(array_unique($p[1])), 'tables' => []];
			}
		}
	}

	private function ParseRoutes(): void
	{
		$prefix = '';
		foreach (file($this->root . '/routes.php') as $i => $l)
		{
			if (str_contains($l, "\$app->group('/api'"))
			{
				$prefix = '/api';
			}
			if (!preg_match("/\\\$group->(get|post|put|delete|patch)\(\s*'([^']*)'\s*,\s*(?:\[\s*([A-Za-z]+)::class\s*,\s*'(\w+)'\s*\]|function)/i", $l, $m))
			{
				continue;
			}
			$controller = $m[3] ?? '';
			$key = $controller ? "$controller::{$m[4]}" : null;
			$meta = $key ? ($this->methods[$key] ?? null) : null;
			$viewsMissing = false;
			foreach ($meta['views'] ?? [] as $v)
			{
				if (!is_file($this->root . '/views/' . str_replace('.', '/', $v) . '.blade.php'))
				{
					$viewsMissing = true;
				}
			}
			$r = ['method' => strtoupper($m[1]), 'path' => ($prefix . $m[2]) ?: '/', 'group' => $prefix ? 'api' : 'web', 'controller' => $controller ?: null, 'action' => $m[4] ?? null,
				'line' => $i + 1, 'handler_ok' => !$controller || ($meta !== null && !$viewsMissing), 'views_missing' => $viewsMissing, 'views' => $meta['views'] ?? [], 'services' => $meta['services'] ?? [],
				'permissions' => $meta['permissions'] ?? [], 'file' => $meta['file'] ?? null, 'method_line' => $meta['line'] ?? null, 'callers' => [], 'tested' => false];
			$r['segs'] = explode('/', trim(substr($r['path'], strlen($prefix)), '/'));
			$this->routes[] = $r;
			if ($prefix)
			{
				$this->apiRoutes[count($this->routes) - 1] = &$this->routes[count($this->routes) - 1];
			}
		}
	}

	private function ParseServices(): void
	{
		$users = [];
		$scan = array_merge(glob($this->root . '/controllers/*.php'), glob($this->root . '/controllers/Api/*.php'), glob($this->root . '/services/*.php'), glob($this->root . '/middleware/*.php'), glob($this->root . '/helpers/*.php'), [$this->root . '/app.php', $this->root . '/routes.php']);
		$texts = [];
		foreach ($scan as $f)
		{
			$texts[basename($f, '.php')] = (string) file_get_contents($f);
		}
		foreach (glob($this->root . '/services/*.php') as $f)
		{
			$name = basename($f, '.php');
			$text = $texts[$name];
			preg_match_all('/\b([A-Z][A-Za-z]*Service)\b/', $text, $d);
			$deps = array_values(array_diff(array_unique($d[1]), [$name, 'BaseService', 'DatabaseService', 'LocalizationService']));
			$deps = array_values(array_filter($deps, fn($x) => is_file($this->root . "/services/$x.php")));
			$tables = $this->TablesIn($text);
			foreach ($tables as $t)
			{
				$this->tables[$t]['used_by'][] = $name;
			}
			$usedBy = [];
			foreach ($texts as $other => $ot)
			{
				if ($other !== $name && preg_match('/\b' . $name . '\b/', $ot))
				{
					$usedBy[] = $other;
				}
			}
			$this->services[$name] = ['name' => $name, 'file' => "services/$name.php", 'lines' => substr_count($text, "\n") + 1, 'deps' => $deps, 'tables' => $tables, 'used_by' => $usedBy,
				'methods' => count($this->Methods($text))];
		}
		foreach ($this->methods as $k => &$mm)
		{
			$mm['tables'] = $this->TablesIn($this->Methods($this->Read($mm['file']))[explode('::', $k)[1]]['body'] ?? '');
		}
	}

	private function LoadJsCorpus(): void
	{
		foreach (array_merge(glob($this->root . '/public/js/*.js'), glob($this->root . '/public/viewjs/*.js')) as $f)
		{
			$rel = 'public/' . basename(dirname($f)) . '/' . basename($f);
			$this->jsCorpus[$rel] = $this->Read($rel);
		}
		// Quién llama a cada API (todas las vistas + JS)
		$sources = array_keys($this->jsCorpus);
		foreach (array_merge(glob($this->root . '/views/*.blade.php'), glob($this->root . '/views/*/*.blade.php'), glob($this->root . '/views/*/*/*.blade.php')) as $f)
		{
			$sources[] = substr(str_replace('\\', '/', $f), strlen(str_replace('\\', '/', $this->root)) + 1);
		}
		foreach ($this->CallsIn($sources) as $c)
		{
			foreach ($c['routes'] as $ri)
			{
				$this->apiRoutes[$ri]['callers'][] = $c['file'] . ':' . $c['line'];
			}
		}
	}

	private array $callCache = [];

	private function CallsIn(array $files): array
	{
		$out = [];
		foreach ($files as $rel)
		{
			$this->callCache[$rel] ??= $this->ExtractCalls($this->Read($rel), $rel);
			$out = array_merge($out, $this->callCache[$rel]);
		}
		return $out;
	}

	/**
	 * Llamadas a la API en un texto JS/Blade. Reconstruye la URL concatenada: literales tal cual y expresiones como {x}.
	 */
	private function ExtractCalls(string $text, string $file): array
	{
		$patterns = [
			'/Grocy\.Api\.(Get|Post|Put|Delete)\(/' => null,
			'/\b(?:DairyUI|UI|U)\.(post|get)\(/' => null,
			'/DairyOffline\.send\(/' => 'POST',
			'/\bfetch\(/' => 'FETCH',
			'/\$\.getJSON\(/' => 'GETJ',
			'/\$\.ajax\(\s*\{[^}]*?\burl\s*:\s*/' => 'AJAX',
			"/\\\$U\\(\\s*(?=['\"]\\/api\\/)/" => '*',
		];
		if (in_array($file, self::API_HELPER_FILES, true))
		{
			$patterns['/(?<![\w.$])(get|post|put|del|send)\(\s*(?=[\'"`]\/)/'] = null;
		}
		// Ayudantes locales de cada vista (mrpPost, trPost, dairyPost, api…): solo cuentan si la ruta existe en la API
		$patterns['/(?<![\w.$])((?!(?:get|post|put|del|send|fetch|require|import|url|href|U|setTimeout|text|html|attr|val|find|on|replace|split|test|match|push|log|alert|confirm|data|prop|css|go|load|navigate)\()[a-zA-Z_]\w*)\(\s*(?=[\'"`](?:\/[a-z][\w-]*[\'"`\/?]|[a-z][\w-]*\/))/'] = 'LOCAL';
		$out = [];
		foreach ($patterns as $re => $fixed)
		{
			if (!preg_match_all($re, $text, $mm, PREG_OFFSET_CAPTURE | PREG_SET_ORDER))
			{
				continue;
			}
			foreach ($mm as $m)
			{
				$start = $m[0][1] + strlen($m[0][0]);
				$arg = $this->FirstArg($text, $start);
				$path = $this->BuildPath($arg);
				if ($path === null)
				{
					continue;
				}
				$method = $fixed ?? strtoupper($m[1][0] ?? 'GET');
				if ($fixed === 'LOCAL')
				{
					$fn = strtolower($m[1][0]);
					$method = match (true) {
						str_contains($fn, 'delete') => 'DELETE',
						str_contains($fn, 'put') => 'PUT',
						str_contains($fn, 'post') || str_contains($fn, 'save') || str_contains($fn, 'send') => 'POST',
						str_contains($fn, 'get') || str_contains($fn, 'load') || str_contains($fn, 'fetch') => 'GET',
						default => '*',
					};
				}
				if ($method === 'DEL')
				{
					$method = 'DELETE';
				}
				if ($method === 'SEND')
				{
					$method = 'POST';
				}
				if ($method === 'FETCH' || $method === 'AJAX')
				{
					$around = $method === 'AJAX' ? $m[0][0] . substr($text, $start + strlen($arg), 300) : substr($text, $start + strlen($arg), 300);
					$method = preg_match("/(?:method|type)\s*:\s*['\"](\w+)/i", $around, $mt) ? strtoupper($mt[1]) : 'GET';
				}
				if ($method === 'GETJ')
				{
					$method = 'GET';
				}
				$isApi = in_array($fixed, ['*', 'FETCH', 'GETJ', 'AJAX'], true) ? str_contains($arg, '/api') || preg_match('/^\s*API\b/', $arg) : true;
				if (in_array($fixed, ['FETCH', 'GETJ', 'AJAX'], true) && !$isApi)
				{
					continue;
				}
				[$routes, $mismatch] = $this->MatchApi($method, $path);
				if ($fixed === 'LOCAL' && !$routes)
				{
					// Ayudante local con una ruta que no es de la API (p. ej. navegación): no es una llamada
					continue;
				}
				$out[] =['file' => $file, 'line' => $this->Line($text, $m[0][1]), 'method' => $method, 'path' => $path, 'raw' => mb_substr(trim(preg_replace('/\s+/', ' ', $m[0][0] . $arg)), 0, 160),
					'routes' => $routes, 'method_mismatch' => $mismatch];
			}
		}
		return $out;
	}

	private function FirstArg(string $text, int $pos): string
	{
		$depth = 0;
		$quote = null;
		$len = strlen($text);
		for ($i = $pos; $i < $len && $i < $pos + 600; $i++)
		{
			$ch = $text[$i];
			if ($quote)
			{
				if ($ch === '\\')
				{
					$i++;
				}
				elseif ($ch === $quote)
				{
					$quote = null;
				}
				continue;
			}
			if ($ch === "'" || $ch === '"' || $ch === '`')
			{
				$quote = $ch;
			}
			elseif (str_contains('([{', $ch))
			{
				$depth++;
			}
			elseif (str_contains(')]}', $ch))
			{
				if ($depth === 0)
				{
					break;
				}
				$depth--;
			}
			elseif ($ch === ',' && $depth === 0)
			{
				break;
			}
		}
		return substr($text, $pos, $i - $pos);
	}

	// 'stock/products/' + id + '/consume' → /stock/products/{x}/consume (sin /api ni query)
	private function BuildPath(string $arg): ?string
	{
		$parts = [];
		$depth = 0;
		$quote = null;
		$cur = '';
		for ($i = 0, $n = strlen($arg); $i < $n; $i++)
		{
			$ch = $arg[$i];
			if ($quote)
			{
				$cur .= $ch;
				if ($ch === '\\')
				{
					$cur .= $arg[++$i] ?? '';
				}
				elseif ($ch === $quote)
				{
					$quote = null;
				}
				continue;
			}
			if ($ch === "'" || $ch === '"' || $ch === '`')
			{
				$quote = $ch;
			}
			elseif (str_contains('([{', $ch))
			{
				$depth++;
			}
			elseif (str_contains(')]}', $ch))
			{
				$depth--;
			}
			if ($ch === '+' && $depth === 0)
			{
				$parts[] = trim($cur);
				$cur = '';
				continue;
			}
			$cur .= $ch;
		}
		$parts[] = trim($cur);
		$path = '';
		$literal = false;
		foreach ($parts as $i => $p)
		{
			if (preg_match('/^([\'"`])(.*)\1$/s', $p, $m))
			{
				$s = $m[1] === '`' ? preg_replace('/\$\{[^}]*\}/', '{x}', $m[2]) : $m[2];
				$path .= $s;
				$literal = true;
			}
			elseif ($path === '' && preg_match('/^(Grocy\.BaseUrl|API|U\.base|C\.base|base|Grocy\.Api\.BaseUrl)$/', $p))
			{
				continue;
			}
			elseif (preg_match("/^\\\$U\\(\\s*'([^']*)'\\s*\\)$/", $p, $m))
			{
				$path .= $m[1];
				$literal = true;
			}
			else
			{
				$path .= '{x}';
			}
		}
		if (!$literal)
		{
			return null;
		}
		// {{ $U('/api/x') }} dentro de Blade y {x} de qs()
		$path = preg_replace('/\{\{.*?\}\}/', '{x}', $path);
		$path = explode('?', $path)[0];
		$path = explode('#', $path)[0];
		$path = preg_replace('~^(https?://[^/]+)?~', '', $path);
		$path = preg_replace('~^/?api(?=/|$|\{)~', '', $path);
		$path = '/' . ltrim($path, '/');
		if ($path === '/' || $path === '/{x}' || str_contains($path, ' '))
		{
			return null;
		}
		return rtrim($path, '/') ?: null;
	}

	private function MatchApi(string $method, string $path): array
	{
		$segs = explode('/', trim($path, '/'));
		$hits = [];
		$pathHits = [];
		foreach ($this->apiRoutes as $ri => $r)
		{
			if ($this->SegsMatch($segs, $r['segs']))
			{
				$pathHits[] = $ri;
				if ($method === '*' || $r['method'] === $method)
				{
					$hits[] = $ri;
				}
			}
		}
		return [$hits, !$hits && (bool) $pathHits];
	}

	private function SegsMatch(array $call, array $route): bool
	{
		$last = end($call) === '{x}';
		if (count($call) !== count($route) && !($last && count($route) > count($call)))
		{
			return false;
		}
		foreach ($call as $i => $c)
		{
			$r = $route[$i] ?? null;
			if ($r === null)
			{
				return false;
			}
			if ($c === '{x}' || str_starts_with($r, '{'))
			{
				continue;
			}
			if (str_contains($c, '{x}'))
			{
				if (!preg_match('~^' . str_replace('\{x\}', '.*', preg_quote($c, '~')) . '$~', $r))
				{
					return false;
				}
				continue;
			}
			if ($c !== $r)
			{
				return false;
			}
		}
		return true;
	}

	private function RouteFor(string $method, string $path, string $group): ?array
	{
		foreach ($this->routes as $r)
		{
			if ($r['group'] === $group && $r['method'] === $method && $r['path'] === $path)
			{
				return $r;
			}
		}
		return null;
	}

	/**
	 * Vista Blade + includes + JS de la vista: pestañas, botones, formularios, modales y llamadas a la API.
	 */
	private function ParseView(string $view): array
	{
		$out = ['tabs' => [], 'buttons' => [], 'forms' => [], 'modals' => [], 'calls' => [], 'suspects' => [], 'files' => []];
		$queue = [$view];
		$seen = [];
		$js = [];
		$bladeTexts = [];
		while ($queue && count($seen) < 25)
		{
			$v = array_shift($queue);
			if (isset($seen[$v]) || str_starts_with($v, 'layout.'))
			{
				continue;
			}
			$seen[$v] = true;
			$rel = 'views/' . str_replace('.', '/', $v) . '.blade.php';
			$t = $this->Read($rel);
			if ($t === '')
			{
				continue;
			}
			$out['files'][] = ['path' => $rel, 'line' => 1];
			$bladeTexts[$rel] = $t;
			preg_match_all("/@include(?:If|When)?\(\s*(?:[^,]*,\s*)?'([\w.]+)'/", $t, $inc);
			foreach ($inc[1] as $i)
			{
				if (!str_starts_with($i, 'components.dairy_kpi') && !str_starts_with($i, 'components.userfields'))
				{
					$queue[] = $i;
				}
			}
			preg_match_all('~viewjs/([\w-]+)\.js~', $t, $vj);
			foreach ($vj[1] as $x)
			{
				$js[] = "public/viewjs/$x.js";
			}
		}
		$main = "public/viewjs/$view.js";
		if (is_file($this->root . '/' . $main))
		{
			array_unshift($js, $main);
		}
		$js = array_values(array_unique($js));
		foreach ($js as $j)
		{
			$out['files'][] = ['path' => $j, 'line' => 1];
		}
		$ownJs = implode("\n", array_map(fn($j) => $this->Read($j), $js));
		$allBlade = implode("\n", $bladeTexts);
		$globalJs = $this->Read('public/js/dairy_ui.js') . $this->Read('public/js/grocy.js') . $this->Read('public/js/dairy_scan.js') . $this->Read('public/js/dairy_mobile.js') . $this->Read('public/js/extensions.js');

		foreach ($bladeTexts as $rel => $t)
		{
			$clean = str_replace(['->', '=>'], ['→', '⇒'], preg_replace('/\{\{--.*?--\}\}/s', '', $t));
			if (preg_match_all('/<a\b([^>]*\bdata-toggle="(?:tab|pill)"[^>]*)>(.*?)<\/a>/s', $clean, $mm, PREG_OFFSET_CAPTURE | PREG_SET_ORDER))
			{
				foreach ($mm as $m)
				{
					if (preg_match('/href="#([\w-]+)"/', $m[1][0], $h))
					{
						$out['tabs'][] = ['id' => $h[1], 'label' => $this->Text($m[2][0]) ?: $h[1], 'file' => $rel, 'line' => $this->Line($clean, $m[0][1])];
					}
				}
			}
			if (preg_match_all('/<button\b([^>]*)>(.*?)<\/button>/s', $clean, $mm, PREG_OFFSET_CAPTURE | PREG_SET_ORDER))
			{
				foreach ($mm as $m)
				{
					$attrs = $m[1][0];
					$label = $this->Text($m[2][0]) ?: $this->Attr($attrs, 'title') ?: $this->Attr($attrs, 'aria-label') ?: '';
					if (preg_match('/\bclose\b|data-dismiss="(modal|alert)"|navbar-toggler|dropdown-toggle/', $attrs) || $label === '')
					{
						continue;
					}
					$line = $this->Line($clean, $m[0][1]);
					$id = $this->Attr($attrs, 'id');
					$out['buttons'][] = ['key' => $label . '|' . $rel, 'label' => $label, 'id' => $id, 'kind' => $this->ButtonKind($attrs), 'file' => $rel, 'line' => $line];
					if ($this->LooksDead($attrs, $id, $ownJs . $allBlade, $globalJs))
					{
						$out['suspects'][] = ['label' => $label, 'file' => $rel, 'line' => $line, 'html' => mb_substr(trim(preg_replace('/\s+/', ' ', $m[0][0])), 0, 200)];
					}
				}
			}
			if (preg_match_all('/<form\b([^>]*)>/s', $clean, $mm, PREG_OFFSET_CAPTURE | PREG_SET_ORDER))
			{
				foreach ($mm as $m)
				{
					$id = $this->Attr($m[1][0], 'id') ?: '(sin id)';
					$out['forms'][] = ['key' => $id . '|' . $rel, 'id' => $id, 'method' => strtoupper($this->Attr($m[1][0], 'method') ?: 'JS'), 'file' => $rel, 'line' => $this->Line($clean, $m[0][1])];
				}
			}
			if (preg_match_all('/<div\b[^>]*class="modal\b[^"]*"[^>]*>/s', $clean, $mm, PREG_OFFSET_CAPTURE | PREG_SET_ORDER))
			{
				foreach ($mm as $m)
				{
					$id = $this->Attr($m[0][0], 'id') ?: 'modal';
					$title = preg_match('/class="modal-title[^"]*"[^>]*>(.*?)<\/h\d>/s', substr($clean, $m[0][1], 2500), $tt) ? $this->Text($tt[1]) : '';
					$out['modals'][] = ['id' => $id, 'title' => $title ?: $id, 'file' => $rel, 'line' => $this->Line($clean, $m[0][1])];
				}
			}
		}
		$out['calls'] = array_merge($this->CallsIn(array_keys($bladeTexts)), $this->CallsIn($js));
		return $out;
	}

	private function Text(string $html): string
	{
		$s = preg_replace(['/\{\{.*?\}\}/s', '/\{!!.*?!!\}/s', '/@\w+(\([^)]*\))?/'], ['…', '…', ''], $html);
		$s = trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($s))));
		$s = trim(preg_replace('/^(…\s*)+|(\s*…)+$/u', '', $s));
		return mb_substr($s, 0, 60);
	}

	private function Attr(string $attrs, string $name): ?string
	{
		return preg_match('/\b' . preg_quote($name, '/') . '="([^"]*)"/', $attrs, $m) ? $m[1] : null;
	}

	private function ButtonKind(string $attrs): string
	{
		return match (true) {
			str_contains($attrs, 'type="submit"') => 'submit',
			str_contains($attrs, 'data-toggle="modal"') || str_contains($attrs, 'data-target=') => 'abre modal',
			str_contains($attrs, 'onclick=') => 'onclick',
			default => 'js',
		};
	}

	// Sospecha: sin submit/onclick/data-*/name y ni su id ni sus clases propias aparecen en ningún JS de la vista o global
	private function LooksDead(string $attrs, ?string $id, string $own, string $global): bool
	{
		if (preg_match('/type="submit"|onclick=|data-[\w-]+=|\bname=|\bform=|@click|x-on|\{\{/', $attrs))
		{
			return false;
		}
		if (!str_contains($attrs, 'type="button"') && !str_contains($attrs, "type='button'"))
		{
			// <button> sin type dentro de un <form> envía el formulario
			return false;
		}
		if ($id && (substr_count($own, $id) > 1 || str_contains($global, $id)))
		{
			return false;
		}
		$classes = preg_split('/\s+/', (string) $this->Attr($attrs, 'class'), -1, PREG_SPLIT_NO_EMPTY);
		foreach ($classes as $c)
		{
			if (preg_match('/^(btn|m[trblxy]?-|p[trblxy]?-|d-|w-|h-|text-|float-|rounded|shadow|dairy-(fs|gap|w)|col|flex|align|justify|border|font|small|ml|mr)/', $c))
			{
				continue;
			}
			if (str_contains($own, $c) || str_contains($global, $c))
			{
				return false;
			}
		}
		return !$id || substr_count($own, $id) <= 1;
	}

	private function ParseNav(): array
	{
		// Menú lateral: configuración central (helpers/DairyNavigation.php). Los grupos con hijos (datos maestros) no son módulos.
		$out = [];
		foreach (\Grocy\Helpers\DairyNavigation::Categories() as $section)
		{
			foreach ($section['items'] as $item)
			{
				if (isset($item['children']))
				{
					continue;
				}
				$route = $item['route'];
				$id = str_contains($route, '#') ? 'simulator' : (trim(str_replace(['/', '-'], ['_', '_'], explode('#', $route)[0]), '_') ?: 'root');
				$cat = SystemMapCatalog::NAV_ITEM_CATEGORY[$item['id']] ?? SystemMapCatalog::NAV_SECTION[$section['id']] ?? 'admin';
				$out[] = ['id' => $id, 'category' => $cat, 'name' => $item['label'], 'route' => $route, 'description' => $item['desc'], 'icon' => $item['icon'], 'in_nav' => true, 'nav_section' => $section['label'],
					'nav_views' => $item['views'], 'extra_routes' => []];
			}
		}
		return $out;
	}

	private function ParseTests(array $evidence): array
	{
		$out = [];
		$results = $evidence['tests']['files'] ?? [];
		foreach (array_merge(glob($this->root . '/tests/dairy/test_*.php'), glob($this->root . '/tests/dairy/http_*.php'), [$this->root . '/tests/dairy/run.php']) as $f)
		{
			$name = basename($f);
			$t = (string) file_get_contents($f);
			preg_match_all('/\b([A-Z][A-Za-z]*Service)\b/', $t, $s);
			preg_match_all("~['\"/]api(/[a-z0-9_\-/]+)~", $t, $p);
			preg_match_all("~(?:get|post|api|req|call|http)\w*\(\s*['\"]/?([a-z][a-z0-9_\-]*/[a-z0-9_\-/]+)~i", $t, $p2);
			$paths = [];
			foreach (array_merge($p[1], array_map(fn($x) => '/' . $x, $p2[1])) as $x)
			{
				$x = rtrim($x, '/');
				foreach ($this->apiRoutes as $ri => $r)
				{
					if ($this->SegsMatch(explode('/', trim($x, '/')), $r['segs']))
					{
						$paths[] = $r['path'];
						$this->apiRoutes[$ri]['tested'] = true;
					}
				}
			}
			$res = $results[$name] ?? null;
			$out[$name] = ['services' => array_values(array_unique($s[1])), 'paths' => array_values(array_unique($paths)), 'ok' => $res['ok'] ?? null,
				'checks' => $res['checks'] ?? null, 'fails' => $res['fails'] ?? null, 'kind' => str_starts_with($name, 'http_') ? 'HTTP' : 'Unitaria'];
		}
		return $out;
	}

	private function TestsFor(array $a, array $tests): array
	{
		$out = [];
		foreach ($tests as $n => $t)
		{
			if (in_array($a['path'], $t['paths'], true))
			{
				$out[] = $n;
			}
		}
		return $out;
	}

	private function Uniq(array $rows, string $key): array
	{
		$out = [];
		foreach ($rows as $r)
		{
			$out[$r[$key]] ??= $r;
		}
		return array_values($out);
	}

	private function Architecture(array $tests): array
	{
		$layers = [];
		foreach (SystemMapCatalog::ARCHITECTURE as $id => [$name, $desc])
		{
			$layers[] = ['id' => $id, 'name' => $name, 'description' => $desc];
		}
		$controllers = [];
		foreach ($this->controllers as $c => $f)
		{
			$controllers[] = ['name' => $c, 'file' => $f, 'core' => in_array($c, self::CORE_CONTROLLERS, true), 'api' => str_contains($f, '/Api/'),
				'routes' => count(array_filter($this->routes, fn($r) => $r['controller'] === $c))];
		}
		$tables = array_values(array_map(fn($t) => ['name' => $t['name'], 'type' => $t['type'], 'used_by' => array_values(array_unique($t['used_by']))], $this->tables));
		usort($tables, fn($a, $b) => strcmp($a['name'], $b['name']));
		$testsOut = [];
		foreach ($tests as $n => $t)
		{
			$testsOut[] = ['file' => $n, 'kind' => $t['kind'], 'ok' => $t['ok'], 'checks' => $t['checks'], 'fails' => $t['fails'], 'services' => $t['services'], 'endpoints' => count($t['paths'])];
		}
		$perms = [];
		foreach ((new \ReflectionClass(\Grocy\Controllers\Users\User::class))->getConstants() as $k => $v)
		{
			if (str_starts_with($k, 'PERMISSION_'))
			{
				$perms[] = $v;
			}
		}
		return ['layers' => $layers, 'services' => array_values($this->services), 'controllers' => $controllers, 'tables' => $tables, 'tests' => $testsOut,
			'permissions' => $perms, 'pages_protected' => DairyAccess::PAGES];
	}
}
