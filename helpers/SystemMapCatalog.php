<?php

namespace Grocy\Helpers;

/**
 * Capa curada del Mapa del Sistema (/system-map). Solo contiene lo que el código NO puede decir por sí mismo:
 * categorías extra, módulos fuera del menú, conexiones operativas con la evidencia (archivo + patrón) que el
 * analizador comprueba en cada ejecución, propuestas (no implementadas) y hallazgos documentados en DAIRY_AUDIT.md.
 * Todo lo demás (rutas, pantallas, pestañas, botones, APIs, permisos, tablas, pruebas) lo extrae SystemMapService.
 */
class SystemMapCatalog
{
	// Las categorías del menú lateral se leen de helpers/DairyNavigation.php; estas se agregan
	const CATEGORIES = [
		'inicio' => ['Supervisión y Control', 'fa-house', '#2563eb'],
		'ventas' => ['Ventas y Rentabilidad', 'fa-cash-register', '#10b981'],
		'inventario' => ['Inventario y Almacenes', 'fa-boxes-stacked', '#475569'],
		'despachos' => ['Despachos y Devoluciones', 'fa-truck-ramp-box', '#0369a1'],
		'mermas' => ['Mermas y Prevención', 'fa-shield-halved', '#dc2626'],
		'operaciones' => ['Operaciones y Personal', 'fa-people-group', '#d97706'],
		'sistema' => ['Administración y Sistema', 'fa-gear', '#334155'],
		'planeacion' => ['Planeación y Compras', 'fa-cart-flatbed', '#1d4ed8'],
		'analisis' => ['Indicadores Financieros', 'fa-chart-pie', '#7c3aed'],
		'movil' => ['DAILY Móvil', 'fa-mobile-screen', '#0f766e'],
		'maestros' => ['Datos maestros', 'fa-database', '#64748b'],
		'admin' => ['Administración', 'fa-user-shield', '#334155'],
		'heredado' => ['Núcleo Grocy heredado', 'fa-box-archive', '#94a3b8'],
		'arquitectura' => ['Arquitectura', 'fa-sitemap', '#0f172a'],
	];

	// Sección del menú → categoría
	// Categoría del menú lateral (DairyNavigation) → categoría del mapa; NAV_ITEM_CATEGORY corrige herramientas puntuales
	const NAV_SECTION = [
		'inicio' => 'inicio',
		'ventas' => 'ventas',
		'planeacion' => 'planeacion',
		'inventario' => 'inventario',
		'operaciones' => 'operaciones',
		'mermas' => 'mermas',
		'despachos' => 'despachos',
		'analisis' => 'analisis',
		'sistema' => 'admin',
	];

	const NAV_ITEM_CATEGORY = [
		'recalls' => 'despachos',
		'traceability' => 'inventario',
	];

	// Módulos que no están en el menú lateral (se descubren por ruta). [categoría, nombre, ruta, descripción, rutas adicionales]
	const EXTRA_MODULES = [
		'waste_reasons' => ['mermas', 'Causales de merma', '/waste/reasons', 'Catálogo de causales (clasificación AKCAL, evidencia y aprobación exigidas)', []],
		'customers' => ['operaciones', 'Clientes', '/customers', 'Clientes para ventas a crédito y pedidos', []],
		'location_labels' => ['inventario', 'Etiquetas de ubicación', '/locations/labels', 'Códigos LOC- (QR / Code 128) para escanear ubicaciones', []],
		'data_import' => ['maestros', 'Importación de datos', '/data-import', 'Carga CSV de maestros con plantilla y registro de cambios', []],
		'documents' => ['analisis', 'Documentos imprimibles', '/document/{type}/{id}', 'OC, recepción, traslado, merma, ajuste, cuarentena, devolución y factura', []],
		'mobile' => ['movil', 'DAIRY Mobile (PWA)', '/m', 'App por rol (empleado, supervisor, gerente): Inicio, Tareas, Escanear, Operaciones, Perfil; cola sin conexión', []],
		'products' => ['maestros', 'Productos', '/products', 'Ficha de producto, códigos de barras, unidades y grupos', ['/product/{productId}', '/productgroups', '/productgroup/{productGroupId}', '/quantityunits', '/quantityunit/{quantityunitId}', '/productbarcodes/{productBarcodeId}']],
		'locations' => ['maestros', 'Ubicaciones', '/locations', 'Almacenes, zonas y pasillos (jerarquía y clase de almacenamiento)', ['/location/{locationId}']],
		'shoppinglocations' => ['maestros', 'Proveedores (maestro Grocy)', '/shoppinglocations', 'Tabla base de proveedores usada por compras', ['/shoppinglocation/{shoppingLocationId}']],
		'users' => ['admin', 'Usuarios y permisos', '/users', 'Usuarios, permisos por módulo y sesiones', ['/user/{userId}', '/user/{userId}/permissions', '/user/{userId}/sessions', '/usersettings']],
		'apikeys' => ['admin', 'Claves API', '/manageapikeys', 'Claves para integraciones y dispositivos', ['/manageapikeys/new', '/api']],
		'userfields' => ['admin', 'Campos y entidades personalizadas', '/userfields', 'Extensión de esquema sin código', ['/userentities', '/userfield/{userfieldId}', '/userentity/{userentityId}']],
		'about' => ['admin', 'Acerca de / versión', '/about', 'Versión y registro de cambios', []],
		'recipes' => ['heredado', 'Recetas y plan de comidas', '/recipes', 'Funcionalidad de hogar heredada de Grocy', ['/mealplan', '/recipe/{recipeId}', '/mealplansections']],
		'chores' => ['heredado', 'Quehaceres (Grocy)', '/choresoverview', 'Tareas periódicas de Grocy; DAIRY usa Piso de Tienda', ['/chores', '/choretracking', '/choresjournal']],
		'batteries' => ['heredado', 'Baterías (Grocy)', '/batteriesoverview', 'Ciclos de carga de baterías', ['/batteries', '/batterytracking', '/batteriesjournal']],
		'tasks' => ['heredado', 'Tareas (Grocy)', '/tasks', 'Lista de tareas de Grocy', ['/taskcategories']],
		'equipment' => ['heredado', 'Equipos', '/equipment', 'Equipos con manual (frío, básculas)', []],
		'calendar' => ['heredado', 'Calendario', '/calendar', 'Calendario iCal de Grocy', []],
		'shoppinglist' => ['heredado', 'Lista de compras (Grocy)', '/shoppinglist', 'Lista de compras de Grocy (la compra DAIRY va por MRP/Proveedores)', ['/shoppinglistsettings']],
	];

	/**
	 * Conexiones operativas. Cada una se COMPRUEBA buscando el patrón (regex) en el archivo:
	 * encontrado = verificada (estática, con archivo:línea); no encontrado = no verificada.
	 * [desde, hacia, etiqueta, archivo, patrón]
	 */
	const CONNECTIONS = [
		['pos', 'sales', 'La venta del POS se guarda en sales / sale_items', 'services/SalesService.php', 'DB->sales\(\)->createRow'],
		['sales', 'stockoverview', 'Cada venta descuenta stock lote a lote en orden FEFO', 'services/SalesService.php', '\$stockService->ConsumeProduct\('],
		['waste_report', 'waste_approvals', 'La merma queda pendiente de aprobación según política', 'services/WasteService.php', "requires_approval"],
		['waste_approvals', 'stockoverview', 'Aprobar descuenta stock (transacción de consumo)', 'services/WasteService.php', '\$stockService->ConsumeProduct\('],
		['stockoverview', 'kardex', 'Kardex valoriza cada movimiento de stock_log', 'services/KardexService.php', 'FROM stock_log'],
		['waste_approvals', 'workforce', 'Merma aprobada dispara reglas de automatización (tareas)', 'services/WasteService.php', "TriggerEvent\('waste_approved'"],
		['sales', 'mrp', 'Venta media diaria (VMD) para sugerir la compra', 'services/MrpService.php', 'vmd_daily_sales'],
		['mrp', 'suppliers', 'Recomendaciones de compra usan el cálculo MRP', 'services/SupplierProcurementService.php', 'MrpService::GetInstance\(\)->CalculateMrp'],
		['mrp', 'stockoverview', 'Recepción de OC suma stock (compra con lote y vencimiento)', 'services/MrpService.php', '\$stockService->AddProduct\('],
		['mrp', 'traceability', 'La recepción registra lote/serie para trazabilidad', 'services/MrpService.php', 'TraceabilityService'],
		['sales', 'schedule', 'Ventas reales → pronóstico de dotación (Personal & Turnos)', 'services/LaborForecastService.php', 'FROM sales WHERE branch_id'],
		['mrp', 'schedule', 'OC por recibir = horas de recepción en el pronóstico laboral', 'services/LaborForecastService.php', 'FROM procurement_orders'],
		['audits', 'workforce', 'Hallazgo de auditoría crea tarea correctiva', 'services/StoreAuditService.php', 'INSERT INTO workforce_store_tasks'],
		['audits', 'alerts', 'Auditoría reprobada abre alerta', 'services/AlertCenterService.php', "case 'audit_failed'"],
		['inventory_control', 'alerts', 'Stock fantasma / anomalías generan alertas', 'services/AlertCenterService.php', 'LossPreventionService::GetInstance'],
		['alerts', 'mobile', 'Alerta → tarea asignada en DAIRY Mobile', 'services/MobileWorkService.php', 'function TaskFromAlert'],
		['sales', 'kpis', 'Ventas y margen en el Panel Ejecutivo', 'services/KpiService.php', 'FROM sales'],
		['waste', 'kpis', 'Merma en el Panel Ejecutivo', 'services/KpiService.php', 'waste_records'],
		['sales', 'cost_centers', 'Margen por sucursal y categoría', 'services/CostCenterService.php', 'FROM sale_items'],
		['waste', 'cost_centers', 'Merma por centro de costo', 'services/CostCenterService.php', 'FROM waste_records'],
		['recalls', 'stockoverview', 'Retiro de lote bloquea stock en cuarentena', 'services/RecallService.php', 'RecallService'],
		['transfers', 'stockoverview', 'Traslados mueven stock entre sucursales', 'services/StockTransferService.php', 'StockService::GetInstance'],
		['orders', 'sales', 'Despacho del pedido se registra como venta', 'services/CustomerOrderService.php', 'SalesService'],
		['orders', 'scan', 'Picking por escáner con FEFO', 'services/CustomerOrderService.php', 'ScanService'],
		['scan', 'mrp', 'Recepción por escáner contra OC', 'services/ScanService.php', 'MrpService'],
		['scan', 'inventory', 'Conteo guiado por escáner', 'services/ScanService.php', 'CycleCountService'],
		['traceability', 'kardex', 'Trazabilidad usa el kardex del lote', 'services/TraceabilityService.php', 'KardexService'],
		['workforce', 'waste', 'Merma exprés desde Piso de Tienda', 'services/WorkforceService.php', 'WasteService'],
		['today', 'alerts', 'Hoy reúne decisiones pendientes (agenda del gerente)', 'controllers/KpiController.php', 'ManagerAgendaService'],
		['data_import', 'products', 'Importación CSV de maestros', 'services/ImportService.php', 'INSERT INTO products'],
		['branches', 'stockoverview', 'Toda petición se acota a las sucursales del usuario', 'middleware/DairyPermissionMiddleware.php', 'function ScopeToBranches'],
		['mobile', 'schedule', 'Mi horario, fichaje y ausencias en móvil', 'services/MobileWorkService.php', 'WorkforceScheduleService'],
		['mobile', 'waste_report', 'Merma desde móvil (escáner y foto)', 'public/js/dairy_m.js', "waste/report"],
		// Etapa 30: conexiones que ya existían en el código y no estaban catalogadas (alimentan también «Módulos conectados»)
		['inventory', 'kardex', 'El conteo físico ajusta stock con transacción de inventario', 'controllers/Api/StockApiController.php', 'StockService::GetInstance\(\)->InventoryProduct'],
		['sales', 'reports', 'Ventas y margen por sucursal en Reportes', 'controllers/KpiController.php', 'GetBranchScorecards'],
		['waste', 'reports', 'Desglose de merma (censo) en Reportes', 'controllers/KpiController.php', 'GetCensoShrinkBreakdown'],
		['stockoverview', 'locations_map', 'Ocupación, valor y vencimientos por ubicación', 'controllers/OperationsController.php', 'AS expiring'],
	];

	/**
	 * Propuestas: NO implementadas. Se muestran en morado. Si el patrón "si_existe" aparece en el archivo,
	 * el analizador avisa que podría estar implementada y conviene revisarla (no la marca como hecha).
	 * [id, desde, hacia, título, fuente, archivo, patrón si_existe]
	 */
	const PROPOSALS = [
		['p_forecast_mrp', 'schedule', 'mrp', 'Usar el pronóstico de ventas (no solo la VMD) en la sugerencia de compra', 'Prompt del mapa: «Ventas → Pronóstico → MRP»; MrpService usa vmd_daily_sales', 'services/MrpService.php', 'LaborForecastService'],
		['p_push', 'alerts', 'mobile', 'Notificaciones push con la app cerrada (Web Push + VAPID)', 'DAIRY_AUDIT.md: matriz «Notificaciones push — Bloqueado»', 'public/js/dairy_m.js', 'pushManager\.subscribe'],
		['p_backup', 'users', 'stockoverview', 'Respaldo y restauración desde la aplicación', 'DAIRY_AUDIT.md: matriz «Respaldo y restauración — Bloqueado»', 'controllers/SystemController.php', 'function Backup'],
		['p_payroll', 'schedule', 'cost_centers', 'Sincronizar horas con nómina PAYCOM', 'DAIRY_AUDIT.md Etapa 29: conector inactivo sin credenciales', 'services/LaborForecastService.php', 'curl_exec'],
		['p_ap', 'suppliers', 'cost_centers', 'Cuentas por pagar y pagos a proveedores', 'DAIRY_AUDIT.md Etapa 15: pendiente', 'services/SupplierInvoiceService.php', 'function RegisterPayment'],
		['p_promos', 'pos', 'sales', 'Promociones generales por período/cliente', 'DAIRY_AUDIT.md Etapa 15: pendiente', 'services/SalesService.php', 'promotion'],
		['p_waves', 'orders', 'scan', 'Olas de picking multi-pedido y guía de despacho imprimible', 'DAIRY_AUDIT.md Etapa 22: pendiente', 'services/CustomerOrderService.php', 'wave'],
		['p_cov_mobile', 'schedule', 'mobile', 'Avisar en /m las solicitudes de cobertura abiertas', 'DAIRY_AUDIT.md Etapa 29: pendiente P2', 'services/MobileWorkService.php', 'labor_coverage_requests'],
		['p_adj_web', 'schedule', 'schedule', 'Decidir correcciones de fichaje desde la web /schedule', 'DAIRY_AUDIT.md Etapa 25: pendiente P2 (hoy móvil y API)', 'public/viewjs/schedule.js', 'adjustments/'],
	];

	/**
	 * Hallazgos documentados y aún abiertos (DAIRY_AUDIT.md). Se re-comprueban: [archivo, patrón, debe_existir]
	 * — si la condición ya no se cumple, el hallazgo se marca «posiblemente resuelto».
	 * [id, prioridad, tipo, módulo, título, evidencia doc, recomendación, archivo, patrón, debe_existir]
	 */
	const KNOWN_ISSUES = [
		['k_objects_branch', 'P3', 'Permisos insuficientes', 'stockoverview', '/api/objects/stock* no filtra por sucursal (exige STOCK)', 'DAIRY_AUDIT.md §Etapa 27 (matriz: Aislamiento multisucursal)', 'Filtrar por user_branches en GenericEntityApiController para tablas de stock', 'controllers/Api/GenericEntityApiController.php', 'AllowedBranches', false],
		['k_demo_seeds', 'P2', 'Datos simulados', 'branches', 'Semillas demo en migraciones sin condicionar a MODE=demo: una instalación nueva nace con datos ficticios', 'DAIRY_DATOS_REALES.md §5 y tabla de huecos', 'Condicionar las inserciones de 0258–0262, 0264, 0268, 0269 a GROCY_MODE === demo (migración nueva, sin editar las aplicadas)', 'migrations/0258.php', 'GROCY_MODE', false],
		['k_backup', 'P1', 'Funcionalidad ausente', 'users', 'No existe respaldo/restauración en la app (depende de copiar data/)', 'DAIRY_AUDIT.md matriz: «Respaldo y restauración — Bloqueado»', 'Documentar y probar procedimiento de respaldo; luego exponerlo a ADMIN', 'controllers/SystemController.php', 'function Backup', false],
		['k_push', 'P2', 'Web presente / móvil ausente', 'mobile', 'Sin push con la app cerrada (requiere claves VAPID y servidor de envío)', 'DAIRY_AUDIT.md matriz: «Notificaciones push — Bloqueado»', 'Configurar VAPID solo si negocio lo exige', 'public/js/dairy_m.js', 'pushManager\.subscribe', false],
		['k_cov_mobile', 'P2', 'Web presente / móvil ausente', 'schedule', 'Solicitudes de cobertura abiertas no se avisan en /m', 'DAIRY_AUDIT.md Etapa 29 «Pendiente P2»', 'Agregar tarjeta en /m (MobileWorkService::Today)', 'services/MobileWorkService.php', 'labor_coverage_requests', false],
		['k_adj_web', 'P2', 'Móvil presente / web ausente', 'schedule', 'Decidir correcciones de fichaje solo desde móvil/API, no desde /schedule', 'DAIRY_AUDIT.md Etapa 25 «Pendientes»', 'Botón aprobar/rechazar en la pestaña de asistencia', 'public/viewjs/schedule.js', 'adjustments/', false],
		['k_payroll', 'P3', 'Integración incompleta', 'schedule', 'Conector de nómina PAYCOM inactivo (sin credenciales ni documentación de API)', 'DAIRY_AUDIT.md Etapa 29', 'Validar API del proveedor antes de sincronizar', 'services/LaborForecastService.php', 'PayrollConnectorStatus', true],
		['k_offline_photos', 'P3', 'Web presente / móvil ausente', 'mobile', 'Sin conexión no se encolan fotos ni fichaje', 'DAIRY_AUDIT.md matriz: «Offline y sincronización»', 'Encolar fotos en IndexedDB con la misma clave de idempotencia', 'public/js/dairy_m.js', 'indexedDB', false],
		['k_ui_untested', 'P3', 'Verificación pendiente', 'pos', 'POS: idempotencia verificada por API; UI no re-probada en navegador tras el cambio', 'DAIRY_AUDIT.md matriz: «Ventas POS — Aprobado con observaciones»', 'Prueba E2E del POS en navegador', 'views/pos.blade.php', 'X-Idempotency-Key', true],
		['k_orders_conc', 'P3', 'Verificación pendiente', 'orders', 'Pedidos y picking sin prueba HTTP concurrente', 'DAIRY_AUDIT.md matriz: «Pedidos y picking»', 'Agregar caso a http_reliability.php', 'tests/dairy/http_reliability.php', '/api/orders', false],
		['k_counts_conc', 'P3', 'Verificación pendiente', 'inventory', 'Conteos y ajustes sin prueba HTTP concurrente', 'DAIRY_AUDIT.md matriz: «Conteos y ajustes»', 'Agregar caso a http_reliability.php', 'tests/dairy/http_reliability.php', 'cycle-counts', false],
	];

	// Capas de arquitectura (los conteos y archivos se calculan)
	const ARCHITECTURE = [
		'frontend' => ['Frontend', 'Blade (views/*.blade.php) + jQuery/Bootstrap 4 + JS por vista (public/viewjs) + componentes DAIRY (public/js/dairy_*.js, public/css/dairy_enterprise_theme.css)'],
		'backend' => ['Backend', 'PHP 8 + Slim 4: controladores de página (controllers/*.php), API (controllers/Api/*.php) y servicios singleton (services/*.php)'],
		'database' => ['Base de datos', 'SQLite (data/grocy*.db) con migraciones numeradas (migrations/*.php|sql) que corren al visitar /'],
		'apis' => ['APIs', 'REST bajo /api (Grocy + DAIRY); especificación OpenAPI heredada en grocy.openapi.json'],
		'auth' => ['Autenticación', 'Sesión por cookie, clave API (cabecera GROCY-API-KEY), proxy inverso o LDAP; MODE=demo inicia sesión como usuario 1'],
		'permissions' => ['Roles y permisos', 'Permisos por usuario (user_permissions); páginas DAIRY protegidas por DairyAccess::PAGES (middleware) y APIs por permiso + sucursal (BaseApiController::DairyRead). Rol mostrado en /m: gerente/supervisor/empleado derivado de permisos'],
		'branches' => ['Multisucursal', 'user_branches + DairyPermissionMiddleware acota branch_id en ruta, query y cuerpo'],
		'integrations' => ['Integraciones', 'Lectores de código (teclado/cámara, GS1), impresión térmica, CSV, conector PAYCOM (inactivo)'],
		'mobile' => ['Aplicación móvil', 'PWA /m (public/js/dairy_m.js, service worker, cola offline con idempotencia); sin app nativa por decisión'],
		'shared' => ['Servicios compartidos', 'DatabaseService (transacciones/bloqueos), DairyAccess, DairyChangeLog (triggers de historial), AutomationEngineService, DocumentService'],
		'tests' => ['Pruebas', 'tests/dairy: run.php (unitarias sobre copia de BD), http_sweep.php (todas las rutas GET), http_reliability.php (concurrencia), http_labor_forecast.php'],
	];
}
