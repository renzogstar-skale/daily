// PPTX editable con estética Liquid Glass. Uso: node build_pptx.js <outDir>  (requiere haber corrido build_web.js)
const fs = require('fs'), path = require('path'), sharp = require('sharp');
const PptxGenJS = require('pptxgenjs');
const fa = require('@fortawesome/free-solid-svg-icons');
const C = require('./content');
const OUT = process.argv[2], IMG = path.join(OUT, 'capturas'), TMP = path.join(__dirname, 'pptx_tmp');
fs.mkdirSync(TMP, { recursive: true });
const F = 'Segoe UI';
const K = { G: '16A34A', GD: '0B6B33', GM: '22C55E', GL: 'DCFCE7', Y: 'F2B300', YD: '9A6B00', YL: 'FEF6D8', R: 'E5484D', RD: 'A8262B', RL: 'FDE8E8', INK: '0E1A14', MUT: '5A6A62', FAINT: '8A9890', W: 'FFFFFF' };
const pptx = new PptxGenJS(); pptx.layout = 'LAYOUT_WIDE'; pptx.title = 'DAILY · Presentación comercial'; pptx.company = 'DAILY';
const jobs = []; let ic = 0;

// ---------- recursos ----------
async function bg() {
  const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="1920" height="1080"><defs><filter id="b"><feGaussianBlur stdDeviation="70"/></filter>
  <pattern id="p" width="64" height="64" patternUnits="userSpaceOnUse"><path d="M64 0H0V64" fill="none" stroke="#0E1A14" stroke-opacity=".035"/></pattern>
  <radialGradient id="m" cx="50%" cy="40%" r="60%"><stop offset=".2" stop-color="#fff" stop-opacity="1"/><stop offset="1" stop-color="#fff" stop-opacity="0"/></radialGradient><mask id="k"><rect width="1920" height="1080" fill="url(#m)"/></mask></defs>
  <rect width="1920" height="1080" fill="#FAFBFA"/><rect width="1920" height="1080" fill="url(#p)" mask="url(#k)"/>
  <g filter="url(#b)"><circle cx="220" cy="160" r="330" fill="#22C55E" fill-opacity=".30"/><circle cx="1780" cy="200" r="290" fill="#F2B300" fill-opacity=".24"/>
  <circle cx="1400" cy="1060" r="320" fill="#E5484D" fill-opacity=".14"/><circle cx="800" cy="1050" r="260" fill="#16A34A" fill-opacity=".18"/></g></svg>`;
  await sharp(Buffer.from(svg)).png().toFile(path.join(TMP, 'bg.png'));
}
async function icon(name, color) {
  const f = path.join(TMP, `i_${name}_${color}.png`);
  if (!fs.existsSync(f)) {
    const [w, h, , , p] = fa[name].icon;
    const s = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ${w} ${h}" width="${w}" height="${h}"><path fill="#${color}" d="${[].concat(p).join(' ')}"/></svg>`;
    await sharp(Buffer.from(s)).resize(256, 256, { fit: 'contain', background: { r: 0, g: 0, b: 0, alpha: 0 } }).png().toFile(f);
  }
  return f;
}
// recorte superior-izquierdo al aspecto exacto del marco
async function crop(name, w, h, pos = 'tl') {
  const f = path.join(TMP, `c_${name}_${Math.round(w * 100)}x${Math.round(h * 100)}.jpg`);
  if (!fs.existsSync(f)) {
    const src = path.join(IMG, name + '.jpg'), m = await sharp(src).metadata();
    let cw = m.width, ch = Math.round(m.width * h / w);
    if (ch > m.height) { ch = m.height; cw = Math.round(m.height * w / h); }
    const left = pos === 'tc' ? Math.round((m.width - cw) / 2) : 0;
    await sharp(src).extract({ left, top: 0, width: cw, height: ch }).jpeg({ quality: 86 }).toFile(f);
  }
  return f;
}

// ---------- primitivas ----------
const shadow = { type: 'outer', color: '0E1A14', opacity: 0.12, blur: 18, offset: 6, angle: 90 };
function glass(s, x, y, w, h, o = {}) {
  s.addShape(pptx.ShapeType.roundRect, { x, y, w, h, rectRadius: o.r ?? 0.18,
    fill: { color: o.fill || K.W, transparency: o.t ?? 38 }, line: { color: o.line || K.W, width: o.lw ?? 1.25, transparency: o.lt ?? 0 }, shadow: o.noShadow ? undefined : { ...shadow } });
  if (!o.noRim) s.addShape(pptx.ShapeType.line, { x: x + 0.22, y: y + 0.02, w: w - 0.44, h: 0, line: { color: K.W, width: 1.5, transparency: 10 } });
}
function txt(s, x, y, w, h, t, o = {}) {
  s.addText(t, { x, y, w, h, fontFace: F, fontSize: o.size || 14, color: o.color || K.INK, bold: !!o.bold, italic: !!o.italic, align: o.align || 'left',
    valign: o.valign || 'top', margin: 0, paraSpaceAfter: o.after || 0, lineSpacingMultiple: o.lh || 1.05, charSpacing: o.cs || 0, fit: 'shrink' });
}
function sq(s, x, y, d, name, kind = 'solid', color = K.G) {
  const fill = kind === 'solid' ? { color } : { color: K.W, transparency: 10 };
  s.addShape(pptx.ShapeType.roundRect, { x, y, w: d, h: d, rectRadius: d * 0.28, fill, line: { color: kind === 'solid' ? color : 'E3E8E5', width: 0.75 }, shadow: { ...shadow, opacity: 0.1, blur: 8, offset: 3 } });
  jobs.push(async () => s.addImage({ path: await icon(name, kind === 'solid' ? 'FFFFFF' : color), x: x + d * 0.27, y: y + d * 0.27, w: d * 0.46, h: d * 0.46 }));
}
function shot(s, x, y, w, h, name, o = {}) {
  glass(s, x - 0.09, y - 0.09, w + 0.18, h + 0.18, { r: 0.2, t: 30, noRim: true });
  jobs.push(async () => s.addImage({ path: await crop(name, w, h, o.pos), x, y, w, h, altText: o.alt || 'Pantalla real de DAILY', rounding: false }));
  s.addShape(pptx.ShapeType.roundRect, { x, y, w, h, rectRadius: 0.1, fill: { color: K.W, transparency: 100 }, line: { color: '0E1A14', width: 0.5, transparency: 85 } });
}
function cap(s, x, y, w, a, b) { s.addText([{ text: a, options: { bold: true, color: K.INK } }, { text: b ? '  ·  ' + b : '', options: { color: K.MUT } }], { x, y, w, h: 0.3, fontFace: F, fontSize: 12, margin: 0, fit: 'shrink' }); }
function badge(s, x, y, n, color = K.R) {
  s.addShape(pptx.ShapeType.ellipse, { x, y, w: 0.36, h: 0.36, fill: { color }, line: { color: K.W, width: 1.5 } });
  txt(s, x, y, 0.36, 0.36, String(n), { size: 13, bold: true, color: K.W, align: 'center', valign: 'middle' });
}
function slide(num, kicker, title, foot, notes) {
  slide.n = (slide.n || 0) + 1; const only = process.env.ONLY ? process.env.ONLY.split(',').map(Number) : null;
  const s = (only && !only.includes(slide.n) ? (slide.dummy ||= new PptxGenJS()) : pptx).addSlide(); s.background = { path: path.join(TMP, 'bg.png') };
  // pptxgenjs deduplica rutas repetidas en una diapositiva y deja relaciones rotas: pasar datos base64
  const add = s.addImage.bind(s);
  s.addImage = o => { const { path: p, ...rest } = o; const ext = p.endsWith('.png') ? 'png' : 'jpeg'; return add({ ...rest, data: `image/${ext};base64,` + fs.readFileSync(p).toString('base64') }); };
  if (num) {
    s.addShape(pptx.ShapeType.roundRect, { x: 0.72, y: 0.5, w: 0.3, h: 0.3, rectRadius: 0.08, fill: { color: K.G }, line: { color: K.G } });
    txt(s, 0.72, 0.5, 0.3, 0.3, num, { size: 10, bold: true, color: K.W, align: 'center', valign: 'middle' });
    txt(s, 1.12, 0.5, 8, 0.3, kicker.toUpperCase(), { size: 12, bold: true, color: K.GD, cs: 2, valign: 'middle' });
    txt(s, 0.72, 0.92, 11.9, 0.75, title, { size: 32, bold: true });
  }
  s.addImage({ path: path.join(__dirname, 'logo.png'), x: 0.72, y: 7.02, w: 0.2, h: 0.2 });
  txt(s, 0.98, 7.02, 1.5, 0.2, 'DAILY', { size: 10, bold: true, cs: 2, valign: 'middle' });
  if (foot) txt(s, 4.5, 7.02, 8.1, 0.2, foot, { size: 10, color: K.FAINT, align: 'right', valign: 'middle' });
  if (notes) s.addNotes(notes);
  return s;
}
function flow(s, x, y, w, rows, o = {}) {
  const n = rows.length, gap = 0.22, bw = (w - gap * (n - 1)) / n;
  rows.forEach((r, i) => {
    const xx = x + i * (bw + gap), col = { y: K.Y, r: K.R, g: K.G }[r[3]] || K.G;
    sq(s, xx + 0.1, y, 0.46, r[0], 'solid', col);
    txt(s, xx + 0.1, y + 0.54, bw - 0.1, 0.42, r[1], { size: 12, bold: true, valign: "bottom" });
    txt(s, xx + 0.1, y + 1.0, bw - 0.1, 0.55, r[2], { size: 10, color: K.MUT });
    if (i < n - 1) s.addShape(pptx.ShapeType.line, { x: xx + 0.62, y: y + 0.23, w: bw + gap - 0.62, h: 0, line: { color: K.G, width: 1.5, dashType: 'dash', endArrowType: 'triangle', transparency: 40 } });
  });
}
const ok = 'Pantalla real · datos de demostración';

// ---------- diapositivas ----------
function build() {
  // 1 Portada
  { const s = slide(null, '', '', 'Presentación comercial · noviembre 2026', 'Apertura: DAILY conecta en una sola plataforma lo que hoy vive en planillas y sistemas separados. Las pantallas son reales, con una empresa de demostración.');
    s.addImage({ path: path.join(__dirname, 'logo.png'), x: 0.75, y: 1.0, w: 0.62, h: 0.62 });
    txt(s, 1.52, 1.0, 3, 0.62, 'DAILY', { size: 30, bold: true, cs: 6, valign: 'middle' });
    s.addText([{ text: 'Plataforma ', options: { color: K.INK } }, { text: 'Inteligente', options: { color: K.G } }, { text: ' de Gestión Retail', options: { color: K.INK } }], { x: 0.75, y: 2.0, w: 6.2, h: 2.3, fontFace: F, fontSize: 52, bold: true, margin: 0, valign: 'top', lineSpacingMultiple: 0.92 });
    txt(s, 0.75, 4.45, 5.6, 1.0, 'Ventas, inventario, prevención de pérdidas, compras, calidad y personal, conectados en una sola operación.', { size: 17, color: K.MUT, lh: 1.2 });
    [['Pantallas reales', K.GD], ['Datos de demostración', K.YD], ['Web + móvil', K.INK]].forEach((c, i) => {
      const x = 0.75 + i * 1.95; glass(s, x, 5.75, 1.82, 0.42, { r: 0.21, t: 25, noRim: true });
      txt(s, x, 5.75, 1.82, 0.42, c[0], { size: 11.5, bold: true, color: c[1], align: 'center', valign: 'middle' });
    });
    shot(s, 6.75, 0.85, 5.35, 3.2, 'kpis', { alt: 'Panel Ejecutivo' });
    shot(s, 8.25, 4.35, 3.85, 2.25, 'loss_prevention', { alt: 'Prevención de Pérdidas' });
    phone(s, 11.55, 2.2, 1.45, 'm_home');
  }
  // 2 Problemas
  { const s = slide('01', 'El problema', 'Lo que hoy le cuesta margen a una cadena retail', 'Diagnóstico operativo', 'Recorrer las tarjetas y preguntar cuáles reconoce el cliente. Debajo de cada problema está el módulo de DAILY que lo aborda.');
    const tw = 2.86, th = 2.45; C.problems.forEach((p, i) => {
      const x = 0.72 + (i % 4) * (tw + 0.18), y = 1.85 + Math.floor(i / 4) * (th + 0.2);
      glass(s, x, y, tw, th); sq(s, x + 0.22, y + 0.22, 0.56, p[0], 'light', K.R);
      txt(s, x + 0.22, y + 0.88, tw - 0.44, 0.58, p[1], { size: 15, valign: "bottom", bold: true });
      txt(s, x + 0.22, y + 1.52, tw - 0.44, 0.45, p[2], { size: 11.5, color: K.MUT });
      txt(s, x + 0.22, y + 2.0, tw - 0.44, 0.4, '→ ' + p[3], { size: 11.5, bold: true, color: K.GD });
    });
    const x = 0.72 + 3 * (tw + 0.18), y = 1.85 + th + 0.2;
    glass(s, x, y, tw, th, { fill: K.R, t: 8, line: K.R }); sq(s, x + 0.22, y + 0.22, 0.56, 'faArrowTrendDown', 'light', K.R);
    txt(s, x + 0.22, y + 0.95, tw - 0.44, 1.3, C.problemHot, { size: 18, bold: true, color: K.W });
  }
  // 3 Hub
  { const s = slide('02', 'La solución', 'Un solo sistema conecta todos los departamentos', 'Módulos disponibles en la plataforma', 'Cada paso de la mercancía deja un movimiento en un libro único. Cuando algo no cuadra, DAILY lo convierte en una alerta con responsable.');
    const cx = 6.67, cy = 4.35, rx = 4.75, ry = 2.05, nw = 2.35, nh = 0.95;
    const pos = C.hub.map((n, i) => { const t = -Math.PI / 2 + i * 2 * Math.PI / C.hub.length; return [cx + rx * Math.cos(t), cy + ry * Math.sin(t)]; });
    pos.forEach(([x, y]) => { const x1 = Math.min(cx, x), y1 = Math.min(cy, y); s.addShape(pptx.ShapeType.line, { x: x1, y: y1, w: Math.abs(x - cx) || 0.001, h: Math.abs(y - cy) || 0.001, flipH: (x < cx) !== (y < cy), line: { color: K.G, width: 1.5, dashType: 'dash', transparency: 45 } }); });
    glass(s, cx - 1.55, cy - 0.85, 3.1, 1.7, { fill: K.G, t: 6, line: K.G, r: 0.3 });
    s.addImage({ path: path.join(__dirname, 'logo.png'), x: cx - 0.25, y: cy - 0.72, w: 0.5, h: 0.5 });
    txt(s, cx - 1.5, cy - 0.17, 3.0, 0.4, 'DAILY', { size: 22, bold: true, color: K.W, align: 'center', cs: 4 });
    txt(s, cx - 1.4, cy + 0.25, 2.8, 0.5, 'Libro de movimientos único · alertas con responsable · indicadores compartidos', { size: 9.5, color: K.W, align: 'center' });
    C.hub.forEach((n, i) => { const [x, y] = pos[i]; glass(s, x - nw / 2, y - nh / 2, nw, nh, { t: 18 });
      sq(s, x - nw / 2 + 0.14, y - 0.23, 0.46, n.icon); txt(s, x - nw / 2 + 0.7, y - nh / 2 + 0.08, nw - 0.8, nh - 0.16, [{ text: n.name, options: { bold: true, fontSize: 12, breakLine: true } }, { text: n.items[0], options: { fontSize: 9.5, color: K.MUT } }], { valign: 'middle' }); });
  }
  // 4 Panel
  { const s = slide('03', 'Panel ejecutivo', 'La salud del negocio, en una sola pantalla', ok, 'Un gerente abre esta pantalla y ve si venta, margen, merma y rotación van bien. Los números salen de ventas, compras y movimientos registrados.');
    const kp = [['Ventas', '$128.450', '▲ 5,2 % vs. ayer', K.GD], ['Margen', '28,4 %', '▲ 1,4 pp', K.GD], ['Mermas', '1,92 %', '▲ 0,42 pp', K.R], ['Inventario', '98,1 %', '▼ 0,6 pp · exactitud', K.R], ['Personal', '87 %', '▼ 6,0 pp · cobertura', K.R], ['Calidad', '99,1 %', '▲ 0,9 pp', K.GD]];
    kp.forEach((k, i) => { const x = 0.72 + i * 2.0; glass(s, x, 1.8, 1.85, 1.05);
      txt(s, x + 0.16, 1.88, 1.6, 0.25, k[0], { size: 11, color: K.MUT, bold: true }); txt(s, x + 0.16, 2.12, 1.6, 0.42, k[1], { size: 21, bold: true }); txt(s, x + 0.16, 2.55, 1.6, 0.22, k[2], { size: 9.5, bold: true, color: k[3] }); });
    shot(s, 0.82, 3.1, 7.6, 3.65, 'kpis'); [[0.9, 4.2], [0.9, 4.95], [6.35, 4.95], [3.75, 4.95]].forEach((p, i) => jobs.push(async () => badge(s, p[0], p[1], i + 1)));
    [['KPI con meta y tendencia', 'Ventas, margen, mermas, inventario, personal y calidad.'], ['Estado de sucursales', 'Mapa y semáforo por tienda.'], ['Alertas y acciones urgentes', 'Lo que hay que atender hoy.'], ['Tendencia y departamentos', 'Venta acumulada del día y aporte por departamento.']]
      .forEach((c, i) => { const y = 3.0 + i * 0.95; glass(s, 8.75, y, 3.85, 0.82); badge(s, 8.9, y + 0.23, i + 1); txt(s, 9.4, y + 0.1, 3.1, 0.3, c[0], { size: 13, bold: true }); txt(s, 9.4, y + 0.4, 3.1, 0.36, c[1], { size: 10.5, color: K.MUT }); });
  }
  // 5 Ventas
  { const s = slide('04', 'Ventas y rentabilidad', 'Qué se vende, cuánto deja y qué rinde poco', ok, 'Ventas, costo, margen y comparación contra el período anterior; debajo, sucursales, departamentos y productos.');
    shot(s, 0.82, 1.95, 7.9, 4.75, 'sales');
    C.salesCallouts.forEach((c, i) => { const y = 1.86 + i * 1.22; glass(s, 9.05, y, 3.55, 1.08); badge(s, 9.2, y + 0.18, i + 1, K.G); txt(s, 9.7, y + 0.13, 2.8, 0.3, c[0], { size: 14, bold: true }); txt(s, 9.7, y + 0.45, 2.8, 0.55, c[1], { size: 11, color: K.MUT }); });
  }
  // 6 Inventario
  { const s = slide('05', 'Inventario y mercancías', 'Cada unidad, ubicada, valorizada y conciliada', 'Pantallas reales · datos de demostración', 'Recorrer las ocho herramientas: existencias, mercancías, control analítico, kardex, ubicaciones, traslados, conciliación y sucursales.');
    shot(s, 0.82, 1.95, 6.0, 4.1, 'stockoverview'); cap(s, 0.82, 6.2, 6, 'Stock & Movimientos', 'valor, rotación, cobertura y riesgo');
    const G = [C.inv[1], C.inv[3], C.inv[4], C.inv[6]];
    G.forEach((t, i) => { const x = 7.2 + (i % 2) * 2.75, y = 1.95 + Math.floor(i / 2) * 2.3; shot(s, x, y, 2.55, 1.62, t.img); cap(s, x, y + 1.72, 2.6, t.name); });
    txt(s, 7.2, 6.55, 5.4, 0.3, 'También: Control de Inventario (stock fantasma, ABC-XYZ) · Traslados · Sucursales', { size: 10.5, color: K.MUT });
  }
  // 7 Prevención
  { const s = slide('06', 'Prevención de pérdidas', 'Expedientes, activos y celulares controlados por IMEI', 'IMEI enmascarado en pantalla · el bloqueo es interno y no afecta la red celular', 'Centro de Control: incidentes, investigaciones, activos, accesos, CCTV, rondas y planes. Control de Celulares: cada IMEI con estado, ubicación y custodio.');
    shot(s, 0.82, 1.9, 5.75, 2.45, 'loss_prevention'); cap(s, 0.82, 4.45, 5.8, 'Centro de Control', 'incidentes, expedientes, activos, CCTV, rondas');
    shot(s, 6.85, 1.9, 5.75, 2.45, 'devices'); cap(s, 6.85, 4.45, 5.8, 'Control de Celulares', 'trazabilidad por IMEI, custodia y estado');
    glass(s, 0.72, 4.85, 11.9, 2.05); txt(s, 0.9, 4.93, 6, 0.25, 'CICLO DE VIDA DE UN CELULAR EN DAILY', { size: 10, bold: true, color: K.GD, cs: 2 });
    flow(s, 0.85, 5.22, 11.65, C.imeiFlow.map((r, i) => i === 6 ? [...r, 'r'] : r));
  }
  // 8 Compras
  { const s = slide('07', 'Planeación y compras', 'Comprar lo necesario, a tiempo, y medir al proveedor', ok, 'MRP con stock de seguridad y punto de reorden; la sugerencia se convierte en orden, se aprueba, se envía, se recibe y se evalúa OTIF.');
    glass(s, 0.72, 1.82, 11.9, 1.78); flow(s, 0.85, 1.94, 11.65, C.mrpFlow);
    shot(s, 0.82, 3.75, 5.75, 2.75, 'mrp'); cap(s, 0.82, 6.6, 5.8, 'Planificación MRP', 'reposición sugerida y punto de reorden');
    shot(s, 6.85, 3.75, 5.75, 2.75, 'suppliers'); cap(s, 6.85, 6.6, 5.8, 'Proveedores y compras', 'órdenes, recepción y OTIF');
  }
  // 9 Personal
  { const s = slide('08', 'Operaciones y personal', 'La gente correcta, en el área correcta, a la hora pico', 'Reglas del generador de turnos configurables; validar con el área jurídica de cada empresa', 'Horarios y Cobertura: programación, ventas proyectadas frente a horas y productividad. Asistencia y tareas de piso con responsable.');
    shot(s, 0.82, 1.95, 7.2, 4.75, 'scheduling');
    shot(s, 8.35, 1.95, 4.25, 1.85, 'attendance'); cap(s, 8.35, 3.9, 4.3, 'Asistencia y fichajes', 'entradas, salidas y novedades');
    shot(s, 8.35, 4.45, 4.25, 1.85, 'workforce'); cap(s, 8.35, 6.4, 4.3, 'Tareas y roles', 'asignación en piso');
  }
  // 10 Calidad
  { const s = slide('09', 'Calidad, vencimientos y mermas', 'Actuar antes de que el producto se pierda', ok, 'Lotes próximos a vencer priorizados por FEFO; acción preventiva; si hay merma, se registra con evidencia, se aprueba y se recupera con el proveedor cuando aplica.');
    glass(s, 0.72, 1.82, 11.9, 1.78); flow(s, 0.85, 1.94, 11.65, C.qualityFlow);
    shot(s, 0.82, 3.75, 5.75, 2.75, 'quality'); cap(s, 0.82, 6.6, 5.8, 'Calidad y vencimientos', 'FEFO, cuarentena y cadena de frío');
    shot(s, 6.85, 3.75, 5.75, 2.75, 'waste'); cap(s, 6.85, 6.6, 5.8, 'Control de mermas', 'causas, aprobaciones y recuperación');
  }
  // 11 Móvil
  { const s = slide('10', 'Móvil y escáner', 'La operación, desde el teléfono del equipo', 'Web móvil desde el navegador del teléfono · publicación en tiendas de aplicaciones no incluida', 'El empleado ve prioridades, tareas, escanea productos y consulta inventario desde la web móvil.');
    C.phones.forEach((p, i) => { const x = 0.8 + i * 1.78; phone(s, x, 1.95 + (i % 2) * 0.3, 1.55, p[0]); txt(s, x, 5.45 + (i % 2) * 0.3, 1.55, 0.3, p[1], { size: 12, bold: true, align: 'center' }); });
    glass(s, 8.05, 1.9, 4.57, 4.85); txt(s, 8.3, 2.05, 4, 0.4, 'Escáner de códigos', { size: 18, bold: true });
    C.scanRows.forEach((r, i) => { const y = 2.65 + i * 1.0; sq(s, 8.3, y, 0.5, r[0]); txt(s, 8.95, y - 0.02, 3.5, 0.3, r[1], { size: 13, bold: true }); txt(s, 8.95, y + 0.28, 3.5, 0.6, r[2], { size: 10.5, color: K.MUT }); });
  }
  // 12 Caso
  { const s = slide('11', 'Una operación de principio a fin', 'Se reciben 10, se cuentan 8: así responde DAILY', 'Caso de demostración ejecutado con la lógica real de DAILY sobre datos ficticios', 'Seguir el caso: recepción, conteo, diferencia con impacto al costo, investigación con línea de tiempo y kardex, causa con evidencia, ajuste autorizado por otro supervisor y cierre.');
    C.caso.forEach((c, i) => { const x = 0.72 + i * 2.0, w = 1.86; glass(s, x, 1.85, w, 2.15);
      badge(s, x + 0.14, 1.97, i + 1, K.G); txt(s, x + 0.58, 1.97, w - 0.7, 0.36, c.step, { size: 12, bold: true, valign: 'middle' });
      txt(s, x + 0.14, 2.4, w - 0.28, 0.6, c.big, { size: 30, bold: true, color: c.color.replace('#', '') });
      txt(s, x + 0.14, 3.05, w - 0.28, 0.9, c.title, { size: 10.5, color: K.MUT }); });
    shot(s, 0.82, 4.3, 3.75, 2.4, 'case_producto'); shot(s, 4.9, 4.3, 3.75, 2.4, 'case_cerrado'); shot(s, 8.95, 4.3, 3.65, 2.4, 'case_ficha');
  }
  // 13 Beneficios
  { const s = slide('12', 'Beneficios', 'Del registro disperso al control operativo', 'Ejemplo ilustrativo: no representa resultados reales ni garantiza ahorros', 'No prometemos una cifra: mostramos el cambio de forma de trabajar y una forma simple de estimar lo que vale medio punto de merma. El gráfico es editable para usar las cifras del cliente.');
    txt(s, 0.75, 1.82, 3, 0.3, 'Antes', { size: 14, bold: true, color: K.R }); txt(s, 4.35, 1.82, 3, 0.3, 'Con DAILY', { size: 14, bold: true, color: K.GD });
    C.ba.forEach((r, i) => { const y = 2.2 + i * 0.75; glass(s, 0.72, y, 7.3, 0.64, { t: 30 });
      txt(s, 0.9, y, 1.2, 0.64, r[0], { size: 11.5, bold: true, valign: 'middle' }); txt(s, 2.05, y, 2.15, 0.64, r[1], { size: 10.5, color: K.RD, valign: 'middle' }); txt(s, 4.35, y, 3.55, 0.64, r[2], { size: 11, bold: true, color: K.GD, valign: 'middle' }); });
    glass(s, 8.35, 1.82, 4.27, 4.95); txt(s, 8.55, 1.95, 3.9, 0.4, '¿Cuánto vale reducir la merma?', { size: 14, bold: true });
    txt(s, 8.55, 2.35, 3.9, 0.3, 'Ventas mensuales del ejemplo: $1.000.000', { size: 10.5, color: K.MUT });
    s.addChart(pptx.ChartType.bar, [{ name: 'Pérdida mensual', labels: ['Merma 2,0 %', 'Merma 1,5 %'], values: [20000, 15000] }], { x: 8.5, y: 2.7, w: 4.0, h: 2.6, barDir: 'col', chartColors: [K.R, K.G], valAxisHidden: true, valGridLine: { style: 'none' }, catAxisLabelFontFace: F, catAxisLabelFontSize: 11, showValue: true, dataLabelFormatCode: '$#,##0', dataLabelFontFace: F, dataLabelFontSize: 11, dataLabelFontBold: true, showLegend: false, barGapWidthPct: 60 });
    glass(s, 8.55, 5.45, 3.87, 1.1, { fill: K.GL, t: 20, line: K.G, lt: 60 }); txt(s, 8.7, 5.5, 3.6, 0.3, 'Diferencia mensual estimada', { size: 10.5, bold: true, color: K.GD }); txt(s, 8.7, 5.8, 3.6, 0.6, '$5.000', { size: 28, bold: true, color: K.GD });
  }
  // 14 Arquitectura
  { const s = slide('13', 'Arquitectura', 'Construida para operar, auditar e integrarse', 'Cifras del código fuente actual de DAILY', 'Canales de uso, aplicación PHP con servicios de dominio, base de datos con libro de movimientos verificable y bitácora de auditoría.');
    C.layers.forEach((l, i) => { const y = 1.85 + i * 1.22; glass(s, 0.72, y, 7.75, 1.08);
      txt(s, 0.92, y + 0.17, 1.7, 0.25, l[0].toUpperCase(), { size: 10, bold: true, color: K.GD, cs: 2 }); txt(s, 0.92, y + 0.45, 1.7, 0.5, l[1], { size: 12.5, bold: true });
      txt(s, 2.75, y + 0.1, 5.55, 0.9, l[2].join('   ·   '), { size: 11.5, valign: 'middle' }); });
    C.archStats.forEach((a, i) => { const x = 8.8 + (i % 2) * 1.95, y = 1.85 + Math.floor(i / 2) * 1.22; glass(s, x, y, 1.82, 1.08); txt(s, x + 0.16, y + 0.08, 1.6, 0.55, a[0], { size: 26, bold: true, color: K.GD }); txt(s, x + 0.16, y + 0.65, 1.6, 0.38, a[1], { size: 9.5, color: K.MUT }); });
    glass(s, 8.8, 4.3, 3.82, 2.45); txt(s, 9.0, 4.42, 3.4, 0.3, 'Control y seguridad', { size: 13.5, bold: true });
    txt(s, 9.0, 4.8, 3.45, 1.9, C.sec.map(t => ({ text: t, options: { bullet: { code: '25CF' }, breakLine: true } })), { size: 10.5, after: 4 });
  }
  // 15 Propuesta
  { const s = slide('14', 'Propuesta comercial', 'Empezar en pequeño, medir y expandir', 'Condiciones y valores se definen después del diagnóstico · sin implementaciones con clientes a la fecha', 'Cuatro formas de colaborar; se elige después del diagnóstico. Piloto de 30 días medido con seis indicadores.');
    C.offers.forEach((o, i) => { const x = 0.72 + i * 3.02, star = i === 1; glass(s, x, 1.85, 2.86, 2.15, star ? { fill: K.G, t: 6, line: K.G } : {});
      sq(s, x + 0.2, 2.02, 0.5, o[0], star ? 'light' : 'solid', K.G); txt(s, x + 0.2, 2.62, 2.5, 0.5, o[1], { size: 14.5, bold: true, color: star ? K.W : K.INK }); txt(s, x + 0.2, 3.12, 2.5, 0.8, o[2], { size: 11, color: star ? K.W : K.MUT }); });
    glass(s, 0.72, 4.2, 11.9, 1.25); txt(s, 0.92, 4.28, 6, 0.25, 'IMPLEMENTACIÓN PROPUESTA', { size: 10, bold: true, color: K.GD, cs: 2 });
    C.road.forEach((r, i) => { const x = 0.92 + i * 1.47; badge(s, x, 4.6, i + 1, i === 5 ? K.G : '8FCFA6'); txt(s, x, 5.0, 1.38, 0.42, r, { size: 10, bold: true }); });
    glass(s, 0.72, 5.62, 11.9, 1.2); txt(s, 0.92, 5.68, 1.6, 0.7, '30', { size: 36, bold: true, color: K.GD }); txt(s, 0.92, 6.35, 1.8, 0.3, 'días de piloto', { size: 11, bold: true });
    C.pilotInd.forEach((p, i) => { const x = 2.75 + (i % 3) * 3.25, y = 5.78 + Math.floor(i / 3) * 0.5; sq(s, x, y, 0.36, p[0]); txt(s, x + 0.46, y, 2.7, 0.36, p[1], { size: 11.5, bold: true, valign: 'middle' }); });
  }
  // 16 Cierre
  { const s = slide(null, '', '', 'Plataforma Inteligente de Gestión Retail', 'Llamado a la acción: proponer fecha para el diagnóstico o una demostración guiada con las preguntas del cliente.');
    s.addImage({ path: path.join(__dirname, 'logo.png'), x: 6.27, y: 1.0, w: 0.8, h: 0.8 });
    s.addText([{ text: 'De los datos a las decisiones.', options: { color: K.INK, breakLine: true } }, { text: 'De las decisiones a los resultados.', options: { color: K.G } }], { x: 1.2, y: 2.1, w: 10.9, h: 1.9, fontFace: F, fontSize: 42, bold: true, align: 'center', margin: 0, valign: 'middle' });
    txt(s, 2.2, 4.1, 8.9, 0.7, 'Agendemos un diagnóstico operativo y una demostración guiada con las preguntas de su equipo.', { size: 17, color: K.MUT, align: 'center' });
    glass(s, 3.45, 5.1, 3.1, 0.75, { fill: K.G, t: 4, line: K.G, r: 0.37 }); txt(s, 3.45, 5.1, 3.1, 0.75, 'Solicitar demostración', { size: 15, bold: true, color: K.W, align: 'center', valign: 'middle' });
    glass(s, 6.8, 5.1, 3.1, 0.75, { r: 0.37 }); txt(s, 6.8, 5.1, 3.1, 0.75, 'Iniciar prueba piloto', { size: 15, bold: true, color: K.GD, align: 'center', valign: 'middle' });
  }
}
function phone(s, x, y, w, name) {
  const h = w * 844 / 390;
  s.addShape(pptx.ShapeType.roundRect, { x: x - 0.07, y: y - 0.07, w: w + 0.14, h: h + 0.14, rectRadius: 0.28, fill: { color: '121A16' }, line: { color: '2A332E', width: 1 }, shadow: { ...shadow, opacity: 0.3 } });
  jobs.push(async () => s.addImage({ path: await crop(name, w, h, 'tc'), x, y, w, h, altText: 'Web móvil de DAILY' }));
}

(async () => {
  await bg(); build();
  for (const j of jobs) await j();
  const f = process.env.ONLY ? path.join(__dirname, 'dbg_' + process.env.ONLY.replace(/,/g, '-') + '.pptx') : path.join(OUT, 'DAILY_presentacion_editable.pptx');
  await pptx.writeFile({ fileName: f }); console.log('PPTX', f);
})();
