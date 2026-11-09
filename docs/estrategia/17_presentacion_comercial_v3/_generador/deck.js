// DAILY — presentación comercial v3. Un spec de diapositivas -> PPTX + PDF + guion
const path = require('path');
const sharp = require('sharp');
const { Slide, C } = require('./engine');

const LOGO = path.join(__dirname, 'assets', 'logo.png').replace(/\\/g, '/');
const S = {};

function content(key, section, kicker, title, status, notes) {
  const s = new Slide({ section, title });
  s.text(0.6, 0.36, 8, 0.28, kicker.toUpperCase(), { size: 11, bold: true, color: C.G3, spacing: 1.5 });
  s.status(status);
  s.notes = notes;
  S[key] = s; return s;
}
function sub(s, text) { s.text(0.6, 1.26, 12.1, 0.36, text, { size: 15, color: C.MUTED }); }
function callouts(s, x, y, w, items, color = C.R2, gap = 1.3) {
  items.forEach((it, i) => {
    const yy = y + i * gap;
    s.badge(x + 0.2, yy + 0.18, i + 1, color);
    s.text(x + 0.55, yy, w - 0.55, 0.32, it[0], { size: 15, bold: true });
    s.text(x + 0.55, yy + 0.34, w - 0.55, 0.75, it[1], { size: 13, color: C.MUTED });
  });
}
function iconCircle(s, x, y, d, name, fg, bg) {
  s.ellipse(x, y, d, d, { fill: bg });
  s.icon(x + d * 0.27, y + d * 0.27, d * 0.46, name, fg);
}
function flowRow(s, x, y, w, steps, o = {}) {
  const n = steps.length, gap = o.gap || 0.3, bw = (w - gap * (n - 1)) / n, h = o.h || 0.9;
  steps.forEach((st, i) => {
    const xx = x + i * (bw + gap), dark = i === n - 1 && o.lastDark;
    s.rect(xx, y, bw, h, { fill: dark ? C.G3 : C.WHITE, line: C.LINE, r: 0.1, shadow: true });
    iconCircle(s, xx + 0.12, y + (h - 0.5) / 2, 0.5, st[1], dark ? C.G3 : C.WHITE, dark ? C.WHITE : (st[2] || C.G2));
    s.text(xx + 0.7, y + 0.06, bw - 0.76, h - 0.12, st[0], { size: o.size || 13, bold: true, valign: 'middle', color: dark ? C.WHITE : C.INK });
    if (i < n - 1) s.line(xx + bw + 0.03, y + h / 2, xx + bw + gap - 0.03, y + h / 2, { color: C.G3, lw: 2, arrow: true });
  });
}
function stepper(s, active) {
  const T = ['Detectar', 'Revisar', 'Investigar', 'Resolver'];
  T.forEach((t, i) => {
    const on = i === active, x = 0.6 + i * 1.72;
    s.pill(x, 1.3, 1.6, (i + 1) + '. ' + t, { fill: on ? C.G3 : C.WHITE, color: on ? C.WHITE : C.MUTED, size: 12, h: 0.34 });
  });
}
function stat(s, x, y, w, big, label, color = C.INK) {
  s.rect(x, y, w, 1.12, { fill: C.WHITE, line: C.LINE, r: 0.1, shadow: true });
  s.text(x + 0.15, y + 0.08, w - 0.3, 0.6, big, { size: 28, bold: true, color, valign: 'middle' });
  s.text(x + 0.15, y + 0.68, w - 0.3, 0.36, label, { size: 12, color: C.MUTED });
}
function label(s, x, y, w, t) { s.text(x, y, w, 0.28, t, { size: 13, bold: true }); }
function note(s, x, y, w, t, kind = 'Y') {
  const f = kind === 'Y' ? [C.YT, C.Y3] : kind === 'R' ? [C.RT, C.R3] : [C.GT, C.GD];
  s.rect(x, y, w, 0.62, { fill: f[0], r: 0.08 });
  s.text(x + 0.15, y, w - 0.3, 0.62, t, { size: 12, bold: true, color: f[1], valign: 'middle' });
}
function logo(s) {
  s.add({ t: 'img', abs: LOGO, x: 0.72, y: 0.8, w: 0.72, h: 0.72, alt: 'Logo DAILY' });
  s.text(1.58, 0.85, 3, 0.62, 'DAILY', { size: 30, bold: true, color: C.WHITE, valign: 'middle', spacing: 2 });
}
const FULLW = [250, 40, 1650];

// ===== PORTADA =====
{
  const s = new Slide({ cover: true, section: 'Apertura' });
  s.o.bg = '__COVER__';
  logo(s);
  s.text(0.75, 2.0, 5.3, 2.0, 'DAILY — Supervisión, control y gestión operativa para retail', { size: 36, bold: true, color: C.WHITE, lh: 0.95 });
  s.text(0.75, 4.2, 5.0, 1.0, 'Inventario, ventas, mermas, prevención de pérdidas, personal y compras en un solo sistema.', { size: 17, color: 'D7F5DF' });
  s.pill(0.75, 5.45, 3.6, 'Pantallas reales · datos de demostración', { fill: '0E4A2B', color: 'B9F3C9', size: 11, h: 0.36 });
  s.text(6.55, 0.42, 3, 0.28, 'HOY · operación del día', { size: 12, bold: true, color: 'B9F3C9' });
  s.shot(6.55, 0.72, 6.2, 3.55, '01_ejecutivo/today', [250, 60, 1650]);
  s.shot(6.0, 3.05, 4.4, 2.52, '03_inventario/stockoverview', [250, 60, 1650]);
  s.text(6.0, 5.65, 2.4, 0.28, 'Inventario', { size: 12, bold: true, color: 'B9F3C9' });
  s.shot(8.65, 4.35, 4.1, 2.35, '02_mermas/waste_centro-control', [250, 440, 1650]);
  s.text(8.65, 6.78, 3, 0.28, 'Control de Mermas', { size: 12, bold: true, color: 'B9F3C9' });
  s.notes = 'QUÉ DECIR: "DAILY reúne en una plataforma lo que en muchas cadenas vive en planillas y sistemas separados: inventario, ventas, mermas, prevención de pérdidas, personal y compras." QUÉ MOSTRAR: las tres pantallas; son reales, con una empresa de demostración de diez sucursales. BENEFICIO: una sola versión de los números para toda la operación. PREGUNTA HABITUAL: "¿Es un ERP?" → No reemplaza su ERP o POS; agrega supervisión y control operativo, y recibe datos por importación o API.';
  S.cover = s;
}

// ===== PROBLEMAS =====
{
  const s = content('problems', 'Apertura', 'El problema', 'Lo que hoy le cuesta margen a una cadena de retail', 'demo',
    'QUÉ DECIR: recorrer las tarjetas y preguntar cuáles reconoce el cliente. QUÉ MOSTRAR: la tarjeta roja resume la consecuencia. BENEFICIO: poner nombre al problema antes de hablar de software. PREGUNTA PARA EL CLIENTE: "¿Cuándo se enteran hoy de una diferencia de inventario: el mismo día o al cierre del mes?"');
  const P = [
    ['faEyeSlash', 'Pérdidas sin detectar', 'Se descubren tarde, sin margen de acción'],
    ['faScaleUnbalanced', 'Físico ≠ sistema', 'Quiebres, sobrecompra y ventas perdidas'],
    ['faCalendarXmark', 'Productos vencidos', 'Desperdicio y riesgo para el cliente'],
    ['faUsers', 'Personal desajustado', 'Filas en pico, horas ociosas en valle'],
    ['faFileLines', 'Procesos manuales', 'Planillas, firmas y horas de supervisión'],
    ['faPuzzlePiece', 'Información fragmentada', 'Cada área con sus propios números'],
    ['faUserSlash', 'Casos sin dueño', 'Alertas que nadie cierra']
  ];
  const tw = 2.88, th = 2.42, gx = 0.2;
  P.forEach((p, i) => {
    const x = 0.6 + (i % 4) * (tw + gx), y = 1.6 + Math.floor(i / 4) * (th + 0.22);
    s.rect(x, y, tw, th, { fill: C.WHITE, line: C.LINE, r: 0.12, shadow: true });
    iconCircle(s, x + 0.25, y + 0.25, 0.72, p[0], C.R2, C.RT);
    s.text(x + 0.25, y + 1.1, tw - 0.5, 0.4, p[1], { size: 16, bold: true });
    s.text(x + 0.25, y + 1.5, tw - 0.5, 0.7, p[2], { size: 14, color: C.MUTED });
  });
  const x = 0.6 + 3 * (tw + gx), y = 1.6 + th + 0.22;
  s.rect(x, y, tw, th, { fill: C.R3, r: 0.12, shadow: true });
  iconCircle(s, x + 0.25, y + 0.25, 0.72, 'faArrowTrendDown', C.R3, 'FFD9D6');
  s.text(x + 0.25, y + 1.1, tw - 0.5, 1.2, 'Margen que se pierde sin saber dónde ni por qué', { size: 17, bold: true, color: C.WHITE });
}

// ===== FLUJO =====
{
  const s = content('flow', 'Apertura', 'La solución', 'Cómo DAILY conecta la operación', 'real',
    'QUÉ DECIR: "Cada paso de la mercancía deja un movimiento en un kardex único. Cuando algo no cuadra, DAILY lo convierte en una alerta o un expediente con responsable." QUÉ MOSTRAR: seguir el anillo desde Recepción hasta Acciones correctivas. BENEFICIO: trazabilidad de punta a punta. PREGUNTA HABITUAL: "¿Necesito cambiar mi POS?" → No. Las ventas pueden registrarse en el POS de DAILY o cargarse por importación o API; la integración exacta se valida en el diagnóstico.');
  const N = [['Recepción', 'faTruckRampBox'], ['Inventario', 'faBoxesStacked'], ['Almacenamiento', 'faWarehouse'], ['Reposición', 'faDolly'], ['Venta', 'faCashRegister'], ['Conciliación', 'faScaleBalanced'], ['Alertas', 'faBell'], ['Acciones correctivas', 'faListCheck']];
  const cx = 6.67, cy = 4.2, rx = 5.0, ry = 2.0, d = 0.95;
  const pos = N.map((n, i) => { const t = Math.PI - i * Math.PI / 4; return [cx + rx * Math.cos(t), cy - ry * Math.sin(t)]; });
  pos.forEach((p, i) => {
    const q = pos[(i + 1) % 8], dx = q[0] - p[0], dy = q[1] - p[1], L = Math.hypot(dx, dy), k = (d / 2 + 0.12) / L;
    s.line(p[0] + dx * k, p[1] + dy * k, q[0] - dx * k, q[1] - dy * k, { color: i === 7 ? C.Y3 : C.G3, lw: 2, arrow: true, dash: i === 7 });
  });
  pos.forEach((p, i) => {
    const alert = i >= 6;
    s.ellipse(p[0] - d / 2, p[1] - d / 2, d, d, { fill: C.WHITE, line: alert ? C.R2 : C.G2, lineW: 2.5, shadow: true });
    s.icon(p[0] - 0.22, p[1] - 0.22, 0.44, N[i][1], alert ? C.R2 : C.G3);
    s.text(p[0] - 1.1, p[1] + d / 2 + 0.06, 2.2, 0.3, N[i][0], { size: 13, bold: true, align: 'center' });
  });
  s.rect(cx - 1.85, cy - 0.72, 3.7, 1.44, { fill: C.G3, r: 0.16, shadow: true });
  s.text(cx - 1.7, cy - 0.62, 3.4, 0.5, 'DAILY', { size: 24, bold: true, color: C.WHITE, align: 'center', spacing: 2 });
  s.text(cx - 1.7, cy - 0.1, 3.4, 0.7, 'Un kardex único · alertas · expedientes · indicadores', { size: 13, color: 'D7F5DF', align: 'center' });
}

// ===== MAPA DE MÓDULOS =====
{
  const s = content('modules', 'Apertura', 'La plataforma', 'Diez áreas de trabajo, un mismo dato', 'dev',
    'QUÉ DECIR: "Este es el menú real de DAILY, agrupado como trabaja una cadena: planear y comprar, recibir y almacenar, vender, despachar, cuidar la calidad, controlar mermas, prevenir pérdidas, organizar al personal, reportar y administrar." QUÉ MOSTRAR: las diez áreas; la etiqueta amarilla indica las piezas que están en desarrollo. BENEFICIO: el cliente ve el alcance completo antes de entrar al detalle. ACLARAR: el detalle de lo disponible, en desarrollo y propuesto está en la diapositiva de estado de funciones.');
  const M = [
    ['faCartShopping', '1. Planeación y compras', 'MRP, proveedores, órdenes', 0],
    ['faBoxesStacked', '2. Inventario y almacenes', 'Stock, kardex, conciliación, ubicaciones', 0],
    ['faCashRegister', '3. Ventas y rentabilidad', 'Ventas, margen, POS, pedidos', 0],
    ['faTruckFast', '4. Despachos y devoluciones', 'Despachos, devoluciones de clientes', 0],
    ['faAward', '5. Calidad y vencimientos', 'Lotes, FEFO, cadena de frío, retiros', 0],
    ['faTrashCan', '6. Mermas y prevención', 'Registro, aprobación, causales', 0],
    ['faShieldHalved', '7. Prevención de pérdidas', 'Expedientes, IMEI, conteos sensibles', 1],
    ['faUsers', '8. Operaciones y personal', 'Horarios, asistencia, tareas', 0],
    ['faChartPie', '9. Reportes y analítica', 'Informe ejecutivo, reportes', 1],
    ['faGears', '10. Administración', 'Sucursales, usuarios, accesos, auditoría', 0]
  ];
  M.forEach((m, i) => {
    const x = 0.6 + (i % 5) * 2.45, y = 1.55 + Math.floor(i / 5) * 2.65;
    s.rect(x, y, 2.3, 2.45, { fill: C.WHITE, line: C.LINE, r: 0.12, shadow: true });
    iconCircle(s, x + 0.22, y + 0.22, 0.66, m[0], C.WHITE, C.G2);
    s.text(x + 0.22, y + 1.0, 1.95, 0.62, m[1], { size: 14, bold: true });
    s.text(x + 0.22, y + 1.62, 1.95, 0.62, m[2], { size: 11.5, color: C.MUTED });
    if (m[3]) s.pill(x + 1.0, y + 0.3, 1.18, 'Parte en desarrollo', { fill: C.YT, color: C.Y3, size: 8.5, h: 0.26 });
  });
}

// ===== HOY =====
{
  const s = content('today', 'Supervisión', 'Vista ejecutiva del día', 'HOY: cómo va la operación, hora a hora', 'real',
    'QUÉ DECIR: "Un gerente abre HOY y ve ventas, cumplimiento de meta, margen, ticket y merma sobre ventas, con la curva real frente a la meta por hora y los avisos que requieren atención." QUÉ MOSTRAR: (1) decisiones críticas del turno; (2) indicadores contra el período anterior; (3) ventas reales contra meta; (4) avisos con acceso directo. BENEFICIO: decidir durante el día, no al cierre. ACLARAR: el Panel Ejecutivo mensual está en rediseño; para la demostración usamos HOY y el Informe Ejecutivo de Pérdidas, que leen datos registrados.');
  const e = s.shot(0.6, 1.5, 8.5, 5.3, '01_ejecutivo/today', [260, 60, 1640]);
  s.pin(e, 285, 175, 1); s.pin(e, 285, 380, 2); s.pin(e, 285, 625, 3); s.pin(e, 1290, 1025, 4);
  callouts(s, 9.4, 1.55, 3.33, [
    ['Decisiones críticas', 'Lo que necesita intervención en el turno.'],
    ['Indicadores', 'Venta, meta, margen, ticket y merma.'],
    ['Real vs. meta por hora', 'Con proyección de cierre del día.'],
    ['Avisos con acceso', 'Mermas por aprobar, alertas y tareas vencidas.']
  ], C.R2, 1.3);
}

// ===== ALERTAS =====
{
  const s = content('alerts', 'Supervisión', 'Centro de alertas', 'Cada desviación con responsable, plazo e impacto', 'real',
    'QUÉ DECIR: "Cada alerta tiene criticidad, responsable, tiempo límite e impacto estimado. El panel derecho muestra el progreso: detectada, asignada, investigación, validación y cierre." QUÉ MOSTRAR: el resumen superior, la lista y el panel de la alerta seleccionada. BENEFICIO: nada queda sin dueño y el atraso se ve. PREGUNTA HABITUAL: "¿Quién recibe las alertas?" → Se asignan a un responsable y también aparecen en la web móvil.');
  flowRow(s, 0.6, 1.4, 12.13, [['Detección', 'faSatelliteDish'], ['Asignación', 'faUserCheck'], ['Investigación', 'faMagnifyingGlass'], ['Validación', 'faClipboardCheck'], ['Cierre', 'faCircleCheck']], { size: 13, h: 0.8, gap: 0.26 });
  s.shot(0.6, 2.42, 12.13, 4.38, '08_alertas/alerts', [260, 60, 1630]);
}

// ===== EXPEDIENTES =====
{
  const s = content('process', 'Supervisión', 'Expedientes y procesos', 'Cada documento abierto: en qué paso está y quién lo tiene', 'real',
    'QUÉ DECIR: "DAILY sigue cada documento de la operación —orden de compra, pedido, merma, traslado, devolución, ajuste, conteo, tarea— y muestra su paso actual, el siguiente, el responsable y la fecha límite. Arriba, el Centro de Excepciones agrupa lo que quedó bloqueado o inconsistente: un turno de caja sin cerrar, una merma descontada sin aprobación, un traslado sin recibir." QUÉ MOSTRAR: las excepciones y los contadores por tipo de expediente. BENEFICIO: los procesos dejan de depender de la memoria de las personas. ACLARAR: el progreso se calcula a partir del estado real de cada documento; no es un registro manual.');
  s.shot(0.6, 1.5, 7.6, 5.3, '12_procesos/process_flow', [255, 160, 1645]);
  callouts(s, 8.5, 1.55, 4.23, [
    ['Centro de Excepciones', 'Operaciones bloqueadas o inconsistentes, con valor comprometido.'],
    ['Diez tipos de expediente', 'Compra, pedido, merma, traslado, devolución, ajuste, conteo, tarea y más.'],
    ['Paso, responsable y límite', 'El vencido se marca en rojo.']
  ], C.G3, 1.45);
  note(s, 8.5, 6.1, 4.23, 'El progreso sale del estado real de cada documento.', 'G');
}

// ===== CASO 1: PASO 1 =====
{
  const s = content('step1', 'Caso completo: diferencia de inventario', 'Caso 1 · Producto sensible', 'Se esperaban 8 celulares; el conteo encontró 6', 'real',
    'QUÉ DECIR: "Seguimos un caso de principio a fin. El sistema espera 8 unidades de un celular de alto valor en la tienda; el conteo físico registra 6. Al guardar el conteo, DAILY calcula la diferencia, su valor y abre el caso DEMO-001." QUÉ MOSTRAR: el aviso rojo en la ficha del producto: diferencia, valor, intervalo de auditoría, prioridad y responsable. BENEFICIO: la diferencia aparece al contar, no al cierre. ACLARAR: caso de demostración sobre datos ficticios, ejecutado con la lógica real. La diferencia surge cuando se registra el conteo; no es seguimiento automático en tiempo real.');
  stepper(s, 0);
  stat(s, 0.6, 1.85, 1.75, '8', 'esperadas');
  stat(s, 0.6, 3.1, 1.75, '6', 'contadas');
  stat(s, 0.6, 4.35, 1.75, '−2', 'diferencia', C.R2);
  stat(s, 0.6, 5.6, 1.75, '$1.040', 'valor en riesgo', C.R2);
  const e = s.shot(2.65, 1.85, 10.08, 4.87, '03_inventario/conciliacion', [560, 455, 1340]);
  s.ring(e, 566, 612, 1894, 736);
  s.demoTag(9.55, 2.0);
}

// ===== CASO 1: PASO 2 =====
{
  const s = content('step2', 'Caso completo: diferencia de inventario', 'Caso 1 · Producto sensible', 'Cada movimiento del producto, con documento y usuario', 'real',
    'QUÉ DECIR: "El responsable no busca en planillas: abre el kardex del producto. Ve recepciones, ventas del POS, traslados y ajustes, con fecha, documento, lote, usuario y saldo valorizado, y la conciliación del período." QUÉ MOSTRAR: el resumen del período y las líneas del kardex. BENEFICIO: la investigación parte de movimientos registrados. PREGUNTA HABITUAL: "¿Se pueden alterar los movimientos?" → El historial está sellado y su integridad puede verificarse a pedido.');
  stepper(s, 1);
  s.shot(0.6, 1.85, 8.3, 4.95, '03_inventario/kardex_producto', [260, 1380, 1640]);
  callouts(s, 9.2, 1.9, 3.53, [
    ['Kardex valorizado', 'Entradas, salidas y saldo, con costo.'],
    ['Documento de origen', 'Ticket POS, recepción o ajuste: con un clic.'],
    ['Hallazgos', 'Posibles duplicados, merma sin caso, fuera de horario.'],
    ['Historial sellado', 'Verificación de integridad a pedido.']
  ], C.G3, 1.2);
}

// ===== CASO 1: PASO 3 =====
{
  const s = content('step3', 'Caso completo: diferencia de inventario', 'Caso 1 · Producto sensible', 'Ficha de investigación: intervalo, causas y reconteo', 'real',
    'QUÉ DECIR: "La ficha delimita el intervalo auditado entre el último conteo correcto y el actual, cuenta los movimientos de esa ventana y evalúa causas con la evidencia. No presume sustracción. Pide un reconteo independiente y, si se ratifica, un supervisor distinto autoriza el ajuste." QUÉ MOSTRAR: el intervalo, la evaluación de causas y los dos bloques de resolución. BENEFICIO: causas documentadas en lugar de suposiciones, con segregación de funciones. ACLARAR: DAILY orienta la investigación; la causa y el ajuste los decide una persona.');
  stepper(s, 2);
  s.shot(0.6, 1.85, 6.3, 4.95, '03_inventario/conciliacion_ficha', [512, 28, 900]);
  callouts(s, 7.2, 1.9, 5.53, [
    ['Intervalo auditado', 'Del último conteo verificado al actual, con los movimientos de la ventana.'],
    ['Evaluación por evidencia', 'Hipótesis de causa; no presume sustracción.'],
    ['Reconteo independiente', 'Confirma o descarta la diferencia.'],
    ['Autorización separada', 'El ajuste lo aprueba un supervisor distinto.']
  ], C.G3, 1.2);
}

// ===== CASO 1: PASO 4 =====
{
  const s = content('step4', 'Caso completo: diferencia de inventario', 'Caso 1 · Producto sensible', 'El caso queda en un expediente hasta su cierre', 'real',
    'QUÉ DECIR: "El caso entra al Centro de Prevención de Pérdidas junto con los demás: producto, tienda, zona, diferencia, valor en riesgo, prioridad, estado y responsable. Se puede resolver, enviar a reconteo o escalar, y cada paso queda en la historia del expediente." QUÉ MOSTRAR: la tabla de incidencias y expedientes; a la derecha, el progreso de una alerta. BENEFICIO: nadie ajusta inventario sin dejar rastro. ACLARAR: los indicadores del encabezado del Centro de Prevención todavía muestran valores de muestra; la tabla de expedientes sí lee los casos registrados.');
  stepper(s, 3);
  s.shot(0.6, 1.85, 8.0, 4.95, '11_prevencion/lp', [275, 648, 1600]);
  label(s, 8.9, 1.85, 3.8, 'Progreso de cada alerta');
  s.shot(8.9, 2.17, 3.83, 3.9, '08_alertas/alerts', [1362, 560, 560]);
  s.text(8.9, 6.2, 3.83, 0.6, 'Detectada → asignada → investigación → validación → cerrada.', { size: 12, color: C.MUTED });
}

// ===== CASO 1: RESUMEN =====
{
  const s = content('case1sum', 'Caso completo: diferencia de inventario', 'Caso 1 · Resumen', 'Del conteo al cierre: seis pasos, cuatro módulos', 'real',
    'QUÉ DECIR: "En resumen: el conteo detecta, la conciliación calcula, el kardex explica, la ficha investiga, el supervisor autoriza y el expediente cierra. Cuatro módulos trabajando sobre el mismo dato." QUÉ MOSTRAR: la secuencia y el módulo que interviene en cada paso. BENEFICIO: un proceso repetible y auditable para cada diferencia. PREGUNTA PARA EL CLIENTE: "¿Cuántos pasos de este proceso hacen hoy fuera del sistema?"');
  const T = [['Conteo físico', 'faClipboardCheck', 'Conteos sensibles'], ['Diferencia y valor', 'faScaleUnbalanced', 'Conciliación'], ['Movimientos', 'faRightLeft', 'Kardex'], ['Investigación', 'faMagnifyingGlass', 'Ficha del caso'], ['Autorización', 'faUserShield', 'Supervisor distinto'], ['Cierre', 'faCircleCheck', 'Prevención de Pérdidas']];
  T.forEach((t, i) => {
    const x = 0.6 + i * 2.05, last = i === T.length - 1;
    s.rect(x, 1.8, 1.85, 3.2, { fill: last ? C.G3 : C.WHITE, line: C.LINE, r: 0.12, shadow: true });
    iconCircle(s, x + 0.55, 2.05, 0.75, t[1], last ? C.G3 : C.WHITE, last ? C.WHITE : C.G2);
    s.text(x + 0.12, 3.0, 1.61, 0.3, String(i + 1), { size: 22, bold: true, color: last ? C.WHITE : 'B8C2BC', align: 'center' });
    s.text(x + 0.12, 3.45, 1.61, 0.7, t[0], { size: 15, bold: true, align: 'center', color: last ? C.WHITE : C.INK });
    s.text(x + 0.12, 4.2, 1.61, 0.6, t[2], { size: 12, align: 'center', color: last ? 'D7F5DF' : C.G3, bold: true });
    if (!last) s.line(x + 1.88, 3.4, x + 2.02, 3.4, { color: C.G3, lw: 2, arrow: true });
  });
  stat(s, 0.6, 5.4, 3.9, 'DEMO-001', 'expediente del caso');
  stat(s, 4.72, 5.4, 3.9, '−2 u. · $1.040', 'diferencia y valor', C.R2);
  stat(s, 8.83, 5.4, 3.9, '2 controles', 'reconteo y aprobación antes del ajuste', C.G3);
}

// ===== MERMAS =====
{
  const s = content('waste', 'Mermas', 'Control de mermas', 'Cuánto se pierde, dónde y por qué', 'real',
    'QUÉ DECIR: "El Cuadro de Control de Mermas muestra merma total y sobre ventas contra la meta, por tipo, departamento, producto y causa, y la tendencia por sucursal." QUÉ MOSTRAR: indicadores, evolución contra objetivo, distribución por tipo y por departamento. BENEFICIO: atacar las causas que más cuestan. PREGUNTA HABITUAL: "¿Quién aprueba una merma?" → Un responsable distinto de quien la registra; hasta entonces el stock no se descuenta.');
  s.shot(0.6, 1.5, 8.5, 5.3, '02_mermas/waste_centro-control', [260, 440, 1640]);
  callouts(s, 9.4, 1.55, 3.33, [
    ['Merma vs. meta', 'Total, sobre ventas y evitable.'],
    ['Por tipo y área', 'Operativa, comercial, averías, ajustes.'],
    ['Productos y causas', 'Top 10 y causas principales.'],
    ['Por sucursal', 'Tendencia contra el período anterior.']
  ], C.R2, 1.3);
}

// ===== MERMAS: HERRAMIENTAS =====
{
  const s = content('waste2', 'Mermas', 'Control de mermas', 'Aprobación, causales, investigación y prevención', 'real',
    'QUÉ DECIR: "Alrededor del cuadro hay cuatro herramientas: la bandeja de aprobaciones, la matriz de causales con Pareto, las investigaciones con plan de acción y la prevención por vencimiento con FEFO." QUÉ MOSTRAR: las cuatro pantallas. BENEFICIO: el registro de merma se convierte en decisiones. PREGUNTA PARA EL CLIENTE: "¿Hoy quién aprueba las mermas y cuánto tarda?"');
  const G = [['02_mermas/waste_approvals', 'Aprobaciones', FULLW], ['02_mermas/waste_causales', 'Causales y Pareto', [250, 420, 1650]], ['02_mermas/waste_investigaciones', 'Investigaciones y planes de acción', [250, 420, 1650]], ['02_mermas/waste_fefo', 'Prevención por vencimiento (FEFO)', [250, 420, 1650]]];
  G.forEach((g, i) => {
    const x = 0.6 + (i % 2) * 6.15, y = 1.45 + Math.floor(i / 2) * 2.74;
    s.shot(x, y, 5.98, 2.28, g[0], g[2]);
    s.text(x, y + 2.33, 5.98, 0.3, g[1], { size: 13, bold: true });
  });
}

// ===== INVENTARIO =====
{
  const s = content('stock', 'Inventario', 'Inventario', 'Qué hay, cuánto vale y qué está en riesgo', 'real',
    'QUÉ DECIR: "Stock & Movimientos muestra valor, unidades, rotación y cobertura; debajo, bajo mínimo, próximos a vencer, agotados y valor en riesgo, cada uno con la acción sugerida." QUÉ MOSTRAR: las tarjetas de riesgo y los movimientos recientes. BENEFICIO: el responsable de inventario prioriza con datos. PREGUNTA HABITUAL: "¿Lo veo por sucursal?" → Sí, con filtros por sucursal, departamento y categoría.');
  s.shot(0.6, 1.5, 7.9, 5.3, '03_inventario/stockoverview', [260, 90, 1640]);
  label(s, 8.8, 1.5, 3.9, 'Kardex con hallazgos');
  s.shot(8.8, 1.82, 3.93, 2.2, '03_inventario/kardex', [260, 290, 1000]);
  label(s, 8.8, 4.2, 3.9, 'Control de inventario');
  s.shot(8.8, 4.52, 3.93, 2.28, '03_inventario/inventory_control', FULLW);
}

// ===== ALMACÉN =====
{
  const s = content('wms', 'Inventario', 'Almacén', 'Ubicaciones, traslados, conteos y escáner', 'real',
    'QUÉ DECIR: "El almacén se opera por ubicación: mapa de zonas, traslados entre tiendas con verificación, conteos físicos y operaciones con escáner." QUÉ MOSTRAR: las cuatro herramientas. BENEFICIO: cada movimiento queda ligado a una ubicación y un responsable. PREGUNTA HABITUAL: "¿Qué lectores acepta?" → Cámara del teléfono y lectores en modo teclado; el modelo exacto se valida en el piloto.');
  const G = [['03_inventario/location_map', 'Mapa de ubicaciones'], ['03_inventario/transfers', 'Traslados entre tiendas'], ['03_inventario/scan', 'Operaciones con escáner'], ['03_inventario/inventory', 'Conteos físicos']];
  G.forEach((g, i) => {
    const x = 0.6 + (i % 2) * 6.15, y = 1.45 + Math.floor(i / 2) * 2.74;
    s.shot(x, y, 5.98, 2.28, g[0], FULLW);
    s.text(x, y + 2.33, 5.98, 0.3, g[1], { size: 13, bold: true });
  });
}

// ===== PREVENCIÓN =====
{
  const s = content('prevention', 'Prevención de pérdidas', 'Prevención de pérdidas', 'Productos de alto valor, controlados por unidad', 'dev',
    'QUÉ DECIR: "Para celulares y otros productos de alto valor, DAILY sigue cada unidad por IMEI o número de serie: dónde está, en qué estado y quién la custodia. Los no localizados abren investigación. Además programa conteos sensibles con vencimiento y reconteo." QUÉ MOSTRAR: los estados de los equipos y la tabla con custodia y último movimiento. BENEFICIO: control unitario donde una sola pérdida cuesta mucho. ACLARAR: los expedientes admiten referencias de CCTV como evidencia, pero no hay integración con cámaras. Algunos indicadores del encabezado del Centro de Prevención aún muestran valores de muestra.');
  s.shot(0.6, 1.5, 7.9, 5.3, '11_prevencion/lp_devices', [275, 320, 1600]);
  callouts(s, 8.8, 1.55, 3.93, [
    ['Custodia por IMEI', 'Ubicación, estado y responsable de cada equipo.'],
    ['No localizados', 'Se marcan y abren investigación.'],
    ['Conteos sensibles', 'Tareas con plazo; vencidas a la vista.'],
    ['Evidencia en el caso', 'Notas, fotos y referencias de CCTV.']
  ], C.G3, 1.12);
  note(s, 8.8, 6.18, 3.93, 'En desarrollo: indicadores del encabezado del Centro de Prevención.');
}

// ===== INFORME DE PÉRDIDAS =====
{
  const s = content('lossreport', 'Prevención de pérdidas', 'Reportes', 'Informe ejecutivo de pérdidas: un corte diario para la dirección', 'real',
    'QUÉ DECIR: "Cada día se genera un informe consolidado de pérdidas con las mismas fuentes que Mermas e Inventario: pérdida confirmada, merma sobre ventas, diferencias en investigación, prioridades con responsable y comparación entre sucursales. Indica si el corte es preliminar y qué tiendas no sincronizaron." QUÉ MOSTRAR: los indicadores, la tabla de prioridades y el estado de sincronización. BENEFICIO: la dirección recibe el mismo número que ve la operación. PREGUNTA HABITUAL: "¿Se puede exportar?" → Sí, en PDF y Excel; diario, semanal, mensual o anual.');
  s.shot(0.6, 1.5, 8.3, 5.3, '02_mermas/loss_executive', [260, 60, 1640]);
  callouts(s, 9.2, 1.55, 3.53, [
    ['Corte con trazabilidad', 'Indica fuente, hora y tiendas sincronizadas.'],
    ['Pérdida por componente', 'Operativa, comercial, ajustes, hurto.'],
    ['Prioridades del día', 'Qué ocurrió, causa, evidencia y responsable.']
  ], C.G3, 1.45);
  note(s, 9.2, 6.18, 3.53, 'Diario · semanal · mensual · anual · PDF y Excel', 'G');
}

// ===== VENTAS =====
{
  const s = content('sales', 'Ventas', 'Ventas y rentabilidad', 'Qué se vende, cuánto deja y dónde está la brecha', 'real',
    'QUÉ DECIR: "Venta neta, cumplimiento de meta, utilidad, margen, transacciones y ticket, contra el período anterior; debajo, la curva frente a la meta, la participación por departamento y el rendimiento por sucursal." QUÉ MOSTRAR: indicadores, curva y departamentos. BENEFICIO: ver a tiempo la brecha frente a la meta. PREGUNTA HABITUAL: "¿Se concilia con inventario?" → Sí; el bloque de calidad de datos indica si cada venta tiene costo y movimiento conciliado.');
  s.shot(0.6, 1.5, 8.3, 5.3, '05_ventas/sales', [260, 520, 1640]);
  callouts(s, 9.2, 1.55, 3.53, [
    ['Venta y margen', 'Contra período anterior y meta.'],
    ['Curva y avance', 'Evolución diaria y brecha a la meta.'],
    ['Departamentos', 'Qué sostiene la venta y qué no.']
  ], C.G3, 1.4);
  note(s, 9.2, 5.95, 3.53, 'Ventas conciliadas con costo y movimiento de inventario.', 'G');
}

// ===== COMPRAS =====
{
  const s = content('purchasing', 'Compras', 'Planeación y compras', 'De la necesidad a la orden, en un ciclo de 12 fases', 'real',
    'QUÉ DECIR: "El MRP calcula, producto por producto, stock neto, venta diaria, cobertura, stock de seguridad y punto de reorden, y sugiere cantidad, costo y proveedor. Las órdenes pasan por aprobación, emisión, seguimiento y recepción, y el desempeño del proveedor se mide en plazo y cumplimiento." QUÉ MOSTRAR: indicadores del ciclo, las 12 fases y la lista de necesidades. BENEFICIO: comprar lo necesario, a tiempo. ACLARAR: las órdenes sugeridas requieren aprobación de una persona; la precisión del pronóstico se muestra tal cual, para mejorarla con los datos del cliente.');
  s.shot(0.6, 1.5, 8.3, 5.3, '09_compras/mrp', [260, 60, 1640]);
  callouts(s, 9.2, 1.55, 3.53, [
    ['Necesidades MRP', 'Cobertura, seguridad y punto de reorden.'],
    ['Órdenes con aprobación', 'Borrador, aprobación, emisión y seguimiento.'],
    ['Proveedores medidos', 'Entregas a tiempo, plazo y tasa de abasto.']
  ], C.G3, 1.45);
}

// ===== PERSONAL =====
{
  const s = content('staff', 'Personal', 'Personal y horarios', 'Turnos, cobertura y costo laboral en un solo tablero', 'real',
    'QUÉ DECIR: "Horarios y Cobertura compara ventas proyectadas con horas laborales, mide productividad por departamento, costo laboral frente a presupuesto y muestra la programación semanal y mensual." QUÉ MOSTRAR: el gráfico y la programación. BENEFICIO: ver excesos y faltas de cobertura antes de publicar el horario. ACLARAR: las reglas del generador son configurables y deben validarse con el área jurídica del cliente.');
  label(s, 0.6, 1.45, 7.6, 'Ventas proyectadas vs. horas, productividad y costo laboral');
  s.shot(0.6, 1.76, 7.6, 2.62, '06_personal/scheduling', [260, 590, 1640]);
  label(s, 0.6, 4.5, 7.6, 'Programación semanal');
  s.shot(0.6, 4.82, 7.6, 1.98, '06_personal/scheduling', [260, 1060, 1640]);
  callouts(s, 8.6, 1.55, 4.13, [
    ['Demanda vs. horas', 'Ventas proyectadas frente a horas programadas.'],
    ['Costo vs. presupuesto', 'Sueldos, cargas y horas extra.'],
    ['Cobertura por área', 'Alertas de dotación mínima.'],
    ['Reglas configurables', 'A validar con el área jurídica.']
  ], C.G3, 1.25);
}

// ===== PRODUCTIVIDAD =====
{
  const s = content('staff2', 'Personal', 'Productividad', 'Asistencia, tareas y planificación según demanda', 'mix',
    'QUÉ DECIR: "Hoy DAILY registra asistencia, tardanzas y tareas de piso, y compara ventas proyectadas con horas laborales. Lo que aún no existe es el estudio de tiempos y movimientos ni la generación automática de turnos directamente desde la demanda." QUÉ MOSTRAR: a la izquierda lo disponible; a la derecha el concepto. BENEFICIO: seguimiento diario del trabajo real. ACLARAR: la columna proyectada es conceptual; no mostrarla como función terminada.');
  label(s, 0.6, 1.5, 4, 'Asistencia y fichajes');
  s.shot(0.6, 1.82, 4.05, 2.3, '06_personal/attendance', [260, 60, 1620]);
  label(s, 0.6, 4.25, 4, 'Tareas de piso');
  s.shot(0.6, 4.57, 4.05, 2.23, '06_personal/workforce', [260, 60, 1620]);
  s.rect(4.95, 1.5, 3.65, 5.3, { fill: C.GT, r: 0.12 });
  s.text(5.15, 1.62, 3.3, 0.35, 'Disponible hoy', { size: 16, bold: true, color: C.GD });
  s.text(5.15, 2.05, 3.3, 4.6, [{ text: 'Horas programadas y trabajadas', bullet: true }, { text: 'Tardanzas y ausencias', bullet: true }, { text: 'Tareas con responsable', bullet: true }, { text: 'Productividad por departamento', bullet: true }, { text: 'Ventas proyectadas vs. horas', bullet: true }], { size: 15, paraAfter: 8 });
  s.rect(8.85, 1.5, 3.88, 5.3, { fill: C.WHITE, line: C.Y2, lineW: 1.5, dash: true, r: 0.12 });
  s.text(9.05, 1.62, 3.5, 0.35, 'Proyectado (concepto)', { size: 16, bold: true, color: C.Y3 });
  s.text(9.05, 2.05, 3.5, 2.3, [{ text: 'Estudio de tiempos y movimientos', bullet: true }, { text: 'Turnos generados desde la demanda', bullet: true }], { size: 15, paraAfter: 8 });
  const days = [[9, 55], [11, 90], [13, 120], [15, 80], [17, 110], [19, 70]];
  days.forEach((d, i) => {
    const x = 9.1 + i * 0.57, base = 6.3, h = d[1] / 120 * 1.5;
    s.rect(x, base - h, 0.42, h, { fill: d[1] >= 110 ? C.Y2 : 'E5E7EB' });
    s.text(x - 0.05, base + 0.05, 0.52, 0.25, d[0] + 'h', { size: 10, color: C.MUTED, align: 'center' });
  });
  s.text(9.05, 4.3, 3.5, 0.3, 'Ejemplo: refuerzo en horas pico', { size: 12, color: C.MUTED, italic: true });
}

// ===== CALIDAD =====
{
  const s = content('quality', 'Calidad', 'Calidad y vencimientos', 'Actuar antes de que el producto venza', 'real',
    'QUÉ DECIR: "DAILY identifica los lotes vencidos o próximos a vencer, con ubicación y valor en riesgo, y sigue una ruta de atención: revisar, evaluar rebaja, aislar, decidir destino y registrar temperatura." QUÉ MOSTRAR: lotes que requieren atención, la matriz y la ruta. BENEFICIO: menos desperdicio y más protección al cliente. PREGUNTA HABITUAL: "¿Controla la cadena de frío?" → Registra temperaturas y excursiones por cámara; la captura automática con sensores se define en cada proyecto.');
  flowRow(s, 0.6, 1.45, 12.13, [['Próximo a vencer', 'faHourglassHalf', C.Y2], ['Identificación', 'faMagnifyingGlass', C.Y2], ['Rebaja o aislamiento', 'faTags', C.Y2], ['Destino autorizado', 'faUserCheck', C.G2], ['Registro de merma', 'faListCheck', C.G2]], { size: 13 });
  s.shot(0.6, 2.55, 7.6, 4.25, '04_calidad/quality', [250, 30, 1650]);
  label(s, 8.5, 2.55, 4.2, 'Matriz de lotes y cadena de frío');
  s.shot(8.5, 2.87, 4.23, 3.93, '04_calidad/quality', [260, 800, 1640]);
}

// ===== CASO 2 =====
{
  const s = content('case2', 'Caso completo: vencimiento', 'Caso 2 · Producto perecedero', 'De un lote por vencer a una merma aprobada', 'real',
    'QUÉ DECIR: "Segundo caso: un lote de leche vence. Calidad lo marca en la matriz; HOY y el Centro de Alertas lo muestran como aviso con responsable; si no se vende con rebaja, se registra la merma; un responsable distinto la aprueba y recién entonces se descuenta del stock; el cuadro de mermas y el informe ejecutivo lo reflejan." QUÉ MOSTRAR: las cuatro pantallas, de izquierda a derecha. BENEFICIO: el vencimiento se gestiona como proceso, no como sorpresa. ACLARAR: el bloqueo automático de venta de lotes vencidos en el POS existe como regla, pero está desactivado hasta definirlo con cada cliente.');
  const T = [['1. Lote vencido', 'Calidad', '04_calidad/quality', [260, 820, 1120]], ['2. Aviso con responsable', 'HOY', '01_ejecutivo/today', [1270, 1010, 630]], ['3. Merma por aprobar', 'Aprobaciones', '02_mermas/waste_approvals', [250, 40, 1000]], ['4. Decisión y causa', 'Cuadro de Mermas', '02_mermas/waste_centro-control', [1330, 1490, 580]]];
  T.forEach((t, i) => {
    const x = 0.6 + i * 3.08, w = 2.86;
    s.rect(x, 1.45, w, 0.62, { fill: i === 3 ? C.G3 : C.WHITE, line: C.LINE, r: 0.1 });
    s.text(x + 0.15, 1.45, w - 0.3, 0.62, t[0], { size: 15, bold: true, valign: 'middle', color: i === 3 ? C.WHITE : C.INK });
    s.shot(x, 2.3, w, 3.6, t[2], t[3]);
    s.text(x, 6.0, w, 0.3, t[1], { size: 13, bold: true, color: C.G3, align: 'center' });
    if (i < 3) s.line(x + w + 0.03, 1.76, x + 3.05, 1.76, { color: C.G3, lw: 2, arrow: true });
  });
  s.text(0.6, 6.4, 12.1, 0.35, 'Una sola regla: la merma no descuenta stock hasta que la aprueba alguien distinto de quien la registró.', { size: 13, color: C.MUTED, align: 'center', italic: true });
}

// ===== MULTISUCURSAL =====
{
  const s = content('branches', 'Multisucursal', 'Operación multisucursal', 'Todas las tiendas, con los mismos indicadores', 'real',
    'QUÉ DECIR: "Cada sucursal se compara con los mismos indicadores: venta, margen, ticket, merma sobre ventas, casos abiertos y valor por vencer. La que supera el tope corporativo de merma se marca en rojo." QUÉ MOSTRAR: las tarjetas; la red de demostración tiene diez tiendas. BENEFICIO: la gerencia ve la red en una pantalla. ACLARAR: sucursales y cifras ficticias; no son resultados de clientes.');
  s.shot(0.6, 1.5, 8.9, 5.3, '07_multisucursal/branches', [260, 180, 1640]);
  callouts(s, 9.8, 1.55, 2.93, [
    ['Mismas métricas', 'Venta, margen, merma, casos y vencimientos.'],
    ['Tope corporativo', 'La tienda que lo supera se marca.'],
    ['Del total al detalle', 'De la sucursal a stock, mermas y ventas.']
  ], C.R2, 1.5);
  s.pill(9.8, 6.32, 2.93, 'Sucursales ficticias de demostración', { fill: C.YT, color: C.Y3, size: 10.5, h: 0.32 });
}

// ===== MÓVIL =====
{
  const s = content('mobile', 'Móvil', 'Experiencia móvil', 'La operación, desde el teléfono del equipo', 'real',
    'QUÉ DECIR: "El empleado ve sus prioridades, tareas y alertas, escanea productos, consulta inventario y atiende casos desde la web móvil." QUÉ MOSTRAR: las seis pantallas. BENEFICIO: el registro se hace donde ocurre el trabajo. PREGUNTA HABITUAL: "¿Está en las tiendas de aplicaciones?" → Hoy se usa desde el navegador del teléfono; la publicación en tiendas no está incluida.');
  const P = [['m_home', 'Hoy'], ['m_tasks', 'Tareas'], ['m_scan', 'Escáner'], ['m_notifications', 'Alertas'], ['m_inv', 'Inventario'], ['m_waste', 'Mermas']];
  P.forEach((p, i) => {
    const x = 0.68 + i * 2.05, w = 1.9;
    s.phone(x, 1.5, w, '10_movil/' + p[0]);
    s.text(x, 5.72, w, 0.3, p[1], { size: 14, bold: true, align: 'center' });
  });
  s.text(0.6, 6.2, 12.1, 0.6, 'Sujeto a las funciones habilitadas para cada rol. Web móvil; no es una app publicada en tiendas.', { size: 13, color: C.MUTED, align: 'center' });
}

// ===== MÓDULOS CONECTADOS =====
{
  const s = content('connect', 'Integración', 'Módulos conectados', 'Un movimiento alimenta a todos los módulos', 'real',
    'QUÉ DECIR: "Lo que diferencia a DAILY es que los módulos no son islas. La recepción entra al kardex; la venta lo descuenta; la merma aprobada ajusta stock y aparece en el informe; una diferencia abre un expediente; las ventas proyectadas alimentan la comparación con horas; y todo llega a HOY y a los reportes." QUÉ MOSTRAR: el diagrama y las seis conexiones. BENEFICIO: un solo dato, sin conciliaciones manuales entre áreas. PREGUNTA PARA EL CLIENTE: "¿Cuántas planillas cruzan hoy para cerrar el mes?"');
  const N = [['Compras', 'faCartShopping'], ['Inventario', 'faBoxesStacked'], ['Ventas', 'faCashRegister'], ['Mermas', 'faTrashCan'], ['Prevención', 'faShieldHalved'], ['Personal', 'faUsers'], ['Reportes', 'faChartPie']];
  const cx = 3.75, cy = 4.15, r = 2.25, d = 0.92;
  const pos = N.map((n, i) => { const t = -Math.PI / 2 + i * 2 * Math.PI / N.length; return [cx + r * Math.cos(t), cy + r * Math.sin(t) * 0.98]; });
  pos.forEach(p => { const dx = p[0] - cx, dy = p[1] - cy, L = Math.hypot(dx, dy); s.line(cx + dx * 0.9 / L, cy + dy * 0.62 / L, p[0] - dx * (d / 2 + 0.05) / L, p[1] - dy * (d / 2 + 0.05) / L, { color: C.G2, lw: 2 }); });
  s.rect(cx - 1.05, cy - 0.6, 2.1, 1.2, { fill: C.G3, r: 0.14, shadow: true });
  s.text(cx - 1.0, cy - 0.55, 2.0, 1.1, 'Kardex único\n+ alertas y expedientes', { size: 13, bold: true, color: C.WHITE, align: 'center', valign: 'middle' });
  pos.forEach((p, i) => {
    s.ellipse(p[0] - d / 2, p[1] - d / 2, d, d, { fill: C.WHITE, line: C.G2, lineW: 2.5, shadow: true });
    s.icon(p[0] - 0.21, p[1] - 0.3, 0.42, N[i][1], C.G3);
    s.text(p[0] - 0.8, p[1] + 0.12, 1.6, 0.25, N[i][0], { size: 10.5, bold: true, align: 'center' });
  });
  const E = [
    ['Compras → Inventario', 'La recepción de la orden entra al stock y al kardex.'],
    ['Ventas → Inventario', 'Cada ticket descuenta su lote; FEFO en el POS.'],
    ['Inventario → Mermas', 'La merma aprobada ajusta stock y entra al informe.'],
    ['Inventario → Prevención', 'Una diferencia de conteo abre un expediente.'],
    ['Ventas → Personal', 'Ventas proyectadas frente a horas programadas.'],
    ['Todo → Reportes', 'HOY, alertas e informe ejecutivo leen el mismo dato.']
  ];
  E.forEach((e, i) => {
    const y = 1.55 + i * 0.88;
    s.rect(7.2, y, 5.53, 0.76, { fill: C.WHITE, line: C.LINE, r: 0.1 });
    s.text(7.38, y + 0.04, 2.3, 0.68, e[0], { size: 13, bold: true, color: C.G3, valign: 'middle' });
    s.text(9.6, y + 0.04, 3.05, 0.68, e[1], { size: 12, color: C.INK, valign: 'middle' });
  });
}

// ===== COMPARATIVA =====
{
  const s = content('compare', 'Integración', 'Referencia de mercado', 'Capacidades que usan las grandes cadenas, y dónde está DAILY', 'mix',
    'QUÉ DECIR: "Las grandes cadenas —por ejemplo Walmart, Costco o Alkosto— operan con prácticas conocidas: inventario perpetuo, conteos cíclicos, áreas de prevención de pérdidas, reposición basada en pronóstico, planificación de personal y control de frescura. Esta tabla compara capacidades, no productos: muestra qué cubre DAILY hoy y qué falta." QUÉ MOSTRAR: la columna de estado. BENEFICIO: una conversación honesta sobre el alcance. ACLARAR: DAILY no tiene integración, alianza ni relación con esas empresas; no afirmamos cómo están implementados sus sistemas internos. Sus marcas pertenecen a sus dueños.');
  sub(s, 'Prácticas habituales en cadenas de gran escala (p. ej., Walmart, Costco, Alkosto), según información pública general.');
  const R = [
    ['Inventario perpetuo por tienda', 'Stock y kardex valorizado por sucursal, con historial verificable', 'D'],
    ['Conteos cíclicos y de alto valor', 'Conteos físicos, conteos sensibles y custodia por IMEI', 'D'],
    ['Prevención de pérdidas', 'Expedientes, reconteo y autorización; CCTV solo como referencia', 'P'],
    ['Reposición según pronóstico', 'MRP con punto de reorden y órdenes sugeridas con aprobación', 'P'],
    ['Personal según demanda', 'Horas vs. ventas proyectadas; turnos desde la demanda: no', 'P'],
    ['Frescura y vencimientos', 'Lotes, FEFO, cuarentena y retiros', 'D'],
    ['Tableros de dirección', 'HOY e Informe Ejecutivo; Panel Ejecutivo en rediseño', 'P'],
    ['Escala e integraciones', '10 sucursales de demostración; importación y API', 'P']
  ];
  const y0 = 1.8, rh = 0.52;
  s.rect(0.6, y0, 12.13, 0.46, { fill: C.G3, r: 0.06 });
  s.text(0.8, y0, 3.9, 0.46, 'Capacidad', { size: 13, bold: true, color: C.WHITE, valign: 'middle' });
  s.text(4.8, y0, 5.9, 0.46, 'Cómo lo resuelve DAILY hoy', { size: 13, bold: true, color: C.WHITE, valign: 'middle' });
  s.text(10.8, y0, 1.8, 0.46, 'Estado', { size: 13, bold: true, color: C.WHITE, valign: 'middle', align: 'center' });
  R.forEach((r, i) => {
    const y = y0 + 0.52 + i * rh;
    s.rect(0.6, y, 12.13, rh - 0.06, { fill: i % 2 ? C.WHITE : 'FAFBFA', line: C.LINE, r: 0.04 });
    s.text(0.8, y, 3.9, rh - 0.06, r[0], { size: 13, bold: true, valign: 'middle' });
    s.text(4.8, y, 5.9, rh - 0.06, r[1], { size: 12, color: C.MUTED, valign: 'middle' });
    const k = r[2] === 'D' ? ['Disponible', C.GT, C.GD] : ['Parcial', C.YT, C.Y3];
    s.pill(11.05, y + 0.1, 1.4, k[0], { fill: k[1], color: k[2], size: 11, h: 0.3 });
  });
  s.text(0.6, 6.62, 12.1, 0.25, 'DAILY no está integrado, aliado ni afiliado con las empresas citadas. Las marcas pertenecen a sus respectivos dueños.', { size: 10.5, color: C.MUTED, italic: true });
}

// ===== ESTADO DE FUNCIONES =====
{
  const s = content('status', 'Integración', 'Transparencia', 'Qué existe, qué está en desarrollo y qué se propone', 'mix',
    'QUÉ DECIR: "Para que la decisión sea informada: a la izquierda lo que hoy funciona y vieron en pantalla; al centro lo que está en desarrollo; a la derecha lo que proponemos construir con el cliente." QUÉ MOSTRAR: las tres columnas. BENEFICIO: confianza; no se vende lo que no existe. ACLARAR: nada de la columna derecha debe presentarse como disponible.');
  const COLS = [
    ['Disponible hoy', C.GT, C.GD, C.G3, ['HOY, Centro de Alertas, Expedientes', 'Stock, kardex, conciliación, almacén', 'Mermas: cuadro, aprobación, causales', 'Prevención: expedientes, IMEI, conteos', 'Informe ejecutivo de pérdidas', 'Ventas, POS, MRP y proveedores', 'Horarios, asistencia y tareas', 'Calidad, FEFO y retiros', 'Multisucursal y web móvil']],
    ['En desarrollo', C.YT, C.Y3, C.Y2, ['Panel Ejecutivo mensual (rediseño)', 'Indicadores de encabezado en Prevención y Mercancías', 'Motor de flujos de trabajo (sin interfaz)', 'Centro de Acción unificado', 'Bloqueo de vencidos en POS (regla inactiva)', 'Integración POS en línea (hoy importación y API)']],
    ['Propuesto', C.RT, C.R3, C.R2, ['Seguridad y salud en el trabajo', 'Estudio de tiempos y movimientos', 'Turnos generados desde la demanda', 'Sensores de frío automáticos', 'Integración con CCTV', 'App en tiendas de aplicaciones']]
  ];
  COLS.forEach((c, i) => {
    const x = 0.6 + i * 4.11, w = 3.91;
    s.rect(x, 1.5, w, 5.3, { fill: c[1], r: 0.14 });
    s.ellipse(x + 0.25, 1.7, 0.3, 0.3, { fill: c[3] });
    s.text(x + 0.68, 1.62, w - 0.8, 0.45, c[0], { size: 19, bold: true, color: c[2] });
    s.text(x + 0.25, 2.25, w - 0.45, 4.45, c[4].map(t => ({ text: t, bullet: true })), { size: 14, paraAfter: 7 });
  });
}

// ===== UN DÍA =====
{
  const s = content('day', 'Valor', 'Escenario ilustrativo', 'Un día utilizando DAILY', 'demo',
    'QUÉ DECIR: recorrer el día de izquierda a derecha, con una pantalla por momento. QUÉ MOSTRAR: que el gerente y el equipo usan la misma información. BENEFICIO: DAILY acompaña la jornada completa, no solo el cierre. ACLARAR: es un escenario ilustrativo; horarios y secuencia cambian en cada empresa.');
  const T = [['07:00', 'Inicio de operaciones', '01_ejecutivo/today', [260, 60, 640]], ['09:00', 'Recepción y escáner', '03_inventario/scan', [260, 60, 640]], ['11:00', 'Inventario en riesgo', '03_inventario/stockoverview', [260, 110, 640]], ['13:00', 'Turnos y cobertura', '06_personal/scheduling', [260, 380, 640]], ['15:00', 'Revisión de alertas', '08_alertas/alerts', [260, 60, 640]], ['18:00', 'Informe del día', '02_mermas/loss_executive', [260, 60, 640]]];
  s.line(0.8, 1.95, 12.5, 1.95, { color: C.G2, lw: 3 });
  T.forEach((t, i) => {
    const x = 0.6 + i * 2.04, w = 1.93;
    s.ellipse(x + w / 2 - 0.11, 1.84, 0.22, 0.22, { fill: C.G3, line: C.WHITE, lineW: 2 });
    s.text(x, 1.4, w, 0.36, t[0], { size: 17, bold: true, color: C.G3, align: 'center' });
    s.shot(x, 2.3, w, 3.3, t[2], t[3]);
    s.text(x, 5.7, w, 0.6, t[1], { size: 14, bold: true, align: 'center' });
  });
  s.text(0.6, 6.45, 12.1, 0.3, 'Escenario ilustrativo.', { size: 13, color: C.MUTED, align: 'center', italic: true });
}

// ===== BENEFICIOS =====
{
  const s = content('benefits', 'Valor', 'Beneficios', 'Seis beneficios, seis herramientas reales', 'real',
    'QUÉ DECIR: cada beneficio se apoya en una herramienta que ya se mostró: reducción de pérdidas, trazabilidad, productividad, control financiero, prevención de vencimientos y decisiones con indicadores. QUÉ MOSTRAR: la pantalla de cada tarjeta. BENEFICIO: valor conectado con el producto, sin promesas abstractas. PREGUNTA PARA EL CLIENTE: "¿Cuál de estos seis le urge más?"');
  const B = [
    ['Reducción de pérdidas', 'Mermas y Prevención', '02_mermas/waste_centro-control', [260, 440, 1640]],
    ['Trazabilidad de punta a punta', 'Kardex y Conciliación', '03_inventario/kardex_producto', [260, 1380, 1640]],
    ['Productividad del equipo', 'Horarios y Tareas', '06_personal/scheduling', [260, 590, 1640]],
    ['Control financiero', 'Ventas e Informe de pérdidas', '05_ventas/sales', [260, 520, 1640]],
    ['Prevención de vencimientos', 'Calidad y FEFO', '04_calidad/quality', [250, 30, 1650]],
    ['Decisiones con indicadores', 'HOY y alertas', '01_ejecutivo/today', [260, 60, 1640]]
  ];
  B.forEach((b, i) => {
    const x = 0.6 + (i % 3) * 4.11, y = 1.5 + Math.floor(i / 3) * 2.72;
    s.rect(x, y, 3.91, 2.58, { fill: C.WHITE, line: C.LINE, r: 0.12, shadow: true });
    s.img(x + 0.1, y + 0.1, 3.71, 1.5, b[2], b[3]);
    s.text(x + 0.15, y + 1.67, 3.61, 0.5, b[0], { size: 15, bold: true });
    s.text(x + 0.15, y + 2.17, 3.61, 0.3, b[1], { size: 13, color: C.G3, bold: true });
  });
}

// ===== ANTES Y DESPUÉS =====
{
  const s = content('beforeafter', 'Valor', 'Antes y después', 'Del registro disperso al control operativo', 'demo',
    'QUÉ DECIR: "No prometemos una cifra. Mostramos el cambio de forma de trabajar: de registros separados a información centralizada, con responsables." QUÉ MOSTRAR: las dos columnas, fila por fila. BENEFICIO: cambio de proceso que luego se mide en el piloto. ACLARAR: no se garantizan resultados económicos.');
  const A = [['faFileLines', 'Registros separados'], ['faPenToSquare', 'Validaciones manuales'], ['faEyeSlash', 'Diferencias al cierre del mes'], ['faUserSlash', 'Casos sin responsable']];
  const D = [['faDatabase', 'Un kardex único'], ['faRoute', 'Trazabilidad por documento'], ['faBell', 'Diferencias al contar'], ['faUserCheck', 'Expedientes con dueño y plazo']];
  s.rect(0.6, 1.5, 5.9, 5.3, { fill: C.RT, r: 0.14 });
  s.rect(6.83, 1.5, 5.9, 5.3, { fill: C.GT, r: 0.14 });
  s.text(0.9, 1.65, 5.3, 0.5, 'Antes', { size: 24, bold: true, color: C.R3 });
  s.text(7.13, 1.65, 5.3, 0.5, 'Con DAILY', { size: 24, bold: true, color: C.GD });
  A.forEach((a, i) => {
    iconCircle(s, 0.95, 2.4 + i * 1.08, 0.72, a[0], C.WHITE, C.R2);
    s.text(1.9, 2.4 + i * 1.08, 4.4, 0.72, a[1], { size: 20, bold: true, valign: 'middle' });
  });
  D.forEach((a, i) => {
    iconCircle(s, 7.18, 2.4 + i * 1.08, 0.72, a[0], C.WHITE, C.G3);
    s.text(8.13, 2.4 + i * 1.08, 4.4, 0.72, a[1], { size: 20, bold: true, valign: 'middle' });
  });
}

// ===== IMPACTO =====
{
  const s = content('impact', 'Valor', 'Impacto económico', 'Medio punto de merma: $5.000 al mes en este ejemplo', 'demo',
    'QUÉ DECIR: "Empresa hipotética con ventas de $1.000.000 mensuales. Con merma de 2 % pierde $20.000; con 1,5 %, $15.000. La diferencia son $5.000 al mes." QUÉ MOSTRAR: el gráfico; es editable en PowerPoint para usar las cifras del cliente. BENEFICIO: una forma simple de estimar lo que vale un punto de merma. ACLARAR: es un ejemplo ilustrativo; no representa resultados reales ni garantiza ahorros.');
  s.chart(0.6, 1.5, 7.4, 5.3, { series: [{ name: 'Merma 2,0 %', labels: ['Pérdida mensual estimada'], values: [20000] }, { name: 'Merma 1,5 %', labels: ['Pérdida mensual estimada'], values: [15000] }], colors: [C.R2, C.G3], max: 25000 });
  stat(s, 8.3, 1.5, 4.43, '$1.000.000', 'ventas mensuales del ejemplo');
  stat(s, 8.3, 2.8, 4.43, '$20.000 → $15.000', 'pérdida estimada mensual', C.R2);
  stat(s, 8.3, 4.1, 4.43, '$5.000', 'diferencia mensual estimada', C.G3);
  s.rect(8.3, 5.45, 4.43, 1.35, { fill: C.YT, r: 0.1 });
  s.text(8.45, 5.5, 4.13, 1.25, 'Ejemplo ilustrativo. No representa resultados reales ni garantiza ahorros.', { size: 15, bold: true, color: C.Y3, valign: 'middle' });
}

// ===== PROPUESTA =====
{
  const s = content('offer', 'Siguiente paso', 'Propuesta comercial', 'Cuatro formas de empezar a trabajar juntos', 'prop',
    'QUÉ DECIR: "Hay cuatro formas de colaborar; la elegimos después del diagnóstico." QUÉ MOSTRAR: las cuatro opciones. BENEFICIO: flexibilidad según el tamaño y la etapa de la empresa. PREGUNTA HABITUAL: "¿Cuánto cuesta?" → Depende de sucursales, usuarios y módulos; se cotiza después del diagnóstico.');
  const O = [['faCloud', 'Licenciamiento SaaS', 'Suscripción según sucursales, usuarios y módulos'], ['faFlask', 'Prueba piloto empresarial', 'Una o dos tiendas, medidas contra línea base'], ['faStore', 'Implementación multisucursal', 'Despliegue por etapas y regiones'], ['faHandshake', 'Colaboración estratégica', 'Desarrollo conjunto de módulos propuestos']];
  O.forEach((o, i) => {
    const x = 0.6 + i * 3.08;
    s.rect(x, 1.6, 2.89, 4.6, { fill: C.WHITE, line: C.LINE, r: 0.14, shadow: true });
    iconCircle(s, x + 0.3, 1.9, 1.0, o[0], C.WHITE, i === 1 ? C.G3 : C.G2);
    s.text(x + 0.3, 3.15, 2.35, 1.2, o[1], { size: 20, bold: true });
    s.text(x + 0.3, 4.45, 2.35, 1.4, o[2], { size: 15, color: C.MUTED });
  });
  s.text(0.6, 6.4, 12.1, 0.32, 'Condiciones y valores se definen después del diagnóstico.', { size: 13, color: C.MUTED, italic: true });
}

// ===== DEMOSTRACIÓN =====
{
  const s = content('demo', 'Siguiente paso', 'Demostración', 'Una demostración de 60 minutos con sus preguntas', 'prop',
    'QUÉ DECIR: "Proponemos una demostración guiada en vivo, sobre la empresa de demostración, siguiendo los dos casos completos y respondiendo con sus preguntas." QUÉ MOSTRAR: la agenda. BENEFICIO: el cliente ve el producto funcionando, no solo diapositivas. PREGUNTA PARA EL CLIENTE: "¿Qué proceso quiere ver primero?"');
  const A = [['10 min', 'HOY y Centro de Alertas', 'faGaugeHigh'], ['15 min', 'Caso 1: diferencia de inventario', 'faScaleUnbalanced'], ['10 min', 'Caso 2: vencimiento y merma', 'faCalendarXmark'], ['10 min', 'Ventas, compras y personal', 'faChartLine'], ['15 min', 'Preguntas del cliente', 'faComments']];
  A.forEach((a, i) => {
    const y = 1.55 + i * 1.03;
    s.rect(0.6, y, 7.2, 0.88, { fill: C.WHITE, line: C.LINE, r: 0.1, shadow: true });
    iconCircle(s, 0.75, y + 0.14, 0.6, a[2], C.WHITE, C.G2);
    s.text(1.5, y, 1.2, 0.88, a[0], { size: 16, bold: true, color: C.G3, valign: 'middle' });
    s.text(2.7, y, 5.0, 0.88, a[1], { size: 17, bold: true, valign: 'middle' });
  });
  s.rect(8.1, 1.55, 4.63, 5.0, { fill: C.G3, r: 0.14, shadow: true });
  s.text(8.4, 1.75, 4.1, 0.5, 'Qué necesitamos', { size: 20, bold: true, color: C.WHITE });
  s.text(8.4, 2.4, 4.1, 3.9, [{ text: 'Una hora con operaciones, inventario y prevención', bullet: true }, { text: 'Sus tres problemas prioritarios', bullet: true }, { text: 'Opcional: una muestra de datos anonimizada para el diagnóstico', bullet: true }], { size: 15, color: C.WHITE, paraAfter: 10 });
}

// ===== IMPLEMENTACIÓN =====
{
  const s = content('roadmap', 'Siguiente paso', 'Implementación', 'De diagnóstico a expansión, en ocho etapas', 'prop',
    'QUÉ DECIR: "Empezamos por entender la operación y terminamos midiendo." QUÉ MOSTRAR: las ocho etapas. BENEFICIO: riesgo acotado: se expande solo si los resultados lo justifican. ACLARAR: son etapas propuestas; todavía no hay implementaciones realizadas con clientes.');
  const R = [['Diagnóstico operativo', 'faStethoscope'], ['Definición del alcance', 'faBullseye'], ['Configuración', 'faGears'], ['Carga e integración', 'faDatabase'], ['Capacitación', 'faChalkboardUser'], ['Prueba piloto', 'faFlask'], ['Medición de resultados', 'faChartLine'], ['Expansión a sucursales', 'faStore']];
  R.forEach((r, i) => {
    const x = 0.6 + (i % 4) * 3.08, y = 1.6 + Math.floor(i / 4) * 2.45, pilot = i === 5;
    s.rect(x, y, 2.89, 2.2, { fill: pilot ? C.G3 : C.WHITE, line: C.LINE, r: 0.12, shadow: true });
    iconCircle(s, x + 0.25, y + 0.25, 0.72, r[1], pilot ? C.G3 : C.WHITE, pilot ? C.WHITE : C.G2);
    s.text(x + 1.1, y + 0.25, 1.6, 0.72, String(i + 1), { size: 30, bold: true, color: pilot ? C.WHITE : 'B8C2BC', align: 'right', valign: 'middle' });
    s.text(x + 0.25, y + 1.15, 2.4, 0.85, r[0], { size: 17, bold: true, color: pilot ? C.WHITE : C.INK });
  });
  s.text(0.6, 6.5, 12.1, 0.32, 'Etapas propuestas. Sin implementaciones realizadas con clientes a la fecha.', { size: 13, color: C.MUTED, italic: true });
}

// ===== PILOTO =====
{
  const s = content('pilot', 'Siguiente paso', 'Prueba piloto', 'Piloto de 30 días, medido con seis indicadores', 'prop',
    'QUÉ DECIR: "Proponemos un piloto de 30 días en una o dos tiendas, sujeto a acuerdo, con línea base y seis indicadores." QUÉ MOSTRAR: los indicadores. BENEFICIO: decidir con los datos de su operación. PREGUNTA HABITUAL: "¿Tienen casos de éxito?" → Proponemos un piloto medido como primer paso; no citamos testimonios.');
  s.rect(0.6, 1.5, 3.7, 5.3, { fill: C.G3, r: 0.14, shadow: true });
  s.text(0.85, 1.75, 3.2, 1.1, '30', { size: 66, bold: true, color: C.WHITE });
  s.text(0.85, 2.95, 3.2, 0.5, 'días de piloto', { size: 22, bold: true, color: C.WHITE });
  s.text(0.85, 3.65, 3.2, 1.6, 'Línea base, operación acompañada y medición final.', { size: 16, color: 'D7F5DF' });
  s.text(0.85, 5.7, 3.2, 0.8, 'Sujeto a acuerdo. Sin testimonios ni resultados previos.', { size: 13, color: 'D7F5DF' });
  const I = [['faBullseye', 'Exactitud de inventario'], ['faScaleUnbalanced', 'Diferencias detectadas y cerradas'], ['faStopwatch', 'Tiempo de investigación'], ['faTrashCan', 'Merma sobre ventas'], ['faCalendarXmark', 'Vencimientos gestionados a tiempo'], ['faListCheck', 'Cumplimiento de tareas']];
  I.forEach((a, i) => {
    const x = 4.6 + (i % 2) * 4.15, y = 1.5 + Math.floor(i / 2) * 1.82;
    s.rect(x, y, 3.98, 1.66, { fill: C.WHITE, line: C.LINE, r: 0.12, shadow: true });
    iconCircle(s, x + 0.22, y + 0.47, 0.72, a[0], C.WHITE, C.G2);
    s.text(x + 1.12, y + 0.2, 2.7, 1.26, a[1], { size: 17, bold: true, valign: 'middle' });
  });
}

// ===== PRÓXIMOS PASOS =====
{
  const s = content('next', 'Siguiente paso', 'Próximos pasos', 'De esta reunión a una decisión, en nueve semanas', 'prop',
    'QUÉ DECIR: "Si les interesa, este es el camino: demostración, diagnóstico, configuración, piloto de 30 días y una reunión de resultados para decidir." QUÉ MOSTRAR: la línea de tiempo. BENEFICIO: un compromiso acotado y con fecha de decisión. LLAMADO A LA ACCIÓN: acordar hoy la fecha de la demostración.');
  const T = [['Semana 1', 'Demostración guiada', 'faDisplay'], ['Semana 2', 'Diagnóstico y alcance', 'faStethoscope'], ['Semanas 3–4', 'Configuración y carga', 'faGears'], ['Semanas 5–8', 'Piloto de 30 días', 'faFlask'], ['Semana 9', 'Resultados y decisión', 'faHandshake']];
  s.line(0.9, 3.0, 12.4, 3.0, { color: C.G2, lw: 3 });
  T.forEach((t, i) => {
    const x = 0.6 + i * 2.45, w = 2.3, last = i === T.length - 1;
    iconCircle(s, x + w / 2 - 0.45, 2.55, 0.9, t[2], C.WHITE, last ? C.G3 : C.G2);
    s.text(x, 1.65, w, 0.4, t[0], { size: 16, bold: true, color: C.G3, align: 'center' });
    s.rect(x, 3.75, w, 1.5, { fill: last ? C.G3 : C.WHITE, line: C.LINE, r: 0.12, shadow: true });
    s.text(x + 0.15, 3.75, w - 0.3, 1.5, t[1], { size: 17, bold: true, align: 'center', valign: 'middle', color: last ? C.WHITE : C.INK });
  });
  s.rect(0.6, 5.65, 12.13, 0.95, { fill: C.GT, r: 0.12 });
  s.text(0.85, 5.65, 11.6, 0.95, 'Hoy: acordar la fecha de la demostración y los participantes.', { size: 18, bold: true, color: C.GD, valign: 'middle' });
}

// ===== CIERRE =====
{
  const s = new Slide({ cover: true, section: 'Siguiente paso' });
  s.o.bg = '__COVER__';
  logo(s);
  s.text(0.75, 1.95, 5.8, 2.3, 'DAILY — De los datos a las decisiones. De las decisiones a los resultados.', { size: 32, bold: true, color: C.WHITE, lh: 0.95 });
  s.rect(0.75, 4.55, 5.4, 1.1, { fill: C.G1, r: 0.14, shadow: true });
  s.text(0.95, 4.55, 5.0, 1.1, 'Solicite una demostración o inicie una prueba piloto.', { size: 18, bold: true, color: '06301B', valign: 'middle' });
  s.text(0.75, 5.95, 5.6, 0.4, 'DAILY | Presentación comercial · v3', { size: 11, color: 'B9F3C9' });
  const G = [['02_mermas/waste_centro-control', [260, 440, 1640]], ['05_ventas/sales', [260, 520, 1640]], ['04_calidad/quality', [250, 30, 1650]], ['12_procesos/process_flow', [255, 160, 1645]], ['07_multisucursal/branches', [260, 180, 1640]], ['01_ejecutivo/today', [260, 60, 1640]]];
  G.forEach((g, i) => s.shot(7.0 + (i % 2) * 2.98, 0.75 + Math.floor(i / 2) * 1.89, 2.75, 1.66, g[0], g[1]));
  s.notes = 'QUÉ DECIR: "DAILY lleva los datos de la operación a decisiones diarias, y esas decisiones a resultados que se pueden medir." LLAMADO A LA ACCIÓN: proponer fecha para la demostración guiada. BENEFICIO: el siguiente paso es acotado y medible.';
  S.closing = s;
}

const FULL = ['cover', 'problems', 'flow', 'modules', 'today', 'alerts', 'process', 'step1', 'step2', 'step3', 'step4', 'case1sum', 'waste', 'waste2', 'stock', 'wms', 'prevention', 'lossreport', 'sales', 'purchasing', 'staff', 'staff2', 'quality', 'case2', 'branches', 'mobile', 'connect', 'compare', 'status', 'day', 'benefits', 'beforeafter', 'impact', 'offer', 'demo', 'roadmap', 'pilot', 'next', 'closing'];
const EXEC = ['cover', 'today', 'case1sum', 'connect', 'next'];

async function coverBg(file) {
  const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="1920" height="1080"><defs>
<radialGradient id="g" cx="78%" cy="30%" r="85%"><stop offset="0" stop-color="#0E8A45"/><stop offset="0.55" stop-color="#075A2E"/><stop offset="1" stop-color="#042614"/></radialGradient>
<radialGradient id="h" cx="8%" cy="95%" r="45%"><stop offset="0" stop-color="#39F500" stop-opacity="0.22"/><stop offset="1" stop-color="#39F500" stop-opacity="0"/></radialGradient></defs>
<rect width="1920" height="1080" fill="url(#g)"/><rect width="1920" height="1080" fill="url(#h)"/></svg>`;
  await sharp(Buffer.from(svg)).png().toFile(file);
}

module.exports = { S, FULL, EXEC, coverBg };
