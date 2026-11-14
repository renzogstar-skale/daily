<?php

namespace Grocy\Helpers;

use Grocy\Services\DailyMetricsService;
use Grocy\Services\DatabaseService;

/**
 * Filtros compartidos de los tableros DAILY (empresa, sucursal, departamento, categoría, subcategoría, producto/SKU y fechas).
 * Un solo nombre de parámetro por filtro en toda la aplicación; cada tablero declara en ROUTES cuáles aplica de verdad,
 * para que la barra no muestre como activo un filtro que el tablero ignora.
 *
 *  - Departamento (department): clave de DailyMetricsService::DEPARTMENTS, agrupa los valores de products.department.
 *  - Categoría (group_id): product_groups, el maestro que cada empresa configura en Datos maestros.
 *  - Subcategoría (subcategory): products.subcategory.
 *  - Producto (product): SKU exacto, código de barras exacto o parte del nombre.
 */
class DailyFilters
{
	const ALL = ['company_id', 'branch_id', 'department', 'group_id', 'subcategory', 'product', 'start_date', 'end_date'];

	// Ruta => filtros que el tablero aplica a sus cifras. HOY usa una sola fecha (?date=, toma end_date).
	const ROUTES = [
		'/sales' => self::ALL,
		'/inventory/stockouts' => ['company_id', 'branch_id', 'department', 'group_id', 'subcategory', 'product'],
		'/today' => ['branch_id', 'department', 'end_date'],
		'/kpis' => ['branch_id', 'start_date', 'end_date'],
		'/waste' => ['branch_id', 'start_date', 'end_date'],
		'/hr' => ['branch_id', 'department', 'start_date', 'end_date'],
		'/warehouse' => ['branch_id', 'department', 'start_date', 'end_date'],
		'/stockoverview' => ['branch_id', 'department', 'group_id', 'subcategory', 'product', 'start_date', 'end_date']
	];

	const LABELS = ['company_id' => 'Empresa', 'branch_id' => 'Sucursal', 'department' => 'Departamento', 'group_id' => 'Categoría',
		'subcategory' => 'Subcategoría', 'product' => 'Producto o SKU', 'start_date' => 'Desde', 'end_date' => 'Hasta'];

	private static function Db(): \PDO
	{
		return DatabaseService::GetInstance()->GetDbConnectionRaw();
	}

	/** Filtros normalizados desde la query; las fechas por defecto son los últimos 30 días hasta hoy */
	public static function FromQuery(array $q, int $defaultDays = 30): array
	{
		$isDate = fn($v) => is_string($v) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $v);
		$end = $isDate($q['end_date'] ?? null) ? $q['end_date'] : date('Y-m-d');
		$start = $isDate($q['start_date'] ?? null) ? $q['start_date'] : date('Y-m-d', strtotime($end . ' -' . ($defaultDays - 1) . ' days'));
		if ($start > $end)
		{
			[$start, $end] = [$end, $start];
		}
		$dept = strtolower(trim((string) ($q['department'] ?? '')));
		$branchId = !empty($q['branch_id']) ? intval($q['branch_id']) : null;
		if ($branchId !== null && !DairyAccess::CanSeeBranch($branchId))
		{
			$branchId = null;
		}
		return [
			'company_id' => !empty($q['company_id']) ? intval($q['company_id']) : null,
			'branch_id' => $branchId,
			'department' => isset(DailyMetricsService::Departments()[$dept]) ? $dept : null,
			'group_id' => isset($q['group_id']) && $q['group_id'] !== '' ? intval($q['group_id']) : null,
			'subcategory' => trim((string) ($q['subcategory'] ?? '')) ?: null,
			'product' => trim((string) ($q['product'] ?? '')) ?: null,
			'start_date' => $start,
			'end_date' => $end
		];
	}

	/** Claves de departamento para DailyMetricsService (null = todos) */
	public static function DeptKeys(array $f): ?array
	{
		return $f['department'] !== null ? [$f['department']] : null;
	}

	/** Condición SQL sobre products (alias p) con departamento, categoría, subcategoría y producto */
	public static function ProductSql(array $f, string $alias = 'p'): string
	{
		$db = self::Db();
		$parts = [];
		if ($f['department'] !== null)
		{
			$parts[] = str_replace('p.', $alias . '.', DailyMetricsService::GetInstance()->DeptSql([$f['department']]));
		}
		if ($f['group_id'] !== null)
		{
			$parts[] = $f['group_id'] === 0 ? "$alias.product_group_id IS NULL" : "$alias.product_group_id = " . intval($f['group_id']);
		}
		if ($f['subcategory'] !== null)
		{
			$parts[] = "$alias.subcategory = " . $db->quote($f['subcategory']);
		}
		if ($f['product'] !== null)
		{
			$t = $f['product'];
			$parts[] = "($alias.sku = " . $db->quote($t) . " OR $alias.id IN (SELECT product_id FROM product_barcodes WHERE barcode = " . $db->quote($t) . ")"
				. " OR $alias.name LIKE " . $db->quote('%' . $t . '%') . (ctype_digit($t) ? " OR $alias.id = " . intval($t) : '') . ')';
		}
		return $parts ? implode(' AND ', $parts) : '1 = 1';
	}

	/** Condición SQL sobre la sucursal (columna dada) combinando empresa, sucursal y sucursales permitidas al usuario */
	public static function BranchSql(array $f, string $col): string
	{
		$ids = self::BranchIds($f);
		return $ids === null ? '1 = 1' : "$col IN (" . implode(',', $ids ?: [0]) . ')';
	}

	/** Sucursales incluidas; null = toda la red sin restricción */
	public static function BranchIds(array $f): ?array
	{
		if ($f['branch_id'] !== null)
		{
			return [$f['branch_id']];
		}
		$allowed = DairyAccess::AllowedBranches();
		if ($f['company_id'] === null)
		{
			return $allowed;
		}
		$ids = array_map('intval', self::Db()->query('SELECT id FROM branches WHERE company_id = ' . intval($f['company_id']))->fetchAll(\PDO::FETCH_COLUMN));
		return $allowed === null ? $ids : array_values(array_intersect($ids, $allowed));
	}

	/** Opciones de cada selector, derivadas de los datos de la empresa (no de una lista fija) */
	public static function Options(array $f): array
	{
		$db = self::Db();
		$companies = $db->query('SELECT c.id, c.name FROM companies c WHERE c.active = 1 AND EXISTS (SELECT 1 FROM branches b WHERE b.company_id = c.id) ORDER BY c.name')->fetchAll(\PDO::FETCH_ASSOC);
		$branchWhere = $f['company_id'] !== null ? ' AND company_id = ' . intval($f['company_id']) : '';
		$branches = DairyAccess::FilterBranches($db->query("SELECT id, name, code FROM branches WHERE IFNULL(active, 1) = 1$branchWhere ORDER BY name")->fetchAll(\PDO::FETCH_ASSOC));

		// Solo departamentos con productos activos: cada cliente ve su propia clasificación
		$present = array_flip(array_filter($db->query('SELECT DISTINCT IFNULL(department, \'\') FROM products WHERE active = 1')->fetchAll(\PDO::FETCH_COLUMN)));
		$departments = [];
		foreach (DailyMetricsService::Departments() as $key => [$name, $color, $values])
		{
			if ($values === null || array_intersect_key($present, array_flip($values)))
			{
				$departments[] = ['key' => $key, 'name' => DailyMetricsService::DepartmentLabel($key)];
			}
		}

		$deptSql = $f['department'] !== null ? DailyMetricsService::GetInstance()->DeptSql([$f['department']]) : '1 = 1';
		$groups = $db->query("SELECT g.id, g.name, COUNT(*) AS n FROM product_groups g JOIN products p ON p.product_group_id = g.id WHERE p.active = 1 AND $deptSql GROUP BY g.id ORDER BY g.name")->fetchAll(\PDO::FETCH_ASSOC);
		foreach ($groups as &$g)
		{
			$g['name'] = trim(preg_replace('/^\d{2}\s+/', '', $g['name']));
		}
		unset($g);
		$subSql = $deptSql . ($f['group_id'] ? ' AND p.product_group_id = ' . intval($f['group_id']) : '');
		$subcategories = $db->query("SELECT DISTINCT p.subcategory FROM products p WHERE p.active = 1 AND IFNULL(p.subcategory, '') <> '' AND $subSql ORDER BY 1")->fetchAll(\PDO::FETCH_COLUMN);

		return ['companies' => $companies, 'branches' => $branches, 'departments' => $departments, 'groups' => $groups, 'subcategories' => $subcategories];
	}

	/** Nombre legible de cada filtro activo (para el encabezado «Período · Sucursal · Departamento») */
	public static function Describe(array $f, array $options): array
	{
		$find = function (array $rows, $id, string $key = 'id') {
			foreach ($rows as $r)
			{
				$r = (array) $r;
				if ((string) $r[$key] === (string) $id)
				{
					return $r['name'];
				}
			}
			return null;
		};
		return [
			'company' => $f['company_id'] !== null ? $find($options['companies'], $f['company_id']) : 'Todas',
			'branch' => $f['branch_id'] !== null ? ($find($options['branches'], $f['branch_id']) ?? 'Sucursal ' . $f['branch_id']) : 'Todas las sucursales',
			'department' => $f['department'] !== null ? DailyMetricsService::DepartmentLabel($f['department']) : 'Todos los departamentos',
			'group' => $f['group_id'] !== null ? ($find($options['groups'], $f['group_id']) ?? 'Sin categoría') : 'Todas las categorías',
			'subcategory' => $f['subcategory'] ?? 'Todas',
			'product' => $f['product'],
			'period' => self::PeriodLabel($f['start_date'], $f['end_date'])
		];
	}

	/** «1–4 de noviembre de 2026», «28 de octubre – 4 de noviembre de 2026» */
	public static function PeriodLabel(string $from, string $to): string
	{
		$m = ['', 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
		[$y1, $m1, $d1] = array_map('intval', explode('-', $from));
		[$y2, $m2, $d2] = array_map('intval', explode('-', $to));
		if ($from === $to)
		{
			return "$d2 de {$m[$m2]} de $y2";
		}
		if ($y1 === $y2 && $m1 === $m2)
		{
			return "{$d1}–{$d2} de {$m[$m2]} de {$y2}";
		}
		return "$d1 de {$m[$m1]}" . ($y1 !== $y2 ? " de $y1" : '') . " – $d2 de {$m[$m2]} de $y2";
	}

	/** Query string con los filtros activos que aplica una ruta (para enlaces de detalle) */
	public static function Query(array $f, string $route, array $extra = []): string
	{
		$keys = self::ROUTES[$route] ?? [];
		$out = [];
		foreach ($keys as $k)
		{
			if ($f[$k] !== null && $f[$k] !== '')
			{
				$out[$k] = $f[$k];
			}
		}
		return http_build_query(array_merge($out, $extra));
	}
}
