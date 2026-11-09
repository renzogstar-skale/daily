// Recolecta textos visibles (nodos de texto + atributos) por ruta para construir/auditar el catálogo i18n.
// Uso: PW_DIR=... node tests/dairy/i18n_harvest.js <salida.json> [ruta1,ruta2] [lang=es|en]
const fs = require('fs');
const { chromium } = require(process.env.PW_DIR);

const ALL = ['/today', '/alerts', '/kpis', '/action-center', '/mrp', '/suppliers', '/branches', '/merchandise', '/scan',
	'/locations/map', '/stockoverview', '/transfers', '/kardex', '/inventory-control', '/inventory', '/reconciliation',
	'/sales', '/pos', '/orders', '/customers', '/dispatch', '/returns', '/quality', '/traceability', '/recalls',
	'/waste', '/waste/report', '/waste/approvals', '/waste/reasons', '/loss-prevention', '/loss-prevention/devices',
	'/reports/loss-executive', '/operations/scheduling', '/operations/attendance', '/workforce', '/reports', '/schedule',
	'/cost-centers', '/settings', '/users', '/products', '/data-import', '/locations', '/locations/labels', '/audit-log',
	'/system-map', '/admin/economics', '/batteries', '/chores', '/productgroups', '/quantityunits', '/shoppinglocations',
	'/taskcategories', '/userentities', '/userfields', '/m', '/stockentries', '/stockjournal', '/shoppinglist', '/purchase',
	'/consume', '/transfer', '/tasks', '/recipes', '/mealplan', '/calendar', '/equipment', '/about', '/usersettings',
	'/stocksettings', '/manageapikeys', '/process-flow', '/waste/overview', '/store-audits', '/login'];

// Se ejecuta en la página: devuelve los textos visibles y de atributos
function collect() {
	const out = new Set();
	const skip = 'SCRIPT,STYLE,NOSCRIPT,CODE,PRE,TEXTAREA,svg'.split(',');
	const w = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT);
	let n;
	while ((n = w.nextNode())) {
		const p = n.parentElement;
		if (!p || skip.includes(p.tagName) || p.closest('[data-i18n-skip]')) continue;
		const t = n.nodeValue.replace(/\s+/g, ' ').trim();
		if (t && /[A-Za-zÁÉÍÓÚáéíóúñÑ]{2}/.test(t)) out.add(t);
	}
	document.querySelectorAll('[title],[placeholder],[aria-label],[alt],[data-original-title],[data-content],input[type=button],input[type=submit]').forEach(e => {
		for (const a of ['title', 'placeholder', 'aria-label', 'alt', 'data-original-title', 'data-content', 'value']) {
			if (a === 'value' && !(e.tagName === 'INPUT' && /button|submit/.test(e.type))) continue;
			const v = (e.getAttribute(a) || '').replace(/\s+/g, ' ').trim();
			if (v && /[A-Za-zÁÉÍÓÚáéíóúñÑ]{2}/.test(v)) out.add(v);
		}
	});
	if (window.Chart && Chart.instances) Object.values(Chart.instances).forEach(c => {
		const d = c.config && c.config.data;
		if (!d) return;
		(d.labels || []).forEach(l => typeof l === 'string' && out.add(l));
		(d.datasets || []).forEach(s => s.label && out.add(String(s.label)));
	});
	out.add('__TITLE__' + document.title);
	return [...out];
}

(async () => {
	const outFile = process.argv[2];
	const routes = process.argv[3] && process.argv[3] !== 'all' ? process.argv[3].split(',') : ALL;
	const lang = process.argv[4] || 'es';
	const browser = await chromium.launch({ channel: 'msedge' });
	const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 } });
	await ctx.addInitScript(l => { try { localStorage.setItem('daily_lang', l); } catch (e) {} }, lang);
	await ctx.addCookies([{ name: 'daily_lang', value: lang, url: 'http://localhost:8080' }]);
	const page = await ctx.newPage();
	const res = {};
	for (const r of routes) {
		const errors = [];
		const onErr = e => errors.push('pageerror: ' + e.message.slice(0, 200));
		const onCon = m => { if (m.type() === 'error' && !/favicon|Failed to load resource/.test(m.text())) errors.push(m.text().slice(0, 200)); };
		page.on('pageerror', onErr); page.on('console', onCon);
		let status = 0;
		try {
			const resp = await page.goto('http://localhost:8080' + r, { waitUntil: 'load', timeout: 120000 });
			status = resp ? resp.status() : 0;
			await page.waitForTimeout(1500);
			// abrir pestañas para capturar su contenido
			const tabs = await page.$$('[data-toggle="tab"]:visible, [data-toggle="pill"]:visible');
			for (const t of tabs.slice(0, 14)) { await t.click({ timeout: 1500 }).catch(() => {}); await page.waitForTimeout(250); }
		} catch (e) { errors.push('nav: ' + e.message.slice(0, 120)); }
		const texts = await page.evaluate(collect).catch(() => []);
		page.off('pageerror', onErr); page.off('console', onCon);
		res[r] = { status, errors, texts };
		console.log(r, status, texts.length, errors.length ? 'ERR ' + errors.length : '');
	}
	fs.writeFileSync(outFile, JSON.stringify(res, null, 1));
	await browser.close();
})();
