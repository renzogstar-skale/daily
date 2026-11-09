<?php

namespace Grocy\Helpers;

use Grocy\Controllers\Users\User;

/**
 * Suscripciones DAILY: catálogo único de capacidades, planes con herencia acumulativa y mapa ruta → capacidad.
 * Permiso efectivo = capacidad del plan (aquí) ∧ rol (DairyAccess::PAGES / roles simulados) ∧ sucursales (DairyAccess::AllowedBranches).
 * El plan se resuelve por empresa (daily_subscriptions); las cuentas de evaluación pueden fijar su propio plan
 * (daily_subscription_user_overrides). El backend es la única fuente: DairyPermissionMiddleware bloquea páginas y APIs.
 */
class DailySubscription
{
	const PLANS = ['essential', 'professional', 'business', 'enterprise'];

	const PLAN_INFO = [
		'essential' => ['name' => 'Essential', 'icon' => 'fa-seedling', 'es' => 'Operación diaria de tienda: ventas, inventario y mermas.', 'en' => 'Daily store operation: sales, inventory and shrink.'],
		'professional' => ['name' => 'Professional', 'icon' => 'fa-layer-group', 'es' => 'Control de mermas, personal y red de sucursales.', 'en' => 'Shrink control, workforce and branch network.'],
		'business' => ['name' => 'Business', 'icon' => 'fa-building', 'es' => 'Logística, abastecimiento y conciliación.', 'en' => 'Logistics, procurement and reconciliation.'],
		'enterprise' => ['name' => 'Enterprise', 'icon' => 'fa-shield-halved', 'es' => 'Prevención de pérdidas, IMEI y gobierno corporativo.', 'en' => 'Loss prevention, IMEI and corporate governance.'],
	];

	// Identificadores estables: no renombrar (se guardan en historial y los usa el front-end)
	const CAPABILITIES = [
		// ESSENTIAL
		'sales.view' => ['plan' => 'essential', 'group' => 'sales', 'es' => 'Tablero e historial de ventas, clientes', 'en' => 'Sales dashboard, history and customers'],
		'pos.access' => ['plan' => 'essential', 'group' => 'sales', 'es' => 'Punto de venta (POS)', 'en' => 'Point of sale (POS)'],
		'pos.returns' => ['plan' => 'essential', 'group' => 'sales', 'es' => 'Devoluciones del POS', 'en' => 'POS returns'],
		'inventory.view' => ['plan' => 'essential', 'group' => 'inventory', 'es' => 'Stock, agotados y control de inventario', 'en' => 'Stock, stockouts and inventory control'],
		'inventory.kardex' => ['plan' => 'essential', 'group' => 'inventory', 'es' => 'Kardex valorizado y trazabilidad de lotes', 'en' => 'Valued kardex and lot traceability'],
		'inventory.count' => ['plan' => 'essential', 'group' => 'inventory', 'es' => 'Conteos físicos y cíclicos', 'en' => 'Physical and cycle counts'],
		'scanner.access' => ['plan' => 'essential', 'group' => 'inventory', 'es' => 'Escáner UPC', 'en' => 'UPC scanner'],
		'mobile.access' => ['plan' => 'essential', 'group' => 'mobile', 'es' => 'Aplicación móvil', 'en' => 'Mobile app'],
		'waste.register' => ['plan' => 'essential', 'group' => 'waste', 'es' => 'Registro e historial de mermas', 'en' => 'Shrink registration and history'],
		'today.view' => ['plan' => 'essential', 'group' => 'reports', 'es' => 'Panel HOY', 'en' => 'TODAY panel'],
		'reports.basic' => ['plan' => 'essential', 'group' => 'reports', 'es' => 'Reportes básicos', 'en' => 'Basic reports'],
		'alerts.view' => ['plan' => 'essential', 'group' => 'reports', 'es' => 'Centro de alertas', 'en' => 'Alert center'],
		'workforce.tasks' => ['plan' => 'essential', 'group' => 'workforce', 'es' => 'Tareas operativas en piso', 'en' => 'Floor tasks'],
		'i18n.languages' => ['plan' => 'essential', 'group' => 'platform', 'es' => 'Idioma español e inglés', 'en' => 'Spanish and English'],
		// PROFESSIONAL
		'waste.approve' => ['plan' => 'professional', 'group' => 'waste', 'es' => 'Aprobación de mermas y matriz de causales', 'en' => 'Shrink approvals and reason matrix'],
		'waste.investigate' => ['plan' => 'professional', 'group' => 'waste', 'es' => 'Investigación de diferencias y expedientes de merma', 'en' => 'Variance investigation and shrink cases'],
		'workforce.schedule' => ['plan' => 'professional', 'group' => 'workforce', 'es' => 'Turnos, cobertura y recursos humanos', 'en' => 'Shifts, coverage and HR'],
		'workforce.attendance' => ['plan' => 'professional', 'group' => 'workforce', 'es' => 'Asistencia y fichajes', 'en' => 'Attendance and clock-ins'],
		'branches.manage' => ['plan' => 'professional', 'group' => 'network', 'es' => 'Gestión multisucursal', 'en' => 'Multi-branch management'],
		'transfers.manage' => ['plan' => 'professional', 'group' => 'network', 'es' => 'Traslados entre tiendas', 'en' => 'Store transfers'],
		'reports.executive' => ['plan' => 'professional', 'group' => 'reports', 'es' => 'Panel ejecutivo de red', 'en' => 'Network executive panel'],
		// BUSINESS
		'dispatch.manage' => ['plan' => 'business', 'group' => 'logistics', 'es' => 'Pedidos, picking y despachos', 'en' => 'Orders, picking and dispatches'],
		'logistics.returns' => ['plan' => 'business', 'group' => 'logistics', 'es' => 'Devoluciones logísticas y retiros de producto', 'en' => 'Logistics returns and product recalls'],
		'planning.mrp' => ['plan' => 'business', 'group' => 'procurement', 'es' => 'Planeación MRP', 'en' => 'MRP planning'],
		'procurement.manage' => ['plan' => 'business', 'group' => 'procurement', 'es' => 'Compras y proveedores', 'en' => 'Purchasing and suppliers'],
		'reconciliation.manage' => ['plan' => 'business', 'group' => 'inventory', 'es' => 'Conciliación e inventario sensible', 'en' => 'Reconciliation and sensitive inventory'],
		'warehouse.locations' => ['plan' => 'business', 'group' => 'inventory', 'es' => 'Almacén (WMS) y mapa de ubicaciones', 'en' => 'Warehouse (WMS) and location map'],
		'finance.cost_centers' => ['plan' => 'business', 'group' => 'reports', 'es' => 'Centros de costo y presupuestos', 'en' => 'Cost centers and budgets'],
		'alerts.automation' => ['plan' => 'business', 'group' => 'reports', 'es' => 'Automatización, flujos y escalamientos', 'en' => 'Automation, workflows and escalations'],
		// ENTERPRISE
		'loss_prevention.access' => ['plan' => 'enterprise', 'group' => 'loss', 'es' => 'Prevención de pérdidas: investigaciones avanzadas, activos y evidencias', 'en' => 'Loss prevention: advanced investigations, assets and evidence'],
		'loss_prevention.audit' => ['plan' => 'enterprise', 'group' => 'loss', 'es' => 'Auditorías de prevención', 'en' => 'Prevention audits'],
		'devices.imei' => ['plan' => 'enterprise', 'group' => 'loss', 'es' => 'Control de celulares e IMEI', 'en' => 'Phone and IMEI control'],
		'branches.roles' => ['plan' => 'enterprise', 'group' => 'governance', 'es' => 'Roles y accesos por sucursal', 'en' => 'Per-branch roles and access'],
		'audit.view' => ['plan' => 'enterprise', 'group' => 'governance', 'es' => 'Registro de auditoría', 'en' => 'Audit log'],
	];

	const GROUPS = [
		'sales' => ['es' => 'Ventas y POS', 'en' => 'Sales and POS'],
		'inventory' => ['es' => 'Inventario', 'en' => 'Inventory'],
		'mobile' => ['es' => 'Movilidad', 'en' => 'Mobility'],
		'waste' => ['es' => 'Mermas', 'en' => 'Shrink'],
		'reports' => ['es' => 'Paneles y reportes', 'en' => 'Panels and reports'],
		'platform' => ['es' => 'Plataforma', 'en' => 'Platform'],
		'workforce' => ['es' => 'Personal', 'en' => 'Workforce'],
		'network' => ['es' => 'Red de sucursales', 'en' => 'Branch network'],
		'logistics' => ['es' => 'Logística', 'en' => 'Logistics'],
		'procurement' => ['es' => 'Planeación y compras', 'en' => 'Planning and purchasing'],
		'loss' => ['es' => 'Prevención de pérdidas', 'en' => 'Loss prevention'],
		'governance' => ['es' => 'Gobierno y auditoría', 'en' => 'Governance and audit'],
	];

	/**
	 * Patrón de ruta (Slim) → capacidad. Gana el prefijo más largo, por segmentos completos ('/pos' cubre '/pos/hold'
	 * pero no '/possible'). Un prefijo "MÉTODO /ruta" aplica solo a ese verbo. Las rutas sin regla son de plataforma
	 * (inicio de sesión, ajustes, usuarios, datos maestros, suscripción) y no dependen del plan.
	 */
	const ROUTES = [
		// Essential
		'/today' => 'today.view', '/api/today' => 'today.view',
		'/reports' => 'reports.basic', '/api/reports' => 'reports.basic',
		'/sales' => 'sales.view', '/api/sales' => 'sales.view', '/customers' => 'sales.view', '/api/customers' => 'sales.view', '/api/m/sales' => 'sales.view',
		'/pos' => 'pos.access', '/api/pos' => 'pos.access',
		'/pos/returns' => 'pos.returns', '/api/pos/returns' => 'pos.returns',
		'/stockoverview' => 'inventory.view', '/api/stockoverview' => 'inventory.view', '/inventory/stockouts' => 'inventory.view',
		'/inventory-control' => 'inventory.view', '/api/inventory-control' => 'inventory.view', '/api/retail/expiry' => 'inventory.view', '/api/retail/bakery' => 'inventory.view',
		'/kardex' => 'inventory.kardex', '/api/inventory-movements' => 'inventory.kardex', '/traceability' => 'inventory.kardex',
		'/inventory' => 'inventory.count', '/api/inventory' => 'inventory.count', '/api/stock/cycle-counts' => 'inventory.count', '/api/inventory-control/counts' => 'inventory.count', '/api/m/inventory' => 'inventory.count', '/api/m/ops/count' => 'inventory.count',
		'/scan' => 'scanner.access', '/api/scan' => 'scanner.access', '/api/m/scan' => 'scanner.access',
		'/m' => 'mobile.access', '/api/m' => 'mobile.access',
		'/waste' => 'waste.register', '/api/waste' => 'waste.register', '/api/loss' => 'waste.register', '/api/markdown' => 'waste.register',
		'/api/m/waste' => 'waste.register', '/api/m/ops/waste' => 'waste.register', '/api/m/ops/waste-reasons' => 'waste.register', '/api/workforce/quick-waste' => 'waste.register',
		'/alerts' => 'alerts.view', '/api/alerts' => 'alerts.view', '/api/m/alerts' => 'alerts.view',
		// Professional
		'/waste/approvals' => 'waste.approve', '/waste/reasons' => 'waste.approve', '/api/m/approvals' => 'waste.approve',
		'/api/waste/{wasteId}/approve' => 'waste.approve', '/api/waste/{wasteId}/reject' => 'waste.approve', '/api/waste/{wasteId}/request-info' => 'waste.approve',
		'POST /api/waste/reasons' => 'waste.approve', '/api/waste/reasons/{reasonId}' => 'waste.approve',
		'/api/waste/cases' => 'waste.investigate', '/api/waste/action-plans' => 'waste.investigate', '/api/waste/supplier-returns' => 'waste.investigate',
		'/api/loss/census/admin-errors' => 'waste.investigate', '/api/loss/price-issues' => 'waste.investigate',
		'/operations/scheduling' => 'workforce.schedule', '/schedule' => 'workforce.schedule', '/api/schedule' => 'workforce.schedule', '/hr' => 'workforce.schedule', '/api/hr' => 'workforce.schedule',
		'/api/workforce/peaks' => 'workforce.schedule', '/api/m/schedule' => 'workforce.schedule', '/api/m/open-shifts' => 'workforce.schedule', '/api/m/open-shift-claims' => 'workforce.schedule', '/api/m/shifts' => 'workforce.schedule', '/api/m/team' => 'workforce.schedule',
		'/operations/attendance' => 'workforce.attendance', '/attendance' => 'workforce.attendance', '/api/attendance' => 'workforce.attendance',
		'/api/schedule/attendance' => 'workforce.attendance', '/api/schedule/clock-in' => 'workforce.attendance', '/api/schedule/clock-out' => 'workforce.attendance', '/api/m/geofence' => 'workforce.attendance',
		'/workforce' => 'workforce.tasks', '/api/workforce' => 'workforce.tasks', '/api/m/tasks' => 'workforce.tasks',
		'/branches' => 'branches.manage', '/branch' => 'branches.manage', '/api/branches' => 'branches.manage',
		'/transfers' => 'transfers.manage', '/api/transfers' => 'transfers.manage', '/transfer' => 'transfers.manage',
		'/api/stock/products/{productId}/transfer' => 'transfers.manage', '/api/stock/products/by-barcode/{barcode}/transfer' => 'transfers.manage',
		'/kpis' => 'reports.executive', '/api/kpi' => 'reports.executive', '/api/kpi-catalog' => 'reports.executive',
		// Business
		'/orders' => 'dispatch.manage', '/api/orders' => 'dispatch.manage', '/dispatch' => 'dispatch.manage', '/dispatches' => 'dispatch.manage', '/api/retail/dispatch' => 'dispatch.manage',
		'/returns' => 'logistics.returns', '/api/retail/returns' => 'logistics.returns', '/recalls' => 'logistics.returns', '/api/recalls' => 'logistics.returns',
		'/mrp' => 'planning.mrp', '/api/mrp' => 'planning.mrp',
		'/suppliers' => 'procurement.manage', '/api/suppliers' => 'procurement.manage', '/api/procurement' => 'procurement.manage', '/purchase' => 'procurement.manage',
		'/reconciliation' => 'reconciliation.manage', '/api/reconciliation' => 'reconciliation.manage', '/traceability-reconciliation' => 'reconciliation.manage',
		'/warehouse' => 'warehouse.locations', '/api/warehouse' => 'warehouse.locations', '/locations/map' => 'warehouse.locations', '/merchandise' => 'warehouse.locations', '/api/merchandise' => 'warehouse.locations',
		'/cost-centers' => 'finance.cost_centers', '/api/budgets' => 'finance.cost_centers',
		'/api/alerts/automation' => 'alerts.automation', '/api/alerts/escalations/run' => 'alerts.automation', '/api/workflows' => 'alerts.automation',
		'/action-center' => 'alerts.automation', '/api/action-center' => 'alerts.automation', '/process-flow' => 'alerts.automation', '/api/process-flow' => 'alerts.automation',
		// Enterprise
		'/loss-prevention' => 'loss_prevention.access', '/api/loss-control' => 'loss_prevention.access', '/reports/loss-executive' => 'loss_prevention.access', '/api/reports/loss-executive' => 'loss_prevention.access',
		'/api/loss/theft' => 'loss_prevention.access', '/api/loss/census/theft' => 'loss_prevention.access', '/api/alerts/prevention' => 'loss_prevention.access',
		'/audits' => 'loss_prevention.audit', '/api/audits' => 'loss_prevention.audit',
		'/loss-prevention/devices' => 'devices.imei', '/loss-prevention/counts' => 'devices.imei', '/api/devices' => 'devices.imei',
		'/api/branches/user-access' => 'branches.roles',
		'/audit-log' => 'audit.view', '/api/audit-log' => 'audit.view',
	];

	/**
	 * Herramientas dentro de tableros compartidos: selectores que se retiran (CSS en el primer pintado y DOM al cargar)
	 * cuando falta la capacidad. Sus APIs ya están protegidas por ROUTES; esto evita botones y pestañas que fallarían.
	 */
	const UI = [
		'waste.investigate' => ['#tab-recuperacion-link', '#tab-recuperacion', '#tab-investigaciones-link', '#tab-investigaciones', '#tab-errores-link', '#tab-errores', '[onclick*="tab-recuperacion-link"]', '[onclick*="tab-investigaciones-link"]', '[data-daily-cap="waste.investigate"]'],
		'waste.approve' => ['a[href$="/waste/approvals"]', 'a[href$="/waste/reasons"]', '[data-daily-cap="waste.approve"]'],
		'loss_prevention.access' => ['#tab-robo-link', '#tab-robo', 'a[href*="/loss-prevention"]:not([href*="/devices"]):not([href*="/counts"])', '[data-daily-cap="loss_prevention.access"]'],
		'loss_prevention.audit' => ['#tab-smart-audits-link', '#tab-smart-audits', 'a[href$="/audits"]', '[data-daily-cap="loss_prevention.audit"]'],
		'devices.imei' => ['a[href*="/loss-prevention/devices"]', 'a[href*="/loss-prevention/counts"]', '[data-daily-cap="devices.imei"]'],
		'workforce.schedule' => ['[data-daily-cap="workforce.schedule"]'],
		'transfers.manage' => ['a[href$="/transfers"]', '[data-daily-cap="transfers.manage"]'],
		'branches.manage' => ['[data-daily-cap="branches.manage"]'],
		'dispatch.manage' => ['[data-daily-cap="dispatch.manage"]'],
		'procurement.manage' => ['[data-daily-cap="procurement.manage"]'],
		'planning.mrp' => ['[data-daily-cap="planning.mrp"]'],
		'audit.view' => ['a[href$="/audit-log"]', '[data-daily-cap="audit.view"]'],
		'branches.roles' => ['[data-daily-cap="branches.roles"]'],
	];

	// Escrituras que siguen permitidas con la suscripción inactiva (para poder renovar o salir)
	const INACTIVE_WRITE_ALLOWED = ['/api/subscription', '/login', '/logout', '/api/user/settings', '/api/system', '/daily/set-role', '/daily/set-plan'];

	private static array $cache = [];
	private static ?array $routeIndex = null;

	public static function Reset(): void
	{
		self::$cache = [];
	}

	public static function L(array $entry): string
	{
		return DailyI18n::IsEnglish() ? ($entry['en'] ?? $entry['es']) : $entry['es'];
	}

	public static function PlanRank(string $plan): int
	{
		$i = array_search($plan, self::PLANS, true);
		return $i === false ? -1 : $i;
	}

	public static function PlanName(string $plan): string
	{
		return self::PLAN_INFO[$plan]['name'] ?? ucfirst($plan);
	}

	// Herencia acumulativa: un plan incluye toda capacidad cuyo plan mínimo tenga rango menor o igual
	public static function CapabilitiesFor(string $plan): array
	{
		$rank = self::PlanRank($plan);
		return array_keys(array_filter(self::CAPABILITIES, fn ($c) => self::PlanRank($c['plan']) <= $rank));
	}

	// Capacidades que el plan agrega sobre el anterior
	public static function AddedIn(string $plan): array
	{
		return array_keys(array_filter(self::CAPABILITIES, fn ($c) => $c['plan'] === $plan));
	}

	public static function RequiredPlan(string $capability): string
	{
		return self::CAPABILITIES[$capability]['plan'] ?? 'enterprise';
	}

	public static function CapabilityForRoute(string $pattern, string $method = 'GET'): ?string
	{
		$pattern = explode('#', explode('?', $pattern)[0])[0];
		if (self::$routeIndex === null)
		{
			self::$routeIndex = self::ROUTES;
			uksort(self::$routeIndex, fn ($a, $b) => strlen($b) <=> strlen($a));
		}
		$method = strtoupper($method);
		foreach (self::$routeIndex as $rule => $cap)
		{
			$prefix = $rule;
			if (str_contains($rule, ' '))
			{
				[$verb, $prefix] = explode(' ', $rule, 2);
				if ($verb !== $method)
				{
					continue;
				}
			}
			if ($pattern === $prefix || str_starts_with($pattern, $prefix . '/'))
			{
				return $cap;
			}
		}
		return null;
	}

	private static function Db(): \PDO
	{
		return \Grocy\Services\DatabaseService::GetInstance()->GetDbConnectionRaw();
	}

	// Empresa del usuario: la de su primera sucursal asignada; si no tiene, la empresa principal
	public static function CompanyId(?int $userId = null): int
	{
		$userId ??= defined('GROCY_USER_ID') ? GROCY_USER_ID : 0;
		$key = 'company_' . $userId;
		if (!isset(self::$cache[$key]))
		{
			$cid = 0;
			try
			{
				$stmt = self::Db()->prepare('SELECT b.company_id FROM user_branches ub JOIN branches b ON b.id = ub.branch_id WHERE ub.user_id = :u AND b.company_id IS NOT NULL ORDER BY ub.branch_id LIMIT 1');
				$stmt->execute([':u' => $userId]);
				$cid = intval($stmt->fetchColumn());
				if ($cid <= 0)
				{
					$cid = intval(self::Db()->query('SELECT MIN(id) FROM companies WHERE active = 1')->fetchColumn());
				}
			}
			catch (\Throwable $e)
			{
			}
			self::$cache[$key] = $cid > 0 ? $cid : 1;
		}
		return self::$cache[$key];
	}

	/**
	 * Suscripción efectiva del usuario actual. Sin fila (instalaciones previas) = Enterprise activa: implantar el
	 * sistema no quita funciones a nadie. Una cuenta de evaluación con override usa su propio plan.
	 */
	public static function Current(?int $userId = null): array
	{
		$userId ??= defined('GROCY_USER_ID') ? GROCY_USER_ID : 0;
		$key = 'sub_' . $userId;
		if (isset(self::$cache[$key]))
		{
			return self::$cache[$key];
		}
		$companyId = self::CompanyId($userId);
		$sub = ['company_id' => $companyId, 'plan' => 'enterprise', 'status' => 'active', 'activated_at' => null, 'renews_at' => null, 'expires_at' => null, 'scope' => 'default', 'notes' => null];
		try
		{
			$stmt = self::Db()->prepare('SELECT * FROM daily_subscriptions WHERE company_id = :c');
			$stmt->execute([':c' => $companyId]);
			if ($row = $stmt->fetch(\PDO::FETCH_ASSOC))
			{
				$sub = array_merge($sub, $row, ['scope' => 'company']);
			}
			$stmt = self::Db()->prepare('SELECT plan, reason FROM daily_subscription_user_overrides WHERE user_id = :u');
			$stmt->execute([':u' => $userId]);
			if ($row = $stmt->fetch(\PDO::FETCH_ASSOC))
			{
				$sub['plan'] = $row['plan'];
				$sub['scope'] = 'user';
				$sub['notes'] = $row['reason'];
			}
		}
		catch (\Throwable $e)
		{
			// Tablas aún no migradas: comportamiento previo (todo disponible)
		}
		if (self::PlanRank($sub['plan']) < 0)
		{
			$sub['plan'] = 'essential';
		}
		$sub['contracted_plan'] = $sub['plan'];
		// Vista previa de un plan inferior (selector del encabezado): solo puede reducir, nunca ampliar
		$preview = $_COOKIE['daily_simulated_plan'] ?? null;
		if ($preview && self::PlanRank($preview) >= 0 && self::PlanRank($preview) < self::PlanRank($sub['plan']))
		{
			$sub['plan'] = $preview;
			$sub['preview'] = true;
		}
		$sub['active'] = in_array($sub['status'], ['active', 'trial'], true) && (empty($sub['expires_at']) || substr($sub['expires_at'], 0, 10) >= date('Y-m-d'));
		$sub['capabilities'] = self::CapabilitiesFor($sub['plan']);
		return self::$cache[$key] = $sub;
	}

	public static function Plan(): string
	{
		return self::Current()['plan'];
	}

	public static function Has(string $capability): bool
	{
		if (!defined('GROCY_USER_ID'))
		{
			return true;
		}
		return in_array($capability, self::Current()['capabilities'], true);
	}

	public static function IsActive(): bool
	{
		return !defined('GROCY_USER_ID') || self::Current()['active'];
	}

	// Capacidad que falta para abrir la ruta (o null si el plan la incluye)
	public static function MissingForRoute(string $pattern, string $method = 'GET'): ?string
	{
		$cap = self::CapabilityForRoute($pattern, $method);
		return ($cap !== null && !self::Has($cap)) ? $cap : null;
	}

	public static function WriteBlockedByStatus(string $pattern, string $method): bool
	{
		if (in_array(strtoupper($method), ['GET', 'HEAD', 'OPTIONS'], true) || self::IsActive())
		{
			return false;
		}
		foreach (self::INACTIVE_WRITE_ALLOWED as $prefix)
		{
			if ($pattern === $prefix || str_starts_with($pattern, $prefix . '/'))
			{
				return false;
			}
		}
		return true;
	}

	// CSS que oculta, antes del primer pintado, las herramientas internas no contratadas
	public static function LockedSelectors(): array
	{
		$out = [];
		foreach (self::UI as $cap => $selectors)
		{
			if (!self::Has($cap))
			{
				$out = array_merge($out, $selectors);
			}
		}
		return array_values(array_unique($out));
	}

	// Datos para el front-end (sin información sensible)
	public static function ClientState(): array
	{
		$sub = self::Current();
		return [
			'plan' => $sub['plan'],
			'planName' => self::PlanName($sub['plan']),
			'contractedPlan' => $sub['contracted_plan'],
			'preview' => !empty($sub['preview']),
			'active' => $sub['active'],
			'status' => $sub['status'],
			'capabilities' => $sub['capabilities'],
			'lockedSelectors' => self::LockedSelectors(),
		];
	}

	public static function LockInfo(string $capability): array
	{
		$required = self::RequiredPlan($capability);
		return [
			'capability' => $capability,
			'capability_label' => self::L(self::CAPABILITIES[$capability] ?? ['es' => $capability]),
			'required_plan' => $required,
			'required_plan_name' => self::PlanName($required),
			'current_plan' => self::Plan(),
			'current_plan_name' => self::PlanName(self::Plan()),
		];
	}

	// Matriz completa para el panel y la documentación
	public static function Matrix(): array
	{
		$rows = [];
		foreach (self::CAPABILITIES as $id => $c)
		{
			$row = ['id' => $id, 'label' => self::L($c), 'group' => self::L(self::GROUPS[$c['group']]), 'plan' => $c['plan']];
			foreach (self::PLANS as $p)
			{
				$row[$p] = self::PlanRank($c['plan']) <= self::PlanRank($p);
			}
			$rows[] = $row;
		}
		return $rows;
	}
}
