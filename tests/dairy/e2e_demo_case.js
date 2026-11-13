// E2E del caso demo Yogur natural 500 g (DAI-YOG-500 · LAC-001 · Hallandale Beach) en el navegador (emulación, msedge):
// restablece, avanza hasta antes del cierre, revisa HOY y la franja en cada tablero, abre la alerta exacta, completa, reintenta
// la aprobación y revisa cifras, anchos móviles (360/390/430) sin desplazamiento horizontal y la barra inferior.
// Uso: PW_DIR=<ruta de playwright> node tests/dairy/e2e_demo_case.js <carpeta_salida>
const fs = require('fs');
const path = require('path');
const pwDir = process.env.PW_DIR || 'C:\\Users\\renzo\\.antigravity-ide\\extensions\\serkan-ozal.browser-devtools-mcp-vscode-0.7.0-universal\\node_modules\\playwright';
const { chromium } = require(pwDir);
const BASE = process.env.DAILY_URL || 'http://localhost:8080';
const out = process.argv[2] || '.';
fs.mkdirSync(out, { recursive: true });
let fails = 0, count = 0;
const ok = (name, cond, info) => { count++; if (!cond) fails++; console.log((cond ? '  OK   ' : '  FAIL ') + name + (info !== undefined ? '  [' + JSON.stringify(info).slice(0, 220) + ']' : '')); };

(async () => {
	const browser = await chromium.launch({ channel: 'msedge' });
	const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 } });
	const page = await ctx.newPage();
	const errors = [];
	page.on('pageerror', e => errors.push(page.url().replace(BASE, '') + ' :: ' + e.message.slice(0, 160)));
	const go = async (u) => { const r = await page.goto(BASE + u, { waitUntil: 'domcontentloaded', timeout: 180000 }); await page.waitForTimeout(1500); return r ? r.status() : 0; };
	// Si el relleno de horas simuladas de la demo corre en segundo plano, el caso responde «se está actualizando»: se reintenta
	const api = async (action) => {
		for (let i = 0; i < 40; i++)
		{
			const r = await page.evaluate(async (a) => { const r = await fetch('/api/demo/case/' + a, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: '{}' }); return { status: r.status, body: await r.json() }; }, action);
			if (r.status === 200 || !/actualizando|locked/.test(r.body.error_message || '')) return r;
			await page.waitForTimeout(10000);
		}
		return { status: 0, body: 'relleno demo sin terminar' };
	};
	const summary = () => page.evaluate(async () => (await fetch('/api/demo/case')).json());
	const strip = async () => (await page.locator('[data-testid="demo-case-strip"]').count()) ? (await page.locator('[data-testid="demo-case-strip"]').innerText()).replace(/\s+/g, ' ') : null;

	console.log('\n1. Restablecer y estado previo al cierre');
	ok('Página del caso', await go('/demo/case') === 200);
	let r = await api('reset');
	ok('Restablecer', r.status === 200, r.body);
	let s = await summary();
	ok('Estado inicial: sin pasos ni stock', s.steps.length === 0 && s.stock.qty === 0);
	r = await api('pre_close');
	ok('Avanzar hasta antes del cierre', r.status === 200 && r.body.next === 'approve', r.body);
	s = await summary();
	ok('Pre-cierre: 12 pendientes, 6 bloqueadas, merma $12 por aprobar', s.po.pending === 12 && s.stock.blocked === 6 && s.waste.pending_cost === 12, [s.po, s.stock, s.waste]);
	await go('/demo/case');
	await page.screenshot({ path: path.join(out, 'caso_preclose_desktop.png'), fullPage: true });

	console.log('\n2. HOY: prioridades del caso y enlace al registro');
	await go('/today');
	let t = await strip();
	ok('HOY muestra recepción parcial, tarea FEFO y merma por aprobar', !!t && /Recepción parcial/.test(t) && /FEFO/.test(t) && /por aprobar/.test(t), t);
	const wasteHref = await page.locator('[data-testid="demo-case-strip"] a', { hasText: 'por aprobar' }).first().getAttribute('href');
	ok('La prioridad de merma abre la cola de aprobación', /\/waste\/approvals/.test(wasteHref || ''), wasteHref);

	console.log('\n3. Centro de Alertas: abre la alerta exacta');
	await go('/alerts?alert=' + s.refs.alert_receipt_id);
	await page.waitForTimeout(2500);
	const panelId = await page.locator('#panelDetailId').innerText().catch(() => '');
	ok('Detalle = alerta de la recepción parcial', panelId.indexOf(String(s.refs.alert_receipt_id)) >= 0, panelId);
	const title = await page.locator('#panelDetailTitle').innerText().catch(() => '');
	ok('Título con la orden', title.indexOf(s.refs.po_code) >= 0, title);

	console.log('\n4. Franja del caso en cada tablero');
	for (const [u, re] of [['/suppliers', /60 pedidas · 48 recibidas · 12 pendientes/], ['/stockoverview', /Existencia 18 u\./], ['/kardex', /Existencia 18 u\./], ['/traceability', /bloqueadas 6/],
		['/sales', /\$105\.00 · 30 u\./], ['/orders', /entregado, \$14\.00/], ['/customers/8', /4 u\. por \$14\.00/], ['/waste', /\$12\.00 por aprobar/], ['/kpis', /ventas \$105\.00 · costo \$60\.00/], ['/cost-centers', /utilidad bruta \$45\.00 · merma \$0\.00 \(\+\$12\.00 por aprobar\)/]])
	{
		const st = await go(u);
		t = await strip();
		ok(u + ' muestra el caso', st === 200 && !!t && re.test(t), t);
	}

	console.log('\n5. Aprobación, reintento y cierre');
	r = await api('approve');
	ok('Aprobar la merma', r.status === 200, r.body);
	r = await api('approve');
	ok('Reintentar la aprobación no hace nada', r.status === 200 && r.body.ran.length === 0, r.body);
	r = await api('all');
	s = await summary();
	ok('Una sola salida de merma', s.waste.moves === 1, s.waste);
	ok('12 u. disponibles por $24.00 y 12 pendientes en la orden', s.stock.available === 12 && s.stock.value === 24 && s.po.pending === 12, [s.stock, s.po]);
	ok('Ventas $105 · costo $60 · utilidad $45 · merma $12 · resultado $13', s.pnl.net === 105 && s.pnl.cogs === 60 && s.pnl.gross === 45 && s.pnl.waste === 12 && s.pnl.result === 13, s.pnl);
	await go('/today');
	t = await strip();
	ok('HOY actualiza el estado de la merma (aprobada)', !!t && /aprobada/.test(t), t);
	await go('/demo/case');
	await page.screenshot({ path: path.join(out, 'caso_cierre_desktop.png'), fullPage: true });

	console.log('\n6. Móvil (emulación): 360, 390 y 430 px');
	for (const w of [360, 390, 430])
	{
		await page.setViewportSize({ width: w, height: 800 });
		for (const u of ['/demo/case', '/today', '/sales', '/waste'])
		{
			await go(u);
			const m = await page.evaluate(() => ({ sw: document.documentElement.scrollWidth, cw: document.documentElement.clientWidth,
				nav: [...document.querySelectorAll('#dairyBottomNav .dairy-bottom-nav-item span:last-child')].map(e => e.textContent.trim()), navVisible: (() => { const n = document.querySelector('#dairyBottomNav'); if (!n) return false; const b = n.getBoundingClientRect(); return getComputedStyle(n).display !== 'none' && b.height > 0 && b.bottom <= innerHeight + 1; })() }));
			ok(`${w}px ${u}: sin desplazamiento horizontal`, m.sw <= m.cw + 1, m);
			if (u === '/today') ok(`${w}px barra inferior HOY · Alertas · Escanear · Tareas · Más`, m.navVisible && m.nav.join('|') === 'HOY|Alertas|Escanear|Tareas|Más', m.nav);
		}
		await go('/demo/case');
		await page.screenshot({ path: path.join(out, `caso_${w}.png`), fullPage: true });
	}
	ok('Sin errores de JavaScript', errors.length === 0, errors.slice(0, 5));
	await browser.close();
	console.log(`\n${count} verificaciones, ${fails} fallas`);
	process.exit(fails ? 1 : 0);
})().catch(e => { console.error(e); process.exit(2); });
