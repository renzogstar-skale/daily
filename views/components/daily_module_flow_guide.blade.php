@php
// Guías operativas desactivadas a solicitud del usuario.
return;
@endphp

// Definición completa de los 10 flujos operativos según especificación de DAILY
$allFlows = [
	'planeacion' => [
		'code' => '1',
		'name' => 'Planeación y Compras',
		'icon' => 'fa-cart-flatbed',
		'match_views' => ['mrp', 'suppliers_procurement', 'purchase', 'shoppinglist', 'shoppinglistitemform', 'shoppinglistform'],
		'match_paths' => ['/mrp', '/suppliers', '/purchase', '/shoppinglist'],
		'steps' => [
			['num' => 1, 'title' => 'Analizar demanda', 'desc' => 'Evaluar consumo histórico, picos de venta y estacionalidad proyectada.', 'resp' => 'Analista de Demanda / MRP', 'action' => 'Consultar Planificación MRP', 'url' => '/mrp', 'val' => 'Cálculo de histórico 30/60 días actualizado'],
			['num' => 2, 'title' => 'Revisar inventario disponible', 'desc' => 'Comprobar stock en mano, pedidos en tránsito y stock de seguridad.', 'resp' => 'Supervisor de Inventarios', 'action' => 'Ver Existencias de Red', 'url' => '/stockoverview', 'val' => 'Stock físico sincronizado sin faltantes fantasma'],
			['num' => 3, 'title' => 'Verificar puntos de reorden', 'desc' => 'Comprobar niveles de reposición sugerida frente a stock de seguridad.', 'resp' => 'Analista de Demanda / MRP', 'action' => 'Revisar Sugeridos MRP', 'url' => '/mrp', 'val' => 'Cálculo de ROP y lead time verificado'],
			['num' => 4, 'title' => 'Generar propuesta de compra', 'desc' => 'Consolidar necesidades por sucursal y generar borrador de orden.', 'resp' => 'Comprador / Abastecimiento', 'action' => 'Generar Orden en Proveedores', 'url' => '/suppliers', 'val' => 'Asignación de proveedor preferente y precio pactado'],
			['num' => 5, 'title' => 'Aprobar pedido', 'desc' => 'Validar presupuesto y aplicar doble firma si supera el umbral corporativo.', 'resp' => 'Gerencia de Compras / Finanzas', 'action' => 'Revisar Aprobaciones', 'url' => '/suppliers#tab-orders', 'val' => 'Órdenes > $5,000 exigen visto bueno financiero'],
			['num' => 6, 'title' => 'Enviar al proveedor', 'desc' => 'Emitir orden formal en PDF/EDI con fecha de entrega comprometida (OTIF).', 'resp' => 'Coordinador de Compras', 'action' => 'Emitir y Notificar Orden', 'url' => '/suppliers', 'val' => 'Acuse de recibo y confirmación de despacho del proveedor'],
			['num' => 7, 'title' => 'Dar seguimiento a recepción', 'desc' => 'Monitorear arribo al muelle de descarga y conciliar remisión física.', 'resp' => 'Jefe de Muelle / Recepción WMS', 'action' => 'Ir a Control de Mercancías', 'url' => '/merchandise', 'val' => 'Inspección de bultos y validación de orden de compra']
		]
	],
	'inventario' => [
		'code' => '2',
		'name' => 'Inventario y Almacenes',
		'icon' => 'fa-boxes-stacked',
		'match_views' => ['branches', 'branch_stock', 'merchandise', 'scan', 'location_map', 'stockoverview', 'stockentries', 'stockjournal', 'transfer', 'kardex', 'inventory', 'reconciliation', 'inventory_control'],
		'match_paths' => ['/branches', '/merchandise', '/scan', '/locations/map', '/stockoverview', '/transfers', '/kardex', '/inventory-control', '/inventory', '/reconciliation'],
		'steps' => [
			['num' => 1, 'title' => 'Recibir mercancía', 'desc' => 'Ingreso de remisión de proveedor o manifiesto de traslado entre tiendas.', 'resp' => 'Receptor de Muelle WMS', 'action' => 'Registrar Recepción', 'url' => '/merchandise', 'val' => 'Remisión física cotejada contra Orden de Compra'],
			['num' => 2, 'title' => 'Escanear UPC', 'desc' => 'Lectura por escáner láser de códigos de barras, serie o lote de cada bulto.', 'resp' => 'Operador de Escáner', 'action' => 'Operaciones con Escáner', 'url' => '/scan', 'val' => 'Validación de código registrado en catálogo maestro'],
			['num' => 3, 'title' => 'Validar cantidades', 'desc' => 'Conteo ciego de unidades recibidas vs. packing list del proveedor.', 'resp' => 'Auditor de Entrada', 'action' => 'Verificar Conteos', 'url' => '/inventory', 'val' => 'Tolerancia 0% de diferencia no registrada'],
			['num' => 4, 'title' => 'Registrar ubicación', 'desc' => 'Asignar estiba, pasillo, nivel y posición en el mapa del almacén.', 'resp' => 'Montacarguista / Acomodador', 'action' => 'Asignar en Mapa de Ubicaciones', 'url' => '/locations/map', 'val' => 'Capacidad de peso y volumen verificada'],
			['num' => 5, 'title' => 'Actualizar existencias', 'desc' => 'Impactar el inventario disponible de la sucursal en tiempo real.', 'resp' => 'Supervisor de Almacén', 'action' => 'Comprobar Stock en Vivo', 'url' => '/stockoverview', 'val' => 'Generación de asiento en diario de stock'],
			['num' => 6, 'title' => 'Ejecutar movimientos y traslados', 'desc' => 'Reabastecer piso de venta o transferir a tiendas con quiebre de stock.', 'resp' => 'Coordinador de Traslados', 'action' => 'Traslados entre Tiendas', 'url' => '/transfers', 'val' => 'Remisión de traslado firmada y precinto verificado'],
			['num' => 7, 'title' => 'Realizar conteos físicos', 'desc' => 'Ejecutar inventarios cíclicos y conteos rotativos por estantería.', 'resp' => 'Equipo de Inventario Físico', 'action' => 'Ejecutar Conteo Físico', 'url' => '/inventory', 'val' => 'Bloqueo temporal de movimientos durante conteo'],
			['num' => 8, 'title' => 'Conciliar diferencias', 'desc' => 'Investigar descuadres entre existencias teóricas y conteo físico.', 'resp' => 'Especialista de Conciliación', 'action' => 'Conciliación & Sensible', 'url' => '/reconciliation', 'val' => 'Justificación causal obligatoria (AKCAL)'],
			['num' => 9, 'title' => 'Aprobar ajustes cuando corresponda', 'desc' => 'Autorizar ajuste final en kardex con trazabilidad contable inalterable.', 'resp' => 'Gerencia de Operaciones', 'action' => 'Control de Inventario y Ajustes', 'url' => '/inventory-control', 'val' => 'Firma digital de ajuste y expediente cerrado']
		]
	],
	'ventas' => [
		'code' => '3',
		'name' => 'Ventas y Rentabilidad',
		'icon' => 'fa-cash-register',
		'match_views' => ['sales_history', 'pos', 'orders', 'customers'],
		'match_paths' => ['/sales', '/pos', '/orders', '/customers'],
		'steps' => [
			['num' => 1, 'title' => 'Consultar disponibilidad', 'desc' => 'Verificar existencias y ubicación del producto en góndola o bodega.', 'resp' => 'Vendedor / Asesor', 'action' => 'Consultar Disponibilidad', 'url' => '/stockoverview', 'val' => 'Existencias en mano > 0'],
			['num' => 2, 'title' => 'Registrar venta o pedido', 'desc' => 'Escanear productos en caja o ingresar pedido mayorista/corporativo.', 'resp' => 'Cajero / Operador POS', 'action' => 'Terminal Punto de Venta', 'url' => '/pos', 'val' => 'Precios vigentes y promociones automáticas aplicadas'],
			['num' => 3, 'title' => 'Confirmar transacción', 'desc' => 'Procesar medio de pago (efectivo, tarjeta, crédito) y emitir comprobante fiscal.', 'resp' => 'Cajero / Facturación', 'action' => 'Cobrar y Emitir Ticket', 'url' => '/pos', 'val' => 'Autorización de pasarela de pago aprobada'],
			['num' => 4, 'title' => 'Actualizar inventario', 'desc' => 'Deducción automática de stock respetando rotación FEFO del lote más antiguo.', 'resp' => 'Motor Transaccional DAILY', 'action' => 'Ver Salida en Kardex', 'url' => '/kardex', 'val' => 'Salida en tiempo real registrada en el kardex'],
			['num' => 5, 'title' => 'Calcular resultados comerciales', 'desc' => 'Consolidar ingresos netos, costo de ventas y margen operativo.', 'resp' => 'Analista Financiero', 'action' => 'Ver Curva de Ventas', 'url' => '/sales', 'val' => 'Cierre diario de terminales POS'],
			['num' => 6, 'title' => 'Comparar contra la meta', 'desc' => 'Medir porcentaje de cumplimiento de venta por tienda y departamento.', 'resp' => 'Director Comercial', 'action' => 'Revisar Metas Comerciales', 'url' => '/sales', 'val' => 'Meta de sucursal configurada'],
			['num' => 7, 'title' => 'Analizar rentabilidad', 'desc' => 'Identificar familias y productos más rentables o en riesgo de margen.', 'resp' => 'Gerencia General', 'action' => 'Rentabilidad por Centro de Costo', 'url' => '/cost-centers', 'val' => 'Margen bruto superior al umbral mínimo']
		]
	],
	'despachos' => [
		'code' => '4',
		'name' => 'Despachos y Devoluciones',
		'icon' => 'fa-truck-ramp-box',
		'match_views' => ['dispatch', 'returns'],
		'match_paths' => ['/dispatch', '/returns'],
		'steps' => [
			['num' => 1, 'title' => 'Recibir pedido', 'desc' => 'Ingreso de orden web, teléfono o transferencia comercial con confirmación de pago.', 'resp' => 'Coordinador Fulfillment', 'action' => 'Revisar Pedidos Pendientes', 'url' => '/orders', 'val' => 'Estado de pedido: Pagado y Validado'],
			['num' => 2, 'title' => 'Asignar preparación', 'desc' => 'Asignar orden de picking a operador de bodega con ruta optimizada de pasillos.', 'resp' => 'Supervisor de Despachos', 'action' => 'Asignar Picking', 'url' => '/orders', 'val' => 'Ubicaciones de producto activas'],
			['num' => 3, 'title' => 'Realizar picking', 'desc' => 'Recolección física de unidades en estantería guiada por lector láser.', 'resp' => 'Operador Picker', 'action' => 'Picking con Escáner', 'url' => '/scan', 'val' => 'Lectura de lote y caducidad más cercana'],
			['num' => 4, 'title' => 'Validar productos', 'desc' => 'Cotejo de ítems, cantidades, estado de empaque y temperatura de conservación.', 'resp' => 'Verificador de Empaque', 'action' => 'Verificar en Despachos', 'url' => '/dispatch#tab-picking', 'val' => 'Cero discrepancias en cantidades escaneadas'],
			['num' => 5, 'title' => 'Empacar', 'desc' => 'Embalaje seguro, etiquetado de guía de envío y precinto de seguridad.', 'resp' => 'Operador Packer', 'action' => 'Registrar Bultos', 'url' => '/dispatch#tab-picking', 'val' => 'Guía de transporte LOC/TRK generada'],
			['num' => 6, 'title' => 'Despachar', 'desc' => 'Entrega al transportista o disposición para retiro de cliente en tienda.', 'resp' => 'Despachador WMS', 'action' => 'Registrar Salida de Camión', 'url' => '/dispatch', 'val' => 'Manifiesto firmado por conductor'],
			['num' => 7, 'title' => 'Confirmar entrega', 'desc' => 'Cierre con firma digital de cliente o prueba electrónica de entrega (POD).', 'resp' => 'Logística / Transportista', 'action' => 'Confirmar Entrega en Seguimiento', 'url' => '/dispatch#tab-tracking', 'val' => 'Firma y evidencia fotográfica cargada'],
			['num' => 8, 'title' => 'Gestionar devolución o incidencia', 'desc' => 'Inspección técnica, clasificación sanitaria en 7 destinos y resolución inmediata.', 'resp' => 'Supervisor de Calidad y Devoluciones', 'action' => 'Gestionar Devoluciones', 'url' => '/returns', 'val' => 'Causal de devolución AKCAL tipificada']
		]
	],
	'mermas' => [
		'code' => '5',
		'name' => 'Mermas y Prevención',
		'icon' => 'fa-trash-can',
		'match_views' => ['waste_overview', 'waste_report', 'waste_approvals', 'waste_reasons'],
		'match_paths' => ['/waste', '/waste/report', '/waste/approvals', '/waste/reasons'],
		'steps' => [
			['num' => 1, 'title' => 'Detectar merma', 'desc' => 'Identificar producto dañado, vencido, roto o no conforme en piso o bodega.', 'resp' => 'Colaborador de Piso / Operador', 'action' => 'Cuadro de Control de Mermas', 'url' => '/waste', 'val' => 'Separación inmediata del lote vendible'],
			['num' => 2, 'title' => 'Escanear producto', 'desc' => 'Lectura láser de código de barras para recuperar SKU, costo unitario y lote.', 'resp' => 'Operador / Auditor', 'action' => 'Escanear Mercancía', 'url' => '/scan', 'val' => 'Costo de reposición identificado'],
			['num' => 3, 'title' => 'Registrar cantidad y motivo', 'desc' => 'Captura de unidades afectadas y clasificación en la matriz de causales AKCAL.', 'resp' => 'Supervisor de Turno', 'action' => 'Registrar Merma', 'url' => '/waste/report', 'val' => 'Causal tipificada (Operativa, Comercial, Avería, Ajuste)'],
			['num' => 4, 'title' => 'Adjuntar evidencia', 'desc' => 'Fotografía obligatoria del daño y empaque antes de cualquier disposición.', 'resp' => 'Registrador', 'action' => 'Cargar Fotografía en Formulario', 'url' => '/waste/report', 'val' => 'Archivo fotográfico adjunto al ticket'],
			['num' => 5, 'title' => 'Enviar a aprobación', 'desc' => 'Remisión del registro a la bandeja de aprobaciones según umbrales de valor.', 'resp' => 'Sistema DAILY', 'action' => 'Bandeja de Aprobaciones', 'url' => '/waste/approvals', 'val' => 'Enrutamiento a Supervisor o Gerente según monto'],
			['num' => 6, 'title' => 'Validar impacto en inventario', 'desc' => 'Comprobar asiento de baja valorizada en kardex contable.', 'resp' => 'Contador de Costos', 'action' => 'Auditar en Kardex Valorizado', 'url' => '/kardex', 'val' => 'Deducción de existencias verificada'],
			['num' => 7, 'title' => 'Analizar causa raíz', 'desc' => 'Revisión técnica de patrones de rotura, caducidad o manipulación inadecuada.', 'resp' => 'Especialista en Prevención', 'action' => 'Matriz de Causales AKCAL', 'url' => '/waste/reasons', 'val' => 'Clasificación 5-Porqués completada'],
			['num' => 8, 'title' => 'Crear acción correctiva', 'desc' => 'Implementar plan preventivo para evitar reincidencia en la sucursal.', 'resp' => 'Gerente de Tienda', 'action' => 'Abrir Investigación de Merma', 'url' => '/waste#tab-investigaciones', 'val' => 'Capacitación o ajuste de estiba programado'],
			['num' => 9, 'title' => 'Evaluar recuperación financiera', 'desc' => 'Reclamo de nota de crédito a proveedor o cobro a póliza de seguros.', 'resp' => 'Coordinador de Recuperaciones', 'action' => 'Gestión de Recuperación Financiera', 'url' => '/waste#tab-recuperacion', 'val' => 'Expediente de reclamo formal emitido'],
			['num' => 10, 'title' => 'Cerrar registro', 'desc' => 'Destrucción certificada o devolución formal con firma de auditoría.', 'resp' => 'Auditor Interno', 'action' => 'Cerrar Ticket de Merma', 'url' => '/waste', 'val' => 'Acta de disposición final firmada']
		]
	],
	'prevencion' => [
		'code' => '6',
		'name' => 'Prevención de Pérdidas',
		'icon' => 'fa-shield-halved',
		'match_views' => ['loss_prevention', 'loss_case', 'loss_counts', 'devices', 'device_detail', 'loss_executive_report'],
		'match_paths' => ['/loss-prevention', '/loss-prevention/devices', '/reports/loss-executive'],
		'steps' => [
			['num' => 1, 'title' => 'Detectar alerta o diferencia', 'desc' => 'Identificación de descuadre crítico en conteo, salto de stock o alerta CCTV.', 'resp' => 'Auditor / Prevencionista', 'action' => 'Centro de Control Prevención', 'url' => '/loss-prevention', 'val' => 'Descuadre monetario superior al umbral'],
			['num' => 2, 'title' => 'Crear expediente', 'desc' => 'Apertura de caso formal con número de expediente único y nivel de severidad.', 'resp' => 'Jefe de Prevención de Pérdidas', 'action' => 'Crear Expediente de Investigación', 'url' => '/loss-prevention#tab-expedientes', 'val' => 'Código de expediente asignado (EXP-2026-XXXX)'],
			['num' => 3, 'title' => 'Identificar mercancía o activo', 'desc' => 'Trazabilidad individual por código de serie, IMEI de celulares o SKU sensible.', 'resp' => 'Investigador', 'action' => 'Control de Celulares y Activos', 'url' => '/loss-prevention/devices', 'val' => 'Serial o IMEI rastreado en base de datos'],
			['num' => 4, 'title' => 'Consultar movimientos y custodias', 'desc' => 'Revisar quién tuvo en custodia el bien y transferencias en los últimos 30 días.', 'resp' => 'Investigador de Seguridad', 'action' => 'Auditoría en Conciliación', 'url' => '/reconciliation', 'val' => 'Línea de tiempo de custodios elaborada'],
			['num' => 5, 'title' => 'Reunir evidencia', 'desc' => 'Compilar grabaciones de CCTV, bitácoras de acceso a bóveda y declaraciones.', 'resp' => 'Operador CCTV / Seguridad', 'action' => 'CCTV y Evidencias', 'url' => '/loss-prevention#tab-cctv', 'val' => 'Marcas de tiempo de video registradas'],
			['num' => 6, 'title' => 'Asignar investigador', 'desc' => 'Designar responsable principal y fijar fecha límite de entrega de conclusiones.', 'resp' => 'Gerencia de Seguridad', 'action' => 'Asignar Investigador', 'url' => '/loss-prevention#tab-expedientes', 'val' => 'Investigador certificado asignado'],
			['num' => 7, 'title' => 'Registrar hallazgos', 'desc' => 'Documentar causa raíz: hurto externo, colusión interna o falla procedimental.', 'resp' => 'Investigador Principal', 'action' => 'Documentar Conclusiones', 'url' => '/loss-prevention#tab-expedientes', 'val' => 'Informe técnico pericial cargado'],
			['num' => 8, 'title' => 'Ejecutar medidas autorizadas', 'desc' => 'Revocación de accesos, modificación de permisos o proceso sancionatorio.', 'resp' => 'Recursos Humanos / Jurídico', 'action' => 'Accesos y Autorizaciones', 'url' => '/loss-prevention#tab-accesos', 'val' => 'Aprobación de Comité de Disciplina'],
			['num' => 9, 'title' => 'Verificar resultados', 'desc' => 'Inspección de rondas físicas y confirmación de blindaje operativo en la tienda.', 'resp' => 'Supervisor de Zona', 'action' => 'Planes Preventivos', 'url' => '/loss-prevention#tab-planes', 'val' => 'Checklist de rondas 100% completado'],
			['num' => 10, 'title' => 'Cerrar investigación', 'desc' => 'Emisión de reporte ejecutivo de cierre con impacto financiero consolidado.', 'resp' => 'Director de Auditoría', 'action' => 'Reporte Ejecutivo de Prevención', 'url' => '/reports/loss-executive', 'val' => 'Expediente cerrado con balance contable']
		]
	],
	'operaciones' => [
		'code' => '7',
		'name' => 'Operaciones y Personal',
		'icon' => 'fa-people-group',
		'match_views' => ['schedule', 'attendance', 'workforce', 'equipment', 'choresoverview', 'tasks', 'calendar'],
		'match_paths' => ['/operations/scheduling', '/operations/attendance', '/workforce', '/schedule'],
		'steps' => [
			['num' => 1, 'title' => 'Revisar dotación', 'desc' => 'Validar plantilla activa por sucursal, ausentismos y contratos vigentes.', 'resp' => 'Jefe de Personal / RRHH', 'action' => 'Gestión de Personal', 'url' => '/schedule#tab-employees', 'val' => 'Headcount real cotejado contra nómina'],
			['num' => 2, 'title' => 'Crear programación', 'desc' => 'Asignar turnos de apertura, intermedio, cierre y noche según forecast de clientes.', 'resp' => 'Planificador WFM', 'action' => 'Programar Horarios y Cobertura', 'url' => '/operations/scheduling', 'val' => 'Curva de personal calzada con horas pico de venta'],
			['num' => 3, 'title' => 'Validar cobertura', 'desc' => 'Chequear restricciones legales: 12h de descanso y regla 3x1 dominical.', 'resp' => 'Motor de Reglas Laborales', 'action' => 'Auditar Restricciones Laborales', 'url' => '/operations/scheduling', 'val' => 'Cero infracciones laborales detectadas'],
			['num' => 4, 'title' => 'Publicar turnos', 'desc' => 'Notificar a colaboradores sus horarios oficiales con 48h de anticipación.', 'resp' => 'Gerente de Tienda', 'action' => 'Publicar Malla de Turnos', 'url' => '/operations/scheduling', 'val' => 'Publicación confirmada en la aplicación del empleado'],
			['num' => 5, 'title' => 'Registrar asistencia', 'desc' => 'Fichaje biométrico de entradas, salidas, descansos e incidencias de puntualidad.', 'resp' => 'Colaborador / Terminal Biométrico', 'action' => 'Control de Asistencia y Fichajes', 'url' => '/operations/attendance', 'val' => 'Marcaciones sincronizadas con el reloj'],
			['num' => 6, 'title' => 'Asignar tareas', 'desc' => 'Distribución de checklists diarios de piso de venta, reposición y limpieza.', 'resp' => 'Líder de Piso de Venta', 'action' => 'Tareas y Roles Operativos', 'url' => '/workforce', 'val' => 'Checklists operativos asignados a cada puesto'],
			['num' => 7, 'title' => 'Supervisar ejecución', 'desc' => 'Monitoreo en tiempo real del grado de avance de tareas críticas del turno.', 'resp' => 'Supervisor de Turno', 'action' => 'Supervisión en Piso', 'url' => '/workforce', 'val' => 'Checklists completados con firma digital'],
			['num' => 8, 'title' => 'Medir productividad', 'desc' => 'Comparar horas hombre programadas vs. ejecutadas y volumen de venta alcanzado.', 'resp' => 'Gerencia de Operaciones', 'action' => 'Analizar Productividad Operativa', 'url' => '/schedule#tab-productivity', 'val' => 'Ventas por hora/hombre calculadas']
		]
	],
	'analisis' => [
		'code' => '8',
		'name' => 'Reportes y Analítica',
		'icon' => 'fa-chart-pie',
		'match_views' => ['reports_hub', 'cost_centers', 'stockreportspendings', 'stockjournalsummary'],
		'match_paths' => ['/reports', '/cost-centers'],
		'steps' => [
			['num' => 1, 'title' => 'Seleccionar período', 'desc' => 'Definir ventana de análisis (hoy, semana en curso, mes cerrado o comparativo anual).', 'resp' => 'Analista de Negocio / BI', 'action' => 'Abrir Reportes & Analítica', 'url' => '/reports', 'val' => 'Rango de fechas validado con datos consolidados'],
			['num' => 2, 'title' => 'Seleccionar sucursal', 'desc' => 'Filtrar por tienda individual, zona geográfica o consolidado corporativo total.', 'resp' => 'Usuario Ejecutivo', 'action' => 'Filtrar Centros de Costo', 'url' => '/cost-centers', 'val' => 'Contexto de tienda seleccionado'],
			['num' => 3, 'title' => 'Consultar indicadores', 'desc' => 'Revisar ventas netas, margen bruto, porcentaje de merma y rotación de stock.', 'resp' => 'Director Ejecutivo', 'action' => 'Ver Panel Ejecutivo', 'url' => '/kpis', 'val' => 'Indicadores calculados contra presupuesto'],
			['num' => 4, 'title' => 'Comparar resultados', 'desc' => 'Benchmarking entre sucursales para detectar brechas de rendimiento.', 'resp' => 'Gerente de Operaciones', 'action' => 'Comparativa Comercial', 'url' => '/sales', 'val' => 'Clasificación de tiendas por quintiles'],
			['num' => 5, 'title' => 'Identificar desviaciones', 'desc' => 'Consultar análisis financiero de centros de costo e incidencias.', 'resp' => 'Analista de Costos / Control', 'action' => 'Consultar Centros de Costo', 'url' => '/cost-centers', 'val' => 'Detección de desviaciones presupuestales'],
			['num' => 6, 'title' => 'Generar reporte', 'desc' => 'Consolidar balance comercial y operativo en informe ejecutivo unificado.', 'resp' => 'Analista Financiero', 'action' => 'Generar Informe Ejecutivo', 'url' => '/reports', 'val' => 'Conciliación contable 100% consistente'],
			['num' => 7, 'title' => 'Exportar o compartir', 'desc' => 'Descarga en Excel/PDF cifrado o programación de envío automático a directores.', 'resp' => 'Control de Gestión', 'action' => 'Exportar Datos Oficiales', 'url' => '/reports', 'val' => 'Exportación segura completada']
		]
	],
	'sistema' => [
		'code' => '9',
		'name' => 'Administración y Sistema',
		'icon' => 'fa-gear',
		'match_views' => ['admin_settings', 'users', 'userform', 'userpermissions', 'data_import', 'location_labels', 'admin_audit_log', 'system_map', 'admin_economics', 'products', 'locations', 'shoppinglocations', 'quantityunits', 'productgroups', 'chores', 'batteries', 'taskcategories', 'userfields', 'userentities'],
		'match_paths' => ['/settings', '/users', '/data-import', '/locations/labels', '/audit-log', '/system-map', '/admin/economics', '/products', '/locations', '/shoppinglocations', '/quantityunits'],
		'steps' => [
			['num' => 1, 'title' => 'Configurar empresa y sucursales', 'desc' => 'Establecer razón social, moneda, zona horaria y estructura de almacenes.', 'resp' => 'Administrador Global', 'action' => 'Configuración del Sistema', 'url' => '/settings', 'val' => 'Parámetros corporativos guardados'],
			['num' => 2, 'title' => 'Gestionar datos maestros', 'desc' => 'Crear productos, unidades de medida, ubicaciones y causales estándar.', 'resp' => 'Gestor de Datos Maestros', 'action' => 'Catálogo de Productos', 'url' => '/products', 'val' => 'Catálogo base de SKUs cargado'],
			['num' => 3, 'title' => 'Crear usuarios', 'desc' => 'Alta de credenciales de colaboradores y directores con políticas de clave segura.', 'resp' => 'Oficial de Seguridad / IAM', 'action' => 'Gestión de Usuarios', 'url' => '/users', 'val' => 'Hashing seguro de contraseñas'],
			['num' => 4, 'title' => 'Asignar permisos', 'desc' => 'Asociar rol (Manager, Supervisor, Operador) y privilegios granulares por módulo.', 'resp' => 'Administrador IAM', 'action' => 'Matriz de Permisos', 'url' => '/users', 'val' => 'Principio de menor privilegio verificado'],
			['num' => 5, 'title' => 'Configurar integraciones', 'desc' => 'Habilitar conexión con cajas POS, ERP central, API keys y webhooks.', 'resp' => 'Arquitecto de Integraciones', 'action' => 'Integraciones POS/ERP', 'url' => '/settings#tab-integrations', 'val' => 'Tokens de integración generados de forma segura'],
			['num' => 6, 'title' => 'Validar seguridad', 'desc' => 'Verificar aislamiento multi-tenant y cifrado en reposo y tránsito.', 'resp' => 'Auditor de Ciberseguridad', 'action' => 'Políticas de Seguridad', 'url' => '/settings#tab-security', 'val' => 'Aislamiento de base de datos verificado'],
			['num' => 7, 'title' => 'Consultar auditoría', 'desc' => 'Inspeccionar bitácora inalterable con diff detallado de todas las transacciones.', 'resp' => 'Auditor Interno', 'action' => 'Bitácora de Auditoría', 'url' => '/audit-log', 'val' => 'Hash SHA-256 verificado'],
			['num' => 8, 'title' => 'Supervisar funcionamiento', 'desc' => 'Monitorear salud del servidor, consumo de memoria y conectividad técnica.', 'resp' => 'SysAdmin / DevOps', 'action' => 'Mapa Técnico del Sistema', 'url' => '/system-map', 'val' => 'Estado operativo: 100% Óptimo']
		]
	]
];

// Identificar si la página actual corresponde a alguno de los 10 ciclos
$activeFlowKey = null;
foreach ($allFlows as $k => $f) {
	if (in_array($v, $f['match_views'])) {
		$activeFlowKey = $k;
		break;
	}
	foreach ($f['match_paths'] as $mp) {
		if ($path === $mp || strpos($path, $mp . '/') === 0) {
			$activeFlowKey = $k;
			break 2;
		}
	}
}

$currentFlow = $activeFlowKey ? $allFlows[$activeFlowKey] : null;
@endphp

@if($currentFlow && GROCY_AUTHENTICATED && empty($embedded))
<div class="daily-flow-guide-wrapper mb-3" id="dailyFlowGuideContainer" data-flow-key="{{ $activeFlowKey }}">
	<div class="card border-0 shadow-sm" style="border-left: 4px solid var(--dairy-primary, #008E48) !important; background: #ffffff;">
		
		<!-- Barra de Título y Estado -->
		<div class="card-header bg-white py-2 px-3 d-flex justify-content-between align-items-center border-bottom-0">
			<div class="d-flex align-items-center gap-2">
				<span class="badge badge-primary px-2 py-1 font-weight-600" style="background: var(--dairy-primary, #008E48); color: #fff;">
					<i class="fa-solid {{ $currentFlow['icon'] }} mr-1"></i> CICLO {{ $currentFlow['code'] }}
				</span>
				<strong class="text-dark font-weight-700" style="font-size: 0.95rem;">
					Guía Operativa Paso a Paso: {{ $currentFlow['name'] }}
				</strong>
				<span class="badge badge-light border text-muted px-2 py-1 small" id="flowStepBadge">
					Paso <span id="flowCurrentStepNum">1</span> de {{ count($currentFlow['steps']) }}
				</span>
			</div>
			
			<div class="d-flex align-items-center gap-2">
				<button type="button" class="btn btn-xs btn-outline-secondary py-1 px-2 font-weight-600" id="btnToggleFlowDetails" title="Minimizar / Expandir guía">
					<i class="fa-solid fa-chevron-up" id="iconToggleFlow"></i> <span id="lblToggleFlow">Ocultar Guía</span>
				</button>
			</div>
		</div>

		<!-- Contenido Detallado de la Guía -->
		<div class="card-body py-2 px-3" id="flowDetailsBody">
			
			<!-- Barra Horizontal de Pasos (Stepper) -->
			<div class="daily-stepper-row d-flex flex-wrap gap-1 mb-3 align-items-center">
				@foreach($currentFlow['steps'] as $s)
				<div class="daily-stepper-item step-pill" 
					 data-step="{{ $s['num'] }}"
					 onclick="DailyFlowGuide.goToStep({{ $s['num'] }})"
					 title="Paso {{ $s['num'] }}: {{ $s['title'] }}">
					<span class="step-circle" id="circle-step-{{ $s['num'] }}">{{ $s['num'] }}</span>
					<span class="step-label d-none d-lg-inline">{{ $s['num'] }}. {{ $s['title'] }}</span>
				</div>
				@if($s['num'] < count($currentFlow['steps']))
				<i class="fa-solid fa-chevron-right text-muted opacity-50 stepper-arrow" style="font-size: 0.65rem;"></i>
				@endif
				@endforeach
			</div>

			<!-- Tarjeta de Acción del Paso Activo -->
			<div class="p-3 rounded border bg-light" id="activeStepBox">
				<div class="row align-items-center">
					<div class="col-md-7">
						<div class="d-flex align-items-center gap-2 mb-1">
							<span class="badge badge-info px-2 py-1 font-weight-600" id="stepCurrentBadge">Paso Actual</span>
							<h5 class="h6 font-weight-bold text-dark mb-0" id="stepCurrentTitle">Cargando paso...</h5>
						</div>
						<p class="text-secondary small mb-2" id="stepCurrentDesc">Descripción operativa del paso.</p>
						
						<div class="d-flex flex-wrap gap-3 small text-muted">
							<div>
								<i class="fa-solid fa-user-shield text-primary mr-1"></i>
								<strong>Responsable:</strong> <span id="stepCurrentResp" class="text-dark">---</span>
							</div>
							<div>
								<i class="fa-solid fa-clipboard-check text-success mr-1"></i>
								<strong>Validación:</strong> <span id="stepCurrentVal" class="text-dark">---</span>
							</div>
							<div>
								<i class="fa-solid fa-signal text-warning mr-1"></i>
								<strong>Estado:</strong> <span id="stepCurrentStatus" class="badge badge-warning">En Curso</span>
							</div>
						</div>
					</div>

					<div class="col-md-5 text-md-right mt-2 mt-md-0 d-flex justify-content-md-end gap-2 align-items-center">
						<a href="#" id="btnStepAction" class="btn btn-primary btn-sm font-weight-600 px-3 shadow-sm" style="background: var(--dairy-primary, #008E48); border-color: var(--dairy-primary, #008E48);">
							<span id="lblStepAction">Ejecutar Acción</span> <i class="fa-solid fa-arrow-up-right-from-square ml-1"></i>
						</a>
						
						<button type="button" class="btn btn-outline-success btn-sm font-weight-600 px-3 shadow-sm" id="btnCompleteStep">
							<i class="fa-solid fa-check mr-1"></i> Completar y Siguiente
						</button>
						
						<button type="button" class="btn btn-outline-secondary btn-sm px-2 shadow-sm" id="btnResetFlow" title="Reiniciar flujo operativo a paso 1">
							<i class="fa-solid fa-rotate-left"></i>
						</button>
					</div>
				</div>
			</div>

		</div>
	</div>
</div>

<style>
.daily-stepper-row {
	overflow-x: auto;
	padding-bottom: 4px;
}
.daily-stepper-item {
	display: inline-flex;
	align-items: center;
	gap: 6px;
	padding: 4px 10px;
	background: #f1f5f9;
	border: 1px solid #cbd5e1;
	border-radius: 20px;
	font-size: 0.78rem;
	color: #475569;
	cursor: pointer;
	user-select: none;
	transition: all 0.2s ease;
}
.daily-stepper-item:hover {
	background: #e2e8f0;
	border-color: #94a3b8;
}
.daily-stepper-item.is-active {
	background: #eff6ff;
	border-color: #3b82f6;
	color: #1d4ed8;
	font-weight: 700;
	box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.2);
}
.daily-stepper-item.is-completed {
	background: #f0fdf4;
	border-color: #22c55e;
	color: #15803d;
	font-weight: 600;
}
.daily-stepper-item .step-circle {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	width: 18px;
	height: 18px;
	border-radius: 50%;
	background: #94a3b8;
	color: #fff;
	font-size: 0.65rem;
	font-weight: 800;
}
.daily-stepper-item.is-active .step-circle {
	background: #2563eb;
}
.daily-stepper-item.is-completed .step-circle {
	background: #16a34a;
}
.stepper-arrow {
	margin: 0 2px;
}
</style>

<script>
window.DailyFlowGuide = (function() {
	var flowData = @json($currentFlow);
	var flowKey = "{{ $activeFlowKey }}";
	var storageKey = "daily_flow_step_" + flowKey;
	var toggleStorageKey = "daily_flow_minimized_" + flowKey;
	
	var currentStep = 1;
	var totalSteps = flowData.steps.length;

	function init() {
		try {
			var saved = parseInt(localStorage.getItem(storageKey), 10);
			if (saved && saved >= 1 && saved <= totalSteps) {
				currentStep = saved;
			}
			
			var isMin = localStorage.getItem(toggleStorageKey) === 'true';
			if (isMin) {
				$('#flowDetailsBody').hide();
				$('#iconToggleFlow').removeClass('fa-chevron-up').addClass('fa-chevron-down');
				$('#lblToggleFlow').text('Mostrar Guía');
			}
		} catch(e) {}

		render();

		$('#btnCompleteStep').on('click', function() {
			if (currentStep < totalSteps) {
				currentStep++;
			} else {
				alert("¡Felicitaciones! Ha completado exitosamente todos los pasos de la guía operativa de " + flowData.name + ".");
				currentStep = 1;
			}
			saveState();
			render();
		});

		$('#btnResetFlow').on('click', function() {
			if (confirm("¿Desea reiniciar el flujo operativo al Paso 1?")) {
				currentStep = 1;
				saveState();
				render();
			}
		});

		$('#btnToggleFlowDetails').on('click', function() {
			var body = $('#flowDetailsBody');
			if (body.is(':visible')) {
				body.slideUp(200);
				$('#iconToggleFlow').removeClass('fa-chevron-up').addClass('fa-chevron-down');
				$('#lblToggleFlow').text('Mostrar Guía');
				try { localStorage.setItem(toggleStorageKey, 'true'); } catch(e) {}
			} else {
				body.slideDown(200);
				$('#iconToggleFlow').removeClass('fa-chevron-down').addClass('fa-chevron-up');
				$('#lblToggleFlow').text('Ocultar Guía');
				try { localStorage.setItem(toggleStorageKey, 'false'); } catch(e) {}
			}
		});
	}

	function saveState() {
		try {
			localStorage.setItem(storageKey, currentStep);
		} catch(e) {}
	}

	function goToStep(num) {
		if (num >= 1 && num <= totalSteps) {
			currentStep = num;
			saveState();
			render();
		}
	}

	function render() {
		var stepObj = flowData.steps[currentStep - 1];
		if (!stepObj) return;

		$('#flowCurrentStepNum').text(currentStep);
		$('#stepCurrentTitle').text("Paso " + stepObj.num + ": " + stepObj.title);
		$('#stepCurrentDesc').text(stepObj.desc);
		$('#stepCurrentResp').text(stepObj.resp);
		$('#stepCurrentVal').text(stepObj.val);
		
		var url = stepObj.url;
		if (url.indexOf('http') !== 0) {
			url = window.Grocy ? (Grocy.BaseUrl.replace(/\/+$/, '') + url) : url;
		}
		$('#btnStepAction').attr('href', url);
		$('#lblStepAction').text(stepObj.action);

		// Render Stepper Pills
		flowData.steps.forEach(function(s) {
			var pill = $('.step-pill[data-step="' + s.num + '"]');
			var circle = $('#circle-step-' + s.num);
			pill.removeClass('is-active is-completed');
			
			if (s.num < currentStep) {
				pill.addClass('is-completed');
				circle.html('<i class="fa-solid fa-check"></i>');
			} else if (s.num === currentStep) {
				pill.addClass('is-active');
				circle.text(s.num);
			} else {
				circle.text(s.num);
			}
		});

		if (currentStep === totalSteps) {
			$('#stepCurrentStatus').removeClass('badge-warning').addClass('badge-success').text('Paso Final / Cierre');
			$('#btnCompleteStep').html('<i class="fa-solid fa-flag-checkered mr-1"></i> Finalizar Ciclo');
		} else {
			$('#stepCurrentStatus').removeClass('badge-success').addClass('badge-warning').text('En Curso');
			$('#btnCompleteStep').html('<i class="fa-solid fa-check mr-1"></i> Completar y Siguiente');
		}
	}

	return {
		init: init,
		goToStep: goToStep
	};
})();

document.addEventListener('DOMContentLoaded', function() {
	if (window.DailyFlowGuide) {
		DailyFlowGuide.init();
	}
});
</script>
@endif

