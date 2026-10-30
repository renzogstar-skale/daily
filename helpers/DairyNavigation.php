<?php

namespace Grocy\Helpers;

/**
 * Configuración centralizada del sidebar DAIRY: categorías → herramientas.
 * Cada herramienta: id, etiqueta, icono (Font Awesome), ruta, vistas que la marcan activa y descripción.
 * Los permisos por rol se aplican con DairyAccess::CanOpen(ruta); las categorías vacías se ocultan.
 */
class DairyNavigation
{
	public static function Categories(): array
	{
		return [
			// Supervisión Central
			['id' => 'inicio', 'label' => 'Supervisión y Control', 'icon' => 'fa-house', 'items' => [
				['id' => 'today', 'label' => 'HOY · Operación del Día', 'icon' => 'fa-list-check', 'route' => '/today', 'views' => ['today'], 'desc' => 'Resumen operativo del día y decisiones urgentes'],
				['id' => 'alerts', 'label' => 'Centro de Alertas', 'icon' => 'fa-bell', 'route' => '/alerts', 'views' => ['alert_center'], 'desc' => 'Alertas accionables con responsable, plazo y cierre aprobado', 'badge' => 'alerts'],
				['id' => 'kpis', 'label' => 'Panel Ejecutivo', 'icon' => 'fa-chart-line', 'route' => '/kpis', 'views' => ['executive_dashboard'], 'desc' => 'Tablero ejecutivo de rentabilidad, margen y KPIs estratégicos'],
			]],

			// 1. Ventas y Rentabilidad
			['id' => 'ventas', 'label' => '1. Ventas y Rentabilidad', 'icon' => 'fa-cash-register', 'items' => [
				['id' => 'sales', 'label' => 'Ventas y Rentabilidad', 'icon' => 'fa-chart-column', 'route' => '/sales', 'views' => ['sales_history'], 'desc' => 'Dashboard comercial: curva de ventas, márgenes, sucursales y análisis por departamento'],
				['id' => 'pos', 'label' => 'Punto de Venta (POS)', 'icon' => 'fa-cash-register', 'route' => '/pos', 'views' => ['pos'], 'desc' => 'Terminal POS con rotación y salida FEFO'],
				['id' => 'orders', 'label' => 'Pedidos de Clientes', 'icon' => 'fa-boxes-packing', 'route' => '/orders', 'views' => ['orders'], 'desc' => 'Reserva, picking por escáner, empaque y despacho'],
				['id' => 'customers', 'label' => 'Clientes y Cuentas', 'icon' => 'fa-users', 'route' => '/customers', 'views' => ['customers'], 'desc' => 'Gestión de clientes, crédito y pedidos corporativos'],
			]],

			// 2. Planeación y Compras
			['id' => 'planeacion', 'label' => '2. Planeación y Compras', 'icon' => 'fa-cart-flatbed', 'items' => [
				['id' => 'mrp', 'label' => 'Planificación MRP', 'icon' => 'fa-diagram-project', 'route' => '/mrp', 'views' => ['mrp'], 'desc' => 'Reposición sugerida, stock de seguridad y punto de reorden'],
				['id' => 'suppliers', 'label' => 'Proveedores & Compras', 'icon' => 'fa-truck-field', 'route' => '/suppliers', 'views' => ['suppliers_procurement', 'purchase', 'shoppinglist', 'shoppinglistitemform', 'shoppinglistform'], 'desc' => 'Abastecimiento, órdenes de compra, evaluación OTIF y recepción'],
				['id' => 'simulator', 'label' => 'Simulador What-If', 'icon' => 'fa-calculator', 'route' => '/ai-advisor#tab-simulator', 'views' => [], 'desc' => 'Impacto financiero de rebajas FEFO vs. merma operativa'],
			]],

			// 3. Inventario y Almacenes
			['id' => 'inventario', 'label' => '3. Inventario y Almacenes', 'icon' => 'fa-boxes-stacked', 'items' => [
				['id' => 'stock', 'label' => 'Stock & Movimientos', 'icon' => 'fa-cubes', 'route' => '/stockoverview', 'views' => ['stockoverview', 'stockentries', 'stockjournal', 'transfer', 'stockentryform', 'locationcontentsheet'], 'desc' => 'Existencias por almacén, lotes y bitácora de movimientos'],
				['id' => 'scan', 'label' => 'Operaciones con Escáner', 'icon' => 'fa-barcode', 'route' => '/scan', 'views' => ['scan'], 'desc' => 'Conteo guiado, movimientos, recepción y picking por escáner'],
				['id' => 'location-map', 'label' => 'Mapa de Ubicaciones', 'icon' => 'fa-map-location-dot', 'route' => '/locations/map', 'views' => ['location_map'], 'desc' => 'Ocupación, vencimientos y valor por almacén, zona y pasillo'],
				['id' => 'transfers', 'label' => 'Traslados entre Tiendas', 'icon' => 'fa-right-left', 'route' => '/transfers', 'views' => ['transfers'], 'desc' => 'Envío y recepción con verificación entre sucursales'],
				['id' => 'kardex', 'label' => 'Kardex Valorizado', 'icon' => 'fa-book-open', 'route' => '/kardex', 'views' => ['kardex'], 'desc' => 'Movimientos valorizados por producto, lote y sucursal'],
				['id' => 'reconciliation', 'label' => 'Conciliación & Sensible', 'icon' => 'fa-arrows-rotate', 'route' => '/reconciliation', 'views' => ['reconciliation'], 'desc' => 'Trazabilidad 360°, inventario sensible, conciliación de 5 flujos y alertas'],
				['id' => 'inventory-control', 'label' => 'Control de Inventario', 'icon' => 'fa-magnifying-glass-chart', 'route' => '/inventory-control', 'views' => ['inventory_control'], 'desc' => 'Stock fantasma y matriz ABC-XYZ de rotación'],
				['id' => 'inventory', 'label' => 'Conteos Físicos', 'icon' => 'fa-clipboard-list', 'route' => '/inventory', 'views' => ['inventory'], 'desc' => 'Inventario físico y conciliación de ajustes'],
				['id' => 'branches', 'label' => 'Sucursales y Almacenes', 'icon' => 'fa-building', 'route' => '/branches', 'views' => ['branches', 'branch_stock'], 'desc' => 'Estructura multi-sucursal y stock consolidado de la red'],
			]],

			// 4. Despachos, Cambios y Devoluciones
			['id' => 'despachos', 'label' => '4. Despachos y Devoluciones', 'icon' => 'fa-truck-ramp-box', 'items' => [
				['id' => 'dispatch', 'label' => 'Despachos y Validaciones', 'icon' => 'fa-truck-ramp-box', 'route' => '/dispatch', 'views' => ['dispatch'], 'desc' => 'Conciliación en 7 etapas, cliente recoge vs. domicilio y escaneo UPC'],
				['id' => 'returns', 'label' => 'Cambios y Devoluciones', 'icon' => 'fa-arrow-rotate-left', 'route' => '/returns', 'views' => ['returns'], 'desc' => 'Inspección, clasificación sanitaria en 7 destinos y control económico'],
			]],

			// 5. Operaciones y Personal
			['id' => 'operaciones', 'label' => '5. Operaciones y Personal', 'icon' => 'fa-people-group', 'items' => [
				['id' => 'schedule', 'label' => 'Horarios y Cobertura', 'icon' => 'fa-calendar-days', 'route' => '/operations/scheduling', 'views' => ['schedule'], 'desc' => 'Planificación de turnos, cobertura por área y picos de demanda'],
				['id' => 'attendance', 'label' => 'Asistencia y Fichajes', 'icon' => 'fa-fingerprint', 'route' => '/operations/attendance', 'views' => ['attendance'], 'desc' => 'Control de entradas, salidas, descansos y novedades por rol'],
				['id' => 'staff', 'label' => 'Gestión de Personal', 'icon' => 'fa-users-gear', 'route' => '/schedule#tab-employees', 'views' => [], 'desc' => 'Colaboradores, áreas, contratos y roles de tienda'],
				['id' => 'tasks', 'label' => 'Tareas y Roles de Piso', 'icon' => 'fa-list-check', 'route' => '/workforce', 'views' => ['workforce', 'equipment', 'equipmentform', 'choresoverview', 'choretracking', 'choresjournal', 'tasks', 'calendar', 'batteriesoverview', 'batterytracking'], 'desc' => 'Tareas operativas y asignación en piso de tienda'],
				['id' => 'productivity', 'label' => 'Productividad Operativa', 'icon' => 'fa-gauge-high', 'route' => '/schedule#tab-productivity', 'views' => [], 'desc' => 'Horas programadas vs. ejecutadas y rendimiento de piso'],
			]],

			// 6. Calidad y Vencimientos
			['id' => 'calidad', 'label' => '6. Calidad y Vencimientos', 'icon' => 'fa-award', 'items' => [
				['id' => 'quality', 'label' => 'Calidad & Vencimientos', 'icon' => 'fa-snowflake', 'route' => '/quality', 'views' => ['quality_expiry'], 'desc' => 'Control FEFO, cuarentena sanitaria y cadena de frío'],
				['id' => 'traceability', 'label' => 'Trazabilidad de Lotes', 'icon' => 'fa-route', 'route' => '/traceability', 'views' => ['traceability'], 'desc' => 'Seguimiento 360° de lotes: proveedor, traslados, mermas y ventas'],
				['id' => 'recalls', 'label' => 'Retiros de Producto', 'icon' => 'fa-rotate-left', 'route' => '/recalls', 'views' => ['recalls'], 'desc' => 'Gestión y confirmación de retiros masivos en red de tiendas'],
			]],

			// 7. Mermas y Prevención
			['id' => 'mermas', 'label' => '7. Mermas y Prevención', 'icon' => 'fa-shield-halved', 'items' => [
				['id' => 'waste', 'label' => 'Centro de Control', 'icon' => 'fa-trash-can', 'route' => '/waste', 'views' => ['waste_overview'], 'desc' => 'Resumen ejecutivo de pérdidas, causas y oportunidades'],
				['id' => 'waste-report', 'label' => 'Registrar Merma', 'icon' => 'fa-file-circle-plus', 'route' => '/waste/report', 'views' => ['waste_report'], 'desc' => 'Registro de merma con fotografía y evidencia'],
				['id' => 'waste-approvals', 'label' => 'Aprobaciones', 'icon' => 'fa-clipboard-check', 'route' => '/waste/approvals', 'views' => ['waste_approvals'], 'desc' => 'Flujo y cola de aprobación de mermas pendientes'],
				['id' => 'waste-reasons', 'label' => 'Matriz de Causales', 'icon' => 'fa-diagram-project', 'route' => '/waste/reasons', 'views' => ['waste_reasons'], 'desc' => 'Catálogo AKCAL de causas, protocolos y niveles de autorización'],
				['id' => 'waste-investigations', 'label' => 'Investigaciones y Casos', 'icon' => 'fa-magnifying-glass', 'route' => '/waste#tab-investigaciones', 'views' => [], 'desc' => 'Expedientes de investigación por merma crítica'],
				['id' => 'waste-recovery', 'label' => 'Recuperación Financiera', 'icon' => 'fa-hand-holding-dollar', 'route' => '/waste#tab-recuperacion', 'views' => [], 'desc' => 'Gestión de reclamos a proveedores y recuperaciones'],
			]],

			// 8. Reportes y Analítica
			['id' => 'analisis', 'label' => '8. Reportes y Analítica', 'icon' => 'fa-chart-pie', 'items' => [
				['id' => 'reports', 'label' => 'Reportes & Analítica', 'icon' => 'fa-file-invoice-dollar', 'route' => '/reports', 'views' => ['reports_hub', 'stockreportspendings', 'stockjournalsummary'], 'desc' => 'Informes financieros, auditorías de inventario y balances'],
				['id' => 'cost-centers', 'label' => 'Centros de Costo', 'icon' => 'fa-building-columns', 'route' => '/cost-centers', 'views' => ['cost_centers'], 'desc' => 'Margen, merma y presupuesto por sucursal y departamento'],
				['id' => 'ai-advisor', 'label' => 'Arturito IA (Asesor)', 'icon' => 'fa-robot', 'route' => '/ai-advisor', 'views' => ['ai_advisor'], 'desc' => 'Asesor ejecutivo inteligente, detección de anomalías y simulador'],
			]],

			// 9. Administración y Sistema
			['id' => 'sistema', 'label' => 'Administración y Sistema', 'icon' => 'fa-gear', 'items' => [
				['id' => 'system-map', 'label' => 'Mapa del Sistema', 'icon' => 'fa-sitemap', 'route' => '/system-map', 'views' => ['system_map'], 'desc' => 'Módulos, conexiones, estado y auditoría técnica (solo administradores)'],
				['id' => 'users', 'label' => 'Usuarios y Permisos', 'icon' => 'fa-user-shield', 'route' => '/users', 'views' => ['users', 'userform', 'userpermissions'], 'desc' => 'Gestión de roles, permisos por módulo y sesiones activas'],
				['id' => 'data-import', 'label' => 'Importar / Exportar', 'icon' => 'fa-file-excel', 'route' => '/data-import', 'views' => ['data_import'], 'desc' => 'Carga masiva CSV/Excel y plantillas de maestros'],
				['id' => 'labels', 'label' => 'Etiquetas de Ubicación', 'icon' => 'fa-tags', 'route' => '/locations/labels', 'views' => ['location_labels'], 'desc' => 'Generación e impresión de etiquetas LOC- (QR / Code 128)'],
				['id' => 'master-data', 'label' => 'Manage master data', 'translate' => true, 'icon' => 'fa-database', 'route' => '#sub-nav-manage-master-data', 'views' => [], 'desc' => 'Productos, ubicaciones, unidades, causales y más', 'children' => [
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
			]],
		];
	}

	/**
	 * Árbol listo para pintar: filtra por permisos y feature flags, traduce y marca activos.
	 */
	public static function Build(string $viewName, callable $translate): array
	{
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
						$child['active'] = in_array($viewName, $child['views']);
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
					$item['active'] = in_array($viewName, $item['views']);
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

	private static function Allowed(array $entry): bool
	{
		foreach ($entry['flags'] ?? [] as $flag)
		{
			if (!defined($flag) || !constant($flag))
			{
				return false;
			}
		}
		return DairyAccess::CanOpen($entry['route']);
	}

	private static function Label(array $entry, callable $translate): string
	{
		return !empty($entry['translate']) ? $translate($entry['label']) : $entry['label'];
	}
}
