// Auditoría del idioma global: abre cada ruta en EN, abre pestañas, y reporta textos en español
// que quedaron sin traducir, errores de consola y desbordamiento horizontal (escritorio y móvil).
// Uso: PW_DIR=... node tests/dairy/i18n_audit.js <salida.json> [rutas|all] [desk|mob|both]
const fs = require('fs');
const { chromium } = require(process.env.PW_DIR);
const ROUTES = ['/today', '/alerts', '/kpis', '/action-center', '/mrp', '/suppliers', '/branches', '/merchandise', '/scan',
	'/locations/map', '/stockoverview', '/transfers', '/kardex', '/inventory-control', '/inventory', '/reconciliation',
	'/sales', '/pos', '/orders', '/customers', '/dispatch', '/returns', '/quality', '/traceability', '/recalls',
	'/waste', '/waste/report', '/waste/approvals', '/waste/reasons', '/loss-prevention', '/loss-prevention/devices',
	'/reports/loss-executive', '/operations/scheduling', '/operations/attendance', '/workforce', '/reports', '/schedule',
	'/cost-centers', '/settings', '/users', '/products', '/data-import', '/locations', '/locations/labels', '/audit-log',
	'/system-map', '/admin/economics', '/m', '/stockjournal', '/purchase', '/consume', '/transfer', '/tasks', '/usersettings',
	'/stocksettings', '/process-flow', '/store-audits'];
const SPANISH = /[áéíóúñ¿¡]|\b(de|del|los|las|para|con|sin|por|una|que|hoy|ventas|merma|mermas|sucursal|existencias|pendiente|guardar|cancelar|buscar|cerrar|ver|nuevo|nueva|fecha|cantidad|motivo|responsable|productos?|tienda|turno|conteo|lote|estado|alerta|alertas)\b/i;

(async () => {
	const out = process.argv[2];
	const routes = process.argv[3] && process.argv[3] !== 'all' ? process.argv[3].split(',') : ROUTES;
	const mode = process.argv[4] || 'desk';
	const vps = mode === 'both' ? [['desk', 1440, 900], ['mob', 390, 844]] : mode === 'mob' ? [['mob', 390, 844]] : [['desk', 1440, 900]];
	const browser = await chromium.launch({ channel: 'msedge' });
	const report = [];
	for (const [vp, w, h] of vps) {
		const ctx = await browser.newContext({ viewport: { width: w, height: h } });
		await ctx.addInitScript(() => { try { localStorage.setItem('daily_lang', 'en'); } catch (e) { } });
		await ctx.addCookies([{ name: 'daily_lang', value: 'en', url: 'http://localhost:8080' }]);
		const page = await ctx.newPage();
		for (const r of routes) {
			const errors = [];
			const onErr = e => errors.push('pageerror: ' + e.message.slice(0, 160));
			const onCon = m => { if (m.type() === 'error' && !/favicon|Failed to load resource/.test(m.text())) errors.push(m.text().slice(0, 160)); };
			page.on('pageerror', onErr); page.on('console', onCon);
			let status = 0;
			try {
				const resp = await page.goto('http://localhost:8080' + r, { waitUntil: 'load', timeout: 120000 });
				status = resp ? resp.status() : 0;
				await page.waitForTimeout(1500);
				if (vp === 'desk') {
					const tabs = await page.$$('[data-toggle="tab"]:visible, [data-toggle="pill"]:visible');
					for (const t of tabs.slice(0, 14)) { await t.click({ timeout: 1500 }).catch(() => { }); await page.waitForTimeout(250); }
				}
			} catch (e) { errors.push('nav: ' + e.message.slice(0, 120)); }
			const res = await page.evaluate(() => {
				const miss = window.DailyI18n ? Object.keys(DailyI18n.missing()) : ['NO_RUNTIME'];
				const wd = document.documentElement.clientWidth;
				return { lang: window.DailyI18n && DailyI18n.lang, htmlLang: document.documentElement.lang, pending: document.documentElement.classList.contains('daily-i18n-pending'), miss, hscroll: document.documentElement.scrollWidth > wd + 1, title: document.title };
			}).catch(e => ({ miss: [], err: e.message }));
			page.off('pageerror', onErr); page.off('console', onCon);
			const spanish = (res.miss || []).filter(s => SPANISH.test(s));
			report.push({ route: r, vp, status, lang: res.lang, htmlLang: res.htmlLang, pending: res.pending, hscroll: res.hscroll, title: res.title, missingAll: (res.miss || []).length, spanish: spanish.length, sample: spanish.slice(0, 400), errors });
			console.log(vp, r, status, 'es-pend:', spanish.length, errors.length ? 'ERR ' + errors.length : '');
		}
		await ctx.close();
	}
	fs.writeFileSync(out, JSON.stringify(report, null, 1));
	await browser.close();
})();
