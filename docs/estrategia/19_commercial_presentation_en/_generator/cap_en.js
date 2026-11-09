// Real capture with the DAILY interface switched to English (daily_lang=en) and DOM anonymization. Usage: node cap_en.js <outDir> <list.json> [filter]
const { chromium } = require('playwright-core');
const fs = require('fs'), path = require('path');
const OUT = process.argv[2]; const list = JSON.parse(fs.readFileSync(process.argv[3])); const only = process.argv[4];
const BASE = process.env.BASE || 'http://localhost:8082';
const NMAP = Object.assign(JSON.parse(fs.readFileSync(process.env.NAME_MAP)), { 'rgiovine': 'udemo', 'Renzo G.': 'Demo U.', 'Renzo': 'Demo User' });
const NAMES = Object.keys(NMAP);
const VOCAB = JSON.parse(fs.readFileSync(path.join(__dirname, 'es_vocab.json')));
const SPANISH = /[¿¡]|\b(de|del|los|las|para|con|sin|por|una|que|hoy|ventas|merma|mermas|sucursal|existencias|pendiente|guardar|cancelar|buscar|cerrar|ver|nuevo|nueva|fecha|cantidad|motivo|responsable|productos?|tienda|turno|conteo|lote|estado|alerta|alertas|y|el|la|en)\b/i;
function anon(fixed) {
  const names = Object.keys(fixed);
  const norm = s => s.normalize('NFD').replace(/[̀-ͯ]/g, '');
  const esc = s => s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
  const l = [...new Set(names.concat(names.map(norm)))].sort((a, b) => b.length - a.length);
  const re = new RegExp('(' + l.map(esc).join('|') + ')', 'gi'); const map = {}; let k = 0;
  const brand = [[/Galaxy Pro 5G/g, 'Pro 5G'], [/Samsung /g, ''], [/Samsung/g, ''], [/AKCAL/g, 'DAILY'], [/Grocy/gi, 'DAILY']];
  const sub = t => {
    let n = t.replace(re, m => { const key = norm(m).toLowerCase(); const hit = Object.keys(fixed).find(f => norm(f).toLowerCase() === key); return hit ? fixed[hit] : 'Employee'; });
    for (const [a, b] of brand) n = n.replace(a, b);
    return n === t ? null : n;
  };
  const w = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT); let x;
  while ((x = w.nextNode())) { const r = sub(x.nodeValue); if (r !== null) x.nodeValue = r; }
  document.querySelectorAll('input,textarea').forEach(i => { const r = i.value && sub(i.value); if (r) i.value = r; });
  const pn = document.querySelector('.dairy-hprofile-name'); if (pn) pn.textContent = 'Demo User';
  const pr = document.querySelector('.dairy-hprofile-role'); if (pr) pr.textContent = 'Store Manager';
  const av = document.querySelector('.dairy-havatar'); if (av) av.textContent = 'UD';
  document.querySelectorAll('li.nav-item.dropdown').forEach(li => { if (/Rol:|Role:/.test(li.textContent)) li.style.visibility = 'hidden'; });
}
// Presentation-only retouch: residual Spanish UI strings the runtime catalog does not cover yet,
// plus DD/MM/YYYY -> MM/DD/YYYY. Every replacement is counted and logged in the report.
const OVERLAY = fs.existsSync(path.join(__dirname, 'overlay.json')) ? JSON.parse(fs.readFileSync(path.join(__dirname, 'overlay.json'))) : { exact: {}, sub: [] };
function retouch(o) {
  const used = {};
  const hit = (k) => { used[k] = (used[k] || 0) + 1; };
  const subs = o.sub.slice().sort((p, q) => q[0].length - p[0].length).map(([a, b]) => [new RegExp(a.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'), 'g'), b, a]);
  const fix = t => {
    const k = t.trim();
    if (o.exact[k] !== undefined) { hit(k); return t.replace(k, o.exact[k]); }
    let n = t;
    for (const [re, b, a] of subs) { const m = n.replace(re, b); if (m !== n) { hit(a); n = m; } }
    for (const [a, b] of o.rx || []) { const m = n.replace(new RegExp(a, 'g'), b); if (m !== n) { hit(a); n = m; } }
    const d = n.replace(/\b(\d{2})\/(\d{2})\/(\d{4})\b/g, (m, dd, mm, yy) => (+dd <= 31 && +mm <= 12) ? `${mm}/${dd}/${yy}` : m);
    if (d !== n) { hit('DD/MM/YYYY'); n = d; }
    const iso = n.replace(/(^|[^\w-])(\d{4})-(\d{2})-(\d{2})(?![\w-])/g, (m, pre, y, mo, da) => `${pre}${mo}/${da}/${y}`);
    if (iso !== n) { hit('YYYY-MM-DD'); n = iso; }
    const rng = n.replace(/(\d{2}\/\d{2}\/\d{4})\s+a\s+(\d{2}\/\d{2}\/\d{4})/g, '$1 to $2');
    if (rng !== n) { hit('date a date'); n = rng; }
    const money = n.replace(/(-?)\b(\d+(?:\.\d{3})*)(?:,(\d+))?\s?(M\s?|k\s?|mil\s)?US\$/g, (m, sg, i, dec, mag) => `${sg}$${(+i.replace(/\./g, '')).toLocaleString('en-US')}${dec ? '.' + dec : ''}${mag ? (mag.trim() === 'mil' ? 'K' : mag.trim()) : ''}`);
    if (money !== n) { hit('1.234,56 US$'); n = money.replace(/(\$[\d,.]+)\s+de\s+(\$)/g, '$1 of $2'); }
    const dm = n.replace(/\b(\d{1,2}) (Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)\.? (\d{4})\b/g, '$2 $1, $3');
    if (dm !== n) { hit('DD Mon. YYYY'); n = dm; }
    const pct = n.replace(/\b(\d+),(\d+)\s?%/g, '$1.$2%');
    if (pct !== n) { hit('12,5 %'); n = pct; }
    return n;
  };
  const w = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT); let x;
  while ((x = w.nextNode())) { if (!x.nodeValue.trim()) continue; const r = fix(x.nodeValue); if (r !== x.nodeValue) x.nodeValue = r; }
  document.querySelectorAll('input[placeholder],textarea[placeholder]').forEach(i => { const r = fix(i.placeholder); if (r !== i.placeholder) i.placeholder = r; });
  document.querySelectorAll('input:not([type=hidden])').forEach(i => { if (i.value) { const r = fix(i.value); if (r !== i.value) i.value = r; } });
  return used;
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
    await ctx.addInitScript(() => { try { localStorage.setItem('daily_lang', 'en'); } catch (e) {} });
    await ctx.addCookies([{ name: 'daily_lang', value: 'en', url: BASE }]);
    const p = await ctx.newPage();
    try {
      await p.goto(BASE + item.u, { waitUntil: 'load', timeout: 90000 });
      await p.waitForLoadState('networkidle', { timeout: 12000 }).catch(() => {});
      await p.waitForTimeout(item.w || 2800);
      for (const c of [].concat(item.click || [])) {
        if (c.startsWith('type:')) { await p.fill('input[placeholder^="Search alert"], input[placeholder^="Buscar alerta"]', c.slice(5)); await p.waitForTimeout(1500); continue; }
        await p.click(c, { timeout: 8000 });
        await p.waitForLoadState('networkidle', { timeout: 8000 }).catch(() => {});
        await p.waitForTimeout(2200);
      }
      if (!item.m && !item.vp) { const hh = await p.evaluate(() => document.documentElement.scrollHeight); await p.setViewportSize({ width: 1920, height: Math.min(hh, item.maxh || 3200) }); await p.waitForTimeout(900); }
      await p.evaluate(anon, NMAP);
      // scoped rules apply only to captures whose "dir/file" starts with the key (e.g. "Settings" means "Adjustments" in shrink screens)
      const sc = Object.entries(OVERLAY.scoped || {}).filter(([pfx]) => (item.d + '/' + item.f).startsWith(pfx)).map(([, m]) => m);
      const retouched = await p.evaluate(retouch, {
        exact: Object.assign({}, OVERLAY.exact, ...sc.map(m => m.exact || {})),
        sub: OVERLAY.sub.concat(...sc.map(m => m.sub || [])),
        rx: [].concat(...sc.map(m => m.rx || []))
      });
      await p.waitForTimeout(250);
      if (item.scroll) { await p.evaluate(y => window.scrollTo(0, y), item.scroll); await p.waitForTimeout(700); }
      fs.mkdirSync(path.join(OUT, item.d), { recursive: true });
      const file = path.join(OUT, item.d, item.f + '.png');
      await p.screenshot({ path: file, fullPage: false });
      const text = norm(await p.evaluate(() => document.body.innerText));
      const leaks = nn.filter(n => text.includes(n)).slice(0, 4);
      const noisy = (text.match(/grocy|localhost|akcal|error|sin informacion/g) || []).length;
      const h = await p.evaluate(() => document.documentElement.scrollHeight);
      const lang = await p.evaluate(() => window.DailyI18n && DailyI18n.lang);
      const missing = await p.evaluate(() => window.DailyI18n ? Object.keys(DailyI18n.missing()) : ['NO_RUNTIME']);
      const lines = await p.evaluate(() => document.body.innerText.split('\n').map(t => t.trim()).filter(Boolean));
      const spanish = [...new Set(lines.filter(t => t !== 'EN' && SPANISH.test(t)))].slice(0, 60);
      const nodes = await p.evaluate(([src, vocab]) => {
        const re = new RegExp(src, 'i'), V = new Set(vocab), out = new Set();
        const isEs = t => re.test(t) || (t.toLowerCase().match(/[a-záéíóúüñ]{3,}/g) || []).some(w => V.has(w));
        const w = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT); let x;
        while ((x = w.nextNode())) {
          const t = x.nodeValue.trim(), el = x.parentElement;
          if (!t || t === 'EN' || !el || el.closest('script,style,option')) continue;
          const r = el.getBoundingClientRect();
          if (r.width && r.height && isEs(t)) out.add(t);
        }
        document.querySelectorAll('input:not([type=hidden]),textarea').forEach(i => { for (const t of [i.placeholder, i.value]) if (t && isEs(t)) out.add(t); });
        return [...out];
      }, [SPANISH.source, VOCAB]);
      console.log('OK', item.f, 'h=' + h, leaks.length ? 'LEAK ' + leaks : '', noisy ? 'ruido=' + noisy : '');
      report.push({ f: item.d + '/' + item.f, u: item.u, h, leaks, noisy, lang, retouched, nodes, missing: missing.filter(t => SPANISH.test(t)).slice(0, 60), spanish });
      if (spanish.length) console.log('  ES?', spanish.length, spanish.slice(0, 6).join(' | '));
    } catch (e) { console.log('FAIL', item.f, e.message.slice(0, 120)); report.push({ f: item.f, fail: e.message.slice(0, 200) }); }
    await ctx.close();
  }
  fs.writeFileSync(path.join(OUT, '_report_' + (process.env.REPORT || (only ? 'parcial' : 'total')) + '.json'), JSON.stringify(report, null, 1));
  await b.close();
})();
