// Pruebas D/E/G: navegación por clics del menú (rápida y con red lenta). Muestrea cada 50 ms durante la
// espera si el encabezado/sidebar están visibles y si la página está en blanco; guarda capturas intermedias.
// Uso: PW_DIR=... node tests/dairy/nav_clicks.js <carpeta> [es|en] [slow]
const fs = require('fs');
const path = require('path');
const { chromium } = require(process.env.PW_DIR);
const SEQ = ['/sales', '/stockoverview', '/waste', '/kpis', '/today', '/sales', '/kpis'];
(async () => {
	const dir = process.argv[2]; fs.mkdirSync(dir, { recursive: true });
	const lang = process.argv[3] || 'en', slow = process.argv[4] === 'slow';
	const browser = await chromium.launch({ channel: 'msedge' });
	const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 } });
	await ctx.addCookies([{ name: 'daily_lang', value: lang, url: 'http://localhost:8080' }]);
	await ctx.addInitScript(l => { try { localStorage.setItem('daily_lang', l); } catch (e) { } }, lang);
	const page = await ctx.newPage();
	const errors = [];
	page.on('pageerror', e => errors.push(e.message.slice(0, 160)));
	page.on('console', m => { if (m.type() === 'error' && !/favicon/.test(m.text())) errors.push(m.text().slice(0, 160)); });
	page.on('response', r => { if (r.status() >= 400) errors.push(r.status() + ' ' + r.url().slice(0, 140)); });
	if (slow) {
		const cdp = await ctx.newCDPSession(page);
		await cdp.send('Network.enable');
		await cdp.send('Network.emulateNetworkConditions', { offline: false, latency: 400, downloadThroughput: 1.6 * 1024 * 1024 / 8, uploadThroughput: 750 * 1024 / 8 });
	}
	await page.goto('http://localhost:8080/today', { waitUntil: 'load', timeout: 180000 });
	const res = [];
	for (let i = 0; i < SEQ.length; i++) {
		const r = SEQ[i];
		const t0 = Date.now();
		const link = await page.$(`a.dnav-link[href$="${r}"]`);
		if (!link) { res.push({ r, err: 'sin enlace en menú' }); continue; }
		await link.evaluate(a => a.scrollIntoView({ block: 'center' }));
		await link.evaluate(a => a.click());
		const samples = []; let shot = 0;
		while (Date.now() - t0 < 60000) {
			const s = await page.evaluate(() => {
				const vis = el => !!(el && el.getBoundingClientRect().height > 0 && getComputedStyle(el).visibility !== 'hidden' && getComputedStyle(el).opacity !== '0');
				const pc = document.getElementById('page-content');
				return { p: location.pathname, hdr: vis(document.querySelector('nav.navbar.fixed-top')), side: vis(document.getElementById('dairySidenav')), loading: document.documentElement.classList.contains('daily-nav-loading'), blank: !document.body || !pc || pc.innerText.trim().length === 0, ready: document.readyState, lang: document.documentElement.lang };
			}).catch(() => null);
			if (s) samples.push(s);
			if (shot < 2 && Date.now() - t0 > (shot ? 900 : 300)) { await page.screenshot({ path: path.join(dir, `${lang}${slow ? '-slow' : ''}-${i}-${r.replace(/\//g, '')}-${shot}.png`) }).catch(() => { }); shot++; }
			if (s && s.p.endsWith(r) && s.ready === 'complete') break;
			await page.waitForTimeout(50);
		}
		const ok = samples.filter(Boolean);
		res.push({ r, ms: Date.now() - t0, samples: ok.length, layoutAlways: ok.every(s => s.hdr && s.side), blankSamples: ok.filter(s => s.blank).length, skeletonSeen: ok.some(s => s.loading), langKept: ok.length ? ok[ok.length - 1].lang === lang : null });
		console.log(JSON.stringify(res[res.length - 1]));
	}
	fs.writeFileSync(path.join(dir, `result-${lang}${slow ? '-slow' : ''}.json`), JSON.stringify({ res, errors }, null, 1));
	console.log('errors', errors.length, errors.slice(0, 5));
	await browser.close();
})();
