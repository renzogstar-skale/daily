// Construye la presentación web: procesa capturas y rellena template.html. Uso: node build_web.js <outDir>
const fs = require('fs'), path = require('path'), sharp = require('sharp');
const fa = require('@fortawesome/free-solid-svg-icons');
const C = require('./content');
const OUT = process.argv[2]; const IMG = path.join(OUT, 'capturas');
const V2 = 'C:/Users/renzo/grocy/grocy/docs/estrategia/15_presentacion_comercial_v2/capturas/';
fs.mkdirSync(IMG, { recursive: true });

const svg = n => { const d = fa[n]; if (!d) throw new Error('icono ' + n); const [w, h, , , p] = d.icon; return `<svg viewBox="0 0 ${w} ${h}" aria-hidden="true"><path d="${[].concat(p).join(' ')}"/></svg>`; };
const used = new Set();
const I = n => { used.add(n); return svg(n); };

// Recortes del caso 10→8 (coordenadas CSS de capturas a 2x): [archivo, x, y, w, aspecto]
const CASE = {
  case_producto: ['03_inventario/conciliacion_producto', 590, 412, 1300],
  case_alerta: ['08_alertas/alerts_caso', 250, 40, 1650],
  case_cerrado: ['03_inventario/conciliacion_cerrado', 590, 450, 1290],
  case_ficha: ['03_inventario/conciliacion_ficha2', 505, 115, 915],
  case_kardex: ['03_inventario/kardex_producto', 260, 40, 1650]
};
async function images() {
  const shots = path.join(__dirname, 'shots');
  for (const f of fs.readdirSync(shots).filter(f => f.endsWith('.png'))) {
    const src = path.join(shots, f), name = f.replace('.png', ''), m = await sharp(src).metadata();
    let img = sharp(src);
    if (name.startsWith('m_')) img = img.resize(600);
    else img = img.extract({ left: 348, top: 86, width: m.width - 348, height: m.height - 86 }).resize(1700);
    await img.jpeg({ quality: 84, mozjpeg: true }).toFile(path.join(IMG, name + '.jpg'));
  }
  for (const [k, [f, x, y, w]] of Object.entries(CASE)) {
    const src = V2 + f + '.png', m = await sharp(src).metadata();
    const h = Math.min(Math.round(w / 1.6), m.height / 2 - y);
    await sharp(src).extract({ left: x * 2, top: y * 2, width: w * 2, height: h * 2 }).resize(1700).jpeg({ quality: 84, mozjpeg: true }).toFile(path.join(IMG, k + '.jpg'));
  }
  fs.copyFileSync(path.join(__dirname, 'logo.png'), path.join(IMG, 'logo.png'));
}

function parts() {
  const P = {};
  const arrow = `<div class="arrow">${I('faChevronRight')}</div>`;
  const flow = (rows, delay = 0) => rows.map((r, i) => `<div class="st"><div class="ico solid ${r[3] || ''}">${I(r[0])}</div><h5>${r[1]}</h5><p>${r[2]}</p></div>`).join(arrow);
  P.problems = C.problems.map((p, i) => `<div class="flip" data-a style="transition-delay:${0.05 * i}s"><div class="in">
      <div class="fa lg"><div class="ico r">${I(p[0])}</div><h4>${p[1]}</h4><p>${p[2]}</p><span class="more">Con DAILY →</span></div>
      <div class="fb lg"><div class="ico solid">${I(p[0])}</div><small>${p[3]}</small><h4>${p[4]}</h4></div></div></div>`).join('')
    + `<div class="flip" data-a style="transition-delay:.35s"><div class="in"><div class="fa lg hot"><div class="ico solid r">${I('faArrowTrendDown')}</div><h4>${C.problemHot}</h4></div><div class="fb lg hot"><div class="ico solid r">${I('faArrowTrendDown')}</div><h4>DAILY lo hace visible, con responsable y causa</h4></div></div></div>`;
  // Hub: 8 nodos en elipse alrededor del núcleo (centro 540,340)
  const cx = 540, cy = 300, rx = 440, ry = 225;
  const pos = C.hub.map((n, i) => { const t = -Math.PI / 2 + i * 2 * Math.PI / C.hub.length; return [cx + rx * Math.cos(t), cy + ry * Math.sin(t)]; });
  P.wires = pos.map(([x, y]) => `<path class="wire" d="M${cx},${cy} Q${(cx + x) / 2 + (y - cy) * 0.15},${(cy + y) / 2 - (x - cx) * 0.15} ${x},${y}"/>`).join('');
  P.nodes = C.hub.map((n, i) => `<button class="node lg" style="left:${pos[i][0] - 85}px;top:${pos[i][1] - 58}px"><div class="ico solid">${I(n.icon)}</div><span>${n.name}</span></button>`).join('');
  C.hub.forEach(n => I(n.icon));
  const kp = [['Ventas', 128450, 0, '$', '', '▲ 5,2 % vs. ayer', 'up', 'M0,30 C30,28 50,20 70,16 S110,6 140,4', '#16A34A'],
    ['Margen', 28.4, 1, '', ' %', '▲ 1,4 pp', 'up', 'M0,26 C30,24 60,18 90,14 S120,8 140,6', '#16A34A'],
    ['Mermas', 1.92, 2, '', ' %', '▲ 0,42 pp', 'dn', 'M0,28 C40,26 70,22 100,16 S130,6 140,2', '#E5484D'],
    ['Inventario', 98.1, 1, '', ' %', '▼ 0,6 pp · exactitud', 'dn', 'M0,8 C40,10 70,14 100,20 S130,24 140,26', '#F2B300'],
    ['Personal', 87, 0, '', ' %', '▼ 6,0 pp · cobertura', 'dn', 'M0,6 C40,8 70,16 100,22 S130,26 140,28', '#F2B300'],
    ['Calidad', 99.1, 1, '', ' %', '▲ 0,9 pp', 'up', 'M0,24 C30,22 60,18 90,12 S120,6 140,4', '#16A34A']];
  P.kpis = kp.map((k, i) => `<div class="kpi lg" data-a style="transition-delay:${0.05 * i}s"><small>${k[0]}</small><div class="v" data-count="${k[1]}" data-dec="${k[2]}" data-pre="${k[3]}" data-suf="${k[4]}">0</div><div class="d ${k[6]}">${k[5]}</div><svg class="sp" viewBox="0 0 140 34" preserveAspectRatio="none"><path d="${k[7]}" fill="none" stroke="${k[8]}" stroke-width="3" stroke-linecap="round"/></svg></div>`).join('');
  P.salesCallouts = C.salesCallouts.map((c, i) => `<div class="co lg green"><span class="n">${i + 1}</span><div><h4>${c[0]}</h4><p>${c[1]}</p></div></div>`).join('');
  C.inv.forEach(t => I(t.icon));
  P.imeiFlow = flow(C.imeiFlow.map((r, i) => i === 6 ? [...r, 'r'] : r));
  P.mrpFlow = flow(C.mrpFlow);
  P.qualityFlow = flow(C.qualityFlow);
  P.phones = C.phones.map((p, i) => `<div data-a style="transition-delay:${0.1 + 0.08 * i}s"><div class="phone"><div class="shot" data-cap="Web móvil · ${p[1]}"><img src="capturas/${p[0]}.jpg" alt="Web móvil: ${p[1]}"></div></div><div class="cap">${p[1]}</div></div>`).join('');
  P.scanRows = C.scanRows.map(r => `<div class="row"><div class="ico solid">${I(r[0])}</div><div><h5>${r[1]}</h5><p>${r[2]}</p></div></div>`).join('');
  P.baRows = C.ba.map(r => `<div class="rowc lg"><b>${r[0]}</b><div class="txt b">${I('faCircleXmark')}<span>${r[1]}</span></div><div class="txt a">${I('faCircleCheck')}<span>${r[2]}</span></div></div>`).join('');
  P.layers = C.layers.map((l, i) => `<div class="layer lg" data-a style="transition-delay:${0.08 * i}s"><div><h5>${l[0]}</h5><h4>${l[1]}</h4></div><div class="items">${l[2].map(x => `<span class="chip">${x}</span>`).join('')}</div></div>`).join('');
  P.archStats = C.archStats.map(s => `<div class="lg"><div class="v" data-count="${s[0]}">${s[0]}</div><small>${s[1]}</small></div>`).join('');
  P.secItems = C.sec.map(s => `<li>${I('faLock')}<span>${s}</span></li>`).join('');
  P.offers = C.offers.map((o, i) => `<div class="offer lg ${i === 1 ? 'star' : ''}" data-a style="transition-delay:${0.06 * i}s"><div class="ico ${i === 1 ? '' : 'solid'}">${I(o[0])}</div><h4>${o[1]}</h4><p>${o[2]}</p></div>`).join('');
  P.road = C.road.map((r, i) => `<li class="${i === 5 ? 'pi' : ''}">${r}</li>`).join('');
  P.pilotInd = C.pilotInd.map(p => `<span class="chip g">${I(p[0])} ${p[1]}</span>`).join('');
  P.mosaic = C.mosaic.map(m => `<img src="capturas/${m}.jpg" alt="">`).join('');
  ['faCircleCheck', 'faLink'].forEach(I);
  return P;
}

(async () => {
  if (!process.argv.includes('--no-img')) await images();
  const P = parts();
  let html = fs.readFileSync(path.join(__dirname, 'template.html'), 'utf8');
  html = html.replace(/\{\{i:(\w+)\}\}/g, (_, n) => I(n));
  for (const [k, v] of Object.entries(P)) html = html.split('{{' + k + '}}').join(v);
  const icons = {}; used.forEach(n => icons[n] = svg(n));
  html = html.replace('{{data}}', JSON.stringify({ icons, hub: C.hub, inv: C.inv, caso: C.caso }));
  const left = html.match(/\{\{\w+(:\w+)?\}\}/g); if (left) throw new Error('Sin reemplazar: ' + left.join(','));
  fs.writeFileSync(path.join(OUT, 'DAILY_presentacion_interactiva.html'), html);
  console.log('HTML', (html.length / 1024).toFixed(0) + ' KB', 'imágenes', fs.readdirSync(IMG).length);
})();
