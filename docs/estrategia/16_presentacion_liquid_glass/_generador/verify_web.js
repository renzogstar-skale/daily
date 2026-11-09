// Renderiza cada diapositiva de la versión web con Edge y arma hojas 2×2 para revisión. Uso: node verify_web.js <html> [w h]
const { chromium } = require('playwright-core'); const sharp = require('sharp'); const fs = require('fs'), path = require('path');
const FILE = 'file:///' + process.argv[2].replace(/\\/g, '/'); const W = +(process.argv[3] || 1920), H = +(process.argv[4] || 1080);
const D = path.join(__dirname, 'verify'); fs.mkdirSync(D, { recursive: true });
(async () => {
  const b = await chromium.launch({ channel: 'msedge' });
  const p = await b.newPage({ viewport: { width: W, height: H } });
  const errs = []; p.on('pageerror', e => errs.push(e.message)); p.on('console', m => m.type() === 'error' && errs.push(m.text()));
  p.on('requestfailed', r => errs.push('FAIL ' + r.url().slice(-60)));
  await p.goto(FILE + '#1'); await p.waitForTimeout(1500);
  const n = await p.evaluate(() => document.querySelectorAll('.slide').length);
  const shots = [];
  for (let i = 1; i <= n; i++) {
    await p.keyboard.press(i === 1 ? 'Home' : 'ArrowRight'); await p.waitForTimeout(1700);
    await p.mouse.move(5, 5);
    const f = path.join(D, `s${String(i).padStart(2, '0')}.png`); await p.screenshot({ path: f }); shots.push(f);
    // desbordes: elementos que salen del escenario
    const over = await p.evaluate(() => { const s = document.querySelector('.slide.active'); const out = [];
      s.querySelectorAll('h2,h3,h4,h5,p,.lg,.shot').forEach(e => { if (e.closest('.mosaic')) return; const r = e.getBoundingClientRect(), st = document.getElementById('stage').getBoundingClientRect();
        if (r.width && (r.right > st.right + 1 || r.bottom > st.bottom + 1)) out.push(e.className + ':' + (e.textContent || '').trim().slice(0, 30));
        if (e.scrollHeight > e.clientHeight + 4 && /^H|^P/.test(e.tagName)) out.push('clip ' + e.tagName + ':' + e.textContent.trim().slice(0, 30)); });
      return out.slice(0, 5); });
    if (over.length) console.log('s' + i, 'desborde', JSON.stringify(over));
  }
  // estados interactivos
  await p.keyboard.press('Home'); for (let i = 1; i < 12; i++) await p.keyboard.press('ArrowRight'); await p.waitForTimeout(1200);
  await p.click('#caseSteps button:nth-child(3)'); await p.waitForTimeout(900); await p.screenshot({ path: path.join(D, 'i12.png') }); shots.push(path.join(D, 'i12.png'));
  await p.keyboard.press('ArrowRight'); await p.waitForTimeout(1500);
  await p.click('#s13 .seg button.b'); await p.evaluate(() => document.activeElement.blur()); await p.fill('#rSales', '5000000').catch(() => {}); await p.evaluate(() => { const r = document.getElementById('rSales'); r.value = 5000000; r.oninput(); });
  await p.waitForTimeout(800); await p.screenshot({ path: path.join(D, 'i13.png') }); await p.evaluate(() => document.activeElement.blur()); shots.push(path.join(D, 'i13.png'));
  for (let i = 0; i < 10; i++) await p.keyboard.press('ArrowLeft'); await p.waitForTimeout(1200);
  await p.click('#s3 .node:nth-of-type(7)').catch(e => errs.push(e.message)); await p.waitForTimeout(700); await p.screenshot({ path: path.join(D, 'i03.png') }); shots.push(path.join(D, 'i03.png'));
  await p.keyboard.press('o'); await p.waitForTimeout(600); await p.screenshot({ path: path.join(D, 'i_overview.png') }); shots.push(path.join(D, 'i_overview.png'));
  console.log('glass refracción activa:', await p.evaluate(() => [...document.querySelectorAll('.lg')].filter(e => /url\(/.test(e.style.backdropFilter)).length));
  console.log('errores:', JSON.stringify([...new Set(errs)].slice(0, 10)));
  await b.close();
  for (let k = 0; k < shots.length; k += 4) {
    const tiles = await Promise.all(shots.slice(k, k + 4).map(async (f, j) => ({ input: await sharp(f).resize(960, 540).png().toBuffer(), left: (j % 2) * 970, top: Math.floor(j / 2) * 550 })));
    await sharp({ create: { width: 1930, height: 1090, channels: 3, background: '#333' } }).composite(tiles).png().toFile(path.join(D, `sheet${k / 4 + 1}.png`));
  }
  console.log('hojas', Math.ceil(shots.length / 4));
})();
