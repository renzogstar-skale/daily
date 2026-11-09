// Motor: un solo spec de diapositivas -> PPTX (pptxgenjs) + HTML (para PDF y QA)
const fs = require('fs'), path = require('path'), crypto = require('crypto');
const sharp = require('sharp');
sharp.cache(false);
const SC = 2; // las capturas de escritorio están a 2x; los recortes se expresan en px CSS
const fa = require('@fortawesome/free-solid-svg-icons');

const W = 13.333, H = 7.5;
const C = {
  G1: '39F500', G2: '00C83B', G3: '008E48', GD: '0B5D35', GT: 'E8F8EC',
  R1: 'FF3B30', R2: 'DC2626', R3: '7F1D1D', RT: 'FDECEC',
  Y1: 'FFE600', Y2: 'FFB800', Y3: 'B66A00', YT: 'FFF6DB',
  INK: '0F172A', MUTED: '566070', LINE: 'E2E8E4', BG: 'F5F7F6', WHITE: 'FFFFFF'
};
const FONT = 'Calibri';

class Slide {
  constructor(opts) { this.o = opts || {}; this.el = []; this.notes = ''; }
  add(e) { this.el.push(e); return e; }
  rect(x, y, w, h, o = {}) { return this.add({ t: 'rect', x, y, w, h, ...o }); }
  ellipse(x, y, w, h, o = {}) { return this.add({ t: 'ellipse', x, y, w, h, ...o }); }
  text(x, y, w, h, text, o = {}) { return this.add({ t: 'text', x, y, w, h, text, ...o }); }
  line(x1, y1, x2, y2, o = {}) { return this.add({ t: 'line', x1, y1, x2, y2, ...o }); }
  icon(x, y, s, name, color, o = {}) { return this.add({ t: 'icon', x, y, w: s, h: s, name, color, ...o }); }
  chart(x, y, w, h, o) { return this.add({ t: 'chart', x, y, w, h, ...o }); }
  // Imagen recortada: crop=[cx,cy,cw] en px de origen; alto del recorte sale de la proporción de la caja
  img(x, y, w, h, src, crop, o = {}) {
    const e = { t: 'img', x, y, w, h, src, crop, ...o };
    if (crop) { e.cx = crop[0]; e.cy = crop[1]; e.cw = crop[2]; e.ch = Math.round(crop[2] * h / w); }
    return this.add(e);
  }
  // Captura con tarjeta y sombra
  shot(x, y, w, h, src, crop, o = {}) {
    this.rect(x - 0.04, y - 0.04, w + 0.08, h + 0.08, { fill: C.WHITE, line: C.LINE, r: 0.06, shadow: true });
    return this.img(x, y, w, h, src, crop, o);
  }
  phone(x, y, w, src) {
    const h = (w - 0.14) / 0.462 + 0.14;
    this.rect(x, y, w, h, { fill: '111827', r: 0.22, shadow: true });
    this.img(x + 0.07, y + 0.07, w - 0.14, h - 0.14, src, null);
    return h;
  }
  P(e, px, py) { const s = e.w / e.cw; return [e.x + (px - e.cx) * s, e.y + (py - e.cy) * s]; }
  mask(e, px1, py1, px2, py2, color = C.WHITE) {
    const [a, b] = this.P(e, px1, py1), [c, d] = this.P(e, px2, py2);
    this.rect(a, b, c - a, d - b, { fill: color });
  }
  badge(x, y, n, color = C.R2, d = 0.4) {
    this.ellipse(x - d / 2, y - d / 2, d, d, { fill: color, line: C.WHITE, lineW: 2, shadow: true });
    this.text(x - d / 2, y - d / 2, d, d, String(n), { size: 14, bold: true, color: C.WHITE, align: 'center', valign: 'middle' });
  }
  pin(e, px, py, n, color) { const [x, y] = this.P(e, px, py); this.badge(x, y, n, color); }
  ring(e, px1, py1, px2, py2, color = C.R2) {
    const [a, b] = this.P(e, px1, py1), [c, d] = this.P(e, px2, py2);
    this.rect(a, b, c - a, d - b, { line: color, lineW: 2.5, r: 0.06 });
  }
  pill(x, y, w, text, o = {}) {
    const h = o.h || 0.32;
    this.rect(x, y, w, h, { fill: o.fill || C.GT, r: h / 2 });
    this.text(x, y, w, h, text, { size: o.size || 11, bold: true, color: o.color || C.GD, align: 'center', valign: 'middle' });
  }
  demoTag(x, y, text = 'Pantalla real · datos de demostración') {
    this.pill(x, y, 3.05, text, { fill: 'FFFFFF', color: C.MUTED, size: 10, h: 0.3 });
  }
  // Etiqueta de estado de la función (arriba a la derecha)
  status(kind) {
    const K = { real: ['Función disponible', C.GT, C.GD, 2.0], demo: ['Escenario ilustrativo', C.YT, C.Y3, 2.2], plan: ['Proyectado · no disponible hoy', C.RT, C.R3, 3.1], mix: ['Disponible + proyectado', C.YT, C.Y3, 2.4], prop: ['Propuesta comercial', C.GT, C.GD, 2.1] }[kind];
    this.pill(12.73 - K[3], 0.32, K[3], K[0], { fill: K[1], color: K[2], size: 10.5, h: 0.3 });
  }
}

// ---------- recursos (recortes, íconos, logos) ----------
async function prepare(slides, capsDir, cacheDir) {
  fs.mkdirSync(cacheDir, { recursive: true });
  for (const s of slides) for (const e of s.el) {
    if (e.t === 'img' && e.abs) { e.file = e.abs; continue; }
    if (e.t === 'img') {
      const src = path.join(capsDir, e.src + (e.src.endsWith('.png') ? '' : '.png'));
      const key = crypto.createHash('md5').update(src + fs.statSync(src).mtimeMs + JSON.stringify(e.crop) + e.w + e.h).digest('hex').slice(0, 12);
      const out = path.join(cacheDir, key + '.jpg');
      if (!fs.existsSync(out)) {
        let im = sharp(src, { limitInputPixels: false });
        if (e.crop) {
          const m = await sharp(src, { limitInputPixels: false }).metadata();
          // si el recorte no cabe en alto, se reduce el ancho para no deformar
          let ch = e.ch * SC, cw = e.cw * SC; const cx = e.cx * SC, cy = e.cy * SC;
          if (cy + ch > m.height) { ch = m.height - cy; cw = Math.round(ch * e.w / e.h); console.warn('Recorte ajustado', e.src, e.cw, '->', cw / SC); }
          im = im.extract({ left: cx, top: cy, width: Math.min(cw, m.width - cx), height: ch });
        }
        await im.resize({ width: Math.round(Math.min(e.w * 220, e.cw ? e.cw * SC : 1170)) }).flatten({ background: '#ffffff' }).jpeg({ quality: 88 }).toFile(out);
      }
      e.file = out;
    }
    if (e.t === 'icon') {
      const def = fa[e.name]; if (!def) throw new Error('Icono ' + e.name);
      const [w, h, , , d] = def.icon;
      const svg = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ${w} ${h}" width="${w}" height="${h}"><path fill="#${e.color}" d="${Array.isArray(d) ? d.join(' ') : d}"/></svg>`;
      const out = path.join(cacheDir, 'ic_' + e.name + '_' + e.color + '.png');
      if (!fs.existsSync(out)) await sharp(Buffer.from(svg)).resize({ width: 256, height: 256, fit: 'contain', background: { r: 0, g: 0, b: 0, alpha: 0 } }).png().toFile(out);
      e.file = out;
    }
    if (e.t === 'raw') e.file = e.file;
  }
}

// ---------- PPTX ----------
function textRuns(text, o) {
  const paras = Array.isArray(text) ? text : String(text).split('\n').map(t => ({ text: t }));
  return paras.map((p, i) => {
    const r = typeof p === 'string' ? { text: p } : p;
    const opt = { bold: r.bold ?? o.bold, color: r.color || o.color || C.INK, fontSize: r.size || o.size || 14, italic: r.italic };
    if (r.bullet) { opt.bullet = { indent: 14 }; }
    if (i < paras.length - 1 && !r.inline) opt.breakLine = true;
    if (o.paraAfter) opt.paraSpaceAfter = o.paraAfter;
    return { text: r.text, options: opt };
  });
}
function shadowObj() { return { type: 'outer', color: '000000', opacity: 0.14, blur: 8, offset: 2, angle: 90 }; }

async function toPptx(slides, file, meta) {
  const pptxgen = require('pptxgenjs');
  const pres = new pptxgen();
  pres.layout = 'LAYOUT_WIDE';
  pres.title = meta.title; pres.author = 'DAILY'; pres.company = 'DAILY';
  pres.theme = { headFontFace: FONT, bodyFontFace: FONT };
  pres.defineSlideMaster({
    title: 'CONTENIDO', background: { color: C.BG },
    objects: [
      { text: { text: 'DAILY | Presentación comercial confidencial', options: { x: 0.6, y: 7.08, w: 6, h: 0.25, fontFace: FONT, fontSize: 10, color: '8A93A0', margin: 0 } } },
      { placeholder: { options: { name: 'title', type: 'title', x: 0.6, y: 0.62, w: 12.1, h: 0.66, fontFace: FONT, fontSize: 28, bold: true, color: C.INK, margin: 0, valign: 'top', align: 'left' }, text: '' } }
    ],
    slideNumber: { x: 12.23, y: 7.08, w: 0.5, h: 0.25, fontFace: FONT, fontSize: 10, color: '8A93A0', align: 'right' }
  });
  pres.defineSlideMaster({ title: 'PORTADA', background: { color: '06301B' }, objects: [] });
  let section = null;
  for (const s of slides) {
    if (s.o.section && s.o.section !== section) { section = s.o.section; pres.addSection({ title: section }); }
    const sl = pres.addSlide({ masterName: s.o.cover ? 'PORTADA' : 'CONTENIDO', sectionTitle: section || undefined });
    if (s.o.bg) sl.background = { path: s.o.bg };
    if (s.o.title && !s.o.cover) sl.addText(s.o.title, { placeholder: 'title', align: 'left' });
    for (const e of s.el) {
      if (e.t === 'rect' || e.t === 'ellipse') {
        const shape = e.t === 'ellipse' ? pres.ShapeType.ellipse : (e.r ? pres.ShapeType.roundRect : pres.ShapeType.rect);
        const o = { x: e.x, y: e.y, w: e.w, h: e.h, fill: e.fill ? { color: e.fill, transparency: e.transp || 0 } : { type: 'none' }, line: e.line ? { color: e.line, width: e.lineW || 0.75, dashType: e.dash ? 'dash' : 'solid' } : { type: 'none' } };
        if (e.r && e.t === 'rect') o.rectRadius = e.r;
        if (e.shadow) o.shadow = shadowObj();
        sl.addShape(shape, o);
      } else if (e.t === 'text') {
        sl.addText(textRuns(e.text, e), { x: e.x, y: e.y, w: e.w, h: e.h, fontFace: FONT, margin: 0, align: e.align || 'left', valign: e.valign || 'top', isTextBox: true, charSpacing: e.spacing || 0, lineSpacingMultiple: e.lh || 1.0, fit: 'none' });
      } else if (e.t === 'img' || e.t === 'icon') {
        sl.addImage({ path: e.file, x: e.x, y: e.y, w: e.w, h: e.h, altText: e.alt || e.src || e.name });
      } else if (e.t === 'line') {
        const x = Math.min(e.x1, e.x2), y = Math.min(e.y1, e.y2);
        sl.addShape(pres.ShapeType.line, { x, y, w: Math.max(Math.abs(e.x2 - e.x1), 0.001), h: Math.max(Math.abs(e.y2 - e.y1), 0.001), flipH: e.x2 < e.x1, flipV: e.y2 < e.y1, line: { color: e.color || C.MUTED, width: e.lw || 2, endArrowType: e.arrow ? 'triangle' : undefined, dashType: e.dash ? 'dash' : 'solid' } });
      } else if (e.t === 'chart') {
        sl.addChart(pres.ChartType.bar, e.series, {
          x: e.x, y: e.y, w: e.w, h: e.h, barDir: 'col', barGapWidthPct: 60, barOverlapPct: -30, chartColors: e.colors,
          showValue: true, dataLabelPosition: 'outEnd', dataLabelFormatCode: '"$"#"."000', dataLabelFontSize: 16, dataLabelFontBold: true, dataLabelColor: C.INK, dataLabelFontFace: FONT,
          valAxisHidden: true, valGridLine: { style: 'none' }, catGridLine: { style: 'none' }, valAxisMinVal: 0, valAxisMaxVal: e.max,
          catAxisLabelColor: C.MUTED, catAxisLabelFontSize: 13, catAxisLabelFontFace: FONT, catAxisLineShow: true,
          showLegend: true, legendPos: 'b', legendFontSize: 13, legendFontFace: FONT, legendColor: C.INK
        });
      }
    }
    if (s.notes) sl.addNotes(s.notes);
  }
  await pres.writeFile({ fileName: file });
}

// ---------- HTML ----------
const esc = s => String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
function htmlText(e) {
  const paras = Array.isArray(e.text) ? e.text : String(e.text).split('\n').map(t => ({ text: t }));
  let out = '', inl = '';
  paras.forEach((p0, i) => {
    const p = typeof p0 === 'string' ? { text: p0 } : p0;
    const st = `color:#${p.color || e.color || C.INK};font-size:${p.size || e.size || 14}pt;font-weight:${(p.bold ?? e.bold) ? 700 : 400};${p.italic ? 'font-style:italic;' : ''}`;
    const span = `<span style="${st}">${esc(p.text) || '&nbsp;'}</span>`;
    if (p.inline) { inl += span; return; }
    out += `<div style="${p.bullet ? 'padding-left:14pt;text-indent:-10pt;' : ''}margin-bottom:${e.paraAfter || 0}pt">${p.bullet ? '<span style="color:#' + (e.color || C.INK) + '">• </span>' : ''}${inl}${span}</div>`; inl = '';
  });
  return out;
}
function toHtml(slides, meta) {
  const inch = v => (+v).toFixed(4) + 'in';
  let body = '';
  slides.forEach((s, idx) => {
    let h = '', svg = '';
    for (const e of s.el) {
      const box = `left:${inch(e.x)};top:${inch(e.y)};width:${inch(e.w)};height:${inch(e.h)};`;
      if (e.t === 'rect' || e.t === 'ellipse') {
        const br = e.t === 'ellipse' ? '50%' : (e.r ? inch(e.r) : '0');
        h += `<div style="position:absolute;${box}box-sizing:border-box;border-radius:${br};${e.fill ? `background:#${e.fill};${e.transp ? 'opacity:' + (1 - e.transp / 100) + ';' : ''}` : ''}${e.line ? `border:${(e.lineW || 0.75)}pt ${e.dash ? 'dashed' : 'solid'} #${e.line};` : ''}${e.shadow ? 'box-shadow:0 2pt 8pt rgba(0,0,0,.14);' : ''}"></div>`;
      } else if (e.t === 'text') {
        const jc = { top: 'flex-start', middle: 'center', bottom: 'flex-end' }[e.valign || 'top'];
        h += `<div style="position:absolute;${box}display:flex;flex-direction:column;justify-content:${jc};text-align:${e.align || 'left'};line-height:${1.2 * (e.lh || 1)};letter-spacing:${(e.spacing || 0)}pt;overflow:visible">${htmlText(e)}</div>`;
      } else if (e.t === 'img' || e.t === 'icon') {
        h += `<img src="${'file:///' + e.file.replace(/\\/g, '/')}" style="position:absolute;${box}">`;
      } else if (e.t === 'line') {
        svg += `<line x1="${e.x1}" y1="${e.y1}" x2="${e.x2}" y2="${e.y2}" stroke="#${e.color || C.MUTED}" stroke-width="${(e.lw || 2) / 72}" ${e.dash ? 'stroke-dasharray="0.06 0.04"' : ''} ${e.arrow ? `marker-end="url(#a${e.color || C.MUTED})"` : ''}/>`;
      } else if (e.t === 'chart') {
        const n = e.series.length, bw = e.w / (n * 2 + 1), base = e.y + e.h - 0.75;
        e.series.forEach((sr, i) => {
          const v = sr.values[0], bh = (v / e.max) * (e.h - 1.3), x = e.x + bw * (1 + i * 2) - bw * 0.3;
          h += `<div style="position:absolute;left:${inch(x)};top:${inch(base - bh)};width:${inch(bw * 1.6)};height:${inch(bh)};background:#${e.colors[i]}"></div>`;
          h += `<div style="position:absolute;left:${inch(x - 0.3)};top:${inch(base - bh - 0.4)};width:${inch(bw * 1.6 + 0.6)};text-align:center;font-size:16pt;font-weight:700;color:#${C.INK}">$${v.toLocaleString('es-CO')}</div>`;
          h += `<div style="position:absolute;left:${inch(e.x + 0.3 + i * 2.1)};top:${inch(e.y + e.h - 0.35)};font-size:13pt;color:#${C.INK}"><span style="display:inline-block;width:10pt;height:10pt;background:#${e.colors[i]};margin-right:5pt"></span>${esc(sr.name)}</div>`;
        });
        h += `<div style="position:absolute;left:${inch(e.x)};top:${inch(base)};width:${inch(e.w)};border-top:1pt solid #CBD2D9"></div>`;
      }
    }
    const markers = [...new Set(s.el.filter(e => e.t === 'line' && e.arrow).map(e => e.color || C.MUTED))].map(c => `<marker id="a${c}" viewBox="0 0 10 10" refX="8" refY="5" markerWidth="4" markerHeight="4" orient="auto"><path d="M0,0 L10,5 L0,10 z" fill="#${c}"/></marker>`).join('');
    const bgcss = s.o.bg ? `background:url('file:///${s.o.bg.replace(/\\/g, '/')}') center/cover;` : `background:#${s.o.cover ? '06301B' : C.BG};`;
    const chrome = s.o.cover ? '' : `<div style="position:absolute;left:.6in;top:.62in;width:12.1in;height:.66in;font-size:28pt;font-weight:700;color:#${C.INK};line-height:1.2">${esc(s.o.title || '')}</div>
      <div style="position:absolute;left:.6in;top:7.08in;font-size:10pt;color:#8A93A0">DAILY | Presentación comercial confidencial</div>
      <div style="position:absolute;right:.6in;top:7.08in;font-size:10pt;color:#8A93A0">${idx + 1}</div>`;
    body += `<section class="slide" style="${bgcss}">${chrome}<svg viewBox="0 0 ${W} ${H}" style="position:absolute;left:0;top:0;width:${W}in;height:${H}in;z-index:0;overflow:visible"><defs>${markers}</defs>${svg}</svg>${h}</section>\n`;
  });
  return `<!doctype html><html lang="es"><head><meta charset="utf-8"><title>${esc(meta.title)}</title><style>
@page{size:${W}in ${H}in;margin:0}html,body{margin:0;padding:0}body{font-family:${FONT},Arial,sans-serif;-webkit-print-color-adjust:exact;print-color-adjust:exact}
.slide{position:relative;width:${W}in;height:${H}in;overflow:hidden;page-break-after:always;break-after:page}
.slide>svg{pointer-events:none}.slide>svg~*{z-index:1}
</style></head><body>${body}</body></html>`;
}

module.exports = { Slide, C, W, H, prepare, toPptx, toHtml };
