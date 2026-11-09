// Auditoría completa de navegación por rol (manager, supervisor, empleado, demo) en escritorio y móvil.
// Por cada ruta del menú del rol: clic desde el menú (escritorio) y acceso directo + recarga; comprueba que el documento
// responde < 400, que el layout (encabezado/menú) sigue visible, que el contenido central se renderizó completo
// (#page-content-end, texto y un bloque principal), que el menú marca la ruta activa y que no hay errores de JS ni
// solicitudes fallidas. Además: cambio rápido entre dashboards, atrás/adelante con contenido correcto, persistencia de
// tema e idioma, rutas inexistentes y páginas ocultas por rol abiertas por URL directa.
// Uso: PW_DIR=... node tests/dairy/nav_full_audit.js <salida.json> [desktop|mobile|both] [roles,separados] [vueltas]
const fs = require('fs');
const { chromium } = require(process.env.PW_DIR || 'playwright');
const BASE = 'http://localhost:8080';
const OUT = process.argv[2] || 'nav_full_audit.json';
const MODES = (process.argv[3] || 'both') === 'both' ? ['desktop', 'mobile'] : [process.argv[3]];
const ROLES = (process.argv[4] || 'manager,supervisor,empleado,demo').split(',');
const RUNS = +(process.argv[5] || 1);
const HOME = { manager: '/today', supervisor: '/today', empleado: '/operations/attendance?role_view=employee', demo: '/sales' };
const res = { at: new Date().toISOString(), runs: [], fails: [] };
const fail = (ctx, msg) => { res.fails.push(ctx + ' · ' + msg); console.log('FAIL ' + ctx + ' · ' + msg); };

// Estado de la página: layout, contenido central y ruta activa del menú
const PROBE = () => {
	const vis = el => !!(el && el.getBoundingClientRect().height > 0 && getComputedStyle(el).visibility !== 'hidden');
	const pc = document.getElementById('page-content');
	const text = pc ? pc.innerText.trim() : '';
	// El más profundo: en grupos (datos maestros) quedan activos el grupo y el hijo
	const acts = document.querySelectorAll('#dairySidenav .dnav-item.is-active > a.dnav-link');
	const act = acts[acts.length - 1];
	const fatal = /(Fatal error|Stack trace|Uncaught |Whoops|SQLSTATE|Error source)/.test(text.slice(0, 4000));
	return {
		path: location.pathname + location.search,
		hdr: vis(document.querySelector('nav.navbar.fixed-top')),
		side: innerWidth < 992 || vis(document.getElementById('dairySidenav')),
		end: !!document.getElementById('page-content-end'),
		chars: text.length,
		block: !!(pc && pc.querySelector('h1,h2,h3,h4,table,canvas,svg,.card,form,.kpi-card,[class*="kpi"]')),
		fatal,
		active: act ? new URL(act.href).pathname : null,
		theme: document.documentElement.getAttribute('data-theme') || 'green',
		lang: document.documentElement.lang,
		notice: !!(document.getElementById('daily-notice') && !document.getElementById('daily-notice').hidden),
	};
};
// Reintenta si el documento cambia durante la lectura (navegación aún en curso)
async function probe(page) {
	for (let i = 0; i < 8; i++) {
		const s = await page.evaluate(PROBE).catch(() => null);
		if (s && s.end) return s;
		await page.waitForTimeout(300);
		await page.waitForLoadState('load').catch(() => { });
	}
	return page.evaluate(PROBE).catch(() => null);
}
const okPage = (s, minChars = 60) => s && s.hdr && s.side && s.end && s.chars >= minChars && s.block && !s.fatal;

async function audit(browser, mode, role, run) {
	const mobile = mode === 'mobile';
	const ctx = await browser.newContext(mobile ? { viewport: { width: 390, height: 844 }, deviceScaleFactor: 2, isMobile: true, hasTouch: true } : { viewport: { width: 1440, height: 900 } });
	await ctx.addCookies([{ name: 'daily_simulated_role', value: role, url: BASE }]);
	const page = await ctx.newPage();
	const errors = [];
	const here = () => { try { return new URL(page.url()).pathname; } catch (e) { return '?'; } };
	page.on('pageerror', e => errors.push({ at: here(), kind: 'pageerror', msg: e.message.slice(0, 200) }));
	page.on('console', m => { if (m.type() === 'error' && !/favicon|ERR_ABORTED/.test(m.text())) errors.push({ at: here(), kind: 'console', msg: m.text().slice(0, 200) }); });
	page.on('response', r => { if (r.status() >= 400 && !/favicon/.test(r.url()) && !/ruta-inexistente/.test(r.url())) errors.push({ at: here(), kind: 'http' + r.status(), msg: r.url().replace(BASE, '').slice(0, 160) }); });
	page.on('requestfailed', r => { const f = (r.failure() || {}).errorText || ''; if (!/ERR_ABORTED/.test(f)) errors.push({ at: here(), kind: 'requestfailed', msg: r.url().replace(BASE, '').slice(0, 140) + ' ' + f }); });
	const tag = `${mode}/${role}#${run}`;
	const out = { mode, role, run, routes: [], rapid: null, history: [], prefs: [], hidden: [], notFound: null };

	await page.goto(BASE + HOME[role], { waitUntil: 'load', timeout: 120000 });
	let routes = await page.evaluate(() => [...document.querySelectorAll('#dairySidenav a.dnav-link[href]')].map(a => new URL(a.href)).filter(u => u.origin === location.origin).map(u => u.pathname + u.search));
	routes = [...new Set(routes)].filter(r => !/logout|set-role/.test(r));
	if (!routes.length) fail(tag, 'el menú no tiene rutas');

	for (const r of routes) {
		const errBefore = errors.length;
		// 1) Por clic en el menú (escritorio) o navegación del documento (móvil, menú plegado)
		const t0 = Date.now();
		let status = 0;
		const respP = page.waitForResponse(x => x.request().isNavigationRequest() && x.request().frame() === page.mainFrame(), { timeout: 90000 }).then(x => { status = x.status(); }).catch(() => { });
		let clicked = false;
		if (!mobile) {
			clicked = await page.evaluate(r => {
				const a = [...document.querySelectorAll('#dairySidenav a.dnav-link[href]')].find(a => { const u = new URL(a.href); return u.pathname + u.search === r; });
				if (!a) return false;
				a.scrollIntoView({ block: 'center' }); a.click(); return true;
			}, r).catch(() => false);
		}
		if (!clicked) await page.goto(BASE + r, { waitUntil: 'commit', timeout: 90000 }).catch(() => { });
		await respP;
		const want0 = r.split('?')[0].split('#')[0];
		await page.waitForURL(u => new URL(u).pathname === want0, { timeout: 90000 }).catch(() => { });
		await page.waitForLoadState('load', { timeout: 90000 }).catch(() => { });
		const ms = Date.now() - t0;
		const s = await probe(page);
		// 2) Acceso directo + recarga de la misma URL
		await page.reload({ waitUntil: 'load', timeout: 90000 }).catch(() => { });
		const s2 = await probe(page);
		const errs = errors.slice(errBefore);
		const want = r.split('?')[0].split('#')[0];
		const row = { r, clicked, status, ms, ok: okPage(s) && okPage(s2), landed: s && s.path, chars: s && s.chars, active: s && s.active, errs: errs.length };
		out.routes.push(row);
		if (status >= 400) fail(tag, `${r} → HTTP ${status}`);
		else if (!okPage(s)) fail(tag, `${r} sin contenido o layout tras clic ${JSON.stringify(s)}`);
		else if (!okPage(s2)) fail(tag, `${r} sin contenido o layout tras recarga ${JSON.stringify(s2)}`);
		if (s && !s.path.startsWith(want)) fail(tag, `${r} terminó en ${s.path}`);
		if (!mobile && clicked && s && s.active && s.active !== want && !r.includes('#')) fail(tag, `${r}: el menú marca activa ${s.active}`);
		if (s && s.notice) fail(tag, `${r}: aviso de error visible`);
		errs.forEach(e => fail(tag, `${r} ${e.kind} ${e.msg}`));
	}

	const pick = routes.filter(r => !/[?#]/.test(r)).slice(0, 8);
	if (pick.length >= 4) {
		// Cambio rápido entre dashboards: 5 clics seguidos cada 120 ms; debe quedar el último, completo y sin errores
		const errBefore = errors.length;
		const seq = pick.slice(1, 6);
		for (const r of seq) {
			if (mobile) page.goto(BASE + r, { waitUntil: 'commit' }).catch(() => { });
			else await page.evaluate(r => { const a = [...document.querySelectorAll('#dairySidenav a.dnav-link[href]')].find(a => new URL(a.href).pathname === r); if (a) a.click(); else location.href = r; }, r).catch(() => { });
			await page.waitForTimeout(120);
		}
		const last = seq[seq.length - 1];
		await page.waitForURL(u => new URL(u).pathname === last, { timeout: 90000 }).catch(() => { });
		await page.waitForLoadState('load', { timeout: 90000 }).catch(() => { });
		await page.waitForTimeout(300);
		const s = await probe(page);
		out.rapid = { seq, landed: s && s.path, ok: okPage(s) && s.path === last, errs: errors.length - errBefore };
		if (!out.rapid.ok) fail(tag, 'cambio rápido: ' + JSON.stringify(out.rapid));
		errors.slice(errBefore).filter(e => e.kind !== 'console' || !/aborted|cancel/i.test(e.msg)).forEach(e => fail(tag, `cambio rápido ${e.kind} ${e.msg}`));

		// Atrás / adelante: la URL y el contenido corresponden a la entrada del historial
		for (const r of pick.slice(0, 3)) await page.goto(BASE + r, { waitUntil: 'load', timeout: 90000 });
		for (const [dir, expect] of [['back', pick[1]], ['back', pick[0]], ['fwd', pick[1]], ['fwd', pick[2]]]) {
			const t0 = Date.now();
			if (dir === 'back') await page.goBack({ waitUntil: 'load', timeout: 90000 }).catch(() => { }); else await page.goForward({ waitUntil: 'load', timeout: 90000 }).catch(() => { });
			const s = await probe(page);
			const h = { dir, expect, landed: s && s.path, ms: Date.now() - t0, ok: !!s && s.path === expect && okPage(s) && (mobile || !s.active || s.active === expect) };
			out.history.push(h);
			if (!h.ok) fail(tag, 'historial: ' + JSON.stringify(h) + ' ' + JSON.stringify(s));
		}
	}

	// Tema e idioma: se aplican sin recargar y se conservan al navegar y al recargar
	await page.evaluate(() => window.setDailyTheme && window.setDailyTheme('red', false)).catch(() => { });
	await page.evaluate(() => { const t = document.querySelector('[data-daily-lang="en"]'); if (t) t.click(); }).catch(() => { });
	await page.waitForFunction(() => document.documentElement.lang === 'en', null, { timeout: 15000 }).catch(() => { });
	for (const r of pick.slice(0, 3)) {
		await page.goto(BASE + r, { waitUntil: 'load', timeout: 90000 });
		const s = await page.evaluate(PROBE);
		out.prefs.push({ r, theme: s.theme, lang: s.lang });
		if (s.theme !== 'red' || s.lang !== 'en') fail(tag, `preferencias perdidas en ${r}: tema ${s.theme}, idioma ${s.lang}`);
	}
	await page.reload({ waitUntil: 'load' });
	const sr = await page.evaluate(PROBE);
	if (sr.theme !== 'red' || sr.lang !== 'en') fail(tag, `preferencias perdidas al recargar: ${sr.theme}/${sr.lang}`);
	await page.evaluate(() => window.setDailyTheme && window.setDailyTheme('green', false)).catch(() => { });
	await page.evaluate(() => { const t = document.querySelector('[data-daily-lang="es"]'); if (t) t.click(); }).catch(() => { });
	await page.waitForFunction(() => document.documentElement.lang === 'es', null, { timeout: 15000 }).catch(() => { });

	// Ruta inexistente: 404 con encabezado y mensaje, sin pantalla en blanco
	const nf = await page.goto(BASE + '/ruta-inexistente-auditoria', { waitUntil: 'load' });
	const s404 = await page.evaluate(PROBE);
	out.notFound = { status: nf && nf.status(), hdr: s404.hdr, chars: s404.chars };
	if (!nf || nf.status() !== 404 || !s404.hdr || s404.chars < 10) fail(tag, '404 incorrecto: ' + JSON.stringify(out.notFound));

	await ctx.close();
	out.routesOk = out.routes.filter(x => x.ok).length;
	out.allRoutes = routes;
	console.log(`${tag}: ${out.routesOk}/${out.routes.length} rutas OK · rápido ${out.rapid && out.rapid.ok} · historial ${out.history.filter(h => h.ok).length}/${out.history.length}`);
	return out;
}

// Páginas que el menú del rol oculta, abiertas por URL directa (sin el menú): ¿se bloquean?
async function hiddenByRole(browser, all, roleRoutes) {
	const out = {};
	for (const role of ROLES.filter(r => r !== 'manager')) {
		const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 } });
		await ctx.addCookies([{ name: 'daily_simulated_role', value: role, url: BASE }]);
		const page = await ctx.newPage();
		out[role] = [];
		for (const r of all.filter(r => !roleRoutes[role].includes(r) && !/[?#]/.test(r))) {
			const resp = await page.goto(BASE + r, { waitUntil: 'load', timeout: 90000 }).catch(() => null);
			const landed = new URL(page.url()).pathname;
			const blocked = !resp || resp.status() === 403 || landed !== r;
			out[role].push({ r, status: resp && resp.status(), landed, blocked });
		}
		const open = out[role].filter(x => !x.blocked).map(x => x.r);
		console.log(`${role}: ${out[role].length} páginas ocultas en el menú; abren por URL directa: ${open.length}${open.length ? ' (' + open.slice(0, 12).join(', ') + (open.length > 12 ? '…' : '') + ')' : ''}`);
		await ctx.close();
	}
	return out;
}

(async () => {
	const browser = await chromium.launch({ channel: 'msedge' });
	// Calentar (la primera visita de la hora puede disparar la empresa demo)
	{ const c = await browser.newContext(); const p = await c.newPage(); await p.goto(BASE + '/today', { waitUntil: 'load', timeout: 180000 }); await c.close(); }
	for (let run = 1; run <= RUNS; run++) {
		for (const mode of MODES) for (const role of ROLES) res.runs.push(await audit(browser, mode, role, run));
	}
	const roleRoutes = {};
	res.runs.forEach(x => { roleRoutes[x.role] = x.allRoutes; });
	if (roleRoutes.manager) res.hiddenByRole = await hiddenByRole(browser, roleRoutes.manager, roleRoutes);
	await browser.close();
	fs.writeFileSync(OUT, JSON.stringify(res, null, 1));
	const total = res.runs.reduce((s, x) => s + x.routes.length, 0), ok = res.runs.reduce((s, x) => s + x.routesOk, 0);
	console.log(`\nRESUMEN: ${ok}/${total} visitas de ruta OK · ${res.fails.length} fallas`);
	process.exit(res.fails.length ? 1 : 0);
})();
