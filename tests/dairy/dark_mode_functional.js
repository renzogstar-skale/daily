// Pruebas funcionales del modo Día/Noche (DailyTheme).
// Uso: node tests/dairy/dark_mode_functional.js   (PW_DIR = Playwright del caché npx)
const { chromium } = require(process.env.PW_DIR || 'playwright');
const BASE = 'http://localhost:8080';
const results = [];
const check = (name, ok, info) => { results.push({ name, ok: !!ok, info }); console.log((ok ? 'PASS ' : 'FAIL ') + name + (info !== undefined ? ' — ' + JSON.stringify(info) : '')); };

// Estado del primer cuadro pintado (rAF corre justo antes de pintar)
const firstFrame = () => {
	requestAnimationFrame(() => {
		const parse = c => { const m = c.match(/^rgba?\(([\d.]+),\s*([\d.]+),\s*([\d.]+)(?:,\s*([\d.]+))?\)$/); return m ? [+m[1], +m[2], +m[3], m[4] === undefined ? 1 : +m[4]] : null; };
		let lightCards = 0;
		const pc = document.getElementById('page-content');
		if (pc) for (const e of pc.querySelectorAll('div, section, article')) {
			const b = e.getBoundingClientRect();
			if (b.width < 120 || b.height < 60 || b.top > innerHeight || b.bottom < 0) continue;
			const c = parse(getComputedStyle(e).backgroundColor);
			if (c && c[3] > 0.6 && c[0] > 235 && c[1] > 235 && c[2] > 235) lightCards++;
		}
		window.__firstFrame = { dark: document.documentElement.classList.contains('daily-dark'), body: document.body && getComputedStyle(document.body).backgroundColor, lightCards };
	});
};

(async () => {
	const browser = await chromium.launch({ channel: 'msedge' });
	const errors = [];
	const watch = page => {
		page.on('pageerror', e => errors.push(page.url() + ' pageerror: ' + e.message.slice(0, 160)));
		page.on('console', m => { if (m.type() === 'error' && !/favicon|Failed to load resource/.test(m.text())) errors.push(page.url() + ' ' + m.text().slice(0, 160)); });
	};

	// 1. Preferencia del sistema sin selección guardada
	for (const scheme of ['light', 'dark']) {
		const ctx = await browser.newContext({ colorScheme: scheme, viewport: { width: 1440, height: 900 } });
		await ctx.addInitScript(firstFrame);
		const page = await ctx.newPage(); watch(page);
		await page.goto(BASE + '/today', { waitUntil: 'load', timeout: 120000 });
		const s = await page.evaluate(() => ({ dark: document.documentElement.classList.contains('daily-dark'), pref: DailyTheme.preference(), ff: window.__firstFrame }));
		check('sistema ' + scheme + ' → modo inicial', s.dark === (scheme === 'dark') && s.pref === 'system', s);
		if (scheme === 'dark') check('primer cuadro ya oscuro (sin destello blanco)', s.ff && s.ff.dark && s.ff.lightCards === 0, s.ff);
		await ctx.close();
	}

	// 2. Cambio inmediato sin recargar, persistencia y recarga
	const ctx = await browser.newContext({ colorScheme: 'light', viewport: { width: 1440, height: 900 } });
	await ctx.addInitScript(firstFrame);
	const page = await ctx.newPage(); watch(page);
	await page.goto(BASE + '/sales', { waitUntil: 'load', timeout: 120000 });
	await page.waitForTimeout(800);
	const order = await page.evaluate(() => {
		const items = [...document.querySelectorAll('#mainNav .navbar-nav > li.nav-item')].filter(li => li.offsetParent);
		return items.map(li => li.classList.contains('daily-lang-switch') ? 'lang' : li.classList.contains('daily-mode-switch') ? 'mode' : li.querySelector('#headerAlertsButton') ? 'bell' : li.classList.contains('daily-user-profile-item') ? 'profile' : null).filter(Boolean);
	});
	check('orden del encabezado idioma → día/noche → campana → perfil', order.join(',') === 'lang,mode,bell,profile', order);
	await page.evaluate(() => { window.__marker = 42; const i = document.querySelector('#page-content input[type="text"], #page-content input[type="search"]'); if (i) { i.value = 'filtro-prueba'; i.id = i.id || 'dk-test-input'; window.__inputId = i.id; } });
	const t0 = Date.now();
	await page.click('#dailyModeToggle');
	await page.waitForFunction(() => document.documentElement.classList.contains('daily-dark'), null, { timeout: 2000 });
	const dt = Date.now() - t0;
	await page.waitForTimeout(400);
	const after = await page.evaluate(() => ({
		marker: window.__marker,
		input: window.__inputId ? document.getElementById(window.__inputId).value : null,
		ls: localStorage.getItem('daily_mode'),
		cookie: /daily_mode=dark/.test(document.cookie),
		body: getComputedStyle(document.body).backgroundColor,
		pressed: document.getElementById('dailyModeToggle').getAttribute('aria-pressed'),
		nightAlias: document.body.classList.contains('night-mode')
	}));
	check('cambio sin recargar (estado JS y filtros intactos)', after.marker === 42 && (after.input === null || after.input === 'filtro-prueba'), { ms: dt, input: after.input });
	check('preferencia guardada (localStorage + cookie)', after.ls === 'dark' && after.cookie, after);
	check('fondo oscuro y aria-pressed', /rgb\(11, 18, 32\)/.test(after.body) && after.pressed === 'true' && after.nightAlias, after);

	const resp = await page.reload({ waitUntil: 'load', timeout: 120000 });
	const html = await resp.text();
	const rl = await page.evaluate(() => ({ dark: document.documentElement.classList.contains('daily-dark'), ff: window.__firstFrame }));
	check('tema conservado tras recargar (servidor pinta la clase)', rl.dark && /<html[^>]*class="daily-dark/.test(html), rl);
	check('recarga: primer cuadro oscuro', rl.ff && rl.ff.dark && rl.ff.lightCards === 0, rl.ff);

	// 3. Gráficos legibles en noche
	await page.goto(BASE + '/kpis', { waitUntil: 'load', timeout: 120000 });
	await page.waitForTimeout(1500);
	const charts = await page.evaluate(() => {
		if (!window.Chart) return { n: 0 };
		const list = Object.values(Chart.instances || {});
		const g = Chart.defaults.global.defaultFontColor;
		const bad = [];
		list.forEach(c => Object.values(c.scales || {}).forEach(s => {
			const col = (s.options.ticks && s.options.ticks.fontColor) || g;
			if (/#(0f172a|334155|475569|64748b|1e293b)/i.test(String(col))) bad.push(c.canvas.id + ':' + col);
		}));
		return { n: list.length, g, bad };
	});
	check('gráficos: ejes con texto claro en noche', charts.g === '#cbd5e1' && charts.bad.length === 0, charts);

	// 4. Navegación entre dashboards: sin destello
	const navs = [];
	for (const r of ['/today', '/stockoverview', '/waste', '/customers']) {
		await page.goto(BASE + r, { waitUntil: 'load', timeout: 120000 });
		navs.push(Object.assign({ r }, await page.evaluate(() => window.__firstFrame)));
	}
	check('navegación en noche: cada primer cuadro oscuro', navs.every(n => n.dark && n.lightCards === 0), navs);

	// 5. Idioma no afecta el tema
	await page.evaluate(() => document.querySelector('.daily-lang-switch [data-daily-lang="en"]').click());
	await page.waitForLoadState('load'); await page.waitForTimeout(1500);
	const en = await page.evaluate(() => ({ lang: document.documentElement.lang, dark: document.documentElement.classList.contains('daily-dark'), label: document.getElementById('dailyModeToggle').getAttribute('aria-label') }));
	check('cambio a EN conserva modo noche', en.lang === 'en' && en.dark, en);
	await page.evaluate(() => document.querySelector('.daily-lang-switch [data-daily-lang="es"]').click());
	await page.waitForLoadState('load'); await page.waitForTimeout(1200);
	const es = await page.evaluate(() => ({ lang: document.documentElement.lang, dark: document.documentElement.classList.contains('daily-dark') }));
	check('vuelta a ES conserva modo noche', es.lang === 'es' && es.dark, es);

	// 6. Perfil: Rol Manager y Plan Enterprise, menús funcionando en noche
	await page.click('#dailyUserProfileDropdown');
	await page.waitForTimeout(300);
	const prof = await page.evaluate(() => {
		const m = document.querySelector('.daily-profile-dropdown-menu');
		return { open: m && m.classList.contains('show'), text: m ? m.innerText.replace(/\s+/g, ' ') : '', bg: m ? getComputedStyle(m).backgroundColor : '' };
	});
	check('perfil abre con Rol Manager y Plan Enterprise', prof.open && /Manager/.test(prof.text) && /Enterprise/.test(prof.text), { open: prof.open, bg: prof.bg });
	await page.keyboard.press('Escape');
	const sub = await page.goto(BASE + '/subscription', { waitUntil: 'load', timeout: 120000 });
	check('permisos: /subscription accesible (Manager/Enterprise)', sub.status() === 200, sub.status());

	// 7. Volver a Día y persistencia
	await page.click('#dailyModeToggle');
	await page.waitForTimeout(500);
	await page.reload({ waitUntil: 'load' });
	const day = await page.evaluate(() => ({ dark: document.documentElement.classList.contains('daily-dark'), ls: localStorage.getItem('daily_mode'), crystal: document.body.classList.contains('crystal-ui') }));
	check('vuelta a Día persiste', !day.dark && day.ls === 'light' && day.crystal, day);
	await ctx.close();

	// 8. Móvil
	const mctx = await browser.newContext({ colorScheme: 'light', viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true });
	const mp = await mctx.newPage(); watch(mp);
	await mp.goto(BASE + '/today', { waitUntil: 'load', timeout: 120000 });
	const mv = await mp.evaluate(() => { const b = document.getElementById('dailyModeToggle').getBoundingClientRect(); return { w: b.width, h: b.height, x: b.right, vw: innerWidth, hscroll: document.documentElement.scrollWidth > innerWidth + 1 }; });
	check('móvil: selector visible dentro de la pantalla', mv.w > 30 && mv.x <= mv.vw && !mv.hscroll, mv);
	await mp.tap('#dailyModeToggle');
	await mp.waitForTimeout(400);
	check('móvil: toque cambia a noche', await mp.evaluate(() => document.documentElement.classList.contains('daily-dark')));
	await mp.screenshot({ path: '.playwright-mcp/dark/mobile_today_dark.png' });
	await mctx.close();

	check('sin errores de consola', errors.length === 0, errors.slice(0, 10));
	await browser.close();
	const failed = results.filter(r => !r.ok).length;
	console.log(`\n${results.length - failed}/${results.length} OK`);
	process.exit(failed ? 1 : 0);
})();
