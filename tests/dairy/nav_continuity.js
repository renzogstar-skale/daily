// Continuidad de navegación: mide en ES y EN cuánto tiempo el cuerpo de la página está oculto/en blanco,
// FCP, carga, recargas al cambiar idioma, errores y si el layout (sidebar/header) se ve.
// Uso: PW_DIR=... node tests/dairy/nav_continuity.js <salida.json> [rutas] [slow]
const fs = require('fs');
const { chromium } = require(process.env.PW_DIR || 'playwright');
const ROUTES = (process.argv[3] && process.argv[3] !== 'default' ? process.argv[3] : '/today,/kpis,/alerts,/sales,/stockoverview,/waste,/loss-prevention,/inventory-control').split(',');
const SLOW = process.argv[4] === 'slow';

async function measure(page) {
	return page.evaluate(() => new Promise(res => {
		const t0 = performance.timeOrigin;
		const done = () => {
			const nav = performance.getEntriesByType('navigation')[0] || {};
			const fcp = (performance.getEntriesByName('first-contentful-paint')[0] || {}).startTime;
			const rev = (performance.getEntriesByName('daily-i18n-ready')[0] || {}).startTime;
			const side = document.querySelector('#sidebarResponsive, .dairy-sidebar, nav.navbar');
			const hdr = document.querySelector('.daily-lang-switch');
			res({ ttfb: Math.round(nav.responseStart || 0), dcl: Math.round(nav.domContentLoadedEventEnd || 0), load: Math.round(nav.loadEventEnd || 0), fcp: fcp && Math.round(fcp), reveal: rev && Math.round(rev), pendingNow: document.documentElement.classList.contains('daily-i18n-pending'), sidebar: !!(side && side.getBoundingClientRect().width), header: !!(hdr && hdr.getBoundingClientRect().width), lang: window.DailyI18n && DailyI18n.lang, htmlBg: getComputedStyle(document.documentElement).backgroundColor });
		};
		if (document.readyState === 'complete') setTimeout(done, 300); else addEventListener('load', () => setTimeout(done, 300));
	}));
}

(async () => {
	const browser = await chromium.launch({ channel: 'msedge' });
	const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 } });
	const page = await ctx.newPage();
	const errors = [];
	page.on('pageerror', e => errors.push(page.url() + ' pageerror: ' + e.message.slice(0, 160)));
	page.on('console', m => { if (m.type() === 'error' && !/favicon/.test(m.text())) errors.push(page.url() + ' ' + m.text().slice(0, 160)); });
	page.on('requestfailed', r => errors.push('requestfailed ' + r.url().slice(0, 120) + ' ' + (r.failure() || {}).errorText));
	if (SLOW) {
		const cdp = await ctx.newCDPSession(page);
		await cdp.send('Network.emulateNetworkConditions', { offline: false, latency: 300, downloadThroughput: 750 * 1024 / 8 * 8, uploadThroughput: 250 * 1024 });
	}
	const out = { routes: {}, langSwitch: {}, errors };
	// Prueba A: ES → EN → ES sin salir del dashboard (detecta recarga con un marcador en window)
	await page.goto('http://localhost:8080/sales', { waitUntil: 'load', timeout: 120000 });
	await page.evaluate(() => { localStorage.setItem('daily_lang', 'es'); document.cookie = 'daily_lang=es;path=/'; });
	await page.reload({ waitUntil: 'load' });
	await page.evaluate(() => { window.__marker = 1; });
	let navs = 0; page.on('framenavigated', f => { if (f === page.mainFrame()) navs++; });
	const t1 = Date.now();
	await page.click('.daily-lang-switch > button'); await page.click('[data-daily-lang="en"]');
	await page.waitForFunction(() => document.documentElement.lang === 'en', null, { timeout: 15000 });
	out.langSwitch.toEnMs = Date.now() - t1;
	const t2 = Date.now();
	await page.click('.daily-lang-switch > button'); await page.click('[data-daily-lang="es"]');
	await page.waitForFunction(() => document.documentElement.lang === 'es', null, { timeout: 15000 });
	out.langSwitch.toEsMs = Date.now() - t2;
	out.langSwitch.reloaded = !(await page.evaluate(() => window.__marker === 1)) || navs > 0;
	// Pruebas B/C/D: recorrido por idioma
	for (const lang of ['en', 'es']) {
		await page.evaluate(l => { localStorage.setItem('daily_lang', l); document.cookie = 'daily_lang=' + l + ';path=/'; }, lang);
		out.routes[lang] = [];
		for (const r of ROUTES) {
			const t = Date.now();
			await page.goto('http://localhost:8080' + r, { waitUntil: 'commit', timeout: 120000 }).catch(e => errors.push('nav ' + r + ' ' + e.message.slice(0, 80)));
			const m = await measure(page).catch(e => ({ err: e.message.slice(0, 80) }));
			m.route = r; m.wallMs = Date.now() - t;
			out.routes[lang].push(m);
			console.log(lang, r, JSON.stringify(m));
		}
	}
	// Prueba F: recarga de ruta interna conserva idioma
	await page.evaluate(() => { localStorage.setItem('daily_lang', 'en'); document.cookie = 'daily_lang=en;path=/'; });
	await page.goto('http://localhost:8080/waste', { waitUntil: 'load', timeout: 120000 });
	await page.reload({ waitUntil: 'load' });
	out.reloadKeepsLang = await page.evaluate(() => DailyI18n.lang === 'en' && document.documentElement.lang === 'en');
	await page.evaluate(() => { localStorage.setItem('daily_lang', 'es'); document.cookie = 'daily_lang=es;path=/'; });
	fs.writeFileSync(process.argv[2], JSON.stringify(out, null, 1));
	console.log('langSwitch', JSON.stringify(out.langSwitch), 'reloadKeepsLang', out.reloadKeepsLang, 'errors', errors.length);
	await browser.close();
})();
