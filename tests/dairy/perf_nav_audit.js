// Auditoría de rendimiento y navegación: recorre por clic TODAS las rutas visibles del menú y mide por ruta
// respuesta al clic, TTFB, FCP, DCL, load, TBT (tareas largas), CLS, muestras en blanco y si header/sidebar
// siguen visibles; además atrás/adelante, cambio de idioma y de tema repetidos, móvil y errores
// (pageerror, console.error, HTTP >= 400, requestfailed).
// Uso: PW_DIR=... node tests/dairy/perf_nav_audit.js <salida.json> [desktop|mobile] [rutas,separadas]
const fs = require('fs');
const { chromium } = require(process.env.PW_DIR);
const BASE = 'http://localhost:8080';
const OUT = process.argv[2] || 'perf_nav_audit.json';
const MODE = process.argv[3] || 'desktop';
const ONLY = process.argv[4] ? process.argv[4].split(',') : null;

const INIT = () => {
	window.__perf = { cls: 0, longs: [], clickAt: 0 };
	// Respuesta visual al clic medida dentro de la página (Playwright no evalúa mientras hay navegación pendiente)
	document.addEventListener('click', () => {
		const t0 = performance.now();
		const check = () => {
			const r = document.documentElement;
			if (r.classList.contains('daily-nav-pending')) {
				requestAnimationFrame(() => console.log('NAVFB ' + Math.round(performance.now() - t0)));
				return true;
			}
		};
		if (!check()) new MutationObserver((m, o) => { if (check()) o.disconnect(); }).observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
	}, true);
	try {
		new PerformanceObserver(l => l.getEntries().forEach(e => { if (!e.hadRecentInput) window.__perf.cls += e.value; })).observe({ type: 'layout-shift', buffered: true });
		new PerformanceObserver(l => l.getEntries().forEach(e => window.__perf.longs.push([Math.round(e.startTime), Math.round(e.duration)]))).observe({ type: 'longtask', buffered: true });
	} catch (e) { }
};

async function snapshot(page) {
	return page.evaluate(() => {
		const nav = performance.getEntriesByType('navigation')[0] || {};
		const fcp = (performance.getEntriesByName('first-contentful-paint')[0] || {}).startTime || 0;
		const p = window.__perf || { cls: 0, longs: [] };
		const tbt = p.longs.reduce((s, l) => s + Math.max(0, l[1] - 50), 0);
		return { ttfb: Math.round(nav.responseStart || 0), fcp: Math.round(fcp), dcl: Math.round(nav.domContentLoadedEventEnd || 0), load: Math.round(nav.loadEventEnd || 0), tbt, longest: p.longs.reduce((m, l) => Math.max(m, l[1]), 0), cls: +p.cls.toFixed(3), domNodes: document.getElementsByTagName('*').length, html: Math.round((nav.transferSize || nav.encodedBodySize || 0) / 1024) };
	});
}

(async () => {
	const browser = await chromium.launch({ channel: 'msedge' });
	const mobile = MODE === 'mobile';
	const ctx = await browser.newContext(mobile ? { viewport: { width: 390, height: 844 }, deviceScaleFactor: 2, isMobile: true, hasTouch: true } : { viewport: { width: 1440, height: 900 } });
	await ctx.addInitScript(INIT);
	// Idioma opcional (DAILY_LANG=en): misma preferencia que el selector (cookie + localStorage)
	if (process.env.DAILY_LANG) {
		await ctx.addCookies([{ name: 'daily_lang', value: process.env.DAILY_LANG, url: BASE }]);
		await ctx.addInitScript(l => { try { localStorage.setItem('daily_lang', l); } catch (e) { } }, process.env.DAILY_LANG);
	}
	const page = await ctx.newPage();
	const errors = [];
	const here = () => new URL(page.url()).pathname;
	page.on('pageerror', e => errors.push({ at: here(), kind: 'pageerror', msg: e.message.slice(0, 200) }));
	let navFb = null;
	page.on('console', m => { if (m.text().startsWith('NAVFB ')) navFb = +m.text().slice(6); });
	page.on('console', m => { if (m.type() === 'error' && !/favicon/.test(m.text())) errors.push({ at: here(), kind: 'console', msg: m.text().slice(0, 200) }); });
	page.on('response', r => { if (r.status() >= 400) errors.push({ at: here(), kind: 'http' + r.status(), msg: r.url().replace(BASE, '').slice(0, 160) }); });
	page.on('requestfailed', r => { const f = (r.failure() || {}).errorText || ''; if (!/ERR_ABORTED/.test(f)) errors.push({ at: here(), kind: 'requestfailed', msg: r.url().replace(BASE, '').slice(0, 140) + ' ' + f }); });

	await page.goto(BASE + '/today', { waitUntil: 'load', timeout: 180000 });
	// Calentar (CatchUp demo corre en la primera visita de la hora)
	await page.reload({ waitUntil: 'load', timeout: 180000 });
	// Rutas visibles del menú (sin anclas puras, sin hijos de datos maestros salvo productos)
	let routes = await page.evaluate(() => [...document.querySelectorAll('#dairySidenav a.dnav-link[href]')].map(a => new URL(a.href)).filter(u => u.origin === location.origin).map(u => u.pathname + u.search));
	routes = [...new Set(routes)].filter(r => !/logout|set-role/.test(r));
	if (ONLY) routes = ONLY;
	const out = { mode: MODE, at: new Date().toISOString(), routes: [], backForward: [], lang: [], theme: [], errors };

	for (const r of routes) {
		const t0 = Date.now();
		const prev = page.url();
		await page.evaluate(() => { window.__old = 1; });
		navFb = null;
		let clicked = false;
		if (!mobile) {
			clicked = await page.evaluate(r => {
				const a = [...document.querySelectorAll('#dairySidenav a.dnav-link[href]')].find(a => { const u = new URL(a.href); return u.pathname + u.search === r; });
				if (!a) return false;
				a.scrollIntoView({ block: 'center' }); a.click(); return true;
			}, r).catch(() => false);
		}
		if (!clicked) await page.evaluate(r => { location.href = r; }, r).catch(() => { });
		// Respuesta visual: ¿cambió algo en el menú o apareció la barra antes de que llegue la página?
		const samples = [];
		let feedbackMs = null, commitMs = null;
		while (Date.now() - t0 < 60000) {
			const s = await page.evaluate(() => {
				const vis = el => !!(el && el.getBoundingClientRect().height > 0 && getComputedStyle(el).visibility !== 'hidden' && getComputedStyle(el).opacity !== '0');
				const pc = document.getElementById('page-content');
				return { old: !!window.__old, fb: document.documentElement.classList.contains('daily-nav-pending') || document.documentElement.classList.contains('daily-nav-loading'), hdr: vis(document.querySelector('nav.navbar.fixed-top')), side: vis(document.getElementById('dairySidenav')) || innerWidth < 992, blank: !document.body || !pc || pc.innerText.trim().length === 0, ready: document.readyState };
			}).catch(() => null);
			const dt = Date.now() - t0;
			if (s) {
				samples.push(s);
				if (feedbackMs === null && (s.fb || !s.old)) feedbackMs = dt;
				if (commitMs === null && !s.old) commitMs = dt;
				if (!s.old && s.ready === 'complete') break;
			}
			await page.waitForTimeout(25);
		}
		await page.waitForTimeout(400);
		const m = await snapshot(page).catch(() => ({}));
		// feedbackMs: primer cuadro con respuesta visible (barra + menú) medido en la página; si no hubo, llegada del documento nuevo
		if (navFb !== null) feedbackMs = navFb;
		const row = { r, clicked, totalMs: Date.now() - t0 - 400, feedbackMs, commitMs, ...m, blank: samples.filter(s => !s.old && s.blank).length, layoutLost: samples.filter(s => !(s.hdr && s.side)).length, errs: errors.filter(e => e.at === r.split('?')[0]).length };
		out.routes.push(row);
		console.log(JSON.stringify(row));
	}

	// Atrás / adelante (bfcache o recarga)
	for (let i = 0; i < 4; i++) {
		const t0 = Date.now();
		await page.goBack({ waitUntil: 'load', timeout: 60000 }).catch(() => { });
		const b = { dir: 'back', to: here(), ms: Date.now() - t0 };
		const t1 = Date.now();
		await page.goForward({ waitUntil: 'load', timeout: 60000 }).catch(() => { });
		out.backForward.push(b, { dir: 'fwd', to: here(), ms: Date.now() - t1 });
	}

	// Idioma ES ↔ EN repetido en un tablero con gráficos: no debe recargar ni dejar textos/errores
	await page.goto(BASE + '/sales', { waitUntil: 'load', timeout: 120000 });
	for (let i = 0; i < 6; i++) {
		const lang = i % 2 ? 'es' : 'en';
		await page.evaluate(() => { window.__mark = 1; });
		const t0 = Date.now();
		await page.evaluate(l => {
			const t = document.querySelector('[data-daily-lang="' + l + '"]');
			if (t) t.click();
		}, lang);
		await page.waitForFunction(l => document.documentElement.lang === l, lang, { timeout: 15000 }).catch(() => { });
		out.lang.push({ lang, ms: Date.now() - t0, reloaded: !(await page.evaluate(() => window.__mark).catch(() => 0)), htmlLang: await page.evaluate(() => document.documentElement.lang).catch(() => '?') });
	}
	// Tras volver a ES, navegar conserva idioma
	await page.goto(BASE + '/kpis', { waitUntil: 'load', timeout: 120000 });
	out.lang.push({ afterNav: await page.evaluate(() => document.documentElement.lang) });

	// Tema de color repetido + persistencia al navegar
	for (const t of ['red', 'yellow', 'green', 'red']) {
		await page.evaluate(t => window.setDailyTheme && window.setDailyTheme(t, false), t);
		out.theme.push({ t, applied: await page.evaluate(() => document.documentElement.getAttribute('data-theme') || 'green') });
	}
	await page.goto(BASE + '/waste', { waitUntil: 'commit', timeout: 120000 });
	out.theme.push({ afterNavFirstFrame: await page.evaluate(() => document.documentElement.getAttribute('data-theme') || 'green') });
	await page.waitForLoadState('load');
	await page.evaluate(() => window.setDailyTheme && window.setDailyTheme('green', false));

	fs.writeFileSync(OUT, JSON.stringify(out, null, 1));
	const by = {}; errors.forEach(e => { const k = e.kind + ' ' + e.at + ' ' + e.msg; by[k] = (by[k] || 0) + 1; });
	console.log('ERRORES', errors.length); Object.entries(by).slice(0, 60).forEach(([k, n]) => console.log(n + 'x', k));
	console.log('BACKFWD', JSON.stringify(out.backForward));
	console.log('LANG', JSON.stringify(out.lang));
	console.log('THEME', JSON.stringify(out.theme));
	await browser.close();
})();
