<?php

namespace Grocy\Helpers;

/**
 * Configuración centralizada del sidebar DAILY: categorías → herramientas.
 * Cada herramienta: id, etiqueta, icono (Font Awesome), ruta, vistas que la marcan activa y descripción.
 * Los permisos por rol se aplican con DairyAccess::CanOpen(ruta); las categorías vacías se ocultan.
 * Orden (revisión 2026-11): Supervisión y Control, luego los módulos por prioridad retail (Ventas, Inventario, Despachos,
 * Mermas y Prevención, Operaciones, Administración, Planeación) e Indicadores Financieros. 'hidden' = fuera del menú,
 * la ruta sigue activa (p. ej. pestañas internas de /waste y /loss-prevention, accesibles dentro de su tablero).
 */
class DairyNavigation
{
	public static function Categories(): array
	{
		return [
			// SUPERVISIÓN Y CONTROL
			['id' => 'inicio', 'label' => 'SUPERVISIÓN Y CONTROL', 'icon' => 'fa-house', 'items' => [
				['id' => 'today', 'label' => 'HOY · Operación del Día', 'icon' => 'fa-list-check', 'route' => '/today', 'views' => ['today'], 'desc' => 'Resumen operativo del día y decisiones urgentes'],
				['id' => 'alerts', 'label' => 'Centro de Alertas', 'icon' => 'fa-bell', 'route' => '/alerts', 'views' => ['alert_center'], 'desc' => 'Alertas accionables con responsable, plazo y cierre aprobado', 'badge' => 'alerts'],
				['id' => 'action-center', 'label' => 'DAILY Action Center', 'icon' => 'fa-bolt-lightning', 'route' => '/action-center', 'views' => ['daily_action_center', 'process_flow'], 'desc' => 'Centro de intervenciones inteligentes, excepciones y aprobaciones multinivel', 'hidden' => true],
				['id' => 'kpis', 'label' => 'Panel Ejecutivo', 'icon' => 'fa-chart-line', 'route' => '/kpis', 'views' => ['executive_dashboard'], 'desc' => 'Tablero ejecutivo de rentabilidad, margen y KPIs estratégicos'],
			]],

			// 1. VENTAS Y RENTABILIDAD
			['id' => 'ventas', 'label' => '1. VENTAS Y RENTABILIDAD', 'icon' => 'fa-cash-register', 'items' => [
				['id' => 'sales', 'label' => '1. Tablero de Ventas', 'icon' => 'fa-chart-column', 'route' => '/sales', 'views' => ['sales_history'], 'desc' => 'Dashboard comercial: curva de ventas, márgenes, sucursales y análisis por departamento'],
				['id' => 'sales-history', 'label' => '2. Historial de Ventas', 'icon' => 'fa-list-ul', 'route' => '/sales?tab=detalle', 'views' => [], 'desc' => 'Transacciones, devoluciones y detalle por SKU del período'],
				['id' => 'pos', 'label' => '3. Punto de Venta (POS)', 'icon' => 'fa-cash-register', 'route' => '/pos', 'views' => ['pos'], 'desc' => 'Terminal POS con rotación y salida FEFO'],
				['id' => 'pos-returns', 'label' => '4. Devoluciones del POS', 'icon' => 'fa-arrow-rotate-left', 'route' => '/pos/returns', 'views' => ['pos_returns'], 'desc' => 'Búsqueda de ticket, validación, inspección física y reembolsos autorizados'],
				['id' => 'customers', 'label' => '5. Gestión de Clientes', 'icon' => 'fa-users', 'route' => '/customers', 'views' => ['customers', 'customer_profile'], 'desc' => 'CRM de clientes: ficha única, historial, segmentos, retención, solicitudes y fidelización'],
			]],

			// 2. INVENTARIO Y ALMACENES
			['id' => 'inventario', 'label' => '2. INVENTARIO Y ALMACENES', 'icon' => 'fa-boxes-stacked', 'items' => [
				['id' => 'stock', 'label' => '1. Stock y Movimientos', 'icon' => 'fa-cubes', 'route' => '/stockoverview', 'views' => ['stockoverview', 'stockentries', 'stockjournal', 'transfer', 'stockentryform', 'locationcontentsheet'], 'desc' => 'Existencias por almacén, lotes y bitácora de movimientos'],
				['id' => 'stockouts', 'label' => '2. Productos Agotados', 'icon' => 'fa-box-open', 'route' => '/inventory/stockouts', 'views' => ['inventory_stockouts'], 'desc' => 'Búsqueda por SKU: agotado, stock registrado y disponible, último movimiento y reposición'],
				['id' => 'inventory-control', 'label' => '3. Control de Inventario', 'icon' => 'fa-magnifying-glass-chart', 'route' => '/inventory-control', 'views' => ['inventory_control'], 'desc' => 'Centro analítico: stock fantasma, matriz ABC-XYZ y cobertura'],
				['id' => 'kardex', 'label' => '4. Kardex Valorizado', 'icon' => 'fa-book-open', 'route' => '/kardex', 'views' => ['kardex'], 'desc' => 'Movimientos valorizados por producto, lote y sucursal'],
				['id' => 'inventory', 'label' => '5. Conteos Físicos', 'icon' => 'fa-clipboard-list', 'route' => '/inventory', 'views' => ['inventory'], 'desc' => 'Inventario físico, conteos cíclicos y conciliación de ajustes'],
				['id' => 'reconciliation', 'label' => '6. Conciliación e Inventario Sensible', 'icon' => 'fa-arrows-rotate', 'route' => '/reconciliation', 'views' => ['reconciliation'], 'desc' => 'Trazabilidad 360°, inventario sensible, conciliación de 5 flujos y alertas'],
				['id' => 'transfers', 'label' => '7. Traslados entre Tiendas', 'icon' => 'fa-right-left', 'route' => '/transfers', 'views' => ['transfers'], 'desc' => 'Envío y recepción con verificación entre sucursales'],
				['id' => 'warehouse', 'label' => '8. Almacén y Bodega (WMS)', 'icon' => 'fa-warehouse', 'route' => '/warehouse', 'views' => ['warehouse_dashboard', 'merchandise'], 'desc' => 'Recepción, inspección, muelles, putaway en racks, picking y devoluciones'],
				['id' => 'scan', 'label' => '9. Operaciones con Escáner', 'icon' => 'fa-barcode', 'route' => '/scan', 'views' => ['scan'], 'desc' => 'Conteo guiado, movimientos, recepción y picking por escáner láser'],
				['id' => 'location-map', 'label' => '10. Mapa de Ubicaciones', 'icon' => 'fa-map-location-dot', 'route' => '/locations/map', 'views' => ['location_map'], 'desc' => 'Ocupación, vencimientos y valor por almacén, zona y pasillo'],
				['id' => 'branches', 'label' => '11. Sucursales y Almacenes', 'icon' => 'fa-building', 'route' => '/branches', 'views' => ['branches', 'branch_stock'], 'desc' => 'Estructura multi-sucursal y stock consolidado de la red'],
				['id' => 'traceability', 'label' => '12. Trazabilidad de Lotes', 'icon' => 'fa-route', 'route' => '/traceability', 'views' => ['traceability'], 'desc' => 'Seguimiento 360° de lotes: proveedor, traslados, mermas y ventas'],
			]],

			// 3. DESPACHOS Y DEVOLUCIONES
			['id' => 'despachos', 'label' => '3. DESPACHOS Y DEVOLUCIONES', 'icon' => 'fa-truck-ramp-box', 'items' => [
				['id' => 'orders', 'label' => '1. Pedidos y Entregas', 'icon' => 'fa-boxes-packing', 'route' => '/orders', 'views' => ['orders'], 'desc' => 'Pedidos de clientes: reserva, picking, empaque, entrega y despacho'],
				['id' => 'dispatch_picking', 'label' => '2. Picking y Packing', 'icon' => 'fa-boxes-packing', 'route' => '/dispatch#tab-picking', 'views' => ['orders'], 'desc' => 'Preparación por escáner láser, validación FEFO y empaque en bultos'],
				['id' => 'dispatches', 'label' => '3. Gestión de Despachos', 'icon' => 'fa-truck-ramp-box', 'route' => '/dispatches', 'views' => ['dispatch', 'dispatches'], 'desc' => 'OMS/WMS: Pedidos, preparación, programación de rutas, salida, seguimiento y conciliación'],
				['id' => 'returns', 'label' => '4. Devoluciones e Incidencias', 'icon' => 'fa-arrow-rotate-left', 'route' => '/returns', 'views' => ['returns'], 'desc' => 'Inspección, clasificación sanitaria en 7 destinos y control de mermas'],
				['id' => 'recalls', 'label' => '5. Retiros de Producto', 'icon' => 'fa-rotate-left', 'route' => '/recalls', 'views' => ['recalls'], 'desc' => 'Gestión y confirmación de retiros masivos en red de tiendas'],
			]],

			// 4. MERMAS Y PREVENCIÓN (las pestañas internas de /waste y /loss-prevention siguen disponibles dentro de cada tablero)
			['id' => 'mermas', 'label' => '4. MERMAS Y PREVENCIÓN', 'icon' => 'fa-trash-can', 'items' => [
				['id' => 'waste', 'label' => '1. Cuadro de Control de Mermas', 'icon' => 'fa-trash-can', 'route' => '/waste', 'views' => ['waste_overview'], 'desc' => 'Resumen ejecutivo de pérdidas, causas y oportunidades'],
				['id' => 'waste-report', 'label' => '2. Registro de Merma', 'icon' => 'fa-file-circle-plus', 'route' => '/waste/report', 'views' => ['waste_report'], 'desc' => 'Registro de merma con fotografía y evidencia'],
				['id' => 'waste-approvals', 'label' => '3. Aprobaciones de Merma', 'icon' => 'fa-clipboard-check', 'route' => '/waste/approvals', 'views' => ['waste_approvals'], 'desc' => 'Flujo y cola de aprobación de mermas pendientes'],
				['id' => 'waste-reasons', 'label' => '4. Matriz de Causales', 'icon' => 'fa-diagram-project', 'route' => '/waste/reasons', 'views' => ['waste_reasons'], 'desc' => 'Catálogo AKCAL de causas, protocolos y niveles de autorización'],
				['id' => 'loss-control', 'label' => '5. Prevención de Pérdidas', 'icon' => 'fa-gauge-high', 'route' => '/loss-prevention', 'views' => ['loss_prevention'], 'desc' => 'Incidentes, activos, celulares, investigaciones y resolución'],
				['id' => 'devices', 'label' => '6. Control de Celulares', 'icon' => 'fa-mobile-screen-button', 'route' => '/loss-prevention/devices', 'views' => ['devices', 'device_detail'], 'desc' => 'Trazabilidad individual por IMEI, custodia y estado'],
				['id' => 'loss-kpi', 'label' => '7. Indicadores de Prevención', 'icon' => 'fa-chart-pie', 'route' => '/reports/loss-executive', 'views' => ['loss_executive_report'], 'desc' => 'Analítica ejecutiva de mermas y prevención'],
				['id' => 'waste-investigations', 'label' => '5. Investigaciones y Casos de Merma', 'icon' => 'fa-magnifying-glass', 'route' => '/waste#tab-investigaciones', 'views' => [], 'hidden' => true, 'desc' => 'Expedientes de investigación por merma crítica'],
				['id' => 'waste-recovery', 'label' => '6. Recuperación Financiera', 'icon' => 'fa-hand-holding-dollar', 'route' => '/waste#tab-recuperacion', 'views' => [], 'hidden' => true, 'desc' => 'Gestión de reclamos a proveedores y recuperaciones'],
				['id' => 'loss-investigations', 'label' => '2. Investigaciones', 'icon' => 'fa-magnifying-glass', 'route' => '/loss-prevention#tab-expedientes', 'views' => ['loss_case', 'loss_counts'], 'hidden' => true, 'desc' => 'Expedientes activos, evidencias y trazabilidad'],
				['id' => 'merchandise-assets', 'label' => '4. Control de Activos', 'icon' => 'fa-laptop-file', 'route' => '/loss-prevention#tab-activos', 'views' => [], 'hidden' => true, 'desc' => 'Control integral de activos, equipos y mercancía sensible'],
				['id' => 'loss-access', 'label' => '5. Accesos y Autorizaciones', 'icon' => 'fa-key', 'route' => '/loss-prevention#tab-accesos', 'views' => [], 'hidden' => true, 'desc' => 'Custodia, roles autorizados y doble aprobación'],
				['id' => 'loss-cctv', 'label' => '6. CCTV y Evidencias', 'icon' => 'fa-video', 'route' => '/loss-prevention#tab-cctv', 'views' => [], 'hidden' => true, 'desc' => 'Cámaras autorizadas, referencias de video y bitácora'],
				['id' => 'loss-rounds', 'label' => '7. Rondas y Recorridos', 'icon' => 'fa-person-walking', 'route' => '/loss-prevention#tab-rondas', 'views' => [], 'hidden' => true, 'desc' => 'Check-ins en piso y rondas de seguridad'],
				['id' => 'loss-plans', 'label' => '8. Planes Preventivos', 'icon' => 'fa-bullseye', 'route' => '/loss-prevention#tab-planes', 'views' => [], 'hidden' => true, 'desc' => 'Matriz de riesgos y planes de acción correctiva'],
			]],

			// 5. OPERACIONES Y PERSONAL
			['id' => 'operaciones', 'label' => '5. OPERACIONES Y PERSONAL', 'icon' => 'fa-people-group', 'items' => [
				['id' => 'hr', 'label' => '1. Recursos Humanos', 'icon' => 'fa-users-gear', 'route' => '/hr', 'views' => ['hr_dashboard'], 'desc' => 'Expedientes, contratación, turnos, nómina, productividad y capacitación'],
				['id' => 'schedule', 'label' => '2. Horarios y Cobertura', 'icon' => 'fa-calendar-days', 'route' => '/operations/scheduling', 'views' => ['schedule'], 'desc' => 'Planificación de turnos, cobertura por área y picos de demanda'],
				['id' => 'attendance', 'label' => '3. Asistencia y Fichajes', 'icon' => 'fa-fingerprint', 'route' => '/operations/attendance', 'views' => ['attendance'], 'desc' => 'Control de entradas, salidas, descansos y novedades por rol'],
				['id' => 'tasks', 'label' => '4. Tareas y Roles', 'icon' => 'fa-list-check', 'route' => '/workforce', 'views' => ['workforce', 'equipment', 'equipmentform', 'choresoverview', 'choretracking', 'choresjournal', 'tasks', 'calendar', 'batteriesoverview', 'batterytracking'], 'desc' => 'Tareas operativas y asignación en piso de tienda'],
				['id' => 'productivity', 'label' => '5. Productividad Operativa', 'icon' => 'fa-gauge-high', 'route' => '/schedule#tab-productivity', 'views' => [], 'desc' => 'Horas programadas vs. ejecutadas y rendimiento de piso'],
			]],

			// 6. ADMINISTRACIÓN Y SISTEMA
			['id' => 'sistema', 'label' => '6. ADMINISTRACIÓN Y SISTEMA', 'icon' => 'fa-gear', 'items' => [
				['id' => 'admin-settings', 'label' => '1. Configuración del Sistema', 'icon' => 'fa-sliders', 'route' => '/settings', 'views' => ['admin_settings'], 'desc' => 'Parámetros corporativos, gobernanza multiempresa, flujos de aprobación e integraciones'],
				['id' => 'users', 'label' => '2. Usuarios y Permisos', 'icon' => 'fa-user-shield', 'route' => '/users', 'views' => ['users', 'userform', 'userpermissions'], 'desc' => 'Gestión de roles, permisos por módulo y sesiones activas'],
				['id' => 'master-data', 'label' => '3. Gestión de Datos Maestros', 'translate' => false, 'icon' => 'fa-database', 'route' => '#sub-nav-manage-master-data', 'views' => [], 'desc' => 'Productos, ubicaciones, unidades, causales y más', 'children' => [
					['label' => 'Products', 'translate' => true, 'route' => '/products', 'views' => ['products']],
					['label' => 'Locations', 'translate' => true, 'route' => '/locations', 'views' => ['locations'], 'flags' => ['GROCY_FEATURE_FLAG_STOCK', 'GROCY_FEATURE_FLAG_STOCK_LOCATION_TRACKING']],
					['label' => 'Stores', 'translate' => true, 'route' => '/shoppinglocations', 'views' => ['shoppinglocations'], 'flags' => ['GROCY_FEATURE_FLAG_STOCK', 'GROCY_FEATURE_FLAG_STOCK_PRICE_TRACKING']],
					['label' => 'Quantity units', 'translate' => true, 'route' => '/quantityunits', 'views' => ['quantityunits']],
					['label' => 'Product groups', 'translate' => true, 'route' => '/productgroups', 'views' => ['productgroups']],
					['label' => 'Chores', 'translate' => true, 'route' => '/chores', 'views' => ['chores'], 'flags' => ['GROCY_FEATURE_FLAG_CHORES']],
					['label' => 'Batteries', 'translate' => true, 'route' => '/batteries', 'views' => ['batteries'], 'flags' => ['GROCY_FEATURE_FLAG_BATTERIES']],
					['label' => 'Task categories', 'translate' => true, 'route' => '/taskcategories', 'views' => ['taskcategories'], 'flags' => ['GROCY_FEATURE_FLAG_TASKS']],
					['label' => 'Userfields', 'translate' => true, 'route' => '/userfields', 'views' => ['userfields']],
					['label' => 'Userentities', 'translate' => true, 'route' => '/userentities', 'views' => ['userentities']],
				]],
				['id' => 'data-import', 'label' => '4. Importar / Exportar', 'icon' => 'fa-file-excel', 'route' => '/data-import', 'views' => ['data_import'], 'desc' => 'Carga masiva CSV/Excel y plantillas de maestros'],
				['id' => 'labels', 'label' => '5. Etiquetas de Ubicación', 'icon' => 'fa-tags', 'route' => '/locations/labels', 'views' => ['location_labels'], 'desc' => 'Generación e impresión de etiquetas LOC- (QR / Code 128)'],
				['id' => 'audit-log', 'label' => '6. Bitácora de Auditoría', 'icon' => 'fa-clock-rotate-left', 'route' => '/audit-log', 'views' => ['admin_audit_log'], 'desc' => 'Trazabilidad inalterable de operaciones, eventos de dominio y diff de datos'],
				['id' => 'system-map', 'label' => '7. Mapa del Sistema', 'icon' => 'fa-sitemap', 'route' => '/system-map', 'views' => ['system_map'], 'desc' => 'Módulos, conexiones, estado y auditoría técnica (solo administradores)'],
				['id' => 'economics', 'label' => '8. Economía de DAILY', 'icon' => 'fa-coins', 'route' => '/admin/economics', 'views' => ['admin_economics'], 'desc' => 'Finanzas internas SaaS: MRR, costos cloud, márgenes y simulador (solo administradores)'],
				['id' => 'subscription', 'label' => '9. Suscripción y Planes', 'icon' => 'fa-gem', 'route' => '/subscription', 'views' => ['subscription'], 'desc' => 'Plan contratado, funcionalidades incluidas, comparación de planes y solicitudes de ampliación'],
			]],

			// 7. PLANEACIÓN Y COMPRAS
			['id' => 'planeacion', 'label' => '7. PLANEACIÓN Y COMPRAS', 'icon' => 'fa-cart-flatbed', 'items' => [
				['id' => 'mrp', 'label' => '1. Planificación MRP', 'icon' => 'fa-diagram-project', 'route' => '/mrp', 'views' => ['mrp'], 'desc' => 'Reposición sugerida, stock de seguridad y punto de reorden'],
				['id' => 'suppliers', 'label' => '2. Proveedores y Compras', 'icon' => 'fa-truck-field', 'route' => '/suppliers', 'views' => ['suppliers_procurement', 'purchase', 'shoppinglist', 'shoppinglistitemform', 'shoppinglistform'], 'desc' => 'Abastecimiento, órdenes de compra, evaluación OTIF y recepción'],
			]],

			// INDICADORES FINANCIEROS (antes «Reportes y Analítica»: cada módulo conserva su análisis; aquí queda lo financiero transversal)
			['id' => 'analisis', 'label' => 'INDICADORES FINANCIEROS', 'icon' => 'fa-chart-pie', 'items' => [
				['id' => 'profitability', 'label' => '1. Rentabilidad y Margen', 'icon' => 'fa-coins', 'route' => '/sales?tab=rentabilidad', 'views' => [], 'desc' => 'Margen diario, mensual y acumulado; costo de ventas y aporte por departamento'],
				['id' => 'cost-centers', 'label' => '2. Centros de Costo', 'icon' => 'fa-building-columns', 'route' => '/cost-centers', 'views' => ['cost_centers'], 'desc' => 'Margen, merma y presupuesto por sucursal y departamento'],
				['id' => 'reports', 'label' => '3. Reportes e Informes', 'icon' => 'fa-file-invoice-dollar', 'route' => '/reports', 'views' => ['reports_hub', 'stockreportspendings', 'stockjournalsummary'], 'desc' => 'Informes financieros, auditorías de inventario y balances'],
			]],
		];
	}

	/**
	 * Árbol listo para pintar: filtra por permisos y feature flags, traduce y marca activos.
	 */
	public static function Build(string $viewName, callable $translate): array
	{
		$isActive = self::ActiveMatcher($viewName);
		$result = [];
		foreach (self::Categories() as $cat)
		{
			$cat['active'] = false;
			$items = [];
			foreach ($cat['items'] as $item)
			{
				if (isset($item['children']))
				{
					$children = [];
					foreach ($item['children'] as $child)
					{
						if (!self::Allowed($child))
						{
							continue;
						}
						$child['label'] = self::Label($child, $translate);
						$child['active'] = $isActive($child);
						$children[] = $child;
					}
					if (count($children) === 0)
					{
						continue;
					}
					$item['children'] = $children;
					$item['active'] = in_array(true, array_column($children, 'active'), true);
				}
				elseif (!self::Allowed($item))
				{
					continue;
				}
				else
				{
					$item['active'] = $isActive($item);
				}
				$item['label'] = self::Label($item, $translate);
				$cat['active'] = $cat['active'] || $item['active'];
				$items[] = $item;
			}
			if (count($items) > 0)
			{
				$cat['items'] = $items;
				$result[] = $cat;
			}
		}
		return $result;
	}

	/**
	 * Entrada activa: la que declara la vista actual. Si varias comparten vista (p. ej. /orders y /dispatch#tab-picking
	 * usan 'orders'), solo la que coincide con la ruta pedida; así el menú nunca marca dos destinos a la vez.
	 */
	/**
	 * Funcionalidades de planes superiores que el rol del usuario podría usar: sección separada «Ampliar plan»,
	 * agrupada por plan requerido. Cada enlace abre la pantalla de plan requerido (nunca una ruta vacía).
	 */
	public static function Locked(callable $translate): array
	{
		$groups = [];
		foreach (self::Categories() as $cat)
		{
			foreach ($cat['items'] as $item)
			{
				if (!empty($item['hidden']) || isset($item['children']))
				{
					continue;
				}
				$cap = DailySubscription::MissingForRoute($item['route']);
				if ($cap === null || !DairyAccess::CanOpen($item['route']))
				{
					continue;
				}
				$plan = DailySubscription::RequiredPlan($cap);
				$item['label'] = preg_replace('/^\d+\.\s*/', '', self::Label($item, $translate));
				$item['plan'] = $plan;
				$groups[$plan][] = $item;
			}
		}
		uksort($groups, fn ($a, $b) => DailySubscription::PlanRank($a) <=> DailySubscription::PlanRank($b));
		return $groups;
	}

	private static function ActiveMatcher(string $viewName): callable
	{
		$routePath = fn (array $e) => explode('#', explode('?', $e['route'] ?? '')[0])[0];
		$leaves = [];
		foreach (self::Categories() as $cat)
		{
			foreach ($cat['items'] as $item)
			{
				foreach ($item['children'] ?? [$item] as $e)
				{
					if (in_array($viewName, $e['views'] ?? [], true))
					{
						$leaves[] = $routePath($e);
					}
				}
			}
		}
		$path = rtrim((string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH), '/');
		$exact = array_values(array_filter(array_unique($leaves), fn ($r) => $r !== '' && $path !== '' && str_ends_with($path, $r)));
		return function (array $e) use ($viewName, $leaves, $exact, $routePath) {
			if (!in_array($viewName, $e['views'] ?? [], true))
			{
				return false;
			}
			if (count(array_unique($leaves)) < 2 || count($exact) === 0)
			{
				return true;
			}
			return in_array($routePath($e), $exact, true);
		};
	}

	private static function Allowed(array $entry): bool
	{
		// Oculto temporalmente del menú; la ruta sigue funcionando
		if (!empty($entry['hidden']))
		{
			return false;
		}
		foreach ($entry['flags'] ?? [] as $flag)
		{
			if (!defined($flag) || !constant($flag))
			{
				return false;
			}
		}
		// Plan contratado (qué ofrece DAILY) y rol (qué puede abrir el usuario)
		return DailySubscription::MissingForRoute($entry['route']) === null && DairyAccess::CanOpen($entry['route']);
	}

	private static function Label(array $entry, callable $translate): string
	{
		return !empty($entry['translate']) ? $translate($entry['label']) : $entry['label'];
	}
}
