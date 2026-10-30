// DAILY — presentación comercial visual. Uso: node deck.js <capsDir> <outDir>
const fs = require('fs'), path = require('path');
const sharp = require('sharp');
const { Slide, C, W, H, prepare, toPptx, toHtml } = require('./engine');

const MAIN = [245, 60, 1665]; // área de contenido (sin menú lateral ni barra superior)
const SECTIONS = [];
const S = {};

function content(key, section, kicker, title, notes) {
  const s = new Slide({ section, title });
  s.text(0.6, 0.36, 9, 0.28, kicker.toUpperCase(), { size: 11, bold: true, color: C.G3, spacing: 1.5 });
  s.notes = notes;
  S[key] = s; return s;
}
function sub(s, text) { s.text(0.6, 1.3, 12.1, 0.36, text, { size: 15, color: C.MUTED }); }
function callouts(s, x, y, w, items, color = C.R2, gap = 1.12) {
  items.forEach((it, i) => {
    const yy = y + i * gap;
    s.badge(x + 0.2, yy + 0.18, i + 1, color);
    s.text(x + 0.55, yy, w - 0.55, 0.32, it[0], { size: 15, bold: true });
    s.text(x + 0.55, yy + 0.34, w - 0.55, 0.7, it[1], { size: 12.5, color: C.MUTED });
  });
}
function iconCircle(s, x, y, d, name, fg, bg) {
  s.ellipse(x, y, d, d, { fill: bg });
  s.icon(x + d * 0.27, y + d * 0.27, d * 0.46, name, fg);
}
function flowRow(s, x, y, w, steps, o = {}) {
  const n = steps.length, gap = o.gap || 0.32, bw = (w - gap * (n - 1)) / n, h = o.h || 0.95;
  steps.forEach((st, i) => {
    const xx = x + i * (bw + gap);
    s.rect(xx, y, bw, h, { fill: i === n - 1 && o.lastDark ? C.G3 : C.WHITE, line: C.LINE, r: 0.1, shadow: true });
    iconCircle(s, xx + 0.14, y + (h - 0.56) / 2, 0.56, st[1], i === n - 1 && o.lastDark ? C.G3 : C.WHITE, i === n - 1 && o.lastDark ? C.WHITE : (st[2] || C.G2));
    s.text(xx + 0.8, y + 0.08, bw - 0.88, h - 0.16, st[0], { size: o.size || 13, bold: true, valign: 'middle', color: i === n - 1 && o.lastDark ? C.WHITE : C.INK });
    if (i < n - 1) s.line(xx + bw + 0.04, y + h / 2, xx + bw + gap - 0.04, y + h / 2, { color: C.G3, lw: 2, arrow: true });
  });
}
function caption(s, x, y, w, title, desc) {
  s.text(x, y, w, 0.3, title, { size: 14, bold: true });
  if (desc) s.text(x, y + 0.32, w, 0.5, desc, { size: 12, color: C.MUTED });
}

// ============ 1. PORTADA ============
{
  const s = new Slide({ cover: true, section: 'Apertura' });
  s.o.bg = '__COVER__';
  s.add({ t: 'img', abs: 'C:/Users/renzo/AppData/Local/Temp/claude/C--Users-renzo-grocy-grocy/c5cc64c9-4f97-4983-8b96-01417d0bc165/scratchpad/build/cache/logo_badge.png', x: 0.72, y: 0.8, w: 0.72, h: 0.72, alt: 'Logo DAILY' });
  s.text(1.5, 0.85, 3, 0.62, 'DAILY', { size: 30, bold: true, color: C.WHITE, valign: 'middle', spacing: 2 });
  s.text(0.75, 2.0, 5.6, 2.0, 'DAILY — Inteligencia y Control Operativo para Retail', { size: 38, bold: true, color: C.WHITE, lh: 0.95 });
  s.text(0.75, 4.15, 5.2, 1.0, 'Una plataforma para conectar inventario, pérdidas, ventas, productividad y operaciones.', { size: 17, color: 'D7F5DF' });
  s.pill(0.75, 5.45, 3.6, 'Capturas reales · datos de demostración', { fill: '0E4A2B', color: 'B9F3C9', size: 11, h: 0.36 });
  s.shot(6.55, 0.7, 6.2, 3.55, '01_ejecutivo/kpis', [245, 60, 1665]);
  s.shot(6.0, 3.05, 4.4, 2.52, '03_inventario/stockoverview', [245, 60, 1665]);
  s.shot(8.65, 4.35, 4.1, 2.35, '02_mermas/waste_full', [245, 280, 1665]);
  s.text(6.0, 5.65, 2.4, 0.3, 'Inventario', { size: 11, bold: true, color: 'B9F3C9' });
  s.text(6.55, 0.38, 3, 0.3, 'Panel Ejecutivo', { size: 11, bold: true, color: 'B9F3C9' });
  s.text(8.65, 6.78, 3, 0.3, 'Control de Mermas', { size: 11, bold: true, color: 'B9F3C9' });
  s.notes = 'QUÉ DECIR: "DAILY conecta en una sola plataforma lo que hoy vive en planillas separadas: inventario, mermas, ventas, personal y alertas." QUÉ MOSTRAR: las tres pantallas de la portada son capturas reales con datos de demostración. BENEFICIO: una sola fuente de verdad para la operación. PREGUNTA HABITUAL: "¿Es un ERP?" → No reemplaza su ERP o POS; se conecta a ellos (CSV, API, webhook) y agrega control operativo.';
  S.cover = s;
}

// ============ 2. PROBLEMAS ============
{
  const s = content('problems', 'Apertura', 'El problema', 'Lo que hoy le cuesta margen a un supermercado',
    'QUÉ DECIR: recorrer las 7 tarjetas y preguntar cuáles reconoce el cliente. QUÉ MOSTRAR: la última tarjeta (roja) resume la consecuencia. BENEFICIO: poner nombre al problema antes de hablar de software. PREGUNTA PARA EL CLIENTE: "¿Cuándo se enteran hoy de una diferencia de inventario: el mismo día o al cierre de mes?"');
  const P = [
    ['faEyeSlash', 'Pérdidas sin detectar a tiempo', 'Se descubren al cierre, sin margen de acción'],
    ['faScaleUnbalanced', 'Inventario físico ≠ sistema', 'Quiebres, sobrecompra y ventas perdidas'],
    ['faCalendarXmark', 'Productos vencidos', 'Desperdicio y riesgo para el cliente'],
    ['faUsers', 'Exceso o falta de personal', 'Filas en hora pico, horas ociosas en valle'],
    ['faFileLines', 'Procesos manuales', 'Planillas, firmas y horas de supervisión'],
    ['faPuzzlePiece', 'Información fragmentada', 'Cada área con su propia versión de los números'],
    ['faStore', 'Sin visibilidad entre sucursales', 'Las desviaciones se ven tarde']
  ];
  const tw = 2.88, th = 2.42, gx = 0.2;
  P.forEach((p, i) => {
    const x = 0.6 + (i % 4) * (tw + gx), y = 1.75 + Math.floor(i / 4) * (th + 0.22);
    s.rect(x, y, tw, th, { fill: C.WHITE, line: C.LINE, r: 0.12, shadow: true });
    iconCircle(s, x + 0.25, y + 0.25, 0.72, p[0], C.R2, C.RT);
    s.text(x + 0.25, y + 1.1, tw - 0.5, 0.62, p[1], { size: 15, bold: true });
    s.text(x + 0.25, y + 1.72, tw - 0.5, 0.6, '→ ' + p[2], { size: 12.5, color: C.MUTED });
  });
  const x = 0.6 + 3 * (tw + gx), y = 1.75 + th + 0.22;
  s.rect(x, y, tw, th, { fill: C.R3, r: 0.12, shadow: true });
  iconCircle(s, x + 0.25, y + 0.25, 0.72, 'faArrowTrendDown', C.R3, 'FFD9D6');
  s.text(x + 0.25, y + 1.1, tw - 0.5, 1.2, 'Resultado: margen que se pierde sin saber dónde ni por qué', { size: 16, bold: true, color: C.WHITE });
}

// ============ 3. FLUJO CONECTADO ============
{
  const s = content('flow', 'Apertura', 'La solución', 'Cómo DAILY conecta la operación',
    'QUÉ DECIR: "Cada paso de la mercancía deja un movimiento en un kardex único. Cuando algo no cuadra, DAILY lo convierte en alerta con responsable y plazo." QUÉ MOSTRAR: seguir el anillo desde Recepción hasta Acciones correctivas. BENEFICIO: trazabilidad de punta a punta. PREGUNTA HABITUAL: "¿Necesito cambiar mi POS?" → No; las ventas entran por integración (CSV, API o webhook).');
  const N = [['Recepción', 'faTruckRampBox'], ['Inventario', 'faBoxesStacked'], ['Almacenamiento', 'faWarehouse'], ['Reposición', 'faDolly'], ['Venta', 'faCashRegister'], ['Conciliación', 'faScaleBalanced'], ['Alertas', 'faBell'], ['Acciones correctivas', 'faListCheck']];
  const cx = 6.67, cy = 4.28, rx = 5.0, ry = 1.95, d = 0.95;
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
  s.text(cx - 1.7, cy - 0.1, 3.4, 0.7, 'Un kardex único · alertas · responsables · indicadores', { size: 12.5, color: 'D7F5DF', align: 'center' });
}

// ============ 4. PANEL EJECUTIVO ============
{
  const s = content('exec', 'Supervisión', 'Panel ejecutivo', 'Las desviaciones del mes, en una sola pantalla',
    'QUÉ DECIR: "Un gerente abre esta pantalla y en 30 segundos sabe si la venta, el margen, la merma y la rotación van bien." QUÉ MOSTRAR: (1) KPIs con variación vs. mes anterior; (2) recomendaciones con el botón que lleva al detalle; (3) riesgos que requieren decisión. BENEFICIO: decidir con datos del día, no del cierre. PREGUNTA HABITUAL: "¿De dónde salen los números?" → De las ventas, compras y movimientos registrados; cada indicador muestra su fórmula o meta.');
  const e = s.shot(0.6, 1.75, 8.4, 5.07, '01_ejecutivo/kpis', MAIN);
  s.pin(e, 262, 340, 1); s.pin(e, 262, 560, 2); s.pin(e, 1100, 750, 3);
  s.ring(e, 1085, 335, 1468, 512);
  callouts(s, 9.35, 1.85, 3.4, [
    ['KPIs con alerta visual', 'Venta, margen, merma y rotación contra el período anterior y la meta.'],
    ['Recomendaciones', 'Ritmo de ventas, merma sobre objetivo y lotes en riesgo, con acceso directo.'],
    ['Riesgos por decidir', 'Presupuesto, desfase POS vs. kardex y planes de acción pendientes.']
  ], C.R2, 1.3);
  s.rect(9.35, 5.85, 3.4, 0.95, { fill: C.RT, r: 0.1 });
  s.text(9.5, 5.92, 3.1, 0.8, [{ text: 'Merma 4,54 % vs. meta 2,5 %', bold: true, color: C.R2 }, { text: 'El gerente la ve el mismo día.', color: C.INK }], { size: 13, valign: 'middle' });
  s.demoTag(0.75, 6.42);
}

// ============ 5. MERMAS: VISTA GENERAL ============
{
  const s = content('wasteHub', 'Control y prevención de mermas', 'Control de mermas', 'Todo el ciclo de la merma en un solo módulo',
    'QUÉ DECIR: "Aquí vive todo lo que tiene que ver con pérdidas: el resumen, la clasificación operativa y comercial, averías, ajustes, inventarios selectivos, investigaciones, aprobaciones y prevención por vencimiento." QUÉ MOSTRAR: el resumen y las cuatro vistas de la derecha. BENEFICIO: la merma deja de ser un número al cierre y pasa a ser una lista de causas. PREGUNTA HABITUAL: "¿Distingue merma operativa de comercial?" → Sí, cada registro se clasifica por causal y tipo.');
  sub(s, 'Resumen ejecutivo, clasificación, averías, ajustes, conteos, investigaciones, aprobaciones y prevención FEFO.');
  s.shot(0.6, 1.9, 7.3, 4.75, '02_mermas/waste_full', [245, 370, 1665]);
  s.demoTag(0.75, 6.25);
  const T = [['waste_tab_clasificacion', 'Operativa y comercial'], ['waste_tab_averias', 'Averías y roturas'], ['waste_tab_conteos', 'Inventarios selectivos'], ['waste_tab_prevencion', 'Prevención y rebajas FEFO']];
  T.forEach((t, i) => {
    const x = 8.2 + (i % 2) * 2.3, y = 1.9 + Math.floor(i / 2) * 2.45;
    s.shot(x, y, 2.15, 1.62, '02_mermas/' + t[0], [245, 100, 1665]);
    s.text(x, y + 1.72, 2.15, 0.5, t[1], { size: 12.5, bold: true });
  });
}

// ============ 6. PASO 1 — IDENTIFICAR ============
{
  const s = content('step1', 'Control y prevención de mermas', 'Demostración · Paso 1 de 4', 'Identificar un producto sensible con diferencia',
    'QUÉ DECIR: "El celular de alto valor tiene 10 unidades recibidas; tras una venta y un despacho, el sistema espera 8. El conteo ciego registró 6: faltan 2 unidades, $1.040." QUÉ MOSTRAR: (1) etapa Inventario sensible; (2) tarjeta Faltantes detectados; (3) alerta preliminar con botón de investigación. BENEFICIO: la diferencia aparece al contar, no al cierre. ACLARAR: DAILY no presume robo; la diferencia queda como alerta preliminar hasta el reconteo.');
  const e = s.shot(0.6, 1.75, 8.2, 4.2, '02_mermas/reconciliation', [256, 230, 1650]);
  s.pin(e, 1290, 264, 1); s.pin(e, 268, 335, 2); s.pin(e, 648, 655, 3);
  s.ring(e, 640, 640, 1895, 788);
  s.demoTag(0.75, 6.12);
  // cálculo
  const x = 9.15;
  s.text(x, 1.78, 3.6, 0.3, 'Lo que muestra la ficha del producto', { size: 13, bold: true, color: C.MUTED });
  const R = [['10', 'unidades recibidas', C.INK], ['− 2', 'vendida y despachada', C.MUTED], ['8', 'esperadas en sistema', C.INK], ['6', 'contadas en físico', C.INK]];
  R.forEach((r, i) => {
    const y = 2.15 + i * 0.78;
    s.rect(x, y, 3.6, 0.66, { fill: C.WHITE, line: C.LINE, r: 0.08 });
    s.text(x + 0.15, y, 0.95, 0.66, r[0], { size: 24, bold: true, color: r[2], valign: 'middle', align: 'center' });
    s.text(x + 1.15, y, 2.35, 0.66, r[1], { size: 13.5, valign: 'middle' });
  });
  s.rect(x, 5.3, 3.6, 1.0, { fill: C.R2, r: 0.1, shadow: true });
  s.text(x + 0.15, 5.3, 3.3, 1.0, [{ text: 'Diferencia: −2 u.', size: 20, bold: true, color: C.WHITE }, { text: '$1.040 a costo · alerta INV-2026-0001', size: 12.5, color: 'FFE1DE' }], { valign: 'middle' });
  s.text(x, 6.4, 3.6, 0.45, 'Caso de la base de demostración.', { size: 10.5, color: C.MUTED, italic: true });
}

// ============ 7. PASO 2 — REVISAR MOVIMIENTOS ============
{
  const s = content('step2', 'Control y prevención de mermas', 'Demostración · Paso 2 de 4', 'Revisar todos sus movimientos en una línea de tiempo',
    'QUÉ DECIR: "Sin pedir reportes a nadie, el responsable ve los 6 eventos del producto: ingreso, conteo, venta, traslado, despacho y el conteo con diferencia; cada uno con documento, usuario, ubicación y número de serie." QUÉ MOSTRAR: la línea de tiempo y los filtros (Ingresos, Ventas, Devoluciones, Despachos, Traslados, Conteos, Ajustes). BENEFICIO: la investigación pasa de días a minutos. PREGUNTA HABITUAL: "¿Se puede alterar el historial?" → El kardex es inalterable y se puede verificar su integridad.');
  const e = s.shot(0.6, 1.75, 7.4, 4.73, '02_mermas/reconciliation_full', [620, 1320, 1280]);
  s.ring(e, 628, 1385, 1900, 1495); s.pin(e, 640, 1390, 1, C.R2);
  s.demoTag(0.75, 6.1);
  const x = 8.35;
  s.text(x, 1.78, 4.4, 0.45, [{ text: '6 eventos', size: 26, bold: true, color: C.G3, inline: true }, { text: '  en una sola vista', size: 15, color: C.MUTED }], {});
  const E = [['faTruckRampBox', 'Ingreso 10 u. con verificación de series', '10 oct'], ['faCashRegister', 'Venta en POS de 1 u.', '18 oct'], ['faTruckFast', 'Despacho reservado de 1 u.', '24 oct'], ['faClipboardList', 'Conteo sensible: 6 vs. 8 esperadas', '26 oct']];
  E.forEach((v, i) => {
    const y = 2.5 + i * 0.86, red = i === 3;
    iconCircle(s, x, y, 0.58, v[0], red ? C.R2 : C.G3, red ? C.RT : C.GT);
    s.text(x + 0.75, y + 0.02, 3.0, 0.56, v[1], { size: 13.5, bold: red, valign: 'middle', color: red ? C.R2 : C.INK });
    s.text(x + 3.75, y + 0.02, 0.65, 0.56, v[2], { size: 11, color: C.MUTED, valign: 'middle', align: 'right' });
  });
  s.rect(x, 6.0, 4.4, 0.8, { fill: C.GT, r: 0.1 });
  s.text(x + 0.18, 6.0, 4.1, 0.8, 'Cada evento enlaza su documento, usuario, ubicación y número de serie.', { size: 12.5, color: C.GD, valign: 'middle' });
}

// ============ 8. PASO 3 — CAUSA ============
{
  const s = content('step3', 'Control y prevención de mermas', 'Demostración · Paso 3 de 4', 'Determinar la causa con señales y causales',
    'QUÉ DECIR: "El kardex marca automáticamente lo que merece revisión: mermas sin caso, posibles duplicados y movimientos fuera de horario. La matriz de causales define qué evidencia exige cada tipo de pérdida." QUÉ MOSTRAR: (1)(2)(3) las tres señales; a la derecha, la matriz con código, tipo y foto obligatoria. BENEFICIO: causas comparables entre tiendas, no explicaciones sueltas. PREGUNTA HABITUAL: "¿Detecta robo?" → Señala diferencias y patrones; no se integra hoy con cámaras ni antenas antihurto.');
  const e = s.shot(0.6, 1.75, 6.55, 4.55, '03_inventario/kardex_full', [256, 305, 1650]);
  s.pin(e, 268, 330, 1); s.pin(e, 650, 330, 2); s.pin(e, 1032, 330, 3);
  caption(s, 0.6, 6.42, 6.55, 'Kardex valorizado · señales por revisar', null);
  s.shot(7.45, 1.75, 5.28, 4.55, '02_mermas/waste_reasons_full', [245, 60, 1665]);
  caption(s, 7.45, 6.42, 5.28, 'Matriz de causales: código, tipo, riesgo y evidencia', null);
}

// ============ 9. PASO 4 — ACTUAR ============
{
  const s = content('step4', 'Control y prevención de mermas', 'Demostración · Paso 4 de 4', 'Investigar, aprobar y corregir con responsables',
    'QUÉ DECIR: "La diferencia abre una investigación con plazo y método de 5 porqués; el ajuste lo aprueba una persona distinta de quien reporta; y la acción correctiva llega como tarea al celular del responsable." QUÉ MOSTRAR: los tres pasos de izquierda a derecha. BENEFICIO: control interno con segregación de funciones, sin papeles. PREGUNTA HABITUAL: "¿Quién puede aprobar?" → Se configura por rol y por monto; quien reporta nunca aprueba su propio registro.');
  const y = 1.9;
  s.shot(0.6, y, 4.3, 2.65, '02_mermas/waste_tab_investigaciones', [245, 90, 1665]);
  s.shot(5.35, y, 4.3, 2.65, '02_mermas/waste_approvals', MAIN);
  s.phone(10.65, 1.75, 1.6, '10_movil/m_tasks');
  s.line(4.98, y + 1.32, 5.27, y + 1.32, { color: C.G3, lw: 2.5, arrow: true });
  s.line(9.73, y + 1.32, 10.57, y + 1.32, { color: C.G3, lw: 2.5, arrow: true });
  const L = [[0.6, 4.3, 'Investigación', 'Responsable, plazo y método de 5 porqués.', 4.95], [5.35, 4.3, 'Aprobación', 'Quien reporta no aprueba; niveles por monto.', 4.95], [9.95, 2.8, 'Tarea correctiva', 'Llega al móvil del responsable.', 5.85]];
  L.forEach((l, i) => { const yy = l[4]; s.badge(l[0] + 0.2, yy, i + 1, C.G3); s.text(l[0] + 0.5, yy - 0.17, l[1] - 0.5, 0.34, l[2], { size: 15, bold: true }); s.text(l[0] + 0.5, yy + 0.19, l[1] - 0.5, 0.6, l[3], { size: 12.5, color: C.MUTED }); });
  s.rect(0.6, 6.0, 9.05, 0.75, { fill: C.YT, r: 0.1 });
  s.text(0.8, 6.0, 8.7, 0.75, 'Seguimiento: alertas y tareas quedan abiertas hasta su cierre con evidencia.', { size: 13, color: C.Y3, bold: true, valign: 'middle' });
}

// ============ 10. INVENTARIO — STOCK ============
{
  const s = content('stock', 'Inventario y trazabilidad', 'Inventario', 'Stock, valor y riesgo de cada sucursal',
    'QUÉ DECIR: "El responsable de inventario ve existencias, valor, rotación y días de cobertura; dónde se concentra el valor y qué productos están en riesgo." QUÉ MOSTRAR: (1) KPIs de inventario; (2) evolución y distribución por departamento; (3) mapa de valor y productos en riesgo. BENEFICIO: menos capital inmovilizado y menos quiebres. PREGUNTA HABITUAL: "¿Maneja lotes?" → Sí, por lote, vencimiento y ubicación.');
  const e = s.shot(0.6, 1.75, 8.4, 5.07, '03_inventario/stockoverview', MAIN);
  s.pin(e, 262, 230, 1, C.G3); s.pin(e, 262, 405, 2, C.G3); s.pin(e, 262, 680, 3, C.G3);
  callouts(s, 9.35, 1.85, 3.4, [
    ['Existencias y valor', 'Unidades, valor a costo, rotación y días de inventario.'],
    ['Tendencia', 'Evolución del inventario y su distribución por departamento.'],
    ['Riesgo', 'Dónde se concentra el valor y qué lotes requieren acción.']
  ], C.G3, 1.3);
  s.demoTag(0.75, 6.42);
}

// ============ 11. INVENTARIO — FLUJO ============
{
  const s = content('invflow', 'Inventario y trazabilidad', 'Trazabilidad', 'De la recepción a la conciliación, paso a paso',
    'QUÉ DECIR: "Cada unidad entra, se ubica, se mueve y se cuenta; al final, DAILY concilia lo físico contra lo digital." QUÉ MOSTRAR: el flujo superior y tres herramientas: mapa de ubicaciones, traslados en dos pasos y conteos selectivos. BENEFICIO: saber dónde está cada producto y por qué falta. PREGUNTA HABITUAL: "¿Los conteos son ciegos?" → Sí, por defecto el contador no ve la cantidad esperada.');
  flowRow(s, 0.6, 1.72, 12.13, [['Recepción', 'faTruckRampBox'], ['Ubicación', 'faLocationDot'], ['Movimiento', 'faRightLeft'], ['Conteo', 'faClipboardCheck'], ['Conciliación', 'faScaleBalanced']], { h: 0.82, lastDark: true });
  const I = [['03_inventario/locations_map', 'Mapa de ubicaciones', 'Almacén › zona › pasillo › estante, con etiquetas QR.'], ['03_inventario/transfers', 'Traslados entre tiendas', 'Envío y recepción; las diferencias pasan a merma.'], ['02_mermas/waste_tab_conteos', 'Conteos selectivos y sensibles', 'Conteo ciego priorizado por riesgo.']];
  I.forEach((it, i) => {
    const x = 0.6 + i * 4.12;
    s.shot(x, 2.9, 3.89, 2.55, it[0], i === 2 ? [245, 100, 1665] : MAIN);
    caption(s, x, 5.6, 3.89, it[1], it[2]);
  });
}

// ============ 12. INVENTARIO — ESCÁNER ============
{
  const s = content('scan', 'Inventario y trazabilidad', 'Operación en piso', 'Escanear, consultar y registrar desde el pasillo',
    'QUÉ DECIR: "El colaborador escanea producto, lote o ubicación y registra una merma, un conteo, un ajuste o una reposición sin volver a la oficina." QUÉ MOSTRAR: la pantalla web de operaciones con escáner y las dos vistas móviles. BENEFICIO: el dato se registra donde ocurre. PREGUNTA HABITUAL: "¿Funciona con lectores Zebra o Honeywell?" → Funciona con cámara y con lectores en modo teclado; aún no se ha validado con hardware específico del cliente.');
  const e = s.shot(0.6, 1.75, 7.6, 4.6, '03_inventario/scan', MAIN);
  s.demoTag(0.75, 5.95);
  s.phone(8.6, 1.75, 1.95, '10_movil/m_scan');
  s.phone(10.78, 1.75, 1.95, '10_movil/m_inv');
  caption(s, 8.6, 6.1, 1.95, 'Escáner móvil', null);
  caption(s, 10.78, 6.1, 1.95, 'Inventario móvil', null);
  s.text(0.6, 6.5, 7.6, 0.4, 'Cámara del teléfono, lector tipo teclado y códigos GS1.', { size: 12.5, color: C.MUTED });
}

// ============ 13. CALIDAD Y VENCIMIENTOS ============
{
  const s = content('quality', 'Calidad y vencimientos', 'Calidad y vencimientos', 'Actuar antes de que el producto se venza',
    'QUÉ DECIR: "DAILY identifica los lotes próximos a vencer, los prioriza por valor y días restantes y propone la acción: rotar, rebajar o retirar. Luego se sigue hasta cerrar." QUÉ MOSTRAR: lotes que requieren atención y la vista de rebajas FEFO. BENEFICIO: menos desperdicio y protección de la calidad. PREGUNTA HABITUAL: "¿Respeta FEFO en la venta?" → Sí, la venta, el picking y los traslados descuentan primero el lote que vence primero.');
  flowRow(s, 0.6, 1.72, 12.13, [['Próximo a vencer', 'faHourglassHalf', C.Y2], ['Identificación', 'faMagnifyingGlass'], ['Priorización', 'faArrowUpWideShort'], ['Acción preventiva', 'faTags'], ['Seguimiento', 'faCircleCheck']], { h: 0.82, lastDark: true });
  s.shot(0.6, 2.9, 5.95, 3.4, '04_calidad/quality', MAIN);
  s.shot(6.78, 2.9, 5.95, 3.4, '02_mermas/waste_tab_prevencion', [245, 90, 1665]);
  caption(s, 0.6, 6.42, 5.95, 'Calidad & Vencimientos: lotes que requieren atención', null);
  caption(s, 6.78, 6.42, 5.95, 'Control FEFO: rebajas sugeridas por vencimiento', null);
}

// ============ 14. VENTAS ============
{
  const s = content('sales', 'Ventas y rentabilidad', 'Ventas y rentabilidad', 'Saber a tiempo si se llega a la meta',
    'QUÉ DECIR: "Venta, utilidad, margen y tickets contra el período anterior; el termómetro proyecta el cierre con el ritmo real." QUÉ MOSTRAR: (1) KPIs; (2) termómetro con monto pendiente; (3) curva diaria. Para bajo rendimiento: Nivel C, detalle por producto y departamento. BENEFICIO: corregir el mes antes de que termine. PREGUNTA HABITUAL: "¿El margen descuenta la merma?" → Sí, se muestra margen antes y después de merma.');
  const e = s.shot(0.6, 1.75, 8.4, 5.07, '05_ventas/sales_scroll', [245, 56, 1665]);
  s.mask(e, 610, 366, 800, 384);
  s.pin(e, 252, 62, 1, C.G3); s.pin(e, 262, 243, 2, C.R2); s.pin(e, 262, 735, 3, C.G3);
  s.ring(e, 535, 60, 795, 192);
  callouts(s, 9.35, 1.85, 3.4, [
    ['Ventas, utilidad y margen', 'Comparados con el período equivalente anterior.'],
    ['Avance contra la meta', 'Monto pendiente y proyección de cierre con el ritmo real.'],
    ['Bajo rendimiento', 'Ventas y margen por sucursal, departamento y producto.']
  ], C.G3, 1.3);
  s.demoTag(0.75, 6.42);
}

// ============ 15. PERSONAL — PLANIFICACIÓN ============
{
  const s = content('staff', 'Personal y productividad', 'Personal · horarios', 'Turnos planificados según la venta esperada',
    'QUÉ DECIR: "DAILY proyecta la venta por día y franja, calcula las horas necesarias y las compara con las programadas, la venta por hora trabajada y el costo contra presupuesto." QUÉ MOSTRAR: el escenario de demostración (25 personas ficticias), el aviso de subcobertura y el gráfico ventas proyectadas vs. horas. BENEFICIO: personal donde está la demanda. ACLARAR: las recomendaciones son por reglas y estadística explicable; la conexión con nómina está en desarrollo.');
  const e = s.shot(0.6, 1.75, 8.4, 5.07, '06_personal/scheduling_full', [250, 282, 1660]);
  s.pin(e, 262, 352, 1, C.R2); s.pin(e, 262, 460, 2, C.G3); s.pin(e, 262, 690, 3, C.G3);
  const x = 9.35;
  s.text(x, 1.8, 3.4, 0.3, 'Disponible hoy', { size: 13, bold: true, color: C.G3 });
  ['Pronóstico de venta por franja', 'Horas necesarias vs. programadas', 'Venta por hora trabajada', 'Costo laboral vs. presupuesto', 'Recomendaciones y simulador'].forEach((t, i) => {
    s.icon(x, 2.2 + i * 0.5, 0.26, 'faCircleCheck', C.G2);
    s.text(x + 0.38, 2.16 + i * 0.5, 3.0, 0.34, t, { size: 13 });
  });
  s.text(x, 4.85, 3.4, 0.3, 'En desarrollo', { size: 13, bold: true, color: C.Y3 });
  ['Conector con nómina', 'Geolocalización en fichaje'].forEach((t, i) => {
    s.icon(x, 5.25 + i * 0.5, 0.26, 'faClock', C.Y2);
    s.text(x + 0.38, 5.21 + i * 0.5, 3.0, 0.34, t, { size: 13 });
  });
}

// ============ 16. PERSONAL — ASISTENCIA Y PRODUCTIVIDAD ============
{
  const s = content('staff2', 'Personal y productividad', 'Personal · ejecución', 'Asistencia, tareas y productividad del día',
    'QUÉ DECIR: "El supervisor ve quién llegó, quién falta y la asistencia por departamento; en Piso de Tienda asigna tareas y ve cuáles se completaron." QUÉ MOSTRAR: control de asistencia y tablero de tareas. BENEFICIO: menos tiempo persiguiendo información, más tiempo en la tienda. PREGUNTA HABITUAL: "¿Cómo marca el empleado?" → Desde la web o el celular; las correcciones requieren aprobación de otra persona.');
  s.shot(0.6, 1.75, 5.95, 3.55, '06_personal/attendance', MAIN);
  s.shot(6.78, 1.75, 5.95, 3.55, '06_personal/workforce', MAIN);
  caption(s, 0.6, 5.45, 5.95, 'Asistencia y dotación', 'Programados, presentes, tardanzas y ausencias por departamento.');
  caption(s, 6.78, 5.45, 5.95, 'Piso de tienda: tareas', 'Tareas asignadas, en curso y completadas, con prioridad.');
  s.demoTag(0.75, 4.9);
}

// ============ 17. MULTISUCURSAL ============
{
  const s = content('branches', 'Operación multisucursal', 'Operación multisucursal', 'Qué tienda se desvía, de un vistazo',
    'QUÉ DECIR: "Cada sucursal tiene su tarjeta con venta, margen, merma y casos abiertos; el semáforo marca en rojo las que superan el tope corporativo." QUÉ MOSTRAR: (1) brecha entre la mejor y la peor; (2) sucursal sobre el tope; (3) accesos directos a stock, mermas y ventas. BENEFICIO: el gerente regional prioriza visitas con datos. ACLARAR: son sucursales de demostración con datos ficticios, no resultados de clientes.');
  const e = s.shot(0.6, 1.75, 8.4, 5.07, '07_multisucursal/branches', [245, 66, 1665]);
  s.pin(e, 1368, 160, 1); s.pin(e, 632, 340, 2); s.pin(e, 262, 586, 3, C.G3);
  s.ring(e, 255, 326, 796, 605); s.ring(e, 1356, 326, 1897, 605);
  callouts(s, 9.35, 1.85, 3.4, [
    ['Brecha de merma', 'Diferencia entre la mejor y la peor sucursal.'],
    ['Semáforo por tienda', 'Rojo cuando supera el tope corporativo.'],
    ['Del indicador al detalle', 'Stock, mermas y ventas de esa tienda en un clic.']
  ], C.R2, 1.3);
  s.rect(9.35, 5.85, 3.4, 0.95, { fill: C.WHITE, line: C.LINE, r: 0.1 });
  s.text(9.5, 5.85, 3.1, 0.95, '8 sucursales de demostración + matriz. Datos ficticios.', { size: 12, color: C.MUTED, valign: 'middle' });
}

// ============ 18. CENTRO DE ALERTAS ============
{
  const s = content('alerts', 'Alertas', 'Centro de alertas', 'Qué atender primero, y quién lo atiende',
    'QUÉ DECIR: "Cada alerta tiene prioridad, responsable, plazo e impacto estimado; el panel derecho muestra en qué etapa está y cuánto queda del plazo." QUÉ MOSTRAR: KPIs de alertas, la lista ordenada por prioridad y el progreso de resolución. BENEFICIO: nada queda sin dueño. ACLARAR: esta vista web usa un escenario de demostración fijo; el motor de alertas (reglas, responsables, cierre con aprobación) es el que alimenta Hoy y la app móvil.');
  const e = s.shot(0.6, 1.75, 7.7, 4.6, '08_alertas/alerts', [235, 60, 1675]);
  s.pin(e, 250, 140, 1); s.pin(e, 250, 600, 2); s.pin(e, 1385, 735, 3, C.G3);
  s.demoTag(0.75, 5.95, 'Pantalla real · escenario de demostración');
  // ciclo
  const Cy = [['Detección', 'faTowerBroadcast'], ['Notificación', 'faBell'], ['Responsable', 'faUserCheck'], ['Investigación', 'faMagnifyingGlass'], ['Acción', 'faWrench'], ['Cierre', 'faCircleCheck']];
  const x = 8.7;
  Cy.forEach((c, i) => {
    const y = 1.8 + i * 0.78, last = i === 5;
    iconCircle(s, x, y, 0.6, c[1], last ? C.WHITE : C.R2, last ? C.G3 : C.RT);
    s.text(x + 0.78, y, 3.2, 0.6, c[0], { size: 15, bold: true, valign: 'middle' });
    if (i < 5) s.line(x + 0.3, y + 0.62, x + 0.3, y + 0.76, { color: C.MUTED, lw: 1.5, arrow: true });
  });
  s.text(x, 6.45, 4, 0.4, 'Ordenadas por prioridad, plazo e impacto.', { size: 12.5, color: C.MUTED });
}

// ============ 19. SEGURIDAD INDUSTRIAL ============
{
  const s = content('sst', 'Seguridad industrial', 'Seguridad y salud en el trabajo', 'Seguridad industrial: base disponible, módulo en desarrollo',
    'QUÉ DECIR: "Hoy DAILY ya permite auditorías con plantillas ponderadas y acciones correctivas, y alertas y tareas con responsable y plazo. El módulo específico de accidentes e incidentes laborales está en la hoja de ruta." QUÉ MOSTRAR: la pantalla de auditorías y el proceso propuesto (rotulado). BENEFICIO: una sola herramienta para seguimiento de hallazgos. PREGUNTA HABITUAL: "¿Cuándo estará?" → Se define en el diagnóstico según prioridad del cliente; no comprometer fecha.');
  s.pill(0.6, 1.3, 2.9, 'Disponible hoy', { fill: C.GT, color: C.GD, size: 12, h: 0.34 });
  s.shot(0.6, 1.85, 5.9, 3.42, '04_calidad/audits', MAIN);
  ['Auditorías con plantillas ponderadas', 'Acciones correctivas con responsable', 'Alertas y tareas con plazo'].forEach((t, i) => {
    s.icon(0.6, 5.5 + i * 0.45, 0.26, 'faCircleCheck', C.G2);
    s.text(0.98, 5.46 + i * 0.45, 5.4, 0.34, t, { size: 13 });
  });
  s.pill(7.0, 1.3, 3.6, 'Proceso propuesto · en desarrollo', { fill: C.YT, color: C.Y3, size: 12, h: 0.34 });
  const St = [['Reporte de incidente o accidente', 'faTriangleExclamation'], ['Clasificación y gravedad', 'faLayerGroup'], ['Investigación de causas', 'faMagnifyingGlass'], ['Acción preventiva', 'faHelmetSafety'], ['Seguimiento hasta cierre', 'faListCheck'], ['Indicadores de seguridad', 'faChartLine']];
  St.forEach((t, i) => {
    const y = 1.85 + i * 0.8;
    s.rect(7.0, y, 5.73, 0.64, { fill: C.WHITE, line: C.Y2, lineW: 1, dash: true, r: 0.1 });
    s.icon(7.2, y + 0.17, 0.3, t[1], C.Y3);
    s.text(7.7, y, 4.9, 0.64, t[0], { size: 13.5, valign: 'middle' });
  });
}

// ============ 20. MÓVIL ============
{
  const s = content('mobile', 'Experiencia móvil', 'Experiencia móvil', 'La operación en el bolsillo de cada colaborador',
    'QUÉ DECIR: "El colaborador abre DAILY en su celular: ve su día, sus tareas, escanea, revisa casos, consulta inventario y registra mermas con foto." QUÉ MOSTRAR: las seis pantallas. BENEFICIO: el dato se captura donde ocurre. PREGUNTA HABITUAL: "¿Hay que instalar una app?" → Es una app web instalable (PWA), con trabajo sin conexión; no está en tiendas de aplicaciones.');
  const P = [['m_home', 'Hoy'], ['m_tasks', 'Tareas'], ['m_scan', 'Escáner'], ['m_control', 'Alertas y casos'], ['m_inv', 'Inventario'], ['m_capture', 'Registro de incidencias']];
  P.forEach((p, i) => {
    const x = 0.6 + i * 2.06;
    s.phone(x, 1.7, 1.88, '10_movil/' + p[0]);
    s.text(x - 0.1, 5.95, 2.08, 0.32, p[1], { size: 13.5, bold: true, align: 'center' });
  });
  s.text(0.6, 6.4, 12.1, 0.4, 'App web instalable, con trabajo sin conexión. Pantallas reales con datos de demostración.', { size: 12.5, color: C.MUTED, align: 'center' });
}

// ============ 21. UN DÍA CON DAILY ============
{
  const s = content('day', 'Un día con DAILY', 'Escenario ilustrativo', 'Un día de operación con DAILY',
    'QUÉ DECIR: contar el día de un gerente de tienda usando las pantallas reales: abre Hoy, valida la recepción, revisa el inventario, sigue los turnos, atiende alertas y cierra con indicadores. QUÉ MOSTRAR: la línea de tiempo. BENEFICIO: DAILY acompaña toda la jornada, no solo el cierre. ACLARAR: es un escenario ilustrativo.');
  const D = [['07:00', 'Inicio de operaciones', '01_ejecutivo/today', MAIN], ['09:00', 'Recepción y validación', '09_compras/mrp', MAIN], ['11:00', 'Control de inventario', '02_mermas/inventory_control', MAIN], ['13:00', 'Turnos y productividad', '06_personal/scheduling_full', [250, 1230, 1660]], ['15:00', 'Revisión de alertas', '08_alertas/alerts', [1150, 560, 750]], ['17:00', 'Indicadores y cierre', '05_ventas/reports', MAIN]];
  s.pill(10.13, 0.36, 2.6, 'Escenario ilustrativo', { fill: C.YT, color: C.Y3, size: 11, h: 0.32 });
  D.forEach((d, i) => {
    const x = 0.6 + (i % 3) * 4.12, y = 1.65 + Math.floor(i / 3) * 2.62;
    s.shot(x, y + 0.55, 3.89, 1.85, d[2], d[3]);
    s.rect(x - 0.04, y, 1.05, 0.42, { fill: C.G3, r: 0.21 });
    s.text(x - 0.04, y, 1.05, 0.42, d[0], { size: 15, bold: true, color: C.WHITE, align: 'center', valign: 'middle' });
    s.text(x + 1.15, y, 2.74, 0.42, d[1], { size: 14.5, bold: true, valign: 'middle' });
    if (i % 3 < 2) s.line(x + 3.93, y + 1.47, x + 4.08, y + 1.47, { color: C.G3, lw: 2, arrow: true });
  });
}

// ============ 22. ANTES Y DESPUÉS ============
{
  const s = content('beforeafter', 'Valor', 'Antes y después', 'Del registro disperso al control con responsables',
    'QUÉ DECIR: comparar cómo se trabaja hoy con cómo se trabajaría con DAILY. QUÉ MOSTRAR: las cuatro filas. BENEFICIO: el cambio está en el proceso, no solo en el software. ACLARAR: no prometemos un ahorro; lo medimos en el piloto contra la línea base del cliente.');
  const A = [['faFileLines', 'Registros separados por área'], ['faPenToSquare', 'Validaciones manuales'], ['faEyeSlash', 'Poca visibilidad entre tiendas'], ['faHourglassEnd', 'Decisiones al cierre de mes']];
  const B = [['faDatabase', 'Información centralizada'], ['faRoute', 'Trazabilidad por producto y lote'], ['faUserCheck', 'Responsables y plazos por caso'], ['faChartLine', 'Análisis operativo diario']];
  s.rect(0.6, 1.75, 5.8, 5.05, { fill: 'EEF1F0', r: 0.14 });
  s.rect(6.93, 1.75, 5.8, 5.05, { fill: C.G3, r: 0.14, shadow: true });
  s.text(0.95, 1.95, 5, 0.5, 'Antes', { size: 22, bold: true, color: C.MUTED });
  s.text(7.28, 1.95, 5, 0.5, 'Con DAILY', { size: 22, bold: true, color: C.WHITE });
  A.forEach((a, i) => { const y = 2.75 + i * 0.98; iconCircle(s, 0.95, y, 0.62, a[0], C.MUTED, 'DDE2E0'); s.text(1.8, y, 4.4, 0.62, a[1], { size: 16, valign: 'middle', color: C.INK }); });
  B.forEach((a, i) => { const y = 2.75 + i * 0.98; iconCircle(s, 7.28, y, 0.62, a[0], C.G3, C.WHITE); s.text(8.13, y, 4.4, 0.62, a[1], { size: 16, bold: true, valign: 'middle', color: C.WHITE }); });
  s.ellipse(6.31, 3.92, 0.72, 0.72, { fill: C.WHITE, line: C.LINE, shadow: true });
  s.icon(6.49, 4.1, 0.36, 'faArrowRight', C.G3);
}

// ============ 23. BENEFICIOS ============
{
  const s = content('benefits', 'Valor', 'Beneficios', 'Seis beneficios, cada uno con su herramienta',
    'QUÉ DECIR: cada beneficio se apoya en una herramienta que ya mostramos. QUÉ MOSTRAR: la herramienta en verde de cada tarjeta. BENEFICIO: valor concreto, no promesas. PREGUNTA HABITUAL: "¿Cuál da resultados más rápido?" → En general, mermas e inventario sensible, porque las diferencias aparecen desde el primer conteo.');
  const B = [['faMagnifyingGlassDollar', 'Mayor visibilidad de las pérdidas', 'Control de Mermas'], ['faBoxesStacked', 'Mejor control del inventario', 'Kardex y conciliación'], ['faCalendarCheck', 'Prevención de vencimientos', 'Calidad y control FEFO'], ['faStopwatch', 'Optimización del tiempo operativo', 'Hoy, tareas y app móvil'], ['faUsersGear', 'Seguimiento de productividad', 'Horarios y asistencia'], ['faChartColumn', 'Decisiones respaldadas por indicadores', 'Panel Ejecutivo y reportes']];
  B.forEach((b, i) => {
    const x = 0.6 + (i % 3) * 4.12, y = 1.75 + Math.floor(i / 3) * 2.6;
    s.rect(x, y, 3.89, 2.38, { fill: C.WHITE, line: C.LINE, r: 0.12, shadow: true });
    iconCircle(s, x + 0.28, y + 0.28, 0.8, b[0], C.G3, C.GT);
    s.text(x + 1.25, y + 0.28, 0.5, 0.8, String(i + 1), { size: 26, bold: true, color: 'C9D3CD', valign: 'middle' });
    s.text(x + 0.28, y + 1.22, 3.35, 0.62, b[1], { size: 16, bold: true });
    s.pill(x + 0.28, y + 1.84, 3.0, b[2], { size: 11.5, h: 0.32 });
  });
}

// ============ 24. IMPACTO ECONÓMICO ============
{
  const s = content('impact', 'Valor', 'Impacto económico', 'Cada medio punto de merma cuenta',
    'QUÉ DECIR: "Tomemos una tienda que vende $1.000.000 al mes con 2 % de merma: pierde $20.000. Si la baja a 1,5 %, pierde $15.000. Son $5.000 al mes, $60.000 al año." QUÉ MOSTRAR: el gráfico es editable en PowerPoint (clic derecho → Editar datos) para usar las cifras del cliente. BENEFICIO: dimensionar el valor con sus números. ACLARAR SIEMPRE: es un ejemplo ilustrativo, no un resultado ni una garantía.');
  s.rect(0.6, 1.75, 6.6, 5.05, { fill: C.WHITE, line: C.LINE, r: 0.12, shadow: true });
  s.text(0.9, 1.92, 6, 0.35, 'Pérdida mensual estimada por merma', { size: 14, bold: true, color: C.MUTED });
  s.chart(0.9, 2.3, 6.0, 4.35, { series: [{ name: 'Merma 2 %', labels: [''], values: [20000] }, { name: 'Merma 1,5 %', labels: [''], values: [15000] }], colors: [C.R2, C.G2], max: 24000 });
  const x = 7.55;
  const L = [['Ventas mensuales', '$1.000.000', C.INK], ['Merma 2 %', '$20.000 / mes', C.R2], ['Merma 1,5 %', '$15.000 / mes', C.G3]];
  L.forEach((l, i) => { const y = 1.8 + i * 0.72; s.text(x, y, 2.6, 0.6, l[0], { size: 14, color: C.MUTED, valign: 'middle' }); s.text(x + 2.4, y, 2.78, 0.6, l[1], { size: 20, bold: true, color: l[2], align: 'right', valign: 'middle' }); });
  s.rect(x, 4.1, 5.18, 1.55, { fill: C.G3, r: 0.12, shadow: true });
  s.text(x + 0.3, 4.18, 4.6, 0.4, 'Diferencia', { size: 14, color: 'D7F5DF' });
  s.text(x + 0.3, 4.55, 4.6, 0.7, '$5.000 / mes', { size: 34, bold: true, color: C.WHITE });
  s.text(x + 0.3, 5.22, 4.6, 0.35, 'equivale a $60.000 al año', { size: 13, color: 'D7F5DF' });
  s.rect(x, 5.9, 5.18, 0.9, { fill: C.YT, r: 0.1 });
  s.text(x + 0.2, 5.9, 4.8, 0.9, 'Ejemplo ilustrativo. No representa resultados reales ni garantiza ahorros.', { size: 12.5, bold: true, color: C.Y3, valign: 'middle' });
}

// ============ 25. IMPLEMENTACIÓN ============
{
  const s = content('roadmap', 'Implementación', 'Implementación', 'Ocho etapas, de un diagnóstico a toda la red',
    'QUÉ DECIR: "Empezamos por un diagnóstico de su operación, configuramos la plataforma con sus datos, capacitamos y medimos en un piloto antes de expandir." QUÉ MOSTRAR: la plataforma ya está construida; las etapas son el plan propuesto para cada cliente. BENEFICIO: riesgo acotado y decisión con resultados medidos. ACLARAR: no hay implementaciones previas en clientes que mostrar; el piloto es la primera medición.');
  const R = ['Diagnóstico operativo', 'Definición del alcance', 'Configuración de la plataforma', 'Carga e integración de información', 'Capacitación de usuarios', 'Prueba piloto', 'Medición de resultados', 'Expansión a otras sucursales'];
  const IC = ['faStethoscope', 'faBullseye', 'faGears', 'faDatabase', 'faGraduationCap', 'faFlask', 'faChartLine', 'faStore'];
  s.line(0.95, 2.67, 12.4, 2.67, { color: C.G2, lw: 3 });
  R.forEach((r, i) => {
    const x = 0.6 + i * 1.53;
    s.ellipse(x + 0.35, 2.3, 0.74, 0.74, { fill: i === 5 ? C.G3 : C.WHITE, line: C.G3, lineW: 2, shadow: true });
    s.icon(x + 0.54, 2.49, 0.36, IC[i], i === 5 ? C.WHITE : C.G3);
    s.text(x - 0.05, 3.27, 1.55, 0.36, String(i + 1).padStart(2, '0'), { size: 18, bold: true, color: C.G3, align: 'center' });
    s.text(x - 0.05, 3.64, 1.55, 0.95, r, { size: 13, bold: true, align: 'center' });
  });
  s.rect(0.6, 5.0, 5.9, 1.3, { fill: C.GT, r: 0.1 });
  s.icon(0.85, 5.4, 0.5, 'faCircleCheck', C.G3);
  s.text(1.55, 5.1, 4.8, 1.1, [{ text: 'Plataforma: disponible hoy', bold: true, color: C.GD }, { text: 'Módulos construidos y probados con datos de demostración.', color: C.INK, size: 12.5 }], { size: 14, valign: 'middle' });
  s.rect(6.83, 5.0, 5.9, 1.3, { fill: C.YT, r: 0.1 });
  s.icon(7.08, 5.4, 0.5, 'faRoute', C.Y3);
  s.text(7.78, 5.1, 4.8, 1.1, [{ text: 'Etapas 1–8: plan propuesto', bold: true, color: C.Y3 }, { text: 'Se ajustan al alcance acordado con cada empresa.', color: C.INK, size: 12.5 }], { size: 14, valign: 'middle' });
}

// ============ 26. PILOTO ============
{
  const s = content('pilot', 'Implementación', 'Prueba piloto', 'Un piloto de 30 días para medir con sus datos',
    'QUÉ DECIR: "Proponemos 30 días en una o dos tiendas, con línea base al inicio y medición al final en seis indicadores." QUÉ MOSTRAR: las cuatro semanas y los seis indicadores. BENEFICIO: decisión con evidencia propia. ACLARAR: condiciones, alcance y costo del piloto se acuerdan por escrito; no hay testimonios de clientes que citar todavía.');
  const Wk = [['Semana 1', 'Línea base y configuración'], ['Semana 2', 'Capacitación y primeros conteos'], ['Semana 3', 'Operación con alertas y tareas'], ['Semana 4', 'Medición y cierre']];
  Wk.forEach((w, i) => {
    const x = 0.6 + i * 3.08;
    s.rect(x, 1.75, 2.9, 1.25, { fill: i === 3 ? C.G3 : C.WHITE, line: C.LINE, r: 0.12, shadow: true });
    s.text(x + 0.22, 1.85, 2.5, 0.38, w[0], { size: 15, bold: true, color: i === 3 ? C.WHITE : C.G3 });
    s.text(x + 0.22, 2.25, 2.5, 0.65, w[1], { size: 13, color: i === 3 ? C.WHITE : C.INK });
    if (i < 3) s.line(x + 2.92, 2.37, x + 3.06, 2.37, { color: C.G3, lw: 2, arrow: true });
  });
  s.text(0.6, 3.3, 8, 0.36, 'Indicadores que podrían evaluarse', { size: 15, bold: true, color: C.MUTED });
  const K = [['faBullseye', 'Exactitud de inventario'], ['faScaleUnbalanced', 'Diferencias detectadas'], ['faStopwatch', 'Tiempo de investigación'], ['faListCheck', 'Cumplimiento de tareas'], ['faCalendarXmark', 'Vencimientos identificados'], ['faMobileScreen', 'Uso de la plataforma']];
  K.forEach((k, i) => {
    const x = 0.6 + (i % 3) * 4.12, y = 3.8 + Math.floor(i / 3) * 1.05;
    s.rect(x, y, 3.89, 0.86, { fill: C.WHITE, line: C.LINE, r: 0.1 });
    iconCircle(s, x + 0.16, y + 0.13, 0.6, k[0], C.G3, C.GT);
    s.text(x + 0.92, y, 2.9, 0.86, k[1], { size: 14.5, bold: true, valign: 'middle' });
  });
  s.text(0.6, 6.05, 12.1, 0.7, 'Piloto comercial sujeto a acuerdo: alcance, tiendas y condiciones se definen en el diagnóstico.', { size: 12.5, color: C.MUTED, valign: 'middle' });
}

// ============ 27. PROPUESTA COMERCIAL ============
{
  const s = content('offer', 'Propuesta', 'Propuesta comercial', 'Cuatro formas de trabajar juntos',
    'QUÉ DECIR: presentar las cuatro modalidades y preguntar cuál se ajusta a su momento. QUÉ MOSTRAR: las tarjetas. BENEFICIO: flexibilidad según tamaño y madurez. ACLARAR: no hay precios definitivos en esta presentación; se cotiza tras el diagnóstico según sucursales, usuarios y módulos.');
  const O = [['faCloud', 'Licenciamiento SaaS', 'Suscripción por sucursal y módulos.'], ['faFlask', 'Prueba piloto empresarial', '30 días en una o dos tiendas, con medición.'], ['faStore', 'Implementación multisucursal', 'Despliegue por etapas en toda la red.'], ['faHandshake', 'Colaboración estratégica', 'Desarrollo conjunto de módulos o mercados.']];
  O.forEach((o, i) => {
    const x = 0.6 + i * 3.08, dark = i === 1;
    s.rect(x, 1.75, 2.9, 4.2, { fill: dark ? C.G3 : C.WHITE, line: C.LINE, r: 0.14, shadow: true });
    iconCircle(s, x + 0.3, 2.05, 0.9, o[0], dark ? C.G3 : C.G3, dark ? C.WHITE : C.GT);
    s.text(x + 0.3, 3.2, 2.35, 0.85, o[1], { size: 17, bold: true, color: dark ? C.WHITE : C.INK });
    s.text(x + 0.3, 4.1, 2.35, 1.2, o[2], { size: 13.5, color: dark ? 'E6F7EA' : C.MUTED });
    if (dark) s.pill(x + 0.3, 5.35, 2.3, 'Recomendado para empezar', { fill: C.WHITE, color: C.G3, size: 11, h: 0.32 });
  });
  s.rect(0.6, 6.2, 12.13, 0.6, { fill: C.WHITE, line: C.LINE, r: 0.1 });
  s.text(0.85, 6.2, 11.6, 0.6, 'Precios según alcance: se cotizan después del diagnóstico.', { size: 13, color: C.MUTED, valign: 'middle' });
}

// ============ 28. CIERRE ============
{
  const s = new Slide({ cover: true, section: 'Cierre' });
  s.o.bg = '__COVER__';
  s.add({ t: 'img', abs: 'C:/Users/renzo/AppData/Local/Temp/claude/C--Users-renzo-grocy-grocy/c5cc64c9-4f97-4983-8b96-01417d0bc165/scratchpad/build/cache/logo_badge.png', x: 0.72, y: 0.8, w: 0.72, h: 0.72, alt: 'Logo DAILY' });
  s.text(1.5, 0.85, 3, 0.62, 'DAILY', { size: 30, bold: true, color: C.WHITE, valign: 'middle', spacing: 2 });
  s.text(0.75, 1.95, 6.0, 2.2, 'De los datos a las decisiones. De las decisiones a los resultados.', { size: 34, bold: true, color: C.WHITE, lh: 0.95 });
  s.rect(0.75, 4.55, 5.4, 1.1, { fill: C.G2, r: 0.14, shadow: true });
  s.text(0.95, 4.55, 5.0, 1.1, 'Solicita una demostración o inicia una prueba piloto.', { size: 17, bold: true, color: '06301B', valign: 'middle' });
  s.text(0.75, 5.95, 5.6, 0.4, 'DAILY | Presentación comercial confidencial', { size: 11, color: 'B9F3C9' });
  s.shot(7.0, 0.75, 2.75, 1.66, '02_mermas/waste_tab_causales', [245, 100, 1665]);
  s.shot(9.98, 0.75, 2.75, 1.66, '05_ventas/sales', MAIN);
  s.shot(7.0, 2.64, 2.75, 1.66, '04_calidad/quality_tab_predictive', [245, 100, 1665]);
  s.shot(9.98, 2.64, 2.75, 1.66, '06_personal/attendance', [245, 60, 1665]);
  s.shot(7.0, 4.53, 2.75, 1.66, '03_inventario/locations_map', [245, 300, 1665]);
  s.shot(9.98, 4.53, 2.75, 1.66, '01_ejecutivo/today', [245, 60, 1665]);
  s.notes = 'QUÉ DECIR: "DAILY lleva los datos de la operación a decisiones diarias, y esas decisiones a resultados que se pueden medir." LLAMADO A LA ACCIÓN: proponer fecha para el diagnóstico o para una demostración con datos del cliente. PREGUNTA DE CIERRE: "¿Qué tienda sería la mejor para un piloto de 30 días?"';
  S.closing = s;
}

const FULL = ['cover', 'problems', 'flow', 'exec', 'wasteHub', 'step1', 'step2', 'step3', 'step4', 'stock', 'invflow', 'scan', 'quality', 'sales', 'staff', 'staff2', 'branches', 'alerts', 'sst', 'mobile', 'day', 'beforeafter', 'benefits', 'impact', 'roadmap', 'pilot', 'offer', 'closing'];
const EXEC = ['cover', 'exec', 'step1', 'impact', 'pilot'];

async function coverBg(file) {
  const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="1920" height="1080"><defs>
<radialGradient id="g" cx="78%" cy="30%" r="85%"><stop offset="0" stop-color="#0E8A45"/><stop offset="0.55" stop-color="#075A2E"/><stop offset="1" stop-color="#042614"/></radialGradient>
<radialGradient id="h" cx="8%" cy="95%" r="45%"><stop offset="0" stop-color="#39F500" stop-opacity="0.22"/><stop offset="1" stop-color="#39F500" stop-opacity="0"/></radialGradient></defs>
<rect width="1920" height="1080" fill="url(#g)"/><rect width="1920" height="1080" fill="url(#h)"/></svg>`;
  await sharp(Buffer.from(svg)).png().toFile(file);
}

module.exports = { S, FULL, EXEC, coverBg };
