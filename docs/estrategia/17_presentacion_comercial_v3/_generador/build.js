const path = require('path'), fs = require('fs');
const { prepare, toPptx, toHtml } = require('./engine');
const { S, FULL, EXEC, coverBg } = require('./deck');
const { chromium } = require('playwright-core');
const CAPS = process.argv[2], OUT = process.argv[3], only = process.argv[4];
const SKILL = 'C:/Users/renzo/.claude/skills/synced/7766e8b2-9f77-4c33-80ba-a2b037f23ad7_4bfe8567-00c2-4c06-87ba-60840dbd8a4e/pptx';
(async () => {
  fs.mkdirSync(OUT, { recursive: true });
  const bg = path.join(__dirname, 'cache', 'cover_bg.png'); fs.mkdirSync(path.dirname(bg), { recursive: true });
  if (!fs.existsSync(bg)) await coverBg(bg);
  for (const k of Object.keys(S)) if (S[k].o.bg === '__COVER__') S[k].o.bg = bg;
  const all = FULL.map(k => S[k]);
  await prepare(all, CAPS, path.join(__dirname, 'cache'));
  const THEME = { name: 'DAILY', headFontFace: 'Calibri', bodyFontFace: 'Calibri', colors: { dk1: '0F172A', lt1: 'FFFFFF', dk2: '0B5D35', lt2: 'F5F7F6', accent1: '00C83B', accent2: '008E48', accent3: 'DC2626', accent4: 'FFB800', accent5: '39F500', accent6: 'B66A00', hlink: '008E48', folHlink: '0B5D35' } };
  const { applyTheme } = require(SKILL + '/scripts/apply_theme.js');
  const b = await chromium.launch({ channel: 'msedge' });
  for (const [name, keys, title] of [['DAILY_presentacion_comercial', FULL, 'DAILY — Presentación comercial v3'], ['DAILY_resumen_ejecutivo_5', EXEC, 'DAILY — Resumen ejecutivo']]) {
    const sl = keys.map(k => S[k]);
    const px = path.join(OUT, name + '.pptx');
    if (!only || only === 'pptx') { await toPptx(sl, px, { title }); await applyTheme(px, THEME); }
    const hf = path.join(__dirname, name + '.html'); fs.writeFileSync(hf, toHtml(sl, { title }));
    const p = await b.newPage({ viewport: { width: 1280, height: 720 }, deviceScaleFactor: 1.5 });
    await p.goto('file:///' + hf.split(path.sep).join('/')); await p.waitForTimeout(1500);
    await p.pdf({ path: path.join(OUT, name + '.pdf'), width: '13.333in', height: '7.5in', printBackground: true });
    const qa = path.join(__dirname, 'qa', name); fs.mkdirSync(qa, { recursive: true });
    const secs = await p.$$('section.slide');
    for (let i = 0; i < secs.length; i++) await secs[i].screenshot({ path: path.join(qa, String(i + 1).padStart(2, '0') + '.png') });
    await p.close();
  }
  await b.close(); console.log('listo');
})().catch(e => { console.error(e); process.exit(1); });
