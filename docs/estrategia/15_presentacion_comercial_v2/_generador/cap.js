// Captura real con anonimización en DOM. Uso: node cap.js <outDir> <lista.json> [filtro]
const { chromium } = require('playwright-core');
const fs = require('fs'), path = require('path');
const OUT = process.argv[2]; const list = JSON.parse(fs.readFileSync(process.argv[3])); const only = process.argv[4];
const BASE = 'http://localhost:8082';
const NAMES = JSON.parse(fs.readFileSync(path.join(__dirname, 'names.json'))).concat(['Renzo Giovine', 'rgiovine', 'Renzo G.', 'Renzo']);
function anon(names) {
  const norm = s => s.normalize('NFD').replace(/[̀-ͯ]/g, '');
  const esc = s => s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
  const l = [...new Set(names.concat(names.map(norm)))].sort((a, b) => b.length - a.length);
  const re = new RegExp('(' + l.map(esc).join('|') + ')', 'gi'); const map = {}; let k = 0;
  const brand = [[/Galaxy Pro 5G/g, 'Pro 5G'], [/Samsung/g, 'Marca demo'], [/AKCAL/g, 'DAILY'], [/Grocy/gi, 'DAILY']];
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
  for (const item of list) {
    if (only && !new RegExp(only).test(item.d + '/' + item.f)) continue;
    const ctx = await b.newContext(item.m
      ? { viewport: { width: 390, height: 844 }, deviceScaleFactor: 3, isMobile: true, hasTouch: true }
      : { viewport: { width: 1920, height: 1080 }, deviceScaleFactor: 2 });
    const p = await ctx.newPage();
    try {
      await p.goto(BASE + item.u, { waitUntil: 'load', timeout: 90000 });
      await p.waitForLoadState('networkidle', { timeout: 12000 }).catch(() => {});
      await p.waitForTimeout(item.w || 2800);
      for (const c of [].concat(item.click || [])) {
        if (c.startsWith('type:')) { await p.fill('input[placeholder^="Buscar alerta"]', c.slice(5)); await p.waitForTimeout(1500); continue; }
        await p.click(c, { timeout: 8000 });
        await p.waitForLoadState('networkidle', { timeout: 8000 }).catch(() => {});
        await p.waitForTimeout(2200);
      }
      if (!item.m && !item.vp) { const hh = await p.evaluate(() => document.documentElement.scrollHeight); await p.setViewportSize({ width: 1920, height: Math.min(hh, item.maxh || 3200) }); await p.waitForTimeout(900); }
      await p.evaluate(anon, NAMES);
      await p.waitForTimeout(250);
      if (item.scroll) { await p.evaluate(y => window.scrollTo(0, y), item.scroll); await p.waitForTimeout(700); }
      fs.mkdirSync(path.join(OUT, item.d), { recursive: true });
      const file = path.join(OUT, item.d, item.f + '.png');
      await p.screenshot({ path: file, fullPage: false });
      const text = norm(await p.evaluate(() => document.body.innerText));
      const leaks = nn.filter(n => text.includes(n)).slice(0, 4);
      const noisy = (text.match(/grocy|localhost|akcal|error|sin informacion/g) || []).length;
      const h = await p.evaluate(() => document.documentElement.scrollHeight);
      console.log('OK', item.f, 'h=' + h, leaks.length ? 'LEAK ' + leaks : '', noisy ? 'ruido=' + noisy : '');
      report.push({ f: item.d + '/' + item.f, u: item.u, h, leaks, noisy });
    } catch (e) { console.log('FAIL', item.f, e.message.slice(0, 120)); report.push({ f: item.f, fail: e.message.slice(0, 200) }); }
    await ctx.close();
  }
  fs.writeFileSync(path.join(OUT, '_report_' + (only ? 'parcial' : 'total') + '.json'), JSON.stringify(report, null, 1));
  await b.close();
})();
