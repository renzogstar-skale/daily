// Capturas reales de DAILY (localhost:8080) con anonimización en DOM. Uso: node cap.js <outDir> [filtro]
const { chromium } = require('playwright-core');
const fs = require('fs'), path = require('path');
const OUT = process.argv[2]; const only = process.argv[3];
const BASE = 'http://localhost:8080';
const NAMES = JSON.parse(fs.readFileSync(path.join(__dirname, 'names.json'))).concat(['Renzo Giovine', 'rgiovine', 'Renzo G.', 'Renzo']);
const LIST = [
  ['today', '/today'], ['kpis', '/kpis'], ['sales', '/sales'], ['sales_exec', '/today/sales-executive'],
  ['stockoverview', '/stockoverview'], ['merchandise', '/merchandise'], ['inventory_control', '/inventory-control'],
  ['kardex', '/kardex'], ['location_map', '/locations/map'], ['transfers', '/transfers'], ['reconciliation', '/reconciliation'],
  ['branches', '/branches'],
  ['loss_prevention', '/loss-prevention'], ['devices', '/loss-prevention/devices'], ['alerts', '/alerts'],
  ['waste', '/waste'], ['waste_report', '/waste/report'], ['loss_exec', '/reports/loss-executive'],
  ['mrp', '/mrp'], ['suppliers', '/suppliers'], ['ai_advisor', '/ai-advisor'],
  ['scheduling', '/operations/scheduling'], ['attendance', '/operations/attendance'], ['workforce', '/workforce'],
  ['quality', '/quality'], ['traceability', '/traceability'], ['recalls', '/recalls'], ['expiry', '/quality-expiry'],
  ['scan', '/scan'], ['process_flow', '/process-flow'], ['audit_log', '/audit-log'],
  ['m_home', '/m#/home', 1], ['m_scan', '/m#/scan', 1], ['m_inv', '/m#/inv', 1], ['m_tasks', '/m#/tasks', 1], ['m_control', '/m#/control', 1], ['m_notifications', '/m#/notifications', 1],
];
function anon(names) {
  const norm = s => s.normalize('NFD').replace(/[̀-ͯ]/g, '');
  const esc = s => s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
  const l = [...new Set(names.map(s => norm(s).toLowerCase()))].sort((a, b) => b.length - a.length);
  const V = { a: '[aáàäâ]', e: '[eéèëê]', i: '[iíìïî]', o: '[oóòöô]', u: '[uúùüû]', n: '[nñ]', c: '[cç]' };
  const pat = s => esc(s).replace(/[aeiounc]/g, ch => V[ch] + '\\p{M}*').replace(/ /g, '\\s+');
  const re = new RegExp('(?<![\\p{L}\\p{N}])(' + l.map(pat).join('|') + ')(?![\\p{L}\\p{N}])', 'giu'); const map = {}; let k = 0;
  const brand = [[/Galaxy Pro 5G/g, 'Pro 5G'], [/Samsung/g, 'Marca demo'], [/AKCAL/g, 'DAILY'], [/Alkosto/gi, 'Retail Demo'], [/Ktronix/gi, 'Retail Demo'], [/Grocy/gi, 'DAILY']];
  const sub = t => {
    let n = t.replace(re, m => { const key = norm(m).toLowerCase(); if (!map[key]) map[key] = 'Colaborador ' + String(++k).padStart(2, '0'); return map[key]; });
    for (const [a, b] of brand) n = n.replace(a, b);
    return n === t ? null : n;
  };
  const w = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT); let x;
  while ((x = w.nextNode())) { const r = sub(x.nodeValue); if (r !== null) x.nodeValue = r; }
  document.querySelectorAll('input,textarea').forEach(i => { const r = i.value && sub(i.value); if (r) i.value = r; });
  const pn = document.querySelector('.dairy-hprofile-name'); if (pn) pn.textContent = 'Usuario Demo';
  const pr = document.querySelector('.dairy-hprofile-role'); if (pr) pr.textContent = 'Gerente de tienda';
  const av = document.querySelector('.dairy-havatar'); if (av) av.textContent = 'UD';
  document.querySelectorAll('li.nav-item.dropdown').forEach(li => { if (/Rol:/.test(li.textContent)) li.style.visibility = 'hidden'; });
}
(async () => {
  const b = await chromium.launch({ channel: 'msedge', headless: true });
  const norm = s => s.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
  const nn = NAMES.map(norm);
  const report = [];
  fs.mkdirSync(OUT, { recursive: true });
  for (const [f, u, m] of LIST) {
    if (only && !new RegExp(only).test(f)) continue;
    const ctx = await b.newContext(m
      ? { viewport: { width: 390, height: 844 }, deviceScaleFactor: 3, isMobile: true, hasTouch: true }
      : { viewport: { width: 1600, height: 1000 }, deviceScaleFactor: 1.5 });
    const p = await ctx.newPage();
    const t0 = Date.now();
    try {
      const r = await p.goto(BASE + u, { waitUntil: 'domcontentloaded', timeout: 120000 });
      await p.waitForLoadState('load', { timeout: 60000 }).catch(() => {});
      await p.waitForLoadState('networkidle', { timeout: 12000 }).catch(() => {});
      await p.waitForTimeout(m ? 4500 : 3000);
      await p.evaluate(anon, NAMES); await p.waitForTimeout(1500); await p.evaluate(anon, NAMES);
      await p.waitForTimeout(300);
      const file = path.join(OUT, f + '.png');
      await p.screenshot({ path: file, fullPage: false });
      const text = norm(await p.evaluate(() => document.body.innerText));
      const leaks = nn.filter(n => new RegExp('(?<![\\p{L}\\p{N}])' + n.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '(?![\\p{L}\\p{N}])', 'u').test(text)).slice(0, 4);
      for (const n of leaks) { const i = text.indexOf(n); console.log('  ctx', n.length, JSON.stringify(text.slice(Math.max(0, i - 50), i)) + '[###]' + JSON.stringify(text.slice(i + n.length, i + n.length + 30))); }
      const noisy = (text.match(/grocy|localhost|akcal|alkosto|ktronix|samsung|exception|error 500|sin informacion/g) || []).length;
      const title = await p.title();
      console.log('OK', f, r && r.status(), leaks.length, ((Date.now() - t0) / 1000).toFixed(0) + 's', title.slice(0, 50), leaks.length ? 'LEAK' : '', noisy ? 'ruido=' + noisy : '');
      report.push({ f, u, status: r && r.status(), leaks: leaks.length, noisy, title, head: text.slice(0, 1500) });
    } catch (e) { console.log('FAIL', f, e.message.slice(0, 120)); report.push({ f, u, fail: e.message.slice(0, 200) }); }
    await ctx.close();
  }
  fs.writeFileSync(path.join(__dirname, '_report' + (only ? '_parcial' : '') + '.json'), JSON.stringify(report, null, 1));
  await b.close();
})();
