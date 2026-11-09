<?php

namespace Grocy\Helpers;

/**
 * Contenido comercial del sitio público de DAILY (ES/EN).
 * Solo describe funciones que existen en la plataforma; ver docs/estrategia/21_pagina_comercial/ESTADO_FUNCIONES.md.
 * Precios: dejar 'price' en null hasta que exista una definición comercial.
 */
class DailySiteContent
{
	public static function Lang(?string $requested, ?string $cookie): string
	{
		$lang = $requested ?: $cookie ?: 'es';
		return in_array($lang, ['es', 'en'], true) ? $lang : 'es';
	}

	public static function Get(string $lang): array
	{
		$t = fn (string $es, string $en) => $lang === 'en' ? $en : $es;

		$modules = [
			'sales' => [
				'icon' => 'chart', 'tone' => 'green', 'img' => 'sales', 'preview' => 'sales', 'gallery' => ['sales', 'reports', 'pos'],
				'name' => $t('Ventas y Rentabilidad', 'Sales & Profitability'),
				'tagline' => $t('Mira cómo vende cada tienda, hora por hora, y cuánto deja realmente.', 'See how each store sells, hour by hour, and what it really earns.'),
				'summary' => $t('Ventas del día, metas, margen y desempeño por departamento con datos del punto de venta.', 'Daily sales, targets, margin and department performance from point-of-sale data.'),
				'features' => [
					[$t('Ventas por hora', 'Hourly sales'), $t('Curva del día contra el histórico para detectar caídas a tiempo.', 'Today\'s curve against history to spot drops in time.')],
					[$t('Ventas acumuladas', 'Running totals'), $t('Acumulado del día, la semana y el mes por sucursal.', 'Day, week and month to date by branch.')],
					[$t('Cumplimiento de metas', 'Target attainment'), $t('Presupuesto contra venta real con semáforo.', 'Budget versus actual with traffic-light status.')],
					[$t('Margen y utilidad', 'Margin & profit'), $t('Costo, margen bruto y utilidad por producto y categoría.', 'Cost, gross margin and profit by product and category.')],
					[$t('Ventas por producto', 'Sales by product'), $t('Ranking de productos y participación en la venta.', 'Product ranking and share of sales.')],
					[$t('Comparaciones históricas', 'Historical comparisons'), $t('Mismo día de la semana anterior y periodos equivalentes.', 'Same weekday last week and equivalent periods.')],
					[$t('Productividad comercial', 'Commercial productivity'), $t('Venta por hora trabajada y por colaborador.', 'Sales per labor hour and per employee.')],
					[$t('Análisis por departamento', 'Department analysis'), $t('Desempeño y participación de cada departamento.', 'Performance and share of every department.')],
				],
				'benefits' => [
					$t('Una sola cifra de ventas para gerencia y tienda.', 'One sales number for head office and stores.'),
					$t('Margen visible junto a la venta, no al cierre del mes.', 'Margin next to sales, not at month end.'),
					$t('Punto de venta con devoluciones integrado.', 'Built-in point of sale with returns.'),
				],
			],
			'inventory' => [
				'icon' => 'box', 'tone' => 'teal', 'img' => 'stock', 'preview' => 'inventory', 'gallery' => ['stock', 'kardex', 'scan', 'transfers', 'locations', 'reconciliation'],
				'name' => $t('Inventario, Almacenes y Bodegas', 'Inventory & Warehousing'),
				'tagline' => $t('Cada unidad, en cada ubicación, con su historia completa.', 'Every unit, in every location, with its full history.'),
				'summary' => $t('Existencias, Kardex valorizado, conteos con escáner, traslados, ubicaciones y conciliación.', 'Stock levels, valued ledger, scanner counts, transfers, locations and reconciliation.'),
				'features' => [
					[$t('Existencias', 'Stock levels'), $t('Inventario por producto, lote, vencimiento y sucursal.', 'Stock by product, lot, expiry and branch.')],
					[$t('Kardex', 'Stock ledger'), $t('Entradas, salidas y saldo valorizado de cada producto.', 'Ins, outs and valued balance for every product.')],
					[$t('Conteos físicos', 'Physical counts'), $t('Conteos cíclicos y totales con diferencias valorizadas.', 'Cycle and full counts with valued differences.')],
					[$t('Escáner UPC', 'UPC scanner'), $t('Lectura de códigos desde PDA, móvil o lector USB.', 'Barcode reading from PDA, phone or USB reader.')],
					[$t('Traslados', 'Transfers'), $t('Envíos entre sucursales y bodegas con recepción.', 'Shipments between branches and warehouses with receipt.')],
					[$t('Control de ubicaciones', 'Location control'), $t('Mapa de ubicaciones y etiquetas por zona.', 'Location map and labels by zone.')],
					[$t('Conciliación', 'Reconciliation'), $t('Sistema contra físico, producto por producto.', 'System versus physical, product by product.')],
					[$t('Rotación de inventario', 'Inventory turnover'), $t('Días de inventario y productos estancados.', 'Days on hand and stagnant products.')],
					[$t('Control de mercancías', 'Merchandise control'), $t('Trazabilidad de movimientos y libro de mercancía.', 'Movement traceability and merchandise book.')],
					[$t('Análisis Pareto', 'Pareto analysis'), $t('Clasificación ABC/XYZ para priorizar el control.', 'ABC/XYZ classification to focus control.')],
				],
				'benefits' => [
					$t('Kardex verificable movimiento a movimiento.', 'A ledger you can verify movement by movement.'),
					$t('Conteos más rápidos con PDA y escáner.', 'Faster counts with PDA and scanner.'),
					$t('Detección de inventario fantasma y estancado.', 'Detection of phantom and stagnant stock.'),
				],
			],
			'shrink' => [
				'icon' => 'drop', 'tone' => 'amber', 'img' => 'waste', 'preview' => 'shrink', 'gallery' => ['waste', 'causes', 'investigations', 'approvals', 'loss_exec'],
				'name' => $t('Mermas y Prevención', 'Shrink Control'),
				'tagline' => $t('Sabe cuánto se pierde, por qué y quién lo está resolviendo.', 'Know how much is lost, why, and who is fixing it.'),
				'summary' => $t('Registro de mermas con causal, aprobaciones, investigación, Pareto y planes de acción.', 'Shrink logging by cause, approvals, investigation, Pareto and action plans.'),
				'features' => [
					[$t('Registro de mermas', 'Shrink logging'), $t('Captura desde tienda o móvil con foto y causal.', 'Capture from store or phone with photo and cause.')],
					[$t('Matriz de causales', 'Cause matrix'), $t('Vencimiento, daño, robo, error administrativo y más.', 'Expiry, damage, theft, admin error and more.')],
					[$t('Investigación de pérdidas', 'Loss investigation'), $t('Expedientes con responsables, hallazgos y cierre.', 'Case files with owners, findings and closure.')],
					[$t('Control de ajustes', 'Adjustment control'), $t('Bandeja de aprobación antes de afectar el inventario.', 'Approval queue before stock is affected.')],
					[$t('Recuperación financiera', 'Financial recovery'), $t('Valor recuperado por rebajas y reclamos a proveedor.', 'Value recovered through markdowns and supplier claims.')],
					[$t('Planes de acción', 'Action plans'), $t('Tareas preventivas con dueño y fecha.', 'Preventive tasks with owner and due date.')],
					[$t('Análisis Pareto', 'Pareto analysis'), $t('Los pocos productos que concentran la pérdida.', 'The few products that concentrate the loss.')],
					[$t('Alertas operativas', 'Operational alerts'), $t('Vencimientos próximos, FEFO y desviaciones.', 'Upcoming expiries, FEFO and deviations.')],
				],
				'benefits' => [
					$t('Merma con causa y responsable, no solo un monto.', 'Shrink with a cause and an owner, not just an amount.'),
					$t('Informe ejecutivo de pérdidas listo para gerencia.', 'Executive loss report ready for management.'),
					$t('Rebajas por vencimiento antes de botar producto.', 'Expiry markdowns before product is thrown away.'),
				],
			],
			'loss-prevention' => [
				'icon' => 'shield', 'tone' => 'dark', 'img' => 'lp', 'preview' => 'loss-prevention', 'gallery' => ['lp', 'devices', 'custody', 'anomalies'],
				'name' => $t('Prevención de Pérdidas', 'Loss Prevention'),
				'tagline' => $t('Activos sensibles bajo control, con trazabilidad y evidencia.', 'Sensitive assets under control, with traceability and evidence.'),
				'summary' => $t('Control de celulares por IMEI, conteos sensibles, cadena de custodia, casos y anomalías.', 'Phone control by IMEI, sensitive counts, chain of custody, cases and anomalies.'),
				'features' => [
					[$t('Control de activos', 'Asset control'), $t('Productos de alto valor identificados uno a uno.', 'High-value items identified one by one.')],
					[$t('Control de celulares por IMEI', 'Phone control by IMEI'), $t('Ingreso, ubicación y salida de cada equipo.', 'Receipt, location and exit of each device.')],
					[$t('Investigaciones', 'Investigations'), $t('Casos con línea de tiempo, involucrados y estado.', 'Cases with timeline, people involved and status.')],
					[$t('CCTV y evidencias', 'CCTV & evidence'), $t('Referencia a grabaciones y adjuntos en el expediente.', 'Footage references and attachments in the case file.')],
					[$t('Accesos y autorizaciones', 'Access & approvals'), $t('Permisos por rol y sucursal; ajustes con aprobación.', 'Role and branch permissions; approved adjustments.')],
					[$t('Conteos sensibles', 'Sensitive counts'), $t('Conteos frecuentes de las categorías de riesgo.', 'Frequent counts of high-risk categories.')],
					[$t('Trazabilidad', 'Traceability'), $t('Cadena de custodia de cada movimiento.', 'Chain of custody for every movement.')],
					[$t('Resolución de incidentes', 'Incident resolution'), $t('Cierre documentado con resultado y recuperación.', 'Documented closure with outcome and recovery.')],
				],
				'benefits' => [
					$t('Cada equipo sensible tiene historia verificable.', 'Every sensitive item has a verifiable history.'),
					$t('Anomalías detectadas por reglas, no por casualidad.', 'Anomalies found by rules, not by chance.'),
					$t('Registro de auditoría de quién hizo qué.', 'Audit log of who did what.'),
				],
			],
			'dispatch' => [
				'icon' => 'truck', 'tone' => 'lime', 'img' => 'dispatch', 'preview' => 'dispatch', 'gallery' => ['dispatch', 'transfers'],
				'name' => $t('Despachos y Devoluciones', 'Dispatch & Returns'),
				'tagline' => $t('Del pedido a la entrega, y de vuelta, sin perder mercancía.', 'From order to delivery and back, without losing goods.'),
				'summary' => $t('Pedidos, preparación, salida validada por escáner, seguimiento y devoluciones.', 'Orders, picking, scanner-validated exit, tracking and returns.'),
				'features' => [
					[$t('Preparación de pedidos', 'Order picking'), $t('Lista de alistamiento por pedido y ubicación.', 'Picking list by order and location.')],
					[$t('Control de salidas', 'Exit control'), $t('Validación por escáner de lo que sale.', 'Scanner validation of what leaves.')],
					[$t('Seguimiento de despachos', 'Dispatch tracking'), $t('Estado de cada despacho y tiempos de ciclo.', 'Status of every dispatch and cycle times.')],
					[$t('Recepción de devoluciones', 'Returns intake'), $t('Devoluciones de clientes y del punto de venta.', 'Customer and point-of-sale returns.')],
					[$t('Conciliación de mercancía', 'Goods reconciliation'), $t('Lo despachado contra lo pedido y lo recibido.', 'Dispatched versus ordered and received.')],
					[$t('Gestión de novedades', 'Exception handling'), $t('Faltantes, sobrantes y diferencias con seguimiento.', 'Shortages, overages and differences with follow-up.')],
				],
				'benefits' => [
					$t('Menos errores de alistamiento.', 'Fewer picking errors.'),
					$t('Devoluciones que regresan al inventario correcto.', 'Returns that go back to the right stock.'),
					$t('Indicadores de cumplimiento de despacho.', 'Dispatch fulfilment indicators.'),
				],
			],
			'workforce' => [
				'icon' => 'people', 'tone' => 'green', 'img' => 'scheduling', 'preview' => 'workforce', 'gallery' => ['scheduling', 'workforce', 'attendance'],
				'name' => $t('Recursos Humanos y Operaciones', 'Workforce & Operations'),
				'tagline' => $t('La gente correcta, en la hora correcta, haciendo lo que importa.', 'The right people, at the right time, doing what matters.'),
				'summary' => $t('Turnos según la demanda, cobertura, asistencia, tareas y solicitudes del personal.', 'Demand-based shifts, coverage, attendance, tasks and staff requests.'),
				'features' => [
					[$t('Programación de turnos', 'Shift scheduling'), $t('Horarios semanales y mensuales con reglas laborales.', 'Weekly and monthly rosters with labor rules.')],
					[$t('Cobertura laboral', 'Labor coverage'), $t('Personal programado contra la demanda por hora.', 'Scheduled staff versus hourly demand.')],
					[$t('Control de asistencia', 'Attendance'), $t('Marcaciones, atrasos y ausencias del día.', 'Clock-ins, late arrivals and absences.')],
					[$t('Productividad por empleado', 'Productivity per employee'), $t('Venta y tareas por hora trabajada.', 'Sales and tasks per hour worked.')],
					[$t('Distribución de personal', 'Staff distribution'), $t('Personal por departamento y sucursal.', 'Staff by department and branch.')],
					[$t('Tareas operativas', 'Operational tasks'), $t('Tareas asignadas y seguimiento desde el móvil.', 'Assigned tasks tracked from the phone.')],
					[$t('Incidencias', 'Incidents'), $t('Novedades de asistencia y de operación.', 'Attendance and operational issues.')],
					[$t('Solicitudes', 'Requests'), $t('Vacaciones, cambios y turnos abiertos.', 'Time off, swaps and open shifts.')],
				],
				'benefits' => [
					$t('Turnos generados según la demanda real.', 'Shifts generated from real demand.'),
					$t('Validación de reglas laborales antes de publicar.', 'Labor rules validated before publishing.'),
					$t('App móvil para el colaborador.', 'Mobile app for employees.'),
				],
			],
			'planning' => [
				'icon' => 'cart', 'tone' => 'teal', 'img' => 'mrp', 'gallery' => ['mrp', 'suppliers'],
				'name' => $t('Planeación y Compras', 'Planning & Purchasing'),
				'tagline' => $t('Compra lo que se va a vender, cuando se necesita.', 'Buy what will sell, when it is needed.'),
				'summary' => $t('Necesidades de abastecimiento, sugeridos de compra, proveedores y órdenes.', 'Replenishment needs, purchase suggestions, suppliers and orders.'),
				'features' => [
					[$t('Planeación de demanda', 'Demand planning'), $t('Necesidades calculadas desde consumo y existencias.', 'Needs calculated from consumption and stock.')],
					[$t('Abastecimiento', 'Replenishment'), $t('Recomendaciones de compra por producto.', 'Purchase recommendations by product.')],
					[$t('Proveedores', 'Suppliers'), $t('Ficha, cumplimiento y condiciones de cada proveedor.', 'Profile, fulfilment and terms for each supplier.')],
					[$t('Órdenes de compra', 'Purchase orders'), $t('Órdenes generadas y reglas de recepción.', 'Generated orders and receiving rules.')],
					[$t('Cobertura de inventario', 'Inventory coverage'), $t('Días de cobertura y riesgo de agotado.', 'Days of cover and stock-out risk.')],
					[$t('Escenarios', 'Scenarios'), $t('Escenarios de cobertura laboral según picos de demanda.', 'Labor coverage scenarios based on demand peaks.')],
				],
				'benefits' => [
					$t('Menos agotados y menos sobrestock.', 'Fewer stock-outs and less overstock.'),
					$t('Compras justificadas con datos de consumo.', 'Purchases backed by consumption data.'),
					$t('Recepción con reglas por proveedor.', 'Receiving rules by supplier.'),
				],
			],
			'analytics' => [
				'icon' => 'gauge', 'tone' => 'dark', 'img' => 'today', 'preview' => 'exec', 'gallery' => ['today', 'branches', 'reports', 'alerts'],
				'name' => $t('Indicadores y Analítica', 'KPIs & Analytics'),
				'tagline' => $t('Un tablero que dice qué hacer hoy, no solo qué pasó.', 'A dashboard that says what to do today, not just what happened.'),
				'summary' => $t('Panel ejecutivo HOY, KPIs operativos y financieros, reportes y comparativos por sucursal.', 'TODAY executive panel, operational and financial KPIs, reports and branch comparisons.'),
				'features' => [
					[$t('Panel Ejecutivo', 'Executive panel'), $t('HOY: ventas, merma, agotados y alertas en una pantalla.', 'TODAY: sales, shrink, stock-outs and alerts on one screen.')],
					[$t('KPIs operativos', 'Operational KPIs'), $t('Catálogo de indicadores con definición y cálculo.', 'KPI catalog with definition and formula.')],
					[$t('Indicadores financieros', 'Financial indicators'), $t('Margen, valor de inventario, pérdida y recuperación.', 'Margin, inventory value, loss and recovery.')],
					[$t('Reportes', 'Reports'), $t('Reportes exportables a Excel y CSV.', 'Reports exportable to Excel and CSV.')],
					[$t('Comparativos', 'Comparisons'), $t('Periodo contra periodo y año contra año.', 'Period over period and year over year.')],
					[$t('Análisis por sucursal', 'Branch analysis'), $t('Ranking y comparación entre sucursales.', 'Ranking and comparison across branches.')],
				],
				'benefits' => [
					$t('Todos miran el mismo número.', 'Everyone looks at the same number.'),
					$t('Alertas que llegan al responsable.', 'Alerts that reach the owner.'),
					$t('Exportación para auditoría y contabilidad.', 'Exports for audit and accounting.'),
				],
			],
		];

		$industries = [
			'supermarkets' => [
				'icon' => 'cart', 'img' => 'waste', 'modules' => ['shrink', 'inventory', 'sales', 'workforce'],
				'name' => $t('Supermercados', 'Supermarkets'),
				'lead' => $t('Perecederos que vencen, agotados en góndola y turnos que no calzan con las horas pico.', 'Perishables that expire, empty shelves and shifts that miss the peak hours.'),
				'problems' => [
					[$t('Merma de perecederos', 'Perishable shrink'), $t('Alertas de vencimiento, FEFO y rebajas antes de perder el producto.', 'Expiry alerts, FEFO and markdowns before the product is lost.')],
					[$t('Agotados', 'Stock-outs'), $t('Detección de productos sin existencia y cobertura en días.', 'Detection of empty items and days of cover.')],
					[$t('Personal en horas pico', 'Peak-hour staffing'), $t('Turnos generados según la venta por hora.', 'Shifts generated from hourly sales.')],
				],
			],
			'retail' => [
				'icon' => 'tag', 'img' => 'sales', 'modules' => ['sales', 'inventory', 'analytics', 'shrink'],
				'name' => $t('Retail', 'Retail'),
				'lead' => $t('Muchas referencias, márgenes estrechos y diferencias de inventario que nadie explica.', 'Many SKUs, thin margins and stock differences nobody can explain.'),
				'problems' => [
					[$t('Diferencias de inventario', 'Stock differences'), $t('Conciliación y Kardex para encontrar dónde se rompe el saldo.', 'Reconciliation and ledger to find where the balance breaks.')],
					[$t('Margen invisible', 'Invisible margin'), $t('Margen por producto y departamento junto a la venta.', 'Margin by product and department next to sales.')],
					[$t('Decisiones tardías', 'Late decisions'), $t('Panel diario con alertas para actuar el mismo día.', 'Daily panel with alerts to act the same day.')],
				],
			],
			'electronics' => [
				'icon' => 'phone', 'img' => 'devices', 'modules' => ['loss-prevention', 'inventory', 'dispatch', 'sales'],
				'name' => $t('Electrónica', 'Electronics'),
				'lead' => $t('Productos de alto valor donde una unidad perdida pesa en el mes.', 'High-value items where one lost unit hurts the month.'),
				'problems' => [
					[$t('Equipos sin rastro', 'Untracked devices'), $t('Control de celulares por IMEI, de la recepción a la venta.', 'Phone control by IMEI, from receipt to sale.')],
					[$t('Conteos sensibles', 'Sensitive counts'), $t('Conteos frecuentes de categorías de riesgo con diferencias valorizadas.', 'Frequent counts of risky categories with valued differences.')],
					[$t('Casos sin cierre', 'Unresolved cases'), $t('Expedientes con evidencia, responsables y recuperación.', 'Case files with evidence, owners and recovery.')],
				],
			],
			'distribution' => [
				'icon' => 'truck', 'img' => 'dispatch', 'modules' => ['dispatch', 'planning', 'inventory', 'analytics'],
				'name' => $t('Distribución', 'Distribution'),
				'lead' => $t('Pedidos que salen incompletos, devoluciones sin conciliar y compras a ojo.', 'Incomplete orders, unreconciled returns and gut-feel purchasing.'),
				'problems' => [
					[$t('Errores de despacho', 'Dispatch errors'), $t('Salida validada por escáner contra el pedido.', 'Scanner-validated exit against the order.')],
					[$t('Devoluciones', 'Returns'), $t('Recepción y conciliación de lo que regresa.', 'Intake and reconciliation of what comes back.')],
					[$t('Abastecimiento', 'Replenishment'), $t('Sugeridos de compra desde el consumo real.', 'Purchase suggestions from real consumption.')],
				],
			],
			'distribution-centers' => [
				'icon' => 'warehouse', 'img' => 'locations', 'modules' => ['inventory', 'dispatch', 'workforce', 'planning'],
				'name' => $t('Centros de distribución', 'Distribution centers'),
				'lead' => $t('Ubicaciones, traslados y personal de bodega coordinados en un solo sistema.', 'Locations, transfers and warehouse staff coordinated in one system.'),
				'problems' => [
					[$t('Ubicaciones desordenadas', 'Messy locations'), $t('Mapa de ubicaciones, etiquetas y conteos por zona.', 'Location map, labels and counts by zone.')],
					[$t('Traslados sin recepción', 'Unconfirmed transfers'), $t('Traslados entre sedes con envío y recepción.', 'Inter-site transfers with shipment and receipt.')],
					[$t('Productividad de bodega', 'Warehouse productivity'), $t('Tareas, turnos y asistencia del personal.', 'Tasks, shifts and staff attendance.')],
				],
			],
			'multi-branch' => [
				'icon' => 'branches', 'img' => 'branches', 'modules' => ['analytics', 'sales', 'shrink', 'workforce'],
				'name' => $t('Cadenas multisucursal', 'Multi-branch chains'),
				'lead' => $t('Cada tienda con su Excel; la gerencia sin una vista común.', 'Every store with its own spreadsheet; head office with no shared view.'),
				'problems' => [
					[$t('Sin vista consolidada', 'No consolidated view'), $t('Comparativo y ranking entre sucursales.', 'Comparison and ranking across branches.')],
					[$t('Accesos por sede', 'Per-branch access'), $t('Roles y permisos limitados a cada sucursal.', 'Roles and permissions scoped to each branch.')],
					[$t('Procesos distintos', 'Inconsistent processes'), $t('El mismo flujo de merma, conteo y despacho en todas.', 'The same shrink, count and dispatch flow everywhere.')],
				],
			],
		];

		$solutions = [
			['icon' => 'drop', 'module' => 'shrink', 'title' => $t('Reducir la merma', 'Reduce shrink'), 'text' => $t('Causa, responsable y plan de acción para cada pérdida.', 'Cause, owner and action plan for every loss.')],
			['icon' => 'box', 'module' => 'inventory', 'title' => $t('Inventario confiable', 'Reliable inventory'), 'text' => $t('Kardex, conteos y conciliación en un mismo flujo.', 'Ledger, counts and reconciliation in one flow.')],
			['icon' => 'shield', 'module' => 'loss-prevention', 'title' => $t('Proteger productos sensibles', 'Protect sensitive items'), 'text' => $t('IMEI, custodia e investigaciones con evidencia.', 'IMEI, custody and investigations with evidence.')],
			['icon' => 'people', 'module' => 'workforce', 'title' => $t('Turnos según la demanda', 'Demand-based shifts'), 'text' => $t('Cobertura por hora y reglas laborales validadas.', 'Hourly coverage and validated labor rules.')],
			['icon' => 'truck', 'module' => 'dispatch', 'title' => $t('Despachar sin errores', 'Error-free dispatch'), 'text' => $t('Salidas validadas y devoluciones conciliadas.', 'Validated exits and reconciled returns.')],
			['icon' => 'gauge', 'module' => 'analytics', 'title' => $t('Gestionar con datos', 'Manage with data'), 'text' => $t('Un panel diario con lo que hay que atender hoy.', 'A daily panel with what needs attention today.')],
		];

		$benefits = [
			['icon' => 'grid', 'title' => $t('Control centralizado', 'Centralized control'), 'text' => $t('Ventas, inventario, merma, personal y despachos en una plataforma.', 'Sales, inventory, shrink, staff and dispatch on one platform.')],
			['icon' => 'eye', 'title' => $t('Visibilidad operativa', 'Operational visibility'), 'text' => $t('Lo que pasa en cada tienda, el mismo día.', 'What happens in every store, the same day.')],
			['icon' => 'drop', 'title' => $t('Reducción potencial de pérdidas', 'Potential loss reduction'), 'text' => $t('Causales, alertas y expedientes para atacar la pérdida.', 'Causes, alerts and cases to tackle loss.')],
			['icon' => 'box', 'title' => $t('Mejor gestión del inventario', 'Better inventory management'), 'text' => $t('Kardex verificable y conteos con escáner.', 'Verifiable ledger and scanner counts.')],
			['icon' => 'people', 'title' => $t('Productividad del personal', 'Staff productivity'), 'text' => $t('Turnos según demanda y tareas medibles.', 'Demand-based shifts and measurable tasks.')],
			['icon' => 'route', 'title' => $t('Trazabilidad de mercancías', 'Goods traceability'), 'text' => $t('Cada movimiento con usuario, fecha y documento.', 'Every movement with user, date and document.')],
			['icon' => 'coin', 'title' => $t('Control financiero', 'Financial control'), 'text' => $t('Margen, valor de inventario y pérdida valorizada.', 'Margin, inventory value and valued loss.')],
			['icon' => 'chart', 'title' => $t('Decisiones basadas en datos', 'Data-driven decisions'), 'text' => $t('Indicadores con definición y cálculo explícitos.', 'KPIs with explicit definition and formula.')],
		];

		$tour = [
			// 'preview' = public/img/site/mod_{preview}_{1400|2400|m}.webp (recortes reales de KPI y gráficos)
			['key' => 'analytics', 'preview' => 'exec', 'href' => '/features/analytics#panel-ejecutivo', 'label' => $t('Panel Ejecutivo', 'Executive panel'),
				'title' => $t('Panel Ejecutivo', 'Executive panel'),
				'text' => $t('Ocho indicadores financieros y comerciales del día, la venta real contra la meta por hora y la participación de cada departamento.', 'Eight financial and commercial KPIs for the day, actual sales against the hourly target and each department\'s share.'),
				'points' => [$t('Ventas netas, margen y utilidad bruta', 'Net sales, gross margin and gross profit'), $t('Cumplimiento de meta y proyección de cierre', 'Target attainment and closing forecast'), $t('Ticket, transacciones y venta por hora laboral', 'Ticket, transactions and sales per labor hour'), $t('Merma sobre ventas frente al objetivo', 'Shrink as % of sales against target')]],
			['key' => 'sales', 'preview' => 'sales', 'label' => $t('Ventas', 'Sales'),
				'title' => $t('Ventas y Rentabilidad', 'Sales & Profitability'),
				'text' => $t('Ventas acumuladas del periodo, cumplimiento, margen y la curva de ventas frente al periodo anterior y a la meta.', 'Period-to-date sales, attainment, margin and the sales curve against the previous period and the target.'),
				'points' => [$t('Venta, utilidad y margen frente al periodo anterior', 'Sales, profit and margin versus the previous period'), $t('Curva por día, hora, semana o mes', 'Curve by day, hour, week or month'), $t('Avance y brecha frente a la meta', 'Progress and gap against the target'), $t('Top de productos por venta y unidades', 'Top products by sales and units')]],
			['key' => 'inventory', 'preview' => 'inventory', 'label' => $t('Inventario', 'Inventory'),
				'title' => $t('Inventario y Bodegas', 'Inventory & Warehousing'),
				'text' => $t('Valor, unidades, rotación y cobertura del inventario, con alertas de agotados, vencimientos y productos bajo mínimo.', 'Inventory value, units, turnover and coverage, with alerts for stock-outs, expiries and items below minimum.'),
				'points' => [$t('Rotación y cobertura en días frente al objetivo', 'Turnover and days of cover against target'), $t('Agotados y productos bajo mínimo', 'Stock-outs and items below minimum'), $t('Valor en riesgo por vencimiento', 'Value at risk from expiry'), $t('Evolución del valor del inventario', 'Inventory value over time')]],
			['key' => 'shrink', 'preview' => 'shrink', 'label' => $t('Mermas', 'Shrink'),
				'title' => $t('Mermas y Prevención', 'Shrink Control'),
				'text' => $t('El impacto financiero de la merma, sus causas y los productos que concentran la pérdida.', 'The financial impact of shrink, its causes and the products that concentrate the loss.'),
				'points' => [$t('Merma total y porcentaje sobre ventas', 'Total shrink and % of sales'), $t('Recuperación financiera y casos pendientes', 'Financial recovery and pending cases'), $t('Top 10 productos con mayor merma', 'Top 10 products by shrink'), $t('Causas principales y tendencia anual', 'Main causes and yearly trend')]],
			['key' => 'dispatch', 'preview' => 'dispatch', 'label' => $t('Despachos', 'Dispatch'),
				'title' => $t('Despachos y Devoluciones', 'Dispatch & Returns'),
				'text' => $t('Del pedido a la conciliación, con indicadores de cumplimiento, picking y devoluciones.', 'From order to reconciliation, with fulfilment, picking and returns indicators.'),
				'points' => [$t('Pedidos pendientes, despachados y atrasados', 'Pending, dispatched and late orders'), $t('Entregas a tiempo (OTIF) y exactitud de picking', 'On-time in-full (OTIF) and picking accuracy'), $t('Conciliación por producto en siete etapas', 'Product reconciliation across seven stages'), $t('Costo y valor recuperable de devoluciones', 'Cost and recoverable value of returns')]],
			['key' => 'workforce', 'preview' => 'workforce', 'label' => $t('Recursos Humanos', 'Workforce'),
				'title' => $t('Recursos Humanos y Operaciones', 'Workforce & Operations'),
				'text' => $t('Asistencia, cobertura y productividad del personal frente a la demanda proyectada.', 'Attendance, coverage and staff productivity against projected demand.'),
				'points' => [$t('Personal programado, presente y ausencias', 'Scheduled and present staff, absences'), $t('Venta por hora laboral por departamento', 'Sales per labor hour by department'), $t('Horas laborales frente a ventas proyectadas', 'Labor hours against projected sales'), $t('Costo laboral frente al presupuesto', 'Labor cost against budget')]],
			['key' => 'loss-prevention', 'preview' => 'loss-prevention', 'label' => $t('Prevención de Pérdidas', 'Loss Prevention'),
				'title' => $t('Prevención de Pérdidas', 'Loss Prevention'),
				'text' => $t('Pérdidas confirmadas, investigaciones abiertas, recuperación y las acciones prioritarias del día con su responsable.', 'Confirmed losses, open investigations, recovery and the day\'s priority actions with their owner.'),
				'points' => [$t('Pérdida confirmada y valor en investigación', 'Confirmed loss and value under investigation'), $t('Recuperación financiera', 'Financial recovery'), $t('Diferencias en investigación por componente', 'Differences under investigation by component'), $t('Prioridades con responsable y fecha límite', 'Priorities with owner and due date')]],
		];

		$plans = [
			['id' => 'essential', 'name' => 'Essential', 'price' => null, 'for' => $t('Una tienda que quiere ordenar inventario y ventas.', 'A single store getting inventory and sales in order.'), 'items' => [$t('Ventas y punto de venta', 'Sales and point of sale'), $t('Inventario y Kardex', 'Inventory and ledger'), $t('Registro de mermas', 'Shrink logging'), $t('App móvil y escáner', 'Mobile app and scanner')]],
			['id' => 'professional', 'name' => 'Professional', 'price' => null, 'for' => $t('Varias tiendas con control de merma y personal.', 'Several stores with shrink and staff control.'), 'items' => [$t('Todo Essential', 'Everything in Essential'), $t('Aprobaciones e investigación de mermas', 'Shrink approvals and investigation'), $t('Turnos y asistencia', 'Shifts and attendance'), $t('Traslados entre sucursales', 'Inter-branch transfers')], 'featured' => true],
			['id' => 'business', 'name' => 'Business', 'price' => null, 'for' => $t('Cadenas con bodega, despachos y compras.', 'Chains with warehouse, dispatch and purchasing.'), 'items' => [$t('Todo Professional', 'Everything in Professional'), $t('Despachos y devoluciones', 'Dispatch and returns'), $t('Planeación y compras', 'Planning and purchasing'), $t('Conciliación y ubicaciones', 'Reconciliation and locations')]],
			['id' => 'enterprise', 'name' => 'Enterprise', 'price' => null, 'for' => $t('Operaciones grandes con prevención de pérdidas.', 'Large operations with loss prevention.'), 'items' => [$t('Todo Business', 'Everything in Business'), $t('Prevención de pérdidas e IMEI', 'Loss prevention and IMEI'), $t('Roles por sucursal y auditoría', 'Branch roles and audit log'), $t('Implementación acompañada', 'Guided implementation')]],
		];

		// Columnas: essential, professional, business, enterprise
		$compare = [
			$t('Ventas, POS y devoluciones', 'Sales, POS and returns') => [1, 1, 1, 1],
			$t('Inventario, Kardex y conteos', 'Inventory, ledger and counts') => [1, 1, 1, 1],
			$t('Escáner UPC y app móvil', 'UPC scanner and mobile app') => [1, 1, 1, 1],
			$t('Registro de mermas', 'Shrink logging') => [1, 1, 1, 1],
			$t('Panel HOY y reportes', 'TODAY panel and reports') => [1, 1, 1, 1],
			$t('Aprobaciones e investigación de mermas', 'Shrink approvals and investigation') => [0, 1, 1, 1],
			$t('Turnos, cobertura y asistencia', 'Shifts, coverage and attendance') => [0, 1, 1, 1],
			$t('Multisucursal y traslados', 'Multi-branch and transfers') => [0, 1, 1, 1],
			$t('Despachos y devoluciones', 'Dispatch and returns') => [0, 0, 1, 1],
			$t('Planeación, compras y proveedores', 'Planning, purchasing and suppliers') => [0, 0, 1, 1],
			$t('Conciliación y mapa de ubicaciones', 'Reconciliation and location map') => [0, 0, 1, 1],
			$t('Prevención de pérdidas e IMEI', 'Loss prevention and IMEI') => [0, 0, 0, 1],
			$t('Roles por sucursal y registro de auditoría', 'Branch roles and audit log') => [0, 0, 0, 1],
			$t('Idioma español e inglés', 'Spanish and English') => [1, 1, 1, 1],
		];

		$faq = [
			[$t('¿Qué es DAILY?', 'What is DAILY?'), $t('Una plataforma web para operar y controlar tiendas: ventas, inventario, mermas, prevención de pérdidas, despachos, personal y compras.', 'A web platform to run and control stores: sales, inventory, shrink, loss prevention, dispatch, staff and purchasing.')],
			[$t('¿Funciona en móvil y PDA?', 'Does it work on phones and PDAs?'), $t('Sí. Tiene una versión móvil para colaboradores con escáner, merma, tareas y turnos.', 'Yes. There is a mobile version for staff with scanner, shrink, tasks and shifts.')],
			[$t('¿Sirve para varias sucursales?', 'Does it support several branches?'), $t('Sí. Cada usuario puede limitarse a sus sucursales y la gerencia ve el consolidado.', 'Yes. Each user can be limited to their branches while management sees the total.')],
			[$t('¿En qué idiomas está?', 'Which languages are available?'), $t('Español e inglés, en la plataforma y en este sitio.', 'Spanish and English, in the platform and on this site.')],
			[$t('¿Puedo cargar mis productos y existencias?', 'Can I load my products and stock?'), $t('Sí, mediante importación de datos desde archivos.', 'Yes, through data import from files.')],
			[$t('¿Cuánto cuesta?', 'How much does it cost?'), $t('Los precios se definen por cotización según sucursales y módulos.', 'Pricing is quoted based on branches and modules.')],
			[$t('¿Cómo es la implementación?', 'What does implementation look like?'), $t('Diagnóstico, carga de datos, configuración de roles y capacitación. Los tiempos se acuerdan en la propuesta.', 'Assessment, data load, role setup and training. Timelines are agreed in the proposal.')],
			[$t('¿Cómo se controlan los accesos?', 'How is access controlled?'), $t('Por rol y sucursal, con registro de auditoría de las acciones.', 'By role and branch, with an audit log of actions.')],
		];

		return [
			'lang' => $lang,
			'modules' => $modules,
			'industries' => $industries,
			'solutions' => $solutions,
			'benefits' => $benefits,
			'tour' => $tour,
			'plans' => $plans,
			'compare' => $compare,
			'faq' => $faq,
			'countries' => ['Argentina', 'Bolivia', 'Chile', 'Colombia', 'Costa Rica', 'Ecuador', 'El Salvador', 'España', 'Estados Unidos', 'Guatemala', 'Honduras', 'México', 'Nicaragua', 'Panamá', 'Paraguay', 'Perú', 'Puerto Rico', 'República Dominicana', 'Uruguay', 'Venezuela', $t('Otro', 'Other')],
		];
	}
}
