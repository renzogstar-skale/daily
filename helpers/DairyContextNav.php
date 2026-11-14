<?php

namespace Grocy\Helpers;

/**
 * Navegación contextual «Módulos conectados» al pie de cada pantalla DAIRY.
 * Única fuente: las conexiones operativas verificadas de SystemMapCatalog::CONNECTIONS (las mismas del Mapa del Sistema)
 * y los nombres/rutas del menú (DairyNavigation). No hay listas de enlaces escritas a mano por pantalla.
 */
class DairyContextNav
{
	// Terminales y formularios de captura: el pie con enlaces distrae de la operación
	// stockoverview dibuja su propio bloque «Módulos conectados» con el evento que pasa entre módulos
	const SKIP_VIEWS = ['pos', 'scan', 'waste_report', 'stockoverview'];

	// Id de módulo = ruta sin barras ni guiones (misma regla que SystemMapService::ParseNav)
	public static function ModuleId(string $route): string
	{
		return str_contains($route, '#') ? 'simulator' : (trim(str_replace(['/', '-'], ['_', '_'], explode('#', $route)[0]), '_') ?: 'root');
	}

	private static function Modules(): array
	{
		$mods = [];
		foreach (DairyNavigation::Categories() as $section)
		{
			foreach ($section['items'] as $item)
			{
				if (isset($item['children']) || $item['route'][0] !== '/')
				{
					continue;
				}
				$mods[self::ModuleId($item['route'])] = ['name' => $item['label'], 'route' => $item['route'], 'icon' => $item['icon'], 'views' => $item['views'], 'section' => $section['label']];
			}
		}
		foreach (SystemMapCatalog::EXTRA_MODULES as $id => $m)
		{
			if (!isset($mods[$id]) && !str_contains($m[2], '{'))
			{
				$mods[$id] = ['name' => $m[1], 'route' => $m[2], 'icon' => SystemMapCatalog::CATEGORIES[$m[0]][1] ?? 'fa-circle', 'views' => [], 'section' => ''];
			}
		}
		return $mods;
	}

	/**
	 * Para la vista actual: ['module' => nombre, 'in' => [...], 'out' => [...]]; cada enlace: name, route, icon, label.
	 * Solo módulos que el usuario puede abrir; null si la vista no es un módulo DAIRY del menú.
	 */
	public static function ForView(string $viewName): ?array
	{
		if (in_array($viewName, self::SKIP_VIEWS, true))
		{
			return null;
		}
		$mods = self::Modules();
		$current = null;
		foreach ($mods as $id => $m)
		{
			if (in_array($viewName, $m['views'], true))
			{
				$current = $id;
				break;
			}
		}
		if ($current === null)
		{
			return null;
		}
		$out = ['module' => $mods[$current]['name'], 'in' => [], 'out' => []];
		foreach (SystemMapCatalog::CONNECTIONS as [$from, $to, $label])
		{
			$dir = $from === $current ? 'out' : ($to === $current ? 'in' : null);
			$other = $dir === 'out' ? $to : $from;
			if ($dir === null || $other === $current || !isset($mods[$other]) || isset($out[$dir][$other]) || !DairyAccess::CanOpen($mods[$other]['route']))
			{
				continue;
			}
			$out[$dir][$other] = $mods[$other] + ['label' => $label];
		}
		$out['in'] = array_values($out['in']);
		$out['out'] = array_values($out['out']);
		return $out['in'] || $out['out'] ? $out : null;
	}
}
