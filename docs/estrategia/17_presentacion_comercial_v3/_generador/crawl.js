// Rastreo de pantallas: estado, texto, tabs, fugas, capturas. Uso: node crawl.js <outDir> <list.json|->
const { chromium } = require('playwright-core');
const fs = require('fs'), path = require('path');
const OUT = process.argv[2];
const NAMES = Object.keys(JSON.parse(fs.readFileSync(process.env.NAME_MAP))).concat(['Renzo Giovine','rgiovine','Renzo G.']);
const ROUTES = (process.env.ROUTES || '').split(',');
const norm = s => s.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
(async () => {
  const b = await chromium.launch({ channel: 'msedge', headless: true });
  const ctx = await b.newContext({ viewport: { width: 1920, height: 1080 } });
  const res = [];
  const nameN = NAMES.map(norm);
  for (const u of ROUTES) {
    const p = await ctx.newPage(); const errs = [];
    p.on('pageerror', e => errs.push(e.message.slice(0, 90)));
    const t0 = Date.now(); let st = 0;
    try {
      const r = await p.goto('http://localhost:8082' + u, { waitUntil: 'load', timeout: 120000 }); st = r.status();
      await p.waitForLoadState('networkidle', { timeout: 15000 }).catch(() => {});
      await p.waitForTimeout(1500);
      const info = await p.evaluate(() => ({
        url: location.pathname, title: document.title, h: document.documentElement.scrollHeight,
        text: document.body.innerText,
        tabs: [...document.querySelectorAll('.nav-tabs a, .nav-pills a, [data-toggle="tab"], [role="tab"]')].map(a => (a.getAttribute('href') || a.id || '') + '=' + a.innerText.trim().slice(0, 28)).filter(x => x.length > 2).slice(0, 26)
      }));
      const tn = norm(info.text);
      const leaks = nameN.filter(n => tn.includes(n)).slice(0, 5);
      const bad = (info.text.match(/AKCAL|grocy|localhost|Sin información|Claude|Anthropic|\[DEMO\]/gi) || []);
      const counts = {}; bad.forEach(x => counts[x] = (counts[x] || 0) + 1);
      fs.mkdirSync(OUT, { recursive: true });
      const f = u.replace(/\//g, '_').slice(1) || 'root';
      fs.writeFileSync(path.join(OUT, f + '.txt'), info.text);
      await p.screenshot({ path: path.join(OUT, f + '.jpg'), type: 'jpeg', quality: 55 });
      res.push({ u, st, final: info.url, ms: Date.now() - t0, h: info.h, leaks, bad: counts, errs: errs.slice(0, 2), tabs: info.tabs });
    } catch (e) { res.push({ u, st, fail: e.message.slice(0, 120) }); }
    await p.close();
    const last = res[res.length - 1];
    console.log(u, last.st, last.final && last.final !== u ? '-> ' + last.final : '', last.ms, 'h=' + last.h, last.leaks && last.leaks.length ? 'LEAK:' + last.leaks : '', JSON.stringify(last.bad || {}), (last.errs || []).join(' | '), last.fail || '');
  }
  fs.writeFileSync(path.join(OUT, '_res.json'), JSON.stringify(res, null, 1));
  await b.close();
})();
