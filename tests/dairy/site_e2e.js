// E2E del sitio comercial público. Uso: node tests/dairy/site_e2e.js <carpeta_capturas> [base]
// Requiere Playwright (NODE_PATH o caché npx) y Edge.
const path = require('path');
const fs = require('fs');
const { chromium } = require(process.env.PW || 'playwright');

const OUT = process.argv[2] || 'site_shots';
const BASE = process.argv[3] || 'http://localhost:8080';
const PAGES = ['/', '/features', '/features/sales', '/features/inventory', '/features/shrink', '/features/loss-prevention', '/features/dispatch', '/features/workforce', '/features/planning', '/features/analytics', '/solutions', '/industries', '/industries/supermarkets', '/industries/retail', '/industries/electronics', '/industries/distribution', '/industries/distribution-centers', '/industries/multi-branch', '/pricing', '/demo', '/contact', '/privacy', '/terms'];
const results = [];
const ok = (name, pass, info) => { results.push({ name, pass: !!pass, info: info || '' }); console.log((pass ? 'OK  ' : 'FAIL') + ' ' + name + (info ? ' — ' + info : '')); };

(async () => {
	fs.mkdirSync(OUT, { recursive: true });
	const browser = await chromium.launch({ channel: 'msedge' });

	for (const vp of [{ name: 'desktop', width: 1440, height: 900 }, { name: 'mobile', width: 390, height: 844, isMobile: true, hasTouch: true }]) {
		const ctx = await browser.newContext({ viewport: { width: vp.width, height: vp.height }, isMobile: !!vp.isMobile, hasTouch: !!vp.hasTouch, deviceScaleFactor: 1 });
		const page = await ctx.newPage();
		const errors = [];
		page.on('console', m => { if (m.type() === 'error') errors.push(m.text()); });
		page.on('pageerror', e => errors.push(String(e)));
		page.on('response', r => { if (r.status() >= 400 && r.url().startsWith(BASE) && !r.url().includes('/site/lead')) errors.push(r.status() + ' ' + r.url()); });

		for (const p of PAGES) {
			errors.length = 0;
			const res = await page.goto(BASE + p, { waitUntil: 'load' });
			// Forzar aparición de todo el contenido para la captura completa
			await page.evaluate(() => document.querySelectorAll('[data-reveal]').forEach(e => e.classList.add('is-in')));
			// Recorrer la página para que carguen las imágenes diferidas
			await page.evaluate(async () => { for (let y = 0; y < document.body.scrollHeight; y += 600) { window.scrollTo(0, y); await new Promise(r => setTimeout(r, 60)); } window.scrollTo(0, 0); });
			await page.evaluate(() => Promise.race([new Promise(r => setTimeout(r, 4000)), Promise.all([...document.images].map(i => i.complete ? 0 : new Promise(r => { i.onload = i.onerror = r; })))]));
			await page.waitForTimeout(500);
			const overflow = await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
			const cls = await page.evaluate(() => new Promise(r => { let v = 0; new PerformanceObserver(l => l.getEntries().forEach(e => { if (!e.hadRecentInput) v += e.value; })).observe({ type: 'layout-shift', buffered: true }); setTimeout(() => r(v), 300); }));
			ok(`${vp.name} ${p} carga`, res.status() === 200, `HTTP ${res.status()}`);
			ok(`${vp.name} ${p} sin errores de consola/red`, errors.length === 0, errors.slice(0, 3).join(' | '));
			ok(`${vp.name} ${p} sin scroll horizontal`, overflow <= 1, `exceso ${overflow}px`);
			ok(`${vp.name} ${p} CLS < 0.1`, cls < 0.1, cls.toFixed(3));
			const file = (p === '/' ? 'home' : p.slice(1).replace(/\//g, '_')) + `_${vp.name}.png`;
			if (vp.name === 'desktop' || ['/', '/features/sales', '/pricing', '/demo', '/industries/electronics'].includes(p)) {
				// fullPage de Chromium deja zonas en blanco o repetidas: se usa un viewport tan alto como la página
				const fullH = await page.evaluate(() => document.documentElement.scrollHeight);
				await page.setViewportSize({ width: vp.width, height: fullH });
				await page.waitForTimeout(400);
				await page.screenshot({ path: path.join(OUT, file) });
				await page.setViewportSize({ width: vp.width, height: vp.height });
			}
		}

		// Enlaces internos
		if (vp.name === 'desktop') {
			const hrefs = new Set();
			for (const p of PAGES) {
				await page.goto(BASE + p, { waitUntil: 'domcontentloaded' });
				(await page.$$eval('a[href]', as => as.map(a => a.href))).forEach(h => { if (h.startsWith(BASE)) hrefs.add(h.split('#')[0]); });
			}
			const broken = [];
			for (const h of hrefs) {
				const r = await ctx.request.get(h, { maxRedirects: 5 });
				if (r.status() >= 400) broken.push(r.status() + ' ' + h);
			}
			ok(`enlaces internos (${hrefs.size}) responden`, broken.length === 0, broken.join(' | '));
		}

		await page.goto(BASE + '/', { waitUntil: 'load' });
		if (vp.name === 'desktop') {
			// Mega menú por hover y por teclado
			await page.hover('.s-dd:nth-of-type(1) .s-dd__btn, [aria-controls="dd-platform"]');
			await page.waitForTimeout(300);
			ok('mega menú Plataforma se abre (hover)', await page.isVisible('#dd-platform'));
			await page.screenshot({ path: path.join(OUT, 'menu_platform_desktop.png') });
			await page.keyboard.press('Escape');
			await page.click('[aria-controls="dd-industries"]');
			await page.waitForTimeout(300);
			ok('menú Industrias se abre (clic)', await page.isVisible('#dd-industries'));
			await page.click('#dd-industries a >> nth=2');
			await page.waitForLoadState('load');
			ok('navegación desde menú Industrias', page.url().includes('/industries/electronics'), page.url());
			await page.goto(BASE + '/', { waitUntil: 'load' });
		} else {
			await page.click('.s-burger');
			await page.waitForTimeout(350);
			ok('menú móvil se abre', await page.isVisible('.s-nav__list'));
			await page.click('[aria-controls="dd-platform"]');
			await page.waitForTimeout(200);
			ok('submenú Plataforma en móvil', await page.isVisible('#dd-platform a >> nth=0'));
			await page.screenshot({ path: path.join(OUT, 'menu_mobile.png') });
			await page.click('.s-burger');
			await page.waitForTimeout(300);
			ok('menú móvil se cierra', !(await page.isVisible('.s-nav__list')));
		}

		// Recorrido: 7 pestañas, cada una con su recorte real, mismo tamaño y destino propio
		await page.locator('#tour').scrollIntoViewIfNeeded();
		await page.waitForTimeout(800);
		const seen = new Set(), heights = new Set(), frames = new Set();
		let reqBefore = 0;
		for (let i = 0; i < 7; i++) {
			await page.click('#tt-' + i);
			await page.evaluate(n => document.querySelector('#tp-' + n + ' .s-live').scrollIntoView({ block: 'center' }), i);
			await page.waitForTimeout(2600); // vista animada (daily_site_live.js): contadores y gráficos terminan en ~2 s
			const info = await page.evaluate(n => {
				const p = document.getElementById('tp-' + n), fig = p.querySelector('.s-live'), fr = fig.getBoundingClientRect();
				const st = document.querySelector('[data-tour]').getBoundingClientRect();
				const vis = [...document.querySelectorAll('.s-tour__panel')].filter(x => getComputedStyle(x).visibility === 'visible').length;
				const fmt = (v, d, pre, s) => { const a = Math.abs(v).toFixed(d).split('.'); a[0] = a[0].replace(/\B(?=(\d{3})+(?!\d))/g, ','); return pre + (v < 0 ? '-' : '') + a.join('.') + s; };
				const nums = [...fig.querySelectorAll('.lv-num')], bad = nums.filter(e => e.textContent !== fmt(+e.dataset.v, +e.dataset.d, e.dataset.p, e.dataset.s)).length;
				return { key: fig.dataset.live, title: p.querySelector('h3').textContent.trim(), points: p.querySelectorAll('.s-checks li').length, href: p.querySelector('.s-btn').getAttribute('href'), fw: Math.round(fr.width), fh: Math.round(fr.height), sh: Math.round(st.height), vis, nums: nums.length, bad, play: fig.querySelector('.lv').classList.contains('is-play') };
			}, i);
			seen.add(info.key);
			heights.add(info.sh); frames.add(info.fw + 'x' + info.fh);
			ok(`recorrido ${i} "${info.title}": vista animada propia y 4 beneficios`, info.play && info.points === 4 && info.vis === 1, `${info.key} · ${info.points} beneficios · paneles visibles ${info.vis}`);
			ok(`recorrido ${i}: ${info.nums} cifras animadas terminan en el valor del dataset`, info.nums > 0 && info.bad === 0, `${info.bad} distintas`);
			ok(`recorrido ${i}: vista legible (marco ≥ ${vp.name === 'desktop' ? 700 : 340}px)`, info.fw >= (vp.name === 'desktop' ? 700 : 340), `${info.fw}x${info.fh}`);
			if (i === 0) ok('recorrido: Panel Ejecutivo apunta a su presentación', info.href.endsWith('/features/analytics#panel-ejecutivo'), info.href);
		}
		ok('recorrido: 7 vistas distintas', seen.size === 7, [...seen].join(', '));
		ok('recorrido: altura estable al cambiar de pestaña', heights.size === 1, [...heights].join(','));
		ok('recorrido: marco uniforme', frames.size === 1, [...frames].join(','));
		await page.click('#tt-0');
		const [nav] = await Promise.all([page.waitForNavigation(), page.click('#tp-0 .s-btn')]);
		ok('«Conocer el módulo» abre la presentación del Panel Ejecutivo', page.url().includes('/features/analytics#panel-ejecutivo') && await page.isVisible('#panel-ejecutivo .s-pv'), page.url());
		await page.goto(BASE + '/', { waitUntil: 'load' });
		if (vp.name === 'desktop') {
			await page.locator('#tour').scrollIntoViewIfNeeded();
			await page.waitForTimeout(600);
			await page.click('#tp-0 .s-pv__zoom', { force: true });
			ok('ampliar vista abre el diálogo', await page.isVisible('#s-zoombox'));
			await page.keyboard.press('Escape');
		}
		await page.click('#tt-3');
		await page.waitForTimeout(350);
		ok('recorrido: pestaña Mermas', await page.isVisible('#tp-3') && !(await page.isVisible('#tp-0')));
		await page.focus('#tt-3');
		await page.keyboard.press('ArrowRight');
		ok('recorrido: flecha derecha', await page.isVisible('#tp-4'));

		// Selector industria / necesidad
		await page.click('#seg-t2');
		ok('selector por necesidad', await page.isVisible('#seg-need') && !(await page.isVisible('#seg-ind')));

		// Calculadora
		const before = await page.textContent('#roi-total');
		await page.fill('#roi-form [name=sales]', '1000000');
		const after = await page.textContent('#roi-total');
		ok('calculadora recalcula', before !== after && /\d/.test(after), `${before} -> ${after}`);
		await page.evaluate(() => document.querySelector('.s-calc__assume').open = true);
		await page.fill('#roi-form [name=a_license]', '1000');
		ok('calculadora muestra retorno con inversión', await page.isVisible('#roi-return'), await page.textContent('#roi-return'));
		await page.fill('#roi-form [name=sales]', '0');
		ok('calculadora acepta cero sin NaN', !(await page.textContent('#roi-total')).includes('NaN'));

		// Idioma
		await page.goto(BASE + '/?lang=en', { waitUntil: 'load' });
		ok('idioma EN', (await page.getAttribute('html', 'lang')) === 'en' && (await page.textContent('h1')).includes('Intelligence'));
		await page.goto(BASE + '/pricing', { waitUntil: 'load' });
		ok('idioma EN persiste (cookie)', (await page.getAttribute('html', 'lang')) === 'en');
		if (vp.name === 'desktop') await page.screenshot({ path: path.join(OUT, 'pricing_en_desktop.png') });
		await page.goto(BASE + '/?lang=es', { waitUntil: 'load' });

		// Formulario: validaciones
		await page.goto(BASE + '/demo?plan=business', { waitUntil: 'load' });
		ok('plan precargado en mensaje', (await page.inputValue('[name=message]')).includes('Business'));
		await page.click('.s-form button[type=submit]');
		const invalid = await page.$$eval('.s-field.is-invalid', e => e.length);
		ok('validación bloquea envío vacío', invalid >= 6, `${invalid} campos marcados`);
		ok('no muestra confirmación sin enviar', !(await page.isVisible('.s-form__status.is-ok')));
		await page.fill('[name=email]', 'correo-malo');
		await page.locator('[name=email]').blur();
		ok('valida formato de correo', (await page.textContent('.s-field:has([name=email]) .s-err')).length > 3);
		if (vp.name === 'mobile') await page.screenshot({ path: path.join(OUT, 'demo_validation_mobile.png'), fullPage: false });

		// Formulario: error de backend no muestra confirmación
		await page.route('**/site/lead', r => r.fulfill({ status: 503, contentType: 'application/json', body: '{"ok":false}' }));
		await page.fill('[name=name]', 'Prueba E2E');
		await page.fill('[name=company]', 'Empresa Ficticia');
		await page.fill('[name=email]', 'e2e@example.com');
		await page.fill('[name=phone]', '+57 300 000 0000');
		await page.selectOption('[name=country]', { index: 4 });
		await page.selectOption('[name=industry]', 'retail');
		await page.selectOption('[name=branches]', '2-5');
		await page.click('.s-form button[type=submit]');
		await page.waitForSelector('.s-form__status.is-error');
		ok('error del servidor: sin confirmación y botón reactivado', !(await page.isVisible('.s-form__status.is-ok')) && await page.isEnabled('.s-form button[type=submit]'));
		await page.unroute('**/site/lead');

		// Formulario: envío real (solo una vez, en escritorio)
		if (vp.name === 'desktop') {
			await page.click('.s-form button[type=submit]');
			await page.waitForSelector('.s-form__status.is-ok', { timeout: 10000 });
			ok('envío real guarda y confirma', true, await page.textContent('.s-form__status'));
			await page.screenshot({ path: path.join(OUT, 'demo_sent_desktop.png') });
		}

		await ctx.close();
	}

	// Validación del servidor
	const ctx = await browser.newContext();
	const r1 = await ctx.request.post(BASE + '/site/lead', { form: { type: 'demo', name: 'x', email: 'malo' } });
	ok('servidor rechaza datos inválidos (422)', r1.status() === 422, await r1.text());
	const r2 = await ctx.request.post(BASE + '/site/lead', { form: { website: 'spam', name: 'bot' } });
	ok('campo trampa no guarda', r2.status() === 200);
	await browser.close();

	fs.writeFileSync(path.join(OUT, 'results.json'), JSON.stringify(results, null, 1));
	const fails = results.filter(r => !r.pass);
	console.log(`\n${results.length - fails.length}/${results.length} OK`);
	process.exit(fails.length ? 1 : 0);
})().catch(e => { console.error(e); process.exit(2); });
