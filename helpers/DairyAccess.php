<?php

namespace Grocy\Helpers;

use Grocy\Controllers\Users\User;

/**
 * Acceso a las páginas DAIRY por permiso (basta uno de la lista; ADMIN siempre puede).
 * Lo usan DairyPermissionMiddleware (bloquea con 403) y el sidebar (oculta lo que el usuario no puede abrir).
 * Las acciones de cada página se validan además en su API.
 */
class DairyAccess
{
	const PAGES = [
		'/today' => [User::PERMISSION_KPIS_VIEW, User::PERMISSION_WASTE_APPROVE, User::PERMISSION_PROCUREMENT_MANAGE],
		'/kpis' => [User::PERMISSION_KPIS_VIEW],
		'/reports' => [User::PERMISSION_KPIS_VIEW],
		'/reports/loss-executive' => [User::PERMISSION_LOSS_PREVENTION, User::PERMISSION_KPIS_VIEW],
		'/cost-centers' => [User::PERMISSION_KPIS_VIEW],
		'/waste' => [User::PERMISSION_WASTE_REPORT, User::PERMISSION_WASTE_APPROVE],
		'/waste/tab/{tab}' => [User::PERMISSION_WASTE_REPORT, User::PERMISSION_WASTE_APPROVE],
		'/waste/report' => [User::PERMISSION_WASTE_REPORT],
		'/waste/approvals' => [User::PERMISSION_WASTE_APPROVE],
		'/waste/reasons' => [User::PERMISSION_WASTE_REASONS_EDIT],
		'/pos' => [User::PERMISSION_SALES_POS],
		'/sales' => [User::PERMISSION_SALES_VIEW],
		'/customers' => [User::PERMISSION_SALES_VIEW, User::PERMISSION_SALES_POS],
		'/branches' => [User::PERMISSION_BRANCH_MANAGE, User::PERMISSION_KPIS_VIEW],
		'/branch/{branchId}/stock' => [User::PERMISSION_BRANCH_MANAGE, User::PERMISSION_KPIS_VIEW],
		'/suppliers' => [User::PERMISSION_PROCUREMENT_MANAGE],
		'/workforce' => [User::PERMISSION_TASKS],
		'/hr' => [User::PERMISSION_WORKFORCE_SCHEDULE_MANAGE, User::PERMISSION_WORKFORCE_ATTENDANCE_MANAGE, User::PERMISSION_TASKS, User::PERMISSION_KPIS_VIEW],
		'/hr/payroll/export' => [User::PERMISSION_WORKFORCE_SCHEDULE_MANAGE, User::PERMISSION_KPIS_VIEW],
		'/operations/scheduling' => [User::PERMISSION_WORKFORCE_SCHEDULE_MANAGE, User::PERMISSION_TASKS, User::PERMISSION_KPIS_VIEW],
		'/operations/attendance' => [User::PERMISSION_WORKFORCE_ATTENDANCE_MANAGE, User::PERMISSION_TASKS, User::PERMISSION_KPIS_VIEW],
		'/users' => [User::PERMISSION_ADMIN, User::PERMISSION_USERS, User::PERMISSION_USERS_READ],
		'/mrp' => [User::PERMISSION_PROCUREMENT_MANAGE, User::PERMISSION_PROCUREMENT_RECEIVE],
		'/transfers' => [User::PERMISSION_STOCK_TRANSFER],
		'/kardex' => [User::PERMISSION_STOCK],
		'/stockoverview' => [User::PERMISSION_STOCK],
		'/inventory' => [User::PERMISSION_STOCK_INVENTORY],
		'/inventory-control' => [User::PERMISSION_STOCK],
		'/inventory/stockouts' => [User::PERMISSION_STOCK],
		'/merchandise' => [User::PERMISSION_STOCK, User::PERMISSION_LOSS_PREVENTION, User::PERMISSION_KPIS_VIEW],
		'/warehouse' => [User::PERMISSION_STOCK, User::PERMISSION_LOSS_PREVENTION, User::PERMISSION_KPIS_VIEW],
		'/loss-prevention' => [User::PERMISSION_LOSS_PREVENTION, User::PERMISSION_ALERTS_SUPERVISE, User::PERMISSION_KPIS_VIEW],
		'/loss-prevention/case/{id}' => [User::PERMISSION_LOSS_PREVENTION, User::PERMISSION_ALERTS_SUPERVISE],
		'/loss-prevention/devices' => [User::PERMISSION_SCAN_OPERATE, User::PERMISSION_STOCK_INVENTORY, User::PERMISSION_LOSS_PREVENTION],
		'/loss-prevention/devices/{id}' => [User::PERMISSION_SCAN_OPERATE, User::PERMISSION_STOCK_INVENTORY, User::PERMISSION_LOSS_PREVENTION],
		'/loss-prevention/counts' => [User::PERMISSION_SCAN_OPERATE, User::PERMISSION_STOCK_INVENTORY, User::PERMISSION_LOSS_PREVENTION],
		'/scan' => [User::PERMISSION_SCAN_OPERATE],
		'/data-import' => [User::PERMISSION_DATA_IMPORT],
		'/locations/labels' => [User::PERMISSION_STOCK],
		'/locations/map' => [User::PERMISSION_STOCK],
		'/traceability' => [User::PERMISSION_STOCK],
		'/orders' => [User::PERMISSION_ORDERS_MANAGE, User::PERMISSION_ORDERS_PICK],
		'/dispatches' => [User::PERMISSION_ORDERS_MANAGE, User::PERMISSION_ORDERS_PICK, User::PERMISSION_STOCK],
		'/dispatch' => [User::PERMISSION_ORDERS_MANAGE, User::PERMISSION_ORDERS_PICK, User::PERMISSION_STOCK],
		'/returns' => [User::PERMISSION_STOCK, User::PERMISSION_WASTE_REPORT, User::PERMISSION_SALES_VIEW],
		'/pos/returns' => [User::PERMISSION_SALES_POS, User::PERMISSION_SALES_VIEW, User::PERMISSION_STOCK, User::PERMISSION_WASTE_REPORT],
		'/recalls' => [User::PERMISSION_RECALL_MANAGE, User::PERMISSION_QUALITY_QUARANTINE],
		'/audits' => [User::PERMISSION_AUDIT_PERFORM],
		'/system-map' => [User::PERMISSION_ADMIN],
		'/settings' => [User::PERMISSION_ADMIN],
		'/audit-log' => [User::PERMISSION_ADMIN],
		'/api/system-map' => [User::PERMISSION_ADMIN],
		'/api/system-map/refresh' => [User::PERMISSION_ADMIN],
		'/api/system-map/export/{format}' => [User::PERMISSION_ADMIN],
		'/admin/economics' => [User::PERMISSION_ADMIN],
		'/api/admin/economics/simulate' => [User::PERMISSION_ADMIN],
		'/api/admin/economics/expense' => [User::PERMISSION_ADMIN],
		'/alerts' => [User::PERMISSION_ALERTS_MANAGE, User::PERMISSION_ALERTS_SUPERVISE, User::PERMISSION_KPIS_VIEW, User::PERMISSION_WASTE_APPROVE, User::PERMISSION_STOCK_INVENTORY],
		'/action-center' => [User::PERMISSION_ADMIN, User::PERMISSION_ALERTS_SUPERVISE, User::PERMISSION_KPIS_VIEW],
	];

	// Permiso para ver cada tipo de documento imprimible
	const DOCUMENTS = [
		'order' => [User::PERMISSION_PROCUREMENT_MANAGE, User::PERMISSION_PROCUREMENT_RECEIVE],
		'receipt' => [User::PERMISSION_PROCUREMENT_MANAGE, User::PERMISSION_PROCUREMENT_RECEIVE],
		'transfer' => [User::PERMISSION_STOCK_TRANSFER, User::PERMISSION_STOCK],
		'waste' => [User::PERMISSION_WASTE_REPORT, User::PERMISSION_WASTE_APPROVE],
		'adjustment' => [User::PERMISSION_STOCK_INVENTORY, User::PERMISSION_INVENTORY_ADJUST_APPROVE],
		'quarantine' => [User::PERMISSION_QUALITY_INSPECT, User::PERMISSION_QUALITY_QUARANTINE],
		'supplier_return' => [User::PERMISSION_WASTE_APPROVE, User::PERMISSION_PROCUREMENT_MANAGE],
		'invoice' => [User::PERMISSION_PROCUREMENT_MANAGE, User::PERMISSION_PROCUREMENT_RECEIVE],
	];

	// Rol "demo" (demostración comercial controlada): solo estas páginas web; el resto se oculta y redirige a /sales
	const DEMO_PAGES = [
		'/', '/sales', '/pos', '/pos/returns', '/customers', '/orders',
		'/today', '/kpis',
		'/waste', '/waste/tab/{tab}', '/waste/report',
		'/stockoverview', '/inventory/stockouts', '/kardex', '/traceability', '/warehouse',
		'/operations/scheduling', '/schedule', '/hr', '/hr/payroll/export',
		'/login', '/logout', '/daily/set-role'
	];

	private static ?User $user = null;
	private static array $cache = [];

	public static function HasAny(array $permissions): bool
	{
		if (!defined('GROCY_USER_ID'))
		{
			return false;
		}
		self::$user ??= new User();
		foreach ($permissions as $p)
		{
			self::$cache[$p] ??= self::$user->HasPermission($p);
			if (self::$cache[$p])
			{
				return true;
			}
		}
		return false;
	}

	public static function GetSimulatedRole(): string
	{
		if (isset($_COOKIE['daily_simulated_role']) && in_array($_COOKIE['daily_simulated_role'], ['empleado', 'supervisor', 'manager', 'demo'], true))
		{
			return $_COOKIE['daily_simulated_role'];
		}
		if (isset($_GET['simulated_role']) && in_array($_GET['simulated_role'], ['empleado', 'supervisor', 'manager', 'demo'], true))
		{
			return $_GET['simulated_role'];
		}
		return 'manager';
	}

	// Ruta sin parámetros (como en el sidebar) o patrón de ruta; las no listadas son libres
	public static function CanOpen(string $path): bool
	{
		$path = explode('#', explode('?', $path)[0])[0];

		$simRole = self::GetSimulatedRole();
		if ($simRole === 'demo' && !in_array($path, self::DEMO_PAGES, true))
		{
			return false;
		}
		if ($simRole === 'empleado')
		{
			$employeePages = [
				'/attendance', '/operations/attendance',
				'/schedule', '/operations/scheduling',
				'/pos', '/workforce', '/loss-prevention/counts', '/loss-prevention/devices',
				'/about', '/usersettings',
				'/login', '/logout', '/daily/set-role'
			];
			if (!in_array($path, $employeePages, true))
			{
				return false;
			}
		}
		elseif ($simRole === 'supervisor')
		{
			$restrictedForSupervisor = ['/system-map', '/admin/economics', '/data-import', '/users', '/branches', '/cost-centers'];
			if (in_array($path, $restrictedForSupervisor, true))
			{
				return false;
			}
		}

		return !isset(self::PAGES[$path]) || self::HasAny(self::PAGES[$path]);
	}

	/**
	 * Sucursales que el usuario puede ver; null = toda la red (ADMIN o sin asignación en user_branches).
	 */
	public static function AllowedBranches(): ?array
	{
		if (array_key_exists('__branches', self::$cache))
		{
			return self::$cache['__branches'];
		}
		$simRole = self::GetSimulatedRole();
		if ($simRole === 'supervisor' || $simRole === 'empleado')
		{
			return self::$cache['__branches'] = [1];
		}
		$allowed = null;
		if (defined('GROCY_USER_ID') && !self::HasAny([User::PERMISSION_ADMIN]))
		{
			try
			{
				$stmt = \Grocy\Services\DatabaseService::GetInstance()->GetDbConnectionRaw()->prepare('SELECT branch_id FROM user_branches WHERE user_id = :u ORDER BY branch_id');
				$stmt->execute([':u' => GROCY_USER_ID]);
				$ids = array_map('intval', $stmt->fetchAll(\PDO::FETCH_COLUMN));
				$allowed = count($ids) > 0 ? $ids : null;
			}
			catch (\Throwable $e)
			{
				$allowed = null; // tabla aún no migrada
			}
		}
		return self::$cache['__branches'] = $allowed;
	}

	public static function CanSeeBranch(?int $branchId): bool
	{
		$allowed = self::AllowedBranches();
		return $allowed === null || ($branchId !== null && in_array($branchId, $allowed, true));
	}

	// Filtra filas (arrays u objetos) con 'id' o 'branch_id' a las sucursales permitidas
	public static function FilterBranches($rows, string $key = 'auto')
	{
		$allowed = self::AllowedBranches();
		if ($allowed === null || !is_iterable($rows))
		{
			return $rows;
		}
		$out = [];
		foreach ($rows as $r)
		{
			$v = is_array($r) ? ($r[$key === 'auto' ? (array_key_exists('branch_id', $r) ? 'branch_id' : 'id') : $key] ?? null) : ($r->{$key === 'auto' ? (property_exists($r, 'branch_id') ? 'branch_id' : 'id') : $key} ?? null);
			if ($v !== null && in_array(intval($v), $allowed, true))
			{
				$out[] = $r;
			}
		}
		return $out;
	}

	// Página web (no API) fuera del guion de la demo comercial
	public static function IsHiddenInDemo(string $pattern): bool
	{
		return self::GetSimulatedRole() === 'demo' && !str_starts_with($pattern, '/api/') && !in_array($pattern, self::DEMO_PAGES, true);
	}

	public static function MissingFor(string $pattern): ?string
	{
		if (isset(self::PAGES[$pattern]) && !self::HasAny(self::PAGES[$pattern]))
		{
			return self::PAGES[$pattern][0];
		}
		return null;
	}
}
