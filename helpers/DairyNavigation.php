<?php

namespace Grocy\Helpers;

/**
 * Configuración centralizada del sidebar DAILY: categorías → herramientas.
 * Cada herramienta: id, etiqueta, icono (Font Awesome), ruta, vistas que la marcan activa y descripción.
 * Los permisos por rol se aplican con DairyAccess::CanOpen(ruta); las categorías vacías se ocultan.
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

			// 1. PLANEACIÓN Y COMPRAS
			['id' => 'planeacion', 'label' => '1. PLANEACIÓN Y COMPRAS', 'icon' => 'fa-cart-flatbed', 'items' => [
				['id' => 'mrp', 'label' => '1. Planificación MRP', 'icon' => 'fa-diagram-project', 'route' => '/mrp', 'views' => ['mrp'], 'desc' => 'Reposición sugerida, stock de seguridad y punto de reorden'],
				['id' => 'simulator', 'label' => '2. Simulador What-If', 'icon' => 'fa-calculator', 'route' => '/ai-advisor#tab-simulator', 'views' => [], 'desc' => 'Impacto financiero de rebajas FEFO vs. merma operativa'],
				['id' => 'suppliers', 'label' => '3. Proveedores & Compras', 'icon' => 'fa-truck-field', 'route' => '/suppliers', 'views' => ['suppliers_procurement', 'purchase', 'shoppinglist', 'shoppinglistitemform', 'shoppinglistform'], 'desc' => 'Abastecimiento, órdenes de compra, evaluación OTIF y recepción'],
			]],

			// 2. INVENTARIO Y ALMACENES
			['id' => 'inventario', 'label' => '2. INVENTARIO Y ALMACENES', 'icon' => 'fa-boxes-stacked', 'items' => [
				['id' => 'branches', 'label' => '1. Sucursales y Almacenes', 'icon' => 'fa-building', 'route' => '/branches', 'views' => ['branches', 'branch_stock'], 'desc' => 'Estructura multi-sucursal y stock consolidado de la red'],
				['id' => 'merchandise', 'label' => '2. Control de Mercancías', 'icon' => 'fa-warehouse', 'route' => '/merchandise', 'views' => ['merchandise'], 'desc' => 'Centro operativo: recepciones, entradas, salidas, movimientos y documentos'],
				['id' => 'scan', 'label' => '3. Operaciones con Escáner', 'icon' => 'fa-barcode', 'route' => '/scan', 'views' => ['scan'], 'desc' => 'Conteo guiado, movimientos, recepción y picking por escáner láser'],
				['id' => 'location-map', 'label' => '4. Mapa de Ubicaciones', 'icon' => 'fa-map-location-dot', 'route' => '/locations/map', 'views' => ['location_map'], 'desc' => 'Ocupación, vencimientos y valor por almacén, zona y pasillo'],
				['id' => 'stock', 'label' => '5. Stock & Movimientos', 'icon' => 'fa-cubes', 'route' => '/stockoverview', 'views' => ['stockoverview', 'stockentries', 'stockjournal', 'transfer', 'stockentryform', 'locationcontentsheet'], 'desc' => 'Existencias por almacén, lotes y bitácora de movimientos'],
				['id' => 'transfers', 'label' => '6. Traslados entre Tiendas', 'icon' => 'fa-right-left', 'route' => '/transfers', 'views' => ['transfers'], 'desc' => 'Envío y recepción con verificación entre sucursales'],
				['id' => 'kardex', 'label' => '7. Kardex Valorizado', 'icon' => 'fa-book-open', 'route' => '/kardex', 'views' => ['kardex'], 'desc' => 'Movimientos valorizados por producto, lote y sucursal'],
				['id' => 'inventory-control', 'label' => '8. Control de Inventario', 'icon' => 'fa-magnifying-glass-chart', 'route' => '/inventory-control', 'views' => ['inventory_control'], 'desc' => 'Centro analítico: stock fantasma, matriz ABC-XYZ y cobertura'],
				['id' => 'inventory', 'label' => '9. Conteos Físicos', 'icon' => 'fa-clipboard-list', 'route' => '/inventory', 'views' => ['inventory'], 'desc' => 'Inventario físico, conteos cíclicos y conciliación de ajustes'],
				['id' => 'reconciliation', 'label' => '10. Conciliación & Sensible', 'icon' => 'fa-arrows-rotate', 'route' => '/reconciliation', 'views' => ['reconciliation'], 'desc' => 'Trazabilidad 360°, inventario sensible, conciliación de 5 flujos y alertas'],
			]],

			// 3. VENTAS Y RENTABILIDAD
			['id' => 'ventas', 'label' => '3. VENTAS Y RENTABILIDAD', 'icon' => 'fa-cash-register', 'items' => [
				['id' => 'sales', 'label' => '1. Ventas y Rentabilidad', 'icon' => 'fa-chart-column', 'route' => '/sales', 'views' => ['sales_history'], 'desc' => 'Dashboard comercial: curva de ventas, márgenes, sucursales y análisis por departamento'],
				['id' => 'pos', 'label' => '2. Punto de Venta (POS)', 'icon' => 'fa-cash-register', 'route' => '/pos', 'views' => ['pos'], 'desc' => 'Terminal POS con rotación y salida FEFO'],
				['id' => 'orders', 'label' => '3. Pedidos de Clientes', 'icon' => 'fa-boxes-packing', 'route' => '/orders', 'views' => ['orders'], 'desc' => 'Reserva, picking por escáner, empaque y despacho'],
				['id' => 'customers', 'label' => '4. Clientes y Cuentas', 'icon' => 'fa-users', 'route' => '/customers', 'views' => ['customers'], 'desc' => 'Gestión de clientes, crédito y pedidos corporativos'],
			]],

			// 4. DESPACHOS Y DEVOLUCIONES
			['id' => 'despachos', 'label' => '4. DESPACHOS Y DEVOLUCIONES', 'icon' => 'fa-truck-ramp-box', 'items' => [
				['id' => 'dispatch_picking', 'label' => '1. Picking y Packing', 'icon' => 'fa-boxes-packing', 'route' => '/dispatch#tab-picking', 'views' => ['orders'], 'desc' => 'Preparación por escáner láser, validación FEFO y empaque en bultos'],
				['id' => 'dispatch', 'label' => '2. Centro de Despachos y Seguimiento', 'icon' => 'fa-truck-ramp-box', 'route' => '/dispatch', 'views' => ['dispatch'], 'desc' => 'Conciliación en 7 etapas, transportista, cliente recoge vs. domicilio y evidencia'],
				['id' => 'returns', 'label' => '3. Devoluciones e Incidencias', 'icon' => 'fa-arrow-rotate-left', 'route' => '/returns', 'views' => ['returns'], 'desc' => 'Inspección, clasificación sanitaria en 7 destinos y control de mermas'],
			]],

			// 5. CALIDAD Y VENCIMIENTOS
			['id' => 'calidad', 'label' => '5. CALIDAD Y VENCIMIENTOS', 'icon' => 'fa-award', 'items' => [
				['id' => 'quality', 'label' => '1. Calidad & Vencimientos', 'icon' => 'fa-snowflake', 'route' => '/quality', 'views' => ['quality_expiry'], 'desc' => 'Control FEFO, cuarentena sanitaria y cadena de frío'],
				['id' => 'traceability', 'label' => '2. Trazabilidad de Lotes', 'icon' => 'fa-route', 'route' => '/traceability', 'views' => ['traceability'], 'desc' => 'Seguimiento 360° de lotes: proveedor, traslados, mermas y ventas'],
				['id' => 'recalls', 'label' => '3. Retiros de Producto', 'icon' => 'fa-rotate-left', 'route' => '/recalls', 'views' => ['recalls'], 'desc' => 'Gestión y confirmación de retiros masivos en red de tiendas'],
			]],

			// 6. MERMAS Y PREVENCIÓN
			['id' => 'mermas', 'label' => '6. MERMAS Y PREVENCIÓN', 'icon' => 'fa-trash-can', 'items' => [
				['id' => 'waste', 'label' => '1. Cuadro de Control de Mermas', 'icon' => 'fa-trash-can', 'route' => '/waste', 'views' => ['waste_overview'], 'desc' => 'Resumen ejecutivo de pérdidas, causas y oportunidades'],
				['id' => 'waste-report', 'label' => '2. Registro de Merma', 'icon' => 'fa-file-circle-plus', 'route' => '/waste/report', 'views' => ['waste_report'], 'desc' => 'Registro de merma con fotografía y evidencia'],
				['id' => 'waste-approvals', 'label' => '3. Aprobaciones de Merma', 'icon' => 'fa-clipboard-check', 'route' => '/waste/approvals', 'views' => ['waste_approvals'], 'desc' => 'Flujo y cola de aprobación de mermas pendientes'],
				['id' => 'waste-reasons', 'label' => '4. Matriz de Causales', 'icon' => 'fa-diagram-project', 'route' => '/waste/reasons', 'views' => ['waste_reasons'], 'desc' => 'Catálogo AKCAL de causas, protocolos y niveles de autorización'],
				['id' => 'waste-investigations', 'label' => '5. Investigaciones y Casos de Merma', 'icon' => 'fa-magnifying-glass', 'route' => '/waste#tab-investigaciones', 'views' => [], 'desc' => 'Expedientes de investigación por merma crítica'],
				['id' => 'waste-recovery', 'label' => '6. Recuperación Financiera', 'icon' => 'fa-hand-holding-dollar', 'route' => '/waste#tab-recuperacion', 'views' => [], 'desc' => 'Gestión de reclamos a proveedores y recuperaciones'],
			]],

			// 7. PREVENCIÓN DE PÉRDIDAS
			['id' => 'prevencion-perdidas', 'label' => '7. PREVENCIÓN DE PÉRDIDAS', 'icon' => 'fa-shield-halved', 'items' => [
				['id' => 'loss-control', 'label' => '1. Centro de Control de Prevención de Pérdidas', 'icon' => 'fa-gauge-high', 'route' => '/loss-prevention', 'views' => ['loss_prevention'], 'desc' => 'Incidentes, activos, celulares, investigaciones y resolución'],
				['id' => 'loss-investigations', 'label' => '2. Investigaciones', 'icon' => 'fa-magnifying-glass', 'route' => '/loss-prevention#tab-expedientes', 'views' => ['loss_case', 'loss_counts'], 'desc' => 'Expedientes activos, evidencias y trazabilidad'],
				['id' => 'devices', 'label' => '3. Control de Celulares', 'icon' => 'fa-mobile-screen-button', 'route' => '/loss-prevention/devices', 'views' => ['devices', 'device_detail'], 'desc' => 'Trazabilidad individual por IMEI, custodia y estado'],
				['id' => 'merchandise-assets', 'label' => '4. Control de Activos', 'icon' => 'fa-laptop-file', 'route' => '/loss-prevention#tab-activos', 'views' => [], 'desc' => 'Control integral de activos, equipos y mercancía sensible'],
				['id' => 'loss-access', 'label' => '5. Accesos y Autorizaciones', 'icon' => 'fa-key', 'route' => '/loss-prevention#tab-accesos', 'views' => [], 'desc' => 'Custodia, roles autorizados y doble aprobación'],
				['id' => 'loss-cctv', 'label' => '6. CCTV y Evidencias', 'icon' => 'fa-video', 'route' => '/loss-prevention#tab-cctv', 'views' => [], 'desc' => 'Cámaras autorizadas, referencias de video y bitácora'],
				['id' => 'loss-rounds', 'label' => '7. Rondas y Recorridos', 'icon' => 'fa-person-walking', 'route' => '/loss-prevention#tab-rondas', 'views' => [], 'desc' => 'Check-ins en piso y rondas de seguridad'],
				['id' => 'loss-plans', 'label' => '8. Planes Preventivos', 'icon' => 'fa-bullseye', 'route' => '/loss-prevention#tab-planes', 'views' => [], 'desc' => 'Matriz de riesgos y planes de acción correctiva'],
				['id' => 'loss-kpi', 'label' => '9. Reportes y KPI de Prevención', 'icon' => 'fa-chart-pie', 'route' => '/reports/loss-executive', 'views' => ['loss_executive_report'], 'desc' => 'Analítica ejecutiva de mermas y prevención'],
			]],

			// 8. OPERACIONES Y PERSONAL
			['id' => 'operaciones', 'label' => '8. OPERACIONES Y PERSONAL', 'icon' => 'fa-people-group', 'items' => [
				['id' => 'staff', 'label' => '1. Gestión de Personal', 'icon' => 'fa-users-gear', 'route' => '/schedule#tab-employees', 'views' => [], 'desc' => 'Colaboradores, áreas, contratos y roles de tienda'],
				['id' => 'schedule', 'label' => '2. Horarios y Cobertura', 'icon' => 'fa-calendar-days', 'route' => '/operations/scheduling', 'views' => ['schedule'], 'desc' => 'Planificación de turnos, cobertura por área y picos de demanda'],
				['id' => 'attendance', 'label' => '3. Asistencia y Fichajes', 'icon' => 'fa-fingerprint', 'route' => '/operations/attendance', 'views' => ['attendance'], 'desc' => 'Control de entradas, salidas, descansos y novedades por rol'],
				['id' => 'tasks', 'label' => '4. Tareas y Roles', 'icon' => 'fa-list-check', 'route' => '/workforce', 'views' => ['workforce', 'equipment', 'equipmentform', 'choresoverview', 'choretracking', 'choresjournal', 'tasks', 'calendar', 'batteriesoverview', 'batterytracking'], 'desc' => 'Tareas operativas y asignación en piso de tienda'],
				['id' => 'productivity', 'label' => '5. Productividad Operativa', 'icon' => 'fa-gauge-high', 'route' => '/schedule#tab-productivity', 'views' => [], 'desc' => 'Horas programadas vs. ejecutadas y rendimiento de piso'],
			]],

			// 9. REPORTES Y ANALÍTICA
			['id' => 'analisis', 'label' => '9. REPORTES Y ANALÍTICA', 'icon' => 'fa-chart-pie', 'items' => [
				['id' => 'reports', 'label' => '1. Reportes & Analítica', 'icon' => 'fa-file-invoice-dollar', 'route' => '/reports', 'views' => ['reports_hub', 'stockreportspendings', 'stockjournalsummary'], 'desc' => 'Informes financieros, auditorías de inventario y balances'],
				['id' => 'cost-centers', 'label' => '2. Centros de Costo', 'icon' => 'fa-building-columns', 'route' => '/cost-centers', 'views' => ['cost_centers'], 'desc' => 'Margen, merma y presupuesto por sucursal y departamento'],
				['id' => 'ai-advisor', 'label' => '3. Arturito IA (Asesor)', 'icon' => 'fa-robot', 'route' => '/ai-advisor', 'views' => ['ai_advisor'], 'desc' => 'Asesor ejecutivo inteligente, detección de anomalías y simulador'],
			]],

			// 10. ADMINISTRACIÓN Y SISTEMA
			['id' => 'sistema', 'label' => '10. ADMINISTRACIÓN Y SISTEMA', 'icon' => 'fa-gear', 'items' => [
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
		return DairyAccess::CanOpen($entry['route']);
	}

	private static function Label(array $entry, callable $translate): string
	{
		return !empty($entry['translate']) ? $translate($entry['label']) : $entry['label'];
	}
}
