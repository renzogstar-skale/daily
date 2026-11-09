// E2E de suscripciones por plan con sesión real (sandbox de tests/dairy/http_subscription.php --serve).
// Por plan: recorre cada tablero del menú y comprueba que abre con contenido, sin errores de JS ni llamadas a APIs
// fuera del plan (403 plan_required), que las herramientas internas bloqueadas no aparecen, que «Ampliar plan» lleva a la
// pantalla de plan requerido con «Ver planes», y que la app móvil carga sin solicitudes bloqueadas.
// Uso: PW_DIR=.../node_modules/playwright node tests/dairy/e2e_subscription_plans.js [salida.json] [planes,separados]
const fs = require('fs');
const path = require('path');
const { spawn } = require('child_process');
const { chromium } = require(process.env.PW_DIR || 'playwright');
const ROOT = path.resolve(__dirname, '../..');
const OUT = process.argv[2] || 'e2e_subscription_plans.json';
const PLANS = (process.argv[3] || 'essential,professional,business,enterprise').split(',');
const res = { at: new Date().toISOString(), plans: {}, fails: [] };
const fail = (ctx, msg) => { res.fails.push(ctx + ' · ' + msg); console.log('FAIL ' + ctx + ' · ' + msg); };

(async () => {
	const srv = spawn('php', ['tests/dairy/http_subscription.php', '--serve'], { cwd: ROOT });
	const ready = await new Promise((ok, ko) => {
		let buf = '';
		srv.stdout.on('data', d => { buf += d; const m = buf.match(/READY (\d+) (\S+) (\S+)/); if (m) ok(m); });
		srv.on('exit', c => ko(new Error('sandbox terminó ' + c + ': ' + buf)));
	});
	const BASE = 'http://127.0.0.1:' + ready[1];
	const PASS = ready[3];
	const browser = await chromium.launch({ channel: 'msedge' });
	try {
		for (const plan of PLANS) {
			const r = res.plans[plan] = { pages: [], mobile: null };
			const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 } });
			const page = await ctx.newPage();
			let blocked = [], errors = [];
			page.on('response', async resp => {
				if (resp.status() === 403 && resp.url().includes('/api/')) {
					try { const j = await resp.json(); if (j.code === 'plan_required') blocked.push(new URL(resp.url()).pathname + ' → ' + j.required_plan); } catch (e) { }
				}
			});
			page.on('pageerror', e => errors.push(String(e.message).slice(0, 160)));
			await page.goto(BASE + '/login');
			await page.fill('#username', plan);
			await page.fill('#password_input', PASS);
			await Promise.all([page.waitForNavigation({ timeout: 90000 }), page.click('#login-button')]);

			// Menú principal (sin «Ampliar plan»)
			await page.goto(BASE + '/stockoverview', { waitUntil: 'domcontentloaded', timeout: 120000 });
			const menu = await page.$$eval('#dairySidenav .dnav-cat:not(.dnav-upgrade) a.dnav-link[href]', as => [...new Set(as.map(a => a.getAttribute('href')).filter(h => h && !h.startsWith('#') && !h.includes('#sub-nav')))]);
			const locked = await page.$$eval('#dairySidenav .dnav-upgrade .dnav-locked a', as => as.map(a => a.getAttribute('href')));
			r.menu = menu.length; r.locked = locked.length;
			for (const href of menu) {
				blocked = []; errors = [];
				const url = href.startsWith('http') ? href : BASE + href;
				let status = 0;
				try {
					const resp = await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 120000 });
					status = resp ? resp.status() : 0;
					await page.waitForLoadState('networkidle', { timeout: 15000 }).catch(() => { });
				} catch (e) { fail(plan + ' ' + href, 'navegación: ' + e.message.slice(0, 120)); continue; }
				const probe = await page.evaluate(() => {
					const pc = document.getElementById('page-content');
					const text = pc ? pc.innerText.trim() : '';
					const sel = (window.DAILY_SUBSCRIPTION && window.DAILY_SUBSCRIPTION.lockedSelectors) || [];
					let visibleLocked = 0;
					try { if (sel.length) visibleLocked = [...document.querySelectorAll(sel.join(','))].filter(el => el.getBoundingClientRect().height > 0 && !el.closest('#dairySidenav')).length; } catch (e) { }
					return { chars: text.length, lock: !!document.querySelector('[data-testid="plan-required"]'), visibleLocked, fatal: /(Fatal error|Stack trace|SQLSTATE|Error source)/.test(text.slice(0, 4000)) };
				});
				const entry = { href, status, ...probe, blocked: [...new Set(blocked)], errors: [...new Set(errors)] };
				r.pages.push(entry);
				if (status >= 400 || probe.lock) fail(plan + ' ' + href, 'tablero autorizado no abre (' + status + ')');
				if (probe.chars < 120 || probe.fatal) fail(plan + ' ' + href, 'contenido vacío o error (' + probe.chars + ' car.)');
				if (entry.blocked.length) fail(plan + ' ' + href, 'llama APIs fuera del plan: ' + entry.blocked.join(', '));
				if (probe.visibleLocked) fail(plan + ' ' + href, probe.visibleLocked + ' herramientas bloqueadas visibles');
				if (entry.errors.length) fail(plan + ' ' + href, 'JS: ' + entry.errors.join(' | '));
			}
			// «Ampliar plan» → pantalla de plan requerido → Ver planes
			if (locked.length) {
				await page.goto(locked[0].startsWith('http') ? locked[0] : BASE + locked[0], { waitUntil: 'domcontentloaded' });
				const ok = await page.$('[data-testid="plan-required"] [data-testid="see-plans"]');
				if (!ok) fail(plan, 'enlace de ampliación sin pantalla de plan requerido');
				else {
					await Promise.all([page.waitForNavigation(), ok.click()]);
					if (!(await page.$('#planes'))) fail(plan, '«Ver planes» no lleva a la comparación');
				}
			} else if (plan !== 'enterprise') fail(plan, 'sin sección de ampliación');
			await ctx.close();

			// Móvil
			const mctx = await browser.newContext({ viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true, storageState: undefined });
			const mp = await mctx.newPage();
			let mblocked = [], merr = [];
			mp.on('response', async resp => { if (resp.status() === 403 && resp.url().includes('/api/')) { try { const j = await resp.json(); if (j.code === 'plan_required') mblocked.push(new URL(resp.url()).pathname); } catch (e) { } } });
			mp.on('pageerror', e => merr.push(String(e.message).slice(0, 160)));
			await mp.goto(BASE + '/login');
			await mp.fill('#username', plan);
			await mp.fill('#password_input', PASS);
			await Promise.all([mp.waitForNavigation({ timeout: 90000 }), mp.click('#login-button')]);
			for (const hash of ['', '#/ops', '#/profile']) {
				await mp.goto(BASE + '/m' + hash, { waitUntil: 'domcontentloaded', timeout: 120000 });
				await mp.waitForLoadState('networkidle', { timeout: 15000 }).catch(() => { });
			}
			const mchars = await mp.evaluate(() => document.body.innerText.trim().length);
			r.mobile = { chars: mchars, blocked: [...new Set(mblocked)], errors: [...new Set(merr)] };
			if (mchars < 100) fail(plan + ' móvil', 'vacío');
			if (r.mobile.blocked.length) fail(plan + ' móvil', 'llama APIs fuera del plan: ' + r.mobile.blocked.join(', '));
			if (r.mobile.errors.length) fail(plan + ' móvil', 'JS: ' + r.mobile.errors.join(' | '));
			await mctx.close();
			console.log(plan + ': ' + r.pages.length + ' tableros, ' + r.locked + ' en ampliación');
		}
	} finally {
		await browser.close();
		fs.writeFileSync(OUT, JSON.stringify(res, null, 1));
		srv.stdin.end();
	}
	console.log('\n' + res.fails.length + ' fallas');
	process.exit(res.fails.length ? 1 : 0);
})().catch(e => { console.error(e); process.exit(2); });
