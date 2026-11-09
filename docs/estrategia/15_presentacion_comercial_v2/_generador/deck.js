// DAILY — presentación comercial visual v2. Un spec de diapositivas -> PPTX + PDF + guion
const path = require('path');
const sharp = require('sharp');
const { Slide, C } = require('./engine');

const LOGO = path.join(__dirname, 'assets', 'logo.png').replace(/\\/g, '/');
const MAIN = [250, 40, 1650]; // área de contenido, sin menú lateral
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
  const T = ['Identificar', 'Revisar', 'Determinar', 'Actuar'];
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
function logo(s) {
  s.add({ t: 'img', abs: LOGO, x: 0.72, y: 0.8, w: 0.72, h: 0.72, alt: 'Logo DAILY' });
  s.text(1.58, 0.85, 3, 0.62, 'DAILY', { size: 30, bold: true, color: C.WHITE, valign: 'middle', spacing: 2 });
}

// ===== 1. PORTADA =====
{
  const s = new Slide({ cover: true, section: 'Apertura' });
  s.o.bg = '__COVER__';
  logo(s);
  s.text(0.75, 2.0, 5.3, 2.0, 'DAILY — Inteligencia y Control Operativo para Retail', { size: 38, bold: true, color: C.WHITE, lh: 0.95 });
  s.text(0.75, 4.2, 5.0, 1.0, 'Una plataforma para conectar inventario, pérdidas, ventas, productividad y operaciones.', { size: 17, color: 'D7F5DF' });
  s.pill(0.75, 5.45, 3.6, 'Pantallas reales · datos de demostración', { fill: '0E4A2B', color: 'B9F3C9', size: 11, h: 0.36 });
  s.text(6.55, 0.42, 3, 0.28, 'Panel Ejecutivo', { size: 12, bold: true, color: 'B9F3C9' });
  s.shot(6.55, 0.72, 6.2, 3.55, '01_ejecutivo/kpis', [250, 110, 1650]);
  s.shot(6.0, 3.05, 4.4, 2.52, '03_inventario/stockoverview', [250, 40, 1650]);
  s.text(6.0, 5.65, 2.4, 0.28, 'Inventario', { size: 12, bold: true, color: 'B9F3C9' });
  s.shot(8.65, 4.35, 4.1, 2.35, '02_mermas/waste_centro-control', [250, 280, 1650]);
  s.text(8.65, 6.78, 3, 0.28, 'Control de Mermas', { size: 12, bold: true, color: 'B9F3C9' });
  s.notes = 'QUÉ DECIR: "DAILY conecta en una sola plataforma lo que hoy vive en planillas y sistemas separados: inventario, mermas, ventas, personal y alertas." QUÉ MOSTRAR: las tres pantallas de la portada; son reales, con una empresa de demostración. BENEFICIO: una sola versión de los números para toda la operación. PREGUNTA HABITUAL: "¿Es un ERP?" → No reemplaza su ERP o POS; agrega control operativo y recibe datos por importación o API.';
  S.cover = s;
}

// ===== 2. PROBLEMAS =====
{
  const s = content('problems', 'Apertura', 'El problema', 'Lo que hoy le cuesta margen a un supermercado', 'demo',
    'QUÉ DECIR: recorrer las siete tarjetas y preguntar cuáles reconoce el cliente. QUÉ MOSTRAR: la tarjeta roja resume la consecuencia. BENEFICIO: poner nombre al problema antes de hablar de software. PREGUNTA PARA EL CLIENTE: "¿Cuándo se enteran hoy de una diferencia de inventario: el mismo día o al cierre del mes?"');
  const P = [
    ['faEyeSlash', 'Pérdidas sin detectar', 'Se descubren tarde, sin margen de acción'],
    ['faScaleUnbalanced', 'Físico ≠ sistema', 'Quiebres, sobrecompra y ventas perdidas'],
    ['faCalendarXmark', 'Productos vencidos', 'Desperdicio y riesgo para el cliente'],
    ['faUsers', 'Personal desajustado', 'Filas en pico, horas ociosas en valle'],
    ['faFileLines', 'Procesos manuales', 'Planillas, firmas y horas de supervisión'],
    ['faPuzzlePiece', 'Información fragmentada', 'Cada área con sus propios números'],
    ['faStore', 'Sucursales sin visibilidad', 'Las desviaciones se ven tarde']
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

// ===== 3. FLUJO =====
{
  const s = content('flow', 'Apertura', 'La solución', 'Cómo DAILY conecta la operación', 'real',
    'QUÉ DECIR: "Cada paso de la mercancía deja un movimiento en un kardex único. Cuando algo no cuadra, DAILY lo convierte en una alerta con responsable." QUÉ MOSTRAR: seguir el anillo desde Recepción hasta Acciones correctivas. BENEFICIO: trazabilidad de punta a punta. PREGUNTA HABITUAL: "¿Necesito cambiar mi POS?" → No. Las ventas pueden registrarse en el POS de DAILY o cargarse por importación o API; la integración exacta se valida en el diagnóstico.');
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
  s.text(cx - 1.7, cy - 0.1, 3.4, 0.7, 'Un kardex único · alertas · responsables · indicadores', { size: 13, color: 'D7F5DF', align: 'center' });
}

// ===== 4. PANEL EJECUTIVO =====
{
  const s = content('exec', 'Supervisión', 'Panel ejecutivo', 'Las desviaciones del mes, en una sola pantalla', 'real',
    'QUÉ DECIR: "Un gerente abre esta pantalla y ve de inmediato si venta, margen, merma y rotación van bien." QUÉ MOSTRAR: (1) indicadores; (2) decisiones priorizadas con acceso directo al detalle; (3) estado de la operación por área. BENEFICIO: decidir con datos registrados, sin esperar el cierre. PREGUNTA HABITUAL: "¿De dónde salen los números?" → De ventas, compras y movimientos registrados en la plataforma.');
  const e = s.shot(0.6, 1.5, 8.5, 5.3, '01_ejecutivo/kpis', [260, 120, 1630]);
  s.pin(e, 285, 150, 1); s.pin(e, 285, 440, 2); s.pin(e, 285, 805, 3);
  callouts(s, 9.4, 1.55, 3.33, [
    ['Indicadores clave', 'Venta, margen, merma y rotación, contra período anterior y meta.'],
    ['Decisiones priorizadas', 'Qué atender primero, con acceso directo al detalle.'],
    ['Estado por área', 'Compras, inventario, ventas, personal, calidad y mermas.']
  ], C.R2, 1.25);
  s.rect(9.4, 5.45, 3.33, 1.35, { fill: C.RT, r: 0.1 });
  s.text(9.55, 5.5, 3.05, 1.25, [{ text: 'Merma 3,04 % vs. objetivo 2,0 %', bold: true, color: C.R2, size: 15 }, { text: 'Desviación visible sin esperar el cierre.', color: C.INK, size: 13 }], { valign: 'middle', paraAfter: 3 });
}

// ===== 5. PASO 1 =====
{
  const s = content('step1', 'Control de mermas', 'Mermas · Demostración', 'Se reciben 10, se cuentan 8: la diferencia aparece', 'real',
    'QUÉ DECIR: "Vamos a seguir un caso de principio a fin. Se reciben 10 unidades de un producto sensible; el conteo encuentra 8. Al registrar el conteo, DAILY calcula la diferencia, su impacto al costo y marca el producto en rojo." QUÉ MOSTRAR: la tarjeta de diferencia y el banner rojo de alerta en la ficha del producto. BENEFICIO: la diferencia aparece al contar, no al cierre. ACLARAR: es un caso de demostración ejecutado con la lógica real sobre datos ficticios. No es seguimiento automático en tiempo real: la diferencia surge cuando se registra el conteo.');
  stepper(s, 0);
  stat(s, 0.6, 1.85, 1.75, '10', 'recibidas');
  stat(s, 0.6, 3.1, 1.75, '8', 'contadas');
  stat(s, 0.6, 4.35, 1.75, '−2', 'diferencia', C.R2);
  stat(s, 0.6, 5.6, 1.75, '$1.120', 'impacto al costo', C.R2);
  const e = s.shot(2.65, 1.85, 10.08, 4.87, '03_inventario/conciliacion_producto', [590, 412, 1300]);
  s.ring(e, 640, 632, 1880, 790);
  s.demoTag(9.55, 2.0);
}

// ===== 6. PASO 2 =====
{
  const s = content('step2', 'Control de mermas', 'Mermas · Demostración', 'Cada movimiento del producto, en una línea de tiempo', 'real',
    'QUÉ DECIR: "El responsable no busca en planillas: abre la línea de tiempo y el kardex del producto. Ve entradas, salidas, ventas, devoluciones y conteos, con usuario, hora y documento." QUÉ MOSTRAR: arriba la línea de tiempo; abajo el kardex valorizado. BENEFICIO: la investigación parte de los movimientos registrados. PREGUNTA HABITUAL: "¿Se pueden alterar los movimientos?" → El libro de movimientos está protegido contra modificaciones y puede verificarse su integridad.');
  stepper(s, 1);
  label(s, 0.6, 1.8, 6, 'Línea de tiempo única');
  s.shot(0.6, 2.12, 7.1, 3.1, '03_inventario/conciliacion_cerrado', [650, 1180, 1230]);
  stat(s, 0.6, 5.5, 2.25, '+10', 'recepción');
  stat(s, 3.02, 5.5, 2.25, '8', 'conteo físico');
  stat(s, 5.45, 5.5, 2.25, '−2', 'ajuste autorizado', C.R2);
  label(s, 8.0, 1.8, 4.7, 'Kardex valorizado del producto');
  s.shot(8.0, 2.12, 4.73, 4.5, '03_inventario/kardex_producto', [260, 1340, 1000]);
}

// ===== 7. PASO 3 =====
{
  const s = content('step3', 'Control de mermas', 'Mermas · Demostración', 'De la diferencia a la causa, con evidencia', 'real',
    'QUÉ DECIR: "La ficha resume el intervalo auditado, la evaluación de causas según la evidencia y las acciones recomendadas. Si el reconteo ratifica la diferencia, el caso sube a Faltante Confirmado." QUÉ MOSTRAR: la ficha a la izquierda; a la derecha la distribución por causales. BENEFICIO: causas documentadas en lugar de suposiciones. ACLARAR: DAILY orienta la investigación; la causa la confirma el responsable.');
  stepper(s, 2);
  const e = s.shot(0.6, 1.85, 6.0, 4.95, '03_inventario/conciliacion_ficha2', [505, 115, 915]);
  callouts(s, 6.95, 1.9, 5.78, [
    ['Intervalo auditado', 'Movimientos entre el último conteo verificado y el actual.'],
    ['Evaluación de causas', 'Hipótesis según la evidencia; las confirma una persona.'],
    ['Reconteo independiente', 'Si se ratifica, pasa a Faltante Confirmado.']
  ], C.G3, 0.98);
  label(s, 6.95, 4.82, 5.7, 'Causales de merma: distribución y Pareto');
  s.shot(6.95, 5.14, 5.78, 1.66, '02_mermas/waste_causales', [270, 560, 1610]);
}

// ===== 8. PASO 4 =====
{
  const s = content('step4', 'Control de mermas', 'Mermas · Demostración', 'Alerta, responsable, ajuste autorizado y cierre', 'real',
    'QUÉ DECIR: "La diferencia genera una alerta con responsable y plazo. El ajuste requiere autorización de un supervisor distinto de quien registró, y el expediente se cierra con la causa documentada." QUÉ MOSTRAR: la alerta a la izquierda y los módulos de prevención a la derecha. BENEFICIO: nadie ajusta inventario sin dejar rastro. ACLARAR: el ajuste y el cierre no son automáticos; cada paso lo decide una persona.');
  stepper(s, 3);
  s.shot(0.6, 1.85, 4.4, 4.95, '08_alertas/alerts_caso', [1362, 560, 540]);
  const G = [['02_mermas/waste_investigaciones', 'Investigaciones y planes', [250, 330, 1650]], ['02_mermas/waste_approvals', 'Aprobaciones', [250, 40, 1650]], ['02_mermas/waste_conteos', 'Selectivos y sensibles', [250, 330, 1650]], ['02_mermas/waste_fefo', 'Prevención y FEFO', [250, 330, 1650]]];
  G.forEach((g, i) => {
    const x = 5.3 + (i % 2) * 3.8, y = 1.85 + Math.floor(i / 2) * 2.5;
    s.shot(x, y, 3.63, 1.95, g[0], g[2]);
    s.text(x, y + 2.03, 3.63, 0.3, g[1], { size: 13, bold: true });
  });
}

// ===== 9. STOCK Y KARDEX =====
{
  const s = content('stock', 'Inventario y trazabilidad', 'Inventario', 'Qué hay, cuánto vale y qué está en riesgo', 'real',
    'QUÉ DECIR: "Stock & Movimientos muestra valor, unidades, rotación y cobertura; debajo, los productos en riesgo y los movimientos recientes." QUÉ MOSTRAR: las tarjetas de bajo mínimo, próximos a vencer y agotados. BENEFICIO: el responsable de inventario prioriza con datos. PREGUNTA HABITUAL: "¿Lo veo por sucursal?" → Sí, con filtros por sucursal y departamento.');
  const e = s.shot(0.6, 1.5, 7.9, 5.3, '03_inventario/stockoverview', [260, 110, 1630]);
  label(s, 8.8, 1.5, 3.9, 'Kardex con hallazgos');
  s.shot(8.8, 1.82, 3.93, 2.2, '03_inventario/kardex', [260, 380, 1000]);
  label(s, 8.8, 4.2, 3.9, 'Control de inventario');
  s.shot(8.8, 4.52, 3.93, 2.28, '03_inventario/inventory_control', [260, 290, 1000]);
}

// ===== 10. OPERACIÓN DE ALMACÉN =====
{
  const s = content('wms', 'Inventario y trazabilidad', 'Almacén', 'Ubicaciones, traslados, conteos y escáner', 'real',
    'QUÉ DECIR: "El almacén se opera por ubicación: mapa de zonas, traslados con verificación, conteos físicos y operaciones con escáner." QUÉ MOSTRAR: las cuatro herramientas. BENEFICIO: cada movimiento queda ligado a una ubicación y un responsable. PREGUNTA HABITUAL: "¿Qué lectores acepta?" → Cámara del teléfono y lectores en modo teclado; el modelo exacto se valida en el piloto.');
  const G = [['03_inventario/location_map', 'Mapa de ubicaciones', [250, 40, 1650]], ['03_inventario/transfers', 'Traslados entre tiendas', [250, 40, 1650]], ['03_inventario/scan', 'Operaciones con escáner', [250, 40, 1650]], ['03_inventario/inventory', 'Conteos físicos', [250, 40, 1650]]];
  G.forEach((g, i) => {
    const x = 0.6 + (i % 2) * 6.15, y = 1.45 + Math.floor(i / 2) * 2.74;
    s.shot(x, y, 5.98, 2.28, g[0], g[2]);
    s.text(x, y + 2.33, 5.98, 0.3, g[1], { size: 13, bold: true });
  });
}

// ===== 11. FLUJO INVENTARIO =====
{
  const s = content('invflow', 'Inventario y trazabilidad', 'Trazabilidad', 'De la recepción a la conciliación', 'real',
    'QUÉ DECIR: "Este es el recorrido completo: recepción, ubicación, movimiento, conteo y conciliación. El responsable consulta cada etapa y ve dónde aparece la diferencia." QUÉ MOSTRAR: las cinco etapas y la ficha de 14 puntos. BENEFICIO: una ficha por producto con la historia completa. PREGUNTA HABITUAL: "¿Qué pasa si recibo menos de lo facturado?" → Queda registrado en la recepción y entra a la conciliación.');
  flowRow(s, 0.6, 1.45, 12.13, [['Recepción', 'faTruckRampBox'], ['Ubicación', 'faLocationDot'], ['Movimiento', 'faRightLeft'], ['Conteo', 'faClipboardCheck'], ['Conciliación', 'faScaleBalanced']], { lastDark: true, size: 14 });
  const e = s.shot(0.6, 2.6, 8.3, 4.2, '03_inventario/conciliacion_cerrado', [590, 450, 1290]);
  callouts(s, 9.2, 2.65, 3.53, [
    ['Ficha de 14 puntos', 'Recepciones, conteos, ajustes y último responsable.'],
    ['Diferencias a la vista', 'Esperado, contado y unidades con diferencia.'],
    ['Acceso al kardex', 'Desde la ficha al movimiento de origen.']
  ], C.G3, 1.38);
}

// ===== 12. CALIDAD =====
{
  const s = content('quality', 'Calidad', 'Calidad y vencimientos', 'Actuar antes de que el producto venza', 'real',
    'QUÉ DECIR: "DAILY identifica los lotes próximos a vencer, los prioriza por riesgo y sugiere la acción preventiva." QUÉ MOSTRAR: la matriz de lotes y la ruta de atención. BENEFICIO: menos desperdicio y más protección de la calidad. PREGUNTA HABITUAL: "¿Controla la cadena de frío?" → Registra temperaturas y excursiones por equipo; la captura automática con sensores se define en cada proyecto.');
  flowRow(s, 0.6, 1.45, 12.13, [['Próximo a vencer', 'faHourglassHalf', C.Y2], ['Identificación', 'faMagnifyingGlass', C.Y2], ['Priorización', 'faArrowUpWideShort', C.Y2], ['Acción preventiva', 'faTags', C.G2], ['Seguimiento', 'faListCheck', C.G2]], { size: 13 });
  s.shot(0.6, 2.55, 7.6, 4.25, '04_calidad/quality', [250, 30, 1650]);
  label(s, 8.5, 2.55, 4.2, 'Control FEFO y cadena de frío');
  s.shot(8.5, 2.87, 4.23, 3.93, '02_mermas/waste_fefo', [270, 400, 1100]);
}

// ===== 13. VENTAS =====
{
  const s = content('sales', 'Ventas', 'Ventas y rentabilidad', 'Qué se vende, cuánto deja y qué rinde poco', 'real',
    'QUÉ DECIR: "Ventas, costo, margen y comparación contra el período anterior; debajo, productos y sucursales." QUÉ MOSTRAR: indicadores, curva de ventas y análisis de productos con mayor y menor margen. BENEFICIO: detectar productos de bajo rendimiento. PREGUNTA HABITUAL: "¿Se concilia con inventario?" → Sí, hay una vista de conciliación de ventas con inventario.');
  const e = s.shot(0.6, 1.5, 8.3, 5.3, '05_ventas/sales', [260, 240, 1630]);
  callouts(s, 9.2, 1.55, 3.53, [
    ['Venta y margen', 'Venta neta, utilidad bruta y margen, contra período anterior.'],
    ['Curva y metas', 'Evolución diaria y cumplimiento.'],
    ['Rendimiento', 'Sucursales y productos: qué sostiene el margen y qué lo reduce.']
  ], C.G3, 1.4);
  s.rect(9.2, 5.8, 3.53, 1.0, { fill: C.GT, r: 0.1 });
  s.text(9.35, 5.85, 3.25, 0.9, 'Margen bruto del ejemplo: 40,3 %', { size: 15, bold: true, color: C.GD, valign: 'middle' });
}

// ===== 14. PERSONAL =====
{
  const s = content('staff', 'Personal', 'Personal y horarios', 'Turnos y cobertura por área en un solo tablero', 'real',
    'QUÉ DECIR: "Horarios y Cobertura muestra la programación semanal y mensual, ventas proyectadas frente a horas laborales, productividad por departamento y costo laboral." QUÉ MOSTRAR: el gráfico de ventas proyectadas y la programación. BENEFICIO: ver excesos y faltas de cobertura antes de publicar. ACLARAR: el generador valida reglas configurables; las normas de cada país deben revisarse con el área jurídica del cliente.');
  label(s, 0.6, 1.45, 7.3, 'Ventas proyectadas, horas, productividad y costo laboral');
  s.shot(0.6, 1.76, 7.3, 2.62, '06_personal/scheduling', [260, 370, 1630]);
  label(s, 0.6, 4.5, 7.3, 'Programación semanal');
  s.shot(0.6, 4.82, 7.3, 1.98, '06_personal/scheduling', [260, 1010, 1630]);
  label(s, 8.2, 1.45, 4.5, 'Generador con comprobaciones');
  s.shot(8.2, 1.76, 4.53, 4.3, '06_personal/generador', [560, 1085, 800]);
  s.text(8.2, 6.2, 4.53, 0.6, 'Reglas configurables, a validar con el área jurídica de cada empresa.', { size: 13, color: C.MUTED });
}

// ===== 15. PRODUCTIVIDAD =====
{
  const s = content('staff2', 'Personal', 'Productividad', 'Asistencia, tareas y planificación según demanda', 'mix',
    'QUÉ DECIR: "Hoy DAILY registra asistencia, tardanzas y tareas de piso, y compara ventas proyectadas con horas laborales. Lo que aún no existe es el estudio de tiempos y movimientos ni la generación automática de turnos directamente desde la demanda." QUÉ MOSTRAR: a la izquierda lo disponible; a la derecha el ejemplo conceptual. BENEFICIO: seguimiento diario del trabajo real. ACLARAR: la columna proyectada es conceptual; no mostrarla como función terminada.');
  label(s, 0.6, 1.5, 4, 'Asistencia y fichajes');
  s.shot(0.6, 1.82, 4.05, 2.3, '06_personal/attendance', [260, 160, 1620]);
  label(s, 0.6, 4.25, 4, 'Tareas de piso');
  s.shot(0.6, 4.57, 4.05, 2.23, '06_personal/workforce', [260, 80, 1620]);
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

// ===== 16. MULTISUCURSAL =====
{
  const s = content('branches', 'Multisucursal', 'Operación multisucursal', 'Todas las tiendas, con los mismos indicadores', 'real',
    'QUÉ DECIR: "Todas las sucursales de demostración se comparan con los mismos indicadores: venta, margen, merma y productos críticos. La que se desvía salta a la vista." QUÉ MOSTRAR: las tarjetas por sucursal; la red de demostración tiene diez tiendas. BENEFICIO: la gerencia ve la red en una pantalla. ACLARAR: sucursales y cifras ficticias; no son resultados de clientes.');
  const e = s.shot(0.6, 1.5, 8.9, 5.3, '07_multisucursal/branches', [260, 130, 1630]);
  callouts(s, 9.8, 1.55, 2.93, [
    ['Mismas métricas', 'Venta, margen, merma y stock crítico.'],
    ['Desviación visible', 'La merma alta resalta frente a las demás tiendas.'],
    ['Del total al detalle', 'De la sucursal a sus módulos.']
  ], C.R2, 1.5);
  s.pill(9.8, 6.32, 2.93, 'Sucursales ficticias de demostración', { fill: C.YT, color: C.Y3, size: 10.5, h: 0.32 });
}

// ===== 17. ALERTAS =====
{
  const s = content('alerts', 'Alertas', 'Centro de alertas', 'Qué atender primero y quién responde', 'real',
    'QUÉ DECIR: "Cada alerta tiene criticidad, responsable, plazo e impacto estimado. El responsable ordena por prioridad, registra acciones y evidencia, y la cierra." QUÉ MOSTRAR: el resumen superior, la lista y el panel de decisión. BENEFICIO: nada queda sin dueño. PREGUNTA HABITUAL: "¿Quién recibe las alertas?" → Se asignan a un responsable y aparecen también en el móvil.');
  flowRow(s, 0.6, 1.4, 12.13, [['Detección', 'faSatelliteDish'], ['Notificación', 'faBell'], ['Responsable', 'faUserCheck'], ['Investigación', 'faMagnifyingGlass'], ['Acción', 'faListCheck'], ['Cierre', 'faCircleCheck']], { size: 12, h: 0.8, gap: 0.24 });
  s.shot(0.6, 2.42, 12.13, 4.38, '08_alertas/alerts', [260, 60, 1630]);
}

// ===== 18. SEGURIDAD INDUSTRIAL =====
{
  const s = content('sst', 'Seguridad', 'Seguridad y salud en el trabajo', 'Reporte de incidentes: módulo proyectado', 'plan',
    'QUÉ DECIR: "Hoy DAILY no incluye un módulo de Seguridad y Salud en el Trabajo. Esto es lo que proponemos construir, apoyado en piezas que ya existen: alertas con responsable, tareas y evidencia." QUÉ MOSTRAR: el proceso conceptual. BENEFICIO: una misma plataforma para operación y seguridad. ACLARAR: no hay capturas porque no hay pantalla; es una propuesta de hoja de ruta, y nunca debe presentarse como disponible.');
  sub(s, 'Hoy no existe este módulo. El proceso siguiente es conceptual y se apoyaría en alertas, tareas y evidencia.');
  flowRow(s, 0.6, 2.0, 12.13, [['Reporte', 'faTriangleExclamation', C.Y2], ['Inspección', 'faClipboardCheck', C.Y2], ['Acción preventiva', 'faHelmetSafety', C.Y2], ['Seguimiento', 'faListCheck', C.Y2], ['Indicadores', 'faChartLine', C.Y2]], { size: 14 });
  const B = [['faTriangleExclamation', 'Accidentes e incidentes', 'Registro con evidencia'], ['faClipboardCheck', 'Inspecciones', 'Listas por área'], ['faHelmetSafety', 'Acciones preventivas', 'Responsable y plazo'], ['faChartLine', 'Indicadores', 'Seguimiento por tienda']];
  B.forEach((b, i) => {
    const x = 0.6 + i * 3.08;
    s.rect(x, 3.35, 2.89, 3.1, { fill: C.WHITE, line: C.Y2, lineW: 1.25, dash: true, r: 0.12 });
    iconCircle(s, x + 0.25, 3.6, 0.72, b[0], C.Y3, C.YT);
    s.text(x + 0.25, 4.5, 2.4, 0.7, b[1], { size: 16, bold: true });
    s.text(x + 0.25, 5.3, 2.4, 0.6, b[2], { size: 14, color: C.MUTED });
  });
}

// ===== 19. MÓVIL =====
{
  const s = content('mobile', 'Móvil', 'Experiencia móvil', 'La operación, desde el teléfono del equipo', 'real',
    'QUÉ DECIR: "El empleado ve sus prioridades, tareas y alertas, escanea productos, consulta inventario y atiende casos desde la web móvil." QUÉ MOSTRAR: las seis pantallas, de izquierda a derecha. BENEFICIO: el registro se hace donde ocurre el trabajo. PREGUNTA HABITUAL: "¿Está en las tiendas de aplicaciones?" → Hoy se usa desde el navegador del teléfono; la publicación en tiendas no está incluida.');
  const P = [['m_home', 'Hoy'], ['m_tasks', 'Tareas'], ['m_scan', 'Escáner'], ['m_notifications', 'Alertas'], ['m_inv', 'Inventario'], ['m_control', 'Incidencias']];
  P.forEach((p, i) => {
    const x = 0.68 + i * 2.05, w = 1.9;
    s.phone(x, 1.5, w, '10_movil/' + p[0]);
    s.text(x, 5.72, w, 0.3, p[1], { size: 14, bold: true, align: 'center' });
  });
  s.text(0.6, 6.2, 12.1, 0.6, 'Sujeto a las funciones habilitadas para cada rol. Pantallas reales con datos de demostración.', { size: 13, color: C.MUTED, align: 'center' });
}

// ===== 20. UN DÍA =====
{
  const s = content('day', 'Un día con DAILY', 'Escenario ilustrativo', 'Un día utilizando DAILY', 'demo',
    'QUÉ DECIR: recorrer el día de izquierda a derecha, con una pantalla por momento. QUÉ MOSTRAR: que el gerente y el equipo usan la misma información. BENEFICIO: DAILY acompaña la jornada completa, no solo el cierre. ACLARAR: es un escenario ilustrativo; horarios y secuencia cambian en cada empresa.');
  const T = [['07:00', 'Inicio de operaciones', '01_ejecutivo/today', [260, 60, 1630]], ['09:00', 'Recepción y validación', '03_inventario/scan', [260, 60, 1630]], ['11:00', 'Control de inventario', '03_inventario/stockoverview', [260, 110, 1630]], ['13:00', 'Tareas y productividad', '06_personal/workforce', [260, 60, 1630]], ['15:00', 'Revisión de alertas', '08_alertas/alerts', [260, 60, 1630]], ['17:00', 'Indicadores y cierre', '01_ejecutivo/kpis', [260, 110, 1630]]];
  s.line(0.8, 1.95, 12.5, 1.95, { color: C.G2, lw: 3 });
  T.forEach((t, i) => {
    const x = 0.6 + i * 2.04, w = 1.93;
    s.ellipse(x + w / 2 - 0.11, 1.84, 0.22, 0.22, { fill: C.G3, line: C.WHITE, lineW: 2 });
    s.text(x, 1.4, w, 0.36, t[0], { size: 17, bold: true, color: C.G3, align: 'center' });
    s.shot(x, 2.3, w, 3.3, t[2], [t[3][0], t[3][1], 640]);
    s.text(x, 5.7, w, 0.6, t[1], { size: 14, bold: true, align: 'center' });
  });
  s.text(0.6, 6.45, 12.1, 0.3, 'Escenario ilustrativo.', { size: 13, color: C.MUTED, align: 'center', italic: true });
}

// ===== 21. ANTES Y DESPUÉS =====
{
  const s = content('beforeafter', 'Valor', 'Antes y después', 'Del registro disperso al control operativo', 'demo',
    'QUÉ DECIR: "No prometemos una cifra. Mostramos el cambio de forma de trabajar: de registros separados a información centralizada, con responsables." QUÉ MOSTRAR: las dos columnas, fila por fila. BENEFICIO: cambio de proceso que luego se mide en el piloto. ACLARAR: no se garantizan resultados económicos.');
  const A = [['faFileLines', 'Registros separados'], ['faPenToSquare', 'Validaciones manuales'], ['faEyeSlash', 'Poca visibilidad'], ['faClock', 'Decisiones tardías']];
  const D = [['faDatabase', 'Información centralizada'], ['faRoute', 'Trazabilidad'], ['faUserCheck', 'Seguimiento de responsables'], ['faChartLine', 'Análisis operativo']];
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

// ===== 22. BENEFICIOS =====
{
  const s = content('benefits', 'Valor', 'Beneficios', 'Seis beneficios, seis herramientas reales', 'real',
    'QUÉ DECIR: cada beneficio se apoya en una herramienta que ya se mostró. QUÉ MOSTRAR: la pantalla de cada tarjeta. BENEFICIO: valor conectado con el producto, sin promesas abstractas. PREGUNTA PARA EL CLIENTE: "¿Cuál de estos seis le urge más?"');
  const B = [
    ['Mayor visibilidad de las pérdidas', 'Control de Mermas', '02_mermas/waste_centro-control', [260, 330, 1630]],
    ['Mejor control del inventario', 'Kardex y Conciliación', '03_inventario/conciliacion_cerrado', [260, 40, 1630]],
    ['Prevención de vencimientos', 'Calidad y FEFO', '04_calidad/quality', [250, 30, 1650]],
    ['Optimización del tiempo operativo', 'Escáner y móvil', '03_inventario/scan', [260, 60, 1630]],
    ['Seguimiento de productividad', 'Horarios y Tareas', '06_personal/scheduling', [260, 370, 1630]],
    ['Decisiones con indicadores', 'Panel Ejecutivo', '01_ejecutivo/kpis', [260, 120, 1630]]
  ];
  B.forEach((b, i) => {
    const x = 0.6 + (i % 3) * 4.11, y = 1.5 + Math.floor(i / 3) * 2.72;
    s.rect(x, y, 3.91, 2.58, { fill: C.WHITE, line: C.LINE, r: 0.12, shadow: true });
    s.img(x + 0.1, y + 0.1, 3.71, 1.5, b[2], b[3]);
    s.text(x + 0.15, y + 1.67, 3.61, 0.5, b[0], { size: 15, bold: true });
    s.text(x + 0.15, y + 2.17, 3.61, 0.3, b[1], { size: 13, color: C.G3, bold: true });
  });
}

// ===== 23. IMPACTO =====
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

// ===== 24. IMPLEMENTACIÓN =====
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

// ===== 25. PILOTO =====
{
  const s = content('pilot', 'Siguiente paso', 'Prueba piloto', 'Piloto de 30 días, medido con seis indicadores', 'prop',
    'QUÉ DECIR: "Proponemos un piloto de 30 días, sujeto a acuerdo, con línea base y seis indicadores." QUÉ MOSTRAR: los indicadores. BENEFICIO: decidir con los datos de su operación. PREGUNTA HABITUAL: "¿Tienen casos de éxito?" → Proponemos un piloto medido como primer paso; no citamos testimonios.');
  s.rect(0.6, 1.5, 3.7, 5.3, { fill: C.G3, r: 0.14, shadow: true });
  s.text(0.85, 1.75, 3.2, 1.1, '30', { size: 66, bold: true, color: C.WHITE });
  s.text(0.85, 2.95, 3.2, 0.5, 'días de piloto', { size: 22, bold: true, color: C.WHITE });
  s.text(0.85, 3.65, 3.2, 1.6, 'Línea base, operación acompañada y medición final.', { size: 16, color: 'D7F5DF' });
  s.text(0.85, 5.7, 3.2, 0.8, 'Sujeto a acuerdo. Sin testimonios ni resultados previos.', { size: 13, color: 'D7F5DF' });
  const I = [['faBullseye', 'Exactitud de inventario'], ['faScaleUnbalanced', 'Diferencias detectadas'], ['faStopwatch', 'Tiempo de investigación'], ['faListCheck', 'Cumplimiento de tareas'], ['faCalendarXmark', 'Vencimientos identificados'], ['faMobileScreen', 'Uso de la plataforma']];
  I.forEach((a, i) => {
    const x = 4.6 + (i % 2) * 4.15, y = 1.5 + Math.floor(i / 2) * 1.82;
    s.rect(x, y, 3.98, 1.66, { fill: C.WHITE, line: C.LINE, r: 0.12, shadow: true });
    iconCircle(s, x + 0.22, y + 0.47, 0.72, a[0], C.WHITE, C.G2);
    s.text(x + 1.12, y + 0.2, 2.7, 1.26, a[1], { size: 17, bold: true, valign: 'middle' });
  });
}

// ===== 26. PROPUESTA =====
{
  const s = content('offer', 'Siguiente paso', 'Propuesta comercial', 'Cuatro formas de empezar a trabajar juntos', 'prop',
    'QUÉ DECIR: "Hay cuatro formas de colaborar; la elegimos después del diagnóstico." QUÉ MOSTRAR: las cuatro opciones. BENEFICIO: flexibilidad según el tamaño y la etapa de la empresa. PREGUNTA HABITUAL: "¿Cuánto cuesta?" → Depende de sucursales, usuarios y módulos; se cotiza después del diagnóstico.');
  const O = [['faCloud', 'Licenciamiento SaaS', 'Suscripción según alcance'], ['faFlask', 'Prueba piloto empresarial', 'Una o dos tiendas, medidas'], ['faStore', 'Implementación multisucursal', 'Despliegue por etapas'], ['faHandshake', 'Colaboración estratégica', 'Acuerdo a medida']];
  O.forEach((o, i) => {
    const x = 0.6 + i * 3.08;
    s.rect(x, 1.6, 2.89, 4.6, { fill: C.WHITE, line: C.LINE, r: 0.14, shadow: true });
    iconCircle(s, x + 0.3, 1.9, 1.0, o[0], C.WHITE, i === 1 ? C.G3 : C.G2);
    s.text(x + 0.3, 3.15, 2.35, 1.2, o[1], { size: 20, bold: true });
    s.text(x + 0.3, 4.45, 2.35, 1.2, o[2], { size: 15, color: C.MUTED });
  });
  s.text(0.6, 6.4, 12.1, 0.32, 'Condiciones y valores se definen después del diagnóstico.', { size: 13, color: C.MUTED, italic: true });
}

// ===== 27. CIERRE =====
{
  const s = new Slide({ cover: true, section: 'Siguiente paso' });
  s.o.bg = '__COVER__';
  logo(s);
  s.text(0.75, 1.95, 5.8, 2.3, 'DAILY — De los datos a las decisiones. De las decisiones a los resultados.', { size: 32, bold: true, color: C.WHITE, lh: 0.95 });
  s.rect(0.75, 4.55, 5.4, 1.1, { fill: C.G1, r: 0.14, shadow: true });
  s.text(0.95, 4.55, 5.0, 1.1, 'Solicita una demostración o inicia una prueba piloto.', { size: 18, bold: true, color: '06301B', valign: 'middle' });
  s.text(0.75, 5.95, 5.6, 0.4, 'DAILY | Presentación comercial confidencial', { size: 11, color: 'B9F3C9' });
  const G = [['02_mermas/waste_causales', [260, 330, 1630]], ['05_ventas/sales', [260, 240, 1630]], ['04_calidad/quality', [250, 30, 1650]], ['06_personal/attendance', [260, 160, 1620]], ['03_inventario/location_map', [260, 40, 1630]], ['01_ejecutivo/today', [260, 60, 1630]]];
  G.forEach((g, i) => s.shot(7.0 + (i % 2) * 2.98, 0.75 + Math.floor(i / 2) * 1.89, 2.75, 1.66, g[0], g[1]));
  s.notes = 'QUÉ DECIR: "DAILY lleva los datos de la operación a decisiones diarias, y esas decisiones a resultados que se pueden medir." LLAMADO A LA ACCIÓN: proponer fecha para el diagnóstico o una demostración guiada con sus propias preguntas. BENEFICIO: el siguiente paso es acotado y medible.';
  S.closing = s;
}

const FULL = ['cover', 'problems', 'flow', 'exec', 'step1', 'step2', 'step3', 'step4', 'stock', 'wms', 'invflow', 'quality', 'sales', 'staff', 'staff2', 'branches', 'alerts', 'sst', 'mobile', 'day', 'beforeafter', 'benefits', 'impact', 'roadmap', 'pilot', 'offer', 'closing'];
const EXEC = ['cover', 'exec', 'step1', 'impact', 'pilot'];

async function coverBg(file) {
  const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="1920" height="1080"><defs>
<radialGradient id="g" cx="78%" cy="30%" r="85%"><stop offset="0" stop-color="#0E8A45"/><stop offset="0.55" stop-color="#075A2E"/><stop offset="1" stop-color="#042614"/></radialGradient>
<radialGradient id="h" cx="8%" cy="95%" r="45%"><stop offset="0" stop-color="#39F500" stop-opacity="0.22"/><stop offset="1" stop-color="#39F500" stop-opacity="0"/></radialGradient></defs>
<rect width="1920" height="1080" fill="url(#g)"/><rect width="1920" height="1080" fill="url(#h)"/></svg>`;
  await sharp(Buffer.from(svg)).png().toFile(file);
}

module.exports = { S, FULL, EXEC, coverBg };
