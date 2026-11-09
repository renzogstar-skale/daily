// Contenido compartido por la versión web y la PPTX. Solo funciones verificadas en el código de DAILY.
module.exports = {
  problems: [
    ['faEyeSlash', 'Pérdidas que se detectan tarde', 'Se descubren al cierre, sin margen de acción.', 'Prevención de Pérdidas', 'Alertas con responsable y expedientes desde la primera diferencia.'],
    ['faScaleUnbalanced', 'Físico ≠ sistema', 'Quiebres, sobrecompra y ventas perdidas.', 'Conciliación y Kardex', 'Conteos, kardex valorizado y conciliación por producto.'],
    ['faCalendarXmark', 'Productos vencidos', 'Desperdicio y riesgo para el cliente.', 'Calidad y FEFO', 'Lotes próximos a vencer, cuarentena y retiros.'],
    ['faMobileScreenButton', 'Equipos de alto valor sin rastro', 'Celulares que nadie sabe dónde están.', 'Control de Celulares', 'Cada IMEI con estado, ubicación y custodio.'],
    ['faUsers', 'Personal desajustado', 'Filas en el pico, horas ociosas en el valle.', 'Horarios y Cobertura', 'Turnos por área frente a la demanda proyectada.'],
    ['faTruckRampBox', 'Compras a ciegas', 'Sobrestock en unos productos, quiebre en otros.', 'Planificación MRP', 'Reposición sugerida con stock de seguridad y punto de reorden.'],
    ['faPuzzlePiece', 'Información fragmentada', 'Cada área con sus propios números.', 'Panel Ejecutivo', 'Una sola versión de los indicadores para toda la red.']
  ],
  problemHot: 'Margen que se pierde sin saber dónde ni por qué',
  hub: [
    { name: 'Planeación y Compras', icon: 'faCartFlatbed', desc: 'Calcula la reposición y convierte la sugerencia en órdenes de compra con seguimiento.', items: ['MRP con stock de seguridad y punto de reorden', 'Órdenes: sugerida → aprobada → enviada → recibida', 'Evaluación OTIF del proveedor'], links: ['Inventario', 'Calidad'] },
    { name: 'Inventario y Almacenes', icon: 'faBoxesStacked', desc: 'Existencias por sucursal, almacén, zona y lote, con cada movimiento en un kardex valorizado.', items: ['Mapa de ubicaciones y traslados verificados', 'Conteos físicos y cíclicos', 'Stock fantasma y matriz ABC-XYZ'], links: ['Compras', 'Ventas', 'Prevención'] },
    { name: 'Ventas y Rentabilidad', icon: 'faCashRegister', desc: 'Venta, costo y margen por sucursal, departamento y producto.', items: ['POS con salida FEFO', 'Pedidos de clientes y cuentas', 'Importación de ventas o API'], links: ['Inventario', 'Panel Ejecutivo'] },
    { name: 'Despachos y Devoluciones', icon: 'faTruckFast', desc: 'Picking por escáner, empaque, despacho y devoluciones con inspección.', items: ['Validación FEFO en el picking', 'Seguimiento por etapas', 'Clasificación sanitaria de devoluciones'], links: ['Ventas', 'Calidad'] },
    { name: 'Calidad y Vencimientos', icon: 'faShieldHeart', desc: 'Protege al cliente y al margen: lotes, temperaturas y retiros.', items: ['Control FEFO y cuarentena', 'Cadena de frío por equipo', 'Trazabilidad de lotes y retiros'], links: ['Mermas', 'Compras'] },
    { name: 'Mermas', icon: 'faTrashCan', desc: 'Registro con evidencia, aprobación y análisis de causas.', items: ['Registro con fotografía', 'Aprobación según nivel', 'Matriz de causales y recuperación con proveedor'], links: ['Calidad', 'Prevención'] },
    { name: 'Prevención de Pérdidas', icon: 'faUserShield', desc: 'Incidentes, expedientes, activos y celulares con trazabilidad individual.', items: ['Expedientes con evidencia', 'Control de celulares por IMEI', 'Rondas, CCTV y planes preventivos'], links: ['Inventario', 'Alertas'] },
    { name: 'Operaciones y Personal', icon: 'faPeopleGroup', desc: 'Horarios, asistencia y tareas de piso conectados con la demanda.', items: ['Turnos y cobertura por área', 'Asistencia y fichajes', 'Tareas con responsable'], links: ['Ventas', 'Panel Ejecutivo'] }
  ],
  salesCallouts: [
    ['Venta y margen', 'Venta neta, utilidad bruta y margen frente al período anterior.'],
    ['Curva y metas', 'Evolución de la venta y cumplimiento de la meta.'],
    ['Sucursales y departamentos', 'Qué tiendas y áreas sostienen el margen.'],
    ['Productos', 'Los de mayor y menor rendimiento, para decidir surtido y precio.']
  ],
  inv: [
    { short: 'Stock', name: 'Stock & Movimientos', img: 'stockoverview', icon: 'faBoxesStacked', desc: 'Existencias por almacén, lotes y bitácora de movimientos.', items: ['Valor, unidades, rotación y cobertura', 'Bajo mínimo, próximos a vencer y agotados', 'Filtros por sucursal y departamento'] },
    { short: 'Mercancías', name: 'Control de Mercancías', img: 'merchandise', icon: 'faDolly', desc: 'Centro operativo de recepciones, entradas, salidas, movimientos y documentos.', items: ['Recepciones y salidas con documento', 'Movimientos por tipo', 'Búsqueda de trazabilidad'] },
    { short: 'Control', name: 'Control de Inventario', img: 'inventory_control', icon: 'faMagnifyingGlassChart', desc: 'Centro analítico del inventario.', items: ['Stock fantasma', 'Matriz ABC-XYZ', 'Inventario estancado y cobertura'] },
    { short: 'Kardex', name: 'Kardex Valorizado', img: 'kardex', icon: 'faBookOpen', desc: 'Movimientos valorizados por producto, lote y sucursal.', items: ['Entradas, salidas y saldo valorizado', 'Usuario, hora y documento', 'Libro de movimientos verificable'] },
    { short: 'Ubicaciones', name: 'Mapa de Ubicaciones', img: 'location_map', icon: 'faMapLocationDot', desc: 'Ocupación, vencimientos y valor por almacén, zona y pasillo.', items: ['Vista por zona y pasillo', 'Etiquetas de ubicación QR / Code 128', 'Valor y vencimientos por ubicación'] },
    { short: 'Traslados', name: 'Traslados entre Tiendas', img: 'transfers', icon: 'faRightLeft', desc: 'Envío y recepción con verificación entre sucursales.', items: ['Envío y recepción confirmados', 'Diferencias en tránsito', 'Movimientos en ambas sucursales'] },
    { short: 'Conciliación', name: 'Conciliación & Sensible', img: 'reconciliation', icon: 'faScaleBalanced', desc: 'Trazabilidad 360°, inventario sensible y conciliación de flujos.', items: ['Ficha por producto con su historia', 'Esperado vs. contado', 'Alertas por diferencias'] },
    { short: 'Sucursales', name: 'Sucursales y Almacenes', img: 'branches', icon: 'faStore', desc: 'Estructura multisucursal y stock consolidado de la red.', items: ['Mismos indicadores por tienda', 'Desviaciones visibles', 'Del total de la red al detalle'] }
  ],
  imeiFlow: [
    ['faTruckRampBox', 'Recepción', 'Escaneo contra manifiesto: conforme, duplicado o adicional.'],
    ['faLocationDot', 'Ubicación', 'Bodega segura, vitrina, piso, pickup, devoluciones, servicio técnico.'],
    ['faRightLeft', 'Movimiento', 'Solicitud y confirmación de recepción en destino.'],
    ['faBoxOpen', 'Pedido', 'Reserva, picking, empaque y verificación por IMEI.'],
    ['faCashRegister', 'Venta', 'Vinculada a la transacción del POS.'],
    ['faClipboardCheck', 'Conteos', 'Apertura, cierre, cambio de turno, riesgo o aleatorio.'],
    ['faTriangleExclamation', 'Diferencia', 'Alerta de prevención con responsable y plazo.']
  ],
  mrpFlow: [
    ['faChartLine', 'Demanda', 'Consumo y ventas por producto y sucursal.'],
    ['faCalculator', 'Cálculo MRP', 'Stock de seguridad y punto de reorden, horizonte de 14 días.'],
    ['faFileCirclePlus', 'Orden sugerida', 'Generada desde el cálculo.'],
    ['faUserCheck', 'Aprobación', 'Revisión antes de enviar.'],
    ['faPaperPlane', 'Envío', 'Al proveedor, con fecha esperada.'],
    ['faTruckRampBox', 'Recepción', 'Contra la orden y con reglas por producto.'],
    ['faMedal', 'Evaluación', 'Cumplimiento OTIF del proveedor.']
  ],
  qualityFlow: [
    ['faHourglassHalf', 'Próximo a vencer', 'Lotes identificados por fecha.', 'y'],
    ['faArrowUpWideShort', 'Priorización', 'FEFO y riesgo por lote.', 'y'],
    ['faTags', 'Acción preventiva', 'Rebaja, traslado o cuarentena.', 'g'],
    ['faCamera', 'Merma con evidencia', 'Registro con fotografía y causa.', 'r'],
    ['faUserCheck', 'Aprobación', 'Según el nivel de autorización.', 'g'],
    ['faHandHoldingDollar', 'Recuperación', 'Reclamo al proveedor cuando aplica.', 'g']
  ],
  phones: [['m_home', 'Hoy'], ['m_scan', 'Escáner'], ['m_inv', 'Inventario'], ['m_tasks', 'Tareas']],
  scanRows: [
    ['faBarcode', 'EAN-13 / UPC', 'Validación del dígito verificador.'],
    ['faQrcode', 'GS1-128', 'Lee GTIN, lote y vencimiento del mismo código.'],
    ['faCameraRetro', 'Cámara o lector', 'Cámara del teléfono o lector en modo teclado.'],
    ['faListCheck', 'Sesiones guiadas', 'Conteo, movimiento, recepción contra OC y preparación de pedido.']
  ],
  caso: [
    { step: 'Recepción', big: '10', color: '#0B6B33', title: 'Se reciben 10 unidades de un producto sensible', text: 'La recepción queda registrada con documento, usuario y hora. El stock esperado pasa a 10.', img: 'case_producto' },
    { step: 'Conteo', big: '8', color: '#9A6B00', title: 'El conteo físico encuentra 8', text: 'Al registrar el conteo, DAILY compara lo esperado con lo contado.', img: 'case_producto', pos: 'top left' },
    { step: 'Diferencia', big: '−2', color: '#E5484D', title: 'Diferencia de 2 unidades: $1.120 al costo', text: 'El producto se marca en rojo y se genera una alerta con responsable y plazo.', img: 'case_alerta' },
    { step: 'Investigación', big: '360°', color: '#0B6B33', title: 'Línea de tiempo y kardex del producto', text: 'Entradas, salidas, ventas, devoluciones y conteos del intervalo auditado, con usuario, hora y documento.', img: 'case_cerrado' },
    { step: 'Causa', big: '?', color: '#9A6B00', title: 'De la diferencia a la causa, con evidencia', text: 'La ficha propone hipótesis según la evidencia. Un reconteo independiente puede ratificar el faltante; la causa la confirma una persona.', img: 'case_ficha' },
    { step: 'Cierre', big: '✓', color: '#0B6B33', title: 'Ajuste autorizado y caso cerrado', text: 'El ajuste lo autoriza un supervisor distinto de quien registró, y el expediente se cierra con la causa documentada.', img: 'case_kardex' }
  ],
  ba: [
    ['Inventario', 'Diferencias que aparecen al cierre del mes', 'La diferencia aparece al registrar el conteo'],
    ['Pérdidas', 'Investigaciones en planillas y correos', 'Expediente con evidencia, responsable y plazo'],
    ['Vencimientos', 'Producto vencido en góndola', 'Lotes priorizados antes de vencer'],
    ['Celulares', 'Equipos de alto valor sin rastro individual', 'Cada IMEI con estado, ubicación y custodio'],
    ['Personal', 'Turnos fijos sin mirar la demanda', 'Cobertura por área frente a la venta proyectada'],
    ['Dirección', 'Cada área con sus propios números', 'Un panel con los mismos indicadores para la red']
  ],
  layers: [
    ['Canales', 'Dónde trabaja el equipo', ['Web de escritorio', 'Web móvil', 'Escáner por cámara o lector', 'POS DAILY', 'Importación CSV / Excel', 'API REST']],
    ['Aplicación', 'Lógica de negocio', ['PHP 8.5', 'Slim 4', 'Blade', 'PHP-DI', 'Servicios de dominio por módulo']],
    ['Datos', 'Una sola fuente de verdad', ['SQLite', 'Migraciones versionadas', 'Libro de movimientos con cadena hash', 'Bitácora de auditoría']],
    ['Interfaz', 'Visualización y captura', ['Bootstrap', 'Chart.js', 'DataTables', 'ZXing (lectura de códigos)', 'bwip-js (etiquetas)', 'FullCalendar']]
  ],
  archStats: [['101', 'servicios de dominio'], ['321', 'migraciones de base de datos'], ['128', 'vistas de la aplicación'], ['10', 'secciones de negocio en el menú']],
  sec: ['Roles y permisos por módulo', 'Doble aprobación en ajustes sensibles', 'IMEI enmascarado; revelarlo requiere permiso', 'Libro de movimientos con integridad verificable', 'Bitácora de auditoría de operaciones'],
  offers: [
    ['faCloud', 'Licenciamiento SaaS', 'Suscripción según sucursales, usuarios y módulos.'],
    ['faFlask', 'Prueba piloto', 'Una o dos tiendas, 30 días, medidos contra línea base.'],
    ['faStore', 'Implementación multisucursal', 'Despliegue por etapas en la red.'],
    ['faHandshake', 'Colaboración estratégica', 'Acuerdo a medida para grandes cadenas.']
  ],
  road: ['Diagnóstico operativo', 'Definición del alcance', 'Configuración', 'Carga e integración', 'Capacitación', 'Prueba piloto', 'Medición de resultados', 'Expansión a sucursales'],
  pilotInd: [['faBullseye', 'Exactitud de inventario'], ['faScaleUnbalanced', 'Diferencias detectadas'], ['faStopwatch', 'Tiempo de investigación'], ['faListCheck', 'Cumplimiento de tareas'], ['faCalendarXmark', 'Vencimientos identificados'], ['faMobileScreen', 'Uso de la plataforma']],
  mosaic: ['kpis', 'sales', 'stockoverview', 'loss_prevention', 'devices', 'mrp', 'quality', 'scheduling', 'waste', 'alerts', 'location_map', 'kardex']
};
