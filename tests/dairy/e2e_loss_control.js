// DAILY — E2E de Prevención de Pérdidas en escritorio y móvil.
// Uso: node tests/dairy/e2e_loss_control.js <ruta a node_modules/playwright> [http://localhost:8080]
const { chromium } = require(process.argv[2]);
const base = process.argv[3] || 'http://localhost:8080';
const shots = 'C:/Users/renzo/grocy/grocy/.playwright-mcp/';

(async () => {
	const browser = await chromium.launch({ channel: 'msedge' });
	let fails = 0;
	const ok = (label, cond, info = '') => { if (!cond) fails++; console.log((cond ? '  OK   ' : '  FAIL ') + label + (info !== '' ? `  [${info}]` : '')); };

	for (const [name, viewport] of [['escritorio', { width: 1366, height: 900 }], ['móvil', { width: 390, height: 844 }]])
	{
		console.log('\n' + name);
		const ctx = await browser.newContext({ viewport, isMobile: name === 'móvil' });
		const page = await ctx.newPage();
		const errors = [];
		page.on('pageerror', e => errors.push(e.message));
		const overflow = () => page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);

		await page.goto(base + '/loss-prevention', { waitUntil: 'domcontentloaded', timeout: 90000 });
		await page.waitForSelector('.lp-kpis', { timeout: 30000 });
		await page.waitForTimeout(1200);
		const text = await page.innerText('.lp-page');
		ok('dashboard muestra los dos casos', text.includes('DEMO-001') && text.includes('DEMO-002'));
		ok('integración de cámaras no se declara conectada', /Cámaras de seguridad\s+No integrado/i.test(text));
		ok('sin scroll horizontal', (await overflow()) <= 1, await overflow());
		ok('los gráficos se dibujan', await page.evaluate(() => { const c = document.getElementById('lpProducts'); return !!c && c.getContext('2d').getImageData(0, 0, c.width, c.height).data.some(v => v !== 0); }));
		await page.screenshot({ path: `${shots}lp_dashboard_${name}.png`, fullPage: true });

		await page.click('text=DEMO-001');
		await page.waitForSelector('.lp-steps', { timeout: 30000 });
		const c = await page.innerText('.lp-page');
		ok('expediente: 8 esperado, 6 físico, -2, $1,040', c.includes('$1,040.00') && /Diferencia\s+-2/.test(c));
		ok('estado en investigación marcado', (await page.innerText('.lp-steps li.now')).includes('En investigación'));
		ok('aclara que registrar no es ser responsable', c.includes('no es responsable'));
		ok('sin scroll horizontal en expediente', (await overflow()) <= 1, await overflow());
		await page.screenshot({ path: `${shots}lp_case_${name}.png`, fullPage: true });

		await page.goto(base + '/loss-prevention/counts', { waitUntil: 'domcontentloaded', timeout: 60000 });
		await page.waitForSelector('.lp-page');
		ok('conteos sin stock esperado a la vista', !(await page.innerText('.lp-page')).match(/esperad[oa]s?:/i));
		ok('sin scroll horizontal en conteos', (await overflow()) <= 1, await overflow());
		await page.screenshot({ path: `${shots}lp_counts_${name}.png`, fullPage: true });

		for (const path of ['/alerts', '/waste', '/kpis', '/stockoverview', '/inventory-control'])
		{
			const r = await page.goto(base + path, { waitUntil: 'domcontentloaded', timeout: 120000 });
			ok(`${path} responde`, r.status() < 400, r.status());
			if (path !== '/alerts')
			{
				ok(`${path} muestra la franja común`, await page.locator('[data-testid=loss-strip]').count() === 1);
			}
		}
		ok('sin errores de JavaScript', errors.length === 0, errors.slice(0, 2).join(' | '));
		await ctx.close();
	}

	// Flujo real con escáner: reconteo de audífonos (Caso 002), con UPC de otro producto primero
	console.log('\nFlujo de reconteo');
	const page = await (await browser.newContext({ viewport: { width: 390, height: 844 }, isMobile: true })).newPage();
	await page.goto(base + '/loss-prevention/counts', { waitUntil: 'domcontentloaded', timeout: 60000 });
	await page.waitForSelector('.lp-task');
	await page.click('.lp-task:has-text("Audífonos inalámbricos Pro")');
	await page.fill('#lpBarcode', '0000000000000');
	await page.fill('#lpQty', '8');
	const submit = async () => {
		const [res] = await Promise.all([
			page.waitForResponse(r => r.url().includes('/loss-control/tasks/') && r.request().method() === 'POST', { timeout: 20000 }),
			page.click('button:has-text("Confirmar conteo")')
		]);
		return { status: res.status(), body: await res.json().catch(() => null) };
	};
	const bad = await submit();
	ok('UPC ajeno rechazado', bad.status === 400, JSON.stringify(bad.body));
	await page.fill('#lpBarcode', process.argv[4] || '');
	const good = await submit();
	ok('reconteo registrado', good.status === 200, JSON.stringify(good.body));
	const api = (path, data) => page.evaluate(async ([p, d]) => {
		const r = await fetch('/api/' + p, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(d) });
		return { status: r.status, body: await r.json().catch(() => null) };
	}, [path, data]);
	const caseId = process.argv[5];
	const proposed = await api(`loss-control/cases/${caseId}/propose`, { reason: 'Prueba de extremo a extremo' });
	ok('propuesta de ajuste por API', proposed.status === 200, JSON.stringify(proposed.body));
	const decided = await api(`loss-control/cases/${caseId}/decide`, { decision: 'approved', reason: 'Prueba de extremo a extremo' });
	ok('aprobación por API', decided.status === 200, JSON.stringify(decided.body));
	const legacy = await api('reconciliation/count', { branch_id: 1, product_id: 9001, counted_qty: 6, count_type: 'sensible' });
	ok('conteo por la API de Conciliación concilia tras el ajuste', legacy.status === 200 && legacy.body.difference_qty === 0, JSON.stringify(legacy.body).slice(0, 160));
	await browser.close();
	console.log(`\n${fails} fallos`);
	process.exit(fails ? 1 : 0);
})().catch(e => { console.error(e); process.exit(2); });
